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
   - рекомендации, групповые прогулки и акции; чат с менеджером; помощник (ИИ) знает питомцев клиента.
   ============================================================ */

add_action('init', 'petgo_cl_role', 4);
function petgo_cl_role() {
    if (!get_role('petgo_client')) {
        add_role('petgo_client', 'Клиент PetGo', array('read' => true));
    }
}

function petgo_cl_is($u) {
    return $u && $u->ID && in_array('petgo_client', (array) $u->roles, true) && !user_can($u, 'manage_options');
}

/* ID вошедшего клиента или 0 */
function petgo_cl_uid() {
    static $c = null;
    if ($c === null) {
        $u = wp_get_current_user();
        $c = petgo_cl_is($u) ? (int) $u->ID : 0;
    }
    return $c;
}

function petgo_cl_code($uid) {
    return 'PG' . str_pad((string) (int) $uid, 5, '0', STR_PAD_LEFT);
}

function petgo_pet_code($pid) {
    return 'P' . str_pad((string) (int) $pid, 5, '0', STR_PAD_LEFT);
}

/* никнейм питомца: латиница из клички + номер (bublik_123) */
function petgo_pet_nick($pid) {
    $n = (string) get_post_meta($pid, '_nick', true);
    if ($n !== '') {
        return $n;
    }
    $t = mb_strtolower(trim((string) get_the_title($pid)));
    $map = array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'ә' => 'a', 'ғ' => 'g', 'қ' => 'k', 'ң' => 'n', 'ө' => 'o', 'ұ' => 'u', 'ү' => 'u', 'һ' => 'h', 'і' => 'i');
    $t = strtr($t, $map);
    $t = trim(preg_replace('/[^a-z0-9]+/', '_', $t), '_');
    $t = substr($t !== '' ? $t : 'pet', 0, 16);
    $n = $t . '_' . (int) $pid;
    update_post_meta($pid, '_nick', $n);
    return $n;
}

function petgo_pet_ids_html($pid) {
    return '<div class="pgf-ids"><span>ID <b>' . esc_html(petgo_pet_code($pid)) . '</b></span><span>@' . esc_html(petgo_pet_nick($pid)) . '</span></div>';
}

function petgo_phone_digits($p) {
    $d = preg_replace('/\D/', '', (string) $p);
    if (strlen($d) === 11 && $d[0] === '8') {
        $d = '7' . substr($d, 1);
    }
    if (strlen($d) === 10) {
        $d = '7' . $d;
    }
    return $d;
}

/* клиент по номеру телефона */
function petgo_cl_by_phone($phone) {
    $d = petgo_phone_digits($phone);
    if (strlen($d) < 11) {
        return 0;
    }
    $u = get_users(array('role' => 'petgo_client', 'meta_key' => 'petgo_phone_d', 'meta_value' => $d, 'number' => 1, 'fields' => 'ID'));
    return $u ? (int) $u[0] : 0;
}

/* питомцы клиента */
function petgo_cl_pets($uid) {
    return get_posts(array('post_type' => 'petgo_pet', 'post_status' => 'publish', 'numberposts' => 50, 'orderby' => 'ID', 'order' => 'ASC', 'meta_key' => '_client', 'meta_value' => (int) $uid, 'no_found_rows' => true));
}

function petgo_cl_owns($uid, $pid) {
    return $uid && get_post_type($pid) === 'petgo_pet' && (int) get_post_meta($pid, '_client', true) === (int) $uid;
}

/* привязать к клиенту питомцев из базы с тем же телефоном хозяина */
function petgo_cl_link_pets($uid) {
    $d = (string) get_user_meta($uid, 'petgo_phone_d', true);
    if (strlen($d) < 11) {
        return 0;
    }
    $n = 0;
    foreach (petgo_pets() as $p) {
        if ((int) get_post_meta($p->ID, '_client', true)) {
            continue;
        }
        if (petgo_phone_digits(get_post_meta($p->ID, '_pf_phone', true)) === $d) {
            update_post_meta($p->ID, '_client', (int) $uid);
            $n++;
        }
    }
    return $n;
}

/* заказы питомцев клиента */
function petgo_cl_orders($uid, $n = 60) {
    $ids = wp_list_pluck(petgo_cl_pets($uid), 'ID');
    if (!$ids) {
        return array();
    }
    return petgo_orders(array('numberposts' => $n, 'meta_key' => '_ts', 'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_query' => array(array('key' => '_pet', 'value' => $ids, 'compare' => 'IN'))));
}

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

/* клиент остаётся в кабинете полгода */
add_filter('auth_cookie_expiration', function ($len, $uid) {
    $u = get_userdata((int) $uid);
    return petgo_cl_is($u) ? 180 * DAY_IN_SECONDS : $len;
}, 20, 2);

/* ============================================================
   ЧАТ КЛИЕНТА С МЕНЕДЖЕРОМ (общие таблицы чатов; у сотрудников он появляется в «Чатах»)
   ============================================================ */
function petgo_cl_chat($uid, $create = true) {
    global $wpdb;
    $ct  = petgo_tb('chats');
    $key = 'cl_' . (int) $uid;
    $cid = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $ct WHERE ckey=%s", $key));
    if (!$cid && $create) {
        $u = get_userdata($uid);
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $ct (ckey,type,title,created) VALUES (%s,'client',%s,%d)", $key, mb_substr($u ? $u->display_name : 'Клиент', 0, 120), time()));
        $cid = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $ct WHERE ckey=%s", $key));
    }
    if ($cid && $create) {
        /* в чате клиент и все, кто ведёт клиентов: владелец, админы, менеджеры */
        $ids = petgo_push_users(array('owner', 'admin', 'manager'));
        $ids[] = (int) $uid;
        foreach (array_unique($ids) as $i) {
            $wpdb->query($wpdb->prepare('INSERT IGNORE INTO ' . petgo_tb('members') . ' (chat_id,user_id,last_read) VALUES (%d,%d,0)', $cid, $i));
        }
    }
    return $cid;
}

function petgo_cl_unread($uid) {
    global $wpdb;
    $cid = petgo_cl_chat($uid, false);
    if (!$cid) {
        return 0;
    }
    $lr = (int) $wpdb->get_var($wpdb->prepare('SELECT last_read FROM ' . petgo_tb('members') . ' WHERE chat_id=%d AND user_id=%d', $cid, $uid));
    return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . petgo_tb('msgs') . ' WHERE chat_id=%d AND id>%d AND user_id<>%d', $cid, $lr, $uid));
}

/* сообщение в чат клиента от имени клиента (или системы) + уведомление сотрудникам */
function petgo_cl_say($uid, $body, $photo = '') {
    global $wpdb;
    $cid = petgo_cl_chat($uid);
    if (!$cid) {
        return 0;
    }
    $wpdb->insert(petgo_tb('msgs'), array('chat_id' => $cid, 'user_id' => (int) $uid, 'subject_id' => 0, 'kind' => 'msg', 'body' => $body, 'photo' => $photo, 'ts' => time()), array('%d', '%d', '%d', '%s', '%s', '%s', '%d'));
    $mid = (int) $wpdb->insert_id;
    if ($mid) {
        petgo_mark_read($cid, $uid, $mid);
        $c = petgo_chat_row($cid);
        if ($c && function_exists('petgo_chat_push')) {
            petgo_chat_push($c, (int) $uid, 0, $body, $photo);
        }
        petgo_bump();
    }
    return $mid;
}

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
    petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), 'Новый клиент на сайте', $name . ' · ' . $phone . ($linked ? ' · питомцев: ' . $linked : ''), petgo_page_url(array('pg' => 'clients')));
    petgo_cl_json(true, array('go' => petgo_site_url('account')));
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
    $op = sanitize_key((string) petgo_p('op'));
    $L  = 'petgo_site_l';
    global $wpdb;

    if ($op === 'live') {
        petgo_cl_json(true, array('pets' => petgo_cl_pets_state($uid), 'u' => petgo_cl_unread($uid)));
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
            $out[] = array('id' => (int) $r->id, 'me' => $me, 'n' => $me ? '' : ($a ? $a->display_name : 'PetGo'), 'av' => (!$me && $a) ? petgo_user_avatar_url($a->ID) : '',
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
        petgo_cl_say($uid, $body, $photo);
        petgo_cl_json(true);
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
        petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), 'Новый отзыв из кабинета', $u->display_name . ': ' . $rating . ' из 5 · ' . petgo_nanny_label($oid), petgo_page_url(array('pg' => 'reviews')));
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
        petgo_cl_say($uid, '🐾 Хочу записаться: «' . $b['title'] . '»' . ($b['when'] !== '' ? ' · ' . $b['when'] : '') . ($pets ? "\nПитомцы: " . implode(', ', $pets) : ''));
        petgo_cl_json(true, array('msg' => $L('Заявка отправлена менеджеру!')));
    }
    petgo_cl_json(false, array('msg' => 'bad op'));
}

