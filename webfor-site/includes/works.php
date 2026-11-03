<?php
/**
 * Работы в папках «Сайты», «Мобильные приложения», «AI-чат-боты», «Izi».
 * Хранятся как записи webfor_work (WebFor → Работы): картинка, ссылка, метка, описание.
 * При первой активации создаются примеры с картинками из assets/works — их можно править, удалять и добавлять свои.
 */
defined('ABSPATH') || exit;

function webfor_folders() {
    return [
        'sites' => ['title' => 'Сайты', 'icon' => 'globe', 'text' => 'Лендинги, сайты-визитки, корпоративные сайты, интернет-магазины и сайты с онлайн-записью — под ваш дизайн и удобные на телефоне.'],
        'apps'  => ['title' => 'Мобильные приложения', 'icon' => 'device', 'text' => 'Приложения для клиентов и для сотрудников на iPhone и Android: запись, заказы, бонусы, задачи и чаты. Установка из браузера или App Store и Google Play.'],
        'bots'  => ['title' => 'AI-чат-боты', 'icon' => 'bot', 'text' => 'Искусственный интеллект отвечает клиентам днём и ночью в WhatsApp, Instagram, Telegram и на сайте, записывает и собирает заявки, сложное передаёт вам.'],
        'izi'   => ['title' => 'Izi — всё в одном', 'icon' => 'layers', 'text' => 'Сайт, CRM и приложение для команды в одной системе: заявки с сайта сразу в CRM, календарь, чаты, роли. Vet.izi — для ветклиник, Izi — для любого бизнеса, с AI-ассистентом.'],
    ];
}

function webfor_default_works() {
    $w = function ($folder, $title, $img, $tag, $desc, $url = '', $shape = 'wide', $action = '') {
        return compact('folder', 'title', 'img', 'tag', 'desc', 'url', 'shape', 'action');
    };
    return [
        $w('sites', 'PetGo', 'petgo-site.jpg', 'Наш проект', 'Сайт сервиса ухода за питомцами: услуги, цены, онлайн-заявка. Заявки сразу попадают в кабинет команды.', 'https://petgo.kz'),
        $w('sites', 'Oscar Clinic', 'oscarclinic.jpg', 'Наш проект', 'Сайт ветеринарной клиники.', 'https://oscarclinic.kz'),
        $w('apps', 'PetGo — приложение для сотрудников', 'petgo-app-home.jpg', 'Наш проект', 'Главный экран команды: заказы на сегодня, новые заявки с сайта, питомцы.', 'https://petgo.kz', 'phone'),
        $w('apps', 'PetGo — заказы', 'petgo-app-orders.jpg', 'Наш проект', 'Заказы со статусами и исполнителями, отметки «вовремя» и «опоздание».', 'https://petgo.kz', 'phone'),
        $w('bots', 'AI-ассистент на этом сайте', 'bot-site.jpg', 'Работает здесь', 'Отвечает на вопросы о наших услугах, а сложные передаёт нам. Попробуйте — кнопка «Спросить AI».', '', 'phone', 'chat'),
        $w('bots', 'Бот ветклиники в WhatsApp', 'bot-wa.jpg', 'Пример', 'Записывает на приём, называет цены, подсказывает, когда срочно звонить врачу.', '', 'phone'),
        $w('izi', 'Vet.izi — заявки', 'crm-requests.jpg', 'Пример: PetGo', 'Заявки с сайта сразу в CRM: контакт, питомец, кнопки WhatsApp и звонка.'),
        $w('izi', 'Vet.izi — главная', 'crm-home.jpg', 'Пример: PetGo', 'Заказы на сегодня, новые заявки и питомцы на одном экране.'),
        $w('izi', 'Vet.izi — календарь', 'crm-cal.jpg', 'Пример: PetGo', 'Календарь команды: кто, когда и к какому питомцу.'),
        $w('izi', 'Vet.izi — чаты', 'crm-chat.jpg', 'Пример: PetGo', 'Чаты с командой прямо в системе.'),
    ];
}

