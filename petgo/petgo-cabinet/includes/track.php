<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ЖИВАЯ ПРОГУЛКА 34.0
   Пока у няни заказ «на месте» или «с питомцем», её телефон (приложение открыто) раз в несколько секунд
   передаёт местоположение. Хозяин видит питомца на карте в своём кабинете: где он сейчас, маршрут прогулки,
   что делает (быстрые статусы няни) и фото — каждое фото стоит на карте там, где его сделали.
   ============================================================ */

/* точки маршрута заказа: [широта, долгота, время, точность] */
function petgo_trk_points($oid) {
    $t = get_post_meta($oid, '_track', true);
    return is_array($t) ? $t : array();
}

/* расстояние в метрах */
function petgo_trk_dist($a, $b) {
    $r  = 6371000;
    $la = deg2rad($b[0] - $a[0]);
    $lo = deg2rad($b[1] - $a[1]);
    $x  = sin($la / 2) * sin($la / 2) + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($lo / 2) * sin($lo / 2);
    return 2 * $r * atan2(sqrt($x), sqrt(1 - $x));
}

/* упростить маршрут до $max точек (для карты) */
function petgo_trk_simplify($pts, $max) {
    $n = count($pts);
    $o = array();
    if ($n <= $max) {
        foreach ($pts as $p) {
            $o[] = array(round((float) $p[0], 6), round((float) $p[1], 6), (int) $p[2]);
        }
        return $o;
    }
    $step = $n / $max;
    for ($i = 0; $i < $max; $i++) {
        $p   = $pts[(int) floor($i * $step)];
        $o[] = array(round((float) $p[0], 6), round((float) $p[1], 6), (int) $p[2]);
    }
    $p   = end($pts);
    $o[] = array(round((float) $p[0], 6), round((float) $p[1], 6), (int) $p[2]);
    return $o;
}

/* последнее известное место по заказу (для фото отчёта) */
function petgo_trk_last_geo($oid) {
    $t = petgo_trk_points($oid);
    if ($t) {
        $p = end($t);
        if (time() - (int) $p[2] < 1800) {
            return array('lat' => (float) $p[0], 'lng' => (float) $p[1]);
        }
    }
    if (petgo_o($oid, 'lat') !== '') {
        return array('lat' => (float) petgo_o($oid, 'lat'), 'lng' => (float) petgo_o($oid, 'lng'));
    }
    return array();
}

/* активные заказы текущего сотрудника, по которым идёт трансляция */
function petgo_trk_active($uid) {
    $o = array();
    foreach (petgo_orders(array('numberposts' => 10, 'meta_query' => array('relation' => 'AND', array('key' => '_nanny', 'value' => (int) $uid), array('key' => '_status', 'value' => array('arrived', 'picked'), 'compare' => 'IN')))) as $x) {
        $o[] = (int) $x->ID;
    }
    return $o;
}

function petgo_trk_add($oid, $lat, $lng, $acc) {
    $t   = petgo_trk_points($oid);
    $now = time();
    $p   = array(round($lat, 6), round($lng, 6), $now, (int) $acc);
    if ($t) {
        $l = end($t);
        $d = petgo_trk_dist($l, $p);
        /* стоим на месте — обновляем время последней точки, а не копим одинаковые */
        if ($d < 6 || ($acc > 60 && $d < $acc / 2)) {
            $t[count($t) - 1][2] = $now;
            update_post_meta($oid, '_track', $t);
            return;
        }
        /* «телепорт» — ошибка геолокации, пропускаем (больше 60 м/с) */
        if ($d / max(1, $now - (int) $l[2]) > 60) {
            return;
        }
    }
    $t[] = $p;
    if (count($t) > 1500) {
        $t = array_merge(array_slice($t, 0, 1), petgo_trk_simplify(array_slice($t, 1), 1200));
    }
    update_post_meta($oid, '_track', $t);
}