/* ============================================================
   СОСТОЯНИЕ ПИТОМЦЕВ: что делает сейчас, где он, текущая прогулка
   ============================================================ */
function petgo_cl_live_words() {
    return array(
        'walk'  => 'Гуляем', 'play' => 'Играем', 'eat' => 'Кушаем', 'water' => 'Пьём водичку', 'rest' => 'Отдыхаем', 'sniff' => 'Изучаем всё вокруг',
        'potty' => 'Сделали дела', 'home' => 'Возвращаемся домой', 'pet' => 'Обнимашки',
    );
}

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
   ДОСКА ДЛЯ КЛИЕНТОВ: акции, групповые прогулки, советы (ведут сотрудники в разделе «Клиенты»)
   ============================================================ */
function petgo_board() {
    $a = get_option('petgo_board', array());
    return is_array($a) ? $a : array();
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
    return array('promo' => 'cl_b_promo', 'group' => 'cl_b_group', 'tip' => 'cl_b_tip');
}

/* советы по уходу: по виду и возрасту питомца и по сезону */
function petgo_cl_tips($pets) {
    $m    = (int) wp_date('n', time(), petgo_tz());
    $tips = array();
    $has  = array('dog' => false, 'cat' => false);
    foreach ($pets as $p) {
        $sp = (string) get_post_meta($p->ID, '_pf_species', true);
        if (isset($has[$sp])) {
            $has[$sp] = $p->post_title;
        }
        $age = (string) get_post_meta($p->ID, '_pf_age', true);
        if (preg_match('/(\d+)/', $age, $mm) && (int) $mm[1] >= 8 && preg_match('/(год|лет|жыл|year)/iu', $age)) {
            $tips[] = array('🩺', petgo_site_lf('%s уже в почтенном возрасте: раз в полгода стоит показываться ветеринару.', $p->post_title));
        }
        if ((string) get_post_meta($p->ID, '_pf_vaccines', true) === '') {
            $tips[] = array('💉', petgo_site_lf('Добавьте в анкету %s даты прививок — няне будет спокойнее.', $p->post_title));
        }
    }
    if ($m >= 11 || $m <= 2) {
        if ($has['dog']) {
            $tips[] = array('❄️', petgo_site_l('Зимой после прогулки мойте лапы тёплой водой: реагенты раздражают кожу. Короткошёрстным собакам пригодится комбинезон.'));
        }
        $tips[] = array('🏠', petgo_site_l('В морозы прогулки лучше делить на 2–3 коротких — питомцу так комфортнее.'));
    } elseif ($m >= 6 && $m <= 8) {
        $tips[] = array('☀️', petgo_site_l('Летом гуляем рано утром и вечером, берём воду. Не оставляйте питомца в машине даже на пять минут.'));
    } else {
        $tips[] = array('🌿', petgo_site_l('Весной и осенью активны клещи: проверьте, когда была последняя обработка от паразитов.'));
    }
    if ($has['cat']) {
        $tips[] = array('🐾', petgo_site_l('Кошкам нужно много воды: поставьте несколько мисок в разных комнатах или фонтанчик.'));
    }
    if ($has['dog']) {
        $tips[] = array('🦮', petgo_site_l('Собакам полезно 1–2 часа активности в день. Попросите няню о длинной прогулке с играми.'));
    }
    return array_slice($tips, 0, 5);
}

/* ============================================================
   СТРАНИЦА «КАБИНЕТ» НА САЙТЕ
   ============================================================ */
function petgo_cl_icon($n) {
    static $P = array(
        'paw'    => '<path d="M12 12.3c-2.7 0-4.8 2.1-4.8 4 0 1.7 1.4 2.4 2.8 2 .9-.3 1.4-.6 2-.6s1.1.3 2 .6c1.4.4 2.8-.3 2.8-2 0-1.9-2.1-4-4.8-4z"/><ellipse cx="6.6" cy="10" rx="1.5" ry="2"/><ellipse cx="9.9" cy="6.5" rx="1.5" ry="2.1"/><ellipse cx="14.1" cy="6.5" rx="1.5" ry="2.1"/><ellipse cx="17.4" cy="10" rx="1.5" ry="2"/>',
        'map'    => '<path d="M9 4L3 6.5v13L9 17l6 2.5 6-2.5v-13L15 7z"/><path d="M9 4v13M15 7v12.5"/>',
        'walk'   => '<circle cx="13" cy="4.5" r="1.8"/><path d="M9 21l2.5-6 2.5 2v4M11.5 15L12 9l-3 2-1.5 3M12 9l3 3 3 .5"/>',
        'gift'   => '<rect x="3.5" y="9" width="17" height="11.5" rx="2"/><path d="M3.5 13h17M12 9v11.5"/><path d="M12 9c-1.5-3.5-5.5-4-5.5-1.5S9.5 9 12 9zm0 0c1.5-3.5 5.5-4 5.5-1.5S14.5 9 12 9z"/>',
        'user'   => '<circle cx="12" cy="8.2" r="3.7"/><path d="M4.5 20c0-4 3.3-6.3 7.5-6.3s7.5 2.3 7.5 6.3"/>',
        'chat'   => '<path d="M12 4.5c4.4 0 8 2.9 8 6.6s-3.6 6.6-8 6.6c-.9 0-1.7-.1-2.5-.3L5.5 19.5l.9-3.3C4.9 15 4 13.2 4 11.1c0-3.7 3.6-6.6 8-6.6z"/>',
        'spark'  => '<path d="M10.5 4l1.7 4.8 4.8 1.7-4.8 1.7-1.7 4.8-1.7-4.8L4 10.5l4.8-1.7z"/><path d="M18 14.5l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'camera' => '<path d="M4 8.5A1.5 1.5 0 0 1 5.5 7H8l1.5-2h5L16 7h2.5A1.5 1.5 0 0 1 20 8.5v9a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 17.5z"/><circle cx="12" cy="13" r="3.3"/>',
        'edit'   => '<path d="M5 19h3.5L19 8.5 15.5 5 5 15.5z"/><path d="M13.5 7l3.5 3.5"/>',
        'star'   => '<path d="M12 3.8l2.5 5.2 5.7.8-4.1 4 1 5.7L12 16.8l-5.1 2.7 1-5.7-4.1-4 5.7-.8z"/>',
        'pin'    => '<path d="M12 21s-6.5-5.7-6.5-11a6.5 6.5 0 0 1 13 0c0 5.3-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>',
        'clock'  => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'logout' => '<path d="M9 20H5.5A1.5 1.5 0 0 1 4 18.5v-13A1.5 1.5 0 0 1 5.5 4H9"/><path d="M15.5 16.5L20 12l-4.5-4.5M20 12H9"/>',
        'key'    => '<circle cx="8" cy="15" r="4"/><path d="M11 12l8.5-8.5M16 7l2.5 2.5M13.5 9.5L15 11"/>',
        'x'      => '<path d="M6 6l12 12M18 6L6 18"/>',
        'send'   => '<path d="M4 12l16-8-6 16-2.5-6.5z"/><path d="M11.5 13.5L20 4"/>',
        'left'   => '<path d="M15 5l-7 7 7 7"/>',
        'right'  => '<path d="M9 5l7 7-7 7"/>',
        'clip'   => '<path d="M20 12.5l-8 8a5.5 5.5 0 0 1-7.8-7.8l8.5-8.5a3.7 3.7 0 0 1 5.2 5.2l-8.4 8.4a1.8 1.8 0 0 1-2.6-2.6l7.8-7.8"/>',
        'eye'    => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="2.8"/>',
        'locate' => '<circle cx="12" cy="12" r="3.5"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3"/><circle cx="12" cy="12" r="7.5"/>',
        'id'     => '<rect x="3.5" y="5" width="17" height="14" rx="2.5"/><circle cx="9" cy="11" r="2.2"/><path d="M5.8 16c.6-1.5 1.8-2.3 3.2-2.3s2.6.8 3.2 2.3M14.5 10h3.5M14.5 13.5h3.5"/>',
        'check'  => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
    );
    return isset($P[$n]) ? '<svg class="pg-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $P[$n] . '</svg>' : '';
}

