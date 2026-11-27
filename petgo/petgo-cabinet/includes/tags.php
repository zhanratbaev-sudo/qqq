<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   40.3 QR-АДРЕСНИКИ ДЛЯ ПИТОМЦЕВ
   Как это работает:
   1. Владелец или администратор в приложении сотрудников («Адресники») создаёт партию кодов и печатает
      наклейки. Каждый QR — уникальный, уже записан в базу и пока ни к кому не привязан.
   2. Клиент получает наклейку или медальон. Он сканирует QR (или вводит код с наклейки в приложении
      My PetGo) и привязывает адресник к своему питомцу.
   3. Если питомец потерялся, нашедший сканирует QR камерой телефона и видит анкету: кличку, фото,
      записку хозяина, кнопки «Позвонить» / «WhatsApp» и «Отправить хозяину, где я». Хозяину приходит
      уведомление (с местом на карте, если нашедший разрешил геолокацию).
   Коды: 8 знаков без похожих букв и цифр (нет 0/O, 1/I/L) — их невозможно подобрать.
   QR ведёт на адрес сайта ?pt=КОД — он работает при любых настройках сайта, поэтому напечатанные
   наклейки не «сломаются».
   ============================================================ */

define('PETGO_TAG_ABC', '23456789ABCDEFGHJKMNPQRSTUVWXYZ');

add_action('init', 'petgo_tag_install', 6);
function petgo_tag_install() {
    if (get_option('petgo_tags_db') === '1') {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $cc = $wpdb->get_charset_collate();
    $t  = petgo_tb('tags');
    dbDelta("CREATE TABLE $t (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  code char(8) NOT NULL DEFAULT '',
  batch int(10) unsigned NOT NULL DEFAULT 0,
  st tinyint(3) unsigned NOT NULL DEFAULT 0,
  pet bigint(20) unsigned NOT NULL DEFAULT 0,
  uid bigint(20) unsigned NOT NULL DEFAULT 0,
  ts int(10) unsigned NOT NULL DEFAULT 0,
  bound int(10) unsigned NOT NULL DEFAULT 0,
  scans int(10) unsigned NOT NULL DEFAULT 0,
  seen int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY code (code),
  KEY batch (batch),
  KEY pet (pet)
) $cc;");
    update_option('petgo_tags_db', '1', false);
}

/* ---------- коды ---------- */
function petgo_tag_norm($s) {
    $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $s));
    return (strlen($s) === 8 && strspn($s, PETGO_TAG_ABC) === 8) ? $s : '';
}
function petgo_tag_fmt($c) {
    return substr((string) $c, 0, 4) . '-' . substr((string) $c, 4);
}
function petgo_tag_url($c) {
    return add_query_arg('pt', $c, home_url('/'));
}
function petgo_tag_rand() {
    $a = PETGO_TAG_ABC;
    $s = '';
    for ($i = 0; $i < 8; $i++) {
        $s .= $a[random_int(0, strlen($a) - 1)];
    }
    return $s;
}
function petgo_tag_get($code) {
    global $wpdb;
    $code = petgo_tag_norm($code);
    return $code === '' ? null : $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . petgo_tb('tags') . ' WHERE code=%s', $code));
}
function petgo_tag_formats() {
    return array('v34' => 'Бирка 3 × 4 см, две стороны', 'r30' => 'Круг 30 мм', 'r25' => 'Круг 25 мм', 'r35' => 'Круг 35 мм', 'b40' => 'Прямоугольник 40 × 25 мм');
}
function petgo_tag_batches() {
    $b = get_option('petgo_tag_batches');
    return is_array($b) ? $b : array();
}

/* новая партия: N уникальных кодов, сразу в базе (статус «свободен») */
function petgo_tag_make($n, $fmt) {
    global $wpdb;
    $n   = max(1, min(400, (int) $n));
    $fmt = isset(petgo_tag_formats()[$fmt]) ? $fmt : 'v34';
    $b   = petgo_tag_batches();
    $id  = $b ? max(array_map('intval', array_keys($b))) + 1 : 1;
    $made = 0;
    for ($try = 0; $made < $n && $try < $n * 5; $try++) {
        if ($wpdb->query($wpdb->prepare('INSERT IGNORE INTO ' . petgo_tb('tags') . ' (code,batch,st,ts) VALUES (%s,%d,0,%d)', petgo_tag_rand(), $id, time()))) {
            $made++;
        }
    }
    $b[$id] = array('n' => $made, 'ts' => time(), 'by' => get_current_user_id(), 'fmt' => $fmt);
    update_option('petgo_tag_batches', $b, false);
    petgo_audit('tags_new', '№' . $id . ' · ' . $made);
    return $id;
}

/* ---------- привязка к питомцу ---------- */
function petgo_tag_of_pet($pid) {
    global $wpdb;
    return (array) $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . petgo_tb('tags') . ' WHERE pet=%d AND st=1 ORDER BY id', (int) $pid));
}
function petgo_tag_bind($code, $pid, $uid) {
    global $wpdb;
    $code = petgo_tag_norm($code);
    if ($code === '') {
        return 'Проверьте код: 8 букв и цифр, например ABCD-2345.';
    }
    if (!function_exists('petgo_cl_owns') || !petgo_cl_owns($uid, $pid)) {
        return 'Нет доступа.';
    }
    $t = petgo_tag_get($code);
    if (!$t) {
        return 'Такого кода нет. Проверьте буквы и цифры на адреснике.';
    }
    if ((int) $t->st === 2) {
        return 'Этот адресник отключён. Напишите в PetGo.';
    }
    if ((int) $t->st === 1 && (int) $t->uid !== (int) $uid) {
        return 'Этот адресник уже привязан к другому питомцу.';
    }
    if ((int) $t->pet !== (int) $pid && count(petgo_tag_of_pet($pid)) >= 5) {
        return 'К одному питомцу можно привязать до 5 адресников.';
    }
    /* одновременная привязка одного кода двумя людьми: выиграет первый */
    $n = $wpdb->query($wpdb->prepare('UPDATE ' . petgo_tb('tags') . ' SET st=1, pet=%d, uid=%d, bound=%d WHERE id=%d AND (st=0 OR uid=%d)', (int) $pid, (int) $uid, time(), (int) $t->id, (int) $uid));
    if (!$n) {
        return 'Этот адресник уже привязан к другому питомцу.';
    }
    if (function_exists('petgo_feed_add') && function_exists('petgo_push_users')) {
        $u = get_userdata((int) $uid);
        petgo_feed_add(petgo_push_users(array('owner', 'admin')), 'Клиент привязал адресник', ($u ? $u->display_name . ' · ' : '') . get_the_title($pid) . ' · ' . petgo_tag_fmt($code), petgo_page_url(array('pg' => 'tags', 'q' => $code)));
    }
    return true;
}
/* $uid = 0 — отвязывает сотрудник */
function petgo_tag_unbind($code, $uid = 0) {
    global $wpdb;
    $code = petgo_tag_norm($code);
    if ($code === '') {
        return false;
    }
    $sql = 'UPDATE ' . petgo_tb('tags') . ' SET st=0, pet=0, uid=0, bound=0 WHERE code=%s AND st=1';
    return (bool) ($uid ? $wpdb->query($wpdb->prepare($sql . ' AND uid=%d', $code, (int) $uid)) : $wpdb->query($wpdb->prepare($sql, $code)));
}

