<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   КАБИНЕТ КЛИЕНТА — разметка (34.3)
   Стиль iPhone в цветах PetGo: крупные заголовки, сгруппированные списки, нижняя панель вкладок на всю ширину,
   плотные блоки без размытия. Светлая и тёмная тема. Оформление — assets/client.css, поведение — assets/client.js.
   ============================================================ */

function petgo_cl_sheet($id, $title, $body, $sub = '', $cls = '') {
    return '<div class="pgk-sheet' . ($cls !== '' ? ' ' . esc_attr($cls) : '') . '" id="pgk-' . esc_attr($id) . '" hidden role="dialog" aria-modal="true" aria-label="' . esc_attr($title) . '"><div class="pgk-sbg" data-k-close></div><div class="pgk-sin"><div class="pgk-sh" data-k-drag><i class="pgk-grab" aria-hidden="true"></i><div class="pgk-sht"><h3 data-k-sht>' . esc_html($title) . '</h3>' . ($sub !== '' ? '<p>' . esc_html($sub) . '</p>' : '') . '</div><button type="button" class="pg-b pgk-x" data-k-close aria-label="' . esc_attr(petgo_site_l('Закрыть')) . '">' . petgo_cl_icon('x') . '</button></div><div class="pgk-sc">' . $body . '</div></div></div>';
}

function petgo_cl_field($name, $label, $type = 'text', $val = '', $attr = '') {
    return '<label class="pgk-f"><span>' . esc_html($label) . '</span><input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '"' . $attr . '></label>';
}

function petgo_cl_av($url, $name, $cls = '') {
    $c = 'pgk-av' . ($cls !== '' ? ' ' . $cls : '');
    if ($url) {
        return '<span class="' . esc_attr($c) . '"><img src="' . esc_url($url) . '" alt="" loading="lazy" decoding="async"></span>';
    }
    return '<span class="' . esc_attr($c) . '"><b>' . esc_html(mb_strtoupper(mb_substr(trim($name) !== '' ? trim($name) : '?', 0, 1))) . '</b></span>';
}

