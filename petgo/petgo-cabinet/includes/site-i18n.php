<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ЯЗЫКИ САЙТА: русский (основной), казахский, английский.
   Язык — в адресе: ?lang=kz или ?lang=en (так страницы правильно кэшируются и находятся поиском).
   Тексты интерфейса и стандартные тексты переведены здесь, в словаре.
   Свои тексты из «Настроек сайта» (услуги, слайды, вопросы) переводит помощник «PetGo ИИ» в фоне:
   пока перевода нет, показывается русский текст. Перевод появляется сам через минуту-две.
   ============================================================ */

function petgo_site_langs() {
    return array('ru' => array('RU', 'Русский', 'ru'), 'kz' => array('KZ', 'Қазақша', 'kk'), 'en' => array('EN', 'English', 'en'));
}

function petgo_site_lang($set = null) {
    static $l = null;
    if ($set !== null) {
        $l = isset(petgo_site_langs()[$set]) ? $set : 'ru';
    }
    if ($l === null) {
        $v = '';
        if (isset($_GET['lang'])) {
            $v = sanitize_key(wp_unslash($_GET['lang']));
        } elseif (isset($_POST['lang'])) {
            $v = sanitize_key(wp_unslash($_POST['lang']));
        }
        $v = $v === 'kk' ? 'kz' : $v;
        $l = isset(petgo_site_langs()[$v]) ? $v : 'ru';
    }
    return $l;
}

/* адрес с языком */
function petgo_site_lang_url($url, $lang = null) {
    $lang = $lang === null ? petgo_site_lang() : $lang;
    $url  = remove_query_arg('lang', $url);
    return $lang === 'ru' ? $url : add_query_arg('lang', $lang, $url);
}

/* перевод строки. %s и {off}, {walk} и т. п. внутри строк сохраняются */
function petgo_site_l($s) {
    $s    = (string) $s;
    $lang = petgo_site_lang();
    if ($lang === 'ru' || trim($s) === '') {
        return $s;
    }
    $D = petgo_site_dict();
    if (isset($D[$s])) {
        return $D[$s][$lang === 'kz' ? 0 : 1];
    }
    $t = trim($s);
    if (isset($D[$t])) {
        return $D[$t][$lang === 'kz' ? 0 : 1];
    }
    /* числа, телефоны и т. п. переводить не нужно */
    if (!preg_match('/[А-Яа-яЁё]/u', $s)) {
        return $s;
    }
    $tr = petgo_site_tr_store();
    $k  = md5($s);
    if (isset($tr[$lang][$k])) {
        return $tr[$lang][$k];
    }
    petgo_site_tr_miss($s, $lang);
    return $s;
}

/* перевод + sprintf */
function petgo_site_lf($s) {
    $a = func_get_args();
    $a[0] = petgo_site_l($s);
    return call_user_func_array('sprintf', $a);
}

/* «отзыв / отзыва / отзывов» на языке сайта */
function petgo_site_n($n, $ru) {
    $lang = petgo_site_lang();
    if ($lang === 'kz') {
        return array('отзыв' => 'пікір', 'заказ' => 'тапсырыс')[$ru[0]] ?? $ru[0];
    }
    if ($lang === 'en') {
        $en = array('отзыв' => array('review', 'reviews'), 'заказ' => array('order', 'orders'));
        $f  = isset($en[$ru[0]]) ? $en[$ru[0]] : array($ru[0], $ru[0]);
        return (int) $n === 1 ? $f[0] : $f[1];
    }
    return petgo_site_plural($n, $ru);
}

/* ---------- фоновый перевод своих текстов (через «PetGo ИИ») ---------- */
function petgo_site_tr_store($fresh = false) {
    static $s = null;
    if ($s === null || $fresh) {
        $s = get_option('petgo_site_tr', array());
        $s = is_array($s) ? $s : array();
    }
    return $s;
}

function petgo_site_tr_miss($s, $lang = null) {
    static $miss = array(), $hooked = false;
    if ($s === null) {
        return $miss;
    }
    if (mb_strlen($s) > 2000) {
        return $miss;
    }
    $miss[$lang][md5($s)] = $s;
    if (!$hooked) {
        $hooked = true;
        add_action('shutdown', 'petgo_site_tr_queue');
    }
    return $miss;
}

