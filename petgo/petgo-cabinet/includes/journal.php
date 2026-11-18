<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ЖУРНАЛ ДЕЙСТВИЙ, ЭКСПОРТ И РЕЗЕРВНАЯ КОПИЯ (открываются из «Настроек»)
   ============================================================ */

/* ---------- журнал действий ---------- */
function petgo_audit($act, $info = '', $uid = null) {
    global $wpdb;
    $wpdb->insert(petgo_tb('log'), array(
        'ts' => time(), 'uid' => $uid === null ? get_current_user_id() : (int) $uid, 'act' => mb_substr((string) $act, 0, 40),
        'info' => mb_substr((string) $info, 0, 255), 'ip' => mb_substr(petgo_ip(), 0, 45),
    ), array('%d', '%d', '%s', '%s', '%s'));
    if (mt_rand(1, 60) === 1) {
        $wpdb->query($wpdb->prepare('DELETE FROM ' . petgo_tb('log') . ' WHERE ts<%d', time() - 200 * DAY_IN_SECONDS));
    }
}

/* все действия сотрудников из кабинета пишутся автоматически (без паролей и текстов) */
add_action('admin_init', 'petgo_audit_auto', 1);
function petgo_audit_auto() {
    global $pagenow;
    if ($pagenow !== 'admin-post.php' || !isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || !is_user_logged_in()) {
        return;
    }
    $a = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';
    if (strpos($a, 'petgo_') !== 0 || in_array($a, array('petgo_req', 'petgo_img', 'petgo_lang_set', 'petgo_push_sub', 'petgo_manifest', 'petgo_sw'), true)) {
        return;
    }
    $parts = array();
    foreach (array('id', 'order', 'pet', 'user', 'status', 'st', 'nanny', 'role', 'type') as $k) {
        if (isset($_POST[$k]) && is_scalar($_POST[$k])) {
            $parts[] = $k . '=' . mb_substr(sanitize_text_field(wp_unslash((string) $_POST[$k])), 0, 30);
        }
    }
    petgo_audit(substr($a, 6), implode(' ', $parts));
}

add_action('wp_login', function ($login, $user) {
    if ($user && petgo_role_of($user) !== '') {
        petgo_audit('login', '', $user->ID);
    }
}, 10, 2);
add_action('wp_login_failed', function ($login) {
    petgo_audit('login_fail', mb_substr((string) $login, 0, 60), 0);
});

function petgo_audit_names() {
    return array(
        'order_new' => 'Создан заказ', 'order_assign' => 'Назначена няня', 'order_set' => 'Изменён статус заказа', 'arrive' => 'Няня прибыла', 'pick' => 'Няня забрала питомца',
        'finish' => 'Заказ завершён', 'pet_save' => 'Сохранён питомец', 'pet_del' => 'Удалён питомец', 'photo_del' => 'Удалено фото', 'staff_new' => 'Добавлен сотрудник',
        'staff_save' => 'Изменён сотрудник', 'staff_del' => 'Удалён сотрудник', 'profile_save' => 'Изменён профиль', 'form_save' => 'Изменена форма заявки',
        'form_reset' => 'Форма заявки сброшена', 'rv_set' => 'Модерация отзыва', 'rv_del' => 'Удалён отзыв', 'rv_opt' => 'Настройка отзывов', 'review_new' => 'Новый отзыв', 'site_save' => 'Изменены настройки сайта',
        'login' => 'Вход', 'login_fail' => 'Неудачный вход', 'export' => 'Экспорт данных', 'backup' => 'Резервная копия', 'wipe' => 'Очистка данных',
        'req_set' => 'Изменена заявка', 'req_del' => 'Удалена заявка', 'explain' => 'Объяснительная', 'photo_avatar' => 'Изменён аватар питомца',
    );
}

/* ---------- экспорт и резервная копия ---------- */
function petgo_csv_cell($v) {
    $v = (string) $v;
    if ($v !== '' && (preg_match('/^[=@\t\r]/', $v) || (preg_match('/^[+\-]/', $v) && !preg_match('/^[+\-]?[\d\s().\-]+$/', $v)))) {
        $v = "'" . $v;
    }
    return $v;
}

function petgo_csv_out($name, $head, $rows) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '-' . wp_date('Y-m-d') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, $head, ';');
    foreach ($rows as $r) {
        fputcsv($o, array_map('petgo_csv_cell', $r), ';');
    }
    fclose($o);
    exit;
}

