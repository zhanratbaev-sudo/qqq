<?php
/**
 * Plugin Name: PetGo Магазин
 * Description: Магазин товаров для питомцев на сайте (страница «Магазин») и в кабинете клиента. Няня привозит заказ бесплатно, когда приходит за питомцем; без няни — доставка в зоне вокруг Гагарина 133/8 (2 км): бесплатно от 5 000 ₸, иначе 900 ₸; адрес вне зоны — «скоро добавим ваш район». Товары, заказы и зона доставки — в приложении сотрудников (раздел «Магазин»). Нужны «PetGo Кабинет» и «PetGo Сайт».
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: web.for made
 */
if (!defined('ABSPATH')) {
    exit;
}

define('PETGO_SHOP_FILE', __FILE__);
define('PETGO_SHOP_VER', '1.0.0');

/* записи товаров и заказов регистрируются всегда (даже без других плагинов — данные не «пропадают») */
add_action('init', 'petgo_shop_types', 5);
function petgo_shop_types() {
    register_post_type('petgo_product', array('public' => false, 'show_ui' => false, 'rewrite' => false, 'query_var' => false, 'supports' => array('title', 'editor')));
    register_post_type('petgo_shop_order', array('public' => false, 'show_ui' => false, 'rewrite' => false, 'query_var' => false, 'supports' => array('title')));
}

/* всё остальное — когда PetGo готов (порядок установки плагинов не важен) */
add_action('petgo_ready', function ($api) {
    if ($api < 1 || !function_exists('petgo_site_url')) {
        return;
    }
    petgo_register_plugin(PETGO_SHOP_FILE, array('name' => 'PetGo Магазин', 'version' => PETGO_SHOP_VER));
    foreach (array('core', 'front', 'staff') as $f) {
        require_once __DIR__ . '/includes/' . $f . '.php';
    }
});

add_action('admin_notices', function () {
    if (!defined('PETGO_VER') || !defined('PETGO_SITE_FILE')) {
        echo '<div class="notice notice-error"><p>«PetGo Магазин» работает вместе с плагинами «PetGo Кабинет» и «PetGo Сайт». Включите их в разделе «Плагины».</p></div>';
    }
});
