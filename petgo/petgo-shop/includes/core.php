<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   МАГАЗИН — данные и правила
   Товар: запись petgo_product (название, описание; цена, старая цена, категория, для кого, фото, в наличии).
   Заказ: запись petgo_shop_order (клиент, телефон, товары, сумма, доставка, адрес/заказ няни, статус).
   Доставка: «няня» — бесплатно, вместе с заказом выгула/визита; «адрес» — только в зонах доставки.
   2.0: зоны — ЖК и районы, каждая — круг на карте (центр и радиус), своя цена и порог бесплатной доставки
   (по умолчанию бесплатно от 5 000 ₸). Отметка «услуги» — там же работают няни (выгул, визиты).
   Всё считается на сервере — цифрам из браузера не верим.
   ============================================================ */

function petgo_shop_cfg() {
    $c = get_option('petgo_shop', array());
    $d = array(
        'addr' => 'Алматы, проспект Гагарина, 133/8', 'label' => 'Гагарина 133/8',
        /* примерная точка; администратор подтверждает её на карте (Магазин → Зона доставки) */
        'lat' => 43.2280, 'lng' => 76.9030, 'geo_ok' => '0',
        'radius' => 2.0, 'free' => 5000, 'fee' => 900, 'on' => '1',
    );
    $c = array_merge($d, is_array($c) ? $c : array());
    /* 2.0: магазин можно выключить — тогда его нет нигде: ни на сайте, ни в кабинете, ни в поиске */
    $c['on']     = (string) $c['on'] === '0' ? '0' : '1';
    $c['banner'] = isset($c['banner']) ? (string) $c['banner'] : '';
    /* 2.0: зоны доставки. Старые настройки (один круг вокруг Гагарина 133/8) становятся первой зоной */
    if (!isset($c['zones']) || !is_array($c['zones'])) {
        $c['zones'] = array(array('id' => 'z1', 'n' => (string) $c['label'], 'lat' => (float) $c['lat'], 'lng' => (float) $c['lng'], 'r' => (int) round((float) $c['radius'] * 1000), 'free' => (int) $c['free'], 'fee' => (int) $c['fee'], 'del' => '1', 'svc' => '1'));
    }
    $c['zones'] = petgo_shop_zones_clean($c['zones']);
    $c['lat'] = (float) $c['lat'];
    $c['lng'] = (float) $c['lng'];
    $c['radius'] = max(0.3, min(30, (float) $c['radius']));
    $c['free'] = max(0, (int) $c['free']);
    $c['fee'] = max(0, (int) $c['fee']);
    return $c;
}

function petgo_shop_on() {
    static $on = null;
    if ($on === null) {
        $on = petgo_shop_cfg()['on'] === '1';
    }
    return $on;
}

/* зона: id, n — название (ЖК / район), lat, lng, r — радиус в метрах, free — бесплатно от (0 — всегда бесплатно),
   fee — цена доставки, del — доставляем, svc — работают няни */
function petgo_shop_zones_clean($zs) {
    $out = array();
    $ids = array();
    foreach ((array) $zs as $z) {
        if (!is_array($z)) {
            continue;
        }
        $lat = isset($z['lat']) ? (float) $z['lat'] : 0;
        $lng = isset($z['lng']) ? (float) $z['lng'] : 0;
        $n   = isset($z['n']) ? mb_substr(trim(wp_strip_all_tags((string) $z['n'])), 0, 60) : '';
        if ($n === '' || $lat < 40 || $lat > 56 || $lng < 46 || $lng > 88) {
            continue;
        }
        $id = isset($z['id']) ? preg_replace('/[^a-z0-9]/', '', strtolower((string) $z['id'])) : '';
        if ($id === '' || isset($ids[$id])) {
            $id = 'z' . substr(md5($n . $lat . $lng . count($out)), 0, 6);
        }
        $ids[$id] = 1;
        $out[] = array('id' => $id, 'n' => $n, 'lat' => round($lat, 6), 'lng' => round($lng, 6),
            'r' => max(100, min(15000, (int) (isset($z['r']) ? $z['r'] : 1000))),
            'free' => max(0, (int) (isset($z['free']) ? $z['free'] : 5000)), 'fee' => max(0, (int) (isset($z['fee']) ? $z['fee'] : 900)),
            'del' => isset($z['del']) && (string) $z['del'] === '0' ? '0' : '1', 'svc' => isset($z['svc']) && (string) $z['svc'] === '0' ? '0' : '1');
        if (count($out) >= 80) {
            break;
        }
    }
    return $out;
}

