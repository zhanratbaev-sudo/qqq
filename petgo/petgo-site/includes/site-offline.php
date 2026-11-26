<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   БЕЗ ИНТЕРНЕТА И ПРИ МЕДЛЕННОМ ИНТЕРНЕТЕ (34.5)
   Service worker сайта (assets/site-sw.js): вместо белого экрана — сохранённая страница с плашкой
   или экран PetGo «Нет интернета» / «Загружаем…». Плюс общий «запуск» страницы: события PGEvent,
   метки рекламы (откуда пришёл клиент) и клики «Позвонить» / WhatsApp.
   ============================================================ */

/* service worker отдаётся с адреса сайта, чтобы работать на всех его страницах */
add_action('init', 'petgo_site_sw_serve', 1);
function petgo_site_sw_serve() {
    if (!isset($_GET['petgo_site_sw'])) {
        return;
    }
    $home  = home_url('/');
    $scope = (string) wp_parse_url($home, PHP_URL_PATH);
    $scope = $scope !== '' ? $scope : '/';
    $skip  = array();
    /* приложение сотрудников — со своим service worker; его страницы не трогаем */
    if (function_exists('petgo_page_url')) {
        $cu = petgo_page_url();
        $cp = (string) wp_parse_url($cu, PHP_URL_PATH);
        $cq = (string) wp_parse_url($cu, PHP_URL_QUERY);
        if ($cp !== '' && ($cp !== $scope || $cq !== '')) {
            $skip[] = array('p' => $cp, 'q' => $cq);
        }
    }
    $pre = array($home);
    foreach (array('site.css', 'glass.js', 'site.js', 'kb.js', 'ui.js', 'form.js', 'icon-192.png', 'apple-touch-icon.png') as $f) {
        $pre[] = petgo_asset_url($f);
    }
    if (defined('PETGO_CLIENT_FILE')) {
        $pre[] = petgo_asset_url('client.css');
        $pre[] = petgo_asset_url('client.js');
    }
    $pre = array_values(array_unique((array) apply_filters('petgo_site_sw_precache', $pre)));
    $tx  = array();
    foreach (array('ru', 'kz', 'en') as $l) {
        $old = petgo_site_lang();
        petgo_site_lang($l);
        $tx[$l] = array(
            'off_t' => petgo_site_l('Нет интернета'),
            'off_b' => petgo_site_l('Проверьте подключение. Страница откроется сама, как только связь вернётся.'),
            'retry' => petgo_site_l('Повторить'),
            'call'  => petgo_site_l('Позвонить'),
            'wait_t' => petgo_site_l('Загружаем…'),
            'wait_b' => petgo_site_l('Интернет медленный — ещё чуть-чуть.'),
        );
        petgo_site_lang($old);
    }
    $cfg = array(
        'v' => PETGO_SITE_VER . '.' . substr(md5(implode('|', $pre)), 0, 10), 'home' => $home, 'skip' => $skip, 'pre' => $pre,
        'wait' => 3500, 'tx' => $tx, 'phone' => petgo_site_phone(),
        /* 40.0: уведомления клиенту (заказ подтверждён, няня пришла…) — показывает service worker */
        'peek' => admin_url('admin-ajax.php'), 'app' => petgo_site_url('account'), 'icon' => petgo_asset_url('icon-192.png'),
    );
    $js = (string) @file_get_contents(dirname(__DIR__) . '/assets/site-sw.js');
    nocache_headers();
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: ' . $scope);
    header('X-Content-Type-Options: nosniff');
    echo 'var C=' . wp_json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n" . $js;
    exit;
}

/* адрес и область service worker — для ui.js (регистрирует его после загрузки страницы) */
function petgo_site_sw_cfg() {
    $home  = home_url('/');
    $scope = (string) wp_parse_url($home, PHP_URL_PATH);
    if (function_exists('petgo_page_url') && untrailingslashit(petgo_page_url()) === untrailingslashit($home)) {
        return null; /* приложение сотрудников стоит на главной — у него свой service worker */
    }
    return array('url' => petgo_rel_url(add_query_arg('petgo_site_sw', '1', $home)), 'scope' => $scope !== '' ? $scope : '/');
}

/* личные страницы (кабинет клиента, анкета по ссылке) не сохраняются в телефоне */
add_action('wp', 'petgo_site_private_headers', 3);
function petgo_site_private_headers() {
    $p = function_exists('petgo_site_page_now') ? petgo_site_page_now() : '';
    if ($p === 'account' || $p === 'join') {
        nocache_headers();
        header('X-PetGo-Private: 1');
    }
}

