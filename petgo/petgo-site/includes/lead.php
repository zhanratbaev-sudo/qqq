<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ЗАЯВКА И РЕГИСТРАЦИЯ ПИТОМЦА НА САЙТЕ
   Одна форма в стиле iPhone (поля списком, переключатели-сегменты). Отправляется без перезагрузки страницы:
   форма сворачивается, появляется галочка «Отправлено». Данные попадают в кабинет, в раздел «Заявки»
   (оттуда сотрудник одним нажатием добавляет питомца в базу).
   Оформление формы больше не настраивается — редактор «Форма заявки» из кабинета убран.
   Шорткод [petgo_request] выводит эту же форму.
   ============================================================ */

function petgo_lead_seg($name, $opts, $checked = '') {
    $h = '<div class="pg-seg" role="radiogroup">';
    foreach ($opts as $v => $t) {
        $h .= '<label><input type="radio" name="' . esc_attr($name) . '" value="' . esc_attr($v) . '"' . ($v === $checked ? ' checked' : '') . '><span>' . esc_html($t) . '</span></label>';
    }
    return $h . '</div>';
}

function petgo_lead_row($label, $input, $cls = '') {
    return '<label class="pg-fl' . ($cls !== '' ? ' ' . $cls : '') . '"><span>' . esc_html($label) . '</span>' . $input . '</label>';
}

/* $kind: request — заявка; pet — регистрация питомца (кабинет) */
function petgo_lead_form($kind = 'request') {
    $pet = $kind === 'pet';
    $L   = function_exists('petgo_site_l') ? 'petgo_site_l' : 'strval';
    $h   = '<form class="pg-form" data-kind="' . ($pet ? 'pet' : 'request') . '" method="post" action="' . esc_attr(petgo_rel_url(admin_url('admin-ajax.php'))) . '" novalidate>';
    $h  .= '<div class="pg-form-body">';
    $h  .= '<input type="hidden" name="action" value="petgo_lead"><input type="hidden" name="kind" value="' . ($pet ? 'pet' : 'request') . '"><input type="hidden" name="pgr_t" value="' . esc_attr(petgo_req_token()) . '"><input type="hidden" name="lang" value="' . esc_attr(function_exists('petgo_site_lang') ? petgo_site_lang() : 'ru') . '">';
    $h  .= '<div class="pg-hp" aria-hidden="true"><input type="text" name="pgr_web" tabindex="-1" autocomplete="off"></div>';

    $h  .= '<div class="pg-fg">';
    $h  .= petgo_lead_row($L('Имя'), '<input type="text" name="name" maxlength="80" autocomplete="name" placeholder="' . esc_attr($L('Как к вам обращаться')) . '" required>');
    $h  .= petgo_lead_row($L('Телефон'), '<input type="tel" name="phone" maxlength="20" autocomplete="tel" inputmode="tel" placeholder="+7 700 000 00 00" required>');
    $h  .= '</div>';

    $h  .= '<div class="pg-fs"><span class="pg-fs-t">' . esc_html($L('Как с вами связаться')) . '</span>' . petgo_lead_seg('contact', array('write' => 'WhatsApp', 'call' => $L('Позвонить')), 'write') . '</div>';
    $h  .= '<div class="pg-fs"><span class="pg-fs-t">' . esc_html($L('Кто у вас')) . '</span>' . petgo_lead_seg('species', array('dog' => $L('Собака'), 'cat' => $L('Кошка'), 'other' => $L('Другой')), 'dog') . '</div>';

    $h  .= '<div class="pg-fg">';
    $h  .= petgo_lead_row($L('Кто именно'), '<input type="text" name="other" maxlength="60" placeholder="' . esc_attr($L('Например, кролик')) . '">', 'pg-fl-other');
    if ($pet) {
        $h .= petgo_lead_row($L('Кличка'), '<input type="text" name="pet" maxlength="60" placeholder="' . esc_attr($L('Как зовут питомца')) . '" required>');
    }
    $h  .= petgo_lead_row($L('Порода'), '<input type="text" name="breed" maxlength="80" placeholder="' . esc_attr($L($pet ? 'Например, лабрадор' : 'Необязательно')) . '">');
    if ($pet) {
        $h .= petgo_lead_row($L('Возраст'), '<input type="text" name="age" maxlength="30" placeholder="' . esc_attr($L('Например, 3 года')) . '">');
    }
    $h  .= petgo_lead_row($L('Вес, кг'), '<input type="text" name="weight" maxlength="20" inputmode="decimal" placeholder="' . esc_attr($L('Необязательно')) . '">');
    $h  .= '</div>';

    $h  .= '<div class="pg-fg">' . petgo_lead_row($L($pet ? 'Особенности' : 'Комментарий'), '<textarea name="note" rows="3" maxlength="1000" placeholder="' . esc_attr($L($pet ? 'Характер, здоровье, привычки, что любит' : 'Что важно знать о питомце и когда нужна няня')) . '"></textarea>', 'pg-fl-area') . '</div>';

    $h  .= '<label class="pg-consent"><input type="checkbox" name="consent" value="1" required><i aria-hidden="true">' . petgo_site_icon('check') . '</i><span>' . esc_html($L('Согласен на обработку персональных данных')) . '</span></label>';
    $h  .= '<p class="pg-form-err" role="alert" hidden></p>';
    $h  .= '<button type="submit" class="pg-b pg-btn pg-btn-primary pg-form-go"><span>' . esc_html($L($pet ? 'Зарегистрировать питомца' : 'Отправить заявку')) . '</span><i class="pg-spin" aria-hidden="true"></i></button>';
    $h  .= '</div>';

    /* состояние «отправлено»: галочка рисуется, форма сворачивается */
    $h  .= '<div class="pg-form-ok" hidden role="status"><svg class="pg-ok-svg" viewBox="0 0 80 80" aria-hidden="true"><circle cx="40" cy="40" r="36"/><path d="M24 41.500l11 11L57 29"/></svg>';
    $h  .= '<b>' . esc_html($L($pet ? 'Питомец зарегистрирован' : 'Заявка отправлена')) . '</b><p>' . esc_html($L($pet ? 'Спасибо! Анкета уже у нас. Свяжемся с вами, чтобы уточнить детали.' : 'Спасибо! Уже смотрим вашу заявку и скоро ответим.')) . '</p>';
    $h  .= '<button type="button" class="pg-b pg-btn pg-btn-second" data-again>' . esc_html($L($pet ? 'Добавить ещё питомца' : 'Отправить ещё одну')) . '</button></div>';
    return $h . '</form>';
}

