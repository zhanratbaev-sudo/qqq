/* PetGo Маркет — панель магазина: товары (добавление онлайн с фото), заказы с витрины. */
(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var esc = Store.esc;
  var tab = 'products', filter = 'all';
  var editing = null, photos = [];

  var IC = {
    box: '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7l-8-4-8 4 8 4 8-4z"/><path d="M4 7v10l8 4 8-4V7M12 11v10"/></svg>',
    doc: '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="3"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
    chev: '<svg class="ic chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>',
    plus: '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
    x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
  };

  // ---------- верх ----------
  function hello() {
    var h = new Date().getHours();
    $('#hello').textContent = (h < 5 ? 'Доброй ночи' : h < 12 ? 'Доброе утро' : h < 18 ? 'Добрый день' : 'Добрый вечер') + ', ' + Store.settings().name;
  }
  window.addEventListener('scroll', function () { $('#top').classList.toggle('sc', window.scrollY > 4); }, { passive: true });

  function toast(text, isErr) {
    var t = $('#toast');
    t.textContent = text; t.classList.toggle('e', !!isErr); t.classList.add('show');
    clearTimeout(toast.t); toast.t = setTimeout(function () { t.classList.remove('show'); }, 2400);
  }

  // ---------- вкладки ----------
  function moveLens() {
    var on = $('.tab.on'), lens = $('#lens');
    lens.style.width = on.offsetWidth + 'px';
    lens.style.transform = 'translateX(' + on.offsetLeft + 'px)';
  }
  $('#tabs').addEventListener('click', function (e) {
    var b = e.target.closest('.tab');
    if (!b) return;
    tab = b.dataset.tab;
    document.querySelectorAll('.tab').forEach(function (x) { x.classList.toggle('on', x === b); });
    moveLens(); render(); window.scrollTo({ top: 0 });
  });
  window.addEventListener('resize', moveLens);

  // ---------- товары ----------
  function renderProducts() {
    var all = Store.products();
    var cats = Store.categories();
    var counts = { all: all.length, out: all.filter(function (p) { return !p.stock; }).length };
    cats.forEach(function (c) { counts[c.id] = all.filter(function (p) { return p.cat === c.id; }).length; });
    var list = all.filter(function (p) { return filter === 'all' || (filter === 'out' ? !p.stock : p.cat === filter); });
    var orders = Store.orders();
    var newOrders = orders.filter(function (o) { return o.status === 'new'; }).length;
    var sum = orders.reduce(function (s, o) { return s + o.total; }, 0);

    var chips = [{ id: 'all', name: 'Все' }].concat(cats).concat([{ id: 'out', name: 'Нет в наличии' }]);
    $('#view').innerHTML =
      '<div class="tiles"><div class="tile hero"><small>Товаров на сайте</small><b>' + all.length + '</b></div>' +
      '<div class="tile"><small>Новые заказы</small><b style="color:var(--wn)">' + newOrders + '</b></div>' +
      '<div class="tile"><small>Сумма заказов</small><b>' + Store.price(sum) + '</b></div></div>' +
      '<div class="chips" role="tablist">' + chips.map(function (c) {
        return '<button class="chip' + (filter === c.id ? ' on' : '') + '" type="button" data-f="' + c.id + '">' + esc(c.name) + ' <i>' + (counts[c.id] || 0) + '</i></button>';
      }).join('') + '</div>' +
      (list.length ? '<div class="list">' + list.map(function (p, i) {
        var c = Store.category(p.cat);
        return '<button class="row" type="button" data-edit="' + p.id + '" style="animation-delay:' + Math.min(i * 30, 300) + 'ms">' +
          (p.photos && p.photos[0] ? '<img src="' + esc(p.photos[0]) + '" alt="" />' : '<span class="noph"></span>') +
          '<span class="row-b"><b>' + esc(p.name) + '</b><span>' + esc(c ? c.name : '—') + ' · <span class="price">' + Store.price(p.price) + '</span></span></span>' +
          '<span class="badge ' + (p.stock ? 'ok' : 'off') + '">' + (p.stock ? 'В наличии' : 'Нет') + '</span>' + IC.chev + '</button>';
      }).join('') + '</div>' : '<div class="empty">' + IC.box + '<p>Товаров пока нет.<br>Нажмите «+», чтобы добавить первый.</p></div>');
  }
  $('#view').addEventListener('click', function (e) {
    var f = e.target.closest('[data-f]'), ed = e.target.closest('[data-edit]'), st = e.target.closest('[data-st]');
    if (f) { filter = f.dataset.f; render(); }
    else if (ed) openEditor(ed.dataset.edit);
    else if (st) { Store.setOrderStatus(st.dataset.st, st.dataset.v); toast(st.dataset.v === 'done' ? 'Заказ выполнен' : 'Заказ снова в работе'); }
  });

  // ---------- заказы ----------
  var dt = new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
  function renderOrders() {
    var orders = Store.orders();
    if (!orders.length) { $('#view').innerHTML = '<div class="empty">' + IC.doc + '<p>Заказов пока нет.<br>Они появятся здесь, как только покупатель оформит корзину на сайте.</p></div>'; return; }
    $('#view').innerHTML = orders.map(function (o, i) {
      var wa = o.phone.replace(/\D/g, '').replace(/^8/, '7');
      return '<article class="order" style="animation-delay:' + Math.min(i * 30, 300) + 'ms">' +
        '<div class="order-h"><b>№ ' + o.no + ' · ' + esc(o.name) + '</b><span class="badge ' + (o.status === 'new' ? 'new' : 'done') + '">' + (o.status === 'new' ? 'Новый' : 'Выполнен') + '</span></div>' +
        '<div class="mu" style="font-size:15px">' + dt.format(o.ts) + ' · ' + esc(o.phone) + ' · ' + (o.contact === 'call' ? 'просит позвонить' : 'просит написать') + '</div>' +
        (o.address ? '<div style="font-size:15px">' + esc(o.address) + '</div>' : '') +
        (o.note ? '<div class="mu" style="font-size:15px">«' + esc(o.note) + '»</div>' : '') +
        '<ul>' + o.items.map(function (x) { return '<li><span>' + esc(x.name) + ' × ' + x.q + '</span><span>' + Store.price(x.price * x.q) + '</span></li>'; }).join('') + '</ul>' +
        '<div class="order-sum"><span>Итого</span><span>' + Store.price(o.total) + '</span></div>' +
        '<div class="order-acts">' +
        (o.contact === 'call' ? '<a class="btn sm tint" href="tel:+' + wa + '">Позвонить</a>' : '<a class="btn sm tint" href="https://wa.me/' + wa + '" target="_blank" rel="noopener">Написать</a>') +
        (o.status === 'new' ? '<button class="btn sm" type="button" data-st="' + o.id + '" data-v="done">Выполнен</button>' : '<button class="btn sm tint" type="button" data-st="' + o.id + '" data-v="new">Вернуть в работу</button>') +
        '</div></article>';
    }).join('');
  }

  function render() {
    hello();
    $('#ttl').textContent = tab === 'products' ? 'Товары' : 'Заказы';
    $('#fab').hidden = tab !== 'products';
    var n = Store.orders().filter(function (o) { return o.status === 'new'; }).length;
    $('#newN').hidden = !n; $('#newN').textContent = n;
    if (tab === 'products') renderProducts(); else renderOrders();
  }

  // ---------- лист товара ----------
  function openSheet() {
    $('#ovl').hidden = false; $('#sheet').hidden = false;
    document.body.classList.add('lock');
    requestAnimationFrame(function () { $('#ovl').classList.add('open'); $('#sheet').classList.add('open'); });
  }
  function closeSheet() {
    $('#ovl').classList.remove('open'); $('#sheet').classList.remove('open');
    document.body.classList.remove('lock');
    setTimeout(function () { if (!$('#sheet').classList.contains('open')) { $('#ovl').hidden = true; $('#sheet').hidden = true; } }, 450);
  }
  $('#ovl').addEventListener('click', closeSheet);
  document.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) closeSheet(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !$('#sheet').hidden) closeSheet(); });
  $('#fab').addEventListener('click', function () { openEditor(null); });

  function openEditor(id) {
    var p = id ? Store.product(id) : null;
    editing = p ? p.id : null;
    photos = p ? (p.photos || []).slice() : [];
    var f = $('#pForm').elements;
    $('#shTitle').textContent = p ? 'Товар' : 'Новый товар';
    $('#save').textContent = p ? 'Сохранить' : 'Опубликовать';
    $('#del').hidden = !p;
    var sel = f.cat;
    sel.innerHTML = Store.categories().map(function (c) { return '<option value="' + c.id + '">' + esc(c.name) + '</option>'; }).join('');
    f.name.value = p ? p.name : '';
    sel.value = p ? p.cat : (filter !== 'all' && filter !== 'out' ? filter : Store.categories()[0].id);
    f.desc.value = p ? p.desc || '' : '';
    f.price.value = p ? p.price : '';
    f.old.value = p && p.old ? p.old : '';
    f.stock.checked = p ? !!p.stock : true;
    renderPhotos();
    openSheet();
    if (!p) setTimeout(function () { f.name.focus(); }, 350);
  }

  function renderPhotos() {
    $('#photos').innerHTML = photos.map(function (src, i) {
      return '<div class="ph"><img src="' + esc(src) + '" alt="" />' + (i === 0 ? '<span class="cover">Обложка</span>' : '<button class="rm" type="button" data-cover="' + i + '" aria-label="Сделать обложкой" style="right:40px">★</button>') +
        '<button class="rm" type="button" data-rm="' + i + '" aria-label="Удалить фото">' + IC.x + '</button></div>';
    }).join('') + (photos.length < 8 ? '<button class="ph add" type="button" id="addPh" aria-label="Добавить фото">' + IC.plus + '</button>' : '');
  }
  $('#photos').addEventListener('click', function (e) {
    var rm = e.target.closest('[data-rm]'), cv = e.target.closest('[data-cover]');
    if (rm) { photos.splice(+rm.dataset.rm, 1); renderPhotos(); }
    else if (cv) { var i = +cv.dataset.cover; photos.unshift(photos.splice(i, 1)[0]); renderPhotos(); }
    else if (e.target.closest('#addPh')) $('#file').click();
  });
  $('#file').addEventListener('change', function (e) { addFiles(e.target.files); e.target.value = ''; });
  ['dragover', 'dragenter'].forEach(function (t) { $('#photos').addEventListener(t, function (e) { e.preventDefault(); var a = $('#addPh'); if (a) a.classList.add('drag'); }); });
  $('#photos').addEventListener('dragleave', function () { var a = $('#addPh'); if (a) a.classList.remove('drag'); });
  $('#photos').addEventListener('drop', function (e) { e.preventDefault(); addFiles(e.dataTransfer.files); });

  // фото сжимаем в браузере: длинная сторона до 1200 px, JPEG 82%
  function compress(file) {
    return new Promise(function (resolve, reject) {
      if (!/^image\//.test(file.type)) return reject(new Error('type'));
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () {
        var k = Math.min(1, 1200 / Math.max(img.naturalWidth, img.naturalHeight));
        var c = document.createElement('canvas');
        c.width = Math.round(img.naturalWidth * k); c.height = Math.round(img.naturalHeight * k);
        var ctx = c.getContext('2d');
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
        ctx.drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        resolve(c.toDataURL('image/jpeg', 0.82));
      };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('decode')); };
      img.src = url;
    });
  }
  function addFiles(list) {
    var files = Array.prototype.slice.call(list || [], 0, 8 - photos.length);
    if (!files.length) return;
    Promise.all(files.map(function (f) { return compress(f).catch(function () { return null; }); })).then(function (res) {
      var ok = res.filter(Boolean);
      photos = photos.concat(ok);
      renderPhotos();
      if (ok.length < files.length) toast('Часть файлов не похожа на изображения', true);
    });
  }

  $('#pForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var f = e.target.elements;
    var name = f.name.value.trim(), price = Math.round(+f.price.value), old = Math.round(+f.old.value) || 0;
    if (!name) { toast('Укажите название', true); f.name.focus(); return; }
    if (!(price > 0)) { toast('Укажите цену', true); f.price.focus(); return; }
    var prev = editing ? Store.product(editing) : null;
    var p = { id: editing || '', ts: prev ? prev.ts : 0, cat: f.cat.value, name: name, desc: f.desc.value.trim(), price: price, old: old > price ? old : 0, stock: f.stock.checked, photos: photos.slice() };
    if (!p.id) delete p.id;
    if (!Store.saveProduct(p)) { toast('Не хватает места в браузере — удалите часть фото', true); return; }
    toast(editing ? 'Сохранено' : 'Товар опубликован на сайте');
    closeSheet();
  });
  $('#del').addEventListener('click', function () {
    var p = Store.product(editing);
    if (!p || !confirm('Удалить «' + p.name + '»? Товар пропадёт с сайта.')) return;
    Store.deleteProduct(p.id);
    toast('Товар удалён');
    closeSheet();
  });

  Store.onChange(render);
  render();
  moveLens();
})();
