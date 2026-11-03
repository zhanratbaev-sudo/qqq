"""Демо панели WebFor для просмотра в чате: та же панель (assets/admin-app.js), но данные — примеры в памяти страницы.
Ничего не сохраняется: после перезагрузки всё как было. Использование: python3 build_admin.py
"""
import json
import re
from pathlib import Path

HERE = Path(__file__).parent
SITE = HERE.parent / 'webfor-site'
ai = json.loads((HERE / 'ai.json').read_text())

# работы — те же примеры, что ставит плагин (includes/works.php), картинки из works/
php = (SITE / 'includes' / 'works.php').read_text()
works = []
for i, m in enumerate(re.finditer(r"\$w\('(\w+)', '([^']*)', '([^']*)', '([^']*)', '([^']*)'(?:, '([^']*)')?(?:, '(\w+)')?", php)):
    folder, title, img, tag, desc, url, shape = m.groups()
    works.append({'id': 100 + i, 'title': title, 'folder': folder, 'tag': tag, 'desc': desc, 'url': url or '', 'shape': shape or 'wide',
                  'image_id': 0, 'src': 'works/' + img, 'order': i})

demo = {
    'site_url': 'https://web.for.petgo.kz',
    'folders': [{'key': 'sites', 'title': 'Сайты'}, {'key': 'apps', 'title': 'Мобильные приложения'}, {'key': 'bots', 'title': 'AI-чат-боты'}, {'key': 'izi', 'title': 'Izi — всё в одном'}],
    'statuses': {'new': 'Новая', 'work': 'В работе', 'done': 'Готово'},
    'settings': {'phone': '+7 747 817 57 12', 'whatsapp': '+7 747 817 57 12'},
    'leads': [
        {'id': 1, 'name': 'Айгерим (пример)', 'phone': '+7 700 000 11 01', 'service_label': 'Vet.izi — для ветклиник и зоо-бизнеса', 'contact': 'whatsapp', 'message': 'Ветклиника, 3 врача. Хотим онлайн-запись и CRM.', 'status': 'new', 'minutes': 12},
        {'id': 2, 'name': 'Данияр (пример)', 'phone': '+7 700 000 11 02', 'service_label': 'AI-чат-бот для бизнеса', 'contact': 'call', 'message': 'Нужен бот в Instagram для салона.', 'status': 'new', 'minutes': 95},
        {'id': 3, 'name': 'Мария (пример)', 'phone': '+7 700 000 11 03', 'service_label': 'Сайт', 'contact': 'call', 'message': '', 'status': 'work', 'minutes': 60 * 26},
        {'id': 4, 'name': 'Ерлан (пример)', 'phone': '+7 700 000 11 04', 'service_label': 'Мобильное приложение', 'contact': 'whatsapp', 'message': 'Приложение для курьеров.', 'status': 'done', 'minutes': 60 * 72},
    ],
    'ai': {
        'enabled': True, 'has_key': True, 'model': 'claude-haiku-5-5', 'settings_url': '#', 'knowledge': ai['kb'],
        'learned': [{'q': 'Работаете ли вы с клиниками в других городах?', 'a': 'Да, работаем по всему Казахстану: всё обсуждаем онлайн, запуск тоже удалённо.'}],
        'questions': [
            {'id': 11, 'q': 'Сколько стоит Vet.izi для клиники с двумя филиалами?', 'reason': 'Точной цены нет в базе знаний.', 'contact': '+7 700 000 22 01', 'answer': '', 'state': 'new', 'learned': False, 'minutes': 40,
             'transcript': [{'role': 'user', 'content': 'Здравствуйте, у нас 2 филиала ветклиники'}, {'role': 'assistant', 'content': 'Здравствуйте! Vet.izi как раз для этого: запись, CRM и приложение для команды.'}, {'role': 'user', 'content': 'Сколько это будет стоить для двух филиалов?'}]},
            {'id': 12, 'q': 'Можно ли подключить оплату Kaspi на сайте?', 'reason': 'В базе знаний нет информации об оплате.', 'contact': '', 'answer': '', 'state': 'new', 'learned': False, 'minutes': 180,
             'transcript': [{'role': 'user', 'content': 'А оплату через Kaspi можно сделать на сайте?'}]},
        ],
    },
}