/* строки для скриптов сайта (плашка «Нет интернета») */
add_filter('petgo_site_js_strings', function ($a) {
    return array_merge($a, array('Нет интернета', 'Снова онлайн', 'Нет интернета — показана сохранённая страница', 'Медленный интернет — показана сохранённая страница', 'Обновить'));
});

add_filter('petgo_site_dict_more', function ($d) {
    return array_merge($d, array(
        'Нет интернета' => array('Интернет жоқ', 'No internet'),
        'Снова онлайн' => array('Байланыс қалпына келді', 'Back online'),
        'Нет интернета — показана сохранённая страница' => array('Интернет жоқ — сақталған бет көрсетілді', 'No internet — showing the saved page'),
        'Медленный интернет — показана сохранённая страница' => array('Интернет баяу — сақталған бет көрсетілді', 'Slow connection — showing the saved page'),
        'Обновить' => array('Жаңарту', 'Refresh'),
        'Проверьте подключение. Страница откроется сама, как только связь вернётся.' => array('Байланысты тексеріңіз. Байланыс қалпына келгенде бет өзі ашылады.', 'Check your connection. The page will open by itself once you’re back online.'),
        'Повторить' => array('Қайталау', 'Try again'),
        'Позвонить' => array('Қоңырау шалу', 'Call us'),
        'Загружаем…' => array('Жүктелуде…', 'Loading…'),
        'Интернет медленный — ещё чуть-чуть.' => array('Интернет баяу — сәл күтіңіз.', 'Slow connection — almost there.'),
    ));
});

/* ============================================================
   ЗАПУСК СТРАНИЦЫ — маленький скрипт прямо в <head>, до всех остальных:
   PGEvent (события для рекламы и плагинов), PGSrc (откуда пришёл клиент), клики «Позвонить» / WhatsApp.
   ============================================================ */
function petgo_site_boot_js() {
    return '<script>(function(W,D){'
        . 'var E=W.PGEvent=W.PGEvent||function(n,d){try{W.dispatchEvent(new CustomEvent("petgo:event",{detail:{name:n,data:d||{}}}))}catch(e){}};'
        . 'E.on=E.on||function(n,f){W.addEventListener("petgo:event",function(e){var x=e.detail||{};if(!n||n==="*"||x.name===n)f(x.data||{},x.name)})};'
        . 'try{var q=new URLSearchParams(location.search),x={},has=0,L=localStorage;'
        . '[["utm_source","s"],["utm_medium","m"],["utm_campaign","c"],["utm_term","t"],["utm_content","x"]].forEach(function(p){var v=q.get(p[0]);if(v){x[p[1]]=v.slice(0,120);has=1}});'
        . '[["gclid","g"],["gbraid","g"],["wbraid","g"],["yclid","y"],["fbclid","f"],["ttclid","k"]].forEach(function(p){if(q.get(p[0])){x[p[1]]=1;has=1}});'
        . 'var r="";try{r=D.referrer?new URL(D.referrer).hostname.replace(/^www\./,""):""}catch(e){}'
        . 'if(!has&&r&&r!==location.hostname.replace(/^www\./,"")){x.r=r;has=1}'
        . 'if(has){x.l=location.pathname.slice(0,80);x.ts=Date.now();var f1=JSON.parse(L.getItem("pg_src1")||"null");'
        . 'if(!f1||Date.now()-(f1.ts||0)>7776e6)L.setItem("pg_src1",JSON.stringify(x));L.setItem("pg_src2",JSON.stringify(x))}'
        . '}catch(e){}'
        . 'W.PGSrc=function(){try{var a=localStorage.getItem("pg_src2"),b=localStorage.getItem("pg_src1");return a||b?JSON.stringify({last:a?JSON.parse(a):null,first:b?JSON.parse(b):null}):""}catch(e){return""}};'
        . 'D.addEventListener("submit",function(e){var f=e.target,s=W.PGSrc();if(!s||!f||!f.querySelector||(f.method||"").toLowerCase()==="get")return;'
        . 'var i=f.querySelector(\'input[name="pg_src"]\');if(!i){i=D.createElement("input");i.type="hidden";i.name="pg_src";f.appendChild(i)}i.value=s},true);'
        . 'D.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a[href]");if(!a)return;var h=a.getAttribute("href")||"";'
        . 'if(/^tel:/.test(h))E("contact",{via:"phone"});else if(/wa\.me\/|api\.whatsapp\.com/.test(h))E("contact",{via:"whatsapp"})},true);'
        . '})(window,document);</script>';
}
