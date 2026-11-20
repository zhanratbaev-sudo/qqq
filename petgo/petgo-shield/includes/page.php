<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   РАЗДЕЛ «ЗАЩИТА» в приложении сотрудников (владелец и администраторы):
   состояние защиты, атаки по дням и видам, журнал, закрытые адреса, сеансы сотрудников, настройки, выгрузка.
   ============================================================ */

add_action('petgo_ready', function () {
    petgo_register_plugin(PGS_FILE, array('name' => 'PetGo Защита', 'version' => PGS_VER));
});

function pgs_admin() {
    return function_exists('petgo_can') && petgo_can('admin');
}

add_filter('petgo_nav_items', function ($items, $adm) {
    if ($adm && pgs_admin()) {
        $items['shield'] = array('Защита', petgo_page_url(array('pg' => 'shield')), 'shield', 'd');
    }
    return $items;
}, 10, 2);
add_filter('petgo_nav_badge', function ($n, $key) {
    if ($key !== 'shield' || !pgs_admin()) {
        return $n;
    }
    $c = pgs_check_counts();
    return $c['bad'] ? $c['bad'] : $n;
}, 10, 2);
add_filter('petgo_app_title', function ($t, $pg) {
    return $pg === 'shield' ? 'Защита' : $t;
}, 10, 2);
add_filter('petgo_settings_rows', function ($rows) {
    if (pgs_admin()) {
        $c = pgs_check_counts();
        $rows[] = array('icon' => 'shield', 'title' => 'Защита сайта', 'sub' => $c['bad'] ? 'Есть замечания: ' . $c['bad'] : 'Атаки, входы, проверка файлов, отчёты', 'url' => petgo_page_url(array('pg' => 'shield')));
    }
    return $rows;
});
add_filter('petgo_app_page', function ($done, $pg) {
    if ($done || $pg !== 'shield') {
        return $done;
    }
    pgs_page();
    return true;
}, 10, 2);

/* главная приложения: замечания или атаки за сутки — одной строкой */
add_filter('petgo_admin_home_top', function ($h) {
    if (!pgs_admin()) {
        return $h;
    }
    $c   = pgs_check_counts();
    $s   = pgs_stats(time() - DAY_IN_SECONDS);
    $att = 0;
    foreach (pgs_attack_kinds() as $k) {
        $att += isset($s[$k]) ? $s[$k] : 0;
    }
    if (!$c['bad'] && !$att) {
        return $h;
    }
    $t = $c['bad'] ? 'Защита: есть замечания (' . $c['bad'] . ')' : 'Защита: за сутки отражено ' . $att . ' ' . pgs_plural($att, 'атака', 'атаки', 'атак');
    $s = $c['bad'] ? 'Откройте, чтобы увидеть, что исправить' : 'Сайт работает, атаки заблокированы автоматически';
    return $h . '<a class="pgc-card pgs-home ' . ($c['bad'] ? 'bad' : 'ok') . '" href="' . esc_url(petgo_page_url(array('pg' => 'shield'))) . '"><span class="pgs-hi">' . petgo_icon('shield') . '</span><span class="pgs-ht"><b>' . esc_html($t) . '</b><small>' . esc_html($s) . '</small></span>' . petgo_icon('right') . '</a>'
        . '<style>.pgs-home{display:flex;align-items:center;gap:12px;margin:0 0 14px;padding:14px 16px;text-decoration:none;color:var(--tx)}.pgs-home.bad{box-shadow:inset 0 0 0 1.5px var(--er)}.pgs-hi{flex:none;width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:var(--oks);color:var(--ok)}.pgs-home.bad .pgs-hi{background:var(--ers);color:var(--er)}.pgs-hi svg{width:20px;height:20px}.pgs-ht{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}.pgs-ht small{color:var(--mu)}.pgs-home>svg:last-child{width:16px;height:16px;color:var(--mu2)}</style>';
});

function pgs_plural($n, $a, $b, $c) {
    $n = abs((int) $n) % 100;
    $m = $n % 10;
    if ($n > 10 && $n < 20) {
        return $c;
    }
    return $m === 1 ? $a : ($m >= 2 && $m <= 4 ? $b : $c);
}

/* ============================================================
   ПРОВЕРКА ЗАЩИТЫ: что включено, что нужно сделать
   ============================================================ */