function petgo_shop_zones($only_del = false) {
    $o = array();
    foreach (petgo_shop_cfg()['zones'] as $z) {
        if (!$only_del || $z['del'] === '1') {
            $o[] = $z;
        }
    }
    return $o;
}

function petgo_shop_zone($id) {
    foreach (petgo_shop_zones(true) as $z) {
        if ($z['id'] === (string) $id) {
            return $z;
        }
    }
    return null;
}

/* зона, в которую попадает точка (если кругов несколько — ближайший центр) */
function petgo_shop_zone_at($lat, $lng) {
    if (!is_numeric($lat) || !is_numeric($lng) || (float) $lat === 0.0) {
        return null;
    }
    $best = null;
    $bd   = 1e9;
    foreach (petgo_shop_zones(true) as $z) {
        $d = petgo_shop_km($z['lat'], $z['lng'], (float) $lat, (float) $lng) * 1000;
        if ($d <= $z['r'] && $d < $bd) {
            $best = $z;
            $bd   = $d;
        }
    }
    return $best;
}

/* цена доставки в зону: бесплатно от порога (0 — всегда бесплатно) */
function petgo_shop_zone_fee($z, $sum) {
    return ((int) $z['free'] === 0 || $sum >= (int) $z['free']) ? 0 : (int) $z['fee'];
}

function petgo_shop_pays() {
    return array('cash' => 'Наличными', 'card' => 'Картой курьеру', 'kaspi' => 'Счёт на Kaspi');
}

function petgo_shop_money($n) {
    return number_format((int) $n, 0, '', "\u{00A0}") . "\u{00A0}₸";
}

function petgo_shop_cats() {
    return array('food' => 'Корм', 'treats' => 'Лакомства', 'toys' => 'Игрушки', 'care' => 'Уход и гигиена', 'acc' => 'Аксессуары', 'other' => 'Другое');
}

function petgo_shop_statuses() {
    return array('new' => 'Новый', 'confirmed' => 'Принят', 'delivered' => 'Доставлен', 'cancelled' => 'Отменён');
}

