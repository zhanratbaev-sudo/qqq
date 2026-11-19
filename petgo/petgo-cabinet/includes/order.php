<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ЗАКАЗ: короткая карточка в списке + полноэкранное окно заказа (как чат)
   ============================================================ */
function petgo_order_card($o, $admin, $n = 0) {
    $id  = (int) $o->ID;
    $st  = petgo_o($id, 'status');
    $pid = (int) petgo_o($id, 'pet');
    $ts  = (int) petgo_o($id, 'ts');
    $dur = max(1, (int) petgo_o($id, 'dur'));
    $svc = petgo_services();
    $sv  = petgo_o($id, 'service');
    $s   = isset($svc[$sv]) ? petgo_t($svc[$sv]) : '';
    $nm  = petgo_nanny_label($id);
    $pn  = petgo_pet_name($id);
    $h   = '<div class="pgc-ord pgc-oc st-' . esc_attr($st) . '" data-k="o' . $id . '" data-ov="' . $id . '" data-name="' . esc_attr($pn) . '" role="button" tabindex="0">' . petgo_av(petgo_pet_avatar_url($pid), $pn, 'md');
    $h  .= '<div class="pgc-sm"><div class="pgc-l1"><b>' . esc_html($pn) . '</b>' . petgo_badge($st) . '</div>';
    $h  .= '<div class="pgc-l2">' . ($n ? esc_html('#' . $n . ' · ') : '') . esc_html(petgo_fmt($ts) . ' · ' . $s . ' · ' . $dur . ' ' . petgo_t('m')) . '</div>';
    if (($admin && $nm) || in_array($st, array('arrived', 'picked', 'done', 'confirmed'), true)) {
        $h .= '<div class="pgc-l3">' . ($admin && $nm ? '<span class="pgc-who">' . petgo_ic('user') . esc_html($nm) . '</span>' : '') . (in_array($st, array('arrived', 'picked', 'done', 'confirmed'), true) ? petgo_late_badge($id) : '') . '</div>';
    }
    if ($st === 'assigned') {
        $h .= '<div class="pgc-l3"><span class="pgc-cd" data-k="s" data-ts="' . $ts . '"></span></div>';
    } else {
        $h .= petgo_prog($id);
    }
    if (!$admin && petgo_o($id, 'arrived_at') && petgo_late_min($id) > 0 && !petgo_o($id, 'expl')) {
        $h .= '<div class="pgc-l3"><span class="pgc-badge late">' . esc_html(petgo_t('expl_card')) . '</span></div>';
    }
    return $h . '</div></div>';
}

function petgo_order_actions($id, $admin) {
    /* на виду только кнопки выполнения заказа; изменения (назначить, отменить) спрятаны за карандашом */
    if ($admin && !petgo_order_of_nanny($id, get_current_user_id())) {
        return '';
    }
    return petgo_worker_actions($id);
}

/* действия изменения заказа: открываются карандашом вверху окна заказа */
function petgo_edit_actions($id) {
    $st = petgo_o($id, 'status');
    $h  = '';
    if ($st === 'new' || $st === 'assigned') {
        $h .= petgo_form_open('order_assign', 'class="pgc-inline pgc-asg"') . '<input type="hidden" name="order" value="' . $id . '"><select name="nanny" style="width:auto">';
        foreach (petgo_assignees() as $u) {
            $h .= '<option value="' . (int) $u->ID . '"' . selected((int) petgo_o($id, 'nanny'), $u->ID, false) . '>' . esc_html(petgo_assignee_label($u)) . '</option>';
        }
        $h .= '</select> ' . ((petgo_need_why(array('manager')) && (int) petgo_o($id, 'nanny')) ? '<input type="hidden" name="why" value=""><button class="pgc-btn pgc-whyb" type="button" data-q="' . esc_attr(petgo_t('q_reassign')) . '">' : '<button class="pgc-btn" type="submit">') . petgo_ic('users') . esc_html(petgo_t('assign')) . '</button></form>';
    }
    if ($st === 'done' && petgo_can('admin')) {
        $h .= petgo_btn_form('order_set', array('order' => $id, 'do' => 'confirm'), petgo_t('confirm_btn'));
    }
    if ($st !== 'confirmed' && $st !== 'cancelled') {
        $h .= petgo_btn_form('order_set', array('order' => $id, 'do' => 'cancel'), petgo_t('cancel_order'), 'pgc-btn red', petgo_t('q_cancel'), true);
    }
    return $h;
}

