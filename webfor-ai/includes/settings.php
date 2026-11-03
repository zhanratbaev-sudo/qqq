<?php
/**
 * Настройки ассистента: ключ API, модель, база знаний, куда отправлять сложные вопросы.
 * «Обучение» — это база знаний + ответы владельца на вопросы, которые ассистент не смог решить сам.
 */
defined('ABSPATH') || exit;

function wfai_models() {
    return [
        'claude-haiku-5-5'  => 'Claude Haiku 5.5 — быстрая и недорогая (рекомендуем)',
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 — умнее, дороже',
        'claude-opus-5-5'   => 'Claude Opus 5.5 — самая сильная, дорогая',
    ];
}

function wfai_default_knowledge() {
    return <<<TXT
О компании
WebFor — команда, которая делает цифровые продукты для бизнеса и внедряет их в работу. Сайт: web.for.petgo.kz. Работаем по Казахстану.

Услуги
1. Сайты — любые: лендинг, сайт-визитка, корпоративный сайт, интернет-магазин и каталог с загрузкой товаров, сайт с онлайн-записью и личным кабинетом. Дизайн под бренд клиента, адаптация под телефоны, заявки, WhatsApp и звонок в один клик.
2. Мобильные приложения — для клиентов (запись, заказы, бонусы) и для сотрудников (задачи, заявки, чаты). iPhone и Android: установка из браузера (PWA) или публикация в App Store и Google Play. Push-уведомления.
3. AI-чат-боты для бизнеса — отвечают клиентам днём и ночью в WhatsApp, Instagram, Telegram и на сайте, знают услуги и цены компании, записывают клиентов, собирают заявки, сложные вопросы передают менеджеру. Ставятся и на уже существующий сайт.
4. Izi — всё в одном: сайт + CRM + приложение для сотрудников в одной системе. Заявки с сайта сразу попадают в CRM, есть чаты, календарь, роли и уведомления.
   - Vet.izi — Izi для ветклиник, груминга, зоомагазинов и всех, кто помогает животным: онлайн-запись, CRM клиентов и питомцев, календарь команды, приложение для сотрудников, чаты и роли. AI-ассистента в Vet.izi нет.
   - Izi — максимальный тариф для любого бизнеса: всё, что есть в Vet.izi (включая специфику ветклиник), плюс AI-ассистент, который отвечает клиентам и помогает разбирать заявки, и регистрация пользователей (личные кабинеты для клиентов).

Примеры работ
- PetGo (petgo.kz) — сайт с заявками и приложение для сотрудников (заявки, заказы, календарь, чаты, роли).
- Oscar Clinic (oscarclinic.kz) — сайт ветклиники.

Как работаем
1) Обсуждаем задачу. 2) Делаем дизайн и согласуем. 3) Разрабатываем, запускаем, обучаем команду. 4) Поддерживаем и добавляем функции.

Цены и сроки
Точную цену и сроки называем после короткого разговора — они зависят от задачи. Не называй конкретных сумм и сроков, если их нет в этой базе знаний.

Частые вопросы
- Можно начать с сайта и потом добавить CRM? Да.
- Нужно ли скачивать приложение из App Store? Нет, можно установить из браузера за пару минут.
- Поддерживаете после запуска? Да.
TXT;
}

function wfai_defaults() {
    return [
        'enabled'     => 1,
        'api_key'     => '',
        'model'       => 'claude-haiku-5-5',
        'name'        => 'Ассистент WebFor',
        'greeting'    => 'Здравствуйте! Я AI-ассистент WebFor. Спросите про сайты, приложения, чат-ботов или Izi — отвечу сразу. Сложный вопрос передам Нуржану.',
        'owner_name'  => 'Нуржан',
        'owner_email' => get_option('admin_email'),
        'knowledge'   => wfai_default_knowledge(),
        'per_hour'    => 30,
    ];
}

function wfai_opt($key) {
    $all = wp_parse_args((array) get_option('wfai_settings', []), wfai_defaults());
    return $all[$key] ?? '';
}

/** Ключ API: из настроек или из константы WFAI_ANTHROPIC_KEY в wp-config.php (так надёжнее). */
function wfai_api_key() {
    if (defined('WFAI_ANTHROPIC_KEY') && WFAI_ANTHROPIC_KEY) {
        return WFAI_ANTHROPIC_KEY;
    }
    return (string) wfai_opt('api_key');
}

