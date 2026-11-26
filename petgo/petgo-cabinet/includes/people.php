<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   34.3: КЛИЕНТЫ И ОБЩЕЕ (в базовом плагине — нужно и приложению, и сайту, и кабинету клиента)
   роль «Клиент», ID и никнеймы, питомцы клиента, чат клиента с менеджером, сообщения PetGo клиенту,
   отчёт няни (настроение, что сделано), единый набор значков
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
    /* 40.0: статусы заказов (sys, loc, rv) — в «Уведомлениях», не в счётчике чата */
    return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . petgo_tb('msgs') . " WHERE chat_id=%d AND id>%d AND user_id<>%d AND kind NOT IN ('sys','loc','rv')", $cid, $lr, $uid));
}

/* сообщение в чат клиента от имени клиента (или системы) + уведомление сотрудникам */
function petgo_cl_say($uid, $body, $photo = '') {
    global $wpdb;
    $cid = petgo_cl_chat($uid);
    if (!$cid) {
        return 0;
    }
    /* 34.6: то же сообщение за 20 секунд второй раз не записываем (двойное нажатие, повтор запроса на плохой связи) */
    if ($photo === '' && $body !== '') {
        $dup = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . petgo_tb('msgs') . ' WHERE chat_id=%d AND user_id=%d AND body=%s AND ts>%d ORDER BY id DESC LIMIT 1', $cid, (int) $uid, $body, time() - 20));
        if ($dup) {
            return $dup;
        }
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

function petgo_cl_live_words() {
    return array(
        'walk'  => 'Гуляем', 'play' => 'Играем', 'eat' => 'Кушаем', 'water' => 'Пьём водичку', 'rest' => 'Отдыхаем', 'sniff' => 'Изучаем всё вокруг',
        'potty' => 'Сделали дела', 'home' => 'Возвращаемся домой', 'pet' => 'Обнимашки',
    );
}

function petgo_cl_obj($uid) {
    $u = get_userdata((int) $uid);
    return petgo_cl_is($u) ? $u : false;
}

