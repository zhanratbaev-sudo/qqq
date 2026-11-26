<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ТЕСТОВЫЙ ПЕРИОД («Тестовая неделя», 39.1 → 39.3)
   Владелец или администратор включает его в приложении сотрудников: Настройки → «Тестовый период».
   Пока он идёт, в приложении клиента на главной и в профиле — отметка с названием периода и кнопка «Оценить PetGo».
   Цены, скидки и акции период НЕ меняет: скидку на первый заказ задают в «Настройках сайта».
   Отзыв клиента (оценка 1–5 и текст) приходит отдельно — не в чат менеджеров: его видит владелец
   (уведомление + список в «Тестовый период»). После первого завершённого заказа приложение один раз само
   предлагает оценить PetGo. Название периода выбирается из готовых (с переводом на казахский и английский).
   Сайт не меняется. Период выключается галочкой или сам в выбранный день.
   ============================================================ */

/* готовые названия: ru, kz, en */
function petgo_tw_names() {
    return array(
        'week'  => array('Тестовая неделя', 'Сынақ аптасы', 'Trial week'),
        'early' => array('Ранний доступ', 'Ерте қолжетімділік', 'Early access'),
        'beta'  => array('Бета-версия', 'Бета-нұсқа', 'Beta'),
        'test'  => array('Тестовый запуск', 'Сынақ кезеңі', 'Test launch'),
        'new'   => array('Мы только открылись', 'Біз жаңа ғана ашылдық', 'We’ve just opened'),
    );
}

function petgo_tw() {
    $o = get_option('petgo_test_week', array());
    $o = is_array($o) ? $o : array();
    $n = isset($o['name']) && isset(petgo_tw_names()[$o['name']]) ? $o['name'] : 'week';
    return array('on' => !empty($o['on']), 'until' => isset($o['until']) ? (int) $o['until'] : 0, 'from' => isset($o['from']) ? (int) $o['from'] : 0, 'name' => $n);
}

function petgo_tw_on() {
    static $on = null;
    if ($on === null) {
        $t  = petgo_tw();
        $on = $t['on'] && (!$t['until'] || time() <= $t['until']);
    }
    return $on;
}

/* название периода на языке клиента ($lang = 'ru' — по-русски для сотрудников) */
function petgo_tw_name($lang = null) {
    $n   = petgo_tw_names()[petgo_tw()['name']];
    $lang = $lang ? $lang : (function_exists('petgo_site_lang') ? petgo_site_lang() : 'ru');
    return $lang === 'kz' ? $n[1] : ($lang === 'en' ? $n[2] : $n[0]);
}

/* полоса на главной и в профиле приложения: название периода и «Оценить PetGo» — без цен и дат */
function petgo_tw_ribbon($uid = 0) {
    if (!petgo_tw_on()) {
        return '';
    }
    $L    = 'petgo_site_l';
    $uid  = $uid ? (int) $uid : get_current_user_id();
    $sent = $uid && petgo_tw_fb_mine($uid);
    return '<div class="pgk-tw"><b>' . esc_html(petgo_tw_name()) . '</b>'
        . '<p>' . esc_html($L('Мы только запустились. Оцените PetGo и подскажите, что улучшить.')) . '</p>'
        . '<button type="button" class="pg-b pgk-tw-fb" data-k-open="fb">' . esc_html($L($sent ? 'Дополнить отзыв' : 'Оценить PetGo')) . '</button></div>';
}

