// Кадр ролика без ноутбука: каждая сцена рисует содержимое 1920x1247 (как раньше), а SCREEN.map
// ставит его на весь кадр 1920x1080 — по центру, с медленным наездом внутри сцены (детерминированно от времени T).
(function () {
  var SW = 1920, SH = 1247;
  // сцены ролика [начало, конец] — наезд начинается заново в каждой (стык закрывает переход)
  var SCENES = [[0, 7.5], [7.5, 16.5], [16.5, 23], [23, 30], [30, 39], [39, 44], [44, 50]];
  function push(T) {
    for (var i = 0; i < SCENES.length; i++) {
      var a = SCENES[i][0], b = SCENES[i][1];
      if (T < b || i === SCENES.length - 1) {
        var k = Math.max(0, Math.min(1, (T - a) / (b - a)));
        return 1 + 0.035 * (0.5 - 0.5 * Math.cos(Math.PI * k));
      }
    }
    return 1;
  }
  // el: элемент SW x SH с transform-origin 0 0
  function map(el, T) {
    var s = push(T);
    el.style.transform = 'translate(960px,540px) scale(' + s.toFixed(5) + ') translate(' + (-SW / 2) + 'px,' + (-SH / 2) + 'px)';
  }
  window.SCREEN = { map: map, SW: SW, SH: SH };
})();