/* что видит нашедший — настраивает хозяин в приложении */
function petgo_tag_opts($pid) {
    return array(
        'phone' => get_post_meta((int) $pid, '_tag_phone', true) !== '0',
        'note'  => (string) get_post_meta((int) $pid, '_tag_note', true),
        'lost'  => (int) get_post_meta((int) $pid, '_tag_lost', true),
    );
}
function petgo_tag_finds($pid) {
    $f = get_post_meta((int) $pid, '_tag_finds', true);
    return is_array($f) ? $f : array();
}
function petgo_tag_owner_phone($uid, $pid) {
    $p = (string) get_user_meta((int) $uid, 'petgo_phone', true);
    if ($p === '') {
        $p = (string) get_post_meta((int) $pid, '_pf_phone', true);
    }
    return function_exists('petgo_phone_digits') ? petgo_phone_digits($p) : preg_replace('/\D/', '', $p);
}
function petgo_tag_phone_fmt($d) {
    $d = preg_replace('/\D/', '', (string) $d);
    if (strlen($d) === 11 && $d[0] === '7') {
        return '+7 ' . substr($d, 1, 3) . ' ' . substr($d, 4, 3) . ' ' . substr($d, 7, 2) . ' ' . substr($d, 9, 2);
    }
    return $d === '' ? '' : '+' . $d;
}
function petgo_tag_maps($lat, $lng) {
    return 'https://maps.google.com/?q=' . rawurlencode(round((float) $lat, 6) . ',' . round((float) $lng, 6));
}

/* уведомление хозяину: в «Уведомления» приложения и на телефон */
function petgo_tag_notify($uid, $text, $lat = null, $lng = null) {
    if (!function_exists('petgo_cl_sys')) {
        return 0;
    }
    $mid = (int) petgo_cl_sys((int) $uid, $text, 'tag');
    if ($mid && $lat !== null) {
        $m = get_user_meta((int) $uid, 'petgo_tagf', true);
        $m = is_array($m) ? $m : array();
        $m[$mid] = array(round((float) $lat, 6), round((float) $lng, 6));
        update_user_meta((int) $uid, 'petgo_tagf', array_slice($m, -30, 30, true));
    }
    return $mid;
}

/* фото питомца на открытой странице: снимки закрыты от посторонних, поэтому встраиваем в страницу */
function petgo_tag_photo($pid) {
    $a = get_post_meta((int) $pid, '_avatar', true);
    $f = is_array($a) && !empty($a['file']) ? (string) $a['file'] : '';
    if ($f === '') {
        $ph = petgo_photos((int) $pid);
        $f  = ($ph && !empty($ph[0]['file'])) ? (string) $ph[0]['file'] : '';
    }
    $ud = wp_upload_dir();
    if ($f === '' || strpos(wp_normalize_path($f), wp_normalize_path($ud['basedir'] . '/petgo')) !== 0 || !is_readable($f) || filesize($f) > 1572864) {
        return '';
    }
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    return 'data:' . ($ext === 'webp' ? 'image/webp' : ($ext === 'png' ? 'image/png' : 'image/jpeg')) . ';base64,' . base64_encode((string) file_get_contents($f));
}

function petgo_tag_limit($key, $max, $ttl) {
    $k = 'pgtag_' . $key . '_' . md5(petgo_ip());
    $n = (int) get_transient($k);
    if ($n >= $max) {
        return false;
    }
    set_transient($k, $n + 1, $ttl);
    return true;
}
function petgo_tag_is_bot() {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    return $ua === '' || (bool) preg_match('/bot|crawl|spider|facebookexternalhit|WhatsApp\/|TelegramBot|Slackbot|Discordbot|vkShare|SkypeUriPreview|Viber|preview/i', $ua);
}

/* ============================================================
   ОТКРЫТАЯ СТРАНИЦА: ?pt=КОД (её открывает камера телефона)
   ============================================================ */
add_action('parse_request', function () {
    if (!isset($_GET['pt']) || is_admin()) {
        return;
    }
    petgo_tag_page();
}, 0);

function petgo_tag_page() {
    $raw  = is_string($_GET['pt']) ? (string) wp_unslash($_GET['pt']) : '';
    $code = petgo_tag_norm($raw);
    $t    = $code !== '' ? petgo_tag_get($code) : null;
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    if (!$t) {
        /* перебор кодов: после 30 неверных за 10 минут — пауза */
        if (!petgo_tag_limit('nf', 30, 10 * MINUTE_IN_SECONDS)) {
            status_header(429);
            petgo_tag_shell('Подождите', '<div class="card c"><h1>Слишком много попыток</h1><p>Попробуйте через 10 минут.</p></div>');
        }
        status_header(404);
        petgo_tag_shell('Адресник не найден', '<div class="card c"><div class="ic">' . petgo_tag_svg('qr') . '</div><h1>Код не найден</h1><p>Проверьте, что сканируете адресник PetGo. Если код на наклейке стёрся — напишите нам.</p></div>');
    }
    $st  = (int) $t->st;
    $cl  = function_exists('petgo_cl_uid') ? petgo_cl_uid() : 0;
    $acc = function_exists('petgo_site_url') ? petgo_site_url('account') : home_url('/');
    if ($st === 2) {
        petgo_tag_shell('Адресник отключён', '<div class="card c"><div class="ic">' . petgo_tag_svg('qr') . '</div><h1>Адресник отключён</h1><p>Этот адресник больше не работает. Если вы нашли питомца — позвоните в PetGo, поможем найти хозяина.</p>' . petgo_tag_office_btn() . '</div>');
    }
    if ($st === 0) {
        petgo_tag_page_new($t, $cl, $acc);
    }
    petgo_tag_page_pet($t, $cl, $acc);
}

/* новый (свободный) адресник: привязать к питомцу */
function petgo_tag_page_new($t, $cl, $acc) {
    $code = (string) $t->code;
    $h    = '<div class="card c"><div class="ic">' . petgo_tag_svg('qr') . '</div><h1>Новый адресник PetGo</h1><p class="code">' . esc_html(petgo_tag_fmt($code)) . '</p>';
    $h   .= '<p>Привяжите его к своему питомцу. Если питомец потеряется, человек отсканирует QR-код и сразу свяжется с вами.</p>';
    if ($cl) {
        $pets = function_exists('petgo_cl_pets') ? petgo_cl_pets($cl) : array();
        if ($pets) {
            $h .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="bind"><input type="hidden" name="action" value="petgo_tag_bind"><input type="hidden" name="c" value="' . esc_attr($code) . '">' . wp_nonce_field('petgo_tag_bind', '_n', false, false) . '<b class="lbl">Чей это адресник?</b><div class="pets">';
            foreach ($pets as $i => $p) {
                $h .= '<label class="pet"><input type="radio" name="pet" value="' . (int) $p->ID . '"' . ($i === 0 ? ' checked' : '') . '><span>' . esc_html($p->post_title) . '</span></label>';
            }
            $h .= '</div><button type="submit" class="btn">Привязать</button></form>';
        } else {
            $h .= '<p class="mu">Сначала добавьте питомца в приложении — потом привяжите адресник.</p><a class="btn" href="' . esc_url($acc) . '">Открыть My PetGo</a>';
        }
    } else {
        /* после входа приложение само предложит привязать этот код */
        setcookie('petgo_tagc', $code, time() + DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);
        $h .= '<a class="btn" href="' . esc_url($acc) . '">Войти в My PetGo</a><p class="mu sm">Нет кабинета? Зарегистрируйтесь там же — после входа приложение само предложит привязать этот адресник.</p>';
    }
    if (function_exists('petgo_role_of') && petgo_role_of() !== '') {
        $b = petgo_tag_batches();
        $h .= '<p class="mu sm">Для сотрудника: партия №' . (int) $t->batch . (isset($b[(int) $t->batch]) ? ' от ' . esc_html(wp_date('d.m.Y', (int) $b[(int) $t->batch]['ts'], petgo_tz())) : '') . ', свободен.</p>';
    }
    petgo_tag_shell('Новый адресник PetGo', $h . '</div>');
}