function pgs_checks() {
    $c   = pgs_cfg();
    $out = array();
    $add = function ($lvl, $t, $s) use (&$out) {
        $out[] = array($lvl, $t, $s);
    };
    $add($c['mode'] === 'protect' ? 'ok' : 'warn', 'Фильтр атак', $c['mode'] === 'protect' ? 'Включён: атаки блокируются, адреса взломщиков закрываются на время.' : 'Режим наблюдения: атаки только записываются. Включите «Защищать» в настройках ниже.');
    $https = is_ssl() || strpos(home_url(), 'https://') === 0;
    $add($https ? 'ok' : 'bad', 'Шифрование (HTTPS)', $https ? 'Данные клиентов передаются в зашифрованном виде.' : 'Сайт открывается без шифрования: пароли и данные клиентов можно перехватить. Включите SSL в Plesk (Let’s Encrypt, бесплатно).');
    /* адрес посетителя: за прокси хостинга все видны с одного адреса */
    $ra  = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $fwd = !empty($_SERVER['HTTP_X_FORWARDED_FOR']) || !empty($_SERVER['HTTP_X_REAL_IP']) || !empty($_SERVER['HTTP_CF_CONNECTING_IP']);
    if ($fwd && !get_option('petgo_trust_proxy') && pgs_private($ra)) {
        $add('bad', 'Адреса посетителей', 'Сайт стоит за прокси (Cloudflare или nginx хостинга), и все посетители видны с адреса ' . $ra . '. Включите «Сайт за прокси» в настройках ниже — иначе блокировка взломщика закроет вход всем.');
    } elseif (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && !get_option('petgo_trust_proxy')) {
        $add('warn', 'Адреса посетителей', 'Сайт работает через Cloudflare. Включите «Сайт за прокси» в настройках, чтобы видеть настоящие адреса посетителей.');
    } else {
        $add('ok', 'Адреса посетителей', 'Определяются верно: блокируется только адрес взломщика.');
    }
    $add($c['xmlrpc'] === '1' ? 'ok' : 'warn', 'XML-RPC', $c['xmlrpc'] === '1' ? 'Закрыт: через него больше нельзя подбирать пароли.' : 'Открыт. PetGo он не нужен — закройте в настройках.');
    $add(defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT ? 'ok' : 'warn', 'Редактор файлов в админке', defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT ? 'Выключен: даже с украденным паролем нельзя вписать вредный код в плагины.' : 'Включён в wp-config.php.');
    $add($c['headers'] === '1' ? 'ok' : 'warn', 'Заголовки безопасности', $c['headers'] === '1' ? 'Сайт нельзя встроить в чужую страницу, браузер не подменяет типы файлов, камера и место — только для PetGo.' : 'Выключены в настройках.');
    $pr = get_option('pgs_probe', array());
    $php = is_array($pr) && isset($pr['php']) ? (int) $pr['php'] : 0;
    $rule = get_option('pgs_up_rule', '');
    if ($php === 2) {
        $add('ok', 'Скрипты в папке загрузок', 'Не запускаются: даже если вредный файл попадёт в загрузки, он не сработает.');
    } elseif ($php === 1) {
        $add('bad', 'Скрипты в папке загрузок', 'Запускаются' . ($rule === 'refused' ? ' (сервер не принял правило .htaccess)' : ' (похоже на nginx: он не читает .htaccess)') . '. Попросите хостинг: «запретить выполнение PHP в /wp-content/uploads/».');
    } else {
        $add('unk', 'Скрипты в папке загрузок', 'Ещё не проверено — нажмите «Проверить сейчас».');
    }
    $docs = is_array($pr) && isset($pr['docs']) ? (int) $pr['docs'] : 0;
    $mp   = is_array($pr) && isset($pr['media']) ? (string) $pr['media'] : '';
    if ($docs === 1 || $mp === 'open') {
        $add('bad', 'Договоры и фото клиентов', 'Открываются по прямой ссылке (nginx не читает .htaccess). Попросите хостинг добавить: location ^~ /wp-content/uploads/petgo { deny all; } и location ^~ /wp-content/uploads/petgo-docs { deny all; }');
    } elseif ($docs === 2 || $mp === 'closed') {
        $add('ok', 'Договоры и фото клиентов', 'Закрыты: открываются только через кабинет — владельцу и сотрудникам.');
    } else {
        $add('unk', 'Договоры и фото клиентов', 'Ещё не проверено — нажмите «Проверить сейчас».');
    }
    $fb = (array) get_option('pgs_files_bad', array());
    $add($fb ? 'bad' : 'ok', 'Файлы PetGo и настройки сайта', $fb ? count($fb) . ' ' . pgs_plural(count($fb), 'файл изменился', 'файла изменились', 'файлов изменились') . ' без обновления — список ниже.' : 'Не изменены с последней проверки.');
    $core = get_option('pgs_core', array());
    $cst  = is_array($core) && isset($core['st']) ? $core['st'] : '';
    $add($cst === 'ok' ? 'ok' : ($cst === 'bad' ? 'bad' : 'unk'), 'Файлы WordPress', $cst === 'ok' ? 'Совпадают с официальными.' : ($cst === 'bad' ? 'Отличаются от официальных: ' . implode(', ', array_slice((array) $core['bad'], 0, 4)) . '. Переустановите WordPress (Консоль → Обновления → «Переустановить»).' : 'Не удалось сверить (сервер WordPress недоступен) или ещё не проверено.'));
    $ub = (array) get_option('pgs_up_bad', array());
    $add($ub ? 'bad' : 'ok', 'Подозрительные файлы в загрузках', $ub ? 'Найдено: ' . implode(', ', array_slice($ub, 0, 3)) . '. Удалите их через файловый менеджер Plesk.' : 'Не найдено.');
    $uc = get_site_transient('update_core');
    $nw = '';
    if (is_object($uc) && !empty($uc->updates)) {
        foreach ($uc->updates as $u) {
            if (isset($u->response) && $u->response === 'upgrade') {
                $nw = (string) $u->current;
            }
        }
    }
    $add($nw ? 'warn' : 'ok', 'Обновления WordPress', $nw ? 'Доступна версия ' . $nw . ' — в обновлениях закрывают уязвимости. Консоль → Обновления.' : 'Установлена последняя известная версия.');
    $upl = get_site_transient('update_plugins');
    $np  = is_object($upl) && !empty($upl->response) ? count((array) $upl->response) : 0;
    if ($np) {
        $add('warn', 'Обновления других плагинов', 'Ждут обновления: ' . $np . '. Консоль → Плагины.');
    }
    $add(version_compare(PHP_VERSION, '8.1', '>=') ? 'ok' : 'warn', 'Версия PHP', 'PHP ' . PHP_VERSION . (version_compare(PHP_VERSION, '8.1', '>=') ? ' — поддерживается.' : ' — устарела, без исправлений безопасности. Выберите PHP 8.2 или новее в Plesk.'));
    $dbg = defined('WP_DEBUG') && WP_DEBUG && (!defined('WP_DEBUG_DISPLAY') || WP_DEBUG_DISPLAY);
    $add($dbg ? 'bad' : 'ok', 'Показ ошибок', $dbg ? 'Ошибки показываются посетителям (WP_DEBUG) — по ним видно устройство сайта. Выключите в wp-config.php.' : 'Ошибки посетителям не показываются.');
    $add(username_exists('admin') ? 'warn' : 'ok', 'Логин «admin»', username_exists('admin') ? 'Есть пользователь с логином admin — его пробуют первым. Создайте администратора с другим логином и удалите этот.' : 'Стандартного логина нет.');
    return $out;
}

