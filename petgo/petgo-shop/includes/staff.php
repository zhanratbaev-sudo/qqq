<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ПРИЛОЖЕНИЕ СОТРУДНИКОВ: раздел «Магазин» (заказы, товары, зона доставки)
   и блок «Привезти из магазина» в заказе няни.
   ============================================================ */

add_filter('petgo_nav_items', function ($items, $adm) {
    if ($adm && function_exists('petgo_can') && petgo_can('office')) {
        $items['shop'] = array('Магазин', petgo_page_url(array('pg' => 'shop')), 'bag', 'd');
    }
    return $items;
}, 10, 2);
add_filter('petgo_nav_badge', function ($n, $k) {
    return $k === 'shop' && function_exists('petgo_can') && petgo_can('office') ? petgo_shop_new_count() : $n;
}, 10, 2);
add_filter('petgo_app_title', function ($t, $pg) {
    return $pg === 'shop' ? 'Магазин' : $t;
}, 10, 2);
add_filter('petgo_app_page', function ($done, $pg) {
    if ($done || $pg !== 'shop') {
        return $done;
    }
    petgo_shop_page();
    return true;
}, 10, 2);
add_filter('petgo_settings_rows', function ($rows) {
    if (function_exists('petgo_can') && petgo_can('admin')) {
        $c = petgo_shop_cfg();
        $rows[] = array('icon' => 'bag', 'title' => 'Магазин', 'sub' => ($c['on'] === '1' ? 'Включён' : 'Выключен') . ' · товары, заказы, ' . count($c['zones']) . ' ' . petgo_shop_zw(count($c['zones'])), 'url' => petgo_page_url(array('pg' => 'shop')));
    }
    return $rows;
});

function petgo_shop_zw($n) {
    return function_exists('petgo_site_plural') ? petgo_site_plural($n, array('зона', 'зоны', 'зон')) : 'зон';
}

/* главная приложения: зоны доставки ещё не отмечены на карте — одна кнопка до настройки */
add_filter('petgo_admin_home_top', function ($h) {
    if (!function_exists('petgo_can') || !petgo_can('admin') || petgo_shop_cfg()['geo_ok'] === '1' || !petgo_shop_on()) {
        return $h;
    }
    return $h . '<a class="pgc-card pshz-warn" href="' . esc_url(petgo_page_url(array('pg' => 'shop', 'tab' => 'zone'))) . '" data-close-nav><span class="pshz-wi">!</span><span class="pshz-wt"><b>Магазин: отметьте ЖК доставки</b><small>Добавьте ЖК и районы кругами на карте — там доставка бесплатна от 5 000 ₸ и работают няни.</small></span><span class="pgc-btn sm">Открыть</span></a>'
        . '<style>.pshz-warn{display:flex;align-items:center;gap:12px;margin:0 0 14px;padding:14px 16px;text-decoration:none;color:inherit;box-shadow:inset 0 0 0 1.5px var(--wn,#E8A33C)}.pshz-wi{flex:none;width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-weight:700;background:var(--wn,#E8A33C);color:#fff}.pshz-wt{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}.pshz-wt b{color:var(--tx)}.pshz-wt small{color:var(--mu);line-height:1.35}</style>';
});

/* ---------- заказ няни: что привезти из магазина ---------- */
add_filter('petgo_order_detail_top', function ($h, $oid, $admin) {
    $list = petgo_shop_by_order($oid);
    if (!$list) {
        return $h;
    }
    $b = '<div class="pgc-card psho-nanny"><b>' . petgo_icon('bag') . ' Привезти из магазина</b>';
    $tot = 0;
    foreach ($list as $s) {
        $b .= '<ul>';
        foreach ($s['items'] as $it) {
            $b .= '<li><span>' . esc_html($it['n']) . '</span><em>× ' . (int) $it['q'] . '</em></li>';
        }
        $b .= '</ul>';
        $tot += $s['sum'];
    }
    $b .= '<p class="mu">Получить оплату: <b>' . esc_html(petgo_shop_money($tot)) . '</b> — наличными или Kaspi. Доставка бесплатная.</p></div>';
    $b .= '<style>.psho-nanny{display:flex;flex-direction:column;gap:8px;margin-bottom:12px;box-shadow:inset 0 0 0 1.5px var(--ac,#0d7c6d)}.psho-nanny>b{display:flex;align-items:center;gap:8px;font-size:17px}.psho-nanny>b .pgc-ic{width:22px;height:22px}.psho-nanny ul{margin:0;padding:0;list-style:none}.psho-nanny li{display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:.5px solid var(--sep,rgba(0,0,0,.1))}.psho-nanny li em{font-style:normal;color:var(--mu)}.psho-nanny p{margin:0}</style>';
    return $b . $h;
}, 10, 3);

/* ============================================================
   СТРАНИЦА «МАГАЗИН»
   ============================================================ */
function petgo_shop_page() {
    if (!petgo_can('office')) {
        echo '<p class="mu">' . esc_html(petgo_t('err_perm')) . '</p>';
        return;
    }
    $tab  = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'orders';
    $tabs = array('orders' => 'Заказы', 'products' => 'Товары');
    if (petgo_can('admin')) {
        $tabs['zone'] = 'ЖК и доставка';
        $tabs['set']  = 'Настройки';
    }
    $tab = isset($tabs[$tab]) ? $tab : 'orders';
    echo '<style>' . petgo_shop_staff_css() . '</style><div class="psha">';
    if (!petgo_shop_on() && $tab !== 'set') {
        echo '<a class="pgc-card pshz-warn" href="' . esc_url(petgo_page_url(array('pg' => 'shop', 'tab' => 'set'))) . '"><span class="pshz-wi">!</span><span class="pshz-wt"><b>Магазин выключен</b><small>Клиенты его не видят: ни на сайте, ни в приложении, ни в поиске. Включить — в «Настройках».</small></span></a>';
    }
    echo '<nav class="psha-tabs">';
    foreach ($tabs as $k => $t) {
        $n = $k === 'orders' ? petgo_shop_new_count() : 0;
        echo '<a class="' . ($k === $tab ? 'on' : '') . '" href="' . esc_url(petgo_page_url(array('pg' => 'shop', 'tab' => $k))) . '">' . esc_html($t) . ($n ? ' <i class="pgc-bd">' . $n . '</i>' : '') . '</a>';
    }
    echo '</nav>';
    if ($tab === 'products') {
        petgo_shop_page_products();
    } elseif ($tab === 'zone') {
        petgo_shop_page_zone();
    } elseif ($tab === 'set') {
        petgo_shop_page_set();
    } else {
        petgo_shop_page_orders();
    }
    echo '</div>';
}

