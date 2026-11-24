<?php
/**
 * Plugin Name: StudyHub Ядро
 * Description: База StudyHub и админ-панель (отдельная страница /panel/, как приложение): чаты со студентами, логины, отзывы, услуги и вузы, настройки. Сайт — плагин «StudyHub Сайт», кабинет студента — «StudyHub Студент», помощник — «StudyHub ИИ».
 * Version: 3.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: studyhub
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STUDYHUB_VER', '3.0.0' );
define( 'STUDYHUB_CORE_FILE', __FILE__ );
define( 'STUDYHUB_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'STUDYHUB_CORE_URL', plugin_dir_url( __FILE__ ) );

/* 1. Данные и настройки */
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/files.php';
/* 2. API для остальных плагинов StudyHub (сайт, студент, ИИ и любые новые) */
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/icons.php';
/* 3. Приложения: манифест и service worker, общий шаблон */
require_once __DIR__ . '/includes/pwa.php';
/* 4. Админ-панель: вход по логину WordPress, REST, отдельная страница */
require_once __DIR__ . '/includes/panel-auth.php';
require_once __DIR__ . '/includes/panel-rest.php';
require_once __DIR__ . '/includes/panel.php';
/* 5. Маленькая страница в wp-admin: ссылка на панель */
require_once __DIR__ . '/includes/wp-admin.php';

register_activation_hook( __FILE__, 'studyhub_core_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

function studyhub_core_activate() {
	StudyHub_DB::install();
	StudyHub_Files::ensure_private_dir();
	if ( false === get_option( StudyHub_Settings::OPTION ) ) {
		add_option( StudyHub_Settings::OPTION, StudyHub_Settings::defaults() );
	}
	studyhub_rewrite_rules();
	flush_rewrite_rules();
}

add_action( 'plugins_loaded', array( 'StudyHub_DB', 'maybe_upgrade' ) );

/* все красивые адреса (/panel/, /app/, страницы сайта) собираются из фильтра studyhub_routes */
add_action( 'init', 'studyhub_rewrite_rules' );
add_filter( 'query_vars', 'studyhub_query_vars' );
add_action( 'template_redirect', 'studyhub_route', 0 );
