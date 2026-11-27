<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   40.4 АКТИВНОСТЬ КЛИЕНТА (видят сотрудники в карточке клиента)
   - когда клиент был в приложении последний раз («сейчас в приложении» / «5 мин назад»), последний вход;
   - с каких устройств заходит: приложение My PetGo (из магазина приложений), «на экране Домой»
     или в браузере; телефон / компьютер; браузер; включены ли уведомления;
   - что открывал: разделы (Главная, Карта, Прогулки, Аккаунт), питомцев, запись, чат, договор,
     уведомления, адресник, страницы сайта;
   - что сделал: регистрация, запись на услугу, анкета питомца, договор, сообщение помощнику,
     оценка няни, фото, уведомления, адресник.
   Хранится 120 дней. Адрес IP и точное место не записываются.
   ============================================================ */

add_action('init', 'petgo_ca_install', 7);
function petgo_ca_install() {
    if (get_option('petgo_ca_db') === '1') {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $cc = $wpdb->get_charset_collate();
    $t  = petgo_tb('clact');
    dbDelta("CREATE TABLE $t (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uid bigint(20) unsigned NOT NULL DEFAULT 0,
  ts int(10) unsigned NOT NULL DEFAULT 0,
  k varchar(8) NOT NULL DEFAULT '',
  w varchar(40) NOT NULL DEFAULT '',
  x varchar(190) NOT NULL DEFAULT '',
  dev varchar(80) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY uid (uid,ts)
) $cc;");
    update_option('petgo_ca_db', '1', false);
}

/* ---------- запись ---------- */
function petgo_ca_add($uid, $k, $w, $x = '', $dev = '') {
    global $wpdb;
    $uid = (int) $uid;
    if (!$uid) {
        return;
    }
    $wpdb->insert(petgo_tb('clact'), array('uid' => $uid, 'ts' => time(), 'k' => mb_substr($k, 0, 8), 'w' => mb_substr($w, 0, 40), 'x' => mb_substr(wp_strip_all_tags((string) $x), 0, 190), 'dev' => mb_substr($dev, 0, 80)), array('%d', '%d', '%s', '%s', '%s', '%s'));
    if (mt_rand(1, 200) === 1) {
        $wpdb->query($wpdb->prepare('DELETE FROM ' . petgo_tb('clact') . ' WHERE ts<%d', time() - 120 * DAY_IN_SECONDS));
    }
}

/* ---------- устройство по User-Agent (+ подсказка приложения: открыто с экрана «Домой») ---------- */
function petgo_ca_dev($standalone = false) {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    $os = 'Компьютер';
    $ph = false;
    if (preg_match('/iPhone/i', $ua)) {
        $os = 'iPhone';
        $ph = true;
    } elseif (preg_match('/iPad/i', $ua)) {
        $os = 'iPad';
        $ph = true;
    } elseif (preg_match('/Android/i', $ua)) {
        $os = 'Android';
        $ph = true;
    } elseif (preg_match('/Macintosh|Mac OS X/i', $ua)) {
        $os = 'Компьютер (Mac)';
    } elseif (preg_match('/Windows/i', $ua)) {
        $os = 'Компьютер (Windows)';
    } elseif (preg_match('/Linux/i', $ua)) {
        $os = 'Компьютер (Linux)';
    }
    $mode = 'web';
    $ver  = '';
    if (preg_match('#MyPetGoApp/([0-9.]+)#', $ua, $m)) {
        $mode = 'app';
        $ver  = $m[1];
        if (stripos($ua, '(ios') !== false && $os === 'Компьютер (Mac)') {
            $os = 'iPhone';
            $ph = true;
        }
    } elseif ($standalone) {
        $mode = 'pwa';
    }
    $br = 'браузер';
    foreach (array('Instagram' => 'Instagram', 'FBAN|FBAV' => 'Facebook', 'WhatsApp' => 'WhatsApp', 'Telegram' => 'Telegram', 'YaBrowser' => 'Яндекс Браузер', 'SamsungBrowser' => 'Samsung Internet', 'Edg/' => 'Edge', 'OPR/|Opera' => 'Opera', 'Firefox|FxiOS' => 'Firefox', 'CriOS|Chrome' => 'Chrome', 'Safari' => 'Safari') as $re => $name) {
        if (preg_match('#' . $re . '#', $ua)) {
            $br = $name;
            break;
        }
    }
    return array('os' => $os, 'ph' => $ph, 'mode' => $mode, 'ver' => $ver, 'br' => $br);
}
function petgo_ca_dev_label($d) {
    if ($d['mode'] === 'app') {
        return $d['os'] . ' · приложение My PetGo' . ($d['ver'] !== '' ? ' ' . $d['ver'] : '');
    }
    if ($d['mode'] === 'pwa') {
        return $d['os'] . ' · на экране «Домой»';
    }
    return $d['os'] . ' · ' . $d['br'];
}
/* запомнить устройство клиента (до 6 последних) */
function petgo_ca_dev_seen($uid, $d) {
    $key  = md5($d['os'] . '|' . $d['mode'] . '|' . $d['br']);
    $all  = get_user_meta((int) $uid, 'petgo_ca_devs', true);
    $all  = is_array($all) ? $all : array();
    $all[$key] = array('l' => petgo_ca_dev_label($d), 'ph' => $d['ph'] ? 1 : 0, 'm' => $d['mode'], 'ts' => time(), 'n' => isset($all[$key]['n']) ? (int) $all[$key]['n'] + 1 : 1);
    uasort($all, function ($a, $b) {
        return (int) $b['ts'] - (int) $a['ts'];
    });
    update_user_meta((int) $uid, 'petgo_ca_devs', array_slice($all, 0, 6, true));
}

/* ---------- каждый запрос из приложения: «был в сети» и понятные действия ---------- */
function petgo_ca_touch($uid, $op) {
    $uid  = (int) $uid;
    $seen = (int) get_user_meta($uid, 'petgo_seen', true);
    if (time() - $seen > 40) {
        update_user_meta($uid, 'petgo_seen', time());
    }
    $map = array(
        'send' => 'Сообщение помощнику', 'review' => 'Оценка няни', 'avatar' => 'Новое фото профиля', 'pet_av' => 'Новое фото питомца',
        'push_sub' => 'Включены уведомления', 'push_native' => 'Включены уведомления', 'tag_bind' => 'Привязка QR-адресника', 'tag_unbind' => 'QR-адресник отвязан',
        'fav' => 'Няня в избранном',
    );
    if (isset($map[$op])) {
        petgo_ca_add($uid, 'act', $op, $map[$op]);
    }
    if ($op === 'tag_opt' && petgo_p('k') === 'lost' && petgo_p('v') === '1') {
        petgo_ca_add($uid, 'act', 'lost', 'Отмечено: питомец потерялся');
    }
}

/* ---------- просмотры из приложения (пакетами) ---------- */
function petgo_ca_views() {
    return array(
        't:home' => 'Главная', 't:map' => 'Карта', 't:walks' => 'Прогулки', 't:acc' => 'Аккаунт',
        's:about' => 'О PetGo', 's:book' => 'Запись на услугу', 's:chat' => 'Чат с помощником', 's:ct' => 'Договор', 's:fb' => 'Отзыв о приложении',
        's:id' => 'Мой ID', 's:me' => 'Профиль', 's:nannies' => 'Няни', 's:nanny' => 'Профиль няни', 's:notifs' => 'Уведомления', 's:order' => 'Заказ',
        's:pet' => 'Анкета питомца', 's:post' => 'Статья в блоге', 's:review' => 'Оценка няни', 's:tag' => 'QR-адресник', 's:push' => 'Предложение включить уведомления',
    );
}
add_action('petgo_cl_op', function ($op, $uid) {
    if ($op !== 'act') {
        return;
    }
    $uid = (int) $uid;
    $ev  = json_decode(wp_unslash((string) petgo_p('ev')), true);
    $ev  = is_array($ev) ? array_slice($ev, 0, 40) : array();
    /* не больше 200 событий за 10 минут */
    $ck = 'petgo_ca_n' . $uid;
    $n  = (int) get_transient($ck);
    if ($n > 200) {
        petgo_cl_json(true);
    }
    set_transient($ck, $n + count($ev), 10 * MINUTE_IN_SECONDS);
    $V    = petgo_ca_views();
    $last = (string) get_transient('petgo_ca_l' . $uid);
    foreach ($ev as $e) {
        if (!is_array($e) || empty($e[0])) {
            continue;
        }
        $k = (string) $e[0];
        if ($k === 'v') {
            /* открыл приложение: новый визит, если перерыв больше 30 минут */
            $d = petgo_ca_dev(!empty($e[1]));
            petgo_ca_dev_seen($uid, $d);
            $lv = (int) get_user_meta($uid, 'petgo_ca_vis', true);
            if (time() - $lv > 30 * MINUTE_IN_SECONDS) {
                petgo_ca_add($uid, 'visit', $d['mode'], $d['mode'] === 'web' ? 'Открыт кабинет на сайте' : 'Открыто приложение', petgo_ca_dev_label($d));
            }
            update_user_meta($uid, 'petgo_ca_vis', time());
            continue;
        }
        $key = '';
        $x   = '';
        if (($k === 't' || $k === 's') && isset($e[1]) && isset($V[$k . ':' . $e[1]])) {
            $key = $k . ':' . $e[1];
            $x   = $V[$key];
        } elseif ($k === 'p' && isset($e[1]) && petgo_cl_owns($uid, (int) $e[1])) {
            $key = 'p:' . (int) $e[1];
            $x   = 'Питомец «' . get_the_title((int) $e[1]) . '»';
        }
        if ($key === '' || $key === $last) {
            continue;
        }
        $last = $key;
        petgo_ca_add($uid, 'view', $key, $x);
    }
    set_transient('petgo_ca_l' . $uid, $last, 30 * MINUTE_IN_SECONDS);
    update_user_meta($uid, 'petgo_ca_vis', time());
    petgo_cl_json(true);
}, 10, 2);

/* ---------- вход, регистрация и действия из общего потока событий ---------- */
add_action('wp_login', function ($login, $user) {
    if (function_exists('petgo_cl_is') && petgo_cl_is($user)) {
        update_user_meta($user->ID, 'petgo_last_login', time());
        $d = petgo_ca_dev(false);
        petgo_ca_dev_seen($user->ID, $d);
        petgo_ca_add($user->ID, 'login', 'login', 'Вход по логину и паролю', petgo_ca_dev_label($d));
    }
}, 10, 2);
add_action('petgo_event', function ($name, $d) {
    $uid = isset($d['user']) ? (int) $d['user'] : 0;
    if (!$uid) {
        return;
    }
    if ($name === 'sign_up') {
        petgo_ca_add($uid, 'act', 'sign_up', 'Регистрация в кабинете');
    } elseif ($name === 'booking' && !empty($d['orders'])) {
        $o   = array_map('intval', (array) $d['orders']);
        $svc = function_exists('petgo_cl_svc_label') ? petgo_cl_svc_label(petgo_o($o[0], 'service')) : 'Заказ';
        $ts  = (int) petgo_o($o[0], 'ts');
        petgo_ca_add($uid, 'act', 'booking', 'Запись: ' . $svc . ($ts ? ' · ' . wp_date('d.m · H:i', $ts, petgo_tz()) : '') . (count($o) > 1 ? ' (+' . (count($o) - 1) . ')' : '') . (!empty($d['by']) && $d['by'] === 'ai' ? ' — через помощника' : ''));
    } elseif ($name === 'pet_saved' && !empty($d['pet'])) {
        petgo_ca_add($uid, 'act', 'pet', (!empty($d['new']) ? 'Добавлен питомец «' : 'Изменена анкета «') . get_the_title((int) $d['pet']) . '»');
    } elseif ($name === 'contract_signed') {
        petgo_ca_add($uid, 'act', 'contract', 'Подписан договор' . (!empty($d['no']) ? ' № ' . $d['no'] : ''));
    } elseif ($name === 'cl_profile') {
        petgo_ca_add($uid, 'act', 'profile', 'Изменён профиль');
    } elseif ($name === 'cl_pass') {
        petgo_ca_add($uid, 'act', 'pass', 'Изменён пароль');
    }
}, 10, 2);

/* ---------- страницы сайта, которые клиент открывал, пока был в кабинете ---------- */
add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || !isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET' || !function_exists('petgo_cl_uid')) {
        return;
    }
    $uid = petgo_cl_uid();
    if (!$uid || is_feed() || is_robots()) {
        return;
    }
    /* только настоящие открытия страниц: сайт заранее подгружает страницы (для работы без интернета) — их не считаем */
    $mode = isset($_SERVER['HTTP_SEC_FETCH_MODE']) ? strtolower((string) $_SERVER['HTTP_SEC_FETCH_MODE']) : '';
    $purp = strtolower((isset($_SERVER['HTTP_SEC_PURPOSE']) ? (string) $_SERVER['HTTP_SEC_PURPOSE'] : '') . (isset($_SERVER['HTTP_PURPOSE']) ? (string) $_SERVER['HTTP_PURPOSE'] : '') . (isset($_SERVER['HTTP_X_MOZ']) ? (string) $_SERVER['HTTP_X_MOZ'] : ''));
    if (($mode !== '' && $mode !== 'navigate') || strpos($purp, 'prefetch') !== false || strpos($purp, 'prerender') !== false || !empty($_SERVER['HTTP_SERVICE_WORKER'])) {
        return;
    }
    $path = (string) wp_parse_url(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
    $acc  = function_exists('petgo_site_url') ? (string) wp_parse_url(petgo_site_url('account'), PHP_URL_PATH) : '';
    /* сам кабинет — это «Открыто приложение» (пишет скрипт приложения) */
    if ($acc !== '' && untrailingslashit($acc) === untrailingslashit($path)) {
        return;
    }
    $tk = 'petgo_ca_pg' . $uid . md5($path);
    if (get_transient($tk)) {
        return;
    }
    set_transient($tk, 1, 10 * MINUTE_IN_SECONDS);
    petgo_ca_add($uid, 'page', mb_substr($path, 0, 40), 'Сайт: «' . petgo_ca_page_title($path) . '»');
}, 1);

