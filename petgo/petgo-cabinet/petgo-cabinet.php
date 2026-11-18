<?php
/**
 * Plugin Name: PetGo Кабинет
 * Description: Сайт PetGo (Главная, Услуги, Няни, Отзывы, Кабинет — без шорткодов, стиль iPhone, матовое Liquid Glass) и приложение для сотрудников (PWA): заказы, календарь, заявки с сайта, питомцы, сотрудники, отзывы, чаты, уведомления и «Настройки сайта». Помощник (ИИ) — отдельный плагин «PetGo ИИ».
 * Version: 34.2.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: web.for made
 */
if (!defined('ABSPATH')) {
    exit;
}

define('PETGO_VER', '34.2.0');
define('PETGO_FILE', __FILE__);

/* ============================================================
   1. ПОЛЯ АНКЕТЫ (названия берутся из словаря: f_ключ)
   ============================================================ */
function petgo_pet_fields() {
    return array(
        'species' => array('select', array('dog', 'cat', 'other')),
        'species_other' => array('text'),
        'breed' => array('text'), 'age' => array('text'),
        'sex' => array('select', array('boy', 'girl')),
        'weight' => array('text'),
        'neutered' => array('select', array('yes', 'no')),
        'vaccines' => array('text'), 'parasites' => array('text'),
        'health' => array('textarea'), 'meds' => array('textarea'), 'food' => array('textarea'),
        'people' => array('text'), 'animals' => array('text'), 'fears' => array('text'),
        'leash' => array('text'), 'commands' => array('text'), 'toys' => array('text'), 'vet' => array('text'),
        'owner' => array('text'), 'phone' => array('text'), 'address' => array('text'),
        'access' => array('textarea'), 'emergency' => array('text'), 'notes' => array('textarea'),
    );
}

function petgo_main_fields() {
    return array('owner', 'phone', 'address', 'access', 'emergency');
}

function petgo_services() {
    return array('walk' => 's_walk', 'visit' => 's_visit', 'other' => 's_other');
}

/* ============================================================
   2. ЯЗЫК И ПЕРЕВОДЫ: русский, казахский, английский, китайский
   ============================================================ */
function petgo_langs() {
    return array('ru' => 'Русский', 'kk' => 'Қазақша', 'en' => 'English', 'zh' => '中文');
}

function petgo_lang() {
    static $l = null;
    if ($l !== null) {
        return $l;
    }
    $l = 'ru';
    $c = '';
    if (is_user_logged_in()) {
        $c = (string) get_user_meta(get_current_user_id(), 'petgo_lang', true);
    }
    if ($c === '' && isset($_COOKIE['petgo_lang'])) {
        $c = sanitize_key($_COOKIE['petgo_lang']);
    }
    $all = petgo_langs();
    if (isset($all[$c])) {
        $l = $c;
    }
    return $l;
}

