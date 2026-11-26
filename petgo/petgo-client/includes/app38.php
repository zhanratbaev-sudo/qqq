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
    /* 39.0: места нянь — у кого больше хороших отзывов (4–5 звёзд), тот выше; дальше — средняя оценка и опыт */
    $good = array();
    foreach ((array) $wpdb->get_results('SELECT nanny_id, COUNT(*) n FROM ' . petgo_tb('reviews') . " WHERE status='ok' AND rating>=4 GROUP BY nanny_id") as $r) {
        $good[(int) $r->nanny_id] = (int) $r->n;
    }
    foreach (petgo_site_nannies() as $n) {
        $rv = array();
        foreach ((array) $wpdb->get_results($wpdb->prepare('SELECT name, rating, body, ts FROM ' . petgo_tb('reviews') . " WHERE nanny_id=%d AND status='ok' ORDER BY ts DESC LIMIT 3", (int) $n['id'])) as $r) {
            $rv[] = array('n' => (string) $r->name, 'r' => (int) $r->rating, 't' => wp_trim_words((string) $r->body, 40, '…'), 'd' => function_exists('petgo_rv_ago') ? petgo_rv_ago((int) $r->ts) : '');
        }
        $u     = get_userdata((int) $n['id']);
        $out[] = array('id' => (int) $n['id'], 'name' => $n['name'], 'img' => $n['img'], 'rate' => $n['rate'], 'rn' => (int) $n['rn'], 'done' => (int) $n['done'], 'bio' => $n['bio'], 'new' => !empty($n['new']),
            'since' => $u ? (int) wp_date('Y', strtotime((string) $u->user_registered)) : 0, 'rv' => $rv, 'good' => isset($good[(int) $n['id']]) ? $good[(int) $n['id']] : 0);
    }
    usort($out, function ($a, $b) {
        if ($a['good'] !== $b['good']) {
            return $b['good'] - $a['good'];
        }
        if ((float) $a['rate'] !== (float) $b['rate']) {
            return (float) $b['rate'] < (float) $a['rate'] ? -1 : 1;
        }
        return $b['done'] - $a['done'];
    });
    foreach ($out as $i => $n) {
        $out[$i]['place'] = $i + 1;
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
    $pl = isset($n['place']) ? (int) $n['place'] : 0;
    $h = '<button type="button" class="pg-b pgk-nc" data-k-nanny="' . (int) $n['id'] . '"><span class="pgk-nc-av">' . petgo_cl_av($n['img'], $n['name']) . ($pl ? '<i class="pgk-place p' . min(4, $pl) . '" aria-label="' . esc_attr(petgo_site_lf('%s место', $pl)) . '">' . $pl . '</i>' : '') . '</span><span class="pgk-nc-b"><b>' . esc_html($n['name']) . petgo_cl_ic('verified', 'pg-i pgk-ver') . '</b>';
    $h .= '<span class="pgk-nc-r">' . ($n['rate'] ? petgo_cl_icon('star', 'pg-i pgk-star') . '<em>' . esc_html(number_format((float) $n['rate'], 1, petgo_site_lang() === 'en' ? '.' : ',', '')) . '</em> <small>(' . esc_html((int) $n['rn'] . ' ' . petgo_site_n((int) $n['rn'], array('отзыв', 'отзыва', 'отзывов'))) . ')</small>' : '<small>' . esc_html($L('Новая няня')) . '</small>') . '</span>';
    $h .= '<span class="pgk-nc-m"><small>' . petgo_cl_ic('walk', 'pg-i') . esc_html((int) $n['done'] . ' ' . petgo_site_n((int) $n['done'], array('заказ', 'заказа', 'заказов'))) . '</small>' . (function_exists('petgo_tw_on') && petgo_tw_on() ? '<small class="pgk-nc-p"><b>0 ₸</b> · ' . esc_html($L('тестовая неделя')) . '</small>' : ($price ? '<small class="pgk-nc-p">' . esc_html($L('от')) . ' <b>' . esc_html(petgo_site_money($price)) . ' ₸</b> / ' . esc_html($L('час')) . '</small>' : '')) . '</span></span>';
    $h .= '<i class="pgk-nc-fav' . (in_array((int) $n['id'], $fav, true) ? ' on' : '') . '" data-k-fav="' . (int) $n['id'] . '" role="button" aria-label="' . esc_attr($L('В избранное')) . '">' . petgo_cl_ic('heart') . '</i></button>';
    return $h;
}

