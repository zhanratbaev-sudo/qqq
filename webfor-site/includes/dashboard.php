<?php
/**
 * Панель управления WebFor (пункт «WebFor» в админке): заявки, работы в папках, AI-ассистент, номера — на одном экране.
 * Экран — JS-приложение assets/admin-app.js, данные — REST /wp-json/webfor/v1/admin/* (только для администратора).
 */
defined('ABSPATH') || exit;

function webfor_panel_page() {
    echo '<div class="wrap"><div id="wfa-app" class="wfa"><p class="wfa-loading">Загружаем панель…</p></div></div>';
}

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_webfor') {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_style('webfor-admin-app', WEBFOR_URL . 'assets/admin-app.css', [], WEBFOR_VERSION);
    wp_enqueue_script('webfor-admin-app', WEBFOR_URL . 'assets/admin-app.js', [], WEBFOR_VERSION, true);
    wp_add_inline_script('webfor-admin-app', 'window.WFADMIN = ' . wp_json_encode(webfor_admin_config()) . ';', 'before');
});

/* ---------- данные ---------- */

function webfor_admin_lead($p) {
    $m = fn($k) => get_post_meta($p->ID, '_wf_' . $k, true);
    $services = webfor_services();
    $phone = $m('phone');
    return [
        'id' => $p->ID, 'name' => $m('name'), 'phone' => $phone, 'service' => $m('service'),
        'service_label' => $services[$m('service')] ?? '—', 'contact' => $m('contact') ?: 'call', 'message' => $m('message'),
        'status' => $m('status') ?: 'new', 'date' => get_post_time('c', true, $p), 'tel' => webfor_tel_href($phone),
        'wa' => 'https://wa.me/' . webfor_digits(webfor_tel_href($phone)),
    ];
}

function webfor_admin_work($p) {
    $img = get_post_meta($p->ID, '_wf_img', true);
    return [
        'id' => $p->ID, 'title' => get_the_title($p), 'folder' => get_post_meta($p->ID, '_wf_folder', true) ?: 'sites',
        'tag' => get_post_meta($p->ID, '_wf_tag', true), 'desc' => get_post_meta($p->ID, '_wf_desc', true),
        'url' => get_post_meta($p->ID, '_wf_url', true), 'shape' => get_post_meta($p->ID, '_wf_shape', true) ?: 'wide',
        'image_id' => (int) get_post_thumbnail_id($p),
        'src' => get_the_post_thumbnail_url($p, 'medium_large') ?: ($img ? WEBFOR_URL . 'assets/works/' . rawurlencode($img) : ''),
        'order' => (int) $p->menu_order,
    ];
}

function webfor_admin_state() {
    $leads = array_map('webfor_admin_lead', get_posts(['post_type' => 'webfor_lead', 'numberposts' => 300, 'post_status' => 'publish']));
    $works = array_map('webfor_admin_work', get_posts(['post_type' => 'webfor_work', 'numberposts' => 300, 'post_status' => 'publish', 'orderby' => 'menu_order', 'order' => 'ASC']));
    $folders = [];
    foreach (webfor_folders() as $k => $f) {
        $folders[] = ['key' => $k, 'title' => $f['title']];
    }
    $ai = null;
    if (function_exists('wfai_opt')) {
        $qs = get_posts(['post_type' => 'wfai_question', 'numberposts' => 200, 'post_status' => 'publish']);
        $ai = [
            'enabled' => (bool) wfai_opt('enabled'), 'has_key' => wfai_api_key() !== '', 'model' => wfai_opt('model'),
            'knowledge' => wfai_opt('knowledge'), 'learned' => array_values((array) get_option('wfai_learned', [])),
            'questions' => array_map(fn($q) => [
                'id' => $q->ID, 'q' => get_post_meta($q->ID, '_q', true), 'reason' => get_post_meta($q->ID, '_reason', true),
                'contact' => get_post_meta($q->ID, '_contact', true), 'answer' => get_post_meta($q->ID, '_answer', true),
                'state' => get_post_meta($q->ID, '_state', true) ?: 'new', 'learned' => (bool) get_post_meta($q->ID, '_learned', true),
                'date' => get_post_time('c', true, $q), 'transcript' => array_values((array) get_post_meta($q->ID, '_transcript', true)),
            ], $qs),
            'settings_url' => admin_url('admin.php?page=wfai'),
        ];
    }
    return [
        'leads' => $leads, 'works' => $works, 'folders' => $folders, 'statuses' => webfor_lead_statuses(), 'services' => webfor_services(),
        'settings' => ['phone' => webfor_opt('phone'), 'whatsapp' => webfor_opt('whatsapp')],
        'ai' => $ai, 'site_url' => ($pid = (int) get_option('webfor_page_id')) ? get_permalink($pid) : home_url('/'),
    ];
}

/* ---------- REST ---------- */

