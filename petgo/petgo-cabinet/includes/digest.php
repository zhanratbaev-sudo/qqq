<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   36.0 УТРЕННЯЯ СВОДКА ДЛЯ АДМИНА: одно уведомление в 9:00 вместо множества мелких
   (новые клиенты, завершённые по QR заказы, хорошие отзывы). Срочное — заявки, заказы без няни,
   «подтвердите», плохие отзывы, сообщения — по-прежнему приходит сразу.
   ============================================================ */
add_action('init', function () {
    if (!wp_next_scheduled('petgo_morning')) {
        $d = new DateTime('now', petgo_tz());
        $d->setTime(9, 3, 0);
        if ($d->getTimestamp() <= time()) {
            $d->modify('+1 day');
        }
        wp_schedule_event($d->getTimestamp(), 'daily', 'petgo_morning');
    }
});

function petgo_morning_stats() {
    global $wpdb;
    $d = new DateTime('now', petgo_tz());
    $d->setTime(0, 0, 0);
    $to   = $d->getTimestamp();
    $from = $to - DAY_IN_SECONDS;
    $s    = array();
    $s['clients'] = count(get_users(array('role' => 'petgo_client', 'fields' => 'ID', 'date_query' => array(array('after' => gmdate('Y-m-d H:i:s', $from), 'before' => gmdate('Y-m-d H:i:s', $to), 'inclusive' => true)))));
    $s['reqs']    = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . petgo_tb('reqs') . ' WHERE ts >= %d AND ts < %d', $from, $to)); // phpcs:ignore
    $s['done']    = count(petgo_orders(array('numberposts' => -1, 'fields' => 'ids', 'meta_query' => array(array('key' => '_done_at', 'value' => array($from, $to - 1), 'compare' => 'BETWEEN', 'type' => 'NUMERIC')))));
    $rv           = $wpdb->get_row($wpdb->prepare('SELECT COUNT(*) n, AVG(rating) a FROM ' . petgo_tb('reviews') . ' WHERE ts >= %d AND ts < %d', $from, $to)); // phpcs:ignore
    $s['rv']      = $rv ? (int) $rv->n : 0;
    $s['rv_avg']  = $rv && $rv->n ? round((float) $rv->a, 1) : 0;
    $s['wait']    = count(petgo_orders(array('numberposts' => -1, 'fields' => 'ids', 'meta_query' => array(array('key' => '_status', 'value' => 'done')))));
    $s['nonanny'] = count(petgo_orders(array('numberposts' => -1, 'fields' => 'ids', 'meta_query' => array(array('key' => '_status', 'value' => 'new')))));
    return $s;
}

add_action('petgo_morning', function () {
    if (!function_exists('petgo_push_notify')) {
        return;
    }
    $s = petgo_morning_stats();
    $p = array();
    if ($s['done']) {
        $p[] = 'заказов выполнено ' . $s['done'];
    }
    if ($s['reqs']) {
        $p[] = 'заявок ' . $s['reqs'];
    }
    if ($s['clients']) {
        $p[] = 'новых клиентов ' . $s['clients'];
    }
    if ($s['rv']) {
        $p[] = 'отзывов ' . $s['rv'] . ' (средняя ' . str_replace('.', ',', (string) $s['rv_avg']) . ')';
    }
    $now = array();
    if ($s['wait']) {
        $now[] = 'ждут подтверждения ' . $s['wait'];
    }
    if ($s['nonanny']) {
        $now[] = 'без няни ' . $s['nonanny'];
    }
    if (!$p && !$now) {
        return;
    }
    $body = ($p ? 'Вчера: ' . implode(', ', $p) . '.' : 'Вчера тихо.') . ($now ? ' Сейчас: ' . implode(', ', $now) . '.' : '');
    petgo_push_notify(petgo_push_users(array('owner', 'admin')), 'Сводка за вчера', $body, petgo_page_url(array('pg' => 'home')), 'morning');
});
