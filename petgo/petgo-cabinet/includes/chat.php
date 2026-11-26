<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   РОЛИ И ПРАВА
   владелец (manage_options) > админ > менеджер > няня
   ============================================================ */
function petgo_role_map() {
    return array('petgo_admin' => 'admin', 'petgo_manager' => 'manager', 'petgo_nanny' => 'nanny');
}

function petgo_role_of($u = null) {
    if (!$u) {
        $u = wp_get_current_user();
    }
    if (!$u || !$u->ID) {
        return '';
    }
    if (user_can($u, 'manage_options')) {
        return 'owner';
    }
    foreach (petgo_role_map() as $wr => $r) {
        if (in_array($wr, (array) $u->roles, true)) {
            return $r;
        }
    }
    return '';
}

function petgo_can($cap) {
    $m = array(
        'office' => array('owner', 'admin', 'manager'),
        'admin'  => array('owner', 'admin'),
        'owner'  => array('owner'),
        'nanny'  => array('nanny'),
        'worker' => array('owner', 'admin', 'manager', 'nanny'),
    );
    return isset($m[$cap]) && in_array(petgo_role_of(), $m[$cap], true);
}

function petgo_is_owner() {
    return petgo_role_of() === 'owner';
}

function petgo_is_admin() {
    return petgo_can('admin');
}

function petgo_is_nanny() {
    return petgo_role_of() === 'nanny';
}

function petgo_is_staffer() {
    $r = petgo_role_of();
    return $r !== '' && $r !== 'owner';
}

function petgo_staff_obj($uid) {
    $u = get_userdata((int) $uid);
    if ($u && !user_can($u, 'manage_options') && array_intersect(array_keys(petgo_role_map()), (array) $u->roles)) {
        return $u;
    }
    return false;
}

function petgo_staff_users() {
    static $c = null;
    if ($c === null) {
        $c = get_users(array('role__in' => array_keys(petgo_role_map()), 'orderby' => 'display_name'));
    }
    return $c;
}

/* кого может изменять/удалять текущий пользователь: владелец всех, админ только менеджеров и нянь */
function petgo_can_manage($u) {
    if (petgo_is_owner()) {
        return true;
    }
    return petgo_role_of() === 'admin' && in_array(petgo_role_of($u), array('manager', 'nanny'), true);
}

function petgo_role_select($cur) {
    $r = '<label class="pgc-f"><span>' . esc_html(petgo_t('role')) . '</span><select name="role">';
    $list = petgo_is_owner() ? array('admin', 'manager', 'nanny') : array('manager', 'nanny');
    foreach ($list as $k) {
        $r .= '<option value="' . $k . '"' . selected($cur, $k, false) . '>' . esc_html(petgo_t('role_' . $k)) . '</option>';
    }
    return $r . '</select></label>';
}

/* ============================================================
   ОБЪЯСНИТЕЛЬНАЯ: админ и менеджер при удалении/передаче обязаны объяснить, текст уходит владельцу в чат
   ============================================================ */
function petgo_need_why($only = null) {
    return in_array(petgo_role_of(), $only ? $only : array('admin', 'manager'), true);
}

function petgo_why($what, $pg = 'orders', $only = null) {
    if (!petgo_need_why($only)) {
        return;
    }
    $w = trim(sanitize_textarea_field((string) petgo_p('why')));
    if (mb_strlen($w) < 5) {
        petgo_fail('err_why', $pg);
    }
    $u = wp_get_current_user();
    petgo_sys_msg(petgo_role_of() === 'manager' ? 'sys_managers' : 'sys_admins', (int) $u->ID, 'why', $what . "\n\nОбъяснительная: " . $w, (int) $u->ID);
}

function petgo_why_modal() {
    $b = '<p>' . esc_html(petgo_t('why_hint')) . '</p><textarea id="pgc-why-t" rows="4" maxlength="1000"></textarea><p><button type="button" class="pgc-btn" id="pgc-why-go">' . esc_html(petgo_t('why_send')) . '</button></p>';
    echo petgo_modal('pgc-m-why', petgo_t('why_title'), $b);
}

/* ============================================================
   УСТАНОВКА: роли, таблицы, системные чаты (запускается один раз при смене версии)
   ============================================================ */
