<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   САЙТ ДЛЯ КЛИЕНТОВ
   Страницы: Главная, Услуги и цены, Няни, Отзывы, Кабинет. Плагин выводит их сам (см. includes/front.php).
   Всё содержимое — номера, тексты первого экрана, фото и видео, услуги, цены, скидка — меняется в WordPress:
   Настройки → PetGo Сайт (см. includes/site-admin.php).
   ============================================================ */

/* встроенные рисунки-анимации: их можно ставить в слайды наравне с фото и видео */
function petgo_site_arts() {
    return array(
        'art:walk-gold'  => 'Анимация: прогулка, рыжая собака',
        'art:walk-gray'  => 'Анимация: прогулка, серая собака',
        'art:walk-cream' => 'Анимация: прогулка, светлая собака',
        'art:dog-gold'   => 'Анимация: рыжая собака',
        'art:dog-gray'   => 'Анимация: серая собака',
        'art:dog-cream'  => 'Анимация: светлая собака',
        'art:cat-gray'   => 'Анимация: серая кошка',
        'art:cat-orange' => 'Анимация: рыжая кошка',
        'art:cat-dark'   => 'Анимация: тёмная кошка',
    );
}

function petgo_site_defaults() {
    return array(
        'wa'       => '77478175712',
        'phone'    => '',
        'front'    => '1', 'seo' => '', 'head' => '', 'kbd' => '1',
        'off'      => '30',
        'hero_sec' => '5',
        'slides'   => array(
            array('t' => 'Няня для вашего питомца в Алматы', 's' => 'Гуляем с собаками и навещаем собак и кошек дома.'),
            array('t' => 'Первый заказ со скидкой {off}', 's' => 'Выгул собаки — {walk} ₸ за час вместо {walk_old} ₸.'),
            array('t' => 'Фото после каждого визита', 's' => 'Вы видите, как прошла прогулка или визит.'),
            array('t' => 'Уезжаете в отпуск?', 's' => 'Будем приходить к питомцу каждый день: покормим и поиграем.'),
        ),
        'hero_media' => array(array('u' => 'art:walk-gold', 's' => '')),
        'checks'   => array('Обученные няни', 'Фото после каждого визита', 'Приход няни отмечается по геолокации'),
        'services' => array(
            array('id' => 'walk', 'title' => 'Выгул собак', 'text' => 'Активная прогулка с обученной няней. Собака двигается, играет и возвращается довольной.', 'pet' => 'dog', 'tag' => '',
                'list' => array('Безопасная прогулка на поводке', 'Игры по пути', 'Фото и видео с прогулки'), 'price' => '3000', 'unit' => 'за час', 'sale' => '1', 'btn' => 'Заказать выгул',
                'media' => array('art:walk-gold', 'art:walk-gray', 'art:walk-cream')),
            array('id' => 'week-dog', 'title' => 'Ежедневные визиты на неделю', 'text' => 'Приходим к питомцу домой каждый день, чтобы он был сыт и не скучал.', 'pet' => 'dog', 'tag' => 'Для отпуска и поездок',
                'list' => array('Навещаем каждый день', 'Кормим и гуляем с собакой', 'Присылаем фото'), 'price' => '29990', 'unit' => 'за неделю', 'sale' => '1', 'btn' => 'Заказать на неделю',
                'media' => array('art:dog-gold', 'art:dog-gray', 'art:dog-cream')),
            array('id' => 'week-cat', 'title' => 'Ежедневные визиты на неделю', 'text' => 'Приходим к питомцу домой каждый день, чтобы он был сыт и не скучал.', 'pet' => 'cat', 'tag' => 'Для отпуска и поездок',
                'list' => array('Навещаем каждый день', 'Кормим, меняем лоток и играем', 'Присылаем фото'), 'price' => '29990', 'unit' => 'за неделю', 'sale' => '1', 'btn' => 'Заказать на неделю',
                'media' => array('art:cat-gray', 'art:cat-orange', 'art:cat-dark')),
        ),
        'ai_on'    => '1', 'ai_name' => 'Помощник PetGo', 'ai_key' => '', 'ai_model' => 'claude-opus-5-5', 'ai_day' => '500', 'ai_know' => '',
        'faq'      => "Сколько стоит выгул? | Первый заказ — {walk} ₸ за час, дальше {walk_old} ₸ за час.\nКак проходит прогулка? | Няня приходит в назначенное время, отмечает приход по геолокации, гуляет с собакой и присылает фото.\nВы ухаживаете за кошками? | Да. Приходим домой каждый день: кормим, меняем лоток, играем и присылаем фото. Неделя визитов для первого заказа — {week} ₸.\nКак няня попадёт в квартиру? | Договоримся заранее: домофон, код, консьерж или ключи — как удобно вам.\nКак заказать? | Заполните заявку на сайте или напишите нам в WhatsApp. Мы свяжемся с вами и подберём няню.",
    );
}

function petgo_site_num($s) {
    return (int) preg_replace('/\D/', '', (string) $s);
}

/* «3 000»: неразрывный пробел, чтобы цена не разрывалась на две строки */
function petgo_site_money($n) {
    return number_format((int) $n, 0, '', "\u{00A0}");
}

/* ---------- проверка и очистка настроек (то, что пришло из админки или лежит в базе) ---------- */
function petgo_site_media_ok($u) {
    $u = trim((string) $u);
    if (strpos($u, 'art:') === 0) {
        return isset(petgo_site_arts()[$u]) ? $u : '';
    }
    return esc_url_raw($u);
}

