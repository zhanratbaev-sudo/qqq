<?php
/**
 * Заявки с сайта: тип записи webfor_lead (виден только в админке), приём формы, статус, уведомление.
 */
defined('ABSPATH') || exit;

function webfor_services() {
    return [
        'site'    => 'Сайт',
        'app'     => 'Мобильное приложение',
        'bot'     => 'AI-чат-бот для бизнеса',
        'vetizi'  => 'Vet.izi — для ветклиник и зоо-бизнеса',
        'izi'     => 'Izi — всё в одном + AI-ассистент',
        'unknown' => 'Пока не знаю, нужна консультация',
    ];
}

function webfor_lead_statuses() {
    return ['new' => 'Новая', 'work' => 'В работе', 'done' => 'Готово'];
}

function webfor_register_lead_type() {
    register_post_type('webfor_lead', [
        'labels' => [
            'name' => 'Заявки', 'singular_name' => 'Заявка', 'menu_name' => 'Заявки',
            'all_items' => 'Заявки', 'edit_item' => 'Заявка', 'search_items' => 'Найти заявку',
            'not_found' => 'Заявок пока нет', 'not_found_in_trash' => 'В корзине пусто',
        ],
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => 'webfor',
        'supports'        => ['title'],
        'capability_type' => 'post',
        'capabilities'    => ['create_posts' => 'do_not_allow'],
        'map_meta_cap'    => true,
    ]);
}
add_action('init', 'webfor_register_lead_type');

/** Число новых заявок — бейдж в меню. */
add_action('admin_menu', function () {
    global $menu;
    $q = new WP_Query(['post_type' => 'webfor_lead', 'post_status' => 'publish', 'meta_key' => '_wf_status', 'meta_value' => 'new', 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => false]);
    $n = (int) $q->found_posts;
    if (!$n || !is_array($menu)) {
        return;
    }
    foreach ($menu as $i => $item) {
        if (isset($item[2]) && $item[2] === 'webfor') {
            $menu[$i][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . $n . '</span></span>';
        }
    }
}, 99);

/* ---------- приём формы (AJAX и обычная отправка без JS) ---------- */

function webfor_handle_lead() {
    $p = wp_unslash($_POST);
    $err = function ($msg, $field = '') {
        return ['ok' => false, 'message' => $msg, 'field' => $field];
    };

    if (empty($p['_wf_nonce']) || !wp_verify_nonce($p['_wf_nonce'], 'webfor_lead')) {
        return $err('Страница устарела. Обновите её и отправьте заявку ещё раз.');
    }
    if (!empty($p['website'])) { // поле-ловушка для ботов
        return ['ok' => true, 'message' => 'Спасибо! Мы скоро свяжемся с вами.'];
    }

    $name = sanitize_text_field(isset($p['name']) ? $p['name'] : '');
    $phone = sanitize_text_field(isset($p['phone']) ? $p['phone'] : '');
    $service = sanitize_key(isset($p['service']) ? $p['service'] : '');
    $contact = (isset($p['contact']) && $p['contact'] === 'whatsapp') ? 'whatsapp' : 'call';
    $message = sanitize_textarea_field(isset($p['message']) ? $p['message'] : '');

    $nlen = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    if ($nlen < 2 || $nlen > 80) {
        return $err('Укажите имя.', 'name');
    }
    $digits = strlen(webfor_digits($phone));
    if ($digits < 10 || $digits > 15) {
        return $err('Проверьте номер телефона: нужно 10–15 цифр.', 'phone');
    }
    if (!isset(webfor_services()[$service])) {
        $service = 'unknown';
    }
    if (empty($p['consent'])) {
        return $err('Нужно согласие на обработку данных.', 'consent');
    }
    if ((function_exists('mb_strlen') ? mb_strlen($message) : strlen($message)) > 2000) {
        $message = mb_substr($message, 0, 2000);
    }

    // не больше 5 заявок в час с одного адреса
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $key = 'webfor_rl_' . md5($ip . wp_salt('nonce'));
    $count = (int) get_transient($key);
    if ($count >= 5) {
        return $err('Слишком много заявок подряд. Попробуйте позже или напишите нам в WhatsApp.');
    }
    set_transient($key, $count + 1, HOUR_IN_SECONDS);

    $id = wp_insert_post([
        'post_type'   => 'webfor_lead',
        'post_status' => 'publish',
        'post_title'  => $name . ' — ' . $phone,
    ], true);
    if (is_wp_error($id)) {
        return $err('Не удалось сохранить заявку. Напишите нам в WhatsApp.');
    }
    update_post_meta($id, '_wf_name', $name);
    update_post_meta($id, '_wf_phone', $phone);
    update_post_meta($id, '_wf_service', $service);
    update_post_meta($id, '_wf_contact', $contact);
    update_post_meta($id, '_wf_message', $message);
    update_post_meta($id, '_wf_status', 'new');

    $to = webfor_opt('notify_email');
    if ($to && is_email($to)) {
        $body = "Новая заявка с сайта WebFor\n\n"
            . 'Имя: ' . $name . "\n"
            . 'Телефон: ' . $phone . "\n"
            . 'Услуга: ' . webfor_services()[$service] . "\n"
            . 'Как связаться: ' . ($contact === 'whatsapp' ? 'WhatsApp' : 'Позвонить') . "\n"
            . ($message !== '' ? 'Комментарий: ' . $message . "\n" : '')
            . "\n" . admin_url('post.php?post=' . $id . '&action=edit');
        wp_mail($to, 'Новая заявка: ' . $name, $body);
    }

    return ['ok' => true, 'message' => 'Спасибо, ' . $name . '! Заявка отправлена — мы свяжемся с вами в ближайшее время.'];
}

add_action('wp_ajax_webfor_lead', 'webfor_ajax_lead');
add_action('wp_ajax_nopriv_webfor_lead', 'webfor_ajax_lead');
function webfor_ajax_lead() {
    $r = webfor_handle_lead();
    wp_send_json($r, $r['ok'] ? 200 : 422);
}

add_action('admin_post_webfor_lead', 'webfor_post_lead');
add_action('admin_post_nopriv_webfor_lead', 'webfor_post_lead');
function webfor_post_lead() {
    $r = webfor_handle_lead();
    $back = wp_get_referer() ? wp_get_referer() : home_url('/');
    $back = remove_query_arg(['wf_sent', 'wf_err'], $back);
    $back = $r['ok'] ? add_query_arg('wf_sent', '1', $back) : add_query_arg('wf_err', rawurlencode($r['message']), $back);
    wp_safe_redirect($back . '#zayavka');
    exit;
}

/* ---------- заявки в админке ---------- */

add_filter('manage_webfor_lead_posts_columns', function () {
    return ['cb' => '<input type="checkbox">', 'title' => 'Клиент', 'wf_service' => 'Услуга', 'wf_contact' => 'Связаться', 'wf_status' => 'Статус', 'date' => 'Дата'];
});
add_action('manage_webfor_lead_posts_custom_column', function ($col, $id) {
    if ($col === 'wf_service') {
        $s = get_post_meta($id, '_wf_service', true);
        echo esc_html(isset(webfor_services()[$s]) ? webfor_services()[$s] : '—');
    } elseif ($col === 'wf_contact') {
        $phone = get_post_meta($id, '_wf_phone', true);
        if (get_post_meta($id, '_wf_contact', true) === 'whatsapp') {
            $d = webfor_digits($phone);
            echo '<a href="' . esc_url('https://wa.me/' . $d) . '" target="_blank" rel="noopener">WhatsApp</a>';
        } else {
            echo '<a href="' . esc_url(webfor_tel_href($phone)) . '">Позвонить</a>';
        }
    } elseif ($col === 'wf_status') {
        $st = get_post_meta($id, '_wf_status', true);
        $labels = webfor_lead_statuses();
        echo '<span class="wf-st wf-st-' . esc_attr($st) . '">' . esc_html(isset($labels[$st]) ? $labels[$st] : '—') . '</span>';
    }
}, 10, 2);

add_action('admin_head', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'webfor_lead') {
        return;
    }
    echo '<style>.wf-st{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;background:#f0f0f1}.wf-st-new{background:#d1f5e5;color:#05603a}.wf-st-work{background:#fff3cd;color:#7a5a00}.wf-st-done{background:#e5e5e5;color:#50575e}.wf-lead th{text-align:left;width:160px;padding:6px 0;vertical-align:top}.wf-lead td{padding:6px 0}</style>';
});