add_action('init', function () {
    register_post_type('webfor_work', [
        'labels' => ['name' => 'Работы', 'singular_name' => 'Работа', 'menu_name' => 'Работы', 'all_items' => 'Работы',
            'add_new' => 'Добавить работу', 'add_new_item' => 'Новая работа', 'edit_item' => 'Работа', 'featured_image' => 'Скриншот',
            'set_featured_image' => 'Загрузить скриншот', 'remove_featured_image' => 'Убрать скриншот', 'use_featured_image' => 'Использовать как скриншот'],
        'public' => false, 'show_ui' => true, 'show_in_menu' => 'webfor', 'supports' => ['title', 'thumbnail', 'page-attributes'],
    ]);
});

/** Встроенные концепты прошлых версий — удаляются один раз при обновлении (свои работы не трогаем). */
function webfor_cleanup_concepts() {
    if ((int) get_option('webfor_works_cleanup') >= 2) {
        return;
    }
    $old = ['concept-vet.jpg', 'concept-groom.jpg', 'concept-dent.jpg', 'concept-beauty.jpg', 'concept-auto.jpg', 'concept-cafe.jpg', 'app-groom.jpg', 'app-cafe.jpg', 'bot-ig.jpg'];
    foreach (get_posts(['post_type' => 'webfor_work', 'numberposts' => -1, 'post_status' => 'any', 'meta_key' => '_wf_img', 'meta_value' => $old, 'meta_compare' => 'IN']) as $p) {
        if (!has_post_thumbnail($p)) {
            wp_delete_post($p->ID, true);
        }
    }
    update_option('webfor_works_cleanup', 2);
}
add_action('admin_init', 'webfor_cleanup_concepts');
add_action('wp', 'webfor_cleanup_concepts');

/** Создаёт примеры работ один раз (при активации или после обновления плагина). */
function webfor_seed_works() {
    if (get_option('webfor_works_seeded')) {
        return;
    }
    update_option('webfor_works_cleanup', 2);
    foreach (webfor_default_works() as $i => $w) {
        $id = wp_insert_post(['post_type' => 'webfor_work', 'post_status' => 'publish', 'post_title' => $w['title'], 'menu_order' => $i]);
        if ($id && !is_wp_error($id)) {
            foreach (['folder', 'img', 'tag', 'desc', 'url', 'shape', 'action'] as $k) {
                update_post_meta($id, '_wf_' . $k, $w[$k]);
            }
        }
    }
    update_option('webfor_works_seeded', 1);
}
add_action('admin_init', 'webfor_seed_works');

/** Работы папки для вывода: [title, src, tag, desc, url, shape, action]. */
function webfor_works($folder) {
    $out = [];
    $q = get_posts(['post_type' => 'webfor_work', 'post_status' => 'publish', 'numberposts' => 60, 'orderby' => 'menu_order', 'order' => 'ASC',
        'meta_key' => '_wf_folder', 'meta_value' => $folder]);
    foreach ($q as $p) {
        $src = get_the_post_thumbnail_url($p, 'large');
        if (!$src) {
            $img = get_post_meta($p->ID, '_wf_img', true);
            $src = $img ? WEBFOR_URL . 'assets/works/' . rawurlencode($img) : '';
        }
        $out[] = ['title' => get_the_title($p), 'src' => $src, 'tag' => get_post_meta($p->ID, '_wf_tag', true), 'desc' => get_post_meta($p->ID, '_wf_desc', true),
            'url' => get_post_meta($p->ID, '_wf_url', true), 'shape' => get_post_meta($p->ID, '_wf_shape', true) ?: 'wide', 'action' => get_post_meta($p->ID, '_wf_action', true)];
    }
    // до первого входа в админку (или если записей нет) показываем встроенные примеры
    if (!$q && !get_option('webfor_works_seeded')) {
        foreach (webfor_default_works() as $w) {
            if ($w['folder'] === $folder) {
                $w['src'] = WEBFOR_URL . 'assets/works/' . $w['img'];
                $out[] = $w;
            }
        }
    }
    return $out;
}

/* ---------- поля работы в админке ---------- */

