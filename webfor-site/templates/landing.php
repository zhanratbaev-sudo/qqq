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

    <header class="wf-header">
        <div class="wf-wrap wf-header-in">
            <a class="wf-logo" href="#wf-top" aria-label="WebFor — наверх"><?php echo webfor_mark(32); ?><span>Web<b>For</b></span></a>
            <nav class="wf-nav" id="wf-nav" aria-label="Разделы">
                <a href="#uslugi">Услуги</a>
                <a href="#izi">Izi</a>
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
                                            <button type="button" class="wf-work-btn" data-title="<?php echo esc_attr($w['title']); ?>" data-desc="<?php echo esc_attr($w['desc']); ?>" data-tag="<?php echo esc_attr($w['tag']); ?>" data-url="<?php echo esc_url($w['url']); ?>" data-src="<?php echo esc_url($w['src']); ?>" data-shape="<?php echo esc_attr($w['shape']); ?>" data-action="<?php echo esc_attr($w['action']); ?>">
                                                <span class="wf-work-shot"><img src="<?php echo esc_url($w['src']); ?>" alt="<?php echo esc_attr($w['title']); ?>" loading="lazy"></span>
                                                <span class="wf-work-meta"><b><?php echo esc_html($w['title']); ?></b><?php if ($w['tag']) : ?><em><?php echo esc_html($w['tag']); ?></em><?php endif; ?></span>
                                            </button>
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

        <section class="wf-section" id="izi" aria-labelledby="izi-h">
            <div class="wf-wrap">
                <header class="wf-section-head">
                    <p class="wf-eyebrow">Izi</p>
                    <h2 id="izi-h">Два варианта Izi</h2>
                    <p>Одна основа — сайт, CRM и приложение. Разница в том, под какой бизнес она настроена.</p>
                </header>
                <div class="wf-grid-2">
                    <article class="wf-card wf-product">
                        <div class="wf-product-head">
                            <span class="wf-product-icon wf-product-vet wf-anim-paw"><?php echo webfor_icon('paw'); ?></span>
                            <div><h3>Vet.izi</h3><p class="wf-muted">Для ветклиник, груминга, зоомагазинов и всех, кто помогает животным</p></div>
                        </div>
                        <ul class="wf-list">
                            <li>Сайт с онлайн-записью и заявками</li>
                            <li>CRM: клиенты, питомцы, заказы и статусы</li>
                            <li>Календарь команды и смены</li>
                            <li>Приложение для сотрудников, чаты и роли</li>
                            <li>Уведомления о новых заявках</li>
                        </ul>
                        <a class="wf-btn wf-btn-secondary wf-btn-block" href="#zayavka" data-service="vetizi">Хочу Vet.izi</a>
                    </article>
                    <article class="wf-card wf-product">
                        <div class="wf-product-head">
                            <span class="wf-product-icon wf-anim-spark"><?php echo webfor_icon('spark'); ?></span>
                            <div><h3>Izi</h3><p class="wf-muted">Для любого бизнеса — с AI-ассистентом</p></div>
                        </div>
                        <ul class="wf-list">
                            <li>Сайт, CRM и приложение для команды</li>
                            <li><b>AI-ассистент</b> отвечает клиентам и помогает разбирать заявки</li>
                            <li>Чаты, календарь, роли и уведомления</li>
                            <li>Модули под ваши процессы</li>
                        </ul>
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
                        <thead><tr><th scope="col"><span class="wf-sr">Возможность</span></th><th scope="col">Сайт</th><th scope="col">Vet.izi</th><th scope="col">Izi</th></tr></thead>
                        <tbody>
                            <tr><th scope="row">Сайт под ваш дизайн</th><td><?php echo $yes; ?></td><td><?php echo $yes; ?></td><td><?php echo $yes; ?></td></tr>
                            <tr><th scope="row">Онлайн-заявки, WhatsApp, звонок</th><td><?php echo $yes; ?></td><td><?php echo $yes; ?></td><td><?php echo $yes; ?></td></tr>
                            <tr><th scope="row">CRM: клиенты, заказы, статусы</th><td><?php echo $no; ?></td><td><?php echo $yes; ?></td><td><?php echo $yes; ?></td></tr>
                            <tr><th scope="row">Приложение для сотрудников</th><td><?php echo $no; ?></td><td><?php echo $yes; ?></td><td><?php echo $yes; ?></td></tr>
                            <tr><th scope="row">Чаты, календарь, роли</th><td><?php echo $no; ?></td><td><?php echo $yes; ?></td><td><?php echo $yes; ?></td></tr>
                            <tr><th scope="row">Карточки питомцев и ветеринарная специфика</th><td><?php echo $no; ?></td><td><?php echo $yes; ?></td><td><?php echo $no; ?></td></tr>
                            <tr><th scope="row">AI-ассистент</th><td><?php echo $no; ?></td><td><?php echo $no; ?></td><td><?php echo $yes; ?></td></tr>
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
                    <details><summary>Сколько это стоит?</summary><p>Зависит от задачи: обычный сайт и система Izi — это разный объём работ. После короткого разговора назовём точную цену и сроки.</p></details>
                    <details><summary>Можно начать с сайта, а CRM добавить потом?</summary><p>Да. Сайт можно позже подключить к Izi — заявки начнут попадать в CRM, а команда получит приложение.</p></details>
                    <details><summary>Нужно ли скачивать приложение из App Store?</summary><p>Нет. Приложение для сотрудников устанавливается прямо из браузера на iPhone и Android за пару минут.</p></details>
                    <details><summary>Чем Vet.izi отличается от Izi?</summary><p>Vet.izi настроен под ветклиники и зоо-бизнес: карточки питомцев, запись, специфика работы с животными. Izi — универсальный вариант для любого бизнеса, и в нём есть AI-ассистент.</p></details>
                    <details><summary>Как работает AI-чат-бот?</summary><p>Мы обучаем его на ваших данных: услуги, цены, адреса, ответы на частые вопросы. Он отвечает клиентам в WhatsApp, Instagram, Telegram или на сайте, записывает и собирает заявки, а если вопрос сложный — передаёт его менеджеру.</p></details>
                    <details><summary>Можно поставить чат-бота на мой сайт?</summary><p>Да. Бот встраивается окном чата на любой сайт — и на тот, который делаем мы, и на уже существующий.</p></details>
                    <details><summary>Вы поддерживаете сайт после запуска?</summary><p>Да. Помогаем с изменениями, следим за работой и добавляем функции по мере роста.</p></details>
                </div>
            </div>
        </section>

        <section class="wf-section wf-section-form" id="zayavka" aria-labelledby="form-h">
            <div class="wf-wrap wf-form-grid">
                <div class="wf-form-intro">
                    <p class="wf-eyebrow">Заявка</p>
                    <h2 id="form-h">Расскажите о задаче</h2>
                    <p>Оставьте контакты — свяжемся, уточним детали и предложим решение. Это ни к чему не обязывает.</p>
                    <ul class="wf-contacts">
                        <?php if ($wf_tel) : ?><li><?php echo webfor_icon('phone'); ?><div><span>Телефон</span><a href="<?php echo esc_url($wf_tel); ?>"><?php echo esc_html($wf_phone); ?></a></div></li><?php endif; ?>
                        <?php if ($wf_wa) : ?><li><?php echo webfor_icon('chat'); ?><div><span>WhatsApp</span><a href="<?php echo esc_url($wf_wa); ?>" target="_blank" rel="noopener"><?php echo esc_html(webfor_opt('whatsapp')); ?></a></div></li><?php endif; ?>
                        <?php if ($wf_email) : ?><li><?php echo webfor_icon('globe'); ?><div><span>E-mail</span><a href="mailto:<?php echo esc_attr($wf_email); ?>"><?php echo esc_html($wf_email); ?></a></div></li><?php endif; ?>
                    </ul>
                </div>

                <form class="wf-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate>
                    <input type="hidden" name="action" value="webfor_lead">
                    <?php wp_nonce_field('webfor_lead', '_wf_nonce', false); ?>
                    <div class="wf-hp" aria-hidden="true"><label>Сайт <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

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
                    <div class="wf-field">
                        <label for="wf-service">Что интересует</label>
                        <select id="wf-service" name="service">
                            <?php foreach (webfor_services() as $k => $label) : ?>
                                <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <fieldset class="wf-field wf-seg">
                        <legend>Как с вами связаться</legend>
                        <label><input type="radio" name="contact" value="call" checked><span>Позвонить</span></label>
                        <label><input type="radio" name="contact" value="whatsapp"><span>WhatsApp</span></label>
                    </fieldset>
                    <div class="wf-field">
                        <label for="wf-message">Комментарий <span class="wf-optional">необязательно</span></label>
                        <textarea id="wf-message" name="message" rows="3" maxlength="2000" placeholder="Например: нужен сайт для клиники и запись онлайн"></textarea>
                    </div>
                    <div class="wf-field">
                        <label class="wf-check"><input type="checkbox" name="consent" value="1" required><span>Согласен на обработку данных для связи со мной</span></label>
                        <p class="wf-error" id="wf-consent-err" hidden></p>
                    </div>
                    <button class="wf-btn wf-btn-primary wf-btn-block" type="submit">Отправить заявку</button>
                    <p class="wf-status <?php echo $wf_sent ? 'wf-status-ok' : ($wf_err ? 'wf-status-err' : ''); ?>" role="status" aria-live="polite"><?php
                        echo $wf_sent ? 'Спасибо! Заявка отправлена — мы скоро свяжемся с вами.' : esc_html($wf_err);
                    ?></p>
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
            </div>
        </div>
    </footer>

    <dialog class="wf-lightbox" aria-labelledby="wf-lb-title">
        <div class="wf-lb-in">
            <button class="wf-lb-close" type="button" aria-label="Закрыть"><?php echo webfor_icon('close'); ?></button>
            <div class="wf-lb-shot"><img alt=""></div>
            <div class="wf-lb-info">
                <em class="wf-lb-tag"></em>
                <h3 id="wf-lb-title"></h3>
                <p class="wf-lb-desc"></p>
                <div class="wf-lb-actions">
                    <button class="wf-btn wf-btn-primary wf-btn-sm wf-lb-chat" type="button" data-wfai-open="">Попробовать ассистента</button>
                    <span class="wf-lb-nav"><button type="button" class="wf-lb-prev" aria-label="Предыдущая работа">‹</button><button type="button" class="wf-lb-next" aria-label="Следующая работа">›</button></span>
                </div>
            </div>
        </div>
    </dialog>

    <div class="wf-mobile-bar" aria-label="Быстрая связь">
        <?php if ($wf_tel) : ?><a class="wf-btn wf-btn-secondary" href="<?php echo esc_url($wf_tel); ?>"><?php echo webfor_icon('phone'); ?>Позвонить</a><?php endif; ?>
        <?php if ($wf_wa) : ?><a class="wf-btn wf-btn-primary" href="<?php echo esc_url($wf_wa); ?>" target="_blank" rel="noopener"><?php echo webfor_icon('chat'); ?>WhatsApp</a><?php endif; ?>
    </div>
</div>
