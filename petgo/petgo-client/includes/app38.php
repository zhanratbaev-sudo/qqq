<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   38.1 КАБИНЕТ ПО МАКЕТУ «PetGo»
   Главная: город и колокольчик, приветствие, поиск няни, услуги плитками, акция, «Наши няни».
   Профиль няни, избранные няни, уведомления, профиль клиента со скидкой и меню.
   Разметка — здесь, поведение — assets/client.js (раздел 38.1), оформление — assets/client.css (раздел 38.1).
   ============================================================ */

/* значки, которых нет в общем наборе */
function petgo_cl_ic($n, $cls = 'pg-i') {
    $P = array(
        'bell'    => '<path d="M6 9.5a6 6 0 0 1 12 0c0 4.5 1.6 6.2 2.4 7H3.6c.8-.8 2.4-2.5 2.4-7Z"/><path d="M10 19.5a2 2 0 0 0 4 0"/>',
        'search'  => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'sliders' => '<path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/>',
        'heart'   => '<path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10C19.5 15.4 12 20 12 20Z"/>',
        'gift'    => '<rect x="3.5" y="8.5" width="17" height="4" rx="1"/><path d="M5 12.5V20h14v-7.5M12 8.5V20M12 8.5C10.5 5 7 4.5 7 6.6 7 8.5 12 8.5 12 8.5Zm0 0c1.5-3.5 5-4 5-1.9 0 1.9-5 1.9-5 1.9Z"/>',
        'help'    => '<circle cx="12" cy="12" r="8.5"/><path d="M9.6 9.6a2.5 2.5 0 1 1 3.4 2.3c-.6.3-1 .8-1 1.5v.4M12 16.8v.1"/>',
        'info'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.8v.1"/>',
        'doc'     => '<path d="M7 3.5h7l4 4V20a.5.5 0 0 1-.5.5h-10A.5.5 0 0 1 7 20V4a.5.5 0 0 1 0-.5Z"/><path d="M14 3.5V8h4M9.5 12.5h5M9.5 16h5"/>',
        'walk'    => '<circle cx="13" cy="4.5" r="1.8"/><path d="m9 21 2.2-6.5 2.8 2.5V21M8 10.5l2.5-3.2h3.2l2.5 3 2.3.7M11.2 14.5 12.5 9"/>',
        'cal'     => '<rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>',
        'back'    => '<path d="m14.5 5.5-6.5 6.5 6.5 6.5"/>',
        'pen'     => '<path d="m15.5 5.5 3 3L9 18H6v-3l9.5-9.5Z"/>',
        'sleep'   => '<path d="M14 4h4l-4 5h4M8 10h3l-3 4h3"/>',
    );
    $paw = '<svg class="' . esc_attr($cls) . ' pgk-paw" viewBox="0 0 100 100" fill="currentColor" aria-hidden="true"><ellipse cx="24" cy="44" rx="9" ry="11" transform="rotate(-18 24 44)"/><ellipse cx="40" cy="27" rx="9.5" ry="12"/><ellipse cx="60" cy="27" rx="9.5" ry="12"/><ellipse cx="76" cy="44" rx="9" ry="11" transform="rotate(18 76 44)"/><path d="M50 50c10 0 17 9 22 17 4 6 3 14-4 16-6 2-11-1-18-1s-12 3-18 1c-7-2-8-10-4-16 5-8 12-17 22-17z"/></svg>';
    if ($n === 'paw') {
        return $paw;
    }
    if ($n === 'verified') {
        return '<svg class="' . esc_attr($cls) . ' pgk-ver" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m12 2 2.4 1.8 3-.2.9 2.9 2.5 1.7-1 2.8 1 2.8-2.5 1.7-.9 2.9-3-.2L12 22l-2.4-1.8-3 .2-.9-2.9-2.5-1.7 1-2.8-1-2.8 2.5-1.7.9-2.9 3 .2Z"/><path d="m8.5 12.2 2.3 2.3 4.7-4.8" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    return isset($P[$n]) ? '<svg class="' . esc_attr($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $P[$n] . '</svg>' : '';
}

/* ---------- няни: карточки, профиль, отзывы ---------- */
function petgo_cl_nannies38() {
    global $wpdb;
    $out = array();
    foreach (petgo_site_nannies() as $n) {
        $rv = array();
        foreach ((array) $wpdb->get_results($wpdb->prepare('SELECT name, rating, body, ts FROM ' . petgo_tb('reviews') . " WHERE nanny_id=%d AND status='ok' ORDER BY ts DESC LIMIT 3", (int) $n['id'])) as $r) {
            $rv[] = array('n' => (string) $r->name, 'r' => (int) $r->rating, 't' => wp_trim_words((string) $r->body, 40, '…'), 'd' => function_exists('petgo_rv_ago') ? petgo_rv_ago((int) $r->ts) : '');
        }
        $u     = get_userdata((int) $n['id']);
        $out[] = array('id' => (int) $n['id'], 'name' => $n['name'], 'img' => $n['img'], 'rate' => $n['rate'], 'rn' => (int) $n['rn'], 'done' => (int) $n['done'], 'bio' => $n['bio'], 'new' => !empty($n['new']),
            'since' => $u ? (int) wp_date('Y', strtotime((string) $u->user_registered)) : 0, 'rv' => $rv);
    }
    return $out;
}

function petgo_cl_favs($uid) {
    $f = get_user_meta($uid, 'petgo_fav_nn', true);
    return is_array($f) ? array_values(array_map('intval', $f)) : array();
}

/* карточка няни на главной и в списке (по макету «Nearby sitters») */
function petgo_cl_nanny_card($n, $fav, $price) {
    $L = 'petgo_site_l';
    $h = '<button type="button" class="pg-b pgk-nc" data-k-nanny="' . (int) $n['id'] . '"><span class="pgk-nc-av">' . petgo_cl_av($n['img'], $n['name']) . '</span><span class="pgk-nc-b"><b>' . esc_html($n['name']) . petgo_cl_ic('verified', 'pg-i pgk-ver') . '</b>';
    $h .= '<span class="pgk-nc-r">' . ($n['rate'] ? petgo_cl_icon('star', 'pg-i pgk-star') . '<em>' . esc_html(number_format((float) $n['rate'], 1, petgo_site_lang() === 'en' ? '.' : ',', '')) . '</em> <small>(' . esc_html((int) $n['rn'] . ' ' . petgo_site_n((int) $n['rn'], array('отзыв', 'отзыва', 'отзывов'))) . ')</small>' : '<small>' . esc_html($L('Новая няня')) . '</small>') . '</span>';
    $h .= '<span class="pgk-nc-m"><small>' . petgo_cl_ic('walk', 'pg-i') . esc_html((int) $n['done'] . ' ' . petgo_site_n((int) $n['done'], array('заказ', 'заказа', 'заказов'))) . '</small>' . ($price ? '<small class="pgk-nc-p">' . esc_html($L('от')) . ' <b>' . esc_html(petgo_site_money($price)) . ' ₸</b> / ' . esc_html($L('час')) . '</small>' : '') . '</span></span>';
    $h .= '<i class="pgk-nc-fav' . (in_array((int) $n['id'], $fav, true) ? ' on' : '') . '" data-k-fav="' . (int) $n['id'] . '" role="button" aria-label="' . esc_attr($L('В избранное')) . '">' . petgo_cl_ic('heart') . '</i></button>';
    return $h;
}

/* ---------- главная по макету ---------- */
function petgo_cl_home38_top($u, $hi) {
    $L     = 'petgo_site_l';
    $first = explode(' ', trim($u->display_name));
    $h  = '<div class="pgk-top"><span class="pgk-city">' . petgo_cl_icon('pin') . esc_html($L('Алматы')) . '</span><button type="button" class="pg-b pgk-bell" data-k-notifs aria-label="' . esc_attr($L('Уведомления')) . '">' . petgo_cl_ic('bell') . '<i class="pgk-bell-dot" data-k-bell hidden></i></button></div>';
    $h .= '<header class="pgk-hd pgk-hello" data-title="' . esc_attr($first[0]) . '"><h1><span data-k-hello>' . esc_html($L($hi)) . '</span><br>' . esc_html($first[0]) . ' <span class="pgk-wave" aria-hidden="true">👋</span></h1><p>' . esc_html($L('Лучший уход для вашего питомца')) . '</p></header>';
    $h .= '<div class="pgk-search"><button type="button" class="pg-b pgk-sbar" data-k-nannies="search">' . petgo_cl_ic('search') . '<span>' . esc_html($L('Поиск няни…')) . '</span></button><button type="button" class="pg-b pgk-sfilt" data-k-nannies="sort" aria-label="' . esc_attr($L('Сортировка')) . '">' . petgo_cl_ic('sliders') . '</button></div>';
    return $h;
}

function petgo_cl_home38_svcs() {
    $L  = 'petgo_site_l';
    $ic = array('walk' => petgo_cl_ic('paw'), 'visit' => petgo_cl_icon('home'), 'week' => petgo_cl_ic('cal'));
    $h  = '<div class="pgk-svc3">';
    foreach (petgo_cl_book_svcs() as $k => $v) {
        $h .= '<button type="button" class="pg-b pgk-svc" data-k-book="' . esc_attr($k) . '"><i>' . (isset($ic[$k]) ? $ic[$k] : petgo_cl_ic('paw')) . '</i><span>' . esc_html($L($v['t'])) . '</span></button>';
    }
    return $h . '</div>';
}

/* акция (по макету «−50% on your first walk»): скидка на первый заказ или первая акция из «Для вас» */
function petgo_cl_home38_promo($uid) {
    $L   = 'petgo_site_l';
    $c   = petgo_site_cfg();
    $off = (int) $c['off'];
    if ($off > 0 && !petgo_cl_orders($uid, 1)) {
        $big = '−' . $off . '%';
        $sub = $L('на первый выгул или визит');
    } else {
        $p = null;
        foreach (array_reverse(petgo_board()) as $b) {
            if ($b['type'] === 'promo') {
                $p = $b;
                break;
            }
        }
        if (!$p) {
            return '';
        }
        $big = $L($p['title']);
        $sub = $p['text'] !== '' ? $L($p['text']) : '';
    }
    $h  = '<div class="pgk-promo38' . (mb_strlen($big) > 8 ? ' long' : '') . '"><div class="pgk-pr-t"><b>' . esc_html($big) . '</b>' . ($sub !== '' ? '<span>' . esc_html($sub) . '</span>' : '') . '<button type="button" class="pg-b pgk-pr-go" data-k-book="walk">' . esc_html($L('Заказать со скидкой')) . '</button></div>';
    return $h . '<span class="pgk-pr-art" data-k-art="art:dog-gold" aria-hidden="true"></span></div>';
}

function petgo_cl_home38_nannies($uid, $nn) {
    if (!$nn) {
        return '';
    }
    $L     = 'petgo_site_l';
    $pr    = petgo_cl_book_prices($uid);
    $price = isset($pr['walk']) ? (int) $pr['walk'] : 0;
    $fav   = petgo_cl_favs($uid);
    $h     = '<div class="pgk-sec-h"><h2 class="pgk-h2">' . esc_html($L('Наши няни')) . '</h2><button type="button" class="pg-b pgk-see" data-k-nannies="all">' . esc_html($L('Все')) . ' →</button></div><div class="pgk-ncs">';
    foreach (array_slice($nn, 0, 3) as $n) {
        $h .= petgo_cl_nanny_card($n, $fav, $price);
    }
    return $h . '</div>';
}

/* ---------- профиль клиента по макету: шапка, скидка, меню ---------- */
function petgo_cl_profile38($u) {
    $L   = 'petgo_site_l';
    $uid = (int) $u->ID;
    $ph  = petgo_site_phone_fmt((string) get_user_meta($uid, 'petgo_phone', true));
    $h   = '<div class="pgk-me"><label class="pgk-me-av">' . petgo_cl_av(petgo_user_avatar_url($uid), $u->display_name) . '<input type="file" accept="image/*" class="pgw-vh" data-k-avatar><i class="pgk-cam">' . petgo_cl_icon('camera') . '</i></label><div class="pgk-me-t"><b>' . esc_html($u->display_name) . '</b><small>' . esc_html($ph !== '' ? $ph : '@' . $u->user_login) . '</small></div><button type="button" class="pg-b pgk-me-ed" data-k-edit aria-label="' . esc_attr($L('Изменить данные')) . '">' . petgo_cl_ic('pen') . '</button></div>';
    $c   = petgo_site_cfg();
    if ((int) $c['off'] > 0 && !petgo_cl_orders($uid, 1)) {
        $h .= '<div class="pgk-disc"><div><small>' . esc_html($L('Ваша скидка')) . '</small><b>' . esc_html(petgo_site_lf('−%s на первый заказ', (int) $c['off'] . '%')) . '</b><span>' . esc_html($L('Применится сама — код не нужен')) . '</span></div><i>' . petgo_cl_ic('gift') . '</i></div>';
    }
    $row = function ($ic, $t, $attr, $tag = 'button', $right = '') {
        return ($tag === 'a' ? '<a class="pgk-row pgk-mrow" ' . $attr . '>' : '<button type="button" class="pg-b pgk-row pgk-mrow" ' . $attr . '>') . '<span class="pgk-mrow-i">' . $ic . '</span><span class="pgk-row-t">' . esc_html($t) . '</span>' . $right . petgo_cl_icon('right', 'pg-i pgk-chev') . ($tag === 'a' ? '</a>' : '</button>');
    };
    $h .= '<div class="pgk-list pgk-menu">';
    $h .= $row(petgo_cl_ic('paw'), $L('Мои питомцы'), 'data-k-tab="home" data-k-goto="pets"');
    $h .= $row(petgo_cl_ic('cal'), $L('Мои заказы'), 'data-k-tab="walks"');
    $h .= $row(petgo_cl_ic('heart'), $L('Избранные няни'), 'data-k-nannies="fav"');
    $h .= $row(petgo_cl_ic('bell'), $L('Уведомления'), 'data-k-notifs');
    if (function_exists('petgo_ct_ready') && petgo_ct_ready()) {
        $h .= $row(petgo_cl_ic('doc'), $L('Договор'), 'data-k-ct');
    }
    $h .= $row(petgo_cl_ic('help'), $L('Помощь и поддержка'), 'data-k-open="chat"');
    $h .= $row(petgo_cl_ic('info'), $L('О PetGo'), 'href="' . esc_url(petgo_site_url('home')) . '"', 'a');
    return $h . '</div>';
}

/* ============================================================
   ЗАПРОСЫ: избранные няни, уведомления
   ============================================================ */
add_action('petgo_cl_op', function ($op, $uid) {
    if ($op === 'fav') {
        $id  = (int) petgo_p('id');
        $all = wp_list_pluck(petgo_site_nannies(), 'id');
        $fav = petgo_cl_favs($uid);
        if (in_array($id, array_map('intval', $all), true)) {
            /* клиент присылает нужное состояние (а не «переключи») — два быстрых нажатия не путают избранное */
            $fav = array_values(array_diff($fav, array($id)));
            if (petgo_p('on') === '1') {
                $fav[] = $id;
            }
            update_user_meta($uid, 'petgo_fav_nn', $fav);
        }
        petgo_cl_json(true, array('fav' => $fav));
    }
    if ($op === 'notifs') {
        petgo_cl_json(true, array('items' => petgo_cl_notifs($uid, 40)));
    }
}, 10, 2);

/* уведомления — сообщения PetGo клиенту (заказ подтверждён, няня пришла, отчёт, акции) */
function petgo_cl_notifs($uid, $n = 40) {
    global $wpdb;
    $cid = function_exists('petgo_cl_chat') ? petgo_cl_chat($uid, false) : 0;
    if (!$cid) {
        return array();
    }
    $o = array();
    foreach ((array) $wpdb->get_results($wpdb->prepare('SELECT id, kind, body, ts FROM ' . petgo_tb('msgs') . ' WHERE chat_id=%d AND user_id=0 ORDER BY id DESC LIMIT %d', $cid, (int) $n)) as $r) {
        $o[] = array('id' => (int) $r->id, 'k' => (string) $r->kind, 'b' => html_entity_decode(wp_strip_all_tags((string) $r->body), ENT_QUOTES, 'UTF-8'), 'd' => function_exists('petgo_rv_ago') ? petgo_rv_ago((int) $r->ts) : wp_date('d.m H:i', (int) $r->ts, petgo_tz()));
    }
    return $o;
}

function petgo_cl_notif_last($uid) {
    $x = petgo_cl_notifs($uid, 1);
    return $x ? (int) $x[0]['id'] : 0;
}

/* ---------- переводы 38.1 (казахский, английский) и надписи для скрипта ---------- */
function petgo_cl_dict38() {
    return array(
        'Забота. Доверие. Вместе.' => array('Қамқорлық. Сенім. Бірге.', 'Care. Trust. Together.'),
        'Начать' => array('Бастау', 'Get started'),
        'Алматы' => array('Алматы', 'Almaty'),
        'Уведомления' => array('Хабарламалар', 'Notifications'),
        'Лучший уход для вашего питомца' => array('Жануарыңызға ең жақсы күтім', 'The best care for your pet'),
        'Поиск няни…' => array('Күтуші іздеу…', 'Search for pet sitters…'),
        'Сортировка' => array('Сұрыптау', 'Sort'),
        'Наши няни' => array('Біздің күтушілер', 'Our pet sitters'),
        'Все' => array('Барлығы', 'See all'),
        'Новая няня' => array('Жаңа күтуші', 'New sitter'),
        'от' => array('бастап', 'from'),
        'час' => array('сағ', 'hour'),
        'В избранное' => array('Таңдаулыға', 'Add to favorites'),
        'Мои питомцы' => array('Менің жануарларым', 'My pets'),
        'Мои заказы' => array('Менің тапсырыстарым', 'My bookings'),
        'Избранные няни' => array('Таңдаулы күтушілер', 'Favorite sitters'),
        'Избранные' => array('Таңдаулылар', 'Favorites'),
        'Помощь и поддержка' => array('Көмек және қолдау', 'Help & Support'),
        'О PetGo' => array('PetGo туралы', 'About PetGo'),
        'Ваша скидка' => array('Сіздің жеңілдігіңіз', 'Your discount'),
        '−%s на первый заказ' => array('Алғашқы тапсырысқа −%s', '−%s on your first order'),
        'Применится сама — код не нужен' => array('Өзі қолданылады — код керек емес', 'Applied automatically, no code needed'),
        'на первый выгул или визит' => array('алғашқы серуенге немесе келуге', 'on your first walk or visit'),
        'Заказать со скидкой' => array('Жеңілдікпен тапсырыс беру', 'Get the discount'),
        'Изменить данные' => array('Деректерді өзгерту', 'Edit details'),
        'Личные данные' => array('Жеке деректер', 'Personal details'),
        'Рейтинг' => array('Рейтинг', 'Rating'),
        'Опыт' => array('Тәжірибе', 'Experience'),
        'О няне' => array('Күтуші туралы', 'About'),
        'В PetGo с %s года' => array('PetGo-да %s жылдан бері', 'With PetGo since %s'),
        'Проверена PetGo' => array('PetGo тексерген', 'Verified by PetGo'),
        'Заказать выгул или визит' => array('Серуен не келу тапсырысы', 'Book a walk or visit'),
        'Написать' => array('Жазу', 'Message'),
        'Здравствуйте! Хочу заказать няню %s.' => array('Сәлеметсіз бе! %s күтушісіне тапсырыс бергім келеді.', 'Hello! I would like to book %s.'),
        'Пока нет уведомлений' => array('Әзірге хабарлама жоқ', 'No notifications yet'),
        'Здесь появятся подтверждения заказов, приход няни и отчёты о прогулках.' => array('Мұнда тапсырыс растаулары, күтушінің келуі және серуен есептері пайда болады.', 'Booking confirmations, sitter arrivals and walk reports will appear here.'),
        'Больше уведомлений нет' => array('Басқа хабарлама жоқ', 'No more notifications for now'),
        'Нет избранных нянь — нажмите ♡ на карточке няни.' => array('Таңдаулы күтуші жоқ — карточкадағы ♡ басыңыз.', 'No favorites yet. Tap ♡ on a sitter card.'),
        'Никого не нашли' => array('Ешкім табылмады', 'Nobody found'),
        'Добавлено в избранное' => array('Таңдаулыға қосылды', 'Added to favorites'),
        'Убрано из избранного' => array('Таңдаулыдан алынды', 'Removed from favorites'),
    );
}
add_filter('petgo_site_dict_more', function ($d) {
    return array_merge($d, petgo_cl_dict38());
});
add_filter('petgo_cl_cfg', function ($cfg) {
    foreach (array_keys(petgo_cl_dict38()) as $s) {
        $cfg['tr'][$s] = petgo_site_l($s);
    }
    foreach (array('Выгул', 'Визит домой', 'Неделя визитов', 'Наши няни', 'Избранные няни', 'Рейтинг', 'Отзывы', 'Опыт') as $s) {
        $cfg['tr'][$s] = petgo_site_l($s);
    }
    return $cfg;
}, 5);
