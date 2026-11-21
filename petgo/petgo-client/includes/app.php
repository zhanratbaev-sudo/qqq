<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   37.0 ПРИЛОЖЕНИЕ «MY PETGO» (App Store и Google Play)
   Приложение открывает кабинет клиента с сайта и добавляет то, чего нет у сайта:
   уведомления на телефон (няня у двери, прогулка началась, ответ менеджера), заставку, экран «Нет интернета».
   Android — через Firebase Cloud Messaging, iPhone — напрямую через Apple (APNs).
   Ключи вводит администратор в приложении сотрудников: Настройки → «Приложение My PetGo».
   ============================================================ */

/* страница открыта в приложении My PetGo (метка в User-Agent ставится самим приложением) */
function petgo_cl_in_app() {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    return strpos($ua, 'MyPetGoApp/') !== false;
}

function petgo_mapp_cfg() {
    $c = get_option('petgo_mapp', array());
    return array_merge(array('fcm' => '', 'apns_key' => '', 'apns_kid' => '', 'apns_team' => '', 'bundle' => 'kz.petgo.my', 'sandbox' => '0'), is_array($c) ? $c : array());
}

/* сервисный аккаунт Firebase (JSON) или null */
function petgo_mapp_fcm_sa() {
    $j = json_decode((string) petgo_mapp_cfg()['fcm'], true);
    return (is_array($j) && !empty($j['client_email']) && !empty($j['private_key']) && !empty($j['project_id'])) ? $j : null;
}

function petgo_mapp_h2() {
    if (!function_exists('curl_init') || !defined('CURL_HTTP_VERSION_2_0')) {
        return false;
    }
    $v = curl_version();
    return defined('CURL_VERSION_HTTP2') && !empty($v['features']) && ($v['features'] & CURL_VERSION_HTTP2);
}

function petgo_mapp_ready($platform) {
    $c = petgo_mapp_cfg();
    if ($platform === 'android') {
        return (bool) petgo_mapp_fcm_sa();
    }
    return $c['apns_key'] !== '' && strlen($c['apns_kid']) === 10 && strlen($c['apns_team']) === 10 && $c['bundle'] !== '' && petgo_mapp_h2();
}

/* ---------- токены устройств: отдельная строка user meta на каждый телефон ---------- */
function petgo_mapp_tokens($uid) {
    $o = array();
    foreach ((array) get_user_meta($uid, 'petgo_app_tok', false) as $r) {
        $r = is_array($r) ? $r : json_decode((string) $r, true);
        if (is_array($r) && !empty($r['t']) && !empty($r['p'])) {
            $o[] = $r;
        }
    }
    return $o;
}

function petgo_mapp_token_ok($t, $p) {
    return $p === 'ios' ? (bool) preg_match('/^[0-9a-f]{64,200}$/', $t) : (bool) preg_match('/^[A-Za-z0-9_:\-]{100,4096}$/', $t);
}

/* убрать токен у всех (телефон перешёл к другому клиенту, вышли из кабинета, Apple/Google сказали «больше нет») */
function petgo_mapp_token_drop($t) {
    global $wpdb;
    $like = '%' . $wpdb->esc_like('"t":"' . $t . '"') . '%';
    $ids  = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'petgo_app_tok' AND meta_value LIKE %s", $like));
    if (!$ids) {
        return;
    }
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'petgo_app_tok' AND meta_value LIKE %s", $like));
    foreach ($ids as $id) {
        wp_cache_delete((int) $id, 'user_meta');
    }
}

function petgo_mapp_token_add($uid, $t, $p) {
    petgo_mapp_token_drop($t);
    $all = petgo_mapp_tokens($uid);
    /* не больше 5 телефонов на кабинет: самый старый уходит */
    if (count($all) >= 5) {
        usort($all, function ($a, $b) {
            return (int) $a['ts'] - (int) $b['ts'];
        });
        petgo_mapp_token_drop($all[0]['t']);
    }
    add_user_meta($uid, 'petgo_app_tok', wp_json_encode(array('t' => $t, 'p' => $p, 'ts' => time())));
}

/* ============================================================
   ОТПРАВКА: копим за запрос, отправляем после ответа браузеру (как уведомления сотрудникам)
   ============================================================ */
