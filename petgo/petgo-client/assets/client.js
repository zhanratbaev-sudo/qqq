/* PetGo Клиент — кабинет клиента (34.4). Движение — по правилам UX-анимации (.claude/skills/charles-ux-animation).
   Вкладки внизу на всю ширину, листы поверх страницы, заказ с колёсами времени (часы и минуты),
   чат всегда прижат вниз и новые сообщения появляются плавно, карта прогулки, тёмная тема.
   Общие помощники (уменьшение фото, маска телефона, анкета) берёт у «PetGo Сайт» — window.PGForm. */
(function () {
  "use strict";
  var D = document, W = window;
  var C = W.PG_CONFIG || {}, K = null;
  var reduce = W.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches;
  function F() { return W.PGForm || {}; }
  function T(s) { return (K && K.tr && K.tr[s]) || (C.tr && C.tr[s]) || s; }
  function $(s, el) { return (el || D).querySelector(s); }
  function qa(s, el) { return Array.prototype.slice.call((el || D).querySelectorAll(s)); }
  function esc(x) { return String(x == null ? "" : x).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
  function json(r) { return F().json ? F().json(r) : r.json(); }
  function shrink(f, m) { return F().shrink ? F().shrink(f, m) : Promise.resolve(f); }
  function phoneMask(el) { if (F().phoneMask) F().phoneMask(el); }
  function svg(d, cls) { return '<svg class="' + (cls || "pg-i") + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + "</svg>"; }
  var STAR = '<path d="M12 3.8l2.5 5.2 5.7.8-4.1 4 1 5.7L12 16.8l-5.1 2.7 1-5.7-4.1-4 5.7-.8z"/>';
  var ICON = {
    qr: '<svg class="pg-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="3.5" width="6.5" height="6.5" rx="1.2"/><rect x="14" y="3.5" width="6.5" height="6.5" rx="1.2"/><rect x="3.5" y="14" width="6.5" height="6.5" rx="1.2"/><path d="M14 14h3v3M20.5 14v.01M14 20.5h3M20.5 17.5v3h-3"/></svg>',
    route: svg('<circle cx="6" cy="18.5" r="2"/><circle cx="18" cy="5.5" r="2"/><path d="M8 18.5h8a3.3 3.3 0 0 0 0-6.6H8a3.3 3.3 0 0 1 0-6.6h8"/>'),
    star: svg(STAR), starOn: svg(STAR, "pg-i on"),
    check: svg('<path d="M5 12.5l4.5 4.5L19 7.5"/>', "pg-i pgk-ok"),
    users: svg('<circle cx="9" cy="8.5" r="3.2"/><path d="M3 19.5c0-3.4 2.7-5.4 6-5.4s6 2 6 5.4"/><circle cx="16.6" cy="9.3" r="2.6"/><path d="M16.8 14.2c2.6.2 4.4 2 4.4 4.8"/>')
  };
  var LOGO = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 12.3c-2.7 0-4.8 2.1-4.8 4 0 1.7 1.4 2.4 2.8 2 .9-.3 1.4-.6 2-.6s1.1.3 2 .6c1.4.4 2.8-.3 2.8-2 0-1.9-2.1-4-4.8-4z"/><ellipse cx="6.6" cy="10" rx="1.5" ry="2"/><ellipse cx="9.9" cy="6.5" rx="1.5" ry="2.1"/><ellipse cx="14.1" cy="6.5" rx="1.5" ry="2.1"/><ellipse cx="17.4" cy="10" rx="1.5" ry="2"/></svg>';
  var CL = { box: null, tab: "home", timer: 0, chatT: 0, lastMsg: 0, chatOpen: false, scroll: {}, day: "" };

  /* ---------- переключатели (как в iPhone): плашка едет к выбранному ---------- */
  function sgMove(sg, instant) {
    if (!sg) return;
    var i = $(".pgk-sgi", sg), on = $(".pg-b.on", sg);
    if (!i) return;
    if (!on || !on.offsetWidth) { i.style.opacity = on ? "" : "0"; return; }
    i.style.opacity = "";
    if (instant || !i._ready) i.style.transition = "none";
    i.style.width = on.offsetWidth + "px";
    i.style.transform = "translateX(" + (on.offsetLeft - 0) + "px)";
    if (instant || !i._ready) { void i.offsetWidth; i.style.transition = ""; i._ready = 1; }
  }
  function sgAll(root) { qa(".pgk-sg", root || D).forEach(function (s) { sgMove(s); }); }

  /* ---------- тема: авто / светлая / тёмная ---------- */
  function themeGet() { try { return localStorage.getItem("pgk_theme") || "auto"; } catch (e) { return "auto"; } }
  var mq = W.matchMedia ? matchMedia("(prefers-color-scheme: dark)") : null;
  function themeApply(user) {
    var root = D.documentElement;
    /* цвета меняются одним кадром: на время смены переходы выключены */
    if (user) { root.classList.add("pgk-theming"); requestAnimationFrame(function () { requestAnimationFrame(function () { root.classList.remove("pgk-theming"); }); }); }
    var t = themeGet(), on = !!(D.getElementById("pgk") || D.getElementById("pgk-gate")) && (t === "dark" || (t === "auto" && !!(mq && mq.matches)));
    D.documentElement.classList.toggle("pgk-dark", on);
    var m = $('meta[name="theme-color"]'); if (m) m.setAttribute("content", on ? "#0B0D0C" : "#F4F6F3");
    qa("[data-k-theme]").forEach(function (b) { b.classList.toggle("on", b.getAttribute("data-k-theme") === t); });
    sgMove($(".pgk-theme"));
    MapK.theme();
  }
  if (mq && mq.addEventListener) mq.addEventListener("change", function () { if (K || D.getElementById("pgk-gate")) themeApply(); });

  /* ---------- запросы ---------- */
  function post(data, files) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    if (files) Object.keys(files).forEach(function (k) { fd.append(k, files[k], "photo.jpg"); });
    return fetch((K && K.aj) || C.ajax || "/wp-admin/admin-ajax.php", { method: "POST", body: fd, credentials: "same-origin" }).then(json);
  }
  function api(op, data, files) {
    var d = data || {};
    d.action = "petgo_cl"; d.op = op; d.n = K.n; d.lang = K.lang || "ru";
    return post(d, files).then(function (j) { if (j && j.auth) location.reload(); return j; });
  }
  var toastT = 0;
  function toast(t, bad) {
    var el = $("[data-k-toast]");
    if (!el) { el = D.createElement("div"); el.className = "pgk-toast"; el.setAttribute("data-k-toast", ""); (D.getElementById("pg-layer") || D.body).appendChild(el); }
    el.textContent = t; el.classList.toggle("bad", !!bad); el.classList.add("on");
    clearTimeout(toastT); toastT = setTimeout(function () { el.classList.remove("on"); }, 2600);
  }
  function kbHide() { if (W.PGKB_HIDE) W.PGKB_HIDE(); else if (D.activeElement && D.activeElement.blur) D.activeElement.blur(); }

  /* ---------- листы: выезжают снизу, открываются друг над другом ---------- */
  var stack = [];
  function kOpen(id) {
    var s = D.getElementById("pgk-" + id);
    if (!s) return null;
    if (stack.indexOf(s) > -1) return s;
    if (W.PGCloseAI) W.PGCloseAI();
    s.style.zIndex = String(9996 + stack.length);
    s.inert = false;
    s.hidden = false; void s.offsetWidth; s.classList.add("on");
    D.documentElement.classList.add("pgk-lock");
    stack.push(s);
    return s;
  }
  function kClose(all) {
    kbHide();
    do {
      var s = stack.pop();
      if (!s) break;
      s.classList.remove("on"); s.inert = true;
      var inn = $(".pgk-sin", s); if (inn) inn.style.transform = "";
      if (s.id === "pgk-chat") { CL.chatOpen = false; clearInterval(CL.chatT); }
      if (s.id === "pgk-order") ordStop();
      if (s.id === "pgk-review" && CL.rvAuto) { var o = $("[data-k-rvform]", s); if (o && o.elements.order.value && !CL.rvSent) api("rv_skip", { order: o.elements.order.value }).catch(function () {}); CL.rvAuto = false; }
      (function (x) { setTimeout(function () { if (!x.classList.contains("on")) x.hidden = true; }, reduce ? 0 : 230); })(s);
    } while (all && stack.length);
    if (!stack.length) D.documentElement.classList.remove("pgk-lock");
  }
  /* лист можно смахнуть вниз за шапку */
  function dragSheets(root) {
    qa("[data-k-drag]", root).forEach(function (h) {
      if (h._d) return; h._d = 1;
      var y0 = 0, dy = 0, t0 = 0, ly = 0, lt = 0, v = 0, inn = h.closest(".pgk-sin"), sh = h.closest(".pgk-sheet"), drag = false;
      h.addEventListener("touchstart", function (e) { if (e.target.closest("button, a")) return; drag = true; y0 = ly = e.touches[0].clientY; t0 = lt = Date.now(); dy = 0; v = 0; sh.classList.add("drag"); }, { passive: true });
      h.addEventListener("touchmove", function (e) {
        if (!drag) return;
        var y = e.touches[0].clientY, now = Date.now();
        if (now > lt) { v = (y - ly) / (now - lt); ly = y; lt = now; }
        dy = y - y0;
        /* вверх — с сопротивлением (лист упирается), вниз — ровно за пальцем */
        var up = dy < 0 ? -12 * (-dy / (-dy + 150)) : dy;
        inn.style.transform = "translateY(" + up + "px)";
      }, { passive: true });
      h.addEventListener("touchend", function () { if (!drag) return; drag = false; sh.classList.remove("drag"); if (dy > 110 || (dy > 8 && v > 0.1)) kClose(); else inn.style.transform = ""; });
    });
  }

  /* ---------- формы ---------- */
  /* ошибка после отправки: сообщение рядом, поле с ошибкой коротко «качает головой» */
  function shake(el) { if (!el || reduce) return; el.classList.remove("pgk-shake"); void el.offsetWidth; el.classList.add("pgk-shake"); setTimeout(function () { el.classList.remove("pgk-shake"); }, 400); }
  function formErr(f, msg, field, box) {
    var er = $(".pgk-err", f);
    qa(".bad", f).forEach(function (x) { x.classList.remove("bad"); });
    if (er) { er.textContent = msg; er.hidden = !msg; }
    var el = field && f.elements[field];
    if (el && el.closest) {
      var b = el.closest(".pgk-f") || el.closest(".pgk-check");
      if (b) { b.classList.add("bad"); shake(b); }
      if (el.focus && el.type !== "checkbox") try { el.focus({ preventScroll: true }); } catch (x) {}
    } else if (box) shake(box);
  }
  function busyBtn(f, on) {
    var b = $(".pgk-go", f);
    if (!b) return;
    clearTimeout(b._bt);
    if (on) { b.disabled = true; b._at = 0; b._bt = setTimeout(function () { b.classList.add("busy"); b._at = Date.now(); }, 300); }
    else { b.classList.remove("busy"); b.disabled = false; b._at = 0; }
  }
  /* ответ пришёл: если крутилка уже видна — держим её минимум 500 мс, потом показываем результат */
  function busyEnd(f, fn) {
    var b = $(".pgk-go", f), wait = b && b._at ? Math.max(0, 500 - (Date.now() - b._at)) : 0;
    if (b) clearTimeout(b._bt);
    setTimeout(function () { busyBtn(f, false); fn(); }, wait);
  }
  function netErr(f) { return function () { busyEnd(f, function () { formErr(f, T("Не получилось — проверьте интернет.")); }); }; }
  D.addEventListener("submit", function (e) {
    var f = e.target;
    if (!f.matches) return;
    if (f.matches(".pgk-form[data-op]")) {
      /* вход, регистрация, «забыли пароль» */
      e.preventDefault(); kbHide();
      formErr(f, ""); busyBtn(f, true);
      var fd = new FormData(f);
      fd.append("action", f.getAttribute("data-op"));
      fetch(C.ajax || "/wp-admin/admin-ajax.php", { method: "POST", body: fd, credentials: "same-origin" }).then(json).then(function (j) {
        /* 34.5: цель «регистрация» для рекламы — отправляем и только потом переходим */
        if (j.ok && j.ev && W.PGEvent) W.PGEvent(j.ev, {});
        if (j.ok && j.go) { var go = j.go; setTimeout(function () { location.href = go; }, j.ev ? 350 : 0); return; }
        busyEnd(f, function () {
          if (!j.ok) { formErr(f, j.msg || "", j.field, $(".pgk-list", f)); return; }
          if (j.wa) { var dn = f.parentNode.querySelector(".pgk-done"); if (dn) { f.hidden = true; dn.hidden = false; var w = $("[data-wa]", dn); if (w) w.href = j.wa; } }
        });
      }).catch(netErr(f));
      return;
    }
    if (!K) return;
    if (f.matches(".pgk-form[data-k-op]")) {
      e.preventDefault(); kbHide();
      formErr(f, ""); busyBtn(f, true);
      var d = {};
      qa("input,textarea,select", f).forEach(function (x) { if (x.name) d[x.name] = x.value; });
      api(f.getAttribute("data-k-op"), d).then(function (j) {
        if (j.ok && j.go) { location.href = j.go; return; }
        busyEnd(f, function () {
          if (!j.ok) { formErr(f, j.msg || "", j.field, $(".pgk-list", f)); return; }
          toast(j.msg || T("Сохранено"));
          if (f.getAttribute("data-k-op") === "pass") { f.reset(); var dt = f.closest("details"); if (dt) dt.open = false; }
          /* 38.1: личные данные сохранены — имя и телефон в шапке профиля сразу новые, лист закрывается */
          if (f.getAttribute("data-k-op") === "profile") { var mb = $(".pgk-me-t b"), ms = $(".pgk-me-t small"); if (mb) mb.textContent = d.name; if (ms && d.phone) ms.textContent = d.phone; if (f.closest("#pgk-me")) kClose(); }
        });
      }).catch(netErr(f));
      return;
    }
    if (f.matches("[data-k-rvform]")) {
      e.preventDefault(); kbHide();
      formErr(f, ""); busyBtn(f, true);
      var r = $('input[name="rating"]:checked', f), rate = +(r ? r.value : 5);
      api("review", { order: f.elements.order.value, rating: rate, text: f.elements.text.value }).then(function (j) { busyEnd(f, function () {
        if (!j.ok) { formErr(f, j.msg || ""); return; }
        qa('[data-k-review="' + f.elements.order.value + '"]').forEach(function (b) {
          if (b.closest(".pgk-n, .pgk-m-pg")) { b.outerHTML = '<span class="pgk-myrv">' + stars(rate) + "<small>" + esc(T("Спасибо за оценку")) + "</small></span>"; return; }
          var st = ""; for (var i = 1; i <= 5; i++) st += i <= rate ? ICON.starOn : ICON.star;
          var w = b.closest(".pgk-wacts");
          b.remove();
          if (w) w.insertAdjacentHTML("beforebegin", '<div class="pgk-myrv">' + st + "<small>" + esc(T("Ваш отзыв")) + "</small></div>");
        });
        CL.rvSent = true; f.reset(); kClose(); CL.rvSent = false; toast(j.msg || T("Отправлено"));
      }); }).catch(netErr(f));
      return;
    }
    /* 39.2: отзыв о тестовой неделе — менеджеру в чат и в список в приложении сотрудников */
    if (f.matches("[data-k-fbform]")) {
      e.preventDefault(); kbHide();
      formErr(f, ""); busyBtn(f, true);
      var fr = $('input[name="rating"]:checked', f);
      api("tw_fb", { rating: fr ? fr.value : 5, text: f.elements.text.value, call: f.elements.call && f.elements.call.checked ? "1" : "0" }).then(function (j) { busyEnd(f, function () {
        if (!j.ok) { formErr(f, j.msg || ""); return; }
        if (K.test) K.test.fb = 1;
        qa('.pgk-tw-fb[data-k-open="fb"]').forEach(function (b) { b.textContent = T("Дополнить отзыв"); });
        f.reset(); kClose(); toast(j.msg || T("Спасибо за отзыв!"));
      }); }).catch(netErr(f));
      return;
    }
    if (f.matches("[data-k-send]")) { e.preventDefault(); sendMsg(f); }
  });

  /* вход / регистрация */
  function authSeg(box, to) {
    var sg = $(".pgk-auth-sg", box);
    if (!sg) return;
    qa("[data-auth]", sg).forEach(function (b) { var on = b.getAttribute("data-auth") === to; b.classList.toggle("on", on); b.setAttribute("aria-selected", on ? "true" : "false"); });
    sgMove(sg);
    qa("[data-auth-p]", box).forEach(function (p) { var on = p.getAttribute("data-auth-p") === to; p.classList.toggle("on", on); p.inert = !on; });
  }

  /* ---------- прокрутка: на телефоне кабинет прокручивается внутри себя (страница браузера стоит на месте) ---------- */
  function scroller() {
    var p = D.getElementById("pg");
    return (p && D.documentElement.classList.contains("pgk-on") && W.matchMedia && matchMedia("(max-width: 860px)").matches) ? p : null;
  }
  function scrollY() { var sc = scroller(); return sc ? sc.scrollTop : W.scrollY; }
  function scrollToY(y, smooth) {
    var sc = scroller(), o = { top: y, behavior: smooth && !reduce ? "smooth" : "auto" };
    if (sc) sc.scrollTo(o); else W.scrollTo(o);
  }

  /* ---------- вкладки ---------- */
  /* 38.1: «Мои заказы» открываются из профиля — справа от него */
  var TABS = ["home", "map", "acc", "walks"];
  function tab(to, opts) {
    if (!CL.box || TABS.indexOf(to) < 0) return;
    opts = opts || {};
    var from = CL.tab;
    if (from === to && !opts.force) {
      /* нажали на открытую вкладку — наверх, как в iPhone */
      if (!opts.keep || opts.bar) scrollToY(0, true);
      if (to === "map") MapK.show(opts);
      return;
    }
    CL.scroll[from] = scrollY();
    CL.tab = to;
    CL.box.setAttribute("data-tab", to);
    /* 38.0: новый экран въезжает с той стороны, куда переключаете (после свайпа он уже на месте) */
    var dir = TABS.indexOf(to) > TABS.indexOf(from) ? "l" : "r";
    qa(".pgk-pane", CL.box).forEach(function (p) {
      var on = p.getAttribute("data-pane") === to;
      p.classList.toggle("on", on);
      p.classList.remove("pgk-in-l", "pgk-in-r", "pgk-peek");
      p.style.transform = "";
      if (on && from && !opts.force && !opts.swipe && !reduce) { void p.offsetWidth; p.classList.add("pgk-in-" + dir); setTimeout(function () { p.classList.remove("pgk-in-l", "pgk-in-r"); }, 480); }
    });
    var bt = to === "walks" ? "acc" : to;
    qa(".pgk-tabbar [data-k-tab]").forEach(function (b) { var on = b.getAttribute("data-k-tab") === bt; b.classList.toggle("on", on); if (on) b.setAttribute("aria-current", "page"); else b.removeAttribute("aria-current"); });
    try { history.replaceState(history.state, "", "#" + to); sessionStorage.setItem("pgk_tab", to); } catch (x) {}
    scrollToY(opts.keep ? (CL.scroll[to] || 0) : 0, false);
    if (to === "map") MapK.show(opts);
    sgAll(CL.box);
    lensMove(!!opts.force);
    topTitle(); onScrollTop();
    live();
  }

  /* ============================================================
     38.0 СТИЛЬ iPHONE: «капля» под выбранной вкладкой, верхняя панель при прокрутке, листание вкладок свайпом
     ============================================================ */
  function lensMove(instant) {
    var bar = $(".pgk-tabbar"), l = bar && $(".pgk-tlens", bar), b = bar && $(".pg-b.on[data-k-tab]", bar);
    if (!l) return;
    if (!b || !b.offsetWidth) { l.classList.remove("on"); return; }
    var tr = "translateX(" + b.offsetLeft + "px)";
    if (instant || !l.classList.contains("on")) l.style.transition = "none";
    else if (l.style.transform && l.style.transform !== tr) { l.classList.remove("mv"); void l.offsetWidth; l.classList.add("mv"); }
    l.style.width = b.offsetWidth + "px";
    l.style.transform = tr;
    l.classList.add("on");
    if (l.style.transition === "none") { void l.offsetWidth; l.style.transition = ""; }
  }
  var TB = null;
  function topbar() {
    if (!TB) { TB = D.createElement("div"); TB.className = "pgk-topbar"; TB.setAttribute("aria-hidden", "true"); TB.innerHTML = "<b></b>"; (D.getElementById("pg-layer") || D.body).appendChild(TB); }
    return TB;
  }
  function topTitle() {
    if (!CL.box) return;
    var p = $(".pgk-pane.on", CL.box), hd = p && $(".pgk-hd", p), h = hd && $("h1", hd);
    $("b", topbar()).textContent = hd && hd.getAttribute("data-title") ? hd.getAttribute("data-title") : h ? h.textContent : "";
  }
  function onScrollTop() { if (K && CL.box) topbar().classList.toggle("on", scrollY() > 64); }
  function scrollWatch() {
    var pg = D.getElementById("pg");
    if (pg && !pg._pgkTop) { pg._pgkTop = 1; pg.addEventListener("scroll", onScrollTop, { passive: true }); }
    if (!W._pgkTop) { W._pgkTop = 1; W.addEventListener("scroll", onScrollTop, { passive: true }); }
  }
  /* свайп влево — следующая вкладка, вправо — предыдущая: экран едет за пальцем, соседний — рядом;
     не дотянули — пружинкой на место. Карта, поля, переключатели и ленты листаются как обычно. */
  function swipeInit() {
    var panes = CL.box && $(".pgk-panes", CL.box);
    if (!panes || panes._sw || !("ontouchstart" in W)) return;
    panes._sw = 1;
    var sx = 0, sy = 0, t0 = 0, on = false, lock = 0, cur = null, nb = null, to = null, w = 0, lastDx = 0;
    var NO = "input, textarea, select, .leaflet-container, .pgk-map, .pgk-hscroll, .pgk-sg, .pgk-qr, [data-noswipe]";
    function peek(want) {
      if (nb) { nb.classList.remove("pgk-peek"); nb.style.transform = ""; nb.style.top = ""; }
      to = want; nb = want ? $('.pgk-pane[data-pane="' + want + '"]', panes) : null;
      /* соседний экран — там, где он окажется после переключения (вкладка откроется с начала) */
      if (nb) { nb.style.top = scrollY() + "px"; nb.classList.add("pgk-peek"); }
    }
    function clean() {
      panes.classList.remove("pgk-drag");
      [cur, nb].forEach(function (p) { if (p) { p.style.transition = ""; p.style.transform = ""; } });
      if (nb) { nb.classList.remove("pgk-peek"); nb.style.top = ""; }
      cur = nb = to = null;
    }
    panes.addEventListener("touchstart", function (e) {
      on = false;
      if (e.touches.length !== 1 || stack.length || !K) return;
      var x = e.touches[0].clientX;
      if (x < 22 || x > W.innerWidth - 22 || (e.target.closest && e.target.closest(NO))) return;
      sx = x; sy = e.touches[0].clientY; t0 = Date.now(); on = true; lock = 0; lastDx = 0;
      cur = $(".pgk-pane.on", panes); w = panes.offsetWidth || W.innerWidth;
    }, { passive: true });
    panes.addEventListener("touchmove", function (e) {
      if (!on || !cur) return;
      var dx = e.touches[0].clientX - sx, dy = e.touches[0].clientY - sy;
      if (!lock) { if (Math.abs(dx) < 10 && Math.abs(dy) < 10) return; lock = Math.abs(dx) > Math.abs(dy) * 1.3 ? 1 : 2; if (lock === 1) panes.classList.add("pgk-drag"); }
      if (lock !== 1) return;
      if (e.cancelable) e.preventDefault();
      var SW = ["home", "map", "acc"], want = CL.tab === "walks" ? (dx > 0 ? "acc" : null) : SW[SW.indexOf(CL.tab) + (dx < 0 ? 1 : -1)] || null;
      if (want !== to) peek(want);
      var x = to ? dx : dx * .2;
      lastDx = dx;
      cur.style.transform = "translate3d(" + x + "px,0,0)";
      if (nb) nb.style.transform = "translate3d(" + (x + (dx < 0 ? w : -w)) + "px,0,0)";
    }, { passive: false });
    function end(e) {
      if (!on) return;
      on = false;
      if (lock !== 1 || !cur) { clean(); return; }
      var dx = lastDx, v = Math.abs(dx) / Math.max(1, Date.now() - t0), go = to && e.type === "touchend" && (Math.abs(dx) > w * .28 || (Math.abs(dx) > 40 && v > .35));
      var c = cur, n = nb, target = to, ease = go ? "transform 360ms cubic-bezier(.32, .72, 0, 1)" : "transform 520ms var(--k-spring)";
      c.style.transition = ease; if (n) n.style.transition = ease;
      if (go) {
        c.style.transform = "translate3d(" + (dx < 0 ? -w : w) + "px,0,0)";
        n.style.transform = "translate3d(0,0,0)";
        if (navigator.vibrate) { try { navigator.vibrate(6); } catch (x) {} }
        setTimeout(function () { clean(); tab(target, { swipe: true }); }, 360);
      } else {
        c.style.transform = "translate3d(0,0,0)";
        if (n) n.style.transform = "translate3d(" + (dx < 0 ? w : -w) + "px,0,0)";
        setTimeout(clean, 520);
      }
    }
    panes.addEventListener("touchend", end, { passive: true });
    panes.addEventListener("touchcancel", end, { passive: true });
  }

  /* 39.2: потяните экран вниз (в самом верху) — приложение обновляется: новые питомцы, заказы, цены, няни.
     Кружок со стрелкой едет за пальцем и крутится; дотянули — отпустите, и экран перезагрузится на той же вкладке. */
  function ptrInit() {
    var sc = D.getElementById("pg");
    if (!sc || sc._ptr || !("ontouchstart" in W) || !K) return;
    sc._ptr = 1;
    var P = D.createElement("div"), TH = 70, y0 = 0, x0 = 0, on = false, pull = false, d = 0, busy = false;
    var NO = "input, textarea, select, .leaflet-container, .pgk-map, .pgk-sg, .pgk-wh-col, [data-noptr]";
    P.className = "pgk-ptr"; P.setAttribute("aria-hidden", "true");
    P.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3"/><path d="M19.5 4.5v4h-4"/></svg>';
    (D.getElementById("pg-layer") || D.body).appendChild(P);
    function atTop() { return scrollY() <= 0; }
    function show(dd) {
      var p = Math.min(1, dd / TH);
      P.style.transition = "none";
      P.style.opacity = String(Math.min(1, dd / 30));
      P.style.transform = "translate3d(-50%," + (dd - 52) + "px,0) rotate(" + Math.round(p * 270) + "deg) scale(" + (0.7 + p * 0.3) + ")";
      P.classList.toggle("ready", dd >= TH);
    }
    function hide() { P.style.transition = ""; P.style.opacity = ""; P.style.transform = ""; P.classList.remove("ready", "spin"); }
    sc.addEventListener("touchstart", function (e) {
      on = pull = false;
      if (busy || e.touches.length !== 1 || stack.length || !atTop() || (e.target.closest && e.target.closest(NO))) return;
      y0 = e.touches[0].clientY; x0 = e.touches[0].clientX; d = 0; on = true;
    }, { passive: true });
    sc.addEventListener("touchmove", function (e) {
      if (!on) return;
      var dy = e.touches[0].clientY - y0, dx = Math.abs(e.touches[0].clientX - x0);
      if (!pull) {
        if (dy < 8 && dx < 8) return;
        if (dy <= 0 || dx > dy || !atTop()) { on = false; return; }
        pull = true;
      }
      if (e.cancelable) e.preventDefault();
      d = Math.max(0, Math.min(120, dy * 0.5));
      show(d);
    }, { passive: false });
    function end(e) {
      if (!on) return;
      on = false;
      if (!pull) return;
      pull = false;
      if (e.type === "touchend" && d >= TH) {
        busy = true;
        if (navigator.vibrate) { try { navigator.vibrate(8); } catch (x) {} }
        hide();
        hardReload();
      } else hide();
    }
    sc.addEventListener("touchend", end, { passive: true });
    sc.addEventListener("touchcancel", end, { passive: true });
    /* вернулись на экран из истории (bfcache) — кружок не должен висеть */
    W.addEventListener("pageshow", function (e) { busy = false; hide(); if (e.persisted) { var b = D.getElementById("pgk-boot"); if (b) b.remove(); } });
  }

  /* ---------- анкета питомца (одна анкета — новые и правка) ---------- */
  function petSheet(pid) {
    var tpl = D.getElementById("pgk-tpl-pet"), s = kOpen("pet");
    if (!s || !tpl) return;
    var body = $("[data-k-pet-body]", s), tt = $("[data-k-sht]", s), pre = pid && K.pre ? K.pre[pid] : null, pet = pid ? petOf(pid) : null;
    if (tt) tt.textContent = pid ? T("Анкета питомца") + (pre ? " · " + pre.title : "") : T("Новый питомец");
    body.innerHTML = "";
    body.appendChild(D.importNode(tpl.content, true));
    var f = $("form.pgw", body);
    if (pid && f) {
      if (f.elements.pet_id) f.elements.pet_id.value = pid;
      Object.keys(pre || {}).forEach(function (k) {
        var el = f.elements[k === "title" ? "title" : "f_" + k];
        if (!el) return;
        if (el.length && !el.tagName) { for (var i = 0; i < el.length; i++) el[i].checked = el[i].value === pre[k]; }
        else el.value = pre[k];
      });
      if (pet && pet.av) { var ph = $(".pgw-ph", f); if (ph) { ph.style.backgroundImage = "url(" + pet.av + ")"; ph.parentNode.classList.add("has"); } }
      var gl = $(".pgw-gl", f); if (gl) gl.textContent = T("Сохранить");
      var okb = $(".pgw-ok b", f); if (okb) okb.textContent = T("Сохранено");
    }
    if (F().scan) F().scan(body);
    var sc = $(".pgk-sc", s); if (sc) sc.scrollTop = 0;
  }
  function postSheet(b) {
    var s = kOpen("post");
    if (!s) return;
    var t = $("template", b), box = $("[data-k-post-body]", s), tt = $("[data-k-sht]", s);
    box.innerHTML = ""; if (t) box.appendChild(D.importNode(t.content, true));
    if (tt) tt.textContent = T("Блог");
    var sc = $(".pgk-sc", s); if (sc) sc.scrollTop = 0;
  }
  function petOf(id) { return (K.pets || []).filter(function (p) { return p.id === id; })[0]; }
  function petPage(pid) {
    var tpl = D.getElementById("pgk-pp-" + pid), s = kOpen("petp");
    if (!s || !tpl) return;
    var body = $("[data-k-pp-body]", s), tt = $("[data-k-sht]", s), p = petOf(pid);
    body.innerHTML = ""; body.appendChild(D.importNode(tpl.content, true));
    if (tt) tt.textContent = p ? p.name : "";
    var sc = $(".pgk-sc", s); if (sc) sc.scrollTop = 0;
    /* 34.8: сразу — то, что было при загрузке; через мгновение — свежее состояние (няня уже вернула питомца и т. п.) */
    api("petpage", { pet: pid }).then(function (j) {
      if (!j.ok || !j.h || !s.classList.contains("on")) return;
      var y = sc ? sc.scrollTop : 0;
      body.innerHTML = j.h;
      if (sc) sc.scrollTop = y;
      var tp = D.getElementById("pgk-pp-" + pid); if (tp) tp.innerHTML = j.h;
    }).catch(function () {});
  }
  function avHtml(p, cls) { return '<span class="pgk-av' + (cls ? " " + cls : "") + '">' + (p && p.av ? '<img src="' + esc(p.av) + '" alt="">' : "<b>" + esc(((p && (p.name || "")) || "?").charAt(0).toUpperCase()) + "</b>") + "</span>"; }

  /* ============================================================
     ЗАКАЗ: питомец, услуга, день, время (колёса), длительность, няня
     ============================================================ */
  var BK = { pet: 0, kind: "walk", date: "", h: 10, m: 0, dur: 60, nanny: 0, busy: [] };
  var WD = ["вс", "пн", "вт", "ср", "чт", "пт", "сб"], MON = ["января", "февраля", "марта", "апреля", "мая", "июня", "июля", "августа", "сентября", "октября", "ноября", "декабря"];
  var ROW = 40, OPEN = 7 * 60, LAST = 21 * 60 + 55;
  function pad(n) { return (n < 10 ? "0" : "") + n; }
  function ymd(d) { return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()); }
  function almaty() { return new Date(Date.now() + (5 * 60 + new Date().getTimezoneOffset()) * 60000); }
  function earliest(date) {
    var n = almaty();
    if (date !== ymd(n)) return OPEN;
    return Math.max(OPEN, Math.ceil((n.getHours() * 60 + n.getMinutes() + 45) / 5) * 5);
  }
  function money(x) { return String(Math.round(x)).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " ₸"; }
  function durLabel(m) { if (m < 60) return m + " " + T("мин"); var h = m / 60; return String(h).replace(".", ",") + " " + T("ч"); }
  function bf() { return $("[data-k-bookform]"); }
  function bpart(k) { var f = bf(); return f && $('[data-b="' + k + '"]', f); }
  function seg(box, items, cur) {
    box.innerHTML = '<i class="pgk-sgi" aria-hidden="true"></i>' + items.map(function (x) { return '<button type="button" class="pg-b' + (String(x[0]) === String(cur) ? " on" : "") + '" data-v="' + esc(x[0]) + '">' + esc(x[1]) + "</button>"; }).join("");
    sgMove(box, true);
  }
  function bkPets() {
    var box = bpart("pet"); if (!box) return;
    box.innerHTML = (K.pets || []).map(function (p) { return '<button type="button" class="pg-b pgk-bk-pet' + (BK.pet === p.id ? " on" : "") + '" data-v="' + p.id + '">' + avHtml(p) + "<span>" + esc(p.name) + "</span></button>"; }).join("");
  }
  function bkDays() {
    var box = bpart("date"); if (!box) return;
    var now = almaty(), h = "", first = earliest(ymd(now)) > LAST ? 1 : 0;
    for (var i = first; i < first + 14; i++) {
      var d = new Date(now.getFullYear(), now.getMonth(), now.getDate() + i), v = ymd(d);
      h += '<button type="button" class="pg-b pgk-day' + (BK.date === v ? " on" : "") + '" data-v="' + v + '"><small>' + esc(i === 0 ? T("Сегодня") : T(WD[d.getDay()])) + "</small><b>" + d.getDate() + "</b></button>";
    }
    box.innerHTML = h;
    var on = $(".on", box); if (on) box.scrollLeft = Math.max(0, on.offsetLeft - 16);
  }
  /* 39.4: выгул — только для собак; у кошки — визит домой и неделя визитов */
  function bkKinds() {
    var p = petOf(BK.pet), cat = p && p.sp === "cat";
    var ks = Object.keys(K.svcs).filter(function (k) { return !(cat && k === "walk"); });
    if (ks.indexOf(BK.kind) < 0) BK.kind = ks[0];
    seg(bpart("kind"), ks.map(function (k) { return [k, K.svcs[k].t]; }), BK.kind);
  }
  /* 39.4: длительность — только у выгула; у визита и недели — что сделает няня (остальное — в комментарии) */
  function bkDurs() {
    var sv = K.svcs[BK.kind] || K.svcs.walk, f = bf(), dg = $('[data-bg="dur"]', f), tg = $('[data-bg="tasks"]', f);
    var walk = sv.durs && sv.durs.length;
    if (dg) dg.hidden = !walk;
    if (tg) tg.hidden = !!walk;
    if (walk) {
      if (sv.durs.indexOf(BK.dur) < 0) BK.dur = sv.dur;
      seg(bpart("dur"), sv.durs.map(function (x) { return [x, durLabel(x)]; }), BK.dur);
    } else {
      BK.dur = sv.dur;
      var p = petOf(BK.pet), list = ((K.tasks || {})[p && p.sp === "cat" ? "cat" : "dog"]) || [], box = $("[data-b-tasks]", f);
      if (box) box.innerHTML = '<ul class="pgk-tasks">' + list.map(function (x) { return "<li>" + ICON.check + "<span>" + esc(x) + "</span></li>"; }).join("") + "</ul><p>" + esc(T("Другие просьбы — например, полить цветы или дать лекарство — напишите в комментарии.")) + "</p>";
    }
    var ta = f && f.elements.note;
    if (ta) ta.placeholder = walk ? T("Пожелания к прогулке, как попасть и т. п.") : T("Другие просьбы: полить цветы, дать лекарство и т. п.");
  }
  /* 39.4: адрес — из анкеты питомца или профиля, пока клиент сам его не изменил */
  function bkAddr() {
    var f = bf(), inp = f && f.elements.addr; if (!inp || inp._touched) return;
    var p = petOf(BK.pet);
    inp.value = (p && p.addr) || K.addr || "";
  }
  function bkNannies() {
    var box = bpart("nanny"); if (!box) return;
    var list = K.nannies || [];
    if (!list.length) { box.innerHTML = '<div class="pgk-row"><span class="pgk-nany">' + ICON.users + '</span><span class="pgk-row-t"><small>' + esc(T("Нет нянь в списке — подберёт менеджер")) + "</small></span></div>"; return; }
    var h = '<button type="button" class="pg-b pgk-row pgk-nrow' + (!BK.nanny ? " on" : "") + '" data-v="0"><span class="pgk-nany">' + ICON.users + '</span><span class="pgk-row-t"><b>' + esc(T("Любая свободная")) + "</b></span>" + ICON.check + "</button>";
    list.forEach(function (n) {
      var busy = BK.busy.indexOf(n.id) > -1;
      h += '<button type="button" class="pg-b pgk-row pgk-nrow' + (BK.nanny === n.id ? " on" : "") + (busy ? " busy" : "") + '" data-v="' + n.id + '"' + (busy ? ' aria-disabled="true"' : "") + ">" + avHtml({ av: n.img, name: n.name }) + '<span class="pgk-row-t"><b>' + esc(n.name) + "</b><small>" + (busy ? esc(T("Занята в это время")) : (n.rate ? svg(STAR) + " " + esc(n.rate) + " · " : "") + esc(T("Свободна"))) + "</small></span>" + ICON.check + "</button>";
    });
    box.innerHTML = h;
  }
  /* 39.4: цена заказа: обычная, скидка первого заказа (час выгула / первый день недели / визит) и итог */
  function bkPrice() {
    var p = petOf(BK.pet), pr = K.price || {}, cat = p && p.sp === "cat", old = 0, cut = 0;
    if (BK.kind === "walk") { old = Math.round((pr.walk || 0) * BK.dur / 60); cut = pr.walk_cut || 0; }
    else if (BK.kind === "week") { old = (cat ? pr.week_cat : pr.week_dog) || 0; cut = (cat ? pr.week_cat_cut : pr.week_dog_cut) || 0; }
    else { old = pr.visit || 0; cut = pr.visit_cut || 0; }
    cut = Math.min(cut, old);
    return { old: old, cut: cut, total: old - cut };
  }
  /* первый заказ со скидкой 100% — у нянь «1-й час бесплатно» */
  function firstFree() { var pr = K.price || {}; return !!(pr.first && pr.walk && pr.walk_cut >= pr.walk); }
  function freeTag() { return firstFree() ? T("первый час") : ""; }
  function discNote() {
    var pc = bkPrice(), pr = K.price || {}, full = (pr.off || 0) >= 100;
    if (!pc.cut) return "";
    if (BK.kind === "week") return T(full ? "Первый день — бесплатно" : "Скидка на первый день") + " (−" + money(pc.cut) + "), " + T("остальные 6 дней — по обычной цене.");
    if (BK.kind === "walk") return T(full ? "Первый час — бесплатно" : "Скидка на первый час") + " (−" + money(pc.cut) + ")" + (pc.total > 0 ? ", " + T("остальное время — по обычной цене.") : ".");
    return T(full ? "Визит — бесплатно" : "Скидка на визит") + " (−" + money(pc.cut) + ").";
  }
  function bkSum() {
    var box = bpart("sum"); if (!box) return;
    var price = bkPrice(), sv = K.svcs[BK.kind] || {};
    var d = BK.date ? new Date(BK.date + "T00:00:00") : null, st = BK.h * 60 + BK.m, en = st + BK.dur;
    var when = d ? T(WD[d.getDay()]).replace(/^./, function (c) { return c.toUpperCase(); }) + ", " + d.getDate() + " " + T(MON[d.getMonth()]) : "";
    /* 39.4: у визита и недели — только время прихода няни */
    when += " · " + pad(BK.h) + ":" + pad(BK.m) + (sv.durs && sv.durs.length ? "–" + pad(Math.floor(en / 60) % 24) + ":" + pad(en % 60) : "");
    if (sv.days > 1) when += " · " + sv.days + " " + T("дней");
    /* 39.4: первый заказ — скидка на первый час / первый день / визит: зачёркнутая цена и что именно бесплатно */
    var f = bf(), nt = f && $(".pgk-tw-note", f), dn = discNote();
    if (dn && !nt) { nt = D.createElement("div"); nt.className = "pgk-tw-note"; f.insertBefore(nt, f.firstChild); }
    if (nt) { if (dn) nt.innerHTML = "<b>" + esc(T("Первый заказ")) + "</b><span>" + esc(dn) + "</span>"; else nt.remove(); }
    var tot = price.total, has = price.old > 0;
    box.innerHTML = "<b>" + esc(when) + "</b>" + (has ? "<strong>" + (price.cut ? "<s>" + money(price.old) + "</s> " : "") + (tot ? "≈ " + money(tot) : "0 ₸") + "</strong>" : "<span>" + esc(T("Цену подтвердит менеджер")) + "</span>");
    /* 38.1: сумма и на кнопке — «Отправить заявку · 3 000 ₸», как в макете */
    var pe = bpart("price"); if (pe) pe.textContent = has ? " · " + (tot ? "≈ " + money(tot) : "0 ₸") : "";
  }
  /* колёса времени: прокручиваются пальцем, колесом мыши, стрелками; выбранное — в середине */
  function wheelBuild() {
    var hc = $('[data-w="h"]', bf()), mc = $('[data-w="m"]', bf()), h = "", m = "";
    if (!hc || hc._b) return;
    for (var i = 7; i <= 21; i++) h += '<b data-v="' + i + '">' + pad(i) + "</b>";
    for (var j = 0; j < 60; j += 5) m += '<b data-v="' + j + '">' + pad(j) + "</b>";
    hc.innerHTML = h; mc.innerHTML = m; hc._b = 1;
    [hc, mc].forEach(function (col) {
      col.addEventListener("scroll", function () {
        var idx = Math.round(col.scrollTop / ROW), items = col.children;
        if (col._idx !== idx && items[idx]) { col._idx = idx; qa("b.on", col).forEach(function (x) { x.classList.remove("on"); }); items[idx].classList.add("on"); }
        if (col._lock) return;
        clearTimeout(col._t);
        col._t = setTimeout(function () { wheelCommit(col); }, 130);
      }, { passive: true });
      col.addEventListener("click", function (e) { var b = e.target.closest("b"); if (b && !b.classList.contains("off")) wheelTo(col, +b.getAttribute("data-v"), true); });
      col.addEventListener("keydown", function (e) {
        if (e.key !== "ArrowUp" && e.key !== "ArrowDown") return;
        e.preventDefault();
        var items = qa("b", col), idx = Math.round(col.scrollTop / ROW) + (e.key === "ArrowDown" ? 1 : -1);
        if (items[idx]) wheelTo(col, +items[idx].getAttribute("data-v"), true);
      });
    });
  }
  function wheelTo(col, v, smooth) {
    var items = qa("b", col), idx = 0;
    items.forEach(function (b, i) { if (+b.getAttribute("data-v") === v) idx = i; });
    col._lock = true; clearTimeout(col._t); clearTimeout(col._lt);
    col.scrollTo({ top: idx * ROW, behavior: smooth && !reduce ? "smooth" : "auto" });
    col._idx = idx; qa("b.on", col).forEach(function (x) { x.classList.remove("on"); }); if (items[idx]) items[idx].classList.add("on");
    col._lt = setTimeout(function () { col._lock = false; if (smooth) wheelCommit(col); }, smooth && !reduce ? 420 : 30);
  }
  function wheelCommit(col) {
    var items = qa("b", col), idx = Math.max(0, Math.min(items.length - 1, Math.round(col.scrollTop / ROW)));
    var v = +items[idx].getAttribute("data-v"), was = BK.h * 60 + BK.m;
    if (col.getAttribute("data-w") === "h") BK.h = v; else BK.m = v;
    bkTimeFix(true);
    if (BK.h * 60 + BK.m !== was) { bkSum(); bkBusy(); }
  }
  /* время раньше допустимого (сегодня уже прошло) — колёса сами докручиваются до ближайшего */
  function bkTimeFix(smooth) {
    var min = earliest(BK.date), t = BK.h * 60 + BK.m;
    if (t < min) { t = min; } if (t > LAST) t = LAST;
    var hc = $('[data-w="h"]', bf()), mc = $('[data-w="m"]', bf());
    if (!hc) return;
    var nh = Math.floor(t / 60), nm = t % 60, ch = nh !== BK.h, cm = nm !== BK.m;
    BK.h = nh; BK.m = nm;
    qa("b", hc).forEach(function (b) { b.classList.toggle("off", +b.getAttribute("data-v") * 60 + 55 < min); });
    qa("b", mc).forEach(function (b) { b.classList.toggle("off", BK.h * 60 + +b.getAttribute("data-v") < min); });
    if (ch || smooth === "force") wheelTo(hc, BK.h, smooth === true);
    if (cm || smooth === "force") wheelTo(mc, BK.m, smooth === true);
  }
  var busyQ = 0;
  function bkBusy() {
    if (!BK.date) return;
    var q = ++busyQ;
    api("slots", { kind: BK.kind, date: BK.date, time: pad(BK.h) + ":" + pad(BK.m), dur: BK.dur }).then(function (j) {
      if (q !== busyQ || !j.ok) return;
      BK.busy = j.busy || [];
      if (BK.busy.indexOf(BK.nanny) > -1) BK.nanny = 0;
      bkNannies();
    }).catch(function () {});
  }
  function book(kind, pid) {
    var s = kOpen("book"); if (!s) return;
    var f = bf(), dn = $(".pgk-done", s);
    f.hidden = false; dn.hidden = true; formErr(f, "");
    BK.kind = K.svcs[kind] ? kind : "walk";
    BK.pet = pid || (BK.pet && petOf(BK.pet) ? BK.pet : (K.pets && K.pets.length ? K.pets[0].id : 0));
    BK.busy = []; BK.nanny = 0;
    var now = almaty();
    if (!BK.date || BK.date < ymd(now)) BK.date = earliest(ymd(now)) > LAST ? ymd(new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1)) : ymd(now);
    if (!BK.touched) { BK.h = 10; BK.m = 0; }
    var sc = $(".pgk-sc", s); if (sc) sc.scrollTop = 0;
    bkPets(); bkKinds();
    bkDays(); bkDurs(); bkNannies(); bkAddr();
    if (f.elements.addr && !f.elements.addr._w) { f.elements.addr._w = 1; f.elements.addr.addEventListener("input", function () { this._touched = true; }); }
    wheelBuild();
    /* лист уже на экране — ставим колёса без анимации */
    requestAnimationFrame(function () { bkTimeFix("force"); bkSum(); sgAll(f); });
    bkBusy();
  }
  D.addEventListener("click", function (e) {
    var c = e.target.closest && e.target.closest("[data-k-bookform] [data-b] .pg-b");
    if (!c || c.disabled || c.getAttribute("aria-disabled") === "true") return;
    var box = c.closest("[data-b]"), b = box.getAttribute("data-b"), v = c.getAttribute("data-v");
    qa(".pg-b.on", box).forEach(function (x) { x.classList.remove("on"); });
    c.classList.add("on");
    if (box.classList.contains("pgk-sg")) sgMove(box);
    if (b === "pet") { BK.pet = +v; bkKinds(); bkDurs(); bkAddr(); bkSum(); sgAll(bf()); return; }
    if (b === "nanny") { BK.nanny = +v; return; }
    if (b === "kind") { BK.kind = v; bkDurs(); }
    if (b === "dur") BK.dur = +v;
    if (b === "date") { BK.date = v; bkTimeFix(true); }
    BK.touched = true;
    bkSum(); bkBusy();
  });
  D.addEventListener("submit", function (e) {
    var f = e.target;
    if (!f.matches || !f.matches("[data-k-bookform]") || !K) return;
    e.preventDefault(); kbHide();
    if (!BK.pet) { formErr(f, T("Выберите питомца."), "", bpart("pet")); return; }
    if (!BK.date) { formErr(f, T("Выберите день и время."), "", bpart("date")); return; }
    var ad = (f.elements.addr ? f.elements.addr.value : "").trim();
    if (ad.length < 5) { formErr(f, T("Укажите адрес: улица, дом, подъезд, квартира."), "", $("[data-b-addr]", f)); if (f.elements.addr) f.elements.addr.focus(); return; }
    formErr(f, ""); busyBtn(f, true);
    api("book", { pet: BK.pet, kind: BK.kind, date: BK.date, time: pad(BK.h) + ":" + pad(BK.m), dur: BK.dur, nanny: BK.nanny, note: f.elements.note.value, addr: ad, pg_src: W.PGSrc ? W.PGSrc() : "" }).then(function (j) { busyEnd(f, function () {
      if (j.ok && W.PGEvent) W.PGEvent("booking", { kind: BK.kind, value: bkPrice().total });
      if (!j.ok) { formErr(f, j.msg || "", "", j.field === "addr" ? $("[data-b-addr]", f) : j.busy ? bpart("nanny") : $('[data-b="time"]', f)); if (j.busy) bkBusy(); return; }
      var pe = petOf(BK.pet); if (pe) pe.addr = ad; K.addr = ad;
      f.hidden = true; $(".pgk-done", f.parentNode).hidden = false; f.reset(); BK.touched = false; if (f.elements.addr) f.elements.addr._touched = false;
      var sc = f.closest(".pgk-sc"); if (sc) sc.scrollTop = 0;
      CL.booked = true;
      ctAfterBook(f);
    }); }).catch(netErr(f));
  });

  /* ============================================================
     ЧАТ: лента всегда прижата вниз (column-reverse), новые сообщения появляются плавно
     ============================================================ */
  function msgsBox() { return $("[data-k-msgs]"); }
  function msgsIn() { return $("[data-k-msgs-in]"); }
  function nearBottom() { var b = msgsBox(); return !b || b.scrollHeight - b.scrollTop - b.clientHeight < 80; }
  /* 34.7: лента всегда тянется вниз — клавиатура, новые сообщения, «печатает…» не уводят её наверх */
  function toBottom(smooth) {
    var b = msgsBox(); if (!b) return;
    CL.stick = true; CL.pinUntil = Date.now() + 450;
    if (smooth && !reduce) b.scrollTo({ top: b.scrollHeight, behavior: "smooth" }); else b.scrollTop = b.scrollHeight;
  }
  function pinSetup() {
    var b = msgsBox(), inn = msgsIn();
    if (!b || b._pin) return;
    b._pin = 1; CL.stick = true;
    b.addEventListener("scroll", function () { if (Date.now() > (CL.pinUntil || 0)) CL.stick = nearBottom(); }, { passive: true });
    var pin = function () { if (CL.stick) b.scrollTop = b.scrollHeight; };
    if (W.ResizeObserver) { var ro = new ResizeObserver(pin); ro.observe(b); if (inn) ro.observe(inn); }
    W.addEventListener("resize", pin);
    W.addEventListener("pgkb", function () { var t0 = Date.now(); (function f() { pin(); if (Date.now() - t0 < 420) requestAnimationFrame(f); })(); });
  }
  function msgHtml(m, anim, i) {
    var cls = anim ? " new" : "", st = anim && i ? ' style="animation-delay:' + Math.min(i, 5) * 40 + 'ms"' : "";
    if (m.k === "sys" || m.k === "rv" || m.k === "loc" || m.k === "note" || m.k === "mgr") {
      var act = "";
      if (m.k === "rv" && m.s) act = m.rv ? '<span class="pgk-myrv">' + stars(m.rv) + "<small>" + esc(T("Спасибо за оценку")) + "</small></span>" : '<button type="button" class="pg-b pgk-btn pgk-btn-p pgk-btn-sm" data-k-review="' + m.s + '">' + svg(STAR) + esc(T("Оценить няню")) + "</button>";
      if (m.k === "loc" && m.pid) act = '<button type="button" class="pg-b pgk-btn pgk-btn-s pgk-btn-sm" data-k-tab="map" data-k-focus="' + m.pid + '">' + svg('<path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>') + esc(T("Где сейчас")) + "</button>";
      return '<div class="pgk-m pgk-m-pg' + cls + '" data-id="' + m.id + '"' + st + '><span class="pgk-m-av pgk-m-logo">' + LOGO + '</span><div class="pgk-m-b"><small>petgo.kz</small>' + (m.h ? "<p>" + m.h + "</p>" : "") + act + "<time>" + esc(m.tm) + "</time></div></div>";
    }
    return '<div class="pgk-m' + (m.me ? " me" : "") + cls + '" data-id="' + m.id + '"' + st + ">" + (!m.me ? '<span class="pgk-m-av">' + (m.av ? '<img src="' + esc(m.av) + '" alt="">' : "<b>" + esc((m.n || "P").charAt(0)) + "</b>") + "</span>" : "") +
      '<div class="pgk-m-b">' + (!m.me && m.n ? "<small>" + esc(m.n) + "</small>" : "") + (m.p ? '<button type="button" class="pg-b pgk-m-ph" data-k-photo="' + esc(m.p) + '"><img src="' + esc(m.p) + '" alt="" loading="lazy"></button>' : "") + (m.h ? "<p>" + m.h + "</p>" : "") + "<time>" + esc(m.tm) + "</time></div></div>";
  }
  function stars(n) { var st = ""; for (var i = 1; i <= 5; i++) st += i <= n ? ICON.starOn : ICON.star; return st; }
  function dayLabel(dl) {
    var n = almaty(), t = pad(n.getDate()) + "." + pad(n.getMonth() + 1) + "." + n.getFullYear();
    var y = new Date(n.getFullYear(), n.getMonth(), n.getDate() - 1), ys = pad(y.getDate()) + "." + pad(y.getMonth() + 1) + "." + y.getFullYear();
    if (dl === t) return T("Сегодня");
    if (dl === ys) return T("Вчера");
    var p = dl.split(".");
    return p.length === 3 ? +p[0] + " " + T(MON[+p[1] - 1]) + (+p[2] !== n.getFullYear() ? " " + p[2] : "") : dl;
  }
  var loading = null;
  function loadMsgs() {
    var inn = msgsIn();
    if (!inn) return Promise.resolve();
    if (loading) return loading;
    var first = !CL.lastMsg;
    loading = api("msgs", { after: CL.lastMsg }).then(function (j) {
      loading = null;
      if (!j.ok || !j.msgs || !j.msgs.length) return;
      var stick = first || CL.stick !== false || nearBottom(), html = "", k = 0;
      j.msgs.forEach(function (m) {
        if (m.id <= CL.lastMsg || inn.querySelector('[data-id="' + m.id + '"]')) return;
        CL.lastMsg = Math.max(CL.lastMsg, m.id);
        /* своё сообщение уже на экране (отправлено только что) — просто «подтверждаем» его без мигания */
        var pend = m.me && inn.querySelector(".pgk-m.sending");
        if (pend) { pend.classList.remove("sending"); pend.setAttribute("data-id", m.id); var tm = $("time", pend); if (tm) tm.textContent = m.tm; return; }
        if (m.dl !== CL.day) { CL.day = m.dl; html += '<div class="pgk-day-l' + (first ? "" : " new") + '">' + esc(dayLabel(m.dl)) + "</div>"; }
        html += msgHtml(m, !first, k++);
      });
      if (html) {
        var ty = $(".pgk-typing", inn);
        if (ty) ty.insertAdjacentHTML("beforebegin", html); else inn.insertAdjacentHTML("beforeend", html);
      }
      if (stick) toBottom(!first);
      unread(0);
    }).catch(function () { loading = null; });
    return loading;
  }
  function chat() {
    var s = kOpen("chat");
    if (!s) return;
    CL.chatOpen = true;
    pinSetup();
    toBottom(false);
    loadMsgs();
    clearInterval(CL.chatT);
    CL.chatT = setInterval(function () { if (!D.hidden && CL.chatOpen) loadMsgs(); }, 4000);
  }
  function sendMsg(f) {
    var ta = f.elements.body, inp = f.elements.photo, file = inp && inp.files && inp.files[0], body = ta.value.trim();
    if (!body && !file) return;
    var btn = $(".pgk-send", f), inn = msgsIn();
    btn.disabled = true;
    /* своё сообщение видно сразу; помощник «печатает» */
    var mine = null;
    if (inn) {
      var n = almaty();
      inn.insertAdjacentHTML("beforeend", msgHtml({ id: 0, me: true, h: esc(body).replace(/\n/g, "<br>"), tm: pad(n.getHours()) + ":" + pad(n.getMinutes()), p: file ? URL.createObjectURL(file) : "" }, true));
      mine = inn.lastElementChild; mine.classList.add("sending");
      toBottom(true);
    }
    var typing = null, tyT = 0;
    if (K.ai && body && inn) {
      tyT = setTimeout(function () {
        typing = D.createElement("div"); typing.className = "pgk-typing";
        typing.innerHTML = "<i></i><i></i><i></i><span>" + esc(T("Помощник PetGo")) + " " + esc(T("печатает…")) + "</span>";
        inn.appendChild(typing); if (nearBottom()) toBottom(true);
      }, 600);
    }
    ta.value = ""; ta.style.height = "";
    function done() { clearTimeout(tyT); if (typing) { typing.remove(); typing = null; } btn.disabled = false; }
    /* 34.6: помощник отвечает уже после «отправлено» — ждём его сообщение частым опросом (до 45 с) */
    function waitAi(from) {
      var t0 = Date.now();
      (function tick() {
        loadMsgs().then(function () {
          var got = qa(".pgk-m:not(.me)", inn).some(function (x) { return +x.getAttribute("data-id") > from; });
          if (got || Date.now() - t0 > 45000 || !CL.chatOpen) { done(); return; }
          setTimeout(tick, 1500);
        });
      })();
    }
    (file ? shrink(file, 1600) : Promise.resolve(null)).then(function (b) {
      return api("send", { body: body }, b ? { photo: b } : null);
    }).then(function (j) {
      if (!j.ok) { done(); if (mine) mine.remove(); ta.value = body; toast(j.msg || T("Не получилось — проверьте интернет."), true); return; }
      if (inp) inp.value = ""; f.classList.remove("has-ph");
      if (j.ai === "wait") { btn.disabled = false; return loadMsgs().then(function () { waitAi(j.id || CL.lastMsg); }); }
      return loadMsgs().then(done);
    }).catch(function () {
      /* связь оборвалась: сообщение могло уже сохраниться — проверяем, прежде чем предлагать отправить снова */
      loadMsgs().then(function () {
        done();
        if (mine && !mine.classList.contains("sending")) return;
        if (mine) mine.remove();
        ta.value = body; toast(T("Не получилось — проверьте интернет."), true);
      });
    });
  }
  function unread(n) { qa("[data-k-unread]").forEach(function (b) { b.textContent = n; b.hidden = !n; }); }

  /* ============================================================
     34.7 ЗАКАЗ И QR-КОД ДЛЯ НЯНИ: появляется, когда нажимаешь на заказ; код действует 24 часа
     ============================================================ */
  function qrLib() { return W.qrcode ? Promise.resolve() : new Promise(function (ok, no) { var s = D.createElement("script"); s.src = K.qrjs; s.onload = ok; s.onerror = no; D.head.appendChild(s); }); }
  function qrSvg(text) {
    var q = W.qrcode(0, "M"); q.addData(text); q.make();
    var n = q.getModuleCount(), m = 3, sz = n + m * 2, d = "";
    for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (q.isDark(r, c)) d += "M" + (c + m) + " " + (r + m) + "h1v1h-1z";
    return '<svg viewBox="0 0 ' + sz + " " + sz + '" shape-rendering="crispEdges" role="img" aria-label="QR"><rect width="' + sz + '" height="' + sz + '" fill="#fff"/><path d="' + d + '" fill="#0F2B27"/></svg>';
  }
  /* 34.9: галочка как на сайте после отправки формы — круг появляется, галочка дорисовывается */
  function okSvg() { return '<svg class="pgk-ok-svg" viewBox="0 0 80 80" aria-hidden="true"><circle cx="40" cy="40" r="36"/><path d="M24 41.5l11 11L57 29"/></svg>'; }
  /* что случилось с заказом: «back» — вернули (визит закончен), «pick» — забрали (няня пришла), "" — ещё ничего */
  function ordDone(o) {
    if (o.back || o.st === "done" || o.st === "confirmed") return "back";
    if (o.by || (o.walk && o.st === "picked")) return "pick";
    return "";
  }
  function ordOk(o, k, live) {
    var t, sub = [], h;
    if (k === "back") {
      t = o.walk ? T("%s дома").replace("%s", o.pet) : T("Визит завершён");
      if (o.back_at) sub.push((o.walk ? T("Вернули в") : T("Няня ушла в")) + " " + o.back_at);
      sub.push(T(o.back === "noqr" ? "ждёт подтверждения администратора" : (o.back === "qr" ? "подтверждено QR-кодом" : (o.st === "confirmed" ? "подтверждено" : "ждёт подтверждения администратора"))));
    } else {
      t = o.walk ? T("Прогулка началась") : T("Няня пришла");
      sub.push(o.walk ? T("%s и няня %s").replace("%s", o.pet).replace("%s", o.nn || "") : (o.nn || ""));
      if (o.hand_at) sub.push(o.hand_at);
      sub.push(T(o.by === "noqr" ? "без QR — место отмечено на карте" : "подтверждено QR-кодом"));
    }
    h = '<div class="pgk-okv' + (live ? " in" : "") + '" role="status">' + okSvg() + "<b>" + esc(t) + "</b><p>" + esc(sub.filter(Boolean).join(" · ")) + "</p>" +
      /* забрали — QR для возврата уже новый, его можно открыть отсюда */
      (k === "pick" && o.qr ? '<button type="button" class="pg-b pgk-btn pgk-okqr" data-k-order="' + o.id + '">' + ICON.qr + esc(T(o.walk ? "QR-код для возврата" : "QR-код, когда няня уходит")) + "</button>" : "") + "</div>";
    if (k === "back" && (o.rep || o.rph)) {
      h += '<div class="pgk-okrep' + (live ? " in" : "") + '"><b>' + esc(T("Отчёт няни")) + "</b>" + (o.rep || "") + (o.rph ? '<button type="button" class="pgk-okph" data-k-photo="' + esc(o.rph) + '"><img src="' + esc(o.rph) + '" alt="" decoding="async"></button>' : "") + "</div>";
    }
    return h;
  }
  function ordStop() { clearTimeout(CL.ordT); CL.ordT = 0; CL.ord = null; }
  function orderSheet(id, auto) {
    var s = kOpen("order"); if (!s) return;
    var box = $("[data-k-order-body]", s);
    ordStop();
    CL.ordId = id;
    box.classList.toggle("auto", !!auto);
    box.innerHTML = '<div class="pgk-map-load"><i class="pg-spin"></i></div>';
    function render(o, live) {
      var tt = $("[data-k-sht]", s); if (tt) tt.textContent = o.pet + " · " + o.svc;
      var head = '<div class="pgk-ord-h">' + avHtml({ av: o.av, name: o.pet }, "md") + '<div><b>' + esc(o.when) + "</b><small>" + esc(o.dur + " " + T("мин")) + '</small></div><span class="pgk-badge st-' + esc(o.st) + '">' + esc(o.stl) + "</span></div>";
      var h = head;
      var k = ordDone(o);
      if (live) {
        /* няня только что отсканировала: вместо QR — галочка (и отчёт, если вернули) */
        h += ordOk(o, live, true);
      } else if (o.qr && W.qrcode) {
        /* 36.0: QR — первым и крупно; одна короткая подсказка; подробности заказа ниже */
        var when = { pick: "Когда няня забирает питомца", back: "Когда няня возвращает питомца", arrive: "Когда няня приходит", leave: "Когда няня уходит" }[o.phase] || "";
        var away = { pick: "Нет дома? Няня отметит без QR — вы увидите её на карте", arrive: "Нет дома? Няня отметит без QR — вы увидите её на карте", back: "Нет дома? Няня завершит без QR, заказ подтвердит менеджер", leave: "Нет дома? Няня завершит без QR, заказ подтвердит менеджер" }[o.phase] || "";
        h = '<div class="pgk-qrhead"><b>' + esc(T("Покажите QR-код няне")) + "</b>" + (when ? "<small>" + esc(T(when)) + "</small>" : "") + "</div>";
        h += '<div class="pgk-qr"><div class="pgk-qr-img">' + qrSvg(o.qr.url) + '</div><b class="pgk-qr-code">' + esc(o.qr.code) + "</b><small>" + esc(T("Камера не сработала — няня введёт эти цифры")) + "</small></div>";
        if (away) h += '<p class="pgk-qr-tip">' + esc(T(away)) + "</p>";
        h += head;
      } else if (k === "back") {
        h += ordOk(o, "back", false);
      } else if (o.by) {
        h += '<p class="pgk-qr-tip">' + esc(T(o.by === "qr" ? "Передача подтверждена QR-кодом" : "Без QR — место отмечено на карте")) + "</p>";
      }
      h += '<div class="pgk-list"><div class="pgk-row pgk-nrow">' + (o.nn ? avHtml({ av: o.nav, name: o.nn }) + '<span class="pgk-row-t"><b>' + esc(o.nn) + "</b><small>" + esc(T("Няня")) + "</small></span>" : '<span class="pgk-nany">' + ICON.users + '</span><span class="pgk-row-t"><b>' + esc(T("Няня ещё не назначена")) + "</b></span>") + "</div></div>";
      if (o.st === "arrived" || o.st === "picked") h += '<button type="button" class="pg-b pgk-btn pgk-btn-p pgk-go" data-k-tab="map" data-k-focus="' + o.pid + '">' + esc(T("Где сейчас")) + "</button>";
      box.innerHTML = h;
    }
    /* пока на экране QR — раз в 2 секунды спрашиваем, не отсканировала ли няня */
    function watch(o0) {
      var was = ordDone(o0), my = CL.ord = { id: id };
      function poll() {
        if (CL.ord !== my) return;
        if (!s.classList.contains("on")) { ordStop(); return; }
        if (D.hidden) { CL.ordT = setTimeout(poll, 2000); return; }
        api("order", { order: id }).then(function (o) {
          if (CL.ord !== my || !o || !o.ok) return;
          var now = ordDone(o);
          if (now && now !== was) {
            var q = $(".pgk-qr", box), tp = $(".pgk-qr-tip", box);
            var show = function () { if (CL.ord === my) { render(o, now); if (now === "back" || !o.qr) ordStop(); else { was = now; o0 = o; } refresh(); } };
            if (q && !reduce) { q.classList.add("out"); if (tp) tp.classList.add("out"); setTimeout(show, 160); } else show();
            if (now === "back" || !o.qr) return;
          }
        }).catch(function () {}).then(function () { if (CL.ord === my) CL.ordT = setTimeout(poll, 2000); });
      }
      CL.ordT = setTimeout(poll, 2000);
    }
    Promise.all([api("order", { order: id }), qrLib().catch(function () {})]).then(function (r) {
      var o = r[0];
      if (!o || !o.ok) { box.innerHTML = '<p class="pgk-note">' + esc((o && o.msg) || T("Не получилось — проверьте интернет.")) + "</p>"; return; }
      render(o, "");
      if (o.qr) watch(o);
    }).catch(function () { box.innerHTML = '<p class="pgk-note">' + esc(T("Не получилось — проверьте интернет.")) + "</p>"; });
  }

  /* ---------- живое состояние питомцев ---------- */
  function applyPets(pets) {
    if (!pets) return;
    (K.pets || []).forEach(function (o) { pets.forEach(function (p) { if (p.id === o.id) { if (!p.home && o.home) p.home = o.home; if (o._geo) p._geo = 1; } }); });
    K.pets = pets;
    var lv = null;
    pets.forEach(function (p) {
      var txt = (p.live && p.hi ? p.hi + " " : "") + p.text + (p.live && p.min ? " · " + p.min + " " + T("мин") : "");
      qa('[data-k-st="' + p.id + '"]').forEach(function (el) {
        var cls = "pgk-st st-" + p.st;
        if (el.className !== cls) el.className = cls;
        var sp = $("span", el); if (sp && sp.textContent !== txt) sp.textContent = txt;
      });
      var row = $('[data-k-petpage="' + p.id + '"] .pgk-pet-av'); if (row) row.classList.toggle("live", !!p.live);
      if (p.live && !lv) lv = p;
    });
    var bar = $(".pgk-live");
    if (bar) {
      bar.hidden = !lv;
      if (lv) {
        $("[data-k-live-name]", bar).textContent = lv.name;
        $("[data-k-live-text]", bar).textContent = lv.text + (lv.min ? " · " + lv.min + " " + T("мин") : "");
        var a = $(".pgk-live-av", bar); if (a && a._p !== lv.id) { a._p = lv.id; a.innerHTML = avHtml(lv); }
      }
    }
    if (CL.tab === "map") MapK.now();
  }
  /* 36.0: няня у двери — QR-код открывается сам, на весь лист, один раз на каждый приход и возврат */
  function doorSeen(k) {
    try { return (JSON.parse(sessionStorage.getItem("pgk_door") || "[]")).indexOf(k) > -1; } catch (e) { return !!(CL.doorSeen || {})[k]; }
  }
  function doorMark(k) {
    CL.doorSeen = CL.doorSeen || {}; CL.doorSeen[k] = 1;
    try { var a = JSON.parse(sessionStorage.getItem("pgk_door") || "[]"); a.push(k); sessionStorage.setItem("pgk_door", JSON.stringify(a.slice(-30))); } catch (e) {}
  }
  function doorCheck(list) {
    if (!K || !list || !list.length || !D.getElementById("pgk")) return;
    var d = list[0];
    list.forEach(function (x) { if (x.t > d.t) d = x; });
    var k = d.id + ":" + d.ph + ":" + d.t;
    if (doorSeen(k)) return;
    doorMark(k);
    var os = D.getElementById("pgk-order");
    if (!(os && os.classList.contains("on") && CL.ordId === d.id)) { if (stack.length) kClose(true); }
    orderSheet(d.id, true);
    if (navigator.vibrate) try { navigator.vibrate([90, 60, 90]); } catch (x) {}
  }
  function live() {
    clearTimeout(CL.timer);
    if (!K || !D.getElementById("pgk")) return;
    var anyLive = (K.pets || []).some(function (p) { return p.live; });
    var wait = CL.tab === "map" ? (anyLive ? 8000 : 30000) : (anyLive ? 15000 : 45000);
    /* идёт заказ (няня едет, пришла, гуляют) — проверяем каждые 5 секунд, чтобы QR открылся сразу */
    if (CL.soon) wait = Math.min(wait, 5000);
    CL.timer = setTimeout(function () {
      if (D.hidden) { live(); return; }
      refresh().then(live);
    }, wait);
  }
  /* 34.6: статусы питомцев, «Ближайшие» и прогулки — свежие, даже если кабинет долго был открыт */
  var refreshing = null;
  function refresh() {
    if (!K) return Promise.resolve();
    if (refreshing) return refreshing;
    refreshing = api("live").then(function (j) {
      if (!j.ok) return;
      applyPets(j.pets); unread(CL.chatOpen ? 0 : (j.u || 0));
      if (j.nl) { K.nlast = j.nl; bell(); }
      CL.soon = !!j.soon;
      var nw = $("[data-k-now]");
      if (nw && typeof j.now === "string" && nw._h !== j.now && nw.innerHTML !== j.now) { nw._h = j.now; nw.innerHTML = j.now; }
      doorCheck(j.door);
      var up = $("[data-k-up]");
      if (up && typeof j.up === "string" && up._h !== j.up && up.innerHTML !== j.up) { up._h = j.up; up.innerHTML = j.up; }
      var wk = $("[data-k-walks]");
      if (wk && j.wk && wk.getAttribute("data-sig") !== j.wk) {
        return api("walks").then(function (w) { if (w.ok) { wk.innerHTML = w.h; wk.setAttribute("data-sig", w.wk); } });
      }
    }).catch(function () {}).then(function () { refreshing = null; });
    return refreshing;
  }

  /* ---------- отзыв ---------- */
  function reviewOpen(oid, name, av, auto) {
    var s = kOpen("review"); if (!s) return;
    var f = $("form", s);
    f.elements.order.value = oid;
    CL.rvAuto = !!auto;
    var fr = $("[data-k-rv-for]", s);
    if (fr) fr.innerHTML = name ? avHtml({ av: av, name: name }, "sm") + "<b>" + esc(T("Отзыв для няни %s").replace("%s", name)) + "</b>" : "";
  }

  /* ---------- фото на весь экран ---------- */
  function lightbox(src, cap) {
    var lb = $("[data-k-lb]");
    if (!lb) return;
    $("img", lb).src = src; $("span", lb).textContent = cap || "";
    lb.hidden = false; void lb.offsetWidth; lb.classList.add("on");
  }
  function lbClose() { var lb = $(".pgk-lb.on"); if (lb) { lb.classList.remove("on"); setTimeout(function () { if (!lb.classList.contains("on")) lb.hidden = true; }, reduce ? 0 : 170); } }

  /* ============================================================
     КАРТА: где питомец сейчас, маршрут, все места, фото на карте.
     Без ключа — OpenStreetMap (Leaflet лежит в плагине), с ключом — Google Maps.
     ============================================================ */
  function dark() { return D.documentElement.classList.contains("pgk-dark"); }
  function loadScript(src) { return new Promise(function (ok, no) { var s = D.createElement("script"); s.src = src; s.async = true; s.onload = ok; s.onerror = no; D.head.appendChild(s); }); }
  function loadCss(href) { if ($('link[href="' + href + '"]')) return; var c = D.createElement("link"); c.rel = "stylesheet"; c.href = href; D.head.appendChild(c); }
  function EngineOSM() {
    var map, grp, Lf;
    return {
      init: function (el) {
        loadCss(K.leaflet.css);
        return ((W.L && W.L.map) ? Promise.resolve() : loadScript(K.leaflet.js)).then(function () {
          Lf = W.L;
          map = Lf.map(el, { zoomControl: false, attributionControl: false, tap: true, zoomSnap: 0.5 }).setView([43.2389, 76.8897], 12);
          Lf.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19, className: "pgk-tiles", crossOrigin: true }).addTo(map);
          grp = Lf.layerGroup().addTo(map);
          map.on("zoomend", function () { if (MapK.zoomed) MapK.zoomed(); });
          return "© OpenStreetMap";
        });
      },
      clear: function () { grp.clearLayers(); },
      line: function (pts, o) {
        if (o.halo) Lf.polyline(pts, { color: o.halo, weight: o.weight + 4, opacity: 1, lineCap: "round", lineJoin: "round", interactive: false }).addTo(grp);
        Lf.polyline(pts, { color: o.color, weight: o.weight, opacity: o.opacity, dashArray: o.dash || null, lineCap: "round", lineJoin: "round", interactive: false }).addTo(grp);
      },
      html: function (pt, html, o) {
        var m = Lf.marker(pt, { icon: Lf.divIcon({ className: "pgk-mk-w", html: html, iconSize: null }), zIndexOffset: o.z || 0, keyboard: false }).addTo(grp);
        if (o.click) m.on("click", o.click);
      },
      fit: function (pts, p) {
        if (pts.length === 1) map.setView(pts[0], 16, { animate: !reduce });
        else if (pts.length > 1) map.fitBounds(Lf.latLngBounds(pts), { paddingTopLeft: [p[3], p[0]], paddingBottomRight: [p[1], p[2]], maxZoom: 17, animate: !reduce });
      },
      resize: function () { map.invalidateSize(); },
      zoom: function () { return map.getZoom(); },
      theme: function () {}
    };
  }
  var GSTYLE = {
    light: [
      { elementType: "geometry", stylers: [{ color: "#f1efe3" }] }, { elementType: "labels.text.fill", stylers: [{ color: "#5c706c" }] }, { elementType: "labels.text.stroke", stylers: [{ color: "#f7f5ea" }] },
      { featureType: "poi", elementType: "labels", stylers: [{ visibility: "off" }] }, { featureType: "poi.park", elementType: "geometry", stylers: [{ color: "#d7e6d0" }] },
      { featureType: "road", elementType: "geometry", stylers: [{ color: "#ffffff" }] }, { featureType: "road.highway", elementType: "geometry", stylers: [{ color: "#ece4c8" }] },
      { featureType: "transit", stylers: [{ visibility: "off" }] }, { featureType: "water", elementType: "geometry", stylers: [{ color: "#c3dbd6" }] }, { featureType: "administrative", elementType: "geometry", stylers: [{ visibility: "off" }] }
    ],
    dark: [
      { elementType: "geometry", stylers: [{ color: "#161616" }] }, { elementType: "labels.text.fill", stylers: [{ color: "#8e8e93" }] }, { elementType: "labels.text.stroke", stylers: [{ color: "#161616" }] },
      { featureType: "poi", elementType: "labels", stylers: [{ visibility: "off" }] }, { featureType: "poi.park", elementType: "geometry", stylers: [{ color: "#1b2a20" }] },
      { featureType: "road", elementType: "geometry", stylers: [{ color: "#2a2a2c" }] }, { featureType: "road.highway", elementType: "geometry", stylers: [{ color: "#3a3a3c" }] },
      { featureType: "transit", stylers: [{ visibility: "off" }] }, { featureType: "water", elementType: "geometry", stylers: [{ color: "#0b1f1c" }] }, { featureType: "administrative", elementType: "geometry", stylers: [{ visibility: "off" }] }
    ]
  };
  function EngineGoogle() {
    var map, g, items = [], Ov;
    return {
      init: function (el) {
        var ready = (W.google && W.google.maps && W.google.maps.Map) ? Promise.resolve() : new Promise(function (ok, no) {
          W.__pgkGm = ok;
          loadScript("https://maps.googleapis.com/maps/api/js?key=" + encodeURIComponent(K.gkey) + "&v=weekly&callback=__pgkGm").catch(no);
          setTimeout(function () { if (!(W.google && W.google.maps && W.google.maps.Map)) no(new Error("gm")); }, 12000);
        });
        return ready.then(function () {
          g = W.google.maps;
          map = new g.Map(el, { center: { lat: 43.2389, lng: 76.8897 }, zoom: 12, disableDefaultUI: true, clickableIcons: false, gestureHandling: "greedy", keyboardShortcuts: false, backgroundColor: dark() ? "#161616" : "#f1efe3", styles: GSTYLE[dark() ? "dark" : "light"] });
          map.addListener("zoom_changed", function () { if (MapK.zoomed) MapK.zoomed(); });
          Ov = function (pt, html, o) { this.pt = new g.LatLng(pt[0], pt[1]); this.html = html; this.o = o; this.setMap(map); };
          Ov.prototype = new g.OverlayView();
          Ov.prototype.onAdd = function () {
            var d = D.createElement("div"); d.className = "pgk-mk-w pgk-mk-g"; d.innerHTML = this.html; d.style.position = "absolute"; d.style.zIndex = String(1000 + (this.o.z || 0));
            if (this.o.click) { var c = this.o.click; d.addEventListener("click", function (e) { e.stopPropagation(); c(); }); }
            this.d = d; this.getPanes().overlayMouseTarget.appendChild(d);
          };
          Ov.prototype.draw = function () { var p = this.getProjection().fromLatLngToDivPixel(this.pt); if (p && this.d) { this.d.style.left = p.x + "px"; this.d.style.top = p.y + "px"; } };
          Ov.prototype.onRemove = function () { if (this.d) this.d.remove(); this.d = null; };
          return "";
        });
      },
      clear: function () { items.forEach(function (x) { x.setMap(null); }); items = []; },
      line: function (pts, o) {
        var path = pts.map(function (p) { return { lat: p[0], lng: p[1] }; });
        if (o.halo) items.push(new g.Polyline({ path: path, map: map, strokeColor: o.halo, strokeWeight: o.weight + 4, strokeOpacity: 1, clickable: false }));
        items.push(new g.Polyline({ path: path, map: map, strokeColor: o.color, strokeWeight: o.weight, strokeOpacity: o.opacity, clickable: false }));
      },
      html: function (pt, html, o) { items.push(new Ov(pt, html, o)); },
      fit: function (pts, p) {
        if (pts.length === 1) { map.setCenter({ lat: pts[0][0], lng: pts[0][1] }); map.setZoom(16); }
        else if (pts.length > 1) { var b = new g.LatLngBounds(); pts.forEach(function (x) { b.extend({ lat: x[0], lng: x[1] }); }); map.fitBounds(b, { top: p[0], right: p[1], bottom: p[2], left: p[3] }); }
      },
      resize: function () {},
      zoom: function () { return map ? map.getZoom() : 14; },
      theme: function () { if (map) map.setOptions({ styles: GSTYLE[dark() ? "dark" : "light"], backgroundColor: dark() ? "#161616" : "#f1efe3" }); }
    };
  }
  var MapK = (function () {
    var E = null, hist = null, mode = "now", focus = 0, walk = 0, starting = null, fitted = false, pts = [], zt = 0;
    var COLORS = ["#0E7A64", "#E58A2B", "#3F7CAC", "#C2557A", "#6E9F2E", "#8A5CC2"];
    function color(pid) { var ids = (K.pets || []).map(function (p) { return p.id; }), i = ids.indexOf(pid); return COLORS[(i < 0 ? 0 : i) % COLORS.length]; }
    function ago(ts) { if (!ts) return ""; var s = Math.max(0, Math.round(Date.now() / 1000 - ts)); return s < 45 ? T("только что") : Math.round(s / 60) + " " + T("мин") + " " + T("назад"); }
    function petMk(p, lv) {
      var bub = lv ? (p.hi ? "<em>" + esc(p.hi) + "</em> " : "") + esc(p.text) : esc(p.name);
      return '<div class="pgk-mk pgk-pm' + (lv ? " live" : "") + '" style="--c:' + color(p.id) + '"><i class="pgk-pm-ring"></i>' + avHtml(p, "pgk-pm-av") + '<span class="pgk-pm-bub">' + bub + "</span></div>";
    }
    function photoMk(u, off) { return '<div class="pgk-mk pgk-phm"' + (off ? ' style="--dx:' + off[0] + "px;--dy:" + off[1] + 'px"' : "") + '><img src="' + esc(u) + '" alt="" loading="lazy"></div>'; }
    /* 34.8: фото в одном месте раскладываются рядом (в пикселях экрана при текущем масштабе), но не уезжают далеко */
    function spread(list) {
      var z = E && E.zoom ? E.zoom() : 15, sc = 256 * Math.pow(2, z), put = [], BOX = 62;
      return list.map(function (ph) {
        var la = Math.max(-85, Math.min(85, ph.lat)) * Math.PI / 180;
        var x = (ph.lng + 180) / 360 * sc, y = (1 - Math.log(Math.tan(la) + 1 / Math.cos(la)) / Math.PI) / 2 * sc;
        var tries = [[0, 0]];
        for (var r = 1; r <= 3; r++) for (var a = 0; a < 8; a++) tries.push([Math.round(Math.cos(a * Math.PI / 4 + r * 0.4) * r * BOX * 0.95), Math.round(Math.sin(a * Math.PI / 4 + r * 0.4) * r * BOX * 0.95)]);
        var best = tries[0];
        for (var i = 0; i < tries.length; i++) {
          var cx = x + tries[i][0], cy = y + tries[i][1], free = true;
          for (var k = 0; k < put.length; k++) if (Math.abs(put[k][0] - cx) < BOX && Math.abs(put[k][1] - cy) < BOX) { free = false; break; }
          if (free) { best = tries[i]; break; }
        }
        put.push([x + best[0], y + best[1]]);
        return best;
      });
    }
    function dotMk(c) { return '<i class="pgk-mk pgk-dot" style="--c:' + c + '"></i>'; }
    function card(html) { var c = $("[data-k-mcard]"); if (!c) return; if (c._h === html) return; c._h = html; c.hidden = !html; if (html) c.innerHTML = html; }
    function shown(pid) { return !focus || focus === pid; }
    function segs() {
      qa("[data-k-mpet]").forEach(function (b) { b.classList.toggle("on", +b.getAttribute("data-k-mpet") === focus); });
      qa("[data-k-mmode]").forEach(function (b) { b.classList.toggle("on", b.getAttribute("data-k-mmode") === mode); });
      sgMove($(".pgk-mseg"));
    }
    function liveCard(p) {
      var left = p.end ? Math.round((p.end - Date.now() / 1000) / 60) : 0, bits = [];
      if (p.min) bits.push(T("Прогулка") + " " + p.min + " " + T("мин"));
      if (left > 0) bits.push(T("ещё") + " " + left + " " + T("мин"));
      if (p.at) bits.push(T("обновлено") + " " + ago(p.at));
      return '<div class="pgk-mc">' + avHtml(p, "md") + '<div><b>' + esc(p.name) + ' <i class="pgk-live-dot"></i></b><span class="pgk-mc-st st-' + esc(p.st) + '">' + (p.hi ? esc(p.hi) + " " : "") + esc(p.text) + "</span><small>" + esc(bits.join(" · ")) + "</small></div>" + (p.nav ? avHtml({ av: p.nav, name: p.nanny || "" }, "sm") : "") + "</div>";
    }
    function draw() {
      if (!E) return;
      E.clear();
      pts = [];
      var pets = K.pets || [], any = false, ch = "", halo = dark() ? "#000000" : "#ffffff";
      segs();
      if (mode === "now") {
        var first = "";
        pets.forEach(function (p) {
          if (!shown(p.id)) return;
          var c = "", pick = function () { focus = p.id; fitted = false; draw(); };
          if (p.live && p.pos) {
            if (p.track && p.track.length > 1) {
              var line = p.track.map(function (t) { return [t[0], t[1]]; });
              E.line(line, { color: color(p.id), weight: 5, opacity: 1, halo: halo });
              E.html(line[0], dotMk(color(p.id)), {});
              pts = pts.concat(line);
            }
            var lp = (p.photos || []).filter(function (ph) { return ph.lat != null; }), lo = spread(lp);
            lp.forEach(function (ph, i) { E.html([ph.lat, ph.lng], photoMk(ph.u, lo[i]), { z: 200, click: function () { lightbox(ph.u, p.name + " · " + ph.t); } }); });
            E.html(p.pos, petMk(p, true), { z: 1000, click: pick });
            pts.push(p.pos);
            c = liveCard(p);
          } else {
            var w = hist ? hist.walks.filter(function (x) { return x.pet === p.id; })[0] : null;
            if (w && w.p.length) {
              var last = w.p[w.p.length - 1];
              E.line(w.p.map(function (t) { return [t[0], t[1]]; }), { color: color(p.id), weight: 4, opacity: 0.45, dash: "1 9" });
              E.html([last[0], last[1]], petMk(p, false), { z: 900, click: pick });
              pts.push([last[0], last[1]]);
              c = '<div class="pgk-mc">' + avHtml(p, "md") + "<div><b>" + esc(p.name) + '</b><span class="pgk-mc-st st-' + esc(p.st) + '">' + esc(p.text) + "</span><small>" + esc(T("Последнее место")) + " · " + esc(w.d) + "</small></div></div>";
            } else if (p.home) {
              E.html(p.home, petMk(p, false), { z: 900, click: pick });
              pts.push(p.home);
              c = '<div class="pgk-mc">' + avHtml(p, "md") + "<div><b>" + esc(p.name) + '</b><span class="pgk-mc-st st-home">' + esc(T("Дома")) + "</span><small>" + esc(p.addr || "") + "</small></div></div>";
            }
          }
          if (c) { any = true; if (focus === p.id || (!ch && p.live)) ch = c; if (!first) first = c; }
        });
        card(ch || first);
      } else if (mode === "all" && hist) {
        var n = 0;
        hist.walks.forEach(function (w) {
          if (!shown(w.pet) || !w.p.length) return;
          var hl = walk && w.o === walk;
          any = true; n++;
          var line = w.p.map(function (t) { return [t[0], t[1]]; });
          if (line.length > 1) E.line(line, { color: color(w.pet), weight: hl ? 6 : 4, opacity: hl ? 1 : (walk ? 0.25 : 0.65), halo: hl ? halo : null });
          E.html(line[0], dotMk(color(w.pet)), {});
          if (!walk || hl) pts = pts.concat(line);
        });
        card(any ? '<div class="pgk-mc"><span class="pgk-mc-i">' + ICON.route + "</span><div><b>" + esc(T("Прогулки")) + ": " + n + "</b><small>" + esc(T("Все места")) + "</small></div></div>" : "");
      } else if (mode === "photos" && hist) {
        var hp = hist.photos.filter(function (ph) { return shown(ph.pet); }), ho = spread(hp);
        hp.forEach(function (ph, i) {
          any = true;
          E.html([ph.lat, ph.lng], photoMk(ph.u, ho[i]), { click: function () { lightbox(ph.u, ((petOf(ph.pet) || {}).name || "") + " · " + ph.d); } });
          pts.push([ph.lat, ph.lng]);
        });
        card("");
      }
      var em = $(".pgk-map-empty");
      if (em) em.hidden = !!any || !hist;
      if (!fitted && pts.length) { fit(); fitted = true; }
    }
    function fit() { if (E && pts.length) E.fit(pts, [110, 70, 150, 40]); }
    function start() {
      if (starting) return starting;
      var el = D.getElementById("pgk-map");
      E = K.gkey ? EngineGoogle() : EngineOSM();
      starting = E.init(el).catch(function () {
        /* Google не загрузился (неверный ключ и т. п.) — сразу открываем OpenStreetMap */
        if (!K.gkey) throw new Error("map");
        el.innerHTML = ""; E = EngineOSM(); return E.init(el);
      }).then(function (attr) {
        var ld = $(".pgk-map-load", el); if (ld) ld.remove();
        var a = $("[data-k-mattr]"); if (a) a.textContent = attr || "";
        var em = D.createElement("div"); em.className = "pgk-map-empty"; em.hidden = true;
        em.innerHTML = '<span class="pgk-empty-i">' + ICON.route + "</span><p>" + esc(T("Маршрут появится здесь, когда няня начнёт прогулку.")) + "</p>";
        el.parentNode.appendChild(em);
      }).catch(function () {
        el.innerHTML = '<div class="pgk-map-load"><p>' + esc(T("Не получилось — проверьте интернет.")) + "</p></div>";
        E = null; starting = null;
      });
      return starting;
    }
    function show(opts) {
      opts = opts || {};
      if (opts.focus !== undefined) { focus = opts.focus; mode = "now"; walk = 0; fitted = false; }
      if (opts.walk) { walk = opts.walk; mode = "all"; fitted = false; }
      if (!D.getElementById("pgk-map")) return;
      start().then(function () {
        if (!E) return;
        homes();
        setTimeout(function () { if (E) E.resize(); segs(); }, 60);
        if (!hist) api("map").then(function (j) { if (j.ok) { hist = j.hist; applyPets(j.pets); homes(); draw(); } });
        draw();
      });
    }
    /* место дома по адресу: находим один раз и запоминаем */
    function homes() {
      (K.pets || []).forEach(function (p) {
        if (p.home || !p.addr || p._geo) return;
        p._geo = 1;
        var q = /алмат|almat/i.test(p.addr) ? p.addr : p.addr + ", Алматы";
        fetch("https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=kz&accept-language=ru&q=" + encodeURIComponent(q)).then(function (r) { return r.json(); }).then(function (a) {
          if (!a || !a[0]) return;
          p.home = [+a[0].lat, +a[0].lon];
          api("home", { pet: p.id, lat: p.home[0], lng: p.home[1] }).catch(function () {});
          fitted = false; draw();
        }).catch(function () {});
      });
    }
    return {
      show: show, now: function () { if (E && starting) draw(); }, fit: fit,
      mode: function (m) { mode = m; walk = 0; fitted = false; draw(); },
      focus: function (id) { focus = focus === id ? 0 : id; fitted = false; draw(); },
      theme: function () { if (E) { E.theme(); draw(); } },
      /* масштаб изменился — фото раскладываются заново, чтобы снова не наезжать друг на друга */
      zoomed: function () { if (E && (mode === "photos" || mode === "now")) { clearTimeout(zt); zt = setTimeout(draw, 80); } },
      reset: function () { E = null; starting = null; hist = null; focus = 0; walk = 0; mode = "now"; fitted = false; }
    };
  })();

  /* ---------- запуск (при открытии страницы и после перехода без перезагрузки) ---------- */
  function initGuest() {
    var guest = $(".pgk-auth");
    if (!guest || guest._i) return;
    guest._i = 1;
    var ly = D.getElementById("pg-layer"), fs = D.getElementById("pgk-forgot");
    if (fs && ly && fs.parentNode !== ly) ly.appendChild(fs);
    qa('input[type="tel"]', guest).concat(fs ? qa('input[type="tel"]', fs) : []).forEach(phoneMask);
    /* 36.1: экран входа «My PetGo» — отдельно от сайта, в теме телефона */
    if (D.getElementById("pgk-gate")) { D.documentElement.classList.add("pgk-on", "pgk-gate-on"); themeApply(); arts(); }
    requestAnimationFrame(function () { authSeg(guest, "in"); });
  }
  function initCabinet() {
    initGuest();
    dragSheets(D);
    var box = D.getElementById("pgk");
    if (!box || box._i) { if (box) CL.box = box; return; }
    box._i = 1;
    CL.box = box;
    try { K = JSON.parse(D.getElementById("pgk-cfg").textContent); } catch (e) { K = null; }
    if (!K) return;
    K.tr = K.tr || {};
    CL.lastMsg = 0; CL.day = ""; CL.tab = "home"; CL.scroll = {};
    MapK.reset();
    D.documentElement.classList.add("pgk-on");
    /* меню вкладок и листы — в слое поверх страницы (при переходах без перезагрузки страница меняется, слой остаётся) */
    var layer = D.getElementById("pg-layer") || D.body;
    qa(".pgk-sheet,.pgk-lb,.pgk-toast,.pgk-tabbar", box).forEach(function (s) { layer.appendChild(s); });
    dragSheets(layer);
    themeApply();
    var t = (location.hash || "").replace("#", "");
    try { if (TABS.indexOf(t) < 0) t = sessionStorage.getItem("pgk_tab") || "home"; } catch (x) { t = "home"; }
    tab(TABS.indexOf(t) > -1 ? t : "home", { force: true, keep: true });
    requestAnimationFrame(function () { sgAll(); lensMove(true); });
    topTitle(); scrollWatch(); swipeInit(); ptrInit();
    arts(); bell();
    /* 39.3: заставка «My PetGo» / экран загрузки уходит, когда приложение уже нарисовано */
    if (W.PGKBoot) requestAnimationFrame(function () { requestAnimationFrame(function () { W.PGKBoot.done(); }); });
    CL.soon = !!K.soon;
    /* приветствие по часам телефона */
    var hel = $("[data-k-hello]");
    if (hel) { var hr = new Date().getHours(); hel.textContent = T(hr >= 5 && hr < 12 ? "Доброе утро," : hr >= 12 && hr < 17 ? "Добрый день," : hr >= 17 && hr < 23 ? "Добрый вечер," : "Доброй ночи,"); }
    live();
    /* няня уже у двери, когда клиент открыл кабинет — QR сразу на экране */
    if (K.door && K.door.length) afterBoot(function () { doorCheck(K.door); }, 450);
    /* прогулка завершилась — кабинет сам предлагает оценить няню (один раз) */
    if (K.rv) {
      var seen = ""; try { seen = sessionStorage.getItem("pgk_rv") || ""; } catch (x) {}
      if (seen !== String(K.rv.o)) afterBoot(function () {
        try { sessionStorage.setItem("pgk_rv", String(K.rv.o)); } catch (x) {}
        if (stack.length) return;
        reviewOpen(K.rv.o, K.rv.n, K.rv.av, true);
        /* 34.6: больше сам не спросит про эту прогулку (оценить можно в «Прогулках» или в чате) */
        api("rv_skip", { order: K.rv.o }).catch(function () {});
      }, 1400);
    }
    /* 39.2: тестовая неделя — после первого завершённого заказа один раз просим оценить PetGo */
    else if (!(K.test && K.test.ask && !K.test.fb)) afterBoot(pushAsk, 900);
    if (!K.rv && K.test && K.test.ask && !K.test.fb) afterBoot(function () {
      if (stack.length || !$("#pgk-fb")) return;
      kOpen("fb");
      K.test.ask = false;
      api("tw_fb_seen").catch(function () {});
    }, 1600);
  }
  /* 39.3: всплывающее (QR у двери, «оцените няню», «оцените PetGo») — только после заставки */
  function afterBoot(fn, ms) {
    if (W.PGKBoot && W.PGKBoot.after) W.PGKBoot.after(function () { setTimeout(fn, Math.min(ms, 700)); });
    else setTimeout(fn, ms);
  }
  /* 39.3: перезагрузка приложения — сразу пустой экран в цвете темы с кружком, без мелькания */
  function hardReload(tabTo) {
    try { sessionStorage.setItem("pgk_reload", "1"); sessionStorage.setItem("pgk_tab", tabTo || CL.tab || "home"); } catch (x) {}
    var el = D.getElementById("pgk-boot");
    if (!el) { el = D.createElement("div"); el.id = "pgk-boot"; el.className = "pgk-boot reload in"; el.setAttribute("aria-hidden", "true"); el.innerHTML = '<i class="pgk-boot-sp"></i>'; D.body.appendChild(el); }
    void el.offsetWidth; el.classList.add("show");
    setTimeout(function () { location.reload(); }, 200);
  }
  function leaveCabinet() {
    if (D.getElementById("pgk") || D.getElementById("pgk-gate")) return;
    clearTimeout(CL.timer); clearInterval(CL.chatT);
    qa("#pg-layer > .pgk-tabbar, #pg-layer > .pgk-sheet, #pg-layer > .pgk-lb, #pg-layer > .pgk-toast, #pg-layer > .pgk-topbar").forEach(function (x) { x.remove(); });
    TB = null;
    stack = [];
    D.documentElement.classList.remove("pgk-on", "pgk-lock", "pgk-dark", "pgk-gate-on");
    var tc = $('meta[name="theme-color"]'); if (tc) tc.setAttribute("content", "#FBF9E8");
    K = null; CL.box = null;
  }

  /* ============================================================
     35.0 ДОГОВОР: подпись через eGov mobile (QR-подписание SIGEX) или галочкой в кабинете.
     На телефоне договор готовится заранее, пока клиент читает, — тогда одно нажатие сразу открывает eGov mobile.
     ============================================================ */
  var CT = { run: 0, pre: null, prep: 0, busy: false, t: 0 };
  var EGOV_I = '<svg class="pg-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 18.5c2.5 0 3.5-2 4.5-4.5s1.6-5.5 3-5.5c1.6 0 .4 4.4 1.8 4.4 1.1 0 1.6-2.4 2.9-2.4 1 0 1.1 1.6 2.1 1.6.6 0 1.1-.4 1.7-1.1"/><path d="M4 21h16"/></svg>';
  function ctMob() { return !!(W.matchMedia && matchMedia("(pointer: coarse)").matches) && /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || ""); }
  function ctForm() { return $("[data-k-ctform]"); }
  function ctVals(f) { return { name: f.elements.name.value.replace(/\s+/g, " ").trim(), vet: f.elements.vet.checked ? "1" : "0", photo: f.elements.photo.checked ? "1" : "0" }; }
  function ctKey(v) { return v.name + "|" + v.vet + "|" + v.photo; }
  function ctNameOk(n) { return n.length >= 5 && n.indexOf(" ") > 0 && !/\d/.test(n); }
  function ctBtn(b, on) {
    if (!b) return;
    clearTimeout(b._bt);
    if (on) { b.disabled = true; b._bt = setTimeout(function () { b.classList.add("busy"); }, 300); }
    else { b.classList.remove("busy"); b.disabled = false; }
  }
  function rj(r) { if (!r.ok) { var e = new Error("http"); e.status = r.status; throw e; } return r.json(); }
  /* запросы к eGov через SIGEX: при обрыве связи (телефон ушёл в eGov mobile) повторяем */
  function ctRetry(fn, run) {
    var n = 0, t0 = Date.now();
    return new Promise(function (ok, bad) {
      (function go() {
        if (run !== CT.run) return;
        fn().then(ok).catch(function (e) {
          var st = e && e.status;
          if (run !== CT.run) return;
          if ((st && st < 500 && st !== 408 && st !== 429) || ++n > 40 || Date.now() - t0 > 15 * 60000) { bad(e); return; }
          setTimeout(go, D.hidden ? 2500 : 1500);
        });
      })();
    });
  }
  function ctSigex(doc) {
    return fetch(doc.sigex + "/api/egovQr", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ description: doc.title.ru }), cache: "no-store" }).then(rj).then(function (q) {
      if (!q || q.message || !q.dataURL || !q.signURL) { var e = new Error("sigex"); e.msg = q && q.message; throw e; }
      return q;
    });
  }
  /* телефон: готовим договор и сеанс eGov заранее (без отправки данных), чтобы кнопка сразу открыла приложение */
  function ctPrep() {
    clearTimeout(CT.t);
    var f = ctForm();
    if (!f || f.hidden || CT.busy || !ctMob() || !K || !K.ct) return;
    var v = ctVals(f), key = ctKey(v);
    if (!ctNameOk(v.name)) return;
    if (CT.pre && CT.pre.key === key && Date.now() - CT.pre.at < 150000) return;
    var my = ++CT.prep;
    CT.pre = null;
    api("ct_doc", v).then(function (j) {
      if (my !== CT.prep || !j.ok) return;
      return ctSigex(j).then(function (q) { if (my === CT.prep) CT.pre = { key: key, at: Date.now(), doc: j, q: q }; });
    }).catch(function () {});
  }
  function ctOpen() {
    var s = kOpen("ct"); if (!s) return;
    var f = ctForm();
    if (f && !f.hidden) setTimeout(ctPrep, 400);
  }
  function ctBack(msg) {
    CT.run++; CT.busy = false;
    var root = $("[data-k-ctroot]"), w = root && $("[data-k-ct-wait]", root), f = ctForm();
    if (w) { w.hidden = true; w.innerHTML = ""; }
    if (f) { f.hidden = false; formErr(f, msg || ""); ctBtn($("[data-k-ct-egov]", f), false); }
    CT.pre = null;
    if (msg) setTimeout(ctPrep, 600);
  }
  function ctDone(h, st) {
    var root = $("[data-k-ctroot]"); if (!root) return;
    CT.run++; CT.busy = false; CT.pre = null;
    var old = $("[data-k-ct-done]", root); if (old) old.remove();
    root.insertAdjacentHTML("afterbegin", h);
    var f = ctForm(), w = $("[data-k-ct-wait]", root);
    if (f) f.hidden = true;
    if (w) { w.hidden = true; w.innerHTML = ""; }
    root.setAttribute("data-st", st);
    if (K && K.ct) K.ct.st = st;
    qa("[data-k-ctcard], [data-k-ctbook]").forEach(function (c) { c.remove(); });
    var dt = new Date(), dd = ("0" + dt.getDate()).slice(-2) + "." + ("0" + (dt.getMonth() + 1)).slice(-2) + "." + dt.getFullYear();
    qa("[data-k-ct-st]").forEach(function (x) { x.textContent = T(st === "egov" ? "Подписан через eGov" : "Подписан в кабинете") + " · " + dd; x.classList.add("ok"); });
    var sc = root.closest(".pgk-sc"); if (sc) sc.scrollTop = 0;
    if (navigator.vibrate) try { navigator.vibrate(30); } catch (x) {}
    /* лист закрыли, пока шла подпись, — сообщаем отдельно */
    var sh = root.closest(".pgk-sheet"); if (sh && !sh.classList.contains("on")) toast(T("Договор подписан"));
  }
  function ctClick(b) {
    var f = ctForm(); if (!f) return;
    var v = ctVals(f);
    formErr(f, "");
    if (!ctNameOk(v.name)) { formErr(f, T("Укажите фамилию и имя, как в документе."), "name"); return; }
    if (!f.elements.agree.checked) { formErr(f, T("Отметьте, что прочитали договор и согласны."), "agree"); return; }
    ctBtn(b, true);
    v.agree = "1";
    api("ct_click", v).then(function (j) {
      ctBtn(b, false);
      if (!j.ok) { formErr(f, j.msg || "", j.field); return; }
      ctDone(j.h, j.st);
    }).catch(function () { ctBtn(b, false); formErr(f, T("Не получилось — проверьте интернет.")); });
  }
  function ctEgov(b) {
    var f = ctForm(); if (!f) return;
    var v = ctVals(f), key = ctKey(v), mob = ctMob();
    formErr(f, "");
    if (!ctNameOk(v.name)) { formErr(f, T("Укажите фамилию и имя, как в документе."), "name"); return; }
    /* договор уже готов — открываем eGov mobile этим же нажатием */
    if (mob && CT.pre && CT.pre.key === key && Date.now() - CT.pre.at < 150000) { var p = CT.pre; CT.pre = null; ctWait(p.q, p.doc, true); return; }
    var run = ++CT.run;
    CT.busy = true; CT.prep++;
    ctBtn(b, true);
    api("ct_doc", v).then(function (j) {
      if (!j.ok) { var e = new Error("doc"); e.msg = j.msg; e.field = j.field; throw e; }
      return ctSigex(j).then(function (q) { return { doc: j, q: q }; });
    }).then(function (r) {
      if (run !== CT.run) return;
      ctBtn(b, false);
      ctWait(r.q, r.doc, false);
    }).catch(function (e) {
      if (run !== CT.run) return;
      CT.busy = false; ctBtn(b, false);
      formErr(f, (e && e.field && e.msg) || (e && e.msg && e.message === "doc" ? e.msg : T("eGov сейчас не отвечает. Попробуйте ещё раз или подпишите без eGov.")), e && e.field);
    });
  }
  /* ждём подпись: телефон — кнопка eGov mobile, компьютер — QR-код для eGov mobile */
  function ctWait(q, doc, launch) {
    var root = $("[data-k-ctroot]"), w = $("[data-k-ct-wait]", root), f = ctForm(), mob = ctMob(), run = ++CT.run;
    CT.busy = true;
    var qr = '<div class="pgk-ctw-qr"><img src="data:image/gif;base64,' + esc(q.qrCode || "") + '" alt="QR"></div>';
    w.innerHTML = '<div class="pgk-ctw' + (mob ? " mob" : "") + '">' +
      (mob ? '<span class="pgk-ct-ic pgk-ctw-ic">' + EGOV_I + "</span><b>" + esc(T("Подпишите в eGov mobile")) + "</b><p>" + esc(T("Откроется eGov mobile с договором. Подпишите его и вернитесь сюда — договор сохранится сам.")) + '</p><a class="pg-b pgk-btn pgk-btn-p pgk-ctw-go" data-ext data-k-ctgo href="' + esc(q.eGovMobileLaunchLink || "#") + '">' + EGOV_I + esc(T("Открыть eGov mobile")) + "</a>" +
          (q.eGovBusinessLaunchLink ? '<a class="pg-b pgk-link" data-ext data-k-ctgo href="' + esc(q.eGovBusinessLaunchLink) + '">' + esc(T("Открыть eGov Business")) + "</a>" : "") +
          '<details class="pgk-ctw-alt"><summary>' + esc(T("Подписать с другого телефона")) + "</summary>" + qr + "</details>"
        : qr + "<b>" + esc(T("Отсканируйте QR-код в eGov mobile")) + '</b><ol class="pgk-ctw-steps"><li>' + esc(T("Откройте eGov mobile на телефоне")) + "</li><li>" + esc(T("Нажмите значок QR-сканера")) + "</li><li>" + esc(T("Наведите камеру на код и подпишите договор")) + "</li></ol>") +
      '<p class="pgk-ctw-st" data-k-ctw-st><i class="pg-spin" aria-hidden="true"></i><span>' + esc(T("Ждём подпись…")) + '</span></p><button type="button" class="pg-b pgk-link" data-k-ct-cancel>' + esc(T("Отмена")) + "</button></div>";
    f.hidden = true; w.hidden = false;
    var sc = root.closest(".pgk-sc"); if (sc) sc.scrollTop = 0;
    var st = $("[data-k-ctw-st] span", w);
    var body = JSON.stringify({ signMethod: "CMS_WITH_DATA", version: 1, documentsToSign: [{ id: 1, nameRu: doc.title.ru, nameKz: doc.title.kk, nameEn: doc.title.en, meta: doc.meta, document: { file: { mime: "@file/pdf", data: doc.b64 } } }] });
    /* договор уходит в eGov сразу; телефон заберёт его, когда клиент откроет eGov mobile */
    ctRetry(function () { return fetch(q.dataURL, { method: "POST", headers: { "Content-Type": "application/json" }, body: body, cache: "no-store" }).then(rj); }, run).then(function (r) {
      if (run !== CT.run) return;
      if (r && r.message) { var e = new Error("sigex"); e.msg = r.message; throw e; }
      w.classList.add("got"); st.textContent = T("Договор открыт в eGov mobile — подпишите его там");
      return ctRetry(function () { return fetch(q.signURL, { cache: "no-store" }).then(rj); }, run);
    }).then(function (r) {
      if (run !== CT.run || !r) return;
      if (r.status === "CANCELED") { var e1 = new Error("cancel"); e1.msg = T("Подпись отменена в eGov mobile."); throw e1; }
      var x = r.documentsToSign && r.documentsToSign[0], cms = x && x.document && x.document.file && x.document.file.data;
      if (!cms) { var e2 = new Error("nosig"); e2.msg = T("Подпись не получена. Попробуйте ещё раз."); throw e2; }
      st.textContent = T("Проверяем подпись…");
      return api("ct_egov", { cms: cms }).then(function (j) {
        if (run !== CT.run) return;
        if (!j.ok) { var e3 = new Error("srv"); e3.msg = j.msg; throw e3; }
        ctDone(j.h, j.st);
      });
    }).catch(function (e) {
      if (run !== CT.run) return;
      ctBack((e && e.msg && e.message !== "sigex") ? e.msg : (e && e.status && e.status < 500 ? T("Время на подпись вышло. Нажмите «Подписать через eGov» ещё раз.") : T("Связь с eGov прервалась. Нажмите «Подписать через eGov» ещё раз.")));
    });
    /* телефон: открываем eGov mobile (данные уже отправляются) */
    if (launch && q.eGovMobileLaunchLink) setTimeout(function () { if (run === CT.run) location.href = q.eGovMobileLaunchLink; }, 260);
  }
  /* поменяли имя или решения — договор на телефоне подготовим заново */
  ["input", "change"].forEach(function (ev) {
    D.addEventListener(ev, function (e) {
      var t = e.target;
      if (!t || !t.closest || !t.closest("[data-k-ctform]") || !/^(name|vet|photo)$/.test(t.name || "")) return;
      CT.pre = null; CT.prep++; clearTimeout(CT.t); CT.t = setTimeout(ctPrep, 800);
    });
  });
  /* первая заявка отправлена, договор ещё не подписан — предлагаем подписать */
  function ctAfterBook(f) {
    if (!K || !K.ct || !K.ct.on || K.ct.st) return;
    var dn = $(".pgk-done", f.parentNode); if (!dn || $("[data-k-ctbook]", dn)) return;
    var bd = $("[data-k-bookdone]", dn);
    var box = D.createElement("div");
    box.className = "pgk-ctbook"; box.setAttribute("data-k-ctbook", "");
    box.innerHTML = "<b>" + esc(T("Остался один шаг — договор")) + "</b><p>" + esc(T("Подпишите его один раз — через eGov или прямо в кабинете. Он действует для всех заказов.")) + '</p><button type="button" class="pg-b pgk-btn pgk-btn-p" data-k-ct>' + EGOV_I + esc(T("Подписать договор")) + "</button>";
    if (bd) { bd.classList.remove("pgk-btn-p"); bd.classList.add("pgk-btn-s"); dn.insertBefore(box, bd); } else dn.appendChild(box);
  }

  /* ============================================================
     38.1 КАБИНЕТ ПО МАКЕТУ: экран приветствия, няни (список, поиск, профиль), избранное, уведомления
     ============================================================ */
  var HEART = '<path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10C19.5 15.4 12 20 12 20Z"/>';
  var WALKI = '<circle cx="13" cy="4.5" r="1.8"/><path d="m9 21 2.2-6.5 2.8 2.5V21M8 10.5l2.5-3.2h3.2l2.5 3 2.3.7M11.2 14.5 12.5 9"/>';
  var CHATI = '<path d="M12 4.5c4.4 0 8 2.9 8 6.6s-3.6 6.6-8 6.6c-.9 0-1.7-.1-2.5-.3L5.5 19.5l.9-3.3C4.9 15 4 13.2 4 11.1c0-3.7 3.6-6.6 8-6.6z"/>';
  var VER = '<svg class="pg-i pgk-ver" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m12 2 2.4 1.8 3-.2.9 2.9 2.5 1.7-1 2.8 1 2.8-2.5 1.7-.9 2.9-3-.2L12 22l-2.4-1.8-3 .2-.9-2.9-2.5-1.7 1-2.8-1-2.8 2.5-1.7.9-2.9 3 .2Z"/><path d="m8.5 12.2 2.3 2.3 4.7-4.8" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  /* спящая собака — пустой экран уведомлений */
  var SLEEP = '<svg class="pgk-sleep" viewBox="0 0 160 110" aria-hidden="true"><ellipse cx="80" cy="96" rx="62" ry="8" fill="currentColor" opacity=".08"/><path d="M30 92c0-22 20-38 50-38s48 14 48 34c0 3-2 5-5 5H35c-3 0-5-.5-5-1z" fill="#E7B46A"/><path d="M96 70c8-2 18 2 22 10" stroke="#C98E43" stroke-width="3" fill="none" stroke-linecap="round"/><circle cx="46" cy="74" r="20" fill="#F0C27E"/><path d="M30 62c-6 4-9 14-4 20 3-6 6-12 10-16z" fill="#C98E43"/><path d="M56 58c7 1 12 9 10 16-4-5-7-10-13-12z" fill="#C98E43"/><path d="M38 75q4 3 8 0M50 75q4 3 8 0" stroke="#5B3A1A" stroke-width="2" fill="none" stroke-linecap="round"/><ellipse cx="48" cy="83" rx="4" ry="3" fill="#5B3A1A"/><path d="M118 92c10 0 16-6 14-12" stroke="#E7B46A" stroke-width="7" fill="none" stroke-linecap="round"/><g fill="currentColor" font-family="-apple-system,Segoe UI,sans-serif" font-weight="700" class="pgk-zz"><text x="70" y="40" font-size="14">z</text><text x="84" y="28" font-size="18">z</text><text x="102" y="16" font-size="22">z</text></g></svg>';
  function nnOf(id) { return (K.nannies || []).filter(function (n) { return n.id === id; })[0]; }
  function isFav(id) { return (K.fav || []).indexOf(id) > -1; }
  function nword(n, ru, en, kz) {
    if (K.lang === "en") return n === 1 ? en[0] : en[1];
    if (K.lang === "kz") return kz;
    var a = n % 10, b = n % 100;
    return ru[a === 1 && b !== 11 ? 0 : a >= 2 && a <= 4 && (b < 10 || b >= 20) ? 1 : 2];
  }
  function rvWord(n) { n = +n || 0; return n + " " + nword(n, ["отзыв", "отзыва", "отзывов"], ["review", "reviews"], "пікір"); }
  function ordWord(n) { n = +n || 0; return n + " " + nword(n, ["заказ", "заказа", "заказов"], ["order", "orders"], "тапсырыс"); }
  function rateFmt(r) { var x = Number(r).toFixed(1); return K.lang === "en" ? x : x.replace(".", ","); }
  function nRate(n) { return n.rate ? svg(STAR, "pg-i pgk-star") + "<em>" + esc(rateFmt(n.rate)) + "</em> <small>(" + esc(rvWord(n.rn)) + ")</small>" : "<small>" + esc(T("Новая няня")) + "</small>"; }
  function ncard(n) {
    var pr = (K.price || {}).walk || 0, pl = +n.place || 0;
    return '<button type="button" class="pg-b pgk-nc" data-k-nanny="' + n.id + '"><span class="pgk-nc-av">' + avHtml({ av: n.img, name: n.name }) + (pl ? '<i class="pgk-place p' + Math.min(4, pl) + '" aria-label="' + esc(T("%s место").replace("%s", pl)) + '">' + pl + "</i>" : "") + '</span><span class="pgk-nc-b"><b>' + esc(n.name) + VER + "</b>" +
      '<span class="pgk-nc-r">' + nRate(n) + "</span>" +
      '<span class="pgk-nc-m"><small>' + svg(WALKI) + esc(ordWord(n.done)) + "</small>" + (firstFree() ? '<small class="pgk-nc-p"><b>0 ₸</b> · ' + esc(freeTag()) + "</small>" : pr ? '<small class="pgk-nc-p">' + esc(T("от")) + " <b>" + esc(money(pr)) + "</b> / " + esc(T("час")) + "</small>" : "") + "</span></span>" +
      '<i class="pgk-nc-fav' + (isFav(n.id) ? " on" : "") + '" data-k-fav="' + n.id + '" role="button" aria-label="' + esc(T("В избранное")) + '">' + svg(HEART) + "</i></button>";
  }
  function emptyBox(icon, title, text) { return '<div class="pgk-empty38">' + icon + "<b>" + esc(title) + "</b>" + (text ? "<p>" + esc(text) + "</p>" : "") + "</div>"; }

  /* 39.0 «Наши няни»: поиск — по имени и описанию; фильтр — отдельно (сортировка, оценка, избранные, с отзывами).
     Места: 1, 2, 3 — у кого больше хороших отзывов; на главной — первые три, в «Все» — все (4, 5, 6…). */
  var NS = { q: "" }, NF0 = { sort: "place", min: 0, fav: false, rv: false }, NF = JSON.parse(JSON.stringify(NF0));
  function nfActive() { return (NF.sort !== "place" ? 1 : 0) + (NF.min ? 1 : 0) + (NF.fav ? 1 : 0) + (NF.rv ? 1 : 0); }
  function nFiltered(f) {
    f = f || NF;
    var q = NS.q.toLowerCase().trim(), key = f.sort === "place" ? "" : f.sort;
    var list = (K.nannies || []).filter(function (n) {
      if (f.fav && !isFav(n.id)) return false;
      if (f.rv && !(+n.rn)) return false;
      if (f.min && !(+n.rate >= f.min)) return false;
      return !q || (n.name + " " + (n.bio || "")).toLowerCase().indexOf(q) > -1;
    });
    list.sort(function (a, b) { return key ? ((+b[key] || 0) - (+a[key] || 0) || (+a.place || 0) - (+b.place || 0)) : (+a.place || 0) - (+b.place || 0); });
    return list;
  }
  function nannies(mode) {
    var s = kOpen("nannies"); if (!s) return;
    var q = $("[data-k-nq]", s);
    NS.q = ""; if (q) q.value = "";
    if (mode === "fav") { NF = JSON.parse(JSON.stringify(NF0)); NF.fav = true; }
    else if (mode !== "keep") NF = JSON.parse(JSON.stringify(NF0));
    $("[data-k-sht]", s).textContent = T(NF.fav && nfActive() === 1 ? "Избранные няни" : "Наши няни");
    var sc = $(".pgk-sc", s); if (sc) sc.scrollTop = 0;
    nList();
    if (mode === "search" && q) setTimeout(function () { q.focus(); }, reduce ? 0 : 300);
  }
  function nfLabel() {
    var o = [];
    if (NF.sort !== "place") o.push(T({ rate: "По оценке", rn: "По отзывам", done: "По опыту" }[NF.sort]));
    if (NF.min) o.push(rateFmt(NF.min) + " " + T("и выше"));
    if (NF.fav) o.push(T("Только избранные"));
    if (NF.rv) o.push(T("Только с отзывами"));
    return o;
  }
  function nList() {
    var box = $("[data-k-nlist]"); if (!box) return;
    var list = nFiltered(), q = NS.q.trim(), n = nfActive(), on = $("[data-k-nf-on]"), badge = $("[data-k-nf-n]");
    if (badge) { badge.textContent = n; badge.hidden = !n; }
    if (on) { on.hidden = !n; on.innerHTML = n ? nfLabel().map(function (x) { return "<span>" + esc(x) + "</span>"; }).join("") + '<button type="button" class="pg-b" data-k-nf-clear aria-label="' + esc(T("Сбросить")) + '">' + svg('<path d="M6 6l12 12M18 6 6 18"/>') + "</button>" : ""; }
    var favOnly = NF.fav && !q;
    box.innerHTML = list.length ? list.map(ncard).join("") : emptyBox('<span class="pgk-empty38-i">' + svg(favOnly ? HEART : '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>') + "</span>", T(favOnly ? "Избранные няни" : "Никого не нашли"), favOnly ? T("Нет избранных нянь — нажмите ♡ на карточке няни.") : "");
  }
  /* лист «Фильтр»: выбор применяется кнопкой «Показать нянь: N» — число меняется сразу */
  var NFd = null;
  function nfOpen() {
    NFd = JSON.parse(JSON.stringify(NF));
    var s = kOpen("nfilter"); if (!s) return;
    nfPaint(s);
  }
  function nfPaint(s) {
    s = s || D.getElementById("pgk-nfilter"); if (!s || !NFd) return;
    qa('[data-nf="sort"] .pg-b', s).forEach(function (b) { b.classList.toggle("on", b.getAttribute("data-v") === NFd.sort); });
    qa('[data-nf="min"] .pg-b', s).forEach(function (b) { b.classList.toggle("on", +b.getAttribute("data-v") === +NFd.min); });
    var fv = $('[data-nf="fav"]', s), rv = $('[data-nf="rv"]', s);
    if (fv) fv.checked = !!NFd.fav; if (rv) rv.checked = !!NFd.rv;
    var c = $("[data-k-nf-cnt]", s); if (c) c.textContent = T("Показать нянь: %s").replace("%s", nFiltered(NFd).length);
  }
  D.addEventListener("click", function (e) {
    var t = e.target, b;
    if (!K || !t.closest) return;
    if ((b = t.closest("#pgk-nfilter [data-nf] .pg-b"))) { var g = b.parentNode.getAttribute("data-nf"), v = b.getAttribute("data-v"); NFd[g] = g === "min" ? +v : v; nfPaint(); return; }
    if (t.closest("[data-k-nf-reset]")) { NFd = JSON.parse(JSON.stringify(NF0)); nfPaint(); return; }
    if (t.closest("[data-k-nf-apply]")) { NF = NFd; NS.q = ""; kClose(true); setTimeout(function () { nannies("keep"); }, reduce ? 0 : 120); return; }
    if (t.closest("[data-k-nf-clear]")) { NF = JSON.parse(JSON.stringify(NF0)); nList(); $("[data-k-sht]", D.getElementById("pgk-nannies")).textContent = T("Наши няни"); return; }
    if (t.closest("[data-k-nfilter]")) { e.preventDefault(); nfOpen(); }
  });
  D.addEventListener("change", function (e) {
    var t = e.target;
    if (K && NFd && t.matches && t.matches('#pgk-nfilter [data-nf]')) { NFd[t.getAttribute("data-nf")] = t.checked; nfPaint(); }
  });
  /* профиль няни: большое фото, рейтинг, услуги, о себе, отзывы, «Заказать» и «Написать» */
  function nannyOpen(id) {
    var n = nnOf(id); if (!n) return;
    var s = kOpen("nanny"); if (!s) return;
    var body = $("[data-k-nanny-body]", s), pr = (K.price || {}).walk || 0;
    var tags = Object.keys(K.svcs || {}).map(function (k) { return "<span>" + esc(K.svcs[k].t) + "</span>"; }).join("");
    var rv = (n.rv || []).map(function (r) { return '<div class="pgk-nrv"><div class="pgk-nrv-h"><b>' + esc(r.n) + '</b><span class="pgk-nrv-s">' + stars(r.r) + "</span><small>" + esc(r.d) + "</small></div>" + (r.t ? "<p>" + esc(r.t) + "</p>" : "") + "</div>"; }).join("");
    var meta = [ordWord(n.done), n.since ? T("В PetGo с %s года").replace("%s", n.since) : ""].filter(Boolean);
    body.innerHTML = '<div class="pgk-np"><div class="pgk-np-ph">' + (n.img ? '<img src="' + esc(n.img) + '" alt="">' : "<b>" + esc(n.name.charAt(0).toUpperCase()) + "</b>") +
      '<i class="pgk-nc-fav pgk-np-fav' + (isFav(n.id) ? " on" : "") + '" data-k-fav="' + n.id + '" role="button" aria-label="' + esc(T("В избранное")) + '">' + svg(HEART) + "</i></div>" +
      '<div class="pgk-np-b"><h2>' + esc(n.name) + VER + '</h2><div class="pgk-np-r">' + nRate(n) + "</div>" +
      '<div class="pgk-np-m">' + meta.map(function (x) { return "<span>" + esc(x) + "</span>"; }).join("") + "</div>" +
      '<div class="pgk-np-ok">' + svg('<path d="M12 3.5l7 2.5v5.5c0 4.5-3 7.7-7 9-4-1.3-7-4.5-7-9V6z"/><path d="M9 12l2.2 2.2L15 10.5"/>') + esc(T("Проверена PetGo")) + "</div>" +
      '<div class="pgk-np-tags">' + tags + "</div>" +
      (n.bio ? '<h4 class="pgk-gt">' + esc(T("О няне")) + '</h4><p class="pgk-np-bio">' + esc(n.bio) + "</p>" : "") +
      (rv ? '<h4 class="pgk-gt">' + esc(T("Отзывы")) + '</h4><div class="pgk-nrvs">' + rv + "</div>" : "") + "</div>" +
      '<div class="pgk-np-go"><button type="button" class="pg-b pgk-btn pgk-btn-p" data-k-nbook="' + n.id + '"><span>' + esc(T("Заказать выгул или визит")) + "</span>" + (firstFree() ? "<em>0 ₸ · " + esc(freeTag()) + "</em>" : pr ? "<em>" + esc(T("от")) + " " + esc(money(pr)) + "</em>" : "") + '</button><button type="button" class="pg-b pgk-btn pgk-btn-o" data-k-nmsg="' + n.id + '">' + svg(CHATI) + esc(T("Написать")) + "</button></div></div>";
    var sc = $(".pgk-sc", s); if (sc) sc.scrollTop = 0;
  }
  function nannyBook(id) {
    kClose(true);
    setTimeout(function () { book("walk"); if (nnOf(id) && BK.busy.indexOf(id) < 0) { BK.nanny = id; bkNannies(); } }, reduce ? 0 : 120);
  }
  function nannyMsg(id) {
    var n = nnOf(id);
    kClose(true);
    setTimeout(function () {
      chat();
      var ta = $("[data-k-send] textarea");
      if (ta && n && !ta.value) { ta.value = T("Здравствуйте! Хочу заказать няню %s.").replace("%s", n.name); ta.dispatchEvent(new Event("input", { bubbles: true })); }
    }, reduce ? 0 : 120);
  }
  function favToggle(id) {
    var on = !isFav(id);
    K.fav = (K.fav || []).filter(function (x) { return x !== id; });
    if (on) K.fav.push(id);
    qa('[data-k-fav="' + id + '"]').forEach(function (x) { x.classList.toggle("on", on); x.classList.remove("pop"); if (on && !reduce) { void x.offsetWidth; x.classList.add("pop"); } });
    toast(T(on ? "Добавлено в избранное" : "Убрано из избранного"));
    api("fav", { id: id, on: on ? "1" : "0" }).then(function (j) { if (j && j.ok && j.fav) K.fav = j.fav; }).catch(function () {});
  }

  /* уведомления: сообщения PetGo (заказ подтверждён, няня пришла, отчёт); точка на колокольчике — есть новые */
  function nSeen() { try { return +localStorage.getItem("pgk_nseen_" + K.uid) || 0; } catch (x) { return 0; } }
  function bell() { var d = $("[data-k-bell]"); if (d && K) d.hidden = !(K.nlast && K.nlast > nSeen()); }
  /* 40.0: значки уведомлений по смыслу: заявка, подтверждено, прогулка, няня пришла, дома, отмена, отзыв, карта */
  var NTI = {
    cal: '<rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>',
    check: '<circle cx="12" cy="12" r="8.5"/><path d="M8.2 12.3l2.6 2.6 5-5.2"/>',
    walk: '<circle cx="13" cy="4.5" r="1.8"/><path d="M10.5 21l2-6.5 2.5 2.5V21M8 12.5l2.5-5h3l2 3.5 2.5 1"/><path d="M12.5 14.5l-2-3.5"/>',
    home: '<path d="M3.5 10.5 12 4l8.5 6.5"/><path d="M5.5 9.5V20h13V9.5"/><path d="M10 20v-5h4v5"/>',
    done: '<path d="M3.5 10.5 12 4l8.5 6.5"/><path d="M5.5 9.5V20h13V9.5"/><path d="M9 14.5l2 2 4-4"/>',
    x: '<circle cx="12" cy="12" r="8.5"/><path d="M9 9l6 6M15 9l-6 6"/>',
    pin: '<path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
    star: '<path d="M12 3.8l2.5 5.2 5.7.8-4.1 4 1 5.7L12 16.8l-5.1 2.7 1-5.7-4.1-4 5.7-.8z"/>',
    bell: '<path d="M6 9a6 6 0 0 1 12 0c0 6 2 7.5 2 7.5H4S6 15 6 9"/><path d="M10 20a2 2 0 0 0 4 0"/>'
  };
  function ntAct(m) {
    if (m.k === "rv" && m.s) return m.rv ? '<span class="pgk-myrv">' + stars(m.rv) + "<small>" + esc(T("Спасибо за оценку")) + "</small></span>" : '<button type="button" class="pg-b pgk-btn pgk-btn-p pgk-btn-sm" data-k-review="' + m.s + '">' + svg(STAR) + esc(T("Оценить няню")) + "</button>";
    if (m.k === "loc" && m.pid) return '<button type="button" class="pg-b pgk-btn pgk-btn-s pgk-btn-sm" data-k-tab="map" data-k-focus="' + m.pid + '">' + svg(NTI.pin) + esc(T("Где сейчас")) + "</button>";
    return "";
  }
  function notifs() {
    var s = kOpen("notifs"); if (!s) return;
    var body = $("[data-k-notifs-body]", s);
    body.innerHTML = '<div class="pgk-map-load"><i class="pg-spin"></i></div>';
    Promise.all([api("notifs40"), pushState()]).then(function (r) {
      var j = r[0], st = r[1], it = (j && j.items) || [], seen = nSeen(), h = "", day = "";
      /* уведомления на телефон выключены — сразу здесь можно включить */
      if (st === "off") h += '<div class="pgk-ntp"><span>' + svg(NTI.bell) + '</span><b>' + esc(T("Уведомления на телефон выключены")) + '</b><button type="button" class="pg-b pgk-btn pgk-btn-p pgk-btn-sm" data-k-push-on>' + esc(T("Включить")) + "</button></div>";
      if (!it.length) { body.innerHTML = h + emptyBox(SLEEP, T("Пока нет уведомлений"), T("Здесь появятся подтверждения заказов, приход няни и отчёты о прогулках.")); bell(); return; }
      it.forEach(function (m, i) {
        if (m.dl !== day) { h += (day ? "</div>" : "") + '<h4 class="pgk-ntd">' + esc(dayLabel(m.dl)) + '</h4><div class="pgk-card pgk-nts">'; day = m.dl; }
        h += '<div class="pgk-nt' + (m.id > seen ? " new" : "") + '" style="--i:' + Math.min(i, 8) + '"><span class="pgk-nt-i ic-' + esc(m.ic) + '">' + svg(NTI[m.ic] || NTI.bell) + '</span><div class="pgk-nt-t"><div class="pgk-nt-h"><b>' + esc(m.t) + "</b><time>" + esc(m.tm) + "</time></div>" + (m.x ? "<p>" + esc(m.x) + "</p>" : "") + ntAct(m) + "</div></div>";
      });
      body.innerHTML = h + "</div>";
      K.nlast = Math.max(K.nlast || 0, it[0].id);
      try { localStorage.setItem("pgk_nseen_" + K.uid, String(it[0].id)); } catch (x) {}
      bell();
    }).catch(function () { body.innerHTML = ""; toast(T("Не получилось — проверьте интернет."), true); });
  }

  /* ============================================================
     40.0 УВЕДОМЛЕНИЯ НА ТЕЛЕФОН: в браузере и в приложении с экрана «Домой» — web push,
     в приложении My PetGo из магазинов — через Firebase (если владелец подключил ключ)
     ============================================================ */
  function pnNative() { var c = W.Capacitor; return !!(c && c.isNativePlatform && c.isNativePlatform() && c.Plugins && c.Plugins.PushNotifications) ? c.Plugins.PushNotifications : null; }
  function pushWebOk() { return "serviceWorker" in navigator && "PushManager" in W && "Notification" in W && !!(K.push && K.push.key); }
  function b64u(s) { var p = "=".repeat((4 - s.length % 4) % 4), r = atob((s + p).replace(/-/g, "+").replace(/_/g, "/")), o = new Uint8Array(r.length); for (var i = 0; i < r.length; i++) o[i] = r.charCodeAt(i); return o; }
  function pushState() {
    if (!K || !K.push) return Promise.resolve("na");
    var pn = pnNative();
    if (pn) {
      if (!K.push.native) return Promise.resolve("na");
      return pn.checkPermissions().then(function (p) { return p.receive === "granted" ? (K.push.nt ? "on" : "off") : p.receive === "denied" ? "denied" : "off"; }).catch(function () { return "na"; });
    }
    if (!pushWebOk()) return Promise.resolve("na");
    if (Notification.permission === "denied") return Promise.resolve("denied");
    return navigator.serviceWorker.ready.then(function (r) { return r.pushManager.getSubscription(); }).then(function (s) { return s && Notification.permission === "granted" ? "on" : "off"; }).catch(function () { return "off"; });
  }
  var pnBound = false;
  function pnBind(pn) {
    if (pnBound) return; pnBound = true;
    pn.addListener("registration", function (t) { if (t && t.value) api("push_native", { token: t.value }).then(function () { K.push.nt = 1; }).catch(function () {}); });
    pn.addListener("pushNotificationActionPerformed", function (a) { var u = a && a.notification && a.notification.data && a.notification.data.u; if (u && u.indexOf(location.origin) === 0) location.href = u; else notifs(); });
  }
  function pushOn(quiet) {
    var pn = pnNative();
    var done = function (ok) { if (!quiet) toast(ok ? T("Уведомления включены") : T("Уведомления выключены в настройках телефона. Включите их для этого приложения."), !ok); qa("[data-k-push-on]").forEach(function (b) { if (ok) b.closest(".pgk-ntp") && b.closest(".pgk-ntp").remove(); }); return ok; };
    if (pn) {
      pnBind(pn);
      return pn.requestPermissions().then(function (p) { if (p.receive !== "granted") return done(false); return pn.register().then(function () { return done(true); }); }).catch(function () { return done(false); });
    }
    if (!pushWebOk()) return Promise.resolve(false);
    return Notification.requestPermission().then(function (p) {
      if (p !== "granted") return done(false);
      return navigator.serviceWorker.ready.then(function (r) {
        return r.pushManager.getSubscription().then(function (s) { return s || r.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64u(K.push.key) }); });
      }).then(function (s) { return api("push_sub", { s: JSON.stringify(s) }); }).then(function () { K.push.on = 1; return done(true); });
    }).catch(function () { return done(false); });
  }
  function pushLater() { try { localStorage.setItem("pgk_push_later", String(Date.now())); } catch (x) {} }
  /* при открытии приложения: уже разрешено — тихо обновляем подписку; не решено — спрашиваем (не чаще раза в неделю) */
  function pushAsk() {
    var pn = pnNative();
    if (pn && K.push && K.push.native) { pnBind(pn); pn.checkPermissions().then(function (p) { if (p.receive === "granted") pn.register().catch(function () {}); }).catch(function () {}); }
    pushState().then(function (st) {
      if (st !== "off") return;
      if (!pn && pushWebOk() && Notification.permission === "granted") { pushOn(true); return; }
      var later = 0; try { later = +localStorage.getItem("pgk_push_later") || 0; } catch (x) {}
      if (Date.now() - later < 7 * 86400000 || stack.length) return;
      kOpen("push");
    });
  }

  /* картинки по макету (питомцы на первом экране, собака в акции) — те же анимации, что на сайте */
  function arts() {
    qa("[data-k-art]").forEach(function (el) {
      if (el._a) return;
      var u = el.getAttribute("data-k-art"), h = W.PGSite && W.PGSite.media ? W.PGSite.media(u, true) : "";
      if (h) { el._a = 1; el.innerHTML = h; }
    });
  }
  /* экран приветствия: «Начать» — регистрация, «Войти» — вход, стрелка — назад */
  function gateGo(v) {
    var g = D.getElementById("pgk-gate"); if (!g) return;
    kbHide();
    g.setAttribute("data-gv", v === "hi" ? "hi" : "form");
    if (v !== "hi") { var a = $(".pgk-auth", g); if (a) requestAnimationFrame(function () { authSeg(a, v); }); }
    scrollToY(0, false);
  }

  /* ---------- все нажатия — одним обработчиком ---------- */
  D.addEventListener("click", function (e) {
    var t = e.target, b;
    if (!t.closest) return;
    if ((b = t.closest("[data-auth]"))) { authSeg(b.closest(".pgk-auth"), b.getAttribute("data-auth")); return; }
    if ((b = t.closest("[data-gate]"))) { e.preventDefault(); gateGo(b.getAttribute("data-gate")); return; }
    if ((b = t.closest("[data-eye]"))) { var inp = b.parentNode.querySelector("input"); if (inp) { inp.type = inp.type === "password" ? "text" : "password"; b.classList.toggle("on", inp.type === "text"); } return; }
    if ((b = t.closest("[data-k-open]"))) { e.preventDefault(); var id = b.getAttribute("data-k-open"); if (id === "chat" && K) chat(); else kOpen(id); return; }
    if ((b = t.closest("[data-k-close]"))) { e.preventDefault(); kClose(); return; }
    if ((b = t.closest("[data-k-push-on]"))) { e.preventDefault(); var inPush = b.closest("#pgk-push"); pushOn().then(function () { if (inPush) kClose(); }); return; }
    if ((b = t.closest("[data-k-push-later]"))) { e.preventDefault(); pushLater(); kClose(); return; }
    /* 40.0: позвать менеджера в чат — помощник замолкает, менеджер отвечает здесь */
    if ((b = t.closest("[data-k-mgr]"))) {
      e.preventDefault(); if (b.disabled) return; b.disabled = true;
      api("mgr").then(function (j) { toast(j.msg || T("Отправлено")); b.textContent = T("Менеджер позван"); if (CL.chatOpen && W.PGKChatPoll) W.PGKChatPoll(); }).catch(function () { b.disabled = false; toast(T("Не получилось — проверьте интернет."), true); });
      return;
    }
    if ((b = t.closest("[data-k-rvlater]"))) { e.preventDefault(); kClose(); return; }
    if ((b = t.closest("[data-k-bookdone]"))) { e.preventDefault(); try { history.replaceState(history.state, "", "#walks"); } catch (x) {} hardReload("walks"); return; }
    if ((b = t.closest("[data-k-lbx]")) || (t.classList && t.classList.contains("pgk-lb"))) { lbClose(); return; }
    if ((b = t.closest("[data-k-photo]"))) { e.preventDefault(); lightbox(b.getAttribute("data-k-photo"), b.getAttribute("data-k-cap")); return; }
    if (!K) return;
    /* 38.1: избранное, няни, уведомления, личные данные */
    if ((b = t.closest("[data-k-fav]"))) { e.preventDefault(); e.stopPropagation(); favToggle(+b.getAttribute("data-k-fav")); return; }
    if ((b = t.closest("[data-k-nanny]"))) { e.preventDefault(); nannyOpen(+b.getAttribute("data-k-nanny")); return; }
    if ((b = t.closest("[data-k-nannies]"))) { e.preventDefault(); nannies(b.getAttribute("data-k-nannies")); return; }
    if ((b = t.closest("[data-k-nbook]"))) { e.preventDefault(); nannyBook(+b.getAttribute("data-k-nbook")); return; }
    if ((b = t.closest("[data-k-nmsg]"))) { e.preventDefault(); nannyMsg(+b.getAttribute("data-k-nmsg")); return; }
    if ((b = t.closest("[data-k-notifs]"))) { e.preventDefault(); notifs(); return; }
    if ((b = t.closest("[data-k-edit]"))) { e.preventDefault(); kOpen("me"); return; }
    if ((b = t.closest("[data-k-tab]"))) {
      e.preventDefault();
      if (stack.length) kClose(true);
      var o = {};
      if (b.hasAttribute("data-k-focus")) o.focus = +b.getAttribute("data-k-focus");
      if (b.hasAttribute("data-k-walk")) o.walk = +b.getAttribute("data-k-walk");
      if (b.closest(".pgk-tabbar")) { o.keep = true; o.bar = true; }
      tab(b.getAttribute("data-k-tab"), o);
      if (b.getAttribute("data-k-goto") === "pets") setTimeout(function () { var mp = D.getElementById("pgk-mypets"); if (mp) scrollToY(Math.max(0, scrollY() + mp.getBoundingClientRect().top - 70), true); }, 60);
      return;
    }
    if ((b = t.closest("[data-k-order]"))) { e.preventDefault(); orderSheet(+b.getAttribute("data-k-order")); return; }
    if ((b = t.closest("[data-k-ct-egov]"))) { e.preventDefault(); ctEgov(b); return; }
    if ((b = t.closest("[data-k-ct-click]"))) { e.preventDefault(); ctClick(b); return; }
    if ((b = t.closest("[data-k-ct-cancel]"))) { e.preventDefault(); ctBack(""); return; }
    if ((b = t.closest("[data-k-ct-redo]"))) { e.preventDefault(); var cf = ctForm(), cd = $("[data-k-ct-done]"); if (cf) { cf.hidden = false; if (cd) cd.hidden = true; var ag = $(".pgk-ct-agree", cf), ab = $("[data-k-ct-click]", cf); if (ag) ag.hidden = true; if (ab) ab.hidden = true; } setTimeout(ctPrep, 300); return; }
    if ((b = t.closest("[data-k-ct]"))) { e.preventDefault(); ctOpen(); return; }
    if ((b = t.closest("[data-k-pet]"))) { e.preventDefault(); petSheet(+b.getAttribute("data-k-pet")); return; }
    if ((b = t.closest("[data-k-petpage]"))) { e.preventDefault(); petPage(+b.getAttribute("data-k-petpage")); return; }
    if ((b = t.closest("[data-k-book]"))) { e.preventDefault(); book(b.getAttribute("data-k-book"), +(b.getAttribute("data-k-bpet") || 0)); return; }
    if ((b = t.closest("[data-k-post]"))) { e.preventDefault(); postSheet(b); return; }
    /* 36.2: язык кабинета — страница открывается на выбранном языке, сайт запоминает выбор */
    if ((b = t.closest("[data-k-lang]"))) {
      if (b.classList.contains("on")) return;
      qa("[data-k-lang]").forEach(function (x) { x.classList.toggle("on", x === b); });
      sgMove(b.parentNode);
      var lu = b.getAttribute("data-url"); setTimeout(function () { location.href = lu; }, 180);
      return;
    }
    if ((b = t.closest("[data-k-theme]"))) { try { localStorage.setItem("pgk_theme", b.getAttribute("data-k-theme")); } catch (x) {} themeApply(true); return; }
    if ((b = t.closest("[data-k-mmode]"))) { MapK.mode(b.getAttribute("data-k-mmode")); return; }
    if ((b = t.closest("[data-k-mpet]"))) { MapK.focus(+b.getAttribute("data-k-mpet")); return; }
    if ((b = t.closest("[data-k-mfit]"))) { MapK.fit(); return; }
    if ((b = t.closest("[data-k-review]"))) {
      var rid = +b.getAttribute("data-k-review"), nm = b.getAttribute("data-k-rv-name"), av = b.getAttribute("data-k-rv-av");
      if (!nm && K.rv && K.rv.o === rid) { nm = K.rv.n; av = K.rv.av; }
      reviewOpen(rid, nm || "", av || "", false);
      return;
    }
    if ((b = t.closest("[data-k-join]"))) {
      b.disabled = true;
      api("join", { id: b.getAttribute("data-k-join") }).then(function (j) {
        toast(j.msg || "");
        if (j.ok) { b.classList.remove("pgk-btn-p"); b.classList.add("pgk-btn-s"); b.textContent = T("Вы записаны"); } else b.disabled = false;
      }).catch(function () { b.disabled = false; toast(T("Не получилось — проверьте интернет."), true); });
    }
  });
  D.addEventListener("change", function (e) {
    var t = e.target;
    if (!K || !t.matches) return;
    if (t.matches("[data-k-avatar],[data-k-petav]") && t.files && t.files[0]) {
      var pet = t.getAttribute("data-k-petav"), wrap = t.closest("label") || t.parentNode, file = t.files[0], img = wrap.querySelector(".pgk-av");
      wrap.classList.add("busy");
      shrink(file, 900).then(function (b) { return api(pet ? "pet_av" : "avatar", pet ? { pet: pet } : {}, { photo: b }); }).then(function (j) {
        wrap.classList.remove("busy");
        if (!j.ok) { toast(j.msg || "", true); return; }
        var h = '<img src="' + esc(j.url) + '" alt="">';
        if (img) img.innerHTML = h;
        /* аватар меняется везде: в шапке, списке питомцев, на карте */
        if (pet) { qa('[data-k-petpage="' + pet + '"] .pgk-av').forEach(function (x) { x.innerHTML = h; }); var p = petOf(+pet); if (p) p.av = j.url; }
        else qa(".pgk-hd-av .pgk-av, .pgk-me-av .pgk-av").forEach(function (x) { x.innerHTML = h; });
        toast(T("Сохранено"));
      }).catch(function () { wrap.classList.remove("busy"); toast(T("Не получилось — проверьте интернет."), true); });
      t.value = "";
    }
    if (t.matches('[data-k-send] input[name="photo"]')) t.closest("form").classList.toggle("has-ph", !!(t.files && t.files[0]));
  });
  D.addEventListener("input", function (e) {
    var t = e.target;
    if (t.matches && t.matches("[data-k-send] textarea")) { t.style.height = "auto"; t.style.height = Math.min(140, t.scrollHeight) + "px"; }
    if (t.matches && t.matches("[data-k-nq]")) { NS.q = t.value; nList(); }
  });
  D.addEventListener("keydown", function (e) {
    if (e.key === "Escape") { if ($(".pgk-lb.on")) lbClose(); else if (stack.length) kClose(); }
    if (e.key === "Enter" && !e.shiftKey && e.target.matches && e.target.matches("[data-k-send] textarea") && !("ontouchstart" in W)) { e.preventDefault(); sendMsg(e.target.closest("form")); }
  });
  D.addEventListener("visibilitychange", function () { if (!D.hidden && K) { refresh().then(live); if (CL.chatOpen) loadMsgs(); } });
  /* iPhone вернул страницу из памяти (переключились между приложениями) — сразу обновить */
  W.addEventListener("pageshow", function (e) { if (e.persisted && K) { refresh().then(live); if (CL.chatOpen) loadMsgs(); } });
  var rzT = 0;
  W.addEventListener("resize", function () {
    clearTimeout(rzT);
    rzT = setTimeout(function () {
      sgAll(); lensMove(true);
      var g = $(".pgk-auth"), on = g && $(".pgk-auth-sg .on", g);
      if (on) authSeg(g, on.getAttribute("data-auth"));
    }, 120);
  });

  /* 34.5: для других плагинов — запросы кабинета, сообщения, листы, вкладки и настройки (window.PGK) */
  W.PGK = {
    api: function (op, data, files) { return K ? api(op, data, files) : Promise.reject(new Error("no cabinet")); },
    toast: toast, open: function (id) { return kOpen(id); }, close: kClose, tab: function (to) { tab(to, {}); },
    cfg: function () { return K; }, on: function (n, f) { if (W.PGEvent && W.PGEvent.on) W.PGEvent.on(n, f); }
  };

  /* переходы по сайту без перезагрузки: ui.js вызывает PGInitPage после смены страницы */
  var prev = W.PGInitPage;
  W.PGInitPage = function () { if (prev) prev.apply(this, arguments); setTimeout(function () { leaveCabinet(); initCabinet(); }, 0); };
  if (D.readyState === "loading") D.addEventListener("DOMContentLoaded", initCabinet); else initCabinet();
})();