function petgo_dict() {
    static $D = null;
    if ($D !== null) {
        return $D;
    }
    $D = array(
        'orders' => array('Заказы', 'Тапсырыстар', 'Orders', '订单'),
        'pets' => array('Питомцы', 'Жануарлар', 'Pets', '宠物'),
        'staff' => array('Сотрудники', 'Қызметкерлер', 'Staff', '员工'),
        'new_order' => array('Новый заказ', 'Жаңа тапсырыс', 'New order', '新订单'),
        'add_pet' => array('Добавить питомца', 'Жануар қосу', 'Add pet', '添加宠物'),
        'add_staff' => array('Добавить сотрудника', 'Қызметкер қосу', 'Add staff', '添加员工'),
        'settings' => array('Настройки', 'Баптаулар', 'Settings', '设置'),
        'profile' => array('Профиль', 'Профиль', 'Profile', '个人资料'),
        'logout' => array('Выйти', 'Шығу', 'Log out', '退出'),
        'save' => array('Сохранить', 'Сақтау', 'Save', '保存'),
        'close' => array('Закрыть', 'Жабу', 'Close', '关闭'),
        'delete' => array('Удалить', 'Жою', 'Delete', '删除'),
        'edit' => array('Изменить', 'Өзгерту', 'Edit', '编辑'),
        'cancel_order' => array('Отменить заказ', 'Тапсырыстан бас тарту', 'Cancel order', '取消订单'),
        'confirm_btn' => array('Подтвердить выполнение', 'Орындалғанын растау', 'Confirm completion', '确认完成'),
        'name' => array('Имя', 'Аты', 'Name', '姓名'),
        'phone' => array('Телефон', 'Телефон', 'Phone', '电话'),
        'login' => array('Логин (латиницей)', 'Логин (латынша)', 'Login (latin)', '账号（拉丁字母）'),
        'password' => array('Пароль (от 10 символов)', 'Құпиясөз (10 таңбадан)', 'Password (10+ chars)', '密码（至少10位）'),
        'new_pass' => array('Новый пароль (необязательно)', 'Жаңа құпиясөз (міндетті емес)', 'New password (optional)', '新密码（可选）'),
        'avatar' => array('Аватар', 'Аватар', 'Avatar', '头像'),
        'language' => array('Язык', 'Тіл', 'Language', '语言'),
        'dark' => array('Ночной режим', 'Түнгі режим', 'Dark mode', '夜间模式'),
        'role_admin' => array('Администратор', 'Әкімші', 'Administrator', '管理员'),
        'me' => array('я', 'мен', 'me', '我'),
        'role_nanny' => array('Сотрудник', 'Қызметкер', 'Staff', '员工'),
        'pet' => array('Питомец', 'Жануар', 'Pet', '宠物'),
        'pet_name' => array('Кличка', 'Аты', 'Pet name', '宠物名字'),
        'nanny' => array('Сотрудник', 'Қызметкер', 'Staff member', '员工'),
        'later' => array('Назначить позже', 'Кейін тағайындау', 'Assign later', '稍后分配'),
        'assign' => array('Назначить', 'Тағайындау', 'Assign', '分配'),
        'service' => array('Услуга', 'Қызмет', 'Service', '服务'),
        'date' => array('Дата', 'Күні', 'Date', '日期'),
        'time' => array('Время', 'Уақыты', 'Time', '时间'),
        'duration' => array('Часы работы (в минутах)', 'Жұмыс уақыты (минут)', 'Work time (minutes)', '工作时长（分钟）'),
        'days' => array('Дней подряд', 'Қатарынан күн', 'Days in a row', '连续天数'),
        'comment' => array('Комментарий', 'Пікір', 'Comment', '备注'),
        'create' => array('Создать', 'Жасау', 'Create', '创建'),
        's_walk' => array('Выгул', 'Серуендеу', 'Walk', '遛狗'),
        's_visit' => array('Визит домой', 'Үйге бару', 'Home visit', '上门服务'),
        's_other' => array('Другое', 'Басқа', 'Other', '其他'),
        'st_new' => array('Не назначен', 'Тағайындалмаған', 'Unassigned', '未分配'),
        'st_assigned' => array('Назначен', 'Тағайындалған', 'Assigned', '已分配'),
        'st_arrived' => array('На месте', 'Жеткен', 'On site', '已到达'),
        'st_picked' => array('С питомцем', 'Жануармен бірге', 'With the pet', '已接宠物'),
        'st_done' => array('Ждёт подтверждения', 'Растауды күтуде', 'Awaiting approval', '待确认'),
        'st_confirmed' => array('Подтверждён', 'Расталды', 'Confirmed', '已确认'),
        'st_cancelled' => array('Отменён', 'Бас тартылды', 'Cancelled', '已取消'),
        'all' => array('Все', 'Барлығы', 'All', '全部'),
        'fl_active' => array('Активные', 'Белсенді', 'Active', '进行中'),
        'fl_done' => array('Выполненные', 'Орындалған', 'Completed', '已完成'),
        'fl_cancel' => array('Отменённые', 'Бас тартылған', 'Cancelled', '已取消'),
        'no_orders' => array('Заказов нет', 'Тапсырыс жоқ', 'No orders', '暂无订单'),
        'no_pets' => array('Питомцев пока нет', 'Жануар әзірге жоқ', 'No pets yet', '暂无宠物'),
        'my_orders' => array('Мои заказы', 'Менің тапсырыстарым', 'My orders', '我的订单'),
        'active_cnt' => array('Активные заказы', 'Белсенді тапсырыстар', 'Active orders', '进行中订单'),
        'add_first' => array('Сначала добавьте питомца через кнопку +', 'Алдымен + арқылы жануар қосыңыз', 'First add a pet with the + button', '请先用 + 添加宠物'),
        'arrived_btn' => array('Я пришёл (отправить местоположение)', 'Мен жеттім (орынды жіберу)', 'I have arrived (send location)', '我到了（发送位置）'),
        'picked_btn' => array('Забрал собаку', 'Итті алдым', 'Picked up the dog', '已接到狗狗'),
        'return_btn' => array('Вернул собаку', 'Итті қайтардым', 'Returned the dog', '已送回狗狗'),
        'visit_end_btn' => array('Завершить визит', 'Келуді аяқтау', 'Finish the visit', '结束上门'),
        'locating' => array('Определяю место...', 'Орынды анықтаудамын...', 'Getting location...', '正在定位...'),
        'noloc' => array('Геолокация недоступна. Откройте сайт по https и включите геолокацию.', 'Геолокация қолжетімсіз. Сайтты https арқылы ашып, геолокацияны қосыңыз.', 'Location is unavailable. Open the site over https and enable location.', '无法获取位置，请使用 https 打开并开启定位。'),
        'locdenied' => array('Без местоположения приход не подтвердить. Разрешите доступ к геолокации и повторите.', 'Орынсыз келуді растау мүмкін емес. Геолокацияға рұқсат беріп, қайталаңыз.', 'Arrival cannot be confirmed without location. Allow location access and try again.', '没有位置无法确认到达，请允许定位后重试。'),
        'err_loc' => array('Нужно отправить местоположение', 'Орынды жіберу керек', 'Location is required', '必须发送位置'),
        'left' => array('Осталось', 'Қалды', 'Left', '剩余'),
        'late' => array('Опоздание', 'Кешігу', 'Late by', '迟到'),
        'to_return' => array('До возврата', 'Қайтаруға дейін', 'Until return', '距送回'),
        'time_up' => array('Время вышло', 'Уақыт өтті', 'Time is up', '时间已到'),
        'h' => array('ч', 'сағ', 'h', '小时'),
        'm' => array('мин', 'мин', 'min', '分钟'),
        'on_time' => array('вовремя', 'уақытында', 'on time', '准时'),
        'late_by' => array('опоздал на', 'кешікті', 'late by', '迟到'),
        'arrived_at' => array('Пришёл', 'Келді', 'Arrived', '到达'),
        'picked_at' => array('Забрал', 'Алды', 'Picked up', '接宠物'),
        'returned_at' => array('Вернул', 'Қайтарды', 'Returned', '送回'),
        'on_map' => array('Где был сотрудник (карта)', 'Қызметкер қайда болды (карта)', 'Staff location (map)', '员工位置（地图）'),
        'addr_map' => array('Адрес заказчика (карта)', 'Тапсырыс беруші мекенжайы (карта)', 'Customer address (map)', '客户地址（地图）'),
        'no_location' => array('Без локации', 'Локациясыз', 'No location', '无位置'),
        'wa_btn' => array('Написать хозяину в WhatsApp', 'Иесіне WhatsApp жазу', 'Message owner on WhatsApp', '在 WhatsApp 联系主人'),
        'tip_walk' => array('Вы подтвердили местоположение. Снимите видео и фото прихода и отправьте в группу Telegram. Заберите собаку у подъезда или порога, в дом не заходите и ничего не принимайте.', 'Орын расталды. Келгеніңіздің бейне мен фотосын түсіріп, Telegram тобына жіберіңіз. Итті подъезден немесе табалдырықтан алыңыз, үйге кірмеңіз, ештеңе қабылдамаңыз.', 'Location confirmed. Record a video and photo of your arrival and send them to the Telegram group. Take the dog at the entrance or doorstep, do not go inside and do not accept anything.', '位置已确认。请拍摄到达的视频和照片并发送到 Telegram 群。在楼门口或门口接狗，不要进屋，不要接收任何东西。'),
        'tip_visit' => array('Вы подтвердили местоположение. Снимите видео и фото прихода и отправьте в группу Telegram. Заходите вместе с консьержем или с включённой камерой, снимите всё на видео и уйдите.', 'Орын расталды. Келгеніңіздің бейне мен фотосын түсіріп, Telegram тобына жіберіңіз. Консьержбен бірге немесе камераны қосып кіріңіз, бәрін бейнеге түсіріп, кетіңіз.', 'Location confirmed. Record a video and photo of your arrival and send them to the Telegram group. Enter with the concierge or with the camera on, record everything on video and leave.', '位置已确认。请拍摄到达的视频和照片并发送到 Telegram 群。请与门卫一同进入或开启摄像，全程录像后离开。'),
        'tip_wa' => array('Когда до возврата останется 15–20 минут, отправьте хозяину фото и видео в WhatsApp.', 'Қайтаруға 15–20 минут қалғанда иесіне WhatsApp-қа фото мен бейне жіберіңіз.', 'When 15–20 minutes remain, send the owner photos and a video on WhatsApp.', '距送回还剩15–20分钟时，请通过 WhatsApp 向主人发送照片和视频。'),
        'tip_photo' => array('Сфотографируйте питомца и загрузите фото. Оно сохранится на сайте.', 'Жануарды суретке түсіріп, фотосын жүктеңіз. Ол сайтта сақталады.', 'Take a photo of the pet and upload it. It is stored on the site.', '请拍摄宠物照片并上传，照片会保存在网站上。'),
        'tip_loc' => array('По приходу сначала отправьте местоположение, это обязательно.', 'Келгенде алдымен орынды жіберіңіз, бұл міндетті.', 'On arrival, send your location first. This is required.', '到达后请先发送位置，这是必须的。'),
        'wait_confirm' => array('Ждём подтверждения администратора.', 'Әкімшінің растауын күтеміз.', 'Waiting for admin approval.', '等待管理员确认。'),
        'report_photo' => array('Фото отчёта', 'Есеп фотосы', 'Report photo', '报告照片'),
        'photos' => array('Фото', 'Фото', 'Photos', '照片'),
        'set_avatar' => array('Сделать аватаром', 'Аватар ету', 'Set as avatar', '设为头像'),
        'rating' => array('Рейтинг', 'Рейтинг', 'Ranking', '排名'),
        'done_cnt' => array('выполнено', 'орындалды', 'completed', '已完成'),
        'pending_cnt' => array('ждут подтверждения', 'растауды күтеді', 'awaiting approval', '待确认'),
        'ontime_cnt' => array('вовремя', 'уақытында', 'on time', '准时'),
        'history' => array('История заказов', 'Тапсырыстар тарихы', 'Order history', '订单历史'),
        'no_history' => array('Заказов ещё не было', 'Тапсырыс әлі болған жоқ', 'No orders yet', '暂无订单'),
        'wp_admin' => array('Админка WordPress', 'WordPress әкімші панелі', 'WordPress admin', 'WordPress 后台'),
        'no_access' => array('Нет доступа.', 'Қолжетімсіз.', 'No access.', '无权访问。'),
        'q_delete_pet' => array('Удалить питомца?', 'Жануарды жою керек пе?', 'Delete this pet?', '删除该宠物？'),
        'q_delete_staff' => array('Удалить сотрудника? Его активные заказы станут неназначенными.', 'Қызметкерді жою керек пе? Оның белсенді тапсырыстары тағайындалмаған болады.', 'Delete this staff member? Active orders become unassigned.', '删除该员工？其进行中订单将变为未分配。'),
        'q_delete_photo' => array('Удалить фото?', 'Фотоны жою керек пе?', 'Delete this photo?', '删除该照片？'),
        'q_cancel' => array('Отменить заказ?', 'Тапсырыстан бас тарту керек пе?', 'Cancel this order?', '取消该订单？'),
        'q_pick' => array('Вы забрали собаку?', 'Итті алдыңыз ба?', 'Have you picked up the dog?', '您已接到狗狗了吗？'),
        'q_finish' => array('Отправить фото и завершить?', 'Фотоны жіберіп, аяқтау керек пе?', 'Send the photo and finish?', '发送照片并完成？'),
        'ok_saved' => array('Сохранено', 'Сақталды', 'Saved', '已保存'),
        'ok_order' => array('Заказ создан', 'Тапсырыс жасалды', 'Order created', '订单已创建'),
        'ok_staff' => array('Сотрудник добавлен', 'Қызметкер қосылды', 'Staff added', '员工已添加'),
        'ok_deleted' => array('Удалено', 'Жойылды', 'Deleted', '已删除'),
        'ok_arrived' => array('Приход и местоположение записаны', 'Келгеніңіз бен орын жазылды', 'Arrival and location recorded', '已记录到达和位置'),
        'ok_picked' => array('Питомец у вас', 'Жануар сізде', 'You have the pet', '已接到宠物'),
        'ok_finished' => array('Заказ завершён, ждёт подтверждения', 'Тапсырыс аяқталды, растауды күтуде', 'Order finished, awaiting approval', '订单已完成，等待确认'),
        'err_fields' => array('Заполните обязательные поля', 'Міндетті өрістерді толтырыңыз', 'Fill in the required fields', '请填写必填项'),
        'err_overlap' => array('У сотрудника уже есть заказ в это время:', 'Қызметкерде осы уақытта тапсырыс бар:', 'The staff member already has an order at:', '该员工在此时间已有订单：'),
        'err_pass' => array('Пароль должен быть от 10 символов', 'Құпиясөз кемінде 10 таңба болуы керек', 'Password must be at least 10 characters', '密码至少10位'),
        'err_login' => array('Такой логин уже есть', 'Мұндай логин бар', 'This login already exists', '该账号已存在'),
        'err_na' => array('Действие недоступно', 'Әрекет қолжетімсіз', 'Action not available', '操作不可用'),
        'err_photo' => array('Не удалось загрузить фото. Нужен JPG, PNG или WebP', 'Фото жүктелмеді. JPG, PNG немесе WebP қажет', 'Could not upload the photo. Use JPG, PNG or WebP', '照片上传失败，请使用 JPG、PNG 或 WebP'),
        'err_photo_req' => array('Нужно загрузить фото', 'Фото жүктеу керек', 'A photo is required', '必须上传照片'),
        'err_arrive_first' => array('Сначала подтвердите приход', 'Алдымен келгеніңізді растаңыз', 'Confirm arrival first', '请先确认到达'),
        'err_pick_first' => array('Сначала нажмите «Забрал собаку»', 'Алдымен «Итті алдым» түймесін басыңыз', 'Press “Picked up the dog” first', '请先点击“已接到狗狗”'),
        'err_later' => array('Укажите время', 'Уақытты көрсетіңіз', 'Enter a time', '请填写时间'),
        'f_species' => array('Вид', 'Түрі', 'Species', '种类'),
        'f_species_other' => array('Если «Другое», какой вид (хомяк и т. д.)', '«Басқа» болса, қандай түр (атжалман және т.б.)', 'If “Other”, which species (hamster etc.)', '若选“其他”，请填写种类（仓鼠等）'),
        'f_breed' => array('Порода', 'Тұқымы', 'Breed', '品种'),
        'f_age' => array('Возраст', 'Жасы', 'Age', '年龄'),
        'f_sex' => array('Пол', 'Жынысы', 'Sex', '性别'),
        'f_weight' => array('Вес, кг', 'Салмағы, кг', 'Weight, kg', '体重（公斤）'),
        'f_neutered' => array('Кастрация или стерилизация', 'Кастрация немесе стерилизация', 'Neutered or spayed', '是否绝育'),
        'f_vaccines' => array('Прививки (даты)', 'Екпелер (күндері)', 'Vaccines (dates)', '疫苗（日期）'),
        'f_parasites' => array('Обработка от паразитов', 'Паразиттерден өңдеу', 'Parasite treatment', '驱虫'),
        'f_health' => array('Болезни и аллергии', 'Аурулар мен аллергия', 'Illnesses and allergies', '疾病和过敏'),
        'f_meds' => array('Лекарства (название, доза, время)', 'Дәрілер (аты, дозасы, уақыты)', 'Medication (name, dose, time)', '药物（名称、剂量、时间）'),
        'f_food' => array('Корм и режим кормления', 'Азық және тамақтандыру тәртібі', 'Food and feeding schedule', '食物和喂食安排'),
        'f_people' => array('Поведение с людьми', 'Адамдармен мінез-құлқы', 'Behaviour with people', '对人的表现'),
        'f_animals' => array('Поведение с животными', 'Жануарлармен мінез-құлқы', 'Behaviour with animals', '对动物的表现'),
        'f_fears' => array('Страхи', 'Қорқыныштары', 'Fears', '害怕的事'),
        'f_leash' => array('Тянет поводок, подбирает с земли', 'Жібін тартады, жерден жейді', 'Pulls leash, eats from the ground', '拉牵引绳、捡地上东西吃'),
        'f_commands' => array('Знает команды', 'Командаларды біледі', 'Knows commands', '会的口令'),
        'f_toys' => array('Игрушки и лакомства', 'Ойыншықтар мен тәттілер', 'Toys and treats', '玩具和零食'),
        'f_vet' => array('Ветклиника и телефон врача', 'Мал дәрігері және телефоны', 'Vet clinic and phone', '宠物医院和电话'),
        'f_owner' => array('Хозяин', 'Иесі', 'Owner', '主人'),
        'f_phone' => array('Телефон хозяина', 'Иесінің телефоны', 'Owner phone', '主人电话'),
        'f_address' => array('Адрес', 'Мекенжайы', 'Address', '地址'),
        'f_access' => array('Как попасть (домофон, код, ключи)', 'Қалай кіру (домофон, код, кілт)', 'How to get in (intercom, code, keys)', '进入方式（门禁、密码、钥匙）'),
        'f_emergency' => array('Контакт для экстренной связи', 'Шұғыл байланыс', 'Emergency contact', '紧急联系人'),
        'f_notes' => array('Другое', 'Басқа', 'Other', '其他'),
        'o_dog' => array('Собака', 'Ит', 'Dog', '狗'),
        'o_cat' => array('Кошка', 'Мысық', 'Cat', '猫'),
        'o_dogs' => array('Собаки', 'Иттер', 'Dogs', '狗'),
        'o_cats' => array('Кошки', 'Мысықтар', 'Cats', '猫'),
        'o_other' => array('Другое', 'Басқа', 'Other', '其他'),
        'o_boy' => array('Мальчик', 'Ұл', 'Male', '公'),
        'o_girl' => array('Девочка', 'Қыз', 'Female', '母'),
        'o_yes' => array('Да', 'Иә', 'Yes', '是'),
        'o_no' => array('Нет', 'Жоқ', 'No', '否'),
    );
    $D = array_merge($D, petgo_dict_more(), function_exists('petgo_dict_v34') ? petgo_dict_v34() : array());
    return $D;
}

function petgo_t($k) {
    $D = petgo_dict();
    if (!isset($D[$k])) {
        return $k;
    }
    $idx = array('ru' => 0, 'kk' => 1, 'en' => 2, 'zh' => 3);
    $i   = $idx[petgo_lang()];
    return (isset($D[$k][$i]) && $D[$k][$i] !== '') ? $D[$k][$i] : $D[$k][0];
}

/* ============================================================
   3. ОБЩИЕ ФУНКЦИИ
   ============================================================ */
function petgo_opt($k, $d = '') {
    return get_option('petgo_' . $k, $d);
}

function petgo_p($k, $d = '') {
    return isset($_POST[$k]) ? wp_unslash($_POST[$k]) : $d;
}

function petgo_page_url($args = array()) {
    $id = (int) petgo_opt('page_id');
    $u  = ($id && get_post_status($id)) ? get_permalink($id) : home_url('/');
    return $args ? add_query_arg($args, $u) : $u;
}

/* роли и права: см. includes/chat.php (petgo_role_of, petgo_can, petgo_is_admin, petgo_is_nanny) */

function petgo_nanny_obj($uid) {
    $u = get_userdata((int) $uid);
    return ($u && in_array('petgo_nanny', (array) $u->roles, true)) ? $u : false;
}

/* кому можно назначить заказ: владелец — любому сотруднику и себе; админ/менеджер — только себе и няням */
function petgo_assignees() {
    static $c = null;
    if ($c === null) {
        $c   = array();
        $me  = wp_get_current_user();
        $own = petgo_is_owner();
        $src = $own ? petgo_staff_users() : petgo_nannies();
        foreach ($src as $u) {
            if (!get_user_meta($u->ID, 'petgo_disabled', true)) {
                $c[(int) $u->ID] = $u;
            }
        }
        if ($me && $me->ID && petgo_can('office')) {
            $c[(int) $me->ID] = $me;
        }
        uasort($c, 'petgo_cmp_assignee');
    }
    return $c;
}

function petgo_cmp_assignee($a, $b) {
    $m = get_current_user_id();
    if ((int) $a->ID === $m) {
        return -1;
    }
    if ((int) $b->ID === $m) {
        return 1;
    }
    return strcasecmp($a->display_name, $b->display_name);
}

function petgo_assignee_ok($uid) {
    $a = petgo_assignees();
    return isset($a[(int) $uid]);
}

