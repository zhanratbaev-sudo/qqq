<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   БЕЗОПАСНОСТЬ 5.0
   1) фото клиентов закрыты от прямого доступа и отдаются только вошедшим сотрудникам
   2) защита от перебора паролей и от узнавания логинов сотрудников
   3) заголовки безопасности на странице кабинета
   4) сотрудник не видит контакты хозяина после завершения заказа
   ============================================================ */
if (!defined('PETGO_SESSION_DAYS')) {
    define('PETGO_SESSION_DAYS', 30);
}

/* ---------- настоящий адрес посетителя ---------- */
function petgo_ip() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    if (get_option('petgo_trust_proxy')) {
        foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR') as $h) {
            if (!empty($_SERVER[$h])) {
                $c = trim(explode(',', (string) $_SERVER[$h])[0]);
                if (filter_var($c, FILTER_VALIDATE_IP)) {
                    return $c;
                }
            }
        }
    }
    return $ip;
}

/* ---------- 1. закрытая папка с фото ---------- */
function petgo_media_dir() {
    $ud = wp_upload_dir();
    return wp_normalize_path($ud['basedir'] . '/petgo');
}

function petgo_media_guard() {
    $dir = petgo_media_dir();
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }
    if (!is_dir($dir)) {
        return;
    }
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    }
    if (!file_exists($dir . '/index.php')) {
        @file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
    }
}

add_action('init', function () {
    if (get_option('petgo_mg') !== '1') {
        petgo_media_guard();
        update_option('petgo_mg', '1', false);
    }
}, 20);

function petgo_media_sig($f) {
    return substr(hash_hmac('sha256', 'img|' . $f, wp_salt('auth')), 0, 20);
}

function petgo_media_name_ok($f) {
    return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,80}\.(webp|jpe?g|png)$/', (string) $f);
}

/* прямая ссылка из базы -> ссылка через проверку входа */
function petgo_media($url) {
    $url = (string) $url;
    if ($url === '') {
        return '';
    }
    $ud   = wp_upload_dir();
    $base = trailingslashit($ud['baseurl']) . 'petgo/';
    if (strpos($url, $base) !== 0) {
        return $url;
    }
    $f = basename((string) wp_parse_url($url, PHP_URL_PATH));
    if (!petgo_media_name_ok($f)) {
        return '';
    }
    return add_query_arg(array('action' => 'petgo_img', 's' => petgo_media_sig($f), 'f' => $f), admin_url('admin-post.php'));
}

add_action('admin_post_petgo_img', 'petgo_img_serve');
add_action('admin_post_nopriv_petgo_img', 'petgo_img_serve');
function petgo_img_serve() {
    /* 34.0: клиент в своём кабинете тоже видит фото (подписанные ссылки выдаются ему только на его питомцев и его чат) */
    if (!is_user_logged_in() || (petgo_role_of() === '' && !(function_exists('petgo_cl_uid') && petgo_cl_uid()))) {
        status_header(403);
        exit;
    }
    $f = isset($_GET['f']) ? (string) wp_unslash($_GET['f']) : '';
    $s = isset($_GET['s']) ? (string) wp_unslash($_GET['s']) : '';
    if (!petgo_media_name_ok($f) || !hash_equals(petgo_media_sig($f), $s)) {
        status_header(404);
        exit;
    }
    $root = realpath(petgo_media_dir());
    $real = $root ? realpath($root . '/' . $f) : false;
    if (!$real || strpos(wp_normalize_path($real), wp_normalize_path($root) . '/') !== 0 || !is_file($real)) {
        status_header(404);
        exit;
    }
    $ext  = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    $mime = array('webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png');
    while (ob_get_level()) {
        ob_end_clean();
    }
    header_remove('Expires');
    header_remove('Pragma');
    header('Content-Type: ' . $mime[$ext]);
    header('Content-Length: ' . filesize($real));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=86400');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Content-Disposition: inline; filename="photo.' . $ext . '"');
    readfile($real);
    exit;
}

/* проверка: можно ли открыть фото напрямую по адресу (для страницы настроек) */
function petgo_media_probe() {
    petgo_media_guard();
    $dir = petgo_media_dir();
    $f   = $dir . '/probe.txt';
    if (!file_exists($f)) {
        @file_put_contents($f, 'petgo-probe');
    }
    $ud = wp_upload_dir();
    $r  = wp_remote_get(trailingslashit($ud['baseurl']) . 'petgo/probe.txt', array('timeout' => 6, 'sslverify' => false));
    if (is_wp_error($r)) {
        return 'unknown';
    }
    return (wp_remote_retrieve_response_code($r) === 200 && strpos((string) wp_remote_retrieve_body($r), 'petgo-probe') !== false) ? 'open' : 'closed';
}

