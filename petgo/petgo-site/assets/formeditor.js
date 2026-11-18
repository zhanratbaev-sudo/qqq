(function(){
var C=window.PGFE,root=document.getElementById('pgc-fe');
if(!C||!root)return;
function clone(o){return JSON.parse(JSON.stringify(o))}
var T=clone(C.c.t),S=clone(C.c.s),FL=clone(C.c.fields);
var frame=document.getElementById('pgc-fe-frame'),stage=document.getElementById('pgc-fe-stage'),pane=document.getElementById('pgc-fe-pane'),tabsEl=document.getElementById('pgc-fe-tabs'),bar=document.getElementById('pgc-fe-bar'),jsn=document.getElementById('pgc-fe-json'),form=document.getElementById('pgc-fe-form');
var doc=null,pf=null,pvMode=(S.theme==='dark')?'dark':'light',tab='f',dirty=false,saving=false;
function el(t,c,x){var e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e}
function mark(){if(!dirty){dirty=true;root.setAttribute('data-dirty','1')}}
function fit(){if(!doc||!doc.documentElement)return;frame.style.height='10px';var h=doc.documentElement.scrollHeight;frame.style.height=Math.max(280,h)+'px'}
function setB(name,val){if(!doc)return;[].slice.call(doc.querySelectorAll('[data-b="'+name+'"]')).forEach(function(n){n.textContent=val});fit()}
function mkBtn(txt,cls,fn){var b=el('button',cls||'pgc-btn sec',txt);b.type='button';b.addEventListener('click',fn);return b}
function lbl(text,node){var l=el('label','pgc-f');l.appendChild(el('span','',text));l.appendChild(node);return l}

/* ---------- предпросмотр ---------- */
function makeCustom(f){
var n=doc.createElement('div');n.setAttribute('data-custom','1');n.setAttribute('data-fld',f.id);
function rq(){var b=doc.createElement('b');b.className='rq';b.textContent=' *';return b}
function lab(){var l=doc.createElement('span');l.className='l';var a=doc.createElement('span');a.textContent=f.label;l.appendChild(a);l.appendChild(rq());return l}
var t=f.type,x;
if(t==='checkbox'){var lb=doc.createElement('label');lb.className='chk';x=doc.createElement('input');x.type='checkbox';var s=doc.createElement('span');s.textContent=f.label;lb.appendChild(x);lb.appendChild(s);lb.appendChild(rq());n.appendChild(lb);return n}
n.appendChild(lab());
if(t==='textarea'){x=doc.createElement('textarea');if(f.ph)x.placeholder=f.ph;n.appendChild(x)}
else if(t==='select'){x=doc.createElement('select');var o0=doc.createElement('option');o0.textContent='—';x.appendChild(o0);(f.opts||[]).forEach(function(v){var o=doc.createElement('option');o.textContent=v;x.appendChild(o)});n.appendChild(x)}
else if(t==='pills'){var pl=doc.createElement('div');pl.className='pills';(f.opts||[]).forEach(function(v){var lb2=doc.createElement('label');var i=doc.createElement('input');i.type='radio';i.name='p_'+f.id;var s2=doc.createElement('span');s2.textContent=v;lb2.appendChild(i);lb2.appendChild(s2);pl.appendChild(lb2)});n.appendChild(pl)}
else{x=doc.createElement('input');x.type=(t==='number')?'number':'text';if(f.ph)x.placeholder=f.ph;n.appendChild(x)}
return n}
function pv(){
if(!doc)return;var g=doc.querySelector('.g2');if(!g)return;
[].slice.call(g.querySelectorAll('[data-custom]')).forEach(function(n){g.removeChild(n)});
FL.forEach(function(f){var n;
if(C.bi[f.id]){n=g.querySelector('[data-fld="'+f.id+'"]');if(!n)return;var lb=n.querySelector('[data-b="lbl_'+f.id+'"]');if(lb)lb.textContent=f.label}
else n=makeCustom(f);
n.className='fld '+(f.w==='half'?'hw':'fw')+(f.req?' req':'');n.hidden=!f.on;g.appendChild(n)});
fit()}
function applyStyle(){
if(!pf)return;var st=pf.style,dm=pvMode==='dark',p=dm?'d_':'c_';
st.setProperty('--w',S.w+'px');st.setProperty('--pad',S.pad+'px');st.setProperty('--gap',S.gap+'px');st.setProperty('--rf',S.rf+'px');st.setProperty('--rfi',S.rfi+'px');st.setProperty('--rb',S.rb+'px');st.setProperty('--bw',S.bw+'px');st.setProperty('--fs',S.fs+'px');st.setProperty('--fsl',S.fsl+'px');st.setProperty('--fst',S.fst+'px');st.setProperty('--fwt',S.fwt);st.setProperty('--fh',S.fh+'px');st.setProperty('--bh',S.bh+'px');st.setProperty('--font',C.fonts[S.font]||'inherit');
['bg','tx','mu','ln','ac','ax','fbg','so'].forEach(function(k){st.setProperty('--'+k,S[p+k])});
var oth=pf.classList.contains('oth');
pf.className='pgf t-'+(dm?'dark':'light')+' b-'+S.btn+' p-'+S.pills+' a-'+S.align+' sh-'+S.shadow+(oth?' oth':'');
doc.body.style.background=dm?'#0b0b0d':'#e9e9ee';fit()}
function init(){
try{doc=frame.contentDocument}catch(e){doc=null}
if(!doc||!doc.querySelector)return;pf=doc.querySelector('.pgf');if(!pf)return;
doc.addEventListener('submit',function(e){e.preventDefault()});
pv();applyStyle()}
frame.addEventListener('load',init);
if(frame.contentDocument&&frame.contentDocument.readyState==='complete')init();

/* ---------- панель сверху над предпросмотром ---------- */
function drawBar(){
bar.textContent='';
var a=el('div','pgc-chips');a.style.margin='0';
[['light','Светлая'],['dark','Тёмная']].forEach(function(m){var b=el('button','pgc-chip'+(pvMode===m[0]?' on':''),m[1]);b.type='button';b.addEventListener('click',function(){pvMode=m[0];drawBar();applyStyle()});a.appendChild(b)});
var w=el('div','pgc-chips');w.style.margin='0';
[['360px','Телефон'],['600px','Планшет'],['100%','Шире']].forEach(function(m,i){var b=el('button','pgc-chip'+((stage.getAttribute('data-w')||'100%')===m[0]?' on':''),m[1]);b.type='button';b.addEventListener('click',function(){stage.setAttribute('data-w',m[0]);stage.style.maxWidth=m[0];drawBar();setTimeout(fit,320)});w.appendChild(b)});
bar.appendChild(a);bar.appendChild(w)}

/* ---------- вкладки ---------- */
var TABS=[['f','Поля'],['t','Тексты'],['c','Цвета'],['s','Размеры и стиль']];
function drawTabs(){
tabsEl.textContent='';
TABS.forEach(function(x){var b=el('button','pgc-chip'+(tab===x[0]?' on':''),x[1]);b.type='button';b.addEventListener('click',function(){tab=x[0];drawTabs();drawPane()});tabsEl.appendChild(b)})}
function drawPane(){
pane.textContent='';
if(tab==='t')paneT();else if(tab==='f')paneF();else if(tab==='c')paneC();else paneS()}

function paneT(){
var TF=[['title','Заголовок'],['text','Подзаголовок'],['btn','Текст кнопки'],['ok','Сообщение после отправки',1],['consent','Согласие на обработку данных',1],['other_label','Подпись «другое животное»'],['dog','Кнопка: собака'],['cat','Кнопка: кошка'],['other','Кнопка: другое'],['write','Связь: написать'],['call','Связь: позвонить']];
TF.forEach(function(x){var k=x[0],i=x[2]?el('textarea'):el('input');if(x[2])i.rows=2;else i.type='text';i.value=T[k]||'';
i.addEventListener('input',function(){T[k]=i.value;mark();setB('t_'+k,i.value)});
var w=lbl(x[1],i);w.style.marginBottom='10px';pane.appendChild(w)})}

function mv(i,d){var j=i+d;if(j<0||j>=FL.length)return;var t=FL[i];FL[i]=FL[j];FL[j]=t;mark();drawPane();pv()}
function paneF(){
pane.appendChild(el('p','mu','Порядок, названия и обязательность полей. Имя и телефон скрыть нельзя.'));
var list=el('div','pgc-fe-list');
FL.forEach(function(f,i){
var bi=C.bi[f.id],isC=!bi,it=el('div','pgc-fe-item');
var l1=el('div','pgc-fe-l1');
l1.appendChild(mkBtn('↑','pgc-fe-mv',function(){mv(i,-1)}));l1.appendChild(mkBtn('↓','pgc-fe-mv',function(){mv(i,1)}));
var ti=el('input');ti.type='text';ti.value=f.label;ti.maxLength=80;ti.addEventListener('input',function(){f.label=ti.value;mark();pv()});l1.appendChild(ti);
if(isC)l1.appendChild(mkBtn('×','pgc-fe-mv red',function(){FL.splice(i,1);mark();drawPane();pv()}));
it.appendChild(l1);
var l2=el('div','pgc-fe-l2');
var on=el('label','pgc-fe-ck'),oc=el('input');oc.type='checkbox';oc.checked=!!f.on;if(bi&&bi.hide)oc.disabled=true;oc.addEventListener('change',function(){f.on=oc.checked?1:0;mark();pv()});on.appendChild(oc);on.appendChild(el('span','','Показывать'));l2.appendChild(on);
var rq=el('label','pgc-fe-ck'),rc=el('input');rc.type='checkbox';rc.checked=!!f.req;if(bi&&bi.reqlock)rc.disabled=true;rc.addEventListener('change',function(){f.req=rc.checked?1:0;mark();pv()});rq.appendChild(rc);rq.appendChild(el('span','','Обязательное'));l2.appendChild(rq);
var ws=el('select');[['full','Вся строка'],['half','Половина']].forEach(function(o){var op=el('option','',o[1]);op.value=o[0];if(f.w===o[0])op.selected=true;ws.appendChild(op)});ws.addEventListener('change',function(){f.w=ws.value;mark();pv()});l2.appendChild(ws);
if(isC)l2.appendChild(el('span','pgc-badge',C.types[f.type]||f.type));
it.appendChild(l2);
if(isC&&(f.type==='select'||f.type==='pills')){var ta=el('textarea');ta.rows=3;ta.value=(f.opts||[]).join('\n');ta.placeholder='Каждый вариант с новой строки';ta.addEventListener('input',function(){f.opts=ta.value.split('\n').map(function(s){return s.trim()}).filter(Boolean).slice(0,12);mark();pv()});it.appendChild(lbl('Варианты',ta))}
if(isC&&(f.type==='text'||f.type==='textarea'||f.type==='number')){var ph=el('input');ph.type='text';ph.maxLength=80;ph.value=f.ph||'';ph.addEventListener('input',function(){f.ph=ph.value;mark();pv()});it.appendChild(lbl('Подсказка в поле',ph))}
list.appendChild(it)});
pane.appendChild(list);
var add=el('div','pgc-fe-add');var sel=el('select');Object.keys(C.types).forEach(function(k){var o=el('option','',C.types[k]);o.value=k;sel.appendChild(o)});
add.appendChild(sel);
add.appendChild(mkBtn('+ Добавить поле','pgc-btn',function(){var n=0;FL.forEach(function(f){if(!C.bi[f.id])n++});if(n>=12){alert('Не больше 12 своих полей');return}var t=sel.value;FL.push({id:'c'+Math.random().toString(36).slice(2,8),type:t,on:1,label:'Новое поле',req:0,w:'full',opts:(t==='select'||t==='pills')?['Вариант 1','Вариант 2']:[],ph:''});mark();drawPane();pv()}));
pane.appendChild(add)}

var CN=[['bg','Фон формы'],['tx','Текст'],['mu','Подписи'],['ln','Линии и рамки'],['ac','Акцент (кнопки)'],['ax','Текст на акценте'],['fbg','Фон полей'],['so','Фон сообщений']];
var PRE=[
{n:'Как на сайте',s:null},
{n:'Тёмный синий',s:{theme:'dark',rf:26,rfi:14,rb:14,btn:'solid',shadow:'strong',pills:'fill',c_bg:'#f3f8ff',c_tx:'#0b1220',c_mu:'#5d6b82',c_ln:'#d5e2f1',c_ac:'#0a84ff',c_ax:'#ffffff',c_fbg:'#ffffff',c_so:'#e6f1ff',d_bg:'#0b1a2b',d_tx:'#ffffff',d_mu:'#9db3c9',d_ln:'#2a3f57',d_ac:'#0a84ff',d_ax:'#ffffff',d_fbg:'#12263b',d_so:'#10253a'}},
{n:'Светлый минимум',s:{theme:'light',rf:8,rfi:8,rb:8,btn:'solid',shadow:'none',pills:'outline',c_bg:'#ffffff',c_tx:'#111111',c_mu:'#6b6b6b',c_ln:'#e5e5e5',c_ac:'#111111',c_ax:'#ffffff',c_fbg:'#fafafa',c_so:'#f4f4f4'}},
{n:'Мягкий зелёный',s:{theme:'light',rf:22,rfi:14,rb:40,btn:'solid',shadow:'soft',pills:'fill',c_bg:'#f4faf6',c_tx:'#12301f',c_mu:'#5d7a69',c_ln:'#d3e8da',c_ac:'#1f9d57',c_ax:'#ffffff',c_fbg:'#ffffff',c_so:'#e6f5ec'}},
{n:'Закат',s:{theme:'light',rf:22,rfi:14,rb:16,btn:'solid',shadow:'soft',pills:'fill',c_bg:'#fffaf5',c_tx:'#2a1a12',c_mu:'#8a6a58',c_ln:'#f1ddcf',c_ac:'#ff6a3d',c_ax:'#ffffff',c_fbg:'#ffffff',c_so:'#fff0e4'}},
{n:'Контраст',s:{theme:'dark',rf:6,rfi:6,rb:6,bw:2,btn:'solid',shadow:'none',pills:'fill',d_bg:'#000000',d_tx:'#ffffff',d_mu:'#bdbdbd',d_ln:'#3a3a3a',d_ac:'#ffd60a',d_ax:'#000000',d_fbg:'#0d0d0d',d_so:'#151515',c_bg:'#ffffff',c_tx:'#000000',c_mu:'#4a4a4a',c_ln:'#111111',c_ac:'#000000',c_ax:'#ffd60a',c_fbg:'#ffffff',c_so:'#f5f5f5'}}];
function applyPreset(p){
var base=p.s?p.s:clone(C.d.s);
Object.keys(base).forEach(function(k){S[k]=base[k]});
if(S.theme==='dark')pvMode='dark';else if(S.theme==='light')pvMode='light';
mark();drawBar();drawPane();applyStyle()}
function paneC(){
pane.appendChild(el('h3','','Готовые стили'));
var pc=el('div','pgc-chips');PRE.forEach(function(p){var b=el('button','pgc-chip',p.n);b.type='button';b.addEventListener('click',function(){applyPreset(p)});pc.appendChild(b)});pane.appendChild(pc);
var th=el('select');[['auto','Как у посетителя (авто)'],['light','Всегда светлая'],['dark','Всегда тёмная']].forEach(function(o){var op=el('option','',o[1]);op.value=o[0];if(S.theme===o[0])op.selected=true;th.appendChild(op)});
th.addEventListener('change',function(){S.theme=th.value;if(S.theme!=='auto'){pvMode=S.theme;drawBar()}mark();applyStyle()});
pane.appendChild(lbl('Тема формы на сайте',th));
[['c_','Светлая тема'],['d_','Тёмная тема']].forEach(function(g){
pane.appendChild(el('h3','',g[1]));var grid=el('div','pgc-fe-cols');
CN.forEach(function(x){var k=g[0]+x[0],i=el('input');i.type='color';i.value=S[k];i.addEventListener('input',function(){S[k]=i.value;mark();if(g[0]==='d_'&&pvMode!=='dark'){pvMode='dark';drawBar()}if(g[0]==='c_'&&pvMode!=='light'){pvMode='light';drawBar()}applyStyle()});grid.appendChild(lbl(x[1],i))});
pane.appendChild(grid)})}

var SR=[['w','Ширина формы',280,900,10],['pad','Внутренний отступ',8,64,1],['gap','Расстояние между полями',6,32,1],['rf','Скругление формы',0,48,1],['rfi','Скругление полей',0,32,1],['rb','Скругление кнопки',0,40,1],['bw','Толщина рамки',0,4,1],['fs','Размер текста',12,22,1],['fsl','Размер подписей',11,18,1],['fst','Размер заголовка',18,48,1],['fh','Высота полей',36,64,1],['bh','Высота кнопки',36,72,1]];
function selBox(key,name,opts){var s=el('select');opts.forEach(function(o){var op=el('option','',o[1]);op.value=o[0];if(String(S[key])===String(o[0]))op.selected=true;s.appendChild(op)});s.addEventListener('change',function(){S[key]=(key==='fwt')?parseInt(s.value,10):s.value;mark();applyStyle()});return lbl(name,s)}
function paneS(){
SR.forEach(function(x){var k=x[0],w=el('div','pgc-fe-rg'),h=el('div','pgc-fe-rh'),v=el('b','',S[k]+' px'),r=el('input');h.appendChild(el('span','',x[1]));h.appendChild(v);r.type='range';r.min=x[2];r.max=x[3];r.step=x[4];r.value=S[k];r.addEventListener('input',function(){S[k]=parseInt(r.value,10);v.textContent=S[k]+' px';mark();applyStyle()});w.appendChild(h);w.appendChild(r);pane.appendChild(w)});
var fo=Object.keys(C.fontNames).map(function(k){return [k,C.fontNames[k]]});
pane.appendChild(selBox('font','Шрифт',fo));
pane.appendChild(selBox('fwt','Жирность заголовка',[[500,'Обычная'],[600,'Средняя'],[700,'Жирная'],[800,'Очень жирная'],[900,'Максимум']]));
pane.appendChild(selBox('align','Заголовок',[['left','Слева'],['center','По центру']]));
pane.appendChild(selBox('btn','Кнопка отправки',[['solid','Заливка'],['outline','Контур']]));
pane.appendChild(selBox('pills','Выбранная кнопка выбора',[['fill','Заливка'],['outline','Контур']]));
pane.appendChild(selBox('shadow','Тень формы',[['none','Нет'],['soft','Мягкая'],['strong','Сильная']]));
}

form.addEventListener('submit',function(){saving=true;jsn.value=JSON.stringify({t:T,s:S,fields:FL})});
form.addEventListener('keydown',function(e){if(e.key==='Enter'&&e.target&&e.target.tagName==='INPUT')e.preventDefault()});
window.addEventListener('beforeunload',function(e){if(dirty&&!saving){e.preventDefault();e.returnValue=''}});
drawTabs();drawPane();drawBar();stage.setAttribute('data-w','100%');
})();