function petgo_assignee_label($u) {
    $r = petgo_role_of($u);
    $n = $u->display_name;
    if ((int) $u->ID === get_current_user_id()) {
        return $n . ' (' . petgo_t('me') . ')';
    }
    return ($r === 'admin' || $r === 'manager') ? $n . ' · ' . petgo_t('role_' . $r) : $n;
}

/* Часовой пояс Алматы: UTC+5 (Казахстан, без перехода на летнее время).
   Фиксированное смещение, чтобы не зависеть от настроек WordPress и устаревшей базы часовых поясов на хостинге. */
function petgo_tz() {
    static $z = null;
    if ($z === null) {
        $z = new DateTimeZone('+05:00');
    }
    return $z;
}

/* кеш в пределах одного запроса: список сотрудников и питомцев берётся из базы один раз */
function petgo_nannies() {
    static $c = null;
    if ($c === null) {
        $c = get_users(array('role' => 'petgo_nanny', 'orderby' => 'display_name'));
    }
    return $c;
}

function petgo_pets() {
    static $c = null;
    if ($c === null) {
        $c = get_posts(array('post_type' => 'petgo_pet', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true, 'update_post_term_cache' => false));
    }
    return $c;
}

/* заказы: берём только ID и все мета-поля одним запросом (быстрее и легче для базы) */
function petgo_orders($args = array()) {
    $ids = get_posts(array_merge(array('post_type' => 'petgo_order', 'post_status' => 'publish', 'numberposts' => 1000, 'orderby' => 'ID', 'order' => 'DESC', 'fields' => 'ids', 'no_found_rows' => true, 'update_post_term_cache' => false), $args));
    if (!$ids) {
        return array();
    }
    update_meta_cache('post', $ids);
    $r = array();
    foreach ($ids as $id) {
        $r[] = (object) array('ID' => (int) $id);
    }
    return $r;
}

/* заранее загружаем питомцев показываемых заказов одним пакетом, а не по одному запросу на карточку */
function petgo_prime($list) {
    $ids = array();
    foreach ($list as $o) {
        $pid = (int) petgo_o($o->ID, 'pet');
        if ($pid) {
            $ids[$pid] = $pid;
        }
    }
    if ($ids) {
        _prime_post_caches(array_values($ids), false, true);
    }
}

function petgo_o($id, $k) {
    return get_post_meta($id, '_' . $k, true);
}

function petgo_pet_name($oid) {
    $t = get_the_title((int) petgo_o($oid, 'pet'));
    return $t ? $t : '—';
}

function petgo_nanny_label($oid) {
    $u = (int) petgo_o($oid, 'nanny') ? get_userdata((int) petgo_o($oid, 'nanny')) : false;
    if ($u) {
        return $u->display_name;
    }
    return (int) petgo_o($oid, 'nanny') ? (string) petgo_o($oid, 'nanny_name') : '';
}

function petgo_flash($msg, $ok = true) {
    set_transient('petgo_fl_' . get_current_user_id(), array($msg, $ok), 120);
}

function petgo_flash_show() {
    $k = 'petgo_fl_' . get_current_user_id();
    $f = get_transient($k);
    if ($f) {
        delete_transient($k);
        echo '<div class="pgc-msg ' . ($f[1] ? 'ok' : 'err') . '" data-flash>' . esc_html($f[0]) . '</div>';
    }
}

function petgo_back($pg = 'orders', $more = array()) {
    wp_safe_redirect(petgo_page_url(array_merge(array('pg' => $pg), $more)));
    exit;
}

function petgo_guard($who) {
    check_admin_referer('petgo_act');
    if (!is_user_logged_in() || !petgo_can($who)) {
        wp_die('403', '', array('response' => 403));
    }
}

function petgo_fail($key, $pg = 'orders', $extra = '') {
    petgo_flash(petgo_t($key) . ($extra ? ' ' . $extra : ''), false);
    petgo_back($pg, (petgo_can('worker') && isset($_POST['order']) && petgo_order_of_nanny((int) $_POST['order'], get_current_user_id())) ? array('o' => (int) $_POST['order']) : array());
}

function petgo_fmt($ts) {
    return $ts ? wp_date('d.m · H:i', (int) $ts, petgo_tz()) : '';
}

function petgo_ts($date, $time) {
    try {
        $d = new DateTime($date . ' ' . $time, petgo_tz());
        return $d->getTimestamp();
    } catch (Exception $e) {
        return 0;
    }
}

/* пересечение по времени у одного сотрудника (запрос только по нужному окну) */
function petgo_conflict($nanny, $ts, $dur, $exclude = 0) {
    if (!$nanny) {
        return 0;
    }
    $end = $ts + $dur * 60;
    $os  = petgo_orders(array('numberposts' => -1, 'meta_query' => array('relation' => 'AND',
        array('key' => '_nanny', 'value' => (int) $nanny),
        array('key' => '_ts', 'value' => array($ts - 43200, $end), 'compare' => 'BETWEEN', 'type' => 'NUMERIC'),
    )));
    foreach ($os as $o) {
        if ($o->ID === (int) $exclude || petgo_o($o->ID, 'status') === 'cancelled') {
            continue;
        }
        $s = (int) petgo_o($o->ID, 'ts');
        $e = $s + max(1, (int) petgo_o($o->ID, 'dur')) * 60;
        if ($ts < $e && $end > $s) {
            return $o->ID;
        }
    }
    return 0;
}

/* группа питомца для фильтра: собаки, кошки или свой вид (хомяк и т. д.) */
function petgo_sp($pid) {
    $s = (string) get_post_meta($pid, '_pf_species', true);
    if ($s === 'dog' || $s === 'cat') {
        return array($s, petgo_t($s === 'dog' ? 'o_dogs' : 'o_cats'));
    }
    $o = trim((string) get_post_meta($pid, '_pf_species_other', true));
    if ($o === '') {
        return array('x_other', petgo_t('o_other'));
    }
    return array('x_' . mb_strtolower($o), mb_convert_case($o, MB_CASE_TITLE));
}

/* ============================================================
   4. РОЛЬ, ЗАПИСИ, ДОСТУП
   ============================================================ */
register_activation_hook(__FILE__, 'petgo_activate');
function petgo_activate() {
    petgo_install();
    $pid = (int) petgo_opt('page_id');
    if (!$pid || !get_post_status($pid)) {
        $id = wp_insert_post(array('post_title' => 'Кабинет', 'post_content' => '[petgo_cabinet]', 'post_status' => 'publish', 'post_type' => 'page'));
        if ($id && !is_wp_error($id)) {
            update_option('petgo_page_id', $id);
        }
    }
}

add_action('init', 'petgo_init');
function petgo_init() {
    if (get_option('petgo_ver') !== PETGO_VER) {
        petgo_install();
    }
    $c = array('public' => false, 'show_ui' => true, 'show_in_rest' => false, 'supports' => array('title'), 'has_archive' => false, 'rewrite' => false);
    register_post_type('petgo_pet', array_merge($c, array('labels' => array('name' => 'Питомцы PetGo', 'singular_name' => 'Питомец'), 'menu_icon' => 'dashicons-heart')));
    register_post_type('petgo_order', array_merge($c, array('labels' => array('name' => 'Заказы PetGo', 'singular_name' => 'Заказ'), 'menu_icon' => 'dashicons-clipboard')));
}

add_filter('show_admin_bar', 'petgo_admin_bar');
function petgo_admin_bar($show) {
    return petgo_is_staffer() ? false : $show;
}

add_action('admin_init', 'petgo_block_admin');
function petgo_block_admin() {
    global $pagenow;
    if (petgo_is_staffer() && !wp_doing_ajax() && $pagenow !== 'admin-post.php') {
        wp_safe_redirect(petgo_page_url());
        exit;
    }
}

add_filter('login_redirect', 'petgo_login_redirect', 10, 3);
function petgo_login_redirect($to, $req, $user) {
    if ($user && !is_wp_error($user) && array_intersect(array_keys(petgo_role_map()), (array) $user->roles)) {
        return petgo_page_url();
    }
    return $to;
}

add_filter('wp_robots', 'petgo_robots');
function petgo_robots($r) {
    $id = (int) petgo_opt('page_id');
    if ($id && is_page($id)) {
        $r['noindex']  = true;
        $r['nofollow'] = true;
    }
    return $r;
}

function petgo_ip_key() {
    return 'petgo_f_' . md5(petgo_ip());
}

add_filter('authenticate', 'petgo_auth_lock', 30, 2);
function petgo_auth_lock($user, $login) {
    if ($login && (int) get_transient(petgo_ip_key()) >= 5) {
        return new WP_Error('petgo_locked', 'Too many attempts. Wait 15 minutes.');
    }
    return $user;
}

add_filter('authenticate', 'petgo_auth_disabled', 40, 1);
function petgo_auth_disabled($user) {
    if ($user instanceof WP_User && get_user_meta($user->ID, 'petgo_disabled', true)) {
        return new WP_Error('petgo_off', 'Access disabled.');
    }
    return $user;
}

add_action('wp_login_failed', 'petgo_login_failed');
function petgo_login_failed() {
    $k = petgo_ip_key();
    set_transient($k, (int) get_transient($k) + 1, 15 * MINUTE_IN_SECONDS);
}

add_action('wp_login', 'petgo_login_ok');
function petgo_login_ok() {
    delete_transient(petgo_ip_key());
}

/* ============================================================
   5. ФОТО: сильное сжатие (webp или jpeg), хранятся в uploads/petgo
   ============================================================ */
function petgo_upload_dir($d) {
    $sub         = isset($GLOBALS['petgo_sub']) ? $GLOBALS['petgo_sub'] : 'petgo';
    $d['subdir'] = '/' . $sub;
    $d['path']   = $d['basedir'] . '/' . $sub;
    $d['url']    = $d['baseurl'] . '/' . $sub;
    return $d;
}

