<?php
/* Шаблон страниц сайта PetGo. Темы WordPress, её шапки, подвала, стилей и скриптов здесь нет. */
if (!defined('ABSPATH')) {
    exit;
}
petgo_site_is_front(true);
$petgo_page = petgo_site_page_now();
$petgo_page = $petgo_page !== '' ? $petgo_page : 'home';
$petgo_html = petgo_site_render($petgo_page);
?><!doctype html>
<html lang="<?php echo esc_attr(petgo_site_langs()[petgo_site_lang()][2]); ?>" class="pg-html">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<?php echo petgo_site_head($petgo_page); ?>
<?php do_action('petgo_site_head'); ?>
</head>
<body class="pg-body">
<?php echo $petgo_html; ?>
</body>
</html>
