<?php
/**
 * Plugin Name: PetGo Клиент
 * Description: Кабинет клиента на сайте PetGo: вход и регистрация, питомцы, заказ выгула и визитов, карта прогулки, отчёты нянь, отзывы, чат с менеджером и помощником. Своё оформление (стиль iPhone в цветах PetGo, светлая и тёмная тема). Нужны «PetGo Кабинет» и «PetGo Сайт».
 * Version: 34.5.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: web.for made
 */
if (!defined('ABSPATH')) {
    exit;
}

define('PETGO_CLIENT_FILE', __FILE__);
define('PETGO_CLIENT_VER', '34.5.0');

add_action('plugins_loaded', function () {
    if (!defined('PETGO_VER') || !defined('PETGO_SITE_FILE')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>«PetGo Клиент» работает вместе с плагинами «PetGo Кабинет» и «PetGo Сайт». Включите их в разделе «Плагины».</p></div>';
        });
        return;
    }
    if (function_exists('petgo_register_plugin')) {
        petgo_register_plugin(PETGO_CLIENT_FILE, array('name' => 'PetGo Клиент', 'version' => PETGO_CLIENT_VER));
    } else {
        petgo_asset_root(PETGO_CLIENT_FILE);
    }
    foreach (array('client', 'client-plus', 'client-dict', 'ui') as $f) {
        require_once __DIR__ . '/includes/' . $f . '.php';
    }
    /* своё оформление кабинета: стили в <head>, скрипт — после скриптов сайта */
    add_action('petgo_site_head', 'petgo_cl_head');
    add_action('petgo_site_foot', 'petgo_cl_foot');
}, 20);

function petgo_cl_head() {
    echo '<link rel="stylesheet" href="' . esc_url(petgo_asset_url('client.css')) . '">';
    /* тёмная или светлая тема кабинета включается до отрисовки страницы — без мигания */
    if (function_exists('petgo_site_page_now') && petgo_site_page_now() === 'account' && petgo_cl_uid()) {
        echo '<script>(function(){var d=document.documentElement,t="auto";try{t=localStorage.getItem("pgk_theme")||"auto"}catch(e){}d.classList.add("pgk-on");if(t==="dark"||(t==="auto"&&window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches))d.classList.add("pgk-dark")})();</script>';
    }
}

function petgo_cl_foot() {
    echo '<script src="' . esc_url(petgo_asset_url('client.js')) . '" defer></script>';
}
