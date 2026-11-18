<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   АНКЕТА ПИТОМЦА — одна схема полей для всех мест (карточки на сайте — в плагине «PetGo Сайт», includes/site-form.php):
   - сайт: «Зарегистрировать питомца» — карточки по шагам, попадает в «Заявки» (ждёт обработки);
   - ссылка-анкета из приложения (?t=…): питомец сразу добавляется в базу;
   - кабинет клиента: питомец сразу в базе и привязан к аккаунту клиента;
   - приложение: форма «Добавить / Изменить питомца» — разделы, одинаковые строки, переключатели.
   ============================================================ */

/* шаги анкеты: ключ => [значок, заголовок, подсказка, поля] */
function petgo_pf_steps() {
    return array(
        'pet'    => array('paw', 'Знакомство', 'Как зовут питомца и кто он', array('title', 'species', 'species_other', 'avatar')),
        'about'  => array('info', 'О питомце', 'Порода, возраст и пол', array('breed', 'sex', 'age', 'weight', 'neutered')),
        'health' => array('health', 'Здоровье', 'Прививки, болезни и лекарства', array('vaccines', 'parasites', 'health', 'meds')),
        'habits' => array('food', 'Питание и привычки', 'Что ест и что любит', array('food', 'toys', 'commands')),
        'behav'  => array('behav', 'Характер', 'Как ведёт себя дома и на улице', array('people', 'animals', 'fears', 'leash')),
        'owner'  => array('user', 'Хозяин', 'Как с вами связаться и как к вам попасть', array('owner', 'phone', 'address', 'access', 'emergency', 'vet')),
        'final'  => array('note', 'Последний штрих', 'Всё, что нам важно знать', array('notes')),
    );
}

/* подписи и подсказки для клиентов (на сайте). Названия для сотрудников — в словаре f_* */
function petgo_pf_meta() {
    return array(
        'title'         => array('Кличка', 'Например, Бублик'),
        'species'       => array('Кто это', ''),
        'species_other' => array('Какое животное', 'Например, кролик'),
        'avatar'        => array('Фото питомца', ''),
        'breed'         => array('Порода', 'Например, корги или метис'),
        'sex'           => array('Пол', ''),
        'age'           => array('Возраст', 'Например, 3 года'),
        'weight'        => array('Вес, кг', 'Например, 12'),
        'neutered'      => array('Кастрирован / стерилизована', ''),
        'vaccines'      => array('Прививки', 'Последняя — когда и какая'),
        'parasites'     => array('Обработка от паразитов', 'Когда и чем обрабатывали'),
        'health'        => array('Болезни и аллергии', 'На что обратить внимание'),
        'meds'          => array('Лекарства', 'Название, доза, время приёма'),
        'food'          => array('Корм и режим', 'Что ест, сколько и когда'),
        'toys'          => array('Игрушки и лакомства', 'Что любит больше всего'),
        'commands'      => array('Команды', 'Сидеть, рядом, ко мне…'),
        'people'        => array('С людьми', 'Дружелюбный, стесняется, лает…'),
        'animals'       => array('С животными', 'Как ведёт себя с собаками и кошками'),
        'fears'         => array('Страхи', 'Салюты, машины, громкие звуки…'),
        'leash'         => array('На прогулке', 'Тянет поводок, подбирает с земли?'),
        'owner'         => array('Ваше имя', 'Как к вам обращаться'),
        'phone'         => array('Телефон', '+7 700 000 00 00'),
        'address'       => array('Адрес', 'Улица, дом, квартира'),
        'access'        => array('Как попасть', 'Домофон, код, ключи, консьерж'),
        'emergency'     => array('Экстренный контакт', 'Имя и телефон близкого человека'),
        'vet'           => array('Ветклиника', 'Клиника и телефон врача'),
        'notes'         => array('Ещё о питомце', 'Привычки, особенности, пожелания'),
    );
}

