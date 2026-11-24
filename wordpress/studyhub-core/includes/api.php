<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   STUDYHUB API (версия 1) — для плагинов StudyHub.
   Новый плагин подключается к этим точкам и сразу работает, ядро обновлять не нужно.
   Подробно: EXTENDING.md рядом с плагинами.

   - add_action('studyhub_ready', fn($api))         — все плагины загружены, можно подключаться;
   - studyhub_register_plugin(__FILE__, [...])       — плагин виден в панели (Настройки → Дополнения);
   - studyhub_asset_url('file.css', __FILE__)        — адрес файла из папки assets плагина;
   - studyhub_event('имя', [...]) / add_action('studyhub_event', fn($name, $data), 10, 2);
   - адреса: фильтр studyhub_routes  ['ключ' => ['slug' => 'app', 'render' => callable, 'front' => false]];
   - панель: фильтр studyhub_panel_sections (свой раздел с полями настроек);
   - сайт: фильтры studyhub_site_pages, studyhub_site_home_sections, действия studyhub_site_head / studyhub_site_foot;
   - кабинет студента: фильтр studyhub_app_config (данные для JS).
   ============================================================ */

if ( ! defined( 'STUDYHUB_API' ) ) {
	define( 'STUDYHUB_API', 1 );
}

function &studyhub_plugins_ref() {
	static $reg = array();
	return $reg;
}

function studyhub_register_plugin( $file, $args = array() ) {
	$reg = &studyhub_plugins_ref();
	$hd  = function_exists( 'get_file_data' ) ? get_file_data( $file, array( 'name' => 'Plugin Name', 'ver' => 'Version', 'desc' => 'Description' ) ) : array();
	$reg[ plugin_basename( $file ) ] = array(
		'file' => $file,
		'name' => isset( $args['name'] ) ? $args['name'] : ( ! empty( $hd['name'] ) ? $hd['name'] : basename( $file ) ),
		'ver'  => isset( $args['version'] ) ? $args['version'] : ( ! empty( $hd['ver'] ) ? $hd['ver'] : '' ),
		'desc' => ! empty( $hd['desc'] ) ? $hd['desc'] : '',
		'core' => ! empty( $args['core'] ),
	);
	return true;
}

function studyhub_plugins() {
	return studyhub_plugins_ref();
}

function studyhub_asset_url( $file, $plugin_file = STUDYHUB_CORE_FILE ) {
	$path = plugin_dir_path( $plugin_file ) . 'assets/' . $file;
	$ver  = file_exists( $path ) ? filemtime( $path ) : STUDYHUB_VER;
	return plugins_url( 'assets/' . $file, $plugin_file ) . '?ver=' . $ver;
}

studyhub_register_plugin( STUDYHUB_CORE_FILE, array( 'name' => 'StudyHub Ядро', 'version' => STUDYHUB_VER, 'core' => true ) );

/* «всё загружено» — после сайта (5), студента (20) и ИИ (20) */
add_action( 'plugins_loaded', 'studyhub_api_ready', 50 );
function studyhub_api_ready() {
	do_action( 'studyhub_ready', STUDYHUB_API );
}

/* ---------- события: заявка, сообщение, отзыв… ---------- */
function studyhub_event( $name, $data = array() ) {
	$name = sanitize_key( $name );
	do_action( 'studyhub_event', $name, (array) $data );
	do_action( 'studyhub_event_' . $name, (array) $data );
}

/* ---------- адреса страниц ---------- */
function studyhub_routes() {
	static $routes = null;
	if ( null === $routes || doing_action( 'init' ) ) {
		$routes = (array) apply_filters( 'studyhub_routes', array() );
	}
	return $routes;
}

function studyhub_rewrite_rules() {
	$sig = array();
	foreach ( studyhub_routes() as $key => $r ) {
		if ( empty( $r['slug'] ) ) {
			continue;
		}
		$slug = trim( $r['slug'], '/' );
		add_rewrite_rule( '^' . preg_quote( $slug, '#' ) . '/?$', 'index.php?studyhub_page=' . $key, 'top' );
		$sig[] = $key . '=' . $slug;
	}
	/* адреса поменялись (новый плагин, другой адрес в настройках) — обновляем правила сами */
	$sig = md5( implode( '|', $sig ) );
	if ( did_action( 'init' ) && get_option( 'studyhub_routes_sig' ) !== $sig ) {
		update_option( 'studyhub_routes_sig', $sig, true );
		add_action( 'wp_loaded', 'flush_rewrite_rules' );
	}
}

function studyhub_query_vars( $vars ) {
	$vars[] = 'studyhub_page';
	$vars[] = 'studyhub_sw';
	$vars[] = 'studyhub_manifest';
	return $vars;
}

function studyhub_url( $key ) {
	$routes = studyhub_routes();
	if ( ! isset( $routes[ $key ] ) ) {
		return home_url( '/' );
	}
	$r = $routes[ $key ];
	if ( ! empty( $r['front'] ) && studyhub_front_owner() === $key ) {
		return home_url( '/' );
	}
	if ( ! get_option( 'permalink_structure' ) || empty( $r['slug'] ) ) {
		return add_query_arg( 'studyhub_page', $key, home_url( '/' ) );
	}
	return home_url( '/' . trim( $r['slug'], '/' ) . '/' );
}

/** Какая страница StudyHub показывается на главной сайта (или '' — тема WordPress). */
function studyhub_front_owner() {
	foreach ( studyhub_routes() as $key => $r ) {
		if ( ! empty( $r['front'] ) ) {
			return $key;
		}
	}
	return '';
}

