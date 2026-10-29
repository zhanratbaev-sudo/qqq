// Общая 3D-сцена Vet.izi: рендерер, студийный свет, модели MacBook/iPhone с экранами-canvas, знак Vet.izi.
// Всё рисуется из состояния, которое задаёт GSAP-таймлайн сцены (детерминированно, без часов).
(function () {
  var PI = Math.PI;

  function create(canvas, opts) {
    opts = opts || {};
    var renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, preserveDrawingBuffer: true, alpha: !!opts.alpha });
    renderer.setSize(1920, 1080, false);
    renderer.setPixelRatio(1);
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = opts.exposure || 1.05;
    renderer.outputEncoding = THREE.sRGBEncoding;
    if (opts.alpha) renderer.setClearColor(0x000000, 0);
    else renderer.setClearColor(new THREE.Color(opts.bg || '#08110F'), 1);

    var scene = new THREE.Scene();
    var camera = new THREE.PerspectiveCamera(opts.fov || 30, 1920 / 1080, 0.1, 100);

    // Окружение: тёмная студия с двумя-тремя мягкими полосами света (даёт блики на металле и стекле)
    var eC = document.createElement('canvas');
    eC.width = 1024; eC.height = 512;
    var ec = eC.getContext('2d');
    ec.fillStyle = '#05080a'; ec.fillRect(0, 0, 1024, 512);
    var strips = [[160, 120, 260, 70, 'rgba(255,236,214,0.95)'], [640, 90, 220, 50, 'rgba(210,235,255,0.75)'], [420, 300, 340, 26, 'rgba(255,255,255,0.35)']];
    strips.forEach(function (s) {
      var g = ec.createLinearGradient(s[0], 0, s[0] + s[2], 0);
      g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(0.5, s[4]); g.addColorStop(1, 'rgba(0,0,0,0)');
      ec.fillStyle = g; ec.fillRect(s[0], s[1], s[2], s[3]);
    });
    var eT = new THREE.CanvasTexture(eC);
    eT.mapping = THREE.EquirectangularReflectionMapping;
    var pm = new THREE.PMREMGenerator(renderer);
    scene.environment = pm.fromEquirectangular(eT).texture;
    eT.dispose(); pm.dispose();

    scene.add(new THREE.AmbientLight(0x2a3a36, 0.35));
    var key = new THREE.DirectionalLight(0xffe6cc, 2.0); key.position.set(-4, 5, 6); scene.add(key);
    var rim = new THREE.DirectionalLight(0xbfe4ff, 1.6); rim.position.set(5, 3, -4); scene.add(rim);

    return { renderer: renderer, scene: scene, camera: camera, render: function () { renderer.render(scene, camera); } };
  }

  // Мягкая контактная тень: радиальный градиент на плоскости под объектом
  function contactShadow(w, d, opacity) {
    var c = document.createElement('canvas'); c.width = 256; c.height = 256;
    var x = c.getContext('2d');
    var g = x.createRadialGradient(128, 128, 0, 128, 128, 128);
    g.addColorStop(0, 'rgba(0,0,0,1)'); g.addColorStop(1, 'rgba(0,0,0,0)');
    x.fillStyle = g; x.fillRect(0, 0, 256, 256);
    var m = new THREE.Mesh(new THREE.PlaneGeometry(w, d), new THREE.MeshBasicMaterial({ map: new THREE.CanvasTexture(c), transparent: true, opacity: opacity == null ? 0.55 : opacity, depthWrite: false }));
    m.rotation.x = -PI / 2;
    return m;
  }

  function planarUv(mesh) {
    var g = mesh.geometry; g.computeBoundingBox();
    var bb = g.boundingBox, pos = g.attributes.position;
    var w = bb.max.x - bb.min.x || 1, h = bb.max.y - bb.min.y || 1;
    var uv = new Float32Array(pos.count * 2);
    for (var i = 0; i < pos.count; i++) {
      uv[i * 2] = (pos.getX(i) - bb.min.x) / w;
      uv[i * 2 + 1] = (pos.getY(i) - bb.min.y) / h;
    }
    g.setAttribute('uv', new THREE.BufferAttribute(uv, 2));
  }

  // Экран устройства — 2D canvas, который сцена перерисовывает сама
  function screen(w, h) {
    var c = document.createElement('canvas'); c.width = w; c.height = h;
    var tex = new THREE.CanvasTexture(c);
    tex.encoding = THREE.sRGBEncoding;
    tex.minFilter = THREE.LinearFilter; tex.generateMipmaps = false;
    tex.anisotropy = 8;
    return { canvas: c, ctx: c.getContext('2d'), tex: tex, w: w, h: h };
  }

  // Загружает модель; screenName — меш дисплея; модель центрируется и масштабируется.
  // Экран смотрит на +Z (к камере по умолчанию).
  function loadDevice(url, kind, scr) {
    return new Promise(function (resolve, reject) {
      new THREE.GLTFLoader().load(url, function (gltf) {
        var m = gltf.scene;
        var box = new THREE.Box3().setFromObject(m), sz = box.getSize(new THREE.Vector3());
        m.scale.setScalar(kind === 'phone' ? 3.2 / sz.y : 4.5 / Math.max(sz.x, sz.z));
        box.setFromObject(m);
        m.position.sub(box.getCenter(new THREE.Vector3()));
        m.traverse(function (o) {
          if (!o.isMesh) return;
          var n = o.name.replace(/\.\d+$/, '');
          if (n.indexOf('apple-logo') === 0) o.visible = false; // без чужих логотипов в кадре
          if (n === 'lid-glass') o.visible = false; // стекло крышки серит экран
          var isScreen = kind === 'phone' ? n.indexOf('front-glass') === 0 : n === 'display';
          if (isScreen) { planarUv(o); o.material = new THREE.MeshBasicMaterial({ map: scr.tex, toneMapped: false }); }
        });
        // модель должна смотреть экраном на +Z
        var front = m.getObjectByName(kind === 'phone' ? 'front-glass' : 'display');
        var back = m.getObjectByName(kind === 'phone' ? 'rear-window' : 'lid');
        var wrap = new THREE.Group(); wrap.add(m);
        if (front && back) {
          m.updateWorldMatrix(true, true);
          var fp = new THREE.Vector3(), bp = new THREE.Vector3();
          front.getWorldPosition(fp); back.getWorldPosition(bp);
          if (fp.z < bp.z) m.rotation.y += PI;
        }
        resolve(wrap);
      }, undefined, reject);
    });
  }

  // Знак Vet.izi: скруглённая плитка + выпуклая лапка
  function mark(opts) {
    opts = opts || {};
    var g = new THREE.Group();
    var s = 1.6, r = 0.42;
    var sh = new THREE.Shape();
    sh.moveTo(-s / 2 + r, -s / 2); sh.lineTo(s / 2 - r, -s / 2); sh.quadraticCurveTo(s / 2, -s / 2, s / 2, -s / 2 + r);
    sh.lineTo(s / 2, s / 2 - r); sh.quadraticCurveTo(s / 2, s / 2, s / 2 - r, s / 2); sh.lineTo(-s / 2 + r, s / 2);
    sh.quadraticCurveTo(-s / 2, s / 2, -s / 2, s / 2 - r); sh.lineTo(-s / 2, -s / 2 + r); sh.quadraticCurveTo(-s / 2, -s / 2, -s / 2 + r, -s / 2);
    var tile = new THREE.Mesh(
      new THREE.ExtrudeGeometry(sh, { depth: 0.32, bevelEnabled: true, bevelThickness: 0.07, bevelSize: 0.07, bevelSegments: 6, curveSegments: 24 }),
      new THREE.MeshPhysicalMaterial({ color: new THREE.Color(opts.tile || '#0E5A4C'), roughness: 0.38, metalness: 0.1, clearcoat: 0.8, clearcoatRoughness: 0.22, envMapIntensity: 0.9 })
    );
    tile.position.z = -0.16;
    g.add(tile);
    var pawMat = new THREE.MeshPhysicalMaterial({ color: new THREE.Color(opts.paw || '#F4F1E4'), roughness: 0.45, clearcoat: 0.5, envMapIntensity: 0.6 });
    function blob(x, y, rx, ry, rot) {
      var e = new THREE.Shape(); e.absellipse(0, 0, rx, ry, 0, PI * 2, false, 0);
      var m = new THREE.Mesh(new THREE.ExtrudeGeometry(e, { depth: 0.08, bevelEnabled: true, bevelThickness: 0.05, bevelSize: 0.04, bevelSegments: 5, curveSegments: 32 }), pawMat);
      m.position.set(x, y, 0.2); m.rotation.z = rot || 0; g.add(m);
    }
    blob(0, -0.2, 0.33, 0.25);
    blob(-0.38, 0.14, 0.11, 0.15, 0.35);
    blob(0.38, 0.14, 0.11, 0.15, -0.35);
    blob(-0.15, 0.36, 0.11, 0.15, 0.1);
    blob(0.15, 0.36, 0.11, 0.15, -0.1);
    return g;
  }

  function roundedRect(w, h, r) {
    var s = new THREE.Shape(), x = -w / 2, y = -h / 2;
    s.moveTo(x + r, y); s.lineTo(x + w - r, y); s.quadraticCurveTo(x + w, y, x + w, y + r);
    s.lineTo(x + w, y + h - r); s.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
    s.lineTo(x + r, y + h); s.quadraticCurveTo(x, y + h, x, y + h - r);
    s.lineTo(x, y + r); s.quadraticCurveTo(x, y, x + r, y);
    return s;
  }

  // Телефон: строгий корпус с умеренным скруглением (≈8% ширины), тонкая рамка, плоский экран.
  // Экран — canvas 1170 x 2484: рисуйте содержимое через phoneScreen(...).draw(ctx). Экран смотрит на +Z.
  var PHONE = { w: 1.55, h: 3.2, r: 0.13, d: 0.15, sw: 1.47, sh: 3.12 };
  function phone(scr) {
    var g = new THREE.Group();
    var body = new THREE.Mesh(
      new THREE.ExtrudeGeometry(roundedRect(PHONE.w - 0.04, PHONE.h - 0.04, PHONE.r - 0.02), { depth: PHONE.d - 0.04, bevelEnabled: true, bevelThickness: 0.02, bevelSize: 0.02, bevelSegments: 4, curveSegments: 16 }),
      new THREE.MeshPhysicalMaterial({ color: new THREE.Color('#2a2e33'), metalness: 0.85, roughness: 0.32, clearcoat: 0.6, clearcoatRoughness: 0.2, envMapIntensity: 0.9 })
    );
    body.position.z = -PHONE.d / 2;
    g.add(body);
    // чёрная лицевая панель (рамка вокруг экрана)
    var face = new THREE.Mesh(new THREE.ShapeGeometry(roundedRect(PHONE.w - 0.03, PHONE.h - 0.03, PHONE.r - 0.015), 16),
      new THREE.MeshPhysicalMaterial({ color: 0x050607, roughness: 0.15, clearcoat: 1, envMapIntensity: 0.6 }));
    face.position.z = PHONE.d / 2 + 0.001;
    g.add(face);
    var screenMesh = new THREE.Mesh(new THREE.PlaneGeometry(PHONE.sw, PHONE.sh), new THREE.MeshBasicMaterial({ map: scr.tex, transparent: true, toneMapped: false }));
    screenMesh.position.z = PHONE.d / 2 + 0.003;
    g.add(screenMesh);
    // боковые кнопки
    var btnMat = new THREE.MeshStandardMaterial({ color: 0x33373c, metalness: 0.8, roughness: 0.35 });
    [[PHONE.w / 2 + 0.012, 0.75, 0.38], [-PHONE.w / 2 - 0.012, 0.95, 0.22], [-PHONE.w / 2 - 0.012, 0.6, 0.3]].forEach(function (b) {
      var m = new THREE.Mesh(new THREE.BoxGeometry(0.03, b[2], 0.06), btnMat); m.position.set(b[0], b[1], 0); g.add(m);
    });
    return g;
  }

  // Рисует содержимое экрана телефона с закруглёнными углами экрана и точкой камеры
  function phoneFrame(ctx, w, h) {
    var r = Math.round(w * 0.06);
    ctx.clearRect(0, 0, w, h);
    ctx.save();
    ctx.beginPath(); ctx.moveTo(r, 0); ctx.arcTo(w, 0, w, h, r); ctx.arcTo(w, h, 0, h, r); ctx.arcTo(0, h, 0, 0, r); ctx.arcTo(0, 0, w, 0, r); ctx.closePath();
    ctx.clip();
    return function done() {
      ctx.fillStyle = '#050607'; ctx.beginPath(); ctx.arc(w / 2, 46, 18, 0, Math.PI * 2); ctx.fill();
      ctx.restore();
    };
  }

  // Рисует изображение по ширине экрана со сдвигом прокрутки (в пикселях исходника)
  function drawScroll(ctx, img, w, h, scrollPx, alpha) {
    if (!img || !img.width) return;
    var k = w / img.width;
    ctx.globalAlpha = alpha == null ? 1 : alpha;
    ctx.drawImage(img, 0, scrollPx, img.width, Math.min(img.height - scrollPx, h / k), 0, 0, w, Math.min(h, (img.height - scrollPx) * k));
    ctx.globalAlpha = 1;
  }

  function loadImage(src) {
    return new Promise(function (res) { var i = new Image(); i.onload = function () { res(i); }; i.onerror = function () { res(null); }; i.src = src; });
  }

  // Плавные кривые (кубический Безье), чтобы считать позы из времени без GSAP
  function cbz(p1x, p1y, p2x, p2y) {
    var ax = 3 * p1x - 3 * p2x + 1, bx = 3 * p2x - 6 * p1x, cx = 3 * p1x;
    var ay = 3 * p1y - 3 * p2y + 1, by = 3 * p2y - 6 * p1y, cy = 3 * p1y;
    return function (t) {
      var s = t;
      for (var i = 0; i < 12; i++) { var x = ((ax * s + bx) * s + cx) * s - t, dx = (3 * ax * s + 2 * bx) * s + cx; if (Math.abs(dx) < 1e-9) break; s -= x / dx; }
      s = Math.max(0, Math.min(1, s));
      return ((ay * s + by) * s + cy) * s;
    };
  }

  window.VI3D = { create: create, contactShadow: contactShadow, screen: screen, loadDevice: loadDevice, mark: mark, phone: phone, phoneFrame: phoneFrame, PHONE: PHONE, drawScroll: drawScroll, loadImage: loadImage, cbz: cbz };
})();
