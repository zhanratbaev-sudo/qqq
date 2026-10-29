<?php
/**
 * Демо-данные для локальной копии PetGo (все имена и номера вымышленные).
 * Запуск: php seed.php /путь/к/wordpress
 */
require $argv[1] . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
global $wpdb;

update_option('timezone_string', 'Asia/Almaty');
update_option('petgo_rv_auto', 1, false);
$site = (array) get_option('petgo_site', array());
$site['wa'] = '77478175712';
update_option('petgo_site', $site, false);

$owner = get_user_by('login', 'owner');
wp_update_user(array('ID' => $owner->ID, 'display_name' => 'Асель Демо', 'first_name' => 'Асель'));

// --- убираем то, что создаётся во время записи (чтобы запись можно было повторять) ---
$m = get_user_by('login', 'marina');
if ($m) {
    wp_delete_user($m->ID);
}
$wpdb->delete($wpdb->prefix . 'petgo_reqs', array('name' => 'Жанар', 'breed' => 'Корги'));

// --- сотрудники ---
$staff = array(
    array('aigerim', 'Айгерим Сапарова', 'manager', '+7 700 100 20 30'),
    array('dana', 'Дана Ким', 'nanny', '+7 700 200 30 40'),
    array('aliya', 'Алия Нурланова', 'nanny', '+7 700 300 40 50'),
    array('erzhan', 'Ержан Абенов', 'nanny', '+7 700 400 50 60'),
);
$uid = array();
foreach ($staff as $s) {
    $u = get_user_by('login', $s[0]);
    $id = $u ? $u->ID : wp_insert_user(array('user_login' => $s[0], 'user_pass' => 'Demo-Staff-2026!', 'user_email' => $s[0] . '@petgo.test',
        'display_name' => $s[1], 'first_name' => $s[1], 'role' => 'petgo_' . $s[2]));
    update_user_meta($id, 'petgo_phone', $s[3]);
    $uid[$s[0]] = $id;
}

// --- питомцы ---
$pets = array(
    array('Рекс', 'dog', 'Лабрадор', '3 года', 'boy', '32 кг', 'Марат Демо', 'Любит мячик, тянет поводок'),
    array('Луна', 'dog', 'Корги', '2 года', 'girl', '11 кг', 'Жанна Демо', 'Боится салютов'),
    array('Барсик', 'cat', 'Британец', '5 лет', 'boy', '6 кг', 'Ольга Демо', 'Корм 2 раза в день'),
    array('Мия', 'cat', 'Шотландская', '1 год', 'girl', '3 кг', 'Тимур Демо', 'Любит играть с удочкой'),
    array('Тайсон', 'dog', 'Хаски', '4 года', 'boy', '24 кг', 'Айдос Демо', 'Длинные прогулки'),
    array('Бусинка', 'dog', 'Той-пудель', '6 лет', 'girl', '3 кг', 'Сауле Демо', 'Зимой в комбинезоне'),
);
$pid = array();
foreach ($pets as $i => $p) {
    $ex = get_page_by_title($p[0], OBJECT, 'petgo_pet');
    $id = $ex ? $ex->ID : wp_insert_post(array('post_type' => 'petgo_pet', 'post_status' => 'publish', 'post_title' => $p[0]));
    $meta = array('species' => $p[1], 'breed' => $p[2], 'age' => $p[3], 'sex' => $p[4], 'weight' => $p[5], 'owner' => $p[6],
        'phone' => '+7 700 555 0' . ($i + 1) . ' 0' . ($i + 1), 'address' => 'Алматы, мкр. Самал-' . ($i % 3 + 1) . ', д. ' . (10 + $i),
        'notes' => $p[7], 'neutered' => 'yes', 'vaccines' => 'Все прививки по графику');
    foreach ($meta as $k => $v) {
        update_post_meta($id, '_pf_' . $k, $v);
    }
    $pid[] = $id;
}

