<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   39.1 ТЕСТОВАЯ НЕДЕЛЯ
   Владелец или администратор включает её в приложении сотрудников (Настройки → «Тестовая неделя»).
   Пока она идёт, в приложении клиента все услуги стоят 0 ₸ и везде написано «Тестовая неделя»;
   заказы из приложения помечаются «Тестовая неделя — бесплатно» (менеджер и няня видят это в заказе).
   Сайт не меняется: цены, акции и заявки — как обычно. Неделя выключается кнопкой или сама в выбранный день.
   ============================================================ */
function petgo_tw() {
    $o = get_option('petgo_test_week', array());
    $o = is_array($o) ? $o : array();
    return array('on' => !empty($o['on']), 'until' => isset($o['until']) ? (int) $o['until'] : 0, 'from' => isset($o['from']) ? (int) $o['from'] : 0);
}

function petgo_tw_on() {
    static $on = null;
    if ($on === null) {
        $t  = petgo_tw();
        $on = $t['on'] && (!$t['until'] || time() <= $t['until']);
    }
    return $on;
}

/* «до 3 декабря» на языке клиента */
function petgo_tw_until() {
    $t = petgo_tw();
    if (!$t['until']) {
        return '';
    }
    $m = array('января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря');
    $d = (int) wp_date('j', $t['until'], petgo_tz());
    $n = (int) wp_date('n', $t['until'], petgo_tz());
    return petgo_site_lf('до %s', $d . ' ' . petgo_site_l($m[$n - 1]));
}

/* полоса на главной и в профиле приложения; 39.2 — с кнопкой «Оставить отзыв» */
function petgo_tw_ribbon($uid = 0) {
    if (!petgo_tw_on()) {
        return '';
    }
    $L    = 'petgo_site_l';
    $u    = petgo_tw_until();
    $uid  = $uid ? (int) $uid : get_current_user_id();
    $sent = $uid && petgo_tw_fb_mine($uid);
    return '<div class="pgk-tw"><b>' . esc_html($L('Тестовая неделя')) . '</b><span>' . esc_html($L('Все услуги в приложении — 0 ₸') . ($u !== '' ? ' ' . $u : '')) . '</span>'
        . '<p>' . esc_html($L('Помогите нам стать лучше: расскажите, что понравилось и что неудобно.')) . '</p>'
        . '<button type="button" class="pg-b pgk-tw-fb" data-k-open="fb">' . esc_html($L($sent ? 'Дополнить отзыв' : 'Оставить отзыв')) . '</button></div>';
}

/* ---------- 39.2 ОБРАТНАЯ СВЯЗЬ В ТЕСТОВУЮ НЕДЕЛЮ ----------
   Клиент оценивает PetGo (1–5) и пишет, что понравилось и что улучшить. Отзыв приходит менеджеру в чат клиента
   (с уведомлением) и копится в приложении сотрудников: Настройки → «Тестовая неделя». После первого завершённого
   заказа приложение один раз само предлагает оставить отзыв. */
