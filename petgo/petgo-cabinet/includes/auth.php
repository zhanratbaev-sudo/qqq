<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ВХОД 27.0: Apple, Google и одноразовый код на почту.
   Войти может только существующий сотрудник, у которого в кабинете указана та же почта.
   Новые аккаунты так не создаются.
   ============================================================ */

function petgo_oauth_cfg($p) {
    $o = (array) get_option('petgo_oauth', array());
    $d = array(
        'google' => array('id' => '', 'secret' => ''),
        'apple'  => array('id' => '', 'team' => '', 'kid' => '', 'key' => ''),
    );
    return isset($o[$p]) && is_array($o[$p]) ? array_merge($d[$p], $o[$p]) : $d[$p];
}

function petgo_oauth_on($p) {
    $c = petgo_oauth_cfg($p);
    if ($p === 'google') {
        return $c['id'] !== '' && $c['secret'] !== '';
    }
    return $c['id'] !== '' && $c['team'] !== '' && $c['kid'] !== '' && $c['key'] !== '';
}

function petgo_oauth_redirect($p) {
    return rest_url('petgo/v1/oauth/' . $p);
}

/* сотрудник по почте: только с ролью кабинета и не отключённый */
function petgo_staff_by_email($email) {
    $email = sanitize_email((string) $email);
    if ($email === '' || !is_email($email)) {
        return false;
    }
    $u = get_user_by('email', $email);
    if (!$u || petgo_role_of($u) === '' || get_user_meta($u->ID, 'petgo_disabled', true)) {
        return false;
    }
    return $u;
}

function petgo_login_as($u) {
    wp_clear_auth_cookie();
    wp_set_current_user($u->ID);
    wp_set_auth_cookie($u->ID, true, is_ssl());
    do_action('wp_login', $u->user_login, $u);
}

/* ошибки входа показываются на странице входа: ?pgl=код */
function petgo_login_err_url($code) {
    return add_query_arg('pgl', $code, petgo_page_url());
}

/* petgo_b64u() — в includes/pwa.php */
function petgo_b64u_dec($s) {
    $s = strtr((string) $s, '-_', '+/');
    return (string) base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4));
}

/* id_token получен напрямую от Google/Apple по https, поэтому достаточно проверить поля */
function petgo_jwt_payload($t) {
    $p = explode('.', (string) $t);
    if (count($p) !== 3) {
        return array();
    }
    $j = json_decode(petgo_b64u_dec($p[1]), true);
    return is_array($j) ? $j : array();
}

/* подпись ES256 для client_secret Apple: openssl выдаёт DER, Apple ждёт R||S по 32 байта */
function petgo_es256($data, $pem) {
    $k = openssl_pkey_get_private($pem);
    if (!$k || !openssl_sign($data, $der, $k, OPENSSL_ALGO_SHA256)) {
        return '';
    }
    $o = 2;
    if (ord($der[1]) & 0x80) {
        $o += ord($der[1]) & 0x7f;
    }
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $len = ord($der[$o + 1]);
        $v   = ltrim(substr($der, $o + 2, $len), "\0");
        $out .= str_pad($v, 32, "\0", STR_PAD_LEFT);
        $o   += 2 + $len;
    }
    return $out;
}

function petgo_apple_secret() {
    $c = petgo_oauth_cfg('apple');
    $h = petgo_b64u(wp_json_encode(array('alg' => 'ES256', 'kid' => $c['kid'])));
    $b = petgo_b64u(wp_json_encode(array('iss' => $c['team'], 'iat' => time(), 'exp' => time() + 300, 'aud' => 'https://appleid.apple.com', 'sub' => $c['id'])));
    $s = petgo_es256($h . '.' . $b, $c['key']);
    return $s === '' ? '' : $h . '.' . $b . '.' . petgo_b64u($s);
}

add_action('rest_api_init', function () {
    register_rest_route('petgo/v1', '/oauth/(?P<p>google|apple|start)', array(
        'methods'             => array('GET', 'POST'),
        'callback'            => 'petgo_oauth_route',
        'permission_callback' => '__return_true',
    ));
});

