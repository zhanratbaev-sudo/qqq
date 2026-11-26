<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   КАБИНЕТ КЛИЕНТА 34.0 (страница сайта «Кабинет», /lk/)
   - регистрация: имя, телефон, логин, пароль; вход по логину (или телефону) и паролю;
   - забыли пароль — заявка менеджеру, менеджер ставит новый пароль в приложении (раздел «Клиенты»);
   - у клиента и у каждого питомца свой ID и никнейм; свои фото (аватар клиента и питомцев);
   - питомцы клиента, карта: где питомец сейчас (живая прогулка с телефона няни), где были все прогулки, фото на карте;
   - история прогулок и отзыв няне после каждой прогулки;
   - «Для вас»: рекомендация менеджера, блог PetGo, групповые прогулки и акции; чат с менеджером.
   ============================================================ */














/* ---------- клиенты не попадают в админку WordPress и в приложение сотрудников ---------- */
add_action('admin_init', 'petgo_cl_block_admin', 1);
function petgo_cl_block_admin() {
    global $pagenow;
    if (petgo_cl_uid() && !wp_doing_ajax() && $pagenow !== 'admin-post.php') {
        wp_safe_redirect(petgo_site_url('account'));
        exit;
    }
}

add_filter('show_admin_bar', function ($s) {
    return petgo_cl_uid() ? false : $s;
}, 30);

add_filter('login_redirect', function ($to, $req, $user) {
    return petgo_cl_is($user) ? petgo_site_url('account') : $to;
}, 20, 3);

add_action('template_redirect', function () {
    if (petgo_cl_uid() && function_exists('petgo_is_cabinet_page') && petgo_is_cabinet_page()) {
        wp_safe_redirect(petgo_site_url('account'));
        exit;
    }
    /* кабинет клиента и анкета по ссылке не кэшируются */
    $r = function_exists('petgo_site_route') ? petgo_site_route() : '';
    if ($r === 'join' || ($r === 'account' && is_user_logged_in())) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
        header('X-LiteSpeed-Cache-Control: no-cache');
    }
}, 1);

/* 36.2: язык клиента — выбранный в кабинете или на сайте запоминается; без ?lang= в адресе сайт и кабинет открываются на нём */
add_action('init', 'petgo_cl_lang_pref', 1);
function petgo_cl_lang_pref() {
    $uid = function_exists('petgo_cl_uid') ? petgo_cl_uid() : 0;
    if (!$uid || !function_exists('petgo_site_langs')) {
        return;
    }
    $all = petgo_site_langs();
    $g   = isset($_GET['lang']) ? sanitize_key(wp_unslash($_GET['lang'])) : '';
    $g   = $g === 'kk' ? 'kz' : $g;
    if ($g !== '' && isset($all[$g])) {
        if (get_user_meta($uid, 'petgo_lang', true) !== $g) {
            update_user_meta($uid, 'petgo_lang', $g);
        }
        return;
    }
    if (isset($_POST['lang'])) {
        return;
    }
    $m = (string) get_user_meta($uid, 'petgo_lang', true);
    if ($m !== '' && isset($all[$m])) {
        petgo_site_lang($m);
    }
}

/* клиент остаётся в кабинете полгода */
add_filter('auth_cookie_expiration', function ($len, $uid) {
    $u = get_userdata((int) $uid);
    return petgo_cl_is($u) ? 180 * DAY_IN_SECONDS : $len;
}, 20, 2);

/* ============================================================
   ЧАТ КЛИЕНТА С МЕНЕДЖЕРОМ (общие таблицы чатов; у сотрудников он появляется в «Чатах»)
   ============================================================ */



/* ============================================================
   ВХОД, РЕГИСТРАЦИЯ, «ЗАБЫЛИ ПАРОЛЬ»
   ============================================================ */
function petgo_cl_json($ok, $extra = array()) {
    wp_send_json(array_merge(array('ok' => (bool) $ok), $extra));
}

function petgo_cl_ip_limit($key, $max, $ttl) {
    $k = 'petgo_cl' . $key . '_' . md5(petgo_ip());
    $n = (int) get_transient($k);
    if ($n >= $max) {
        return false;
    }
    set_transient($k, $n + 1, $ttl);
    return true;
}

function petgo_cl_lang_from_post() {
    if (isset($_POST['lang']) && function_exists('petgo_site_lang')) {
        petgo_site_lang(sanitize_key(wp_unslash($_POST['lang'])));
    }
}

add_action('wp_ajax_nopriv_petgo_cl_reg', 'petgo_cl_reg');
add_action('wp_ajax_petgo_cl_reg', 'petgo_cl_reg');
function petgo_cl_reg() {
    nocache_headers();
    petgo_cl_lang_from_post();
    $L = 'petgo_site_l';
    if (!empty($_POST['pgr_web'])) {
        petgo_cl_json(true);
    }
    if (!petgo_guard_same_origin()) {
        petgo_cl_json(false, array('msg' => $L('Обновите страницу и попробуйте ещё раз.')));
    }
    if (is_user_logged_in()) {
        petgo_cl_json(false, array('msg' => $L('Вы уже вошли. Обновите страницу.')));
    }
    $name  = mb_substr(sanitize_text_field((string) petgo_p('name')), 0, 60);
    $phone = petgo_pf_phone((string) petgo_p('phone'));
    $login = strtolower(trim((string) petgo_p('login')));
    $pass  = (string) petgo_p('pass');
    if (mb_strlen($name) < 2) {
        petgo_cl_json(false, array('msg' => $L('Укажите имя.'), 'field' => 'name'));
    }
    if ($phone === '') {
        petgo_cl_json(false, array('msg' => $L('Проверьте номер телефона.'), 'field' => 'phone'));
    }
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,29}$/', $login)) {
        petgo_cl_json(false, array('msg' => $L('Логин: от 3 до 30 латинских букв или цифр.'), 'field' => 'login'));
    }
    if (strlen($pass) < 8) {
        petgo_cl_json(false, array('msg' => $L('Пароль — не короче 8 символов.'), 'field' => 'pass'));
    }
    if (petgo_p('consent') !== '1') {
        petgo_cl_json(false, array('msg' => $L('Нужно согласие на обработку данных.'), 'field' => 'consent'));
    }
    if (username_exists($login)) {
        petgo_cl_json(false, array('msg' => $L('Такой логин уже занят. Придумайте другой.'), 'field' => 'login'));
    }
    if (petgo_cl_by_phone($phone)) {
        petgo_cl_json(false, array('msg' => $L('С этим номером уже есть кабинет. Войдите или напишите менеджеру, если забыли пароль.'), 'field' => 'phone'));
    }
    if (!petgo_cl_ip_limit('r', 5, HOUR_IN_SECONDS)) {
        petgo_cl_json(false, array('msg' => $L('Слишком много попыток. Попробуйте позже.')));
    }
    $uid = wp_insert_user(array('user_login' => $login, 'user_pass' => $pass, 'user_email' => '', 'display_name' => $name, 'first_name' => $name, 'role' => 'petgo_client'));
    if (is_wp_error($uid)) {
        petgo_cl_json(false, array('msg' => $uid->get_error_message()));
    }
    update_user_meta($uid, 'petgo_phone', $phone);
    update_user_meta($uid, 'petgo_phone_d', petgo_phone_digits($phone));
    update_user_meta($uid, 'petgo_cl_since', time());
    if (function_exists('petgo_site_lang')) {
        update_user_meta($uid, 'petgo_cl_lang', petgo_site_lang());
    }
    $linked = petgo_cl_link_pets($uid);
    wp_set_current_user($uid);
    wp_set_auth_cookie($uid, true, is_ssl());
    petgo_cl_chat($uid);
    petgo_audit('client_reg', $login . ' pets=' . $linked, $uid);
    /* 34.5: откуда пришёл клиент (реклама, метки utm) — видно в карточке клиента */
    $src = function_exists('petgo_src_read') ? petgo_src_read() : array();
    if ($src) {
        update_user_meta($uid, 'petgo_src', $src);
    }
    /* 36.0: новый клиент — без отдельного уведомления, он в утренней сводке и в «Клиентах» */
    if (function_exists('petgo_event')) {
        petgo_event('sign_up', array('user' => (int) $uid));
    }
    petgo_cl_json(true, array('go' => petgo_site_url('account'), 'ev' => 'sign_up'));
}

