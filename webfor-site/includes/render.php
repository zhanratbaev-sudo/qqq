<?php
/**
 * Вывод сайта: шорткоды, собственный шаблон страницы (без шапки и подвала темы), стили и скрипт.
 */
defined('ABSPATH') || exit;

add_shortcode('webfor_landing', function () {
    webfor_enqueue();
    ob_start();
    include WEBFOR_DIR . 'templates/landing.php';
    return ob_get_clean();
});

add_shortcode('webfor_phone', function ($atts) {
    $a = shortcode_atts(['link' => '1'], $atts);
    $p = esc_html(webfor_opt('phone'));
    return $a['link'] === '1' ? '<a href="' . esc_url(webfor_tel_href()) . '">' . $p . '</a>' : $p;
});

add_shortcode('webfor_whatsapp', function ($atts) {
    $a = shortcode_atts(['text' => 'Написать в WhatsApp'], $atts);
    return '<a href="' . esc_url(webfor_wa_href()) . '" target="_blank" rel="noopener">' . esc_html($a['text']) . '</a>';
});

function webfor_enqueue() {
    wp_enqueue_style('webfor-site', WEBFOR_URL . 'assets/site.css', [], WEBFOR_VERSION);
    wp_enqueue_script('webfor-site', WEBFOR_URL . 'assets/site.js', [], WEBFOR_VERSION, true);
    wp_localize_script('webfor-site', 'WEBFOR', ['ajax' => admin_url('admin-ajax.php')]);
}

/** Страница с шорткодом сайта открывается во весь экран, без шапки и подвала темы. */
function webfor_is_landing_page() {
    if (!is_singular('page')) {
        return false;
    }
    $post = get_queried_object();
    return $post && ((int) $post->ID === (int) get_option('webfor_page_id') || has_shortcode($post->post_content, 'webfor_landing'));
}

add_filter('template_include', function ($tpl) {
    if (webfor_is_landing_page()) {
        webfor_enqueue();
        return WEBFOR_DIR . 'templates/page.php';
    }
    return $tpl;
}, 99);

/** Склонение: 1 работа, 2 работы, 5 работ. */
function webfor_plural($n, $one, $few, $many) {
    $n = abs((int) $n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) {
        return $many;
    }
    return $n1 === 1 ? $one : ($n1 >= 2 && $n1 <= 4 ? $few : $many);
}

/** Маленький знак WebFor: плитка с буквой W, без градиентов. */
function webfor_mark($size = 32) {
    return '<svg class="wf-mark" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 120 120" aria-hidden="true"><rect width="120" height="120" rx="30" fill="#0f8a76"/><path d="M26 40 L42 84 L60 52 L78 84 L94 40" fill="none" stroke="#f4f1e4" stroke-width="12" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/** Иконки (линейные, 24×24, одна толщина). */
function webfor_icon($name) {
    $p = [
        'phone'   => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'chat'    => '<path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.5 3.5V17h0A2.5 2.5 0 0 1 4 14.5z"/><path d="M8 9h8M8 12.5h5"/>',
        'check'   => '<path d="m5 12 5 5L20 7"/>',
        'dash'    => '<path d="M7 12h10"/>',
        'globe'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'layers'  => '<path d="m12 3 9 5-9 5-9-5 9-5z"/><path d="m3 13 9 5 9-5"/>',
        'paw'     => '<circle cx="7" cy="10" r="1.6"/><circle cx="10.5" cy="6.5" r="1.6"/><circle cx="14.5" cy="6.5" r="1.6"/><circle cx="18" cy="10" r="1.6"/><path d="M12.5 11c-3 0-5.5 3.5-5.5 6 0 1.7 1.3 2.5 2.8 2.2 1-.2 1.8-.6 2.7-.6s1.7.4 2.7.6c1.5.3 2.8-.5 2.8-2.2 0-2.5-2.5-6-5.5-6z"/>',
        'spark'   => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
        'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'device'  => '<rect x="6" y="2.5" width="12" height="19" rx="3"/><path d="M10.5 18.5h3"/>',
        'bot'     => '<path class="wf-s1" d="M10 6.0Q10 13.5 17.5 13.5Q10 13.5 10 21.0Q10 13.5 2.5 13.5Q10 13.5 10 6.0Z" fill="currentColor" stroke="none"/><path class="wf-s2" d="M18.5 2.0Q18.5 5.5 22.0 5.5Q18.5 5.5 18.5 9.0Q18.5 5.5 15.0 5.5Q18.5 5.5 18.5 2.0Z" fill="currentColor" stroke="none"/><path class="wf-s3" d="M19 15.0Q19 17.5 21.5 17.5Q19 17.5 19 20.0Q19 17.5 16.5 17.5Q19 17.5 19 15.0Z" fill="currentColor" stroke="none"/>', // три искры AI, как у кнопки чата
        'tag'     => '<path d="M3 12V4.5A1.5 1.5 0 0 1 4.5 3H12l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
        'columns' => '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M9 4v16M15 4v16"/>',
        'help'    => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .8-1 1.5v.4M12 17h.01"/>',
        'grid'    => '<rect x="4" y="4" width="7" height="7" rx="2"/><rect x="13" y="4" width="7" height="7" rx="2"/><rect x="4" y="13" width="7" height="7" rx="2"/><rect x="13" y="13" width="7" height="7" rx="2"/>',
        'back'    => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
        'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
    ];
    return '<svg class="wf-i" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . (isset($p[$name]) ? $p[$name] : '') . '</svg>';
}
