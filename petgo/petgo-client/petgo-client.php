<?php
/**
 * Plugin Name: PetGo Клиент
 * Description: Кабинет клиента на сайте PetGo: вход и регистрация, питомцы, заказ выгула и визитов, карта прогулки, отчёты нянь, отзывы, чат с менеджером и помощником. Своё оформление (стиль iPhone в цветах PetGo, светлая и тёмная тема). Нужны «PetGo Кабинет» и «PetGo Сайт».
 * Version: 40.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: web.for made
 */
if (!defined('ABSPATH')) {
    exit;
}

define('PETGO_CLIENT_FILE', __FILE__);
define('PETGO_CLIENT_VER', '40.0.0');

add_action('plugins_loaded', function () {
    if (!defined('PETGO_VER') || !defined('PETGO_SITE_FILE')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>«PetGo Клиент» работает вместе с плагинами «PetGo Кабинет» и «PetGo Сайт». Включите их в разделе «Плагины».</p></div>';
        });
        return;
    }
    if (function_exists('petgo_register_plugin')) {
        petgo_register_plugin(PETGO_CLIENT_FILE, array('name' => 'PetGo Клиент', 'version' => PETGO_CLIENT_VER));
    } else {
        petgo_asset_root(PETGO_CLIENT_FILE);
    }
    foreach (array('client', 'client-plus', 'client-dict', 'ui', 'contract', 'app38', 'testweek', 'app394', 'notify40') as $f) {
        require_once __DIR__ . '/includes/' . $f . '.php';
    }
    /* своё оформление кабинета: стили в <head>, скрипт — после скриптов сайта */
    add_action('petgo_site_head', 'petgo_cl_head');
    add_action('petgo_site_foot', 'petgo_cl_foot');
}, 20);

function petgo_cl_head() {
    echo '<link rel="stylesheet" href="' . esc_url(petgo_asset_url('client.css')) . '">';
    /* 35.0.2: настоящая видимая высота экрана (--app-h) — листы и кабинет не длиннее экрана на любом телефоне */
    echo '<script>(function(){var d=document.documentElement,w=window;function s(){var h=w.innerHeight||d.clientHeight;if(h)d.style.setProperty("--app-h",h+"px")}s();w.addEventListener("resize",s);w.addEventListener("orientationchange",function(){setTimeout(s,250)});w.addEventListener("pageshow",s)})();</script>';
    if (!function_exists('petgo_site_page_now') || petgo_site_page_now() !== 'account') {
        return;
    }
    /* 36.1: кабинет — приложение «My PetGo»: короткое имя вкладки и ярлыка на экране «Домой» */
    echo '<meta name="apple-mobile-web-app-title" content="My PetGo"><meta name="application-name" content="My PetGo">';
    /* тёмная или светлая тема кабинета включается до отрисовки страницы — без мигания;
       36.1: и на экране входа (гость) — он открывается отдельно от сайта, в теме телефона */
    $gate = !is_user_logged_in() && function_exists('petgo_cl_gate');
    if (petgo_cl_uid() || $gate) {
        echo '<script>(function(){var d=document.documentElement,t="auto";try{t=localStorage.getItem("pgk_theme")||"auto"}catch(e){}d.classList.add("pgk-on"' . ($gate ? ',"pgk-gate-on"' : '') . ');if(t==="dark"||(t==="auto"&&window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches)){d.classList.add("pgk-dark");var m=document.querySelector(\'meta[name="theme-color"]\');if(m)m.setAttribute("content","#000000")}})();</script>';
    }
    /* 39.3: экран входа — следующий вход покажет приветствие */
    if ($gate) {
        echo '<script>try{sessionStorage.removeItem("pgk_hi")}catch(e){}</script>';
    }
    if (petgo_cl_uid()) {
        echo petgo_cl_boot_html();
    }
}

/* ============================================================
   39.3 ЗАСТАВКА ПРИЛОЖЕНИЯ И ЭКРАН ЗАГРУЗКИ
   Открыли приложение (или только что вошли) — на 2–3 секунды заставка в теме клиента (тёмная или светлая):
   логотип PetGo наверху, приветствие по времени суток с именем, затем «My PetGo»; потом плавно — приложение.
   Обновили приложение (потянули вниз, отправили заказ) — пустой экран в цвете темы с кружком, пока приложение
   не готово, без мелькания. Заставка встаёт до первой отрисовки страницы (скрипт в <head>), убирает её client.js.
   Один раз за запуск: в той же вкладке повторно не показывается; после выхода и нового входа — снова.
   ============================================================ */