/* возвращает array(url, file) или код ошибки; 'none' = файл не выбран */
function petgo_save_image($f, $max, $q, $sub = 'petgo') {
    if (empty($f['name']) || (isset($f['error']) && $f['error'] === UPLOAD_ERR_NO_FILE)) {
        return 'none';
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return in_array((int) $f['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true) ? 'err_photo_big' : 'err_photo';
    }
    if (!petgo_upload_ok($f)) {
        return 'err_photo';
    }
    if ($sub === 'petgo') {
        petgo_media_guard();
    }
    $GLOBALS['petgo_sub'] = $sub === 'petgo-reviews' ? 'petgo-reviews' : 'petgo';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    add_filter('upload_dir', 'petgo_upload_dir');
    $up = wp_handle_upload($f, array('test_form' => false, 'mimes' => array('jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp')));
    remove_filter('upload_dir', 'petgo_upload_dir');
    if (!empty($up['error']) || empty($up['file'])) {
        return 'err_photo';
    }
    $file = $up['file'];
    $ed   = wp_get_image_editor($file);
    if (is_wp_error($ed)) {
        @unlink($file);
        return 'err_photo';
    }
    if (method_exists($ed, 'maybe_exif_rotate')) {
        $ed->maybe_exif_rotate();
    }
    $ed->resize($max, $max, false);
    $ed->set_quality($q);
    $mime = wp_image_editor_supports(array('mime_type' => 'image/webp')) ? 'image/webp' : 'image/jpeg';
    $ext  = ($mime === 'image/webp') ? 'webp' : 'jpg';
    $new  = dirname($file) . '/' . wp_generate_password(14, false) . '.' . $ext;
    $s    = $ed->save($new, $mime);
    @unlink($file);
    if (is_wp_error($s) || empty($s['path'])) {
        return 'err_photo';
    }
    petgo_strip_meta($s['path']);
    $ud  = wp_upload_dir();
    $url = str_replace(wp_normalize_path($ud['basedir']), $ud['baseurl'], wp_normalize_path($s['path']));
    return array('url' => $url, 'file' => $s['path']);
}

function petgo_rm($path) {
    if (!$path) {
        return;
    }
    $ud = wp_upload_dir();
    if (strpos(wp_normalize_path($path), wp_normalize_path($ud['basedir'] . '/petgo')) === 0) {
        @unlink($path);
    }
}

function petgo_photos($pid) {
    $p = get_post_meta($pid, '_photos', true);
    return is_array($p) ? $p : array();
}

function petgo_pet_avatar_url($pid) {
    $a = get_post_meta($pid, '_avatar', true);
    if (is_array($a) && !empty($a['url'])) {
        return petgo_media($a['url']);
    }
    $ph = petgo_photos($pid);
    return $ph ? petgo_media($ph[0]['url']) : '';
}

function petgo_user_avatar_url($uid) {
    $a = get_user_meta($uid, 'petgo_avatar', true);
    return (is_array($a) && !empty($a['url'])) ? petgo_media($a['url']) : '';
}

function petgo_av($url, $name, $size = '') {
    $cls = 'pgc-av' . ($size ? ' ' . $size : '');
    if ($url) {
        return '<span class="' . $cls . '"><img src="' . esc_url($url) . '" alt="" loading="lazy" decoding="async"></span>';
    }
    return '<span class="' . $cls . '">' . esc_html(mb_strtoupper(mb_substr(trim($name) !== '' ? trim($name) : '?', 0, 1))) . '</span>';
}

/* ============================================================
   6. ДЕЙСТВИЯ
   ============================================================ */
foreach (array('pet_save', 'pet_del', 'photo_del', 'photo_avatar', 'staff_new', 'staff_save', 'staff_del', 'order_new', 'order_assign', 'order_set', 'arrive', 'pick', 'finish', 'profile_save', 'lang_set') as $petgo_a) {
    add_action('admin_post_petgo_' . $petgo_a, 'petgo_h_' . $petgo_a);
}

function petgo_h_lang_set() {
    check_admin_referer('petgo_act');
    if (is_user_logged_in()) {
        $l = sanitize_key((string) petgo_p('lang'));
        if (isset(petgo_langs()[$l])) {
            update_user_meta(get_current_user_id(), 'petgo_lang', $l);
        }
    }
    petgo_back(petgo_ref_pg());
}

/* страница, с которой пришёл запрос (чтобы после сохранения остаться на ней) */
function petgo_ref_pg($def = 'home') {
    $r = (string) wp_get_referer();
    $q = (string) wp_parse_url($r, PHP_URL_QUERY);
    if ($q !== '') {
        wp_parse_str($q, $a);
        if (!empty($a['pg']) && is_string($a['pg'])) {
            return sanitize_key($a['pg']);
        }
    }
    return $def;
}

function petgo_h_profile_save() {
    check_admin_referer('petgo_act');
    if (!is_user_logged_in()) {
        wp_die('403', '', array('response' => 403));
    }
    $uid  = get_current_user_id();
    $name = sanitize_text_field((string) petgo_p('name'));
    if ($name === '') {
        petgo_fail('err_fields');
    }
    $em = petgo_email_check(petgo_p('email'), $uid);
    if ($em === false) {
        petgo_fail('err_email');
    }
    $upd = array('ID' => $uid, 'display_name' => $name, 'first_name' => $name);
    if ($em !== '') {
        $upd['user_email'] = $em;
    }
    wp_update_user($upd);
    update_user_meta($uid, 'petgo_phone', sanitize_text_field((string) petgo_p('phone')));
    if (isset($_FILES['avatar'])) {
        $r = petgo_save_image($_FILES['avatar'], 300, 70);
        if (is_array($r)) {
            $old = get_user_meta($uid, 'petgo_avatar', true);
            if (is_array($old)) {
                petgo_rm(isset($old['file']) ? $old['file'] : '');
            }
            update_user_meta($uid, 'petgo_avatar', $r);
        } elseif ($r !== 'none') {
            petgo_fail($r);
        }
    }
    petgo_flash(petgo_t('ok_saved'));
    petgo_back(petgo_ref_pg());
}

/* ---- питомцы ---- */
function petgo_h_pet_save() {
    petgo_guard('office');
    $id   = (int) petgo_p('pet_id');
    if ($id && !petgo_can('admin')) {
        petgo_fail('err_perm', 'pets');
    }
    $name = sanitize_text_field((string) petgo_p('title'));
    if ($name === '') {
        petgo_fail('err_fields', 'pets');
    }
    if ($id && get_post_type($id) === 'petgo_pet') {
        wp_update_post(array('ID' => $id, 'post_title' => $name));
    } else {
        $id = wp_insert_post(array('post_type' => 'petgo_pet', 'post_status' => 'publish', 'post_title' => $name));
    }
    if (!$id || is_wp_error($id)) {
        petgo_fail('err_fields', 'pets');
    }
    foreach (petgo_pet_fields() as $k => $f) {
        $v = (string) petgo_p('f_' . $k);
        update_post_meta($id, '_pf_' . $k, $f[0] === 'textarea' ? sanitize_textarea_field($v) : sanitize_text_field($v));
    }
    if (isset($_FILES['avatar'])) {
        $r = petgo_save_image($_FILES['avatar'], 480, 70);
        if (is_array($r)) {
            $old = get_post_meta($id, '_avatar', true);
            if (is_array($old) && !empty($old['file'])) {
                petgo_rm($old['file']);
            }
            update_post_meta($id, '_avatar', $r);
        } elseif ($r !== 'none') {
            petgo_fail($r, 'pets');
        }
    }
    $fr = (int) petgo_p('from_req');
    if ($fr) {
        global $wpdb;
        $wpdb->update(petgo_tb('reqs'), array('status' => 'done', 'pet_id' => (int) $id), array('id' => $fr), array('%s', '%d'), array('%d'));
    }
    petgo_flash(petgo_t('ok_saved'));
    petgo_back('pets');
}

function petgo_h_pet_del() {
    petgo_guard('admin');
    $id = (int) petgo_p('pet_id');
    if (get_post_type($id) === 'petgo_pet') {
        petgo_why('Удалил питомца «' . get_the_title($id) . '»', 'pets');
        foreach (petgo_photos($id) as $ph) {
            petgo_rm(isset($ph['file']) ? $ph['file'] : '');
        }
        $a = get_post_meta($id, '_avatar', true);
        if (is_array($a) && !empty($a['file'])) {
            petgo_rm($a['file']);
        }
        wp_trash_post($id);
        petgo_flash(petgo_t('ok_deleted'));
    }
    petgo_back('pets');
}

function petgo_h_photo_del() {
    petgo_guard('admin');
    $pid = (int) petgo_p('pet_id');
    $key = (string) petgo_p('photo');
    petgo_why('Удалил фото питомца «' . get_the_title($pid) . '»', 'pets');
    $new = array();
    $url = '';
    foreach (petgo_photos($pid) as $ph) {
        if ($ph['id'] === $key) {
            petgo_rm(isset($ph['file']) ? $ph['file'] : '');
            $url = $ph['url'];
        } else {
            $new[] = $ph;
        }
    }
    update_post_meta($pid, '_photos', $new);
    $a = get_post_meta($pid, '_avatar', true);
    if ($url && is_array($a) && isset($a['url']) && $a['url'] === $url) {
        delete_post_meta($pid, '_avatar');
    }
    petgo_flash(petgo_t('ok_deleted'));
    petgo_back('pets');
}

function petgo_h_photo_avatar() {
    petgo_guard('admin');
    $pid = (int) petgo_p('pet_id');
    $key = (string) petgo_p('photo');
    foreach (petgo_photos($pid) as $ph) {
        if ($ph['id'] === $key) {
            $old = get_post_meta($pid, '_avatar', true);
            if (is_array($old) && !empty($old['file'])) {
                petgo_rm($old['file']);
            }
            update_post_meta($pid, '_avatar', array('url' => $ph['url'], 'file' => ''));
            petgo_flash(petgo_t('ok_saved'));
            break;
        }
    }
    petgo_back('pets');
}

/* ---- сотрудники ---- */
function petgo_h_staff_new() {
    petgo_guard('admin');
    $name  = sanitize_text_field((string) petgo_p('name'));
    $login = sanitize_user((string) petgo_p('login'), true);
    $pass  = (string) petgo_p('pass');
    $rl    = (string) petgo_p('role');
    $rl    = in_array($rl, array('admin', 'manager', 'nanny'), true) ? $rl : 'nanny';
    if ($rl === 'admin' && !petgo_is_owner()) {
        petgo_fail('err_perm', 'staff');
    }
    if ($name === '' || $login === '') {
        petgo_fail('err_fields', 'staff');
    }
    if (strlen($pass) < 10) {
        petgo_fail('err_pass', 'staff');
    }
    if (username_exists($login)) {
        petgo_fail('err_login', 'staff');
    }
    $em = petgo_email_check(petgo_p('email'));
    if ($em === false) {
        petgo_fail('err_email', 'staff');
    }
    $uid = wp_insert_user(array('user_login' => $login, 'user_pass' => $pass, 'user_email' => $em, 'display_name' => $name, 'first_name' => $name, 'role' => 'petgo_' . $rl));
    if (is_wp_error($uid)) {
        petgo_fail('err_fields', 'staff', $uid->get_error_message());
    }
    update_user_meta($uid, 'petgo_phone', sanitize_text_field((string) petgo_p('phone')));
    petgo_flash(petgo_t('ok_staff') . ': ' . $login);
    petgo_back('staff');
}

function petgo_h_staff_save() {
    petgo_guard('admin');
    $u = petgo_staff_obj((int) petgo_p('uid'));
    if (!$u || !petgo_can_manage($u)) {
        petgo_fail('err_na', 'staff');
    }
    $name = sanitize_text_field((string) petgo_p('name'));
    if ($name === '') {
        petgo_fail('err_fields', 'staff');
    }
    $rl = (string) petgo_p('role');
    if (petgo_is_owner() && in_array($rl, array('admin', 'manager', 'nanny'), true)) {
        $u->set_role('petgo_' . $rl);
    }
    $em = petgo_email_check(petgo_p('email'), $u->ID);
    if ($em === false) {
        petgo_fail('err_email', 'staff');
    }
    wp_update_user(array('ID' => $u->ID, 'display_name' => $name, 'first_name' => $name, 'user_email' => $em));
    update_user_meta($u->ID, 'petgo_phone', sanitize_text_field((string) petgo_p('phone')));
    $pass = (string) petgo_p('pass');
    if ($pass !== '') {
        if (strlen($pass) < 10) {
            petgo_fail('err_pass', 'staff');
        }
        wp_set_password($pass, $u->ID);
    }
    if (isset($_FILES['avatar'])) {
        $r = petgo_save_image($_FILES['avatar'], 300, 70);
        if (is_array($r)) {
            $old = get_user_meta($u->ID, 'petgo_avatar', true);
            if (is_array($old)) {
                petgo_rm(isset($old['file']) ? $old['file'] : '');
            }
            update_user_meta($u->ID, 'petgo_avatar', $r);
        } elseif ($r !== 'none') {
            petgo_fail($r, 'staff');
        }
    }
    if (petgo_p('disabled')) {
        update_user_meta($u->ID, 'petgo_disabled', 1);
        WP_Session_Tokens::get_instance($u->ID)->destroy_all();
    } else {
        delete_user_meta($u->ID, 'petgo_disabled');
    }
    petgo_staff_site_save($u->ID);
    petgo_flash(petgo_t('ok_saved'));
    petgo_back('staff');
}

function petgo_h_staff_del() {
    petgo_guard('admin');
    $u = petgo_staff_obj((int) petgo_p('uid'));
    if (!$u || !petgo_can_manage($u)) {
        petgo_fail('err_na', 'staff');
    }
    petgo_why('Удалил сотрудника «' . $u->display_name . '»', 'staff');
    foreach (petgo_orders(array('numberposts' => -1, 'meta_key' => '_nanny', 'meta_value' => $u->ID)) as $o) {
        $st = petgo_o($o->ID, 'status');
        if ($st === 'assigned' || $st === 'arrived' || $st === 'picked') {
            update_post_meta($o->ID, '_nanny', 0);
            update_post_meta($o->ID, '_status', 'new');
        }
    }
    $a = get_user_meta($u->ID, 'petgo_avatar', true);
    if (is_array($a)) {
        petgo_rm(isset($a['file']) ? $a['file'] : '');
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($u->ID);
    petgo_flash(petgo_t('ok_deleted'));
    petgo_back('staff');
}

/* ---- заказы ---- */
function petgo_h_order_new() {
    petgo_guard('office');
    $pet   = (int) petgo_p('pet');
    $nanny = (int) petgo_p('nanny');
    $date  = (string) petgo_p('date');
    $time  = (string) petgo_p('time');
    $dur   = max(10, min(720, (int) petgo_p('dur')));
    $days  = max(1, min(30, (int) petgo_p('days')));
    $svc   = (string) petgo_p('service');
    if (get_post_type($pet) !== 'petgo_pet' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        petgo_fail('err_fields');
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        petgo_fail('err_later');
    }
    if ($nanny && !petgo_assignee_ok($nanny)) {
        petgo_fail('err_fields');
    }
    if (!isset(petgo_services()[$svc])) {
        $svc = 'other';
    }
    $slots = array();
    for ($i = 0; $i < $days; $i++) {
        $dt = new DateTime($date);
        $dt->modify('+' . $i . ' day');
        $ts = petgo_ts($dt->format('Y-m-d'), $time);
        if (!$ts) {
            petgo_fail('err_fields');
        }
        if ($nanny && petgo_conflict($nanny, $ts, $dur)) {
            petgo_fail('err_overlap', 'orders', petgo_fmt($ts));
        }
        $slots[] = $ts;
    }
    $u = $nanny ? get_userdata($nanny) : false;
    foreach ($slots as $ts) {
        $id = wp_insert_post(array('post_type' => 'petgo_order', 'post_status' => 'publish', 'post_title' => 'Order'));
        if (!$id || is_wp_error($id)) {
            continue;
        }
        wp_update_post(array('ID' => $id, 'post_title' => 'Order #' . $id));
        update_post_meta($id, '_pet', $pet);
        update_post_meta($id, '_nanny', $nanny);
        update_post_meta($id, '_nanny_name', $u ? $u->display_name : '');
        update_post_meta($id, '_service', $svc);
        update_post_meta($id, '_ts', $ts);
        update_post_meta($id, '_dur', $dur);
        update_post_meta($id, '_comment', sanitize_textarea_field((string) petgo_p('comment')));
        update_post_meta($id, '_status', $nanny ? 'assigned' : 'new');
    }
    if ($slots && $nanny && !empty($id)) {
        do_action('petgo_order_event', $id, 'assigned');
    }
    if ($slots) {
        petgo_push_notify($nanny ? array($nanny) : petgo_push_users(array('owner', 'admin', 'manager')), $nanny ? 'Вам назначен заказ' : 'Новый заказ без няни', get_the_title($pet) . ' · ' . petgo_fmt($slots[0]), petgo_page_url(array('pg' => 'orders')));
    }
    petgo_flash(petgo_t('ok_order'));
    petgo_back('orders');
}

function petgo_h_order_assign() {
    petgo_guard('office');
    $oid   = (int) petgo_p('order');
    $nanny = (int) petgo_p('nanny');
    $st    = petgo_o($oid, 'status');
    if (get_post_type($oid) !== 'petgo_order' || ($st !== 'new' && $st !== 'assigned') || !petgo_assignee_ok($nanny)) {
        petgo_fail('err_na');
    }
    $c = petgo_conflict($nanny, (int) petgo_o($oid, 'ts'), (int) petgo_o($oid, 'dur'), $oid);
    if ($c) {
        petgo_fail('err_overlap', 'orders', petgo_fmt((int) petgo_o($c, 'ts')));
    }
    $u = get_userdata($nanny);
    if ((int) petgo_o($oid, 'nanny') && (int) petgo_o($oid, 'nanny') !== $nanny) {
        petgo_why('Передал заказ №' . $oid . ' (' . petgo_pet_name($oid) . ') от «' . petgo_nanny_label($oid) . '» к «' . $u->display_name . '»', 'orders', array('manager'));
    }
    update_post_meta($oid, '_nanny', $nanny);
    update_post_meta($oid, '_nanny_name', $u->display_name);
    update_post_meta($oid, '_status', 'assigned');
    do_action('petgo_order_event', $oid, 'assigned');
    petgo_push_notify(array($nanny), 'Вам назначен заказ', petgo_pet_name($oid) . ' · ' . petgo_fmt((int) petgo_o($oid, 'ts')), petgo_page_url(array('pg' => 'orders', 'o' => $oid)));
    petgo_flash(petgo_t('ok_saved'));
    petgo_back('orders');
}

function petgo_h_order_set() {
    petgo_guard('office');
    $oid = (int) petgo_p('order');
    $do  = (string) petgo_p('do');
    $st  = petgo_o($oid, 'status');
    if (get_post_type($oid) === 'petgo_order') {
        if ($do === 'confirm' && $st === 'done' && petgo_can('admin')) {
            update_post_meta($oid, '_status', 'confirmed');
            petgo_flash(petgo_t('ok_saved'));
        } elseif ($do === 'cancel' && $st !== 'confirmed' && $st !== 'cancelled') {
            petgo_why('Отменил заказ №' . $oid . ' (' . petgo_pet_name($oid) . ')', 'orders');
            update_post_meta($oid, '_status', 'cancelled');
            do_action('petgo_order_event', $oid, 'cancelled');
            petgo_flash(petgo_t('ok_saved'));
        } else {
            petgo_flash(petgo_t('err_na'), false);
        }
    }
    petgo_back('orders');
}

function petgo_order_of_nanny($oid, $uid) {
    return get_post_type($oid) === 'petgo_order' && get_post_status($oid) === 'publish' && (int) petgo_o($oid, 'nanny') === (int) $uid;
}

/* 1) пришёл: местоположение ОБЯЗАТЕЛЬНО */
function petgo_h_arrive() {
    petgo_guard('worker');
    $oid = (int) petgo_p('order');
    if (!petgo_order_of_nanny($oid, get_current_user_id()) || petgo_o($oid, 'status') !== 'assigned') {
        petgo_fail('err_na');
    }
    $lat = petgo_p('lat');
    $lng = petgo_p('lng');
    if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
        petgo_fail('err_loc');
    }
    update_post_meta($oid, '_lat', round((float) $lat, 6));
    update_post_meta($oid, '_lng', round((float) $lng, 6));
    update_post_meta($oid, '_acc', (int) petgo_p('acc'));
    update_post_meta($oid, '_arrived_at', time());
    update_post_meta($oid, '_status', 'arrived');
    do_action('petgo_order_event', $oid, 'arrived');
    petgo_flash(petgo_t('ok_arrived'));
    $late = petgo_late_min($oid);
    if ($late > 0) {
        $u = wp_get_current_user();
        petgo_sys_msg('sys_nannies', 0, 'late', 'Опоздание: ' . $u->display_name . ' пришёл(а) на ' . $late . ' мин позже. Заказ №' . $oid . ', ' . petgo_pet_name($oid) . ', ' . petgo_fmt((int) petgo_o($oid, 'ts')) . '. Напишите объяснительную.', (int) $u->ID);
    }
    petgo_back('orders', array('o' => $oid));
}

/* 2) забрал собаку (только для выгула) */
function petgo_h_pick() {
    petgo_guard('worker');
    $oid = (int) petgo_p('order');
    if (!petgo_order_of_nanny($oid, get_current_user_id()) || petgo_o($oid, 'status') !== 'arrived' || petgo_o($oid, 'service') !== 'walk') {
        petgo_fail('err_na');
    }
    update_post_meta($oid, '_picked_at', time());
    update_post_meta($oid, '_status', 'picked');
    do_action('petgo_order_event', $oid, 'picked');
    petgo_flash(petgo_t('ok_picked'));
    petgo_back('orders', array('o' => $oid));
}

/* 3) вернул собаку (или завершил визит) + обязательное фото на сайт */
function petgo_h_finish() {
    petgo_guard('worker');
    $oid  = (int) petgo_p('order');
    $walk = (petgo_o($oid, 'service') === 'walk');
    if (!petgo_order_of_nanny($oid, get_current_user_id())) {
        petgo_fail('err_na');
    }
    $st = petgo_o($oid, 'status');
    if ($st === 'assigned') {
        petgo_fail('err_arrive_first');
    }
    if ($walk && $st === 'arrived') {
        petgo_fail('err_pick_first');
    }
    if (($walk && $st !== 'picked') || (!$walk && $st !== 'arrived')) {
        petgo_fail('err_na');
    }
    $r = isset($_FILES['photo']) ? petgo_save_image($_FILES['photo'], 1024, 68) : 'none';
    if (!is_array($r)) {
        petgo_fail($r === 'none' ? 'err_photo_req' : $r);
    }
    update_post_meta($oid, '_rphoto', $r);
    $pid = (int) petgo_o($oid, 'pet');
    $ph  = petgo_photos($pid);
    if (count($ph) < 10) {
        $ph[] = array_merge(array('id' => wp_generate_password(10, false), 'url' => $r['url'], 'file' => '', 'by' => get_current_user_id(), 'order' => $oid, 't' => time()), function_exists('petgo_trk_last_geo') ? petgo_trk_last_geo($oid) : array());
        update_post_meta($pid, '_photos', $ph);
        $a = get_post_meta($pid, '_avatar', true);
        if (!is_array($a) || empty($a['url'])) {
            update_post_meta($pid, '_avatar', array('url' => $r['url'], 'file' => ''));
        }
    }
    update_post_meta($oid, '_done_at', time());
    update_post_meta($oid, '_status', 'done');
    /* 34.2: отчёт няни (настроение, что сделано) и уведомление клиенту «Оцените няню» */
    if (function_exists('petgo_rep_save')) {
        petgo_rep_save($oid);
    }
    do_action('petgo_order_event', $oid, 'done');
    petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), 'Заказ завершён', petgo_pet_name($oid) . ' · ' . petgo_nanny_label($oid), petgo_page_url(array('pg' => 'orders', 'o' => $oid)));
    petgo_flash(petgo_t('ok_finished'));
    petgo_back('orders', array('o' => $oid));
}

