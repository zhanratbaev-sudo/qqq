<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ПРИЛОЖЕНИЕ НА ЭКРАН (PWA) И УВЕДОМЛЕНИЯ НА ТЕЛЕФОН
   Service worker (assets/sw.js) хранит кабинет на телефоне: открывается сразу и работает без интернета.
   Уведомления идут через браузерный push (VAPID). Нужен https.
   Текст уведомления сервер хранит у себя и отдаёт только вошедшему сотруднику.
   ============================================================ */
function petgo_is_cabinet_page() {
    static $r = null;
    if ($r === null) {
        $id = (int) petgo_opt('page_id');
        $p  = is_singular() ? get_post() : null;
        $r  = ($id && is_page($id)) || ($p && has_shortcode((string) $p->post_content, 'petgo_cabinet'));
    }
    return $r;
}

function petgo_scope() {
    $p = (string) wp_parse_url(petgo_page_url(), PHP_URL_PATH);
    return $p === '' ? '/' : trailingslashit($p);
}

function petgo_b64u($s) {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function petgo_asset($f) {
    return plugins_url('assets/' . $f, dirname(__DIR__) . '/petgo-cabinet.php');
}

/* ---------- манифест и service worker (адреса внутри страницы кабинета, чтобы приложение охватывало весь кабинет) ---------- */
add_action('init', 'petgo_pwa_files', 1);
function petgo_pwa_files() {
    if (isset($_GET['petgo_manifest'])) {
        petgo_manifest();
    }
    if (isset($_GET['petgo_sw'])) {
        petgo_sw();
    }
}

function petgo_manifest() {
    $m = array(
        'id' => petgo_scope(), 'name' => petgo_app_name(), 'short_name' => petgo_app_name(), 'description' => 'PetGo: заказы, календарь, заявки и чаты',
        'start_url' => petgo_page_url(), 'scope' => petgo_scope(), 'display' => 'standalone', 'orientation' => 'portrait',
        'background_color' => '#f2f2f7', 'theme_color' => '#f2f2f7', 'lang' => 'ru',
        'icons' => array(
            array('src' => petgo_asset('icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'),
            array('src' => petgo_asset('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'),
            array('src' => petgo_asset('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'),
        ),
    );
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    echo wp_json_encode($m, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function petgo_sw() {
    $cfg = wp_json_encode(array(
        'v' => petgo_asset_ver('app.js') . '-' . petgo_asset_ver('app.css') . '-' . petgo_asset_ver('kb.js'), 'home' => petgo_page_url(), 'scope' => petgo_scope(),
        'peek' => admin_url('admin-ajax.php'), 'icon' => petgo_asset('icon-192.png'),
        'pre' => array(petgo_asset_url('app.css'), petgo_asset_url('kb.js'), petgo_asset_url('app.js'), petgo_asset('icon-192.png')),
        'off' => array('t' => 'Нет интернета', 'b' => 'Откройте кабинет, когда появится связь.', 'r' => 'Обновить'),
    ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Content-Type-Options: nosniff');
    $js = is_readable(dirname(__DIR__) . '/assets/sw.js') ? (string) file_get_contents(dirname(__DIR__) . '/assets/sw.js') : '';
    echo 'var C=' . $cfg . ";\n" . $js;
    exit;
}

/* ---------- ключи VAPID ---------- */
function petgo_vapid() {
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $o = get_option('petgo_vapid');
    if (is_array($o) && !empty($o['pem']) && !empty($o['pub'])) {
        return $c = $o;
    }
    if (!function_exists('openssl_pkey_new')) {
        return $c = false;
    }
    $k = @openssl_pkey_new(array('private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'));
    if (!$k) {
        return $c = false;
    }
    $d = openssl_pkey_get_details($k);
    if (empty($d['ec']['x']) || empty($d['ec']['y']) || !openssl_pkey_export($k, $pem)) {
        return $c = false;
    }
    $o = array('pem' => $pem, 'pub' => petgo_b64u("\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT)));
    update_option('petgo_vapid', $o, false);
    return $c = $o;
}

function petgo_der_to_raw($der) {
    $o = 2;
    if (ord($der[1]) & 0x80) {
        $o = 2 + (ord($der[1]) & 0x7f);
    }
    $rl = ord($der[$o + 1]);
    $r  = substr($der, $o + 2, $rl);
    $o2 = $o + 2 + $rl;
    $sl = ord($der[$o2 + 1]);
    $s  = substr($der, $o2 + 2, $sl);
    return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
}

function petgo_vapid_jwt($aud) {
    $v = petgo_vapid();
    if (!$v) {
        return '';
    }
    $in = petgo_b64u(wp_json_encode(array('typ' => 'JWT', 'alg' => 'ES256'))) . '.' . petgo_b64u(wp_json_encode(array('aud' => $aud, 'exp' => time() + 43200, 'sub' => 'mailto:' . get_option('admin_email'))));
    if (!openssl_sign($in, $sig, $v['pem'], OPENSSL_ALGO_SHA256)) {
        return '';
    }
    return $in . '.' . petgo_b64u(petgo_der_to_raw($sig));
}

function petgo_push_host_ok($ep) {
    $u = wp_parse_url((string) $ep);
    if (empty($u['scheme']) || $u['scheme'] !== 'https' || empty($u['host']) || (!empty($u['port']) && (int) $u['port'] !== 443)) {
        return false;
    }
    $h = strtolower($u['host']);
    foreach (array('googleapis.com', 'mozilla.com', 'apple.com', 'windows.com', 'microsoft.com') as $s) {
        if ($h === $s || substr($h, -strlen($s) - 1) === '.' . $s) {
            return true;
        }
    }
    return false;
}

/* ---------- подписки ---------- */
add_action('wp_ajax_petgo_push_sub', 'petgo_ajax_push_sub');
function petgo_ajax_push_sub() {
    check_ajax_referer('petgo_push', 'n');
    if (petgo_role_of() === '') {
        wp_send_json(array('ok' => false));
    }
    $s  = json_decode(isset($_POST['s']) ? (string) wp_unslash($_POST['s']) : '', true);
    $ep = (is_array($s) && !empty($s['endpoint']) && is_string($s['endpoint'])) ? $s['endpoint'] : '';
    if (!petgo_push_host_ok($ep) || strlen($ep) > 600) {
        wp_send_json(array('ok' => false));
    }
    $uid  = get_current_user_id();
    $subs = get_user_meta($uid, 'petgo_push', true);
    $subs = is_array($subs) ? $subs : array();
    $subs = array_values(array_filter($subs, function ($x) use ($ep) {
        return isset($x['e']) && $x['e'] !== $ep;
    }));
    if (isset($_POST['do']) && $_POST['do'] === 'sub') {
        $subs[] = array('e' => $ep, 't' => time());
        $subs   = array_slice($subs, -5);
    }
    update_user_meta($uid, 'petgo_push', $subs);
    wp_send_json(array('ok' => true));
}

add_action('wp_ajax_petgo_push_peek', 'petgo_ajax_push_peek');
add_action('wp_ajax_nopriv_petgo_push_peek', 'petgo_ajax_push_peek');
function petgo_ajax_push_peek() {
    if (!is_user_logged_in() || petgo_role_of() === '') {
        wp_send_json(array('items' => array()));
    }
    $uid = get_current_user_id();
    $h   = md5(isset($_POST['e']) ? (string) wp_unslash($_POST['e']) : '');
    $q   = get_user_meta($uid, 'petgo_pq', true);
    $q   = is_array($q) ? $q : array();
    $it  = array();
    if (!empty($q[$h])) {
        foreach ($q[$h] as $x) {
            if (isset($x['ts']) && $x['ts'] > time() - 6 * HOUR_IN_SECONDS) {
                $it[] = array('t' => $x['t'], 'b' => $x['b'], 'u' => $x['u'], 'g' => $x['g']);
            }
        }
        unset($q[$h]);
        update_user_meta($uid, 'petgo_pq', $q);
    }
    wp_send_json(array('items' => $it));
}

/* ---------- отправка ---------- */
function petgo_push_users($roles) {
    $o = array();
    foreach (petgo_staff_users() as $u) {
        if (in_array(petgo_role_of($u), $roles, true)) {
            $o[] = (int) $u->ID;
        }
    }
    if (in_array('owner', $roles, true)) {
        foreach (get_users(array('role' => 'administrator', 'fields' => 'ID')) as $id) {
            $o[] = (int) $id;
        }
    }
    return array_values(array_unique($o));
}

function petgo_push_notify($uids, $title, $body, $url = '', $tag = '') {
    if (!petgo_vapid()) {
        return;
    }
    $jobs = array();
    foreach (array_unique(array_map('intval', (array) $uids)) as $uid) {
        $subs = get_user_meta($uid, 'petgo_push', true);
        if (!is_array($subs) || !$subs) {
            continue;
        }
        $q = get_user_meta($uid, 'petgo_pq', true);
        $q = is_array($q) ? $q : array();
        foreach ($subs as $s) {
            $h        = md5($s['e']);
            $q[$h][]  = array('t' => mb_substr((string) $title, 0, 80), 'b' => mb_substr((string) $body, 0, 160), 'u' => $url !== '' ? $url : petgo_page_url(), 'g' => $tag !== '' ? $tag : substr(md5($title), 0, 6), 'ts' => time());
            $q[$h]    = array_slice($q[$h], -6);
            $jobs[]   = array($uid, $s['e']);
        }
        update_user_meta($uid, 'petgo_pq', $q);
    }
    if (!$jobs) {
        return;
    }
    global $petgo_push_jobs;
    $petgo_push_jobs = is_array($petgo_push_jobs) ? array_merge($petgo_push_jobs, $jobs) : $jobs;
    static $reg = false;
    if (!$reg) {
        $reg = true;
        add_action('shutdown', 'petgo_push_flush', 99);
    }
}

function petgo_push_flush() {
    global $petgo_push_jobs;
    if (empty($petgo_push_jobs)) {
        return;
    }
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        @litespeed_finish_request();
    }
    foreach ($petgo_push_jobs as $j) {
        $code = petgo_push_send_one($j[1]);
        if ($code === 404 || $code === 410) {
            $subs = get_user_meta($j[0], 'petgo_push', true);
            if (is_array($subs)) {
                update_user_meta($j[0], 'petgo_push', array_values(array_filter($subs, function ($x) use ($j) {
                    return isset($x['e']) && $x['e'] !== $j[1];
                })));
            }
        }
    }
    $petgo_push_jobs = array();
}

function petgo_push_send_one($ep) {
    $v = petgo_vapid();
    if (!$v || !petgo_push_host_ok($ep)) {
        return 0;
    }
    $u   = wp_parse_url($ep);
    $jwt = petgo_vapid_jwt($u['scheme'] . '://' . $u['host']);
    if ($jwt === '') {
        return 0;
    }
    $r = wp_remote_post($ep, array('timeout' => 6, 'redirection' => 0, 'headers' => array('Authorization' => 'vapid t=' . $jwt . ', k=' . $v['pub'], 'TTL' => '7200', 'Urgency' => 'high'), 'body' => ''));
    return is_wp_error($r) ? 0 : (int) wp_remote_retrieve_response_code($r);
}
