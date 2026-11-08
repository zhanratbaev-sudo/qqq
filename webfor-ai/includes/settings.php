<?php
/**
 * Настройки ассистента: ключ API, модель, база знаний, куда отправлять сложные вопросы.
 * «Обучение» — это база знаний + ответы владельца на вопросы, которые ассистент не смог решить сам.
 */
defined('ABSPATH') || exit;

/** Модели Gemini (Google). Список подсказок: Google часто выпускает новые, поэтому в настройках можно вписать и свою. */
function wfai_gemini_models() {
    return [
        'gemini-3.1-flash-lite'  => 'Gemini 3.1 Flash-Lite — быстрая, есть бесплатный лимит (рекомендуем)',
        'gemini-3-flash-preview' => 'Gemini 3 Flash (preview) — умнее, лимит меньше',
        'gemini-2.5-flash'       => 'Gemini 2.5 Flash',
    ];
}

/** Какой сервис отвечает клиентам: gemini или claude. */
function wfai_provider() {
    return wfai_opt('provider') === 'claude' ? 'claude' : 'gemini';
}

/** Модель выбранного сервиса — для подписи в админке. */
function wfai_current_model() {
    return wfai_provider() === 'claude' ? (wfai_opt('model') ?: 'claude-haiku-5-5') : (wfai_opt('gemini_model') ?: 'gemini-3.1-flash-lite');
}

function wfai_models() {
    return [
        'claude-haiku-5-5'  => 'Claude Haiku 5.5 — быстрая и недорогая (рекомендуем)',
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 — умнее, дороже',
        'claude-opus-5-5'   => 'Claude Opus 5.5 — самая сильная, дорогая',
    ];
}

