<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ЯДРО: настройки, адрес посетителя, журнал событий, блокировки, ответ «доступ ограничен»
   ============================================================ */

function pgs_cfg() {
    static $c = null;
    if ($c === null) {
        $o = get_option('pgs_cfg', array());
        $c = array_merge(array(
            'mode' => 'protect',      /* protect — блокировать; watch — только записывать */
            'login_max' => 10,        /* неудачных входов с одного адреса … */
            'login_win' => 15,        /* … за столько минут → вход закрыт */
            'block_min' => 60,        /* на сколько минут закрываем адрес */
            'strikes' => 3,           /* атак с адреса за 10 минут → закрыт весь сайт */
            'flood404' => 40,         /* страниц «не найдено» за 5 минут */
            'ajax_max' => 400,        /* запросов гостя к admin-ajax за 5 минут */
            'xmlrpc' => '1', 'headers' => '1', 'notify' => '1', 'digest' => '1',
            'allow' => '',            /* свои адреса (офис): по одному в строке, можно 1.2.3.0/24 */
        ), is_array($o) ? $o : array());
    }
    return $c;
}

/* адрес посетителя: как в «PetGo Кабинет» (за Cloudflare/прокси — только если включено «Сайт за прокси») */
function pgs_ip() {
    static $ip = null;
    if ($ip !== null) {
        return $ip;
    }
    if (function_exists('petgo_ip')) {
        $ip = (string) petgo_ip();
        return $ip;
    }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    if (get_option('petgo_trust_proxy')) {
        foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR') as $h) {
            if (!empty($_SERVER[$h])) {
                $x = trim(explode(',', (string) $_SERVER[$h])[0]);
                if (filter_var($x, FILTER_VALIDATE_IP)) {
                    $ip = $x;
                    break;
                }
            }
        }
    }
    return $ip;
}

/* внутренние адреса (сам сервер, прокси хостинга) никогда не блокируем — только записываем */
function pgs_private($ip) {
    return $ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function pgs_ip_in($ip, $rule) {
    $rule = trim($rule);
    if ($rule === '' || $ip === '') {
        return false;
    }
    if (strpos($rule, '/') === false) {
        return $ip === $rule;
    }
    list($net, $bits) = explode('/', $rule, 2);
    $a = @inet_pton($ip);
    $b = @inet_pton($net);
    $bits = (int) $bits;
    if ($a === false || $b === false || strlen($a) !== strlen($b) || $bits < 0 || $bits > strlen($a) * 8) {
        return false;
    }
    $bytes = intdiv($bits, 8);
    $rest  = $bits % 8;
    if ($bytes && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
        return false;
    }
    if ($rest) {
        $m = chr((0xff << (8 - $rest)) & 0xff);
        return (ord($a[$bytes]) & ord($m)) === (ord($b[$bytes]) & ord($m));
    }
    return true;
}

function pgs_allowed($ip) {
    foreach (preg_split('/[\s,]+/', (string) pgs_cfg()['allow']) as $r) {
        if (pgs_ip_in($ip, $r)) {
            return true;
        }
    }
    return false;
}

/* адрес для показа: 203.0.113.45 → 203.0.113.45 (сотрудникам виден полностью, в уведомлениях — с маской) */
function pgs_ip_mask($ip) {
    if (strpos($ip, ':') !== false) {
        return implode(':', array_slice(explode(':', $ip), 0, 3)) . ':…';
    }
    $p = explode('.', $ip);
    return count($p) === 4 ? $p[0] . '.' . $p[1] . '.' . $p[2] . '.×' : $ip;
}

/* ---------- журнал ---------- */
function pgs_tb() {
    global $wpdb;
    return $wpdb->prefix . 'petgo_shield_log';
}

function pgs_install_db() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta('CREATE TABLE ' . pgs_tb() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ts int(10) unsigned NOT NULL DEFAULT 0,
  ip varchar(45) NOT NULL DEFAULT '',
  kind varchar(16) NOT NULL DEFAULT '',
  sev tinyint(3) unsigned NOT NULL DEFAULT 0,
  path varchar(255) NOT NULL DEFAULT '',
  info varchar(255) NOT NULL DEFAULT '',
  ua varchar(190) NOT NULL DEFAULT '',
  uid bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY ts (ts),
  KEY kind (kind, ts),
  KEY ip (ip, ts)
) " . $wpdb->get_charset_collate() . ';');
    update_option('pgs_db', PGS_VER, false);
}