/* ---------- главная по макету ---------- */
function petgo_cl_home38_top($u, $hi) {
    $L     = 'petgo_site_l';
    $first = explode(' ', trim($u->display_name));
    $h  = '<div class="pgk-top"><span class="pgk-city">' . petgo_cl_icon('pin') . esc_html($L('Алматы')) . '</span><button type="button" class="pg-b pgk-bell" data-k-notifs aria-label="' . esc_attr($L('Уведомления')) . '">' . petgo_cl_ic('bell') . '<i class="pgk-bell-dot" data-k-bell hidden></i></button></div>';
    $h .= '<header class="pgk-hd pgk-hello" data-title="' . esc_attr($first[0]) . '"><h1><span data-k-hello>' . esc_html($L($hi)) . '</span><br>' . esc_html($first[0]) . ' <span class="pgk-wave" aria-hidden="true">👋</span></h1><p>' . esc_html($L('Лучший уход для вашего питомца')) . '</p></header>';
    /* 39.1: тестовая неделя — полоса под приветствием */
    $h .= function_exists('petgo_tw_ribbon') ? petgo_tw_ribbon() : '';
    $h .= '<div class="pgk-search"><button type="button" class="pg-b pgk-sbar" data-k-nannies="search">' . petgo_cl_ic('search') . '<span>' . esc_html($L('Поиск няни…')) . '</span></button><button type="button" class="pg-b pgk-sfilt" data-k-nfilter aria-label="' . esc_attr($L('Сортировка')) . '">' . petgo_cl_ic('sliders') . '</button></div>';
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
    if (function_exists('petgo_tw_on') && petgo_tw_on()) {
        return petgo_tw_promo();
    }
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
    if ((int) $c['off'] > 0 && !petgo_cl_orders($uid, 1) && !(function_exists('petgo_tw_on') && petgo_tw_on())) {
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
    /* 38.2: «О PetGo» — экран внутри приложения, сайт из кабинета не открывается */
    $h .= $row(petgo_cl_ic('info'), $L('О PetGo'), 'data-k-open="about"');
    return $h . '</div>';
}

/* ---------- 38.2 «О PetGo»: о компании, услуги и цены, вопросы, связь — внутри приложения ---------- */
function petgo_cl_about_html() {
    $L   = 'petgo_site_l';
    $c   = petgo_site_cfg();
    $ok  = petgo_cl_icon('check', 'pg-i');
    $h   = '<div class="pgk-about"><div class="pgk-ab-top"><span class="pgk-wl-logo">' . petgo_cl_ic('paw') . '</span><h2>PetGo</h2><p>' . esc_html($L('Забота. Доверие. Вместе.')) . '</p></div>';
    if (!empty($c['slides'][0]['t'])) {
        $h .= '<p class="pgk-ab-lead"><b>' . esc_html(petgo_site_tx($c['slides'][0]['t'])) . '</b>' . (!empty($c['slides'][0]['s']) ? ' ' . esc_html(petgo_site_tx($c['slides'][0]['s'])) : '') . '</p>';
    }
    /* цифры: средняя оценка, отзывы, няни, выполненные заказы */
    $rv  = function_exists('petgo_site_reviews') ? (array) petgo_site_reviews() : array();
    $nn  = petgo_site_nannies();
    $st  = array();
    if ($rv) {
        $avg  = array_sum(wp_list_pluck($rv, 'r')) / count($rv);
        $st[] = array(number_format($avg, 1, petgo_site_lang() === 'en' ? '.' : ',', ''), count($rv) . ' ' . petgo_site_n(count($rv), array('отзыв', 'отзыва', 'отзывов')), 'star');
    }
    if ($nn) {
        $st[] = array((string) count($nn), $L('нянь в команде'), 'paw');
        $done = array_sum(array_map('intval', wp_list_pluck($nn, 'done')));
        if ($done > 0) {
            $st[] = array((string) $done, petgo_site_n($done, array('заказ', 'заказа', 'заказов')) . ' ' . $L('выполнено'), 'check');
        }
    }
    if ($st) {
        $h .= '<div class="pgk-ab-st">';
        foreach ($st as $x) {
            $h .= '<div><b>' . ($x[2] === 'star' ? petgo_cl_icon('star', 'pg-i pgk-star') : '') . esc_html($x[0]) . '</b><small>' . esc_html($x[1]) . '</small></div>';
        }
        $h .= '</div>';
    }
    if (!empty($c['checks'])) {
        $h .= '<h4 class="pgk-gt">' . esc_html($L('Почему PetGo')) . '</h4><div class="pgk-list pgk-ab-ok">';
        foreach ((array) $c['checks'] as $t) {
            $h .= '<div class="pgk-row"><span class="pgk-mrow-i">' . $ok . '</span><span class="pgk-row-t">' . esc_html(petgo_site_tx($t)) . '</span></div>';
        }
        $h .= '</div>';
    }
    if (!empty($c['services'])) {
        $h .= '<h4 class="pgk-gt">' . esc_html($L('Услуги и цены')) . '</h4><div class="pgk-ab-sv">';
        foreach ((array) $c['services'] as $sv) {
            $pr   = petgo_site_price($sv);
            $id   = (string) $sv['id'];
            $kind = strpos($id, 'week') !== false ? 'week' : (strpos($id, 'visit') !== false ? 'visit' : 'walk');
            $pet  = isset($sv['pet']) ? ($sv['pet'] === 'cat' ? $L('Для кошек') : ($sv['pet'] === 'dog' ? $L('Для собак') : '')) : '';
            $h   .= '<div class="pgk-card pgk-ab-s"><div class="pgk-ab-s-h"><b>' . esc_html(petgo_site_tx($sv['title'])) . '</b>' . ($pet !== '' ? '<small>' . esc_html($pet) . '</small>' : '') . '</div>';
            $h   .= !empty($sv['text']) ? '<p>' . esc_html(petgo_site_tx($sv['text'])) . '</p>' : '';
            $h   .= '<div class="pgk-ab-s-f"><span class="pgk-ab-pr">' . (function_exists('petgo_tw_on') && petgo_tw_on() && $pr['now'] ? '<b>0 ₸</b><s>' . esc_html(petgo_site_money($pr['old'] ? $pr['old'] : $pr['now'])) . ' ₸</s><small>' . esc_html($L('Тестовая неделя')) . '</small>' : '') . (!(function_exists('petgo_tw_on') && petgo_tw_on()) && $pr['now'] ? '<b>' . esc_html(petgo_site_money($pr['now'])) . ' ₸</b>' . ($pr['old'] ? '<s>' . esc_html(petgo_site_money($pr['old'])) . ' ₸</s>' : '') . '<small>' . esc_html(petgo_site_tx($sv['unit'])) . '</small>' : '') . '</span>';
            $h   .= '<button type="button" class="pg-b pgk-btn pgk-btn-p pgk-btn-sm" data-k-book="' . esc_attr($kind) . '">' . esc_html($L('Заказать')) . '</button></div></div>';
        }
        $h .= '</div>';
    }
    $faq = function_exists('petgo_site_faq') ? petgo_site_faq($c) : array();
    if ($faq) {
        $h .= '<h4 class="pgk-gt">' . esc_html($L('Частые вопросы')) . '</h4><div class="pgk-list pgk-ab-faq">';
        foreach ($faq as $q) {
            $h .= '<details class="pgk-more-d"><summary class="pgk-row"><span class="pgk-row-t">' . esc_html($q[0]) . '</span>' . petgo_cl_icon('right', 'pg-i pgk-chev') . '</summary><p>' . esc_html($q[1]) . '</p></details>';
        }
        $h .= '</div>';
    }
    $tel = preg_replace('/\D/', '', (string) ($c['phone'] !== '' ? $c['phone'] : $c['wa']));
    $h  .= '<h4 class="pgk-gt">' . esc_html($L('Связаться')) . '</h4><div class="pgk-list">';
    $h  .= '<button type="button" class="pg-b pgk-row pgk-mrow" data-k-open="chat"><span class="pgk-mrow-i">' . petgo_cl_icon('chat') . '</span><span class="pgk-row-t">' . esc_html($L('Написать менеджеру')) . '</span>' . petgo_cl_icon('right', 'pg-i pgk-chev') . '</button>';
    $h  .= '<a class="pgk-row pgk-mrow" data-ext target="_blank" rel="noopener" href="https://wa.me/' . esc_attr(petgo_site_wa()) . '"><span class="pgk-mrow-i">' . petgo_cl_icon('wa') . '</span><span class="pgk-row-t">WhatsApp</span>' . petgo_cl_icon('right', 'pg-i pgk-chev') . '</a>';
    if ($tel !== '') {
        $h .= '<a class="pgk-row pgk-mrow" data-ext href="tel:+' . esc_attr($tel) . '"><span class="pgk-mrow-i">' . petgo_cl_icon('phone') . '</span><span class="pgk-row-t"><b>' . esc_html($L('Позвонить')) . '</b><small>' . esc_html(petgo_site_phone_fmt()) . '</small></span>' . petgo_cl_icon('right', 'pg-i pgk-chev') . '</a>';
    }
    $h .= '</div><p class="pgk-ids">PetGo · ' . esc_html($L('Алматы')) . '</p></div>';
    return $h;
}
add_filter('petgo_cl_sheets', function ($h) {
    return $h . petgo_cl_sheet('about', petgo_site_l('О PetGo'), petgo_cl_about_html(), '', 'pgk-sheet-tall');
}, 5);

/* 38.2: помощник (ИИ) не смог ответить — клиент не остаётся в тишине: сообщение в чате (не чаще раза в 20 минут),
   менеджеру — уведомление. Причина ошибки видна в «PetGo ИИ» → «Проверить каждый ключ». */
function petgo_cl_ai_fail($uid, $cid) {
    if (get_transient('petgo_cl_ai_fail_' . (int) $uid)) {
        return false;
    }
    set_transient('petgo_cl_ai_fail_' . (int) $uid, 1, 20 * MINUTE_IN_SECONDS);
    global $wpdb;
    $tel = petgo_site_phone_fmt();
    $wpdb->insert(petgo_tb('msgs'), array('chat_id' => (int) $cid, 'user_id' => 0, 'subject_id' => 0, 'kind' => 'sys', 'body' => petgo_site_l('Помощник сейчас не может ответить. Ваше сообщение увидит менеджер и ответит здесь.') . ($tel !== '' ? ' ' . petgo_site_l('Срочно — позвоните:') . ' ' . $tel : ''), 'photo' => '', 'ts' => time()), array('%d', '%d', '%d', '%s', '%s', '%s', '%d'));
    if (function_exists('petgo_push_notify') && function_exists('petgo_push_users')) {
        $u = get_userdata($uid);
        petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), 'Клиент ждёт ответа (ИИ не ответил)', ($u ? $u->display_name . ' · ' : '') . petgo_cl_code($uid), petgo_page_url(array('c' => (int) $cid)));
    }
    petgo_bump();
    return true;
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
        '%s место' => array('%s-орын', 'Place %s'),
        'Фильтр' => array('Сүзгі', 'Filter'),
        'Сначала' => array('Алдымен', 'Sort by'),
        'По месту' => array('Орны бойынша', 'Top rated'),
        'По оценке' => array('Баға бойынша', 'Rating'),
        'По отзывам' => array('Пікірлер бойынша', 'Reviews'),
        'По опыту' => array('Тәжірибе бойынша', 'Experience'),
        'Оценка' => array('Баға', 'Rating'),
        'Любая' => array('Кез келген', 'Any'),
        'и выше' => array('және жоғары', 'and up'),
        'Только избранные' => array('Тек таңдаулылар', 'Favorites only'),
        'Только с отзывами' => array('Тек пікірлері барлар', 'With reviews only'),
        'Показать нянь: %s' => array('Күтушілерді көрсету: %s', 'Show sitters: %s'),
        'Сбросить' => array('Тазалау', 'Reset'),
        'Места — по хорошим отзывам: чем их больше, тем выше няня.' => array('Орындар — жақсы пікірлер бойынша: көп болса, күтуші жоғары.', 'Places are based on good reviews: the more, the higher.'),
        'нянь в команде' => array('күтуші командада', 'sitters on the team'),
        'выполнено' => array('орындалды', 'completed'),
        'Почему PetGo' => array('Неге PetGo', 'Why PetGo'),
        'Для собак' => array('Иттерге', 'For dogs'),
        'Для кошек' => array('Мысықтарға', 'For cats'),
        'Связаться' => array('Байланыс', 'Contact us'),
        'Написать менеджеру' => array('Менеджерге жазу', 'Message a manager'),
        'Помощник сейчас не может ответить. Ваше сообщение увидит менеджер и ответит здесь.' => array('Көмекші қазір жауап бере алмайды. Хабарламаңызды менеджер көріп, осында жауап береді.', 'The assistant cannot answer right now. A manager will see your message and reply here.'),
        'Срочно — позвоните:' => array('Шұғыл болса — қоңырау шалыңыз:', 'Urgent? Call:'),
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