function petgo_install() {
    foreach (array('petgo_nanny' => 'Няня PetGo', 'petgo_manager' => 'Менеджер PetGo', 'petgo_admin' => 'Админ PetGo') as $k => $n) {
        if (!get_role($k)) {
            add_role($k, $n, array('read' => true));
        }
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $cc = $wpdb->get_charset_collate();
    $p  = $wpdb->prefix . 'petgo_';
    dbDelta("CREATE TABLE {$p}chats (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ckey varchar(40) NOT NULL,
  type varchar(8) NOT NULL DEFAULT 'group',
  title varchar(120) NOT NULL DEFAULT '',
  ph tinyint(1) NOT NULL DEFAULT 1,
  lk tinyint(1) NOT NULL DEFAULT 1,
  av varchar(255) NOT NULL DEFAULT '',
  created int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY ckey (ckey)
) $cc;");
    dbDelta("CREATE TABLE {$p}members (
  chat_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  last_read bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (chat_id,user_id),
  KEY user_id (user_id)
) $cc;");
    dbDelta("CREATE TABLE {$p}msgs (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  chat_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  subject_id bigint(20) unsigned NOT NULL DEFAULT 0,
  kind varchar(8) NOT NULL DEFAULT 'msg',
  body text NOT NULL,
  photo varchar(255) NOT NULL DEFAULT '',
  ts int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY chat_id (chat_id,id)
) $cc;");
    dbDelta("CREATE TABLE {$p}reqs (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(80) NOT NULL,
  phone varchar(32) NOT NULL,
  contact varchar(8) NOT NULL DEFAULT 'write',
  species varchar(8) NOT NULL DEFAULT 'dog',
  sp_other varchar(60) NOT NULL DEFAULT '',
  weight varchar(20) NOT NULL DEFAULT '',
  breed varchar(80) NOT NULL DEFAULT '',
  note text NOT NULL,
  extra text,
  pet_id bigint(20) unsigned NOT NULL DEFAULT 0,
  pf longtext,
  status varchar(8) NOT NULL DEFAULT 'new',
  ts int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY status (status,id),
  KEY phone (phone)
) $cc;");
    foreach (array('sys_nannies' => 'Няни', 'sys_managers' => 'Менеджеры', 'sys_admins' => 'Админ') as $k => $t) {
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$p}chats (ckey,type,title,created) VALUES (%s,'sys',%s,%d)", $k, $t, time()));
    }
    update_option('petgo_ver', PETGO_VER);
}

/* ============================================================
   ЧАТЫ: хелперы
   ============================================================ */
function petgo_chat_id($ckey) {
    static $c = array();
    if (!isset($c[$ckey])) {
        global $wpdb;
        $c[$ckey] = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . petgo_tb('chats') . ' WHERE ckey=%s', $ckey));
    }
    return $c[$ckey];
}

/* системное сообщение: автор 0 = система; subject = кому из сотрудников оно относится (видимость) */
function petgo_sys_msg($ckey, $author, $kind, $body, $subject = 0) {
    global $wpdb;
    $cid = petgo_chat_id($ckey);
    if (!$cid) {
        return;
    }
    $wpdb->insert(petgo_tb('msgs'), array('chat_id' => $cid, 'user_id' => (int) $author, 'subject_id' => (int) $subject, 'kind' => $kind, 'body' => $body, 'photo' => '', 'ts' => time()), array('%d', '%d', '%d', '%s', '%s', '%s', '%d'));
}

function petgo_sys_owner_role() {
    return array('sys_nannies' => 'nanny', 'sys_managers' => 'manager', 'sys_admins' => 'admin');
}

/* кто какие системные чаты видит */
function petgo_sys_visible($ckey, $role) {
    if ($role === 'owner') {
        return true;
    }
    if ($role === 'admin') {
        return true;
    }
    $m = petgo_sys_owner_role();
    return isset($m[$ckey]) && $m[$ckey] === $role && $role !== 'admin';
}

/* в «своём» системном чате сотрудник видит только сообщения о себе, от себя и общие */
function petgo_vis_sql($c, $uid, $role) {
    if ($c->type !== 'sys') {
        return '';
    }
    $m = petgo_sys_owner_role();
    if (isset($m[$c->ckey]) && $m[$c->ckey] === $role) {
        return ' AND (subject_id IN (0,' . (int) $uid . ') OR user_id=' . (int) $uid . ')';
    }
    return '';
}

function petgo_chat_row($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . petgo_tb('chats') . ' WHERE id=%d', (int) $id));
}

function petgo_chat_access($c, $uid, $role) {
    if (!$c) {
        return false;
    }
    if ($c->type === 'sys') {
        return petgo_sys_visible($c->ckey, $role);
    }
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare('SELECT 1 FROM ' . petgo_tb('members') . ' WHERE chat_id=%d AND user_id=%d', $c->id, $uid));
}

function petgo_chats_for($uid, $role) {
    global $wpdb;
    $c = petgo_tb('chats');
    $o = array();
    foreach ((array) $wpdb->get_results("SELECT * FROM $c WHERE type='sys' ORDER BY id") as $r) {
        if (petgo_sys_visible($r->ckey, $role)) {
            $o[] = $r;
        }
    }
    $q = $wpdb->get_results($wpdb->prepare('SELECT c.* FROM ' . $c . ' c JOIN ' . petgo_tb('members') . " m ON m.chat_id=c.id WHERE m.user_id=%d AND c.type<>'sys' ORDER BY c.id DESC LIMIT 60", $uid));
    return array_merge($o, (array) $q);
}

