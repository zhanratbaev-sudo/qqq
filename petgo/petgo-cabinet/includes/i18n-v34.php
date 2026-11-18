<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ПЕРЕВОДЫ 34.0
   petgo_dict_v34() — приложение сотрудников: русский, казахский, английский, китайский.
   petgo_site_dict_v34() — сайт и кабинет клиента: «русский текст» => [казахский, английский].
   Чего здесь нет, переводит «PetGo ИИ» в фоне (как и остальные свои тексты сайта).
   ============================================================ */
function petgo_dict_v34() {
    return array(
        'pf_added'      => array('Питомец добавлен в базу. Его можно изменить в «Питомцах»', 'Жануар базаға қосылды. Оны «Жануарлар» бөлімінде өзгертуге болады', 'Pet added. You can edit it in Pets', '宠物已添加，可在“宠物”中编辑'),
        'pf_s_pet'      => array('Знакомство', 'Танысу', 'Basics', '基本信息'),
        'pf_s_owner'    => array('Хозяин и доступ', 'Иесі және кіру', 'Owner and access', '主人与进入方式'),
        'pf_add_link'   => array('Отправить анкету клиенту', 'Клиентке сауалнама жіберу', 'Send the form to the client', '把问卷发给客户'),
        'pf_add_link_s' => array('Клиент заполнит сам по ссылке — питомец появится в базе автоматически', 'Клиент сілтеме арқылы өзі толтырады — жануар базада өзі пайда болады', 'The client fills it in via a link — the pet appears automatically', '客户通过链接自行填写，宠物自动加入数据库'),
        'pf_add_self'   => array('Заполнить анкету самому', 'Сауалнаманы өзім толтырамын', 'Fill in the form myself', '自己填写问卷'),
        'pf_add_self_s' => array('Если клиент рассказал всё по телефону', 'Клиент бәрін телефонмен айтса', 'If the client told you everything by phone', '客户已在电话里说明'),
        'pf_inv_ok'     => array('Ссылка готова', 'Сілтеме дайын', 'Link is ready', '链接已生成'),
        'pf_inv_opt'    => array('Оба поля необязательны. С телефоном ссылка сразу откроется в WhatsApp у этого номера.', 'Екі өріс те міндетті емес. Телефон болса, сілтеме бірден осы нөмірдің WhatsApp-ында ашылады.', 'Both fields are optional. With a phone, WhatsApp opens straight to that number.', '两项均可不填。填写电话后会直接打开该号码的 WhatsApp。'),
        'pf_send_wa'    => array('Отправить в WhatsApp', 'WhatsApp-қа жіберу', 'Send via WhatsApp', '通过 WhatsApp 发送'),
        'pf_copy'       => array('Скопировать', 'Көшіру', 'Copy', '复制'),
        'pf_copied'     => array('Скопировано', 'Көшірілді', 'Copied', '已复制'),
        'pf_share'      => array('Поделиться', 'Бөлісу', 'Share', '分享'),
        'pf_inv_t'      => array('Ссылка-анкета клиенту', 'Клиентке сауалнама сілтемесі', 'Pet form link', '宠物问卷链接'),
        'pf_inv_ready'  => array('Отправьте её клиенту. Когда он заполнит анкету, питомец сам появится в «Питомцах».', 'Оны клиентке жіберіңіз. Сауалнаманы толтырғанда жануар «Жануарларда» өзі пайда болады.', 'Send it to the client. Once they fill in the form, the pet appears in Pets automatically.', '发送给客户，填写后宠物会自动出现在“宠物”中。'),
        'pf_inv_hint'   => array('Клиент откроет ссылку, заполнит анкету карточками — и питомец сразу появится в базе со всеми данными и фото.', 'Клиент сілтемені ашып, сауалнаманы толтырады — жануар барлық деректерімен бірден базаға түседі.', 'The client opens the link and fills in the card-by-card form — the pet goes straight into the database with all details and a photo.', '客户打开链接逐步填写问卷，宠物将连同所有信息和照片直接加入数据库。'),
        'pf_inv_for'    => array('Для кого', 'Кімге', 'For', '给谁'),
        'pf_inv_for_ph' => array('Например, Айгерим, корги', 'Мысалы, Айгерім, корги', 'E.g. Aigerim, corgi', '例如：Aigerim，柯基'),
        'pf_inv_make'   => array('Создать ссылку', 'Сілтеме жасау', 'Create link', '生成链接'),
        'pf_inv_noname' => array('Ссылка-анкета', 'Сауалнама сілтемесі', 'Form link', '问卷链接'),
        'pf_inv_filled' => array('заполнена', 'толтырылды', 'filled in', '已填写'),
        'pf_inv_wait'   => array('ждёт клиента', 'клиентті күтуде', 'waiting for the client', '等待客户填写'),
        'pf_inv_list'   => array('Ссылки-анкеты', 'Сауалнама сілтемелері', 'Form links', '问卷链接'),
        'q_delete_inv'  => array('Удалить?', 'Жою керек пе?', 'Delete?', '删除？'),
        'cl_has_acc'    => array('есть кабинет', 'кабинеті бар', 'has account', '有账户'),
        'cl_nav'        => array('Клиенты', 'Клиенттер', 'Clients', '客户'),
        'cl_board'      => array('Для клиентов', 'Клиенттерге', 'For clients', '客户公告'),
        'cl_pw_new'     => array('Новый пароль', 'Жаңа құпиясөз', 'New password', '新密码'),
        'cl_none'       => array('Клиенты появятся, когда зарегистрируются на сайте в разделе «Кабинет».', 'Клиенттер сайттағы «Кабинет» бөлімінде тіркелгенде пайда болады.', 'Clients appear here once they sign up on the site (Account page).', '客户在网站“账户”页面注册后显示在这里。'),
        'cl_search'     => array('Поиск: имя, телефон, ID, питомец', 'Іздеу: аты, телефон, ID, жануар', 'Search: name, phone, ID, pet', '搜索：姓名、电话、ID、宠物'),
        'cl_since'      => array('С нами с', 'Бізбен бірге', 'Client since', '注册时间'),
        'cl_pw_reset'   => array('Новый пароль', 'Жаңа құпиясөз', 'New password', '重设密码'),
        'cl_pw_q'       => array('Поставить клиенту новый пароль? Старый перестанет работать.', 'Клиентке жаңа құпиясөз қою керек пе? Ескісі жарамсыз болады.', 'Set a new password for this client? The old one will stop working.', '为客户设置新密码？旧密码将失效。'),
        'cl_rec'        => array('Рекомендация клиенту', 'Клиентке ұсыныс', 'Recommendation', '给客户的建议'),
        'cl_rec_ph'     => array('Видна клиенту в разделе «Для вас»', 'Клиентке «Сізге» бөлімінде көрінеді', 'Shown to the client in “For you”', '客户在“为你”中可见'),
        'cl_link'       => array('Привязать питомца из базы…', 'Базадағы жануарды байланыстыру…', 'Link a pet from the database…', '关联数据库中的宠物…'),
        'cl_link_btn'   => array('Привязать', 'Байланыстыру', 'Link', '关联'),
        'cl_board_hint' => array('Записи блога, групповые прогулки и акции видны всем клиентам в кабинете, раздел «Для вас». Запись на групповую прогулку приходит в чат клиента.', 'Блог жазбалары, топтық серуендер мен акциялар барлық клиенттерге «Сізге» бөлімінде көрінеді. Топтық серуенге жазылу клиент чатына келеді.', 'Blog posts, group walks and promotions are shown to all clients in “For you”. Group-walk sign-ups arrive in the client chat.', '博客、集体遛狗和优惠对所有客户可见，报名会发到客户聊天中。'),
        'cl_b_title'    => array('Название', 'Атауы', 'Title', '标题'),
        'cl_b_when'     => array('Когда', 'Қашан', 'When', '时间'),
        'cl_b_when_ph'  => array('Например, сб 23.11 в 10:00', 'Мысалы, сб 23.11 сағ 10:00', 'E.g. Sat 23.11 at 10:00', '例如：周六 23.11 10:00'),
        'cl_b_place'    => array('Где', 'Қайда', 'Where', '地点'),
        'cl_b_text'     => array('Описание', 'Сипаттама', 'Description', '描述'),
        'cl_b_add'      => array('Опубликовать', 'Жариялау', 'Publish', '发布'),
        'cl_b_joined'   => array('Записались', 'Жазылғандар', 'Signed up', '已报名'),
        'cl_b_promo'    => array('Акция', 'Акция', 'Promo', '优惠'),
        'cl_b_group'    => array('Групповая прогулка', 'Топтық серуен', 'Group walk', '集体遛狗'),
        'cl_b_tip'      => array('Совет', 'Кеңес', 'Tip', '建议'),
        'cl_b_group_s'  => array('Прогулка', 'Серуен', 'Group walk', '集体遛狗'),
        'cl_ok'         => array('Подтверждённый клиент — помощник может записывать сам', 'Расталған клиент — көмекші өзі жаза алады', 'Verified client — the assistant can book on their behalf', '已确认客户：助手可直接预约'),
        'rep_t'         => array('Отчёт для хозяина: настроение и что сделано', 'Иесіне есеп: көңіл-күйі және не істелді', 'Report for the owner: mood and what was done', '给主人的报告：情绪与完成事项'),
        'rep_note'      => array('Пара слов хозяину (необязательно)', 'Иесіне бірер сөз (міндетті емес)', 'A few words for the owner (optional)', '给主人留言（可选）'),
        'mood_1' => array('Грустил', 'Мұңайды', 'Sad', '难过'), 'mood_2' => array('Скучал', 'Зерікті', 'Bored', '无聊'), 'mood_3' => array('Спокоен', 'Тыныш', 'Calm', '平静'),
        'mood_4' => array('Доволен', 'Риза', 'Happy', '开心'), 'mood_5' => array('Счастлив', 'Бақытты', 'Overjoyed', '非常开心'),
        'did_potty' => array('Сделал дела', 'Іс бітті', 'Potty done', '已方便'), 'did_water' => array('Вода', 'Су', 'Water', '喝水'), 'did_play' => array('Поиграли', 'Ойнадық', 'Played', '玩耍'),
        'did_paws' => array('Помыли лапы', 'Табанын жудық', 'Paws washed', '洗爪子'), 'did_feed' => array('Покормила', 'Тамақтандырдым', 'Fed', '喂食'), 'did_litter' => array('Лоток / уборка', 'Науа / тазалау', 'Litter / cleaning', '猫砂/清洁'),
        'did_walk' => array('Погуляли', 'Серуендедік', 'Walked', '散步'), 'did_meds' => array('Лекарство', 'Дәрі', 'Medication', '药物'),
        'cl_b_blog'     => array('Блог', 'Блог', 'Blog', '博客'),
        'cl_b_photo'    => array('Обложка (необязательно)', 'Мұқаба (міндетті емес)', 'Cover photo (optional)', '封面（可选）'),
        'cl_map_t'      => array('Карта в кабинете клиента', 'Клиент кабинетіндегі карта', 'Map in the client account', '客户账户中的地图'),
        'cl_map_hint'   => array('Без ключа работает бесплатная карта OpenStreetMap. С ключом Google Maps (console.cloud.google.com → Maps JavaScript API) будет карта Google: до 10 000 открытий в месяц бесплатно.', 'Кілтсіз тегін OpenStreetMap картасы жұмыс істейді. Google Maps кілтімен Google картасы болады: айына 10 000 ашылымға дейін тегін.', 'Without a key the free OpenStreetMap map is used. With a Google Maps key you get Google Maps: up to 10,000 loads a month free.', '不填密钥使用免费的 OpenStreetMap；填写 Google Maps 密钥则使用 Google 地图，每月 1 万次免费。'),
        'cl_chat_sub'   => array('Клиент', 'Клиент', 'Client', '客户'),
        'trk_live'      => array('Хозяин видит прогулку на карте', 'Иесі серуенді картадан көреді', 'The owner sees the walk on a map', '主人可在地图上看到遛狗'),
        'trk_wait'      => array('определяю место…', 'орын анықталуда…', 'locating…', '定位中…'),
        'trk_on'        => array('трансляция идёт', 'трансляция жүріп жатыр', 'live', '直播中'),
        'trk_off'       => array('нет доступа к геолокации', 'геолокацияға рұқсат жоқ', 'no location access', '无定位权限'),
        'trk_st_hint'   => array('Что делаете сейчас? Хозяин увидит это у себя в кабинете.', 'Қазір не істеп жатырсыз? Иесі оны кабинетінде көреді.', 'What are you doing now? The owner will see it in their account.', '正在做什么？主人会在账户中看到。'),
        'trk_photo'     => array('Фото хозяину (с местом на карте)', 'Иесіне фото (картадағы орнымен)', 'Photo for the owner (with map location)', '给主人发照片（带地图位置）'),
        'trk_ph_ok'     => array('Фото отправлено хозяину', 'Фото иесіне жіберілді', 'Photo sent to the owner', '照片已发送给主人'),
        'trk_sending'   => array('Отправляю фото…', 'Фото жіберілуде…', 'Sending photo…', '正在发送照片…'),
        'trk_banner'    => array('Трансляция прогулки', 'Серуен трансляциясы', 'Walk is live', '遛狗直播'),
        'lw_walk'       => array('Гуляем', 'Серуендеп жүрміз', 'Walking', '散步'),
        'lw_play'       => array('Играем', 'Ойнап жүрміз', 'Playing', '玩耍'),
        'lw_eat'        => array('Кушаем', 'Тамақтанып жатырмыз', 'Eating', '吃饭'),
        'lw_water'      => array('Пьём водичку', 'Су ішіп жатырмыз', 'Drinking water', '喝水'),
        'lw_rest'       => array('Отдыхаем', 'Демалып жатырмыз', 'Resting', '休息'),
        'lw_sniff'      => array('Изучаем всё вокруг', 'Айналаны зерттеп жүрміз', 'Sniffing around', '四处嗅嗅'),
        'lw_potty'      => array('Сделали дела', 'Іс бітті', 'Potty done', '已方便'),
        'lw_home'       => array('Возвращаемся домой', 'Үйге қайтып келеміз', 'Heading home', '回家中'),
        'lw_pet'        => array('Обнимашки', 'Еркелетіп жатырмыз', 'Cuddles', '抱抱'),
    );
}

/* строки для скрипта трансляции в приложении */
add_filter('petgo_head_extra', function ($h) {
    $L = array();
    foreach (array('trk_on', 'trk_off', 'trk_wait', 'trk_ph_ok', 'trk_sending', 'trk_banner', 'pf_copied', 'neterr') as $k) {
        $L[$k] = petgo_t($k);
    }
    return '<script>window.PGT_L=' . wp_json_encode($L, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) . ';</script>' . $h;
}, 5);
