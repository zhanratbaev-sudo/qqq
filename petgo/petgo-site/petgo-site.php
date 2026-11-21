<?php
/**
 * Plugin Name: PetGo Сайт
 * Description: Сайт PetGo для клиентов: Главная, Услуги и цены, Няни, Отзывы, Кабинет, анкета питомца карточками, заявки, языки RU · KZ · EN. Всё оформление сайта — в этом плагине. Нужен плагин «PetGo Кабинет».
 * Version: 36.1.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: web.for made
 */
if (!defined('ABSPATH')) {
    exit;
}

define('PETGO_SITE_FILE', __FILE__);
define('PETGO_SITE_VER', '36.1.0');

/* сайт работает поверх базы «PetGo Кабинет»: подключаемся, когда загружены все плагины */
add_action('plugins_loaded', function () {
    if (!defined('PETGO_VER')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>«PetGo Сайт» работает вместе с плагином «PetGo Кабинет». Включите его в разделе «Плагины».</p></div>';
        });
        return;
    }
    /* 34.5: регистрируемся через API PetGo (если «Кабинет» старее — по-старому) */
    if (function_exists('petgo_register_plugin')) {
        petgo_register_plugin(PETGO_SITE_FILE, array('name' => 'PetGo Сайт', 'version' => PETGO_SITE_VER));
    } else {
        petgo_asset_root(PETGO_SITE_FILE);
    }
    foreach (array('site-i18n', 'site-dict', 'site', 'site-nannies', 'site-admin', 'lead', 'formeditor', 'site-form', 'front', 'site-offline', 'site-ads') as $f) {
        require_once __DIR__ . '/includes/' . $f . '.php';
    }
}, 5);
