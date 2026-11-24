<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   САЙТ: свои страницы без темы WordPress.
   Страницы — фильтр studyhub_site_pages: 'ключ' => ['slug','title'=>[ru,kk],'nav'=>true,'icon'=>…]
   Содержимое — фильтр studyhub_site_page_html($html, $key).
   ============================================================ */

function studyhub_site_pages() {
	$pages = array(
		'home'     => array( 'slug' => 'studyhub', 'front' => (bool) StudyHub_Settings::get( 'site_on_front' ), 'nav' => 'nav_home', 'icon' => 'home' ),
		'services' => array( 'slug' => 'uslugi', 'nav' => 'nav_services', 'icon' => 'grid' ),
		'reviews'  => array( 'slug' => 'otzyvy', 'nav' => 'nav_reviews', 'icon' => 'star' ),
	);
	return (array) apply_filters( 'studyhub_site_pages', $pages );
}

add_filter(
	'studyhub_routes',
	function ( $routes ) {
		foreach ( studyhub_site_pages() as $key => $p ) {
			$routes[ 'site_' . $key ] = array(
				'slug'   => $p['slug'],
				'front'  => ! empty( $p['front'] ),
				'render' => 'studyhub_site_render',
			);
		}
		return $routes;
	},
	5
);

function studyhub_site_url( $key ) {
	return studyhub_url( 'site_' . $key );
}

/** Куда ведёт «Войти» / «Заказать»: кабинет студента (если включён) или главная. */
function studyhub_site_app_url( $hash = '' ) {
	$routes = studyhub_routes();
	$url    = isset( $routes['app'] ) ? studyhub_url( 'app' ) : home_url( '/' );
	return $hash ? $url . '#' . $hash : $url;
}

/* ---------- SEO ---------- */
function studyhub_site_seo( $key ) {
	$brand = StudyHub_Settings::brand();
	$s     = StudyHub_Settings::all();
	$lang  = studyhub_lang();
	$desc  = 'kk' === $lang ? $s['hero_text_kk'] : $s['hero_text_ru'];
	$title = 'home' === $key ? $brand . ' — ' . ( 'kk' === $lang ? $s['brand_tagline_kk'] : $s['brand_tagline_ru'] ) : studyhub_st( 'nav_' . $key ) . ' — ' . $brand;
	return array(
		'title' => apply_filters( 'studyhub_site_seo_title', $title, $key ),
		'desc'  => apply_filters( 'studyhub_site_seo_desc', $desc, $key ),
		'image' => apply_filters( 'studyhub_site_seo_image', $s['hero_image'] ? $s['hero_image'] : studyhub_icon_url( 512 ), $key ),
	);
}

/* ---------- части страницы ---------- */
function studyhub_site_brand_tile() {
	$logo = StudyHub_Settings::get( 'brand_logo' );
	if ( $logo ) {
		return '<span class="brand-tile"><img src="' . esc_url( $logo ) . '" alt=""></span>';
	}
	return '<span class="brand-tile"><svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round">'
		. '<path d="M16 7 7.5 27" style="stroke:var(--text)" stroke-width="3.4"/><path d="M16 7 24.5 27" style="stroke:var(--accent)" stroke-width="3.4"/>'
		. '<path d="M10.5 20.5c3.6 1.6 7.4 1.6 11 0" style="stroke:var(--text)" stroke-width="2.4"/><circle cx="16" cy="5.5" r="2.6" style="fill:var(--text)"/></svg></span>';
}

function studyhub_site_lang_switch() {
	$l = studyhub_lang();
	return '<div class="lang glass" role="group" aria-label="Language"><button data-lang="kk" aria-pressed="' . ( 'kk' === $l ? 'true' : 'false' ) . '">Қаз</button><button data-lang="ru" aria-pressed="' . ( 'ru' === $l ? 'true' : 'false' ) . '">Рус</button></div>';
}