function petgo_site_clean($in, $old = array()) {
    $d   = petgo_site_defaults();
    $in  = is_array($in) ? $in : array();
    $txt = function ($v, $max = 200) {
        return mb_substr(sanitize_text_field(is_scalar($v) ? (string) $v : ''), 0, $max);
    };
    $o = array();
    foreach (array('wa', 'phone') as $k) {
        $o[$k] = substr(preg_replace('/\D/', '', isset($in[$k]) && is_scalar($in[$k]) ? (string) $in[$k] : ''), 0, 15);
    }
    foreach (array('front', 'kbd') as $k) {
        $o[$k] = empty($in[$k]) ? '0' : '1';
    }
    $o['seo']      = $txt(isset($in['seo']) ? $in['seo'] : '', 300);
    $o['off']      = (string) min(90, max(0, petgo_site_num(isset($in['off']) ? $in['off'] : 0)));
    $o['hero_sec'] = (string) min(60, max(2, petgo_site_num(isset($in['hero_sec']) ? $in['hero_sec'] : 5)));
    $o['faq']      = mb_substr(sanitize_textarea_field(isset($in['faq']) && is_scalar($in['faq']) ? (string) $in['faq'] : ''), 0, 8000);
    /* старые настройки помощника (до 33.0) не трогаем: плагин «PetGo ИИ» забирает их к себе один раз */
    foreach (array('ai_on', 'ai_name', 'ai_key', 'ai_model', 'ai_day', 'ai_know') as $k) {
        $o[$k] = isset($old[$k]) ? (string) $old[$k] : (string) $d[$k];
    }
    /* код счётчиков задаётся отдельным полем (см. сохранение настроек) */
    $o['head']     = isset($old['head']) ? (string) $old['head'] : '';

    $o['slides'] = array();
    foreach (array_slice(isset($in['slides']) && is_array($in['slides']) ? $in['slides'] : array(), 0, 12) as $s) {
        $t = $txt(is_array($s) && isset($s['t']) ? $s['t'] : '', 120);
        if ($t !== '') {
            $o['slides'][] = array('t' => $t, 's' => $txt(isset($s['s']) ? $s['s'] : '', 200));
        }
    }
    if (!$o['slides']) {
        $o['slides'] = $d['slides'];
    }
    $o['hero_media'] = array();
    foreach (array_slice(isset($in['hero_media']) && is_array($in['hero_media']) ? $in['hero_media'] : array(), 0, 12) as $m) {
        $u = petgo_site_media_ok(is_array($m) && isset($m['u']) ? $m['u'] : '');
        if ($u !== '') {
            $sec = petgo_site_num(isset($m['s']) ? $m['s'] : 0);
            $o['hero_media'][] = array('u' => $u, 's' => $sec > 0 ? (string) min(600, $sec) : '');
        }
    }
    if (!$o['hero_media']) {
        $o['hero_media'] = $d['hero_media'];
    }
    $o['checks'] = array();
    foreach (array_slice(isset($in['checks']) && is_array($in['checks']) ? $in['checks'] : array(), 0, 6) as $c) {
        $c = $txt($c, 80);
        if ($c !== '') {
            $o['checks'][] = $c;
        }
    }
    $o['services'] = array();
    $ids = array();
    foreach (array_slice(isset($in['services']) && is_array($in['services']) ? $in['services'] : array(), 0, 20) as $i => $s) {
        if (!is_array($s)) {
            continue;
        }
        $title = $txt(isset($s['title']) ? $s['title'] : '', 100);
        if ($title === '') {
            continue;
        }
        $id = sanitize_key(isset($s['id']) ? $s['id'] : '');
        if ($id === '' || isset($ids[$id])) {
            $id = 's' . ($i + 1) . substr(md5($title . $i), 0, 4);
        }
        $ids[$id] = 1;
        $list = array();
        foreach (array_slice(isset($s['list']) && is_array($s['list']) ? $s['list'] : array(), 0, 8) as $l) {
            $l = $txt($l, 120);
            if ($l !== '') {
                $list[] = $l;
            }
        }
        $media = array();
        foreach (array_slice(isset($s['media']) && is_array($s['media']) ? $s['media'] : array(), 0, 12) as $m) {
            $m = petgo_site_media_ok(is_scalar($m) ? $m : '');
            if ($m !== '') {
                $media[] = $m;
            }
        }
        $pet = isset($s['pet']) && in_array($s['pet'], array('dog', 'cat', 'all'), true) ? $s['pet'] : 'all';
        $o['services'][] = array(
            'id' => $id, 'title' => $title, 'text' => $txt(isset($s['text']) ? $s['text'] : '', 400), 'pet' => $pet, 'tag' => $txt(isset($s['tag']) ? $s['tag'] : '', 60),
            'list' => $list, 'price' => (string) petgo_site_num(isset($s['price']) ? $s['price'] : 0), 'unit' => $txt(isset($s['unit']) ? $s['unit'] : '', 40),
            'sale' => empty($s['sale']) ? '0' : '1', 'btn' => $txt(isset($s['btn']) ? $s['btn'] : '', 40), 'media' => $media,
        );
    }
    return $o;
}

/* настройки сайта; старый формат (до 29.0) переводится в новый на лету */
function petgo_site_cfg($fresh = false) {
    static $c = null;
    if ($c !== null && !$fresh) {
        return $c;
    }
    $d = petgo_site_defaults();
    $o = get_option('petgo_site', array());
    $o = is_array($o) ? $o : array();
    if ($o && !isset($o['services'])) {
        $n = $d;
        foreach (array('wa', 'front', 'seo', 'head', 'ai_on', 'ai_name', 'ai_key', 'ai_model', 'ai_day', 'ai_know', 'faq') as $k) {
            if (isset($o[$k])) {
                $n[$k] = (string) $o[$k];
            }
        }
        if (!empty($o['ai_phone'])) {
            $n['phone'] = preg_replace('/\D/', '', (string) $o['ai_phone']);
        }
        if (isset($o['off']) && petgo_site_num($o['off'])) {
            $n['off'] = (string) petgo_site_num($o['off']);
        }
        if (!empty($o['title'])) {
            $n['slides'][0] = array('t' => (string) $o['title'], 's' => isset($o['sub']) ? (string) $o['sub'] : '');
        }
        if (!empty($o['hero'])) {
            $n['hero_media'] = array(array('u' => esc_url_raw((string) $o['hero']), 's' => ''));
        }
        if (!empty($o['walk_old']) && petgo_site_num($o['walk_old'])) {
            $n['services'][0]['price'] = (string) petgo_site_num($o['walk_old']);
        }
        if (!empty($o['week_old']) && petgo_site_num($o['week_old'])) {
            $n['services'][1]['price'] = $n['services'][2]['price'] = (string) petgo_site_num($o['week_old']);
        }
        foreach (array('p_walk' => 0, 'p_dog' => 1, 'p_cat' => 2) as $k => $i) {
            $urls = isset($o[$k]) ? petgo_site_lines($o[$k]) : array();
            if ($urls) {
                $n['services'][$i]['media'] = $urls;
            }
        }
        $o = $n;
    }
    $c = array_merge($d, $o);
    foreach (array('slides', 'hero_media', 'checks', 'services') as $k) {
        if (!is_array($c[$k])) {
            $c[$k] = $d[$k];
        }
    }
    return $c;
}

function petgo_site_lines($s) {
    $o = array();
    foreach (preg_split('/[\r\n]+/', (string) $s) as $l) {
        $l = esc_url_raw(trim($l));
        if ($l !== '') {
            $o[] = $l;
        }
    }
    return $o;
}

/* ---------- номера: телефон и WhatsApp задаются в настройках и используются на всём сайте ---------- */
function petgo_site_wa() {
    $c = petgo_site_cfg();
    return preg_replace('/\D/', '', (string) $c['wa']);
}

function petgo_site_phone() {
    $c = petgo_site_cfg();
    $p = preg_replace('/\D/', '', (string) $c['phone']);
    return $p !== '' ? $p : petgo_site_wa();
}

function petgo_site_phone_fmt($d = null) {
    $d = $d === null ? petgo_site_phone() : preg_replace('/\D/', '', (string) $d);
    if (strlen($d) === 11 && ($d[0] === '7' || $d[0] === '8')) {
        return '+7 ' . substr($d, 1, 3) . ' ' . substr($d, 4, 3) . ' ' . substr($d, 7, 2) . ' ' . substr($d, 9, 2);
    }
    return $d === '' ? '' : '+' . $d;
}

/* ---------- цены и скидка ---------- */
function petgo_site_price($svc) {
    $c    = petgo_site_cfg();
    $p    = petgo_site_num($svc['price']);
    $off  = min(90, max(0, (int) $c['off']));
    $sale = $off > 0 && $p > 0 && $svc['sale'] === '1';
    return array('now' => $sale ? (int) round($p * (100 - $off) / 100) : $p, 'old' => $sale ? $p : 0, 'off' => $sale ? $off : 0);
}