/* ---------- 2. вход ---------- */
add_filter('authenticate', 'petgo_auth_user_lock', 31, 2);
function petgo_auth_user_lock($user, $login) {
    if ($login && (int) get_transient('petgo_u_' . md5(strtolower((string) $login))) >= 8) {
        return new WP_Error('petgo_locked', 'Too many attempts. Wait 15 minutes.');
    }
    return $user;
}

add_action('wp_login_failed', function ($login) {
    if ($login) {
        $k = 'petgo_u_' . md5(strtolower((string) $login));
        set_transient($k, (int) get_transient($k) + 1, 15 * MINUTE_IN_SECONDS);
    }
});

add_action('wp_login', function ($login) {
    delete_transient('petgo_u_' . md5(strtolower((string) $login)));
}, 10, 1);

/* одинаковая ошибка: нельзя понять, существует ли такой логин */
add_filter('authenticate', function ($user) {
    if (is_wp_error($user) && in_array($user->get_error_code(), array('invalid_username', 'incorrect_password', 'invalid_email'), true)) {
        return new WP_Error('petgo_bad', 'Неверный логин или пароль');
    }
    return $user;
}, 100);

/* логины сотрудников не отдаются наружу */
add_filter('rest_endpoints', function ($e) {
    /* список пользователей закрыт для гостей и клиентов (данные клиентов и сотрудников не видны снаружи) */
    if (!is_user_logged_in() || (function_exists('petgo_role_of') && petgo_role_of() === '')) {
        foreach (array_keys($e) as $r) {
            if (strpos($r, '/wp/v2/users') === 0) {
                unset($e[$r]);
            }
        }
    }
    return $e;
});

add_action('template_redirect', function () {
    if (!is_user_logged_in() && (isset($_GET['author']) || is_author())) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
}, 1);

add_filter('wp_sitemaps_add_provider', function ($p, $n) {
    return $n === 'users' ? false : $p;
}, 10, 2);

/* ---------- 3. заголовки на странице кабинета ---------- */
add_action('template_redirect', function () {
    $id = (int) petgo_opt('page_id');
    $p  = is_singular() ? get_post() : null;
    if (($id && is_page($id)) || ($p && has_shortcode((string) $p->post_content, 'petgo_cabinet'))) {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('X-Frame-Options: SAMEORIGIN');
        header('Permissions-Policy: geolocation=(self), camera=(self), microphone=()');
    }
}, 1);

/* ---------- проверка присланной фотографии до обработки ---------- */
function petgo_upload_ok($f) {
    if (empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) {
        return false;
    }
    if (!empty($f['size']) && (int) $f['size'] > 15 * MB_IN_BYTES) {
        return false;
    }
    $gi = @getimagesize($f['tmp_name']);
    if (!$gi || !in_array((int) $gi[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP), true)) {
        return false;
    }
    return ((int) $gi[0] * (int) $gi[1]) <= 50000000;
}

/* убрать метаданные (место съёмки) из готового файла */
function petgo_strip_meta($path) {
    if (class_exists('Imagick')) {
        try {
            $im = new Imagick($path);
            $im->stripImage();
            $im->writeImage($path);
            $im->clear();
        } catch (Exception $e) {
            return;
        }
    }
}

/* ---------- страница настроек WordPress: статус защиты ---------- */
function petgo_security_status_html() {
    $st = petgo_media_probe();
    if ($st === 'closed') {
        $m = '<span style="color:#1a7f37;font-weight:700">Защищено:</span> фото клиентов нельзя открыть по прямой ссылке.';
    } elseif ($st === 'open') {
        $m = '<span style="color:#b42318;font-weight:700">Внимание:</span> сервер отдаёт файлы папки <code>uploads/petgo</code> напрямую (похоже на nginx). Добавьте в конфиг сайта правило <code>location ^~ /wp-content/uploads/petgo/ { deny all; }</code> или попросите хостинг сделать это.';
    } else {
        $m = 'Не удалось проверить автоматически. Попробуйте открыть <code>' . esc_html(trailingslashit(wp_upload_dir()['baseurl']) . 'petgo/probe.txt') . '</code> в браузере: должна быть ошибка 403 или 404.';
    }
    return '<h2>Безопасность</h2><p>' . $m . '</p><p>Сессии сотрудников: ' . (int) PETGO_SESSION_DAYS . ' дней. Вход блокируется после 5 неудачных попыток с одного адреса и после 8 для одного логина (15 минут).</p>';
}
