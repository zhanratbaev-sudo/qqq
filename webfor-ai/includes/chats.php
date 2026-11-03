<?php
/**
 * Разговоры в чате сохраняются, чтобы менеджер мог подключиться к живому чату.
 * Разговор — запись wfai_chat: метаполе _msgs — список сообщений {i, r: user|bot|manager|system, a: автор, t: текст, at: время},
 * _status — bot (отвечает ассистент) | waiting (ассистент позвал менеджера) | live (менеджер в чате) | closed.
 * Клиент знает свой разговор по случайному токену. Менеджер работает через REST /webfor-ai/v1/manager/* (только администратор).
 */
defined('ABSPATH') || exit;

add_action('init', function () {
    register_post_type('wfai_chat', ['public' => false, 'show_ui' => false, 'supports' => ['title'], 'label' => 'Чаты']);
});

function wfai_manager_name() {
    return wfai_opt('owner_name') ?: 'Менеджер';
}

function wfai_chat_by_token($token) {
    $token = preg_replace('/[^a-f0-9]/', '', (string) $token);
    if (strlen($token) !== 32) {
        return null;
    }
    $q = get_posts(['post_type' => 'wfai_chat', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => '_token', 'meta_value' => $token, 'fields' => 'ids']);
    return $q ? (int) $q[0] : null;
}

function wfai_chat_create() {
    $token = bin2hex(random_bytes(16));
    $id = wp_insert_post(['post_type' => 'wfai_chat', 'post_status' => 'publish', 'post_title' => 'Чат ' . wp_date('d.m H:i')]);
    if (!$id || is_wp_error($id)) {
        return [0, ''];
    }
    update_post_meta($id, '_token', $token);
    update_post_meta($id, '_status', 'bot');
    update_post_meta($id, '_msgs', []);
    update_post_meta($id, '_unread', 0);
    return [(int) $id, $token];
}

function wfai_chat_msgs($id) {
    return array_values((array) get_post_meta($id, '_msgs', true));
}

function wfai_chat_status($id) {
    return get_post_meta($id, '_status', true) ?: 'bot';
}

function wfai_chat_set_status($id, $status) {
    update_post_meta($id, '_status', $status);
}

/** Менеджер входит в чат. Если клиенту уже сказали «менеджер подключился» (статус waiting) — второй раз не пишем. */
function wfai_chat_join($id) {
    $was = wfai_chat_status($id);
    if ($was === 'live') {
        return;
    }
    wfai_chat_set_status($id, 'live');
    if ($was !== 'waiting') {
        wfai_chat_add($id, 'system', 'Менеджер ' . wfai_manager_name() . ' подключился к чату и скоро ответит.');
    }
}

/** Добавляет сообщение и возвращает его. */
function wfai_chat_add($id, $role, $text, $author = '') {
    $msgs = wfai_chat_msgs($id);
    $m = ['i' => count($msgs) ? end($msgs)['i'] + 1 : 1, 'r' => $role, 'a' => $author, 't' => (string) $text, 'at' => time()];
    $msgs[] = $m;
    update_post_meta($id, '_msgs', array_slice($msgs, -200));
    update_post_meta($id, '_updated', time());
    if ($role === 'user') {
        update_post_meta($id, '_last', mb_substr($text, 0, 140));
        update_post_meta($id, '_unread', (int) get_post_meta($id, '_unread', true) + 1);
    }
    return $m;
}

/** История для модели: клиент — user, ассистент и менеджер — assistant (менеджер с подписью). */
function wfai_chat_history($id) {
    $out = [];
    foreach (array_slice(wfai_chat_msgs($id), -16) as $m) {
        if ($m['r'] === 'system') {
            continue;
        }
        $role = $m['r'] === 'user' ? 'user' : 'assistant';
        $text = $m['r'] === 'manager' ? '[Менеджер ' . $m['a'] . ']: ' . $m['t'] : $m['t'];
        if ($out && end($out)['role'] === $role) {
            $out[count($out) - 1]['content'] .= "\n" . $text;
        } else {
            $out[] = ['role' => $role, 'content' => $text];
        }
    }
    while ($out && $out[0]['role'] !== 'user') {
        array_shift($out);
    }
    return $out;
}