function petgo_oauth_route($req) {
    $p = (string) $req['p'];
    if ($p === 'start') {
        petgo_oauth_start(sanitize_key((string) $req->get_param('via')));
    }
    petgo_oauth_back($p, $req);
    exit;
}

function petgo_oauth_start($p) {
    if (!in_array($p, array('google', 'apple'), true) || !petgo_oauth_on($p)) {
        wp_safe_redirect(petgo_login_err_url('off'));
        exit;
    }
    $state = wp_generate_password(32, false, false);
    set_transient('petgo_os_' . $state, $p, 10 * MINUTE_IN_SECONDS);
    /* Apple возвращает POST с другого сайта, поэтому cookie нужен SameSite=None */
    setcookie('petgo_os', $state, array('expires' => time() + 600, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => is_ssl() ? 'None' : 'Lax'));
    $c = petgo_oauth_cfg($p);
    if ($p === 'google') {
        $u = add_query_arg(array(
            'client_id' => $c['id'], 'redirect_uri' => rawurlencode(petgo_oauth_redirect('google')), 'response_type' => 'code',
            'scope' => 'openid%20email', 'state' => $state, 'prompt' => 'select_account',
        ), 'https://accounts.google.com/o/oauth2/v2/auth');
    } else {
        $u = add_query_arg(array(
            'client_id' => $c['id'], 'redirect_uri' => rawurlencode(petgo_oauth_redirect('apple')), 'response_type' => 'code',
            'scope' => 'email', 'response_mode' => 'form_post', 'state' => $state,
        ), 'https://appleid.apple.com/auth/authorize');
    }
    wp_redirect($u);
    exit;
}

function petgo_oauth_back($p, $req) {
    $state = (string) $req->get_param('state');
    $ck    = isset($_COOKIE['petgo_os']) ? (string) $_COOKIE['petgo_os'] : '';
    $saved = $state !== '' ? get_transient('petgo_os_' . $state) : false;
    if ($state !== '') {
        delete_transient('petgo_os_' . $state);
    }
    setcookie('petgo_os', '', array('expires' => time() - 3600, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => is_ssl() ? 'None' : 'Lax'));
    if ($saved !== $p || $ck === '' || !hash_equals($ck, $state)) {
        wp_safe_redirect(petgo_login_err_url('state'));
        exit;
    }
    $code = (string) $req->get_param('code');
    if ($code === '' || !petgo_oauth_on($p)) {
        wp_safe_redirect(petgo_login_err_url('cancel'));
        exit;
    }
    $c = petgo_oauth_cfg($p);
    if ($p === 'google') {
        $r = wp_remote_post('https://oauth2.googleapis.com/token', array('timeout' => 15, 'body' => array(
            'code' => $code, 'client_id' => $c['id'], 'client_secret' => $c['secret'], 'redirect_uri' => petgo_oauth_redirect('google'), 'grant_type' => 'authorization_code',
        )));
    } else {
        $sec = petgo_apple_secret();
        if ($sec === '') {
            wp_safe_redirect(petgo_login_err_url('cfg'));
            exit;
        }
        $r = wp_remote_post('https://appleid.apple.com/auth/token', array('timeout' => 15, 'body' => array(
            'code' => $code, 'client_id' => $c['id'], 'client_secret' => $sec, 'redirect_uri' => petgo_oauth_redirect('apple'), 'grant_type' => 'authorization_code',
        )));
    }
    $j  = is_wp_error($r) ? array() : json_decode((string) wp_remote_retrieve_body($r), true);
    $pl = petgo_jwt_payload(is_array($j) && isset($j['id_token']) ? $j['id_token'] : '');
    $iss_ok = $p === 'google' ? in_array(isset($pl['iss']) ? $pl['iss'] : '', array('https://accounts.google.com', 'accounts.google.com'), true) : (isset($pl['iss']) && $pl['iss'] === 'https://appleid.apple.com');
    $aud    = isset($pl['aud']) ? (array) $pl['aud'] : array();
    $ver    = isset($pl['email_verified']) && ($pl['email_verified'] === true || $pl['email_verified'] === 'true');
    if (!$iss_ok || !in_array($c['id'], $aud, true) || (int) (isset($pl['exp']) ? $pl['exp'] : 0) < time() || !$ver || empty($pl['email'])) {
        wp_safe_redirect(petgo_login_err_url('cfg'));
        exit;
    }
    $u = petgo_staff_by_email($pl['email']);
    if (!$u) {
        wp_safe_redirect(petgo_login_err_url('nouser'));
        exit;
    }
    petgo_login_as($u);
    wp_safe_redirect(petgo_page_url());
    exit;
}

