<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   САЙТ: анкета карточками по шагам
   $mode: site — регистрация с сайта (в заявки), invite — по ссылке из приложения (сразу в базу),
          client — из кабинета клиента (сразу в базу, привязка к клиенту)
   ============================================================ */
function petgo_pf_wizard($mode = 'site', $args = array()) {
    $L    = 'petgo_site_l';
    $M    = petgo_pf_meta();
    $pre  = isset($args['pre']) && is_array($args['pre']) ? $args['pre'] : array();
    $pid  = isset($args['pet_id']) ? (int) $args['pet_id'] : 0;
    $tok  = isset($args['token']) ? (string) $args['token'] : '';
    $steps = petgo_pf_steps();
    /* в кабинете клиента имя и телефон уже есть в аккаунте — шаг «Хозяин» короче */
    if ($mode === 'client') {
        $steps['owner'][3] = array('address', 'access', 'emergency', 'vet');
        $steps['owner'][2] = 'Как к вам попасть и кому звонить';
    }
    $n  = count($steps);
    $h  = '<form class="pgw" data-mode="' . esc_attr($mode) . '" method="post" action="' . esc_attr(petgo_rel_url(admin_url('admin-ajax.php'))) . '" enctype="multipart/form-data" novalidate>';
    $h .= '<input type="hidden" name="action" value="petgo_pf_send"><input type="hidden" name="mode" value="' . esc_attr($mode) . '"><input type="hidden" name="t" value="' . esc_attr($tok) . '"><input type="hidden" name="pet_id" value="' . $pid . '">';
    $h .= '<input type="hidden" name="pgr_t" value="' . esc_attr(petgo_req_token()) . '"><input type="hidden" name="lang" value="' . esc_attr(petgo_site_lang()) . '">';
    if ($mode === 'client') {
        $h .= '<input type="hidden" name="n" value="' . esc_attr(wp_create_nonce('petgo_cl')) . '">';
    }
    $h .= '<div class="pg-hp" aria-hidden="true"><input type="text" name="pgr_web" tabindex="-1" autocomplete="off"></div>';
    $h .= '<div class="pgw-head"><div class="pgw-dots" aria-hidden="true">';
    for ($i = 0; $i < $n; $i++) {
        $h .= '<i' . ($i ? '' : ' class="on"') . '></i>';
    }
    $h .= '</div><span class="pgw-cnt" aria-live="polite"><b>1</b> / ' . $n . '</span></div>';
    $h .= '<div class="pgw-stack">';
    $i = 0;
    foreach ($steps as $sk => $st) {
        $h .= '<section class="pgw-card' . ($i === 0 ? ' on' : '') . '" data-step="' . $i . '" data-k="' . esc_attr($sk) . '"' . ($i ? ' aria-hidden="true"' : '') . '>';
        $h .= '<div class="pgw-ch"><span class="pgw-ico">' . petgo_pf_icon($st[0]) . '</span><div><h3>' . esc_html($L($st[1])) . '</h3><p>' . esc_html($L($st[2])) . '</p></div></div><div class="pgw-fields">';
        foreach ($st[3] as $k) {
            $h .= petgo_pf_site_field($k, isset($pre[$k]) ? (string) $pre[$k] : '', $M, $mode, $pid);
        }
        $h .= '</div>';
        if ($sk === 'final') {
            $h .= '<div class="pgw-sum" aria-live="polite"></div>';
            if ($mode !== 'client') {
                $h .= '<label class="pgw-consent"><input type="checkbox" name="consent" value="1"><i aria-hidden="true">' . petgo_pf_icon('check') . '</i><span>' . esc_html($L('Согласен на обработку персональных данных')) . '</span></label>';
            }
        }
        $h .= '</section>';
        $i++;
    }
    $h .= '</div><p class="pgw-err" role="alert" hidden></p>';
    $go = $mode === 'site' ? 'Отправить анкету' : ($pid ? 'Сохранить' : 'Добавить питомца');
    $h .= '<div class="pgw-nav"><button type="button" class="pg-b pgw-back" data-w="back" aria-label="' . esc_attr($L('Назад')) . '">' . petgo_pf_icon('left') . '</button>';
    $h .= '<button type="button" class="pg-b pgw-next" data-w="next"><span class="pgw-nl">' . esc_html($L('Далее')) . '</span><span class="pgw-gl">' . esc_html($L($go)) . '</span><i class="pg-spin" aria-hidden="true"></i></button></div>';
    $h .= '<p class="pgw-skip">' . esc_html($L('Необязательные поля можно пропустить — заполним вместе позже.')) . '</p>';
    /* готово */
    $ok_t = $mode === 'site' ? 'Анкета отправлена' : ($pid ? 'Сохранено' : 'Питомец добавлен');
    $ok_s = $mode === 'site' ? 'Спасибо! Мы проверим анкету, добавим питомца в базу и свяжемся с вами.' : ($mode === 'invite' ? 'Спасибо! Питомец уже в нашей базе. До встречи на прогулке!' : 'Питомец уже в вашем кабинете.');
    $h .= '<div class="pgw-ok" hidden role="status"><svg class="pg-ok-svg" viewBox="0 0 80 80" aria-hidden="true"><circle cx="40" cy="40" r="36"/><path d="M24 41.5l11 11L57 29"/></svg><b>' . esc_html($L($ok_t)) . '</b><p>' . esc_html($L($ok_s)) . '</p>';
    if ($mode === 'site') {
        $h .= '<button type="button" class="pg-b pg-btn pg-btn-second" data-w="again">' . esc_html($L('Добавить ещё питомца')) . '</button>';
    } elseif ($mode === 'client') {
        $h .= '<button type="button" class="pg-b pg-btn pg-btn-primary" data-w="reload">' . esc_html($L('Готово')) . '</button>';
    }
    $h .= '</div></form>';
    return $h;
}