function pgs_check_counts() {
    static $c = null;
    if ($c === null) {
        $c = array('ok' => 0, 'warn' => 0, 'bad' => 0, 'unk' => 0);
        foreach (pgs_checks() as $x) {
            $c[$x[0]]++;
        }
    }
    return $c;
}

/* ============================================================
   СТРАНИЦА
   ============================================================ */
function pgs_page() {
    global $wpdb;
    if (!pgs_admin()) {
        echo '<p class="mu">' . esc_html(petgo_t('err_perm')) . '</p>';
        return;
    }
    $tb   = pgs_tb();
    $cfg  = pgs_cfg();
    $K    = pgs_kinds();
    $AK   = pgs_attack_kinds();
    $cnt  = pgs_check_counts();
    $s1   = pgs_stats(time() - DAY_IN_SECONDS);
    $s7   = pgs_stats(time() - 7 * DAY_IN_SECONDS);
    $sum  = function ($s) use ($AK) {
        $n = 0;
        foreach ($AK as $k) {
            $n += isset($s[$k]) ? $s[$k] : 0;
        }
        return $n;
    };
    $blocks = pgs_blocks();
    $last   = (int) get_option('pgs_last_check', 0);
    $t      = function ($ts) {
        return wp_date('d.m · H:i', (int) $ts, function_exists('petgo_tz') ? petgo_tz() : wp_timezone());
    };

    echo '<style>' . pgs_css() . '</style><div class="pgs">';

    /* 1. состояние */
    $lvl = $cnt['bad'] ? 'bad' : ($cfg['mode'] !== 'protect' || $cnt['warn'] ? 'warn' : 'ok');
    $ttl = $cnt['bad'] ? 'Есть замечания: ' . $cnt['bad'] : ($cfg['mode'] !== 'protect' ? 'Режим наблюдения' : 'Сайт под защитой');
    echo '<div class="pgc-card pgs-hero ' . $lvl . '"><span class="pgs-shield">' . petgo_icon('shield') . '</span><div><b>' . esc_html($ttl) . '</b><small>' . esc_html($last ? 'Последняя проверка: ' . $t($last) : 'Полная проверка ещё не запускалась') . '</small></div>';
    echo petgo_form_open('pgs_check', 'class="pgs-inl"') . '<button type="submit" class="pgc-btn sm">Проверить сейчас</button></form></div>';

    /* 2. цифры */
    echo '<div class="pgs-tiles"><div><b>' . (int) $sum($s1) . '</b><small>атак отражено за сутки</small></div><div><b>' . (int) $sum($s7) . '</b><small>за 7 дней</small></div><div><b>' . count($blocks) . '</b><small>' . esc_html(pgs_plural(count($blocks), 'адрес закрыт', 'адреса закрыты', 'адресов закрыто')) . ' сейчас</small></div><div><b>' . (int) (isset($s1['login_fail']) ? $s1['login_fail'] : 0) . '</b><small>неверных паролей за сутки</small></div></div>';

    /* 3. график за 14 дней */
    $tz   = function_exists('petgo_tz') ? petgo_tz() : wp_timezone();
    $d0   = new DateTime('now', $tz);
    $d0->setTime(0, 0, 0);
    $from = $d0->getTimestamp() - 13 * DAY_IN_SECONDS;
    $rows = $wpdb->get_results($wpdb->prepare("SELECT ts, kind FROM $tb WHERE ts >= %d AND kind IN ('" . implode("','", array_map('esc_sql', $AK)) . "')", $from)); // phpcs:ignore
    $days = array_fill(0, 14, 0);
    foreach ((array) $rows as $r) {
        $i = (int) floor(((int) $r->ts - $from) / DAY_IN_SECONDS);
        if ($i >= 0 && $i < 14) {
            $days[$i]++;
        }
    }
    $mx = max(1, max($days));
    echo '<div class="pgc-card pgs-chart"><b>Атаки по дням</b><div class="pgs-bars">';
    foreach ($days as $i => $n) {
        $ts = $from + $i * DAY_IN_SECONDS + 3600;
        echo '<div class="pgs-bar' . ($i === 13 ? ' today' : '') . '" title="' . esc_attr(wp_date('d.m', $ts, $tz) . ': ' . $n) . '"><em>' . ($n ? (int) $n : '') . '</em><i style="height:' . ($n ? max(4, round($n / $mx * 100)) : 0) . '%"></i><span>' . esc_html(wp_date('j', $ts, $tz)) . '</span></div>';
    }
    echo '</div></div>';

    /* 4. по видам */
    echo '<div class="pgc-card"><b>За 7 дней по видам</b><div class="pgs-kinds">';
    $any = false;
    foreach ($K as $k => $kv) {
        if (empty($s7[$k]) || in_array($k, array('login', 'admin'), true)) {
            continue;
        }
        $any = true;
        echo '<a class="pgs-kind" href="' . esc_url(petgo_page_url(array('pg' => 'shield', 'k' => $k))) . '#pgs-ev"><span class="pgs-dot s' . (in_array($k, array('files', 'malware'), true) ? 3 : (in_array($k, $AK, true) ? 2 : 1)) . '"></span><span><b>' . esc_html($kv[0]) . '</b><small>' . esc_html($kv[1]) . '</small></span><em>' . (int) $s7[$k] . '</em></a>';
    }
    echo $any ? '' : '<p class="mu">Событий нет — тихая неделя.</p>';
    echo '</div></div>';

    /* 5. проверка */
    echo '<div class="pgc-card"><b>Проверка защиты</b><div class="pgs-checks">';
    $order = array('bad' => 0, 'warn' => 1, 'unk' => 2, 'ok' => 3);
    $ch    = pgs_checks();
    usort($ch, function ($a, $b) use ($order) {
        return $order[$a[0]] - $order[$b[0]];
    });
    foreach ($ch as $x) {
        $ic = $x[0] === 'ok' ? '✓' : ($x[0] === 'unk' ? '?' : '!');
        echo '<div class="pgs-chk ' . $x[0] . '"><span>' . $ic . '</span><div><b>' . esc_html($x[1]) . '</b><small>' . esc_html($x[2]) . '</small></div></div>';
    }
    echo '</div>';
    $fb = (array) get_option('pgs_files_bad', array());
    if ($fb) {
        echo '<div class="pgs-files"><b>Изменённые файлы</b><ul>';
        foreach (array_slice($fb, 0, 30, true) as $f => $w) {
            echo '<li><code>' . esc_html($f) . '</code> — ' . esc_html($w) . '</li>';
        }
        echo '</ul>' . petgo_form_open('pgs_accept', 'onsubmit="return confirm(\'Принять эти файлы как правильные? Делайте это, только если вы сами обновляли плагины или настройки.\')"') . '<button type="submit" class="pgc-btn sec sm">Это я обновлял — принять</button></form></div>';
    }
    echo '</div>';

    /* 6. журнал */
    $kf = isset($_GET['k']) ? sanitize_key(wp_unslash($_GET['k'])) : '';
    $lim = isset($_GET['n']) ? min(500, max(50, (int) $_GET['n'])) : 60;
    $where = $kf === 'attacks' ? "kind IN ('" . implode("','", array_map('esc_sql', $AK)) . "')" : ($kf === 'logins' ? "kind IN ('login','login_fail','brute','device')" : ($kf !== '' && isset($K[$kf]) ? $wpdb->prepare('kind = %s', $kf) : "kind <> 'login'"));
    $ev = $wpdb->get_results("SELECT * FROM $tb WHERE $where ORDER BY id DESC LIMIT " . (int) $lim); // phpcs:ignore
    echo '<div class="pgc-card" id="pgs-ev"><b>Журнал</b><div class="pgc-chips pgs-chips">';
    foreach (array('' => 'Все', 'attacks' => 'Атаки', 'logins' => 'Входы', 'files' => 'Файлы', 'block' => 'Блокировки') as $k => $l) {
        echo '<a class="pgc-chip' . ($kf === $k ? ' on' : '') . '" href="' . esc_url(petgo_page_url($k === '' ? array('pg' => 'shield') : array('pg' => 'shield', 'k' => $k))) . '#pgs-ev">' . esc_html($l) . '</a>';
    }
    echo '</div>';
    if (!$ev) {
        echo '<p class="mu">Записей нет.</p>';
    }
    echo '<div class="pgs-evs">';
    foreach ((array) $ev as $e) {
        $kv = isset($K[$e->kind]) ? $K[$e->kind][0] : $e->kind;
        echo '<details class="pgs-ev s' . (int) $e->sev . '"><summary><span class="pgs-dot s' . (int) $e->sev . '"></span><span class="pgs-evt"><b>' . esc_html($kv) . '</b><small>' . esc_html($t($e->ts) . ($e->ip !== '' ? ' · ' . $e->ip : '')) . '</small></span></summary><div class="pgs-evb">';
        if ($e->info !== '') {
            echo '<p>' . esc_html($e->info) . '</p>';
        }
        if ($e->path !== '') {
            echo '<p class="mu"><code>' . esc_html(mb_substr($e->path, 0, 200)) . '</code></p>';
        }
        if ($e->ua !== '') {
            echo '<p class="mu">' . esc_html(mb_substr($e->ua, 0, 140)) . '</p>';
        }
        if ($e->ip !== '' && !pgs_private($e->ip) && !isset($blocks[$e->ip])) {
            echo petgo_form_open('pgs_block', 'class="pgs-inl"') . '<input type="hidden" name="ip" value="' . esc_attr($e->ip) . '"><input type="hidden" name="min" value="10080"><button type="submit" class="pgc-btn sec sm">Закрыть этот адрес на неделю</button></form>';
        } elseif (isset($blocks[$e->ip])) {
            echo '<p class="mu">Адрес закрыт до ' . esc_html($t($blocks[$e->ip]['until'])) . '</p>';
        }
        echo '</div></details>';
    }
    echo '</div>';
    if (count((array) $ev) >= $lim && $lim < 500) {
        echo '<a class="pgc-btn sec wide" href="' . esc_url(petgo_page_url(array_filter(array('pg' => 'shield', 'k' => $kf, 'n' => $lim + 100)))) . '#pgs-ev">Показать ещё</a>';
    }
    echo petgo_form_open('pgs_csv', 'class="pgs-inl pgs-csv"') . '<button type="submit" class="pgc-btn sec sm">' . petgo_icon('download') . 'Отчёт за 30 дней (CSV)</button></form>';
    echo '</div>';

    /* 7. закрытые адреса */
    echo '<div class="pgc-card"><b>Закрытые адреса</b>';
    if (!$blocks) {
        echo '<p class="mu">Сейчас ни один адрес не закрыт.</p>';
    }
    uasort($blocks, function ($a, $b) {
        return $b['at'] - $a['at'];
    });
    foreach (array_slice($blocks, 0, 100, true) as $ip => $b) {
        echo '<div class="pgs-blk"><div><b>' . esc_html($ip) . '</b><small>' . esc_html(($b['scope'] === 'site' ? 'весь сайт' : 'только вход') . ' · ' . $b['why'] . ' · до ' . $t($b['until'])) . '</small></div>' . petgo_form_open('pgs_unblock', 'class="pgs-inl"') . '<input type="hidden" name="ip" value="' . esc_attr($ip) . '"><button type="submit" class="pgc-btn sec sm">Открыть</button></form></div>';
    }
    echo petgo_form_open('pgs_block', 'class="pgs-addblk"') . '<input type="text" name="ip" placeholder="Адрес, например 203.0.113.7" inputmode="decimal" autocomplete="off" required><select name="min"><option value="1440">на сутки</option><option value="10080">на неделю</option><option value="43200">на месяц</option><option value="525600">на год</option></select><button type="submit" class="pgc-btn sm">Закрыть</button></form>';
    echo '</div>';

    /* 8. сеансы сотрудников */
    echo '<div class="pgc-card"><b>Сеансы сотрудников</b><p class="mu">Телефон сотрудника потерян или вход с чужого устройства — завершите его сеансы: приложение попросит войти заново.</p><div class="pgs-users">';
    $users = function_exists('petgo_staff_users') ? petgo_staff_users() : get_users(array('role__in' => array('administrator')));
    foreach ($users as $u) {
        $ses = class_exists('WP_Session_Tokens') ? count(WP_Session_Tokens::get_instance($u->ID)->get_all()) : 0;
        $ll  = get_user_meta($u->ID, 'pgs_last', true);
        $l0  = is_array($ll) && $ll ? $ll[0] : null;
        $rl  = function_exists('petgo_role_of') && function_exists('petgo_t') ? petgo_t('role_' . petgo_role_of($u)) : '';
        echo '<div class="pgs-user"><div><b>' . esc_html($u->display_name) . '</b><small>' . esc_html(trim($rl . ' · ' . ($l0 ? 'вход ' . $t($l0['t']) . ' · ' . $l0['ua'] . ' · ' . $l0['ip'] : 'входов ещё не было') . ' · сеансов: ' . $ses, ' ·')) . '</small></div>';
        if ($ses && $u->ID !== get_current_user_id()) {
            echo petgo_form_open('pgs_kill', 'class="pgs-inl" onsubmit="return confirm(\'Завершить все сеансы: ' . esc_js($u->display_name) . '?\')"') . '<input type="hidden" name="uid" value="' . (int) $u->ID . '"><button type="submit" class="pgc-btn sec sm">Завершить</button></form>';
        }
        echo '</div>';
    }
    echo '</div></div>';

    /* 9. настройки */
    $num = function ($k, $label, $min, $max, $hint) use ($cfg) {
        return '<label class="pgf-row"><span>' . esc_html($label) . '</span><input type="number" name="' . esc_attr($k) . '" value="' . (int) $cfg[$k] . '" min="' . $min . '" max="' . $max . '" inputmode="numeric"></label><small class="pgs-hint">' . esc_html($hint) . '</small>';
    };
    $ck = function ($k, $label, $on) {
        return '<label class="pgc-ck"><input type="checkbox" name="' . esc_attr($k) . '" value="1"' . ($on ? ' checked' : '') . '> <span>' . esc_html($label) . '</span></label>';
    };
    echo petgo_form_open('pgs_save') . '<div class="pgc-card"><b>Настройки</b><div class="pgf-list">';
    echo '<label class="pgf-row"><span>Режим</span><select name="mode"><option value="protect"' . selected($cfg['mode'], 'protect', false) . '>Защищать (блокировать)</option><option value="watch"' . selected($cfg['mode'], 'watch', false) . '>Только наблюдать</option></select></label>';
    echo $num('login_max', 'Неверных паролей', 3, 50, 'С одного адреса — после этого вход для него закрывается.');
    echo $num('login_win', 'За минут', 5, 120, 'Окно, в котором считаются неверные пароли.');
    echo $num('block_min', 'Закрыть на минут', 5, 10080, 'На сколько закрывать адрес взломщика.');
    echo $num('strikes', 'Атак до блокировки', 1, 20, 'Атак с одного адреса за 10 минут — и весь сайт для него закрыт.');
    echo $num('flood404', 'Страниц «не найдено»', 10, 500, 'За 5 минут с одного адреса — так перебирают адреса в поисках уязвимостей.');
    echo $num('ajax_max', 'Запросов гостя', 60, 5000, 'К входу и формам за 5 минут с одного адреса.');
    echo '<label class="pgf-row pgf-ra"><span>Свои адреса</span><textarea name="allow" rows="2" placeholder="Офис: 203.0.113.7 или 203.0.113.0/24">' . esc_textarea($cfg['allow']) . '</textarea></label><small class="pgs-hint">Эти адреса никогда не блокируются. Ваш адрес сейчас: ' . esc_html(pgs_ip()) . '</small>';
    echo '</div>';
    echo $ck('xmlrpc', 'Закрыть XML-RPC (PetGo он не нужен)', $cfg['xmlrpc'] === '1');
    echo $ck('headers', 'Заголовки безопасности на всех страницах', $cfg['headers'] === '1');
    echo $ck('notify', 'Уведомления о блокировках и опасных событиях', $cfg['notify'] === '1');
    echo $ck('digest', 'Отчёт за сутки каждое утро в 9:00', $cfg['digest'] === '1');
    echo $ck('trust_proxy', 'Сайт за прокси (Cloudflare или nginx хостинга): брать адрес посетителя из заголовка', (bool) get_option('petgo_trust_proxy'));
    echo '<button type="submit" class="pgc-btn wide">Сохранить</button></div></form>';
    echo '</div>';
}

