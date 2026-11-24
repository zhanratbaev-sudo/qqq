<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   АДМИН-ПАНЕЛЬ — отдельная страница (по умолчанию /panel/), как приложение:
   без темы и без wp-admin. Чаты, студенты, отзывы, услуги, настройки, разделы плагинов.
   ============================================================ */

add_filter(
	'studyhub_routes',
	function ( $routes ) {
		$routes['panel'] = array(
			'slug'   => StudyHub_Settings::slug( 'panel_slug', 'panel' ),
			'render' => 'studyhub_render_panel',
		);
		return $routes;
	},
	1
);

add_filter(
	'studyhub_pwa_apps',
	function ( $apps ) {
		$apps['panel'] = array(
			'name'   => StudyHub_Settings::brand() . ' · Панель',
			'start'  => studyhub_url( 'panel' ),
			'assets' => array( studyhub_asset_url( 'ui.js' ), studyhub_asset_url( 'panel.js' ), studyhub_asset_url( 'panel.css' ) ),
		);
		return $apps;
	}
);

/* вход администратора из wp-login.php ведёт сразу в панель, если он пришёл оттуда */
add_filter(
	'login_redirect',
	function ( $to, $requested, $user ) {
		if ( $user instanceof WP_User && studyhub_panel_user_ok( $user ) && false !== strpos( (string) $requested, '/' . StudyHub_Settings::slug( 'panel_slug', 'panel' ) ) ) {
			return studyhub_url( 'panel' );
		}
		return $to;
	},
	10,
	3
);

function studyhub_panel_config() {
	$user     = wp_get_current_user();
	$sections = array();
	foreach ( studyhub_panel_sections() as $key => $sec ) {
		$sections[] = array(
			'key'     => $key,
			'title'   => isset( $sec['title'] ) ? $sec['title'] : $key,
			'icon'    => isset( $sec['icon'] ) ? $sec['icon'] : 'grid',
			'intro'   => isset( $sec['intro'] ) ? $sec['intro'] : '',
			'fields'  => isset( $sec['fields'] ) ? $sec['fields'] : array(),
			'actions' => isset( $sec['actions'] ) ? $sec['actions'] : array(),
		);
	}
	$services = array();
	foreach ( (array) StudyHub_Settings::get( 'services' ) as $svc ) {
		$services[] = array( 'id' => $svc['id'], 'title' => $svc['title_ru'], 'icon' => $svc['icon'], 'color' => $svc['color'] );
	}
	return array(
		'brand'     => StudyHub_Settings::brand(),
		'logo'      => StudyHub_Settings::get( 'brand_logo' ),
		'rest'      => esc_url_raw( rest_url( 'studyhub/v1/' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'logged'    => is_user_logged_in(),
		'allowed'   => studyhub_panel_user_ok(),
		'userName'  => $user && $user->exists() ? $user->display_name : '',
		'services'  => $services,
		'sections'  => $sections,
		'swUrl'     => studyhub_sw_url(),
		'hasClient' => class_exists( 'StudyHub_Auth' ),
		'urls'      => array(
			'site'  => home_url( '/' ),
			'app'   => isset( studyhub_routes()['app'] ) ? studyhub_url( 'app' ) : '',
			'panel' => studyhub_url( 'panel' ),
			'wp'    => admin_url(),
			'media' => admin_url( 'upload.php' ),
		),
	);
}

function studyhub_render_panel() {
	$config = studyhub_panel_config();
	include STUDYHUB_CORE_DIR . 'templates/panel.php';
}