/* кнопки выполнения заказа: пришёл → забрал → вернул (для няни, а также для админа/менеджера/владельца на своих заказах) */
function petgo_worker_actions($id) {
    $st  = petgo_o($id, 'status');
    $ts  = (int) petgo_o($id, 'ts');
    $dur = max(1, (int) petgo_o($id, 'dur'));
    $sv  = petgo_o($id, 'service');
    $ph  = (string) get_post_meta((int) petgo_o($id, 'pet'), '_pf_phone', true);
    $h   = '';
    if ($st === 'assigned') {
        $h .= '<div class="pgc-tip">' . esc_html(petgo_t('tip_loc')) . '</div>';
        if ($sv !== 'walk' && function_exists('petgo_qr_block')) {
            /* 34.7: визит — хозяин дома: QR; нет дома — «Я на месте» с местоположением */
            $h .= petgo_qr_block($id, 'arrive');
        } else {
            $h .= petgo_form_open('arrive') . '<input type="hidden" name="order" value="' . $id . '"><input type="hidden" name="lat" value=""><input type="hidden" name="lng" value=""><input type="hidden" name="acc" value=""><button type="button" class="pgc-btn pgc-arrive">' . petgo_ic('pinf') . esc_html(petgo_t('arrived_btn')) . '</button></form>';
        }
    } elseif ($st === 'arrived') {
        $h .= '<div class="pgc-tip">' . esc_html(petgo_t($sv === 'walk' ? 'tip_walk' : 'tip_visit')) . '</div>';
        if ($sv === 'walk') {
            /* 34.7: забрал собаку — QR хозяина или «хозяина нет дома» (с местоположением) */
            $h .= function_exists('petgo_qr_block') ? petgo_qr_block($id, 'pick') : petgo_btn_form('pick', array('order' => $id), petgo_t('picked_btn'), 'pgc-btn', petgo_t('q_pick'));
        } else {
            $h .= petgo_trk_worker_block($id) . petgo_finish_form($id, 'visit_end_btn');
        }
    } elseif ($st === 'picked') {
        $wa = petgo_wa_url($ph);
        $h .= petgo_trk_worker_block($id);
        $h .= '<div class="pgc-wa" data-end="' . ($ts + $dur * 60) . '">' . esc_html(petgo_t('tip_wa')) . ($wa ? '<br><a class="pgc-btn sec" style="margin-top:8px" target="_blank" rel="noopener" href="' . esc_url($wa) . '">' . petgo_ic('msg') . esc_html(petgo_t('wa_btn')) . '</a>' : '') . '</div>';
        $h .= petgo_finish_form($id, 'return_btn');
    } elseif ($st === 'done') {
        $h .= '<p class="mu">' . esc_html(petgo_t('wait_confirm')) . '</p>';
    }
    return $h;
}

