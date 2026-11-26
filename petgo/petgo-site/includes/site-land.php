<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   39.1 СТРАНИЦЫ ДЛЯ ПОИСКА: «Выгул собак в Алматы» (/vygul-sobak/) и «Няня для кошек в Алматы» (/nyanya-dlya-koshek/)
   Google и Яндекс показывают по запросу ту страницу, которая целиком о нём. Главная — обо всём сразу,
   поэтому у каждой услуги своя страница: заголовок, цены, как проходит, вопросы, отзывы и заявка.
   В верхнем меню их нет (меню не меняется), ссылки — внизу сайта, на «Услугах» и в тексте на главной.
   Три языка: /vygul-sobak/?lang=kz и ?lang=en. В карте сайта и в IndexNow — сами.
   ============================================================ */

add_filter('petgo_site_pages', function ($p) {
    $l = 'petgo_site_l';
    $p['dogwalk'] = array('slug' => 'vygul-sobak', 'name' => $l('Выгул собак'), 'title' => $l('Выгул собак в Алматы'), 'land' => 'dog');
    $p['catsit']  = array('slug' => 'nyanya-dlya-koshek', 'name' => $l('Няня для кошек'), 'title' => $l('Няня для кошек в Алматы'), 'land' => 'cat');
    return $p;
}, 5);