/** Номера владельца: берём из плагина WebFor Site, если он установлен. */
function wfai_contacts() {
    $phone = function_exists('webfor_opt') ? webfor_opt('phone') : '';
    $wa = function_exists('webfor_opt') ? webfor_opt('whatsapp') : '';
    return ['phone' => $phone, 'whatsapp' => $wa, 'wa_href' => function_exists('webfor_wa_href') ? webfor_wa_href() : ''];
}

add_action('admin_menu', function () {
    add_menu_page('AI-ассистент', 'AI-ассистент', 'manage_options', 'wfai', 'wfai_settings_page', 'dashicons-format-chat', 4);
    add_submenu_page('wfai', 'Настройки ассистента', 'Настройки', 'manage_options', 'wfai', 'wfai_settings_page');
    add_submenu_page('wfai', 'Обучение', 'Обучение', 'manage_options', 'wfai-training', 'wfai_training_page');
});

add_action('admin_init', function () {
    register_setting('wfai', 'wfai_settings', ['type' => 'array', 'sanitize_callback' => 'wfai_sanitize']);
    register_setting('wfai_training', 'wfai_learned', ['type' => 'array', 'sanitize_callback' => 'wfai_sanitize_learned']);
});

function wfai_sanitize($in) {
    $in = (array) $in;
    $old = wp_parse_args((array) get_option('wfai_settings', []), wfai_defaults());
    $out = [
        'enabled'     => empty($in['enabled']) ? 0 : 1,
        // пустое поле ключа не стирает сохранённый ключ
        'api_key'     => trim((string) ($in['api_key'] ?? '')) !== '' ? sanitize_text_field($in['api_key']) : $old['api_key'],
        'model'       => isset(wfai_models()[$in['model'] ?? '']) ? $in['model'] : 'claude-haiku-5-5',
        'name'        => sanitize_text_field($in['name'] ?? ''),
        'greeting'    => sanitize_textarea_field($in['greeting'] ?? ''),
        'owner_name'  => sanitize_text_field($in['owner_name'] ?? ''),
        'owner_email' => sanitize_email($in['owner_email'] ?? ''),
        'knowledge'   => sanitize_textarea_field($in['knowledge'] ?? ''),
        'per_hour'    => max(5, min(200, (int) ($in['per_hour'] ?? 30))),
    ];
    if (!empty($in['forget_key'])) {
        $out['api_key'] = '';
    }
    return $out;
}

function wfai_sanitize_learned($in) {
    $out = [];
    foreach ((array) $in as $row) {
        $q = sanitize_textarea_field($row['q'] ?? '');
        $a = sanitize_textarea_field($row['a'] ?? '');
        if ($q !== '' && $a !== '' && empty($row['delete'])) {
            $out[] = ['q' => $q, 'a' => $a];
        }
    }
    return $out;
}

