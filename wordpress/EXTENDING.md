# Как добавить новый плагин StudyHub

Ядро даёт API. Новый плагин подключается к нему, и ядро, сайт и кабинет обновлять не нужно.

```php
<?php
/**
 * Plugin Name: StudyHub Акции
 * Version: 1.0.0
 */
add_action('studyhub_ready', function ($api) {      // все плагины загружены; $api — версия API (1)
    studyhub_register_plugin(__FILE__);             // виден в панели → Настройки → Дополнения
});
```

## Адреса
| Что | Как |
|---|---|
| Своя страница `/akcii/` | фильтр `studyhub_routes`: `$r['akcii'] = ['slug' => 'akcii', 'render' => 'my_render']` |
| Ссылка на страницу | `studyhub_url('akcii')` |
| Файл из папки `assets/` плагина | `studyhub_asset_url('file.css', __FILE__)` |
| Общий дизайн | `studyhub_head_tags()` в `<head>` подключает `ui.css` ядра; иконки: `studyhub_icon('star')` |

## Панель `/panel/`
| Что | Как |
|---|---|
| Свой раздел с настройками | фильтр `studyhub_panel_sections`: `['title','icon','intro','fields'=>[[name,label,type,help]], 'get'=>fn, 'save'=>fn($values,$action), 'actions'=>[['id','label']]]` (пример — `studyhub-ai/includes/class-panel.php`) |
| Типы полей | `text`, `number`, `url`, `password`, `textarea`, `checkbox`, `select` (`options`), `info` |

## Сайт
| Что | Как |
|---|---|
| Новая страница в меню | фильтр `studyhub_site_pages`: `$p['akcii'] = ['slug'=>'akcii','nav'=>'nav_akcii','icon'=>'star']` |
| Содержимое страницы | фильтр `studyhub_site_page_html($html, $key)` |
| Блок на главной | фильтр `studyhub_site_home_sections`. Ключ задаёт место: 10 — первый экран, 20 — услуги, 25 — вузы, 30 — «как это работает», 35 — ИИ, 40 — отзывы, 50 — «получить доступ» |
| Переводы | фильтр `studyhub_site_dict`: `'nav_akcii' => ['Акции', 'Акциялар']` |
| Стили и скрипты | действия `studyhub_site_head`, `studyhub_site_foot` |
| SEO | фильтры `studyhub_site_seo_title`, `studyhub_site_seo_desc`, `studyhub_site_seo_image` |

## Кабинет студента
| Что | Как |
|---|---|
| Данные для JS | фильтр `studyhub_app_config($config, $student)` → `window.STUDYHUB` |
| Вошедший студент | `studyhub_current_student()` |
| Приложение на экран «Домой» | фильтр `studyhub_pwa_apps` |

## События
Подписка: `add_action('studyhub_event', function ($name, $data) {...}, 10, 2)` или на конкретное событие через `studyhub_event_{имя}`. Отправить своё событие: `studyhub_event('имя', [...])`.

| Событие | Когда |
|---|---|
| `student_login` | студент вошёл |
| `student_message` | студент написал (если это заказ, в данных есть `service`) |
| `admin_message` | вы ответили |
| `review` | студент оставил отзыв |
| `ai_answer` | ИИ ответил |
