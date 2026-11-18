/* PetGo.kz — первый экран (фото/видео/анимации и сменяющийся текст), листы снизу, форма заявки без перезагрузки,
   своя клавиатура. Работает и отдельно от site.js (например, с шорткодом [petgo_request]). */
(function () {
  "use strict";
  var C = window.PG_CONFIG || {}, S = window.PGSite || {};
  var doc = document, layer = doc.getElementById("pg-layer");
  function pgRoot() { return doc.getElementById("pg"); }
  var reduce = !!(window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches);
  function $(id) { return doc.getElementById(id); }
  function qa(sel, el) { return Array.prototype.slice.call((el || doc).querySelectorAll(sel)); }

  /* ============================================================
     ПЕРВЫЙ ЭКРАН: медиа сменяются по таймеру, текст на стекле — по своему
     ============================================================ */
  var heroT = { mt: 0, tt: 0, io: null }, fitArt = function () {};
  window.addEventListener("resize", function () { fitArt(); });
  function initHero() {
    clearTimeout(heroT.mt); clearTimeout(heroT.tt);
    if (heroT.io) { heroT.io.disconnect(); heroT.io = null; }
    fitArt = function () {};
    var stage = $("pg-hero-stage"), box = $("pg-hero-media");
    if (!stage || !box || !S.media) return;
    var list = [];
    try { list = JSON.parse(stage.getAttribute("data-media")) || []; } catch (e) {}
    var sec = Math.max(2, +stage.getAttribute("data-sec") || 5) * 1000;
    var seen = true;
    if ("IntersectionObserver" in window) { heroT.io = new IntersectionObserver(function (e) { seen = e[0].isIntersecting; stage.classList.toggle("pg-still", !seen); }); heroT.io.observe(stage); }
    function live() { return seen && !doc.hidden && stage.isConnected; }

    /* --- медиа: лента с отступом между фото, видео и анимациями --- */
    var track = doc.createElement("div");
    track.className = "pg-hero-track";
    box.appendChild(track);
    var slides = list.map(function (m, i) {
      var d = doc.createElement("div");
      d.className = "pg-hero-m" + (i ? "" : " on");
      d.innerHTML = S.media(m.u, i === 0);
      track.appendChild(d);
      var sv = d.querySelector("svg");
      if (sv && sv.getAttribute("viewBox") === "0 0 520 480") sv.setAttribute("data-scene", "1");
      return d;
    });
    /* рисунок прогулки подгоняется под форму блока: собака целиком над стеклом */
    fitArt = function () {
      var tall = stage.clientWidth / Math.max(1, stage.clientHeight) < 1.15;
      qa("svg[data-scene]", box).forEach(function (s) {
        s.setAttribute("viewBox", tall ? "0 0 520 640" : "0 100 520 300");
        s.setAttribute("preserveAspectRatio", tall ? "xMidYMin slice" : "xMidYMid slice");
      });
    };
    fitArt();
    var mi = 0;
    function playM(i) {
      mi = (i + slides.length) % slides.length;
      track.style.transform = "translateX(calc(" + (-mi * 100) + "% - " + (mi * 14) + "px))";
      slides.forEach(function (d, j) {
        d.classList.toggle("on", j === mi);
        var v = d.querySelector("video");
        if (!v) return;
        if (j !== mi) { v.pause(); return; }
        v.loop = slides.length === 1;
        try { v.currentTime = 0; } catch (e) {}
        var p = v.play(); if (p && p.catch) p.catch(function () {});
      });
      clearTimeout(heroT.mt);
      if (slides.length < 2 || !stage.isConnected) return;
      var m = list[mi], v = slides[mi].querySelector("video"), s = +m.s || 0;
      if (v && !s) { v.onended = function () { if (stage.isConnected) playM(mi + 1); }; heroT.mt = setTimeout(function () { playM(mi + 1); }, 120000); return; }
      heroT.mt = setTimeout(function tick() { if (!stage.isConnected) return; if (live()) playM(mi + 1); else heroT.mt = setTimeout(tick, 1000); }, (s || 8) * 1000);
    }
    if (slides.length) playM(0);

    /* --- текст на стекле --- */
    var texts = qa(".pg-hero-t", stage), dots = $("pg-hero-dots"), ti = 0;
    if (texts.length < 2 || !dots) return;
    texts.forEach(function (t, i) {
      var b = doc.createElement("button");
      b.type = "button"; b.className = "pg-b pg-hdot" + (i ? "" : " on"); b.tabIndex = -1;
      b.addEventListener("click", function () { showT(i); });
      dots.appendChild(b);
    });
    var dd = qa(".pg-hdot", dots);
    function showT(i) {
      var prev = ti;
      ti = (i + texts.length) % texts.length;
      texts.forEach(function (t, j) {
        t.classList.toggle("on", j === ti);
        t.classList.toggle("out", j === prev && j !== ti);
        if (j === ti) t.removeAttribute("aria-hidden"); else t.setAttribute("aria-hidden", "true");
      });
      dd.forEach(function (d, j) { d.classList.toggle("on", j === ti); });
      clearTimeout(heroT.tt);
      heroT.tt = setTimeout(function tick() { if (!stage.isConnected) return; if (live()) showT(ti + 1); else heroT.tt = setTimeout(tick, 1000); }, sec);
    }
    stage.style.setProperty("--hero-sec", sec + "ms");
    showT(0);
    /* листание текста пальцем */
    var sx = 0, glass = stage.querySelector(".pg-hero-glass");
    glass.addEventListener("touchstart", function (e) { sx = e.touches[0].clientX; }, { passive: true });
    glass.addEventListener("touchend", function (e) { var dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 40) showT(ti + (dx < 0 ? 1 : -1)); }, { passive: true });
  }
  initHero();

  /* ============================================================
     СВОЯ КЛАВИАТУРА (телефоны и планшеты): общий модуль kb.js — раскладки РУ, ҚАЗ, EN, цифры.
     Язык клавиатуры по умолчанию — язык сайта.
     ============================================================ */
  function T(s) { return (C.tr && C.tr[s]) || s; }

  /* язык сайта: выбор запоминается; при следующем заходе на русскую страницу без ?lang= — открывается выбранный */
  (function () {
    try {
      var s = localStorage.getItem("pg_lang"), u = new URL(location.href);
      if (C.spa && !u.searchParams.get("lang") && s && s !== "ru" && (C.lang || "ru") === "ru" && !sessionStorage.getItem("pg_lang_auto")) {
        sessionStorage.setItem("pg_lang_auto", "1");
        u.searchParams.set("lang", s);
        location.replace(u.href);
      }
    } catch (e) {}
    /* вверху на телефоне: нажатие на текущий язык открывает остальные */
    doc.addEventListener("click", function (e) {
      var box = doc.querySelector(".pg-nav-lang");
      if (!box) return;
      var a = e.target.closest ? e.target.closest("a") : null;
      if (a && a.parentNode === box && a.classList.contains("on") && window.matchMedia && matchMedia("(max-width: 600px)").matches) {
        e.preventDefault(); e.stopPropagation();
        box.classList.toggle("open");
        return;
      }
      if (!box.contains(e.target)) box.classList.remove("open");
    }, true);
    doc.addEventListener("click", function (e) {
      var a = e.target.closest ? e.target.closest("a[data-lang]") : null;
      if (a && !a.classList.contains("on")) { try { localStorage.setItem("pg_lang", a.getAttribute("data-lang")); localStorage.setItem("pgkb_lang", a.getAttribute("data-lang")); } catch (x) {} }
    }, true);
  })();

  var KB = (window.PGKB && layer && C.kbd) ? window.PGKB.init({
    /* 34.2: клавиатура PetGo во всех полях сайта и кабинета (анкета, вход, чаты, отзывы) */
    sel: ["text", "tel", "email", "number", "search", "url", "password"].map(function (t) { return ".pg input[type=" + t + "], .pg-layer input[type=" + t + "]"; }).concat([".pg input:not([type])", ".pg-layer input:not([type])", ".pg textarea", ".pg-layer textarea", "[data-kb-send]"]).join(", "),
    theme: "site", lang: C.lang || "ru", T: T, layer: layer, pad: pgRoot, noSys: true
  }) : { hide: function () {}, scan: function () {}, on: false };

  /* ============================================================
     ЛИСТЫ СНИЗУ: заявка и регистрация питомца выезжают поверх страницы
     ============================================================ */
  var opened = null, sheetT = 0;
  function closeSheet() {
    var s = opened;
    if (!s) return;
    opened = null;
    KB.hide();
    s.classList.remove("on");
    if (layer) layer.classList.remove("sheet-open");
    doc.documentElement.style.overflow = "";
    var inn = s.querySelector(".pg-sheet-in");
    if (inn) inn.style.transform = "";
    clearTimeout(sheetT);
    sheetT = setTimeout(function () { s.hidden = true; }, reduce ? 0 : 220);
  }
  function openSheet(id) {
    var s = $("pg-sheet-" + id);
    if (!s) {
      /* листа нет (форма стоит на странице отдельно) — прокрутить к ней */
      var f = $("form1") || doc.querySelector("form.pg-form");
      if (f) f.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
      return;
    }
    if (opened === s) return;
    if (opened) closeSheet();
    if (window.PGCloseAI) window.PGCloseAI();
    clearTimeout(sheetT);
    s.hidden = false;
    void s.offsetWidth;
    s.classList.add("on");
    if (layer) layer.classList.add("sheet-open");
    doc.documentElement.style.overflow = "hidden";
    opened = s;
    var x = s.querySelector(".pg-x");
    if (x) x.focus({ preventScroll: true });
  }
  window.PGSheet = { open: openSheet, close: closeSheet };
  doc.addEventListener("click", function (e) {
    var t = e.target.closest ? e.target.closest("[data-sheet], [data-close]") : null;
    if (!t) return;
    e.preventDefault();
    if (t.hasAttribute("data-close")) { closeSheet(); return; }
    var id = t.getAttribute("data-sheet");
    openSheet(id);
    /* «Выбрать эту няню»: имя няни сразу в комментарии заявки */
    var note = t.getAttribute("data-note"), sh = $("pg-sheet-" + id), ta = sh ? sh.querySelector('textarea[name="note"]') : null;
    if (note && ta && ta.value.indexOf(note) < 0) ta.value = note + (ta.value ? "\n" + ta.value : "");
  });
  doc.addEventListener("keydown", function (e) { if (e.key === "Escape" && opened) closeSheet(); });
  /* потянуть лист вниз за шапку — закрыть */
  qa(".pg-sheet [data-drag]").forEach(function (h) {
    var y0 = 0, dy = 0, inn = h.closest(".pg-sheet-in"), drag = false;
    h.addEventListener("touchstart", function (e) { if (e.target.closest("button")) return; drag = true; y0 = e.touches[0].clientY; dy = 0; inn.style.transition = "none"; }, { passive: true });
    h.addEventListener("touchmove", function (e) { if (!drag) return; dy = Math.max(0, e.touches[0].clientY - y0); inn.style.transform = "translateY(" + dy + "px)"; }, { passive: true });
    h.addEventListener("touchend", function () {
      if (!drag) return;
      drag = false; inn.style.transition = "";
      if (dy > 110) closeSheet(); else inn.style.transform = "";
    });
  });

  /* ============================================================
     ФОРМА: проверка, отправка без перезагрузки, «Отправлено» с галочкой
     ============================================================ */
  function initForm(f) {
    if (f.getAttribute("data-ready")) return;
    f.setAttribute("data-ready", "1");
    var body = f.querySelector(".pg-form-body"), ok = f.querySelector(".pg-form-ok"), err = f.querySelector(".pg-form-err"), go = f.querySelector(".pg-form-go");
    var pet = f.getAttribute("data-kind") === "pet", busy = false;
    function val(n) { var el = f.elements[n]; return el ? String(el.value || "").trim() : ""; }
    function species() { var r = f.querySelector('input[name="species"]:checked'); return r ? r.value : ""; }
    function sync() { f.classList.toggle("oth", species() === "other"); }
    f.addEventListener("change", sync); sync();
    function clearErr() { err.hidden = true; qa(".bad", f).forEach(function (x) { x.classList.remove("bad"); }); }
    f.addEventListener("input", function (e) { var l = e.target.closest ? e.target.closest(".bad") : null; if (l) l.classList.remove("bad"); });
    function fail(msg, field) {
      clearErr();
      err.textContent = msg; err.hidden = false;
      var el = field ? f.elements[field] : null;
      if (el && el.length && !el.tagName) el = el[0];
      var box = el ? (el.closest(".pg-fl") || el.closest(".pg-fs") || el.closest(".pg-consent")) : null;
      if (box) {
        box.classList.add("bad");
        if (box.scrollIntoView) box.scrollIntoView({ block: "center", behavior: reduce ? "auto" : "smooth" });
        /* 34.4: «качает головой» поле с ошибкой, а не кнопка */
        if (!reduce) { box.classList.remove("shake"); void box.offsetWidth; box.classList.add("shake"); setTimeout(function () { box.classList.remove("shake"); }, 400); }
      }
    }
    var ph = f.elements.phone;
    if (ph) {
      ph.addEventListener("focus", function () { if (ph.value === "") ph.value = "+7 "; });
      ph.addEventListener("blur", function () { if (ph.value.replace(/\D/g, "").length <= 1) ph.value = ""; });
      ph.addEventListener("input", function () { var v = ph.value.replace(/[^\d+\s()\-]/g, ""); if (v !== ph.value) ph.value = v; });
    }
    function sent() {
      /* форма плавно сворачивается, на её месте рисуется галочка */
      var h0 = f.offsetHeight;
      f.style.height = h0 + "px";
      f.classList.add("sending-out");
      setTimeout(function () {
        body.hidden = true; ok.hidden = false;
        f.classList.remove("sending-out"); f.classList.add("sent");
        var h1 = ok.offsetHeight;
        f.style.height = h1 + "px";
        setTimeout(function () { f.style.height = ""; }, 320);
        if (ok.scrollIntoView && !f.closest(".pg-sheet")) ok.scrollIntoView({ block: "center", behavior: reduce ? "auto" : "smooth" });
      }, reduce ? 0 : 180);
    }
    f.addEventListener("submit", function (e) {
      e.preventDefault();
      if (busy) return;
      KB.hide();
      clearErr();
      if (val("name").length < 2) return fail(T("Укажите имя."), "name");
      if (val("phone").replace(/\D/g, "").length < 10) return fail(T("Проверьте номер телефона."), "phone");
      if (!species()) return fail(T("Выберите, кто у вас."), "species");
      if (species() === "other" && !val("other")) return fail(T("Напишите, какое это животное."), "other");
      if (pet && !val("pet")) return fail(T("Укажите кличку питомца."), "pet");
      if (!f.elements.consent.checked) return fail(T("Нужно согласие на обработку данных."), "consent");
      busy = true; go.classList.add("busy"); go.disabled = true;
      var bad = T("Не получилось отправить — проверьте интернет и попробуйте ещё раз") + (C.phone_fmt ? T(" или позвоните нам: ") + C.phone_fmt : "") + ".";
      /* адрес — путь на этом же сайте (без http/https), так запрос не блокируется браузером */
      var act = f.getAttribute("action") || C.ajax || "/wp-admin/admin-ajax.php";
      fetch(act, { method: "POST", body: new FormData(f), credentials: "same-origin" })
        .then(function (r) {
          return r.text().then(function (t) {
            /* ответ может прийти с лишним текстом от других плагинов: ищем в нём JSON */
            var j = null;
            try { j = JSON.parse(t); } catch (e) { var m = t.match(/\{"ok":(true|false)[\s\S]*\}/); if (m) { try { j = JSON.parse(m[0]); } catch (e2) {} } }
            if (!j) { var err = new Error("bad"); err.st = r.status; throw err; }
            return j;
          });
        })
        .then(function (j) {
          busy = false; go.classList.remove("busy"); go.disabled = false;
          if (j && j.ok) sent(); else fail((j && j.msg) || bad, j && j.field);
        })
        .catch(function (e) {
          busy = false; go.classList.remove("busy"); go.disabled = false;
          fail(e && e.st ? T("Сайт ответил ошибкой (") + e.st + T("). Попробуйте ещё раз") + (C.phone_fmt ? T(" или позвоните нам: ") + C.phone_fmt : "") + "." : bad);
        });
    });
    var again = f.querySelector("[data-again]");
    if (again) again.addEventListener("click", function () {
      f.reset(); sync(); clearErr();
      ok.hidden = true; body.hidden = false; f.classList.remove("sent"); f.style.height = "";
    });
  }
  qa("form.pg-form").forEach(initForm);


  /* ============================================================
     ПЕРЕХОДЫ МЕЖДУ СТРАНИЦАМИ БЕЗ ПЕРЕЗАГРУЗКИ
     Все страницы сайта подгружаются заранее, в фоне (у сайта их пять). Нажатие — содержимое сразу
     плавно меняется, меню и помощник остаются на месте. Если страница ещё грузится — по центру
     крутится кружок в цветах PetGo.
     ============================================================ */
  (function () {
    if (!C.spa || !C.pages || !window.fetch || !window.DOMParser || !history.pushState) return;
    /* сравниваем только путь и запрос: http/https и www не важны */
    function clean(u) { try { var a = new URL(u, location.href); return a.pathname.replace(/\/+$/, "/") + a.search; } catch (e) { return ""; } }
    function same(u) { try { var a = new URL(u, location.href); return a.pathname + a.search; } catch (e) { return u; } }
    var map = {};
    Object.keys(C.pages).forEach(function (k) { map[clean(C.pages[k])] = k; });
    function keyOf(u) { return map[clean(u)] || ""; }
    var cache = {}, seq = 0;
    function load(u) {
      var n = clean(u), c = cache[n];
      if (c && Date.now() - c.t < 300000) return c.p;
      var p = fetch(same(u), { credentials: "same-origin" })
        .then(function (r) { if (!r.ok) throw new Error("http"); return r.text(); })
        .then(function (t) { return new DOMParser().parseFromString(t, "text/html"); });
      cache[n] = { t: Date.now(), p: p };
      p.catch(function () { delete cache[n]; });
      return p;
    }
    /* кружок загрузки */
    var spin = doc.createElement("div");
    spin.className = "pg-loader"; spin.setAttribute("aria-hidden", "true"); spin.innerHTML = "<i></i>";
    if (layer) layer.appendChild(spin);
    var spinT = 0;
    function loading(on) {
      clearTimeout(spinT);
      if (on) spinT = setTimeout(function () { spin.classList.add("on"); }, 140);
      else spin.classList.remove("on");
    }
    function swap(d, u, push, key) {
      var np = d.getElementById("pg"), op = pgRoot();
      if (!np || !op) throw new Error("nopage");
      var imp = doc.importNode(np, true);
      /* блоки на первом экране показываются сразу, без «выплывания» */
      imp.classList.add("pg-in", "pg-enter");
      op.parentNode.replaceChild(imp, op);
      if (d.title) doc.title = d.title;
      var md = d.querySelector('meta[name="description"]'), cd = doc.querySelector('meta[name="description"]');
      if (md && cd) cd.setAttribute("content", md.getAttribute("content"));
      C.page = key;
      if (push) history.pushState({ pg: key }, "", u);
      window.scrollTo(0, 0);
      if (window.PGInitPage) window.PGInitPage();
      var vh = window.innerHeight;
      qa(".pg-rise, .pg-summary", imp).forEach(function (x) { if (x.getBoundingClientRect().top < vh) x.classList.add("in"); });
      initHero();
      qa("form.pg-form", imp).forEach(initForm);
      if (KB.scan) KB.scan();
      if (window.PGGlass) window.PGGlass.scan(imp);
      void imp.offsetWidth;
      imp.classList.remove("pg-enter");
      setTimeout(function () { imp.classList.remove("pg-in"); }, 450);
    }
    function go(u, push) {
      var key = keyOf(u);
      if (!key) { location.href = u; return; }
      if (push && key === C.page) { window.scrollTo({ top: 0, behavior: reduce ? "auto" : "smooth" }); return; }
      var my = ++seq;
      closeSheet(); KB.hide();
      if (window.PGCloseAI) window.PGCloseAI();
      if (window.PGMark) window.PGMark(u);
      var r = pgRoot();
      if (r && !reduce) r.classList.add("pg-leave");
      loading(true);
      var t0 = Date.now();
      load(u).then(function (d) {
        if (my !== seq) return;
        if (!d.getElementById("pg")) throw new Error("nopage");
        /* старая страница успевает плавно погаснуть (до 140 мс), дальше ничего не ждём */
        setTimeout(function () {
          if (my !== seq) return;
          loading(false);
          swap(d, u, push, key);
        }, reduce ? 0 : Math.max(0, 140 - (Date.now() - t0)));
      }).catch(function () { location.href = u; });
    }
    doc.addEventListener("click", function (e) {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var a = e.target.closest ? e.target.closest("a[href]") : null;
      if (!a || a.target === "_blank" || a.hasAttribute("download") || a.hasAttribute("data-ext")) return;
      var h = a.getAttribute("href");
      if (!h || h.charAt(0) === "#" || /^(tel|mailto):/i.test(h) || a.href.indexOf("#") > -1 || !keyOf(a.href)) return;
      e.preventDefault();
      go(a.href, true);
    });
    /* заранее подгрузить все страницы, когда браузер свободен */
    function warmAll() {
      Object.keys(C.pages).forEach(function (k, i) {
        /* кабинет и анкету заранее не грузим: они личные и тяжелее остальных страниц */
        if (k === C.page || k === "account" || k === "join") return;
        setTimeout(function () { load(C.pages[k]).catch(function () {}); }, 300 * i);
      });
    }
    if (window.requestIdleCallback) requestIdleCallback(warmAll, { timeout: 2500 }); else setTimeout(warmAll, 1200);
    history.replaceState({ pg: C.page }, "", location.href);
    window.addEventListener("popstate", function () {
      var k = keyOf(location.href);
      if (k && k !== C.page) go(location.href, false);
    });
  })();
})();