/* лист снизу внутри кабинета (свой, чтобы при переходах между страницами всё оставалось на месте) */
function petgo_cl_sheet($id, $title, $body, $sub = '') {
    return '<div class="pgk-sheet" id="pgk-' . esc_attr($id) . '" hidden role="dialog" aria-modal="true" aria-label="' . esc_attr($title) . '"><div class="pgk-sbg" data-k-close></div><div class="pgk-sin"><div class="pgk-sh" data-k-drag><i class="pgk-grab" aria-hidden="true"></i><div><h3>' . esc_html($title) . '</h3>' . ($sub !== '' ? '<p>' . esc_html($sub) . '</p>' : '') . '</div><button type="button" class="pg-b pgk-x" data-k-close aria-label="' . esc_attr(petgo_site_l('Закрыть')) . '">' . petgo_cl_icon('x') . '</button></div><div class="pgk-sc">' . $body . '</div></div></div>';
}

function petgo_cl_field($name, $label, $type = 'text', $val = '', $attr = '') {
    return '<label class="pgk-f"><span>' . esc_html($label) . '</span><input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '"' . $attr . '></label>';
}

/* --- гость: вход и регистрация --- */
function petgo_cl_guest() {
    $L  = 'petgo_site_l';
    $hp = '<div class="pg-hp" aria-hidden="true"><input type="text" name="pgr_web" tabindex="-1" autocomplete="off"></div><input type="hidden" name="lang" value="' . esc_attr(petgo_site_lang()) . '">';
    $h  = '<section class="pg-sec pg-sec-tight"><div class="pg-wrap"><div class="pgk-auth pg-rise">';
    $h .= '<div class="pgk-auth-card"><div class="pgk-seg" role="tablist"><i class="pgk-segi" aria-hidden="true"></i><button type="button" class="pg-b on" data-auth="in" role="tab" aria-selected="true">' . esc_html($L('Вход')) . '</button><button type="button" class="pg-b" data-auth="up" role="tab" aria-selected="false">' . esc_html($L('Регистрация')) . '</button></div>';
    $h .= '<div class="pgk-auth-pages">';
    /* вход */
    $h .= '<form class="pgk-form pgk-auth-p on" data-auth-p="in" data-op="petgo_cl_login" novalidate>' . $hp . '<div class="pgk-fg">' . petgo_cl_field('login', $L('Логин'), 'text', '', ' autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="' . esc_attr($L('или номер телефона')) . '" required') . '<label class="pgk-f"><span>' . esc_html($L('Пароль')) . '</span><input type="password" name="pass" autocomplete="current-password" required><button type="button" class="pg-b pgk-eye" data-eye aria-label="' . esc_attr($L('Показать пароль')) . '">' . petgo_cl_icon('eye') . '</button></label></div>';
    $h .= '<p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pg-btn pg-btn-primary pgk-go"><span>' . esc_html($L('Войти')) . '</span><i class="pg-spin" aria-hidden="true"></i></button><button type="button" class="pg-b pgk-link" data-k-open="forgot">' . esc_html($L('Забыли логин или пароль?')) . '</button></form>';
    /* регистрация */
    $h .= '<form class="pgk-form pgk-auth-p" data-auth-p="up" data-op="petgo_cl_reg" novalidate>' . $hp . '<div class="pgk-fg">' . petgo_cl_field('name', $L('Имя'), 'text', '', ' autocomplete="name" maxlength="60" required') . petgo_cl_field('phone', $L('Телефон'), 'tel', '', ' autocomplete="tel" inputmode="tel" maxlength="20" placeholder="+7 700 000 00 00" required') . '</div>';
    $h .= '<div class="pgk-fg">' . petgo_cl_field('login', $L('Логин'), 'text', '', ' autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="30" placeholder="' . esc_attr($L('латиницей, например aigerim')) . '" required') . '<label class="pgk-f"><span>' . esc_html($L('Пароль')) . '</span><input type="password" name="pass" autocomplete="new-password" minlength="8" placeholder="' . esc_attr($L('не короче 8 символов')) . '" required><button type="button" class="pg-b pgk-eye" data-eye aria-label="' . esc_attr($L('Показать пароль')) . '">' . petgo_cl_icon('eye') . '</button></label></div>';
    $h .= '<label class="pgw-consent"><input type="checkbox" name="consent" value="1"><i aria-hidden="true">' . petgo_cl_icon('check') . '</i><span>' . esc_html($L('Согласен на обработку персональных данных')) . '</span></label>';
    $h .= '<p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pg-btn pg-btn-primary pgk-go"><span>' . esc_html($L('Создать кабинет')) . '</span><i class="pg-spin" aria-hidden="true"></i></button><p class="pgk-note">' . esc_html($L('Если вы уже пользовались PetGo, питомцы с вашим номером появятся в кабинете сами.')) . '</p></form>';
    $h .= '</div></div>';
    /* что даёт кабинет */
    $h .= '<div class="pgk-perks">';
    foreach (array(array('map', 'Питомец на карте', 'Где он гуляет прямо сейчас и где был раньше'), array('camera', 'Фото с прогулок', 'Няня присылает фото — они видны на карте'), array('star', 'Отзывы няням', 'Оценка после каждой прогулки'), array('gift', 'Акции и групповые прогулки', 'Всё для вас — в одном месте')) as $p) {
        $h .= '<div class="pgk-perk"><span>' . petgo_cl_icon($p[0]) . '</span><div><b>' . esc_html($L($p[1])) . '</b><small>' . esc_html($L($p[2])) . '</small></div></div>';
    }
    $h .= '</div></div>';
    /* регистрация питомца без аккаунта и вход сотрудников */
    $h .= '<div class="pg-grid pg-g2 pg-acc pgk-more"><button type="button" class="pg-b pg-acc-card pg-rise" data-sheet="pet"><span class="pg-acc-i">' . petgo_site_icon('paw') . '</span><b>' . esc_html($L('Зарегистрировать питомца')) . '</b><span>' . esc_html($L('Без аккаунта: анкета попадёт к нам, мы добавим питомца в базу и свяжемся с вами.')) . '</span><i class="pg-acc-go">' . petgo_site_icon('right') . '</i></button>';
    $h .= '<a class="pg-acc-card pg-acc-staff pg-rise" data-ext href="' . esc_url(petgo_page_url()) . '"><span class="pg-acc-i">' . petgo_site_icon('key') . '</span><b>' . esc_html($L('Войти как сотрудник')) . '</b><span>' . esc_html($L('Для нянь, менеджеров и администраторов PetGo.')) . '</span><i class="pg-acc-go">' . petgo_site_icon('right') . '</i></a></div>';
    $h .= '</div></section>';
    /* забыли пароль */
    $f  = '<form class="pgk-form" data-op="petgo_cl_forgot" novalidate>' . $hp . '<p class="pgk-note pgk-note-top">' . esc_html($L('Пароль восстанавливает менеджер: оставьте номер — мы проверим, что это вы, и пришлём новый пароль.')) . '</p><div class="pgk-fg">' . petgo_cl_field('phone', $L('Телефон'), 'tel', '', ' inputmode="tel" maxlength="20" placeholder="+7 700 000 00 00" required') . petgo_cl_field('who', $L('Имя или логин'), 'text', '', ' maxlength="80"') . '</div>';
    $f .= '<p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pg-btn pg-btn-primary pgk-go"><span>' . esc_html($L('Написать менеджеру')) . '</span><i class="pg-spin" aria-hidden="true"></i></button></form>';
    $f .= '<div class="pgk-done" hidden><svg class="pg-ok-svg" viewBox="0 0 80 80" aria-hidden="true"><circle cx="40" cy="40" r="36"/><path d="M24 41.5l11 11L57 29"/></svg><b>' . esc_html($L('Менеджер уже знает')) . '</b><p>' . esc_html($L('Скоро свяжемся с вами. Можно сразу написать нам в WhatsApp:')) . '</p><a class="pg-btn pg-btn-second" data-wa target="_blank" rel="noopener" href="https://wa.me/' . esc_attr(petgo_site_wa()) . '">' . petgo_site_icon('chat') . 'WhatsApp</a></div>';
    $h .= petgo_cl_sheet('forgot', $L('Восстановить доступ'), $f);
    return $h;
}

