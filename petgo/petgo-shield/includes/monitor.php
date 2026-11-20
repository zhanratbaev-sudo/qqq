<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   НАБЛЮДЕНИЕ: файлы не изменены, в загрузках нет скриптов, сотрудники входят со своих устройств,
   каждый день — отчёт в приложение.
   ============================================================ */

function pgs_schedule() {
    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('Asia/Almaty');
    $at = function ($h, $m) use ($tz) {
        $d = new DateTime('now', $tz);
        $d->setTime($h, $m, 0);
        if ($d->getTimestamp() <= time()) {
            $d->modify('+1 day');
        }
        return $d->getTimestamp();
    };
    if (!wp_next_scheduled('pgs_daily')) {
        wp_schedule_event($at(3, 17), 'daily', 'pgs_daily');
    }
    if (!wp_next_scheduled('pgs_digest')) {
        wp_schedule_event($at(9, 7), 'daily', 'pgs_digest');
    }
}
add_action('init', function () {
    if (!wp_next_scheduled('pgs_daily') || !wp_next_scheduled('pgs_digest')) {
        pgs_schedule();
    }
    /* первый запуск после установки без активации (например, файлы залиты по FTP) */
    if (!get_option('pgs_base')) {
        pgs_baseline();
    }
});

/* ---------- файлы: плагины PetGo, wp-config.php, .htaccess, index.php ---------- */
function pgs_watch_list() {
    $out = array();
    foreach (glob(WP_PLUGIN_DIR . '/petgo-*', GLOB_ONLYDIR) ?: array() as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        $n  = 0;
        foreach ($it as $f) {
            if (++$n > 3000) {
                break;
            }
            if ($f->isFile() && preg_match('/\.(?:php|js|css|html?|json|htaccess)$/i', $f->getFilename())) {
                $out[] = wp_normalize_path($f->getPathname());
            }
        }
    }
    foreach (array('wp-config.php', '.htaccess', 'index.php', 'wp-login.php', 'wp-settings.php') as $x) {
        if (is_file(ABSPATH . $x)) {
            $out[] = wp_normalize_path(ABSPATH . $x);
        }
    }
    if (is_file(dirname(ABSPATH) . '/wp-config.php')) {
        $out[] = wp_normalize_path(dirname(ABSPATH) . '/wp-config.php');
    }
    return $out;
}

function pgs_rel($p) {
    return ltrim(str_replace(wp_normalize_path(ABSPATH), '', wp_normalize_path($p)), '/');
}

/* версии плагинов PetGo — обновление плагина меняет его файлы законно */
function pgs_plugin_vers() {
    $v = array();
    foreach (glob(WP_PLUGIN_DIR . '/petgo-*', GLOB_ONLYDIR) ?: array() as $dir) {
        foreach (glob($dir . '/*.php') ?: array() as $f) {
            $h = get_file_data($f, array('v' => 'Version', 'n' => 'Plugin Name'));
            if (!empty($h['n'])) {
                $v[basename($dir)] = (string) $h['v'];
            }
        }
    }
    return $v;
}

function pgs_hashes() {
    $h = array();
    foreach (pgs_watch_list() as $f) {
        $x = @sha1_file($f);
        if ($x) {
            $h[pgs_rel($f)] = $x;
        }
    }
    return $h;
}

/* «эталон»: текущие файлы считаются правильными */
function pgs_baseline() {
    update_option('pgs_base', array('t' => time(), 'v' => pgs_plugin_vers(), 'f' => pgs_hashes()), false);
    delete_option('pgs_files_bad');
}

/* после обновления плагинов через админку — эталон обновляется сам */
add_action('upgrader_process_complete', function () {
    pgs_baseline();
    pgs_log('admin', 0, 'Обновлены плагины — эталон файлов обновлён');
}, 20);

