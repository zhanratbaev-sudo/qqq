<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   МАГАЗИН НА САЙТЕ И В КАБИНЕТЕ КЛИЕНТА
   Витрина, корзина и оформление рисует assets/shop.js — одинаково на странице «Магазин» и в кабинете (лист «Магазин»).
   ============================================================ */

/* страница сайта /magazin/ — в верхнем меню; в нижнем меню телефона занимает место «Отзывов» */
add_filter('petgo_site_pages', function ($p) {
    $p['shop'] = array('slug' => 'magazin', 'name' => petgo_site_l('Магазин'), 'title' => petgo_site_l('Магазин для питомцев'), 'nav' => true);
    return $p;
});
add_filter('petgo_site_page_html', function ($h, $page) {
    if ($page !== 'shop') {
        return $h;
    }
    $c = petgo_shop_cfg();
    return petgo_site_b_title(petgo_site_l('Магазин для питомцев'), petgo_site_lf('Корм, лакомства и всё для питомца. Няня привезёт бесплатно, когда придёт за питомцем; доставка рядом с нами — бесплатно от %s.', petgo_shop_money($c['free'])))
        . '<section class="pg-sec pg-sec-tight"><div class="pg-wrap">' . petgo_shop_box('site') . '</div></section>';
}, 10, 2);

