<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   РЕКЛАМА И SEO (34.5)
   Раздел приложения «Реклама и SEO» (владелец и админ): счётчики и пиксели (Google Analytics / Google Ads,
   Яндекс Метрика, Meta — Instagram/Facebook, TikTok, VK), подтверждение сайта, заголовки и описания страниц,
   данные компании для поиска. Цели (заявка, анкета, регистрация, запись, звонок) отправляются сами.
   Карта сайта: /petgo-sitemap.xml (добавлена в robots.txt). Источник клиента (utm, gclid, yclid…) — в заявке.
   Сделан через API PetGo (petgo_nav_items, petgo_app_page, petgo_settings_rows) — как будущие плагины.
   ============================================================ */

function petgo_ads_defaults() {
    return array(
        'ga4' => '', 'gads' => '', 'gads_lead' => '', 'gads_signup' => '', 'gads_book' => '',
        'ym' => '', 'ym_wv' => '1', 'fb' => '', 'tt' => '', 'vk' => '',
        'v_google' => '', 'v_yandex' => '', 'v_fb' => '',
        'seo' => array(), 'og' => '',
        'addr' => '', 'hours' => 'Mo-Su 07:00-22:00', 'price' => '₸₸', 'ig' => '', 'tiktok' => '', 'gis' => '',
    );
}

function petgo_ads_cfg($fresh = false) {
    static $c = null;
    if ($c === null || $fresh) {
        $o = get_option('petgo_ads', array());
        $c = array_merge(petgo_ads_defaults(), is_array($o) ? $o : array());
        $c['seo'] = is_array($c['seo']) ? $c['seo'] : array();
    }
    return $c;
}

/* заголовки и описания по умолчанию — с ключевыми словами для Алматы ({walk}, {week} — цены с сайта) */
function petgo_ads_seo_defaults() {
    return array(
        'home'     => array('Выгул собак и няня для кошек в Алматы — PetGo', 'Выгул собак и визиты к кошкам и собакам на дом в Алматы. Проверенные няни, фото после каждого визита, приход по геолокации. Оставьте заявку — подберём няню.'),
        'services' => array('Цены на выгул собак и визиты к питомцам в Алматы — PetGo', 'Выгул собак — {walk} ₸ за час для первого заказа, ежедневные визиты к кошкам и собакам на неделю. Фото и отчёт няни после каждого визита.'),
        'nannies'  => array('Няни для собак и кошек в Алматы — PetGo', 'Проверенные догситтеры и кэтситтеры PetGo в Алматы. Подберём няню под характер и привычки вашего питомца.'),
        'reviews'  => array('Отзывы о PetGo — выгул собак и няни в Алматы', 'Настоящие отзывы хозяев собак и кошек после заказов PetGo в Алматы.'),
    );
}

function petgo_ads_seo_pages() {
    return array('home' => 'Главная', 'services' => 'Услуги и цены', 'nannies' => 'Няни', 'reviews' => 'Отзывы');
}

/* ---------- подключение к приложению сотрудников (через API PetGo) ---------- */
add_filter('petgo_nav_items', function ($items, $adm) {
    if ($adm && function_exists('petgo_can') && petgo_can('admin')) {
        $items['ads'] = array('Реклама', petgo_page_url(array('pg' => 'ads')), 'globe', 'd');
    }
    return $items;
}, 10, 2);
add_filter('petgo_app_page', function ($done, $pg) {
    if ($done || $pg !== 'ads') {
        return $done;
    }
    petgo_page_ads();
    return true;
}, 10, 2);
add_filter('petgo_settings_rows', function ($rows) {
    $rows[] = array('icon' => 'globe', 'title' => 'Реклама и SEO', 'sub' => 'Пиксели, цели, поиск Google и Яндекс', 'url' => petgo_page_url(array('pg' => 'ads')));
    return $rows;
});

function petgo_ads_field($name, $label, $val, $ph = '', $hint = '') {
    return '<label class="pgf-row"><span>' . esc_html($label) . '</span><input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '" placeholder="' . esc_attr($ph) . '" autocomplete="off" spellcheck="false" autocapitalize="none"></label>' . ($hint !== '' ? '<small class="pgads-hint">' . esc_html($hint) . '</small>' : '');
}