/* тексты страниц (русский — ключ словаря, казахский и английский — в petgo_site_dict_more ниже) */
function petgo_site_land_data($k) {
    $d = array(
        'dogwalk' => array(
            'pet'  => 'dog',
            'h1'   => 'Выгул собак в Алматы',
            'sub'  => 'Догситтер PetGo погуляет с вашей собакой в удобное время: прогулка от часа, фото и отчёт после каждого выгула. Первый заказ — {walk} ₸ за час.',
            'btn'  => 'Заказать выгул',
            'note' => 'Хочу заказать: Выгул собак',
            'why'  => array(
                array('nanny', 'Обученные няни', 'Подберём няню под характер, размер и привычки вашей собаки.'),
                array('camera', 'Фото и отчёт', 'После каждой прогулки присылаем фото и короткий отчёт: как гуляли и как вела себя собака.'),
                array('pin', 'Приход по геолокации', 'Няня отмечает приход по геолокации, а прогулку видно в приложении My PetGo.'),
                array('spark', 'Когда удобно вам', 'Утром, днём или вечером, разово или каждый день — пока вы на работе или в поездке.'),
            ),
            'price' => 'Цены на выгул собак',
            'text_h' => 'Догситтер в Алматы: как проходит выгул',
            'text' => array(
                'Няня приходит в назначенное время, забирает собаку и гуляет с ней не меньше часа — на поводке, по спокойному маршруту, с играми. Если собаке нужно больше движения, скажите об этом при заказе.',
                'Выгул выручает, когда вы допоздна на работе, заболели, уехали или у щенка слишком много энергии. Можно заказать одну прогулку или ежедневные визиты на неделю — {week} ₸ для первого заказа.',
                'Обычный выгул — {walk_old} ₸ за час, первый заказ — {walk} ₸. После прогулки няня возвращает собаку домой и присылает фото.',
            ),
            'faq'  => array(
                array('Сколько стоит выгул собак в Алматы?', 'Первый заказ — {walk} ₸ за час, дальше {walk_old} ₸ за час. Неделя ежедневных визитов — {week} ₸ для первого заказа.'),
                array('Сколько длится прогулка?', 'От часа. Если собаке нужно больше, скажите при заказе — учтём.'),
                array('С какими собаками вы гуляете?', 'Со щенками и взрослыми собаками разных пород и размеров. Расскажите о характере и привычках — подберём подходящую няню.'),
                array('Как я узнаю, что прогулка прошла?', 'Няня отмечает приход по геолокации и присылает фото после прогулки. В приложении My PetGo видно прогулку и историю заказов.'),
                array('Как няня попадёт в квартиру?', 'Договоримся заранее: домофон, код, консьерж или ключи — как удобно вам.'),
                array('Как заказать выгул?', 'Оставьте заявку на сайте, напишите нам в WhatsApp или закажите в приложении My PetGo. Мы свяжемся с вами и подберём няню.'),
            ),
            'cta'  => array('Нужно погулять с собакой?', 'Оставьте заявку — подберём няню и согласуем время прогулки.'),
            'art'  => 'art:walk-gold',
        ),
        'catsit' => array(
            'pet'  => 'cat',
            'h1'   => 'Няня для кошек в Алматы',
            'sub'  => 'Кэтситтер PetGo приходит к кошке домой, пока вас нет: кормит, меняет лоток, играет и присылает фото. Неделя ежедневных визитов — {week_cat} ₸ для первого заказа.',
            'btn'  => 'Заказать визиты',
            'note' => 'Хочу заказать: Няня для кошки',
            'why'  => array(
                array('home', 'Кошка остаётся дома', 'Без стресса от переезда и чужих животных: привычная квартира, свой лоток и любимые места.'),
                array('paw', 'Корм, лоток и игры', 'Кормим по вашему режиму, меняем воду и лоток, играем — если кошка разрешает.'),
                array('camera', 'Фото после визита', 'После каждого визита присылаем фото и короткий отчёт.'),
                array('pin', 'Приход по геолокации', 'Няня отмечает приход по геолокации — вы знаете, когда она была у кошки.'),
            ),
            'price' => 'Цены на визиты к кошке',
            'text_h' => 'Кэтситтер вместо передержки',
            'text' => array(
                'Кошки тяжело переносят переезд и чужую обстановку. Поэтому удобнее, когда няня приходит к кошке домой: питомец остаётся в привычной квартире, а вы спокойно уезжаете в отпуск или командировку.',
                'Закажите визиты каждый день на неделю или на нужные даты. Перед первым визитом расскажите о режиме кормления и характере кошки — няня всё учтёт.',
                'Как попасть в квартиру, договоримся заранее: ключи, код или консьерж. Неделя визитов для первого заказа — {week_cat} ₸.',
            ),
            'faq'  => array(
                array('Сколько стоит няня для кошки?', 'Неделя ежедневных визитов — {week_cat} ₸ для первого заказа. Цену за другие даты подскажем в WhatsApp.'),
                array('Что делает няня во время визита?', 'Кормит, меняет воду и лоток, играет с кошкой и присылает фото.'),
                array('Как часто вы приходите?', 'Обычно раз в день. Если нужно чаще, скажите при заказе.'),
                array('Как няня попадёт в квартиру?', 'Договоримся заранее: домофон, код, консьерж или ключи — как удобно вам.'),
                array('Вы приходите и к собакам?', 'Да. Гуляем с собаками и навещаем их дома, пока вас нет.'),
                array('Как заказать?', 'Оставьте заявку на сайте, напишите нам в WhatsApp или закажите в приложении My PetGo. Мы свяжемся с вами и подберём няню.'),
            ),
            'cta'  => array('Уезжаете и некому покормить кошку?', 'Оставьте заявку — подберём няню на нужные даты.'),
            'art'  => 'art:cat-orange',
        ),
    );
    return isset($d[$k]) ? $d[$k] : null;
}

/* «Также:» — ссылки на страницы услуг (кроме текущей) */
function petgo_site_land_links($cur) {
    $pgs = petgo_site_pages();
    $out = array();
    foreach (array('dogwalk', 'catsit', 'services') as $k) {
        if ($k !== $cur && isset($pgs[$k])) {
            $out[] = '<a href="' . esc_url(petgo_site_url($k)) . '">' . esc_html($pgs[$k]['title']) . petgo_site_icon('right') . '</a>';
        }
    }
    return $out ? '<section class="pg-sec pg-sec-tight"><div class="pg-wrap"><nav class="pg-land-links pg-rise" aria-label="' . esc_attr(petgo_site_l('Услуги')) . '">' . implode('', $out) . '</nav></div></section>' : '';
}

