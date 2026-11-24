/* StudyHub — admin chat (wp-admin) */
(function () {
  'use strict';
  var A = window.STUDYHUB_ADMIN || {};
  var root = document.getElementById('studyhub-chat');
  if (!root) return;

  var state = { chats: [], active: +A.open || 0, messages: [], lastId: 0, readUpTo: 0, status: 'auto', online: false, filter: '', sending: false };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function api(path, opts) {
    opts = opts || {};
    var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': A.nonce } };
    if (opts.body instanceof FormData) init.body = opts.body;
    else if (opts.body) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.body); }
    var url = A.rest + (A.rest.indexOf('?') > -1 ? path.replace('?', '&') : path);
    return fetch(url, init).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) {
        if (!r.ok) { var e = new Error(d.message || 'error'); e.status = r.status; throw e; }
        return d;
      });
    });
  }
  function time(ts) {
    if (!ts) return '';
    var d = new Date(ts * 1000), n = new Date();
    var hm = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
    return d.toDateString() === n.toDateString() ? hm : d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }) + ' ' + hm;
  }
  function size(b) { return b < 1048576 ? Math.round(b / 1024) + ' KB' : (b / 1048576).toFixed(1) + ' MB'; }
  function svcTitle(id) {
    for (var i = 0; i < (A.services || []).length; i++) if (A.services[i].id === id) return A.services[i].title;
    return id;
  }

  root.innerHTML =
    '<aside class="sh-list">' +
      '<div class="sh-list-head">' +
        '<div class="sh-status"><span class="sh-dot" id="sh-dot"></span><select id="sh-status" aria-label="Статус">' +
          '<option value="auto">Авто</option><option value="online">В сети</option><option value="offline">Не в сети (ИИ)</option></select></div>' +
        '<input type="search" id="sh-search" placeholder="Поиск: #ID, логин, вуз, заметка">' +
      '</div>' +
      '<div class="sh-items" id="sh-items"></div>' +
    '</aside>' +
    '<section class="sh-conv" id="sh-conv"><div class="sh-empty">Выберите чат слева.<br><a href="' + esc(A.students) + '">Выдать логин новому студенту →</a></div></section>';

  var items = document.getElementById('sh-items');
  var conv = document.getElementById('sh-conv');
  document.getElementById('sh-search').addEventListener('input', function () { state.filter = this.value.toLowerCase(); renderList(); });
  document.getElementById('sh-status').addEventListener('change', function () {
    api('admin/status', { method: 'POST', body: { status: this.value } }).then(function (d) { state.status = d.status; state.online = d.online; renderStatus(); });
  });

  function renderStatus() {
    document.getElementById('sh-status').value = state.status;
    document.getElementById('sh-dot').className = 'sh-dot' + (state.online ? ' on' : '');
  }

  function loadChats() {
    return api('admin/chats').then(function (d) {
      state.chats = d.chats; state.status = d.status; state.online = d.online;
      renderStatus();
      renderList();
      var total = state.chats.reduce(function (s, c) { return s + c.unread; }, 0);
      document.title = (total ? '(' + total + ') ' : '') + 'Чаты — StudyHub';
    });
  }

  function renderList() {
    var f = state.filter;
    var list = state.chats.filter(function (c) {
      if (!f) return true;
      return (c.alias + ' ' + c.login + ' ' + c.university + ' ' + c.note).toLowerCase().indexOf(f) > -1;
    });
    if (!list.length) { items.innerHTML = '<p class="sh-muted" style="padding:16px">Нет чатов.</p>'; return; }
    items.innerHTML = list.map(function (c) {
      return '<button class="sh-item' + (c.id === state.active ? ' active' : '') + (c.disabled ? ' off' : '') + '" data-id="' + c.id + '">' +
        '<span class="sh-ava">' + esc(c.alias.replace('#', '').slice(0, 2)) + '</span>' +
        '<span class="sh-item-body"><span class="sh-item-top"><b>' + esc(c.alias) + '</b>' + (c.university ? '<span class="sh-tag">' + esc(c.university) + '</span>' : '') + '<span class="sh-time">' + time(c.lastAt) + '</span></span>' +
        '<span class="sh-item-prev">' + esc(c.preview || (c.note ? '📝 ' + c.note : 'Нет сообщений')) + '</span></span>' +
        (c.unread ? '<span class="sh-badge">' + c.unread + '</span>' : '') + '</button>';
    }).join('');
  }

  items.addEventListener('click', function (e) {
    var b = e.target.closest('.sh-item');
    if (!b) return;
    openChat(+b.getAttribute('data-id'));
  });

  function current() {
    for (var i = 0; i < state.chats.length; i++) if (state.chats[i].id === state.active) return state.chats[i];
    return null;
  }

  function openChat(id) {
    state.active = id; state.messages = []; state.lastId = 0;
    root.classList.add('has-active');
    if (history.replaceState) history.replaceState(null, '', location.pathname + '?page=studyhub&chat=' + id);
    var c = current();
    var svcOpts = (A.services || []).map(function (s) { return '<option value="' + esc(s.id) + '">' + esc(s.title) + '</option>'; }).join('');
    conv.innerHTML =
      '<header class="sh-conv-head">' +
        '<button class="button sh-back" type="button">←</button>' +
        '<div class="sh-conv-title"><b>' + esc(c ? c.alias : '#' + id) + '</b>' +
          '<span class="sh-muted">' + esc(c ? ('логин: ' + c.login + (c.university ? ' · ' + c.university : '') + (c.lastSeen ? ' · был(а) ' + time(c.lastSeen) : '')) : '') + '</span></div>' +
        '<div class="sh-conv-actions">' +
          '<select id="sh-rev-svc" title="Услуга для отзыва"><option value="">Услуга…</option>' + svcOpts + '</select>' +
          '<button class="button" id="sh-review" type="button">⭐ Попросить отзыв</button>' +
        '</div>' +
      '</header>' +
      '<div class="sh-note"><label>📝 Заметка (видите только вы): <input type="text" id="sh-note" value="' + esc(c ? c.note : '') + '" placeholder="что заказал, сроки, цена…"></label></div>' +
      '<div class="sh-msgs" id="sh-msgs"><p class="sh-muted" style="text-align:center">Загрузка…</p></div>' +
      '<form class="sh-composer" id="sh-form">' +
        '<input type="file" id="sh-file" hidden>' +
        '<button type="button" class="button sh-attach" id="sh-attach" title="Прикрепить файл">📎</button>' +
        '<textarea id="sh-input" rows="1" placeholder="Ответ от имени «' + esc(A.brand) + '» (Enter — отправить, Shift+Enter — новая строка)"></textarea>' +
        '<button class="button button-primary" id="sh-send">Отправить</button>' +
      '</form>';

    conv.querySelector('.sh-back').addEventListener('click', function () { root.classList.remove('has-active'); });
    var input = document.getElementById('sh-input');
    input.addEventListener('input', function () { input.style.height = 'auto'; input.style.height = Math.min(180, input.scrollHeight) + 'px'; });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
    });
    document.getElementById('sh-form').addEventListener('submit', function (e) { e.preventDefault(); send(); });
    document.getElementById('sh-attach').addEventListener('click', function () { document.getElementById('sh-file').click(); });
    document.getElementById('sh-file').addEventListener('change', function () {
      var f = this.files[0];
      this.value = '';
      if (f) send(f);
    });
    document.getElementById('sh-review').addEventListener('click', function () {
      if (!confirm('Отправить студенту карточку «Оцените нашу работу»?')) return;
      api('admin/chats/' + state.active + '/review-request', { method: 'POST', body: { service: document.getElementById('sh-rev-svc').value } }).then(function () { loadMessages(); });
    });
    var noteTimer;
    document.getElementById('sh-note').addEventListener('input', function () {
      var v = this.value;
      clearTimeout(noteTimer);
      noteTimer = setTimeout(function () {
        api('admin/chats/' + state.active + '/note', { method: 'POST', body: { note: v } });
        var cc = current(); if (cc) cc.note = v;
      }, 600);
    });
    renderList();
    loadMessages(true);
    input.focus();
  }

  function loadMessages(initial) {
    if (!state.active) return Promise.resolve();
    var id = state.active;
    return api('admin/chats/' + id + '/messages?after=' + (initial ? 0 : state.lastId)).then(function (d) {
      if (id !== state.active) return;
      var added = initial;
      if (initial) state.messages = d.messages;
      else d.messages.forEach(function (m) { if (!state.messages.some(function (x) { return x.id === m.id; })) { state.messages.push(m); added = true; } });
      state.messages.forEach(function (m) { state.lastId = Math.max(state.lastId, m.id); });
      var changedRead = state.readUpTo !== d.readUpTo;
      state.readUpTo = d.readUpTo;
      if (added || changedRead) renderMessages(added);
      var c = current();
      if (c && c.unread) { c.unread = 0; renderList(); }
    });
  }

  function renderMessages(scroll) {
    var box = document.getElementById('sh-msgs');
    if (!box) return;
    var near = box.scrollHeight - box.scrollTop - box.clientHeight < 150;
    if (!state.messages.length) {
      box.innerHTML = '<p class="sh-muted" style="text-align:center;margin-top:40px">Сообщений пока нет. Студент видит приветствие из настроек.</p>';
      return;
    }
    box.innerHTML = state.messages.map(function (m) {
      var mine = m.from !== 'student';
      var inner = '';
      if (m.meta && m.meta.service && m.type !== 'review_request') inner += '<span class="sh-svc">' + esc(svcTitle(m.meta.service)) + '</span>';
      if (m.type === 'review_request') {
        inner += '<div class="sh-bubble sh-sys">⭐ Запрос отзыва' + (m.meta && m.meta.service ? ' · ' + esc(svcTitle(m.meta.service)) : '') + ' — ' + (m.meta && m.meta.done ? 'оставлен (' + (m.meta.rating || '') + '★)' : 'ожидает') + '</div>';
      } else if (m.type === 'review') {
        inner += '<div class="sh-bubble sh-sys">Отзыв: ' + '★'.repeat((m.meta && m.meta.rating) || 5) + '<br>' + esc(m.text) + '<br><a href="admin.php?page=studyhub-reviews">Модерация →</a></div>';
      } else {
        if (m.file) {
          if (m.file.isImage) inner += '<a href="' + esc(m.file.url) + '" target="_blank" class="sh-img"><img src="' + esc(m.file.url) + '" alt="" loading="lazy"></a>';
          else inner += '<a class="sh-file" href="' + esc(m.file.url) + '&download=1">📄 ' + esc(m.file.name) + ' <span class="sh-muted">' + size(m.file.size) + '</span></a>';
        }
        if (m.text) inner += '<div class="sh-bubble">' + esc(m.text).replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>') + '</div>';
      }
      var tick = mine && m.type !== 'review_request' ? (m.id <= state.readUpTo ? ' <span class="sh-read">✓✓</span>' : ' ✓') : '';
      return '<div class="sh-msg ' + (mine ? 'mine' : 'theirs') + '">' + inner + '<span class="sh-meta">' + time(m.time) + tick + '</span></div>';
    }).join('');
    if (scroll || near) box.scrollTop = box.scrollHeight;
  }

  function send(file) {
    var input = document.getElementById('sh-input');
    var text = input.value.trim();
    if ((!text && !file) || state.sending) return;
    state.sending = true;
    var fd = new FormData();
    fd.append('text', file ? '' : text);
    if (file) fd.append('file', file);
    var btn = document.getElementById('sh-send');
    btn.disabled = true;
    api('admin/chats/' + state.active + '/messages', { method: 'POST', body: fd }).then(function (d) {
      if (!file) { input.value = ''; input.style.height = 'auto'; }
      state.messages.push(d.message);
      state.lastId = Math.max(state.lastId, d.message.id);
      renderMessages(true);
    }).catch(function (e) {
      alert(e.status === 415 ? 'Этот тип файла не разрешён.' : e.status === 413 ? 'Файл слишком большой (см. Настройки).' : 'Не удалось отправить: ' + e.message);
    }).then(function () { state.sending = false; btn.disabled = false; input.focus(); });
  }

  // Polling.
  loadChats().then(function () { if (state.active) openChat(state.active); });
  setInterval(function () { if (!document.hidden) loadChats(); }, 6000);
  setInterval(function () { if (!document.hidden && state.active) loadMessages(false); }, 3000);
})();