function petgo_page_ads() {
    if (!petgo_can('admin')) {
        echo '<p class="mu">' . esc_html(petgo_t('err_perm')) . '</p>';
        return;
    }
    $c   = petgo_ads_cfg(true);
    $def = petgo_ads_seo_defaults();
    $map = home_url('/petgo-sitemap.xml');
    echo '<style>.pgads .pgc-card{display:flex;flex-direction:column;gap:10px;margin-bottom:14px}.pgads .pgc-card>b{font-size:17px}.pgads-hint{display:block;margin:-2px 2px 4px;font-size:12.5px;color:var(--mu)}.pgads code{font-size:12.5px;padding:1px 6px;border-radius:6px;background:var(--so)}.pgads .pgf-list{margin:0}.pgads ul{margin:0;padding-left:18px;color:var(--mu);font-size:14px;line-height:1.5}.pgads-save{position:sticky;bottom:calc(var(--sab) + 12px);z-index:5}</style>';
    echo '<div class="pgads">' . petgo_form_open('ads_save');

    echo '<div class="pgc-card"><b>Счётчики и пиксели</b><p class="mu">Вставьте номера из кабинетов рекламы — коды подключатся сами на всех страницах сайта. Сотрудники PetGo в статистику не попадают.</p><div class="pgf-list">';
    echo petgo_ads_field('ga4', 'Google Analytics 4', $c['ga4'], 'G-XXXXXXXXXX');
    echo petgo_ads_field('gads', 'Google Ads', $c['gads'], 'AW-123456789');
    echo petgo_ads_field('gads_lead', 'Ads: метка «заявка»', $c['gads_lead'], 'AbCdEfGh123');
    echo petgo_ads_field('gads_signup', 'Ads: метка «регистрация»', $c['gads_signup'], 'необязательно');
    echo petgo_ads_field('gads_book', 'Ads: метка «запись»', $c['gads_book'], 'необязательно');
    echo petgo_ads_field('ym', 'Яндекс Метрика', $c['ym'], '12345678');
    echo petgo_ads_field('fb', 'Meta Pixel (Instagram)', $c['fb'], '1234567890');
    echo petgo_ads_field('tt', 'TikTok Pixel', $c['tt'], 'C1ABCDEF2345');
    echo petgo_ads_field('vk', 'VK Реклама (Top.Mail.Ru)', $c['vk'], '3456789');
    echo '</div><label class="pgc-ck"><input type="checkbox" name="ym_wv" value="1"' . checked($c['ym_wv'], '1', false) . '> <span>Вебвизор Яндекса (запись действий на сайте, кроме кабинета клиента)</span></label></div>';

    echo '<div class="pgc-card"><b>Цели — уже настроены</b><p class="mu">Сайт сам сообщает рекламным системам об этих действиях. В Яндекс Метрике создайте цели типа «JavaScript-событие» с такими идентификаторами:</p><ul>';
    foreach (array('lead' => 'заявка с сайта', 'pet_form' => 'анкета питомца отправлена', 'sign_up' => 'клиент создал кабинет', 'booking' => 'заявка на выгул из кабинета', 'contact' => 'нажали «Позвонить» или WhatsApp') as $k => $t) {
        echo '<li><code>' . esc_html($k) . '</code> — ' . esc_html($t) . '</li>';
    }
    echo '</ul><p class="mu">Google и Meta получают стандартные события (generate_lead, sign_up, Lead, CompleteRegistration, Contact, Schedule). В заявках в приложении видно, откуда пришёл клиент: метки utm, Google Ads, Яндекс Директ, Instagram, TikTok.</p></div>';

    echo '<div class="pgc-card"><b>Поиск: заголовки и описания</b><p class="mu">То, что видно в Google и Яндексе и при отправке ссылки в WhatsApp или Instagram. Пусто — используется текст по умолчанию (серым). Можно писать {walk}, {week} — подставятся цены.</p>';
    foreach (petgo_ads_seo_pages() as $k => $name) {
        $v = isset($c['seo'][$k]) && is_array($c['seo'][$k]) ? $c['seo'][$k] : array('', '');
        echo '<div class="pgf-list"><label class="pgf-row"><span>' . esc_html($name) . '</span><input type="text" name="seo[' . esc_attr($k) . '][0]" value="' . esc_attr(isset($v[0]) ? $v[0] : '') . '" maxlength="90" placeholder="' . esc_attr($def[$k][0]) . '"></label>';
        echo '<label class="pgf-row pgf-ra"><span>Описание</span><textarea name="seo[' . esc_attr($k) . '][1]" rows="2" maxlength="300" placeholder="' . esc_attr($def[$k][1]) . '">' . esc_textarea(isset($v[1]) ? $v[1] : '') . '</textarea></label></div>';
    }
    echo '<div class="pgf-list">' . petgo_ads_field('og', 'Картинка для ссылок', $c['og'], 'https://… (1200×630); пусто — фото с главной') . '</div></div>';

    echo '<div class="pgc-card"><b>Компания для поиска и карт</b><p class="mu">Помогает Google и Яндексу показывать PetGo в местной выдаче: адрес, часы работы, соцсети.</p><div class="pgf-list">';
    echo petgo_ads_field('addr', 'Адрес', $c['addr'], 'Алматы, ул. …');
    echo petgo_ads_field('hours', 'Часы работы', $c['hours'], 'Mo-Su 07:00-22:00');
    echo petgo_ads_field('price', 'Уровень цен', $c['price'], '₸₸');
    echo petgo_ads_field('ig', 'Instagram', $c['ig'], 'https://instagram.com/…');
    echo petgo_ads_field('tiktok', 'TikTok', $c['tiktok'], 'https://tiktok.com/@…');
    echo petgo_ads_field('gis', '2ГИС', $c['gis'], 'https://2gis.kz/almaty/firm/…');
    echo '</div></div>';

    echo '<div class="pgc-card"><b>Подтверждение сайта</b><p class="mu">Код из Google Search Console, Яндекс Вебмастера и Meta Business (только сам код из content="…").</p><div class="pgf-list">';
    echo petgo_ads_field('v_google', 'Google', $c['v_google'], 'abc123…');
    echo petgo_ads_field('v_yandex', 'Яндекс', $c['v_yandex'], '0123abcd…');
    echo petgo_ads_field('v_fb', 'Meta (Facebook)', $c['v_fb'], 'abc123…');
    echo '</div><p class="mu">Карта сайта для поиска: <a href="' . esc_url($map) . '" target="_blank" rel="noopener" data-ext>' . esc_html($map) . '</a> — укажите её в Search Console и Вебмастере.</p></div>';

    echo '<div class="pgads-save"><button type="submit" class="pgc-btn wide lg">Сохранить</button></div></form></div>';
}

