<?php
/* Шаблон страницы кабинета: только то, что нужно приложению (без темы, её стилей и скриптов) */
if (!defined('ABSPATH')) {
    exit;
}
$petgo_html = petgo_render_app();
?><!doctype html>
<html lang="<?php echo esc_attr(petgo_lang()); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,interactive-widget=resizes-content">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html(petgo_app_name()); ?></title>
<?php echo petgo_head_tags(); ?>
</head>
<body class="pgc-body">
<?php echo $petgo_html; ?>
</body>
</html>