function petgo_site_service($id) {
    $c = petgo_site_cfg();
    foreach ($c['services'] as $s) {
        if ($s['id'] === $id) {
            return $s;
        }
    }
    return null;
}

/* подстановки в текстах: {off} {walk} {walk_old} {week} {week_old} */
function petgo_site_vars() {
    static $v = null;
    if ($v !== null) {
        return $v;
    }
    $c  = petgo_site_cfg();
    $sv = $c['services'];
    $a  = petgo_site_service('walk');
    $b  = petgo_site_service('week-dog');
    $a  = $a ? $a : (isset($sv[0]) ? $sv[0] : null);
    $b  = $b ? $b : (isset($sv[1]) ? $sv[1] : $a);
    $pa = $a ? petgo_site_price($a) : array('now' => 0, 'old' => 0);
    $pb = $b ? petgo_site_price($b) : array('now' => 0, 'old' => 0);
    $v  = array(
        '{off}'      => (int) $c['off'] . '%',
        '{walk}'     => petgo_site_money($pa['now']),
        '{walk_old}' => petgo_site_money($pa['old'] ? $pa['old'] : $pa['now']),
        '{week}'     => petgo_site_money($pb['now']),
        '{week_old}' => petgo_site_money($pb['old'] ? $pb['old'] : $pb['now']),
    );
    return $v;
}

function petgo_site_tx($s) {
    return strtr(petgo_site_l((string) $s), petgo_site_vars());
}

/* вопросы: строка «Вопрос | Ответ» */
function petgo_site_faq($c = null) {
    $c   = $c ? $c : petgo_site_cfg();
    $out = array();
    foreach (preg_split('/[\r\n]+/', (string) $c['faq']) as $l) {
        $p = explode('|', $l, 2);
        if (count($p) === 2 && trim($p[0]) !== '' && trim($p[1]) !== '') {
            $out[] = array(trim(petgo_site_tx($p[0])), trim(petgo_site_tx($p[1])));
        }
    }
    return $out;
}

function petgo_site_plural($n, $f) {
    $m = $n % 100;
    $k = $n % 10;
    return ($m > 10 && $m < 20) ? $f[2] : ($k === 1 ? $f[0] : (($k >= 2 && $k <= 4) ? $f[1] : $f[2]));
}

/* отзывы для сайта — только опубликованные в кабинете */
function petgo_site_reviews() {
    static $out = null;
    if ($out !== null) {
        return $out;
    }
    global $wpdb;
    $out = array();
    foreach ((array) $wpdb->get_results('SELECT * FROM ' . petgo_tb('reviews') . " WHERE status='ok' ORDER BY id DESC LIMIT 60") as $r) {
        $out[] = array('n' => (string) $r->name, 'r' => (int) $r->rating, 'd' => petgo_rv_ago((int) $r->ts), 't' => (string) $r->body, 'p' => petgo_rv_photos($r));
    }
    return $out;
}

/* помощник (ИИ) — отдельный плагин «PetGo ИИ». Он сообщает о себе фильтром petgo_ai_active,
   окно чата вставляет в действие petgo_layer_end, свои стили и скрипт — в petgo_site_head / petgo_site_foot.
   Нет плагина или он выключен — кнопки помощника на сайте нет, всё остальное работает */
function petgo_site_ai() {
    static $a = null;
    if ($a === null) {
        $a = (bool) apply_filters('petgo_ai_active', false);
    }
    return $a;
}

/* вывести то, что другие плагины добавили в действие (для своего шаблона и для шорткода) */
function petgo_site_hook($name) {
    ob_start();
    do_action($name);
    return (string) ob_get_clean();
}

/* ---------- страницы сайта ---------- */
function petgo_site_pages() {
    $l = 'petgo_site_l';
    /* 34.5: новые плагины добавляют свои страницы фильтром petgo_site_pages:
       'akcii' => array('slug' => 'akcii', 'name' => 'Акции', 'title' => 'Акции', 'nav' => true) — адрес /akcii/ заработает сам */
    return (array) apply_filters('petgo_site_pages', array(
        'home'     => array('slug' => '', 'name' => $l('Главная'), 'title' => ''),
        'services' => array('slug' => 'uslugi', 'name' => $l('Услуги'), 'title' => $l('Услуги и цены')),
        'nannies'  => array('slug' => 'nyani', 'name' => $l('Няни'), 'title' => $l('Наши няни')),
        'reviews'  => array('slug' => 'otzyvy', 'name' => $l('Отзывы'), 'title' => $l('Отзывы')),
        'account'  => array('slug' => 'lk', 'name' => $l('Кабинет'), 'title' => $l('Кабинет')),
        /* 34.0: анкета питомца (по ссылке из приложения или регистрация с сайта) — в меню не показывается */
        'join'     => array('slug' => 'anketa', 'name' => $l('Анкета питомца'), 'title' => $l('Анкета питомца'), 'hide' => true),
    ));
}

/* разделы в верхнем меню: свои + страницы плагинов с 'nav' => true (перед «Кабинетом») */
function petgo_site_nav_keys() {
    $keys = array('services', 'nannies', 'reviews');
    foreach (petgo_site_pages() as $k => $p) {
        if (!empty($p['nav']) && !in_array($k, $keys, true) && $k !== 'account') {
            $keys[] = $k;
        }
    }
    $keys[] = 'account';
    return (array) apply_filters('petgo_site_nav', $keys);
}

/* адрес страницы сайта; язык (kz, en) сохраняется в адресе. $lang = 'ru' — без языка */
function petgo_site_url($page, $lang = null) {
    $p = petgo_site_pages();
    if (!isset($p[$page]) || $p[$page]['slug'] === '') {
        $u = home_url('/');
    } else {
        $u = get_option('permalink_structure') ? home_url(user_trailingslashit($p[$page]['slug'])) : add_query_arg('petgo', $p[$page]['slug'], home_url('/'));
    }
    return petgo_site_lang_url($u, $lang);
}