add_action('wp_ajax_nopriv_petgo_cl_login', 'petgo_cl_login');
add_action('wp_ajax_petgo_cl_login', 'petgo_cl_login');
function petgo_cl_login() {
    nocache_headers();
    petgo_cl_lang_from_post();
    $L = 'petgo_site_l';
    if (!petgo_guard_same_origin()) {
        petgo_cl_json(false, array('msg' => $L('Обновите страницу и попробуйте ещё раз.')));
    }
    $log  = trim((string) petgo_p('login'));
    $pass = (string) petgo_p('pass');
    if ($log === '' || $pass === '') {
        petgo_cl_json(false, array('msg' => $L('Введите логин и пароль.')));
    }
    /* вход по номеру телефона */
    if (preg_match('/^[\d\s()+-]{10,}$/', $log)) {
        $id = petgo_cl_by_phone($log);
        $u  = $id ? get_userdata($id) : false;
        if ($u) {
            $log = $u->user_login;
        }
    }
    $u = wp_signon(array('user_login' => $log, 'user_password' => $pass, 'remember' => true), is_ssl());
    if (is_wp_error($u)) {
        $c = $u->get_error_code();
        petgo_cl_json(false, array('msg' => $c === 'petgo_locked' ? $L('Слишком много попыток. Подождите 15 минут или напишите менеджеру.') : ($c === 'petgo_off' ? $L('Доступ отключён. Напишите менеджеру.') : $L('Неверный логин или пароль.'))));
    }
    if (petgo_cl_is($u)) {
        petgo_cl_json(true, array('go' => petgo_site_url('account')));
    }
    /* сотрудник вошёл через кабинет клиента — отправляем в приложение */
    petgo_cl_json(true, array('go' => petgo_page_url()));
}

add_action('wp_ajax_nopriv_petgo_cl_forgot', 'petgo_cl_forgot');
add_action('wp_ajax_petgo_cl_forgot', 'petgo_cl_forgot');
function petgo_cl_forgot() {
    nocache_headers();
    petgo_cl_lang_from_post();
    $L = 'petgo_site_l';
    if (!petgo_guard_same_origin() || !empty($_POST['pgr_web'])) {
        petgo_cl_json(false, array('msg' => $L('Обновите страницу и попробуйте ещё раз.')));
    }
    $who   = mb_substr(sanitize_text_field((string) petgo_p('who')), 0, 80);
    $phone = petgo_pf_phone((string) petgo_p('phone'));
    if ($phone === '') {
        petgo_cl_json(false, array('msg' => $L('Проверьте номер телефона.'), 'field' => 'phone'));
    }
    if (!petgo_cl_ip_limit('f', 4, HOUR_IN_SECONDS)) {
        petgo_cl_json(false, array('msg' => $L('Заявка уже у менеджера. Мы скоро свяжемся с вами.')));
    }
    $id = petgo_cl_by_phone($phone);
    $u  = $id ? get_userdata($id) : false;
    global $wpdb;
    $extra = array(array('Тип', 'Восстановление доступа к кабинету'), array('Логин', $u ? $u->user_login . ' (' . petgo_cl_code($u->ID) . ')' : ($who !== '' ? $who : 'не найден по номеру')));
    $wpdb->insert(petgo_tb('reqs'), array('name' => $u ? $u->display_name : ($who !== '' ? $who : '—'), 'phone' => $phone, 'contact' => 'write', 'species' => '', 'sp_other' => '', 'weight' => '', 'breed' => '',
        'note' => 'Клиент забыл логин или пароль. Новый пароль ставится в приложении: Клиенты → карточка клиента → «Новый пароль».', 'extra' => wp_json_encode($extra, JSON_UNESCAPED_UNICODE), 'status' => 'new', 'ts' => time()),
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'));
    petgo_bump();
    petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), 'Клиент забыл пароль', ($u ? $u->display_name . ' · ' : '') . $phone, petgo_page_url(array('pg' => $u ? 'clients' : 'requests')));
    $text = petgo_site_lf('Здравствуйте! Я забыл(а) логин или пароль от кабинета PetGo. Мой номер: %s', $phone);
    petgo_cl_json(true, array('wa' => 'https://wa.me/' . petgo_site_wa() . '?text=' . rawurlencode($text)));
}

/* ============================================================
   AJAX КАБИНЕТА (только для вошедшего клиента)
   ============================================================ */