function petgo_tw_fb_all() {
    $a = get_option('petgo_tw_fb', array());
    return is_array($a) ? $a : array();
}
function petgo_tw_fb_mine($uid) {
    return (int) get_user_meta((int) $uid, 'petgo_tw_fb', true);
}
function petgo_tw_fb_ask($uid) {
    if (!$uid || petgo_tw_fb_mine($uid) || get_user_meta($uid, 'petgo_tw_fb_asked', true) || !function_exists('petgo_cl_orders')) {
        return false;
    }
    $t = petgo_tw();
    foreach (petgo_cl_orders($uid, 20) as $o) {
        if (in_array(petgo_o($o->ID, 'status'), array('done', 'confirmed'), true) && (int) petgo_o($o->ID, 'ts') >= $t['from'] - DAY_IN_SECONDS && (int) petgo_o($o->ID, 'ts') <= time()) {
            return true;
        }
    }
    return false;
}
add_filter('petgo_cl_sheets', function ($h, $uid) {
    if (!petgo_tw_on()) {
        return $h;
    }
    $L  = 'petgo_site_l';
    $ck = '<i aria-hidden="true">' . petgo_cl_icon('check') . '</i>';
    $b  = '<form class="pgk-form pgk-rvf pgk-fbf" data-k-fbform novalidate><p class="pgk-fb-lead">' . esc_html($L('Мы только запустились. Расскажите честно, что понравилось и что неудобно — читаем каждый отзыв и отвечаем.')) . '</p><div class="pgk-stars" role="radiogroup" aria-label="' . esc_attr($L('Оценка')) . '">';
    for ($i = 5; $i >= 1; $i--) {
        $b .= '<input type="radio" name="rating" id="pgk-fb' . $i . '" value="' . $i . '"' . ($i === 5 ? ' checked' : '') . '><label for="pgk-fb' . $i . '">' . petgo_cl_icon('star') . '</label>';
    }
    $b .= '</div><div class="pgk-list"><label class="pgk-f pgk-fa"><span>' . esc_html($L('Что понравилось, что улучшить?')) . '</span><textarea name="text" rows="4" maxlength="2000" placeholder="' . esc_attr($L('Например: удобно заказывать, но хочется больше времени для прогулки')) . '"></textarea></label></div>';
    $b .= '<label class="pgk-check pgk-fb-call"><input type="checkbox" name="call" value="1" checked>' . $ck . '<span>' . esc_html($L('Можно позвонить мне, чтобы уточнить')) . '</span></label>';
    $b .= '<p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pgk-btn pgk-btn-p pgk-go"><span>' . esc_html($L('Отправить')) . '</span><i class="pg-spin" aria-hidden="true"></i></button><button type="button" class="pg-b pgk-link" data-k-close>' . esc_html($L('Позже')) . '</button></form>';
    return $h . petgo_cl_sheet('fb', $L('Как вам PetGo?'), $b, $L('Тестовая неделя'));
}, 10, 2);
add_action('petgo_cl_op', function ($op, $uid) {
    if ($op === 'tw_fb_seen') {
        update_user_meta($uid, 'petgo_tw_fb_asked', time());
        petgo_cl_json(true);
    }
    if ($op !== 'tw_fb') {
        return;
    }
    $r    = max(1, min(5, (int) petgo_p('rating')));
    $t    = trim(mb_substr(sanitize_textarea_field((string) petgo_p('text')), 0, 2000));
    $call = petgo_p('call') === '1';
    $u    = get_userdata($uid);
    $all  = petgo_tw_fb_all();
    /* двойное нажатие или повтор на плохой связи — второй раз не записываем */
    if (!$all || (int) $all[0]['uid'] !== (int) $uid || $all[0]['t'] !== $t || time() - (int) $all[0]['ts'] > 30) {
        array_unshift($all, array('uid' => (int) $uid, 'n' => $u ? $u->display_name : '', 'ph' => (string) get_user_meta($uid, 'petgo_phone', true), 'r' => $r, 't' => $t, 'call' => $call ? 1 : 0, 'ts' => time()));
        update_option('petgo_tw_fb', array_slice($all, 0, 500), false);
        update_user_meta($uid, 'petgo_tw_fb', time());
        update_user_meta($uid, 'petgo_tw_fb_asked', time());
        if (function_exists('petgo_cl_say')) {
            petgo_cl_say($uid, 'Отзыв о тестовой неделе: ' . str_repeat('★', $r) . str_repeat('☆', 5 - $r) . ($t !== '' ? "\n" . $t : '') . ($call ? "\nМожно позвонить, чтобы уточнить." : ''));
        }
        petgo_audit('tw_fb', 'r=' . $r, $uid);
    }
    petgo_cl_json(true, array('msg' => petgo_site_l('Спасибо! Мы прочитаем и ответим в чате.')));
}, 10, 2);

/* карточка вместо акции на главной */
function petgo_tw_promo() {
    $L = 'petgo_site_l';
    $u = petgo_tw_until();
    return '<div class="pgk-promo38 pgk-tw-promo"><div class="pgk-pr-t"><b>0 ₸</b><span>' . esc_html($L('Тестовая неделя: выгул и визиты бесплатно') . ($u !== '' ? ' ' . $u : '')) . '</span><button type="button" class="pg-b pgk-pr-go" data-k-book="walk">' . esc_html($L('Заказать бесплатно')) . '</button></div><span class="pgk-pr-art" data-k-art="art:dog-gold" aria-hidden="true"></span></div>';
}

/* данные для скрипта приложения: цены остаются настоящими (для зачёркнутой цены), показывается 0 ₸ */
add_filter('petgo_cl_cfg', function ($cfg, $uid = 0) {
    $cfg['test'] = petgo_tw_on() ? array('until' => petgo_tw_until(), 'fb' => petgo_tw_fb_mine($uid) ? 1 : 0, 'ask' => petgo_tw_fb_ask($uid)) : false;
    foreach (array('Спасибо за отзыв!', 'Дополнить отзыв', 'Тестовая неделя', 'тестовая неделя', 'Заказ в тестовую неделю — бесплатно', 'бесплатно', 'первый заказ', 'Первый заказ — бесплатно', 'Скидка 100% на первый выгул или визит') as $s) {
        $cfg['tr'][$s] = petgo_site_l($s);
    }
    return $cfg;
}, 20, 2);

