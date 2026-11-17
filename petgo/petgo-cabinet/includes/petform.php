<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   АНКЕТА ПИТОМЦА 34.0 — одна схема полей для всех мест:
   - сайт: «Зарегистрировать питомца» — карточки по шагам, попадает в «Заявки» (ждёт обработки);
   - ссылка-анкета из приложения (?t=…): питомец сразу добавляется в базу;
   - кабинет клиента: питомец сразу в базе и привязан к аккаунту клиента;
   - приложение: форма «Добавить / Изменить питомца» — разделы, одинаковые строки, переключатели.
   ============================================================ */

/* шаги анкеты: ключ => [значок, заголовок, подсказка, поля] */
function petgo_pf_steps() {
    return array(
        'pet'    => array('paw', 'Знакомство', 'Как зовут питомца и кто он', array('title', 'species', 'species_other', 'avatar')),
        'about'  => array('info', 'О питомце', 'Порода, возраст и пол', array('breed', 'sex', 'age', 'weight', 'neutered')),
        'health' => array('health', 'Здоровье', 'Прививки, болезни и лекарства', array('vaccines', 'parasites', 'health', 'meds')),
        'habits' => array('food', 'Питание и привычки', 'Что ест и что любит', array('food', 'toys', 'commands')),
        'behav'  => array('behav', 'Характер', 'Как ведёт себя дома и на улице', array('people', 'animals', 'fears', 'leash')),
        'owner'  => array('user', 'Хозяин', 'Как с вами связаться и как к вам попасть', array('owner', 'phone', 'address', 'access', 'emergency', 'vet')),
        'final'  => array('note', 'Последний штрих', 'Всё, что нам важно знать', array('notes')),
    );
}

/* подписи и подсказки для клиентов (на сайте). Названия для сотрудников — в словаре f_* */
function petgo_pf_meta() {
    return array(
        'title'         => array('Кличка', 'Например, Бублик'),
        'species'       => array('Кто это', ''),
        'species_other' => array('Какое животное', 'Например, кролик'),
        'avatar'        => array('Фото питомца', ''),
        'breed'         => array('Порода', 'Например, корги или метис'),
        'sex'           => array('Пол', ''),
        'age'           => array('Возраст', 'Например, 3 года'),
        'weight'        => array('Вес, кг', 'Например, 12'),
        'neutered'      => array('Кастрирован / стерилизована', ''),
        'vaccines'      => array('Прививки', 'Последняя — когда и какая'),
        'parasites'     => array('Обработка от паразитов', 'Когда и чем обрабатывали'),
        'health'        => array('Болезни и аллергии', 'На что обратить внимание'),
        'meds'          => array('Лекарства', 'Название, доза, время приёма'),
        'food'          => array('Корм и режим', 'Что ест, сколько и когда'),
        'toys'          => array('Игрушки и лакомства', 'Что любит больше всего'),
        'commands'      => array('Команды', 'Сидеть, рядом, ко мне…'),
        'people'        => array('С людьми', 'Дружелюбный, стесняется, лает…'),
        'animals'       => array('С животными', 'Как ведёт себя с собаками и кошками'),
        'fears'         => array('Страхи', 'Салюты, машины, громкие звуки…'),
        'leash'         => array('На прогулке', 'Тянет поводок, подбирает с земли?'),
        'owner'         => array('Ваше имя', 'Как к вам обращаться'),
        'phone'         => array('Телефон', '+7 700 000 00 00'),
        'address'       => array('Адрес', 'Улица, дом, квартира'),
        'access'        => array('Как попасть', 'Домофон, код, ключи, консьерж'),
        'emergency'     => array('Экстренный контакт', 'Имя и телефон близкого человека'),
        'vet'           => array('Ветклиника', 'Клиника и телефон врача'),
        'notes'         => array('Ещё о питомце', 'Привычки, особенности, пожелания'),
    );
}

/* тип поля: text, textarea, seg (варианты), photo */
function petgo_pf_type($k) {
    if ($k === 'title') {
        return array('text');
    }
    if ($k === 'avatar') {
        return array('photo');
    }
    $F = petgo_pet_fields();
    if (!isset($F[$k])) {
        return array('text');
    }
    return $F[$k][0] === 'select' ? array('seg', $F[$k][1]) : array($F[$k][0]);
}

