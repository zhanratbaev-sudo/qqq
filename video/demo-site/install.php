<?php
/**
 * Устанавливает WordPress, включает плагин PetGo и создаёт главную со [petgo_site].
 * Запуск: php install.php /путь/к/wordpress
 */
define('WP_INSTALLING', true);
$_SERVER['HTTP_HOST'] = '127.0.0.1:8080';
require $argv[1] . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if (!is_blog_installed()) {
    wp_install('PetGo', 'owner', 'owner@petgo.test', true, '', 'Demo-Owner-2026!', 'ru_RU');
}
global $wp_rewrite;
$wp_rewrite->set_permalink_structure('/%postname%/');
switch_theme('petgo-blank');
$res = activate_plugin('petgo-cabinet/petgo-cabinet.php');
if (is_wp_error($res)) {
    fwrite(STDERR, $res->get_error_message() . "\n");
    exit(1);
}
$home = get_page_by_path('home');
$id = $home ? $home->ID : wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Главная', 'post_name' => 'home', 'post_content' => '[petgo_site]'));
update_option('show_on_front', 'page');
update_option('page_on_front', $id);
flush_rewrite_rules();
echo 'Кабинет: ' . rawurldecode(get_permalink(get_option('petgo_page_id'))) . "\n";