add_action('admin_post_petgo_export', 'petgo_h_export');
function petgo_h_export() {
    check_admin_referer('petgo_exp');
    if (!is_user_logged_in() || !petgo_can('admin')) {
        wp_die('403', '', array('response' => 403));
    }
    global $wpdb;
    $type = isset($_GET['type']) ? sanitize_key(wp_unslash($_GET['type'])) : '';
    $from = isset($_GET['from']) ? (int) $_GET['from'] : 0;
    $to   = isset($_GET['to']) ? (int) $_GET['to'] : 0;
    petgo_audit('export', $type);
    $st = array('new' => 'Новый', 'assigned' => 'Назначен', 'arrived' => 'Няня на месте', 'picked' => 'Питомец у няни', 'done' => 'Ждёт подтверждения', 'confirmed' => 'Подтверждён', 'cancelled' => 'Отменён');
    if ($type === 'orders') {
        $args = array('numberposts' => 5000);
        if ($to > $from && $from > 0) {
            $args['meta_query'] = array(array('key' => '_ts', 'value' => array($from, $to - 1), 'compare' => 'BETWEEN', 'type' => 'NUMERIC'));
        }
        $list = petgo_orders($args);
        $rows = array();
        foreach ($list as $o) {
            $id = (int) $o->ID;
            $sv = petgo_o($id, 'service');
            $s  = petgo_o($id, 'status');
            $rows[] = array($id, wp_date('Y-m-d H:i', (int) petgo_o($id, 'ts'), petgo_tz()), petgo_pet_name($id), (string) get_post_meta((int) petgo_o($id, 'pet'), '_pf_owner', true), petgo_nanny_label($id), isset(petgo_services()[$sv]) ? petgo_t(petgo_services()[$sv]) : $sv, (int) petgo_o($id, 'dur'), isset($st[$s]) ? $st[$s] : $s);
        }
        petgo_csv_out('petgo-orders', array('№', 'Дата', 'Питомец', 'Хозяин', 'Няня', 'Услуга', 'Минут', 'Статус'), $rows);
    }
    if ($type === 'requests') {
        $rows = array();
        foreach ((array) $wpdb->get_results('SELECT * FROM ' . petgo_tb('reqs') . ' ORDER BY id DESC LIMIT 20000') as $r) {
            $x = ($r->extra !== null && $r->extra !== '') ? json_decode($r->extra, true) : array();
            $e = '';
            if (is_array($x)) {
                foreach ($x as $p) {
                    if (is_array($p) && count($p) === 2) {
                        $e .= $p[0] . ': ' . $p[1] . '; ';
                    }
                }
            }
            $rows[] = array($r->id, wp_date('Y-m-d H:i', (int) $r->ts, petgo_tz()), $r->name, $r->phone, $r->species, $r->sp_other, $r->weight, $r->breed, $r->note, $e, $r->status);
        }
        petgo_csv_out('petgo-requests', array('№', 'Дата', 'Имя', 'Телефон', 'Вид', 'Другое', 'Вес', 'Порода', 'Комментарий', 'Доп. поля', 'Статус'), $rows);
    }
    if ($type === 'pets') {
        $rows = array();
        foreach (petgo_pets() as $p) {
            $rows[] = array($p->ID, $p->post_title, get_post_meta($p->ID, '_pf_owner', true), get_post_meta($p->ID, '_pf_phone', true), get_post_meta($p->ID, '_pf_address', true));
        }
        petgo_csv_out('petgo-pets', array('№', 'Кличка', 'Хозяин', 'Телефон', 'Адрес'), $rows);
    }
    if ($type === 'reviews') {
        $rows = array();
        foreach ((array) $wpdb->get_results('SELECT * FROM ' . petgo_tb('reviews') . ' ORDER BY id DESC LIMIT 20000') as $r) {
            $rows[] = array($r->id, wp_date('Y-m-d H:i', (int) $r->ts, petgo_tz()), $r->name, $r->rating, $r->body, $r->status, $r->order_id);
        }
        petgo_csv_out('petgo-reviews', array('№', 'Дата', 'Имя', 'Оценка', 'Текст', 'Статус', 'Заказ'), $rows);
    }
    if ($type === 'log') {
        $rows = array();
        $nm   = petgo_audit_names();
        foreach ((array) $wpdb->get_results('SELECT * FROM ' . petgo_tb('log') . ' ORDER BY id DESC LIMIT 20000') as $r) {
            $u      = $r->uid ? get_userdata((int) $r->uid) : false;
            $rows[] = array(wp_date('Y-m-d H:i:s', (int) $r->ts, petgo_tz()), $u ? $u->display_name : '—', isset($nm[$r->act]) ? $nm[$r->act] : $r->act, $r->info, $r->ip);
        }
        petgo_csv_out('petgo-log', array('Время', 'Кто', 'Действие', 'Подробности', 'IP'), $rows);
    }
    wp_die('?', '', array('response' => 400));
}