/* ---------- приложение сотрудников: Настройки → «Тестовая неделя» ---------- */
add_filter('petgo_settings_rows', function ($rows) {
    if (function_exists('petgo_can') && petgo_can('admin')) {
        $t = petgo_tw();
        $rows[] = array('icon' => 'gear', 'title' => 'Тестовая неделя', 'sub' => petgo_tw_on() ? 'Включена' . ($t['until'] ? ' до ' . wp_date('d.m', $t['until'], petgo_tz()) : '') . ' · в приложении клиентов всё 0 ₸' . (($n = count(petgo_tw_fb_all())) ? ' · отзывов: ' . $n : '') : 'Выключена', 'url' => petgo_page_url(array('pg' => 'testweek')));
    }
    return $rows;
});
add_filter('petgo_app_title', function ($t, $pg) {
    return $pg === 'testweek' ? 'Тест-неделя' : $t;
}, 10, 2);
add_filter('petgo_app_page', function ($done, $pg) {
    if ($done || $pg !== 'testweek') {
        return $done;
    }
    if (!petgo_can('admin')) {
        echo '<p class="mu">' . esc_html(petgo_t('err_perm')) . '</p>';
        return true;
    }
    $t   = petgo_tw();
    $on  = petgo_tw_on();
    $def = $t['until'] && $t['until'] > time() ? $t['until'] : time() + 7 * DAY_IN_SECONDS;
    echo '<style>.pgtw .pgc-card{display:flex;flex-direction:column;gap:10px;margin-bottom:12px}.pgtw .pgc-card>b{font-size:17px}.pgtw-st{display:inline-flex;align-self:flex-start;padding:4px 12px;border-radius:999px;font-weight:700;font-size:14px}.pgtw-st.on{background:#1E9A5F;color:#fff}.pgtw-st.off{background:var(--so);color:var(--mu)}.pgtw ul{margin:0;padding-left:18px;color:var(--mu);font-size:14px;line-height:1.5}.pgtw input[type=date]{border:0;background:none;font:inherit;color:inherit;text-align:right}</style>';
    echo petgo_form_open('testweek', 'class="pgtw"');
    echo '<div class="pgc-card"><b>Тестовая неделя</b><span class="pgtw-st ' . ($on ? 'on' : 'off') . '">' . ($on ? 'Включена' . ($t['until'] ? ' до ' . esc_html(wp_date('d.m.Y', $t['until'], petgo_tz())) : '') : 'Выключена') . '</span>';
    echo '<ul><li>В приложении клиента все услуги стоят 0 ₸, на главной и в заказе написано «Тестовая неделя».</li><li>Заказы из приложения помечаются «Тестовая неделя — бесплатно»: менеджер и няня видят это в заказе.</li><li>Сайт не меняется: цены и заявки как обычно.</li><li>В выбранный день неделя выключится сама.</li></ul>';
    echo '<label class="pgc-ck"><input type="checkbox" name="on" value="1"' . checked($on, true, false) . '> <span>Тестовая неделя включена</span></label>';
    echo '<div class="pgf-list"><label class="pgf-row"><span>Последний день</span><input type="date" name="until" value="' . esc_attr(wp_date('Y-m-d', $def, petgo_tz())) . '"></label></div></div>';
    echo '<div style="position:sticky;bottom:calc(var(--sab) + 12px);z-index:5"><button type="submit" class="pgc-btn wide lg">Сохранить</button></div></form>';
    /* 39.2: отзывы клиентов о тестовой неделе */
    $fb = petgo_tw_fb_all();
    echo '<style>.pgtw-fb{display:flex;flex-direction:column;gap:10px;margin-top:18px}.pgtw-fb h3{margin:0;font-size:20px}.pgtw-avg{display:flex;align-items:baseline;gap:8px;color:var(--mu)}.pgtw-avg b{font-size:28px;color:var(--tx)}.pgtw-it{display:flex;flex-direction:column;gap:6px}.pgtw-it-h{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.pgtw-st{color:#E8A33C;letter-spacing:1px;font-size:16px}.pgtw-it p{margin:0;white-space:pre-wrap;line-height:1.45}.pgtw-it small{color:var(--mu)}.pgtw-call{padding:2px 8px;border-radius:999px;background:rgba(30,154,95,.14);color:#1E9A5F;font-size:12px;font-weight:700}.pgtw-it-a{display:flex;gap:14px;font-size:15px}</style>';
    echo '<section class="pgtw-fb"><h3>Отзывы клиентов</h3>';
    if (!$fb) {
        echo '<p class="mu">Пока нет. Клиенты видят кнопку «Оставить отзыв» на главной и в профиле, а после первого завершённого заказа приложение само предложит оценить PetGo.</p>';
    } else {
        $sum = 0;
        foreach ($fb as $x) {
            $sum += (int) $x['r'];
        }
        echo '<div class="pgtw-avg"><b>' . esc_html(number_format($sum / count($fb), 1, ',', '')) . '</b><span>средняя оценка · ' . count($fb) . ' ' . esc_html(petgo_site_n(count($fb), array('отзыв', 'отзыва', 'отзывов'))) . '</span></div>';
        foreach (array_slice($fb, 0, 100) as $x) {
            $cid = function_exists('petgo_cl_chat') ? petgo_cl_chat((int) $x['uid'], false) : 0;
            $tel = preg_replace('/\D/', '', (string) $x['ph']);
            echo '<div class="pgc-card pgtw-it"><div class="pgtw-it-h"><span class="pgtw-st">' . str_repeat('★', (int) $x['r']) . '<span style="opacity:.3">' . str_repeat('★', 5 - (int) $x['r']) . '</span></span><b>' . esc_html($x['n']) . '</b>' . (!empty($x['call']) ? '<span class="pgtw-call">Можно позвонить</span>' : '') . '</div>';
            echo $x['t'] !== '' ? '<p>' . esc_html($x['t']) . '</p>' : '';
            echo '<small>' . esc_html(wp_date('d.m.Y H:i', (int) $x['ts'], petgo_tz())) . '</small><div class="pgtw-it-a">' . ($tel !== '' ? '<a href="tel:+' . esc_attr($tel) . '">Позвонить</a>' : '') . ($cid ? '<a href="' . esc_url(petgo_page_url(array('c' => $cid))) . '">Ответить в чате</a>' : '') . '</div></div>';
        }
    }
    echo '</section>';
    return true;
}, 10, 2);
add_action('admin_post_petgo_testweek', function () {
    petgo_guard('admin');
    $on    = !empty($_POST['on']);
    $d     = isset($_POST['until']) ? sanitize_text_field(wp_unslash($_POST['until'])) : '';
    $until = preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? (int) petgo_ts($d, '23:59') : 0;
    $old   = petgo_tw();
    update_option('petgo_test_week', array('on' => $on ? 1 : 0, 'until' => $until, 'from' => $on && !$old['on'] ? time() : $old['from']), false);
    petgo_audit('test_week', $on ? 'on until ' . $d : 'off');
    petgo_flash($on ? 'Тестовая неделя включена' . ($until ? ' до ' . wp_date('d.m', $until, petgo_tz()) : '') : 'Тестовая неделя выключена', true);
    wp_safe_redirect(petgo_page_url(array('pg' => 'testweek')));
    exit;
});
/* главная приложения сотрудников: напоминание, пока неделя идёт */
add_filter('petgo_admin_home_top', function ($h) {
    if (!petgo_tw_on() || !function_exists('petgo_can') || !petgo_can('office')) {
        return $h;
    }
    $t = petgo_tw();
    return $h . '<a class="pgc-card" href="' . esc_url(petgo_page_url(array('pg' => 'testweek'))) . '" style="display:flex;align-items:center;gap:12px;margin:0 0 14px;padding:14px 16px;text-decoration:none;color:inherit;box-shadow:inset 0 0 0 1.5px #1E9A5F"><span style="flex:none;padding:4px 10px;border-radius:999px;background:#1E9A5F;color:#fff;font-weight:700;font-size:13px">0 ₸</span><span style="flex:1;min-width:0"><b>Тестовая неделя' . ($t['until'] ? ' до ' . esc_html(wp_date('d.m', $t['until'], petgo_tz())) : '') . '</b><br><small class="mu">Заказы из приложения клиентов — бесплатно' . (($n = count(petgo_tw_fb_all())) ? ' · отзывов: ' . $n : '') . '</small></span></a>';
});
/* заказ, сделанный в тестовую неделю, — отметка в карточке заказа */
add_filter('petgo_order_detail_top', function ($h, $oid) {
    /* 39.2: первый заказ со скидкой 100% */
    if (get_post_meta($oid, '_free1', true) === '1') {
        $h = '<div class="pgc-card" style="margin-bottom:12px;box-shadow:inset 0 0 0 1.5px #1E9A5F"><b style="color:#1E9A5F">Первый заказ — бесплатно (скидка 100%)</b><p class="mu" style="margin:4px 0 0">Первый заказ клиента. Оплату не брать.</p></div>' . $h;
    }
    if (get_post_meta($oid, '_test', true) !== '1') {
        return $h;
    }
    return '<div class="pgc-card" style="margin-bottom:12px;box-shadow:inset 0 0 0 1.5px #1E9A5F"><b style="color:#1E9A5F">Тестовая неделя — бесплатно (0 ₸)</b><p class="mu" style="margin:4px 0 0">Клиент заказал в тестовую неделю. Оплату не брать.</p></div>' . $h;
}, 5, 2);