function petgo_pf_opt_label($o, $site) {
    $m = array('dog' => 'Собака', 'cat' => 'Кошка', 'other' => 'Другой', 'boy' => 'Мальчик', 'girl' => 'Девочка', 'yes' => 'Да', 'no' => 'Нет');
    if ($site) {
        return petgo_site_l(isset($m[$o]) ? $m[$o] : $o);
    }
    return petgo_t('o_' . $o);
}

/* ---------- значки шагов (общий набор с сайтом и приложением) ---------- */
function petgo_pf_icon($n) {
    static $P = array(
        'paw'    => '<path d="M12 12.3c-2.7 0-4.8 2.1-4.8 4 0 1.7 1.4 2.4 2.8 2 .9-.3 1.4-.6 2-.6s1.1.3 2 .6c1.4.4 2.8-.3 2.8-2 0-1.9-2.1-4-4.8-4z"/><ellipse cx="6.6" cy="10" rx="1.5" ry="2"/><ellipse cx="9.9" cy="6.5" rx="1.5" ry="2.1"/><ellipse cx="14.1" cy="6.5" rx="1.5" ry="2.1"/><ellipse cx="17.4" cy="10" rx="1.5" ry="2"/>',
        'info'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.8h.01"/>',
        'health' => '<path d="M12 20s-7-4.4-7-10a4.2 4.2 0 0 1 7-3 4.2 4.2 0 0 1 7 3c0 5.6-7 10-7 10z"/><path d="M9.5 11.5h5M12 9v5"/>',
        'food'   => '<path d="M4 11h16a8 8 0 0 1-16 0z"/><path d="M8 7c0-1.6 1-1.6 1-3.2M12 7c0-1.6 1-1.6 1-3.2M16 7c0-1.6 1-1.6 1-3.2"/>',
        'behav'  => '<circle cx="12" cy="12" r="8.5"/><path d="M8.5 14c1 1.5 2.2 2 3.5 2s2.5-.5 3.5-2M9 9.5h.01M15 9.5h.01"/>',
        'user'   => '<circle cx="12" cy="8.2" r="3.7"/><path d="M4.5 20c0-4 3.3-6.3 7.5-6.3s7.5 2.3 7.5 6.3"/>',
        'note'   => '<path d="M7 3h8l4 4v14H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/>',
        'camera' => '<path d="M4 8.5A1.5 1.5 0 0 1 5.5 7H8l1.5-2h5L16 7h2.5A1.5 1.5 0 0 1 20 8.5v9a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 17.5z"/><circle cx="12" cy="13" r="3.3"/>',
        'left'   => '<path d="M15 5l-7 7 7 7"/>',
        'right'  => '<path d="M9 5l7 7-7 7"/>',
        'check'  => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'link'   => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
        'copy'   => '<rect x="8" y="8" width="12" height="12" rx="2.5"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
        'share'  => '<path d="M12 3v12M7.5 7.5 12 3l4.5 4.5"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/>',
        'wa'     => '<path d="M3.5 20.5l1.3-4.4A8.5 8.5 0 1 1 8 19.3z"/><path d="M9 8.5c0 3.5 3 6.5 6.5 6.5l1-1.6-2-1-1 .8c-1-.5-2-1.5-2.5-2.5l.8-1-1-2z"/>',
    );
    return isset($P[$n]) ? '<svg class="pgw-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $P[$n] . '</svg>' : '';
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
        if (!(int) petgo_p('pet_id')) {
            petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), 'Клиент добавил питомца', $d['title'] . ' · ' . $d['owner'], petgo_page_url(array('pg' => 'pets')));
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

function petgo_pf_phone($raw) {
    $d = preg_replace('/\D/', '', (string) $raw);
    if (strlen($d) === 11 && $d[0] === '8') {
        $d = '7' . substr($d, 1);
    }
    if (strlen($d) === 10) {
        $d = '7' . $d;
    }
    return (strlen($d) < 11 || strlen($d) > 15) ? '' : '+' . $d;
}

