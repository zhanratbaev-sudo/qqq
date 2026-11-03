<?php
/** Страница админки-приложения /webfor-admin/ (PWA). */
defined('ABSPATH') || exit;
$cfg = webfor_admin_config();
$cfg['sw'] = webfor_app_asset_url('sw.js');
$cfg['logout'] = wp_logout_url(webfor_admin_app_url());
?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#040606">
    <meta name="robots" content="noindex">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="WebFor">
    <title>WebFor — админка</title>
    <link rel="manifest" href="<?php echo esc_url(webfor_app_asset_url('manifest.webmanifest')); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url(WEBFOR_URL . 'assets/icons/apple-180.png'); ?>">
    <link rel="icon" href="<?php echo esc_url(WEBFOR_URL . 'assets/icons/icon-192.png'); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(WEBFOR_URL . 'assets/admin-app.css?ver=' . WEBFOR_VERSION); ?>">
    <style>
        html, body { margin: 0; height: 100%; overflow: hidden; background: #040606; color-scheme: dark; overscroll-behavior: none; }
        body { -webkit-text-size-adjust: 100%; }
    </style>
</head>
<body>
    <div id="wfa-app" class="wfa"><p class="wfa-loading">Загружаем панель…</p></div>
    <script>window.WFADMIN = <?php echo wp_json_encode($cfg); ?>;</script>
    <script src="<?php echo esc_url(WEBFOR_URL . 'assets/admin-app.js?ver=' . WEBFOR_VERSION); ?>"></script>
</body>
</html>
