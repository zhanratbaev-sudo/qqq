<?php
/**
 * Вопросы, которые ассистент передал владельцу. Владелец отвечает здесь:
 * ответ можно отправить клиенту в WhatsApp и одной галочкой добавить в обучение.
 */
defined('ABSPATH') || exit;

add_action('init', function () {
    register_post_type('wfai_question', [
        'labels' => ['name' => 'Вопросы к вам', 'singular_name' => 'Вопрос', 'menu_name' => 'Вопросы', 'all_items' => 'Вопросы к вам',
            'edit_item' => 'Вопрос клиента', 'not_found' => 'Вопросов пока нет — ассистент справляется сам'],
        'public' => false, 'show_ui' => true, 'show_in_menu' => 'wfai', 'supports' => ['title'],
        'capabilities' => ['create_posts' => 'do_not_allow'], 'map_meta_cap' => true,
    ]);
});

/** Сохраняет вопрос и шлёт письмо владельцу. Возвращает ID. */
function wfai_create_question($question, $reason, array $transcript) {
    $id = wp_insert_post(['post_type' => 'wfai_question', 'post_status' => 'publish', 'post_title' => wp_trim_words($question, 14, '…')]);
    if (!$id || is_wp_error($id)) {
        return 0;
    }
    update_post_meta($id, '_q', $question);
    update_post_meta($id, '_reason', $reason);
    update_post_meta($id, '_transcript', $transcript);
    update_post_meta($id, '_state', 'new');

    $to = wfai_opt('owner_email');
    if ($to && is_email($to)) {
        $lines = array_map(fn($m) => ($m['role'] === 'user' ? 'Клиент: ' : 'Ассистент: ') . $m['content'], $transcript);
        wp_mail($to, 'Вопрос с сайта для вас: ' . wp_trim_words($question, 8, '…'),
            "Ассистент не смог ответить сам и передал вопрос вам.\n\nВопрос: {$question}\nПочему: {$reason}\n\nДиалог:\n" . implode("\n", $lines)
            . "\n\nОтветить: " . admin_url('post.php?post=' . $id . '&action=edit'));
    }
    return (int) $id;
}

add_filter('manage_wfai_question_posts_columns', fn() => ['cb' => '<input type="checkbox">', 'title' => 'Вопрос', 'wfai_contact' => 'Контакт клиента', 'wfai_state' => 'Статус', 'date' => 'Дата']);
add_action('manage_wfai_question_posts_custom_column', function ($col, $id) {
    if ($col === 'wfai_contact') {
        echo esc_html(get_post_meta($id, '_contact', true) ?: '—');
    } elseif ($col === 'wfai_state') {
        echo get_post_meta($id, '_state', true) === 'answered' ? 'Отвечен' : '<b>Ждёт ответа</b>';
    }
}, 10, 2);

add_action('add_meta_boxes_wfai_question', function () {
    add_meta_box('wfai_q', 'Вопрос и ответ', 'wfai_question_box', 'wfai_question', 'normal', 'high');
});

function wfai_question_box($post) {
    $m = fn($k) => get_post_meta($post->ID, $k, true);
    $contact = (string) $m('_contact');
    $answer = (string) $m('_answer');
    $digits = preg_replace('/\D+/', '', $contact);
    wp_nonce_field('wfai_answer', '_wfai_nonce');
    ?>
    <p><b>Вопрос:</b> <?php echo esc_html($m('_q')); ?></p>
    <p><b>Почему передал вам:</b> <?php echo esc_html($m('_reason') ?: '—'); ?></p>
    <p><b>Контакт клиента:</b> <?php echo esc_html($contact ?: 'клиент не оставил — ответ увидит, только если напишет снова'); ?></p>
    <details><summary><b>Весь диалог</b></summary>
        <div style="max-height:280px;overflow:auto;background:#f6f7f7;padding:8px 12px;margin-top:8px">
        <?php foreach ((array) $m('_transcript') as $msg) : ?>
            <p><b><?php echo $msg['role'] === 'user' ? 'Клиент' : 'Ассистент'; ?>:</b> <?php echo esc_html($msg['content']); ?></p>
        <?php endforeach; ?>
        </div></details>
    <p><label for="wfai-answer"><b>Ваш ответ</b></label><br>
        <textarea id="wfai-answer" name="_wfai_answer" class="large-text" rows="5"><?php echo esc_textarea($answer); ?></textarea></p>
    <p><label><input type="checkbox" name="_wfai_learn" value="1" <?php checked(!$m('_learned')); ?>> Добавить в обучение — в следующий раз ассистент ответит сам</label></p>
    <?php if ($digits && $answer) : ?>
        <p><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url('https://wa.me/' . $digits . '?text=' . rawurlencode($answer)); ?>">Отправить ответ клиенту в WhatsApp</a></p>
    <?php endif; ?>
    <p class="description">Нажмите «Обновить», чтобы сохранить ответ.</p>
    <?php
}

/** Сохраняет ответ владельца; с $learn — добавляет пару «вопрос — ответ» в обучение (один раз). */
function wfai_answer_question($id, $answer, $learn) {
    update_post_meta($id, '_answer', $answer);
    if ($answer === '') {
        return;
    }
    update_post_meta($id, '_state', 'answered');
    if ($learn && !get_post_meta($id, '_learned', true)) {
        $learned = (array) get_option('wfai_learned', []);
        $learned[] = ['q' => (string) get_post_meta($id, '_q', true), 'a' => $answer];
        update_option('wfai_learned', wfai_sanitize_learned($learned));
        update_post_meta($id, '_learned', 1);
    }
}

add_action('save_post_wfai_question', function ($id) {
    if (!isset($_POST['_wfai_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wfai_nonce'])), 'wfai_answer') || !current_user_can('edit_post', $id)) {
        return;
    }
    wfai_answer_question($id, sanitize_textarea_field(wp_unslash($_POST['_wfai_answer'] ?? '')), !empty($_POST['_wfai_learn']));
});

/** Счётчик неотвеченных вопросов в меню. */
add_action('admin_menu', function () {
    global $menu;
    $n = (int) (new WP_Query(['post_type' => 'wfai_question', 'meta_key' => '_state', 'meta_value' => 'new', 'fields' => 'ids', 'posts_per_page' => 1]))->found_posts;
    if (!$n || !is_array($menu)) {
        return;
    }
    foreach ($menu as $i => $item) {
        if (($item[2] ?? '') === 'wfai') {
            $menu[$i][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . $n . '</span></span>';
        }
    }
}, 99);