function wfai_default_knowledge() {
    return <<<TXT
О нас
WebFor — команда, которая делает цифровые продукты для бизнеса и внедряет их в работу. Работаем больше 3 лет, онлайн, с клиентами по всему миру; находимся в Алматы, офиса нет. Работаем и с крупными компаниями, и с госкомпаниями.
Коротко о нас: «Делаем сайты, приложения и AI-ботов, которые приносят бизнесу клиентов и экономят время».
Отвечаем с 10:00 до 18:00 (время Алматы). Связь — WhatsApp. Портфолио — на нашем сайте web.for.petgo.kz, в разделе «Что мы делаем».
Не называй имён и не говори, сколько человек в команде, — просто «команда WebFor».

Почему мы (наши преимущества)
- Быстро: большинство проектов — за 3–7 дней.
- Доступно: лендинг от 10 000 ₸, корпоративный сайт с AI-ассистентом — от 30 000 ₸.
- Тестовая неделя после запуска: мелкие правки и ошибки — бесплатно.
- Гарантия 1 год, бесплатное обучение сотрудников, договор по желанию клиента.
- Всё в одном месте: сайт, приложение, CRM и AI-бот, которые работают вместе.

Цены (все цены «от», точную сумму называем после обсуждения задачи)
Сайты
- Сайт-визитка (одна страница) — 10 000 ₸, домен клиента.
- Лендинг — 10 000–15 000 ₸.
- Сайт с онлайн-записью — от 15 000 ₸.
- Корпоративный сайт — от 30 000 ₸: все нужные страницы и AI-ассистент на сайте.
- Сайт с личным кабинетом клиента — от 30 000 ₸.
- Интернет-магазин — от 30 000 ₸. Если товары даёт клиент в Excel в нужном формате (с фото по требованиям) — загружаем сразу, без доплаты. Если товары нужно вносить вручную — каждый дополнительный рабочий день добавления товаров 30 000 ₸. Также можем обучить сотрудника добавлять товары самому.
- Многоязычный сайт: автоматический перевод (Google) — бесплатно; отдельные страницы с ручным переводом — дороже, зависит от числа страниц.
- Редизайн готового сайта — около 3 дней, цену называем после просмотра сайта.
- Доработка сайта (добавить форму, страницу, раздел) и правки — от 10 000 ₸, зависит от объёма.
- Логотип — 10 000 ₸.
- Фотосессия для сайта — 20 000 ₸ (полная съёмка).
Приложения
- Приложение, которое ставится через браузер (PWA, мы поможем установить), — от 30 000 ₸; работает и на iPhone, и на Android, есть push-уведомления.
- Приложение в App Store и Google Play — от 95 000 ₸ (с учётом публикации в магазинах).
- Приложение для клиентов (запись, бонусы) — от 50 000 ₸.
AI-чат-боты
- AI-ассистент на сайт — 30 000 ₸.
- Бот в WhatsApp или Telegram — 30 000 ₸.
- Бот сразу на все площадки (сайт, WhatsApp, Telegram, Instagram) — 40 000 ₸.
- Ежемесячно за работу бота — 10 000 ₸.
Izi
- Vet.izi — от 30 000 ₸, оплата разовая.
- Izi — от 50 000 ₸, оплата разовая.
- CRM отдельно, без сайта, не делаем — она входит в Vet.izi / Izi.
Хостинг и домен
- Хостинг входит в стоимость на 1 год, дальше — продление раз в год.
- Домен не входит: покупается на hoster.kz по их цене. Если у клиента есть домен — перенесём на наш хостинг бесплатно. Можем работать и на хостинге клиента.
- SSL (https), безопасность и резервные копии — бесплатно.
Валюта
- Для клиентов не из Казахстана можно назвать цену в долларах по текущему курсу, предупредив, что к моменту оплаты курс может измениться.

Срочные заказы
Если клиенту нужно срочно (быстрее обычного срока или начать в тот же день) — цена выше. Называй сразу итоговую срочную цену: обычная цена × 1,3, округлённая вверх до красивого числа (например, лендинг 15 000 ₸ → срочно 20 000 ₸, сайт 30 000 ₸ → срочно 40 000 ₸). Не объясняй, из чего она сложилась.

Сроки
- Лендинг — 1–3 дня. Корпоративный сайт — 3–7 дней. Магазин — 3–7 дней (если товары вносятся вручную — дольше).
- Приложение через браузер — от 3 дней; в App Store и Google Play — от 1 до 3 недель.
- Бот — 3–7 дней, обучение бота под бизнес — 1–2 недели. Vet.izi и Izi — 3–7 дней.
- Можем начать в день обращения, если позволяет загрузка (это срочный заказ).

Оплата
- 50% — предоплата, остальные 50% — после завершения, тестовой недели и проверки.
- Способы: счёт в Kaspi или перевод на карту Freedom Bank. Работаем с ТОО и ИП; с НДС — тоже возможно.
- Оплата частями — до 3 месяцев. На это время материалы, хостинг и домен остаются у нас и передаются после полной оплаты.
- Возврат: если работа ещё не начата — возвращаем полностью; если клиент отказался в процессе — возвращаем 50%.
- Бесплатной пробной версии нет: 50% предоплата, тестовая неделя, затем остальные 50%.

Скидки
- Сейчас акций нет, но предложение можем подобрать под клиента.
- Скидку до 30% можно обсудить при большом заказе, для студентов и новых бизнесов. Скидка действует при условии, что данные правдивы, — если окажется иначе, скидка отменяется.
- Решение о скидке принимает менеджер: всегда передавай просьбу о скидке менеджеру (escalate_to_owner) с объяснением ситуации клиента, а клиенту скажи, что скидку возможно обсудить и менеджер ответит.

Как работаем
1) Бесплатная консультация. Всё можно обсудить в WhatsApp, без созвонов.
2) Клиент даёт техническое задание и материалы: тексты, фото, логотип (если нет — сделаем логотип за 10 000 ₸; фото без своих — подберём бесплатные или сделаем фотосессию). Без ТЗ сайт и приложение не делаем, тексты пишет клиент.
3) Дизайн и разработка; по просьбе показываем промежуточные результаты.
4) Запуск, бесплатное обучение и тестовая неделя: небольшие правки и ошибки — бесплатно (без кардинальных переделок). После — правки от 10 000 ₸.
5) Гарантия — 1 год. Договор подписываем при большой сумме или по желанию клиента.
6) Возможна ежемесячная поддержка «под ключ» — условия и цену обсуждает менеджер.

