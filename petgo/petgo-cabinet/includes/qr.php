<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   QR-КОД ПЕРЕДАЧИ ПИТОМЦА (34.7)
   У каждого заказа свой QR-код. Хозяин открывает заказ в кабинете и показывает QR — няня сканирует его,
   когда забирает собаку на прогулку (выгул) или приходит (визит). Код действует 24 часа и сам меняется.
   Хозяина нет дома — няня подтверждает «без QR», тогда обязательно её местоположение.
   В обоих случаях хозяину приходит сообщение от petgo.kz и место на карте.
   ============================================================ */

/* на каком шаге нужен QR: выгул — когда забирает, визит — когда пришла */
function petgo_qr_step($oid) {
    return petgo_o($oid, 'service') === 'walk' ? 'pick' : 'arrive';
}

/* QR-код заказа: 6 цифр и срок; истёк — выдаётся новый (на 24 часа) */
function petgo_qr($oid, $renew = true) {
    $q = get_post_meta($oid, '_qr', true);
    if (!is_array($q) || empty($q['code']) || (int) $q['exp'] < time()) {
        if (!$renew) {
            return null;
        }
        $q = array('code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT), 'exp' => time() + DAY_IN_SECONDS);
        update_post_meta($oid, '_qr', $q);
    }
    return $q;
}

/* что зашито в QR: ссылка на заказ в приложении — камера телефона тоже откроет её */
function petgo_qr_url($oid, $q) {
    return petgo_page_url(array('o' => (int) $oid, 'qr' => $q['code']));
}

/* из отсканированного текста — код (ссылка, «482 913» или «482913») */
function petgo_qr_code_from($raw) {
    $raw = (string) $raw;
    if (preg_match('/[?&]qr=(\d{6})/', $raw, $m)) {
        return $m[1];
    }
    $d = preg_replace('/\D/', '', $raw);
    return strlen($d) === 6 ? $d : '';
}

function petgo_qr_ok($oid, $raw) {
    $code = petgo_qr_code_from($raw);
    $q    = get_post_meta($oid, '_qr', true);
    return $code !== '' && is_array($q) && !empty($q['code']) && (int) $q['exp'] >= time() && hash_equals((string) $q['code'], $code);
}

/* проверка: QR или «хозяина нет дома» (тогда обязательно место). Ничего не меняет — только проверяет.
   Возвращает array(how => 'qr'|'noqr', lat, lng, acc) или сразу показывает няне ошибку */
function petgo_qr_check($oid, $need_loc_always = false) {
    $raw  = (string) petgo_p('qr');
    $noqr = petgo_p('noqr') === '1';
    $lat  = petgo_p('lat');
    $lng  = petgo_p('lng');
    $loc  = is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180;
    if ($raw !== '') {
        /* перебор кодов не пройдёт: после 5 ошибок за 10 минут — только «без QR» */
        $fk    = 'petgo_qrf_' . (int) $oid;
        $fails = (int) get_transient($fk);
        if ($fails >= 5 || !petgo_qr_ok($oid, $raw)) {
            set_transient($fk, $fails + 1, 10 * MINUTE_IN_SECONDS);
            petgo_fail('qr_bad');
        }
        $how = 'qr';
    } elseif ($noqr) {
        if (!$loc) {
            petgo_fail('err_loc');
        }
        $how = 'noqr';
    } else {
        petgo_fail('qr_need');
    }
    if ($need_loc_always && !$loc) {
        petgo_fail('err_loc');
    }
    return array('how' => $how, 'lat' => $loc ? round((float) $lat, 6) : null, 'lng' => $loc ? round((float) $lng, 6) : null, 'acc' => (int) petgo_p('acc'));
}

/* записать: как прошла передача ('hand' — забрала/пришла, 'back' — вернула/ушла), место — на карту хозяина; код погашен */
function petgo_qr_commit($oid, $r, $what = 'hand') {
    if ($r['lat'] !== null) {
        update_post_meta($oid, '_' . $what . '_lat', $r['lat']);
        update_post_meta($oid, '_' . $what . '_lng', $r['lng']);
        if (function_exists('petgo_trk_add')) {
            petgo_trk_add($oid, (float) $r['lat'], (float) $r['lng'], (int) $r['acc']);
        }
    }
    update_post_meta($oid, '_' . $what . '_by', $r['how']);
    update_post_meta($oid, '_' . $what . '_at', time());
    /* код использован — следующий раз хозяин увидит новый */
    delete_post_meta($oid, '_qr');
    return $r['how'];
}

/* забрала / пришла: проверить и сразу записать */
function petgo_qr_confirm($oid, $need_loc_always = false) {
    return petgo_qr_commit($oid, petgo_qr_check($oid, $need_loc_always), 'hand');
}

/* кнопки няни: «Сканировать QR хозяина» и «Хозяина нет дома — без QR» + скрытая форма */
function petgo_qr_block($id, $action) {
    $pn = petgo_pet_name($id);
    $h  = '<div class="pgq" data-qr-order="' . (int) $id . '">';
    $h .= petgo_form_open($action, 'class="pgq-f" data-qr-form="' . (int) $id . '"') . '<input type="hidden" name="order" value="' . (int) $id . '"><input type="hidden" name="qr" value=""><input type="hidden" name="noqr" value=""><input type="hidden" name="lat" value=""><input type="hidden" name="lng" value=""><input type="hidden" name="acc" value="">';
    $h .= '<button type="button" class="pgc-btn pgq-scan" data-qr-scan="' . (int) $id . '" data-pet="' . esc_attr($pn) . '">' . petgo_ic('qr') . esc_html(petgo_t($action === 'pick' ? 'qr_scan' : 'qr_arrive')) . '</button>';
    $h .= '<button type="button" class="pgc-btn sec pgq-noqr" data-qr-noqr="' . (int) $id . '" data-q="' . esc_attr(petgo_t('qr_q_noqr')) . '">' . petgo_ic('pinf') . esc_html(petgo_t($action === 'pick' ? 'qr_noqr' : 'qr_noqr_v')) . '</button>';
    $h .= '</form><p class="pgq-tip">' . esc_html(petgo_t('qr_tip')) . '</p></div>';
    return $h;
}

/* сканер — один на всё приложение (окно на весь экран с камерой) */
function petgo_qr_scanner_html() {
    return '<div class="pgq-ov" id="pgq-ov" hidden role="dialog" aria-modal="true" aria-label="' . esc_attr(petgo_t('qr_scan')) . '">'
        . '<video id="pgq-v" playsinline muted autoplay></video><div class="pgq-shade"></div><div class="pgq-frame"><i></i><i></i><i></i><i></i></div>'
        . '<button type="button" class="pgc-bk pgq-x" data-qr-close aria-label="' . esc_attr(petgo_t('cancel')) . '">' . petgo_ic('x') . '</button>'
        . '<div class="pgq-bot"><p id="pgq-msg">' . esc_html(petgo_t('qr_cam')) . '</p>'
        . '<form class="pgq-man" id="pgq-man"><input type="text" inputmode="numeric" maxlength="7" autocomplete="one-time-code" placeholder="' . esc_attr(petgo_t('qr_code_ph')) . '"><button type="submit" class="pgc-btn">' . esc_html(petgo_t('qr_manual')) . '</button></form></div></div>';
}

/* строки приложения (RU · KZ · EN · 中文) */
add_filter('petgo_app_dict', function ($d) {
    return array_merge($d, array(
        'qr_scan'   => array('Сканировать QR хозяина', 'Иесінің QR-кодын сканерлеу', 'Scan the owner’s QR', '扫描主人的二维码'),
        'qr_arrive' => array('Хозяин дома — сканировать QR', 'Иесі үйде — QR сканерлеу', 'Owner at home — scan QR', '主人在家——扫码'),
        'qr_noqr'   => array('Хозяина нет дома — без QR', 'Иесі үйде жоқ — QR-сыз', 'Owner not home — no QR', '主人不在——不扫码'),
        'qr_noqr_v' => array('Я на месте (хозяина нет дома)', 'Мен орнымдамын (иесі үйде жоқ)', 'I’m here (owner not home)', '我已到达（主人不在）'),
        'qr_tip'    => array('Хозяин показывает QR в своём кабинете (нажимает на заказ). Ваше местоположение отправится хозяину в любом случае.', 'Иесі QR-ды өз кабинетінде көрсетеді (тапсырысты басады). Орныңыз иесіне кез келген жағдайда жіберіледі.', 'The owner shows the QR in their account (by tapping the booking). Your location is sent to the owner either way.', '主人在个人中心点开订单出示二维码。无论哪种方式，您的位置都会发送给主人。'),
        'qr_q_noqr' => array('Хозяина нет дома? Отметим без QR и отправим хозяину ваше местоположение.', 'Иесі үйде жоқ па? QR-сыз белгілеп, иесіне орныңызды жібереміз.', 'Owner not home? We’ll confirm without QR and send the owner your location.', '主人不在？将不扫码确认，并把您的位置发送给主人。'),
        'qr_cam'    => array('Наведите камеру на QR-код в кабинете хозяина', 'Камераны иесінің кабинетіндегі QR-кодқа бағыттаңыз', 'Point the camera at the QR in the owner’s account', '请将摄像头对准主人个人中心里的二维码'),
        'qr_no_cam' => array('Камера недоступна — введите 6 цифр под QR-кодом', 'Камера қолжетімсіз — QR астындағы 6 цифрды енгізіңіз', 'Camera unavailable — enter the 6 digits under the QR', '无法使用摄像头——请输入二维码下方的6位数字'),
        'qr_manual' => array('Подтвердить', 'Растау', 'Confirm', '确认'),
        'qr_code_ph' => array('6 цифр под QR', 'QR астындағы 6 цифр', '6 digits under the QR', '二维码下方6位数字'),
        'qr_wrong'  => array('Это QR-код другого заказа', 'Бұл басқа тапсырыстың QR-коды', 'This QR belongs to another booking', '这是其他订单的二维码'),
        'qr_bad'    => array('QR-код не подходит к этому заказу или устарел. Попросите хозяина открыть заказ в кабинете ещё раз.', 'QR-код бұл тапсырысқа сәйкес емес немесе ескірген. Иесінен тапсырысты қайта ашуды сұраңыз.', 'The QR doesn’t match this booking or has expired. Ask the owner to open the booking again.', '二维码与此订单不符或已过期，请主人重新打开订单。'),
        'qr_need'   => array('Отсканируйте QR хозяина или отметьте «Хозяина нет дома».', 'Иесінің QR-ын сканерлеңіз немесе «Иесі үйде жоқ» деп белгілеңіз.', 'Scan the owner’s QR or mark “Owner not home”.', '请扫描主人的二维码，或选择“主人不在”。'),
        'qr_by_qr'  => array('по QR', 'QR бойынша', 'by QR', '扫码确认'),
        'qr_back'   => array('Вернул(а) — сканировать QR хозяина', 'Қайтардым — иесінің QR-ын сканерлеу', 'Returned — scan the owner’s QR', '已送回——扫描主人二维码'),
        'qr_back_v' => array('Ухожу — сканировать QR хозяина', 'Кетіп барамын — иесінің QR-ын сканерлеу', 'Leaving — scan the owner’s QR', '离开——扫描主人二维码'),
        'qr_back_no' => array('Хозяина нет дома — завершить без QR', 'Иесі үйде жоқ — QR-сыз аяқтау', 'Owner not home — finish without QR', '主人不在——不扫码完成'),
        'qr_back_tip' => array('По QR заказ завершится сразу. Без QR — после подтверждения администратора. Фото обязательно.', 'QR арқылы тапсырыс бірден аяқталады. QR-сыз — әкімші растағаннан кейін. Фото міндетті.', 'With the QR the booking completes at once. Without it, after an admin confirms. A photo is required.', '扫码后订单立即完成；不扫码需管理员确认。必须拍照。'),
        'qr_q_back_no' => array('Хозяина нет дома? Завершим без QR — заказ подтвердит администратор. Хозяину отправим ваше местоположение.', 'Иесі үйде жоқ па? QR-сыз аяқтаймыз — тапсырысты әкімші растайды. Иесіне орныңызды жібереміз.', 'Owner not home? We’ll finish without QR — an admin will confirm. Your location goes to the owner.', '主人不在？将不扫码完成，由管理员确认，并把您的位置发送给主人。'),
        'qr_wait_adm' => array('Завершено без QR — ждёт подтверждения администратора', 'QR-сыз аяқталды — әкімшінің растауын күтуде', 'Finished without QR — waiting for an admin to confirm', '未扫码完成——等待管理员确认'),
        'qr_done_qr' => array('Завершено по QR хозяина', 'Иесінің QR-ы бойынша аяқталды', 'Completed with the owner’s QR', '已通过主人二维码完成'),
        'qr_by_no'  => array('без QR (хозяина не было)', 'QR-сыз (иесі болмады)', 'without QR (owner away)', '未扫码（主人不在）'),
    ));
});

/* завершение: фото и отчёт няни + QR хозяина (сразу завершено) или «хозяина нет дома» (подтвердит администратор) */
function petgo_qr_finish_form($id, $walk) {
    $h  = petgo_form_open('finish', 'enctype="multipart/form-data" class="pgc-finish pgq-f" data-qr-form="' . (int) $id . '" data-qr-finish="1"') . '<input type="hidden" name="order" value="' . (int) $id . '"><input type="hidden" name="qr" value=""><input type="hidden" name="noqr" value=""><input type="hidden" name="lat" value=""><input type="hidden" name="lng" value=""><input type="hidden" name="acc" value="">';
    $h .= (function_exists('petgo_rep_fields') ? petgo_rep_fields($id) : '');
    $h .= '<label class="pgc-shot"><input class="pgc-vh" type="file" name="photo" accept="image/*" capture="environment" required data-preview><span class="pgc-shot-in"><svg class="pgc-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg><span>' . esc_html(petgo_t('tip_photo')) . '</span></span></label>';
    $h .= '<div class="pgq"><button type="button" class="pgc-btn wide pgq-scan" data-qr-scan="' . (int) $id . '">' . petgo_ic('qr') . esc_html(petgo_t($walk ? 'qr_back' : 'qr_back_v')) . '</button>';
    $h .= '<button type="button" class="pgc-btn sec wide pgq-noqr" data-qr-noqr="' . (int) $id . '" data-q="' . esc_attr(petgo_t('qr_q_back_no')) . '">' . petgo_ic('pinf') . esc_html(petgo_t('qr_back_no')) . '</button>';
    $h .= '<p class="pgq-tip">' . esc_html(petgo_t('qr_back_tip')) . '</p></div></form>';
    return $h;
}

/* ============================================================
   36.0 «НЯНЯ У ДВЕРИ»: у клиента QR-код открывается сам, на весь экран.
   Сигнал ставится, когда няня отметила «Пришла» на выгул, открыла сканер QR или прикрепила фото в конце.
   Кабинет клиента видит его при обновлении (раз в несколько секунд, пока идёт заказ) и показывает «Покажите QR няне».
   ============================================================ */
function petgo_door_phase($oid) {
    $st   = petgo_o($oid, 'status');
    $walk = petgo_o($oid, 'service') === 'walk';
    if ($walk) {
        return in_array($st, array('assigned', 'arrived'), true) ? 'pick' : ($st === 'picked' ? 'back' : '');
    }
    return $st === 'assigned' ? 'arrive' : ($st === 'arrived' ? 'leave' : '');
}

function petgo_door($oid) {
    $ph = petgo_door_phase($oid);
    if ($ph === '') {
        return '';
    }
    $old = get_post_meta($oid, '_door', true);
    /* тот же этап недавно уже отмечен — время не трогаем, чтобы у клиента QR не открывался повторно */
    if (!is_array($old) || $old['ph'] !== $ph || time() - (int) $old['t'] > 10 * MINUTE_IN_SECONDS) {
        update_post_meta($oid, '_door', array('t' => time(), 'ph' => $ph));
    }
    return $ph;
}

/* у двери и QR ещё не отсканирован (этап не закрыт) */
function petgo_door_open($oid) {
    $d = get_post_meta($oid, '_door', true);
    if (!is_array($d) || time() - (int) $d['t'] > 20 * MINUTE_IN_SECONDS) {
        return null;
    }
    return petgo_door_phase($oid) === $d['ph'] ? $d : null;
}

add_action('wp_ajax_petgo_door', function () {
    check_ajax_referer('petgo_ord', 'n');
    $oid = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if (!$oid || get_post_type($oid) !== 'petgo_order' || !petgo_order_of_nanny($oid, get_current_user_id())) {
        wp_send_json(array('ok' => false));
    }
    wp_send_json(array('ok' => true, 'ph' => petgo_door($oid)));
});

add_filter('petgo_app_dict', function ($d) {
    $d['qr_door'] = array('У клиента QR-код уже открыт на экране — попросите показать телефон', 'Клиенттің экранында QR-код ашық — телефонды көрсетуді сұраңыз', 'The QR code is already open on the owner’s screen — ask them to show the phone', '二维码已在主人屏幕上打开——请对方出示手机');
    return $d;
});