function pgs_files_check() {
    $base = get_option('pgs_base');
    if (!is_array($base) || empty($base['f'])) {
        pgs_baseline();
        return array();
    }
    $vers = pgs_plugin_vers();
    $now  = pgs_hashes();
    /* плагин обновился (другая версия) — его файлы принимаем */
    $upd = array();
    foreach ($vers as $pl => $v) {
        if (!isset($base['v'][$pl]) || $base['v'][$pl] !== $v) {
            $upd[] = $pl;
        }
    }
    $legit = function ($rel) use ($upd) {
        foreach ($upd as $pl) {
            if (strpos($rel, 'wp-content/plugins/' . $pl . '/') !== false || strpos($rel, '/plugins/' . $pl . '/') !== false) {
                return true;
            }
        }
        return false;
    };
    $chg = array();
    foreach ($now as $rel => $h) {
        if (!isset($base['f'][$rel])) {
            if (!$legit($rel)) {
                $chg[$rel] = 'новый';
            }
        } elseif ($base['f'][$rel] !== $h && !$legit($rel)) {
            $chg[$rel] = 'изменён';
        }
    }
    foreach ($base['f'] as $rel => $h) {
        if (!isset($now[$rel]) && !$legit($rel)) {
            $chg[$rel] = 'удалён';
        }
    }
    if ($upd) {
        $base['v'] = $vers;
        foreach ($now as $rel => $h) {
            if ($legit($rel)) {
                $base['f'][$rel] = $h;
            }
        }
        foreach (array_keys($base['f']) as $rel) {
            if (!isset($now[$rel]) && $legit($rel)) {
                unset($base['f'][$rel]);
            }
        }
        update_option('pgs_base', $base, false);
        pgs_log('admin', 0, 'Обновлены: ' . implode(', ', $upd) . ' — эталон файлов обновлён');
    }
    $old = get_option('pgs_files_bad', array());
    update_option('pgs_files_bad', $chg, false);
    if ($chg && array_keys($chg) !== array_keys((array) $old)) {
        $list = array();
        foreach (array_slice($chg, 0, 6, true) as $f => $w) {
            $list[] = $f . ' (' . $w . ')';
        }
        pgs_log('files', 3, count($chg) . ' файл(ов): ' . implode('; ', $list), '');
        pgs_alert('files', 'Защита: изменены файлы сайта', count($chg) . ' файл(ов) изменились без обновления. Проверьте раздел «Защита».', true);
    }
    return $chg;
}

/* ---------- скрипты в загрузках (там должны быть только фото и документы) ---------- */
function pgs_uploads_scan() {
    $up  = wp_upload_dir(null, false);
    $bad = array();
    if (empty($up['basedir']) || !is_dir($up['basedir'])) {
        return $bad;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($up['basedir'], FilesystemIterator::SKIP_DOTS));
    $n  = 0;
    foreach ($it as $f) {
        if (++$n > 60000) {
            break;
        }
        if (!$f->isFile() || !preg_match('/\.(?:php\d?|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp)$/i', $f->getFilename())) {
            continue;
        }
        /* пустые index.php («тишина — золото») — норма */
        if ($f->getSize() < 120 && preg_match('/^<\?php\s*(?:\/\/[^\n]*)?\s*$/', (string) @file_get_contents($f->getPathname()))) {
            continue;
        }
        $bad[] = pgs_rel($f->getPathname());
        if (count($bad) >= 50) {
            break;
        }
    }
    $old = get_option('pgs_up_bad', array());
    update_option('pgs_up_bad', $bad, false);
    if ($bad && $bad !== $old) {
        pgs_log('malware', 3, count($bad) . ' скрипт(ов) в загрузках: ' . implode('; ', array_slice($bad, 0, 5)), '');
        pgs_alert('malware', 'Защита: подозрительный файл', 'В папке загрузок найден скрипт: ' . $bad[0] . '. Проверьте раздел «Защита».', true);
    }
    return $bad;
}

