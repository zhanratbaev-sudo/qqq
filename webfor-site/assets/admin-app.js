/* Админка WebFor — приложение для телефона (PWA) в стиле Liquid Glass.
   Четыре раздела внизу: Чаты, Заявки, Обучение, Ещё. Подробности открываются «листами» снизу и экранами справа, как в iOS.
   Данные — через WFADMIN.adapter (по умолчанию REST WordPress), поэтому та же панель работает и в демо. */
(function () {
  'use strict';
  const app = document.getElementById('wfa-app');
  if (!app) return;
  const CFG = window.WFADMIN || {};
  const reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  const EASE = 'cubic-bezier(.32,.72,0,1)'; // «пружина» iOS
  const ls = {
    get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
  };

  /* ---------- доступ к данным ---------- */
  function rest(base) {
    const url = (path) => (base.indexOf('?') >= 0 ? base + path.replace('?', '&') : base + path);
    return (method, path, body) => fetch(url(path), {
      method, credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce },
      body: body ? JSON.stringify(body) : undefined,
    }).then((r) => r.json().then((d) => { if (!r.ok) throw new Error((d && d.message) || 'Ошибка ' + r.status); return d; }));
  }
  const adapter = CFG.adapter || {
    api: rest(CFG.root),
    chatApi: CFG.chats ? rest(CFG.chats.root) : null,
    pickImage: () => (window.wp && wp.media ? pickFromLibrary() : uploadImage()),
  };
  const HAS_CHATS = !!adapter.chatApi;
  function pickFromLibrary() {
    return new Promise((resolve) => {
      const frame = wp.media({ title: 'Скриншот работы', button: { text: 'Выбрать' }, library: { type: 'image' }, multiple: false });
      frame.on('select', () => { const a = frame.state().get('selection').first().toJSON(); resolve({ id: a.id, url: (a.sizes && (a.sizes.large || a.sizes.full) || a).url }); });
      frame.on('close', () => setTimeout(() => resolve(null), 0));
      frame.open();
    });
  }
  function uploadImage() {
    return pickFile('image/*').then((f) => {
      if (!f) return null;
      toast('Загружаем скриншот…');
      return fetch(CFG.media, { method: 'POST', credentials: 'same-origin', body: f, headers: {
        'X-WP-Nonce': CFG.nonce, 'Content-Type': f.type || 'image/jpeg',
        'Content-Disposition': 'attachment; filename="' + encodeURIComponent(f.name.replace(/"/g, '')) + '"' } })
        .then((r) => r.json().then((d) => { if (!r.ok) throw new Error(d.message || 'Не загрузилось'); return d; }))
        .then((d) => { const sz = (d.media_details && d.media_details.sizes) || {}; return { id: d.id, url: (sz.large || sz.full || {}).source_url || d.source_url }; })
        .catch((e) => { fail(e); return null; });
    });
  }
  function pickFile(accept) {
    return new Promise((resolve) => {
      const inp = document.createElement('input'); inp.type = 'file'; inp.accept = accept;
      inp.onchange = () => resolve((inp.files && inp.files[0]) || null);
      inp.click();
    });
  }

  /* ---------- мелочи ---------- */
  function h(tag, attrs, kids) {
    const el = document.createElement(tag);
    Object.keys(attrs || {}).forEach((k) => {
      const v = attrs[k];
      if (v == null || v === false) return;
      if (k === 'class') el.className = v;
      else if (k === 'text') el.textContent = v;
      else if (k === 'html') el.innerHTML = v;
      else if (k.slice(0, 2) === 'on') el.addEventListener(k.slice(2), v);
      else el.setAttribute(k, v === true ? '' : v);
    });
    (Array.isArray(kids) ? kids : kids != null ? [kids] : []).forEach((c) => {
      if (c == null || c === false) return;
      el.appendChild(typeof c === 'string' || typeof c === 'number' ? document.createTextNode(String(c)) : c);
    });
    return el;
  }
  const P = (d) => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>';
  const I = {
    chats: P('<path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.5 3.5V17h0A2.5 2.5 0 0 1 4 14.5z"/>'),
    leads: P('<path d="M4 13h4l2 3h4l2-3h4"/><path d="M5.5 5h13L21 13v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5z"/>'),
    train: P('<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>'),
    more: P('<circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/>'),
    back: P('<path d="M15 5l-7 7 7 7"/>'),
    next: P('<path d="M9 5l7 7-7 7"/>'),
    close: P('<path d="M6 6l12 12M18 6 6 18"/>'),
    phone: P('<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>'),
    wa: P('<path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.5 3.5V17h0A2.5 2.5 0 0 1 4 14.5z"/><path d="M8 9h8M8 12.5h5"/>'), // обычная иконка чата со строками — без логотипов
    send: P('<path d="M12 19V5M6 11l6-6 6 6"/>'),
    plus: P('<path d="M12 5v14M5 12h14"/>'),
    bell: P('<path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15z"/><path d="M10 20a2 2 0 0 0 4 0"/>'),
    check: P('<path d="M5 12.5l4.5 4.5L19 7.5"/>'),
    file: P('<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>'),
    test: P('<path d="M8 5.5v13l10.5-6.5z"/>'),
    book: P('<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16M8 7h8"/>'),
    star: P('<path d="M12 4l2.4 5 5.4.6-4 3.7 1.1 5.4L12 16l-4.9 2.7 1.1-5.4-4-3.7 5.4-.6z"/>'),
    globe: P('<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>'),
    grid: P('<rect x="4" y="4" width="7" height="7" rx="2"/><rect x="13" y="4" width="7" height="7" rx="2"/><rect x="4" y="13" width="7" height="7" rx="2"/><rect x="13" y="13" width="7" height="7" rx="2"/>'),
    gear: P('<circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M5.3 18.7l2.1-2.1M16.6 7.4l2.1-2.1"/>'),
    logout: P('<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3"/>'),
    install: P('<rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M12 7v7M9 11l3 3 3-3"/>'),
    image: P('<rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>'),
  };
  const ico = (n, cls) => h('span', { class: 'wfa-ico' + (cls ? ' ' + cls : ''), html: I[n] });
  function timeAgo(v) {
    const d = typeof v === 'number' ? new Date(v * 1000) : new Date(v);
    if (isNaN(d)) return '';
    const s = (Date.now() - d) / 1000;
    if (s < 60) return 'сейчас';
    if (s < 3600) return Math.floor(s / 60) + ' мин';
    if (new Date().toDateString() === d.toDateString()) return d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
  }
  const clock = (ts) => new Date(ts * 1000).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
  const digits = (s) => String(s || '').replace(/\D/g, '');
  const plural = (n, one, few, many) => { const a = n % 10, b = n % 100; return n + ' ' + (a === 1 && b !== 11 ? one : a >= 2 && a <= 4 && (b < 12 || b > 14) ? few : many); };
  const phoneUI = () => !window.matchMedia || matchMedia('(max-width: 760px)').matches;
  function anim(el, frames, opt) { return !reduce && el.animate ? el.animate(frames, Object.assign({ duration: 360, easing: EASE }, opt)) : null; }
  function done(a, fn) { if (a) a.onfinish = fn; else fn(); }

  /* ---------- уведомления-«пилюли» сверху ---------- */
  const toastBox = h('div', { class: 'wfa-toasts', 'aria-live': 'polite' });
  function toast(text, bad) {
    const t = h('div', { class: 'wfa-toast' + (bad ? ' is-bad' : ''), text });
    toastBox.appendChild(t);
    anim(t, [{ transform: 'translateY(-24px) scale(.9)', opacity: 0 }, { transform: 'none', opacity: 1 }], { duration: 420 });
    setTimeout(() => done(anim(t, [{ opacity: 1 }, { opacity: 0, transform: 'translateY(-12px)' }], { duration: 260 }), () => t.remove()), 2600);
  }
  function fail(e) {
    let m = e && e.message ? e.message : 'Не удалось сохранить';
    if (/маршрут|route|404/i.test(m)) m = 'Сайт не нашёл этот раздел. Обновите оба плагина WebFor до последней версии.';
    else if (/Failed to fetch|NetworkError|Load failed/i.test(m)) m = 'Нет интернета — проверьте связь';
    else if (/nonce|cookie|403|401/i.test(m)) m = 'Сессия устарела — войдите заново';
    toast(m, true);
  }
  // удалить — с подтверждением прямо в кнопке
  function confirmBtn(label, onYes) {
    let armed = false, timer;
    const b = h('button', { type: 'button', class: 'wfa-btn is-danger is-wide', text: label, onclick: () => {
      if (!armed) { armed = true; b.textContent = 'Нажмите ещё раз, чтобы удалить'; timer = setTimeout(() => { armed = false; b.textContent = label; }, 3000); return; }
      clearTimeout(timer); onYes();
    } });
    return b;
  }

  /* ---------- каркас: фон, экраны, нижняя панель, листы ---------- */
  const embedded = !CFG.sw && !CFG.demo; // внутри админки WordPress, а не отдельным приложением
  app.className = 'wfa' + (embedded ? ' is-embedded' : ' is-app');
  // высоту не растягиваем на весь экран: нижнюю полосу iPhone для приложений с экрана «Домой» страница не рисует
  app.innerHTML = '';
  const stage = h('div', { class: 'wfa-stage' });
  const tabbar = h('nav', { class: 'wfa-tabbar', 'aria-label': 'Разделы' });
  const pill = h('span', { class: 'wfa-tabpill', 'aria-hidden': 'true' });
  const layer = h('div', { class: 'wfa-layer' });
  app.append(h('div', { class: 'wfa-bg', 'aria-hidden': 'true' }), stage, tabbar, layer, toastBox);

  const TABS = [['chats', 'Чаты'], ['leads', 'Заявки'], ['train', 'Обучение'], ['more', 'Ещё']].filter((t) => t[0] !== 'chats' || HAS_CHATS);
  const screens = {};
  function makeScreen(title) {
    const nav = h('header', { class: 'wfa-nav' }, [h('span', { class: 'wfa-nav-title', text: title })]);
    const body = h('div', { class: 'wfa-body' });
    const scroller = h('div', { class: 'wfa-scroll' }, [h('h1', { class: 'wfa-large', text: title }), body]);
    // большой заголовок уезжает — появляется маленький в стеклянной полосе сверху (как в iOS)
    scroller.addEventListener('scroll', () => nav.classList.toggle('is-compact', scroller.scrollTop > 34), { passive: true });
    const ptr = h('div', { class: 'wfa-ptr', 'aria-hidden': 'true' }, h('span', { class: 'wfa-spinner' }));
    const el = h('section', { class: 'wfa-screen' }, [nav, ptr, scroller]);
    pullToRefresh(scroller, ptr);
    return { el, body, scroller, nav };
  }
  function pullToRefresh(sc, ptr) {
    let y0 = null, dy = 0, busy = false;
    sc.addEventListener('touchstart', (e) => { if (sc.scrollTop <= 0 && !busy) { y0 = e.touches[0].clientY; dy = 0; } }, { passive: true });
    sc.addEventListener('touchmove', (e) => {
      if (y0 == null) return;
      dy = Math.max(0, e.touches[0].clientY - y0);
      if (sc.scrollTop > 0) { y0 = null; dy = 0; }
      const k = Math.min(1, dy / 90);
      ptr.style.opacity = k; ptr.style.transform = 'translateY(' + Math.min(70, dy * 0.6) + 'px) rotate(' + dy * 3 + 'deg)';
    }, { passive: true });
    sc.addEventListener('touchend', () => {
      if (y0 == null) return; y0 = null;
      if (dy > 90 && S) {
        busy = true; ptr.classList.add('is-busy');
        refreshAll().then(() => { busy = false; ptr.classList.remove('is-busy'); ptr.style.opacity = 0; ptr.style.transform = ''; toast('Обновлено'); });
      } else { ptr.style.opacity = 0; ptr.style.transform = ''; }
    }, { passive: true });
  }
  TABS.forEach(([k, label]) => {
    screens[k] = makeScreen(label);
    screens[k].el.hidden = true;
    stage.appendChild(screens[k].el);
    tabbar.appendChild(h('button', { type: 'button', class: 'wfa-tab', 'data-tab': k, 'aria-label': label, onclick: () => setTab(k) },
      [ico(k), h('span', { class: 'wfa-tab-label', text: label }), h('b', { class: 'wfa-tab-badge', hidden: true })]));
  });
  tabbar.appendChild(pill);

  let tab = ls.get('wfa-tab2');
  if (!TABS.some((t) => t[0] === tab)) tab = TABS[0][0];
  function setTab(k, quiet) {
    const prev = tab;
    if (stack.length) popAll();
    tab = k; ls.set('wfa-tab2', k);
    TABS.forEach(([key]) => { screens[key].el.hidden = key !== k; });
    tabbar.querySelectorAll('.wfa-tab').forEach((b) => b.setAttribute('aria-current', b.dataset.tab === k ? 'page' : 'false'));
    movePill();
    render(k);
    if (quiet) return;
    if (prev !== k) anim(screens[k].el, [{ opacity: 0, transform: 'translateY(10px) scale(.99)' }, { opacity: 1, transform: 'none' }], { duration: 300 });
    else screens[k].scroller.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); // повторное нажатие — наверх
  }
  function movePill() {
    const b = tabbar.querySelector('.wfa-tab[aria-current=page]');
    if (!b) return;
    pill.style.width = b.offsetWidth + 'px';
    pill.style.transform = 'translateX(' + b.offsetLeft + 'px)';
  }
  window.addEventListener('resize', movePill);
  function badge(k, n) {
    const b = tabbar.querySelector('.wfa-tab[data-tab=' + k + '] .wfa-tab-badge');
    if (!b) return;
    b.hidden = !n; b.textContent = n > 99 ? '99+' : n;
  }

  /* экраны «справа» (разговор, список, проверка ассистента) */
  const stack = [];
  const below = () => (stack.length ? stack[stack.length - 1].el : screens[tab].el);
  function push(view) {
    const under = below();
    stage.appendChild(view.el);
    stack.push(view);
    anim(view.el, [{ transform: 'translateX(100%)' }, { transform: 'none' }], { duration: 460 });
    anim(under, [{ transform: 'none', opacity: 1 }, { transform: 'translateX(-28%)', opacity: 0.4 }], { duration: 460, fill: 'forwards' });
    tabbar.classList.add('is-hidden');
  }
  function pop() {
    const view = stack.pop();
    if (!view) return;
    const under = below();
    if (view.onclose) view.onclose();
    done(anim(view.el, [{ transform: 'none' }, { transform: 'translateX(100%)' }], { duration: 380 }), () => view.el.remove());
    under.getAnimations && under.getAnimations().forEach((x) => x.cancel());
    anim(under, [{ transform: 'translateX(-28%)', opacity: 0.4 }, { transform: 'none', opacity: 1 }], { duration: 380 });
    if (!stack.length) { tabbar.classList.remove('is-hidden'); render(tab); }
    if (/^#chat-/.test(location.hash) && !stack.some((v) => v.chat)) history.replaceState(null, '', location.pathname + location.search);
  }
  function popAll() {
    while (stack.length) { const v = stack.pop(); if (v.onclose) v.onclose(); v.el.remove(); }
    Object.values(screens).forEach((s) => s.el.getAnimations && s.el.getAnimations().forEach((x) => x.cancel()));
    tabbar.classList.remove('is-hidden');
  }
  function pushView(title, opts) {
    opts = opts || {};
    const backBtn = h('button', { type: 'button', class: 'wfa-back', 'aria-label': 'Назад', onclick: pop }, [ico('back'), h('span', { text: opts.backLabel || 'Назад' })]);
    const nav = h('header', { class: 'wfa-nav is-compact is-push' }, [backBtn, h('div', { class: 'wfa-nav-center' }, [h('span', { class: 'wfa-nav-title', text: title }), opts.subtitle || null]), h('div', { class: 'wfa-nav-actions' }, opts.actions || [])]);
    const body = h('div', { class: 'wfa-body' });
    const scroller = h('div', { class: 'wfa-scroll' }, [body]);
    const el = h('section', { class: 'wfa-screen is-pushed' + (opts.cls ? ' ' + opts.cls : '') }, [nav, scroller].concat(opts.footer ? [opts.footer] : []));
    // свайп от левого края — назад, как в iOS
    let sx = null;
    el.addEventListener('touchstart', (e) => { const t = e.touches[0]; sx = t.clientX < 28 ? t.clientX : null; }, { passive: true });
    el.addEventListener('touchend', (e) => { if (sx != null && e.changedTouches[0].clientX - sx > 80) pop(); sx = null; }, { passive: true });
    return { el, body, scroller, nav };
  }

  /* лист снизу: редактирование, ответы, настройки */
  function sheet(title, content, opts) {
    opts = opts || {};
    const back = h('div', { class: 'wfa-sheet-back' });
    const closeBtn = h('button', { type: 'button', class: 'wfa-icon-btn', 'aria-label': 'Закрыть', html: I.close });
    const head = h('div', { class: 'wfa-sheet-head' }, [h('span', { class: 'wfa-grabber' }), h('b', { text: title }), closeBtn]);
    const scroll = h('div', { class: 'wfa-sheet-body' }, content);
    const foot = opts.actions ? h('div', { class: 'wfa-sheet-foot' }, opts.actions) : null;
    const el = h('div', { class: 'wfa-sheet', role: 'dialog', 'aria-modal': 'true', 'aria-label': title }, [head, scroll, foot]);
    layer.append(back, el);
    layer.classList.add('is-on');
    anim(back, [{ opacity: 0 }, { opacity: 1 }], { duration: 300 });
    anim(el, [{ transform: 'translateY(100%)' }, { transform: 'none' }], { duration: 440 });
    let closed = false;
    function close() {
      if (closed) return; closed = true;
      anim(back, [{ opacity: 1 }, { opacity: 0 }], { duration: 260 });
      const from = el.style.transform || 'none';
      done(anim(el, [{ transform: from }, { transform: 'translateY(100%)' }], { duration: 300, easing: 'cubic-bezier(.4,0,1,1)' }), () => {
        back.remove(); el.remove();
        if (!layer.querySelector('.wfa-sheet')) layer.classList.remove('is-on');
        if (opts.onclose) opts.onclose();
      });
    }
    back.addEventListener('click', close);
    closeBtn.addEventListener('click', close);
    // потянуть вниз за шапку — закрыть
    let y0 = null, dy = 0;
    head.addEventListener('pointerdown', (e) => { if (e.target.closest('button')) return; y0 = e.clientY; dy = 0; head.setPointerCapture(e.pointerId); el.style.transition = 'none'; });
    head.addEventListener('pointermove', (e) => { if (y0 == null) return; dy = Math.max(0, e.clientY - y0); el.style.transform = 'translateY(' + dy + 'px)'; });
    head.addEventListener('pointerup', () => {
      if (y0 == null) return; y0 = null;
      if (dy > 110) close();
      else { el.style.transition = 'transform .3s ' + EASE; el.style.transform = ''; }
    });
    const first = el.querySelector('textarea, input:not([type=checkbox]):not([type=radio])');
    // курсор ставим сам только с мышью: на телефоне фокус открывает клавиатуру и iPhone приближает страницу
    if (first && opts.focus && window.matchMedia && matchMedia('(hover: hover) and (pointer: fine)').matches) setTimeout(() => first.focus(), 350);
    return { close, el };
  }
  const field = (label, input, hint) => h('label', { class: 'wfa-field' }, [h('span', { text: label }), input, hint ? h('small', { text: hint }) : null]);
  const area = (value, rows, ph) => { const t = h('textarea', { class: 'wfa-input', rows: rows || 4, placeholder: ph || '' }); t.value = value || ''; return t; };
  const inputEl = (value, type, ph) => h('input', { class: 'wfa-input', type: type || 'text', value: value || '', placeholder: ph || '' });
  const btn = (text, onclick, cls) => h('button', { type: 'button', class: 'wfa-btn' + (cls ? ' ' + cls : ''), onclick }, text);

  /* общие блоки списков в стиле iOS */
  const group = (title, rows, foot) => h('section', { class: 'wfa-group' }, [
    title ? h('h2', { class: 'wfa-group-title', text: title }) : null,
    h('div', { class: 'wfa-list' }, rows),
    foot ? h('p', { class: 'wfa-group-foot', text: foot }) : null,
  ]);
  function row(o) {
    const attrs = { class: 'wfa-item' + (o.cls ? ' ' + o.cls : ''), onclick: o.onclick };
    if (o.href) { attrs.href = o.href; if (o.blank) { attrs.target = '_blank'; attrs.rel = 'noopener'; } } else attrs.type = 'button';
    return h(o.href ? 'a' : 'button', attrs, [
      o.icon ? h('span', { class: 'wfa-item-ico' + (o.tint ? ' is-' + o.tint : '') }, ico(o.icon)) : null,
      h('span', { class: 'wfa-item-main' }, [h('b', { text: o.title }), o.sub ? h('span', { class: 'wfa-item-sub', text: o.sub }) : null]),
      o.value != null ? h('span', { class: 'wfa-item-value', text: o.value }) : null,
      o.badge ? h('span', { class: 'wfa-count', text: o.badge }) : null,
      o.chevron === false ? null : ico('next', 'wfa-chev'),
    ]);
  }
  const staticRow = (text, icon) => h('div', { class: 'wfa-item is-static' }, [icon ? h('span', { class: 'wfa-item-ico is-ok' }, ico(icon)) : null, h('span', { class: 'wfa-item-main' }, [h(icon ? 'b' : 'span', { class: icon ? null : 'wfa-item-sub', text })])]);
  function segmented(items, current, onpick) {
    const s = h('div', { class: 'wfa-seg', role: 'tablist' });
    const thumb = h('span', { class: 'wfa-seg-thumb', 'aria-hidden': 'true' });
    s.appendChild(thumb);
    items.forEach(([k, label, n]) => s.appendChild(h('button', { type: 'button', role: 'tab', 'aria-selected': k === current ? 'true' : 'false', onclick: () => onpick(k) },
      [label, n ? h('span', { class: 'wfa-seg-n', text: n }) : null])));
    requestAnimationFrame(() => {
      const b = s.querySelector('[aria-selected=true]');
      if (b) { thumb.style.width = b.offsetWidth + 'px'; thumb.style.transform = 'translateX(' + b.offsetLeft + 'px)'; }
    });
    return s;
  }
  const empty = (icon, title, text) => h('div', { class: 'wfa-empty' }, [ico(icon), h('b', { text: title }), text ? h('p', { text }) : null]);

  /* ---------- состояние ---------- */
  let S = null, faq = null;
  const C = { list: [], seen: null };
  let leadFilter = 'new', chatFilter = 'waiting';
  const counts = () => ({
    waiting: C.list.filter((c) => c.status === 'waiting').length,
    newLeads: S ? S.leads.filter((l) => l.status === 'new').length : 0,
    newQs: S && S.ai ? S.ai.questions.filter((q) => q.state !== 'answered').length : 0,
  });
  function updateBadges() {
    const c = counts();
    badge('chats', c.waiting); badge('leads', c.newLeads); badge('train', c.newQs);
    if (navigator.setAppBadge) { try { const n = c.waiting + c.newLeads; n ? navigator.setAppBadge(n) : navigator.clearAppBadge(); } catch (e) {} }
  }
  function render(k) {
    if (!S) return;
    const scr = screens[k];
    if (document.activeElement && scr.body.contains(document.activeElement) && document.activeElement.matches('input, textarea')) return; // не мешаем печатать
    scr.body.innerHTML = '';
    ({ chats: viewChats, leads: viewLeads, train: viewTrain, more: viewMore })[k](scr.body);
    updateBadges();
  }

  /* ================= ЧАТЫ ================= */
  const CHANNEL = { site: 'Сайт', tg: 'Telegram', wa: 'WhatsApp', ig: 'Instagram' };
  const REFRESH = 10000; // обновление панели — каждые 10 секунд (новое сообщение клиента приходит сразу push-уведомлением)
  const STATUS = { waiting: 'Ждёт вас', live: 'Вы в чате', bot: 'Отвечает ассистент', closed: 'Закрыт' };
  function setChats(list) {
    const waiting = list.filter((c) => c.status === 'waiting').map((c) => c.id);
    if (C.seen && waiting.some((id) => C.seen.indexOf(id) < 0)) toast('Клиенту нужен менеджер');
    C.seen = waiting; C.list = list;
  }
  function viewChats(body) {
    notifyCards().forEach((c) => body.appendChild(c));
    const c = counts();
    body.appendChild(segmented([['waiting', 'Ждут вас', c.waiting], ['all', 'Все', C.list.length]], chatFilter, (k) => { chatFilter = k; render('chats'); }));
    const list = C.list.filter((x) => chatFilter === 'all' || x.status === 'waiting');
    if (!list.length) {
      body.appendChild(chatFilter === 'waiting'
        ? empty('check', 'Никто не ждёт', 'Когда ассистент позовёт вас, разговор появится здесь и придёт уведомление.')
        : empty('chats', 'Разговоров пока нет', 'Когда клиент напишет ассистенту на сайте, разговор появится здесь.'));
      return;
    }
    body.appendChild(h('div', { class: 'wfa-list is-chats' }, list.map((x) => swipeRow(h('button', { type: 'button', class: 'wfa-chat-row is-' + x.status, onclick: () => openChat(x.id) }, [
      h('span', { class: 'wfa-ava', text: x.contact ? '☎' : '#' + String(x.id).slice(-2) }),
      h('span', { class: 'wfa-chat-main' }, [
        h('span', { class: 'wfa-chat-top' }, [h('b', { text: x.contact || 'Клиент №' + x.id }), h('time', { text: timeAgo(x.updated) })]),
        h('span', { class: 'wfa-chat-last', text: x.last || '…' }),
        h('span', { class: 'wfa-chat-top' }, [h('span', { class: 'wfa-pills' }, [h('span', { class: 'wfa-pill is-' + x.status, text: STATUS[x.status] || x.status }), h('span', { class: 'wfa-pill is-ch', text: CHANNEL[x.channel || 'site'] })]), x.unread ? h('span', { class: 'wfa-count', text: x.unread }) : null]),
      ]),
    ]), () => closeChat(x.id)))));
    body.appendChild(h('p', { class: 'wfa-group-foot wfa-hint', text: 'Смахните чат влево, чтобы закрыть его. Если клиент напишет снова — чат вернётся.' }));
  }
  // строка, которую можно смахнуть влево — под ней кнопка «Закрыть»
  function swipeRow(rowEl, onClose) {
    const act = h('button', { type: 'button', class: 'wfa-swipe-act', text: 'Закрыть', onclick: (e) => { e.stopPropagation(); onClose(); } });
    const wrap = h('div', { class: 'wfa-swipe' }, [act, rowEl]);
    let x0 = null, y0 = 0, dx = 0, open = false, horiz = null;
    const W = 96;
    const set = (x, anim) => { rowEl.style.transition = anim ? 'transform .35s ' + EASE : 'none'; rowEl.style.transform = x ? 'translateX(' + x + 'px)' : ''; };
    rowEl.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; dx = 0; horiz = null; }, { passive: true });
    rowEl.addEventListener('touchmove', (e) => {
      if (x0 == null) return;
      const mx = e.touches[0].clientX - x0, my = e.touches[0].clientY - y0;
      if (horiz == null && (Math.abs(mx) > 8 || Math.abs(my) > 8)) horiz = Math.abs(mx) > Math.abs(my);
      if (!horiz) return;
      dx = Math.min(0, Math.max(-W * 1.4, mx + (open ? -W : 0)));
      set(dx);
    }, { passive: true });
    rowEl.addEventListener('touchend', () => {
      if (x0 == null || !horiz) { x0 = null; return; }
      x0 = null;
      if (dx < -W * 1.25) { set(-wrap.offsetWidth, true); setTimeout(onClose, 200); return; } // смахнули до конца — сразу закрываем
      open = dx < -W / 2; set(open ? -W : 0, true);
    });
    rowEl.addEventListener('click', (e) => { if (open) { e.preventDefault(); e.stopImmediatePropagation(); open = false; set(0, true); } }, true);
    return wrap;
  }
  function closeChat(id) {
    adapter.chatApi('POST', '/chat/' + id + '/close').then(() => {
      C.list = C.list.filter((x) => x.id !== id);
      if (chatView && chatView.id === id) pop();
      updateBadges(); render('chats'); toast('Чат закрыт');
    }).catch(fail);
  }

  let chatView = null, chatTimer = 0, listTimer = 0;
  function openChat(id, instant) {
    if (!HAS_CHATS) return;
    if (tab !== 'chats') setTab('chats', true);
    if (chatView) pop();
    const it = C.list.find((x) => x.id === id) || {};
    const sub = h('span', { class: 'wfa-nav-sub', text: STATUS[it.status] || '' });
    const waBtn = h('a', { class: 'wfa-icon-btn', target: '_blank', rel: 'noopener', 'aria-label': 'Написать в WhatsApp', html: I.wa, hidden: true });
    const log = h('div', { class: 'wfa-msgs', 'aria-live': 'polite' });
    const join = h('div', { class: 'wfa-join' });
    const ta = h('textarea', { class: 'wfa-composer-input', rows: 1, placeholder: 'Сообщение клиенту', 'aria-label': 'Сообщение клиенту', enterkeyhint: 'send' });
    const form = h('form', { class: 'wfa-composer' }, [ta, h('button', { type: 'submit', class: 'wfa-send', 'aria-label': 'Отправить', html: I.send })]);
    const closeB = h('button', { type: 'button', class: 'wfa-text-btn', text: 'Закрыть', onclick: () => closeChat(id) });
    const v = pushView(it.contact || 'Клиент №' + id, { backLabel: 'Чаты', subtitle: sub, actions: [waBtn, closeB], footer: h('div', { class: 'wfa-chat-foot' }, [join, form]), cls: 'is-chat' });
    v.chat = id; v.body.appendChild(log);
    const st = { msgs: [], status: it.status || '', contact: it.contact || '' };
    chatView = { v, id, st, log, sub, join, waBtn };
    v.onclose = () => { clearTimeout(chatTimer); chatView = null; };
    ta.addEventListener('input', () => { ta.style.height = 'auto'; ta.style.height = Math.min(140, ta.scrollHeight) + 'px'; });
    ta.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && !phoneUI()) { e.preventDefault(); form.requestSubmit(); } });
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const t = ta.value.trim(); if (!t) return;
      ta.value = ''; ta.style.height = '';
      chatAction('/send', { text: t }).catch((err) => { ta.value = t; fail(err); });
    });
    if (location.hash !== '#chat-' + id) history.replaceState(null, '', '#chat-' + id);
    push(v);
    if (instant) v.el.getAnimations && v.el.getAnimations().forEach((a) => a.finish());
    drawJoin();
    pollChat(0, true);
  }
  function drawJoin() {
    if (!chatView) return;
    const { st, join, sub, waBtn } = chatView;
    sub.textContent = (STATUS[st.status] || '') + (st.channel && st.channel !== 'site' ? ' · ' + CHANNEL[st.channel] : '');
    const d = digits(st.contact);
    waBtn.hidden = !d; if (d) waBtn.href = 'https://wa.me/' + d;
    join.innerHTML = '';
    if (st.err) join.appendChild(h('p', { class: 'wfa-sys is-warn', text: 'Не доставлено в ' + (CHANNEL[st.channel] || 'мессенджер') + ': ' + st.err }));
    if (st.status === 'live') {
      join.appendChild(h('div', { class: 'wfa-join-live' }, [h('span', { text: 'Вы в чате — ассистент молчит' }), btn('Вернуть ассистенту', () => chatAction('/release').then(() => toast('Дальше отвечает ассистент')).catch(fail), 'is-small is-ghost')]));
    } else {
      join.appendChild(btn(st.status === 'waiting' ? 'Присоединиться — клиент ждёт' : 'Присоединиться к чату', () => chatAction('/join').then(() => toast('Вы в чате — пишите клиенту')).catch(fail), 'is-wide is-primary'));
    }
  }
  function drawMsgs(list) {
    if (!chatView) return;
    const { log, v } = chatView;
    const sc = v.scroller, stick = sc.scrollHeight - sc.scrollTop - sc.clientHeight < 80;
    const me = (CFG.chats && CFG.chats.manager) || 'Менеджер';
    list.forEach((m) => {
      const node = m.r === 'system' ? h('p', { class: 'wfa-sys', text: m.t }) : h('div', { class: 'wfa-bubble is-' + m.r }, [
        h('small', { text: (m.r === 'user' ? 'Клиент' : m.r === 'bot' ? 'Ассистент' : m.a || me) + (m.at ? ' · ' + clock(m.at) : '') }),
        h('span', { text: m.t }),
      ]);
      log.appendChild(node);
      if (list.length < 4) anim(node, [{ opacity: 0, transform: 'translateY(8px) scale(.98)' }, { opacity: 1, transform: 'none' }], { duration: 320 });
    });
    if (stick || list.length > 3) sc.scrollTop = sc.scrollHeight;
  }
  function pollChat(delay, first) {
    clearTimeout(chatTimer);
    if (!chatView) return;
    const cv = chatView;
    chatTimer = setTimeout(() => {
      const after = cv.st.msgs.length ? cv.st.msgs[cv.st.msgs.length - 1].i : 0;
      adapter.chatApi('GET', '/chat/' + cv.id + '?after=' + after).then((d) => {
        if (chatView !== cv) return;
        const fresh = d.msgs.filter((m) => m.i > after);
        cv.st.msgs = cv.st.msgs.concat(fresh);
        if (fresh.length) drawMsgs(fresh);
        if (d.status !== cv.st.status || d.contact !== cv.st.contact || d.channel !== cv.st.channel || d.send_err !== cv.st.err) { cv.st.status = d.status; cv.st.contact = d.contact; cv.st.channel = d.channel; cv.st.err = d.send_err; drawJoin(); }
        const it = C.list.find((x) => x.id === cv.id); if (it) { it.unread = 0; it.status = d.status; }
        updateBadges();
      }).catch((e) => { if (first) { fail(e); pop(); } }).then(() => { if (chatView === cv) pollChat(document.hidden ? 15000 : 3000); });
    }, delay || 0);
  }
  function chatAction(path, body) {
    const cv = chatView;
    return adapter.chatApi('POST', '/chat/' + cv.id + path, body).then((r) => {
      if (r.status) cv.st.status = r.status;
      if (path === '/send') cv.st.status = 'live';
      const it = C.list.find((x) => x.id === cv.id); if (it) it.status = cv.st.status;
      drawJoin(); pollChat(0); return r;
    });
  }
  function pollList() {
    clearTimeout(listTimer);
    listTimer = setTimeout(() => {
      if (document.hidden) return pollList();
      adapter.chatApi('GET', '/chats').then((d) => {
        const before = JSON.stringify(C.list);
        setChats(d.chats);
        if (JSON.stringify(C.list) !== before) { updateBadges(); if (tab === 'chats' && !stack.length) render('chats'); }
      }).catch(() => {}).then(pollList);
    }, REFRESH);
  }
  // заявки, вопросы к вам, номера — тоже обновляются сами
  let stateTimer = 0;
  function refreshState() {
    return adapter.api('GET', '/state').then((d) => {
      const known = new Set(S.leads.map((l) => l.id));
      const fresh = d.leads.filter((l) => !known.has(l.id));
      const before = JSON.stringify([S.leads, S.ai && S.ai.questions, S.settings]);
      S = Object.assign(S, d);
      if (fresh.length) toast(fresh.length === 1 ? 'Новая заявка: ' + fresh[0].name : 'Новых заявок: ' + fresh.length);
      if (JSON.stringify([S.leads, S.ai && S.ai.questions, S.settings]) !== before) { updateBadges(); if (!stack.length && (tab === 'leads' || tab === 'train')) render(tab); }
    }).catch(() => {});
  }
  function pollState() {
    clearTimeout(stateTimer);
    stateTimer = setTimeout(() => { (document.hidden ? Promise.resolve() : refreshState()).then(pollState); }, REFRESH);
  }
  // всё сразу: при возврате в приложение, после push-уведомления, при «потянуть вниз»
  function refreshAll() {
    const jobs = [refreshState()];
    if (HAS_CHATS) jobs.push(adapter.chatApi('GET', '/chats').then((d) => { setChats(d.chats); updateBadges(); if (tab === 'chats' && !stack.length) render('chats'); }).catch(() => {}));
    if (chatView) pollChat(0);
    faq = null;
    return Promise.all(jobs);
  }
  document.addEventListener('visibilitychange', () => { if (!document.hidden && S) refreshAll(); });
  if ('serviceWorker' in navigator) navigator.serviceWorker.addEventListener('message', (e) => { if (e.data && e.data.type === 'webfor-refresh' && S) refreshAll(); });
  window.addEventListener('hashchange', () => {
    const m = /^#chat-(\d+)$/.exec(location.hash);
    if (m && S && (!chatView || chatView.id !== +m[1])) openChat(+m[1]);
    if (location.hash === '#leads' && S) { leadFilter = 'new'; refreshState().then(() => setTab('leads')); }
  });

  /* уведомления и установка */
  function pushState() {
    if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) return 'unsupported';
    return Notification.permission;
  }
  const standalone = () => (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
  const isIOS = /iPhone|iPad|iPod/.test(navigator.userAgent);
  function notifyCards() {
    const out = [];
    if (!HAS_CHATS) return out;
    if (embedded) {
      if (CFG.app) out.push(h('a', { class: 'wfa-card is-tip', href: CFG.app, target: '_blank', rel: 'noopener' }, [ico('install'), h('div', null, [h('b', { text: 'Админка на телефоне' }), h('p', { text: 'Откройте её на телефоне и установите как приложение — придут уведомления, когда клиенту нужен менеджер.' })])]));
      return out;
    }
    if (!standalone() && ls.get('wfa-install-hide') !== '1') {
      const card = h('div', { class: 'wfa-card is-tip' }, [ico('install'), h('div', null, [h('b', { text: 'Установите как приложение' }),
        h('p', { text: isIOS ? 'В Safari нажмите «Поделиться» → «На экран Домой» и откройте админку со значка. Так придут уведомления.' : 'В меню браузера выберите «Установить приложение» или «Добавить на главный экран».' })]),
        h('button', { type: 'button', class: 'wfa-icon-btn', 'aria-label': 'Скрыть', html: I.close, onclick: () => { ls.set('wfa-install-hide', '1'); card.remove(); } })]);
      out.push(card);
    }
    const p = pushState();
    if (p === 'default' || (p === 'granted' && ls.get('wfa-push-ok') !== '1')) {
      out.push(h('div', { class: 'wfa-card is-alert' }, [ico('bell'), h('div', null, [h('b', { text: 'Включите уведомления' }), h('p', { text: 'Чтобы сразу узнавать, когда клиенту нужен менеджер.' })]),
        btn('Включить', () => enablePush(), 'is-small is-primary')]));
    }
    return out;
  }
  function b64ToBytes(s) {
    const pad = '='.repeat((4 - (s.length % 4)) % 4), raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
  }
  function subscribePush() {
    if (CFG.demo) return Promise.reject(new Error('Это демо — на вашем сайте здесь включаются уведомления на телефон'));
    if (pushState() === 'unsupported') return Promise.reject(new Error(isIOS ? 'На iPhone уведомления работают, когда админка открыта со значка на экране «Домой».' : 'Этот браузер не умеет уведомления.'));
    if (!CFG.chats || !CFG.chats.vapid) return Promise.reject(new Error('Ключи уведомлений не созданы на сервере'));
    return Notification.requestPermission().then((p) => {
      if (p !== 'granted') throw new Error('Разрешите уведомления в настройках телефона');
      return navigator.serviceWorker.register(CFG.sw);
    }).then(() => navigator.serviceWorker.ready)
      .then((reg) => reg.pushManager.getSubscription().then((s) => s || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(CFG.chats.vapid) })))
      .then((sub) => adapter.chatApi('POST', '/push/subscribe', { subscription: sub.toJSON() }));
  }
  function enablePush() {
    return subscribePush().then(() => { ls.set('wfa-push-ok', '1'); toast('Готово: уведомления будут приходить сюда'); render(tab); })
      .catch((e) => { fail(e); render(tab); });
  }
  if ('serviceWorker' in navigator && CFG.sw) navigator.serviceWorker.register(CFG.sw).catch(() => {});

  /* ================= ЗАЯВКИ ================= */
  function viewLeads(body) {
    const st = S.statuses;
    const n = (k) => S.leads.filter((l) => k === 'all' || l.status === k).length;
    body.appendChild(segmented([['new', st.new || 'Новые', n('new')], ['work', st.work || 'В работе', n('work')], ['done', st.done || 'Готово'], ['all', 'Все']], leadFilter, (k) => { leadFilter = k; render('leads'); }));
    const list = S.leads.filter((l) => leadFilter === 'all' || l.status === leadFilter);
    if (!list.length) { body.appendChild(empty('leads', leadFilter === 'new' ? 'Новых заявок нет' : 'Здесь пусто', 'Заявки приходят из формы на сайте. Новые сразу видно здесь.')); return; }
    body.appendChild(h('div', { class: 'wfa-cards' }, list.map(leadCard)));
  }
  function leadCard(l) {
    const wa = l.contact === 'whatsapp';
    return h('article', { class: 'wfa-lead is-' + l.status }, [
      h('button', { type: 'button', class: 'wfa-lead-main', onclick: () => leadSheet(l) }, [
        h('span', { class: 'wfa-lead-top' }, [h('b', { text: l.name }), h('time', { text: timeAgo(l.date) })]),
        h('span', { class: 'wfa-lead-svc', text: l.service_label }),
        h('span', { class: 'wfa-lead-phone', text: l.phone }),
        l.message ? h('span', { class: 'wfa-lead-msg', text: l.message }) : null,
      ]),
      h('div', { class: 'wfa-row2' }, [
        h('a', { class: 'wfa-btn ' + (wa ? 'is-ghost' : 'is-primary'), href: l.tel }, [ico('phone'), 'Позвонить']),
        h('a', { class: 'wfa-btn ' + (wa ? 'is-primary' : 'is-ghost'), href: l.wa, target: '_blank', rel: 'noopener' }, [ico('wa'), 'WhatsApp']),
      ]),
    ]);
  }
  function leadSheet(l) {
    const seg = h('div', { class: 'wfa-choice' }, Object.keys(S.statuses).map((k) => h('button', { type: 'button', class: 'is-' + k, 'data-k': k, 'aria-pressed': l.status === k ? 'true' : 'false', text: S.statuses[k], onclick: () => {
      const prev = l.status; l.status = k;
      seg.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', b.dataset.k === k ? 'true' : 'false'));
      adapter.api('POST', '/lead/' + l.id, { status: k }).then(() => { toast('Статус: ' + S.statuses[k]); render('leads'); }).catch((e) => { l.status = prev; fail(e); });
    } })));
    const sh = sheet(l.name, [
      h('div', { class: 'wfa-detail' }, [
        h('span', { text: l.service_label + ' · ' + (l.contact === 'whatsapp' ? 'просит написать в WhatsApp' : 'просит позвонить') }),
        h('a', { class: 'wfa-detail-phone', href: l.tel, text: l.phone }),
        l.message ? h('p', { class: 'wfa-quote', text: l.message }) : null,
        h('small', { text: 'Пришла: ' + new Date(l.date).toLocaleString('ru-RU', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }) }),
      ]),
      field('Статус', seg),
      h('div', { class: 'wfa-row2' }, [h('a', { class: 'wfa-btn is-primary', href: l.tel }, [ico('phone'), 'Позвонить']), h('a', { class: 'wfa-btn is-ghost', href: l.wa, target: '_blank', rel: 'noopener' }, [ico('wa'), 'WhatsApp'])]),
      confirmBtn('Удалить заявку', () => adapter.api('DELETE', '/lead/' + l.id).then(() => { S.leads = S.leads.filter((x) => x !== l); sh.close(); render('leads'); toast('Заявка удалена'); }).catch(fail)),
    ]);
  }

  /* ================= ОБУЧЕНИЕ ================= */
  // база знаний — обычный текст; темы разделены пустой строкой, первая строка темы — её название
  function parseKB(text) {
    return String(text || '').split(/\n\s*\n/).map((b) => b.trim()).filter(Boolean).map((b) => {
      const lines = b.split('\n');
      return { title: lines[0].trim(), body: lines.slice(1).join('\n').trim() };
    });
  }
  const joinKB = (items) => items.filter((i) => i.title.trim() || i.body.trim())
    .map((i) => (i.title.trim() + '\n' + i.body.replace(/\n\s*\n+/g, '\n').trim()).trim()).join('\n\n');
  function saveKB(items, msg) {
    const text = joinKB(items);
    return adapter.api('POST', '/ai/knowledge', { knowledge: text }).then(() => { S.ai.knowledge = text; render('train'); toast(msg || 'Сохранено — ассистент уже знает'); });
  }
  function saveLearned(rows, msg) {
    return adapter.api('POST', '/ai/learned', { rows }).then((r) => { S.ai.learned = r.learned || rows; render('train'); toast(msg || 'Сохранено'); });
  }
  const normQ = (t) => String(t || '').toLowerCase().replace(/[^\p{L}\p{N}\s]/gu, '').replace(/\s+/g, ' ').trim();
  function viewTrain(body) {
    const ai = S.ai;
    if (!ai) { body.appendChild(empty('train', 'AI-ассистент не установлен', 'Установите плагин WebFor AI Assistant — здесь появится его обучение.')); return; }
    const model = String(ai.model || '');
    body.appendChild(h('div', { class: 'wfa-card ' + (ai.has_key ? 'is-ok' : 'is-alert') }, [ico(ai.has_key ? 'check' : 'bell'), h('div', null, [
      h('b', { text: ai.has_key ? 'Ассистент отвечает клиентам' : 'Ассистент не подключён' }),
      h('p', { text: ai.has_key ? (model.indexOf('gemini') === 0 ? 'Gemini' : 'Claude') + ' · ' + model + '. Чего он не знает — спросит у вас здесь.' : 'Нет ключа API — все вопросы сразу приходят вам. Добавьте ключ в настройках.' }),
    ])]));

    body.appendChild(h('button', { type: 'button', class: 'wfa-hero', onclick: openTest }, [h('span', { class: 'wfa-hero-ico' }, ico('test')), h('span', { class: 'wfa-hero-text' }, [h('b', { text: 'Проверить ассистента' }), h('span', { text: 'Спросите как клиент и посмотрите ответ' })]), ico('next', 'wfa-chev')]));

    const qs = ai.questions.filter((q) => q.state !== 'answered');
    const qRow = (q) => row({ icon: 'chats', tint: 'accent', title: q.q, sub: (q.contact ? '☎ ' + q.contact + ' · ' : '') + timeAgo(q.date), onclick: () => questionSheet(q) });
    const qRows = qs.length ? qs.slice(0, 5).map(qRow) : [staticRow('Все вопросы отвечены', 'check')];
    if (qs.length > 5) qRows.push(row({ title: 'Все вопросы без ответа', value: String(qs.length), onclick: () => {
      const v = pushView('Нужен ваш ответ', { backLabel: 'Обучение' });
      v.body.appendChild(h('div', { class: 'wfa-list' }, qs.map(qRow)));
      push(v);
    } }));
    body.appendChild(group('Нужен ваш ответ', qRows,
    'Ассистент не знал ответа и спросил вас. Ответьте — и в следующий раз он ответит сам.'));

    const faqBox = h('div');
    body.appendChild(faqBox);
    drawFaq(faqBox);

    const kb = parseKB(ai.knowledge);
    body.appendChild(group('Что знает ассистент', kb.map((it, i) => row({ icon: 'book', tint: 'blue', title: it.title, sub: it.body.split('\n')[0], onclick: () => kbSheet(kb, i) }))
      .concat([row({ icon: 'plus', tint: 'accent', title: 'Добавить знание', sub: 'Цены, услуги, правила — обычным текстом', onclick: () => kbSheet(kb, -1) }),
        row({ icon: 'file', tint: 'accent', title: 'Загрузить из файла', sub: 'Текстовый файл .txt', onclick: () => kbFromFile(kb) })]),
    'Главное, по чему отвечает ассистент. Чего здесь нет, он не придумывает — спросит у вас.'));

    body.appendChild(group('Готовые ответы', ai.learned.map((r, i) => row({ icon: 'star', tint: 'gold', title: r.q, sub: r.a, onclick: () => learnedSheet(i) }))
      .concat([row({ icon: 'plus', tint: 'accent', title: 'Добавить ответ', sub: 'Вопрос клиента и как на него отвечать', onclick: () => learnedSheet(-1) })]),
    'Точные ответы на конкретные вопросы — ассистент отвечает так же.'));
  }
  function drawFaq(box) {
    box.innerHTML = '';
    if (!HAS_CHATS) return;
    if (!faq) {
      box.appendChild(group('Частые вопросы', [staticRow('Загружаем…')]));
      adapter.chatApi('GET', '/faq').then((d) => { faq = d.items || []; if (box.isConnected) drawFaq(box); }).catch(() => {});
      return;
    }
    const known = {}; S.ai.learned.forEach((r) => { known[normQ(r.q)] = true; });
    const faqRow = (it) => row({ icon: known[normQ(it.q)] ? 'check' : 'chats', tint: known[normQ(it.q)] ? 'ok' : 'blue', title: it.q, sub: known[normQ(it.q)] ? 'Ответ есть' : 'Нет точного ответа — нажмите, чтобы дать', badge: it.n > 1 ? '×' + it.n : null, onclick: () => faqSheet(it) });
    const rows = faq.length ? faq.slice(0, 5).map(faqRow) : [staticRow('Клиенты ещё ничего не спрашивали')];
    if (faq.length > 5) rows.push(row({ title: 'Все вопросы клиентов', value: String(faq.length), onclick: () => {
      const v = pushView('Вопросы клиентов', { backLabel: 'Обучение' });
      v.body.appendChild(h('div', { class: 'wfa-list' }, faq.map(faqRow)));
      push(v);
    } }));
    box.appendChild(group('Частые вопросы', rows, 'Что клиенты спрашивают чаще всего. Дайте точный ответ — ассистент запомнит.'));
  }
  function faqSheet(it) {
    const old = S.ai.learned.find((r) => normQ(r.q) === normQ(it.q));
    const a = area(old ? old.a : '', 5, 'Как ассистент должен отвечать на этот вопрос');
    const sh = sheet('Ответ на частый вопрос', [h('p', { class: 'wfa-quote', text: it.q }), h('small', { class: 'wfa-muted', text: 'Спросили ' + plural(it.n, 'раз', 'раза', 'раз') }), field('Ответ', a)], { focus: true, actions: [
      btn('Сохранить ответ', () => {
        if (!a.value.trim()) { a.focus(); toast('Напишите ответ', true); return; }
        const rows = S.ai.learned.filter((r) => normQ(r.q) !== normQ(it.q)).concat([{ q: it.q, a: a.value.trim() }]);
        saveLearned(rows, 'Готово — ассистент будет отвечать так').then(() => { sh.close(); if (stack.length) pop(); }).catch(fail);
      }, 'is-wide is-primary'),
    ] });
  }
  function questionSheet(q) {
    const a = area(q.answer, 5, 'Ваш ответ клиенту');
    const learn = h('input', { type: 'checkbox', checked: true });
    const d = digits(q.contact);
    const sh = sheet('Вопрос клиента', [
      h('p', { class: 'wfa-quote', text: q.q }),
      h('small', { class: 'wfa-muted', text: 'Почему спросил вас: ' + (q.reason || '—') + (q.contact ? ' · Контакт: ' + q.contact : ' · Клиент не оставил номер') }),
      q.transcript && q.transcript.length ? h('details', { class: 'wfa-transcript' }, [h('summary', { text: 'Весь разговор' })].concat(q.transcript.map((m) => h('p', null, [h('b', { text: m.role === 'user' ? 'Клиент: ' : 'Ассистент: ' }), m.content])))) : null,
      field('Ответ', a),
      q.learned ? h('small', { class: 'wfa-muted', text: '✓ Уже в готовых ответах' }) : h('label', { class: 'wfa-switch' }, [learn, h('span', { class: 'wfa-switch-ui' }), h('span', { text: 'Запомнить — в следующий раз ассистент ответит сам' })]),
    ], { focus: true, actions: [
      btn('Сохранить ответ', () => {
        if (!a.value.trim()) { a.focus(); toast('Напишите ответ', true); return; }
        adapter.api('POST', '/ai/question/' + q.id, { answer: a.value.trim(), learn: learn.checked && !q.learned }).then((r) => {
          q.answer = a.value.trim(); q.state = 'answered'; if (learn.checked) q.learned = true; S.ai.learned = r.learned || S.ai.learned;
          sh.close(); if (stack.length) pop(); render('train'); toast(learn.checked ? 'Ответ сохранён и запомнен' : 'Ответ сохранён');
        }).catch(fail);
      }, 'is-wide is-primary'),
      d ? h('a', { class: 'wfa-btn is-wide is-ghost', target: '_blank', rel: 'noopener', href: 'https://wa.me/' + d, onclick: (e) => { e.currentTarget.href = 'https://wa.me/' + d + '?text=' + encodeURIComponent(a.value); } }, [ico('wa'), 'Ответить в WhatsApp']) : null,
      confirmBtn('Удалить вопрос', () => adapter.api('DELETE', '/ai/question/' + q.id).then(() => { S.ai.questions = S.ai.questions.filter((x) => x !== q); sh.close(); render('train'); }).catch(fail)),
    ] });
  }
  function kbSheet(kb, i, preset) {
    const it = i >= 0 ? kb[i] : (preset || { title: '', body: '' });
    const t = inputEl(it.title, 'text', 'Например: Доставка и оплата');
    const b = area(it.body, 10, 'Пишите как для нового сотрудника: цены, сроки, условия, частые вопросы.');
    const sh = sheet(i >= 0 ? 'Знание' : 'Новое знание', [field('Тема', t), field('Что должен знать ассистент', b, 'Можно несколько строк.')], { focus: i < 0, actions: [
      btn('Сохранить', () => {
        if (!t.value.trim() || !b.value.trim()) { (t.value.trim() ? b : t).focus(); toast('Заполните тему и текст', true); return; }
        const next = kb.slice(); const item = { title: t.value.trim().replace(/\n/g, ' '), body: b.value.trim() };
        if (i >= 0) next[i] = item; else next.push(item);
        saveKB(next).then(() => sh.close()).catch(fail);
      }, 'is-wide is-primary'),
      i >= 0 ? confirmBtn('Удалить знание', () => { const next = kb.slice(); next.splice(i, 1); saveKB(next, 'Удалено').then(() => sh.close()).catch(fail); }) : null,
    ] });
  }
  function kbFromFile(kb) {
    pickFile('.txt,.md,.csv,text/plain').then((f) => {
      if (!f) return;
      if (f.size > 300000) { toast('Файл слишком большой — до 300 КБ текста', true); return; }
      f.text().then((txt) => {
        txt = txt.replace(/\r/g, '').trim();
        if (!txt) { toast('Файл пустой', true); return; }
        kbSheet(kb, -1, { title: f.name.replace(/\.[^.]+$/, ''), body: txt });
      }).catch(() => toast('Не получилось прочитать файл. Нужен обычный текст (.txt).', true));
    });
  }
  function learnedSheet(i) {
    const r = i >= 0 ? S.ai.learned[i] : { q: '', a: '' };
    const q = area(r.q, 2, 'Как спрашивает клиент'); const a = area(r.a, 5, 'Как правильно ответить');
    const sh = sheet(i >= 0 ? 'Готовый ответ' : 'Новый ответ', [field('Вопрос клиента', q), field('Ответ ассистента', a)], { focus: i < 0, actions: [
      btn('Сохранить', () => {
        if (!q.value.trim() || !a.value.trim()) { toast('Заполните вопрос и ответ', true); return; }
        const rows = S.ai.learned.slice(); const item = { q: q.value.trim(), a: a.value.trim() };
        if (i >= 0) rows[i] = item; else rows.push(item);
        saveLearned(rows).then(() => sh.close()).catch(fail);
      }, 'is-wide is-primary'),
      i >= 0 ? confirmBtn('Удалить ответ', () => { const rows = S.ai.learned.slice(); rows.splice(i, 1); saveLearned(rows, 'Удалено').then(() => sh.close()).catch(fail); }) : null,
    ] });
  }
  // проверка: разговор с ассистентом от лица клиента, ничего не сохраняется
  function openTest() {
    if (!HAS_CHATS) { toast('Нужен плагин WebFor AI Assistant', true); return; }
    const log = h('div', { class: 'wfa-msgs' });
    const ta = h('textarea', { class: 'wfa-composer-input', rows: 1, placeholder: 'Спросите как клиент', 'aria-label': 'Вопрос ассистенту', enterkeyhint: 'send' });
    const form = h('form', { class: 'wfa-composer' }, [ta, h('button', { type: 'submit', class: 'wfa-send', 'aria-label': 'Отправить', html: I.send })]);
    const chipsBox = h('div', { class: 'wfa-chips' }, ['Сколько стоит сайт?', 'Сделаете скидку?', 'Какие сроки?'].map((t) => h('button', { type: 'button', class: 'wfa-chip', text: t, onclick: () => ask(t) })));
    const v = pushView('Проверка', { backLabel: 'Обучение', footer: h('div', { class: 'wfa-chat-foot' }, [chipsBox, form]), cls: 'is-chat' });
    v.body.append(h('p', { class: 'wfa-note', text: 'Это проверка: клиенты её не видят, вопросы никуда не сохраняются.' }), log);
    const hist = [];
    let busy = false;
    function bubble(r, t, note) {
      const n = h('div', { class: 'wfa-bubble is-' + (r === 'user' ? 'user' : 'bot') }, [h('small', { text: r === 'user' ? 'Вы как клиент' : 'Ассистент' }), h('span', { text: t })]);
      log.appendChild(n); if (note) log.appendChild(h('p', { class: 'wfa-sys is-warn', text: note }));
      anim(n, [{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'none' }], { duration: 300 });
      v.scroller.scrollTop = v.scroller.scrollHeight;
    }
    function ask(t) {
      t = t.trim(); if (!t || busy) return;
      busy = true; chipsBox.hidden = true; bubble('user', t);
      const typing = h('div', { class: 'wfa-bubble is-bot is-typing' }, [h('i'), h('i'), h('i')]); log.appendChild(typing);
      v.scroller.scrollTop = v.scroller.scrollHeight;
      adapter.chatApi('POST', '/test', { text: t, history: hist.slice(-10) }).then((r) => {
        typing.remove();
        hist.push({ role: 'user', content: t }, { role: 'assistant', content: r.reply });
        bubble('bot', r.reply || '—', r.escalated ? 'Здесь ассистент позвал бы вас: ' + (r.reason || 'нужен менеджер') + '. Добавьте ответ в «Готовые ответы» или «Что знает ассистент».' : r.unsupported ? 'Этот язык ассистент не поддерживает.' : null);
      }).catch((e) => { typing.remove(); fail(e); }).then(() => { busy = false; });
    }
    form.addEventListener('submit', (e) => { e.preventDefault(); const t = ta.value; ta.value = ''; ask(t); });
    push(v);
  }

  /* ================= ЕЩЁ ================= */
  function viewMore(body) {
    const rowsN = [];
    if (HAS_CHATS && !embedded) {
      const p = pushState();
      rowsN.push(row({ icon: 'bell', tint: p === 'granted' ? 'ok' : 'red', title: 'Уведомления', value: p === 'granted' ? 'Включены' : p === 'denied' ? 'Запрещены' : p === 'unsupported' ? 'Недоступны' : 'Выключены',
        onclick: () => (p === 'granted' ? subscribePush().then(() => adapter.chatApi('POST', '/push/test')).then((r) => toast(r.sent ? 'Тестовое уведомление отправлено' : 'Не дошло — нажмите ещё раз', !r.sent)).catch(fail)
          : p === 'denied' ? toast('Разрешите уведомления для этого сайта в настройках телефона', true) : enablePush()) }));
      rowsN.push(row({ icon: 'install', tint: 'blue', title: 'Как установить на телефон', onclick: installSheet }));
    } else if (HAS_CHATS && CFG.app) {
      rowsN.push(row({ icon: 'install', tint: 'blue', title: 'Админка на телефоне', sub: 'Установите и получайте уведомления', href: CFG.app, blank: true }));
    }
    if (rowsN.length) body.appendChild(group('Телефон', rowsN, 'Уведомление приходит, когда ассистент зовёт менеджера или клиент пишет в чат, где вы уже есть. Нажмите на «Включены», чтобы проверить.'));
    if (HAS_CHATS) {
      const mBox = h('div');
      body.appendChild(mBox);
      drawChannels(mBox);
    }
    body.appendChild(group('Контакты на сайте', [
      row({ icon: 'phone', tint: 'accent', title: 'Телефон', value: S.settings.phone || '—', onclick: numbersSheet }),
      row({ icon: 'wa', tint: 'ok', title: 'WhatsApp', value: S.settings.whatsapp || '—', onclick: numbersSheet }),
    ], 'Меняются на всём сайте сразу.'));
    body.appendChild(group('Сайт', [
      row({ icon: 'grid', tint: 'blue', title: 'Работы в папках', value: String(S.works.length), onclick: openWorks }),
      row({ icon: 'globe', tint: 'blue', title: 'Открыть сайт', href: S.site_url, blank: true }),
      S.ai && S.ai.settings_url ? row({ icon: 'gear', tint: 'gray', title: 'Ключ и модель ассистента', href: S.ai.settings_url, blank: !embedded }) : null,
    ]));
    if (CFG.logout) body.appendChild(group(null, [row({ icon: 'logout', tint: 'red', title: 'Выйти', href: CFG.logout, chevron: false, cls: 'is-danger' })]));
  }
  /* ---------- мессенджеры: Telegram, WhatsApp, Instagram ---------- */
  let CH = null;
  function drawChannels(box) {
    const rows = ['tg', 'wa', 'ig'].map((k) => row({ icon: 'chats', tint: CH && CH[k].on ? 'ok' : 'gray', title: CHANNEL[k],
      value: !CH ? '…' : CH[k].on ? (CH[k].name || 'Подключён') : 'Не подключён', onclick: () => channelSheet(k) }));
    box.innerHTML = '';
    box.appendChild(group('Мессенджеры', rows, 'Тот же ассистент отвечает в мессенджерах. Разговоры приходят сюда, во вкладку «Чаты», ваши ответы уходят клиенту туда же.'));
    if (!CH) adapter.chatApi('GET', '/channels').then((d) => { CH = d; if (box.isConnected) drawChannels(box); }).catch(() => {});
  }
  function copyRow(label, value) {
    return h('div', { class: 'wfa-copy' }, [h('span', { text: label }), h('code', { text: value }),
      btn('Копировать', () => { (navigator.clipboard ? navigator.clipboard.writeText(value) : Promise.reject()).then(() => toast('Скопировано')).catch(() => toast('Выделите и скопируйте вручную', true)); }, 'is-small is-ghost')]);
  }
  function channelSheet(k) {
    if (!CH) { toast('Загружаем…'); return; }
    const st = CH[k];
    const steps = (list) => h('ol', { class: 'wfa-steps' }, list.map((t) => h('li', { text: t })));
    const status = h('div', { class: 'wfa-card ' + (st.on ? 'is-ok' : 'is-tip') }, [ico(st.on ? 'check' : 'chats'), h('div', null, [h('b', { text: st.on ? 'Подключён' + (st.name ? ': ' + st.name : '') : 'Не подключён' }), h('p', { text: st.on ? 'Ассистент отвечает клиентам в ' + CHANNEL[k] + '.' : 'Сделайте шаги ниже — это 5–15 минут.' })])]);
    const httpsWarn = CH.https ? null : h('p', { class: 'wfa-sys is-warn', text: 'Сайт открывается без https — мессенджеры подключаются только к сайтам с https.' });
    let fields = [], content = [];
    if (k === 'tg') {
      const tok = inputEl('', 'password', st.on ? 'Токен сохранён — вставьте новый, чтобы заменить' : '123456789:AA…');
      fields = [['tg_token', tok]];
      content = [status, httpsWarn, steps([
        'Откройте в Telegram @BotFather и отправьте /newbot.',
        'Придумайте имя (например «WebFor») и адрес бота — он должен заканчиваться на bot.',
        'BotFather пришлёт токен — длинную строку с двоеточием. Скопируйте её.',
        'Вставьте токен ниже и нажмите «Подключить». Готово — напишите боту, он ответит.',
        'Чтобы бот отвечал от вашего личного аккаунта (нужен Telegram Premium): Настройки → Telegram для бизнеса → Чат-боты → выберите этого бота.',
      ]), field('Токен бота', tok)];
    } else if (k === 'wa') {
      const pid = inputEl('', 'text', st.on ? 'Сохранён' : 'Например 1234567890'), tok = inputEl('', 'password', st.on ? 'Сохранён' : 'EAA…'), sec = inputEl('', 'password', CH.meta_secret_set ? 'Сохранён' : 'Ключ приложения (App Secret)');
      fields = [['wa_phone_id', pid], ['wa_token', tok], ['meta_secret', sec]];
      content = [status, httpsWarn, steps([
        'Зайдите на developers.facebook.com → «Мои приложения» → «Создать приложение» → тип «Бизнес», выберите свой бизнес-аккаунт.',
        'В приложении добавьте продукт WhatsApp и подключите свой номер (WhatsApp → Настройка API → «Добавить номер телефона»).',
        'Скопируйте «Идентификатор номера телефона» (Phone number ID) — в поле ниже.',
        'Постоянный токен: business.facebook.com → Настройки компании → Пользователи системы → добавить (админ) → «Создать токен», выбрать приложение и права whatsapp_business_messaging и whatsapp_business_management.',
        'Ключ приложения: в приложении «Настройки приложения → Основное» → «Секрет приложения» → Показать.',
        'Вебхук: WhatsApp → Конфигурация → «Изменить» — вставьте адрес и проверочный ключ ниже, затем «Управлять» → подпишитесь на поле messages.',
      ]), copyRow('Адрес обратного вызова (Callback URL)', CH.meta_webhook), copyRow('Проверочный ключ (Verify token)', CH.meta_verify),
      field('Phone number ID', pid), field('Постоянный токен', tok), field('Секрет приложения', sec, 'Нужен, чтобы сайт отвечал только на настоящие сообщения от Meta.')];
    } else {
      const tok = inputEl('', 'password', st.on ? 'Токен сохранён — вставьте новый, чтобы заменить' : 'IGAA…'), sec = inputEl('', 'password', CH.meta_secret_set ? 'Сохранён' : 'Тот же секрет приложения, что для WhatsApp');
      fields = [['ig_token', tok], ['meta_secret', sec]];
      content = [status, httpsWarn, steps([
        'Аккаунт Instagram должен быть профессиональным (Настройки → Тип аккаунта → Бизнес или Автор).',
        'В том же приложении на developers.facebook.com добавьте продукт Instagram → «API с входом через Instagram».',
        'Нажмите «Добавить аккаунт» и войдите в свой Instagram, затем «Создать токен» — скопируйте его в поле ниже.',
        'Вебхуки: там же «Настроить вебхуки» — вставьте адрес и проверочный ключ ниже, подпишитесь на messages.',
        'В приложении Instagram: Настройки → Сообщения → разрешите доступ к сообщениям для подключённых сервисов.',
      ]), copyRow('Адрес обратного вызова (Callback URL)', CH.meta_webhook), copyRow('Проверочный ключ (Verify token)', CH.meta_verify),
      field('Токен Instagram', tok), field('Секрет приложения', sec)];
    }
    const sh = sheet(CHANNEL[k], content, { actions: [
      btn(st.on ? 'Сохранить и проверить' : 'Подключить', () => {
        const body = { ch: k };
        fields.forEach(([key, el]) => { if (el.value.trim()) body[key] = el.value.trim(); });
        if (!st.on && k === 'wa' && (!body.wa_phone_id || !body.wa_token)) { toast('Заполните Phone number ID и токен', true); return; }
        if (!st.on && k === 'tg' && !body.tg_token) { toast('Вставьте токен бота', true); return; }
        if (!st.on && k === 'ig' && !body.ig_token) { toast('Вставьте токен Instagram', true); return; }
        adapter.chatApi('POST', '/channels', body).then((d) => { CH = d; sh.close(); render('more'); toast(CHANNEL[k] + ' подключён'); }).catch(fail);
      }, 'is-wide is-primary'),
      st.on ? confirmBtn('Отключить ' + CHANNEL[k], () => adapter.chatApi('POST', '/channels', { ch: k, off: 1 }).then((d) => { CH = d; sh.close(); render('more'); toast(CHANNEL[k] + ' отключён'); }).catch(fail)) : null,
    ] });
  }

  function numbersSheet() {
    const ph = inputEl(S.settings.phone, 'tel'), wa = inputEl(S.settings.whatsapp, 'tel');
    const sh = sheet('Контакты на сайте', [field('Телефон для звонков', ph), field('Номер WhatsApp', wa, 'Телефон и WhatsApp независимы: поменяли один — второй остался прежним.')], { actions: [
      btn('Сохранить', () => adapter.api('POST', '/settings', { phone: ph.value, whatsapp: wa.value }).then((r) => { S.settings = r; sh.close(); render('more'); toast('Номера обновлены на сайте'); }).catch(fail), 'is-wide is-primary'),
    ] });
  }
  function installSheet() {
    sheet('Установить на телефон', [h('ol', { class: 'wfa-steps' }, isIOS ? [
      h('li', { text: 'Откройте эту страницу в Safari.' }), h('li', { text: 'Нажмите «Поделиться» (квадрат со стрелкой) внизу экрана.' }),
      h('li', { text: 'Выберите «На экран Домой» → «Добавить».' }), h('li', { text: 'Откройте админку со значка WebFor и включите уведомления.' }),
    ] : [
      h('li', { text: 'Откройте эту страницу в Chrome.' }), h('li', { text: 'Меню ⋮ → «Установить приложение» (или «Добавить на главный экран»).' }),
      h('li', { text: 'Откройте админку со значка WebFor и включите уведомления.' }),
    ])]);
  }
  let worksFolder = null;
  function openWorks() {
    let draw = () => {};
    const v = pushView('Работы', { backLabel: 'Ещё', actions: [h('button', { type: 'button', class: 'wfa-icon-btn', 'aria-label': 'Добавить работу', html: I.plus, onclick: () => workSheet(null, draw) })] });
    worksFolder = worksFolder || (S.folders[0] && S.folders[0].key);
    draw = () => {
      // меню папок прокручивается вбок — запоминаем, где оно было, чтобы после выбора папки не прыгало в начало
      const old = v.body.querySelector('.wfa-scroll-x'), left = old ? old.scrollLeft : 0, pageY = v.scroller.scrollTop;
      v.body.innerHTML = '';
      const bar = h('div', { class: 'wfa-scroll-x' }, segmented(S.folders.map((f) => [f.key, f.title.replace(/ — .*/, ''), S.works.filter((w) => w.folder === f.key).length]), worksFolder, (k) => { worksFolder = k; draw(); }));
      v.body.appendChild(bar);
      bar.scrollLeft = left;
      requestAnimationFrame(() => { bar.scrollLeft = left; v.scroller.scrollTop = pageY; });
      const list = S.works.filter((w) => w.folder === worksFolder);
      if (!list.length) { v.body.appendChild(empty('grid', 'Папка пустая', 'Нажмите «+» вверху, чтобы добавить работу.')); return; }
      v.body.appendChild(h('div', { class: 'wfa-works' }, list.map((w, i) => h('div', { class: 'wfa-work' }, [
        h('button', { type: 'button', class: 'wfa-work-open', onclick: () => workSheet(w, draw) }, [
          h('span', { class: 'wfa-work-img' + (w.shape === 'phone' ? ' is-phone' : '') }, w.src ? h('img', { src: w.src, alt: '', loading: 'lazy' }) : ico('image')),
          h('b', { text: w.title }), h('small', { text: w.tag || '' }),
        ]),
        h('div', { class: 'wfa-work-move' }, [
          h('button', { type: 'button', 'aria-label': 'Раньше', disabled: i === 0, html: I.back, onclick: () => moveWork(list, i, -1, draw) }),
          h('button', { type: 'button', 'aria-label': 'Позже', disabled: i === list.length - 1, html: I.next, onclick: () => moveWork(list, i, 1, draw) }),
        ]),
      ]))));
    };
    draw();
    push(v);
  }
  function moveWork(list, i, dir, draw) {
    const arr = list.slice(); const [m] = arr.splice(i, 1); arr.splice(i + dir, 0, m);
    S.works = S.works.filter((w) => w.folder !== worksFolder).concat(arr);
    draw();
    adapter.api('POST', '/works/order', { ids: arr.map((w) => w.id) }).then(() => toast('Порядок сохранён')).catch(fail);
  }
  function workSheet(w, draw) {
    const d = Object.assign({ id: 0, title: '', folder: worksFolder, tag: 'Наш проект', desc: '', url: '', shape: worksFolder === 'sites' || worksFolder === 'izi' ? 'wide' : 'phone', image_id: 0, src: '' }, w || {});
    const prev = h('button', { type: 'button', class: 'wfa-img-pick', onclick: () => adapter.pickImage().then((img) => { if (img) { d.image_id = img.id; d.src = img.url; drawPrev(); } }) });
    function drawPrev() { prev.innerHTML = ''; prev.classList.toggle('is-phone', d.shape === 'phone'); prev.appendChild(d.src ? h('img', { src: d.src, alt: 'Скриншот' }) : h('span', null, [ico('image'), 'Выбрать скриншот'])); }
    drawPrev();
    const title = inputEl(d.title, 'text', 'Например: Сайт ветклиники');
    const folder = h('select', { class: 'wfa-input' }, S.folders.map((f) => h('option', { value: f.key, selected: f.key === d.folder, text: f.title })));
    const shape = h('select', { class: 'wfa-input', onchange: () => { d.shape = shape.value; drawPrev(); } }, [h('option', { value: 'wide', selected: d.shape !== 'phone', text: 'Сайт / компьютер' }), h('option', { value: 'phone', selected: d.shape === 'phone', text: 'Телефон' })]);
    const tag = inputEl(d.tag, 'text', 'Наш проект'); const desc = area(d.desc, 3, 'Что сделали и чем полезно клиенту'); const url = inputEl(d.url, 'url', 'https://');
    const sh = sheet(d.id ? 'Работа' : 'Новая работа', [prev, field('Название', title), field('Папка', folder), field('Вид скриншота', shape), field('Метка', tag), field('Описание', desc), field('Ссылка', url)], { actions: [
      btn('Сохранить', () => {
        if (!title.value.trim()) { title.focus(); toast('Укажите название', true); return; }
        adapter.api('POST', '/work', { id: d.id, title: title.value.trim(), folder: folder.value, shape: shape.value, tag: tag.value, desc: desc.value, url: url.value, image_id: d.image_id || 0, src: d.src }).then((saved) => {
          if (!saved.src && d.src) saved.src = d.src;
          const k = S.works.findIndex((x) => x.id === saved.id);
          if (k >= 0) S.works[k] = saved; else S.works.push(saved);
          worksFolder = saved.folder; sh.close(); draw(); toast('Сохранено — уже на сайте');
        }).catch(fail);
      }, 'is-wide is-primary'),
      d.id ? confirmBtn('Удалить работу', () => adapter.api('DELETE', '/work/' + d.id).then(() => { S.works = S.works.filter((x) => x.id !== d.id); sh.close(); draw(); toast('Работа удалена'); }).catch(fail)) : null,
    ] });
  }

  /* ---------- запуск ---------- */
  stage.appendChild(h('div', { class: 'wfa-boot' }, [h('span', { class: 'wfa-spinner' }), 'Загружаем…']));
  Promise.all([adapter.api('GET', '/state'), HAS_CHATS ? adapter.chatApi('GET', '/chats').catch(() => ({ chats: [] })) : null]).then(([state, chats]) => {
    S = state; if (chats) setChats(chats.chats || []);
    const boot = stage.querySelector('.wfa-boot'); if (boot) boot.remove();
    const m = /^#chat-(\d+)$/.exec(location.hash);
    if (m && HAS_CHATS) tab = 'chats';
    if (location.hash === '#leads') { tab = 'leads'; leadFilter = 'new'; history.replaceState(null, '', location.pathname + location.search); }
    setTab(tab, true);
    requestAnimationFrame(movePill);
    if (m && HAS_CHATS) openChat(+m[1], true);
    if (HAS_CHATS) pollList();
    pollState();
    if (CFG.demo) setTimeout(() => toast('Демо: данные — примеры, ничего не сохраняется'), 600);
  }).catch((e) => {
    stage.innerHTML = '';
    stage.appendChild(empty('close', 'Не удалось загрузить', e.message));
  });
})();
