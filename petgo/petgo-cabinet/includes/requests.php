<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ЗАЯВКИ С САЙТА: шорткод [petgo_request], своя таблица (без нагрузки на wp_posts)
   ============================================================ */
function petgo_tb($n) {
    global $wpdb;
    return $wpdb->prefix . 'petgo_' . $n;
}

function petgo_req_msgs() {
    return array(
        'ok'        => 'Спасибо! Заявка отправлена, мы скоро свяжемся с вами.',
        'e_name'    => 'Укажите имя.',
        'e_phone'   => 'Проверьте номер телефона.',
        'e_contact' => 'Выберите: написать или позвонить.',
        'e_species' => 'Выберите, кто у вас: собака, кошка или другое.',
        'e_other'   => 'Напишите, какое это животное.',
        'e_wb'      => 'Укажите вес и породу.',
        'e_consent' => 'Нужно согласие на обработку данных.',
        'e_tok'     => 'Обновите страницу и отправьте ещё раз.',
        'e_req'     => 'Заполните обязательное поле.',
        'e_many'    => 'Слишком много заявок с вашего адреса. Попробуйте позже.',
    );
}

function petgo_req_token() {
    $t = time();
    return $t . '.' . hash_hmac('sha256', (string) $t, wp_salt('auth'));
}

function petgo_req_token_ok($tk) {
    $p = explode('.', (string) $tk);
    if (count($p) !== 2 || !ctype_digit($p[0])) {
        return false;
    }
    if (!hash_equals(hash_hmac('sha256', $p[0], wp_salt('auth')), $p[1])) {
        return false;
    }
    $age = time() - (int) $p[0];
    return $age >= 3 && $age <= 14 * DAY_IN_SECONDS;
}

function petgo_req_ip() {
    return md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
}

/* ============================================================
   ЗАЯВКИ В КАБИНЕТЕ
   ============================================================ */
function petgo_req_new_count() {
    static $n = null;
    if ($n === null) {
        global $wpdb;
        $n = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . petgo_tb('reqs') . " WHERE status='new'");
    }
    return $n;
}

function petgo_admin_requests() {
    global $wpdb;
    $t  = petgo_tb('reqs');
    $f  = (isset($_GET['rs']) && $_GET['rs'] === 'done') ? 'done' : 'new';
    $cn = array('new' => 0, 'done' => 0);
    foreach ((array) $wpdb->get_results("SELECT status, COUNT(*) c FROM $t GROUP BY status") as $r) {
        if (isset($cn[$r->status])) {
            $cn[$r->status] = (int) $r->c;
        }
    }
    echo '<div class="pgc-chips">';
    foreach (array('new' => 'rq_new', 'done' => 'rq_done') as $k => $l) {
        echo '<a class="pgc-chip' . ($f === $k ? ' on' : '') . '" href="' . esc_url(petgo_page_url(array('pg' => 'requests', 'rs' => $k))) . '">' . esc_html(petgo_t($l)) . '<i>' . (int) $cn[$k] . '</i></a>';
    }
    echo '</div>';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE status=%s ORDER BY id DESC LIMIT 100", $f));
    if (!$rows) {
        echo petgo_empty('requests', petgo_t('rq_none'));
        return;
    }
    foreach ($rows as $r) {
        $sp  = $r->species === '' ? '' : ($r->species === 'dog' ? petgo_t('o_dog') : ($r->species === 'cat' ? petgo_t('o_cat') : ($r->sp_other !== '' ? $r->sp_other : petgo_t('o_other'))));
        $tel = preg_replace('/[^0-9+]/', '', $r->phone);
        $wa  = petgo_wa_url($r->phone);
        $id  = (int) $r->id;
        echo '<article class="pgc-card pgc-rq' . ($f === 'new' ? ' is-new' : '') . '" data-k="r' . $id . '">';
        echo '<div class="pgc-rqh">' . petgo_av('', $r->name, 'md') . '<div class="pgc-sm"><b>' . esc_html($r->name) . '</b><span class="mu">' . esc_html(petgo_ago((int) $r->ts)) . '</span></div>';
        echo '<button type="button" class="pgc-dots" data-menu="pgc-rm' . $id . '" aria-label="' . esc_attr(petgo_t('rq_more')) . '">' . petgo_icon('more') . '</button>';
        echo '<div class="pgc-menu" id="pgc-rm' . $id . '">';
        echo petgo_btn_form('req_set', array('id' => $id, 'do' => $f === 'new' ? 'done' : 'new'), petgo_t($f === 'new' ? 'rq_mark' : 'rq_unmark'), 'pgc-mi');
        if (petgo_can('admin')) {
            echo petgo_btn_form('req_del', array('id' => $id), petgo_t('delete'), 'pgc-mi red', petgo_t('q_delete_req'), true);
        }
        echo '</div></div>';
        echo '<a class="pgc-rqtel" href="tel:' . esc_attr($tel) . '">' . petgo_icon('phone') . '<span>' . esc_html(petgo_phone_fmt($r->phone)) . '</span></a>';
        $tags = '';
        if ($r->contact !== '') {
            $tags .= '<span class="pgc-tag ' . ($r->contact === 'call' ? 'warn' : 'info') . '">' . esc_html(petgo_t($r->contact === 'call' ? 'rq_w_call' : 'rq_w_write')) . '</span>';
        }
        if ((int) $r->pet_id) {
            $tags .= '<span class="pgc-tag ok">' . esc_html(petgo_t('rq_pet_added')) . '</span>';
        }
        if ($tags !== '') {
            echo '<div class="pgc-tags">' . $tags . '</div>';
        }
        $pet = array();
        foreach (array($sp, $r->breed, $r->weight !== '' ? $r->weight . ' ' . petgo_t('kg') : '') as $x) {
            if (trim((string) $x) !== '') {
                $pet[] = '<span>' . esc_html($x) . '</span>';
            }
        }
        if ($pet) {
            echo '<div class="pgc-rqpet">' . petgo_icon('paw') . '<div>' . implode('', $pet) . '</div></div>';
        }
        $more = '';
        if ($r->note !== '') {
            $more .= '<p class="pgc-rqnote">' . nl2br(esc_html($r->note)) . '</p>';
        }
        $xt = ($r->extra !== null && $r->extra !== '') ? json_decode($r->extra, true) : array();
        if (is_array($xt) && $xt) {
            $more .= '<dl class="pgc-dl">';
            foreach ($xt as $xp) {
                if (is_array($xp) && count($xp) === 2) {
                    $more .= '<dt>' . esc_html((string) $xp[0]) . '</dt><dd>' . nl2br(esc_html((string) $xp[1])) . '</dd>';
                }
            }
            $more .= '</dl>';
        }
        echo $more;
        echo '<div class="pgc-rqact">';
        if ($wa) {
            echo '<a class="pgc-btn sec" target="_blank" rel="noopener" href="' . esc_url($wa) . '">' . petgo_icon('chat') . '<span>WhatsApp</span></a>';
        }
        echo '<a class="pgc-btn sec" href="tel:' . esc_attr($tel) . '">' . petgo_icon('phone') . '<span>' . esc_html(petgo_t('rq_call')) . '</span></a>';
        if (!(int) $r->pet_id) {
            /* 34.0: питомец добавляется сам, со всеми полями анкеты и фото — ничего вводить не нужно */
            echo petgo_form_open('req_addpet', 'class="pgc-inline"') . '<input type="hidden" name="id" value="' . $id . '"><button type="submit" class="pgc-btn">' . petgo_icon('plus') . '<span>' . esc_html(petgo_t('rq_addpet')) . '</span></button></form>';
        } else {
            if (get_post_type((int) $r->pet_id) === 'petgo_pet' && petgo_can('admin')) {
                echo '<a class="pgc-btn sec" href="' . esc_url(petgo_page_url(array('pg' => 'pets', 'edit' => (int) $r->pet_id))) . '">' . petgo_ic('edit') . '<span>' . esc_html(petgo_t('edit')) . '</span></a>';
            }
            if ($f === 'new') {
                echo petgo_btn_form('req_set', array('id' => $id, 'do' => 'done'), petgo_t('rq_mark'), 'pgc-btn');
            }
        }
        echo '</div><p class="pgc-rqc">' . petgo_icon('check') . esc_html(petgo_t('rq_consent')) . '</p></article>';
    }
}