/* понятное название страницы сайта */
function petgo_ca_page_title($path) {
    $slug = trim((string) $path, '/');
    if (function_exists('petgo_site_pages')) {
        foreach (petgo_site_pages() as $p) {
            if (isset($p['slug']) && trim((string) $p['slug'], '/') === $slug) {
                return (string) ($p['title'] !== '' ? $p['title'] : $p['name']);
            }
        }
    }
    $o = get_queried_object();
    if ($o instanceof WP_Post) {
        return get_the_title($o);
    }
    $t = trim(preg_replace('/\s*[–—|-]\s*PetGo.*$/u', '', html_entity_decode(wp_get_document_title(), ENT_QUOTES, 'UTF-8')));
    return ($t !== '' && $t !== 'PetGo') ? $t : '/' . urldecode($slug);
}

/* ============================================================
   ПРИЛОЖЕНИЕ СОТРУДНИКОВ: карточка клиента → «Активность»
   ============================================================ */
function petgo_ca_ago($ts, $now = 'только что') {
    $d = time() - (int) $ts;
    $tz = petgo_tz();
    if ($d < 150) {
        return $now;
    }
    if ($d < HOUR_IN_SECONDS) {
        return floor($d / 60) . ' мин назад';
    }
    if (wp_date('Y-m-d', (int) $ts, $tz) === wp_date('Y-m-d', time(), $tz)) {
        return 'сегодня в ' . wp_date('H:i', (int) $ts, $tz);
    }
    if (wp_date('Y-m-d', (int) $ts, $tz) === wp_date('Y-m-d', time() - DAY_IN_SECONDS, $tz)) {
        return 'вчера в ' . wp_date('H:i', (int) $ts, $tz);
    }
    return wp_date('d.m.Y · H:i', (int) $ts, $tz);
}
function petgo_ca_online($uid) {
    return time() - (int) get_user_meta((int) $uid, 'petgo_seen', true) < 150;
}
function petgo_ca_list($uid, $n) {
    global $wpdb;
    return (array) $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . petgo_tb('clact') . ' WHERE uid=%d ORDER BY id DESC LIMIT %d', (int) $uid, (int) $n));
}
function petgo_ca_icon($r) {
    if ($r->k === 'visit' || $r->k === 'login') {
        return 'phone';
    }
    if ($r->k === 'page') {
        return 'link';
    }
    if ($r->k === 'act') {
        return $r->w === 'booking' ? 'order' : ($r->w === 'send' ? 'msg' : ($r->w === 'contract' ? 'note' : ($r->w === 'pet' || $r->w === 'pet_av' ? 'paw' : 'check')));
    }
    return strpos($r->w, 'p:') === 0 ? 'paw' : 'eye';
}
function petgo_ca_ic($n) {
    if ($n === 'eye') {
        return '<svg class="pgc-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
    return petgo_ic($n);
}
function petgo_ca_timeline($rows) {
    $tz  = petgo_tz();
    $day = '';
    $h   = '<div class="pgca-tl">';
    foreach ($rows as $r) {
        $d = wp_date('Y-m-d', (int) $r->ts, $tz);
        if ($d !== $day) {
            $day = $d;
            $h  .= '<div class="pgca-d">' . esc_html($d === wp_date('Y-m-d', time(), $tz) ? 'Сегодня' : ($d === wp_date('Y-m-d', time() - DAY_IN_SECONDS, $tz) ? 'Вчера' : wp_date('d.m.Y', (int) $r->ts, $tz))) . '</div>';
        }
        $h .= '<div class="pgca-e k-' . esc_attr($r->k) . '"><time>' . esc_html(wp_date('H:i', (int) $r->ts, $tz)) . '</time><span class="pgca-i">' . petgo_ca_ic(petgo_ca_icon($r)) . '</span><span class="pgca-t">' . esc_html($r->x) . ($r->dev !== '' ? '<small>' . esc_html($r->dev) . '</small>' : '') . '</span></div>';
    }
    return $h . '</div>';
}
function petgo_ca_css() {
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    return '<style>.pgca{margin:12px 0 4px;padding:14px;border-radius:16px;background:var(--so)}.pgca-h{display:flex;align-items:center;gap:8px;font-size:15.5px}.pgca-h b{font-weight:600}.pgca-dot{flex:none;width:10px;height:10px;border-radius:50%;background:var(--mu2,#A9B4B1)}.pgca-dot.on{background:#1E9A5F;box-shadow:0 0 0 4px rgba(30,154,95,.18)}'
        . '.pgca-kv{display:grid;grid-template-columns:auto 1fr;gap:6px 12px;margin:10px 0 4px;font-size:14px}.pgca-kv>span{color:var(--mu)}.pgca-kv>b{font-weight:500;min-width:0;overflow-wrap:anywhere}.pgca-dv{display:flex;flex-direction:column;gap:3px}.pgca-dv small{color:var(--mu);font-size:12.5px}'
        . '.pgca-tl{display:flex;flex-direction:column;margin-top:8px}.pgca-d{margin:10px 0 4px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--mu)}.pgca-e{display:grid;grid-template-columns:42px 26px 1fr;align-items:start;gap:8px;padding:5px 0;font-size:14px}.pgca-e time{color:var(--mu);font-variant-numeric:tabular-nums;padding-top:3px}.pgca-i{width:26px;height:26px;border-radius:8px;display:grid;place-items:center;background:var(--cd);color:var(--mu)}.pgca-i .pgc-ic{width:15px;height:15px}'
        . '.pgca-e.k-act .pgca-i{background:rgba(30,154,95,.14);color:#1E7A4C}.pgca-e.k-visit .pgca-i,.pgca-e.k-login .pgca-i{background:var(--acs);color:var(--ac)}.pgca-t{min-width:0;padding-top:3px;line-height:1.35}.pgca-t small{display:block;color:var(--mu);font-size:12.5px}.pgca-e.k-act .pgca-t{font-weight:600}'
        . '.pgca-more{display:inline-flex;margin-top:10px;font-weight:600;color:var(--ac);text-decoration:none}.pgca-empty{margin:8px 0 0;color:var(--mu);font-size:14px}'
        . '.pgcl summary .pgca-on{display:inline-block;width:8px;height:8px;margin-left:6px;border-radius:50%;background:#1E9A5F;vertical-align:middle}.pgcl .pgca-sm{color:var(--mu);font-weight:400}</style>';
}

/* блок в карточке клиента */
function petgo_ca_card($uid, $full = false) {
    global $wpdb;
    $uid   = (int) $uid;
    $seen  = (int) get_user_meta($uid, 'petgo_seen', true);
    $login = (int) get_user_meta($uid, 'petgo_last_login', true);
    $devs  = get_user_meta($uid, 'petgo_ca_devs', true);
    $devs  = is_array($devs) ? $devs : array();
    $push  = get_user_meta($uid, 'petgo_push', true);
    $fcm   = get_user_meta($uid, 'petgo_fcm', true);
    $on    = petgo_ca_online($uid);
    $vis   = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . petgo_tb('clact') . " WHERE uid=%d AND k='visit' AND ts>%d", $uid, time() - 30 * DAY_IN_SECONDS));
    $h     = petgo_ca_css() . '<div class="pgca"><div class="pgca-h"><span class="pgca-dot' . ($on ? ' on' : '') . '"></span><b>' . esc_html($seen ? ($on ? 'Сейчас в приложении' : 'Последняя активность: ' . petgo_ca_ago($seen)) : 'Приложение ещё не открывали после обновления') . '</b></div><div class="pgca-kv">';
    if ($login) {
        $h .= '<span>Последний вход</span><b>' . esc_html(petgo_ca_ago($login)) . '</b>';
    }
    $app = '';
    foreach ($devs as $d) {
        if ($d['m'] === 'app') {
            $app = 'Установлено: My PetGo';
            break;
        }
        if ($d['m'] === 'pwa') {
            $app = 'Добавлено на экран «Домой»';
        }
    }
    $h .= '<span>Приложение</span><b>' . esc_html($app !== '' ? $app : ($devs ? 'Не установлено — заходит через браузер' : '—')) . '</b>';
    if ($devs) {
        $h .= '<span>Устройства</span><span class="pgca-dv">';
        foreach (array_slice($devs, 0, $full ? 6 : 3) as $d) {
            $h .= '<b>' . esc_html($d['l']) . '<small> · ' . esc_html(petgo_ca_ago((int) $d['ts'])) . '</small></b>';
        }
        $h .= '</span>';
    }
    $h .= '<span>Уведомления</span><b>' . esc_html(($push || $fcm) ? 'включены на телефоне' : 'выключены') . '</b>';
    $h .= '<span>Визитов за 30 дней</span><b>' . (int) $vis . '</b></div>';
    $rows = petgo_ca_list($uid, $full ? 300 : 8);
    $h   .= $rows ? petgo_ca_timeline($rows) : '<p class="pgca-empty">Пока нет записей — появятся, когда клиент откроет приложение.</p>';
    if (!$full && $rows) {
        $h .= '<a class="pgca-more" href="' . esc_url(petgo_page_url(array('pg' => 'clact', 'uid' => $uid))) . '">Вся активность ›</a>';
    }
    return $h . '</div>';
}
/* точка «в сети» и «был …» в списке клиентов */
function petgo_ca_badge($uid) {
    $seen = (int) get_user_meta((int) $uid, 'petgo_seen', true);
    if (!$seen) {
        return '';
    }
    return petgo_ca_online($uid) ? '<i class="pgca-on" title="Сейчас в приложении"></i>' : '<span class="pgca-sm"> · ' . esc_html(petgo_ca_ago($seen)) . '</span>';
}