function pgs_css() {
    return '.pgs .pgc-card{display:flex;flex-direction:column;gap:10px;margin-bottom:14px}.pgs .pgc-card>b{font-size:17px}.pgs .mu{margin:0}'
        . '.pgs-hero{flex-direction:row!important;align-items:center;gap:14px!important}.pgs-hero>div{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}.pgs-hero b{font-size:20px}.pgs-hero small{color:var(--mu)}'
        . '.pgs-shield{flex:none;width:52px;height:52px;border-radius:16px;display:grid;place-items:center;background:var(--oks);color:var(--ok)}.pgs-shield svg{width:28px;height:28px}.pgs-hero.warn .pgs-shield{background:var(--wns);color:var(--wn)}.pgs-hero.bad .pgs-shield{background:var(--ers);color:var(--er)}'
        . '.pgs-inl{margin:0}.pgs-tiles{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:14px}@media(min-width:760px){.pgs-tiles{grid-template-columns:repeat(4,1fr)}}.pgs-tiles div{padding:14px;border-radius:var(--r,18px);background:var(--cd)}.pgs-tiles b{display:block;font-size:28px;line-height:1.1;font-variant-numeric:tabular-nums}.pgs-tiles small{color:var(--mu);font-size:13px}'
        . '.pgs-bars{display:grid;grid-template-columns:repeat(14,1fr);gap:4px;align-items:end;height:140px}.pgs-bar{height:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:3px}.pgs-bar i{display:block;width:100%;max-width:22px;border-radius:5px 5px 2px 2px;background:var(--ac);opacity:.75}.pgs-bar.today i{opacity:1}.pgs-bar em{font-style:normal;font-size:10.5px;color:var(--mu);font-variant-numeric:tabular-nums}.pgs-bar span{font-size:11px;color:var(--mu)}'
        . '.pgs-kinds,.pgs-checks,.pgs-evs,.pgs-users{display:flex;flex-direction:column}.pgs-kind{display:flex;align-items:center;gap:12px;padding:10px 0;border-top:.5px solid var(--ln);color:var(--tx);text-decoration:none}.pgs-kind:first-child{border-top:0}.pgs-kind>span:nth-child(2){flex:1;min-width:0;display:flex;flex-direction:column}.pgs-kind small{color:var(--mu);font-size:12.5px}.pgs-kind em{font-style:normal;font-weight:700;font-size:18px;font-variant-numeric:tabular-nums}'
        . '.pgs-dot{flex:none;width:10px;height:10px;border-radius:50%;background:var(--mu2)}.pgs-dot.s1{background:var(--wn)}.pgs-dot.s2,.pgs-dot.s3{background:var(--er)}.pgs-dot.s0{background:var(--ok)}'
        . '.pgs-chk{display:flex;gap:12px;padding:10px 0;border-top:.5px solid var(--ln)}.pgs-chk:first-child{border-top:0}.pgs-chk>span{flex:none;width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-weight:700;font-size:14px;background:var(--oks);color:var(--ok)}.pgs-chk.warn>span{background:var(--wns);color:var(--wn)}.pgs-chk.bad>span{background:var(--ers);color:var(--er)}.pgs-chk.unk>span{background:var(--so);color:var(--mu)}.pgs-chk>div{display:flex;flex-direction:column;gap:2px;min-width:0}.pgs-chk small{color:var(--mu);font-size:13px;line-height:1.4;overflow-wrap:anywhere}'
        . '.pgs-files ul{margin:6px 0 10px;padding-left:18px;font-size:13px}.pgs-files code,.pgs-evb code{font-size:12px;overflow-wrap:anywhere}'
        . '.pgs-chips{margin:0!important}.pgs-ev{border-top:.5px solid var(--ln)}.pgs-ev:first-child{border-top:0}.pgs-ev>summary{display:flex;align-items:center;gap:12px;padding:10px 0;list-style:none;cursor:pointer}.pgs-ev>summary::-webkit-details-marker{display:none}.pgs-evt{display:flex;flex-direction:column;min-width:0}.pgs-evt small{color:var(--mu);font-size:12.5px;font-variant-numeric:tabular-nums}.pgs-evb{padding:0 0 12px 22px;display:flex;flex-direction:column;gap:6px;font-size:14px}.pgs-evb p{margin:0;overflow-wrap:anywhere}'
        . '.pgs-csv button{gap:6px}.pgs-csv svg{width:16px;height:16px}'
        . '.pgs-blk,.pgs-user{display:flex;align-items:center;gap:12px;padding:10px 0;border-top:.5px solid var(--ln)}.pgs-blk>div,.pgs-user>div{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}.pgs-blk small,.pgs-user small{color:var(--mu);font-size:12.5px;line-height:1.35}'
        . '.pgs-addblk{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}.pgs-addblk input{flex:1 1 180px;min-width:0;height:40px;padding:0 12px;border-radius:12px;border:0;background:var(--in);color:var(--tx);font:inherit}.pgs-addblk select{height:40px;padding:0 10px;border-radius:12px;border:0;background:var(--in);color:var(--tx);font:inherit}'
        . '.pgs .pgf-list{margin:0}.pgs .pgf-row select,.pgs .pgf-row input[type=number]{border:0;background:none;font:inherit;color:inherit;text-align:right;max-width:55%}.pgs-hint{display:block;margin:-2px 2px 6px;font-size:12.5px;color:var(--mu)}';
}