add_action('admin_post_petgo_req_set', 'petgo_h_req_set');
function petgo_h_req_set() {
    petgo_guard('office');
    global $wpdb;
    $wpdb->update(petgo_tb('reqs'), array('status' => petgo_p('do') === 'done' ? 'done' : 'new'), array('id' => (int) petgo_p('id')), array('%s'), array('%d'));
    petgo_bump();
    petgo_flash(petgo_t('ok_saved'));
    petgo_back('requests', petgo_p('do') === 'done' ? array() : array('rs' => 'done'));
}

add_action('admin_post_petgo_req_del', 'petgo_h_req_del');
function petgo_h_req_del() {
    petgo_guard('admin');
    global $wpdb;
    $id = (int) petgo_p('id');
    $r  = $wpdb->get_row($wpdb->prepare('SELECT name FROM ' . petgo_tb('reqs') . ' WHERE id=%d', $id));
    if ($r) {
        petgo_why('Удалил заявку от «' . $r->name . '»', 'requests');
        $wpdb->delete(petgo_tb('reqs'), array('id' => $id), array('%d'));
        petgo_bump();
        petgo_flash(petgo_t('ok_deleted'));
    }
    petgo_back('requests');
}

/* данные заявки для формы «Добавить питомца» */
function petgo_req_prefill($id) {
    global $wpdb;
    $r = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . petgo_tb('reqs') . ' WHERE id=%d', (int) $id));
    if (!$r) {
        return array();
    }
    return array('owner' => $r->name, 'phone' => $r->phone, 'species' => $r->species, 'species_other' => $r->sp_other, 'breed' => $r->breed, 'weight' => $r->weight, 'notes' => trim($r->note . petgo_req_extra_text($r)));
}

function petgo_req_extra_text($r) {
    $o  = '';
    $xt = ($r->extra !== null && $r->extra !== '') ? json_decode($r->extra, true) : array();
    if (is_array($xt)) {
        foreach ($xt as $xp) {
            if (is_array($xp) && count($xp) === 2) {
                $o .= "\n" . sanitize_text_field((string) $xp[0]) . ': ' . sanitize_text_field((string) $xp[1]);
            }
        }
    }
    return $o;
}