/* виды событий: что это и насколько серьёзно */
function pgs_kinds() {
    return array(
        'waf' => array('Атака на сайт', 'SQL-инъекция, XSS, подмена путей, вредный код в запросе'),
        'scan' => array('Поиск уязвимостей', 'Запросы к .env, .git, бэкапам, phpMyAdmin и т. п.'),
        'bot' => array('Вредный бот', 'Сканеры и программы для взлома (sqlmap, wpscan…)'),
        'login_fail' => array('Неверный пароль', 'Неудачный вход в приложение, кабинет или админку'),
        'brute' => array('Подбор пароля', 'Много неудачных входов — вход для адреса закрыт'),
        'flood' => array('Поток запросов', 'Слишком много запросов или страниц «не найдено»'),
        'xmlrpc' => array('XML-RPC', 'Старый вход WordPress, через который подбирают пароли'),
        'block' => array('Адрес заблокирован', 'Адрес закрыт на время'),
        'files' => array('Изменены файлы', 'Файлы плагинов PetGo или WordPress изменились без обновления'),
        'malware' => array('Подозрительный файл', 'PHP-файл в папке загрузок'),
        'device' => array('Новое устройство', 'Сотрудник вошёл с нового телефона или компьютера'),
        'login' => array('Вход сотрудника', 'Успешный вход в приложение'),
        'admin' => array('Действие защиты', 'Изменение настроек, разблокировка, проверка'),
    );
}

function pgs_log($kind, $sev, $info, $path = null, $ip = null) {
    global $wpdb;
    /* не больше 4000 записей в сутки на один вид — при сильной атаке журнал не раздувается (счётчик идёт дальше) */
    $day = 'pgs_lc_' . $kind . '_' . gmdate('Ymd');
    $n   = (int) get_transient($day);
    set_transient($day, $n + 1, DAY_IN_SECONDS);
    if ($n >= 4000) {
        return 0;
    }
    if ($path === null) {
        $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    }
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';
    $wpdb->insert(pgs_tb(), array(
        'ts' => time(), 'ip' => mb_substr($ip === null ? pgs_ip() : $ip, 0, 45), 'kind' => $kind, 'sev' => (int) $sev,
        'path' => mb_substr(pgs_clean($path), 0, 250), 'info' => mb_substr(pgs_clean($info), 0, 250), 'ua' => mb_substr(pgs_clean($ua), 0, 180),
        'uid' => function_exists('get_current_user_id') && did_action('plugins_loaded') ? (int) get_current_user_id() : 0,
    ));
    return (int) $wpdb->insert_id;
}

/* в журнал — только видимые символы, без управляющих */
function pgs_clean($s) {
    $s = (string) $s;
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    return preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);
}

/* ---------- счётчики (кэш объектов, если есть; иначе временные записи WordPress) ---------- */
function pgs_count($key, $ttl, $add = 1) {
    $k = 'pgs_c_' . md5($key);
    if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
        $v = wp_cache_get($k, 'pgs');
        if ($v === false) {
            wp_cache_set($k, $add, 'pgs', $ttl);
            return $add;
        }
        $v = (int) $v + $add;
        wp_cache_set($k, $v, 'pgs', $ttl);
        return $v;
    }
    $v = get_transient($k);
    if ($v === false) {
        set_transient($k, $add, $ttl);
        return $add;
    }
    $v = (int) $v + $add;
    set_transient($k, $v, $ttl);
    return $v;
}