/* ============================================================
   ДЕЙСТВИЯ
   ============================================================ */
add_action('admin_post_petgo_pgs_save', function () {
    petgo_guard('admin');
    $in  = wp_unslash($_POST);
    $int = function ($k, $min, $max, $def) use ($in) {
        return isset($in[$k]) ? max($min, min($max, (int) $in[$k])) : $def;
    };
    $c = pgs_cfg();
    $allow = array();
    foreach (preg_split('/[\s,;]+/', isset($in['allow']) ? (string) $in['allow'] : '') as $a) {
        $a = trim($a);
        $ip = strpos($a, '/') !== false ? explode('/', $a, 2)[0] : $a;
        if ($a !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            $allow[] = $a;
        }
    }
    $n = array(
        'mode' => (isset($in['mode']) && $in['mode'] === 'watch') ? 'watch' : 'protect',
        'login_max' => $int('login_max', 3, 50, 10), 'login_win' => $int('login_win', 5, 120, 15), 'block_min' => $int('block_min', 5, 10080, 60),
        'strikes' => $int('strikes', 1, 20, 3), 'flood404' => $int('flood404', 10, 500, 40), 'ajax_max' => $int('ajax_max', 60, 5000, 400),
        'xmlrpc' => !empty($in['xmlrpc']) ? '1' : '0', 'headers' => !empty($in['headers']) ? '1' : '0', 'notify' => !empty($in['notify']) ? '1' : '0', 'digest' => !empty($in['digest']) ? '1' : '0',
        'allow' => implode("\n", array_slice(array_unique($allow), 0, 50)),
    );
    update_option('pgs_cfg', array_merge($c, $n), false);
    update_option('petgo_trust_proxy', !empty($in['trust_proxy']) ? 1 : 0);
    pgs_log('admin', 0, 'Настройки защиты изменены (' . ($n['mode'] === 'protect' ? 'защита' : 'наблюдение') . ')');
    petgo_flash('Сохранено');
    petgo_back('shield');
});

