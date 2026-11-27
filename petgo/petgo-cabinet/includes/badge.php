<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   40.2 БЕЙДЖ СОТРУДНИКА (удостоверение PetGo)
   - У каждого сотрудника в телефоне: меню профиля → «Мой бейдж». Фото, имя, должность, ID сотрудника и QR-код.
   - QR-код открывает страницу проверки: клиент видит, что это действующий сотрудник PetGo (фото, имя, должность).
     Уволили или выключили сотрудника — страница сразу пишет «Не работает в PetGo».
   - Печать: кнопка «Распечатать» у сотрудника (свой бейдж) и у владельца/администратора в «Сотрудниках»
     (один бейдж или все сразу). Лист A4, бейджи в настоящем размере 54 × 86 мм (как банковская карта),
     с линиями для вырезания. В окне печати можно сохранить PDF.
   - ID сотрудника: по умолчанию PGS0007 (по номеру в системе); владелец или администратор меняет его
     в карточке сотрудника, владелец свой — в профиле. Фото — то же, что в профиле (загружается в хорошем качестве).
   ============================================================ */

function petgo_staff_no($uid) {
    $n = trim((string) get_user_meta((int) $uid, 'petgo_staff_no', true));
    return $n !== '' ? $n : sprintf('PGS%04d', (int) $uid);
}

function petgo_staff_no_save($uid, $v) {
    $v = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) $v));
    $v = substr($v, 0, 20);
    if ($v === '' || $v === sprintf('PGS%04d', (int) $uid)) {
        delete_user_meta((int) $uid, 'petgo_staff_no');
    } else {
        update_user_meta((int) $uid, 'petgo_staff_no', $v);
    }
}

/* токен для QR: нельзя подобрать чужой */
function petgo_badge_token($uid) {
    return (int) $uid . '-' . substr(hash_hmac('sha256', 'pgbadge|' . (int) $uid, wp_salt('nonce')), 0, 12);
}
function petgo_badge_uid_from($t) {
    if (!preg_match('/^(\d+)-([a-f0-9]{12})$/', (string) $t, $m)) {
        return 0;
    }
    return hash_equals(petgo_badge_token((int) $m[1]), $m[0]) ? (int) $m[1] : 0;
}
function petgo_badge_verify_url($uid) {
    return add_query_arg('petgo_id', petgo_badge_token($uid), home_url('/'));
}
function petgo_badge_print_url($uids) {
    return add_query_arg(array('action' => 'petgo_badge_print', 'u' => is_array($uids) ? implode(',', array_map('intval', $uids)) : (string) $uids), admin_url('admin-post.php'));
}

function petgo_badge_phone() {
    if (function_exists('petgo_site_phone_fmt')) {
        $p = petgo_site_phone_fmt();
        if ($p !== '') {
            return $p;
        }
    }
    return '+7 747 817 57 12';
}

/* логотип PetGo: на зелёной полосе — белый */
function petgo_badge_logo() {
    return '<svg viewBox="0 0 254 72" aria-label="PetGo"><circle cx="32" cy="36" r="32" fill="#fff"/><g transform="translate(0 4)" fill="#024C46"><path d="M32 36c-8 0-14 6-14 11 0 5 4 7 8 6 3-1 4-2 6-2s3 1 6 2c4 1 8-1 8-6 0-5-6-11-14-11z"/><ellipse cx="19" cy="30" rx="4.5" ry="6" transform="rotate(-20 19 30)"/><ellipse cx="27" cy="22" rx="4.5" ry="6.5" transform="rotate(-6 27 22)"/><ellipse cx="37" cy="22" rx="4.5" ry="6.5" transform="rotate(6 37 22)"/><ellipse cx="45" cy="30" rx="4.5" ry="6" transform="rotate(20 45 30)"/></g><g transform="translate(76 4) scale(.62)" fill="none" stroke="#fff" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 30V92"/><circle cx="34" cy="50" r="20"/><path d="M72 50H112A20 20 0 1 0 106.14 64.14"/><path d="M142 12V54Q142 70 158 70"/><path d="M130 32H156"/><circle cx="196" cy="50" r="20"/><path d="M216 30V80Q216 96 196 96Q182 96 176 88"/><circle cx="254" cy="50" r="20"/></g></svg>';
}