function wfai_settings_page() {
    $s = wp_parse_args((array) get_option('wfai_settings', []), wfai_defaults());
    $has_key = wfai_api_key() !== '';
    ?>
    <div class="wrap">
        <h1>AI-ассистент</h1>
        <p>Ассистент отвечает посетителям сайта по базе знаний. Если не уверен — не выдумывает, а передаёт вопрос вам: он появляется в разделе <a href="<?php echo esc_url(admin_url('edit.php?post_type=wfai_question')); ?>">«Вопросы»</a> и приходит на e-mail. Ваш ответ можно одной галочкой добавить в обучение.</p>
        <?php settings_errors(); ?>
        <form method="post" action="options.php">
            <?php settings_fields('wfai'); ?>
            <table class="form-table" role="presentation">
                <tr><th scope="row">Ассистент на сайте</th><td><label><input type="checkbox" name="wfai_settings[enabled]" value="1" <?php checked($s['enabled'], 1); ?>> Показывать окно чата</label></td></tr>
                <tr><th scope="row"><label for="wfai-key">Ключ Anthropic API</label></th><td>
                    <input id="wfai-key" class="regular-text" type="password" name="wfai_settings[api_key]" value="" autocomplete="off" placeholder="<?php echo $has_key ? 'Ключ сохранён — введите новый, чтобы заменить' : 'sk-ant-…'; ?>">
                    <?php if ($has_key && !defined('WFAI_ANTHROPIC_KEY')) : ?><label style="margin-left:8px"><input type="checkbox" name="wfai_settings[forget_key]" value="1"> удалить ключ</label><?php endif; ?>
                    <p class="description">Создайте ключ на console.anthropic.com → API Keys. Надёжнее хранить его в wp-config.php: <code>define('WFAI_ANTHROPIC_KEY', 'sk-ant-…');</code><?php echo defined('WFAI_ANTHROPIC_KEY') ? ' — сейчас используется ключ из wp-config.php.' : ''; ?></p>
                    <p><?php echo $has_key ? '✅ Ключ есть.' : '⚠️ Ключа нет — без него ассистент не отвечает сам, а сразу передаёт вопросы вам.'; ?></p></td></tr>
                <tr><th scope="row"><label for="wfai-model">Модель</label></th><td><select id="wfai-model" name="wfai_settings[model]">
                    <?php foreach (wfai_models() as $id => $label) : ?><option value="<?php echo esc_attr($id); ?>" <?php selected($s['model'], $id); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                    </select><p class="description">Для ответов клиентам хватает Haiku: она быстрая и стоит копейки. Сложное всё равно уходит вам.</p></td></tr>
                <tr><th scope="row"><label for="wfai-name">Имя ассистента</label></th><td><input id="wfai-name" class="regular-text" name="wfai_settings[name]" value="<?php echo esc_attr($s['name']); ?>"></td></tr>
                <tr><th scope="row"><label for="wfai-greet">Приветствие</label></th><td><textarea id="wfai-greet" class="large-text" rows="2" name="wfai_settings[greeting]"><?php echo esc_textarea($s['greeting']); ?></textarea></td></tr>
                <tr><th scope="row"><label for="wfai-owner">Кому передавать сложные вопросы</label></th><td>
                    <input id="wfai-owner" class="regular-text" name="wfai_settings[owner_name]" value="<?php echo esc_attr($s['owner_name']); ?>" placeholder="Имя">
                    <input class="regular-text" type="email" name="wfai_settings[owner_email]" value="<?php echo esc_attr($s['owner_email']); ?>" placeholder="E-mail для уведомлений">
                    <p class="description">Телефон и WhatsApp для клиентов берутся из WebFor → Настройки.</p></td></tr>
                <tr><th scope="row"><label for="wfai-kb">База знаний</label></th><td>
                    <textarea id="wfai-kb" class="large-text code" rows="22" name="wfai_settings[knowledge]"><?php echo esc_textarea($s['knowledge']); ?></textarea>
                    <p class="description">Всё, что ассистент знает о вас: услуги, цены, сроки, адреса, правила. Пишите обычным текстом. Чего здесь нет — ассистент не придумает и передаст вопрос вам.</p></td></tr>
                <tr><th scope="row"><label for="wfai-ph">Лимит сообщений</label></th><td><input id="wfai-ph" type="number" min="5" max="200" name="wfai_settings[per_hour]" value="<?php echo (int) $s['per_hour']; ?>"> в час с одного посетителя <p class="description">Защита от спама и лишних расходов.</p></td></tr>
            </table>
            <?php submit_button('Сохранить'); ?>
        </form>
    </div>
    <?php
}

function wfai_training_page() {
    $rows = (array) get_option('wfai_learned', []);
    ?>
    <div class="wrap">
        <h1>Обучение ассистента</h1>
        <p>Здесь ответы, которые ассистент выучил из ваших ответов на «Вопросы». Можно поправить текст, удалить или добавить новый пример вручную. Основная информация — в <a href="<?php echo esc_url(admin_url('admin.php?page=wfai')); ?>">базе знаний</a>.</p>
        <form method="post" action="options.php">
            <?php settings_fields('wfai_training'); ?>
            <table class="widefat striped" style="max-width:1100px">
                <thead><tr><th style="width:40%">Вопрос клиента</th><th>Правильный ответ</th><th style="width:80px">Удалить</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r) : ?>
                    <tr><td><textarea class="large-text" rows="3" name="wfai_learned[<?php echo (int) $i; ?>][q]"><?php echo esc_textarea($r['q']); ?></textarea></td>
                        <td><textarea class="large-text" rows="3" name="wfai_learned[<?php echo (int) $i; ?>][a]"><?php echo esc_textarea($r['a']); ?></textarea></td>
                        <td><input type="checkbox" name="wfai_learned[<?php echo (int) $i; ?>][delete]" value="1" aria-label="Удалить"></td></tr>
                <?php endforeach; ?>
                    <tr><td><textarea class="large-text" rows="3" name="wfai_learned[new][q]" placeholder="Новый вопрос"></textarea></td>
                        <td><textarea class="large-text" rows="3" name="wfai_learned[new][a]" placeholder="Ответ"></textarea></td><td></td></tr>
                </tbody>
            </table>
            <?php submit_button('Сохранить обучение'); ?>
        </form>
    </div>
    <?php
}
