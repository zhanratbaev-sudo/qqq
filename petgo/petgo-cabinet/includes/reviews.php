<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ОТЗЫВЫ: ссылка после заказа, страница отзыва, модерация, шорткод [petgo_reviews]
   ============================================================ */
add_action('init', 'petgo_ext_install', 5);
function petgo_ext_install() {
    if (get_option('petgo_ext_ver') === '1') {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $cc = $wpdb->get_charset_collate();
    $p  = $wpdb->prefix . 'petgo_';
    dbDelta("CREATE TABLE {$p}reviews (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_id bigint(20) unsigned NOT NULL DEFAULT 0,
  nanny_id bigint(20) unsigned NOT NULL DEFAULT 0,
  name varchar(80) NOT NULL DEFAULT '',
  rating tinyint(1) unsigned NOT NULL DEFAULT 5,
  body text,
  photos text,
  status varchar(8) NOT NULL DEFAULT 'pending',
  ts int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY order_id (order_id),
  KEY status (status,id),
  KEY nanny_id (nanny_id)
) $cc;");
    dbDelta("CREATE TABLE {$p}log (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ts int(10) unsigned NOT NULL DEFAULT 0,
  uid bigint(20) unsigned NOT NULL DEFAULT 0,
  act varchar(40) NOT NULL DEFAULT '',
  info varchar(255) NOT NULL DEFAULT '',
  ip varchar(45) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY ts (ts)
) $cc;");
    update_option('petgo_ext_ver', '1', false);
}

function petgo_rv_token($oid) {
    return substr(hash_hmac('sha256', 'rv|' . (int) $oid, wp_salt('auth')), 0, 20);
}

function petgo_rv_link($oid) {
    return home_url('/?petgo_rv=' . (int) $oid . '-' . petgo_rv_token($oid));
}

function petgo_rv_pending() {
    static $n = null;
    if ($n === null) {
        global $wpdb;
        $n = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . petgo_tb('reviews') . " WHERE status='pending'");
    }
    return $n;
}

function petgo_rv_dir() {
    $ud = wp_upload_dir();
    return array('path' => wp_normalize_path($ud['basedir'] . '/petgo-reviews'), 'url' => $ud['baseurl'] . '/petgo-reviews');
}

function petgo_rv_by_order($oid) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . petgo_tb('reviews') . ' WHERE order_id=%d', (int) $oid));
}

function petgo_rv_photos($r) {
    $a = ($r->photos !== null && $r->photos !== '') ? json_decode($r->photos, true) : array();
    $o = array();
    $d = petgo_rv_dir();
    if (is_array($a)) {
        foreach ($a as $f) {
            if (is_string($f) && preg_match('/^[A-Za-z0-9]{6,40}\.(webp|jpg)$/', $f)) {
                $o[] = $d['url'] . '/' . $f;
            }
        }
    }
    return $o;
}

/* рейтинг няни по опубликованным отзывам */
function petgo_nanny_rating($uid) {
    global $wpdb;
    $r = $wpdb->get_row($wpdb->prepare('SELECT AVG(rating) a, COUNT(*) n FROM ' . petgo_tb('reviews') . " WHERE nanny_id=%d AND status='ok'", (int) $uid));
    return array($r && $r->n ? round((float) $r->a, 1) : 0, $r ? (int) $r->n : 0);
}

/* ---------- блок в окне заказа: ссылка клиенту ---------- */
function petgo_rv_order_block($oid, $admin) {
    if (!$admin || !in_array(petgo_o($oid, 'status'), array('done', 'confirmed'), true)) {
        return '';
    }
    $rv = petgo_rv_by_order($oid);
    if ($rv) {
        return '<div class="pgc-tip">' . esc_html('Отзыв получен: ' . (int) $rv->rating . ' из 5') . '</div>';
    }
    $link  = petgo_rv_link($oid);
    $phone = preg_replace('/\D/', '', (string) get_post_meta((int) petgo_o($oid, 'pet'), '_pf_phone', true));
    $text  = 'Здравствуйте! Спасибо, что доверили нам питомца. Оставьте, пожалуйста, отзыв: ' . $link;
    $wa    = $phone ? 'https://wa.me/' . $phone . '?text=' . rawurlencode($text) : 'https://wa.me/?text=' . rawurlencode($text);
    return '<div class="pgc-tip"><b>' . esc_html('Запросить отзыв') . '</b><br><input type="text" readonly value="' . esc_attr($link) . '" onfocus="this.select()" style="margin:8px 0"><a class="pgc-btn" target="_blank" rel="noopener" href="' . esc_url($wa) . '">' . esc_html('Отправить в WhatsApp') . '</a></div>';
}

