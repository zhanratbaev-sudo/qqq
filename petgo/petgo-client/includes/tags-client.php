<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   40.3 QR-АДРЕСНИК В ПРИЛОЖЕНИИ КЛИЕНТА (My PetGo)
   Карточка питомца → «QR-адресник»: привязать по коду с наклейки (или после сканирования QR —
   приложение само предложит), показывать ли телефон, записка для нашедшего, «Питомец потерялся»,
   где нашли (место на карте), отвязать. Сами коды, страница при сканировании и уведомления —
   в «PetGo Кабинет» (includes/tags.php).
   ============================================================ */

function petgo_cl_tag_ok() {
    return function_exists('petgo_tag_of_pet') && function_exists('petgo_tag_bind');
}

/* раздел в карточке питомца */
add_filter('petgo_cl_petpage_extra', function ($h, $pid) {
    if (!petgo_cl_tag_ok()) {
        return $h;
    }
    $L    = 'petgo_site_l';
    $pid  = (int) $pid;
    $tags = petgo_tag_of_pet($pid);
    $qi   = petgo_cl_qr_icon();
    $tz   = petgo_tz();
    $h   .= '<h4 class="pgk-gt">' . esc_html($L('QR-адресник')) . '</h4>';
    if (!$tags) {
        return $h . '<div class="pgk-list"><button type="button" class="pg-b pgk-row" data-k-tg-add="' . $pid . '"><span class="pgk-row-i">' . $qi . '</span><span class="pgk-row-t"><b>' . esc_html($L('Привязать адресник')) . '</b><small class="pgk-wrap">' . esc_html($L('Если питомец потеряется, нашедший отсканирует QR и сразу свяжется с вами')) . '</small></span>' . petgo_cl_icon('right', 'pg-i pgk-chev') . '</button></div>';
    }
    $o  = petgo_tag_opts($pid);
    $h .= '<div class="pgk-list pgk-tg" data-k-tg-pet="' . $pid . '">';
    foreach ($tags as $t) {
        $seen = (int) $t->seen ? wp_date('d.m · H:i', (int) $t->seen, $tz) : '';
        $h   .= '<div class="pgk-row pgk-tg-r"><span class="pgk-row-i">' . $qi . '</span><span class="pgk-row-t"><b class="pgk-tg-c">' . esc_html(petgo_tag_fmt($t->code)) . '</b><small>' . esc_html((int) $t->scans ? petgo_site_lf('Сканировали: %d · последний раз %s', (int) $t->scans, $seen) : $L('Ещё не сканировали')) . '</small></span><button type="button" class="pg-b pgk-tg-x" data-k-tg-del="' . esc_attr($t->code) . '" aria-label="' . esc_attr($L('Отвязать адресник')) . '">' . petgo_cl_icon('x') . '</button></div>';
    }
    $h .= '<label class="pgk-row pgk-tg-o"><span class="pgk-row-t"><b>' . esc_html($L('Показывать мой телефон')) . '</b><small class="pgk-wrap">' . esc_html($L('Нашедший сможет сразу позвонить или написать в WhatsApp')) . '</small></span><input type="checkbox" class="pgk-sw" data-k-tg-opt="phone"' . checked($o['phone'], true, false) . '></label>';
    $h .= '<label class="pgk-row pgk-tg-o pgk-tg-lost' . ($o['lost'] ? ' on' : '') . '"><span class="pgk-row-t"><b>' . esc_html($L('Питомец потерялся')) . '</b><small class="pgk-wrap">' . esc_html($L('На анкете появится «Меня ищут!», а вы получите уведомление о каждом сканировании')) . '</small></span><input type="checkbox" class="pgk-sw pgk-sw-red" data-k-tg-opt="lost"' . checked((bool) $o['lost'], true, false) . '></label>';
    $h .= '<div class="pgk-row pgk-tg-n"><span class="pgk-row-t"><b>' . esc_html($L('Записка для нашедшего')) . '</b><textarea rows="2" maxlength="200" data-k-tg-note placeholder="' . esc_attr($L('Например: боится машин, не отпускайте с поводка. Аллергия на курицу.')) . '">' . esc_textarea($o['note']) . '</textarea></span></div></div>';
    $h .= '<div class="pgk-pp-acts pgk-tg-acts"><a class="pg-b pgk-btn pgk-btn-s" href="' . esc_url(petgo_tag_url($tags[0]->code)) . '" target="_blank" rel="noopener">' . petgo_cl_icon('right') . esc_html($L('Анкета')) . '</a><button type="button" class="pg-b pgk-btn pgk-btn-s" data-k-tg-add="' . $pid . '">' . $qi . esc_html($L('Ещё один')) . '</button></div>';
    $finds = petgo_tag_finds($pid);
    if ($finds) {
        $h .= '<h4 class="pgk-gt">' . esc_html($L('Где нашли')) . '</h4><div class="pgk-list pgk-tg-f">';
        foreach (array_slice($finds, 0, 5) as $f) {
            $geo = isset($f['lat']) && $f['lat'] !== null;
            $h  .= '<div class="pgk-row"><span class="pgk-row-i pgk-tg-pin">' . petgo_cl_icon('map') . '</span><span class="pgk-row-t"><b>' . esc_html(wp_date('d.m · H:i', (int) $f['ts'], $tz)) . '</b>' . ($f['msg'] !== '' ? '<small class="pgk-wrap">«' . esc_html($f['msg']) . '»</small>' : '') . ($geo ? '<span class="pgk-tg-maps"><a href="' . esc_url(petgo_tag_maps($f['lat'], $f['lng'])) . '" target="_blank" rel="noopener">Google ' . esc_html($L('Карты')) . '</a><a href="' . esc_url('https://2gis.kz/geo/' . round((float) $f['lng'], 6) . ',' . round((float) $f['lat'], 6)) . '" target="_blank" rel="noopener">2ГИС</a></span>' : '<small>' . esc_html($L('Место не отправили')) . '</small>') . '</span></div>';
        }
        $h .= '</div>';
    }
    return $h;
}, 10, 2);

