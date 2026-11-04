<?php
/**
 * Мессенджеры: тот же ассистент отвечает в Telegram, WhatsApp и Instagram.
 * Входящие приходят на вебхуки (REST /webfor-ai/v1/telegram и /webfor-ai/v1/meta), каждый собеседник — отдельный
 * разговор wfai_chat (метаполя _ch: tg|wa|ig и _ext: его id в мессенджере). Ответы ассистента, менеджера и служебные
 * строки уходят обратно в мессенджер из wfai_chat_add() → wfai_channel_deliver().
 * Настройки (токены) — в админке: Ещё → Мессенджеры; хранятся в опции wfai_channels.
 */
defined('ABSPATH') || exit;

const WFAI_CHANNELS = ['tg' => 'Telegram', 'wa' => 'WhatsApp', 'ig' => 'Instagram'];

function wfai_ch_opts() {
    return wp_parse_args((array) get_option('wfai_channels', []), [
        'tg_token' => '', 'tg_secret' => '', 'tg_bot' => '', 'tg_owner' => '',
        'wa_token' => '', 'wa_phone_id' => '', 'wa_phone' => '',
        'ig_token' => '', 'ig_id' => '', 'ig_user' => '',
        'meta_secret' => '', 'meta_verify' => '', 'graph' => 'v23.0',
    ]);
}
function wfai_ch_opt($k) {
    return (string) (wfai_ch_opts()[$k] ?? '');
}
function wfai_ch_save(array $vals) {
    update_option('wfai_channels', array_merge(wfai_ch_opts(), $vals), false);
}
/** Случайный секрет, создаётся один раз (проверка, что вебхук пришёл от Telegram или Meta). */
function wfai_ch_secret($k) {
    $v = wfai_ch_opt($k);
    if ($v === '') {
        $v = bin2hex(random_bytes(16));
        wfai_ch_save([$k => $v]);
    }
    return $v;
}
function wfai_ch_webhook($ch) {
    $route = '/webfor-ai/v1/' . ($ch === 'tg' ? 'telegram' : 'meta');
    // без постоянных ссылок rest_url даёт «/index.php?rest_route=…», а многие хостинги перенаправляют /index.php → / —
    // Telegram и Meta перенаправления не проходят. Адрес без index.php работает так же, но без редиректа.
    if (!get_option('permalink_structure')) {
        return add_query_arg('rest_route', $route, home_url('/'));
    }
    return rest_url(ltrim($route, '/'));
}
function wfai_ch_on($ch) {
    return $ch === 'tg' ? wfai_ch_opt('tg_token') !== '' : ($ch === 'wa' ? wfai_ch_opt('wa_token') !== '' && wfai_ch_opt('wa_phone_id') !== '' : wfai_ch_opt('ig_token') !== '');
}

/** Разговор с собеседником из мессенджера: находим или создаём. */
function wfai_ch_chat($ch, $ext, $label) {
    $q = get_posts(['post_type' => 'wfai_chat', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids',
        'meta_query' => [['key' => '_ch', 'value' => $ch], ['key' => '_ext', 'value' => (string) $ext]]]);
    if ($q) {
        return (int) $q[0];
    }
    [$id] = wfai_chat_create();
    if ($id) {
        update_post_meta($id, '_ch', $ch);
        update_post_meta($id, '_ext', (string) $ext);
        if ($label !== '') {
            update_post_meta($id, '_contact', $label);
        }
    }
    return (int) $id;
}
/** Не больше N сообщений в час от одного собеседника (вебхуки приходят с серверов мессенджеров, IP у всех один). */
function wfai_ch_rate($key) {
    $k = 'wfai_chrl_' . md5($key);
    $n = (int) get_transient($k);
    if ($n >= (int) wfai_opt('per_hour')) {
        return false;
    }
    set_transient($k, $n + 1, HOUR_IN_SECONDS);
    return true;
}
/** Мессенджеры иногда присылают одно сообщение дважды — второй раз не отвечаем. */
function wfai_ch_seen($id) {
    $k = 'wfai_mid_' . md5((string) $id);
    if (get_transient($k)) {
        return true;
    }
    set_transient($k, 1, DAY_IN_SECONDS);
    return false;
}