/* содержимое окна заказа: сверху только важное, остальное под кнопками-разделами */
function petgo_order_detail($id, $admin) {
    $st  = petgo_o($id, 'status');
    $pid = (int) petgo_o($id, 'pet');
    $ts  = (int) petgo_o($id, 'ts');
    $dur = max(1, (int) petgo_o($id, 'dur'));
    $svc = petgo_services();
    $sv  = petgo_o($id, 'service');
    $s   = isset($svc[$sv]) ? petgo_t($svc[$sv]) : '';
    $pn  = petgo_pet_name($id);
    $nm  = petgo_nanny_label($id);
    $h   = '<div class="pgc-ovh">' . petgo_av(petgo_pet_avatar_url($pid), $pn, 'lg') . '<div><div class="pgc-ovn">' . esc_html($pn) . '</div><div class="mu">' . esc_html(petgo_fmt($ts) . ' · ' . $s . ' · ' . $dur . ' ' . petgo_t('m')) . ($admin && $nm ? ' · ' . esc_html($nm) : '') . '</div><div class="pgc-bds">' . petgo_badge($st) . ' ' . (in_array($st, array('arrived', 'picked', 'done', 'confirmed'), true) ? petgo_late_badge($id) : '');
    if ($st === 'assigned') {
        $h .= '<span class="pgc-cd" data-k="s" data-ts="' . $ts . '"></span>';
    }
    $h .= '</div></div>' . ($admin ? '<button type="button" class="pgc-pen" aria-label="' . esc_attr(petgo_t('edit')) . '">' . petgo_ic('edit') . '</button>' : '') . '</div>';
    if (petgo_o($id, 'comment')) {
        $h .= '<div class="pgc-note">' . nl2br(esc_html(petgo_o($id, 'comment'))) . '</div>';
    }
    if ($admin) {
        $h .= '<div class="pgc-edp" hidden>' . petgo_edit_actions($id) . '</div>';
    }

    $h .= petgo_prog($id);
    $imp = '';
    $fin = !$admin && in_array($st, array('done', 'confirmed', 'cancelled'), true);
    foreach (petgo_main_fields() as $k) {
        $v = (string) get_post_meta($pid, '_pf_' . $k, true);
        if ($v === '' || ($fin && $k !== 'owner')) {
            continue;
        }
        $val = nl2br(esc_html($v));
        if ($k === 'phone') {
            $val = '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $v)) . '">' . esc_html($v) . '</a>';
        } elseif ($k === 'emergency' && preg_match('/\+?\d[\d\s\-()]{8,}\d/', $v, $mm)) {
            $val .= ' <a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $mm[0])) . '">' . esc_html(petgo_t('rq_call')) . '</a>';
        }
        $imp .= '<dt>' . esc_html(petgo_t('f_' . $k)) . '</dt><dd>' . $val . '</dd>';
    }
    if ($imp) {
        $h .= '<div class="pgc-main"><dl class="pgc-dl" style="margin:0">' . $imp . '</dl></div>';
    }
    $h .= petgo_timeline($id, $admin);
    $h .= petgo_expl_block($id, $admin && !petgo_order_of_nanny($id, get_current_user_id()));
    $h .= petgo_rv_order_block($id, $admin);
    $h .= '<div class="pgc-acts">' . petgo_order_actions($id, $admin) . '</div>';

    $secs = array(
        'sec_pet'    => array('info', array('species', 'species_other', 'breed', 'age', 'sex', 'weight', 'neutered')),
        'sec_health' => array('health', array('vaccines', 'parasites', 'health', 'meds')),
        'sec_food'   => array('food', array('food')),
        'sec_behav'  => array('behav', array('people', 'animals', 'fears', 'leash', 'commands', 'toys')),
        'sec_vet'    => array('vet', array('vet')),
        'sec_other'  => array('note', array('notes')),
    );
    $F  = petgo_pet_fields();
    $ch = '<svg class="pgc-chv" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>';
    foreach ($secs as $lab => $def) {
        $rows = '';
        foreach ($def[1] as $k) {
            $v = (string) get_post_meta($pid, '_pf_' . $k, true);
            if ($v !== '' && isset($F[$k])) {
                $rows .= '<dt>' . esc_html(petgo_t('f_' . $k)) . '</dt><dd>' . nl2br(esc_html(petgo_val($k, $v, $F[$k][0]))) . '</dd>';
            }
        }
        if ($rows) {
            $h .= '<details class="pgc-sec"><summary><span class="pgc-sh2">' . petgo_ic($def[0]) . esc_html(petgo_t($lab)) . '</span>' . $ch . '</summary><dl class="pgc-dl">' . $rows . '</dl></details>';
        }
    }
    $h .= petgo_maps($id, $admin, $ch);
    $ph = array_reverse(petgo_photos($pid));
    if ($ph) {
        $h .= '<details class="pgc-sec"><summary><span class="pgc-sh2">' . petgo_ic('photo') . esc_html(petgo_t('photos')) . ' (' . count($ph) . ')</span>' . $ch . '</summary><div class="pgc-gal">';
        foreach ($ph as $x) {
            $by = get_userdata((int) $x['by']);
            $h .= '<figure><a href="' . esc_url($x['url']) . '" target="_blank" rel="noopener"><img src="' . esc_url($x['url']) . '" alt="" loading="lazy" decoding="async"></a><figcaption>' . esc_html(($by ? $by->display_name . ' · ' : '') . wp_date('d.m', (int) $x['t'], petgo_tz())) . '</figcaption>';
            if ($admin && petgo_can('admin')) {
                $h .= petgo_btn_form('photo_avatar', array('pet_id' => $pid, 'photo' => $x['id']), petgo_t('set_avatar'), 'pgc-btn sec');
                $h .= petgo_btn_form('photo_del', array('pet_id' => $pid, 'photo' => $x['id']), '×', 'pgc-btn red', petgo_t('q_delete_photo'), true);
            }
            $h .= '</figure>';
        }
        $h .= '</div></details>';
    }
    return $h;
}