/* тип поля: text, textarea, seg (варианты), photo */
function petgo_pf_type($k) {
    if ($k === 'title') {
        return array('text');
    }
    if ($k === 'avatar') {
        return array('photo');
    }
    $F = petgo_pet_fields();
    if (!isset($F[$k])) {
        return array('text');
    }
    return $F[$k][0] === 'select' ? array('seg', $F[$k][1]) : array($F[$k][0]);
}

function petgo_pf_opt_label($o, $site) {
    $m = array('dog' => 'Собака', 'cat' => 'Кошка', 'other' => 'Другой', 'boy' => 'Мальчик', 'girl' => 'Девочка', 'yes' => 'Да', 'no' => 'Нет');
    if ($site && function_exists('petgo_site_l')) {
        return petgo_site_l(isset($m[$o]) ? $m[$o] : $o);
    }
    return petgo_t('o_' . $o);
}

/* ---------- значки шагов (общий набор с сайтом и приложением) ---------- */
function petgo_pf_icon($n) {
    /* один набор значков на сайте и в приложении (см. petgo_cl_icon в includes/client.php) */
    return petgo_cl_icon($n, 'pgw-i');
}

function petgo_pf_phone($raw) {
    $d = preg_replace('/\D/', '', (string) $raw);
    if (strlen($d) === 11 && $d[0] === '8') {
        $d = '7' . substr($d, 1);
    }
    if (strlen($d) === 10) {
        $d = '7' . $d;
    }
    return (strlen($d) < 11 || strlen($d) > 15) ? '' : '+' . $d;
}

/* все поля анкеты из запроса */
function petgo_pf_collect() {
    $d = array('title' => mb_substr(sanitize_text_field((string) petgo_p('title')), 0, 60));
    foreach (petgo_pet_fields() as $k => $f) {
        $v     = petgo_p('f_' . $k);
        $v     = is_string($v) ? $v : '';
        $d[$k] = $f[0] === 'textarea' ? mb_substr(sanitize_textarea_field($v), 0, 1000) : mb_substr(sanitize_text_field($v), 0, 200);
        if ($f[0] === 'select' && !in_array($d[$k], $f[1], true)) {
            $d[$k] = '';
        }
    }
    if ($d['species'] !== 'other') {
        $d['species_other'] = '';
    }
    return $d;
}

/* создать или обновить питомца из данных анкеты */
function petgo_pf_save_pet($d, $pid, $av, $o = array()) {
    $title = isset($d['title']) ? (string) $d['title'] : '';
    if ($title === '') {
        return 0;
    }
    if ($pid && get_post_type($pid) === 'petgo_pet') {
        wp_update_post(array('ID' => $pid, 'post_title' => $title));
    } else {
        $pid = wp_insert_post(array('post_type' => 'petgo_pet', 'post_status' => 'publish', 'post_title' => $title));
        if (!$pid || is_wp_error($pid)) {
            return 0;
        }
        update_post_meta($pid, '_src', isset($o['src']) ? $o['src'] : 'app');
        update_post_meta($pid, '_created', time());
    }
    foreach (petgo_pet_fields() as $k => $f) {
        if (isset($d[$k])) {
            update_post_meta($pid, '_pf_' . $k, (string) $d[$k]);
        }
    }
    if (is_array($av) && !empty($av['url'])) {
        $old = get_post_meta($pid, '_avatar', true);
        if (is_array($old) && !empty($old['file'])) {
            petgo_rm($old['file']);
        }
        update_post_meta($pid, '_avatar', $av);
    }
    if (function_exists('petgo_pet_nick')) {
        petgo_pet_nick($pid);
    }
    $cl = isset($o['client']) ? (int) $o['client'] : 0;
    if (!$cl && function_exists('petgo_cl_by_phone') && !empty($d['phone'])) {
        $cl = petgo_cl_by_phone((string) $d['phone']);
    }
    if ($cl && !(int) get_post_meta($pid, '_client', true)) {
        update_post_meta($pid, '_client', $cl);
    }
    petgo_bump();
    return (int) $pid;
}

/* ============================================================
   ЗАЯВКА → ПИТОМЕЦ ОДНИМ НАЖАТИЕМ (раздел «Заявки» в приложении)
   ============================================================ */