/* расстояние по прямой, км (формула гаверсинуса) */
function petgo_shop_km($la1, $lo1, $la2, $lo2) {
    $r  = 6371.0;
    $dl = deg2rad($la2 - $la1);
    $dn = deg2rad($lo2 - $lo1);
    $a  = sin($dl / 2) * sin($dl / 2) + cos(deg2rad($la1)) * cos(deg2rad($la2)) * sin($dn / 2) * sin($dn / 2);
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function petgo_shop_in_zone($lat, $lng) {
    return petgo_shop_zone_at($lat, $lng) !== null;
}

/* стоимость доставки по адресу: 0 — бесплатно; null — вне зон */
function petgo_shop_fee($sum, $lat, $lng) {
    $z = petgo_shop_zone_at($lat, $lng);
    return $z ? petgo_shop_zone_fee($z, $sum) : null;
}

/* ---------- товары ---------- */
function petgo_shop_products($all = false) {
    /* 2.0: список товаров собирается один раз за запрос (его просят страница, меню, главная, кабинет) */
    static $memo = array();
    $k = $all ? 'all' : 'pub';
    if (isset($memo[$k])) {
        return $memo[$k];
    }
    $q = get_posts(array('post_type' => 'petgo_product', 'post_status' => $all ? array('publish', 'draft') : 'publish', 'numberposts' => 300, 'orderby' => array('menu_order' => 'ASC', 'ID' => 'DESC'), 'no_found_rows' => true));
    $o = array();
    foreach ($q as $p) {
        $o[] = petgo_shop_product($p);
    }
    return $memo[$k] = $o;
}

function petgo_shop_product($p) {
    $p   = is_object($p) ? $p : get_post((int) $p);
    $img = get_post_meta($p->ID, '_img', true);
    return array(
        'id' => (int) $p->ID, 'n' => $p->post_title, 'd' => wp_strip_all_tags((string) $p->post_content),
        'p' => (int) get_post_meta($p->ID, '_price', true), 'o' => (int) get_post_meta($p->ID, '_old', true),
        'c' => (string) get_post_meta($p->ID, '_cat', true), 'pet' => (string) get_post_meta($p->ID, '_pet', true) ?: 'all',
        'u' => (string) get_post_meta($p->ID, '_unit', true), 'img' => is_array($img) && !empty($img['url']) ? set_url_scheme($img['url']) : '',
        /* 2.0: маленькое фото для карточек (быстрее грузится) и отметка «Хит» */
        'th' => is_array($img) && !empty($img['thumb']) ? set_url_scheme($img['thumb']) : (is_array($img) && !empty($img['url']) ? set_url_scheme($img['url']) : ''),
        'hit' => get_post_meta($p->ID, '_hit', true) === '1',
        'in' => get_post_meta($p->ID, '_stock', true) !== '0', 'on' => $p->post_status === 'publish',
    );
}

/* ---------- заказы няни, с которыми можно передать товары (ещё не начались) ---------- */
function petgo_shop_nanny_orders($uid) {
    if (!$uid || !function_exists('petgo_cl_orders')) {
        return array();
    }
    $o = array();
    foreach (petgo_cl_orders($uid, 30) as $ord) {
        $st = petgo_o($ord->ID, 'status');
        $ts = (int) petgo_o($ord->ID, 'ts');
        if (!in_array($st, array('new', 'assigned'), true) || $ts < time() - 30 * MINUTE_IN_SECONDS || $ts > time() + 21 * DAY_IN_SECONDS) {
            continue;
        }
        $svc = petgo_o($ord->ID, 'service') === 'walk' ? 'Выгул' : 'Визит';
        $nn  = (int) petgo_o($ord->ID, 'nanny');
        $o[] = array('id' => (int) $ord->ID, 'ts' => $ts, 'svc' => petgo_site_l($svc), 'pet' => petgo_pet_name($ord->ID), 'when' => petgo_shop_when($ts), 'nanny' => $nn ? petgo_nanny_label($ord->ID) : '');
    }
    usort($o, function ($a, $b) {
        return $a['ts'] - $b['ts'];
    });
    return array_slice($o, 0, 6);
}

function petgo_shop_when($ts) {
    $d = wp_date('Y-m-d', $ts, petgo_tz());
    $t = wp_date('H:i', $ts, petgo_tz());
    if ($d === wp_date('Y-m-d', time(), petgo_tz())) {
        return petgo_site_l('сегодня') . ' ' . $t;
    }
    if ($d === wp_date('Y-m-d', time() + DAY_IN_SECONDS, petgo_tz())) {
        return petgo_site_l('завтра') . ' ' . $t;
    }
    return wp_date('d.m', $ts, petgo_tz()) . ' ' . $t;
}

function petgo_shop_order_client($oid) {
    $pid = (int) petgo_o($oid, 'pet');
    return $pid ? (int) get_post_meta($pid, '_client', true) : 0;
}

/* ---------- заказ из магазина ---------- */
function petgo_shop_get($sid) {
    $p = get_post((int) $sid);
    if (!$p || $p->post_type !== 'petgo_shop_order') {
        return null;
    }
    $m = function ($k) use ($p) {
        return get_post_meta($p->ID, '_' . $k, true);
    };
    return array(
        'id' => (int) $p->ID, 'no' => 'S' . str_pad((string) $p->ID, 4, '0', STR_PAD_LEFT), 'ts' => (int) $m('ts'),
        'uid' => (int) $m('uid'), 'name' => (string) $m('name'), 'phone' => (string) $m('phone'),
        'items' => is_array($m('items')) ? $m('items') : array(), 'sum' => (int) $m('sum'), 'fee' => (int) $m('fee'),
        'mode' => (string) $m('mode'), 'order' => (int) $m('order'), 'addr' => (string) $m('addr'), 'lat' => (float) $m('lat'), 'lng' => (float) $m('lng'),
        'note' => (string) $m('note'), 'st' => (string) $m('st') ?: 'new', 'zone' => (string) $m('zone'), 'pay' => (string) $m('pay'),
    );
}

function petgo_shop_by_order($oid) {
    $ids = get_posts(array('post_type' => 'petgo_shop_order', 'post_status' => 'publish', 'numberposts' => 10, 'fields' => 'ids', 'meta_key' => '_order', 'meta_value' => (int) $oid, 'no_found_rows' => true));
    $o = array();
    foreach ($ids as $id) {
        $s = petgo_shop_get($id);
        if ($s && $s['st'] !== 'cancelled') {
            $o[] = $s;
        }
    }
    return $o;
}

function petgo_shop_items_text($items, $sep = ', ') {
    $t = array();
    foreach ((array) $items as $it) {
        $t[] = $it['n'] . ((int) $it['q'] > 1 ? ' × ' . (int) $it['q'] : '');
    }
    return implode($sep, $t);
}

function petgo_shop_new_count() {
    $n = get_transient('petgo_shop_new');
    if ($n === false) {
        $n = count(get_posts(array('post_type' => 'petgo_shop_order', 'post_status' => 'publish', 'numberposts' => 99, 'fields' => 'ids', 'meta_key' => '_st', 'meta_value' => 'new', 'no_found_rows' => true)));
        set_transient('petgo_shop_new', $n, 10 * MINUTE_IN_SECONDS);
    }
    return (int) $n;
}

/* создать заказ. $in: items [id=>qty], mode nanny|addr, order, addr, lat, lng, name, phone, note. Ответ: [ok, data|msg, field] */
function petgo_shop_create($uid, $in) {
    $L = 'petgo_site_l';
    $items = array();
    $sum   = 0;
    foreach ((array) $in['items'] as $id => $q) {
        $id = (int) $id;
        $q  = max(0, min(20, (int) $q));
        if (!$id || !$q) {
            continue;
        }
        $p = get_post($id);
        if (!$p || $p->post_type !== 'petgo_product' || $p->post_status !== 'publish' || get_post_meta($id, '_stock', true) === '0') {
            return array(false, $L('Один из товаров закончился. Обновите корзину.'), 'items');
        }
        $price   = (int) get_post_meta($id, '_price', true);
        $items[] = array('id' => $id, 'n' => $p->post_title, 'p' => $price, 'q' => $q);
        $sum    += $price * $q;
    }
    if (!$items || count($items) > 40) {
        return array(false, $L('Корзина пуста.'), 'items');
    }
    $mode = $in['mode'] === 'nanny' ? 'nanny' : 'addr';
    $fee  = 0;
    $oid  = 0;
    $addr = mb_substr(sanitize_text_field((string) $in['addr']), 0, 200);
    $lat  = (float) $in['lat'];
    $lng  = (float) $in['lng'];
    if ($mode === 'nanny') {
        $oid = (int) $in['order'];
        $ok  = false;
        foreach (petgo_shop_nanny_orders($uid) as $o) {
            if ($o['id'] === $oid) {
                $ok = true;
            }
        }
        if (!$ok) {
            return array(false, $L('Этот заказ уже начался или отменён. Выберите другой или доставку по адресу.'), 'mode');
        }
    } else {
        /* 2.0: клиент выбирает свой ЖК из списка — или отмечает точку на карте, и мы ищем зону */
        $zone = !empty($in['zone']) ? petgo_shop_zone($in['zone']) : null;
        if (!$zone) {
            $zone = petgo_shop_zone_at($lat, $lng);
        }
        if (!$zone) {
            return array(false, $L('Скоро добавим ваш район. Пока доставляем только в отмеченные ЖК — или няня привезёт бесплатно вместе с выгулом.'), 'addr');
        }
        if (mb_strlen($addr) < 2) {
            return array(false, $L('Укажите подъезд и квартиру.'), 'addr');
        }
        $fee = petgo_shop_zone_fee($zone, $sum);
        $addr = $zone['n'] . ', ' . $addr;
        if (!$lat) {
            $lat = $zone['lat'];
            $lng = $zone['lng'];
        }
    }
    $name  = mb_substr(sanitize_text_field((string) $in['name']), 0, 80);
    $phone = preg_replace('/\D/', '', (string) $in['phone']);
    if (strlen($phone) === 11 && $phone[0] === '8') {
        $phone = '7' . substr($phone, 1);
    }
    if (strlen($phone) === 10) {
        $phone = '7' . $phone;
    }
    if ($uid) {
        $u = get_userdata($uid);
        if ($name === '') {
            $name = $u ? $u->display_name : '';
        }
        if (strlen($phone) < 11) {
            $phone = preg_replace('/\D/', '', (string) get_user_meta($uid, 'petgo_phone', true));
        }
    }
    if (mb_strlen($name) < 2) {
        return array(false, $L('Укажите имя.'), 'name');
    }
    if (strlen($phone) < 11 || strlen($phone) > 15) {
        return array(false, $L('Проверьте номер телефона.'), 'phone');
    }
    $sid = wp_insert_post(array('post_type' => 'petgo_shop_order', 'post_status' => 'publish', 'post_title' => 'Shop ' . $name, 'post_author' => 0));
    if (!$sid || is_wp_error($sid)) {
        return array(false, $L('Не получилось — попробуйте ещё раз.'), '');
    }
    $meta = array('ts' => time(), 'uid' => (int) $uid, 'name' => $name, 'phone' => $phone, 'items' => $items, 'sum' => $sum, 'fee' => (int) $fee, 'mode' => $mode, 'order' => $oid,
        'addr' => $mode === 'addr' ? $addr : '', 'lat' => $mode === 'addr' ? round($lat, 6) : 0, 'lng' => $mode === 'addr' ? round($lng, 6) : 0,
        'note' => mb_substr(sanitize_textarea_field((string) $in['note']), 0, 400), 'st' => 'new',
        'zone' => $mode === 'addr' && !empty($zone) ? $zone['n'] : '', 'pay' => isset($in['pay']) && isset(petgo_shop_pays()[$in['pay']]) ? $in['pay'] : 'cash');
    foreach ($meta as $k => $v) {
        update_post_meta($sid, '_' . $k, $v);
    }
    delete_transient('petgo_shop_new');
    $s = petgo_shop_get($sid);
    petgo_shop_announce($s);
    if (function_exists('petgo_event')) {
        petgo_event('shop_order', array('shop' => (int) $sid, 'user' => (int) $uid, 'sum' => $sum + (int) $fee, 'mode' => $mode));
    }
    return array(true, $s, '');
}

/* сотрудникам — уведомление; клиенту — сообщение в чат кабинета */
function petgo_shop_announce($s) {
    $tot  = petgo_shop_money($s['sum'] + $s['fee']);
    $cnt  = 0;
    foreach ($s['items'] as $it) {
        $cnt += (int) $it['q'];
    }
    $how = ($s['mode'] === 'nanny' ? 'с няней · ' . petgo_shop_when((int) petgo_o($s['order'], 'ts')) : 'доставка: ' . $s['addr']) . ' · ' . mb_strtolower(petgo_shop_pays()[$s['pay'] ?: 'cash']);
    if (function_exists('petgo_push_notify')) {
        petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), 'Магазин: новый заказ ' . $s['no'], $cnt . ' шт. · ' . $tot . ' · ' . $how, petgo_page_url(array('pg' => 'shop')), 'shop');
    }
    if ($s['uid'] && function_exists('petgo_cl_sys')) {
        $msg = $s['mode'] === 'nanny'
            ? sprintf('Заказ из магазина %s принят: %s. Няня привезёт его %s, когда придёт за питомцем. Сумма %s, доставка бесплатно. Оплата при получении: %s.', $s['no'], petgo_shop_items_text($s['items']), petgo_shop_when((int) petgo_o($s['order'], 'ts')), $tot, mb_strtolower(petgo_shop_pays()[$s['pay'] ?: 'cash']))
            : sprintf('Заказ из магазина %s принят: %s. Доставка: %s (%s). Сумма %s. Менеджер свяжется и скажет время. Оплата при получении: %s.', $s['no'], petgo_shop_items_text($s['items']), $s['addr'], $s['fee'] ? petgo_shop_money($s['fee']) : 'бесплатно', $tot, mb_strtolower(petgo_shop_pays()[$s['pay'] ?: 'cash']));
        petgo_cl_sys($s['uid'], $msg, 'shop', $s['id']);
    }
}

