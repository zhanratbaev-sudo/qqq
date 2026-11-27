<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   35.0 ДОГОВОР С КЛИЕНТОМ
   Один договор на все заказы (выгул и визиты домой). Клиент подписывает его один раз:
   - через eGov mobile — электронной цифровой подписью (ЭЦП), такая же сила, как у подписи на бумаге;
     QR-подписание через сервис SIGEX: на телефоне открывается eGov mobile, на компьютере — QR-код;
   - без eGov — галочка «согласен» и кнопка в кабинете (в договоре записывается время, телефон и IP).
   Договор в PDF и файл подписи (.cms) хранятся в кабинете клиента; сотрудники видят их у клиента и в заказе.
   Реквизиты исполнителя — в приложении: Настройки → «Договор с клиентами».
   ============================================================ */

define('PETGO_CT_TPL', '2026.11');

function petgo_ct_cfg() {
    $c = get_option('petgo_ct', array());
    return array_merge(array('on' => '1', 'name' => '', 'idt' => 'ИИН', 'id' => '', 'addr' => '', 'phone' => '', 'city' => 'г. Алматы'), is_array($c) ? $c : array());
}

/* договор показываем клиентам, когда заполнены реквизиты исполнителя */
function petgo_ct_ready() {
    $c = petgo_ct_cfg();
    return $c['on'] === '1' && $c['name'] !== '' && $c['id'] !== '' && $c['addr'] !== '';
}

function petgo_ct_sigex() {
    return rtrim((string) apply_filters('petgo_ct_sigex', 'https://sigex.kz'), '/');
}

/* подписанный (принятый) договор клиента или null */
function petgo_ct_get($uid) {
    $r = get_user_meta((int) $uid, 'petgo_ct', true);
    return (is_array($r) && !empty($r['st'])) ? $r : null;
}

