/* PetGo Магазин — витрина, корзина и оформление заказа. Работает одинаково на странице сайта «Магазин» и в кабинете клиента.
   Доставка: «Привезёт няня» — бесплатно (вместе с заказом выгула/визита); «По адресу» — только в зоне вокруг центра:
   бесплатно от суммы free, иначе fee; вне зоны — «Скоро добавим ваш район». Окончательно всё считает сервер. */
(function () {
  "use strict";
  var D = document, W = window, KEY = "psh_cart";
  function $(s, r) { return (r || D).querySelector(s); }
  function qa(s, r) { return Array.prototype.slice.call((r || D).querySelectorAll(s)); }
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
  function money(n) { return String(Math.round(n || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " ₸"; }
  function fmt(s) { var a = [].slice.call(arguments, 1), i = 0; return String(s).replace(/%(\d)\$s|%s/g, function (m, n) { return n ? a[+n - 1] : a[i++]; }); }
  function km(a, b, c, d) { var r = Math.PI / 180, x = Math.sin((c - a) * r / 2), y = Math.sin((d - b) * r / 2), h = x * x + Math.cos(a * r) * Math.cos(c * r) * y * y; return 12742 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h)); }
  function cartGet() { try { var c = JSON.parse(localStorage.getItem(KEY) || "{}"); return c && typeof c === "object" ? c : {}; } catch (e) { return {}; } }
  function cartPut(c) { try { localStorage.setItem(KEY, JSON.stringify(c)); } catch (e) {} }
  var ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8h14l-1 12.2a1 1 0 0 1-1 .8H7a1 1 0 0 1-1-.8L5 8Z"/><path d="M9 10V7a3 3 0 0 1 6 0v3"/></svg>';
  var OK = '<svg class="psh-ok" viewBox="0 0 80 80" aria-hidden="true"><circle cx="40" cy="40" r="36"/><path d="M24 41.5l11 11L57 29"/></svg>';
  var PIN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>';

  function setup(box) {
    if (box._psh) return;
    box._psh = 1;
    var S; try { S = JSON.parse($(".psh-cfg", box).textContent); } catch (e) { return; }
    var T = function (s) { return (S.tr && S.tr[s]) || s; };
    var Z = S.zone, me = S.me || {}, cab = S.ctx === "cabinet";
    var byId = {}; S.items.forEach(function (p) { byId[p.id] = p; });
    var st = { view: "list", cat: "", pet: "", open: 0, mode: (S.nanny && S.nanny.length) ? "nanny" : "addr", order: S.nanny && S.nanny[0] ? S.nanny[0].id : 0,
      addr: me.addr || "", lat: me.lat || 0, lng: me.lng || 0, geo: "", name: me.name || "", phone: me.phone || "", note: "", busy: false, err: "", done: null };
    if (st.lat) st.geo = km(Z.lat, Z.lng, st.lat, st.lng) <= Z.r ? "in" : "out";
    var map = null, mk = null, view = D.createElement("div"), bar = D.createElement("div");
    view.className = "psh-v"; bar.className = "psh-bar"; bar.hidden = true;
    box.appendChild(view); box.appendChild(bar);

    /* ---------- корзина ---------- */
    function cart() {
      var c = cartGet(), o = {}, n = 0, sum = 0;
      Object.keys(c).forEach(function (id) { var p = byId[id], q = Math.max(0, Math.min(20, c[id] | 0)); if (p && p.in && q) { o[id] = q; n += q; sum += p.p * q; } });
      return { items: o, n: n, sum: sum };
    }
    function setQ(id, q) { var c = cartGet(); q = Math.max(0, Math.min(20, q | 0)); if (q) c[id] = q; else delete c[id]; cartPut(c); }
    function fee(sum) { if (st.mode === "nanny") return 0; if (st.geo !== "in") return null; return sum >= Z.free ? 0 : Z.fee; }

    /* ---------- витрина ---------- */
    function stepper(id, q) { return '<span class="psh-step"><button type="button" class="pg-b" data-q="' + id + '" data-d="-1" aria-label="−">−</button><b>' + q + '</b><button type="button" class="pg-b" data-q="' + id + '" data-d="1" aria-label="+">+</button></span>'; }
    function card(p, q) {
      var img = p.img ? '<img src="' + esc(p.img) + '" alt="" loading="lazy" decoding="async">' : '<span class="psh-ph">' + ICON + "</span>";
      var price = '<span class="psh-pr"><b>' + money(p.p) + "</b>" + (p.o > p.p ? "<s>" + money(p.o) + "</s>" : "") + "</span>";
      var btn = !p.in ? '<span class="psh-na">' + esc(T("Нет в наличии")) + "</span>" : (q ? stepper(p.id, q) : '<button type="button" class="pg-b psh-add" data-add="' + p.id + '">' + esc(T("В корзину")) + "</button>");
      return '<article class="psh-c' + (p.in ? "" : " out") + (st.open === p.id ? " open" : "") + '" id="p' + p.id + '"><div class="psh-ci" data-more="' + p.id + '">' + img + (p.o > p.p ? '<em class="psh-sale">−' + Math.round(100 - p.p * 100 / p.o) + "%</em>" : "") + '</div><div class="psh-cb"><b class="psh-cn" data-more="' + p.id + '">' + esc(p.n) + "</b>" + (p.u ? '<small class="psh-cu">' + esc(p.u) + "</small>" : "") + (p.d ? '<p class="psh-cd" data-more="' + p.id + '">' + esc(p.d) + "</p>" : "") + '<div class="psh-cf">' + price + btn + "</div></div></article>";
    }
    function list() {
      var c = cart(), pets = { dog: 0, cat: 0 };
      S.items.forEach(function (p) { if (p.pet === "dog") pets.dog++; if (p.pet === "cat") pets.cat++; });
      var h = "";
      if (cab && S.mine && S.mine.length) h += '<button type="button" class="pg-b psh-mine-b" data-go="mine">' + esc(T("Мои заказы")) + " · " + S.mine.length + "</button>";
      var chips = [["", T("Все")]];
      Object.keys(S.cats || {}).forEach(function (k) { chips.push([k, S.cats[k]]); });
      if (chips.length > 2 || (pets.dog && pets.cat)) {
        h += '<div class="psh-chips" role="tablist">';
        chips.forEach(function (x) { h += '<button type="button" class="pg-b' + (st.cat === x[0] && !st.pet ? " on" : "") + '" data-cat="' + esc(x[0]) + '">' + esc(x[1]) + "</button>"; });
        if (pets.dog && pets.cat) { h += '<button type="button" class="pg-b' + (st.pet === "dog" ? " on" : "") + '" data-pet="dog">' + esc(T("Для собак")) + '</button><button type="button" class="pg-b' + (st.pet === "cat" ? " on" : "") + '" data-pet="cat">' + esc(T("Для кошек")) + "</button>"; }
        h += "</div>";
      }
      h += '<div class="psh-grid">';
      S.items.forEach(function (p) {
        if (st.cat && p.c !== st.cat) return;
        if (st.pet && p.pet !== "all" && p.pet !== st.pet) return;
        h += card(p, c.items[p.id] || 0);
      });
      return h + "</div>";
    }

    /* ---------- оформление ---------- */
    function zoneLine(sum) {
      if (st.geo === "busy") return '<p class="psh-z">' + esc(T("Ищем адрес…")) + "</p>";
      if (st.geo === "none") return '<p class="psh-z warn">' + esc(T("Адрес не найден — уточните или нажмите на карте, где вы")) + "</p>";
      if (st.geo === "out") return '<div class="psh-z out"><b>' + esc(T("Скоро добавим ваш район")) + "</b><span>" + esc(fmt(T("Пока доставляем в радиусе %s км от %s. Няня может привезти заказ бесплатно вместе с выгулом."), String(Z.r).replace(".", ","), Z.label)) + "</span></div>";
      if (st.geo === "in") {
        if (sum >= Z.free) return '<p class="psh-z ok">' + esc(T("Бесплатная доставка")) + "</p>";
        return '<p class="psh-z ok">' + esc(fmt(T("Доставка %s"), money(Z.fee))) + ' <span class="psh-z-mu">· ' + esc(fmt(T("Бесплатно от %s — добавьте ещё на %s"), money(Z.free), money(Z.free - sum))) + "</span></p>";
      }
      return '<p class="psh-z mu">' + esc(T("Укажите адрес — проверим, доставляем ли мы к вам")) + "</p>";
    }
    function checkout() {
      var c = cart(), f = fee(c.sum), h = '<button type="button" class="pg-b psh-back" data-go="list">‹ ' + esc(T("Назад к товарам")) + '</button><h3 class="psh-h">' + esc(T("Оформление заказа")) + "</h3>";
      h += '<div class="psh-box psh-lines">';
      Object.keys(c.items).forEach(function (id) { var p = byId[id], q = c.items[id]; h += '<div class="psh-line"><span class="psh-li">' + (p.img ? '<img src="' + esc(p.img) + '" alt="">' : ICON) + '</span><span class="psh-lt"><b>' + esc(p.n) + "</b><small>" + money(p.p) + (p.u ? " · " + esc(p.u) : "") + "</small></span>" + stepper(p.id, q) + "</div>"; });
      h += "</div>";
      /* как получить */
      h += '<h4 class="psh-gt">' + esc(T("Как получить")) + '</h4><div class="psh-ways">';
      var hasN = S.nanny && S.nanny.length;
      h += '<label class="psh-way' + (st.mode === "nanny" ? " on" : "") + (hasN ? "" : " dis") + '"><input type="radio" name="psh-mode" value="nanny"' + (st.mode === "nanny" ? " checked" : "") + (hasN ? "" : " disabled") + '><span class="psh-wt"><b>' + esc(T("Привезёт няня")) + '</b><small>' + esc(T("Бесплатно — когда придёт за питомцем")) + "</small></span><em>" + esc(T("бесплатно")) + "</em></label>";
      if (st.mode === "nanny" && hasN) {
        h += '<div class="psh-ords">';
        S.nanny.forEach(function (o) { h += '<label class="psh-ord' + (st.order === o.id ? " on" : "") + '"><input type="radio" name="psh-ord" value="' + o.id + '"' + (st.order === o.id ? " checked" : "") + "><span><b>" + esc(o.svc + " · " + o.pet) + "</b><small>" + esc(o.when + (o.nanny ? " · " + o.nanny : "")) + "</small></span></label>"; });
        h += "</div>";
      } else if (!hasN) {
        h += '<p class="psh-hint">' + (S.me ? esc(T("Закажите выгул или визит — няня привезёт товары бесплатно")) + (cab ? ' <button type="button" class="pg-b psh-link" data-k-book="walk">' + esc(T("Заказать выгул")) + "</button>" : "") : esc(T("Войдите в кабинет — няня привезёт заказ бесплатно, когда придёт за питомцем")) + ' <a class="psh-link" href="' + esc(S.acc) + '">' + esc(T("Войти")) + "</a>") + "</p>";
      }
      h += '<label class="psh-way' + (st.mode === "addr" ? " on" : "") + '"><input type="radio" name="psh-mode" value="addr"' + (st.mode === "addr" ? " checked" : "") + '><span class="psh-wt"><b>' + esc(T("Доставка по адресу")) + "</b><small>" + esc(fmt(T("Бесплатно от %s — добавьте ещё на %s"), money(Z.free), "").replace(/ — .*$/, "")) + " · " + esc(fmt(T("Доставка %s"), money(Z.fee))) + "</small></span></label>";
      if (st.mode === "addr") {
        h += '<div class="psh-addr"><label class="psh-f"><span>' + esc(T("Адрес")) + '</span><input type="text" name="psh-addr" value="' + esc(st.addr) + '" placeholder="' + esc(T("Улица, дом, квартира")) + '" maxlength="200" autocomplete="street-address"></label>';
        h += '<div class="psh-ab"><button type="button" class="pg-b psh-sb" data-find>' + PIN + esc(T("Найти на карте")) + '</button>' + (navigator.geolocation ? '<button type="button" class="pg-b psh-sb" data-here>' + esc(T("Моё местоположение")) + "</button>" : "") + "</div>";
        if (S.leaflet) h += '<div class="psh-map" aria-label="' + esc(T("Зона доставки")) + '"></div><small class="psh-mh">' + esc(T("Нажмите на карте, где вас найти")) + "</small>";
        h += '<div class="psh-zl">' + zoneLine(c.sum) + "</div></div>";
      }
      h += "</div>";
      /* контакты */
      h += '<div class="psh-box psh-fields">';
      if (!S.me) h += '<label class="psh-f"><span>' + esc(T("Имя")) + '</span><input type="text" name="psh-name" value="' + esc(st.name) + '" maxlength="80" autocomplete="name"></label><label class="psh-f"><span>' + esc(T("Телефон")) + '</span><input type="tel" name="psh-phone" value="' + esc(st.phone) + '" maxlength="20" inputmode="tel" placeholder="+7 700 000 00 00" autocomplete="tel"></label>';
      h += '<label class="psh-f psh-ft"><span>' + esc(T("Комментарий")) + '</span><textarea name="psh-note" rows="2" maxlength="400" placeholder="' + esc(T("Например: позвонить за 10 минут")) + '">' + esc(st.note) + "</textarea></label></div>";
      h += '<p class="psh-pay">' + esc(T("Оплата при получении — наличными или Kaspi")) + "</p>";
      h += '<div class="psh-box psh-tot"><div><span>' + esc(T("Товары")) + "</span><b>" + money(c.sum) + "</b></div><div><span>" + esc(T("Доставка")) + "</span><b>" + (f === null ? "—" : (f ? money(f) : esc(T("бесплатно")))) + '</b></div><div class="psh-tt"><span>' + esc(T("Итого")) + "</span><b>" + money(c.sum + (f || 0)) + "</b></div></div>";
      h += '<div class="pg-hp" aria-hidden="true"><input type="text" name="pgr_web" tabindex="-1" autocomplete="off"></div>';
      h += '<p class="psh-err" role="alert"' + (st.err ? "" : " hidden") + ">" + esc(st.err) + '</p><button type="button" class="pg-b psh-go" data-order' + (ready(c) ? "" : " disabled") + "><span>" + esc(T("Заказать")) + " · " + money(c.sum + (f || 0)) + '</span><i class="pg-spin" aria-hidden="true"></i></button>';
      return h;
    }
    function ready(c) {
      if (!c.n || st.busy) return false;
      if (st.mode === "nanny") return !!st.order;
      if (st.geo !== "in" || st.addr.trim().length < 5) return false;
      if (!S.me && (st.name.trim().length < 2 || st.phone.replace(/\D/g, "").length < 11)) return false;
      return true;
    }
    function done() {
      var d = st.done;
      return '<div class="psh-done">' + OK + "<h3>" + esc(T("Заказ принят")) + "</h3><p>" + esc(fmt(T(S.me ? "Номер заказа %s. Мы написали подробности в чат кабинета." : "Номер заказа %s. Менеджер позвонит и скажет время доставки."), d.no)) + "</p><p class=\"psh-pay\">" + esc(T("Оплата при получении — наличными или Kaspi")) + '</p><button type="button" class="pg-b psh-sb" data-go="list">' + esc(T("Продолжить покупки")) + "</button></div>";
    }
    function mine() {
      var h = '<button type="button" class="pg-b psh-back" data-go="list">‹ ' + esc(T("Назад к товарам")) + '</button><h3 class="psh-h">' + esc(T("Мои заказы")) + "</h3>";
      (S.mine || []).forEach(function (o) { h += '<div class="psh-box psh-mo"><div class="psh-moh"><b>' + esc(o.no) + '</b><span class="psh-st st-' + esc(o.st) + '">' + esc(o.stl) + "</span><small>" + esc(o.when) + "</small></div><p>" + esc(o.items) + "</p><small>" + esc(o.how) + " · " + esc(o.tot) + "</small></div>"; });
      return h;
    }

    /* ---------- отрисовка ---------- */
    function render(keepScroll) {
      if (map) { try { map.remove(); } catch (e) {} map = null; mk = null; }
      view.innerHTML = st.view === "co" ? checkout() : st.view === "done" ? done() : st.view === "mine" ? mine() : list();
      box.setAttribute("data-view", st.view);
      paintBar();
      if (st.view === "co" && st.mode === "addr" && S.leaflet) mapInit();
      /* клавиатура PetGo, уходя, плавно возвращает страницу — прокручиваем к началу ещё раз после её анимации */
      if (!keepScroll && st.view !== "list") { var v0 = st.view; requestAnimationFrame(top); setTimeout(function () { if (st.view === v0) top(); }, 320); }
    }
    function paintBar() {
      var c = cart();
      bar.hidden = st.view !== "list" || !c.n;
      bar.innerHTML = c.n ? '<button type="button" class="pg-b" data-go="co"><span class="psh-bi">' + ICON + "<i>" + c.n + "</i></span><span class=\"psh-bt\"><b>" + esc(T("Корзина")) + "</b><small>" + c.n + " " + esc(T("шт")) + " · " + money(c.sum) + '</small></span><span class="psh-bgo">' + esc(T("Оформить")) + " ›</span></button>" : "";
      D.documentElement.classList.toggle("psh-bar-on", !bar.hidden && !cab);
    }
    function top() {
      var sc = box.closest(".pgk-sc");
      if (sc) { sc.scrollTop = 0; return; }
      var y = box.getBoundingClientRect().top + (W.pageYOffset || 0) - 90;
      var pg = D.querySelector("html.pgk-on body.pg-body > .pg");
      if (pg && getComputedStyle(pg).position === "fixed") pg.scrollTop = Math.max(0, pg.scrollTop + box.getBoundingClientRect().top - 90);
      else W.scrollTo(0, Math.max(0, y));
    }
    /* меняем только то, что зависит от суммы и адреса — поля ввода не пересоздаём (фокус и клавиатура остаются) */
    function refreshTotals() {
      if (st.view !== "co") return;
      var c = cart(), f = fee(c.sum), zl = $(".psh-zl", view), tot = $(".psh-tot", view), go = $("[data-order]", view);
      if (zl) zl.innerHTML = zoneLine(c.sum);
      if (tot) tot.innerHTML = "<div><span>" + esc(T("Товары")) + "</span><b>" + money(c.sum) + "</b></div><div><span>" + esc(T("Доставка")) + "</span><b>" + (f === null ? "—" : (f ? money(f) : esc(T("бесплатно")))) + '</b></div><div class="psh-tt"><span>' + esc(T("Итого")) + "</span><b>" + money(c.sum + (f || 0)) + "</b></div>";
      if (go) { go.disabled = !ready(c); $("span", go).textContent = T("Заказать") + " · " + money(c.sum + (f || 0)); }
    }

    /* ---------- карта и адрес ---------- */
    function loadLeaflet() {
      if (W.L) return Promise.resolve();
      if (W.__pshLL) return W.__pshLL;
      W.__pshLL = new Promise(function (ok, no) {
        var l = D.createElement("link"); l.rel = "stylesheet"; l.href = S.leaflet.css; D.head.appendChild(l);
        var s = D.createElement("script"); s.src = S.leaflet.js; s.onload = ok; s.onerror = no; D.head.appendChild(s);
      });
      return W.__pshLL;
    }
    function setPoint(lat, lng, fly) {
      st.lat = lat; st.lng = lng;
      st.geo = km(Z.lat, Z.lng, lat, lng) <= Z.r ? "in" : "out";
      if (map) {
        if (!mk) mk = W.L.marker([lat, lng], { draggable: true, icon: W.L.divIcon({ className: "psh-pin", html: "<i></i>", iconSize: [30, 38], iconAnchor: [15, 36] }) }).addTo(map).on("dragend", function () { var p = mk.getLatLng(); setPoint(p.lat, p.lng); });
        else mk.setLatLng([lat, lng]);
        if (fly) map.setView([lat, lng], 15);
      }
      refreshTotals();
    }
    function mapInit() {
      var el = $(".psh-map", view);
      if (!el) return;
      loadLeaflet().then(function () {
        if (!el.isConnected) return;
        var L = W.L, dark = D.documentElement.classList.contains("pgk-dark");
        map = L.map(el, { zoomControl: false, attributionControl: true, scrollWheelZoom: false }).setView([Z.lat, Z.lng], 13);
        L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19, attribution: "© OpenStreetMap", className: dark ? "psh-tiles-dark" : "" }).addTo(map);
        var circ = L.circle([Z.lat, Z.lng], { radius: Z.r * 1000, color: "#024C46", weight: 2, fillColor: "#2E9E6B", fillOpacity: 0.12 }).addTo(map);
        L.circleMarker([Z.lat, Z.lng], { radius: 6, color: "#fff", weight: 2, fillColor: "#024C46", fillOpacity: 1 }).addTo(map).bindTooltip("PetGo · " + Z.label);
        map.fitBounds(circ.getBounds(), { padding: [10, 10] });
        map.on("click", function (e) { setPoint(e.latlng.lat, e.latlng.lng); });
        if (st.lat) setPoint(st.lat, st.lng);
      }).catch(function () {});
    }
    function geocode() {
      var q = st.addr.trim();
      if (q.length < 4) return;
      if (!/алмат|almat/i.test(q)) q = "Алматы, " + q;
      st.geo = "busy"; refreshTotals();
      var url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=kz&accept-language=ru&viewbox=76.70,43.42,77.15,43.12&bounded=1&q=" + encodeURIComponent(q);
      fetch(url).then(function (r) { return r.json(); }).then(function (a) {
        if (a && a[0]) setPoint(+a[0].lat, +a[0].lon, true); else { st.geo = "none"; refreshTotals(); }
      }).catch(function () { st.geo = "none"; refreshTotals(); });
    }

    /* ---------- заказ ---------- */
    function send() {
      var c = cart();
      if (!ready(c)) return;
      st.busy = true; st.err = "";
      if (W.PGKB_HIDE) W.PGKB_HIDE();
      var go = $("[data-order]", view); if (go) { go.disabled = true; go.classList.add("busy"); }
      var fd = new FormData();
      var hp = $('input[name="pgr_web"]', view);
      var data = { action: "petgo_shop", tk: S.tk, lang: S.lang, items: JSON.stringify(c.items), mode: st.mode, order: st.order, addr: st.addr, lat: st.lat, lng: st.lng, name: st.name, phone: st.phone, note: st.note, pgr_web: hp ? hp.value : "" };
      Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
      fetch(S.aj, { method: "POST", body: fd, credentials: "same-origin" }).then(function (r) { return r.json(); }).then(function (j) {
        st.busy = false;
        if (j && j.ok) {
          cartPut({}); st.done = j; if (j.mine) S.mine = j.mine; st.view = "done"; render();
          if (W.PGEvent) W.PGEvent("shop_order", { value: (j.sum || 0) + (j.fee || 0), mode: j.mode });
          return;
        }
        st.err = (j && j.msg) || T("Не получилось — проверьте интернет."); render(true);
        var e = $(".psh-err", view); if (e && e.scrollIntoView) e.scrollIntoView({ block: "center", behavior: "smooth" });
      }).catch(function () { st.busy = false; st.err = T("Не получилось — проверьте интернет."); render(true); });
    }

    /* ---------- нажатия и ввод ---------- */
    box.addEventListener("click", function (e) {
      var t = e.target, b;
      if ((b = t.closest("[data-add]"))) { var id = +b.getAttribute("data-add"); setQ(id, (cartGet()[id] | 0) + 1); render(true); return; }
      if ((b = t.closest("[data-q]"))) { var qid = +b.getAttribute("data-q"); setQ(qid, (cartGet()[qid] | 0) + (+b.getAttribute("data-d"))); if (st.view === "co" && !cart().n) st.view = "list"; if (st.view === "co") { var keep = $(".psh-lines", view); if (keep) { render(true); } } else render(true); return; }
      if ((b = t.closest("[data-cat]"))) { st.cat = b.getAttribute("data-cat"); st.pet = ""; render(true); return; }
      if ((b = t.closest("[data-pet]"))) { var pv = b.getAttribute("data-pet"); st.pet = st.pet === pv ? "" : pv; st.cat = ""; render(true); return; }
      if ((b = t.closest("[data-more]"))) { var mid = +b.getAttribute("data-more"); st.open = st.open === mid ? 0 : mid; var art = $("#p" + mid, view); if (art) art.classList.toggle("open", st.open === mid); qa(".psh-c.open", view).forEach(function (x) { if (x !== art) x.classList.remove("open"); }); return; }
      if ((b = t.closest("[data-go]"))) { st.view = b.getAttribute("data-go"); st.err = ""; render(); return; }
      if (t.closest("[data-find]")) { var ai = $('input[name="psh-addr"]', view); if (ai) st.addr = ai.value; geocode(); return; }
      if (t.closest("[data-here]")) { st.geo = "busy"; refreshTotals(); navigator.geolocation.getCurrentPosition(function (p) { setPoint(p.coords.latitude, p.coords.longitude, true); }, function () { st.geo = st.lat ? (km(Z.lat, Z.lng, st.lat, st.lng) <= Z.r ? "in" : "out") : ""; refreshTotals(); }, { enableHighAccuracy: true, timeout: 10000 }); return; }
      if (t.closest("[data-order]")) { send(); return; }
    });
    box.addEventListener("change", function (e) {
      var t = e.target;
      if (t.name === "psh-mode") { st.mode = t.value; st.err = ""; render(true); return; }
      if (t.name === "psh-ord") { st.order = +t.value; qa(".psh-ord", view).forEach(function (l) { l.classList.toggle("on", $("input", l).checked); }); refreshTotals(); return; }
      if (t.name === "psh-addr" && t.value.trim() !== st.addr.trim()) { st.addr = t.value; geocode(); }
    });
    box.addEventListener("input", function (e) {
      var t = e.target;
      if (t.name === "psh-addr") { st.addr = t.value; if (st.geo === "in" || st.geo === "out" || st.geo === "none") { st.geo = ""; st.lat = 0; st.lng = 0; if (mk && map) { map.removeLayer(mk); mk = null; } } refreshTotals(); }
      else if (t.name === "psh-name") { st.name = t.value; refreshTotals(); }
      else if (t.name === "psh-phone") { st.phone = t.value; refreshTotals(); }
      else if (t.name === "psh-note") st.note = t.value;
    });
    box.addEventListener("keydown", function (e) { if (e.key === "Enter" && e.target.name === "psh-addr") { e.preventDefault(); st.addr = e.target.value; geocode(); } });
    W.addEventListener("storage", function (e) { if (e.key === KEY) render(true); });

    render(true);
    /* переход с главной по товару: #p123 — прокрутить к нему */
    var hm = /^#p(\d+)$/.exec(location.hash || "");
    if (hm && byId[hm[1]]) setTimeout(function () { var a = $("#p" + hm[1], view); if (a) { a.classList.add("hl"); a.scrollIntoView({ block: "center", behavior: "smooth" }); } }, 300);
  }

  function init() { qa(".psh[data-psh]").forEach(setup); if (!D.querySelector('.psh[data-psh="site"]')) D.documentElement.classList.remove("psh-bar-on"); }
  var prev = W.PGInitPage;
  W.PGInitPage = function () { if (prev) prev.apply(this, arguments); setTimeout(init, 0); };
  if (D.readyState === "loading") D.addEventListener("DOMContentLoaded", init); else init();
})();
