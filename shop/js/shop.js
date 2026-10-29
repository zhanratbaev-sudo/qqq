/* PetGo Маркет — витрина: каталог, карточка товара, корзина и оформление заказа. */
(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var esc = Store.esc;
  var cat = 'all';
  document.documentElement.classList.add('js');

  // ---------- общее ----------
  function applySettings() {
    document.querySelectorAll('[data-shop-name]').forEach(function (el) { el.textContent = Store.settings().name; });
    $('#year').textContent = new Date().getFullYear();
  }

  // меню прячется при прокрутке вниз и появляется при прокрутке вверх
  var lastY = 0;
  window.addEventListener('scroll', function () {
    var y = window.scrollY;
    $('#nav').classList.toggle('hide', y > 140 && y > lastY);
    lastY = y;
    floatCart();
  }, { passive: true });

  // плавное появление блоков
  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (es) {
    es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { rootMargin: '0px 0px -10% 0px' }) : null;
  function rise(root) {
    (root || document).querySelectorAll('.rise:not(.in)').forEach(function (el) { io ? io.observe(el) : el.classList.add('in'); });
  }

  // ---------- главный экран: три последних товара ----------
  function renderHero() {
    var ps = Store.products().filter(function (p) { return p.photos && p.photos[0]; }).slice(0, 3);
    $('#heroArt').innerHTML = ps.map(function (p) { return '<img src="' + esc(p.photos[0]) + '" alt="" />'; }).join('');
  }

  // ---------- каталог ----------
  function renderPicker() {
    var picker = $('#picker');
    var cats = [{ id: 'all', name: 'Все' }].concat(Store.categories());
    picker.querySelectorAll('button').forEach(function (b) { b.remove(); });
    cats.forEach(function (c) {
      var b = document.createElement('button');
      b.type = 'button'; b.setAttribute('role', 'tab'); b.dataset.cat = c.id; b.textContent = c.name;
      b.setAttribute('aria-selected', c.id === cat ? 'true' : 'false');
      b.addEventListener('click', function () { cat = c.id; renderPicker(); renderGrid(true); });
      picker.appendChild(b);
    });
    moveInd();
  }
  function moveInd() {
    var on = $('#picker button[aria-selected="true"]');
    var ind = $('#pickerInd');
    if (!on) return;
    ind.style.width = on.offsetWidth + 'px';
    ind.style.transform = 'translateX(' + on.offsetLeft + 'px)';
  }
  window.addEventListener('resize', moveInd);

  function card(p) {
    var off = p.old && p.old > p.price ? Math.round((1 - p.price / p.old) * 100) : 0;
    var badge = !p.stock ? '<span class="badge out">Нет в наличии</span>' : off ? '<span class="badge">−' + off + '%</span>' : '';
    return '<article class="card">' +
      '<button class="card-ph" type="button" data-open="' + p.id + '" aria-label="' + esc(p.name) + '">' +
      (p.photos && p.photos[0] ? '<img src="' + esc(p.photos[0]) + '" alt="" loading="lazy" />' : '') + badge + '</button>' +
      '<h3 class="card-name" data-open="' + p.id + '">' + esc(p.name) + '</h3>' +
      '<div class="price-row"><span class="price">' + Store.price(p.price) + '</span>' + (off ? '<span class="old">' + Store.price(p.old) + '</span>' : '') + '</div>' +
      '<button class="btn ' + (p.stock ? 'btn-primary' : 'btn-second') + '" type="button" data-add="' + p.id + '"' + (p.stock ? '' : ' disabled') + '>' + (p.stock ? 'В корзину' : 'Нет в наличии') + '</button>' +
      '</article>';
  }

  function renderGrid(animate) {
    var list = Store.products().filter(function (p) { return cat === 'all' || p.cat === cat; });
    var grid = $('#grid');
    grid.innerHTML = list.length ? list.map(card).join('') : '<div class="empty">В этой категории пока нет товаров</div>';
    grid.classList.remove('swap');
    if (animate) { void grid.offsetWidth; grid.classList.add('swap'); }
  }

  $('#grid').addEventListener('click', function (e) {
    var open = e.target.closest('[data-open]');
    var add = e.target.closest('[data-add]');
    if (add) addToCart(add.dataset.add);
    else if (open) openProduct(open.dataset.open);
  });

  // ---------- окна ----------
  var current = null;
  function openSheet(el) {
    closeSheets(true);
    $('#ovl').hidden = false; el.hidden = false;
    document.body.classList.add('lock');
    requestAnimationFrame(function () { $('#ovl').classList.add('open'); el.classList.add('open'); });
    current = el;
    var x = el.querySelector('[data-close]'); if (x) x.focus({ preventScroll: true });
  }
  function closeSheets(instant) {
    document.querySelectorAll('.sheet.open').forEach(function (el) {
      el.classList.remove('open');
      if (instant) el.hidden = true; else setTimeout(function () { if (!el.classList.contains('open')) el.hidden = true; }, 450);
    });
    $('#ovl').classList.remove('open');
    if (instant) $('#ovl').hidden = true; else setTimeout(function () { if (!$('#ovl').classList.contains('open')) $('#ovl').hidden = true; }, 300);
    document.body.classList.remove('lock');
    current = null;
  }
  $('#ovl').addEventListener('click', function () { closeSheets(); });
  document.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) closeSheets(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && current) closeSheets(); });

  // ---------- товар ----------
  var shown = null;
  function openProduct(id) {
    var p = Store.product(id);
    if (!p) return;
    shown = id;
    $('#pName').textContent = p.name;
    var photos = p.photos && p.photos.length ? p.photos : [''];
    $('#pImg').src = photos[0];
    $('#pThumbs').innerHTML = photos.length > 1 ? photos.map(function (src, i) {
      return '<button type="button" class="' + (i ? '' : 'on') + '" data-i="' + i + '"><img src="' + esc(src) + '" alt="" /></button>';
    }).join('') : '';
    $('#pPrice').textContent = Store.price(p.price);
    $('#pOld').textContent = p.old && p.old > p.price ? Store.price(p.old) : '';
    $('#pDesc').textContent = p.desc || '';
    var b = $('#pAdd');
    b.disabled = !p.stock; b.textContent = p.stock ? 'В корзину' : 'Нет в наличии';
    openSheet($('#pSheet'));
  }
  $('#pThumbs').addEventListener('click', function (e) {
    var t = e.target.closest('button[data-i]');
    if (!t) return;
    var p = Store.product(shown);
    $('#pImg').src = p.photos[+t.dataset.i];
    $('#pThumbs').querySelectorAll('button').forEach(function (b) { b.classList.toggle('on', b === t); });
  });
  $('#pAdd').addEventListener('click', function () { addToCart(shown); closeSheets(); });

  // ---------- корзина ----------
  function cartItems() {
    var c = Store.cart();
    return Object.keys(c).map(function (id) { return { p: Store.product(id), q: c[id] }; }).filter(function (x) { return x.p && x.q > 0; });
  }
  function cartTotal(items) { return items.reduce(function (s, x) { return s + x.p.price * x.q; }, 0); }
  function updateCount() {
    var n = cartItems().reduce(function (s, x) { return s + x.q; }, 0);
    var el = $('#cartN');
    if (el.textContent !== String(n)) { el.textContent = n; el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump'); }
  }
  function floatCart() {
    var items = cartItems(), n = items.reduce(function (s, x) { return s + x.q; }, 0);
    $('#cfN').textContent = n;
    $('#cfSum').textContent = n ? '· ' + Store.price(cartTotal(items)) : '';
    $('#cartFloat').classList.toggle('show', n > 0 && $('#nav').classList.contains('hide'));
  }
  function addToCart(id) {
    var p = Store.product(id);
    if (!p || !p.stock) return;
    var c = Store.cart(); c[id] = (c[id] || 0) + 1; Store.setCart(c);
  }
  function setQty(id, q) {
    var c = Store.cart();
    if (q <= 0) delete c[id]; else c[id] = q;
    Store.setCart(c);
  }

  var step = 'cart'; // cart → form → done
  function renderCart() {
    var items = cartItems();
    var body = $('#cBody'), foot = $('#cFoot');
    if (step === 'done') return;
    if (!items.length) {
      $('#cTitle').textContent = 'Корзина';
      body.innerHTML = '<div class="empty">Корзина пока пустая</div>';
      foot.innerHTML = '<button class="btn btn-second btn-wide" type="button" data-close>Перейти в каталог</button>';
      step = 'cart';
      return;
    }
    var total = cartTotal(items);
    if (step === 'cart') {
      $('#cTitle').textContent = 'Корзина';
      body.innerHTML = items.map(function (x) {
        return '<div class="line"><img src="' + esc(x.p.photos[0] || '') + '" alt="" /><div><b>' + esc(x.p.name) + '</b><span class="meta">' + Store.price(x.p.price) + '</span></div>' +
          '<div class="qty"><button type="button" data-q="' + x.p.id + '" data-d="-1" aria-label="Меньше">−</button><span>' + x.q + '</span><button type="button" data-q="' + x.p.id + '" data-d="1" aria-label="Больше">+</button></div></div>';
      }).join('');
      foot.innerHTML = '<div class="total"><span>Итого</span><b>' + Store.price(total) + '</b></div><button class="btn btn-primary btn-wide" type="button" id="toForm">Оформить заказ</button>';
    }
  }
  $('#cBody').addEventListener('click', function (e) {
    var b = e.target.closest('[data-q]');
    if (!b) return;
    var c = Store.cart(); setQty(b.dataset.q, (c[b.dataset.q] || 0) + +b.dataset.d);
  });
  $('#cFoot').addEventListener('click', function (e) {
    if (e.target.closest('#toForm')) { step = 'form'; renderForm(); }
  });
  function openCart() { step = 'cart'; renderCart(); openSheet($('#cSheet')); }
  $('#cartBtn').addEventListener('click', openCart);
  $('#cartFloat').addEventListener('click', openCart);

  // ---------- оформление (как форма заявки PetGo) ----------
  function renderForm() {
    var items = cartItems();
    $('#cTitle').textContent = 'Оформление';
    $('#cBody').innerHTML =
      '<form class="form" id="oForm" novalidate>' +
      '<div class="msg e" id="oErr" hidden></div>' +
      '<label class="fld">Ваше имя *<input name="name" autocomplete="name" maxlength="80" /></label>' +
      '<label class="fld">Телефон *<input name="phone" type="tel" inputmode="tel" placeholder="+7 ___ ___ __ __" autocomplete="tel" /></label>' +
      '<div class="fld">Как с вами связаться *<div class="pills"><label><input type="radio" name="contact" value="write" checked /><span>Написать</span></label><label><input type="radio" name="contact" value="call" /><span>Позвонить</span></label></div></div>' +
      '<label class="fld">Адрес доставки<input name="address" autocomplete="street-address" maxlength="160" /></label>' +
      '<label class="fld">Комментарий<textarea name="note" maxlength="600"></textarea></label>' +
      '<label class="consent"><input type="checkbox" name="consent" /><span>Я согласен на обработку моих данных. Их увидят только сотрудники магазина.</span></label>' +
      '</form>';
    $('#cFoot').innerHTML = '<div class="total"><span>К оплате при получении</span><b>' + Store.price(cartTotal(items)) + '</b></div>' +
      '<button class="btn btn-primary btn-wide" type="submit" form="oForm">Отправить заказ</button>' +
      '<button class="btn btn-second btn-wide" type="button" id="toCart">Назад в корзину</button>';
    $('#oForm').addEventListener('submit', submitOrder);
    $('#toCart').addEventListener('click', function () { step = 'cart'; renderCart(); });
  }

  function submitOrder(e) {
    e.preventDefault();
    var f = e.target.elements, err = []; // f.name у самой формы — её атрибут, поэтому поля берём из elements
    var name = f.name.value.trim(), phone = f.phone.value.trim(), digits = phone.replace(/\D/g, '');
    e.target.querySelectorAll('.fld').forEach(function (x) { x.classList.remove('err'); });
    if (!name) { err.push('Укажите имя.'); f.name.closest('.fld').classList.add('err'); }
    if (digits.length < 10) { err.push('Проверьте номер телефона.'); f.phone.closest('.fld').classList.add('err'); }
    if (!f.consent.checked) err.push('Нужно согласие на обработку данных.');
    var box = $('#oErr');
    if (err.length) { box.textContent = err.join(' '); box.hidden = false; box.scrollIntoView({ block: 'nearest' }); return; }
    var items = cartItems();
    var o = Store.addOrder({
      name: name, phone: phone, contact: f.contact.value, address: f.address.value.trim(), note: f.note.value.trim(),
      items: items.map(function (x) { return { id: x.p.id, name: x.p.name, price: x.p.price, q: x.q }; }),
      total: cartTotal(items),
    });
    Store.setCart({});
    step = 'done';
    $('#cTitle').textContent = 'Заказ отправлен';
    $('#cBody').innerHTML = '<div class="done"><i><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7"/></svg></i><h3 class="h3">Спасибо! Заказ №' + o.no + ' принят</h3><p class="sub" style="margin:0">Мы скоро свяжемся с вами и подтвердим доставку.</p></div>';
    $('#cFoot').innerHTML = '<button class="btn btn-primary btn-wide" type="button" data-close>Готово</button>';
  }

  // ---------- запуск и обновление при изменениях из панели ----------
  function renderAll() {
    applySettings();
    if (cat !== 'all' && !Store.category(cat)) cat = 'all';
    renderHero(); renderPicker(); renderGrid(false); updateCount(); floatCart();
    if (current === $('#cSheet') && step === 'cart') renderCart();
    if (current === $('#pSheet') && shown && !Store.product(shown)) closeSheets();
    rise();
  }
  Store.onChange(renderAll);
  renderAll();
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(moveInd);
})();
