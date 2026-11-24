<?php
/**
 * Standalone app shell. Variables: $config (array).
 *
 * @package StudyHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$studyhub_brand  = StudyHub_Settings::brand();
$studyhub_accent = StudyHub_Settings::accent();
$studyhub_lang   = ( $config['student'] && 'kk' === $config['student']['lang'] ) ? 'kk' : 'ru';
?><!doctype html>
<html lang="<?php echo esc_attr( $studyhub_lang ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
<title><?php echo esc_html( $studyhub_brand ); ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#F4F2F0" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#121214" media="(prefers-color-scheme: dark)">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $studyhub_brand ); ?>">
<link rel="manifest" href="<?php echo esc_url( StudyHub_App::manifest_url() ); ?>">
<link rel="icon" href="<?php echo esc_url( StudyHub_App::icon_url( 192 ) ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( StudyHub_Settings::get( 'brand_logo' ) ? StudyHub_Settings::get( 'brand_logo' ) : STUDYHUB_URL . 'assets/icons/apple-180.png' ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( STUDYHUB_URL . 'assets/app.css?ver=' . STUDYHUB_VERSION ); ?>">
<style>:root{--accent:<?php echo esc_html( $studyhub_accent ); ?>;}</style>
</head>
<body>
<div id="app" aria-live="polite">
	<div class="boot"><div class="boot-logo"></div></div>
</div>
<noscript><p style="padding:24px;font-family:sans-serif">Включите JavaScript, чтобы пользоваться приложением.</p></noscript>
<script>window.STUDYHUB = <?php echo wp_json_encode( $config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?>;</script>
<script src="<?php echo esc_url( STUDYHUB_URL . 'assets/app.js?ver=' . STUDYHUB_VERSION ); ?>" defer></script>
</body>
</html>
