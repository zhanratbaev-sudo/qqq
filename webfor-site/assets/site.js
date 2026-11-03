/* WebFor: меню на телефоне, выбор услуги по кнопке, отправка заявки без перезагрузки (без JS форма уходит обычным POST). */
(function () {
  var root = document.querySelector('.wf');
  if (!root) return;

  // меню
  var burger = root.querySelector('.wf-burger');
  var nav = root.querySelector('#wf-nav');
  // преломление стекла включаем только там, где backdrop-filter понимает SVG-фильтры (Chromium)
  var brands = (navigator.userAgentData && navigator.userAgentData.brands) || [];
  if (brands.some(function (b) { return /Chromium|Google Chrome|Microsoft Edge|Opera/.test(b.brand); })) document.documentElement.classList.add('wf-refract');

  var menuY = null;
  function setMenu(open) {
    nav.classList.toggle('is-open', open);
    // меню справа на весь экран: сайт под ним не листается, а после закрытия остаётся на том же месте
    if (open) menuY = window.scrollY;
    document.documentElement.classList.toggle('wf-menu-open', open);
    if (!open && menuY != null) { window.scrollTo({ top: menuY, behavior: 'instant' }); menuY = null; }
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    burger.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
  }
  if (burger && nav) {
    burger.addEventListener('click', function () { setMenu(!nav.classList.contains('is-open')); });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) setMenu(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });
    document.addEventListener('click', function (e) { if (nav.classList.contains('is-open') && !e.target.closest('.wf-nav') && !e.target.closest('.wf-burger')) setMenu(false); });
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
      page.animate([
        { clipPath: insetFrom(btn), opacity: 0.4 },
        { clipPath: 'inset(0px 0px 0px 0px round 0px)', opacity: 1 },
      ], { duration: 560, easing: 'cubic-bezier(.2,.8,.2,1)' });
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
      // страница плавно сжимается к папке и одновременно тает — сайт под ней не «вырезается» прямоугольником
      page.animate([
        { clipPath: 'inset(0px 0px 0px 0px round 0px)', opacity: 1 },
        { clipPath: insetFrom(btn), opacity: 0 },
      ], { duration: 460, easing: 'cubic-bezier(.4,0,.2,1)' }).onfinish = done;
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
    if (e.key === 'Escape' && openPage) closePage();
  });
  fromHash();

  // кнопки «Хочу Vet.izi» и т.п. сразу выбирают услугу в форме
  root.querySelectorAll('[data-service]').forEach(function (a) {
    a.addEventListener('click', function () {
      var r = root.querySelector('.wf-pick input[value="' + a.getAttribute('data-service') + '"]');
      if (r) r.checked = true;
    });
  });

  // вопросы: плавно раскрываются, «+» поворачивается в «×»
  root.querySelectorAll('.wf-faq details').forEach(function (d) {
    var sum = d.querySelector('summary'), body = d.querySelector('p');
    sum.addEventListener('click', function (e) {
      if (reduce || !body.animate) return;
      e.preventDefault();
      if (d.classList.contains('is-anim')) return;
      d.classList.add('is-anim');
      if (!d.open) {
        d.open = true;
        var h = body.offsetHeight;
        body.animate([{ height: '0px', opacity: 0, transform: 'translateY(-6px)' }, { height: h + 'px', opacity: 1, transform: 'none' }], { duration: 380, easing: 'cubic-bezier(.2,.9,.3,1)' }).onfinish = function () { d.classList.remove('is-anim'); };
      } else {
        d.classList.add('is-closing');
        body.animate([{ height: body.offsetHeight + 'px', opacity: 1, transform: 'none' }, { height: '0px', opacity: 0, transform: 'translateY(-6px)' }], { duration: 260, easing: 'cubic-bezier(.4,0,.2,1)' }).onfinish = function () {
          d.open = false; d.classList.remove('is-anim', 'is-closing');
        };
      }
    });
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
    var name = form.elements.name.value.trim();
    var digits = form.elements.phone.value.replace(/\D/g, '');
    var okName = name.length >= 2, okPhone = digits.length >= 10 && digits.length <= 15;
    fieldError('name', okName ? '' : 'Укажите имя.');
    fieldError('phone', okPhone ? '' : 'Проверьте номер: нужно 10–15 цифр.');
    if (!okName) form.elements.name.focus(); else if (!okPhone) form.elements.phone.focus();
    return okName && okPhone;
  }
  ['name', 'phone'].forEach(function (n) {
    form.elements[n].addEventListener('input', function () { if (form.elements[n].getAttribute('aria-invalid') === 'true') fieldError(n, ''); });
  });
  var done = form.querySelector('.wf-done');
  function showDone(name) {
    done.querySelector('.wf-done-title').textContent = name ? 'Спасибо, ' + name + '!' : 'Заявка отправлена';
    done.hidden = false;
    form.classList.add('is-done');
    done.querySelector('.wf-done-again').focus({ preventScroll: true });
  }
  done.querySelector('.wf-done-again').addEventListener('click', function () {
    form.classList.remove('is-done'); done.hidden = true; form.elements.name.focus();
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    setStatus('', '');
    if (!validate()) return;
    var data = new FormData(form);
    btn.disabled = true;
    btn.firstChild.textContent = 'Отправляем…';
    fetch(window.WEBFOR.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.ok) {
          var name = form.elements.name.value.trim();
          form.reset();
          setStatus('', '');
          showDone(name);
        } else {
          if (res.field) fieldError(res.field, res.message);
          setStatus(res.message, 'err');
        }
      })
      .catch(function () { setStatus('Не удалось отправить. Проверьте интернет или напишите нам в WhatsApp.', 'err'); })
      .then(function () { btn.disabled = false; btn.firstChild.textContent = 'Отправить заявку'; });
  });
})();
