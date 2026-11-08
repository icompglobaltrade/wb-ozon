<?php
/*
 * iComp: панель руководителя — кто из сотрудников что сделал в ботах (08.11.2026).
 * POST (заголовок X-Icomp-Token, как у relay) — Mac присылает журнал и то, что ждёт сейчас.
 * GET ?k=<ключ> — страница. Ключ хранится только как sha256.
 */
define('NO_KEEP_STATISTIC', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$dataFile = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/icomp_boss_data.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	header('Content-Type: application/json; charset=utf-8');
	$auth = include $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/icomp_tg_config.php';
	$given = (string)($_SERVER['HTTP_X_ICOMP_TOKEN'] ?? '');
	if (!is_array($auth) || empty($auth['token']) || $given === '' || !hash_equals((string)$auth['token'], $given)) {
		http_response_code(403);
		die('{"ok":false}');
	}
	$d = json_decode((string)file_get_contents('php://input'), true);
	if (!is_array($d) || empty($d['key'])) {
		http_response_code(400);
		die('{"ok":false}');
	}
	$d['received'] = time();
	file_put_contents($dataFile, '<?php return ' . var_export($d, true) . ';', LOCK_EX);
	die('{"ok":true}');
}
$d = file_exists($dataFile) ? include $dataFile : null;
$k = (string)($_GET['k'] ?? '');
if (!is_array($d) || $k === '' || !hash_equals((string)$d['key'], hash('sha256', $k))) {
	http_response_code(404);
	die('Not found');
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$json = json_encode(['j' => $d['journal'] ?? [], 'p' => $d['pending'] ?? [], 'team' => $d['team'] ?? [], 'gen' => $d['generated'] ?? 0, 'o' => $d['orders'] ?? [], 'ph' => $d['prices'] ?? []],
	JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Кабинет руководителя · iComp</title>
<style>
:root{--bg:#f4f3ef;--card:#fff;--tx:#1d1d1b;--mu:#76746d;--ln:#e6e4dd;--ac:#c6f432;--red:#c0392b;--grn:#2e7d32;--yel:#a86b00}
@media (prefers-color-scheme:dark){:root{--bg:#161615;--card:#212120;--tx:#ecebe6;--mu:#9b9a93;--ln:#33322f;--red:#ff7a6b;--grn:#7bd389;--yel:#f0b84a}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--tx);font:15px/1.45 -apple-system,Segoe UI,Roboto,Arial,sans-serif}
.w{max-width:1200px;margin:0 auto;padding:14px 14px 40px}h1{font-size:21px;margin:0}h2{font-size:16px;margin:20px 0 10px}
.top{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between}.mu{color:var(--mu)}
.warn{background:#fdecea;color:#a2281b;border-radius:10px;padding:8px 12px;margin-top:10px}
.tabs{display:flex;gap:6px;overflow-x:auto;margin:14px 0 4px;padding-bottom:4px}.tab{white-space:nowrap;border:1px solid var(--ln);background:var(--card);border-radius:10px;padding:8px 14px;cursor:pointer;font-size:15px;color:var(--tx)}
.tab.on{background:var(--ac);border-color:var(--ac);color:#1b2400;font-weight:600}.tab .n{display:inline-block;min-width:20px;margin-left:6px;padding:0 6px;border-radius:9px;background:var(--red);color:#fff;font-size:12px;text-align:center}
.chips{display:flex;flex-wrap:wrap;gap:6px;align-items:center}.chip{border:1px solid var(--ln);background:var(--card);border-radius:999px;padding:4px 11px;cursor:pointer;font-size:13px;color:var(--tx)}
.chip.on{background:var(--tx);color:var(--bg);border-color:var(--tx)}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px}
.card{background:var(--card);border:1px solid var(--ln);border-radius:14px;padding:12px 14px}
.big{font-size:24px;font-weight:700;margin-top:2px}.row{display:flex;justify-content:space-between;gap:8px;font-size:14px;padding:2px 0}
.bad{color:var(--red)}.ok{color:var(--grn)}.yel{color:var(--yel)}
table{width:100%;border-collapse:collapse;background:var(--card);font-size:14px}
th,td{text-align:left;padding:7px 9px;border-bottom:1px solid var(--ln);vertical-align:top}th{color:var(--mu);font-weight:500;font-size:12px;white-space:nowrap}
td.r,th.r{text-align:right;white-space:nowrap}.tw{overflow-x:auto;border:1px solid var(--ln);border-radius:14px}
select,input{font:inherit;padding:5px 8px;border-radius:8px;border:1px solid var(--ln);background:var(--card);color:var(--tx)}
.q{background:var(--card);border:1px solid var(--ln);border-radius:12px;padding:10px 12px;margin-bottom:8px}.q .d{border-left:3px solid var(--ac);padding-left:8px;margin-top:6px}
</style></head><body><div class="w">
<div class="top"><div><h1>Кабинет руководителя</h1><div class="mu" id="upd"></div></div><div class="chips" id="per"></div></div>
<div id="stale"></div>
<div class="tabs" id="tabs"></div>
<div id="body"></div>
</div>
<script>
const D=<?= $json ?>;
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const t2=ts=>new Date(ts*1000).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
const rub=x=>x==null||isNaN(x)?'—':Math.round(x).toLocaleString('ru-RU')+' ₽';
const dur=s=>s==null?'':s<60?Math.round(s)+' с':s<3600?Math.round(s/60)+' мин':s<86400?(s/3600).toFixed(1).replace('.',',')+' ч':Math.round(s/86400)+' дн';
const MK={wb:'WB',ym:'Маркет',av:'Авито'};
const GN={open:['на сборке','yel'],shipped:['в доставке',''],delivered:['получен','ok'],returned:['возврат','bad'],cancelled:['отменён','bad']};
let tab='over',per='today',fw='',fb='',fmp='',q='';
function rng(){const d=new Date();d.setHours(0,0,0,0);const t=d.getTime()/1000;return per==='today'?[t,1e12]:per==='yday'?[t-86400,t]:per==='7'?[t-6*86400,1e12]:per==='30'?[t-29*86400,1e12]:[t-91*86400,1e12];}
const inR=ts=>{const [a,b]=rng();return ts>=a&&ts<b;};
const P=D.p||{}, O=(D.o||[]), J=(D.j||[]);
function sum(list){let s={n:0,sale:0,cost:0,profit:0,nc:0};list.forEach(o=>{if(o.g==='cancelled')return;s.n++;s.sale+=o.sale||0;if(o.cost!=null){s.cost+=o.cost;s.profit+=o.profit||0;s.nc++;}});return s;}
function over(){
 const L=O.filter(o=>inR(o.ts)), s=sum(L), canc=L.filter(o=>o.g==='cancelled').length;
 const by={};L.filter(o=>o.g!=='cancelled').forEach(o=>{(by[o.mp]=by[o.mp]||[]).push(o)});
 let h='<div class="grid">'+
 '<div class="card"><div class="mu">Заказов</div><div class="big">'+s.n+'</div><div class="mu">'+(canc?'отмен: '+canc:'без отмен')+'</div></div>'+
 '<div class="card"><div class="mu">Продажи (наша цена)</div><div class="big">'+rub(s.sale)+'</div></div>'+
 '<div class="card"><div class="mu">Закуп</div><div class="big">'+rub(s.cost)+'</div><div class="mu">по прайсам на момент заказа</div></div>'+
 '<div class="card"><div class="mu">Прибыль</div><div class="big ok">'+rub(s.profit)+'</div><div class="mu">'+(s.sale?'маржа '+(s.profit/s.sale*100).toFixed(1).replace('.',',')+'%':'')+'</div></div></div>';
 h+='<h2>По площадкам</h2><div class="grid">'+(Object.keys(by).length?Object.entries(by).map(([mp,l])=>{const x=sum(l);return '<div class="card"><b>'+esc(mp)+'</b><div class="row"><span>заказов</span><b>'+x.n+'</b></div><div class="row"><span>продажи</span><b>'+rub(x.sale)+'</b></div><div class="row"><span>прибыль</span><b class="ok">'+rub(x.profit)+'</b></div></div>'}).join(''):'<div class="card mu">Заказов за период нет.</div>')+'</div>';
 h+='<h2>Ждёт сейчас</h2><div class="grid">'+
 '<div class="card" data-go="buy"><div class="mu">Покупатели ждут ответа</div><div class="big '+(P.buyers?'bad':'ok')+'">'+(P.buyers||0)+'</div></div>'+
 '<div class="card" data-go="pr"><div class="mu">Цены на подтверждение</div><div class="big '+(P.prices?'bad':'ok')+'">'+(P.prices||0)+'</div></div>'+
 '<div class="card" data-go="ord"><div class="mu">На сборке</div><div class="big">'+(P.pack_open||0)+'</div><div class="row"><span>не собрано</span><b class="'+(P.pack_notpacked?'bad':'')+'">'+(P.pack_notpacked||0)+'</b></div><div class="row"><span>без фото</span><b class="'+(P.pack_nophoto?'bad':'')+'">'+(P.pack_nophoto||0)+'</b></div></div></div>';
 h+='<h2>Команда за период</h2>'+team(true);
 return h;
}
function orders(){
 const mps=[...new Set(O.map(o=>o.mp))];
 let L=O.filter(o=>inR(o.ts)&&(!fmp||o.mp===fmp)&&(!q||(o.name+' '+o.id+' '+o.sku).toLowerCase().includes(q.toLowerCase()))).sort((a,b)=>b.ts-a.ts);
 const s=sum(L);
 let h='<div class="chips" style="margin-bottom:10px"><select id="fmp"><option value="">Все площадки</option>'+mps.map(m=>'<option'+(m===fmp?' selected':'')+'>'+esc(m)+'</option>').join('')+'</select><input id="q" placeholder="Поиск: товар, № заказа" value="'+esc(q)+'"></div>';
 h+='<div class="tw"><table><thead><tr><th>Дата</th><th>Площадка · №</th><th>Товар</th><th class="r">Продажа</th><th class="r">Закуп</th><th>Где купить</th><th class="r">Прибыль</th><th>Статус</th><th>Собрал · фото</th></tr></thead><tbody>'+
 (L.length?L.map(o=>{const g=GN[o.g]||[o.stage||o.st||'',''];return '<tr><td>'+t2(o.ts)+'</td><td>'+esc(o.mp)+'<br><span class="mu">'+esc(o.id)+'</span></td><td>'+esc(o.name)+(o.qty>1?' × '+o.qty:'')+'</td><td class="r">'+rub(o.sale)+'</td><td class="r">'+rub(o.cost)+'</td><td>'+esc(o.src||'')+'</td><td class="r '+(o.profit>0?'ok':o.profit<0?'bad':'')+'">'+rub(o.profit)+(o.margin!=null?'<br><span class="mu">'+String(o.margin).replace('.',',')+'%</span>':'')+'</td><td class="'+g[1]+'">'+esc(g[0])+(o.g==='open'&&o.due?'<br><span class="mu">сдать до '+esc(o.due)+'</span>':'')+'</td><td>'+(o.packed?esc(o.packed):(o.g==='open'?'<span class="bad">не собран</span>':'—'))+(o.mp!=='Сайт'?'<br><span class="'+(o.photos?'':'bad')+'">📷 '+(o.photos||0)+'</span>':'')+'</td></tr>'}).join(''):'<tr><td colspan="9" class="mu">Заказов за период нет.</td></tr>')+
 (L.length?'<tr><td colspan="3"><b>Итого: '+s.n+'</b></td><td class="r"><b>'+rub(s.sale)+'</b></td><td class="r"><b>'+rub(s.cost)+'</b></td><td></td><td class="r ok"><b>'+rub(s.profit)+'</b></td><td colspan="2"></td></tr>':'')+'</tbody></table></div>';
 h+='<p class="mu" style="font-size:13px">Закуп — лучшая цена в прайсах поставщиков в момент заказа (бот присылает её в «Закупить»). Прибыль — после комиссий площадки, логистики и налогов.</p>';
 return h;
}
function buyers(){
 const L=(P.buyers_list||[]).slice().sort((a,b)=>(a.t0||0)-(b.t0||0));
 let h='<h2>Ждут ответа · '+L.length+'</h2>'+(L.length?L.map(x=>'<div class="q"><div class="row"><b>'+esc(MK[x.mk]||x.mk)+' · '+esc(x.what||'')+'</b><span class="'+(x.t0&&Date.now()/1000-x.t0>3600?'bad':'mu')+'">'+(x.t0?'ждёт '+dur(Date.now()/1000-x.t0):'')+'</span></div><div>'+esc(x.in)+'</div>'+(x.draft?'<div class="d mu">Черновик ИИ: '+esc(x.draft)+'</div>':'')+'</div>').join(''):'<div class="card ok">Все вопросы отвечены.</div>');
 h+='<h2>Кто ответил</h2>'+journal('Покупатели');
 return h;
}
function prices(){
 const L=P.prices_list||[];
 let h='<h2>Ждут подтверждения · '+L.length+'</h2>'+(L.length?'<div class="tw"><table><thead><tr><th>Товар</th><th class="r">Было ¥</th><th class="r">Станет ¥</th><th class="r">Изм.</th></tr></thead><tbody>'+L.map(x=>'<tr><td>'+esc(x.name)+'</td><td class="r">'+esc(x.cur)+'</td><td class="r">'+esc(x.new)+'</td><td class="r">'+(x.chg!=null?(x.chg*100).toFixed(0)+'%':'')+'</td></tr>').join('')+'</tbody></table></div>':'<div class="card ok">Ничего не ждёт.</div>');
 const H=(D.ph||[]).slice().reverse().slice(0,150);
 h+='<h2>Последние изменения цен</h2><div class="tw"><table><thead><tr><th>Когда</th><th>Где</th><th>Товар</th><th class="r">Было</th><th class="r">Стало</th><th class="r">Закуп ₽</th><th>Как</th></tr></thead><tbody>'+H.map(r=>'<tr><td>'+esc(String(r[0]).slice(5,16))+'</td><td>'+esc(r[1])+'</td><td>'+esc(r[3])+'</td><td class="r">'+esc(r[4])+'</td><td class="r">'+esc(r[5])+'</td><td class="r">'+esc(r[7])+'</td><td class="mu">'+esc(r[9])+'</td></tr>').join('')+'</tbody></table></div>';
 return h;
}
function team(short){
 const L=J.filter(r=>inR(r.ts)), ppl={};
 L.forEach(r=>{const p=ppl[r.who]=ppl[r.who]||{n:0,k:{},w:[]};p.n++;const k=r.what.replace(/ [\d  ]+¥$/,'').replace(/ \d★$/,'');p.k[k]=(p.k[k]||0)+1;if(r.wait!=null&&r.who!=='ИИ (сам)'&&!/Сдан|Получен|Возврат|Отменён/.test(r.what))p.w.push(r.wait);});
 const names=Object.keys(ppl).sort((x,y)=>ppl[y].n-ppl[x].n);
 return '<div class="grid">'+(names.length?names.map(nm=>{const p=ppl[nm],w=p.w.sort((a,b)=>a-b),m=w.length?w[Math.floor(w.length/2)]:null;return '<div class="card"><div class="row"><b>'+esc(nm)+'</b><b>'+p.n+'</b></div>'+Object.entries(p.k).sort((a,b)=>b[1]-a[1]).slice(0,short?4:20).map(([k,v])=>'<div class="row"><span>'+esc(k)+'</span><b>'+v+'</b></div>').join('')+(m!=null?'<div class="row mu"><span>реагирует за</span><span>'+dur(m)+'</span></div>':'')+'</div>'}).join(''):'<div class="card mu">За период действий нет.</div>')+'</div>';
}
function journal(bot){
 const ws=[...new Set(J.map(r=>r.who))].sort(), bs=[...new Set(J.map(r=>r.bot))].sort();
 const L=J.filter(r=>inR(r.ts)&&(!fw||r.who===fw)&&(bot?r.bot===bot:(!fb||r.bot===fb))).sort((a,b)=>b.ts-a.ts).slice(0,500);
 return (bot?'':'<div class="chips" style="margin-bottom:10px"><select id="fw"><option value="">Все сотрудники</option>'+ws.map(x=>'<option'+(x===fw?' selected':'')+'>'+esc(x)+'</option>').join('')+'</select><select id="fb"><option value="">Все боты</option>'+bs.map(x=>'<option'+(x===fb?' selected':'')+'>'+esc(x)+'</option>').join('')+'</select></div>')+
 '<div class="tw"><table><thead><tr><th>Время</th><th>Кто</th>'+(bot?'':'<th>Бот</th>')+'<th>Действие</th><th>Что</th><th class="r">Реакция</th></tr></thead><tbody>'+(L.length?L.map(r=>'<tr><td>'+t2(r.ts)+'</td><td><b>'+esc(r.who)+'</b></td>'+(bot?'':'<td>'+esc(r.bot)+'</td>')+'<td>'+esc(r.what)+'</td><td>'+esc(r.obj)+'</td><td class="r">'+(r.wait!=null?dur(r.wait):'')+'</td></tr>').join(''):'<tr><td colspan="6" class="mu">Пока пусто — действия появятся, как только сотрудники начнут нажимать кнопки в ботах.</td></tr>')+'</tbody></table></div>';
}
function draw(){
 const age=Date.now()/1000-D.gen;
 document.getElementById('upd').textContent='Данные от '+t2(D.gen)+' · обновляется каждые 2 минуты';
 document.getElementById('stale').innerHTML=age>900?'<div class="warn">Данные не обновлялись '+dur(age)+' — похоже, Mac с ботами не на связи.</div>':'';
 document.getElementById('per').innerHTML=[['today','Сегодня'],['yday','Вчера'],['7','7 дней'],['30','30 дней'],['90','90 дней']].map(([k,n])=>'<button class="chip'+(per===k?' on':'')+'" data-p="'+k+'">'+n+'</button>').join('');
 const T=[['over','Обзор',0],['ord','Заказы',P.pack_notpacked||0],['buy','Покупатели',P.buyers||0],['pr','Цены',P.prices||0],['team','Команда',0],['log','Журнал',0]];
 document.getElementById('tabs').innerHTML=T.map(([k,n,c])=>'<button class="tab'+(tab===k?' on':'')+'" data-t="'+k+'">'+n+(c?'<span class="n">'+c+'</span>':'')+'</button>').join('');
 document.getElementById('body').innerHTML=tab==='over'?over():tab==='ord'?orders():tab==='buy'?buyers():tab==='pr'?prices():tab==='team'?'<h2>Сотрудники</h2>'+team(false):'<h2>Журнал действий</h2>'+journal('');
}
document.addEventListener('click',e=>{const t=e.target.closest('[data-t],[data-go],[data-p]');if(!t)return;if(t.dataset.t)tab=t.dataset.t;if(t.dataset.go)tab=t.dataset.go;if(t.dataset.p)per=t.dataset.p;draw();});
document.addEventListener('change',e=>{if(e.target.id==='fw')fw=e.target.value;if(e.target.id==='fb')fb=e.target.value;if(e.target.id==='fmp')fmp=e.target.value;draw();});
document.addEventListener('input',e=>{if(e.target.id==='q'){q=e.target.value;const pos=e.target.selectionStart;draw();const i=document.getElementById('q');i.focus();i.setSelectionRange(pos,pos);}});
draw();setTimeout(()=>{if(!document.activeElement||document.activeElement.id!=='q')location.reload()},120000);
</script></body></html>
