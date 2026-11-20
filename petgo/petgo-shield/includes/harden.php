<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ЗАКРЫТЫЕ СЛАБЫЕ МЕСТА: то, что PetGo не нужно, но через что обычно взламывают сайты на WordPress
   ============================================================ */

/* редактор файлов в админке WordPress выключен: укравший пароль не сможет вписать вредный код в плагины */
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

/* XML-RPC (старый способ входа, через него подбирают пароли сотнями за один запрос) */
add_filter('xmlrpc_enabled', function ($on) {
    return pgs_cfg()['xmlrpc'] === '1' ? false : $on;
});
add_filter('xmlrpc_methods', function ($m) {
    unset($m['pingback.ping'], $m['pingback.extensions.getPingbacks'], $m['system.multicall']);
    return $m;
});
add_filter('wp_headers', function ($h) {
    unset($h['X-Pingback']);
    return $h;
});
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');

/* «пароли приложений» — вход в API мимо обычного входа; PetGo их не использует */
add_filter('wp_is_application_passwords_available', '__return_false');

/* регистрация через стандартную страницу WordPress закрыта (клиенты регистрируются в кабинете PetGo) */
add_filter('pre_option_users_can_register', function () {
    return '0';
});

/* версия WordPress не показывается: по ней подбирают известные уязвимости */
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');

/* заголовки безопасности на всех страницах: сайт, кабинет клиента, приложение, вход */
function pgs_headers() {
    if (headers_sent() || pgs_cfg()['headers'] !== '1') {
        return;
    }
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), geolocation=(self), microphone=(self), payment=(), usb=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    /* сайт нельзя вставить в чужую страницу, нельзя подменить базовый адрес и встроить плагины браузера */
    header("Content-Security-Policy: object-src 'none'; base-uri 'self'; frame-ancestors 'self'" . (is_ssl() ? '; upgrade-insecure-requests' : ''));
    if (is_ssl()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}
add_action('send_headers', 'pgs_headers');
add_action('login_init', 'pgs_headers');
add_action('admin_init', 'pgs_headers');
add_action('plugins_loaded', function () {
    /* admin-ajax и admin-post (вход клиента, заказы, фото) */
    if ((defined('DOING_AJAX') && DOING_AJAX) || (isset($_SERVER['SCRIPT_NAME']) && substr((string) $_SERVER['SCRIPT_NAME'], -14) === 'admin-post.php')) {
        pgs_headers();
    }
}, 3);

/* ---------- в папке загрузок PHP не запускается (если туда всё же попадёт вредный файл) ---------- */
function pgs_uploads_guard($on) {
    $up = wp_upload_dir(null, false);
    if (empty($up['basedir']) || !is_dir($up['basedir'])) {
        return false;
    }
    $f = trailingslashit($up['basedir']) . '.htaccess';
    if (!function_exists('insert_with_markers')) {
        require_once ABSPATH . 'wp-admin/includes/misc.php';
    }
    $rules = $on ? array(
        '# Запуск скриптов в загрузках запрещён (PetGo Защита)',
        '<FilesMatch "\.(?i:php[0-9]?|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp)$">',
        '  <IfModule mod_authz_core.c>',
        '    Require all denied',
        '  </IfModule>',
        '  <IfModule !mod_authz_core.c>',
        '    Order allow,deny',
        '    Deny from all',
        '  </IfModule>',
        '</FilesMatch>',
    ) : array();
    if (!$on && !file_exists($f)) {
        return true;
    }
    $ok = (bool) @insert_with_markers($f, 'PetGo Shield', $rules);
    /* сервер не принял правило (ошибка 500 на картинках) — сразу убираем, чтобы сайт не сломался */
    if ($on && $ok && pgs_uploads_probe('txt') === 500) {
        @insert_with_markers($f, 'PetGo Shield', array());
        update_option('pgs_up_rule', 'refused', false);
        return false;
    }
    update_option('pgs_up_rule', $on ? 'ok' : 'off', false);
    return $ok;
}

/* проверка через сам сайт: txt — открываются ли обычные файлы загрузок; php — запускаются ли скрипты */
function pgs_uploads_probe($type) {
    $up = wp_upload_dir(null, false);
    if (empty($up['basedir']) || !is_dir($up['basedir'])) {
        return 0;
    }
    $name = 'pgs-probe-' . wp_generate_password(10, false, false) . '.' . $type;
    $file = trailingslashit($up['basedir']) . $name;
    $body = $type === 'php' ? '<?php echo "PGS" . "RUN";' : 'PGS-OK';
    if (@file_put_contents($file, $body) === false) {
        return 0;
    }
    $r = wp_remote_get(trailingslashit($up['baseurl']) . $name, array('timeout' => 8, 'sslverify' => false, 'redirection' => 0));
    @unlink($file);
    if (is_wp_error($r)) {
        return 0;
    }
    $code = (int) wp_remote_retrieve_response_code($r);
    if ($type === 'php') {
        return strpos((string) wp_remote_retrieve_body($r), 'PGSRUN') !== false ? 1 : 2;
    }
    return $code;
}

/* раз в день проверяем, что правило на месте (его могли стереть) */
add_action('pgs_daily', function () {
    pgs_uploads_guard(true);
}, 5);
