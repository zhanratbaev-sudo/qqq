<?php
/**
 * Админка-приложение WebFor по адресу /webfor-admin/ — та же панель, что в WordPress, но в дизайне сайта и на весь экран.
 * Её можно установить на телефон (PWA): manifest и service worker отдаются отсюда же, push-уведомления о чатах
 * приходят через сервис-воркер (ключи и отправка — в плагине WebFor AI Assistant).
 */
defined('ABSPATH') || exit;

function webfor_admin_app_url() {
    return get_option('permalink_structure') ? home_url('/webfor-admin/') : add_query_arg('webfor_app', 'page', home_url('/'));
}

function webfor_app_asset_url($what) {
    return get_option('permalink_structure') ? home_url('/webfor-admin/' . $what) : add_query_arg('webfor_app', $what === 'sw.js' ? 'sw' : 'manifest', home_url('/'));
}

add_action('init', function () {
    add_rewrite_rule('^webfor-admin/?$', 'index.php?webfor_app=page', 'top');
    add_rewrite_rule('^webfor-admin/manifest\.webmanifest$', 'index.php?webfor_app=manifest', 'top');
    add_rewrite_rule('^webfor-admin/sw\.js$', 'index.php?webfor_app=sw', 'top');
    if (get_option('webfor_rewrite_v') !== WEBFOR_VERSION) { // новые адреса после обновления плагина
        flush_rewrite_rules(false);
        update_option('webfor_rewrite_v', WEBFOR_VERSION);
    }
});
/**
 * Запасной путь: если правила адресов не обновились (кэш, другой плагин, сервер), всё равно узнаём /webfor-admin/
 * прямо по адресу запроса. Так админка открывается сразу после установки, без «Настройки → Постоянные ссылки → Сохранить».
 */
add_action('parse_request', function ($wp) {
    if (!empty($wp->query_vars['webfor_app'])) {
        return;
    }
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $base = rtrim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
    if ($base !== '' && strpos($path, $base) === 0) {
        $path = substr($path, strlen($base));
    }
    $path = trim(rawurldecode($path), '/');
    $map = ['webfor-admin' => 'page', 'webfor-admin/manifest.webmanifest' => 'manifest', 'webfor-admin/sw.js' => 'sw'];
    if (isset($map[$path])) {
        $wp->query_vars = ['webfor_app' => $map[$path]];
        $wp->matched_rule = 'webfor-admin';
    }
}, 1);

add_filter('query_vars', function ($v) {
    $v[] = 'webfor_app';
    return $v;
});

add_action('template_redirect', function () {
    $what = get_query_var('webfor_app');
    if (!$what) {
        return;
    }
    nocache_headers();
    if ($what === 'manifest') {
        header('Content-Type: application/manifest+json; charset=utf-8');
        $icons = WEBFOR_URL . 'assets/icons/';
        echo wp_json_encode([
            'name' => 'WebFor — админка', 'short_name' => 'WebFor', 'lang' => 'ru',
            'start_url' => webfor_admin_app_url(), 'scope' => get_option('permalink_structure') ? home_url('/webfor-admin/') : home_url('/'),
            'display' => 'standalone', 'background_color' => '#050707', 'theme_color' => '#050707',
            'icons' => [
                ['src' => $icons . 'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => $icons . 'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => $icons . 'maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($what === 'sw') {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        $icon = WEBFOR_URL . 'assets/icons/icon-192.png';
        echo "/* WebFor админка: push-уведомления о чатах */\n"
            . "self.addEventListener('install', function () { self.skipWaiting(); });\n"
            . "self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });\n"
            . "self.addEventListener('push', function (e) {\n"
            . "  var d = {}; try { d = e.data ? e.data.json() : {}; } catch (err) { d = { title: 'WebFor', body: e.data ? e.data.text() : '' }; }\n"
            . "  // открытая админка обновляется сразу, не дожидаясь своих 10 секунд\n"
            . "  var tell = self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) { list.forEach(function (c) { c.postMessage({ type: 'webfor-refresh' }); }); });\n"
            . "  e.waitUntil(Promise.all([tell, self.registration.showNotification(d.title || 'WebFor', { body: d.body || '', icon: " . wp_json_encode($icon) . ", badge: " . wp_json_encode($icon) . ", tag: d.tag || 'webfor-chat', renotify: true, data: { url: d.url || " . wp_json_encode(webfor_admin_app_url()) . " } })]));\n"
            . "});\n"
            . "self.addEventListener('notificationclick', function (e) {\n"
            . "  e.notification.close();\n"
            . "  var url = e.notification.data && e.notification.data.url;\n"
            . "  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {\n"
            . "    for (var i = 0; i < list.length; i++) { if (list[i].url.indexOf('webfor') >= 0 && 'focus' in list[i]) { list[i].navigate(url); return list[i].focus(); } }\n"
            . "    return self.clients.openWindow(url);\n"
            . "  }));\n"
            . "});\n";
        exit;
    }
    // сама админка — только для администратора
    if (!current_user_can('manage_options')) {
        auth_redirect();
    }
    include WEBFOR_DIR . 'templates/app.php';
    exit;
}, 1); // раньше redirect_canonical, иначе к sw.js и manifest допишется «/»

/** Настройки для JS-панели (общие для админки WordPress и приложения). */
function webfor_admin_config() {
    $cfg = [
        'root' => esc_url_raw(rest_url('webfor/v1/admin')),
        'nonce' => wp_create_nonce('wp_rest'),
        'media' => esc_url_raw(rest_url('wp/v2/media')),
        'app' => webfor_admin_app_url(),
        'version' => WEBFOR_VERSION,
        'ai_version' => defined('WFAI_VERSION') ? WFAI_VERSION : '',
    ];
    if (function_exists('wfai_vapid')) {
        $keys = wfai_vapid();
        $cfg['chats'] = [
            'root' => esc_url_raw(rest_url('webfor-ai/v1/manager')),
            'vapid' => $keys ? $keys['publicKey'] : '',
            'manager' => wfai_manager_name(),
        ];
    }
    return $cfg;
}
