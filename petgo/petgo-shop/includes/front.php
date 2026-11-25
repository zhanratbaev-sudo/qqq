<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   МАГАЗИН НА САЙТЕ И В ПРИЛОЖЕНИИ КЛИЕНТА (2.0)
   Сайт: страница «Магазин» (/magazin/). Витрина приходит уже готовой в HTML (видно сразу и видно поиску),
   скрипт assets/shop.js добавляет корзину, поиск, фильтры и оформление.
   Приложение клиента: на главной — баннер «Магазин», он открывает магазин на весь экран со стрелкой «назад».
   Магазин выключен — его нет нигде: ни страницы, ни меню, ни блоков, ни карты сайта, ни стилей и скриптов.
   ============================================================ */

add_filter('petgo_site_pages', function ($p) {
    if (petgo_shop_on()) {
        $p['shop'] = array('slug' => 'magazin', 'name' => petgo_site_l('Магазин'), 'title' => petgo_site_l('Магазин для питомцев в Алматы'), 'nav' => true);
    }
    return $p;
});

/* поиск: заголовок, описание и разметка товаров (Google, Яндекс) */
add_filter('petgo_site_seo_title', function ($t, $page) {
    return $page === 'shop' ? petgo_site_l('Зоомагазин в Алматы с доставкой: корм, игрушки, уход — PetGo') : $t;
}, 20, 2);
add_filter('petgo_site_seo_desc', function ($d, $page) {
    if ($page !== 'shop') {
        return $d;
    }
    $z = petgo_shop_zones(true);
    return petgo_site_lf('Корм, лакомства, игрушки и всё для собак и кошек с доставкой в Алматы. Бесплатно от %s, няня PetGo привезёт заказ вместе с выгулом. Оплата при получении.', petgo_shop_money($z ? $z[0]['free'] : 5000));
}, 20, 2);
add_filter('petgo_site_schema', function ($g, $page) {
    if ($page !== 'shop') {
        return $g;
    }
    $list = array();
    $i    = 0;
    foreach (petgo_shop_products() as $p) {
        $o = array('@type' => 'Product', 'name' => $p['n'], 'offers' => array('@type' => 'Offer', 'price' => (string) $p['p'], 'priceCurrency' => 'KZT', 'availability' => 'https://schema.org/' . ($p['in'] ? 'InStock' : 'OutOfStock'), 'url' => petgo_site_url('shop') . '#p' . $p['id']));
        if ($p['img']) {
            $o['image'] = $p['img'];
        }
        if ($p['d'] !== '') {
            $o['description'] = wp_trim_words($p['d'], 40, '…');
        }
        $list[] = array('@type' => 'ListItem', 'position' => ++$i, 'item' => $o);
        if ($i >= 30) {
            break;
        }
    }
    if ($list) {
        $g[] = array('@type' => 'ItemList', 'name' => petgo_site_l('Магазин для питомцев в Алматы'), 'itemListElement' => $list);
    }
    return $g;
}, 20, 2);

add_filter('petgo_site_page_html', function ($h, $page) {
    if ($page !== 'shop' || !petgo_shop_on()) {
        return $h;
    }
    return petgo_site_b_title(petgo_site_l('Магазин для питомцев'), petgo_site_l('Корм, лакомства, игрушки и уход — с доставкой по ЖК Алматы. Оплата при получении.'))
        . '<section class="pg-sec pg-sec-tight"><div class="pg-wrap">' . petgo_shop_box('site') . '</div></section>';
}, 10, 2);

add_filter('petgo_site_dock_item', function ($h, $k, $page) {
    if ($k !== 'reviews' || !petgo_shop_on() || !petgo_shop_products()) {
        return $h;
    }
    return '<a href="' . esc_url(petgo_site_url('shop')) . '"' . ($page === 'shop' ? ' class="on" aria-current="page"' : '') . '>' . petgo_shop_icon('pg-i') . '<span>' . esc_html(petgo_site_l('Магазин')) . '</span></a>';
}, 10, 3);

function petgo_shop_icon($cls = 'pg-i') {
    return '<svg class="' . esc_attr($cls) . '" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8h14l-1 12.2a1 1 0 0 1-1 .8H7a1 1 0 0 1-1-.8L5 8Z"/><path d="M9 10V7a3 3 0 0 1 6 0v3"/></svg>';
}
add_filter('petgo_icon_svg', function ($svg, $n) {
    return $n === 'bag' && $svg === '' ? petgo_svg('<path d="M5 8h14l-1 12.2a1 1 0 0 1-1 .8H7a1 1 0 0 1-1-.8L5 8Z"/><path d="M9 10V7a3 3 0 0 1 6 0v3"/>') : $svg;
}, 10, 2);