function petgo_site_land_faq_html($faq) {
    $h = '<div class="pg-faq pg-rise"><h2 class="pg-h3">' . esc_html(petgo_site_l('Частые вопросы')) . '</h2><div class="pg-faq-list">';
    foreach ($faq as $q) {
        $h .= '<details class="pg-q"><summary>' . esc_html(petgo_site_tx($q[0])) . '<i class="pg-chev" aria-hidden="true"></i></summary><div class="pg-qa"><p>' . esc_html(petgo_site_tx($q[1])) . '</p></div></details>';
    }
    return $h . '</div></div>';
}

add_filter('petgo_site_page_html', function ($h, $page) {
    $d = petgo_site_land_data($page);
    if (!$d) {
        return $h;
    }
    $L  = 'petgo_site_l';
    $tx = 'petgo_site_tx';
    $rv = petgo_site_reviews();
    /* шапка: заголовок с ключевой фразой, цена, две кнопки, оценка */
    $o  = '<header class="pg-phead pg-land-head"><div class="pg-wrap"><div class="pg-land-hero pg-rise"><div class="pg-land-copy"><h1 class="pg-h">' . esc_html($L($d['h1'])) . '</h1><p class="pg-sub">' . esc_html($tx($d['sub'])) . '</p>';
    $o .= '<div class="pg-row"><button type="button" class="pg-b pg-btn pg-btn-primary" data-sheet="request" data-note="' . esc_attr($L($d['note'])) . '" data-species="' . esc_attr($d['pet']) . '">' . esc_html($L($d['btn'])) . '</button><a class="pg-btn pg-btn-second" href="https://wa.me/' . esc_attr(petgo_site_wa()) . '" target="_blank" rel="noopener">' . petgo_site_icon('chat') . 'WhatsApp</a></div>';
    if ($rv) {
        $avg = 0;
        foreach ($rv as $x) {
            $avg += $x['r'];
        }
        $o .= '<ul class="pg-chips"><li class="pg-chip-st"><a href="' . esc_url(petgo_site_url('reviews')) . '">' . petgo_site_icon('star') . '<b>' . esc_html(number_format($avg / count($rv), 1, ',', '')) . '</b> · ' . esc_html(count($rv) . ' ' . petgo_site_n(count($rv), array('отзыв', 'отзыва', 'отзывов'))) . '</a></li></ul>';
    }
    $o .= '</div><div class="pg-land-art pg-ph pg-slider" data-media="' . esc_attr(wp_json_encode(array($d['art']), JSON_UNESCAPED_SLASHES)) . '" aria-hidden="true"></div></div></div></header>';
    /* почему мы */
    $o .= '<section class="pg-sec pg-sec-tight"><div class="pg-wrap"><ul class="pg-land-why">';
    foreach ($d['why'] as $w) {
        $o .= '<li class="pg-rise"><span class="pg-land-i">' . petgo_site_icon($w[0]) . '</span><b>' . esc_html($L($w[1])) . '</b><p>' . esc_html($L($w[2])) . '</p></li>';
    }
    $o .= '</ul></div></section>';
    /* цены — карточки услуг из настроек сайта, только для этого питомца */
    $o .= petgo_site_b_services($L($d['price']), $d['pet']);
    $o .= petgo_site_b_how();
    /* текст для людей и поиска */
    $areas = function_exists('petgo_ads_areas') ? petgo_ads_areas() : array();
    $o .= '<section class="pg-sec pg-seo"><div class="pg-wrap"><div class="pg-seo-in pg-rise"><h2 class="pg-h">' . esc_html($L($d['text_h'])) . '</h2>';
    foreach ($d['text'] as $p) {
        $o .= '<p>' . esc_html($tx($p)) . '</p>';
    }
    if ($areas) {
        $o .= '<p>' . esc_html($L('Работаем в ЖК и районах:') . ' ' . implode(', ', array_slice($areas, 0, 30)) . '.') . '</p>';
    }
    $o .= '</div></div></section>';
    $o .= '<section class="pg-sec"><div class="pg-wrap pg-narrow">' . petgo_site_land_faq_html($d['faq']) . '</div></section>';
    $o .= petgo_site_b_reviews(6);
    $o .= petgo_site_land_links($page);
    return $o . petgo_site_b_cta($L($d['cta'][0]), $L($d['cta'][1]));
}, 10, 2);

