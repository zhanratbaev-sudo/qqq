<?php
/** Содержимое сайта WebFor. Телефон и WhatsApp берутся из настроек (WebFor → Настройки). */
defined('ABSPATH') || exit;

$wf_phone = webfor_opt('phone');
$wf_tel = webfor_tel_href();
$wf_wa = webfor_wa_href();
$wf_email = webfor_opt('email');
$wf_sent = isset($_GET['wf_sent']);
$wf_err = isset($_GET['wf_err']) ? sanitize_text_field(wp_unslash($_GET['wf_err'])) : '';
$yes = '<span class="wf-yes" aria-label="есть">' . webfor_icon('check') . '</span>';
$no = '<span class="wf-no" aria-label="нет">' . webfor_icon('dash') . '</span>';
?>
<div class="wf" id="wf-top">
    <a class="wf-skip" href="#wf-main">Перейти к содержимому</a>
    <!-- Жидкое стекло: преломление по краям (как в liquid-glass-js). Работает там, где backdrop-filter понимает SVG-фильтры (Chrome, Android). -->
    <svg class="wf-lg-defs" width="0" height="0" aria-hidden="true" focusable="false">
        <filter id="wf-lg" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
            <feTurbulence type="fractalNoise" baseFrequency="0.008 0.012" numOctaves="2" seed="7" result="noise"/>
            <feGaussianBlur in="noise" stdDeviation="2" result="soft"/>
            <feDisplacementMap in="SourceGraphic" in2="soft" scale="38" xChannelSelector="R" yChannelSelector="G"/>
        </filter>
    </svg>

    <header class="wf-header">
        <div class="wf-wrap wf-header-in">
            <a class="wf-logo" href="#wf-top" aria-label="WebFor — наверх"><?php echo webfor_mark(32); ?><span>Web<b>For</b></span></a>
            <nav class="wf-nav" id="wf-nav" aria-label="Разделы">
                <a href="#uslugi">Услуги</a>
                <a href="#ceny">Цены</a>
                <a href="#sravnenie">Сравнение</a>
                <a href="#faq">Вопросы</a>
            </nav>
            <div class="wf-header-actions">
                <?php if ($wf_tel) : ?><a class="wf-header-phone" href="<?php echo esc_url($wf_tel); ?>"><?php echo esc_html($wf_phone); ?></a><?php endif; ?>
                <?php if ($wf_wa) : ?><a class="wf-btn wf-btn-sm wf-btn-secondary" href="<?php echo esc_url($wf_wa); ?>" target="_blank" rel="noopener"><?php echo webfor_icon('chat'); ?>WhatsApp</a><?php endif; ?>
                <button class="wf-burger" type="button" aria-controls="wf-nav" aria-expanded="false" aria-label="Открыть меню"><i></i><i></i><i></i></button>
            </div>
        </div>
    </header>

    <main id="wf-main">
        <section class="wf-hero">
            <div class="wf-wrap wf-hero-in">
                <div class="wf-hero-text">
                <p class="wf-eyebrow">Цифровые продукты для бизнеса</p>
                <h1>Сайты и системы, с которыми работать проще</h1>
                <p class="wf-lead">Делаем сайты и мобильные приложения, ставим AI-чат-ботов, которые сами отвечают клиентам, и внедряем CRM в работу команды. Берём на себя всё — от идеи до запуска и поддержки.</p>
                <div class="wf-actions">
                    <a class="wf-btn wf-btn-primary" href="#zayavka">Обсудить проект <?php echo webfor_icon('arrow'); ?></a>
                    <?php if (function_exists('wfai_opt') && wfai_opt('enabled')) : ?><button class="wf-btn wf-btn-secondary" type="button" data-wfai-open=""><?php echo webfor_icon('bot'); ?>Спросить AI-ассистента</button>
                    <?php elseif ($wf_wa) : ?><a class="wf-btn wf-btn-secondary" href="<?php echo esc_url($wf_wa); ?>" target="_blank" rel="noopener"><?php echo webfor_icon('chat'); ?>Написать в WhatsApp</a><?php endif; ?>
                </div>
                </div>
                <?php $art = array_filter([webfor_works('sites')[0] ?? null, webfor_works('apps')[0] ?? null, webfor_works('bots')[0] ?? null]); ?>
                <?php if (count($art) === 3) : $art = array_values($art); ?>
                    <div class="wf-hero-art" aria-hidden="true">
                        <img class="wf-art-site" src="<?php echo esc_url($art[0]['src']); ?>" alt="">
                        <img class="wf-art-app" src="<?php echo esc_url($art[1]['src']); ?>" alt="">
                        <img class="wf-art-bot" src="<?php echo esc_url($art[2]['src']); ?>" alt="">
                        <div class="wf-glass wf-art-chip wf-art-chip-1"><?php echo webfor_icon('bot'); ?><span><b>AI ответил клиенту</b>в 23:40, без менеджера</span></div>
                        <div class="wf-glass wf-art-chip wf-art-chip-2"><?php echo webfor_icon('check'); ?><span><b>Новая заявка</b>сразу в CRM</span></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="wf-section" id="uslugi" aria-labelledby="uslugi-h">
            <div class="wf-wrap">
                <header class="wf-section-head">
                    <p class="wf-eyebrow">Услуги и работы</p>
                    <h2 id="uslugi-h">Что мы делаем</h2>
                    <p>Откройте папку — внутри наши работы. Нажмите на карточку, чтобы рассмотреть её ближе.</p>
                </header>
                <div class="wf-folders" role="list">
                    <?php foreach (webfor_folders() as $fk => $f) : $works = webfor_works($fk); ?>
                        <div class="wf-folder-wrap" role="listitem">
                            <button class="wf-folder" type="button" id="wf-f-<?php echo esc_attr($fk); ?>" aria-haspopup="dialog" aria-controls="wf-p-<?php echo esc_attr($fk); ?>" data-folder="<?php echo esc_attr($fk); ?>">
                                <span class="wf-folder-peek" aria-hidden="true">
                                    <?php foreach (array_slice($works, 0, 3) as $i => $w) : ?>
                                        <img src="<?php echo esc_url($w['src']); ?>" alt="" loading="lazy" class="wf-peek-<?php echo (int) $i; ?>">
                                    <?php endforeach; ?>
                                </span>
                                <span class="wf-folder-body">
                                    <span class="wf-folder-icon wf-anim-<?php echo esc_attr($f['icon']); ?>"><?php echo webfor_icon($f['icon']); ?></span>
                                    <span class="wf-folder-title"><?php echo esc_html($f['title']); ?></span>
                                    <span class="wf-folder-count"><?php echo count($works); ?> <?php echo esc_html(webfor_plural(count($works), 'работа', 'работы', 'работ')); ?> · открыть</span>
                                </span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php foreach (webfor_folders() as $fk => $f) : $works = webfor_works($fk); $svc = ['sites' => 'site', 'apps' => 'app', 'bots' => 'bot', 'izi' => 'izi'][$fk]; ?>
                    <div class="wf-page" id="wf-p-<?php echo esc_attr($fk); ?>" data-folder="<?php echo esc_attr($fk); ?>" role="dialog" aria-modal="true" aria-labelledby="wf-ph-<?php echo esc_attr($fk); ?>" hidden>
                        <div class="wf-page-bar">
                            <div class="wf-wrap wf-page-bar-in">
                                <button class="wf-page-back" type="button"><?php echo webfor_icon('back'); ?><span>Все услуги</span></button>
                                <span class="wf-page-crumb">WebFor <i>/</i> <?php echo esc_html($f['title']); ?></span>
                                <a class="wf-btn wf-btn-primary wf-btn-sm" href="#zayavka" data-service="<?php echo esc_attr($svc); ?>">Обсудить</a>
                            </div>
                        </div>
                        <div class="wf-page-scroll">
                            <div class="wf-wrap">
                                <header class="wf-page-head">
                                    <span class="wf-page-icon wf-anim-<?php echo esc_attr($f['icon']); ?>"><?php echo webfor_icon($f['icon']); ?></span>
                                    <h2 id="wf-ph-<?php echo esc_attr($fk); ?>"><?php echo esc_html($f['title']); ?></h2>
                                    <p><?php echo esc_html($f['text']); ?></p>
                                    <span class="wf-page-count"><?php echo count($works); ?> <?php echo esc_html(webfor_plural(count($works), 'работа', 'работы', 'работ')); ?></span>
                                </header>
                                <ul class="wf-cards">
                                    <?php foreach ($works as $i => $w) : ?>
                                        <li class="wf-work wf-work-<?php echo esc_attr($w['shape']); ?>" style="--i:<?php echo (int) $i; ?>">
                                            <figure class="wf-work-fig">
                                                <span class="wf-work-shot"><img src="<?php echo esc_url($w['src']); ?>" alt="<?php echo esc_attr($w['title']); ?>" loading="lazy"></span>
                                                <figcaption class="wf-work-meta"><b><?php echo esc_html($w['title']); ?></b><?php if ($w['tag']) : ?><em><?php echo esc_html($w['tag']); ?></em><?php endif; ?><?php if ($w['desc']) : ?><span><?php echo esc_html($w['desc']); ?></span><?php endif; ?></figcaption>
                                            </figure>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="wf-page-foot">
                                    <p><b>Нужно что-то похожее?</b> Расскажите о задаче — предложим решение и назовём цену.</p>
                                    <div class="wf-actions">
                                        <a class="wf-btn wf-btn-primary" href="#zayavka" data-service="<?php echo esc_attr($svc); ?>">Оставить заявку <?php echo webfor_icon('arrow'); ?></a>
                                        <?php if (function_exists('wfai_opt') && wfai_opt('enabled')) : ?><button class="wf-btn wf-btn-secondary" type="button" data-wfai-open=""><?php echo webfor_icon('bot'); ?>Спросить AI</button><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="wf-section" id="ceny" aria-labelledby="ceny-h">
            <div class="wf-wrap">
                <header class="wf-section-head">
                    <p class="wf-eyebrow">Услуги и цены</p>
                    <h2 id="ceny-h">Главное — AI-ассистент</h2>
                    <p>Он отвечает вашим клиентам сам, днём и ночью. А ещё делаем сайты, приложения и систему Izi. Все цены — «от», точную назовём после короткого разговора.</p>
                </header>
                <article class="wf-card wf-svc-ai" aria-labelledby="svc-ai-h">
                    <div class="wf-svc-ai-main">
                        <div class="wf-product-head">
                            <span class="wf-product-icon wf-anim-bot"><?php echo webfor_icon('bot'); ?></span>
                            <div><h3 id="svc-ai-h">AI-ассистент для бизнеса</h3><p class="wf-muted">Обучаем на ваших услугах и ценах. Отвечает за секунды, записывает клиентов и собирает заявки, сложное передаёт вам.</p></div>
                        </div>
                        <ul class="wf-svc-channels" aria-label="Где работает">
                            <li><?php echo webfor_icon('globe'); ?>Сайт</li>
                            <li><?php echo webfor_icon('chat'); ?>WhatsApp</li>
                            <li><?php echo webfor_icon('chat'); ?>Instagram</li>
                            <li><?php echo webfor_icon('chat'); ?>Telegram</li>
                        </ul>
                    </div>
                    <div class="wf-svc-ai-side">
                        <ul class="wf-prices">
                            <li><span>На сайт или в один мессенджер</span><b>30 000 ₸</b></li>
                            <li><span>Сразу везде: сайт, WhatsApp, Instagram, Telegram</span><b>40 000 ₸</b></li>
                            <li><span>Работа ассистента</span><b>10 000 ₸/мес</b></li>
                            <li><span>Тестовая неделя после запуска</span><b>бесплатно</b></li>
                        </ul>
                        <div class="wf-actions">
                            <a class="wf-btn wf-btn-primary" href="#zayavka" data-service="bot">Хочу AI-ассистента <?php echo webfor_icon('arrow'); ?></a>
                            <?php if (function_exists('wfai_opt') && wfai_opt('enabled')) : ?><button class="wf-btn wf-btn-secondary" type="button" data-wfai-open=""><?php echo webfor_icon('bot'); ?>Попробовать</button><?php endif; ?>
                        </div>
                    </div>
                </article>
                <div class="wf-svc-grid">
                    <article class="wf-card wf-svc" aria-labelledby="svc-site-h">
                        <div class="wf-product-head"><span class="wf-card-icon"><?php echo webfor_icon('globe'); ?></span><h3 id="svc-site-h">Сайты</h3></div>
                        <ul class="wf-prices">
                            <li><span>Лендинг</span><b>от 10 000 ₸</b></li>
                            <li><span>Сайт с онлайн-записью</span><b>от 15 000 ₸</b></li>
                            <li><span>Корпоративный сайт с AI-ассистентом</span><b>от 30 000 ₸</b></li>
                            <li><span>Интернет-магазин</span><b>от 30 000 ₸</b></li>
                        </ul>
                        <a class="wf-btn wf-btn-secondary wf-btn-block" href="#zayavka" data-service="site">Хочу сайт</a>
                    </article>
                    <article class="wf-card wf-svc" aria-labelledby="svc-app-h">
                        <div class="wf-product-head"><span class="wf-card-icon"><?php echo webfor_icon('device'); ?></span><h3 id="svc-app-h">Приложения</h3></div>
                        <ul class="wf-prices">
                            <li><span>Через браузер (PWA)<small>Ставится с сайта за минуту, на iPhone и Android, с уведомлениями</small></span><b>от 30 000 ₸</b></li>
                            <li><span>В Google Play<small>+ аккаунт разработчика Google: $25 один раз</small></span><b>от 95 000 ₸</b></li>
                            <li><span>В App Store<small>+ аккаунт разработчика Apple: $99 в год</small></span><b>от 95 000 ₸</b></li>
                        </ul>
                        <a class="wf-btn wf-btn-secondary wf-btn-block" href="#zayavka" data-service="app">Хочу приложение</a>
                    </article>
                    <article class="wf-card wf-svc wf-svc-quiet" aria-labelledby="svc-izi-h">
                        <div class="wf-product-head"><span class="wf-card-icon"><?php echo webfor_icon('layers'); ?></span><h3 id="svc-izi-h">Izi — всё в одном</h3></div>
                        <p>Сайт, CRM и приложение для команды в одной системе: заявки сразу в CRM, календарь, чаты, роли и AI-ассистент.</p>
                        <ul class="wf-prices"><li><span>Izi</span><b>от 50 000 ₸</b></li></ul>
                        <p class="wf-svc-note">Для ветклиник есть готовая версия — Vet.izi, от 30 000 ₸.</p>
                        <a class="wf-btn wf-btn-secondary wf-btn-block" href="#zayavka" data-service="izi">Хочу Izi</a>
                    </article>
                </div>
            </div>
        </section>

        <section class="wf-section" id="sravnenie" aria-labelledby="cmp-h">
            <div class="wf-wrap">
                <header class="wf-section-head">
                    <p class="wf-eyebrow">Сравнение</p>
                    <h2 id="cmp-h">Что входит</h2>
                </header>
                <div class="wf-table-wrap" tabindex="0" role="region" aria-label="Сравнение услуг">
                    <table class="wf-table">
                        <thead><tr><th scope="col"><span class="wf-sr">Возможность</span></th><th scope="col">Сайт</th><th scope="col">Сайт + AI</th><th scope="col">Izi</th></tr></thead>
                        <tbody>
                            <tr><th scope="row">Сайт под ваш дизайн</th><td><?php echo $yes; ?></td><td><?php echo $yes; ?></td><td><?php echo $yes; ?></td></tr>
                            <tr><th scope="row">Онлайн-заявки, WhatsApp, звонок</th><td><?php echo $yes; ?></td><td><?php echo $yes; ?></td><td><?php echo $yes; ?></td></tr>
                            <tr><th scope="row">AI-ассистент на сайте</th><td><?php echo $no; ?></td><td><?php echo $yes; ?></td><td><?php echo $yes; ?></td></tr>
                            <tr><th scope="row">AI в WhatsApp, Instagram, Telegram</th><td><?php echo $no; ?></td><td><?php echo $yes; ?></td><td><?php echo $yes; ?></td></tr>
                            <tr><th scope="row">CRM: клиенты, заказы, статусы</th><td><?php echo $no; ?></td><td><?php echo $no; ?></td><td><?php echo $yes; ?></td></tr>
                            <tr><th scope="row">Приложение для сотрудников</th><td><?php echo $no; ?></td><td><?php echo $no; ?></td><td><?php echo $yes; ?></td></tr>
                            <tr><th scope="row">Чаты, календарь, роли</th><td><?php echo $no; ?></td><td><?php echo $no; ?></td><td><?php echo $yes; ?></td></tr>
                            <tr><th scope="row">Регистрация пользователей</th><td><?php echo $no; ?></td><td><?php echo $no; ?></td><td><?php echo $yes; ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="wf-section" id="faq" aria-labelledby="faq-h">
            <div class="wf-wrap wf-narrow">
                <header class="wf-section-head">
                    <p class="wf-eyebrow">Вопросы</p>
                    <h2 id="faq-h">Частые вопросы</h2>
                </header>
                <div class="wf-faq">
                    <details><summary>Как работает AI-чат-бот?<span class="wf-x" aria-hidden="true"></span></summary><p>Мы обучаем его на ваших данных: услуги, цены, адреса, ответы на частые вопросы. Он отвечает клиентам в WhatsApp, Instagram, Telegram или на сайте, записывает и собирает заявки, а если вопрос сложный — передаёт его менеджеру.</p></details>
                    <details><summary>Сколько это стоит?<span class="wf-x" aria-hidden="true"></span></summary><p>Цены «от» — в разделе «Услуги и цены»: AI-ассистент от 30 000 ₸, лендинг от 10 000 ₸, приложение через браузер от 30 000 ₸. Точную сумму и сроки назовём после короткого разговора.</p></details>
                    <details><summary>Можно начать с сайта, а CRM добавить потом?<span class="wf-x" aria-hidden="true"></span></summary><p>Да. Сайт можно позже подключить к Izi — заявки начнут попадать в CRM, а команда получит приложение.</p></details>
                    <details><summary>Приложение через браузер или в App Store?<span class="wf-x" aria-hidden="true"></span></summary><p>Приложение через браузер (PWA) ставится прямо с сайта за минуту, работает на iPhone и Android, присылает уведомления — и стоит дешевле, от 30 000 ₸. Если нужно именно в App Store или Google Play — от 95 000 ₸, плюс аккаунт разработчика: Google — $25 один раз, Apple — $99 в год.</p></details>
                    
                    <details><summary>Можно поставить чат-бота на мой сайт?<span class="wf-x" aria-hidden="true"></span></summary><p>Да. Бот встраивается окном чата на любой сайт — и на тот, который делаем мы, и на уже существующий.</p></details>
                    <details><summary>Вы поддерживаете сайт после запуска?<span class="wf-x" aria-hidden="true"></span></summary><p>Да. Помогаем с изменениями, следим за работой и добавляем функции по мере роста.</p></details>
                </div>
            </div>
        </section>

        <section class="wf-section wf-section-form" id="zayavka" aria-labelledby="form-h">
            <div class="wf-wrap wf-form-grid">
                <div class="wf-form-intro">
                    <p class="wf-eyebrow">Заявка</p>
                    <h2 id="form-h">Расскажите о задаче</h2>
                    <p>Выберите, что нужно, и оставьте имя с телефоном. Свяжемся, уточним детали и предложим решение — это ни к чему не обязывает.</p>
                </div>

                <form class="wf-form<?php echo $wf_sent ? ' is-done' : ''; ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate>
                    <input type="hidden" name="action" value="webfor_lead">
                    <input type="hidden" name="contact" value="call">
                    <?php wp_nonce_field('webfor_lead', '_wf_nonce', false); ?>
                    <div class="wf-hp" aria-hidden="true"><label>Сайт <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                    <div class="wf-form-body">
                        <fieldset class="wf-pick">
                            <legend>Что вам нужно?</legend>
                            <div class="wf-pick-grid">
                                <?php
                                $picks = ['bot' => ['AI-ассистент', 'bot'], 'site' => ['Сайт', 'globe'], 'app' => ['Приложение', 'device'], 'izi' => ['Izi', 'layers'], 'unknown' => ['Посоветуйте', 'spark']];
                                foreach ($picks as $k => $pk) : ?>
                                    <label class="wf-pick-item wf-anim-<?php echo esc_attr($pk[1]); ?>">
                                        <input type="radio" name="service" value="<?php echo esc_attr($k); ?>"<?php checked($k, 'site'); ?>>
                                        <span><?php echo webfor_icon($pk[1]); ?><b><?php echo esc_html($pk[0]); ?></b></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <div class="wf-form-row">
                            <div class="wf-field">
                                <label for="wf-name">Имя</label>
                                <input id="wf-name" name="name" type="text" autocomplete="name" required maxlength="80" placeholder="Как к вам обращаться">
                                <p class="wf-error" id="wf-name-err" hidden></p>
                            </div>
                            <div class="wf-field">
                                <label for="wf-phone">Телефон</label>
                                <input id="wf-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" required placeholder="+7 700 000 00 00">
                                <p class="wf-error" id="wf-phone-err" hidden></p>
                            </div>
                        </div>
                        <button class="wf-btn wf-btn-primary wf-btn-block wf-send" type="submit"><span>Отправить заявку</span><?php echo webfor_icon('arrow'); ?></button>
                        <p class="wf-consent">Нажимая кнопку, вы соглашаетесь на обработку данных для связи с вами.</p>
                        <p class="wf-status <?php echo $wf_err ? 'wf-status-err' : ''; ?>" role="status" aria-live="polite"><?php echo esc_html($wf_err); ?></p>
                    </div>

                    <div class="wf-done" <?php echo $wf_sent ? '' : 'hidden'; ?>>
                        <svg class="wf-done-check" viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="28"/><path d="M20 33l8 8 16-17"/></svg>
                        <h3 class="wf-done-title">Заявка отправлена</h3>
                        <p class="wf-done-text">Мы свяжемся с вами в ближайшее время.</p>
                        <button type="button" class="wf-btn wf-btn-secondary wf-done-again">Отправить ещё одну</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <footer class="wf-footer">
        <div class="wf-wrap wf-footer-in">
            <div>
                <a class="wf-logo" href="#wf-top"><?php echo webfor_mark(28); ?><span>Web<b>For</b></span></a>
                <p class="wf-muted">Цифровые продукты для бизнеса<?php echo webfor_opt('city') ? ' · ' . esc_html(webfor_opt('city')) : ''; ?></p>
            </div>
            <div class="wf-footer-links">
                <?php if ($wf_tel) : ?><a href="<?php echo esc_url($wf_tel); ?>"><?php echo esc_html($wf_phone); ?></a><?php endif; ?>
                <?php if ($wf_wa) : ?><a href="<?php echo esc_url($wf_wa); ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
                <span class="wf-muted">© <?php echo esc_html(gmdate('Y')); ?> WebFor</span>
                <span class="wf-muted wf-version">Izi · версия <?php echo esc_html(WEBFOR_VERSION); ?></span>
            </div>
        </div>
    </footer>

    <div class="wf-mobile-bar" aria-label="Быстрая связь">
        <?php if ($wf_tel) : ?><a class="wf-btn wf-btn-secondary" href="<?php echo esc_url($wf_tel); ?>"><?php echo webfor_icon('phone'); ?>Позвонить</a><?php endif; ?>
        <?php if ($wf_wa) : ?><a class="wf-btn wf-btn-primary" href="<?php echo esc_url($wf_wa); ?>" target="_blank" rel="noopener"><?php echo webfor_icon('chat'); ?>WhatsApp</a><?php endif; ?>
    </div>
</div>