add_action('admin_post_petgo_req_addpet', 'petgo_h_req_addpet');
function petgo_h_req_addpet() {
    petgo_guard('office');
    global $wpdb;
    $id = (int) petgo_p('id');
    $r  = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . petgo_tb('reqs') . ' WHERE id=%d', $id));
    if (!$r) {
        petgo_fail('err_na', 'requests');
    }
    if ((int) $r->pet_id && get_post_type((int) $r->pet_id) === 'petgo_pet') {
        petgo_flash(petgo_t('rq_pet_added'));
        petgo_back('requests');
    }
    $pf = (isset($r->pf) && $r->pf !== '') ? json_decode($r->pf, true) : null;
    $av = null;
    if (is_array($pf)) {
        $d  = $pf;
        $av = isset($pf['_avatar']) && is_array($pf['_avatar']) ? $pf['_avatar'] : null;
    } else {
        /* старая заявка: берём то, что есть */
        $pre = petgo_req_prefill($id);
        $d   = array();
        foreach (petgo_pet_fields() as $k => $f) {
            $d[$k] = isset($pre[$k]) ? (string) $pre[$k] : '';
        }
        $nick = '';
        $xt   = ($r->extra !== null && $r->extra !== '') ? json_decode($r->extra, true) : array();
        foreach ((array) $xt as $xp) {
            if (is_array($xp) && count($xp) === 2 && $xp[0] === 'Кличка') {
                $nick = (string) $xp[1];
            }
        }
        $d['title'] = $nick !== '' ? $nick : ($r->breed !== '' ? $r->breed : petgo_t('pet') . ' · ' . $r->name);
    }
    $d['title'] = sanitize_text_field((string) (isset($d['title']) ? $d['title'] : ''));
    foreach (petgo_pet_fields() as $k => $f) {
        $d[$k] = isset($d[$k]) ? ($f[0] === 'textarea' ? sanitize_textarea_field((string) $d[$k]) : sanitize_text_field((string) $d[$k])) : '';
    }
    $pid = petgo_pf_save_pet($d, 0, $av, array('src' => 'site'));
    if (!$pid) {
        petgo_fail('err_fields', 'requests');
    }
    $wpdb->update(petgo_tb('reqs'), array('status' => 'done', 'pet_id' => $pid), array('id' => $id), array('%s', '%d'), array('%d'));
    petgo_audit('pet_from_req', 'req=' . $id . ' pet=' . $pid);
    petgo_flash(petgo_t('pf_added'));
    petgo_back('requests', array('rs' => 'done'));
}

/* ============================================================
   ПРИЛОЖЕНИЕ: форма питомца — разделы, одинаковые строки, переключатели вместо списков
   ============================================================ */
