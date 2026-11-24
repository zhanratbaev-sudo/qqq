/* StudyHub — админ-панель (отдельная страница, как приложение) */
(function () {
  'use strict';
  var P = window.STUDYHUB_PANEL || {};
  var SH = window.SH;
  var root = document.getElementById('panel');
  var esc = SH.esc, icon = SH.icon;

  var ICON_CHOICES = { cube: 'Макет', pencil: 'Карандаш', shapes: 'Композиция', lecture: 'Лекция', book: 'Книга', ruler: 'Линейка', building: 'Здание', laptop: 'Ноутбук', palette: 'Палитра', doc: 'Документ' };
  var TONES = { peach: 'Персиковый', lilac: 'Сиреневый', mint: 'Мятный', sky: 'Голубой', sand: 'Песочный', rose: 'Розовый' };

  var state = {
    chats: [], status: 'auto', online: false, pending: 0,
    active: 0, messages: [], lastId: 0, readUpTo: 0, filter: '',
    students: null, reviews: null, reviewFilter: 'pending', reviewStats: { count: 0, average: 0 },
    settings: null, services: null, universities: null, plugins: [], section: {}
  };

  function api(path, opts) { return SH.api(P.rest, path, opts, { 'X-WP-Nonce': P.nonce }); }
  function fail(e) { SH.toast(e && e.message && e.message !== 'error' ? e.message : 'Не получилось. Попробуйте ещё раз.', true); }
  function totalUnread() { return state.chats.reduce(function (s, c) { return s + c.unread; }, 0); }

  /* ---------------------------------------------------------------- вход */
  function viewLogin() {
    var denied = P.logged && !P.allowed;
    return '<main class="login screen" style="max-width:440px;margin:0 auto">' +
      '<div class="login-hero"><div class="brand-tile">' + SH.brandMark(P.logo) + '</div>' +
      '<h1>' + esc(P.brand) + '</h1><p>Панель управления</p></div>' +
      (denied
        ? '<div class="login-card glass"><p class="p-login-note">Вы вошли как «' + esc(P.userName) + '», но у этого аккаунта нет прав администратора.</p>' +
          '<button class="btn btn-dark btn-block" data-act="logout">Выйти и войти другим аккаунтом</button></div>'
        : '<form class="login-card glass" id="p-login" novalidate>' +
          '<div class="field"><label for="pl-l">Логин или e-mail WordPress</label><input class="input" id="pl-l" name="login" autocomplete="username" autocapitalize="off" required></div>' +
          '<div class="field"><label for="pl-p">Пароль</label><input class="input" id="pl-p" name="password" type="password" autocomplete="current-password" required>' +
          '<button type="button" class="eye" aria-label="Показать пароль">' + icon('eye', 'sm') + '</button></div>' +
          '<p class="form-error" id="pl-err" role="alert"></p>' +
          '<button class="btn btn-primary btn-block" type="submit">Войти</button></form>') +
      '<div class="login-foot"><p class="p-login-note">Вход — тем же логином и паролем, что и в WordPress. Только для администратора.</p>' +
      '<div class="trust"><span>' + icon('shield') + 'Защита от подбора пароля</span><span>' + icon('lock') + 'Отдельно от wp-admin</span></div></div></main>';
  }
  function mountLogin() {
    var f = document.getElementById('p-login');
    if (!f) return;
    f.querySelector('.eye').addEventListener('click', function () {
      var p = f.password; p.type = p.type === 'password' ? 'text' : 'password';
      this.innerHTML = icon(p.type === 'password' ? 'eye' : 'eyeOff', 'sm');
    });
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = f.querySelector('[type=submit]'), err = document.getElementById('pl-err');
      btn.disabled = true; err.textContent = '';
      SH.api(P.rest, 'panel/login', { method: 'POST', body: { login: f.login.value.trim(), password: f.password.value } })
        .then(function () { location.reload(); })
        .catch(function (ex) {
          btn.disabled = false;
          err.textContent = ex.status === 429 ? 'Слишком много попыток. Подождите 15 минут.' : ex.status === 401 ? 'Неверный логин или пароль (или нет прав администратора).' : 'Нет соединения.';
        });
    });
  }

  /* ---------------------------------------------------------------- оболочка */
  function navItems() {
    var items = [
      ['chats', 'chat', 'Чаты', totalUnread()],
      ['students', 'user', 'Студенты', 0],
      ['reviews', 'star', 'Отзывы', state.pending],
      ['catalog', 'grid', 'Услуги и вузы', 0],
      ['settings', 'sparkle', 'Настройки', 0]
    ];
    (P.sections || []).forEach(function (s) { items.push(['s-' + s.key, s.icon, s.title, 0]); });
    return items;
  }
  function route() {
    var r = (location.hash || '#chats').slice(1).split('/')[0];
    return navItems().some(function (n) { return n[0] === r; }) ? r : 'chats';
  }
  function go(r) { if (location.hash === '#' + r) render(); else location.hash = r; }

  function statusSeg() {
    return '<div class="p-status">' + '<span class="status-dot' + (state.online ? ' on' : '') + '"></span>' +
      '<div class="seg" role="group" aria-label="Статус">' +
      [['auto', 'Авто'], ['online', 'В сети'], ['offline', 'Нет']].map(function (s) {
        return '<button data-status="' + s[0] + '" aria-pressed="' + (state.status === s[0]) + '" title="' + (s[0] === 'auto' ? 'В сети, пока открыта панель' : s[0] === 'offline' ? 'Не в сети — студентам сразу предлагается ИИ' : 'Всегда в сети') + '">' + s[1] + '</button>';
      }).join('') + '</div></div>';
  }

  function shell(active, content) {
    var nav = navItems();
    var side = '<aside class="p-side glass"><div class="p-brand"><div class="brand-tile">' + SH.brandMark(P.logo) + '</div><div><b>' + esc(P.brand) + '</b><span>Панель</span></div></div>' +
      nav.map(function (n) {
        return '<a class="p-nav" href="#' + n[0] + '"' + (active === n[0] ? ' aria-current="page"' : '') + '>' + icon(n[1], 'sm') + '<span>' + esc(n[2]) + '</span>' + (n[3] ? '<span class="badge">' + n[3] + '</span>' : '') + '</a>';
      }).join('') +
      '<div class="p-side-foot">' + statusSeg() +
      '<a class="p-nav" href="' + esc(P.urls.site) + '" target="_blank">' + icon('globe', 'sm') + 'Открыть сайт</a>' +
      (P.urls.app ? '<a class="p-nav" href="' + esc(P.urls.app) + '" target="_blank">' + icon('phone', 'sm') + 'Кабинет студента</a>' : '') +
      '<button class="p-nav" data-act="logout">' + icon('logout', 'sm') + 'Выйти</button></div></aside>';
    var mobileTabs = nav.slice(0, 3).concat([['more', 'grid', 'Ещё', 0]]);
    var tabbar = '<nav class="tabbar glass">' + mobileTabs.map(function (n) {
      var cur = n[0] === active || (n[0] === 'more' && ['catalog', 'settings'].concat((P.sections || []).map(function (s) { return 's-' + s.key; })).indexOf(active) > -1);
      return '<a class="tab" href="#' + n[0] + '"' + (cur ? ' aria-current="page"' : '') + (n[0] === 'more' ? ' data-act="more"' : '') + '>' + icon(n[1]) + '<span>' + esc(n[2]) + '</span>' + (n[3] ? '<span class="badge">' + n[3] + '</span>' : '') + '</a>';
    }).join('') + '</nav>';
    var mtop = '<div class="p-mtop"><div class="brand-tile">' + SH.brandMark(P.logo) + '</div><div class="grow"><b>' + esc(P.brand) + '</b><span>Панель</span></div>' + statusSeg() + '</div>';
    return '<div class="p-shell">' + side + '<main class="p-main">' + mtop + content + '</main></div>' + tabbar;
  }

  function moreSheet() {
    var items = navItems().slice(3);
    SH.sheet('<h3>Ещё</h3>' + items.map(function (n) {
      return '<a class="option" href="#' + n[0] + '" data-close>' + icon(n[1]) + '<span class="grow"><span class="t">' + esc(n[2]) + '</span></span>' + icon('chevRight', 'sm') + '</a>';
    }).join('') +
      '<a class="option" href="' + esc(P.urls.site) + '" target="_blank">' + icon('globe') + '<span class="grow"><span class="t">Открыть сайт</span></span></a>' +
      '<button class="option" data-act="logout">' + icon('logout') + '<span class="grow"><span class="t">Выйти</span></span></button>');
  }

  function render() {
    if (!P.logged || !P.allowed) { root.innerHTML = viewLogin(); mountLogin(); return; }
    var r = route();
    var html = '';
    if (r === 'chats') html = viewChats();
    else if (r === 'students') html = viewStudents();
    else if (r === 'reviews') html = viewReviews();
    else if (r === 'catalog') html = viewCatalog();
    else if (r === 'settings') html = viewSettings();
    else if (r.indexOf('s-') === 0) html = viewSection(r.slice(2));
    root.innerHTML = shell(r, html);
    if (r === 'chats') mountChats();
  }
  function refreshShell() {
    var r = route();
    var side = root.querySelector('.p-side'); var tb = root.querySelector('.tabbar'); var mt = root.querySelector('.p-mtop');
    if (!side) return;
    var tmp = document.createElement('div');
    tmp.innerHTML = shell(r, '');
    side.replaceWith(tmp.querySelector('.p-side'));
    tb.replaceWith(tmp.querySelector('.tabbar'));
    if (mt) mt.replaceWith(tmp.querySelector('.p-mtop'));
  }
  function head(title, sub, right) {
    return '<header class="p-head"><div><h1>' + esc(title) + '</h1>' + (sub ? '<p>' + esc(sub) + '</p>' : '') + '</div>' + (right || '') + '</header>';
  }
  function loading() { return '<div class="p-card glass"><div class="skeleton" style="height:18px;width:40%"></div><div class="skeleton" style="height:14px;width:70%;margin-top:12px"></div></div>'; }

  /* ---------------------------------------------------------------- чаты */
  function viewChats() {
    return head('Чаты', 'Студенты видят только «' + P.brand + '». Ваше имя скрыто.') +
      '<section class="p-chat glass' + (state.active ? ' open' : '') + '" id="p-chat">' +
      '<div class="p-chat-list"><div class="p-chat-search"><input class="input" id="p-search" type="search" placeholder="Поиск: #ID, логин, вуз, заметка" value="' + esc(state.filter) + '"></div>' +
      '<div class="p-chat-items" id="p-items"></div></div>' +
      '<div class="p-conv" id="p-conv"></div></section>';
  }
  function mountChats() {
    document.getElementById('p-search').addEventListener('input', function () { state.filter = this.value.toLowerCase(); renderChatList(); });
    renderChatList();
    renderConv();
  }
  function renderChatList() {
    var box = document.getElementById('p-items');
    if (!box) return;
    var f = state.filter;
    var list = state.chats.filter(function (c) { return !f || (c.alias + ' ' + c.login + ' ' + c.university + ' ' + c.note).toLowerCase().indexOf(f) > -1; });
    if (!state.chats.length) {
      box.innerHTML = '<div class="p-empty">' + icon('chat') + 'Чатов пока нет.<br><a href="#students" style="color:var(--accent);font-weight:600">Выдать логин студенту →</a></div>';
      return;
    }
    box.innerHTML = list.map(function (c) {
      return '<button class="p-ci' + (c.id === state.active ? ' active' : '') + '" data-chat="' + c.id + '"' + (c.disabled ? ' style="opacity:.5"' : '') + '>' + SH.anonAvatar(c.alias) +
        '<span class="grow"><span class="top"><b>' + esc(c.alias) + '</b>' + (c.university ? '<span class="p-tag">' + esc(c.university) + '</span>' : '') + '<span class="when">' + SH.fmtDate(c.lastAt) + '</span></span>' +
        '<span class="prev" style="display:block">' + esc(c.preview || (c.note ? '📝 ' + c.note : 'Нет сообщений')) + '</span></span>' +
        (c.unread ? '<span class="badge">' + c.unread + '</span>' : '') + '</button>';
    }).join('') || '<div class="p-empty">Ничего не найдено</div>';
  }
  function currentChat() { for (var i = 0; i < state.chats.length; i++) if (state.chats[i].id === state.active) return state.chats[i]; return null; }

  function renderConv() {
    var box = document.getElementById('p-conv');
    if (!box) return;
    var c = currentChat();
    if (!state.active || !c) {
      box.innerHTML = '<div class="p-empty" style="margin:auto">' + icon('chat') + 'Выберите чат слева</div>';
      return;
    }
    var svc = (P.services || []).map(function (s) { return '<option value="' + esc(s.id) + '">' + esc(s.title) + '</option>'; }).join('');
    box.innerHTML =
      '<header class="chat-head"><button class="icon-btn p-back" data-act="back" aria-label="Назад">' + icon('chevRight', 'sm').replace('m9 6 6 6-6 6', 'm15 6-6 6 6 6') + '</button>' +
        SH.anonAvatar(c.alias) +
        '<div class="grow"><div class="t">' + esc(c.alias) + '</div><div class="s">логин <code class="p-code">' + esc(c.login) + '</code>' + (c.university ? ' · ' + esc(c.university) : '') + (c.lastSeen ? ' · был(а) ' + SH.fmtDate(c.lastSeen) : '') + '</div></div>' +
        '<button class="btn btn-ghost btn-sm" data-act="review-ask" title="Отправить студенту карточку «Оцените работу»">' + icon('star', 'xs') + 'Отзыв</button>' +
      '</header>' +
      '<div class="p-note">' + icon('pencil', 'xs') + '<input id="p-note" value="' + esc(c.note) + '" placeholder="Заметка (видите только вы): что заказал, цена, срок…"></div>' +
      '<div class="chat-scroll" id="p-msgs"><div class="skeleton" style="height:50px;width:60%"></div></div>' +
      '<div class="composer-wrap"><form class="composer" id="p-form">' +
        '<input type="file" id="p-file" class="sr"><button type="button" class="icon-btn attach-btn" data-act="attach" aria-label="Прикрепить файл">' + icon('clip', 'sm') + '</button>' +
        '<textarea id="p-input" rows="1" placeholder="Ответ от имени «' + esc(P.brand) + '»…"></textarea>' +
        '<button class="icon-btn send-btn" id="p-send" aria-label="Отправить">' + icon('arrowUp', 'sm') + '</button></form></div>' +
      '<select id="p-rev-svc" class="sr">' + svc + '</select>';
    var input = document.getElementById('p-input');
    input.addEventListener('input', function () { input.style.height = 'auto'; input.style.height = Math.min(160, input.scrollHeight) + 'px'; });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } });
    document.getElementById('p-form').addEventListener('submit', function (e) { e.preventDefault(); send(); });
    document.getElementById('p-file').addEventListener('change', function () { var f = this.files[0]; this.value = ''; if (f) send(f); });
    var t;
    document.getElementById('p-note').addEventListener('input', function () {
      var v = this.value; clearTimeout(t);
      t = setTimeout(function () { api('admin/chats/' + state.active + '/note', { method: 'POST', body: { note: v } }); c.note = v; }, 600);
    });
    renderMessages(true);
    loadMessages(true);
  }

  function openChat(id) {
    state.active = id; state.messages = []; state.lastId = 0; state.readUpTo = 0;
    var el = document.getElementById('p-chat'); if (el) el.classList.add('open');
    renderChatList(); renderConv();
  }

  function loadChats() {
    return api('admin/chats').then(function (d) {
      var before = totalUnread() + '|' + state.pending + '|' + state.status + '|' + state.online;
      state.chats = d.chats; state.status = d.status; state.online = d.online; state.pending = d.pending;
      if (before !== totalUnread() + '|' + state.pending + '|' + state.status + '|' + state.online) refreshShell();
      renderChatList();
      var n = totalUnread();
      document.title = (n ? '(' + n + ') ' : '') + P.brand + ' · Панель';
    }).catch(function (e) { if (e.status === 401 || e.status === 403) location.reload(); });
  }

  function loadMessages(initial) {
    var id = state.active;
    if (!id) return;
    api('admin/chats/' + id + '/messages?after=' + (initial ? 0 : state.lastId)).then(function (d) {
      if (id !== state.active) return;
      var added = !!initial;
      if (initial) state.messages = d.messages;
      else d.messages.forEach(function (m) { if (!state.messages.some(function (x) { return x.id === m.id; })) { state.messages.push(m); added = true; } });
      state.messages.forEach(function (m) { state.lastId = Math.max(state.lastId, m.id); });
      var readChanged = state.readUpTo !== d.readUpTo; state.readUpTo = d.readUpTo;
      if (added || readChanged) renderMessages(added);
      var c = currentChat(); if (c && c.unread) { c.unread = 0; renderChatList(); refreshShell(); }
    }).catch(function () {});
  }

  function svcTitle(id) { for (var i = 0; i < (P.services || []).length; i++) if (P.services[i].id === id) return P.services[i].title; return id; }

  function renderMessages(scroll) {
    var box = document.getElementById('p-msgs');
    if (!box) return;
    if (!state.messages.length) { box.innerHTML = '<div class="p-empty">Сообщений пока нет. Студент видит приветствие из настроек.</div>'; return; }
    var near = box.scrollHeight - box.scrollTop - box.clientHeight < 150;
    var lastDay = '';
    box.innerHTML = state.messages.map(function (m) {
      var mine = m.from !== 'student';
      var day = new Date(m.time * 1000).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' });
      var sep = day !== lastDay ? '<div class="day">' + esc(day) + '</div>' : ''; lastDay = day;
      var inner = '';
      if (m.meta && m.meta.service && m.type !== 'review_request') inner += '<span class="p-tag ok p-msg-svc">' + esc(svcTitle(m.meta.service)) + '</span>';
      if (m.type === 'review_request') {
        inner += '<div class="bubble sys">⭐ Запрос отзыва' + (m.meta && m.meta.service ? ' · ' + esc(svcTitle(m.meta.service)) : '') + ' — ' + (m.meta && m.meta.done ? 'оставлен (' + (m.meta.rating || '') + '★)' : 'ожидает') + '</div>';
      } else if (m.type === 'review') {
        inner += '<div class="bubble sys">Отзыв ' + '★'.repeat((m.meta && m.meta.rating) || 5) + '\n' + esc(m.text) + '</div><a href="#reviews" class="tiny" style="margin:4px 6px">Модерация →</a>';
      } else {
        if (m.file) {
          if (m.file.isImage) inner += '<a class="msg-img" href="' + esc(m.file.url) + '" target="_blank"><img src="' + esc(m.file.url) + '" alt="" loading="lazy"></a>';
          else inner += '<a class="msg-file" href="' + esc(SH.withQuery(m.file.url, 'download=1')) + '"><span class="file-ico">' + esc((m.file.name.split('.').pop() || '').slice(0, 4)) + '</span><span><span class="file-name">' + esc(m.file.name) + '</span><span class="file-size">' + SH.fmtSize(m.file.size) + '</span></span></a>';
        }
        if (m.text) inner += '<div class="bubble">' + esc(m.text).replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>') + '</div>';
      }
      var tick = mine && m.type !== 'review_request' ? (m.id <= state.readUpTo ? '<span class="read">' + icon('checks') + '</span>' : icon('check')) : '';
      return sep + '<div class="msg ' + (mine ? 'me' : 'them') + '">' + inner + '<span class="meta">' + SH.fmtTime(m.time) + tick + '</span></div>';
    }).join('');
    if (scroll || near) box.scrollTop = box.scrollHeight;
  }

  var sending = false;
  function send(file) {
    var input = document.getElementById('p-input');
    var text = input.value.trim();
    if ((!text && !file) || sending) return;
    sending = true;
    var fd = new FormData();
    fd.append('text', file ? '' : text);
    if (file) fd.append('file', file);
    api('admin/chats/' + state.active + '/messages', { method: 'POST', body: fd }).then(function (d) {
      if (!file) { input.value = ''; input.style.height = 'auto'; }
      state.messages.push(d.message); state.lastId = Math.max(state.lastId, d.message.id);
      renderMessages(true);
    }).catch(function (e) {
      SH.toast(e.status === 415 ? 'Этот тип файла не разрешён.' : e.status === 413 ? 'Файл слишком большой (см. Настройки).' : 'Не удалось отправить.', true);
    }).then(function () { sending = false; input.focus(); });
  }

  function askReview() {
    var opts = '<option value="">Без услуги</option>' + (P.services || []).map(function (s) { return '<option value="' + esc(s.id) + '">' + esc(s.title) + '</option>'; }).join('');
    SH.sheet('<h3>Попросить отзыв</h3><p class="muted" style="margin:-6px 0 14px">Студент получит карточку «Оцените нашу работу». Оставить отзыв сможет только он и только один раз.</p>' +
      '<label class="p-field">За какую услугу<select class="input" id="rv-svc">' + opts + '</select></label>' +
      '<div class="p-row-actions"><button class="btn btn-primary" id="rv-go">' + icon('star', 'xs') + 'Отправить</button><button class="btn btn-ghost" data-close>Отмена</button></div>',
      function (el, close) {
        el.querySelector('#rv-go').addEventListener('click', function () {
          api('admin/chats/' + state.active + '/review-request', { method: 'POST', body: { service: el.querySelector('#rv-svc').value } })
            .then(function () { close(); loadMessages(false); SH.toast('Запрос отправлен'); }).catch(fail);
        });
      });
  }

  /* ---------------------------------------------------------------- студенты */
  function viewStudents() {
    if (!state.students) { loadStudents(); return head('Студенты', '') + loading(); }
    var list = state.students;
    var online = list.filter(function (s) { return s.lastSeen && Date.now() / 1000 - s.lastSeen < 86400 * 7; }).length;
    return head('Студенты', 'Регистрации нет — вы выдаёте логин и пароль. Имён в системе нет, только анонимный ID.') +
      '<div class="p-stats">' +
        '<div class="p-stat glass"><span class="quick-ico tone-lilac tone-bg">' + icon('user', 'sm') + '</span><div><b>' + list.length + '</b><span>всего</span></div></div>' +
        '<div class="p-stat glass"><span class="quick-ico tone-mint tone-bg">' + icon('check', 'sm') + '</span><div><b>' + online + '</b><span>заходили за неделю</span></div></div>' +
        '<div class="p-stat glass"><span class="quick-ico tone-peach tone-bg">' + icon('chat', 'sm') + '</span><div><b>' + totalUnread() + '</b><span>непрочитанных</span></div></div>' +
      '</div>' +
      (!P.hasClient ? '<div class="p-card glass"><b>Включите плагин «StudyHub Студент»</b> — без него студенты не смогут войти.</div>' : '') +
      '<form class="p-card glass" id="st-new"><h2>' + icon('plusSq', 'sm') + 'Выдать доступ</h2><div class="p-grid">' +
        '<label class="p-field">Логин<input class="input" name="login" placeholder="авто (st12345)" pattern="[A-Za-z0-9_.\\-]{3,40}" autocapitalize="off"></label>' +
        '<label class="p-field">Пароль<input class="input" name="password" placeholder="авто" minlength="6" autocapitalize="off"></label>' +
        '<label class="p-field">Заметка (видите только вы)<input class="input" name="note" placeholder="например: макет, 2 курс"></label>' +
        '<label class="p-field">Сколько логинов<input class="input" name="count" type="number" min="1" max="50" value="1"></label>' +
      '</div><div class="p-row-actions"><button class="btn btn-primary">' + icon('plusSq', 'xs') + 'Создать</button></div></form>' +
      '<div class="p-list">' + (list.length ? list.map(function (s) {
        return '<div class="p-item glass' + (s.disabled ? ' off' : '') + '">' + SH.anonAvatar(s.alias) +
          '<div class="grow"><div class="t">' + esc(s.alias) + ' <code class="p-code">' + esc(s.login) + '</code>' + (s.university ? '<span class="p-tag">' + esc(s.university) + '</span>' : '') + (s.disabled ? '<span class="p-tag mute">заблокирован</span>' : '') + (s.unread ? '<span class="badge">' + s.unread + '</span>' : '') + '</div>' +
          '<div class="s">' + (s.note ? '📝 ' + esc(s.note) + ' · ' : '') + (s.lastSeen ? 'был(а) ' + SH.fmtDate(s.lastSeen) : 'ещё не входил(а)') + '</div></div>' +
          '<div class="p-filter">' +
            '<button class="btn btn-ghost btn-sm" data-st-chat="' + s.id + '">' + icon('chat', 'xs') + 'Чат</button>' +
            '<button class="btn btn-ghost btn-sm" data-st="reset" data-id="' + s.id + '">Новый пароль</button>' +
            '<button class="btn btn-ghost btn-sm" data-st="' + (s.disabled ? 'enable' : 'disable') + '" data-id="' + s.id + '">' + (s.disabled ? 'Включить' : 'Блок') + '</button>' +
            '<button class="btn btn-danger btn-sm" data-st="delete" data-id="' + s.id + '" aria-label="Удалить">' + icon('trash', 'xs') + '</button>' +
          '</div></div>';
      }).join('') : '<div class="p-empty glass">' + icon('user') + 'Пока никого. Создайте первый логин выше.</div>') + '</div>';
  }
  function loadStudents() { api('admin/students').then(function (d) { state.students = d.students; if (route() === 'students') render(); }).catch(fail); }
  function credsSheet(d) {
    SH.sheet('<h3>Готово! Отправьте студенту</h3><p class="muted" style="margin:-6px 0 12px">Пароль показывается только один раз.</p>' +
      '<textarea class="textarea creds" readonly>' + esc(d.text) + '</textarea>' +
      '<div class="p-row-actions"><button class="btn btn-primary" id="cp">' + icon('doc', 'xs') + 'Копировать</button><button class="btn btn-ghost" data-close>Закрыть</button></div>',
      function (el) {
        el.querySelector('#cp').addEventListener('click', function () {
          var ta = el.querySelector('textarea'); ta.select();
          (navigator.clipboard ? navigator.clipboard.writeText(ta.value) : Promise.reject()).catch(function () { document.execCommand('copy'); });
          this.innerHTML = icon('check', 'xs') + 'Скопировано';
        });
      });
  }

  /* ---------------------------------------------------------------- отзывы */
  function viewReviews() {
    if (!state.reviews) { loadReviews(); return head('Отзывы', '') + loading(); }
    var f = state.reviewFilter;
    var list = state.reviews.filter(function (r) { return f === 'all' || r.status === f; });
    var labels = { pending: ['На проверке', 'warn'], approved: ['Опубликован', 'ok'], hidden: ['Скрыт', 'mute'] };
    return head('Отзывы', 'Только от студентов, которым вы отправили запрос в чате. Имена, фото и контакты не публикуются.',
      '<div class="seg">' + [['pending', 'На проверке'], ['approved', 'Опубликованные'], ['all', 'Все']].map(function (x) {
        return '<button data-rf="' + x[0] + '" aria-pressed="' + (f === x[0]) + '">' + x[1] + '</button>';
      }).join('') + '</div>') +
      '<div class="p-stats"><div class="p-stat glass"><span class="quick-ico tone-peach tone-bg">' + icon('star', 'sm') + '</span><div><b>' + (state.reviewStats.average || '—') + '</b><span>средняя оценка</span></div></div>' +
      '<div class="p-stat glass"><span class="quick-ico tone-mint tone-bg">' + icon('check', 'sm') + '</span><div><b>' + state.reviewStats.count + '</b><span>опубликовано</span></div></div></div>' +
      (list.length ? '<div class="p-reviews">' + list.map(function (r) {
        var l = labels[r.status] || [r.status, 'mute'];
        return '<article class="p-review glass"><div style="display:flex;justify-content:space-between;align-items:center">' + SH.stars(r.rating) + '<span class="p-tag ' + l[1] + '">' + l[0] + '</span></div>' +
          '<p>' + esc(r.text) + '</p><div class="tiny">' + esc(r.alias) + ' · ' + esc(r.university || 'вуз не указан') + (r.service ? ' · ' + esc(svcTitle(r.service)) : '') + ' · ' + new Date(r.time * 1000).toLocaleDateString('ru-RU') + '</div>' +
          '<div class="p-filter">' + (r.status !== 'approved' ? '<button class="btn btn-primary btn-sm" data-rv="approve" data-id="' + r.id + '">Опубликовать</button>' : '') +
          (r.status !== 'hidden' ? '<button class="btn btn-ghost btn-sm" data-rv="hide" data-id="' + r.id + '">Скрыть</button>' : '') +
          '<button class="btn btn-danger btn-sm" data-rv="delete" data-id="' + r.id + '">' + icon('trash', 'xs') + '</button></div></article>';
      }).join('') + '</div>' : '<div class="p-empty glass">' + icon('star') + (f === 'pending' ? 'Нет отзывов на проверке.' : 'Отзывов пока нет.') + '<br>Откройте чат со студентом → «Отзыв».</div>');
  }
  function loadReviews() { api('admin/reviews').then(function (d) { state.reviews = d.reviews; state.reviewStats = d.stats; if (route() === 'reviews') render(); }).catch(fail); }

  /* ---------------------------------------------------------------- услуги и вузы */
  function loadSettings() {
    return api('admin/settings').then(function (d) {
      state.settings = d.settings; state.services = d.services; state.universities = d.universities; state.plugins = d.plugins;
      var r = route(); if (r === 'catalog' || r === 'settings') render();
    }).catch(fail);
  }
  function sel(name, map, val) {
    return '<select class="input" name="' + name + '">' + Object.keys(map).map(function (k) { return '<option value="' + k + '"' + (k === val ? ' selected' : '') + '>' + esc(map[k]) + '</option>'; }).join('') + '</select>';
  }
  function serviceRow(s) {
    s = s || {};
    return '<div class="p-edit" data-row="svc"><input type="hidden" name="id" value="' + esc(s.id || '') + '">' +
      '<div class="p-edit-head"><span class="quick-ico tone-' + esc(s.color || 'peach') + ' tone-bg">' + icon(s.icon || 'book', 'sm') + '</span><b>' + esc(s.title_ru || 'Новая услуга') + '</b>' +
      '<button type="button" class="btn btn-danger btn-sm" data-act="row-del">' + icon('trash', 'xs') + '</button></div><div class="p-grid">' +
      '<label class="p-field">Название (рус)<input class="input" name="title_ru" value="' + esc(s.title_ru || '') + '" required></label>' +
      '<label class="p-field">Атауы (қаз)<input class="input" name="title_kk" value="' + esc(s.title_kk || '') + '"></label>' +
      '<label class="p-field">Описание (рус)<textarea class="textarea" name="desc_ru">' + esc(s.desc_ru || '') + '</textarea></label>' +
      '<label class="p-field">Сипаттамасы (қаз)<textarea class="textarea" name="desc_kk">' + esc(s.desc_kk || '') + '</textarea></label>' +
      '<label class="p-field">Цена <small>пусто — «Цена — в чате»</small><input class="input" name="price" value="' + esc(s.price || '') + '" placeholder="от 5 000 ₸"></label>' +
      '<label class="p-field">Иконка' + sel('icon', ICON_CHOICES, s.icon || 'book') + '</label>' +
      '<label class="p-field">Цвет' + sel('color', TONES, s.color || 'peach') + '</label></div></div>';
  }
  function uniRow(u) {
    u = u || {};
    return '<div class="p-edit" data-row="uni"><input type="hidden" name="id" value="' + esc(u.id || '') + '">' +
      '<div class="p-edit-head"><span class="uni-chip">' + (u.logo ? '<img src="' + esc(u.logo) + '" alt="">' : esc(u.short || '?')) + '</span><b>' + esc(u.short || 'Новый вуз') + '</b>' +
      '<button type="button" class="btn btn-danger btn-sm" data-act="row-del">' + icon('trash', 'xs') + '</button></div><div class="p-grid">' +
      '<label class="p-field">Коротко (на значке)<input class="input" name="short" maxlength="10" value="' + esc(u.short || '') + '" required></label>' +
      '<label class="p-field">Полное название (рус)<input class="input" name="name_ru" value="' + esc(u.name_ru || '') + '"></label>' +
      '<label class="p-field">Толық атауы (қаз)<input class="input" name="name_kk" value="' + esc(u.name_kk || '') + '"></label>' +
      '<label class="p-field">Логотип<span style="display:flex;gap:6px"><input class="input" name="logo" type="url" value="' + esc(u.logo || '') + '" placeholder="https://…"><button type="button" class="btn btn-ghost btn-sm" style="height:46px" data-upload="logo">' + icon('image', 'xs') + '</button></span></label>' +
      '</div></div>';
  }
  function viewCatalog() {
    if (!state.services) { loadSettings(); return head('Услуги и вузы', '') + loading(); }
    return head('Услуги и вузы', 'Тексты на русском и казахском — студент видит свой язык.') +
      '<form id="cat-form"><section class="p-card glass"><h2>' + icon('grid', 'sm') + 'Услуги</h2><div id="svc-rows">' + state.services.map(serviceRow).join('') + '</div>' +
      '<button type="button" class="btn btn-ghost btn-sm" data-act="add-svc">+ Добавить услугу</button></section>' +
      '<section class="p-card glass"><h2>' + icon('grad', 'sm') + 'Университеты</h2><div id="uni-rows">' + state.universities.map(uniRow).join('') + '</div>' +
      '<button type="button" class="btn btn-ghost btn-sm" data-act="add-uni">+ Добавить университет</button></section>' +
      '<div class="p-sticky-save"><button class="btn btn-primary">' + icon('check', 'xs') + 'Сохранить</button></div></form>';
  }
  function rowsData(type) {
    return Array.prototype.map.call(document.querySelectorAll('[data-row="' + type + '"]'), function (row) {
      var o = {}; row.querySelectorAll('[name]').forEach(function (i) { o[i.name] = i.value; }); return o;
    });
  }

  /* ---------------------------------------------------------------- настройки */
  function fld(name, label, type, help, extra) {
    var v = state.settings[name];
    var h = help ? '<small>' + esc(help) + '</small>' : '';
    if (type === 'checkbox') return '<label class="p-check"><input type="checkbox" name="' + name + '"' + (+v ? ' checked' : '') + '><span>' + esc(label) + (help ? '<br><small class="muted">' + esc(help) + '</small>' : '') + '</span></label>';
    if (type === 'textarea') return '<label class="p-field">' + esc(label) + h + '<textarea class="textarea" name="' + name + '">' + esc(v) + '</textarea></label>';
    if (type === 'media') return '<label class="p-field">' + esc(label) + h + '<span style="display:flex;gap:6px"><input class="input" type="url" name="' + name + '" value="' + esc(v) + '" placeholder="https://…"><button type="button" class="btn btn-ghost btn-sm" style="height:46px" data-upload="' + name + '">' + icon('image', 'xs') + 'Загрузить</button></span></label>';
    if (type === 'select') return '<label class="p-field">' + esc(label) + h + sel(name, extra, v) + '</label>';
    return '<label class="p-field">' + esc(label) + h + '<input class="input" type="' + type + '" name="' + name + '" value="' + esc(v) + '"' + (extra || '') + '></label>';
  }
  function viewSettings() {
    if (!state.settings) { loadSettings(); return head('Настройки', '') + loading(); }
    return head('Настройки', 'Название сайта студенты видят вместо вашего имени.') +
      '<form id="set-form">' +
      '<section class="p-card glass"><h2>' + icon('sparkle', 'sm') + 'Бренд</h2><div class="p-grid">' +
        fld('brand_name', 'Название сайта и приложения', 'text') + fld('accent_color', 'Акцентный цвет', 'color') +
        fld('brand_tagline_ru', 'Подзаголовок (рус)', 'text') + fld('brand_tagline_kk', 'Подзаголовок (қаз)', 'text') +
        fld('brand_logo', 'Логотип / значок приложения', 'media', 'Квадрат PNG 512×512') + '</div></section>' +
      '<section class="p-card glass"><h2>' + icon('building', 'sm') + 'Главный баннер</h2><div class="p-grid">' +
        fld('hero_title_ru', 'Заголовок (рус)', 'text') + fld('hero_title_kk', 'Тақырып (қаз)', 'text') +
        fld('hero_text_ru', 'Текст (рус)', 'textarea') + fld('hero_text_kk', 'Мәтін (қаз)', 'textarea') +
        fld('hero_image', 'Фото баннера (необязательно)', 'media') + '</div></section>' +
      '<section class="p-card glass"><h2>' + icon('chat', 'sm') + 'Чат и ИИ-подстраховка</h2><div class="p-grid">' +
        fld('fallback_minutes', 'Предлагать ИИ, если я не ответил за (минут)', 'number', '', ' min="1" max="240"') +
        fld('max_upload_mb', 'Максимальный размер файла, МБ', 'number', '', ' min="1" max="200"') +
        fld('welcome_ru', 'Приветствие в чате (рус)', 'textarea') + fld('welcome_kk', 'Чаттағы сәлемдесу (қаз)', 'textarea') + '</div>' +
        fld('notify_email', 'Письмо на e-mail администратора о новых сообщениях', 'checkbox', 'Не чаще раза в 10 минут на один чат') +
        fld('reviews_automod', 'Публиковать отзывы сразу, без проверки', 'checkbox') + '</section>' +
      '<section class="p-card glass"><h2>' + icon('globe', 'sm') + 'Адреса</h2><div class="p-grid">' +
        fld('app_slug', 'Кабинет студента', 'text', location.origin + '/…') + fld('panel_slug', 'Эта панель', 'text', location.origin + '/…') +
        fld('contact_url', 'Ссылка «Получить доступ» на сайте', 'url', 'Telegram, WhatsApp или Instagram — куда писать новым студентам. Ваше имя не показывается.') +
        fld('contact_label', 'Текст кнопки', 'text') + '</div>' +
        fld('site_on_front', 'Показывать сайт StudyHub на главной странице', 'checkbox', 'Выключите, если главную должна показывать тема WordPress') +
        fld('delete_on_uninstall', 'Удалить все данные при удалении плагина', 'checkbox', 'Чаты, студенты, файлы — безвозвратно') + '</section>' +
      '<section class="p-card glass"><h2>' + icon('grid', 'sm') + 'Дополнения</h2><div class="p-list">' + state.plugins.map(function (p) {
        return '<div class="p-item" style="padding:8px 0"><div class="grow"><div class="t">' + esc(p.name) + (p.core ? ' <span class="p-tag mute">ядро</span>' : '') + '</div></div><span class="tiny">' + esc(p.ver) + '</span></div>';
      }).join('') + '</div><p class="tiny" style="margin:10px 0 0"><a href="' + esc(P.urls.wp) + '" target="_blank">Админка WordPress ↗</a></p></section>' +
      '<div class="p-sticky-save"><button class="btn btn-primary">' + icon('check', 'xs') + 'Сохранить</button></div></form>';
  }

  /* ---------------------------------------------------------------- разделы плагинов */
  function sectionMeta(key) { return (P.sections || []).filter(function (s) { return s.key === key; })[0]; }
  function viewSection(key) {
    var meta = sectionMeta(key);
    if (!meta) return head('Раздел не найден', '');
    var vals = state.section[key];
    if (!vals) {
      api('admin/sections/' + key).then(function (d) { state.section[key] = d.values || {}; if (route() === 's-' + key) render(); }).catch(fail);
      return head(meta.title, meta.intro) + loading();
    }
    var fields = meta.fields.map(function (f) {
      var v = vals[f.name] == null ? '' : vals[f.name];
      var help = f.help ? '<small>' + esc(f.help) + '</small>' : '';
      if (f.type === 'heading') return '<h2 style="grid-column:1/-1;margin:14px 0 0;padding-top:14px;border-top:1px solid var(--hairline)">' + esc(f.label) + (f.help ? ' <small class="tiny" style="font-weight:500">' + esc(f.help) + '</small>' : '') + '</h2>';
      if (f.type === 'media') return '<label class="p-field">' + esc(f.label) + help + '<span style="display:flex;gap:6px"><input class="input" type="url" name="' + esc(f.name) + '" value="' + esc(v) + '" placeholder="https://…"><button type="button" class="btn btn-ghost btn-sm" style="height:46px" data-upload="1">' + icon('image', 'xs') + '</button></span></label>';
      if (f.type === 'info') return '<div class="p-field" style="grid-column:1/-1"><span>' + esc(f.label) + '</span><div style="font-weight:400;color:var(--text)">' + esc(v || f.help || '') + '</div></div>';
      if (f.type === 'checkbox') return '<label class="p-check" style="grid-column:1/-1"><input type="checkbox" name="' + esc(f.name) + '"' + (+v ? ' checked' : '') + '><span>' + esc(f.label) + (f.help ? '<br><small class="muted">' + esc(f.help) + '</small>' : '') + '</span></label>';
      if (f.type === 'textarea') return '<label class="p-field" style="grid-column:1/-1">' + esc(f.label) + help + '<textarea class="textarea" name="' + esc(f.name) + '" rows="' + (f.rows || 4) + '" placeholder="' + esc(f.placeholder || '') + '">' + esc(v) + '</textarea></label>';
      if (f.type === 'select') return '<label class="p-field">' + esc(f.label) + help + sel(f.name, f.options || {}, v) + '</label>';
      return '<label class="p-field">' + esc(f.label) + help + '<input class="input" type="' + esc(f.type || 'text') + '" name="' + esc(f.name) + '" value="' + esc(v) + '" placeholder="' + esc(f.placeholder || '') + '"' + (f.type === 'password' ? ' autocomplete="new-password"' : '') + '></label>';
    }).join('');
    return head(meta.title, meta.intro) +
      (vals._notice ? '<div class="p-card glass" style="white-space:pre-line;border-color:' + (vals._ok === false ? 'var(--danger)' : 'var(--ok)') + '">' + esc(vals._notice) + '</div>' : '') +
      '<form class="p-card glass" id="sec-form" data-key="' + esc(key) + '"><div class="p-grid">' + fields + '</div>' +
      '<div class="p-row-actions"><button class="btn btn-primary" data-sec-action="save">' + icon('check', 'xs') + 'Сохранить</button>' +
      (meta.actions || []).map(function (a) { return '<button class="btn btn-ghost" data-sec-action="' + esc(a.id) + '">' + esc(a.label) + '</button>'; }).join('') + '</div></form>';
  }

  /* ---------------------------------------------------------------- события */
  function upload(target) {
    var inp = document.createElement('input');
    inp.type = 'file'; inp.accept = 'image/*';
    inp.onchange = function () {
      var f = inp.files[0]; if (!f) return;
      var fd = new FormData(); fd.append('file', f);
      SH.toast('Загружаю…');
      api('admin/upload-image', { method: 'POST', body: fd }).then(function (d) { target.value = d.url; SH.toast('Загружено — не забудьте сохранить'); }).catch(fail);
    };
    inp.click();
  }

  root.addEventListener('click', function (e) {
    var el;
    if ((el = e.target.closest('[data-chat]'))) { openChat(+el.getAttribute('data-chat')); return; }
    if ((el = e.target.closest('[data-status]'))) {
      var st = el.getAttribute('data-status');
      api('admin/status', { method: 'POST', body: { status: st } }).then(function (d) { state.status = d.status; state.online = d.online; refreshShell(); SH.toast(st === 'offline' ? 'Не в сети: студентам сразу предлагается ИИ' : st === 'online' ? 'Вы всегда в сети' : 'Авто: в сети, пока открыта панель'); }).catch(fail);
      return;
    }
    if ((el = e.target.closest('[data-st-chat]'))) { state.active = +el.getAttribute('data-st-chat'); go('chats'); return; }
    if ((el = e.target.closest('[data-st]'))) {
      var doSt = el.getAttribute('data-st'), id = +el.getAttribute('data-id');
      var q = { reset: 'Сгенерировать новый пароль? Старый перестанет работать.', delete: 'Удалить студента вместе с перепиской и файлами? Это необратимо.', disable: 'Заблокировать вход этому студенту?' }[doSt];
      if (q && !confirm(q)) return;
      api('admin/students/' + id, { method: 'POST', body: { do: doSt } }).then(function (d) { if (d.creds) credsSheet(d); loadStudents(); loadChats(); }).catch(fail);
      return;
    }
    if ((el = e.target.closest('[data-rf]'))) { state.reviewFilter = el.getAttribute('data-rf'); render(); return; }
    if ((el = e.target.closest('[data-rv]'))) {
      var rv = el.getAttribute('data-rv');
      if (rv === 'delete' && !confirm('Удалить отзыв?')) return;
      api('admin/reviews/' + el.getAttribute('data-id'), { method: 'POST', body: { do: rv } }).then(function () { loadReviews(); loadChats(); }).catch(fail);
      return;
    }
    if ((el = e.target.closest('[data-upload]'))) { upload(el.parentNode.querySelector('input')); return; }
    if ((el = e.target.closest('[data-sec-action]'))) {
      e.preventDefault();
      var form = el.closest('form'), key = form.getAttribute('data-key'), vals = {};
      form.querySelectorAll('[name]').forEach(function (i) { vals[i.name] = i.type === 'checkbox' ? (i.checked ? 1 : 0) : i.value; });
      el.disabled = true;
      api('admin/sections/' + key, { method: 'POST', body: { values: vals, action: el.getAttribute('data-sec-action') } }).then(function (d) {
        state.section[key] = Object.assign({}, d.values || vals, { _notice: d.notice || 'Сохранено', _ok: d.ok !== false });
        render();
      }).catch(function (ex) { el.disabled = false; fail(ex); });
      return;
    }
    if ((el = e.target.closest('[data-act]'))) {
      var a = el.getAttribute('data-act');
      if (a === 'logout') {
        e.preventDefault();
        api('panel/logout', { method: 'POST' }).catch(function () {}).then(function () { location.reload(); });
      } else if (a === 'more') { e.preventDefault(); moreSheet(); }
      else if (a === 'back') { state.active = 0; document.getElementById('p-chat').classList.remove('open'); renderChatList(); }
      else if (a === 'attach') document.getElementById('p-file').click();
      else if (a === 'review-ask') askReview();
      else if (a === 'add-svc') document.getElementById('svc-rows').insertAdjacentHTML('beforeend', serviceRow());
      else if (a === 'add-uni') document.getElementById('uni-rows').insertAdjacentHTML('beforeend', uniRow());
      else if (a === 'row-del') { if (confirm('Удалить строку?')) el.closest('.p-edit').remove(); }
    }
  });
  /* «Ещё» в нижнем меню живёт в окне, которое вне #panel */
  document.addEventListener('click', function (e) {
    var el = e.target.closest('.overlay [data-act="logout"]');
    if (el) api('panel/logout', { method: 'POST' }).catch(function () {}).then(function () { location.reload(); });
  });

  root.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.id === 'st-new') {
      e.preventDefault();
      var body = { login: f.login.value.trim(), password: f.password.value.trim(), note: f.note.value.trim(), count: +f.count.value || 1 };
      api('admin/students', { method: 'POST', body: body }).then(function (d) { f.reset(); credsSheet(d); loadStudents(); loadChats(); }).catch(fail);
    } else if (f.id === 'cat-form') {
      e.preventDefault();
      api('admin/catalog', { method: 'POST', body: { services: rowsData('svc'), universities: rowsData('uni') } }).then(function (d) {
        state.services = d.services; state.universities = d.universities;
        P.services = d.services.map(function (s) { return { id: s.id, title: s.title_ru, icon: s.icon, color: s.color }; });
        SH.toast('Сохранено'); render();
      }).catch(fail);
    } else if (f.id === 'set-form') {
      e.preventDefault();
      var vals = {};
      f.querySelectorAll('[name]').forEach(function (i) { vals[i.name] = i.type === 'checkbox' ? (i.checked ? 1 : 0) : i.value; });
      api('admin/settings', { method: 'POST', body: { settings: vals } }).then(function (d) {
        var slugChanged = d.settings.panel_slug !== state.settings.panel_slug;
        state.settings = d.settings; P.brand = d.settings.brand_name || P.brand; P.logo = d.settings.brand_logo;
        SH.toast('Настройки сохранены');
        if (slugChanged) { setTimeout(function () { location.href = location.origin + '/' + d.settings.panel_slug + '/#settings'; }, 800); return; }
        render();
      }).catch(fail);
    }
  });

  window.addEventListener('hashchange', function () { render(); window.scrollTo(0, 0); });

  /* ---------------------------------------------------------------- запуск */
  render();
  if (P.logged && P.allowed) {
    loadChats();
    setInterval(function () { if (!document.hidden) loadChats(); }, 6000);
    setInterval(function () { if (!document.hidden && state.active && route() === 'chats') loadMessages(false); }, 3000);
    SH.registerSW(P.swUrl);
  }
})();
