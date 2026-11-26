<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   39.1 ТЕСТОВАЯ НЕДЕЛЯ
   Владелец или администратор включает её в приложении сотрудников (Настройки → «Тестовая неделя»).
   Пока она идёт, в приложении клиента все услуги стоят 0 ₸ и везде написано «Тестовая неделя»;
   заказы из приложения помечаются «Тестовая неделя — бесплатно» (менеджер и няня видят это в заказе).
   Сайт не меняется: цены, акции и заявки — как обычно. Неделя выключается кнопкой или сама в выбранный день.
   ============================================================ */
function petgo_tw() {
    $o = get_option('petgo_test_week', array());
    $o = is_array($o) ? $o : array();
    return array('on' => !empty($o['on']), 'until' => isset($o['until']) ? (int) $o['until'] : 0, 'from' => isset($o['from']) ? (int) $o['from'] : 0);
}

function petgo_tw_on() {
    static $on = null;
    if ($on === null) {
        $t  = petgo_tw();
        $on = $t['on'] && (!$t['until'] || time() <= $t['until']);
    }
    return $on;
}

/* «до 3 декабря» на языке клиента */
function petgo_tw_until() {
    $t = petgo_tw();
    if (!$t['until']) {
        return '';
    }
    $m = array('января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря');
    $d = (int) wp_date('j', $t['until'], petgo_tz());
    $n = (int) wp_date('n', $t['until'], petgo_tz());
    return petgo_site_lf('до %s', $d . ' ' . petgo_site_l($m[$n - 1]));
}

/* полоса на главной приложения */
function petgo_tw_ribbon() {
    if (!petgo_tw_on()) {
        return '';
    }
    $L = 'petgo_site_l';
    $u = petgo_tw_until();
    return '<div class="pgk-tw"><b>' . esc_html($L('Тестовая неделя')) . '</b><span>' . esc_html($L('Все услуги в приложении — 0 ₸') . ($u !== '' ? ' ' . $u : '')) . '</span></div>';
}

/* карточка вместо акции на главной */
function petgo_tw_promo() {
    $L = 'petgo_site_l';
    $u = petgo_tw_until();
    return '<div class="pgk-promo38 pgk-tw-promo"><div class="pgk-pr-t"><b>0 ₸</b><span>' . esc_html($L('Тестовая неделя: выгул и визиты бесплатно') . ($u !== '' ? ' ' . $u : '')) . '</span><button type="button" class="pg-b pgk-pr-go" data-k-book="walk">' . esc_html($L('Заказать бесплатно')) . '</button></div><span class="pgk-pr-art" data-k-art="art:dog-gold" aria-hidden="true"></span></div>';
}

/* данные для скрипта приложения: цены остаются настоящими (для зачёркнутой цены), показывается 0 ₸ */
add_filter('petgo_cl_cfg', function ($cfg) {
    $cfg['test'] = petgo_tw_on() ? array('until' => petgo_tw_until()) : false;
    foreach (array('Тестовая неделя', 'тестовая неделя', 'Заказ в тестовую неделю — бесплатно', 'бесплатно') as $s) {
        $cfg['tr'][$s] = petgo_site_l($s);
    }
    return $cfg;
}, 20);

