<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   40.5 ЗНАКОМСТВО С КАБИНЕТОМ (первый вход)
   Новый клиент при первом входе видит подсказки по экрану: услуги, «Заказать», уведомления, карта,
   сообщения, мои питомцы, профиль. «Далее» — или нажатие в любом месте экрана. «Пропустить» —
   закрыть. Показывается один раз (клиентам, зарегистрированным после обновления); повторить —
   «Профиль» → «Как пользоваться приложением».
   ============================================================ */

/* с какого момента показываем (старые клиенты уже знают приложение) */
add_action('init', function () {
    if (!get_option('petgo_tour_since')) {
        add_option('petgo_tour_since', time(), '', false);
    }
}, 8);

function petgo_cl_tour_need($uid) {
    $u = get_userdata((int) $uid);
    if (!$u || get_user_meta($u->ID, 'petgo_tour', true)) {
        return false;
    }
    return strtotime($u->user_registered . ' UTC') >= (int) get_option('petgo_tour_since') - 60;
}

add_filter('petgo_cl_cfg', function ($cfg, $uid = 0) {
    $cfg['tour'] = petgo_cl_tour_need($uid) ? 1 : 0;
    foreach (array('Далее', 'Пропустить', 'Начать', 'Знакомство с кабинетом') as $x) {
        $cfg['tr'][$x] = petgo_site_l($x);
    }
    return $cfg;
}, 30, 2);

add_action('petgo_cl_op', function ($op, $uid) {
    if ($op === 'tour_done') {
        update_user_meta((int) $uid, 'petgo_tour', time());
        petgo_cl_json(true);
    }
}, 10, 2);

/* «Профиль»: посмотреть подсказки ещё раз */
add_filter('petgo_cl_account_html', function ($h, $uid) {
    $L = 'petgo_site_l';
    return $h . '<div class="pgk-list pgk-tour-row"><button type="button" class="pg-b pgk-row" data-k-tour><span class="pgk-row-i">' . petgo_cl_icon('right') . '</span><span class="pgk-row-t"><b>' . esc_html($L('Как пользоваться приложением')) . '</b><small>' . esc_html($L('Короткое знакомство с экраном')) . '</small></span>' . petgo_cl_icon('right', 'pg-i pgk-chev') . '</button></div>';
}, 40, 2);

/* имя указано в профиле — дальше приветствие с именем */
add_action('petgo_event', function ($name, $d) {
    if ($name === 'cl_profile' && !empty($d['user'])) {
        delete_user_meta((int) $d['user'], 'petgo_noname');
    }
}, 10, 2);
