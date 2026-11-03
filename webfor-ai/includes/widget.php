<?php
/** Окно чата на всех страницах сайта (кнопка в правом нижнем углу). */
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    if (!wfai_opt('enabled') || is_admin()) {
        return;
    }
    wp_enqueue_style('wfai-widget', WFAI_URL . 'assets/widget.css', [], WFAI_VERSION);
    wp_enqueue_script('wfai-widget', WFAI_URL . 'assets/widget.js', [], WFAI_VERSION, true);
    $c = wfai_contacts();
    wp_localize_script('wfai-widget', 'WFAI', [
        'chat'     => esc_url_raw(rest_url('webfor-ai/v1/chat')),
        'contact'  => esc_url_raw(rest_url('webfor-ai/v1/contact')),
        'name'     => wfai_opt('name'),
        'greeting' => wfai_opt('greeting'),
        'wa'       => $c['wa_href'],
        'chips'    => ['Сколько стоит сайт?', 'Что такое Vet.izi?', 'Хочу чат-бота для бизнеса'],
    ]);
});