function petgo_pf_site_field($k, $v, $M, $mode, $pid) {
    $L   = 'petgo_site_l';
    $t   = petgo_pf_type($k);
    $lab = $L($M[$k][0]);
    $ph  = $M[$k][1] !== '' ? $L($M[$k][1]) : '';
    $req = in_array($k, array('title', 'species'), true) || ($mode !== 'client' && in_array($k, array('owner', 'phone'), true));
    $name = $k === 'title' ? 'title' : ($k === 'avatar' ? 'avatar' : 'f_' . $k);
    $star = $req ? '<em aria-hidden="true">*</em>' : '';
    if ($t[0] === 'photo') {
        $img = $pid ? petgo_pet_avatar_url($pid) : '';
        return '<label class="pgw-photo' . ($img ? ' has' : '') . '"><input class="pgw-vh" type="file" name="avatar" accept="image/*"><span class="pgw-ph"' . ($img ? ' style="background-image:url(' . esc_url($img) . ')"' : '') . '>' . petgo_pf_icon('camera') . '</span><span class="pgw-pht"><b>' . esc_html($lab) . '</b><small>' . esc_html($L('Необязательно, но очень приятно')) . '</small></span></label>';
    }
    if ($t[0] === 'seg') {
        $cur = $v !== '' ? $v : ($k === 'species' ? 'dog' : '');
        $h   = '<div class="pgw-f pgw-fseg" data-f="' . esc_attr($k) . '"><span class="pgw-l">' . esc_html($lab) . $star . '</span><div class="pgw-seg" role="radiogroup" aria-label="' . esc_attr($lab) . '"><i class="pgw-segi" aria-hidden="true"></i>';
        foreach ($t[1] as $o) {
            $h .= '<label><input type="radio" name="' . esc_attr($name) . '" value="' . esc_attr($o) . '"' . checked($cur, $o, false) . '><span>' . esc_html(petgo_pf_opt_label($o, true)) . '</span></label>';
        }
        return $h . '</div></div>';
    }
    $attr = ' name="' . esc_attr($name) . '" placeholder="' . esc_attr($ph) . '"' . ($req ? ' required' : '');
    $cls  = 'pgw-f' . ($k === 'species_other' ? ' pgw-other' : '');
    if ($t[0] === 'textarea') {
        return '<label class="' . $cls . ' pgw-fa" data-f="' . esc_attr($k) . '"><span class="pgw-l">' . esc_html($lab) . $star . '</span><textarea rows="2" maxlength="1000"' . $attr . '>' . esc_textarea($v) . '</textarea></label>';
    }
    $type = 'text';
    $more = ' maxlength="' . ($k === 'title' ? 60 : 160) . '"';
    if ($k === 'phone') {
        $type = 'tel';
        $more = ' maxlength="20" inputmode="tel" autocomplete="tel"';
    } elseif ($k === 'owner') {
        $more = ' maxlength="80" autocomplete="name"';
    } elseif ($k === 'weight') {
        $more = ' maxlength="10" inputmode="decimal"';
    } elseif ($k === 'address') {
        $more = ' maxlength="200" autocomplete="street-address"';
    }
    return '<label class="' . $cls . '" data-f="' . esc_attr($k) . '"><span class="pgw-l">' . esc_html($lab) . $star . '</span><input type="' . $type . '" value="' . esc_attr($v) . '"' . $attr . $more . '></label>';
}