function petgo_ct_date($ts) {
    $m = array(1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря');
    return (int) wp_date('j', $ts, petgo_tz()) . ' ' . $m[(int) wp_date('n', $ts, petgo_tz())] . ' ' . wp_date('Y', $ts, petgo_tz()) . ' г.';
}

function petgo_ct_iin_mask($iin) {
    $iin = preg_replace('/\D/', '', (string) $iin);
    return $iin === '' ? '' : str_repeat('•', max(0, strlen($iin) - 4)) . substr($iin, -4);
}

/* версия текста: шаблон + реквизиты исполнителя */
function petgo_ct_ver() {
    $c = petgo_ct_cfg();
    return PETGO_CT_TPL . '-' . substr(md5($c['name'] . '|' . $c['idt'] . '|' . $c['id'] . '|' . $c['addr'] . '|' . $c['phone']), 0, 6);
}

/* данные для договора конкретного клиента */
function petgo_ct_data($uid, $name, $vet, $photo, $how, $ts = 0) {
    $ts = $ts ? $ts : time();
    return array(
        'uid' => (int) $uid, 'no' => petgo_cl_code($uid) . '/' . wp_date('dmy', $ts, petgo_tz()), 'ts' => $ts, 'date' => petgo_ct_date($ts),
        'name' => $name, 'phone' => petgo_site_phone_fmt((string) get_user_meta($uid, 'petgo_phone', true)),
        'vet' => $vet ? '1' : '0', 'photo' => $photo ? '1' : '0', 'how' => $how, 'acc' => null, 'ex' => petgo_ct_cfg(), 'ver' => petgo_ct_ver(),
    );
}

/* ============================================================
   ТЕКСТ ДОГОВОРА (одна основа для страницы в кабинете и для PDF). **…** — жирным.
   Взят из договоров «разовая прогулка» и «ежедневные визиты»; цены и номер карты убраны — они меняются,
   стоимость берётся из прайса на сайте на день заказа.
   ============================================================ */
function petgo_ct_blocks($d) {
    $ex  = $d['ex'];
    $exn = $ex['name'] . ', ' . $ex['idt'] . ' ' . $ex['id'] . ', адрес: ' . $ex['addr'];
    $cl  = $d['name'] !== '' ? $d['name'] : '____________________';
    $B   = array();
    $B[] = array('title', 'Договор оказания услуг по уходу за питомцем');
    $B[] = array('sub', 'выгул собак и посещение питомца на дому · № ' . $d['no']);
    $B[] = array('meta', $ex['city'] . ' · ' . $d['date']);
    $B[] = array('p', '**' . $ex['name'] . '**, ' . $ex['idt'] . ' ' . $ex['id'] . ', адрес: ' . $ex['addr'] . ', именуемый далее «Исполнитель», с одной стороны, и **' . $cl . '**, телефон ' . $d['phone'] . ', именуемый(ая) далее «Заказчик», с другой стороны, заключили настоящий договор о нижеследующем.');

    $B[] = array('h', '1. Предмет договора');
    $B[] = array('p', '1.1. Исполнитель обязуется за плату оказывать Заказчику услуги по уходу за питомцем Заказчика (далее — «Питомец»): выгул собаки, посещение Питомца на дому в отсутствие Заказчика и другие услуги, представленные на сайте petgo.kz (далее — «Услуги»), а Заказчик обязуется принимать и оплачивать Услуги.');
    $B[] = array('p', '1.2. Каждая Услуга оказывается по заказу Заказчика, оформленному в личном кабинете на сайте petgo.kz, в чате с менеджером или в мессенджере (далее — «Заказ»). В Заказе указываются вид Услуги, Питомец, дата, время, продолжительность, адрес и пожелания. Подтверждённый Исполнителем Заказ является неотъемлемой частью договора.');
    $B[] = array('p', '1.3. Сведения о Питомце и доме (кличка, вид и порода, возраст, здоровье и прививки, корм, лекарства, поведение, доступ в помещение, ветеринарная клиника, контакт для экстренной связи) Заказчик указывает в анкете питомца в личном кабинете (далее — «Анкета»). Анкета является неотъемлемой частью договора.');
    $B[] = array('p', '1.4. Договор заключается один раз и действует для всех Заказов Заказчика.');

    $B[] = array('h', '2. Порядок оказания услуг');
    $B[] = array('p', '2.1. Выгул проходит в безопасных местах рядом с местом передачи Питомца: во дворе, на улице, в парке или на площадке для выгула собак. Время начала и продолжительность указываются в Заказе.');
    $B[] = array('p', '2.2. При посещении на дому в визит по Заказу и Анкете могут входить: кормление по режиму, смена воды, прогулка (для собак), уборка лотка или уборка после Питомца, игра и общение, дача лекарств по схеме врача, полив цветов и другие поручения. Фактическое время визита может отличаться от указанного в Заказе не более чем на 30 минут.');
    $B[] = array('p', '2.3. Передача Питомца и приход Исполнителя подтверждаются QR-кодом Заказчика в личном кабинете. Если Заказчика нет дома, Исполнитель отмечает передачу без QR-кода, а Заказчик видит место Исполнителя на карте.');
    $B[] = array('p', '2.4. По итогам каждой прогулки или визита Исполнитель присылает фото Питомца и краткий отчёт (поел, погулял, самочувствие) в личный кабинет или мессенджер.');

    $B[] = array('h', '3. Доступ в помещение и ключи');
    $B[] = array('p', '3.1. Если Услуга оказывается в отсутствие Заказчика, Заказчик обеспечивает доступ в помещение (ключи, код замка, консьерж или иной способ) и указывает его в Анкете или Заказе. Передача и возврат ключей подтверждаются сообщением в личном кабинете или мессенджере.');
    $B[] = array('p', '3.2. Исполнитель обязуется: использовать ключи и доступ только для оказания Услуг; не передавать их третьим лицам и не делать копий; не приводить в помещение посторонних; не пользоваться имуществом Заказчика, кроме необходимого для ухода за Питомцем.');
    $B[] = array('p', '3.3. Исполнитель не разглашает адрес, коды доступа и любые сведения о жизни Заказчика, ставшие ему известными при оказании Услуг.');
    $B[] = array('p', '3.4. Исполнитель возвращает ключи в день окончания Заказа или в срок, согласованный сторонами.');
    $B[] = array('p', '3.5. Если в помещении есть камеры видеонаблюдения, Заказчик сообщает об этом Исполнителю до начала Услуг.');

    $B[] = array('h', '4. Обязанности Исполнителя');
    $B[] = array('p', '4.1. Приходить в согласованное время и оказывать Услуги в соответствии с Заказом и Анкетой.');
    $B[] = array('p', '4.2. Выгуливать собаку на поводке. Спускать с поводка только на огороженной площадке для выгула собак и только с согласия Заказчика.');
    $B[] = array('p', '4.3. Не оставлять Питомца без присмотра, не передавать его третьим лицам и не привязывать на улице; убирать за Питомцем и соблюдать правила содержания животных в г. Алматы.');
    $B[] = array('p', '4.4. Не кормить Питомца ничем, кроме корма и лакомств Заказчика или разрешённых им, и не позволять подбирать еду с земли.');
    $B[] = array('p', '4.5. Бережно относиться к Питомцу и имуществу Заказчика, после визита закрывать двери и окна и оставлять помещение в порядке.');
    $B[] = array('p', '4.6. Немедленно сообщать Заказчику о любых нестандартных ситуациях: травма, плохое самочувствие или отказ от еды, потеря Питомца, конфликт с людьми или животными, поломки и протечки в помещении.');

    $B[] = array('h', '5. Обязанности Заказчика');
    $B[] = array('p', '5.1. Полно и достоверно заполнить Анкету, в том числе сообщить о заболеваниях, аллергиях, агрессии, особенностях поведения и особенностях помещения, и своевременно её обновлять.');
    $B[] = array('p', '5.2. Подтвердить, что собака привита от бешенства и обработана от клещей и блох, и при необходимости предъявить ветеринарный паспорт.');
    $B[] = array('p', '5.3. Предоставить исправные поводок и шлейку (ошейник), а для визитов на дому — достаточный запас корма, воды, наполнителя, пакетов для уборки и лекарств на весь срок Заказа.');
    $B[] = array('p', '5.4. Заранее предупреждать Исполнителя о течке, недавней операции, назначенных лекарствах и других ограничениях.');
    $B[] = array('p', '5.5. Убрать ценные, хрупкие и опасные для Питомца предметы.');
    $B[] = array('p', '5.6. Быть на связи во время оказания Услуг или указать в Анкете доверенное лицо для экстренной связи.');
    $B[] = array('p', '5.7. Оплачивать Услуги в порядке, предусмотренном разделом 6.');

    $B[] = array('h', '6. Стоимость и оплата');
    $B[] = array('p', '6.1. Стоимость Услуг определяется по прайсу на сайте petgo.kz, действующему на дату оформления Заказа, с учётом действующих акций и скидок, и указывается при оформлении или подтверждении Заказа.');
    $B[] = array('p', '6.2. Оплата производится в размере 100% стоимости Заказа до начала оказания Услуг переводом по реквизитам Исполнителя, указанным при подтверждении Заказа, или по счёту на оплату в приложении Kaspi. Подтверждением оплаты служит квитанция о переводе.');
    $B[] = array('p', '6.3. Продление Заказа или дополнительное время по просьбе Заказчика согласуется сторонами в личном кабинете или мессенджере и оплачивается до начала продлеваемого периода, а дополнительное время прогулки — сразу после прогулки.');
    $B[] = array('p', '6.4. Если нужно докупить корм или другие товары, Заказчик заранее указывает марку и вес упаковки и переводит Исполнителю полную сумму до покупки; Исполнитель присылает чек или фото покупки. Расходы на такси до ветеринарной клиники и обратно Заказчик возмещает сразу после запроса Исполнителя. Исполнитель не обязан оплачивать эти расходы за свой счёт.');

    $B[] = array('h', '7. Отмена, перенос и возврат');
    $B[] = array('p', '7.1. Заказчик может бесплатно отменить или перенести Заказ, предупредив Исполнителя не позднее чем за 24 часа до начала Услуги; уплаченная сумма возвращается полностью.');
    $B[] = array('p', '7.2. При отмене позже этого срока оплата не возвращается.');
    $B[] = array('p', '7.3. Если Заказчик досрочно отказался от оставшихся визитов, возвращается стоимость неоказанных дней при условии, что отказ сообщён не позднее чем за 24 часа до неоказанного дня.');
    $B[] = array('p', '7.4. Исполнитель вправе отменить или перенести прогулку при погоде, опасной для здоровья Питомца, или по уважительной причине, заранее предупредив Заказчика. Если Исполнитель не может прийти, он заранее сообщает об этом и предоставляет замену (проверенную няню PetGo) или возвращает стоимость несостоявшихся Услуг.');
    $B[] = array('p', '7.5. Исполнитель вправе прекратить прогулку, если Питомец ведёт себя агрессивно и создаёт опасность для людей, животных или самого себя, и вернуть Питомца Заказчику. Оплата в этом случае не возвращается.');

    $B[] = array('h', '8. Непредвиденные ситуации и ветеринарная помощь');
    $B[] = array('p', '8.1. Если Питомцу понадобилась ветеринарная помощь, Исполнитель немедленно связывается с Заказчиком и доверенным лицом.');
    $B[] = array('p', $d['vet'] === '1'
        ? '8.2. **Заказчик даёт согласие:** если связаться с ним и доверенным лицом не удалось, Исполнитель вправе отвезти Питомца в ветеринарную клинику (при возможности — в клинику-партнёр) для оказания неотложной помощи. Расходы сверх неотложной помощи согласуются с Заказчиком.'
        : '8.2. **Заказчик не даёт согласия** на самостоятельное обращение Исполнителя в ветеринарную клинику: Исполнитель продолжает связываться с Заказчиком и доверенным лицом и действует по их указанию, кроме случаев прямой угрозы жизни Питомца.');
    $B[] = array('p', '8.3. Расходы на лечение, не связанные с виной Исполнителя, оплачивает Заказчик.');
    $B[] = array('p', '8.4. Если Питомец потерялся или убежал, Исполнитель немедленно сообщает Заказчику и по его указанию участвует в поисках.');

    $B[] = array('h', '9. Ответственность сторон');
    $B[] = array('p', '9.1. Стороны несут ответственность в соответствии с законодательством Республики Казахстан и настоящим договором.');
    $B[] = array('p', '9.2. Исполнитель отвечает за вред, причинённый Питомцу или имуществу Заказчика по его вине, а также за утерю ключей или доступа по его вине. Размер возмещения определяется на основании подтверждающих документов (чеки, акты, заключения врача, фото).');
    $B[] = array('p', '9.3. Исполнитель не отвечает за болезни и травмы Питомца, не связанные с его действиями, за скрытые заболевания и за последствия того, что Заказчик не сообщил или неверно указал сведения в Анкете.');
    $B[] = array('p', '9.4. Повреждения имущества самим Питомцем (грызёт мебель, пачкает пол) не являются виной Исполнителя, если он не нарушил условия раздела 4.');
    $B[] = array('p', '9.5. Заказчик отвечает за достоверность сведений о Питомце. Если Заказчик не предупредил об агрессивности Питомца и из-за этого причинён вред Исполнителю, третьим лицам или их имуществу, он возмещает вред в порядке, установленном законодательством.');
    $B[] = array('p', '9.6. Стороны освобождаются от ответственности при обстоятельствах непреодолимой силы.');

    $B[] = array('h', '10. Фото, видео и персональные данные');
    $B[] = array('p', '10.1. Заказчик **' . ($d['photo'] === '1' ? 'разрешает' : 'не разрешает') . '** публикацию фото и видео Питомца в социальных сетях @petgo.kz и на сайте petgo.kz. На публичных фото не должны быть видны адрес, лица людей и другие личные данные.');
    $B[] = array('p', '10.2. Исполнитель не публикует фото и данные Заказчика (лицо, адрес, телефон) без его отдельного согласия.');
    $B[] = array('p', '10.3. Стороны обрабатывают персональные данные друг друга только для исполнения договора. Заказчик согласен на обработку своих персональных данных и геоданных (место передачи Питомца, маршрут прогулки) для оказания Услуг.');

    $B[] = array('h', '11. Заключительные положения');
    $B[] = array('p', '11.1. Договор заключается в электронной форме. Текст договора предложен Исполнителем в личном кабинете Заказчика на сайте petgo.kz. Заказчик подписывает договор электронной цифровой подписью (ЭЦП) в приложении eGov mobile либо принимает его условия в личном кабинете, отметив согласие и нажав «Подписать без eGov». Стороны признают оба способа надлежащим заключением договора.');
    $B[] = array('p', '11.2. Договор вступает в силу с момента подписания (принятия) Заказчиком и действует бессрочно. Любая сторона вправе расторгнуть договор, уведомив другую сторону через личный кабинет или мессенджер; оплаченные Заказы при этом исполняются или возвращаются по правилам раздела 7.');
    $B[] = array('p', '11.3. Экземпляр договора хранится в личном кабинете Заказчика на сайте petgo.kz и доступен для скачивания в любое время.');
    $B[] = array('p', '11.4. Споры решаются путём переговоров, а при невозможности договориться — в суде по месту нахождения Исполнителя (г. Алматы) по законодательству Республики Казахстан.');

    $B[] = array('h', '12. Реквизиты и подписи сторон');
    $B[] = array('p', '**Исполнитель:** ' . $exn . ($ex['phone'] !== '' ? ', тел. ' . $ex['phone'] : '') . '.');
    $B[] = array('p', '**Заказчик:** ' . $cl . ', тел. ' . $d['phone'] . '.');
    $B[] = array('p', '**Подпись Исполнителя:** договор предложен Исполнителем в личном кабинете Заказчика на сайте petgo.kz.');
    if ($d['how'] === 'click' && is_array($d['acc'])) {
        $a   = $d['acc'];
        $B[] = array('p', '**Подпись Заказчика:** условия договора приняты в личном кабинете на сайте petgo.kz ' . wp_date('d.m.Y в H:i', (int) $a['t'], petgo_tz()) . ' (время Алматы): отмечено согласие и нажата кнопка «Подписать без eGov». Вход в кабинет — по номеру телефона ' . $d['phone'] . ($a['ip'] !== '' ? ', IP-адрес ' . $a['ip'] : '') . '.');
    } else {
        $B[] = array('p', '**Подпись Заказчика:** электронная цифровая подпись (ЭЦП) в приложении eGov mobile. Подпись хранится вместе с договором в файле .cms; её можно проверить в сервисе проверки ЭЦП (НУЦ РК или sigex.kz).');
    }
    return $B;
}

function petgo_ct_md($s) {
    return preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', esc_html($s));
}

function petgo_ct_html($d) {
    $h = '<div class="pgk-ct-doc">';
    foreach (petgo_ct_blocks($d) as $b) {
        if ($b[0] === 'title') {
            $h .= '<h3>' . esc_html($b[1]) . '</h3>';
        } elseif ($b[0] === 'sub' || $b[0] === 'meta') {
            $h .= '<p class="pgk-ct-m">' . esc_html($b[1]) . '</p>';
        } elseif ($b[0] === 'h') {
            $h .= '<h4>' . esc_html($b[1]) . '</h4>';
        } else {
            $h .= '<p>' . petgo_ct_md($b[1]) . '</p>';
        }
    }
    return $h . '</div>';
}

function petgo_ct_pdf($d) {
    require_once __DIR__ . '/pdf.php';
    $p = new PetGo_PDF(array('title' => 'Договор оказания услуг № ' . $d['no'], 'author' => $d['ex']['name'], 'subject' => 'PetGo · ' . $d['name'], 'creator' => 'petgo.kz', 'time' => $d['ts']));
    $p->foot = 'Договор № ' . $d['no'] . ' · ' . $d['date'] . ' · petgo.kz';
    $p->page();
    $green = array(2, 76, 70);
    $mu    = array(110, 122, 119);
    foreach (petgo_ct_blocks($d) as $b) {
        if ($b[0] === 'title') {
            $p->para($b[1], array('size' => 19, 'lead' => 24, 'font' => 'sb', 'rgb' => $green, 'after' => 2));
        } elseif ($b[0] === 'sub') {
            $p->para($b[1], array('size' => 10.5, 'lead' => 15, 'rgb' => $mu, 'after' => 0));
        } elseif ($b[0] === 'meta') {
            $p->para($b[1], array('size' => 10.5, 'lead' => 15, 'rgb' => $mu, 'after' => 12));
        } elseif ($b[0] === 'h') {
            $p->gap(8);
            $p->para($b[1], array('size' => 12, 'lead' => 17, 'font' => 'sb', 'rgb' => $green, 'after' => 3, 'keep' => 30));
        } else {
            $p->para($b[1], array('size' => 9.6, 'lead' => 13.8, 'after' => 4.5));
        }
    }
    return $p->output();
}

/* ============================================================
   ХРАНЕНИЕ: uploads/petgo-docs/<клиент-код>/ — закрыто от прямого доступа, отдаётся только владельцу и сотрудникам
   ============================================================ */
function petgo_ct_dir($uid) {
    $up   = wp_upload_dir();
    $root = trailingslashit($up['basedir']) . 'petgo-docs';
    if (!is_dir($root)) {
        wp_mkdir_p($root);
    }
    if (!file_exists($root . '/.htaccess')) {
        @file_put_contents($root . '/.htaccess', "Require all denied\nDeny from all\n");
        @file_put_contents($root . '/index.php', "<?php\n");
        @file_put_contents($root . '/web.config', '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>');
    }
    $sub = (int) $uid . '-' . substr(hash_hmac('sha256', 'ct' . (int) $uid, wp_salt('auth')), 0, 20);
    $dir = $root . '/' . $sub;
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
        @file_put_contents($dir . '/index.php', "<?php\n");
    }
    return array($dir, 'petgo-docs/' . $sub);
}