Технически
- Сайты делаем на коде и нужных платформах и плагинах; быстрые и удобные на телефоне.
- Менять сайт сам клиент не может: правки делаем мы. Если клиент сам что-то сломает — ответственность на нём.
- Подключаем онлайн-оплату, интеграции (1С, учётные системы и др. — по задаче), кнопки WhatsApp, Instagram, карты 2ГИС и Google.
- Рекламу и продвижение (SEO, таргет) не делаем.
- Собственность: после полной оплаты сайт принадлежит клиенту — передаём логины и пароли. Если сайт на нашем хостинге, он размещён у нас, но у клиента есть доступ. Клиент может перейти к другому разработчику.

AI-чат-боты подробно
- Отвечают 24/7 на казахском, русском и английском; понимают текст (голосовые — нет).
- Могут записывать клиентов в календарь и принимать оплату — подключаем по задаче.
- Владелец может дообучать бота сам в админке. Если бот ошибся из-за наших настроек — исправляем мы; если после правок владельца — отвечает владелец.
- При большом потоке клиентов может понадобиться более мощная AI-модель (дороже).

Vet.izi / Izi подробно
- Любое число сотрудников, несколько филиалов, работает на компьютере и телефоне.
- Медкарты и история приёмов питомцев, склад и лекарства, онлайн-оплата и чеки, отчёты для владельца, напоминания клиентам по почте.
- Можно перенести базу клиентов из Excel или старой системы, если она подходит под требования системы.
- Izi подходит любому бизнесу: салоны, автосервисы, кафе, стоматологии и др. — клиент сам выбирает нужные модули.

Чего не делаем
Игры и рекламу/продвижение не делаем. Сложные маркетплейсы — делаем, цену называет менеджер.

Примеры работ
- PetGo (petgo.kz) — сайт с заявками и приложение для сотрудников.
- Oscar Clinic (oscarclinic.kz) — сайт ветклиники.
Можно давать ссылки на эти сайты. Отзывов пока не приводи.
TXT;
}

/** Хэши встроенных баз знаний прошлых версий: такие заменяются новой автоматически (свои правки владельца не трогаем). */
function wfai_old_knowledge_hashes() {
    return ['cca24bb530b03a246b2ba2a2c3b50c54'];
}

