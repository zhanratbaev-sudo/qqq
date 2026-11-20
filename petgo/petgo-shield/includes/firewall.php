<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ФИЛЬТР АТАК: проверяет каждый запрос к сайту, кабинету и приложению до того, как он дойдёт до PetGo.
   Атака → запрос отклонён (403) и записан в журнал; несколько атак с адреса → адрес закрыт на время.
   Правила осторожные: обычные сообщения, анкеты и заказы под них не попадают.
   ============================================================ */

function pgs_rules() {
    return array(
        'sqli' => array('SQL-инъекция', array(
            '/\bunion\b(?:\s|\+|\(|\/\*.*?\*\/)+(?:all\s+|distinct\s+)?select\b/is',
            '/\bselect\b[\s\S]{1,120}\bfrom\b[\s\S]{0,60}\b(?:information_schema|mysql\.user|pg_catalog|sysobjects|sqlite_master)\b/i',
            '/\b(?:sleep|benchmark|pg_sleep)\s*\(\s*\d/i',
            '/\bwaitfor\s+delay\s+[\'"]/i',
            '/[\'"`]\s*(?:or|and|\|\||&&)\s+[\'"`]?(\d+|[a-z])[\'"`]?\s*=\s*[\'"`]?\1\b/i',
            '/\b(?:load_file|extractvalue|updatexml|make_set|exp\s*\(\s*~)\s*\(/i',
            '/\binto\s+(?:out|dump)file\b/i',
            '/;\s*(?:drop|truncate|alter)\s+table\b/i',
            '/\b(?:concat|concat_ws|group_concat)\s*\([^)]{0,80}(?:0x[0-9a-f]{4,}|char\s*\()/i',
        )),
        'xss' => array('Вредный скрипт (XSS)', array(
            '/<\s*script\b/i',
            '/<\s*(?:iframe|object|embed|applet|frameset)\b/i',
            '/<\s*(?:meta\s+http-equiv|base\s+href|link\s+rel\s*=\s*["\']?import)/i',
            '/<[^>]{0,250}\bon(?:error|load|mouseover|mouseenter|focus|blur|click|animationstart|transitionend|toggle|begin|pointer\w+)\s*=/i',
            '/\bjavascript\s*:\s*[a-z(\/]/i',
            '/\bvbscript\s*:/i',
            '/\bdocument\s*\.\s*(?:cookie|domain|write)\b/i',
            '/\b(?:alert|prompt|confirm)\s*\(\s*(?:document|window|1\)|[\'"]xss)/i',
        )),
        'path' => array('Подмена пути к файлу', array(
            '/(?:\.\.[\/\\\\]){2,}/',
            '/\/etc\/(?:passwd|shadow|hosts|group)\b/i',
            '/\b(?:php|zip|phar|expect|glob|file|gopher|dict):\/\//i',
            '/\bproc\/self\/(?:environ|cmdline|fd)\b/i',
            '/(?:^|[\/\\\\=])wp-config\.php/i',
            '/\bc:\\\\windows\\\\/i',
        )),
        'rce' => array('Вредный код', array(
            '/<\?(?:php\b|=)/i',
            '/\b(?:eval|assert|system|shell_exec|passthru|popen|proc_open|pcntl_exec|create_function)\s*\(/i',
            '/\bbase64_decode\s*\(/i',
            '/\$\{(?:jndi|env|sys|java):/i',
            '/\b(?:wget|curl)\s+(?:-\S+\s+)*https?:\/\/\S+\s*(?:\||;|&&|>)/i',
            '/\{\{\s*(?:7\s*\*\s*7|config|self\.__)/i',
        )),
    );
}

/* поиск уязвимостей: адреса, к которым обычный посетитель не обращается */
function pgs_probe_re() {
    return '#/(?:\.env(?:\.[a-z]+)?$|\.git(?:/|$)|\.svn/|\.hg/|\.htpasswd|\.htaccess$|\.ds_store|\.aws/|\.ssh/|id_rsa|wp-config\.php.+|wp-config\.(?:bak|old|txt|save|orig)|[^/]+\.(?:sql|bak|old|swp|save|orig)(?:\.gz|\.zip)?$|backup\.(?:zip|tar|tgz|gz|sql)$|phpinfo\.php|php-info\.php|info\.php$|phpmyadmin|/pma/|adminer(?:-[\d.]+)?\.php|myadmin|vendor/phpunit|eval-stdin\.php|cgi-bin/|server-status|wp-admin/(?:install|setup-config)\.php|xmlrpc\.php\?rsd|telescope/requests|actuator/|\.vscode/|config\.(?:json|yml|yaml)$|docker-compose|_profiler/)#i';
}

