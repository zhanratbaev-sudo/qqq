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

# демо-чаты: вымышленные клиенты, все номера — примеры
chats = [
    {'id': 501, 'status': 'waiting', 'contact': '+7 700 000 33 01', 'mins': 2, 'msgs': [
        ('user', '', 'Здравствуйте! Нужен сайт для салона красоты с онлайн-записью'),
        ('bot', '', 'Здравствуйте! 😊 Сайт с онлайн-записью — 15 000 ₸, срок 3–7 дней. Хостинг на год включён, домен оформляем отдельно.'),
        ('user', '', 'А скидку сделаете, если сразу закажу ещё и чат-бота?'),
        ('bot', '', 'За комплекс можно обсудить скидку — уточняем у менеджера, сейчас он вам ответит.')]},
    {'id': 502, 'status': 'bot', 'contact': '', 'mins': 25, 'msgs': [
        ('user', '', 'Сколько стоит приложение в App Store?'),
        ('bot', '', 'Приложение в App Store и Google Play — 95 000 ₸, срок 1–3 недели. Если нужен быстрый старт, есть PWA-приложение от 30 000 ₸ — ставится на телефон прямо с сайта.')]},
    {'id': 503, 'status': 'bot', 'contact': '', 'mins': 190, 'msgs': [
        ('user', '', 'Vet.izi подойдёт для зоомагазина?'),
        ('bot', '', 'Да, Vet.izi подходит и для зоо-бизнеса: запись, склад, оплаты и отчёты. Стоимость — от 30 000 ₸, разово.')]},
]
faq = [
    {'q': 'Сколько стоит сайт?', 'n': 14, 'mins': 30}, {'q': 'Какие сроки разработки?', 'n': 9, 'mins': 70},
    {'q': 'Можно ли оплатить в рассрочку?', 'n': 6, 'mins': 200}, {'q': 'Что такое Vet.izi?', 'n': 5, 'mins': 300},
    {'q': 'Делаете ли вы рекламу в Instagram?', 'n': 3, 'mins': 900},
]
demo['chats'] = [{'id': c['id'], 'status': c['status'], 'contact': c['contact'], 'mins': c['mins'],
                  'msgs': [{'i': i + 1, 'r': r, 'a': a, 't': t} for i, (r, a, t) in enumerate(c['msgs'])]} for c in chats]
demo['faq'] = faq

adapter = r"""
(function () {
  var S = __DEMO__;
  var now = Date.now();
  function iso(min) { return new Date(now - min * 60000).toISOString(); }
  S.leads.forEach(function (l) { l.date = iso(l.minutes); l.tel = 'tel:' + l.phone.replace(/\s/g, ''); l.wa = 'https://wa.me/' + l.phone.replace(/\D/g, ''); });
  S.ai.questions.forEach(function (q) { q.date = iso(q.minutes); });
  var nextId = 1000;
  var CH = S.chats; delete S.chats; var FAQ = S.faq; delete S.faq;
  CH.forEach(function (c) { c.updated = Math.floor(now / 1000) - c.mins * 60; c.unread = c.status === 'waiting' ? 2 : 0; c.msgs.forEach(function (m) { m.at = c.updated; }); });
  FAQ.forEach(function (f) { f.at = Math.floor(now / 1000) - f.mins * 60; });
  function addMsg(c, r, t, a) { var m = { i: c.msgs.length + 1, r: r, a: a || '', t: t, at: Math.floor(Date.now() / 1000) }; c.msgs.push(m); c.updated = m.at; return m; }
  function ok(v) { return new Promise(function (r) { setTimeout(function () { r(JSON.parse(JSON.stringify(v))); }, 150); }); }
  function bad(msg) { return new Promise(function (_, j) { setTimeout(function () { j(new Error(msg)); }, 150); }); }
  window.WFADMIN = { chats: { manager: 'Нуржан', vapid: '' }, adapter: {
    chatApi: function (method, path, body) {
      var m, c;
      if (path === '/chats') return ok({ chats: CH.slice().sort(function (a, b) { var r = { waiting: 0, live: 1, bot: 2 }; return (r[a.status] - r[b.status]) || (b.updated - a.updated); }).map(function (c) {
        var last = c.msgs.filter(function (x) { return x.r === 'user'; }).pop();
        return { id: c.id, status: c.status, contact: c.contact, unread: c.unread, updated: c.updated, last: last ? last.t : '', count: c.msgs.length };
      }) });
      if (path === '/faq') return ok({ items: FAQ });
      if ((m = path.match(/^\/chat\/(\d+)(\/\w+)?(?:\?after=(\d+))?$/))) {
        c = CH.find(function (x) { return x.id == m[1]; }); if (!c) return bad('Чат не найден');
        var act = m[2], after = +(m[3] || 0);
        if (!act) { c.unread = 0; return ok({ status: c.status, contact: c.contact, msgs: c.msgs.filter(function (x) { return x.i > after; }) }); }
        if (act === '/release') { c.status = 'bot'; addMsg(c, 'system', 'Менеджер вышел из чата — дальше снова отвечает ассистент.'); return ok({ ok: true, status: 'bot' }); }
        if (c.status !== 'live') { c.status = 'live'; addMsg(c, 'system', 'Менеджер присоединился, сейчас ответит вам.'); }
        if (act === '/send') {
          var msg = addMsg(c, 'manager', body.text, 'Нуржан');
          if (!c.replied) { c.replied = true; setTimeout(function () { addMsg(c, 'user', 'Спасибо! Тогда давайте сайт и бота вместе 👍'); c.unread++; }, 3500); } // ответ «клиента» в демо
          return ok({ ok: true, msg: msg });
        }
        return ok({ ok: true, status: 'live' });
      }
      return bad('Неизвестный запрос');
    },
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
<div class="demo-note">Демо админки с примерами данных: всё можно нажимать и менять, после перезагрузки всё сбросится. Откройте «Чаты» → «Присоединиться» и напишите клиенту. На сайте админка открывается по адресу /webfor-admin/ и ставится на телефон как приложение.</div>
<div id="wfa-app" class="wfa"><p class="wfa-loading">Загружаем панель…</p></div>
<script>{adapter}</script>
<script>{js}</script>
"""
out = HERE / 'admin'
out.mkdir(exist_ok=True)
(out / 'index.html').write_text(page)
print('admin/index.html', len(page) // 1024, 'KB,', len(works), 'работ')