function studyhub_site_header( $active ) {
	$nav = '';
	foreach ( studyhub_site_pages() as $key => $p ) {
		if ( empty( $p['nav'] ) ) {
			continue;
		}
		$nav .= '<a href="' . esc_url( studyhub_site_url( $key ) ) . '"' . ( $active === $key ? ' aria-current="page"' : '' ) . '>' . esc_html( studyhub_st( $p['nav'] ) ) . '</a>';
	}
	$nav = apply_filters( 'studyhub_site_nav', $nav, $active );
	return '<header class="s-top"><a class="s-brand" href="' . esc_url( studyhub_site_url( 'home' ) ) . '">' . studyhub_site_brand_tile()
		. '<span><b>' . esc_html( StudyHub_Settings::brand() ) . '</b><small>' . esc_html( studyhub_loc( array( 'ru' => StudyHub_Settings::get( 'brand_tagline_ru' ), 'kk' => StudyHub_Settings::get( 'brand_tagline_kk' ) ) ) ) . '</small></span></a>'
		. '<nav class="s-nav glass">' . $nav . '</nav>'
		. studyhub_site_lang_switch()
		. '<a class="btn btn-dark btn-sm s-login" href="' . esc_url( studyhub_site_app_url() ) . '">' . studyhub_icon( 'user', 'xs' ) . esc_html( studyhub_st( 'nav_login' ) ) . '</a></header>';
}

function studyhub_site_tabbar( $active ) {
	$out = '<nav class="tabbar glass s-tabbar" aria-label="Menu">';
	foreach ( studyhub_site_pages() as $key => $p ) {
		if ( empty( $p['nav'] ) ) {
			continue;
		}
		$out .= '<a class="tab" href="' . esc_url( studyhub_site_url( $key ) ) . '"' . ( $active === $key ? ' aria-current="page"' : '' ) . '>' . studyhub_icon( isset( $p['icon'] ) ? $p['icon'] : 'doc' ) . '<span>' . esc_html( studyhub_st( $p['nav'] ) ) . '</span></a>';
	}
	return $out . '<a class="tab" href="' . esc_url( studyhub_site_app_url() ) . '">' . studyhub_icon( 'user' ) . '<span>' . esc_html( studyhub_st( 'nav_login' ) ) . '</span></a></nav>';
}

function studyhub_site_footer() {
	$y = gmdate( 'Y' );
	return '<footer class="s-foot"><div>' . studyhub_site_brand_tile() . '<span><b>' . esc_html( StudyHub_Settings::brand() ) . '</b><br><small>' . esc_html( studyhub_st( 'footer' ) ) . ' · ' . $y . '</small></span></div>'
		. '<div class="s-foot-links"><a href="' . esc_url( studyhub_site_url( 'services' ) ) . '">' . esc_html( studyhub_st( 'nav_services' ) ) . '</a><a href="' . esc_url( studyhub_site_url( 'reviews' ) ) . '">' . esc_html( studyhub_st( 'nav_reviews' ) ) . '</a><a href="' . esc_url( studyhub_site_app_url() ) . '">' . esc_html( studyhub_st( 'nav_cabinet' ) ) . '</a></div></footer>';
}

/* ---------- вывод ---------- */
function studyhub_site_render( $route_key ) {
	$key  = substr( $route_key, 5 );
	$html = '';
	if ( 'home' === $key ) {
		$sections = (array) apply_filters( 'studyhub_site_home_sections', studyhub_site_home_sections_default() );
		ksort( $sections );
		foreach ( $sections as $fn ) {
			$html .= is_callable( $fn ) ? call_user_func( $fn ) : (string) $fn;
		}
	} elseif ( 'services' === $key ) {
		$html = studyhub_site_section_services( false ) . studyhub_site_section_how();
	} elseif ( 'reviews' === $key ) {
		$html = studyhub_site_section_reviews( false ) . studyhub_site_section_cta();
	}
	$html = apply_filters( 'studyhub_site_page_html', $html, $key );
	$seo  = studyhub_site_seo( $key );
	include dirname( __DIR__ ) . '/templates/site.php';
}
