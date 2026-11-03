/* Панель WebFor: заявки, работы в папках, AI-ассистент, номера.
   Данные — через WFADMIN.adapter (по умолчанию REST WordPress), чтобы ту же панель можно было показать в демо. */
(function () {
  var app = document.getElementById('wfa-app');
  if (!app) return;
  var CFG = window.WFADMIN || {};

  /* ---------- доступ к данным ---------- */
  var adapter = CFG.adapter || {
    api: function (method, path, body) {
      return fetch(CFG.root + path, {
        method: method, credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce },
        body: body ? JSON.stringify(body) : undefined,
      }).then(function (r) {
        return r.json().then(function (d) { if (!r.ok) throw new Error((d && d.message) || 'Ошибка ' + r.status); return d; });
      });
    },
    chatApi: CFG.chats ? function (method, path, body) {
      return fetch(CFG.chats.root + path, {
        method: method, credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce },
        body: body ? JSON.stringify(body) : undefined,
      }).then(function (r) {
        return r.json().then(function (d) { if (!r.ok) throw new Error((d && d.message) || 'Ошибка ' + r.status); return d; });
      });
    } : null,
    pickImage: function () {
      if (!(window.wp && wp.media)) return uploadImage(); // приложение-админка: без медиатеки WordPress
      return new Promise(function (resolve) {
        var frame = wp.media({ title: 'Скриншот работы', button: { text: 'Выбрать' }, library: { type: 'image' }, multiple: false });
        frame.on('select', function () {
          var a = frame.state().get('selection').first().toJSON();
          resolve({ id: a.id, url: (a.sizes && (a.sizes.large || a.sizes.full) || a).url });
        });
        frame.on('close', function () { setTimeout(function () { resolve(null); }, 0); });
        frame.open();
      });
    },
  };

  /* загрузка скриншота напрямую в медиатеку (REST), когда окна медиатеки нет */
  function uploadImage() {
    return new Promise(function (resolve) {
      var inp = document.createElement('input');
      inp.type = 'file'; inp.accept = 'image/*';
      inp.onchange = function () {
        var f = inp.files && inp.files[0];
        if (!f) { resolve(null); return; }
        toast('Загружаем скриншот…');
        fetch(CFG.media, { method: 'POST', credentials: 'same-origin', body: f, headers: {
          'X-WP-Nonce': CFG.nonce, 'Content-Type': f.type || 'image/jpeg',
          'Content-Disposition': 'attachment; filename="' + encodeURIComponent(f.name.replace(/"/g, '')) + '"' } })
          .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.message || 'Не загрузилось'); return d; }); })
          .then(function (d) {
            var sz = d.media_details && d.media_details.sizes || {};
            resolve({ id: d.id, url: (sz.large || sz.full || {}).source_url || d.source_url });
          }).catch(function (e) { fail(e); resolve(null); });
      };
      inp.click();
    });
  }

  /* ---------- мелочи ---------- */
  function h(tag, attrs, kids) {
    var el = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      var v = attrs[k];
      if (v == null || v === false) return;
      if (k === 'class') el.className = v;
      else if (k === 'text') el.textContent = v;
      else if (k.slice(0, 2) === 'on') el.addEventListener(k.slice(2), v);
      else el.setAttribute(k, v === true ? '' : v);
    });
    (Array.isArray(kids) ? kids : kids != null ? [kids] : []).forEach(function (c) {
      if (c == null || c === false) return;
      el.appendChild(typeof c === 'string' || typeof c === 'number' ? document.createTextNode(String(c)) : c);
    });
    return el;
  }
  function fmtDate(iso) {
    var d = new Date(iso);
    if (isNaN(d)) return '';
    var today = new Date(); var same = d.toDateString() === today.toDateString();
    return same ? 'сегодня, ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }) : d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }) + ', ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
  }
  var toastBox = h('div', { class: 'wfa-toasts', 'aria-live': 'polite' });
  function toast(text, bad) {
    var t = h('div', { class: 'wfa-toast' + (bad ? ' is-bad' : ''), text: text });
    toastBox.appendChild(t);
    setTimeout(function () { t.remove(); }, 3200);
  }
  function fail(e) { toast(e && e.message ? e.message : 'Не удалось сохранить', true); }
  /* кнопка удаления с подтверждением прямо в кнопке */
  function confirmBtn(label, onYes) {
    var armed = false, timer;
    var b = h('button', { type: 'button', class: 'wfa-btn wfa-btn-ghost wfa-danger', text: label, onclick: function () {
      if (!armed) { armed = true; b.textContent = 'Точно удалить?'; timer = setTimeout(function () { armed = false; b.textContent = label; }, 3000); return; }
      clearTimeout(timer); onYes();
    } });
    return b;
  }

  /* ---------- состояние ---------- */
  var S = null, tab = 'home', leadFilter = 'all', leadQuery = '', worksFolder = 'sites', aiTab = 'questions';
  try { tab = sessionStorage.getItem('wfa-tab') || 'home'; } catch (e) {}
  var HAS_CHATS = !!adapter.chatApi;
  var C = { list: [], open: 0, msgs: [], status: '', contact: '', draft: '', seenWaiting: null, faq: null };
  readHash();

  function load() {
    return Promise.all([adapter.api('GET', '/state'), HAS_CHATS ? adapter.chatApi('GET', '/chats').catch(function () { return { chats: [] }; }) : null]).then(function (r) {
      S = r[0]; if (r[1]) setChats(r[1].chats);
      render(); if (C.open) openChat(C.open, true);
      if (HAS_CHATS) pollList();
    }).catch(function (e) {
      app.innerHTML = ''; app.appendChild(h('p', { class: 'wfa-loading', text: 'Не удалось загрузить панель: ' + e.message }));
    });
  }
  function counts() {
    return {
      newLeads: S.leads.filter(function (l) { return l.status === 'new'; }).length,
      newQs: S.ai ? S.ai.questions.filter(function (q) { return q.state !== 'answered'; }).length : 0,
      waiting: C.list.filter(function (c) { return c.status === 'waiting'; }).length,
    };
  }
  function go(t) {
    tab = t; try { sessionStorage.setItem('wfa-tab', t); } catch (e) {}
    if (t !== 'chats') { C.open = 0; if (/^#chat-/.test(location.hash)) history.replaceState(null, '', location.pathname + location.search); }
    render(); app.scrollIntoView({ block: 'start' });
  }
  function readHash() {
    var m = /^#chat-(\d+)$/.exec(location.hash);
    if (m && HAS_CHATS) { tab = 'chats'; C.open = +m[1]; }
  }
  window.addEventListener('hashchange', function () { var was = C.open; readHash(); if (S && C.open && C.open !== was) { render(); openChat(C.open); } });

  /* ---------- каркас ---------- */
  function render() {
    var c = counts();
    var tabs = [['home', 'Обзор'], ['chats', 'Чаты', c.waiting], ['leads', 'Заявки', c.newLeads], ['works', 'Работы'], ['ai', 'AI-ассистент', c.newQs], ['settings', 'Номера']];
    if (!S.ai) tabs = tabs.filter(function (t) { return t[0] !== 'ai'; });
    if (!HAS_CHATS) tabs = tabs.filter(function (t) { return t[0] !== 'chats'; });
    if ((tab === 'ai' && !S.ai) || (tab === 'chats' && !HAS_CHATS)) tab = 'home';
    // сохраняем фокус и текст в поле ввода, которое перерисовывается (поиск, ответ в чате)
    var act = document.activeElement, keep = act && act.id && app.contains(act) ? { id: act.id, s: act.selectionStart, e: act.selectionEnd } : null;
    var oldLog = app.querySelector('.wfa-chat-log'), stick = !oldLog || oldLog.scrollHeight - oldLog.scrollTop - oldLog.clientHeight < 40;
    var nav = h('nav', { class: 'wfa-tabs', role: 'tablist' }, tabs.map(function (t) {
      return h('button', { type: 'button', role: 'tab', class: 'wfa-tab', 'aria-selected': tab === t[0] ? 'true' : 'false', onclick: function () { go(t[0]); } },
        [t[1], t[2] ? h('span', { class: 'wfa-badge', text: t[2] }) : null]);
    }));
    var view = { home: viewHome, chats: viewChats, leads: viewLeads, works: viewWorks, ai: viewAi, settings: viewSettings }[tab]();
    app.innerHTML = '';
    app.appendChild(h('header', { class: 'wfa-top' }, [
      h('div', { class: 'wfa-brand' }, [h('span', { class: 'wfa-mark', text: 'W' }), h('div', null, [h('b', { text: 'WebFor' }), h('span', { text: 'Панель управления' })])]),
      h('div', { class: 'wfa-row' }, [
        h('a', { class: 'wfa-btn wfa-btn-ghost', href: S.site_url, target: '_blank', rel: 'noopener', text: 'Открыть сайт ↗' }),
        CFG.logout ? h('a', { class: 'wfa-btn wfa-btn-ghost wfa-logout', href: CFG.logout, text: 'Выйти' }) : null,
      ]),
    ]));
    app.appendChild(nav);
    app.appendChild(h('main', { class: 'wfa-view' }, view));
    app.appendChild(toastBox);
    if (keep) { var el = document.getElementById(keep.id); if (el) { el.focus(); try { el.setSelectionRange(keep.s, keep.e); } catch (e) {} } }
    var log = app.querySelector('.wfa-chat-log'); if (log && stick) log.scrollTop = log.scrollHeight;
    document.documentElement.classList.toggle('wfa-chat-full', tab === 'chats' && !!C.open && matchMedia('(max-width: 782px)').matches);
    if (navigator.setAppBadge) { try { c.waiting ? navigator.setAppBadge(c.waiting) : navigator.clearAppBadge(); } catch (e) {} }
  }
  function busy() { return !!app.querySelector('dialog[open]'); }

  /* ---------- обзор ---------- */
  function viewHome() {
    var c = counts();
    var tiles = h('div', { class: 'wfa-tiles' }, [
      HAS_CHATS ? tile('Чаты', c.waiting ? c.waiting + ' ждут' : C.list.length, c.waiting ? 'клиенту нужен менеджер' : 'разговоров с ассистентом', function () { go('chats'); }, c.waiting > 0) : null,
      tile('Новые заявки', c.newLeads, 'ждут звонка или сообщения', function () { leadFilter = 'new'; go('leads'); }, c.newLeads > 0),
      S.ai ? tile('Вопросы к вам', c.newQs, 'AI не смог ответить сам', function () { aiTab = 'questions'; go('ai'); }, c.newQs > 0) : null,
      tile('Работ на сайте', S.works.length, 'в ' + S.folders.length + ' папках', function () { go('works'); }),
      S.ai ? tile('AI-ассистент', S.ai.has_key ? 'Работает' : 'Нет ключа', S.ai.has_key ? 'выучено ответов: ' + S.ai.learned.length : 'вопросы сразу идут вам', function () { aiTab = 'learn'; go('ai'); }, !S.ai.has_key) : null,
    ]);
    var recent = S.leads.slice(0, 5);
    return [
      h('h2', { class: 'wfa-h', text: 'Обзор' }),
      tiles,
      h('section', { class: 'wfa-card' }, [
        h('div', { class: 'wfa-card-head' }, [h('h3', { text: 'Последние заявки' }), h('button', { type: 'button', class: 'wfa-link', text: 'Все заявки →', onclick: function () { leadFilter = 'all'; go('leads'); } })]),
        recent.length ? h('div', { class: 'wfa-mini' }, recent.map(function (l) {
          return h('div', { class: 'wfa-mini-row' }, [h('span', { class: 'wfa-dot is-' + l.status }), h('b', { text: l.name }), h('span', { text: l.service_label }), h('time', { text: fmtDate(l.date) })]);
        })) : h('p', { class: 'wfa-empty', text: 'Заявок пока нет. Они появятся здесь, как только клиент отправит форму на сайте.' }),
      ]),
      S.ai ? trainHelp() : null,
    ];
  }
  function tile(label, value, note, onclick, hot) {
    return h('button', { type: 'button', class: 'wfa-tile' + (hot ? ' is-hot' : ''), onclick: onclick }, [h('span', { text: label }), h('b', { text: value }), h('small', { text: note })]);
  }
  function trainHelp() {
    return h('section', { class: 'wfa-card wfa-help' }, [
      h('h3', { text: 'Как обучать AI-ассистента' }),
      h('ol', null, [
        h('li', null, [h('b', { text: 'База знаний. ' }), 'Впишите всё, что клиенты должны узнать: услуги, цены «от», сроки, адрес, график. Ассистент отвечает только по ней.']),
        h('li', null, [h('b', { text: 'Вопросы к вам. ' }), 'На что ассистент не нашёл ответа, приходит сюда. Ответьте и оставьте галочку «Запомнить» — в следующий раз он ответит сам.']),
        h('li', null, [h('b', { text: 'Обучение. ' }), 'Список выученных ответов: поправьте формулировку или добавьте пример вручную.']),
      ]),
      h('button', { type: 'button', class: 'wfa-btn', text: 'Открыть базу знаний', onclick: function () { aiTab = 'kb'; go('ai'); } }),
    ]);
  }

  /* ---------- заявки ---------- */
  function viewLeads() {
    var st = S.statuses;
    var list = S.leads.filter(function (l) {
      if (leadFilter !== 'all' && l.status !== leadFilter) return false;
      var q = leadQuery.trim().toLowerCase();
      return !q || (l.name + ' ' + l.phone + ' ' + l.message + ' ' + l.service_label).toLowerCase().indexOf(q) >= 0;
    });
    var filters = h('div', { class: 'wfa-filters' }, [
      h('div', { class: 'wfa-chips' }, [['all', 'Все']].concat(Object.keys(st).map(function (k) { return [k, st[k]]; })).map(function (f) {
        var n = f[0] === 'all' ? S.leads.length : S.leads.filter(function (l) { return l.status === f[0]; }).length;
        return h('button', { type: 'button', class: 'wfa-chip', 'aria-pressed': leadFilter === f[0] ? 'true' : 'false', onclick: function () { leadFilter = f[0]; render(); } }, [f[1] + ' ', h('span', { text: n })]);
      })),
      h('input', { class: 'wfa-input wfa-search', id: 'wfa-lead-search', type: 'search', placeholder: 'Поиск: имя, телефон, текст', value: leadQuery, oninput: function (e) { leadQuery = e.target.value; render(); } }),
    ]);
    return [
      h('h2', { class: 'wfa-h', text: 'Заявки' }), filters,
      list.length ? h('div', { class: 'wfa-leads' }, list.map(leadCard)) : h('p', { class: 'wfa-empty', text: S.leads.length ? 'Под фильтр ничего не подходит.' : 'Заявок пока нет.' }),
    ];
  }
  function leadCard(l) {
    var seg = h('div', { class: 'wfa-seg', role: 'group', 'aria-label': 'Статус заявки' }, Object.keys(S.statuses).map(function (k) {
      return h('button', { type: 'button', class: 'is-' + k, 'aria-pressed': l.status === k ? 'true' : 'false', text: S.statuses[k], onclick: function () {
        var prev = l.status; l.status = k; render();
        adapter.api('POST', '/lead/' + l.id, { status: k }).then(function () { toast('Статус: ' + S.statuses[k]); }).catch(function (e) { l.status = prev; render(); fail(e); });
      } });
    }));
    return h('article', { class: 'wfa-lead is-' + l.status }, [
      h('div', { class: 'wfa-lead-main' }, [
        h('div', { class: 'wfa-lead-title' }, [h('b', { text: l.name }), h('time', { text: fmtDate(l.date) })]),
        h('div', { class: 'wfa-lead-phone', text: l.phone }),
        h('div', { class: 'wfa-lead-svc', text: l.service_label + ' · ' + (l.contact === 'whatsapp' ? 'просит написать в WhatsApp' : 'просит позвонить') }),
        l.message ? h('p', { class: 'wfa-lead-msg', text: l.message }) : null,
      ]),
      h('div', { class: 'wfa-lead-side' }, [
        seg,
        h('div', { class: 'wfa-row' }, [
          h('a', { class: 'wfa-btn' + (l.contact === 'whatsapp' ? '' : ' wfa-btn-ghost'), href: l.wa, target: '_blank', rel: 'noopener', text: 'WhatsApp' }),
          h('a', { class: 'wfa-btn' + (l.contact === 'whatsapp' ? ' wfa-btn-ghost' : ''), href: l.tel, text: 'Позвонить' }),
          confirmBtn('Удалить', function () {
            adapter.api('DELETE', '/lead/' + l.id).then(function () { S.leads = S.leads.filter(function (x) { return x !== l; }); render(); toast('Заявка удалена'); }).catch(fail);
          }),
        ]),
      ]),
    ]);
  }

  /* ---------- работы ---------- */
  var dragId = null;
  function viewWorks() {
    var works = S.works.filter(function (w) { return w.folder === worksFolder; });
    var folderTabs = h('div', { class: 'wfa-chips' }, S.folders.map(function (f) {
      var n = S.works.filter(function (w) { return w.folder === f.key; }).length;
      return h('button', { type: 'button', class: 'wfa-chip', 'aria-pressed': worksFolder === f.key ? 'true' : 'false', onclick: function () { worksFolder = f.key; render(); } }, [f.title + ' ', h('span', { text: n })]);
    }));
    var grid = h('ul', { class: 'wfa-works' }, works.map(function (w, i) {
      var li = h('li', { class: 'wfa-work', draggable: 'true', 'data-id': w.id,
        ondragstart: function (e) { dragId = w.id; li.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; },
        ondragend: function () { li.classList.remove('is-drag'); },
        ondragover: function (e) { e.preventDefault(); },
        ondrop: function (e) { e.preventDefault(); if (dragId && dragId !== w.id) moveWork(dragId, w.id); },
      }, [
        h('button', { type: 'button', class: 'wfa-work-open', title: 'Изменить', onclick: function () { editWork(w); } }, [
          h('span', { class: 'wfa-work-img' + (w.shape === 'phone' ? ' is-phone' : '') }, w.src ? h('img', { src: w.src, alt: '' }) : h('em', { text: 'нет скриншота' })),
          h('b', { text: w.title }), h('small', { text: w.tag || '—' }),
        ]),
        h('div', { class: 'wfa-work-move' }, [
          h('button', { type: 'button', 'aria-label': 'Выше', disabled: i === 0, text: '↑', onclick: function () { moveWork(w.id, works[i - 1].id); } }),
          h('button', { type: 'button', 'aria-label': 'Ниже', disabled: i === works.length - 1, text: '↓', onclick: function () { moveWork(w.id, works[i + 1].id, true); } }),
        ]),
      ]);
      return li;
    }));
    return [
      h('div', { class: 'wfa-h-row' }, [h('h2', { class: 'wfa-h', text: 'Работы' }), h('button', { type: 'button', class: 'wfa-btn', text: '+ Добавить работу', onclick: function () { editWork({ id: 0, title: '', folder: worksFolder, tag: 'Наш проект', desc: '', url: '', shape: worksFolder === 'sites' || worksFolder === 'izi' ? 'wide' : 'phone', image_id: 0, src: '' }); } })]),
      h('p', { class: 'wfa-note', text: 'Это карточки внутри папок на сайте. Перетащите карточку (или стрелками), чтобы поменять порядок. Нажмите, чтобы изменить.' }),
      folderTabs,
      works.length ? grid : h('p', { class: 'wfa-empty', text: 'В этой папке пока пусто — добавьте первую работу.' }),
    ];
  }
  function moveWork(id, targetId, after) {
    var folderWorks = S.works.filter(function (w) { return w.folder === worksFolder; });
    var moving = folderWorks.find(function (w) { return w.id === id; });
    folderWorks.splice(folderWorks.indexOf(moving), 1);
    var t = folderWorks.findIndex(function (w) { return w.id === targetId; });
    folderWorks.splice(after ? t + 1 : t, 0, moving);
    S.works = S.works.filter(function (w) { return w.folder !== worksFolder; }).concat(folderWorks);
    render();
    adapter.api('POST', '/works/order', { ids: folderWorks.map(function (w) { return w.id; }) }).then(function () { toast('Порядок сохранён'); }).catch(fail);
  }
  function editWork(w) {
    var d = Object.assign({}, w);
    var dlg = h('dialog', { class: 'wfa-dialog', 'aria-labelledby': 'wfa-dlg-title' });
    var preview = h('div', { class: 'wfa-img-pick' + (d.shape === 'phone' ? ' is-phone' : '') });
    function drawPreview() {
      preview.innerHTML = '';
      preview.className = 'wfa-img-pick' + (d.shape === 'phone' ? ' is-phone' : '');
      preview.appendChild(d.src ? h('img', { src: d.src, alt: 'Скриншот' }) : h('span', { text: 'Скриншот не выбран' }));
    }
    drawPreview();
    function field(label, input) { return h('label', { class: 'wfa-field' }, [h('span', { text: label }), input]); }
    var title = h('input', { class: 'wfa-input', id: 'wfa-w-title', value: d.title, required: true, placeholder: 'Например: Сайт ветклиники «Айболит»' });
    var folder = h('select', { class: 'wfa-input', id: 'wfa-w-folder' }, S.folders.map(function (f) { return h('option', { value: f.key, selected: f.key === d.folder, text: f.title }); }));
    var shape = h('select', { class: 'wfa-input', id: 'wfa-w-shape', onchange: function () { d.shape = shape.value; drawPreview(); } }, [
      h('option', { value: 'wide', selected: d.shape !== 'phone', text: 'Сайт / компьютер' }), h('option', { value: 'phone', selected: d.shape === 'phone', text: 'Телефон' })]);
    var tag = h('input', { class: 'wfa-input', id: 'wfa-w-tag', value: d.tag, placeholder: 'Наш проект / Концепт', list: 'wfa-tags' });
    var desc = h('textarea', { class: 'wfa-input', id: 'wfa-w-desc', rows: 3, placeholder: 'Что сделали и чем полезно клиенту' }); desc.value = d.desc || '';
    var url = h('input', { class: 'wfa-input', id: 'wfa-w-url', type: 'url', value: d.url, placeholder: 'https://' });
    var form = h('form', { class: 'wfa-form', method: 'dialog' }, [
      h('h3', { id: 'wfa-dlg-title', text: d.id ? 'Изменить работу' : 'Новая работа' }),
      h('div', { class: 'wfa-form-grid' }, [
        h('div', null, [preview, h('div', { class: 'wfa-row' }, [
          h('button', { type: 'button', class: 'wfa-btn', text: d.src ? 'Заменить скриншот' : 'Загрузить скриншот', onclick: function () {
            adapter.pickImage().then(function (img) { if (img) { d.image_id = img.id; d.src = img.url; drawPreview(); } });
          } }),
          d.image_id ? h('button', { type: 'button', class: 'wfa-btn wfa-btn-ghost', text: 'Убрать', onclick: function () { d.image_id = 0; d.src = ''; drawPreview(); } }) : null,
        ])]),
        h('div', { class: 'wfa-fields' }, [field('Название', title), field('Папка', folder), field('Вид скриншота', shape), field('Метка', tag), field('Описание', desc), field('Ссылка', url),
          h('datalist', { id: 'wfa-tags' }, ['Наш проект', 'Концепт', 'Пример: PetGo'].map(function (t) { return h('option', { value: t }); }))]),
      ]),
      h('div', { class: 'wfa-row wfa-form-actions' }, [
        d.id ? confirmBtn('Удалить работу', function () {
          adapter.api('DELETE', '/work/' + d.id).then(function () { S.works = S.works.filter(function (x) { return x.id !== d.id; }); dlg.close(); render(); toast('Работа удалена'); }).catch(fail);
        }) : null,
        h('span', { class: 'wfa-grow' }),
        h('button', { type: 'button', class: 'wfa-btn wfa-btn-ghost', text: 'Отмена', onclick: function () { dlg.close(); } }),
        h('button', { type: 'submit', class: 'wfa-btn', text: 'Сохранить' }),
      ]),
    ]);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!title.value.trim()) { title.focus(); toast('Укажите название', true); return; }
      var body = { id: d.id, title: title.value.trim(), folder: folder.value, shape: shape.value, tag: tag.value, desc: desc.value, url: url.value, image_id: d.image_id || 0, src: d.src };
      adapter.api('POST', '/work', body).then(function (saved) {
        if (!saved.src && d.src) saved.src = d.src;
        var i = S.works.findIndex(function (x) { return x.id === saved.id; });
        if (i >= 0) S.works[i] = saved; else S.works.push(saved);
        worksFolder = saved.folder; dlg.close(); render(); toast('Сохранено — уже на сайте');
      }).catch(fail);
    });
    dlg.appendChild(form);
    dlg.addEventListener('close', function () { dlg.remove(); });
    app.appendChild(dlg);
    dlg.showModal();
    title.focus();
  }

  /* ---------- AI-ассистент ---------- */
  function viewAi() {
    var ai = S.ai;
    var sub = h('div', { class: 'wfa-chips' }, [['questions', 'Вопросы к вам', counts().newQs]].concat(HAS_CHATS ? [['faq', 'Частые вопросы']] : []).concat([['learn', 'Обучение', ai.learned.length], ['kb', 'База знаний']]).map(function (t) {
      return h('button', { type: 'button', class: 'wfa-chip', 'aria-pressed': aiTab === t[0] ? 'true' : 'false', onclick: function () { aiTab = t[0]; render(); } }, [t[1] + (t[2] != null ? ' ' : ''), t[2] != null ? h('span', { text: t[2] }) : null]);
    }));
    var status = h('div', { class: 'wfa-ai-status' + (ai.has_key ? '' : ' is-off') }, [
      h('span', { class: 'wfa-dot ' + (ai.has_key ? 'is-done' : 'is-new') }),
      ai.has_key ? 'Ассистент отвечает сам (' + ai.model + '). Сложное приходит сюда.' : 'Ключ API не добавлен — ассистент сразу передаёт все вопросы вам.',
      h('a', { href: ai.settings_url, class: 'wfa-link', text: ai.has_key ? 'Настройки' : 'Добавить ключ' }),
    ]);
    if (aiTab === 'faq' && !HAS_CHATS) aiTab = 'questions';
    var body = { questions: aiQuestions, faq: aiFaq, learn: aiLearn, kb: aiKb }[aiTab]();
    return [h('h2', { class: 'wfa-h', text: 'AI-ассистент' }), status, sub].concat(body);
  }
  function aiQuestions() {
    var qs = S.ai.questions.slice().sort(function (a, b) { return (a.state === 'answered') - (b.state === 'answered'); });
    if (!qs.length) return [h('p', { class: 'wfa-empty', text: 'Вопросов пока нет — ассистент справляется сам.' })];
    return [h('div', { class: 'wfa-qs' }, qs.map(function (q) {
      var ans = h('textarea', { class: 'wfa-input', rows: 3, id: 'wfa-q-' + q.id, placeholder: 'Ваш ответ клиенту' }); ans.value = q.answer || '';
      var learn = h('input', { type: 'checkbox', checked: !q.learned });
      var digits = (q.contact || '').replace(/\D/g, '');
      return h('article', { class: 'wfa-q' + (q.state === 'answered' ? ' is-done' : '') }, [
        h('div', { class: 'wfa-lead-title' }, [h('b', { text: q.q }), h('time', { text: fmtDate(q.date) })]),
        h('p', { class: 'wfa-note', text: 'Почему передал: ' + (q.reason || '—') + (q.contact ? ' · Контакт: ' + q.contact : ' · Клиент не оставил контакт') }),
        q.transcript && q.transcript.length ? h('details', { class: 'wfa-transcript' }, [h('summary', { text: 'Весь диалог' })].concat(q.transcript.map(function (m) {
          return h('p', null, [h('b', { text: m.role === 'user' ? 'Клиент: ' : 'Ассистент: ' }), m.content]);
        }))) : null,
        ans,
        h('div', { class: 'wfa-row' }, [
          q.learned ? h('span', { class: 'wfa-note', text: '✓ Уже в обучении' }) : h('label', { class: 'wfa-check' }, [learn, ' Запомнить ответ — в следующий раз ассистент ответит сам']),
          h('span', { class: 'wfa-grow' }),
          digits ? h('a', { class: 'wfa-btn wfa-btn-ghost', target: '_blank', rel: 'noopener', href: 'https://wa.me/' + digits + '?text=' + encodeURIComponent(ans.value), onclick: function (e) { e.currentTarget.href = 'https://wa.me/' + digits + '?text=' + encodeURIComponent(ans.value); }, text: 'Ответить в WhatsApp' }) : null,
          h('button', { type: 'button', class: 'wfa-btn', text: 'Сохранить ответ', onclick: function () {
            if (!ans.value.trim()) { ans.focus(); toast('Напишите ответ', true); return; }
            adapter.api('POST', '/ai/question/' + q.id, { answer: ans.value.trim(), learn: learn.checked && !q.learned }).then(function (r) {
              q.answer = ans.value.trim(); q.state = 'answered'; if (learn.checked) q.learned = true; S.ai.learned = r.learned || S.ai.learned;
              render(); toast(learn.checked ? 'Ответ сохранён и добавлен в обучение' : 'Ответ сохранён');
            }).catch(fail);
          } }),
          confirmBtn('Удалить', function () {
            adapter.api('DELETE', '/ai/question/' + q.id).then(function () { S.ai.questions = S.ai.questions.filter(function (x) { return x !== q; }); render(); }).catch(fail);
          }),
        ]),
      ]);
    }))];
  }
  function aiLearn() {
    var rows = S.ai.learned.map(function (r) { return { q: r.q, a: r.a }; });
    var box = h('div', { class: 'wfa-learn' });
    function draw() {
      box.innerHTML = '';
      rows.forEach(function (r, i) {
        var q = h('textarea', { class: 'wfa-input', rows: 2, 'aria-label': 'Вопрос', oninput: function (e) { r.q = e.target.value; } }); q.value = r.q;
        var a = h('textarea', { class: 'wfa-input', rows: 3, 'aria-label': 'Ответ', oninput: function (e) { r.a = e.target.value; } }); a.value = r.a;
        box.appendChild(h('div', { class: 'wfa-learn-row' }, [h('label', null, [h('span', { text: 'Вопрос клиента' }), q]), h('label', null, [h('span', { text: 'Правильный ответ' }), a]),
          h('button', { type: 'button', class: 'wfa-btn wfa-btn-ghost wfa-danger', text: 'Убрать', onclick: function () { rows.splice(i, 1); draw(); } })]));
      });
      if (!rows.length) box.appendChild(h('p', { class: 'wfa-empty', text: 'Пока ничего не выучено. Ответьте на вопрос во вкладке «Вопросы к вам» с галочкой «Запомнить» или добавьте пример сами.' }));
    }
    draw();
    return [
      h('p', { class: 'wfa-note', text: 'Это ваши ответы, которые ассистент использует как образец. Добавьте пример для частого вопроса — и он будет отвечать так же.' }),
      box,
      h('div', { class: 'wfa-row' }, [
        h('button', { type: 'button', class: 'wfa-btn wfa-btn-ghost', text: '+ Добавить пример', onclick: function () { rows.push({ q: '', a: '' }); draw(); } }),
        h('span', { class: 'wfa-grow' }),
        h('button', { type: 'button', class: 'wfa-btn', text: 'Сохранить обучение', onclick: function () {
          adapter.api('POST', '/ai/learned', { rows: rows.filter(function (r) { return r.q.trim() && r.a.trim(); }) }).then(function (r) { S.ai.learned = r.learned; render(); toast('Обучение сохранено'); }).catch(fail);
        } }),
      ]),
    ];
  }
  function aiKb() {
    var t = h('textarea', { class: 'wfa-input wfa-kb', id: 'wfa-kb', rows: 22 }); t.value = S.ai.knowledge;
    return [
      h('p', { class: 'wfa-note', text: 'Главный источник знаний ассистента. Пишите обычным текстом: услуги, цены «от», сроки, адрес, график, частые вопросы. Чего здесь нет — ассистент не придумает и передаст вопрос вам.' }),
      t,
      h('div', { class: 'wfa-row' }, [h('span', { class: 'wfa-grow' }), h('button', { type: 'button', class: 'wfa-btn', text: 'Сохранить базу знаний', onclick: function () {
        adapter.api('POST', '/ai/knowledge', { knowledge: t.value }).then(function () { S.ai.knowledge = t.value; toast('База знаний сохранена'); }).catch(fail);
      } })]),
    ];
  }

  /* ---------- частые вопросы: что клиенты спрашивают чаще всего ---------- */
  function normQ(t) { return String(t || '').toLowerCase().replace(/[^\p{L}\p{N}\s]/gu, '').replace(/\s+/g, ' ').trim(); }
  function aiFaq() {
    if (!C.faq) {
      adapter.chatApi('GET', '/faq').then(function (d) { C.faq = d.items || []; if (tab === 'ai' && aiTab === 'faq') render(); }).catch(fail);
      return [h('p', { class: 'wfa-loading', text: 'Загружаем…' })];
    }
    if (!C.faq.length) return [h('p', { class: 'wfa-empty', text: 'Клиенты ещё ничего не спрашивали. Здесь появятся их вопросы — самые частые сверху.' })];
    var known = {}; S.ai.learned.forEach(function (r) { known[normQ(r.q)] = r.a; });
    return [
      h('p', { class: 'wfa-note', text: 'Все вопросы клиентов из чата, самые частые сверху. Впишите ответ и нажмите «В обучение» — ассистент будет отвечать так же.' }),
      h('div', { class: 'wfa-qs' }, C.faq.map(function (it, i) {
        var done = known[normQ(it.q)];
        var ans = h('textarea', { class: 'wfa-input', rows: 2, id: 'wfa-faq-' + i, placeholder: done ? '' : 'Ваш ответ на этот вопрос' }); ans.value = done || '';
        return h('article', { class: 'wfa-q' + (done ? ' is-done' : '') }, [
          h('div', { class: 'wfa-lead-title' }, [h('b', { text: it.q }), h('span', { class: 'wfa-count', text: it.n + (it.n % 10 >= 2 && it.n % 10 <= 4 && (it.n % 100 < 12 || it.n % 100 > 14) ? ' раза' : ' раз') })]),
          ans,
          h('div', { class: 'wfa-row' }, [h('span', { class: 'wfa-note', text: done ? '✓ Ассистент знает ответ' : 'Спросили последний раз: ' + fmtDate(new Date(it.at * 1000).toISOString()) }), h('span', { class: 'wfa-grow' }),
            h('button', { type: 'button', class: 'wfa-btn', text: done ? 'Обновить ответ' : 'В обучение', onclick: function () {
              var a = ans.value.trim();
              if (!a) { ans.focus(); toast('Напишите ответ', true); return; }
              var rows = S.ai.learned.filter(function (r) { return normQ(r.q) !== normQ(it.q); }).concat([{ q: it.q, a: a }]);
              adapter.api('POST', '/ai/learned', { rows: rows }).then(function (r) { S.ai.learned = r.learned; render(); toast('Добавлено в обучение'); }).catch(fail);
            } }),
          ]),
        ]);
      })),
    ];
  }

  /* ---------- чаты: менеджер подключается к разговору ---------- */
  var STATUS = { waiting: 'Ждёт вас', live: 'Вы в чате', bot: 'Отвечает ассистент', closed: 'Закрыт' };
  var listTimer = 0, chatTimer = 0;
  function setChats(list) {
    var waitingIds = list.filter(function (c) { return c.status === 'waiting'; }).map(function (c) { return c.id; });
    if (C.seenWaiting) {
      var fresh = waitingIds.filter(function (id) { return C.seenWaiting.indexOf(id) < 0; });
      if (fresh.length) toast('Клиенту нужен менеджер — откройте «Чаты»');
    }
    C.seenWaiting = waitingIds; C.list = list;
  }
  function pollList() {
    clearTimeout(listTimer);
    listTimer = setTimeout(function () {
      if (document.hidden) { pollList(); return; }
      adapter.chatApi('GET', '/chats').then(function (d) {
        var before = JSON.stringify(C.list); setChats(d.chats);
        if (JSON.stringify(C.list) !== before && !busy() && (tab === 'chats' || tab === 'home')) render();
      }).catch(function () {}).then(pollList);
    }, tab === 'chats' ? 4000 : 10000);
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden && S && HAS_CHATS) { pollList(); if (C.open) pollChat(0); } });
  function openChat(id, quiet) {
    C.open = id; C.msgs = []; C.status = ''; C.contact = '';
    if (location.hash !== '#chat-' + id) history.replaceState(null, '', '#chat-' + id);
    if (!quiet) render();
    pollChat(0, true);
  }
  function closeChat() {
    C.open = 0; clearTimeout(chatTimer);
    history.replaceState(null, '', location.pathname + location.search);
    render();
  }
  function pollChat(delay, first) {
    clearTimeout(chatTimer);
    var id = C.open; if (!id) return;
    chatTimer = setTimeout(function () {
      if (C.open !== id) return;
      var after = C.msgs.length ? C.msgs[C.msgs.length - 1].i : 0;
      adapter.chatApi('GET', '/chat/' + id + '?after=' + after).then(function (d) {
        if (C.open !== id) return;
        var changed = first || d.msgs.length || d.status !== C.status || d.contact !== C.contact;
        C.msgs = C.msgs.concat(d.msgs); C.status = d.status; C.contact = d.contact;
        var it = C.list.find(function (c) { return c.id === id; }); if (it) { it.unread = 0; it.status = d.status; }
        if (changed && !busy() && tab === 'chats') render();
      }).catch(function (e) { if (first) { fail(e); closeChat(); } }).then(function () { if (C.open === id) pollChat(document.hidden ? 15000 : 2500); });
    }, delay || 0);
  }
  function chatAction(path, body) {
    var id = C.open;
    return adapter.chatApi('POST', '/chat/' + id + path, body).then(function (r) {
      if (r.status) C.status = r.status;
      var it = C.list.find(function (c) { return c.id === id; }); if (it && r.status) it.status = r.status;
      pollChat(0); return r;
    });
  }
  function ago(ts) {
    var s = Math.max(0, Date.now() / 1000 - ts);
    if (s < 60) return 'только что'; if (s < 3600) return Math.floor(s / 60) + ' мин'; if (s < 86400) return Math.floor(s / 3600) + ' ч';
    return fmtDate(new Date(ts * 1000).toISOString());
  }
  function viewChats() {
    var list = h('div', { class: 'wfa-chat-list' }, C.list.length ? C.list.map(function (c) {
      return h('button', { type: 'button', class: 'wfa-chat-item is-' + c.status + (c.id === C.open ? ' is-open' : ''), onclick: function () { openChat(c.id); } }, [
        h('span', { class: 'wfa-chat-item-top' }, [h('b', { text: c.contact || 'Клиент №' + c.id }), h('time', { text: ago(c.updated) })]),
        h('span', { class: 'wfa-chat-last', text: c.last || '…' }),
        h('span', { class: 'wfa-chat-item-top' }, [h('span', { class: 'wfa-pill is-' + c.status, text: STATUS[c.status] || c.status }), c.unread && c.id !== C.open ? h('span', { class: 'wfa-badge', text: c.unread }) : null]),
      ]);
    }) : [h('p', { class: 'wfa-empty', text: 'Разговоров пока нет. Когда клиент напишет ассистенту на сайте, чат появится здесь.' })]);
    return [
      h('div', { class: 'wfa-h-row' }, [h('h2', { class: 'wfa-h', text: 'Чаты' }), pushControl()]),
      installHint(),
      h('div', { class: 'wfa-chats' + (C.open ? ' has-open' : '') }, [list, C.open ? chatPane() : h('div', { class: 'wfa-chat-pane wfa-chat-placeholder' }, h('p', { class: 'wfa-note', text: 'Выберите разговор слева. Чаты «Ждёт вас» — ассистент позвал менеджера.' }))]),
    ];
  }
  function chatPane() {
    var name = (CFG.chats && CFG.chats.manager) || 'Менеджер';
    var log = h('div', { class: 'wfa-chat-log', 'aria-live': 'polite' }, C.msgs.length ? C.msgs.map(function (m) {
      if (m.r === 'system') return h('p', { class: 'wfa-chat-sys', text: m.t });
      var who = m.r === 'user' ? 'Клиент' : m.r === 'bot' ? 'Ассистент' : (m.a || name);
      return h('div', { class: 'wfa-bubble is-' + m.r }, [h('small', { text: who + (m.at ? ' · ' + new Date(m.at * 1000).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }) : '') }), h('span', { text: m.t })]);
    }) : [h('p', { class: 'wfa-loading', text: 'Загружаем разговор…' })]);
    var live = C.status === 'live';
    var input = h('textarea', { class: 'wfa-input wfa-chat-input', id: 'wfa-chat-input', rows: 1, placeholder: live ? 'Ответ клиенту от имени «' + name + '»' : 'Напишите — и вы присоединитесь к чату', oninput: function (e) { C.draft = e.target.value; },
      onkeydown: function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit(); } } });
    input.value = C.draft;
    var form = h('form', { class: 'wfa-chat-form' }, [input, h('button', { type: 'submit', class: 'wfa-btn', text: 'Отправить' })]);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var t = C.draft.trim(); if (!t) return;
      C.draft = ''; input.value = '';
      chatAction('/send', { text: t }).then(function () { C.status = 'live'; }).catch(function (err) { C.draft = t; render(); fail(err); });
    });
    var digits = (C.contact || '').replace(/\D/g, '');
    return h('section', { class: 'wfa-chat-pane' }, [
      h('div', { class: 'wfa-chat-head' }, [
        h('button', { type: 'button', class: 'wfa-btn wfa-btn-ghost wfa-chat-back', 'aria-label': 'К списку чатов', text: '←', onclick: closeChat }),
        h('div', { class: 'wfa-grow' }, [h('b', { text: C.contact || 'Клиент №' + C.open }), h('span', { class: 'wfa-pill is-' + C.status, text: STATUS[C.status] || '…' })]),
        digits ? h('a', { class: 'wfa-btn wfa-btn-ghost', href: 'https://wa.me/' + digits, target: '_blank', rel: 'noopener', text: 'WhatsApp' }) : null,
        live ? h('button', { type: 'button', class: 'wfa-btn wfa-btn-ghost', text: 'Вернуть ассистенту', onclick: function () { chatAction('/release').then(function () { toast('Дальше отвечает ассистент'); }).catch(fail); } })
          : h('button', { type: 'button', class: 'wfa-btn', text: 'Присоединиться', onclick: function () { chatAction('/join').then(function () { toast('Клиент видит: «Менеджер присоединился»'); var i = document.getElementById('wfa-chat-input'); if (i) i.focus(); }).catch(fail); } }),
      ]),
      log,
      form,
    ]);
  }

  /* ---------- push-уведомления на телефон ---------- */
  function pushState() {
    if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) return 'unsupported';
    return Notification.permission; // default | granted | denied
  }
  function pushControl() {
    if (!CFG.sw) return CFG.app ? h('a', { class: 'wfa-btn wfa-btn-ghost', href: CFG.app, target: '_blank', rel: 'noopener', text: 'Приложение и уведомления ↗' }) : null;
    var st = pushState();
    if (st === 'unsupported') return null;
    if (st === 'denied') return h('span', { class: 'wfa-note', text: 'Уведомления запрещены в настройках браузера' });
    if (st === 'granted') return h('div', { class: 'wfa-row' }, [
      h('span', { class: 'wfa-note', text: '🔔 Уведомления включены' }),
      h('button', { type: 'button', class: 'wfa-btn wfa-btn-ghost', text: 'Проверить', onclick: function () { subscribePush().then(function () { return adapter.chatApi('POST', '/push/test'); }).then(function (r) { toast(r.sent ? 'Уведомление отправлено' : 'Не дошло — включите ещё раз', !r.sent); }).catch(fail); } }),
    ]);
    return h('button', { type: 'button', class: 'wfa-btn', text: '🔔 Включить уведомления', onclick: function () { subscribePush().then(function () { toast('Готово: уведомления будут приходить на это устройство'); render(); }).catch(function (e) { fail(e); render(); }); } });
  }
  function b64ToBytes(s) {
    var pad = '='.repeat((4 - s.length % 4) % 4), raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(raw.length); for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i); return out;
  }
  function subscribePush() {
    if (pushState() === 'unsupported') return Promise.reject(new Error('Этот браузер не умеет уведомления. На iPhone добавьте админку на экран «Домой» и откройте оттуда.'));
    if (!CFG.chats.vapid) return Promise.reject(new Error('Ключи уведомлений не созданы на сервере'));
    return Notification.requestPermission().then(function (p) {
      if (p !== 'granted') throw new Error('Разрешите уведомления, чтобы они приходили');
      return navigator.serviceWorker.register(CFG.sw);
    }).then(function () { return navigator.serviceWorker.ready; }).then(function (reg) {
      return reg.pushManager.getSubscription().then(function (s) { return s || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(CFG.chats.vapid) }); });
    }).then(function (sub) { return adapter.chatApi('POST', '/push/subscribe', { subscription: sub.toJSON() }); });
  }
  function installHint() {
    if (!CFG.sw) return null;
    var standalone = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone;
    if (standalone) return null;
    var ios = /iPhone|iPad|iPod/.test(navigator.userAgent);
    return h('p', { class: 'wfa-ai-status' }, [h('span', { class: 'wfa-dot is-new' }),
      ios ? 'Установите админку как приложение: в Safari нажмите «Поделиться» → «На экран Домой», откройте её со значка и включите уведомления.'
          : 'Установите админку как приложение: в меню браузера «Установить приложение» (или «Добавить на главный экран»), затем включите уведомления.']);
  }
  if ('serviceWorker' in navigator && CFG.sw) { navigator.serviceWorker.register(CFG.sw).catch(function () {}); }

  /* ---------- номера ---------- */
  function viewSettings() {
    var ph = h('input', { class: 'wfa-input', id: 'wfa-phone', type: 'tel', value: S.settings.phone });
    var wa = h('input', { class: 'wfa-input', id: 'wfa-wa', type: 'tel', value: S.settings.whatsapp });
    return [
      h('h2', { class: 'wfa-h', text: 'Номера' }),
      h('section', { class: 'wfa-card wfa-narrow' }, [
        h('p', { class: 'wfa-note', text: 'Меняются на всём сайте сразу. Телефон и WhatsApp независимы: поменяли один — второй остался прежним.' }),
        h('label', { class: 'wfa-field' }, [h('span', { text: 'Телефон для звонков' }), ph]),
        h('label', { class: 'wfa-field' }, [h('span', { text: 'Номер WhatsApp' }), wa]),
        h('div', { class: 'wfa-row' }, [h('span', { class: 'wfa-grow' }), h('button', { type: 'button', class: 'wfa-btn', text: 'Сохранить', onclick: function () {
          adapter.api('POST', '/settings', { phone: ph.value, whatsapp: wa.value }).then(function (r) { S.settings = r; render(); toast('Номера обновлены на сайте'); }).catch(fail);
        } })]),
      ]),
    ];
  }

  load();
})();