function petgo_shop_page_orders() {
    $f   = isset($_GET['f']) ? sanitize_key(wp_unslash($_GET['f'])) : 'open';
    $fl  = array('open' => 'В работе', 'delivered' => 'Доставлены', 'cancelled' => 'Отменены', 'all' => 'Все');
    $f   = isset($fl[$f]) ? $f : 'open';
    echo '<div class="psha-chips">';
    foreach ($fl as $k => $t) {
        echo '<a class="' . ($k === $f ? 'on' : '') . '" href="' . esc_url(petgo_page_url(array('pg' => 'shop', 'f' => $k))) . '">' . esc_html($t) . '</a>';
    }
    echo '</div>';
    $args = array('post_type' => 'petgo_shop_order', 'post_status' => 'publish', 'numberposts' => 60, 'fields' => 'ids', 'no_found_rows' => true);
    if ($f === 'open') {
        $args['meta_query'] = array(array('key' => '_st', 'value' => array('new', 'confirmed'), 'compare' => 'IN'));
    } elseif ($f !== 'all') {
        $args['meta_key']   = '_st';
        $args['meta_value'] = $f;
    }
    $ids = get_posts($args);
    if (!$ids) {
        echo '<div class="pgc-card psha-empty">' . petgo_icon('bag') . '<b>' . ($f === 'open' ? 'Новых заказов нет' : 'Здесь пока пусто') . '</b><p class="mu">Заказы с сайта и из кабинета клиентов появятся здесь, а вам придёт уведомление.</p></div>';
        return;
    }
    $st = petgo_shop_statuses();
    foreach ($ids as $id) {
        $s = petgo_shop_get($id);
        if (!$s) {
            continue;
        }
        $wa = 'https://wa.me/' . $s['phone'];
        echo '<div class="pgc-card psha-o st-' . esc_attr($s['st']) . '"><div class="psha-oh"><b>' . esc_html($s['no']) . '</b><span class="pgc-badge ' . ($s['st'] === 'new' ? 'late' : ($s['st'] === 'delivered' ? 'ok' : '')) . '">' . esc_html($st[$s['st']]) . '</span><small class="mu">' . esc_html(wp_date('d.m H:i', $s['ts'], petgo_tz())) . '</small></div>';
        echo '<div class="psha-who"><b>' . esc_html($s['name']) . '</b> <a href="tel:+' . esc_attr($s['phone']) . '">' . esc_html(function_exists('petgo_site_phone_fmt') ? petgo_site_phone_fmt($s['phone']) : '+' . $s['phone']) . '</a> · <a href="' . esc_url($wa) . '" target="_blank" rel="noopener" data-ext>WhatsApp</a>' . ($s['uid'] ? ' <span class="pgc-badge">клиент ' . esc_html(petgo_cl_code($s['uid'])) . '</span>' : '') . '</div><ul class="psha-items">';
        foreach ($s['items'] as $it) {
            echo '<li><span>' . esc_html($it['n']) . ' <em>× ' . (int) $it['q'] . '</em></span><b>' . esc_html(petgo_shop_money($it['p'] * $it['q'])) . '</b></li>';
        }
        echo '</ul><div class="psha-sum"><span>Доставка: ' . esc_html($s['fee'] ? petgo_shop_money($s['fee']) : 'бесплатно') . ' · ' . esc_html(petgo_shop_pays()[$s['pay'] ?: 'cash']) . '</span><b>Итого ' . esc_html(petgo_shop_money($s['sum'] + $s['fee'])) . '</b></div>';
        if ($s['mode'] === 'nanny') {
            echo '<p class="psha-how">' . petgo_icon('paw') . '<span>Привезёт няня с заказом <a href="' . esc_url(petgo_page_url(array('pg' => 'orders', 'o' => $s['order']))) . '">#' . (int) $s['order'] . '</a> · ' . esc_html(petgo_shop_when((int) petgo_o($s['order'], 'ts'))) . ' · ' . esc_html(petgo_nanny_label($s['order']) ?: 'няня не назначена') . '</span></p>';
        } else {
            echo '<p class="psha-how">' . petgo_icon('home') . '<span>' . esc_html($s['addr']) . ($s['lat'] ? ' · <a href="https://www.openstreetmap.org/?mlat=' . esc_attr($s['lat']) . '&mlon=' . esc_attr($s['lng']) . '#map=17/' . esc_attr($s['lat']) . '/' . esc_attr($s['lng']) . '" target="_blank" rel="noopener" data-ext>на карте</a> · <a href="https://2gis.kz/almaty/geo/' . esc_attr($s['lng']) . ',' . esc_attr($s['lat']) . '" target="_blank" rel="noopener" data-ext>2ГИС</a>' : '') . '</span></p>';
        }
        if ($s['note'] !== '') {
            echo '<p class="psha-note">«' . esc_html($s['note']) . '»</p>';
        }
        if (in_array($s['st'], array('new', 'confirmed'), true)) {
            echo '<div class="psha-act">';
            if ($s['st'] === 'new') {
                echo petgo_shop_st_form($s['id'], 'confirmed', 'Принять', 'pgc-btn sm');
            }
            echo petgo_shop_st_form($s['id'], 'delivered', 'Доставлен', 'pgc-btn sm ' . ($s['st'] === 'new' ? 'sec' : ''));
            echo petgo_shop_st_form($s['id'], 'cancelled', 'Отменить', 'pgc-btn sm sec red', 'Отменить заказ ' . $s['no'] . '?');
            echo '</div>';
        }
        echo '</div>';
    }
}