/* все поля анкеты из запроса */
function petgo_pf_collect() {
    $d = array('title' => mb_substr(sanitize_text_field((string) petgo_p('title')), 0, 60));
    foreach (petgo_pet_fields() as $k => $f) {
        $v     = petgo_p('f_' . $k);
        $v     = is_string($v) ? $v : '';
        $d[$k] = $f[0] === 'textarea' ? mb_substr(sanitize_textarea_field($v), 0, 1000) : mb_substr(sanitize_text_field($v), 0, 200);
        if ($f[0] === 'select' && !in_array($d[$k], $f[1], true)) {
            $d[$k] = '';
        }
    }
    if ($d['species'] !== 'other') {
        $d['species_other'] = '';
    }
    return $d;
}

/* создать или обновить питомца из данных анкеты */
function petgo_pf_save_pet($d, $pid, $av, $o = array()) {
    $title = isset($d['title']) ? (string) $d['title'] : '';
    if ($title === '') {
        return 0;
    }
    if ($pid && get_post_type($pid) === 'petgo_pet') {
        wp_update_post(array('ID' => $pid, 'post_title' => $title));
    } else {
        $pid = wp_insert_post(array('post_type' => 'petgo_pet', 'post_status' => 'publish', 'post_title' => $title));
        if (!$pid || is_wp_error($pid)) {
            return 0;
        }
        update_post_meta($pid, '_src', isset($o['src']) ? $o['src'] : 'app');
        update_post_meta($pid, '_created', time());
    }
    foreach (petgo_pet_fields() as $k => $f) {
        if (isset($d[$k])) {
            update_post_meta($pid, '_pf_' . $k, (string) $d[$k]);
        }
    }
    if (is_array($av) && !empty($av['url'])) {
        $old = get_post_meta($pid, '_avatar', true);
        if (is_array($old) && !empty($old['file'])) {
            petgo_rm($old['file']);
        }
        update_post_meta($pid, '_avatar', $av);
    }
    if (function_exists('petgo_pet_nick')) {
        petgo_pet_nick($pid);
    }
    $cl = isset($o['client']) ? (int) $o['client'] : 0;
    if (!$cl && function_exists('petgo_cl_by_phone') && !empty($d['phone'])) {
        $cl = petgo_cl_by_phone((string) $d['phone']);
    }
    if ($cl && !(int) get_post_meta($pid, '_client', true)) {
        update_post_meta($pid, '_client', $cl);
    }
    petgo_bump();
    return (int) $pid;
}

/* ============================================================
   ЗАЯВКА → ПИТОМЕЦ ОДНИМ НАЖАТИЕМ (раздел «Заявки» в приложении)
   ============================================================ */
add_action('admin_post_petgo_req_addpet', 'petgo_h_req_addpet');
function petgo_h_req_addpet() {
    petgo_guard('office');
    global $wpdb;
    $id = (int) petgo_p('id');
    $r  = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . petgo_tb('reqs') . ' WHERE id=%d', $id));
    if (!$r) {
        petgo_fail('err_na', 'requests');
    }
    if ((int) $r->pet_id && get_post_type((int) $r->pet_id) === 'petgo_pet') {
        petgo_flash(petgo_t('rq_pet_added'));
        petgo_back('requests');
    }
    $pf = (isset($r->pf) && $r->pf !== '') ? json_decode($r->pf, true) : null;
    $av = null;
    if (is_array($pf)) {
        $d  = $pf;
        $av = isset($pf['_avatar']) && is_array($pf['_avatar']) ? $pf['_avatar'] : null;
    } else {
        /* старая заявка: берём то, что есть */
        $pre = petgo_req_prefill($id);
        $d   = array();
        foreach (petgo_pet_fields() as $k => $f) {
            $d[$k] = isset($pre[$k]) ? (string) $pre[$k] : '';
        }
        $nick = '';
        $xt   = ($r->extra !== null && $r->extra !== '') ? json_decode($r->extra, true) : array();
        foreach ((array) $xt as $xp) {
            if (is_array($xp) && count($xp) === 2 && $xp[0] === 'Кличка') {
                $nick = (string) $xp[1];
            }
        }
        $d['title'] = $nick !== '' ? $nick : ($r->breed !== '' ? $r->breed : petgo_t('pet') . ' · ' . $r->name);
    }
    $d['title'] = sanitize_text_field((string) (isset($d['title']) ? $d['title'] : ''));
    foreach (petgo_pet_fields() as $k => $f) {
        $d[$k] = isset($d[$k]) ? ($f[0] === 'textarea' ? sanitize_textarea_field((string) $d[$k]) : sanitize_text_field((string) $d[$k])) : '';
    }
    $pid = petgo_pf_save_pet($d, 0, $av, array('src' => 'site'));
    if (!$pid) {
        petgo_fail('err_fields', 'requests');
    }
    $wpdb->update(petgo_tb('reqs'), array('status' => 'done', 'pet_id' => $pid), array('id' => $id), array('%s', '%d'), array('%d'));
    petgo_audit('pet_from_req', 'req=' . $id . ' pet=' . $pid);
    petgo_flash(petgo_t('pf_added'));
    petgo_back('requests', array('rs' => 'done'));
}

