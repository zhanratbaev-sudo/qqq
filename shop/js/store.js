/* PetGo Маркет — общий слой данных витрины и панели управления.
   Данные хранятся в localStorage браузера; вкладки синхронизируются через событие storage.
   Для боевого сайта этот файл заменяется запросами к серверу (WordPress REST), интерфейс остаётся тем же. */
(function () {
  var KEY = 'pgshop:data:v1';
  var CART = 'pgshop:cart:v1';

  // Простые иллюстрации-заглушки для стартовых товаров (вместо фото)
  function art(bg, fg, shape) {
    var shapes = {
      bowl: '<ellipse cx="200" cy="250" rx="130" ry="26" fill="#000" opacity=".08"/><path d="M80 170h240l-26 70a30 30 0 0 1-28 20H134a30 30 0 0 1-28-20z" fill="' + fg + '"/><ellipse cx="200" cy="170" rx="120" ry="22" fill="#fff" opacity=".35"/><circle cx="170" cy="150" r="14" fill="#c8833c"/><circle cx="205" cy="142" r="14" fill="#b06f2d"/><circle cx="236" cy="152" r="14" fill="#c8833c"/>',
      ball: '<ellipse cx="200" cy="300" rx="90" ry="18" fill="#000" opacity=".08"/><circle cx="200" cy="190" r="100" fill="' + fg + '"/><path d="M110 150c50 30 130 30 180 0M110 230c50-30 130-30 180 0" stroke="#fff" stroke-width="12" fill="none" opacity=".8"/>',
      leash: '<path d="M120 90c60-40 150-10 150 60s-60 110-100 140" stroke="' + fg + '" stroke-width="22" fill="none" stroke-linecap="round"/><rect x="150" y="260" width="50" height="70" rx="14" fill="' + fg + '"/><circle cx="120" cy="90" r="22" fill="none" stroke="#c9a227" stroke-width="10"/>',
      bed: '<ellipse cx="200" cy="300" rx="150" ry="20" fill="#000" opacity=".08"/><rect x="60" y="170" width="280" height="120" rx="60" fill="' + fg + '"/><rect x="95" y="190" width="210" height="70" rx="35" fill="#fff" opacity=".55"/>',
      bone: '<g transform="rotate(-25 200 200)"><rect x="120" y="175" width="160" height="50" rx="25" fill="' + fg + '"/><circle cx="120" cy="175" r="32" fill="' + fg + '"/><circle cx="120" cy="225" r="32" fill="' + fg + '"/><circle cx="280" cy="175" r="32" fill="' + fg + '"/><circle cx="280" cy="225" r="32" fill="' + fg + '"/></g>',
      bottle: '<rect x="160" y="70" width="80" height="40" rx="10" fill="#024C46"/><rect x="130" y="110" width="140" height="210" rx="34" fill="' + fg + '"/><rect x="150" y="180" width="100" height="70" rx="14" fill="#fff" opacity=".7"/>',
    };
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400"><rect width="400" height="400" fill="' + bg + '"/>' + shapes[shape] + '</svg>';
    return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
  }

  function seed() {
    var now = Date.now();
    return {
      settings: { name: 'PetGo Маркет', currency: '₸', wa: '' },
      categories: [
        { id: 'food', name: 'Корм' },
        { id: 'toys', name: 'Игрушки' },
        { id: 'ammo', name: 'Амуниция' },
        { id: 'care', name: 'Уход' },
      ],
      products: [
        { id: 'p1', cat: 'food', name: 'Сухой корм для собак, 3 кг', price: 9900, old: 12400, stock: true, desc: 'Сбалансированный рацион для взрослых собак средних пород. Курица и рис.', photos: [art('#f3efd6', '#024C46', 'bowl')], ts: now - 6e5 },
        { id: 'p2', cat: 'food', name: 'Влажный корм для кошек, 12 шт.', price: 5400, old: 0, stock: true, desc: 'Паучи с кусочками в соусе, 12 по 85 г.', photos: [art('#fbe6dc', '#e07a5f', 'bowl')], ts: now - 5e5 },
        { id: 'p3', cat: 'toys', name: 'Мяч из каучука', price: 2200, old: 0, stock: true, desc: 'Прочный мяч для апортировки, не тонет в воде.', photos: [art('#e5f0ff', '#2f6bff', 'ball')], ts: now - 4e5 },
        { id: 'p4', cat: 'toys', name: 'Игрушка-косточка', price: 1800, old: 2400, stock: true, desc: 'Жевательная косточка для чистки зубов.', photos: [art('#fff4d6', '#f2b134', 'bone')], ts: now - 3e5 },
        { id: 'p5', cat: 'ammo', name: 'Поводок-рулетка, 5 м', price: 6900, old: 0, stock: true, desc: 'Для собак до 25 кг, кнопка фиксации.', photos: [art('#e3f3ee', '#0d7c6d', 'leash')], ts: now - 2e5 },
        { id: 'p6', cat: 'care', name: 'Лежанка круглая, M', price: 14900, old: 17900, stock: false, desc: 'Мягкий бортик, съёмный чехол, диаметр 60 см.', photos: [art('#efe9f8', '#8a6fd1', 'bed')], ts: now - 1e5 },
        { id: 'p7', cat: 'care', name: 'Шампунь для шерсти', price: 3200, old: 0, stock: true, desc: 'Мягкая формула без отдушек, 250 мл.', photos: [art('#e8f6f9', '#2aa0b8', 'bottle')], ts: now },
      ],
      orders: [],
    };
  }

  var data = null;
  var subs = [];

  function load() {
    try {
      var raw = localStorage.getItem(KEY);
      data = raw ? JSON.parse(raw) : seed();
    } catch (e) {
      data = seed();
    }
    return data;
  }

  function save() {
    try {
      localStorage.setItem(KEY, JSON.stringify(data));
      return true;
    } catch (e) {
      // чаще всего — закончилось место под фото (лимит браузера ~5 МБ)
      return false;
    }
  }

  function emit() { subs.forEach(function (fn) { fn(); }); }

  window.addEventListener('storage', function (e) {
    if (e.key === KEY || e.key === CART) { load(); emit(); }
  });

  function uid(p) { return p + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); }

  var Store = {
    onChange: function (fn) { subs.push(fn); },
    settings: function () { return data.settings; },
    categories: function () { return data.categories.slice(); },
    category: function (id) { return data.categories.find(function (c) { return c.id === id; }); },
    products: function () { return data.products.slice().sort(function (a, b) { return b.ts - a.ts; }); },
    product: function (id) { return data.products.find(function (p) { return p.id === id; }); },
    saveProduct: function (p) {
      var i = data.products.findIndex(function (x) { return x.id === p.id; });
      if (!p.id) { p.id = uid('p'); p.ts = Date.now(); }
      if (i >= 0) data.products[i] = p; else data.products.push(p);
      var ok = save();
      if (!ok) { if (i >= 0) load(); else data.products.pop(); }
      else emit();
      return ok;
    },
    deleteProduct: function (id) {
      data.products = data.products.filter(function (p) { return p.id !== id; });
      save(); emit();
    },
    orders: function () { return data.orders.slice().sort(function (a, b) { return b.ts - a.ts; }); },
    addOrder: function (o) {
      o.id = uid('o'); o.ts = Date.now(); o.status = 'new';
      o.no = (data.orders.reduce(function (m, x) { return Math.max(m, x.no || 1000); }, 1000)) + 1;
      data.orders.push(o); save(); emit();
      return o;
    },
    setOrderStatus: function (id, status) {
      var o = data.orders.find(function (x) { return x.id === id; });
      if (o) { o.status = status; save(); emit(); }
    },
    reset: function () { data = seed(); save(); emit(); },

    // корзина витрины: { [id]: количество }
    cart: function () { try { return JSON.parse(localStorage.getItem(CART)) || {}; } catch (e) { return {}; } },
    setCart: function (c) { try { localStorage.setItem(CART, JSON.stringify(c)); } catch (e) {} emit(); },

    price: function (n) { return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ' + data.settings.currency; },
    esc: function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); },
  };

  load();
  window.Store = Store;
})();
