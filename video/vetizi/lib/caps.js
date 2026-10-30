// Единый движок надписей. Группа: <div class="cap-g" data-in data-out> с .cap-t (заголовок) и .cap-d p (описания).
// Времена — секунды ГЛОБАЛЬНОГО времени ролика; t0 — начало сцены, чтобы перевести в локальное время её таймлайна.
// Заголовок появляется по словам (blur 8 → 0), описание — целиком; уход — размытием. data-at / data-until — смена описания.
(function () {
  function split(node, words) {
    Array.prototype.slice.call(node.childNodes).forEach(function (n) {
      if (n.nodeType === 3) {
        var frag = document.createDocumentFragment();
        n.textContent.split(/(\s+)/).forEach(function (p) {
          if (!p) return;
          if (/^\s+$/.test(p)) { frag.appendChild(document.createTextNode(p)); return; }
          var s = document.createElement('span'); s.className = 'w'; s.textContent = p; frag.appendChild(s); words.push(s);
        });
        node.replaceChild(frag, n);
      } else if (n.nodeType === 1 && n.tagName !== 'BR') split(n, words);
    });
  }
  function build(tl, root, t0) {
    root.querySelectorAll('.cap-g').forEach(function (g) {
      var tIn = parseFloat(g.dataset.in) - t0, tOut = parseFloat(g.dataset.out) - t0;
      var words = [];
      split(g.querySelector('.cap-t'), words);
      words.forEach(function (w, i) {
        tl.fromTo(w, { opacity: 0, filter: 'blur(8px)' }, { opacity: 1, filter: 'blur(0px)', duration: 0.18, ease: 'power4.out' }, tIn + i * 0.05);
      });
      g.querySelectorAll('.cap-d p').forEach(function (p) {
        var at = parseFloat(p.dataset.at) - t0;
        tl.fromTo(p, { opacity: 0, filter: 'blur(8px)' }, { opacity: 1, filter: 'blur(0px)', duration: 0.22, ease: 'power4.out' }, at);
        if (p.dataset.until) tl.to(p, { opacity: 0, filter: 'blur(8px)', duration: 0.15, ease: 'power3.in' }, parseFloat(p.dataset.until) - t0);
      });
      if (tOut < 90) {
        tl.to(words, { opacity: 0, filter: 'blur(10px)', duration: 0.15, ease: 'power3.in' }, tOut - 0.15);
        tl.to(g.querySelectorAll('.cap-d p'), { opacity: 0, filter: 'blur(8px)', duration: 0.15, ease: 'power3.in' }, tOut - 0.15);
      }
    });
  }
  window.CAPS = { build: build };
})();
