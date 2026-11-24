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
$studyhub_lang   = ( $config['student'] && 'kk' === $config['student']['lang'] ) ? 'kk' : 'ru';
?><!doctype html>
<html lang="<?php echo esc_attr( $studyhub_lang ); ?>">
<head>
<?php echo studyhub_head_tags( 'app' ); // phpcs:ignore ?>
<title><?php echo esc_html( $studyhub_brand ); ?></title>
<meta name="robots" content="noindex, nofollow">
</head>
<body>
<div id="app" aria-live="polite">
	<div class="boot"><div class="boot-logo"></div></div>
</div>
<noscript><p style="padding:24px;font-family:sans-serif">Включите JavaScript, чтобы пользоваться приложением.</p></noscript>
<script>window.STUDYHUB = <?php echo wp_json_encode( $config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?>;</script>
<script src="<?php echo esc_url( studyhub_asset_url( 'app.js', STUDYHUB_CLIENT_FILE ) ); ?>" defer></script>
</body>
</html>