function petgo_shop_st_form($sid, $st, $label, $cls, $ask = '') {
    return petgo_form_open('shop_st', 'class="psha-f"') . '<input type="hidden" name="sid" value="' . (int) $sid . '"><input type="hidden" name="st" value="' . esc_attr($st) . '"><button type="submit" class="' . esc_attr($cls) . '"' . ($ask !== '' ? ' onclick="return confirm(\'' . esc_js($ask) . '\')"' : '') . '>' . esc_html($label) . '</button></form>';
}

add_action('admin_post_petgo_shop_st', function () {
    petgo_guard('office');
    $sid = isset($_POST['sid']) ? (int) $_POST['sid'] : 0;
    $st  = isset($_POST['st']) ? sanitize_key(wp_unslash($_POST['st'])) : '';
    $ok  = petgo_shop_set_status($sid, $st);
    petgo_flash($ok ? 'Готово: ' . petgo_shop_statuses()[$st] : 'Статус не изменился', $ok);
    wp_safe_redirect(petgo_page_url(array('pg' => 'shop')));
    exit;
});

/* ---------- товары ---------- */
function petgo_shop_page_products() {
    $edit = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
    $all  = petgo_shop_products(true);
    $cur  = null;
    foreach ($all as $p) {
        if ($p['id'] === $edit) {
            $cur = $p;
        }
    }
    echo '<details class="pgc-card psha-add"' . ($cur || !$all ? ' open' : '') . '><summary><b>' . ($cur ? 'Изменить товар' : '+ Добавить товар') . '</b></summary>' . petgo_shop_product_form($cur) . '</details>';
    if (!$all) {
        echo '<p class="mu psha-hint">Добавьте первый товар — он сразу появится на сайте (страница «Магазин»), на главной и в кабинете клиентов.</p>';
        return;
    }
    $cats = petgo_shop_cats();
    echo '<div class="psha-plist">';
    foreach ($all as $p) {
        echo '<div class="pgc-card psha-p' . ($p['on'] ? '' : ' off') . '"><span class="psha-pi">' . ($p['img'] ? '<img src="' . esc_url($p['img']) . '" alt="" loading="lazy">' : petgo_icon('bag')) . '</span><span class="psha-pt"><b>' . esc_html($p['n']) . '</b><small>' . esc_html(petgo_shop_money($p['p'])) . ($p['o'] ? ' <s>' . esc_html(petgo_shop_money($p['o'])) . '</s>' : '') . ' · ' . esc_html(isset($cats[$p['c']]) ? $cats[$p['c']] : '—') . ($p['in'] ? '' : ' · <span class="psha-out">нет в наличии</span>') . ($p['on'] ? '' : ' · скрыт') . '</small></span>';
        echo '<span class="psha-pa"><a class="pgc-btn sm sec" href="' . esc_url(petgo_page_url(array('pg' => 'shop', 'tab' => 'products', 'edit' => $p['id']))) . '">Изменить</a>' . petgo_form_open('shop_pq', 'class="psha-f"') . '<input type="hidden" name="pid" value="' . (int) $p['id'] . '"><input type="hidden" name="do" value="' . ($p['in'] ? 'out' : 'in') . '"><button class="pgc-btn sm sec" type="submit">' . ($p['in'] ? 'Закончился' : 'Есть в наличии') . '</button></form></span></div>';
    }
    echo '</div>';
}

