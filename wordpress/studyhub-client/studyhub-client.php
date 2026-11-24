<?php
/**
 * Plugin Name: StudyHub Студент
 * Description: Кабинет студента (приложение, адрес /app/): вход по выданному логину, услуги и заказ, анонимный личный чат с администратором, файлы, отзыв по приглашению, выбор университета, русский и казахский. Нужен плагин «StudyHub Ядро».
 * Version: 2.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: studyhub-core
 * Text Domain: studyhub
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STUDYHUB_CLIENT_FILE', __FILE__ );
define( 'STUDYHUB_CLIENT_VER', '2.1.0' );

/* кабинет работает поверх «StudyHub Ядро»: подключаемся, когда загружены все плагины */
add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'STUDYHUB_VER' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>«StudyHub Студент» работает вместе с плагином «StudyHub Ядро». Включите его в разделе «Плагины».</p></div>';
				}
			);
			return;
		}
		studyhub_register_plugin( STUDYHUB_CLIENT_FILE, array( 'name' => 'StudyHub Студент', 'version' => STUDYHUB_CLIENT_VER ) );
		foreach ( array( 'auth', 'rest', 'app', 'content' ) as $f ) {
			require_once __DIR__ . '/includes/' . $f . '.php';
		}
	},
	20
);

register_activation_hook(
	__FILE__,
	function () {
		delete_option( 'studyhub_routes_sig' ); // адрес /app/ появится при следующей загрузке.
	}
);

/** Для дополнений (например «StudyHub ИИ»): вошедший студент или null. */
function studyhub_current_student() {
	return class_exists( 'StudyHub_Auth' ) ? StudyHub_Auth::current_student() : null;
}