function petgo_shop_set_status($sid, $st, $why = '') {
    $s = petgo_shop_get($sid);
    if (!$s || !isset(petgo_shop_statuses()[$st]) || $s['st'] === $st) {
        return false;
    }
    update_post_meta($sid, '_st', $st);
    update_post_meta($sid, '_st_' . $st, time());
    delete_transient('petgo_shop_new');
    if ($s['uid'] && function_exists('petgo_cl_sys')) {
        $t = array(
            'confirmed' => $s['mode'] === 'nanny' ? 'Заказ из магазина %s собран — няня привезёт его вместе с заказом.' : 'Заказ из магазина %s принят, скоро привезём.',
            'delivered' => 'Заказ из магазина %s доставлен. Спасибо!',
            'cancelled' => 'Заказ из магазина %s отменён.' . ($why !== '' ? ' ' . $why : ' Напишите нам, если это ошибка.'),
        );
        if (isset($t[$st])) {
            /* одно сообщение на каждую смену статуса (subject — номер заказа, вид — статус) */
            petgo_cl_sys($s['uid'], sprintf($t[$st], $s['no']), 'shop_' . $st, $s['id']);
        }
    }
    return true;
}

/* заказ няни завершён — товары, которые она везла, доставлены; заказ няни отменён — товары ждут решения менеджера */
add_action('petgo_order_event', function ($oid, $ev) {
    if ($ev !== 'done' && $ev !== 'cancelled') {
        return;
    }
    foreach (petgo_shop_by_order($oid) as $s) {
        if ($ev === 'done' && in_array($s['st'], array('new', 'confirmed'), true)) {
            petgo_shop_set_status($s['id'], 'delivered');
        } elseif ($ev === 'cancelled' && $s['st'] !== 'delivered') {
            update_post_meta($s['id'], '_st', 'new');
            update_post_meta($s['id'], '_note', trim($s['note'] . ' [Заказ няни отменён — договоритесь с клиентом о доставке]'));
            delete_transient('petgo_shop_new');
            if (function_exists('petgo_push_notify')) {
                petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), 'Магазин: заказ ' . $s['no'] . ' без няни', 'Заказ няни отменён — договоритесь с клиентом о доставке товаров', petgo_page_url(array('pg' => 'shop')), 'shop');
            }
        }
    }
}, 20, 2);