/* вся активность одного клиента */
add_filter('petgo_app_title', function ($t, $pg) {
    return $pg === 'clact' ? 'Активность клиента' : $t;
}, 10, 2);
add_filter('petgo_app_page', function ($done, $pg) {
    if ($done || $pg !== 'clact') {
        return $done;
    }
    $u = get_userdata(isset($_GET['uid']) ? (int) $_GET['uid'] : 0);
    if (!petgo_can('office') || !$u || !petgo_cl_is($u)) {
        echo '<p class="mu">Нет доступа.</p>';
        return true;
    }
    echo '<div class="pgc-card" style="padding:16px"><div style="display:flex;align-items:center;gap:12px;margin-bottom:4px">' . petgo_av(petgo_user_avatar_url($u->ID), $u->display_name, 'md') . '<div><b style="font-size:17px">' . esc_html($u->display_name) . '</b><div class="mu" style="font-size:14px">' . esc_html(petgo_cl_code($u->ID) . ' · ' . petgo_phone_fmt((string) get_user_meta($u->ID, 'petgo_phone', true))) . '</div></div></div>' . petgo_ca_card($u->ID, true) . '</div>';
    echo '<p style="margin:14px 4px 0"><a class="pgc-btn sec" href="' . esc_url(petgo_page_url(array('pg' => 'clients'))) . '">' . petgo_ic('undo') . 'К клиентам</a></p>';
    return true;
}, 10, 2);