/* ---------- публичная страница отзыва (по личной ссылке) ---------- */
add_action('template_redirect', 'petgo_rv_page', 2);
function petgo_rv_page() {
    if (!isset($_GET['petgo_rv'])) {
        return;
    }
    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    $raw = sanitize_text_field(wp_unslash($_GET['petgo_rv']));
    $st  = 'bad';
    $oid = 0;
    $own = '';
    if (preg_match('/^(\d{1,9})-([a-f0-9]{20})$/', $raw, $m) && hash_equals(petgo_rv_token((int) $m[1]), $m[2]) && get_post_type((int) $m[1]) === 'petgo_order') {
        $oid = (int) $m[1];
        if (!in_array(petgo_o($oid, 'status'), array('done', 'confirmed'), true)) {
            $st = 'closed';
        } elseif (petgo_rv_by_order($oid)) {
            $st = isset($_GET['ok']) ? 'thanks' : 'done';
        } else {
            $st  = 'form';
            $own = (string) get_post_meta((int) petgo_o($oid, 'pet'), '_pf_owner', true);
        }
    }
    $logo = plugins_url('assets/icon-192.png', dirname(__DIR__) . '/petgo-cabinet.php');
    $msg  = array(
        'bad'    => 'Ссылка недействительна. Попросите у нас новую.',
        'closed' => 'Отзыв можно оставить после завершения прогулки.',
        'done'   => 'Отзыв уже получен. Спасибо!',
        'thanks' => 'Спасибо за отзыв! Он очень помогает нам.',
    );
    status_header(200);
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Отзыв · PetGo</title>';
    echo '<style>*{box-sizing:border-box}body{margin:0;background:#FBF9E8;color:#024C46;font-family:Manrope,system-ui,-apple-system,"Segoe UI",sans-serif;font-size:16px;line-height:1.5}.w{max-width:520px;margin:0 auto;padding:24px 18px 48px}.lg{display:flex;align-items:center;gap:12px;margin-bottom:20px;font-weight:800;font-size:20px}.lg img{width:44px;height:44px;border-radius:50%}h1{font-size:26px;line-height:1.2;margin:0 0 6px}p{margin:0 0 14px}.m{color:#5f8480}.c{background:#fff;border:1px solid #e3e0cc;border-radius:12px;padding:18px}label.f{display:block;font-weight:700;font-size:14px;margin:14px 0 6px}input[type=text],textarea{width:100%;padding:12px;border:1px solid #e3e0cc;border-radius:12px;font:inherit;color:#024C46;background:#fff}textarea{min-height:120px;resize:vertical}.st{display:flex;gap:6px;flex-direction:row-reverse;justify-content:flex-end}.st input{position:absolute;opacity:0;pointer-events:none}.st label{cursor:pointer;font-size:40px;line-height:1;color:#ece8cf}.st input:checked~label,.st label:hover,.st label:hover~label{color:#f5a623}.st input:focus-visible+label{outline:3px solid #024C46;border-radius:8px}button.go{width:100%;min-height:52px;margin-top:18px;border:0;border-radius:12px;background:#024C46;color:#fff;font:800 16px Manrope,system-ui,sans-serif;cursor:pointer}button.go:disabled{opacity:.6}.ph{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}.ph img{width:72px;height:72px;object-fit:cover;border-radius:10px;border:1px solid #e3e0cc}.ck{display:flex;gap:10px;align-items:flex-start;margin-top:16px;font-size:14px;color:#5f8480}.ck input{width:20px;height:20px;margin:2px 0 0;accent-color:#024C46;flex:none}.e{color:#c0392b;font-weight:700;margin-top:12px}.hp{position:absolute;left:-9999px}</style></head><body><div class="w"><div class="lg"><img src="' . esc_url($logo) . '" alt=""><span>PetGo</span></div>';
    if ($st !== 'form') {
        echo '<div class="c"><h1>' . esc_html($st === 'thanks' ? 'Спасибо!' : 'Отзыв') . '</h1><p>' . esc_html($msg[$st]) . '</p></div></div></body></html>';
        exit;
    }
    $nonce = wp_create_nonce('petgo_rv_' . $oid);
    echo '<h1>Как всё прошло?</h1><p class="m">Ваш отзыв увидят другие хозяева. Оценка и текст помогают нам стать лучше.</p><form class="c" id="f" novalidate><input type="hidden" name="t" value="' . esc_attr($raw) . '"><input type="hidden" name="n" value="' . esc_attr($nonce) . '"><div class="hp"><input type="text" name="web" tabindex="-1" autocomplete="off"></div>';
    echo '<label class="f">Оценка</label><div class="st">';
    for ($i = 5; $i >= 1; $i--) {
        echo '<input type="radio" name="rating" id="r' . $i . '" value="' . $i . '"' . ($i === 5 ? ' checked' : '') . '><label for="r' . $i . '" title="' . $i . '">★</label>';
    }
    echo '</div><label class="f" for="nm">Ваше имя</label><input type="text" id="nm" name="name" maxlength="80" value="' . esc_attr($own) . '" autocomplete="given-name"><label class="f" for="tx">Отзыв</label><textarea id="tx" name="text" maxlength="1500" placeholder="Расскажите, как прошла прогулка или визит"></textarea>';
    echo '<label class="f" for="ph">Фото (до 5, необязательно)</label><input type="file" id="ph" accept="image/*" multiple><div class="ph" id="pv"></div>';
    echo '<label class="ck"><input type="checkbox" name="consent" value="1"><span>Я согласен на публикацию отзыва и фото на сайте PetGo.</span></label><div class="e" id="er" hidden></div><button class="go" type="submit" id="go">Отправить отзыв</button></form></div>';
    echo '<script>(function(){var f=document.getElementById("f"),inp=document.getElementById("ph"),pv=document.getElementById("pv"),er=document.getElementById("er"),go=document.getElementById("go"),files=[];var AJ=' . wp_json_encode(admin_url('admin-ajax.php')) . ';';
    echo 'function shrink(file){return new Promise(function(ok){try{var u=URL.createObjectURL(file),im=new Image();im.onload=function(){var m=1600,s=Math.min(1,m/Math.max(im.width,im.height)),c=document.createElement("canvas");c.width=Math.round(im.width*s);c.height=Math.round(im.height*s);c.getContext("2d").drawImage(im,0,0,c.width,c.height);URL.revokeObjectURL(u);c.toBlob(function(b){ok(b||file)},"image/jpeg",.82)};im.onerror=function(){ok(file)};im.src=u}catch(e){ok(file)}})}';
    echo 'inp.addEventListener("change",function(){files=[].slice.call(inp.files).slice(0,5);pv.textContent="";files.forEach(function(x){var i=document.createElement("img");i.src=URL.createObjectURL(x);pv.appendChild(i)})});';
    echo 'function fail(t){er.textContent=t;er.hidden=false;go.disabled=false}';
    echo 'f.addEventListener("submit",function(e){e.preventDefault();er.hidden=true;if(!f.consent.checked){fail("Отметьте согласие на публикацию");return}if(f.text.value.trim().length<5){fail("Напишите пару слов об отзыве");return}go.disabled=true;go.textContent="Отправляем...";var fd=new FormData(f);fd.append("action","petgo_rv_send");Promise.all(files.map(shrink)).then(function(bs){bs.forEach(function(b,i){fd.append("photos[]",b,"p"+i+".jpg")});return fetch(AJ,{method:"POST",body:fd,credentials:"same-origin"})}).then(function(r){return r.json()}).then(function(d){if(d&&d.ok){location.href=location.pathname+"?petgo_rv="+encodeURIComponent(f.t.value)+"&ok=1"}else{go.textContent="Отправить отзыв";fail((d&&d.err)||"Не удалось отправить")}}).catch(function(){go.textContent="Отправить отзыв";fail("Нет соединения. Попробуйте ещё раз")})});';
    echo '})();</script></body></html>';
    exit;
}

