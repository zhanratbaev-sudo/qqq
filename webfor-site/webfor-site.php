<?php
/**
 * Plugin Name: WebFor Site
 * Description: Сайт WebFor: папки с работами (сайты, приложения, AI-чат-боты, Izi), сравнение, форма заявки. Телефон и WhatsApp меняются в одном месте: WebFor → Настройки.
 * Version: 2.8.0
 * Author: WebFor
 * Text Domain: webfor-site
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

define('WEBFOR_VERSION', '2.8.0');
define('WEBFOR_FILE', __FILE__);
define('WEBFOR_DIR', plugin_dir_path(__FILE__));
define('WEBFOR_URL', plugin_dir_url(__FILE__));

require_once WEBFOR_DIR . 'includes/settings.php';
require_once WEBFOR_DIR . 'includes/leads.php';
require_once WEBFOR_DIR . 'includes/render.php';
require_once WEBFOR_DIR . 'includes/works.php';
require_once WEBFOR_DIR . 'includes/dashboard.php';
require_once WEBFOR_DIR . 'includes/app.php';

register_activation_hook(__FILE__, 'webfor_activate');

/**
 * Активация: настройки по умолчанию и страница сайта.
 * Страница становится главной только если на сайте главной была лента записей — чужую главную не трогаем.
 */
function webfor_activate() {
    if (!get_option('webfor_settings')) {
        add_option('webfor_settings', webfor_default_settings());
    }
    webfor_register_lead_type();
    webfor_seed_works();

    $page_id = (int) get_option('webfor_page_id');
    if (!$page_id || get_post_status($page_id) === false) {
        $page_id = wp_insert_post([
            'post_title'   => 'WebFor',
            'post_name'    => 'webfor',
            'post_content' => '[webfor_landing]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);
        if ($page_id && !is_wp_error($page_id)) {
            update_option('webfor_page_id', $page_id);
        }
    }
    if ($page_id && get_option('show_on_front') === 'posts') {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $page_id);
    }
    flush_rewrite_rules();
}