function petgo_cl_icon($n, $cls = 'pg-i') {
    static $P = array(
        'paw'    => '<path d="M12 12.3c-2.7 0-4.8 2.1-4.8 4 0 1.7 1.4 2.4 2.8 2 .9-.3 1.4-.6 2-.6s1.1.3 2 .6c1.4.4 2.8-.3 2.8-2 0-1.9-2.1-4-4.8-4z"/><ellipse cx="6.6" cy="10" rx="1.5" ry="2"/><ellipse cx="9.9" cy="6.5" rx="1.5" ry="2.1"/><ellipse cx="14.1" cy="6.5" rx="1.5" ry="2.1"/><ellipse cx="17.4" cy="10" rx="1.5" ry="2"/>',
        'map'    => '<path d="M9 4.5 3.5 6.8v12.7L9 17.2l6 2.3 5.5-2.3V4.5L15 6.8z"/><path d="M9 4.5v12.7M15 6.8v12.7"/>',
        'route'  => '<circle cx="6" cy="18.5" r="2"/><circle cx="18" cy="5.5" r="2"/><path d="M8 18.5h8a3.3 3.3 0 0 0 0-6.6H8a3.3 3.3 0 0 1 0-6.6h8"/>',
        'gift'   => '<rect x="4" y="9" width="16" height="11" rx="2"/><path d="M4 13h16M12 9v11"/><path d="M12 9c-1.4-3.3-5.2-3.8-5.2-1.4S9.6 9 12 9zm0 0c1.4-3.3 5.2-3.8 5.2-1.4S14.4 9 12 9z"/>',
        'user'   => '<circle cx="12" cy="8.2" r="3.7"/><path d="M4.5 20c0-4 3.3-6.3 7.5-6.3s7.5 2.3 7.5 6.3"/>',
        'chat'   => '<path d="M12 4.5c4.4 0 8 2.9 8 6.6s-3.6 6.6-8 6.6c-.9 0-1.7-.1-2.5-.3L5.5 19.5l.9-3.3C4.9 15 4 13.2 4 11.1c0-3.7 3.6-6.6 8-6.6z"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'camera' => '<path d="M4 8.5A1.5 1.5 0 0 1 5.5 7H8l1.5-2h5L16 7h2.5A1.5 1.5 0 0 1 20 8.5v9a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 17.5z"/><circle cx="12" cy="13" r="3.3"/>',
        'edit'   => '<path d="M5 19h3.5L19 8.5 15.5 5 5 15.5z"/><path d="M13.5 7l3.5 3.5"/>',
        'star'   => '<path d="M12 3.8l2.5 5.2 5.7.8-4.1 4 1 5.7L12 16.8l-5.1 2.7 1-5.7-4.1-4 5.7-.8z"/>',
        'pin'    => '<path d="M12 21s-6.5-5.7-6.5-11a6.5 6.5 0 0 1 13 0c0 5.3-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>',
        'clock'  => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'logout' => '<path d="M9 20H5.5A1.5 1.5 0 0 1 4 18.5v-13A1.5 1.5 0 0 1 5.5 4H9"/><path d="M15.5 16.5 20 12l-4.5-4.5M20 12H9"/>',
        'key'    => '<circle cx="8" cy="15" r="4"/><path d="M11 12l8.5-8.5M16 7l2.5 2.5M13.5 9.5 15 11"/>',
        'x'      => '<path d="M6 6l12 12M18 6 6 18"/>',
        'send'   => '<path d="M4.5 12 19.5 4.5 15 19.5l-3-6z"/><path d="M12 13.5 19.5 4.5"/>',
        'left'   => '<path d="M15 5l-7 7 7 7"/>',
        'right'  => '<path d="M9 5l7 7-7 7"/>',
        'clip'   => '<path d="M19.5 12.5 12 20a5.2 5.2 0 0 1-7.4-7.4l8-8a3.5 3.5 0 0 1 5 5l-7.9 7.9a1.7 1.7 0 0 1-2.4-2.4l7.3-7.3"/>',
        'eye'    => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="2.8"/>',
        'locate' => '<circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="7.5"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2"/>',
        'check'  => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'home'   => '<path d="M4 10.8 12 4l8 6.8V19a1 1 0 0 1-1 1h-4.5v-5.5h-5V20H5a1 1 0 0 1-1-1z"/>',
        'sun'    => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.6 4.6l1.4 1.4M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4 6 18M18 6l1.4-1.4"/>',
        'moon'   => '<path d="M19.5 14.5A8 8 0 0 1 9.5 4.5a8 8 0 1 0 10 10z"/>',
        'auto'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 3.5v17a8.5 8.5 0 0 0 0-17z" fill="currentColor" stroke="none"/>',
        'book'   => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H10a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H4z"/><path d="M20 5.5A1.5 1.5 0 0 0 18.5 4H14a2 2 0 0 0-2 2v14a2 2 0 0 1 2-2h6z"/>',
        'tag'    => '<path d="M3.5 12.2V4.5a1 1 0 0 1 1-1h7.7l8.3 8.3a1.5 1.5 0 0 1 0 2.1l-6.1 6.1a1.5 1.5 0 0 1-2.1 0z"/><circle cx="8" cy="8" r="1.5"/>',
        'group'  => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c0-3.2 2.5-5.3 5.5-5.3s5.5 2.1 5.5 5.3"/><circle cx="17" cy="9" r="2.4"/><path d="M16.5 14.3c2.4 0 4 1.7 4 4.3"/>',
        'id'     => '<rect x="3.5" y="5" width="17" height="14" rx="2.5"/><circle cx="9" cy="11" r="2.2"/><path d="M5.8 16c.6-1.5 1.8-2.3 3.2-2.3s2.6.8 3.2 2.3M14.5 10H18M14.5 13.5H18"/>',
        'link'   => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
        'copy'   => '<rect x="8" y="8" width="12" height="12" rx="2.5"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
        'share'  => '<path d="M12 3v12M7.5 7.5 12 3l4.5 4.5"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/>',
        'wa'     => '<path d="M12 4.5c4.4 0 8 2.9 8 6.6s-3.6 6.6-8 6.6c-.9 0-1.7-.1-2.5-.3L5.5 19.5l.9-3.3C4.9 15 4 13.2 4 11.1c0-3.7 3.6-6.6 8-6.6z"/>',
        'info'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.8h.01"/>',
        'health' => '<path d="M12 20s-7-4.4-7-10a4.2 4.2 0 0 1 7-3 4.2 4.2 0 0 1 7 3c0 5.6-7 10-7 10z"/><path d="M9.5 11.5h5M12 9v5"/>',
        'food'   => '<path d="M4 11h16a8 8 0 0 1-16 0z"/><path d="M8 7c0-1.6 1-1.6 1-3.2M12 7c0-1.6 1-1.6 1-3.2M16 7c0-1.6 1-1.6 1-3.2"/>',
        'behav'  => '<circle cx="12" cy="12" r="8.5"/><path d="M8.5 14c1 1.5 2.2 2 3.5 2s2.5-.5 3.5-2M9 9.5h.01M15 9.5h.01"/>',
        'note'   => '<path d="M7 3.5h7.5l4 4v13H7z"/><path d="M14 3.5V8h4.5M10 13h6M10 16.5h6"/>',
        'photo'  => '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="9" cy="10" r="1.8"/><path d="m20.5 15.5-4-4-9 8"/>',
        'phone'  => '<path d="M6.5 4h3l1.5 4-2 1.5a11 11 0 0 0 5.5 5.5l1.5-2 4 1.5v3a1.5 1.5 0 0 1-1.5 1.5C11.6 19 5 12.4 5 5.5A1.5 1.5 0 0 1 6.5 4z"/>',
    );
    return isset($P[$n]) ? '<svg class="' . esc_attr($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $P[$n] . '</svg>' : '';
}

/* ---------- значки (тот же набор, что в кабинете) ---------- */
function petgo_cp_icon($n, $cls = 'pg-i') {
    static $X = array(
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>',
        'trash'    => '<path d="M4 6.5h16M9.5 6.5V4.5h5v2M6 6.5l1 13h10l1-13"/>',
        'shield'   => '<path d="M12 3.5l7 2.5v5.5c0 4.5-3 7.7-7 9-4-1.3-7-4.5-7-9V6z"/><path d="M9 12l2.2 2.2L15 10.5"/>',
        'm1'       => '<circle cx="12" cy="12" r="8.5"/><path d="M8.5 16.2c1-1.3 2.2-1.9 3.5-1.9s2.5.6 3.5 1.9M9 10h.01M15 10h.01"/>',
        'm2'       => '<circle cx="12" cy="12" r="8.5"/><path d="M9 15.5h6M9 10h.01M15 10h.01"/>',
        'm3'       => '<circle cx="12" cy="12" r="8.5"/><path d="M9 14.8c.9.8 1.9 1.2 3 1.2s2.1-.4 3-1.2M9 10h.01M15 10h.01"/>',
        'm4'       => '<circle cx="12" cy="12" r="8.5"/><path d="M8.3 13.5c.9 1.8 2.2 2.7 3.7 2.7s2.8-.9 3.7-2.7M9 10h.01M15 10h.01"/>',
        'm5'       => '<circle cx="12" cy="12" r="8.5"/><path d="M8 13h8c-.5 2.2-2 3.5-4 3.5S8.5 15.2 8 13zM8.5 9.8c.5-.7 1.5-.7 2 0M13.5 9.8c.5-.7 1.5-.7 2 0"/>',
    );
    if (isset($X[$n])) {
        return '<svg class="' . esc_attr($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $X[$n] . '</svg>';
    }
    return petgo_cl_icon($n, $cls);
}

function petgo_rep_moods() {
    return array(1 => 'Грустил', 2 => 'Скучал', 3 => 'Спокоен', 4 => 'Доволен', 5 => 'Счастлив');
}

function petgo_rep_did($svc) {
    return $svc === 'walk'
        ? array('potty' => 'Сделал дела', 'water' => 'Попил воды', 'play' => 'Поиграли', 'paws' => 'Помыли лапы')
        : array('feed' => 'Покормила', 'water' => 'Свежая вода', 'litter' => 'Лоток / уборка', 'play' => 'Поиграли', 'walk' => 'Погуляли', 'meds' => 'Дала лекарство');
}

/* поля отчёта в форме завершения у няни */
function petgo_rep_fields($oid) {
    $svc = petgo_o($oid, 'service');
    $h   = '<div class="pgr"><div class="pgr-h">' . esc_html(petgo_t('rep_t')) . '</div><div class="pgr-mood" role="radiogroup">';
    foreach (petgo_rep_moods() as $k => $t) {
        $h .= '<label><input type="radio" name="mood" value="' . $k . '"' . ($k === 4 ? ' checked' : '') . '><span>' . petgo_cp_icon('m' . $k, 'pgw-i') . '<small>' . esc_html(petgo_t('mood_' . $k)) . '</small></span></label>';
    }
    $h .= '</div><div class="pgr-did">';
    foreach (petgo_rep_did($svc) as $k => $t) {
        $h .= '<label><input type="checkbox" name="did[]" value="' . esc_attr($k) . '"><span>' . esc_html(petgo_t('did_' . $k)) . '</span></label>';
    }
    return $h . '</div><textarea name="rnote" rows="2" maxlength="600" placeholder="' . esc_attr(petgo_t('rep_note')) . '"></textarea></div>';
}

function petgo_rep_save($oid) {
    $mood = (int) petgo_p('mood');
    $did  = array();
    $all  = petgo_rep_did(petgo_o($oid, 'service'));
    foreach ((array) petgo_p('did', array()) as $d) {
        $d = sanitize_key((string) $d);
        if (isset($all[$d])) {
            $did[] = $d;
        }
    }
    $note = mb_substr(sanitize_textarea_field((string) petgo_p('rnote')), 0, 600);
    if ($mood >= 1 && $mood <= 5 || $did || $note !== '') {
        update_post_meta($oid, '_report', array('mood' => max(0, min(5, $mood)), 'did' => $did, 'note' => $note));
    }
}

function petgo_rep_text($oid) {
    $r = get_post_meta($oid, '_report', true);
    if (!is_array($r)) {
        return '';
    }
    $M   = petgo_rep_moods();
    $all = petgo_rep_did(petgo_o($oid, 'service'));
    $p   = array();
    if (!empty($r['mood']) && isset($M[(int) $r['mood']])) {
        $p[] = 'настроение: ' . mb_strtolower($M[(int) $r['mood']]);
    }
    foreach ((array) $r['did'] as $d) {
        if (isset($all[$d])) {
            $p[] = mb_strtolower($all[$d]);
        }
    }
    return implode(', ', $p) . (!empty($r['note']) ? ". «" . $r['note'] . "»" : '');
}

function petgo_cl_sys($uid, $body, $kind = 'sys', $subj = 0) {
    global $wpdb;
    $cid = petgo_cl_chat($uid);
    if (!$cid) {
        return 0;
    }
    /* 34.6: одно и то же уведомление (например, «Оцените няню» по одному заказу) — только один раз */
    $dup = $subj
        ? (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . petgo_tb('msgs') . ' WHERE chat_id=%d AND user_id=0 AND kind=%s AND subject_id=%d LIMIT 1', $cid, $kind, (int) $subj))
        : (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . petgo_tb('msgs') . ' WHERE chat_id=%d AND user_id=0 AND body=%s AND ts>%d LIMIT 1', $cid, $body, time() - 120));
    if ($dup) {
        return $dup;
    }
    $wpdb->insert(petgo_tb('msgs'), array('chat_id' => $cid, 'user_id' => 0, 'subject_id' => (int) $subj, 'kind' => $kind, 'body' => $body, 'photo' => '', 'ts' => time()), array('%d', '%d', '%d', '%s', '%s', '%s', '%d'));
    $mid = (int) $wpdb->insert_id;
    petgo_bump();
    /* 40.0: уведомление клиенту на телефон (если он их включил) — делает «PetGo Клиент» */
    do_action('petgo_cl_sys_sent', (int) $uid, (string) $body, (string) $kind, (int) $subj, $mid);
    return $mid;
}