add_filter('petgo_site_dock_item', function ($h, $k, $page) {
    if ($k !== 'reviews' || !petgo_shop_products()) {
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

/* блок на главной (после услуг): несколько товаров и кнопка в магазин */
add_filter('petgo_site_home_sections', function ($sec) {
    $all = array_values(array_filter(petgo_shop_products(), function ($p) {
        return $p['in'];
    }));
    if (!$all) {
        return $sec;
    }
    $h = '<section class="pg-sec" id="pg-shop"><div class="pg-wrap"><div class="psh-teaser pg-rise"><div class="psh-teaser-h"><div><h2 class="pg-h">' . esc_html(petgo_site_l('Магазин для питомцев')) . '</h2><p class="pg-sub">' . esc_html(petgo_site_l('Няня привезёт заказ бесплатно, когда придёт за питомцем.')) . '</p></div><a class="pg-btn pg-btn-primary" href="' . esc_url(petgo_site_url('shop')) . '">' . esc_html(petgo_site_l('В магазин')) . '</a></div><div class="psh-teaser-row">';
    foreach (array_slice($all, 0, 4) as $p) {
        $h .= '<a class="psh-tc" href="' . esc_url(petgo_site_url('shop')) . '#p' . (int) $p['id'] . '"><span class="psh-tc-i">' . ($p['img'] ? '<img src="' . esc_url($p['img']) . '" alt="" loading="lazy" decoding="async">' : petgo_shop_icon('psh-ph')) . '</span><b>' . esc_html($p['n']) . '</b><span>' . esc_html(petgo_shop_money($p['p'])) . '</span></a>';
    }
    $sec[25] = $h . '</div></div></div></section>';
    return $sec;
});

/* ---------- кабинет клиента: карточка на главной и лист «Магазин» ---------- */
add_filter('petgo_cl_home_first', function ($h, $uid) {
    $all = array_values(array_filter(petgo_shop_products(), function ($p) {
        return $p['in'];
    }));
    if (!$all) {
        return $h;
    }
    $th = '';
    foreach (array_slice($all, 0, 3) as $p) {
        $th .= '<span class="psh-hc-i">' . ($p['img'] ? '<img src="' . esc_url($p['img']) . '" alt="" loading="lazy" decoding="async">' : petgo_shop_icon('psh-ph')) . '</span>';
    }
    return $h . '<button type="button" class="pg-b psh-hc" data-k-open="shop"><span class="psh-hc-t"><b>' . esc_html(petgo_site_l('Магазин')) . '</b><small>' . esc_html(petgo_site_l('Корм и лакомства — няня привезёт бесплатно, когда придёт за питомцем')) . '</small></span><span class="psh-hc-th">' . $th . '</span></button>';
}, 20, 2);

add_filter('petgo_cl_sheets', function ($h, $uid) {
    if (!petgo_shop_products() || !function_exists('petgo_cl_sheet')) {
        return $h;
    }
    return $h . petgo_cl_sheet('shop', petgo_site_l('Магазин'), petgo_shop_box('cabinet', $uid), '', 'pgk-sheet-tall pgk-sheet-shop');
}, 10, 2);

/* ---------- витрина: место для shop.js и данные для неё ---------- */
function petgo_shop_box($ctx, $uid = null) {
    $uid = $uid === null ? (function_exists('petgo_cl_uid') ? petgo_cl_uid() : 0) : (int) $uid;
    $cfg = petgo_shop_js_cfg($ctx, $uid);
    $ns  = '';
    foreach ($cfg['items'] as $p) {
        $ns .= '<li>' . esc_html($p['n'] . ' — ' . petgo_shop_money($p['p'])) . '</li>';
    }
    return '<div class="psh" data-psh="' . esc_attr($ctx) . '"><script type="application/json" class="psh-cfg">' . wp_json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script><noscript><ul>' . $ns . '</ul></noscript></div>';
}

function petgo_shop_me($uid) {
    if (!$uid) {
        return null;
    }
    $u  = get_userdata($uid);
    $me = array('name' => $u ? $u->display_name : '', 'phone' => function_exists('petgo_site_phone_fmt') ? petgo_site_phone_fmt((string) get_user_meta($uid, 'petgo_phone', true)) : '', 'addr' => '', 'lat' => 0, 'lng' => 0);
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
    $c     = petgo_shop_cfg();
    $cats  = petgo_shop_cats();
    $items = array();
    $used  = array();
    foreach (petgo_shop_products() as $p) {
        $items[] = $p;
        if ($p['c'] !== '' && isset($cats[$p['c']])) {
            $used[$p['c']] = petgo_site_l($cats[$p['c']]);
        }
    }
    $ll = function_exists('petgo_asset_where') && is_readable(dirname(petgo_asset_where('leaflet/leaflet.js')) . '/assets/leaflet/leaflet.js');
    $tr = array();
    foreach (petgo_shop_js_strings() as $s) {
        $tr[$s] = petgo_site_l($s);
    }
    return array(
        'ctx' => $ctx, 'items' => $items, 'cats' => $used, 'money' => '₸', 'lang' => petgo_site_lang(),
        'zone' => array('lat' => $c['lat'], 'lng' => $c['lng'], 'r' => $c['radius'], 'free' => $c['free'], 'fee' => $c['fee'], 'label' => $c['label']),
        'me' => petgo_shop_me($uid), 'nanny' => petgo_shop_nanny_orders($uid), 'mine' => petgo_shop_mine($uid),
        'aj' => function_exists('petgo_rel_url') ? petgo_rel_url(admin_url('admin-ajax.php')) : admin_url('admin-ajax.php'), 'tk' => petgo_req_token(),
        'acc' => petgo_site_url('account'), 'shop' => petgo_site_url('shop'),
        'leaflet' => $ll ? array('css' => petgo_asset_url('leaflet/leaflet.css'), 'js' => petgo_asset_url('leaflet/leaflet.js')) : null,
        'tr' => $tr,
    );
}

/* подключаем стили и скрипт магазина на страницах сайта и в кабинете */
add_action('petgo_site_head', function () {
    echo '<link rel="stylesheet" href="' . esc_url(petgo_asset_url('shop.css')) . '">';
}, 30);
add_action('petgo_site_foot', function () {
    echo '<script src="' . esc_url(petgo_asset_url('shop.js')) . '" defer></script>';
}, 30);

/* ---------- приём заказа (с сайта и из кабинета) ---------- */
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
    $in    = array(
        'items' => is_array($items) ? $items : array(), 'mode' => isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'addr',
        'order' => isset($_POST['order']) ? (int) $_POST['order'] : 0, 'addr' => isset($_POST['addr']) ? wp_unslash($_POST['addr']) : '',
        'lat' => isset($_POST['lat']) ? (float) $_POST['lat'] : 0, 'lng' => isset($_POST['lng']) ? (float) $_POST['lng'] : 0,
        'name' => isset($_POST['name']) ? wp_unslash($_POST['name']) : '', 'phone' => isset($_POST['phone']) ? wp_unslash($_POST['phone']) : '', 'note' => isset($_POST['note']) ? wp_unslash($_POST['note']) : '',
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
    wp_send_json(array('ok' => true, 'no' => $s['no'], 'sum' => $s['sum'], 'fee' => $s['fee'], 'mode' => $s['mode'], 'mine' => petgo_shop_mine($uid)));
}

/* строки витрины для скрипта (переводятся словарём сайта) */
function petgo_shop_js_strings() {
    return array('Все', 'Для собак', 'Для кошек', 'В корзину', 'Нет в наличии', 'Корзина', 'Оформить', 'Назад к товарам', 'Оформление заказа', 'Товары',
        'Как получить', 'Привезёт няня', 'Бесплатно — когда придёт за питомцем', 'Доставка по адресу', 'Выберите заказ', 'Нет заказов с няней',
        'Закажите выгул или визит — няня привезёт товары бесплатно', 'Войдите в кабинет — няня привезёт заказ бесплатно, когда придёт за питомцем', 'Войти',
        'Адрес', 'Улица, дом, квартира', 'Найти на карте', 'Моё местоположение', 'Ищем адрес…', 'Адрес не найден — уточните или нажмите на карте, где вы',
        'Бесплатная доставка', 'Доставка %s', 'Бесплатно от %s — добавьте ещё на %s', 'Скоро добавим ваш район',
        'Пока доставляем в радиусе %s км от %s. Няня может привезти заказ бесплатно вместе с выгулом.', 'Укажите адрес — проверим, доставляем ли мы к вам',
        'Имя', 'Телефон', 'Комментарий', 'Например: позвонить за 10 минут', 'Оплата при получении — наличными или Kaspi',
        'Доставка', 'Итого', 'Заказать', 'Заказ принят', 'Номер заказа %s. Мы написали подробности в чат кабинета.', 'Номер заказа %s. Менеджер позвонит и скажет время доставки.',
        'Продолжить покупки', 'Мои заказы', 'Не получилось — проверьте интернет.', 'бесплатно', 'шт', 'Убрать', 'Нажмите на карте, где вас найти',
        'Новый', 'Принят', 'Доставлен', 'Отменён', 'Заказать выгул', 'Зона доставки');
}

/* переводы витрины: казахский и английский */
add_filter('petgo_site_dict_more', function ($d) {
    return array_merge($d, array(
        'Магазин' => array('Дүкен', 'Shop'), 'Магазин для питомцев' => array('Үй жануарларына арналған дүкен', 'Pet shop'),
        'В магазин' => array('Дүкенге', 'Go to shop'), 'Все' => array('Барлығы', 'All'), 'Для собак' => array('Иттерге', 'For dogs'), 'Для кошек' => array('Мысықтарға', 'For cats'),
        'В корзину' => array('Себетке', 'Add to cart'), 'Нет в наличии' => array('Қолда жоқ', 'Out of stock'), 'Корзина' => array('Себет', 'Cart'), 'Оформить' => array('Рәсімдеу', 'Checkout'),
        'Назад к товарам' => array('Тауарларға оралу', 'Back to products'), 'Оформление заказа' => array('Тапсырысты рәсімдеу', 'Checkout'), 'Товары' => array('Тауарлар', 'Items'),
        'Как получить' => array('Қалай алу', 'Delivery'), 'Привезёт няня' => array('Күтуші әкеледі', 'The sitter brings it'), 'Бесплатно — когда придёт за питомцем' => array('Тегін — жануарды алуға келгенде', 'Free — when she comes for your pet'),
        'Доставка по адресу' => array('Мекенжайға жеткізу', 'Delivery to address'), 'Выберите заказ' => array('Тапсырысты таңдаңыз', 'Choose a booking'), 'Нет заказов с няней' => array('Күтушімен тапсырыс жоқ', 'No sitter bookings'),
        'Закажите выгул или визит — няня привезёт товары бесплатно' => array('Серуенге не келуге тапсырыс беріңіз — күтуші тауарларды тегін әкеледі', 'Book a walk or a visit — the sitter brings your items for free'),
        'Войдите в кабинет — няня привезёт заказ бесплатно, когда придёт за питомцем' => array('Кабинетке кіріңіз — күтуші тапсырысты тегін әкеледі', 'Sign in — the sitter brings your order for free when she comes for your pet'),
        'Войти' => array('Кіру', 'Sign in'), 'Адрес' => array('Мекенжай', 'Address'), 'Улица, дом, квартира' => array('Көше, үй, пәтер', 'Street, house, flat'),
        'Найти на карте' => array('Картадан табу', 'Find on map'), 'Моё местоположение' => array('Менің орным', 'My location'), 'Ищем адрес…' => array('Мекенжайды іздеп жатырмыз…', 'Looking up the address…'),
        'Адрес не найден — уточните или нажмите на карте, где вы' => array('Мекенжай табылмады — нақтылаңыз немесе картадан белгілеңіз', 'Address not found — refine it or tap your place on the map'),
        'Бесплатная доставка' => array('Тегін жеткізу', 'Free delivery'), 'Доставка %s' => array('Жеткізу %s', 'Delivery %s'),
        'Бесплатно от %s — добавьте ещё на %s' => array('%s бастап тегін — тағы %s қосыңыз', 'Free from %s — add %s more'),
        'Скоро добавим ваш район' => array('Сіздің ауданды жақында қосамыз', 'Your area is coming soon'),
        'Пока доставляем в радиусе %s км от %s. Няня может привезти заказ бесплатно вместе с выгулом.' => array('Әзірге %2$s бастап %1$s км радиуста жеткіземіз. Күтуші тапсырысты серуенмен бірге тегін әкеле алады.', 'For now we deliver within %s km of %s. The sitter can bring your order for free with a walk.'),
        'Укажите адрес — проверим, доставляем ли мы к вам' => array('Мекенжайды көрсетіңіз — жеткізетінімізді тексереміз', 'Enter your address — we will check if we deliver there'),
        'Комментарий' => array('Пікір', 'Comment'), 'Например: позвонить за 10 минут' => array('Мысалы: 10 минут бұрын қоңырау шалу', 'E.g. call 10 minutes before'),
        'Оплата при получении — наличными или Kaspi' => array('Алған кезде төлеу — қолма-қол немесе Kaspi', 'Pay on delivery — cash or Kaspi'),
        'Доставка' => array('Жеткізу', 'Delivery'), 'Итого' => array('Барлығы', 'Total'), 'Заказать' => array('Тапсырыс беру', 'Order'), 'Заказ принят' => array('Тапсырыс қабылданды', 'Order placed'),
        'Номер заказа %s. Мы написали подробности в чат кабинета.' => array('Тапсырыс нөмірі %s. Толығын кабинет чатына жаздық.', 'Order %s. Details are in your account chat.'),
        'Номер заказа %s. Менеджер позвонит и скажет время доставки.' => array('Тапсырыс нөмірі %s. Менеджер қоңырау шалып, уақытын айтады.', 'Order %s. A manager will call you with the delivery time.'),
        'Продолжить покупки' => array('Сатып алуды жалғастыру', 'Continue shopping'), 'Мои заказы' => array('Менің тапсырыстарым', 'My orders'),
        'бесплатно' => array('тегін', 'free'), 'шт' => array('дана', 'pcs'), 'Убрать' => array('Алып тастау', 'Remove'), 'Нажмите на карте, где вас найти' => array('Сізді қайдан табуға болатынын картадан басыңыз', 'Tap the map where we can find you'),
        'Новый' => array('Жаңа', 'New'), 'Принят' => array('Қабылданды', 'Accepted'), 'Доставлен' => array('Жеткізілді', 'Delivered'), 'Заказать выгул' => array('Серуенге тапсырыс', 'Book a walk'), 'Зона доставки' => array('Жеткізу аймағы', 'Delivery area'),
        'Корм' => array('Жем', 'Food'), 'Лакомства' => array('Тәттілер', 'Treats'), 'Игрушки' => array('Ойыншықтар', 'Toys'), 'Уход и гигиена' => array('Күтім және гигиена', 'Care'), 'Аксессуары' => array('Аксессуарлар', 'Accessories'), 'Другое' => array('Басқа', 'Other'),
        'Няня привезёт заказ бесплатно, когда придёт за питомцем.' => array('Күтуші жануарды алуға келгенде тапсырысты тегін әкеледі.', 'The sitter brings your order for free when she comes for your pet.'),
        'Корм и лакомства — няня привезёт бесплатно, когда придёт за питомцем' => array('Жем мен тәттілер — күтуші тегін әкеледі', 'Food and treats — the sitter brings them for free'),
        'Корм, лакомства и всё для питомца. Няня привезёт бесплатно, когда придёт за питомцем; доставка рядом с нами — бесплатно от %s.' => array('Жем, тәттілер және жануарға қажеттің бәрі. Күтуші тегін әкеледі; жақын жерге жеткізу %s бастап тегін.', 'Food, treats and everything for your pet. The sitter brings it for free; delivery nearby is free from %s.'),
        'сегодня' => array('бүгін', 'today'), 'завтра' => array('ертең', 'tomorrow'),
    ));
});