/* блок на главной сайта: несколько товаров и кнопка в магазин */
add_filter('petgo_site_home_sections', function ($sec) {
    if (!petgo_shop_on()) {
        return $sec;
    }
    $all = array_values(array_filter(petgo_shop_products(), function ($p) {
        return $p['in'];
    }));
    if (!$all) {
        return $sec;
    }
    usort($all, function ($a, $b) {
        return (int) $b['hit'] - (int) $a['hit'];
    });
    $h = '<section class="pg-sec" id="pg-shop"><div class="pg-wrap"><div class="psh-teaser pg-rise"><div class="psh-teaser-h"><div><h2 class="pg-h">' . esc_html(petgo_site_l('Магазин для питомцев')) . '</h2><p class="pg-sub">' . esc_html(petgo_site_l('Корм, игрушки и уход с доставкой. Няня привезёт бесплатно вместе с выгулом.')) . '</p></div><a class="pg-btn pg-btn-primary" href="' . esc_url(petgo_site_url('shop')) . '">' . esc_html(petgo_site_l('В магазин')) . '</a></div><div class="psh-teaser-row">';
    foreach (array_slice($all, 0, 4) as $p) {
        $h .= '<a class="psh-tc" href="' . esc_url(petgo_site_url('shop')) . '#p' . (int) $p['id'] . '"><span class="psh-tc-i">' . ($p['th'] ? '<img src="' . esc_url($p['th']) . '" alt="' . esc_attr($p['n']) . '" loading="lazy" decoding="async" width="240" height="240">' : petgo_shop_icon('psh-ph')) . '</span><b>' . esc_html($p['n']) . '</b><span>' . esc_html(petgo_shop_money($p['p'])) . '</span></a>';
    }
    $sec[25] = $h . '</div></div></div></section>';
    return $sec;
});

/* ---------- приложение клиента: баннер «Магазин» на главной и магазин на весь экран ---------- */
function petgo_shop_banner_img() {
    $c = petgo_shop_cfg();
    if ($c['banner'] !== '') {
        return $c['banner'];
    }
    foreach (petgo_shop_products() as $p) {
        if ($p['in'] && $p['img']) {
            return $p['img'];
        }
    }
    return '';
}
add_filter('petgo_cl_home_mid', function ($h, $uid) {
    if (!petgo_shop_on() || !petgo_shop_products()) {
        return $h;
    }
    $img = petgo_shop_banner_img();
    $z   = petgo_shop_zones(true);
    $sub = $z ? petgo_site_lf('Корм, игрушки и уход. Бесплатная доставка от %s', petgo_shop_money($z[0]['free'])) : petgo_site_l('Корм, игрушки и уход — няня привезёт вместе с выгулом');
    return $h . '<button type="button" class="pg-b psh-hb" data-k-open="shop"><span class="psh-hb-t"><small>' . esc_html(petgo_site_l('Магазин PetGo')) . '</small><b>' . esc_html(petgo_site_l('Всё для вашего питомца')) . '</b><span>' . esc_html($sub) . '</span><em>' . esc_html(petgo_site_l('Перейти в магазин')) . ' →</em></span><span class="psh-hb-i"' . ($img === '' ? ' data-k-art="art:dog-gold"' : '') . '>' . ($img !== '' ? '<img src="' . esc_url($img) . '" alt="" loading="lazy" decoding="async">' : '') . '</span></button>';
}, 10, 2);

add_filter('petgo_cl_sheets', function ($h, $uid) {
    if (!petgo_shop_on() || !petgo_shop_products()) {
        return $h;
    }
    /* магазин в приложении — отдельный экран на весь экран: стрелка «назад» возвращает в приложение */
    $L = 'petgo_site_l';
    return $h . '<div class="pgk-sheet pgk-sheet-page pgk-sheet-shop" id="pgk-shop" hidden role="dialog" aria-modal="true" aria-label="' . esc_attr($L('Магазин')) . '"><div class="pgk-sin"><div class="pgk-pgh"><button type="button" class="pg-b pgk-pgback" data-k-close aria-label="' . esc_attr($L('Назад')) . '"><svg class="pg-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14.5 5.5-6.5 6.5 6.5 6.5"/></svg><span>' . esc_html($L('Назад')) . '</span></button><b>' . esc_html($L('Магазин')) . '</b><button type="button" class="pg-b pgk-pgcart" data-psh-cart aria-label="' . esc_attr($L('Корзина')) . '">' . petgo_shop_icon('pg-i') . '<i data-psh-n hidden></i></button></div><div class="pgk-sc">' . petgo_shop_box('cabinet', $uid) . '</div></div></div>';
}, 10, 2);

