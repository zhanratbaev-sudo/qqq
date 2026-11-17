<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   НЯНИ НА САЙТЕ
   Страница «Няни» показывает сотрудников с ролью «Няня» из приложения: фото, имя, рейтинг клиентов,
   сколько заказов выполнено и «О себе». Добавили няню в приложении — она сразу на сайте.
   Не показываются: отключённые и те, у кого в приложении снята галочка «Показывать на сайте».
   Телефон, почта и логин на сайт не попадают.
   ============================================================ */

function petgo_site_nannies() {
    static $out = null;
    if ($out !== null) {
        return $out;
    }
    $out = array();
    if (!function_exists('petgo_nannies')) {
        return $out;
    }
    $users = array();
    foreach ((array) petgo_nannies() as $u) {
        if (get_user_meta($u->ID, 'petgo_disabled', true) || get_user_meta($u->ID, 'petgo_site_hide', true)) {
            continue;
        }
        $users[(int) $u->ID] = $u;
    }
    if (!$users) {
        return $out;
    }
    /* выполненные заказы — одним запросом */
    global $wpdb;
    $done = array();
    $ids  = implode(',', array_map('intval', array_keys($users)));
    $rows = $wpdb->get_results("SELECT n.meta_value uid, COUNT(*) c FROM {$wpdb->postmeta} n JOIN {$wpdb->postmeta} s ON s.post_id=n.post_id AND s.meta_key='_status' AND s.meta_value='confirmed' JOIN {$wpdb->posts} p ON p.ID=n.post_id AND p.post_type='petgo_order' AND p.post_status='publish' WHERE n.meta_key='_nanny' AND n.meta_value IN ($ids) GROUP BY n.meta_value"); // phpcs:ignore
    foreach ((array) $rows as $r) {
        $done[(int) $r->uid] = (int) $r->c;
    }
    foreach ($users as $id => $u) {
        $rv    = function_exists('petgo_nanny_rating') ? petgo_nanny_rating($id) : array(0, 0);
        $out[] = array(
            'id'   => $id,
            'name' => trim((string) $u->display_name),
            'img'  => petgo_site_nanny_img($id),
            'bio'  => trim((string) get_user_meta($id, 'petgo_bio', true)),
            'rate' => (float) $rv[0],
            'rn'   => (int) $rv[1],
            'done' => isset($done[$id]) ? $done[$id] : 0,
            'new'  => strtotime((string) $u->user_registered) > time() - 30 * DAY_IN_SECONDS,
        );
    }
    /* сначала — с лучшим рейтингом и опытом */
    usort($out, function ($a, $b) {
        $x = $b['rate'] * min(10, $b['rn']) + $b['done'] / 5;
        $y = $a['rate'] * min(10, $a['rn']) + $a['done'] / 5;
        return $x === $y ? strcmp($a['name'], $b['name']) : ($x > $y ? 1 : -1);
    });
    return $out;
}

/* фото няни для сайта: отдельная открытая ссылка только на аватар няни (фото клиентов остаются закрытыми) */
function petgo_site_nanny_sig($uid, $file) {
    return substr(hash_hmac('sha256', 'nimg|' . (int) $uid . '|' . $file, wp_salt('auth')), 0, 16);
}

function petgo_site_nanny_img($uid) {
    $a = get_user_meta($uid, 'petgo_avatar', true);
    if (!is_array($a) || empty($a['file'])) {
        return '';
    }
    $f = basename((string) $a['file']);
    return petgo_rel_url(add_query_arg(array('action' => 'petgo_nimg', 'u' => (int) $uid, 's' => petgo_site_nanny_sig($uid, $f)), admin_url('admin-post.php')));
}

add_action('admin_post_petgo_nimg', 'petgo_site_nanny_img_serve');
add_action('admin_post_nopriv_petgo_nimg', 'petgo_site_nanny_img_serve');
function petgo_site_nanny_img_serve() {
    $uid = isset($_GET['u']) ? (int) $_GET['u'] : 0;
    $sig = isset($_GET['s']) ? (string) wp_unslash($_GET['s']) : '';
    $u   = $uid ? get_userdata($uid) : false;
    $a   = $u ? get_user_meta($uid, 'petgo_avatar', true) : false;
    /* только няня, которая показывается на сайте */
    if (!$u || !in_array('petgo_nanny', (array) $u->roles, true) || get_user_meta($uid, 'petgo_disabled', true) || get_user_meta($uid, 'petgo_site_hide', true) || !is_array($a) || empty($a['file'])) {
        status_header(404);
        exit;
    }
    $f = basename((string) $a['file']);
    if (!petgo_media_name_ok($f) || !hash_equals(petgo_site_nanny_sig($uid, $f), $sig)) {
        status_header(404);
        exit;
    }
    $root = realpath(petgo_media_dir());
    $real = $root ? realpath($root . '/' . $f) : false;
    if (!$real || strpos(wp_normalize_path($real), wp_normalize_path($root) . '/') !== 0 || !is_file($real)) {
        status_header(404);
        exit;
    }
    $ext  = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    $mime = array('webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png');
    while (ob_get_level()) {
        ob_end_clean();
    }
    header_remove('Expires');
    header_remove('Pragma');
    header('Content-Type: ' . $mime[$ext]);
    header('Content-Length: ' . filesize($real));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=86400');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    readfile($real);
    exit;
}

