<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   PETGO API (версия 1) — для новых плагинов PetGo.
   Новый плагин подключается к этим точкам и сразу работает: обновлять «Кабинет», «Сайт» и «Клиент» не нужно.
   Подробно и с примером: EXTENDING.md рядом с плагинами (и examples/petgo-primer).

   Главное:
   - add_action('petgo_ready', fn($api)) — все плагины PetGo загружены, можно подключаться (порядок установки не важен);
   - petgo_register_plugin(__FILE__, ['name' => …]) — плагин виден в приложении (Настройки → Дополнения),
     его файлы assets/… находятся через petgo_asset_url();
   - petgo_event('имя', [...]) / add_action('petgo_event', fn($name, $data)) — общие события (заявка, запись, заказ…);
   - приложение сотрудников: фильтры petgo_nav_items, petgo_app_page, petgo_nav_badge, petgo_settings_rows;
   - сайт: petgo_site_pages, petgo_site_page_html, petgo_site_home_sections, petgo_site_nav, petgo_site_head/foot;
   - кабинет клиента: petgo_cl_home_html, petgo_cl_account_html, petgo_cl_sheets, petgo_cl_cfg, petgo_cl_op.
   В браузере: PGEvent('имя', {...}) и PGEvent.on('имя', fn) — те же события для скриптов.
   ============================================================ */

if (!defined('PETGO_API')) {
    define('PETGO_API', 1);
}

function petgo_api_version() {
    return PETGO_API;
}

/* зарегистрировать плагин PetGo: имя и версия видны в приложении, папка assets становится доступной */
function petgo_register_plugin($file, $args = array()) {
    $reg = &petgo_plugins_ref();
    $key = plugin_basename($file);
    $hd  = function_exists('get_file_data') ? get_file_data($file, array('name' => 'Plugin Name', 'ver' => 'Version', 'desc' => 'Description')) : array();
    $reg[$key] = array(
        'file' => $file,
        'name' => isset($args['name']) ? (string) $args['name'] : (!empty($hd['name']) ? $hd['name'] : $key),
        'ver'  => isset($args['version']) ? (string) $args['version'] : (!empty($hd['ver']) ? $hd['ver'] : ''),
        'desc' => isset($args['desc']) ? (string) $args['desc'] : (!empty($hd['desc']) ? $hd['desc'] : ''),
        'core' => !empty($args['core']),
    );
    if (function_exists('petgo_asset_root')) {
        petgo_asset_root($file);
    }
    return true;
}

function &petgo_plugins_ref() {
    static $reg = array();
    return $reg;
}

function petgo_plugins() {
    return petgo_plugins_ref();
}

/* «всё загружено» — после основных плагинов (сайт — 5, клиент — 20); новые плагины подключаются здесь */
petgo_register_plugin(PETGO_FILE, array('name' => 'PetGo Кабинет', 'version' => PETGO_VER, 'core' => true));
add_action('plugins_loaded', 'petgo_api_ready', 50);
function petgo_api_ready() {
    /* «PetGo ИИ» старше API — показываем его в списке сами */
    if (defined('PGA_FILE') && !isset(petgo_plugins()[plugin_basename(PGA_FILE)])) {
        petgo_register_plugin(PGA_FILE, array('name' => 'PetGo ИИ'));
    }
    do_action('petgo_ready', PETGO_API);
}

/* ---------- события: одно место, куда все плагины сообщают, что произошло ---------- */
function petgo_event($name, $data = array()) {
    $name = sanitize_key($name);
    do_action('petgo_event', $name, (array) $data);
    do_action('petgo_event_' . $name, (array) $data);
}

/* события заказа (назначен, пришла няня, гуляют, завершён, отменён) — тоже в общий поток */
add_action('petgo_order_event', 'petgo_api_order_event', 5, 2);
function petgo_api_order_event($oid, $ev) {
    petgo_event('order_' . sanitize_key($ev), array('order' => (int) $oid));
}

/* ============================================================
   ОТКУДА ПРИШЁЛ КЛИЕНТ (реклама, метки utm, клики gclid/yclid/fbclid/ttclid)
   Сайт запоминает это в браузере и добавляет к формам поле pg_src; здесь — чтение и понятная подпись.
   ============================================================ */
function petgo_src_read($raw = null) {
    if ($raw === null) {
        $raw = isset($_POST['pg_src']) ? (string) wp_unslash($_POST['pg_src']) : '';
    }
    if ($raw === '' || strlen($raw) > 3000) {
        return array();
    }
    $j = json_decode($raw, true);
    if (!is_array($j)) {
        return array();
    }
    $out = array();
    foreach (array('last', 'first') as $w) {
        if (empty($j[$w]) || !is_array($j[$w])) {
            continue;
        }
        $x = array();
        foreach (array('s', 'm', 'c', 't', 'x', 'r', 'l') as $k) {
            if (isset($j[$w][$k]) && is_scalar($j[$w][$k])) {
                $v = mb_substr(sanitize_text_field((string) $j[$w][$k]), 0, 120);
                if ($v !== '') {
                    $x[$k] = $v;
                }
            }
        }
        foreach (array('g', 'y', 'f', 'k') as $k) {
            if (!empty($j[$w][$k])) {
                $x[$k] = 1;
            }
        }
        if ($x) {
            $out[$w] = $x;
        }
    }
    return $out;
}

/* «google / cpc · кампания «Выгул» · клик из Google Ads» — для заявки в приложении */
function petgo_src_text($s) {
    if (!$s) {
        return '';
    }
    $one = function ($x) {
        $p = array();
        if (!empty($x['s'])) {
            $p[] = $x['s'] . (!empty($x['m']) ? ' / ' . $x['m'] : '');
        } elseif (!empty($x['g'])) {
            $p[] = 'Google Ads';
        } elseif (!empty($x['y'])) {
            $p[] = 'Яндекс Директ';
        } elseif (!empty($x['f'])) {
            $p[] = 'Instagram / Facebook';
        } elseif (!empty($x['k'])) {
            $p[] = 'TikTok';
        } elseif (!empty($x['r'])) {
            $p[] = $x['r'];
        } else {
            $p[] = 'прямой заход';
        }
        if (!empty($x['c'])) {
            $p[] = 'кампания «' . $x['c'] . '»';
        }
        if (!empty($x['t'])) {
            $p[] = 'запрос «' . $x['t'] . '»';
        }
        return implode(' · ', $p);
    };
    $t = isset($s['last']) ? $one($s['last']) : '';
    if (isset($s['first'])) {
        $f = $one($s['first']);
        if ($f !== $t) {
            $t .= ($t !== '' ? ' (впервые: ' . $f . ')' : $f);
        }
    }
    return $t;
}

/* строка «Источник» для поля extra заявки */
function petgo_src_pairs($s = null) {
    $s = $s === null ? petgo_src_read() : $s;
    $t = petgo_src_text($s);
    return $t !== '' ? array(array('Источник', $t)) : array();
}