add_action('wp_ajax_petgo_order', 'petgo_ajax_order');
function petgo_ajax_order() {
    check_ajax_referer('petgo_ord', 'n');
    $role = petgo_role_of();
    $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($role === '' || get_post_type($id) !== 'petgo_order' || get_post_status($id) !== 'publish') {
        wp_send_json(array('ok' => false));
    }
    $adm = petgo_can('office');
    if (!$adm && !petgo_order_of_nanny($id, get_current_user_id())) {
        wp_send_json(array('ok' => false));
    }
    wp_send_json(array('ok' => true, 't' => petgo_pet_name($id), 'h' => petgo_order_detail($id, $adm)));
}

function petgo_ov_ui() {
    $auto = isset($_GET['o']) ? (int) $_GET['o'] : 0;
    $back = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';
    echo '<div class="pgc-ov" id="pgc-ov" data-auto="' . $auto . '" aria-hidden="true"><div class="pgc-ovw"><div class="pgc-ch"><button type="button" class="pgc-bk" id="pgc-ovback" aria-label="back">' . $back . '</button><b class="pgc-chtl" id="pgc-ovt"></b><span class="pgc-bk"></span></div><div class="pgc-ovb" id="pgc-ovb"></div></div></div>';
    echo '<div class="pgc-lb" id="pgc-lb"><button type="button" class="pgc-bk" id="pgc-lbback" aria-label="back">' . $back . '</button><div class="pgc-lbi" id="pgc-lbi"><img id="pgc-lbimg" alt=""></div></div>';
}

/* объяснительная: появляется только если сотрудник опоздал, после того как он пришёл */
function petgo_expl_block($id, $admin) {
    if (!petgo_o($id, 'arrived_at') || petgo_late_min($id) <= 0) {
        return '';
    }
    $txt = (string) petgo_o($id, 'expl_text');
    if ($admin) {
        return $txt !== '' ? '<div class="pgc-expl"><b>' . esc_html(petgo_t('why_title')) . ':</b><br>' . nl2br(esc_html($txt)) . '</div>' : '';
    }
    if (petgo_o($id, 'expl')) {
        return '<p class="mu">' . esc_html(petgo_t('expl_sent')) . '</p>';
    }
    return '<div class="pgc-expl"><b>' . esc_html(petgo_t('late_by') . ' ' . petgo_late_min($id) . ' ' . petgo_t('m')) . '</b>' . petgo_form_open('explain') . '<input type="hidden" name="order" value="' . (int) $id . '"><textarea name="text" rows="3" minlength="5" maxlength="1000" required placeholder="' . esc_attr(petgo_t('expl_ph')) . '" style="margin-top:8px"></textarea><p><button class="pgc-btn" type="submit">' . esc_html(petgo_t('expl_send')) . '</button></p></form></div>';
}