/* ============================================================
   ПРИЛОЖЕНИЕ: форма питомца — разделы, одинаковые строки, переключатели вместо списков
   ============================================================ */
function petgo_pf_app_form($pid, $pre, $fr = 0) {
    $val = function ($k) use ($pid, $pre) {
        if ($pid) {
            return $k === 'title' ? get_the_title($pid) : (string) get_post_meta($pid, '_pf_' . $k, true);
        }
        return isset($pre[$k]) ? (string) $pre[$k] : '';
    };
    $M  = petgo_pf_meta();
    $b  = petgo_form_open('pet_save', 'enctype="multipart/form-data" class="pgf"') . '<input type="hidden" name="pet_id" value="' . (int) $pid . '"><input type="hidden" name="from_req" value="' . (int) $fr . '">';
    if ($fr) {
        $b .= '<div class="pgc-tip">' . esc_html(petgo_t('rq_from')) . '</div>';
    }
    $name = $pid ? get_the_title($pid) : '';
    $b .= '<label class="pgf-av">' . petgo_av($pid ? petgo_pet_avatar_url($pid) : '', $name !== '' ? $name : '+', 'xl') . '<span>' . esc_html(petgo_t('av_change')) . '</span><input class="pgc-vh" type="file" name="avatar" accept="image/*" data-preview></label>';
    if ($pid && function_exists('petgo_pet_ids_html')) {
        $b .= petgo_pet_ids_html($pid);
    }
    foreach (petgo_pf_steps() as $sk => $st) {
        $rows = '';
        foreach ($st[3] as $k) {
            if ($k === 'avatar') {
                continue;
            }
            $t    = petgo_pf_type($k);
            $lab  = $k === 'title' ? petgo_t('pet_name') : petgo_t('f_' . $k);
            $nm   = $k === 'title' ? 'title' : 'f_' . $k;
            $v    = $val($k);
            $ph   = esc_attr(isset($M[$k]) ? $M[$k][1] : '');
            if ($t[0] === 'seg') {
                $rows .= '<div class="pgf-row pgf-rseg' . ($k === 'species' ? ' pgf-sp' : '') . '"><span>' . esc_html($lab) . '</span><div class="pgf-seg">';
                if ($k !== 'species') {
                    $rows .= '<label><input type="radio" name="' . $nm . '" value=""' . checked($v, '', false) . '><span>—</span></label>';
                }
                foreach ($t[1] as $o) {
                    $rows .= '<label><input type="radio" name="' . $nm . '" value="' . esc_attr($o) . '"' . checked($k === 'species' && $v === '' ? 'dog' : $v, $o, false) . '><span>' . esc_html(petgo_t('o_' . $o)) . '</span></label>';
                }
                $rows .= '</div></div>';
            } elseif ($t[0] === 'textarea') {
                $rows .= '<label class="pgf-row pgf-ra"><span>' . esc_html($lab) . '</span><textarea name="' . $nm . '" rows="2" placeholder="' . $ph . '">' . esc_textarea($v) . '</textarea></label>';
            } else {
                $type = $k === 'phone' ? 'tel' : 'text';
                $rows .= '<label class="pgf-row' . ($k === 'species_other' ? ' pgf-oth' : '') . '"><span>' . esc_html($lab) . '</span><input type="' . $type . '" name="' . $nm . '" value="' . esc_attr($v) . '" placeholder="' . $ph . '"' . ($k === 'title' ? ' required' : '') . '></label>';
            }
        }
        $b .= '<div class="pgf-sec"><div class="pgf-h">' . petgo_pf_icon($st[0]) . '<span>' . esc_html(petgo_pf_app_sec($sk)) . '</span></div><div class="pgf-list">' . $rows . '</div></div>';
    }
    $b .= '<button class="pgc-btn wide lg pgf-go" type="submit">' . esc_html(petgo_t('save')) . '</button></form>';
    return $b;
}

