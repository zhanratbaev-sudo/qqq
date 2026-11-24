<?php
/**
 * Шаблон админ-панели: только то, что нужно приложению (без темы WordPress). Переменная: $config.
 *
 * @package StudyHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!doctype html>
<html lang="ru" class="sh-auto">
<head>
<?php echo studyhub_head_tags( 'panel' ); // phpcs:ignore ?>
<title><?php echo esc_html( StudyHub_Settings::brand() ); ?> · Панель</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?php echo esc_url( studyhub_asset_url( 'panel.css' ) ); ?>">
</head>
<body class="panel-body">
<div id="panel"><div class="boot"><div class="boot-logo"></div></div></div>
<noscript><p style="padding:24px">Включите JavaScript.</p></noscript>
<script>window.STUDYHUB_PANEL = <?php echo wp_json_encode( $config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?>;</script>
<script src="<?php echo esc_url( studyhub_asset_url( 'ui.js' ) ); ?>"></script>
<script src="<?php echo esc_url( studyhub_asset_url( 'panel.js' ) ); ?>" defer></script>
</body>
</html>