add_action('wp_ajax_petgo_cl', 'petgo_cl_ajax');
function petgo_cl_ajax() {
    nocache_headers();
    petgo_cl_lang_from_post();
    $uid = petgo_cl_uid();
    if (!$uid || !check_ajax_referer('petgo_cl', 'n', false)) {
        petgo_cl_json(false, array('msg' => petgo_site_l('Войдите в кабинет ещё раз.'), 'auth' => 1));
    }
    /* защита: не больше 240 запросов за 10 минут с одного кабинета */
    if (!petgo_cl_ip_limit('q' . $uid, 240, 10 * MINUTE_IN_SECONDS)) {
        petgo_cl_json(false, array('msg' => petgo_site_l('Слишком много запросов. Подождите минуту.')));
    }
    $op = sanitize_key((string) petgo_p('op'));
    $L  = 'petgo_site_l';
    global $wpdb;

    if ($op === 'live') {
        /* 34.6: вместе со статусами питомцев — «Ближайшие» и отметка, изменились ли прогулки (заказ выполнен, отменён…) */
        $out = array('pets' => petgo_cl_pets_state($uid), 'u' => petgo_cl_unread($uid));
        if (function_exists('petgo_cl_upcoming_html')) {
            $out['up'] = petgo_cl_upcoming_html($uid);
            $out['wk'] = petgo_cl_walks_sig($uid);
            $out['now'] = petgo_cl_now_html($uid);
        }
        /* 36.0: няня у двери → у клиента сам открывается QR; пока идёт заказ — обновление чаще */
        $out = array_merge($out, petgo_cl_door_state($uid));
        /* 38.1: последнее уведомление — точка на колокольчике */
        if (function_exists('petgo_cl_notif_last')) {
            $out['nl'] = petgo_cl_notif_last($uid);
        }
        petgo_cl_json(true, $out);
    }
    /* 34.7: заказ — подробности и QR-код для няни (действует 24 часа, потом сам меняется) */
    if ($op === 'order') {
        $oid = (int) petgo_p('order');
        if (get_post_type($oid) !== 'petgo_order' || !petgo_cl_owns($uid, (int) petgo_o($oid, 'pet'))) {
            petgo_cl_json(false, array('msg' => $L('Нет доступа.')));
        }
        $st   = petgo_o($oid, 'status');
        $walk = petgo_o($oid, 'service') === 'walk';
        $ts   = (int) petgo_o($oid, 'ts');
        $pid  = (int) petgo_o($oid, 'pet');
        /* 34.8: QR нужен дважды — когда няня забирает (приходит) и когда возвращает (уходит) */
        $phase = $walk ? (in_array($st, array('new', 'assigned', 'arrived'), true) ? 'pick' : ($st === 'picked' ? 'back' : '')) : (in_array($st, array('new', 'assigned'), true) ? 'arrive' : ($st === 'arrived' ? 'leave' : ''));
        $show = function_exists('petgo_qr') && $phase !== '';
        $q    = $show ? petgo_qr($oid) : null;
        $nid  = (int) petgo_o($oid, 'nanny');
        $out  = array(
            'id' => $oid, 'pet' => get_the_title($pid), 'pid' => $pid, 'av' => petgo_pet_avatar_url($pid), 'svc' => petgo_cl_svc_label(petgo_o($oid, 'service')),
            'when' => wp_date('d.m · H:i', $ts, petgo_tz()), 'dur' => max(1, (int) petgo_o($oid, 'dur')), 'st' => $st, 'stl' => petgo_cl_status_label($st),
            'nn' => petgo_nanny_label($oid), 'nav' => $nid ? petgo_user_avatar_url($nid) : '', 'walk' => $walk,
            'qr' => $q ? array('url' => petgo_qr_url($oid, $q), 'code' => substr($q['code'], 0, 3) . ' ' . substr($q['code'], 3), 'exp' => wp_date('d.m · H:i', (int) $q['exp'], petgo_tz())) : null,
            'by' => (string) get_post_meta($oid, '_hand_by', true), 'back' => (string) get_post_meta($oid, '_back_by', true), 'phase' => $phase,
        );
        /* 34.9: когда передали и когда вернули — кабинет сам меняет QR на галочку; при возврате — отчёт няни и фото */
        $ha = (int) get_post_meta($oid, '_hand_at', true);
        $ba = (int) get_post_meta($oid, '_back_at', true);
        $out['hand_at'] = $ha ? wp_date('H:i', $ha, petgo_tz()) : '';
        $out['back_at'] = $ba ? wp_date('H:i', $ba, petgo_tz()) : (in_array($st, array('done', 'confirmed'), true) && petgo_o($oid, 'done_at') ? wp_date('H:i', (int) petgo_o($oid, 'done_at'), petgo_tz()) : '');
        if (in_array($st, array('done', 'confirmed'), true)) {
            $out['rep'] = function_exists('petgo_rep_html') ? petgo_rep_html($oid) : '';
            $rp = petgo_o($oid, 'rphoto');
            $out['rph'] = (is_array($rp) && !empty($rp['url'])) ? petgo_media($rp['url']) : '';
        }
        petgo_cl_json(true, $out);
    }
    /* 34.8: страница питомца — всегда свежая (идёт прогулка, уже дома, отчёты) */
    if ($op === 'petpage' && function_exists('petgo_cl_petpage')) {
        $pid = (int) petgo_p('pet');
        if (!petgo_cl_owns($uid, $pid)) {
            petgo_cl_json(false, array('msg' => $L('Нет доступа.')));
        }
        petgo_cl_json(true, array('h' => petgo_cl_petpage($pid, petgo_cl_pet_state($pid))));
    }
    if ($op === 'walks' && function_exists('petgo_cl_walks_html')) {
        petgo_cl_json(true, array('h' => petgo_cl_walks_html($uid), 'wk' => petgo_cl_walks_sig($uid)));
    }
    if ($op === 'map') {
        petgo_cl_json(true, array('pets' => petgo_cl_pets_state($uid), 'hist' => petgo_cl_map_history($uid)));
    }
    if ($op === 'msgs') {
        $cid   = petgo_cl_chat($uid);
        $after = (int) petgo_p('after');
        $mt    = petgo_tb('msgs');
        $rows  = $after ? $wpdb->get_results($wpdb->prepare("SELECT * FROM $mt WHERE chat_id=%d AND id>%d ORDER BY id ASC LIMIT 100", $cid, $after))
            : array_reverse((array) $wpdb->get_results($wpdb->prepare("SELECT * FROM $mt WHERE chat_id=%d ORDER BY id DESC LIMIT 80", $cid)));
        $out = array();
        $max = 0;
        foreach ((array) $rows as $r) {
            $a     = (int) $r->user_id ? get_userdata((int) $r->user_id) : false;
            $me    = (int) $r->user_id === $uid;
            $max   = max($max, (int) $r->id);
            $out[] = array('id' => (int) $r->id, 'me' => $me, 'k' => (string) $r->kind, 's' => (int) $r->subject_id, 'pid' => $r->kind === 'loc' ? (int) petgo_o((int) $r->subject_id, 'pet') : 0, 'rv' => ($r->kind === 'rv' && (int) $r->subject_id && function_exists('petgo_rv_by_order') && petgo_rv_by_order((int) $r->subject_id)) ? (int) petgo_rv_by_order((int) $r->subject_id)->rating : 0, 'n' => $me ? '' : ($a ? $a->display_name : petgo_site_l($r->kind === 'ai' ? 'Помощник PetGo' : 'PetGo')), 'av' => (!$me && $a) ? petgo_user_avatar_url($a->ID) : '',
                'h' => petgo_msg_html($r->body), 'p' => $r->photo !== '' ? petgo_media($r->photo) : '', 'tm' => wp_date('H:i', (int) $r->ts, petgo_tz()), 'dl' => wp_date('d.m.Y', (int) $r->ts, petgo_tz()));
        }
        if ($max) {
            petgo_mark_read($cid, $uid, $max);
        }
        petgo_cl_json(true, array('msgs' => $out));
    }
    if ($op === 'send') {
        if (!petgo_cl_ip_limit('m' . $uid, 30, 10 * MINUTE_IN_SECONDS)) {
            petgo_cl_json(false, array('msg' => $L('Слишком много сообщений подряд. Подождите немного.')));
        }
        $body  = mb_substr(sanitize_textarea_field((string) petgo_p('body')), 0, 2000);
        $photo = '';
        if (isset($_FILES['photo']) && !empty($_FILES['photo']['name'])) {
            $r = petgo_save_image($_FILES['photo'], 1280, 72);
            if (!is_array($r)) {
                petgo_cl_json(false, array('msg' => $L('Не удалось загрузить фото. Нужен JPG, PNG или WebP.')));
            }
            $photo = $r['url'];
        }
        if ($body === '' && $photo === '') {
            petgo_cl_json(false, array('msg' => $L('Напишите сообщение.')));
        }
        $mid = petgo_cl_say($uid, $body, $photo);
        /* 34.6: помощник (ИИ) отвечает после того, как сообщение уже сохранено и телефон получил «отправлено»:
           долгий ответ ИИ больше не держит отправку (из-за этого на плохой связи сообщение уходило дважды) */
        $want = $body !== '' && function_exists('petgo_cl_ai_reply') && function_exists('petgo_cl_ai_on') && petgo_cl_ai_on();
        if ($want && function_exists('fastcgi_finish_request')) {
            nocache_headers();
            header('Content-Type: application/json; charset=utf-8');
            echo wp_json_encode(array('ok' => true, 'id' => (int) $mid, 'ai' => 'wait'));
            fastcgi_finish_request();
            @ignore_user_abort(true);
            petgo_cl_ai_reply($uid);
            exit;
        }
        $ai = $want ? petgo_cl_ai_reply($uid) : false;
        petgo_cl_json(true, array('id' => (int) $mid, 'ai' => (bool) $ai));
    }
    if ($op === 'profile') {
        $name = mb_substr(sanitize_text_field((string) petgo_p('name')), 0, 60);
        if (mb_strlen($name) < 2) {
            petgo_cl_json(false, array('msg' => $L('Укажите имя.'), 'field' => 'name'));
        }
        $em = petgo_email_check(petgo_p('email'), $uid);
        if ($em === false) {
            petgo_cl_json(false, array('msg' => $L('Проверьте почту — возможно, она уже занята.'), 'field' => 'email'));
        }
        $phone = petgo_pf_phone((string) petgo_p('phone'));
        if ($phone === '') {
            petgo_cl_json(false, array('msg' => $L('Проверьте номер телефона.'), 'field' => 'phone'));
        }
        $other = petgo_cl_by_phone($phone);
        if ($other && $other !== $uid) {
            petgo_cl_json(false, array('msg' => $L('Этот номер уже привязан к другому кабинету.'), 'field' => 'phone'));
        }
        wp_update_user(array('ID' => $uid, 'display_name' => $name, 'first_name' => $name, 'user_email' => $em));
        update_user_meta($uid, 'petgo_phone', $phone);
        update_user_meta($uid, 'petgo_phone_d', petgo_phone_digits($phone));
        petgo_cl_link_pets($uid);
        /* 39.4: адрес — для заказов; питомцам без адреса (или со старым адресом профиля) — тот же */
        if (isset($_POST['addr'])) {
            $old  = (string) get_user_meta($uid, 'petgo_addr', true);
            $addr = trim(mb_substr(sanitize_text_field((string) petgo_p('addr')), 0, 200));
            update_user_meta($uid, 'petgo_addr', $addr);
            if ($addr !== '') {
                foreach (petgo_cl_pets($uid) as $pt) {
                    $pid = is_object($pt) ? (int) $pt->ID : (int) $pt;
                    $cur = trim((string) get_post_meta($pid, '_pf_address', true));
                    if ($cur === '' || $cur === $old) {
                        update_post_meta($pid, '_pf_address', $addr);
                    }
                }
            }
        }
        petgo_cl_json(true, array('msg' => $L('Сохранено')));
    }
    if ($op === 'pass') {
        $u = get_userdata($uid);
        if (!wp_check_password((string) petgo_p('old'), $u->user_pass, $uid)) {
            petgo_cl_json(false, array('msg' => $L('Старый пароль неверный.'), 'field' => 'old'));
        }
        $new = (string) petgo_p('new');
        if (strlen($new) < 8) {
            petgo_cl_json(false, array('msg' => $L('Пароль — не короче 8 символов.'), 'field' => 'new'));
        }
        wp_set_password($new, $uid);
        /* после смены пароля все остальные входы (другие телефоны) закрываются */
        WP_Session_Tokens::get_instance($uid)->destroy_all();
        wp_set_current_user($uid);
        wp_set_auth_cookie($uid, true, is_ssl());
        petgo_cl_json(true, array('msg' => $L('Пароль изменён')));
    }
    if ($op === 'avatar' || $op === 'pet_av') {
        $pid = (int) petgo_p('pet');
        if ($op === 'pet_av' && !petgo_cl_owns($uid, $pid)) {
            petgo_cl_json(false, array('msg' => $L('Нет доступа.')));
        }
        $r = isset($_FILES['photo']) ? petgo_save_image($_FILES['photo'], $op === 'avatar' ? 400 : 640, 72) : 'none';
        if (!is_array($r)) {
            petgo_cl_json(false, array('msg' => $L('Не удалось загрузить фото. Нужен JPG, PNG или WebP.')));
        }
        if ($op === 'avatar') {
            $old = get_user_meta($uid, 'petgo_avatar', true);
            if (is_array($old) && !empty($old['file'])) {
                petgo_rm($old['file']);
            }
            update_user_meta($uid, 'petgo_avatar', $r);
            petgo_cl_json(true, array('url' => petgo_media($r['url'])));
        }
        $old = get_post_meta($pid, '_avatar', true);
        if (is_array($old) && !empty($old['file'])) {
            petgo_rm($old['file']);
        }
        update_post_meta($pid, '_avatar', $r);
        petgo_bump();
        petgo_cl_json(true, array('url' => petgo_media($r['url'])));
    }
    if ($op === 'review') {
        $oid = (int) petgo_p('order');
        $pid = (int) petgo_o($oid, 'pet');
        if (get_post_type($oid) !== 'petgo_order' || !petgo_cl_owns($uid, $pid) || !in_array(petgo_o($oid, 'status'), array('done', 'confirmed'), true)) {
            petgo_cl_json(false, array('msg' => $L('Отзыв пока нельзя оставить.')));
        }
        if (petgo_rv_by_order($oid)) {
            petgo_cl_json(false, array('msg' => $L('Отзыв к этой прогулке уже есть. Спасибо!')));
        }
        $rating = (int) petgo_p('rating');
        $text   = mb_substr(sanitize_textarea_field((string) petgo_p('text')), 0, 1500);
        if ($rating < 1 || $rating > 5) {
            petgo_cl_json(false, array('msg' => $L('Поставьте оценку.')));
        }
        if (mb_strlen($text) < 3) {
            petgo_cl_json(false, array('msg' => $L('Напишите пару слов о прогулке.')));
        }
        $u = get_userdata($uid);
        $wpdb->insert(petgo_tb('reviews'), array('order_id' => $oid, 'nanny_id' => (int) petgo_o($oid, 'nanny'), 'name' => mb_substr($u->display_name, 0, 80), 'rating' => $rating, 'body' => $text,
            'photos' => '', 'status' => get_option('petgo_rv_auto') ? 'ok' : 'pending', 'ts' => time()), array('%d', '%d', '%s', '%d', '%s', '%s', '%s', '%d'));
        if (!$wpdb->insert_id) {
            petgo_cl_json(false, array('msg' => $L('Отзыв к этой прогулке уже есть. Спасибо!')));
        }
        petgo_audit('review_new', 'order=' . $oid . ' rating=' . $rating . ' client=' . $uid, $uid);
        petgo_bump();
        /* 36.0: уведомление — только о плохом отзыве (3 и ниже), остальные — в утренней сводке */
        if ((int) $rating <= 3) {
            petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), 'Плохой отзыв: ' . $rating . ' из 5', $u->display_name . ': ' . $rating . ' из 5 · ' . petgo_nanny_label($oid), petgo_page_url(array('pg' => 'reviews')));
        }
        petgo_cl_json(true, array('msg' => $L('Спасибо за отзыв!')));
    }
    if ($op === 'join') {
        $b = petgo_board_get((string) petgo_p('id'));
        if (!$b) {
            petgo_cl_json(false, array('msg' => $L('Событие не найдено.')));
        }
        $pets = wp_list_pluck(petgo_cl_pets($uid), 'post_title');
        $who  = get_user_meta($uid, 'petgo_join_' . $b['id'], true);
        if ($who) {
            petgo_cl_json(true, array('msg' => $L('Вы уже записаны. Менеджер свяжется с вами.')));
        }
        update_user_meta($uid, 'petgo_join_' . $b['id'], time());
        petgo_cl_say($uid, 'Хочу записаться: «' . $b['title'] . '»' . ($b['when'] !== '' ? ' · ' . $b['when'] : '') . ($pets ? "\nПитомцы: " . implode(', ', $pets) : ''));
        petgo_cl_json(true, array('msg' => $L('Заявка отправлена менеджеру!')));
    }
    /* 34.2: заказ, проверка свободных нянь, отзыв позже, дом на карте, удаление кабинета (см. includes/client-plus.php) */
    do_action('petgo_cl_op', $op, $uid);
    petgo_cl_json(false, array('msg' => 'bad op'));
}