function petgo_unread_map($uid, $role) {
    global $wpdb;
    $ch = petgo_chats_for($uid, $role);
    if (!$ch) {
        return array();
    }
    $ids = array();
    $own = array();
    foreach ($ch as $c) {
        $ids[] = (int) $c->id;
        if (petgo_vis_sql($c, $uid, $role) !== '') {
            $own[] = (int) $c->id;
        }
    }
    $sql = 'SELECT m.chat_id, COUNT(*) n FROM ' . petgo_tb('msgs') . ' m LEFT JOIN ' . petgo_tb('members') . ' r ON r.chat_id=m.chat_id AND r.user_id=' . (int) $uid
        . ' WHERE m.chat_id IN (' . implode(',', $ids) . ') AND m.id > COALESCE(r.last_read,0) AND m.user_id<>' . (int) $uid;
    if ($own) {
        $sql .= ' AND (m.chat_id NOT IN (' . implode(',', $own) . ') OR m.subject_id IN (0,' . (int) $uid . '))';
    }
    $sql .= ' GROUP BY m.chat_id';
    $o = array();
    foreach ((array) $wpdb->get_results($sql) as $r) {
        $o[(int) $r->chat_id] = (int) $r->n;
    }
    return $o;
}

function petgo_unread_total() {
    static $n = null;
    if ($n === null) {
        $u = wp_get_current_user();
        $n = array_sum(petgo_unread_map((int) $u->ID, petgo_role_of()));
    }
    return $n;
}

function petgo_mark_read($cid, $uid, $mid) {
    global $wpdb;
    $wpdb->query($wpdb->prepare('INSERT INTO ' . petgo_tb('members') . ' (chat_id,user_id,last_read) VALUES (%d,%d,%d) ON DUPLICATE KEY UPDATE last_read=GREATEST(last_read,VALUES(last_read))', $cid, $uid, $mid));
}

function petgo_msg_html($t) {
    $parts = preg_split('~(https?://[^\s<>"]+|www\.[^\s<>"]+)~iu', (string) $t, -1, PREG_SPLIT_DELIM_CAPTURE);
    $o     = '';
    foreach ($parts as $i => $p) {
        if ($i % 2) {
            $u  = (stripos($p, 'www.') === 0 ? 'https://' : '') . $p;
            $o .= '<a href="' . esc_url($u, array('http', 'https')) . '" target="_blank" rel="noopener nofollow">' . esc_html($p) . '</a>';
        } else {
            $o .= nl2br(esc_html($p));
        }
    }
    return $o;
}

function petgo_has_link($t) {
    return (bool) preg_match('~(?:https?://|www\.|\b[a-z0-9-]+\.(?:com|kz|ru|me|org|net|io|ly|app|info|su|by|uz)\b)~iu', (string) $t);
}

function petgo_url_rm($url) {
    $ud = wp_upload_dir();
    if ($url && strpos($url, $ud['baseurl']) === 0) {
        petgo_rm($ud['basedir'] . substr($url, strlen($ud['baseurl'])));
    }
}

/* удалить все сообщения чатов (и файлы фото); $del = ещё и удалить сами чаты (кроме системных) */
function petgo_chats_wipe($ids, $del) {
    global $wpdb;
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return;
    }
    $in = implode(',', $ids);
    foreach ((array) $wpdb->get_col("SELECT photo FROM " . petgo_tb('msgs') . " WHERE chat_id IN ($in) AND photo<>''") as $u) {
        petgo_url_rm($u);
    }
    $wpdb->query("DELETE FROM " . petgo_tb('msgs') . " WHERE chat_id IN ($in)");
    $wpdb->query("UPDATE " . petgo_tb('members') . " SET last_read=0 WHERE chat_id IN ($in)");
    if ($del) {
        $wpdb->query("DELETE FROM " . petgo_tb('members') . " WHERE chat_id IN (SELECT id FROM (SELECT id FROM " . petgo_tb('chats') . " WHERE id IN ($in) AND type<>'sys') t)");
        $wpdb->query("DELETE FROM " . petgo_tb('chats') . " WHERE id IN ($in) AND type<>'sys'");
    }
}

