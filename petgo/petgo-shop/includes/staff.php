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
        $rows[] = array('icon' => 'bag', 'title' => 'Магазин', 'sub' => 'Товары, заказы, зона доставки ' . rtrim(rtrim(number_format($c['radius'], 1, '.', ''), '0'), '.') . ' км', 'url' => petgo_page_url(array('pg' => 'shop')));
    }
    return $rows;
});

/* главная приложения: центр зоны ещё не подтверждён на карте — одна кнопка до настройки */
add_filter('petgo_admin_home_top', function ($h) {
    if (!function_exists('petgo_can') || !petgo_can('admin') || petgo_shop_cfg()['geo_ok'] === '1') {
        return $h;
    }
    return $h . '<a class="pgc-card pshz-warn" href="' . esc_url(petgo_page_url(array('pg' => 'shop', 'tab' => 'zone'))) . '" data-close-nav><span class="pshz-wi">!</span><span class="pshz-wt"><b>Магазин: проверьте зону доставки</b><small>Отметьте на карте точку «' . esc_html(petgo_shop_cfg()['label']) . '» — от неё считаются 2 км бесплатной и платной доставки.</small></span><span class="pgc-btn sm">Открыть</span></a>'
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
        $tabs['zone'] = 'Зона доставки';
    }
    $tab = isset($tabs[$tab]) ? $tab : 'orders';
    echo '<style>' . petgo_shop_staff_css() . '</style><div class="psha">';
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
        echo '</ul><div class="psha-sum"><span>Доставка: ' . esc_html($s['fee'] ? petgo_shop_money($s['fee']) : 'бесплатно') . '</span><b>Итого ' . esc_html(petgo_shop_money($s['sum'] + $s['fee'])) . '</b></div>';
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
    $p    = $p ?: array('id' => 0, 'n' => '', 'd' => '', 'p' => '', 'o' => '', 'c' => 'food', 'pet' => 'all', 'u' => '', 'img' => '', 'in' => true, 'on' => true);
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
    $h .= '<label class="pgc-ck"><input type="checkbox" name="in" value="1"' . checked($p['in'], true, false) . '> <span>Есть в наличии</span></label><label class="pgc-ck"><input type="checkbox" name="on" value="1"' . checked($p['on'], true, false) . '> <span>Показывать на сайте и в кабинете</span></label>';
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
    if (!empty($_FILES['photo']['name'])) {
        $r = petgo_shop_save_image($_FILES['photo']);
        if (is_array($r)) {
            $old = get_post_meta($pid, '_img', true);
            if (is_array($old) && !empty($old['file'])) {
                petgo_rm($old['file']);
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
function petgo_shop_save_image($f) {
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
    $ed->resize(900, 900, false);
    $ed->set_quality(80);
    $mime = wp_image_editor_supports(array('mime_type' => 'image/webp')) ? 'image/webp' : 'image/jpeg';
    $new  = dirname($up['file']) . '/' . wp_generate_password(14, false) . ($mime === 'image/webp' ? '.webp' : '.jpg');
    $s    = $ed->save($new, $mime);
    @unlink($up['file']);
    if (is_wp_error($s) || empty($s['path'])) {
        return 'err_photo';
    }
    if (function_exists('petgo_strip_meta')) {
        petgo_strip_meta($s['path']);
    }
    $ud = wp_upload_dir();
    return array('url' => str_replace(wp_normalize_path($ud['basedir']), $ud['baseurl'], wp_normalize_path($s['path'])), 'file' => $s['path']);
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
        }
        wp_delete_post($pid, true);
        petgo_flash('Товар удалён', true);
    }
    wp_safe_redirect(petgo_page_url(array('pg' => 'shop', 'tab' => 'products')));
    exit;
});

/* ---------- зона доставки: центр на карте, радиус, бесплатно от, цена доставки ---------- */
function petgo_shop_page_zone() {
    $c  = petgo_shop_cfg();
    $ll = function_exists('petgo_asset_where') && is_readable(dirname(petgo_asset_where('leaflet/leaflet.js')) . '/assets/leaflet/leaflet.js');
    echo petgo_form_open('shop_zone', 'class="pshz"');
    echo '<div class="pgc-card"><b>Центр зоны</b><p class="mu">' . ($c['geo_ok'] === '1' ? 'Точка подтверждена. Передвиньте метку, если нужно.' : 'Проверьте точку: перетащите метку на дом «' . esc_html($c['label']) . '» или нажмите на карте — и сохраните.') . '</p>';
    if ($ll) {
        echo '<link rel="stylesheet" href="' . esc_url(petgo_asset_url('leaflet/leaflet.css')) . '"><div class="pshz-map" id="pshz-map"></div>';
    }
    echo '<div class="pgf-list"><label class="pgf-row"><span>Адрес</span><input type="text" name="addr" value="' . esc_attr($c['addr']) . '" maxlength="160"></label><label class="pgf-row"><span>Подпись</span><input type="text" name="label" value="' . esc_attr($c['label']) . '" maxlength="60"></label></div>';
    echo '<button type="button" class="pgc-btn sec" id="pshz-find">Найти адрес на карте</button><input type="hidden" name="lat" value="' . esc_attr($c['lat']) . '"><input type="hidden" name="lng" value="' . esc_attr($c['lng']) . '"></div>';
    echo '<div class="pgc-card"><b>Доставка без няни</b><div class="pgf-list">';
    echo '<label class="pgf-row"><span>Радиус, км</span><input type="text" name="radius" inputmode="decimal" value="' . esc_attr(rtrim(rtrim(number_format($c['radius'], 1, '.', ''), '0'), '.')) . '" maxlength="4"></label>';
    echo '<label class="pgf-row"><span>Бесплатно от, ₸</span><input type="text" name="free" inputmode="numeric" value="' . (int) $c['free'] . '" maxlength="7"></label>';
    echo '<label class="pgf-row"><span>Цена доставки, ₸</span><input type="text" name="fee" inputmode="numeric" value="' . (int) $c['fee'] . '" maxlength="6"></label>';
    echo '</div><p class="mu">С няней — всегда бесплатно: она привозит заказ, когда приходит за питомцем. Адрес вне круга — клиент видит «Скоро добавим ваш район».</p></div>';
    echo '<div class="pgct-save" style="position:sticky;bottom:calc(var(--sab) + 12px);z-index:5"><button type="submit" class="pgc-btn wide lg">Сохранить</button></div></form>';
    if ($ll) {
        echo '<script src="' . esc_url(petgo_asset_url('leaflet/leaflet.js')) . '"></script>';
        echo '<script>(function(){var f=document.querySelector(".pshz"),la=f.elements.lat,lo=f.elements.lng,rr=f.elements.radius,ok=' . ($c['geo_ok'] === '1' ? 'true' : 'false') . ';'
            . 'var m=L.map("pshz-map",{zoomControl:false,attributionControl:true}).setView([+la.value,+lo.value],13);L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19,attribution:"© OpenStreetMap"}).addTo(m);'
            . 'var mk=L.marker([+la.value,+lo.value],{draggable:true,icon:L.divIcon({className:"psh-pin",html:"<i></i>",iconSize:[30,38],iconAnchor:[15,36]})}).addTo(m),c=L.circle([+la.value,+lo.value],{radius:(+rr.value.replace(",","."))*1000,color:"#024C46",weight:2,fillColor:"#2E9E6B",fillOpacity:.12}).addTo(m);'
            . 'function set(p){la.value=p.lat.toFixed(6);lo.value=p.lng.toFixed(6);mk.setLatLng(p);c.setLatLng(p);}'
            . 'mk.on("dragend",function(){set(mk.getLatLng())});m.on("click",function(e){set(e.latlng)});rr.addEventListener("input",function(){var v=+rr.value.replace(",",".");if(v>0)c.setRadius(v*1000)});'
            . 'm.fitBounds(c.getBounds(),{padding:[16,16]});'
            . 'function find(){var q=f.elements.addr.value;fetch("https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=kz&accept-language=ru&q="+encodeURIComponent(q)).then(function(r){return r.json()}).then(function(a){if(a&&a[0]){set({lat:+a[0].lat,lng:+a[0].lon});m.setView([+a[0].lat,+a[0].lon],15)}else alert("Адрес не найден — поставьте метку вручную")}).catch(function(){})}'
            . 'document.getElementById("pshz-find").addEventListener("click",find);if(!ok)find();})();</script>';
    }
}

add_action('admin_post_petgo_shop_zone', function () {
    petgo_guard('admin');
    $in = wp_unslash($_POST);
    $c  = petgo_shop_cfg();
    $la = isset($in['lat']) ? (float) $in['lat'] : 0;
    $lo = isset($in['lng']) ? (float) $in['lng'] : 0;
    if ($la > 40 && $la < 56 && $lo > 46 && $lo < 88) {
        $c['lat'] = round($la, 6);
        $c['lng'] = round($lo, 6);
        $c['geo_ok'] = '1';
    }
    $c['addr']   = isset($in['addr']) && trim((string) $in['addr']) !== '' ? mb_substr(sanitize_text_field((string) $in['addr']), 0, 160) : $c['addr'];
    $c['label']  = isset($in['label']) && trim((string) $in['label']) !== '' ? mb_substr(sanitize_text_field((string) $in['label']), 0, 60) : $c['label'];
    $c['radius'] = isset($in['radius']) ? max(0.3, min(30, (float) str_replace(',', '.', (string) $in['radius']))) : $c['radius'];
    $c['free']   = isset($in['free']) ? max(0, (int) preg_replace('/\D/', '', (string) $in['free'])) : $c['free'];
    $c['fee']    = isset($in['fee']) ? max(0, (int) preg_replace('/\D/', '', (string) $in['fee'])) : $c['fee'];
    update_option('petgo_shop', $c, false);
    petgo_flash('Сохранено', true);
    wp_safe_redirect(petgo_page_url(array('pg' => 'shop', 'tab' => 'zone')));
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
        . '.pshz .pgc-card{display:flex;flex-direction:column;gap:10px;margin-bottom:12px}.pshz-map{height:300px;border-radius:14px;overflow:hidden;background:var(--so)}.psh-pin i{display:block;width:30px;height:30px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:#024C46;box-shadow:0 0 0 3px #fff,0 4px 10px rgba(0,0,0,.3)}.pshz .pgf-list{margin:0}';
}