add_action('wp_ajax_petgo_trk', 'petgo_ajax_trk');
function petgo_ajax_trk() {
    check_ajax_referer('petgo_ord', 'n');
    if (!petgo_can('worker')) {
        wp_send_json(array('ok' => false));
    }
    $uid = get_current_user_id();
    $op  = sanitize_key((string) petgo_p('op'));
    if ($op === 'act') {
        $out = array();
        foreach (petgo_trk_active($uid) as $oid) {
            $out[] = array('id' => $oid, 'pet' => petgo_pet_name($oid), 'st' => petgo_o($oid, 'status'));
        }
        wp_send_json(array('ok' => true, 'orders' => $out));
    }
    if ($op === 'pt') {
        $lat = petgo_p('lat');
        $lng = petgo_p('lng');
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            wp_send_json(array('ok' => false));
        }
        $act = petgo_trk_active($uid);
        foreach ($act as $oid) {
            petgo_trk_add($oid, (float) $lat, (float) $lng, max(0, (int) petgo_p('acc')));
        }
        wp_send_json(array('ok' => true, 'n' => count($act)));
    }
    $oid = (int) petgo_p('order');
    if (!petgo_order_of_nanny($oid, $uid) || !in_array(petgo_o($oid, 'status'), array('arrived', 'picked'), true)) {
        wp_send_json(array('ok' => false, 'msg' => petgo_t('err_na')));
    }
    if ($op === 'st') {
        $s = sanitize_key((string) petgo_p('st'));
        if (!isset(petgo_cl_live_words()[$s])) {
            wp_send_json(array('ok' => false));
        }
        update_post_meta($oid, '_live_st', $s);
        update_post_meta($oid, '_live_st_t', time());
        wp_send_json(array('ok' => true));
    }
    if ($op === 'photo') {
        $r = isset($_FILES['photo']) ? petgo_save_image($_FILES['photo'], 1600, 72) : 'none';
        if (!is_array($r)) {
            wp_send_json(array('ok' => false, 'msg' => petgo_t($r === 'none' ? 'err_photo_req' : $r)));
        }
        $lat = petgo_p('lat');
        $lng = petgo_p('lng');
        $geo = (is_numeric($lat) && is_numeric($lng)) ? array('lat' => round((float) $lat, 6), 'lng' => round((float) $lng, 6)) : petgo_trk_last_geo($oid);
        if ($geo && is_numeric($lat)) {
            petgo_trk_add($oid, (float) $lat, (float) $lng, (int) petgo_p('acc'));
        }
        $pid = (int) petgo_o($oid, 'pet');
        $ph  = petgo_photos($pid);
        $ph[] = array_merge(array('id' => wp_generate_password(10, false), 'url' => $r['url'], 'file' => $r['file'], 'by' => $uid, 'order' => $oid, 't' => time(), 'walk' => 1), $geo);
        /* храним до 80 фото: самые старые прогулочные удаляются */
        $av = get_post_meta($pid, '_avatar', true);
        while (count($ph) > 80) {
            $idx = 0;
            foreach ($ph as $i => $x) {
                if (!empty($x['walk']) && !(is_array($av) && isset($av['url']) && $av['url'] === $x['url'])) {
                    $idx = $i;
                    break;
                }
            }
            if (!empty($ph[$idx]['file'])) {
                petgo_rm($ph[$idx]['file']);
            }
            array_splice($ph, $idx, 1);
        }
        update_post_meta($pid, '_photos', array_values($ph));
        if (!is_array($av) || empty($av['url'])) {
            update_post_meta($pid, '_avatar', array('url' => $r['url'], 'file' => ''));
        }
        wp_send_json(array('ok' => true, 'msg' => petgo_t('trk_ph_ok')));
    }
    wp_send_json(array('ok' => false));
}

/* блок в окне заказа у няни: трансляция, статус для хозяина, фото с местом */
function petgo_trk_worker_block($id) {
    $W  = petgo_cl_live_words();
    $cur = (string) petgo_o($id, 'live_st');
    $h  = '<div class="pgt" data-order="' . (int) $id . '"><div class="pgt-h"><span class="pgt-dot" aria-hidden="true"></span><b>' . esc_html(petgo_t('trk_live')) . '</b><small data-trk-state>' . esc_html(petgo_t('trk_wait')) . '</small></div>';
    $h .= '<p class="mu pgt-sub">' . esc_html(petgo_t('trk_st_hint')) . '</p><div class="pgt-st">';
    foreach ($W as $k => $t) {
        $h .= '<button type="button" class="pgt-chip' . ($cur === $k ? ' on' : '') . '" data-trk-st="' . esc_attr($k) . '">' . esc_html(petgo_t('lw_' . $k)) . '</button>';
    }
    $h .= '</div><label class="pgc-btn sec wide pgt-photo"><input type="file" accept="image/*" capture="environment" hidden data-trk-photo>' . petgo_ic('photo') . '<span>' . esc_html(petgo_t('trk_photo')) . '</span></label></div>';
    return $h;
}

/* скрипт трансляции, быстрых статусов и кнопок «Скопировать / Поделиться» — для всех сотрудников */
add_filter('petgo_head_extra', function ($h) {
    return $h . '<script src="' . esc_url(petgo_asset_url('track.js')) . '" defer></script>';
});