function petgo_shop_product_form($p) {
    $p    = $p ?: array('id' => 0, 'n' => '', 'd' => '', 'p' => '', 'o' => '', 'c' => 'food', 'pet' => 'all', 'u' => '', 'img' => '', 'in' => true, 'on' => true, 'hit' => false);
    $f    = function ($name, $label, $val, $attr = '') {
        return '<label class="pgf-row"><span>' . esc_html($label) . '</span><input type="text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" autocomplete="off"' . $attr . '></label>';
    };
    $h  = petgo_form_open('shop_prod', 'enctype="multipart/form-data" class="psha-pf"') . '<input type="hidden" name="pid" value="' . (int) $p['id'] . '">';
    $h .= '<label class="psha-photo">' . ($p['img'] ? '<img src="' . esc_url($p['img']) . '" alt="">' : petgo_icon('bag')) . '<input class="pgc-vh" type="file" name="photo" accept="image/*" data-preview><span>' . ($p['img'] ? 'Заменить фото' : 'Фото товара') . '</span></label>';
    $h .= '<div class="pgf-list">' . $f('n', 'Название', $p['n'], ' maxlength="120" required placeholder="Royal Canin Mini Adult, 2 кг"') . $f('p', 'Цена, ₸', $p['p'], ' inputmode="numeric" maxlength="7" required placeholder="7900"') . $f('o', 'Старая цена', $p['o'] ?: '', ' inputmode="numeric" maxlength="7" placeholder="необязательно"') . $f('u', 'Вес / объём', $p['u'], ' maxlength="30" placeholder="2 кг, 400 г, 1 шт"');
    $h .= '<label class="pgf-row"><span>Категория</span><select name="c">';
    foreach (petgo_shop_cats() as $k => $t) {
        $h .= '<option value="' . esc_attr($k) . '"' . selected($p['c'], $k, false) . '>' . esc_html($t) . '</option>';
    }
    $h .= '</select></label><label class="pgf-row"><span>Для кого</span><select name="pet">';
    foreach (array('all' => 'Всем', 'dog' => 'Собакам', 'cat' => 'Кошкам') as $k => $t) {
        $h .= '<option value="' . esc_attr($k) . '"' . selected($p['pet'], $k, false) . '>' . esc_html($t) . '</option>';
    }
    $h .= '</select></label></div><textarea name="d" rows="3" maxlength="600" placeholder="Описание: состав, для какого возраста, особенности">' . esc_textarea($p['d']) . '</textarea>';
    $h .= '<label class="pgc-ck"><input type="checkbox" name="in" value="1"' . checked($p['in'], true, false) . '> <span>Есть в наличии</span></label><label class="pgc-ck"><input type="checkbox" name="on" value="1"' . checked($p['on'], true, false) . '> <span>Показывать на сайте и в кабинете</span></label><label class="pgc-ck"><input type="checkbox" name="hit" value="1"' . checked(!empty($p['hit']), true, false) . '> <span>«Хит» — отметка на карточке, товар выше в списке</span></label>';
    $h .= '<div class="psha-row"><button type="submit" class="pgc-btn wide">' . ($p['id'] ? 'Сохранить' : 'Добавить товар') . '</button>' . ($p['id'] ? '<a class="pgc-btn sec" href="' . esc_url(petgo_page_url(array('pg' => 'shop', 'tab' => 'products'))) . '">Отмена</a>' : '') . '</div></form>';
    if ($p['id']) {
        $h .= petgo_form_open('shop_pdel', 'class="psha-f"') . '<input type="hidden" name="pid" value="' . (int) $p['id'] . '"><button type="submit" class="pgc-btn sec red wide" onclick="return confirm(\'Удалить товар?\')">Удалить товар</button></form>';
    }
    return $h;
}

add_action('admin_post_petgo_shop_prod', function () {
    petgo_guard('office');
    $in  = wp_unslash($_POST);
    $pid = isset($in['pid']) ? (int) $in['pid'] : 0;
    $n   = isset($in['n']) ? mb_substr(sanitize_text_field((string) $in['n']), 0, 120) : '';
    $pr  = isset($in['p']) ? (int) preg_replace('/\D/', '', (string) $in['p']) : 0;
    if ($n === '' || $pr <= 0) {
        petgo_flash('Укажите название и цену', false);
        wp_safe_redirect(petgo_page_url(array('pg' => 'shop', 'tab' => 'products') + ($pid ? array('edit' => $pid) : array())));
        exit;
    }
    $post = array('post_type' => 'petgo_product', 'post_title' => $n, 'post_content' => isset($in['d']) ? mb_substr(sanitize_textarea_field((string) $in['d']), 0, 600) : '', 'post_status' => !empty($in['on']) ? 'publish' : 'draft');
    if ($pid && get_post_type($pid) === 'petgo_product') {
        $post['ID'] = $pid;
        wp_update_post($post);
    } else {
        $pid = (int) wp_insert_post($post);
    }
    if (!$pid) {
        petgo_flash('Не получилось сохранить', false);
        wp_safe_redirect(petgo_page_url(array('pg' => 'shop', 'tab' => 'products')));
        exit;
    }
    $cats = petgo_shop_cats();
    update_post_meta($pid, '_price', $pr);
    update_post_meta($pid, '_old', isset($in['o']) ? max(0, (int) preg_replace('/\D/', '', (string) $in['o'])) : 0);
    update_post_meta($pid, '_unit', isset($in['u']) ? mb_substr(sanitize_text_field((string) $in['u']), 0, 30) : '');
    update_post_meta($pid, '_cat', isset($in['c']) && isset($cats[$in['c']]) ? $in['c'] : 'other');
    update_post_meta($pid, '_pet', isset($in['pet']) && in_array($in['pet'], array('all', 'dog', 'cat'), true) ? $in['pet'] : 'all');
    update_post_meta($pid, '_stock', !empty($in['in']) ? '1' : '0');
    update_post_meta($pid, '_hit', !empty($in['hit']) ? '1' : '0');
    if (!empty($_FILES['photo']['name'])) {
        $r = petgo_shop_save_image($_FILES['photo']);
        if (is_array($r)) {
            $old = get_post_meta($pid, '_img', true);
            if (is_array($old) && !empty($old['file'])) {
                petgo_rm($old['file']);
                if (!empty($old['tfile'])) {
                    petgo_rm($old['tfile']);
                }
            }
            update_post_meta($pid, '_img', $r);
        } elseif ($r !== 'none') {
            petgo_flash('Товар сохранён, но фото не подошло (JPG, PNG или WebP)', false);
            wp_safe_redirect(petgo_page_url(array('pg' => 'shop', 'tab' => 'products', 'edit' => $pid)));
            exit;
        }
    }
    petgo_flash('Сохранено — товар уже на сайте', true);
    wp_safe_redirect(petgo_page_url(array('pg' => 'shop', 'tab' => 'products')));
    exit;
});