/** Входящее сообщение из мессенджера → тот же ассистент, что и на сайте. */
function wfai_ch_incoming($ch, $ext, $label, $text, $voice = false, array $meta = []) {
    if (!wfai_opt('enabled')) {
        return;
    }
    $id = wfai_ch_chat($ch, $ext, $label);
    if (!$id) {
        return;
    }
    foreach ($meta as $k => $v) {
        update_post_meta($id, $k, $v);
    }
    if (!wfai_ch_rate($ch . $ext)) {
        return;
    }
    $text = mb_substr(trim($text), 0, 1500);
    if ($text === '') {
        return;
    }
    if (!$voice && function_exists('wfai_run_command') && wfai_run_command($id, $text)) {
        return; // команда (/del, /help…) — ответ уже ушёл в мессенджер
    }
    wfai_context_note('Клиент пишет в ' . WFAI_CHANNELS[$ch] . '. Не предлагай форму на сайте и не проси номер телефона — контакт уже есть. '
        . 'Если нужен менеджер — он ответит здесь же, в ' . WFAI_CHANNELS[$ch] . '. Пиши обычным текстом, без Markdown.');
    wfai_process_message($id, $text, $voice);
}

/* ---------- отправка ---------- */

/** Вызывается из wfai_chat_add для каждого ответа ассистента, менеджера и служебной строки. */
function wfai_channel_deliver($id, $role, $text) {
    $ch = get_post_meta($id, '_ch', true);
    if (!$ch || $role === 'user') {
        return;
    }
    wfai_ch_send($id, $role === 'system' ? '— ' . $text : $text);
}
function wfai_ch_chunks($text, $max) {
    $out = [];
    while (mb_strlen($text) > $max) {
        $cut = mb_strrpos(mb_substr($text, 0, $max), "\n") ?: mb_strrpos(mb_substr($text, 0, $max), ' ') ?: $max;
        $out[] = trim(mb_substr($text, 0, $cut));
        $text = trim(mb_substr($text, $cut));
    }
    if ($text !== '') {
        $out[] = $text;
    }
    return $out;
}
function wfai_ch_send($id, $text) {
    $ch = get_post_meta($id, '_ch', true);
    $ext = (string) get_post_meta($id, '_ext', true);
    $text = trim(wp_strip_all_tags((string) $text));
    if (!$ch || $ext === '' || $text === '') {
        return;
    }
    $err = null;
    if ($ch === 'tg') {
        $bc = (string) get_post_meta($id, '_tg_bc', true);
        foreach (wfai_ch_chunks($text, 4000) as $part) {
            $r = wfai_tg_api('sendMessage', ['chat_id' => $ext, 'text' => $part] + ($bc !== '' ? ['business_connection_id' => $bc] : []));
            if (is_wp_error($r)) { $err = $r; break; }
        }
    } elseif ($ch === 'wa') {
        foreach (wfai_ch_chunks($text, 4000) as $part) {
            $r = wfai_meta_api('POST', wfai_ch_opt('wa_phone_id') . '/messages', wfai_ch_opt('wa_token'),
                ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $ext, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => $part]]);
            if (is_wp_error($r)) { $err = $r; break; }
        }
    } elseif ($ch === 'ig') {
        foreach (wfai_ch_chunks($text, 990) as $part) { // в Instagram — до 1000 символов в сообщении
            $r = wfai_meta_api('POST', 'me/messages', wfai_ch_opt('ig_token'), ['recipient' => ['id' => $ext], 'message' => ['text' => $part]], wfai_ig_host());
            if (is_wp_error($r)) { $err = $r; break; }
        }
    }
    if ($err) {
        error_log('[webfor-ai] не отправлено в ' . (WFAI_CHANNELS[$ch] ?? $ch) . ': ' . $err->get_error_message());
        set_transient('wfai_' . $ch . '_send_err', ['t' => time(), 'm' => $err->get_error_message()], WEEK_IN_SECONDS);
        update_post_meta($id, '_send_err', ['t' => time(), 'm' => $err->get_error_message()]);
    } else {
        delete_post_meta($id, '_send_err');
    }
}