function petgo_pf_app_sec($k) {
    $m = array('pet' => 'pf_s_pet', 'about' => 'sec_pet', 'health' => 'sec_health', 'habits' => 'sec_food', 'behav' => 'sec_behav', 'owner' => 'pf_s_owner', 'final' => 'sec_other');
    return petgo_t($m[$k]);
}

/* ============================================================
   ССЫЛКИ-АНКЕТЫ: сотрудник создаёт ссылку в приложении и отправляет клиенту (WhatsApp и т. д.).
   Клиент заполняет анкету — питомец сразу появляется в базе.
   ============================================================ */
function petgo_inv_all() {
    $a = get_option('petgo_invites', array());
    return is_array($a) ? $a : array();
}

function petgo_inv_get($t) {
    if (!preg_match('/^[A-Za-z0-9]{16}$/', (string) $t)) {
        return null;
    }
    $a = petgo_inv_all();
    if (!isset($a[$t])) {
        return null;
    }
    $i = array_merge(array('t' => 0, 'by' => 0, 'note' => '', 'phone' => '', 'pet' => 0, 'cl' => 0), (array) $a[$t]);
    if (!$i['pet'] && time() - (int) $i['t'] > 45 * DAY_IN_SECONDS) {
        return null;
    }
    return $i;
}

function petgo_inv_url($t) {
    return add_query_arg('t', $t, petgo_site_url('join'));
}

function petgo_inv_done($t, $pid) {
    $a = petgo_inv_all();
    if (isset($a[$t])) {
        $a[$t]['pet']  = (int) $pid;
        $a[$t]['done'] = time();
        update_option('petgo_invites', $a, false);
    }
}

add_action('admin_post_petgo_inv_new', 'petgo_h_inv_new');
function petgo_h_inv_new() {
    petgo_guard('office');
    $a = petgo_inv_all();
    /* старые использованные и просроченные ссылки чистим, храним не больше 300 */
    foreach ($a as $k => $i) {
        if (time() - (int) $i['t'] > 90 * DAY_IN_SECONDS) {
            unset($a[$k]);
        }
    }
    $a = array_slice($a, -299, null, true);
    $t = wp_generate_password(16, false);
    $a[$t] = array('t' => time(), 'by' => get_current_user_id(), 'note' => mb_substr(sanitize_text_field((string) petgo_p('note')), 0, 80),
        'phone' => petgo_pf_phone((string) petgo_p('phone')), 'pet' => 0, 'cl' => (int) petgo_p('cl'));
    update_option('petgo_invites', $a, false);
    petgo_audit('invite_new', $a[$t]['note']);
    petgo_back('pets', array('inv' => $t));
}

add_action('admin_post_petgo_inv_del', 'petgo_h_inv_del');
function petgo_h_inv_del() {
    petgo_guard('office');
    $a = petgo_inv_all();
    unset($a[(string) petgo_p('t')]);
    update_option('petgo_invites', $a, false);
    petgo_flash(petgo_t('ok_deleted'));
    petgo_back('pets');
}

/* текст и ссылки «поделиться» для готовой ссылки-анкеты */
function petgo_inv_share($t) {
    $i    = petgo_inv_get($t);
    $url  = petgo_inv_url($t);
    $text = 'Здравствуйте! Это PetGo 🐾 Заполните, пожалуйста, анкету вашего питомца — это займёт пару минут: ' . $url;
    $ph   = $i && $i['phone'] !== '' ? preg_replace('/\D/', '', $i['phone']) : '';
    $wa   = 'https://wa.me/' . $ph . '?text=' . rawurlencode($text);
    $h    = '<div class="pgi-share"><div class="pgi-link"><span class="pgi-li">' . petgo_pf_icon('link') . '</span><input type="text" readonly value="' . esc_attr($url) . '" onfocus="this.select()"></div>';
    $h   .= '<div class="pgi-btns"><button type="button" class="pgc-btn sec" data-copy="' . esc_attr($url) . '" data-done="' . esc_attr(petgo_t('pf_copied')) . '">' . petgo_pf_icon('copy') . '<span>' . esc_html(petgo_t('pf_copy')) . '</span></button>';
    $h   .= '<a class="pgc-btn pgi-wa" target="_blank" rel="noopener" href="' . esc_url($wa) . '">' . petgo_pf_icon('wa') . '<span>WhatsApp</span></a>';
    $h   .= '<button type="button" class="pgc-btn sec" data-share="' . esc_attr($url) . '" data-text="' . esc_attr($text) . '" hidden>' . petgo_pf_icon('share') . '<span>' . esc_html(petgo_t('pf_share')) . '</span></button></div></div>';
    return $h;
}

