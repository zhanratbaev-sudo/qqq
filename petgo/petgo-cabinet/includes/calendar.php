<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   КАЛЕНДАРЬ (как Google Календарь): день, 3 дня, неделя, месяц, расписание.
   Сервер отдаёт только данные заказов, сетку рисует скрипт на телефоне.
   ============================================================ */
function petgo_cal_events($from, $to) {
    $list = petgo_orders(array('numberposts' => 1500, 'meta_query' => array(array('key' => '_ts', 'value' => array((int) $from, (int) $to - 1), 'compare' => 'BETWEEN', 'type' => 'NUMERIC'))));
    petgo_prime($list);
    $out = array();
    foreach ($list as $o) {
        $id = (int) $o->ID;
        $st = (string) petgo_o($id, 'status');
        if ($st === 'cancelled') {
            continue;
        }
        $out[] = array('i' => $id, 's' => (int) petgo_o($id, 'ts'), 'd' => max(15, (int) petgo_o($id, 'dur')), 'p' => petgo_pet_name($id), 'n' => (int) petgo_o($id, 'nanny'), 'st' => $st, 'sv' => (string) petgo_o($id, 'service'));
    }
    return $out;
}

/* сотрудники с постоянным цветом (по порядку добавления) */
function petgo_cal_staff($events) {
    $out  = array();
    $seen = array();
    $list = petgo_staff_users();
    usort($list, function ($a, $b) {
        return (int) $a->ID - (int) $b->ID;
    });
    $i = 0;
    foreach ($list as $u) {
        $seen[(int) $u->ID] = 1;
        $out[] = array('id' => (int) $u->ID, 'nm' => $u->display_name, 'c' => $i++ % 10, 'off' => get_user_meta($u->ID, 'petgo_disabled', true) ? 1 : 0);
    }
    foreach ($events as $e) {
        if ($e['n'] && !isset($seen[$e['n']])) {
            $seen[$e['n']] = 1;
            $x     = get_userdata($e['n']);
            $out[] = array('id' => $e['n'], 'nm' => $x ? $x->display_name : (string) petgo_o($e['i'], 'nanny_name'), 'c' => $i++ % 10, 'off' => 0);
        }
    }
    return $out;
}

function petgo_page_cal() {
    $tz  = petgo_tz();
    $now = new DateTime('now', $tz);
    $a   = new DateTime($now->format('Y-m-01') . ' 00:00:00', $tz);
    $a->modify('-7 day');
    $b = clone $a;
    $b->modify('+49 day');
    $ev  = petgo_cal_events($a->getTimestamp(), $b->getTimestamp());
    $cfg = array('from' => $a->getTimestamp(), 'to' => $b->getTimestamp(), 'ev' => $ev, 'staff' => petgo_cal_staff($ev), 'tzo' => 5 * HOUR_IN_SECONDS, 'now' => time(), 'svc' => array('walk' => petgo_t('s_walk'), 'visit' => petgo_t('s_visit'), 'other' => petgo_t('s_other')), 'stl' => array('new' => petgo_t('st_new'), 'assigned' => petgo_t('st_assigned'), 'arrived' => petgo_t('st_arrived'), 'picked' => petgo_t('st_picked'), 'done' => petgo_t('st_done'), 'confirmed' => petgo_t('st_confirmed')));
    echo '<div class="pgc-calapp pgc-full" id="pgc-calapp"><div class="pgc-load"><i class="pgc-spin"></i></div></div>';
    echo '<script type="application/json" id="pgc-cal-data">' . wp_json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . '</script>';
}

add_action('wp_ajax_petgo_cal', 'petgo_ajax_cal');
function petgo_ajax_cal() {
    check_ajax_referer('petgo_ord', 'n');
    if (!petgo_can('office')) {
        wp_send_json(array('ok' => false));
    }
    $from = isset($_POST['from']) ? (int) $_POST['from'] : 0;
    $to   = isset($_POST['to']) ? (int) $_POST['to'] : 0;
    if ($from <= 0 || $to <= $from || $to - $from > 70 * DAY_IN_SECONDS) {
        wp_send_json(array('ok' => false));
    }
    $ev = petgo_cal_events($from, $to);
    wp_send_json(array('ok' => true, 'from' => $from, 'to' => $to, 'ev' => $ev, 'staff' => petgo_cal_staff($ev), 'now' => time()));
}
