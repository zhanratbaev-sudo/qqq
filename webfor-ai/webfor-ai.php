<?php
/**
 * Plugin Name: WebFor AI Assistant
 * Description: AI-ассистент на сайте: отвечает клиентам по базе знаний (Gemini или Claude), сложные вопросы передаёт владельцу. Обучение — в разделе «AI-ассистент».
 * Version: 1.7.1
 * Author: WebFor
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

defined('ABSPATH') || exit;

define('WFAI_VERSION', '1.7.1');
define('WFAI_DIR', plugin_dir_path(__FILE__));
define('WFAI_URL', plugin_dir_url(__FILE__));

if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>WebFor AI Assistant требует PHP 8.1 или новее. Сейчас: ' . esc_html(PHP_VERSION) . '. Попросите хостинг обновить PHP.</p></div>';
    });
    return;
}

require_once WFAI_DIR . 'vendor/autoload.php';
require_once WFAI_DIR . 'includes/settings.php';
require_once WFAI_DIR . 'includes/questions.php';
require_once WFAI_DIR . 'includes/push.php';
require_once WFAI_DIR . 'includes/chats.php';
require_once WFAI_DIR . 'includes/chat.php';
require_once WFAI_DIR . 'includes/widget.php';