/* ---------- одноразовый код на почту ---------- */
add_action('wp_ajax_nopriv_petgo_mcode_send', 'petgo_mcode_send');
add_action('wp_ajax_petgo_mcode_send', 'petgo_mcode_send');
function petgo_mcode_send() {
    check_ajax_referer('petgo_mcode', 'n');
    $email = strtolower(sanitize_email(wp_unslash(isset($_POST['email']) ? $_POST['email'] : '')));
    if ($email === '' || !is_email($email)) {
        wp_send_json(array('ok' => false, 'm' => petgo_t('mc_bad_email')));
    }
    $ik = 'petgo_mci_' . md5(petgo_ip());
    $ek = 'petgo_mce_' . md5($email);
    if ((int) get_transient($ik) >= 10 || get_transient($ek)) {
        wp_send_json(array('ok' => false, 'm' => petgo_t('mc_wait')));
    }
    set_transient($ik, (int) get_transient($ik) + 1, HOUR_IN_SECONDS);
    set_transient($ek, 1, MINUTE_IN_SECONDS);
    $u = petgo_staff_by_email($email);
    if ($u) {
        $code = (string) wp_rand(100000, 999999);
        update_user_meta($u->ID, 'petgo_mcode', array('h' => wp_hash_password($code), 'exp' => time() + 10 * MINUTE_IN_SECONDS, 'n' => 0));
        $app  = petgo_app_name();
        $body = sprintf(petgo_t('mc_mail'), $code, $app) . "\n\n" . petgo_page_url();
        wp_mail($u->user_email, $code . ' — ' . sprintf(petgo_t('mc_subj'), $app), $body);
    }
    /* ответ одинаковый: нельзя узнать, есть ли такая почта */
    wp_send_json(array('ok' => true));
}

add_action('wp_ajax_nopriv_petgo_mcode_check', 'petgo_mcode_check');
add_action('wp_ajax_petgo_mcode_check', 'petgo_mcode_check');
function petgo_mcode_check() {
    check_ajax_referer('petgo_mcode', 'n');
    $email = strtolower(sanitize_email(wp_unslash(isset($_POST['email']) ? $_POST['email'] : '')));
    $code  = preg_replace('/\D/', '', (string) wp_unslash(isset($_POST['code']) ? $_POST['code'] : ''));
    $ik    = petgo_ip_key();
    if ((int) get_transient($ik) >= 5) {
        wp_send_json(array('ok' => false, 'm' => petgo_t('mc_wait')));
    }
    $u = petgo_staff_by_email($email);
    $m = $u ? get_user_meta($u->ID, 'petgo_mcode', true) : false;
    if (!$u || !is_array($m) || (int) $m['exp'] < time() || (int) $m['n'] >= 5 || strlen($code) !== 6 || !wp_check_password($code, $m['h'], $u->ID)) {
        if ($u && is_array($m)) {
            $m['n'] = (int) $m['n'] + 1;
            update_user_meta($u->ID, 'petgo_mcode', $m);
        }
        set_transient($ik, (int) get_transient($ik) + 1, 15 * MINUTE_IN_SECONDS);
        wp_send_json(array('ok' => false, 'm' => petgo_t('mc_bad')));
    }
    delete_user_meta($u->ID, 'petgo_mcode');
    petgo_login_as($u);
    wp_send_json(array('ok' => true, 'to' => petgo_page_url()));
}

/* ---------- почта сотрудника: проверка перед сохранением ---------- */
function petgo_email_check($email, $uid = 0) {
    $email = sanitize_email((string) $email);
    if ($email === '') {
        return '';
    }
    if (!is_email($email)) {
        return false;
    }
    $o = email_exists($email);
    if ($o && (int) $o !== (int) $uid) {
        return false;
    }
    return $email;
}

