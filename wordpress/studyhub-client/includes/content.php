<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   КОНТЕНТ КАБИНЕТА: акции и «Полезные материалы».
   Редактируется в панели → «Кабинет студента» (раздел через studyhub_panel_sections).
   ============================================================ */

const STUDYHUB_CL_OPTION = 'studyhub_client_content';
const STUDYHUB_CL_PROMOS = 3;
const STUDYHUB_CL_MATS   = 8;

function studyhub_cl_content() {
	$c = get_option( STUDYHUB_CL_OPTION, array() );
	return is_array( $c ) ? $c : array();
}

function studyhub_cl_val( $c, $k, $d = '' ) {
	return isset( $c[ $k ] ) ? $c[ $k ] : $d;
}

/** Акции для приложения. */
function studyhub_cl_promos() {
	$c   = studyhub_cl_content();
	$out = array();
	for ( $i = 1; $i <= STUDYHUB_CL_PROMOS; $i++ ) {
		$title = studyhub_cl_val( $c, "promo{$i}_title_ru" );
		if ( ! studyhub_cl_val( $c, "promo{$i}_on" ) || '' === $title ) {
			continue;
		}
		$out[] = array(
			'id'      => 'p' . $i,
			'badge'   => array( 'ru' => studyhub_cl_val( $c, "promo{$i}_badge_ru", 'Акция' ), 'kk' => studyhub_cl_val( $c, "promo{$i}_badge_kk", 'Акция' ) ),
			'title'   => array( 'ru' => $title, 'kk' => studyhub_cl_val( $c, "promo{$i}_title_kk" ) ),
			'text'    => array( 'ru' => studyhub_cl_val( $c, "promo{$i}_text_ru" ), 'kk' => studyhub_cl_val( $c, "promo{$i}_text_kk" ) ),
			'service' => studyhub_cl_val( $c, "promo{$i}_service" ),
			'image'   => studyhub_cl_val( $c, "promo{$i}_image" ),
		);
	}
	return $out;
}

/** Полезные материалы для приложения. */
function studyhub_cl_materials() {
	$c   = studyhub_cl_content();
	$out = array();
	for ( $i = 1; $i <= STUDYHUB_CL_MATS; $i++ ) {
		$title = studyhub_cl_val( $c, "mat{$i}_title_ru" );
		if ( '' === $title ) {
			continue;
		}
		$out[] = array(
			'id'    => 'm' . $i,
			'title' => array( 'ru' => $title, 'kk' => studyhub_cl_val( $c, "mat{$i}_title_kk" ) ),
			'image' => studyhub_cl_val( $c, "mat{$i}_image" ),
			'url'   => studyhub_cl_val( $c, "mat{$i}_url" ),
		);
	}
	return $out;
}

add_filter(
	'studyhub_app_config',
	function ( $config ) {
		$config['promos']    = studyhub_cl_promos();
		$config['materials'] = studyhub_cl_materials();
		return $config;
	}
);

/* ---------- раздел панели ---------- */
add_filter( 'studyhub_panel_sections', 'studyhub_cl_section' );
function studyhub_cl_section( $sections ) {
	$services = array( '' => 'Без услуги' );
	foreach ( (array) StudyHub_Settings::get( 'services' ) as $s ) {
		$services[ $s['id'] ] = $s['title_ru'];
	}
	$f = array();
	for ( $i = 1; $i <= STUDYHUB_CL_PROMOS; $i++ ) {
		$f[] = array( 'name' => "h_promo{$i}", 'label' => "Акция {$i}", 'type' => 'heading' );
		$f[] = array( 'name' => "promo{$i}_on", 'label' => 'Показывать эту акцию', 'type' => 'checkbox' );
		$f[] = array( 'name' => "promo{$i}_title_ru", 'label' => 'Заголовок (рус)', 'type' => 'text', 'placeholder' => 'Скидка 30% на макеты' );
		$f[] = array( 'name' => "promo{$i}_title_kk", 'label' => 'Тақырып (қаз)', 'type' => 'text', 'placeholder' => 'Макеттерге 30% жеңілдік' );
		$f[] = array( 'name' => "promo{$i}_text_ru", 'label' => 'Текст (рус)', 'type' => 'text', 'placeholder' => 'Только до конца недели' );
		$f[] = array( 'name' => "promo{$i}_text_kk", 'label' => 'Мәтін (қаз)', 'type' => 'text', 'placeholder' => 'Тек апта соңына дейін' );
		$f[] = array( 'name' => "promo{$i}_badge_ru", 'label' => 'Метка (рус)', 'type' => 'text', 'placeholder' => 'Акция' );
		$f[] = array( 'name' => "promo{$i}_badge_kk", 'label' => 'Белгі (қаз)', 'type' => 'text', 'placeholder' => 'Акция' );
		$f[] = array( 'name' => "promo{$i}_service", 'label' => 'Услуга (кнопка «Заказать»)', 'type' => 'select', 'options' => $services );
		$f[] = array( 'name' => "promo{$i}_image", 'label' => 'Картинка (необязательно)', 'type' => 'media' );
	}
	for ( $i = 1; $i <= STUDYHUB_CL_MATS; $i++ ) {
		$f[] = array( 'name' => "h_mat{$i}", 'label' => "Материал {$i}", 'type' => 'heading', 'help' => 1 === $i ? 'Пустое название — материал не показывается.' : '' );
		$f[] = array( 'name' => "mat{$i}_title_ru", 'label' => 'Название (рус)', 'type' => 'text', 'placeholder' => 'Перспектива: основы' );
		$f[] = array( 'name' => "mat{$i}_title_kk", 'label' => 'Атауы (қаз)', 'type' => 'text' );
		$f[] = array( 'name' => "mat{$i}_image", 'label' => 'Обложка', 'type' => 'media' );
		$f[] = array( 'name' => "mat{$i}_url", 'label' => 'Ссылка (PDF, видео, статья)', 'type' => 'url', 'placeholder' => 'https://…' );
	}
	$sections['cabinet'] = array(
		'title'  => 'Кабинет студента',
		'icon'   => 'phone',
		'intro'  => 'Акции и «Полезные материалы» на главной кабинета. Баннер и услуги — в «Настройках» и «Услугах».',
		'fields' => $f,
		'get'    => 'studyhub_cl_content',
		'save'   => 'studyhub_cl_save',
	);
	return $sections;
}

function studyhub_cl_save( array $v ) {
	$out = array();
	foreach ( $v as $k => $val ) {
		if ( ! preg_match( '/^(promo[1-9]|mat[1-9])_[a-z_]+$/', $k ) ) {
			continue;
		}
		if ( preg_match( '/_(image|url)$/', $k ) ) {
			$out[ $k ] = esc_url_raw( (string) $val );
		} elseif ( preg_match( '/_on$/', $k ) ) {
			$out[ $k ] = empty( $val ) ? 0 : 1;
		} elseif ( preg_match( '/_service$/', $k ) ) {
			$out[ $k ] = sanitize_key( (string) $val );
		} else {
			$out[ $k ] = sanitize_text_field( (string) $val );
		}
	}
	update_option( STUDYHUB_CL_OPTION, $out, false );
	return array( 'values' => $out, 'notice' => 'Сохранено — кабинет студента обновится при следующем открытии.' );
}