/* ---------- приём отзыва ---------- */
add_action('wp_ajax_nopriv_petgo_rv_send', 'petgo_rv_send');
add_action('wp_ajax_petgo_rv_send', 'petgo_rv_send');
function petgo_rv_send() {
    $fail = function ($t) {
        wp_send_json(array('ok' => false, 'err' => $t));
    };
    if (!empty($_POST['web'])) {
        wp_send_json(array('ok' => true));
    }
    $raw = isset($_POST['t']) ? sanitize_text_field(wp_unslash($_POST['t'])) : '';
    if (!preg_match('/^(\d{1,9})-([a-f0-9]{20})$/', $raw, $m) || !hash_equals(petgo_rv_token((int) $m[1]), $m[2])) {
        $fail('Ссылка недействительна');
    }
    $oid = (int) $m[1];
    if (get_post_type($oid) !== 'petgo_order' || !in_array(petgo_o($oid, 'status'), array('done', 'confirmed'), true)) {
        $fail('Отзыв пока нельзя оставить');
    }
    if (!isset($_POST['n']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['n'])), 'petgo_rv_' . $oid)) {
        $fail('Страница устарела, обновите её');
    }
    $ik = 'petgo_rvi_' . md5(petgo_ip());
    if ((int) get_transient($ik) >= 6) {
        $fail('Слишком много попыток. Попробуйте позже');
    }
    set_transient($ik, (int) get_transient($ik) + 1, HOUR_IN_SECONDS);
    if (petgo_rv_by_order($oid)) {
        $fail('Отзыв уже получен');
    }
    $name   = mb_substr(sanitize_text_field(isset($_POST['name']) ? wp_unslash($_POST['name']) : ''), 0, 80);
    $text   = mb_substr(sanitize_textarea_field(isset($_POST['text']) ? wp_unslash($_POST['text']) : ''), 0, 1500);
    $rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
    if (mb_strlen($name) < 2) {
        $fail('Укажите имя');
    }
    if (mb_strlen($text) < 5) {
        $fail('Напишите пару слов об отзыве');
    }
    if ($rating < 1 || $rating > 5) {
        $fail('Поставьте оценку');
    }
    if (!isset($_POST['consent']) || $_POST['consent'] !== '1') {
        $fail('Нужно согласие на публикацию');
    }
    $saved = array();
    if (!empty($_FILES['photos']['name']) && is_array($_FILES['photos']['name'])) {
        $n = min(5, count($_FILES['photos']['name']));
        for ($i = 0; $i < $n; $i++) {
            $f = array();
            foreach (array('name', 'type', 'tmp_name', 'error', 'size') as $k) {
                $f[$k] = isset($_FILES['photos'][$k][$i]) ? $_FILES['photos'][$k][$i] : '';
            }
            $r = petgo_save_image($f, 1600, 80, 'petgo-reviews');
            if (is_array($r)) {
                $saved[] = basename($r['file']);
            } elseif ($r !== 'none') {
                foreach ($saved as $sf) {
                    @unlink(petgo_rv_dir()['path'] . '/' . $sf);
                }
                $fail('Не удалось загрузить фото. Попробуйте другое');
            }
        }
    }
    global $wpdb;
    $ok = $wpdb->insert(petgo_tb('reviews'), array(
        'order_id' => $oid, 'nanny_id' => (int) petgo_o($oid, 'nanny'), 'name' => $name, 'rating' => $rating, 'body' => $text,
        'photos' => $saved ? wp_json_encode($saved) : '', 'status' => get_option('petgo_rv_auto') ? 'ok' : 'pending', 'ts' => time(),
    ), array('%d', '%d', '%s', '%d', '%s', '%s', '%s', '%d'));
    if (!$ok) {
        $fail('Отзыв уже получен');
    }
    petgo_audit('review_new', 'order=' . $oid . ' rating=' . $rating, 0);
    petgo_bump();
    /* 36.0: хороший отзыв — в утреннюю сводку; плохой (3 и ниже) — сразу, чтобы успеть связаться с клиентом */
    if ((int) $rating <= 3) {
        petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), 'Плохой отзыв: ' . $rating . ' из 5', $name, petgo_page_url(array('pg' => 'reviews')));
    }
    wp_send_json(array('ok' => true));
}