/* ============================================================
   7. ВНЕШНИЙ ВИД: полный экран, минимализм, углы прямые (круг только у аватарок)
   ============================================================ */

function petgo_field($name, $label, $type, $val = '', $opts = array(), $req = false) {
    $r = '<label class="pgc-f"><span>' . esc_html($label) . '</span>';
    if ($type === 'textarea') {
        $r .= '<textarea name="' . esc_attr($name) . '" rows="2">' . esc_textarea($val) . '</textarea>';
    } elseif ($type === 'select') {
        $r .= '<select name="' . esc_attr($name) . '"><option value="">—</option>';
        foreach ($opts as $o) {
            $r .= '<option value="' . esc_attr($o) . '"' . selected($val, $o, false) . '>' . esc_html(petgo_t('o_' . $o)) . '</option>';
        }
        $r .= '</select>';
    } else {
        $r .= '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '"' . ($req ? ' required' : '') . '>';
    }
    return $r . '</label>';
}

function petgo_form_open($action, $extra = '') {
    return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" ' . $extra . '><input type="hidden" name="action" value="petgo_' . esc_attr($action) . '">' . wp_nonce_field('petgo_act', '_wpnonce', true, false);
}

/* значки элементов (единый набор, цвет берут от текста) */
function petgo_ic($n) {
    static $P = array(
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>',
        'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3 1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8 1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'dash'     => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'order'    => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M12 9v6M9 12h6"/>',
        'paw'      => '<circle cx="6" cy="11" r="1.8"/><circle cx="10" cy="6.5" r="1.8"/><circle cx="14.5" cy="6.5" r="1.8"/><circle cx="18.5" cy="11" r="1.8"/><path d="M12.2 12c-3 0-5.2 3-5.2 5 0 1.8 1.8 2.6 5.2 2.6s5.2-.8 5.2-2.6c0-2-2.2-5-5.2-5z"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-6 6.5-6s6.5 2.5 6.5 6"/><circle cx="17.5" cy="9" r="2.5"/><path d="M17.5 14c2.5 0 4 1.8 4 4.5"/>',
        'userplus' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-6 6.5-6s6.5 2.5 6.5 6"/><path d="M19 8v6M16 11h6"/>',
        'trash'    => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6M14 11v6"/>',
        'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'check'    => '<path d="M5 12.5l4.5 4.5L19 7"/>',
        'undo'     => '<path d="M4 9h11a5 5 0 0 1 0 10H8M4 9l4-4M4 9l4 4"/>',
        'x'        => '<path d="M6 6l12 12M18 6L6 18"/>',
        'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'msg'      => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>',
        'pin'      => '<path d="M12 21s7-6.2 7-11a7 7 0 0 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'pinf'     => '<path fill="currentColor" stroke="none" fill-rule="evenodd" d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12zm0-9.2a2.8 2.8 0 1 1 0-5.6 2.8 2.8 0 0 1 0 5.6z"/>',
        'select'   => '<path d="m9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'chatplus' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M12 7v6M9 10h6"/>',
        'broom'    => '<path d="m7 21-4.3-4.3c-1-1-1-2.5 0-3.4l9.6-9.6c1-1 2.5-1 3.4 0l5.6 5.6c1 1 1 2.5 0 3.4L13 21"/><path d="M22 21H7"/><path d="m5 11 9 9"/>',
        'photo'    => '<rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
        'link'     => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
        'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'health'   => '<path d="M12 20s-7-4.4-7-10a4.2 4.2 0 0 1 7-3 4.2 4.2 0 0 1 7 3c0 5.6-7 10-7 10z"/>',
        'food'     => '<path d="M4 11h16a8 8 0 0 1-16 0z"/><path d="M8 7c0-1.6 1-1.6 1-3.2M12 7c0-1.6 1-1.6 1-3.2M16 7c0-1.6 1-1.6 1-3.2"/>',
        'behav'    => '<circle cx="12" cy="12" r="9"/><path d="M8.5 14c1 1.5 2.2 2 3.5 2s2.5-.5 3.5-2M9 9.5h.01M15 9.5h.01"/>',
        'vet'      => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M12 8v8M8 12h8"/>',
        'note'     => '<path d="M7 3h8l4 4v14H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/>',
        'map'      => '<path d="M9 4L3 6.5v13L9 17l6 2.5 6-2.5v-13L15 7z"/><path d="M9 4v13M15 7v12.5"/>',
        'send'     => '<path d="M21 3L10 14M21 3l-7 18-4-7-7-4z"/>',
    );
    return isset($P[$n]) ? '<svg class="pgc-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $P[$n] . '</svg>' : '';
}

