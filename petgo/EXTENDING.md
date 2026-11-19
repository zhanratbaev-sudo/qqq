# Как добавить новый плагин PetGo

С версии **34.5** у PetGo есть API. Новый плагин подключается к нему и **сразу работает** —
«PetGo Кабинет», «PetGo Сайт» и «PetGo Клиент» обновлять не нужно. Порядок установки не важен.
Готовый образец: `examples/petgo-primer/petgo-primer.php`.

## Скелет

```php
<?php
/**
 * Plugin Name: PetGo Акции
 * Version: 1.0.0
 */
add_action('petgo_ready', function ($api) {   // PetGo загружен; $api — версия API (сейчас 1)
    petgo_register_plugin(__FILE__);          // виден в приложении, файлы assets/ доступны
    // ... подключения ниже
});
```

## Сайт

| Что | Как |
|---|---|
| Новая страница `/akcii/` | фильтр `petgo_site_pages`: `$p['akcii'] = ['slug'=>'akcii','name'=>'Акции','title'=>'Акции','nav'=>true]` |
| Содержимое страницы | фильтр `petgo_site_page_html($html, $page, $atts)` — вернуть HTML |
| Блок на главной | фильтр `petgo_site_home_sections($sections)` — ключ = место: 10 первый экран, 20 услуги, 30 «как это работает», 40 отзывы, 50 заявка |
| Пункты меню | `'nav' => true` у страницы или фильтр `petgo_site_nav` |
| Свои стили и скрипты | действия `petgo_site_head` и `petgo_site_foot` (+ `petgo_asset_url('file.css')`) |
| Переводы KZ / EN | фильтр `petgo_site_dict_more` (`'Текст' => ['қазақша', 'English']`), строки для JS — `petgo_site_js_strings` |
| SEO и разметка | фильтры `petgo_site_seo_title`, `petgo_site_seo_desc`, `petgo_site_seo_image`, `petgo_site_schema` |
| Офлайн | новые страницы сохраняются service worker'ом сами; файлы для предзагрузки — `petgo_site_sw_precache` |

Карта сайта (`/petgo-sitemap.xml`), языки и переходы без перезагрузки работают для новых страниц автоматически.

## Приложение сотрудников

| Что | Как |
|---|---|
| Пункт меню | фильтр `petgo_nav_items($items, $adm)`: `$items['akcii'] = ['Акции', petgo_page_url(['pg'=>'akcii']), 'globe', 'd']` |
| Страница раздела | фильтр `petgo_app_page($done, $pg)` — вывести HTML и вернуть `true` |
| Счётчик у пункта | фильтр `petgo_nav_badge($n, $key)` |
| Строка в «Настройках» | фильтр `petgo_settings_rows`: `['icon'=>'globe','title'=>'…','sub'=>'…','url'=>'…']` |
| Формы | `petgo_form_open('my_action')` + `add_action('admin_post_petgo_my_action', …)` с `petgo_guard('admin')` |
| Заголовок своей страницы | фильтр `petgo_app_title($title, $pg)` (с 35.0) |
| Строка в окне заказа | фильтр `petgo_order_detail_top($html, $order_id, $admin)` (с 35.0) |

## Кабинет клиента

| Что | Как |
|---|---|
| Блок на главной | фильтр `petgo_cl_home_html($html, $uid)`; первым в «Для вас» — `petgo_cl_home_first($html, $uid)` (с 35.0) |
| Строки в «Аккаунте» | фильтр `petgo_cl_account_html($html, $uid)` |
| Свой лист (окно) | фильтр `petgo_cl_sheets($html, $uid)` + `petgo_cl_sheet('id', 'Заголовок', $html)`; открыть — кнопка `data-k-open="id"` |
| Данные для JS | фильтр `petgo_cl_cfg($cfg, $uid)` → в браузере `PGK.cfg()` |
| Свои запросы | действие `petgo_cl_op($op, $uid)` (ответ — `petgo_cl_json(true, [...])`); в браузере `PGK.api('op', {...})` |
| В браузере | `PGK.toast(текст)`, `PGK.open(id)`, `PGK.close()`, `PGK.tab('home'|'map'|'walks'|'acc')` |

## События

На сервере: `add_action('petgo_event', function ($name, $data) {...}, 10, 2)` или `petgo_event_{имя}`.
Свои события: `petgo_event('my_event', [...])`.

В браузере: `PGEvent.on('lead', function (data) {...})`, свои — `PGEvent('my_event', {...})`.

| Событие | Когда |
|---|---|
| `lead` | заявка с сайта |
| `pet_form` | анкета питомца отправлена |
| `sign_up` | клиент создал кабинет |
| `booking` | заказ из кабинета клиента (в браузере есть `value` — примерная цена) |
| `contact` | нажали «Позвонить» или WhatsApp (только в браузере) |
| `page_view` | переход по сайту без перезагрузки (только в браузере) |
| `order_assigned`, `order_arrived`, `order_picked`, `order_done`, `order_cancelled` | заказ сменил статус (на сервере) |

Рекламные цели (Google, Яндекс, Meta, TikTok, VK) подписаны на эти же события — новые события плагинов
тоже можно отправлять в рекламу через `PGEvent`.

## Источник клиента

Сайт запоминает метки рекламы (utm, gclid, yclid, fbclid, ttclid) и добавляет к каждой форме поле `pg_src`.
На сервере: `petgo_src_read()` → массив, `petgo_src_text($src)` → «google / cpc · кампания «…»».
