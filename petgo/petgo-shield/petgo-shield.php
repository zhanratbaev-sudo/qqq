<?php
/**
 * Plugin Name: PetGo Защита
 * Description: Защита сайта, кабинета клиента и приложения сотрудников: фильтр атак (SQL-инъекции, XSS, подбор путей к файлам, вредные боты и сканеры), блокировка подбора паролей и потоков запросов, закрытые слабые места WordPress, проверка файлов на изменения и вирусы в загрузках, уведомления и отчёты об атаках в приложении сотрудников (раздел «Защита»).
 * Version: 1.0.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: web.for made
 */
if (!defined('ABSPATH')) {
    exit;
}

define('PGS_FILE', __FILE__);
define('PGS_VER', '1.0.1');
define('PGS_DIR', __DIR__);

/* защита работает сама по себе; раздел «Защита» в приложении — когда установлен «PetGo Кабинет» */
foreach (array('core', 'firewall', 'harden', 'monitor', 'page') as $pgs_f) {
    require_once PGS_DIR . '/includes/' . $pgs_f . '.php';
}

register_activation_hook(__FILE__, 'pgs_activate');
register_deactivation_hook(__FILE__, 'pgs_deactivate');