/** Сообщения для клиента после индекса $after (свои сообщения клиент уже показал сам). */
function wfai_chat_public($id, $after) {
    $out = [];
    foreach (wfai_chat_msgs($id) as $m) {
        if ($m['i'] > $after && $m['r'] !== 'user') {
            $out[] = ['i' => $m['i'], 'r' => $m['r'], 'a' => $m['a'], 't' => $m['t']];
        }
    }
    return $out;
}

/* ---------- частые вопросы: каждый вопрос клиента считается ---------- */

function wfai_log_question($text) {
    $norm = trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}\s]/u', '', mb_strtolower($text))));
    if (mb_strlen($norm) < 4) {
        return;
    }
    $key = md5(mb_substr($norm, 0, 160));
    $log = (array) get_option('wfai_qlog', []);
    if (isset($log[$key])) {
        $log[$key]['n']++;
        $log[$key]['at'] = time();
    } else {
        $log[$key] = ['q' => mb_substr($text, 0, 300), 'n' => 1, 'at' => time()];
    }
    if (count($log) > 400) { // храним самые частые и свежие
        uasort($log, fn($a, $b) => [$b['n'], $b['at']] <=> [$a['n'], $a['at']]);
        $log = array_slice($log, 0, 300, true);
    }
    update_option('wfai_qlog', $log, false);
}

/* ---------- REST для клиента: новые сообщения и статус ---------- */