add_action('admin_post_petgo_wipe', 'petgo_h_wipe');
function petgo_h_wipe() {
    /* 40.0: без пароля — владелец дважды подтверждает в приложении (два вопроса подряд) */
    petgo_guard('owner');
    $w = array_map('sanitize_key', (array) petgo_p('w'));
    if (!$w) {
        petgo_fail('err_na');
    }
    global $wpdb;
    $q = array('post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true);
    if (in_array('orders', $w, true)) {
        foreach (get_posts(array_merge($q, array('post_type' => 'petgo_order'))) as $id) {
            $rp = get_post_meta($id, '_rphoto', true);
            if (in_array('pets', $w, true) && is_array($rp) && !empty($rp['file'])) {
                petgo_rm($rp['file']);
            }
            wp_delete_post($id, true);
        }
    }
    if (in_array('pets', $w, true)) {
        foreach (get_posts(array_merge($q, array('post_type' => 'petgo_pet'))) as $id) {
            $a = get_post_meta($id, '_avatar', true);
            if (is_array($a) && !empty($a['file'])) {
                petgo_rm($a['file']);
            }
            foreach (petgo_photos($id) as $ph) {
                petgo_rm(isset($ph['file']) ? $ph['file'] : '');
            }
            wp_delete_post($id, true);
        }
    }
    if (in_array('reqs', $w, true)) {
        $wpdb->query('TRUNCATE TABLE ' . petgo_tb('reqs'));
    }
    if (in_array('chats', $w, true)) {
        petgo_chats_wipe($wpdb->get_col('SELECT id FROM ' . petgo_tb('chats')), true);
    }
    /* 40.0: все кабинеты клиентов (питомцы остаются в базе, если их не выбрали отдельно) */
    if (in_array('clients', $w, true)) {
        foreach (get_users(array('role' => 'petgo_client', 'fields' => 'ID', 'number' => -1)) as $cu) {
            if (function_exists('petgo_cl_delete_account')) {
                petgo_cl_delete_account((int) $cu);
            } else {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                wp_delete_user((int) $cu, get_current_user_id());
            }
        }
    }
    petgo_audit('wipe', implode(',', $w));
    petgo_bump();
    petgo_flash(petgo_t('wipe_ok'));
    petgo_back('orders');
}

function petgo_day_label($ts) {
    $k = wp_date('Y-m-d', $ts, petgo_tz());
    if ($k === wp_date('Y-m-d', time(), petgo_tz())) {
        return petgo_t('today');
    }
    if ($k === wp_date('Y-m-d', time() - DAY_IN_SECONDS, petgo_tz())) {
        return petgo_t('yesterday');
    }
    return wp_date('d.m.Y', $ts, petgo_tz());
}

/* уведомление на телефон о новом сообщении: имя отправителя и текст */
function petgo_chat_recipients($c, $from, $subj) {
    global $wpdb;
    $to = array();
    if ($c->type !== 'sys') {
        foreach ((array) $wpdb->get_col($wpdb->prepare('SELECT user_id FROM ' . petgo_tb('members') . ' WHERE chat_id=%d', $c->id)) as $u) {
            $to[] = (int) $u;
        }
    } else {
        $m    = petgo_sys_owner_role();
        $role = isset($m[$c->ckey]) ? $m[$c->ckey] : '';
        $top  = petgo_push_users(array('owner', 'admin'));
        if ($subj && $subj !== $from) {
            $to = array($subj);
        } elseif ($subj === $from) {
            $to = $top;
        } else {
            $to = array_merge($top, $role ? petgo_push_users(array($role)) : array());
        }
    }
    return array_values(array_diff(array_unique(array_map('intval', $to)), array((int) $from)));
}

function petgo_chat_push($c, $from, $subj, $body, $photo) {
    $to = petgo_chat_recipients($c, $from, (int) $subj);
    if (!$to) {
        return;
    }
    $u     = get_userdata($from);
    $name  = $u ? $u->display_name : 'PetGo';
    $title = $c->type === 'dm' ? $name : ($c->type === 'client' ? $name . ' · ' . petgo_t('cl_chat_sub') : $name . ' · ' . $c->title);
    $text  = $body !== '' ? preg_replace('/\s+/u', ' ', $body) : '📷 ' . petgo_t('c_photo');
    if ($body !== '' && $photo) {
        $text = '📷 ' . $text;
    }
    petgo_push_notify($to, $title, $text, petgo_page_url(array('c' => (int) $c->id)), 'c' . (int) $c->id);
}

/* название, аватар и подпись чата для списка и шапки */
function petgo_chat_head($c, $uid) {
    global $wpdb;
    if ($c->type === 'dm') {
        $o = (int) $wpdb->get_var($wpdb->prepare('SELECT user_id FROM ' . petgo_tb('members') . ' WHERE chat_id=%d AND user_id<>%d LIMIT 1', $c->id, $uid));
        $x = $o ? get_userdata($o) : false;
        return array('t' => $x ? $x->display_name : '—', 'av' => $x ? petgo_user_avatar_url($x->ID) : '', 'sub' => $x ? petgo_t('role_' . petgo_role_of($x)) : '');
    }
    if ($c->type === 'sys') {
        return array('t' => $c->title, 'av' => isset($c->av) && $c->av !== '' ? petgo_media($c->av) : '', 'sub' => petgo_t('c_sys'));
    }
    /* 34.0: чат с клиентом из его кабинета на сайте */
    if ($c->type === 'client' && preg_match('/^cl_(\d+)$/', (string) $c->ckey, $m)) {
        $x = get_userdata((int) $m[1]);
        return array('t' => $x ? $x->display_name : $c->title, 'av' => $x ? petgo_user_avatar_url($x->ID) : '', 'sub' => petgo_t('cl_chat_sub') . ($x ? ' · ' . petgo_cl_code($x->ID) : ''));
    }
    $n = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . petgo_tb('members') . ' WHERE chat_id=%d', $c->id));
    return array('t' => $c->title, 'av' => isset($c->av) && $c->av !== '' ? petgo_media($c->av) : '', 'sub' => $n . ' ' . petgo_t('c_members'));
}

/* время в списке чатов: сегодня — часы, на этой неделе — день, раньше — дата */
function petgo_chat_when($ts) {
    $tz = petgo_tz();
    if (wp_date('Y-m-d', $ts, $tz) === wp_date('Y-m-d', time(), $tz)) {
        return wp_date('H:i', $ts, $tz);
    }
    if (time() - $ts < 6 * DAY_IN_SECONDS) {
        $d = petgo_day_short();
        return $d[(int) wp_date('w', $ts, $tz)];
    }
    return wp_date('d.m.y', $ts, $tz);
}

