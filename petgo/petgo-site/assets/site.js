/* PetGo.kz — главная страница (шорткод [petgo_site]). Настройки приходят из WordPress в window.PG_CONFIG. */
(function () {
  var C = window.PG_CONFIG || { wa: "", reviews: [], photos: {} };
  function T(s) { return (C.tr && C.tr[s]) || s; }
  var layer = document.getElementById("pg-layer");
  var root = document.getElementById("pg");
  if (!root || !layer || layer.getAttribute("data-ready")) return;
  layer.setAttribute("data-ready", "1");
  if (layer.parentNode !== document.body) document.body.appendChild(layer);
  var reviews = C.reviews || [], P = C.photos || {};
  var reduce = window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches;

  function $(id) { return document.getElementById(id); }
  function qa(sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); }
  function esc(x) { return String(x).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }

  /* ============================================================
     РИСОВАННЫЕ КАРТИНКИ-ЗАГЛУШКИ (пока нет фото)
     ============================================================ */
  var DOGS = { gold: { m: "#E9A25B", d: "#B86F2E", l: "#F6D3A8", s: "#C97F3A" }, gray: { m: "#9EA8AE", d: "#6B767D", l: "#E6EAEC", s: "#7F8A91" }, cream: { m: "#F1EBDD", d: "#CDBF9F", l: "#FFFFFF", s: "#DCD2BB" } };
  var CATS = { gray: { m: "#AEB8C7", d: "#8793A6", l: "#E8ECF2", e: "#8FD19E" }, orange: { m: "#EBA25F", d: "#C97F3A", l: "#F8DDB8", e: "#9ACB8A" }, dark: { m: "#4A4A55", d: "#33333C", l: "#8E8E99", e: "#B7E08A" } };
  var INK = "#2B1A12", GREEN = "#024C46", LINE = "#e3e0cc", STAR = "#f5a623", BG = "#FBF9E8";
  var PAW = '<path d="M0 6c-9 0-16 7-16 13 0 5 4 7 9 6 3-1 5-2 7-2s4 1 7 2c5 1 9-1 9-6 0-6-7-13-16-13z"/>' +
    '<ellipse cx="-16" cy="-3" rx="5" ry="7" transform="rotate(-20 -16 -3)"/><ellipse cx="-6" cy="-13" rx="5" ry="7.5" transform="rotate(-6 -6 -13)"/>' +
    '<ellipse cx="6" cy="-13" rx="5" ry="7.5" transform="rotate(6 6 -13)"/><ellipse cx="16" cy="-3" rx="5" ry="7" transform="rotate(20 16 -3)"/>';
  function paw(x, y, s, fill, cls, delay) { return '<g class="' + (cls || "") + '" style="animation-delay:' + (delay || 0) + 's"><g transform="translate(' + x + " " + y + ") scale(" + s + ')" fill="' + fill + '">' + PAW + "</g></g>"; }
  function heart(x, y, s, fill, delay) { return '<g class="pg-heart" style="animation-delay:' + delay + 's"><g transform="translate(' + x + " " + y + ") scale(" + s + ')"><path d="M0 7C-16-3-9-17 0-9 9-17 16-3 0 7z" fill="' + fill + '"/></g></g>'; }
  function svg(vb, inner) { return '<svg viewBox="' + vb + '" preserveAspectRatio="xMidYMid slice" aria-hidden="true">' + inner + "</svg>"; }
  function dogFaceG(c) {
    return '<g class="pg-float">' +
      '<g class="pg-ear" style="transform-origin:52px 50px"><ellipse cx="46" cy="84" rx="25" ry="46" fill="' + c.d + '" transform="rotate(16 46 84)"/></g>' +
      '<g class="pg-ear r" style="transform-origin:148px 50px"><ellipse cx="154" cy="84" rx="25" ry="46" fill="' + c.d + '" transform="rotate(-16 154 84)"/></g>' +
      '<circle cx="100" cy="102" r="62" fill="' + c.m + '"/><ellipse cx="100" cy="128" rx="36" ry="28" fill="' + c.l + '"/>' +
      '<g class="pg-blink" style="transform-origin:78px 92px"><circle cx="78" cy="92" r="7.5" fill="' + INK + '"/><circle cx="80.5" cy="89" r="2.4" fill="#fff"/></g>' +
      '<g class="pg-blink" style="transform-origin:122px 92px"><circle cx="122" cy="92" r="7.5" fill="' + INK + '"/><circle cx="124.5" cy="89" r="2.4" fill="#fff"/></g>' +
      '<ellipse cx="100" cy="112" rx="12" ry="9" fill="' + INK + '"/>' +
      '<g class="pg-tongue" style="transform-origin:100px 130px"><path d="M91 130q9 28 18 0z" fill="#FF7E8B"/></g>' +
      '<path d="M100 121v8M100 129q-11 11-22 2M100 129q11 11 22 2" stroke="' + INK + '" stroke-width="3.2" fill="none" stroke-linecap="round"/>' +
      '<circle cx="64" cy="116" r="8" fill="#FF9C8A" opacity=".4"/><circle cx="136" cy="116" r="8" fill="#FF9C8A" opacity=".4"/></g>';
  }
  function catFaceG(c) {
    return '<g class="pg-float r">' +
      '<path d="M40 88L52 22L98 56Z" fill="' + c.m + '" stroke="' + c.m + '" stroke-width="8" stroke-linejoin="round"/>' +
      '<path d="M160 88L148 22L102 56Z" fill="' + c.m + '" stroke="' + c.m + '" stroke-width="8" stroke-linejoin="round"/>' +
      '<path d="M54 62L58 40L78 54Z" fill="#F2A7B5"/><path d="M146 62L142 40L122 54Z" fill="#F2A7B5"/>' +
      '<ellipse cx="100" cy="112" rx="68" ry="58" fill="' + c.m + '"/>' +
      '<path d="M100 56v16M86 58l3 13M114 58l-3 13" stroke="' + c.d + '" stroke-width="5" stroke-linecap="round"/>' +
      '<ellipse cx="100" cy="132" rx="27" ry="19" fill="' + c.l + '"/>' +
      '<g class="pg-blink" style="transform-origin:72px 106px"><ellipse cx="72" cy="106" rx="13" ry="15" fill="' + c.e + '"/><ellipse cx="72" cy="106" rx="4.5" ry="12" fill="#1F2A24"/><circle cx="76" cy="100" r="3" fill="#fff"/></g>' +
      '<g class="pg-blink" style="transform-origin:128px 106px"><ellipse cx="128" cy="106" rx="13" ry="15" fill="' + c.e + '"/><ellipse cx="128" cy="106" rx="4.5" ry="12" fill="#1F2A24"/><circle cx="132" cy="100" r="3" fill="#fff"/></g>' +
      '<path d="M93 122h14l-7 8z" fill="#F08CA0"/>' +
      '<path d="M100 130q-8 10-17 4M100 130q8 10 17 4" stroke="#6B5560" stroke-width="3" fill="none" stroke-linecap="round"/>' +
      '<path d="M60 124l-30-6M60 132l-30 6M140 124l30-6M140 132l30 6" stroke="#fff" stroke-width="2.4" stroke-linecap="round" opacity=".85"/></g>';
  }
  function floaty() {
    return paw(26, 34, .55, GREEN, "pg-fade", 0) + paw(172, 60, .45, GREEN, "pg-fade", 1.2) + paw(30, 172, .45, GREEN, "pg-fade", 2.4) +
      paw(172, 176, .55, GREEN, "pg-fade", .6) + heart(160, 150, .8, STAR, .3) + heart(40, 120, .6, STAR, 1.6);
  }
  function dogFace(c, bg) { return svg("0 0 200 200", '<rect width="200" height="200" fill="' + (bg || LINE) + '"/>' + floaty() + dogFaceG(c)); }
  function catFace(c, bg) { return svg("0 0 200 200", '<rect width="200" height="200" fill="' + (bg || LINE) + '"/>' + floaty() + catFaceG(c)); }
  function dogWalkG(c) {
    return '<g class="pg-bob">' +
      '<g class="pg-tail" style="transform-origin:48px 140px"><path d="M48 140Q8 128 20 84" fill="none" stroke="' + c.m + '" stroke-width="17" stroke-linecap="round"/></g>' +
      '<g class="pg-leg b" style="transform-origin:112px 190px"><rect x="103" y="188" width="18" height="74" rx="9" fill="' + c.s + '"/><rect x="103" y="242" width="18" height="20" rx="9" fill="' + c.l + '"/></g>' +
      '<g class="pg-leg" style="transform-origin:172px 190px"><rect x="163" y="188" width="18" height="74" rx="9" fill="' + c.s + '"/><rect x="163" y="242" width="18" height="20" rx="9" fill="' + c.l + '"/></g>' +
      '<ellipse cx="130" cy="156" rx="90" ry="50" fill="' + c.m + '"/><ellipse cx="130" cy="182" rx="68" ry="22" fill="' + c.l + '" opacity=".7"/>' +
      '<path d="M152 112Q162 156 152 204" stroke="' + GREEN + '" stroke-width="10" fill="none" stroke-linecap="round"/>' +
      '<g class="pg-leg" style="transform-origin:78px 190px"><rect x="69" y="188" width="18" height="74" rx="9" fill="' + c.m + '"/><rect x="69" y="242" width="18" height="20" rx="9" fill="' + c.l + '"/></g>' +
      '<g class="pg-leg b" style="transform-origin:198px 190px"><rect x="189" y="188" width="18" height="74" rx="9" fill="' + c.m + '"/><rect x="189" y="242" width="18" height="20" rx="9" fill="' + c.l + '"/></g>' +
      '<g class="pg-nod" style="transform-origin:212px 140px">' +
      '<circle cx="228" cy="112" r="42" fill="' + c.m + '"/>' +
      '<g class="pg-ear" style="transform-origin:208px 82px"><path d="M196 84Q176 102 190 140Q206 142 214 118Z" fill="' + c.d + '"/></g>' +
      '<ellipse cx="262" cy="128" rx="30" ry="21" fill="' + c.l + '"/><ellipse cx="286" cy="119" rx="10" ry="8" fill="' + INK + '"/>' +
      '<circle cx="242" cy="100" r="6.5" fill="' + INK + '"/><circle cx="244" cy="97.5" r="2" fill="#fff"/>' +
      '<g class="pg-tongue" style="transform-origin:272px 142px"><path d="M266 142Q270 166 278 142Z" fill="#FF7E8B"/></g>' +
      '<path d="M258 140Q270 148 284 138" stroke="' + INK + '" stroke-width="3" fill="none" stroke-linecap="round"/>' +
      '<circle cx="250" cy="126" r="7" fill="#FF9C8A" opacity=".4"/></g>' +
      '<path d="M203 138Q214 162 200 178" stroke="' + GREEN + '" stroke-width="10" fill="none" stroke-linecap="round"/>' +
      '<path d="M204 152C150 96 40 70 -90 40" stroke="' + STAR + '" stroke-width="4" fill="none" stroke-linecap="round"/></g>';
  }
  function scene(c) {
    var rays = "", posts = "", i;
    for (i = 0; i < 12; i++) {
      var a = i * Math.PI / 6, co = Math.cos(a), si = Math.sin(a);
      rays += '<line x1="' + (410 + 58 * co).toFixed(1) + '" y1="' + (108 + 58 * si).toFixed(1) + '" x2="' + (410 + 76 * co).toFixed(1) + '" y2="' + (108 + 76 * si).toFixed(1) + '" stroke="' + STAR + '" stroke-width="6" stroke-linecap="round"/>';
    }
    for (i = 0; i < 9; i++) posts += '<rect x="' + (-80 + i * 80) + '" y="358" width="12" height="42" rx="4" fill="' + BG + '"/>';
    return svg("0 0 520 480",
      '<rect width="520" height="480" fill="#fff"/>' +
      '<g class="pg-sun" style="transform-origin:410px 108px">' + rays + '</g><circle cx="410" cy="108" r="44" fill="' + STAR + '"/>' +
      '<g class="pg-cloud"><ellipse cx="90" cy="96" rx="44" ry="16" fill="#EEF3F1"/><circle cx="76" cy="84" r="18" fill="#EEF3F1"/><circle cx="106" cy="80" r="22" fill="#EEF3F1"/></g>' +
      '<g class="pg-cloud c2"><ellipse cx="260" cy="52" rx="30" ry="11" fill="#EEF3F1"/><circle cx="252" cy="44" r="12" fill="#EEF3F1"/><circle cx="272" cy="42" r="14" fill="#EEF3F1"/></g>' +
      '<path d="M0 330Q120 262 250 316T520 292V480H0Z" fill="#CFE3DE"/>' +
      '<rect x="64" y="286" width="12" height="46" rx="5" fill="#5f8480"/><circle cx="70" cy="272" r="32" fill="#4C9A8F"/><circle cx="94" cy="290" r="20" fill="#5FAEA2"/><circle cx="48" cy="292" r="18" fill="#5FAEA2"/>' +
      '<path d="M0 372Q160 326 300 366T520 346V480H0Z" fill="#8FBFB5"/>' +
      '<g class="pg-fence"><rect x="-80" y="372" width="800" height="7" fill="' + BG + '"/>' + posts + '</g>' +
      '<rect y="398" width="520" height="82" fill="' + GREEN + '"/>' +
      '<line class="pg-dash" x1="-60" y1="438" x2="580" y2="438" stroke="' + BG + '" stroke-width="5" stroke-dasharray="28 24" stroke-linecap="round" opacity=".6"/>' +
      paw(50, 462, .5, BG, "pg-print", 0) + paw(104, 452, .5, BG, "pg-print", .4) + paw(158, 462, .5, BG, "pg-print", .8) + paw(212, 452, .5, BG, "pg-print", 1.2) + paw(266, 462, .5, BG, "pg-print", 1.6) +
      '<g transform="translate(96 120) scale(1.1)">' + dogWalkG(c) + "</g>" +
      heart(352, 212, .9, STAR, 0) + heart(384, 222, .7, STAR, 1.1) + heart(330, 200, .6, STAR, 2.2));
  }
  function chatArt() {
    return svg("0 0 400 250", '<rect width="400" height="250" fill="' + LINE + '"/>' +
      '<g class="pg-pop"><rect x="36" y="38" width="210" height="70" rx="12" fill="' + GREEN + '"/><rect x="54" y="58" width="150" height="8" rx="4" fill="#fff"/><rect x="54" y="76" width="110" height="8" rx="4" fill="#fff" opacity=".7"/></g>' +
      '<g class="pg-pop d2"><rect x="150" y="134" width="214" height="70" rx="12" fill="#fff"/><rect x="168" y="154" width="150" height="8" rx="4" fill="' + GREEN + '"/><rect x="168" y="172" width="100" height="8" rx="4" fill="' + GREEN + '" opacity=".5"/></g>' +
      paw(330, 60, .9, GREEN, "pg-fade", 0) + paw(60, 200, .8, GREEN, "pg-fade", 1));
  }
  function matchArt() {
    return svg("0 0 400 250", '<rect width="400" height="250" fill="' + LINE + '"/>' +
      '<g class="pg-pop"><rect x="90" y="40" width="220" height="170" rx="12" fill="#fff"/>' +
      '<rect x="112" y="62" width="64" height="64" rx="12" fill="' + GREEN + '"/><g transform="translate(144 96) scale(.9)" fill="' + BG + '">' + PAW + '</g>' +
      '<rect x="192" y="70" width="96" height="10" rx="5" fill="' + GREEN + '"/><rect x="192" y="92" width="70" height="8" rx="4" fill="' + GREEN + '" opacity=".5"/>' +
      '<rect x="112" y="146" width="176" height="40" rx="12" fill="' + BG + '"/><path d="M132 166l8 8 14-16" stroke="' + GREEN + '" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/><rect x="168" y="162" width="100" height="8" rx="4" fill="' + GREEN + '" opacity=".6"/></g>');
  }
  var WALK_ARTS = [function () { return scene(DOGS.gold); }, function () { return scene(DOGS.gray); }, function () { return scene(DOGS.cream); }];
  var DOG_ARTS = [function () { return dogFace(DOGS.gold); }, function () { return dogFace(DOGS.gray); }, function () { return dogFace(DOGS.cream); }];
  var CAT_ARTS = [function () { return catFace(CATS.gray); }, function () { return catFace(CATS.orange); }, function () { return catFace(CATS.dark); }];
  var HOW_ARTS = [chatArt, matchArt, function () { return dogFace(DOGS.gold); }];

  /* встроенные анимации по имени (их выбирают в настройках наравне с фото и видео) */
  var ART = {
    "art:walk-gold": WALK_ARTS[0], "art:walk-gray": WALK_ARTS[1], "art:walk-cream": WALK_ARTS[2],
    "art:dog-gold": DOG_ARTS[0], "art:dog-gray": DOG_ARTS[1], "art:dog-cream": DOG_ARTS[2],
    "art:cat-gray": CAT_ARTS[0], "art:cat-orange": CAT_ARTS[1], "art:cat-dark": CAT_ARTS[2]
  };
  function isVideo(u) { return /\.(mp4|webm|mov|m4v|ogv)(\?|#|$)/i.test(u); }
  /* одна картинка слайда: анимация, видео или фото */
  function mediaHtml(u, eager) {
    if (ART[u]) return ART[u]();
    if (String(u).indexOf("art:") === 0) return "";
    if (isVideo(u)) return '<video src="' + esc(u) + '" muted playsinline preload="' + (eager ? "auto" : "metadata") + '"></video>';
    return '<img src="' + esc(u) + '" alt="" decoding="async"' + (eager ? ' fetchpriority="high"' : ' loading="lazy"') + ">";
  }
  window.PGSite = { media: mediaHtml, isVideo: isVideo, qa: qa, esc: esc, reduce: reduce };

  /* ============================================================
     БЛОК 0: меню — бургер, плавная прокрутка, подсветка раздела,
     прячется при прокрутке вниз и появляется при прокрутке вверх
     ============================================================ */
  var links = $("pg-links"), tabs = $("pg-dock");
  function goTo(id) {
    if (id === "pg-top") { window.scrollTo({ top: 0, behavior: reduce ? "auto" : "smooth" }); return true; }
    var t = $(id);
    if (!t) return false;
    t.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
    return true;
  }
  document.addEventListener("click", function (e) {
    var a = e.target.closest && e.target.closest('a[href^="#pg-"]');
    if (!a) return;
    if (goTo(a.getAttribute("href").slice(1))) { e.preventDefault(); if (window.PGCloseAI) window.PGCloseAI(); }
  });
  /* «линза» переезжает под выбранный раздел */
  function lens(box, a) {
    var l = box && box.querySelector(".pg-lens");
    if (!l) return;
    if (!a || !a.offsetWidth) { l.classList.remove("on"); return; }
    l.style.width = a.offsetWidth + "px";
    l.style.height = a.offsetHeight + "px";
    l.style.transform = "translate(" + a.offsetLeft + "px," + a.offsetTop + "px)";
    l.classList.add("on");
  }
  /* страницы настоящие, выбранную отмечает сервер (class="on"); линза встаёт под неё и следует за пальцем/мышью */
  function place(box, a) { lens(box, a || (box && box.querySelector("a.on"))); }
  function onScroll() { place(links); place(tabs); }
  [links, tabs].forEach(function (box) {
    if (!box) return;
    /* мышь: линза следует за курсором и возвращается, когда курсор ушёл.
       палец: линза едет один раз — к нажатой странице (по нажатию), без «туда-обратно» */
    qa("a", box).forEach(function (a) {
      a.addEventListener("pointerenter", function (e) { if (e.pointerType === "mouse") place(box, a); });
    });
    box.addEventListener("pointerleave", function (e) { if (e.pointerType === "mouse") place(box); });
  });
  window.addEventListener("resize", onScroll);
  window.addEventListener("pageshow", onScroll);
  onScroll();
  /* нижнее меню и верхние разделы: отметить страницу сразу при нажатии (до загрузки) */
  window.PGMark = function (href) {
    [links, tabs].forEach(function (box) {
      if (!box) return;
      qa("a", box).forEach(function (a) {
        var on = a.href.split("#")[0] === href;
        a.classList.toggle("on", on);
        if (on) a.setAttribute("aria-current", "page"); else a.removeAttribute("aria-current");
      });
      place(box);
    });
  };

  /* ============================================================
     СТРАНИЦА: всё, что внутри #pg. Запускается при открытии и после каждого перехода без перезагрузки
     ============================================================ */
  var timers = [], observers = [], svc = null, ind = null, cards = null;
  function initPage() {
    timers.forEach(clearInterval); timers = [];
    observers.forEach(function (o) { o.disconnect(); }); observers = [];
    root = $("pg");
    if (!root) return;
    root.classList.add("js");
    onScroll();

  /* БЛОК 3: услуги — выбор собака/кошка и слайдеры */
  qa(".pg-pet-art[data-art]").forEach(function (el) { el.innerHTML = el.getAttribute("data-art").indexOf("cat") > 0 ? catFace(CATS.orange, "#fff") : dogFace(DOGS.gold, "#fff"); });
  /* слайдер карточки: фото, видео и анимации из настроек, в любом количестве; листаются лентой с отступом */
  function makeSlider(box, list) {
    var n = list.length, html = "", i;
    if (!n) { box.style.display = "none"; return; }
    for (i = 0; i < n; i++) html += '<div class="pg-slide' + (i ? "" : " on") + '">' + mediaHtml(list[i], false) + "</div>";
    var dots = "";
    if (n > 1) {
      dots = '<div class="pg-dots">';
      for (i = 0; i < n; i++) dots += '<button type="button" class="pg-b pg-dot' + (i ? "" : " on") + '" data-i="' + i + '" aria-label="' + T("Фото") + ' ' + (i + 1) + '"></button>';
      dots += "</div>";
    }
    box.innerHTML = '<div class="pg-strack">' + html + "</div>" + dots;
    var track = box.firstChild;
    var cur = 0, timer, sx = 0;
    function go(k) {
      cur = (k + n) % n;
      track.style.transform = "translateX(calc(" + (-cur * 100) + "% - " + (cur * 12) + "px))";
      qa(".pg-slide", box).forEach(function (s, j) {
        s.classList.toggle("on", j === cur);
        var v = s.querySelector("video");
        if (v) { if (j === cur) { v.loop = true; var p = v.play(); if (p && p.catch) p.catch(function () {}); } else v.pause(); }
      });
      qa(".pg-dot", box).forEach(function (d, j) { d.classList.toggle("on", j === cur); });
    }
    function start() { clearInterval(timer); if (n > 1) { timer = setInterval(function () { if (box.offsetParent !== null && !document.hidden && !box.classList.contains("pg-still")) go(cur + 1); }, 3800); timers.push(timer); } }
    qa(".pg-dot", box).forEach(function (d) { d.addEventListener("click", function () { go(+d.getAttribute("data-i")); start(); }); });
    /* листание пальцем */
    box.addEventListener("touchstart", function (e) { sx = e.touches[0].clientX; }, { passive: true });
    box.addEventListener("touchend", function (e) { var dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 40 && n > 1) { go(cur + (dx < 0 ? 1 : -1)); start(); } }, { passive: true });
    go(0);
    start();
  }
  qa(".pg-slider[data-media]").forEach(function (box) {
    var list = [];
    try { list = JSON.parse(box.getAttribute("data-media")) || []; } catch (e) {}
    makeSlider(box, list);
  });
  svc = $("pg-services"); ind = svc && svc.querySelector(".pg-picker-ind"); cards = svc && svc.querySelector(".pg-cards");
  qa(".pg-pet").forEach(function (b) {
    b.addEventListener("click", function () {
      var v = b.getAttribute("data-pet");
      if (svc.getAttribute("data-pet") === v) return;
      svc.setAttribute("data-pet", v);
      qa(".pg-pet").forEach(function (x) { x.setAttribute("aria-selected", x === b ? "true" : "false"); });
      moveInd(b, true);
      cards.classList.remove("swap"); void cards.offsetWidth; cards.classList.add("swap");
    });
  });
  indNow();

  /* БЛОК 4: как это работает — шаги сменяются каждые 6 секунд, пока блок на экране */
  (function () {
    var wrap = $("pg-steps"), steps = qa(".pg-step"), urls = P.how || [], started = false;
    if (!wrap) return;
    steps.forEach(function (s, i) { s.querySelector(".pg-how-art").innerHTML = urls[i] ? '<img src="' + esc(urls[i]) + '" alt="" loading="lazy">' : HOW_ARTS[i](); });
    function go(i) {
      steps.forEach(function (s, j) {
        s.classList.remove("on");
        s.querySelector(".pg-step-h").setAttribute("aria-expanded", "false");
        if (j === i) { void s.offsetWidth; s.classList.add("on"); s.querySelector(".pg-step-h").setAttribute("aria-expanded", "true"); }
      });
    }
    steps.forEach(function (s, i) {
      s.querySelector(".pg-step-h").addEventListener("click", function () { started = true; go(i); });
      s.querySelector(".pg-prog").addEventListener("animationend", function () { if (s.classList.contains("on")) go((i + 1) % steps.length); });
    });
    function seen(v) { wrap.classList.toggle("paused", !v); if (v && !started) { started = true; go(0); } }
    if ("IntersectionObserver" in window) { var so = new IntersectionObserver(function (e) { seen(e[0].isIntersecting); }, { rootMargin: "0px 0px -20% 0px" }); so.observe(wrap); observers.push(so); }
    else seen(true);
  })();

  renderReviews();
  if ($("pg-list")) {
    $("pg-list").addEventListener("click", function (e) {
      var th = e.target.closest(".pg-thumb");
      if (th) { e.stopPropagation(); openPhotos(+th.getAttribute("data-r"), +th.getAttribute("data-p")); return; }
      /* 36.1: отзыв не открывается отдельным экраном — длинный раскрывается прямо здесь */
      var card = e.target.closest(".pg-rcard");
      if (card) rvToggle(card);
    });
    $("pg-list").addEventListener("keydown", function (e) { if (e.key === "Enter" && e.target.classList.contains("pg-rcard")) rvToggle(e.target); });
  }

  /* плавное появление блоков; анимации рисунков идут только на экране */
  if ("IntersectionObserver" in window && !reduce) {
    var rio = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add("in"); rio.unobserve(e.target); } });
    }, { rootMargin: "0px 0px -8% 0px", threshold: 0.08 });
    qa(".pg-rise, .pg-summary", root).forEach(function (el, i) { el.style.transitionDelay = (el.classList.contains("pg-card") ? (i % 2) * 90 : 0) + "ms"; rio.observe(el); });
    var aio = new IntersectionObserver(function (es) { es.forEach(function (e) { e.target.classList.toggle("pg-still", !e.isIntersecting); }); });
    qa(".pg-ph, .pg-pet-art", root).forEach(function (el) { aio.observe(el); });
    observers.push(rio, aio);
  } else {
    qa(".pg-rise, .pg-summary", root).forEach(function (el) { el.classList.add("in"); });
  }
  }
  function moveInd(b, anim) {
    if (!ind || !b) return;
    if (!anim) ind.style.transition = "none";
    ind.style.width = b.offsetWidth + "px";
    ind.style.transform = "translateX(" + (b.offsetLeft - 4) + "px)";
    if (!anim) { void ind.offsetWidth; ind.style.transition = ""; }
  }
  function indNow() { if (svc) moveInd(svc.querySelector('.pg-pet[aria-selected="true"]'), false); }
  window.addEventListener("resize", indNow);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(indNow);

  /* ============================================================
     БЛОК 7: отзывы (настоящие, из кабинета)
     ============================================================ */
  var STARP = '<path d="M12 3.2l2.6 5.4 5.9.8-4.3 4.1 1 5.9L12 16.6 6.8 19.4l1-5.9L3.5 9.4l5.9-.8z"/>';
  function stars(n) { var s = '<span class="pg-stars" aria-label="' + T("Оценка") + ' ' + n + ' ' + T("из 5") + '">'; for (var i = 1; i <= 5; i++) s += '<svg viewBox="0 0 24 24" class="' + (i <= n ? "full" : "empty") + '">' + STARP + "</svg>"; return s + "</span>"; }
  function thumbs(rev, ri, max) {
    if (!rev.p || !rev.p.length) return "";
    var html = '<div class="pg-thumbs">', shown = Math.min(rev.p.length, max);
    for (var k = 0; k < shown; k++) {
      var extra = (k === shown - 1 && rev.p.length > shown) ? rev.p.length - shown : 0;
      html += '<button class="pg-b pg-thumb" type="button" data-r="' + ri + '" data-p="' + k + '" aria-label="' + T("Открыть фото") + '"><img src="' + esc(rev.p[k]) + '" alt="" loading="lazy">' + (extra ? '<span class="pg-thumb__more">+' + extra + "</span>" : "") + "</button>";
    }
    return html + "</div>";
  }
  function plural(n, f) { var m = n % 100, k = n % 10; return (m > 10 && m < 20) ? f[2] : (k === 1 ? f[0] : (k >= 2 && k <= 4 ? f[1] : f[2])); }
  /* 36.1: на странице «Отзывы» все отзывы открыты целиком; на главной длинный раскрывается нажатием */
  function rvAll() { var l = $("pg-list"); return !!(l && !(+l.getAttribute("data-limit"))); }
  function checkClamp() {
    if (rvAll()) return;
    qa(".pg-rcard").forEach(function (card) {
      if (card.classList.contains("open")) return;
      var t = card.querySelector(".pg-rcard__text"), long = t.scrollHeight > t.clientHeight + 1;
      card.classList.toggle("long", long);
      card.querySelector(".pg-rmore").classList.toggle("hide", !long);
    });
  }
  function rvToggle(card) {
    if (rvAll() || !(card.classList.contains("long") || card.classList.contains("open"))) return;
    var open = card.classList.toggle("open"), b = card.querySelector(".pg-rmore");
    if (b) b.textContent = open ? T("Свернуть") : T("Читать полностью");
    card.setAttribute("aria-expanded", open ? "true" : "false");
  }
  function renderReviews() {
    if (!$("pg-list")) return;
    var total = reviews.length, sum = 0, c = [0, 0, 0, 0, 0, 0];
    reviews.forEach(function (x) { sum += x.r; c[x.r]++; });
    var avg = total ? sum / total : 0;
    $("pg-avg").textContent = avg.toFixed(1).replace(".", ",");
    $("pg-avgStars").innerHTML = stars(Math.round(avg));
    $("pg-count").textContent = total + " " + (C.tr && C.tr["#rv"] ? C.tr["#rv"][total === 1 ? 0 : 1] : plural(total, ["отзыв", "отзыва", "отзывов"]));
    var bars = "";
    for (var i = 5; i >= 1; i--) bars += '<div class="pg-bar">' + i + '<i><b style="width:' + (total ? c[i] / total * 100 : 0) + '%"></b></i></div>';
    $("pg-bars").innerHTML = bars;
    var lim = +$("pg-list").getAttribute("data-limit") || reviews.length;
    $("pg-list").classList.toggle("pg-rlist-all", rvAll());
    $("pg-list").innerHTML = reviews.slice(0, lim).map(function (x, i) {
      return '<article class="pg-rcard" data-i="' + i + '" tabindex="0">' + thumbs(x, i, 3) +
        '<div class="pg-rcard__head"><span>' + esc(x.n) + "</span><small>" + esc(x.d) + "</small></div>" + stars(x.r) +
        '<p class="pg-rcard__text">' + esc(x.t) + '</p>' + (rvAll() ? "" : '<button class="pg-b pg-rmore hide" type="button">' + T("Читать полностью") + "</button>") + "</article>";
    }).join("");
    checkClamp();
  }
  window.addEventListener("resize", checkClamp);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(checkClamp);

  function openViewer(rootEl, track, counter, slidesHtml, index, total) {
    track.innerHTML = slidesHtml;
    rootEl.hidden = false;
    document.documentElement.style.overflow = "hidden";
    track.style.scrollSnapType = "none";
    track.scrollLeft = index * track.clientWidth;
    track.style.scrollSnapType = "";
    function upd() { counter.textContent = (Math.round(track.scrollLeft / track.clientWidth) + 1) + " / " + total; }
    track.onscroll = upd; upd();
    rootEl.querySelector(".pg-back").focus({ preventScroll: true });
  }
  function closeViewer(rootEl) { rootEl.hidden = true; if ($("pg-rv").hidden && $("pg-pv").hidden) document.documentElement.style.overflow = ""; }
  function openReviews(index) {
    var html = reviews.map(function (x, i) {
      return '<div class="pg-rslide"><div class="pg-rslide__in">' + thumbs(x, i, 99) + '<div class="pg-rcard__head"><span>' + esc(x.n) + "</span><small>" + esc(x.d) + '</small></div><div style="margin:6px 0 12px">' + stars(x.r) + "</div><p>" + esc(x.t) + "</p></div></div>";
    }).join("");
    openViewer($("pg-rv"), $("pg-rvTrack"), $("pg-rvCount"), html, index, reviews.length);
  }
  function openPhotos(ri, pi) {
    var list = reviews[ri].p;
    openViewer($("pg-pv"), $("pg-pvTrack"), $("pg-pvCount"), list.map(function (src) { return '<div class="pg-pslide"><img src="' + esc(src) + '" alt=""></div>'; }).join(""), pi, list.length);
  }
  $("pg-rvTrack").addEventListener("click", function (e) { var th = e.target.closest(".pg-thumb"); if (th) openPhotos(+th.getAttribute("data-r"), +th.getAttribute("data-p")); });
  $("pg-rvBack").onclick = function () { closeViewer($("pg-rv")); };
  $("pg-pvBack").onclick = function () { closeViewer($("pg-pv")); };
  function stepViewer(track, dir) { track.scrollBy({ left: dir * track.clientWidth, behavior: "smooth" }); }
  $("pg-rvPrev").onclick = function () { stepViewer($("pg-rvTrack"), -1); };
  $("pg-rvNext").onclick = function () { stepViewer($("pg-rvTrack"), 1); };
  $("pg-pvPrev").onclick = function () { stepViewer($("pg-pvTrack"), -1); };
  $("pg-pvNext").onclick = function () { stepViewer($("pg-pvTrack"), 1); };
  document.addEventListener("keydown", function (e) {
    var pv = !$("pg-pv").hidden, rv = !$("pg-rv").hidden;
    if (e.key === "Escape") { if (pv) closeViewer($("pg-pv")); else if (rv) closeViewer($("pg-rv")); else if (window.PGCloseAI) window.PGCloseAI(); }
    else if ((e.key === "ArrowLeft" || e.key === "ArrowRight") && (pv || rv)) stepViewer(pv ? $("pg-pvTrack") : $("pg-rvTrack"), e.key === "ArrowLeft" ? -1 : 1);
  });
  initPage();
  window.PGInitPage = initPage;
  document.addEventListener("visibilitychange", function () { if (root) root.classList.toggle("pg-hidden", document.hidden); });
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { onScroll(true); });
  if (window.PGGlass) window.PGGlass.scan(layer);

  /* помощник (ИИ) — окно чата выводит отдельный плагин «PetGo ИИ» (assets/ai.js) */
})();