/* ---------- модерация ---------- */
add_action('admin_post_petgo_rv_set', 'petgo_h_rv_set');
function petgo_h_rv_set() {
    petgo_guard('office');
    $id = (int) petgo_p('id');
    $st = (string) petgo_p('st') === 'ok' ? 'ok' : 'hidden';
    global $wpdb;
    $wpdb->update(petgo_tb('reviews'), array('status' => $st), array('id' => $id), array('%s'), array('%d'));
    petgo_flash(petgo_t('ok_saved'));
    petgo_back('reviews', array('st' => isset($_POST['back']) ? sanitize_key(wp_unslash($_POST['back'])) : 'pending'));
}

add_action('admin_post_petgo_rv_del', 'petgo_h_rv_del');
function petgo_h_rv_del() {
    petgo_guard('admin');
    global $wpdb;
    $id = (int) petgo_p('id');
    $r  = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . petgo_tb('reviews') . ' WHERE id=%d', $id));
    if ($r) {
        $d = petgo_rv_dir();
        $a = ($r->photos !== null && $r->photos !== '') ? json_decode($r->photos, true) : array();
        foreach ((array) $a as $f) {
            if (is_string($f) && preg_match('/^[A-Za-z0-9]{6,40}\.(webp|jpg)$/', $f)) {
                @unlink($d['path'] . '/' . $f);
            }
        }
        $wpdb->delete(petgo_tb('reviews'), array('id' => $id), array('%d'));
    }
    petgo_flash(petgo_t('ok_saved'));
    petgo_back('reviews', array('st' => 'all'));
}

