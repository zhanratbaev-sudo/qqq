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
        try { sessionStorage.setItem("pgk_tab", "pets"); } catch (x) {}
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
  D.addEventListener("contextmenu", function (e) {
    var t = e.target;
    if (!t.closest || !t.closest(".pg,.pg-layer")) return;
    if (t.closest("input,textarea,[contenteditable]")) return;
    if (t.closest("img,svg,video,.pg-ph,.pgk-map,.pg-hero")) e.preventDefault();
  });
  D.addEventListener("dragstart", function (e) { if (e.target.tagName === "IMG" && e.target.closest(".pg,.pg-layer")) e.preventDefault(); });
  D.addEventListener("selectstart", function (e) {
    var t = e.target.nodeType === 3 ? e.target.parentNode : e.target;
    if (t && t.closest && t.closest(".pg,.pg-layer") && !t.closest("input,textarea,[contenteditable],.pg-sel-ok")) e.preventDefault();
  });

  /* ============================================================
     4. КАБИНЕТ КЛИЕНТА
     ============================================================ */
  var CL = { box: null, tab: "pets", timer: 0, chatT: 0, lastMsg: 0, chatOpen: false };
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

  /* --- листы снизу --- */
  var openSheet = null;
  function kOpen(id) {
    var s = D.getElementById("pgk-" + id);
    if (!s) return null;
    if (openSheet && openSheet !== s) kClose();
    if (W.PGCloseAI) W.PGCloseAI();
    s.hidden = false; void s.offsetWidth; s.classList.add("on");
    D.documentElement.classList.add("pgk-lock");
    openSheet = s;
    return s;
  }
  function kClose() {
    var s = openSheet;
    if (!s) return;
    openSheet = null;
    s.classList.remove("on");
    var inn = $(".pgk-sin", s); if (inn) inn.style.transform = "";
    D.documentElement.classList.remove("pgk-lock");
    if (s.id === "pgk-chat") { CL.chatOpen = false; clearInterval(CL.chatT); }
    setTimeout(function () { if (!s.classList.contains("on")) s.hidden = true; }, reduce ? 0 : 420);
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
        if (b) { var w = b.closest(".pgk-wacts"); var s = +(r ? r.value : 5); if (w) w.outerHTML = '<div class="pgk-myrv">' + "★★★★★".slice(0, s) + "<span>" + "★★★★★".slice(0, 5 - s) + "</span></div>"; }
        f.reset(); kClose(); toast(j.msg || T("Отправлено"));
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
  var TABS = ["pets", "map", "walks", "for", "me"];
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
    live();
  }

  /* --- анкета питомца в листе --- */
  function petSheet(pid) {
    var tpl = D.getElementById("pgk-tpl-" + (pid ? pid : "new"));
    var s = kOpen("pet");
    if (!s || !tpl) return;
    var body = $("[data-k-pet-body]", s), tt = $("[data-k-pet-title]", s);
    if (tt) tt.textContent = pid ? (T("Анкета питомца") + " · " + (($('[data-pet="' + pid + '"] .pgk-pet-t b') || {}).textContent || "")) : T("Новый питомец");
    body.innerHTML = "";
    body.appendChild(D.importNode(tpl.content, true));
    scanWizards(body);
  }

  /* --- чат с менеджером --- */
  function msgHtml(m) {
    return '<div class="pgk-m' + (m.me ? " me" : "") + '" data-id="' + m.id + '">' + (!m.me ? '<span class="pgk-m-av">' + (m.av ? '<img src="' + esc(m.av) + '" alt="">' : "<b>" + esc((m.n || "P").charAt(0)) + "</b>") + "</span>" : "") +
      '<div class="pgk-m-b">' + (!m.me && m.n ? "<small>" + esc(m.n) + "</small>" : "") + (m.p ? '<button type="button" class="pg-b pgk-m-ph" data-k-photo="' + esc(m.p) + '"><img src="' + esc(m.p) + '" alt="" loading="lazy"></button>' : "") + (m.h ? "<p>" + m.h + "</p>" : "") + "<time>" + esc(m.tm) + "</time></div></div>";
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
    (file ? shrink(file, 1600) : Promise.resolve(null)).then(function (b) {
      return api("send", { body: body }, b ? { photo: b } : null);
    }).then(function (j) {
      btn.disabled = false;
      if (!j.ok) { toast(j.msg || T("Не получилось — проверьте интернет."), true); return; }
      ta.value = ""; ta.style.height = ""; if (inp) inp.value = ""; f.classList.remove("has-ph");
      loadMsgs(false);
    }).catch(function () { btn.disabled = false; toast(T("Не получилось — проверьте интернет."), true); });
  }
  function unread(n) {
    qa("[data-k-unread]").forEach(function (b) { b.textContent = n; b.hidden = !n; });
  }

  /* --- живое состояние питомцев (статусы, баннер прогулки, непрочитанные) --- */
  function applyPets(pets) {
    if (!pets) return;
    K.pets = pets;
    var live = null;
    pets.forEach(function (p) {
      var el = $('[data-k-st="' + p.id + '"]');
      if (el) {
        el.className = "pgk-status st-" + p.st;
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

  /* --- лайтбокс фото --- */
  function lightbox(src, cap) {
    var lb = $("[data-k-lb]") || $(".pgk-lb");
    if (!lb) { lb = D.createElement("div"); lb.className = "pgk-lb"; lb.setAttribute("data-k-lb", ""); lb.innerHTML = '<button type="button" class="pg-b pgk-x" data-k-lbx>×</button><img alt=""><span></span>'; D.body.appendChild(lb); }
    $("img", lb).src = src; $("span", lb).textContent = cap || "";
    lb.hidden = false; void lb.offsetWidth; lb.classList.add("on");
  }
  function lbClose() { var lb = $(".pgk-lb.on"); if (lb) { lb.classList.remove("on"); setTimeout(function () { lb.hidden = true; }, 300); } }

  /* ============================================================
     5. КАРТА: где питомец сейчас, маршрут прогулки, все места и фото на карте (Leaflet + OpenStreetMap)
     ============================================================ */
  var MapK = (function () {
    var map = null, Lf = null, layer = null, hist = null, mode = "now", focus = 0, walk = 0, loading = null, fitted = false;
    var COLORS = ["#024C46", "#E58A2B", "#3F7CAC", "#C2557A", "#6E9F2E", "#8A5CC2"];
    function color(pid) { var ids = (K.pets || []).map(function (p) { return p.id; }), i = ids.indexOf(pid); return COLORS[(i < 0 ? 0 : i) % COLORS.length]; }
    function petOf(id) { return (K.pets || []).filter(function (p) { return p.id === id; })[0]; }
    function load() {
      if (W.L) return Promise.resolve(W.L);
      if (loading) return loading;
      loading = new Promise(function (ok, no) {
        var c = D.createElement("link"); c.rel = "stylesheet"; c.href = K.leaflet.css; D.head.appendChild(c);
        var s = D.createElement("script"); s.src = K.leaflet.js; s.onload = function () { ok(W.L); }; s.onerror = no; D.head.appendChild(s);
      });
      return loading;
    }
    function ago(ts) {
      if (!ts) return "";
      var s = Math.max(0, Math.round(Date.now() / 1000 - ts));
      if (s < 45) return T("только что");
      return Math.round(s / 60) + " " + T("мин") + " " + T("назад");
    }
    function petIcon(p, liveOn) {
      var img = p.av ? '<img src="' + esc(p.av) + '" alt="">' : "<b>" + esc((p.name || "?").charAt(0)) + "</b>";
      var bub = liveOn ? (p.hi ? "<em>" + esc(p.hi) + "</em> " : "") + esc(p.text) : esc(p.name);
      return Lf.divIcon({ className: "pgk-pm-w", iconSize: [60, 60], iconAnchor: [30, 30],
        html: '<div class="pgk-pm' + (liveOn ? " live" : "") + '" style="--c:' + color(p.id) + '"><i class="pgk-pm-ring"></i><span class="pgk-pm-av">' + img + '</span><span class="pgk-pm-bub">' + bub + "</span></div>" });
    }
    function photoIcon(u) {
      return Lf.divIcon({ className: "pgk-phm-w", iconSize: [46, 46], iconAnchor: [23, 46], html: '<div class="pgk-phm"><img src="' + esc(u) + '" alt="" loading="lazy"></div>' });
    }
    function dot(c) { return Lf.divIcon({ className: "pgk-dot-w", iconSize: [14, 14], iconAnchor: [7, 7], html: '<i class="pgk-dot" style="--c:' + c + '"></i>' }); }
    function card(html) {
      var c = $("[data-k-mcard]");
      if (!c) return;
      if (!html) { c.hidden = true; return; }
      c.innerHTML = html; c.hidden = false;
    }
    function shown(pid) { return !focus || focus === pid; }
    function draw() {
      if (!map) return;
      layer.clearLayers();
      var pts = [], pets = K.pets || [], any = false;
      qa("[data-k-mpet]").forEach(function (b) { b.classList.toggle("on", +b.getAttribute("data-k-mpet") === focus); });
      qa("[data-k-mmode]").forEach(function (b) { b.classList.toggle("on", b.getAttribute("data-k-mmode") === mode); });
      if (mode === "now") {
        var cardHtml = "";
        pets.forEach(function (p) {
          if (!shown(p.id)) return;
          if (p.live && p.pos) {
            any = true;
            if (p.track && p.track.length > 1) {
              var line = p.track.map(function (t) { return [t[0], t[1]]; });
              Lf.polyline(line, { color: "#fff", weight: 9, opacity: .9, lineCap: "round", lineJoin: "round" }).addTo(layer);
              Lf.polyline(line, { color: color(p.id), weight: 5, opacity: 1, lineCap: "round", lineJoin: "round" }).addTo(layer);
              Lf.marker(line[0], { icon: dot(color(p.id)), interactive: false }).addTo(layer);
              pts = pts.concat(line);
            }
            (p.photos || []).forEach(function (ph) {
              if (ph.lat == null) return;
              Lf.marker([ph.lat, ph.lng], { icon: photoIcon(ph.u), zIndexOffset: 200 }).on("click", function () { lightbox(ph.u, p.name + " · " + ph.t); }).addTo(layer);
            });
            Lf.marker(p.pos, { icon: petIcon(p, true), zIndexOffset: 1000 }).on("click", function () { focus = p.id; draw(); fit(); }).addTo(layer);
            pts.push(p.pos);
            if (!cardHtml || focus === p.id) cardHtml = liveCard(p);
          }
        });
        /* сейчас никто не гуляет — показываем, где питомец был в последний раз */
        if (!any && hist) {
          pets.forEach(function (p) {
            if (!shown(p.id)) return;
            var w = hist.walks.filter(function (x) { return x.pet === p.id; })[0];
            if (!w || !w.p.length) return;
            var last = w.p[w.p.length - 1];
            any = true;
            Lf.polyline(w.p.map(function (t) { return [t[0], t[1]]; }), { color: color(p.id), weight: 4, opacity: .45, dashArray: "1 8", lineCap: "round" }).addTo(layer);
            Lf.marker([last[0], last[1]], { icon: petIcon({ id: p.id, av: p.av, name: p.name, hi: "", text: p.name }, false) }).addTo(layer);
            pts.push([last[0], last[1]]);
            if (!cardHtml || focus === p.id) cardHtml = '<div class="pgk-mc">' + avHtml(p) + "<div><b>" + esc(p.name) + "</b><small>" + esc(T("Последнее место")) + " · " + esc(w.d) + (w.n ? " · " + esc(T("Няня")) + " " + esc(w.n) : "") + '</small><span class="pgk-mc-st st-' + esc(p.st) + '"><i></i>' + esc(p.text) + "</span></div></div>";
          });
        }
        card(cardHtml);
      } else if (mode === "all" && hist) {
        hist.walks.forEach(function (w) {
          if (!shown(w.pet) || !w.p.length) return;
          var hl = walk && w.o === walk;
          any = true;
          var line = w.p.map(function (t) { return [t[0], t[1]]; });
          if (line.length > 1) Lf.polyline(line, { color: color(w.pet), weight: hl ? 6 : 4, opacity: hl ? 1 : (walk ? .2 : .55), lineCap: "round", lineJoin: "round" }).bindTooltip(w.d + (w.n ? " · " + w.n : ""), { sticky: true }).addTo(layer);
          Lf.marker(line[0], { icon: dot(color(w.pet)) }).bindTooltip(T("Прогулка") + " " + w.d).addTo(layer);
          if (!walk || hl) pts = pts.concat(line);
        });
        var cnt = hist.walks.filter(function (w) { return shown(w.pet); }).length;
        card(any ? '<div class="pgk-mc pgk-mc-sum">' + mcIcon() + "<div><b>" + esc(T("Прогулки")) + ": " + cnt + "</b><small>" + esc(T("Все места")) + "</small></div></div>" : "");
      } else if (mode === "photos" && hist) {
        hist.photos.forEach(function (ph) {
          if (!shown(ph.pet)) return;
          any = true;
          Lf.marker([ph.lat, ph.lng], { icon: photoIcon(ph.u) }).on("click", function () { lightbox(ph.u, ((petOf(ph.pet) || {}).name || "") + " · " + ph.d); }).addTo(layer);
          pts.push([ph.lat, ph.lng]);
        });
        card("");
      }
      var em = $(".pgk-map-empty");
      if (!any && hist) {
        if (!em) { em = D.createElement("div"); em.className = "pgk-map-empty"; $("#pgk-map").appendChild(em); }
        em.innerHTML = "<b>🐾</b><p>" + esc(T("Нет данных о местах. Они появятся после первой прогулки с няней.")) + "</p>";
        em.hidden = false;
      } else if (em) em.hidden = true;
      MapK._pts = pts;
      if (!fitted && pts.length) { fit(); fitted = true; }
    }
    function avHtml(p) { return '<span class="pgk-av sm">' + (p.av ? '<img src="' + esc(p.av) + '" alt="">' : "<b>" + esc((p.name || "?").charAt(0)) + "</b>") + "</span>"; }
    function mcIcon() { return '<span class="pgk-mc-i"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4L3 6.5v13L9 17l6 2.5 6-2.5v-13L15 7z"/><path d="M9 4v13M15 7v12.5"/></svg></span>'; }
    function liveCard(p) {
      var left = p.end ? Math.round((p.end - Date.now() / 1000) / 60) : 0;
      return '<div class="pgk-mc live">' + avHtml(p) + "<div><b>" + esc(p.name) + ' <span class="pgk-live-dot"></span></b><span class="pgk-mc-st st-' + esc(p.st) + '"><i></i>' + (p.hi ? "<em>" + esc(p.hi) + "</em> " : "") + esc(p.text) + "</span><small>" +
        (p.min ? esc(T("Прогулка")) + " " + p.min + " " + esc(T("мин")) : "") + (left > 0 ? " · ⏱ " + left + " " + esc(T("мин")) : "") + (p.at ? " · " + esc(T("обновлено")) + " " + esc(ago(p.at)) : "") + "</small></div>" + (p.nav ? '<span class="pgk-av sm pgk-mc-n" title="' + esc(p.nanny || "") + '"><img src="' + esc(p.nav) + '" alt=""></span>' : "") + "</div>";
    }
    function fit() {
      if (!map) return;
      var pts = MapK._pts || [];
      if (pts.length === 1) { map.setView(pts[0], 16, { animate: false }); map.panBy([0, -20], { animate: false }); }
      else if (pts.length > 1) map.fitBounds(Lf.latLngBounds(pts), { paddingTopLeft: [40, 120], paddingBottomRight: [70, 130], maxZoom: 17, animate: !reduce });
    }
    function show(opts) {
      opts = opts || {};
      if (opts.focus !== undefined) { focus = opts.focus; fitted = false; }
      if (opts.walk) { walk = opts.walk; mode = "all"; fitted = false; }
      var el = D.getElementById("pgk-map");
      if (!el) return;
      load().then(function (L) {
        Lf = L;
        if (!map || !D.body.contains(map.getContainer())) {
          map = L.map(el, { zoomControl: false, attributionControl: true, tap: true }).setView([43.2389, 76.8897], 12);
          L.tileLayer("https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png", { subdomains: "abcd", maxZoom: 20, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions" target="_blank" rel="noopener">CARTO</a>' }).addTo(map);
          layer = L.layerGroup().addTo(map);
          var ld = $(".pgk-map-load", el); if (ld) ld.remove();
          fitted = false;
        }
        setTimeout(function () { map.invalidateSize(); }, 60);
        if (!hist) {
          api("map").then(function (j) { if (j.ok) { hist = j.hist; applyPets(j.pets); draw(); } });
        }
        draw();
        if (opts.focus !== undefined || opts.walk) setTimeout(fit, 120);
      }).catch(function () { el.innerHTML = '<div class="pgk-map-empty"><p>' + esc(T("Не получилось — проверьте интернет.")) + "</p></div>"; });
    }
    return {
      show: show, now: function () { if (map) draw(); }, fit: fit, _pts: [],
      mode: function (m) { mode = m; walk = 0; fitted = false; draw(); fit(); },
      focus: function (id) { focus = focus === id ? 0 : id; fitted = false; draw(); fit(); },
      reset: function () { map = null; hist = null; focus = 0; walk = 0; mode = "now"; fitted = false; }
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
    /* слой (нижнее меню сайта и т. п.) лежит вне страницы: листы кабинета переносим туда же, чтобы они были поверх всего */
    var layerEl = D.getElementById("pg-layer") || D.body;
    qa(".pgk-sheet,.pgk-lb,.pgk-toast", box).forEach(function (s) { layerEl.appendChild(s); });
    var dock = $(".pgk-dock", box); if (dock) layerEl.appendChild(dock);
    if (W.PGGlass && dock) W.PGGlass.scan(dock.parentNode);
    var t = (location.hash || "").replace("#", "");
    try { if (TABS.indexOf(t) < 0) t = sessionStorage.getItem("pgk_tab") || "pets"; } catch (x) { t = "pets"; }
    tab(TABS.indexOf(t) > -1 ? t : "pets", { quiet: true, keep: true });
    setTimeout(lens, 50);
    W.addEventListener("resize", lens);
    live();
  }
  /* уходим со страницы кабинета (переход без перезагрузки) — убираем его меню и листы */
  function leaveCabinet() {
    if (D.getElementById("pgk")) return;
    clearTimeout(CL.timer); clearInterval(CL.chatT);
    qa("#pg-layer > .pgk-dock, #pg-layer > .pgk-sheet, #pg-layer > .pgk-lb").forEach(function (x) { x.remove(); });
    D.documentElement.classList.remove("pgk-on", "pgk-lock");
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
    if ((b = t.closest("[data-k-lbx]")) || (t.classList && t.classList.contains("pgk-lb"))) { lbClose(); return; }
    if ((b = t.closest("[data-k-photo]"))) { e.preventDefault(); lightbox(b.getAttribute("data-k-photo"), b.getAttribute("data-k-cap")); return; }
    if (!K) return;
    if ((b = t.closest("[data-k-tab]"))) {
      e.preventDefault();
      var o = {};
      if (b.hasAttribute("data-k-focus")) o.focus = +b.getAttribute("data-k-focus");
      if (b.hasAttribute("data-k-walk")) o.walk = +b.getAttribute("data-k-walk");
      tab(b.getAttribute("data-k-tab"), o);
      return;
    }
    if ((b = t.closest("[data-k-pet]"))) { e.preventDefault(); petSheet(+b.getAttribute("data-k-pet")); return; }
    if ((b = t.closest("[data-k-mmode]"))) { MapK.mode(b.getAttribute("data-k-mmode")); return; }
    if ((b = t.closest("[data-k-mpet]"))) { MapK.focus(+b.getAttribute("data-k-mpet")); return; }
    if ((b = t.closest("[data-k-mfit]"))) { MapK.fit(); return; }
    if ((b = t.closest("[data-k-review]"))) {
      var s = kOpen("review"); if (!s) return;
      var f = s.querySelector("form");
      f.elements.order.value = b.getAttribute("data-k-review");
      var nm = b.getAttribute("data-k-rv-name"), av = b.getAttribute("data-k-rv-av"), fr = $("[data-k-rv-for]", s);
      if (fr) fr.innerHTML = nm ? (av ? '<span class="pgk-av sm"><img src="' + esc(av) + '" alt=""></span>' : "") + "<b>" + esc(T("Отзыв для няни %s").replace("%s", nm)) + "</b>" : "";
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
    if (e.key === "Escape") { if ($(".pgk-lb.on")) lbClose(); else if (openSheet) kClose(); }
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
