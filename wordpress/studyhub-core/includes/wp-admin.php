<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   В АДМИНКЕ WORDPRESS — только ссылка на панель (всё управление — в /panel/).
   ============================================================ */

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'StudyHub', 'StudyHub', 'manage_options', 'studyhub', 'studyhub_wp_admin_page', 'dashicons-format-chat', 3 );
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( STUDYHUB_CORE_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( studyhub_url( 'panel' ) ) . '" target="_blank">Открыть панель</a>' );
		return $links;
	}
);

/* пункт «StudyHub» в верхней чёрной полосе WordPress */
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( studyhub_panel_user_ok() ) {
			$bar->add_node( array( 'id' => 'studyhub-panel', 'title' => 'StudyHub · Панель', 'href' => studyhub_url( 'panel' ) ) );
		}
	},
	80
);

function studyhub_wp_admin_page() {
	$panel = studyhub_url( 'panel' );
	echo '<div class="wrap"><h1>StudyHub ' . esc_html( STUDYHUB_VER ) . '</h1>';
	echo '<p style="font-size:15px">Всё управление — в отдельной панели: чаты со студентами, логины, отзывы, услуги и настройки. Вход — тем же логином и паролем WordPress.</p>';
	echo '<p><a class="button button-primary button-hero" href="' . esc_url( $panel ) . '" target="_blank">Открыть панель ↗</a></p>';
	echo '<p>Адрес панели: <code>' . esc_html( $panel ) . '</code>. На телефоне откройте его и добавьте на экран «Домой» — панель работает как приложение.</p>';
	echo '<h2>Плагины StudyHub</h2><ul>';
	foreach ( studyhub_plugins() as $p ) {
		echo '<li>' . esc_html( $p['name'] . ' ' . $p['ver'] ) . '</li>';
	}
	echo '</ul></div>';
}