function petgo_ct_path($rel) {
    $up = wp_upload_dir();
    $rel = ltrim(str_replace(array('..', '\\'), '', (string) $rel), '/');
    return $rel === '' ? '' : trailingslashit($up['basedir']) . $rel;
}

function petgo_ct_file_url($uid, $f) {
    return add_query_arg(array('action' => 'petgo_ct_file', 'u' => (int) $uid, 'f' => $f, 'n' => wp_create_nonce('petgo_ct_file')), admin_url('admin-ajax.php'));
}

/* скачать договор (PDF) или подпись (.cms): сам клиент или сотрудник офиса */
add_action('wp_ajax_petgo_ct_file', 'petgo_ct_download');
function petgo_ct_download() {
    $me  = get_current_user_id();
    $uid = isset($_GET['u']) ? (int) $_GET['u'] : 0;
    $f   = isset($_GET['f']) ? sanitize_key(wp_unslash($_GET['f'])) : 'pdf';
    /* 40.5: «Скачать» в листе договора — подписанный договор, а если ещё не подписан — проект с данными клиента */
    if ($f === 'auto') {
        if (!$me || !wp_verify_nonce(isset($_GET['n']) ? sanitize_text_field(wp_unslash($_GET['n'])) : '', 'petgo_ct_file') || ($uid !== $me && !(function_exists('petgo_can') && petgo_can('office')))) {
            status_header(403);
            exit('403');
        }
        $r = petgo_ct_get($uid);
        if ($r && !empty($r['pdf']) && ($p = petgo_ct_path($r['pdf'])) && is_file($p)) {
            petgo_ct_send(file_get_contents($p), 'dogovor-petgo-' . preg_replace('/[^A-Za-z0-9-]/', '-', $r['no']) . '.pdf', 'application/pdf');
        }
        $u = get_userdata($uid);
        $d = petgo_ct_data($uid, ($u && !get_user_meta($uid, 'petgo_noname', true)) ? $u->display_name : '', false, false, 'egov');
        petgo_ct_send(petgo_ct_pdf($d), 'dogovor-petgo-proekt.pdf', 'application/pdf');
    }
    if (!$me || !wp_verify_nonce(isset($_GET['n']) ? sanitize_text_field(wp_unslash($_GET['n'])) : '', 'petgo_ct_file')) {
        status_header(403);
        exit('403');
    }
    $staff = function_exists('petgo_can') && petgo_can('office');
    /* образец для настройки — только администратор */
    if ($f === 'sample' && function_exists('petgo_can') && petgo_can('admin')) {
        $d = petgo_ct_data($me, 'Иванова Айгерим', true, true, 'egov');
        $d['phone'] = '+7 700 000 00 00';
        petgo_ct_send(petgo_ct_pdf($d), 'dogovor-obrazec.pdf', 'application/pdf');
    }
    if ($uid !== $me && !$staff) {
        status_header(403);
        exit('403');
    }
    $r = petgo_ct_get($uid);
    if (!$r || !in_array($f, array('pdf', 'cms'), true) || empty($r[$f])) {
        status_header(404);
        exit('404');
    }
    $path = petgo_ct_path($r[$f]);
    if (!$path || !is_file($path)) {
        status_header(404);
        exit('404');
    }
    $name = 'dogovor-petgo-' . preg_replace('/[^A-Za-z0-9-]/', '-', $r['no']) . '.' . $f;
    petgo_ct_send(file_get_contents($path), $name, $f === 'pdf' ? 'application/pdf' : 'application/pkcs7-signature');
}