/* узор с лапками (как фон приложения клиента) */
function petgo_paw_pattern($rgba) {
    $p = "%3Cpath d='M50 50c10 0 17 9 22 17 4 6 3 14-4 16-6 2-11-1-18-1s-12 3-18 1c-7-2-8-10-4-16 5-8 12-17 22-17z'/%3E%3Cellipse cx='24' cy='44' rx='9' ry='11' transform='rotate(-18 24 44)'/%3E%3Cellipse cx='40' cy='27' rx='9.5' ry='12'/%3E%3Cellipse cx='60' cy='27' rx='9.5' ry='12'/%3E%3Cellipse cx='76' cy='44' rx='9' ry='11' transform='rotate(18 76 44)'/%3E";
    return "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='220' viewBox='0 0 220 220'%3E%3Cg fill='" . $rgba . "'%3E%3Cg transform='translate(20 24) rotate(-22 15 15) scale(.3)'%3E" . $p . "%3C/g%3E%3Cg transform='translate(140 96) rotate(18 13 13) scale(.26)'%3E" . $p . "%3C/g%3E%3Cg transform='translate(54 156) rotate(-8 11 11) scale(.22)'%3E" . $p . "%3C/g%3E%3C/g%3E%3C/svg%3E\")";
}

/* стили бейджа: размеры в долях ширины карточки (cqw) — одинаково на экране и в печати (54 мм) */
function petgo_badge_css() {
    return '.pgb{--bw:min(330px,calc(100vw - 64px));container-type:inline-size;position:relative;width:var(--bw);aspect-ratio:54/85.6;margin:0 auto;border-radius:6.4cqw;overflow:hidden;background:' . petgo_paw_pattern('rgba(2,76,70,.05)') . ' 0 0/60cqw 60cqw repeat,#FAFCFA;color:#0F2A26;font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;box-shadow:0 1px 2px rgba(15,42,38,.06),0 18px 50px rgba(15,42,38,.16);-webkit-print-color-adjust:exact;print-color-adjust:exact;text-align:center}'
        . '.pgb *{box-sizing:border-box}'
        . '.pgb-top{position:absolute;left:0;right:0;top:0;height:44cqw;background:' . petgo_paw_pattern('rgba(255,255,255,.08)') . ' 0 0/60cqw 60cqw repeat,linear-gradient(160deg,#0B6B5C,#024C46 70%)}'
        . '.pgb-logo{position:absolute;left:7cqw;top:7cqw;width:33cqw;height:auto}'
        . '.pgb-kind{position:absolute;right:7cqw;top:8.4cqw;font-size:3.3cqw;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.82)}'
        . '.pgb-ph{position:absolute;left:25cqw;top:20cqw;width:50cqw;height:60cqw;border-radius:7cqw;border:1.5cqw solid #fff;overflow:hidden;background:#E1F2E9;box-shadow:0 2cqw 6cqw rgba(2,76,70,.25);display:grid;place-items:center}'
        . '.pgb-ph img{width:100%;height:100%;object-fit:cover;display:block}'
        . '.pgb-ph b{font-size:20cqw;font-weight:700;color:#024C46}'
        . '.pgb-main{position:absolute;left:6cqw;right:6cqw;top:84cqw;display:flex;flex-direction:column;align-items:center;gap:2.2cqw}'
        . '.pgb-name{font-size:7.4cqw;line-height:1.12;font-weight:800;letter-spacing:-.02em;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}'
        . '.pgb-role{display:inline-block;padding:1.1cqw 3.4cqw;border-radius:99cqw;background:#E1F2E9;color:#024C46;font-size:4cqw;font-weight:700}'
        . '.pgb-id{display:flex;align-items:baseline;justify-content:center;gap:2cqw;margin-top:.6cqw}'
        . '.pgb-id small{font-size:3.2cqw;font-weight:700;letter-spacing:.12em;color:#6B7A77}'
        . '.pgb-id b{font:800 6.4cqw/1 ui-monospace,"SF Mono",SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.06em;color:#024C46}'
        . '.pgb-bot{position:absolute;left:0;right:0;bottom:0;height:34cqw;display:flex;align-items:center;gap:4cqw;padding:0 6cqw 2cqw;background:#fff;border-top:.4cqw solid rgba(15,42,38,.08);text-align:left}'
        . '.pgb-qr{flex:none;width:24cqw;height:24cqw;padding:1cqw;border-radius:2cqw;background:#fff;box-shadow:0 0 0 .4cqw rgba(15,42,38,.1)}'
        . '.pgb-qr svg{display:block;width:100%;height:100%}'
        . '.pgb-bt{display:flex;flex-direction:column;gap:.8cqw;min-width:0}'
        . '.pgb-bt b{font-size:3.7cqw;line-height:1.2;font-weight:700}'
        . '.pgb-bt span{font-size:3.1cqw;line-height:1.3;color:#6B7A77}'
        . '.pgb-bt em{font-style:normal;font-size:3.1cqw;font-weight:600;color:#024C46}'
        . '.pgb-line{position:absolute;left:0;right:0;bottom:0;height:2cqw;background:#1E9A5F}'
        . '.pgb.off .pgb-top{filter:grayscale(1)}';
}

