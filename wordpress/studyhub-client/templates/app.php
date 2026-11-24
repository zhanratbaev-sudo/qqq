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
<html lang="<?php echo esc_attr( $studyhub_lang ); ?>" class="sh-light">
<head>
<?php echo studyhub_head_tags( 'app' ); // phpcs:ignore ?>
<title><?php echo esc_html( $studyhub_brand ); ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?php echo esc_url( studyhub_asset_url( 'client.css', STUDYHUB_CLIENT_FILE ) ); ?>">
<meta name="theme-color" content="#F1EFFB">
<meta name="format-detection" content="telephone=no">
<script>/* тема включается до отрисовки — без мигания: светлая по умолчанию, тёмная или как в телефоне — в профиле */
(function(){var d=document.documentElement,t="light";try{t=localStorage.getItem("sh_theme")||"light"}catch(e){}
var dark=t==="dark"||(t==="auto"&&window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches);
d.className=dark?"sh-dark":"sh-light";var m=document.querySelectorAll('meta[name="theme-color"]');for(var i=0;i<m.length;i++){m[i].setAttribute("content",dark?"#0E0D16":"#F1EFFB");m[i].removeAttribute("media")}
function h(){d.style.setProperty("--app-h",(window.innerHeight||d.clientHeight)+"px")}h();window.addEventListener("resize",h)})();</script>
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