/* ============================================================
   СОСТОЯНИЕ ПИТОМЦЕВ: что делает сейчас, где он, текущая прогулка
   ============================================================ */

function petgo_cl_pet_state($pid) {
    $now = time();
    $sp  = (string) get_post_meta($pid, '_pf_species', true);
    $hi  = $sp === 'cat' ? 'Мяу!' : ($sp === 'dog' ? 'Гав!' : '');
    $os  = petgo_orders(array('numberposts' => 20, 'meta_query' => array('relation' => 'AND', array('key' => '_pet', 'value' => (int) $pid),
        array('key' => '_status', 'value' => array('assigned', 'arrived', 'picked'), 'compare' => 'IN'))));
    $act = null;
    $next = null;
    foreach ($os as $o) {
        $st = petgo_o($o->ID, 'status');
        if ($st === 'arrived' || $st === 'picked') {
            /* 34.8: заказ «завис» (няня не завершила, прошло больше 6 часов после конца) — питомец не «гуляет» вечно */
            $end = (int) petgo_o($o->ID, 'ts') + max(30, (int) petgo_o($o->ID, 'dur')) * MINUTE_IN_SECONDS;
            if ($end + 6 * HOUR_IN_SECONDS < $now) {
                continue;
            }
            $act = $o->ID;
            break;
        }
        $ts = (int) petgo_o($o->ID, 'ts');
        if ($ts > $now - 3600 && (!$next || $ts < (int) petgo_o($next, 'ts'))) {
            $next = $o->ID;
        }
    }
    $L = 'petgo_site_l';
    $o = array('id' => (int) $pid, 'name' => get_the_title($pid), 'av' => petgo_pet_avatar_url($pid), 'sp' => $sp, 'hi' => $L($hi), 'live' => false, 'st' => 'home', 'text' => $L('Дома'), 'order' => 0, 'pos' => null, 'track' => array());
    /* дом питомца на карте (адрес из анкеты; место находится один раз и запоминается) */
    $addr = trim((string) get_post_meta($pid, '_pf_address', true));
    $hg   = get_post_meta($pid, '_home_geo', true);
    $o['addr'] = $addr;
    $o['home'] = (is_array($hg) && isset($hg[2]) && $hg[2] === md5($addr)) ? array((float) $hg[0], (float) $hg[1]) : null;
    if ($act) {
        $sv  = petgo_o($act, 'service');
        $st  = petgo_o($act, 'status');
        $nn  = petgo_nanny_label($act);
        $ls  = (string) petgo_o($act, 'live_st');
        $W   = petgo_cl_live_words();
        $since = (int) petgo_o($act, $st === 'picked' ? 'picked_at' : 'arrived_at');
        $o['live']  = true;
        $o['order'] = (int) $act;
        $o['nanny'] = $nn;
        $o['nav']   = (int) petgo_o($act, 'nanny') ? petgo_user_avatar_url((int) petgo_o($act, 'nanny')) : '';
        $o['min']   = $since ? max(0, (int) floor(($now - $since) / 60)) : 0;
        $o['end']   = (int) petgo_o($act, 'ts') + max(1, (int) petgo_o($act, 'dur')) * 60;
        if ($st === 'picked') {
            $o['st']   = 'walk';
            $o['text'] = isset($W[$ls]) ? $L($W[$ls]) : petgo_site_lf('Гуляет с няней %s', $nn);
        } elseif ($sv === 'walk') {
            $o['st']   = 'arrived';
            $o['text'] = petgo_site_lf('Няня %s пришла', $nn);
        } else {
            $o['st']   = 'visit';
            $o['text'] = isset($W[$ls]) ? $L($W[$ls]) : petgo_site_lf('Няня %s дома с питомцем', $nn);
        }
        $tr = petgo_trk_points($act);
        if ($tr) {
            $last       = end($tr);
            $o['pos']   = array($last[0], $last[1]);
            $o['at']    = (int) $last[2];
            $o['track'] = petgo_trk_simplify($tr, 400);
        } elseif (petgo_o($act, 'lat') !== '') {
            $o['pos'] = array((float) petgo_o($act, 'lat'), (float) petgo_o($act, 'lng'));
            $o['at']  = (int) petgo_o($act, 'arrived_at');
        }
        $o['photos'] = petgo_cl_order_photos($act, $pid);
    } elseif ($next) {
        $o['st']   = 'soon';
        $o['text'] = petgo_site_lf('Няня придёт %s', wp_date('d.m в H:i', (int) petgo_o($next, 'ts'), petgo_tz()));
        $o['next'] = (int) petgo_o($next, 'ts');
    }
    return $o;
}