/* фото для открытой страницы проверки: снимки сотрудников закрыты от посторонних, поэтому фото
   встраивается прямо в страницу (только по настоящему QR-коду) */
function petgo_badge_photo_inline($uid) {
    $a = get_user_meta((int) $uid, 'petgo_avatar', true);
    $f = is_array($a) && !empty($a['file']) ? (string) $a['file'] : '';
    $ud = wp_upload_dir();
    if ($f === '' || strpos(wp_normalize_path($f), wp_normalize_path($ud['basedir'] . '/petgo')) !== 0 || !is_readable($f) || filesize($f) > 1572864) {
        return '';
    }
    $ext  = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    $mime = $ext === 'webp' ? 'image/webp' : ($ext === 'png' ? 'image/png' : 'image/jpeg');
    return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($f));
}

/* карточка бейджа */
function petgo_badge_card($u, $qr = true, $inline = false) {
    $u    = is_object($u) ? $u : get_userdata((int) $u);
    if (!$u) {
        return '';
    }
    $role = petgo_role_of($u);
    $av   = $inline ? petgo_badge_photo_inline($u->ID) : petgo_user_avatar_url($u->ID);
    $off  = (bool) get_user_meta($u->ID, 'petgo_disabled', true);
    $ini  = mb_strtoupper(mb_substr(trim($u->display_name), 0, 1));
    $h  = '<div class="pgb' . ($off ? ' off' : '') . '"><div class="pgb-top"><span class="pgb-logo">' . petgo_badge_logo() . '</span><span class="pgb-kind">' . esc_html('Сотрудник') . '</span></div>';
    $h .= '<div class="pgb-ph">' . ($av ? '<img src="' . ($inline ? esc_attr($av) : esc_url($av)) . '" alt="">' : '<b>' . esc_html($ini) . '</b>') . '</div>';
    $h .= '<div class="pgb-main"><div class="pgb-name">' . esc_html($u->display_name) . '</div><span class="pgb-role">' . esc_html(petgo_t('role_' . $role)) . '</span><div class="pgb-id"><small>ID</small><b>' . esc_html(petgo_staff_no($u->ID)) . '</b></div></div>';
    $h .= '<div class="pgb-bot">' . ($qr ? '<span class="pgb-qr" data-qr="' . esc_attr(petgo_badge_verify_url($u->ID)) . '"></span>' : '') . '<span class="pgb-bt"><b>Проверить сотрудника</b><span>Наведите камеру на QR-код</span><em>petgo.kz · ' . esc_html(petgo_badge_phone()) . '</em></span></div><i class="pgb-line"></i></div>';
    return $h;
}