add_action('add_meta_boxes_webfor_work', function () {
    add_meta_box('wf_work', 'Где и как показывать', 'webfor_work_box', 'webfor_work', 'normal', 'high');
});

function webfor_work_box($post) {
    $m = fn($k) => get_post_meta($post->ID, '_wf_' . $k, true);
    wp_nonce_field('webfor_work', '_wf_work_nonce');
    $folder = $m('folder') ?: 'sites';
    ?>
    <table class="form-table" role="presentation">
        <tr><th><label for="wf-folder">Папка</label></th><td><select id="wf-folder" name="wf_folder">
            <?php foreach (webfor_folders() as $k => $f) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected($folder, $k); ?>><?php echo esc_html($f['title']); ?></option><?php endforeach; ?>
        </select></td></tr>
        <tr><th><label for="wf-shape">Вид скриншота</label></th><td><select id="wf-shape" name="wf_shape">
            <option value="wide" <?php selected($m('shape'), 'wide'); ?>>Сайт / компьютер (горизонтальный)</option>
            <option value="phone" <?php selected($m('shape'), 'phone'); ?>>Телефон (вертикальный)</option>
        </select><p class="description">Скриншот загрузите справа в блоке «Скриншот».</p></td></tr>
        <tr><th><label for="wf-tag">Метка</label></th><td><input id="wf-tag" class="regular-text" name="wf_tag" value="<?php echo esc_attr($m('tag')); ?>" placeholder="Наш проект / Концепт"></td></tr>
        <tr><th><label for="wf-desc">Описание</label></th><td><textarea id="wf-desc" class="large-text" rows="3" name="wf_desc"><?php echo esc_textarea($m('desc')); ?></textarea></td></tr>
        <tr><th><label for="wf-url">Ссылка</label></th><td><input id="wf-url" class="regular-text" type="url" name="wf_url" value="<?php echo esc_attr($m('url')); ?>" placeholder="https://"></td></tr>
    </table>
    <p class="description">Порядок в папке — поле «Порядок» в блоке «Атрибуты» (меньше — раньше).</p>
    <?php
}

add_action('save_post_webfor_work', function ($id) {
    if (!isset($_POST['_wf_work_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wf_work_nonce'])), 'webfor_work') || !current_user_can('edit_post', $id)) {
        return;
    }
    $folder = sanitize_key(wp_unslash($_POST['wf_folder'] ?? 'sites'));
    update_post_meta($id, '_wf_folder', isset(webfor_folders()[$folder]) ? $folder : 'sites');
    update_post_meta($id, '_wf_shape', ($_POST['wf_shape'] ?? '') === 'phone' ? 'phone' : 'wide');
    update_post_meta($id, '_wf_tag', sanitize_text_field(wp_unslash($_POST['wf_tag'] ?? '')));
    update_post_meta($id, '_wf_desc', sanitize_textarea_field(wp_unslash($_POST['wf_desc'] ?? '')));
    update_post_meta($id, '_wf_url', esc_url_raw(wp_unslash($_POST['wf_url'] ?? '')));
});

add_filter('manage_webfor_work_posts_columns', fn() => ['cb' => '<input type="checkbox">', 'wf_thumb' => '', 'title' => 'Работа', 'wf_folder' => 'Папка', 'wf_tag' => 'Метка']);
add_action('manage_webfor_work_posts_custom_column', function ($col, $id) {
    if ($col === 'wf_thumb') {
        $src = get_the_post_thumbnail_url($id, 'thumbnail') ?: (get_post_meta($id, '_wf_img', true) ? WEBFOR_URL . 'assets/works/' . get_post_meta($id, '_wf_img', true) : '');
        if ($src) {
            echo '<img src="' . esc_url($src) . '" alt="" style="width:64px;height:44px;object-fit:cover;object-position:top;border-radius:4px">';
        }
    } elseif ($col === 'wf_folder') {
        $f = webfor_folders()[get_post_meta($id, '_wf_folder', true)] ?? null;
        echo esc_html($f ? $f['title'] : '—');
    } elseif ($col === 'wf_tag') {
        echo esc_html(get_post_meta($id, '_wf_tag', true));
    }
}, 10, 2);