/* 36.0: у каких заказов няня сейчас у двери (QR ещё не отсканирован) и идёт ли заказ (чаще обновлять) */
function petgo_cl_door_state($uid) {
    $door = array();
    $soon = false;
    foreach (petgo_cl_upcoming($uid, 12) as $o) {
        $st  = petgo_o($o->ID, 'status');
        $ts  = (int) petgo_o($o->ID, 'ts');
        $end = $ts + max(30, (int) petgo_o($o->ID, 'dur')) * MINUTE_IN_SECONDS;
        if (in_array($st, array('arrived', 'picked'), true) || ($st === 'assigned' && $ts - 90 * MINUTE_IN_SECONDS <= time() && $end + 2 * HOUR_IN_SECONDS >= time())) {
            $soon = true;
        }
        $d = function_exists('petgo_door_open') ? petgo_door_open($o->ID) : null;
        if ($d) {
            $door[] = array('id' => (int) $o->ID, 'ph' => (string) $d['ph'], 't' => (int) $d['t']);
        }
    }
    return array('door' => $door, 'soon' => $soon ? 1 : 0);
}

function petgo_cl_pets_state($uid) {
    $o = array();
    foreach (petgo_cl_pets($uid) as $p) {
        $o[] = petgo_cl_pet_state($p->ID);
    }
    return $o;
}

/* фото прогулки (с местом на карте) */
function petgo_cl_order_photos($oid, $pid) {
    $o = array();
    foreach (petgo_photos($pid) as $x) {
        if ((int) (isset($x['order']) ? $x['order'] : 0) === (int) $oid) {
            $o[] = array('u' => petgo_media($x['url']), 't' => wp_date('H:i', (int) $x['t'], petgo_tz()), 'lat' => isset($x['lat']) ? (float) $x['lat'] : null, 'lng' => isset($x['lng']) ? (float) $x['lng'] : null);
        }
    }
    return $o;
}