add_action('admin_post_petgo_rv_opt', 'petgo_h_rv_opt');
function petgo_h_rv_opt() {
    petgo_guard('admin');
    update_option('petgo_rv_auto', petgo_p('auto') ? 1 : 0, false);
    petgo_flash(petgo_t('ok_saved'));
    petgo_back(petgo_ref_pg('reviews'));
}

function petgo_rv_stars($n) {
    $o = '<span class="pgc-rvs" aria-label="' . (int) $n . '/5">';
    for ($i = 1; $i <= 5; $i++) {
        $o .= '<svg viewBox="0 0 24 24" width="16" height="16" fill="' . ($i <= $n ? '#f5a623' : 'none') . '" stroke="' . ($i <= $n ? '#f5a623' : 'currentColor') . '" stroke-width="2" stroke-linejoin="round"><path d="M12 3.2l2.6 5.4 5.9.8-4.3 4.1 1 5.9L12 16.6 6.8 19.4l1-5.9L3.5 9.4l5.9-.8z"/></svg>';
    }
    return $o . '</span>';
}

/* ---------- страница «Отзывы» в кабинете ---------- */
function petgo_admin_reviews() {
    global $wpdb;
    $f  = isset($_GET['st']) ? sanitize_key(wp_unslash($_GET['st'])) : 'pending';
    $f  = in_array($f, array('pending', 'ok', 'hidden', 'all'), true) ? $f : 'pending';
    $T  = petgo_tb('reviews');
    $c  = array();
    foreach ((array) $wpdb->get_results("SELECT status, COUNT(*) n FROM $T GROUP BY status") as $x) {
        $c[$x->status] = (int) $x->n;
    }
    $all = array_sum($c);
    $lab = array('pending' => 'На проверке', 'ok' => 'Опубликованы', 'hidden' => 'Скрыты', 'all' => 'Все');
    $cnt = array('pending' => isset($c['pending']) ? $c['pending'] : 0, 'ok' => isset($c['ok']) ? $c['ok'] : 0, 'hidden' => isset($c['hidden']) ? $c['hidden'] : 0, 'all' => $all);
    echo '<div class="pgc-chips">';
    foreach ($lab as $k => $t) {
        echo '<a class="pgc-chip' . ($f === $k ? ' on' : '') . '" href="' . esc_url(petgo_page_url(array('pg' => 'reviews', 'st' => $k))) . '">' . esc_html($t) . '<i>' . (int) $cnt[$k] . '</i></a>';
    }
    echo '</div>';
    $rows = $wpdb->get_results($f === 'all' ? "SELECT * FROM $T ORDER BY id DESC LIMIT 200" : $wpdb->prepare("SELECT * FROM $T WHERE status=%s ORDER BY id DESC LIMIT 200", $f));
    if (!$rows) {
        echo petgo_empty('reviews', 'Отзывов пока нет. Ссылку на отзыв можно отправить из окна завершённого заказа.');
        return;
    }
    foreach ($rows as $r) {
        $u  = $r->nanny_id ? get_userdata((int) $r->nanny_id) : false;
        $h  = '<div class="pgc-card pgc-rv" data-k="v' . (int) $r->id . '"><div class="pgc-rqh">' . petgo_av('', $r->name, 'md') . '<div class="pgc-sm"><b>' . esc_html($r->name) . '</b>' . petgo_rv_stars((int) $r->rating) . '</div><span class="pgc-badge ' . ($r->status === 'ok' ? 'ok' : ($r->status === 'pending' ? 'done' : 'cancelled')) . '">' . esc_html($r->status === 'ok' ? 'опубликован' : ($r->status === 'pending' ? 'на проверке' : 'скрыт')) . '</span></div>';
        $h .= '<p class="pgc-rvt">' . esc_html((string) $r->body) . '</p>';
        $h .= '<div class="mu pgc-rvm">' . esc_html(petgo_fmt((int) $r->ts) . ' · заказ №' . (int) $r->order_id . ($u ? ' · ' . $u->display_name : '')) . '</div>';
        $ph = petgo_rv_photos($r);
        if ($ph) {
            $h .= '<div class="pgc-gal">';
            foreach ($ph as $u2) {
                $h .= '<a href="' . esc_url($u2) . '"><img src="' . esc_url($u2) . '" alt="" loading="lazy"></a>';
            }
            $h .= '</div>';
        }
        $h .= '<div class="pgc-btns">';
        if ($r->status !== 'ok') {
            $h .= petgo_form_open('rv_set') . '<input type="hidden" name="id" value="' . (int) $r->id . '"><input type="hidden" name="st" value="ok"><input type="hidden" name="back" value="' . esc_attr($f) . '"><button type="submit" class="pgc-btn">' . esc_html('Опубликовать') . '</button></form>';
        }
        if ($r->status !== 'hidden') {
            $h .= petgo_form_open('rv_set') . '<input type="hidden" name="id" value="' . (int) $r->id . '"><input type="hidden" name="st" value="hidden"><input type="hidden" name="back" value="' . esc_attr($f) . '"><button type="submit" class="pgc-btn sec">' . esc_html('Скрыть') . '</button></form>';
        }
        if (petgo_can('admin')) {
            $h .= petgo_form_open('rv_del') . '<input type="hidden" name="id" value="' . (int) $r->id . '"><button type="submit" class="pgc-btn red" onclick="return confirm(\'Удалить отзыв и его фото навсегда?\')">' . esc_html('Удалить') . '</button></form>';
        }
        echo $h . '</div></div>';
    }
}