/* ---------- витрина ---------- */
function petgo_shop_box($ctx, $uid = null) {
    $uid = $uid === null ? (function_exists('petgo_cl_uid') ? petgo_cl_uid() : 0) : (int) $uid;
    $cfg = petgo_shop_js_cfg($ctx, $uid);
    return '<div class="psh" data-psh="' . esc_attr($ctx) . '"><script type="application/json" class="psh-cfg">' . wp_json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script><div class="psh-v">' . petgo_shop_list_html($cfg) . '</div></div>';
}

/* витрина в HTML — та же разметка, что рисует shop.js (видна сразу, до загрузки скрипта, и поиску) */
function petgo_shop_list_html($cfg) {
    $L = 'petgo_site_l';
    $h = '<label class="psh-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg><input type="search" data-psh-q placeholder="' . esc_attr($L('Поиск товаров…')) . '" autocomplete="off" enterkeyhint="search"></label>';
    $h .= '<div class="psh-hero"><div class="psh-hero-t"><h2>' . esc_html($L('Всё для вашего')) . ' <em>' . esc_html($L('питомца')) . '</em></h2><p>' . esc_html($cfg['heroSub']) . '</p><button type="button" class="pg-b psh-hero-go" data-psh-to="grid">' . esc_html($L('Смотреть товары')) . ' →</button></div><div class="psh-hero-i"' . ($cfg['banner'] === '' ? ' data-psh-art="art:dog-gold"' : '') . '>' . ($cfg['banner'] !== '' ? '<img src="' . esc_url($cfg['banner']) . '" alt="" decoding="async" fetchpriority="high">' : '') . '</div></div>';
    if (count($cfg['cats']) > 1) {
        $h .= '<div class="psh-tiles" role="tablist">';
        foreach ($cfg['cats'] as $k => $c) {
            $h .= '<button type="button" class="pg-b psh-tile" data-cat="' . esc_attr($k) . '" role="tab" aria-selected="false"><span class="psh-tile-i">' . ($c['img'] !== '' ? '<img src="' . esc_url($c['img']) . '" alt="" loading="lazy" decoding="async">' : petgo_shop_icon('psh-ph')) . '</span><span>' . esc_html($c['n']) . '</span></button>';
        }
        $h .= '</div>';
    }
    $h .= '<div class="psh-sh" id="psh-grid"><h3>' . esc_html($L('Популярные товары')) . '</h3></div><div class="psh-grid">';
    foreach ($cfg['items'] as $p) {
        $h .= petgo_shop_card_html($p);
    }
    return $h . '</div>';
}

function petgo_shop_card_html($p) {
    $L     = 'petgo_site_l';
    $badge = $p['o'] > $p['p'] ? '<em class="psh-badge sale">−' . (int) round(100 - $p['p'] * 100 / $p['o']) . '%</em>' : ($p['hit'] ? '<em class="psh-badge">' . esc_html($L('Хит')) . '</em>' : '');
    $img   = $p['th'] !== '' ? '<img src="' . esc_url($p['th']) . '" alt="' . esc_attr($p['n']) . '" loading="lazy" decoding="async" width="300" height="300">' : '<span class="psh-ph">' . petgo_shop_icon('pg-i') . '</span>';
    $btn   = $p['in'] ? '<button type="button" class="pg-b psh-add" data-add="' . (int) $p['id'] . '" aria-label="' . esc_attr($L('В корзину')) . '">' . petgo_shop_cart_svg() . '</button>' : '<span class="psh-na">' . esc_html($L('Нет в наличии')) . '</span>';
    return '<article class="psh-c' . ($p['in'] ? '' : ' out') . '" id="p' . (int) $p['id'] . '"><div class="psh-ci" data-more="' . (int) $p['id'] . '">' . $img . $badge . '<button type="button" class="pg-b psh-fav" data-fav="' . (int) $p['id'] . '" aria-label="' . esc_attr($L('В избранное')) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10C19.5 15.4 12 20 12 20Z"/></svg></button></div><div class="psh-cb"><b class="psh-cn" data-more="' . (int) $p['id'] . '">' . esc_html($p['n']) . '</b>' . ($p['u'] !== '' ? '<small class="psh-cu">' . esc_html($p['u']) . '</small>' : '') . ($p['d'] !== '' ? '<p class="psh-cd">' . esc_html($p['d']) . '</p>' : '') . '<div class="psh-cf"><span class="psh-pr">' . ($p['o'] > $p['p'] ? '<s>' . esc_html(petgo_shop_money($p['o'])) . '</s>' : '') . '<b>' . esc_html(petgo_shop_money($p['p'])) . '</b></span>' . $btn . '</div></div></article>';
}

function petgo_shop_cart_svg() {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h2l2.2 10.2a1 1 0 0 0 1 .8h8.6a1 1 0 0 0 1-.8L19.5 8H6"/><circle cx="9.5" cy="19" r="1.4"/><circle cx="16.5" cy="19" r="1.4"/></svg>';
}