function petgo_icon_for($action, $fields) {
    if (in_array($action, array('pet_del', 'staff_del', 'req_del'), true)) {
        return 'trash';
    }
    if ($action === 'photo_avatar') {
        return 'user';
    }
    if ($action === 'pick') {
        return 'paw';
    }
    if ($action === 'req_set') {
        return (isset($fields['do']) && $fields['do'] === 'new') ? 'undo' : 'check';
    }
    if ($action === 'order_set') {
        return (isset($fields['do']) && $fields['do'] === 'confirm') ? 'check' : 'x';
    }
    return '';
}

function petgo_btn_form($action, $fields, $label, $cls = 'pgc-btn', $confirm = '', $why = false) {
    $r = petgo_form_open($action, 'class="pgc-inline"');
    foreach ($fields as $k => $v) {
        $r .= '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($v) . '">';
    }
    if ($why && petgo_need_why()) {
        return $r . '<input type="hidden" name="why" value=""><button type="button" class="' . esc_attr($cls) . ' pgc-whyb" data-q="' . esc_attr($confirm) . '">' . ($label !== '×' ? petgo_ic(petgo_icon_for($action, $fields)) : '') . esc_html($label) . '</button></form>';
    }
    $on = $confirm ? ' onclick="return confirm(\'' . esc_js($confirm) . '\')"' : '';
    return $r . '<button type="submit" class="' . esc_attr($cls) . '"' . $on . '>' . ($label !== '×' ? petgo_ic(petgo_icon_for($action, $fields)) : '') . esc_html($label) . '</button></form>';
}

/* окно: на телефоне выезжает снизу, на ПК — по центру */
function petgo_modal($id, $title, $body, $open = false) {
    return '<div class="pgc-modal' . ($open ? ' open' : '') . '" id="' . esc_attr($id) . '"><div class="pgc-box" role="dialog" aria-modal="true" aria-label="' . esc_attr($title) . '"><div class="pgc-mh"><b>' . esc_html($title) . '</b><button type="button" class="pgc-x" data-close aria-label="' . esc_attr(petgo_t('close')) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div><div class="pgc-mb">' . $body . '</div></div></div>';
}

function petgo_badge($st) {
    return '<span class="pgc-badge ' . esc_attr($st) . '">' . esc_html(petgo_t('st_' . $st)) . '</span>';
}

function petgo_val($k, $v, $type) {
    if ($type === 'select' && isset(petgo_dict()['o_' . $v])) {
        return petgo_t('o_' . $v);
    }
    return $v;
}

function petgo_late_min($id) {
    $arr = (int) petgo_o($id, 'arrived_at');
    return $arr ? (int) floor(($arr - (int) petgo_o($id, 'ts')) / 60) : 0;
}

function petgo_late_badge($id) {
    if (!petgo_o($id, 'arrived_at')) {
        return '';
    }
    $d = petgo_late_min($id);
    if ($d > 0) {
        return '<span class="pgc-badge late">' . esc_html(petgo_t('late_by') . ' ' . $d . ' ' . petgo_t('m')) . '</span>';
    }
    return '<span class="pgc-badge ok">' . esc_html(petgo_t('on_time')) . '</span>';
}

function petgo_wa_url($phone) {
    $d = preg_replace('/\D/', '', (string) $phone);
    if (strlen($d) === 11 && $d[0] === '8') {
        $d = '7' . substr($d, 1);
    }
    return $d ? 'https://wa.me/' . $d : '';
}

/* профиль питомца: аватар, имя, главное, остальное, фото */
function petgo_pet_profile($pid, $admin) {
    $name = get_the_title($pid);
    $o    = '<div class="pgc-ph">' . petgo_av(petgo_pet_avatar_url($pid), $name, 'lg') . '<strong style="font-size:16px">' . esc_html($name) . '</strong></div>';
    $main = '';
    foreach (petgo_main_fields() as $k) {
        $v = (string) get_post_meta($pid, '_pf_' . $k, true);
        if ($v === '') {
            continue;
        }
        $val = nl2br(esc_html($v));
        if ($k === 'phone') {
            $val = '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $v)) . '">' . esc_html($v) . '</a>';
        }
        $main .= '<dt>' . esc_html(petgo_t('f_' . $k)) . '</dt><dd>' . $val . '</dd>';
    }
    if ($main) {
        $o .= '<div class="pgc-main"><dl class="pgc-dl" style="margin:0">' . $main . '</dl></div>';
    }
    $rest = '';
    foreach (petgo_pet_fields() as $k => $f) {
        if (in_array($k, petgo_main_fields(), true)) {
            continue;
        }
        $v = (string) get_post_meta($pid, '_pf_' . $k, true);
        if ($v !== '') {
            $rest .= '<dt>' . esc_html(petgo_t('f_' . $k)) . '</dt><dd>' . nl2br(esc_html(petgo_val($k, $v, $f[0]))) . '</dd>';
        }
    }
    if ($rest) {
        $o .= '<dl class="pgc-dl">' . $rest . '</dl>';
    }
    $ph = petgo_photos($pid);
    if ($ph) {
        $o .= '<div class="mu" style="margin-top:8px">' . esc_html(petgo_t('photos')) . ' (' . count($ph) . '/10)</div><div class="pgc-gal">';
        foreach ($ph as $x) {
            $by = get_userdata((int) $x['by']);
            $o .= '<figure><a href="' . esc_url(petgo_media($x['url'])) . '" target="_blank" rel="noopener"><img src="' . esc_url(petgo_media($x['url'])) . '" alt="" loading="lazy" decoding="async"></a><figcaption>' . esc_html(($by ? $by->display_name : '') . ' · ' . wp_date('d.m', (int) $x['t'], petgo_tz())) . '</figcaption>';
            if ($admin && petgo_can('admin')) {
                $o .= petgo_btn_form('photo_avatar', array('pet_id' => $pid, 'photo' => $x['id']), petgo_t('set_avatar'), 'pgc-btn sec');
                $o .= petgo_btn_form('photo_del', array('pet_id' => $pid, 'photo' => $x['id']), '×', 'pgc-btn red', petgo_t('q_delete_photo'), true);
            }
            $o .= '</figure>';
        }
        $o .= '</div>';
    }
    return $o;
}

/* ход заказа: пришёл (с опозданием или нет), забрал, вернул, фото, карты (для админа) */
function petgo_timeline($id, $admin) {
    $ts = (int) petgo_o($id, 'ts');
    $h  = '<ul class="pgc-tl">';
    if (petgo_o($id, 'arrived_at')) {
        $h .= '<li><b>' . esc_html(petgo_t('arrived_at')) . ':</b> ' . esc_html(petgo_fmt((int) petgo_o($id, 'arrived_at'))) . ' ' . petgo_late_badge($id) . '</li>';
    }
    if (petgo_o($id, 'picked_at')) {
        $h .= '<li><b>' . esc_html(petgo_t('picked_at')) . ':</b> ' . esc_html(petgo_fmt((int) petgo_o($id, 'picked_at'))) . '</li>';
    }
    if (petgo_o($id, 'done_at')) {
        $h .= '<li><b>' . esc_html(petgo_t('returned_at')) . ':</b> ' . esc_html(petgo_fmt((int) petgo_o($id, 'done_at'))) . '</li>';
    }
    $h .= '</ul>';
    $rp = petgo_o($id, 'rphoto');
    if (is_array($rp) && !empty($rp['url'])) {
        $h .= '<p class="mu">' . esc_html(petgo_t('report_photo')) . '</p><a href="' . esc_url(petgo_media($rp['url'])) . '" target="_blank" rel="noopener"><img src="' . esc_url(petgo_media($rp['url'])) . '" alt="" loading="lazy" decoding="async" style="max-width:220px;width:100%;display:block"></a>';
    }
    return $h;
}

/* карточка заказа: сверху всегда главное, одно нажатие открывает профиль питомца и действия */
function petgo_finish_form($id, $labelkey) {
    return petgo_form_open('finish', 'enctype="multipart/form-data" class="pgc-finish"') . '<input type="hidden" name="order" value="' . (int) $id . '">' . (function_exists('petgo_rep_fields') ? petgo_rep_fields($id) : '') . '<label class="pgc-shot"><input class="pgc-vh" type="file" name="photo" accept="image/*" capture="environment" required data-preview><span class="pgc-shot-in"><svg class="pgc-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg><span>' . esc_html(petgo_t('tip_photo')) . '</span></span></label><button class="pgc-btn wide" type="submit" onclick="return confirm(\'' . esc_js(petgo_t('q_finish')) . '\')">' . esc_html(petgo_t($labelkey)) . '</button></form>';
}

/* ============================================================
   8. ШОРТКОД [petgo_cabinet]
   Страница кабинета выводится своим лёгким шаблоном (без темы сайта), см. includes/ui.php
   ============================================================ */
add_shortcode('petgo_cabinet', 'petgo_shortcode');
function petgo_shortcode() {
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    return petgo_head_tags() . petgo_render_app();
}

/* ---------- администратор ---------- */
function petgo_render_admin() {
    $pg = isset($_GET['pg']) ? sanitize_key(wp_unslash($_GET['pg'])) : 'home';
    if (!petgo_ext_page($pg)) {
        if ($pg === 'pets') {
            petgo_admin_pets();
        } elseif ($pg === 'staff' && petgo_can('admin')) {
            petgo_admin_staff();
        } elseif ($pg === 'requests') {
            petgo_admin_requests();
        } elseif ($pg === 'orders') {
            petgo_admin_orders();
        } else {
            petgo_admin_home();
        }
    }
}

/* окна создания и правки (лежат отдельно от содержимого страницы) */
function petgo_admin_modals() {
    petgo_modal_order();
    petgo_modal_pet();
    petgo_inv_modal();
    if (petgo_can('admin')) {
        petgo_modal_staff();
    }
}

function petgo_fab() {
    echo '<div class="pgc-fabm" id="pgc-fabm"><button type="button" class="pgc-mi" data-open="pgc-m-order">' . petgo_ic('order') . esc_html(petgo_t('new_order')) . '</button><button type="button" class="pgc-mi" data-open="pgc-m-addpet">' . petgo_ic('paw') . esc_html(petgo_t('add_pet')) . '</button>';
    if (petgo_can('admin')) {
        echo '<button type="button" class="pgc-mi" data-open="pgc-m-staff">' . petgo_ic('userplus') . esc_html(petgo_t('add_staff')) . '</button>';
    }
    echo '</div><button type="button" class="pgc-fab mob" data-menu="pgc-fabm" aria-label="' . esc_attr(petgo_t('create')) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>';
}

function petgo_modal_order() {
    $pets = petgo_pets();
    if (!$pets) {
        echo petgo_modal('pgc-m-order', petgo_t('new_order'), '<p class="mu">' . esc_html(petgo_t('add_first')) . '</p>');
        return;
    }
    $b = petgo_form_open('order_new') . '<div class="pgc-grid"><label class="pgc-f"><span>' . esc_html(petgo_t('pet')) . '</span><select name="pet" required>';
    foreach ($pets as $p) {
        $b .= '<option value="' . (int) $p->ID . '">' . esc_html($p->post_title) . '</option>';
    }
    $b .= '</select></label><label class="pgc-f"><span>' . esc_html(petgo_t('nanny')) . '</span><select name="nanny"><option value="0">' . esc_html(petgo_t('later')) . '</option>';
    foreach (petgo_assignees() as $u) {
        $b .= '<option value="' . (int) $u->ID . '">' . esc_html(petgo_assignee_label($u)) . '</option>';
    }
    $b .= '</select></label><label class="pgc-f"><span>' . esc_html(petgo_t('service')) . '</span><select name="service">';
    foreach (petgo_services() as $c => $l) {
        $b .= '<option value="' . esc_attr($c) . '">' . esc_html(petgo_t($l)) . '</option>';
    }
    $b .= '</select></label>' . petgo_field('date', petgo_t('date'), 'date', wp_date('Y-m-d', time(), petgo_tz()), array(), true) . petgo_field('time', petgo_t('time'), 'time', '', array(), true);
    $b .= '<label class="pgc-f"><span>' . esc_html(petgo_t('duration')) . '</span><input type="number" name="dur" value="60" min="10" max="720"></label>';
    $b .= '<label class="pgc-f"><span>' . esc_html(petgo_t('days')) . '</span><input type="number" name="days" value="1" min="1" max="30"></label></div>';
    $b .= petgo_field('comment', petgo_t('comment'), 'textarea') . '<button class="pgc-btn wide" type="submit">' . esc_html(petgo_t('create')) . '</button></form>';
    echo petgo_modal('pgc-m-order', petgo_t('new_order'), $b);
}

