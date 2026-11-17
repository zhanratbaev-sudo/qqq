<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ЗАЩИТА САЙТА (33.11)
   1) заголовки безопасности на всех страницах сайта PetGo: сайт нельзя встроить в чужую страницу,
      браузер не «угадывает» тип файлов, адрес страницы не утекает на чужие сайты, HTTPS закрепляется
   2) XML-RPC выключен (через него чаще всего подбирают пароли), версия WordPress скрыта
   3) пароли приложений выключены, редактор файлов тем и плагинов в WordPress выключен
   4) заявки и регистрация питомца принимаются только с этого же сайта
   5) PHP-файлы в папке загрузок не запускаются (если сервер это поддерживает)
   Уже было раньше: фото клиентов закрыты, перебор паролей блокируется, логины сотрудников скрыты.
   ============================================================ */

/* ---------- 1. заголовки ---------- */
function petgo_guard_headers($site = true) {
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    /* микрофон — только для голосовых сообщений помощнику на этом сайте */
    header('Permissions-Policy: geolocation=(), camera=(), microphone=(self), payment=(), usb=(), interest-cohort=()');
    /* не мешает встроенным скриптам и счётчикам, но запрещает встраивание сайта, чужой <base> и плагины */
    header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self' https://wa.me https://api.whatsapp.com");
    if (is_ssl()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

add_action('template_redirect', function () {
    if (function_exists('petgo_site_page_now') && petgo_site_page_now() !== '') {
        petgo_guard_headers();
    }
}, 2);

/* ответы admin-ajax и REST тоже не «угадываются» браузером */
add_action('admin_init', function () {
    if (wp_doing_ajax() && !headers_sent()) {
        header('X-Content-Type-Options: nosniff');
    }
});

/* ---------- 2. XML-RPC и следы WordPress ---------- */
add_filter('xmlrpc_enabled', '__return_false');
add_filter('xmlrpc_methods', function () {
    return array();
});
add_filter('wp_headers', function ($h) {
    unset($h['X-Pingback']);
    return $h;
});
add_filter('pings_open', '__return_false', 20);
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
add_filter('the_generator', '__return_empty_string');
/* прямой запрос к xmlrpc.php — сразу 403, без загрузки лишнего */
add_action('init', function () {
    if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
        status_header(403);
        exit;
    }
}, 0);

/* ---------- 3. пароли приложений и редактор файлов ---------- */
add_filter('wp_is_application_passwords_available', '__return_false');
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

/* имена авторов в oEmbed не показываем */
add_filter('oembed_response_data', function ($d) {
    unset($d['author_name'], $d['author_url']);
    return $d;
});

/* ---------- 4. заявки только с этого сайта ---------- */
function petgo_guard_same_origin() {
    $src = '';
    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        $src = (string) $_SERVER['HTTP_ORIGIN'];
    } elseif (!empty($_SERVER['HTTP_REFERER'])) {
        $src = (string) $_SERVER['HTTP_REFERER'];
    }
    /* браузер не прислал ни Origin, ни Referer (бывает в приватном режиме) — пропускаем: есть метка формы и лимиты */
    if ($src === '' || $src === 'null') {
        return true;
    }
    $h = strtolower((string) wp_parse_url($src, PHP_URL_HOST));
    $ok = array(strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)), strtolower((string) wp_parse_url(site_url(), PHP_URL_HOST)));
    if (!empty($_SERVER['HTTP_HOST'])) {
        $ok[] = strtolower(preg_replace('/:\d+$/', '', (string) $_SERVER['HTTP_HOST']));
    }
    $strip = function ($x) {
        return preg_replace('/^www\./', '', $x);
    };
    return in_array($strip($h), array_map($strip, $ok), true);
}

/* ---------- 5. PHP в загрузках не запускается ---------- */
add_action('admin_init', function () {
    if (get_option('petgo_up_guard') === '1') {
        return;
    }
    update_option('petgo_up_guard', '1', false);
    $ud = wp_upload_dir();
    $ht = trailingslashit($ud['basedir']) . '.htaccess';
    /* чужой .htaccess не трогаем */
    if (file_exists($ht)) {
        return;
    }
    @file_put_contents($ht, "# PetGo: запрет запуска скриптов в папке загрузок\n<FilesMatch \"\\.(php|phtml|php[0-9]|phar|pl|py|cgi|sh)$\">\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n</FilesMatch>\nOptions -Indexes\n");
});