function petgo_mapp_notify($uid, $title, $body, $go = '', $tag = '') {
    $uid = (int) $uid;
    if (!$uid) {
        return;
    }
    $toks = petgo_mapp_tokens($uid);
    if (!$toks) {
        return;
    }
    global $petgo_mapp_jobs;
    $petgo_mapp_jobs = is_array($petgo_mapp_jobs) ? $petgo_mapp_jobs : array();
    $msg = array('t' => mb_substr(wp_strip_all_tags((string) $title), 0, 80), 'b' => mb_substr(trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $body))), 0, 220), 'go' => (string) $go, 'tag' => (string) $tag);
    foreach ($toks as $k) {
        $petgo_mapp_jobs[] = array($k, $msg);
    }
    static $reg = false;
    if (!$reg) {
        $reg = true;
        add_action('shutdown', 'petgo_mapp_flush', 98);
    }
}

function petgo_mapp_flush() {
    global $petgo_mapp_jobs;
    if (empty($petgo_mapp_jobs)) {
        return;
    }
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        @litespeed_finish_request();
    }
    foreach ($petgo_mapp_jobs as $j) {
        petgo_mapp_send($j[0], $j[1]);
    }
    $petgo_mapp_jobs = array();
}

/* одна отправка; 'gone' — токен больше не действует (удаляем) */
function petgo_mapp_send($tok, $m) {
    $r = $tok['p'] === 'ios' ? petgo_mapp_apns($tok['t'], $m) : petgo_mapp_fcm($tok['t'], $m);
    if ($r === 'gone') {
        petgo_mapp_token_drop($tok['t']);
    }
    return $r;
}

