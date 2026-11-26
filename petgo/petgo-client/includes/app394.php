<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   39.4 ПОГОДА В АЛМАТЫ И ПОДСКАЗКА ДНЯ
   Сейчас в Алматы: температура и короткое «ясно / облачно / снег» — на главной приложения (рядом с «Алматы»)
   и на заставке при входе. Под приветствием — подсказка по погоде с именем питомца:
   «Сегодня хорошая погода. Можно погулять, Бублик!», «Снег! Бублик, гуляем — а потом моем лапы от реагентов».
   Погоду даёт бесплатный сервис Open-Meteo (без ключа). Сайт спрашивает её в фоне (WP-Cron) раз в 20 минут
   и помнит последнее значение до 3 часов — страница из-за погоды не ждёт. Нет погоды — просто нет строки.
   ============================================================ */

define('PETGO_WX_URL', 'https://api.open-meteo.com/v1/forecast?latitude=43.2389&longitude=76.8897&current=temperature_2m,weather_code,wind_speed_10m,is_day&wind_speed_unit=ms&timezone=Asia%2FAlmaty');

/* запрос погоды (в фоне) */
function petgo_wx_fetch() {
    $r = wp_remote_get(PETGO_WX_URL, array('timeout' => 8));
    $j = is_wp_error($r) ? null : json_decode((string) wp_remote_retrieve_body($r), true);
    if (is_array($j) && !empty($j['current']) && isset($j['current']['temperature_2m'], $j['current']['weather_code'])) {
        $c = $j['current'];
        update_option('petgo_wx_last', array('t' => (float) $c['temperature_2m'], 'code' => (int) $c['weather_code'], 'wind' => isset($c['wind_speed_10m']) ? (float) $c['wind_speed_10m'] : 0.0, 'at' => time()), false);
    }
}
add_action('petgo_wx_fetch', 'petgo_wx_fetch');

/* погода сейчас (последняя известная, не старше 3 часов); устарела — обновится в фоне */
function petgo_wx() {
    $w = get_option('petgo_wx_last');
    if (!get_transient('petgo_wx_ask')) {
        set_transient('petgo_wx_ask', 1, 20 * MINUTE_IN_SECONDS);
        if (!wp_next_scheduled('petgo_wx_fetch')) {
            wp_schedule_single_event(time(), 'petgo_wx_fetch');
        }
    }
    return is_array($w) && isset($w['at']) && time() - (int) $w['at'] < 3 * HOUR_IN_SECONDS ? $w : null;
}

/* «+5°», «−3°» */
function petgo_wx_temp($t) {
    $t = (int) round($t);
    return ($t > 0 ? '+' : ($t < 0 ? '−' : '')) . abs($t) . '°';
}

/* коротко о погоде по коду WMO */
function petgo_wx_desc($code) {
    $c = (int) $code;
    $d = $c === 0 ? 'ясно' : ($c <= 2 ? 'малооблачно' : ($c === 3 ? 'облачно' : ($c === 45 || $c === 48 ? 'туман' : ($c >= 51 && $c <= 57 ? 'морось' : ($c >= 61 && $c <= 67 ? 'дождь' : ($c >= 71 && $c <= 77 ? 'снег' : ($c >= 80 && $c <= 82 ? 'ливень' : ($c >= 85 && $c <= 86 ? 'снегопад' : ($c >= 95 ? 'гроза' : 'облачно')))))))));
    return petgo_site_l($d);
}

/* питомец для подсказки: первая собака, иначе первый питомец */
function petgo_wx_pet($uid) {
    $pick = null;
    foreach (function_exists('petgo_cl_pets') ? petgo_cl_pets($uid) : array() as $p) {
        $sp = (string) get_post_meta($p->ID, '_pf_species', true);
        if (!$pick || ($sp === 'dog' && $pick['sp'] !== 'dog')) {
            $pick = array('name' => get_the_title($p->ID), 'sp' => $sp);
        }
    }
    return $pick;
}