/* ---------- приём ---------- */
add_action('wp_ajax_nopriv_petgo_lead', 'petgo_lead_ajax');
add_action('wp_ajax_petgo_lead', 'petgo_lead_ajax');
function petgo_lead_ajax() {
    nocache_headers();
    $fail = function ($msg, $field = '') {
        wp_send_json(array('ok' => false, 'msg' => $msg, 'field' => $field));
    };
    /* поле-ловушка: робот его заполняет, человек не видит */
    if (!empty($_POST['pgr_web'])) {
        wp_send_json(array('ok' => true));
    }
    /* заявка должна прийти с этого же сайта (защита от рассылки заявок с чужих страниц) */
    if (function_exists('petgo_guard_same_origin') && !petgo_guard_same_origin()) {
        $fail(petgo_site_l('Обновите страницу и отправьте ещё раз.'));
    }
    /* метка формы: подпись должна быть верной, с момента открытия — не меньше 2 секунд (роботы отправляют сразу).
       Срок не ограничиваем: страницу могли сохранить в кэше сайта или Cloudflare, и тогда метка «старая» */
    $tk = explode('.', (string) petgo_p('pgr_t'));
    if (count($tk) !== 2 || !ctype_digit($tk[0]) || !hash_equals(hash_hmac('sha256', $tk[0], wp_salt('auth')), $tk[1])) {
        $fail(petgo_site_l('Обновите страницу и отправьте ещё раз.'));
    }
    if (time() - (int) $tk[0] < 2) {
        $fail(petgo_site_l('Секунду… Нажмите «Отправить» ещё раз.'));
    }
    $ik = 'petgo_rq_' . md5(petgo_ip());
    if ((int) get_transient($ik) >= 5) {
        $fail(petgo_site_lf('Слишком много заявок с вашего адреса. Попробуйте позже или позвоните нам: %s.', petgo_site_phone_fmt()));
    }
    $pick = function ($k, $max) {
        $v = petgo_p($k);
        return mb_substr(sanitize_text_field(is_string($v) ? $v : ''), 0, $max);
    };
    $pet  = petgo_p('kind') === 'pet';
    $name = $pick('name', 80);
    if (mb_strlen($name) < 2) {
        $fail(petgo_site_l('Укажите имя.'), 'name');
    }
    $d = preg_replace('/\D/', '', (string) $pick('phone', 30));
    if (strlen($d) === 11 && $d[0] === '8') {
        $d = '7' . substr($d, 1);
    }
    if (strlen($d) === 10) {
        $d = '7' . $d;
    }
    if (strlen($d) < 11 || strlen($d) > 15) {
        $fail(petgo_site_l('Проверьте номер телефона.'), 'phone');
    }
    $contact = $pick('contact', 10);
    $contact = in_array($contact, array('write', 'call'), true) ? $contact : '';
    $species = $pick('species', 10);
    if (!in_array($species, array('dog', 'cat', 'other'), true)) {
        $fail(petgo_site_l('Выберите, кто у вас: собака, кошка или другой питомец.'), 'species');
    }
    $other = $species === 'other' ? $pick('other', 60) : '';
    if ($species === 'other' && $other === '') {
        $fail(petgo_site_l('Напишите, какое это животное.'), 'other');
    }
    $nick = $pet ? $pick('pet', 60) : '';
    if ($pet && $nick === '') {
        $fail(petgo_site_l('Укажите кличку питомца.'), 'pet');
    }
    $v = petgo_p('note');
    $note = mb_substr(sanitize_textarea_field(is_string($v) ? $v : ''), 0, 1000);
    if (petgo_p('consent') !== '1') {
        $fail(petgo_site_l('Нужно согласие на обработку данных.'), 'consent');
    }
    $extra = array();
    if (petgo_site_lang() !== 'ru') {
        $extra[] = array('Язык', petgo_site_langs()[petgo_site_lang()][1]);
    }
    if ($pet) {
        $extra[] = array('Тип', 'Регистрация питомца');
        $extra[] = array('Кличка', $nick);
        $age = $pick('age', 30);
        if ($age !== '') {
            $extra[] = array('Возраст', $age);
        }
    }
    global $wpdb;
    $t   = petgo_tb('reqs');
    /* повтор той же заявки за 10 минут не создаёт вторую запись (для регистрации питомца — если та же кличка) */
    $dup = $wpdb->get_row($wpdb->prepare("SELECT id, extra FROM $t WHERE phone=%s AND ts>%d ORDER BY id DESC LIMIT 1", '+' . $d, time() - 600));
    if ($dup && $pet && strpos((string) $dup->extra, wp_json_encode($nick, JSON_UNESCAPED_UNICODE)) === false) {
        $dup = null;
    }
    if (!$dup) {
        $wpdb->insert($t, array(
            'name' => $name, 'phone' => '+' . $d, 'contact' => $contact, 'species' => $species, 'sp_other' => $other,
            'weight' => $pick('weight', 20), 'breed' => $pick('breed', 80), 'note' => $note,
            'extra' => $extra ? mb_substr(wp_json_encode($extra, JSON_UNESCAPED_UNICODE), 0, 6000) : '', 'status' => 'new', 'ts' => time(),
        ), array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'));
        if (!$wpdb->insert_id) {
            $fail(petgo_site_lf('Не получилось сохранить. Попробуйте ещё раз или позвоните нам: %s.', petgo_site_phone_fmt()));
        }
        petgo_bump();
        petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), $pet ? 'Регистрация питомца' : 'Новая заявка', ($pet ? $nick . ' · ' : '') . $name . ' · +' . $d, petgo_page_url(array('pg' => 'requests')));
    }
    set_transient($ik, (int) get_transient($ik) + 1, HOUR_IN_SECONDS);
    wp_send_json(array('ok' => true));
}
