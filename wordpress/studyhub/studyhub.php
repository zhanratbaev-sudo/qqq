<?php
/**
 * Plugin Name: StudyHub — анонимные услуги для студентов
 * Description: Мобильное приложение (PWA) для студентов: услуги (домашка, макеты, рисунки, чертежи), анонимный личный чат с администратором, отзывы по приглашению, выбор университета. Русский и казахский языки.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: studyhub
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STUDYHUB_VERSION', '1.0.0' );
define( 'STUDYHUB_FILE', __FILE__ );
define( 'STUDYHUB_DIR', plugin_dir_path( __FILE__ ) );
define( 'STUDYHUB_URL', plugin_dir_url( __FILE__ ) );

require_once STUDYHUB_DIR . 'includes/class-settings.php';
require_once STUDYHUB_DIR . 'includes/class-db.php';
require_once STUDYHUB_DIR . 'includes/class-auth.php';
require_once STUDYHUB_DIR . 'includes/class-files.php';
require_once STUDYHUB_DIR . 'includes/class-rest.php';
require_once STUDYHUB_DIR . 'includes/class-app.php';
require_once STUDYHUB_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'StudyHub_DB', 'activate' ) );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action( 'plugins_loaded', array( 'StudyHub_DB', 'maybe_upgrade' ) );
add_action( 'init', array( 'StudyHub_App', 'init' ) );
add_action( 'rest_api_init', array( 'StudyHub_REST', 'register_routes' ) );

if ( is_admin() ) {
	StudyHub_Admin::init();
}

/**
 * Public helper for add-ons (e.g. StudyHub AI): returns the logged-in student row or null.
 *
 * @return object|null
 */
function studyhub_current_student() {
	return StudyHub_Auth::current_student();
}