/* разметка для поиска: услуга с ценой, компания, вопросы и ответы */
add_filter('petgo_site_schema', function ($g, $page) {
    $d = petgo_site_land_data($page);
    if (!$d) {
        return $g;
    }
    $home = home_url('/');
    $name = trim((string) get_bloginfo('name'));
    $biz  = array('@type' => 'LocalBusiness', '@id' => $home . '#petgo', 'name' => $name !== '' ? $name : 'PetGo.kz', 'url' => $home, 'telephone' => '+' . petgo_site_phone(), 'image' => petgo_asset('icon-512.png'), 'address' => array('@type' => 'PostalAddress', 'addressLocality' => 'Алматы', 'addressCountry' => 'KZ'), 'areaServed' => array('@type' => 'City', 'name' => 'Алматы'));
    $srv  = array('@type' => 'Service', 'name' => petgo_site_l($d['h1']), 'serviceType' => petgo_site_l($page === 'dogwalk' ? 'Выгул собак' : 'Няня для кошек'), 'description' => petgo_site_tx($d['sub']), 'provider' => array('@id' => $home . '#petgo'), 'areaServed' => array('@type' => 'City', 'name' => 'Алматы'), 'url' => petgo_site_url($page));
    $offers = array();
    foreach ((array) petgo_site_cfg()['services'] as $s) {
        if (empty($s['title']) || ($s['pet'] !== $d['pet'] && $s['pet'] !== 'all')) {
            continue;
        }
        $p  = petgo_site_price($s);
        $pp = $p['now'] > 0 ? $p['now'] : $p['old'];
        if ($pp > 0) {
            $offers[] = array('@type' => 'Offer', 'name' => petgo_site_l($s['title']), 'price' => (string) (int) $pp, 'priceCurrency' => 'KZT', 'url' => petgo_site_url($page));
        }
    }
    if ($offers) {
        $srv['offers'] = count($offers) === 1 ? $offers[0] : $offers;
    }
    $faq = array();
    foreach ($d['faq'] as $q) {
        $faq[] = array('@type' => 'Question', 'name' => petgo_site_tx($q[0]), 'acceptedAnswer' => array('@type' => 'Answer', 'text' => petgo_site_tx($q[1])));
    }
    $g[] = $srv;
    $g[] = $biz;
    $g[] = array('@type' => 'FAQPage', 'mainEntity' => $faq);
    return $g;
}, 20, 2);

/* заголовок и описание для Google и Яндекс (можно поменять в «Реклама и SEO») */
add_filter('petgo_ads_seo_defaults', function ($d) {
    /* 39.2: первый заказ бесплатно (скидка 100%) — в заголовке так и пишем, а не «от 0 ₸» */
    $free = (int) petgo_site_cfg()['off'] >= 100;
    $d['dogwalk'] = array($free ? 'Выгул собак в Алматы — первый выгул бесплатно | PetGo' : 'Выгул собак в Алматы — догситтер от {walk} ₸ за час | PetGo', 'Выгул собак в Алматы: обученные няни, прогулка от часа, фото и отчёт после каждого выгула, приход по геолокации. Первый заказ — {walk} ₸ за час. Оставьте заявку.');
    $d['catsit']  = array('Няня для кошек в Алматы — кэтситтер на дом | PetGo', 'Кэтситтер в Алматы: няня приходит к кошке домой — кормит, меняет лоток, играет и присылает фото. Неделя визитов — {week_cat} ₸ для первого заказа.');
    return $d;
});
add_filter('petgo_ads_seo_pages', function ($p) {
    $p['dogwalk'] = 'Выгул собак';
    $p['catsit']  = 'Няня для кошек';
    return $p;
});