/* все места, где был питомец: прогулки за последние 60 заказов + все фото с геолокацией */
function petgo_cl_map_history($uid) {
    $out = array('walks' => array(), 'photos' => array());
    foreach (petgo_cl_orders($uid, 60) as $o) {
        $st = petgo_o($o->ID, 'status');
        if (!in_array($st, array('done', 'confirmed'), true)) {
            continue;
        }
        $tr = petgo_trk_points($o->ID);
        if (!$tr && petgo_o($o->ID, 'lat') !== '') {
            $tr = array(array((float) petgo_o($o->ID, 'lat'), (float) petgo_o($o->ID, 'lng'), (int) petgo_o($o->ID, 'arrived_at')));
        }
        if ($tr) {
            $out['walks'][] = array('o' => (int) $o->ID, 'pet' => (int) petgo_o($o->ID, 'pet'), 'd' => wp_date('d.m.Y', (int) petgo_o($o->ID, 'ts'), petgo_tz()), 'n' => petgo_nanny_label($o->ID), 'p' => petgo_trk_simplify($tr, 120));
        }
    }
    foreach (petgo_cl_pets($uid) as $p) {
        foreach (petgo_photos($p->ID) as $x) {
            if (isset($x['lat'], $x['lng']) && $x['lat'] !== '' && $x['lng'] !== '') {
                $out['photos'][] = array('pet' => (int) $p->ID, 'u' => petgo_media($x['url']), 'lat' => (float) $x['lat'], 'lng' => (float) $x['lng'], 'd' => wp_date('d.m.Y H:i', (int) $x['t'], petgo_tz()));
            }
        }
    }
    $out['photos'] = array_slice($out['photos'], -120);
    return $out;
}

/* ============================================================
   ДЛЯ КЛИЕНТОВ: ваш блог, групповые прогулки, акции (ведутся в приложении: Клиенты → «Для клиентов»)
   ============================================================ */
function petgo_board() {
    $a = get_option('petgo_board', array());
    $o = array();
    foreach (is_array($a) ? $a : array() as $b) {
        if (!is_array($b) || empty($b['id'])) {
            continue;
        }
        $b = array_merge(array('type' => 'blog', 'title' => '', 'text' => '', 'when' => '', 'place' => '', 'photo' => '', 'file' => '', 't' => 0), $b);
        if ($b['type'] === 'tip') {
            $b['type'] = 'blog';
        }
        $o[] = $b;
    }
    return $o;
}

function petgo_board_get($id) {
    foreach (petgo_board() as $b) {
        if ($b['id'] === $id) {
            return $b;
        }
    }
    return null;
}

function petgo_board_types() {
    return array('blog' => 'cl_b_blog', 'group' => 'cl_b_group', 'promo' => 'cl_b_promo');
}

/* ============================================================
   СТРАНИЦА «КАБИНЕТ» НА САЙТЕ
   Значки — один набор, один стиль: контур 1.8, скруглённые концы (как в меню сайта)
   ============================================================ */







/* ============================================================
   ПРИЛОЖЕНИЕ СОТРУДНИКОВ: раздел «Клиенты» и «Для клиентов» (блог, групповые прогулки, акции, карта)
   ============================================================ */
add_filter('petgo_nav_items', function ($items, $adm) {
    if (!$adm) {
        return $items;
    }
    $new = array();
    foreach ($items as $k => $v) {
        $new[$k] = $v;
        if ($k === 'pets') {
            $new['clients'] = array(petgo_t('cl_nav'), petgo_page_url(array('pg' => 'clients')), 'staff', 'd');
        }
    }
    return $new;
}, 5, 2);

add_filter('petgo_app_page', function ($done, $pg) {
    if ($pg === 'clients' && petgo_can('office')) {
        petgo_page_clients();
        return true;
    }
    return $done;
}, 10, 2);

function petgo_page_clients() {
    $f   = isset($_GET['cv']) && $_GET['cv'] === 'board' ? 'board' : 'list';
    $all = get_users(array('role' => 'petgo_client', 'orderby' => 'registered', 'order' => 'DESC', 'number' => 500));
    echo '<div class="pgc-chips"><a class="pgc-chip' . ($f === 'list' ? ' on' : '') . '" href="' . esc_url(petgo_page_url(array('pg' => 'clients'))) . '">' . esc_html(petgo_t('cl_nav')) . '<i>' . count($all) . '</i></a><a class="pgc-chip' . ($f === 'board' ? ' on' : '') . '" href="' . esc_url(petgo_page_url(array('pg' => 'clients', 'cv' => 'board'))) . '">' . esc_html(petgo_t('cl_board')) . '<i>' . count(petgo_board()) . '</i></a></div>';
    if ($f === 'board') {
        petgo_page_board();
        return;
    }
    $np = get_transient('petgo_clpw_' . get_current_user_id());
    if (is_array($np)) {
        delete_transient('petgo_clpw_' . get_current_user_id());
        $cu = get_userdata((int) $np[0]);
        if ($cu) {
            $text = 'Здравствуйте! Ваш кабинет PetGo: ' . petgo_site_url('account') . "\nЛогин: " . $cu->user_login . "\nНовый пароль: " . $np[1] . "\nПароль можно сменить в профиле.";
            echo '<div class="pgc-card pgcl-pw"><b>' . esc_html(petgo_t('cl_pw_new') . ': ' . $cu->display_name) . '</b><div class="pgcl-pwv"><span>' . esc_html(petgo_t('login_s')) . '</span><code>' . esc_html($cu->user_login) . '</code><span>' . esc_html(petgo_t('pass_s')) . '</span><code>' . esc_html($np[1]) . '</code></div>';
            echo '<div class="pgi-btns"><button type="button" class="pgc-btn sec" data-copy="' . esc_attr($text) . '" data-done="' . esc_attr(petgo_t('pf_copied')) . '">' . petgo_pf_icon('copy') . '<span>' . esc_html(petgo_t('pf_copy')) . '</span></button><a class="pgc-btn pgi-wa" target="_blank" rel="noopener" href="' . esc_url('https://wa.me/' . petgo_phone_digits(get_user_meta($cu->ID, 'petgo_phone', true)) . '?text=' . rawurlencode($text)) . '">' . petgo_pf_icon('wa') . '<span>WhatsApp</span></a></div></div>';
        }
    }
    if (!$all) {
        echo petgo_empty('staff', petgo_t('cl_none'));
        return;
    }
    echo '<label class="pgcl-search">' . petgo_icon('user') . '<input type="search" placeholder="' . esc_attr(petgo_t('cl_search')) . '" data-cl-search></label>';
    $free = array();
    foreach (petgo_pets() as $p) {
        if (!(int) get_post_meta($p->ID, '_client', true)) {
            $free[] = $p;
        }
    }
    foreach ($all as $u) {
        $pets  = petgo_cl_pets($u->ID);
        $ph    = (string) get_user_meta($u->ID, 'petgo_phone', true);
        $cid   = petgo_cl_chat($u->ID, false);
        if ($cid) {
            petgo_mark_read($cid, get_current_user_id(), 0);
        }
        $names = wp_list_pluck($pets, 'post_title');
        echo '<details class="pgc-ord pgcl" data-k="cl' . (int) $u->ID . '" data-q="' . esc_attr(mb_strtolower($u->display_name . ' ' . $u->user_login . ' ' . $ph . ' ' . petgo_cl_code($u->ID) . ' ' . implode(' ', $names))) . '"><summary>' . petgo_av(petgo_user_avatar_url($u->ID), $u->display_name, 'md') . '<div class="pgc-sm"><div class="pgc-l1"><b>' . esc_html($u->display_name) . '</b><span class="mu">@' . esc_html($u->user_login) . '</span></div><div class="pgc-l2">' . esc_html(petgo_cl_code($u->ID) . ($names ? ' · ' . implode(', ', $names) : '')) . '</div></div></summary><div class="pgc-body">';
        echo '<div class="pgf-list pgcl-info"><div class="pgf-row"><span>ID</span><b>' . esc_html(petgo_cl_code($u->ID)) . '</b></div><div class="pgf-row"><span>' . esc_html(petgo_t('login_s')) . '</span><b>' . esc_html($u->user_login) . '</b></div>';
        if ($ph !== '') {
            echo '<div class="pgf-row"><span>' . esc_html(petgo_t('phone')) . '</span><a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $ph)) . '">' . esc_html(petgo_phone_fmt($ph)) . '</a></div>';
        }
        echo '<div class="pgf-row"><span>' . esc_html(petgo_t('cl_since')) . '</span><b>' . esc_html(wp_date('d.m.Y', strtotime($u->user_registered . ' UTC'), petgo_tz())) . '</b></div>';
        /* 34.5: откуда пришёл (реклама, метки utm) */
        $src = get_user_meta($u->ID, 'petgo_src', true);
        if ($src && function_exists('petgo_src_text')) {
            echo '<div class="pgf-row"><span>Источник</span><b>' . esc_html(petgo_src_text((array) $src)) . '</b></div>';
        }
        /* 35.0: договор — подписан ли, как, скачать PDF и подпись */
        if (function_exists('petgo_ct_staff_line') && (petgo_ct_ready() || petgo_ct_get($u->ID))) {
            echo '<div class="pgf-row pgct-row"><span>Договор</span><span>' . petgo_ct_staff_line($u->ID) . '</span></div>';
        }
        echo '</div>';
        if ($pets) {
            echo '<div class="pgcl-pets">';
            foreach ($pets as $p) {
                echo '<span>' . petgo_av(petgo_pet_avatar_url($p->ID), $p->post_title, 'sm') . esc_html($p->post_title) . '<small>' . esc_html(petgo_pet_code($p->ID)) . '</small></span>';
            }
            echo '</div>';
        }
        echo '<div class="pgi-btns"><a class="pgc-btn sec" href="' . esc_url(petgo_page_url(array('pg' => 'clients', 'c' => $cid ? $cid : petgo_cl_chat($u->ID)))) . '">' . petgo_ic('msg') . '<span>' . esc_html(petgo_t('c_write')) . '</span></a>';
        echo petgo_btn_form('cl_pass', array('uid' => $u->ID), petgo_t('cl_pw_reset'), 'pgc-btn sec', petgo_t('cl_pw_q')) . '</div>';
        echo petgo_form_open('cl_ok', 'class="pgcl-ok"') . '<input type="hidden" name="uid" value="' . (int) $u->ID . '"><label class="pgc-ck"><input type="checkbox" name="ok" value="1"' . checked(petgo_cl_confirmed($u->ID), true, false) . ' onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"> <span>' . esc_html(petgo_t('cl_ok')) . '</span></label></form>';
        echo petgo_form_open('cl_rec', 'class="pgcl-rec"') . '<input type="hidden" name="uid" value="' . (int) $u->ID . '"><div class="pgf-list"><label class="pgf-row pgf-ra"><span>' . esc_html(petgo_t('cl_rec')) . '</span><textarea name="rec" rows="2" maxlength="1000" placeholder="' . esc_attr(petgo_t('cl_rec_ph')) . '">' . esc_textarea((string) get_user_meta($u->ID, 'petgo_cl_rec', true)) . '</textarea></label></div><button type="submit" class="pgc-btn sec sm">' . esc_html(petgo_t('save')) . '</button></form>';
        if ($free) {
            echo petgo_form_open('cl_link', 'class="pgcl-link"') . '<input type="hidden" name="uid" value="' . (int) $u->ID . '"><select name="pet"><option value="">' . esc_html(petgo_t('cl_link')) . '</option>';
            foreach ($free as $p) {
                echo '<option value="' . (int) $p->ID . '">' . esc_html($p->post_title . ' · ' . get_post_meta($p->ID, '_pf_owner', true)) . '</option>';
            }
            echo '</select><button type="submit" class="pgc-btn sec sm">' . esc_html(petgo_t('cl_link_btn')) . '</button></form>';
        }
        echo '</div></details>';
    }
}

