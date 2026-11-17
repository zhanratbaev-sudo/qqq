<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   НАСТРОЙКИ САЙТА — раздел приложения (кабинета) для владельца и админа.
   ПК: левое меню → «Настройки сайта». Телефон: аватарка → «Настройки сайта» (или Настройки → «Настройки сайта»).
   Вкладки: Контакты (номера), Первый экран (тексты, фото, видео, таймеры), Услуги и цены (скидка), Прочее.
   Выглядит как остальное приложение и меняет цвета вместе с темой (тёмная / светлая).
   Обучение помощника (ИИ) — в отдельном плагине «PetGo ИИ»: WordPress → Настройки → PetGo ИИ.
   Редактор — assets/site-editor.js.
   ============================================================ */

/* сохранить то, что пришло из редактора; true — сохранено */
function petgo_site_save_post() {
    $old = petgo_site_cfg(true);
    $raw = isset($_POST['pgs_json']) ? (string) wp_unslash($_POST['pgs_json']) : '';
    $j   = (strlen($raw) > 2 && strlen($raw) < 400000) ? json_decode($raw, true) : null;
    if (!is_array($j)) {
        return false;
    }
    $n = petgo_site_clean($j, $old);
    /* код счётчиков: только для тех, кому WordPress разрешает вставлять свой HTML */
    if (isset($_POST['head']) && current_user_can('unfiltered_html')) {
        $n['head'] = trim((string) wp_unslash($_POST['head']));
    }
    update_option('petgo_site', $n, false);
    petgo_site_cfg(true);
    petgo_audit('site_save');
    return true;
}

function petgo_site_editor_data() {
    $c = petgo_site_cfg(true);
    unset($c['head']);
    $pages = array();
    foreach (petgo_site_pages() as $k => $p) {
        $pages[] = array('name' => $p['name'], 'url' => petgo_site_url($k));
    }
    return array(
        'cfg'    => $c,
        'arts'   => petgo_site_arts(),
        'pages'  => $pages,
        'upload' => array('url' => petgo_rel_url(admin_url('admin-ajax.php')), 'n' => wp_create_nonce('petgo_site_up'), 'max' => (int) wp_max_upload_size()),
    );
}

/* страница «Настройки сайта» в приложении */
function petgo_page_site() {
    if (!petgo_can('admin')) {
        echo '<p class="mu">' . esc_html(petgo_t('err_perm')) . '</p>';
        return;
    }
    $c  = petgo_site_cfg(true);
    $js = (string) @file_get_contents(dirname(__DIR__) . '/assets/site-editor.js');
    echo '<div class="pgc-full pgs-wrap"><i class="pgc-fe" id="pgs-dirty" style="display:none"></i>';
    echo '<div class="pgc-row pgs-top"><p class="mu">Всё, что видит клиент на сайте. Изменения появляются сразу после сохранения.</p><a class="pgc-btn sec sm" href="' . esc_url(home_url('/')) . '" target="_blank" rel="noopener" data-ext>Открыть сайт</a></div>';
    echo petgo_form_open('site_save', 'id="pgs-form"') . '<input type="hidden" name="pgs_json" id="pgs-json" value="">';
    echo '<div id="pgs-app"><div class="pgc-load"><i class="pgc-spin"></i></div></div>';
    $ro = current_user_can('unfiltered_html') ? '' : ' readonly';
    echo '<label class="pgc-f pgs-fld" id="pgs-head" hidden><span>Яндекс Метрика, Google Analytics</span><textarea name="head" rows="4" class="pgs-code"' . $ro . '>' . esc_textarea($c['head']) . '</textarea>';
    echo '<small class="pgs-hint">Код вставляется на все страницы сайта PetGo.' . ($ro ? ' Менять его может только владелец сайта.' : '') . '</small></label>';
    echo '<div class="pgs-save"><button type="submit" class="pgc-btn wide lg">Сохранить</button></div></form>';
    echo '<script>window.PGS=' . wp_json_encode(petgo_site_editor_data(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
    /* app.js выполняет только встроенные скрипты, поэтому редактор вставляется целиком */
    echo '<script>' . $js . '</script></div>';
}

add_action('admin_post_petgo_site_save', 'petgo_h_site_save');
function petgo_h_site_save() {
    petgo_guard('admin');
    if (petgo_site_save_post()) {
        petgo_flash('Сохранено. Изменения уже на сайте.');
    } else {
        petgo_flash('Не получилось сохранить: данные пришли не полностью. Попробуйте ещё раз.', false);
    }
    petgo_back('site');
}

/* ---------- загрузка фото и видео из редактора (в медиатеку WordPress, они публичные) ---------- */
add_action('wp_ajax_petgo_site_upload', 'petgo_site_upload');
function petgo_site_upload() {
    if (!check_ajax_referer('petgo_site_up', 'n', false)) {
        wp_send_json(array('ok' => false, 'm' => 'Страница устарела. Обновите её и попробуйте ещё раз.'));
    }
    if (!petgo_can('admin')) {
        wp_send_json(array('ok' => false, 'm' => 'Нет доступа.'));
    }
    if (empty($_FILES['f']) || !empty($_FILES['f']['error'])) {
        $big = !empty($_FILES['f']['error']) && in_array((int) $_FILES['f']['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true);
        wp_send_json(array('ok' => false, 'm' => $big ? 'Файл слишком большой для сервера. Можно не больше ' . size_format(wp_max_upload_size()) . '.' : 'Файл не дошёл. Попробуйте ещё раз.'));
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $mimes = array(
        'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'mp4|m4v' => 'video/mp4', 'webm' => 'video/webm',
    );
    $id = media_handle_upload('f', 0, array(), array('test_form' => false, 'mimes' => $mimes));
    if (is_wp_error($id)) {
        wp_send_json(array('ok' => false, 'm' => 'Не получилось загрузить: ' . wp_strip_all_tags($id->get_error_message()) . ' Подходят фото JPG, PNG, WEBP и видео MP4, WEBM.'));
    }
    wp_send_json(array('ok' => true, 'url' => wp_get_attachment_url($id)));
}

/* адрес без схемы и домена: «/wp-admin/admin-ajax.php». Так запросы не ломаются, если сайт за Cloudflare
   и WordPress думает, что он на http, а открыт по https (браузер блокирует такие запросы) */
function petgo_rel_url($u) {
    $p = wp_parse_url($u);
    if (!$p || empty($p['path'])) {
        return $u;
    }
    return $p['path'] . (isset($p['query']) ? '?' . $p['query'] : '');
}