/* ---------- файлы самого WordPress сверяются с официальными (api.wordpress.org) ---------- */
function pgs_core_check() {
    global $wp_version;
    require_once ABSPATH . 'wp-admin/includes/update.php';
    $sums = function_exists('get_core_checksums') ? get_core_checksums($wp_version, get_locale() ? get_locale() : 'en_US') : false;
    if (!is_array($sums) || !$sums) {
        $sums = function_exists('get_core_checksums') ? get_core_checksums($wp_version, 'en_US') : false;
    }
    if (!is_array($sums) || !$sums) {
        update_option('pgs_core', array('t' => time(), 'st' => 'unknown', 'bad' => array()), false);
        return null;
    }
    $bad = array();
    foreach ($sums as $f => $md5) {
        if (strpos($f, 'wp-content/') === 0 || $f === 'wp-config-sample.php' || strpos($f, 'readme') !== false || strpos($f, 'license') !== false) {
            continue;
        }
        $p = ABSPATH . $f;
        if (is_file($p) && md5_file($p) !== $md5) {
            $bad[] = $f;
            if (count($bad) >= 40) {
                break;
            }
        }
    }
    $old = get_option('pgs_core', array());
    update_option('pgs_core', array('t' => time(), 'st' => $bad ? 'bad' : 'ok', 'bad' => $bad), false);
    if ($bad && (!is_array($old) || ($old['bad'] ?? array()) !== $bad)) {
        pgs_log('files', 3, 'Файлы WordPress отличаются от официальных: ' . implode('; ', array_slice($bad, 0, 6)), '');
        pgs_alert('core', 'Защита: изменены файлы WordPress', count($bad) . ' файл(ов) ядра отличаются от официальных. Проверьте раздел «Защита».', true);
    }
    return $bad;
}

/* полная проверка: раз в сутки ночью и по кнопке «Проверить сейчас» */
function pgs_full_check() {
    $r = array('files' => pgs_files_check(), 'uploads' => pgs_uploads_scan(), 'core' => pgs_core_check());
    $r['php_up'] = pgs_uploads_probe('php');
    update_option('pgs_probe', array('t' => time(), 'php' => $r['php_up'], 'docs' => pgs_docs_probe(), 'media' => function_exists('petgo_media_probe') ? petgo_media_probe() : ''), false);
    update_option('pgs_last_check', time(), false);
    return $r;
}

/* закрыты ли договоры и фото клиентов от прямых ссылок */
function pgs_docs_probe() {
    $up = wp_upload_dir(null, false);
    $d  = trailingslashit($up['basedir']) . 'petgo-docs';
    if (!is_dir($d)) {
        return 0;
    }
    $name = 'pgs-probe-' . wp_generate_password(8, false, false) . '.txt';
    if (@file_put_contents($d . '/' . $name, 'PGS-OPEN') === false) {
        return 0;
    }
    $r = wp_remote_get(trailingslashit($up['baseurl']) . 'petgo-docs/' . $name, array('timeout' => 8, 'sslverify' => false, 'redirection' => 0));
    @unlink($d . '/' . $name);
    if (is_wp_error($r)) {
        return 0;
    }
    return strpos((string) wp_remote_retrieve_body($r), 'PGS-OPEN') !== false ? 1 : 2;
}

add_action('pgs_daily', function () {
    pgs_full_check();
    pgs_prune();
}, 10);

function pgs_prune() {
    global $wpdb;
    $tb = pgs_tb();
    $wpdb->query($wpdb->prepare("DELETE FROM $tb WHERE ts < %d", time() - 120 * DAY_IN_SECONDS)); // phpcs:ignore
    $n = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tb"); // phpcs:ignore
    if ($n > 80000) {
        $cut = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $tb ORDER BY id DESC LIMIT 1 OFFSET %d", 80000)); // phpcs:ignore
        if ($cut) {
            $wpdb->query($wpdb->prepare("DELETE FROM $tb WHERE id <= %d", $cut)); // phpcs:ignore
        }
    }
}

/* ---------- входы сотрудников: новое устройство → уведомление владельцу ---------- */
function pgs_dev_key($ip, $ua) {
    $net = strpos($ip, ':') !== false ? implode(':', array_slice(explode(':', $ip), 0, 4)) : implode('.', array_slice(explode('.', $ip), 0, 3));
    $os  = preg_match('/(iPhone|iPad|Android|Windows|Macintosh|Linux)/i', $ua, $m) ? strtolower($m[1]) : 'other';
    $br  = preg_match('/(Edg|OPR|YaBrowser|Chrome|CriOS|Firefox|FxiOS|Safari)/', $ua, $b) ? $b[1] : 'other';
    return substr(md5($os . '|' . $br . '|' . $net), 0, 12);
}