add_action('admin_post_petgo_backup', 'petgo_h_backup');
function petgo_h_backup() {
    check_admin_referer('petgo_exp');
    if (!is_user_logged_in() || !petgo_can('admin')) {
        wp_die('403', '', array('response' => 403));
    }
    global $wpdb;
    petgo_audit('backup');
    $out = array('plugin' => 'petgo-cabinet', 'version' => PETGO_VER, 'made' => gmdate('c'), 'note' => 'Без паролей, чатов и файлов фотографий. Фото лежат в папках uploads/petgo и uploads/petgo-reviews.');
    foreach (array('pet' => 'petgo_pet', 'order' => 'petgo_order') as $k => $pt) {
        $out[$k . 's'] = array();
        $ids = get_posts(array('post_type' => $pt, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true));
        foreach ($ids as $id) {
            $meta = array();
            foreach ((array) get_post_meta($id) as $mk => $mv) {
                if (strpos($mk, '_edit') === 0) {
                    continue;
                }
                $meta[$mk] = maybe_unserialize($mv[0]);
            }
            $out[$k . 's'][] = array('id' => $id, 'title' => get_the_title($id), 'date' => get_post_field('post_date_gmt', $id), 'meta' => $meta);
        }
    }
    $out['requests'] = $wpdb->get_results('SELECT * FROM ' . petgo_tb('reqs'), ARRAY_A);
    $out['reviews']  = $wpdb->get_results('SELECT * FROM ' . petgo_tb('reviews'), ARRAY_A);
    $out['log']      = $wpdb->get_results('SELECT * FROM ' . petgo_tb('log') . ' ORDER BY id DESC LIMIT 5000', ARRAY_A);
    $out['staff']    = array();
    foreach (petgo_staff_users() as $u) {
        $out['staff'][] = array('id' => $u->ID, 'login' => $u->user_login, 'name' => $u->display_name, 'role' => petgo_role_of($u));
    }
    $out['options'] = array('form' => get_option('petgo_form', array()));
    while (ob_get_level()) {
        ob_end_clean();
    }
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="petgo-backup-' . wp_date('Y-m-d') . '.json"');
    echo wp_json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/* ---------- страница «Журнал действий» (из настроек) ---------- */
function petgo_page_log() {
    global $wpdb;
    $n    = isset($_GET['n']) ? max(50, min(1000, (int) $_GET['n'])) : 100;
    $nm   = petgo_audit_names();
    $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . petgo_tb('log') . ' ORDER BY id DESC LIMIT %d', $n));
    $ids  = array();
    foreach ((array) $rows as $r) {
        if ($r->uid) {
            $ids[(int) $r->uid] = (int) $r->uid;
        }
    }
    if ($ids) {
        cache_users(array_values($ids));
    }
    echo '<p class="mu pgc-full">' . esc_html(petgo_t('log_s')) . '</p>';
    if (!$rows) {
        echo petgo_empty('log', petgo_t('log_none'));
        return;
    }
    echo '<div class="pgc-tbl pgc-full"><table><thead><tr><th>' . esc_html(petgo_t('time')) . '</th><th>' . esc_html(petgo_t('log_who')) . '</th><th>' . esc_html(petgo_t('log_act')) . '</th><th>' . esc_html(petgo_t('log_info')) . '</th><th>IP</th></tr></thead><tbody>';
    foreach ((array) $rows as $r) {
        $u = $r->uid ? get_userdata((int) $r->uid) : false;
        echo '<tr><td class="nw">' . esc_html(petgo_fmt((int) $r->ts)) . '</td><td>' . esc_html($u ? $u->display_name : '—') . '</td><td>' . esc_html(isset($nm[$r->act]) ? $nm[$r->act] : $r->act) . '</td><td class="mu">' . esc_html($r->info) . '</td><td class="mu nw">' . esc_html($r->ip) . '</td></tr>';
    }
    echo '</tbody></table></div>';
    if (count((array) $rows) >= $n && $n < 1000) {
        echo '<p class="pgc-full"><a class="pgc-btn sec wide" href="' . esc_url(petgo_page_url(array('pg' => 'log', 'n' => $n + 200))) . '">' . esc_html(petgo_t('more')) . '</a></p>';
    }
}

/* ---------- маршрут дополнительных страниц ---------- */
function petgo_ext_page($pg) {
    if ($pg === 'cal' && petgo_can('office')) {
        petgo_page_cal();
    } elseif ($pg === 'reviews' && petgo_can('office')) {
        petgo_admin_reviews();
    } elseif ($pg === 'log' && petgo_can('admin')) {
        petgo_page_log();
    } elseif ($pg === 'site' && petgo_can('admin') && function_exists('petgo_page_site')) {
        petgo_page_site();
    } else {
        /* страницы от других плагинов: выводят себя и возвращают true */
        return (bool) apply_filters('petgo_app_page', false, $pg);
    }
    return true;
}
