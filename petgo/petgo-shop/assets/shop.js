/* PetGo Магазин 2.0 — витрина, корзина и заявка. Одинаково на странице сайта «Магазин» и в приложении клиента.
   Витрина приходит уже готовой в HTML — скрипт только оживляет её (поиск, категории, корзина), поэтому всё видно сразу.
   Доставка: клиент выбирает свой ЖК из списка (зоны — круги на карте, их задаёт администратор) или отмечает дом на карте;
   в зоне — бесплатно от порога (обычно 5 000 ₸), иначе цена доставки; вне зон — «Скоро добавим ваш район».
   «Привезёт няня» — бесплатно вместе с выгулом. Оплата при получении: наличными, картой или счёт на Kaspi.
   Окончательно всё считает сервер. */
(function () {
  "use strict";
  var D = document, W = window, KEY = "psh_cart", FAV = "psh_fav";
  function $(s, r) { return (r || D).querySelector(s); }
  function qa(s, r) { return Array.prototype.slice.call((r || D).querySelectorAll(s)); }
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
  function money(n) { return String(Math.round(n || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " ₸"; }
  function fmt(s) { var a = [].slice.call(arguments, 1), i = 0; return String(s).replace(/%(\d)\$s|%s/g, function (m, n) { return n ? a[+n - 1] : a[i++]; }); }
  function m2(a, b, c, d) { var r = Math.PI / 180, x = Math.sin((c - a) * r / 2), y = Math.sin((d - b) * r / 2), h = x * x + Math.cos(a * r) * Math.cos(c * r) * y * y; return 12742000 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h)); }
  function load(k) { try { var c = JSON.parse(localStorage.getItem(k) || "{}"); return c && typeof c === "object" ? c : {}; } catch (e) { return {}; } }
  function save(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
  var reduce = W.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches;
  var BAG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8h14l-1 12.2a1 1 0 0 1-1 .8H7a1 1 0 0 1-1-.8L5 8Z"/><path d="M9 10V7a3 3 0 0 1 6 0v3"/></svg>';
  var CART = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h2l2.2 10.2a1 1 0 0 0 1 .8h8.6a1 1 0 0 0 1-.8L19.5 8H6"/><circle cx="9.5" cy="19" r="1.4"/><circle cx="16.5" cy="19" r="1.4"/></svg>';
  var HEART = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10C19.5 15.4 12 20 12 20Z"/></svg>';
  var OK = '<svg class="psh-ok" viewBox="0 0 80 80" aria-hidden="true"><circle cx="40" cy="40" r="36"/><path d="M24 41.5l11 11L57 29"/></svg>';
  var PIN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>';
  var PAW = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 12.3c-2.7 0-4.8 2.1-4.8 4 0 1.7 1.4 2.4 2.8 2 .9-.3 1.4-.6 2-.6s1.1.3 2 .6c1.4.4 2.8-.3 2.8-2 0-1.9-2.1-4-4.8-4z"/><ellipse cx="6.6" cy="10" rx="1.5" ry="2"/><ellipse cx="9.9" cy="6.5" rx="1.5" ry="2.1"/><ellipse cx="14.1" cy="6.5" rx="1.5" ry="2.1"/><ellipse cx="17.4" cy="10" rx="1.5" ry="2"/></svg>';
  var SEARCH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>';

  function setup(box) {
    if (box._psh) return;
    box._psh = 1;
    var S; try { S = JSON.parse($(".psh-cfg", box).textContent); } catch (e) { return; }
    var T = function (s) { return (S.tr && S.tr[s]) || s; };
    var me = S.me || {}, cab = S.ctx === "cabinet";
    var ZD = (S.zones || []).filter(function (z) { return z.del; });
    var byId = {}; S.items.forEach(function (p) { byId[p.id] = p; });
    var st = { view: "list", q: "", cat: "", pet: "", mode: (S.nanny && S.nanny.length) ? "nanny" : "addr", order: S.nanny && S.nanny[0] ? S.nanny[0].id : 0,
      zone: me.zone && zoneById(me.zone) ? me.zone : "", other: false, zq: "", flat: me.flat || "", addr: me.addr || "", lat: me.lat || 0, lng: me.lng || 0, geo: "",
      name: me.name || "", phone: me.phone || "", note: "", pay: "cash", busy: false, err: "", done: null, mapOn: false };
    if (!st.zone && ZD.length === 1) st.zone = ZD[0].id;
    var view = $(".psh-v", box), bar = D.createElement("div"), map = null, mk = null, zmap = null;
    bar.className = "psh-bar"; bar.hidden = true; box.appendChild(bar);
    var page = box.closest(".pgk-sheet");

    function zoneById(id) { for (var i = 0; i < ZD.length; i++) if (ZD[i].id === id) return ZD[i]; return null; }
    function zoneAt(lat, lng) { var best = null, bd = 1e12; ZD.forEach(function (z) { var d = m2(z.lat, z.lng, lat, lng); if (d <= z.r && d < bd) { best = z; bd = d; } }); return best; }
    function zfee(z, sum) { return !z ? null : (!z.free || sum >= z.free) ? 0 : z.fee; }

    /* ---------- корзина ---------- */
    function cart() {
      var c = load(KEY), o = {}, n = 0, sum = 0;
      Object.keys(c).forEach(function (id) { var p = byId[id], q = Math.max(0, Math.min(20, c[id] | 0)); if (p && p.in && q) { o[id] = q; n += q; sum += p.p * q; } });
      return { items: o, n: n, sum: sum };
    }
    function setQ(id, q) { var c = load(KEY); q = Math.max(0, Math.min(20, q | 0)); if (q) c[id] = q; else delete c[id]; save(KEY, c); }
    function curZone() { if (st.mode !== "addr") return null; if (!st.other) return zoneById(st.zone); return st.geo === "in" ? zoneAt(st.lat, st.lng) : null; }
    function fee(sum) { if (st.mode === "nanny") return 0; return zfee(curZone(), sum); }

    /* ---------- витрина ---------- */
    function stepper(id, q) { return '<span class="psh-step"><button type="button" class="pg-b" data-q="' + id + '" data-d="-1" aria-label="−">−</button><b>' + q + '</b><button type="button" class="pg-b" data-q="' + id + '" data-d="1" aria-label="+">+</button></span>'; }
    function card(p, q, fav) {
      var badge = p.o > p.p ? '<em class="psh-badge sale">−' + Math.round(100 - p.p * 100 / p.o) + "%</em>" : (p.hit ? '<em class="psh-badge">' + esc(T("Хит")) + "</em>" : "");
      var img = p.th ? '<img src="' + esc(p.th) + '" alt="' + esc(p.n) + '" loading="lazy" decoding="async" width="300" height="300">' : '<span class="psh-ph">' + BAG + "</span>";
      var btn = !p.in ? '<span class="psh-na">' + esc(T("Нет в наличии")) + "</span>" : (q ? stepper(p.id, q) : '<button type="button" class="pg-b psh-add" data-add="' + p.id + '" aria-label="' + esc(T("В корзину")) + '">' + CART + "</button>");
      return '<article class="psh-c' + (p.in ? "" : " out") + '" id="p' + p.id + '"><div class="psh-ci" data-more="' + p.id + '">' + img + badge + '<button type="button" class="pg-b psh-fav' + (fav ? " on" : "") + '" data-fav="' + p.id + '" aria-label="' + esc(T("В избранное")) + '">' + HEART + '</button></div><div class="psh-cb"><b class="psh-cn" data-more="' + p.id + '">' + esc(p.n) + "</b>" + (p.u ? '<small class="psh-cu">' + esc(p.u) + "</small>" : "") + (p.d ? '<p class="psh-cd">' + esc(p.d) + "</p>" : "") + '<div class="psh-cf"><span class="psh-pr">' + (p.o > p.p ? "<s>" + money(p.o) + "</s>" : "") + "<b>" + money(p.p) + "</b></span>" + btn + "</div></div></article>";
    }
    function shown() {
      var q = st.q.toLowerCase().trim(), fav = load(FAV);
      return S.items.filter(function (p) {
        if (st.cat === "fav") { if (!fav[p.id]) return false; }
        else if (st.cat && p.c !== st.cat) return false;
        if (st.pet && p.pet !== "all" && p.pet !== st.pet) return false;
        return !q || (p.n + " " + (p.d || "") + " " + (p.u || "")).toLowerCase().indexOf(q) > -1;
      });
    }
    function gridHtml() {
      var c = cart(), fav = load(FAV), list = shown();
      if (!list.length) return '<div class="psh-empty">' + SEARCH + "<b>" + esc(T("Ничего не нашли")) + "</b></div>";
      return list.map(function (p) { return card(p, c.items[p.id] || 0, !!fav[p.id]); }).join("");
    }
    function list() {
      var pets = { dog: 0, cat: 0 }, h = "", fav = load(FAV), nf = Object.keys(fav).filter(function (k) { return byId[k]; }).length;
      S.items.forEach(function (p) { if (p.pet === "dog") pets.dog++; if (p.pet === "cat") pets.cat++; });
      if (cab && S.mine && S.mine.length) h += '<button type="button" class="pg-b psh-mine-b" data-go="mine">' + BAG + esc(T("Мои заказы")) + " · " + S.mine.length + "</button>";
      h += '<label class="psh-search">' + SEARCH + '<input type="search" data-psh-q value="' + esc(st.q) + '" placeholder="' + esc(T("Поиск товаров…")) + '" autocomplete="off" enterkeyhint="search"></label>';
      h += '<div class="psh-hero"><div class="psh-hero-t"><h2>' + esc(T("Всё для вашего")) + " <em>" + esc(T("питомца")) + "</em></h2><p>" + esc(S.heroSub) + '</p><button type="button" class="pg-b psh-hero-go" data-psh-to="grid">' + esc(T("Смотреть товары")) + ' →</button></div><div class="psh-hero-i"' + (S.banner ? "" : ' data-psh-art="art:dog-gold"') + ">" + (S.banner ? '<img src="' + esc(S.banner) + '" alt="" decoding="async">' : "") + "</div></div>";
      var keys = Object.keys(S.cats || {});
      if (keys.length > 1 || nf) {
        h += '<div class="psh-tiles" role="tablist">';
        keys.forEach(function (k) { var c = S.cats[k]; h += '<button type="button" class="pg-b psh-tile' + (st.cat === k ? " on" : "") + '" data-cat="' + esc(k) + '" role="tab" aria-selected="' + (st.cat === k) + '"><span class="psh-tile-i">' + (c.img ? '<img src="' + esc(c.img) + '" alt="" loading="lazy" decoding="async">' : '<span class="psh-ph">' + BAG + "</span>") + "</span><span>" + esc(c.n) + "</span></button>"; });
        if (nf) h += '<button type="button" class="pg-b psh-tile' + (st.cat === "fav" ? " on" : "") + '" data-cat="fav" role="tab"><span class="psh-tile-i psh-tile-fav">' + HEART + "</span><span>" + esc(T("Избранное")) + "</span></button>";
        h += "</div>";
      }
      var title = st.cat === "fav" ? T("Избранное") : st.cat && S.cats[st.cat] ? S.cats[st.cat].n : T("Популярные товары");
      h += '<div class="psh-sh" id="psh-grid"><h3>' + esc(title) + "</h3>";
      if (pets.dog && pets.cat) h += '<div class="psh-pets"><button type="button" class="pg-b' + (st.pet === "dog" ? " on" : "") + '" data-pet="dog">' + esc(T("Для собак")) + '</button><button type="button" class="pg-b' + (st.pet === "cat" ? " on" : "") + '" data-pet="cat">' + esc(T("Для кошек")) + "</button></div>";
      h += '</div><div class="psh-grid">' + gridHtml() + "</div>";
      /* как на макете: помощь с питомцем — заявка на сайте, в приложении — заказ няни */
      h += '<div class="psh-help"><div><b>' + esc(T("Нужна помощь с питомцем?")) + "</b><span>" + esc(T("Няня погуляет с собакой или навестит кошку дома")) + "</span>" + (cab ? '<button type="button" class="pg-b psh-hero-go" data-k-book="walk">' : '<button type="button" class="pg-b psh-hero-go" data-sheet="request">') + esc(T("Заказать выгул")) + ' →</button></div><span class="psh-help-i" data-psh-art="art:cat-orange"></span></div>';
      if (ZD.length) {
        var free = ZD[0].free;
        h += '<div class="psh-zbox"><div class="psh-zbox-h">' + PIN + "<div><b>" + esc(T("Где доставляем")) + "</b><span>" + esc(ZD.map(function (z) { return z.n; }).slice(0, 8).join(" · ")) + (ZD.length > 8 ? " …" : "") + "</span><small>" + esc(free ? fmt(T("бесплатно от %s"), money(free)) : T("Бесплатная доставка")) + "</small></div></div>";
        if (S.leaflet) h += '<button type="button" class="pg-b psh-sb" data-zmap>' + esc(st.mapOn ? T("Скрыть карту") : T("Показать на карте")) + '</button><div class="psh-zmap"' + (st.mapOn ? "" : " hidden") + "></div>";
        h += "</div>";
      }
      return h;
    }
    function refreshGrid() { var g = $(".psh-grid", view); if (g) g.innerHTML = gridHtml(); box.classList.toggle("psh-searching", !!st.q.trim()); paintBar(); }

    /* ---------- оформление ---------- */
    function zoneLine(sum) {
      if (st.mode !== "addr") return "";
      var z = curZone();
      if (st.other) {
        if (st.geo === "busy") return '<p class="psh-z">' + esc(T("Ищем адрес…")) + "</p>";
        if (st.geo === "none") return '<p class="psh-z warn">' + esc(T("Адрес не найден — уточните или нажмите на карте, где вы")) + "</p>";
        if (st.geo === "out") return '<div class="psh-z out"><b>' + esc(T("Скоро добавим ваш район")) + "</b><span>" + esc(T("Пока доставляем только в отмеченные ЖК. Няня может привезти заказ бесплатно вместе с выгулом.")) + "</span></div>";
        if (!z) return '<p class="psh-z mu">' + esc(T("Отметьте на карте, где вы живёте")) + "</p>";
      }
      if (!z) return "";
      var f = zfee(z, sum);
      if (!f) return '<p class="psh-z ok">' + esc(T("Бесплатная доставка")) + " · " + esc(z.n) + "</p>";
      return '<p class="psh-z ok">' + esc(fmt(T("Доставка %s"), money(f))) + ' <span class="psh-z-mu">· ' + esc(fmt(T("Бесплатно от %s — добавьте ещё на %s"), money(z.free), money(z.free - sum))) + "</span></p>";
    }
    function zoneCards() {
      var q = st.zq.toLowerCase().trim(), h = "";
      if (ZD.length > 6) h += '<label class="psh-search psh-zq">' + SEARCH + '<input type="search" name="psh-zq" value="' + esc(st.zq) + '" placeholder="' + esc(T("Поиск ЖК")) + '" autocomplete="off"></label>';
      h += '<div class="psh-zones" role="radiogroup">';
      ZD.forEach(function (z) {
        if (q && z.n.toLowerCase().indexOf(q) < 0 && st.zone !== z.id) return;
        var rule = z.free ? fmt(T("бесплатно от %s"), money(z.free)) + " · " + fmt(T("доставка %s"), money(z.fee)) : T("Бесплатная доставка");
        h += '<label class="psh-zc' + (!st.other && st.zone === z.id ? " on" : "") + '"><input type="radio" name="psh-zone" value="' + esc(z.id) + '"' + (!st.other && st.zone === z.id ? " checked" : "") + "><span><b>" + esc(z.n) + "</b><small>" + esc(rule) + "</small></span></label>";
      });
      h += '<label class="psh-zc psh-zc-o' + (st.other ? " on" : "") + '"><input type="radio" name="psh-zone" value=""' + (st.other ? " checked" : "") + "><span><b>" + esc(T("Моего ЖК нет в списке")) + "</b><small>" + esc(T("Отметьте на карте, где вы живёте")) + "</small></span></label></div>";
      return h;
    }
    function checkout() {
      var c = cart(), f = fee(c.sum), hasN = S.nanny && S.nanny.length;
      var h = '<button type="button" class="pg-b psh-back" data-go="list">‹ ' + esc(T("Назад к товарам")) + '</button><h3 class="psh-h">' + esc(T("Оформление заказа")) + "</h3>";
      h += '<div class="psh-box psh-lines">';
      Object.keys(c.items).forEach(function (id) { var p = byId[id], q = c.items[id]; h += '<div class="psh-line"><span class="psh-li">' + (p.th ? '<img src="' + esc(p.th) + '" alt="">' : BAG) + '</span><span class="psh-lt"><b>' + esc(p.n) + "</b><small>" + money(p.p) + (p.u ? " · " + esc(p.u) : "") + "</small></span>" + stepper(p.id, q) + "</div>"; });
      h += "</div>";
      h += '<h4 class="psh-gt">' + esc(T("Как получить")) + '</h4><div class="psh-seg2" role="radiogroup"><label class="' + (st.mode === "addr" ? "on" : "") + '"><input type="radio" name="psh-mode" value="addr"' + (st.mode === "addr" ? " checked" : "") + ">" + PIN + "<span>" + esc(T("Доставка")) + '</span></label><label class="' + (st.mode === "nanny" ? "on" : "") + (hasN ? "" : " dis") + '"><input type="radio" name="psh-mode" value="nanny"' + (st.mode === "nanny" ? " checked" : "") + (hasN ? "" : " disabled") + ">" + PAW + "<span>" + esc(T("Привезёт няня")) + "</span></label></div>";
      if (st.mode === "nanny" && hasN) {
        h += '<p class="psh-hint">' + esc(T("Бесплатно — когда придёт за питомцем")) + '</p><div class="psh-ords">';
        S.nanny.forEach(function (o) { h += '<label class="psh-ord' + (st.order === o.id ? " on" : "") + '"><input type="radio" name="psh-ord" value="' + o.id + '"' + (st.order === o.id ? " checked" : "") + "><span><b>" + esc(o.svc + " · " + o.pet) + "</b><small>" + esc(o.when + (o.nanny ? " · " + o.nanny : "")) + "</small></span></label>"; });
        h += "</div>";
      } else {
        if (!hasN) h += '<p class="psh-hint">' + (S.me ? esc(T("Закажите выгул или визит — няня привезёт товары бесплатно")) + (cab ? ' <button type="button" class="pg-b psh-link" data-k-book="walk">' + esc(T("Заказать выгул")) + "</button>" : "") : esc(T("Войдите в кабинет — няня привезёт заказ бесплатно, когда придёт за питомцем")) + ' <a class="psh-link" href="' + esc(S.acc) + '">' + esc(T("Войти")) + "</a>") + "</p>";
        h += '<h4 class="psh-gt">' + esc(T("Ваш ЖК")) + "</h4>" + zoneCards();
        h += '<div class="psh-addr">';
        if (st.other) {
          h += '<label class="psh-f"><span>' + esc(T("Адрес")) + '</span><input type="text" name="psh-addr" value="' + esc(st.addr) + '" placeholder="' + esc(T("Улица, дом")) + '" maxlength="160" autocomplete="street-address"></label>';
          h += '<div class="psh-ab"><button type="button" class="pg-b psh-sb" data-find>' + PIN + esc(T("Найти на карте")) + "</button>" + (navigator.geolocation ? '<button type="button" class="pg-b psh-sb" data-here>' + esc(T("Моё местоположение")) + "</button>" : "") + "</div>";
          if (S.leaflet) h += '<div class="psh-map"></div>';
        }
        h += '<label class="psh-f"><span>' + esc(T("Подъезд, этаж, квартира")) + '</span><input type="text" name="psh-flat" value="' + esc(st.flat) + '" maxlength="120" placeholder="' + esc(T("Подъезд, этаж, квартира")) + '"></label>';
        h += '<div class="psh-zl">' + zoneLine(c.sum) + "</div></div>";
      }
      h += '<div class="psh-box psh-fields">';
      if (!S.me) h += '<label class="psh-f"><span>' + esc(T("Имя")) + '</span><input type="text" name="psh-name" value="' + esc(st.name) + '" maxlength="80" autocomplete="name"></label><label class="psh-f"><span>' + esc(T("Телефон")) + '</span><input type="tel" name="psh-phone" value="' + esc(st.phone) + '" maxlength="20" inputmode="tel" placeholder="+7 700 000 00 00" autocomplete="tel"></label>';
      h += '<label class="psh-f psh-ft"><span>' + esc(T("Комментарий")) + '</span><textarea name="psh-note" rows="2" maxlength="400" placeholder="' + esc(T("Например: позвонить за 10 минут")) + '">' + esc(st.note) + "</textarea></label></div>";
      h += '<h4 class="psh-gt">' + esc(T("Оплата при получении")) + '</h4><div class="psh-pays" role="radiogroup">';
      Object.keys(S.pays || {}).forEach(function (k) { h += '<label class="' + (st.pay === k ? "on" : "") + '"><input type="radio" name="psh-pay" value="' + k + '"' + (st.pay === k ? " checked" : "") + "><span>" + esc(S.pays[k]) + "</span></label>"; });
      h += '</div><p class="psh-hint psh-kaspi"' + (st.pay === "kaspi" ? "" : " hidden") + ">" + esc(T("Менеджер пришлёт счёт на Kaspi")) + "</p>";
      h += '<div class="psh-box psh-tot">' + totHtml(c, f) + "</div>";
      h += '<div class="pg-hp" aria-hidden="true"><input type="text" name="pgr_web" tabindex="-1" autocomplete="off"></div>';
      h += '<p class="psh-err" role="alert"' + (st.err ? "" : " hidden") + ">" + esc(st.err) + '</p><button type="button" class="pg-b psh-go" data-order' + (ready(c) ? "" : " disabled") + "><span>" + esc(T("Отправить заявку")) + " · " + money(c.sum + (f || 0)) + '</span><i class="pg-spin" aria-hidden="true"></i></button>';
      return h;
    }
    function totHtml(c, f) {
      return "<div><span>" + esc(T("Товары")) + "</span><b>" + money(c.sum) + "</b></div><div><span>" + esc(T("Доставка")) + "</span><b>" + (f === null ? "—" : (f ? money(f) : esc(T("бесплатно")))) + '</b></div><div class="psh-tt"><span>' + esc(T("Итого")) + "</span><b>" + money(c.sum + (f || 0)) + '</b></div><small class="psh-tt-n">' + esc(T("Сумма к оплате при получении")) + "</small>";
    }
    function ready(c) {
      if (!c.n || st.busy) return false;
      if (st.mode === "nanny") return !!st.order;
      if (!curZone()) return false;
      if (st.flat.trim().length < 2) return false;
      if (st.other && st.addr.trim().length < 4) return false;
      if (!S.me && (st.name.trim().length < 2 || st.phone.replace(/\D/g, "").length < 11)) return false;
      return true;
    }
    function done() {
      var d = st.done;
      return '<div class="psh-done">' + OK + "<h3>" + esc(T("Заявка принята")) + "</h3><p>" + esc(fmt(T(S.me ? "Номер заказа %s. Мы написали подробности в чат кабинета." : "Номер заказа %s. Менеджер позвонит, подтвердит время и сумму."), d.no)) + '</p><p class="psh-pay"><b>' + money((d.sum || 0) + (d.fee || 0)) + "</b> · " + esc(T("Оплата при получении")) + '</p><button type="button" class="pg-b psh-sb" data-go="list">' + esc(T("Продолжить покупки")) + "</button></div>";
    }
    function mine() {
      var h = '<button type="button" class="pg-b psh-back" data-go="list">‹ ' + esc(T("Назад к товарам")) + '</button><h3 class="psh-h">' + esc(T("Мои заказы")) + "</h3>";
      (S.mine || []).forEach(function (o) { h += '<div class="psh-box psh-mo"><div class="psh-moh"><b>' + esc(o.no) + '</b><span class="psh-st st-' + esc(o.st) + '">' + esc(o.stl) + "</span><small>" + esc(o.when) + "</small></div><p>" + esc(o.items) + "</p><small>" + esc(o.how) + " · " + esc(o.tot) + "</small></div>"; });
      return h;
    }

    /* ---------- отрисовка ---------- */
    function arts() {
      qa("[data-psh-art]", view).forEach(function (el) { if (el._a || !W.PGSite || !W.PGSite.media) return; el._a = 1; el.innerHTML = W.PGSite.media(el.getAttribute("data-psh-art"), true); });
    }
    function render(keepScroll) {
      [map, zmap].forEach(function (x) { if (x) { try { x.remove(); } catch (e) {} } });
      map = mk = zmap = null;
      view.innerHTML = st.view === "co" ? checkout() : st.view === "done" ? done() : st.view === "mine" ? mine() : list();
      box.setAttribute("data-view", st.view);
      box.classList.toggle("psh-searching", st.view === "list" && !!st.q.trim());
      arts(); paintBar();
      if (st.view === "co" && st.mode === "addr" && st.other && S.leaflet) mapInit();
      if (st.view === "list" && st.mapOn) zonesMap();
      if (!keepScroll && st.view !== "list") { var v0 = st.view; requestAnimationFrame(top); setTimeout(function () { if (st.view === v0) top(); }, 320); }
    }
    function paintBar() {
      var c = cart();
      bar.hidden = st.view !== "list" || !c.n;
      bar.innerHTML = c.n ? '<button type="button" class="pg-b" data-go="co"><span class="psh-bi">' + CART + "<i>" + c.n + "</i></span><span class=\"psh-bt\"><b>" + esc(T("Корзина")) + "</b><small>" + c.n + " " + esc(T("шт")) + " · " + money(c.sum) + '</small></span><span class="psh-bgo">' + esc(T("Оформить")) + " ›</span></button>" : "";
      D.documentElement.classList.toggle("psh-bar-on", !bar.hidden && !cab);
      if (page) { var n = $("[data-psh-n]", page); if (n) { n.textContent = c.n; n.hidden = !c.n; } }
    }
    function top() {
      var sc = box.closest(".pgk-sc");
      if (sc) { sc.scrollTop = 0; return; }
      var pg = D.querySelector("html.pgk-on body.pg-body > .pg");
      if (pg && getComputedStyle(pg).position === "fixed") pg.scrollTop = Math.max(0, pg.scrollTop + box.getBoundingClientRect().top - 90);
      else W.scrollTo(0, Math.max(0, box.getBoundingClientRect().top + (W.pageYOffset || 0) - 90));
    }
    function scrollToEl(el) {
      if (!el) return;
      var sc = box.closest(".pgk-sc");
      if (sc) { sc.scrollTo({ top: sc.scrollTop + el.getBoundingClientRect().top - sc.getBoundingClientRect().top - 8, behavior: reduce ? "auto" : "smooth" }); return; }
      el.scrollIntoView({ block: "start", behavior: reduce ? "auto" : "smooth" });
    }
    /* меняем только то, что зависит от суммы и адреса — поля не пересоздаём (фокус и клавиатура остаются) */
    function refreshTotals() {
      if (st.view !== "co") return;
      var c = cart(), f = fee(c.sum), zl = $(".psh-zl", view), tot = $(".psh-tot", view), go = $("[data-order]", view);
      if (zl) zl.innerHTML = zoneLine(c.sum);
      if (tot) tot.innerHTML = totHtml(c, f);
      if (go) { go.disabled = !ready(c); $("span", go).textContent = T("Отправить заявку") + " · " + money(c.sum + (f || 0)); }
    }

    /* ---------- карта ---------- */
    function loadLeaflet() {
      if (W.L) return Promise.resolve();
      if (W.__pshLL) return W.__pshLL;
      W.__pshLL = new Promise(function (ok, no) {
        var l = D.createElement("link"); l.rel = "stylesheet"; l.href = S.leaflet.css; D.head.appendChild(l);
        var s = D.createElement("script"); s.src = S.leaflet.js; s.onload = ok; s.onerror = no; D.head.appendChild(s);
      });
      return W.__pshLL;
    }
    function baseMap(el) {
      var L = W.L, dark = D.documentElement.classList.contains("pgk-dark");
      var m = L.map(el, { zoomControl: false, attributionControl: true, scrollWheelZoom: false }).setView([43.238, 76.89], 12);
      L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19, attribution: "© OpenStreetMap", className: dark ? "psh-tiles-dark" : "" }).addTo(m);
      var g = [];
      (S.zones || []).forEach(function (z) {
        var col = z.del && z.svc ? "#024C46" : z.del ? "#2F6FD1" : "#8A8F8C";
        g.push(L.circle([z.lat, z.lng], { radius: z.r, color: col, weight: 2, fillColor: col, fillOpacity: 0.13 }).addTo(m).bindTooltip(esc(z.n) + " · " + esc(T(z.del && z.svc ? "Доставка и услуги" : z.del ? "Только доставка" : "Няни работают")), { direction: "top" }));
      });
      if (g.length) m.fitBounds(L.featureGroup(g).getBounds(), { padding: [14, 14], maxZoom: 15 });
      return m;
    }
    function zonesMap() {
      var el = $(".psh-zmap", view);
      if (!el || !S.leaflet) return;
      loadLeaflet().then(function () { if (el.isConnected && !zmap) zmap = baseMap(el); }).catch(function () {});
    }
    function setPoint(lat, lng, fly) {
      st.lat = lat; st.lng = lng;
      st.geo = zoneAt(lat, lng) ? "in" : "out";
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
        map = baseMap(el);
        map.on("click", function (e) { setPoint(e.latlng.lat, e.latlng.lng); });
        if (st.lat) setPoint(st.lat, st.lng);
      }).catch(function () {});
    }
    function geocode() {
      var q = st.addr.trim();
      if (q.length < 4) return;
      if (!/алмат|almat/i.test(q)) q = "Алматы, " + q;
      st.geo = "busy"; refreshTotals();
      fetch("https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=kz&accept-language=ru&viewbox=76.70,43.42,77.15,43.12&bounded=1&q=" + encodeURIComponent(q)).then(function (r) { return r.json(); }).then(function (a) {
        if (a && a[0]) setPoint(+a[0].lat, +a[0].lon, true); else { st.geo = "none"; refreshTotals(); }
      }).catch(function () { st.geo = "none"; refreshTotals(); });
    }

    /* ---------- заявка ---------- */
    function send() {
      var c = cart();
      if (!ready(c)) return;
      st.busy = true; st.err = "";
      if (W.PGKB_HIDE) W.PGKB_HIDE();
      var go = $("[data-order]", view); if (go) { go.disabled = true; go.classList.add("busy"); }
      var hp = $('input[name="pgr_web"]', view), z = curZone();
      var data = { action: "petgo_shop", tk: S.tk, lang: S.lang, items: JSON.stringify(c.items), mode: st.mode, order: st.order, zone: st.mode === "addr" && !st.other && z ? z.id : "",
        addr: st.mode === "addr" ? (st.other ? st.addr.trim() + ", " : "") + st.flat.trim() : "", lat: st.other ? st.lat : 0, lng: st.other ? st.lng : 0, name: st.name, phone: st.phone, note: st.note, pay: st.pay, pgr_web: hp ? hp.value : "" };
      var fd = new FormData(); Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
      fetch(S.aj, { method: "POST", body: fd, credentials: "same-origin" }).then(function (r) { return r.json(); }).then(function (j) {
        st.busy = false;
        if (j && j.ok) {
          save(KEY, {}); st.done = j; if (j.mine) S.mine = j.mine; st.view = "done"; render();
          if (W.PGEvent) W.PGEvent("shop_order", { value: (j.sum || 0) + (j.fee || 0), mode: j.mode });
          return;
        }
        st.err = (j && j.msg) || T("Не получилось — проверьте интернет."); render(true);
        var e = $(".psh-err", view); if (e && e.scrollIntoView) e.scrollIntoView({ block: "center", behavior: "smooth" });
      }).catch(function () { st.busy = false; st.err = T("Не получилось — проверьте интернет."); render(true); });
    }

    /* ---------- нажатия и ввод ---------- */
    function bump(el) { if (!el || reduce) return; el.classList.remove("bump"); void el.offsetWidth; el.classList.add("bump"); }
    box.addEventListener("click", function (e) {
      var t = e.target, b;
      if ((b = t.closest("[data-fav]"))) { e.stopPropagation(); var f = load(FAV), fid = b.getAttribute("data-fav"); if (f[fid]) delete f[fid]; else f[fid] = 1; save(FAV, f); b.classList.toggle("on", !!f[fid]); bump(b); return; }
      if ((b = t.closest("[data-add]"))) { var id = +b.getAttribute("data-add"); setQ(id, (load(KEY)[id] | 0) + 1); var art = b.closest(".psh-c"); if (art) { var tmp = D.createElement("div"); tmp.innerHTML = card(byId[id], cart().items[id] || 0, !!load(FAV)[id]); art.replaceWith(tmp.firstChild); } paintBar(); bump($(".psh-bi", bar)); return; }
      if ((b = t.closest("[data-q]"))) {
        var qid = +b.getAttribute("data-q"); setQ(qid, (load(KEY)[qid] | 0) + (+b.getAttribute("data-d")));
        if (st.view === "co") { if (!cart().n) { st.view = "list"; render(); } else { var ln = b.closest(".psh-line"), cq = cart().items[qid] || 0; if (ln && !cq) ln.remove(); else if (ln) $("b", $(".psh-step", ln)).textContent = cq; refreshTotals(); } }
        else { var a2 = b.closest(".psh-c"); if (a2) { var t2 = D.createElement("div"); t2.innerHTML = card(byId[qid], cart().items[qid] || 0, !!load(FAV)[qid]); a2.replaceWith(t2.firstChild); } paintBar(); }
        return;
      }
      if ((b = t.closest("[data-cat]"))) { var cv = b.getAttribute("data-cat"); st.cat = st.cat === cv ? "" : cv; qa("[data-cat]", view).forEach(function (x) { var on = x.getAttribute("data-cat") === st.cat; x.classList.toggle("on", on); x.setAttribute("aria-selected", on); }); var h3 = $("#psh-grid h3", view); if (h3) h3.textContent = st.cat === "fav" ? T("Избранное") : st.cat && S.cats[st.cat] ? S.cats[st.cat].n : T("Популярные товары"); refreshGrid(); return; }
      if ((b = t.closest("[data-pet]"))) { var pv = b.getAttribute("data-pet"); st.pet = st.pet === pv ? "" : pv; qa("[data-pet]", view).forEach(function (x) { x.classList.toggle("on", x.getAttribute("data-pet") === st.pet); }); refreshGrid(); return; }
      if ((b = t.closest("[data-more]"))) { var art3 = b.closest(".psh-c"); if (art3) { var was = art3.classList.contains("open"); qa(".psh-c.open", view).forEach(function (x) { x.classList.remove("open"); }); art3.classList.toggle("open", !was); } return; }
      if ((b = t.closest("[data-psh-to]"))) { scrollToEl($("#psh-grid", view)); return; }
      if (t.closest("[data-zmap]")) { st.mapOn = !st.mapOn; var zm = $(".psh-zmap", view); if (zm) zm.hidden = !st.mapOn; t.closest("[data-zmap]").textContent = T(st.mapOn ? "Скрыть карту" : "Показать на карте"); if (st.mapOn) zonesMap(); return; }
      if ((b = t.closest("[data-go]"))) { st.view = b.getAttribute("data-go"); st.err = ""; render(); return; }
      if (t.closest("[data-find]")) { var ai = $('input[name="psh-addr"]', view); if (ai) st.addr = ai.value; geocode(); return; }
      if (t.closest("[data-here]")) { st.geo = "busy"; refreshTotals(); navigator.geolocation.getCurrentPosition(function (p) { setPoint(p.coords.latitude, p.coords.longitude, true); }, function () { st.geo = st.lat ? (zoneAt(st.lat, st.lng) ? "in" : "out") : ""; refreshTotals(); }, { enableHighAccuracy: true, timeout: 10000 }); return; }
      if (t.closest("[data-order]")) { send(); return; }
    });
    box.addEventListener("change", function (e) {
      var t = e.target;
      if (t.name === "psh-mode") { st.mode = t.value; st.err = ""; render(true); return; }
      if (t.name === "psh-ord") { st.order = +t.value; qa(".psh-ord", view).forEach(function (l) { l.classList.toggle("on", $("input", l).checked); }); refreshTotals(); return; }
      if (t.name === "psh-zone") { var was = st.other; st.other = t.value === ""; st.zone = t.value; st.err = ""; if (was !== st.other) render(true); else { qa(".psh-zc", view).forEach(function (l) { l.classList.toggle("on", $("input", l).checked); }); refreshTotals(); } return; }
      if (t.name === "psh-pay") { st.pay = t.value; qa(".psh-pays label", view).forEach(function (l) { l.classList.toggle("on", $("input", l).checked); }); var k = $(".psh-kaspi", view); if (k) k.hidden = st.pay !== "kaspi"; return; }
      if (t.name === "psh-addr" && t.value.trim() !== st.addr.trim()) { st.addr = t.value; geocode(); }
    });
    box.addEventListener("input", function (e) {
      var t = e.target;
      if (t.hasAttribute("data-psh-q")) { st.q = t.value; refreshGrid(); return; }
      if (t.name === "psh-zq") { st.zq = t.value; var zs = $(".psh-zones", view); if (zs) { var tmp = D.createElement("div"); tmp.innerHTML = zoneCards(); var nz = $(".psh-zones", tmp); if (nz) zs.replaceWith(nz); } return; }
      if (t.name === "psh-addr") { st.addr = t.value; if (st.geo === "in" || st.geo === "out" || st.geo === "none") { st.geo = ""; st.lat = 0; st.lng = 0; if (mk && map) { map.removeLayer(mk); mk = null; } } refreshTotals(); }
      else if (t.name === "psh-flat") { st.flat = t.value; refreshTotals(); }
      else if (t.name === "psh-name") { st.name = t.value; refreshTotals(); }
      else if (t.name === "psh-phone") { st.phone = t.value; refreshTotals(); }
      else if (t.name === "psh-note") st.note = t.value;
    });
    box.addEventListener("keydown", function (e) {
      if (e.key === "Enter" && e.target.name === "psh-addr") { e.preventDefault(); st.addr = e.target.value; geocode(); }
      if (e.key === "Enter" && e.target.hasAttribute && e.target.hasAttribute("data-psh-q")) { e.preventDefault(); if (W.PGKB_HIDE) W.PGKB_HIDE(); else e.target.blur(); scrollToEl($("#psh-grid", view)); }
    });
    W.addEventListener("storage", function (e) { if (e.key === KEY && st.view !== "co") render(true); });
    /* корзина в шапке магазина (приложение) */
    if (page) page.addEventListener("click", function (e) { if (e.target.closest("[data-psh-cart]") && cart().n) { st.view = "co"; st.err = ""; render(); } });
    box._pshRender = render;

    render(true);
    var hm = /^#p(\d+)$/.exec(location.hash || "");
    if (hm && byId[hm[1]]) setTimeout(function () { var a = $("#p" + hm[1], view); if (a) { a.classList.add("hl"); a.scrollIntoView({ block: "center", behavior: "smooth" }); } }, 300);
  }

  function init() { qa(".psh[data-psh]").forEach(setup); if (!D.querySelector('.psh[data-psh="site"]')) D.documentElement.classList.remove("psh-bar-on"); }
  var prev = W.PGInitPage;
  W.PGInitPage = function () { if (prev) prev.apply(this, arguments); setTimeout(init, 0); };
  if (D.readyState === "loading") D.addEventListener("DOMContentLoaded", init); else init();
})();
