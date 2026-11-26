<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   40.0 УВЕДОМЛЕНИЯ СОТРУДНИКА (колокольчик в шапке приложения)
   Всё, о чём приложение сообщает сотруднику (новый клиент, заказ, питомец, отзыв, договор, «клиент ждёт менеджера»,
   заявка с сайта, заказ в магазине…), теперь ещё и записывается в его ленту «Уведомления». Так ничего не теряется,
   даже если уведомления на телефон выключены или телефон был без связи. Сообщения чатов сюда не дублируются —
   у них свой значок. Хранится 150 последних событий на сотрудника.
   ============================================================ */
function petgo_feed_add($uids, $title, $body, $url = '') {
    $it = array('t' => mb_substr(wp_strip_all_tags((string) $title), 0, 120), 'b' => mb_substr(wp_strip_all_tags((string) $body), 0, 300), 'u' => (string) $url, 'ts' => time());
    foreach (array_unique(array_map('intval', (array) $uids)) as $uid) {
        $u = $uid > 0 ? get_userdata($uid) : null;
        /* лента — только у сотрудников; клиентам уведомления приходят в их приложение */
        if (!$u || petgo_role_of($u) === '') {
            continue;
        }
        $f = get_user_meta($uid, 'petgo_feed', true);
        $f = is_array($f) ? $f : array();
        /* то же событие второй раз за минуту (двойное нажатие, повтор запроса) — не записываем */
        if ($f && $f[0]['t'] === $it['t'] && $f[0]['b'] === $it['b'] && time() - (int) $f[0]['ts'] < 60) {
            continue;
        }
        array_unshift($f, $it);
        update_user_meta($uid, 'petgo_feed', array_slice($f, 0, 150));
    }
}

function petgo_feed_items($uid) {
    $f = get_user_meta((int) $uid, 'petgo_feed', true);
    return is_array($f) ? $f : array();
}

function petgo_feed_unread($uid) {
    $seen = (int) get_user_meta((int) $uid, 'petgo_feed_seen', true);
    $n    = 0;
    foreach (petgo_feed_items($uid) as $x) {
        if ((int) $x['ts'] > $seen) {
            $n++;
        }
    }
    return $n;
}

/* значок события по его заголовку */
function petgo_feed_icon($t) {
    $t = mb_strtolower($t);
    $m = array(
        'user' => array('новый клиент', 'клиент изменил', 'клиент сменил', 'удалил кабинет'),
        'paw' => array('питом', 'анкета'),
        'reviews' => array('отзыв'),
        'form' => array('договор'),
        'chat' => array('менеджер', 'чат', 'ответа'),
        'bag' => array('магазин'),
        'shield' => array('пароль', 'защита', 'вход'),
        'requests' => array('заявка', 'регистрация'),
        'cal' => array('заказ', 'назначен', 'записал', 'сводка'),
    );
    foreach ($m as $ic => $keys) {
        foreach ($keys as $k) {
            if (mb_strpos($t, $k) !== false) {
                return $ic;
            }
        }
    }
    return 'bell';
}

/* шапка приложения: колокольчик с числом новых */
function petgo_feed_bell() {
    $uid = get_current_user_id();
    /* открыта сама лента — всё прочитано уже в шапке этой страницы */
    if ($uid && function_exists('petgo_cur_pg') && petgo_cur_pg() === 'feed') {
        $GLOBALS['petgo_feed_prev'] = (int) get_user_meta($uid, 'petgo_feed_seen', true);
        update_user_meta($uid, 'petgo_feed_seen', time());
    }
    $n   = $uid ? petgo_feed_unread($uid) : 0;
    return '<a class="pgc-iconb pgc-bell" href="' . esc_url(petgo_page_url(array('pg' => 'feed'))) . '" aria-label="' . esc_attr('Уведомления' . ($n ? ': ' . $n : '')) . '">' . petgo_icon('bell') . '<i class="pgc-bd"' . ($n ? '' : ' hidden') . '>' . ($n > 99 ? '99+' : (int) $n) . '</i></a>';
}

add_filter('petgo_app_title', function ($t, $pg) {
    return $pg === 'feed' ? 'События' : $t;
}, 10, 2);

add_filter('petgo_app_page', function ($done, $pg) {
    if ($done || $pg !== 'feed') {
        return $done;
    }
    $uid   = get_current_user_id();
    $items = petgo_feed_items($uid);
    $seen  = isset($GLOBALS['petgo_feed_prev']) ? (int) $GLOBALS['petgo_feed_prev'] : (int) get_user_meta($uid, 'petgo_feed_seen', true);
    update_user_meta($uid, 'petgo_feed_seen', time());
    echo '<style>.pgfd{display:flex;flex-direction:column;gap:6px}.pgfd-d{margin:14px 4px 4px;font-size:13px;font-weight:700;letter-spacing:.02em;color:var(--mu);text-transform:uppercase}.pgfd-d:first-child{margin-top:0}'
        . '.pgfd-i{position:relative;display:flex;align-items:flex-start;gap:12px;padding:12px 14px;text-decoration:none;color:inherit}.pgfd-i.new{box-shadow:inset 3px 0 0 var(--ac,#1E9A5F)}'
        . '.pgfd-ic{flex:none;width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:var(--so);color:var(--tx)}.pgfd-i.new .pgfd-ic{background:rgba(30,154,95,.14);color:#1E9A5F}.pgfd-ic svg{width:20px;height:20px}'
        . '.pgfd-t{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}.pgfd-t b{font-size:15.5px;line-height:1.3}.pgfd-t span{font-size:14.5px;line-height:1.4;color:var(--mu);overflow-wrap:anywhere}.pgfd-t time{font-size:12.5px;color:var(--mu)}'
        . '.pgfd-e{padding:40px 20px;text-align:center;color:var(--mu)}</style>';
    if (!$items) {
        echo '<div class="pgc-card pgfd-e">Пока нет уведомлений. Здесь будут новые клиенты, заказы, питомцы, отзывы, договоры и просьбы позвать менеджера.</div>';
        return true;
    }
    $tz    = petgo_tz();
    $today = wp_date('Y-m-d', time(), $tz);
    $yest  = wp_date('Y-m-d', time() - DAY_IN_SECONDS, $tz);
    $day   = '';
    echo '<div class="pgfd">';
    foreach ($items as $x) {
        $d = wp_date('Y-m-d', (int) $x['ts'], $tz);
        if ($d !== $day) {
            $day = $d;
            echo '<div class="pgfd-d">' . esc_html($d === $today ? 'Сегодня' : ($d === $yest ? 'Вчера' : wp_date('d.m.Y', (int) $x['ts'], $tz))) . '</div>';
        }
        $tag = $x['u'] !== '' ? 'a' : 'div';
        echo '<' . $tag . ' class="pgc-card pgfd-i' . ((int) $x['ts'] > $seen ? ' new' : '') . '"' . ($tag === 'a' ? ' href="' . esc_url($x['u']) . '"' : '') . '><span class="pgfd-ic">' . petgo_icon(petgo_feed_icon($x['t'])) . '</span><span class="pgfd-t"><b>' . esc_html($x['t']) . '</b>' . ($x['b'] !== '' ? '<span>' . esc_html($x['b']) . '</span>' : '') . '<time>' . esc_html(wp_date('H:i', (int) $x['ts'], $tz)) . '</time></span></' . $tag . '>';
    }
    echo '</div>';
    return true;
}, 10, 2);