function petgo_chat_out($ok, $extra = array()) {
    wp_send_json(array_merge(array('ok' => $ok), $extra));
}

/* ============================================================
   AJAX: единая точка для чатов (экономно: только нужные выборки, без постоянных соединений)
   ============================================================ */
add_action('wp_ajax_petgo_chat', 'petgo_ajax_chat');
function petgo_ajax_chat() {
    check_ajax_referer('petgo_chat', 'n');
    $role = petgo_role_of();
    if ($role === '') {
        petgo_chat_out(false, array('m' => '403'));
    }
    global $wpdb;
    $uid = get_current_user_id();
    $op  = isset($_POST['op']) ? sanitize_key(wp_unslash($_POST['op'])) : '';

    if ($op === 'ver') {
        petgo_chat_out(true, array('v' => petgo_ver(), 'n' => array_sum(petgo_unread_map($uid, $role))));
    }

    if ($op === 'unread') {
        petgo_chat_out(true, array('n' => array_sum(petgo_unread_map($uid, $role))));
    }

    if ($op === 'list') {
        $un  = petgo_unread_map($uid, $role);
        $ch  = petgo_chats_for($uid, $role);
        $dm  = array();
        foreach ($ch as $c) {
            if ($c->type === 'dm') {
                $dm[] = (int) $c->id;
            }
        }
        $out = array();
        foreach ($ch as $c) {
            $l    = $wpdb->get_row('SELECT user_id,body,photo,ts FROM ' . petgo_tb('msgs') . ' WHERE chat_id=' . (int) $c->id . petgo_vis_sql($c, $uid, $role) . ' ORDER BY id DESC LIMIT 1');
            $head = petgo_chat_head($c, $uid);
            $who  = '';
            if ($l && (int) $l->user_id && $c->type !== 'dm') {
                $wu  = (int) $l->user_id === $uid ? false : get_userdata((int) $l->user_id);
                $who = (int) $l->user_id === $uid ? petgo_t('c_you') : ($wu ? $wu->display_name : '');
            }
            $out[] = array('id' => (int) $c->id, 'k' => $c->ckey, 'av' => $head['av'], 'type' => $c->type, 't' => $head['t'], 'sub' => $head['sub'], 'u' => isset($un[(int) $c->id]) ? $un[(int) $c->id] : 0,
                'w' => $who, 'l' => $l ? mb_substr($l->body !== '' ? preg_replace('/\s+/', ' ', $l->body) : '📷 ' . petgo_t('c_photo'), 0, 80) : '', 'at' => $l ? petgo_chat_when((int) $l->ts) : '', 'ts' => $l ? (int) $l->ts : 0);
        }
        usort($out, function ($a, $b) {
            return $b['ts'] - $a['ts'];
        });
        petgo_chat_out(true, array('chats' => $out));
    }

    if ($op === 'new') {
        if ($role !== 'owner') {
            petgo_chat_out(false, array('m' => petgo_t('err_perm')));
        }
        $ids = array();
        foreach (explode(',', (string) wp_unslash(isset($_POST['users']) ? $_POST['users'] : '')) as $x) {
            if (petgo_staff_obj((int) $x)) {
                $ids[(int) $x] = (int) $x;
            }
        }
        if (!$ids) {
            petgo_chat_out(false, array('m' => petgo_t('c_pick')));
        }
        $title = mb_substr(sanitize_text_field((string) wp_unslash(isset($_POST['title']) ? $_POST['title'] : '')), 0, 120);
        $ct    = petgo_tb('chats');
        if (count($ids) === 1 && $title === '') {
            $o   = reset($ids);
            $xo  = get_userdata($o);
            $tt  = $xo ? $xo->display_name : '';
            $key = 'dm_' . min($uid, $o) . '_' . max($uid, $o);
            $cid = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $ct WHERE ckey=%s", $key));
            if (!$cid) {
                $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $ct (ckey,type,title,created) VALUES (%s,'dm','',%d)", $key, time()));
                $cid = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $ct WHERE ckey=%s", $key));
            }
        } else {
            if ($title === '') {
                $nm = array();
                foreach ($ids as $i) {
                    $x    = get_userdata($i);
                    $nm[] = $x ? $x->display_name : '';
                }
                $title = mb_substr(implode(', ', $nm), 0, 120);
            }
            $tt = $title;
            $wpdb->query($wpdb->prepare("INSERT INTO $ct (ckey,type,title,created) VALUES (%s,'group',%s,%d)", 'g_' . wp_generate_password(12, false), $title, time()));
            $cid = (int) $wpdb->insert_id;
        }
        if (!$cid) {
            petgo_chat_out(false, array('m' => petgo_t('c_err')));
        }
        $ids[$uid] = $uid;
        foreach ($ids as $i) {
            $wpdb->query($wpdb->prepare('INSERT IGNORE INTO ' . petgo_tb('members') . ' (chat_id,user_id,last_read) VALUES (%d,%d,0)', $cid, $i));
        }
        petgo_chat_out(true, array('id' => $cid, 't' => $tt));
    }

    if ($op === 'clear' || $op === 'delchat') {
        if ($role !== 'owner') {
            petgo_chat_out(false, array('m' => petgo_t('err_perm')));
        }
        $allowed = array();
        foreach (petgo_chats_for($uid, $role) as $x) {
            $allowed[(int) $x->id] = 1;
        }
        $ids = array();
        foreach (explode(',', (string) wp_unslash(isset($_POST['ids']) ? $_POST['ids'] : '')) as $x) {
            if (isset($allowed[(int) $x])) {
                $ids[] = (int) $x;
            }
        }
        petgo_chats_wipe($ids, $op === 'delchat');
        petgo_chat_out(true);
    }

    $c = petgo_chat_row(isset($_POST['chat']) ? (int) $_POST['chat'] : 0);
    if (!petgo_chat_access($c, $uid, $role)) {
        petgo_chat_out(false, array('m' => '403'));
    }
    $own = ($role === 'owner');

    if ($op === 'delmsg') {
        if (!$own) {
            petgo_chat_out(false, array('m' => petgo_t('err_perm')));
        }
        $list = explode(',', (string) wp_unslash(isset($_POST['ids']) ? $_POST['ids'] : (isset($_POST['id']) ? $_POST['id'] : '')));
        foreach ($list as $x) {
            $mid = (int) $x;
            if (!$mid) {
                continue;
            }
            $ph = $wpdb->get_var($wpdb->prepare('SELECT photo FROM ' . petgo_tb('msgs') . ' WHERE id=%d AND chat_id=%d', $mid, $c->id));
            if ($ph) {
                petgo_url_rm($ph);
            }
            $wpdb->delete(petgo_tb('msgs'), array('id' => $mid, 'chat_id' => (int) $c->id), array('%d', '%d'));
        }
        petgo_chat_out(true);
    }

    if ($op === 'perms') {
        if (!$own) {
            petgo_chat_out(false, array('m' => petgo_t('err_perm')));
        }
        $wpdb->update(petgo_tb('chats'), array('ph' => empty($_POST['ph']) ? 0 : 1, 'lk' => empty($_POST['lk']) ? 0 : 1), array('id' => (int) $c->id), array('%d', '%d'), array('%d'));
        petgo_chat_out(true);
    }

    if ($op === 'cav') {
        if (!$own || $c->type === 'dm' || !isset($_FILES['photo'])) {
            petgo_chat_out(false, array('m' => petgo_t('err_perm')));
        }
        $r = petgo_save_image($_FILES['photo'], 300, 72);
        if (!is_array($r)) {
            petgo_chat_out(false, array('m' => petgo_t('err_photo')));
        }
        if ($c->av !== '') {
            petgo_url_rm($c->av);
        }
        $wpdb->update(petgo_tb('chats'), array('av' => $r['url']), array('id' => (int) $c->id), array('%s'), array('%d'));
        petgo_chat_out(true, array('av' => petgo_media($r['url'])));
    }

    if ($op === 'msgs') {
        $after = isset($_POST['after']) ? (int) $_POST['after'] : 0;
        $vis   = petgo_vis_sql($c, $uid, $role);
        $mt    = petgo_tb('msgs');
        if ($after > 0) {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $mt WHERE chat_id=%d AND id>%d $vis ORDER BY id ASC LIMIT 100", $c->id, $after));
        } else {
            $rows = array_reverse((array) $wpdb->get_results($wpdb->prepare("SELECT * FROM $mt WHERE chat_id=%d $vis ORDER BY id DESC LIMIT 60", $c->id)));
        }
        $uids = array();
        foreach ($rows as $r) {
            $uids[(int) $r->user_id] = 1;
            $uids[(int) $r->subject_id] = 1;
        }
        unset($uids[0]);
        if ($uids) {
            cache_users(array_keys($uids));
        }
        $msgs = array();
        $max  = 0;
        foreach ($rows as $r) {
            $a  = (int) $r->user_id ? get_userdata((int) $r->user_id) : false;
            /* 34.7: у уведомлений кабинета клиента (оценка, место няни) subject — номер заказа, а не сотрудник */
            $s  = ((int) $r->subject_id && !in_array($r->kind, array('rv', 'loc', 'ai'), true)) ? get_userdata((int) $r->subject_id) : false;
            $max = max($max, (int) $r->id);
            $msgs[] = array('id' => (int) $r->id, 'ts' => (int) $r->ts, 'u' => (int) $r->user_id, 'n' => (int) $r->user_id ? ($a ? $a->display_name : '—') : ($r->kind === 'ai' ? 'Помощник PetGo' : 'petgo.kz'),
                'av' => $a ? petgo_user_avatar_url($a->ID) : '', 'me' => (int) $r->user_id === $uid, 'k' => $r->kind, 'h' => petgo_msg_html($r->body),
                'p' => petgo_media($r->photo), 't' => petgo_fmt((int) $r->ts), 'tm' => wp_date('H:i', (int) $r->ts, petgo_tz()), 'd' => wp_date('Y-m-d', (int) $r->ts, petgo_tz()), 'dl' => petgo_day_label((int) $r->ts), 's' => (int) $r->subject_id, 'sn' => $s ? $s->display_name : '');
        }
        if ($max) {
            petgo_mark_read((int) $c->id, $uid, $max);
        }
        $first = ($after === 0);
        $head  = $first ? petgo_chat_head($c, $uid) : null;
        petgo_chat_out(true, array('msgs' => $msgs, 'chat' => $first ? array('id' => (int) $c->id, 'type' => $c->type, 'sys' => $c->type === 'sys', 'ph' => (int) $c->ph, 'lk' => (int) $c->lk, 'own' => $own,
            't' => $head['t'], 'av' => $head['av'], 'sub' => $head['sub'], 'to' => ($c->type === 'sys' && in_array($role, array('owner', 'admin'), true))) : null));
    }

    if ($op === 'send') {
        $k = 'petgo_th_' . $uid;
        $n = (int) get_transient($k);
        if ($n >= 20) {
            petgo_chat_out(false, array('m' => petgo_t('c_wait')));
        }
        $body = mb_substr(sanitize_textarea_field((string) wp_unslash(isset($_POST['body']) ? $_POST['body'] : '')), 0, 2000);
        $has  = isset($_FILES['photo']) && !empty($_FILES['photo']['name']);
        if ($body === '' && !$has) {
            petgo_chat_out(false, array('m' => petgo_t('c_empty')));
        }
        if (!$own) {
            if ($has && !(int) $c->ph) {
                petgo_chat_out(false, array('m' => petgo_t('c_ph_off')));
            }
            if ($body !== '' && !(int) $c->lk && petgo_has_link($body)) {
                petgo_chat_out(false, array('m' => petgo_t('c_lk_off')));
            }
        }
        $photo = '';
        if ($has) {
            $r = petgo_save_image($_FILES['photo'], 1024, 68);
            if (!is_array($r)) {
                petgo_chat_out(false, array('m' => petgo_t('err_photo')));
            }
            $photo = $r['url'];
        }
        /* защита от повторной отправки (двойное нажатие, плохая связь): то же сообщение за 10 секунд не дублируем */
        if (!$has && $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . petgo_tb('msgs') . ' WHERE chat_id=%d AND user_id=%d AND body=%s AND ts>%d LIMIT 1', $c->id, $uid, $body, time() - 10))) {
            petgo_chat_out(true);
        }
        $kind = 'msg';
        $subj = 0;
        if ($c->type === 'sys') {
            $m = petgo_sys_owner_role();
            if (isset($m[$c->ckey]) && $m[$c->ckey] === $role) {
                $subj = $uid;
            } elseif (in_array($role, array('owner', 'admin'), true) && !empty($_POST['subject']) && petgo_staff_obj((int) $_POST['subject'])) {
                $subj = (int) $_POST['subject'];
            }
        }
        $wpdb->insert(petgo_tb('msgs'), array('chat_id' => (int) $c->id, 'user_id' => $uid, 'subject_id' => $subj, 'kind' => $kind, 'body' => $body, 'photo' => $photo, 'ts' => time()), array('%d', '%d', '%d', '%s', '%s', '%s', '%d'));
        set_transient($k, $n + 1, 60);
        petgo_chat_push($c, $uid, $subj, $body, $photo !== '');
        petgo_chat_out(true);
    }
    petgo_chat_out(false, array('m' => 'op'));
}

