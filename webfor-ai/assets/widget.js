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

  var ICON_CHAT = '<svg class="wfai-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.5 3.5V17h0A2.5 2.5 0 0 1 4 14.5z"/><path d="M8 9h8M8 12.5h5"/></svg>';
  var ICON_X = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
  var ICON_MIC = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>';
  var ICON_SPEAK = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 10v4h4l5 4V6L8 10H4z"/><path d="M16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12"/></svg>';
  var ICON_SEND = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

  var root = el('div', 'wfai');
  var launcher = el('button', 'wfai-launcher');
  launcher.type = 'button'; launcher.setAttribute('aria-label', 'Открыть чат с ассистентом'); launcher.setAttribute('aria-expanded', 'false');
  launcher.innerHTML = ICON_CHAT + '<span>Спросить AI</span>';

  var panel = el('section', 'wfai-panel'); panel.setAttribute('aria-label', C.name || 'Ассистент'); panel.hidden = true;
  var head = el('header', 'wfai-head');
  var who = el('div', 'wfai-who');
  who.appendChild(el('span', 'wfai-dot'));
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
  input.setAttribute('autocomplete', 'off'); input.setAttribute('enterkeyhint', 'send'); input.setAttribute('data-form-type', 'other'); input.name = 'wfai-q';
  var sendBtn = el('button', 'wfai-send'); sendBtn.type = 'submit'; sendBtn.setAttribute('aria-label', 'Отправить'); sendBtn.innerHTML = ICON_SEND;
  // голосовые: кнопка микрофона, если на сервере подключён Gemini и браузер умеет записывать звук
  var canRec = !!(C.voice && !C.transport && navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder && (window.AudioContext || window.webkitAudioContext));
  var micBtn = el('button', 'wfai-mic'); micBtn.type = 'button'; micBtn.setAttribute('aria-label', 'Записать голосовое'); micBtn.innerHTML = ICON_MIC;
  var recBar = el('div', 'wfai-rec'); recBar.hidden = true;
  var recDot = el('i'); var recTime = el('span', null, '0:00'); var recHint = el('em', null, 'Говорите…');
  var recCancel = el('button', 'wfai-rec-x', 'Отмена'); recCancel.type = 'button';
  recBar.appendChild(recDot); recBar.appendChild(recTime); recBar.appendChild(recHint); recBar.appendChild(recCancel);
  form.appendChild(input); form.appendChild(recBar);
  if (canRec) form.appendChild(micBtn);
  form.appendChild(sendBtn);
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
    if (canSpeak && (role === 'assistant' || role === 'manager') && !err) {
      var sp = el('button', 'wfai-speak'); sp.type = 'button'; sp.setAttribute('aria-label', 'Прочитать вслух'); sp.innerHTML = ICON_SPEAK;
      sp.addEventListener('click', function () { speak(text, sp); });
      b.appendChild(sp);
    }
    log.appendChild(b); log.scrollTop = log.scrollHeight;
    return b;
  }
  /* ---------- ответ вслух: голос телефона/компьютера (бесплатно) ---------- */
  var canSpeak = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
  var speakingBtn = null;
  function speak(text, btn) {
    if (!canSpeak) return;
    var synth = window.speechSynthesis;
    if (speakingBtn) { speakingBtn.classList.remove('is-on'); }
    if (speakingBtn === btn && synth.speaking) { synth.cancel(); speakingBtn = null; return; } // повторное нажатие — стоп
    synth.cancel();
    var clean = String(text).replace(/\p{Extended_Pictographic}|\uFE0F/gu, '').replace(/[*_#>`]/g, '').trim();
    if (!clean) return;
    var lang = /[әғқңөұүһі]/i.test(clean) ? 'kk-KZ' : /[а-яё]/i.test(clean) ? 'ru-RU' : 'en-US';
    var u = new SpeechSynthesisUtterance(clean);
    var voices = synth.getVoices(), pick = null;
    for (var i = 0; i < voices.length; i++) { if (voices[i].lang && voices[i].lang.replace('_', '-').toLowerCase().indexOf(lang.slice(0, 2)) === 0) { pick = voices[i]; if (/premium|enhanced|natural|google/i.test(voices[i].name)) break; } }
    if (!pick && lang === 'kk-KZ') { lang = 'ru-RU'; for (var j = 0; j < voices.length; j++) { if (/^ru/i.test(voices[j].lang)) { pick = voices[j]; break; } } } // казахского голоса нет — читаем русским
    u.lang = lang; if (pick) u.voice = pick; u.rate = 1.02;
    if (btn) { btn.classList.add('is-on'); speakingBtn = btn; }
    u.onend = u.onerror = function () { if (btn) btn.classList.remove('is-on'); if (speakingBtn === btn) speakingBtn = null; };
    synth.speak(u);
  }
  if (canSpeak && window.speechSynthesis.getVoices) { window.speechSynthesis.getVoices(); } // в Chrome список голосов загружается не сразу

  /* ---------- запись голосового: MediaRecorder → WAV 16 кГц моно (его понимает Gemini) ---------- */
  var rec = null, recChunks = [], recStream = null, recStart = 0, recTimer = 0, recCancelled = false;
  var REC_MAX = 60;
  function recUi(on) {
    form.classList.toggle('is-rec', on); recBar.hidden = !on; input.hidden = on;
    micBtn.setAttribute('aria-label', on ? 'Отправить голосовое' : 'Записать голосовое');
    sendBtn.hidden = on;
  }
  function startRec() {
    if (busy || rec) return;
    if (canSpeak) window.speechSynthesis.cancel();
    navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }).then(function (stream) {
      recStream = stream; recChunks = []; recCancelled = false;
      var types = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/webm', 'audio/ogg;codecs=opus'], type = '';
      for (var i = 0; i < types.length; i++) { if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(types[i])) { type = types[i]; break; } }
      rec = type ? new MediaRecorder(stream, { mimeType: type }) : new MediaRecorder(stream);
      rec.ondataavailable = function (e) { if (e.data && e.data.size) recChunks.push(e.data); };
      rec.onstop = function () {
        stream.getTracks().forEach(function (t) { t.stop(); });
        var secs = (Date.now() - recStart) / 1000, blob = new Blob(recChunks, { type: rec.mimeType || type || 'audio/webm' });
        rec = null; clearInterval(recTimer); recUi(false);
        if (recCancelled) return;
        if (secs < 0.8) { bubble('system', 'Слишком коротко — нажмите 🎤 и говорите, потом нажмите ещё раз, чтобы отправить.'); return; }
        sendVoice(blob);
      };
      rec.start();
      recStart = Date.now(); recTime.textContent = '0:00'; recUi(true);
      recTimer = setInterval(function () {
        var s = Math.floor((Date.now() - recStart) / 1000);
        recTime.textContent = Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
        if (s >= REC_MAX) stopRec();
      }, 250);
    }).catch(function () {
      bubble('system', 'Нет доступа к микрофону. Разрешите его в настройках браузера или напишите текстом.');
    });
  }
  function stopRec() { if (rec && rec.state !== 'inactive') rec.stop(); }
  micBtn.addEventListener('click', function () { if (rec) stopRec(); else startRec(); });
  recCancel.addEventListener('click', function () { recCancelled = true; stopRec(); });
  function toWav(blob) {
    var AC = window.AudioContext || window.webkitAudioContext, OAC = window.OfflineAudioContext || window.webkitOfflineAudioContext;
    return blob.arrayBuffer().then(function (buf) {
      var ac = new AC();
      return new Promise(function (res, rej) { ac.decodeAudioData(buf, res, rej); }).then(function (audio) { if (ac.close) ac.close(); return audio; });
    }).then(function (audio) {
      var rate = 16000, len = Math.max(1, Math.ceil(audio.duration * rate)), off = new OAC(1, len, rate);
      var src = off.createBufferSource(); src.buffer = audio; src.connect(off.destination); src.start(0);
      return off.startRendering();
    }).then(function (out) {
      var pcm = out.getChannelData(0), n = pcm.length, wav = new DataView(new ArrayBuffer(44 + n * 2));
      function str(o, t) { for (var i = 0; i < t.length; i++) wav.setUint8(o + i, t.charCodeAt(i)); }
      str(0, 'RIFF'); wav.setUint32(4, 36 + n * 2, true); str(8, 'WAVE'); str(12, 'fmt ');
      wav.setUint32(16, 16, true); wav.setUint16(20, 1, true); wav.setUint16(22, 1, true); wav.setUint32(24, 16000, true);
      wav.setUint32(28, 32000, true); wav.setUint16(32, 2, true); wav.setUint16(34, 16, true); str(36, 'data'); wav.setUint32(40, n * 2, true);
      for (var i = 0; i < n; i++) { var v = Math.max(-1, Math.min(1, pcm[i])); wav.setInt16(44 + i * 2, v < 0 ? v * 0x8000 : v * 0x7fff, true); }
      var bytes = new Uint8Array(wav.buffer), bin = '';
      for (var k = 0; k < bytes.length; k += 0x8000) bin += String.fromCharCode.apply(null, bytes.subarray(k, k + 0x8000));
      return btoa(bin);
    });
  }
  function sendVoice(blob) {
    if (busy) return;
    var ph = bubble('user', '🎤 Голосовое сообщение…'); ph.classList.add('wfai-voice-wait');
    toWav(blob).then(function (b64) { send('', { audio: b64, placeholder: ph }); })
      .catch(function () { ph.remove(); bubble('system', 'Не получилось записать голос. Попробуйте ещё раз или напишите текстом.'); });
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

  function send(text, voice) {
    text = (text || '').trim();
    if ((!text && !voice) || busy) return;
    busy = true; chips.hidden = true;
    if (!voice) { history.push({ role: 'user', content: text }); save(); bubble('user', text); input.value = ''; autosize(); }
    var live = st.status === 'live';
    var typing = null;
    if (!live) { typing = el('div', 'wfai-msg wfai-assistant wfai-typing'); typing.innerHTML = '<i></i><i></i><i></i>'; typing.setAttribute('aria-label', 'Ассистент печатает'); log.appendChild(typing); log.scrollTop = log.scrollHeight; }
    var body = C.transport ? { messages: history.filter(function (m) { return m.role === 'user' || m.role === 'assistant'; }).map(function (m) { return { role: m.role, content: m.content }; }) } : voice ? { chat: st.chat, audio: voice.audio } : { chat: st.chat, text: text };
    post(C.chat, body).then(function (r) {
      if (typing) typing.remove();
      r = r || {};
      if (voice) { // голосовое: показываем, что расслышали; не разобрали — убираем заглушку и объясняем
        voice.placeholder.classList.remove('wfai-voice-wait');
        if (r.ok === false || !r.transcript) { voice.placeholder.remove(); bubble('system', r.reply || 'Не получилось разобрать голосовое — напишите, пожалуйста, текстом.'); return; }
        voice.placeholder.textContent = '🎤 ' + r.transcript;
        history.push({ role: 'user', content: '🎤 ' + r.transcript }); save();
      }
      var before = log.childElementCount;
      if (r.chat) st.chat = r.chat;
      if (r.user_i) st.last = Math.max(st.last, r.user_i);
      if (r.status) st.status = r.status;
      if (r.msgs) addServer(r.msgs);
      else if (r.reply) { history.push({ role: 'assistant', content: r.reply, err: !!r.unsupported }); bubble('assistant', r.reply, '', !!r.unsupported); }
      else if (!live) bubble('assistant', 'Не получилось ответить. Напишите нам в WhatsApp.');
      if (r.unsupported && r.msgs) { var lastB = log.querySelector('.wfai-msg:last-of-type'); if (lastB) lastB.classList.add('wfai-error'); history[history.length - 1].err = true; }
      save();
      // спросили голосом — ответ сразу читаем вслух
      if (voice && canSpeak) { var nb = log.querySelectorAll('.wfai-assistant:not(.wfai-typing)'); var lastA = nb[nb.length - 1]; if (lastA && log.childElementCount > before) { var spb = lastA.querySelector('.wfai-speak'); if (spb) speak(lastA.firstChild.textContent, spb); } }
      if (r.escalated && r.question_id && !contactAsked && st.status !== 'live') { pendingQuestion = r.question_id; contactAsked = true; contactForm(); }
      schedulePoll();
    }).catch(function () {
      if (typing) typing.remove();
      if (voice) voice.placeholder.remove();
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
    launcher.querySelector('.wfai-ico').animate([
      { transform: 'scale(.2) rotate(-25deg)', opacity: 0 },
      { transform: 'scale(1.18) rotate(6deg)', opacity: 1, offset: .6 },
      { transform: 'scale(1) rotate(0)', opacity: 1 },
    ], { duration: 520, easing: 'cubic-bezier(.3,1.4,.5,1)' });
    var label = launcher.querySelector('span');
    if (label) label.animate([{ opacity: 0, transform: 'translateX(-6px)' }, { opacity: 1, transform: 'none' }], { duration: 300, delay: 160, fill: 'backwards', easing: 'ease-out' });
  }

  /* Телефон и клавиатура. Окно чата всегда ровно до клавиатуры: высоту и положение берём из visualViewport
     (на iPhone клавиатура не уменьшает страницу, и под чатом оставался видимый, прокручиваемый сайт).
     Сайт под чатом «замораживаем» через position: fixed у body — iOS игнорирует overflow: hidden. */
  var phone = window.matchMedia ? matchMedia('(max-width: 760px)') : { matches: false };
  var vv = window.visualViewport;
  // Android Chrome: клавиатура уменьшает окно страницы, а не перекрывает его
  (function () {
    var m = document.querySelector('meta[name=viewport]');
    if (m && m.content.indexOf('interactive-widget') < 0) m.content += ', interactive-widget=resizes-content';
  })();
  // пересчёт не чаще одного раза за кадр и только когда размер правда изменился — иначе окно «дрожит», пока выезжает клавиатура
  var fitQueued = false, fitLast = '';
  function fitPanel() {
    if (fitQueued) return;
    fitQueued = true;
    requestAnimationFrame(function () { fitQueued = false; fitNow(); });
  }
  function fitNow() {
    if (panel.hidden || !phone.matches || !vv) { panel.style.top = panel.style.height = panel.style.bottom = ''; fitLast = ''; return; }
    var top = Math.round(vv.offsetTop), h = Math.round(vv.height), key = top + ':' + h;
    if (key === fitLast) return;
    fitLast = key;
    var stick = log.scrollHeight - log.scrollTop - log.clientHeight < 60;
    panel.style.top = top + 'px';
    panel.style.bottom = 'auto';
    panel.style.height = h + 'px';
    root.style.setProperty('--wfai-vb', (top + h) + 'px'); // где начинается клавиатура
    root.classList.toggle('wfai-kb', vv.height < window.innerHeight * .8 || document.activeElement === input);
    if (stick) log.scrollTop = log.scrollHeight; // последнее сообщение остаётся над полем ввода
  }
  if (vv) { vv.addEventListener('resize', fitPanel); vv.addEventListener('scroll', fitPanel); }
  var lockY = null;
  function lockPage(on) {
    var b = document.body.style;
    if (on) {
      lockY = window.scrollY;
      document.documentElement.classList.add('wfai-lock');
      if (phone.matches) { b.position = 'fixed'; b.top = -lockY + 'px'; b.left = '0'; b.right = '0'; b.width = '100%'; }
    } else {
      document.documentElement.classList.remove('wfai-lock');
      b.position = b.top = b.left = b.right = b.width = '';
      if (lockY != null) { window.scrollTo({ top: lockY, behavior: 'instant' }); lockY = null; }
    }
  }
  function setOpen(open) {
    if (animating || open === !panel.hidden) return;
    launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
    // сайт под открытым чатом не прокручивается и после закрытия остаётся на том же месте
    lockPage(open);
    if (open) {
      if (!log.childElementCount) render();
      var btnRect = launcher.getBoundingClientRect(); // до раскрытия: на телефоне контейнер чата потом растягивается на весь экран
      panel.hidden = false; root.classList.add('is-open'); fitPanel();
      root.classList.remove('wfai-unread');
      if (st.chat && !C.transport) { clearTimeout(pollTimer); pollTimer = null; poll(); }
      if (!reduce && panel.animate) { animating = true; morph(btnRect, true, function () { animating = false; }); }
      if (!phone.matches) setTimeout(function () { input.focus({ preventScroll: true }); }, 60);
    } else {
      var finish = function () { panel.hidden = true; root.classList.remove('is-open', 'wfai-kb'); fitPanel(); animating = false; launcher.focus({ preventScroll: true }); };
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
  input.addEventListener('focus', function () { setTimeout(fitPanel, 50); setTimeout(fitPanel, 350); });
  input.addEventListener('blur', function () { setTimeout(fitPanel, 50); });
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(input.value); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) setOpen(false); });
  // любые ссылки/кнопки с data-wfai-open открывают чат (например, «Спросить AI» на сайте)
  document.addEventListener('click', function (e) {
    var t = e.target.closest && e.target.closest('[data-wfai-open]');
    if (t) { e.preventDefault(); setOpen(true); var q = t.getAttribute('data-wfai-open'); if (q) send(q); }
  });
  schedulePoll();
})();