/* «Для клиентов»: блог (с фото), групповые прогулки, акции + какая карта в кабинете клиента */
function petgo_page_board() {
    $eid = isset($_GET['be']) ? sanitize_text_field(wp_unslash($_GET['be'])) : '';
    $e   = $eid !== '' ? petgo_board_get($eid) : null;
    $t   = $e ? $e['type'] : (isset($_GET['bt']) && isset(petgo_board_types()[$_GET['bt']]) ? sanitize_key($_GET['bt']) : 'blog');
    echo '<p class="mu pgcl-hint">' . esc_html(petgo_t('cl_board_hint')) . '</p>';
    $b  = petgo_form_open('board_add', 'enctype="multipart/form-data" class="pgc-card pgcl-badd" data-bt="' . esc_attr($t) . '"') . '<input type="hidden" name="id" value="' . esc_attr($e ? $e['id'] : '') . '">';
    $b .= '<div class="pgf-seg pgcl-btype">';
    foreach (petgo_board_types() as $k => $l) {
        $b .= '<label><input type="radio" name="type" value="' . esc_attr($k) . '"' . checked($k, $t, false) . '><span>' . esc_html(petgo_t($k === 'group' ? 'cl_b_group_s' : $l)) . '</span></label>';
    }
    $img = $e && $e['photo'] !== '' ? petgo_media($e['photo']) : '';
    $b .= '</div><label class="pgcl-cover' . ($img ? ' has' : '') . '"><input class="pgc-vh" type="file" name="photo" accept="image/*" data-cover><span class="pgcl-cimg"' . ($img ? ' style="background-image:url(' . esc_url($img) . ')"' : '') . '></span><span class="pgcl-cl">' . petgo_pf_icon('photo') . '<span>' . esc_html(petgo_t('cl_b_photo')) . '</span></span></label>';
    $b .= '<div class="pgf-list"><label class="pgf-row"><span>' . esc_html(petgo_t('cl_b_title')) . '</span><input type="text" name="title" maxlength="120" value="' . esc_attr($e ? $e['title'] : '') . '" required></label>';
    $b .= '<label class="pgf-row pgcl-when"><span>' . esc_html(petgo_t('cl_b_when')) . '</span><input type="text" name="when" maxlength="60" value="' . esc_attr($e ? $e['when'] : '') . '" placeholder="' . esc_attr(petgo_t('cl_b_when_ph')) . '"></label>';
    $b .= '<label class="pgf-row pgcl-place"><span>' . esc_html(petgo_t('cl_b_place')) . '</span><input type="text" name="place" maxlength="120" value="' . esc_attr($e ? $e['place'] : '') . '"></label>';
    $b .= '<label class="pgf-row pgf-ra"><span>' . esc_html(petgo_t('cl_b_text')) . '</span><textarea name="text" rows="5" maxlength="4000">' . esc_textarea($e ? $e['text'] : '') . '</textarea></label></div>';
    $b .= '<div class="pgi-btns">' . ($e ? '<a class="pgc-btn sec" href="' . esc_url(petgo_page_url(array('pg' => 'clients', 'cv' => 'board'))) . '">' . esc_html(petgo_t('c_cancel')) . '</a>' : '') . '<button class="pgc-btn" type="submit"' . ($e ? '' : ' style="grid-column:1/-1"') . '>' . esc_html(petgo_t($e ? 'save' : 'cl_b_add')) . '</button></div></form>';
    echo $b;
    $T = petgo_board_types();
    foreach (array_reverse(petgo_board()) as $x) {
        $n = 0;
        if ($x['type'] === 'group') {
            global $wpdb;
            $n = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key=%s", 'petgo_join_' . $x['id']));
        }
        $img = $x['photo'] !== '' ? petgo_media($x['photo']) : '';
        echo '<div class="pgc-card pgcl-bi">' . ($img ? '<img class="pgcl-bimg" src="' . esc_url($img) . '" alt="" loading="lazy">' : '') . '<div class="pgcl-bb"><span class="pgc-tag ' . ($x['type'] === 'promo' ? 'warn' : ($x['type'] === 'group' ? 'ok' : 'info')) . '">' . esc_html(petgo_t($T[$x['type']])) . '</span><b>' . esc_html($x['title']) . '</b>';
        $meta = trim($x['when'] . ($x['place'] !== '' ? ' · ' . $x['place'] : ''), ' ·');
        echo ($meta !== '' ? '<small>' . esc_html($meta) . '</small>' : '') . ($x['text'] !== '' ? '<p>' . esc_html(wp_trim_words($x['text'], 24, '…')) . '</p>' : '') . ($x['type'] === 'group' ? '<p class="pgcl-joined">' . esc_html(petgo_t('cl_b_joined') . ': ' . $n) . '</p>' : '');
        echo '<div class="pgcl-bacts"><a class="pgc-btn sec sm" href="' . esc_url(petgo_page_url(array('pg' => 'clients', 'cv' => 'board', 'be' => $x['id']))) . '">' . petgo_ic('edit') . esc_html(petgo_t('edit')) . '</a>' . petgo_btn_form('board_del', array('id' => $x['id']), petgo_t('delete'), 'pgc-btn sec sm', petgo_t('q_delete_inv')) . '</div></div></div>';
    }
    /* карта в кабинете клиента */
    if (petgo_can('admin')) {
        $k = (string) get_option('petgo_gmaps_key', '');
        echo petgo_form_open('gmaps_key', 'class="pgc-card pgcl-map"') . '<b>' . esc_html(petgo_t('cl_map_t')) . '</b><p class="mu">' . esc_html(petgo_t('cl_map_hint')) . '</p><div class="pgf-list"><label class="pgf-row"><span>Google Maps API</span><input type="text" name="key" value="' . esc_attr($k) . '" placeholder="AIza…" autocomplete="off" spellcheck="false"></label></div><button type="submit" class="pgc-btn sec sm">' . esc_html(petgo_t('save')) . '</button></form>';
    }
}