/* --- вошедший клиент --- */
function petgo_cl_dashboard() {
    $uid  = petgo_cl_uid();
    $u    = get_userdata($uid);
    $L    = 'petgo_site_l';
    $pets = petgo_cl_pets($uid);
    $st   = petgo_cl_pets_state($uid);
    $ai   = function_exists('petgo_site_ai') && petgo_site_ai();
    $un   = petgo_cl_unread($uid);
    $first = explode(' ', trim($u->display_name));
    $live  = null;
    foreach ($st as $s) {
        if ($s['live']) {
            $live = $s;
            break;
        }
    }
    $cfg = array(
        'aj' => petgo_rel_url(admin_url('admin-ajax.php')), 'n' => wp_create_nonce('petgo_cl'), 'lang' => petgo_site_lang(), 'uid' => $uid,
        'pets' => $st, 'leaflet' => array('css' => 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css', 'js' => 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js'),
        'tr' => petgo_cl_js_dict(),
    );
    $h  = '<div class="pgk" id="pgk" data-tab="pets">';
    $h .= '<script type="application/json" id="pgk-cfg">' . wp_json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    /* шапка */
    $h .= '<header class="pgk-top"><div class="pg-wrap pgk-top-in"><label class="pgk-me" title="' . esc_attr($L('Сменить фото')) . '">' . petgo_cl_av(petgo_user_avatar_url($uid), $u->display_name, 'pgk-av-me') . '<input type="file" accept="image/*" class="pgw-vh" data-k-avatar><i class="pgk-cam">' . petgo_cl_icon('camera') . '</i></label>';
    $h .= '<div class="pgk-hi"><small>' . esc_html(petgo_site_lf('Здравствуйте, %s', $first[0])) . '</small><b>' . esc_html($L('Мой кабинет')) . '</b><span class="pgk-ids"><span>@' . esc_html($u->user_login) . '</span><span>ID ' . esc_html(petgo_cl_code($uid)) . '</span></span></div>';
    $h .= '<button type="button" class="pg-b pgk-ib" data-k-open="chat" aria-label="' . esc_attr($L('Написать менеджеру')) . '">' . petgo_cl_icon('chat') . '<i class="pgk-bd" data-k-unread' . ($un ? '' : ' hidden') . '>' . (int) $un . '</i></button></div></header>';
    /* живая прогулка — баннер */
    $h .= '<div class="pg-wrap"><button type="button" class="pg-b pgk-livebar" data-k-tab="map"' . ($live ? '' : ' hidden') . '><span class="pgk-pulse" aria-hidden="true"></span><span class="pgk-lb-t"><b data-k-live-name>' . esc_html($live ? $live['name'] : '') . '</b><span data-k-live-text>' . esc_html($live ? $live['text'] : '') . '</span></span><span class="pgk-lb-go">' . esc_html($L('На карте')) . petgo_cl_icon('right') . '</span></button></div>';
    $h .= '<div class="pgk-panes">';

    /* ПИТОМЦЫ */
    $h .= '<section class="pgk-pane on" data-pane="pets"><div class="pg-wrap"><div class="pgk-ph"><h2>' . esc_html($L('Мои питомцы')) . '</h2><button type="button" class="pg-b pgk-add" data-k-pet="0">' . petgo_cl_icon('plus') . '<span>' . esc_html($L('Добавить')) . '</span></button></div>';
    $h .= '<div class="pgk-pets">';
    if (!$pets) {
        $h .= '<div class="pgk-empty">' . petgo_cl_icon('paw') . '<b>' . esc_html($L('Пока здесь пусто')) . '</b><p>' . esc_html($L('Добавьте питомца — анкета займёт пару минут. Питомцы, которых мы уже знаем по вашему номеру, появятся сами.')) . '</p><button type="button" class="pg-b pg-btn pg-btn-primary" data-k-pet="0">' . esc_html($L('Добавить питомца')) . '</button></div>';
    }
    foreach ($st as $i => $s) {
        $pid = $s['id'];
        $sub = array();
        $sp  = $s['sp'] === 'dog' ? $L('Собака') : ($s['sp'] === 'cat' ? $L('Кошка') : (string) get_post_meta($pid, '_pf_species_other', true));
        foreach (array($sp, (string) get_post_meta($pid, '_pf_breed', true), (string) get_post_meta($pid, '_pf_age', true)) as $x) {
            if (trim($x) !== '') {
                $sub[] = $x;
            }
        }
        $h .= '<article class="pgk-pet" data-pet="' . $pid . '" style="--d:' . ($i * 70) . 'ms"><div class="pgk-pet-top"><label class="pgk-pet-av' . ($s['live'] ? ' live' : '') . '">' . petgo_cl_av($s['av'], $s['name'], '') . '<input type="file" accept="image/*" class="pgw-vh" data-k-petav="' . $pid . '"><i class="pgk-cam">' . petgo_cl_icon('camera') . '</i></label>';
        $h .= '<div class="pgk-pet-t"><b>' . esc_html($s['name']) . '</b><span class="pgk-ids"><span>@' . esc_html(petgo_pet_nick($pid)) . '</span><span>ID ' . esc_html(petgo_pet_code($pid)) . '</span></span><small>' . esc_html(implode(' · ', $sub)) . '</small></div></div>';
        $h .= '<div class="pgk-status st-' . esc_attr($s['st']) . '" data-k-st="' . $pid . '"><i></i><span>' . ($s['hi'] !== '' && $s['live'] ? '<em>' . esc_html($s['hi']) . '</em> ' : '') . esc_html($s['text']) . '</span></div>';
        $h .= '<div class="pgk-pet-acts"><button type="button" class="pg-b pgk-chip" data-k-tab="map" data-k-focus="' . $pid . '">' . petgo_cl_icon('map') . esc_html($L('Карта')) . '</button><button type="button" class="pg-b pgk-chip" data-k-tab="walks">' . petgo_cl_icon('walk') . esc_html($L('Прогулки')) . '</button><button type="button" class="pg-b pgk-chip" data-k-pet="' . $pid . '">' . petgo_cl_icon('edit') . esc_html($L('Анкета')) . '</button></div>';
        $ph = array_slice(array_reverse(petgo_photos($pid)), 0, 8);
        if ($ph) {
            $h .= '<div class="pgk-strip">';
            foreach ($ph as $x) {
                $h .= '<button type="button" class="pg-b" data-k-photo="' . esc_url(petgo_media($x['url'])) . '" data-k-cap="' . esc_attr(wp_date('d.m.Y H:i', (int) $x['t'], petgo_tz())) . '"><img src="' . esc_url(petgo_media($x['url'])) . '" alt="" loading="lazy" decoding="async"></button>';
            }
            $h .= '</div>';
        }
        $h .= '</article>';
    }
    $h .= '</div></div></section>';

    /* КАРТА */
    $h .= '<section class="pgk-pane" data-pane="map"><div class="pgk-mapw"><div class="pgk-map" id="pgk-map"><div class="pgk-map-load"><i class="pg-spin"></i></div></div>';
    $h .= '<div class="pgk-map-ui"><div class="pgk-mchips" data-k-mchips>';
    foreach ($st as $s) {
        $h .= '<button type="button" class="pg-b pgk-mchip" data-k-mpet="' . (int) $s['id'] . '">' . petgo_cl_av($s['av'], $s['name'], 'sm') . '<span>' . esc_html($s['name']) . '</span></button>';
    }
    $h .= '</div><div class="pgk-mseg"><button type="button" class="pg-b on" data-k-mmode="now">' . esc_html($L('Сейчас')) . '</button><button type="button" class="pg-b" data-k-mmode="all">' . esc_html($L('Все места')) . '</button><button type="button" class="pg-b" data-k-mmode="photos">' . esc_html($L('Фото')) . '</button></div></div>';
    $h .= '<div class="pgk-mcard" data-k-mcard hidden></div><button type="button" class="pg-b pgk-mloc" data-k-mfit aria-label="' . esc_attr($L('Показать всё')) . '">' . petgo_cl_icon('locate') . '</button></div></section>';

    /* ПРОГУЛКИ */
    $h .= '<section class="pgk-pane" data-pane="walks"><div class="pg-wrap"><div class="pgk-ph"><h2>' . esc_html($L('Прогулки и визиты')) . '</h2></div>' . petgo_cl_walks_html($uid) . '</div></section>';

    /* ДЛЯ ВАС */
    $h .= '<section class="pgk-pane" data-pane="for"><div class="pg-wrap">' . petgo_cl_for_html($uid, $pets) . '</div></section>';

    /* ПРОФИЛЬ */
    $h .= '<section class="pgk-pane" data-pane="me"><div class="pg-wrap">' . petgo_cl_profile_html($u) . '</div></section>';
    $h .= '</div>';

    /* нижнее меню кабинета (стекло) */
    $tabs = array('pets' => array('paw', 'Питомцы'), 'map' => array('map', 'Карта'), 'ai' => array('spark', 'Помощник'), 'walks' => array('walk', 'Прогулки'), 'for' => array('gift', 'Для вас'), 'me' => array('user', 'Профиль'));
    if (!$ai) {
        unset($tabs['ai']);
    }
    $h .= '<nav class="pgk-dock pg-glass" data-glass data-glass-bezel="18" data-glass-scale="44" data-glass-blur="9" aria-label="' . esc_attr($L('Кабинет')) . '"><span class="pgk-lens" aria-hidden="true"></span>';
    foreach ($tabs as $k => $t) {
        if ($k === 'ai') {
            $h .= '<button type="button" class="pg-b pgk-dai" data-chat aria-haspopup="dialog"><i>' . petgo_cl_icon('spark') . '</i><span>' . esc_html($L($t[1])) . '</span></button>';
            continue;
        }
        $h .= '<button type="button" class="pg-b' . ($k === 'pets' ? ' on' : '') . '" data-k-tab="' . $k . '">' . petgo_cl_icon($t[0]) . '<span>' . esc_html($L($t[1])) . '</span></button>';
    }
    $h .= '</nav>';

    /* листы: анкета, чат, отзыв, фото */
    $h .= '<div class="pgk-sheet pgk-sheet-w" id="pgk-pet" hidden role="dialog" aria-modal="true"><div class="pgk-sbg" data-k-close></div><div class="pgk-sin"><div class="pgk-sh" data-k-drag><i class="pgk-grab" aria-hidden="true"></i><div><h3 data-k-pet-title>' . esc_html($L('Новый питомец')) . '</h3><p>' . esc_html($L('Карточки листаются — заполните, что знаете')) . '</p></div><button type="button" class="pg-b pgk-x" data-k-close aria-label="' . esc_attr($L('Закрыть')) . '">' . petgo_cl_icon('x') . '</button></div><div class="pgk-sc" data-k-pet-body></div></div></div>';
    $h .= '<template id="pgk-tpl-new">' . petgo_pf_wizard('client') . '</template>';
    foreach ($pets as $p) {
        $pre = array('title' => $p->post_title);
        foreach (petgo_pet_fields() as $k => $f) {
            $pre[$k] = (string) get_post_meta($p->ID, '_pf_' . $k, true);
        }
        $h .= '<template id="pgk-tpl-' . (int) $p->ID . '">' . petgo_pf_wizard('client', array('pre' => $pre, 'pet_id' => (int) $p->ID)) . '</template>';
    }
    $chat  = '<div class="pgk-chat"><div class="pgk-msgs" data-k-msgs><div class="pgk-chat-hi">' . petgo_cl_icon('chat') . '<b>' . esc_html($L('Менеджер PetGo')) . '</b><p>' . esc_html($L('Спросите что угодно: время прогулки, перенос, оплата, пожелания няне.')) . '</p></div></div>';
    $chat .= '<form class="pgk-cf" data-k-send><label class="pg-b pgk-clip" aria-label="' . esc_attr($L('Фото')) . '"><input type="file" accept="image/*" class="pgw-vh" name="photo">' . petgo_cl_icon('clip') . '</label><textarea name="body" rows="1" maxlength="2000" placeholder="' . esc_attr($L('Сообщение менеджеру')) . '"></textarea><button type="submit" class="pg-b pgk-send" aria-label="' . esc_attr($L('Отправить')) . '">' . petgo_cl_icon('send') . '</button></form></div>';
    $h .= petgo_cl_sheet('chat', $L('Написать менеджеру'), $chat, $L('Обычно отвечаем в течение 15 минут'));
    $rv  = '<form class="pgk-form pgk-rvf" data-k-rvform novalidate><input type="hidden" name="order" value=""><p class="pgk-rv-for" data-k-rv-for></p><div class="pgk-stars" role="radiogroup" aria-label="' . esc_attr($L('Оценка')) . '">';
    for ($i = 1; $i <= 5; $i++) {
        $rv .= '<label><input type="radio" name="rating" value="' . $i . '"' . ($i === 5 ? ' checked' : '') . '><span>' . petgo_cl_icon('star') . '</span></label>';
    }
    $rv .= '</div><div class="pgk-fg"><label class="pgk-f pgk-fa"><span>' . esc_html($L('Как всё прошло?')) . '</span><textarea name="text" rows="4" maxlength="1500" placeholder="' . esc_attr($L('Что понравилось, что можно сделать лучше')) . '"></textarea></label></div><p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pg-btn pg-btn-primary pgk-go"><span>' . esc_html($L('Отправить отзыв')) . '</span><i class="pg-spin" aria-hidden="true"></i></button></form>';
    $h .= petgo_cl_sheet('review', $L('Отзыв о прогулке'), $rv);
    $h .= '<div class="pgk-lb" data-k-lb hidden><button type="button" class="pg-b pgk-x" data-k-lbx aria-label="' . esc_attr($L('Закрыть')) . '">' . petgo_cl_icon('x') . '</button><img alt=""><span></span></div>';
    $h .= '<div class="pgk-toast" data-k-toast role="status" aria-live="polite"></div>';
    return $h . '</div>';
}