/* окно «Ссылка-анкета для клиента» */
function petgo_inv_modal() {
    $t = isset($_GET['inv']) ? sanitize_text_field(wp_unslash($_GET['inv'])) : '';
    if ($t !== '' && petgo_inv_get($t)) {
        $b = '<div class="pgi-ready"><span class="pgi-big">' . petgo_pf_icon('check') . '</span><p>' . esc_html(petgo_t('pf_inv_ready')) . '</p></div>' . petgo_inv_share($t);
        echo petgo_modal('pgc-m-invok', petgo_t('pf_inv_t'), $b, true);
    }
    $b  = '<p class="mu pgi-hint">' . esc_html(petgo_t('pf_inv_hint')) . '</p>' . petgo_form_open('inv_new') . '<div class="pgf-list">';
    $b .= '<label class="pgf-row"><span>' . esc_html(petgo_t('pf_inv_for')) . '</span><input type="text" name="note" maxlength="80" placeholder="' . esc_attr(petgo_t('pf_inv_for_ph')) . '"></label>';
    $b .= '<label class="pgf-row"><span>' . esc_html(petgo_t('phone')) . '</span><input type="tel" name="phone" maxlength="20" placeholder="+7 700 000 00 00"></label></div>';
    $b .= '<button class="pgc-btn wide lg" type="submit" style="margin-top:16px">' . petgo_pf_icon('link') . esc_html(petgo_t('pf_inv_make')) . '</button></form>';
    echo petgo_modal('pgc-m-inv', petgo_t('pf_inv_t'), $b);
}

/* список ссылок-анкет на странице «Питомцы» */
function petgo_inv_list_html() {
    $a = petgo_inv_all();
    if (!$a) {
        return '';
    }
    $wait = 0;
    $rows = '';
    foreach (array_reverse($a, true) as $t => $i) {
        $i = petgo_inv_get($t);
        if (!$i) {
            continue;
        }
        if (!$i['pet']) {
            $wait++;
        }
        if ($i['pet'] && time() - (int) $i['t'] > 14 * DAY_IN_SECONDS) {
            continue;
        }
        $by    = get_userdata((int) $i['by']);
        $title = $i['note'] !== '' ? $i['note'] : ($i['phone'] !== '' ? $i['phone'] : petgo_t('pf_inv_noname'));
        $rows .= '<div class="pgi-row"><span class="pgi-st ' . ($i['pet'] ? 'ok' : 'wait') . '">' . petgo_pf_icon($i['pet'] ? 'check' : 'link') . '</span><div class="pgc-sm"><b>' . esc_html($title) . '</b><small>' . esc_html(petgo_ago((int) $i['t']) . ($by ? ' · ' . $by->display_name : '') . ' · ' . ($i['pet'] ? petgo_t('pf_inv_filled') . ': ' . get_the_title((int) $i['pet']) : petgo_t('pf_inv_wait'))) . '</small></div>';
        if (!$i['pet']) {
            $rows .= '<button type="button" class="pgc-btn sec sm" data-copy="' . esc_attr(petgo_inv_url($t)) . '" data-done="' . esc_attr(petgo_t('pf_copied')) . '">' . petgo_pf_icon('copy') . '</button>' . petgo_btn_form('inv_del', array('t' => $t), '×', 'pgc-btn sec sm', petgo_t('q_delete_inv'));
        }
        $rows .= '</div>';
    }
    if ($rows === '') {
        return '';
    }
    return '<details class="pgc-card pgi-list"' . ($wait ? ' open' : '') . '><summary><span class="pgi-li">' . petgo_pf_icon('link') . '</span><b>' . esc_html(petgo_t('pf_inv_list')) . '</b>' . ($wait ? '<i class="pgc-bd">' . $wait . '</i>' : '') . '</summary>' . $rows . '</details>';
}