function petgo_shop_me($uid) {
    if (!$uid) {
        return null;
    }
    $u  = get_userdata($uid);
    $me = array('name' => $u ? $u->display_name : '', 'phone' => function_exists('petgo_site_phone_fmt') ? petgo_site_phone_fmt((string) get_user_meta($uid, 'petgo_phone', true)) : '', 'addr' => '', 'lat' => 0, 'lng' => 0, 'zone' => (string) get_user_meta($uid, 'petgo_shop_zone', true), 'flat' => (string) get_user_meta($uid, 'petgo_shop_flat', true));
    foreach (function_exists('petgo_cl_pets') ? petgo_cl_pets($uid) : array() as $p) {
        $a = trim((string) get_post_meta($p->ID, '_pf_address', true));
        if ($a === '') {
            continue;
        }
        $me['addr'] = $a;
        $g = get_post_meta($p->ID, '_home_geo', true);
        if (is_array($g) && isset($g[2]) && $g[2] === md5($a)) {
            $me['lat'] = (float) $g[0];
            $me['lng'] = (float) $g[1];
        }
        break;
    }
    /* дом клиента уже в одной из зон — ЖК выбран сам */
    if ($me['zone'] === '' && $me['lat']) {
        $z = petgo_shop_zone_at($me['lat'], $me['lng']);
        $me['zone'] = $z ? $z['id'] : '';
    }
    return $me;
}

function petgo_shop_mine($uid) {
    if (!$uid) {
        return array();
    }
    $o  = array();
    $st = petgo_shop_statuses();
    foreach (get_posts(array('post_type' => 'petgo_shop_order', 'post_status' => 'publish', 'numberposts' => 10, 'fields' => 'ids', 'meta_key' => '_uid', 'meta_value' => (int) $uid, 'no_found_rows' => true)) as $id) {
        $s = petgo_shop_get($id);
        if ($s) {
            $o[] = array('no' => $s['no'], 'when' => wp_date('d.m.Y', $s['ts'], petgo_tz()), 'items' => petgo_shop_items_text($s['items']), 'tot' => petgo_shop_money($s['sum'] + $s['fee']), 'st' => $s['st'], 'stl' => petgo_site_l($st[$s['st']]), 'how' => $s['mode'] === 'nanny' ? petgo_site_l('Привезёт няня') : $s['addr']);
        }
    }
    return $o;
}

function petgo_shop_js_cfg($ctx, $uid) {
    $cats  = petgo_shop_cats();
    $items = petgo_shop_products();
    /* «Хиты» и товары со скидкой — первыми */
    usort($items, function ($a, $b) {
        $x = ($b['in'] ? 4 : 0) + ($b['hit'] ? 2 : 0) + ($b['o'] > $b['p'] ? 1 : 0);
        $y = ($a['in'] ? 4 : 0) + ($a['hit'] ? 2 : 0) + ($a['o'] > $a['p'] ? 1 : 0);
        return $x - $y;
    });
    $used = array();
    foreach ($items as $p) {
        if ($p['c'] !== '' && isset($cats[$p['c']])) {
            if (!isset($used[$p['c']])) {
                $used[$p['c']] = array('n' => petgo_site_l($cats[$p['c']]), 'img' => '');
            }
            if ($used[$p['c']]['img'] === '' && $p['th'] !== '') {
                $used[$p['c']]['img'] = $p['th'];
            }
        }
    }
    $zones = array();
    foreach (petgo_shop_zones() as $z) {
        $zones[] = array('id' => $z['id'], 'n' => $z['n'], 'lat' => $z['lat'], 'lng' => $z['lng'], 'r' => $z['r'], 'free' => $z['free'], 'fee' => $z['fee'], 'del' => $z['del'] === '1', 'svc' => $z['svc'] === '1');
    }
    $dz  = petgo_shop_zones(true);
    $ll  = function_exists('petgo_asset_where') && is_readable(dirname(petgo_asset_where('leaflet/leaflet.js')) . '/assets/leaflet/leaflet.js');
    $tr  = array();
    foreach (petgo_shop_js_strings() as $s) {
        $tr[$s] = petgo_site_l($s);
    }
    $pays = array();
    foreach (petgo_shop_pays() as $k => $v) {
        $pays[$k] = petgo_site_l($v);
    }
    return array(
        'ctx' => $ctx, 'items' => $items, 'cats' => $used, 'lang' => petgo_site_lang(), 'zones' => $zones, 'pays' => $pays,
        'banner' => petgo_shop_cfg()['banner'],
        'heroSub' => $dz ? petgo_site_lf('Корм, игрушки и уход с доставкой. Бесплатно от %s.', petgo_shop_money($dz[0]['free'])) : petgo_site_l('Корм, игрушки и уход — няня привезёт вместе с выгулом.'),
        'me' => petgo_shop_me($uid), 'nanny' => petgo_shop_nanny_orders($uid), 'mine' => petgo_shop_mine($uid),
        'aj' => function_exists('petgo_rel_url') ? petgo_rel_url(admin_url('admin-ajax.php')) : admin_url('admin-ajax.php'), 'tk' => petgo_req_token(),
        'acc' => petgo_site_url('account'), 'shop' => petgo_site_url('shop'),
        'leaflet' => $ll ? array('css' => petgo_asset_url('leaflet/leaflet.css'), 'js' => petgo_asset_url('leaflet/leaflet.js')) : null,
        'tr' => $tr,
    );
}

