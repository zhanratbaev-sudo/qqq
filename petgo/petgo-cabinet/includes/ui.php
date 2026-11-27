<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ВНЕШНИЙ ВИД 27.0 (iOS 27 Liquid Glass): лёгкая страница-приложение, меню-таблетка, главная, настройки, вход
   ============================================================ */
/* 34.3: файлы оформления лежат в своих плагинах (база, «PetGo Сайт», «PetGo Клиент»):
   плагин сообщает свою папку через petgo_asset_root(), адрес файла находится сам */
function petgo_asset_root($file = null) {
    static $roots = array();
    if ($file !== null) {
        $roots[] = $file;
    }
    return array_merge($roots, array(PETGO_FILE));
}

function petgo_asset_where($f) {
    foreach (petgo_asset_root() as $main) {
        if (is_readable(dirname($main) . '/assets/' . $f)) {
            return $main;
        }
    }
    return PETGO_FILE;
}

function petgo_asset_ver($f) {
    $p = dirname(petgo_asset_where($f)) . '/assets/' . $f;
    return PETGO_VER . '.' . (is_readable($p) ? (int) filemtime($p) : 0);
}

function petgo_asset_url($f) {
    return plugins_url('assets/' . $f, petgo_asset_where($f)) . '?ver=' . petgo_asset_ver($f);
}

/* стили, значок, манифест, тема до отрисовки (без мигания), скрипт приложения */
function petgo_head_tags() {
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    $h  = '<link rel="stylesheet" href="' . esc_url(petgo_asset_url('app.css')) . '">';
    $h .= '<link rel="manifest" href="' . esc_url(petgo_page_url(array('petgo_manifest' => 1))) . '">';
    $h .= '<meta name="theme-color" content="#f2f2f7">';
    $h .= '<meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="' . esc_attr(petgo_app_name()) . '"><meta name="apple-mobile-web-app-status-bar-style" content="default"><meta name="format-detection" content="telephone=no">';
    $h .= '<link rel="apple-touch-icon" href="' . esc_url(petgo_asset('apple-touch-icon.png')) . '"><link rel="icon" href="' . esc_url(petgo_asset('icon-192.png')) . '">';
    $h .= '<script>(function(){var t="auto";try{t=localStorage.getItem("pgc_theme")||"auto"}catch(e){}var d=t==="dark"||(t!=="light"&&window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches);document.documentElement.classList.toggle("dark",d);var m=document.querySelector(\'meta[name="theme-color"]\');if(m)m.content=d?"#000000":"#f2f2f7";var g=null;try{g=localStorage.getItem("pgc_glass")}catch(e){}if(g!==null)document.documentElement.style.setProperty("--gu",Math.max(0,Math.min(100,+g||0))/100)})();</script>';
    $h .= '<script src="' . esc_url(petgo_asset_url('kb.js')) . '" defer></script>';
    $h .= '<script src="' . esc_url(petgo_asset_url('app.js')) . '" defer></script>';
    /* 34.0: трансляция прогулки, «Скопировать / Поделиться» (см. includes/track.php) */
    return (string) apply_filters('petgo_head_extra', $h);
}

