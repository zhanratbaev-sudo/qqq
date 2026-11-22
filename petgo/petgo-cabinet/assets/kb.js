/* PetGo — своя клавиатура для телефонов и планшетов (сайт и приложение).
   36.2: быстрый набор — нажатия внахлёст не теряются, касание между клавишами попадает в ближайшую, буква над пальцем,
   два пробела — точка.
   Раскладки: русская, казахская (ә і ң ғ ү ұ қ ө һ — отдельным рядом), английская, цифры и символы.
   Системная клавиатура не открывается, поэтому нет и полоски браузера над ней.
   Долгое нажатие — доп. буквы (ё, ъ, казахские). «Системная клавиатура» возвращает обычную для поля.
   Подключение: PGKB.init({ sel, theme: "site"|"app", lang, T, layer, pad }) */
(function () {
  "use strict";
  var doc = document, W = window;
  var LAYOUTS = {
    ru: ["йцукенгшщзх", "фывапролджэ", "ячсмитьбю"],
    kz: ["әіңғүұқөһ", "йцукенгшщзх", "фывапролджэ", "ячсмитьбю"],
    en: ["qwertyuiop", "asdfghjkl", "zxcvbnm"],
    sym: ["1234567890", "-/:;()₸&@\"", ".,?!'+%"]
  };
  var NAMES = { ru: "РУ", kz: "ҚАЗ", en: "EN" };
  var ALT = { "е": "ё", "ь": "ъ", "а": "ә", "и": "і", "н": "ң", "г": "ғ", "у": "ұү", "к": "қ", "о": "ө", "х": "һ", "e": "é", "a": "á" };
  var CSS =
    /* 34.4: прямые углы, плотный фон; движение по правилам UX-анимации: появляется 280 мс (ease-out), уходит 200 мс (ease-in) */
    ".pgkb{position:fixed;z-index:2147483000;left:0;right:0;bottom:0;padding:6px 3px calc(env(safe-area-inset-bottom,0px) + 6px);border-radius:0;" +
    "background:var(--pgkb-bg);box-shadow:0 -.5px 0 var(--pgkb-line);" +
    "transform:translateY(105%);transition:transform 200ms cubic-bezier(.55,0,1,.45);touch-action:none;-webkit-user-select:none;user-select:none;-webkit-tap-highlight-color:transparent;font-family:-apple-system,BlinkMacSystemFont,'SF Pro Text','Segoe UI',Roboto,sans-serif}" +
    ".pgkb[hidden]{display:none}.pgkb.on{transform:none;transition:transform 280ms cubic-bezier(.32,.72,0,1)}" +
    ".pgkb-top{display:flex;justify-content:space-between;align-items:center;padding:0 5px 6px;gap:8px}" +
    ".pgkb button{-webkit-appearance:none;appearance:none;border:0;margin:0;font:inherit;cursor:pointer;-webkit-tap-highlight-color:transparent}" +
    ".pgkb .pgkb-t{height:32px;padding:0 12px;border-radius:999px;font-size:13px;font-weight:500;color:var(--pgkb-mu);background:transparent}" +
    ".pgkb .pgkb-done{background:var(--pgkb-fill);color:var(--pgkb-ac);font-weight:700}" +
    ".pgkb-r{display:flex;justify-content:center;gap:5px;margin-bottom:8px;padding:0 2px}.pgkb-r:last-child{margin-bottom:0}" +
    ".pgkb .pgkb-k{flex:1 1 0;min-width:0;height:44px;padding:0;border-radius:7px;display:grid;place-items:center;background:var(--pgkb-key);color:var(--pgkb-ink);box-shadow:0 1px 0 var(--pgkb-keysh);font-size:21px;line-height:1;transition:background-color 120ms ease,color 120ms ease}" +
    ".pgkb-r.kz4 .pgkb-k{height:40px;font-size:19px}" +
    ".pgkb .pgkb-k.sp{flex:1.45 1 0;background:var(--pgkb-key2);font-size:15px;font-weight:600}" +
    ".pgkb .pgkb-k.sp.w{flex:2.2 1 0}.pgkb .pgkb-k.space{flex:5 1 0}" +
    ".pgkb .pgkb-k.go,.pgkb .pgkb-k.act{background:var(--pgkb-ac);color:var(--pgkb-on)}" +
    /* нажатие: клавиша мягко подсвечивается, без «прыжка» */
    ".pgkb .pgkb-k.down{background:var(--pgkb-down);color:var(--pgkb-ink);transition-duration:0s}" +
    ".pgkb .pgkb-k.alt{background:var(--pgkb-alt);color:#fff;font-weight:600;transform:scale(1.4) translateY(-16px);position:relative;z-index:2}" +
    /* 36.2: буква над пальцем, как на iPhone — видно, что нажато, даже при быстром наборе */
    ".pgkb-pop{position:absolute;left:0;top:0;z-index:3;min-width:46px;height:54px;padding:0 6px;border-radius:9px;display:none;place-items:center;background:var(--pgkb-key);color:var(--pgkb-ink);font-size:30px;line-height:1;box-shadow:0 2px 10px rgba(0,0,0,.22),0 0 0 .5px var(--pgkb-line);pointer-events:none;transform:translate(-50%,calc(-100% - 6px))}.pgkb-pop.on{display:grid}" +
    ".pgkb-num{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;max-width:420px;margin:0 auto;padding:0 6px}.pgkb-num .pgkb-k{height:52px;font-size:25px}" +
    /* сайт: цвета PetGo, матовое стекло */
    ".pgkb--site{--pgkb-bg:#E9E7D8;--pgkb-line:rgba(2,76,70,.14);--pgkb-key:#FFFEF8;--pgkb-key2:#CFCDBC;--pgkb-keysh:rgba(2,76,70,.28);--pgkb-ink:#024C46;--pgkb-mu:rgba(2,76,70,.65);--pgkb-fill:rgba(2,76,70,.1);--pgkb-ac:#024C46;--pgkb-on:#fff;--pgkb-alt:#E8A33C;--pgkb-down:#D6D4C3}" +
    /* сайт в тёмной теме (кабинет клиента) — тёмная клавиатура */
    ".pgk-dark .pgkb--site{--pgkb-bg:#1C1C1E;--pgkb-line:rgba(255,255,255,.08);--pgkb-key:#4A4A4D;--pgkb-key2:#2E2E31;--pgkb-keysh:rgba(0,0,0,.6);--pgkb-ink:#fff;--pgkb-mu:rgba(235,235,245,.6);--pgkb-fill:rgba(255,255,255,.12);--pgkb-ac:#0E7A64;--pgkb-on:#fff;--pgkb-alt:#F0B04D;--pgkb-down:#6A6A6E}" +
    ".pgk-dark .pgkb--site .pgkb-done{color:#4CC38A}" +
    /* приложение: светлая и тёмная темы приложения */
    ".pgkb--app{--pgkb-down:#B4B8BF;--pgkb-bg:#D1D4D9;--pgkb-line:rgba(0,0,0,.08);--pgkb-key:#fff;--pgkb-key2:rgba(171,177,186,.9);--pgkb-keysh:rgba(0,0,0,.3);--pgkb-ink:#000;--pgkb-mu:rgba(60,60,67,.7);--pgkb-fill:rgba(13,124,109,.12);--pgkb-ac:var(--ac,#0d7c6d);--pgkb-on:#fff;--pgkb-alt:#ff9f0a}" +
    ":root.dark .pgkb--app{--pgkb-down:#8A8A8E;--pgkb-bg:#2B2B2E;--pgkb-line:rgba(255,255,255,.08);--pgkb-key:#6b6b6e;--pgkb-key2:#47474a;--pgkb-keysh:rgba(0,0,0,.5);--pgkb-ink:#fff;--pgkb-mu:rgba(235,235,245,.65);--pgkb-fill:rgba(61,190,139,.18);--pgkb-ac:var(--acb,#0E7A64);--pgkb-on:#fff}" +
    ":root.dark .pgkb--app .pgkb-done{color:var(--ac,#3DBE8B)}" +
    "@media (min-width:700px){.pgkb .pgkb-k{height:50px}.pgkb-num .pgkb-k{height:56px}}" +
    "@media (prefers-reduced-motion:reduce){.pgkb{transition:none}.pgkb .pgkb-k.down,.pgkb .pgkb-k.alt{transform:none}}";

  function css() {
    if (doc.getElementById("pgkb-css")) return;
    var s = doc.createElement("style");
    s.id = "pgkb-css"; s.textContent = CSS;
    (doc.head || doc.documentElement).appendChild(s);
  }
  function store(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) {} return null; }

  function init(o) {
    o = o || {};
    var api = { hide: function () {}, scan: function () {}, on: false };
    if (!(W.matchMedia && matchMedia("(pointer: coarse)").matches) || o.off) return api;
    css();
    var T = o.T || function (s) { return s; };
    var SEL = o.sel;
    var reduce = !!(W.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches);
    var langs = o.langs || ["ru", "kz", "en"];
    var lang = store("pgkb_lang");
    if (langs.indexOf(lang) < 0) lang = langs.indexOf(o.lang) > -1 ? o.lang : langs[0];
    var el = o.el || doc.createElement("div");
    el.className = (el.className ? el.className + " " : "") + "pgkb pgkb--" + (o.theme || "site");
    el.hidden = true;
    el.setAttribute("aria-hidden", "true");
    if (!el.parentNode) (o.host || doc.body).appendChild(el);

    var cur = null, mode = lang, shift = false, held = null, holdT = 0, repT = 0;
    function sel(f) { try { return f.selectionStart; } catch (e) { return null; } }
    function isNum(f) { var im = f.getAttribute("data-im") || ""; return f.type === "tel" || f.type === "number" || im === "decimal" || im === "numeric" || im === "tel"; }
    function arm(f) {
      if (f.getAttribute("data-kb")) return;
      f.setAttribute("data-kb", "1");
      f.setAttribute("data-im", f.getAttribute("inputmode") || "");
      f.setAttribute("data-ac", f.getAttribute("autocomplete") || "");
      f.setAttribute("inputmode", "none");
      f.setAttribute("autocomplete", "off");
      f.setAttribute("autocorrect", "off");
      f.setAttribute("spellcheck", "false");
    }
    function ok(f) { return f && f.matches && f.matches(SEL) && !f.getAttribute("data-sys") && !f.readOnly && !f.disabled; }
    function scan(root) {
      var list = (root && root.querySelectorAll ? root : doc).querySelectorAll(SEL);
      for (var i = 0; i < list.length; i++) if (!list[i].getAttribute("data-sys")) arm(list[i]);
    }
    function key(ch, cls, attr, base) { return '<button type="button" tabindex="-1" class="pgkb-k' + (cls ? " " + cls : "") + '"' + (attr || ' data-k="' + ch.replace(/"/g, "&quot;") + '"') + (base ? ' data-b="' + base.replace(/"/g, "&quot;") + '"' : "") + ">" + ch.replace(/&/g, "&amp;").replace(/</g, "&lt;") + "</button>"; }
    function nextLang() { return langs[(langs.indexOf(lang) + 1) % langs.length]; }
    function render() {
      /* 34.2: везде только эта клавиатура — кнопки «Системная клавиатура» нет (o.noSys) */
      var h = '<div class="pgkb-top">' + (o.noSys ? '<span class="pgkb-t pgkb-tl">' + (NAMES[lang] || "") + "</span>" : '<button type="button" tabindex="-1" class="pgkb-t" data-a="sys">' + T("Системная клавиатура") + "</button>") + '<button type="button" tabindex="-1" class="pgkb-t pgkb-done" data-a="done">' + T("Готово") + "</button></div>";
      if (mode === "num") {
        var dec = cur && cur.type !== "tel";
        h += '<div class="pgkb-num">';
        "123456789".split("").forEach(function (c) { h += key(c); });
        h += key(dec ? "," : "+") + key("0") + key("⌫", "sp", ' data-a="bs" aria-label="' + T("Стереть") + '"') + "</div>";
      } else {
        var rows = LAYOUTS[mode], last = rows.length - 1;
        rows.forEach(function (r, i) {
          h += '<div class="pgkb-r' + (rows.length > 3 ? " kz4" : "") + '">';
          if (i === last && mode !== "sym") h += key("⇧", "sp" + (shift ? " act" : ""), ' data-a="shift" aria-label="' + T("Заглавные") + '"');
          r.split("").forEach(function (c) { h += mode === "sym" ? key(c) : key(shift ? c.toUpperCase() : c, "", null, c); });
          if (i === last) h += key("⌫", "sp", ' data-a="bs" aria-label="' + T("Стереть") + '"');
          h += "</div>";
        });
        var ent = cur && cur.hasAttribute("data-kb-send") ? "↑" : (cur && cur.tagName === "TEXTAREA" ? "↵" : T("Далее"));
        var mail = cur && cur.type === "email";
        h += '<div class="pgkb-r">' + key(mode === "sym" ? (NAMES[lang] || "АБВ") : "123", "sp w", ' data-a="mode"') +
          (langs.length > 1 ? key(NAMES[nextLang()], "sp", ' data-a="lang" aria-label="' + T("Язык клавиатуры") + '"') : "") +
          (mail ? key("@") : "") +
          key(" ", "space", ' data-k=" " aria-label="' + T("Пробел") + '"') + key(mode === "sym" ? "," : ".", "") + key(ent, "sp go w", ' data-a="enter"') + "</div>";
      }
      el.innerHTML = h + '<div class="pgkb-pop" aria-hidden="true"></div>';
      pop = el.querySelector(".pgkb-pop");
      measure();
    }
    var pop = null;
    /* Shift: меняем только буквы на месте — нажатия, которые уже идут, не теряются */
    function setShift(v) {
      shift = !!v;
      if (mode === "num" || mode === "sym") return;
      var ks = el.querySelectorAll(".pgkb-k[data-b]");
      for (var i = 0; i < ks.length; i++) {
        var c = ks[i].getAttribute("data-b"), u = shift ? c.toUpperCase() : c;
        if (ks[i].getAttribute("data-k") !== u) { ks[i].setAttribute("data-k", u); if (!ks[i].classList.contains("alt")) ks[i].textContent = u; }
      }
      var sh = el.querySelector('[data-a="shift"]'); if (sh) sh.classList.toggle("act", shift);
    }
    var padT = 0;
    function measure(zero) {
      var hgt = (zero || el.hidden) ? 0 : el.offsetHeight;
      W.PGKB_H = hgt;
      doc.documentElement.style.setProperty("--pgkb", hgt + "px");
      if (o.layer) o.layer.style.setProperty("--kb", hgt + "px");
      var p = o.pad ? o.pad() : null;
      if (p) {
        clearTimeout(padT);
        if (hgt) p.style.paddingBottom = hgt + "px";
        else if (p.style.paddingBottom) {
          /* клавиатура уходит: если страница была прокручена в её место — плавно возвращаем, отступ убираем после (без скачка) */
          var se = doc.scrollingElement || doc.documentElement, cur = parseFloat(p.style.paddingBottom) || 0;
          var over = se.scrollTop + W.innerHeight - (se.scrollHeight - cur);
          if (over > 1) W.scrollBy({ top: -over, behavior: reduce ? "auto" : "smooth" });
          padT = setTimeout(function () { if (!el.classList.contains("on")) p.style.paddingBottom = ""; }, reduce ? 0 : 220);
        }
      }
      try { W.dispatchEvent(new Event("pgkb")); } catch (e) {}
    }
    function fire(f) { f.dispatchEvent(new Event("input", { bubbles: true })); }
    function caps() {
      if (!cur || mode === "sym" || mode === "num" || cur.type === "email" || cur.type === "url" || cur.type === "password") return false;
      /* логин и подобные поля: autocapitalize="none" — без заглавных */
      var acp = (cur.getAttribute("autocapitalize") || "").toLowerCase();
      if (acp === "none" || acp === "off") return false;
      var s = sel(cur), before = cur.value.slice(0, s == null ? cur.value.length : s);
      if (before === "") return true;
      if (/[.!?]\s$/.test(before)) return true;
      return (cur.name === "name" || cur.name === "pet" || cur.name === "title") && /\s$/.test(before);
    }
    function ins(t) {
      var f = cur;
      if (!f) return;
      var s = sel(f);
      if (f.maxLength > 0 && f.value.length >= f.maxLength && (s == null || s === f.selectionEnd)) return;
      /* 36.2: два пробела подряд — точка и пробел (как на iPhone) */
      var now = Date.now();
      if (t === " " && s != null && s === f.selectionEnd && now - lastSp < 700 && f.type !== "password" && f.type !== "email" && f.type !== "url" && !/^(login|log|user|username)$/.test(f.name || "") && /[^\s.,!?:;]\s$/.test(f.value.slice(Math.max(0, s - 2), s))) {
        f.setRangeText(". ", s - 1, s, "end"); lastSp = 0;
      } else {
        if (s == null) f.value += t; else f.setRangeText(t, s, f.selectionEnd, "end");
        lastSp = t === " " ? now : 0;
      }
      fire(f);
      var c = caps();
      if (c !== shift && mode !== "num" && mode !== "sym") setShift(c);
    }
    var lastSp = 0;
    function bs() {
      var f = cur;
      if (!f) return;
      var s = sel(f), e = s == null ? null : f.selectionEnd;
      if (s == null) f.value = f.value.slice(0, -1);
      else if (s !== e) f.setRangeText("", s, e, "end");
      else if (s > 0) f.setRangeText("", s - 1, s, "end");
      fire(f);
      var c = caps();
      if (c !== shift && mode !== "num" && mode !== "sym") setShift(c);
    }
    function vis(f) { return f.offsetParent !== null || f.getClientRects().length > 0; }
    function nextField() {
      if (!cur) return null;
      var box = cur.form || cur.closest(".pgc-box, .pgc-sheet, .pgc-fe, .pg-sheet, form") || doc;
      var all = Array.prototype.filter.call(box.querySelectorAll(SEL), function (f) { return vis(f) && !f.readOnly && !f.disabled; }), i = all.indexOf(cur);
      return all[i + 1] || null;
    }
    function act(a) {
      if (a === "shift") { setShift(!shift); }
      else if (a === "mode") { mode = mode === "sym" ? lang : "sym"; shift = mode === "sym" ? false : caps(); render(); }
      else if (a === "lang") { lang = nextLang(); store("pgkb_lang", lang); mode = lang; shift = caps(); render(); }
      else if (a === "done") { var f = cur; hide(); if (f) f.blur(); }
      else if (a === "sys") {
        var g = cur;
        if (!g) return;
        g.setAttribute("data-sys", "1");
        var im = g.getAttribute("data-im"), ac = g.getAttribute("data-ac");
        if (im) g.setAttribute("inputmode", im); else g.removeAttribute("inputmode");
        if (ac) g.setAttribute("autocomplete", ac); else g.removeAttribute("autocomplete");
        hide(); g.blur();
        setTimeout(function () { g.focus(); }, 60);
      } else if (a === "enter") {
        if (!cur) return;
        if (cur.hasAttribute("data-kb-send") && cur.form) { cur.form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true })); return; }
        if (cur.tagName === "TEXTAREA") { ins("\n"); return; }
        var n = nextField();
        if (n) n.focus(); else { var c = cur; hide(); c.blur(); }
      }
    }
    function stopHold() {
      clearTimeout(holdT); clearInterval(repT);
      if (held) { held.b.classList.remove("down", "alt"); if (held.txt != null) held.b.textContent = held.txt; }
      if (pop) pop.classList.remove("on");
      held = null;
    }
    /* 36.2: палец попал между клавишами — берём ближайшую (у системной клавиатуры тоже нет «мёртвых» мест) */
    function keyAt(x, y) {
      var bs = el.querySelectorAll("button"), best = null, bd = 1e9;
      for (var i = 0; i < bs.length; i++) {
        var r = bs[i].getBoundingClientRect();
        if (!r.width) continue;
        var dx = x < r.left ? r.left - x : (x > r.right ? x - r.right : 0), dy = y < r.top ? r.top - y : (y > r.bottom ? y - r.bottom : 0), d = dx * dx + dy * dy;
        if (d < bd) { bd = d; best = bs[i]; }
      }
      return bd <= 26 * 26 ? best : null;
    }
    function keyFor(e) {
      var b = e.target && e.target.closest ? e.target.closest("button") : null;
      return b && el.contains(b) ? b : keyAt(e.clientX, e.clientY);
    }
    /* нажатие засчитывается: буква — та, что на клавише сейчас (с учётом Shift и долгого нажатия) */
    function commit(h) {
      if (h.a) { if (h.a !== "bs") act(h.a); return; }
      var ch = h.ch != null ? h.ch : h.b.getAttribute("data-k");
      if (ch != null) ins(ch);
    }
    function showPop(b) {
      if (!pop || mode === "num" || !b.hasAttribute("data-k") || b.classList.contains("space")) return;
      var r = b.getBoundingClientRect(), er = el.getBoundingClientRect();
      pop.textContent = b.getAttribute("data-k");
      pop.style.left = (r.left - er.left + r.width / 2) + "px";
      pop.style.top = (r.top - er.top) + "px";
      pop.style.minWidth = Math.round(r.width * 1.25) + "px";
      pop.classList.add("on");
    }
    el.addEventListener("mousedown", function (e) { e.preventDefault(); });
    el.addEventListener("contextmenu", function (e) { e.preventDefault(); });
    el.addEventListener("pointerdown", function (e) {
      e.preventDefault();
      var b = keyFor(e);
      if (!b) return;
      /* быстрый набор: второй палец нажал, пока первый ещё на экране — первая буква вводится сразу, а не теряется */
      if (held && held.id !== e.pointerId) { var prev = held; stopHold(); commit(prev); }
      else stopHold();
      b.classList.add("down");
      if (navigator.vibrate) { try { navigator.vibrate(4); } catch (x) {} }
      var a = b.getAttribute("data-a");
      held = { b: b, id: e.pointerId, ch: null, txt: null, a: null };
      if (a === "bs") { held.a = "bs"; bs(); holdT = setTimeout(function () { repT = setInterval(bs, 60); }, 380); return; }
      if (a) { held.a = a; return; }
      var ch = b.getAttribute("data-k");
      if (ch == null) return;
      showPop(b);
      var alts = ALT[ch.toLowerCase()];
      if (alts) {
        var step = 0;
        holdT = setTimeout(function next() {
          if (!held || held.b !== b) return;
          var cur0 = b.getAttribute("data-k"), up = cur0 !== cur0.toLowerCase();
          if (held.txt == null) held.txt = b.textContent;
          var c = alts.charAt(step % alts.length);
          held.ch = up ? c.toUpperCase() : c;
          if (pop) pop.classList.remove("on");
          b.textContent = held.ch; b.classList.add("alt");
          step++;
          if (alts.length > 1) holdT = setTimeout(next, 650);
        }, 380);
      }
    });
    function up(e) {
      if (!held || held.id !== e.pointerId) return;
      var h = held;
      stopHold();
      /* браузер отменил касание (жест) — всё равно засчитываем нажатую клавишу */
      commit(h);
    }
    el.addEventListener("pointerup", up);
    el.addEventListener("pointercancel", up);
    el.addEventListener("pointerleave", function (e) { if (held && e.pointerType === "mouse") stopHold(); });

    function show(f) {
      cur = f;
      mode = isNum(f) ? "num" : (f.type === "email" || f.type === "url" ? "en" : lang);
      shift = caps();
      clearTimeout(hideT);
      el.hidden = false;
      doc.documentElement.classList.add("pgkb-open");
      if (o.layer) o.layer.classList.add("kb-open");
      render();
      requestAnimationFrame(function () { el.classList.add("on"); });
      setTimeout(function () { if (cur === f && f.scrollIntoView && !f.hasAttribute("data-kb-send")) f.scrollIntoView({ block: "center", behavior: reduce ? "auto" : "smooth" }); }, 140);
      if (f.type === "tel" && f.value === "" && o.telPrefix !== false) { f.value = "+7 "; fire(f); }
    }
    /* 34.3: клавиатура уезжает вниз плавно (как появляется), страница и листы опускаются вместе с ней */
    var hideT = 0;
    function hide() {
      if (el.hidden || !el.classList.contains("on")) return;
      stopHold();
      cur = null;
      el.classList.remove("on");
      doc.documentElement.classList.remove("pgkb-open");
      if (o.layer) { o.layer.classList.remove("kb-open"); o.layer.style.removeProperty("--kb"); }
      measure(true);
      clearTimeout(hideT);
      hideT = setTimeout(function () { if (!el.classList.contains("on")) el.hidden = true; }, reduce ? 0 : 220);
    }
    /* пока палец на экране, клавиатуру не прячем: иначе всё сдвигается и нажатие (например, на «Отправить»)
       попадает мимо кнопки. Прячем, когда палец отпущен и нажатие уже сработало */
    var pressing = false, later = false;
    function check() { var a = doc.activeElement; if (!ok(a)) hide(); }
    doc.addEventListener("pointerdown", function (e) { if (!el.contains(e.target)) pressing = true; }, true);
    doc.addEventListener("pointerup", function () { if (!pressing) return; pressing = false; if (later) { later = false; setTimeout(check, 120); } }, true);
    doc.addEventListener("pointercancel", function () { pressing = false; if (later) { later = false; setTimeout(check, 120); } }, true);
    /* поле ещё не «вооружено» (появилось только что) — до фокуса ставим inputmode=none, чтобы системная не выскочила */
    doc.addEventListener("touchstart", function (e) { var f = e.target; if (f && f.matches && f.matches(SEL) && !f.getAttribute("data-sys")) arm(f); }, { capture: true, passive: true });
    doc.addEventListener("focusin", function (e) {
      var f = e.target;
      if (ok(f)) { later = false; arm(f); show(f); }
    });
    doc.addEventListener("focusout", function () {
      if (pressing) { later = true; return; }
      setTimeout(check, 80);
    });
    W.addEventListener("resize", function () { if (el.classList.contains("on")) measure(); });
    /* новые поля (страницы и окна приложения загружаются без перезагрузки) */
    if (W.MutationObserver) {
      var t = 0;
      new MutationObserver(function (recs) {
        for (var i = 0; i < recs.length; i++) {
          if (!el.contains(recs[i].target)) { clearTimeout(t); t = setTimeout(function () { scan(); if (cur && !cur.isConnected) hide(); }, 60); return; }
        }
      })
        .observe(doc.body, { childList: true, subtree: true });
    }
    scan();
    api.hide = hide; api.on = true; api.scan = scan; api.el = el;
    W.PGKB_HIDE = function () { var a = doc.activeElement; hide(); if (a && a !== doc.body && a.blur && ok(a)) a.blur(); };
    return api;
  }
  W.PGKB = { init: init };
})();
