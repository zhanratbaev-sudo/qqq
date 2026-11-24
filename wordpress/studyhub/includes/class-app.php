<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end app shell (PWA): standalone page at /app/, web manifest and service worker.
 */
class StudyHub_App {

	public static function init() {
		self::add_rewrite_rules();
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'route' ), 0 );
		add_shortcode( 'studyhub_app', array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_post_studyhub_file', array( __CLASS__, 'admin_file' ) );
	}

	public static function add_rewrite_rules() {
		$slug = preg_quote( StudyHub_Settings::app_slug(), '#' );
		add_rewrite_rule( '^' . $slug . '/?$', 'index.php?studyhub_app=1', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'studyhub_app';
		$vars[] = 'studyhub_sw';
		$vars[] = 'studyhub_manifest';
		return $vars;
	}

	public static function app_url() {
		if ( ! get_option( 'permalink_structure' ) ) {
			return add_query_arg( 'studyhub_app', '1', home_url( '/' ) );
		}
		return StudyHub_Settings::app_url();
	}

	/** Service worker lives at the site root (query URL) so its scope covers the app. */
	public static function sw_url() {
		return add_query_arg( 'studyhub_sw', '1', home_url( '/' ) );
	}

	public static function manifest_url() {
		return add_query_arg( 'studyhub_manifest', '1', home_url( '/' ) );
	}

	public static function route() {
		if ( get_query_var( 'studyhub_sw' ) || isset( $_GET['studyhub_sw'] ) ) { // phpcs:ignore
			self::service_worker();
		}
		if ( get_query_var( 'studyhub_manifest' ) || isset( $_GET['studyhub_manifest'] ) ) { // phpcs:ignore
			self::manifest();
		}
		$is_app = get_query_var( 'studyhub_app' ) || isset( $_GET['studyhub_app'] ); // phpcs:ignore
		if ( ! $is_app && StudyHub_Settings::get( 'app_on_front' ) && ( is_front_page() || is_home() ) && ! is_paged() ) {
			$is_app = true;
		}
		if ( $is_app ) {
			self::render();
		}
	}

	public static function icon_url( $size ) {
		$logo = StudyHub_Settings::get( 'brand_logo' );
		if ( $logo ) {
			return $logo;
		}
		return STUDYHUB_URL . 'assets/icons/icon-' . (int) $size . '.png';
	}

	public static function manifest() {
		$brand = StudyHub_Settings::brand();
		$logo  = StudyHub_Settings::get( 'brand_logo' );
		$icons = array();
		if ( $logo ) {
			$icons[] = array( 'src' => $logo, 'sizes' => 'any', 'type' => 'image/png', 'purpose' => 'any' );
		}
		foreach ( array( 192, 512 ) as $size ) {
			$icons[] = array( 'src' => STUDYHUB_URL . 'assets/icons/icon-' . $size . '.png', 'sizes' => $size . 'x' . $size, 'type' => 'image/png', 'purpose' => 'any' );
		}
		$icons[] = array( 'src' => STUDYHUB_URL . 'assets/icons/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' );

		$data = array(
			'name'             => $brand,
			'short_name'       => mb_substr( $brand, 0, 12 ),
			'description'      => StudyHub_Settings::get( 'brand_tagline_ru' ),
			'start_url'        => self::app_url(),
			'scope'            => home_url( '/' ),
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'background_color' => '#F4F2F0',
			'theme_color'      => '#F4F2F0',
			'lang'             => 'ru',
			'icons'            => $icons,
		);
		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public static function service_worker() {
		$version = STUDYHUB_VERSION . '-' . substr( md5( (string) filemtime( STUDYHUB_DIR . 'assets/app.js' ) . filemtime( STUDYHUB_DIR . 'assets/app.css' ) ), 0, 8 );
		$assets  = array(
			STUDYHUB_URL . 'assets/app.css?ver=' . STUDYHUB_VERSION,
			STUDYHUB_URL . 'assets/app.js?ver=' . STUDYHUB_VERSION,
			STUDYHUB_URL . 'assets/icons/icon-192.png',
		);
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		header( 'Cache-Control: no-cache' );
		$js = file_get_contents( STUDYHUB_DIR . 'assets/sw.js' ); // phpcs:ignore
		$js = str_replace(
			array( '__VERSION__', '__ASSETS__', '__APP_URL__' ),
			array( esc_js( $version ), wp_json_encode( $assets, JSON_UNESCAPED_SLASHES ), esc_url_raw( self::app_url() ) ),
			$js
		);
		echo $js; // phpcs:ignore
		exit;
	}

	public static function config() {
		$s       = StudyHub_Settings::all();
		$student = StudyHub_Auth::current_student();
		$reviews = array();
		foreach ( StudyHub_DB::reviews( 'approved', 20 ) as $r ) {
			$reviews[] = StudyHub_REST::review_shape( $r );
		}
		$services = array();
		foreach ( (array) $s['services'] as $svc ) {
			$services[] = array(
				'id'    => sanitize_key( $svc['id'] ),
				'icon'  => sanitize_key( $svc['icon'] ),
				'color' => sanitize_key( $svc['color'] ),
				'title' => array( 'ru' => $svc['title_ru'], 'kk' => $svc['title_kk'] ),
				'desc'  => array( 'ru' => $svc['desc_ru'], 'kk' => $svc['desc_kk'] ),
				'price' => (string) $svc['price'],
			);
		}
		$unis = array();
		foreach ( (array) $s['universities'] as $u ) {
			$unis[] = array(
				'id'    => $u['id'],
				'short' => $u['short'],
				'name'  => array( 'ru' => $u['name_ru'], 'kk' => $u['name_kk'] ),
				'logo'  => $u['logo'],
			);
		}

		$config = array(
			'brand'           => StudyHub_Settings::brand(),
			'tagline'         => array( 'ru' => $s['brand_tagline_ru'], 'kk' => $s['brand_tagline_kk'] ),
			'logo'            => $s['brand_logo'],
			'icon'            => self::icon_url( 192 ),
			'hero'            => array(
				'title' => array( 'ru' => $s['hero_title_ru'], 'kk' => $s['hero_title_kk'] ),
				'text'  => array( 'ru' => $s['hero_text_ru'], 'kk' => $s['hero_text_kk'] ),
				'image' => $s['hero_image'],
			),
			'welcome'         => array( 'ru' => $s['welcome_ru'], 'kk' => $s['welcome_kk'] ),
			'rest'            => esc_url_raw( rest_url( StudyHub_REST::NS . '/' ) ),
			'appUrl'          => self::app_url(),
			'swUrl'           => self::sw_url(),
			'adminOnline'     => StudyHub_Settings::admin_online(),
			'fallbackMinutes' => max( 1, (int) $s['fallback_minutes'] ),
			'maxUploadMb'     => (int) $s['max_upload_mb'],
			'accept'          => '.' . implode( ',.', array_keys( StudyHub_Files::allowed_types() ) ),
			'universities'    => $unis,
			'services'        => $services,
			'reviews'         => $reviews,
			'reviewStats'     => StudyHub_DB::review_stats(),
			'student'         => $student ? StudyHub_REST::student_shape( $student ) : null,
			'unread'          => $student ? StudyHub_DB::unread_for_student( $student->id ) : 0,
			// Filled by the StudyHub AI add-on: { enabled, endpoint }.
			'ai'              => array( 'enabled' => false ),
		);
		return apply_filters( 'studyhub_app_config', $config, $student );
	}

	public static function render() {
		nocache_headers();
		status_header( 200 );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: no-referrer' );
		$config = self::config();
		include STUDYHUB_DIR . 'templates/app.php';
		exit;
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'height' => '820' ), $atts );
		$url  = self::app_url();
		return sprintf(
			'<div class="studyhub-embed" style="max-width:430px;margin:0 auto;"><iframe src="%1$s" style="width:100%%;height:%2$dpx;border:0;border-radius:36px;box-shadow:0 30px 60px -20px rgba(30,20,10,.25)" title="%3$s" loading="lazy"></iframe><p style="text-align:center"><a href="%1$s">%4$s</a></p></div>',
			esc_url( $url ),
			(int) $atts['height'],
			esc_attr( StudyHub_Settings::brand() ),
			esc_html__( 'Открыть приложение', 'studyhub' )
		);
	}

	/** Files for the admin panel (cookie + nonce auth). */
	public static function admin_file() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'studyhub_file' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		$file = StudyHub_DB::get_file( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 );
		if ( ! $file ) {
			wp_die( 'Not found', 404 );
		}
		StudyHub_Files::stream( $file, ! empty( $_GET['download'] ) );
	}
}