/* QR рисует скрипт (qrcode-generator, MIT) — один для экрана, печати и проверки */
function petgo_badge_qr_js() {
    return '<script src="' . esc_url(petgo_asset_url('vendor/qrcode.min.js')) . '"></script><script>(function(){function go(){if(!window.qrcode)return setTimeout(go,60);document.querySelectorAll("[data-qr]").forEach(function(e){if(e._q)return;e._q=1;var q=qrcode(0,"M");q.addData(e.getAttribute("data-qr"));q.make();e.innerHTML=q.createSvgTag({cellSize:4,margin:0,scalable:true})});window.pgbReady=1;document.dispatchEvent(new Event("pgb-ready"))}go()})();</script>';
}

/* ---------- приложение: «Мой бейдж» (и бейдж сотрудника для владельца/администратора) ---------- */
add_filter('petgo_app_title', function ($t, $pg) {
    return $pg === 'badge' ? 'Бейдж' : $t;
}, 10, 2);
add_filter('petgo_app_page', function ($done, $pg) {
    if ($done || $pg !== 'badge') {
        return $done;
    }
    $me  = get_current_user_id();
    $uid = isset($_GET['uid']) ? (int) $_GET['uid'] : $me;
    if ($uid !== $me && !petgo_can('admin')) {
        $uid = $me;
    }
    $u = get_userdata($uid);
    if (!$u || petgo_role_of($u) === '') {
        echo '<p class="mu">' . esc_html('Нет доступа') . '</p>';
        return true;
    }
    echo '<style>' . petgo_badge_css() . '.pgbw{display:flex;flex-direction:column;align-items:center;gap:16px;padding:6px 0 10px}.pgbw p{margin:0;max-width:330px;text-align:center;color:var(--mu);font-size:14.5px;line-height:1.45}.pgbw .pgc-btns{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;width:min(330px,100%)}.pgbw .pgc-btns .pgc-btn{flex:1 1 0;justify-content:center;white-space:nowrap}.pgbw>.pgb{cursor:zoom-in}'
        . '.pgb-fs{position:fixed;inset:0;z-index:3000;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:calc(env(safe-area-inset-top,0px) + 16px) 16px calc(env(safe-area-inset-bottom,0px) + 16px);background:' . petgo_paw_pattern('rgba(2,76,70,.05)') . ' 0 0/220px 220px repeat,#F4F6F3;opacity:0;transition:opacity .22s ease;cursor:zoom-out}.pgb-fs.on{opacity:1}'
        . '.pgb-fs .pgb{--bw:min(calc(100vw - 32px),calc((100vh - 90px) * .6308),calc((100dvh - 90px) * .6308));transform:scale(.96);transition:transform .32s cubic-bezier(.32,.72,0,1)}.pgb-fs.on .pgb{transform:none}.pgb-fs p{margin:0;font:500 13px/1.3 -apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI",Roboto,Arial,sans-serif;color:#6B7A77}'
        . '@media(prefers-reduced-motion:reduce){.pgb-fs,.pgb-fs .pgb{transition:none}}</style>';
    echo '<div class="pgbw">' . petgo_badge_card($u) . '<p>' . esc_html($uid === $me ? 'Покажите бейдж клиенту при встрече: по QR-коду он проверит, что вы работаете в PetGo.' : 'Бейдж сотрудника. По QR-коду клиент проверяет, что сотрудник работает в PetGo.') . '</p>';
    echo '<div class="pgc-btns"><button type="button" class="pgc-btn pgb-show">' . petgo_icon('idcard') . '<span>Показать клиенту</span></button><a class="pgc-btn sec" href="' . esc_url(petgo_badge_print_url($uid)) . '" target="_blank" rel="noopener">' . petgo_icon('download') . '<span>Распечатать / PDF</span></a></div></div>';
    echo petgo_badge_qr_js();
    /* «Показать клиенту»: бейдж на весь экран, нажатие в любом месте — закрыть */
    echo '<script>(function(){if(window.__pgbFs)return;window.__pgbFs=1;document.addEventListener("click",function(e){var b=e.target.closest(".pgb-show,.pgbw>.pgb");if(b){var c=document.querySelector(".pgbw>.pgb");if(!c)return;var o=document.createElement("div");o.className="pgb-fs";o.setAttribute("role","dialog");o.setAttribute("aria-label","Бейдж");o.appendChild(c.cloneNode(true));var h=document.createElement("p");h.textContent="Нажмите, чтобы закрыть";o.appendChild(h);document.body.appendChild(o);document.documentElement.classList.add("pgc-lock");requestAnimationFrame(function(){o.classList.add("on")});return}var f=e.target.closest(".pgb-fs");if(f){f.classList.remove("on");document.documentElement.classList.remove("pgc-lock");setTimeout(function(){f.remove()},220)}});document.addEventListener("keydown",function(e){var f=document.querySelector(".pgb-fs");if(f&&e.key==="Escape")f.click()})})();</script>';
    return true;
}, 10, 2);