/* привязанный адресник: анкета питомца для нашедшего */
function petgo_tag_page_pet($t, $cl, $acc) {
    global $wpdb;
    $pid  = (int) $t->pet;
    $uid  = (int) $t->uid;
    $code = (string) $t->code;
    if (get_post_type($pid) !== 'petgo_pet') {
        petgo_tag_shell('Адресник PetGo', '<div class="card c"><h1>Анкета недоступна</h1><p>Позвоните в PetGo — поможем найти хозяина.</p>' . petgo_tag_office_btn() . '</div>');
    }
    $o     = petgo_tag_opts($pid);
    $own   = $cl && $cl === $uid;
    $name  = get_the_title($pid);
    $owner = get_userdata($uid);
    $first = $owner ? trim(strtok((string) $owner->display_name, ' ')) : '';
    $sp    = (string) get_post_meta($pid, '_pf_species', true);
    $sub   = array_filter(array($sp === 'dog' ? 'Собака' : ($sp === 'cat' ? 'Кошка' : (string) get_post_meta($pid, '_pf_species_other', true)), (string) get_post_meta($pid, '_pf_breed', true)), 'strlen');
    /* сканирование: считаем и сообщаем хозяину (не чаще раза в 15 минут, если потерялся — раз в 2 минуты) */
    if (!$own && !petgo_tag_is_bot()) {
        $gap = $o['lost'] ? 2 * MINUTE_IN_SECONDS : 15 * MINUTE_IN_SECONDS;
        $wpdb->query($wpdb->prepare('UPDATE ' . petgo_tb('tags') . ' SET scans=scans+1, seen=%d WHERE id=%d', time(), (int) $t->id));
        if (time() - (int) $t->seen > $gap) {
            petgo_tag_notify($uid, $o['lost']
                ? sprintf('Адресник отсканировали: анкету «%s» открыли только что. Возможно, питомца нашли — держите телефон рядом.', $name)
                : sprintf('Адресник отсканировали: анкету «%s» открыли в %s.', $name, wp_date('H:i', time(), petgo_tz())));
        }
    }
    $ph  = petgo_tag_photo($pid);
    $tel = $o['phone'] ? petgo_tag_owner_phone($uid, $pid) : '';
    $h   = '';
    if ($own) {
        $h .= '<div class="own">' . petgo_tag_svg('eye') . '<span>Это ваш адресник. Так анкету видят те, кто отсканирует QR. Изменить — в приложении: питомец → «QR-адресник».</span></div>';
    }
    if (isset($_GET['ok'])) {
        $h .= '<div class="okb">' . petgo_tag_svg('check') . '<span>Готово! Адресник привязан к питомцу ' . esc_html($name) . '.</span></div>';
    }
    if ($o['lost']) {
        $h .= '<div class="lost"><b>Меня ищут!</b><span>Помогите мне вернуться домой — позвоните хозяину или отправьте, где я.</span></div>';
    }
    $h .= '<div class="card pet"><div class="av">' . ($ph ? '<img src="' . esc_attr($ph) . '" alt="">' : '<b>' . esc_html(mb_strtoupper(mb_substr($name, 0, 1))) . '</b>') . '</div>';
    $h .= '<h1>Привет! Я ' . esc_html($name) . '</h1>' . ($sub ? '<p class="sub">' . esc_html(implode(' · ', $sub)) . '</p>' : '');
    if ($first !== '') {
        $h .= '<p class="who">Мой хозяин — ' . esc_html($first) . '</p>';
    }
    if ($o['note'] !== '') {
        $h .= '<div class="note"><small>Записка от хозяина</small><p>' . esc_html($o['note']) . '</p></div>';
    }
    if ($tel !== '') {
        $wa = 'https://wa.me/' . $tel . '?text=' . rawurlencode('Здравствуйте! Я нашёл(ла) вашего питомца ' . $name . ' по адреснику PetGo.');
        $h .= '<div class="acts"><a class="btn" href="tel:+' . esc_attr($tel) . '">' . petgo_tag_svg('phone') . 'Позвонить хозяину</a><a class="btn wa" href="' . esc_url($wa) . '">' . petgo_tag_svg('wa') . 'Написать в WhatsApp</a></div>';
    }
    $h .= '</div>';
    /* сообщение хозяину с местом на карте */
    $h .= '<div class="card send" id="send"><h2>' . esc_html($tel !== '' ? 'Или отправьте хозяину, где вы' : 'Отправьте хозяину, где вы') . '</h2><p class="mu">Хозяин сразу получит уведомление' . ($tel === '' ? ' и свяжется с вами' : '') . '. Можно добавить сообщение.</p>';
    $h .= '<form data-send><textarea name="msg" maxlength="300" rows="2" placeholder="Например: собака со мной, жду у остановки Абая — Розыбакиева. Мой номер…"></textarea><input type="text" name="hp" value="" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true"><button type="submit" class="btn">' . petgo_tag_svg('pin') . 'Отправить хозяину, где я</button><p class="res" role="status" hidden></p></form></div>';
    if ($tel === '') {
        $h .= '<div class="card c"><p class="mu">Если нужна помощь — позвоните в PetGo.</p>' . petgo_tag_office_btn() . '</div>';
    }
    $js = '<script>(function(){var f=document.querySelector("[data-send]");if(!f)return;var r=f.querySelector(".res"),b=f.querySelector("button");'
        . 'function out(t,ok){r.hidden=false;r.textContent=t;r.className="res"+(ok?" ok":" bad")}'
        . 'function go(pos){var d=new FormData(f);d.append("action","petgo_tag_found");d.append("c",' . wp_json_encode($code) . ');d.append("_n",' . wp_json_encode(wp_create_nonce('petgo_tag_found')) . ');'
        . 'if(pos){d.append("lat",pos.coords.latitude);d.append("lng",pos.coords.longitude);d.append("acc",Math.round(pos.coords.accuracy||0))}'
        . 'fetch(' . wp_json_encode(admin_url('admin-ajax.php')) . ',{method:"POST",body:d,credentials:"same-origin"}).then(function(x){return x.json()}).then(function(j){b.disabled=false;b.removeAttribute("aria-busy");'
        . 'if(j&&j.ok){f.querySelector("textarea").value="";out(pos?"Спасибо! Хозяин получил уведомление и место на карте.":"Спасибо! Хозяин получил ваше сообщение. Место отправить не получилось — разрешите геолокацию и нажмите ещё раз.",true)}else out((j&&j.msg)||"Не получилось. Попробуйте ещё раз.",false)}).catch(function(){b.disabled=false;b.removeAttribute("aria-busy");out("Нет связи с интернетом. Попробуйте ещё раз.",false)})}'
        . 'f.addEventListener("submit",function(e){e.preventDefault();if(b.disabled)return;b.disabled=true;b.setAttribute("aria-busy","true");out("Определяем место…",true);'
        . 'if(!navigator.geolocation)return go(null);navigator.geolocation.getCurrentPosition(go,function(){go(null)},{enableHighAccuracy:true,timeout:12000,maximumAge:60000})})})();</script>';
    petgo_tag_shell($name . ' · PetGo', $h, $code, $js);
}