function petgo_modal_pet() {
    $edit = (isset($_GET['edit']) && petgo_can('admin')) ? (int) $_GET['edit'] : 0;
    if ($edit && get_post_type($edit) !== 'petgo_pet') {
        $edit = 0;
    }
    $pre = array();
    $fr  = (!$edit && isset($_GET['from_req']) && petgo_can('office')) ? (int) $_GET['from_req'] : 0;
    if ($fr) {
        $pre = petgo_req_prefill($fr);
        if (!$pre) {
            $fr = 0;
        }
    }
    /* 34.0: форма разделами, одинаковые строки, переключатели вместо списков (см. includes/petform.php) */
    /* 34.2: анкета открывается целой страницей (на телефоне — на весь экран, поля не сжимаются клавиатурой) */
    echo str_replace('class="pgc-modal', 'class="pgc-modal pgc-full', petgo_modal('pgc-m-pet', petgo_t($edit ? 'edit' : 'add_pet'), petgo_pf_app_form($edit, $pre, $fr), (bool) ($edit || $fr)));
}

function petgo_modal_staff() {
    $b = petgo_form_open('staff_new') . '<div class="pgc-grid">' . petgo_field('name', petgo_t('name'), 'text', '', array(), true) . petgo_field('phone', petgo_t('phone'), 'tel') . petgo_field('email', petgo_t('email'), 'email');
    $b .= petgo_field('login', petgo_t('login'), 'text', '', array(), true) . petgo_field('pass', petgo_t('password'), 'text', '', array(), true);
    $b .= petgo_role_select('nanny') . '</div><button class="pgc-btn wide" type="submit">' . esc_html(petgo_t('add_staff')) . '</button></form>';
    echo petgo_modal('pgc-m-staff', petgo_t('add_staff'), $b);
    $eu = isset($_GET['edit_staff']) ? petgo_staff_obj((int) $_GET['edit_staff']) : false;
    if ($eu && petgo_can_manage($eu)) {
        $e = petgo_form_open('staff_save', 'enctype="multipart/form-data"') . '<input type="hidden" name="uid" value="' . (int) $eu->ID . '"><label class="pgc-avpick">' . petgo_av(petgo_user_avatar_url($eu->ID), $eu->display_name, 'xl') . '<span>' . esc_html(petgo_t('av_change')) . '</span><input class="pgc-vh" type="file" name="avatar" accept="image/*" data-preview></label><div class="pgc-grid">';
        $e .= petgo_field('name', petgo_t('name'), 'text', $eu->display_name, array(), true) . petgo_field('phone', petgo_t('phone'), 'tel', (string) get_user_meta($eu->ID, 'petgo_phone', true)) . petgo_field('email', petgo_t('email'), 'email', petgo_real_email($eu));
        if (petgo_is_owner()) {
            $e .= petgo_role_select(petgo_role_of($eu));
        }
        $e .= petgo_field('pass', petgo_t('new_pass'), 'text') . '</div>' . petgo_staff_site_fields($eu) . '<label class="pgc-ck"><input type="checkbox" name="disabled" value="1"' . checked((bool) get_user_meta($eu->ID, 'petgo_disabled', true), true, false) . '> <span>' . esc_html(petgo_t('st_off')) . '</span></label><button class="pgc-btn wide" type="submit">' . esc_html(petgo_t('save')) . '</button></form>';
        echo petgo_modal('pgc-m-staffe', $eu->display_name . ' · ' . $eu->user_login, $e, true);
    }
}

function petgo_cmp_ts_asc($a, $b) {
    return (int) petgo_o($a->ID, 'ts') - (int) petgo_o($b->ID, 'ts');
}

function petgo_cmp_ts_desc($a, $b) {
    return (int) petgo_o($b->ID, 'ts') - (int) petgo_o($a->ID, 'ts');
}

/* заказы: фильтр Активные, Выполненные, Отменённые */
/* число заказов по вкладкам одним лёгким запросом */
function petgo_status_counts() {
    global $wpdb;
    $r = array('active' => 0, 'done' => 0, 'cancel' => 0);
    $q = $wpdb->get_results("SELECT m.meta_value st, COUNT(*) n FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE m.meta_key='_status' AND p.post_type='petgo_order' AND p.post_status='publish' GROUP BY m.meta_value");
    foreach ((array) $q as $x) {
        $k = $x->st === 'confirmed' ? 'done' : ($x->st === 'cancelled' ? 'cancel' : 'active');
        $r[$k] += (int) $x->n;
    }
    return $r;
}

function petgo_admin_orders() {
    $f = isset($_GET['st']) ? sanitize_key(wp_unslash($_GET['st'])) : 'active';
    if (!in_array($f, array('active', 'done', 'cancel'), true)) {
        $f = 'active';
    }
    $cnt = petgo_status_counts();
    echo '<div class="pgc-chips">';
    foreach (array('active' => 'fl_active', 'done' => 'fl_done', 'cancel' => 'fl_cancel') as $k => $l) {
        echo '<a class="pgc-chip' . ($f === $k ? ' on' : '') . '" href="' . esc_url(petgo_page_url(array('pg' => 'orders', 'st' => $k))) . '">' . esc_html(petgo_t($l)) . '<i>' . (int) $cnt[$k] . '</i></a>';
    }
    echo '</div>';
    if ($f === 'active') {
        $list = petgo_orders(array('numberposts' => 400, 'meta_query' => array(array('key' => '_status', 'value' => array('confirmed', 'cancelled'), 'compare' => 'NOT IN'))));
    } else {
        $list = petgo_orders(array('numberposts' => 60, 'meta_key' => '_ts', 'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_query' => array(array('key' => '_status', 'value' => $f === 'done' ? 'confirmed' : 'cancelled'))));
    }
    if ($f === 'active') {
        usort($list, 'petgo_cmp_ts_asc');
        $need = array();
        $rest = array();
        foreach ($list as $o) {
            $st = petgo_o($o->ID, 'status');
            if ($st === 'done' || $st === 'new') {
                $need[] = $o;
            } else {
                $rest[] = $o;
            }
        }
        $list = array_slice(array_merge($need, $rest), 0, 100);
    } else {
        usort($list, 'petgo_cmp_ts_desc');
        $list = array_slice($list, 0, 60);
    }
    if (!$list) {
        echo petgo_empty('orders', petgo_t('no_orders'));
    }
    petgo_prime($list);
    foreach ($list as $o) {
        echo petgo_order_card($o, true);
    }
}

/* питомцы с фильтром: собаки, кошки и свои виды (хомяк и т. д.) */
function petgo_admin_pets() {
    $pets = petgo_pets();
    echo '<button type="button" class="pgc-btn wide pgi-add" data-open="pgc-m-addpet">' . petgo_ic('paw') . esc_html(petgo_t('add_pet')) . '</button>';
    echo petgo_inv_list_html();
    if (!$pets) {
        echo petgo_empty('pets', petgo_t('no_pets'));
        return;
    }
    $g = array();
    foreach ($pets as $p) {
        $s = petgo_sp($p->ID);
        if (!isset($g[$s[0]])) {
            $g[$s[0]] = array($s[1], 0);
        }
        $g[$s[0]][1]++;
    }
    echo '<div class="pgc-chips"><button type="button" class="pgc-chip on" data-f="*">' . esc_html(petgo_t('all')) . '<i>' . count($pets) . '</i></button>';
    foreach (array('dog', 'cat') as $k) {
        if (isset($g[$k])) {
            echo '<button type="button" class="pgc-chip" data-f="' . esc_attr($k) . '">' . esc_html($g[$k][0]) . '<i>' . $g[$k][1] . '</i></button>';
        }
    }
    foreach ($g as $k => $v) {
        if ($k !== 'dog' && $k !== 'cat') {
            echo '<button type="button" class="pgc-chip" data-f="' . esc_attr($k) . '">' . esc_html($v[0]) . '<i>' . $v[1] . '</i></button>';
        }
    }
    echo '</div>';
    foreach ($pets as $p) {
        $s = petgo_sp($p->ID);
        echo '<details class="pgc-ord" data-k="p' . (int) $p->ID . '" data-sp="' . esc_attr($s[0]) . '"><summary>' . petgo_av(petgo_pet_avatar_url($p->ID), $p->post_title, 'md') . '<div class="pgc-sm"><div class="pgc-l1"><b>' . esc_html($p->post_title) . '</b><span class="mu">' . esc_html($s[1]) . '</span></div><div class="pgc-l2">' . esc_html(get_post_meta($p->ID, '_pf_owner', true) . ' · ' . get_post_meta($p->ID, '_pf_phone', true)) . '</div></div>' . ((int) get_post_meta($p->ID, '_client', true) ? '<span class="pgc-tag ok pgc-cltag">' . esc_html(petgo_t('cl_has_acc')) . '</span>' : '') . '</summary><div class="pgc-body">';
        echo petgo_pet_profile($p->ID, true);
        if (petgo_can('admin')) {
            echo '<a class="pgc-btn sec pgc-inline" href="' . esc_url(petgo_page_url(array('pg' => 'pets', 'edit' => $p->ID))) . '">' . petgo_ic('edit') . esc_html(petgo_t('edit')) . '</a>';
            echo petgo_btn_form('pet_del', array('pet_id' => $p->ID), petgo_t('delete'), 'pgc-btn red', petgo_t('q_delete_pet'), true);
        }
        echo '</div></details>';
    }
}

/* статистика сотрудников за один проход по заказам */
function petgo_staff_stats() {
    $S = array();
    foreach (petgo_orders() as $o) {
        $n = (int) petgo_o($o->ID, 'nanny');
        if (!$n) {
            continue;
        }
        if (!isset($S[$n])) {
            $S[$n] = array('done' => 0, 'pend' => 0, 'on' => 0, 'hrs' => 0.0, 'list' => array());
        }
        $st = petgo_o($o->ID, 'status');
        $S[$n]['list'][] = $o;
        if ($st === 'done') {
            $S[$n]['pend']++;
        } elseif ($st === 'confirmed') {
            $S[$n]['done']++;
            $S[$n]['hrs'] += max(1, (int) petgo_o($o->ID, 'dur')) / 60;
            if (petgo_o($o->ID, 'arrived_at') && petgo_late_min($o->ID) <= 0) {
                $S[$n]['on']++;
            }
        }
    }
    return $S;
}

