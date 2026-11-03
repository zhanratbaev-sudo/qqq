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
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- «живая» иконка ассистента (по эскизу владельца) ----------
     Тело — круг с хвостиком, большие глаза с бликами, рот-полукруг. Рука отделена от тела ровным зазором:
     её внутренний край — дуга, параллельная телу, а взмах — поворот вокруг центра тела, поэтому зазор всегда одинаковый.
     «Думает»: глаза и рот становятся тремя точками, точки по очереди опускаются и поднимаются волной.
     Эмоции: моргнуть, оглядеться, помахать, недовольный взгляд на сайт, радость, удивление. Всё через Web Animations. */
  var BOT_SVG = '<svg class="wfai-bot" viewBox="0 0 32 32" aria-hidden="true">'
    + '<g class="b-hand"><path class="b-handp" d="M21.2 5.0L23.0 3.0L24.1 2.6L25.1 2.4L26.1 2.4L27.0 2.6L27.8 2.8L28.6 3.2L29.4 3.7L30.0 4.2L30.7 4.8L31.2 5.5L31.7 6.2L32.1 6.9L32.5 7.7L32.7 8.5L32.9 9.4L33.0 10.2L33.0 11.1L32.9 12.0L32.6 12.9L32.2 13.7L31.6 14.6L30.7 15.4L28.1 16.1A13.1 13.1 0 0 0 21.2 5.0Z"/></g>'
    + '<g class="b-body"><circle class="b-skin" cx="15" cy="16.6" r="10.4"/><path class="b-skin" d="M6.2 21.4C6 24.5 5 27 3.6 28.8C3.2 29.4 3.8 30 4.5 29.7C7 28.6 9.4 27.4 11.8 26.2Z"/>'
    + '<g class="b-look"><g class="b-blink">'
    + '<g class="b-eL"><g class="b-eLm"><ellipse class="b-eye" cx="11.2" cy="15.2" rx="2.2" ry="3.2"/><circle class="b-hl" cx="11.95" cy="13.95" r=".95"/><circle class="b-hl" cx="10.45" cy="16.65" r=".42"/></g></g>'
    + '<g class="b-eR"><g class="b-eRm"><ellipse class="b-eye" cx="18.8" cy="15.2" rx="2.2" ry="3.2"/><circle class="b-hl" cx="19.55" cy="13.95" r=".95"/><circle class="b-hl" cx="18.05" cy="16.65" r=".42"/></g></g>'
    + '</g></g>'
    + '<path class="b-hap" d="M9.2 15.8Q11.2 12.6 13.2 15.8M16.8 15.8Q18.8 12.6 20.8 15.8"/>'
    + '<path class="b-smile" d="M12.4 20.2H17.6A2.6 2.6 0 0 1 12.4 20.2Z"/>'
    + '<path class="b-flat" d="M12.8 22Q15.6 19.6 18.4 20Q16 21.6 12.8 22Z"/>'
    + '<ellipse class="b-o" cx="15" cy="21.2" rx="1.4" ry="1.7"/>'
    + '<g class="b-d2w"><circle class="b-d2" cx="15" cy="14.2" r="1.75"/></g>'
    + '</g></svg>';

  function makeBot() {
    var box = document.createElement('div'); box.innerHTML = BOT_SVG;
    var svg = box.firstChild;
    function q(c) { return svg.querySelector('.' + c); }
    var E = { hand: q('b-hand'), handp: q('b-handp'), look: q('b-look'), blink: q('b-blink'), eL: q('b-eL'), eR: q('b-eR'), eLm: q('b-eLm'), eRm: q('b-eRm'),
      hap: q('b-hap'), smile: q('b-smile'), flat: q('b-flat'), o: q('b-o'), d2w: q('b-d2w'), d2: q('b-d2') };
    var hls = svg.querySelectorAll('.b-hl');
    var all = Object.keys(E).map(function (k) { return E[k]; }).concat([].slice.call(hls));
    var can = !!svg.animate && !reduce;
    var state = 'idle', busyUntil = 0;
    function an(elm, frames, opt) {
      if (!can) return null;
      opt = Object.assign({ duration: 300, easing: 'cubic-bezier(.3,1.2,.5,1)', fill: 'forwards' }, opt);
      // в многошаговой анимации плавность задаём каждому шагу, иначе она «сжимает» всю анимацию по времени
      if (frames.length > 2) { var e = opt.easing; frames = frames.map(function (f) { return f.easing ? f : Object.assign({ easing: e }, f); }); opt.easing = 'linear'; }
      return elm.animate(frames, opt);
    }
    // плавно вернуть всё в обычное лицо из любого состояния
    function settle(dur) {
      if (!can) return;
      all.forEach(function (n) {
        var cs = getComputedStyle(n), t = cs.transform, o = cs.opacity;
        n.getAnimations().forEach(function (a) { a.cancel(); });
        var base = getComputedStyle(n).opacity;
        if (t !== 'none' || o !== base) n.animate([{ transform: t === 'none' ? 'none' : t, opacity: o }, { transform: 'none', opacity: base }], { duration: dur || 260, easing: 'cubic-bezier(.3,1.1,.5,1)' });
      });
    }
    // эмоция «туда и обратно»: держим позу, потом возвращаемся
    function hold(n, pose, dur, hIn, hOut) {
      var base = { transform: 'none', opacity: getComputedStyle(n).opacity };
      var p = Object.assign({}, base, pose);
      return an(n, [base, Object.assign({ offset: hIn || .14 }, p), Object.assign({ offset: hOut || .84 }, p), base], { duration: dur, fill: 'none', easing: 'ease-in-out' });
    }
    var ONE = {
      blink: function () { an(E.blink, [{ transform: 'scaleY(1)' }, { transform: 'scaleY(.08)', offset: .5 }, { transform: 'scaleY(1)' }], { duration: 240, fill: 'none', easing: 'ease-in-out' }); return 260; },
      look: function () {
        an(E.look, [{ transform: 'none' }, { transform: 'translate(-1.8px,0)', offset: .2 }, { transform: 'translate(-1.8px,0)', offset: .42 }, { transform: 'translate(1.8px,-.3px)', offset: .6 }, { transform: 'translate(1.8px,-.3px)', offset: .84 }, { transform: 'none' }], { duration: 1800, fill: 'none', easing: 'ease-in-out' });
        return 1800;
      },
      wave: function () {
        an(E.handp, [{ opacity: 0, transform: 'scale(.35)' }, { opacity: 1, transform: 'scale(1)', offset: .12 }, { opacity: 1, transform: 'scale(1)', offset: .88 }, { opacity: 0, transform: 'scale(.35)' }], { duration: 2200, fill: 'none', easing: 'cubic-bezier(.3,1.3,.5,1)' });
        an(E.hand, [{ transform: 'rotate(0)' }, { transform: 'rotate(0)', offset: .1 }, { transform: 'rotate(-16deg)', offset: .25 }, { transform: 'rotate(12deg)', offset: .4 }, { transform: 'rotate(-16deg)', offset: .55 }, { transform: 'rotate(12deg)', offset: .7 }, { transform: 'rotate(0)', offset: .85 }, { transform: 'rotate(0)' }], { duration: 2200, fill: 'none', easing: 'ease-in-out' });
        hold(E.smile, { transform: 'scale(1.15)' }, 2200);
        return 2200;
      },
      // недовольный взгляд в сторону сайта: один глаз прищурен, рот — косая полоска
      grumpy: function () {
        hold(E.look, { transform: 'translate(-1.9px,.3px)' }, 2600);
        hold(E.eLm, { transform: 'scale(1.02,.8)' }, 2600);
        hold(E.eRm, { transform: 'translate(0,1px) scale(1.15,.42)' }, 2600);
        hold(E.smile, { opacity: 0, transform: 'scale(.6)' }, 2600, .1, .88);
        hold(E.flat, { opacity: 1 }, 2600, .16, .82);
        return 2600;
      },
      happy: function () {
        hold(E.eLm, { transform: 'scaleY(.1)', opacity: 0 }, 1500, .12, .86);
        hold(E.eRm, { transform: 'scaleY(.1)', opacity: 0 }, 1500, .12, .86);
        hold(E.hap, { opacity: 1 }, 1500, .16, .82);
        hold(E.smile, { transform: 'scale(1.25,1.3)' }, 1500);
        return 1500;
      },
      surprised: function () {
        hold(E.eLm, { transform: 'scale(1.14)' }, 1400);
        hold(E.eRm, { transform: 'scale(1.14)' }, 1400);
        hold(E.smile, { opacity: 0, transform: 'scale(.5)' }, 1400, .1, .86);
        hold(E.o, { opacity: 1 }, 1400, .16, .82);
        return 1400;
      },
      think: function () { setState('think'); setTimeout(function () { if (state === 'think') setState('idle'); }, 3600); return 3600; },
    };
    function act(name) {
      if (!can || state !== 'idle' || Date.now() < busyUntil) return;
      busyUntil = Date.now() + ONE[name]();
    }
    // «думает»: глаза и рот → три точки (1 выше всех, 2 ниже, 3 по центру), потом волна 3→2→1
    function think() {
      settle(120);
      var ease = 'cubic-bezier(.3,1.1,.5,1)';
      an(E.eLm, [{ transform: 'none' }, { transform: 'translate(-.8px,-3.6px) scale(.8,.55)' }], { duration: 320, easing: ease });
      an(E.eRm, [{ transform: 'none' }, { transform: 'translate(.8px,1.2px) scale(.8,.55)' }], { duration: 320, easing: ease });
      [].forEach.call(hls, function (h) { an(h, [{ opacity: 1 }, { opacity: 0 }], { duration: 160 }); });
      an(E.smile, [{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'scale(.4)' }], { duration: 220 });
      an(E.d2, [{ opacity: 0, transform: 'translateY(7.2px) scale(.5)' }, { opacity: 1, transform: 'none' }], { duration: 360, easing: ease });
      var dots = [[E.eL, 5.2, 300], [E.d2w, 2.6, 150], [E.eR, .4, 0]]; // [точка, насколько опуститься до общего уровня, задержка волны]
      dots.forEach(function (d, i) {
        an(d[0], [{ transform: 'none' }, { transform: 'translateY(' + d[1] + 'px)' }], { duration: 300, delay: 850 + i * 230, easing: 'cubic-bezier(.4,0,.2,1)' });
        an(d[0], [{ transform: 'translateY(' + d[1] + 'px)' }, { transform: 'translateY(' + (d[1] - 3.4) + 'px)', offset: .28 }, { transform: 'translateY(' + d[1] + 'px)', offset: .56 }, { transform: 'translateY(' + d[1] + 'px)' }],
          { duration: 1200, delay: 1900 + d[2], iterations: Infinity, easing: 'ease-in-out', fill: 'none' });
      });
    }
    function setState(s) {
      if (s === state) return;
      state = s;
      if (!can) return;
      if (s === 'think') think();
      else if (s === 'down') { settle(120); an(E.look, [{ transform: 'none' }, { transform: 'translate(-.4px,1.9px) scaleY(.92)' }], { duration: 280 }); }
      else settle(300);
    }
    return { el: svg, act: act, set: setState, get state() { return state; } };
  }
  var ICON_X = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
  var ICON_SEND = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

  var root = el('div', 'wfai');
  var launcher = el('button', 'wfai-launcher');
  launcher.type = 'button'; launcher.setAttribute('aria-label', 'Открыть чат с ассистентом'); launcher.setAttribute('aria-expanded', 'false');
  var lBot = makeBot(); launcher._bot = lBot; launcher.appendChild(lBot.el); launcher.appendChild(el('span', null, 'Спросить AI'));

  var panel = el('section', 'wfai-panel'); panel.setAttribute('aria-label', C.name || 'Ассистент'); panel.hidden = true;
  var head = el('header', 'wfai-head');
  var who = el('div', 'wfai-who');
  var hBot = makeBot(); var ava = el('span', 'wfai-ava'); ava._bot = hBot; ava.appendChild(hBot.el); who.appendChild(ava);
  var whoText = el('div'); whoText.appendChild(el('b', null, C.name || 'Ассистент')); whoText.appendChild(el('span', null, 'Отвечает сразу · 24/7'));
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
    if (!live) hBot.set('think');
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
    }).then(function () { busy = false; hBot.set('idle'); setTimeout(function () { hBot.act('happy'); }, 350); });
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
    lBot.el.animate([
      { transform: 'scale(.2) rotate(-25deg)', opacity: 0 },
      { transform: 'scale(1.18) rotate(6deg)', opacity: 1, offset: .6 },
      { transform: 'scale(1) rotate(0)', opacity: 1 },
    ], { duration: 520, easing: 'cubic-bezier(.3,1.4,.5,1)' });
    var label = launcher.querySelector('span');
    if (label) label.animate([{ opacity: 0, transform: 'translateX(-6px)' }, { opacity: 1, transform: 'none' }], { duration: 300, delay: 160, fill: 'backwards', easing: 'ease-out' });
    setTimeout(function () { act('blink'); }, 560);
  }

  /* иконка на кнопке живёт сама: моргает чаще всего, иногда оглядывается, машет, думает, косится на сайт, радуется */
  function act(name) { if (!root.classList.contains('is-open')) lBot.act(name); }
  (function idle() {
    var bag = ['blink', 'blink', 'blink', 'blink', 'look', 'look', 'wave', 'think', 'grumpy', 'happy', 'surprised'];
    setTimeout(function () { if (!document.hidden) act(bag[Math.floor(Math.random() * bag.length)]); idle(); }, 3000 + Math.random() * 4000);
  })();
  setTimeout(function () { act('wave'); }, 2200); // первое приветствие
  /* иконка в шапке чата: смотрит на клиента, опускает глаза, пока он печатает, думает, пока готовится ответ */
  (function headIdle() {
    setTimeout(function () { if (!panel.hidden && hBot.state === 'idle') hBot.act(Math.random() < .8 ? 'blink' : 'happy'); headIdle(); }, 2500 + Math.random() * 3000);
  })();
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
  var typeTimer = 0;
  input.addEventListener('input', function () {
    if (busy) return;
    hBot.set(input.value.trim() ? 'down' : 'idle');
    clearTimeout(typeTimer); typeTimer = setTimeout(function () { if (!busy) hBot.set('idle'); }, 1400);
  });
  input.addEventListener('blur', function () { if (!busy) hBot.set('idle'); });
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(input.value); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) setOpen(false); });
  // любые ссылки/кнопки с data-wfai-open открывают чат (например, «Спросить AI» на сайте)
  document.addEventListener('click', function (e) {
    var t = e.target.closest && e.target.closest('[data-wfai-open]');
    if (t) { e.preventDefault(); setOpen(true); var q = t.getAttribute('data-wfai-open'); if (q) send(q); }
  });
  schedulePoll();
})();