// --- заказы на сегодня/завтра ---
if (!get_posts(array('post_type' => 'petgo_order', 'numberposts' => 1))) {
    // время считаем от «сейчас», чтобы статусы выглядели правдоподобно в момент записи
    $now = (int) (floor(time() / 300) * 300);
    $orders = array(  // питомец, няня, услуга, сдвиг в минутах, длительность, статус
        array(0, 'dana', 'walk', -180, 60, 'confirmed'),
        array(1, 'aliya', 'walk', -25, 60, 'picked'),
        array(2, 'erzhan', 'visit', -5, 40, 'arrived'),
        array(3, 'dana', 'visit', 90, 40, 'assigned'),
        array(4, '', 'walk', 240, 90, 'new'),
        array(5, 'aliya', 'walk', 1440, 60, 'assigned'),
        array(0, 'erzhan', 'walk', 1560, 60, 'assigned'),
    );
    foreach ($orders as $o) {
        $ts = $now + $o[3] * 60;
        $id = wp_insert_post(array('post_type' => 'petgo_order', 'post_status' => 'publish', 'post_title' => 'Order'));
        wp_update_post(array('ID' => $id, 'post_title' => 'Order #' . $id));
        $n = $o[1] ? get_userdata($uid[$o[1]]) : null;
        update_post_meta($id, '_pet', $pid[$o[0]]);
        update_post_meta($id, '_nanny', $n ? $n->ID : 0);
        update_post_meta($id, '_nanny_name', $n ? $n->display_name : '');
        update_post_meta($id, '_service', $o[2]);
        update_post_meta($id, '_ts', $ts);
        update_post_meta($id, '_dur', $o[4]);
        update_post_meta($id, '_comment', '');
        update_post_meta($id, '_status', $o[5]);
        if (in_array($o[5], array('arrived', 'picked', 'confirmed'), true)) {
            update_post_meta($id, '_arrived_at', $ts - 60);
        }
    }
}

// --- заявки с сайта ---
$t = $wpdb->prefix . 'petgo_reqs';
if (!(int) $wpdb->get_var("SELECT COUNT(*) FROM $t")) {
    $reqs = array(
        array('Жанар', 'dog', '9 кг', 'Бигль', 'Выгул по будням в 13:00', 'new', 5400),
        array('Алексей', 'cat', '', '', 'Уезжаем на неделю, нужны визиты к кошке', 'new', 3100),
        array('Мадина', 'dog', '28 кг', 'Немецкая овчарка', 'Долгая прогулка в выходные', 'new', 1500),
        array('Ирина', 'cat', '', '', 'Покормить и поиграть, 2 кошки', 'new', 700),
        array('Нурлан', 'dog', '4 кг', 'Шпиц', 'Нужна няня на вечер пятницы', 'new', 240),
        array('Светлана', 'dog', '15 кг', 'Бордер-колли', 'Активный выгул утром', 'done', 86400),
        array('Даурен', 'cat', '', '', 'Визит на 3 дня', 'done', 172800),
    );
    foreach ($reqs as $i => $r) {
        $wpdb->insert($t, array('name' => $r[0], 'phone' => '+7 700 6' . $i . $i . ' ' . $i . '0 ' . $i . '1', 'contact' => $i % 2 ? 'call' : 'write',
            'species' => $r[1], 'sp_other' => '', 'weight' => $r[2], 'breed' => $r[3], 'note' => $r[4], 'extra' => '', 'pet_id' => 0,
            'status' => $r[5], 'ts' => time() - $r[6]));
    }
}

// --- чат владельца с няней ---
$a = min($owner->ID, $uid['dana']);
$b = max($owner->ID, $uid['dana']);
$ct = $wpdb->prefix . 'petgo_chats';
$key = 'dm_' . $a . '_' . $b;
if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM $ct WHERE ckey=%s", $key))) {
    $wpdb->insert($ct, array('ckey' => $key, 'type' => 'dm', 'title' => '', 'created' => time() - 7200));
    $cid = (int) $wpdb->insert_id;
    foreach (array($owner->ID, $uid['dana']) as $m) {
        $wpdb->insert($wpdb->prefix . 'petgo_members', array('chat_id' => $cid, 'user_id' => $m, 'last_read' => 0));
    }
    $msgs = array(
        array($uid['dana'], 'Добрый день! Забрала Рекса, идём в парк', 3000),
        array($owner->ID, 'Отлично, спасибо! Он сегодня поел?', 2900),
        array($uid['dana'], 'Да, покормила в 9:00. Гуляем час, потом домой', 2800),
        array($uid['dana'], 'Вернулись, всё хорошо. Лапы помыли', 600),
    );
    foreach ($msgs as $m) {
        $wpdb->insert($wpdb->prefix . 'petgo_msgs', array('chat_id' => $cid, 'user_id' => $m[0], 'subject_id' => 0, 'kind' => 'msg', 'body' => $m[1], 'photo' => '', 'ts' => time() - $m[2]));
    }
}
echo "seeded: staff=" . count($uid) . " pets=" . count($pid) . "\n";