/* ---------- отзыв о приложении ---------- */
function petgo_tw_fb_all() {
    $a = get_option('petgo_tw_fb', array());
    return is_array($a) ? $a : array();
}
function petgo_tw_fb_mine($uid) {
    return (int) get_user_meta((int) $uid, 'petgo_tw_fb', true);
}
/* сам спросит один раз: был завершённый заказ в этот период, клиент ещё не оценивал и его не спрашивали */
function petgo_tw_fb_ask($uid) {
    if (!$uid || petgo_tw_fb_mine($uid) || get_user_meta($uid, 'petgo_tw_fb_asked', true) || !function_exists('petgo_cl_orders')) {
        return false;
    }
    $t = petgo_tw();
    foreach (petgo_cl_orders($uid, 20) as $o) {
        $ts = (int) petgo_o($o->ID, 'ts');
        if (in_array(petgo_o($o->ID, 'status'), array('done', 'confirmed'), true) && $ts >= $t['from'] - DAY_IN_SECONDS && $ts <= time()) {
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
    $b  = '<form class="pgk-form pgk-rvf pgk-fbf" data-k-fbform novalidate><p class="pgk-fb-lead">' . esc_html($L('Мы только запустились. Расскажите честно, что понравилось и что неудобно — мы читаем каждый отзыв.')) . '</p><div class="pgk-stars" role="radiogroup" aria-label="' . esc_attr($L('Оценка')) . '">';
    for ($i = 5; $i >= 1; $i--) {
        $b .= '<input type="radio" name="rating" id="pgk-fb' . $i . '" value="' . $i . '"' . ($i === 5 ? ' checked' : '') . '><label for="pgk-fb' . $i . '">' . petgo_cl_icon('star') . '</label>';
    }
    $b .= '</div><div class="pgk-list"><label class="pgk-f pgk-fa"><span>' . esc_html($L('Что понравилось, что улучшить?')) . '</span><textarea name="text" rows="4" maxlength="2000" placeholder="' . esc_attr($L('Например: удобно заказывать, но хочется больше времени для прогулки')) . '"></textarea></label></div>';
    $b .= '<label class="pgk-check pgk-fb-call"><input type="checkbox" name="call" value="1" checked>' . $ck . '<span>' . esc_html($L('Можно позвонить мне, чтобы уточнить')) . '</span></label>';
    $b .= '<p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pgk-btn pgk-btn-p pgk-go"><span>' . esc_html($L('Отправить')) . '</span><i class="pg-spin" aria-hidden="true"></i></button><button type="button" class="pg-b pgk-link" data-k-close>' . esc_html($L('Позже')) . '</button></form>';
    return $h . petgo_cl_sheet('fb', $L('Как вам PetGo?'), $b, petgo_tw_name());
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
        /* отдельно от чата менеджеров: уведомление только владельцу */
        if (function_exists('petgo_push_notify') && function_exists('petgo_push_users')) {
            petgo_push_notify(petgo_push_users(array('owner')), 'Отзыв о приложении · ' . str_repeat('★', $r) . str_repeat('☆', 5 - $r), ($u ? $u->display_name : '') . ($t !== '' ? ': ' . mb_substr($t, 0, 120) : ''), petgo_page_url(array('pg' => 'testweek')));
        }
        petgo_audit('tw_fb', 'r=' . $r, $uid);
    }
    petgo_cl_json(true, array('msg' => petgo_site_l('Спасибо! Ваш отзыв получен.')));
}, 10, 2);

/* данные для скрипта приложения */
add_filter('petgo_cl_cfg', function ($cfg, $uid = 0) {
    $cfg['test'] = petgo_tw_on() ? array('fb' => petgo_tw_fb_mine($uid) ? 1 : 0, 'ask' => petgo_tw_fb_ask($uid)) : false;
    foreach (array('Спасибо за отзыв!', 'Дополнить отзыв', 'первый заказ', 'Первый заказ — бесплатно', 'Скидка 100% на первый выгул или визит') as $s) {
        $cfg['tr'][$s] = petgo_site_l($s);
    }
    return $cfg;
}, 20, 2);