function petgo_cl_av($url, $name, $cls = '') {
    $c = 'pgk-av' . ($cls !== '' ? ' ' . $cls : '');
    if ($url) {
        return '<span class="' . esc_attr($c) . '"><img src="' . esc_url($url) . '" alt="" loading="lazy" decoding="async"></span>';
    }
    return '<span class="' . esc_attr($c) . '"><b>' . esc_html(mb_strtoupper(mb_substr(trim($name) !== '' ? trim($name) : '?', 0, 1))) . '</b></span>';
}

/* история прогулок + отзывы */
function petgo_cl_walks_html($uid) {
    $L    = 'petgo_site_l';
    $list = petgo_cl_orders($uid, 60);
    if (!$list) {
        return '<div class="pgk-empty">' . petgo_cl_icon('walk') . '<b>' . esc_html($L('Прогулок ещё не было')) . '</b><p>' . esc_html($L('Когда няня погуляет с питомцем, здесь появятся время, фото, маршрут и оценка.')) . '</p><button type="button" class="pg-b pg-btn pg-btn-primary" data-sheet="request">' . esc_html($L('Заказать прогулку')) . '</button></div>';
    }
    $svc = array('walk' => 'Выгул', 'visit' => 'Визит домой', 'other' => 'Услуга');
    $STL = array('new' => 'Подбираем няню', 'assigned' => 'Запланирована', 'arrived' => 'Няня на месте', 'picked' => 'Гуляют сейчас', 'done' => 'Завершена', 'confirmed' => 'Завершена', 'cancelled' => 'Отменена');
    $h   = '<div class="pgk-walks">';
    $mon = '';
    foreach ($list as $i => $o) {
        $id  = $o->ID;
        $st  = petgo_o($id, 'status');
        $ts  = (int) petgo_o($id, 'ts');
        $pid = (int) petgo_o($id, 'pet');
        $m   = wp_date('m.Y', $ts, petgo_tz());
        if ($m !== $mon) {
            $mon = $m;
            $h  .= '<div class="pgk-mon">' . esc_html(petgo_cl_month($ts)) . '</div>';
        }
        $nid = (int) petgo_o($id, 'nanny');
        $nn  = petgo_nanny_label($id);
        $sv  = petgo_o($id, 'service');
        $dur = max(1, (int) petgo_o($id, 'dur'));
        $h  .= '<article class="pgk-walk st-' . esc_attr($st) . '" style="--d:' . min(10, $i) * 50 . 'ms"><div class="pgk-wd"><b>' . esc_html(wp_date('d', $ts, petgo_tz())) . '</b><small>' . esc_html(wp_date('H:i', $ts, petgo_tz())) . '</small></div><div class="pgk-wb"><div class="pgk-wt"><b>' . esc_html(get_the_title($pid)) . '</b><span class="pgk-wst">' . esc_html($L($STL[$st] ?? $st)) . '</span></div>';
        $h  .= '<small>' . esc_html($L($svc[$sv] ?? 'Услуга') . ' · ' . $dur . ' ' . $L('мин') . ($nn !== '' ? ' · ' . $nn : '')) . '</small>';
        $ph  = petgo_cl_order_photos($id, $pid);
        $rp  = petgo_o($id, 'rphoto');
        if (!$ph && is_array($rp) && !empty($rp['url'])) {
            $ph = array(array('u' => petgo_media($rp['url']), 't' => ''));
        }
        if ($ph) {
            $h .= '<div class="pgk-strip sm">';
            foreach (array_slice($ph, 0, 6) as $x) {
                $h .= '<button type="button" class="pg-b" data-k-photo="' . esc_url($x['u']) . '" data-k-cap="' . esc_attr($x['t']) . '"><img src="' . esc_url($x['u']) . '" alt="" loading="lazy" decoding="async"></button>';
            }
            $h .= '</div>';
        }
        if (in_array($st, array('done', 'confirmed'), true)) {
            $rv = petgo_rv_by_order($id);
            if ($rv) {
                $h .= '<div class="pgk-myrv">' . str_repeat('★', (int) $rv->rating) . '<span>' . str_repeat('★', 5 - (int) $rv->rating) . '</span> <small>' . esc_html($L('Ваш отзыв')) . '</small></div>';
            } else {
                $h .= '<div class="pgk-wacts"><button type="button" class="pg-b pgk-chip pgk-chip-acc" data-k-review="' . $id . '" data-k-rv-name="' . esc_attr($nn) . '" data-k-rv-av="' . esc_url($nid ? petgo_user_avatar_url($nid) : '') . '">' . petgo_cl_icon('star') . esc_html($nn !== '' ? petgo_site_lf('Оценить няню %s', $nn) : $L('Оставить отзыв')) . '</button>';
                if (petgo_trk_points($id)) {
                    $h .= '<button type="button" class="pg-b pgk-chip" data-k-tab="map" data-k-walk="' . $id . '">' . petgo_cl_icon('map') . esc_html($L('Маршрут')) . '</button>';
                }
                $h .= '</div>';
            }
        } elseif (in_array($st, array('arrived', 'picked'), true)) {
            $h .= '<div class="pgk-wacts"><button type="button" class="pg-b pgk-chip pgk-chip-acc" data-k-tab="map" data-k-focus="' . $pid . '">' . petgo_cl_icon('map') . esc_html($L('Смотреть на карте')) . '</button></div>';
        }
        $h .= '</div></article>';
    }
    return $h . '</div>';
}