/* стили и скрипт магазина — только когда магазин включён */
add_action('petgo_site_head', function () {
    if (petgo_shop_on()) {
        echo '<link rel="stylesheet" href="' . esc_url(petgo_asset_url('shop.css')) . '">';
    }
}, 30);
add_action('petgo_site_foot', function () {
    if (petgo_shop_on()) {
        echo '<script src="' . esc_url(petgo_asset_url('shop.js')) . '" defer></script>';
    }
}, 30);

/* ---------- приём заказа (с сайта и из приложения) ---------- */
add_action('wp_ajax_petgo_shop', 'petgo_shop_ajax');
add_action('wp_ajax_nopriv_petgo_shop', 'petgo_shop_ajax');
function petgo_shop_ajax() {
    nocache_headers();
    if (isset($_POST['lang']) && function_exists('petgo_site_lang')) {
        petgo_site_lang(sanitize_key(wp_unslash($_POST['lang'])));
    }
    $L    = 'petgo_site_l';
    $fail = function ($msg, $field = '') {
        wp_send_json(array('ok' => false, 'msg' => $msg, 'field' => $field));
    };
    if (!petgo_shop_on()) {
        $fail($L('Магазин сейчас закрыт.'));
    }
    if (!empty($_POST['pgr_web'])) {
        wp_send_json(array('ok' => true, 'no' => ''));
    }
    if (function_exists('petgo_guard_same_origin') && !petgo_guard_same_origin()) {
        $fail($L('Обновите страницу и отправьте ещё раз.'));
    }
    if (!petgo_req_token_ok(isset($_POST['tk']) ? wp_unslash($_POST['tk']) : '')) {
        $fail($L('Обновите страницу и отправьте ещё раз.'));
    }
    $uid = function_exists('petgo_cl_uid') ? petgo_cl_uid() : 0;
    $ik  = 'petgo_shop_rq_' . md5(petgo_ip());
    $n   = (int) get_transient($ik);
    if ($n >= ($uid ? 20 : 6)) {
        $fail(petgo_site_lf('Слишком много заказов с вашего адреса. Позвоните нам: %s.', petgo_site_phone_fmt()));
    }
    $items = json_decode(isset($_POST['items']) ? wp_unslash($_POST['items']) : '', true);
    $g     = function ($k) {
        return isset($_POST[$k]) ? wp_unslash($_POST[$k]) : '';
    };
    $in = array(
        'items' => is_array($items) ? $items : array(), 'mode' => sanitize_key($g('mode')) ?: 'addr', 'order' => (int) $g('order'),
        'zone' => sanitize_key($g('zone')), 'addr' => $g('addr'), 'lat' => (float) $g('lat'), 'lng' => (float) $g('lng'),
        'name' => $g('name'), 'phone' => $g('phone'), 'note' => $g('note'), 'pay' => sanitize_key($g('pay')),
    );
    if ($in['mode'] === 'nanny' && !$uid) {
        $fail($L('Войдите в кабинет — тогда няня привезёт заказ бесплатно.'), 'mode');
    }
    $r = petgo_shop_create($uid, $in);
    if (!$r[0]) {
        $fail($r[1], $r[2]);
    }
    set_transient($ik, $n + 1, 10 * MINUTE_IN_SECONDS);
    $s = $r[1];
    /* следующий заказ — ЖК и квартира уже заполнены */
    if ($uid && $s['mode'] === 'addr') {
        if ($in['zone'] !== '') {
            update_user_meta($uid, 'petgo_shop_zone', $in['zone']);
        }
        update_user_meta($uid, 'petgo_shop_flat', mb_substr(sanitize_text_field((string) $in['addr']), 0, 120));
    }
    wp_send_json(array('ok' => true, 'no' => $s['no'], 'sum' => $s['sum'], 'fee' => $s['fee'], 'mode' => $s['mode'], 'mine' => petgo_shop_mine($uid)));
}