/* ---------- значки: один набор, один стиль (контур 1.8, скруглённые концы) ---------- */
function petgo_site_icon($n, $cls = '') {
    static $I = null;
    if ($I === null) {
        $I = array(
            'home'   => '<path d="M4 10.800 12 4l8 6.800V19a1 1 0 0 1-1 1h-4.500v-5.500h-5V20H5a1 1 0 0 1-1-1z"/>',
            'paw'    => '<path d="M12 12.300c-2.700 0-4.800 2.100-4.800 4 0 1.700 1.400 2.400 2.800 2 .900-.300 1.400-.600 2-.600s1.100.300 2 .600c1.400.400 2.800-.300 2.800-2 0-1.900-2.100-4-4.800-4z"/><ellipse cx="6.600" cy="10" rx="1.500" ry="2"/><ellipse cx="9.900" cy="6.500" rx="1.500" ry="2.100"/><ellipse cx="14.100" cy="6.500" rx="1.500" ry="2.100"/><ellipse cx="17.400" cy="10" rx="1.500" ry="2"/>',
            'nanny'  => '<circle cx="9" cy="8" r="3.200"/><path d="M3.500 19.500c0-3.200 2.500-5.300 5.500-5.300s5.500 2.100 5.500 5.300"/><path d="M17.800 10.200c-1.800-1.500-.700-3.600.900-2.500 1.600-1.100 2.700 1 .900 2.500l-.900.800z"/>',
            'star'   => '<path d="M12 3.800l2.500 5.200 5.700.800-4.100 4 1 5.700L12 16.800l-5.100 2.700 1-5.700-4.100-4 5.700-.800z"/>',
            'user'   => '<circle cx="12" cy="8.200" r="3.700"/><path d="M4.500 20c0-4 3.300-6.300 7.500-6.300s7.500 2.300 7.500 6.300"/>',
            'phone'  => '<path d="M6.500 4h3l1.500 4-2 1.500a11 11 0 0 0 5.500 5.500l1.500-2 4 1.500v3a1.500 1.500 0 0 1-1.500 1.500C11.600 19 5 12.400 5 5.500A1.500 1.500 0 0 1 6.500 4z"/>',
            'chat'   => '<path d="M12 4.500c4.400 0 8 2.900 8 6.600s-3.600 6.600-8 6.600c-.900 0-1.700-.100-2.500-.300L5.500 19.500l.900-3.300C4.900 15 4 13.200 4 11.100c0-3.700 3.600-6.600 8-6.600z"/>',
            'spark'  => '<path d="M10.500 4l1.700 4.800 4.800 1.700-4.800 1.700-1.700 4.800-1.700-4.800L4 10.500l4.800-1.700z"/><path d="M18 14.500l.800 2.200 2.200.800-2.200.800-.800 2.200-.800-2.200-2.200-.800 2.200-.800z"/>',
            'check'  => '<path d="M5 12.500l4.500 4.500L19 7.500"/>',
            'x'      => '<path d="M6 6l12 12M18 6L6 18"/>',
            'left'   => '<path d="M15 5l-7 7 7 7"/>',
            'right'  => '<path d="M9 5l7 7-7 7"/>',
            'up'     => '<path d="M12 19V5.500M6 11l6-6 6 6"/>',
            'edit'   => '<path d="M5 19h3.500L19 8.500 15.500 5 5 15.500z"/><path d="M13.500 7l3.500 3.500"/>',
            'key'    => '<circle cx="8" cy="15" r="4"/><path d="M11 12l8.500-8.500M16 7l2.500 2.500M13.500 9.500L15 11"/>',
            'plus'   => '<path d="M12 5v14M5 12h14"/>',
            'camera' => '<path d="M4 8.500A1.500 1.500 0 0 1 5.500 7H8l1.500-2h5L16 7h2.500A1.500 1.500 0 0 1 20 8.500v9a1.500 1.500 0 0 1-1.500 1.500h-13A1.500 1.500 0 0 1 4 17.500z"/><circle cx="12" cy="13" r="3.300"/>',
            'pin'    => '<path d="M12 21s-6.500-5.700-6.500-11a6.500 6.500 0 0 1 13 0c0 5.300-6.500 11-6.500 11z"/><circle cx="12" cy="10" r="2.400"/>',
            'shield' => '<path d="M12 3.500l7 2.500v5.500c0 4.500-3 7.700-7 9-4-1.300-7-4.500-7-9V6z"/><path d="M9 12l2.200 2.200L15 10.500"/>',
        );
    }
    $p = isset($I[$n]) ? $I[$n] : $I['check'];
    return '<svg class="pg-i' . ($cls !== '' ? ' ' . esc_attr($cls) : '') . '" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}

function petgo_site_logo() {
    return '<svg viewBox="0 0 254 72" role="img" aria-label="PetGo.kz"><circle cx="32" cy="36" r="32" fill="#024C46"/><g transform="translate(0 4)" fill="#FBF9E8"><path d="M32 36c-8 0-14 6-14 11 0 5 4 7 8 6 3-1 4-2 6-2s3 1 6 2c4 1 8-1 8-6 0-5-6-11-14-11z"/><ellipse cx="19" cy="30" rx="4.5" ry="6" transform="rotate(-20 19 30)"/><ellipse cx="27" cy="22" rx="4.5" ry="6.5" transform="rotate(-6 27 22)"/><ellipse cx="37" cy="22" rx="4.5" ry="6.5" transform="rotate(6 37 22)"/><ellipse cx="45" cy="30" rx="4.5" ry="6" transform="rotate(20 45 30)"/></g><g transform="translate(76 4) scale(.62)" fill="none" stroke="#024C46" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 30V92"/><circle cx="34" cy="50" r="20"/><path d="M72 50H112A20 20 0 1 0 106.14 64.14"/><path d="M142 12V54Q142 70 158 70"/><path d="M130 32H156"/><circle cx="196" cy="50" r="20"/><path d="M216 30V80Q216 96 196 96Q182 96 176 88"/><circle cx="254" cy="50" r="20"/></g></svg>';
}

/* ============================================================
   КАРКАС: навигация, нижнее меню, помощник, листы с формами, клавиатура — общие для всех страниц
   ============================================================ */
function petgo_site_glass($bezel = 16, $scale = 38) {
    return ' data-glass data-glass-bezel="' . (int) $bezel . '" data-glass-scale="' . (int) $scale . '" data-glass-blur="9"';
}

function petgo_site_layer($page) {
    $c    = petgo_site_cfg();
    $ai   = petgo_site_ai();
    $tel  = petgo_site_phone();
    $pgs  = petgo_site_pages();
    $gl   = petgo_site_glass();
    $cur  = function ($k) use ($page) {
        return $k === $page ? ' class="on" aria-current="page"' : '';
    };
    $l    = 'petgo_site_l';
    $h  = '<div class="pg-layer" id="pg-layer">';

    /* верх: логотип, разделы, язык, номер */
    $h .= '<nav class="pg-nav pg-glass" id="pg-nav" aria-label="' . esc_attr($l('Меню')) . '"' . $gl . '><a class="pg-nav-logo" href="' . esc_url(petgo_site_url('home')) . '" aria-label="' . esc_attr($l('PetGo.kz — на главную')) . '">' . petgo_site_logo() . '</a>';
    $h .= '<div class="pg-nav-links" id="pg-links"><span class="pg-lens" aria-hidden="true"></span>';
    foreach (petgo_site_nav_keys() as $k) {
        if (!isset($pgs[$k])) {
            continue;
        }
        $h .= '<a href="' . esc_url(petgo_site_url($k)) . '"' . $cur($k) . '>' . esc_html($pgs[$k]['name']) . '</a>';
    }
    $h .= '</div>';
    /* язык: RU · KZ · EN */
    $h .= petgo_site_lang_switch('pg-nav-lang', true);
    /* справа вверху — номер телефона (нажатие — звонок); если номера нет — «Оставить заявку» */
    if ($tel !== '') {
        $h .= '<a class="pg-btn pg-btn-primary pg-nav-call" href="tel:+' . esc_attr($tel) . '" aria-label="' . esc_attr(petgo_site_lf('Позвонить: %s', petgo_site_phone_fmt())) . '">' . petgo_site_icon('phone') . '<span>' . esc_html(petgo_site_phone_fmt()) . '</span></a></nav>';
    } else {
        $h .= '<button type="button" class="pg-b pg-btn pg-btn-primary" data-sheet="request">' . esc_html($l('Оставить заявку')) . '</button></nav>';
    }

    /* низ: плавающее меню, помощник посередине, справа — кабинет */
    $h .= '<nav class="pg-dock pg-glass" id="pg-dock" aria-label="' . esc_attr($l('Разделы')) . '"' . petgo_site_glass(18, 44) . '><span class="pg-lens" aria-hidden="true"></span>';
    $h .= '<a href="' . esc_url(petgo_site_url('home')) . '"' . $cur('home') . '>' . petgo_site_icon('home') . '<span>' . esc_html($pgs['home']['name']) . '</span></a>';
    $h .= '<a href="' . esc_url(petgo_site_url('nannies')) . '"' . $cur('nannies') . '>' . petgo_site_icon('nanny') . '<span>' . esc_html($pgs['nannies']['name']) . '</span></a>';
    if ($ai) {
        $h .= '<button type="button" class="pg-b pg-dock-ai" data-chat aria-haspopup="dialog" aria-controls="pga-chat"><i>' . petgo_site_icon('spark') . '</i><span>' . esc_html($l('Помощник')) . '</span></button>';
    }
    /* 36.2: место «Отзывы» в нижнем меню может занять раздел другого плагина (например, «Магазин») */
    $h .= (string) apply_filters('petgo_site_dock_item', '<a href="' . esc_url(petgo_site_url('reviews')) . '"' . $cur('reviews') . '>' . petgo_site_icon('star') . '<span>' . esc_html($pgs['reviews']['name']) . '</span></a>', 'reviews', $page);
    $h .= '<a href="' . esc_url(petgo_site_url('account')) . '"' . $cur('account') . '>' . petgo_site_icon('user') . '<span>' . esc_html($pgs['account']['name']) . '</span></a>';
    $h .= '</nav>';

    /* окно помощника (ИИ) — его выводит плагин «PetGo ИИ» */
    if ($ai) {
        $h .= petgo_site_hook('petgo_layer_end');
    }

    /* листы снизу: заявка и регистрация питомца */
    $h .= petgo_site_sheet('request', $l('Оставьте заявку'), $l('Мы свяжемся с вами и подберём няню.'), petgo_lead_form('request'));
    /* лист регистрации есть на всех страницах: между страницами сайт переходит без перезагрузки */
    if ($page !== '') {
        /* 34.0: регистрация питомца — анкета карточками (см. includes/petform.php) */
        $h .= petgo_site_sheet('pet', $l('Регистрация питомца'), $l('Расскажите о питомце: анкета попадёт к нам, мы добавим питомца в базу и свяжемся с вами.'), petgo_pf_wizard('site'));
    }

    /* окна просмотра отзывов и фото */
    foreach (array('rv' => 'pg-viewer', 'pv' => 'pg-viewer pg-viewer--photo') as $k => $cls) {
        $h .= '<div class="' . $cls . '" id="pg-' . $k . '" hidden role="dialog" aria-modal="true"><div class="pg-counter" id="pg-' . $k . 'Count"></div><button class="pg-b pg-back" id="pg-' . $k . 'Back" type="button" aria-label="' . esc_attr($l('Назад')) . '">' . petgo_site_icon('left') . '</button><button class="pg-b pg-vnav prev" id="pg-' . $k . 'Prev" type="button" aria-label="' . esc_attr($l('Предыдущий')) . '">' . petgo_site_icon('left') . '</button><button class="pg-b pg-vnav next" id="pg-' . $k . 'Next" type="button" aria-label="' . esc_attr($l('Следующий')) . '">' . petgo_site_icon('right') . '</button><div class="pg-track" id="pg-' . $k . 'Track"></div></div>';
    }
    /* своя клавиатура (на телефонах) рисуется скриптом kb.js */
    return $h . '</div>';
}

function petgo_site_sheet($id, $title, $sub, $body) {
    $h  = '<div class="pg-sheet" id="pg-sheet-' . esc_attr($id) . '" hidden role="dialog" aria-modal="true" aria-label="' . esc_attr($title) . '"><div class="pg-sheet-bg" data-close></div>';
    $h .= '<div class="pg-sheet-in pg-glass"><div class="pg-sheet-top" data-drag><i class="pg-sheet-grab" aria-hidden="true"></i><div class="pg-sheet-h"><div><h2>' . esc_html($title) . '</h2><p>' . esc_html($sub) . '</p></div><button type="button" class="pg-b pg-x" data-close aria-label="' . esc_attr(petgo_site_l('Закрыть')) . '">' . petgo_site_icon('x') . '</button></div></div>';
    return $h . '<div class="pg-sheet-sc">' . $body . '</div></div></div>';
}

/* ============================================================
   БЛОКИ СТРАНИЦ
   ============================================================ */

/* первый экран: фото / видео / анимация во весь блок, нижние 30% — стекло со сменяющимся текстом, ниже кнопки */
function petgo_site_b_hero() {
    $c  = petgo_site_cfg();
    $rv = petgo_site_reviews();
    $media = array();
    foreach ($c['hero_media'] as $m) {
        $media[] = array('u' => $m['u'], 's' => (int) $m['s']);
    }
    $h  = '<header class="pg-hero" id="pg-top"><div class="pg-hero-stage" id="pg-hero-stage" data-sec="' . (int) $c['hero_sec'] . '" data-media="' . esc_attr(wp_json_encode($media, JSON_UNESCAPED_SLASHES)) . '">';
    $h .= '<div class="pg-hero-media" id="pg-hero-media" role="img" aria-label="' . esc_attr(petgo_site_l('Няня гуляет с собакой')) . '"></div>';
    /* большое стекло над живой анимацией — без преломления (оно пересчитывается каждый кадр и тормозит), только размытие */
    $h .= '<div class="pg-hero-glass pg-glass"><div class="pg-hero-texts" id="pg-hero-texts">';
    foreach ($c['slides'] as $i => $s) {
        $t  = esc_html(petgo_site_tx($s['t']));
        $h .= '<div class="pg-hero-t' . ($i ? '' : ' on') . '"' . ($i ? ' aria-hidden="true"' : '') . '>' . ($i ? '<p class="pg-h">' . $t . '</p>' : '<h1 class="pg-h">' . $t . '</h1>');
        $h .= $s['s'] !== '' ? '<p class="pg-sub">' . esc_html(petgo_site_tx($s['s'])) . '</p>' : '';
        $h .= '</div>';
    }
    $h .= '</div><div class="pg-hero-dots" id="pg-hero-dots" aria-hidden="true"></div></div></div>';
    $h .= '<div class="pg-wrap pg-hero-cta pg-rise"><div class="pg-row"><button type="button" class="pg-b pg-btn pg-btn-primary" data-sheet="request">' . esc_html(petgo_site_l('Оставить заявку')) . '</button><a class="pg-btn pg-btn-second" href="#pg-services">' . esc_html(petgo_site_l('Услуги и цены')) . '</a></div>';
    if ($c['checks'] || $rv) {
        $h .= '<ul class="pg-chips">';
        if ($rv) {
            $avg = 0;
            foreach ($rv as $x) {
                $avg += $x['r'];
            }
            $h .= '<li class="pg-chip-st"><a href="' . esc_url(petgo_site_url('reviews')) . '">' . petgo_site_icon('star') . '<b>' . esc_html(number_format($avg / count($rv), 1, ',', '')) . '</b> · ' . esc_html(count($rv) . ' ' . petgo_site_n(count($rv), array('отзыв', 'отзыва', 'отзывов'))) . '</a></li>';
        }
        $ic = array('shield', 'camera', 'pin');
        foreach ($c['checks'] as $i => $t) {
            $h .= '<li>' . petgo_site_icon(isset($ic[$i]) ? $ic[$i] : 'check') . esc_html(petgo_site_l($t)) . '</li>';
        }
        $h .= '</ul>';
    }
    return $h . '</div></header>';
}

/* услуги: выбор «Собака / Кошка» и карточки из настроек */
function petgo_site_b_services($head = true) {
    $c   = petgo_site_cfg();
    $sv  = $c['services'];
    if (!$sv) {
        return '';
    }
    $pets = array();
    foreach ($sv as $s) {
        $pets[$s['pet']] = 1;
    }
    $both = isset($pets['dog']) && isset($pets['cat']);
    $off  = (int) $c['off'];
    $h  = '<section class="pg-sec pg-svc" id="pg-services" data-pet="' . ($both ? 'dog' : 'any') . '"><div class="pg-wrap">';
    $L = 'petgo_site_l';
    if ($head) {
        $h .= '<div class="pg-sec-head pg-rise"><h2 class="pg-h">' . esc_html($L('Выберите услугу')) . '</h2>' . ($off > 0 ? '<p class="pg-sub">' . esc_html(petgo_site_lf('Цены указаны для первого заказа со скидкой %s.', $off . '%')) . '</p>' : '') . '</div>';
    }
    if ($both) {
        $h .= '<div class="pg-picker pg-rise" role="tablist" aria-label="' . esc_attr($L('Кто у вас дома')) . '"><span class="pg-picker-ind" aria-hidden="true"></span><button type="button" class="pg-b pg-pet" role="tab" data-pet="dog" aria-selected="true"><span class="pg-pet-art" data-art="art:dog-gold"></span>' . esc_html($L('Собака')) . '</button><button type="button" class="pg-b pg-pet" role="tab" data-pet="cat" aria-selected="false"><span class="pg-pet-art" data-art="art:cat-orange"></span>' . esc_html($L('Кошка')) . '</button></div>';
    }
    $h .= '<div class="pg-grid pg-cards">';
    foreach ($sv as $s) {
        $p     = petgo_site_price($s);
        $title = $L($s['title']);
        $pet   = $L($s['pet'] === 'dog' ? 'собаки' : ($s['pet'] === 'cat' ? 'кошки' : 'питомца'));
        $msg   = petgo_site_lf('Хочу заказать: %s', $title . ' (' . $pet . ')');
        $h    .= '<article class="pg-card pg-rise"' . ($both && $s['pet'] !== 'all' ? ' data-only="' . esc_attr($s['pet']) . '"' : '') . '>';
        if ($s['media']) {
            $h .= '<div class="pg-ph pg-slider" data-media="' . esc_attr(wp_json_encode(array_values($s['media']), JSON_UNESCAPED_SLASHES)) . '"></div>';
        }
        $h .= $s['tag'] !== '' ? '<span class="pg-tag">' . esc_html($L($s['tag'])) . '</span>' : '';
        $h .= '<h3 class="pg-h3">' . esc_html($title) . '</h3>' . ($s['text'] !== '' ? '<p class="pg-p">' . esc_html($L($s['text'])) . '</p>' : '');
        if ($s['list']) {
            $h .= '<ul class="pg-list">';
            foreach ($s['list'] as $l) {
                $h .= '<li>' . petgo_site_icon('check') . '<span>' . esc_html($L($l)) . '</span></li>';
            }
            $h .= '</ul>';
        }
        if ($p['now'] > 0) {
            $h .= '<div class="pg-price-row"><span class="pg-price">' . esc_html(petgo_site_money($p['now'])) . ' ₸</span>' . ($p['off'] ? '<span class="pg-off">−' . (int) $p['off'] . '%</span>' : '');
            $h .= '<span class="pg-meta">' . esc_html($L($s['unit'])) . ($p['old'] ? ' · ' . esc_html($L('потом')) . ' <s>' . esc_html(petgo_site_money($p['old'])) . ' ₸</s>' : '') . '</span></div>';
        }
        /* 34.0: кнопка услуги открывает заявку (а не WhatsApp): услуга и вид питомца подставляются сами */
        $h .= '<button type="button" class="pg-b pg-btn pg-btn-primary" data-sheet="request" data-note="' . esc_attr($msg) . '"' . ($s['pet'] !== 'all' ? ' data-species="' . esc_attr($s['pet']) . '"' : '') . '>' . esc_html($L($s['btn'] !== '' ? $s['btn'] : 'Заказать')) . '</button></article>';
    }
    return $h . '</div></div></section>';
}

function petgo_site_b_how() {
    $h = '<section class="pg-sec" id="pg-how"><div class="pg-wrap"><div class="pg-sec-head pg-rise"><h2 class="pg-h">' . esc_html(petgo_site_l('Как это работает')) . '</h2><p class="pg-sub">' . esc_html(petgo_site_l('Три простых шага.')) . '</p></div><div class="pg-steps pg-rise" id="pg-steps">';
    foreach (array(array('Пишете нам', 'Расскажите, кто ваш питомец и что он любит.'), array('Подбираем няню', 'Обсуждаем детали и находим подходящий формат ухода.'), array('Отдыхаете спокойно', 'Няня заботится о питомце и присылает вам фото.')) as $i => $s) {
        $h .= '<div class="pg-step"><button type="button" class="pg-b pg-step-h" aria-expanded="false"><span class="pg-num">' . ($i + 1) . '</span><b>' . esc_html(petgo_site_l($s[0])) . '</b><i class="pg-chev"></i></button><div class="pg-step-body"><div class="pg-step-in"><p class="pg-p">' . esc_html(petgo_site_l($s[1])) . '</p><div class="pg-ph pg-how-art"></div></div></div><i class="pg-prog"></i></div>';
    }
    return $h . '</div></div></section>';
}

/* отзывы: зелёная панель, стеклянные карточки. $limit = 0 — все */
function petgo_site_b_reviews($limit, $head = true) {
    $rv = petgo_site_reviews();
    if (!$rv) {
        return '';
    }
    $h  = '<section class="pg-sec" id="pg-reviews"><div class="pg-wrap"><div class="pg-rv-panel pg-rise' . ($limit ? '' : ' pg-rv-all') . '">';
    $h .= '<div class="pg-rv-head">' . ($head ? '<div><h2 class="pg-h">' . esc_html(petgo_site_l('Хозяева доверяют нам своих питомцев')) . '</h2><p class="pg-sub">' . esc_html(petgo_site_l('Настоящие отзывы наших клиентов.')) . '</p></div>' : '');
    $h .= '<div class="pg-summary"><div><div class="pg-summary__big" id="pg-avg"></div><div id="pg-avgStars"></div><div class="pg-summary__count" id="pg-count"></div></div><div class="pg-summary__bars" id="pg-bars"></div></div></div>';
    $h .= '<div class="pg-rlist" id="pg-list" data-limit="' . (int) $limit . '"></div>';
    if ($limit && count($rv) > $limit) {
        $h .= '<a class="pg-btn pg-btn-glass" href="' . esc_url(petgo_site_url('reviews')) . '">' . esc_html(petgo_site_lf('Все отзывы · %d', count($rv))) . '</a>';
    }
    return $h . '</div></div></section>';
}

function petgo_site_b_faq() {
    $faq = petgo_site_faq();
    if (!$faq) {
        return '';
    }
    $h = '<div class="pg-faq pg-rise" id="pg-faq"><h3 class="pg-h3">' . esc_html(petgo_site_l('Частые вопросы')) . '</h3><div class="pg-faq-list">';
    foreach ($faq as $q) {
        $h .= '<details class="pg-q"><summary>' . esc_html($q[0]) . '<i class="pg-chev" aria-hidden="true"></i></summary><div class="pg-qa"><p>' . esc_html($q[1]) . '</p></div></details>';
    }
    return $h . '</div></div>';
}

/* заявка внизу страницы: слева текст и вопросы, справа форма */
function petgo_site_b_apply($form = true) {
    $wa = petgo_site_wa();
    $L  = 'petgo_site_l';
    $h  = '<section class="pg-sec" id="pg-apply"><div class="pg-wrap"><div class="pg-apply"><div class="pg-apply-copy pg-rise"><h2 class="pg-h">' . esc_html($L('Оставьте заявку')) . '</h2><p class="pg-sub">' . esc_html($L('Мы свяжемся с вами и подберём няню для питомца.')) . '</p>';
    $h .= '<ul class="pg-checks"><li>' . petgo_site_icon('check') . esc_html($L('Подберём няню под вашего питомца')) . '</li><li>' . petgo_site_icon('check') . esc_html($L('Напишем в WhatsApp или позвоним — как удобно')) . '</li></ul>';
    $h .= '<div class="pg-row"><a class="pg-btn pg-btn-second" href="https://wa.me/' . esc_attr($wa) . '" target="_blank" rel="noopener">' . petgo_site_icon('chat') . 'WhatsApp</a><a class="pg-btn pg-btn-second" href="tel:+' . esc_attr(petgo_site_phone()) . '">' . petgo_site_icon('phone') . esc_html(petgo_site_phone_fmt()) . '</a></div></div>';
    $h .= '<div class="pg-apply-form pg-rise" id="form1">' . ($form ? '<div class="pg-form-card">' . petgo_lead_form('request') . '</div>' : '') . '</div>';
    return $h . petgo_site_b_faq() . '</div></div></section>';
}

/* шапка внутренней страницы */
function petgo_site_b_title($title, $sub = '') {
    return '<header class="pg-phead"><div class="pg-wrap pg-rise"><h1 class="pg-h">' . esc_html($title) . '</h1>' . ($sub !== '' ? '<p class="pg-sub">' . esc_html($sub) . '</p>' : '') . '</div></header>';
}

/* полоса «оставьте заявку» в конце внутренних страниц */
function petgo_site_b_cta($title, $sub) {
    return '<section class="pg-sec pg-sec-cta"><div class="pg-wrap"><div class="pg-cta pg-rise"><div><h2 class="pg-h">' . esc_html($title) . '</h2><p class="pg-sub">' . esc_html($sub) . '</p></div><div class="pg-row"><button type="button" class="pg-b pg-btn pg-btn-primary" data-sheet="request">' . esc_html(petgo_site_l('Оставить заявку')) . '</button><a class="pg-btn pg-btn-second" href="https://wa.me/' . esc_attr(petgo_site_wa()) . '" target="_blank" rel="noopener">' . petgo_site_icon('chat') . 'WhatsApp</a></div></div></div></section>';
}

function petgo_site_b_foot() {
    $pgs = petgo_site_pages();
    $h   = '<footer class="pg-foot"><div class="pg-wrap"><div class="pg-foot-in"><a class="pg-foot-logo" href="' . esc_url(petgo_site_url('home')) . '" aria-label="PetGo.kz">' . petgo_site_logo() . '</a><nav class="pg-foot-links" aria-label="' . esc_attr(petgo_site_l('Разделы')) . '">';
    foreach ($pgs as $k => $p) {
        if (!empty($p['hide'])) {
            continue;
        }
        $h .= '<a href="' . esc_url(petgo_site_url($k)) . '">' . esc_html($p['name']) . '</a>';
    }
    $h .= '<a href="https://wa.me/' . esc_attr(petgo_site_wa()) . '" target="_blank" rel="noopener">WhatsApp</a><a href="tel:+' . esc_attr(petgo_site_phone()) . '">' . esc_html(petgo_site_phone_fmt()) . '</a></nav></div>';
    $h .= petgo_site_lang_switch('pg-foot-lang');
    return $h . '<div class="pg-foot-bot"><span>© ' . esc_html(wp_date('Y')) . ' PetGo.kz</span><span>' . esc_html(petgo_site_l('Няня для питомцев в Алматы')) . '</span></div></div></footer>';
}

/* ---------- страницы целиком ---------- */
function petgo_site_content($page, $a = array()) {
    $c = petgo_site_cfg();
    $L = 'petgo_site_l';
    /* 34.5: страницы других плагинов рисуют себя сами (фильтр petgo_site_page_html) */
    if (!in_array($page, array('home', 'services', 'nannies', 'reviews', 'account', 'join'), true)) {
        $x = apply_filters('petgo_site_page_html', null, $page, $a);
        if ($x !== null) {
            return (string) $x;
        }
        $pgs = petgo_site_pages();
        return petgo_site_b_title(isset($pgs[$page]['title']) ? $pgs[$page]['title'] : '', '');
    }
    switch ($page) {
        case 'services':
            $off = (int) $c['off'];
            return petgo_site_b_title($L('Услуги и цены'), $off > 0 ? petgo_site_lf('Первый заказ — со скидкой %s.', $off . '%') : $L('Выберите, что нужно вашему питомцу.'))
                . petgo_site_b_services(false) . petgo_site_b_how()
                . '<section class="pg-sec"><div class="pg-wrap pg-narrow">' . petgo_site_b_faq() . '</div></section>'
                . petgo_site_b_cta($L('Не нашли нужную услугу?'), $L('Напишите нам — обсудим, что нужно вашему питомцу.'));
        case 'nannies':
            $list = petgo_site_nannies();
            $h = petgo_site_b_title($L('Наши няни'), $list ? $L('Проверенные няни PetGo. Подберём ту, что подойдёт вашему питомцу.') : $L('Скоро здесь появятся анкеты нянь PetGo.'));
            $h .= petgo_site_b_nannies($list);
            return $h . petgo_site_b_cta($L('Подберём няню для вас'), $L('Оставьте заявку — расскажем о няне, которая подойдёт вашему питомцу.'));
        case 'reviews':
            $rv = petgo_site_b_reviews(0, false);
            return petgo_site_b_title($L('Отзывы'), $rv !== '' ? $L('Настоящие отзывы хозяев. Мы публикуем их после завершённого заказа.') : $L('Отзывов пока нет. Они появятся здесь после первых заказов.'))
                . $rv . petgo_site_b_cta($L('Хотите так же?'), $L('Оставьте заявку, и мы подберём няню вашему питомцу.'));
        case 'account':
            /* 34.0: кабинет клиента — вход, регистрация; после входа — питомцы, карта, прогулки (см. includes/client.php) */
            if (petgo_cl_uid() && function_exists('petgo_cl_dashboard')) {
                return petgo_cl_dashboard();
            }
            if (is_user_logged_in() && petgo_role_of() !== '') {
                return petgo_site_b_title($L('Кабинет'), $L('Вы вошли как сотрудник PetGo.')) . '<section class="pg-sec pg-sec-tight"><div class="pg-wrap"><a class="pg-btn pg-btn-primary" data-ext href="' . esc_url(petgo_page_url()) . '">' . esc_html($L('Открыть приложение')) . '</a></div></section>';
            }
            /* без плагина «PetGo Клиент» — регистрация питомца и вход для сотрудников */
            if (!function_exists('petgo_cl_guest')) {
                return petgo_site_b_title($L('Кабинет'), $L('Зарегистрируйте питомца или войдите как сотрудник PetGo.')) . '<section class="pg-sec pg-sec-tight"><div class="pg-wrap"><div class="pg-grid pg-g2 pg-acc"><button type="button" class="pg-b pg-acc-card pg-rise" data-sheet="pet"><span class="pg-acc-i">' . petgo_site_icon('paw') . '</span><b>' . esc_html($L('Зарегистрировать питомца')) . '</b><i class="pg-acc-go">' . petgo_site_icon('right') . '</i></button><a class="pg-acc-card pg-acc-staff pg-rise" data-ext href="' . esc_url(petgo_page_url()) . '"><span class="pg-acc-i">' . petgo_site_icon('key') . '</span><b>' . esc_html($L('Войти как сотрудник')) . '</b><i class="pg-acc-go">' . petgo_site_icon('right') . '</i></a></div></div></section>';
            }
            /* 36.1: «PetGo Клиент» рисует вход отдельным экраном приложения «My PetGo» */
            if (function_exists('petgo_cl_gate')) {
                return petgo_cl_gate();
            }
            return petgo_site_b_title($L('Кабинет'), $L('Следите за питомцем на прогулке, смотрите фото и историю, оставляйте отзывы няням.')) . petgo_cl_guest();
        case 'join':
            $tok = isset($_GET['t']) ? sanitize_text_field(wp_unslash($_GET['t'])) : '';
            $inv = $tok !== '' ? petgo_inv_get($tok) : null;
            if ($inv && $inv['pet']) {
                return petgo_site_b_title($L('Анкета уже заполнена'), $L('Спасибо! Питомец уже в нашей базе. Если нужно что-то изменить — напишите нам.')) . petgo_site_b_cta($L('Нужна няня?'), $L('Оставьте заявку, и мы подберём няню вашему питомцу.'));
            }
            $mode = $inv ? 'invite' : 'site';
            $pre  = array();
            if ($inv) {
                $pre['phone'] = $inv['phone'];
            }
            if (petgo_cl_uid()) {
                $cu = wp_get_current_user();
                $pre['owner'] = $cu->display_name;
                $pre['phone'] = (string) get_user_meta($cu->ID, 'petgo_phone', true);
            }
            return petgo_site_b_title($L('Анкета питомца'), $L($inv ? 'Заполните анкету — это займёт пару минут. Питомец сразу появится у нас в базе.' : 'Расскажите о питомце: анкета попадёт к нам, мы добавим питомца в базу и свяжемся с вами.'))
                . '<section class="pg-sec pg-sec-tight"><div class="pg-wrap pg-narrow"><div class="pgw-solo pg-rise">' . petgo_pf_wizard($mode, array('token' => $inv ? $tok : '', 'pre' => $pre)) . '</div></div></section>';
        default:
            $form = !isset($a['form']) || $a['form'] !== '0';
            /* 34.5: блоки главной по порядку; плагины добавляют свои (фильтр petgo_site_home_sections, ключ — порядок) */
            $sec = array(
                10 => petgo_site_b_hero(),
                20 => petgo_site_b_services(),
                30 => petgo_site_b_how(),
                40 => (!isset($a['reviews']) || $a['reviews'] !== '0') ? petgo_site_b_reviews(6) : '',
                50 => petgo_site_b_apply($form),
            );
            $sec = (array) apply_filters('petgo_site_home_sections', $sec, $a);
            ksort($sec);
            return implode('', $sec);
    }
}

function petgo_site_render($page, $a = array()) {
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    $pgs  = petgo_site_pages();
    $page = isset($pgs[$page]) ? $page : 'home';
    $c    = petgo_site_cfg();
    $ic   = array();
    foreach (array('chat', 'phone', 'check', 'x', 'up') as $k) {
        $ic[$k] = petgo_site_icon($k);
    }
    $cfg = array(
        'page' => $page, 'wa' => petgo_site_wa(), 'phone' => petgo_site_phone(), 'phone_fmt' => petgo_site_phone_fmt(),
        'reviews' => petgo_site_reviews(), 'kbd' => true,
        'ajax' => petgo_rel_url(admin_url('admin-ajax.php')), 'ic' => $ic, 'pages' => array(),
        /* переходы без перезагрузки — только на своих страницах (не внутри темы через шорткод) */
        'spa' => petgo_site_is_front(),
        /* язык сайта и строки для скриптов */
        'lang' => petgo_site_lang(), 'tr' => array_merge((array) petgo_site_js_dict(), petgo_pf_js_dict()),
        /* 34.0: кабинет клиента */
        'client' => petgo_cl_uid() > 0,
        /* 34.5: service worker сайта — экран «Нет интернета» и «Загружаем…» вместо белого экрана */
        'sw' => (petgo_site_is_front() && function_exists('petgo_site_sw_cfg')) ? petgo_site_sw_cfg() : null,
    );
    foreach (array_keys($pgs) as $k) {
        $cfg['pages'][$k] = petgo_site_url($k);
    }
    $h = '';
    if (!petgo_site_is_front()) {
        /* шорткод внутри темы: стили подключаем сами (на своих страницах они уже в <head>) */
        $h .= '<link rel="stylesheet" href="' . esc_url(petgo_asset_url('site.css')) . '">' . petgo_site_hook('petgo_site_head');
    }
    $h .= petgo_site_layer($page);
    $h .= '<div class="pg pg-p-' . esc_attr($page) . '" id="pg"><main>' . petgo_site_content($page, $a) . '</main>' . petgo_site_b_foot() . '</div>';
    $h .= '<script>window.PG_CONFIG=' . wp_json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
    /* 34.2: везде клавиатура PetGo (kb.js) — без полоски браузера над клавиатурой */
    foreach (array('glass.js', 'site.js', 'kb.js', 'ui.js', 'form.js') as $f) {
        $h .= '<script src="' . esc_url(petgo_asset_url($f)) . '" defer></script>';
    }
    /* скрипты других плагинов сайта (например, «PetGo ИИ») */
    return $h . petgo_site_hook('petgo_site_foot');
}

/* шорткоды: [petgo_site] — главная внутри темы (старый способ), page="services|nannies|reviews|account" — другая страница */
add_shortcode('petgo_site', 'petgo_site_shortcode');
function petgo_site_shortcode($atts) {
    $a = shortcode_atts(array('form' => '1', 'reviews' => '1', 'page' => 'home'), $atts);
    return petgo_site_render(sanitize_key($a['page']), $a);
}