/* фото товара: открытая папка uploads/petgo-shop (видно всем посетителям сайта), до 900 px, WebP или JPEG, без EXIF */
function petgo_shop_save_image($f, $max = 900, $thumb = true) {
    if (empty($f['name']) || (isset($f['error']) && $f['error'] === UPLOAD_ERR_NO_FILE)) {
        return 'none';
    }
    if ($f['error'] !== UPLOAD_ERR_OK || (function_exists('petgo_upload_ok') && !petgo_upload_ok($f))) {
        return 'err_photo';
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $dir = function ($d) {
        $d['subdir'] = '/petgo-shop';
        $d['path']   = $d['basedir'] . '/petgo-shop';
        $d['url']    = $d['baseurl'] . '/petgo-shop';
        return $d;
    };
    add_filter('upload_dir', $dir);
    $up = wp_handle_upload($f, array('test_form' => false, 'mimes' => array('jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp')));
    remove_filter('upload_dir', $dir);
    if (!empty($up['error']) || empty($up['file'])) {
        return 'err_photo';
    }
    $ed = wp_get_image_editor($up['file']);
    if (is_wp_error($ed)) {
        @unlink($up['file']);
        return 'err_photo';
    }
    if (method_exists($ed, 'maybe_exif_rotate')) {
        $ed->maybe_exif_rotate();
    }
    $ed->resize($max, $max, false);
    $ed->set_quality(80);
    $mime = wp_image_editor_supports(array('mime_type' => 'image/webp')) ? 'image/webp' : 'image/jpeg';
    $base = dirname($up['file']) . '/' . wp_generate_password(14, false);
    $s    = $ed->save($base . ($mime === 'image/webp' ? '.webp' : '.jpg'), $mime);
    /* 2.0: маленькая копия 480 px для карточек — витрина грузится в несколько раз быстрее */
    $t = null;
    if ($thumb && !is_wp_error($s)) {
        $ed->resize(480, 480, false);
        $ed->set_quality(76);
        $t = $ed->save($base . '-s' . ($mime === 'image/webp' ? '.webp' : '.jpg'), $mime);
    }
    @unlink($up['file']);
    if (is_wp_error($s) || empty($s['path'])) {
        return 'err_photo';
    }
    if (function_exists('petgo_strip_meta')) {
        petgo_strip_meta($s['path']);
    }
    $ud  = wp_upload_dir();
    $url = function ($pth) use ($ud) {
        return str_replace(wp_normalize_path($ud['basedir']), $ud['baseurl'], wp_normalize_path($pth));
    };
    $o = array('url' => $url($s['path']), 'file' => $s['path']);
    if ($t && !is_wp_error($t) && !empty($t['path'])) {
        $o['thumb'] = $url($t['path']);
        $o['tfile'] = $t['path'];
    }
    return $o;
}

add_action('admin_post_petgo_shop_pq', function () {
    petgo_guard('office');
    $pid = isset($_POST['pid']) ? (int) $_POST['pid'] : 0;
    if (get_post_type($pid) === 'petgo_product') {
        update_post_meta($pid, '_stock', isset($_POST['do']) && $_POST['do'] === 'in' ? '1' : '0');
        petgo_flash('Сохранено', true);
    }
    wp_safe_redirect(petgo_page_url(array('pg' => 'shop', 'tab' => 'products')));
    exit;
});

add_action('admin_post_petgo_shop_pdel', function () {
    petgo_guard('office');
    $pid = isset($_POST['pid']) ? (int) $_POST['pid'] : 0;
    if (get_post_type($pid) === 'petgo_product') {
        $img = get_post_meta($pid, '_img', true);
        if (is_array($img) && !empty($img['file'])) {
            petgo_rm($img['file']);
            if (!empty($img['tfile'])) {
                petgo_rm($img['tfile']);
            }
        }
        wp_delete_post($pid, true);
        petgo_flash('Товар удалён', true);
    }
    wp_safe_redirect(petgo_page_url(array('pg' => 'shop', 'tab' => 'products')));
    exit;
});

/* ---------- зона доставки: центр на карте, радиус, бесплатно от, цена доставки ---------- */
/* ---------- ЖК и доставка: круги на карте ---------- */
function petgo_shop_page_zone() {
    $c  = petgo_shop_cfg();
    $ll = function_exists('petgo_asset_where') && is_readable(dirname(petgo_asset_where('leaflet/leaflet.js')) . '/assets/leaflet/leaflet.js');
    echo petgo_form_open('shop_zones', 'class="pshz" id="pshz-form"');
    echo '<div class="pgc-card"><b>ЖК и районы</b><p class="mu">Каждый ЖК — круг на карте. Найдите адрес или нажмите на карте, потяните метку в центр ЖК и задайте радиус. В зоне доставки — бесплатно от суммы заказа (обычно 5 000 ₸), иначе цена доставки. Отметка «Няни работают» — там же наши услуги.</p>';
    if ($ll) {
        echo '<link rel="stylesheet" href="' . esc_url(petgo_asset_url('leaflet/leaflet.css')) . '"><div class="pshz-map" id="pshz-map"></div>';
    }
    echo '<div class="pshz-find"><input type="text" id="pshz-q" placeholder="Найти: ЖК Esentai City, Алматы" autocomplete="off"><button type="button" class="pgc-btn sec" id="pshz-go">Найти</button></div>';
    echo '<div class="pshz-leg"><span><i class="a"></i>доставка и няни</span><span><i class="b"></i>только доставка</span><span><i class="c"></i>только няни</span></div></div>';
    echo '<div class="pshz-list" id="pshz-list"></div><button type="button" class="pgc-btn sec wide" id="pshz-add">+ Добавить ЖК в центре карты</button>';
    echo '<input type="hidden" name="zones" id="pshz-json" value="' . esc_attr(wp_json_encode($c['zones'], JSON_UNESCAPED_UNICODE)) . '">';
    echo '<div class="pgct-save" style="position:sticky;bottom:calc(var(--sab) + 12px);z-index:5;margin-top:12px"><button type="submit" class="pgc-btn wide lg">Сохранить</button></div></form>';
    if ($ll) {
        echo '<script src="' . esc_url(petgo_asset_url('leaflet/leaflet.js')) . '"></script>';
    }
    echo '<script>' . petgo_shop_zone_js() . '</script>';
}

function petgo_shop_zone_js() {
    return '(function(){var f=document.getElementById("pshz-form"),inp=document.getElementById("pshz-json"),box=document.getElementById("pshz-list"),Z=[];try{Z=JSON.parse(inp.value)||[]}catch(e){}'
        . 'var L=window.L,m=null,lay={},cur=-1,C={a:"#024C46",b:"#2F6FD1",c:"#8A8F8C"};'
        . 'function col(z){return z.del==="1"&&z.svc==="1"?C.a:z.del==="1"?C.b:C.c}'
        . 'function esc(s){return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c]})}'
        . 'function sync(){inp.value=JSON.stringify(Z)}'
        . 'function draw(i){if(!m)return;var z=Z[i];if(lay[i]){m.removeLayer(lay[i].c);m.removeLayer(lay[i].k)}'
        . 'var c=L.circle([z.lat,z.lng],{radius:+z.r,color:col(z),weight:i===cur?3:2,fillColor:col(z),fillOpacity:i===cur?.22:.12}).addTo(m).on("click",function(){pick(i,true)});'
        . 'var k=L.marker([z.lat,z.lng],{draggable:true,icon:L.divIcon({className:"psh-pin",html:"<i style=\"background:"+col(z)+"\"></i>",iconSize:[30,38],iconAnchor:[15,36]})}).addTo(m).bindTooltip(esc(z.n)||"ЖК",{direction:"top",offset:[0,-34]});'
        . 'k.on("dragend",function(){var p=k.getLatLng();z.lat=+p.lat.toFixed(6);z.lng=+p.lng.toFixed(6);c.setLatLng(p);sync()});lay[i]={c:c,k:k}}'
        . 'function drawAll(){if(!m)return;Object.keys(lay).forEach(function(i){m.removeLayer(lay[i].c);m.removeLayer(lay[i].k)});lay={};Z.forEach(function(z,i){draw(i)})}'
        . 'function card(z,i){return "<div class=\"pgc-card pshz-z"+(i===cur?" on":"")+"\" data-i=\""+i+"\"><div class=\"pshz-zh\"><i style=\"background:"+col(z)+"\"></i><input type=\"text\" data-k=\"n\" value=\""+esc(z.n)+"\" placeholder=\"Название ЖК\" maxlength=\"60\"><button type=\"button\" class=\"pgc-btn sm sec\" data-show>На карте</button></div>"'
        . '+"<label class=\"pshz-r\"><span>Радиус</span><input type=\"range\" min=\"100\" max=\"5000\" step=\"50\" data-k=\"r\" value=\""+z.r+"\"><b>"+(z.r>=1000?(z.r/1000).toFixed(1).replace(".",",")+" км":z.r+" м")+"</b></label>"'
        . '+"<div class=\"pgf-list\"><label class=\"pgf-row\"><span>Бесплатно от, ₸</span><input type=\"text\" inputmode=\"numeric\" data-k=\"free\" value=\""+z.free+"\" maxlength=\"7\"></label><label class=\"pgf-row\"><span>Доставка, ₸</span><input type=\"text\" inputmode=\"numeric\" data-k=\"fee\" value=\""+z.fee+"\" maxlength=\"6\"></label></div>"'
        . '+"<div class=\"pshz-ck\"><label class=\"pgc-ck\"><input type=\"checkbox\" data-k=\"del\""+(z.del==="1"?" checked":"")+"> <span>Доставка</span></label><label class=\"pgc-ck\"><input type=\"checkbox\" data-k=\"svc\""+(z.svc==="1"?" checked":"")+"> <span>Няни работают</span></label><button type=\"button\" class=\"pgc-btn sm sec red\" data-del>Удалить</button></div></div>"}'
        . 'function list(){box.innerHTML=Z.length?Z.map(card).join(""):"<p class=\"mu\" style=\"margin:4px\">Пока нет ни одного ЖК — найдите адрес или нажмите на карте.</p>"}'
        . 'function pick(i,fly){cur=i;list();drawAll();if(m&&fly&&Z[i])m.fitBounds(lay[i].c.getBounds(),{padding:[20,20]});var el=box.querySelector("[data-i=\""+i+"\"]");if(el&&fly)el.scrollIntoView({block:"nearest",behavior:"smooth"})}'
        . 'function add(lat,lng,n){Z.push({id:"",n:n||"",lat:+lat.toFixed(6),lng:+lng.toFixed(6),r:800,free:5000,fee:900,del:"1",svc:"1"});sync();pick(Z.length-1,true);var x=box.querySelector("[data-i=\""+(Z.length-1)+"\"] [data-k=n]");if(x&&!n)x.focus()}'
        . 'box.addEventListener("input",function(e){var t=e.target,c=t.closest("[data-i]");if(!c)return;var i=+c.getAttribute("data-i"),z=Z[i],k=t.getAttribute("data-k");if(!k)return;'
        . 'if(k==="del"||k==="svc"){z[k]=t.checked?"1":"0";var ic=c.querySelector(".pshz-zh i");if(ic)ic.style.background=col(z);draw(i)}else if(k==="r"){z.r=+t.value;t.nextElementSibling.textContent=z.r>=1000?(z.r/1000).toFixed(1).replace(".",",")+" км":z.r+" м";if(lay[i])lay[i].c.setRadius(z.r)}'
        . 'else if(k==="free"||k==="fee"){z[k]=+(t.value.replace(/\D/g,"")||0)}else{z[k]=t.value;if(lay[i])lay[i].k.setTooltipContent(esc(z.n)||"ЖК")}sync()});'
        . 'box.addEventListener("change",function(e){if(e.target.type==="checkbox")box.dispatchEvent(new Event("input"))});'
        . 'box.addEventListener("click",function(e){var c=e.target.closest("[data-i]");if(!c)return;var i=+c.getAttribute("data-i");if(e.target.closest("[data-del]")){if(confirm("Удалить «"+(Z[i].n||"ЖК")+"»?")){Z.splice(i,1);cur=-1;sync();list();drawAll()}return}if(e.target.closest("[data-show]")){pick(i,true);return}if(cur!==i&&!e.target.closest("input")){pick(i,false)}});'
        . 'if(L&&document.getElementById("pshz-map")){m=L.map("pshz-map",{zoomControl:false,attributionControl:true}).setView([43.238,76.89],12);L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19,attribution:"© OpenStreetMap"}).addTo(m);'
        . 'm.on("click",function(e){if(cur>-1&&Z[cur]&&confirm("Перенести «"+(Z[cur].n||"ЖК")+"» сюда? (Отмена — добавить новый ЖК)")){Z[cur].lat=+e.latlng.lat.toFixed(6);Z[cur].lng=+e.latlng.lng.toFixed(6);sync();drawAll()}else add(e.latlng.lat,e.latlng.lng)});drawAll();'
        . 'if(Z.length){var g=L.featureGroup(Object.keys(lay).map(function(i){return lay[i].c}));m.fitBounds(g.getBounds(),{padding:[20,20],maxZoom:14})}}'
        . 'document.getElementById("pshz-add").addEventListener("click",function(){var c=m?m.getCenter():{lat:43.238,lng:76.89};add(c.lat,c.lng)});'
        . 'function find(){var q=document.getElementById("pshz-q").value.trim();if(q.length<3)return;if(!/алмат|almat/i.test(q))q="Алматы, "+q;fetch("https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=kz&accept-language=ru&q="+encodeURIComponent(q)).then(function(r){return r.json()}).then(function(a){if(a&&a[0]){var n=document.getElementById("pshz-q").value.trim();if(m)m.setView([+a[0].lat,+a[0].lon],16);add(+a[0].lat,+a[0].lon,n)}else alert("Адрес не найден — нажмите на карте, где этот ЖК")}).catch(function(){alert("Нет связи с картой")})}'
        . 'document.getElementById("pshz-go").addEventListener("click",find);document.getElementById("pshz-q").addEventListener("keydown",function(e){if(e.key==="Enter"){e.preventDefault();find()}});'
        . 'f.addEventListener("submit",function(e){for(var i=0;i<Z.length;i++){if(!String(Z[i].n).trim()){e.preventDefault();pick(i,true);alert("Укажите название ЖК");return}}sync()});list()})();';
}

add_action('admin_post_petgo_shop_zones', function () {
    petgo_guard('admin');
    $raw = isset($_POST['zones']) ? json_decode(wp_unslash($_POST['zones']), true) : null;
    $c   = get_option('petgo_shop', array());
    $c   = is_array($c) ? $c : array();
    $c['zones']  = petgo_shop_zones_clean(is_array($raw) ? $raw : array());
    $c['geo_ok'] = '1';
    update_option('petgo_shop', $c, false);
    petgo_flash('Сохранено: ' . count($c['zones']) . ' ' . petgo_shop_zw(count($c['zones'])), true);
    wp_safe_redirect(petgo_page_url(array('pg' => 'shop', 'tab' => 'zone')));
    exit;
});

/* ---------- настройки: включить / выключить, фото для баннера ---------- */
function petgo_shop_page_set() {
    $c = petgo_shop_cfg();
    echo petgo_form_open('shop_set', 'enctype="multipart/form-data" class="pshz"');
    echo '<div class="pgc-card"><b>Магазин</b><label class="pgc-ck"><input type="checkbox" name="on" value="1"' . checked($c['on'], '1', false) . '> <span>Магазин включён</span></label><p class="mu">Выключен — магазина нет нигде: ни на сайте (страница, меню, блок на главной), ни в приложении клиентов, ни в поиске Google и Яндекс. Товары и заказы сохраняются — включите, и всё вернётся.</p></div>';
    echo '<div class="pgc-card"><b>Фото для баннера</b><p class="mu">Большая картинка в магазине и на главной приложения (например, собака и кошка). Лучше горизонтальная, от 1200 px.</p><label class="psha-photo">' . ($c['banner'] !== '' ? '<img src="' . esc_url($c['banner']) . '" alt="">' : petgo_icon('bag')) . '<input class="pgc-vh" type="file" name="banner" accept="image/*" data-preview><span>' . ($c['banner'] !== '' ? 'Заменить фото' : 'Выбрать фото') . '</span></label>';
    if ($c['banner'] !== '') {
        echo '<label class="pgc-ck"><input type="checkbox" name="banner_rm" value="1"> <span>Убрать фото (будет рисунок)</span></label>';
    }
    echo '</div><div class="pgct-save" style="position:sticky;bottom:calc(var(--sab) + 12px);z-index:5"><button type="submit" class="pgc-btn wide lg">Сохранить</button></div></form>';
}

add_action('admin_post_petgo_shop_set', function () {
    petgo_guard('admin');
    $c = get_option('petgo_shop', array());
    $c = is_array($c) ? $c : array();
    $c['on'] = !empty($_POST['on']) ? '1' : '0';
    if (!empty($_POST['banner_rm']) && !empty($c['banner_file'])) {
        petgo_rm($c['banner_file']);
        $c['banner'] = '';
        $c['banner_file'] = '';
    }
    if (!empty($_FILES['banner']['name'])) {
        $r = petgo_shop_save_image($_FILES['banner'], 1600, false);
        if (is_array($r)) {
            if (!empty($c['banner_file'])) {
                petgo_rm($c['banner_file']);
            }
            $c['banner']      = $r['url'];
            $c['banner_file'] = $r['file'];
        } elseif ($r !== 'none') {
            petgo_flash('Фото не подошло (JPG, PNG или WebP)', false);
        }
    }
    update_option('petgo_shop', $c, false);
    /* меню и карта сайта меняются — сообщаем сайту, что страницы обновились */
    update_option('petgo_site_mod', time(), false);
    petgo_flash($c['on'] === '1' ? 'Сохранено — магазин включён' : 'Сохранено — магазин выключен и скрыт везде', true);
    wp_safe_redirect(petgo_page_url(array('pg' => 'shop', 'tab' => 'set')));
    exit;
});

function petgo_shop_staff_css() {
    return '.psha{display:flex;flex-direction:column;gap:12px}.psha-tabs,.psha-chips{display:flex;gap:6px;overflow-x:auto;scrollbar-width:none}.psha-tabs a,.psha-chips a{flex:none;padding:8px 14px;border-radius:999px;background:var(--so);color:var(--tx);text-decoration:none;font-weight:600;font-size:15px;display:inline-flex;align-items:center;gap:6px}.psha-tabs a.on,.psha-chips a.on{background:var(--ac,#0d7c6d);color:#fff}.psha-chips a{font-weight:500;font-size:14px;padding:6px 12px}'
        . '.psha-empty{display:flex;flex-direction:column;align-items:center;text-align:center;gap:6px;padding:28px 18px}.psha-empty .pgc-ic{width:34px;height:34px;color:var(--mu)}.psha-empty p{margin:0}'
        . '.psha-o{display:flex;flex-direction:column;gap:8px}.psha-o.st-new{box-shadow:inset 0 0 0 1.5px var(--wn,#E8A33C)}.psha-oh{display:flex;align-items:center;gap:8px}.psha-oh b{font-size:17px}.psha-oh small{margin-left:auto}.psha-who a{color:var(--ac,#0d7c6d)}'
        . '.psha-items{margin:0;padding:0;list-style:none}.psha-items li{display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:.5px solid var(--sep,rgba(0,0,0,.1))}.psha-items em{font-style:normal;color:var(--mu)}.psha-sum{display:flex;justify-content:space-between;align-items:baseline;gap:10px}.psha-sum span{color:var(--mu);font-size:14px}'
        . '.psha-how{display:flex;gap:8px;align-items:flex-start;margin:0;font-size:15px}.psha-how .pgc-ic{flex:none;width:20px;height:20px;color:var(--ac,#0d7c6d)}.psha-how a{color:var(--ac,#0d7c6d)}.psha-note{margin:0;color:var(--mu);font-size:14px}.psha-act{display:flex;flex-wrap:wrap;gap:8px}.psha-f{display:inline;margin:0}'
        . '.psha-add summary{cursor:pointer;list-style:none;font-size:17px}.psha-add summary::-webkit-details-marker{display:none}.psha-pf{display:flex;flex-direction:column;gap:10px;margin-top:12px}.psha-pf textarea{width:100%;padding:10px 12px;border:0;border-radius:12px;background:var(--so);color:inherit;font:inherit;resize:vertical}.psha-pf select{border:0;background:none;font:inherit;color:inherit;text-align:right}.psha-row{display:flex;gap:8px}'
        . '.psha-photo{display:flex;align-items:center;gap:12px;cursor:pointer}.psha-photo img,.psha-photo>.pgc-ic{width:72px;height:72px;border-radius:14px;object-fit:cover;background:var(--so);padding:0}.psha-photo>.pgc-ic{padding:20px;color:var(--mu)}.psha-photo span{color:var(--ac,#0d7c6d);font-weight:600}'
        . '.psha-plist{display:flex;flex-direction:column;gap:8px}.psha-p{display:flex;align-items:center;gap:12px}.psha-p.off{opacity:.55}.psha-pi{flex:none;width:56px;height:56px;border-radius:12px;overflow:hidden;background:var(--so);display:grid;place-items:center}.psha-pi img{width:100%;height:100%;object-fit:cover}.psha-pi .pgc-ic{color:var(--mu)}.psha-pt{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}.psha-pt small{color:var(--mu)}.psha-out{color:var(--rd,#d6453a)}.psha-pa{display:flex;flex-direction:column;gap:6px;align-items:flex-end}.psha-hint{margin:0 4px}'
        . '.pshz .pgc-card{display:flex;flex-direction:column;gap:10px;margin-bottom:12px}.pshz-map{height:340px;border-radius:14px;overflow:hidden;background:var(--so)}.pshz-find{display:flex;gap:8px}.pshz-find input{flex:1;min-width:0;padding:10px 12px;border:0;border-radius:12px;background:var(--so);color:inherit;font:inherit}.pshz-leg{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:13px;color:var(--mu)}.pshz-leg span{display:inline-flex;align-items:center;gap:6px}.pshz-leg i,.pshz-zh i{width:12px;height:12px;border-radius:50%;flex:none}.pshz-leg i.a{background:#024C46}.pshz-leg i.b{background:#2F6FD1}.pshz-leg i.c{background:#8A8F8C}.pshz-list{display:flex;flex-direction:column}.pshz-z.on{box-shadow:inset 0 0 0 2px var(--ac,#0d7c6d)}.pshz-zh{display:flex;align-items:center;gap:10px}.pshz-zh input{flex:1;min-width:0;padding:8px 10px;border:0;border-radius:10px;background:var(--so);color:inherit;font:600 16px/1.2 inherit}.pshz-r{display:flex;align-items:center;gap:10px}.pshz-r span{flex:none;color:var(--mu);font-size:14px}.pshz-r input{flex:1;accent-color:var(--ac,#0d7c6d)}.pshz-r b{flex:none;width:58px;text-align:right;font-size:14px}.pshz-ck{display:flex;flex-wrap:wrap;align-items:center;gap:8px 16px}.pshz-ck .red{margin-left:auto}.psh-pin i{display:block;width:30px;height:30px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:#024C46;box-shadow:0 0 0 3px #fff,0 4px 10px rgba(0,0,0,.3)}.pshz .pgf-list{margin:0}';
}