/* ============================================================
   ИНТЕРФЕЙС ЧАТА (как в Telegram): список слева/на весь экран, переписка поверх, поле ввода прямо над клавиатурой
   Логика — в assets/app.js
   ============================================================ */
function petgo_chat_ui($adm) {
    $auto = isset($_GET['c']) ? sanitize_key(wp_unslash($_GET['c'])) : '';
    $own  = petgo_is_owner();
    $back = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';
    $dots = petgo_icon('more');
    echo '<div class="pgc-chat" id="pgc-chat" data-auto="' . esc_attr($auto) . '" aria-hidden="true"><div class="pgc-cw">';
    echo '<aside class="pgc-clp"><div class="pgc-ch"><button type="button" class="pgc-bk" id="pgc-cx" aria-label="' . esc_attr(petgo_t('close')) . '">' . $back . '</button><b class="pgc-chtl">' . esc_html(petgo_t('chat')) . '</b>' . ($own ? '<button type="button" class="pgc-bk" id="pgc-lhm" aria-label="menu">' . $dots . '</button>' : '<span class="pgc-bkph" aria-hidden="true"></span>') . '<button type="button" class="pgc-bk pgc-cx2 desk" id="pgc-cx2" aria-label="' . esc_attr(petgo_t('close')) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>';
    if ($own) {
        echo '<div class="pgc-sel" id="pgc-sel" hidden><button type="button" class="pgc-btn sec sm" id="pgc-selclr">' . esc_html(petgo_t('c_clear')) . '</button><button type="button" class="pgc-btn red sm" id="pgc-seldel">' . esc_html(petgo_t('c_delete')) . '</button><button type="button" class="pgc-btn sec sm" id="pgc-selx">' . esc_html(petgo_t('c_cancel')) . '</button></div>';
    }
    echo '<div class="pgc-cl" id="pgc-cl"><div class="pgc-load"><i class="pgc-spin"></i></div></div></aside>';
    echo '<section class="pgc-cth" id="pgc-cth"><div class="pgc-ch"><button type="button" class="pgc-bk" id="pgc-cback" aria-label="back">' . $back . '</button><span class="pgc-chav" id="pgc-chav"></span><span class="pgc-cht"><b id="pgc-ctt"></b><small id="pgc-cts"></small></span>' . ($own ? '<button type="button" class="pgc-bk" id="pgc-cdots" aria-label="menu">' . $dots . '</button>' : '') . '</div>';
    if ($own) {
        echo '<div class="pgc-sel" id="pgc-msel" hidden><button type="button" class="pgc-btn red sm" id="pgc-msdel">' . esc_html(petgo_t('c_delete')) . '</button><button type="button" class="pgc-btn sec sm" id="pgc-msx">' . esc_html(petgo_t('c_cancel')) . '</button></div>';
    }
    echo '<div class="pgc-ms" id="pgc-ms"></div><div class="pgc-to" id="pgc-to" hidden></div>';
    echo '<div class="pgc-pv" id="pgc-pv" hidden><img id="pgc-pvi" alt=""><span id="pgc-pvn"></span><button type="button" id="pgc-pvx" aria-label="' . esc_attr(petgo_t('close')) . '">×</button></div>';
    echo '<div class="pgc-cf" id="pgc-cf"><label class="pgc-att" id="pgc-att" aria-label="' . esc_attr(petgo_t('c_photo')) . '"><input type="file" id="pgc-cfile" accept="image/*" hidden><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.5l-8.6 8.6a6 6 0 0 1-8.5-8.5l9-9a4 4 0 0 1 5.7 5.7l-9 9a2 2 0 0 1-2.8-2.8l8.4-8.4"/></svg></label>';
    echo '<textarea id="pgc-ci" rows="1" maxlength="2000" enterkeyhint="send" autocomplete="off" autocorrect="on" placeholder="' . esc_attr(petgo_t('c_msg')) . '"></textarea>';
    echo '<button type="button" class="pgc-cs" id="pgc-cs" aria-label="' . esc_attr(petgo_t('c_send')) . '"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.4 20.4l17.4-7.5a1 1 0 0 0 0-1.8L3.4 3.6a.9.9 0 0 0-1.3 1l1.9 6.5 9 .9-9 .9-1.9 6.5a.9.9 0 0 0 1.3 1z" fill="currentColor"/></svg></button></div>';
    echo '<div class="pgc-empty" id="pgc-empty">' . petgo_icon('chat') . '<span>' . esc_html(petgo_t('c_pickchat')) . '</span></div></section></div>';
    if ($own) {
        echo '<div class="pgc-menu pgc-fx" id="pgc-cmenu"><button type="button" class="pgc-mi" id="pgc-cmadd">' . petgo_ic('chatplus') . esc_html(petgo_t('c_add')) . '</button><button type="button" class="pgc-mi" id="pgc-cmsel">' . petgo_ic('select') . esc_html(petgo_t('c_select')) . '</button></div>';
        echo '<div class="pgc-menu pgc-fx" id="pgc-tmenu"><button type="button" class="pgc-mi" id="pgc-tav">' . petgo_ic('photo') . esc_html(petgo_t('c_setav')) . '</button><button type="button" class="pgc-mi" id="pgc-tsel">' . petgo_ic('select') . esc_html(petgo_t('c_selmode')) . '</button><button type="button" class="pgc-mi" id="pgc-tclr">' . petgo_ic('broom') . esc_html(petgo_t('c_clear_one')) . '</button><button type="button" class="pgc-mi" id="pgc-tph">' . petgo_ic('photo') . '<span id="pgc-tphs"></span></button><button type="button" class="pgc-mi" id="pgc-tlk">' . petgo_ic('link') . '<span id="pgc-tlks"></span></button></div><input type="file" id="pgc-cavf" accept="image/*" hidden>';
        $b = '<label class="pgc-f"><span>' . esc_html(petgo_t('c_name')) . '</span><input type="text" id="pgc-cnn" maxlength="120"></label><div class="pgc-f"><span>' . esc_html(petgo_t('c_pick')) . '</span></div><div class="pgc-pks" id="pgc-cn">';
        foreach (petgo_staff_users() as $su) {
            $b .= '<label class="pgc-pk"><input type="checkbox" value="' . (int) $su->ID . '">' . petgo_av(petgo_user_avatar_url($su->ID), $su->display_name, 'sm') . '<span>' . esc_html($su->display_name) . '<small>' . esc_html(petgo_t('role_' . petgo_role_of($su))) . '</small></span></label>';
        }
        $b .= '</div><button type="button" class="pgc-btn wide" id="pgc-cmk">' . esc_html(petgo_t('c_create')) . '</button>';
        echo petgo_modal('pgc-m-newchat', petgo_t('c_add'), $b);
    }
    echo '</div>';
}