add_action('admin_post_petgo_explain', 'petgo_h_explain');
function petgo_h_explain() {
    petgo_guard('worker');
    $oid = (int) petgo_p('order');
    $uid = get_current_user_id();
    if (!petgo_order_of_nanny($oid, $uid) || !petgo_o($oid, 'arrived_at') || petgo_late_min($oid) <= 0 || petgo_o($oid, 'expl')) {
        petgo_fail('err_na');
    }
    $t = trim(sanitize_textarea_field((string) petgo_p('text')));
    if (mb_strlen($t) < 5) {
        petgo_fail('err_why');
    }
    update_post_meta($oid, '_expl', 1);
    update_post_meta($oid, '_expl_text', $t);
    petgo_sys_msg('sys_nannies', $uid, 'why', 'Заказ №' . $oid . ' (' . petgo_pet_name($oid) . '), опоздание ' . petgo_late_min($oid) . " мин.\n\nОбъяснительная: " . $t, $uid);
    petgo_flash(petgo_t('expl_sent'));
    petgo_back('orders', array('o' => $oid));
}

/* линия времени возврата: тусклая линия со светом, пройденная часть яркая, точка идёт к правому краю */
function petgo_prog($id) {
    $st  = petgo_o($id, 'status');
    $sv  = petgo_o($id, 'service');
    $ts  = (int) petgo_o($id, 'ts');
    $end = $ts + max(1, (int) petgo_o($id, 'dur')) * 60;
    if ($st === 'picked') {
        $start = (int) petgo_o($id, 'picked_at');
    } elseif ($st === 'arrived' && $sv !== 'walk') {
        $start = (int) petgo_o($id, 'arrived_at');
    } else {
        return '';
    }
    if (!$start || $start >= $end) {
        $start = $end - 60;
    }
    return '<div class="pgc-prog" data-s="' . $start . '" data-e="' . $end . '"><i class="pgc-pt"></i><i class="pgc-pf"></i><i class="pgc-pd"></i></div><div class="pgc-prl"><span>' . esc_html(wp_date('H:i', $start, petgo_tz())) . '</span><span>' . esc_html(wp_date('H:i', $end, petgo_tz())) . '</span></div>';
}

/* карты прямо на сайте (OpenStreetMap и встроенная карта по адресу), без перехода в другие приложения */
function petgo_maps($id, $admin, $ch) {
    $h    = '';
    $mapd = function ($label, $icon, $url, $open) use ($ch) {
        return '<details class="pgc-sec pgc-mapd"' . ($open ? ' open' : '') . ' data-src="' . esc_url($url) . '"><summary><span class="pgc-sh2">' . petgo_ic($icon) . esc_html($label) . '</span>' . $ch . '</summary><div class="pgc-map"></div></details>';
    };
    if ($admin && petgo_o($id, 'arrived_at')) {
        $lat = petgo_o($id, 'lat');
        $lng = petgo_o($id, 'lng');
        if ($lat !== '' && $lng !== '') {
            $la  = (float) $lat;
            $lo  = (float) $lng;
            $f   = function ($x) {
                return number_format($x, 6, '.', '');
            };
            $url = 'https://www.openstreetmap.org/export/embed.html?bbox=' . $f($lo - 0.004) . '%2C' . $f($la - 0.002) . '%2C' . $f($lo + 0.004) . '%2C' . $f($la + 0.002) . '&layer=mapnik&marker=' . $f($la) . '%2C' . $f($lo);
            $h  .= $mapd(petgo_t('sec_map_pos'), 'pin', $url, true);
        } else {
            $h .= '<p class="mu">' . esc_html(petgo_t('no_location')) . '</p>';
        }
    }
    $addr = (string) get_post_meta((int) petgo_o($id, 'pet'), '_pf_address', true);
    if (!$admin && in_array(petgo_o($id, 'status'), array('done', 'confirmed', 'cancelled'), true)) {
        $addr = '';
    }
    if ($addr !== '') {
        $h .= $mapd(petgo_t('sec_map_addr'), 'map', 'https://maps.google.com/maps?q=' . rawurlencode($addr) . '&z=16&output=embed', false);
    }
    return $h;
}