add_action('admin_post_petgo_ads_save', 'petgo_h_ads_save');
function petgo_h_ads_save() {
    petgo_guard('admin');
    $in  = wp_unslash($_POST);
    $old = petgo_ads_cfg(true);
    $id  = function ($k, $re) use ($in) {
        $v = isset($in[$k]) && is_scalar($in[$k]) ? trim((string) $in[$k]) : '';
        return preg_match($re, $v) ? $v : '';
    };
    $url = function ($k) use ($in) {
        $v = isset($in[$k]) && is_scalar($in[$k]) ? esc_url_raw(trim((string) $in[$k]), array('https', 'http')) : '';
        return (string) $v;
    };
    $txt = function ($k, $max) use ($in) {
        return isset($in[$k]) && is_scalar($in[$k]) ? mb_substr(sanitize_text_field((string) $in[$k]), 0, $max) : '';
    };
    $n = array(
        'ga4' => strtoupper($id('ga4', '/^G-[A-Za-z0-9]{4,20}$/')),
        'gads' => strtoupper($id('gads', '/^AW-\d{5,15}$/i')),
        'gads_lead' => $id('gads_lead', '/^[A-Za-z0-9_-]{3,60}$/'),
        'gads_signup' => $id('gads_signup', '/^[A-Za-z0-9_-]{3,60}$/'),
        'gads_book' => $id('gads_book', '/^[A-Za-z0-9_-]{3,60}$/'),
        'ym' => $id('ym', '/^\d{5,12}$/'),
        'ym_wv' => !empty($in['ym_wv']) ? '1' : '0',
        'fb' => $id('fb', '/^\d{8,20}$/'),
        'tt' => strtoupper($id('tt', '/^[A-Za-z0-9]{8,30}$/')),
        'vk' => $id('vk', '/^\d{4,12}$/'),
        'v_google' => $id('v_google', '/^[A-Za-z0-9_-]{8,100}$/'),
        'v_yandex' => $id('v_yandex', '/^[A-Za-z0-9_-]{8,100}$/'),
        'v_fb' => $id('v_fb', '/^[A-Za-z0-9_-]{8,100}$/'),
        'og' => $url('og'), 'ig' => $url('ig'), 'tiktok' => $url('tiktok'), 'gis' => $url('gis'),
        'addr' => $txt('addr', 160), 'hours' => $txt('hours', 120), 'price' => $txt('price', 10),
        'seo' => array(),
    );
    foreach (array_keys(petgo_ads_seo_pages()) as $k) {
        $t = isset($in['seo'][$k][0]) && is_scalar($in['seo'][$k][0]) ? mb_substr(sanitize_text_field((string) $in['seo'][$k][0]), 0, 90) : '';
        $d = isset($in['seo'][$k][1]) && is_scalar($in['seo'][$k][1]) ? mb_substr(sanitize_text_field((string) $in['seo'][$k][1]), 0, 300) : '';
        if ($t !== '' || $d !== '') {
            $n['seo'][$k] = array($t, $d);
        }
    }
    /* поле заполнено, но с ошибкой — подскажем, не сохраняя мусор */
    $bad = array();
    foreach (array('ga4' => 'Google Analytics', 'gads' => 'Google Ads', 'ym' => 'Яндекс Метрика', 'fb' => 'Meta Pixel', 'tt' => 'TikTok', 'vk' => 'VK') as $k => $t) {
        if (isset($in[$k]) && trim((string) $in[$k]) !== '' && $n[$k] === '') {
            $bad[] = $t;
            $n[$k] = $old[$k];
        }
    }
    update_option('petgo_ads', $n, false);
    petgo_ads_cfg(true);
    if (function_exists('petgo_audit')) {
        petgo_audit('ads_save');
    }
    petgo_flash($bad ? 'Сохранено, но проверьте: ' . implode(', ', $bad) . ' — номер в другом формате.' : 'Сохранено. Изменения уже на сайте.', !$bad);
    petgo_back('ads');
}

