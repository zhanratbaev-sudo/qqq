/* PetGo 34.0 — анкета питомца карточками, кабинет клиента (питомцы, карта, прогулки, чат с менеджером),
   красивый переключатель языка, запрет выделения на сайте. Работает и после переходов без перезагрузки. */
(function () {
  "use strict";
  var D = document, W = window;
  var C = W.PG_CONFIG || {};
  var reduce = W.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches;
  function T(s) { return (C.tr && C.tr[s]) || (K && K.tr && K.tr[s]) || s; }
  function $(s, el) { return (el || D).querySelector(s); }
  function qa(s, el) { return Array.prototype.slice.call((el || D).querySelectorAll(s)); }
  function esc(x) { return String(x == null ? "" : x).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
  function json(r) {
    return r.text().then(function (t) {
      var j = null;
      try { j = JSON.parse(t); } catch (e) { var m = t.match(/\{"ok":(true|false)[\s\S]*\}/); if (m) { try { j = JSON.parse(m[0]); } catch (e2) {} } }
      if (!j) { var er = new Error("bad"); er.st = r.status; throw er; }
      return j;
    });
  }
  /* фото перед отправкой уменьшаем (быстрее и экономнее для мобильного интернета) */
  function shrink(file, max) {
    max = max || 1600;
    return new Promise(function (ok) {
      if (!file || !/^image\//.test(file.type) || !W.URL || !D.createElement("canvas").toBlob) return ok(file);
      try {
        var u = URL.createObjectURL(file), im = new Image();
        im.onload = function () {
          var s = Math.min(1, max / Math.max(im.width, im.height)), c = D.createElement("canvas");
          c.width = Math.round(im.width * s); c.height = Math.round(im.height * s);
          c.getContext("2d").drawImage(im, 0, 0, c.width, c.height);
          URL.revokeObjectURL(u);
          c.toBlob(function (b) { ok(b || file); }, "image/jpeg", 0.84);
        };
        im.onerror = function () { ok(file); };
        im.src = u;
      } catch (e) { ok(file); }
    });
  }
  function phoneMask(ph) {
    if (!ph || ph._pm) return;
    ph._pm = 1;
    ph.addEventListener("focus", function () { if (ph.value === "") ph.value = "+7 "; });
    ph.addEventListener("blur", function () { if (ph.value.replace(/\D/g, "").length <= 1) ph.value = ""; });
    /* вставили или автозаполнили полный номер поверх «+7 » — убираем лишнее начало */
    function fix() {
      var v = ph.value.replace(/[^\d+\s()\-]/g, "");
      v = v.replace(/^\+7[\s()\-]*(?=\+)/, "").replace(/^\+7[\s()\-]*8(?=[\s()\-]*\d{3}[\s()\-]*\d{3}[\s()\-]*\d{2}[\s()\-]*\d{2}$)/, "+7 ");
      if (v !== ph.value) ph.value = v;
    }
    ph.addEventListener("input", fix);
    ph.addEventListener("change", fix);
  }
  var K = null; /* настройки кабинета клиента */

  /* ============================================================
     1. АНКЕТА ПИТОМЦА: карточки сменяют друг друга (колода), свайп, проверка, отправка без перезагрузки
     ============================================================ */
  function segInd(seg) {
    var i = seg.querySelector(".pgw-segi"), c = seg.querySelector("input:checked");
    if (!i) return;
    if (!c) { i.style.opacity = "0"; return; }
    var l = c.parentNode;
    i.style.opacity = "1";
    i.style.width = l.offsetWidth + "px";
    i.style.transform = "translateX(" + l.offsetLeft + "px)";
  }
  function initWizard(f) {
    if (!f || f._w) return;
    f._w = 1;
    var cards = qa(".pgw-card", f), n = cards.length, i = 0, busy = false;
    var stack = $(".pgw-stack", f), dots = qa(".pgw-dots i", f), cnt = $(".pgw-cnt b", f);
    var back = $('[data-w="back"]', f), next = $('[data-w="next"]', f), err = $(".pgw-err", f), ok = $(".pgw-ok", f);
    var mode = f.getAttribute("data-mode");
    function val(nm) { var el = f.elements[nm]; if (!el) return ""; if (el.length && !el.tagName) { for (var k = 0; k < el.length; k++) if (el[k].checked) return el[k].value; return ""; } return String(el.value || "").trim(); }
    function sp() { return val("f_species"); }
    function sync() { f.classList.toggle("oth", sp() === "other"); qa(".pgw-seg", f).forEach(segInd); height(); }
    function height() {
      var c = cards[i];
      if (!c || !stack) return;
      stack.style.setProperty("--pgw-h", c.offsetHeight + "px");
      stack.style.height = (c.offsetHeight + 34) + "px";
    }
    function layout() {
      cards.forEach(function (c, k) {
        var d = k - i;
        c.classList.remove("on", "prev", "n1", "n2", "n3");
        c.classList.add(d === 0 ? "on" : d < 0 ? "prev" : d === 1 ? "n1" : d === 2 ? "n2" : "n3");
        c.setAttribute("aria-hidden", d === 0 ? "false" : "true");
        if ("inert" in c) c.inert = d !== 0;
      });
      dots.forEach(function (d, k) { d.classList.toggle("on", k === i); d.classList.toggle("done", k < i); });
      if (cnt) cnt.textContent = i + 1;
      f.classList.toggle("first", i === 0);
      f.classList.toggle("last", i === n - 1);
      if (i === n - 1) summary();
      setTimeout(function () { qa(".pgw-seg", cards[i]).forEach(segInd); height(); }, 0);
    }
    function clearErr() { err.hidden = true; qa(".bad", f).forEach(function (x) { x.classList.remove("bad"); }); }
    function fail(msg, field) {
      clearErr();
      err.textContent = msg; err.hidden = false;
      var el = field ? f.elements[field] : null;
      if (el && el.length && !el.tagName) el = el[0];
      if (el) {
        var card = el.closest(".pgw-card"), k = cards.indexOf(card);
        if (k > -1 && k !== i) { i = k; layout(); }
        var box = el.closest(".pgw-f") || el.closest(".pgw-consent") || el.closest(".pgw-photo");
        if (box) box.classList.add("bad");
      }
      if (!reduce) { next.classList.remove("shake"); void next.offsetWidth; next.classList.add("shake"); }
    }
    function check(k) {
      var key = cards[k].getAttribute("data-k");
      if (key === "pet") {
        if (!val("title")) return fail(T("Укажите кличку питомца."), "title"), false;
        if (!sp()) return fail(T("Выберите, кто у вас."), "f_species"), false;
        if (sp() === "other" && !val("f_species_other")) return fail(T("Напишите, какое это животное."), "f_species_other"), false;
      }
      if (key === "owner" && mode !== "client") {
        if (val("f_owner").length < 2) return fail(T("Укажите имя."), "f_owner"), false;
        if (val("f_phone").replace(/\D/g, "").length < 10) return fail(T("Проверьте номер телефона."), "f_phone"), false;
      }
      if (key === "final" && f.elements.consent && !f.elements.consent.checked) return fail(T("Нужно согласие на обработку данных."), "consent"), false;
      return true;
    }
    function go(d) {
      if (busy) return;
      clearErr();
      if (d > 0 && !check(i)) return;
      if (d > 0 && i === n - 1) { send(); return; }
      var t = Math.max(0, Math.min(n - 1, i + d));
      if (t === i) return;
      i = t;
      layout();
      var r = f.getBoundingClientRect();
      if (r.top < 0 && !f.closest(".pg-sheet,.pgk-sheet")) f.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
      var sc = f.closest(".pg-sheet-sc,.pgk-sc");
      if (sc) sc.scrollTo({ top: 0, behavior: reduce ? "auto" : "smooth" });
    }
    function summary() {
      var box = $(".pgw-sum", f);
      if (!box) return;
      var rows = [["Кличка", val("title")], ["Кто", sp() === "other" ? val("f_species_other") : (($('input[name="f_species"]:checked', f) || {}).parentNode || { textContent: "" }).textContent], ["Порода", val("f_breed")], ["Возраст", val("f_age")]];
      if (mode !== "client") rows.push(["Хозяин", val("f_owner")], ["Телефон", val("f_phone")]);
      var filled = 0, total = 0;
      qa("input[type=text],input[type=tel],textarea", f).forEach(function (x) { if (x.name === "pgr_web") return; total++; if (String(x.value).trim()) filled++; });
      var ph = $(".pgw-ph", f), bg = ph ? ph.style.backgroundImage : "";
      var h = '<div class="pgw-sum-h">' + (bg ? '<span class="pgw-sum-av" style="background-image:' + esc(bg).replace(/&quot;/g, "'") + '"></span>' : '<span class="pgw-sum-av e">' + esc((val("title") || "?").charAt(0).toUpperCase()) + "</span>") + "<div><b>" + esc(val("title") || "—") + "</b><small>" + esc(T("Заполнено")) + " " + filled + " / " + total + '</small></div><i class="pgw-ring" style="--p:' + Math.round(filled / Math.max(1, total) * 100) + '"></i></div><dl>';
      rows.forEach(function (r) { h += "<dt>" + esc(T(r[0])) + "</dt><dd>" + (r[1] ? esc(r[1]) : '<span class="mu">' + esc(T("не указано")) + "</span>") + "</dd>"; });
      box.innerHTML = h + "</dl>";
    }
    function send() {
      busy = true; next.classList.add("busy"); next.disabled = true;
      var fd = new FormData(f), file = f.elements.avatar && f.elements.avatar.files && f.elements.avatar.files[0];
      var bad = T("Не получилось отправить — проверьте интернет и попробуйте ещё раз") + (C.phone_fmt ? T(" или позвоните нам: ") + C.phone_fmt : "") + ".";
      (file ? shrink(file, 1280) : Promise.resolve(null)).then(function (b) {
        if (b) { fd.delete("avatar"); fd.append("avatar", b, "pet.jpg"); }
        return fetch(f.getAttribute("action") || C.ajax || "/wp-admin/admin-ajax.php", { method: "POST", body: fd, credentials: "same-origin" });
      }).then(json).then(function (j) {
        busy = false; next.classList.remove("busy"); next.disabled = false;
        if (j && j.ok) done(); else fail((j && j.msg) || bad, j && j.field);
      }).catch(function (e) {
        busy = false; next.classList.remove("busy"); next.disabled = false;
        fail(e && e.st ? T("Сайт ответил ошибкой (") + e.st + T("). Попробуйте ещё раз") : bad);
      });
    }
    function done() {
      f.classList.add("sent-out");
      setTimeout(function () {
        qa(".pgw-head,.pgw-stack,.pgw-nav,.pgw-skip,.pgw-err", f).forEach(function (x) { x.hidden = true; });
        ok.hidden = false;
        f.classList.remove("sent-out"); f.classList.add("sent");
        var sc = f.closest(".pg-sheet-sc,.pgk-sc");
        if (sc) sc.scrollTo({ top: 0 }); else if (ok.scrollIntoView) ok.scrollIntoView({ block: "center", behavior: reduce ? "auto" : "smooth" });
      }, reduce ? 0 : 380);
    }
    f.addEventListener("click", function (e) {
      var b = e.target.closest("[data-w]");
      if (!b) return;
      var w = b.getAttribute("data-w");
      if (w === "next") go(1);
      else if (w === "back") go(-1);
      else if (w === "again") {
        f.reset(); i = 0; clearErr();
        var ph = $(".pgw-ph", f); if (ph) { ph.style.backgroundImage = ""; ph.parentNode.classList.remove("has"); }
        ok.hidden = true; f.classList.remove("sent");
        qa(".pgw-head,.pgw-stack,.pgw-nav,.pgw-skip", f).forEach(function (x) { x.hidden = false; });
        layout(); sync();
      } else if (w === "reload") {
        try { sessionStorage.setItem("pgk_tab", "home"); } catch (x) {}
        location.reload();
      }
    });
    f.addEventListener("change", function (e) {
      sync();
      var t = e.target;
      if (t.name === "avatar" && t.files && t.files[0]) {
        var ph = $(".pgw-ph", f), u = URL.createObjectURL(t.files[0]);
        ph.style.backgroundImage = "url(" + u + ")"; ph.parentNode.classList.add("has");
        ph.parentNode.classList.remove("bad");
      }
    });
    f.addEventListener("input", function (e) { var l = e.target.closest(".bad"); if (l) l.classList.remove("bad"); if (e.target.tagName === "TEXTAREA") height(); });
    f.addEventListener("keydown", function (e) {
      if (e.key === "Enter" && e.target.tagName === "INPUT" && e.target.type !== "checkbox") { e.preventDefault(); go(1); }
    });
    f.addEventListener("submit", function (e) { e.preventDefault(); go(1); });
    /* свайп карточки: влево — дальше, вправо — назад */
    var sx = 0, sy = 0, tracking = false;
    stack.addEventListener("touchstart", function (e) {
      if (e.target.closest("input,textarea,label.pgw-photo,.pgw-seg")) { tracking = false; return; }
      tracking = true; sx = e.touches[0].clientX; sy = e.touches[0].clientY;
    }, { passive: true });
    stack.addEventListener("touchend", function (e) {
      if (!tracking) return;
      tracking = false;
      var dx = e.changedTouches[0].clientX - sx, dy = e.changedTouches[0].clientY - sy;
      if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) go(dx < 0 ? 1 : -1);
    }, { passive: true });
    phoneMask(f.elements.f_phone);
    W.addEventListener("resize", function () { qa(".pgw-seg", f).forEach(segInd); height(); });
    layout(); sync();
    /* лист открыли — пересчитать размеры (пока он был скрыт, размеров не было) */
    if ("ResizeObserver" in W) { var ro = new ResizeObserver(function () { height(); qa(".pgw-seg", cards[i]).forEach(segInd); }); cards.forEach(function (c) { ro.observe(c); }); }
  }
  function scanWizards(root) { qa("form.pgw", root || D).forEach(initWizard); }

  /* заявка из кнопки услуги: вид питомца подставляется сам */
  D.addEventListener("click", function (e) {
    var b = e.target.closest && e.target.closest("[data-sheet][data-species]");
    if (!b) return;
    var s = D.getElementById("pg-sheet-" + b.getAttribute("data-sheet"));
    var r = s && s.querySelector('input[name="species"][value="' + b.getAttribute("data-species") + '"]');
    if (r) { r.checked = true; r.dispatchEvent(new Event("change", { bubbles: true })); }
  });

  /* ============================================================
     2. ЯЗЫК: подсветка-«капля» переезжает к выбранному языку, страница плавно растворяется и проявляется
     ============================================================ */
  function langInd(box) {
    if (!box) return;
    var ind = box.querySelector(".pg-lang-ind");
    if (!ind) { ind = D.createElement("i"); ind.className = "pg-lang-ind"; ind.setAttribute("aria-hidden", "true"); box.insertBefore(ind, box.firstChild); }
    var a = box.querySelector("a.pg-lang-to") || box.querySelector("a.on");
    if (!a || !a.offsetWidth || getComputedStyle(a).display === "none") { ind.style.opacity = "0"; return; }
    ind.style.opacity = "1";
    ind.style.width = a.offsetWidth + "px";
    ind.style.height = a.offsetHeight + "px";
    ind.style.transform = "translate(" + a.offsetLeft + "px," + a.offsetTop + "px)";
  }
  function langAll() { qa(".pg-langs").forEach(langInd); }
  D.addEventListener("click", function (e) {
    if (e.defaultPrevented) return;
    var a = e.target.closest && e.target.closest(".pg-langs a[data-lang]");
    if (!a || a.classList.contains("on") || e.metaKey || e.ctrlKey) return;
    e.preventDefault();
    var box = a.closest(".pg-langs");
    qa("a", box).forEach(function (x) { x.classList.remove("pg-lang-to"); });
    a.classList.add("pg-lang-to");
    box.classList.add("pg-lang-moving");
    langInd(box);
    try { sessionStorage.setItem("pg_lang_anim", "1"); localStorage.setItem("pg_lang", a.getAttribute("data-lang")); localStorage.setItem("pgkb_lang", a.getAttribute("data-lang")); } catch (x) {}
    var href = a.href;
    if (reduce) { location.href = href; return; }
    setTimeout(function () { D.documentElement.classList.add("pg-lang-out"); }, 160);
    setTimeout(function () { location.href = href; }, 520);
  });
  (function () {
    var on = false;
    try { on = sessionStorage.getItem("pg_lang_anim") === "1"; sessionStorage.removeItem("pg_lang_anim"); } catch (x) {}
    if (on && !reduce) {
      D.documentElement.classList.add("pg-lang-in");
      setTimeout(function () { D.documentElement.classList.remove("pg-lang-in"); }, 900);
    }
    W.addEventListener("pageshow", function () { D.documentElement.classList.remove("pg-lang-out"); qa(".pg-lang-to").forEach(function (x) { x.classList.remove("pg-lang-to"); }); langAll(); });
  })();
  W.addEventListener("resize", langAll);

  /* ============================================================
     3. НИЧЕГО НЕ ВЫДЕЛЯЕТСЯ: долгое нажатие на текст и фото не открывает выделение и меню
     (в полях ввода выделение работает как обычно)
     ============================================================ */
  function editable(t) { return t && t.closest && t.closest("input,textarea,[contenteditable]"); }
  D.addEventListener("contextmenu", function (e) { if (!editable(e.target)) e.preventDefault(); });
  D.addEventListener("copy", function (e) { if (!editable(D.activeElement) && !editable(e.target)) e.preventDefault(); });
  D.addEventListener("cut", function (e) { if (!editable(e.target)) e.preventDefault(); });
  /* сайт не масштабируется щипком (iPhone) — карта масштабируется как обычно */
  ["gesturestart", "gesturechange"].forEach(function (ev) { D.addEventListener(ev, function (e) { e.preventDefault(); }, { passive: false }); });
  D.addEventListener("touchmove", function (e) { if (e.touches && e.touches.length > 1 && !(e.target.closest && e.target.closest(".pgk-map,.gm-style,.leaflet-container"))) e.preventDefault(); }, { passive: false });
  D.addEventListener("dragstart", function (e) { if (e.target.tagName === "IMG" && e.target.closest(".pg,.pg-layer")) e.preventDefault(); });
  D.addEventListener("selectstart", function (e) {
    var t = e.target.nodeType === 3 ? e.target.parentNode : e.target;
    if (t && t.closest && t.closest(".pg,.pg-layer") && !t.closest("input,textarea,[contenteditable],.pg-sel-ok")) e.preventDefault();
  });

  /* ============================================================
     4. КАБИНЕТ КЛИЕНТА
     ============================================================ */
  var CL = { box: null, tab: "home", timer: 0, chatT: 0, lastMsg: 0, chatOpen: false };
  function svg(d, cls) { return '<svg class="' + (cls || "pg-i") + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + "</svg>"; }
  var ICON = {
    route: svg('<circle cx="6" cy="18.5" r="2"/><circle cx="18" cy="5.5" r="2"/><path d="M8 18.5h8a3.3 3.3 0 0 0 0-6.6H8a3.3 3.3 0 0 1 0-6.6h8"/>'),
    star: svg('<path d="M12 3.8l2.5 5.2 5.7.8-4.1 4 1 5.7L12 16.8l-5.1 2.7 1-5.7-4.1-4 5.7-.8z"/>'),
    starOn: svg('<path d="M12 3.8l2.5 5.2 5.7.8-4.1 4 1 5.7L12 16.8l-5.1 2.7 1-5.7-4.1-4 5.7-.8z"/>', "pg-i on")
  };

  /* --- тема кабинета: авто / светлая / тёмная --- */
  function themeGet() { try { return localStorage.getItem("pgk_theme") || "auto"; } catch (e) { return "auto"; } }
  var mq = W.matchMedia ? matchMedia("(prefers-color-scheme: dark)") : null;
  function themeApply() {
    var t = themeGet(), on = t === "dark" || (t === "auto" && mq && mq.matches);
    D.documentElement.classList.toggle("pgk-dark", !!on && !!D.getElementById("pgk"));
    var m = $('meta[name="theme-color"]'); if (m) m.setAttribute("content", on ? "#0c1211" : "#FBF9E8");
    var seg = $(".pgk-theme"), cur = null;
    if (seg) {
      qa("[data-k-theme]", seg).forEach(function (b) { var x = b.getAttribute("data-k-theme") === t; b.classList.toggle("on", x); if (x) cur = b; });
      var i = $(".pgk-segi", seg); if (i && cur && cur.offsetWidth) { i.style.width = cur.offsetWidth + "px"; i.style.transform = "translateX(" + cur.offsetLeft + "px)"; }
    }
    MapK.theme();
  }
  if (mq && mq.addEventListener) mq.addEventListener("change", function () { if (K) themeApply(); });
  function post(data, files) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    if (files) Object.keys(files).forEach(function (k) { fd.append(k, files[k], "photo.jpg"); });
    return fetch((K && K.aj) || C.ajax || "/wp-admin/admin-ajax.php", { method: "POST", body: fd, credentials: "same-origin" }).then(json);
  }
  function api(op, data, files) {
    var d = data || {};
    d.action = "petgo_cl"; d.op = op; d.n = K.n; d.lang = K.lang || "ru";
    return post(d, files).then(function (j) { if (j && j.auth) { location.reload(); } return j; });
  }
  var toastT = 0;
  function toast(t, bad) {
    var el = $("[data-k-toast]") || (function () { var x = D.createElement("div"); x.className = "pgk-toast"; x.setAttribute("data-k-toast", ""); (D.getElementById("pg-layer") || D.body).appendChild(x); return x; })();
    el.textContent = t; el.classList.toggle("bad", !!bad); el.classList.add("on");
    clearTimeout(toastT); toastT = setTimeout(function () { el.classList.remove("on"); }, 2600);
  }

  /* --- листы снизу: открываются друг над другом (страница питомца → анкета, заказ, отзыв) --- */
  var stack = [];
  function kOpen(id) {
    var s = D.getElementById("pgk-" + id);
    if (!s) return null;
    if (stack.indexOf(s) > -1) return s;
    if (W.PGCloseAI) W.PGCloseAI();
    s.style.zIndex = String(9996 + stack.length);
    s.hidden = false; void s.offsetWidth; s.classList.add("on");
    D.documentElement.classList.add("pgk-lock");
    stack.push(s);
    return s;
  }
  function kClose(all) {
    do {
      var s = stack.pop();
      if (!s) break;
      s.classList.remove("on");
      var inn = $(".pgk-sin", s); if (inn) inn.style.transform = "";
      if (s.id === "pgk-chat") { CL.chatOpen = false; clearInterval(CL.chatT); }
      if (s.id === "pgk-review" && CL.rvAuto) { var o = $("[data-k-rvform]", s); if (o && o.elements.order.value && !CL.rvSent) api("rv_skip", { order: o.elements.order.value }).catch(function () {}); CL.rvAuto = false; }
      (function (x) { setTimeout(function () { if (!x.classList.contains("on")) x.hidden = true; }, reduce ? 0 : 420); })(s);
    } while (all && stack.length);
    if (!stack.length) D.documentElement.classList.remove("pgk-lock");
  }
  function dragSheets(root) {
    qa("[data-k-drag]", root).forEach(function (h) {
      if (h._d) return; h._d = 1;
      var y0 = 0, dy = 0, inn = h.closest(".pgk-sin"), drag = false;
      h.addEventListener("touchstart", function (e) { if (e.target.closest("button")) return; drag = true; y0 = e.touches[0].clientY; dy = 0; inn.style.transition = "none"; }, { passive: true });
      h.addEventListener("touchmove", function (e) { if (!drag) return; dy = Math.max(0, e.touches[0].clientY - y0); inn.style.transform = "translateY(" + dy + "px)"; }, { passive: true });
      h.addEventListener("touchend", function () { if (!drag) return; drag = false; inn.style.transition = ""; if (dy > 110) kClose(); else inn.style.transform = ""; });
    });
  }

  /* --- формы входа, регистрации и «забыли пароль» (гость) и профиль (клиент) --- */
  function formErr(f, msg, field) {
    var er = $(".pgk-err", f);
    qa(".bad", f).forEach(function (x) { x.classList.remove("bad"); });
    if (er) { er.textContent = msg; er.hidden = !msg; }
    var el = field && f.elements[field];
    if (el) { var b = el.closest(".pgk-f") || el.closest(".pgw-consent"); if (b) b.classList.add("bad"); try { el.focus({ preventScroll: true }); } catch (x) {} }
  }
  function busyBtn(f, on) { var b = $(".pgk-go", f); if (b) { b.classList.toggle("busy", on); b.disabled = on; } }
  D.addEventListener("submit", function (e) {
    var f = e.target;
    if (!f.matches) return;
    if (f.matches(".pgk-form[data-op]")) {
      e.preventDefault();
      formErr(f, "");
      busyBtn(f, true);
      var fd = new FormData(f);
      fd.append("action", f.getAttribute("data-op"));
      fetch(C.ajax || "/wp-admin/admin-ajax.php", { method: "POST", body: fd, credentials: "same-origin" }).then(json).then(function (j) {
        busyBtn(f, false);
        if (!j.ok) { formErr(f, j.msg || "", j.field); return; }
        if (j.go) { location.href = j.go; return; }
        if (j.wa) {
          var dn = f.parentNode.querySelector(".pgk-done");
          if (dn) { f.hidden = true; dn.hidden = false; var w = $("[data-wa]", dn); if (w) w.href = j.wa; }
        }
      }).catch(function () { busyBtn(f, false); formErr(f, T("Не получилось — проверьте интернет.")); });
      return;
    }
    if (f.matches(".pgk-form[data-k-op]") && K) {
      e.preventDefault();
      formErr(f, ""); busyBtn(f, true);
      var d = {};
      qa("input,textarea,select", f).forEach(function (x) { if (x.name) d[x.name] = x.value; });
      api(f.getAttribute("data-k-op"), d).then(function (j) {
        busyBtn(f, false);
        if (!j.ok) { formErr(f, j.msg || "", j.field); return; }
        if (j.go) { location.href = j.go; return; }
        toast(j.msg || T("Сохранено"));
        if (f.getAttribute("data-k-op") === "pass") f.reset();
      }).catch(function () { busyBtn(f, false); formErr(f, T("Не получилось — проверьте интернет.")); });
      return;
    }
    if (f.matches("[data-k-rvform]") && K) {
      e.preventDefault();
      formErr(f, ""); busyBtn(f, true);
      var r = $('input[name="rating"]:checked', f);
      api("review", { order: f.elements.order.value, rating: r ? r.value : 5, text: f.elements.text.value }).then(function (j) {
        busyBtn(f, false);
        if (!j.ok) { formErr(f, j.msg || ""); return; }
        var b = $('[data-k-review="' + f.elements.order.value + '"]');
        if (b) { var s = +(r ? r.value : 5), st = ""; for (var i = 1; i <= 5; i++) st += i <= s ? ICON.starOn : ICON.star; b.outerHTML = '<div class="pgk-myrv">' + st + "</div>"; }
        CL.rvSent = true; f.reset(); kClose(); CL.rvSent = false; toast(j.msg || T("Отправлено"));
      }).catch(function () { busyBtn(f, false); formErr(f, T("Не получилось — проверьте интернет.")); });
      return;
    }
    if (f.matches("[data-k-send]") && K) {
      e.preventDefault();
      sendMsg(f);
    }
  });

  /* вход / регистрация: переключатель с «каплей» */
  function authSeg(box, to) {
    var seg = $(".pgk-seg", box);
    if (!seg) return;
    var btn = $('[data-auth="' + to + '"]', seg), ind = $(".pgk-segi", seg);
    qa("[data-auth]", seg).forEach(function (b) { var on = b === btn; b.classList.toggle("on", on); b.setAttribute("aria-selected", on ? "true" : "false"); });
    if (ind && btn) { ind.style.width = btn.offsetWidth + "px"; ind.style.transform = "translateX(" + btn.offsetLeft + "px)"; }
    qa("[data-auth-p]", box).forEach(function (p) {
      var on = p.getAttribute("data-auth-p") === to;
      if (on) { p.hidden = false; void p.offsetWidth; p.classList.add("on"); }
      else { p.classList.remove("on"); }
    });
    var pages = $(".pgk-auth-pages", box), cur = $('[data-auth-p="' + to + '"]', box);
    if (pages && cur) pages.style.height = cur.offsetHeight + "px";
  }

  /* --- вкладки кабинета --- */
  var TABS = ["home", "map", "walks", "acc"];
  function lens() {
    var dock = $(".pgk-dock"), l = dock && $(".pgk-lens", dock), a = dock && $("[data-k-tab].on", dock);
    if (!l) return;
    if (!a) { l.classList.remove("on"); return; }
    l.style.width = a.offsetWidth + "px"; l.style.height = a.offsetHeight + "px";
    l.style.transform = "translate(" + a.offsetLeft + "px," + a.offsetTop + "px)";
    l.classList.add("on");
  }
  function tab(to, opts) {
    if (!CL.box || TABS.indexOf(to) < 0) return;
    opts = opts || {};
    var from = CL.tab, dir = TABS.indexOf(to) - TABS.indexOf(from);
    CL.tab = to;
    CL.box.setAttribute("data-tab", to);
    qa(".pgk-pane", CL.box).forEach(function (p) {
      var on = p.getAttribute("data-pane") === to;
      p.classList.remove("from-l", "from-r");
      if (on && from !== to && !reduce) p.classList.add(dir < 0 ? "from-l" : "from-r");
      p.classList.toggle("on", on);
    });
    qa(".pgk-dock [data-k-tab]").forEach(function (b) { b.classList.toggle("on", b.getAttribute("data-k-tab") === to); });
    lens();
    try { history.replaceState(history.state, "", "#" + to); sessionStorage.setItem("pgk_tab", to); } catch (x) {}
    if (!opts.keep) W.scrollTo({ top: 0, behavior: reduce || opts.quiet ? "auto" : "smooth" });
    if (to === "map") MapK.show(opts);
    if (to === "acc") setTimeout(themeApply, 30);
    live();
  }

  /* --- анкета питомца в листе: одна анкета, данные питомца подставляются --- */
  function petSheet(pid) {
    var tpl = D.getElementById("pgk-tpl-pet");
    var s = kOpen("pet");
    if (!s || !tpl) return;
    var body = $("[data-k-pet-body]", s), tt = $("[data-k-sht]", s), pre = pid && K.pre ? K.pre[pid] : null, pet = pid ? (K.pets || []).filter(function (p) { return p.id === pid; })[0] : null;
    if (tt) tt.textContent = pid ? T("Анкета питомца") + (pre ? " · " + pre.title : "") : T("Новый питомец");
    body.innerHTML = "";
    body.appendChild(D.importNode(tpl.content, true));
    var f = $("form.pgw", body);
    if (pid && f) {
      f.elements.pet_id.value = pid;
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
    scanWizards(body);
  }

  /* --- запись блога --- */
  function postSheet(b) {
    var s = kOpen("post");
    if (!s) return;
    var t = $("template", b), box = $("[data-k-post-body]", s), tt = $("[data-k-sht]", s);
    box.innerHTML = ""; if (t) box.appendChild(D.importNode(t.content, true));
    if (tt) tt.textContent = T("Блог");
    var sc = $(".pgk-sc", s); if (sc) sc.scrollTop = 0;
  }

  /* --- страница питомца: фото, где он, прогулки и отчёты --- */
  function petPage(pid) {
    var tpl = D.getElementById("pgk-pp-" + pid), s = kOpen("petp");
    if (!s || !tpl) return;
    var body = $("[data-k-pp-body]", s), tt = $("[data-k-sht]", s), p = (K.pets || []).filter(function (x) { return x.id === pid; })[0];
    body.innerHTML = ""; body.appendChild(D.importNode(tpl.content, true));
    if (tt) tt.textContent = p ? p.name : "";
    var sc = $(".pgk-sc", s); if (sc) sc.scrollTop = 0;
  }

  /* --- заказ: питомец, услуга, день, время, длительность, няня (занятые видны сразу) --- */
  var BK = { pet: 0, kind: "walk", date: "", time: "", dur: 60, nanny: 0, busy: [] };
  function chip(v, html, on, cls, dis) { return '<button type="button" class="pg-b pgk-c' + (cls ? " " + cls : "") + (on ? " on" : "") + '" data-v="' + esc(v) + '"' + (dis ? " disabled" : "") + ">" + html + "</button>"; }
  function pad(n) { return (n < 10 ? "0" : "") + n; }
  function ymd(d) { return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()); }
  function almaty() { var d = new Date(Date.now() + (5 * 60 + new Date().getTimezoneOffset()) * 60000); return d; }
  function bookRender() {
    var f = $("[data-k-bookform]"); if (!f) return;
    var sv = K.svcs[BK.kind] || K.svcs.walk, now = almaty(), h = "";
    if (sv.durs.indexOf(BK.dur) < 0) BK.dur = sv.dur;
    (K.pets || []).forEach(function (p) { h += chip(p.id, '<span class="pgk-av xs">' + (p.av ? '<img src="' + esc(p.av) + '" alt="">' : "<b>" + esc(p.name.charAt(0)) + "</b>") + "</span>" + esc(p.name), BK.pet === p.id); });
    $('[data-b="pet"]', f).innerHTML = h;
    h = ""; Object.keys(K.svcs).forEach(function (k) { h += chip(k, esc(K.svcs[k].t), BK.kind === k); });
    $('[data-b="kind"]', f).innerHTML = h;
    var WD = ["вс", "пн", "вт", "ср", "чт", "пт", "сб"];
    h = "";
    for (var i = 0; i < 14; i++) {
      var d = new Date(now.getFullYear(), now.getMonth(), now.getDate() + i), v = ymd(d);
      h += chip(v, "<small>" + esc(i === 0 ? T("Сегодня") : i === 1 ? T("Завтра") : T(WD[d.getDay()])) + "</small><b>" + d.getDate() + "</b>", BK.date === v, "pgk-day-c");
    }
    $('[data-b="date"]', f).innerHTML = h;
    h = "";
    for (var m = 7 * 60; m <= 21 * 60 + 30; m += 30) {
      var t = pad(Math.floor(m / 60)) + ":" + pad(m % 60), past = BK.date === ymd(now) && m < now.getHours() * 60 + now.getMinutes() + 45;
      h += chip(t, t, BK.time === t, "", past);
    }
    $('[data-b="time"]', f).innerHTML = h;
    h = ""; sv.durs.forEach(function (x) { h += chip(x, x + " " + esc(T("мин")), BK.dur === x); });
    $('[data-b="dur"]', f).innerHTML = h;
    h = chip(0, '<span class="pgk-av xs pgk-any">✦</span>' + esc(T("Любая")), !BK.nanny);
    (K.nannies || []).forEach(function (n) {
      var busy = BK.busy.indexOf(n.id) > -1;
      h += chip(n.id, '<span class="pgk-av xs">' + (n.img ? '<img src="' + esc(n.img) + '" alt="">' : "<b>" + esc(n.name.charAt(0)) + "</b>") + "</span>" + esc(n.name) + (busy ? " <em>" + esc(T("Занята")) + "</em>" : ""), BK.nanny === n.id, busy ? "busy" : "", busy);
    });
    $('[data-b="nanny"]', f).innerHTML = h;
    var p = (K.pets || []).filter(function (x) { return x.id === BK.pet; })[0], price = 0, pr = K.price || {};
    if (BK.kind === "walk") price = Math.round((pr.walk || 0) * BK.dur / 60);
    if (BK.kind === "week") price = p && p.sp === "cat" ? pr.week_cat : pr.week_dog;
    $('[data-b="sum"]', f).innerHTML = price ? esc(T("Примерно")) + " <b>" + String(price).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " ₸</b>" + (BK.kind === "week" ? " " + esc(T("за неделю")) : "") : esc(T("Цену подтвердит менеджер"));
  }
  function bookBusy() {
    if (!BK.date || !BK.time) return;
    api("slots", { kind: BK.kind, date: BK.date, time: BK.time, dur: BK.dur }).then(function (j) { if (j.ok) { BK.busy = j.busy || []; if (BK.busy.indexOf(BK.nanny) > -1) BK.nanny = 0; bookRender(); } }).catch(function () {});
  }
  function book(kind, pid) {
    var s = kOpen("book"); if (!s) return;
    var f = $("[data-k-bookform]", s), dn = $(".pgk-done", s);
    f.hidden = false; dn.hidden = true; formErr(f, "");
    BK.kind = K.svcs[kind] ? kind : "walk"; BK.pet = pid || (K.pets && K.pets.length === 1 ? K.pets[0].id : BK.pet); BK.busy = []; BK.nanny = 0;
    if (!BK.date) BK.date = ymd(almaty());
    bookRender(); bookBusy();
  }
  D.addEventListener("click", function (e) {
    var c = e.target.closest && e.target.closest("[data-k-bookform] .pgk-c");
    if (!c || c.disabled) return;
    var b = c.parentNode.getAttribute("data-b"), v = c.getAttribute("data-v");
    if (b === "pet" || b === "dur" || b === "nanny") BK[b] = +v; else BK[b] = v;
    bookRender();
    if (b === "date" || b === "time" || b === "dur" || b === "kind") bookBusy();
  });
  D.addEventListener("submit", function (e) {
    var f = e.target;
    if (!f.matches || !f.matches("[data-k-bookform]")) return;
    e.preventDefault();
    if (!BK.pet) { formErr(f, T("Выберите питомца.")); return; }
    if (!BK.date || !BK.time) { formErr(f, T("Выберите день и время.")); return; }
    formErr(f, ""); busyBtn(f, true);
    api("book", { pet: BK.pet, kind: BK.kind, date: BK.date, time: BK.time, dur: BK.dur, nanny: BK.nanny, note: f.elements.note.value }).then(function (j) {
      busyBtn(f, false);
      if (!j.ok) { formErr(f, j.msg || ""); if (j.busy) bookBusy(); return; }
      f.hidden = true; $(".pgk-done", f.parentNode).hidden = false; f.reset(); BK.time = "";
    }).catch(function () { busyBtn(f, false); formErr(f, T("Не получилось — проверьте интернет.")); });
  });

  /* --- чат с менеджером --- */
  function msgHtml(m) {
    return '<div class="pgk-m' + (m.me ? " me" : "") + '" data-id="' + m.id + '">' + (!m.me ? '<span class="pgk-m-av">' + (m.av ? '<img src="' + esc(m.av) + '" alt="">' : "<b>" + esc((m.n || "P").charAt(0)) + "</b>") + "</span>" : "") +
      '<div class="pgk-m-b' + (m.k === "ai" ? " ai" : "") + '">' + (!m.me && m.n ? "<small>" + esc(m.n) + "</small>" : "") + (m.p ? '<button type="button" class="pg-b pgk-m-ph" data-k-photo="' + esc(m.p) + '"><img src="' + esc(m.p) + '" alt="" loading="lazy"></button>' : "") + (m.h ? "<p>" + m.h + "</p>" : "") +
      (m.k === "rv" && m.s ? '<button type="button" class="pg-b pgk-chip pgk-chip-acc" data-k-review="' + m.s + '">' + ICON.star + esc(T("Оценить няню")) + "</button>" : "") + "<time>" + esc(m.tm) + "</time></div></div>";
  }
  function loadMsgs(first) {
    var box = $("[data-k-msgs]");
    if (!box) return Promise.resolve();
    return api("msgs", { after: first ? 0 : CL.lastMsg }).then(function (j) {
      if (!j.ok || !j.msgs) return;
      if (first) qa(".pgk-m,.pgk-day", box).forEach(function (x) { x.remove(); });
      var day = box.getAttribute("data-day") || "";
      j.msgs.forEach(function (m) {
        if (box.querySelector('[data-id="' + m.id + '"]')) return;
        if (m.dl !== day) { day = m.dl; box.insertAdjacentHTML("beforeend", '<div class="pgk-day"><span>' + esc(day) + "</span></div>"); }
        box.insertAdjacentHTML("beforeend", msgHtml(m));
        CL.lastMsg = Math.max(CL.lastMsg, m.id);
      });
      box.setAttribute("data-day", day);
      if (j.msgs.length) box.scrollTop = box.scrollHeight;
      unread(0);
    });
  }
  function chat() {
    var s = kOpen("chat");
    if (!s) return;
    CL.chatOpen = true;
    loadMsgs(true).then(function () { var b = $("[data-k-msgs]"); if (b) b.scrollTop = b.scrollHeight; });
    clearInterval(CL.chatT);
    CL.chatT = setInterval(function () { if (!D.hidden && CL.chatOpen) loadMsgs(false); }, 4000);
    var ta = $("[data-k-send] textarea", s);
    if (ta && !("ontouchstart" in W)) setTimeout(function () { ta.focus(); }, 300);
  }
  function sendMsg(f) {
    var ta = f.elements.body, inp = f.elements.photo, file = inp && inp.files && inp.files[0], body = ta.value.trim();
    if (!body && !file) return;
    var btn = $(".pgk-send", f);
    btn.disabled = true;
    /* своё сообщение видно сразу, помощник «печатает» */
    var box = $("[data-k-msgs]"), mine = D.createElement("div");
    mine.className = "pgk-m me pgk-pending"; mine.innerHTML = '<div class="pgk-m-b"><p>' + esc(body).replace(/\n/g, "<br>") + "</p><time>…</time></div>";
    if (body && box) { box.appendChild(mine); box.scrollTop = box.scrollHeight; }
    var typing = null;
    if (K.ai && body && box) { typing = D.createElement("div"); typing.className = "pgk-typing"; typing.innerHTML = "<i></i><i></i><i></i><span>" + esc(T("Помощник PetGo")) + " " + esc(T("печатает…")) + "</span>"; setTimeout(function () { if (typing) { box.appendChild(typing); box.scrollTop = box.scrollHeight; } }, 500); }
    ta.value = ""; ta.style.height = "";
    (file ? shrink(file, 1600) : Promise.resolve(null)).then(function (b) {
      return api("send", { body: body }, b ? { photo: b } : null);
    }).then(function (j) {
      if (typing) { typing.remove(); typing = null; }
      mine.remove();
      btn.disabled = false;
      if (!j.ok) { toast(j.msg || T("Не получилось — проверьте интернет."), true); return; }
      ta.value = ""; ta.style.height = ""; if (inp) inp.value = ""; f.classList.remove("has-ph");
      loadMsgs(false);
    }).catch(function () { if (typing) { typing.remove(); typing = null; } mine.remove(); ta.value = body; btn.disabled = false; toast(T("Не получилось — проверьте интернет."), true); });
  }
  function unread(n) {
    qa("[data-k-unread]").forEach(function (b) { b.textContent = n; b.hidden = !n; });
  }

  /* --- живое состояние питомцев (статусы, баннер прогулки, непрочитанные) --- */
  function applyPets(pets) {
    if (!pets) return;
    /* место дома, найденное по адресу, не теряем при обновлении */
    (K.pets || []).forEach(function (o) { pets.forEach(function (p) { if (p.id === o.id) { if (!p.home && o.home) p.home = o.home; if (o._geo) p._geo = 1; } }); });
    K.pets = pets;
    var live = null;
    pets.forEach(function (p) {
      var el = $('[data-k-st="' + p.id + '"]');
      if (el) {
        el.className = "pg-b pgk-status st-" + p.st;
        el.querySelector("span").innerHTML = (p.live && p.hi ? "<em>" + esc(p.hi) + "</em> " : "") + esc(p.text) + (p.live && p.min ? ' <small>· ' + p.min + " " + esc(T("мин")) + "</small>" : "");
      }
      var av = $('[data-pet="' + p.id + '"] .pgk-pet-av');
      if (av) av.classList.toggle("live", !!p.live);
      if (p.live && !live) live = p;
    });
    var bar = $(".pgk-livebar");
    if (bar) {
      bar.hidden = !live;
      if (live) { $("[data-k-live-name]", bar).textContent = live.name; $("[data-k-live-text]", bar).textContent = live.text + (live.min ? " · " + live.min + " " + T("мин") : ""); }
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
      api("live").then(function (j) { if (j.ok) { applyPets(j.pets); unread(j.u || 0); } }).catch(function () {}).then(live);
    }, wait);
  }

  /* --- отзыв: из списка прогулок, из чата или сам после завершения --- */
  function reviewOpen(oid, name, av, auto) {
    var s = kOpen("review"); if (!s) return;
    var f = s.querySelector("form");
    f.elements.order.value = oid;
    CL.rvAuto = !!auto;
    var fr = $("[data-k-rv-for]", s);
    if (fr) fr.innerHTML = name ? (av ? '<span class="pgk-av sm"><img src="' + esc(av) + '" alt=""></span>' : "") + "<b>" + esc(T("Отзыв для няни %s").replace("%s", name)) + "</b>" : "";
  }

  /* --- лайтбокс фото --- */
  function lightbox(src, cap) {
    var lb = $("[data-k-lb]") || $(".pgk-lb");
    if (!lb) { lb = D.createElement("div"); lb.className = "pgk-lb"; lb.setAttribute("data-k-lb", ""); lb.innerHTML = '<button type="button" class="pg-b pgk-x" data-k-lbx>×</button><img alt=""><span></span>'; D.body.appendChild(lb); }
    $("img", lb).src = src; $("span", lb).textContent = cap || "";
    lb.hidden = false; void lb.offsetWidth; lb.classList.add("on");
  }
  function lbClose() { var lb = $(".pgk-lb.on"); if (lb) { lb.classList.remove("on"); setTimeout(function () { lb.hidden = true; }, 300); } }

  /* ============================================================
     5. КАРТА: где питомец сейчас, маршрут, все места, фото на карте.
     Без ключа — OpenStreetMap (Leaflet лежит в плагине, внешних скриптов не нужно), с ключом — Google Maps.
     Метки и линии одинаковые на обеих картах, цвета — PetGo, тёмная тема — тёмная карта.
     ============================================================ */
  function dark() { return D.documentElement.classList.contains("pgk-dark"); }
  function loadScript(src) {
    return new Promise(function (ok, no) { var s = D.createElement("script"); s.src = src; s.async = true; s.onload = ok; s.onerror = no; D.head.appendChild(s); });
  }
  function loadCss(href) { if ($('link[href="' + href + '"]')) return; var c = D.createElement("link"); c.rel = "stylesheet"; c.href = href; D.head.appendChild(c); }

  /* --- движок OpenStreetMap (Leaflet) --- */
  function EngineOSM() {
    var map, grp, Lf;
    return {
      init: function (el) {
        loadCss(K.leaflet.css);
        var p = (W.L && W.L.map) ? Promise.resolve() : loadScript(K.leaflet.js);
        return p.then(function () {
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
      fit: function (pts, pad) {
        if (pts.length === 1) map.setView(pts[0], 16, { animate: !reduce });
        else if (pts.length > 1) map.fitBounds(Lf.latLngBounds(pts), { paddingTopLeft: [pad[3], pad[0]], paddingBottomRight: [pad[1], pad[2]], maxZoom: 17, animate: !reduce });
      },
      resize: function () { map.invalidateSize(); },
      theme: function () {},
      alive: function () { return map && D.body.contains(map.getContainer()); }
    };
  }

  /* --- движок Google Maps (по ключу из приложения: Клиенты → Для клиентов) --- */
  var GSTYLE = {
    light: [
      { elementType: "geometry", stylers: [{ color: "#f4f2e6" }] }, { elementType: "labels.text.fill", stylers: [{ color: "#4d6f6a" }] }, { elementType: "labels.text.stroke", stylers: [{ color: "#fbf9e8" }] },
      { featureType: "poi", elementType: "labels", stylers: [{ visibility: "off" }] }, { featureType: "poi.park", elementType: "geometry", stylers: [{ color: "#d6e8d3" }] },
      { featureType: "road", elementType: "geometry", stylers: [{ color: "#ffffff" }] }, { featureType: "road.arterial", elementType: "geometry", stylers: [{ color: "#fdfcf5" }] },
      { featureType: "road.highway", elementType: "geometry", stylers: [{ color: "#efe6c9" }] }, { featureType: "transit", stylers: [{ visibility: "off" }] },
      { featureType: "water", elementType: "geometry", stylers: [{ color: "#bfdbd6" }] }, { featureType: "administrative", elementType: "geometry", stylers: [{ visibility: "off" }] }
    ],
    dark: [
      { elementType: "geometry", stylers: [{ color: "#141c1b" }] }, { elementType: "labels.text.fill", stylers: [{ color: "#8fa9a4" }] }, { elementType: "labels.text.stroke", stylers: [{ color: "#141c1b" }] },
      { featureType: "poi", elementType: "labels", stylers: [{ visibility: "off" }] }, { featureType: "poi.park", elementType: "geometry", stylers: [{ color: "#1b2b25" }] },
      { featureType: "road", elementType: "geometry", stylers: [{ color: "#24302e" }] }, { featureType: "road.highway", elementType: "geometry", stylers: [{ color: "#2e3b37" }] },
      { featureType: "transit", stylers: [{ visibility: "off" }] }, { featureType: "water", elementType: "geometry", stylers: [{ color: "#0d2a27" }] }, { featureType: "administrative", elementType: "geometry", stylers: [{ visibility: "off" }] }
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
          map = new g.Map(el, { center: { lat: 43.2389, lng: 76.8897 }, zoom: 12, disableDefaultUI: true, clickableIcons: false, gestureHandling: "greedy", keyboardShortcuts: false, backgroundColor: dark() ? "#141c1b" : "#f4f2e6", styles: GSTYLE[dark() ? "dark" : "light"] });
          /* своя метка: HTML поверх карты, как в OpenStreetMap */
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
      fit: function (pts, pad) {
        if (pts.length === 1) { map.setCenter({ lat: pts[0][0], lng: pts[0][1] }); map.setZoom(16); }
        else if (pts.length > 1) { var b = new g.LatLngBounds(); pts.forEach(function (p) { b.extend({ lat: p[0], lng: p[1] }); }); map.fitBounds(b, { top: pad[0], right: pad[1], bottom: pad[2], left: pad[3] }); }
      },
      resize: function () {},
      theme: function () { if (map) map.setOptions({ styles: GSTYLE[dark() ? "dark" : "light"], backgroundColor: dark() ? "#141c1b" : "#f4f2e6" }); },
      alive: function () { return map && D.body.contains(map.getDiv()); }
    };
  }

  var MapK = (function () {
    var E = null, hist = null, mode = "now", focus = 0, walk = 0, starting = null, fitted = false, pts = [];
    var COLORS = ["#0b7a6f", "#E58A2B", "#3F7CAC", "#C2557A", "#6E9F2E", "#8A5CC2"];
    function color(pid) { var ids = (K.pets || []).map(function (p) { return p.id; }), i = ids.indexOf(pid); return COLORS[(i < 0 ? 0 : i) % COLORS.length]; }
    function petOf(id) { return (K.pets || []).filter(function (p) { return p.id === id; })[0]; }
    function ago(ts) {
      if (!ts) return "";
      var s = Math.max(0, Math.round(Date.now() / 1000 - ts));
      return s < 45 ? T("только что") : Math.round(s / 60) + " " + T("мин") + " " + T("назад");
    }
    function avHtml(p, cls) { return '<span class="pgk-av ' + (cls || "sm") + '">' + (p.av ? '<img src="' + esc(p.av) + '" alt="">' : "<b>" + esc((p.name || "?").charAt(0)) + "</b>") + "</span>"; }
    function petMk(p, live) {
      var bub = live ? (p.hi ? "<em>" + esc(p.hi) + "</em> " : "") + esc(p.text) : esc(p.name);
      return '<div class="pgk-mk pgk-pm' + (live ? " live" : "") + '" style="--c:' + color(p.id) + '"><i class="pgk-pm-ring"></i>' + avHtml(p, "pgk-pm-av") + '<span class="pgk-pm-bub">' + bub + "</span></div>";
    }
    function photoMk(u) { return '<div class="pgk-mk pgk-phm"><img src="' + esc(u) + '" alt="" loading="lazy"></div>'; }
    function dotMk(c) { return '<i class="pgk-mk pgk-dot" style="--c:' + c + '"></i>'; }
    function card(html) { var c = $("[data-k-mcard]"); if (!c) return; c.hidden = !html; if (html) c.innerHTML = html; }
    function shown(pid) { return !focus || focus === pid; }
    function seg() {
      qa("[data-k-mpet]").forEach(function (b) { b.classList.toggle("on", +b.getAttribute("data-k-mpet") === focus); });
      var box = $(".pgk-mseg"), on = null;
      qa("[data-k-mmode]").forEach(function (b) { var x = b.getAttribute("data-k-mmode") === mode; b.classList.toggle("on", x); if (x) on = b; });
      var i = box && $(".pgk-segi", box);
      if (i && on) { i.style.width = on.offsetWidth + "px"; i.style.transform = "translateX(" + on.offsetLeft + "px)"; }
    }
    function liveCard(p) {
      var left = p.end ? Math.round((p.end - Date.now() / 1000) / 60) : 0, bits = [];
      if (p.min) bits.push(T("Прогулка") + " " + p.min + " " + T("мин"));
      if (left > 0) bits.push(T("ещё") + " " + left + " " + T("мин"));
      if (p.at) bits.push(T("обновлено") + " " + ago(p.at));
      return '<div class="pgk-mc">' + avHtml(p, "md") + '<div><b>' + esc(p.name) + ' <i class="pgk-live-dot"></i></b><span class="pgk-mc-st st-' + esc(p.st) + '">' + (p.hi ? "<em>" + esc(p.hi) + "</em> " : "") + esc(p.text) + "</span><small>" + esc(bits.join(" · ")) + "</small></div>" + (p.nav ? '<span class="pgk-av sm pgk-mc-n" title="' + esc(p.nanny || "") + '"><img src="' + esc(p.nav) + '" alt=""></span>' : "") + "</div>";
    }
    function draw() {
      if (!E) return;
      E.clear();
      pts = [];
      var pets = K.pets || [], any = false, ch = "", halo = dark() ? "#0c1211" : "#ffffff";
      seg();
      if (mode === "now") {
        /* каждый питомец на карте: гуляет — живая метка и маршрут; нет — последнее место прогулки или дом */
        var first = "";
        pets.forEach(function (p) {
          if (!shown(p.id)) return;
          var c = "";
          if (p.live && p.pos) {
            if (p.track && p.track.length > 1) {
              var line = p.track.map(function (t) { return [t[0], t[1]]; });
              E.line(line, { color: color(p.id), weight: 5, opacity: 1, halo: halo });
              E.html(line[0], dotMk(color(p.id)), {});
              pts = pts.concat(line);
            }
            (p.photos || []).forEach(function (ph) { if (ph.lat != null) E.html([ph.lat, ph.lng], photoMk(ph.u), { z: 200, click: function () { lightbox(ph.u, p.name + " · " + ph.t); } }); });
            E.html(p.pos, petMk(p, true), { z: 1000, click: function () { focus = p.id; fitted = false; draw(); } });
            pts.push(p.pos);
            c = liveCard(p);
          } else {
            var w = hist ? hist.walks.filter(function (x) { return x.pet === p.id; })[0] : null;
            if (w && w.p.length) {
              var last = w.p[w.p.length - 1];
              E.line(w.p.map(function (t) { return [t[0], t[1]]; }), { color: color(p.id), weight: 4, opacity: 0.45, dash: "1 9" });
              E.html([last[0], last[1]], petMk(p, false), { z: 900, click: function () { focus = p.id; fitted = false; draw(); } });
              pts.push([last[0], last[1]]);
              c = '<div class="pgk-mc">' + avHtml(p, "md") + "<div><b>" + esc(p.name) + '</b><span class="pgk-mc-st st-' + esc(p.st) + '">' + esc(p.text) + "</span><small>" + esc(T("Последнее место")) + " · " + esc(w.d) + "</small></div></div>";
            } else if (p.home) {
              E.html(p.home, petMk(p, false), { z: 900, click: function () { focus = p.id; fitted = false; draw(); } });
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
        starting = null;
      });
      return starting;
    }
    function show(opts) {
      opts = opts || {};
      if (opts.focus !== undefined) { focus = opts.focus; mode = "now"; fitted = false; }
      if (opts.walk) { walk = opts.walk; mode = "all"; fitted = false; }
      if (!D.getElementById("pgk-map")) return;
      start().then(function () {
        if (!E) return;
        homes();
        setTimeout(function () { E.resize(); seg(); }, 60);
        if (!hist) api("map").then(function (j) { if (j.ok) { hist = j.hist; applyPets(j.pets); homes(); draw(); } });
        draw();
      });
    }
    /* место дома по адресу питомца: находим один раз (OpenStreetMap) и запоминаем в кабинете */
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

  /* ---------- запуск кабинета (при открытии страницы и после перехода без перезагрузки) ---------- */
  function initCabinet() {
    var box = D.getElementById("pgk");
    var guest = $(".pgk-auth");
    if (guest && !guest._i) {
      guest._i = 1;
      setTimeout(function () { authSeg(guest, "in"); }, 0);
      qa('input[type="tel"]', guest.parentNode).forEach(phoneMask);
      qa('input[type="tel"]', D.getElementById("pgk-forgot") || D.createElement("i")).forEach(phoneMask);
      /* лист «Восстановить доступ» — поверх всего, как остальные листы */
      var fs = D.getElementById("pgk-forgot"), ly = D.getElementById("pg-layer");
      if (fs && ly && fs.parentNode !== ly) ly.appendChild(fs);
      W.addEventListener("resize", function () { var on = $(".pgk-seg .on", guest); if (on) authSeg(guest, on.getAttribute("data-auth")); });
    }
    dragSheets(D);
    if (!box || box._i) { CL.box = box; return; }
    box._i = 1;
    CL.box = box;
    try { K = JSON.parse(D.getElementById("pgk-cfg").textContent); } catch (e) { K = null; }
    if (!K) return;
    K.tr = K.tr || {};
    MapK.reset();
    D.documentElement.classList.add("pgk-on");
    themeApply();
    /* слой (нижнее меню сайта и т. п.) лежит вне страницы: листы кабинета переносим туда же, чтобы они были поверх всего */
    var layerEl = D.getElementById("pg-layer") || D.body;
    qa(".pgk-sheet,.pgk-lb,.pgk-toast", box).forEach(function (s) { layerEl.appendChild(s); });
    var dock = $(".pgk-dock", box); if (dock) layerEl.appendChild(dock);
    var t = (location.hash || "").replace("#", "");
    try { if (TABS.indexOf(t) < 0) t = sessionStorage.getItem("pgk_tab") || "home"; } catch (x) { t = "home"; }
    tab(TABS.indexOf(t) > -1 ? t : "home", { quiet: true, keep: true });
    setTimeout(lens, 50);
    W.addEventListener("resize", lens);
    live();
    /* прогулка завершилась — кабинет сам предлагает оценить няню (один раз; «Позже» — больше не спрашивает) */
    if (K.rv) {
      var seen = ""; try { seen = sessionStorage.getItem("pgk_rv") || ""; } catch (x) {}
      if (seen !== String(K.rv.o)) setTimeout(function () { try { sessionStorage.setItem("pgk_rv", String(K.rv.o)); } catch (x) {} if (!stack.length) reviewOpen(K.rv.o, K.rv.n, K.rv.av, true); }, 1400);
    }
  }
  /* уходим со страницы кабинета (переход без перезагрузки) — убираем его меню и листы */
  function leaveCabinet() {
    if (D.getElementById("pgk")) return;
    clearTimeout(CL.timer); clearInterval(CL.chatT);
    qa("#pg-layer > .pgk-dock, #pg-layer > .pgk-sheet, #pg-layer > .pgk-lb").forEach(function (x) { x.remove(); });
    D.documentElement.classList.remove("pgk-on", "pgk-lock", "pgk-dark");
    K = null; CL.box = null;
  }

  /* все нажатия кабинета — одним обработчиком */
  D.addEventListener("click", function (e) {
    var t = e.target, b;
    if (!t.closest) return;
    if ((b = t.closest("[data-auth]"))) { authSeg(b.closest(".pgk-auth"), b.getAttribute("data-auth")); return; }
    if ((b = t.closest("[data-eye]"))) { var inp = b.parentNode.querySelector("input"); if (inp) { inp.type = inp.type === "password" ? "text" : "password"; b.classList.toggle("on", inp.type === "text"); } return; }
    if ((b = t.closest("[data-k-open]"))) { e.preventDefault(); var id = b.getAttribute("data-k-open"); if (id === "chat") chat(); else kOpen(id); return; }
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
      tab(b.getAttribute("data-k-tab"), o);
      return;
    }
    if ((b = t.closest("[data-k-pet]"))) { e.preventDefault(); petSheet(+b.getAttribute("data-k-pet")); return; }
    if ((b = t.closest("[data-k-petpage]"))) { e.preventDefault(); petPage(+b.getAttribute("data-k-petpage")); return; }
    if ((b = t.closest("[data-k-book]"))) { e.preventDefault(); book(b.getAttribute("data-k-book"), +(b.getAttribute("data-k-bpet") || 0)); return; }
    if ((b = t.closest("[data-k-post]"))) { e.preventDefault(); postSheet(b); return; }
    if ((b = t.closest("[data-k-theme]"))) { try { localStorage.setItem("pgk_theme", b.getAttribute("data-k-theme")); } catch (x) {} themeApply(); return; }
    if ((b = t.closest("[data-k-mmode]"))) { MapK.mode(b.getAttribute("data-k-mmode")); return; }
    if ((b = t.closest("[data-k-mpet]"))) { MapK.focus(+b.getAttribute("data-k-mpet")); return; }
    if ((b = t.closest("[data-k-mfit]"))) { MapK.fit(); return; }
    if ((b = t.closest("[data-k-review]")) && !b.matches("form")) {
      var rid = +b.getAttribute("data-k-review"), nm = b.getAttribute("data-k-rv-name"), av = b.getAttribute("data-k-rv-av");
      if (!nm && K.rv && K.rv.o === rid) { nm = K.rv.n; av = K.rv.av; }
      reviewOpen(rid, nm || "", av || "", false);
      return;
    }
    if ((b = t.closest("[data-k-join]"))) {
      b.disabled = true;
      api("join", { id: b.getAttribute("data-k-join") }).then(function (j) { toast(j.msg || ""); if (j.ok) { b.classList.remove("pg-btn-primary"); b.classList.add("pg-btn-second"); } else b.disabled = false; }).catch(function () { b.disabled = false; toast(T("Не получилось — проверьте интернет."), true); });
      return;
    }
  });
  D.addEventListener("change", function (e) {
    var t = e.target;
    if (!K || !t.matches) return;
    if (t.matches("[data-k-avatar],[data-k-petav]") && t.files && t.files[0]) {
      var pet = t.getAttribute("data-k-petav"), wrap = t.closest("label"), file = t.files[0];
      var img = wrap.querySelector(".pgk-av");
      wrap.classList.add("busy");
      shrink(file, 900).then(function (b) { return api(pet ? "pet_av" : "avatar", pet ? { pet: pet } : {}, { photo: b }); }).then(function (j) {
        wrap.classList.remove("busy");
        if (!j.ok) { toast(j.msg || "", true); return; }
        if (img) img.innerHTML = '<img src="' + esc(j.url) + '" alt="">';
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
  D.addEventListener("visibilitychange", function () { if (!D.hidden && K) live(); });

  /* ---------- старт и переходы без перезагрузки ---------- */
  function boot() {
    scanWizards(D);
    langAll();
    leaveCabinet();
    initCabinet();
  }
  var prev = W.PGInitPage;
  W.PGInitPage = function () { if (prev) prev.apply(this, arguments); setTimeout(boot, 0); };
  if (D.readyState === "loading") D.addEventListener("DOMContentLoaded", boot); else boot();
  W.addEventListener("load", langAll);
})();