add_action('rest_api_init', function () {
    $can = fn() => current_user_can('manage_options');
    $r = function ($route, $methods, $cb) use ($can) {
        register_rest_route('webfor/v1', '/admin' . $route, ['methods' => $methods, 'callback' => $cb, 'permission_callback' => $can]);
    };

    $r('/state', 'GET', fn() => webfor_admin_state());

    $r('/lead/(?P<id>\d+)', 'POST', function (WP_REST_Request $q) {
        $id = (int) $q['id'];
        $st = sanitize_key((string) $q->get_param('status'));
        if (get_post_type($id) !== 'webfor_lead' || !isset(webfor_lead_statuses()[$st])) {
            return new WP_Error('bad', 'Неверная заявка или статус', ['status' => 400]);
        }
        update_post_meta($id, '_wf_status', $st);
        return webfor_admin_lead(get_post($id));
    });
    $r('/lead/(?P<id>\d+)', 'DELETE', function (WP_REST_Request $q) {
        $id = (int) $q['id'];
        if (get_post_type($id) !== 'webfor_lead') {
            return new WP_Error('bad', 'Нет такой заявки', ['status' => 404]);
        }
        wp_trash_post($id);
        return ['ok' => true];
    });

    $r('/work', 'POST', function (WP_REST_Request $q) {
        $id = (int) $q->get_param('id');
        if ($id && get_post_type($id) !== 'webfor_work') {
            return new WP_Error('bad', 'Нет такой работы', ['status' => 404]);
        }
        $title = sanitize_text_field((string) $q->get_param('title'));
        if ($title === '') {
            return new WP_Error('bad', 'Укажите название работы', ['status' => 400]);
        }
        $folder = sanitize_key((string) $q->get_param('folder'));
        $folder = isset(webfor_folders()[$folder]) ? $folder : 'sites';
        $data = ['post_type' => 'webfor_work', 'post_status' => 'publish', 'post_title' => $title];
        if ($id) {
            $data['ID'] = $id;
            $id = wp_update_post($data, true);
        } else {
            $last = get_posts(['post_type' => 'webfor_work', 'numberposts' => 1, 'orderby' => 'menu_order', 'order' => 'DESC']);
            $data['menu_order'] = $last ? $last[0]->menu_order + 1 : 0;
            $id = wp_insert_post($data, true);
        }
        if (is_wp_error($id)) {
            return $id;
        }
        update_post_meta($id, '_wf_folder', $folder);
        update_post_meta($id, '_wf_shape', $q->get_param('shape') === 'phone' ? 'phone' : 'wide');
        update_post_meta($id, '_wf_tag', sanitize_text_field((string) $q->get_param('tag')));
        update_post_meta($id, '_wf_desc', sanitize_textarea_field((string) $q->get_param('desc')));
        update_post_meta($id, '_wf_url', esc_url_raw((string) $q->get_param('url')));
        $img = (int) $q->get_param('image_id');
        if ($img && wp_attachment_is_image($img)) {
            set_post_thumbnail($id, $img);
        } elseif ($q->get_param('image_id') === 0 || $q->get_param('image_id') === '0') {
            delete_post_thumbnail($id);
        }
        return webfor_admin_work(get_post($id));
    });
    $r('/work/(?P<id>\d+)', 'DELETE', function (WP_REST_Request $q) {
        $id = (int) $q['id'];
        if (get_post_type($id) !== 'webfor_work') {
            return new WP_Error('bad', 'Нет такой работы', ['status' => 404]);
        }
        wp_trash_post($id);
        return ['ok' => true];
    });
    $r('/works/order', 'POST', function (WP_REST_Request $q) {
        foreach (array_values((array) $q->get_param('ids')) as $i => $id) {
            if (get_post_type((int) $id) === 'webfor_work') {
                wp_update_post(['ID' => (int) $id, 'menu_order' => $i]);
            }
        }
        return ['ok' => true];
    });

    $r('/settings', 'POST', function (WP_REST_Request $q) {
        $s = wp_parse_args((array) get_option('webfor_settings', []), webfor_default_settings());
        foreach (['phone' => 'Телефон', 'whatsapp' => 'WhatsApp'] as $k => $label) {
            $v = sanitize_text_field((string) $q->get_param($k));
            $n = strlen(webfor_digits($v));
            if ($n < 10 || $n > 15) {
                return new WP_Error('bad', $label . ': номер должен содержать от 10 до 15 цифр.', ['status' => 400]);
            }
            $s[$k] = $v;
        }
        update_option('webfor_settings', $s);
        return ['phone' => webfor_opt('phone'), 'whatsapp' => webfor_opt('whatsapp')];
    });

    if (!function_exists('wfai_opt')) {
        return;
    }
    $r('/ai/question/(?P<id>\d+)', 'POST', function (WP_REST_Request $q) {
        $id = (int) $q['id'];
        if (get_post_type($id) !== 'wfai_question') {
            return new WP_Error('bad', 'Нет такого вопроса', ['status' => 404]);
        }
        wfai_answer_question($id, sanitize_textarea_field((string) $q->get_param('answer')), (bool) $q->get_param('learn'));
        return ['ok' => true, 'learned' => array_values((array) get_option('wfai_learned', []))];
    });
    $r('/ai/question/(?P<id>\d+)', 'DELETE', function (WP_REST_Request $q) {
        $id = (int) $q['id'];
        if (get_post_type($id) !== 'wfai_question') {
            return new WP_Error('bad', 'Нет такого вопроса', ['status' => 404]);
        }
        wp_trash_post($id);
        return ['ok' => true];
    });
    $r('/ai/knowledge', 'POST', function (WP_REST_Request $q) {
        $s = wp_parse_args((array) get_option('wfai_settings', []), wfai_defaults());
        $s['knowledge'] = sanitize_textarea_field((string) $q->get_param('knowledge'));
        update_option('wfai_settings', $s);
        return ['ok' => true];
    });
    $r('/ai/learned', 'POST', function (WP_REST_Request $q) {
        update_option('wfai_learned', wfai_sanitize_learned((array) $q->get_param('rows')));
        return ['ok' => true, 'learned' => array_values((array) get_option('wfai_learned', []))];
    });
});