function petgo_ct_send($data, $name, $type) {
    nocache_headers();
    header('Content-Type: ' . $type);
    header('Content-Disposition: ' . ($type === 'application/pdf' ? 'inline' : 'attachment') . '; filename="' . $name . '"');
    header('Content-Length: ' . strlen($data));
    header('X-Content-Type-Options: nosniff');
    echo $data; // phpcs:ignore
    exit;
}

/* ============================================================
   ПОДПИСЬ ИЗ eGov (CMS): разбор без сторонних библиотек.
   Проверяем: подписан именно наш PDF (он лежит внутри подписи), сертификат выдан НУЦ РК и действует;
   берём из сертификата ФИО и ИИН. RSA-подпись дополнительно проверяется криптографически (OpenSSL);
   ГОСТ-подпись хранится как есть и проверяется в сервисе проверки ЭЦП (НУЦ РК, sigex.kz).
   ============================================================ */
function petgo_asn1($s, &$off, $end, $depth = 0) {
    if ($depth > 48 || $off + 2 > $end) {
        throw new Exception('asn1');
    }
    $start = $off;
    $tag   = ord($s[$off++]);
    if (($tag & 0x1f) === 0x1f) {
        while ($off < $end && (ord($s[$off]) & 0x80)) {
            $off++;
        }
        $off++;
    }
    if ($off >= $end) {
        throw new Exception('asn1');
    }
    $l     = ord($s[$off++]);
    $indef = false;
    $len   = 0;
    if ($l === 0x80) {
        $indef = true;
    } elseif ($l & 0x80) {
        $n = $l & 0x7f;
        if ($n < 1 || $n > 4 || $off + $n > $end) {
            throw new Exception('asn1');
        }
        for ($i = 0; $i < $n; $i++) {
            $len = ($len << 8) | ord($s[$off++]);
        }
    } else {
        $len = $l;
    }
    $cons = (bool) ($tag & 0x20);
    $node = array('tag' => $tag, 'cons' => $cons, 'kids' => array(), 'val' => '');
    if ($indef) {
        if (!$cons) {
            throw new Exception('asn1');
        }
        while (true) {
            if ($off + 2 > $end) {
                throw new Exception('asn1');
            }
            if ($s[$off] === "\x00" && $s[$off + 1] === "\x00") {
                $off += 2;
                break;
            }
            $node['kids'][] = petgo_asn1($s, $off, $end, $depth + 1);
        }
    } else {
        $ce = $off + $len;
        if ($ce > $end) {
            throw new Exception('asn1');
        }
        if ($cons) {
            while ($off < $ce) {
                $node['kids'][] = petgo_asn1($s, $off, $ce, $depth + 1);
            }
        } else {
            $node['val'] = substr($s, $off, $len);
        }
        $off = $ce;
    }
    $node['raw'] = substr($s, $start, $off - $start);
    return $node;
}

function petgo_asn1_oid($v) {
    if ($v === '') {
        return '';
    }
    $b   = array_values(unpack('C*', $v));
    $out = array(intdiv($b[0], 40), $b[0] % 40);
    $n   = 0;
    for ($i = 1; $i < count($b); $i++) {
        $n = ($n << 7) | ($b[$i] & 0x7f);
        if (!($b[$i] & 0x80)) {
            $out[] = $n;
            $n     = 0;
        }
    }
    return implode('.', $out);
}

function petgo_asn1_str($n) {
    if ($n['tag'] === 0x1e) {
        return mb_convert_encoding($n['val'], 'UTF-8', 'UTF-16BE');
    }
    return (string) $n['val'];
}

/* имя из сертификата: OID → значение */
function petgo_asn1_name($n) {
    $out = array();
    foreach ($n['kids'] as $set) {
        foreach ($set['kids'] as $atv) {
            if (count($atv['kids']) >= 2) {
                $out[petgo_asn1_oid($atv['kids'][0]['val'])] = petgo_asn1_str($atv['kids'][1]);
            }
        }
    }
    return $out;
}

function petgo_asn1_time($n) {
    $v = $n['val'];
    if ($n['tag'] === 0x17 && preg_match('/^(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})?Z$/', $v, $m)) {
        $y = (int) $m[1];
        return gmmktime((int) $m[4], (int) $m[5], (int) ($m[6] ?? 0), (int) $m[2], (int) $m[3], $y < 50 ? 2000 + $y : 1900 + $y);
    }
    if ($n['tag'] === 0x18 && preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $v, $m)) {
        return gmmktime((int) $m[4], (int) $m[5], (int) $m[6], (int) $m[2], (int) $m[3], (int) $m[1]);
    }
    return 0;
}

function petgo_ct_cert($der) {
    $o    = 0;
    $c    = petgo_asn1($der, $o, strlen($der));
    $tbs  = $c['kids'][0]['kids'];
    $i    = ($tbs[0]['tag'] === 0xa0) ? 1 : 0;
    $spki = $tbs[$i + 5];
    return array(
        'serial' => bin2hex($tbs[$i]['val']),
        'issuer' => petgo_asn1_name($tbs[$i + 2]),
        'from' => petgo_asn1_time($tbs[$i + 3]['kids'][0]), 'to' => petgo_asn1_time($tbs[$i + 3]['kids'][1]),
        'subject' => petgo_asn1_name($tbs[$i + 4]),
        'alg' => petgo_asn1_oid($spki['kids'][0]['kids'][0]['val']),
        'der' => $der,
    );
}

/* разбор CMS: подписанное содержимое, сертификат подписавшего, время подписи */
function petgo_ct_cms($der) {
    $o  = 0;
    $ci = petgo_asn1($der, $o, strlen($der));
    if (petgo_asn1_oid($ci['kids'][0]['val']) !== '1.2.840.113549.1.7.2') {
        throw new Exception('notcms');
    }
    $sd   = $ci['kids'][1]['kids'][0]['kids'];
    $eci  = $sd[2];
    $data = null;
    if (isset($eci['kids'][1])) {
        $oc   = $eci['kids'][1]['kids'][0];
        $data = $oc['cons'] ? implode('', array_map(function ($k) {
            return $k['val'];
        }, $oc['kids'])) : $oc['val'];
    }
    $certs = array();
    $si    = null;
    for ($i = 3; $i < count($sd); $i++) {
        if ($sd[$i]['tag'] === 0xa0) {
            foreach ($sd[$i]['kids'] as $k) {
                if ($k['tag'] === 0x30) {
                    $certs[] = petgo_ct_cert($k['raw']);
                }
            }
        } elseif ($sd[$i]['tag'] === 0x31 && isset($sd[$i]['kids'][0])) {
            $si = $sd[$i]['kids'][0]['kids'];
        }
    }
    $serial = '';
    $stime  = 0;
    if ($si) {
        if ($si[1]['tag'] === 0x30 && isset($si[1]['kids'][1])) {
            $serial = bin2hex($si[1]['kids'][1]['val']);
        }
        foreach ($si as $k) {
            if ($k['tag'] === 0xa0) {
                foreach ($k['kids'] as $attr) {
                    if (petgo_asn1_oid($attr['kids'][0]['val']) === '1.2.840.113549.1.9.5' && isset($attr['kids'][1]['kids'][0])) {
                        $stime = petgo_asn1_time($attr['kids'][1]['kids'][0]);
                    }
                }
            }
        }
    }
    $signer = null;
    foreach ($certs as $c) {
        if ($serial !== '' && ltrim($c['serial'], '0') === ltrim($serial, '0')) {
            $signer = $c;
        }
    }
    if (!$signer) {
        foreach ($certs as $c) {
            if (isset($c['subject']['2.5.4.5']) && stripos($c['subject']['2.5.4.5'], 'IIN') === 0) {
                $signer = $c;
            }
        }
    }
    return array('data' => $data, 'signer' => $signer, 'time' => $stime);
}

/* сертификат выдал Национальный удостоверяющий центр РК */
function petgo_ct_nuc($issuer) {
    $cn = isset($issuer['2.5.4.3']) ? $issuer['2.5.4.3'] : '';
    $ok = (bool) preg_match('/ҰЛТТЫҚ\s+КУӘЛАНДЫРУШЫ\s+ОРТАЛЫҚ|НАЦИОНАЛЬНЫЙ\s+УДОСТОВЕРЯЮЩИЙ\s+ЦЕНТР|NATIONAL\s+CERTIFICATION\s+AUTHORITY/iu', $cn);
    return (bool) apply_filters('petgo_ct_issuer_ok', $ok, $issuer);
}