function petgo_cl_month($ts) {
    $m  = (int) wp_date('n', $ts, petgo_tz());
    $ru = array(1 => 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь');
    return petgo_site_l($ru[$m]) . ' ' . wp_date('Y', $ts, petgo_tz());
}

/* «Для вас»: рекомендации, групповые прогулки, акции */
function petgo_cl_for_html($uid, $pets) {
    $L  = 'petgo_site_l';
    $h  = '';
    $my = trim((string) get_user_meta($uid, 'petgo_cl_rec', true));
    $tips = petgo_cl_tips($pets);
    $h .= '<div class="pgk-ph"><h2>' . esc_html($L('Рекомендации')) . '</h2></div><div class="pgk-recs">';
    if ($my !== '') {
        $h .= '<div class="pgk-rec pgk-rec-me"><span>💬</span><div><b>' . esc_html($L('От вашего менеджера')) . '</b><p>' . nl2br(esc_html($my)) . '</p></div></div>';
    }
    foreach ($tips as $t) {
        $h .= '<div class="pgk-rec"><span>' . esc_html($t[0]) . '</span><p>' . esc_html($t[1]) . '</p></div>';
    }
    $h .= '</div>';
    $B = petgo_board();
    $groups = array();
    $promos = array();
    foreach ($B as $b) {
        if ($b['type'] === 'group') {
            $groups[] = $b;
        } elseif ($b['type'] === 'promo') {
            $promos[] = $b;
        } else {
            $h = str_replace('<div class="pgk-recs">', '<div class="pgk-recs"><div class="pgk-rec"><span>🐶</span><div><b>' . esc_html($L($b['title'])) . '</b><p>' . esc_html($L($b['text'])) . '</p></div></div>', $h);
        }
    }
    /* скидка на первый заказ — тоже акция */
    $c = petgo_site_cfg();
    if ((int) $c['off'] > 0 && !petgo_cl_orders($uid, 1)) {
        array_unshift($promos, array('id' => 'first', 'title' => petgo_site_lf('−%s на первый заказ', (int) $c['off'] . '%'), 'text' => $L('Скидка действует на первую прогулку или неделю визитов.'), 'when' => '', 'place' => ''));
    }
    $h .= '<div class="pgk-ph"><h2>' . esc_html($L('Групповые прогулки')) . '</h2></div>';
    if ($groups) {
        $h .= '<div class="pgk-cards">';
        foreach ($groups as $b) {
            $joined = (bool) get_user_meta($uid, 'petgo_join_' . $b['id'], true);
            $h .= '<article class="pgk-ev"><div class="pgk-ev-d">' . petgo_cl_icon('walk') . '</div><div class="pgk-ev-b"><b>' . esc_html($L($b['title'])) . '</b>' . ($b['when'] !== '' ? '<span>' . petgo_cl_icon('clock') . esc_html($b['when']) . '</span>' : '') . ($b['place'] !== '' ? '<span>' . petgo_cl_icon('pin') . esc_html($L($b['place'])) . '</span>' : '') . ($b['text'] !== '' ? '<p>' . esc_html($L($b['text'])) . '</p>' : '');
            $h .= '<button type="button" class="pg-b pg-btn ' . ($joined ? 'pg-btn-second' : 'pg-btn-primary') . ' pgk-join" data-k-join="' . esc_attr($b['id']) . '"' . ($joined ? ' disabled' : '') . '>' . esc_html($L($joined ? 'Вы записаны' : 'Записаться')) . '</button></div></article>';
        }
        $h .= '</div>';
    } else {
        $h .= '<div class="pgk-empty sm">' . petgo_cl_icon('walk') . '<p>' . esc_html($L('Скоро объявим новые групповые прогулки — следите за этим разделом.')) . '</p></div>';
    }
    $h .= '<div class="pgk-ph"><h2>' . esc_html($L('Акции')) . '</h2></div>';
    if ($promos) {
        $h .= '<div class="pgk-cards">';
        foreach ($promos as $b) {
            $h .= '<article class="pgk-promo"><i aria-hidden="true"></i><b>' . esc_html($L($b['title'])) . '</b>' . ($b['text'] !== '' ? '<p>' . esc_html($L($b['text'])) . '</p>' : '') . ($b['when'] !== '' ? '<small>' . esc_html(petgo_site_lf('До %s', $b['when'])) . '</small>' : '') . '<button type="button" class="pg-b pg-btn pg-btn-glass" data-sheet="request" data-note="' . esc_attr(petgo_site_lf('Хочу по акции: %s', $L($b['title']))) . '">' . esc_html($L('Воспользоваться')) . '</button></article>';
        }
        $h .= '</div>';
    } else {
        $h .= '<div class="pgk-empty sm">' . petgo_cl_icon('gift') . '<p>' . esc_html($L('Сейчас акций нет. Мы напишем, когда появятся.')) . '</p></div>';
    }
    return $h;
}

function petgo_cl_profile_html($u) {
    $L  = 'petgo_site_l';
    $em = function_exists('petgo_real_email') ? petgo_real_email($u) : $u->user_email;
    $h  = '<div class="pgk-ph"><h2>' . esc_html($L('Профиль')) . '</h2></div><div class="pgk-idcard"><span>' . petgo_cl_icon('id') . '</span><div><small>' . esc_html($L('Ваш ID')) . '</small><b>' . esc_html(petgo_cl_code($u->ID)) . '</b></div><div><small>' . esc_html($L('Никнейм')) . '</small><b>@' . esc_html($u->user_login) . '</b></div></div>';
    $h .= '<form class="pgk-form" data-k-op="profile" novalidate><div class="pgk-fg">' . petgo_cl_field('name', $L('Имя'), 'text', $u->display_name, ' maxlength="60" autocomplete="name" required') . petgo_cl_field('phone', $L('Телефон'), 'tel', petgo_site_phone_fmt((string) get_user_meta($u->ID, 'petgo_phone', true)), ' maxlength="20" inputmode="tel" required') . petgo_cl_field('email', $L('Почта'), 'email', $em, ' maxlength="100" placeholder="' . esc_attr($L('необязательно')) . '" autocomplete="email"') . '</div>';
    $h .= '<p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pg-btn pg-btn-primary pgk-go"><span>' . esc_html($L('Сохранить')) . '</span><i class="pg-spin" aria-hidden="true"></i></button></form>';
    $h .= '<details class="pgk-more-d"><summary>' . petgo_cl_icon('key') . esc_html($L('Сменить пароль')) . '</summary><form class="pgk-form" data-k-op="pass" novalidate><div class="pgk-fg"><label class="pgk-f"><span>' . esc_html($L('Старый пароль')) . '</span><input type="password" name="old" autocomplete="current-password" required></label><label class="pgk-f"><span>' . esc_html($L('Новый пароль')) . '</span><input type="password" name="new" autocomplete="new-password" minlength="8" required></label></div><p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pg-btn pg-btn-second pgk-go"><span>' . esc_html($L('Изменить пароль')) . '</span><i class="pg-spin" aria-hidden="true"></i></button></form></details>';
    $h .= '<div class="pgk-rows"><button type="button" class="pg-b pgk-row" data-k-open="chat">' . petgo_cl_icon('chat') . '<span>' . esc_html($L('Написать менеджеру')) . '</span>' . petgo_cl_icon('right') . '</button>';
    $h .= '<a class="pgk-row" href="' . esc_url(petgo_site_url('home')) . '">' . petgo_site_icon('home') . '<span>' . esc_html($L('Сайт PetGo')) . '</span>' . petgo_cl_icon('right') . '</a>';
    $h .= '<a class="pgk-row red" data-ext href="' . esc_url(wp_logout_url(petgo_site_url('account'))) . '">' . petgo_cl_icon('logout') . '<span>' . esc_html($L('Выйти')) . '</span></a></div>';
    $h .= '<p class="pgk-note">' . esc_html($L('Забыли пароль? Выйдите и нажмите «Забыли логин или пароль?» — менеджер поможет.')) . '</p>';
    return $h;
}

function petgo_cl_js_dict() {
    $o = array();
    foreach (array('Сейчас', 'мин', 'Гуляет', 'Последнее место', 'Фото', 'Маршрут', 'обновлено', 'только что', 'назад', 'Нет данных о местах. Они появятся после первой прогулки с няней.', 'Сохранено', 'Не получилось — проверьте интернет.',
        'Ссылка скопирована', 'Новый питомец', 'Анкета питомца', 'Сегодня', 'Вчера', 'Няня', 'Прогулка', 'Здесь сделано фото', 'Отправлено', 'Отзыв для няни %s', 'Отзыв о прогулке') as $s) {
        $o[$s] = petgo_site_l($s);
    }
    return $o;
}

/* ============================================================
   ПРИЛОЖЕНИЕ СОТРУДНИКОВ: раздел «Клиенты» и «Для клиентов»
   ============================================================ */
add_filter('petgo_nav_items', function ($items, $adm) {
    if ($adm) {
        $new = array();
        foreach ($items as $k => $v) {
            $new[$k] = $v;
            if ($k === 'pets') {
                $new['clients'] = array(petgo_t('cl_nav'), petgo_page_url(array('pg' => 'clients')), 'staff', 'd');
            }
        }
        return $new;
    }
    return $items;
}, 5, 2);

add_filter('petgo_app_page', function ($done, $pg) {
    if ($pg === 'clients' && petgo_can('office')) {
        petgo_page_clients();
        return true;
    }
    return $done;
}, 10, 2);

function petgo_page_clients() {
    $f = isset($_GET['cv']) && $_GET['cv'] === 'board' ? 'board' : 'list';
    $all = get_users(array('role' => 'petgo_client', 'orderby' => 'registered', 'order' => 'DESC', 'number' => 500));
    echo '<div class="pgc-chips"><a class="pgc-chip' . ($f === 'list' ? ' on' : '') . '" href="' . esc_url(petgo_page_url(array('pg' => 'clients'))) . '">' . esc_html(petgo_t('cl_nav')) . '<i>' . count($all) . '</i></a><a class="pgc-chip' . ($f === 'board' ? ' on' : '') . '" href="' . esc_url(petgo_page_url(array('pg' => 'clients', 'cv' => 'board'))) . '">' . esc_html(petgo_t('cl_board')) . '<i>' . count(petgo_board()) . '</i></a></div>';
    if ($f === 'board') {
        petgo_page_board();
        return;
    }
    /* пароль только что сброшен — показать один раз */
    $np = get_transient('petgo_clpw_' . get_current_user_id());
    if (is_array($np)) {
        delete_transient('petgo_clpw_' . get_current_user_id());
        $cu = get_userdata((int) $np[0]);
        if ($cu) {
            $text = 'Здравствуйте! Ваш кабинет PetGo: ' . petgo_site_url('account') . "\nЛогин: " . $cu->user_login . "\nНовый пароль: " . $np[1] . "\nПароль можно сменить в профиле.";
            echo '<div class="pgc-card pgcl-pw"><b>' . esc_html(petgo_t('cl_pw_new') . ': ' . $cu->display_name) . '</b><div class="pgcl-pwv"><span>' . esc_html(petgo_t('login_s')) . '</span><code>' . esc_html($cu->user_login) . '</code><span>' . esc_html(petgo_t('pass_s')) . '</span><code>' . esc_html($np[1]) . '</code></div>';
            echo '<div class="pgc-btns"><button type="button" class="pgc-btn sec" data-copy="' . esc_attr($text) . '" data-done="' . esc_attr(petgo_t('pf_copied')) . '">' . petgo_pf_icon('copy') . esc_html(petgo_t('pf_copy')) . '</button><a class="pgc-btn" target="_blank" rel="noopener" href="' . esc_url('https://wa.me/' . petgo_phone_digits(get_user_meta($cu->ID, 'petgo_phone', true)) . '?text=' . rawurlencode($text)) . '">' . petgo_pf_icon('wa') . 'WhatsApp</a></div></div>';
        }
    }
    if (!$all) {
        echo petgo_empty('staff', petgo_t('cl_none'));
        return;
    }
    echo '<label class="pgcl-search">' . petgo_icon('user') . '<input type="search" placeholder="' . esc_attr(petgo_t('cl_search')) . '" data-cl-search></label>';
    foreach ($all as $u) {
        $pets  = petgo_cl_pets($u->ID);
        $ph    = (string) get_user_meta($u->ID, 'petgo_phone', true);
        $cid   = petgo_cl_chat($u->ID, false);
        if ($cid) {
            /* сотрудник, добавленный позже, тоже видит чат клиента */
            petgo_mark_read($cid, get_current_user_id(), 0);
        }
        $names = wp_list_pluck($pets, 'post_title');
        $off   = (bool) get_user_meta($u->ID, 'petgo_disabled', true);
        echo '<details class="pgc-ord pgcl" data-k="cl' . (int) $u->ID . '" data-q="' . esc_attr(mb_strtolower($u->display_name . ' ' . $u->user_login . ' ' . $ph . ' ' . petgo_cl_code($u->ID) . ' ' . implode(' ', $names))) . '"><summary>' . petgo_av(petgo_user_avatar_url($u->ID), $u->display_name, 'md') . '<div class="pgc-sm"><div class="pgc-l1"><b>' . esc_html($u->display_name) . '</b><span class="mu">@' . esc_html($u->user_login) . '</span>' . ($off ? '<span class="pgc-badge cancelled">' . esc_html(petgo_t('st_off')) . '</span>' : '') . '</div><div class="pgc-l2">' . esc_html(petgo_cl_code($u->ID) . ' · ' . $ph . ($names ? ' · ' . implode(', ', $names) : '')) . '</div></div></summary><div class="pgc-body">';
        echo '<div class="pgc-kv"><span>ID</span><b>' . esc_html(petgo_cl_code($u->ID)) . '</b></div><div class="pgc-kv"><span>' . esc_html(petgo_t('login_s')) . '</span><b>' . esc_html($u->user_login) . '</b></div>';
        if ($ph !== '') {
            echo '<div class="pgc-kv"><span>' . esc_html(petgo_t('phone')) . '</span><a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $ph)) . '">' . esc_html($ph) . '</a></div>';
        }
        echo '<div class="pgc-kv"><span>' . esc_html(petgo_t('cl_since')) . '</span><b>' . esc_html(wp_date('d.m.Y', strtotime($u->user_registered . ' UTC'), petgo_tz())) . '</b></div>';
        if ($pets) {
            echo '<div class="pgcl-pets">';
            foreach ($pets as $p) {
                echo '<span>' . petgo_av(petgo_pet_avatar_url($p->ID), $p->post_title, 'sm') . esc_html($p->post_title) . ' <small>' . esc_html(petgo_pet_code($p->ID)) . '</small></span>';
            }
            echo '</div>';
        }
        echo '<div class="pgc-btns">';
        echo '<a class="pgc-btn sec" href="' . esc_url(petgo_page_url(array('pg' => 'clients', 'c' => $cid ? $cid : petgo_cl_chat($u->ID)))) . '">' . petgo_ic('msg') . esc_html(petgo_t('c_write')) . '</a>';
        echo petgo_btn_form('cl_pass', array('uid' => $u->ID), petgo_t('cl_pw_reset'), 'pgc-btn sec', petgo_t('cl_pw_q'));
        echo '</div>';
        /* рекомендация от менеджера */
        echo petgo_form_open('cl_rec', 'class="pgcl-rec"') . '<input type="hidden" name="uid" value="' . (int) $u->ID . '"><label class="pgc-f"><span>' . esc_html(petgo_t('cl_rec')) . '</span><textarea name="rec" rows="2" maxlength="1000" placeholder="' . esc_attr(petgo_t('cl_rec_ph')) . '">' . esc_textarea((string) get_user_meta($u->ID, 'petgo_cl_rec', true)) . '</textarea></label><button type="submit" class="pgc-btn sec sm">' . esc_html(petgo_t('save')) . '</button></form>';
        /* привязать питомца */
        $free = array();
        foreach (petgo_pets() as $p) {
            if (!(int) get_post_meta($p->ID, '_client', true)) {
                $free[] = $p;
            }
        }
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

function petgo_page_board() {
    echo '<p class="mu pgcl-hint">' . esc_html(petgo_t('cl_board_hint')) . '</p>';
    $b  = petgo_form_open('board_add', 'class="pgc-card pgcl-badd"') . '<div class="pgf-seg pgcl-btype">';
    foreach (petgo_board_types() as $k => $l) {
        $b .= '<label><input type="radio" name="type" value="' . esc_attr($k) . '"' . checked($k, 'group', false) . '><span>' . esc_html(petgo_t($l)) . '</span></label>';
    }
    $b .= '</div><div class="pgf-list"><label class="pgf-row"><span>' . esc_html(petgo_t('cl_b_title')) . '</span><input type="text" name="title" maxlength="120" required></label><label class="pgf-row"><span>' . esc_html(petgo_t('cl_b_when')) . '</span><input type="text" name="when" maxlength="60" placeholder="' . esc_attr(petgo_t('cl_b_when_ph')) . '"></label>';
    $b .= '<label class="pgf-row"><span>' . esc_html(petgo_t('cl_b_place')) . '</span><input type="text" name="place" maxlength="120"></label><label class="pgf-row pgf-ra"><span>' . esc_html(petgo_t('cl_b_text')) . '</span><textarea name="text" rows="3" maxlength="800"></textarea></label></div><button class="pgc-btn wide" type="submit">' . esc_html(petgo_t('cl_b_add')) . '</button></form>';
    echo $b;
    foreach (array_reverse(petgo_board()) as $x) {
        $n = 0;
        if ($x['type'] === 'group') {
            global $wpdb;
            $n = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key=%s", 'petgo_join_' . $x['id']));
        }
        echo '<div class="pgc-card pgcl-bi"><div class="pgc-rqh"><span class="pgc-tag ' . ($x['type'] === 'promo' ? 'warn' : ($x['type'] === 'group' ? 'ok' : 'info')) . '">' . esc_html(petgo_t(petgo_board_types()[$x['type']])) . '</span><b>' . esc_html($x['title']) . '</b>' . petgo_btn_form('board_del', array('id' => $x['id']), '×', 'pgc-btn sec sm', petgo_t('q_delete_inv')) . '</div>';
        echo '<p class="mu">' . esc_html(trim($x['when'] . ($x['place'] !== '' ? ' · ' . $x['place'] : ''), ' ·')) . '</p>' . ($x['text'] !== '' ? '<p>' . nl2br(esc_html($x['text'])) . '</p>' : '') . ($x['type'] === 'group' ? '<p class="pgcl-joined">' . esc_html(petgo_t('cl_b_joined') . ': ' . $n) . '</p>' : '') . '</div>';
    }
}

foreach (array('cl_pass', 'cl_rec', 'cl_link', 'board_add', 'board_del') as $petgo_a) {
    add_action('admin_post_petgo_' . $petgo_a, 'petgo_h_' . $petgo_a);
}

function petgo_cl_obj($uid) {
    $u = get_userdata((int) $uid);
    return petgo_cl_is($u) ? $u : false;
}

/* менеджер ставит клиенту новый пароль (клиент забыл) — пароль показывается один раз */
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
    $t = isset(petgo_board_types()[$t]) ? $t : 'promo';
    $title = mb_substr(sanitize_text_field((string) petgo_p('title')), 0, 120);
    if ($title === '') {
        petgo_fail('err_fields', 'clients');
    }
    $a   = petgo_board();
    $a[] = array('id' => wp_generate_password(8, false), 'type' => $t, 'title' => $title, 'text' => mb_substr(sanitize_textarea_field((string) petgo_p('text')), 0, 800),
        'when' => mb_substr(sanitize_text_field((string) petgo_p('when')), 0, 60), 'place' => mb_substr(sanitize_text_field((string) petgo_p('place')), 0, 120), 't' => time());
    update_option('petgo_board', array_slice($a, -40), false);
    petgo_flash(petgo_t('ok_saved'));
    petgo_back('clients', array('cv' => 'board'));
}

function petgo_h_board_del() {
    petgo_guard('office');
    $id = (string) petgo_p('id');
    update_option('petgo_board', array_values(array_filter(petgo_board(), function ($b) use ($id) {
        return $b['id'] !== $id;
    })), false);
    petgo_flash(petgo_t('ok_deleted'));
    petgo_back('clients', array('cv' => 'board'));
}

add_filter('petgo_nav_badge', function ($n, $k) {
    return $n;
}, 10, 2);

/* ============================================================
   ПОМОЩНИК (ИИ) ЗНАЕТ КЛИЕНТА: «PetGo ИИ» получает подписанную метку и короткую справку о клиенте
   ============================================================ */
add_filter('pga_client_token', function ($t) {
    $uid = petgo_cl_uid();
    if (!$uid) {
        return $t;
    }
    $ts = time();
    return $uid . '.' . $ts . '.' . substr(hash_hmac('sha256', 'pga|' . $uid . '|' . $ts, wp_salt('auth')), 0, 32);
});

function petgo_cl_token_uid($tok) {
    $p = explode('.', (string) $tok);
    if (count($p) !== 3 || !ctype_digit($p[0]) || !ctype_digit($p[1]) || time() - (int) $p[1] > 7 * DAY_IN_SECONDS) {
        return 0;
    }
    if (!hash_equals(substr(hash_hmac('sha256', 'pga|' . $p[0] . '|' . $p[1], wp_salt('auth')), 0, 32), $p[2])) {
        return 0;
    }
    return petgo_cl_obj((int) $p[0]) ? (int) $p[0] : 0;
}

add_filter('pga_client_context', function ($ctx, $tok) {
    $uid = petgo_cl_token_uid($tok);
    if (!$uid) {
        return $ctx;
    }
    $u = get_userdata($uid);
    $o = "Клиент вошёл в свой кабинет: {$u->display_name} (ID " . petgo_cl_code($uid) . "). Обращайся по имени, помогай с его питомцами, отслеживанием прогулок и записью.\n";
    foreach (petgo_cl_pets($uid) as $p) {
        $s   = petgo_cl_pet_state($p->ID);
        $bits = array();
        foreach (array('species' => 'вид', 'breed' => 'порода', 'age' => 'возраст', 'weight' => 'вес', 'health' => 'здоровье', 'food' => 'питание', 'fears' => 'страхи') as $k => $l) {
            $v = (string) get_post_meta($p->ID, '_pf_' . $k, true);
            if ($v !== '') {
                $bits[] = $l . ': ' . petgo_val($k, $v, 'select');
            }
        }
        $o .= '- Питомец «' . $p->post_title . '» (' . implode('; ', $bits) . '). Сейчас: ' . $s['text'] . ".\n";
    }
    $ol = petgo_cl_orders($uid, 8);
    if ($ol) {
        $o .= "Последние и ближайшие заказы:\n";
        foreach ($ol as $x) {
            $o .= '- ' . wp_date('d.m.Y H:i', (int) petgo_o($x->ID, 'ts'), petgo_tz()) . ', ' . get_the_title((int) petgo_o($x->ID, 'pet')) . ', статус: ' . petgo_o($x->ID, 'status') . (petgo_nanny_label($x->ID) !== '' ? ', няня ' . petgo_nanny_label($x->ID) : '') . "\n";
        }
    }
    $o .= "В кабинете есть разделы: Питомцы, Карта (где питомец сейчас и где гулял, фото на карте), Прогулки (история и отзывы), Для вас (рекомендации, групповые прогулки, акции), Профиль. Кнопка «Написать менеджеру» — вверху справа.\n";
    return $ctx . $o;
}, 10, 2);

add_filter('pga_greeting', function ($g) {
    $uid = petgo_cl_uid();
    if (!$uid) {
        return $g;
    }
    $u    = get_userdata($uid);
    $f    = explode(' ', trim($u->display_name));
    $pets = wp_list_pluck(petgo_cl_pets($uid), 'post_title');
    return $pets ? petgo_site_lf('Здравствуйте, %1$s! Я помощник PetGo 🐾 Подскажу про %2$s: прогулки, питание, уход. Чем помочь?', $f[0], implode(', ', $pets)) : petgo_site_lf('Здравствуйте, %s! Я помощник PetGo 🐾 Помогу с заказом няни и уходом за питомцем.', $f[0]);
});

add_filter('pga_chips', function ($c) {
    if (!petgo_cl_uid()) {
        return $c;
    }
    return array(petgo_site_l('Когда следующая прогулка?'), petgo_site_l('Как прошла последняя прогулка?'), petgo_site_l('Совет по питанию'), petgo_site_l('Хочу на групповую прогулку'));
});