function studyhub_current_page() {
	static $cur = null;
	if ( null !== $cur ) {
		return $cur;
	}
	$cur  = '';
	$key  = get_query_var( 'studyhub_page' );
	if ( ! $key && isset( $_GET['studyhub_page'] ) ) { // phpcs:ignore
		$key = sanitize_key( wp_unslash( $_GET['studyhub_page'] ) ); // phpcs:ignore
	}
	$routes = studyhub_routes();
	if ( $key && isset( $routes[ $key ] ) ) {
		$cur = $key;
	} elseif ( ( is_front_page() || is_home() ) && ! is_paged() && studyhub_front_owner() ) {
		$cur = studyhub_front_owner();
	}
	return $cur;
}

function studyhub_route() {
	$sw = get_query_var( 'studyhub_sw' ) ? get_query_var( 'studyhub_sw' ) : ( isset( $_GET['studyhub_sw'] ) ? sanitize_key( wp_unslash( $_GET['studyhub_sw'] ) ) : '' ); // phpcs:ignore
	if ( $sw ) {
		studyhub_service_worker( $sw );
	}
	$mf = get_query_var( 'studyhub_manifest' ) ? get_query_var( 'studyhub_manifest' ) : ( isset( $_GET['studyhub_manifest'] ) ? sanitize_key( wp_unslash( $_GET['studyhub_manifest'] ) ) : '' ); // phpcs:ignore
	if ( $mf ) {
		studyhub_manifest( $mf );
	}
	$key = studyhub_current_page();
	if ( ! $key ) {
		return;
	}
	$routes = studyhub_routes();
	if ( empty( $routes[ $key ]['render'] ) || ! is_callable( $routes[ $key ]['render'] ) ) {
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
	status_header( 200 );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: same-origin' );
	call_user_func( $routes[ $key ]['render'], $key );
	exit;
}

/* ---------- общий словарь ru / kk ---------- */
function studyhub_lang() {
	$l = isset( $_COOKIE['studyhub_lang'] ) ? sanitize_key( wp_unslash( $_COOKIE['studyhub_lang'] ) ) : 'ru';
	return 'kk' === $l ? 'kk' : 'ru';
}

/** Текст из пары ['ru' => …, 'kk' => …] или настройки с суффиксом _ru/_kk. */
function studyhub_loc( $pair, $lang = null ) {
	$lang = $lang ? $lang : studyhub_lang();
	if ( is_array( $pair ) ) {
		return ! empty( $pair[ $lang ] ) ? $pair[ $lang ] : ( isset( $pair['ru'] ) ? $pair['ru'] : '' );
	}
	return (string) $pair;
}

/* ---------- общие данные для всех приложений ---------- */
function studyhub_public_services() {
	$out = array();
	foreach ( (array) StudyHub_Settings::get( 'services' ) as $svc ) {
		$out[] = array(
			'id'    => sanitize_key( $svc['id'] ),
			'icon'  => sanitize_key( $svc['icon'] ),
			'color' => sanitize_key( $svc['color'] ),
			'title' => array( 'ru' => $svc['title_ru'], 'kk' => $svc['title_kk'] ),
			'desc'  => array( 'ru' => $svc['desc_ru'], 'kk' => $svc['desc_kk'] ),
			'price' => (string) $svc['price'],
		);
	}
	return $out;
}

function studyhub_public_universities() {
	$out = array();
	foreach ( (array) StudyHub_Settings::get( 'universities' ) as $u ) {
		$out[] = array(
			'id'    => $u['id'],
			'short' => $u['short'],
			'name'  => array( 'ru' => $u['name_ru'], 'kk' => $u['name_kk'] ),
			'logo'  => $u['logo'],
		);
	}
	return $out;
}

function studyhub_review_shape( $r ) {
	return array(
		'id'         => (int) $r->id,
		'rating'     => (int) $r->rating,
		'text'       => $r->body,
		'university' => StudyHub_Settings::university_label( $r->university ),
		'service'    => $r->service,
		'time'       => (int) $r->created_at,
	);
}

function studyhub_public_reviews( $limit = 20 ) {
	$out = array();
	foreach ( StudyHub_DB::reviews( 'approved', $limit ) as $r ) {
		$out[] = studyhub_review_shape( $r );
	}
	return $out;
}

/** Общие поля конфигурации для сайта, кабинета и панели. */
function studyhub_base_config() {
	$s = StudyHub_Settings::all();
	return array(
		'brand'        => StudyHub_Settings::brand(),
		'tagline'      => array( 'ru' => $s['brand_tagline_ru'], 'kk' => $s['brand_tagline_kk'] ),
		'logo'         => $s['brand_logo'],
		'hero'         => array(
			'title' => array( 'ru' => $s['hero_title_ru'], 'kk' => $s['hero_title_kk'] ),
			'text'  => array( 'ru' => $s['hero_text_ru'], 'kk' => $s['hero_text_kk'] ),
			'image' => $s['hero_image'],
		),
		'services'     => studyhub_public_services(),
		'universities' => studyhub_public_universities(),
		'reviews'      => studyhub_public_reviews(),
		'reviewStats'  => StudyHub_DB::review_stats(),
		'adminOnline'  => StudyHub_Settings::admin_online(),
		'urls'         => array(
			'site'  => home_url( '/' ),
			'app'   => isset( studyhub_routes()['app'] ) ? studyhub_url( 'app' ) : '',
			'panel' => studyhub_url( 'panel' ),
		),
	);
}

/* публичные отзывы — нужны и сайту, и кабинету */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'studyhub/v1',
			'/reviews',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					return array(
						'reviews' => studyhub_public_reviews( 30 ),
						'stats'   => StudyHub_DB::review_stats(),
					);
				},
			)
		);
	}
);
