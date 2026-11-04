/* Общие приёмы ролика: заголовок встаёт маской, ответ набирается по словам. Всё детерминировано (без случайностей). */
window.K = {
  // разбить текст на слова-спаны (один раз при сборке сцены); возвращает спаны
  words: function (el) {
    if (el.__w) return el.__w;
    var parts = el.textContent.split(/(\s+)/);
    el.textContent = '';
    var out = [];
    parts.forEach(function (p) {
      if (/^\s+$/.test(p)) { el.appendChild(document.createTextNode(p)); return; }
      if (!p) return;
      var s = document.createElement('span');
      s.textContent = p;
      s.style.opacity = '0';
      el.appendChild(s);
      out.push(s);
    });
    el.__w = out;
    return out;
  },
  // набрать ответ по словам: 0.06 с на слово
  type: function (tl, el, at, per) {
    var w = K.words(el);
    tl.to(w, { opacity: 1, duration: 0.12, stagger: per || 0.06, ease: 'none' }, at);
    return at + w.length * (per || 0.06);
  },
  // заголовок: строка поднимается из-под маски
  title: function (tl, sel, at) {
    tl.fromTo(sel + ' > span', { yPercent: 115 }, { yPercent: 0, duration: 0.55, ease: 'power3.out' }, at);
  },
  titleOut: function (tl, sel, at) {
    tl.to(sel + ' > span', { yPercent: -115, duration: 0.4, ease: 'power2.in' }, at);
  },
  // пузырь появляется
  pop: function (tl, sel, at) {
    tl.fromTo(sel, { opacity: 0, scale: 0.86, y: 14 }, { opacity: 1, scale: 1, y: 0, duration: 0.35, ease: 'back.out(1.6)' }, at);
  },
  // три точки «печатает…»
  typing: function (tl, sel, from, to) {
    tl.set(sel, { display: 'none' }, 0);
    tl.set(sel, { display: 'flex' }, from);
    tl.fromTo(sel, { opacity: 0 }, { opacity: 1, duration: 0.15, immediateRender: false }, from);
    tl.fromTo(sel + ' i', { y: 0, opacity: 0.35 }, { y: -5, opacity: 1, duration: 0.2, stagger: 0.1, repeat: Math.max(1, Math.round((to - from) / 0.4)), yoyo: true, ease: 'sine.inOut' }, from);
    tl.set(sel, { display: 'none' }, to);
  },
};