function wfai_defaults() {
    return [
        'enabled'     => 1,
        'provider'     => 'gemini',
        'gemini_key'   => '',
        'gemini_model' => 'gemini-3.1-flash-lite',
        'api_key'     => '',
        'model'       => 'claude-haiku-5-5',
        'name'        => 'Ассистент WebFor',
        'greeting'    => 'Здравствуйте! Я AI-ассистент WebFor. Спросите про сайты, приложения, чат-ботов, Izi, цены и сроки — отвечу сразу.',
        'teaser'      => 'Здравствуйте! 👋 Я AI-ассистент WebFor — искусственный интеллект. Отвечу на любой вопрос сразу, днём и ночью.',
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

/** Ключ выбранного сервиса: из настроек или из wp-config.php (WFAI_GEMINI_KEY / WFAI_ANTHROPIC_KEY — так надёжнее). */
function wfai_api_key() {
    if (wfai_provider() === 'gemini') {
        return defined('WFAI_GEMINI_KEY') && WFAI_GEMINI_KEY ? WFAI_GEMINI_KEY : (string) wfai_opt('gemini_key');
    }
    if (defined('WFAI_ANTHROPIC_KEY') && WFAI_ANTHROPIC_KEY) {
        return WFAI_ANTHROPIC_KEY;
    }
    return (string) wfai_opt('api_key');
}

/** Ключ Google (Gemini) узнаём по началу «AIza» — если его вставили в поле Claude, переносим куда надо. */
function wfai_is_gemini_key($k) {
    return strpos(trim((string) $k), 'AIza') === 0;
}
add_action('init', function () {
    $s = get_option('wfai_settings');
    if (!is_array($s)) {
        return;
    }
    if (wfai_is_gemini_key($s['api_key'] ?? '')) {
        $s['gemini_key'] = trim($s['api_key']);
        $s['api_key'] = '';
        $s['provider'] = 'gemini';
        update_option('wfai_settings', $s);
    } elseif (!isset($s['provider']) && (trim((string) ($s['api_key'] ?? '')) !== '' || (defined('WFAI_ANTHROPIC_KEY') && WFAI_ANTHROPIC_KEY))) {
        $s['provider'] = 'claude'; // сайт уже работал с ключом Claude — после обновления ничего не ломаем
        update_option('wfai_settings', $s);
    }
}, 5);

/** Номера владельца: берём из плагина WebFor Site, если он установлен. */
function wfai_contacts() {
    $phone = function_exists('webfor_opt') ? webfor_opt('phone') : '';
    $wa = function_exists('webfor_opt') ? webfor_opt('whatsapp') : '';
    return ['phone' => $phone, 'whatsapp' => $wa, 'wa_href' => function_exists('webfor_wa_href') ? webfor_wa_href() : ''];
}

/** Заменяет встроенную базу знаний прошлой версии на новую, если владелец её не правил. */
add_action('init', function () {
    if ((int) get_option('wfai_kb_v') >= 3) {
        return;
    }
    $s = get_option('wfai_settings');
    if (is_array($s)) {
        $kb = trim((string) ($s['knowledge'] ?? ''));
        if ($kb === '' || strpos($kb, 'Работаем по Казахстану.') !== false || in_array(md5($kb), wfai_old_knowledge_hashes(), true)) {
            $s['knowledge'] = wfai_default_knowledge();
        }
        if (isset($s['greeting']) && strpos($s['greeting'], 'Нуржан') !== false) {
            $s['greeting'] = wfai_defaults()['greeting'];
        }
        if (($s['owner_name'] ?? '') === '') {
            $s['owner_name'] = 'Нуржан';
        }
        update_option('wfai_settings', $s);
    }
    update_option('wfai_kb_v', 3);
});

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
    $gm = trim((string) ($in['gemini_model'] ?? ''));
    $out = [
        'enabled'     => empty($in['enabled']) ? 0 : 1,
        'provider'     => ($in['provider'] ?? '') === 'claude' ? 'claude' : 'gemini',
        'gemini_key'   => trim((string) ($in['gemini_key'] ?? '')) !== '' ? sanitize_text_field($in['gemini_key']) : $old['gemini_key'],
        'gemini_model' => preg_match('/^gemini-[a-z0-9.\-]{1,60}$/', $gm) ? $gm : 'gemini-3.1-flash-lite',
        // пустое поле ключа не стирает сохранённый ключ
        'api_key'     => trim((string) ($in['api_key'] ?? '')) !== '' ? sanitize_text_field($in['api_key']) : $old['api_key'],
        'model'       => isset(wfai_models()[$in['model'] ?? '']) ? $in['model'] : 'claude-haiku-5-5',
        'name'        => sanitize_text_field($in['name'] ?? ''),
        'greeting'    => sanitize_textarea_field($in['greeting'] ?? ''),
        'teaser'      => sanitize_textarea_field($in['teaser'] ?? ''),
        'owner_name'  => sanitize_text_field($in['owner_name'] ?? ''),
        'owner_email' => sanitize_email($in['owner_email'] ?? ''),
        'knowledge'   => sanitize_textarea_field($in['knowledge'] ?? ''),
        'per_hour'    => max(5, min(200, (int) ($in['per_hour'] ?? 30))),
    ];
    if (!empty($in['forget_key'])) {
        $out['api_key'] = '';
    }
    if (!empty($in['forget_gemini_key'])) {
        $out['gemini_key'] = '';
    }
    if (wfai_is_gemini_key($out['api_key'])) { // ключ Google вставили в поле Claude
        $out['gemini_key'] = $out['api_key'];
        $out['api_key'] = '';
        $out['provider'] = 'gemini';
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
                <tr><th scope="row">Кто отвечает клиентам</th><td>
                    <label><input type="radio" name="wfai_settings[provider]" value="gemini" <?php checked($s['provider'] !== 'claude'); ?>> Gemini (Google) — есть бесплатный лимит</label><br>
                    <label><input type="radio" name="wfai_settings[provider]" value="claude" <?php checked($s['provider'], 'claude'); ?>> Claude (Anthropic) — платно, лучше держит правила</label>
                    <p><?php echo $has_key ? '✅ Ключ для выбранного сервиса есть.' : '⚠️ Для выбранного сервиса нет ключа — ассистент не отвечает сам, а сразу передаёт вопросы вам.'; ?></p></td></tr>
                <tr><th scope="row"><label for="wfai-gkey">Ключ Gemini API</label></th><td>
                    <?php $has_g = (defined('WFAI_GEMINI_KEY') && WFAI_GEMINI_KEY) || $s['gemini_key'] !== ''; ?>
                    <input id="wfai-gkey" class="regular-text" type="password" name="wfai_settings[gemini_key]" value="" autocomplete="off" placeholder="<?php echo $has_g ? 'Ключ сохранён — введите новый, чтобы заменить' : 'AIza…'; ?>">
                    <?php if ($has_g && !defined('WFAI_GEMINI_KEY')) : ?><label style="margin-left:8px"><input type="checkbox" name="wfai_settings[forget_gemini_key]" value="1"> удалить ключ</label><?php endif; ?>
                    <p class="description">Бесплатно: aistudio.google.com → Get API key. Бесплатный лимит — несколько десятков или сотен ответов в день, Google его меняет; когда он кончится, вопросы на сегодня уйдут вам.</p></td></tr>
                <tr><th scope="row"><label for="wfai-gmodel">Модель Gemini</label></th><td>
                    <input id="wfai-gmodel" class="regular-text" list="wfai-gmodels" name="wfai_settings[gemini_model]" value="<?php echo esc_attr($s['gemini_model']); ?>">
                    <datalist id="wfai-gmodels"><?php foreach (wfai_gemini_models() as $id => $label) : ?><option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></datalist>
                    <p class="description">По умолчанию <code>gemini-3.1-flash-lite</code>. Если Google выпустит новую модель, впишите её название (начинается с gemini-).</p></td></tr>
                <tr><th scope="row"><label for="wfai-key">Ключ Anthropic API (Claude)</label></th><td>
                    <?php $has_c = (defined('WFAI_ANTHROPIC_KEY') && WFAI_ANTHROPIC_KEY) || $s['api_key'] !== ''; ?>
                    <input id="wfai-key" class="regular-text" type="password" name="wfai_settings[api_key]" value="" autocomplete="off" placeholder="<?php echo $has_c ? 'Ключ сохранён — введите новый, чтобы заменить' : 'sk-ant-…'; ?>">
                    <?php if ($has_c && !defined('WFAI_ANTHROPIC_KEY')) : ?><label style="margin-left:8px"><input type="checkbox" name="wfai_settings[forget_key]" value="1"> удалить ключ</label><?php endif; ?>
                    <p class="description">Нужен, только если выбран Claude: console.anthropic.com → API Keys.</p></td></tr>
                <tr><th scope="row"><label for="wfai-model">Модель Claude</label></th><td><select id="wfai-model" name="wfai_settings[model]">
                    <?php foreach (wfai_models() as $id => $label) : ?><option value="<?php echo esc_attr($id); ?>" <?php selected($s['model'], $id); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                    </select></td></tr>
                <tr><th scope="row"><label for="wfai-name">Имя ассистента</label></th><td><input id="wfai-name" class="regular-text" name="wfai_settings[name]" value="<?php echo esc_attr($s['name']); ?>"></td></tr>
                <tr><th scope="row"><label for="wfai-greet">Приветствие</label></th><td><textarea id="wfai-greet" class="large-text" rows="2" name="wfai_settings[greeting]"><?php echo esc_textarea($s['greeting']); ?></textarea></td></tr>
                <tr><th scope="row"><label for="wfai-teaser">Сообщение при входе</label></th><td><textarea id="wfai-teaser" class="large-text" rows="2" name="wfai_settings[teaser]"><?php echo esc_textarea($s['teaser'] ?? ''); ?></textarea><p class="description">Всплывает над кнопкой чата через пару секунд после входа на сайт (один раз за визит). Пусто — не показывать.</p></td></tr>
                <tr><th scope="row"><label for="wfai-owner">Менеджер (имя в живом чате) и e-mail для уведомлений</label></th><td>
                    <input id="wfai-owner" class="regular-text" name="wfai_settings[owner_name]" value="<?php echo esc_attr($s['owner_name']); ?>" placeholder="Имя менеджера в живом чате">
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