function pgs_bot_re() {
    return '/(?:sqlmap|nikto|nmap|masscan|zgrab|wpscan|acunetix|nessus|openvas|netsparker|nuclei|gobuster|dirbuster|\bdirb\b|ffuf|feroxbuster|hydra|w3af|havij|jaeles|whatweb|xsstrike|commix|arachni|skipfish|fimap|wfuzz|zmeu|morfeus|jorgee|python-requests\/[\d.]+ ?\(sqli|libwww-perl\/.*(?:sql|scan))/i';
}

/* поля, которые не проверяем: пароли (могут быть любыми), подписи и файлы в base64 */
function pgs_skip_keys() {
    return array('pass', 'pwd', 'password', 'pass1', 'pass2', 'old', 'new', 'user_pass', 'cms', 'b64', '_wpnonce', 'n', 'nonce', 'log');
}

function pgs_decode($v) {
    $d = (string) $v;
    for ($i = 0; $i < 3; $i++) {
        $n = rawurldecode($d);
        if ($n === $d) {
            break;
        }
        $d = $n;
    }
    $d = html_entity_decode($d, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $d = str_replace(array("\0", '\\x00'), '', $d);
    return preg_replace('#/\*.*?\*/#s', ' ', $d);
}

/* одно значение → [правило, название, отрывок] или null */
function pgs_check_value($v) {
    if (!is_string($v) || $v === '' || strlen($v) > 40000) {
        return null;
    }
    /* только буквы, цифры и пробелы — проверять нечего */
    if (!preg_match('/[<>\'"`;(){}\/\\\\%&=$:.|]/', $v)) {
        return null;
    }
    $d = pgs_decode($v);
    foreach (pgs_rules() as $k => $r) {
        foreach ($r[1] as $re) {
            if (@preg_match($re, $d, $m, PREG_OFFSET_CAPTURE)) {
                $at = max(0, $m[0][1] - 20);
                return array($k, $r[0], mb_substr(substr($d, $at, 120), 0, 90));
            }
        }
    }
    return null;
}

function pgs_check_array($a, $depth = 0, &$n = 0) {
    if ($depth > 6) {
        return null;
    }
    foreach ((array) $a as $k => $v) {
        if (++$n > 400) {
            return null;
        }
        if (in_array(strtolower((string) $k), pgs_skip_keys(), true)) {
            continue;
        }
        /* имя поля тоже может быть атакой */
        if (is_string($k) && strlen($k) > 3 && ($h = pgs_check_value($k))) {
            return $h;
        }
        $h = is_array($v) ? pgs_check_array($v, $depth + 1, $n) : pgs_check_value(is_scalar($v) ? (string) $v : '');
        if ($h) {
            return $h;
        }
    }
    return null;
}

function pgs_path() {
    $u = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
    $p = (string) parse_url($u, PHP_URL_PATH);
    return strtolower(rawurldecode($p !== '' ? $p : '/'));
}

/* вход: страница входа WordPress, вход и регистрация в кабинете клиента */
function pgs_is_login_req() {
    $p = pgs_path();
    $m = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
    if ($m === 'POST' && substr($p, -13) === '/wp-login.php') {
        return true;
    }
    if (substr($p, -15) === '/admin-ajax.php' && isset($_REQUEST['action']) && in_array((string) $_REQUEST['action'], array('petgo_cl_login', 'petgo_cl_reg', 'petgo_cl_forgot'), true)) {
        return true;
    }
    return false;
}

/* атака найдена: записать; в режиме защиты — отклонить и посчитать «удар» */
function pgs_hit($kind, $info, $strike = true) {
    $ip = pgs_ip();
    $id = pgs_log($kind, 2, $info);
    if (pgs_cfg()['mode'] !== 'protect' || pgs_private($ip)) {
        return;
    }
    if ($strike) {
        pgs_strike($ip, pgs_kinds()[$kind][0]);
    }
    pgs_alert('attack', 'Защита: отражена атака', pgs_kinds()[$kind][0] . ' · ' . pgs_ip_mask($ip) . ' · ' . mb_substr($info, 0, 80));
    pgs_deny($kind, $id);
}

add_action('plugins_loaded', 'pgs_firewall', 1);
function pgs_firewall() {
    if ((defined('WP_CLI') && WP_CLI) || (defined('DOING_CRON') && DOING_CRON) || PHP_SAPI === 'cli') {
        return;
    }
    $ip = pgs_ip();
    if ($ip === '' || pgs_allowed($ip)) {
        return;
    }
    $path = pgs_path();
    if (substr($path, -12) === '/wp-cron.php') {
        return;
    }
    /* 1. адрес закрыт */
    if (pgs_blocked($ip, 'site')) {
        pgs_count('blockedhits' . gmdate('Ymd'), DAY_IN_SECONDS);
        pgs_deny('blocked');
    }
    if (pgs_is_login_req() && pgs_blocked($ip, 'login')) {
        pgs_deny('login');
    }
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    /* 2. программы для взлома */
    if ($ua !== '' && preg_match(pgs_bot_re(), $ua, $m)) {
        pgs_hit('bot', 'Программа: ' . $m[0]);
    }
    /* 3. поиск уязвимостей */
    if (preg_match(pgs_probe_re(), $path, $m)) {
        pgs_hit('scan', 'Искали: ' . $m[0]);
    }
    /* 4. XML-RPC: PetGo его не использует, через него подбирают пароли */
    if (pgs_cfg()['xmlrpc'] === '1' && substr($path, -11) === '/xmlrpc.php') {
        pgs_log('xmlrpc', 1, 'Запрос к xmlrpc.php');
        if (pgs_count('xr' . $ip, 10 * MINUTE_IN_SECONDS) >= 5) {
            pgs_block($ip, (int) pgs_cfg()['block_min'], 'site', 'XML-RPC');
        }
        pgs_deny('xmlrpc');
    }
    /* 5. содержимое запроса: адрес, параметры, формы (у сотрудников формы не проверяем — у них свои права) */
    $q = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '';
    $h = $q !== '' ? pgs_check_value($q) : null;
    if (!$h) {
        $h = pgs_check_array($_GET);
    }
    $staff = pgs_is_staff();
    if (!$h && !$staff && !empty($_POST)) {
        $h = pgs_check_array($_POST);
    }
    /* JSON-запросы к REST от гостей и клиентов */
    if (!$h && !$staff && isset($_SERVER['CONTENT_TYPE']) && stripos((string) $_SERVER['CONTENT_TYPE'], 'json') !== false && strpos($path, '/wp-json/') !== false) {
        $raw = file_get_contents('php://input', false, null, 0, 40000);
        $j   = json_decode((string) $raw, true);
        $h   = is_array($j) ? pgs_check_array($j) : pgs_check_value((string) $raw);
    }
    if ($h) {
        pgs_hit('waf', $h[1] . ': ' . $h[2]);
    }
    /* 6. поток запросов гостя к admin-ajax (вход, регистрация, формы) */
    if (substr($path, -15) === '/admin-ajax.php' && !is_user_logged_in()) {
        if (pgs_count('aj' . $ip, 5 * MINUTE_IN_SECONDS) > max(60, (int) pgs_cfg()['ajax_max'])) {
            pgs_log('flood', 2, 'Больше ' . (int) pgs_cfg()['ajax_max'] . ' запросов за 5 минут');
            pgs_block($ip, 15, 'site', 'Поток запросов');
            pgs_deny('flood');
        }
    }
}

/* страницы «не найдено» подряд — так ищут уязвимости */
add_action('template_redirect', function () {
    if (!is_404() || is_user_logged_in()) {
        return;
    }
    $ip = pgs_ip();
    if (pgs_allowed($ip)) {
        return;
    }
    $n = pgs_count('nf' . $ip, 5 * MINUTE_IN_SECONDS);
    $lim = max(10, (int) pgs_cfg()['flood404']);
    if ($n === $lim) {
        pgs_log('flood', 2, $lim . ' страниц «не найдено» за 5 минут');
        pgs_block($ip, (int) pgs_cfg()['block_min'], 'site', 'Перебор адресов (404)');
    }
}, 1);

/* ---------- подбор паролей: вход в приложение, кабинет клиента, админку ---------- */
add_filter('authenticate', function ($user) {
    $ip = pgs_ip();
    if (!pgs_allowed($ip) && pgs_blocked($ip, 'login')) {
        return new WP_Error('petgo_locked', 'Слишком много попыток входа. Подождите и попробуйте позже.');
    }
    return $user;
}, 99);

add_action('wp_login_failed', function ($login, $err = null) {
    /* считаем и попытки, которые уже отклонил «Кабинет» (5 с адреса, 8 на логин), — это тоже подбор */
    $ip = pgs_ip();
    $u  = get_user_by('login', (string) $login);
    if (!$u && is_email((string) $login)) {
        $u = get_user_by('email', (string) $login);
    }
    $who = $u ? $u->user_login : (mb_strlen((string) $login) > 3 ? mb_substr((string) $login, 0, 2) . '***' : '***');
    pgs_log('login_fail', 1, 'Логин: ' . $who . ($u ? '' : ' (нет такого)'));
    $n   = pgs_count('lf' . $ip, max(1, (int) pgs_cfg()['login_win']) * MINUTE_IN_SECONDS);
    if ($n >= max(3, (int) pgs_cfg()['login_max'])) {
        pgs_log('brute', 2, $n . ' неудачных входов за ' . (int) pgs_cfg()['login_win'] . ' мин');
        pgs_block($ip, (int) pgs_cfg()['block_min'], 'login', 'Подбор пароля');
    }
}, 10, 2);
