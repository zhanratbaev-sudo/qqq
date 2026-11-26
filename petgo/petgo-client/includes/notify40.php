<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   40.0 УВЕДОМЛЕНИЯ
   Клиенту:
   - Статусы заказа («Заявка принята», «Подтверждено», «Няня пришла», «Прогулка началась», «Питомец дома»,
     «Оцените няню») больше не пишутся в чат — они в «Уведомлениях» (колокольчик) и приходят на телефон.
     Чат — только разговор с помощником (ИИ) и менеджером.
   - Уведомления на телефон: при открытии приложения клиента вежливо спрашиваем «Включить уведомления?»
     («Включить» / «Не сейчас», снова — не раньше чем через неделю). В браузере и в приложении с экрана «Домой» —
     web push; в приложении My PetGo из магазинов — через Firebase (ключ вставляет владелец в Настройках).
   Сотрудникам: о каждом действии клиента — уведомление и запись в ленту «Уведомления» (новый клиент, питомец,
   данные и адрес, пароль, договор, отзыв, удаление кабинета; заказы, «ждёт менеджера» — как и раньше).
   ============================================================ */

/* ---------- сотрудникам: действия клиентов ---------- */
function petgo_cl_staff_note($title, $body, $url = '') {
    if (function_exists('petgo_push_notify') && function_exists('petgo_push_users')) {
        petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), $title, $body, $url !== '' ? $url : petgo_page_url(array('pg' => 'clients')));
    }
}
function petgo_cl_who($uid) {
    $u  = get_userdata((int) $uid);
    $ph = function_exists('petgo_site_phone_fmt') ? petgo_site_phone_fmt((string) get_user_meta((int) $uid, 'petgo_phone', true)) : '';
    return trim(($u ? $u->display_name : '') . ($ph !== '' ? ' · ' . $ph : ''));
}
add_action('petgo_event', function ($name, $d) {
    $uid = isset($d['user']) ? (int) $d['user'] : 0;
    if (!$uid) {
        return;
    }
    $cid = function_exists('petgo_cl_chat') ? petgo_cl_chat($uid, false) : 0;
    $url = petgo_page_url($cid ? array('pg' => 'clients', 'c' => $cid) : array('pg' => 'clients'));
    if ($name === 'sign_up') {
        petgo_cl_staff_note('Новый клиент', petgo_cl_who($uid), $url);
    } elseif ($name === 'contract_signed') {
        petgo_cl_staff_note('Договор подписан', petgo_cl_who($uid) . (!empty($d['no']) ? ' · № ' . $d['no'] : ''), $url);
    } elseif ($name === 'pet_saved') {
        $pid = isset($d['pet']) ? (int) $d['pet'] : 0;
        petgo_cl_staff_note(!empty($d['new']) ? 'Клиент добавил питомца' : 'Клиент изменил анкету питомца', ($pid ? get_the_title($pid) . ' · ' : '') . petgo_cl_who($uid), $pid ? petgo_page_url(array('pg' => 'pets', 'pet' => $pid)) : $url);
    } elseif ($name === 'cl_profile') {
        $a = trim((string) get_user_meta($uid, 'petgo_addr', true));
        petgo_cl_staff_note('Клиент изменил данные', petgo_cl_who($uid) . ($a !== '' ? ' · ' . $a : ''), $url);
    } elseif ($name === 'cl_pass') {
        petgo_cl_staff_note('Клиент сменил пароль', petgo_cl_who($uid), $url);
    }
}, 20, 2);