function petgo_ct_title($s) {
    return mb_convert_case(mb_strtolower(trim((string) $s), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

/* ============================================================
   ДЕЙСТВИЯ КАБИНЕТА
   ============================================================ */
add_action('petgo_cl_op', 'petgo_ct_ops', 10, 2);
function petgo_ct_ops($op, $uid) {
    if (strpos($op, 'ct_') !== 0) {
        return;
    }
    $L = 'petgo_site_l';
    if (!petgo_ct_ready()) {
        petgo_cl_json(false, array('msg' => $L('Договор пока недоступен. Напишите менеджеру.')));
    }
    if (!petgo_cl_ip_limit('ct' . $uid, 90, HOUR_IN_SECONDS)) {
        petgo_cl_json(false, array('msg' => $L('Слишком много попыток. Попробуйте позже.')));
    }
    $name = trim(preg_replace('/\s+/u', ' ', sanitize_text_field((string) petgo_p('name'))));
    $vet  = petgo_p('vet') === '1';
    $ph   = petgo_p('photo') === '1';
    $need = function () use ($name, $L) {
        if (mb_strlen($name) < 5 || mb_strlen($name) > 80 || strpos($name, ' ') === false || preg_match('/\d/', $name)) {
            petgo_cl_json(false, array('msg' => $L('Укажите фамилию и имя, как в документе.'), 'field' => 'name'));
        }
    };

    /* eGov: готовим PDF, который подпишет клиент */
    if ($op === 'ct_doc') {
        $need();
        $d   = petgo_ct_data($uid, $name, $vet, $ph, 'egov');
        $pdf = petgo_ct_pdf($d);
        list($dir, $rel) = petgo_ct_dir($uid);
        file_put_contents($dir . '/draft.pdf', $pdf);
        update_user_meta($uid, 'petgo_ct_draft', array('d' => $d, 'rel' => $rel . '/draft.pdf', 'hash' => hash('sha256', $pdf), 't' => time()));
        petgo_cl_json(true, array(
            'b64' => base64_encode($pdf), 'sigex' => petgo_ct_sigex(), 'no' => $d['no'],
            'title' => array('ru' => 'Договор оказания услуг PetGo № ' . $d['no'], 'kk' => 'PetGo қызмет көрсету шарты № ' . $d['no'], 'en' => 'PetGo service agreement No. ' . $d['no']),
            'meta' => array(array('name' => 'Заказчик', 'value' => $name), array('name' => 'Телефон', 'value' => $d['phone']), array('name' => 'Дата', 'value' => $d['date'])),
        ));
    }

    /* eGov: пришла подпись — проверяем и сохраняем */
    if ($op === 'ct_egov') {
        $dr  = get_user_meta($uid, 'petgo_ct_draft', true);
        $b64 = preg_replace('/\s+/', '', (string) petgo_p('cms'));
        if (!is_array($dr) || empty($dr['rel']) || time() - (int) $dr['t'] > 3 * HOUR_IN_SECONDS) {
            petgo_cl_json(false, array('msg' => $L('Время подписи вышло. Нажмите «Подписать через eGov» ещё раз.')));
        }
        $der = base64_decode($b64, true);
        if ($der === false || strlen($der) < 200 || strlen($der) > 6 * 1024 * 1024) {
            petgo_cl_json(false, array('msg' => $L('Подпись не получена. Попробуйте ещё раз.')));
        }
        $pdf = (string) @file_get_contents(petgo_ct_path($dr['rel']));
        try {
            $c = petgo_ct_cms($der);
        } catch (Exception $e) {
            petgo_cl_json(false, array('msg' => $L('Не удалось прочитать подпись. Попробуйте ещё раз.')));
        }
        if ($pdf === '' || $c['data'] === null || !hash_equals(hash('sha256', $pdf), hash('sha256', (string) $c['data']))) {
            petgo_cl_json(false, array('msg' => $L('Подписан другой документ. Нажмите «Подписать через eGov» ещё раз.')));
        }
        $s = $c['signer'];
        if (!$s || !petgo_ct_nuc($s['issuer'])) {
            petgo_cl_json(false, array('msg' => $L('Подпись не от Национального удостоверяющего центра РК.')));
        }
        $at = $c['time'] ? $c['time'] : time();
        if (($s['from'] && $at < $s['from'] - 300) || ($s['to'] && $at > $s['to'] + 300)) {
            petgo_cl_json(false, array('msg' => $L('Срок действия ЭЦП истёк. Обновите ЭЦП в eGov mobile.')));
        }
        $iin = isset($s['subject']['2.5.4.5']) ? preg_replace('/\D/', '', $s['subject']['2.5.4.5']) : '';
        $fio = petgo_ct_title(trim((isset($s['subject']['2.5.4.3']) ? $s['subject']['2.5.4.3'] : '') . ' ' . (isset($s['subject']['2.5.4.42']) ? $s['subject']['2.5.4.42'] : '')));
        /* RSA — проверяем подпись сами; ГОСТ — хранится как есть, проверяется сервисом ЭЦП */
        $chk = 'gost';
        if ($s['alg'] === '1.2.840.113549.1.1.1' && function_exists('openssl_cms_verify')) {
            $tmp = wp_tempnam('pgct');
            file_put_contents($tmp, $der);
            $chk = @openssl_cms_verify($tmp, OPENSSL_CMS_NOVERIFY | OPENSSL_CMS_BINARY, null, array(), null, null, null, null, OPENSSL_ENCODING_DER) ? 'rsa' : 'bad';
            @unlink($tmp);
            if ($chk === 'bad') {
                petgo_cl_json(false, array('msg' => $L('Подпись не прошла проверку. Попробуйте ещё раз.')));
            }
        }
        $d = $dr['d'];
        list($dir, $rel) = petgo_ct_dir($uid);
        $base = 'dogovor-' . preg_replace('/[^A-Za-z0-9-]/', '-', $d['no']) . '-' . substr(hash('sha256', $pdf), 0, 8);
        file_put_contents($dir . '/' . $base . '.pdf', $pdf);
        file_put_contents($dir . '/' . $base . '.cms', $der);
        @unlink($dir . '/draft.pdf');
        delete_user_meta($uid, 'petgo_ct_draft');
        $rec = array(
            'st' => 'egov', 'at' => time(), 'no' => $d['no'], 'date' => $d['date'], 'name' => $d['name'], 'phone' => $d['phone'], 'vet' => $d['vet'], 'photo' => $d['photo'], 'ver' => $d['ver'],
            'hash' => hash('sha256', $pdf), 'pdf' => $rel . '/' . $base . '.pdf', 'cms' => $rel . '/' . $base . '.cms', 'ip' => petgo_ip(), 'ua' => mb_substr(sanitize_text_field(isset($_SERVER['HTTP_USER_AGENT']) ? wp_unslash($_SERVER['HTTP_USER_AGENT']) : ''), 0, 200),
            'cert' => array('fio' => $fio, 'iin' => $iin, 'serial' => $s['serial'], 'issuer' => isset($s['issuer']['2.5.4.3']) ? $s['issuer']['2.5.4.3'] : '', 'from' => $s['from'], 'to' => $s['to'], 'alg' => $s['alg']),
            'stime' => $c['time'], 'chk' => $chk,
        );
        petgo_ct_save($uid, $rec);
        petgo_cl_json(true, array('h' => petgo_ct_done_html($uid, $rec, true), 'st' => 'egov'));
    }

    /* без eGov: галочка «согласен» */
    if ($op === 'ct_click') {
        $need();
        if (petgo_p('agree') !== '1') {
            petgo_cl_json(false, array('msg' => $L('Отметьте, что прочитали договор и согласны.'), 'field' => 'agree'));
        }
        $old = petgo_ct_get($uid);
        if ($old && $old['st'] === 'egov') {
            petgo_cl_json(true, array('h' => petgo_ct_done_html($uid, $old), 'st' => 'egov'));
        }
        $d        = petgo_ct_data($uid, $name, $vet, $ph, 'click');
        $d['acc'] = array('t' => time(), 'ip' => petgo_ip());
        $pdf      = petgo_ct_pdf($d);
        list($dir, $rel) = petgo_ct_dir($uid);
        $base = 'dogovor-' . preg_replace('/[^A-Za-z0-9-]/', '-', $d['no']) . '-' . substr(hash('sha256', $pdf), 0, 8);
        file_put_contents($dir . '/' . $base . '.pdf', $pdf);
        $rec = array(
            'st' => 'click', 'at' => time(), 'no' => $d['no'], 'date' => $d['date'], 'name' => $d['name'], 'phone' => $d['phone'], 'vet' => $d['vet'], 'photo' => $d['photo'], 'ver' => $d['ver'],
            'hash' => hash('sha256', $pdf), 'pdf' => $rel . '/' . $base . '.pdf', 'cms' => '', 'ip' => petgo_ip(), 'ua' => mb_substr(sanitize_text_field(isset($_SERVER['HTTP_USER_AGENT']) ? wp_unslash($_SERVER['HTTP_USER_AGENT']) : ''), 0, 200),
        );
        petgo_ct_save($uid, $rec);
        petgo_cl_json(true, array('h' => petgo_ct_done_html($uid, $rec, true), 'st' => 'click'));
    }
}

function petgo_ct_save($uid, $rec) {
    $old = petgo_ct_get($uid);
    if ($old) {
        $hist   = (array) get_user_meta($uid, 'petgo_ct_hist', true);
        $hist[] = $old;
        update_user_meta($uid, 'petgo_ct_hist', array_slice(array_filter($hist), -10));
    }
    update_user_meta($uid, 'petgo_ct', $rec);
    if (function_exists('petgo_audit')) {
        $u = get_userdata($uid);
        petgo_audit('contract_' . $rec['st'], $u ? $u->user_login : (string) $uid, $uid);
    }
    if (function_exists('petgo_event')) {
        petgo_event('contract_signed', array('user' => (int) $uid, 'how' => $rec['st'], 'no' => $rec['no']));
    }
}

/* ============================================================
   ВИД В КАБИНЕТЕ
   ============================================================ */
function petgo_ct_icon($n) {
    $p = array(
        'doc' => '<path d="M7 3.5h7l4.5 4.5v11A1.5 1.5 0 0 1 17 20.5H7A1.5 1.5 0 0 1 5.5 19V5A1.5 1.5 0 0 1 7 3.5z"/><path d="M13.5 3.5V8h4.5M8.5 12.5h7M8.5 16h5"/>',
        'down' => '<path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 19.5h14"/>',
        'sign' => '<path d="M4 18.5c2.5 0 3.5-2 4.5-4.5s1.6-5.5 3-5.5c1.6 0 .4 4.4 1.8 4.4 1.1 0 1.6-2.4 2.9-2.4 1 0 1.1 1.6 2.1 1.6.6 0 1.1-.4 1.7-1.1"/><path d="M4 21h16"/>',
        'shield' => '<path d="M12 3.5 5.5 6v5.5c0 4.2 2.8 7.6 6.5 9 3.7-1.4 6.5-4.8 6.5-9V6L12 3.5z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
    );
    return '<svg class="pg-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . (isset($p[$n]) ? $p[$n] : '') . '</svg>';
}

function petgo_ct_ok_svg() {
    return '<svg class="pgk-ok-svg" viewBox="0 0 80 80" aria-hidden="true"><circle cx="40" cy="40" r="36"/><path d="M24 41.5l11 11L57 29"/></svg>';
}

/* договор подписан: галочка, кто и как подписал, скачать */
function petgo_ct_done_html($uid, $r, $live = false) {
    $L  = 'petgo_site_l';
    $eg = $r['st'] === 'egov';
    $h  = '<div class="pgk-ct-done" data-k-ct-done><div class="pgk-okv' . ($live ? ' in' : '') . '" role="status">' . petgo_ct_ok_svg() . '<b>' . esc_html($L('Договор подписан')) . '</b><p>' . esc_html($eg ? $L('Через eGov — электронной цифровой подписью') : $L('В кабинете, без eGov')) . '</p></div>';
    $h .= '<div class="pgk-list pgk-ct-info">';
    $row = function ($k, $v) {
        return '<div class="pgk-row pgk-ct-r"><span class="pgk-row-t"><small>' . esc_html($k) . '</small><b>' . esc_html($v) . '</b></span></div>';
    };
    $h .= $row($L('Заказчик'), $eg && !empty($r['cert']['fio']) ? $r['cert']['fio'] : $r['name']);
    if ($eg && !empty($r['cert']['iin'])) {
        $h .= $row($L('ИИН'), petgo_ct_iin_mask($r['cert']['iin']));
    }
    $h .= $row($L('Подписан'), wp_date('d.m.Y · H:i', (int) $r['at'], petgo_tz()));
    $h .= $row($L('Номер договора'), $r['no']);
    $h .= '</div><div class="pgk-ct-dl"><a class="pg-b pgk-btn pgk-btn-p" data-ext target="_blank" rel="noopener" href="' . esc_url(petgo_ct_file_url($uid, 'pdf')) . '">' . petgo_ct_icon('down') . esc_html($L('Скачать договор (PDF)')) . '</a>';
    if ($eg && !empty($r['cms'])) {
        $h .= '<a class="pg-b pgk-btn pgk-btn-s" data-ext href="' . esc_url(petgo_ct_file_url($uid, 'cms')) . '" download>' . petgo_ct_icon('shield') . esc_html($L('Файл подписи (.cms)')) . '</a>';
    }
    $h .= '</div>';
    if (!$eg) {
        $h .= '<button type="button" class="pg-b pgk-link pgk-ct-up" data-k-ct-redo>' . esc_html($L('Подписать ещё и через eGov')) . '</button>';
    }
    return $h . '</div>';
}

/* лист «Договор»: ваши данные, текст, две кнопки */
function petgo_ct_sheet_html($uid) {
    $L   = 'petgo_site_l';
    $u   = get_userdata($uid);
    $r   = petgo_ct_get($uid);
    $d   = petgo_ct_data($uid, $r ? $r['name'] : (($u && !get_user_meta($uid, 'petgo_noname', true)) ? $u->display_name : ''), $r ? $r['vet'] === '1' : false, $r ? $r['photo'] === '1' : false, 'egov');
    $ck  = '<i aria-hidden="true">' . petgo_cl_icon('check') . '</i>';
    $h   = '<div class="pgk-ct" data-k-ctroot data-st="' . esc_attr($r ? $r['st'] : 'none') . '">';
    if ($r) {
        $h .= petgo_ct_done_html($uid, $r);
    }
    $h .= '<form class="pgk-ct-f" data-k-ctform novalidate' . ($r ? ' hidden' : '') . '>';
    $h .= '<div class="pgk-ct-hd"><span class="pgk-ct-ic">' . petgo_ct_icon('doc') . '</span><div><b>' . esc_html($L('Договор оказания услуг')) . '</b><small>' . esc_html($L('Один раз — для всех прогулок и визитов')) . '</small></div></div>';
    $h .= '<div class="pgk-list">' . petgo_cl_field('name', $L('Фамилия и имя'), 'text', $d['name'], ' maxlength="80" autocomplete="name" autocapitalize="words" required');
    $h .= '<div class="pgk-row pgk-ct-r"><span class="pgk-row-t"><small>' . esc_html($L('Телефон')) . '</small><b>' . esc_html($d['phone'] !== '' ? $d['phone'] : '—') . '</b></span></div>';
    $h .= '<div class="pgk-row pgk-ct-r"><span class="pgk-row-t"><small>' . esc_html($L('Дата')) . '</small><b>' . esc_html($d['date']) . '</b></span></div></div>';
    $h .= '<h4 class="pgk-gt">' . esc_html($L('Ваши решения')) . '</h4><div class="pgk-list pgk-ct-opts">';
    $h .= '<label class="pgk-check pgk-row"><input type="checkbox" name="vet" value="1"' . checked($d['vet'], '1', false) . '>' . $ck . '<span class="pgk-row-t"><b>' . esc_html($L('Срочно к ветеринару')) . '</b><small>' . esc_html($L('Если со мной не связаться, няня может отвезти питомца к ветеринару за неотложной помощью')) . '</small></span></label>';
    $h .= '<label class="pgk-check pgk-row"><input type="checkbox" name="photo" value="1"' . checked($d['photo'], '1', false) . '>' . $ck . '<span class="pgk-row-t"><b>' . esc_html($L('Фото питомца в соцсетях')) . '</b><small>' . esc_html($L('Можно публиковать в @petgo.kz — без адреса и лиц людей')) . '</small></span></label></div>';
    $h .= '<details class="pgk-ct-txt"><summary>' . petgo_ct_icon('doc') . '<span>' . esc_html($L('Текст договора')) . '<small>' . esc_html($L('12 разделов · можно прочитать здесь')) . '</small></span>' . petgo_cl_icon('right', 'pg-i pgk-chev') . '</summary>' . petgo_ct_html($d) . '</details>';
    $h .= '<div class="pgk-ct-bar"><p class="pgk-err" role="alert" hidden></p>';
    $h .= '<button type="button" class="pg-b pgk-btn pgk-btn-p pgk-go pgk-ct-egov" data-k-ct-egov><span>' . petgo_ct_icon('sign') . esc_html($L('Подписать через eGov')) . '</span><i class="pg-spin" aria-hidden="true"></i></button>';
    $h .= '<p class="pgk-ct-hint">' . esc_html($L('Откроется eGov mobile. Электронная подпись имеет такую же силу, как подпись на бумаге.')) . '</p>';
    $h .= '<div class="pgk-ct-or"><span>' . esc_html($L('или')) . '</span></div>';
    $h .= '<label class="pgk-check pgk-ct-agree"><input type="checkbox" name="agree" value="1">' . $ck . '<span>' . esc_html($L('Я прочитал(а) договор и согласен(на) с его условиями')) . '</span></label>';
    $h .= '<button type="button" class="pg-b pgk-btn pgk-btn-s pgk-go" data-k-ct-click><span>' . esc_html($L('Подписать без eGov')) . '</span><i class="pg-spin" aria-hidden="true"></i></button>';
    $h .= '<p class="pgk-ct-hint">' . esc_html($L('Без eGov договор принимается галочкой в кабинете: мы запишем дату, время и ваш номер.')) . '</p></div></form>';
    /* ожидание подписи в eGov (рисует скрипт) */
    $h .= '<div class="pgk-ct-wait" data-k-ct-wait hidden></div>';
    return $h . '</div>';
}

/* «Для вас» на главной: договор ещё не подписан */
add_filter('petgo_cl_home_first', function ($h, $uid) {
    if (!petgo_ct_ready() || petgo_ct_get($uid)) {
        return $h;
    }
    $L = 'petgo_site_l';
    return '<div class="pgk-card pgk-ctcard" data-k-ctcard><span class="pgk-ct-ic">' . petgo_ct_icon('doc') . '</span><div class="pgk-ctcard-t"><b>' . esc_html($L('Договор с PetGo')) . '</b><p>' . esc_html($L('Подпишите один раз — через eGov или прямо здесь. Займёт минуту.')) . '</p></div><button type="button" class="pg-b pgk-btn pgk-btn-p pgk-btn-sm" data-k-ct>' . esc_html($L('Открыть договор')) . '</button></div>' . $h;
}, 5, 2);

/* «Аккаунт»: строка «Договор» */
add_filter('petgo_cl_account_html', function ($h, $uid) {
    if (!petgo_ct_ready() && !petgo_ct_get($uid)) {
        return $h;
    }
    $L = 'petgo_site_l';
    $r = petgo_ct_get($uid);
    $s = $r ? ($r['st'] === 'egov' ? $L('Подписан через eGov') : $L('Подписан в кабинете')) . ' · ' . wp_date('d.m.Y', (int) $r['at'], petgo_tz()) : $L('Не подписан');
    return $h . '<h4 class="pgk-gt">' . esc_html($L('Документы')) . '</h4><div class="pgk-list"><button type="button" class="pg-b pgk-row" data-k-ct><span class="pgk-row-i">' . petgo_ct_icon('doc') . '</span><span class="pgk-row-t"><b>' . esc_html($L('Договор оказания услуг')) . '</b><small class="pgk-ct-st' . ($r ? ' ok' : '') . '" data-k-ct-st>' . esc_html($s) . '</small></span>' . petgo_cl_icon('right', 'pg-i pgk-chev') . '</button></div>';
}, 10, 2);

/* лист и данные для скрипта */
add_filter('petgo_cl_sheets', function ($h, $uid) {
    if (!petgo_ct_ready() && !petgo_ct_get($uid)) {
        return $h;
    }
    /* 40.5: вверху слева — стрелка «назад», справа — «Скачать» (подписанный договор или проект с вашими данными) */
    $sh = petgo_cl_sheet('ct', petgo_site_l('Договор'), petgo_ct_sheet_html($uid), '', 'pgk-sheet-tall pgk-sheet-ct');
    $sh = preg_replace('#<button type="button" class="pg-b pgk-x" data-k-close[^>]*>.*?</button>#s', '<button type="button" class="pg-b pgk-ct-hdl" data-k-ct-dl="' . esc_url(petgo_ct_file_url($uid, 'auto')) . '" aria-label="' . esc_attr(petgo_site_l('Скачать договор')) . '"><svg class="pg-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg><span>' . esc_html(petgo_site_l('Скачать')) . '</span></button>', $sh, 1);
    $sh = str_replace('<i class="pgk-grab" aria-hidden="true"></i>', '<i class="pgk-grab" aria-hidden="true"></i><button type="button" class="pg-b pgk-ct-back" data-k-close aria-label="' . esc_attr(petgo_site_l('Назад')) . '">' . petgo_cl_ic('back') . '</button>', $sh);
    return $h . $sh;
}, 10, 2);
add_filter('petgo_cl_cfg', function ($cfg, $uid) {
    $r         = petgo_ct_get($uid);
    $cfg['ct'] = array('on' => petgo_ct_ready() ? 1 : 0, 'st' => $r ? $r['st'] : '', 'sigex' => petgo_ct_sigex());
    return $cfg;
}, 10, 2);

/* ============================================================
   ПРИЛОЖЕНИЕ СОТРУДНИКОВ: настройки, клиенты, заказ
   ============================================================ */
add_filter('petgo_settings_rows', function ($rows) {
    if (function_exists('petgo_can') && petgo_can('admin')) {
        $rows[] = array('icon' => 'reviews', 'title' => 'Договор с клиентами', 'sub' => petgo_ct_ready() ? 'Подписание через eGov и в кабинете' : 'Заполните реквизиты исполнителя', 'url' => petgo_page_url(array('pg' => 'contract')));
    }
    return $rows;
});
/* главная приложения: договор ещё не включён — видно сразу, одна кнопка до настроек */
add_filter('petgo_admin_home_top', function ($h) {
    if (petgo_ct_ready() || !function_exists('petgo_can') || !petgo_can('admin')) {
        return $h;
    }
    $c   = petgo_ct_cfg();
    $why = $c['on'] !== '1' ? 'Договор выключен в настройках.' : 'Не заполнены реквизиты исполнителя (ФИО, ИИН, адрес) — без них клиенты договор не видят.';
    return $h . '<a class="pgc-card pgct-warn" href="' . esc_url(petgo_page_url(array('pg' => 'contract'))) . '" data-close-nav><span class="pgct-wi">!</span><span class="pgct-wt"><b>Договор с клиентами не показывается</b><small>' . esc_html($why) . '</small></span><span class="pgc-btn sm">Заполнить</span></a>'
        . '<style>.pgct-warn{display:flex;align-items:center;gap:12px;margin:0 0 14px;padding:14px 16px;text-decoration:none;color:inherit;box-shadow:inset 0 0 0 1.5px var(--wn,#E8A33C)}.pgct-wi{flex:none;width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-weight:700;background:var(--wn,#E8A33C);color:#fff}.pgct-wt{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}.pgct-wt b{color:var(--tx)}.pgct-wt small{color:var(--mu);line-height:1.35}</style>';
});

add_filter('petgo_app_title', function ($t, $pg) {
    return $pg === 'contract' ? 'Договор' : $t;
}, 10, 2);
add_filter('petgo_app_page', function ($done, $pg) {
    if ($done || $pg !== 'contract') {
        return $done;
    }
    petgo_ct_page();
    return true;
}, 10, 2);

function petgo_ct_counts() {
    global $wpdb;
    $rows = $wpdb->get_col($wpdb->prepare("SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", 'petgo_ct'));
    $n    = array('egov' => 0, 'click' => 0);
    foreach ((array) $rows as $v) {
        $r = maybe_unserialize($v);
        if (is_array($r) && isset($r['st'], $n[$r['st']])) {
            $n[$r['st']]++;
        }
    }
    return $n;
}

function petgo_ct_page() {
    if (!petgo_can('admin')) {
        echo '<p class="mu">' . esc_html(petgo_t('err_perm')) . '</p>';
        return;
    }
    $c  = petgo_ct_cfg();
    $n  = petgo_ct_counts();
    $f  = function ($name, $label, $val, $ph = '', $attr = '') {
        return '<label class="pgf-row"><span>' . esc_html($label) . '</span><input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '" placeholder="' . esc_attr($ph) . '" autocomplete="off"' . $attr . '></label>';
    };
    echo '<style>.pgct .pgc-card{display:flex;flex-direction:column;gap:10px;margin-bottom:14px}.pgct .pgc-card>b{font-size:17px}.pgct .pgf-list{margin:0}.pgct ul{margin:0;padding-left:18px;color:var(--mu);font-size:14px;line-height:1.5}.pgct-n{display:flex;gap:10px}.pgct-n div{flex:1;padding:12px;border-radius:14px;background:var(--so)}.pgct-n b{display:block;font-size:24px}.pgct-n small{color:var(--mu)}.pgct-save{position:sticky;bottom:calc(var(--sab) + 12px);z-index:5}.pgct .pgf-row select{border:0;background:none;font:inherit;color:inherit;text-align:right}</style>';
    echo '<div class="pgct">';
    if (!petgo_ct_ready()) {
        echo '<div class="pgc-card pgct-warn" style="cursor:default;flex-direction:row;margin-bottom:14px"><span class="pgct-wi">!</span><span class="pgct-wt"><b>Клиенты пока не видят договор</b><small>Заполните ниже ФИО, ИИН и адрес, оставьте галочку «Предлагать договор клиентам» и нажмите «Сохранить». Договор сразу появится у клиентов в «Для вас».</small></span></div><style>.pgct-warn{display:flex;align-items:center;gap:12px;padding:14px 16px;box-shadow:inset 0 0 0 1.5px var(--wn,#E8A33C)}.pgct-wi{flex:none;width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-weight:700;background:var(--wn,#E8A33C);color:#fff}.pgct-wt{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}.pgct-wt small{color:var(--mu);line-height:1.35}</style>';
    }
    echo '<div class="pgc-card"><b>Подписано</b><div class="pgct-n"><div><b>' . (int) $n['egov'] . '</b><small>через eGov (ЭЦП)</small></div><div><b>' . (int) $n['click'] . '</b><small>в кабинете, без eGov</small></div></div>';
    echo '<p class="mu">Договор каждого клиента — в «Клиентах» и в окне заказа: PDF и файл подписи .cms.</p></div>';
    echo petgo_form_open('ct_save');
    echo '<div class="pgc-card"><b>Исполнитель</b><p class="mu">Эти данные попадают в договор. Пока они не заполнены, клиенты договор не видят.</p><div class="pgf-list">';
    echo $f('name', 'ФИО или ИП', $c['name'], 'Иванов Иван или ИП «…»', ' maxlength="120" required');
    echo '<label class="pgf-row"><span>Номер</span><select name="idt">';
    foreach (array('ИИН', 'БИН') as $o) {
        echo '<option' . selected($c['idt'], $o, false) . '>' . esc_html($o) . '</option>';
    }
    echo '</select></label>';
    echo $f('id', $c['idt'], $c['id'], '12 цифр', ' inputmode="numeric" maxlength="12" pattern="\d{12}" required');
    echo $f('addr', 'Адрес', $c['addr'], 'г. Алматы, ул. …', ' maxlength="200" required');
    echo $f('phone', 'Телефон', $c['phone'], 'необязательно', ' maxlength="30" inputmode="tel"');
    echo $f('city', 'Город в договоре', $c['city'], 'г. Алматы', ' maxlength="60"');
    echo '</div><label class="pgc-ck"><input type="checkbox" name="on" value="1"' . checked($c['on'], '1', false) . '> <span>Предлагать договор клиентам (на главной в «Для вас», после первого заказа и в «Аккаунте»)</span></label>';
    if (petgo_ct_ready()) {
        echo '<a class="pgc-btn sec" target="_blank" rel="noopener" data-ext href="' . esc_url(petgo_ct_file_url(0, 'sample')) . '">Образец договора (PDF)</a>';
    }
    echo '</div>';
    echo '<div class="pgc-card"><b>Как клиент подписывает</b><ul><li><b>Через eGov</b> — на телефоне открывается eGov mobile, на компьютере — QR-код для eGov mobile. Подпись ЭЦП равна подписи на бумаге. Подписание идёт через сервис SIGEX (sigex.kz).</li><li><b>Без eGov</b> — галочка «согласен» и кнопка. В договор записываются дата, время, телефон и IP.</li><li>Цены в договоре не указаны: стоимость — по прайсу на сайте на день заказа.</li></ul></div>';
    echo '<div class="pgct-save"><button type="submit" class="pgc-btn wide lg">Сохранить</button></div></form></div>';
}

add_action('admin_post_petgo_ct_save', 'petgo_h_ct_save');
function petgo_h_ct_save() {
    petgo_guard('admin');
    $in  = wp_unslash($_POST);
    $txt = function ($k, $max) use ($in) {
        return isset($in[$k]) && is_scalar($in[$k]) ? mb_substr(trim(sanitize_text_field((string) $in[$k])), 0, $max) : '';
    };
    $id = preg_replace('/\D/', '', $txt('id', 20));
    update_option('petgo_ct', array(
        'on' => !empty($in['on']) ? '1' : '0', 'name' => $txt('name', 120), 'idt' => in_array($txt('idt', 4), array('ИИН', 'БИН'), true) ? $txt('idt', 4) : 'ИИН',
        'id' => strlen($id) === 12 ? $id : '', 'addr' => $txt('addr', 200), 'phone' => $txt('phone', 30), 'city' => $txt('city', 60) !== '' ? $txt('city', 60) : 'г. Алматы',
    ), false);
    petgo_flash(strlen($id) === 12 ? 'Сохранено' : 'ИИН/БИН — 12 цифр', strlen($id) === 12);
    petgo_back('contract');
}

/* строка «Договор» для сотрудников: у клиента и в окне заказа */
function petgo_ct_staff_line($uid) {
    $r = petgo_ct_get($uid);
    if (!$r) {
        return '<span class="pgc-badge late">Договор не подписан</span>';
    }
    $adm = function_exists('petgo_can') && petgo_can('admin');
    $t   = ($r['st'] === 'egov' ? 'ЭЦП eGov' : 'в кабинете, без eGov') . ' · ' . wp_date('d.m.Y H:i', (int) $r['at'], petgo_tz());
    if ($r['st'] === 'egov' && !empty($r['cert']['fio'])) {
        $t .= ' · ' . $r['cert']['fio'] . (!empty($r['cert']['iin']) ? ', ИИН ' . ($adm ? $r['cert']['iin'] : petgo_ct_iin_mask($r['cert']['iin'])) : '');
    }
    $h = '<span class="pgc-badge ok">Договор подписан</span> <small class="mu">' . esc_html($t) . '</small> <a href="' . esc_url(petgo_ct_file_url($uid, 'pdf')) . '" target="_blank" rel="noopener" data-ext>PDF</a>';
    if (!empty($r['cms'])) {
        $h .= ' · <a href="' . esc_url(petgo_ct_file_url($uid, 'cms')) . '" data-ext download>.cms</a>';
    }
    return $h;
}

add_filter('petgo_order_detail_top', function ($h, $oid, $admin) {
    if (!$admin || !petgo_ct_ready()) {
        return $h;
    }
    $uid = (int) get_post_meta((int) petgo_o($oid, 'pet'), '_client', true);
    return $uid ? $h . '<div class="pgc-note pgct-line">' . petgo_ct_staff_line($uid) . '</div>' : $h;
}, 10, 3);

/* клиент удалил кабинет — договор остаётся у PetGo (файлы на месте, запись — в архиве договоров) */
add_action('delete_user', function ($uid) {
    $r = petgo_ct_get($uid);
    if (!$r) {
        return;
    }
    $u   = get_userdata($uid);
    $arc = get_option('petgo_ct_arch', array());
    $arc = is_array($arc) ? $arc : array();
    $arc[(int) $uid] = array_merge($r, array('login' => $u ? $u->user_login : '', 'deleted' => time()));
    update_option('petgo_ct_arch', array_slice($arc, -1000, null, true), false);
});
