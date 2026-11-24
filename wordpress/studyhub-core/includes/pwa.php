<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   PWA: каждое приложение (панель, кабинет студента) — свой манифест и значок,
   service worker общий. Приложения добавляются фильтром studyhub_pwa_apps:
   'ключ' => ['name' => …, 'start' => url, 'assets' => [url, …]]
   ============================================================ */

function studyhub_pwa_apps() {
	return (array) apply_filters( 'studyhub_pwa_apps', array() );
}

function studyhub_sw_url() {
	return add_query_arg( 'studyhub_sw', '1', home_url( '/' ) );
}

function studyhub_manifest_url( $key ) {
	return add_query_arg( 'studyhub_manifest', $key, home_url( '/' ) );
}

function studyhub_icon_url( $size = 192 ) {
	$logo = StudyHub_Settings::get( 'brand_logo' );
	if ( $logo ) {
		return $logo;
	}
	return STUDYHUB_CORE_URL . 'assets/icons/icon-' . ( 512 === (int) $size ? 512 : 192 ) . '.png';
}

function studyhub_manifest( $key ) {
	$apps = studyhub_pwa_apps();
	if ( ! isset( $apps[ $key ] ) ) {
		status_header( 404 );
		exit;
	}
	$app   = $apps[ $key ];
	$logo  = StudyHub_Settings::get( 'brand_logo' );
	$icons = array();
	if ( $logo ) {
		$icons[] = array( 'src' => $logo, 'sizes' => 'any', 'purpose' => 'any' );
	}
	foreach ( array( 192, 512 ) as $size ) {
		$icons[] = array( 'src' => STUDYHUB_CORE_URL . 'assets/icons/icon-' . $size . '.png', 'sizes' => $size . 'x' . $size, 'type' => 'image/png', 'purpose' => 'any' );
	}
	$icons[] = array( 'src' => STUDYHUB_CORE_URL . 'assets/icons/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' );
	$name = isset( $app['name'] ) ? $app['name'] : StudyHub_Settings::brand();
	$data = array(
		'id'               => '/?studyhub_app=' . $key,
		'name'             => $name,
		'short_name'       => mb_substr( $name, 0, 12 ),
		'start_url'        => $app['start'],
		'scope'            => home_url( '/' ),
		'display'          => 'standalone',
		'orientation'      => 'any',
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

function studyhub_service_worker() {
	$assets = array( studyhub_asset_url( 'ui.css' ), STUDYHUB_CORE_URL . 'assets/icons/icon-192.png' );
	$pages  = array();
	foreach ( studyhub_pwa_apps() as $app ) {
		$pages[] = $app['start'];
		foreach ( (array) ( isset( $app['assets'] ) ? $app['assets'] : array() ) as $a ) {
			$assets[] = $a;
		}
	}
	$version = substr( md5( STUDYHUB_VER . implode( '|', $assets ) ), 0, 10 );
	header( 'Content-Type: application/javascript; charset=utf-8' );
	header( 'Service-Worker-Allowed: /' );
	header( 'Cache-Control: no-cache' );
	$js = file_get_contents( STUDYHUB_CORE_DIR . 'assets/sw.js' ); // phpcs:ignore
	echo str_replace( // phpcs:ignore
		array( '__VERSION__', '__ASSETS__', '__PAGES__' ),
		array( $version, wp_json_encode( array_values( array_unique( $assets ) ), JSON_UNESCAPED_SLASHES ), wp_json_encode( $pages, JSON_UNESCAPED_SLASHES ) ),
		$js
	);
	exit;
}

/** Общие теги <head> для всех страниц StudyHub (сайт, кабинет, панель). */
function studyhub_head_tags( $manifest_key = '' ) {
	$brand  = StudyHub_Settings::brand();
	$logo   = StudyHub_Settings::get( 'brand_logo' );
	$accent = StudyHub_Settings::accent();
	$out    = '<meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">'
		. '<meta name="theme-color" content="#F4F2F0" media="(prefers-color-scheme: light)">'
		. '<meta name="theme-color" content="#121214" media="(prefers-color-scheme: dark)">'
		. '<link rel="icon" href="' . esc_url( studyhub_icon_url( 192 ) ) . '">'
		. '<link rel="apple-touch-icon" href="' . esc_url( $logo ? $logo : STUDYHUB_CORE_URL . 'assets/icons/apple-180.png' ) . '">'
		. '<link rel="stylesheet" href="' . esc_url( studyhub_asset_url( 'ui.css' ) ) . '">'
		. '<style>:root{--accent:' . esc_html( $accent ) . ';}</style>';
	if ( $manifest_key ) {
		$out .= '<link rel="manifest" href="' . esc_url( studyhub_manifest_url( $manifest_key ) ) . '">'
			. '<meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes">'
			. '<meta name="apple-mobile-web-app-status-bar-style" content="default">'
			. '<meta name="apple-mobile-web-app-title" content="' . esc_attr( $brand ) . '">';
	}
	return $out;
}
