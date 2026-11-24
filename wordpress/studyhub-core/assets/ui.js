/* StudyHub UI — общие инструменты для сайта, кабинета и панели: иконки, окна, уведомления, запросы. */
(function () {
  'use strict';
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  var C = { logo: '' };
  var ICONS = {
    home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
    grad: '<path d="M22 9 12 4 2 9l10 5 10-5Z"/><path d="M6 11v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/><path d="M22 9v6"/>',
    grid: '<rect x="3" y="3" width="7.5" height="7.5" rx="2.2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2.2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2.2"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2.2"/>',
    chat: '<path d="M21 12a8.5 8.5 0 0 1-12.3 7.6L3 21l1.4-5.1A8.5 8.5 0 1 1 21 12Z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/>',
    sparkle: '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9Z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8Z"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
    bell: '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    chevDown: '<path d="m6 9 6 6 6-6"/>',
    chevRight: '<path d="m9 6 6 6-6 6"/>',
    arrowRight: '<path d="M5 12h14M13 6l6 6-6 6"/>',
    arrowUp: '<path d="M12 19V5M5 12l7-7 7 7"/>',
    book: '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2Z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7Z"/>',
    cube: '<path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.7Z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/>',
    pencil: '<path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
    ruler: '<path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.41 2.41 0 0 1 0-3.4l2.6-2.6a2.41 2.41 0 0 1 3.4 0Z"/><path d="m14.5 12.5 2-2M11.5 9.5l2-2M8.5 6.5l2-2M17.5 15.5l2-2"/>',
    building: '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/>',
    laptop: '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M2 20h20"/>',
    palette: '<circle cx="13.5" cy="6.5" r=".6"/><circle cx="17.5" cy="10.5" r=".6"/><circle cx="8.5" cy="7.5" r=".6"/><circle cx="6.5" cy="12.5" r=".6"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.9 0 1.6-.7 1.6-1.7 0-.4-.2-.8-.4-1.1-.3-.3-.4-.7-.4-1.1a1.6 1.6 0 0 1 1.6-1.6h2c3 0 5.6-2.5 5.6-5.6C22 6 17.5 2 12 2Z"/>',
    doc: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
    clip: '<path d="m21.4 11.1-9.2 9.2a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5"/>',
    lock: '<rect x="4" y="11" width="16" height="10" rx="2.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    star: '<path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4l-5.9 3.1 1.2-6.5L2.5 9.4l6.6-.9Z"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    checks: '<path d="M17 7 6.5 17.5 2 13"/><path d="m22 8-9.5 9.5-1.5-1.5"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    globe: '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10Z"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    eyeOff: '<path d="M9.9 4.2A10 10 0 0 1 12 4c6.5 0 10 8 10 8a17 17 0 0 1-2.2 3.2M6.6 6.6A17 17 0 0 0 2 12s3.5 8 10 8a9.7 9.7 0 0 0 5.4-1.6"/><path d="M14.1 14.1a3 3 0 1 1-4.2-4.2M2 2l20 20"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
    mask: '<path d="M3 11h18"/><path d="M5.5 11 7 5.6a1 1 0 0 1 1.2-.7L12 6l3.8-1.1a1 1 0 0 1 1.2.7L18.5 11"/><circle cx="7.5" cy="16.5" r="3"/><circle cx="16.5" cy="16.5" r="3"/><path d="M10.5 16.5h3"/>',
    phone: '<rect x="5" y="2" width="14" height="20" rx="3"/><path d="M11 18h2"/>',
    share: '<path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M16 6l-4-4-4 4M12 2v13"/>',
    plusSq: '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/>',
    image: '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
    shapes: '<rect x="3" y="11" width="10" height="10" rx="2.5"/><circle cx="15.5" cy="8.5" r="5.5"/><path d="M9 11V8"/>',
    lecture: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8M8 9h2"/>',
    tag: '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
    homeFill: '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z"/>',
    trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>'
  };
  function icon(name, cls) {
    return '<svg class="icon ' + (cls || '') + '" viewBox="0 0 24 24" aria-hidden="true">' + (ICONS[name] || ICONS.doc) + '</svg>';
  }
  var starSeq = 0;
  function aiStar(cls) {
    var id = 'sg' + (++starSeq);
    return '<svg class="ai-star ' + (cls || '') + '" viewBox="0 0 100 100" aria-hidden="true"><defs>' +
      '<radialGradient id="' + id + '" cx="36%" cy="30%" r="80%"><stop offset="0" stop-color="#FFE1C4"/><stop offset=".42" stop-color="#FF9B52"/><stop offset="1" stop-color="#E2541C"/></radialGradient></defs>' +
      '<path d="M50 3C54 31 69 46 97 50 69 54 54 69 50 97 46 69 31 54 3 50 31 46 46 31 50 3Z" fill="url(#' + id + ')"/>' +
      '<path d="M50 15c2.6 15 8.5 23.5 21 29" stroke="rgba(255,255,255,.6)" stroke-width="3.5" fill="none" stroke-linecap="round"/></svg>';
  }
  function brandMark(logo) {
    if (arguments[0]) return '<img src="' + esc(arguments[0]) + '" alt="">';
    return '<svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M16 7 7.5 27" style="stroke:var(--text)" stroke-width="3.4"/><path d="M16 7 24.5 27" style="stroke:var(--accent)" stroke-width="3.4"/>' +
      '<path d="M10.5 20.5c3.6 1.6 7.4 1.6 11 0" style="stroke:var(--text)" stroke-width="2.4"/><circle cx="16" cy="5.5" r="2.6" style="fill:var(--text)" stroke="none"/></svg>';
  }
  function anonAvatar(seed, cls) {
    var tones = ['peach', 'lilac', 'mint', 'sky', 'sand', 'rose'];
    var n = 0; seed = String(seed || '');
    for (var i = 0; i < seed.length; i++) n = (n * 31 + seed.charCodeAt(i)) >>> 0;
    return '<div class="' + (cls || 'anon-ava') + ' tone-' + tones[n % tones.length] + ' tone-bg">' + icon('mask') + '</div>';
  }
  function stars(n) {
    var out = '<span class="stars" aria-label="' + n + '/5">';
    for (var i = 1; i <= 5; i++) out += '<span class="' + (i <= n ? '' : 'off') + '">' + icon('star') + '</span>';
    return out + '</span>';
  }

  function store(kind) {
    var s = null;
    try { s = window[kind]; s.setItem('__t', '1'); s.removeItem('__t'); } catch (e) { s = null; }
    return {
      get: function (k) { try { return s ? s.getItem(k) : null; } catch (e) { return null; } },
      set: function (k, v) { try { if (s) s.setItem(k, v); } catch (e) { /* ignore */ } },
      del: function (k) { try { if (s) s.removeItem(k); } catch (e) { /* ignore */ } }
    };
  }
  function withQuery(url, q) { return url + (url.indexOf('?') > -1 ? '&' : '?') + q; }
  function api(base, path, opts, headers) {
    opts = opts || {};
    var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: Object.assign({ 'X-StudyHub': '1' }, headers || {}) };
    if (opts.body instanceof FormData) init.body = opts.body;
    else if (opts.body) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.body); }
    var url = /^https?:/.test(path) ? path : base + (base.indexOf('?') > -1 ? path.replace('?', '&') : path);
    return fetch(url, init).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok) { var err = new Error((data && data.message) || 'error'); err.status = res.status; err.code = data && data.code; throw err; }
        return data;
      });
    });
  }
  var toastTimer;
  function toast(msg, isError) {
    var old = document.querySelector('.toast'); if (old) old.remove();
    var el = document.createElement('div');
    el.className = 'toast' + (isError ? ' error' : ''); el.setAttribute('role', 'status'); el.textContent = msg;
    document.body.appendChild(el);
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { el.remove(); }, 3200);
  }
  function sheet(html, onMount) {
    var ov = document.createElement('div');
    ov.className = 'overlay';
    ov.innerHTML = '<div class="sheet" role="dialog" aria-modal="true"><div class="grabber"></div>' + html + '</div>';
    function close() { ov.remove(); document.removeEventListener('keydown', onKey); }
    function onKey(e) { if (e.key === 'Escape') close(); }
    ov.addEventListener('click', function (e) { if (e.target === ov || e.target.closest('[data-close]')) close(); });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(ov);
    if (onMount) onMount(ov.querySelector('.sheet'), close);
    return close;
  }
  function fmtTime(ts) { var d = new Date(ts * 1000); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
  function fmtDate(ts, lang) {
    if (!ts) return '';
    var d = new Date(ts * 1000), n = new Date();
    if (d.toDateString() === n.toDateString()) return fmtTime(ts);
    try { return d.toLocaleDateString(lang === 'kk' ? 'kk-KZ' : 'ru-RU', { day: 'numeric', month: 'short' }); } catch (e) { return d.toLocaleDateString(); }
  }
  function fmtSize(b) { return b < 1024 ? b + ' B' : b < 1048576 ? Math.round(b / 1024) + ' KB' : (b / 1048576).toFixed(1) + ' MB'; }
  function registerSW(url) {
    if ('serviceWorker' in navigator && url && location.protocol === 'https:') {
      window.addEventListener('load', function () {
        navigator.serviceWorker.register(url, { scope: new URL(url, location.href).pathname }).catch(function () {});
      });
    }
  }
  window.SH = { esc: esc, icon: icon, aiStar: aiStar, brandMark: brandMark, anonAvatar: anonAvatar, stars: stars, store: store,
    withQuery: withQuery, api: api, toast: toast, sheet: sheet, fmtTime: fmtTime, fmtDate: fmtDate, fmtSize: fmtSize, registerSW: registerSW };
})();
