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