/* ---------- печать: лист A4, бейджи 54 × 86 мм с линиями для вырезания ---------- */
add_action('admin_post_petgo_badge_print', 'petgo_badge_print');
add_action('admin_post_nopriv_petgo_badge_print', function () {
    /* не вошли — на вход в приложение сотрудников */
    wp_safe_redirect(petgo_page_url());
    exit;
});
function petgo_badge_print() {
    if (!is_user_logged_in() || petgo_role_of() === '') {
        auth_redirect();
    }
    $me  = get_current_user_id();
    $arg = isset($_GET['u']) ? (string) wp_unslash($_GET['u']) : (string) $me;
    if ($arg === 'all') {
        /* все работающие сотрудники; владелец, который печатает, — первым */
        $ids = petgo_is_owner() ? array($me) : array();
        foreach (petgo_staff_users() as $u) {
            if (!get_user_meta($u->ID, 'petgo_disabled', true)) {
                $ids[] = (int) $u->ID;
            }
        }
    } else {
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $arg)))));
    }
    if (!petgo_can('admin')) {
        $ids = array($me);
    }
    $ids = array_slice($ids, 0, 60);
    $cards = '';
    foreach ($ids as $id) {
        $u = get_userdata($id);
        if ($u && petgo_role_of($u) !== '') {
            $cards .= '<div class="cut">' . petgo_badge_card($u) . '</div>';
        }
    }
    petgo_audit('badge_print', implode(',', $ids));
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Бейджи PetGo</title><style>' . petgo_badge_css()
        . '@page{size:A4;margin:10mm}html,body{margin:0;background:#F4F6F3;font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI",Roboto,Arial,sans-serif;color:#0F2A26}'
        . '.bar{position:sticky;top:0;z-index:2;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:10px;padding:14px 16px;background:rgba(244,246,243,.95);border-bottom:.5px solid rgba(15,42,38,.1)}'
        . '.bar b{flex-basis:100%;text-align:center;font-size:15px}.bar span{flex-basis:100%;text-align:center;font-size:13px;color:#6B7A77}'
        . '.bar button{min-height:44px;padding:0 20px;border:0;border-radius:14px;background:#024C46;color:#fff;font:600 16px/1 inherit;cursor:pointer}.bar button.sec{background:#E1F2E9;color:#024C46}'
        . '.sheet{display:flex;flex-wrap:wrap;justify-content:center;gap:24px;padding:24px 16px}'
        . '.cut{padding:0}'
        . '@media print{html,body{background:#fff}.bar{display:none}.sheet{display:grid;grid-template-columns:repeat(3,54mm);gap:6mm;justify-content:center;padding:0}.pgb{--bw:54mm;box-shadow:none;border-radius:3.4mm;break-inside:avoid}.cut{outline:.2mm dashed #B8C4C0;outline-offset:1.5mm;break-inside:avoid}}'
        . '</style></head><body><div class="bar"><b>Бейджи PetGo · ' . count($ids) . '</b><span>Размер 54 × 86 мм, как банковская карта. В окне печати можно выбрать «Сохранить как PDF».</span><button type="button" onclick="window.print()">Распечатать</button><button type="button" class="sec" onclick="history.length>1?history.back():window.close()">Закрыть</button></div>'
        . '<div class="sheet">' . $cards . '</div>' . petgo_badge_qr_js()
        . '<script>(function(){function run(){var im=[].slice.call(document.images),n=im.filter(function(i){return!i.complete}).length;function p(){if(!window._pp){window._pp=1;setTimeout(function(){window.print()},350)}}if(!n)p();else im.forEach(function(i){if(!i.complete)i.addEventListener("load",function(){if(--n<=0)p()}),i.addEventListener("error",function(){if(--n<=0)p()})})}if(window.pgbReady)run();else document.addEventListener("pgb-ready",run)})()</script></body></html>';
    exit;
}