/* ============================================================
   НА САЙТЕ: заголовки, описания, разметка для поиска
   ============================================================ */
add_filter('petgo_site_seo_title', function ($t, $page) {
    $c = petgo_ads_cfg();
    if (!empty($c['seo'][$page][0])) {
        return petgo_site_tx(petgo_site_l($c['seo'][$page][0]));
    }
    $d = petgo_ads_seo_defaults();
    return isset($d[$page]) ? petgo_site_tx(petgo_site_l($d[$page][0])) : $t;
}, 10, 2);
add_filter('petgo_site_seo_desc', function ($t, $page) {
    $c = petgo_ads_cfg();
    if (!empty($c['seo'][$page][1])) {
        return petgo_site_tx(petgo_site_l($c['seo'][$page][1]));
    }
    $d = petgo_ads_seo_defaults();
    return isset($d[$page]) && trim((string) petgo_site_cfg()['seo']) === '' ? petgo_site_tx(petgo_site_l($d[$page][1])) : $t;
}, 10, 2);
add_filter('petgo_site_seo_image', function ($img) {
    $c = petgo_ads_cfg();
    return $c['og'] !== '' ? $c['og'] : $img;
});

add_filter('petgo_site_schema', 'petgo_ads_schema', 10, 2);
function petgo_ads_schema($graph, $page) {
    $c    = petgo_ads_cfg();
    $home = home_url('/');
    $name = trim((string) get_bloginfo('name'));
    $name = $name !== '' ? $name : 'PetGo.kz';
    $same = array_values(array_filter(array($c['ig'], $c['tiktok'], $c['gis'])));
    $biz  = array(
        '@type' => 'LocalBusiness', '@id' => $home . '#petgo', 'name' => $name, 'url' => $home,
        'telephone' => '+' . petgo_site_phone(), 'image' => apply_filters('petgo_site_seo_image', petgo_asset('icon-512.png'), $page),
        'logo' => petgo_asset('icon-512.png'), 'priceRange' => $c['price'] !== '' ? $c['price'] : '₸₸',
        'address' => array('@type' => 'PostalAddress', 'addressLocality' => 'Алматы', 'addressCountry' => 'KZ') + ($c['addr'] !== '' ? array('streetAddress' => $c['addr']) : array()),
        'areaServed' => array('@type' => 'City', 'name' => 'Алматы'),
        'description' => petgo_site_tx(petgo_site_l(petgo_ads_seo_defaults()['home'][1])),
    );
    if ($c['hours'] !== '') {
        $biz['openingHours'] = $c['hours'];
    }
    if ($same) {
        $biz['sameAs'] = $same;
    }
    $out = array();
    if ($page === 'home') {
        $out[] = $biz;
        $faq = function_exists('petgo_site_faq') ? petgo_site_faq() : array();
        if ($faq) {
            $q = array();
            foreach ($faq as $x) {
                $q[] = array('@type' => 'Question', 'name' => wp_strip_all_tags($x[0]), 'acceptedAnswer' => array('@type' => 'Answer', 'text' => wp_strip_all_tags($x[1])));
            }
            $out[] = array('@type' => 'FAQPage', 'mainEntity' => $q);
        }
    } else {
        $pgs = petgo_site_pages();
        if (isset($pgs[$page]) && empty($pgs[$page]['hide']) && $page !== 'account') {
            $out[] = array('@type' => 'BreadcrumbList', 'itemListElement' => array(
                array('@type' => 'ListItem', 'position' => 1, 'name' => petgo_site_l('Главная'), 'item' => petgo_site_url('home')),
                array('@type' => 'ListItem', 'position' => 2, 'name' => $pgs[$page]['name'], 'item' => petgo_site_url($page)),
            ));
        }
        if ($page === 'services') {
            foreach ((array) petgo_site_cfg()['services'] as $s) {
                if (empty($s['title'])) {
                    continue;
                }
                $p = function_exists('petgo_site_price') ? petgo_site_price($s) : array('now' => 0);
                $o = array('@type' => 'Service', 'name' => petgo_site_tx(petgo_site_l($s['title'])), 'serviceType' => petgo_site_tx(petgo_site_l($s['title'])), 'provider' => array('@id' => $home . '#petgo'), 'areaServed' => array('@type' => 'City', 'name' => 'Алматы'));
                if (!empty($s['text'])) {
                    $o['description'] = wp_strip_all_tags(petgo_site_tx(petgo_site_l($s['text'])));
                }
                if (!empty($p['now'])) {
                    $o['offers'] = array('@type' => 'Offer', 'price' => (string) (int) $p['now'], 'priceCurrency' => 'KZT', 'url' => petgo_site_url('services'));
                }
                $out[] = $o;
            }
            $out[] = $biz;
        }
    }
    /* старая разметка компании (только главная) заменяется полной */
    return $out ? $out : $graph;
}