/* ---------- блокировки: адрес → до какого времени, весь сайт или только вход ---------- */
function pgs_blocks($clean = true) {
    $b = get_option('pgs_blocks', array());
    $b = is_array($b) ? $b : array();
    if ($clean) {
        $now = time();
        foreach ($b as $ip => $x) {
            if (!is_array($x) || (int) $x['until'] < $now) {
                unset($b[$ip]);
            }
        }
    }
    return $b;
}

function pgs_blocked($ip, $scope = 'site') {
    $b = pgs_blocks();
    if (!isset($b[$ip])) {
        return false;
    }
    return $b[$ip]['scope'] === 'site' || $scope === 'login';
}

function pgs_block($ip, $min, $scope, $why, $manual = false) {
    if (!$manual && (pgs_private($ip) || pgs_allowed($ip))) {
        return false;
    }
    if (!$manual && pgs_cfg()['mode'] !== 'protect') {
        pgs_log('block', 1, 'Режим наблюдения: адрес не закрыт (' . $why . ')', null, $ip);
        return false;
    }
    $b = pgs_blocks();
    if (isset($b[$ip]) && $b[$ip]['scope'] === 'site' && $scope === 'login') {
        return true;
    }
    $b[$ip] = array('until' => time() + max(1, (int) $min) * 60, 'scope' => $scope, 'why' => $why, 'at' => time(), 'manual' => $manual ? 1 : 0);
    /* не больше 2000 адресов: старые уходят первыми */
    if (count($b) > 2000) {
        uasort($b, function ($x, $y) {
            return $x['at'] - $y['at'];
        });
        $b = array_slice($b, -2000, null, true);
    }
    update_option('pgs_blocks', $b, true);
    $id = pgs_log('block', 2, ($scope === 'site' ? 'Закрыт весь сайт' : 'Закрыт вход') . ' на ' . (int) $min . ' мин: ' . $why, null, $ip);
    if (!$manual) {
        pgs_alert('block', 'Защита: адрес заблокирован', pgs_ip_mask($ip) . ' · ' . $why . ' · ' . ($scope === 'site' ? 'весь сайт' : 'вход') . ' на ' . (int) $min . ' мин');
    }
    do_action('pgs_blocked', $ip, $scope, $why, $id);
    return true;
}

function pgs_unblock($ip) {
    $b = pgs_blocks(false);
    unset($b[$ip]);
    update_option('pgs_blocks', $b, true);
}

/* «удар»: атака с адреса; несколько подряд — адрес закрыт для всего сайта */
function pgs_strike($ip, $why) {
    $n = pgs_count('strike' . $ip, 10 * MINUTE_IN_SECONDS);
    if ($n >= max(1, (int) pgs_cfg()['strikes'])) {
        pgs_block($ip, (int) pgs_cfg()['block_min'], 'site', $why);
    }
    return $n;
}

/* ---------- ответ «доступ временно ограничен» ---------- */
function pgs_deny($why, $id = 0) {
    if (!headers_sent()) {
        status_header(403);
        nocache_headers();
        header('X-PetGo-Shield: ' . preg_replace('/[^a-z_]/', '', $why));
    }
    $ref  = $id ? 'PGS-' . $id : 'PGS-' . strtoupper(substr(md5(pgs_ip() . gmdate('YmdH')), 0, 6));
    $ajax = (defined('DOING_AJAX') && DOING_AJAX) || (isset($_SERVER['HTTP_ACCEPT']) && strpos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false) || (isset($_SERVER['REQUEST_URI']) && strpos((string) $_SERVER['REQUEST_URI'], '/wp-json/') !== false) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']));
    $msg  = $why === 'login' ? 'Слишком много попыток входа. Подождите и попробуйте позже или напишите менеджеру.' : 'Доступ временно ограничен системой защиты. Если это ошибка — напишите нам и назовите код ' . $ref . '.';
    if ($ajax) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo wp_json_encode(array('ok' => false, 'success' => false, 'msg' => $msg, 'code' => $ref)); // phpcs:ignore
        exit;
    }
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Доступ ограничен — PetGo</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#F2F0E3;color:#10302C;font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}main{max-width:420px;margin:24px;padding:28px 24px;border-radius:24px;background:#FFFEF8;text-align:center;box-shadow:0 10px 40px rgba(16,48,44,.08)}i{display:grid;place-items:center;width:64px;height:64px;margin:0 auto 14px;border-radius:50%;background:#024C46;color:#fff;font-style:normal;font-size:30px}h1{margin:0 0 8px;font-size:22px}p{margin:0 0 6px;color:#4c625e}small{color:#8a9a97}@media(prefers-color-scheme:dark){body{background:#000;color:#F2F2F7}main{background:#1C1C1E}p{color:#a0a0a8}}</style></head><body><main><i>🛡</i><h1>Доступ временно ограничен</h1><p>' . esc_html($msg) . '</p><p>Қолжетімділік уақытша шектелген. Access is temporarily limited.</p><small>' . esc_html($ref) . '</small></main></body></html>';
    exit;
}