function pgs_ua_short($ua) {
    $os = preg_match('/iPhone|iPad/i', $ua) ? 'iPhone/iPad' : (preg_match('/Android/i', $ua) ? 'Android' : (preg_match('/Windows/i', $ua) ? 'Windows' : (preg_match('/Macintosh/i', $ua) ? 'Mac' : (preg_match('/Linux/i', $ua) ? 'Linux' : 'устройство'))));
    $br = preg_match('/YaBrowser/', $ua) ? 'Яндекс Браузер' : (preg_match('/Edg/', $ua) ? 'Edge' : (preg_match('/OPR/', $ua) ? 'Opera' : (preg_match('/Firefox|FxiOS/', $ua) ? 'Firefox' : (preg_match('/Chrome|CriOS/', $ua) ? 'Chrome' : (preg_match('/Safari/', $ua) ? 'Safari' : '')))));
    return trim($os . ($br !== '' ? ' · ' . $br : ''));
}

add_action('wp_login', function ($login, $user) {
    if (!($user instanceof WP_User)) {
        return;
    }
    $staff = user_can($user, 'manage_options');
    foreach ((array) $user->roles as $r) {
        if (strpos($r, 'petgo_') === 0 && $r !== 'petgo_client') {
            $staff = true;
        }
    }
    if (!$staff) {
        return;
    }
    $ip  = pgs_ip();
    $ua  = isset($_SERVER['HTTP_USER_AGENT']) ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';
    $key = pgs_dev_key($ip, $ua);
    $dev = get_user_meta($user->ID, 'pgs_dev', true);
    $dev = is_array($dev) ? $dev : array();
    $new = !isset($dev[$key]);
    $dev[$key] = time();
    arsort($dev);
    update_user_meta($user->ID, 'pgs_dev', array_slice($dev, 0, 20, true));
    $last   = get_user_meta($user->ID, 'pgs_last', true);
    $last   = is_array($last) ? $last : array();
    array_unshift($last, array('t' => time(), 'ip' => $ip, 'ua' => pgs_ua_short($ua)));
    update_user_meta($user->ID, 'pgs_last', array_slice($last, 0, 10));
    pgs_log('login', 0, $user->display_name . ' · ' . pgs_ua_short($ua));
    /* первый вход вообще — не тревога; новое устройство после этого — тревога */
    if ($new && count($dev) > 1) {
        pgs_log('device', 1, $user->display_name . ' вошёл(ла) с нового устройства: ' . pgs_ua_short($ua));
        pgs_alert('device' . $user->ID, 'Защита: вход с нового устройства', $user->display_name . ' · ' . pgs_ua_short($ua) . ' · ' . pgs_ip_mask($ip) . '. Если это не сотрудник — завершите его сеансы в «Защите».', true);
    }
}, 20, 2);

/* ---------- отчёт за сутки: в 9 утра в приложение ---------- */
function pgs_stats($since) {
    global $wpdb;
    $tb  = pgs_tb();
    $out = array();
    foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT kind, COUNT(*) n FROM $tb WHERE ts >= %d GROUP BY kind", $since)) as $r) { // phpcs:ignore
        $out[$r->kind] = (int) $r->n;
    }
    return $out;
}

/* атаки = всё, что отражено (без обычных входов и служебных записей) */
function pgs_attack_kinds() {
    return array('waf', 'scan', 'bot', 'brute', 'flood', 'xmlrpc');
}

add_action('pgs_digest', function () {
    if (pgs_cfg()['digest'] !== '1') {
        return;
    }
    $s   = pgs_stats(time() - DAY_IN_SECONDS);
    $att = 0;
    $p   = array();
    $K   = pgs_kinds();
    foreach (pgs_attack_kinds() as $k) {
        if (!empty($s[$k])) {
            $att += $s[$k];
            $p[]  = mb_strtolower($K[$k][0]) . ' ' . $s[$k];
        }
    }
    $warn = count((array) get_option('pgs_files_bad', array())) + count((array) get_option('pgs_up_bad', array()));
    if (!$att && !$warn && empty($s['login_fail'])) {
        return;
    }
    $body = $att ? 'Отражено атак: ' . $att . ' (' . implode(', ', $p) . ').' : 'Атак не было.';
    if (!empty($s['login_fail'])) {
        $body .= ' Неверных паролей: ' . $s['login_fail'] . '.';
    }
    if ($warn) {
        $body .= ' Есть замечания — откройте «Защиту».';
    }
    pgs_alert('digest', 'Защита за сутки', $body, true);
});