/* ---------- клиенту: статусы заказов — в «Уведомлениях» и на телефон ---------- */
/* «Заявка принята: выгул для Бублика…» → заголовок «Заявка принята», текст — остальное */
function petgo_cl_nt_split($b) {
    $b = trim(html_entity_decode(wp_strip_all_tags((string) $b), ENT_QUOTES, 'UTF-8'));
    if (preg_match('/^(.{3,48}?)[:.]\s+(.+)$/us', $b, $m)) {
        return array(trim($m[1]), trim($m[2]));
    }
    return array($b, '');
}
function petgo_cl_nt_icon($kind, $b) {
    if ($kind === 'rv') {
        return 'star';
    }
    $t = mb_strtolower((string) $b);
    foreach (array('cal' => array('заявка принята', 'заявка'), 'check' => array('подтвержден'), 'walk' => array('прогулка началась', 'гуляем'), 'home' => array('пришла', 'на месте', 'визит начался'), 'done' => array('завершён', 'дома'), 'x' => array('отменён', 'отменен')) as $ic => $keys) {
        foreach ($keys as $k) {
            if (mb_strpos($t, $k) !== false) {
                return $ic;
            }
        }
    }
    return $kind === 'loc' ? 'pin' : 'bell';
}
add_action('petgo_cl_sys_sent', function ($uid, $body, $kind, $subj, $mid) {
    if (!function_exists('petgo_push_notify') || !in_array($kind, array('sys', 'loc', 'rv'), true)) {
        return;
    }
    list($t, $x) = petgo_cl_nt_split($body);
    petgo_push_notify(array((int) $uid), $t, $x !== '' ? $x : 'PetGo', petgo_site_url('account') . '#home', 'n' . (int) $mid);
}, 10, 5);

/* список уведомлений клиента: только статусы (не ответы помощника) */
function petgo_cl_notifs40($uid, $n = 60) {
    global $wpdb;
    $cid = function_exists('petgo_cl_chat') ? petgo_cl_chat($uid, false) : 0;
    if (!$cid) {
        return array();
    }
    $o  = array();
    $tz = petgo_tz();
    foreach ((array) $wpdb->get_results($wpdb->prepare('SELECT id, kind, body, subject_id, ts FROM ' . petgo_tb('msgs') . " WHERE chat_id=%d AND user_id=0 AND kind IN ('sys','loc','rv') ORDER BY id DESC LIMIT %d", $cid, (int) $n)) as $r) {
        list($t, $x) = petgo_cl_nt_split($r->body);
        $o[] = array(
            'id' => (int) $r->id, 'k' => (string) $r->kind, 't' => $t, 'x' => $x, 'ic' => petgo_cl_nt_icon((string) $r->kind, $r->body),
            's' => (int) $r->subject_id, 'pid' => $r->kind === 'loc' ? (int) petgo_o((int) $r->subject_id, 'pet') : 0,
            'rv' => ($r->kind === 'rv' && (int) $r->subject_id && function_exists('petgo_rv_by_order') && petgo_rv_by_order((int) $r->subject_id)) ? (int) petgo_rv_by_order((int) $r->subject_id)->rating : 0,
            'dl' => wp_date('d.m.Y', (int) $r->ts, $tz), 'tm' => wp_date('H:i', (int) $r->ts, $tz),
        );
    }
    return $o;
}

/* ---------- подписка на уведомления ---------- */
add_action('petgo_cl_op', function ($op, $uid) {
    if ($op === 'push_sub') {
        $s  = json_decode((string) petgo_p('s'), true);
        $ep = (is_array($s) && !empty($s['endpoint']) && is_string($s['endpoint'])) ? $s['endpoint'] : '';
        if ($ep === '' || strlen($ep) > 600 || !function_exists('petgo_push_host_ok') || !petgo_push_host_ok($ep)) {
            petgo_cl_json(false);
        }
        $subs = get_user_meta($uid, 'petgo_push', true);
        $subs = array_values(array_filter(is_array($subs) ? $subs : array(), function ($x) use ($ep) {
            return isset($x['e']) && $x['e'] !== $ep;
        }));
        if (petgo_p('do') !== 'unsub') {
            $subs[] = array('e' => $ep, 't' => time());
        }
        update_user_meta($uid, 'petgo_push', array_slice($subs, -5));
        petgo_cl_json(true);
    }
    if ($op === 'push_native') {
        $tok = preg_replace('/[^A-Za-z0-9:_\-\.]/', '', (string) petgo_p('token'));
        if ($tok === '' || strlen($tok) > 400) {
            petgo_cl_json(false);
        }
        $all = get_user_meta($uid, 'petgo_fcm', true);
        $all = array_values(array_diff(is_array($all) ? $all : array(), array($tok)));
        if (petgo_p('do') !== 'unsub') {
            $all[] = $tok;
        }
        update_user_meta($uid, 'petgo_fcm', array_slice($all, -5));
        petgo_cl_json(true);
    }
    if ($op === 'notifs40') {
        petgo_cl_json(true, array('items' => petgo_cl_notifs40($uid)));
    }
}, 10, 2);