/* после входа на странице «Новый адресник»: привязать к выбранному питомцу */
add_action('admin_post_petgo_tag_bind', function () {
    $code = petgo_tag_norm(isset($_POST['c']) ? wp_unslash($_POST['c']) : '');
    $back = $code !== '' ? petgo_tag_url($code) : home_url('/');
    if (!isset($_POST['_n']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_n'])), 'petgo_tag_bind')) {
        wp_safe_redirect($back);
        exit;
    }
    $uid = function_exists('petgo_cl_uid') ? petgo_cl_uid() : 0;
    if (!$uid || !petgo_tag_limit('bind' . $uid, 20, HOUR_IN_SECONDS)) {
        wp_safe_redirect($back);
        exit;
    }
    $r = petgo_tag_bind($code, (int) (isset($_POST['pet']) ? $_POST['pet'] : 0), $uid);
    if ($r === true) {
        setcookie('petgo_tagc', '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);
        wp_safe_redirect(add_query_arg('ok', '1', $back));
        exit;
    }
    wp_die(esc_html($r), 'PetGo', array('response' => 200, 'back_link' => true));
});

/* нашедший отправил сообщение и (если разрешил) место */
add_action('wp_ajax_nopriv_petgo_tag_found', 'petgo_tag_found');
add_action('wp_ajax_petgo_tag_found', 'petgo_tag_found');
function petgo_tag_found() {
    nocache_headers();
    $out = function ($ok, $msg = '') {
        wp_send_json(array('ok' => $ok, 'msg' => $msg));
    };
    if (!empty($_POST['hp'])) {
        $out(true);
    }
    if (!isset($_POST['_n']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_n'])), 'petgo_tag_found') || (function_exists('petgo_guard_same_origin') && !petgo_guard_same_origin())) {
        $out(false, 'Обновите страницу и попробуйте ещё раз.');
    }
    if (!petgo_tag_limit('found', 6, 10 * MINUTE_IN_SECONDS)) {
        $out(false, 'Сообщение уже отправлено. Подождите несколько минут.');
    }
    $t = petgo_tag_get(isset($_POST['c']) ? wp_unslash($_POST['c']) : '');
    if (!$t || (int) $t->st !== 1 || get_post_type((int) $t->pet) !== 'petgo_pet') {
        $out(false, 'Адресник не найден.');
    }
    $msg = mb_substr(trim(sanitize_textarea_field(wp_unslash(isset($_POST['msg']) ? (string) $_POST['msg'] : ''))), 0, 300);
    $lat = isset($_POST['lat']) && is_numeric($_POST['lat']) ? (float) $_POST['lat'] : null;
    $lng = isset($_POST['lng']) && is_numeric($_POST['lng']) ? (float) $_POST['lng'] : null;
    if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180 || ($lat == 0 && $lng == 0)) {
        $lat = $lng = null;
    }
    if ($lat === null && $msg === '') {
        $out(false, 'Разрешите геолокацию или напишите сообщение.');
    }
    $pid  = (int) $t->pet;
    $name = get_the_title($pid);
    $f    = petgo_tag_finds($pid);
    array_unshift($f, array('ts' => time(), 'lat' => $lat, 'lng' => $lng, 'acc' => isset($_POST['acc']) ? (int) $_POST['acc'] : 0, 'msg' => $msg));
    update_post_meta($pid, '_tag_finds', array_slice($f, 0, 10));
    $text = $lat !== null
        ? sprintf('Питомца нашли: «%s». Человек отправил, где он сейчас%s', $name, $msg !== '' ? '. Сообщение: «' . $msg . '»' : ' — откройте карту.')
        : sprintf('Сообщение по адреснику: «%s» (анкета «%s»)', $msg, $name);
    petgo_tag_notify((int) $t->uid, $text, $lat, $lng);
    /* сотрудники тоже видят: можно помочь клиенту */
    if (function_exists('petgo_feed_add') && function_exists('petgo_push_users')) {
        $u = get_userdata((int) $t->uid);
        petgo_feed_add(petgo_push_users(array('owner', 'admin', 'manager')), 'Адресник: нашли питомца клиента', $name . ($u ? ' · ' . $u->display_name : '') . ($lat !== null ? ' · место отправлено' : '') . ($msg !== '' ? ' · «' . $msg . '»' : ''), $lat !== null ? petgo_tag_maps($lat, $lng) : '');
    }
    $out(true);
}

/* ---------- оформление открытой страницы ---------- */
function petgo_tag_office_btn() {
    $p = function_exists('petgo_badge_phone') ? petgo_badge_phone() : '';
    $d = preg_replace('/\D/', '', $p);
    return $d !== '' ? '<a class="btn sec" href="tel:+' . esc_attr($d) . '">' . petgo_tag_svg('phone') . 'PetGo: ' . esc_html($p) . '</a>' : '';
}
function petgo_tag_svg($n) {
    $P = array(
        'qr'    => '<rect x="3.5" y="3.5" width="6.5" height="6.5" rx="1.2"/><rect x="14" y="3.5" width="6.5" height="6.5" rx="1.2"/><rect x="3.5" y="14" width="6.5" height="6.5" rx="1.2"/><path d="M14 14h3v3M20.5 14v.01M14 20.5h3M20.5 17.5v3h-3"/>',
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'wa'    => '<path d="M20 11.6a8 8 0 0 1-11.8 7L4 20l1.4-4.1A8 8 0 1 1 20 11.6z"/><path d="M9 8.6c0 3.4 2.7 6.3 6 6.4l1.2-1.4-1.8-.9-.9.8c-1-.5-2-1.5-2.4-2.5l.8-.9-.8-1.8L9.7 7.6c-.4.2-.7.6-.7 1z"/>',
        'pin'   => '<path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'eye'   => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
        'check' => '<circle cx="12" cy="12" r="8.5"/><path d="M8.2 12.3l2.6 2.6 5-5.2"/>',
    );
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . (isset($P[$n]) ? $P[$n] : '') . '</svg>';
}
function petgo_tag_shell($title, $body, $code = '', $js = '') {
    $site = home_url('/');
    $paw  = function_exists('petgo_paw_pattern') ? petgo_paw_pattern('rgba(2,76,70,.045)') . ' 0 0/220px 220px repeat,' : '';
    $logo = function_exists('petgo_badge_logo') ? strtr(petgo_badge_logo(), array('fill="#fff"' => 'fill="#024C46"', 'stroke="#fff"' => 'stroke="#024C46"', 'fill="#024C46"' => 'fill="#FBF9E8"')) : 'PetGo';
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#F4F6F3"><title>' . esc_html($title) . '</title><style>'
        . '*{box-sizing:border-box}html,body{margin:0;min-height:100%;background:' . $paw . '#F4F6F3;color:#0F2A26;font:16px/1.45 -apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;-webkit-text-size-adjust:100%}'
        . 'main{max-width:440px;margin:0 auto;padding:calc(env(safe-area-inset-top,0px) + 18px) 16px calc(env(safe-area-inset-bottom,0px) + 28px);display:flex;flex-direction:column;gap:12px}'
        . '.top{display:flex;justify-content:center;padding:4px 0 6px}.top svg{width:112px;height:auto}'
        . '.card{background:#fff;border-radius:24px;padding:22px 18px;box-shadow:0 1px 2px rgba(15,42,38,.04),0 10px 30px rgba(15,42,38,.07)}.c{text-align:center}'
        . 'h1{margin:0 0 6px;font-size:26px;line-height:1.15;letter-spacing:-.02em}h2{margin:0 0 4px;font-size:18px}p{margin:0 0 12px}.mu{color:#6B7A77}.sm{font-size:13px;margin-top:12px}'
        . '.ic{width:64px;height:64px;margin:0 auto 12px;border-radius:20px;display:grid;place-items:center;background:#E1F2E9;color:#024C46}.ic svg{width:32px;height:32px}'
        . '.code{font:800 22px/1 ui-monospace,"SF Mono",Menlo,Consolas,monospace;letter-spacing:.08em;color:#024C46}'
        . '.btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;min-height:54px;margin-top:10px;padding:0 18px;border:0;border-radius:16px;background:#024C46;color:#fff;font-weight:600;font-size:17px;line-height:1.2;font-family:inherit;text-decoration:none;cursor:pointer;-webkit-tap-highlight-color:transparent}.btn svg{width:22px;height:22px;flex:none}.btn:active{transform:scale(.98)}.btn[disabled]{opacity:.7}'
        . '.btn.sec{background:#E1F2E9;color:#024C46}.btn.wa{background:#1FAF5A}'
        . '.pet{text-align:center;padding-top:26px}.av{width:132px;height:132px;margin:0 auto 14px;border-radius:50%;overflow:hidden;background:#E1F2E9;display:grid;place-items:center;border:4px solid #fff;box-shadow:0 6px 20px rgba(2,76,70,.18)}.av img{width:100%;height:100%;object-fit:cover}.av b{font-size:54px;color:#024C46}'
        . '.sub{color:#6B7A77;margin-top:-2px}.who{font-weight:600}.note{margin:4px 0 6px;padding:12px 14px;border-radius:16px;background:#FFF7E6;text-align:left}.note small{display:block;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#9A6A00}.note p{margin:2px 0 0}'
        . '.lost{display:flex;flex-direction:column;gap:2px;padding:14px 16px;border-radius:20px;background:#C63A2E;color:#fff;text-align:center}.lost b{font-size:20px}.lost span{font-size:14.5px;opacity:.92}'
        . '.own,.okb{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border-radius:18px;background:#E1F2E9;color:#024C46;font-size:14.5px}.own svg,.okb svg{width:22px;height:22px;flex:none}'
        . 'textarea{width:100%;margin-top:4px;padding:12px 14px;border:1px solid rgba(15,42,38,.14);border-radius:14px;font-size:16px;line-height:1.4;font-family:inherit;color:inherit;background:#F8FAF8;resize:vertical}textarea:focus{outline:2px solid #024C46;outline-offset:1px;border-color:transparent}'
        . '.hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}.res{margin:10px 0 0;font-size:14.5px}.res.ok{color:#1E7A4C}.res.bad{color:#C63A2E}'
        . '.bind{margin-top:6px;text-align:left}.lbl{display:block;margin-bottom:8px}.pets{display:flex;flex-wrap:wrap;gap:8px}.pet input{position:absolute;opacity:0}.pet span{display:inline-flex;min-height:44px;align-items:center;padding:0 16px;border-radius:14px;background:#F1F4F2;font-weight:600;cursor:pointer}.pet input:checked+span{background:#024C46;color:#fff}.pet input:focus-visible+span{outline:2px solid #024C46;outline-offset:2px}'
        . '.promo{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:20px;background:#024C46;color:#fff;text-decoration:none}.promo b{display:block;font-size:15px}.promo span{font-size:13.5px;opacity:.85}.promo i{margin-left:auto;font-style:normal;font-size:22px;opacity:.8}'
        . '.foot{text-align:center;font-size:12.5px;color:#6B7A77}'
        . '@media(prefers-reduced-motion:no-preference){.card,.lost{animation:up .4s cubic-bezier(.32,.72,0,1) both}@keyframes up{from{opacity:0;transform:translateY(8px)}}}'
        . '</style></head><body><main><a class="top" href="' . esc_url($site) . '" aria-label="PetGo">' . $logo . '</a>' . $body
        . '<a class="promo" href="' . esc_url($site) . '"><div><b>PetGo — выгул и присмотр за питомцами</b><span>Алматы · погуляем, покормим, присмотрим, пока вы заняты</span></div><i>›</i></a>'
        . '<p class="foot">Адресник PetGo' . ($code !== '' ? ' · ' . esc_html(petgo_tag_fmt($code)) : '') . '</p></main>' . $js . '</body></html>';
    exit;
}

/* ============================================================
   ПРИЛОЖЕНИЕ СОТРУДНИКОВ: «Адресники» (владелец и администратор)
   ============================================================ */
add_filter('petgo_nav_items', function ($items, $adm) {
    if ($adm && petgo_can('admin')) {
        $items['tags'] = array('Адресники', petgo_page_url(array('pg' => 'tags')), 'qr', 'd');
    }
    return $items;
}, 12, 2);
add_filter('petgo_app_title', function ($t, $pg) {
    return $pg === 'tags' ? 'Адресники' : $t;
}, 10, 2);
add_filter('petgo_app_page', function ($done, $pg) {
    if ($done || $pg !== 'tags') {
        return $done;
    }
    if (!petgo_can('admin')) {
        echo '<p class="mu">' . esc_html('Раздел доступен владельцу и администратору.') . '</p>';
        return true;
    }
    petgo_page_tags();
    return true;
}, 10, 2);

function petgo_tags_print_url($batch, $only = '') {
    return petgo_page_url(array_filter(array('petgo_tags' => (int) $batch, 'only' => $only)));
}
function petgo_tag_st_label($st) {
    return array(0 => 'Свободен', 1 => 'Привязан', 2 => 'Отключён')[(int) $st] ?? '—';
}

function petgo_page_tags() {
    global $wpdb;
    $tb  = petgo_tb('tags');
    $all = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tb");
    $act = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tb WHERE st=1");
    $scn = (int) $wpdb->get_var("SELECT COALESCE(SUM(scans),0) FROM $tb");
    $fmts = petgo_tag_formats();
    echo '<style>.pgtg{display:flex;flex-direction:column;gap:12px}.pgtg .pgc-card{padding:16px}.pgtg h3{margin:0 0 4px;font-size:17px}.pgtg p.mu{margin:0 0 12px;font-size:14px;line-height:1.45}'
        . '.pgtg-new .pgc-grid{display:grid;grid-template-columns:96px minmax(0,1fr);gap:10px}.pgtg-new select{min-width:0;width:100%;text-overflow:ellipsis}.pgtg-find{display:flex;gap:8px}.pgtg-find input{flex:1;min-width:0;text-transform:uppercase;font-family:var(--mono,ui-monospace,monospace);letter-spacing:.06em}'
        . '.pgtg-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-top:.5px solid var(--ln)}.pgtg-row:first-of-type{border-top:0}.pgtg-row .t{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}.pgtg-row .t b{font-size:15.5px}.pgtg-row .t small{color:var(--mu);font-size:13.5px}'
        . '.pgtg-row.hl{margin:0 -16px;padding:12px 16px;background:var(--acs);border-radius:14px}.pgtg-code{font:700 15px/1 var(--mono,ui-monospace,monospace);letter-spacing:.06em}'
        . '.pgtg-kv{display:grid;grid-template-columns:auto 1fr;gap:6px 14px;margin:10px 0 12px;font-size:14.5px}.pgtg-kv span{color:var(--mu)}.pgtg-st{display:inline-flex;padding:3px 10px;border-radius:99px;font-size:13px;font-weight:600;background:var(--so)}.pgtg-st.s1{background:rgba(30,154,95,.14);color:#1E7A4C}.pgtg-st.s2{background:rgba(198,58,46,.12);color:#C63A2E}'
        . '.pgtg .pgc-btns{display:flex;flex-wrap:wrap;gap:8px}</style>';
    echo '<div class="pgtg"><div class="pgc-stats three">' . petgo_stat_tile('Всего кодов', (string) $all, '', 'pri wide', '', 'qr') . petgo_stat_tile('Привязано', (string) $act, '', '', '', 'paw') . petgo_stat_tile('Сканирований', (string) $scn, '', '', '', 'eye') . '</div>';
    /* новая партия */
    $sel = '<label class="pgc-f"><span>Формат</span><select name="fmt">';
    foreach ($fmts as $k => $l) {
        $sel .= '<option value="' . esc_attr($k) . '"' . selected($k, 'v34', false) . '>' . esc_html($l) . '</option>';
    }
    $sel .= '</select></label>';
    echo '<div class="pgc-card pgtg-new"><h3>Новая партия адресников</h3><p class="mu">Каждый QR — уникальный и сразу записан в базу. Клиент сам привязывает его к питомцу: сканирует QR или вводит код в приложении. Бирка 3 × 4 см печатается с двух сторон: QR и логотип PetGo, по 42 на лист A4.</p>'
        . petgo_form_open('tags_new') . '<div class="pgc-grid"><label class="pgc-f"><span>Сколько штук</span><input type="number" name="n" value="40" min="1" max="400" inputmode="numeric" required></label>' . $sel . '</div><button class="pgc-btn wide" type="submit">' . petgo_ic('qr') . 'Создать партию</button></form></div>';
    /* поиск кода */
    $q   = isset($_GET['q']) ? petgo_tag_norm(wp_unslash($_GET['q'])) : '';
    $raw = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    $hid = '';
    parse_str((string) wp_parse_url(petgo_page_url(), PHP_URL_QUERY), $qa);
    foreach ($qa as $k => $v) {
        $hid .= '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($v) . '">';
    }
    echo '<div class="pgc-card"><h3>Найти адресник</h3><p class="mu">Введите код с наклейки — увидите, чей это питомец, и сможете отвязать или отключить адресник.</p><form method="get" action="' . esc_url(petgo_page_url()) . '" class="pgtg-find">' . $hid . '<input type="hidden" name="pg" value="tags"><input type="search" name="q" value="' . esc_attr($raw !== '' ? $raw : '') . '" placeholder="ABCD-2345" autocomplete="off" autocapitalize="characters" maxlength="12" aria-label="Код адресника"><button class="pgc-btn" type="submit">Найти</button></form>';
    if ($raw !== '') {
        $t = $q !== '' ? petgo_tag_get($q) : null;
        if (!$t) {
            echo '<p class="mu" style="margin:12px 0 0">Код не найден.</p>';
        } else {
            $u = (int) $t->uid ? get_userdata((int) $t->uid) : null;
            echo '<div class="pgtg-kv"><span>Код</span><b class="pgtg-code">' . esc_html(petgo_tag_fmt($t->code)) . '</b><span>Статус</span><span><i class="pgtg-st s' . (int) $t->st . '" style="font-style:normal">' . esc_html(petgo_tag_st_label($t->st)) . '</i></span><span>Партия</span><b>№' . (int) $t->batch . '</b>';
            if ((int) $t->st === 1) {
                echo '<span>Питомец</span><b>' . esc_html(get_the_title((int) $t->pet)) . '</b><span>Хозяин</span><b>' . esc_html($u ? $u->display_name . ' · ' . petgo_tag_phone_fmt(petgo_tag_owner_phone($u->ID, (int) $t->pet)) : '—') . '</b><span>Привязан</span><b>' . esc_html(petgo_fmt((int) $t->bound)) . '</b>';
            }
            echo '<span>Сканирований</span><b>' . (int) $t->scans . ((int) $t->seen ? ' · последний ' . esc_html(petgo_fmt((int) $t->seen)) : '') . '</b></div><div class="pgc-btns"><a class="pgc-btn sec" data-ext href="' . esc_url(petgo_tag_url($t->code)) . '" target="_blank" rel="noopener">' . petgo_ic('link') . 'Открыть страницу</a>';
            if ((int) $t->st === 1) {
                echo petgo_btn_form('tag_admin', array('c' => $t->code, 'do' => 'unbind'), 'Отвязать', 'pgc-btn sec', 'Отвязать адресник от питомца? Его можно будет привязать заново.');
            }
            echo (int) $t->st === 2 ? petgo_btn_form('tag_admin', array('c' => $t->code, 'do' => 'on'), 'Включить', 'pgc-btn sec') : petgo_btn_form('tag_admin', array('c' => $t->code, 'do' => 'off'), 'Отключить', 'pgc-btn red', 'Отключить адресник? При сканировании будет написано, что он не работает.');
            echo '</div>';
        }
    }
    echo '</div>';
    /* партии */
    $b = petgo_tag_batches();
    if ($b) {
        krsort($b);
        $cnt = array();
        foreach ((array) $wpdb->get_results("SELECT batch, SUM(CASE WHEN st=1 THEN 1 ELSE 0 END) a, COUNT(*) n FROM $tb GROUP BY batch") as $r) {
            $cnt[(int) $r->batch] = array((int) $r->a, (int) $r->n);
        }
        $new = isset($_GET['nb']) ? (int) $_GET['nb'] : 0;
        echo '<div class="pgc-card"><h3>Партии</h3>';
        foreach ($b as $id => $x) {
            $c = isset($cnt[(int) $id]) ? $cnt[(int) $id] : array(0, (int) $x['n']);
            echo '<div class="pgtg-row' . ($new === (int) $id ? ' hl' : '') . '"><span class="t"><b>№' . (int) $id . ' · ' . esc_html(isset($fmts[$x['fmt']]) ? $fmts[$x['fmt']] : '') . '</b><small>' . (int) $c[1] . ' шт · ' . esc_html(wp_date('d.m.Y', (int) $x['ts'], petgo_tz())) . ' · привязано ' . (int) $c[0] . '</small></span><a class="pgc-btn ' . ($new === (int) $id ? '' : 'sec ') . 'sm" data-ext href="' . esc_url(petgo_tags_print_url($id)) . '">' . petgo_ic('download') . 'Печать / PDF</a></div>';
        }
        echo '</div>';
    }
    /* последние привязанные */
    $last = (array) $wpdb->get_results("SELECT * FROM $tb WHERE st=1 ORDER BY bound DESC LIMIT 10");
    if ($last) {
        echo '<div class="pgc-card"><h3>Последние привязанные</h3>';
        foreach ($last as $t) {
            $u = get_userdata((int) $t->uid);
            echo '<a class="pgtg-row" style="color:inherit;text-decoration:none" href="' . esc_url(petgo_page_url(array('pg' => 'tags', 'q' => $t->code))) . '"><span class="t"><b>' . esc_html(get_the_title((int) $t->pet)) . ' · <span class="pgtg-code">' . esc_html(petgo_tag_fmt($t->code)) . '</span></b><small>' . esc_html(($u ? $u->display_name . ' · ' : '') . petgo_fmt((int) $t->bound) . ' · сканирований: ' . (int) $t->scans) . '</small></span>' . petgo_ic('chev') . '</a>';
        }
        echo '</div>';
    }
    echo '</div>';
}

add_action('admin_post_petgo_tags_new', function () {
    petgo_guard('admin');
    $id = petgo_tag_make((int) petgo_p('n'), sanitize_key((string) petgo_p('fmt')));
    $b  = petgo_tag_batches();
    petgo_flash('Партия №' . $id . ' готова: ' . (int) $b[$id]['n'] . ' шт. Нажмите «Печать / PDF».');
    petgo_back('tags', array('nb' => $id));
});
add_action('admin_post_petgo_tag_admin', function () {
    petgo_guard('admin');
    global $wpdb;
    $c  = petgo_tag_norm((string) petgo_p('c'));
    $do = (string) petgo_p('do');
    if ($c !== '') {
        if ($do === 'unbind') {
            petgo_tag_unbind($c);
        } elseif ($do === 'off') {
            $wpdb->update(petgo_tb('tags'), array('st' => 2), array('code' => $c), array('%d'), array('%s'));
        } elseif ($do === 'on') {
            $wpdb->query($wpdb->prepare('UPDATE ' . petgo_tb('tags') . ' SET st=(CASE WHEN pet>0 THEN 1 ELSE 0 END) WHERE code=%s AND st=2', $c));
        }
        petgo_audit('tag_' . sanitize_key($do), $c);
        petgo_flash('Готово');
    }
    petgo_back('tags', array('q' => $c));
});

/* ============================================================
   ЛИСТ ПЕЧАТИ НАКЛЕЕК (внутри приложения, как бейджи): ?petgo_tags=ПАРТИЯ
   ============================================================ */
add_action('template_redirect', function () {
    if (isset($_GET['petgo_tags'])) {
        petgo_tags_print();
    }
}, 0);

function petgo_tag_mark() {
    return '<svg viewBox="0 4 64 64" aria-hidden="true"><circle cx="32" cy="36" r="32" fill="#fff"/><g transform="translate(0 4)" fill="#024C46"><path d="M32 36c-8 0-14 6-14 11 0 5 4 7 8 6 3-1 4-2 6-2s3 1 6 2c4 1 8-1 8-6 0-5-6-11-14-11z"/><ellipse cx="19" cy="30" rx="4.5" ry="6" transform="rotate(-20 19 30)"/><ellipse cx="27" cy="22" rx="4.5" ry="6.5" transform="rotate(-6 27 22)"/><ellipse cx="37" cy="22" rx="4.5" ry="6.5" transform="rotate(6 37 22)"/><ellipse cx="45" cy="30" rx="4.5" ry="6" transform="rotate(20 45 30)"/></g></svg>';
}

function petgo_tag_word() {
    return '<svg viewBox="78 6 172 63" aria-hidden="true"><g transform="translate(76 4) scale(.62)" fill="none" stroke="#fff" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 30V92"/><circle cx="34" cy="50" r="20"/><path d="M72 50H112A20 20 0 1 0 106.14 64.14"/><path d="M142 12V54Q142 70 158 70"/><path d="M130 32H156"/><circle cx="196" cy="50" r="20"/><path d="M216 30V80Q216 96 196 96Q182 96 176 88"/><circle cx="254" cy="50" r="20"/></g></svg>';
}

/* одна наклейка / бирка (лицо) */
function petgo_tag_face($fmt, $c, $base) {
    $q = '<span class="tg-q" data-q="' . esc_attr($base . $c) . '"></span>';
    if ($fmt === 'v34') {
        return '<div class="tg v" data-c="' . esc_attr($c) . '"><i class="tg-t">НАШЛИ МЕНЯ?</i>' . $q . '<b class="tg-c">' . esc_html(petgo_tag_fmt($c)) . '</b><b class="tg-u">petgo.kz</b></div>';
    }
    if ($fmt === 'b40') {
        return '<div class="tg b" data-c="' . esc_attr($c) . '">' . $q . '<span class="tg-m">' . petgo_tag_mark() . '</span><i class="tg-t">НАШЛИ<br>МЕНЯ?</i><b class="tg-c">' . esc_html(substr($c, 0, 4)) . '<br>' . esc_html(substr($c, 4)) . '</b></div>';
    }
    return '<div class="tg r" data-c="' . esc_attr($c) . '"><i class="tg-t">НАШЛИ МЕНЯ?</i>' . $q . '<b class="tg-c">' . esc_html(petgo_tag_fmt($c)) . '</b></div>';
}
/* оборот бирки — логотип PetGo */
function petgo_tag_back() {
    return '<div class="tg v bk"><span class="tg-m">' . petgo_tag_mark() . '</span><span class="tg-w">' . petgo_tag_word() . '</span><i class="tg-s">выгул и присмотр<br>за питомцами</i><b class="tg-u">petgo.kz</b></div>';
}

function petgo_tags_print() {
    if (!is_user_logged_in() || !function_exists('petgo_can') || !petgo_can('admin')) {
        wp_safe_redirect(petgo_page_url());
        exit;
    }
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    global $wpdb;
    $bid = (int) $_GET['petgo_tags'];
    $b   = petgo_tag_batches();
    if (!isset($b[$bid])) {
        wp_safe_redirect(petgo_page_url(array('pg' => 'tags')));
        exit;
    }
    $fmts = petgo_tag_formats();
    $fmt  = (isset($_GET['fmt']) && isset($fmts[(string) $_GET['fmt']])) ? (string) $_GET['fmt'] : (string) $b[$bid]['fmt'];
    $fmt  = isset($fmts[$fmt]) ? $fmt : 'v34';
    $only = isset($_GET['only']) && $_GET['only'] === 'free';
    $rows = (array) $wpdb->get_col($wpdb->prepare('SELECT code FROM ' . petgo_tb('tags') . ' WHERE batch=%d' . ($only ? ' AND st=0' : '') . ' ORDER BY id', $bid));
    $base = add_query_arg('pt', '', home_url('/'));
    $base = substr($base, -1) === '=' ? $base : $base . '=';
    $two  = $fmt === 'v34';
    $mm   = array('v34' => 30, 'r30' => 30, 'r25' => 25, 'r35' => 35, 'b40' => 40);
    $scr  = array('v34' => 132, 'r30' => 150, 'r25' => 132, 'r35' => 168, 'b40' => 236);
    $paw  = petgo_paw_pattern('rgba(255,255,255,.07)');
    $self = function ($args) use ($bid, $fmt, $only) {
        return petgo_page_url(array_filter(array_merge(array('petgo_tags' => $bid, 'fmt' => $fmt, 'only' => $only ? 'free' : ''), $args)));
    };
    $cards = '';
    foreach ($rows as $c) {
        $cards .= '<div class="cut">' . petgo_tag_face($fmt, $c, $base) . '</div>';
    }
    /* бирки 3 × 4: листы A4 для печати из браузера — 6 × 7 вплотную, метки для резки, за каждым листом — обороты (зеркально) */
    $pages = '';
    if ($two && $rows) {
        $cols = 6;
        $per  = 42;
        $mark = function () {
            $h = '';
            for ($i = 0; $i <= 6; $i++) {
                $x  = 15 + $i * 30;
                $h .= '<i class="cm v" style="left:' . $x . 'mm;top:2mm"></i><i class="cm v" style="left:' . $x . 'mm;top:290.5mm"></i>';
            }
            for ($j = 0; $j <= 7; $j++) {
                $y  = 8.5 + $j * 40;
                $h .= '<i class="cm h" style="top:' . $y . 'mm;left:6mm"></i><i class="cm h" style="top:' . $y . 'mm;left:196.5mm"></i>';
            }
            return $h;
        };
        foreach (array_chunk($rows, $per) as $chunk) {
            $f  = '';
            $bk = array_fill(0, $per, '<span></span>');
            foreach ($chunk as $i => $c) {
                $f .= petgo_tag_face($fmt, $c, $base);
                $bk[(int) floor($i / $cols) * $cols + ($cols - 1 - $i % $cols)] = petgo_tag_back();
            }
            $pages .= '<section class="pp">' . $mark() . '<div class="pg">' . $f . '</div></section><section class="pp">' . $mark() . '<div class="pg">' . implode('', array_slice($bk, 0, (int) ceil(count($chunk) / $cols) * $cols)) . '</div></section>';
        }
    }
    $chips = '';
    foreach ($fmts as $k => $l) {
        $chips .= '<a class="chip' . ($k === $fmt ? ' on' : '') . '" href="' . esc_url($self(array('fmt' => $k))) . '">' . esc_html($l) . '</a>';
    }
    $chips .= '<a class="chip' . ($only ? ' on' : '') . '" href="' . esc_url($only ? $self(array('only' => '')) : $self(array('only' => 'free'))) . '">' . esc_html('Только свободные') . '</a>';
    $hint = $two
        ? 'Бирка 3 × 4 см, две стороны: лист с QR, за ним — лист с логотипом для обратной стороны (зеркально). Печатайте двусторонне, переворот по длинному краю, масштаб 100%. По 42 на лист A4, режьте по меткам на полях.'
        : $fmts[$fmt] . '. Печатайте на самоклеящейся бумаге в масштабе 100%. Каждый QR уникален и уже в базе.';
    petgo_audit('tags_print', '№' . $bid . ' · ' . count($rows));
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    $FONT = '-apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI",Roboto,Arial,sans-serif';
    $css  = '@view-transition{navigation:auto}@media(prefers-reduced-motion:reduce){@view-transition{navigation:none}}@page{size:A4;margin:' . ($two ? '0' : '10mm') . '}'
        . 'html,body{margin:0;background:#F4F6F3;font-family:' . $FONT . ';color:#0F2A26}'
        . '.bar{position:sticky;top:0;z-index:2;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:10px;padding:calc(env(safe-area-inset-top,0px) + 14px) 16px 14px;background:rgba(244,246,243,.95);-webkit-backdrop-filter:blur(16px);backdrop-filter:blur(16px);border-bottom:.5px solid rgba(15,42,38,.1)}'
        . '.bar>b{flex-basis:100%;text-align:center;font-size:16px}.bar>.hint{flex-basis:100%;max-width:640px;text-align:center;font-size:13px;line-height:1.4;color:#6B7A77}'
        . '.chips{flex-basis:100%;display:flex;gap:6px;overflow-x:auto;padding:2px 0;scrollbar-width:none;justify-content:safe center}.chips::-webkit-scrollbar{display:none}.chip{flex:none;padding:8px 12px;border-radius:99px;background:#fff;color:#0F2A26;font-size:13.5px;font-weight:600;text-decoration:none;box-shadow:0 1px 2px rgba(15,42,38,.06)}.chip.on{background:#024C46;color:#fff}'
        . '.bar button,.bar a.b{flex:1 1 140px;max-width:240px;display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 20px;border:0;border-radius:14px;background:#024C46;color:#fff;font-weight:600;font-size:16px;font-family:inherit;text-decoration:none;cursor:pointer}.bar .sec,.bar a.b.sec{background:#E1F2E9;color:#024C46}.bar [aria-busy]{opacity:.7}'
        . '.sheet{display:flex;flex-wrap:wrap;justify-content:center;gap:16px;padding:24px 16px 40px}.cut{padding:0}'
        . '.side{margin:8px 16px 0;text-align:center;font-size:13px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#6B7A77}.sheet.one{padding-top:12px}'
        /* общее для всех наклеек */
        . '.tg{--w:' . (int) $scr[$fmt] . 'px;container-type:inline-size;position:relative;width:var(--w);overflow:hidden;color:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact;box-shadow:0 6px 18px rgba(2,76,70,.22)}'
        . '.tg-q{position:absolute;display:block;background:#fff}.tg-q svg{display:block;width:100%;height:100%}'
        . '.tg-t{position:absolute;left:0;right:0;font-style:normal;font-weight:800;text-align:center;letter-spacing:.03em;line-height:1}'
        . '.tg-c,.tg-u{position:absolute;left:0;right:0;text-align:center;font-family:"Helvetica Neue",Helvetica,Arial,sans-serif;font-weight:700;line-height:1;letter-spacing:.02em}'
        . '.tg-m{position:absolute}.tg-m svg,.tg-w svg{display:block;width:100%;height:100%}'
        /* круг */
        . '.tg.r{aspect-ratio:1;border-radius:50%;background:' . $paw . ' 0 0/60cqw 60cqw repeat,radial-gradient(circle at 50% 30%,#0B6B5C,#024C46 70%)}.tg.r .tg-q{left:17cqw;top:17cqw;width:66cqw;height:66cqw;border-radius:7cqw}.tg.r .tg-t{top:8.6cqw;font-size:6.2cqw}.tg.r .tg-c{top:auto;bottom:8.6cqw;font-size:6.6cqw}'
        /* прямоугольник 40 × 25 */
        . '.tg.b{aspect-ratio:40/25;border-radius:7.5cqw;background:' . $paw . ' 0 0/60cqw 60cqw repeat,linear-gradient(160deg,#0B6B5C,#024C46 70%)}.tg.b .tg-q{left:5cqw;top:5cqw;width:52.5cqw;height:52.5cqw;border-radius:5cqw}.tg.b .tg-t{left:62.5cqw;right:5cqw;top:24cqw;font-size:5.6cqw;line-height:1.16}.tg.b .tg-c{left:62.5cqw;right:5cqw;top:39cqw;font-size:6.4cqw;line-height:1.25}.tg.b .tg-m{left:71.75cqw;top:6cqw;width:14cqw;height:14cqw}'
        /* бирка 3 × 4: лицо и оборот */
        . '.tg.v{aspect-ratio:30/40;border-radius:4cqw;background:' . $paw . ' 0 0/60cqw 60cqw repeat,linear-gradient(165deg,#0B6B5C,#024C46 70%)}.tg.v .tg-q{left:10cqw;top:19cqw;width:80cqw;height:80cqw;border-radius:6cqw}.tg.v .tg-t{top:6.8cqw;font-size:7.4cqw}.tg.v .tg-c{top:105.3cqw;font-size:8.4cqw}.tg.v .tg-u{top:118.7cqw;font-size:5.6cqw;color:#BFE3D2;letter-spacing:.04em}'
        . '.tg.v.bk{background:' . petgo_paw_pattern('rgba(255,255,255,.09)') . ' 0 0/60cqw 60cqw repeat,linear-gradient(195deg,#0B6B5C,#024C46 70%)}.tg.v.bk .tg-m{left:27cqw;top:18cqw;width:46cqw;height:46cqw}.tg-w{position:absolute;left:20cqw;top:72cqw;width:60cqw;height:22cqw}.tg-s{position:absolute;left:0;right:0;top:99.3cqw;font-style:normal;font-weight:500;font-size:5.4cqw;line-height:1.3;color:rgba(255,255,255,.82);text-align:center}.tg.v.bk .tg-u{top:119cqw;font-size:6.2cqw;letter-spacing:.02em}'
        . '.pps{display:none}.empty{padding:40px 16px;text-align:center;color:#6B7A77}'
        . ($two
            ? '@media print{html,body{background:#fff}.bar,.side,.sheet{display:none!important}.pps{display:block}.pp{position:relative;width:210mm;height:297mm;overflow:hidden;break-after:page;page-break-after:always}.pp:last-child{break-after:auto;page-break-after:auto}.pg{position:absolute;left:15mm;top:8.5mm;display:grid;grid-template-columns:repeat(6,30mm);grid-auto-rows:40mm}.pg .tg{--w:30mm;border-radius:0;box-shadow:none}.cm{position:absolute;background:#333}.cm.v{width:.1mm;height:4.5mm}.cm.h{height:.1mm;width:7.5mm}}'
            : '@media print{html,body{background:#fff}.bar{display:none}.sheet{gap:4mm;padding:0}.tg{--w:' . (int) $mm[$fmt] . 'mm;box-shadow:none}.cut{border:.2mm dashed #B8C4C0;padding:.4mm;break-inside:avoid}.cut:has(.r){border-radius:50%}.cut:has(.b){border-radius:3.6mm}}');
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#F4F6F3"><title>Адресники PetGo</title><style>' . $css . '</style></head><body>'
        . '<div class="bar"><b>Адресники · партия №' . (int) $bid . ' · ' . count($rows) . ' шт</b><span class="hint">' . esc_html($hint) . '</span><div class="chips">' . $chips . '</div>'
        . '<button type="button" data-pgt-pdf><span>Скачать PDF</span></button><button type="button" class="sec" data-pgt-print><span>Распечатать</span></button><a class="b sec" href="' . esc_url(petgo_page_url(array('pg' => 'tags'))) . '" onclick="if(document.referrer.indexOf(location.origin)===0&&history.length>1){history.back();return false}">Назад</a></div>'
        . ($rows
            ? ($two ? '<p class="side">Лицевая сторона · QR</p>' : '') . '<div class="sheet" data-pgt-sheet data-fmt="' . esc_attr($fmt) . '" data-base="' . esc_attr($base) . '" data-batch="' . (int) $bid . '">' . $cards . '</div>'
                . ($two ? '<p class="side">Обратная сторона · одинаковая для всех</p><div class="sheet one"><div class="cut">' . petgo_tag_back() . '</div></div><div class="pps">' . $pages . '</div>' : '')
            : '<p class="empty">В этой партии нет свободных адресников.</p>')
        . '<script src="' . esc_url(petgo_asset_url('vendor/qrcode.min.js')) . '"></script><script src="' . esc_url(add_query_arg('v', PETGO_VER, petgo_asset_url('badge.js'))) . '"></script><script src="' . esc_url(add_query_arg('v', PETGO_VER, petgo_asset_url('tags.js'))) . '"></script></body></html>';
    exit;
}
