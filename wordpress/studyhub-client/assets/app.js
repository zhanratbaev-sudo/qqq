/* StudyHub — кабинет студента 3.0 (он же сайт).
   Структура и движение — как в приложении iPhone: листы снизу (смахиваются вниз), переключатели с едущей плашкой,
   «капля» под выбранной вкладкой, свайп между вкладками, поле с ошибкой «качает головой», галочка после отправки.
   Гость: вход (пароль или Face ID), заявка без регистрации, ИИ. Чат с администратором — после принятой заявки. */
(function () {
  'use strict';

  var C = window.STUDYHUB || {};
  var D = document, W = window;
  var root = D.getElementById('app');
  var reduce = W.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var LS = store('localStorage'), SS = store('sessionStorage');
  var IOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var STANDALONE = (W.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;

  /* ================================================================ тексты */
  var I18N = {
    ru: {
      nav_home: 'Главная', nav_services: 'Услуги', nav_chat: 'Чат', nav_promos: 'Акции', nav_ai: 'ИИ', nav_profile: 'Профиль',
      login: 'Логин', password: 'Пароль', sign_in: 'Войти', sign_in_face: 'Войти с {bio}', show_pass: 'Показать пароль',
      bad_credentials: 'Неверный логин или пароль', too_many: 'Слишком много попыток. Попробуйте через 15 минут.',
      other_device: 'Этот логин уже открыт на другом телефоне или в браузере. Нажмите там «Выйти» или попросите администратора сбросить устройство.',
      network: 'Нет соединения. Проверьте интернет.', fill_login: 'Введите логин и пароль',
      gate_hi: 'Вход в кабинет', gate_sub: 'Логин и пароль выдаёт администратор — после вашей заявки.',
      no_login: 'Нет логина?', leave_request: 'Оставить заявку', ask_ai: 'Спросить ИИ', ask_ai_sub: 'Ответит сразу, без регистрации',
      req_title: 'Заявка', req_sub: 'Опишите задачу — администратор ответит прямо здесь, на этом телефоне.',
      req_service: 'Что нужно', req_task: 'Задача', req_task_ph: 'Предмет, что сделать, требования преподавателя, формат…',
      req_deadline: 'Срок', dl_today: 'Сегодня', dl_tomorrow: 'Завтра', dl_3: '3 дня', dl_week: 'Неделя', dl_date: 'Дата',
      req_uni: 'Университет', req_contacts: 'Как с вами связаться', req_tg: 'Telegram', req_tg_ph: 'username', req_phone: 'Телефон',
      req_contacts_note: 'Хватит одного. Имя не нужно. Контакты видит только администратор, они стираются, когда откроется чат.',
      req_send: 'Отправить заявку', req_sent: 'Заявка отправлена', req_sent_sub: 'Ответ придёт сюда. Откройте сайт с этого же телефона.',
      req_task_short: 'Опишите задачу подробнее (хотя бы пару предложений)', req_need_contact: 'Укажите Telegram или телефон',
      req_limit: 'Слишком много заявок за сутки. Попробуйте завтра.', optional: 'необязательно',
      st_new: 'Заявка #{n} · ждёт ответа', st_answered: 'Заявка #{n} · есть ответ', st_accepted: 'Заявка #{n} принята', st_declined: 'Заявка #{n} отклонена',
      st_new_sub: 'Обычно отвечаем в течение часа. Пока можно спросить ИИ.', st_accepted_sub: 'Чат с администратором открыт — ваш логин и пароль готовы.',
      open_chat: 'Открыть чат', new_request: 'Новая заявка', admin_reply: 'Ответ администратора', your_task: 'Ваша задача',
      save_title: 'Сохраните вход', save_sub: 'iPhone предложит сохранить пароль в «Пароли» — нажмите «Сохранить». Потом можно входить по Face ID.',
      save_btn: 'Сохранить и открыть чат',
      shh_title: 'Тсс… это между нами 🤫', shh_text: 'Никому не рассказывайте о сервисе — особенно преподавателям: некоторые специально ищут студентов, которым помогают. Мы не храним имён, не публикуем работы и не пишем первыми.',
      handmade: 'Все работы — вручную, без компьютера и без ИИ.',
      install_t: 'Установите приложение', install_s: 'Сначала установите, потом входите — логин работает только на одном устройстве.',
      install_ios: ['Нажмите «Поделиться» внизу Safari', 'Выберите «На экран „Домой“»', 'Откройте приложение с экрана «Домой» и войдите'],
      install_other: ['Откройте меню браузера (⋮)', 'Выберите «Установить приложение»', 'Откройте приложение и войдите'],
      choose_uni: 'Выберите университет', uni_sheet: 'Ваш университет', unis_title: 'Университеты', all: 'Все',
      hero_btn: 'Оставить заявку', order: 'Заказать', price_chat: 'Цена — в чате', promo_more: 'Узнать больше',
      adv_title: 'Наши преимущества', adv1: 'Анонимность', adv2: 'Только ручная работа', adv3: 'Соблюдение сроков',
      reviews: 'Отзывы студентов', reviews_count: '({n} отзывов)', no_reviews: 'Отзывов пока нет.', student: 'Студент', student_of: 'Студент · {uni}',
      materials: 'Полезные материалы', materials_sub: 'Лекции, шпаргалки и примеры — бесплатно.', no_materials: 'Материалы скоро появятся.',
      promos_sub: 'Скидки и спецпредложения для студентов.', no_promos: 'Сейчас акций нет — загляните позже.',
      services_title: 'Услуги', services_sub: 'Выберите, что нужно сделать — детали, цену и сроки обсудим в чате.',
      order_text: 'Здравствуйте! Хочу заказать: {svc}.\nПредмет: \nСрок: \n', order_promo: 'Здравствуйте! Хочу воспользоваться акцией «{p}».\n',
      chat_on: 'В сети', chat_off: 'Не в сети — ответим позже', chat_anon: 'Анонимно', today: 'Сегодня', yesterday: 'Вчера',
      placeholder: 'Сообщение', attach: 'Прикрепить файл', send: 'Отправить', remove: 'Убрать', retry: 'Не отправлено · нажмите, чтобы повторить',
      new_msgs: 'Новые сообщения', file_too_large: 'Файл слишком большой (макс. {mb} МБ)', file_type: 'Этот тип файла не поддерживается', send_failed: 'Не удалось отправить',
      ai_suggest_t: 'Пока мы не ответили', ai_suggest_s: 'ИИ-помощник может ответить прямо сейчас на общие вопросы об учёбе.', ai_suggest_btn: 'Спросить ИИ', ai_suggest_wait: 'Подожду',
      offline_note: 'Мы сейчас не в сети, но обязательно ответим.',
      review_t: 'Оцените нашу работу', review_s: 'Отзыв анонимный: имя, фото и контакты не публикуются.', review_ph: 'Что понравилось?', review_send: 'Отправить анонимно',
      review_thanks: 'Спасибо за отзыв!', review_pending: 'Опубликуем после проверки.', review_short: 'Напишите хотя бы пару слов', review_rate: 'Поставьте оценку',
      ai_title: 'ИИ-помощник', ai_hello_t: 'Чем помочь?', ai_hello_s: 'Спросите про задания, архитектуру, программы или учёбу.', ai_ph: 'Спросить ИИ',
      ai_disclaimer: 'ИИ может ошибаться', ai_error: 'ИИ сейчас недоступен. Попробуйте позже.', ai_limit: 'Лимит вопросов на сегодня исчерпан.', ai_clear: 'Очистить',
      ai_chips: ['Как оформить чертёж по ГОСТ?', 'Идеи для концепции макета', 'Как построить перспективу?', 'Сколько стоит работа?'],
      ai_guest_note: 'Для заказа оставьте заявку — ИИ не принимает заказы.',
      profile_title: 'Профиль', anon_id: 'Анонимный ID', university: 'Университет', not_selected: 'Не выбран', language: 'Язык',
      theme: 'Тема', th_light: 'Светлая', th_dark: 'Тёмная', th_auto: 'Авто',
      security: 'Вход и безопасность', bio_on: 'Включить вход по {bio}', bio_on_sub: 'Без пароля — только ваше лицо или палец',
      bio_lock: 'Просить {bio} при открытии', bio_lock_sub: 'Если 15 минут не пользовались', bio_off: 'Отключить {bio}',
      bio_done: '{bio} включён', bio_fail: 'Не получилось. Попробуйте ещё раз.', bio_none: 'На этом устройстве нет Face ID или отпечатка',
      device_note: 'Логин работает только на этом телефоне. Чтобы перейти на другой — нажмите «Выйти».',
      install: 'Установить приложение', install_sub: 'Иконка на главном экране', privacy: 'Приватность',
      priv1: 'Имя нигде не хранится — только анонимный ID', priv2: 'Фото в чате очищаются от геоданных (EXIF)', priv3: 'Отзывы — без имени и фото',
      logout: 'Выйти', logout_confirm: 'Выйти? Потом можно войти на этом или другом телефоне.',
      locked_t: 'Кабинет закрыт', locked_s: 'Подтвердите, что это вы', unlock: 'Открыть с {bio}',
      sign_in_face_f: 'Войти по отпечатку', bio_on_f: 'Включить вход по отпечатку', bio_lock_f: 'Просить отпечаток при открытии', bio_off_f: 'Отключить вход по отпечатку', bio_done_f: 'Вход по отпечатку включён', unlock_f: 'Открыть по отпечатку',
      installed: 'Приложение установлено', saved: 'Сохранено', close: 'Закрыть', download: 'Скачать', notifications: 'Сообщения', open: 'Открыть', cancel: 'Отмена'
    },
    kk: {
      nav_home: 'Басты', nav_services: 'Қызметтер', nav_chat: 'Чат', nav_promos: 'Акциялар', nav_ai: 'ЖИ', nav_profile: 'Профиль',
      login: 'Логин', password: 'Құпиясөз', sign_in: 'Кіру', sign_in_face: '{bio} арқылы кіру', show_pass: 'Құпиясөзді көрсету',
      bad_credentials: 'Логин немесе құпиясөз қате', too_many: 'Тым көп әрекет. 15 минуттан кейін қайталаңыз.',
      other_device: 'Бұл логин басқа телефонда немесе браузерде ашық. Сол жерде «Шығу» батырмасын басыңыз немесе әкімшіден құрылғыны тастауды сұраңыз.',
      network: 'Байланыс жоқ. Интернетті тексеріңіз.', fill_login: 'Логин мен құпиясөзді енгізіңіз',
      gate_hi: 'Кабинетке кіру', gate_sub: 'Логин мен құпиясөзді әкімші береді — өтініміңізден кейін.',
      no_login: 'Логин жоқ па?', leave_request: 'Өтінім қалдыру', ask_ai: 'ЖИ-ден сұрау', ask_ai_sub: 'Тіркелусіз бірден жауап береді',
      req_title: 'Өтінім', req_sub: 'Тапсырманы сипаттаңыз — әкімші осы жерде, осы телефонда жауап береді.',
      req_service: 'Не керек', req_task: 'Тапсырма', req_task_ph: 'Пән, не істеу керек, оқытушының талаптары, формат…',
      req_deadline: 'Мерзімі', dl_today: 'Бүгін', dl_tomorrow: 'Ертең', dl_3: '3 күн', dl_week: 'Апта', dl_date: 'Күні',
      req_uni: 'Университет', req_contacts: 'Сізбен қалай байланысамыз', req_tg: 'Telegram', req_tg_ph: 'username', req_phone: 'Телефон',
      req_contacts_note: 'Біреуі жеткілікті. Аты керек емес. Байланыстарды тек әкімші көреді, чат ашылғанда олар өшіріледі.',
      req_send: 'Өтінімді жіберу', req_sent: 'Өтінім жіберілді', req_sent_sub: 'Жауап осында келеді. Сайтты осы телефоннан ашыңыз.',
      req_task_short: 'Тапсырманы толығырақ сипаттаңыз (кем дегенде екі сөйлем)', req_need_contact: 'Telegram немесе телефонды көрсетіңіз',
      req_limit: 'Бір тәулікте тым көп өтінім. Ертең қайталаңыз.', optional: 'міндетті емес',
      st_new: '№{n} өтінім · жауап күтуде', st_answered: '№{n} өтінім · жауап бар', st_accepted: '№{n} өтінім қабылданды', st_declined: '№{n} өтінім қабылданбады',
      st_new_sub: 'Әдетте бір сағат ішінде жауап береміз. Әзірге ЖИ-ден сұрауға болады.', st_accepted_sub: 'Әкімшімен чат ашық — логин мен құпиясөзіңіз дайын.',
      open_chat: 'Чатты ашу', new_request: 'Жаңа өтінім', admin_reply: 'Әкімшінің жауабы', your_task: 'Сіздің тапсырмаңыз',
      save_title: 'Кіруді сақтаңыз', save_sub: 'iPhone құпиясөзді «Құпиясөздер»-ге сақтауды ұсынады — «Сақтау» басыңыз. Кейін Face ID арқылы кіруге болады.',
      save_btn: 'Сақтап, чатты ашу',
      shh_title: 'Тсс… бұл біздің арамызда 🤫', shh_text: 'Сервис туралы ешкімге айтпаңыз — әсіресе оқытушыларға: кейбіреулері көмек алатын студенттерді арнайы іздейді. Біз аттарды сақтамаймыз, жұмыстарды жарияламаймыз және бірінші жазбаймыз.',
      handmade: 'Барлық жұмыс — қолмен, компьютерсіз және ЖИ-сіз.',
      install_t: 'Қосымшаны орнатыңыз', install_s: 'Алдымен орнатыңыз, содан кейін кіріңіз — логин тек бір құрылғыда жұмыс істейді.',
      install_ios: ['Safari төменгі жағындағы «Бөлісу» батырмасын басыңыз', '«Басты экранға қосу» тармағын таңдаңыз', 'Қосымшаны басты экраннан ашып, кіріңіз'],
      install_other: ['Браузер мәзірін ашыңыз (⋮)', '«Қосымшаны орнату» таңдаңыз', 'Қосымшаны ашып, кіріңіз'],
      choose_uni: 'Университетті таңдаңыз', uni_sheet: 'Сіздің университетіңіз', unis_title: 'Университеттер', all: 'Барлығы',
      hero_btn: 'Өтінім қалдыру', order: 'Тапсырыс беру', price_chat: 'Бағасы — чатта', promo_more: 'Толығырақ',
      adv_title: 'Біздің артықшылықтар', adv1: 'Анонимдік', adv2: 'Тек қол жұмысы', adv3: 'Мерзімді сақтау',
      reviews: 'Студенттер пікірлері', reviews_count: '({n} пікір)', no_reviews: 'Әзірге пікір жоқ.', student: 'Студент', student_of: '{uni} студенті',
      materials: 'Пайдалы материалдар', materials_sub: 'Дәрістер, шпаргалкалар мен мысалдар — тегін.', no_materials: 'Материалдар жақында қосылады.',
      promos_sub: 'Студенттерге арналған жеңілдіктер мен ұсыныстар.', no_promos: 'Қазір акция жоқ — кейінірек қараңыз.',
      services_title: 'Қызметтер', services_sub: 'Не істеу керегін таңдаңыз — егжей-тегжейін, бағасы мен мерзімін чатта талқылаймыз.',
      order_text: 'Сәлеметсіз бе! Тапсырыс бергім келеді: {svc}.\nПән: \nМерзімі: \n', order_promo: 'Сәлеметсіз бе! «{p}» акциясын пайдаланғым келеді.\n',
      chat_on: 'Желіде', chat_off: 'Желіде емес — кейінірек жауап береміз', chat_anon: 'Анонимді', today: 'Бүгін', yesterday: 'Кеше',
      placeholder: 'Хабарлама', attach: 'Файл тіркеу', send: 'Жіберу', remove: 'Алып тастау', retry: 'Жіберілмеді · қайталау үшін басыңыз',
      new_msgs: 'Жаңа хабарламалар', file_too_large: 'Файл тым үлкен (макс. {mb} МБ)', file_type: 'Бұл файл түріне қолдау жоқ', send_failed: 'Жіберу мүмкін болмады',
      ai_suggest_t: 'Біз жауап бергенше', ai_suggest_s: 'ЖИ-көмекші оқу туралы жалпы сұрақтарға қазір жауап бере алады.', ai_suggest_btn: 'ЖИ-ден сұрау', ai_suggest_wait: 'Күтемін',
      offline_note: 'Қазір желіде емеспіз, бірақ міндетті түрде жауап береміз.',
      review_t: 'Жұмысымызды бағалаңыз', review_s: 'Пікір анонимді: аты, фотосы және байланыстары жарияланбайды.', review_ph: 'Не ұнады?', review_send: 'Анонимді жіберу',
      review_thanks: 'Пікіріңізге рахмет!', review_pending: 'Тексерістен кейін жарияланады.', review_short: 'Кем дегенде бірнеше сөз жазыңыз', review_rate: 'Баға қойыңыз',
      ai_title: 'ЖИ-көмекші', ai_hello_t: 'Қалай көмектесейін?', ai_hello_s: 'Тапсырмалар, сәулет, бағдарламалар немесе оқу туралы сұраңыз.', ai_ph: 'ЖИ-ден сұрау',
      ai_disclaimer: 'ЖИ қателесуі мүмкін', ai_error: 'ЖИ қазір қолжетімсіз. Кейінірек қайталаңыз.', ai_limit: 'Бүгінгі сұрақтар лимиті таусылды.', ai_clear: 'Тазалау',
      ai_chips: ['Сызбаны ГОСТ бойынша қалай рәсімдеймін?', 'Макет концепциясына идеялар', 'Перспективаны қалай саламын?', 'Жұмыс қанша тұрады?'],
      ai_guest_note: 'Тапсырыс үшін өтінім қалдырыңыз — ЖИ тапсырыс қабылдамайды.',
      profile_title: 'Профиль', anon_id: 'Анонимді ID', university: 'Университет', not_selected: 'Таңдалмаған', language: 'Тіл',
      theme: 'Тақырып', th_light: 'Ашық', th_dark: 'Қараңғы', th_auto: 'Авто',
      security: 'Кіру және қауіпсіздік', bio_on: '{bio} арқылы кіруді қосу', bio_on_sub: 'Құпиясөзсіз — тек бетіңіз немесе саусағыңыз',
      bio_lock: 'Ашқанда {bio} сұрау', bio_lock_sub: 'Егер 15 минут қолданбасаңыз', bio_off: '{bio} өшіру',
      bio_done: '{bio} қосылды', bio_fail: 'Болмады. Қайталап көріңіз.', bio_none: 'Бұл құрылғыда Face ID немесе саусақ ізі жоқ',
      device_note: 'Логин тек осы телефонда жұмыс істейді. Басқасына ауысу үшін «Шығу» басыңыз.',
      install: 'Қосымшаны орнату', install_sub: 'Басты экрандағы белгіше', privacy: 'Құпиялылық',
      priv1: 'Атыңыз ешқайда сақталмайды — тек анонимді ID', priv2: 'Чаттағы фотолар геодеректерден (EXIF) тазартылады', priv3: 'Пікірлер — атсыз және фотосыз',
      logout: 'Шығу', logout_confirm: 'Шығасыз ба? Кейін осы немесе басқа телефоннан кіруге болады.',
      locked_t: 'Кабинет жабық', locked_s: 'Бұл сіз екеніңізді растаңыз', unlock: '{bio} арқылы ашу',
      sign_in_face_f: 'Саусақ ізімен кіру', bio_on_f: 'Саусақ ізімен кіруді қосу', bio_lock_f: 'Ашқанда саусақ ізін сұрау', bio_off_f: 'Саусақ ізімен кіруді өшіру', bio_done_f: 'Саусақ ізімен кіру қосылды', unlock_f: 'Саусақ ізімен ашу',
      installed: 'Қосымша орнатылды', saved: 'Сақталды', close: 'Жабу', download: 'Жүктеп алу', notifications: 'Хабарламалар', open: 'Ашу', cancel: 'Болдырмау'
    }
  };

  /* ================================================================ состояние */
  var state = {
    lang: (C.student && C.student.lang) || LS.get('studyhub_lang') || ((D.cookie.match(/(?:^|; )studyhub_lang=(kk|ru)/) || [])[1]) || 'ru',
    student: C.student, locked: C.locked, request: C.request,
    unread: C.unread || 0, adminOnline: !!C.adminOnline,
    reviews: C.reviews || [], stats: C.reviewStats || { count: 0, average: 0 },
    messages: [], loaded: false, lastId: 0, readUpTo: 0, serverOffset: 0,
    draftService: null, draftText: null, aiDismissedFor: 0,
    ai: { messages: [], busy: false }, installPrompt: null, prevRoute: null, pkAvail: false
  };
  if (state.lang !== 'kk') state.lang = 'ru';
  try { state.ai.messages = JSON.parse(SS.get('studyhub_ai') || '[]'); } catch (e) { state.ai.messages = []; }

  /* ================================================================ помощники */
  function store(kind) {
    var s = null;
    try { s = W[kind]; s.setItem('__t', '1'); s.removeItem('__t'); } catch (e) { s = null; }
    return {
      get: function (k) { try { return s ? s.getItem(k) : null; } catch (e) { return null; } },
      set: function (k, v) { try { if (s) s.setItem(k, v); } catch (e) { /* ignore */ } },
      del: function (k) { try { if (s) s.removeItem(k); } catch (e) { /* ignore */ } }
    };
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function t(key, vars) {
    var v = I18N[state.lang] && I18N[state.lang][key];
    if (v == null) v = I18N.ru[key];
    if (v == null) return key;
    if (vars && typeof v === 'string') Object.keys(vars).forEach(function (k) { v = v.split('{' + k + '}').join(vars[k]); });
    return v;
  }
  function loc(o) { return !o ? '' : typeof o === 'string' ? o : (o[state.lang] || o.ru || ''); }
  function $(s, el) { return (el || D).querySelector(s); }
  function qa(s, el) { return Array.prototype.slice.call((el || D).querySelectorAll(s)); }
  function nowServer() { return Math.floor(Date.now() / 1000) + state.serverOffset; }
  function fmtTime(ts) { var d = new Date(ts * 1000); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
  function fmtDay(ts) {
    var d = new Date(ts * 1000), n = new Date(), y = new Date(); y.setDate(n.getDate() - 1);
    if (d.toDateString() === n.toDateString()) return t('today');
    if (d.toDateString() === y.toDateString()) return t('yesterday');
    try { return d.toLocaleDateString(state.lang === 'kk' ? 'kk-KZ' : 'ru-RU', { day: 'numeric', month: 'long' }); } catch (e) { return d.toLocaleDateString(); }
  }
  function fmtSize(b) { return b < 1024 ? b + ' B' : b < 1048576 ? Math.round(b / 1024) + ' KB' : (b / 1048576).toFixed(1) + ' MB'; }
  function serviceById(id) { var l = C.services || []; for (var i = 0; i < l.length; i++) if (l[i].id === id) return l[i]; return null; }
  function uniById(id) { var l = C.universities || []; for (var i = 0; i < l.length; i++) if (l[i].id === id) return l[i]; return null; }
  function uniName(u) { return u ? (u.short || loc(u.name)) : ''; }
  function withQuery(url, q) { return url + (url.indexOf('?') > -1 ? '&' : '?') + q; }
  function vibrate(ms) { try { if (navigator.vibrate) navigator.vibrate(ms || 8); } catch (e) { /* ignore */ } }
  var FINGER = !IOS && /android/i.test(navigator.userAgent);
  function bioName() { return 'Face ID'; }
  /* «Face ID» на iPhone, «отпечаток» на Android — у каждого своя грамотная фраза */
  function tb(key) { return FINGER ? t(key + '_f') : t(key, { bio: 'Face ID' }); }

  /* ================================================================ иконки и картинки */
  var ICONS = {
    home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
    homeFill: '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z"/>',
    grad: '<path d="M22 9 12 4 2 9l10 5 10-5Z"/><path d="M6 11v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/><path d="M22 9v6"/>',
    grid: '<rect x="3" y="3" width="7.5" height="7.5" rx="2.2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2.2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2.2"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2.2"/>',
    chat: '<path d="M21 12a8.5 8.5 0 0 1-12.3 7.6L3 21l1.4-5.1A8.5 8.5 0 1 1 21 12Z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/>',
    sparkle: '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9Z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8Z"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
    bell: '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    chevDown: '<path d="m6 9 6 6 6-6"/>', chevRight: '<path d="m9 6 6 6-6 6"/>', chevLeft: '<path d="m15 6-6 6 6 6"/>',
    arrowRight: '<path d="M5 12h14M13 6l6 6-6 6"/>', arrowUp: '<path d="M12 19V5M5 12l7-7 7 7"/>', arrowDown: '<path d="M12 5v14M5 12l7 7 7-7"/>',
    book: '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2Z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7Z"/>',
    cube: '<path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.7Z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/>',
    pencil: '<path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
    ruler: '<path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.41 2.41 0 0 1 0-3.4l2.6-2.6a2.41 2.41 0 0 1 3.4 0Z"/><path d="m14.5 12.5 2-2M11.5 9.5l2-2M8.5 6.5l2-2M17.5 15.5l2-2"/>',
    building: '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/>',
    laptop: '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M2 20h20"/>',
    palette: '<circle cx="13.5" cy="6.5" r=".6"/><circle cx="17.5" cy="10.5" r=".6"/><circle cx="8.5" cy="7.5" r=".6"/><circle cx="6.5" cy="12.5" r=".6"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.9 0 1.6-.7 1.6-1.7 0-.4-.2-.8-.4-1.1-.3-.3-.4-.7-.4-1.1a1.6 1.6 0 0 1 1.6-1.6h2c3 0 5.6-2.5 5.6-5.6C22 6 17.5 2 12 2Z"/>',
    doc: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
    shapes: '<rect x="3" y="11" width="10" height="10" rx="2.5"/><circle cx="15.5" cy="8.5" r="5.5"/><path d="M9 11V8"/>',
    lecture: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8M8 9h2"/>',
    tag: '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
    clip: '<path d="m21.4 11.1-9.2 9.2a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5"/>',
    lock: '<rect x="4" y="11" width="16" height="10" rx="2.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    star: '<path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4l-5.9 3.1 1.2-6.5L2.5 9.4l6.6-.9Z"/>',
    check: '<path d="M20 6 9 17l-5-5"/>', checks: '<path d="M17 7 6.5 17.5 2 13"/><path d="m22 8-9.5 9.5-1.5-1.5"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    globe: '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10Z"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    eyeOff: '<path d="M9.9 4.2A10 10 0 0 1 12 4c6.5 0 10 8 10 8a17 17 0 0 1-2.2 3.2M6.6 6.6A17 17 0 0 0 2 12s3.5 8 10 8a9.7 9.7 0 0 0 5.4-1.6"/><path d="M14.1 14.1a3 3 0 1 1-4.2-4.2M2 2l20 20"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
    mask: '<path d="M3 11h18"/><path d="M5.5 11 7 5.6a1 1 0 0 1 1.2-.7L12 6l3.8-1.1a1 1 0 0 1 1.2.7L18.5 11"/><circle cx="7.5" cy="16.5" r="3"/><circle cx="16.5" cy="16.5" r="3"/><path d="M10.5 16.5h3"/>',
    phone: '<rect x="5" y="2" width="14" height="20" rx="3"/><path d="M11 18h2"/>',
    share: '<path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M16 6l-4-4-4 4M12 2v13"/>',
    plusSq: '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/>',
    image: '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
    trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
    face: '<path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2"/><path d="M9 9v1.5M15 9v1.5M12 9v4h-1"/><path d="M9.5 16a4 4 0 0 0 5 0"/>',
    send: '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
    clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    tg: '<path d="m21.5 3.5-19 7.3c-.8.3-.8 1.4 0 1.7l4.6 1.6 1.8 5.6c.2.7 1.1.9 1.6.4l2.6-2.4 4.7 3.5c.6.4 1.4.1 1.6-.6l3.2-15.6c.2-1-.7-1.8-1.6-1.5Z"/><path d="m7.1 14.1 10.4-6.6"/>'
  };
  function icon(name, cls) { return '<svg class="icon ' + (cls || '') + '" viewBox="0 0 24 24" aria-hidden="true">' + (ICONS[name] || ICONS.doc) + '</svg>'; }
  var seq = 0;
  function aiStar(cls, size) {
    var id = 'sg' + (++seq);
    return '<svg class="ai-star ' + (cls || '') + '"' + (size ? ' style="width:' + size + 'px;height:' + size + 'px;animation:none"' : '') + ' viewBox="0 0 100 100" aria-hidden="true"><defs>' +
      '<radialGradient id="' + id + '" cx="36%" cy="30%" r="80%"><stop offset="0" stop-color="#EEEAFF"/><stop offset=".42" stop-color="#A193FF"/><stop offset="1" stop-color="#5A45E6"/></radialGradient></defs>' +
      '<path d="M50 3C54 31 69 46 97 50 69 54 54 69 50 97 46 69 31 54 3 50 31 46 46 31 50 3Z" fill="url(#' + id + ')"/>' +
      '<path d="M50 15c2.6 15 8.5 23.5 21 29" stroke="rgba(255,255,255,.6)" stroke-width="3.5" fill="none" stroke-linecap="round"/></svg>';
  }
  function brandMark() {
    if (C.logo) return '<img src="' + esc(C.logo) + '" alt="">';
    return '<svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M16 7 7.5 27" style="stroke:var(--ink)" stroke-width="3.4"/><path d="M16 7 24.5 27" style="stroke:var(--accent)" stroke-width="3.4"/>' +
      '<path d="M10.5 20.5c3.6 1.6 7.4 1.6 11 0" style="stroke:var(--ink)" stroke-width="2.4"/><circle cx="16" cy="5.5" r="2.6" style="fill:var(--ink)" stroke="none"/></svg>';
  }
  function brandTile(cls) { return '<span class="brand-tile ' + (cls || '') + '">' + brandMark() + '</span>'; }
  function anonAvatar(seed, cls) {
    var tones = ['lilac', 'sky', 'rose', 'mint', 'sand', 'peach'], n = 0; seed = String(seed || '');
    for (var i = 0; i < seed.length; i++) n = (n * 31 + seed.charCodeAt(i)) >>> 0;
    return '<div class="' + (cls || 'anon-ava') + ' tone-' + tones[n % tones.length] + ' tone-bg">' + icon('mask') + '</div>';
  }
  function stars(n) {
    var out = '<span class="stars" aria-label="' + n + '/5">';
    for (var i = 1; i <= 5; i++) out += '<span class="' + (i <= n ? '' : 'off') + '">' + icon('star') + '</span>';
    return out + '</span>';
  }
  function sparkleSvg(cls) { return '<svg class="t-sparkle ' + (cls || '') + '" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1c.9 6.2 4.8 10.1 11 11-6.2.9-10.1 4.8-11 11-.9-6.2-4.8-10.1-11-11C7.2 11.1 11.1 7.2 12 1Z"/></svg>'; }
  /* современный белый дом с консолями — рисуется SVG */
  function villaArt(cls) {
    var id = 'v' + (++seq);
    function level(x, y, w, h, gx, gw, d) {
      var g = '';
      for (var i = 1; i < 3; i++) g += '<rect x="' + (gx + gw * i / 3) + '" y="' + (y + 9) + '" width="1.8" height="' + (h - 16) + '" fill="#E4E6EF" opacity=".7"/>';
      return '<rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" fill="url(#' + id + 'w)"/>' +
        '<polygon points="' + (x + w) + ',' + y + ' ' + (x + w + d) + ',' + (y - d * .45) + ' ' + (x + w + d) + ',' + (y + h - d * .45) + ' ' + (x + w) + ',' + (y + h) + '" fill="url(#' + id + 'd)"/>' +
        '<rect x="' + gx + '" y="' + (y + 9) + '" width="' + gw + '" height="' + (h - 16) + '" rx="1" fill="url(#' + id + 'g)"/>' +
        '<rect x="' + gx + '" y="' + (y + 9) + '" width="' + gw * .35 + '" height="' + (h - 16) + '" fill="#FFFFFF" opacity=".12"/>' + g;
    }
    function slab(x, y, w, d) {
      return '<rect x="' + x + '" y="' + y + '" width="' + w + '" height="11" fill="#FFFFFF"/><rect x="' + x + '" y="' + (y + 11) + '" width="' + w + '" height="4" fill="#D6D3E6"/>' +
        '<polygon points="' + (x + w) + ',' + y + ' ' + (x + w + d) + ',' + (y - d * .5) + ' ' + (x + w + d) + ',' + (y + 11 - d * .5) + ' ' + (x + w) + ',' + (y + 11) + '" fill="#ECEAF5"/>' +
        '<polygon points="' + x + ',' + y + ' ' + (x + d) + ',' + (y - d * .5) + ' ' + (x + w + d) + ',' + (y - d * .5) + ' ' + (x + w) + ',' + y + '" fill="#F7F6FC"/>';
    }
    return '<svg class="t-villa ' + (cls || '') + '" viewBox="0 0 330 300" preserveAspectRatio="xMaxYMax meet" aria-hidden="true"><defs>' +
      '<linearGradient id="' + id + 'w" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFFFFF"/><stop offset="1" stop-color="#ECEBF4"/></linearGradient>' +
      '<linearGradient id="' + id + 'g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#5A6378"/><stop offset=".55" stop-color="#2F3445"/><stop offset="1" stop-color="#4A5166"/></linearGradient>' +
      '<linearGradient id="' + id + 'd" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#E3E0EF"/><stop offset="1" stop-color="#CBC7DE"/></linearGradient>' +
      '<radialGradient id="' + id + 's" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#B9AEF5" stop-opacity=".45"/><stop offset="1" stop-color="#B9AEF5" stop-opacity="0"/></radialGradient></defs>' +
      '<ellipse cx="200" cy="286" rx="150" ry="16" fill="url(#' + id + 's)"/>' +
      level(104, 238, 180, 44, 168, 70, 34) + slab(70, 228, 228, 34) +
      level(140, 176, 156, 52, 150, 62, 34) + '<rect x="222" y="185" width="44" height="36" rx="1" fill="url(#' + id + 'g)"/>' + slab(96, 166, 214, 34) +
      level(176, 114, 128, 52, 196, 72, 34) + slab(146, 104, 176, 34) + '</svg>';
  }

  /* ================================================================ тема: светлая / тёмная / как в телефоне */
  var mq = W.matchMedia ? matchMedia('(prefers-color-scheme: dark)') : null;
  function themeGet() { return LS.get('sh_theme') || 'light'; }
  function themeApply(user) {
    var el = D.documentElement, th = themeGet();
    var dark = th === 'dark' || (th === 'auto' && mq && mq.matches);
    if (user) { el.classList.add('sh-theming'); requestAnimationFrame(function () { requestAnimationFrame(function () { el.classList.remove('sh-theming'); }); }); }
    el.classList.toggle('sh-dark', !!dark); el.classList.toggle('sh-light', !dark);
    qa('meta[name="theme-color"]').forEach(function (m) { m.setAttribute('content', dark ? '#0E0D16' : '#F1EFFB'); });
  }
  if (mq && mq.addEventListener) mq.addEventListener('change', function () { if (themeGet() === 'auto') themeApply(); });

  /* ================================================================ переключатели: плашка едет к выбранному */
  function seg(name, opts, val, cls) {
    return '<div class="seg2 ' + (cls || '') + '" data-seg="' + name + '" role="group"><i class="seg2-i"></i>' + opts.map(function (o) {
      return '<button type="button" data-v="' + esc(o[0]) + '" aria-pressed="' + (o[0] === val) + '">' + esc(o[1]) + '</button>';
    }).join('') + '</div>';
  }
  function segMove(sg, instant) {
    var i = $('.seg2-i', sg), on = $('[aria-pressed="true"]', sg);
    if (!i) return;
    if (!on || !on.offsetWidth) { i.style.opacity = '0'; return; }
    i.style.opacity = '';
    if (instant || !i._ready) i.style.transition = 'none';
    i.style.width = on.offsetWidth + 'px';
    i.style.transform = 'translateX(' + on.offsetLeft + 'px)';
    if (instant || !i._ready) { void i.offsetWidth; i.style.transition = ''; i._ready = 1; }
  }
  function segAll(el) { qa('.seg2', el || D).forEach(function (s) { segMove(s); }); }
  function segSet(sg, v) { qa('button', sg).forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-v') === v)); }); segMove(sg); }

  /* ================================================================ запросы, уведомления, кнопки */
  function api(path, opts) {
    opts = opts || {};
    var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: { 'X-StudyHub': '1' } };
    if (opts.body instanceof FormData) init.body = opts.body;
    else if (opts.body) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.body); }
    var url = /^https?:/.test(path) ? path : C.rest + (C.rest.indexOf('?') > -1 ? path.replace('?', '&') : path);
    return fetch(url, init).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok) { var err = new Error((data && data.message) || 'error'); err.status = res.status; err.code = data && data.code; throw err; }
        return data;
      });
    });
  }
  var toastTimer;
  function toast(msg, bad) {
    var el = $('.toast2');
    if (!el) { el = D.createElement('div'); el.className = 'toast2'; el.setAttribute('role', 'status'); D.body.appendChild(el); }
    el.textContent = msg; el.classList.toggle('bad', !!bad);
    void el.offsetWidth; el.classList.add('on');
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { el.classList.remove('on'); }, 2800);
  }
  /* ошибка: поле коротко «качает головой» */
  function shake(el) { if (!el || reduce) return; el.classList.remove('sh-shake'); void el.offsetWidth; el.classList.add('sh-shake'); vibrate(20); setTimeout(function () { el.classList.remove('sh-shake'); }, 420); }
  /* кнопка «думает»: крутилка не короче 500 мс, чтобы не мигала */
  function busy(btn, on) {
    if (!btn) return Promise.resolve();
    if (on) { btn._t0 = Date.now(); btn.disabled = true; btn.classList.add('is-busy'); return Promise.resolve(); }
    var wait = Math.max(0, 500 - (Date.now() - (btn._t0 || 0)));
    return new Promise(function (r) { setTimeout(function () { btn.disabled = false; btn.classList.remove('is-busy'); r(); }, wait); });
  }
  function errText(ex) {
    if (!ex) return t('network');
    if (ex.code === 'other_device') return t('other_device');
    if (ex.status === 429) return t('too_many');
    if (ex.status === 401) return t('bad_credentials');
    return t('network');
  }

  /* ================================================================ листы: выезжают снизу, друг над другом, смахиваются вниз */
  var stack = [];
  function sheet(html, opts) {
    opts = opts || {};
    var s = D.createElement('div');
    s.className = 'sh2' + (opts.tall ? ' sh2-tall' : '') + (opts.cls ? ' ' + opts.cls : '');
    s.innerHTML = '<div class="sh2-bg"></div><div class="sh2-in" role="dialog" aria-modal="true"><div class="sh2-hd" data-drag><span class="sh2-grab"></span>' +
      (opts.title ? '<h3>' + esc(opts.title) + '</h3>' : '<span></span>') + '<button class="sh2-x" data-close aria-label="' + esc(t('close')) + '">' + icon('x', 'sm') + '</button></div>' +
      '<div class="sh2-body">' + html + '</div></div>';
    s.style.zIndex = String(60 + stack.length * 2);
    D.body.appendChild(s);
    stack.push(s);
    D.documentElement.classList.add('sh-lock');
    void s.offsetWidth; s.classList.add('on');
    s.addEventListener('click', function (e) { if (e.target.classList.contains('sh2-bg') || e.target.closest('[data-close]')) closeSheet(s); });
    drag(s);
    segAll(s);
    if (opts.onMount) opts.onMount(s, function () { closeSheet(s); });
    s._onClose = opts.onClose;
    return s;
  }
  function closeSheet(s) {
    s = s || stack[stack.length - 1];
    if (!s || !s.parentNode) return;
    var i = stack.indexOf(s); if (i > -1) stack.splice(i, 1);
    if (D.activeElement && D.activeElement.blur) D.activeElement.blur();
    s.classList.remove('on');
    $('.sh2-in', s).style.transform = '';
    setTimeout(function () { s.remove(); }, reduce ? 0 : 260);
    if (!stack.length) D.documentElement.classList.remove('sh-lock');
    if (s._onClose) s._onClose();
  }
  function drag(s) {
    var h = $('[data-drag]', s), inn = $('.sh2-in', s), y0 = 0, dy = 0, ly = 0, lt = 0, v = 0, on = false;
    h.addEventListener('touchstart', function (e) { if (e.target.closest('button')) return; on = true; y0 = ly = e.touches[0].clientY; lt = Date.now(); dy = 0; v = 0; s.classList.add('drag'); }, { passive: true });
    h.addEventListener('touchmove', function (e) {
      if (!on) return;
      var y = e.touches[0].clientY, n = Date.now();
      if (n > lt) { v = (y - ly) / (n - lt); ly = y; lt = n; }
      dy = y - y0;
      inn.style.transform = 'translateY(' + (dy < 0 ? -12 * (-dy / (-dy + 150)) : dy) + 'px)';
    }, { passive: true });
    h.addEventListener('touchend', function () { if (!on) return; on = false; s.classList.remove('drag'); if (dy > 110 || (dy > 8 && v > 0.1)) closeSheet(s); else inn.style.transform = ''; });
  }
  /* галочка: круг появляется, галочка дорисовывается */
  function okMark() { return '<svg class="okmark" viewBox="0 0 52 52" aria-hidden="true"><circle cx="26" cy="26" r="24"/><path d="M15 27l7 7 15-16"/></svg>'; }

  /* ================================================================ Face ID / отпечаток (ключи доступа) */
  function b64uToBuf(s) { s = s.replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '='; var b = atob(s), a = new Uint8Array(b.length); for (var i = 0; i < b.length; i++) a[i] = b.charCodeAt(i); return a.buffer; }
  function bufToB64u(buf) { var b = new Uint8Array(buf), s = ''; for (var i = 0; i < b.length; i++) s += String.fromCharCode(b[i]); return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  function pkCheck() {
    if (!W.PublicKeyCredential || !PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable || location.protocol !== 'https:' && location.hostname !== 'localhost') return Promise.resolve(false);
    return PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(function () { return false; });
  }
  function pkGet(mode) {
    return api('passkey/options', { method: 'POST', body: { mode: mode } }).then(function (o) {
      var pk = o.publicKey;
      pk.challenge = b64uToBuf(pk.challenge);
      pk.allowCredentials = (pk.allowCredentials || []).map(function (c) { return { type: 'public-key', id: b64uToBuf(c.id) }; });
      return navigator.credentials.get({ publicKey: pk });
    }).then(function (cred) {
      var r = cred.response;
      return api('passkey/login', { method: 'POST', body: { mode: mode, id: bufToB64u(cred.rawId), clientDataJSON: bufToB64u(r.clientDataJSON), authenticatorData: bufToB64u(r.authenticatorData), signature: bufToB64u(r.signature) } });
    });
  }
  function pkRegister() {
    return api('passkey/options', { method: 'POST', body: { mode: 'register' } }).then(function (o) {
      var pk = o.publicKey;
      pk.challenge = b64uToBuf(pk.challenge);
      pk.user.id = b64uToBuf(pk.user.id);
      pk.excludeCredentials = (pk.excludeCredentials || []).map(function (c) { return { type: 'public-key', id: b64uToBuf(c.id) }; });
      return navigator.credentials.create({ publicKey: pk });
    }).then(function (cred) {
      return api('passkey/register', { method: 'POST', body: { id: bufToB64u(cred.rawId), clientDataJSON: bufToB64u(cred.response.clientDataJSON), attestationObject: bufToB64u(cred.response.attestationObject) } });
    }).then(function () { LS.set('sh_pk', '1'); if (state.student) state.student.passkeys = (state.student.passkeys || 0) + 1; });
  }
  function pkOffer() {
    if (!state.pkAvail || !state.student || state.student.passkeys) return;
    sheet('<div class="center-pad">' + '<div class="bio-ico">' + icon('face') + '</div><h2>' + esc(tb('bio_on')) + '</h2><p class="muted">' + esc(t('bio_on_sub')) + '</p>' +
      '<button class="btn btn-ink btn-block" data-act="pk-on">' + icon('face', 'sm') + esc(tb('bio_on')) + '</button>' +
      '<button class="btn btn-ghost btn-block" data-close style="margin-top:10px">' + esc(t('cancel')) + '</button></div>');
  }

  /* ================================================================ гость: вход, заявка, ИИ */
  function langSeg() { return seg('lang', [['kk', 'Қаз'], ['ru', 'Рус']], state.lang, 'seg2-lang'); }

  function requestCard() {
    var r = state.request;
    if (!r) return '';
    var head = t('st_' + r.status, { n: r.id });
    var body = '';
    if (r.status === 'new') body = '<p class="muted">' + esc(t('st_new_sub')) + '</p>';
    if (r.reply) body += '<div class="rq-reply">' + brandTile('sm') + '<div><span class="tiny">' + esc(t('admin_reply')) + '</span><p>' + esc(r.reply) + '</p></div></div>';
    if (r.canOpen) body = '<p class="muted">' + esc(t('st_accepted_sub')) + '</p>' + body + '<button class="btn btn-ink btn-block" data-act="claim">' + icon('chat', 'sm') + esc(t('open_chat')) + '</button>';
    if (r.status === 'declined') body += '<button class="btn btn-ghost btn-block" data-act="request-new">' + esc(t('new_request')) + '</button>';
    var tone = r.status === 'accepted' ? 'ok' : r.status === 'declined' ? 'mute' : r.status === 'answered' ? 'acc' : 'wait';
    return '<section class="g-card glass rq-card rq-' + tone + '"><div class="rq-top"><span class="rq-dot"></span><b>' + esc(head) + '</b></div>' +
      '<details class="rq-task"><summary>' + esc(t('your_task')) + '</summary><p>' + esc(r.task) + '</p>' + (r.deadline ? '<p class="tiny">⏱ ' + esc(r.deadline) + '</p>' : '') + '</details>' + body + '</section>';
  }

  function shhCard() {
    return '<section class="g-card glass shh"><h3>' + esc(t('shh_title')) + '</h3><p>' + esc(t('shh_text')) + '</p><p class="shh-hand">' + icon('pencil', 'xs') + esc(t('handmade')) + '</p></section>';
  }

  function installCard() {
    if (STANDALONE || !IOS) return '';
    return '<button class="g-card glass inst" data-act="install"><span class="inst-ico">' + icon('share', 'sm') + '</span><span class="grow"><b>' + esc(t('install_t')) + '</b><span class="tiny">' + esc(t('install_s')) + '</span></span>' + icon('chevRight', 'sm') + '</button>';
  }

  function renderGate() {
    var hasReq = !!state.request && state.request.status !== 'declined';
    root.innerHTML =
      '<main class="gate sh-screen">' +
        '<header class="g-top">' + brandTile() + '<div class="grow"><b class="brand-name">' + esc(C.brand) + '</b><span class="brand-sub">' + esc(loc(C.tagline)) + '</span></div>' + langSeg() + '</header>' +
        '<section class="g-hero"><div class="g-art">' + (C.hero && C.hero.image ? '<img src="' + esc(C.hero.image) + '" alt="">' : villaArt()) + sparkleSvg('g-spark') + '</div>' +
          '<h1>' + esc(loc(C.hero && C.hero.title)) + '</h1><p>' + esc(loc(C.hero && C.hero.text)) + '</p></section>' +
        installCard() + requestCard() +
        '<form class="g-card glass g-login' + (state.request && state.request.canOpen ? ' g-login-dim' : '') + '" id="login-form" method="post" action="' + esc(C.appUrl) + '" novalidate>' +
          '<h2>' + esc(t('gate_hi')) + '</h2><p class="muted">' + esc(t('gate_sub')) + '</p>' +
          '<label class="fld"><span>' + esc(t('login')) + '</span><input class="input" name="username" id="l-login" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" inputmode="text" required></label>' +
          '<label class="fld fld-pass"><span>' + esc(t('password')) + '</span><input class="input" name="password" id="l-pass" type="password" autocomplete="current-password" required>' +
            '<button type="button" class="eye" data-act="eye" aria-label="' + esc(t('show_pass')) + '">' + icon('eye', 'sm') + '</button></label>' +
          '<p class="form-err" role="alert" hidden></p>' +
          '<button class="btn btn-ink btn-block btn-lg" type="submit"><span class="btn-t">' + esc(t('sign_in')) + '</span>' + icon('arrowRight', 'sm') + '<i class="spin"></i></button>' +
          '<button type="button" class="btn btn-glass btn-block btn-lg" data-act="pk-login" hidden>' + icon('face', 'sm') + '<span class="btn-t">' + esc(tb('sign_in_face')) + '</span><i class="spin"></i></button>' +
        '</form>' +
        '<div class="g-or"><span>' + esc(t('no_login')) + '</span></div>' +
        '<div class="g-actions">' +
          (hasReq ? '' : '<button class="g-act g-act-main" data-act="request">' + '<span class="g-act-ico">' + icon('doc') + '</span><b>' + esc(t('leave_request')) + '</b><span>' + esc(t('req_sub')) + '</span></button>') +
          (C.ai && C.ai.enabled ? '<button class="g-act glass" data-act="guest-ai">' + aiStar('', 34) + '<b>' + esc(t('ask_ai')) + '</b><span>' + esc(t('ask_ai_sub')) + '</span></button>' : '') +
        '</div>' +
        shhCard() +
        '<p class="tiny g-foot">' + esc(C.brand) + ' · ' + esc(t('chat_anon')) + '</p>' +
      '</main>';
    segAll(root);
    if (state.pkAvail && LS.get('sh_pk')) $('[data-act="pk-login"]').hidden = false;
    var f = $('#login-form');
    f.addEventListener('input', function () { $('.form-err', f).hidden = true; });
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = $('[type=submit]', f), er = $('.form-err', f), login = f.username.value.trim(), pass = f.password.value;
      er.hidden = true;
      if (!login || !pass) { er.textContent = t('fill_login'); er.hidden = false; shake(!login ? f.username : f.password); return; }
      busy(btn, true);
      api('login', { method: 'POST', body: { login: login, password: pass } }).then(function () {
        SS.set('sh_offer_pk', '1');
        location.replace(C.appUrl.split('#')[0] + '#home');
        setTimeout(function () { location.reload(); }, 30);
      }).catch(function (ex) {
        busy(btn, false).then(function () { er.textContent = errText(ex); er.hidden = false; shake(f.password); f.password.select(); });
      });
    });
    pollRequest();
  }

  /* лист заявки */
  function requestSheet(pre) {
    pre = pre || {};
    var svcs = (C.services || []).map(function (s) { return '<button type="button" class="chip2" data-pick="service" data-v="' + esc(s.id) + '" aria-pressed="' + (pre.service === s.id) + '">' + icon(s.icon, 'xs') + esc(loc(s.title)) + '</button>'; }).join('');
    var unis = (C.universities || []).map(function (u) { return '<button type="button" class="chip2" data-pick="university" data-v="' + esc(u.id) + '" aria-pressed="false">' + esc(uniName(u)) + '</button>'; }).join('');
    var dls = [['today', t('dl_today')], ['tomorrow', t('dl_tomorrow')], ['3d', t('dl_3')], ['week', t('dl_week')], ['date', t('dl_date')]];
    sheet(
      '<form id="rq-form" novalidate><p class="muted sh2-lead">' + esc(t('req_sub')) + '</p>' +
      (svcs ? '<div class="fgroup"><span class="flabel">' + esc(t('req_service')) + '</span><div class="chips2" data-group="service">' + svcs + '</div></div>' : '') +
      '<label class="fgroup"><span class="flabel">' + esc(t('req_task')) + '</span><textarea class="input ta" name="task" rows="4" maxlength="3000" placeholder="' + esc(t('req_task_ph')) + '"></textarea></label>' +
      '<div class="fgroup"><span class="flabel">' + esc(t('req_deadline')) + '</span>' + seg('deadline', dls, 'week', 'seg2-wide') + '<input class="input" type="date" name="date" hidden></div>' +
      (unis ? '<div class="fgroup"><span class="flabel">' + esc(t('req_uni')) + ' <i>· ' + esc(t('optional')) + '</i></span><div class="chips2" data-group="university">' + unis + '</div></div>' : '') +
      '<div class="fgroup"><span class="flabel">' + esc(t('req_contacts')) + '</span>' +
        '<div class="ct-row"><span class="ct-ico tg">' + icon('tg', 'sm') + '</span><span class="ct-pre">@</span><input class="input" name="telegram" placeholder="' + esc(t('req_tg_ph')) + '" autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="off"></div>' +
        '<div class="ct-row"><span class="ct-ico">' + icon('phone', 'sm') + '</span><input class="input" name="phone" type="tel" inputmode="tel" placeholder="+7 (___) ___-__-__" autocomplete="tel"></div>' +
        '<p class="note">' + icon('lock', 'xs') + esc(t('req_contacts_note')) + '</p></div>' +
      '<div class="shh-mini">🤫 ' + esc(t('shh_text')) + '</div>' +
      '<p class="form-err" role="alert" hidden></p>' +
      '<button class="btn btn-ink btn-block btn-lg" type="submit"><span class="btn-t">' + esc(t('req_send')) + '</span>' + icon('send', 'sm') + '<i class="spin"></i></button></form>',
      {
        title: t('req_title'), tall: true, onMount: function (s, close) {
          var f = $('#rq-form', s), picked = { service: pre.service || '', university: (state.student && state.student.university) || '' };
          s.addEventListener('click', function (e) {
            var b = e.target.closest('[data-pick]');
            if (b) {
              var g = b.getAttribute('data-pick'), v = b.getAttribute('data-v');
              picked[g] = picked[g] === v ? '' : v;
              qa('[data-pick="' + g + '"]', s).forEach(function (x) { x.setAttribute('aria-pressed', String(x.getAttribute('data-v') === picked[g])); });
              vibrate(6);
            }
            var sb = e.target.closest('[data-seg="deadline"] button');
            if (sb) { segSet(sb.parentNode, sb.getAttribute('data-v')); f.date.hidden = sb.getAttribute('data-v') !== 'date'; if (!f.date.hidden) f.date.focus(); }
          });
          phoneMask(f.phone);
          f.addEventListener('input', function () { $('.form-err', f).hidden = true; });
          f.telegram.addEventListener('change', function () { f.telegram.value = f.telegram.value.trim().replace(/^(https?:\/\/)?(www\.)?(t\.me|telegram\.me)\//i, '').replace(/^@/, '').replace(/[^A-Za-z0-9_]/g, ''); });
          f.addEventListener('submit', function (e) {
            e.preventDefault();
            var er = $('.form-err', f), btn = $('[type=submit]', f);
            var dlv = $('[data-seg="deadline"] [aria-pressed="true"]', f), dlKey = dlv ? dlv.getAttribute('data-v') : 'week';
            var deadline = dlKey === 'date' ? (f.date.value ? new Date(f.date.value).toLocaleDateString(state.lang === 'kk' ? 'kk-KZ' : 'ru-RU') : '') : t('dl_' + (dlKey === '3d' ? '3' : dlKey));
            var body = { service: picked.service, university: picked.university, task: f.task.value.trim(), deadline: deadline, telegram: f.telegram.value.trim(), phone: f.phone.value.trim(), lang: state.lang };
            er.hidden = true;
            if (body.task.length < 10) { er.textContent = t('req_task_short'); er.hidden = false; shake(f.task); f.task.focus(); return; }
            if (body.telegram.replace(/[^A-Za-z0-9_]/g, '').length < 4 && body.phone.replace(/\D/g, '').length < 10) { er.textContent = t('req_need_contact'); er.hidden = false; shake($('.ct-row', f)); return; }
            busy(btn, true);
            api('request', { method: 'POST', body: body }).then(function (d) {
              return busy(btn, false).then(function () {
                state.request = d.request;
                $('.sh2-body', s).innerHTML = '<div class="center-pad done">' + okMark() + '<h2>' + esc(t('req_sent')) + '</h2><p class="muted">' + esc(t('req_sent_sub')) + '</p><button class="btn btn-ink btn-block" data-close>' + esc(t('close')) + '</button></div>';
                vibrate(15);
                if (!state.student) renderGate();
              });
            }).catch(function (ex) {
              busy(btn, false).then(function () { er.textContent = ex.status === 429 ? t('req_limit') : ex.code === 'contact' ? t('req_need_contact') : ex.code === 'task' ? t('req_task_short') : t('network'); er.hidden = false; shake(btn); });
            });
          });
        }
      }
    );
  }
  /* маска телефона +7 (XXX) XXX-XX-XX */
  function phoneMask(el) {
    el.addEventListener('input', function () {
      var d = el.value.replace(/\D/g, '');
      if (!d) { el.value = ''; return; }
      if (d[0] === '8') d = '7' + d.slice(1);
      if (d[0] !== '7') d = '7' + d;
      d = d.slice(0, 11);
      var o = '+7';
      if (d.length > 1) o += ' (' + d.slice(1, 4);
      if (d.length >= 4) o += ')';
      if (d.length > 4) o += ' ' + d.slice(4, 7);
      if (d.length > 7) o += '-' + d.slice(7, 9);
      if (d.length > 9) o += '-' + d.slice(9, 11);
      el.value = o;
    });
  }

  var reqTimer = null;
  function pollRequest() {
    clearInterval(reqTimer);
    if (state.student || !state.request || state.request.canOpen || state.request.status === 'declined') return;
    reqTimer = setInterval(function () {
      if (D.hidden || state.student) return;
      api('request').then(function (d) {
        var before = JSON.stringify(state.request);
        state.request = d.request;
        if (JSON.stringify(state.request) !== before && !stack.length) { renderGate(); vibrate(15); }
      }).catch(function () {});
    }, 8000);
  }

  /* заявку приняли: открыть чат и сохранить логин/пароль в «Пароли» */
  function claim(btn) {
    busy(btn, true);
    api('request/claim', { method: 'POST' }).then(function (d) {
      busy(btn, false);
      var c = d.creds || {};
      SS.set('sh_offer_pk', '1');
      sheet('<form class="center-pad" id="save-form" method="post" action="' + esc(C.appUrl) + '">' + okMark() + '<h2>' + esc(t('save_title')) + '</h2><p class="muted">' + esc(t('save_sub')) + '</p>' +
        '<label class="fld"><span>' + esc(t('login')) + '</span><input class="input" name="username" autocomplete="username" readonly value="' + esc(c.login || '') + '"></label>' +
        '<label class="fld"><span>' + esc(t('password')) + '</span><input class="input mono" name="password" type="text" autocomplete="new-password" readonly value="' + esc(c.password || '') + '"></label>' +
        '<button class="btn btn-ink btn-block btn-lg" type="submit">' + icon('lock', 'sm') + esc(t('save_btn')) + '</button></form>',
        { onMount: function (s) { var f = $('#save-form', s); setTimeout(function () { f.password.type = 'password'; }, 1500); f.addEventListener('submit', function () { f.action = C.appUrl.split('#')[0] + '#chat'; }); }, onClose: function () { location.replace(C.appUrl.split('#')[0] + '#chat'); location.reload(); } });
    }).catch(function (ex) { busy(btn, false); toast(errText(ex), true); });
  }

  /* экран «кабинет закрыт» — открыть Face ID */
  function renderLock() {
    root.innerHTML = '<main class="lock sh-screen">' + brandTile('xl') + '<h1>' + esc(t('locked_t')) + '</h1><p class="muted">' + esc(t('student')) + ' ' + esc(state.locked.alias) + ' · ' + esc(t('locked_s')) + '</p>' +
      '<button class="btn btn-ink btn-lg lock-btn" data-act="unlock">' + icon('face', 'sm') + '<span class="btn-t">' + esc(tb('unlock')) + '</span><i class="spin"></i></button>' +
      '<button class="btn btn-ghost" data-act="logout">' + esc(t('logout')) + '</button></main>';
  }

  /* ================================================================ кабинет: вкладки, «капля», свайп */
  var TABS = ['home', 'services', 'chat', 'promos', 'ai'];
  function tabsList() { return (C.ai && C.ai.enabled) ? TABS : ['home', 'services', 'chat', 'promos', 'profile']; }
  function route() {
    var r = (location.hash || '#home').slice(1).split('?')[0];
    var ok = tabsList().concat(['profile', 'materials']);
    return ok.indexOf(r) > -1 ? r : 'home';
  }
  function go(r) { if (location.hash === '#' + r) render(); else location.hash = r; }
  W.addEventListener('hashchange', function () { if (state.student) { render(); W.scrollTo(0, 0); } });

  function tabbar(active) {
    var icons = { home: 'home', services: 'grid', chat: 'chat', promos: 'star', ai: 'sparkle', profile: 'user' };
    return '<nav class="tabbar2" aria-label="Menu"><i class="lens"></i>' + tabsList().map(function (k) {
      var on = active === k || (k === 'home' && active === 'materials');
      var badge = k === 'chat' && state.unread && !on ? '<span class="badge">' + state.unread + '</span>' : '';
      return '<a class="tab2' + (on ? ' on' : '') + '" href="#' + k + '"' + (on ? ' aria-current="page"' : '') + ' data-tab="' + k + '">' + icon(on && k === 'home' ? 'homeFill' : icons[k]) + '<span>' + esc(t('nav_' + k)) + '</span>' + badge + '</a>';
    }).join('') + '</nav>';
  }
  function lensMove(instant) {
    var bar = $('.tabbar2'), l = bar && $('.lens', bar), b = bar && $('.tab2.on', bar);
    if (!l) return;
    if (!b) { l.classList.remove('on'); return; }
    var tr = 'translateX(' + b.offsetLeft + 'px)';
    if (instant || !l.classList.contains('on')) l.style.transition = 'none';
    else if (l.style.transform !== tr && !reduce) { l.classList.remove('mv'); void l.offsetWidth; l.classList.add('mv'); }
    l.style.width = b.offsetWidth + 'px'; l.style.transform = tr; l.classList.add('on');
    if (l.style.transition === 'none') { void l.offsetWidth; l.style.transition = ''; }
  }

  function render() {
    D.documentElement.lang = state.lang;
    clearInterval(reqTimer);
    if (state.locked && !state.student) { renderLock(); return; }
    if (!state.student) { renderGate(); return; }
    var r = route(), prev = state.prevRoute;
    var html = r === 'home' ? viewHome() : r === 'services' ? viewServices() : r === 'chat' ? viewChat() : r === 'ai' ? viewAI() :
      r === 'promos' ? viewPromos() : r === 'materials' ? viewMaterials() : viewProfile();
    var oldBar = $('.tabbar2');
    root.innerHTML = html;
    /* панель вкладок не пересоздаётся — «капля» плавно переезжает */
    if (oldBar) { root.appendChild(oldBar); oldBar.outerHTML = tabbar(r); var nb = $('.tabbar2'); var l = $('.lens', nb); l.classList.add('on'); l.style.transition = 'none'; var pb = prev && $('[data-tab="' + prev + '"]', nb); if (pb) { l.style.width = pb.offsetWidth + 'px'; l.style.transform = 'translateX(' + pb.offsetLeft + 'px)'; } void l.offsetWidth; l.style.transition = ''; }
    else root.insertAdjacentHTML('beforeend', tabbar(r));
    lensMove(!prev);
    var scr = $('.sh-screen', root);
    if (scr && prev && prev !== r && !reduce) {
      var a = tabsList(), dir = a.indexOf(r) >= a.indexOf(prev) ? 'l' : 'r';
      scr.classList.add('in-' + dir);
    }
    state.prevRoute = r;
    segAll(root);
    if (r === 'chat') mountChat();
    if (r === 'ai') mountAI($('.ai-wrap', root));
  }

  /* свайп влево — следующая вкладка, вправо — предыдущая (не в чате и не на полях) */
  (function swipe() {
    var sx = 0, sy = 0, on = false, t0 = 0;
    D.addEventListener('touchstart', function (e) {
      on = false;
      if (!state.student || stack.length || e.touches.length !== 1) return;
      var r = route(); if (r === 'chat' || r === 'ai') return;
      var x = e.touches[0].clientX;
      if (x < 24 || x > W.innerWidth - 24 || e.target.closest('input, textarea, .h-scroll, .seg2, .tabbar2, .t-chips')) return;
      sx = x; sy = e.touches[0].clientY; t0 = Date.now(); on = true;
    }, { passive: true });
    D.addEventListener('touchend', function (e) {
      if (!on) return; on = false;
      var p = e.changedTouches[0], dx = p.clientX - sx, dy = p.clientY - sy, v = Math.abs(dx) / Math.max(1, Date.now() - t0);
      if (Math.abs(dx) < 70 && !(Math.abs(dx) > 40 && v > .45)) return;
      if (Math.abs(dy) > Math.abs(dx) * .6) return;
      var a = tabsList(), i = a.indexOf(route() === 'materials' ? 'home' : route());
      var to = a[i + (dx < 0 ? 1 : -1)];
      if (to) { vibrate(6); go(to); }
    }, { passive: true });
  })();

  /* ================================================================ главная */
  function header() {
    return '<header class="topbar t-top">' +
      '<a class="brand-tile" href="#profile" aria-label="' + esc(t('nav_profile')) + '">' + brandMark() + '</a>' +
      '<div class="grow"><p class="brand-name">' + esc(C.brand) + '</p><div class="brand-sub">' + esc(loc(C.tagline)) + '</div></div>' +
      langSeg() +
      '<a href="#chat" class="icon-btn t-bell" aria-label="' + esc(t('notifications')) + '">' + icon('bell') + (state.unread ? '<span class="dot"></span>' : '') + '</a>' +
      '<a href="#profile" class="t-me" aria-label="' + esc(t('nav_profile')) + '">' + anonAvatar(state.student.alias, 'anon-ava') + '</a>' +
      '</header>';
  }

  function viewHome() {
    var tiles = (C.services || []).slice(0, 4).map(function (s, i) {
      return '<button class="t-tile glass" style="--d:' + i + '" data-order="' + esc(s.id) + '">' + icon(s.icon, 'lg') + '<span>' + esc(loc(s.title)) + '</span></button>';
    }).join('');
    var cur = state.student.university;
    var unis = (C.universities || []).map(function (u) {
      return '<button class="t-chip" data-uni="' + esc(u.id) + '" aria-pressed="' + (cur === u.id) + '">' + icon('grad', 'xs') + esc(uniName(u)) + '</button>';
    }).join('');
    var promo = (C.promos || [])[0], st = state.stats || { count: 0 }, rv = state.reviews[0], mats = (C.materials || []).slice(0, 8);
    return '<main class="screen t-screen sh-screen">' + header() +
      '<section class="t-hero"><div class="t-hero-art">' + (C.hero && C.hero.image ? '<img src="' + esc(C.hero.image) + '" alt="">' : villaArt()) + sparkleSvg('t-hero-spark') + '</div>' +
        '<div class="t-hero-body"><h1>' + esc(loc(C.hero && C.hero.title)) + '</h1><p>' + esc(loc(C.hero && C.hero.text)) + '</p>' +
        '<a class="btn btn-ink" href="#services">' + esc(t('hero_btn')) + icon('arrowRight', 'sm') + '</a></div></section>' +
      (tiles ? '<nav class="t-tiles">' + tiles + '</nav>' : '') +
      (unis ? '<section class="t-card glass"><div class="t-card-head"><h2>' + esc(t('unis_title')) + '</h2></div><div class="t-chips">' + unis + '</div></section>' : '') +
      (promo ? promoCard(promo) : '') +
      '<div class="t-duo">' +
        '<section class="t-card glass t-adv"><h3>' + esc(t('adv_title')) + '</h3>' + ['adv1', 'adv2', 'adv3'].map(function (k) { return '<div class="t-check"><span>' + icon('check', 'xs') + '</span>' + esc(t(k)) + '</div>'; }).join('') + '</section>' +
        '<button class="t-card glass t-rev" data-act="reviews"><h3>' + esc(t('reviews')) + '</h3>' +
          (st.count ? '<div class="t-rating"><b>' + esc(st.average) + '</b><span class="t-star">' + icon('star') + '</span><span class="tiny">' + esc(t('reviews_count', { n: st.count })) + '</span></div>' : '<p class="tiny">' + esc(t('no_reviews')) + '</p>') +
          '<div class="t-rev-row"><div class="t-stack">' + [0, 1, 2].map(function (i) { return anonAvatar('s' + i); }).join('') + '</div>' +
          (rv ? '<span class="t-quote">«' + esc(rv.text.length > 46 ? rv.text.slice(0, 44) + '…' : rv.text) + '»</span>' : '') + '</div></button>' +
      '</div>' +
      shhCard() +
      (mats.length ? '<section class="section"><div class="section-head"><h2>' + esc(t('materials')) + '</h2><a class="link-more" href="#materials">' + esc(t('all')) + icon('arrowRight', 'sm') + '</a></div><div class="h-scroll t-mats">' + mats.map(materialCard).join('') + '</div></section>' : '') +
      '</main>';
  }
  function promoCard(p, big) {
    return '<section class="t-promo' + (big ? ' big' : '') + '"><div class="t-promo-art">' + (p.image ? '<img src="' + esc(p.image) + '" alt="">' : villaArt('t-villa-promo')) + sparkleSvg('t-promo-spark') + '</div>' +
      '<div class="t-promo-body"><span class="t-badge">' + esc(loc(p.badge) || t('nav_promos')) + '</span><h3>' + esc(loc(p.title)) + '</h3>' + (loc(p.text) ? '<p>' + esc(loc(p.text)) + '</p>' : '') +
      '<button class="btn btn-ink btn-sm" data-promo="' + esc(p.id) + '">' + esc(big ? t('order') : t('promo_more')) + icon('arrowRight', 'xs') + '</button></div></section>';
  }
  function materialCard(m) {
    return '<a class="t-mat" href="' + esc(m.url || '#materials') + '"' + (m.url ? ' target="_blank" rel="noopener"' : '') + '><span class="t-mat-img">' + (m.image ? '<img src="' + esc(m.image) + '" alt="" loading="lazy">' : icon('lecture', 'lg')) + '</span><span class="t-mat-t">' + esc(loc(m.title)) + '</span></a>';
  }
  function reviewCard(r) {
    var who = r.university ? t('student_of', { uni: r.university }) : t('student');
    return '<article class="review glass"><div class="review-head">' + anonAvatar(r.id) + '<div><div class="review-who">' + esc(who) + '</div>' + stars(r.rating) + '</div></div><p>“' + esc(r.text) + '”</p></article>';
  }
  function pageHead(title, sub) { return '<header class="page-hd"><h1 class="page-title">' + esc(title) + '</h1>' + (sub ? '<p class="page-sub">' + esc(sub) + '</p>' : '') + '</header>'; }
  function viewServices() {
    return '<main class="screen t-screen sh-screen">' + pageHead(t('services_title'), t('services_sub')) + '<div class="svc-list">' + (C.services || []).map(function (s, i) {
      return '<article class="svc glass" style="--d:' + i + '"><div class="svc-ico tone-bg">' + icon(s.icon, 'lg') + '</div><div><h3>' + esc(loc(s.title)) + '</h3><p>' + esc(loc(s.desc)) + '</p></div>' +
        '<div class="svc-foot">' + (s.price ? '<span class="price">' + esc(s.price) + '</span>' : '<span class="price na">' + esc(t('price_chat')) + '</span>') +
        '<button class="btn btn-ink btn-sm" data-order="' + esc(s.id) + '">' + esc(t('order')) + icon('arrowRight', 'xs') + '</button></div></article>';
    }).join('') + '</div>' + shhCard() + '</main>';
  }
  function viewPromos() {
    var l = C.promos || [];
    return '<main class="screen t-screen sh-screen">' + pageHead(t('nav_promos'), t('promos_sub')) +
      (l.length ? l.map(function (p) { return promoCard(p, true); }).join('') : '<div class="empty glass">' + icon('tag') + esc(t('no_promos')) + '</div>') +
      '<section class="section"><div class="section-head"><h2>' + esc(t('reviews')) + '</h2></div>' + (state.reviews.length ? '<div class="h-scroll">' + state.reviews.map(reviewCard).join('') + '</div>' : '<div class="empty glass">' + icon('star') + esc(t('no_reviews')) + '</div>') + '</section></main>';
  }
  function viewMaterials() {
    var l = C.materials || [];
    return '<main class="screen t-screen sh-screen"><a class="back" href="#home">' + icon('chevLeft', 'sm') + esc(t('nav_home')) + '</a>' + pageHead(t('materials'), t('materials_sub')) +
      (l.length ? '<div class="t-mat-grid">' + l.map(materialCard).join('') + '</div>' : '<div class="empty glass">' + icon('lecture') + esc(t('no_materials')) + '</div>') + '</main>';
  }
  function startOrder(id) {
    var s = serviceById(id); if (!s) return;
    state.draftService = s.id; state.draftText = t('order_text', { svc: loc(s.title) }); vibrate(); go('chat');
  }
  function startPromo(id) {
    var p = (C.promos || []).filter(function (x) { return x.id === id; })[0]; if (!p) return;
    var s = p.service && serviceById(p.service);
    state.draftService = s ? s.id : null;
    state.draftText = t('order_promo', { p: loc(p.title) }) + (s ? t('order_text', { svc: loc(s.title) }).split('\n').slice(1).join('\n') : '');
    vibrate(); go('chat');
  }

  /* ================================================================ профиль */
  function viewProfile() {
    var cur = uniById(state.student.university), s = state.student;
    var bio = bioName();
    var sec = '';
    if (state.pkAvail) {
      sec = s.passkeys
        ? '<label class="list-item sw-row"><span class="li-ico tone-bg">' + icon('face', 'sm') + '</span><span class="grow"><span class="t">' + esc(tb('bio_lock')) + '</span><br><span class="s">' + esc(t('bio_lock_sub')) + '</span></span><input type="checkbox" class="sw" data-act="face-lock"' + (s.faceLock ? ' checked' : '') + '></label>' +
          '<button class="list-item" data-act="pk-off"><span class="li-ico tone-bg">' + icon('x', 'sm') + '</span><span class="grow"><span class="t">' + esc(tb('bio_off')) + '</span></span></button>'
        : '<button class="list-item" data-act="pk-on"><span class="li-ico tone-bg">' + icon('face', 'sm') + '</span><span class="grow"><span class="t">' + esc(tb('bio_on')) + '</span><br><span class="s">' + esc(t('bio_on_sub')) + '</span></span>' + icon('chevRight', 'sm chev') + '</button>';
    }
    return '<main class="screen t-screen sh-screen">' + pageHead(t('profile_title')) +
      '<section class="profile-card glass">' + anonAvatar(s.alias, 'big-ava') + '<h2>' + esc(t('student')) + ' ' + esc(s.alias) + '</h2><p class="muted">' + esc(t('anon_id')) + '</p></section>' +
      '<div class="list glass">' +
        '<button class="list-item" data-act="uni-sheet"><span class="li-ico tone-bg">' + icon('grad', 'sm') + '</span><span class="grow"><span class="t">' + esc(t('university')) + '</span><br><span class="s">' + esc(cur ? uniName(cur) : t('not_selected')) + '</span></span>' + icon('chevRight', 'sm chev') + '</button>' +
        '<div class="list-item"><span class="li-ico tone-bg">' + icon('globe', 'sm') + '</span><span class="grow"><span class="t">' + esc(t('language')) + '</span></span>' + langSeg() + '</div>' +
        '<div class="list-item li-col"><span class="li-row"><span class="li-ico tone-bg">' + icon('sparkle', 'sm') + '</span><span class="grow"><span class="t">' + esc(t('theme')) + '</span></span></span>' + seg('theme', [['light', t('th_light')], ['dark', t('th_dark')], ['auto', t('th_auto')]], themeGet(), 'seg2-wide') + '</div>' +
      '</div>' +
      '<h2 class="list-title">' + esc(t('security')) + '</h2>' +
      '<div class="list glass">' + sec +
        '<div class="list-item"><span class="li-ico tone-bg">' + icon('phone', 'sm') + '</span><span class="grow"><span class="s">' + esc(t('device_note')) + '</span></span></div>' +
        (STANDALONE ? '' : '<button class="list-item" data-act="install"><span class="li-ico tone-bg">' + icon('share', 'sm') + '</span><span class="grow"><span class="t">' + esc(t('install')) + '</span><br><span class="s">' + esc(t('install_sub')) + '</span></span>' + icon('chevRight', 'sm chev') + '</button>') +
      '</div>' +
      shhCard() +
      '<div class="privacy glass">' + ['priv1', 'priv2', 'priv3'].map(function (k) { return '<div class="privacy-row">' + icon('check', 'sm') + '<span>' + esc(t(k)) + '</span></div>'; }).join('') + '</div>' +
      '<div class="list glass"><button class="list-item danger" data-act="logout"><span class="li-ico li-danger">' + icon('logout', 'sm') + '</span><span class="grow"><span class="t">' + esc(t('logout')) + '</span></span></button></div>' +
      '</main>';
  }
  function uniSheet() {
    var cur = state.student.university;
    sheet((C.universities || []).map(function (u) {
      return '<button class="option" data-pick-uni="' + esc(u.id) + '" aria-pressed="' + (u.id === cur) + '"><span class="opt-ico">' + icon('grad', 'sm') + '</span><span class="grow"><span class="t">' + esc(uniName(u)) + '</span>' + (loc(u.name) && loc(u.name) !== u.short ? '<br><span class="s">' + esc(loc(u.name)) + '</span>' : '') + '</span>' + (u.id === cur ? icon('check', 'check') : '') + '</button>';
    }).join(''), { title: t('uni_sheet'), onMount: function (s, close) {
      s.addEventListener('click', function (e) { var b = e.target.closest('[data-pick-uni]'); if (b) { setUni(b.getAttribute('data-pick-uni')); close(); } });
    } });
  }
  function setUni(id) {
    state.student.university = state.student.university === id ? '' : id;
    vibrate(); render();
    api('me', { method: 'POST', body: { university: state.student.university } }).catch(function () { toast(t('send_failed'), true); });
  }
  function installSheet() {
    if (state.installPrompt) { state.installPrompt.prompt(); state.installPrompt.userChoice.then(function (c) { if (c.outcome === 'accepted') toast(t('installed')); state.installPrompt = null; }); return; }
    var steps = IOS ? t('install_ios') : t('install_other'), ic = IOS ? ['share', 'plusSq', 'phone'] : ['grid', 'download', 'phone'];
    sheet('<p class="muted sh2-lead">' + esc(t('install_s')) + '</p><div class="howto">' + steps.map(function (s, i) { return '<div><span class="step-n">' + icon(ic[i], 'sm') + '</span><span>' + esc(s) + '</span></div>'; }).join('') + '</div><button class="btn btn-ink btn-block" data-close>' + esc(t('close')) + '</button>', { title: t('install_t') });
  }
  function logout() {
    if (!W.confirm(t('logout_confirm'))) return;
    api('logout', { method: 'POST' }).catch(function () {}).then(function () { SS.del('studyhub_ai'); location.replace(C.appUrl.split('#')[0]); });
  }

  /* ================================================================ чат с администратором */
  function viewChat() {
    return '<main class="chat2 sh-screen">' +
      '<header class="chat2-hd glass">' + brandTile('sm') +
        '<div class="grow"><b>' + esc(C.brand) + '</b><span class="chat2-st" id="chat-status"></span></div>' +
        '<span class="anon-note">' + icon('lock', 'xs') + esc(t('chat_anon')) + '</span></header>' +
      '<div class="feed" id="feed"><div class="feed-in" id="feed-in">' + (state.loaded ? '' : '<div class="skeleton" style="height:58px;width:70%"></div><div class="skeleton" style="height:42px;width:50%;align-self:flex-end"></div>') + '</div><div id="feed-tail"></div></div>' +
      '<button class="to-bottom" id="to-bottom" hidden aria-label="' + esc(t('new_msgs')) + '">' + icon('arrowDown', 'sm') + '<span class="badge" hidden></span></button>' +
      '<div class="composer2">' +
        '<div id="composer-chip"></div>' +
        '<form class="composer2-in" id="composer">' +
          '<input type="file" id="file-input" class="sr" accept="' + esc(C.accept || '') + '">' +
          '<button type="button" class="c-btn c-attach" data-act="attach" aria-label="' + esc(t('attach')) + '">' + icon('clip', 'sm') + '</button>' +
          '<textarea id="msg-input" rows="1" placeholder="' + esc(t('placeholder')) + '" aria-label="' + esc(t('placeholder')) + '"></textarea>' +
          '<button class="c-btn c-send" id="send" aria-label="' + esc(t('send')) + '" disabled>' + icon('arrowUp', 'sm') + '</button>' +
        '</form></div></main>';
  }
  function updateChatStatus() {
    var el = D.getElementById('chat-status');
    if (el) el.innerHTML = '<i class="status-dot' + (state.adminOnline ? ' on' : '') + '"></i>' + esc(state.adminOnline ? t('chat_on') : t('chat_off'));
  }
  function feedEl() { return D.getElementById('feed'); }
  function nearBottom() { var f = feedEl(); return !f || f.scrollHeight - f.scrollTop - f.clientHeight < 90; }
  function toBottom(smooth) { var f = feedEl(); if (f) f.scrollTo({ top: f.scrollHeight, behavior: smooth && !reduce ? 'smooth' : 'auto' }); }
  var unseen = 0;
  function mountChat() {
    updateChatStatus();
    var input = $('#msg-input'), send = $('#send'), form = $('#composer'), file = $('#file-input'), f = feedEl(), tb = $('#to-bottom');
    if (state.draftText) { input.value = state.draftText; state.draftText = null; }
    renderChip();
    function sync() { input.style.height = 'auto'; input.style.height = Math.min(140, input.scrollHeight) + 'px'; send.disabled = !input.value.trim(); }
    input.addEventListener('input', sync);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !('ontouchstart' in W)) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true })); } });
    input.addEventListener('focus', function () { setTimeout(function () { toBottom(false); }, 250); });
    sync();
    file.addEventListener('change', function () {
      var fl = file.files[0]; file.value = '';
      if (!fl) return;
      if (fl.size > (C.maxUploadMb || 20) * 1048576) { toast(t('file_too_large', { mb: C.maxUploadMb }), true); return; }
      sendMessage('', fl);
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var txt = input.value.trim(); if (!txt) return;
      input.value = ''; sync(); input.focus();
      sendMessage(txt, null);
    });
    f.addEventListener('scroll', function () { if (nearBottom()) { unseen = 0; tb.hidden = true; } else tb.hidden = false; updateUnseen(); }, { passive: true });
    tb.addEventListener('click', function () { toBottom(true); });
    if (state.loaded) renderFeed();
    fetchMessages(!state.loaded);
  }
  function updateUnseen() { var b = $('#to-bottom .badge'); if (b) { b.hidden = !unseen; b.textContent = unseen; } }
  function renderChip() {
    var box = D.getElementById('composer-chip'); if (!box) return;
    var s = state.draftService && serviceById(state.draftService);
    box.innerHTML = s ? '<div class="c-chip"><span class="svc-tag">' + icon(s.icon, 'xs') + esc(loc(s.title)) + '</span><button type="button" data-act="clear-chip" aria-label="' + esc(t('remove')) + '">' + icon('x', 'xs') + '</button></div>' : '';
  }
  function findMsg(id) { for (var i = 0; i < state.messages.length; i++) if (state.messages[i].id === id) return state.messages[i]; return null; }

  function sendMessage(text, file, retryOf) {
    var m = retryOf || { id: 'tmp' + Date.now(), from: 'me', type: file ? 'file' : 'text', text: text, file: file ? { name: file.name, size: file.size } : null, _file: file, time: nowServer(), meta: state.draftService ? { service: state.draftService } : null };
    m.pending = true; m.failed = false;
    if (!retryOf) { state.messages.push(m); appendMsgs([m]); toBottom(true); } else updateMsg(m);
    var fd = new FormData();
    fd.append('text', m.text || '');
    if (m._file) fd.append('file', m._file);
    if (m.meta && m.meta.service) fd.append('service', m.meta.service);
    state.draftService = null; renderChip();
    api('messages', { method: 'POST', body: fd }).then(function (d) {
      var old = m.id;
      Object.keys(d.message).forEach(function (k) { m[k] = d.message[k]; });
      m.pending = false; delete m._file;
      var el = D.querySelector('[data-id="' + old + '"]'); if (el) el.setAttribute('data-id', m.id);
      state.lastId = Math.max(state.lastId, m.id);
      updateMsg(m); renderTail();
    }).catch(function (ex) {
      m.pending = false; m.failed = true; updateMsg(m);
      toast(ex.status === 415 ? t('file_type') : ex.status === 413 ? t('file_too_large', { mb: C.maxUploadMb }) : t('send_failed'), true);
    });
  }

  function fetchMessages(initial) {
    return api('messages?after=' + (initial ? 0 : state.lastId)).then(function (d) {
      state.serverOffset = d.now ? d.now - Math.floor(Date.now() / 1000) : 0;
      var wasOnline = state.adminOnline; state.adminOnline = !!d.adminOnline;
      var readChanged = state.readUpTo !== (d.readUpTo || 0); state.readUpTo = d.readUpTo || 0;
      var fresh = [];
      if (initial) {
        var keep = state.messages.filter(function (m) { return m.pending || m.failed; });
        state.messages = d.messages.concat(keep);
      } else d.messages.forEach(function (m) { if (!findMsg(m.id)) { state.messages.push(m); fresh.push(m); } });
      state.messages.forEach(function (m) { if (typeof m.id === 'number') state.lastId = Math.max(state.lastId, m.id); });
      var first = !state.loaded; state.loaded = true; state.unread = 0;
      if (route() !== 'chat' || !feedEl()) return;
      if (wasOnline !== state.adminOnline) updateChatStatus();
      if (initial || first) { renderFeed(); toBottom(false); return; }
      if (fresh.length) {
        var stick = nearBottom();
        appendMsgs(fresh);
        if (stick) toBottom(true); else { unseen += fresh.filter(function (m) { return m.from !== 'me'; }).length; updateUnseen(); $('#to-bottom').hidden = false; }
        if (fresh.some(function (m) { return m.from !== 'me'; })) vibrate(10);
      }
      if (readChanged) qa('.msg2.me[data-id]').forEach(function (el) { var m = findMsg(+el.getAttribute('data-id')); if (m) updateMsg(m); });
      renderTail();
    }).catch(handleAuthError);
  }

  /* лента собирается целиком только при открытии; потом сообщения добавляются по одному */
  var lastDay = '', lastFrom = null;
  function renderFeed() {
    var box = D.getElementById('feed-in'); if (!box) return;
    lastDay = ''; lastFrom = null;
    box.innerHTML = '<div class="day">' + esc(C.brand) + ' · ' + esc(t('chat_anon')) + '</div>' +
      '<div class="msg2 them first"><div class="bubble">' + esc(loc(C.welcome)) + '</div></div>' +
      '<div class="shh-mini">🤫 ' + esc(t('shh_text')) + '</div>';
    lastFrom = 'brand';
    box.insertAdjacentHTML('beforeend', state.messages.map(msgBlock).join(''));
    renderTail();
  }
  function appendMsgs(list) {
    var box = D.getElementById('feed-in'); if (!box) return;
    var html = list.map(msgBlock).join('');
    var tmp = D.createElement('div'); tmp.innerHTML = html;
    Array.prototype.slice.call(tmp.children).forEach(function (n) { if (n.classList.contains('msg2') || n.classList.contains('sys-card')) n.classList.add('pop'); box.appendChild(n); });
  }
  function msgBlock(m) {
    var day = fmtDay(m.time), out = '';
    if (day !== lastDay) { out += '<div class="day">' + esc(day) + '</div>'; lastDay = day; lastFrom = null; }
    var from = m.from === 'me' ? 'me' : 'them';
    out += msgHTML(m, lastFrom === from);
    lastFrom = from;
    return out;
  }
  function tick(m) {
    if (m.from !== 'me') return '';
    if (m.pending) return '<i class="tk">' + icon('clock') + '</i>';
    if (m.failed) return '';
    return typeof m.id === 'number' && m.id <= state.readUpTo ? '<i class="tk read">' + icon('checks') + '</i>' : '<i class="tk">' + icon('check') + '</i>';
  }
  function msgHTML(m, grouped) {
    if (m.type === 'review_request') return reviewRequestHTML(m);
    var mine = m.from === 'me', inner = '';
    if (m.meta && m.meta.service) { var s = serviceById(m.meta.service); if (s) inner += '<span class="svc-tag">' + icon(s.icon, 'xs') + esc(loc(s.title)) + '</span>'; }
    var time = '<span class="mt">' + fmtTime(m.time) + tick(m) + '</span>';
    if (m.type === 'review') inner += '<div class="bubble">' + stars((m.meta && m.meta.rating) || 5) + '<br>' + esc(m.text) + time + '</div>';
    else {
      if (m.file) {
        if (m.file.isImage && m.file.url) inner += '<div class="msg-img"><img src="' + esc(m.file.url) + '" alt="" loading="lazy" data-view="' + esc(m.file.url) + '">' + (m.text ? '' : time) + '</div>';
        else inner += '<a class="msg-file"' + (m.file.url ? ' href="' + esc(withQuery(m.file.url, 'download=1')) + '"' : '') + '><span class="file-ico">' + esc((m.file.name.split('.').pop() || '').slice(0, 4)) + '</span><span><span class="file-name">' + esc(m.file.name) + '</span><span class="file-size">' + fmtSize(m.file.size || 0) + (m.text ? '' : ' · ' + fmtTime(m.time)) + '</span></span>' + (m.text ? '' : tick(m)) + '</a>';
      }
      if (m.text) inner += '<div class="bubble">' + linkify(esc(m.text)) + time + '</div>';
    }
    return '<div class="msg2 ' + (mine ? 'me' : 'them') + (grouped ? ' grp' : '') + (m.pending ? ' pending' : '') + (m.failed ? ' failed' : '') + '" data-id="' + esc(m.id) + '">' + inner +
      (m.failed ? '<button class="retry" data-retry="' + esc(m.id) + '">' + esc(t('retry')) + '</button>' : '') + '</div>';
  }
  function updateMsg(m) {
    var el = D.querySelector('[data-id="' + m.id + '"]'); if (!el) return;
    var tmp = D.createElement('div'); tmp.innerHTML = msgHTML(m, el.classList.contains('grp'));
    el.replaceWith(tmp.firstChild);
  }
  function linkify(html) { return html.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'); }
  function reviewRequestHTML(m) {
    var meta = m.meta || {};
    if (meta.done) return '<div class="sys-card glass" data-id="' + m.id + '"><h4>' + icon('star', 'sm') + esc(t('review_thanks')) + '</h4>' + stars(meta.rating || 5) + '</div>';
    return '<form class="sys-card glass" data-review="' + m.id + '" data-id="' + m.id + '"><h4>' + aiStar('', 24) + esc(t('review_t')) + '</h4><p>' + esc(t('review_s')) + '</p>' +
      '<div class="star-input">' + [1, 2, 3, 4, 5].map(function (n) { return '<button type="button" data-star="' + n + '" aria-label="' + n + '">' + icon('star') + '</button>'; }).join('') + '</div>' +
      '<textarea class="input ta" name="text" maxlength="600" rows="3" placeholder="' + esc(t('review_ph')) + '"></textarea>' +
      '<button class="btn btn-ink btn-sm" type="submit" style="margin-top:10px">' + icon('lock', 'xs') + esc(t('review_send')) + '</button></form>';
  }
  /* подсказка ИИ, если администратор не в сети или не ответил за N минут */
  function renderTail() {
    var box = D.getElementById('feed-tail'); if (!box) return;
    var lastMine = null, answered = false;
    for (var i = state.messages.length - 1; i >= 0; i--) {
      var m = state.messages[i];
      if (m.from === 'me' && m.type !== 'review') { lastMine = m; break; }
      if (m.from === 'brand') { answered = true; break; }
    }
    var html = '';
    if (lastMine && !answered && !lastMine.pending && !lastMine.failed && state.aiDismissedFor !== lastMine.id) {
      var waited = nowServer() - lastMine.time;
      if (!state.adminOnline || waited >= (C.fallbackMinutes || 5) * 60) {
        html = C.ai && C.ai.enabled
          ? '<div class="sys-card ai-suggest"><h4>' + aiStar('', 28) + esc(t('ai_suggest_t')) + '</h4><p>' + esc(t('ai_suggest_s')) + '</p><div class="row"><button class="btn btn-ink btn-sm" data-act="ask-ai" data-msg="' + esc(lastMine.id) + '">' + icon('sparkle', 'xs') + esc(t('ai_suggest_btn')) + '</button><button class="btn btn-ghost btn-sm" data-act="dismiss-ai" data-msg="' + esc(lastMine.id) + '">' + esc(t('ai_suggest_wait')) + '</button></div></div>'
          : (!state.adminOnline ? '<div class="day">' + esc(t('offline_note')) + '</div>' : '');
      }
    }
    if (box.innerHTML !== html) box.innerHTML = html;
  }
  function submitReview(form) {
    var rating = +(form.getAttribute('data-rating') || 0), text = form.text.value.trim(), btn = $('[type=submit]', form);
    if (!rating) { toast(t('review_rate'), true); shake($('.star-input', form)); return; }
    if (text.length < 3) { toast(t('review_short'), true); shake(form.text); return; }
    busy(btn, true);
    var id = +form.getAttribute('data-review');
    api('review', { method: 'POST', body: { invite: id, rating: rating, text: text } }).then(function (res) {
      var m = findMsg(id); if (m) { m.meta = m.meta || {}; m.meta.done = true; m.meta.rating = rating; updateMsg(m); }
      toast(t('review_thanks') + (res.status === 'pending' ? ' ' + t('review_pending') : ''));
      api('reviews').then(function (d) { state.reviews = d.reviews || []; state.stats = d.stats || state.stats; }).catch(function () {});
      fetchMessages(false);
    }).catch(function (ex) { busy(btn, false); toast(ex.status === 409 ? t('review_thanks') : t('send_failed'), true); });
  }

  /* ================================================================ ИИ (вкладка у студента и лист у гостя) */
  function aiInner(guest) {
    return '<div class="ai-wrap' + (guest ? ' guest' : '') + '"><div class="feed ai-feed"><div class="feed-in ai-list"></div></div>' +
      '<div class="composer2"><div class="suggestions ai-chips"></div>' + (guest ? '<p class="note ai-note">' + icon('doc', 'xs') + esc(t('ai_guest_note')) + '</p>' : '') +
      '<form class="composer2-in ai-form"><textarea rows="1" placeholder="' + esc(t('ai_ph')) + '" aria-label="' + esc(t('ai_ph')) + '"></textarea>' +
      '<button class="c-btn c-send" disabled aria-label="' + esc(t('send')) + '">' + icon('arrowUp', 'sm') + '</button></form></div></div>';
  }
  function viewAI() {
    return '<main class="chat2 sh-screen"><header class="chat2-hd glass">' + aiStar('', 36) + '<div class="grow"><b>' + esc(t('ai_title')) + '</b><span class="chat2-st">' + esc(t('ai_disclaimer')) + '</span></div>' +
      (state.ai.messages.length ? '<button class="c-btn" data-act="ai-clear" aria-label="' + esc(t('ai_clear')) + '">' + icon('trash', 'sm') + '</button>' : '') + '</header>' + aiInner(false) + '</main>';
  }
  function mountAI(wrap) {
    if (!wrap) return;
    var input = $('textarea', wrap), send = $('.c-send', wrap), form = $('.ai-form', wrap);
    function sync() { input.style.height = 'auto'; input.style.height = Math.min(140, input.scrollHeight) + 'px'; send.disabled = !input.value.trim() || state.ai.busy; }
    input.addEventListener('input', sync);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !('ontouchstart' in W)) { e.preventDefault(); form.dispatchEvent(new Event('submit', { cancelable: true })); } });
    form.addEventListener('submit', function (e) { e.preventDefault(); var q = input.value.trim(); if (!q || state.ai.busy) return; input.value = ''; sync(); askAI(q, wrap); });
    wrap.addEventListener('click', function (e) { var c = e.target.closest('[data-ai-chip]'); if (c) askAI(c.getAttribute('data-ai-chip'), wrap); });
    renderAI(wrap);
    if (state.pendingAIQuestion) { var q = state.pendingAIQuestion; state.pendingAIQuestion = null; askAI(q, wrap); }
  }
  function mdLite(text) {
    var h = esc(text).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/^\s*[-*•]\s+/gm, '• ').replace(/^#{1,4}\s*(.+)$/gm, '<b>$1</b>');
    return linkify(h);
  }
  function renderAI(wrap) {
    var list = $('.ai-list', wrap), feed = $('.ai-feed', wrap); if (!list) return;
    var html = state.ai.messages.length ? '' : '<div class="ai-welcome">' + aiStar() + '<h3>' + esc(t('ai_hello_t')) + '</h3><p>' + esc(t('ai_hello_s')) + '</p></div>';
    state.ai.messages.forEach(function (m) { html += '<div class="msg2 ' + (m.role === 'user' ? 'me' : 'them') + '"><div class="bubble">' + (m.role === 'user' ? esc(m.content) : mdLite(m.content)) + '</div></div>'; });
    if (state.ai.busy) html += '<div class="msg2 them"><div class="bubble typing"><i></i><i></i><i></i></div></div>';
    list.innerHTML = html;
    feed.scrollTop = feed.scrollHeight;
    var chips = $('.ai-chips', wrap);
    chips.innerHTML = state.ai.messages.length ? '' : t('ai_chips').map(function (c) { return '<button class="chip" data-ai-chip="' + esc(c) + '">' + esc(c) + '</button>'; }).join('');
    var send = $('.c-send', wrap), input = $('textarea', wrap); send.disabled = !input.value.trim() || state.ai.busy;
  }
  function askAI(q, wrap) {
    state.ai.messages.push({ role: 'user', content: q }); state.ai.busy = true; renderAI(wrap);
    api(C.ai.endpoint, { method: 'POST', body: { messages: state.ai.messages.slice(-12), lang: state.lang } })
      .then(function (d) { state.ai.messages.push({ role: 'assistant', content: d.reply || t('ai_error') }); })
      .catch(function (ex) { state.ai.messages.push({ role: 'assistant', content: ex.status === 429 ? t('ai_limit') : t('ai_error') }); })
      .then(function () { state.ai.busy = false; SS.set('studyhub_ai', JSON.stringify(state.ai.messages.slice(-30))); if (wrap.isConnected) renderAI(wrap); });
  }
  function guestAI() {
    sheet(aiInner(true), { title: t('ai_title'), tall: true, cls: 'sh2-chat', onMount: function (s) { mountAI($('.ai-wrap', s)); } });
  }

  /* ================================================================ фото на весь экран */
  function viewer(src) {
    var v = D.createElement('div'); v.className = 'viewer';
    v.innerHTML = '<img src="' + esc(src) + '" alt=""><a class="icon-btn dl" href="' + esc(withQuery(src, 'download=1')) + '" aria-label="' + esc(t('download')) + '">' + icon('download') + '</a><button class="icon-btn close" aria-label="' + esc(t('close')) + '">' + icon('x') + '</button>';
    v.addEventListener('click', function (e) { if (!e.target.closest('.dl')) v.remove(); });
    D.body.appendChild(v);
  }

  /* ================================================================ все нажатия — одним обработчиком */
  D.addEventListener('click', function (e) {
    var el;
    if ((el = e.target.closest('[data-seg] button'))) {
      var sg = el.parentNode, name = sg.getAttribute('data-seg'), v = el.getAttribute('data-v');
      if (name === 'lang') { segSet(sg, v); vibrate(6); setTimeout(function () { setLang(v); }, reduce ? 0 : 180); return; }
      if (name === 'theme') { segSet(sg, v); vibrate(6); LS.set('sh_theme', v); themeApply(true); return; }
      return;
    }
    if ((el = e.target.closest('[data-order]'))) { startOrder(el.getAttribute('data-order')); return; }
    if ((el = e.target.closest('[data-promo]'))) { startPromo(el.getAttribute('data-promo')); return; }
    if ((el = e.target.closest('[data-uni]'))) { setUni(el.getAttribute('data-uni')); return; }
    if ((el = e.target.closest('[data-view]'))) { viewer(el.getAttribute('data-view')); return; }
    if ((el = e.target.closest('[data-retry]'))) { var rm = findMsg(el.getAttribute('data-retry')); if (rm) sendMessage(rm.text, rm._file, rm); return; }
    if ((el = e.target.closest('[data-star]'))) {
      var form = el.closest('form'), n = +el.getAttribute('data-star');
      form.setAttribute('data-rating', n);
      qa('[data-star]', form).forEach(function (b) { b.classList.toggle('on', +b.getAttribute('data-star') <= n); });
      vibrate(6); return;
    }
    if (!(el = e.target.closest('[data-act]'))) return;
    var a = el.getAttribute('data-act');
    if (a === 'eye') { var p = el.parentNode.querySelector('input'); p.type = p.type === 'password' ? 'text' : 'password'; el.innerHTML = icon(p.type === 'password' ? 'eye' : 'eyeOff', 'sm'); }
    else if (a === 'request') requestSheet();
    else if (a === 'request-new') { api('request/forget', { method: 'POST' }).catch(function () {}); state.request = null; requestSheet(); renderGate(); }
    else if (a === 'guest-ai') guestAI();
    else if (a === 'claim') claim(el);
    else if (a === 'install') installSheet();
    else if (a === 'pk-login') {
      busy(el, true);
      pkGet('login').then(function () { location.reload(); }).catch(function (ex) { busy(el, false); if (ex && ex.name === 'NotAllowedError') return; toast(ex && ex.status ? errText(ex) : t('bio_fail'), true); });
    }
    else if (a === 'unlock') {
      busy(el, true);
      pkGet('unlock').then(function () { location.reload(); }).catch(function (ex) { busy(el, false); if (ex && ex.name !== 'NotAllowedError') toast(t('bio_fail'), true); });
    }
    else if (a === 'pk-on') {
      pkRegister().then(function () {
        while (stack.length) closeSheet();
        toast(tb('bio_done')); vibrate(15);
        return api('me', { method: 'POST', body: { faceLock: 1 } }).then(function (d) { state.student = Object.assign(state.student, d.student); if (route() === 'profile') render(); });
      }).catch(function (ex) { if (ex && ex.name === 'NotAllowedError') return; toast(t('bio_fail'), true); });
    }
    else if (a === 'pk-off') {
      api('passkey/delete', { method: 'POST' }).then(function () { LS.del('sh_pk'); state.student.passkeys = 0; state.student.faceLock = 0; render(); }).catch(function () { toast(t('send_failed'), true); });
    }
    else if (a === 'face-lock') {
      api('me', { method: 'POST', body: { faceLock: el.checked ? 1 : 0 } }).then(function (d) { state.student.faceLock = d.student.faceLock; }).catch(function () { el.checked = !el.checked; toast(t('send_failed'), true); });
    }
    else if (a === 'uni-sheet') uniSheet();
    else if (a === 'logout') logout();
    else if (a === 'attach') { var fi = $('#file-input'); if (fi) fi.click(); }
    else if (a === 'clear-chip') { state.draftService = null; renderChip(); }
    else if (a === 'ai-clear') { state.ai.messages = []; SS.del('studyhub_ai'); render(); }
    else if (a === 'dismiss-ai') { state.aiDismissedFor = +el.getAttribute('data-msg'); renderTail(); }
    else if (a === 'ask-ai') {
      var lastId = +el.getAttribute('data-msg'), q = [];
      for (var i = state.messages.length - 1; i >= 0; i--) { var m = state.messages[i]; if (m.from !== 'me' || m.type === 'review') break; if (m.text) q.unshift(m.text); if (q.length > 2) break; }
      state.aiDismissedFor = lastId; state.pendingAIQuestion = q.join('\n').trim() || null; go('ai');
    }
    else if (a === 'reviews') sheet(state.reviews.length ? '<div class="t-rev-list">' + state.reviews.map(reviewCard).join('') + '</div>' : '<div class="empty">' + esc(t('no_reviews')) + '</div>', { title: t('reviews') });
  });
  D.addEventListener('submit', function (e) { var f = e.target.closest('[data-review]'); if (f) { e.preventDefault(); submitReview(f); } });

  function setLang(l) {
    state.lang = l === 'kk' ? 'kk' : 'ru';
    LS.set('studyhub_lang', state.lang);
    try { D.cookie = 'studyhub_lang=' + state.lang + ';path=/;max-age=31536000;samesite=lax'; } catch (e) { /* ignore */ }
    if (state.student) { state.student.lang = state.lang; api('me', { method: 'POST', body: { lang: state.lang } }).catch(function () {}); }
    render();
  }
  function handleAuthError(ex) { if (ex && ex.status === 401 && state.student) location.reload(); }

  /* ================================================================ клавиатура: поле ввода всегда над ней, меню прячется */
  function vv() {
    var v = W.visualViewport, el = D.documentElement;
    var h = v ? v.height : W.innerHeight;
    el.style.setProperty('--vvh', h + 'px');
    el.style.setProperty('--vvt', (v ? v.offsetTop : 0) + 'px');
    el.classList.toggle('kb-open', !!(v && W.innerHeight - v.height > 120));
  }
  if (W.visualViewport) { W.visualViewport.addEventListener('resize', vv); W.visualViewport.addEventListener('scroll', vv); }
  W.addEventListener('resize', function () { vv(); lensMove(true); segAll(); });
  vv();

  /* ================================================================ обновления */
  var pollTimer = null, tickN = 0;
  function poll() {
    if (D.hidden || !state.student) return;
    tickN++;
    if (route() === 'chat') { fetchMessages(!state.loaded); return; }
    if (tickN % 3) return;
    api('me').then(function (d) {
      var changed = d.unread !== state.unread;
      state.unread = d.unread; state.adminOnline = !!d.adminOnline;
      state.serverOffset = d.now ? d.now - Math.floor(Date.now() / 1000) : state.serverOffset;
      if (changed) {
        var bar = $('.tabbar2'); if (bar) { var r = route(); bar.outerHTML = tabbar(r); lensMove(true); }
        var bell = $('.t-bell'); if (bell) bell.innerHTML = icon('bell') + (state.unread ? '<span class="dot"></span>' : '');
        if (d.unread) vibrate(12);
      }
    }).catch(handleAuthError);
  }
  D.addEventListener('visibilitychange', function () { if (!D.hidden) { poll(); if (!state.student && state.request) api('request').then(function (d) { if (JSON.stringify(d.request) !== JSON.stringify(state.request)) { state.request = d.request; if (!stack.length) renderGate(); } }).catch(function () {}); } });
  /* iPhone вернул страницу из памяти — сразу свежие данные */
  W.addEventListener('pageshow', function (e) { if (e.persisted) location.reload(); });

  /* ================================================================ PWA */
  W.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); state.installPrompt = e; });
  if ('serviceWorker' in navigator && C.swUrl && location.protocol === 'https:') {
    W.addEventListener('load', function () { navigator.serviceWorker.register(C.swUrl, { scope: new URL(C.swUrl, location.href).pathname }).catch(function () {}); });
  }

  /* ================================================================ запуск */
  themeApply();
  render();
  pkCheck().then(function (ok) {
    state.pkAvail = !!ok;
    if (!state.student && ok && LS.get('sh_pk')) { var b = $('[data-act="pk-login"]'); if (b) b.hidden = false; }
    if (state.student && route() === 'profile') render();
    if (state.student && ok && SS.get('sh_offer_pk')) { SS.del('sh_offer_pk'); setTimeout(pkOffer, 900); }
  });
  if (state.student) {
    pollTimer = setInterval(poll, 4000);
    api('messages?after=0' + (route() === 'chat' ? '' : '&peek=1')).then(function (d) {
      if (route() === 'chat' && state.loaded) return;
      state.serverOffset = d.now ? d.now - Math.floor(Date.now() / 1000) : 0;
      state.messages = d.messages || [];
      state.messages.forEach(function (m) { state.lastId = Math.max(state.lastId, m.id); });
      state.readUpTo = d.readUpTo || 0; state.adminOnline = !!d.adminOnline; state.loaded = true;
      if (route() === 'chat') { updateChatStatus(); renderFeed(); toBottom(false); }
    }).catch(handleAuthError);
  }
})();