/* ---------- публичный блок [petgo_reviews] ---------- */
function petgo_rv_ago($ts) {
    $d = max(0, (int) floor((time() - $ts) / DAY_IN_SECONDS));
    $pl = function ($n, $a) {
        $m = $n % 100;
        $k = $n % 10;
        return ($m > 10 && $m < 20) ? $a[2] : ($k === 1 ? $a[0] : (($k >= 2 && $k <= 4) ? $a[1] : $a[2]));
    };
    if ($d < 1) {
        return 'сегодня';
    }
    if ($d < 7) {
        return $d . ' ' . $pl($d, array('день', 'дня', 'дней')) . ' назад';
    }
    if ($d < 30) {
        $w = (int) floor($d / 7);
        return $w . ' ' . $pl($w, array('неделю', 'недели', 'недель')) . ' назад';
    }
    if ($d < 365) {
        $mo = (int) floor($d / 30);
        return $mo . ' ' . $pl($mo, array('месяц', 'месяца', 'месяцев')) . ' назад';
    }
    return wp_date('d.m.Y', $ts, petgo_tz());
}

add_shortcode('petgo_reviews', 'petgo_reviews_shortcode');
function petgo_reviews_shortcode() {
    static $done = false;
    if ($done) {
        return '';
    }
    global $wpdb;
    $rows = $wpdb->get_results('SELECT * FROM ' . petgo_tb('reviews') . " WHERE status='ok' ORDER BY id DESC LIMIT 100");
    if (!$rows) {
        return '';
    }
    $done = true;
    $data = array();
    foreach ($rows as $r) {
        $data[] = array('n' => (string) $r->name, 'r' => (int) $r->rating, 'd' => petgo_rv_ago((int) $r->ts), 't' => (string) $r->body, 'p' => petgo_rv_photos($r));
    }
    $f   = __DIR__ . '/../assets/reviews.html';
    $tpl = is_readable($f) ? (string) file_get_contents($f) : '';
    return str_replace('__PGV_DATA__', wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE), $tpl);
}
