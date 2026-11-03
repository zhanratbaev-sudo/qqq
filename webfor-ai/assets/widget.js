/* Окно AI-ассистента WebFor. Настройки приходят в window.WFAI (имя, приветствие, адреса REST).
   WFAI.transport (необязательно) — своя функция отправки, например для демо без сервера. */
(function () {
  var C = window.WFAI;
  if (!C || document.querySelector('.wfai')) return;
  // разговор хранится на сервере; здесь — его токен, номер последнего полученного сообщения и копия для показа
  var KEY = 'wfai-chat';
  var st = { chat: '', last: 0, status: 'bot', msgs: [] };
  try { st = Object.assign(st, JSON.parse(localStorage.getItem(KEY) || '{}')); } catch (e) {}
  var history = st.msgs;
  var pendingQuestion = 0, busy = false, contactAsked = false;

  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  var ICON_X = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
  var ICON_SEND = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

  var root = el('div', 'wfai');
  var launcher = el('button', 'wfai-launcher');
  launcher.type = 'button'; launcher.setAttribute('aria-label', 'Открыть чат с ассистентом'); launcher.setAttribute('aria-expanded', 'false');
  // «живая» иконка: облачко с глазами; рука, мысли и прыжок включаются по таймеру (см. idle ниже)
  var ICON_BOT = '<svg class="wfai-bot" width="30" height="30" viewBox="0 0 32 32" aria-hidden="true">'
    + '<g class="wfai-bot-think"><circle cx="27.4" cy="3" r="2.1"/><circle cx="31.2" cy="-0.6" r="1.6"/><circle cx="34" cy="-3.4" r="1.1"/></g>'
    + '<g class="wfai-bot-body"><path d="M16 4.5c6.6 0 11.5 4.6 11.5 10.4S22.6 25.3 16 25.3c-1.5 0-2.9-.2-4.2-.6L5.6 27.4l1.6-5.2C5.3 20.3 4.5 17.8 4.5 14.9 4.5 9.1 9.4 4.5 16 4.5z"/>'
    + '<g class="wfai-bot-eyes"><rect class="wfai-eye" x="10.6" y="11.6" width="3.2" height="5.2" rx="1.6"/><rect class="wfai-eye" x="18.2" y="11.6" width="3.2" height="5.2" rx="1.6"/></g>'
    + '<path class="wfai-bot-smile" d="M13.2 19.6q2.8 2 5.6 0"/></g>'
    + '<g class="wfai-bot-hand"><path class="wfai-arm" d="M26.2 18.4 29.4 14.4"/><rect x="27.8" y="7.2" width="6.6" height="6.6" rx="2.6"/><rect x="28.1" y="3.6" width="1.7" height="5" rx=".85"/><rect x="30.25" y="3" width="1.7" height="5.4" rx=".85"/><rect x="32.4" y="3.6" width="1.7" height="5" rx=".85"/><rect x="26.4" y="8.6" width="1.9" height="4" rx=".95"/></g>'
    + '</svg>';
  launcher.innerHTML = ICON_BOT + '<span>Спросить AI</span>';

  var panel = el('section', 'wfai-panel'); panel.setAttribute('aria-label', C.name || 'Ассистент'); panel.hidden = true;
  var head = el('header', 'wfai-head');
  var who = el('div', 'wfai-who');
  who.appendChild(el('span', 'wfai-dot'));
  var whoText = el('div'); whoText.appendChild(el('b', null, C.name || 'Ассистент')); whoText.appendChild(el('span', null, 'Отвечает сразу · сложное передаёт владельцу'));
  who.appendChild(whoText);
  var close = el('button', 'wfai-close'); close.type = 'button'; close.setAttribute('aria-label', 'Закрыть чат'); close.innerHTML = ICON_X;
  head.appendChild(who); head.appendChild(close);

  var log = el('div', 'wfai-log'); log.setAttribute('role', 'log'); log.setAttribute('aria-live', 'polite');
  var chips = el('div', 'wfai-chips');
  (C.chips || []).forEach(function (t) {
    var b = el('button', 'wfai-chip', t); b.type = 'button';
    b.addEventListener('click', function () { send(t); });
    chips.appendChild(b);
  });
  var form = el('form', 'wfai-form');
  var input = el('textarea', 'wfai-input'); input.rows = 1; input.maxLength = 1500; input.placeholder = 'Ваш вопрос…'; input.setAttribute('aria-label', 'Ваш вопрос');
  var sendBtn = el('button', 'wfai-send'); sendBtn.type = 'submit'; sendBtn.setAttribute('aria-label', 'Отправить'); sendBtn.innerHTML = ICON_SEND;
  form.appendChild(input); form.appendChild(sendBtn);
  var foot = el('p', 'wfai-foot', 'Ответы даёт AI. Важное уточняйте у менеджера.');

  panel.appendChild(head); panel.appendChild(log); panel.appendChild(chips); panel.appendChild(form); panel.appendChild(foot);
  root.appendChild(panel); root.appendChild(launcher);
  document.body.appendChild(root);
  if (document.querySelector('.wf-mobile-bar')) root.classList.add('wfai-has-bar');

  function bubble(role, text, author, err) {
    var cls = role === 'user' ? 'user' : role === 'system' ? 'system' : role === 'manager' ? 'assistant wfai-manager' : 'assistant';
    var b = el('div', 'wfai-msg wfai-' + cls + (err ? ' wfai-error' : ''));
    if (role === 'manager') b.appendChild(el('b', 'wfai-author', author || 'Менеджер'));
    b.appendChild(document.createTextNode(text));
    log.appendChild(b); log.scrollTop = log.scrollHeight;
    return b;
  }
  function save() { st.msgs = history.slice(-40); try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) {} }
  function render() {
    log.innerHTML = '';
    bubble('assistant', C.greeting || 'Здравствуйте! Чем помочь?');
    history.forEach(function (m) { bubble(m.role, m.content, m.author, m.err); });
    chips.hidden = history.length > 0;
  }
  // сообщения с сервера: ассистент, менеджер, служебные («Менеджер присоединился…»)
  function addServer(list) {
    (list || []).forEach(function (m) {
      if (m.i <= st.last) return;
      st.last = m.i;
      var role = m.r === 'bot' ? 'assistant' : m.r;
      history.push({ role: role, content: m.t, author: m.a });
      if (log.childElementCount) bubble(role, m.t, m.a);
    });
    save();
  }
  function contactForm() {
    var f = el('form', 'wfai-contact');
    var i = el('input'); i.type = 'tel'; i.placeholder = 'Ваш WhatsApp: +7 700 000 00 00'; i.setAttribute('aria-label', 'Ваш номер WhatsApp для ответа'); i.autocomplete = 'tel';
    var b = el('button', null, 'Отправить'); b.type = 'submit';
    f.appendChild(i); f.appendChild(b);
    if (C.wa) { var a = el('a', 'wfai-wa', 'или написать в WhatsApp'); a.href = C.wa; a.target = '_blank'; a.rel = 'noopener'; f.appendChild(a); }
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var v = i.value.trim();
      if (v.replace(/\D/g, '').length < 10) { i.setAttribute('aria-invalid', 'true'); i.focus(); return; }
      b.disabled = true;
      post(C.contact, { question_id: pendingQuestion, contact: v }).then(function (r) {
        f.remove(); bubble('assistant', r.reply || 'Спасибо!');
        if (r.ok) pendingQuestion = 0;
      }).catch(function () { b.disabled = false; });
    });
    log.appendChild(f); log.scrollTop = log.scrollHeight;
    i.focus();
  }

  function post(url, body) {
    if (C.transport) return C.transport(url, body);
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      .then(function (r) { return r.json(); });
  }

  function send(text) {
    text = (text || '').trim();
    if (!text || busy) return;
    busy = true; chips.hidden = true;
    history.push({ role: 'user', content: text }); save();
    bubble('user', text);
    input.value = ''; autosize();
    var live = st.status === 'live';
    var typing = null;
    if (!live) { typing = el('div', 'wfai-msg wfai-assistant wfai-typing'); typing.innerHTML = '<i></i><i></i><i></i>'; typing.setAttribute('aria-label', 'Ассистент печатает'); log.appendChild(typing); log.scrollTop = log.scrollHeight; }
    var body = C.transport ? { messages: history.filter(function (m) { return m.role === 'user' || m.role === 'assistant'; }).map(function (m) { return { role: m.role, content: m.content }; }) } : { chat: st.chat, text: text };
    post(C.chat, body).then(function (r) {
      if (typing) typing.remove();
      r = r || {};
      if (r.chat) st.chat = r.chat;
      if (r.user_i) st.last = Math.max(st.last, r.user_i);
      if (r.status) st.status = r.status;
      if (r.msgs) addServer(r.msgs);
      else if (r.reply) { history.push({ role: 'assistant', content: r.reply, err: !!r.unsupported }); bubble('assistant', r.reply, '', !!r.unsupported); }
      else if (!live) bubble('assistant', 'Не получилось ответить. Напишите нам в WhatsApp.');
      if (r.unsupported && r.msgs) { var lastB = log.querySelector('.wfai-msg:last-of-type'); if (lastB) lastB.classList.add('wfai-error'); history[history.length - 1].err = true; }
      save();
      if (r.escalated && r.question_id && !contactAsked && st.status !== 'live') { pendingQuestion = r.question_id; contactAsked = true; contactForm(); }
      schedulePoll();
    }).catch(function () {
      if (typing) typing.remove();
      bubble('assistant', 'Нет связи с сервером. Попробуйте ещё раз или напишите нам в WhatsApp.');
    }).then(function () { busy = false; });
  }

  // новые сообщения от менеджера: часто, пока чат открыт; реже, если менеджер ждёт или в чате, а окно закрыто
  var pollTimer = null;
  function poll() {
    pollTimer = null;
    if (!st.chat || !C.poll || C.transport) return;
    fetch(C.poll + (C.poll.indexOf('?') >= 0 ? '&' : '?') + 'chat=' + encodeURIComponent(st.chat) + '&after=' + st.last)
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (d && d.ok) {
          var before = history.length;
          if (d.status) st.status = d.status;
          addServer(d.msgs);
          if (history.length > before && panel.hidden) root.classList.add('wfai-unread');
        }
      }).catch(function () {}).then(schedulePoll);
  }
  function schedulePoll() {
    if (pollTimer || !st.chat || C.transport) return;
    var active = st.status === 'waiting' || st.status === 'live';
    if (panel.hidden && !active) return;
    pollTimer = setTimeout(poll, panel.hidden ? 8000 : (active ? 2500 : 6000));
  }

  function autosize() { input.style.height = 'auto'; input.style.height = Math.min(120, input.scrollHeight) + 'px'; }
  // открытие: кнопка «вырастает» в окно чата, зелёный цвет растворяется в стекле; закрытие — обратно
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var animating = false;
  // окно обрезается clip-path, поэтому скругление всегда настоящее: прямоугольник плавно становится кругом, а не «прыгает»
  function clipTo(r, big, radius) {
    return 'inset(' + Math.max(0, r.top - big.top) + 'px ' + Math.max(0, big.right - r.right) + 'px ' + Math.max(0, big.bottom - r.bottom) + 'px ' + Math.max(0, r.left - big.left) + 'px round ' + radius + 'px)';
  }
  function morph(btnRect, opening, done) {
    var big = panel.getBoundingClientRect();
    var accent = getComputedStyle(launcher).backgroundColor, glass = getComputedStyle(panel).backgroundColor;
    var full = 'inset(0px 0px 0px 0px round 0px)';
    panel.classList.add('is-morph');
    var anim;
    if (opening) {
      anim = panel.animate([
        { clipPath: clipTo(btnRect, big, btnRect.height / 2), backgroundColor: accent },
        { clipPath: full, backgroundColor: glass },
      ], { duration: 520, easing: 'cubic-bezier(.2,.9,.25,1)' });
    } else {
      // закрытие: окно сжимается, заливается цветом сайта и в конце становится кругом на месте кнопки
      var d = btnRect.height, cx = btnRect.right - d / 2, cy = btnRect.top + d / 2;
      var mid = { top: big.top + (cy - big.top) * .55, bottom: big.bottom + (cy - big.bottom) * .55, left: big.left + (cx - big.left) * .55, right: big.right + (cx - big.right) * .55 };
      var circle = { top: cy - d / 2, bottom: cy + d / 2, left: cx - d / 2, right: cx + d / 2 };
      anim = panel.animate([
        { clipPath: full, backgroundColor: glass, offset: 0 },
        { clipPath: clipTo(mid, big, 40), backgroundColor: accent, offset: .45 },
        { clipPath: clipTo(circle, big, d / 2), backgroundColor: accent, offset: 1 },
      ], { duration: 560, easing: 'cubic-bezier(.55,0,.25,1)' });
    }
    anim.onfinish = function () { panel.classList.remove('is-morph'); done(); };
  }
  // кнопка появляется после закрытия: круг «раскрывается» в кнопку, иконка выпрыгивает и моргает
  function popLauncher() {
    if (reduce || !launcher.animate) return;
    var r = launcher.getBoundingClientRect(), d = r.height;
    launcher.animate([
      { clipPath: 'inset(0px 0px 0px ' + (r.width - d) + 'px round ' + d / 2 + 'px)' },
      { clipPath: 'inset(0px 0px 0px 0px round ' + d / 2 + 'px)' },
    ], { duration: 420, easing: 'cubic-bezier(.2,.9,.25,1)' });
    var bot = launcher.querySelector('.wfai-bot');
    bot.animate([
      { transform: 'scale(.2) rotate(-25deg)', opacity: 0 },
      { transform: 'scale(1.18) rotate(6deg)', opacity: 1, offset: .6 },
      { transform: 'scale(1) rotate(0)', opacity: 1 },
    ], { duration: 520, easing: 'cubic-bezier(.3,1.4,.5,1)' });
    var label = launcher.querySelector('span');
    if (label) label.animate([{ opacity: 0, transform: 'translateX(-6px)' }, { opacity: 1, transform: 'none' }], { duration: 300, delay: 160, fill: 'backwards', easing: 'ease-out' });
    setTimeout(function () { act('blink'); }, 560);
  }

  /* иконка «живёт»: моргает, оглядывается, иногда машет рукой, думает или подпрыгивает */
  var ACTS = { blink: 450, look: 1600, wave: 2000, think: 2400, hop: 700, happy: 1300 };
  var actTimer = 0;
  function act(name) {
    if (reduce || root.classList.contains('is-open')) return;
    Object.keys(ACTS).forEach(function (k) { launcher.classList.remove('is-' + k); });
    void launcher.offsetWidth; // перезапуск анимации
    launcher.classList.add('is-' + name);
    clearTimeout(actTimer);
    actTimer = setTimeout(function () { launcher.classList.remove('is-' + name); }, ACTS[name]);
  }
  (function idle() {
    var bag = ['blink', 'blink', 'blink', 'look', 'look', 'wave', 'think', 'hop', 'happy'];
    setTimeout(function () { if (!document.hidden) act(bag[Math.floor(Math.random() * bag.length)]); idle(); }, 3500 + Math.random() * 4500);
  })();
  setTimeout(function () { act('wave'); }, 2500); // первое приветствие
  var lockY = null;
  function setOpen(open) {
    if (animating || open === !panel.hidden) return;
    launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
    // сайт под открытым чатом не прокручивается и после закрытия остаётся на том же месте
    if (open) lockY = window.scrollY;
    document.documentElement.classList.toggle('wfai-lock', open);
    if (!open && lockY != null) { window.scrollTo({ top: lockY, behavior: 'instant' }); lockY = null; }
    if (open) {
      if (!log.childElementCount) render();
      var btnRect = launcher.getBoundingClientRect(); // до раскрытия: на телефоне контейнер чата потом растягивается на весь экран
      panel.hidden = false; root.classList.add('is-open');
      root.classList.remove('wfai-unread');
      if (st.chat && !C.transport) { clearTimeout(pollTimer); pollTimer = null; poll(); }
      if (!reduce && panel.animate) { animating = true; morph(btnRect, true, function () { animating = false; }); }
      setTimeout(function () { input.focus({ preventScroll: true }); }, 60);
    } else {
      var finish = function () { panel.hidden = true; root.classList.remove('is-open'); animating = false; launcher.focus({ preventScroll: true }); };
      if (!reduce && panel.animate) {
        animating = true; root.classList.add('is-closing'); root.classList.remove('is-open');
        morph(launcher.getBoundingClientRect(), false, function () { root.classList.remove('is-closing'); finish(); popLauncher(); });
      }
      else finish();
    }
  }

  launcher.addEventListener('click', function () { setOpen(panel.hidden); });
  close.addEventListener('click', function () { setOpen(false); });
  form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
  input.addEventListener('input', autosize);
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(input.value); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) setOpen(false); });
  // любые ссылки/кнопки с data-wfai-open открывают чат (например, «Спросить AI» на сайте)
  document.addEventListener('click', function (e) {
    var t = e.target.closest && e.target.closest('[data-wfai-open]');
    if (t) { e.preventDefault(); setOpen(true); var q = t.getAttribute('data-wfai-open'); if (q) send(q); }
  });
  schedulePoll();
})();