/* сотрудники: рейтинг по числу выполненных заказов, оценки клиентов, история заказов */
function petgo_admin_staff() {
    $all = petgo_staff_users();
    if (!$all) {
        echo '<p class="mu">—</p>';
        return;
    }
    $f = isset($_GET['rl']) ? sanitize_key(wp_unslash($_GET['rl'])) : 'all';
    if (!in_array($f, array('all', 'admin', 'manager', 'nanny'), true)) {
        $f = 'all';
    }
    $cn = array('all' => count($all), 'admin' => 0, 'manager' => 0, 'nanny' => 0);
    $list = array();
    foreach ($all as $u) {
        $r = petgo_role_of($u);
        $cn[$r]++;
        if ($f === 'all' || $f === $r) {
            $list[] = $u;
        }
    }
    echo '<div class="pgc-chips">';
    foreach (array('all' => 'rl_all', 'admin' => 'rl_admin', 'manager' => 'rl_manager', 'nanny' => 'rl_nanny') as $k => $l) {
        echo '<a class="pgc-chip' . ($f === $k ? ' on' : '') . '" href="' . esc_url(petgo_page_url(array('pg' => 'staff', 'rl' => $k))) . '">' . esc_html(petgo_t($l)) . '<i>' . (int) $cn[$k] . '</i></a>';
    }
    echo '</div>';
    $S = petgo_staff_stats();
    usort($list, function ($a, $b) use ($S) {
        $x = isset($S[$a->ID]) ? $S[$a->ID]['done'] : 0;
        $y = isset($S[$b->ID]) ? $S[$b->ID]['done'] : 0;
        return $y - $x;
    });
    $i = 0;
    foreach ($list as $u) {
        $i++;
        $st  = isset($S[$u->ID]) ? $S[$u->ID] : array('done' => 0, 'pend' => 0, 'on' => 0, 'hrs' => 0, 'list' => array());
        $rv  = petgo_nanny_rating($u->ID);
        $off = (bool) get_user_meta($u->ID, 'petgo_disabled', true);
        $rl  = petgo_role_of($u);
        $ph  = (string) get_user_meta($u->ID, 'petgo_phone', true);
        echo '<details class="pgc-ord" data-k="s' . (int) $u->ID . '"><summary><span class="pgc-rank">' . $i . '</span>' . petgo_av(petgo_user_avatar_url($u->ID), $u->display_name, 'md') . '<div class="pgc-sm"><div class="pgc-l1"><b>' . esc_html($u->display_name) . '</b>' . ($off ? '<span class="pgc-badge cancelled">' . esc_html(petgo_t('st_off')) . '</span>' : '') . '</div>';
        echo '<div class="pgc-l2">' . esc_html(petgo_t('role_' . $rl) . ' · ' . $st['done'] . ' ' . petgo_t('done_cnt')) . ($rv[1] ? ' · ★ ' . esc_html($rv[0]) : '') . '</div></div></summary><div class="pgc-body">';
        echo '<div class="pgc-kv"><span>' . esc_html(petgo_t('login_s')) . '</span><b>' . esc_html($u->user_login) . '</b></div>';
        if (petgo_real_email($u) !== '') {
            echo '<div class="pgc-kv"><span>' . esc_html(petgo_t('email')) . '</span><a href="mailto:' . esc_attr($u->user_email) . '">' . esc_html($u->user_email) . '</a></div>';
        }
        if ($ph !== '') {
            echo '<div class="pgc-kv"><span>' . esc_html(petgo_t('phone')) . '</span><a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $ph)) . '">' . esc_html($ph) . '</a></div>';
        }
        echo '<div class="pgc-mstat"><div><b>' . (int) $st['done'] . '</b><span>' . esc_html(petgo_t('done_cnt')) . '</span></div><div><b>' . (int) $st['on'] . '</b><span>' . esc_html(petgo_t('ontime_cnt')) . '</span></div><div><b>' . (int) $st['pend'] . '</b><span>' . esc_html(petgo_t('pend_s')) . '</span></div><div><b>' . esc_html($rv[1] ? $rv[0] : '—') . '</b><span>' . esc_html(petgo_t('rv_avg')) . '</span></div></div>';
        echo '<div class="pgc-btns">';
        if (petgo_is_owner()) {
            echo '<button type="button" class="pgc-btn sec pgc-dm" data-u="' . (int) $u->ID . '">' . petgo_ic('msg') . esc_html(petgo_t('c_write')) . '</button>';
        }
        if (petgo_can_manage($u)) {
            echo '<a class="pgc-btn sec" href="' . esc_url(petgo_page_url(array('pg' => 'staff', 'edit_staff' => $u->ID))) . '">' . petgo_ic('edit') . esc_html(petgo_t('edit')) . '</a>';
            echo petgo_btn_form('staff_del', array('uid' => $u->ID), petgo_t('delete'), 'pgc-btn red', petgo_t('q_delete_staff'), true);
        }
        echo '</div><details class="pgc-hist"><summary>' . esc_html(petgo_t('history')) . ' (' . count($st['list']) . ')</summary>';
        $ol = $st['list'];
        usort($ol, 'petgo_cmp_ts_desc');
        if (!$ol) {
            echo '<p class="mu">' . esc_html(petgo_t('no_history')) . '</p>';
        }
        $ol = array_slice($ol, 0, 10);
        petgo_prime($ol);
        foreach ($ol as $o) {
            echo petgo_order_card($o, true);
        }
        echo '</details></div></details>';
    }
}

/* ---------- сотрудник: активные заказы по порядку ---------- */
function petgo_render_nanny() {
    $f    = (isset($_GET['st']) && $_GET['st'] === 'done') ? 'done' : 'cur';
    $act  = array();
    $done = array();
    foreach (petgo_orders(array('numberposts' => 300, 'meta_key' => '_nanny', 'meta_value' => get_current_user_id())) as $o) {
        $st = petgo_o($o->ID, 'status');
        if ($st === 'assigned' || $st === 'arrived' || $st === 'picked') {
            $act[] = $o;
        } elseif ($st === 'done' || $st === 'confirmed') {
            $done[] = $o;
        }
    }
    usort($act, 'petgo_cmp_ts_asc');
    usort($done, 'petgo_cmp_ts_desc');
    $done_n = count($done);
    $done = array_slice($done, 0, 30);
    $list = $f === 'done' ? $done : $act;
    petgo_nanny_tiles($act, $done_n);
    petgo_prime($list);
    if (!$list) {
        echo petgo_empty('orders', petgo_t('no_orders'));
    }
    $n = 0;
    foreach ($list as $o) {
        $n++;
        echo petgo_order_card($o, false, $n);
    }
}

/* ============================================================
   9. НАСТРОЙКИ В АДМИНКЕ WORDPRESS: Настройки, PetGo Кабинет
   ============================================================ */
add_action('admin_menu', 'petgo_menu');
function petgo_menu() {
    add_options_page('PetGo Кабинет', 'PetGo Кабинет', 'manage_options', 'petgo-cabinet', 'petgo_settings_page');
}

function petgo_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_POST['petgo_wp_save']) && check_admin_referer('petgo_wp_set')) {
        update_option('petgo_trust_proxy', empty($_POST['trust_proxy']) ? 0 : 1, false);
        update_option('petgo_app_name', sanitize_text_field(wp_unslash(isset($_POST['app_name']) ? $_POST['app_name'] : '')), false);
        update_option('petgo_rv_auto', empty($_POST['rv_auto']) ? 0 : 1, false);
        petgo_oauth_settings_save();
        $pid = isset($_POST['page_id']) ? (int) $_POST['page_id'] : 0;
        if ($pid && get_post_type($pid) === 'page') {
            update_option('petgo_page_id', $pid);
        }
        echo '<div class="notice notice-success"><p>Сохранено</p></div>';
    }
    $exp = function ($type) {
        return esc_url(wp_nonce_url(admin_url('admin-post.php?action=petgo_export&type=' . $type), 'petgo_exp'));
    };
    echo '<div class="wrap"><h1>PetGo Кабинет ' . esc_html(PETGO_VER) . '</h1>';
    echo '<p>Кабинет: <a href="' . esc_url(petgo_page_url()) . '" target="_blank">' . esc_html(petgo_page_url()) . '</a> · шорткоды <code>[petgo_cabinet]</code>, <code>[petgo_request]</code>, <code>[petgo_reviews]</code></p>';
    echo '<p>На телефоне откройте кабинет и добавьте его на экран «Домой»: он работает как приложение, открывается мгновенно и показывает сохранённые данные без интернета. Уведомления приходят на телефон (нужен https).</p>';
    echo '<form method="post">' . wp_nonce_field('petgo_wp_set', '_wpnonce', true, false) . '<input type="hidden" name="petgo_wp_save" value="1"><table class="form-table">';
    echo '<tr><th>Страница кабинета</th><td>' . wp_dropdown_pages(array('name' => 'page_id', 'selected' => (int) petgo_opt('page_id'), 'echo' => 0)) . '</td></tr>';
    echo '<tr><th>Название приложения</th><td><input type="text" class="regular-text" name="app_name" value="' . esc_attr(petgo_app_name()) . '"><p class="description">Подпись под значком на экране телефона.</p></td></tr>';
    echo '<tr><th>Отзывы</th><td><label><input type="checkbox" name="rv_auto" value="1"' . checked((int) get_option('petgo_rv_auto'), 1, false) . '> Публиковать новые отзывы сразу, без проверки</label></td></tr>';
    echo '<tr><th>Прокси</th><td><label><input type="checkbox" name="trust_proxy" value="1"' . checked((int) get_option('petgo_trust_proxy'), 1, false) . '> Сайт работает через Cloudflare или другой прокси (брать настоящий IP посетителя)</label></td></tr>';
    echo petgo_oauth_settings_html();
    echo '</table>' . get_submit_button('Сохранить') . '</form>';
    echo '<h2>Экспорт</h2><p>';
    foreach (array('orders' => 'Заказы', 'requests' => 'Заявки', 'pets' => 'Питомцы', 'reviews' => 'Отзывы', 'log' => 'Журнал действий') as $k => $t) {
        echo '<a class="button" href="' . $exp($k) . '">' . esc_html($t) . ' (CSV)</a> ';
    }
    echo '<a class="button button-primary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=petgo_backup'), 'petgo_exp')) . '">Резервная копия (JSON)</a></p>';
    echo petgo_security_status_html() . '</div>';
}

function petgo_app_name() {
    $n = trim((string) get_option('petgo_app_name', ''));
    return $n !== '' ? $n : 'PetGo';
}

/* ============================================================
   10. МОДУЛИ: переводы, заявки с сайта, роли и чаты
   ============================================================ */
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/requests.php';
require_once __DIR__ . '/includes/chat.php';
require_once __DIR__ . '/includes/order.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/formeditor.php';
require_once __DIR__ . '/includes/reviews.php';
require_once __DIR__ . '/includes/journal.php';
require_once __DIR__ . '/includes/calendar.php';
require_once __DIR__ . '/includes/pwa.php';
require_once __DIR__ . '/includes/site.php';
require_once __DIR__ . '/includes/site-i18n.php';
require_once __DIR__ . '/includes/site-nannies.php';
require_once __DIR__ . '/includes/site-guard.php';
require_once __DIR__ . '/includes/site-admin.php';
require_once __DIR__ . '/includes/lead.php';
require_once __DIR__ . '/includes/front.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/petform.php';
require_once __DIR__ . '/includes/client.php';
require_once __DIR__ . '/includes/client-plus.php';
require_once __DIR__ . '/includes/track.php';
require_once __DIR__ . '/includes/i18n-v34.php';

/* ============================================================
   11. ЖИВОЕ ОБНОВЛЕНИЕ: «ревизия» данных меняется при любой правке, телефон раз в 20 секунд спрашивает одно число
   ============================================================ */
function petgo_bump() {
    static $q = false;
    if ($q) {
        return;
    }
    $q = true;
    add_action('shutdown', function () {
        update_option('petgo_rev', (string) microtime(true));
    });
}

function petgo_ver() {
    return get_option('petgo_rev', '0') . '|' . petgo_role_of();
}

function petgo_bump_meta($mid, $pid) {
    $t = get_post_type($pid);
    if ($t === 'petgo_order' || $t === 'petgo_pet') {
        petgo_bump();
    }
}
add_action('added_post_meta', 'petgo_bump_meta', 10, 2);
add_action('updated_post_meta', 'petgo_bump_meta', 10, 2);
add_action('deleted_post_meta', 'petgo_bump_meta', 10, 2);

function petgo_bump_post($pid) {
    $t = get_post_type($pid);
    if ($t === 'petgo_order' || $t === 'petgo_pet') {
        petgo_bump();
    }
}
add_action('trashed_post', 'petgo_bump_post');
add_action('deleted_post', 'petgo_bump_post');
add_action('set_user_role', 'petgo_bump');
add_action('user_register', 'petgo_bump');
add_action('deleted_user', 'petgo_bump');
add_action('profile_update', 'petgo_bump');
add_action('updated_user_meta', function ($mid, $uid, $key) {
    if (strpos((string) $key, 'petgo_') === 0) {
        petgo_bump();
    }
}, 10, 3);
add_action('added_user_meta', function ($mid, $uid, $key) {
    if (strpos((string) $key, 'petgo_') === 0) {
        petgo_bump();
    }
}, 10, 3);

/* ============================================================
   12. СЕССИЯ: вход помнится надолго без галочки, страница кабинета не кэшируется
   ============================================================ */
add_action('login_init', 'petgo_force_remember');
function petgo_force_remember() {
    if (empty($_POST['log'])) {
        return;
    }
    $l = sanitize_user(wp_unslash($_POST['log']), false);
    $u = get_user_by('login', $l);
    if (!$u && strpos($l, '@') !== false) {
        $u = get_user_by('email', $l);
    }
    if ($u && petgo_role_of($u) !== '') {
        $_POST['rememberme'] = 'forever';
    }
}

add_filter('auth_cookie_expiration', 'petgo_cookie_len', 10, 3);
function petgo_cookie_len($len, $uid, $remember) {
    $u = get_userdata((int) $uid);
    return ($u && petgo_role_of($u) !== '') ? PETGO_SESSION_DAYS * DAY_IN_SECONDS : $len;
}

add_action('template_redirect', 'petgo_no_cache_page', 0);
function petgo_no_cache_page() {
    if (petgo_is_cabinet_page()) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
        header('X-LiteSpeed-Cache-Control: no-cache');
        /* метка для приложения: эту страницу можно сохранить на телефоне (только для вошедшего сотрудника) */
        header('X-PetGo-App: ' . ((is_user_logged_in() && petgo_role_of() !== '') ? '1' : '0'));
    }
}

/* страница кабинета выводится своим шаблоном: без темы и чужих скриптов, поэтому открывается быстро */
add_filter('template_include', 'petgo_template', 99);
function petgo_template($t) {
    return petgo_is_cabinet_page() ? __DIR__ . '/includes/template.php' : $t;
}