function petgo_cl_boot_html() {
    $u     = wp_get_current_user();
    $first = trim((string) strtok(trim($u->display_name), ' '));
    $L     = 'petgo_site_l';
    /* 39.4: наверху — знак PetGo (лапка в круге) и «My PetGo»; ниже — приветствие, погода и подсказка дня */
    $logo  = petgo_site_logo();
    $mark  = preg_match('~<circle[^>]*/>\s*<g transform="translate\(0 4\)"[^>]*>.*?</g>~s', $logo, $m) ? '<svg viewBox="0 0 64 72" aria-hidden="true">' . str_replace('fill="#024C46"', 'class="pgb-c"', $m[0]) . '</svg>' : '';
    $wx    = function_exists('petgo_wx_tip') ? petgo_wx_tip((int) $u->ID) : null;
    $data  = array(
        'name' => $first,
        'hi'   => array($L('Доброе утро,'), $L('Добрый день,'), $L('Добрый вечер,'), $L('Доброй ночи,')),
        'w'    => $L('Добро пожаловать'),
        'mark' => $mark,
        'wx'   => $wx ? array('line' => $wx['temp'] . ' · ' . $L('Алматы') . ' · ' . $wx['desc'], 'tip' => $wx['tip']) : array('line' => '', 'tip' => $L('Лучший уход для вашего питомца')),
    );
    $css = '.pgk-boot{position:fixed;top:0;right:0;bottom:0;left:0;z-index:2147483000;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#F4F6F3;color:#0F2A26;font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;-webkit-font-smoothing:antialiased;transition:opacity 320ms cubic-bezier(.4,0,1,1)}'
        . 'html.pgk-dark .pgk-boot{background:#0B0D0C;color:#F1F4F2}'
        . 'html.pgk-boot-on,html.pgk-boot-on body{background:#F4F6F3}html.pgk-dark.pgk-boot-on,html.pgk-dark.pgk-boot-on body{background:#0B0D0C}'
        . '.pgk-boot.out{opacity:0;pointer-events:none}.pgk-boot.in{opacity:0;transition:opacity 180ms ease-out}.pgk-boot.in.show{opacity:1}'
        . '.pgk-boot-sp{display:block;width:34px;height:34px;border-radius:50%;border:3px solid rgba(2,76,70,.15);border-top-color:#024C46;animation:pgkBootSp .8s linear infinite}'
        . 'html.pgk-dark .pgk-boot-sp{border-color:rgba(255,255,255,.14);border-top-color:#4CC38A}'
        . '.pgk-boot-top{position:absolute;top:calc(max(env(safe-area-inset-top,0px),var(--safe-area-inset-top,0px)) + 58px);left:0;right:0;display:flex;align-items:center;justify-content:center;gap:10px;font-size:24px;font-weight:800;letter-spacing:-.02em;color:#024C46;animation:pgkBootIn 520ms cubic-bezier(.32,.72,0,1) both}'
        . '.pgk-boot-top svg{display:block;width:40px;height:45px}.pgb-c{fill:#024C46}html.pgk-dark .pgb-c{fill:#0E7A64}html.pgk-dark .pgk-boot-top{color:#F1F4F2}'
        . '.pgk-boot-hi{display:flex;flex-direction:column;align-items:center;gap:6px;padding:0 28px;text-align:center}'
        . '.pgk-boot-hi span{font-size:21px;color:#6B7A77;animation:pgkBootIn 560ms 150ms cubic-bezier(.32,.72,0,1) both}html.pgk-dark .pgk-boot-hi span{color:#8D9893}'
        . '.pgk-boot-hi b{font-size:38px;font-weight:800;line-height:1.1;letter-spacing:-.02em;animation:pgkBootIn 560ms 260ms cubic-bezier(.32,.72,0,1) both}'
        . '.pgk-boot-wx{display:flex;flex-direction:column;align-items:center;gap:12px;margin-top:26px;padding:0 32px;text-align:center;animation:pgkBootIn 560ms 850ms cubic-bezier(.32,.72,0,1) both}'
        . '.pgk-boot-wx em{font-style:normal;padding:8px 16px;border-radius:999px;background:rgba(2,76,70,.08);color:#024C46;font-size:16px;font-weight:700}html.pgk-dark .pgk-boot-wx em{background:rgba(255,255,255,.08);color:#4CC38A}'
        . '.pgk-boot-wx p{margin:0;max-width:320px;font-size:18px;line-height:1.4;color:#33504B}html.pgk-dark .pgk-boot-wx p{color:#C9D3CF}'
        . '.pgk-boot-wait{position:absolute;bottom:calc(max(env(safe-area-inset-bottom,0px),var(--safe-area-inset-bottom,0px)) + 56px);opacity:0;transition:opacity 300ms}.pgk-boot.wait .pgk-boot-wait{opacity:1}.pgk-boot-wait .pgk-boot-sp{width:24px;height:24px;border-width:2.5px}'
        . '@keyframes pgkBootSp{to{transform:rotate(360deg)}}@keyframes pgkBootIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}'
        . '@media (prefers-reduced-motion:reduce){.pgk-boot *{animation-delay:0ms!important;animation-duration:1ms!important}.pgk-boot-sp{animation-duration:1.6s!important}}';
    $js = <<<'JS'
(function(W,D){var C=__DATA__,mode="",de=D.documentElement;
try{var s=sessionStorage;if(s.getItem("pgk_reload")){mode="reload";s.removeItem("pgk_reload")}else if(!s.getItem("pgk_hi")){mode="hi";s.setItem("pgk_hi","1")}}catch(e){}
var cbs=[],B=W.PGKBoot={mode:mode,gone:!mode,after:function(f){if(B.gone)f();else cbs.push(f)},done:function(){}};if(!mode)return;
de.classList.add("pgk-boot-on");var t0=Date.now(),el=null,fin=false,ready=false;
function esc(x){return String(x).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]})}
function make(){if(el||!D.body)return;el=D.getElementById("pgk-boot");if(el)return;el=D.createElement("div");el.id="pgk-boot";el.className="pgk-boot "+mode;el.setAttribute("aria-hidden","true");
if(mode==="hi"){var h=new Date().getHours(),g=C.name?C.hi[h>=5&&h<12?0:h>=12&&h<17?1:h>=17&&h<23?2:3]:C.w;
el.innerHTML='<div class="pgk-boot-top">'+C.mark+'<span>My PetGo</span></div><div class="pgk-boot-hi"><span>'+esc(g)+'</span>'+(C.name?'<b>'+esc(C.name)+'</b>':'')+'</div><div class="pgk-boot-wx">'+(C.wx.line?'<em>'+esc(C.wx.line)+'</em>':'')+(C.wx.tip?'<p>'+esc(C.wx.tip)+'</p>':'')+'</div><div class="pgk-boot-wait"><i class="pgk-boot-sp"></i></div>'}
else el.innerHTML='<i class="pgk-boot-sp"></i>';D.body.insertBefore(el,D.body.firstChild)}
if(D.body)make();else{var mo=new MutationObserver(function(){if(D.body){make();mo.disconnect()}});mo.observe(de,{childList:true})}
var MIN=mode==="hi"?2500:300;setTimeout(function(){if(!ready&&el)el.classList.add("wait")},MIN);
function hide(){if(fin)return;fin=true;make();de.classList.remove("pgk-boot-on");if(el){el.classList.add("out");setTimeout(function(){if(el&&el.parentNode)el.parentNode.removeChild(el)},360)}B.gone=true;var l=cbs;cbs=[];l.forEach(function(f){try{f()}catch(e){}})}
B.done=function(){if(ready)return;ready=true;setTimeout(hide,Math.max(0,MIN-(Date.now()-t0)))};
setTimeout(function(){ready=true;hide()},12000)
})(window,document);
JS;
    $js = str_replace('__DATA__', wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $js);
    return '<style>' . $css . '</style><script>' . $js . '</script>';
}

/* 36.1: имя страницы кабинета — «My PetGo» (без длинного «Кабинет — название сайта») */
add_filter('petgo_site_seo_title', function ($t, $page) {
    return $page === 'account' ? 'My PetGo' : $t;
}, 50, 2);

function petgo_cl_foot() {
    echo '<script src="' . esc_url(petgo_asset_url('client.js')) . '" defer></script>';
}