function petgo_svg($d, $cls = 'pgc-ic') {
    return '<svg class="' . esc_attr($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

function petgo_icon($n) {
    static $P = array(
        'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
        'bag'      => '<path d="M5 8h14l-1 12.2a1 1 0 0 1-1 .8H7a1 1 0 0 1-1-.8L5 8Z"/><path d="M9 10V7a3 3 0 0 1 6 0v3"/>',
        'orders'   => '<rect x="4" y="3" width="16" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'cal'      => '<rect x="3" y="4.5" width="18" height="16.5" rx="3"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/><path d="M7.5 13.5h2M11 13.5h2M14.5 13.5h2M7.5 17h2M11 17h2"/>',
        'requests' => '<path d="M4 4h16v12H8l-4 4z"/><path d="M8 9h8M8 12.5h5"/>',
        'pets'     => '<circle cx="6" cy="11" r="1.8"/><circle cx="10" cy="6.5" r="1.8"/><circle cx="14.5" cy="6.5" r="1.8"/><circle cx="18.5" cy="11" r="1.8"/><path d="M12.2 12c-3 0-5.2 3-5.2 5 0 1.8 1.8 2.6 5.2 2.6s5.2-.8 5.2-2.6c0-2-2.2-5-5.2-5z"/>',
        'staff'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-6 6.5-6s6.5 2.5 6.5 6"/><circle cx="17.5" cy="9" r="2.5"/><path d="M17.5 14c2.5 0 4 1.8 4 4.5"/>',
        'form'     => '<rect x="4" y="3" width="16" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'reviews'  => '<path d="M12 3.2l2.6 5.4 5.9.8-4.3 4.1 1 5.9L12 16.6 6.8 19.4l1-5.9L3.5 9.4l5.9-.8z"/>',
        'log'      => '<path d="M12 8v4l3 2"/><path d="M3.5 12a8.5 8.5 0 1 0 2.5-6"/><path d="M3 4v4h4"/>',
        'cur'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'done'     => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/>',
        'chat'     => '<path d="M21 11.5a8.5 8.5 0 0 1-12.3 7.6L3.5 20.5l1.4-4.8A8.5 8.5 0 1 1 21 11.5z"/>',
        'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3 1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8 1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'bell'     => '<path d="M6 9a6 6 0 0 1 12 0c0 6 2 7.5 2 7.5H4S6 15 6 9"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.7 3.8 5.7 3.8 9s-1.2 6.3-3.8 9c-2.6-2.7-3.8-5.7-3.8-9S9.4 5.7 12 3z"/>',
        'chev'     => '<path d="m6 9 6 6 6-6"/>',
        'right'    => '<path d="m9 6 6 6-6 6"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'wp'       => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'download' => '<path d="M12 4v11M7 11l5 5 5-5M5 20h14"/>',
        'idcard'   => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><circle cx="9" cy="11" r="2.2"/><path d="M5.8 16c.6-1.6 1.8-2.4 3.2-2.4s2.6.8 3.2 2.4M14.5 10h4M14.5 13.5h3"/>',
        'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'wa'       => '<path d="M21 11.5a8.5 8.5 0 0 1-12.3 7.6L3.5 20.5l1.4-4.8A8.5 8.5 0 1 1 21 11.5z"/>',
        'more'     => '<circle cx="12" cy="5" r="1.4" fill="currentColor"/><circle cx="12" cy="12" r="1.4" fill="currentColor"/><circle cx="12" cy="19" r="1.4" fill="currentColor"/>',
        'theme'    => '<circle cx="12" cy="12" r="9"/><path d="M12 3v18a9 9 0 0 0 0-18z" fill="currentColor"/>',
        'install'  => '<rect x="6" y="2.5" width="12" height="19" rx="3"/><path d="M12 7.5v7M9 12l3 3 3-3"/>',
        'shield'   => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
        'paw'      => '<circle cx="6" cy="11" r="1.8"/><circle cx="10" cy="6.5" r="1.8"/><circle cx="14.5" cy="6.5" r="1.8"/><circle cx="18.5" cy="11" r="1.8"/><path d="M12.2 12c-3 0-5.2 3-5.2 5 0 1.8 1.8 2.6 5.2 2.6s5.2-.8 5.2-2.6c0-2-2.2-5-5.2-5z"/>',
        'check'    => '<path d="M5 12.5l4.5 4.5L19 7"/>',
        'trash'    => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'undo'     => '<path d="M4 9h11a5 5 0 0 1 0 10H8M4 9l4-4M4 9l4 4"/>',
        'glass'    => '<rect x="3" y="7" width="14" height="14" rx="4"/><path d="M8 3h9a4 4 0 0 1 4 4v9"/><path d="M7 11.5c1-1 2.2-1.5 3.5-1.5"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    );
    /* 36.2: значки других плагинов (например, «PetGo Магазин» — сумка) */
    return isset($P[$n]) ? petgo_svg($P[$n]) : (string) apply_filters('petgo_icon_svg', '', $n);
}

function petgo_greeting() {
    $h = (int) wp_date('G', time(), petgo_tz());
    $k = $h < 5 ? 'g_night' : ($h < 12 ? 'g_morning' : ($h < 18 ? 'g_day' : 'g_evening'));
    return petgo_t($k);
}

function petgo_cur_pg() {
    return isset($_GET['pg']) ? sanitize_key(wp_unslash($_GET['pg'])) : '';
}

/* пункты меню: m = в таблетке на телефоне, d = только в левом меню на ПК (на телефоне — в меню профиля) */
function petgo_nav_data($adm) {
    $items = array();
    if ($adm) {
        $items['home']     = array(petgo_t('nav_home'), petgo_page_url(array('pg' => 'home')), 'home', 'm');
        $items['orders']   = array(petgo_t('orders'), petgo_page_url(array('pg' => 'orders')), 'orders', 'm');
        $items['cal']      = array(petgo_t('nav_cal'), petgo_page_url(array('pg' => 'cal')), 'cal', 'm');
        $items['requests'] = array(petgo_t('requests'), petgo_page_url(array('pg' => 'requests')), 'requests', 'm');
        $items['pets']     = array(petgo_t('pets'), petgo_page_url(array('pg' => 'pets')), 'pets', 'm');
        $items['reviews']  = array(petgo_t('nav_reviews'), petgo_page_url(array('pg' => 'reviews')), 'reviews', 'd');
        if (petgo_can('admin')) {
            $items['staff'] = array(petgo_t('staff'), petgo_page_url(array('pg' => 'staff')), 'staff', 'd');
            if (function_exists('petgo_page_site')) {
                $items['site'] = array(petgo_t('nav_site'), petgo_page_url(array('pg' => 'site')), 'globe', 'd');
            }
            $items['log']   = array(petgo_t('nav_log'), petgo_page_url(array('pg' => 'log')), 'log', 'x');
        }
        /* разделы от других плагинов (например, «PetGo ИИ» → «Чаты с сайта») */
        $items = (array) apply_filters('petgo_nav_items', $items, $adm);
        $pg  = petgo_cur_pg();
        $cur = isset($items[$pg]) ? $pg : 'home';
    } else {
        $items['cur']  = array(petgo_t('cur_orders'), petgo_page_url(array('pg' => 'orders', 'st' => 'cur')), 'cur', 'm');
        $items['done'] = array(petgo_t('fl_done'), petgo_page_url(array('pg' => 'orders', 'st' => 'done')), 'done', 'm');
        $cur = (isset($_GET['st']) && $_GET['st'] === 'done') ? 'done' : 'cur';
    }
    return array($items, $cur);
}

function petgo_nav_badge($k) {
    if ($k === 'requests' && petgo_req_new_count() > 0) {
        return '<i class="pgc-bd">' . (int) petgo_req_new_count() . '</i>';
    }
    if ($k === 'reviews' && petgo_rv_pending() > 0) {
        return '<i class="pgc-bd">' . (int) petgo_rv_pending() . '</i>';
    }
    $n = (int) apply_filters('petgo_nav_badge', 0, $k);
    return $n > 0 ? '<i class="pgc-bd">' . $n . '</i>' : '';
}

/* меню-таблетка внизу на телефоне и левое меню на ПК (одна разметка) */
function petgo_bottom_nav($adm) {
    list($items, $cur) = petgo_nav_data($adm);
    $un = petgo_unread_total();
    echo '<nav class="pgc-nav" id="pgc-nav"><div class="pgc-brand desk"><img src="' . esc_url(petgo_asset('icon-192.png')) . '" alt="" width="32" height="32"><span>' . esc_html(petgo_app_name()) . '</span></div><div class="pgc-navi"><span class="pgc-ind" aria-hidden="true"></span>';
    foreach ($items as $k => $it) {
        if ($it[3] === 'x') {
            continue;
        }
        echo '<a class="' . ($cur === $k ? 'on' : '') . ($it[3] === 'd' ? ' desk' : '') . '" href="' . esc_url($it[1]) . '" data-pg="' . esc_attr($k) . '" data-t="' . esc_attr($it[0]) . '">' . petgo_icon($it[2]) . '<span>' . esc_html($it[0]) . '</span>' . petgo_nav_badge($k) . '</a>';
    }
    echo '</div><div class="pgc-navx desk"><button type="button" class="pgc-navb" data-chat>' . petgo_icon('chat') . '<span>' . esc_html(petgo_t('chat')) . '</span><i class="pgc-bd pgc-cbd"' . ($un ? '' : ' hidden') . '>' . (int) $un . '</i></button>';
    echo '<button type="button" class="pgc-navb" data-open="pgc-m-set">' . petgo_icon('gear') . '<span>' . esc_html(petgo_t('settings')) . '</span></button></div></nav>';
}

function petgo_page_title($adm) {
    list($items, $cur) = petgo_nav_data($adm);
    $pg = petgo_cur_pg();
    /* 35.0: заголовок страницы плагина, которой нет в меню (например, «Договор») */
    $t = (string) apply_filters('petgo_app_title', '', $pg);
    if ($t !== '') {
        return $t;
    }
    if ($adm && isset($items[$pg])) {
        return $items[$pg][0];
    }
    return $items[$cur][0];
}

function petgo_topbar($adm) {
    $u  = wp_get_current_user();
    $un = petgo_unread_total();
    list($items) = petgo_nav_data($adm);
    echo '<header class="pgc-top"><div class="pgc-topl"><span class="pgc-hello">' . esc_html(petgo_greeting() . ', ' . $u->display_name) . '</span><h1 class="pgc-ttl" id="pgc-ttl">' . esc_html(petgo_page_title($adm)) . '</h1></div><div class="pgc-topr">';
    if ($adm) {
        echo '<button type="button" class="pgc-btn pgc-create desk" data-menu="pgc-fabm">' . petgo_icon('plus') . '<span>' . esc_html(petgo_t('create')) . '</span></button>';
    }
    /* 40.0: колокольчик — лента «Уведомления» (новые клиенты, заказы, отзывы, договоры…) */
    echo function_exists('petgo_feed_bell') ? petgo_feed_bell() : '';
    echo '<button type="button" class="pgc-iconb mob" data-chat aria-label="' . esc_attr(petgo_t('chat')) . '">' . petgo_icon('chat') . '<i class="pgc-bd pgc-cbd"' . ($un ? '' : ' hidden') . '>' . (int) $un . '</i></button>';
    echo '<div class="pgc-me"><button type="button" class="pgc-meb" data-menu="pgc-menu" aria-label="' . esc_attr(petgo_t('profile')) . '">' . petgo_av(petgo_user_avatar_url($u->ID), $u->display_name) . '<span class="pgc-mn desk">' . esc_html($u->display_name) . '</span></button>';
    echo '<div class="pgc-menu" id="pgc-menu"><div class="pgc-mt">' . petgo_av(petgo_user_avatar_url($u->ID), $u->display_name, 'md') . '<div><b>' . esc_html($u->display_name) . '</b><small>' . esc_html(petgo_t('role_' . petgo_role_of())) . '</small></div></div>';
    echo '<button type="button" class="pgc-mi" data-open="pgc-m-prof">' . petgo_icon('user') . esc_html(petgo_t('profile')) . '</button>';
    echo function_exists('petgo_badge_menu_item') ? petgo_badge_menu_item() : '';
    echo '<button type="button" class="pgc-mi" data-open="pgc-m-set">' . petgo_icon('gear') . esc_html(petgo_t('settings')) . '</button>';
    $more = '';
    foreach ($items as $k => $it) {
        if ($it[3] === 'd') {
            $more .= '<a class="pgc-mi mob" href="' . esc_url($it[1]) . '">' . petgo_icon($it[2]) . esc_html($it[0]) . petgo_nav_badge($k) . '</a>';
        }
    }
    if ($more !== '') {
        echo '<div class="pgc-msep mob"></div>' . $more;
    }
    echo '<div class="pgc-msep"></div>';
    echo '<a class="pgc-mi red" href="' . esc_url(wp_logout_url(petgo_page_url())) . '" data-logout>' . petgo_icon('logout') . esc_html(petgo_t('logout')) . '</a></div></div></div></header>';
}

/* ---------- плитка показателя ---------- */
function petgo_stat_tile($label, $value, $sub = '', $cls = '', $href = '', $icon = '') {
    $t = ($href ? 'a' : 'div');
    return '<' . $t . ' class="pgc-stat ' . esc_attr($cls) . '"' . ($href ? ' href="' . esc_url($href) . '"' : '') . '>' . ($icon ? '<span class="pgc-sti">' . petgo_icon($icon) . '</span>' : '') . '<span class="k">' . esc_html($label) . '</span><span class="v">' . esc_html($value) . '</span>' . ($sub !== '' ? '<span class="s">' . esc_html($sub) . '</span>' : '') . '</' . $t . '>';
}

function petgo_day_short() {
    $D = array(
        'ru' => array('Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'),
        'kk' => array('Жс', 'Дс', 'Сс', 'Ср', 'Бс', 'Жм', 'Сб'),
        'en' => array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'),
        'zh' => array('日', '一', '二', '三', '四', '五', '六'),
    );
    return $D[petgo_lang()];
}

function petgo_months() {
    $M = array(
        'ru' => array(array('Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'), array('января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря')),
        'kk' => array(array('Қаңтар', 'Ақпан', 'Наурыз', 'Сәуір', 'Мамыр', 'Маусым', 'Шілде', 'Тамыз', 'Қыркүйек', 'Қазан', 'Қараша', 'Желтоқсан'), array('қаңтар', 'ақпан', 'наурыз', 'сәуір', 'мамыр', 'маусым', 'шілде', 'тамыз', 'қыркүйек', 'қазан', 'қараша', 'желтоқсан')),
        'en' => array(array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'), array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec')),
        'zh' => array(array('一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'), array('1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月')),
    );
    return $M[petgo_lang()];
}

/* ---------- главная страница админа и менеджера ---------- */
function petgo_admin_home() {
    global $wpdb;
    $d = new DateTime('now', petgo_tz());
    $d->setTime(0, 0, 0);
    $t0    = $d->getTimestamp();
    $today = 0;
    foreach (petgo_orders(array('numberposts' => -1, 'meta_query' => array(array('key' => '_ts', 'value' => array($t0, $t0 + DAY_IN_SECONDS - 1), 'compare' => 'BETWEEN', 'type' => 'NUMERIC')))) as $o) {
        if (petgo_o($o->ID, 'status') !== 'cancelled') {
            $today++;
        }
    }
    $by = array();
    foreach ((array) $wpdb->get_results("SELECT m.meta_value st, COUNT(*) n FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE m.meta_key='_status' AND p.post_type='petgo_order' AND p.post_status='publish' GROUP BY m.meta_value") as $x) {
        $by[$x->st] = (int) $x->n;
    }
    $g  = function ($k) use ($by) {
        return isset($by[$k]) ? (int) $by[$k] : 0;
    };
    $on = $g('arrived') + $g('picked');
    $rq = petgo_req_new_count();
    $ou = petgo_page_url(array('pg' => 'orders'));
    /* 35.0.1: важные напоминания от плагинов (например, «Договор выключен — заполните реквизиты») */
    echo (string) apply_filters('petgo_admin_home_top', '');
    echo '<div class="pgc-stats">';
    echo petgo_stat_tile(petgo_t('st_today'), (string) $today, petgo_t('st_inprog') . ': ' . $on, 'pri wide', petgo_page_url(array('pg' => 'cal')), 'cal');
    echo petgo_stat_tile(petgo_t('st_unassigned'), (string) $g('new'), '', $g('new') ? 'warn' : '', $ou, 'user');
    echo petgo_stat_tile(petgo_t('st_toconfirm'), (string) $g('done'), '', $g('done') ? 'warn' : '', $ou, 'check');
    echo petgo_stat_tile(petgo_t('st_newreq'), (string) $rq, '', $rq ? 'hot' : '', petgo_page_url(array('pg' => 'requests')), 'requests');
    echo petgo_stat_tile(petgo_t('pets'), (string) count(petgo_pets()), '', '', petgo_page_url(array('pg' => 'pets')), 'paw');
    echo '</div>';

    $rows = $wpdb->get_results('SELECT id,name,phone,species,ts FROM ' . petgo_tb('reqs') . " WHERE status='new' ORDER BY id DESC LIMIT 4");
    if ($rows) {
        echo '<section class="pgc-panel"><div class="pgc-ph2"><h3>' . esc_html(petgo_t('st_newreq')) . '</h3><a href="' . esc_url(petgo_page_url(array('pg' => 'requests'))) . '">' . esc_html(petgo_t('all')) . '</a></div>';
        foreach ((array) $rows as $r) {
            echo '<a class="pgc-mini" href="' . esc_url(petgo_page_url(array('pg' => 'requests'))) . '">' . petgo_av('', $r->name, 'md') . '<span class="pgc-sm"><b>' . esc_html($r->name) . '</b><span class="mu">' . esc_html(petgo_phone_fmt($r->phone)) . '</span></span><span class="mu pgc-ago">' . esc_html(petgo_ago((int) $r->ts)) . '</span></a>';
        }
        echo '</section>';
    }

    echo '<div class="pgc-ph2 pgc-full"><h3>' . esc_html(petgo_t('st_next')) . '</h3><a href="' . esc_url($ou) . '">' . esc_html(petgo_t('all')) . '</a></div>';
    $list = petgo_orders(array('numberposts' => 300, 'meta_query' => array(array('key' => '_status', 'value' => array('confirmed', 'cancelled', 'done'), 'compare' => 'NOT IN'))));
    usort($list, 'petgo_cmp_ts_asc');
    $list = array_slice($list, 0, 6);
    if (!$list) {
        echo petgo_empty('orders', petgo_t('no_orders'));
    }
    petgo_prime($list);
    foreach ($list as $o) {
        echo petgo_order_card($o, true);
    }
}

/* «5 мин назад», «2 ч назад», «вчера», дата */
function petgo_ago($ts) {
    $d = time() - (int) $ts;
    if ($d < 60) {
        return petgo_t('ago_now');
    }
    if ($d < 3600) {
        return (int) floor($d / 60) . ' ' . petgo_t('m') . ' ' . petgo_t('ago');
    }
    if ($d < 86400) {
        return (int) floor($d / 3600) . ' ' . petgo_t('h') . ' ' . petgo_t('ago');
    }
    return petgo_day_label($ts) . ', ' . wp_date('H:i', (int) $ts, petgo_tz());
}

/* +77011234567 -> +7 701 123 45 67 */
function petgo_phone_fmt($p) {
    $d = preg_replace('/\D/', '', (string) $p);
    if (strlen($d) === 11 && ($d[0] === '7' || $d[0] === '8')) {
        return '+7 ' . substr($d, 1, 3) . ' ' . substr($d, 4, 3) . ' ' . substr($d, 7, 2) . ' ' . substr($d, 9, 2);
    }
    return (string) $p;
}

/* пустой экран: значок и подпись по центру */
function petgo_empty($icon, $text) {
    return '<div class="pgc-emptyst">' . petgo_icon($icon) . '<p>' . esc_html($text) . '</p></div>';
}

/* плитки сверху у няни: одна широкая сверху, две под ней */
function petgo_nanny_tiles($act, $done_n) {
    $d = new DateTime('now', petgo_tz());
    $d->setTime(0, 0, 0);
    $t0    = $d->getTimestamp();
    $today = 0;
    foreach ($act as $o) {
        $ts = (int) petgo_o($o->ID, 'ts');
        if ($ts >= $t0 && $ts < $t0 + DAY_IN_SECONDS) {
            $today++;
        }
    }
    echo '<div class="pgc-stats three">' . petgo_stat_tile(petgo_t('st_today'), (string) $today, '', 'pri wide', '', 'cal') . petgo_stat_tile(petgo_t('active_cnt'), (string) count($act), '', '', petgo_page_url(array('pg' => 'orders', 'st' => 'cur')), 'cur') . petgo_stat_tile(petgo_t('fl_done'), (string) $done_n, '', '', petgo_page_url(array('pg' => 'orders', 'st' => 'done')), 'done') . '</div>';
}

/* ---------- профиль и настройки ---------- */
/* профиль в стиле iOS 27: большое фото, имя и роль, поля списком (подпись слева, значение справа) */
function petgo_lrow($name, $label, $type, $val = '', $extra = '') {
    return '<label class="pgc-lrow"><span>' . esc_html($label) . '</span><input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '"' . $extra . '></label>';
}

function petgo_profile_modal() {
    $u = wp_get_current_user();
    $b = petgo_form_open('profile_save', 'enctype="multipart/form-data" class="pgc-prof"') . '<label class="pgc-avpick">' . petgo_av(petgo_user_avatar_url($u->ID), $u->display_name, 'xl') . '<span>' . esc_html(petgo_t('av_change')) . '</span><input class="pgc-vh" type="file" name="avatar" accept="image/*" data-preview></label>';
    $b .= '<div class="pgc-prn"><b>' . esc_html($u->display_name) . '</b><small>' . esc_html(petgo_t('role_' . petgo_role_of())) . ' · ' . esc_html($u->user_login) . '</small></div>';
    $b .= '<div class="pgc-list">' . petgo_lrow('name', petgo_t('name'), 'text', $u->display_name, ' required autocomplete="name"') . (petgo_is_owner() && function_exists('petgo_staff_no') ? petgo_lrow('staff_no', 'ID сотрудника', 'text', petgo_staff_no($u->ID), ' autocapitalize="characters" maxlength="20"') : '') . petgo_lrow('phone', petgo_t('phone'), 'tel', (string) get_user_meta($u->ID, 'petgo_phone', true), ' autocomplete="tel"') . petgo_lrow('email', petgo_t('email'), 'email', petgo_real_email($u), ' autocomplete="email" autocapitalize="none"') . '</div>';
    $b .= '<button class="pgc-btn wide lg" type="submit">' . esc_html(petgo_t('save')) . '</button></form>';
    echo petgo_modal('pgc-m-prof', petgo_t('profile'), $b);
}

function petgo_set_row($icon, $title, $sub, $ctl, $attrs = '') {
    return '<div class="pgc-srow"' . $attrs . '><span class="pgc-sri ic-' . esc_attr($icon) . '">' . petgo_icon($icon) . '</span><span class="pgc-srt"><b>' . esc_html($title) . '</b>' . ($sub !== '' ? '<small>' . $sub . '</small>' : '') . '</span>' . $ctl . '</div>';
}

function petgo_settings_modal() {
    $cur = petgo_lang();
    $s   = '<div class="pgc-sgrp"><h4>' . esc_html(petgo_t('set_look')) . '</h4><div class="pgc-sgl">';
    $seg = '<div class="pgc-seg" id="pgc-theme-seg">';
    foreach (array('auto' => 'th_auto', 'light' => 'th_light', 'dark' => 'th_dark') as $k => $l) {
        $seg .= '<button type="button" data-theme="' . $k . '">' . esc_html(petgo_t($l)) . '</button>';
    }
    $seg .= '</div>';
    $s  .= petgo_set_row('theme', petgo_t('set_theme'), '', '', ' data-col') . $seg;
    /* iOS 27: ползунок «тонированное ↔ прозрачное» для стеклянных панелей (хранится на устройстве) */
    $s  .= petgo_set_row('glass', petgo_t('set_glass'), '', '', ' data-col') . '<div class="pgc-glr"><small>' . esc_html(petgo_t('gl_tinted')) . '</small><input type="range" id="pgc-glass" min="0" max="100" step="5" value="50" aria-label="' . esc_attr(petgo_t('set_glass')) . '"><small>' . esc_html(petgo_t('gl_clear')) . '</small></div>';
    $sel = petgo_form_open('lang_set', 'class="pgc-lgf"') . '<select name="lang" data-autosubmit aria-label="' . esc_attr(petgo_t('language')) . '">';
    foreach (petgo_langs() as $c => $l) {
        $sel .= '<option value="' . esc_attr($c) . '"' . selected($cur, $c, false) . '>' . esc_html($l) . '</option>';
    }
    $sel .= '</select></form>';
    $s  .= petgo_set_row('globe', petgo_t('language'), '', $sel) . '</div></div>';

    $s .= '<div class="pgc-sgrp"><h4>' . esc_html(petgo_t('set_notif')) . '</h4><div class="pgc-sgl">';
    $s .= petgo_set_row('bell', petgo_t('set_push'), '<span id="pgc-push-st">' . esc_html(petgo_t('push_checking')) . '</span>', '<button type="button" class="pgc-sw" id="pgc-push-tg" role="switch" aria-checked="false" aria-label="' . esc_attr(petgo_t('set_push')) . '"><i></i></button>');
    $s .= petgo_set_row('install', petgo_t('set_install'), esc_html(petgo_t('set_install_s')), '<button type="button" class="pgc-btn sec sm" data-pwa-install>' . esc_html(petgo_t('install')) . '</button>', ' hidden id="pgc-inst-row"');
    $s .= '</div></div>';

    if (petgo_can('admin')) {
        $exp = function ($type) {
            return esc_url(wp_nonce_url(admin_url('admin-post.php?action=petgo_export&type=' . $type), 'petgo_exp'));
        };
        $s .= '<div class="pgc-sgrp"><h4>' . esc_html(petgo_t('nav_site')) . '</h4><div class="pgc-sgl">';
        if (function_exists('petgo_page_site')) {
            $s .= '<a class="pgc-srow pgc-link" href="' . esc_url(petgo_page_url(array('pg' => 'site'))) . '" data-close-nav><span class="pgc-sri ic-globe">' . petgo_icon('globe') . '</span><span class="pgc-srt"><b>' . esc_html(petgo_t('nav_site')) . '</b><small>' . esc_html(petgo_t('site_s')) . '</small></span>' . petgo_icon('right') . '</a>';
        }
        /* 34.5: строки от других плагинов (например, «Реклама и SEO»): array(icon, title, sub, url) */
        foreach ((array) apply_filters('petgo_settings_rows', array()) as $r) {
            if (!is_array($r) || empty($r['title']) || empty($r['url'])) {
                continue;
            }
            $ic = isset($r['icon']) ? (string) $r['icon'] : 'globe';
            $s .= '<a class="pgc-srow pgc-link" href="' . esc_url($r['url']) . '" data-close-nav><span class="pgc-sri ic-' . esc_attr($ic) . '">' . petgo_icon($ic) . '</span><span class="pgc-srt"><b>' . esc_html($r['title']) . '</b><small>' . esc_html(isset($r['sub']) ? $r['sub'] : '') . '</small></span>' . petgo_icon('right') . '</a>';
        }
        $s .= '</div></div>';
        /* 34.5: какие плагины PetGo установлены — новые появляются здесь сами */
        $pl = function_exists('petgo_plugins') ? petgo_plugins() : array();
        if ($pl) {
            $s .= '<div class="pgc-sgrp"><h4>Плагины PetGo</h4><div class="pgc-sgl">';
            foreach ($pl as $x) {
                $s .= '<div class="pgc-srow"><span class="pgc-sri ic-install">' . petgo_icon('install') . '</span><span class="pgc-srt"><b>' . esc_html($x['name']) . '</b><small>' . esc_html(trim(($x['ver'] !== '' ? 'версия ' . $x['ver'] : '') . ($x['desc'] !== '' && !$x['core'] ? ' · ' . wp_trim_words(wp_strip_all_tags($x['desc']), 10, '…') : ''))) . '</small></span></div>';
            }
            $s .= '</div></div>';
        }
        $s .= '<div class="pgc-sgrp"><h4>' . esc_html(petgo_t('set_data')) . '</h4><div class="pgc-sgl">';
        $s .= '<a class="pgc-srow pgc-link" href="' . esc_url(petgo_page_url(array('pg' => 'log'))) . '" data-close-nav><span class="pgc-sri ic-log">' . petgo_icon('log') . '</span><span class="pgc-srt"><b>' . esc_html(petgo_t('nav_log')) . '</b><small>' . esc_html(petgo_t('log_s')) . '</small></span>' . petgo_icon('right') . '</a>';
        $s .= '<details class="pgc-srow pgc-exp"><summary><span class="pgc-sri ic-download">' . petgo_icon('download') . '</span><span class="pgc-srt"><b>' . esc_html(petgo_t('export')) . '</b><small>' . esc_html(petgo_t('export_s')) . '</small></span>' . petgo_icon('chev') . '</summary><div class="pgc-expl2">';
        foreach (array('orders' => 'orders', 'requests' => 'requests', 'pets' => 'pets', 'reviews' => 'nav_reviews', 'log' => 'nav_log') as $k => $l) {
            $s .= '<a class="pgc-btn sec sm" href="' . $exp($k) . '" download>' . esc_html(petgo_t($l)) . ' · CSV</a>';
        }
        $s .= '<a class="pgc-btn sm" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=petgo_backup'), 'petgo_exp')) . '" download>' . esc_html(petgo_t('backup')) . '</a></div></details>';
        $s .= petgo_form_open('rv_opt', 'class="pgc-srow"') . '<span class="pgc-sri ic-reviews">' . petgo_icon('reviews') . '</span><span class="pgc-srt"><b>' . esc_html(petgo_t('rv_auto')) . '</b><small>' . esc_html(petgo_t('rv_auto_s')) . '</small></span><input type="hidden" name="auto" value="' . (get_option('petgo_rv_auto') ? '0' : '1') . '"><button type="submit" class="pgc-sw" role="switch" aria-checked="' . (get_option('petgo_rv_auto') ? 'true' : 'false') . '" aria-label="' . esc_attr(petgo_t('rv_auto')) . '"><i></i></button></form>';
        $s .= '</div></div>';
    }

    if (petgo_is_owner()) {
        $w = petgo_form_open('wipe');
        foreach (array('orders' => 'wipe_orders', 'pets' => 'wipe_pets', 'reqs' => 'wipe_reqs', 'chats' => 'wipe_chats', 'clients' => 'wipe_clients') as $k => $l) {
            $w .= '<label class="pgc-ck"><input type="checkbox" name="w[]" value="' . $k . '"> <span>' . esc_html(petgo_t($l)) . '</span></label>';
        }
        /* 40.0: без пароля — два подтверждения подряд */
        $w .= '<button class="pgc-btn red wide" type="submit" onclick="if(!this.form.querySelector(\'input[name=&quot;w[]&quot;]:checked\'))return false;return confirm(\'' . esc_js(petgo_t('wipe_q')) . '\')&&confirm(\'' . esc_js(petgo_t('wipe_q2')) . '\')">' . esc_html(petgo_t('wipe_btn')) . '</button></form>';
        $s .= '<div class="pgc-sgrp"><details class="pgc-danger"><summary>' . esc_html(petgo_t('wipe')) . petgo_icon('chev') . '</summary><p class="mu">' . esc_html(petgo_t('wipe_hint')) . '</p>' . $w . '</details></div>';
    }
    $s .= '<p class="pgc-ver">' . esc_html(petgo_app_name() . ' · ' . PETGO_VER) . '</p>';
    echo petgo_modal('pgc-m-set', petgo_t('settings'), $s);
}