/* подсказка дня: что сейчас с погодой и что это значит для питомца */
function petgo_wx_tip($uid) {
    $w = petgo_wx();
    if (!$w) {
        return null;
    }
    $t    = (float) $w['t'];
    $code = (int) $w['code'];
    $h    = (int) wp_date('G', null, petgo_tz());
    if ($code >= 95) {
        $k = 'storm';
    } elseif (($code >= 71 && $code <= 77) || $code === 85 || $code === 86) {
        $k = 'snow';
    } elseif (($code >= 51 && $code <= 67) || ($code >= 80 && $code <= 82)) {
        $k = 'rain';
    } elseif ((float) $w['wind'] >= 12) {
        $k = 'wind';
    } elseif ($t >= 30) {
        $k = 'hot';
    } elseif ($t <= -10) {
        $k = 'frost';
    } elseif ($t < 8) {
        $k = 'cold';
    } elseif ($h >= 22 || $h < 6) {
        $k = 'night';
    } else {
        $k = 'good';
    }
    $T = array(
        'good'  => array('Сегодня хорошая погода. Можно погулять, {pet}!', 'Сегодня хорошая погода. {pet}, лучшее место — у окна.', 'Сегодня хорошая погода — самое время для прогулки.'),
        'hot'   => array('Жарко. {pet}, гуляем утром или вечером и не забываем воду.', 'Жарко. {pet}, миска с водой должна быть полной.', 'Жарко — гулять лучше утром или вечером.'),
        'cold'  => array('Прохладно. {pet}, гуляем бодрым шагом.', 'Прохладно. {pet}, сегодня день для пледа.', 'Прохладно — одевайтесь на прогулку теплее.'),
        'frost' => array('Мороз. {pet}, сегодня прогулка покороче.', 'Мороз. {pet}, сегодня точно день для пледа.', 'Мороз — прогулку лучше сделать покороче.'),
        'rain'  => array('Дождь. {pet}, после прогулки — полотенце для лап.', 'Дождь за окном. {pet}, уютный день дома.', 'Дождь — после прогулки не забудьте вытереть лапы.'),
        'snow'  => array('Снег! {pet}, гуляем — а потом моем лапы от реагентов.', 'Снег за окном. {pet}, есть на что посмотреть.', 'Снег — после прогулки помойте лапы от реагентов.'),
        'wind'  => array('Сильный ветер. {pet}, гуляем там, где тише.', 'Ветрено. {pet}, сегодня уютнее дома.', 'Сильный ветер — гуляйте там, где тише.'),
        'storm' => array('Гроза. {pet}, переждём дома — вместе не страшно.', 'Гроза. {pet}, переждём дома — вместе не страшно.', 'Гроза — лучше переждать дома.'),
        'night' => array('Ночь в Алматы. {pet}, спокойной ночи — утром на прогулку.', 'Ночь в Алматы. {pet}, сладких снов.', 'Спокойной ночи!'),
    );
    $pet = petgo_wx_pet($uid);
    $i   = !$pet ? 2 : ($pet['sp'] === 'cat' ? 1 : 0);
    $tip = str_replace('{pet}', $pet ? $pet['name'] : '', petgo_site_l($T[$k][$i]));
    return array('temp' => petgo_wx_temp($t), 'desc' => petgo_wx_desc($code), 'tip' => $tip, 'kind' => $k);
}

/* главная приложения: «Алматы · +5°, облачно» и подсказка под приветствием (вместо постоянной фразы) */
function petgo_wx_city_html($uid) {
    $w = petgo_wx_tip($uid);
    return esc_html(petgo_site_l('Алматы')) . ($w ? ' <b class="pgk-wx">' . esc_html($w['temp']) . '</b><small class="pgk-wx-d">' . esc_html($w['desc']) . '</small>' : '');
}

add_filter('petgo_cl_cfg', function ($cfg, $uid = 0) {
    foreach (array('первый час', 'Первый заказ', 'Первый день — бесплатно', 'Скидка на первый день', 'остальные 6 дней — по обычной цене.', 'Первый час — бесплатно', 'Скидка на первый час', 'остальное время — по обычной цене.', 'Визит — бесплатно', 'Скидка на визит', 'Другие просьбы — например, полить цветы или дать лекарство — напишите в комментарии.', 'Пожелания к прогулке, как попасть и т. п.', 'Другие просьбы: полить цветы, дать лекарство и т. п.', 'Укажите адрес: улица, дом, подъезд, квартира.') as $s) {
        $cfg['tr'][$s] = petgo_site_l($s);
    }
    return $cfg;
}, 25, 2);

