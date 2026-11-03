<?php
/** Шаблон страницы сайта WebFor: свой документ без шапки и подвала темы. */
defined('ABSPATH') || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d1211">
    <meta name="description" content="WebFor — сайты и цифровые системы для бизнеса: сайты под ваш дизайн, Izi — сайт, CRM и приложение для команды в одном, Vet.izi для ветклиник.">
    <?php wp_head(); ?>
</head>
<body <?php body_class('wf-body'); ?>>
<?php wp_body_open(); ?>
<?php
while (have_posts()) {
    the_post();
    include WEBFOR_DIR . 'templates/landing.php';
}
?>
<?php wp_footer(); ?>
</body>
</html>