function petgo_cl_month($ts) {
    $m  = (int) wp_date('n', $ts, petgo_tz());
    $ru = array(1 => 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь');
    return petgo_site_l($ru[$m]) . ' ' . wp_date('Y', $ts, petgo_tz());
}

/* строка списка (как в «Настройках» iPhone) */
function petgo_cl_row($icon, $text, $attrs, $tag = 'button', $right = '') {
    $open = $tag === 'a' ? '<a class="pgk-row" ' . $attrs . '>' : '<button type="button" class="pg-b pgk-row" ' . $attrs . '>';
    return $open . '<span class="pgk-row-i">' . petgo_cp_icon($icon) . '</span><span class="pgk-row-t">' . esc_html($text) . '</span>' . $right . petgo_cl_icon('right', 'pg-i pgk-chev') . ($tag === 'a' ? '</a>' : '</button>');
}

/* ============================================================
   ГОСТЬ: вход, регистрация, «забыли пароль»
   ============================================================ */
function petgo_cl_guest() {
    $L   = 'petgo_site_l';
    $hp  = '<div class="pg-hp" aria-hidden="true"><input type="text" name="pgr_web" tabindex="-1" autocomplete="off"></div><input type="hidden" name="lang" value="' . esc_attr(petgo_site_lang()) . '">';
    $eye = '<button type="button" class="pg-b pgk-eye" data-eye aria-label="' . esc_attr($L('Показать пароль')) . '">' . petgo_cl_icon('eye') . '</button>';
    $h   = '<section class="pg-sec pg-sec-tight"><div class="pg-wrap"><div class="pgk-auth">';
    $h  .= '<div class="pgk-sg pgk-auth-sg" role="tablist"><i class="pgk-sgi" aria-hidden="true"></i><button type="button" class="pg-b on" data-auth="in" role="tab" aria-selected="true">' . esc_html($L('Вход')) . '</button><button type="button" class="pg-b" data-auth="up" role="tab" aria-selected="false">' . esc_html($L('Регистрация')) . '</button></div>';
    $h  .= '<div class="pgk-auth-pages">';
    $h  .= '<form class="pgk-form pgk-auth-p on" data-auth-p="in" data-op="petgo_cl_login" novalidate><div class="pgk-auth-in">' . $hp . '<div class="pgk-list">' . petgo_cl_field('login', $L('Логин'), 'text', '', ' autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="' . esc_attr($L('или номер телефона')) . '" required') . '<label class="pgk-f"><span>' . esc_html($L('Пароль')) . '</span><input type="password" name="pass" autocomplete="current-password" required>' . $eye . '</label></div>';
    $h  .= '<p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pgk-btn pgk-btn-p pgk-go"><span>' . esc_html($L('Войти')) . '</span><i class="pg-spin" aria-hidden="true"></i></button><button type="button" class="pg-b pgk-link" data-k-open="forgot">' . esc_html($L('Забыли логин или пароль?')) . '</button></div></form>';
    $h  .= '<form class="pgk-form pgk-auth-p" data-auth-p="up" data-op="petgo_cl_reg" novalidate inert><div class="pgk-auth-in">' . $hp . '<div class="pgk-list">' . petgo_cl_field('name', $L('Имя'), 'text', '', ' autocomplete="name" maxlength="60" required') . petgo_cl_field('phone', $L('Телефон'), 'tel', '', ' autocomplete="tel" inputmode="tel" maxlength="20" placeholder="+7 700 000 00 00" required') . '</div>';
    $h  .= '<div class="pgk-list">' . petgo_cl_field('login', $L('Логин'), 'text', '', ' autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="30" placeholder="' . esc_attr($L('латиницей, например aigerim')) . '" required') . '<label class="pgk-f"><span>' . esc_html($L('Пароль')) . '</span><input type="password" name="pass" autocomplete="new-password" minlength="8" placeholder="' . esc_attr($L('не короче 8 символов')) . '" required>' . $eye . '</label></div>';
    $h  .= '<label class="pgk-check"><input type="checkbox" name="consent" value="1"><i aria-hidden="true">' . petgo_cl_icon('check') . '</i><span>' . esc_html($L('Согласен на обработку персональных данных')) . '</span></label>';
    $h  .= '<p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pgk-btn pgk-btn-p pgk-go"><span>' . esc_html($L('Создать кабинет')) . '</span><i class="pg-spin" aria-hidden="true"></i></button><p class="pgk-note">' . esc_html($L('Питомцы, которых мы уже знаем по вашему номеру, появятся в кабинете сами.')) . '</p></div></form>';
    $h  .= '</div></div>';
    $h  .= '<div class="pgk-list pgk-auth-alt">' . petgo_cl_row('paw', $L('Зарегистрировать питомца без аккаунта'), 'data-sheet="pet"') . petgo_cl_row('key', $L('Вход для сотрудников'), 'data-ext href="' . esc_url(petgo_page_url()) . '"', 'a') . '</div>';
    $h  .= '</div></section>';
    $f   = '<form class="pgk-form" data-op="petgo_cl_forgot" novalidate>' . $hp . '<p class="pgk-note pgk-note-l">' . esc_html($L('Пароль восстанавливает менеджер: оставьте номер — мы проверим, что это вы, и пришлём новый пароль.')) . '</p><div class="pgk-list">' . petgo_cl_field('phone', $L('Телефон'), 'tel', '', ' inputmode="tel" maxlength="20" placeholder="+7 700 000 00 00" required') . petgo_cl_field('who', $L('Имя'), 'text', '', ' maxlength="80" placeholder="' . esc_attr($L('или логин')) . '"') . '</div>';
    $f  .= '<p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pgk-btn pgk-btn-p pgk-go"><span>' . esc_html($L('Написать менеджеру')) . '</span><i class="pg-spin" aria-hidden="true"></i></button></form>';
    $f  .= '<div class="pgk-done" hidden><span class="pgk-done-i">' . petgo_cl_icon('check') . '</span><b>' . esc_html($L('Менеджер уже знает')) . '</b><p>' . esc_html($L('Скоро свяжемся с вами. Можно сразу написать нам в WhatsApp:')) . '</p><a class="pg-b pgk-btn pgk-btn-s" data-wa target="_blank" rel="noopener" href="https://wa.me/' . esc_attr(petgo_site_wa()) . '">' . petgo_cl_icon('chat') . 'WhatsApp</a></div>';
    return $h . petgo_cl_sheet('forgot', $L('Восстановить доступ'), $f);
}

/* ============================================================
   ВОШЕДШИЙ КЛИЕНТ
   ============================================================ */

/* последний завершённый заказ без отзыва — кабинет сам предложит оценить няню */
function petgo_cl_rv_due($uid) {
    foreach (petgo_cl_orders($uid, 10) as $o) {
        $st = petgo_o($o->ID, 'status');
        if (!in_array($st, array('done', 'confirmed'), true)) {
            continue;
        }
        $at = (int) petgo_o($o->ID, 'done_at');
        if ($at && time() - $at > 7 * DAY_IN_SECONDS) {
            break;
        }
        if (petgo_rv_by_order($o->ID) || get_user_meta($uid, 'petgo_rv_skip_' . $o->ID, true)) {
            continue;
        }
        $n = (int) petgo_o($o->ID, 'nanny');
        return array('o' => (int) $o->ID, 'n' => petgo_nanny_label($o->ID), 'av' => $n ? petgo_user_avatar_url($n) : '', 'pet' => petgo_pet_name($o->ID));
    }
    return null;
}

/* ближайшие заказы (ждут подтверждения, подтверждены, идут сейчас) */
function petgo_cl_upcoming($uid, $n = 3) {
    $out = array();
    foreach (array_reverse(petgo_cl_orders($uid, 40)) as $o) {
        $st = petgo_o($o->ID, 'status');
        $ts = (int) petgo_o($o->ID, 'ts');
        $end = $ts + max(30, (int) petgo_o($o->ID, 'dur')) * MINUTE_IN_SECONDS;
        if (in_array($st, array('arrived', 'picked'), true) || ($st === 'assigned' && $end > time() - HOUR_IN_SECONDS) || ($st === 'new' && $ts > time() - 30 * MINUTE_IN_SECONDS)) {
            $out[] = $o;
        }
    }
    usort($out, function ($a, $b) {
        return (int) petgo_o($a->ID, 'ts') - (int) petgo_o($b->ID, 'ts');
    });
    return array_slice($out, 0, $n);
}

function petgo_cl_status_label($st) {
    $m = array('expired' => 'Не подтверждён', 'new' => 'Ждёт подтверждения', 'assigned' => 'Подтверждён', 'arrived' => 'Няня на месте', 'picked' => 'Гуляют сейчас', 'done' => 'Завершён', 'confirmed' => 'Завершён', 'cancelled' => 'Отменён');
    return petgo_site_l(isset($m[$st]) ? $m[$st] : $st);
}

function petgo_cl_svc_label($sv) {
    $m = array('walk' => 'Выгул', 'visit' => 'Визит домой', 'other' => 'Услуга');
    return petgo_site_l(isset($m[$sv]) ? $m[$sv] : 'Услуга');
}

function petgo_cl_dashboard() {
    $uid  = petgo_cl_uid();
    $u    = get_userdata($uid);
    $L    = 'petgo_site_l';
    $pets = petgo_cl_pets($uid);
    $st   = petgo_cl_pets_state($uid);
    $un   = petgo_cl_unread($uid);
    $first = explode(' ', trim($u->display_name));
    $live  = null;
    foreach ($st as $s) {
        if ($s['live']) {
            $live = $s;
            break;
        }
    }
    $pre = array();
    foreach ($pets as $p) {
        $x = array('title' => $p->post_title);
        foreach (petgo_pet_fields() as $k => $f) {
            $x[$k] = (string) get_post_meta($p->ID, '_pf_' . $k, true);
        }
        $pre[(int) $p->ID] = $x;
    }
    $nn = array();
    foreach (petgo_site_nannies() as $n) {
        $nn[] = array('id' => (int) $n['id'], 'name' => $n['name'], 'img' => $n['img'], 'rate' => $n['rate']);
    }
    $svcs = array();
    foreach (petgo_cl_book_svcs() as $k => $v) {
        $svcs[$k] = array('t' => $L($v['t']), 'durs' => $v['durs'], 'dur' => $v['dur'], 'days' => $v['days']);
    }
    $cfg = array(
        'aj' => petgo_rel_url(admin_url('admin-ajax.php')), 'n' => wp_create_nonce('petgo_cl'), 'lang' => petgo_site_lang(), 'uid' => $uid,
        'pets' => $st, 'pre' => (object) $pre, 'gkey' => trim((string) get_option('petgo_gmaps_key', '')),
        'leaflet' => array('css' => petgo_asset_url('leaflet/leaflet.css'), 'js' => petgo_asset_url('leaflet/leaflet.js')),
        'nannies' => $nn, 'svcs' => $svcs, 'price' => petgo_cl_book_prices($uid), 'rv' => petgo_cl_rv_due($uid), 'ai' => petgo_cl_ai_on(),
        'tr' => petgo_cl_js_dict(),
    );
    /* 34.5: плагины добавляют свои данные для скриптов кабинета (window.PGK.cfg) */
    $cfg = (array) apply_filters('petgo_cl_cfg', $cfg, $uid);
    $h  = '<div class="pgk" id="pgk" data-tab="home">';
    $h .= '<script type="application/json" id="pgk-cfg">' . wp_json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script><div class="pgk-panes">';

    /* ГЛАВНАЯ */
    $h .= '<section class="pgk-pane on" data-pane="home"><div class="pg-wrap">';
    $h .= '<header class="pgk-hd"><div><small>' . esc_html($L('Здравствуйте,')) . '</small><h1>' . esc_html($first[0]) . '</h1></div><button type="button" class="pg-b pgk-hd-av" data-k-tab="acc" aria-label="' . esc_attr($L('Аккаунт')) . '">' . petgo_cl_av(petgo_user_avatar_url($uid), $u->display_name) . '</button></header>';
    $h .= '<button type="button" class="pg-b pgk-live" data-k-tab="map"' . ($live ? '' : ' hidden') . '><span class="pgk-live-av">' . petgo_cl_av($live ? $live['av'] : '', $live ? $live['name'] : '') . '</span><span class="pgk-live-t"><b data-k-live-name>' . esc_html($live ? $live['name'] : '') . '</b><span data-k-live-text>' . esc_html($live ? $live['text'] : '') . '</span></span><span class="pgk-live-go">' . esc_html($L('На карте')) . petgo_cl_icon('right') . '</span></button>';
    $h .= '<div class="pgk-order"><button type="button" class="pg-b pgk-btn pgk-btn-p pgk-order-main" data-k-book="walk">' . petgo_cl_icon('route') . esc_html($L('Заказать выгул')) . '</button><div class="pgk-order-2"><button type="button" class="pg-b pgk-btn pgk-btn-s" data-k-book="visit">' . esc_html($L('Визит домой')) . '</button><button type="button" class="pg-b pgk-btn pgk-btn-s" data-k-book="week">' . esc_html($L('Неделя визитов')) . '</button></div></div>';
    $h .= '<h2 class="pgk-h2">' . esc_html($L('Питомцы')) . '</h2><div class="pgk-list pgk-pets">';
    foreach ($st as $s) {
        $pid = $s['id'];
        $h  .= '<button type="button" class="pg-b pgk-row pgk-petrow" data-k-petpage="' . $pid . '"><span class="pgk-pet-av' . ($s['live'] ? ' live' : '') . '">' . petgo_cl_av($s['av'], $s['name']) . '</span><span class="pgk-row-t"><b>' . esc_html($s['name']) . '</b><small class="pgk-st st-' . esc_attr($s['st']) . '" data-k-st="' . $pid . '"><i></i><span>' . ($s['hi'] !== '' && $s['live'] ? esc_html($s['hi']) . ' ' : '') . esc_html($s['text']) . '</span></small></span>' . petgo_cl_icon('right', 'pg-i pgk-chev') . '</button>';
        $h  .= '<template id="pgk-pp-' . $pid . '">' . petgo_cl_petpage($pid, $s) . '</template>';
    }
    $h .= '<button type="button" class="pg-b pgk-row pgk-row-add" data-k-pet="0"><span class="pgk-row-i">' . petgo_cl_icon('plus') . '</span><span class="pgk-row-t">' . esc_html($L('Добавить питомца')) . '</span></button></div>';
    /* 34.6: «Ближайшие» обновляются сами (заказ подтвердили, выполнили, отменили) — без перезагрузки */
    $h .= '<div data-k-up>' . petgo_cl_upcoming_html($uid) . '</div>';
    $h .= '</div>';
    /* 34.6: вторая часть главной — «Для вас»: акции, групповые прогулки, блог — на своём тоне фона */
    $feed = petgo_cl_for_html($uid) . (string) apply_filters('petgo_cl_home_html', '', $uid);
    if ($feed !== '') {
        $h .= '<div class="pgk-feed"><div class="pg-wrap"><h2 class="pgk-feed-t">' . esc_html($L('Для вас')) . '</h2>' . $feed . '</div></div>';
    }
    $h .= '</section>';

    /* КАРТА */
    $h .= '<section class="pgk-pane" data-pane="map"><div class="pg-wrap"><header class="pgk-hd"><h1>' . esc_html($L('Карта')) . '</h1></header></div><div class="pgk-mapw"><div class="pgk-map" id="pgk-map"><div class="pgk-map-load"><i class="pg-spin"></i></div></div><div class="pgk-map-ui">';
    if (count($st) > 1) {
        $h .= '<div class="pgk-mchips">';
        foreach ($st as $s) {
            $h .= '<button type="button" class="pg-b pgk-mchip" data-k-mpet="' . (int) $s['id'] . '">' . petgo_cl_av($s['av'], $s['name'], 'xs') . '<span>' . esc_html($s['name']) . '</span></button>';
        }
        $h .= '</div>';
    }
    $h .= '<div class="pgk-sg pgk-mseg"><i class="pgk-sgi" aria-hidden="true"></i><button type="button" class="pg-b on" data-k-mmode="now">' . esc_html($L('Сейчас')) . '</button><button type="button" class="pg-b" data-k-mmode="all">' . esc_html($L('Все места')) . '</button><button type="button" class="pg-b" data-k-mmode="photos">' . esc_html($L('Фото')) . '</button></div></div>';
    $h .= '<div class="pgk-mcard" data-k-mcard hidden></div><button type="button" class="pg-b pgk-mloc" data-k-mfit aria-label="' . esc_attr($L('Показать всё')) . '">' . petgo_cl_icon('locate') . '</button><span class="pgk-mattr" data-k-mattr></span></div></section>';

    /* ПРОГУЛКИ */
    $h .= '<section class="pgk-pane" data-pane="walks"><div class="pg-wrap"><header class="pgk-hd"><h1>' . esc_html($L('Прогулки')) . '</h1><button type="button" class="pg-b pgk-hd-btn" data-k-book="walk">' . petgo_cl_icon('plus') . esc_html($L('Заказать')) . '</button></header><div data-k-walks data-sig="' . esc_attr(petgo_cl_walks_sig($uid)) . '">' . petgo_cl_walks_html($uid) . '</div></div></section>';

    /* АККАУНТ */
    $h .= '<section class="pgk-pane" data-pane="acc"><div class="pg-wrap"><header class="pgk-hd"><h1>' . esc_html($L('Аккаунт')) . '</h1></header>' . petgo_cl_account_html($u, $un) . (string) apply_filters('petgo_cl_account_html', '', $uid) . '</div></section>';
    $h .= '</div>';

    /* нижняя панель вкладок — на всю ширину, как в приложениях iPhone */
    $tab = function ($k, $ic, $t, $on = false, $extra = '') use ($L) {
        return '<button type="button" class="pg-b' . ($on ? ' on' : '') . '" ' . ($k === 'chat' ? 'data-k-open="chat"' : 'data-k-tab="' . $k . '"') . '><i>' . petgo_cp_icon($ic) . $extra . '</i><span>' . esc_html($L($t)) . '</span></button>';
    };
    $h .= '<nav class="pgk-tabbar" aria-label="' . esc_attr($L('Кабинет')) . '">' . $tab('home', 'home', 'Главная', true) . $tab('map', 'map', 'Карта') . $tab('chat', 'chat', 'Менеджер', false, '<em class="pgk-bd" data-k-unread' . ($un ? '' : ' hidden') . '>' . (int) $un . '</em>') . $tab('walks', 'route', 'Прогулки') . $tab('acc', 'user', 'Аккаунт') . '</nav>';

    /* листы */
    $h .= petgo_cl_sheet('petp', '', '<div data-k-pp-body></div>', '', 'pgk-sheet-tall');
    $h .= petgo_cl_sheet('pet', $L('Новый питомец'), '<div data-k-pet-body></div>', $L('Карточки листаются — заполните, что знаете'), 'pgk-sheet-tall');
    $h .= '<template id="pgk-tpl-pet">' . petgo_pf_wizard('client') . '</template>';
    $h .= petgo_cl_sheet('book', $L('Заказ'), petgo_cl_book_form(), '', 'pgk-sheet-tall pgk-sheet-book');
    $chat  = '<div class="pgk-chat"><div class="pgk-msgs" data-k-msgs><div class="pgk-msgs-in" data-k-msgs-in><div class="pgk-chat-hi"><span class="pgk-empty-i">' . petgo_cl_icon('chat') . '</span><b>' . esc_html($L('Менеджер PetGo')) . '</b><p>' . esc_html($cfg['ai'] ? $L('Помощник ответит сразу и может записать на свободное время. Менеджер подключится, если нужно.') : $L('Время прогулки, перенос, оплата, пожелания няне — пишите.')) . '</p></div></div></div>';
    $chat .= '<form class="pgk-cf" data-k-send><label class="pg-b pgk-clip" aria-label="' . esc_attr($L('Фото')) . '"><input type="file" accept="image/*" class="pgw-vh" name="photo">' . petgo_cl_icon('clip') . '</label><textarea name="body" rows="1" maxlength="2000" data-kb-send placeholder="' . esc_attr($L('Сообщение')) . '"></textarea><button type="submit" class="pg-b pgk-send" aria-label="' . esc_attr($L('Отправить')) . '">' . petgo_cl_icon('send') . '</button></form></div>';
    $h .= petgo_cl_sheet('chat', $L('Менеджер'), $chat, 'WhatsApp ' . petgo_site_phone_fmt(petgo_site_wa()), 'pgk-sheet-tall pgk-sheet-chat');
    $rv  = '<form class="pgk-form pgk-rvf" data-k-rvform novalidate><input type="hidden" name="order" value=""><p class="pgk-rv-for" data-k-rv-for></p><div class="pgk-stars" role="radiogroup" aria-label="' . esc_attr($L('Оценка')) . '">';
    for ($i = 5; $i >= 1; $i--) {
        $rv .= '<input type="radio" name="rating" id="pgk-r' . $i . '" value="' . $i . '"' . ($i === 5 ? ' checked' : '') . '><label for="pgk-r' . $i . '">' . petgo_cl_icon('star') . '</label>';
    }
    $rv .= '</div><div class="pgk-list"><label class="pgk-f pgk-fa"><span>' . esc_html($L('Как всё прошло?')) . '</span><textarea name="text" rows="4" maxlength="1500" placeholder="' . esc_attr($L('Что понравилось, что можно сделать лучше')) . '"></textarea></label></div><p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pgk-btn pgk-btn-p pgk-go"><span>' . esc_html($L('Отправить отзыв')) . '</span><i class="pg-spin" aria-hidden="true"></i></button><button type="button" class="pg-b pgk-link" data-k-rvlater>' . esc_html($L('Позже')) . '</button></form>';
    $h .= petgo_cl_sheet('review', $L('Оцените няню'), $rv);
    $h .= petgo_cl_sheet('post', '', '<article class="pgk-post" data-k-post-body></article>', '', 'pgk-sheet-tall');
    /* 34.5: листы других плагинов: petgo_cl_sheet('id', 'Заголовок', $html) — открываются кнопкой data-k-open="id" */
    $h .= (string) apply_filters('petgo_cl_sheets', '', $uid);
    $h .= '<div class="pgk-lb" data-k-lb hidden><button type="button" class="pg-b pgk-x" data-k-lbx aria-label="' . esc_attr($L('Закрыть')) . '">' . petgo_cl_icon('x') . '</button><img alt=""><span></span></div>';
    $h .= '<div class="pgk-toast" data-k-toast role="status" aria-live="polite"></div>';
    return $h . '</div>';
}

/* ближайшие заказы на главной (ждут подтверждения, подтверждены, идут сейчас) */
function petgo_cl_upcoming_html($uid) {
    $L  = 'petgo_site_l';
    $up = petgo_cl_upcoming($uid);
    if (!$up) {
        return '';
    }
    $h = '<h2 class="pgk-h2">' . esc_html($L('Ближайшие')) . '</h2><div class="pgk-list">';
    foreach ($up as $o) {
        $ts  = (int) petgo_o($o->ID, 'ts');
        $ost = petgo_o($o->ID, 'status');
        $h  .= '<button type="button" class="pg-b pgk-row pgk-uprow" data-k-tab="walks" data-k-order="' . (int) $o->ID . '"><span class="pgk-date"><b>' . esc_html(wp_date('d', $ts, petgo_tz())) . '</b><small>' . esc_html(wp_date('H:i', $ts, petgo_tz())) . '</small></span><span class="pgk-row-t"><b>' . esc_html(petgo_pet_name($o->ID) . ' · ' . petgo_cl_svc_label(petgo_o($o->ID, 'service'))) . '</b><small class="pgk-st st-' . esc_attr($ost) . '"><i></i><span>' . esc_html(petgo_cl_status_label($ost) . (petgo_nanny_label($o->ID) !== '' ? ' · ' . petgo_nanny_label($o->ID) : '')) . '</span></small></span>' . petgo_cl_icon('right', 'pg-i pgk-chev') . '</button>';
    }
    return $h . '</div>';
}

/* «отпечаток» прогулок: изменился статус, отзыв, отчёт — кабинет перерисует вкладку «Прогулки» */
function petgo_cl_walks_sig($uid) {
    $x = '';
    foreach (petgo_cl_orders($uid, 60) as $o) {
        $x .= $o->ID . ':' . petgo_o($o->ID, 'status') . ':' . (petgo_rv_by_order($o->ID) ? 1 : 0) . ':' . (get_post_meta($o->ID, '_report', true) ? 1 : 0) . ';';
    }
    return substr(md5($x), 0, 12);
}

/* «Для вас» на главной: рекомендация менеджера, блог, групповые прогулки, акции — только то, что есть */
function petgo_cl_for_html($uid) {
    $L    = 'petgo_site_l';
    $h    = '';
    $my   = trim((string) get_user_meta($uid, 'petgo_cl_rec', true));
    $blog = $groups = $promos = array();
    foreach (array_reverse(petgo_board()) as $b) {
        if ($b['type'] === 'group') {
            $groups[] = $b;
        } elseif ($b['type'] === 'promo') {
            $promos[] = $b;
        } else {
            $blog[] = $b;
        }
    }
    $c = petgo_site_cfg();
    if ((int) $c['off'] > 0 && !petgo_cl_orders($uid, 1)) {
        array_unshift($promos, array('id' => 'first', 'title' => petgo_site_lf('−%s на первый заказ', (int) $c['off'] . '%'), 'text' => $L('Скидка действует на первую прогулку или неделю визитов.'), 'when' => '', 'place' => '', 'photo' => ''));
    }
    if ($my !== '') {
        $h .= '<h2 class="pgk-h2">' . esc_html($L('От менеджера')) . '</h2><div class="pgk-card pgk-rec"><span class="pgk-row-i">' . petgo_cl_icon('chat') . '</span><p>' . nl2br(esc_html($my)) . '</p></div>';
    }
    if ($promos) {
        $h .= '<h2 class="pgk-h2">' . esc_html($L('Акции')) . '</h2><div class="pgk-hscroll' . (count($promos) === 1 ? ' one' : '') . '">';
        foreach ($promos as $b) {
            $h .= '<article class="pgk-card pgk-promo"><span class="pgk-promo-i">' . petgo_cl_icon('tag') . '</span><b>' . esc_html($L($b['title'])) . '</b>' . ($b['text'] !== '' ? '<p>' . esc_html($L($b['text'])) . '</p>' : '') . ($b['when'] !== '' ? '<small>' . esc_html(petgo_site_lf('До %s', $b['when'])) . '</small>' : '') . '<button type="button" class="pg-b pgk-btn pgk-btn-s pgk-btn-sm" data-k-book="walk">' . esc_html($L('Заказать')) . '</button></article>';
        }
        $h .= '</div>';
    }
    if ($groups) {
        $h .= '<h2 class="pgk-h2">' . esc_html($L('Групповые прогулки')) . '</h2><div class="pgk-hscroll' . (count($groups) === 1 ? ' one' : '') . '">';
        foreach ($groups as $b) {
            $joined = (bool) get_user_meta($uid, 'petgo_join_' . $b['id'], true);
            $h .= '<article class="pgk-card pgk-ev">' . ($b['photo'] !== '' ? '<img class="pgk-ev-img" src="' . esc_url(petgo_media($b['photo'])) . '" alt="" loading="lazy">' : '') . '<div class="pgk-ev-b"><b>' . esc_html($L($b['title'])) . '</b>';
            $h .= ($b['when'] !== '' ? '<span>' . petgo_cp_icon('calendar') . esc_html($b['when']) . '</span>' : '') . ($b['place'] !== '' ? '<span>' . petgo_cl_icon('pin') . esc_html($L($b['place'])) . '</span>' : '') . ($b['text'] !== '' ? '<p>' . esc_html($L($b['text'])) . '</p>' : '');
            $h .= '<button type="button" class="pg-b pgk-btn ' . ($joined ? 'pgk-btn-s' : 'pgk-btn-p') . ' pgk-btn-sm pgk-join" data-k-join="' . esc_attr($b['id']) . '"' . ($joined ? ' disabled' : '') . '>' . esc_html($L($joined ? 'Вы записаны' : 'Записаться')) . '</button></div></article>';
        }
        $h .= '</div>';
    }
    if ($blog) {
        $h .= '<h2 class="pgk-h2">' . esc_html($L('Блог')) . '</h2><div class="pgk-blog">';
        foreach ($blog as $b) {
            $img  = $b['photo'] !== '' ? petgo_media($b['photo']) : '';
            $date = $b['t'] ? wp_date('d.m.Y', (int) $b['t'], petgo_tz()) : '';
            $full = ($img ? '<img class="pgk-post-img" src="' . esc_url($img) . '" alt="">' : '') . '<small>' . esc_html($date) . '</small><h3>' . esc_html($L($b['title'])) . '</h3><div class="pgk-post-t">' . nl2br(esc_html($L($b['text']))) . '</div>';
            /* 34.6: запись блога — большая карточка: картинка во всю ширину, заголовок, начало текста */
            $h   .= '<button type="button" class="pg-b pgk-card pgk-bcard" data-k-post="' . esc_attr($b['id']) . '">' . ($img ? '<span class="pgk-bc-img"><img src="' . esc_url($img) . '" alt="" loading="lazy" decoding="async"></span>' : '') . '<span class="pgk-bc-b">' . ($date !== '' ? '<small>' . esc_html($date) . '</small>' : '') . '<b>' . esc_html($L($b['title'])) . '</b><span>' . esc_html(wp_trim_words($L($b['text']), 26, '…')) . '</span><em>' . esc_html($L('Читать')) . petgo_cl_icon('right') . '</em></span><template>' . $full . '</template></button>';
        }
        $h .= '</div>';
    }
    return $h;
}

/* страница питомца: фото, где он, прогулки и отчёты */
function petgo_cl_petpage($pid, $s) {
    $L   = 'petgo_site_l';
    $sp  = $s['sp'] === 'dog' ? $L('Собака') : ($s['sp'] === 'cat' ? $L('Кошка') : (string) get_post_meta($pid, '_pf_species_other', true));
    $sub = array_filter(array($sp, (string) get_post_meta($pid, '_pf_breed', true), (string) get_post_meta($pid, '_pf_age', true)), 'strlen');
    $h   = '<div class="pgk-pp"><label class="pgk-pp-av' . ($s['live'] ? ' live' : '') . '">' . petgo_cl_av($s['av'], $s['name']) . '<input type="file" accept="image/*" class="pgw-vh" data-k-petav="' . (int) $pid . '"><i class="pgk-cam">' . petgo_cl_icon('camera') . '</i></label>';
    $h  .= '<b>' . esc_html($s['name']) . '</b><small>' . esc_html(implode(' · ', $sub)) . '</small></div>';
    $h  .= '<div class="pgk-pp-acts"><button type="button" class="pg-b pgk-btn pgk-btn-p" data-k-book="walk" data-k-bpet="' . (int) $pid . '">' . petgo_cp_icon('calendar') . esc_html($L('Заказать')) . '</button><button type="button" class="pg-b pgk-btn pgk-btn-s" data-k-pet="' . (int) $pid . '">' . petgo_cl_icon('edit') . esc_html($L('Анкета')) . '</button></div>';
    $h  .= '<div class="pgk-list"><button type="button" class="pg-b pgk-row" data-k-tab="map" data-k-focus="' . (int) $pid . '"><span class="pgk-row-i">' . petgo_cl_icon('map') . '</span><span class="pgk-row-t"><b>' . esc_html($L('Где сейчас')) . '</b><small class="pgk-st st-' . esc_attr($s['st']) . '"><i></i><span>' . ($s['hi'] !== '' && $s['live'] ? esc_html($s['hi']) . ' ' : '') . esc_html($s['text']) . '</span></small></span>' . petgo_cl_icon('right', 'pg-i pgk-chev') . '</button></div>';
    $ph = array_reverse(petgo_photos($pid));
    if ($ph) {
        $h .= '<h4 class="pgk-gt">' . esc_html($L('Фото')) . ' · ' . count($ph) . '</h4><div class="pgk-grid">';
        foreach (array_slice($ph, 0, 60) as $x) {
            $u  = petgo_media($x['url']);
            $h .= '<button type="button" class="pg-b" data-k-photo="' . esc_url($u) . '" data-k-cap="' . esc_attr(wp_date('d.m.Y H:i', (int) $x['t'], petgo_tz())) . '"><img src="' . esc_url($u) . '" alt="" loading="lazy" decoding="async"></button>';
        }
        $h .= '</div>';
    }
    $ol = petgo_orders(array('numberposts' => 8, 'meta_key' => '_ts', 'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_query' => array(array('key' => '_pet', 'value' => (int) $pid))));
    if ($ol) {
        $h .= '<h4 class="pgk-gt">' . esc_html($L('Прогулки и визиты')) . '</h4><div class="pgk-walks">';
        foreach ($ol as $o) {
            $h .= petgo_cl_walk_item($o->ID);
        }
        $h .= '</div>';
    }
    return $h . '<p class="pgk-ids">@' . esc_html(petgo_pet_nick($pid)) . ' · ID ' . esc_html(petgo_pet_code($pid)) . '</p>';
}

/* отчёт няни для клиента */
function petgo_rep_html($oid) {
    $r = get_post_meta($oid, '_report', true);
    if (!is_array($r)) {
        return '';
    }
    $L   = 'petgo_site_l';
    $M   = petgo_rep_moods();
    $all = petgo_rep_did(petgo_o($oid, 'service'));
    $h   = '<div class="pgk-rep">';
    if (!empty($r['mood']) && isset($M[(int) $r['mood']])) {
        $h .= '<span class="pgk-rep-m m' . (int) $r['mood'] . '">' . petgo_cp_icon('m' . (int) $r['mood']) . esc_html($L($M[(int) $r['mood']])) . '</span>';
    }
    foreach ((array) $r['did'] as $d) {
        if (isset($all[$d])) {
            $h .= '<span class="pgk-rep-d">' . petgo_cl_icon('check') . esc_html($L($all[$d])) . '</span>';
        }
    }
    $h .= '</div>';
    if (!empty($r['note'])) {
        $h .= '<p class="pgk-rep-n">«' . esc_html($r['note']) . '»</p>';
    }
    return $h;
}

/* одна прогулка (визит) */
function petgo_cl_walk_item($id) {
    $L   = 'petgo_site_l';
    $st  = petgo_o($id, 'status');
    $ts  = (int) petgo_o($id, 'ts');
    /* 34.6: заявку так и не подтвердили, а время прошло — не «ждёт», а «не подтверждён» */
    if ($st === 'new' && $ts < time() - HOUR_IN_SECONDS) {
        $st = 'expired';
    }
    $pid = (int) petgo_o($id, 'pet');
    $nid = (int) petgo_o($id, 'nanny');
    $nn  = petgo_nanny_label($id);
    $dur = max(1, (int) petgo_o($id, 'dur'));
    $h   = '<article class="pgk-card pgk-walk st-' . esc_attr($st) . '"><div class="pgk-walk-h"><span class="pgk-date"><b>' . esc_html(wp_date('d', $ts, petgo_tz())) . '</b><small>' . esc_html(wp_date('H:i', $ts, petgo_tz())) . '</small></span><span class="pgk-row-t"><b>' . esc_html(get_the_title($pid) . ' · ' . petgo_cl_svc_label(petgo_o($id, 'service'))) . '</b><small>' . esc_html($dur . ' ' . $L('мин') . ($nn !== '' ? ' · ' . $nn : '')) . '</small></span><span class="pgk-badge st-' . esc_attr($st) . '">' . esc_html(petgo_cl_status_label($st)) . '</span></div>' . petgo_rep_html($id);
    $ph  = petgo_cl_order_photos($id, $pid);
    $rp  = petgo_o($id, 'rphoto');
    if (!$ph && is_array($rp) && !empty($rp['url'])) {
        $ph = array(array('u' => petgo_media($rp['url']), 't' => ''));
    }
    if ($ph) {
        $h .= '<div class="pgk-strip">';
        foreach (array_slice($ph, 0, 6) as $x) {
            $h .= '<button type="button" class="pg-b" data-k-photo="' . esc_url($x['u']) . '" data-k-cap="' . esc_attr($x['t']) . '"><img src="' . esc_url($x['u']) . '" alt="" loading="lazy" decoding="async"></button>';
        }
        $h .= '</div>';
    }
    $acts = '';
    if (in_array($st, array('done', 'confirmed'), true)) {
        $rv = petgo_rv_by_order($id);
        if ($rv) {
            $h .= '<div class="pgk-myrv">' . str_repeat(petgo_cl_icon('star', 'pg-i on'), (int) $rv->rating) . str_repeat(petgo_cl_icon('star'), 5 - (int) $rv->rating) . '<small>' . esc_html($L('Ваш отзыв')) . '</small></div>';
        } else {
            $acts .= '<button type="button" class="pg-b pgk-btn pgk-btn-p pgk-btn-sm" data-k-review="' . $id . '" data-k-rv-name="' . esc_attr($nn) . '" data-k-rv-av="' . esc_url($nid ? petgo_user_avatar_url($nid) : '') . '">' . petgo_cl_icon('star') . esc_html($L('Оценить няню')) . '</button>';
        }
        if (petgo_trk_points($id)) {
            $acts .= '<button type="button" class="pg-b pgk-btn pgk-btn-s pgk-btn-sm" data-k-tab="map" data-k-walk="' . $id . '">' . petgo_cl_icon('route') . esc_html($L('Маршрут')) . '</button>';
        }
    } elseif (in_array($st, array('arrived', 'picked'), true)) {
        $acts .= '<button type="button" class="pg-b pgk-btn pgk-btn-p pgk-btn-sm" data-k-tab="map" data-k-focus="' . $pid . '">' . petgo_cl_icon('map') . esc_html($L('Смотреть на карте')) . '</button>';
    }
    return $h . ($acts !== '' ? '<div class="pgk-wacts">' . $acts . '</div>' : '') . '</article>';
}

function petgo_cl_walks_html($uid) {
    $L    = 'petgo_site_l';
    $list = petgo_cl_orders($uid, 60);
    if (!$list) {
        return '<div class="pgk-card pgk-empty"><span class="pgk-empty-i">' . petgo_cl_icon('route') . '</span><b>' . esc_html($L('Прогулок ещё не было')) . '</b><p>' . esc_html($L('Здесь появятся время, фото, маршрут, отчёт няни и оценка.')) . '</p><button type="button" class="pg-b pgk-btn pgk-btn-p" data-k-book="walk">' . esc_html($L('Заказать выгул')) . '</button></div>';
    }
    $h   = '<div class="pgk-walks">';
    $mon = '';
    foreach ($list as $o) {
        $m = wp_date('m.Y', (int) petgo_o($o->ID, 'ts'), petgo_tz());
        if ($m !== $mon) {
            $mon = $m;
            $h  .= '<h4 class="pgk-gt">' . esc_html(petgo_cl_month((int) petgo_o($o->ID, 'ts'))) . '</h4>';
        }
        $h .= petgo_cl_walk_item($o->ID);
    }
    return $h . '</div>';
}

/* заказ: каркас; содержимое (питомцы, дни, колёса времени, няни) рисует client.js */
function petgo_cl_book_form() {
    $L  = 'petgo_site_l';
    $g  = function ($title, $inner) {
        return '<div class="pgk-g"><h4 class="pgk-gt">' . esc_html($title) . '</h4>' . $inner . '</div>';
    };
    $h  = '<form class="pgk-bk" data-k-bookform novalidate>';
    $h .= $g($L('Питомец'), '<div class="pgk-bk-pets" data-b="pet"></div>');
    $h .= $g($L('Услуга'), '<div class="pgk-sg" data-b="kind"></div>');
    $h .= $g($L('День'), '<div class="pgk-bk-days" data-b="date"></div>');
    $h .= $g($L('Время'), '<div class="pgk-card pgk-wheel" data-b="time"><i class="pgk-wh-band" aria-hidden="true"></i><div class="pgk-wh-col" data-w="h" tabindex="0" aria-label="' . esc_attr($L('Часы')) . '"></div><span class="pgk-wh-sep">:</span><div class="pgk-wh-col" data-w="m" tabindex="0" aria-label="' . esc_attr($L('Минуты')) . '"></div></div>');
    $h .= $g($L('Длительность'), '<div class="pgk-sg" data-b="dur"></div>');
    $h .= $g($L('Няня'), '<div class="pgk-list" data-b="nanny"></div>');
    $h .= $g($L('Комментарий'), '<div class="pgk-list"><label class="pgk-f pgk-fa"><textarea name="note" rows="2" maxlength="500" placeholder="' . esc_attr($L('Пожелания няне, как попасть и т. п.')) . '"></textarea></label></div>');
    $h .= '<div class="pgk-bk-bar"><div class="pgk-bk-sum" data-b="sum"></div><p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pgk-btn pgk-btn-p pgk-go"><span>' . esc_html($L('Отправить заявку')) . '</span><i class="pg-spin" aria-hidden="true"></i></button></div></form>';
    $h .= '<div class="pgk-done" hidden><span class="pgk-done-i">' . petgo_cl_icon('check') . '</span><b>' . esc_html($L('Заявка отправлена')) . '</b><p>' . esc_html($L('Менеджер подтвердит время или предложит другое — ответ придёт в чат «Менеджер».')) . '</p><button type="button" class="pg-b pgk-btn pgk-btn-p" data-k-bookdone>' . esc_html($L('Готово')) . '</button></div>';
    return $h;
}

/* аккаунт */
function petgo_cl_account_html($u, $un = 0) {
    $L  = 'petgo_site_l';
    $em = function_exists('petgo_real_email') ? petgo_real_email($u) : $u->user_email;
    $h  = '<div class="pgk-list"><label class="pgk-row pgk-me-row"><span class="pgk-prof-av">' . petgo_cl_av(petgo_user_avatar_url($u->ID), $u->display_name) . '<input type="file" accept="image/*" class="pgw-vh" data-k-avatar><i class="pgk-cam">' . petgo_cl_icon('camera') . '</i></span><span class="pgk-row-t"><b>' . esc_html($u->display_name) . '</b><small>@' . esc_html($u->user_login) . ' · ID ' . esc_html(petgo_cl_code($u->ID)) . '</small></span></label></div>';
    $h .= '<div class="pgk-list">' . petgo_cl_row('chat', $L('Сообщения'), 'data-k-open="chat"', 'button', '<em class="pgk-bd pgk-bd-row" data-k-unread' . ($un ? '' : ' hidden') . '>' . (int) $un . '</em>') . petgo_cl_row('calendar', $L('Заказать услугу'), 'data-k-book="walk"') . petgo_cl_row('chat', 'WhatsApp', 'target="_blank" rel="noopener" href="https://wa.me/' . esc_attr(petgo_site_wa()) . '"', 'a') . '</div>';
    $h .= '<h4 class="pgk-gt">' . esc_html($L('Оформление')) . '</h4><div class="pgk-sg pgk-theme" role="radiogroup"><i class="pgk-sgi" aria-hidden="true"></i>';
    foreach (array('auto' => 'Авто', 'light' => 'Светлая', 'dark' => 'Тёмная') as $k => $t) {
        $h .= '<button type="button" class="pg-b" data-k-theme="' . $k . '">' . esc_html($L($t)) . '</button>';
    }
    $h .= '</div>';
    $h .= '<h4 class="pgk-gt">' . esc_html($L('Данные')) . '</h4><form class="pgk-form" data-k-op="profile" novalidate><div class="pgk-list">' . petgo_cl_field('name', $L('Имя'), 'text', $u->display_name, ' maxlength="60" autocomplete="name" required') . petgo_cl_field('phone', $L('Телефон'), 'tel', petgo_site_phone_fmt((string) get_user_meta($u->ID, 'petgo_phone', true)), ' maxlength="20" inputmode="tel" required') . petgo_cl_field('email', $L('Почта'), 'email', $em, ' maxlength="100" placeholder="' . esc_attr($L('необязательно')) . '" autocomplete="email"') . '</div>';
    $h .= '<p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pgk-btn pgk-btn-p pgk-go"><span>' . esc_html($L('Сохранить')) . '</span><i class="pg-spin" aria-hidden="true"></i></button></form>';
    $h .= '<h4 class="pgk-gt">' . esc_html($L('Безопасность')) . '</h4><details class="pgk-list pgk-more-d"><summary class="pgk-row"><span class="pgk-row-i">' . petgo_cl_icon('key') . '</span><span class="pgk-row-t">' . esc_html($L('Сменить пароль')) . '</span>' . petgo_cl_icon('right', 'pg-i pgk-chev') . '</summary><form class="pgk-form pgk-in" data-k-op="pass" novalidate><div class="pgk-list"><label class="pgk-f"><span>' . esc_html($L('Старый')) . '</span><input type="password" name="old" autocomplete="current-password" required></label><label class="pgk-f"><span>' . esc_html($L('Новый')) . '</span><input type="password" name="new" autocomplete="new-password" minlength="8" placeholder="' . esc_attr($L('не короче 8 символов')) . '" required></label></div><p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pgk-btn pgk-btn-s pgk-go"><span>' . esc_html($L('Изменить пароль')) . '</span><i class="pg-spin" aria-hidden="true"></i></button><p class="pgk-note">' . esc_html($L('После смены пароля вход на других телефонах закроется.')) . '</p></form></details>';
    $h .= '<div class="pgk-list">' . petgo_cl_row('home', $L('Сайт PetGo'), 'href="' . esc_url(petgo_site_url('home')) . '"', 'a') . '<a class="pgk-row pgk-red" data-ext href="' . esc_url(wp_logout_url(petgo_site_url('account'))) . '"><span class="pgk-row-i">' . petgo_cl_icon('logout') . '</span><span class="pgk-row-t">' . esc_html($L('Выйти')) . '</span></a></div>';
    $h .= '<details class="pgk-list pgk-more-d"><summary class="pgk-row pgk-red"><span class="pgk-row-i">' . petgo_cp_icon('trash') . '</span><span class="pgk-row-t">' . esc_html($L('Удалить кабинет')) . '</span>' . petgo_cl_icon('right', 'pg-i pgk-chev') . '</summary><form class="pgk-form pgk-in" data-k-op="del_acc" novalidate><p class="pgk-note pgk-note-l">' . esc_html($L('Кабинет и ваши данные входа будут удалены. История заказов останется у нас для отчётности.')) . '</p><div class="pgk-list"><label class="pgk-f"><span>' . esc_html($L('Пароль')) . '</span><input type="password" name="pass" autocomplete="current-password" required></label></div><p class="pgk-err" role="alert" hidden></p><button type="submit" class="pg-b pgk-btn pgk-btn-s pgk-go pgk-red"><span>' . esc_html($L('Удалить навсегда')) . '</span><i class="pg-spin" aria-hidden="true"></i></button></form></details>';
    return $h;
}

function petgo_cl_js_dict() {
    $o = array();
    foreach (array('Сейчас', 'мин', 'ч', 'Последнее место', 'Фото', 'обновлено', 'только что', 'назад', 'Маршрут появится здесь, когда няня начнёт прогулку.', 'Сохранено', 'Не получилось — проверьте интернет.',
        'Новый питомец', 'Анкета питомца', 'Няня', 'Прогулка', 'Прогулки', 'Все места', 'Отправлено', 'Отзыв для няни %s', 'Сохранить', 'Блог', 'ещё', 'Дома',
        'Сегодня', 'Завтра', 'Любая свободная', 'Свободна', 'Занята в это время', 'Выберите питомца.', 'Выберите день и время.', 'Примерно', 'за неделю', 'Цену подтвердит менеджер', 'Помощник PetGo', 'печатает…', 'Оценить няню', 'Менеджер',
        'пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря', 'Нет нянь в списке — подберёт менеджер', 'Вчера', 'дней', 'Вы записаны', 'Ваш отзыв', 'Спасибо за оценку') as $s) {
        $o[$s] = petgo_site_l($s);
    }
    return $o;
}