/* ---------- уведомления сотрудникам (владелец и администраторы), не чаще раза в 30 минут на вид ----------
   Атакующий запрос отклоняется очень рано, когда ссылки на страницы ещё не строятся, — поэтому уведомление
   ставится в очередь и уходит при следующем обычном запросе (или через минуту по расписанию). */
function pgs_alert($kind, $title, $body, $force = false) {
    if (pgs_cfg()['notify'] !== '1') {
        return;
    }
    $k = 'pgs_al_' . $kind;
    if (!$force && get_transient($k)) {
        return;
    }
    set_transient($k, 1, 30 * MINUTE_IN_SECONDS);
    $q   = get_option('pgs_q', array());
    $q   = is_array($q) ? $q : array();
    $q[] = array($kind, $title, $body, time());
    update_option('pgs_q', array_slice($q, -20), true);
    if (did_action('init')) {
        pgs_flush();
    } elseif (function_exists('wp_next_scheduled') && !wp_next_scheduled('pgs_flush')) {
        wp_schedule_single_event(time() + 60, 'pgs_flush');
    }
}

function pgs_flush() {
    $q = get_option('pgs_q', array());
    if (!is_array($q) || !$q) {
        return;
    }
    update_option('pgs_q', array(), true);
    if (!function_exists('petgo_push_notify') || !function_exists('petgo_push_users')) {
        return;
    }
    try {
        $url = function_exists('petgo_page_url') ? petgo_page_url(array('pg' => 'shield')) : '';
        $to  = petgo_push_users(array('owner', 'admin'));
        foreach ($q as $a) {
            petgo_push_notify($to, $a[1], $a[2], $url, 'pgs-' . $a[0]);
        }
    } catch (Throwable $e) {
        return;
    }
}
add_action('pgs_flush', 'pgs_flush');
add_action('init', function () {
    $q = get_option('pgs_q', array());
    if (is_array($q) && $q) {
        pgs_flush();
    }
}, 99);

/* сотрудник PetGo (а не клиент и не гость) */
function pgs_is_staff() {
    if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
        return false;
    }
    if (current_user_can('manage_options')) {
        return true;
    }
    if (function_exists('petgo_role_of')) {
        return in_array(petgo_role_of(), array('owner', 'admin', 'manager', 'nanny'), true);
    }
    $u = wp_get_current_user();
    foreach ((array) $u->roles as $r) {
        if (strpos($r, 'petgo_') === 0 && $r !== 'petgo_client') {
            return true;
        }
    }
    return false;
}

/* ---------- установка ---------- */
function pgs_activate() {
    pgs_install_db();
    if (function_exists('pgs_uploads_guard')) {
        pgs_uploads_guard(true);
    }
    if (function_exists('pgs_baseline')) {
        pgs_baseline();
    }
    if (function_exists('pgs_schedule')) {
        pgs_schedule();
    }
}

function pgs_deactivate() {
    wp_clear_scheduled_hook('pgs_daily');
    wp_clear_scheduled_hook('pgs_digest');
    if (function_exists('pgs_uploads_guard')) {
        pgs_uploads_guard(false);
    }
}

add_action('plugins_loaded', function () {
    if (get_option('pgs_db') !== PGS_VER) {
        pgs_install_db();
    }
}, 2);