function petgo_pf_app_form($pid, $pre, $fr = 0) {
    $val = function ($k) use ($pid, $pre) {
        if ($pid) {
            return $k === 'title' ? get_the_title($pid) : (string) get_post_meta($pid, '_pf_' . $k, true);
        }
        return isset($pre[$k]) ? (string) $pre[$k] : '';
    };
    $M  = petgo_pf_meta();
    $b  = petgo_form_open('pet_save', 'enctype="multipart/form-data" class="pgf"') . '<input type="hidden" name="pet_id" value="' . (int) $pid . '"><input type="hidden" name="from_req" value="' . (int) $fr . '">';
    if ($fr) {
        $b .= '<div class="pgc-tip">' . esc_html(petgo_t('rq_from')) . '</div>';
    }
    $name = $pid ? get_the_title($pid) : '';
    $b .= '<label class="pgf-av">' . petgo_av($pid ? petgo_pet_avatar_url($pid) : '', $name !== '' ? $name : '+', 'xl') . '<span>' . esc_html(petgo_t('av_change')) . '</span><input class="pgc-vh" type="file" name="avatar" accept="image/*" data-preview></label>';
    if ($pid && function_exists('petgo_pet_ids_html')) {
        $b .= petgo_pet_ids_html($pid);
    }
    foreach (petgo_pf_steps() as $sk => $st) {
        $rows = '';
        foreach ($st[3] as $k) {
            if ($k === 'avatar') {
                continue;
            }
            $t    = petgo_pf_type($k);
            $lab  = $k === 'title' ? petgo_t('pet_name') : petgo_t('f_' . $k);
            $nm   = $k === 'title' ? 'title' : 'f_' . $k;
            $v    = $val($k);
            $ph   = esc_attr(isset($M[$k]) ? $M[$k][1] : '');
            if ($t[0] === 'seg') {
                $rows .= '<div class="pgf-row pgf-rseg' . ($k === 'species' ? ' pgf-sp' : '') . '"><span>' . esc_html($lab) . '</span><div class="pgf-seg">';
                if ($k !== 'species') {
                    $rows .= '<label><input type="radio" name="' . $nm . '" value=""' . checked($v, '', false) . '><span>—</span></label>';
                }
                foreach ($t[1] as $o) {
                    $rows .= '<label><input type="radio" name="' . $nm . '" value="' . esc_attr($o) . '"' . checked($k === 'species' && $v === '' ? 'dog' : $v, $o, false) . '><span>' . esc_html(petgo_t('o_' . $o)) . '</span></label>';
                }
                $rows .= '</div></div>';
            } elseif ($t[0] === 'textarea') {
                $rows .= '<label class="pgf-row pgf-ra"><span>' . esc_html($lab) . '</span><textarea name="' . $nm . '" rows="2" placeholder="' . $ph . '">' . esc_textarea($v) . '</textarea></label>';
            } else {
                $type = $k === 'phone' ? 'tel' : 'text';
                $rows .= '<label class="pgf-row' . ($k === 'species_other' ? ' pgf-oth' : '') . '"><span>' . esc_html($lab) . '</span><input type="' . $type . '" name="' . $nm . '" value="' . esc_attr($v) . '" placeholder="' . $ph . '"' . ($k === 'title' ? ' required' : '') . '></label>';
            }
        }
        $b .= '<div class="pgf-sec"><div class="pgf-h">' . petgo_pf_icon($st[0]) . '<span>' . esc_html(petgo_pf_app_sec($sk)) . '</span></div><div class="pgf-list">' . $rows . '</div></div>';
    }
    $b .= '<button class="pgc-btn wide lg pgf-go" type="submit">' . esc_html(petgo_t('save')) . '</button></form>';
    return $b;
}

function petgo_pf_app_sec($k) {
    $m = array('pet' => 'pf_s_pet', 'about' => 'sec_pet', 'health' => 'sec_health', 'habits' => 'sec_food', 'behav' => 'sec_behav', 'owner' => 'pf_s_owner', 'final' => 'sec_other');
    return petgo_t($m[$k]);
}

/* ============================================================
   ССЫЛКИ-АНКЕТЫ: сотрудник создаёт ссылку в приложении и отправляет клиенту (WhatsApp и т. д.).
   Клиент заполняет анкету — питомец сразу появляется в базе.
   ============================================================ */
function petgo_inv_all() {
    $a = get_option('petgo_invites', array());
    return is_array($a) ? $a : array();
}

function petgo_inv_get($t) {
    if (!preg_match('/^[A-Za-z0-9]{16}$/', (string) $t)) {
        return null;
    }
    $a = petgo_inv_all();
    if (!isset($a[$t])) {
        return null;
    }
    $i = array_merge(array('t' => 0, 'by' => 0, 'note' => '', 'phone' => '', 'pet' => 0, 'cl' => 0), (array) $a[$t]);
    if (!$i['pet'] && time() - (int) $i['t'] > 45 * DAY_IN_SECONDS) {
        return null;
    }
    return $i;
}

function petgo_inv_url($t) {
    return add_query_arg('t', $t, function_exists('petgo_site_url') ? petgo_site_url('join') : home_url('/anketa/'));
}

function petgo_inv_done($t, $pid) {
    $a = petgo_inv_all();
    if (isset($a[$t])) {
        $a[$t]['pet']  = (int) $pid;
        $a[$t]['done'] = time();
        update_option('petgo_invites', $a, false);
    }
}