/* строки витрины для скрипта (переводятся словарём сайта) */
function petgo_shop_js_strings() {
    return array('Все', 'Для собак', 'Для кошек', 'В корзину', 'Нет в наличии', 'Корзина', 'Оформить', 'Назад к товарам', 'Оформление заказа', 'Товары',
        'Как получить', 'Привезёт няня', 'Бесплатно — когда придёт за питомцем', 'Доставка', 'Выберите заказ', 'Нет заказов с няней',
        'Закажите выгул или визит — няня привезёт товары бесплатно', 'Войдите в кабинет — няня привезёт заказ бесплатно, когда придёт за питомцем', 'Войти',
        'Адрес', 'Улица, дом', 'Найти на карте', 'Моё местоположение', 'Ищем адрес…', 'Адрес не найден — уточните или нажмите на карте, где вы',
        'Бесплатная доставка', 'Доставка %s', 'Бесплатно от %s — добавьте ещё на %s', 'Скоро добавим ваш район',
        'Пока доставляем только в отмеченные ЖК. Няня может привезти заказ бесплатно вместе с выгулом.', 'Отметьте на карте, где вы живёте',
        'Имя', 'Телефон', 'Комментарий', 'Например: позвонить за 10 минут', 'Оплата при получении',
        'Итого', 'Отправить заявку', 'Заявка принята', 'Номер заказа %s. Мы написали подробности в чат кабинета.', 'Номер заказа %s. Менеджер позвонит, подтвердит время и сумму.',
        'Продолжить покупки', 'Мои заказы', 'Не получилось — проверьте интернет.', 'бесплатно', 'шт', 'Убрать',
        'Новый', 'Принят', 'Доставлен', 'Отменён', 'Заказать выгул', 'Ваш ЖК', 'Моего ЖК нет в списке', 'Подъезд, этаж, квартира', 'Поиск ЖК', 'бесплатно от %s', 'доставка %s',
        'Поиск товаров…', 'Ничего не нашли', 'Популярные товары', 'Где доставляем', 'Показать на карте', 'Скрыть карту', 'Доставка и услуги', 'Только доставка', 'Няни работают',
        'Нужна помощь с питомцем?', 'Няня погуляет с собакой или навестит кошку дома', 'Хит', 'В избранное', 'Избранное', 'Сумма к оплате при получении', 'Менеджер пришлёт счёт на Kaspi');
}