/* действия из приложения */
add_action('petgo_cl_op', function ($op, $uid) {
    if (strpos((string) $op, 'tag_') !== 0 || !petgo_cl_tag_ok()) {
        return;
    }
    $L = 'petgo_site_l';
    if ($op === 'tag_bind') {
        if (!petgo_tag_limit('bind' . (int) $uid, 20, HOUR_IN_SECONDS)) {
            petgo_cl_json(false, array('msg' => $L('Слишком много попыток. Попробуйте через час.')));
        }
        $pid = (int) petgo_p('pet');
        $r   = petgo_tag_bind((string) petgo_p('code'), $pid, $uid);
        if ($r !== true) {
            petgo_cl_json(false, array('msg' => $L($r)));
        }
        setcookie('petgo_tagc', '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);
        petgo_cl_json(true, array('pet' => $pid));
    }
    if ($op === 'tag_unbind') {
        $t = petgo_tag_get((string) petgo_p('code'));
        if (!$t || (int) $t->uid !== (int) $uid || (int) $t->st !== 1) {
            petgo_cl_json(false, array('msg' => $L('Нет доступа.')));
        }
        petgo_tag_unbind($t->code, $uid);
        petgo_cl_json(true, array('pet' => (int) $t->pet));
    }
    if ($op === 'tag_opt') {
        $pid = (int) petgo_p('pet');
        if (!petgo_cl_owns($uid, $pid)) {
            petgo_cl_json(false, array('msg' => $L('Нет доступа.')));
        }
        $k = (string) petgo_p('k');
        $v = (string) petgo_p('v');
        if ($k === 'phone') {
            update_post_meta($pid, '_tag_phone', $v === '1' ? '1' : '0');
        } elseif ($k === 'note') {
            update_post_meta($pid, '_tag_note', mb_substr(trim(sanitize_textarea_field($v)), 0, 200));
        } elseif ($k === 'lost') {
            if ($v === '1') {
                $was = (int) get_post_meta($pid, '_tag_lost', true);
                update_post_meta($pid, '_tag_lost', time());
                /* сотрудники сразу узнают — могут помочь с поиском */
                if (!$was && function_exists('petgo_push_notify') && function_exists('petgo_push_users')) {
                    $u = get_userdata((int) $uid);
                    petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), 'Питомец клиента потерялся', get_the_title($pid) . ($u ? ' · ' . $u->display_name . ' · ' . (string) get_user_meta($u->ID, 'petgo_phone', true) : ''), petgo_page_url(array('pg' => 'clients')), 'lost' . $pid);
                }
            } else {
                delete_post_meta($pid, '_tag_lost');
            }
        } else {
            petgo_cl_json(false);
        }
        petgo_cl_json(true, array('pet' => $pid));
    }
    if ($op === 'tag_later') {
        setcookie('petgo_tagc', '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);
        petgo_cl_json(true);
    }
}, 10, 2);

