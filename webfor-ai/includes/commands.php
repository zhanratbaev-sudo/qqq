<?php
/**
 * Команды в чате (на сайте и в мессенджерах): сообщение, которое начинается с «/».
 * Встроенные: /del (очистить чат — как новый), /help, /manager, /bot, /status. Свои — в админке (Обучение → Команды чата),
 * хранятся в опции wfai_commands как [{cmd: '/price', reply: 'текст'}]. Команды не попадают в «Частые вопросы».
 */
defined('ABSPATH') || exit;

function wfai_builtin_commands() {
    return [
        '/del'     => 'Очистить чат и начать заново',
        '/help'    => 'Список команд',
        '/manager' => 'Позвать менеджера',
        '/bot'     => 'Вернуть ответы ассистента',
        '/status'  => 'Кто сейчас отвечает в чате',
    ];
}
function wfai_builtin_aliases() {
    return ['/new' => '/del', '/reset' => '/del', '/clear' => '/del', '/start' => '/help', '/menu' => '/help', '/human' => '/manager', '/menedzher' => '/manager'];
}
/** Свои команды владельца. */
function wfai_custom_commands() {
    $out = [];
    foreach ((array) get_option('wfai_commands', []) as $c) {
        if (!empty($c['cmd']) && isset($c['reply']) && $c['reply'] !== '') {
            $out[] = ['cmd' => (string) $c['cmd'], 'reply' => (string) $c['reply'], 'desc' => (string) ($c['desc'] ?? '')];
        }
    }
    return $out;
}
function wfai_sanitize_commands($rows) {
    $out = [];
    $builtin = array_keys(wfai_builtin_commands() + wfai_builtin_aliases());
    foreach ((array) $rows as $r) {
        $cmd = '/' . ltrim(mb_strtolower(trim(sanitize_text_field((string) ($r['cmd'] ?? '')))), '/');
        $reply = trim(sanitize_textarea_field((string) ($r['reply'] ?? '')));
        if (!preg_match('/^\/[\p{L}0-9_]{1,32}$/u', $cmd) || $reply === '' || in_array($cmd, $builtin, true) || isset($out[$cmd])) {
            continue;
        }
        $out[$cmd] = ['cmd' => $cmd, 'reply' => mb_substr($reply, 0, 3000), 'desc' => mb_substr(trim(sanitize_text_field((string) ($r['desc'] ?? ''))), 0, 80)];
    }
    return array_values($out);
}
/** Текст для /help. */
function wfai_help_text() {
    $lines = ['Команды:'];
    foreach (wfai_builtin_commands() as $c => $d) {
        $lines[] = $c . ' — ' . mb_strtolower(mb_substr($d, 0, 1)) . mb_substr($d, 1);
    }
    foreach (wfai_custom_commands() as $c) {
        $lines[] = $c['cmd'] . ($c['desc'] !== '' ? ' — ' . $c['desc'] : '');
    }
    return implode("\n", $lines);
}
/** Разбирает «/команда» (в Telegram бывает «/help@ИмяБота»). Возвращает команду или ''. */
function wfai_parse_command($text) {
    $text = trim((string) $text);
    if ($text === '' || $text[0] !== '/' || mb_strlen($text) > 64) {
        return '';
    }
    $cmd = mb_strtolower(preg_split('/[\s@]/u', $text)[0]);
    return wfai_builtin_aliases()[$cmd] ?? $cmd;
}

/**
 * Выполняет команду. $id — разговор (может быть 0, если его ещё нет).
 * Возвращает null, если это не команда, иначе ['reset' => bool, 'reply' => текст, 'out' => [сообщения], 'status' => …].
 */