/* ---------- приложение сотрудников: Настройки → «Тестовый период» ---------- */
function petgo_tw_avg() {
    $fb = petgo_tw_fb_all();
    if (!$fb) {
        return '';
    }
    $s = 0;
    foreach ($fb as $x) {
        $s += (int) $x['r'];
    }
    return number_format($s / count($fb), 1, ',', '');
}
add_filter('petgo_settings_rows', function ($rows) {
    if (function_exists('petgo_can') && petgo_can('admin')) {
        $t = petgo_tw();
        $n = count(petgo_tw_fb_all());
        $rows[] = array('icon' => 'gear', 'title' => 'Тестовый период', 'sub' => (petgo_tw_on() ? '«' . petgo_tw_name('ru') . '»' . ($t['until'] ? ' до ' . wp_date('d.m', $t['until'], petgo_tz()) : '') : 'Выключен') . ($n ? ' · отзывов: ' . $n : ''), 'url' => petgo_page_url(array('pg' => 'testweek')));
    }
    return $rows;
});
add_filter('petgo_app_title', function ($t, $pg) {
    return $pg === 'testweek' ? 'Тест-период' : $t;
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
    echo '<style>.pgtw .pgc-card{display:flex;flex-direction:column;gap:10px;margin-bottom:12px}.pgtw .pgc-card>b{font-size:17px}.pgtw-on{display:inline-flex;align-self:flex-start;padding:4px 12px;border-radius:999px;font-weight:700;font-size:14px}.pgtw-on.on{background:#1E9A5F;color:#fff}.pgtw-on.off{background:var(--so);color:var(--mu)}.pgtw ul{margin:0;padding-left:18px;color:var(--mu);font-size:14px;line-height:1.5}.pgtw input[type=date]{border:0;background:none;font:inherit;color:inherit;text-align:right}.pgtw-nm{display:flex;flex-direction:column;gap:2px}.pgtw-nm label{display:flex;align-items:center;gap:10px;padding:8px 2px}.pgtw-nm small{color:var(--mu)}</style>';
    echo petgo_form_open('testweek', 'class="pgtw"');
    echo '<div class="pgc-card"><b>Тестовый период</b><span class="pgtw-on ' . ($on ? 'on' : 'off') . '">' . ($on ? 'Включён' . ($t['until'] ? ' до ' . esc_html(wp_date('d.m.Y', $t['until'], petgo_tz())) : '') : 'Выключен') . '</span>';
    echo '<ul><li>В приложении клиента на главной и в профиле — отметка с названием периода и кнопка «Оценить PetGo».</li><li>Цены и скидки период не меняет. Скидка на первый заказ — в «Настройках сайта».</li><li>Отзывы приходят только сюда и вам в уведомления — не в чат менеджеров.</li><li>После первого завершённого заказа приложение один раз само попросит оценить PetGo.</li><li>Сайт не меняется. В выбранный день период выключится сам.</li></ul>';
    echo '<label class="pgc-ck"><input type="checkbox" name="on" value="1"' . checked($on, true, false) . '> <span>Тестовый период включён</span></label>';
    echo '<div class="pgf-list"><label class="pgf-row"><span>Последний день</span><input type="date" name="until" value="' . esc_attr(wp_date('Y-m-d', $def, petgo_tz())) . '"></label></div></div>';
    echo '<div class="pgc-card"><b>Как назвать для клиентов</b><div class="pgtw-nm">';
    foreach (petgo_tw_names() as $k => $n) {
        echo '<label><input type="radio" name="name" value="' . esc_attr($k) . '"' . checked($t['name'], $k, false) . '><span>' . esc_html($n[0]) . '<br><small>' . esc_html($n[1] . ' · ' . $n[2]) . '</small></span></label>';
    }
    echo '</div></div>';
    echo '<div style="position:sticky;bottom:calc(var(--sab) + 12px);z-index:5"><button type="submit" class="pgc-btn wide lg">Сохранить</button></div></form>';
    /* отзывы клиентов о приложении */
    $fb = petgo_tw_fb_all();
    echo '<style>.pgtw-fb{display:flex;flex-direction:column;gap:10px;margin-top:18px}.pgtw-fb h3{margin:0;font-size:20px}.pgtw-avg{display:flex;align-items:baseline;gap:8px;color:var(--mu)}.pgtw-avg b{font-size:28px;color:var(--tx)}.pgtw-it{display:flex;flex-direction:column;gap:6px}.pgtw-it-h{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.pgtw-st{color:#E8A33C;letter-spacing:1px;font-size:16px}.pgtw-it p{margin:0;white-space:pre-wrap;line-height:1.45}.pgtw-it small{color:var(--mu)}.pgtw-call{padding:2px 8px;border-radius:999px;background:rgba(30,154,95,.14);color:#1E9A5F;font-size:12px;font-weight:700}.pgtw-it-a{display:flex;gap:14px;font-size:15px}</style>';
    echo '<section class="pgtw-fb"><h3>Отзывы о приложении</h3>';
    if (!$fb) {
        echo '<p class="mu">Пока нет. Клиенты видят кнопку «Оценить PetGo» на главной и в профиле приложения.</p>';
    } else {
        echo '<div class="pgtw-avg"><b>' . esc_html(petgo_tw_avg()) . '</b><span>средняя оценка · ' . count($fb) . ' ' . esc_html(petgo_site_n(count($fb), array('отзыв', 'отзыва', 'отзывов'))) . '</span></div>';
        foreach (array_slice($fb, 0, 100) as $x) {
            $tel = preg_replace('/\D/', '', (string) $x['ph']);
            echo '<div class="pgc-card pgtw-it"><div class="pgtw-it-h"><span class="pgtw-st">' . str_repeat('★', (int) $x['r']) . '<span style="opacity:.3">' . str_repeat('★', 5 - (int) $x['r']) . '</span></span><b>' . esc_html($x['n']) . '</b>' . (!empty($x['call']) ? '<span class="pgtw-call">Можно позвонить</span>' : '') . '</div>';
            echo $x['t'] !== '' ? '<p>' . esc_html($x['t']) . '</p>' : '';
            echo '<small>' . esc_html(wp_date('d.m.Y H:i', (int) $x['ts'], petgo_tz())) . '</small>' . ($tel !== '' ? '<div class="pgtw-it-a"><a href="tel:+' . esc_attr($tel) . '">Позвонить</a></div>' : '') . '</div>';
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
    $name  = isset($_POST['name']) ? sanitize_key(wp_unslash($_POST['name'])) : 'week';
    $name  = isset(petgo_tw_names()[$name]) ? $name : 'week';
    $old   = petgo_tw();
    update_option('petgo_test_week', array('on' => $on ? 1 : 0, 'until' => $until, 'from' => $on && !$old['on'] ? time() : $old['from'], 'name' => $name), false);
    petgo_audit('test_week', ($on ? 'on until ' . $d : 'off') . ' name=' . $name);
    petgo_flash($on ? 'Тестовый период «' . petgo_tw_names()[$name][0] . '» включён' . ($until ? ' до ' . wp_date('d.m', $until, petgo_tz()) : '') : 'Тестовый период выключен', true);
    wp_safe_redirect(petgo_page_url(array('pg' => 'testweek')));
    exit;
});
/* главная приложения сотрудников (владелец и администратор): период идёт — карточка с отзывами */
add_filter('petgo_admin_home_top', function ($h) {
    if (!petgo_tw_on() || !function_exists('petgo_can') || !petgo_can('admin')) {
        return $h;
    }
    $t   = petgo_tw();
    $n   = count(petgo_tw_fb_all());
    $avg = petgo_tw_avg();
    return $h . '<a class="pgc-card" href="' . esc_url(petgo_page_url(array('pg' => 'testweek'))) . '" style="display:flex;align-items:center;gap:12px;margin:0 0 14px;padding:14px 16px;text-decoration:none;color:inherit;box-shadow:inset 0 0 0 1.5px #1E9A5F"><span style="flex:none;padding:4px 10px;border-radius:999px;background:#1E9A5F;color:#fff;font-weight:700;font-size:13px">' . ($avg !== '' ? '★ ' . esc_html($avg) : 'Тест') . '</span><span style="flex:1;min-width:0"><b>' . esc_html(petgo_tw_name('ru')) . ($t['until'] ? ' до ' . esc_html(wp_date('d.m', $t['until'], petgo_tz())) : '') . '</b><br><small class="mu">' . ($n ? 'Отзывов о приложении: ' . $n : 'Отзывов пока нет') . '</small></span></a>';
});
/* заказы: отметка в карточке заказа */
add_filter('petgo_order_detail_top', function ($h, $oid) {
    /* 39.2: первый заказ со скидкой 100% */
    if (get_post_meta($oid, '_free1', true) === '1') {
        $h = '<div class="pgc-card" style="margin-bottom:12px;box-shadow:inset 0 0 0 1.5px #1E9A5F"><b style="color:#1E9A5F">Первый заказ — бесплатно (скидка 100%)</b><p class="mu" style="margin:4px 0 0">Первый заказ клиента. Оплату не брать.</p></div>' . $h;
    }
    return $h;
}, 5, 2);

add_filter('petgo_site_dict_more', function ($d) {
    return array_merge($d, array(
        'Мы только запустились. Оцените PetGo и подскажите, что улучшить.' => array('Біз жаңа ғана іске қосылдық. PetGo-ны бағалап, нені жақсарту керегін айтыңыз.', 'We have just launched. Rate PetGo and tell us what to improve.'),
        'Оценить PetGo' => array('PetGo-ны бағалау', 'Rate PetGo'),
        'Дополнить отзыв' => array('Пікірді толықтыру', 'Add to feedback'),
        'Спасибо за отзыв!' => array('Пікіріңізге рахмет!', 'Thanks for your feedback!'),
        'Как вам PetGo?' => array('PetGo ұнады ма?', 'How do you like PetGo?'),
        'Мы только запустились. Расскажите честно, что понравилось и что неудобно — мы читаем каждый отзыв.' => array('Біз жаңа ғана іске қосылдық. Не ұнағанын және не ыңғайсыз екенін шынын айтыңыз — әр пікірді оқимыз.', 'We have just launched. Tell us honestly what you liked and what was inconvenient — we read every message.'),
        'Что понравилось, что улучшить?' => array('Не ұнады, нені жақсарту керек?', 'What did you like, what should we improve?'),
        'Например: удобно заказывать, но хочется больше времени для прогулки' => array('Мысалы: тапсырыс беру ыңғайлы, бірақ серуенге көбірек уақыт керек', 'For example: easy to book, but I would like longer walks'),
        'Можно позвонить мне, чтобы уточнить' => array('Нақтылау үшін маған қоңырау шалуға болады', 'You can call me to clarify'),
        'Отправить' => array('Жіберу', 'Send'),
        'Позже' => array('Кейінірек', 'Later'),
        'Спасибо! Ваш отзыв получен.' => array('Рахмет! Пікіріңіз қабылданды.', 'Thank you! We have received your feedback.'),
        'первый заказ' => array('алғашқы тапсырыс', 'first order'),
        'Первый заказ — бесплатно' => array('Алғашқы тапсырыс — тегін', 'Your first order is free'),
        'Скидка 100% на первый выгул или визит' => array('Алғашқы серуен немесе келуге 100% жеңілдік', '100% off your first walk or visit'),
        'Первый выгул или визит — бесплатно' => array('Алғашқы серуен немесе келу — тегін', 'First walk or visit is free'),
        'Первый выгул или визит — бесплатно, код не нужен' => array('Алғашқы серуен немесе келу — тегін, код керек емес', 'First walk or visit is free, no code needed'),
    ));
});