/* лист «QR-адресник»: выбрать питомца и ввести код */
add_filter('petgo_cl_sheets', function ($h, $uid) {
    if (!petgo_cl_tag_ok()) {
        return $h;
    }
    $L = 'petgo_site_l';
    $b = '<div class="pgk-tgs"><span class="pgk-tgs-i">' . petgo_cl_qr_icon() . '</span><p>' . esc_html($L('Наклейка или медальон PetGo с QR-кодом. Если питомец потеряется, человек отсканирует QR и сразу свяжется с вами.')) . '</p>';
    $b .= '<div class="pgk-tgs-pets" data-k-tg-pets role="radiogroup" aria-label="' . esc_attr($L('Питомец')) . '"></div>';
    $b .= '<div class="pgk-list"><label class="pgk-f"><span>' . esc_html($L('Код')) . '</span><input type="text" data-k-tg-code data-kb-lang="en" autocomplete="off" autocapitalize="characters" autocorrect="off" spellcheck="false" maxlength="9" placeholder="ABCD-2345" aria-label="' . esc_attr($L('Код с адресника')) . '"></label></div>';
    $b .= '<small class="pgk-tgs-h">' . esc_html($L('8 букв и цифр под QR-кодом на наклейке')) . '</small><button type="button" class="pg-b pgk-btn pgk-btn-p pgk-go" data-k-tg-bind>' . esc_html($L('Привязать')) . '</button></div>';
    return $h . petgo_cl_sheet('tag', $L('QR-адресник'), $b);
}, 10, 2);

/* код из QR, отсканированного до входа: после входа приложение само предложит привязать */
add_filter('petgo_cl_cfg', function ($cfg, $uid = 0) {
    if (petgo_cl_tag_ok() && !empty($_COOKIE['petgo_tagc'])) {
        $t = petgo_tag_get(sanitize_text_field(wp_unslash($_COOKIE['petgo_tagc'])));
        if ($t && (int) $t->st === 0) {
            $cfg['tagc'] = $t->code;
        }
    }
    foreach (array('QR-адресник', 'Адресник привязан', 'Код — 8 букв и цифр, например ABCD-2345', 'Сначала добавьте питомца', 'Отвязать адресник %s? Анкета по этому QR больше не откроется.', 'Адресник отвязан', 'Сохранено', 'Записка сохранена', 'Отмечено: питомец потерялся. На анкете — «Меня ищут!»', 'Хорошо, что нашёлся!', 'Не получилось — проверьте интернет.', 'Открыть карту', 'Добавьте питомца — потом привяжите адресник') as $x) {
        $cfg['tr'][$x] = petgo_site_l($x);
    }
    return $cfg;
}, 30, 2);
