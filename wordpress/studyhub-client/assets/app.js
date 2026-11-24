/* StudyHub — student app (vanilla JS, no build step) */
(function () {
  'use strict';

  var C = window.STUDYHUB || {};
  var root = document.getElementById('app');
  var LS = safeStorage('localStorage');
  var SS = safeStorage('sessionStorage');

  /* ------------------------------------------------------------------ i18n */
  var I18N = {
    ru: {
      nav_home: 'Главная', nav_services: 'Услуги', nav_chat: 'Чат', nav_ai: 'ИИ', nav_profile: 'Профиль',
      login: 'Логин', password: 'Пароль', sign_in: 'Войти',
      login_hint: 'Регистрации нет — логин и пароль выдаёт администратор.',
      bad_credentials: 'Неверный логин или пароль', too_many: 'Слишком много попыток. Попробуйте через 15 минут.',
      network: 'Нет соединения. Проверьте интернет.', show_pass: 'Показать пароль',
      trust_anon: 'Полная анонимность', trust_noname: 'Без имён и фото', trust_private: 'Личный чат',
      choose_uni: 'Выберите университет', uni_sheet: 'Ваш университет', hero_cta: 'Заказать', anon_badge: 'Анонимно',
      ai_title: 'ИИ-помощник', ai_sub: 'Задай любой вопрос об учёбе, заданиях и архитектуре',
      chat_card: 'Чат', no_messages: 'Напишите нам — обсудим задание', you: 'Вы: ',
      promo_pill: 'Приватность', promo_title: '100% анонимно', promo_btn: 'Как это работает',
      reviews: 'Отзывы студентов', all: 'Все', no_reviews: 'Отзывов пока нет. Будьте первым!',
      student_of: 'Студент · {uni}', student: 'Студент',
      services_title: 'Услуги', services_sub: 'Выберите, что нужно сделать — детали обсудим в личном чате.',
      order: 'Заказать', price_chat: 'Цена — в чате',
      how_title: 'Как это работает',
      step1_t: 'Напишите в чат', step1_s: 'Опишите задание и прикрепите файлы',
      step2_t: 'Обсудим цену и сроки', step2_s: 'Отвечаем лично, без посредников',
      step3_t: 'Получите работу', step3_s: 'Готовые файлы придут прямо в чат',
      order_text: 'Здравствуйте! Хочу заказать: {svc}.\nПредмет: \nСрок: \n',
      chat_on: 'В сети', chat_off: 'Не в сети — ответим позже', chat_anon: 'Анонимно',
      today: 'Сегодня', yesterday: 'Вчера',
      placeholder: 'Сообщение…', attach: 'Прикрепить файл', send: 'Отправить', remove: 'Убрать',
      file_too_large: 'Файл слишком большой (макс. {mb} МБ)', file_type: 'Этот тип файла не поддерживается',
      send_failed: 'Не удалось отправить. Попробуйте ещё раз.',
      ai_suggest_t: 'Пока мы не ответили', ai_suggest_s: 'ИИ-помощник может ответить прямо сейчас на общие вопросы об учёбе и заданиях.',
      ai_suggest_btn: 'Спросить ИИ', ai_suggest_wait: 'Подожду', offline_note: 'Мы сейчас не в сети, но обязательно ответим.',
      review_t: 'Оцените нашу работу', review_s: 'Отзыв анонимный: имя, фото и контакты не публикуются.',
      review_ph: 'Что понравилось?', review_send: 'Отправить анонимно', review_thanks: 'Спасибо за отзыв!',
      review_pending: 'Опубликуем после проверки.', review_short: 'Напишите хотя бы пару слов', review_rate: 'Поставьте оценку',
      ai_hello_t: 'Чем помочь?', ai_hello_s: 'Спросите про задания, архитектуру, программы или учёбу. Для заказа — напишите в чат.',
      ai_ph: 'Спросить ИИ…', ai_disclaimer: 'ИИ может ошибаться. Важное уточняйте в чате.',
      ai_error: 'ИИ сейчас недоступен. Напишите в чат.', ai_limit: 'Лимит вопросов на сегодня исчерпан.',
      ai_to_chat: 'В чат', ai_clear: 'Очистить',
      ai_chips: ['Как оформить чертёж по ГОСТ?', 'Идеи для концепции макета', 'Как построить перспективу?', 'Какие программы нужны архитектору?'],
      profile_title: 'Профиль', anon_id: 'Анонимный ID', university: 'Университет', not_selected: 'Не выбран',
      language: 'Язык', install: 'Установить приложение', install_s: 'Иконка на главном экране телефона',
      logout: 'Выйти', logout_confirm: 'Выйти из аккаунта?', privacy: 'Приватность',
      priv1: 'Ваше имя нигде не хранится и не показывается', priv2: 'Фото в чате очищаются от геоданных (EXIF)',
      priv3: 'Отзывы публикуются без имени и фото',
      install_ios: ['Нажмите «Поделиться» внизу Safari', 'Выберите «На экран „Домой“»', 'Нажмите «Добавить»'],
      install_other: ['Откройте меню браузера (⋮)', 'Выберите «Установить приложение» или «Добавить на главный экран»'],
      installed: 'Приложение установлено', site: 'Открыть сайт', saved: 'Сохранено', close: 'Закрыть', download: 'Скачать', notifications: 'Сообщения'
    },
    kk: {
      nav_home: 'Басты', nav_services: 'Қызметтер', nav_chat: 'Чат', nav_ai: 'ЖИ', nav_profile: 'Профиль',
      login: 'Логин', password: 'Құпиясөз', sign_in: 'Кіру',
      login_hint: 'Тіркелу жоқ — логин мен құпиясөзді әкімші береді.',
      bad_credentials: 'Логин немесе құпиясөз қате', too_many: 'Тым көп әрекет. 15 минуттан кейін қайталаңыз.',
      network: 'Байланыс жоқ. Интернетті тексеріңіз.', show_pass: 'Құпиясөзді көрсету',
      trust_anon: 'Толық анонимдік', trust_noname: 'Атсыз және фотосыз', trust_private: 'Жеке чат',
      choose_uni: 'Университетті таңдаңыз', uni_sheet: 'Сіздің университетіңіз', hero_cta: 'Тапсырыс беру', anon_badge: 'Анонимді',
      ai_title: 'ЖИ-көмекші', ai_sub: 'Оқу, тапсырмалар және сәулет туралы кез келген сұрақ қой',
      chat_card: 'Чат', no_messages: 'Бізге жазыңыз — тапсырманы талқылаймыз', you: 'Сіз: ',
      promo_pill: 'Құпиялылық', promo_title: '100% анонимді', promo_btn: 'Қалай жұмыс істейді',
      reviews: 'Студенттер пікірлері', all: 'Барлығы', no_reviews: 'Әзірге пікір жоқ. Бірінші болыңыз!',
      student_of: '{uni} студенті', student: 'Студент',
      services_title: 'Қызметтер', services_sub: 'Не істеу керегін таңдаңыз — егжей-тегжейін жеке чатта талқылаймыз.',
      order: 'Тапсырыс беру', price_chat: 'Бағасы — чатта',
      how_title: 'Бұл қалай жұмыс істейді',
      step1_t: 'Чатқа жазыңыз', step1_s: 'Тапсырманы сипаттап, файлдарды тіркеңіз',
      step2_t: 'Баға мен мерзімді келісеміз', step2_s: 'Делдалсыз, жеке жауап береміз',
      step3_t: 'Дайын жұмысты алыңыз', step3_s: 'Файлдар тікелей чатқа келеді',
      order_text: 'Сәлеметсіз бе! Тапсырыс бергім келеді: {svc}.\nПән: \nМерзімі: \n',
      chat_on: 'Желіде', chat_off: 'Желіде емес — кейінірек жауап береміз', chat_anon: 'Анонимді',
      today: 'Бүгін', yesterday: 'Кеше',
      placeholder: 'Хабарлама…', attach: 'Файл тіркеу', send: 'Жіберу', remove: 'Алып тастау',
      file_too_large: 'Файл тым үлкен (макс. {mb} МБ)', file_type: 'Бұл файл түріне қолдау жоқ',
      send_failed: 'Жіберу мүмкін болмады. Қайталап көріңіз.',
      ai_suggest_t: 'Біз жауап бергенше', ai_suggest_s: 'ЖИ-көмекші оқу мен тапсырмалар туралы жалпы сұрақтарға қазір жауап бере алады.',
      ai_suggest_btn: 'ЖИ-ден сұрау', ai_suggest_wait: 'Күтемін', offline_note: 'Қазір желіде емеспіз, бірақ міндетті түрде жауап береміз.',
      review_t: 'Жұмысымызды бағалаңыз', review_s: 'Пікір анонимді: аты, фотосы және байланыстары жарияланбайды.',
      review_ph: 'Не ұнады?', review_send: 'Анонимді жіберу', review_thanks: 'Пікіріңізге рахмет!',
      review_pending: 'Тексерістен кейін жарияланады.', review_short: 'Кем дегенде бірнеше сөз жазыңыз', review_rate: 'Баға қойыңыз',
      ai_hello_t: 'Қалай көмектесейін?', ai_hello_s: 'Тапсырмалар, сәулет, бағдарламалар немесе оқу туралы сұраңыз. Тапсырыс үшін — чатқа жазыңыз.',
      ai_ph: 'ЖИ-ден сұрау…', ai_disclaimer: 'ЖИ қателесуі мүмкін. Маңызды сұрақтарды чатта нақтылаңыз.',
      ai_error: 'ЖИ қазір қолжетімсіз. Чатқа жазыңыз.', ai_limit: 'Бүгінгі сұрақтар лимиті таусылды.',
      ai_to_chat: 'Чатқа', ai_clear: 'Тазалау',
      ai_chips: ['Сызбаны ГОСТ бойынша қалай рәсімдеймін?', 'Макет концепциясына идеялар', 'Перспективаны қалай саламын?', 'Сәулетшіге қандай бағдарламалар керек?'],
      profile_title: 'Профиль', anon_id: 'Анонимді ID', university: 'Университет', not_selected: 'Таңдалмаған',
      language: 'Тіл', install: 'Қосымшаны орнату', install_s: 'Телефонның басты экранындағы белгіше',
      logout: 'Шығу', logout_confirm: 'Аккаунттан шығасыз ба?', privacy: 'Құпиялылық',
      priv1: 'Атыңыз ешқайда сақталмайды және көрсетілмейді', priv2: 'Чаттағы фотолар геодеректерден (EXIF) тазартылады',
      priv3: 'Пікірлер атсыз және фотосыз жарияланады',
      install_ios: ['Safari төменгі жағындағы «Бөлісу» батырмасын басыңыз', '«Басты экранға қосу» тармағын таңдаңыз', '«Қосу» батырмасын басыңыз'],
      install_other: ['Браузер мәзірін ашыңыз (⋮)', '«Қосымшаны орнату» немесе «Басты экранға қосу» таңдаңыз'],
      installed: 'Қосымша орнатылды', site: 'Сайтты ашу', saved: 'Сақталды', close: 'Жабу', download: 'Жүктеп алу', notifications: 'Хабарламалар'
    }
  };

  /* ----------------------------------------------------------------- state */
  var state = {
    lang: (C.student && C.student.lang) || LS.get('studyhub_lang') || ((document.cookie.match(/(?:^|; )studyhub_lang=(kk|ru)/) || [])[1]) || 'ru',
    student: C.student,
    unread: C.unread || 0,
    adminOnline: !!C.adminOnline,
    reviews: C.reviews || [],
    stats: C.reviewStats || { count: 0, average: 0 },
    messages: [],
    loaded: false,
    lastId: 0,
    readUpTo: 0,
    serverOffset: 0,
    draftService: null,
    aiDismissedFor: 0,
    ai: { messages: [], busy: false },
    installPrompt: null
  };
  try { state.ai.messages = JSON.parse(SS.get('studyhub_ai') || '[]'); } catch (e) { state.ai.messages = []; }
  if (state.lang !== 'kk') state.lang = 'ru';

  /* --------------------------------------------------------------- helpers */
  function safeStorage(kind) {
    var s = null;
    try { s = window[kind]; s.setItem('__t', '1'); s.removeItem('__t'); } catch (e) { s = null; }
    return {
      get: function (k) { try { return s ? s.getItem(k) : null; } catch (e) { return null; } },
      set: function (k, v) { try { if (s) s.setItem(k, v); } catch (e) { /* ignore */ } },
      del: function (k) { try { if (s) s.removeItem(k); } catch (e) { /* ignore */ } }
    };
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function t(key, vars) {
    var v = (I18N[state.lang] && I18N[state.lang][key]);
    if (v == null) v = I18N.ru[key];
    if (v == null) return key;
    if (vars && typeof v === 'string') {
      Object.keys(vars).forEach(function (k) { v = v.replace('{' + k + '}', vars[k]); });
    }
    return v;
  }
  function loc(obj) {
    if (!obj) return '';
    if (typeof obj === 'string') return obj;
    return obj[state.lang] || obj.ru || '';
  }
  function $(sel, ctx) { return (ctx || root).querySelector(sel); }
  function nowServer() { return Math.floor(Date.now() / 1000) + state.serverOffset; }
  function fmtTime(ts) {
    var d = new Date(ts * 1000);
    return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }
  function fmtDay(ts) {
    var d = new Date(ts * 1000), n = new Date();
    var y = new Date(); y.setDate(n.getDate() - 1);
    if (d.toDateString() === n.toDateString()) return t('today');
    if (d.toDateString() === y.toDateString()) return t('yesterday');
    try {
      return d.toLocaleDateString(state.lang === 'kk' ? 'kk-KZ' : 'ru-RU', { day: 'numeric', month: 'long' });
    } catch (e) { return d.toLocaleDateString(); }
  }
  function fmtSize(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return Math.round(b / 1024) + ' KB';
    return (b / 1048576).toFixed(1) + ' MB';
  }
  function serviceById(id) {
    var list = C.services || [];
    for (var i = 0; i < list.length; i++) if (list[i].id === id) return list[i];
    return null;
  }
  function uniById(id) {
    var list = C.universities || [];
    for (var i = 0; i < list.length; i++) if (list[i].id === id) return list[i];
    return null;
  }
  function withQuery(url, q) { return url + (url.indexOf('?') > -1 ? '&' : '?') + q; }
  function vibrate() { try { if (navigator.vibrate) navigator.vibrate(8); } catch (e) { /* ignore */ } }

  /* ----------------------------------------------------------------- icons */
  var ICONS = {
    home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
    grad: '<path d="M22 9 12 4 2 9l10 5 10-5Z"/><path d="M6 11v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/><path d="M22 9v6"/>',
    grid: '<rect x="3" y="3" width="7.5" height="7.5" rx="2.2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2.2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2.2"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2.2"/>',
    chat: '<path d="M21 12a8.5 8.5 0 0 1-12.3 7.6L3 21l1.4-5.1A8.5 8.5 0 1 1 21 12Z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/>',
    sparkle: '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9Z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8Z"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
    bell: '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    chevDown: '<path d="m6 9 6 6 6-6"/>',
    chevRight: '<path d="m9 6 6 6-6 6"/>',
    arrowRight: '<path d="M5 12h14M13 6l6 6-6 6"/>',
    arrowUp: '<path d="M12 19V5M5 12l7-7 7 7"/>',
    book: '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2Z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7Z"/>',
    cube: '<path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.7Z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/>',
    pencil: '<path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
    ruler: '<path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.41 2.41 0 0 1 0-3.4l2.6-2.6a2.41 2.41 0 0 1 3.4 0Z"/><path d="m14.5 12.5 2-2M11.5 9.5l2-2M8.5 6.5l2-2M17.5 15.5l2-2"/>',
    building: '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/>',
    laptop: '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M2 20h20"/>',
    palette: '<circle cx="13.5" cy="6.5" r=".6"/><circle cx="17.5" cy="10.5" r=".6"/><circle cx="8.5" cy="7.5" r=".6"/><circle cx="6.5" cy="12.5" r=".6"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.9 0 1.6-.7 1.6-1.7 0-.4-.2-.8-.4-1.1-.3-.3-.4-.7-.4-1.1a1.6 1.6 0 0 1 1.6-1.6h2c3 0 5.6-2.5 5.6-5.6C22 6 17.5 2 12 2Z"/>',
    doc: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
    clip: '<path d="m21.4 11.1-9.2 9.2a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5"/>',
    lock: '<rect x="4" y="11" width="16" height="10" rx="2.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    star: '<path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4l-5.9 3.1 1.2-6.5L2.5 9.4l6.6-.9Z"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    checks: '<path d="M17 7 6.5 17.5 2 13"/><path d="m22 8-9.5 9.5-1.5-1.5"/>',
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
    trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>'
  };
  function icon(name, cls) {
    return '<svg class="icon ' + (cls || '') + '" viewBox="0 0 24 24" aria-hidden="true">' + (ICONS[name] || ICONS.doc) + '</svg>';
  }
  var starSeq = 0;
  function aiStar(cls) {
    var id = 'sg' + (++starSeq);
    return '<svg class="ai-star ' + (cls || '') + '" viewBox="0 0 100 100" aria-hidden="true"><defs>' +
      '<radialGradient id="' + id + '" cx="36%" cy="30%" r="80%"><stop offset="0" stop-color="#FFE1C4"/><stop offset=".42" stop-color="#FF9B52"/><stop offset="1" stop-color="#E2541C"/></radialGradient></defs>' +
      '<path d="M50 3C54 31 69 46 97 50 69 54 54 69 50 97 46 69 31 54 3 50 31 46 46 31 50 3Z" fill="url(#' + id + ')"/>' +
      '<path d="M50 15c2.6 15 8.5 23.5 21 29" stroke="rgba(255,255,255,.6)" stroke-width="3.5" fill="none" stroke-linecap="round"/></svg>';
  }
  function brandMark() {
    if (C.logo) return '<img src="' + esc(C.logo) + '" alt="">';
    return '<svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M16 7 7.5 27" style="stroke:var(--text)" stroke-width="3.4"/><path d="M16 7 24.5 27" style="stroke:var(--accent)" stroke-width="3.4"/>' +
      '<path d="M10.5 20.5c3.6 1.6 7.4 1.6 11 0" style="stroke:var(--text)" stroke-width="2.4"/><circle cx="16" cy="5.5" r="2.6" style="fill:var(--text)" stroke="none"/></svg>';
  }
  function anonAvatar(seed, cls) {
    var tones = ['peach', 'lilac', 'mint', 'sky', 'sand', 'rose'];
    var n = 0; seed = String(seed || '');
    for (var i = 0; i < seed.length; i++) n = (n * 31 + seed.charCodeAt(i)) >>> 0;
    return '<div class="' + (cls || 'anon-ava') + ' tone-' + tones[n % tones.length] + ' tone-bg">' + icon('mask') + '</div>';
  }
  function stars(n) {
    var out = '<span class="stars" aria-label="' + n + '/5">';
    for (var i = 1; i <= 5; i++) out += '<span class="' + (i <= n ? '' : 'off') + '">' + icon('star') + '</span>';
    return out + '</span>';
  }
  function heroArt() {
    if (C.hero && C.hero.image) return '<img src="' + esc(C.hero.image) + '" alt="">';
    var w = '';
    var r, c;
    for (r = 0; r < 8; r++) for (c = 0; c < 3; c++) w += '<rect x="' + (162 + c * 18) + '" y="' + (50 + r * 22) + '" width="11" height="14" rx="1.5" fill="url(#hg)" opacity=".85"/>';
    for (r = 0; r < 4; r++) {
      for (c = 0; c < 4; c++) w += '<rect x="' + (78 + c * 27) + '" y="' + (112 + r * 29) + '" width="21" height="21" rx="2" fill="url(#hg)"/>';
      w += '<rect x="70" y="' + (133 + r * 29) + '" width="120" height="3" fill="#D8D2CB"/>';
      w += '<polygon points="190,' + (106 + r * 29) + ' 230,' + (117 + r * 29) + ' 230,' + (120 + r * 29) + ' 190,' + (109 + r * 29) + '" fill="#B9B2AA"/>';
    }
    return '<svg viewBox="0 0 260 230" preserveAspectRatio="xMaxYMax meet" aria-hidden="true"><defs>' +
      '<linearGradient id="hg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#C9DAEA"/><stop offset=".6" stop-color="#8FA7C0"/><stop offset="1" stop-color="#6F87A2"/></linearGradient></defs>' +
      '<circle cx="118" cy="52" r="24" fill="#FFD7B5" opacity=".75"/>' +
      '<rect x="150" y="38" width="66" height="192" fill="#DCD7D1"/><polygon points="216,38 252,50 252,230 216,230" fill="#C3BCB4"/>' + w.split('<rect x="78"')[0] +
      '<rect x="70" y="98" width="120" height="132" fill="#F1EDE8"/><polygon points="190,98 230,110 230,230 190,230" fill="#CFC8C0"/>' +
      '<rect x="66" y="94" width="128" height="6" rx="1" fill="#E4DED8"/>' + w.substring(w.split('<rect x="78"')[0].length) +
      '<ellipse cx="40" cy="214" rx="26" ry="22" fill="#9DB88F"/><ellipse cx="22" cy="222" rx="18" ry="15" fill="#86A57A"/><ellipse cx="246" cy="222" rx="20" ry="14" fill="#93B087"/>' +
      '<rect x="0" y="226" width="260" height="4" fill="#CFC8C0"/></svg>';
  }

  /* ------------------------------------------------------------------- api */
  function api(path, opts) {
    opts = opts || {};
    var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: { 'X-StudyHub': '1' } };
    if (opts.body instanceof FormData) {
      init.body = opts.body;
    } else if (opts.body) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(opts.body);
    }
    var url = /^https?:/.test(path) ? path : C.rest + (C.rest.indexOf('?') > -1 ? path.replace('?', '&') : path);
    return fetch(url, init).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok) {
          var err = new Error((data && data.message) || 'error');
          err.status = res.status; err.code = data && data.code; err.data = data;
          throw err;
        }
        return data;
      });
    });
  }

  /* --------------------------------------------------------------- toasts */
  var toastTimer;
  function toast(msg, isError) {
    var old = document.querySelector('.toast');
    if (old) old.remove();
    var el = document.createElement('div');
    el.className = 'toast' + (isError ? ' error' : '');
    el.setAttribute('role', 'status');
    el.textContent = msg;
    document.body.appendChild(el);
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.remove(); }, 3200);
  }

  function sheet(html, onMount) {
    var ov = document.createElement('div');
    ov.className = 'overlay';
    ov.innerHTML = '<div class="sheet" role="dialog" aria-modal="true"><div class="grabber"></div>' + html + '</div>';
    function close() { ov.remove(); document.removeEventListener('keydown', onKey); }
    function onKey(e) { if (e.key === 'Escape') close(); }
    ov.addEventListener('click', function (e) { if (e.target === ov || e.target.closest('[data-close]')) close(); });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(ov);
    if (onMount) onMount(ov.querySelector('.sheet'), close);
    return close;
  }

  function viewer(src) {
    var v = document.createElement('div');
    v.className = 'viewer';
    v.innerHTML = '<img src="' + esc(src) + '" alt=""><a class="icon-btn dl" href="' + esc(withQuery(src, 'download=1')) + '" aria-label="' + esc(t('download')) + '">' + icon('download') + '</a><button class="icon-btn close" aria-label="' + esc(t('close')) + '">' + icon('x') + '</button>';
    v.addEventListener('click', function (e) { if (!e.target.closest('.dl')) v.remove(); });
    document.body.appendChild(v);
  }

  /* --------------------------------------------------------------- router */
  function route() {
    var r = (location.hash || '#home').slice(1).split('?')[0];
    var allowed = ['home', 'services', 'chat', 'profile'];
    if (C.ai && C.ai.enabled) allowed.push('ai');
    return allowed.indexOf(r) > -1 ? r : 'home';
  }
  function go(r) {
    if (location.hash === '#' + r) render(); else location.hash = r;
  }
  window.addEventListener('hashchange', function () { render(); window.scrollTo(0, 0); });

  function render() {
    document.documentElement.lang = state.lang;
    if (!state.student) { renderLogin(); return; }
    var r = route();
    var html = '';
    if (r === 'home') html = viewHome();
    else if (r === 'services') html = viewServices();
    else if (r === 'chat') html = viewChat();
    else if (r === 'ai') html = viewAI();
    else if (r === 'profile') html = viewProfile();
    root.innerHTML = html + tabbar(r);
    if (r === 'chat') mountChat();
    if (r === 'ai') mountAI();
  }

  function tabbar(active) {
    var tabs = [['home', 'home', t('nav_home')], ['services', 'grid', t('nav_services')], ['chat', 'chat', t('nav_chat')]];
    if (C.ai && C.ai.enabled) tabs.push(['ai', 'sparkle', t('nav_ai')]);
    tabs.push(['profile', 'user', t('nav_profile')]);
    return '<nav class="tabbar glass" aria-label="Menu">' + tabs.map(function (tb) {
      var badge = tb[0] === 'chat' && state.unread && active !== 'chat' ? '<span class="badge">' + state.unread + '</span>' : '';
      return '<a class="tab" href="#' + tb[0] + '"' + (active === tb[0] ? ' aria-current="page"' : '') + '>' + icon(tb[1]) + '<span>' + esc(tb[2]) + '</span>' + badge + '</a>';
    }).join('') + '</nav>';
  }

  function langSwitch() {
    return '<div class="lang glass" role="group" aria-label="Language">' +
      '<button data-lang="kk" aria-pressed="' + (state.lang === 'kk') + '">Қаз</button>' +
      '<button data-lang="ru" aria-pressed="' + (state.lang === 'ru') + '">Рус</button></div>';
  }
  function setLang(l) {
    if (l !== 'ru' && l !== 'kk') return;
    state.lang = l;
    LS.set('studyhub_lang', l);
    try { document.cookie = 'studyhub_lang=' + l + ';path=/;max-age=31536000;samesite=lax'; } catch (e) { /* ignore */ }
    if (state.student) {
      state.student.lang = l;
      api('me', { method: 'POST', body: { lang: l } }).catch(function () {});
    }
    render();
  }

  /* ---------------------------------------------------------------- login */
  function renderLogin() {
    root.innerHTML =
      '<div class="login screen">' +
        '<div class="login-top">' + langSwitch() + '</div>' +
        '<div class="login-hero"><div class="brand-tile">' + brandMark() + '</div>' +
          '<h1>' + esc(C.brand) + '</h1><p>' + esc(loc(C.tagline)) + '</p></div>' +
        '<form class="login-card glass" id="login-form" novalidate>' +
          '<div class="field"><label for="l-login">' + esc(t('login')) + '</label>' +
            '<input class="input" id="l-login" name="login" autocomplete="username" autocapitalize="off" autocorrect="off" spellcheck="false" required></div>' +
          '<div class="field"><label for="l-pass">' + esc(t('password')) + '</label>' +
            '<input class="input" id="l-pass" name="password" type="password" autocomplete="current-password" required>' +
            '<button type="button" class="eye" aria-label="' + esc(t('show_pass')) + '">' + icon('eye', 'sm') + '</button></div>' +
          '<p class="form-error" id="l-err" role="alert"></p>' +
          '<button class="btn btn-primary btn-block" type="submit">' + esc(t('sign_in')) + '</button>' +
        '</form>' +
        '<div class="login-foot"><p class="tiny" style="text-align:center;margin:0">' + esc(t('login_hint')) + '</p>' +
          '<div class="trust"><span>' + icon('shield') + esc(t('trust_anon')) + '</span><span>' + icon('mask') + esc(t('trust_noname')) + '</span><span>' + icon('lock') + esc(t('trust_private')) + '</span></div>' +
        '</div>' +
      '</div>';

    var form = $('#login-form');
    $('.eye').addEventListener('click', function () {
      var p = $('#l-pass');
      p.type = p.type === 'password' ? 'text' : 'password';
      this.innerHTML = icon(p.type === 'password' ? 'eye' : 'eyeOff', 'sm');
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = form.querySelector('button[type=submit]');
      var err = $('#l-err');
      err.textContent = '';
      btn.disabled = true;
      api('login', { method: 'POST', body: { login: form.login.value.trim(), password: form.password.value } })
        .then(function (data) {
          state.student = data.student;
          state.unread = data.unread || 0;
          state.adminOnline = !!data.adminOnline;
          if (data.student.lang && data.student.lang !== state.lang) {
            api('me', { method: 'POST', body: { lang: state.lang } }).catch(function () {});
            state.student.lang = state.lang;
          }
          location.hash = 'home';
          render();
          startPolling();
        })
        .catch(function (ex) {
          btn.disabled = false;
          err.textContent = ex.status === 429 ? t('too_many') : (ex.status === 401 ? t('bad_credentials') : t('network'));
          form.password.select();
        });
    });
  }

  /* ----------------------------------------------------------------- home */
  function header() {
    return '<header class="topbar">' +
      '<div class="brand-tile">' + brandMark() + '</div>' +
      '<div class="grow"><p class="brand-name">' + esc(C.brand) + '</p><div class="brand-sub">' + esc(loc(C.tagline)) + '</div></div>' +
      langSwitch() +
      '<a href="#chat" class="icon-btn glass" aria-label="' + esc(t('notifications')) + '">' + icon('bell') + (state.unread ? '<span class="dot"></span>' : '') + '</a>' +
      '</header>';
  }

  function uniRow() {
    var cur = uniById(state.student.university);
    var chips = (C.universities || []).slice(0, 3).map(function (u) {
      return '<button class="uni-chip" data-uni="' + esc(u.id) + '" aria-pressed="' + (cur && cur.id === u.id) + '" title="' + esc(loc(u.name)) + '">' +
        (u.logo ? '<img src="' + esc(u.logo) + '" alt="' + esc(u.short) + '">' : esc(u.short)) + '</button>';
    }).join('');
    return '<div class="uni-row">' +
      '<button class="uni-select glass" data-action="uni-sheet">' + icon('grad') + '<span>' + esc(cur ? loc(cur.name) : t('choose_uni')) + '</span>' + icon('chevDown', 'sm') + '</button>' +
      (chips ? '<div class="uni-chips glass">' + chips + '</div>' : '') +
      '</div>';
  }

  function lastMessagePreview() {
    var m = state.messages.length ? state.messages[state.messages.length - 1] : null;
    if (!m) return { text: state.lastPreview || t('no_messages'), time: state.lastPreviewTime || 0 };
    var txt = m.text || (m.file ? '📎 ' + m.file.name : (m.type === 'review_request' ? '⭐ ' + t('review_t') : ''));
    return { text: (m.from === 'me' ? t('you') : '') + txt, time: m.time };
  }

  function viewHome() {
    var svcs = (C.services || []).slice(0, 4);
    var quick = svcs.map(function (s) {
      return '<button class="quick-item glass" data-order="' + esc(s.id) + '"><span class="quick-ico tone-' + esc(s.color || 'peach') + ' tone-bg">' + icon(s.icon) + '</span><span>' + esc(loc(s.title)) + '</span></button>';
    }).join('');
    var prev = lastMessagePreview();
    var ai = C.ai && C.ai.enabled
      ? '<a class="ai-banner glass" href="#ai">' + aiStar() + '<div class="grow"><h3>' + esc(t('ai_title')) + '</h3><p>' + esc(t('ai_sub')) + '</p></div><span class="round-arrow">' + icon('arrowRight', 'sm') + '</span></a>'
      : '';

    var reviews = state.reviews.length
      ? '<div class="h-scroll">' + state.reviews.map(reviewCard).join('') + '</div>'
      : '<div class="empty glass">' + icon('star') + esc(t('no_reviews')) + '</div>';

    return '<main class="screen">' + header() + uniRow() +
      '<section class="hero">' +
        '<div class="hero-art">' + heroArt() + '</div>' +
        '<span class="anon-badge">' + icon('lock', 'xs') + esc(t('anon_badge')) + '</span>' +
        '<div class="hero-body"><h1>' + esc(loc(C.hero && C.hero.title)) + '</h1><p>' + esc(loc(C.hero && C.hero.text)) + '</p>' +
        '<a class="btn btn-dark" href="#services">' + esc(t('hero_cta')) + icon('arrowRight', 'sm') + '</a></div>' +
      '</section>' +
      (quick ? '<nav class="quick">' + quick + '</nav>' : '') +
      ai +
      '<div class="duo">' +
        '<a class="mini-chat glass" href="#chat"><h3>' + esc(t('chat_card')) + (state.unread ? '<span class="badge">' + state.unread + '</span>' : icon('arrowRight', 'sm')) + '</h3>' +
          '<div class="mini-row"><div class="avatar">' + (C.logo ? '<img src="' + esc(C.logo) + '" alt="">' : esc(C.brand.charAt(0))) + '</div>' +
          '<div style="min-width:0"><div class="t">' + esc(C.brand) + '</div><div class="tiny"><span class="status-dot' + (state.adminOnline ? ' on' : '') + '"></span>' + esc(state.adminOnline ? t('chat_on') : t('chat_off').split(' — ')[0]) + '</div></div></div>' +
          '<div class="mini-row"><div class="s">' + esc(prev.text) + '</div></div>' +
        '</a>' +
        '<div class="promo"><span class="pill">' + icon('shield', 'xs') + esc(t('promo_pill')) + '</span>' +
          '<h3>' + esc(t('promo_title')) + '</h3>' +
          '<svg class="lock-art" viewBox="0 0 100 100" aria-hidden="true"><rect x="18" y="44" width="64" height="50" rx="14" fill="rgba(255,255,255,.65)"/><path d="M32 46V32a18 18 0 0 1 36 0v14" fill="none" stroke="rgba(255,255,255,.75)" stroke-width="9" stroke-linecap="round"/><circle cx="50" cy="66" r="7" style="fill:var(--accent)"/><rect x="47" y="68" width="6" height="12" rx="3" style="fill:var(--accent)"/></svg>' +
          '<a class="btn" href="#services" data-scroll="how">' + esc(t('promo_btn')) + icon('arrowRight', 'xs') + '</a>' +
        '</div>' +
      '</div>' +
      '<section class="section"><div class="section-head"><h2>' + esc(t('reviews')) + '</h2>' +
        (state.stats.count ? '<span class="rating-sum">' + stars(Math.round(state.stats.average)) + '<b>' + esc(state.stats.average) + '</b> · ' + state.stats.count + '</span>' : '') +
      '</div>' + reviews + '</section>' +
      '</main>';
  }

  function reviewCard(r) {
    var who = r.university ? t('student_of', { uni: r.university }) : t('student');
    var svc = r.service && serviceById(r.service);
    return '<article class="review glass"><div class="review-head">' + anonAvatar(r.id) +
      '<div><div class="review-who">' + esc(who) + '</div>' + stars(r.rating) + '</div></div>' +
      '<p>“' + esc(r.text) + '”</p>' +
      (svc ? '<span class="tiny">' + esc(loc(svc.title)) + '</span>' : '') + '</article>';
  }

  /* ------------------------------------------------------------- services */
  function viewServices() {
    var list = (C.services || []).map(function (s) {
      return '<article class="svc glass">' +
        '<div class="svc-ico tone-' + esc(s.color || 'peach') + ' tone-bg">' + icon(s.icon, 'lg') + '</div>' +
        '<div><h3>' + esc(loc(s.title)) + '</h3><p>' + esc(loc(s.desc)) + '</p></div>' +
        '<div class="svc-foot">' + (s.price ? '<span class="price">' + esc(s.price) + '</span>' : '<span class="price na">' + esc(t('price_chat')) + '</span>') +
        '<button class="btn btn-primary btn-sm" data-order="' + esc(s.id) + '">' + esc(t('order')) + icon('arrowRight', 'xs') + '</button></div>' +
        '</article>';
    }).join('');
    return '<main class="screen"><h1 class="page-title">' + esc(t('services_title')) + '</h1><p class="page-sub">' + esc(t('services_sub')) + '</p>' +
      '<div class="svc-list">' + list + '</div>' +
      '<section class="section" id="how"><div class="section-head"><h2>' + esc(t('how_title')) + '</h2></div>' +
      '<div class="steps glass">' +
        [1, 2, 3].map(function (n) { return '<div class="step"><span class="step-n">' + n + '</span><div><b>' + esc(t('step' + n + '_t')) + '</b><span>' + esc(t('step' + n + '_s')) + '</span></div></div>'; }).join('') +
      '</div>' +
      '<div class="privacy glass">' + privacyRows() + '</div></section></main>';
  }
  function privacyRows() {
    return ['priv1', 'priv2', 'priv3'].map(function (k) { return '<div class="privacy-row">' + icon('check', 'sm') + '<span>' + esc(t(k)) + '</span></div>'; }).join('');
  }

  function startOrder(id) {
    var s = serviceById(id);
    if (!s) return;
    state.draftService = s.id;
    state.draftText = t('order_text', { svc: loc(s.title) });
    vibrate();
    go('chat');
  }

  /* ----------------------------------------------------------------- chat */
  function viewChat() {
    return '<main class="chat-screen">' +
      '<header class="chat-head glass">' +
        '<div class="avatar">' + (C.logo ? '<img src="' + esc(C.logo) + '" alt="">' : esc(C.brand.charAt(0))) + '</div>' +
        '<div class="grow"><div class="t">' + esc(C.brand) + '</div><div class="s" id="chat-status"></div></div>' +
        '<span class="anon-note">' + icon('lock', 'xs') + esc(t('chat_anon')) + '</span>' +
      '</header>' +
      '<div class="chat-scroll" id="chat-scroll" aria-live="polite">' + (state.loaded ? '' : skeletonMessages()) + '</div>' +
      '<div class="composer-wrap glass">' +
        '<div id="composer-chip"></div>' +
        '<form class="composer" id="composer">' +
          '<input type="file" id="file-input" class="sr" accept="' + esc(C.accept || '') + '">' +
          '<button type="button" class="icon-btn attach-btn" id="attach" aria-label="' + esc(t('attach')) + '">' + icon('clip', 'sm') + '</button>' +
          '<label class="sr" for="msg-input">' + esc(t('placeholder')) + '</label>' +
          '<textarea id="msg-input" rows="1" placeholder="' + esc(t('placeholder')) + '"></textarea>' +
          '<button class="icon-btn send-btn" id="send" aria-label="' + esc(t('send')) + '" disabled>' + icon('arrowUp', 'sm') + '</button>' +
        '</form>' +
      '</div></main>';
  }
  function skeletonMessages() {
    return '<div class="skeleton" style="height:58px;width:70%;margin:6px 0"></div><div class="skeleton" style="height:42px;width:55%;margin:6px 0;align-self:flex-end"></div><div class="skeleton" style="height:70px;width:65%;margin:6px 0"></div>';
  }

  function updateChatStatus() {
    var el = document.getElementById('chat-status');
    if (el) el.innerHTML = '<span class="status-dot' + (state.adminOnline ? ' on' : '') + '"></span>' + esc(state.adminOnline ? t('chat_on') : t('chat_off'));
  }

  function mountChat() {
    updateChatStatus();
    var input = $('#msg-input'), send = $('#send'), form = $('#composer'), file = $('#file-input');
    if (state.draftText) { input.value = state.draftText; state.draftText = null; }
    renderChip();
    function sync() {
      input.style.height = 'auto';
      input.style.height = Math.min(140, input.scrollHeight) + 'px';
      send.disabled = !input.value.trim();
    }
    input.addEventListener('input', sync);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && !('ontouchstart' in window)) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit')); }
    });
    sync();
    $('#attach').addEventListener('click', function () { file.click(); });
    file.addEventListener('change', function () {
      var f = file.files[0];
      file.value = '';
      if (!f) return;
      if (f.size > (C.maxUploadMb || 20) * 1048576) { toast(t('file_too_large', { mb: C.maxUploadMb }), true); return; }
      sendMessage('', f);
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var txt = input.value.trim();
      if (!txt) return;
      input.value = '';
      sync();
      sendMessage(txt, null);
    });
    renderMessages(true);
    fetchMessages(true);
  }

  function renderChip() {
    var box = document.getElementById('composer-chip');
    if (!box) return;
    var s = state.draftService && serviceById(state.draftService);
    box.innerHTML = s ? '<div class="composer-chip"><span class="svc-tag tone-' + esc(s.color) + ' tone-bg">' + icon(s.icon, 'xs') + esc(loc(s.title)) + '</span>' +
      '<button type="button" class="icon-btn" style="width:30px;height:30px" data-action="clear-chip" aria-label="' + esc(t('remove')) + '">' + icon('x', 'xs') + '</button></div>' : '';
  }

  function sendMessage(text, file) {
    var tmpId = 'tmp' + Date.now();
    var optimistic = { id: tmpId, from: 'me', type: file ? 'file' : 'text', text: text, file: file ? { name: file.name, size: file.size, isImage: false } : null, time: nowServer(), pending: true };
    if (state.draftService) optimistic.meta = { service: state.draftService };
    state.messages.push(optimistic);
    renderMessages(true);
    var fd = new FormData();
    fd.append('text', text);
    if (file) fd.append('file', file);
    if (state.draftService) fd.append('service', state.draftService);
    state.draftService = null;
    renderChip();
    api('messages', { method: 'POST', body: fd }).then(function (data) {
      replaceMsg(tmpId, data.message);
      state.lastId = Math.max(state.lastId, data.message.id);
      renderMessages(true);
    }).catch(function (ex) {
      var m = findMsg(tmpId);
      if (m) { m.pending = false; m.failed = true; }
      renderMessages(false);
      toast(ex.status === 415 ? t('file_type') : ex.status === 413 ? t('file_too_large', { mb: C.maxUploadMb }) : t('send_failed'), true);
    });
  }
  function findMsg(id) { for (var i = 0; i < state.messages.length; i++) if (state.messages[i].id === id) return state.messages[i]; return null; }
  function replaceMsg(id, msg) {
    for (var i = 0; i < state.messages.length; i++) if (state.messages[i].id === id) { state.messages[i] = msg; return; }
    state.messages.push(msg);
  }

  function fetchMessages(initial) {
    return api('messages?after=' + (initial ? 0 : state.lastId)).then(function (data) {
      state.serverOffset = data.now ? data.now - Math.floor(Date.now() / 1000) : 0;
      state.adminOnline = !!data.adminOnline;
      state.readUpTo = data.readUpTo || 0;
      var added = false;
      if (initial) {
        state.messages = state.messages.filter(function (m) { return m.pending || m.failed; });
        state.messages = data.messages.concat(state.messages);
        added = true;
      } else {
        data.messages.forEach(function (m) {
          if (!findMsg(m.id)) { state.messages.push(m); added = true; }
        });
      }
      state.messages.forEach(function (m) { if (typeof m.id === 'number') state.lastId = Math.max(state.lastId, m.id); });
      state.loaded = true;
      state.unread = 0;
      if (route() === 'chat') {
        updateChatStatus();
        renderMessages(added);
      }
    }).catch(handleAuthError);
  }

  function renderMessages(scroll) {
    var box = document.getElementById('chat-scroll');
    if (!box) return;
    if (!state.loaded) return;
    var nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 120;
    var html = '';
    var lastDay = '';
    // Synthetic welcome from the brand (not stored).
    html += '<div class="day">' + esc(C.brand) + ' · ' + esc(t('chat_anon')) + '</div>';
    html += '<div class="msg them"><div class="bubble">' + esc(loc(C.welcome)) + '</div></div>';
    var prevFrom = null;
    state.messages.forEach(function (m) {
      var day = fmtDay(m.time);
      if (day !== lastDay) { html += '<div class="day">' + esc(day) + '</div>'; lastDay = day; prevFrom = null; }
      html += messageHTML(m, prevFrom === m.from);
      prevFrom = m.from;
    });
    html += fallbackCard();
    box.innerHTML = html;
    if (scroll || nearBottom) box.scrollTop = box.scrollHeight;
  }

  function messageHTML(m, grouped) {
    var mine = m.from === 'me';
    var cls = 'msg ' + (mine ? 'me' : 'them') + (grouped ? ' grouped' : '') + (m.pending ? ' pending' : '') + (m.failed ? ' failed' : '');
    if (m.type === 'review_request') return reviewRequestHTML(m);
    var inner = '';
    if (m.meta && m.meta.service) {
      var s = serviceById(m.meta.service);
      if (s) inner += '<span class="svc-tag tone-' + esc(s.color) + ' tone-bg">' + icon(s.icon, 'xs') + esc(loc(s.title)) + '</span>';
    }
    if (m.type === 'review') {
      inner += '<div class="bubble">' + stars(m.meta && m.meta.rating || 5) + '\n' + esc(m.text) + '</div>';
    } else {
      if (m.file) {
        if (m.file.isImage && m.file.url) {
          inner += '<div class="msg-img"><img src="' + esc(m.file.url) + '" alt="' + esc(m.file.name) + '" loading="lazy" data-view="' + esc(m.file.url) + '"></div>';
        } else {
          var ext = (m.file.name.split('.').pop() || '').slice(0, 4);
          inner += '<a class="msg-file" ' + (m.file.url ? 'href="' + esc(withQuery(m.file.url, 'download=1')) + '"' : '') + '><span class="file-ico">' + esc(ext) + '</span>' +
            '<span><span class="file-name">' + esc(m.file.name) + '</span><span class="file-size">' + fmtSize(m.file.size || 0) + '</span></span></a>';
        }
      }
      if (m.text) inner += '<div class="bubble">' + linkify(esc(m.text)) + '</div>';
    }
    var tick = '';
    if (mine && !m.pending && !m.failed) tick = typeof m.id === 'number' && m.id <= state.readUpTo ? '<span class="read">' + icon('checks') + '</span>' : icon('check');
    return '<div class="' + cls + '">' + inner + '<span class="meta">' + fmtTime(m.time) + tick + '</span></div>';
  }

  function linkify(html) {
    return html.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
  }

  function reviewRequestHTML(m) {
    var meta = m.meta || {};
    if (meta.done) {
      return '<div class="sys-card glass"><h4>' + icon('star', 'sm') + esc(t('review_thanks')) + '</h4><p style="margin:0">' + stars(meta.rating || 5) + '</p></div>';
    }
    return '<form class="sys-card glass" data-review="' + m.id + '">' +
      '<h4>' + aiStar('') .replace('class="ai-star ', 'style="width:26px;height:26px;animation:none" class="ai-star ') + esc(t('review_t')) + '</h4>' +
      '<p>' + esc(t('review_s')) + '</p>' +
      '<div class="star-input" role="radiogroup" aria-label="' + esc(t('review_rate')) + '">' +
        [1, 2, 3, 4, 5].map(function (n) { return '<button type="button" data-star="' + n + '" aria-label="' + n + '">' + icon('star') + '</button>'; }).join('') +
      '</div>' +
      '<textarea class="textarea" name="text" maxlength="600" placeholder="' + esc(t('review_ph')) + '"></textarea>' +
      '<div class="row" style="margin-top:10px"><button class="btn btn-primary btn-sm" type="submit">' + icon('lock', 'xs') + esc(t('review_send')) + '</button></div>' +
      '</form>';
  }

  /**
   * Suggest the AI assistant when the admin is offline, or hasn't replied in N minutes.
   */
  function fallbackCard() {
    var lastMine = null, answered = false;
    for (var i = state.messages.length - 1; i >= 0; i--) {
      var m = state.messages[i];
      if (m.from === 'me' && m.type !== 'review') { lastMine = m; break; }
      if (m.from === 'brand') { answered = true; break; }
    }
    if (!lastMine || answered || lastMine.pending || lastMine.failed) return '';
    if (state.aiDismissedFor === lastMine.id) return '';
    var waited = nowServer() - lastMine.time;
    var trigger = !state.adminOnline || waited >= (C.fallbackMinutes || 5) * 60;
    if (!trigger) return '';
    if (!(C.ai && C.ai.enabled)) {
      return !state.adminOnline ? '<div class="day">' + esc(t('offline_note')) + '</div>' : '';
    }
    return '<div class="sys-card ai-suggest">' +
      '<h4>' + aiStar() + esc(t('ai_suggest_t')) + '</h4><p>' + esc(t('ai_suggest_s')) + '</p>' +
      '<div class="row"><button class="btn btn-primary btn-sm" data-action="ask-ai" data-msg="' + esc(lastMine.id) + '">' + icon('sparkle', 'xs') + esc(t('ai_suggest_btn')) + '</button>' +
      '<button class="btn btn-ghost btn-sm" data-action="dismiss-ai" data-msg="' + esc(lastMine.id) + '">' + esc(t('ai_suggest_wait')) + '</button></div></div>';
  }

  function submitReview(form) {
    var rating = +(form.getAttribute('data-rating') || 0);
    var text = form.text.value.trim();
    if (!rating) { toast(t('review_rate'), true); return; }
    if (text.length < 3) { toast(t('review_short'), true); form.text.focus(); return; }
    var btn = form.querySelector('[type=submit]');
    btn.disabled = true;
    var id = +form.getAttribute('data-review');
    api('review', { method: 'POST', body: { invite: id, rating: rating, text: text } }).then(function (res) {
      var m = findMsg(id);
      if (m) { m.meta = m.meta || {}; m.meta.done = true; m.meta.rating = rating; }
      toast(t('review_thanks') + (res.status === 'pending' ? ' ' + t('review_pending') : ''));
      fetchMessages(false);
      renderMessages(true);
      refreshReviews();
    }).catch(function (ex) {
      btn.disabled = false;
      toast(ex.status === 409 ? t('review_thanks') : t('send_failed'), true);
    });
  }

  function refreshReviews() {
    api('reviews').then(function (d) { state.reviews = d.reviews || []; state.stats = d.stats || state.stats; }).catch(function () {});
  }

  /* ------------------------------------------------------------------- AI */
  function viewAI() {
    return '<main class="chat-screen">' +
      '<header class="chat-head glass">' + aiStar('') .replace('class="ai-star ', 'style="width:40px;height:40px;animation:none" class="ai-star ') +
        '<div class="grow"><div class="t">' + esc(t('ai_title')) + '</div><div class="s">' + esc(t('ai_disclaimer')) + '</div></div>' +
        (state.ai.messages.length ? '<button class="icon-btn" data-action="ai-clear" aria-label="' + esc(t('ai_clear')) + '">' + icon('trash', 'sm') + '</button>' : '') +
        '<a class="btn btn-ghost btn-sm" href="#chat">' + esc(t('ai_to_chat')) + '</a>' +
      '</header>' +
      '<div class="chat-scroll" id="ai-scroll"></div>' +
      '<div class="composer-wrap glass">' +
        '<div class="suggestions" id="ai-chips"></div>' +
        '<form class="composer" id="ai-form">' +
          '<label class="sr" for="ai-input">' + esc(t('ai_ph')) + '</label>' +
          '<textarea id="ai-input" rows="1" placeholder="' + esc(t('ai_ph')) + '"></textarea>' +
          '<button class="icon-btn send-btn" id="ai-send" aria-label="' + esc(t('send')) + '" disabled>' + icon('arrowUp', 'sm') + '</button>' +
        '</form></div></main>';
  }

  function mountAI() {
    var input = $('#ai-input'), send = $('#ai-send'), form = $('#ai-form');
    function sync() {
      input.style.height = 'auto';
      input.style.height = Math.min(140, input.scrollHeight) + 'px';
      send.disabled = !input.value.trim() || state.ai.busy;
    }
    input.addEventListener('input', sync);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && !('ontouchstart' in window)) { e.preventDefault(); form.dispatchEvent(new Event('submit', { cancelable: true })); }
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var q = input.value.trim();
      if (!q || state.ai.busy) return;
      input.value = '';
      sync();
      askAI(q);
    });
    renderAI();
    if (state.pendingAIQuestion) {
      var q = state.pendingAIQuestion;
      state.pendingAIQuestion = null;
      askAI(q);
    }
  }

  function mdLite(text) {
    var h = esc(text);
    h = h.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>');
    h = h.replace(/^\s*[-*•]\s+/gm, '• ');
    h = h.replace(/^#{1,4}\s*(.+)$/gm, '<b>$1</b>');
    return linkify(h);
  }

  function renderAI() {
    var box = document.getElementById('ai-scroll');
    if (!box) return;
    var html = '';
    if (!state.ai.messages.length) {
      html = '<div class="ai-welcome">' + aiStar() + '<h3>' + esc(t('ai_hello_t')) + '</h3><p>' + esc(t('ai_hello_s')) + '</p></div>';
    }
    state.ai.messages.forEach(function (m) {
      html += '<div class="msg ' + (m.role === 'user' ? 'me' : 'them') + '"><div class="bubble">' + (m.role === 'user' ? esc(m.content) : mdLite(m.content)) + '</div></div>';
    });
    if (state.ai.busy) html += '<div class="msg them"><div class="bubble typing"><i></i><i></i><i></i></div></div>';
    box.innerHTML = html;
    box.scrollTop = box.scrollHeight;
    var chips = document.getElementById('ai-chips');
    if (chips) {
      chips.innerHTML = state.ai.messages.length ? '' : t('ai_chips').map(function (c) { return '<button class="chip" data-ai-chip="' + esc(c) + '">' + esc(c) + '</button>'; }).join('');
    }
    var send = document.getElementById('ai-send'), input = document.getElementById('ai-input');
    if (send && input) send.disabled = !input.value.trim() || state.ai.busy;
  }

  function askAI(q) {
    state.ai.messages.push({ role: 'user', content: q });
    state.ai.busy = true;
    renderAI();
    var history = state.ai.messages.slice(-12);
    api(C.ai.endpoint, { method: 'POST', body: { messages: history, lang: state.lang } }).then(function (d) {
      state.ai.messages.push({ role: 'assistant', content: d.reply || t('ai_error') });
    }).catch(function (ex) {
      state.ai.messages.push({ role: 'assistant', content: ex.status === 429 ? t('ai_limit') : t('ai_error') });
    }).then(function () {
      state.ai.busy = false;
      SS.set('studyhub_ai', JSON.stringify(state.ai.messages.slice(-30)));
      if (route() === 'ai') render();
    });
  }

  /* -------------------------------------------------------------- profile */
  function viewProfile() {
    var cur = uniById(state.student.university);
    var standalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
    return '<main class="screen"><h1 class="page-title">' + esc(t('profile_title')) + '</h1>' +
      '<section class="profile-card glass" style="margin-top:14px">' + anonAvatar(state.student.alias, 'big-ava') +
        '<h2>' + esc(t('student')) + ' ' + esc(state.student.alias) + '</h2><p class="muted" style="margin:4px 0 0">' + esc(t('anon_id')) + '</p></section>' +
      '<div class="list glass">' +
        '<button class="list-item" data-action="uni-sheet"><span class="li-ico tone-lilac tone-bg">' + icon('grad', 'sm') + '</span><span class="grow"><span class="t">' + esc(t('university')) + '</span><br><span class="s">' + esc(cur ? loc(cur.name) : t('not_selected')) + '</span></span>' + icon('chevRight', 'sm chev') + '</button>' +
        '<div class="list-item"><span class="li-ico tone-sky tone-bg">' + icon('globe', 'sm') + '</span><span class="grow"><span class="t">' + esc(t('language')) + '</span></span>' +
          '<span class="seg"><button data-lang="kk" aria-pressed="' + (state.lang === 'kk') + '">Қаз</button><button data-lang="ru" aria-pressed="' + (state.lang === 'ru') + '">Рус</button></span></div>' +
        (standalone ? '' : '<button class="list-item" data-action="install"><span class="li-ico tone-peach tone-bg">' + icon('phone', 'sm') + '</span><span class="grow"><span class="t">' + esc(t('install')) + '</span><br><span class="s">' + esc(t('install_s')) + '</span></span>' + icon('chevRight', 'sm chev') + '</button>') +
      '</div>' +
      '<div class="section-head" style="margin-top:22px"><h2>' + esc(t('privacy')) + '</h2></div>' +
      '<div class="privacy glass" style="margin-top:0">' + privacyRows() + '</div>' +
      (C.urls && C.urls.site ? '<div class="list glass"><a class="list-item" href="' + esc(C.urls.site) + '"><span class="li-ico tone-mint tone-bg">' + icon('globe', 'sm') + '</span><span class="grow"><span class="t">' + esc(t('site')) + '</span></span>' + icon('chevRight', 'sm chev') + '</a></div>' : '') +
      '<div class="list glass"><button class="list-item danger" data-action="logout"><span class="li-ico" style="background:color-mix(in srgb,var(--danger) 12%,transparent);color:var(--danger)">' + icon('logout', 'sm') + '</span><span class="grow"><span class="t">' + esc(t('logout')) + '</span></span></button></div>' +
      '<p class="tiny" style="text-align:center;margin-top:18px">' + esc(C.brand) + '</p>' +
      '</main>';
  }

  function uniSheet() {
    var cur = state.student.university;
    var opts = (C.universities || []).map(function (u) {
      return '<button class="option" data-pick-uni="' + esc(u.id) + '" aria-pressed="' + (u.id === cur) + '">' +
        '<span class="uni-chip" style="border-color:transparent">' + (u.logo ? '<img src="' + esc(u.logo) + '" alt="">' : esc(u.short)) + '</span>' +
        '<span class="grow"><span class="t">' + esc(u.short) + '</span><br><span class="s">' + esc(loc(u.name)) + '</span></span>' +
        (u.id === cur ? icon('check', 'check') : '') + '</button>';
    }).join('');
    sheet('<h3>' + esc(t('uni_sheet')) + '</h3>' + opts, function (el, close) {
      el.addEventListener('click', function (e) {
        var b = e.target.closest('[data-pick-uni]');
        if (!b) return;
        setUni(b.getAttribute('data-pick-uni'));
        close();
      });
    });
  }
  function setUni(id) {
    state.student.university = id;
    vibrate();
    render();
    api('me', { method: 'POST', body: { university: id } }).catch(function () { toast(t('send_failed'), true); });
  }

  function install() {
    if (state.installPrompt) {
      state.installPrompt.prompt();
      state.installPrompt.userChoice.then(function (c) { if (c.outcome === 'accepted') toast(t('installed')); state.installPrompt = null; });
      return;
    }
    var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var steps = ios ? t('install_ios') : t('install_other');
    var icons = ios ? ['share', 'plusSq', 'check'] : ['grid', 'phone'];
    sheet('<h3>' + esc(t('install')) + '</h3><div class="howto">' + steps.map(function (s, i) {
      return '<div><span class="step-n">' + icon(icons[i] || 'check', 'sm') + '</span><span>' + esc(s) + '</span></div>';
    }).join('') + '</div><button class="btn btn-dark btn-block" data-close>' + esc(t('close')) + '</button>');
  }

  function logout() {
    if (!window.confirm(t('logout_confirm'))) return;
    api('logout', { method: 'POST' }).catch(function () {}).then(function () {
      state.student = null;
      state.messages = []; state.loaded = false; state.lastId = 0;
      state.ai.messages = []; SS.del('studyhub_ai');
      stopPolling();
      location.hash = '';
      render();
    });
  }

  /* ------------------------------------------------------ global handlers */
  root.addEventListener('click', function (e) {
    var el;
    if ((el = e.target.closest('[data-lang]'))) { setLang(el.getAttribute('data-lang')); return; }
    if ((el = e.target.closest('[data-order]'))) { startOrder(el.getAttribute('data-order')); return; }
    if ((el = e.target.closest('[data-uni]'))) { setUni(el.getAttribute('data-uni')); return; }
    if ((el = e.target.closest('[data-view]'))) { viewer(el.getAttribute('data-view')); return; }
    if ((el = e.target.closest('[data-ai-chip]'))) { askAI(el.getAttribute('data-ai-chip')); return; }
    if ((el = e.target.closest('[data-star]'))) {
      var form = el.closest('form'), n = +el.getAttribute('data-star');
      form.setAttribute('data-rating', n);
      form.querySelectorAll('[data-star]').forEach(function (b) { b.classList.toggle('on', +b.getAttribute('data-star') <= n); });
      vibrate();
      return;
    }
    if ((el = e.target.closest('[data-scroll]'))) {
      var target = el.getAttribute('data-scroll');
      setTimeout(function () { var s = document.getElementById(target); if (s) s.scrollIntoView({ behavior: 'smooth' }); }, 80);
      return;
    }
    if ((el = e.target.closest('[data-action]'))) {
      var a = el.getAttribute('data-action');
      if (a === 'uni-sheet') uniSheet();
      else if (a === 'install') install();
      else if (a === 'logout') logout();
      else if (a === 'clear-chip') { state.draftService = null; renderChip(); }
      else if (a === 'ai-clear') { state.ai.messages = []; SS.del('studyhub_ai'); render(); }
      else if (a === 'dismiss-ai') { state.aiDismissedFor = +el.getAttribute('data-msg'); renderMessages(false); }
      else if (a === 'ask-ai') {
        var lastId = +el.getAttribute('data-msg');
        var q = [];
        for (var i = state.messages.length - 1; i >= 0; i--) {
          var m = state.messages[i];
          if (m.from !== 'me' || m.type === 'review') break;
          if (m.text) q.unshift(m.text);
          if (m.id === lastId && q.length > 2) break;
        }
        state.aiDismissedFor = lastId;
        state.pendingAIQuestion = q.join('\n').trim() || null;
        go('ai');
      }
    }
  });
  root.addEventListener('submit', function (e) {
    var f = e.target.closest('[data-review]');
    if (f) { e.preventDefault(); submitReview(f); }
  });

  function handleAuthError(ex) {
    if (ex && ex.status === 401 && state.student) {
      state.student = null;
      stopPolling();
      render();
    }
  }

  /* -------------------------------------------------------------- polling */
  var pollTimer = null, tick = 0;
  function poll() {
    if (document.hidden || !state.student) return;
    tick++;
    if (route() === 'chat') {
      fetchMessages(!state.loaded);
      return;
    }
    if (tick % 3 === 0) {
      api('me').then(function (d) {
        var changed = d.unread !== state.unread || d.adminOnline !== state.adminOnline;
        state.unread = d.unread; state.adminOnline = !!d.adminOnline;
        state.serverOffset = d.now ? d.now - Math.floor(Date.now() / 1000) : state.serverOffset;
        if (changed && route() === 'home') render();
        else if (changed) { var tb = document.querySelector('.tabbar'); if (tb) tb.outerHTML = tabbar(route()); }
      }).catch(handleAuthError);
    }
  }
  function startPolling() { stopPolling(); pollTimer = setInterval(poll, 4000); }
  function stopPolling() { if (pollTimer) clearInterval(pollTimer); pollTimer = null; }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

  /* ------------------------------------------------------------------ PWA */
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); state.installPrompt = e; });
  if ('serviceWorker' in navigator && C.swUrl && location.protocol === 'https:') {
    window.addEventListener('load', function () {
      var scope = new URL(C.swUrl, location.href).pathname;
      navigator.serviceWorker.register(C.swUrl, { scope: scope }).catch(function () {});
    });
  }

  /* ----------------------------------------------------------------- boot */
  render();
  if (state.student) {
    startPolling();
    // Preload last messages for the home preview.
    api('messages?after=0' + (route() === 'chat' ? '' : '&peek=1')).then(function (d) {
      state.serverOffset = d.now ? d.now - Math.floor(Date.now() / 1000) : 0;
      state.messages = d.messages || [];
      state.messages.forEach(function (m) { state.lastId = Math.max(state.lastId, m.id); });
      state.readUpTo = d.readUpTo || 0;
      state.adminOnline = !!d.adminOnline;
      state.loaded = true;
      if (route() === 'home') {
        // Keep unread badge visible on home even though messages were fetched.
        render();
      } else if (route() === 'chat') {
        updateChatStatus();
        renderMessages(true);
      }
    }).catch(handleAuthError);
  }
})();
