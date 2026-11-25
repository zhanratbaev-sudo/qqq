<?php
/**
 * Plugin Name: PetGo Клиент
 * Description: Кабинет клиента на сайте PetGo: вход и регистрация, питомцы, заказ выгула и визитов, карта прогулки, отчёты нянь, отзывы, чат с менеджером и помощником. Своё оформление (стиль iPhone в цветах PetGo, светлая и тёмная тема). Нужны «PetGo Кабинет» и «PetGo Сайт».
 * Version: 39.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: web.for made
 */
if (!defined('ABSPATH')) {
    exit;
}

define('PETGO_CLIENT_FILE', __FILE__);
define('PETGO_CLIENT_VER', '39.0.0');

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
    foreach (array('client', 'client-plus', 'client-dict', 'ui', 'contract', 'app38') as $f) {
        require_once __DIR__ . '/includes/' . $f . '.php';
    }
    /* своё оформление кабинета: стили в <head>, скрипт — после скриптов сайта */
    add_action('petgo_site_head', 'petgo_cl_head');
    add_action('petgo_site_foot', 'petgo_cl_foot');
}, 20);

function petgo_cl_head() {
    echo '<link rel="stylesheet" href="' . esc_url(petgo_asset_url('client.css')) . '">';
    /* 35.0.2: настоящая видимая высота экрана (--app-h) — листы и кабинет не длиннее экрана на любом телефоне */
    echo '<script>(function(){var d=document.documentElement,w=window;function s(){var h=w.innerHeight||d.clientHeight;if(h)d.style.setProperty("--app-h",h+"px")}s();w.addEventListener("resize",s);w.addEventListener("orientationchange",function(){setTimeout(s,250)});w.addEventListener("pageshow",s)})();</script>';
    if (!function_exists('petgo_site_page_now') || petgo_site_page_now() !== 'account') {
        return;
    }
    /* 36.1: кабинет — приложение «My PetGo»: короткое имя вкладки и ярлыка на экране «Домой» */
    echo '<meta name="apple-mobile-web-app-title" content="My PetGo"><meta name="application-name" content="My PetGo">';
    /* тёмная или светлая тема кабинета включается до отрисовки страницы — без мигания;
       36.1: и на экране входа (гость) — он открывается отдельно от сайта, в теме телефона */
    $gate = !is_user_logged_in() && function_exists('petgo_cl_gate');
    if (petgo_cl_uid() || $gate) {
        echo '<script>(function(){var d=document.documentElement,t="auto";try{t=localStorage.getItem("pgk_theme")||"auto"}catch(e){}d.classList.add("pgk-on"' . ($gate ? ',"pgk-gate-on"' : '') . ');if(t==="dark"||(t==="auto"&&window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches)){d.classList.add("pgk-dark");var m=document.querySelector(\'meta[name="theme-color"]\');if(m)m.setAttribute("content","#000000")}})();</script>';
    }
}

/* 36.1: имя страницы кабинета — «My PetGo» (без длинного «Кабинет — название сайта») */
add_filter('petgo_site_seo_title', function ($t, $page) {
    return $page === 'account' ? 'My PetGo' : $t;
}, 50, 2);

function petgo_cl_foot() {
    echo '<script src="' . esc_url(petgo_asset_url('client.js')) . '" defer></script>';
}