/* ---------- приложение сотрудников: Настройки → «Тестовая неделя» ---------- */
add_filter('petgo_settings_rows', function ($rows) {
    if (function_exists('petgo_can') && petgo_can('admin')) {
        $t = petgo_tw();
        $rows[] = array('icon' => 'gear', 'title' => 'Тестовая неделя', 'sub' => petgo_tw_on() ? 'Включена' . ($t['until'] ? ' до ' . wp_date('d.m', $t['until'], petgo_tz()) : '') . ' · в приложении клиентов всё 0 ₸' : 'Выключена', 'url' => petgo_page_url(array('pg' => 'testweek')));
    }
    return $rows;
});
add_filter('petgo_app_title', function ($t, $pg) {
    return $pg === 'testweek' ? 'Тест-неделя' : $t;
}, 10, 2);
add_filter('petgo_app_page', function ($done, $pg) {
    if ($done || $pg !== 'testweek') {
        return $done;
    }
    if (!petgo_can('admin')) {
        echo '<p class="mu">' . esc_html(petgo_t('err_perm')) . '</p>';
        return true;
    }
    $t   = petgo_tw();
    $on  = petgo_tw_on();
    $def = $t['until'] && $t['until'] > time() ? $t['until'] : time() + 7 * DAY_IN_SECONDS;
    echo '<style>.pgtw .pgc-card{display:flex;flex-direction:column;gap:10px;margin-bottom:12px}.pgtw .pgc-card>b{font-size:17px}.pgtw-st{display:inline-flex;align-self:flex-start;padding:4px 12px;border-radius:999px;font-weight:700;font-size:14px}.pgtw-st.on{background:#1E9A5F;color:#fff}.pgtw-st.off{background:var(--so);color:var(--mu)}.pgtw ul{margin:0;padding-left:18px;color:var(--mu);font-size:14px;line-height:1.5}.pgtw input[type=date]{border:0;background:none;font:inherit;color:inherit;text-align:right}</style>';
    echo petgo_form_open('testweek', 'class="pgtw"');
    echo '<div class="pgc-card"><b>Тестовая неделя</b><span class="pgtw-st ' . ($on ? 'on' : 'off') . '">' . ($on ? 'Включена' . ($t['until'] ? ' до ' . esc_html(wp_date('d.m.Y', $t['until'], petgo_tz())) : '') : 'Выключена') . '</span>';
    echo '<ul><li>В приложении клиента все услуги стоят 0 ₸, на главной и в заказе написано «Тестовая неделя».</li><li>Заказы из приложения помечаются «Тестовая неделя — бесплатно»: менеджер и няня видят это в заказе.</li><li>Сайт не меняется: цены и заявки как обычно.</li><li>В выбранный день неделя выключится сама.</li></ul>';
    echo '<label class="pgc-ck"><input type="checkbox" name="on" value="1"' . checked($on, true, false) . '> <span>Тестовая неделя включена</span></label>';
    echo '<div class="pgf-list"><label class="pgf-row"><span>Последний день</span><input type="date" name="until" value="' . esc_attr(wp_date('Y-m-d', $def, petgo_tz())) . '"></label></div></div>';
    echo '<div style="position:sticky;bottom:calc(var(--sab) + 12px);z-index:5"><button type="submit" class="pgc-btn wide lg">Сохранить</button></div></form>';
    return true;
}, 10, 2);
add_action('admin_post_petgo_testweek', function () {
    petgo_guard('admin');
    $on    = !empty($_POST['on']);
    $d     = isset($_POST['until']) ? sanitize_text_field(wp_unslash($_POST['until'])) : '';
    $until = preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? (int) petgo_ts($d, '23:59') : 0;
    $old   = petgo_tw();
    update_option('petgo_test_week', array('on' => $on ? 1 : 0, 'until' => $until, 'from' => $on && !$old['on'] ? time() : $old['from']), false);
    petgo_audit('test_week', $on ? 'on until ' . $d : 'off');
    petgo_flash($on ? 'Тестовая неделя включена' . ($until ? ' до ' . wp_date('d.m', $until, petgo_tz()) : '') : 'Тестовая неделя выключена', true);
    wp_safe_redirect(petgo_page_url(array('pg' => 'testweek')));
    exit;
});
/* главная приложения сотрудников: напоминание, пока неделя идёт */
add_filter('petgo_admin_home_top', function ($h) {
    if (!petgo_tw_on() || !function_exists('petgo_can') || !petgo_can('office')) {
        return $h;
    }
    $t = petgo_tw();
    return $h . '<a class="pgc-card" href="' . esc_url(petgo_page_url(array('pg' => 'testweek'))) . '" style="display:flex;align-items:center;gap:12px;margin:0 0 14px;padding:14px 16px;text-decoration:none;color:inherit;box-shadow:inset 0 0 0 1.5px #1E9A5F"><span style="flex:none;padding:4px 10px;border-radius:999px;background:#1E9A5F;color:#fff;font-weight:700;font-size:13px">0 ₸</span><span style="flex:1;min-width:0"><b>Тестовая неделя' . ($t['until'] ? ' до ' . esc_html(wp_date('d.m', $t['until'], petgo_tz())) : '') . '</b><br><small class="mu">Заказы из приложения клиентов — бесплатно</small></span></a>';
});
/* заказ, сделанный в тестовую неделю, — отметка в карточке заказа */
add_filter('petgo_order_detail_top', function ($h, $oid) {
    if (get_post_meta($oid, '_test', true) !== '1') {
        return $h;
    }
    return '<div class="pgc-card" style="margin-bottom:12px;box-shadow:inset 0 0 0 1.5px #1E9A5F"><b style="color:#1E9A5F">Тестовая неделя — бесплатно (0 ₸)</b><p class="mu" style="margin:4px 0 0">Клиент заказал в тестовую неделю. Оплату не брать.</p></div>' . $h;
}, 5, 2);

add_filter('petgo_site_dict_more', function ($d) {
    return array_merge($d, array(
        'Тестовая неделя' => array('Сынақ аптасы', 'Trial week'),
        'тестовая неделя' => array('сынақ аптасы', 'trial week'),
        'Все услуги в приложении — 0 ₸' => array('Қосымшадағы барлық қызмет — 0 ₸', 'All services in the app are 0 ₸'),
        'Тестовая неделя: выгул и визиты бесплатно' => array('Сынақ аптасы: серуен мен келу тегін', 'Trial week: walks and visits are free'),
        'Заказать бесплатно' => array('Тегін тапсырыс беру', 'Book for free'),
        'Заказ в тестовую неделю — бесплатно' => array('Сынақ аптасындағы тапсырыс — тегін', 'Bookings during the trial week are free'),
        'бесплатно' => array('тегін', 'free'),
        'до %s' => array('%s дейін', 'until %s'),
    ));
});