add_filter('petgo_site_dict_more', function ($d) {
    return array_merge($d, array(
        'Тестовая неделя' => array('Сынақ аптасы', 'Trial week'),
        'тестовая неделя' => array('сынақ аптасы', 'trial week'),
        'Все услуги в приложении — 0 ₸' => array('Қосымшадағы барлық қызмет — 0 ₸', 'All services in the app are 0 ₸'),
        'Тестовая неделя: выгул и визиты бесплатно' => array('Сынақ аптасы: серуен мен келу тегін', 'Trial week: walks and visits are free'),
        'Заказать бесплатно' => array('Тегін тапсырыс беру', 'Book for free'),
        'Заказ в тестовую неделю — бесплатно' => array('Сынақ аптасындағы тапсырыс — тегін', 'Bookings during the trial week are free'),
        'бесплатно' => array('тегін', 'free'),
        'до %s' => array('%s дейін', 'until %s'),
        'первый заказ' => array('алғашқы тапсырыс', 'first order'),
        'Помогите нам стать лучше: расскажите, что понравилось и что неудобно.' => array('Жақсаруға көмектесіңіз: не ұнағанын және не ыңғайсыз екенін айтыңыз.', 'Help us improve: tell us what you liked and what was inconvenient.'),
        'Оставить отзыв' => array('Пікір қалдыру', 'Leave feedback'),
        'Дополнить отзыв' => array('Пікірді толықтыру', 'Add to feedback'),
        'Спасибо за отзыв!' => array('Пікіріңізге рахмет!', 'Thanks for your feedback!'),
        'Как вам PetGo?' => array('PetGo ұнады ма?', 'How do you like PetGo?'),
        'Мы только запустились. Расскажите честно, что понравилось и что неудобно — читаем каждый отзыв и отвечаем.' => array('Біз жаңа ғана іске қосылдық. Не ұнағанын және не ыңғайсыз екенін шынын айтыңыз — әр пікірді оқып, жауап береміз.', 'We have just launched. Tell us honestly what you liked and what was inconvenient — we read and answer every message.'),
        'Что понравилось, что улучшить?' => array('Не ұнады, нені жақсарту керек?', 'What did you like, what should we improve?'),
        'Например: удобно заказывать, но хочется больше времени для прогулки' => array('Мысалы: тапсырыс беру ыңғайлы, бірақ серуенге көбірек уақыт керек', 'For example: easy to book, but I would like longer walks'),
        'Можно позвонить мне, чтобы уточнить' => array('Нақтылау үшін маған қоңырау шалуға болады', 'You can call me to clarify'),
        'Отправить' => array('Жіберу', 'Send'),
        'Позже' => array('Кейінірек', 'Later'),
        'Спасибо! Мы прочитаем и ответим в чате.' => array('Рахмет! Оқып, чатта жауап береміз.', 'Thank you! We will read it and reply in the chat.'),
        'Первый заказ — бесплатно' => array('Алғашқы тапсырыс — тегін', 'Your first order is free'),
        'Скидка 100% на первый выгул или визит' => array('Алғашқы серуен немесе келуге 100% жеңілдік', '100% off your first walk or visit'),
        'Первый выгул или визит — бесплатно' => array('Алғашқы серуен немесе келу — тегін', 'First walk or visit is free'),
        'Первый выгул или визит — бесплатно, код не нужен' => array('Алғашқы серуен немесе келу — тегін, код керек емес', 'First walk or visit is free, no code needed'),
    ));
});