foreach (array('cl_pass', 'cl_rec', 'cl_link', 'board_add', 'board_del', 'gmaps_key') as $petgo_a) {
    add_action('admin_post_petgo_' . $petgo_a, 'petgo_h_' . $petgo_a);
}


/* менеджер ставит клиенту новый пароль — пароль показывается один раз */
function petgo_h_cl_pass() {
    petgo_guard('office');
    $u = petgo_cl_obj(petgo_p('uid'));
    if (!$u) {
        petgo_fail('err_na', 'clients');
    }
    $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
    $pw    = '';
    for ($i = 0; $i < 10; $i++) {
        $pw .= $chars[random_int(0, strlen($chars) - 1)];
    }
    wp_set_password($pw, $u->ID);
    set_transient('petgo_clpw_' . get_current_user_id(), array($u->ID, $pw), 10 * MINUTE_IN_SECONDS);
    petgo_audit('client_pass', $u->user_login);
    global $wpdb;
    $wpdb->query($wpdb->prepare('UPDATE ' . petgo_tb('reqs') . " SET status='done' WHERE status='new' AND phone=%s AND note LIKE %s", (string) get_user_meta($u->ID, 'petgo_phone', true), 'Клиент забыл%'));
    petgo_back('clients');
}

function petgo_h_cl_rec() {
    petgo_guard('office');
    $u = petgo_cl_obj(petgo_p('uid'));
    if (!$u) {
        petgo_fail('err_na', 'clients');
    }
    update_user_meta($u->ID, 'petgo_cl_rec', mb_substr(sanitize_textarea_field((string) petgo_p('rec')), 0, 1000));
    petgo_flash(petgo_t('ok_saved'));
    petgo_back('clients');
}

function petgo_h_cl_link() {
    petgo_guard('office');
    $u   = petgo_cl_obj(petgo_p('uid'));
    $pid = (int) petgo_p('pet');
    if (!$u || get_post_type($pid) !== 'petgo_pet') {
        petgo_fail('err_na', 'clients');
    }
    update_post_meta($pid, '_client', (int) $u->ID);
    petgo_flash(petgo_t('ok_saved'));
    petgo_back('clients');
}

function petgo_h_board_add() {
    petgo_guard('office');
    $t = (string) petgo_p('type');
    $t = isset(petgo_board_types()[$t]) ? $t : 'blog';
    $title = mb_substr(sanitize_text_field((string) petgo_p('title')), 0, 120);
    if ($title === '') {
        petgo_fail('err_fields', 'clients');
    }
    $id  = sanitize_text_field((string) petgo_p('id'));
    $old = $id !== '' ? petgo_board_get($id) : null;
    $row = array('id' => $old ? $old['id'] : wp_generate_password(8, false), 'type' => $t, 'title' => $title, 'text' => mb_substr(sanitize_textarea_field((string) petgo_p('text')), 0, 4000),
        'when' => $t === 'blog' ? '' : mb_substr(sanitize_text_field((string) petgo_p('when')), 0, 60), 'place' => $t === 'group' ? mb_substr(sanitize_text_field((string) petgo_p('place')), 0, 120) : '',
        'photo' => $old ? $old['photo'] : '', 'file' => $old ? $old['file'] : '', 't' => $old ? (int) $old['t'] : time());
    if (isset($_FILES['photo']) && !empty($_FILES['photo']['name'])) {
        $r = petgo_save_image($_FILES['photo'], 1600, 78);
        if (!is_array($r)) {
            petgo_fail($r, 'clients');
        }
        if ($row['file'] !== '') {
            petgo_rm($row['file']);
        }
        $row['photo'] = $r['url'];
        $row['file']  = $r['file'];
    }
    $a = array();
    $done = false;
    foreach (petgo_board() as $b) {
        if ($old && $b['id'] === $old['id']) {
            $a[] = $row;
            $done = true;
        } else {
            $a[] = $b;
        }
    }
    if (!$done) {
        $a[] = $row;
    }
    update_option('petgo_board', array_slice($a, -60), false);
    petgo_flash(petgo_t('ok_saved'));
    petgo_back('clients', array('cv' => 'board'));
}

function petgo_h_board_del() {
    petgo_guard('office');
    $id = (string) petgo_p('id');
    $a  = array();
    foreach (petgo_board() as $b) {
        if ($b['id'] === $id) {
            if ($b['file'] !== '') {
                petgo_rm($b['file']);
            }
            continue;
        }
        $a[] = $b;
    }
    update_option('petgo_board', $a, false);
    petgo_flash(petgo_t('ok_deleted'));
    petgo_back('clients', array('cv' => 'board'));
}

function petgo_h_gmaps_key() {
    petgo_guard('admin');
    update_option('petgo_gmaps_key', substr(preg_replace('/[^A-Za-z0-9_\-]/', '', (string) petgo_p('key')), 0, 60), false);
    petgo_flash(petgo_t('ok_saved'));
    petgo_back('clients', array('cv' => 'board'));
}