/* строки для скрипта анкеты */
function petgo_pf_js_dict() {
    $o = array();
    foreach (array('Укажите кличку питомца.', 'Выберите, кто у вас.', 'Напишите, какое это животное.', 'Укажите имя.', 'Проверьте номер телефона.', 'Нужно согласие на обработку данных.',
        'Не получилось отправить — проверьте интернет и попробуйте ещё раз', 'Сайт ответил ошибкой (', '). Попробуйте ещё раз', 'Кличка', 'Кто', 'Порода', 'Возраст', 'Хозяин', 'Телефон', 'Адрес', 'Заполнено', 'не указано') as $s) {
        $o[$s] = petgo_site_l($s);
    }
    return $o;
}

/* ============================================================
   ПРИЁМ АНКЕТЫ (AJAX, без перезагрузки)
   ============================================================ */
add_action('wp_ajax_nopriv_petgo_pf_send', 'petgo_pf_send');
add_action('wp_ajax_petgo_pf_send', 'petgo_pf_send');
function petgo_pf_send() {
    nocache_headers();
    if (isset($_POST['lang'])) {
        petgo_site_lang(sanitize_key(wp_unslash($_POST['lang'])));
    }
    $L    = 'petgo_site_l';
    $fail = function ($msg, $field = '') {
        wp_send_json(array('ok' => false, 'msg' => $msg, 'field' => $field));
    };
    if (!empty($_POST['pgr_web'])) {
        wp_send_json(array('ok' => true));
    }
    if (function_exists('petgo_guard_same_origin') && !petgo_guard_same_origin()) {
        $fail($L('Обновите страницу и отправьте ещё раз.'));
    }
    $mode = (string) petgo_p('mode');
    $mode = in_array($mode, array('site', 'invite', 'client'), true) ? $mode : 'site';
    $inv  = null;
    $cl   = 0;
    if ($mode === 'client') {
        $cl = function_exists('petgo_cl_uid') ? petgo_cl_uid() : 0;
        if (!$cl || !wp_verify_nonce((string) petgo_p('n'), 'petgo_cl')) {
            $fail($L('Войдите в кабинет ещё раз.'));
        }
    } else {
        $tk = explode('.', (string) petgo_p('pgr_t'));
        if (count($tk) !== 2 || !ctype_digit($tk[0]) || !hash_equals(hash_hmac('sha256', $tk[0], wp_salt('auth')), $tk[1])) {
            $fail($L('Обновите страницу и отправьте ещё раз.'));
        }
        if (time() - (int) $tk[0] < 3) {
            $fail($L('Секунду… Нажмите «Отправить» ещё раз.'));
        }
        if ($mode === 'invite') {
            $inv = petgo_inv_get((string) petgo_p('t'));
            if (!$inv || $inv['pet']) {
                $fail($L('Ссылка-анкета уже использована или устарела. Попросите у нас новую.'));
            }
        }
        $ik = 'petgo_rq_' . md5(petgo_ip());
        if ((int) get_transient($ik) >= 6) {
            $fail(petgo_site_lf('Слишком много заявок с вашего адреса. Попробуйте позже или позвоните нам: %s.', petgo_site_phone_fmt()));
        }
        set_transient($ik, (int) get_transient($ik) + 1, HOUR_IN_SECONDS);
    }
    $d = petgo_pf_collect();
    if ($d['title'] === '') {
        $fail($L('Укажите кличку питомца.'), 'title');
    }
    if (!in_array($d['species'], array('dog', 'cat', 'other'), true)) {
        $fail($L('Выберите, кто у вас.'), 'f_species');
    }
    if ($d['species'] === 'other' && $d['species_other'] === '') {
        $fail($L('Напишите, какое это животное.'), 'f_species_other');
    }
    if ($mode === 'client') {
        $u = get_userdata($cl);
        if ($d['owner'] === '') {
            $d['owner'] = $u->display_name;
        }
        if ($d['phone'] === '') {
            $d['phone'] = (string) get_user_meta($cl, 'petgo_phone', true);
        }
    } else {
        if (mb_strlen($d['owner']) < 2) {
            $fail($L('Укажите имя.'), 'f_owner');
        }
        $ph = petgo_pf_phone($d['phone']);
        if ($ph === '') {
            $fail($L('Проверьте номер телефона.'), 'f_phone');
        }
        $d['phone'] = $ph;
        if (petgo_p('consent') !== '1') {
            $fail($L('Нужно согласие на обработку данных.'), 'consent');
        }
    }
    /* фото питомца */
    $av = null;
    if (isset($_FILES['avatar']) && !empty($_FILES['avatar']['name'])) {
        $r = petgo_save_image($_FILES['avatar'], 640, 72);
        if (is_array($r)) {
            $av = $r;
        } elseif ($r !== 'none') {
            $fail($L('Не удалось загрузить фото. Нужен JPG, PNG или WebP.'), 'avatar');
        }
    }

    if ($mode === 'site') {
        /* регистрация с сайта: в «Заявки», питомец добавляется сотрудником одним нажатием */
        global $wpdb;
        $extra = array(array('Тип', 'Регистрация питомца'), array('Кличка', $d['title']));
        if (petgo_site_lang() !== 'ru') {
            $extra[] = array('Язык', petgo_site_langs()[petgo_site_lang()][1]);
        }
        foreach (petgo_pet_fields() as $k => $f) {
            if (in_array($k, array('species', 'species_other', 'breed', 'weight', 'owner', 'phone', 'notes'), true) || $d[$k] === '') {
                continue;
            }
            $extra[] = array(petgo_pf_meta()[$k][0], petgo_val($k, $d[$k], $f[0]));
        }
        /* 34.5: откуда пришёл клиент (реклама, метки utm) */
        if (function_exists('petgo_src_pairs')) {
            $extra = array_merge($extra, petgo_src_pairs());
        }
        $pf = $d;
        if ($av) {
            $pf['_avatar'] = $av;
        }
        $wpdb->insert(petgo_tb('reqs'), array(
            'name' => $d['owner'], 'phone' => $d['phone'], 'contact' => 'write', 'species' => $d['species'], 'sp_other' => $d['species_other'],
            'weight' => mb_substr($d['weight'], 0, 20), 'breed' => mb_substr($d['breed'], 0, 80), 'note' => $d['notes'],
            'extra' => mb_substr(wp_json_encode($extra, JSON_UNESCAPED_UNICODE), 0, 6000), 'pf' => wp_json_encode($pf, JSON_UNESCAPED_UNICODE), 'status' => 'new', 'ts' => time(),
        ), array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'));
        if (!$wpdb->insert_id) {
            $fail(petgo_site_lf('Не получилось сохранить. Попробуйте ещё раз или позвоните нам: %s.', petgo_site_phone_fmt()));
        }
        petgo_bump();
        petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), 'Регистрация питомца', $d['title'] . ' · ' . $d['owner'] . ' · ' . $d['phone'], petgo_page_url(array('pg' => 'requests')));
        if (function_exists('petgo_event')) {
            petgo_event('pet_form', array('request' => (int) $wpdb->insert_id, 'mode' => 'site'));
        }
        wp_send_json(array('ok' => true));
    }

    if ($mode === 'client') {
        $pid = (int) petgo_p('pet_id');
        if ($pid && (get_post_type($pid) !== 'petgo_pet' || (int) get_post_meta($pid, '_client', true) !== $cl)) {
            $fail($L('Нет доступа.'));
        }
        $pid = petgo_pf_save_pet($d, $pid, $av, array('src' => 'client', 'client' => $cl));
        if (!$pid) {
            $fail($L('Не получилось сохранить. Попробуйте ещё раз.'));
        }
        /* 40.0: клиент добавил или изменил питомца — сотрудникам уведомление (делает «PetGo Клиент») */
        if (function_exists('petgo_event')) {
            petgo_event('pet_saved', array('user' => (int) $cl, 'pet' => (int) $pid, 'new' => !(int) petgo_p('pet_id')));
        }
        wp_send_json(array('ok' => true, 'pet' => $pid));
    }

    /* по ссылке-анкете: сразу в базу */
    $pid = petgo_pf_save_pet($d, 0, $av, array('src' => 'invite', 'client' => $inv['cl'] ? (int) $inv['cl'] : (function_exists('petgo_cl_uid') ? petgo_cl_uid() : 0)));
    if (!$pid) {
        $fail($L('Не получилось сохранить. Попробуйте ещё раз.'));
    }
    petgo_inv_done((string) petgo_p('t'), $pid);
    petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), 'Анкета по ссылке заполнена', $d['title'] . ' · ' . $d['owner'] . ' · ' . $d['phone'], petgo_page_url(array('pg' => 'pets')));
    wp_send_json(array('ok' => true));
}

