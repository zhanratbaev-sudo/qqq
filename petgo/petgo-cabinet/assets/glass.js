/* PetGo — «жидкое стекло» (Liquid Glass), матовое.
   Идея как в dashersw/liquid-glass-js: край стекла преломляет то, что под ним (сильнее к ободку, с усилением в углах),
   плюс размытие и лёгкий тон. Сделано без снимка страницы и WebGL: карта смещения рисуется на canvas под размер
   элемента и подключается как SVG-фильтр в backdrop-filter. Поэтому стекло «живое»: работает при прокрутке,
   на закреплённых панелях и с анимациями под ним.
   Преломление умеют браузеры на Chromium (Chrome, Edge, Яндекс, Samsung). В Safari и Firefox остаётся
   обычное матовое стекло из CSS (blur) — выглядит так же, только без искажения по краю.
   Использование: <div data-glass data-glass-bezel="18" data-glass-scale="42" data-glass-blur="9">, затем PGGlass.scan(). */
(function () {
  "use strict";
  var ua = navigator.userAgent || "";
  var chromium = !!(navigator.userAgentData && navigator.userAgentData.brands && navigator.userAgentData.brands.some(function (b) { return b.brand === "Chromium"; })) ||
    (/Chrome\/\d+/.test(ua) && !/CriOS|FxiOS|EdgiOS/.test(ua) && !!window.chrome);
  var mq = window.matchMedia ? window.matchMedia("(prefers-reduced-transparency: reduce), (prefers-contrast: more)") : null;
  var supported = chromium && !!window.ResizeObserver && !(mq && mq.matches);
  var NS = "http://www.w3.org/2000/svg", defs = null, seq = 0, items = [];

  function host() {
    if (defs) return defs;
    var svg = document.createElementNS(NS, "svg");
    svg.setAttribute("aria-hidden", "true");
    svg.setAttribute("width", "0");
    svg.setAttribute("height", "0");
    svg.style.cssText = "position:absolute;width:0;height:0;overflow:hidden;pointer-events:none";
    defs = document.createElementNS(NS, "defs");
    svg.appendChild(defs);
    document.body.appendChild(svg);
    return defs;
  }

  /* карта смещения: R — сдвиг по X, G — по Y, 128 — без сдвига. Внутри ободка картинка тянется к центру (как выпуклая линза). */
  function map(w, h, r, bezel) {
    var c = document.createElement("canvas");
    c.width = w; c.height = h;
    var ctx = c.getContext("2d"), img = ctx.createImageData(w, h), d = img.data;
    var hx = w / 2, hy = h / 2, x, y, i = 0;
    r = Math.min(r, hx, hy);
    for (y = 0; y < h; y++) {
      for (x = 0; x < w; x++, i += 4) {
        var px = x + 0.5 - hx, py = y + 0.5 - hy;
        var qx = Math.abs(px) - (hx - r), qy = Math.abs(py) - (hy - r);
        var nx = 0, ny = 0, dist;
        if (qx > 0 && qy > 0) {
          var len = Math.sqrt(qx * qx + qy * qy) || 1;
          dist = r - len;
          nx = (px < 0 ? -1 : 1) * qx / len; ny = (py < 0 ? -1 : 1) * qy / len;
        } else if (qx > qy) {
          dist = -qx + r; nx = px < 0 ? -1 : 1;
        } else {
          dist = -qy + r; ny = py < 0 ? -1 : 1;
        }
        var m = 0;
        if (dist < bezel) {
          var t = 1 - Math.max(dist, 0) / bezel;
          m = t * t * (0.55 + 0.45 * t);
        }
        d[i] = Math.round(128 - nx * m * 127);
        d[i + 1] = Math.round(128 - ny * m * 127);
        d[i + 2] = 128;
        d[i + 3] = 255;
      }
    }
    ctx.putImageData(img, 0, 0);
    return c.toDataURL("image/png");
  }

  function num(el, name, def) {
    var v = parseFloat(el.getAttribute("data-glass-" + name));
    return isNaN(v) ? def : v;
  }

  function build(it) {
    var el = it.el, w = Math.round(el.offsetWidth), h = Math.round(el.offsetHeight);
    if (w < 8 || h < 8 || w * h > 900000) return;
    if (it.w === w && it.h === h) return;
    it.w = w; it.h = h;
    var cs = getComputedStyle(el);
    var r = parseFloat(cs.borderTopLeftRadius) || 0;
    var bezel = Math.min(num(el, "bezel", 16), Math.min(w, h) / 2);
    var f = it.f;
    if (!f) {
      f = it.f = document.createElementNS(NS, "filter");
      f.setAttribute("id", "pgg-" + (++seq));
      f.setAttribute("filterUnits", "userSpaceOnUse");
      f.setAttribute("primitiveUnits", "userSpaceOnUse");
      f.setAttribute("color-interpolation-filters", "sRGB");
      f.innerHTML = '<feImage result="m" preserveAspectRatio="none"/>' +
        '<feDisplacementMap in="SourceGraphic" in2="m" xChannelSelector="R" yChannelSelector="G" result="d"/>' +
        '<feGaussianBlur in="d" result="b"/>' +
        '<feColorMatrix in="b" type="saturate"/>';
      host().appendChild(f);
    }
    f.setAttribute("x", "0"); f.setAttribute("y", "0");
    f.setAttribute("width", w); f.setAttribute("height", h);
    var im = f.childNodes[0];
    im.setAttribute("x", "0"); im.setAttribute("y", "0");
    im.setAttribute("width", w); im.setAttribute("height", h);
    /* карту рисуем в половинном размере (в 4 раза быстрее), браузер растягивает её сам */
    var q = w * h > 60000 ? 2 : 1;
    im.setAttribute("href", map(Math.round(w / q), Math.round(h / q), r / q, bezel / q));
    f.childNodes[1].setAttribute("scale", num(el, "scale", 40));
    f.childNodes[2].setAttribute("stdDeviation", num(el, "blur", 9));
    f.childNodes[3].setAttribute("values", num(el, "sat", 1.45));
    var v = "url(#" + f.getAttribute("id") + ")";
    el.style.webkitBackdropFilter = v;
    el.style.backdropFilter = v;
    el.classList.add("is-lg");
  }

  var ro = supported ? new ResizeObserver(function (es) {
    es.forEach(function (e) {
      for (var i = 0; i < items.length; i++) if (items[i].el === e.target) { build(items[i]); break; }
    });
  }) : null;

  function apply(el) {
    if (!supported || !el || el.__pgg) return;
    el.__pgg = true;
    var it = { el: el, w: 0, h: 0, f: null };
    items.push(it);
    build(it);
    ro.observe(el);
  }

  function scan(root) {
    if (!supported) return;
    var list = (root || document).querySelectorAll("[data-glass]");
    for (var i = 0; i < list.length; i++) apply(list[i]);
  }

  window.PGGlass = { supported: supported, apply: apply, scan: scan };
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", function () { scan(); });
  else scan();
})();