add_filter('petgo_site_dict_more', function ($d) {
    return array_merge($d, array(
        /* погода */
        'ясно' => array('ашық', 'clear'), 'малооблачно' => array('аздап бұлтты', 'partly cloudy'), 'облачно' => array('бұлтты', 'cloudy'), 'туман' => array('тұман', 'fog'),
        'морось' => array('сіркіреме', 'drizzle'), 'дождь' => array('жаңбыр', 'rain'), 'снег' => array('қар', 'snow'), 'ливень' => array('нөсер', 'showers'), 'снегопад' => array('қар жауып тұр', 'snowfall'), 'гроза' => array('найзағай', 'thunderstorm'),
        'Сегодня хорошая погода. Можно погулять, {pet}!' => array('Бүгін ауа райы жақсы. Серуендеуге болады, {pet}!', 'Lovely weather today. Time for a walk, {pet}!'),
        'Сегодня хорошая погода. {pet}, лучшее место — у окна.' => array('Бүгін ауа райы жақсы. {pet}, ең жақсы орын — терезе алды.', 'Lovely weather today. {pet}, the best spot is by the window.'),
        'Сегодня хорошая погода — самое время для прогулки.' => array('Бүгін ауа райы жақсы — серуенге нағыз уақыт.', 'Lovely weather today — perfect time for a walk.'),
        'Жарко. {pet}, гуляем утром или вечером и не забываем воду.' => array('Ыстық. {pet}, таңертең не кешке серуендейміз, суды ұмытпаймыз.', 'It’s hot. {pet}, let’s walk in the morning or evening and bring water.'),
        'Жарко. {pet}, миска с водой должна быть полной.' => array('Ыстық. {pet}, ыдыстағы су толы болсын.', 'It’s hot. {pet}, keep the water bowl full.'),
        'Жарко — гулять лучше утром или вечером.' => array('Ыстық — таңертең не кешке серуендеген дұрыс.', 'It’s hot — better walk in the morning or evening.'),
        'Прохладно. {pet}, гуляем бодрым шагом.' => array('Салқын. {pet}, ширақ қадаммен серуендейміз.', 'It’s chilly. {pet}, let’s walk at a brisk pace.'),
        'Прохладно. {pet}, сегодня день для пледа.' => array('Салқын. {pet}, бүгін пледке оранатын күн.', 'It’s chilly. {pet}, a perfect day for a blanket.'),
        'Прохладно — одевайтесь на прогулку теплее.' => array('Салқын — серуенге жылырақ киініңіз.', 'It’s chilly — dress warmer for the walk.'),
        'Мороз. {pet}, сегодня прогулка покороче.' => array('Аяз. {pet}, бүгін серуен қысқарақ.', 'Frosty. {pet}, a shorter walk today.'),
        'Мороз. {pet}, сегодня точно день для пледа.' => array('Аяз. {pet}, бүгін нағыз пледтің күні.', 'Frosty. {pet}, definitely a blanket day.'),
        'Мороз — прогулку лучше сделать покороче.' => array('Аяз — серуенді қысқартқан дұрыс.', 'Frosty — better keep the walk short.'),
        'Дождь. {pet}, после прогулки — полотенце для лап.' => array('Жаңбыр. {pet}, серуеннен кейін — табанға орамал.', 'Rain. {pet}, a towel for the paws after the walk.'),
        'Дождь за окном. {pet}, уютный день дома.' => array('Терезе сыртында жаңбыр. {pet}, үйде жайлы күн.', 'Rain outside. {pet}, a cosy day at home.'),
        'Дождь — после прогулки не забудьте вытереть лапы.' => array('Жаңбыр — серуеннен кейін табанды сүртуді ұмытпаңыз.', 'Rain — don’t forget to wipe the paws after the walk.'),
        'Снег! {pet}, гуляем — а потом моем лапы от реагентов.' => array('Қар! {pet}, серуендейміз — кейін табанды реагенттен жуамыз.', 'Snow! {pet}, let’s walk — then wash the paws from de-icer.'),
        'Снег за окном. {pet}, есть на что посмотреть.' => array('Терезе сыртында қар. {pet}, қарайтын нәрсе бар.', 'Snow outside. {pet}, something to watch.'),
        'Снег — после прогулки помойте лапы от реагентов.' => array('Қар — серуеннен кейін табанды реагенттен жуыңыз.', 'Snow — wash the paws from de-icer after the walk.'),
        'Сильный ветер. {pet}, гуляем там, где тише.' => array('Қатты жел. {pet}, тынышырақ жерде серуендейміз.', 'Strong wind. {pet}, let’s walk somewhere sheltered.'),
        'Ветрено. {pet}, сегодня уютнее дома.' => array('Желді. {pet}, бүгін үйде жайлырақ.', 'Windy. {pet}, it’s cosier at home today.'),
        'Сильный ветер — гуляйте там, где тише.' => array('Қатты жел — тынышырақ жерде серуендеңіз.', 'Strong wind — walk somewhere sheltered.'),
        'Гроза. {pet}, переждём дома — вместе не страшно.' => array('Найзағай. {pet}, үйде күтеміз — бірге қорқынышты емес.', 'Thunderstorm. {pet}, let’s wait it out at home together.'),
        'Гроза — лучше переждать дома.' => array('Найзағай — үйде күткен дұрыс.', 'Thunderstorm — better wait it out at home.'),
        'Ночь в Алматы. {pet}, спокойной ночи — утром на прогулку.' => array('Алматыда түн. {pet}, тыныш түн — таңертең серуенге.', 'Night in Almaty. {pet}, good night — walk in the morning.'),
        'Ночь в Алматы. {pet}, сладких снов.' => array('Алматыда түн. {pet}, тәтті түс көр.', 'Night in Almaty. {pet}, sweet dreams.'),
        'Спокойной ночи!' => array('Тыныш түн!', 'Good night!'),
        /* заказ: услуги, адрес, скидка первого заказа */
        'Что сделает няня' => array('Күтуші не істейді', 'What the sitter will do'),
        'Адрес' => array('Мекенжай', 'Address'),
        'Улица, дом, ЖК, подъезд, квартира' => array('Көше, үй, ТК, кіреберіс, пәтер', 'Street, building, complex, entrance, flat'),
        'Покормит' => array('Тамақтандырады', 'Feed'), 'Нальёт свежую воду' => array('Таза су құяды', 'Fresh water'), 'Почистит лоток' => array('Науаны тазалайды', 'Clean the litter box'),
        'Немного уберёт' => array('Аздап жинайды', 'Tidy up a little'), 'Немного уберёт за питомцем' => array('Жануардан кейін аздап жинайды', 'Tidy up after the pet'),
        'Другие просьбы — например, полить цветы или дать лекарство — напишите в комментарии.' => array('Басқа өтініштерді — мысалы, гүл суару немесе дәрі беру — түсініктемеге жазыңыз.', 'Other requests — for example, watering plants or giving medicine — write in the comment.'),
        'Пожелания к прогулке, как попасть и т. п.' => array('Серуенге тілектер, қалай кіруге болады т. б.', 'Walk wishes, how to get in, etc.'),
        'Другие просьбы: полить цветы, дать лекарство и т. п.' => array('Басқа өтініштер: гүл суару, дәрі беру т. б.', 'Other requests: water plants, give medicine, etc.'),
        'Укажите адрес: улица, дом, подъезд, квартира.' => array('Мекенжайды көрсетіңіз: көше, үй, кіреберіс, пәтер.', 'Enter the address: street, building, entrance, flat.'),
        'Выгул — только для собак. Для кошки выберите «Визит домой» или «Неделю визитов».' => array('Серуен — тек иттерге. Мысық үшін «Үйге келу» немесе «Бір апта келу» таңдаңыз.', 'Walks are for dogs only. For a cat choose “Home visit” or “Week of visits”.'),
        'покормить, налить свежую воду, почистить лоток, немного убрать' => array('тамақтандыру, таза су құю, науаны тазалау, аздап жинау', 'feed, fresh water, clean the litter box, tidy up'),
        'покормить, налить свежую воду, немного убрать за питомцем' => array('тамақтандыру, таза су құю, жануардан кейін жинау', 'feed, fresh water, tidy up after the pet'),
        'первый час' => array('алғашқы сағат', 'first hour'),
        'Первый заказ' => array('Алғашқы тапсырыс', 'First order'),
        'Первый день — бесплатно' => array('Алғашқы күн — тегін', 'The first day is free'),
        'Скидка на первый день' => array('Алғашқы күнге жеңілдік', 'Discount on the first day'),
        'остальные 6 дней — по обычной цене.' => array('қалған 6 күн — әдеттегі бағамен.', 'the other 6 days are at the regular price.'),
        'Первый час — бесплатно' => array('Алғашқы сағат — тегін', 'The first hour is free'),
        'Скидка на первый час' => array('Алғашқы сағатқа жеңілдік', 'Discount on the first hour'),
        'остальное время — по обычной цене.' => array('қалған уақыт — әдеттегі бағамен.', 'the rest of the time is at the regular price.'),
        'Визит — бесплатно' => array('Келу — тегін', 'The visit is free'),
        'Скидка на визит' => array('Келуге жеңілдік', 'Discount on the visit'),
        'Первый час выгула или первый день визитов — бесплатно' => array('Серуеннің алғашқы сағаты немесе келудің алғашқы күні — тегін', 'First walk hour or first day of visits is free'),
        'на первый час выгула или первый день визитов' => array('серуеннің алғашқы сағатына немесе келудің алғашқы күніне', 'on the first walk hour or first day of visits'),
        'Первый час выгула или первый день визитов — бесплатно, код не нужен' => array('Серуеннің алғашқы сағаты немесе келудің алғашқы күні — тегін, код керек емес', 'First walk hour or first day of visits is free, no code needed'),
        'На первый час выгула или первый день визитов, код не нужен' => array('Серуеннің алғашқы сағатына немесе келудің алғашқы күніне, код керек емес', 'On the first walk hour or first day of visits, no code needed'),
    ));
});