adapter = r"""
(function () {
  var S = __DEMO__;
  var now = Date.now();
  function iso(min) { return new Date(now - min * 60000).toISOString(); }
  S.leads.forEach(function (l) { l.date = iso(l.minutes); l.tel = 'tel:' + l.phone.replace(/\s/g, ''); l.wa = 'https://wa.me/' + l.phone.replace(/\D/g, ''); });
  S.ai.questions.forEach(function (q) { q.date = iso(q.minutes); });
  var nextId = 1000;
  function ok(v) { return new Promise(function (r) { setTimeout(function () { r(JSON.parse(JSON.stringify(v))); }, 150); }); }
  function bad(msg) { return new Promise(function (_, j) { setTimeout(function () { j(new Error(msg)); }, 150); }); }
  window.WFADMIN = { adapter: {
    api: function (method, path, body) {
      var m;
      if (path === '/state') return ok(S);
      if ((m = path.match(/^\/lead\/(\d+)$/))) {
        var l = S.leads.find(function (x) { return x.id == m[1]; });
        if (method === 'DELETE') { S.leads = S.leads.filter(function (x) { return x !== l; }); return ok({ ok: true }); }
        l.status = body.status; return ok(l);
      }
      if (path === '/work') {
        if (!body.title) return bad('Укажите название работы');
        var w = S.works.find(function (x) { return x.id === body.id; });
        if (!w) { w = { id: nextId++, order: S.works.length }; S.works.push(w); }
        Object.assign(w, body, { id: w.id, src: pendingImg[body.image_id] || body.src || '' });
        return ok(w);
      }
      if ((m = path.match(/^\/work\/(\d+)$/))) { S.works = S.works.filter(function (x) { return x.id != m[1]; }); return ok({ ok: true }); }
      if (path === '/works/order') return ok({ ok: true });
      if (path === '/settings') {
        for (var k of ['phone', 'whatsapp']) { var n = (body[k] || '').replace(/\D/g, '').length; if (n < 10 || n > 15) return bad((k === 'phone' ? 'Телефон' : 'WhatsApp') + ': номер должен содержать от 10 до 15 цифр.'); }
        S.settings = { phone: body.phone, whatsapp: body.whatsapp }; return ok(S.settings);
      }
      if ((m = path.match(/^\/ai\/question\/(\d+)$/))) {
        var q = S.ai.questions.find(function (x) { return x.id == m[1]; });
        if (method === 'DELETE') { S.ai.questions = S.ai.questions.filter(function (x) { return x !== q; }); return ok({ ok: true }); }
        q.answer = body.answer; q.state = 'answered';
        if (body.learn && !q.learned) { q.learned = true; S.ai.learned.push({ q: q.q, a: body.answer }); }
        return ok({ ok: true, learned: S.ai.learned });
      }
      if (path === '/ai/knowledge') { S.ai.knowledge = body.knowledge; return ok({ ok: true }); }
      if (path === '/ai/learned') { S.ai.learned = body.rows; return ok({ ok: true, learned: S.ai.learned }); }
      return bad('Неизвестный запрос');
    },
    pickImage: function () {
      return new Promise(function (resolve) {
        var input = document.createElement('input'); input.type = 'file'; input.accept = 'image/*';
        input.onchange = function () { var f = input.files[0]; if (!f) return resolve(null); var id = nextId++; pendingImg[id] = URL.createObjectURL(f); resolve({ id: id, url: pendingImg[id] }); };
        input.click();
      });
    },
  } };
  var pendingImg = {};
})();
""".replace('__DEMO__', json.dumps({**demo, 'works': works}, ensure_ascii=False))

css = (SITE / 'assets' / 'admin-app.css').read_text()
js = (SITE / 'assets' / 'admin-app.js').read_text()
page = f"""<title>WebFor Панель</title>
<meta name="description" content="Демо панели управления WebFor">
<style>
:root {{ color-scheme: dark; }}
html, body {{ background: #000; }}
body {{ padding: 16px max(12px, env(safe-area-inset-right, 0px)) 16px max(12px, env(safe-area-inset-left, 0px)); }}
.demo-note {{ max-width: 1200px; margin: 0 auto 12px; padding: 10px 14px; border-radius: 12px; background: #0c100f; border: 1px solid #1b2321; color: #a9b7b2; font: 500 13px/1.45 "Inter", system-ui, sans-serif; }}
#wfa-app {{ max-width: 1200px; margin: 0 auto; }}
{css}
</style>
<div class="demo-note">Демо админки с примерами данных: всё можно нажимать и менять, но после перезагрузки страницы изменения сбросятся. На вашем сайте это пункт «WebFor» в админке WordPress.</div>
<div id="wfa-app" class="wfa"><p class="wfa-loading">Загружаем панель…</p></div>
<script>{adapter}</script>
<script>{js}</script>
"""
out = HERE / 'admin'
out.mkdir(exist_ok=True)
(out / 'index.html').write_text(page)
print('admin/index.html', len(page) // 1024, 'KB,', len(works), 'работ')