/* ---------- приложение My PetGo (Android, iOS): уведомления через Firebase Cloud Messaging ---------- */
function petgo_fcm_sa() {
    $j = json_decode((string) get_option('petgo_fcm_sa', ''), true);
    return (is_array($j) && !empty($j['project_id']) && !empty($j['client_email']) && !empty($j['private_key'])) ? $j : null;
}
function petgo_fcm_token() {
    $sa = petgo_fcm_sa();
    if (!$sa || !function_exists('openssl_sign')) {
        return '';
    }
    $t = get_transient('petgo_fcm_at');
    if (is_string($t) && $t !== '') {
        return $t;
    }
    $b64 = function ($s) {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    };
    $aud = !empty($sa['token_uri']) ? $sa['token_uri'] : 'https://oauth2.googleapis.com/token';
    $in  = $b64(wp_json_encode(array('alg' => 'RS256', 'typ' => 'JWT'))) . '.' . $b64(wp_json_encode(array('iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => $aud, 'iat' => time(), 'exp' => time() + 3600)));
    if (!@openssl_sign($in, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
        return '';
    }
    $r = wp_remote_post($aud, array('timeout' => 8, 'body' => array('grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $in . '.' . $b64($sig))));
    $j = is_wp_error($r) ? null : json_decode((string) wp_remote_retrieve_body($r), true);
    if (empty($j['access_token'])) {
        return '';
    }
    set_transient('petgo_fcm_at', (string) $j['access_token'], 50 * MINUTE_IN_SECONDS);
    return (string) $j['access_token'];
}
function petgo_fcm_send($uid, $title, $body, $url) {
    $toks = get_user_meta((int) $uid, 'petgo_fcm', true);
    if (!is_array($toks) || !$toks) {
        return 0;
    }
    $sa = petgo_fcm_sa();
    $at = petgo_fcm_token();
    if (!$sa || $at === '') {
        return 0;
    }
    $ok   = 0;
    $keep = array();
    foreach ($toks as $tok) {
        $msg = array('message' => array(
            'token' => $tok,
            'notification' => array('title' => mb_substr((string) $title, 0, 80), 'body' => mb_substr((string) $body, 0, 200)),
            'data' => array('u' => (string) $url),
            'android' => array('priority' => 'high', 'notification' => array('sound' => 'default')),
            'apns' => array('payload' => array('aps' => array('sound' => 'default'))),
        ));
        $r    = wp_remote_post('https://fcm.googleapis.com/v1/projects/' . rawurlencode($sa['project_id']) . '/messages:send', array('timeout' => 8, 'headers' => array('Authorization' => 'Bearer ' . $at, 'Content-Type' => 'application/json'), 'body' => wp_json_encode($msg)));
        $code = is_wp_error($r) ? 0 : (int) wp_remote_retrieve_response_code($r);
        if ($code === 200) {
            $ok++;
        }
        /* токен больше не действует (приложение удалили) — забываем */
        if ($code !== 404 && !($code === 400 && strpos((string) wp_remote_retrieve_body($r), 'UNREGISTERED') !== false)) {
            $keep[] = $tok;
        }
    }
    if (count($keep) !== count($toks)) {
        update_user_meta((int) $uid, 'petgo_fcm', $keep);
    }
    return $ok;
}
/* «PetGo Кабинет» сообщает о каждом уведомлении — клиентам с приложением My PetGo отправляем через Firebase */
add_action('petgo_push_native', function ($uids, $title, $body, $url) {
    if (!petgo_fcm_sa()) {
        return;
    }
    foreach (array_unique(array_map('intval', (array) $uids)) as $uid) {
        if (get_user_meta($uid, 'petgo_fcm', true)) {
            petgo_fcm_send($uid, $title, $body, $url);
        }
    }
}, 10, 4);

/* лист «Включите уведомления» — показывается при открытии приложения, пока клиент не решил */
add_filter('petgo_cl_sheets', function ($h, $uid) {
    $L  = 'petgo_site_l';
    $i  = function ($p) {
        return '<svg class="pg-i" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
    };
    $b  = '<div class="pgk-pa"><span class="pgk-pa-i">' . $i('<path d="M6 9a6 6 0 0 1 12 0c0 6 2 7.5 2 7.5H4S6 15 6 9"/><path d="M10 20a2 2 0 0 0 4 0"/>') . '<i></i></span>';
    $b .= '<h3>' . esc_html($L('Включите уведомления')) . '</h3><p>' . esc_html($L('Сообщим, когда менеджер подтвердит заказ, няня придёт, заберёт питомца и вернёт его домой.')) . '</p>';
    $b .= '<ul class="pgk-pa-l"><li><span class="ic-check">' . $i('<circle cx="12" cy="12" r="8.5"/><path d="M8.2 12.3l2.6 2.6 5-5.2"/>') . '</span>' . esc_html($L('Заказ подтверждён')) . '</li>';
    $b .= '<li><span class="ic-walk">' . $i('<circle cx="13" cy="4.5" r="1.8"/><path d="M10.5 21l2-6.5 2.5 2.5V21M8 12.5l2.5-5h3l2 3.5 2.5 1"/><path d="M12.5 14.5l-2-3.5"/>') . '</span>' . esc_html($L('Няня пришла и забрала питомца')) . '</li>';
    $b .= '<li><span class="ic-done">' . $i('<path d="M3.5 10.5 12 4l8.5 6.5"/><path d="M5.5 9.5V20h13V9.5"/><path d="M9 14.5l2 2 4-4"/>') . '</span>' . esc_html($L('Питомец дома — фото и отчёт')) . '</li></ul>';
    $b .= '<button type="button" class="pg-b pgk-btn pgk-btn-p pgk-go" data-k-push-on>' . esc_html($L('Включить')) . '</button><button type="button" class="pg-b pgk-link" data-k-push-later>' . esc_html($L('Не сейчас')) . '</button></div>';
    return $h . petgo_cl_sheet('push', '', $b, '', 'pgk-sheet-push');
}, 10, 2);

/* данные для скрипта приложения клиента */
add_filter('petgo_cl_cfg', function ($cfg, $uid = 0) {
    $v = function_exists('petgo_vapid') ? petgo_vapid() : false;
    $s = get_user_meta($uid, 'petgo_push', true);
    $cfg['push'] = array('key' => $v ? $v['pub'] : '', 'on' => is_array($s) && $s ? 1 : 0, 'native' => petgo_fcm_sa() ? 1 : 0, 'nt' => get_user_meta($uid, 'petgo_fcm', true) ? 1 : 0);
    foreach (array('Включите уведомления', 'Сообщим, когда менеджер подтвердит заказ, няня придёт, заберёт питомца и вернёт его домой.', 'Заказ подтверждён', 'Няня пришла и забрала питомца', 'Питомец дома — фото и отчёт', 'Включить', 'Не сейчас', 'Уведомления включены', 'Уведомления выключены в настройках телефона. Включите их для этого приложения.', 'Уведомления на телефон', 'Включены', 'Выключены', 'Где сейчас', 'Оценить няню', 'Спасибо за оценку', 'Сегодня', 'Вчера', 'Пока нет уведомлений', 'Здесь появятся подтверждения заказов, приход няни и отчёты о прогулках.', 'Уведомления на телефон выключены', 'Менеджер позван', 'Не получилось — проверьте интернет.') as $x) {
        $cfg['tr'][$x] = petgo_site_l($x);
    }
    return $cfg;
}, 30, 2);

/* ---------- приложение сотрудников: Настройки → «Уведомления в приложении My PetGo» (ключ Firebase) ---------- */
add_filter('petgo_settings_rows', function ($rows) {
    if (function_exists('petgo_is_owner') && petgo_is_owner()) {
        $rows[] = array('icon' => 'bell', 'title' => 'Уведомления в приложении My PetGo', 'sub' => petgo_fcm_sa() ? 'Firebase подключён' : 'Нужен ключ Firebase', 'url' => petgo_page_url(array('pg' => 'fcm')));
    }
    return $rows;
});
add_filter('petgo_app_title', function ($t, $pg) {
    return $pg === 'fcm' ? 'Уведомления' : $t;
}, 10, 2);
add_filter('petgo_app_page', function ($done, $pg) {
    if ($done || $pg !== 'fcm') {
        return $done;
    }
    if (!petgo_is_owner()) {
        echo '<p class="mu">' . esc_html(petgo_t('err_perm')) . '</p>';
        return true;
    }
    $sa = petgo_fcm_sa();
    echo '<style>.pgfcm .pgc-card{display:flex;flex-direction:column;gap:10px;margin-bottom:12px}.pgfcm ol{margin:0;padding-left:20px;color:var(--mu);font-size:14px;line-height:1.55}.pgfcm textarea{width:100%;min-height:140px;font:13px/1.4 ui-monospace,monospace;border-radius:12px;border:1px solid var(--ln);padding:10px;background:var(--so);color:var(--tx)}</style>';
    echo petgo_form_open('fcm_save', 'class="pgfcm"');
    echo '<div class="pgc-card"><b>Уведомления в приложении My PetGo</b><span class="mu">' . ($sa ? 'Подключён проект Firebase «' . esc_html($sa['project_id']) . '». Клиенты с приложением получают уведомления о заказах.' : 'Сайт и приложение с экрана «Домой» присылают уведомления сами. Приложению из App Store и Google Play нужен ключ Firebase.') . '</span>';
    echo '<ol><li>console.firebase.google.com → ваш проект (тот же, что в приложении My PetGo).</li><li>Настройки проекта → «Сервисные аккаунты» → «Создать закрытый ключ».</li><li>Откройте скачанный файл .json, скопируйте всё и вставьте сюда.</li></ol>';
    echo '<textarea name="sa" placeholder="{ &quot;type&quot;: &quot;service_account&quot;, … }"></textarea></div>';
    echo '<div style="display:flex;gap:10px;flex-wrap:wrap"><button type="submit" class="pgc-btn">Сохранить ключ</button>' . ($sa ? '<button type="submit" name="del" value="1" class="pgc-btn sec red" onclick="return confirm(\'Удалить ключ Firebase?\')">Удалить ключ</button>' : '') . '</div></form>';
    return true;
}, 10, 2);
add_action('admin_post_petgo_fcm_save', function () {
    petgo_guard('admin');
    if (!petgo_is_owner()) {
        wp_die('');
    }
    if (!empty($_POST['del'])) {
        delete_option('petgo_fcm_sa');
        delete_transient('petgo_fcm_at');
        petgo_flash('Ключ Firebase удалён', true);
    } else {
        $raw = isset($_POST['sa']) ? trim((string) wp_unslash($_POST['sa'])) : '';
        $j   = json_decode($raw, true);
        if (is_array($j) && !empty($j['project_id']) && !empty($j['client_email']) && !empty($j['private_key'])) {
            update_option('petgo_fcm_sa', wp_json_encode($j), false);
            delete_transient('petgo_fcm_at');
            petgo_flash(petgo_fcm_token() !== '' ? 'Firebase подключён' : 'Ключ сохранён, но Google его не принял — проверьте файл', true);
        } else {
            petgo_flash('Это не ключ сервисного аккаунта Firebase (.json)', false);
        }
    }
    petgo_audit('fcm_key', !empty($_POST['del']) ? 'del' : 'set');
    wp_safe_redirect(petgo_page_url(array('pg' => 'fcm')));
    exit;
});

add_filter('petgo_site_dict_more', function ($d) {
    return array_merge($d, array(
        'Включите уведомления' => array('Хабарландыруды қосыңыз', 'Turn on notifications'),
        'Сообщим, когда менеджер подтвердит заказ, няня придёт, заберёт питомца и вернёт его домой.' => array('Менеджер тапсырысты растағанда, күтуші келгенде, жануарды алып кеткенде және үйге әкелгенде хабарлаймыз.', 'We’ll let you know when your booking is confirmed and when the sitter arrives, picks up your pet and brings it home.'),
        'Заказ подтверждён' => array('Тапсырыс расталды', 'Booking confirmed'),
        'Няня пришла и забрала питомца' => array('Күтуші келіп, жануарды алды', 'The sitter arrived and picked up your pet'),
        'Питомец дома — фото и отчёт' => array('Жануар үйде — фото мен есеп', 'Your pet is home — photos and a report'),
        'Включить' => array('Қосу', 'Turn on'),
        'Не сейчас' => array('Қазір емес', 'Not now'),
        'Уведомления включены' => array('Хабарландыру қосылды', 'Notifications are on'),
        'Уведомления выключены в настройках телефона. Включите их для этого приложения.' => array('Телефон баптауларында хабарландыру өшірулі. Осы қосымша үшін қосыңыз.', 'Notifications are off in your phone settings. Turn them on for this app.'),
        'Уведомления на телефон' => array('Телефонға хабарландыру', 'Phone notifications'),
        'Включены' => array('Қосулы', 'On'),
        'Выключены' => array('Өшірулі', 'Off'),
        'Уведомления на телефон выключены' => array('Телефонға хабарландыру өшірулі', 'Phone notifications are off'),
        'Позвать менеджера' => array('Менеджерді шақыру', 'Call a manager'),
        'Менеджер позван' => array('Менеджер шақырылды', 'Manager called'),
        'Менеджер подключён — ответит здесь, в чате.' => array('Менеджер қосылды — осы чатта жауап береді.', 'A manager has joined — they’ll reply here in the chat.'),
        'Менеджер подключён к чату и ответит здесь. Помощник пока не отвечает.' => array('Менеджер чатқа қосылды, осында жауап береді. Көмекші әзірге жауап бермейді.', 'A manager has joined the chat and will reply here. The assistant is paused.'),
        'Помощник PetGo' => array('PetGo көмекшісі', 'PetGo assistant'),
    ));
});

/* ============================================================
   40.0 УДАЛЕНИЕ КЛИЕНТА (сотрудник в карточке клиента или «Очистить сайт» → «Клиенты»)
   Удаляются кабинет, чат с сообщениями и фото, подписки на уведомления. Питомцы остаются в базе (без хозяина-кабинета).
   ============================================================ */
function petgo_cl_delete_account($uid, $by = 'staff') {
    $u = get_userdata((int) $uid);
    if (!$u || !function_exists('petgo_cl_is') || !petgo_cl_is($u)) {
        return false;
    }
    foreach (petgo_cl_pets($uid) as $p) {
        delete_post_meta($p->ID, '_client');
    }
    $a = get_user_meta($uid, 'petgo_avatar', true);
    if (is_array($a) && !empty($a['file']) && function_exists('petgo_rm')) {
        petgo_rm($a['file']);
    }
    $cid = function_exists('petgo_cl_chat') ? petgo_cl_chat($uid, false) : 0;
    if ($cid && function_exists('petgo_chats_wipe')) {
        petgo_chats_wipe(array($cid), true);
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    $to = get_current_user_id();
    wp_delete_user((int) $uid, $to && $to !== (int) $uid ? $to : null);
    petgo_audit('cl_delete', $u->user_login . ' by=' . $by);
    return true;
}
add_action('admin_post_petgo_cl_delete', function () {
    petgo_guard('admin');
    $uid = (int) petgo_p('uid');
    $u   = get_userdata($uid);
    $ok  = $u && petgo_cl_delete_account($uid, 'staff');
    petgo_flash($ok ? 'Клиент «' . $u->display_name . '» удалён' : 'Клиент не найден', (bool) $ok);
    if (function_exists('petgo_bump')) {
        petgo_bump();
    }
    wp_safe_redirect(petgo_page_url(array('pg' => 'clients')));
    exit;
});