function petgo_site_tr_queue() {
    /* переводить некому — очередь не копим */
    if (!has_filter('petgo_site_translate')) {
        return;
    }
    $miss = petgo_site_tr_miss(null);
    if (!$miss) {
        return;
    }
    $q = get_option('petgo_site_tr_q', array());
    $q = is_array($q) ? $q : array();
    $n = 0;
    foreach ($miss as $lang => $list) {
        foreach ($list as $k => $s) {
            if (!isset($q[$lang][$k])) {
                $q[$lang][$k] = $s;
                $n++;
            }
        }
        $q[$lang] = array_slice($q[$lang], -300, null, true);
    }
    if ($n) {
        update_option('petgo_site_tr_q', $q, false);
    }
    if (!wp_next_scheduled('petgo_site_tr_run')) {
        wp_schedule_single_event(time() + 20, 'petgo_site_tr_run');
    }
}

add_action('petgo_site_tr_run', 'petgo_site_tr_run');
function petgo_site_tr_run() {
    $q = get_option('petgo_site_tr_q', array());
    if (!is_array($q) || !$q) {
        return;
    }
    $store = petgo_site_tr_store(true);
    $left  = false;
    foreach ($q as $lang => $list) {
        if (!$list) {
            unset($q[$lang]);
            continue;
        }
        /* за один раз — до 40 строк на язык, остальное — следующим запуском */
        $part = array_slice($list, 0, 40, true);
        $res  = apply_filters('petgo_site_translate', null, $part, $lang);
        if (!is_array($res)) {
            /* ИИ недоступен — попробуем позже */
            $left = true;
            continue;
        }
        foreach ($part as $k => $s) {
            if (isset($res[$k]) && is_string($res[$k]) && trim($res[$k]) !== '') {
                $store[$lang][$k] = wp_strip_all_tags($res[$k]);
            }
            unset($q[$lang][$k]);
        }
        if (count($store[$lang]) > 800) {
            $store[$lang] = array_slice($store[$lang], -800, null, true);
        }
        if ($q[$lang]) {
            $left = true;
        }
    }
    update_option('petgo_site_tr', $store, false);
    update_option('petgo_site_tr_q', $q, false);
    if ($left && !wp_next_scheduled('petgo_site_tr_run')) {
        wp_schedule_single_event(time() + 120, 'petgo_site_tr_run');
    }
    if (function_exists('petgo_site_flush_cache')) {
        petgo_site_flush_cache();
    }
}