add_action('rest_api_init', function () {
    register_rest_route('webfor-ai/v1', '/poll', ['methods' => 'GET', 'callback' => function (WP_REST_Request $q) {
        $id = wfai_chat_by_token($q->get_param('chat'));
        if (!$id) {
            return new WP_REST_Response(['ok' => false], 404);
        }
        return ['ok' => true, 'status' => wfai_chat_status($id), 'msgs' => wfai_chat_public($id, (int) $q->get_param('after'))];
    }, 'permission_callback' => '__return_true']);

    /* ---------- REST для менеджера ---------- */
    $can = fn() => current_user_can('manage_options');
    $r = function ($route, $methods, $cb) use ($can) {
        register_rest_route('webfor-ai/v1', '/manager' . $route, ['methods' => $methods, 'callback' => $cb, 'permission_callback' => $can]);
    };
    $chat_or_404 = function ($id) {
        $id = (int) $id;
        return get_post_type($id) === 'wfai_chat' ? $id : 0;
    };

    $r('/chats', 'GET', function () {
        $out = [];
        foreach (get_posts(['post_type' => 'wfai_chat', 'post_status' => 'publish', 'numberposts' => 100, 'meta_key' => '_updated', 'orderby' => 'meta_value_num', 'order' => 'DESC']) as $p) {
            $msgs = wfai_chat_msgs($p->ID);
            if (!$msgs || wfai_chat_status($p->ID) === 'closed') {
                continue;
            }
            $out[] = ['id' => $p->ID, 'status' => wfai_chat_status($p->ID), 'last' => (string) get_post_meta($p->ID, '_last', true),
                'contact' => (string) get_post_meta($p->ID, '_contact', true), 'unread' => (int) get_post_meta($p->ID, '_unread', true),
                'updated' => (int) get_post_meta($p->ID, '_updated', true), 'count' => count($msgs)];
        }
        // сначала ждут менеджера, потом живые, потом остальные
        $rank = ['waiting' => 0, 'live' => 1, 'bot' => 2, 'closed' => 3];
        usort($out, fn($a, $b) => [$rank[$a['status']] ?? 9, -$a['updated']] <=> [$rank[$b['status']] ?? 9, -$b['updated']]);
        return ['chats' => $out, 'manager' => wfai_manager_name()];
    });

    $r('/chat/(?P<id>\d+)', 'GET', function (WP_REST_Request $q) use ($chat_or_404) {
        $id = $chat_or_404($q['id']);
        if (!$id) {
            return new WP_Error('nf', 'Чат не найден', ['status' => 404]);
        }
        update_post_meta($id, '_unread', 0);
        $after = (int) $q->get_param('after');
        return ['status' => wfai_chat_status($id), 'contact' => (string) get_post_meta($id, '_contact', true),
            'msgs' => array_values(array_filter(wfai_chat_msgs($id), fn($m) => $m['i'] > $after))];
    });

    $r('/chat/(?P<id>\d+)/join', 'POST', function (WP_REST_Request $q) use ($chat_or_404) {
        $id = $chat_or_404($q['id']);
        if (!$id) {
            return new WP_Error('nf', 'Чат не найден', ['status' => 404]);
        }
        wfai_chat_join($id);
        return ['ok' => true, 'status' => 'live'];
    });

    $r('/chat/(?P<id>\d+)/send', 'POST', function (WP_REST_Request $q) use ($chat_or_404) {
        $id = $chat_or_404($q['id']);
        $text = trim(sanitize_textarea_field((string) $q->get_param('text')));
        if (!$id || $text === '') {
            return new WP_Error('bad', 'Пустое сообщение', ['status' => 400]);
        }
        wfai_chat_join($id); // писать можно только присоединившись
        return ['ok' => true, 'msg' => wfai_chat_add($id, 'manager', mb_substr($text, 0, 2000), wfai_manager_name())];
    });

    $r('/chat/(?P<id>\d+)/release', 'POST', function (WP_REST_Request $q) use ($chat_or_404) {
        $id = $chat_or_404($q['id']);
        if (!$id) {
            return new WP_Error('nf', 'Чат не найден', ['status' => 404]);
        }
        wfai_chat_set_status($id, 'bot');
        wfai_chat_add($id, 'system', 'Менеджер вышел из чата — дальше снова отвечает ассистент.');
        return ['ok' => true, 'status' => 'bot'];
    });

    // проверка ассистента из админки: ответ на пробный вопрос, ничего не сохраняется и менеджеру не уходит
    $r('/test', 'POST', function (WP_REST_Request $q) {
        $text = trim(sanitize_textarea_field((string) $q->get_param('text')));
        if ($text === '') {
            return new WP_Error('bad', 'Напишите вопрос', ['status' => 400]);
        }
        $hist = [];
        foreach (array_slice((array) $q->get_param('history'), -10) as $m) {
            $role = ($m['role'] ?? '') === 'user' ? 'user' : 'assistant';
            $c = mb_substr(sanitize_textarea_field((string) ($m['content'] ?? '')), 0, 1500);
            if ($c !== '') {
                $hist[] = ['role' => $role, 'content' => $c];
            }
        }
        while ($hist && $hist[0]['role'] !== 'user') {
            array_shift($hist);
        }
        $hist[] = ['role' => 'user', 'content' => mb_substr($text, 0, 1500)];
        $a = wfai_model_answer($hist, $text);
        return ['reply' => $a['text'], 'escalated' => (bool) $a['escalate'], 'reason' => (string) ($a['escalate']['reason'] ?? ''), 'unsupported' => $a['unsupported']];
    });

    // закрыть разговор: он пропадает из списка; если клиент напишет снова — откроется заново и ответит ассистент
    $r('/chat/(?P<id>\d+)/close', 'POST', function (WP_REST_Request $q) use ($chat_or_404) {
        $id = $chat_or_404($q['id']);
        if (!$id) {
            return new WP_Error('nf', 'Чат не найден', ['status' => 404]);
        }
        wfai_chat_set_status($id, 'closed');
        update_post_meta($id, '_unread', 0);
        return ['ok' => true, 'status' => 'closed'];
    });

    $r('/faq', 'GET', function () {
        $log = array_values((array) get_option('wfai_qlog', []));
        usort($log, fn($a, $b) => [$b['n'], $b['at']] <=> [$a['n'], $a['at']]);
        return ['items' => array_slice($log, 0, 100)];
    });

    $r('/push/subscribe', 'POST', function (WP_REST_Request $q) {
        $sub = $q->get_param('subscription');
        if (!is_array($sub) || empty($sub['endpoint']) || stripos((string) $sub['endpoint'], 'https://') !== 0) {
            return new WP_Error('bad', 'Неверная подписка', ['status' => 400]);
        }
        $subs = (array) get_option('wfai_push_subs', []);
        $subs[md5($sub['endpoint'])] = ['endpoint' => esc_url_raw($sub['endpoint']), 'keys' => array_map('sanitize_text_field', (array) ($sub['keys'] ?? []))];
        update_option('wfai_push_subs', $subs, false);
        return ['ok' => true, 'count' => count($subs)];
    });
    $r('/push/test', 'POST', function () {
        $n = wfai_push_all('WebFor', 'Уведомления работают 👍', wfai_admin_url());
        return ['ok' => true, 'sent' => $n];
    });
});
