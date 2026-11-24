/* StudyHub — сайт: переключение языка (запоминается и для кабинета) */
(function () {
  'use strict';
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-lang]');
    if (!b) return;
    var l = b.getAttribute('data-lang') === 'kk' ? 'kk' : 'ru';
    document.cookie = 'studyhub_lang=' + l + ';path=/;max-age=31536000;samesite=lax';
    try { localStorage.setItem('studyhub_lang', l); } catch (err) { /* ignore */ }
    location.reload();
  });
})();