/* ---------- переключатель языка ---------- */
/* $menu = true (вверху): на телефоне видна только текущая кнопка, остальные — в выпадающем окошке */
function petgo_site_lang_switch($cls = '', $menu = false) {
    $cur = petgo_site_lang();
    $L   = petgo_site_langs();
    $url = (is_ssl() ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '') . (isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/');
    $a   = function ($k, $v) use ($url, $cur) {
        return '<a href="' . esc_url(petgo_site_lang_url($url, $k)) . '" hreflang="' . esc_attr($v[2]) . '" lang="' . esc_attr($v[2]) . '" data-lang="' . esc_attr($k) . '" title="' . esc_attr($v[1]) . '"' . ($k === $cur ? ' class="on" aria-current="true"' : '') . '>' . esc_html($v[0]) . '</a>';
    };
    $h = '<div class="pg-langs' . ($cls !== '' ? ' ' . esc_attr($cls) : '') . '" role="group" aria-label="' . esc_attr(petgo_site_l('Язык')) . '">';
    if (!$menu) {
        foreach ($L as $k => $v) {
            $h .= $a($k, $v);
        }
        return $h . '</div>';
    }
    $h .= $a($cur, $L[$cur]) . '<div class="pg-langs-more">';
    foreach ($L as $k => $v) {
        if ($k !== $cur) {
            $h .= $a($k, $v);
        }
    }
    return $h . '</div></div>';
}

/* строки для скриптов сайта (ui.js, site.js, клавиатура) */
function petgo_site_js_dict() {
    if (petgo_site_lang() === 'ru') {
        return new stdClass();
    }
    $o = array();
    foreach (array(
        'Укажите имя.', 'Проверьте номер телефона.', 'Выберите, кто у вас.', 'Напишите, какое это животное.', 'Укажите кличку питомца.',
        'Нужно согласие на обработку данных.', 'Не получилось отправить — проверьте интернет и попробуйте ещё раз', ' или позвоните нам: ',
        'Сайт ответил ошибкой (', '). Попробуйте ещё раз', 'Фото', 'Оценка', 'из 5', 'Открыть фото', 'читать далее', 'Отзывов пока нет',
        'Системная клавиатура', 'Готово', 'Стереть', 'Заглавные', 'Далее', 'Пробел', 'Язык клавиатуры', 'Хочу няню: %s',
    ) as $s) {
        $o[$s] = petgo_site_l($s);
    }
    $lang = petgo_site_lang();
    $o['#rv'] = $lang === 'kz' ? array('пікір', 'пікір', 'пікір') : array('review', 'reviews', 'reviews');
    return $o;
}

/* ============================================================
   СЛОВАРЬ: русский => [казахский, английский]
   ============================================================ */
function petgo_site_dict() {
    static $D = null;
    if ($D !== null) {
        return $D;
    }
    $D = array(
        /* страницы и меню */
        'Главная' => array('Басты бет', 'Home'),
        'Услуги' => array('Қызметтер', 'Services'),
        'Услуги и цены' => array('Қызметтер мен бағалар', 'Services & prices'),
        'Няни' => array('Күтушілер', 'Sitters'),
        'Наши няни' => array('Біздің күтушілер', 'Our sitters'),
        'Отзывы' => array('Пікірлер', 'Reviews'),
        'Кабинет' => array('Кабинет', 'Account'),
        'Меню' => array('Мәзір', 'Menu'),
        'Язык' => array('Тіл', 'Language'),
        'PetGo.kz — на главную' => array('PetGo.kz — басты бетке', 'PetGo.kz — home'),
        'Позвонить: %s' => array('Қоңырау шалу: %s', 'Call: %s'),
        'Оставить заявку' => array('Өтінім қалдыру', 'Request a sitter'),
        'Разделы' => array('Бөлімдер', 'Sections'),
        'Помощник' => array('Көмекші', 'Assistant'),
        'Оставьте заявку' => array('Өтінім қалдырыңыз', 'Leave a request'),
        'Мы свяжемся с вами и подберём няню.' => array('Біз сізбен хабарласып, күтуші таңдаймыз.', 'We will contact you and find a sitter.'),
        'Регистрация питомца' => array('Үй жануарын тіркеу', 'Pet registration'),
        'Расскажите о питомце — данные попадут к нам в базу.' => array('Үй жануарыңыз туралы айтыңыз — деректер біздің базаға түседі.', 'Tell us about your pet — the details go straight to our database.'),
        'Назад' => array('Артқа', 'Back'),
        'Предыдущий' => array('Алдыңғы', 'Previous'),
        'Следующий' => array('Келесі', 'Next'),
        'Закрыть' => array('Жабу', 'Close'),
        'Няня гуляет с собакой' => array('Күтуші итті серуендетіп жүр', 'A sitter walking a dog'),
        'Выберите услугу' => array('Қызметті таңдаңыз', 'Choose a service'),
        'Цены указаны для первого заказа со скидкой %s.' => array('Бағалар %s жеңілдікпен алғашқы тапсырыс үшін көрсетілген.', 'Prices shown are for your first order with a %s discount.'),
        'Кто у вас дома' => array('Үйіңізде кім бар', 'Who lives with you'),
        'Собака' => array('Ит', 'Dog'),
        'Кошка' => array('Мысық', 'Cat'),
        'Здравствуйте! Хочу заказать: %1$s (для %2$s).' => array('Сәлеметсіз бе! Тапсырыс бергім келеді: %1$s (%2$s үшін).', 'Hello! I would like to order: %1$s (for my %2$s).'),
        'собаки' => array('ит', 'dog'),
        'кошки' => array('мысық', 'cat'),
        'питомца' => array('үй жануары', 'pet'),
        'потом' => array('кейін', 'then'),
        'Заказать' => array('Тапсырыс беру', 'Order'),
        'Как это работает' => array('Бұл қалай жұмыс істейді', 'How it works'),
        'Три простых шага.' => array('Үш қарапайым қадам.', 'Three simple steps.'),
        'Пишете нам' => array('Бізге жазасыз', 'You message us'),
        'Расскажите, кто ваш питомец и что он любит.' => array('Үй жануарыңыз кім екенін және не ұнататынын айтыңыз.', 'Tell us about your pet and what it loves.'),
        'Подбираем няню' => array('Күтуші таңдаймыз', 'We find a sitter'),
        'Обсуждаем детали и находим подходящий формат ухода.' => array('Егжей-тегжейін талқылап, күтімнің қолайлы түрін табамыз.', 'We discuss the details and find the right kind of care.'),
        'Отдыхаете спокойно' => array('Алаңсыз демаласыз', 'You relax'),
        'Няня заботится о питомце и присылает вам фото.' => array('Күтуші үй жануарыңызға қамқорлық жасап, сізге фото жібереді.', 'The sitter looks after your pet and sends you photos.'),
        'Хозяева доверяют нам своих питомцев' => array('Иелер бізге үй жануарларын сеніп тапсырады', 'Owners trust us with their pets'),
        'Настоящие отзывы наших клиентов.' => array('Клиенттеріміздің шынайы пікірлері.', 'Real reviews from our clients.'),
        'Все отзывы · %d' => array('Барлық пікірлер · %d', 'All reviews · %d'),
        'Частые вопросы' => array('Жиі қойылатын сұрақтар', 'FAQ'),
        'Мы свяжемся с вами и подберём няню для питомца.' => array('Біз сізбен хабарласып, үй жануарыңызға күтуші таңдаймыз.', 'We will contact you and find a sitter for your pet.'),
        'Подберём няню под вашего питомца' => array('Үй жануарыңызға лайық күтуші таңдаймыз', 'We will match a sitter to your pet'),
        'Напишем в WhatsApp или позвоним — как удобно' => array('WhatsApp-қа жазамыз немесе қоңырау шаламыз — қалай ыңғайлы болса', 'We will message you on WhatsApp or call — whichever you prefer'),
        'Няня для питомцев в Алматы' => array('Алматыдағы үй жануарларының күтушісі', 'Pet sitters in Almaty'),
        'Первый заказ — со скидкой %s.' => array('Алғашқы тапсырысқа — %s жеңілдік.', 'Your first order is %s off.'),
        'Выберите, что нужно вашему питомцу.' => array('Үй жануарыңызға не қажет екенін таңдаңыз.', 'Choose what your pet needs.'),
        'Не нашли нужную услугу?' => array('Қажетті қызметті таппадыңыз ба?', 'Didn’t find what you need?'),
        'Напишите нам — обсудим, что нужно вашему питомцу.' => array('Бізге жазыңыз — үй жануарыңызға не қажет екенін талқылаймыз.', 'Message us — we will discuss what your pet needs.'),
        'Подберём няню для вас' => array('Сізге күтуші таңдаймыз', 'We will find a sitter for you'),
        'Оставьте заявку — расскажем о няне, которая подойдёт вашему питомцу.' => array('Өтінім қалдырыңыз — үй жануарыңызға лайық күтуші туралы айтамыз.', 'Leave a request — we will tell you about the right sitter for your pet.'),
        'Настоящие отзывы хозяев. Мы публикуем их после завершённого заказа.' => array('Иелердің шынайы пікірлері. Біз оларды тапсырыс аяқталғаннан кейін жариялаймыз.', 'Real reviews from owners, published after a completed order.'),
        'Отзывов пока нет. Они появятся здесь после первых заказов.' => array('Әзірге пікірлер жоқ. Олар алғашқы тапсырыстардан кейін осында пайда болады.', 'No reviews yet. They will appear here after the first orders.'),
        'Хотите так же?' => array('Сіз де осылай қалайсыз ба?', 'Want the same?'),
        'Оставьте заявку, и мы подберём няню вашему питомцу.' => array('Өтінім қалдырыңыз, біз үй жануарыңызға күтуші таңдаймыз.', 'Leave a request and we will find a sitter for your pet.'),
        'Зарегистрируйте питомца или войдите как сотрудник PetGo.' => array('Үй жануарыңызды тіркеңіз немесе PetGo қызметкері ретінде кіріңіз.', 'Register your pet or sign in as a PetGo team member.'),
        'Зарегистрировать питомца' => array('Үй жануарын тіркеу', 'Register a pet'),
        'Собаку, кошку или другого питомца. Анкета попадёт к нам в базу, и мы свяжемся с вами.' => array('Итті, мысықты немесе басқа үй жануарын. Сауалнама біздің базаға түседі, біз сізбен хабарласамыз.', 'A dog, cat or any other pet. The form goes to our database and we will contact you.'),
        'Войти как сотрудник' => array('Қызметкер ретінде кіру', 'Team sign-in'),
        'Для нянь, менеджеров и администраторов PetGo. Вход по логину и паролю.' => array('PetGo күтушілері, менеджерлері мен әкімшілеріне арналған. Логин және құпиясөз арқылы кіру.', 'For PetGo sitters, managers and admins. Sign in with your login and password.'),

        /* няни */
        'Проверенные няни PetGo. Подберём ту, что подойдёт вашему питомцу.' => array('PetGo-ның тексерілген күтушілері. Үй жануарыңызға лайығын таңдаймыз.', 'Trusted PetGo sitters. We will match the right one to your pet.'),
        'Скоро здесь появятся анкеты нянь PetGo.' => array('Жақында мұнда PetGo күтушілерінің сауалнамалары пайда болады.', 'PetGo sitter profiles will appear here soon.'),
        'Няня PetGo' => array('PetGo күтушісі', 'PetGo sitter'),
        'Новая няня' => array('Жаңа күтуші', 'New sitter'),
        'Выполнено заказов: %d' => array('Орындалған тапсырыстар: %d', 'Completed orders: %d'),
        'Выбрать эту няню' => array('Осы күтушіні таңдау', 'Choose this sitter'),
        'Хочу няню: %s' => array('Маған осы күтуші керек: %s', 'I would like this sitter: %s'),

        /* форма */
        'Имя' => array('Аты', 'Name'),
        'Как к вам обращаться' => array('Сізге қалай жүгінейік', 'What should we call you'),
        'Телефон' => array('Телефон', 'Phone'),
        'Как с вами связаться' => array('Сізбен қалай байланысайық', 'How should we contact you'),
        'Позвонить' => array('Қоңырау шалу', 'Call'),
        'Кто у вас' => array('Сізде кім бар', 'Your pet'),
        'Другой' => array('Басқа', 'Other'),
        'Кто именно' => array('Нақты кім', 'What kind'),
        'Например, кролик' => array('Мысалы, қоян', 'e.g. a rabbit'),
        'Кличка' => array('Аты', 'Pet’s name'),
        'Как зовут питомца' => array('Үй жануарының аты кім', 'Your pet’s name'),
        'Порода' => array('Тұқымы', 'Breed'),
        'Например, лабрадор' => array('Мысалы, лабрадор', 'e.g. Labrador'),
        'Необязательно' => array('Міндетті емес', 'Optional'),
        'Возраст' => array('Жасы', 'Age'),
        'Например, 3 года' => array('Мысалы, 3 жаста', 'e.g. 3 years'),
        'Вес, кг' => array('Салмағы, кг', 'Weight, kg'),
        'Особенности' => array('Ерекшеліктері', 'Special notes'),
        'Комментарий' => array('Түсініктеме', 'Comment'),
        'Характер, здоровье, привычки, что любит' => array('Мінезі, денсаулығы, әдеттері, не ұнатады', 'Temperament, health, habits, what it loves'),
        'Что важно знать о питомце и когда нужна няня' => array('Үй жануары туралы не білу маңызды және күтуші қашан керек', 'What we should know about your pet and when you need a sitter'),
        'Согласен на обработку персональных данных' => array('Дербес деректерді өңдеуге келісемін', 'I agree to the processing of my personal data'),
        'Отправить заявку' => array('Өтінім жіберу', 'Send request'),
        'Питомец зарегистрирован' => array('Үй жануары тіркелді', 'Pet registered'),
        'Заявка отправлена' => array('Өтінім жіберілді', 'Request sent'),
        'Спасибо! Анкета уже у нас. Свяжемся с вами, чтобы уточнить детали.' => array('Рақмет! Сауалнама бізде. Егжей-тегжейін нақтылау үшін сізбен хабарласамыз.', 'Thank you! We have your form and will contact you to confirm the details.'),
        'Спасибо! Уже смотрим вашу заявку и скоро ответим.' => array('Рақмет! Өтініміңізді қарап жатырмыз, жақында жауап береміз.', 'Thank you! We are already looking at your request and will reply soon.'),
        'Добавить ещё питомца' => array('Тағы бір үй жануарын қосу', 'Add another pet'),
        'Отправить ещё одну' => array('Тағы біреуін жіберу', 'Send another'),
        'Обновите страницу и отправьте ещё раз.' => array('Бетті жаңартып, қайта жіберіңіз.', 'Please refresh the page and send again.'),
        'Секунду… Нажмите «Отправить» ещё раз.' => array('Бір сәт… «Жіберу» түймесін қайта басыңыз.', 'One moment… Tap “Send” again.'),
        'Слишком много заявок с вашего адреса. Попробуйте позже или позвоните нам: %s.' => array('Сіздің мекенжайыңыздан өтінімдер тым көп. Кейінірек қайталаңыз немесе бізге қоңырау шалыңыз: %s.', 'Too many requests from your address. Try later or call us: %s.'),
        'Укажите имя.' => array('Атыңызды көрсетіңіз.', 'Please enter your name.'),
        'Проверьте номер телефона.' => array('Телефон нөмірін тексеріңіз.', 'Please check the phone number.'),
        'Выберите, кто у вас: собака, кошка или другой питомец.' => array('Сізде кім бар екенін таңдаңыз: ит, мысық немесе басқа үй жануары.', 'Choose your pet: dog, cat or other.'),
        'Выберите, кто у вас.' => array('Сізде кім бар екенін таңдаңыз.', 'Choose your pet.'),
        'Напишите, какое это животное.' => array('Бұл қандай жануар екенін жазыңыз.', 'Tell us what kind of animal it is.'),
        'Укажите кличку питомца.' => array('Үй жануарының атын көрсетіңіз.', 'Please enter your pet’s name.'),
        'Нужно согласие на обработку данных.' => array('Деректерді өңдеуге келісім қажет.', 'Consent to data processing is required.'),
        'Не получилось сохранить. Попробуйте ещё раз или позвоните нам: %s.' => array('Сақтау мүмкін болмады. Қайталап көріңіз немесе бізге қоңырау шалыңыз: %s.', 'Could not save. Try again or call us: %s.'),
        'Не получилось отправить — проверьте интернет и попробуйте ещё раз' => array('Жіберу мүмкін болмады — интернетті тексеріп, қайталап көріңіз', 'Could not send — check your connection and try again'),
        ' или позвоните нам: ' => array(' немесе бізге қоңырау шалыңыз: ', ' or call us: '),
        'Сайт ответил ошибкой (' => array('Сайт қатемен жауап берді (', 'The site returned an error ('),
        '). Попробуйте ещё раз' => array('). Қайталап көріңіз', '). Please try again'),

        /* отзывы и фото (скрипты) */
        'Фото' => array('Фото', 'Photo'),
        'Оценка' => array('Баға', 'Rating'),
        'из 5' => array('/ 5', 'of 5'),
        'Открыть фото' => array('Фотоны ашу', 'Open photo'),
        'читать далее' => array('толығырақ', 'read more'),
        'Отзывов пока нет' => array('Әзірге пікірлер жоқ', 'No reviews yet'),

        /* клавиатура */
        'Системная клавиатура' => array('Жүйелік пернетақта', 'System keyboard'),
        'Готово' => array('Дайын', 'Done'),
        'Стереть' => array('Өшіру', 'Delete'),
        'Заглавные' => array('Бас әріптер', 'Shift'),
        'Далее' => array('Келесі', 'Next'),
        'Пробел' => array('Бос орын', 'Space'),
        'Язык клавиатуры' => array('Пернетақта тілі', 'Keyboard language'),

        /* помощник (плагин «PetGo ИИ») */
        'Помощник PetGo' => array('PetGo көмекшісі', 'PetGo assistant'),
        'PetGo · Алматы' => array('PetGo · Алматы', 'PetGo · Almaty'),
        'Начать заново' => array('Қайта бастау', 'Start over'),
        'Спросите о ценах и услугах' => array('Бағалар мен қызметтер туралы сұраңыз', 'Ask about prices and services'),
        'Ваш вопрос' => array('Сіздің сұрағыңыз', 'Your question'),
        'Голосовое сообщение' => array('Дауыстық хабарлама', 'Voice message'),
        'Отправить' => array('Жіберу', 'Send'),
        'ИИ может ошибаться. Точные детали уточняйте у нас' => array('ЖИ қателесуі мүмкін. Нақты мәліметті бізден сұраңыз', 'AI can make mistakes. Please confirm details with us'),
        'Здравствуйте! Я помощник PetGo 🐾 Расскажу про выгул, визиты к питомцу, цены и помогу оставить заявку. Что вас интересует?' => array('Сәлеметсіз бе! Мен PetGo көмекшісімін 🐾 Серуендету, үй жануарына бару, бағалар туралы айтып, өтінім қалдыруға көмектесемін. Сізді не қызықтырады?', 'Hello! I am the PetGo assistant 🐾 I can tell you about dog walks, home visits and prices, and help you leave a request. What would you like to know?'),
        'Здравствуйте! Пишу с сайта PetGo.' => array('Сәлеметсіз бе! PetGo сайтынан жазып отырмын.', 'Hello! I am writing from the PetGo website.'),
        'Не получилось ответить — похоже, пропала связь. Напишите нам в WhatsApp или позвоните' => array('Жауап беру мүмкін болмады — байланыс үзілген сияқты. Бізге WhatsApp-қа жазыңыз немесе қоңырау шалыңыз', 'Could not answer — the connection seems to be lost. Message us on WhatsApp or call'),
        'Как заказать няню?' => array('Күтушіге қалай тапсырыс беруге болады?', 'How do I book a sitter?'),

        /* стандартные тексты «Настроек сайта» */
        'Няня для вашего питомца в Алматы' => array('Алматыдағы үй жануарыңыздың күтушісі', 'A sitter for your pet in Almaty'),
        'Гуляем с собаками и навещаем собак и кошек дома.' => array('Иттерді серуендетеміз, үйдегі иттер мен мысықтарға барамыз.', 'We walk dogs and visit dogs and cats at home.'),
        'Первый заказ со скидкой {off}' => array('Алғашқы тапсырысқа {off} жеңілдік', '{off} off your first order'),
        'Выгул собаки — {walk} ₸ за час вместо {walk_old} ₸.' => array('Итті серуендету — сағатына {walk_old} ₸ орнына {walk} ₸.', 'Dog walking — {walk} ₸ per hour instead of {walk_old} ₸.'),
        'Фото после каждого визита' => array('Әр барудан кейін фото', 'Photos after every visit'),
        'Вы видите, как прошла прогулка или визит.' => array('Серуен немесе бару қалай өткенін көресіз.', 'You see how the walk or visit went.'),
        'Уезжаете в отпуск?' => array('Демалысқа кетіп барасыз ба?', 'Going on holiday?'),
        'Будем приходить к питомцу каждый день: покормим и поиграем.' => array('Үй жануарыңызға күн сайын келіп, тамақтандырамыз және ойнаймыз.', 'We will visit your pet every day to feed and play.'),
        'Обученные няни' => array('Оқытылған күтушілер', 'Trained sitters'),
        'Приход няни отмечается по геолокации' => array('Күтушінің келуі геолокация бойынша белгіленеді', 'Sitter arrival is confirmed by geolocation'),
        'Выгул собак' => array('Итті серуендету', 'Dog walking'),
        'Активная прогулка с обученной няней. Собака двигается, играет и возвращается довольной.' => array('Оқытылған күтушімен белсенді серуен. Ит қозғалады, ойнайды және риза болып оралады.', 'An active walk with a trained sitter. Your dog moves, plays and comes home happy.'),
        'Безопасная прогулка на поводке' => array('Қарғыбаумен қауіпсіз серуен', 'Safe on-leash walk'),
        'Игры по пути' => array('Жол бойы ойындар', 'Games along the way'),
        'Фото и видео с прогулки' => array('Серуеннен фото және видео', 'Photos and videos from the walk'),
        'за час' => array('сағатына', 'per hour'),
        'Заказать выгул' => array('Серуенге тапсырыс беру', 'Book a walk'),
        'Ежедневные визиты на неделю' => array('Бір аптаға күнделікті бару', 'Daily visits for a week'),
        'Приходим к питомцу домой каждый день, чтобы он был сыт и не скучал.' => array('Үй жануарыңыз тоқ болып, жалықпауы үшін күн сайын үйге келеміз.', 'We come to your home every day so your pet is fed and never bored.'),
        'Для отпуска и поездок' => array('Демалыс пен сапарларға', 'For holidays and trips'),
        'Навещаем каждый день' => array('Күн сайын барамыз', 'Daily visits'),
        'Кормим и гуляем с собакой' => array('Итті тамақтандырып, серуендетеміз', 'We feed and walk your dog'),
        'Присылаем фото' => array('Фото жібереміз', 'We send photos'),
        'за неделю' => array('аптасына', 'per week'),
        'Заказать на неделю' => array('Бір аптаға тапсырыс беру', 'Book a week'),
        'Кормим, меняем лоток и играем' => array('Тамақтандырамыз, науаны ауыстырамыз және ойнаймыз', 'We feed, change the litter and play'),
        'Сколько стоит выгул?' => array('Серуендету қанша тұрады?', 'How much is a walk?'),
        'Первый заказ — {walk} ₸ за час, дальше {walk_old} ₸ за час.' => array('Алғашқы тапсырыс — сағатына {walk} ₸, кейін сағатына {walk_old} ₸.', 'First order — {walk} ₸ per hour, then {walk_old} ₸ per hour.'),
        'Как проходит прогулка?' => array('Серуен қалай өтеді?', 'How does a walk work?'),
        'Няня приходит в назначенное время, отмечает приход по геолокации, гуляет с собакой и присылает фото.' => array('Күтуші белгіленген уақытта келеді, келуін геолокация бойынша белгілейді, итті серуендетіп, фото жібереді.', 'The sitter arrives on time, checks in by geolocation, walks your dog and sends photos.'),
        'Вы ухаживаете за кошками?' => array('Мысықтарға да күтім жасайсыздар ма?', 'Do you look after cats?'),
        'Да. Приходим домой каждый день: кормим, меняем лоток, играем и присылаем фото. Неделя визитов для первого заказа — {week} ₸.' => array('Иә. Күн сайын үйге келеміз: тамақтандырамыз, науаны ауыстырамыз, ойнаймыз және фото жібереміз. Алғашқы тапсырыс үшін бір апта бару — {week} ₸.', 'Yes. We visit every day: feed, change the litter, play and send photos. A week of visits for your first order is {week} ₸.'),
        'Как няня попадёт в квартиру?' => array('Күтуші пәтерге қалай кіреді?', 'How will the sitter get into the flat?'),
        'Договоримся заранее: домофон, код, консьерж или ключи — как удобно вам.' => array('Алдын ала келісеміз: домофон, код, консьерж немесе кілттер — сізге қалай ыңғайлы болса.', 'We agree in advance: intercom, code, concierge or keys — whatever suits you.'),
        'Как заказать?' => array('Қалай тапсырыс беруге болады?', 'How do I order?'),
        'Заполните заявку на сайте или напишите нам в WhatsApp. Мы свяжемся с вами и подберём няню.' => array('Сайтта өтінім толтырыңыз немесе бізге WhatsApp-қа жазыңыз. Біз сізбен хабарласып, күтуші таңдаймыз.', 'Fill in a request on the site or message us on WhatsApp. We will contact you and find a sitter.'),
    );
    return $D;
}
