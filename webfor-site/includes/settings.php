<?php
/**
 * Настройки WebFor: телефон для звонков и WhatsApp — два независимых поля.
 * Меняете здесь — меняется на всём сайте (шапка, кнопки, форма, подвал, шорткоды).
 */
defined('ABSPATH') || exit;

function webfor_default_settings() {
    return [
        'phone'        => '+7 747 817 57 12',
        'whatsapp'     => '+7 747 817 57 12',
        'wa_text'      => 'Здравствуйте! Хочу узнать подробнее об услугах WebFor.',
        'email'        => '',
        'city'         => 'Казахстан',
        'notify_email' => get_option('admin_email'),
    ];
}

/** Значение настройки (с запасным значением по умолчанию). */
function webfor_opt($key) {
    $all = wp_parse_args((array) get_option('webfor_settings', []), webfor_default_settings());
    return isset($all[$key]) ? $all[$key] : '';
}

function webfor_digits($s) {
    return preg_replace('/\D+/', '', (string) $s);
}

/** tel:-ссылка из телефона в любом виде: «8 747…» превращается в +7 747… */
function webfor_tel_href($phone = null) {
    $d = webfor_digits($phone === null ? webfor_opt('phone') : $phone);
    if (strlen($d) === 11 && $d[0] === '8') {
        $d = '7' . substr($d, 1);
    }
    return $d ? 'tel:+' . $d : '';
}

/** Ссылка на чат WhatsApp с заготовленным текстом. */
function webfor_wa_href($text = null) {
    $d = webfor_digits(webfor_opt('whatsapp'));
    if (strlen($d) === 11 && $d[0] === '8') {
        $d = '7' . substr($d, 1);
    }
    if (!$d) {
        return '';
    }
    $msg = $text === null ? webfor_opt('wa_text') : $text;
    return 'https://wa.me/' . $d . ($msg !== '' ? '?text=' . rawurlencode($msg) : '');
}

add_action('admin_menu', function () {
    // «WebFor» открывает панель управления (includes/dashboard.php), настройки — отдельным пунктом
    add_menu_page('WebFor', 'WebFor', 'manage_options', 'webfor', 'webfor_panel_page', 'dashicons-admin-site-alt3', 3);
    add_submenu_page('webfor', 'Панель WebFor', 'Панель', 'manage_options', 'webfor', 'webfor_panel_page');
    add_submenu_page('webfor', 'Настройки WebFor', 'Настройки', 'manage_options', 'webfor-settings', 'webfor_settings_page');
});

add_action('admin_init', function () {
    register_setting('webfor', 'webfor_settings', [
        'type'              => 'array',
        'sanitize_callback' => 'webfor_sanitize_settings',
        'default'           => webfor_default_settings(),
    ]);
});

function webfor_sanitize_settings($in) {
    $in = (array) $in;
    $out = [];
    foreach (['phone', 'whatsapp', 'city'] as $k) {
        $out[$k] = isset($in[$k]) ? sanitize_text_field($in[$k]) : '';
    }
    $out['wa_text'] = isset($in['wa_text']) ? sanitize_textarea_field($in['wa_text']) : '';
    $out['email'] = isset($in['email']) ? sanitize_email($in['email']) : '';
    $out['notify_email'] = isset($in['notify_email']) ? sanitize_email($in['notify_email']) : '';
    foreach (['phone' => 'Телефон', 'whatsapp' => 'WhatsApp'] as $k => $label) {
        $n = strlen(webfor_digits($out[$k]));
        if ($out[$k] !== '' && ($n < 10 || $n > 15)) {
            add_settings_error('webfor_settings', 'bad_' . $k, $label . ': номер должен содержать от 10 до 15 цифр.');
            $out[$k] = webfor_opt($k);
        }
    }
    return $out;
}

function webfor_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $s = wp_parse_args((array) get_option('webfor_settings', []), webfor_default_settings());
    $page_id = (int) get_option('webfor_page_id');
    ?>
    <div class="wrap">
        <h1>Настройки WebFor</h1>
        <p>Номера используются на всём сайте: в шапке, кнопках, форме заявки и подвале. Телефон и WhatsApp независимы — можно поменять один, второй останется прежним.</p>
        <?php settings_errors('webfor_settings'); ?>
        <form method="post" action="options.php">
            <?php settings_fields('webfor'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="wf-phone">Телефон для звонков</label></th>
                    <td><input id="wf-phone" class="regular-text" type="tel" name="webfor_settings[phone]" value="<?php echo esc_attr($s['phone']); ?>" placeholder="+7 700 000 00 00">
                        <p class="description">Кнопки «Позвонить» и номер в шапке и подвале.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wf-wa">Номер WhatsApp</label></th>
                    <td><input id="wf-wa" class="regular-text" type="tel" name="webfor_settings[whatsapp]" value="<?php echo esc_attr($s['whatsapp']); ?>" placeholder="+7 700 000 00 00">
                        <p class="description">Все кнопки «Написать в WhatsApp».</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wf-watext">Текст сообщения в WhatsApp</label></th>
                    <td><textarea id="wf-watext" class="large-text" rows="2" name="webfor_settings[wa_text]"><?php echo esc_textarea($s['wa_text']); ?></textarea>
                        <p class="description">Подставляется в чат, когда клиент нажимает кнопку WhatsApp.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wf-email">E-mail на сайте</label></th>
                    <td><input id="wf-email" class="regular-text" type="email" name="webfor_settings[email]" value="<?php echo esc_attr($s['email']); ?>">
                        <p class="description">Необязательно. Если пусто — не показывается.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wf-city">Город / регион</label></th>
                    <td><input id="wf-city" class="regular-text" type="text" name="webfor_settings[city]" value="<?php echo esc_attr($s['city']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wf-notify">Уведомлять о заявках на e-mail</label></th>
                    <td><input id="wf-notify" class="regular-text" type="email" name="webfor_settings[notify_email]" value="<?php echo esc_attr($s['notify_email']); ?>">
                        <p class="description">Новые заявки всегда сохраняются в разделе WebFor → Заявки. Сюда дополнительно придёт письмо (если на сайте настроена почта).</p></td>
                </tr>
            </table>
            <?php submit_button('Сохранить'); ?>
        </form>
        <h2>Где сайт</h2>
        <p><?php if ($page_id && get_post_status($page_id)) : ?>
            Страница сайта: <a href="<?php echo esc_url(get_permalink($page_id)); ?>" target="_blank">открыть</a>.
        <?php endif; ?>
            Сайт можно вставить на любую страницу шорткодом <code>[webfor_landing]</code>.
            Номера по отдельности: <code>[webfor_phone]</code>, <code>[webfor_whatsapp]</code>.</p>
    </div>
    <?php
}