/* ---------- настройки в WordPress: ключи Apple и Google ---------- */
function petgo_oauth_settings_save() {
    $g = isset($_POST['oauth']['google']) ? (array) wp_unslash($_POST['oauth']['google']) : array();
    $a = isset($_POST['oauth']['apple']) ? (array) wp_unslash($_POST['oauth']['apple']) : array();
    $old = (array) get_option('petgo_oauth', array());
    $key = isset($a['key']) ? trim((string) $a['key']) : '';
    $sec = isset($g['secret']) ? trim((string) $g['secret']) : '';
    update_option('petgo_oauth', array(
        'google' => array('id' => sanitize_text_field(isset($g['id']) ? $g['id'] : ''), 'secret' => $sec !== '' ? sanitize_text_field($sec) : (isset($old['google']['secret']) ? $old['google']['secret'] : '')),
        'apple'  => array(
            'id' => sanitize_text_field(isset($a['id']) ? $a['id'] : ''), 'team' => sanitize_text_field(isset($a['team']) ? $a['team'] : ''), 'kid' => sanitize_text_field(isset($a['kid']) ? $a['kid'] : ''),
            'key' => $key !== '' ? sanitize_textarea_field($key) : (isset($old['apple']['key']) ? $old['apple']['key'] : ''),
        ),
    ), false);
}

function petgo_oauth_settings_html() {
    $g = petgo_oauth_cfg('google');
    $a = petgo_oauth_cfg('apple');
    $h  = '<tr><th>Вход через Google</th><td><p><input type="text" class="regular-text" name="oauth[google][id]" value="' . esc_attr($g['id']) . '" placeholder="Client ID"></p>';
    $h .= '<p><input type="password" class="regular-text" name="oauth[google][secret]" value="" placeholder="' . ($g['secret'] !== '' ? 'Client secret сохранён — оставьте пустым' : 'Client secret') . '" autocomplete="off"></p>';
    $h .= '<p class="description">Google Cloud Console → APIs &amp; Services → Credentials → OAuth client ID (Web application). Authorized redirect URI:<br><code>' . esc_html(petgo_oauth_redirect('google')) . '</code></p></td></tr>';
    $h .= '<tr><th>Вход через Apple</th><td><p><input type="text" class="regular-text" name="oauth[apple][id]" value="' . esc_attr($a['id']) . '" placeholder="Services ID (например kz.petgo.login)"></p>';
    $h .= '<p><input type="text" name="oauth[apple][team]" value="' . esc_attr($a['team']) . '" placeholder="Team ID"> <input type="text" name="oauth[apple][kid]" value="' . esc_attr($a['kid']) . '" placeholder="Key ID"></p>';
    $h .= '<p><textarea name="oauth[apple][key]" rows="4" class="large-text code" placeholder="' . ($a['key'] !== '' ? 'Ключ .p8 сохранён — оставьте пустым' : '-----BEGIN PRIVATE KEY----- (содержимое файла .p8)') . '"></textarea></p>';
    $h .= '<p class="description">developer.apple.com → Certificates, IDs &amp; Profiles: Services ID с «Sign in with Apple», домен сайта и Return URL:<br><code>' . esc_html(petgo_oauth_redirect('apple')) . '</code><br>Ключ (Keys) с «Sign in with Apple» — вставьте содержимое файла .p8.</p>';
    $h .= '<p class="description">Войти можно, только если почта аккаунта Google/Apple совпадает с почтой сотрудника в кабинете. Если в Apple выбрано «Скрыть e-mail», вход не пройдёт — нужно выбрать «Поделиться e-mail».</p></td></tr>';
    return $h;
}

/* почта сотрудника для показа в формах */
function petgo_real_email($u) {
    return $u && isset($u->user_email) ? (string) $u->user_email : '';
}

/* сотрудникам не отправляем письмо «ваша почта изменена» на старый адрес — почту меняет администратор */
add_filter('send_email_change_email', function ($send, $user) {
    $u = get_userdata(isset($user['ID']) ? (int) $user['ID'] : 0);
    return ($u && petgo_role_of($u) !== '' && petgo_role_of($u) !== 'owner') ? false : $send;
}, 10, 2);