/* ---------- проверка по QR: открыто для всех (клиент наводит камеру на бейдж) ---------- */
add_action('parse_request', function () {
    if (!isset($_GET['petgo_id'])) {
        return;
    }
    $uid = petgo_badge_uid_from(sanitize_text_field(wp_unslash($_GET['petgo_id'])));
    $u   = $uid ? get_userdata($uid) : null;
    $ok  = $u && petgo_role_of($u) !== '' && !get_user_meta($uid, 'petgo_disabled', true);
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><title>Проверка сотрудника PetGo</title><style>' . petgo_badge_css()
        . 'html,body{margin:0;min-height:100%;background:' . petgo_paw_pattern('rgba(2,76,70,.045)') . ' 0 0/220px 220px repeat,#F4F6F3;font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI",Roboto,Arial,sans-serif;color:#0F2A26}'
        . 'main{display:flex;flex-direction:column;align-items:center;gap:18px;padding:calc(env(safe-area-inset-top,0px) + 28px) 16px 40px}'
        . '.st{box-sizing:border-box;display:flex;align-items:center;gap:12px;width:min(360px,100%);padding:14px 16px;border-radius:18px;background:#fff;box-shadow:0 1px 2px rgba(15,42,38,.04),0 8px 24px rgba(15,42,38,.06)}'
        . '.st i{flex:none;width:40px;height:40px;border-radius:50%;display:grid;place-items:center;color:#fff;font-style:normal;font-size:22px;font-weight:700}.st.ok i{background:#1E9A5F}.st.no i{background:#C63A2E}'
        . '.st b{display:block;font-size:17px}.st span{font-size:14px;color:#6B7A77}'
        . 'p{margin:0;max-width:360px;text-align:center;font-size:14.5px;line-height:1.45;color:#6B7A77}a{color:#024C46;font-weight:600;white-space:nowrap}</style></head><body><main>';
    if ($ok) {
        echo '<div class="st ok"><i>✓</i><div><b>Действующий сотрудник PetGo</b><span>' . esc_html($u->display_name . ' · ' . petgo_t('role_' . petgo_role_of($u)) . ' · ID ' . petgo_staff_no($uid)) . '</span></div></div>' . petgo_badge_card($u, false, true);
    } else {
        echo '<div class="st no"><i>!</i><div><b>' . esc_html($u ? 'Сотрудник больше не работает в PetGo' : 'Сотрудник не найден') . '</b><span>Не передавайте ему питомца и ключи.</span></div></div>';
    }
    $tel = preg_replace('/\D/', '', petgo_badge_phone());
    echo '<p>Есть сомнения — позвоните в PetGo: <a href="tel:+' . esc_attr($tel) . '">' . esc_html(petgo_badge_phone()) . '</a></p></main></body></html>';
    exit;
}, 0);

/* ---------- меню профиля: «Мой бейдж» ---------- */
function petgo_badge_menu_item() {
    return '<a class="pgc-mi" href="' . esc_url(petgo_page_url(array('pg' => 'badge'))) . '">' . petgo_icon('idcard') . 'Мой бейдж</a>';
}