add_action('admin_post_petgo_pgs_check', function () {
    petgo_guard('admin');
    @set_time_limit(120);
    $r = pgs_full_check();
    pgs_log('admin', 0, 'Проверка вручную: файлы ' . count($r['files']) . ', загрузки ' . count($r['uploads']) . ', ядро ' . (is_array($r['core']) ? count($r['core']) : '—'));
    petgo_flash('Проверка завершена');
    petgo_back('shield');
});

add_action('admin_post_petgo_pgs_accept', function () {
    petgo_guard('admin');
    pgs_baseline();
    pgs_log('admin', 0, 'Изменения файлов приняты как правильные');
    petgo_flash('Файлы приняты');
    petgo_back('shield');
});

add_action('admin_post_petgo_pgs_unblock', function () {
    petgo_guard('admin');
    $ip = isset($_POST['ip']) ? sanitize_text_field(wp_unslash($_POST['ip'])) : '';
    pgs_unblock($ip);
    pgs_log('admin', 0, 'Адрес открыт вручную: ' . $ip, '', $ip);
    petgo_flash('Адрес открыт');
    petgo_back('shield');
});

add_action('admin_post_petgo_pgs_block', function () {
    petgo_guard('admin');
    $ip  = isset($_POST['ip']) ? trim(sanitize_text_field(wp_unslash($_POST['ip']))) : '';
    $min = isset($_POST['min']) ? max(60, min(525600, (int) $_POST['min'])) : 1440;
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        petgo_flash('Неверный адрес', false);
        petgo_back('shield');
    }
    if ($ip === pgs_ip()) {
        petgo_flash('Это ваш собственный адрес — его закрывать нельзя', false);
        petgo_back('shield');
    }
    pgs_block($ip, $min, 'site', 'закрыт вручную', true);
    petgo_flash('Адрес закрыт');
    petgo_back('shield');
});