add_filter('petgo_site_dict_more', function ($d) {
    return array_merge($d, array(
        'Выгул собак в Алматы' => array('Алматыда итті серуендету', 'Dog walking in Almaty'),
        'Няня для кошек в Алматы' => array('Алматыда мысыққа күтуші', 'Cat sitter in Almaty'),
        'Догситтер PetGo погуляет с вашей собакой в удобное время: прогулка от часа, фото и отчёт после каждого выгула. Первый заказ — {walk} ₸ за час.' => array('PetGo догситтері итіңізбен ыңғайлы уақытта серуендейді: бір сағаттан бастап, әр серуеннен кейін фото мен есеп. Алғашқы тапсырыс — сағатына {walk} ₸.', 'A PetGo dog sitter walks your dog at a time that suits you: walks from one hour, photos and a report after every walk. First order — {walk} ₸ per hour.'),
        'Заказать выгул' => array('Серуенге тапсырыс беру', 'Book a walk'),
        'Хочу заказать: Выгул собак' => array('Тапсырыс бергім келеді: Итті серуендету', 'I want to book: Dog walking'),
        'Обученные няни' => array('Оқытылған күтушілер', 'Trained sitters'),
        'Подберём няню под характер, размер и привычки вашей собаки.' => array('Итіңіздің мінезіне, өлшеміне және әдеттеріне қарай күтуші таңдаймыз.', 'We match a sitter to your dog\'s character, size and habits.'),
        'Фото и отчёт' => array('Фото және есеп', 'Photos and a report'),
        'После каждой прогулки присылаем фото и короткий отчёт: как гуляли и как вела себя собака.' => array('Әр серуеннен кейін фото мен қысқа есеп жібереміз: қалай серуендедік, ит өзін қалай ұстады.', 'After every walk we send photos and a short report: how the walk went and how the dog behaved.'),
        'Приход по геолокации' => array('Геолокация бойынша келу', 'Arrival by geolocation'),
        'Няня отмечает приход по геолокации, а прогулку видно в приложении My PetGo.' => array('Күтуші келгенін геолокация бойынша белгілейді, ал серуенді My PetGo қосымшасынан көруге болады.', 'The sitter checks in by geolocation, and you can see the walk in the My PetGo app.'),
        'Когда удобно вам' => array('Сізге ыңғайлы уақытта', 'When it suits you'),
        'Утром, днём или вечером, разово или каждый день — пока вы на работе или в поездке.' => array('Таңертең, күндіз немесе кешке, бір рет немесе күн сайын — сіз жұмыста немесе сапарда болғанда.', 'Morning, afternoon or evening, once or every day — while you are at work or away.'),
        'Цены на выгул собак' => array('Итті серуендету бағасы', 'Dog walking prices'),
        'Догситтер в Алматы: как проходит выгул' => array('Алматыдағы догситтер: серуен қалай өтеді', 'Dog sitter in Almaty: how a walk works'),
        'Няня приходит в назначенное время, забирает собаку и гуляет с ней не меньше часа — на поводке, по спокойному маршруту, с играми. Если собаке нужно больше движения, скажите об этом при заказе.' => array('Күтуші белгіленген уақытта келіп, итті алып, кемінде бір сағат серуендейді — бауда, тыныш бағытпен, ойындармен. Итке көбірек қозғалыс керек болса, тапсырыс бергенде айтыңыз.', 'The sitter arrives at the agreed time, picks up your dog and walks for at least an hour — on a leash, on a calm route, with games. If your dog needs more exercise, tell us when booking.'),
        'Выгул выручает, когда вы допоздна на работе, заболели, уехали или у щенка слишком много энергии. Можно заказать одну прогулку или ежедневные визиты на неделю — {week} ₸ для первого заказа.' => array('Серуен жұмыста кешіккенде, ауырғанда, кетіп қалғанда немесе күшіктің қуаты тым көп болғанда көмектеседі. Бір серуенге немесе бір аптаға күнделікті келуге тапсырыс беруге болады — алғашқы тапсырыста {week} ₸.', 'Walks help when you work late, are ill, are away, or your puppy has too much energy. Book a single walk or daily visits for a week — {week} ₸ for your first order.'),
        'Обычный выгул — {walk_old} ₸ за час, первый заказ — {walk} ₸. После прогулки няня возвращает собаку домой и присылает фото.' => array('Әдеттегі серуен — сағатына {walk_old} ₸, алғашқы тапсырыс — {walk} ₸. Серуеннен кейін күтуші итті үйге әкеліп, фото жібереді.', 'A regular walk is {walk_old} ₸ per hour, the first order is {walk} ₸. After the walk the sitter brings your dog home and sends photos.'),
        'Сколько стоит выгул собак в Алматы?' => array('Алматыда итті серуендету қанша тұрады?', 'How much does dog walking cost in Almaty?'),
        'Первый заказ — {walk} ₸ за час, дальше {walk_old} ₸ за час. Неделя ежедневных визитов — {week} ₸ для первого заказа.' => array('Алғашқы тапсырыс — сағатына {walk} ₸, кейін сағатына {walk_old} ₸. Бір апта күнделікті келу — алғашқы тапсырыста {week} ₸.', 'First order — {walk} ₸ per hour, then {walk_old} ₸ per hour. A week of daily visits is {week} ₸ for your first order.'),
        'Сколько длится прогулка?' => array('Серуен қанша уақытқа созылады?', 'How long is a walk?'),
        'От часа. Если собаке нужно больше, скажите при заказе — учтём.' => array('Бір сағаттан бастап. Итке көбірек керек болса, тапсырыс бергенде айтыңыз — ескереміз.', 'From one hour. If your dog needs more, tell us when booking.'),
        'С какими собаками вы гуляете?' => array('Қандай иттермен серуендейсіздер?', 'Which dogs do you walk?'),
        'Со щенками и взрослыми собаками разных пород и размеров. Расскажите о характере и привычках — подберём подходящую няню.' => array('Әртүрлі тұқым мен өлшемдегі күшіктермен және ересек иттермен. Мінезі мен әдеттері туралы айтыңыз — лайықты күтуші таңдаймыз.', 'Puppies and adult dogs of different breeds and sizes. Tell us about their character and habits and we will find the right sitter.'),
        'Как я узнаю, что прогулка прошла?' => array('Серуен өткенін қалай білемін?', 'How will I know the walk happened?'),
        'Няня отмечает приход по геолокации и присылает фото после прогулки. В приложении My PetGo видно прогулку и историю заказов.' => array('Күтуші келгенін геолокация бойынша белгілеп, серуеннен кейін фото жібереді. My PetGo қосымшасында серуен мен тапсырыстар тарихы көрінеді.', 'The sitter checks in by geolocation and sends photos after the walk. The My PetGo app shows the walk and your order history.'),
        'Как няня попадёт в квартиру?' => array('Күтуші пәтерге қалай кіреді?', 'How will the sitter get into the flat?'),
        'Договоримся заранее: домофон, код, консьерж или ключи — как удобно вам.' => array('Алдын ала келісеміз: домофон, код, консьерж немесе кілт — өзіңізге ыңғайлысы.', 'We agree in advance: intercom, code, concierge or keys — whatever suits you.'),
        'Как заказать выгул?' => array('Серуенге қалай тапсырыс беремін?', 'How do I book a walk?'),
        'Оставьте заявку на сайте, напишите нам в WhatsApp или закажите в приложении My PetGo. Мы свяжемся с вами и подберём няню.' => array('Сайтта өтінім қалдырыңыз, WhatsApp-қа жазыңыз немесе My PetGo қосымшасында тапсырыс беріңіз. Біз сізбен байланысып, күтуші таңдаймыз.', 'Leave a request on the website, message us on WhatsApp or book in the My PetGo app. We will contact you and find a sitter.'),
        'Нужно погулять с собакой?' => array('Итпен серуендеу керек пе?', 'Need someone to walk your dog?'),
        'Оставьте заявку — подберём няню и согласуем время прогулки.' => array('Өтінім қалдырыңыз — күтуші таңдап, серуен уақытын келісеміз.', 'Leave a request — we will find a sitter and agree on the walk time.'),
        'Кэтситтер PetGo приходит к кошке домой, пока вас нет: кормит, меняет лоток, играет и присылает фото. Неделя ежедневных визитов — {week_cat} ₸ для первого заказа.' => array('PetGo кэтситтері сіз жоқта мысыққа үйге келеді: тамақтандырады, науасын ауыстырады, ойнайды және фото жібереді. Бір апта күнделікті келу — алғашқы тапсырыста {week_cat} ₸.', 'A PetGo cat sitter visits your cat at home while you are away: feeds, changes the litter, plays and sends photos. A week of daily visits is {week_cat} ₸ for your first order.'),
        'Заказать визиты' => array('Келуге тапсырыс беру', 'Book visits'),
        'Хочу заказать: Няня для кошки' => array('Тапсырыс бергім келеді: Мысыққа күтуші', 'I want to book: Cat sitter'),
        'Кошка остаётся дома' => array('Мысық үйде қалады', 'Your cat stays at home'),
        'Без стресса от переезда и чужих животных: привычная квартира, свой лоток и любимые места.' => array('Көшу мен бөтен жануарлардың күйзелісінсіз: таныс пәтер, өз науасы және сүйікті орындары.', 'No stress from moving or other animals: a familiar flat, its own litter box and favourite spots.'),
        'Корм, лоток и игры' => array('Тамақ, науа және ойын', 'Food, litter and play'),
        'Кормим по вашему режиму, меняем воду и лоток, играем — если кошка разрешает.' => array('Сіздің кестеңіз бойынша тамақтандырамыз, су мен науаны ауыстырамыз, ойнаймыз — мысық рұқсат етсе.', 'We feed on your schedule, change water and litter, and play — if your cat allows it.'),
        'Фото после визита' => array('Келуден кейін фото', 'Photos after each visit'),
        'После каждого визита присылаем фото и короткий отчёт.' => array('Әр келуден кейін фото мен қысқа есеп жібереміз.', 'After every visit we send photos and a short report.'),
        'Няня отмечает приход по геолокации — вы знаете, когда она была у кошки.' => array('Күтуші келгенін геолокация бойынша белгілейді — оның мысықта қашан болғанын білесіз.', 'The sitter checks in by geolocation, so you know when they visited your cat.'),
        'Цены на визиты к кошке' => array('Мысыққа келу бағасы', 'Cat visit prices'),
        'Кэтситтер вместо передержки' => array('Мысықты басқа жерге бермей — кэтситтер', 'A cat sitter instead of a cattery'),
        'Кошки тяжело переносят переезд и чужую обстановку. Поэтому удобнее, когда няня приходит к кошке домой: питомец остаётся в привычной квартире, а вы спокойно уезжаете в отпуск или командировку.' => array('Мысықтар көшуді және бөтен ортаны қиын көтереді. Сондықтан күтушінің мысыққа үйге келгені ыңғайлы: жануар таныс пәтерде қалады, ал сіз демалысқа немесе іссапарға алаңсыз кетесіз.', 'Cats find moving and unfamiliar places stressful. That is why it is easier when a sitter comes to your home: your cat stays in a familiar flat and you can travel with peace of mind.'),
        'Закажите визиты каждый день на неделю или на нужные даты. Перед первым визитом расскажите о режиме кормления и характере кошки — няня всё учтёт.' => array('Бір аптаға немесе керек күндерге күн сайын келуге тапсырыс беріңіз. Алғашқы келу алдында тамақтандыру кестесі мен мысықтың мінезі туралы айтыңыз — күтуші бәрін ескереді.', 'Book daily visits for a week or for the dates you need. Before the first visit tell us about feeding times and your cat\'s character — the sitter will take it all into account.'),
        'Как попасть в квартиру, договоримся заранее: ключи, код или консьерж. Неделя визитов для первого заказа — {week_cat} ₸.' => array('Пәтерге қалай кіретінін алдын ала келісеміз: кілт, код немесе консьерж. Алғашқы тапсырыста бір апта келу — {week_cat} ₸.', 'We agree in advance how to get into the flat: keys, a code or the concierge. A week of visits for your first order is {week_cat} ₸.'),
        'Сколько стоит няня для кошки?' => array('Мысыққа күтуші қанша тұрады?', 'How much does a cat sitter cost?'),
        'Неделя ежедневных визитов — {week_cat} ₸ для первого заказа. Цену за другие даты подскажем в WhatsApp.' => array('Бір апта күнделікті келу — алғашқы тапсырыста {week_cat} ₸. Басқа күндердің бағасын WhatsApp-та айтамыз.', 'A week of daily visits is {week_cat} ₸ for your first order. Ask us on WhatsApp for other dates.'),
        'Что делает няня во время визита?' => array('Күтуші келгенде не істейді?', 'What does the sitter do during a visit?'),
        'Кормит, меняет воду и лоток, играет с кошкой и присылает фото.' => array('Тамақтандырады, су мен науаны ауыстырады, мысықпен ойнайды және фото жібереді.', 'Feeds, changes water and litter, plays with your cat and sends photos.'),
        'Как часто вы приходите?' => array('Қаншалықты жиі келесіздер?', 'How often do you visit?'),
        'Обычно раз в день. Если нужно чаще, скажите при заказе.' => array('Әдетте күніне бір рет. Жиірек керек болса, тапсырыс бергенде айтыңыз.', 'Usually once a day. If you need more often, tell us when booking.'),
        'Вы приходите и к собакам?' => array('Иттерге де келесіздер ме?', 'Do you also look after dogs?'),
        'Да. Гуляем с собаками и навещаем их дома, пока вас нет.' => array('Иә. Иттермен серуендейміз және сіз жоқта оларға үйге келеміз.', 'Yes. We walk dogs and visit them at home while you are away.'),
        'Как заказать?' => array('Қалай тапсырыс беремін?', 'How do I book?'),
        'Уезжаете и некому покормить кошку?' => array('Кетесіз бе, ал мысықты тамақтандыратын ешкім жоқ па?', 'Going away with no one to feed your cat?'),
        'Оставьте заявку — подберём няню на нужные даты.' => array('Өтінім қалдырыңыз — керек күндерге күтуші таңдаймыз.', 'Leave a request — we will find a sitter for your dates.'),
        'Выгул собак в Алматы — догситтер от {walk} ₸ за час | PetGo' => array('Алматыда итті серуендету — догситтер сағатына {walk} ₸-ден | PetGo', 'Dog walking in Almaty — dog sitter from {walk} ₸ per hour | PetGo'),
        'Выгул собак в Алматы: обученные няни, прогулка от часа, фото и отчёт после каждого выгула, приход по геолокации. Первый заказ — {walk} ₸ за час. Оставьте заявку.' => array('Алматыда итті серуендету: оқытылған күтушілер, бір сағаттан серуен, әр серуеннен кейін фото мен есеп, геолокация бойынша келу. Алғашқы тапсырыс — сағатына {walk} ₸. Өтінім қалдырыңыз.', 'Dog walking in Almaty: trained sitters, walks from one hour, photos and a report after every walk, check-in by geolocation. First order — {walk} ₸ per hour. Leave a request.'),
        'Няня для кошек в Алматы — кэтситтер на дом | PetGo' => array('Алматыда мысыққа күтуші — үйге кэтситтер | PetGo', 'Cat sitter in Almaty — home visits | PetGo'),
        'Кэтситтер в Алматы: няня приходит к кошке домой — кормит, меняет лоток, играет и присылает фото. Неделя визитов — {week_cat} ₸ для первого заказа.' => array('Алматыда кэтситтер: күтуші мысыққа үйге келеді — тамақтандырады, науасын ауыстырады, ойнайды және фото жібереді. Бір апта келу — алғашқы тапсырыста {week_cat} ₸.', 'Cat sitter in Almaty: a sitter visits your cat at home — feeds, changes the litter, plays and sends photos. A week of visits is {week_cat} ₸ for your first order.'),
        'Подробнее об услугах:' => array('Қызметтер туралы толығырақ:', 'More about our services:'),
        'Выгул собак в Алматы — первый выгул бесплатно | PetGo' => array('Алматыда итті серуендету — алғашқы серуен тегін | PetGo', 'Dog walking in Almaty — first walk free | PetGo'),
    ));
});
