/* WebFor: меню на телефоне, выбор услуги по кнопке, отправка заявки без перезагрузки (без JS форма уходит обычным POST). */
(function () {
  var root = document.querySelector('.wf');
  if (!root) return;

  // меню
  var burger = root.querySelector('.wf-burger');
  var nav = root.querySelector('#wf-nav');
  function setMenu(open) {
    nav.classList.toggle('is-open', open);
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    burger.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
  }
  if (burger && nav) {
    burger.addEventListener('click', function () { setMenu(!nav.classList.contains('is-open')); });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) setMenu(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });
  }

  // папки: открываются отдельной страницей, которая «вырастает» из папки (адрес #sites, #apps, #bots, #izi)
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var openPage = null, pushed = false;
  function folderBtn(fk) { return root.querySelector('.wf-folder[data-folder="' + fk + '"]'); }
  function insetFrom(el) {
    var r = (el.querySelector('.wf-folder-body') || el).getBoundingClientRect();
    return 'inset(' + Math.max(0, r.top) + 'px ' + Math.max(0, innerWidth - r.right) + 'px ' + Math.max(0, innerHeight - r.bottom) + 'px ' + Math.max(0, r.left) + 'px round 16px)';
  }
  function show(fk) {
    var page = document.getElementById('wf-p-' + fk);
    if (!page || openPage === page) return;
    if (openPage) hide(true);
    openPage = page;
    page.hidden = false;
    page.querySelector('.wf-page-scroll').scrollTop = 0;
    document.documentElement.classList.add('wf-locked');
    var btn = folderBtn(fk);
    if (!reduce && btn && page.animate) {
      page.animate([{ clipPath: insetFrom(btn) }, { clipPath: 'inset(0px 0px 0px 0px round 0px)' }], { duration: 560, easing: 'cubic-bezier(.2,.8,.2,1)' });
    }
    requestAnimationFrame(function () { page.classList.add('is-open'); });
    page.querySelector('.wf-page-back').focus({ preventScroll: true });
  }
  function hide(instant) {
    var page = openPage;
    if (!page) return;
    openPage = null;
    var done = function () { page.hidden = true; page.classList.remove('is-open'); if (!openPage) document.documentElement.classList.remove('wf-locked'); };
    var btn = folderBtn(page.getAttribute('data-folder'));
    if (!instant && !reduce && btn && page.animate) {
      page.animate([{ clipPath: 'inset(0px 0px 0px 0px round 0px)' }, { clipPath: insetFrom(btn) }], { duration: 420, easing: 'cubic-bezier(.4,0,.2,1)' }).onfinish = done;
    } else done();
    if (btn) btn.focus({ preventScroll: true });
  }
  function fromHash() {
    var fk = location.hash.replace('#', '');
    if (document.getElementById('wf-p-' + fk) && fk) show(fk); else if (openPage) hide();
  }
  function closePage() {
    if (pushed) { pushed = false; history.back(); } else { hide(); try { history.replaceState(null, '', location.pathname + location.search); } catch (e) { location.hash = ''; } }
  }
  root.querySelectorAll('.wf-folder').forEach(function (f) {
    f.addEventListener('click', function () { pushed = true; location.hash = f.getAttribute('data-folder'); });
  });
  root.querySelectorAll('.wf-page-back').forEach(function (b) { b.addEventListener('click', closePage); });
  // ссылки на разделы сайта (например «Оставить заявку») закрывают страницу папки
  root.querySelectorAll('.wf-page a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      var target = document.querySelector(a.getAttribute('href'));
      pushed = false; hide(true);
      try { history.replaceState(null, '', location.pathname + location.search); } catch (err) {}
      if (target) target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth' });
    });
  });
  root.querySelectorAll('.wf-page [data-wfai-open]').forEach(function (b) { b.addEventListener('click', function () { pushed = false; hide(true); }); });
  window.addEventListener('hashchange', fromHash);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && openPage && !(document.querySelector('.wf-lightbox[open]'))) closePage();
  });
  fromHash();

  // просмотр работы
  var lb = root.querySelector('.wf-lightbox');
  var list = [], cur = 0;
  function showWork(i) {
    cur = (i + list.length) % list.length;
    var d = list[cur].dataset;
    lb.classList.toggle('is-phone', d.shape === 'phone');
    var img = lb.querySelector('.wf-lb-shot img'); img.src = d.src; img.alt = d.title;
    lb.querySelector('.wf-lb-shot').scrollTop = 0;
    lb.querySelector('.wf-lb-tag').textContent = d.tag || '';
    lb.querySelector('#wf-lb-title').textContent = d.title;
    lb.querySelector('.wf-lb-desc').textContent = d.desc || '';
    var url = lb.querySelector('.wf-lb-url');
    url.hidden = !d.url; if (d.url) url.href = d.url;
    url.firstChild.textContent = /petgo|clinic|\.kz/.test(d.url || '') ? 'Открыть сайт ' : 'Открыть ';
    lb.querySelector('.wf-lb-chat').hidden = d.action !== 'chat' || !window.WFAI;
    lb.querySelector('.wf-lb-nav').hidden = list.length < 2;
  }
  if (lb && lb.showModal) {
    root.querySelectorAll('.wf-work-btn').forEach(function (b) {
      b.addEventListener('click', function () {
        list = Array.prototype.slice.call(b.closest('.wf-cards').querySelectorAll('.wf-work-btn'));
        showWork(list.indexOf(b)); lb.showModal();
      });
    });
    lb.querySelector('.wf-lb-close').addEventListener('click', function () { lb.close(); });
    lb.querySelector('.wf-lb-prev').addEventListener('click', function () { showWork(cur - 1); });
    lb.querySelector('.wf-lb-next').addEventListener('click', function () { showWork(cur + 1); });
    lb.querySelector('.wf-lb-chat').addEventListener('click', function () { lb.close(); });
    lb.addEventListener('click', function (e) { if (e.target === lb) lb.close(); });
    lb.addEventListener('keydown', function (e) { if (e.key === 'ArrowLeft') showWork(cur - 1); if (e.key === 'ArrowRight') showWork(cur + 1); });
  }

  // кнопки «Хочу Vet.izi» и т.п. сразу выбирают услугу в форме
  var select = root.querySelector('#wf-service');
  root.querySelectorAll('[data-service]').forEach(function (a) {
    a.addEventListener('click', function () { if (select) select.value = a.getAttribute('data-service'); });
  });

  // форма
  var form = root.querySelector('.wf-form');
  if (!form || !window.fetch || !window.WEBFOR) return;
  var status = form.querySelector('.wf-status');
  var btn = form.querySelector('button[type=submit]');

  function fieldError(name, msg) {
    var input = form.querySelector('[name="' + name + '"]');
    var box = form.querySelector('#wf-' + name + '-err');
    if (input) {
      input.setAttribute('aria-invalid', msg ? 'true' : 'false');
      if (box) input.setAttribute('aria-describedby', box.id);
    }
    if (box) { box.textContent = msg || ''; box.hidden = !msg; }
  }
  function setStatus(text, kind) {
    status.textContent = text;
    status.className = 'wf-status' + (kind ? ' wf-status-' + kind : '');
  }
  function validate() {
    var ok = true;
    var name = form.elements.name.value.trim();
    var digits = form.elements.phone.value.replace(/\D/g, '');
    fieldError('name', name.length >= 2 ? '' : 'Укажите имя.');
    fieldError('phone', digits.length >= 10 && digits.length <= 15 ? '' : 'Проверьте номер: нужно 10–15 цифр.');
    fieldError('consent', form.elements.consent.checked ? '' : 'Нужно согласие на обработку данных.');
    [['name', name.length >= 2], ['phone', digits.length >= 10 && digits.length <= 15], ['consent', form.elements.consent.checked]].some(function (f) {
      if (!f[1]) { ok = false; form.elements[f[0]].focus(); return true; }
      return false;
    });
    return ok;
  }
  ['name', 'phone'].forEach(function (n) {
    form.elements[n].addEventListener('input', function () { if (form.elements[n].getAttribute('aria-invalid') === 'true') fieldError(n, ''); });
  });
  form.elements.consent.addEventListener('change', function () { if (form.elements.consent.checked) fieldError('consent', ''); });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    setStatus('', '');
    if (!validate()) return;
    var data = new FormData(form);
    btn.disabled = true;
    btn.textContent = 'Отправляем…';
    fetch(window.WEBFOR.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.ok) {
          form.reset();
          setStatus(res.message, 'ok');
        } else {
          if (res.field) fieldError(res.field, res.message);
          setStatus(res.message, 'err');
        }
      })
      .catch(function () { setStatus('Не удалось отправить. Проверьте интернет или напишите нам в WhatsApp.', 'err'); })
      .then(function () { btn.disabled = false; btn.textContent = 'Отправить заявку'; });
  });
})();
