<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   СТРАНИЦЫ САЙТА БЕЗ ШОРТКОДОВ
   Плагин сам выводит: главную (/), /uslugi/, /nyani/, /otzyvy/, /lk/ — своим шаблоном, без шапки, подвала,
   стилей и скриптов темы. Создавать эти страницы в WordPress не нужно.
   Если постоянные ссылки выключены, адреса такие: /?petgo=uslugi и т. д.
   Главная выключается в WordPress: Настройки → PetGo Сайт («Показывать сайт PetGo на главной странице»).
   ============================================================ */

/* true, пока страница рисуется своим шаблоном (а не шорткодом внутри темы) */
function petgo_site_is_front($set = null) {
    static $v = false;
    if ($set !== null) {
        $v = (bool) $set;
    }
    return $v;
}

/* какая страница сайта запрошена по адресу: ключ из petgo_site_pages() или '' */
function petgo_site_route($set = null) {
    static $v = '';
    if ($set !== null) {
        $v = (string) $set;
    }
    return $v;
}

add_action('parse_request', 'petgo_site_parse', 1);
function petgo_site_parse($wp) {
    if (is_admin()) {
        return;
    }
    $slug = isset($_GET['petgo']) ? sanitize_key(wp_unslash($_GET['petgo'])) : '';
    if ($slug === '' && isset($wp->request)) {
        $slug = sanitize_key(trim((string) $wp->request, '/'));
    }
    if ($slug === '') {
        return;
    }
    foreach (petgo_site_pages() as $k => $p) {
        if ($p['slug'] !== '' && $p['slug'] === $slug) {
            petgo_site_route($k);
            /* WordPress такой страницы не знает и считает адрес «не найденным» — это наша страница */
            $wp->query_vars = array();
            return;
        }
    }
}

add_action('wp', 'petgo_site_wp', 1);
function petgo_site_wp() {
    if (petgo_site_route() === '') {
        return;
    }
    global $wp_query;
    $wp_query->is_404 = false;
    status_header(200);
}

/* адрес нашей страницы не «исправлять» на похожий */
add_filter('redirect_canonical', 'petgo_site_no_canon', 10, 1);
function petgo_site_no_canon($url) {
    return petgo_site_route() !== '' ? false : $url;
}

/* главная: только если включено и это действительно главная, а не кабинет */
function petgo_site_takeover() {
    static $r = null;
    if ($r !== null) {
        return $r;
    }
    $c = petgo_site_cfg();
    $r = $c['front'] === '1'
        && is_front_page()
        && !petgo_is_cabinet_page()
        && !is_feed()
        && !is_customize_preview()
        && !isset($_GET['elementor-preview']);
    return $r;
}

/* какую страницу сайта выводим сейчас ('' — никакую, работает тема) */
function petgo_site_page_now() {
    $r = petgo_site_route();
    if ($r !== '') {
        return $r;
    }
    return (did_action('wp') && petgo_site_takeover()) ? 'home' : '';
}

/* после шаблона кабинета (99): кабинет важнее, если он стоит на главной */
add_filter('template_include', 'petgo_site_template', 100);
function petgo_site_template($t) {
    return petgo_site_page_now() !== '' ? __DIR__ . '/site-template.php' : $t;
}

/* панель администратора WordPress поверх сайта не нужна */
add_filter('show_admin_bar', 'petgo_site_no_bar', 20);
function petgo_site_no_bar($show) {
    return petgo_site_page_now() !== '' ? false : $show;
}

/* описание страницы для поиска и соцсетей */
function petgo_site_desc() {
    $c = petgo_site_cfg();
    $s = isset($c['slides'][0]) ? $c['slides'][0] : array('t' => '', 's' => '');
    $d = trim((string) $c['seo']) !== '' ? petgo_site_l($c['seo']) : trim(petgo_site_tx($s['t']) . '. ' . petgo_site_tx($s['s']));
    return trim(wp_strip_all_tags(str_replace("\u{00A0}", ' ', (string) $d)));
}