add_action('admin_post_petgo_pgs_kill', function () {
    petgo_guard('admin');
    $uid = isset($_POST['uid']) ? (int) $_POST['uid'] : 0;
    $u   = $uid ? get_userdata($uid) : false;
    if ($u && $uid !== get_current_user_id()) {
        WP_Session_Tokens::get_instance($uid)->destroy_all();
        pgs_log('admin', 1, 'Завершены все сеансы: ' . $u->display_name);
        petgo_flash('Сеансы завершены: ' . $u->display_name);
    }
    petgo_back('shield');
});

/* отчёт за 30 дней в CSV (открывается в Excel и Google Таблицах) */
add_action('admin_post_petgo_pgs_csv', function () {
    global $wpdb;
    petgo_guard('admin');
    $tb   = pgs_tb();
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tb WHERE ts >= %d ORDER BY id DESC LIMIT 20000", time() - 30 * DAY_IN_SECONDS)); // phpcs:ignore
    $K    = pgs_kinds();
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="petgo-zashchita-' . wp_date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, array('Время', 'Событие', 'Важность', 'Адрес', 'Что', 'Страница', 'Браузер'), ';');
    $tz = function_exists('petgo_tz') ? petgo_tz() : wp_timezone();
    foreach ((array) $rows as $r) {
        $cell = function ($v) {
            $v = (string) $v;
            return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
        };
        fputcsv($o, array(wp_date('d.m.Y H:i:s', (int) $r->ts, $tz), isset($K[$r->kind]) ? $K[$r->kind][0] : $r->kind, array('инфо', 'внимание', 'атака', 'опасно')[min(3, (int) $r->sev)], $r->ip, $cell($r->info), $cell($r->path), $cell($r->ua)), ';');
    }
    fclose($o);
    exit;
});