/* ============================================================
   НА САЙТЕ: подтверждение, счётчики, цели
   ============================================================ */
add_action('petgo_site_head', 'petgo_ads_head', 1);
function petgo_ads_head() {
    $c = petgo_ads_cfg();
    foreach (array('v_google' => 'google-site-verification', 'v_yandex' => 'yandex-verification', 'v_fb' => 'facebook-domain-verification') as $k => $n) {
        if ($c[$k] !== '') {
            echo '<meta name="' . esc_attr($n) . '" content="' . esc_attr($c[$k]) . '">';
        }
    }
    /* сотрудники PetGo, заходя на сайт, статистику не портят */
    if (function_exists('petgo_role_of') && is_user_logged_in() && petgo_role_of() !== '') {
        return;
    }
    $page = function_exists('petgo_site_page_now') ? petgo_site_page_now() : '';
    $gid  = $c['ga4'] !== '' ? $c['ga4'] : $c['gads'];
    $js   = '';
    if ($gid !== '') {
        echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr($gid) . '"></script>';
        $js .= 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag("js",new Date());';
        foreach (array_filter(array($c['ga4'], $c['gads'])) as $x) {
            $js .= 'gtag("config",' . wp_json_encode($x) . ');';
        }
    }
    if ($c['ym'] !== '') {
        $wv  = $c['ym_wv'] === '1' && $page !== 'account' && $page !== 'join';
        $js .= '(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return}}k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");'
            . 'ym(' . (int) $c['ym'] . ',"init",{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:' . ($wv ? 'true' : 'false') . '});';
    }
    if ($c['fb'] !== '') {
        $js .= '!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");'
            . 'fbq("init",' . wp_json_encode($c['fb']) . ');fbq("track","PageView");';
    }
    if ($c['tt'] !== '') {
        $js .= '!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=r+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load(' . wp_json_encode($c['tt']) . ');ttq.page()}(window,document,"ttq");';
    }
    if ($c['vk'] !== '') {
        $js .= 'var _tmr=window._tmr||(window._tmr=[]);_tmr.push({id:' . wp_json_encode($c['vk']) . ',type:"pageView",start:(new Date()).getTime()});(function(d,w,id){if(d.getElementById(id))return;var ts=d.createElement("script");ts.type="text/javascript";ts.async=true;ts.id=id;ts.src="https://top-fwz1.mail.ru/js/code.js";var s=d.getElementsByTagName("script")[0];s.parentNode.insertBefore(ts,s)})(document,window,"tmr-code");';
    }
    if ($js === '') {
        return;
    }
    /* цели: события сайта (PGEvent) → Google, Яндекс, Meta, TikTok, VK */
    $cfg = array('ym' => $c['ym'] !== '' ? (int) $c['ym'] : 0, 'vk' => $c['vk'], 'aw' => $c['gads'], 'lb' => array('lead' => $c['gads_lead'], 'pet_form' => $c['gads_lead'], 'sign_up' => $c['gads_signup'], 'booking' => $c['gads_book']));
    $js .= '(function(A){var W=window;function go(n,d){d=d||{};'
        . 'var G={lead:["generate_lead","Lead","SubmitForm"],pet_form:["generate_lead","Lead","SubmitForm"],sign_up:["sign_up","CompleteRegistration","CompleteRegistration"],booking:["booking_request","Schedule","PlaceAnOrder"],contact:["contact","Contact","Contact"]}[n];'
        . 'if(n==="page_view"){try{W.gtag&&gtag("event","page_view",{page_location:location.href,page_title:document.title});A.ym&&W.ym&&ym(A.ym,"hit",location.href,{title:document.title,referer:d.from||""});W.fbq&&fbq("track","PageView");W.ttq&&ttq.page();A.vk&&W._tmr&&_tmr.push({id:A.vk,type:"pageView",url:location.href,start:Date.now()})}catch(e){}return}'
        . 'if(!G)return;var v=d.value?{value:d.value,currency:"KZT"}:{};'
        . 'try{if(W.gtag){gtag("event",G[0],Object.assign({transport_type:"beacon",form:d.form||n},v));if(A.aw&&A.lb[n])gtag("event","conversion",Object.assign({send_to:A.aw+"/"+A.lb[n],transport_type:"beacon"},v))}}catch(e){}'
        . 'try{A.ym&&W.ym&&ym(A.ym,"reachGoal",n,d)}catch(e){}try{W.fbq&&fbq("track",G[1],v)}catch(e){}try{W.ttq&&ttq.track(G[2],v)}catch(e){}'
        . 'try{A.vk&&W._tmr&&_tmr.push({id:A.vk,type:"reachGoal",goal:n,value:d.value||undefined})}catch(e){}}'
        . 'if(W.PGEvent&&PGEvent.on)PGEvent.on("*",function(d,n){go(n,d)})})(' . wp_json_encode($cfg) . ');';
    echo '<script>' . $js . '</script>';
}