/* переводы витрины: казахский и английский */
add_filter('petgo_site_dict_more', function ($d) {
    return array_merge($d, array(
        'Магазин' => array('Дүкен', 'Shop'), 'Магазин для питомцев' => array('Үй жануарларына арналған дүкен', 'Pet shop'), 'Магазин для питомцев в Алматы' => array('Алматыдағы үй жануарлары дүкені', 'Pet shop in Almaty'),
        'Зоомагазин в Алматы с доставкой: корм, игрушки, уход — PetGo' => array('Алматыдағы жеткізумен зоодүкен: жем, ойыншықтар, күтім — PetGo', 'Pet shop in Almaty with delivery: food, toys, care — PetGo'),
        'Корм, лакомства, игрушки и всё для собак и кошек с доставкой в Алматы. Бесплатно от %s, няня PetGo привезёт заказ вместе с выгулом. Оплата при получении.' => array('Алматыда иттер мен мысықтарға жем, тәттілер, ойыншықтар — жеткізумен. %s бастап тегін, PetGo күтушісі серуенмен бірге әкеледі. Алған кезде төлеу.', 'Food, treats, toys and everything for dogs and cats delivered in Almaty. Free from %s; a PetGo sitter can bring your order with a walk. Pay on delivery.'),
        'Корм, лакомства, игрушки и уход — с доставкой по ЖК Алматы. Оплата при получении.' => array('Жем, тәттілер, ойыншықтар және күтім — Алматы ТК бойынша жеткізу. Алған кезде төлеу.', 'Food, treats, toys and care — delivered to residential complexes in Almaty. Pay on delivery.'),
        'В магазин' => array('Дүкенге', 'Go to shop'), 'Все' => array('Барлығы', 'All'), 'Для собак' => array('Иттерге', 'For dogs'), 'Для кошек' => array('Мысықтарға', 'For cats'),
        'В корзину' => array('Себетке', 'Add to cart'), 'Нет в наличии' => array('Қолда жоқ', 'Out of stock'), 'Корзина' => array('Себет', 'Cart'), 'Оформить' => array('Рәсімдеу', 'Checkout'),
        'Назад к товарам' => array('Тауарларға оралу', 'Back to products'), 'Оформление заказа' => array('Тапсырысты рәсімдеу', 'Checkout'), 'Товары' => array('Тауарлар', 'Items'),
        'Как получить' => array('Қалай алу', 'Delivery'), 'Привезёт няня' => array('Күтуші әкеледі', 'The sitter brings it'), 'Бесплатно — когда придёт за питомцем' => array('Тегін — жануарды алуға келгенде', 'Free — when she comes for your pet'),
        'Доставка' => array('Жеткізу', 'Delivery'), 'Выберите заказ' => array('Тапсырысты таңдаңыз', 'Choose a booking'), 'Нет заказов с няней' => array('Күтушімен тапсырыс жоқ', 'No sitter bookings'),
        'Закажите выгул или визит — няня привезёт товары бесплатно' => array('Серуенге не келуге тапсырыс беріңіз — күтуші тауарларды тегін әкеледі', 'Book a walk or a visit — the sitter brings your items for free'),
        'Войдите в кабинет — няня привезёт заказ бесплатно, когда придёт за питомцем' => array('Кабинетке кіріңіз — күтуші тапсырысты тегін әкеледі', 'Sign in — the sitter brings your order for free when she comes for your pet'),
        'Войти' => array('Кіру', 'Sign in'), 'Адрес' => array('Мекенжай', 'Address'), 'Улица, дом' => array('Көше, үй', 'Street, house'),
        'Найти на карте' => array('Картадан табу', 'Find on map'), 'Моё местоположение' => array('Менің орным', 'My location'), 'Ищем адрес…' => array('Мекенжайды іздеп жатырмыз…', 'Looking up the address…'),
        'Адрес не найден — уточните или нажмите на карте, где вы' => array('Мекенжай табылмады — нақтылаңыз немесе картадан белгілеңіз', 'Address not found — refine it or tap your place on the map'),
        'Бесплатная доставка' => array('Тегін жеткізу', 'Free delivery'), 'Доставка %s' => array('Жеткізу %s', 'Delivery %s'),
        'Бесплатно от %s — добавьте ещё на %s' => array('%s бастап тегін — тағы %s қосыңыз', 'Free from %s — add %s more'),
        'Скоро добавим ваш район' => array('Сіздің ауданды жақында қосамыз', 'Your area is coming soon'),
        'Пока доставляем только в отмеченные ЖК. Няня может привезти заказ бесплатно вместе с выгулом.' => array('Әзірге тек белгіленген ТК-ға жеткіземіз. Күтуші тапсырысты серуенмен бірге тегін әкеле алады.', 'For now we deliver only to the marked complexes. A sitter can bring your order for free with a walk.'),
        'Отметьте на карте, где вы живёте' => array('Қай жерде тұратыныңызды картадан белгілеңіз', 'Mark where you live on the map'),
        'Комментарий' => array('Пікір', 'Comment'), 'Например: позвонить за 10 минут' => array('Мысалы: 10 минут бұрын қоңырау шалу', 'E.g. call 10 minutes before'),
        'Оплата при получении' => array('Алған кезде төлеу', 'Pay on delivery'), 'Наличными' => array('Қолма-қол', 'Cash'), 'Картой курьеру' => array('Курьерге картамен', 'Card on delivery'), 'Счёт на Kaspi' => array('Kaspi шоты', 'Kaspi invoice'),
        'Итого' => array('Барлығы', 'Total'), 'Заявка принята' => array('Өтінім қабылданды', 'Request received'),
        'Номер заказа %s. Мы написали подробности в чат кабинета.' => array('Тапсырыс нөмірі %s. Толығын кабинет чатына жаздық.', 'Order %s. Details are in your account chat.'),
        'Номер заказа %s. Менеджер позвонит, подтвердит время и сумму.' => array('Тапсырыс нөмірі %s. Менеджер қоңырау шалып, уақыты мен соманы растайды.', 'Order %s. A manager will call to confirm the time and total.'),
        'Продолжить покупки' => array('Сатып алуды жалғастыру', 'Continue shopping'), 'Мои заказы' => array('Менің тапсырыстарым', 'My orders'),
        'бесплатно' => array('тегін', 'free'), 'шт' => array('дана', 'pcs'), 'Убрать' => array('Алып тастау', 'Remove'),
        'Новый' => array('Жаңа', 'New'), 'Принят' => array('Қабылданды', 'Accepted'), 'Доставлен' => array('Жеткізілді', 'Delivered'), 'Заказать выгул' => array('Серуенге тапсырыс', 'Book a walk'),
        'Ваш ЖК' => array('Сіздің ТК', 'Your residential complex'), 'Моего ЖК нет в списке' => array('Менің ТК тізімде жоқ', 'My complex is not listed'), 'Подъезд, этаж, квартира' => array('Кіреберіс, қабат, пәтер', 'Entrance, floor, flat'),
        'Поиск ЖК' => array('ТК іздеу', 'Search complex'), 'бесплатно от %s' => array('%s бастап тегін', 'free from %s'), 'доставка %s' => array('жеткізу %s', 'delivery %s'),
        'Поиск товаров…' => array('Тауар іздеу…', 'Search products…'), 'Ничего не нашли' => array('Ештеңе табылмады', 'Nothing found'), 'Популярные товары' => array('Танымал тауарлар', 'Popular products'),
        'Где доставляем' => array('Қайда жеткіземіз', 'Where we deliver'), 'Показать на карте' => array('Картадан көрсету', 'Show on map'), 'Скрыть карту' => array('Картаны жасыру', 'Hide map'),
        'Доставка и услуги' => array('Жеткізу және қызметтер', 'Delivery and services'), 'Только доставка' => array('Тек жеткізу', 'Delivery only'), 'Няни работают' => array('Күтушілер жұмыс істейді', 'Sitters work here'),
        'Нужна помощь с питомцем?' => array('Жануарға көмек керек пе?', 'Need help with your pet?'), 'Няня погуляет с собакой или навестит кошку дома' => array('Күтуші итпен серуендейді немесе мысыққа үйге келеді', 'A sitter will walk your dog or visit your cat at home'),
        'Хит' => array('Хит', 'Top'), 'Избранное' => array('Таңдаулы', 'Favorites'), 'Сумма к оплате при получении' => array('Алған кезде төлейтін сома', 'Amount to pay on delivery'),
        'Менеджер пришлёт счёт на Kaspi' => array('Менеджер Kaspi шотын жібереді', 'A manager will send a Kaspi invoice'),
        'Всё для вашего' => array('Жануарыңызға', 'Everything for your'), 'питомца' => array('бәрі', 'pet'), 'Смотреть товары' => array('Тауарларды көру', 'View products'),
        'Корм, игрушки и уход с доставкой. Бесплатно от %s.' => array('Жем, ойыншықтар және күтім жеткізумен. %s бастап тегін.', 'Food, toys and care delivered. Free from %s.'),
        'Корм, игрушки и уход — няня привезёт вместе с выгулом.' => array('Жем, ойыншықтар және күтім — күтуші серуенмен бірге әкеледі.', 'Food, toys and care — a sitter brings them with a walk.'),
        'Корм, игрушки и уход с доставкой. Няня привезёт бесплатно вместе с выгулом.' => array('Жем, ойыншықтар және күтім жеткізумен. Күтуші серуенмен бірге тегін әкеледі.', 'Food, toys and care delivered. A sitter brings them for free with a walk.'),
        'Магазин PetGo' => array('PetGo дүкені', 'PetGo shop'), 'Всё для вашего питомца' => array('Жануарыңызға бәрі', 'Everything for your pet'), 'Перейти в магазин' => array('Дүкенге өту', 'Go to shop'),
        'Корм, игрушки и уход. Бесплатная доставка от %s' => array('Жем, ойыншықтар, күтім. %s бастап тегін жеткізу', 'Food, toys and care. Free delivery from %s'),
        'Корм, игрушки и уход — няня привезёт вместе с выгулом' => array('Жем, ойыншықтар, күтім — күтуші серуенмен бірге әкеледі', 'Food, toys and care — a sitter brings them with a walk'),
        'Магазин сейчас закрыт.' => array('Дүкен қазір жабық.', 'The shop is closed right now.'),
        'Укажите подъезд и квартиру.' => array('Кіреберіс пен пәтерді көрсетіңіз.', 'Enter the entrance and flat.'),
        'Скоро добавим ваш район. Пока доставляем только в отмеченные ЖК — или няня привезёт бесплатно вместе с выгулом.' => array('Ауданыңызды жақында қосамыз. Әзірге белгіленген ТК-ға ғана жеткіземіз — немесе күтуші серуенмен бірге тегін әкеледі.', 'Your area is coming soon. For now we deliver only to the marked complexes, or a sitter brings it for free with a walk.'),
        'Корм' => array('Жем', 'Food'), 'Лакомства' => array('Тәттілер', 'Treats'), 'Игрушки' => array('Ойыншықтар', 'Toys'), 'Уход и гигиена' => array('Күтім және гигиена', 'Care'), 'Аксессуары' => array('Аксессуарлар', 'Accessories'), 'Другое' => array('Басқа', 'Other'),
        'Отправить заявку' => array('Өтінім жіберу', 'Send request'), 'сегодня' => array('бүгін', 'today'), 'завтра' => array('ертең', 'tomorrow'),
    ));
});

/* ЖК и районы, где работают няни (отметка «Няни работают») — сайт пишет их в тексте и разметке для поиска */
add_filter('petgo_site_service_areas', function ($a) {
    foreach (petgo_shop_zones() as $z) {
        if ($z['svc'] === '1') {
            $a[] = $z['n'];
        }
    }
    return $a;
});