/* ---------- Telegram ---------- */

function wfai_tg_api($method, array $args = [], $token = null) {
    $t = $token ?? wfai_ch_opt('tg_token');
    if ($t === '') {
        return new WP_Error('tg', 'Нет токена Telegram');
    }
    $r = wp_remote_post('https://api.telegram.org/bot' . $t . '/' . $method, ['timeout' => 15, 'headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode($args ?: new stdClass())]);
    if (is_wp_error($r)) {
        return $r;
    }
    $d = json_decode(wp_remote_retrieve_body($r), true);
    if (empty($d['ok'])) {
        return new WP_Error('tg', (string) ($d['description'] ?? 'Ошибка Telegram'));
    }
    return $d['result'];
}
function wfai_tg_file($file_id) {
    $f = wfai_tg_api('getFile', ['file_id' => $file_id]);
    if (is_wp_error($f) || empty($f['file_path'])) {
        return null;
    }
    $r = wp_remote_get('https://api.telegram.org/file/bot' . wfai_ch_opt('tg_token') . '/' . $f['file_path'], ['timeout' => 20]);
    return is_wp_error($r) ? null : wp_remote_retrieve_body($r);
}
/** Подключение: проверяем токен и говорим Telegram, куда присылать сообщения. */
function wfai_tg_connect($token) {
    $me = wfai_tg_api('getMe', [], $token);
    if (is_wp_error($me)) {
        return new WP_Error('tg', 'Telegram не принял токен: ' . $me->get_error_message());
    }
    $hook = wfai_tg_api('setWebhook', ['url' => wfai_ch_webhook('tg'), 'secret_token' => wfai_ch_secret('tg_secret'),
        'allowed_updates' => ['message', 'business_message', 'business_connection'], 'drop_pending_updates' => true], $token);
    if (is_wp_error($hook)) {
        return new WP_Error('tg', 'Не получилось подключить вебхук: ' . $hook->get_error_message() . '. Сайт должен открываться по https.');
    }
    wfai_ch_save(['tg_token' => $token, 'tg_bot' => (string) ($me['username'] ?? '')]);
    return $me;
}
/**
 * Проверка, что Telegram правда доставляет сообщения на сайт (getWebhookInfo).
 * «Подключён» в админке раньше значило только «токен сохранён»; если Telegram не может достучаться до сайта
 * (редирект, 403 от защиты хостинга, другой адрес сайта, ошибка сертификата), бот молча не отвечал.
 * Если адрес вебхука не совпадает с нынешним или сайт отвечал 401/403 — переподключаем вебхук сами.
 */
function wfai_tg_health($repair = true) {
    $info = wfai_tg_api('getWebhookInfo');
    if (is_wp_error($info)) {
        return ['ok' => false, 'error' => 'Telegram не отвечает на проверку: ' . $info->get_error_message()];
    }
    $want = wfai_ch_webhook('tg');
    $url = (string) ($info['url'] ?? '');
    $err = (string) ($info['last_error_message'] ?? '');
    $err_at = (int) ($info['last_error_date'] ?? 0);
    $last_in = (int) get_transient('wfai_tg_last_in');
    $fresh = $err !== '' && $err_at > $last_in; // ошибка новее последнего удачного сообщения
    if ($repair && ($url !== $want || ($fresh && preg_match('/\b(401|403)\b/', $err)))) {
        wfai_tg_api('setWebhook', ['url' => $want, 'secret_token' => wfai_ch_secret('tg_secret'),
            'allowed_updates' => ['message', 'business_message', 'business_connection']]);
        return wfai_tg_health(false);
    }
    $out = ['ok' => true, 'error' => '', 'pending' => (int) ($info['pending_update_count'] ?? 0), 'last_in' => $last_in, 'url' => $url];
    if ($url === '') {
        $out['ok'] = false;
        $out['error'] = 'Вебхук не установлен — нажмите «Сохранить и проверить».';
    } elseif ($url !== $want) {
        $out['ok'] = false;
        $out['error'] = 'Telegram шлёт сообщения на другой адрес: ' . $url . '. Нажмите «Сохранить и проверить».';
    } elseif ($fresh) {
        $out['ok'] = false;
        $out['error'] = 'Telegram не может доставить сообщения на сайт: ' . $err . '. ' . wfai_tg_hint($err);
    }
    if ($out['ok'] && ($se = get_transient('wfai_tg_send_err')) && (int) $se['t'] > $last_in - 60) {
        $out['ok'] = false;
        $out['error'] = 'Сообщения доходят, но ответ не отправился: ' . $se['m'];
    }
    return $out;
}
/** Подсказка по частым ошибкам вебхука — простыми словами. */
function wfai_tg_hint($err) {
    if (preg_match('/\b30[1278]\b/', $err)) {
        return 'Сайт перенаправляет запрос (http→https или www↔без www), а Telegram редиректы не проходит. В WordPress → Настройки → Общие адрес сайта должен совпадать с тем, по которому он реально открывается.';
    }
    if (preg_match('/\b(401|403)\b/', $err)) {
        return 'Запрос блокирует защита: плагин безопасности (Wordfence, iThemes и т.п.), Cloudflare или хостинг. Разрешите адрес /wp-json/webfor-ai/v1/telegram.';
    }
    if (preg_match('/\b404\b/', $err)) {
        return 'Адрес не найден: включите постоянные ссылки (Настройки → Постоянные ссылки → Сохранить) и проверьте, что REST API (/wp-json/) не отключён.';
    }
    if (preg_match('/\b5\d\d\b/', $err)) {
        return 'Ошибка на сервере сайта — посмотрите журнал ошибок PHP на хостинге.';
    }
    if (stripos($err, 'certificate') !== false || stripos($err, 'SSL') !== false) {
        return 'Проблема с SSL-сертификатом сайта — нужен действующий сертификат (например Let\'s Encrypt), с полной цепочкой.';
    }
    if (stripos($err, 'timeout') !== false || stripos($err, 'timed out') !== false || stripos($err, 'Connection') !== false) {
        return 'Сайт не ответил вовремя или недоступен снаружи — проверьте, что он открывается по https с другого устройства.';
    }
    return '';
}
function wfai_tg_update(array $u) {
    if (!empty($u['business_connection']['user']['id'])) { // владелец подключил бота к своему аккаунту (Telegram для бизнеса)
        wfai_ch_save(['tg_owner' => (string) $u['business_connection']['user']['id']]);
        return;
    }
    $m = $u['message'] ?? $u['business_message'] ?? null;
    if (!$m || ($m['chat']['type'] ?? '') !== 'private' || !empty($m['from']['is_bot'])) {
        return;
    }
    $from = (string) ($m['from']['id'] ?? '');
    // Telegram для бизнеса: ваши собственные сообщения клиенту тоже приходят сюда — на них не отвечаем
    if ($from !== '' && ($from === wfai_ch_opt('tg_owner') || (isset($u['business_message']) && $from !== (string) ($m['chat']['id'] ?? '')))) {
        return;
    }
    if (wfai_ch_seen('tg' . ($u['update_id'] ?? ''))) {
        return;
    }
    $chat = (string) $m['chat']['id'];
    $name = trim(($m['from']['first_name'] ?? '') . ' ' . ($m['from']['last_name'] ?? ''));
    $label = (!empty($m['from']['username']) ? '@' . $m['from']['username'] : ($name ?: 'Клиент')) . ' · Telegram';
    $meta = !empty($m['business_connection_id']) ? ['_tg_bc' => (string) $m['business_connection_id']] : [];
    $text = (string) ($m['text'] ?? $m['caption'] ?? '');
    $bc = $meta['_tg_bc'] ?? '';
    if (strpos($text, '/start') === 0) { // первое нажатие «Старт» — приветствие
        $id = wfai_ch_chat('tg', $chat, $label);
        foreach ($meta as $k => $v) {
            update_post_meta($id, $k, $v);
        }
        if ($id) {
            wfai_chat_add($id, 'bot', wfai_opt('greeting'));
            wfai_tg_sync_commands();
        }
        return;
    }
    wfai_tg_api('sendChatAction', ['chat_id' => $chat, 'action' => 'typing'] + ($bc !== '' ? ['business_connection_id' => $bc] : []));
    $voice = false;
    if ($text === '' && !empty($m['voice']['file_id'])) {
        $raw = wfai_tg_file($m['voice']['file_id']);
        $heard = $raw ? wfai_transcribe_audio($raw, 'audio/ogg') : new WP_Error('voice', '');
        if (is_wp_error($heard)) {
            $tmp = wfai_ch_chat('tg', $chat, $label);
            if ($tmp) {
                wfai_ch_send($tmp, $heard->get_error_message() ?: 'Не получилось разобрать голосовое — напишите, пожалуйста, текстом.');
            }
            return;
        }
        $text = $heard;
        $voice = true;
    }
    if ($text === '') {
        return; // фото, стикеры и прочее без текста пропускаем
    }
    wfai_ch_incoming('tg', $chat, $label, $text, $voice, $meta);
}

/* ---------- Meta: WhatsApp и Instagram ---------- */

function wfai_ig_host() {
    // токен «Instagram API с входом через Instagram» начинается с IG, токен страницы Facebook — нет
    return strpos(wfai_ch_opt('ig_token'), 'IG') === 0 ? 'https://graph.instagram.com/' : 'https://graph.facebook.com/';
}
function wfai_meta_api($method, $path, $token, $body = null, $host = 'https://graph.facebook.com/') {
    if ($token === '') {
        return new WP_Error('meta', 'Нет токена');
    }
    $url = $host . (wfai_ch_opt('graph') ?: 'v23.0') . '/' . ltrim($path, '/');
    $args = ['timeout' => 15, 'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json']];
    $r = $method === 'GET' ? wp_remote_get($url, $args) : wp_remote_post($url, $args + ['body' => wp_json_encode($body)]);
    if (is_wp_error($r)) {
        return $r;
    }
    $d = json_decode(wp_remote_retrieve_body($r), true) ?: [];
    if ((int) wp_remote_retrieve_response_code($r) >= 300 || isset($d['error'])) {
        $msg = (string) ($d['error']['message'] ?? 'Ошибка ' . wp_remote_retrieve_response_code($r));
        if (in_array((int) ($d['error']['code'] ?? 0), [131047, 10], true) || stripos($msg, '24 hour') !== false || stripos($msg, 'outside of allowed window') !== false) {
            $msg = 'Прошло больше 24 часов с последнего сообщения клиента — по правилам Meta ответить можно, только когда он напишет снова.';
        }
        return new WP_Error('meta', $msg);
    }
    return $d;
}
function wfai_wa_media($media_id) {
    $m = wfai_meta_api('GET', $media_id, wfai_ch_opt('wa_token'));
    if (is_wp_error($m) || empty($m['url'])) {
        return null;
    }
    $r = wp_remote_get($m['url'], ['timeout' => 20, 'headers' => ['Authorization' => 'Bearer ' . wfai_ch_opt('wa_token')]]);
    return is_wp_error($r) ? null : wp_remote_retrieve_body($r);
}
function wfai_meta_update(array $p) {
    foreach ((array) ($p['entry'] ?? []) as $entry) {
        // WhatsApp
        foreach ((array) ($entry['changes'] ?? []) as $chg) {
            $v = $chg['value'] ?? [];
            if (($v['messaging_product'] ?? '') !== 'whatsapp') {
                continue;
            }
            $names = [];
            foreach ((array) ($v['contacts'] ?? []) as $c) {
                $names[$c['wa_id'] ?? ''] = $c['profile']['name'] ?? '';
            }
            foreach ((array) ($v['messages'] ?? []) as $m) {
                if (wfai_ch_seen('wa' . ($m['id'] ?? '')) || empty($m['from'])) {
                    continue;
                }
                $from = preg_replace('/\D/', '', (string) $m['from']);
                $label = '+' . $from . (!empty($names[$m['from']]) ? ' · ' . $names[$m['from']] : '');
                $text = '';
                $voice = false;
                if (($m['type'] ?? '') === 'text') {
                    $text = (string) ($m['text']['body'] ?? '');
                } elseif (($m['type'] ?? '') === 'audio' && !empty($m['audio']['id'])) {
                    $raw = wfai_wa_media($m['audio']['id']);
                    $heard = $raw ? wfai_transcribe_audio($raw, 'audio/ogg') : new WP_Error('voice', '');
                    if (is_wp_error($heard)) {
                        $id = wfai_ch_chat('wa', $from, $label);
                        if ($id) {
                            wfai_ch_send($id, $heard->get_error_message() ?: 'Не получилось разобрать голосовое — напишите, пожалуйста, текстом.');
                        }
                        continue;
                    }
                    $text = $heard;
                    $voice = true;
                } elseif (($m['type'] ?? '') === 'button') {
                    $text = (string) ($m['button']['text'] ?? '');
                } elseif (($m['type'] ?? '') === 'interactive') {
                    $text = (string) ($m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? '');
                }
                if ($text !== '') {
                    wfai_ch_incoming('wa', $from, $label, $text, $voice);
                }
            }
        }
        // Instagram (директ)
        foreach ((array) ($entry['messaging'] ?? []) as $ev) {
            $m = $ev['message'] ?? null;
            $from = (string) ($ev['sender']['id'] ?? '');
            if (!$m || !empty($m['is_echo']) || $from === '' || $from === wfai_ch_opt('ig_id') || wfai_ch_seen('ig' . ($m['mid'] ?? ''))) {
                continue;
            }
            $text = (string) ($m['text'] ?? '');
            if ($text !== '') {
                wfai_ch_incoming('ig', $from, 'Instagram · ' . substr($from, -6), $text);
            }
        }
    }
}

/* ---------- вебхуки ---------- */

add_action('rest_api_init', function () {
    register_rest_route('webfor-ai/v1', '/telegram', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function (WP_REST_Request $q) {
        $secret = wfai_ch_opt('tg_secret');
        if ($secret === '' || !hash_equals($secret, (string) $q->get_header('x_telegram_bot_api_secret_token'))) {
            return new WP_REST_Response(['ok' => false], 403);
        }
        set_transient('wfai_tg_last_in', time(), WEEK_IN_SECONDS); // для проверки в админке: сообщения доходят до сайта
        wfai_tg_update((array) json_decode($q->get_body(), true));
        return ['ok' => true];
    }]);
    register_rest_route('webfor-ai/v1', '/meta', [
        // Meta проверяет адрес: присылает hub.challenge — возвращаем его как есть, если совпал проверочный ключ
        ['methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => function () {
            $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
            $tok = (string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
            $ch = (string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');
            if ($mode === 'subscribe' && $tok !== '' && hash_equals(wfai_ch_secret('meta_verify'), $tok)) {
                header('Content-Type: text/plain; charset=utf-8');
                echo preg_replace('/[^A-Za-z0-9_\-]/', '', $ch);
                exit;
            }
            return new WP_REST_Response('forbidden', 403);
        }],
        ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function (WP_REST_Request $q) {
            $sec = wfai_ch_opt('meta_secret');
            if ($sec !== '') { // подпись Meta: HMAC-SHA256 тела запроса ключом приложения
                $sig = (string) $q->get_header('x_hub_signature_256');
                if (!hash_equals('sha256=' . hash_hmac('sha256', $q->get_body(), $sec), $sig)) {
                    return new WP_REST_Response(['ok' => false], 403);
                }
            }
            wfai_meta_update((array) json_decode($q->get_body(), true));
            return ['ok' => true];
        }],
    ]);

    /* ---------- настройки из админки (Ещё → Мессенджеры) ---------- */
    $can = fn() => current_user_can('manage_options');
    register_rest_route('webfor-ai/v1', '/manager/channels', [
        ['methods' => 'GET', 'permission_callback' => $can, 'callback' => fn() => wfai_ch_state()],
        ['methods' => 'POST', 'permission_callback' => $can, 'callback' => function (WP_REST_Request $q) {
            $ch = sanitize_key((string) $q->get_param('ch'));
            $val = fn($k) => trim(sanitize_text_field((string) $q->get_param($k)));
            if ($q->get_param('off')) { // отключить мессенджер
                $keys = ['tg' => ['tg_token' => '', 'tg_bot' => ''], 'wa' => ['wa_token' => '', 'wa_phone_id' => '', 'wa_phone' => ''], 'ig' => ['ig_token' => '', 'ig_id' => '', 'ig_user' => '']][$ch] ?? [];
                if ($ch === 'tg' && wfai_ch_opt('tg_token') !== '') {
                    wfai_tg_api('deleteWebhook');
                }
                wfai_ch_save($keys);
                return wfai_ch_state();
            }
            if ($val('meta_secret') !== '') {
                wfai_ch_save(['meta_secret' => $val('meta_secret')]);
            }
            if ($ch === 'tg') {
                $t = $val('tg_token') ?: wfai_ch_opt('tg_token');
                $r = wfai_tg_connect($t);
                if (is_wp_error($r)) {
                    return new WP_Error('tg', $r->get_error_message(), ['status' => 400]);
                }
            } elseif ($ch === 'wa') {
                $tok = $val('wa_token') ?: wfai_ch_opt('wa_token');
                $pid = preg_replace('/\D/', '', $val('wa_phone_id') ?: wfai_ch_opt('wa_phone_id'));
                $r = wfai_meta_api('GET', $pid . '?fields=display_phone_number,verified_name', $tok);
                if (is_wp_error($r)) {
                    return new WP_Error('wa', 'WhatsApp не принял данные: ' . $r->get_error_message(), ['status' => 400]);
                }
                wfai_ch_save(['wa_token' => $tok, 'wa_phone_id' => $pid, 'wa_phone' => (string) ($r['display_phone_number'] ?? '')]);
            } elseif ($ch === 'ig') {
                $tok = $val('ig_token') ?: wfai_ch_opt('ig_token');
                wfai_ch_save(['ig_token' => $tok]);
                $r = wfai_meta_api('GET', 'me?fields=id,username,user_id', $tok, null, wfai_ig_host());
                if (is_wp_error($r)) {
                    wfai_ch_save(['ig_token' => '']);
                    return new WP_Error('ig', 'Instagram не принял токен: ' . $r->get_error_message(), ['status' => 400]);
                }
                wfai_ch_save(['ig_id' => (string) ($r['user_id'] ?? $r['id'] ?? ''), 'ig_user' => (string) ($r['username'] ?? '')]);
            }
            return wfai_ch_state();
        }],
    ]);
});

/** Что показывать в админке: подключено ли, куда вставлять адрес вебхука и проверочный ключ. */
function wfai_ch_state() {
    $o = wfai_ch_opts();
    return [
        'tg' => ['on' => wfai_ch_on('tg'), 'name' => $o['tg_bot'] ? '@' . $o['tg_bot'] : '', 'health' => wfai_ch_on('tg') ? wfai_tg_health() : null],
        'wa' => ['on' => wfai_ch_on('wa'), 'name' => $o['wa_phone']],
        'ig' => ['on' => wfai_ch_on('ig'), 'name' => $o['ig_user'] ? '@' . $o['ig_user'] : ''],
        'meta_webhook' => wfai_ch_webhook('meta'), 'meta_verify' => wfai_ch_secret('meta_verify'), 'meta_secret_set' => $o['meta_secret'] !== '',
        'https' => strpos(home_url('/'), 'https://') === 0,
    ];
}
