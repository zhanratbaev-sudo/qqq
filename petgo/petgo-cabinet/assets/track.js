/* PetGo 34.0 — приложение сотрудников: живая прогулка для хозяина.
   Пока у няни заказ «на месте» / «с питомцем» и приложение открыто, телефон передаёт местоположение
   (не чаще раза в 8 секунд и только когда няня сдвинулась). Быстрые статусы и фото с местом на карте.
   Ещё здесь кнопки «Скопировать» и «Поделиться» (ссылка-анкета, пароль клиента). */
(function () {
  "use strict";
  var D = document, W = window, L = W.PGT_L || {};
  function cfg() { try { return JSON.parse(D.getElementById("pgc-cfg").textContent); } catch (e) { return null; } }
  function post(data, file) {
    var c = cfg();
    if (!c) return Promise.reject(new Error("nocfg"));
    var fd = new FormData();
    fd.append("action", "petgo_trk"); fd.append("n", c.no);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    if (file) fd.append("photo", file, "walk.jpg");
    return fetch(c.aj, { method: "POST", body: fd, credentials: "same-origin" }).then(function (r) { return r.json(); });
  }
  function toast(t) {
    var box = D.getElementById("pgc-toasts");
    if (!box) { alert(t); return; }
    var el = D.createElement("div");
    el.className = "pgc-toast"; el.textContent = t;
    box.appendChild(el);
    requestAnimationFrame(function () { requestAnimationFrame(function () { el.classList.add("in"); }); });
    setTimeout(function () { el.classList.remove("in"); setTimeout(function () { el.remove(); }, 300); }, 2600);
  }
  function shrink(file) {
    return new Promise(function (ok) {
      try {
        var u = URL.createObjectURL(file), im = new Image();
        im.onload = function () {
          var s = Math.min(1, 1600 / Math.max(im.width, im.height)), c = D.createElement("canvas");
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

  /* ---------- трансляция местоположения ---------- */
  var T = { watch: null, active: [], last: null, sentAt: 0, lock: null, checkT: 0, checked: 0, pos: null, state: "" };
  function dist(a, b) {
    var r = 6371000, la = (b[0] - a[0]) * Math.PI / 180, lo = (b[1] - a[1]) * Math.PI / 180;
    var x = Math.sin(la / 2) * Math.sin(la / 2) + Math.cos(a[0] * Math.PI / 180) * Math.cos(b[0] * Math.PI / 180) * Math.sin(lo / 2) * Math.sin(lo / 2);
    return 2 * r * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
  }
  function setState(s) {
    T.state = s;
    var txt = (s === "on" ? L.trk_on : s === "off" ? L.trk_off : L.trk_wait) || "";
    Array.prototype.forEach.call(D.querySelectorAll("[data-trk-state]"), function (el) {
      if (el.textContent !== txt) el.textContent = txt;
      var box = el.closest(".pgt");
      if (box) { box.classList.toggle("on", s === "on"); box.classList.toggle("off", s === "off"); }
    });
    var pill = D.getElementById("pgt-pill");
    if (T.active.length) {
      var html = '<i></i><span>' + (L.trk_banner || "") + " · " + T.active.map(function (o) { return String(o.pet).replace(/[<>&"]/g, ""); }).join(", ") + "</span>";
      if (!pill) { pill = D.createElement("div"); pill.id = "pgt-pill"; D.body.appendChild(pill); }
      if (pill.className !== "pgt-pill " + s) pill.className = "pgt-pill " + s;
      if (pill.innerHTML !== html) pill.innerHTML = html;
    } else if (pill) pill.remove();
  }
  function send(p, force) {
    var now = Date.now(), pt = [p.coords.latitude, p.coords.longitude];
    T.pos = { lat: pt[0], lng: pt[1], acc: Math.round(p.coords.accuracy || 0) };
    setState("on");
    if (!force) {
      if (now - T.sentAt < 8000) return;
      if (T.last && dist(T.last, pt) < 8 && now - T.sentAt < 45000) return;
    }
    T.sentAt = now; T.last = pt;
    post({ op: "pt", lat: pt[0], lng: pt[1], acc: T.pos.acc }).then(function (j) { if (j && j.ok && j.n === 0) stop(); }).catch(function () {});
  }
  function wake() {
    if (!("wakeLock" in navigator) || T.lock || D.hidden) return;
    navigator.wakeLock.request("screen").then(function (l) { T.lock = l; l.addEventListener("release", function () { T.lock = null; }); }).catch(function () {});
  }
  function start() {
    if (T.watch !== null || !navigator.geolocation) { if (!navigator.geolocation) setState("off"); return; }
    setState("wait");
    T.watch = navigator.geolocation.watchPosition(send, function (e) { if (e && e.code === 1) setState("off"); }, { enableHighAccuracy: true, maximumAge: 5000, timeout: 30000 });
    wake();
  }
  function stop() {
    if (T.watch !== null && navigator.geolocation) navigator.geolocation.clearWatch(T.watch);
    T.watch = null; T.active = [];
    if (T.lock) { try { T.lock.release(); } catch (e) {} T.lock = null; }
    setState("");
  }
  function check() {
    clearTimeout(T.checkT);
    T.checked = Date.now();
    if (!cfg()) return;
    post({ op: "act" }).then(function (j) {
      if (!j || !j.ok) return;
      T.active = j.orders || [];
      if (T.active.length) start(); else stop();
      if (T.active.length) setState(T.state || "wait");
    }).catch(function () {}).then(function () { T.checkT = setTimeout(check, T.active.length ? 60000 : 120000); });
  }
  D.addEventListener("visibilitychange", function () { if (!D.hidden) { wake(); check(); } });
  /* после «Я пришёл» / «Забрал» страница обновляется — проверяем снова */
  var mo = new MutationObserver(function (list) {
    /* реагируем только на новое содержимое страницы или окна заказа, а не на свои же надписи */
    var fresh = false;
    for (var i = 0; i < list.length && !fresh; i++) {
      for (var j = 0; j < list[i].addedNodes.length; j++) {
        var n = list[i].addedNodes[j];
        if (n.nodeType === 1 && n.id !== "pgt-pill" && (n.matches(".pgt") || n.querySelector(".pgt"))) { fresh = true; break; }
      }
    }
    if (!fresh) return;
    if (T.watch === null && Date.now() - T.checked > 15000) check();
    setState(T.state || "wait");
  });

  /* ---------- быстрые статусы и фото с местом ---------- */
  D.addEventListener("click", function (e) {
    var b = e.target.closest && e.target.closest("[data-trk-st]");
    if (b) {
      var box = b.closest(".pgt");
      Array.prototype.forEach.call(box.querySelectorAll("[data-trk-st]"), function (x) { x.classList.toggle("on", x === b); });
      post({ op: "st", order: box.getAttribute("data-order"), st: b.getAttribute("data-trk-st") }).then(function (j) { if (!j || !j.ok) toast(L.neterr || "Error"); }).catch(function () { toast(L.neterr || "Error"); });
      return;
    }
    var c = e.target.closest && e.target.closest("[data-copy]");
    if (c) {
      e.preventDefault();
      var v = c.getAttribute("data-copy"), done = function () { toast(c.getAttribute("data-done") || L.pf_copied || "OK"); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(v).then(done, function () { fallback(v); done(); });
      else { fallback(v); done(); }
      return;
    }
    var s = e.target.closest && e.target.closest("[data-share]");
    if (s && navigator.share) {
      e.preventDefault();
      navigator.share({ title: "PetGo", text: s.getAttribute("data-text") || "", url: s.getAttribute("data-share") }).catch(function () {});
    }
  });
  function fallback(v) {
    var t = D.createElement("textarea");
    t.value = v; t.style.position = "fixed"; t.style.opacity = "0";
    D.body.appendChild(t); t.select();
    try { D.execCommand("copy"); } catch (e) {}
    t.remove();
  }
  D.addEventListener("change", function (e) {
    var inp = e.target;
    if (!inp.matches || !inp.matches("[data-trk-photo]") || !inp.files || !inp.files[0]) return;
    var box = inp.closest(".pgt"), lab = inp.closest("label"), file = inp.files[0], sp = lab.querySelector("span"), txt = sp.textContent;
    lab.classList.add("busy"); sp.textContent = L.trk_sending || "…";
    var geo = new Promise(function (ok) {
      if (T.pos && Date.now() - T.sentAt < 60000) return ok(T.pos);
      if (!navigator.geolocation) return ok(null);
      navigator.geolocation.getCurrentPosition(function (p) { ok({ lat: p.coords.latitude, lng: p.coords.longitude, acc: Math.round(p.coords.accuracy || 0) }); }, function () { ok(null); }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 20000 });
    });
    Promise.all([shrink(file), geo]).then(function (r) {
      var d = { op: "photo", order: box.getAttribute("data-order") };
      if (r[1]) { d.lat = r[1].lat; d.lng = r[1].lng; d.acc = r[1].acc; }
      return post(d, r[0]);
    }).then(function (j) {
      toast((j && j.msg) || L.neterr || "");
    }).catch(function () { toast(L.neterr || "Error"); }).then(function () { lab.classList.remove("busy"); sp.textContent = txt; inp.value = ""; });
  });

  function boot() {
    if (!D.getElementById("pgc")) return;
    mo.observe(D.body, { childList: true, subtree: true });
    check();
  }
  if (D.readyState === "loading") D.addEventListener("DOMContentLoaded", boot); else boot();
})();