add_action('admin_post_petgo_inv_new', 'petgo_h_inv_new');
function petgo_h_inv_new() {
    petgo_guard('office');
    $a = petgo_inv_all();
    /* старые использованные и просроченные ссылки чистим, храним не больше 300 */
    foreach ($a as $k => $i) {
        if (time() - (int) $i['t'] > 90 * DAY_IN_SECONDS) {
            unset($a[$k]);
        }
    }
    $a = array_slice($a, -299, null, true);
    $t = wp_generate_password(16, false);
    $a[$t] = array('t' => time(), 'by' => get_current_user_id(), 'note' => mb_substr(sanitize_text_field((string) petgo_p('note')), 0, 80),
        'phone' => petgo_pf_phone((string) petgo_p('phone')), 'pet' => 0, 'cl' => (int) petgo_p('cl'));
    update_option('petgo_invites', $a, false);
    petgo_audit('invite_new', $a[$t]['note']);
    petgo_back('pets', array('inv' => $t));
}

add_action('admin_post_petgo_inv_del', 'petgo_h_inv_del');
function petgo_h_inv_del() {
    petgo_guard('office');
    $a = petgo_inv_all();
    unset($a[(string) petgo_p('t')]);
    update_option('petgo_invites', $a, false);
    petgo_flash(petgo_t('ok_deleted'));
    petgo_back('pets');
}

/* текст и ссылки «поделиться» для готовой ссылки-анкеты */
function petgo_inv_share($t) {
    $i    = petgo_inv_get($t);
    $url  = petgo_inv_url($t);
    $text = 'Здравствуйте! Это PetGo. Заполните, пожалуйста, анкету вашего питомца — это займёт пару минут: ' . $url;
    $ph   = $i && $i['phone'] !== '' ? preg_replace('/\D/', '', $i['phone']) : '';
    $wa   = 'https://wa.me/' . $ph . '?text=' . rawurlencode($text);
    $h    = '<div class="pgi-share"><div class="pgi-link"><span class="pgi-li">' . petgo_pf_icon('link') . '</span><span class="pgi-url">' . esc_html(preg_replace('~^https?://~', '', $url)) . '</span></div>';
    $h   .= '<a class="pgc-btn wide lg pgi-wa" target="_blank" rel="noopener" href="' . esc_url($wa) . '">' . petgo_pf_icon('wa') . '<span>' . esc_html(petgo_t('pf_send_wa')) . '</span></a>';
    $h   .= '<div class="pgi-btns"><button type="button" class="pgc-btn sec" data-copy="' . esc_attr($url) . '" data-done="' . esc_attr(petgo_t('pf_copied')) . '">' . petgo_pf_icon('copy') . '<span>' . esc_html(petgo_t('pf_copy')) . '</span></button>';
    $h   .= '<button type="button" class="pgc-btn sec" data-share="' . esc_attr($url) . '" data-text="' . esc_attr($text) . '">' . petgo_pf_icon('share') . '<span>' . esc_html(petgo_t('pf_share')) . '</span></button></div></div>';
    return $h;
}

