/* PetGo — «Настройки сайта» в приложении (кабинете). Собран из тех же элементов, что и всё приложение:
   списки как в «Настройках» iPhone, переключатели, вкладки-чипы. Цвета берутся из темы приложения
   (тёмная / светлая / как в системе). Все данные — в объекте S; при сохранении уходят одним полем. */
(function () {
  "use strict";
  var D = window.PGS;
  var app = document.getElementById("pgs-app"), form = document.getElementById("pgs-form"), out = document.getElementById("pgs-json");
  if (!D || !app || !form || !out) return;
  var S = D.cfg, tab = "contacts";
  try { tab = sessionStorage.getItem("pgs-tab") || tab; } catch (e) {}
  var head = document.getElementById("pgs-head");

  function el(tag, props, kids) {
    var n = document.createElement(tag), k;
    for (k in (props || {})) {
      if (k === "class") n.className = props[k];
      else if (k === "text") n.textContent = props[k];
      else if (k.slice(0, 2) === "on") n.addEventListener(k.slice(2), props[k]);
      else if (props[k] !== false && props[k] != null) n.setAttribute(k, props[k] === true ? "" : props[k]);
    }
    (kids || []).forEach(function (c) { if (c) n.appendChild(typeof c === "string" ? document.createTextNode(c) : c); });
    return n;
  }
  var mark = document.getElementById("pgs-dirty");
  function dirty() { if (mark) mark.setAttribute("data-dirty", "1"); }

  /* ---------- элементы в стиле приложения ---------- */
  function group(title, rows, foot) {
    return el("div", { class: "pgc-sgrp" }, [title ? el("h4", { text: title }) : null, el("div", { class: "pgc-sgl" }, rows), foot ? el("p", { class: "pgs-foot", text: foot }) : null]);
  }
  function inp(o, k, ph, type, after) {
    var i = el("input", { type: type || "text", value: o[k] == null ? "" : String(o[k]), placeholder: ph || "", inputmode: type === "number" ? "numeric" : (type === "tel" ? "tel" : null), oninput: function () { o[k] = i.value; if (after) after(); } });
    if (type === "number") i.type = "text";
    return i;
  }
  function lrow(label, input) { return el("label", { class: "pgc-lrow" }, [el("span", { text: label }), input]); }
  function list(rows) { return el("div", { class: "pgc-list" }, rows); }
  function area(o, k, rows, ph) {
    var t = el("textarea", { rows: rows || 3, placeholder: ph || "", oninput: function () { o[k] = t.value; } });
    t.value = o[k] == null ? "" : String(o[k]);
    return t;
  }
  function lines(o, k, rows, ph) {
    var t = el("textarea", { rows: rows || 3, placeholder: ph || "", oninput: function () { o[k] = t.value.split(/\r?\n/).map(function (x) { return x.trim(); }).filter(Boolean); } });
    t.value = (o[k] || []).join("\n");
    return t;
  }
  function field(label, node, hint) { return el("label", { class: "pgc-f pgs-fld" }, [el("span", { text: label }), node, hint ? el("small", { class: "pgs-hint", text: hint }) : null]); }
  function sw(o, k, title, sub, after) {
    var on = String(o[k]) === "1";
    var b = el("button", { type: "button", class: "pgc-sw", role: "switch", "aria-checked": on ? "true" : "false", "aria-label": title, onclick: function () {
      on = !on; o[k] = on ? "1" : "0"; b.setAttribute("aria-checked", on ? "true" : "false"); dirty(); if (after) after();
    } }, [el("i")]);
    return el("div", { class: "pgc-srow" }, [el("span", { class: "pgc-srt" }, [el("b", { text: title }), sub ? el("small", { text: sub }) : null]), b]);
  }
  function select(o, k, opts, after) {
    var s = el("select", { onchange: function () { o[k] = s.value; if (after) after(); } });
    Object.keys(opts).forEach(function (v) { var op = el("option", { value: v, text: opts[v] }); if (String(o[k]) === v) op.selected = true; s.appendChild(op); });
    return s;
  }
  function btn(label, fn, cls) { return el("button", { type: "button", class: "pgc-btn " + (cls || "sec sm"), onclick: fn, text: label }); }

  /* список карточек с кнопками «выше / ниже / удалить» и «добавить» */
  function cards(arr, draw, blank, addLabel, max) {
    var box = el("div", { class: "pgs-cards" });
    arr.forEach(function (it, i) {
      var tools = el("div", { class: "pgs-tools" }, [
        el("button", { type: "button", class: "pgs-tb", "aria-label": "Выше", disabled: i === 0, onclick: function () { arr.splice(i - 1, 0, arr.splice(i, 1)[0]); dirty(); render(); } }, [ic("up")]),
        el("button", { type: "button", class: "pgs-tb", "aria-label": "Ниже", disabled: i === arr.length - 1, onclick: function () { arr.splice(i + 1, 0, arr.splice(i, 1)[0]); dirty(); render(); } }, [ic("down")]),
        el("button", { type: "button", class: "pgs-tb red", "aria-label": "Удалить", onclick: function () { if (confirm("Удалить?")) { arr.splice(i, 1); dirty(); render(); } } }, [ic("trash")])
      ]);
      box.appendChild(el("div", { class: "pgs-card" }, [el("div", { class: "pgs-card-h" }, [el("b", { text: "№ " + (i + 1) }), tools]), draw(it, i)]));
    });
    if (!max || arr.length < max) box.appendChild(btn("+ " + addLabel, function () { arr.push(blank()); dirty(); render(); }, "sec wide pgs-add"));
    return box;
  }
  var ICONS = {
    up: '<path d="M12 19V5M6 11l6-6 6 6"/>', down: '<path d="M12 5v14M6 13l6 6 6-6"/>',
    trash: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>',
    upload: '<path d="M12 16V4M7 9l5-5 5 5M5 20h14"/>'
  };
  function ic(n) {
    var s = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    s.setAttribute("viewBox", "0 0 24 24"); s.setAttribute("class", "pgc-ic");
    s.setAttribute("fill", "none"); s.setAttribute("stroke", "currentColor"); s.setAttribute("stroke-width", "2"); s.setAttribute("stroke-linecap", "round"); s.setAttribute("stroke-linejoin", "round");
    s.innerHTML = ICONS[n];
    return s;
  }

  /* ---------- фото, видео, анимации ---------- */
  function isVideo(u) { return /\.(mp4|webm|mov|m4v|ogv)(\?|#|$)/i.test(u || ""); }
  function upload(b, done) {
    var U = D.upload || {};
    var f = el("input", { type: "file", accept: "image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" });
    f.addEventListener("change", function () {
      var file = f.files && f.files[0];
      if (!file) return;
      if (U.max && file.size > U.max) { alert("Файл слишком большой: можно не больше " + Math.floor(U.max / 1048576) + " МБ."); return; }
      var fd = new FormData();
      fd.append("action", "petgo_site_upload"); fd.append("n", U.n || ""); fd.append("f", file);
      b.classList.add("busy");
      fetch(U.url, { method: "POST", body: fd, credentials: "same-origin" })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (j && j.ok && j.url) done(j.url); else alert((j && j.m) || "Не получилось загрузить файл."); })
        .catch(function () { alert("Не получилось загрузить: нет связи или файл слишком большой."); })
        .then(function () { b.classList.remove("busy"); });
    });
    f.click();
  }
  function media(get, set, secObj) {
    var u = get() || "", own = u.indexOf("art:") !== 0, k;
    var opts = { "": "Своё фото или видео" };
    for (k in D.arts) opts[k] = D.arts[k];
    var s = el("select", { onchange: function () { set(s.value); dirty(); render(); } });
    Object.keys(opts).forEach(function (v) { var op = el("option", { value: v, text: opts[v] }); if ((own ? "" : u) === v) op.selected = true; s.appendChild(op); });
    var kids = [s];
    if (own) {
      var prev = u ? (isVideo(u) ? el("span", { class: "pgs-thumb vid", text: "▶" }) : el("img", { class: "pgs-thumb", src: u, alt: "" })) : el("span", { class: "pgs-thumb empty" });
      var up = el("button", { type: "button", class: "pgc-btn sm", onclick: function () { upload(up, function (url) { set(url); dirty(); render(); }); } }, [ic("upload"), u ? "Заменить" : "Загрузить"]);
      kids.push(el("div", { class: "pgs-mrow" }, [prev, up]));
    }
    if (secObj) {
      var vid = own && isVideo(u);
      kids.push(list([lrow("Показывать, сек.", inp(secObj, "s", vid ? "до конца ролика" : "8", "number"))]));
    }
    return el("div", { class: "pgs-media" }, kids);
  }

  function money(n) { return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, " "); }
  function priceNote(s) {
    var p = parseInt(String(s.price).replace(/\D/g, ""), 10) || 0, off = Math.min(90, Math.max(0, parseInt(S.off, 10) || 0));
    if (!p) return "Цена не показывается.";
    if (off > 0 && String(s.sale) === "1") return "На сайте: " + money(p * (100 - off) / 100) + " ₸ (−" + off + "%), потом " + money(p) + " ₸";
    return "На сайте: " + money(p) + " ₸, без скидки";
  }
  function updPrices() { Array.prototype.forEach.call(app.querySelectorAll("[data-price]"), function (n) { n.textContent = priceNote(S.services[+n.getAttribute("data-price")]); }); }

  /* ---------- вкладки ---------- */
  var TABS = {
    contacts: ["Контакты", function () {
      return [
        group("Номера", [list([
          lrow("WhatsApp", inp(S, "wa", "77001234567", "tel")),
          lrow("Телефон", inp(S, "phone", "как WhatsApp", "tel"))
        ])], "Только цифры, с кодом страны. Меняются сразу на всём сайте: вверху, в кнопках, в подвале. Пустой телефон — тот же номер, что WhatsApp."),
        group("Сайт", [
          sw(S, "front", "Сайт PetGo на главной", "Без шорткода, без шапки и подвала темы"),
          sw(S, "kbd", "Своя клавиатура на телефонах", "В формах и в чате вместо системной")
        ]),
        group("Страницы", D.pages.map(function (p) {
          return el("a", { class: "pgc-srow pgc-link", href: p.url, target: "_blank", rel: "noopener", "data-ext": "1" }, [el("span", { class: "pgc-srt" }, [el("b", { text: p.name })]), el("small", { class: "pgs-go", text: "Открыть ↗" })]);
        }))
      ];
    }],
    hero: ["Первый экран", function () {
      return [
        group("Тексты на стекле", [cards(S.slides, function (s) {
          return list([lrow("Заголовок", inp(s, "t", "Няня для вашего питомца")), lrow("Строка", inp(s, "s", "Необязательно"))]);
        }, function () { return { t: "", s: "" }; }, "Добавить текст", 12)], "Сменяются по очереди. Можно вставлять {off} — скидка, {walk} и {walk_old} — цена выгула, {week} и {week_old} — цена недели."),
        group("", [list([lrow("Менять текст, сек.", inp(S, "hero_sec", "5", "number"))])]),
        group("Фото, видео и анимации", [cards(S.hero_media, function (m) {
          return media(function () { return m.u; }, function (v) { m.u = v; }, m);
        }, function () { return { u: "", s: "" }; }, "Добавить фото, видео или анимацию", 12)], "Идут по кругу под стеклом. У видео пустое время — до конца ролика. Видео играет без звука."),
        group("Преимущества", [field("Одна строка — одна плашка", lines(S, "checks", 4, "Обученные няни"))])
      ];
    }],
    services: ["Услуги и цены", function () {
      return [
        group("Скидка на первый заказ", [list([lrow("Скидка, %", inp(S, "off", "30", "number", updPrices))])], "Цена со скидкой считается сама, старая зачёркивается. 0 — без скидки."),
        group("Услуги", [cards(S.services, function (s, i) {
          if (!s.list) s.list = [];
          if (!s.media) s.media = [];
          return el("div", { class: "pgs-svc" }, [
            list([
              lrow("Название", inp(s, "title", "Выгул собак")),
              lrow("Для кого", select(s, "pet", { dog: "Собака", cat: "Кошка", all: "Все питомцы" })),
              lrow("Цена, ₸", inp(s, "price", "3000", "number", updPrices)),
              lrow("За что", inp(s, "unit", "за час")),
              lrow("Метка", inp(s, "tag", "Необязательно")),
              lrow("Кнопка", inp(s, "btn", "Заказать"))
            ]),
            el("div", { class: "pgc-sgl pgs-gap" }, [sw(s, "sale", "Скидка на эту услугу", "", updPrices)]),
            el("p", { class: "pgs-price", "data-price": i, text: priceNote(s) }),
            field("Описание", area(s, "text", 2)),
            field("Что входит — одна строка, один пункт", lines(s, "list", 3)),
            el("p", { class: "pgs-sub", text: "Фото, видео и анимации карточки (листаются сами)" }),
            cards(s.media, function (m, j) {
              return media(function () { return s.media[j]; }, function (v) { s.media[j] = v; });
            }, function () { return ""; }, "Добавить картинку", 12)
          ]);
        }, function () { return { id: "", title: "", text: "", pet: "all", tag: "", list: [], price: "", unit: "", sale: "1", btn: "Заказать", media: [] }; }, "Добавить услугу", 20)])
      ];
    }],
    more: ["Прочее", function () {
      var r = [
        group("Частые вопросы", [field("Каждая строка: «Вопрос | Ответ»", area(S, "faq", 9), "Цены подставляются: {walk}, {walk_old}, {week}, {week_old}, {off}. Эти же ответы помощник (ИИ) знает сам.")]),
        group("Поиск", [field("Описание для Google и соцсетей", area(S, "seo", 2), "Пусто — первый текст первого экрана.")])
      ];
      if (head) { head.hidden = false; r.push(group("Счётчики", [head])); }
      return r;
    }]
  };

  function render() {
    var y = window.scrollY;
    app.innerHTML = "";
    if (!TABS[tab]) tab = "contacts";
    var nav = el("div", { class: "pgc-chips pgs-tabs", role: "tablist" });
    Object.keys(TABS).forEach(function (k) {
      nav.appendChild(el("button", { type: "button", role: "tab", class: "pgc-chip" + (k === tab ? " on" : ""), "aria-selected": k === tab ? "true" : "false", text: TABS[k][0], onclick: function () {
        tab = k; try { sessionStorage.setItem("pgs-tab", k); } catch (e) {} render(); window.scrollTo(0, 0);
      } }));
    });
    app.appendChild(nav);
    if (head && head.parentNode) head.parentNode.removeChild(head);
    TABS[tab][1]().forEach(function (c) { app.appendChild(c); });
    if (head && !app.contains(head)) { head.hidden = true; form.appendChild(head); }
    window.scrollTo(0, y);
  }
  app.addEventListener("input", dirty);
  app.addEventListener("change", dirty);
  form.addEventListener("submit", function () { out.value = JSON.stringify(S); if (mark) mark.removeAttribute("data-dirty"); });
  render();
})();