function wfai_run_command($id, $text) {
    $cmd = wfai_parse_command($text);
    if ($cmd === '') {
        return null;
    }
    $custom = [];
    foreach (wfai_custom_commands() as $c) {
        $custom[$c['cmd']] = $c['reply'];
    }
    if (!isset(wfai_builtin_commands()[$cmd]) && !isset($custom[$cmd])) {
        return null; // неизвестная «/…» — пусть отвечает ассистент как на обычный текст
    }
    $res = ['reset' => false, 'reply' => '', 'out' => [], 'status' => $id ? wfai_chat_status($id) : 'bot'];
    $say = function ($t, $role = 'bot') use (&$res, &$id) {
        if (!$id) {
            [$id] = wfai_chat_create();
        }
        $res['out'][] = wfai_chat_add($id, $role, $t);
        $res['reply'] = $t;
    };

    if ($cmd === '/del') {
        $res['reset'] = true;
        $res['reply'] = 'Чат очищен — начнём заново 🙂';
        if ($id) {
            if (get_post_meta($id, '_ch', true) && function_exists('wfai_ch_send')) {
                wfai_ch_send($id, $res['reply']); // в мессенджере — сообщаем перед удалением
            }
            wp_delete_post($id, true);
        }
        $res['status'] = 'bot';
        return $res;
    }
    if (isset($custom[$cmd])) {
        if ($id) {
            wfai_chat_add($id, 'user', $text);
        }
        $say($custom[$cmd]);
        return $res + ['chat' => $id];
    }
    if ($id) {
        wfai_chat_add($id, 'user', $text);
    }
    switch ($cmd) {
        case '/help':
            $say(wfai_help_text());
            break;
        case '/status':
            $st = $id ? wfai_chat_status($id) : 'bot';
            $say(['bot' => 'Сейчас отвечает AI-ассистент.', 'waiting' => 'Менеджер позван и скоро ответит. Ассистент пока молчит.', 'live' => 'В чате менеджер ' . wfai_manager_name() . '.', 'closed' => 'Чат закрыт — напишите, и он откроется снова.'][$st] ?? $st, 'system');
            break;
        case '/manager':
            if (!$id) {
                [$id] = wfai_chat_create();
            }
            if (in_array(wfai_chat_status($id), ['waiting', 'live'], true)) {
                $say('Менеджер уже в чате и скоро ответит.', 'system');
                break;
            }
            $qid = wfai_create_question('Клиент просит менеджера (команда /manager)', 'Клиент сам позвал менеджера', wfai_chat_history($id));
            update_post_meta($qid, '_chat', $id);
            if ($c = get_post_meta($id, '_contact', true)) {
                update_post_meta($qid, '_contact', $c);
            }
            wfai_chat_set_status($id, 'waiting');
            $say('Менеджер ' . wfai_manager_name() . ' подключился к чату и скоро ответит.', 'system');
            wfai_push_all('Нужно присоединиться к чату', 'Клиент позвал менеджера командой /manager', wfai_admin_url('chat-' . $id));
            $res['question_id'] = $qid;
            break;
        case '/bot':
            if ($id && wfai_chat_status($id) !== 'bot') {
                wfai_chat_set_status($id, 'bot');
            }
            $say('Дальше снова отвечает AI-ассистент. Спрашивайте 🙂', 'system');
            break;
    }
    $res['status'] = $id ? wfai_chat_status($id) : 'bot';
    $res['chat'] = $id;
    return $res;
}

/** Меню команд в Telegram (кнопка «/» у поля ввода). */
function wfai_tg_sync_commands() {
    if (!function_exists('wfai_tg_api') || wfai_ch_opt('tg_token') === '') {
        return;
    }
    $list = [];
    foreach (wfai_builtin_commands() as $c => $d) {
        $list[] = ['command' => ltrim($c, '/'), 'description' => $d];
    }
    foreach (wfai_custom_commands() as $c) {
        if (preg_match('/^\/[a-z0-9_]{1,32}$/', $c['cmd'])) { // Telegram понимает только латиницу
            $list[] = ['command' => ltrim($c['cmd'], '/'), 'description' => $c['desc'] ?: mb_substr($c['reply'], 0, 60)];
        }
    }
    wfai_tg_api('setMyCommands', ['commands' => array_slice($list, 0, 100)]);
}

add_action('rest_api_init', function () {
    $can = fn() => current_user_can('manage_options');
    register_rest_route('webfor-ai/v1', '/manager/commands', [
        ['methods' => 'GET', 'permission_callback' => $can, 'callback' => fn() => ['builtin' => wfai_builtin_commands(), 'items' => wfai_custom_commands()]],
        ['methods' => 'POST', 'permission_callback' => $can, 'callback' => function (WP_REST_Request $q) {
            update_option('wfai_commands', wfai_sanitize_commands($q->get_param('items')), false);
            wfai_tg_sync_commands();
            return ['builtin' => wfai_builtin_commands(), 'items' => wfai_custom_commands()];
        }],
    ]);
});
