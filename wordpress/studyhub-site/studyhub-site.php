<?php
/**
 * Plugin Name: StudyHub Сайт
 * Description: Сайт для студентов без темы WordPress: Главная, Услуги, Отзывы, «Войти» в кабинет. Русский и казахский, светлая и тёмная тема, SEO. Нужен плагин «StudyHub Ядро».
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: studyhub-core
 * Text Domain: studyhub
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STUDYHUB_SITE_FILE', __FILE__ );
define( 'STUDYHUB_SITE_VER', '2.0.0' );

/* сайт работает поверх «StudyHub Ядро»: подключаемся, когда загружены все плагины */
add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'STUDYHUB_VER' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>«StudyHub Сайт» работает вместе с плагином «StudyHub Ядро». Включите его в разделе «Плагины».</p></div>';
				}
			);
			return;
		}
		studyhub_register_plugin( STUDYHUB_SITE_FILE, array( 'name' => 'StudyHub Сайт', 'version' => STUDYHUB_SITE_VER ) );
		foreach ( array( 'site-dict', 'site', 'site-sections' ) as $f ) {
			require_once __DIR__ . '/includes/' . $f . '.php';
		}
	},
	5
);

register_activation_hook(
	__FILE__,
	function () {
		delete_option( 'studyhub_routes_sig' );
	}
);
