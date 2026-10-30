// Камера ноутбука и наложение HTML-содержимого на его экран.
// Всё — чистые функции глобального времени ролика T (секунды), поэтому 3D-комната (room.html)
// и сцены на экране (каждая в своём файле) считают одно и то же положение независимо друг от друга.
(function () {
  // Углы дисплея модели macbook.glb после нормализации VI3D.loadDevice (ширина 4.5), мир: ноутбук в начале координат.
  var D = {
    tl: [-2.1723, 1.5731, -1.5405], tr: [2.1723, 1.5731, -1.5405],
    br: [2.1723, -1.248, -1.4913], bl: [-2.1723, -1.248, -1.4913],
  };
  var SW = 1920, SH = 1247; // размер «экрана» для содержимого: соотношение дисплея 1.54
  var FOV = 30;

  // Ключевые положения камеры: [время, позиция, точка взгляда]
  var KEYS = [
    [0.0, [0.0, 3.4, 10.6], [0.0, -0.35, -0.6]],   // общий план: ноутбук на столе в тёмной комнате
    [3.2, [0.0, 2.6, 8.4], [0.0, -0.1, -0.9]],
    [7.2, [0.0, 0.55, 4.7], [0.0, 0.16, -1.52]],   // близко: экран почти во весь кадр
    [29.4, [0.0, 0.55, 4.7], [0.0, 0.16, -1.52]],
    [30.6, [0.0, 0.42, 3.95], [0.0, 0.12, -1.52]],  // CRM: камера ближе, чтобы читался интерфейс
    [38.4, [0.0, 0.42, 3.95], [0.0, 0.12, -1.52]],
    [39.6, [0.0, 0.55, 4.7], [0.0, 0.16, -1.52]],
    [44.0, [0.0, 0.55, 4.7], [0.0, 0.16, -1.52]],
    [48.0, [0.0, 3.0, 9.6], [0.0, -0.3, -0.7]],    // финал: отъезд
    [60.0, [0.0, 3.1, 10.0], [0.0, -0.3, -0.7]],
  ];
  function ease(k) { return k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2; }
  function lerp(a, b, k) { return [a[0] + (b[0] - a[0]) * k, a[1] + (b[1] - a[1]) * k, a[2] + (b[2] - a[2]) * k]; }
  function cam(T) {
    var i = 0;
    while (i < KEYS.length - 2 && T > KEYS[i + 1][0]) i++;
    var a = KEYS[i], b = KEYS[i + 1];
    var k = Math.max(0, Math.min(1, (T - a[0]) / (b[0] - a[0])));
    k = ease(k);
    var pos = lerp(a[1], b[1], k), look = lerp(a[2], b[2], k);
    // лёгкое «дыхание» камеры на близком плане (детерминированно от T)
    var drift = Math.sin(T * 0.35) * 0.04;
    pos = [pos[0] + drift, pos[1] + Math.sin(T * 0.27) * 0.02, pos[2]];
    return { pos: pos, look: look, fov: FOV };
  }

  // Проекция точки мира в пиксели кадра 1920x1080 (та же математика, что у THREE.PerspectiveCamera)
  function project(p, c) {
    var f = [c.look[0] - c.pos[0], c.look[1] - c.pos[1], c.look[2] - c.pos[2]];
    var fl = Math.hypot(f[0], f[1], f[2]); f = [f[0] / fl, f[1] / fl, f[2] / fl];
    var up = [0, 1, 0];
    var r = [f[1] * up[2] - f[2] * up[1], f[2] * up[0] - f[0] * up[2], f[0] * up[1] - f[1] * up[0]];
    var rl = Math.hypot(r[0], r[1], r[2]); r = [r[0] / rl, r[1] / rl, r[2] / rl];
    var u = [r[1] * f[2] - r[2] * f[1], r[2] * f[0] - r[0] * f[2], r[0] * f[1] - r[1] * f[0]];
    var d = [p[0] - c.pos[0], p[1] - c.pos[1], p[2] - c.pos[2]];
    var x = d[0] * r[0] + d[1] * r[1] + d[2] * r[2];
    var y = d[0] * u[0] + d[1] * u[1] + d[2] * u[2];
    var z = d[0] * f[0] + d[1] * f[1] + d[2] * f[2];
    var t = Math.tan((c.fov * Math.PI / 180) / 2);
    var ndcX = x / (z * t * (1920 / 1080)), ndcY = y / (z * t);
    return [(ndcX + 1) / 2 * 1920, (1 - ndcY) / 2 * 1080];
  }

  // Матрица перспективного переноса прямоугольника w x h в четырёхугольник q (по часовой с левого верхнего)
  function homography(w, h, q) {
    var x0 = q[0][0], y0 = q[0][1], x1 = q[1][0], y1 = q[1][1], x2 = q[2][0], y2 = q[2][1], x3 = q[3][0], y3 = q[3][1];
    var dx1 = x1 - x2, dx2 = x3 - x2, dy1 = y1 - y2, dy2 = y3 - y2;
    var sx = x0 - x1 + x2 - x3, sy = y0 - y1 + y2 - y3;
    var den = dx1 * dy2 - dx2 * dy1;
    var g = (sx * dy2 - dx2 * sy) / den, hh = (dx1 * sy - sx * dy1) / den;
    var a = x1 - x0 + g * x1, b = x3 - x0 + hh * x3, c = x0;
    var d = y1 - y0 + g * y1, e = y3 - y0 + hh * y3, f = y0;
    // масштаб из единичного квадрата в w x h
    a /= w; d /= w; g /= w; b /= h; e /= h; hh /= h;
    return 'matrix3d(' + [a, d, 0, g, b, e, 0, hh, 0, 0, 1, 0, c, f, 0, 1].map(function (v) { return +v.toFixed(9); }).join(',') + ')';
  }

  function quad(T) {
    var c = cam(T);
    return [project(D.tl, c), project(D.tr, c), project(D.br, c), project(D.bl, c)];
  }

  // Ставит элемент размером SW x SH ровно на экран ноутбука в момент T (transform-origin: 0 0)
  function mapScreen(el, T) { el.style.transform = homography(SW, SH, quad(T)); }

  window.LAPTOP = { cam: cam, quad: quad, mapScreen: mapScreen, SW: SW, SH: SH, D: D };
})();