function petgo_mapp_b64u($s) {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

/* ---------- Android: Firebase Cloud Messaging (HTTP v1) ---------- */
function petgo_mapp_fcm_token() {
    $sa = petgo_mapp_fcm_sa();
    if (!$sa) {
        return '';
    }
    $ck = 'petgo_fcm_at_' . md5($sa['client_email']);
    $at = get_transient($ck);
    if (is_string($at) && $at !== '') {
        return $at;
    }
    $aud = !empty($sa['token_uri']) ? $sa['token_uri'] : 'https://oauth2.googleapis.com/token';
    $now = time();
    $jwt = petgo_mapp_b64u(wp_json_encode(array('alg' => 'RS256', 'typ' => 'JWT'))) . '.' . petgo_mapp_b64u(wp_json_encode(array('iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => $aud, 'iat' => $now, 'exp' => $now + 3600)));
    $sig = '';
    if (!function_exists('openssl_sign') || !openssl_sign($jwt, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
        return '';
    }
    $r = wp_remote_post($aud, array('timeout' => 10, 'body' => array('grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt . '.' . petgo_mapp_b64u($sig))));
    $j = is_wp_error($r) ? null : json_decode(wp_remote_retrieve_body($r), true);
    if (empty($j['access_token'])) {
        return '';
    }
    set_transient($ck, $j['access_token'], max(60, (int) (isset($j['expires_in']) ? $j['expires_in'] : 3600) - 120));
    return $j['access_token'];
}

function petgo_mapp_fcm($token, $m) {
    $sa = petgo_mapp_fcm_sa();
    $at = $sa ? petgo_mapp_fcm_token() : '';
    if ($at === '') {
        return 'off';
    }
    $data = array('go' => $m['go'], 'tag' => $m['tag']);
    $msg  = array('message' => array(
        'token' => $token,
        'notification' => array('title' => $m['t'], 'body' => $m['b']),
        'data' => array_map('strval', $data),
        'android' => array('priority' => 'high', 'notification' => array('channel_id' => 'petgo', 'color' => '#024C46', 'sound' => 'default', 'tag' => $m['tag'] !== '' ? $m['tag'] : null)),
    ));
    if ($m['tag'] === '') {
        unset($msg['message']['android']['notification']['tag']);
    }
    $r    = wp_remote_post('https://fcm.googleapis.com/v1/projects/' . rawurlencode($sa['project_id']) . '/messages:send', array('timeout' => 10, 'headers' => array('Authorization' => 'Bearer ' . $at, 'Content-Type' => 'application/json; charset=utf-8'), 'body' => wp_json_encode($msg)));
    if (is_wp_error($r)) {
        return 'err';
    }
    $code = (int) wp_remote_retrieve_response_code($r);
    if ($code === 200) {
        return 'ok';
    }
    $b = (string) wp_remote_retrieve_body($r);
    if ($code === 404 || strpos($b, 'UNREGISTERED') !== false || ($code === 400 && strpos($b, 'registration token') !== false)) {
        return 'gone';
    }
    if ($code === 401) {
        delete_transient('petgo_fcm_at_' . md5($sa['client_email']));
    }
    return 'err';
}

/* ---------- iPhone: Apple Push Notification service (HTTP/2, ключ .p8) ---------- */
function petgo_mapp_apns_jwt() {
    $c  = petgo_mapp_cfg();
    $ck = 'petgo_apns_jwt_' . md5($c['apns_kid'] . $c['apns_team']);
    $j  = get_transient($ck);
    if (is_string($j) && $j !== '') {
        return $j;
    }
    $in  = petgo_mapp_b64u(wp_json_encode(array('alg' => 'ES256', 'kid' => $c['apns_kid']))) . '.' . petgo_mapp_b64u(wp_json_encode(array('iss' => $c['apns_team'], 'iat' => time())));
    $der = '';
    if (!function_exists('openssl_sign') || !openssl_sign($in, $der, $c['apns_key'], OPENSSL_ALGO_SHA256)) {
        return '';
    }
    $raw = petgo_mapp_der2raw($der);
    if ($raw === '') {
        return '';
    }
    $j = $in . '.' . petgo_mapp_b64u($raw);
    /* Apple: токен живёт до часа, обновлять не чаще раза в 20 минут */
    set_transient($ck, $j, 40 * MINUTE_IN_SECONDS);
    return $j;
}

/* подпись ECDSA из DER (SEQUENCE{INTEGER r, INTEGER s}) — в 64 байта r||s, как нужно для JWT */
function petgo_mapp_der2raw($der) {
    if (strlen($der) < 8 || ord($der[0]) !== 0x30) {
        return '';
    }
    $p = 2;
    if (ord($der[1]) & 0x80) {
        $p += ord($der[1]) & 0x7f;
    }
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        if (!isset($der[$p]) || ord($der[$p]) !== 0x02) {
            return '';
        }
        $len  = ord($der[$p + 1]);
        $int  = ltrim(substr($der, $p + 2, $len), "\x00");
        $out .= str_pad($int, 32, "\x00", STR_PAD_LEFT);
        $p   += 2 + $len;
    }
    return strlen($out) === 64 ? $out : '';
}

function petgo_mapp_apns($token, $m, $sandbox = null) {
    $c = petgo_mapp_cfg();
    if (!petgo_mapp_ready('ios')) {
        return 'off';
    }
    $jwt = petgo_mapp_apns_jwt();
    if ($jwt === '') {
        return 'off';
    }
    $sb   = $sandbox === null ? $c['sandbox'] === '1' : $sandbox;
    $host = $sb ? 'https://api.sandbox.push.apple.com' : 'https://api.push.apple.com';
    $body = array('aps' => array('alert' => array('title' => $m['t'], 'body' => $m['b']), 'sound' => 'default'), 'go' => $m['go'], 'tag' => $m['tag']);
    $hd   = array('authorization: bearer ' . $jwt, 'apns-topic: ' . $c['bundle'], 'apns-push-type: alert', 'apns-priority: 10', 'content-type: application/json');
    if ($m['tag'] !== '') {
        $hd[] = 'apns-collapse-id: ' . substr(preg_replace('/[^A-Za-z0-9_\-]/', '', $m['tag']), 0, 60);
    }
    $ch = curl_init($host . '/3/device/' . $token);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => wp_json_encode($body), CURLOPT_HTTPHEADER => $hd,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 6,
    ));
    $resp = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) {
        return 'ok';
    }
    if ($code === 403 && strpos($resp, 'ExpiredProviderToken') !== false) {
        delete_transient('petgo_apns_jwt_' . md5($c['apns_kid'] . $c['apns_team']));
        return 'err';
    }
    /* токен из сборки Xcode (sandbox), а отправили в рабочий сервер Apple — пробуем sandbox один раз */
    if ($code === 400 && strpos($resp, 'BadDeviceToken') !== false && $sandbox === null) {
        $r2 = petgo_mapp_apns($token, $m, !$sb);
        return $r2 === 'ok' ? 'ok' : 'gone';
    }
    if ($code === 410 || ($code === 400 && strpos($resp, 'BadDeviceToken') !== false)) {
        return 'gone';
    }
    return 'err';
}

/* ============================================================
   КОГДА УВЕДОМЛЯТЬ КЛИЕНТА
   ============================================================ */
/* сообщение кабинета клиенту («Подтверждено…», «Прогулка началась…», «Мурка дома…») */
add_action('petgo_cl_sys_msg', function ($uid, $body) {
    petgo_mapp_notify($uid, 'PetGo', $body, 'chat');
}, 10, 2);

/* менеджер ответил в чате */
add_action('petgo_chat_pushed', function ($c, $from, $to, $text) {
    if (!is_object($c) || $c->type !== 'client' || strpos((string) $c->ckey, 'cl_') !== 0) {
        return;
    }
    $uid = (int) substr((string) $c->ckey, 3);
    if ($uid && in_array($uid, array_map('intval', (array) $to), true)) {
        petgo_mapp_notify($uid, petgo_site_l('Менеджер PetGo'), $text, 'chat', 'chat');
    }
}, 10, 4);

/* няня у двери — QR-код нужен прямо сейчас */
add_action('petgo_door_set', function ($oid, $ph) {
    $pid = (int) petgo_o($oid, 'pet');
    $uid = $pid ? (int) get_post_meta($pid, '_client', true) : 0;
    if (!$uid) {
        return;
    }
    $pet  = petgo_pet_name($oid);
    $back = in_array($ph, array('back', 'leave'), true);
    $t    = $back ? sprintf('%s дома', $pet) : 'Няня у двери';
    $b    = $back ? 'Покажите няне QR-код из приложения — так вы подтвердите, что питомец вернулся.' : sprintf('Покажите няне QR-код из приложения, чтобы передать %s.', $pet);
    petgo_mapp_notify($uid, $t, $b, 'door', 'door-' . (int) $oid);
}, 10, 2);

/* ============================================================
   ЗАПРОСЫ КАБИНЕТА ИЗ ПРИЛОЖЕНИЯ
   ============================================================ */
add_action('petgo_cl_op', function ($op, $uid) {
    if ($op === 'push_reg') {
        $t = trim((string) petgo_p('t'));
        $p = petgo_p('p') === 'ios' ? 'ios' : 'android';
        if (!petgo_mapp_token_ok($t, $p)) {
            petgo_cl_json(false);
        }
        petgo_mapp_token_add($uid, $t, $p);
        petgo_cl_json(true, array('ready' => petgo_mapp_ready($p) ? 1 : 0));
    }
    if ($op === 'push_unreg') {
        $t = trim((string) petgo_p('t'));
        if ($t !== '' && petgo_mapp_token_ok($t, petgo_p('p') === 'ios' ? 'ios' : 'android')) {
            petgo_mapp_token_drop($t);
        }
        petgo_cl_json(true);
    }
    /* договор в приложении: ссылка на 5 минут без входа (браузер внутри приложения не видит вход кабинета) */
    if ($op === 'file_link') {
        $f = petgo_p('f') === 'cms' ? 'cms' : 'pdf';
        $e = time() + 5 * MINUTE_IN_SECONDS;
        petgo_cl_json(true, array('url' => add_query_arg(array('action' => 'petgo_ct_link', 'u' => $uid, 'f' => $f, 'e' => $e, 'k' => petgo_mapp_file_key($uid, $f, $e)), admin_url('admin-ajax.php'))));
    }
}, 10, 2);

function petgo_mapp_file_key($uid, $f, $e) {
    return substr(hash_hmac('sha256', 'ctlink|' . (int) $uid . '|' . $f . '|' . (int) $e, wp_salt('auth')), 0, 32);
}

add_action('wp_ajax_petgo_ct_link', 'petgo_mapp_file_dl');
add_action('wp_ajax_nopriv_petgo_ct_link', 'petgo_mapp_file_dl');
function petgo_mapp_file_dl() {
    $uid = isset($_GET['u']) ? (int) $_GET['u'] : 0;
    $f   = isset($_GET['f']) && $_GET['f'] === 'cms' ? 'cms' : 'pdf';
    $e   = isset($_GET['e']) ? (int) $_GET['e'] : 0;
    $k   = isset($_GET['k']) ? sanitize_text_field(wp_unslash($_GET['k'])) : '';
    if (!$uid || $e < time() || !hash_equals(petgo_mapp_file_key($uid, $f, $e), $k) || !function_exists('petgo_ct_get')) {
        status_header(403);
        exit('403');
    }
    $r    = petgo_ct_get($uid);
    $path = ($r && !empty($r[$f])) ? petgo_ct_path($r[$f]) : '';
    if (!$path || !is_file($path)) {
        status_header(404);
        exit('404');
    }
    petgo_ct_send(file_get_contents($path), 'dogovor-petgo-' . preg_replace('/[^A-Za-z0-9-]/', '-', $r['no']) . '.' . $f, $f === 'pdf' ? 'application/pdf' : 'application/pkcs7-signature');
}

/* данные для скриптов кабинета: приложение и готовность уведомлений */
add_filter('petgo_cl_cfg', function ($cfg, $uid) {
    $cfg['app'] = petgo_cl_in_app() ? array('ios' => petgo_mapp_ready('ios') ? 1 : 0, 'android' => petgo_mapp_ready('android') ? 1 : 0) : null;
    return $cfg;
}, 10, 2);

/* ============================================================
   ПРИЛОЖЕНИЕ СОТРУДНИКОВ: Настройки → «Приложение My PetGo»
   ============================================================ */
add_filter('petgo_settings_rows', function ($rows) {
    if (function_exists('petgo_can') && petgo_can('admin')) {
        $on = (petgo_mapp_ready('ios') ? 1 : 0) + (petgo_mapp_ready('android') ? 1 : 0);
        $rows[] = array('icon' => 'bell', 'title' => 'Приложение My PetGo', 'sub' => $on === 2 ? 'Уведомления на iPhone и Android включены' : 'Уведомления клиентам в App Store и Google Play', 'url' => petgo_page_url(array('pg' => 'mobileapp')));
    }
    return $rows;
});
add_filter('petgo_app_title', function ($t, $pg) {
    return $pg === 'mobileapp' ? 'My PetGo' : $t;
}, 10, 2);
add_filter('petgo_app_page', function ($done, $pg) {
    if ($done || $pg !== 'mobileapp') {
        return $done;
    }
    petgo_mapp_page();
    return true;
}, 10, 2);

function petgo_mapp_counts() {
    global $wpdb;
    $n = array('ios' => 0, 'android' => 0, 'users' => 0);
    $u = array();
    foreach ((array) $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'petgo_app_tok'") as $r) {
        $j = json_decode((string) $r->meta_value, true);
        if (is_array($j) && isset($j['p'], $n[$j['p']])) {
            $n[$j['p']]++;
            $u[(int) $r->user_id] = 1;
        }
    }
    $n['users'] = count($u);
    return $n;
}

function petgo_mapp_page() {
    if (!petgo_can('admin')) {
        echo '<p class="mu">' . esc_html(petgo_t('err_perm')) . '</p>';
        return;
    }
    $c  = petgo_mapp_cfg();
    $n  = petgo_mapp_counts();
    $sa = petgo_mapp_fcm_sa();
    $ok = function ($b, $yes, $no) {
        return '<span class="pgc-badge ' . ($b ? 'ok' : 'late') . '">' . esc_html($b ? $yes : $no) . '</span>';
    };
    $f = function ($name, $label, $val, $ph = '', $attr = '') {
        return '<label class="pgf-row"><span>' . esc_html($label) . '</span><input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '" placeholder="' . esc_attr($ph) . '" autocomplete="off" spellcheck="false"' . $attr . '></label>';
    };
    echo '<style>.pgap .pgc-card{display:flex;flex-direction:column;gap:10px;margin-bottom:14px}.pgap .pgc-card>b{font-size:17px}.pgap .pgf-list{margin:0}.pgap textarea{width:100%;min-height:92px;padding:10px 12px;border:0;border-radius:12px;background:var(--so);color:inherit;font:13px/1.4 ui-monospace,Menlo,monospace;resize:vertical}.pgap ol{margin:0;padding-left:20px;color:var(--mu);font-size:14px;line-height:1.55}.pgap-n{display:flex;gap:10px}.pgap-n div{flex:1;padding:12px;border-radius:14px;background:var(--so)}.pgap-n b{display:block;font-size:24px}.pgap-n small{color:var(--mu)}.pgap-st{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.pgap-save{position:sticky;bottom:calc(var(--sab) + 12px);z-index:5}</style>';
    echo '<div class="pgap">';
    echo '<div class="pgc-card"><b>Клиенты в приложении</b><div class="pgap-n"><div><b>' . (int) $n['users'] . '</b><small>клиентов с уведомлениями</small></div><div><b>' . (int) $n['ios'] . '</b><small>iPhone</small></div><div><b>' . (int) $n['android'] . '</b><small>Android</small></div></div>';
    echo '<div class="pgap-st">' . $ok(petgo_mapp_ready('ios'), 'iPhone: уведомления включены', 'iPhone: ключ Apple не введён') . $ok((bool) $sa, 'Android: уведомления включены', 'Android: ключ Firebase не введён') . '</div>';
    echo '<p class="mu">Клиент получает уведомление, когда няня у двери (нужен QR-код), заказ подтверждён, прогулка началась и закончилась, и когда менеджер ответил в чате.</p></div>';
    if (!petgo_mapp_h2()) {
        echo '<div class="pgc-card"><b>Хостинг: нет HTTP/2</b><p class="mu">Для уведомлений на iPhone сервер Apple требует HTTP/2, а PHP на этом хостинге его не поддерживает (cURL без HTTP/2). Попросите хостинг включить HTTP/2 для cURL — Android работает и без этого.</p></div>';
    }
    echo petgo_form_open('app_save');
    echo '<div class="pgc-card"><b>Android — Firebase</b><ol><li>console.firebase.google.com → проект PetGo → «Настройки проекта» → «Сервисные аккаунты».</li><li>«Создать закрытый ключ» — скачается файл .json.</li><li>Откройте его, скопируйте всё содержимое и вставьте сюда.</li></ol>';
    echo '<textarea name="fcm" placeholder="' . esc_attr($sa ? 'Ключ сохранён: ' . $sa['client_email'] . ' — вставьте новый, чтобы заменить' : '{ "type": "service_account", ... }') . '"></textarea></div>';
    echo '<div class="pgc-card"><b>iPhone — Apple</b><ol><li>developer.apple.com → Certificates, IDs &amp; Profiles → Keys → «+» → Apple Push Notifications service (APNs).</li><li>Скачайте файл AuthKey_XXXXXXXXXX.p8 (скачать можно только один раз) и откройте его как текст.</li><li>Скопируйте всё, включая строки BEGIN и END, и вставьте сюда. Key ID — 10 символов в названии ключа, Team ID — в правом верхнем углу кабинета разработчика.</li></ol>';
    echo '<textarea name="apns_key" placeholder="' . esc_attr($c['apns_key'] !== '' ? 'Ключ сохранён — вставьте новый, чтобы заменить' : '-----BEGIN PRIVATE KEY----- …') . '"></textarea><div class="pgf-list">';
    echo $f('apns_kid', 'Key ID', $c['apns_kid'], '10 символов', ' maxlength="10"');
    echo $f('apns_team', 'Team ID', $c['apns_team'], '10 символов', ' maxlength="10"');
    echo $f('bundle', 'ID приложения', $c['bundle'], 'kz.petgo.my', ' maxlength="100"');
    echo '</div><label class="pgc-ck"><input type="checkbox" name="sandbox" value="1"' . checked($c['sandbox'], '1', false) . '> <span>Тестовый сервер Apple (только для сборок из Xcode; TestFlight и App Store — без галочки)</span></label></div>';
    echo '<div class="pgap-save"><button type="submit" class="pgc-btn wide lg">Сохранить</button></div></form>';
    echo petgo_form_open('app_test');
    echo '<div class="pgc-card"><b>Проверить уведомление</b><p class="mu">Клиент должен войти в приложение и разрешить уведомления.</p><div class="pgf-list">' . $f('who', 'Клиент', '', 'логин или телефон', ' required') . '</div><button type="submit" class="pgc-btn sec">Отправить тестовое уведомление</button></div></form>';
    echo '</div>';
}

add_action('admin_post_petgo_app_save', function () {
    petgo_guard('admin');
    $in  = wp_unslash($_POST);
    $c   = petgo_mapp_cfg();
    $bad = array();
    $fcm = isset($in['fcm']) ? trim((string) $in['fcm']) : '';
    if ($fcm !== '') {
        $j = json_decode($fcm, true);
        if (is_array($j) && !empty($j['client_email']) && !empty($j['private_key']) && !empty($j['project_id']) && openssl_pkey_get_private($j['private_key'])) {
            $c['fcm'] = wp_json_encode(array_intersect_key($j, array_flip(array('type', 'project_id', 'private_key_id', 'private_key', 'client_email', 'client_id', 'token_uri'))));
        } else {
            $bad[] = 'ключ Firebase (нужен весь файл .json сервисного аккаунта)';
        }
    }
    $p8 = isset($in['apns_key']) ? trim((string) $in['apns_key']) : '';
    if ($p8 !== '') {
        if (strpos($p8, 'BEGIN PRIVATE KEY') !== false && openssl_pkey_get_private($p8)) {
            $c['apns_key'] = $p8;
        } else {
            $bad[] = 'ключ Apple (.p8 целиком, со строками BEGIN и END)';
        }
    }
    foreach (array('apns_kid', 'apns_team') as $k) {
        $v = isset($in[$k]) ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $in[$k])) : '';
        if ($v !== '' && strlen($v) !== 10) {
            $bad[] = $k === 'apns_kid' ? 'Key ID (10 символов)' : 'Team ID (10 символов)';
            continue;
        }
        $c[$k] = $v;
    }
    $b           = isset($in['bundle']) ? preg_replace('/[^A-Za-z0-9.\-]/', '', (string) $in['bundle']) : '';
    $c['bundle'] = $b !== '' ? $b : 'kz.petgo.my';
    $c['sandbox'] = !empty($in['sandbox']) ? '1' : '0';
    update_option('petgo_mapp', $c, false);
    delete_transient('petgo_apns_jwt_' . md5($c['apns_kid'] . $c['apns_team']));
    petgo_flash($bad ? 'Сохранено, но проверьте: ' . implode('; ', $bad) : 'Сохранено', !$bad);
    petgo_back('mobileapp');
});

add_action('admin_post_petgo_app_test', function () {
    petgo_guard('admin');
    $who = isset($_POST['who']) ? trim(sanitize_text_field(wp_unslash($_POST['who']))) : '';
    $u   = $who !== '' ? get_user_by('login', $who) : false;
    if (!$u && $who !== '') {
        $d  = preg_replace('/\D/', '', $who);
        $d  = strlen($d) === 10 ? '7' . $d : (strlen($d) === 11 && $d[0] === '8' ? '7' . substr($d, 1) : $d);
        $us = $d !== '' ? get_users(array('meta_key' => 'petgo_phone_d', 'meta_value' => $d, 'number' => 1)) : array();
        $u  = $us ? $us[0] : false;
    }
    if (!$u) {
        petgo_flash('Клиент не найден', false);
        petgo_back('mobileapp');
    }
    $toks = petgo_mapp_tokens($u->ID);
    if (!$toks) {
        petgo_flash($u->display_name . ' ещё не включил уведомления в приложении', false);
        petgo_back('mobileapp');
    }
    $res = array();
    foreach ($toks as $t) {
        $res[] = ($t['p'] === 'ios' ? 'iPhone' : 'Android') . ': ' . petgo_mapp_send($t, array('t' => 'PetGo', 'b' => 'Проверка уведомлений: всё работает 🐾', 'go' => '', 'tag' => 'test'));
    }
    $good = strpos(implode(' ', $res), ': ok') !== false;
    petgo_flash(($good ? 'Отправлено. ' : 'Не отправлено. ') . str_replace(array(': ok', ': off', ': err', ': gone'), array(' — доставлено', ' — ключ не введён', ' — не принято: проверьте ключ, Key ID и Team ID', ' — телефон больше не принимает, клиенту нужно открыть приложение'), implode(', ', $res)), $good);
    petgo_back('mobileapp');
});

/* ============================================================
   ПОЛИТИКА КОНФИДЕНЦИАЛЬНОСТИ (нужна App Store и Google Play) — страница сайта /privacy/
   ============================================================ */
add_filter('petgo_site_pages', function ($p) {
    $p['privacy'] = array('slug' => 'privacy', 'name' => petgo_site_l('Политика конфиденциальности'), 'title' => petgo_site_l('Политика конфиденциальности'), 'hide' => true, 'noindex' => false);
    return $p;
});
add_filter('petgo_site_page_html', function ($h, $page) {
    return $page === 'privacy' ? petgo_mapp_privacy_html() : $h;
}, 10, 2);

function petgo_mapp_privacy_html() {
    $ct   = function_exists('petgo_ct_cfg') ? petgo_ct_cfg() : array('name' => '', 'idt' => 'ИИН', 'id' => '', 'addr' => '', 'phone' => '');
    $who  = trim($ct['name']) !== '' ? $ct['name'] . ($ct['id'] !== '' ? ' (' . $ct['idt'] . ' ' . $ct['id'] . ')' : '') : 'PetGo';
    $addr = trim($ct['addr']);
    $tel  = function_exists('petgo_site_phone_fmt') ? petgo_site_phone_fmt() : '';
    $wa   = function_exists('petgo_site_wa') ? petgo_site_wa() : '';
    $site = home_url('/');
    $s = array(
        array('Кто обрабатывает данные', array(
            'Оператор персональных данных — ' . $who . ($addr !== '' ? ', адрес: ' . $addr : '') . '. Сервис PetGo (сайт ' . $site . ' и приложение My PetGo) помогает заказать выгул собак и визиты к питомцам в Алматы.',
            'Мы обрабатываем данные по Закону Республики Казахстан «О персональных данных и их защите» и храним их на серверах, к которым есть доступ только у сотрудников PetGo.',
        )),
        array('Какие данные мы собираем', array(
            'Имя, номер телефона, логин и пароль (пароль хранится только в зашифрованном виде) — чтобы создать кабинет и связаться с вами.',
            'Данные питомца: кличка, вид, порода, возраст, особенности здоровья и характера, фото, адрес, куда приходит няня, — чтобы няня правильно выполнила заказ.',
            'Заказы, отчёты нянь, фото и маршрут прогулки (местоположение няни во время заказа), сообщения в чате с менеджером, отзывы.',
            'Договор: ФИО, телефон, дата подписания; при подписании через eGov — электронная подпись (сертификат НУЦ РК с ИИН), при подписании в кабинете — дата, время, IP-адрес и браузер.',
            'Технические данные: IP-адрес, тип браузера или телефона, токен для уведомлений (если вы их разрешили). Местоположение вашего телефона приложение не запрашивает.',
        )),
        array('Зачем', array(
            'Чтобы принимать и выполнять заказы, показывать вам прогулку на карте, фото и отчёты, связываться с вами, отправлять уведомления о заказе, заключать и хранить договор, защищать сайт от взлома.',
            'Мы не продаём данные и не передаём их для рекламы. В приложении My PetGo нет рекламы и рекламных счётчиков.',
        )),
        array('Кому передаём', array(
            'Няне, которая выполняет ваш заказ, — только то, что нужно для заказа (имя, адрес, данные питомца, телефон).',
            'Сервисам, без которых приложение не работает: хостинг сайта; Apple и Google — чтобы доставлять уведомления; SIGEX (sigex.kz) — если вы подписываете договор через eGov; карты OpenStreetMap — показывают маршрут прогулки.',
            'Государственным органам — только когда этого требует закон.',
        )),
        array('Сколько храним', array(
            'Данные кабинета — пока он существует. Историю заказов и подписанные договоры храним столько, сколько требует закон для бухгалтерии и договоров (до 5 лет), даже после удаления кабинета.',
        )),
        array('Ваши права и удаление кабинета', array(
            'Вы можете посмотреть и изменить свои данные в кабинете («Аккаунт»), отказаться от уведомлений в настройках телефона и удалить кабинет.',
            'Удалить кабинет: приложение My PetGo или ' . petgo_site_url('account') . ' → «Аккаунт» → «Удалить кабинет» → пароль → «Удалить навсегда». Вход, профиль, фото профиля, переписка и уведомления удаляются сразу; питомцы отвязываются от кабинета.',
            'Если нет доступа к кабинету — напишите нам' . ($wa !== '' ? ' в WhatsApp: wa.me/' . $wa : '') . ($tel !== '' ? ' или позвоните: ' . $tel : '') . ', и мы удалим данные в течение 30 дней.',
        )),
        array('Дети', array('Сервис предназначен для взрослых. Мы не собираем данные детей младше 16 лет намеренно.')),
        array('Изменения', array('Если политика изменится, новая версия появится на этой странице. Дата обновления — ниже.')),
    );
    $h = '<section class="pg-sec pg-sec-tight"><div class="pg-wrap pg-narrow"><div class="pg-legal">';
    $h .= '<h1 class="pg-h">Политика конфиденциальности</h1><p class="pg-sub">PetGo и приложение My PetGo</p>';
    foreach ($s as $b) {
        $h .= '<h2>' . esc_html($b[0]) . '</h2>';
        foreach ($b[1] as $p) {
            $h .= '<p>' . esc_html($p) . '</p>';
        }
    }
    $h .= '<p class="pg-legal-d">' . esc_html('Обновлено: ' . wp_date('d.m.Y', (int) get_option('petgo_privacy_ts', time()))) . '</p>';
    $h .= '</div></div></section>';
    $h .= '<style>.pg .pg-legal{padding:8px 0 40px;color:var(--green)}.pg .pg-legal h1{margin:0 0 6px}.pg .pg-legal h2{margin:28px 0 8px;font-size:20px;letter-spacing:-.02em}.pg .pg-legal p{margin:0 0 10px;font-size:16px;line-height:1.55;opacity:.88}.pg .pg-legal .pg-sub{opacity:.7}.pg .pg-legal-d{margin-top:24px!important;font-size:14px!important;opacity:.6!important}</style>';
    return $h;
}

/* ссылка на политику — дата первой публикации страницы */
add_action('init', function () {
    if (!get_option('petgo_privacy_ts')) {
        update_option('petgo_privacy_ts', time(), false);
    }
});