/* окна: «Добавить питомца» (выбор), «Ссылка-анкета клиенту», «Ссылка готова» */
function petgo_inv_modal() {
    $ch = petgo_pf_icon('right');
    $c  = '<div class="pga-choose"><button type="button" class="pga-opt" data-open="pgc-m-inv" data-swap><span class="pga-oi">' . petgo_pf_icon('link') . '</span><span class="pga-ot"><b>' . esc_html(petgo_t('pf_add_link')) . '</b><small>' . esc_html(petgo_t('pf_add_link_s')) . '</small></span>' . $ch . '</button>';
    $c .= '<button type="button" class="pga-opt" data-open="pgc-m-pet" data-swap><span class="pga-oi">' . petgo_pf_icon('edit') . '</span><span class="pga-ot"><b>' . esc_html(petgo_t('pf_add_self')) . '</b><small>' . esc_html(petgo_t('pf_add_self_s')) . '</small></span>' . $ch . '</button></div>';
    echo petgo_modal('pgc-m-addpet', petgo_t('add_pet'), $c);
    $t = isset($_GET['inv']) ? sanitize_text_field(wp_unslash($_GET['inv'])) : '';
    if ($t !== '' && petgo_inv_get($t)) {
        $b = '<div class="pgi-ready"><span class="pgi-big">' . petgo_pf_icon('check') . '</span><b>' . esc_html(petgo_t('pf_inv_ok')) . '</b><p>' . esc_html(petgo_t('pf_inv_ready')) . '</p></div>' . petgo_inv_share($t);
        echo petgo_modal('pgc-m-invok', petgo_t('pf_inv_t'), $b, true);
    }
    $b  = '<div class="pgi-hero"><span class="pgi-hi">' . petgo_pf_icon('link') . '</span><p>' . esc_html(petgo_t('pf_inv_hint')) . '</p></div>' . petgo_form_open('inv_new', 'class="pgf"') . '<div class="pgf-list">';
    $b .= '<label class="pgf-row"><span>' . esc_html(petgo_t('pf_inv_for')) . '</span><input type="text" name="note" maxlength="80" placeholder="' . esc_attr(petgo_t('pf_inv_for_ph')) . '"></label>';
    $b .= '<label class="pgf-row"><span>' . esc_html(petgo_t('phone')) . '</span><input type="tel" name="phone" maxlength="20" inputmode="tel" placeholder="+7 700 000 00 00"></label></div><p class="mu pgi-note">' . esc_html(petgo_t('pf_inv_opt')) . '</p>';
    $b .= '<button class="pgc-btn wide lg" type="submit">' . esc_html(petgo_t('pf_inv_make')) . '</button></form>';
    echo petgo_modal('pgc-m-inv', petgo_t('pf_inv_t'), $b);
}

/* список ссылок-анкет на странице «Питомцы» */
function petgo_inv_list_html() {
    $a = petgo_inv_all();
    if (!$a) {
        return '';
    }
    $wait = 0;
    $rows = '';
    foreach (array_reverse($a, true) as $t => $i) {
        $i = petgo_inv_get($t);
        if (!$i) {
            continue;
        }
        if (!$i['pet']) {
            $wait++;
        }
        if ($i['pet'] && time() - (int) $i['t'] > 14 * DAY_IN_SECONDS) {
            continue;
        }
        $by    = get_userdata((int) $i['by']);
        $title = $i['note'] !== '' ? $i['note'] : ($i['phone'] !== '' ? $i['phone'] : petgo_t('pf_inv_noname'));
        $rows .= '<div class="pgi-row"><span class="pgi-st ' . ($i['pet'] ? 'ok' : 'wait') . '">' . petgo_pf_icon($i['pet'] ? 'check' : 'link') . '</span><div class="pgc-sm"><b>' . esc_html($title) . '</b><small>' . esc_html(petgo_ago((int) $i['t']) . ($by ? ' · ' . $by->display_name : '') . ' · ' . ($i['pet'] ? petgo_t('pf_inv_filled') . ': ' . get_the_title((int) $i['pet']) : petgo_t('pf_inv_wait'))) . '</small></div>';
        if (!$i['pet']) {
            $rows .= '<button type="button" class="pgc-btn sec sm" data-copy="' . esc_attr(petgo_inv_url($t)) . '" data-done="' . esc_attr(petgo_t('pf_copied')) . '">' . petgo_pf_icon('copy') . '</button>' . petgo_btn_form('inv_del', array('t' => $t), '×', 'pgc-btn sec sm', petgo_t('q_delete_inv'));
        }
        $rows .= '</div>';
    }
    if ($rows === '') {
        return '';
    }
    return '<details class="pgc-card pgi-list"' . ($wait ? ' open' : '') . '><summary><span class="pgi-li">' . petgo_pf_icon('link') . '</span><b>' . esc_html(petgo_t('pf_inv_list')) . '</b>' . ($wait ? '<i class="pgc-bd">' . $wait . '</i>' : '') . '</summary>' . $rows . '</details>';
}