/* ============================================================
   КАРТА САЙТА ДЛЯ ПОИСКА: /petgo-sitemap.xml (все страницы на трёх языках)
   ============================================================ */
add_action('parse_request', 'petgo_ads_sitemap', 0);
function petgo_ads_sitemap($wp) {
    $req = isset($wp->request) ? trim((string) $wp->request, '/') : '';
    if ($req !== 'petgo-sitemap.xml' && !isset($_GET['petgo_sitemap'])) {
        return;
    }
    $mod = (int) get_option('petgo_site_mod', 0);
    $x   = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    foreach (petgo_site_pages() as $k => $p) {
        if (!empty($p['hide']) || $k === 'account' || !empty($p['noindex'])) {
            continue;
        }
        foreach (array_keys(petgo_site_langs()) as $l) {
            $x .= '<url><loc>' . esc_url(petgo_site_url($k, $l)) . '</loc>';
            foreach (petgo_site_langs() as $l2 => $lv) {
                $x .= '<xhtml:link rel="alternate" hreflang="' . esc_attr($lv[2]) . '" href="' . esc_url(petgo_site_url($k, $l2)) . '"/>';
            }
            $x .= ($mod ? '<lastmod>' . esc_html(gmdate('Y-m-d', $mod)) . '</lastmod>' : '') . '<changefreq>' . ($k === 'home' || $k === 'reviews' ? 'weekly' : 'monthly') . '</changefreq><priority>' . ($k === 'home' ? '1.0' : '0.8') . '</priority></url>' . "\n";
        }
    }
    $x .= '</urlset>';
    status_header(200);
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo $x;
    exit;
}

add_filter('robots_txt', function ($out, $public) {
    if ($public) {
        $out .= "\nSitemap: " . home_url('/petgo-sitemap.xml') . "\n";
    }
    return $out;
}, 20, 2);

/* дата правки сайта — для карты сайта */
add_action('update_option_petgo_site', function () {
    update_option('petgo_site_mod', time(), false);
});