/* блок нянь на странице */
function petgo_site_b_nannies($list) {
    if (!$list) {
        return '';
    }
    $L = 'petgo_site_l';
    $h = '<section class="pg-sec pg-sec-tight"><div class="pg-wrap"><div class="pg-grid pg-nannies">';
    foreach ($list as $n) {
        $first = preg_split('/\s+/u', $n['name']);
        $first = $first ? $first[0] : $n['name'];
        $h .= '<article class="pg-nanny pg-rise">';
        $h .= '<div class="pg-nanny-top"><span class="pg-nanny-av">' . ($n['img'] !== '' ? '<img src="' . esc_url($n['img']) . '" alt="" loading="lazy" decoding="async">' : '<b>' . esc_html(mb_strtoupper(mb_substr($n['name'] !== '' ? $n['name'] : '?', 0, 1))) . '</b>') . '</span>';
        $h .= '<div class="pg-nanny-who"><h3 class="pg-h3">' . esc_html($n['name']) . '</h3><span class="pg-nanny-role">' . esc_html($L($n['new'] && !$n['done'] ? 'Новая няня' : 'Няня PetGo')) . '</span></div></div>';
        $h .= '<ul class="pg-nanny-stats">';
        if ($n['rn']) {
            $h .= '<li>' . petgo_site_icon('star') . '<b>' . esc_html(number_format($n['rate'], 1, ',', '')) . '</b> · ' . esc_html($n['rn'] . ' ' . petgo_site_n($n['rn'], array('отзыв', 'отзыва', 'отзывов'))) . '</li>';
        }
        if ($n['done']) {
            $h .= '<li>' . petgo_site_icon('check') . esc_html(petgo_site_lf('Выполнено заказов: %d', $n['done'])) . '</li>';
        }
        $h .= '</ul>';
        if ($n['bio'] !== '') {
            $h .= '<p class="pg-p">' . esc_html($L($n['bio'])) . '</p>';
        }
        $h .= '<button type="button" class="pg-b pg-btn pg-btn-second" data-sheet="request" data-note="' . esc_attr(petgo_site_lf('Хочу няню: %s', $first)) . '">' . esc_html($L('Выбрать эту няню')) . '</button>';
        $h .= '</article>';
    }
    return $h . '</div></div></section>';
}

/* ---------- в приложении: «О себе» и «Показывать на сайте» в карточке сотрудника ---------- */
function petgo_staff_site_fields($u) {
    if (!$u || petgo_role_of($u) !== 'nanny') {
        return '';
    }
    $bio = (string) get_user_meta($u->ID, 'petgo_bio', true);
    $h   = '<label class="pgc-f"><span>О себе — для сайта</span><textarea name="bio" rows="3" maxlength="400" placeholder="Например: люблю собак, 3 года опыта, есть свой кот">' . esc_textarea($bio) . '</textarea></label>';
    $h  .= '<label class="pgc-ck"><input type="checkbox" name="site_show" value="1"' . checked(!get_user_meta($u->ID, 'petgo_site_hide', true), true, false) . '> <span>Показывать на сайте (страница «Няни»)</span></label>';
    return $h;
}

function petgo_staff_site_save($uid) {
    /* поля есть в форме только у нянь: нет поля — ничего не меняем */
    if (!isset($_POST['bio'])) {
        return;
    }
    $u = get_userdata($uid);
    if (!$u || petgo_role_of($u) !== 'nanny') {
        return;
    }
    update_user_meta($uid, 'petgo_bio', mb_substr(sanitize_textarea_field(wp_unslash($_POST['bio'])), 0, 400));
    if (!empty($_POST['site_show'])) {
        delete_user_meta($uid, 'petgo_site_hide');
    } else {
        update_user_meta($uid, 'petgo_site_hide', 1);
    }
}

/* ---------- сброс кэша страниц: изменения в приложении сразу видны на сайте ---------- */
function petgo_site_flush_cache() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    do_action('litespeed_purge_all');
    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
    }
    if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
    }
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }
    if (function_exists('sg_cachepress_purge_cache')) {
        sg_cachepress_purge_cache();
    }
    if (class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall')) {
        autoptimizeCache::clearall();
    }
    do_action('breeze_clear_all_cache');
    do_action('wpfc_clear_all_cache');
    if (has_action('cache_enabler_clear_complete_cache')) {
        do_action('cache_enabler_clear_complete_cache');
    }
}

/* сотрудники, настройки сайта, отзывы — после изменений сайт показывает свежие данные */
foreach (array('admin_post_petgo_staff_new', 'admin_post_petgo_staff_save', 'admin_post_petgo_staff_del', 'admin_post_petgo_site_save') as $petgo_hk) {
    add_action($petgo_hk, 'petgo_site_flush_cache', 1);
}
add_action('profile_update', function ($uid) {
    $u = get_userdata($uid);
    if ($u && in_array('petgo_nanny', (array) $u->roles, true)) {
        petgo_site_flush_cache();
    }
});
add_action('set_user_role', function ($uid, $role) {
    if ($role === 'petgo_nanny') {
        petgo_site_flush_cache();
    }
}, 10, 2);
