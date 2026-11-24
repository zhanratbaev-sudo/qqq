<?php
/**
 * Шаблон сайта StudyHub: без темы WordPress. Переменные: $key, $html, $seo.
 *
 * @package StudyHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$studyhub_canon = studyhub_site_url( $key );
?><!doctype html>
<html lang="<?php echo esc_attr( studyhub_lang() ); ?>" class="sh-auto">
<head>
<?php echo studyhub_head_tags(); // phpcs:ignore ?>
<title><?php echo esc_html( $seo['title'] ); ?></title>
<meta name="description" content="<?php echo esc_attr( $seo['desc'] ); ?>">
<link rel="canonical" href="<?php echo esc_url( $studyhub_canon ); ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo esc_attr( $seo['title'] ); ?>">
<meta property="og:description" content="<?php echo esc_attr( $seo['desc'] ); ?>">
<meta property="og:image" content="<?php echo esc_url( $seo['image'] ); ?>">
<meta property="og:url" content="<?php echo esc_url( $studyhub_canon ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( studyhub_asset_url( 'site.css', STUDYHUB_SITE_FILE ) ); ?>">
<?php do_action( 'studyhub_site_head', $key ); ?>
</head>
<body class="s-body s-page-<?php echo esc_attr( $key ); ?>">
<div class="s-wrap">
<?php echo studyhub_site_header( $key ); // phpcs:ignore ?>
<main class="s-main"><?php echo $html; // phpcs:ignore ?></main>
<?php echo studyhub_site_footer(); // phpcs:ignore ?>
</div>
<?php echo studyhub_site_tabbar( $key ); // phpcs:ignore ?>
<script src="<?php echo esc_url( studyhub_asset_url( 'site.js', STUDYHUB_SITE_FILE ) ); ?>" defer></script>
<?php do_action( 'studyhub_site_foot', $key ); ?>
</body>
</html>