function petgo_site_head($page = 'home') {
    $c     = petgo_site_cfg();
    $pgs   = petgo_site_pages();
    $page  = isset($pgs[$page]) ? $page : 'home';
    $s0    = isset($c['slides'][0]['t']) ? petgo_site_tx($c['slides'][0]['t']) : '';
    $title = $page === 'home' ? trim(wp_strip_all_tags($s0)) : $pgs[$page]['title'];
    $name  = trim((string) get_bloginfo('name'));
    $full  = $title . ($name !== '' ? ' — ' . $name : '');
    $desc  = petgo_site_desc();
    $url   = petgo_site_url($page);
    $img   = petgo_asset('icon-512.png');
    foreach ($c['hero_media'] as $m) {
        if (strpos($m['u'], 'art:') !== 0 && preg_match('/\.(jpe?g|png|webp)(\?|$)/i', $m['u'])) {
            $img = $m['u'];
            break;
        }
    }
    $h  = '<title>' . esc_html($full) . '</title>';
    $h .= '<meta name="description" content="' . esc_attr($desc) . '">';
    if (!get_option('blog_public') || $page === 'account' || $page === 'join') {
        $h .= '<meta name="robots" content="noindex,nofollow">';
    }
    $h .= '<link rel="canonical" href="' . esc_url($url) . '">';
    /* те же страницы на других языках — для поиска */
    foreach (petgo_site_langs() as $lk => $lv) {
        $h .= '<link rel="alternate" hreflang="' . esc_attr($lv[2]) . '" href="' . esc_url(petgo_site_url($page, $lk)) . '">';
    }
    $h .= '<link rel="alternate" hreflang="x-default" href="' . esc_url(petgo_site_url($page, 'ru')) . '">';
    $loc = array('ru' => 'ru_RU', 'kz' => 'kk_KZ', 'en' => 'en_US');
    $h .= '<meta property="og:type" content="website"><meta property="og:locale" content="' . esc_attr($loc[petgo_site_lang()]) . '">';
    $h .= '<meta property="og:title" content="' . esc_attr($full) . '"><meta property="og:description" content="' . esc_attr($desc) . '">';
    $h .= '<meta property="og:url" content="' . esc_url($url) . '"><meta property="og:image" content="' . esc_url($img) . '">';
    $h .= '<meta name="twitter:card" content="summary_large_image">';
    $h .= '<meta name="theme-color" content="#FBF9E8"><meta name="color-scheme" content="light"><meta name="format-detection" content="telephone=no">';
    $h .= '<link rel="icon" href="' . esc_url(petgo_asset('icon-192.png')) . '"><link rel="apple-touch-icon" href="' . esc_url(petgo_asset('apple-touch-icon.png')) . '">';
    $h .= '<link rel="stylesheet" href="' . esc_url(petgo_asset_url('site.css')) . '">';
    /* кабинет клиента: тема (светлая / тёмная) включается до отрисовки, без мигания */
    if ($page === 'account' && function_exists('petgo_cl_uid') && petgo_cl_uid()) {
        $h .= '<script>(function(){var d=document.documentElement,t="auto";try{t=localStorage.getItem("pgk_theme")||"auto"}catch(e){}d.classList.add("pgk-on");if(t==="dark"||(t==="auto"&&window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches))d.classList.add("pgk-dark")})();</script>';
    }
    if ($page === 'home') {
        $ld = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'LocalBusiness',
            'name'        => $name !== '' ? $name : 'PetGo.kz',
            'url'         => $url,
            'description' => $desc,
            'image'       => $img,
            'telephone'   => '+' . petgo_site_phone(),
            'areaServed'  => 'Алматы',
        );
        $h .= '<script type="application/ld+json">' . wp_json_encode($ld, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }
    /* счётчики: код вводит администратор сайта в настройках */
    if (trim((string) $c['head']) !== '') {
        $h .= "\n" . $c['head'] . "\n";
    }
    return $h;
}
