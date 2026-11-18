/* PetGo Сайт — анкета питомца карточками, красивый переключатель языка, запрет выделения и масштабирования.
   Кабинет клиента — отдельный плагин «PetGo Клиент» (assets/client.js). Работает и после переходов без перезагрузки. */
(function () {
  "use strict";
  var D = document, W = window;
  var C = W.PG_CONFIG || {};
  var reduce = W.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches;
  function T(s) { return (C.tr && C.tr[s]) || s; }
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

  /* общие помощники — ими пользуется кабинет клиента (плагин «PetGo Клиент») */
  W.PGForm = { scan: scanWizards, shrink: shrink, phoneMask: phoneMask, json: json, esc: esc };

  /* ---------- старт и переходы без перезагрузки ---------- */
  function boot() {
    scanWizards(D);
    langAll();
  }
  var prev = W.PGInitPage;
  W.PGInitPage = function () { if (prev) prev.apply(this, arguments); setTimeout(boot, 0); };
  if (D.readyState === "loading") D.addEventListener("DOMContentLoaded", boot); else boot();
  W.addEventListener("load", langAll);
})();
