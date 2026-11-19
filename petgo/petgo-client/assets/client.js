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
    var t = themeGet(), on = !!D.getElementById("pgk") && (t === "dark" || (t === "auto" && !!(mq && mq.matches)));
    D.documentElement.classList.toggle("pgk-dark", on);
    var m = $('meta[name="theme-color"]'); if (m) m.setAttribute("content", on ? "#000000" : "#F2F0E3");
    qa("[data-k-theme]").forEach(function (b) { b.classList.toggle("on", b.getAttribute("data-k-theme") === t); });
    sgMove($(".pgk-theme"));
    MapK.theme();
  }
  if (mq && mq.addEventListener) mq.addEventListener("change", function () { if (K) themeApply(); });

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
      h.addEventListener("touchstart", function (e) { if (e.target.closest("button")) return; drag = true; y0 = ly = e.touches[0].clientY; t0 = lt = Date.now(); dy = 0; v = 0; sh.classList.add("drag"); }, { passive: true });
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

  /* ---------- вкладки ---------- */
  var TABS = ["home", "map", "walks", "acc"];
  function tab(to, opts) {
    if (!CL.box || TABS.indexOf(to) < 0) return;
    opts = opts || {};
    var from = CL.tab;
    if (from === to && !opts.force) {
      /* нажали на открытую вкладку — наверх, как в iPhone */
      if (!opts.keep) W.scrollTo({ top: 0, behavior: reduce ? "auto" : "smooth" });
      if (to === "map") MapK.show(opts);
      return;
    }
    CL.scroll[from] = W.scrollY;
    CL.tab = to;
    CL.box.setAttribute("data-tab", to);
    qa(".pgk-pane", CL.box).forEach(function (p) { p.classList.toggle("on", p.getAttribute("data-pane") === to); });
    qa(".pgk-tabbar [data-k-tab]").forEach(function (b) { var on = b.getAttribute("data-k-tab") === to; b.classList.toggle("on", on); if (on) b.setAttribute("aria-current", "page"); else b.removeAttribute("aria-current"); });
    try { history.replaceState(history.state, "", "#" + to); sessionStorage.setItem("pgk_tab", to); } catch (x) {}
    W.scrollTo(0, opts.keep ? (CL.scroll[to] || 0) : 0);
    if (to === "map") MapK.show(opts);
    sgAll(CL.box);
    live();
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
  function bkDurs() {
    var sv = K.svcs[BK.kind] || K.svcs.walk;
    if (sv.durs.indexOf(BK.dur) < 0) BK.dur = sv.dur;
    seg(bpart("dur"), sv.durs.map(function (x) { return [x, durLabel(x)]; }), BK.dur);
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
  function bkPrice() {
    var p = petOf(BK.pet), pr = K.price || {};
    if (BK.kind === "walk") return Math.round((pr.walk || 0) * BK.dur / 60);
    if (BK.kind === "week") return (p && p.sp === "cat" ? pr.week_cat : pr.week_dog) || 0;
    return 0;
  }
  function bkSum() {
    var box = bpart("sum"); if (!box) return;
    var price = bkPrice(), sv = K.svcs[BK.kind] || {};
    var d = BK.date ? new Date(BK.date + "T00:00:00") : null, st = BK.h * 60 + BK.m, en = st + BK.dur;
    var when = d ? T(WD[d.getDay()]).replace(/^./, function (c) { return c.toUpperCase(); }) + ", " + d.getDate() + " " + T(MON[d.getMonth()]) : "";
    when += " · " + pad(BK.h) + ":" + pad(BK.m) + "–" + pad(Math.floor(en / 60) % 24) + ":" + pad(en % 60);
    if (sv.days > 1) when += " · " + sv.days + " " + T("дней");
    box.innerHTML = "<b>" + esc(when) + "</b>" + (price ? "<strong>≈ " + money(price) + "</strong>" : "<span>" + esc(T("Цену подтвердит менеджер")) + "</span>");
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
    bkPets();
    seg(bpart("kind"), Object.keys(K.svcs).map(function (k) { return [k, K.svcs[k].t]; }), BK.kind);
    bkDays(); bkDurs(); bkNannies();
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
    if (b === "pet") { BK.pet = +v; bkSum(); return; }
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
    formErr(f, ""); busyBtn(f, true);
    api("book", { pet: BK.pet, kind: BK.kind, date: BK.date, time: pad(BK.h) + ":" + pad(BK.m), dur: BK.dur, nanny: BK.nanny, note: f.elements.note.value, pg_src: W.PGSrc ? W.PGSrc() : "" }).then(function (j) { busyEnd(f, function () {
      if (j.ok && W.PGEvent) W.PGEvent("booking", { kind: BK.kind, value: bkPrice() });
      if (!j.ok) { formErr(f, j.msg || "", "", j.busy ? bpart("nanny") : $('[data-b="time"]', f)); if (j.busy) bkBusy(); return; }
      f.hidden = true; $(".pgk-done", f.parentNode).hidden = false; f.reset(); BK.touched = false;
      var sc = f.closest(".pgk-sc"); if (sc) sc.scrollTop = 0;
      CL.booked = true;
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
    if (m.k === "sys" || m.k === "rv" || m.k === "loc") {
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
  function orderSheet(id) {
    var s = kOpen("order"); if (!s) return;
    var box = $("[data-k-order-body]", s);
    box.innerHTML = '<div class="pgk-map-load"><i class="pg-spin"></i></div>';
    Promise.all([api("order", { order: id }), qrLib().catch(function () {})]).then(function (r) {
      var o = r[0];
      if (!o || !o.ok) { box.innerHTML = '<p class="pgk-note">' + esc((o && o.msg) || T("Не получилось — проверьте интернет.")) + "</p>"; return; }
      var tt = $("[data-k-sht]", s); if (tt) tt.textContent = o.pet + " · " + o.svc;
      var h = '<div class="pgk-ord-h">' + avHtml({ av: o.av, name: o.pet }, "md") + '<div><b>' + esc(o.when) + "</b><small>" + esc(o.dur + " " + T("мин")) + '</small></div><span class="pgk-badge st-' + esc(o.st) + '">' + esc(o.stl) + "</span></div>";
      if (o.qr && W.qrcode) {
        h += '<div class="pgk-qr"><div class="pgk-qr-img">' + qrSvg(o.qr.url) + '</div><small>' + esc(T("Код для няни")) + '</small><b class="pgk-qr-code">' + esc(o.qr.code) + "</b><small>" + esc(T("Действует до")) + " " + esc(o.qr.exp) + "</small></div>";
        h += '<p class="pgk-qr-tip"><b>' + esc(T(o.walk ? "Дайте няне отсканировать QR-код, когда она забирает питомца." : "Дайте няне отсканировать QR-код, когда она придёт. Если вас не будет дома — няня отметит приход без QR, а вы увидите её место на карте.")) + "</b>" + (o.walk ? " " + esc(T("Если вас не будет дома, няня заберёт питомца без QR, а вы увидите её место на карте.")) : "") + "</p>";
      } else if (o.by) {
        h += '<p class="pgk-qr-tip">' + esc(T(o.by === "qr" ? "Передача подтверждена QR-кодом" : "Без QR — место отмечено на карте")) + "</p>";
      }
      h += '<div class="pgk-list"><div class="pgk-row pgk-nrow">' + (o.nn ? avHtml({ av: o.nav, name: o.nn }) + '<span class="pgk-row-t"><b>' + esc(o.nn) + "</b><small>" + esc(T("Няня")) + "</small></span>" : '<span class="pgk-nany">' + ICON.users + '</span><span class="pgk-row-t"><b>' + esc(T("Няня ещё не назначена")) + "</b></span>") + "</div></div>";
      if (o.st === "arrived" || o.st === "picked") h += '<button type="button" class="pg-b pgk-btn pgk-btn-p pgk-go" data-k-tab="map" data-k-focus="' + o.pid + '">' + esc(T("Где сейчас")) + "</button>";
      box.innerHTML = h;
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
  function live() {
    clearTimeout(CL.timer);
    if (!K || !D.getElementById("pgk")) return;
    var anyLive = (K.pets || []).some(function (p) { return p.live; });
    var wait = CL.tab === "map" ? (anyLive ? 8000 : 30000) : (anyLive ? 15000 : 45000);
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
      theme: function () { if (map) map.setOptions({ styles: GSTYLE[dark() ? "dark" : "light"], backgroundColor: dark() ? "#161616" : "#f1efe3" }); }
    };
  }
  var MapK = (function () {
    var E = null, hist = null, mode = "now", focus = 0, walk = 0, starting = null, fitted = false, pts = [];
    var COLORS = ["#0E7A64", "#E58A2B", "#3F7CAC", "#C2557A", "#6E9F2E", "#8A5CC2"];
    function color(pid) { var ids = (K.pets || []).map(function (p) { return p.id; }), i = ids.indexOf(pid); return COLORS[(i < 0 ? 0 : i) % COLORS.length]; }
    function ago(ts) { if (!ts) return ""; var s = Math.max(0, Math.round(Date.now() / 1000 - ts)); return s < 45 ? T("только что") : Math.round(s / 60) + " " + T("мин") + " " + T("назад"); }
    function petMk(p, lv) {
      var bub = lv ? (p.hi ? "<em>" + esc(p.hi) + "</em> " : "") + esc(p.text) : esc(p.name);
      return '<div class="pgk-mk pgk-pm' + (lv ? " live" : "") + '" style="--c:' + color(p.id) + '"><i class="pgk-pm-ring"></i>' + avHtml(p, "pgk-pm-av") + '<span class="pgk-pm-bub">' + bub + "</span></div>";
    }
    function photoMk(u) { return '<div class="pgk-mk pgk-phm"><img src="' + esc(u) + '" alt="" loading="lazy"></div>'; }
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
            (p.photos || []).forEach(function (ph) { if (ph.lat != null) E.html([ph.lat, ph.lng], photoMk(ph.u), { z: 200, click: function () { lightbox(ph.u, p.name + " · " + ph.t); } }); });
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
        hist.photos.forEach(function (ph) {
          if (!shown(ph.pet)) return;
          any = true;
          E.html([ph.lat, ph.lng], photoMk(ph.u), { click: function () { lightbox(ph.u, ((petOf(ph.pet) || {}).name || "") + " · " + ph.d); } });
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
    requestAnimationFrame(function () { sgAll(); });
    live();
    /* прогулка завершилась — кабинет сам предлагает оценить няню (один раз) */
    if (K.rv) {
      var seen = ""; try { seen = sessionStorage.getItem("pgk_rv") || ""; } catch (x) {}
      if (seen !== String(K.rv.o)) setTimeout(function () {
        try { sessionStorage.setItem("pgk_rv", String(K.rv.o)); } catch (x) {}
        if (stack.length) return;
        reviewOpen(K.rv.o, K.rv.n, K.rv.av, true);
        /* 34.6: больше сам не спросит про эту прогулку (оценить можно в «Прогулках» или в чате) */
        api("rv_skip", { order: K.rv.o }).catch(function () {});
      }, 1400);
    }
  }
  function leaveCabinet() {
    if (D.getElementById("pgk")) return;
    clearTimeout(CL.timer); clearInterval(CL.chatT);
    qa("#pg-layer > .pgk-tabbar, #pg-layer > .pgk-sheet, #pg-layer > .pgk-lb, #pg-layer > .pgk-toast").forEach(function (x) { x.remove(); });
    stack = [];
    D.documentElement.classList.remove("pgk-on", "pgk-lock", "pgk-dark");
    K = null; CL.box = null;
  }

  /* ---------- все нажатия — одним обработчиком ---------- */
  D.addEventListener("click", function (e) {
    var t = e.target, b;
    if (!t.closest) return;
    if ((b = t.closest("[data-auth]"))) { authSeg(b.closest(".pgk-auth"), b.getAttribute("data-auth")); return; }
    if ((b = t.closest("[data-eye]"))) { var inp = b.parentNode.querySelector("input"); if (inp) { inp.type = inp.type === "password" ? "text" : "password"; b.classList.toggle("on", inp.type === "text"); } return; }
    if ((b = t.closest("[data-k-open]"))) { e.preventDefault(); var id = b.getAttribute("data-k-open"); if (id === "chat" && K) chat(); else kOpen(id); return; }
    if ((b = t.closest("[data-k-close]"))) { e.preventDefault(); kClose(); return; }
    if ((b = t.closest("[data-k-rvlater]"))) { e.preventDefault(); kClose(); return; }
    if ((b = t.closest("[data-k-bookdone]"))) { e.preventDefault(); try { sessionStorage.setItem("pgk_tab", "walks"); } catch (x) {} location.hash = "walks"; location.reload(); return; }
    if ((b = t.closest("[data-k-lbx]")) || (t.classList && t.classList.contains("pgk-lb"))) { lbClose(); return; }
    if ((b = t.closest("[data-k-photo]"))) { e.preventDefault(); lightbox(b.getAttribute("data-k-photo"), b.getAttribute("data-k-cap")); return; }
    if (!K) return;
    if ((b = t.closest("[data-k-tab]"))) {
      e.preventDefault();
      if (stack.length) kClose(true);
      var o = {};
      if (b.hasAttribute("data-k-focus")) o.focus = +b.getAttribute("data-k-focus");
      if (b.hasAttribute("data-k-walk")) o.walk = +b.getAttribute("data-k-walk");
      if (b.closest(".pgk-tabbar")) o.keep = true;
      tab(b.getAttribute("data-k-tab"), o);
      return;
    }
    if ((b = t.closest("[data-k-order]"))) { e.preventDefault(); orderSheet(+b.getAttribute("data-k-order")); return; }
    if ((b = t.closest("[data-k-pet]"))) { e.preventDefault(); petSheet(+b.getAttribute("data-k-pet")); return; }
    if ((b = t.closest("[data-k-petpage]"))) { e.preventDefault(); petPage(+b.getAttribute("data-k-petpage")); return; }
    if ((b = t.closest("[data-k-book]"))) { e.preventDefault(); book(b.getAttribute("data-k-book"), +(b.getAttribute("data-k-bpet") || 0)); return; }
    if ((b = t.closest("[data-k-post]"))) { e.preventDefault(); postSheet(b); return; }
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
        else qa(".pgk-hd-av .pgk-av").forEach(function (x) { x.innerHTML = h; });
        toast(T("Сохранено"));
      }).catch(function () { wrap.classList.remove("busy"); toast(T("Не получилось — проверьте интернет."), true); });
      t.value = "";
    }
    if (t.matches('[data-k-send] input[name="photo"]')) t.closest("form").classList.toggle("has-ph", !!(t.files && t.files[0]));
  });
  D.addEventListener("input", function (e) {
    var t = e.target;
    if (t.matches && t.matches("[data-k-send] textarea")) { t.style.height = "auto"; t.style.height = Math.min(140, t.scrollHeight) + "px"; }
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
      sgAll();
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