add_action('add_meta_boxes_webfor_lead', function () {
    add_meta_box('wf_lead', 'Данные заявки', 'webfor_lead_box', 'webfor_lead', 'normal', 'high');
    remove_meta_box('slugdiv', 'webfor_lead', 'normal');
});

function webfor_lead_box($post) {
    $m = function ($k) use ($post) { return get_post_meta($post->ID, '_wf_' . $k, true); };
    $phone = $m('phone');
    $service = $m('service');
    wp_nonce_field('webfor_lead_status', '_wf_status_nonce');
    ?>
    <table class="wf-lead">
        <tr><th>Имя</th><td><?php echo esc_html($m('name')); ?></td></tr>
        <tr><th>Телефон</th><td><a href="<?php echo esc_url(webfor_tel_href($phone)); ?>"><?php echo esc_html($phone); ?></a>
            · <a href="<?php echo esc_url('https://wa.me/' . webfor_digits($phone)); ?>" target="_blank" rel="noopener">WhatsApp</a></td></tr>
        <tr><th>Услуга</th><td><?php echo esc_html(isset(webfor_services()[$service]) ? webfor_services()[$service] : '—'); ?></td></tr>
        <tr><th>Как связаться</th><td><?php echo $m('contact') === 'whatsapp' ? 'Написать в WhatsApp' : 'Позвонить'; ?></td></tr>
        <tr><th>Комментарий</th><td><?php echo nl2br(esc_html($m('message') ?: '—')); ?></td></tr>
        <tr><th><label for="wf-status">Статус</label></th><td>
            <select id="wf-status" name="_wf_status">
                <?php foreach (webfor_lead_statuses() as $k => $label) : ?>
                    <option value="<?php echo esc_attr($k); ?>" <?php selected($m('status'), $k); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select> <span class="description">Сохраните кнопкой «Обновить».</span></td></tr>
    </table>
    <?php
}

add_action('save_post_webfor_lead', function ($id) {
    if (!isset($_POST['_wf_status_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wf_status_nonce'])), 'webfor_lead_status')) {
        return;
    }
    if (!current_user_can('edit_post', $id)) {
        return;
    }
    $st = isset($_POST['_wf_status']) ? sanitize_key(wp_unslash($_POST['_wf_status'])) : '';
    if (isset(webfor_lead_statuses()[$st])) {
        update_post_meta($id, '_wf_status', $st);
    }
});