/* окно «разрешите уведомления» — появляется сразу при открытии, если ещё не разрешено */
function petgo_push_sheet() {
    echo '<div class="pgc-sheet" id="pgc-push" hidden><div class="pgc-shb"><span class="pgc-shi">' . petgo_icon('bell') . '</span><b>' . esc_html(petgo_t('push_t')) . '</b><p data-n>' . esc_html(petgo_t('push_d')) . '</p><p data-i hidden>' . esc_html(petgo_t('push_ios')) . '</p>';
    echo '<div class="pgc-shbtn"><button type="button" class="pgc-btn sec" data-push-later>' . esc_html(petgo_t('later_btn')) . '</button><button type="button" class="pgc-btn" data-push-allow>' . esc_html(petgo_t('allow')) . '</button></div></div></div>';
}

/* ---------- настройки для скрипта приложения ---------- */
function petgo_app_cfg($adm) {
    list($items) = petgo_nav_data($adm);
    $warm = array();
    foreach ($items as $k => $it) {
        if ($it[3] !== 'x') {
            $warm[] = $it[1];
        }
    }
    $v = petgo_vapid();
    $M = petgo_months();
    $L = array(
        'h' => petgo_t('h'), 'm' => petgo_t('m'), 'left' => petgo_t('left'), 'late' => petgo_t('late'), 'ret' => petgo_t('to_return'), 'up' => petgo_t('time_up'),
        'locating' => petgo_t('locating'), 'arrived' => petgo_t('arrived_btn'), 'noloc' => petgo_t('noloc'), 'denied' => petgo_t('locdenied'),
        'qr_cam' => petgo_t('qr_cam'), 'qr_door' => petgo_t('qr_door'), 'photo_need' => petgo_t('tip_photo'), 'qr_no_cam' => petgo_t('qr_no_cam'), 'qr_wrong' => petgo_t('qr_wrong'), 'qr_js' => petgo_asset_url('vendor/jsqr.min.js'),
        'offline' => petgo_t('offline'), 'offline_act' => petgo_t('offline_act'), 'neterr' => petgo_t('neterr'), 'short' => petgo_t('why_short'),
        'push_on' => petgo_t('push_on'), 'push_off' => petgo_t('push_off'), 'push_den' => petgo_t('push_den'), 'push_na' => petgo_t('push_na'), 'push_ios_s' => petgo_t('push_ios_s'), 'push_err' => petgo_t('push_err'),
        'chat' => petgo_t('chat'), 'none' => petgo_t('c_none'), 'to' => petgo_t('c_to'), 'expl' => petgo_t('c_expl'), 'c_late' => petgo_t('c_late'), 'err' => petgo_t('c_err'),
        'clrq' => petgo_t('c_clear_q'), 'delq' => petgo_t('c_del_q'), 'msgq' => petgo_t('c_msg_q'), 'add' => petgo_t('c_add'), 'del' => petgo_t('c_delete'),
        'tph1' => petgo_t('c_tph1'), 'tph0' => petgo_t('c_tph0'), 'tlk1' => petgo_t('c_tlk1'), 'tlk0' => petgo_t('c_tlk0'), 'today' => petgo_t('today'), 'yesterday' => petgo_t('yesterday'),
        'c_sys' => petgo_t('c_sys'), 'c_members' => petgo_t('c_members'), 'c_photo' => petgo_t('c_photo'), 'c_empty_t' => petgo_t('c_empty_t'),
        'cal_day' => petgo_t('cal_day'), 'cal_3' => petgo_t('cal_3'), 'cal_week' => petgo_t('cal_week'), 'cal_month' => petgo_t('cal_month'), 'cal_list' => petgo_t('cal_list'),
        'cal_free' => petgo_t('cal_free'), 'cal_more' => petgo_t('cal_more'), 'cal_none' => petgo_t('st_unassigned'), 'all' => petgo_t('all'),
        'unsaved' => petgo_t('unsaved'), 'months' => $M[0], 'months_g' => $M[1], 'wd' => petgo_day_short(),
    );
    return array(
        'aj' => admin_url('admin-ajax.php'), 'page' => petgo_page_url(), 'scope' => petgo_scope(), 'sw' => petgo_page_url(array('petgo_sw' => 1)),
        'nc' => wp_create_nonce('petgo_chat'), 'no' => wp_create_nonce('petgo_ord'), 'np' => wp_create_nonce('petgo_push'),
        'key' => $v ? $v['pub'] : '', 't' => time(), 'b' => petgo_asset_ver('app.css') . '.' . petgo_asset_ver('app.js'), 'uid' => get_current_user_id(), 'role' => petgo_role_of(), 'own' => petgo_is_owner(), 'adm' => $adm,
        'warm' => $warm, 'lang' => petgo_lang(), 'L' => $L,
    );
}

