<?php
/**
 * Plugin Name: PetGo Пример дополнения
 * Description: Образец нового плагина PetGo: своя страница сайта с пунктом меню, блок на главной, раздел в приложении сотрудников, строка в кабинете клиента и подписка на события. Работает с PetGo 34.5 и новее — другие плагины обновлять не нужно.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: web.for made
 */
if (!defined('ABSPATH')) {
    exit;
}

/* всё подключаем, когда PetGo готов (порядок установки плагинов не важен; нет PetGo — плагин просто молчит) */
add_action('petgo_ready', function ($api) {
    if ($api < 1) {
        return;
    }
    /* плагин виден в приложении: Настройки → «Плагины PetGo»; его assets/ доступны через petgo_asset_url() */
    petgo_register_plugin(__FILE__, array('name' => 'PetGo Пример дополнения'));

    /* 1. Страница сайта /primer/ — адрес, меню, подвал, карта сайта, языки подключаются сами */
    add_filter('petgo_site_pages', function ($pages) {
        $pages['primer'] = array('slug' => 'primer', 'name' => 'Пример', 'title' => 'Пример страницы', 'nav' => true);
        return $pages;
    });
    add_filter('petgo_site_page_html', function ($html, $page) {
        if ($page !== 'primer') {
            return $html;
        }
        $t = function_exists('petgo_site_b_title') ? petgo_site_b_title('Пример страницы', 'Эту страницу добавил отдельный плагин — остальные плагины PetGo не менялись.') : '';
        return $t . '<section class="pg-sec pg-sec-tight"><div class="pg-wrap"><div class="pg-card"><h3 class="pg-h3">Что угодно</h3><p class="pg-p">Акции, вакансии, статьи, калькулятор цены…</p><button type="button" class="pg-b pg-btn pg-btn-primary" data-sheet="request">Оставить заявку</button></div></div></section>';
    }, 10, 2);

    /* 2. Блок на главной: ключ — место (10 первый экран, 20 услуги, 30 «как это работает», 40 отзывы, 50 заявка) */
    add_filter('petgo_site_home_sections', function ($sec) {
        $sec[25] = '<section class="pg-sec pg-sec-tight" id="pg-primer"><div class="pg-wrap"><div class="pg-card"><h3 class="pg-h3">Блок из плагина «Пример»</h3><p class="pg-p">Появился на главной без правок других плагинов.</p></div></div></section>';
        return $sec;
    });

    /* 3. Раздел в приложении сотрудников (меню слева на ПК, меню профиля на телефоне) */
    add_filter('petgo_nav_items', function ($items, $adm) {
        if ($adm && petgo_can('admin')) {
            $items['primer'] = array('Пример', petgo_page_url(array('pg' => 'primer')), 'globe', 'd');
        }
        return $items;
    }, 10, 2);
    add_filter('petgo_app_page', function ($done, $pg) {
        if ($done || $pg !== 'primer') {
            return $done;
        }
        $n = (int) get_option('petgo_primer_events', 0);
        echo '<div class="pgc-card"><b>Раздел из плагина</b><p class="mu">Событий PetGo с момента установки: ' . $n . '.</p></div>';
        return true;
    }, 10, 2);

    /* 4. Кабинет клиента: строка во вкладке «Аккаунт» */
    add_filter('petgo_cl_account_html', function ($h, $uid) {
        return $h . '<div class="pgk-list"><div class="pgk-row"><span class="pgk-row-t"><b>Строка из плагина</b><small>Кабинет клиента тоже расширяется</small></span></div></div>';
    }, 10, 2);

    /* 5. События PetGo: заявка (lead), анкета (pet_form), регистрация (sign_up), запись (booking), заказ (order_…) */
    add_action('petgo_event', function ($name, $data) {
        update_option('petgo_primer_events', (int) get_option('petgo_primer_events', 0) + 1, false);
    }, 10, 2);
});