/* ---------- всё приложение ---------- */
function petgo_render_app() {
    if (!is_user_logged_in()) {
        return petgo_login_html();
    }
    if (petgo_role_of() === '') {
        return '<div class="pgc pgc-blank" id="pgc" data-role=""><div class="pgc-lcard">' . petgo_icon('shield') . '<h1>' . esc_html(petgo_t('no_access')) . '</h1><a class="pgc-btn" href="' . esc_url(wp_logout_url(petgo_page_url())) . '">' . esc_html(petgo_t('logout')) . '</a></div></div>';
    }
    $adm = petgo_can('office');
    list(, $cur) = petgo_nav_data($adm);
    ob_start();
    echo '<div class="pgc" id="pgc" data-role="' . esc_attr(petgo_role_of()) . '" data-v="' . esc_attr(petgo_ver()) . '" data-pg="' . esc_attr($cur) . '">';
    echo '<div class="pgc-off" id="pgc-off" hidden>' . esc_html(petgo_t('offline')) . '</div>';
    petgo_topbar($adm);
    echo '<main class="pgc-in" id="pgc-in">';
    /* 40.1: крупный заголовок — в начале страницы (как в iPhone и в приложении клиента); при прокрутке он
       уходит вверх, а в шапке появляется короткий заголовок */
    echo '<h1 class="pgc-lt" aria-hidden="true">' . esc_html(petgo_page_title($adm)) . '</h1>';
    petgo_flash_show();
    if ($adm) {
        petgo_render_admin();
    } else {
        petgo_render_nanny();
    }
    echo '</main><div id="pgc-mods">';
    if ($adm) {
        petgo_admin_modals();
    }
    petgo_profile_modal();
    petgo_settings_modal();
    petgo_why_modal();
    echo '</div>';
    petgo_chat_ui($adm);
    petgo_ov_ui();
    petgo_bottom_nav($adm);
    if ($adm) {
        petgo_fab();
    }
    petgo_push_sheet();
    /* 34.7: сканер QR-кода хозяина (камера на весь экран) */
    if (function_exists('petgo_qr_scanner_html')) {
        echo petgo_qr_scanner_html();
    }
    echo '<div class="pgc-toasts" id="pgc-toasts" aria-live="polite"></div>';
    echo '<script type="application/json" id="pgc-cfg">' . wp_json_encode(petgo_app_cfg($adm), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    echo '</div>';
    return ob_get_clean();
}

/* ---------- страница входа (iOS 27): логотип слева вверху, язык справа вверху, по центру — вход ---------- */
function petgo_login_html() {
    $cur  = petgo_lang();
    $LG   = petgo_langs();
    $logo = esc_url(petgo_asset('icon-192.png'));
    $err  = isset($_GET['pgl']) ? sanitize_key(wp_unslash($_GET['pgl'])) : '';
    $h  = '<div class="pgc pgc-login" id="pgc" data-role="">';
    $h .= '<header class="pgc-lt"><span class="pgc-logo"><img src="' . $logo . '" alt="" width="36" height="36"><b>' . esc_html(petgo_app_name()) . '</b></span>';
    $h .= '<div class="pgc-me"><button type="button" class="pgc-langb" data-menu="pgc-langm" aria-label="' . esc_attr(petgo_t('language')) . '">' . petgo_icon('globe') . '<span>' . esc_html(strtoupper($cur)) . '</span>' . petgo_icon('chev') . '</button><div class="pgc-menu" id="pgc-langm">';
    foreach ($LG as $c => $l) {
        $h .= '<button type="button" class="pgc-mi' . ($c === $cur ? ' on' : '') . '" data-lang="' . esc_attr($c) . '"><span class="pgc-lc2">' . esc_html(strtoupper($c)) . '</span>' . esc_html($l) . ($c === $cur ? petgo_icon('check') : '') . '</button>';
    }
    $h .= '</div></div></header>';
    $h .= '<main class="pgc-lmain"><div class="pgc-lcard"><img class="pgc-lmark" src="' . $logo . '" alt="" width="88" height="88"><h1>' . esc_html(petgo_t('login_title')) . '</h1><p class="mu">' . esc_html(petgo_t('login_sub')) . '</p>';
    $h .= '<div class="pgc-msg err" id="pgc-lerr"' . ($err !== '' ? '' : ' hidden') . '>' . esc_html(petgo_t($err === 'nouser' ? 'le_nouser' : ($err !== '' ? 'le_fail' : 'login_bad'))) . '</div>';
    $h .= '<div class="pgc-seg two" id="pgc-lmode" role="tablist"><button type="button" class="on" data-lmode="pass" role="tab">' . esc_html(petgo_t('mc_tab_pass')) . '</button><button type="button" data-lmode="code" role="tab">' . esc_html(petgo_t('mc_tab_code')) . '</button></div>';
    /* вход по паролю */
    $h .= '<form id="pgc-lform" method="post" action="' . esc_url(site_url('wp-login.php', 'login_post')) . '"><input type="hidden" name="redirect_to" value="' . esc_url(petgo_page_url()) . '"><input type="hidden" name="rememberme" value="forever">';
    $h .= '<div class="pgc-list"><label class="pgc-lrow"><span>' . esc_html(petgo_t('login_s')) . '</span><input type="text" name="log" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required></label>';
    $h .= '<label class="pgc-lrow"><span>' . esc_html(petgo_t('pass_s')) . '</span><span class="pgc-pw"><input type="password" name="pwd" autocomplete="current-password" required><button type="button" class="pgc-eye" data-eye aria-label="' . esc_attr(petgo_t('pass_show')) . '">' . petgo_icon('eye') . '</button></span></label></div>';
    $h .= '<button type="submit" class="pgc-btn wide lg">' . esc_html(petgo_t('login_btn')) . '</button></form>';
    /* вход по коду на почту */
    $h .= '<form id="pgc-mform" hidden data-aj="' . esc_url(admin_url('admin-ajax.php')) . '" data-to="' . esc_url(petgo_page_url()) . '"><input type="hidden" name="n" value="' . esc_attr(wp_create_nonce('petgo_mcode')) . '">';
    $h .= '<div class="pgc-list"><label class="pgc-lrow"><span>' . esc_html(petgo_t('email')) . '</span><input type="email" name="email" autocomplete="email" autocapitalize="none" spellcheck="false" inputmode="email" required></label>';
    $h .= '<label class="pgc-lrow" data-step2 hidden><span>' . esc_html(petgo_t('mc_code_l')) . '</span><input type="text" name="code" class="pgc-otp" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="••••••"></label></div>';
    $h .= '<p class="pgc-lhint mu" data-step2 hidden>' . esc_html(petgo_t('mc_sent')) . '</p>';
    $h .= '<button type="submit" class="pgc-btn wide lg" data-l1="' . esc_attr(petgo_t('mc_send')) . '" data-l2="' . esc_attr(petgo_t('login_btn')) . '">' . esc_html(petgo_t('mc_send')) . '</button>';
    $h .= '<button type="button" class="pgc-lnk" data-mreset data-step2 hidden>' . esc_html(petgo_t('mc_again')) . '</button></form>';
    /* Apple и Google — только если ключи заданы в настройках WordPress */
    $ap = function_exists('petgo_oauth_on') && petgo_oauth_on('apple');
    $go = function_exists('petgo_oauth_on') && petgo_oauth_on('google');
    if ($ap || $go) {
        $h .= '<div class="pgc-or"><span>' . esc_html(petgo_t('or')) . '</span></div><div class="pgc-sso">';
        if ($ap) {
            $h .= '<a class="pgc-btn wide lg pgc-apple" href="' . esc_url(add_query_arg('via', 'apple', petgo_oauth_redirect('start'))) . '"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16.37 12.6c-.02-2.2 1.8-3.26 1.88-3.31-1.03-1.5-2.62-1.7-3.18-1.73-1.35-.14-2.64.8-3.33.8-.69 0-1.74-.78-2.86-.76-1.47.02-2.83.86-3.59 2.17-1.53 2.66-.39 6.59 1.1 8.75.73 1.05 1.6 2.24 2.73 2.2 1.1-.05 1.51-.71 2.84-.71 1.32 0 1.7.71 2.86.69 1.18-.02 1.93-1.07 2.65-2.13.84-1.22 1.18-2.4 1.2-2.46-.03-.01-2.3-.88-2.3-3.51zM14.2 6.13c.6-.73 1.01-1.75.9-2.76-.87.04-1.92.58-2.54 1.31-.56.65-1.05 1.68-.92 2.68.97.07 1.96-.49 2.56-1.23z"/></svg><span>' . esc_html(petgo_t('via_apple')) . '</span></a>';
        }
        if ($go) {
            $h .= '<a class="pgc-btn wide lg pgc-google" href="' . esc_url(add_query_arg('via', 'google', petgo_oauth_redirect('start'))) . '"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.84 14.1A6.6 6.6 0 0 1 5.5 12c0-.73.13-1.44.34-2.1V7.06H2.18A11 11 0 0 0 1 12c0 1.77.43 3.45 1.18 4.94l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.06l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"/></svg><span>' . esc_html(petgo_t('via_google')) . '</span></a>';
        }
        $h .= '</div>';
    }
    $h .= '</div></main>';
    $h .= '<footer class="pgc-lfoot">© ' . esc_html(wp_date('Y') . ' ' . petgo_app_name()) . '</footer></div>';
    return $h;
}
