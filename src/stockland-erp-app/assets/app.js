(function(){'use strict';const A=window.StocklandApp;const root=document.getElementById('stockland-app');let state={boot:null,setupStatus:null,view:'dashboard',q:'',inventoryQuery:'',productQuery:'',catalog:null,catalogQuery:null,catalogSearchPending:false,catalogLoading:false,catalogRequestId:0,reqDetails:{},users:[],report:null,finance:null,financeLoaded:false,unreadNotifications:0,setupAdvancing:false,skuPickerSeq:0,calendarMonth:null,calendarData:null,posSales:[],dashboardInsights:null,dashboardInsightsLoading:false,dashboardInsightsRequestId:0,wholesaleAdvice:null,wholesaleAdviceLoading:false,wholesaleAdviceError:'',wholesaleAdviceRequestId:0,wholesalePrefill:null};
const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
const uiIcon=name=>{const p={home:'M3 10.5 12 3l9 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 19.5v-9Z',cash:'M4 7h16v10H4zM8 12h8M9 9h.01M15 15h.01',cart:'M3 4h2l2.1 10.1a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L21 8H7M10 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z',chart:'M5 19V10m7 9V5m7 14v-7',box:'M4 7.5 12 3l8 4.5v9L12 21l-8-4.5zM4 7.5l8 4.5 8-4.5M12 12v9',arrow:'M4 12h16m-7-7 7 7-7 7',list:'M6 6h13M6 12h13M6 18h13M3 6h.01M3 12h.01M3 18h.01',warehouse:'M3 21V8l9-5 9 5v16M6 21v-9h12v9M9 21v-5h6v5',transfer:'M4 7h16M16 3l4 4-4 4M20 17H4m4-4-4 4 4 4',return:'M9 6 4 11l5 5M4 11h10a5 5 0 0 1 5 5v1',close:'M4 12a8 8 0 1 0 16 0 8 8 0 1 0-16 0Zm8-4v5l3 2',report:'M5 4h14v16H5zM8 8h8M8 12h8M8 16h5',product:'M4 6h16M7 4v4m10-4v4M6 10h12v10H6z',upload:'M12 16V4m0 0-4 4m4-4 4 4M5 20h14',users:'M16 19v-1a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v1M10 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm5 1a3 3 0 1 0 0-6',settings:'M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm8-3.5-2 1.2.2 2.3-2.1 2.1-2.3-.2-1.2 2H9.4l-1.2-2-2.3.2-2.1-2.1.2-2.3-2-1.2v-3l2-1.2-.2-2.3L7 3.4l2.3.2 1.2-2h3l1.2 2 2.3-.2 2.1 2.1-.2 2.3 2 1.2v3Z',audit:'M7 3h10v4H7zM6 7h12a2 2 0 0 1 2 2v9H4V9a2 2 0 0 1 2-2Zm3 5h6M9 16h6',calendar:'M5 4h14v16H5zM8 2v4m8-4v4M5 9h14M8 13h3m2 0h3m-8 4h3m2 0h3',health:'M12 20s-7-4.2-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.8-7 10-7 10',bell:'M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4',profile:'M20 21a8 8 0 0 0-16 0M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',search:'M21 21l-4.3-4.3M10.8 18a7.2 7.2 0 1 1 0-14.4 7.2 7.2 0 0 1 0 14.4Z'};return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'+(p[name]||p.list)+'"/></svg>'};
const money=n=>new Intl.NumberFormat('en-ET',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(n||0))+' ETB';


/* UI-only appearance preferences. These settings are local to this browser and never touch ERP records. */
const slThemeOptions=[{id:'sapphire',name:'Stockland Sapphire',desc:'Official navy + cobalt',color:'#295ff3',navy:'#0b1730'}];
function applySlTheme(theme,notify=false){const palette=slThemeOptions[0];const allowed=palette.id;document.body.setAttribute('data-sl-theme',allowed);document.documentElement.setAttribute('data-sl-theme',allowed);document.querySelectorAll('meta[name="theme-color"]').forEach(m=>m.setAttribute('content',palette.color));try{localStorage.setItem('stockland_erp_theme',allowed)}catch(_e){}if(notify)toast('Appearance updated: '+palette.name)}
try{applySlTheme(localStorage.getItem('stockland_erp_theme')||'sapphire',false)}catch(_e){applySlTheme('sapphire',false)}
function openThemePicker(){const old=document.getElementById('slThemePicker');if(old){old.remove();return}const active=document.body.getAttribute('data-sl-theme')||'sapphire';const ov=document.createElement('div');ov.id='slThemePicker';ov.className='sl-overlay sl-theme-overlay';ov.innerHTML='<section class="sl-theme-panel" role="dialog" aria-modal="true" aria-labelledby="slThemeTitle"><div class="sl-drawer-head"><div><div class="sl-eyebrow">PERSONALIZE WORKSPACE</div><h2 id="slThemeTitle">Color scheme</h2><p>Applies consistently across cards, sheets, fields, buttons and navigation. ERP data, permissions and workflow behavior are unchanged.</p></div><button class="sl-icon-btn" id="slThemeClose" type="button" aria-label="Close color scheme picker">×</button></div><div class="sl-theme-options">'+slThemeOptions.map(t=>'<button type="button" class="sl-theme-option '+(t.id===active?'active':'')+'" data-sl-theme-choice="'+t.id+'" aria-pressed="'+(t.id===active?'true':'false')+'"><span class="sl-theme-swatch" style="--sl-swatch:'+t.color+';--sl-swatch-navy:'+t.navy+'"><i></i><i></i><i></i></span><span class="sl-theme-copy"><b>'+esc(t.name)+'</b><small>'+esc(t.desc)+'</small></span><span class="sl-theme-check" aria-hidden="true">'+(t.id===active?'✓':'')+'</span></button>').join('')+'</div><div class="sl-notice sl-notice-soft">Your selection is saved on this browser only.</div></section>';document.body.appendChild(ov);const close=()=>ov.remove();document.getElementById('slThemeClose').onclick=close;ov.onclick=e=>{if(e.target===ov)close()};ov.querySelectorAll('[data-sl-theme-choice]').forEach(b=>b.onclick=()=>{applySlTheme(b.dataset.slThemeChoice,true);ov.querySelectorAll('[data-sl-theme-choice]').forEach(x=>{const on=x===b;x.classList.toggle('active',on);x.setAttribute('aria-pressed',on?'true':'false');const mark=x.querySelector('.sl-theme-check');if(mark)mark.textContent=on?'✓':''})})}
// Business dates must follow the browser/operator local calendar, not UTC.
// At East Africa midnight, toISOString() can still point to the previous date.
const localDate=d=>{d=d||new Date();const p=n=>String(n).padStart(2,'0');return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())};
const localMonthStart=()=>{const d=new Date();d.setDate(1);return localDate(d)};
const newRequestKey=()=>{try{if(window.crypto&&typeof window.crypto.randomUUID==='function')return window.crypto.randomUUID()}catch(_e){}return 'a'+Date.now().toString(16)+Math.random().toString(16).slice(2)+Math.random().toString(16).slice(2)};
const debounce=(fn,wait=220)=>{let t=0;return(...args)=>{clearTimeout(t);t=setTimeout(()=>fn(...args),wait)}};
const roleSopGuide={owner:{title:'Owner • Control & Delegation',next:'Review exceptions, branch demand and organization authority.',steps:['MONITOR COMPANY','REVIEW EXCEPTIONS','AUTHORIZE STRUCTURE','CONTROL POLICY']},management:{title:'Management • Review & Approval',next:'Clear pending workflow decisions within your authority.',steps:['REVIEW DEMAND','CHECK WAREHOUSE AVAILABILITY','APPROVE / REJECT','MONITOR EXCEPTIONS']},branch_manager:{title:'Branch Manager • Branch Operations',next:'Validate branch demand, review live system sourcing and complete authorized branch work.',steps:['CHECK BRANCH STOCK','CHECK SYSTEM SOURCING: CENTRAL + PEER BRANCHES','REVIEW WAREHOUSE AVAILABILITY','SUBMIT REQUISITION','REVIEW POS RESULT','RECEIVE & CONFIRM']},sales_employee:{title:'Sales Employee • Branch Sales Operations',next:'Focus on customer sales, Product Master SKU selection, stock requests and branch operational counts; Daily POS release belongs to the Branch Manager.',steps:['CHECK CUSTOMER','SELECT LIVE SKU','COMPLETE SALES IN POS','UPLOAD / STAGE DAILY POS WHEN ASSIGNED','REQUEST STOCK WHEN NEEDED','HAND OVER TO BRANCH MANAGER / EXCEPTIONS']},warehouse_manager:{title:'Warehouse Manager • Central Fulfillment',next:'Review approved requisitions, reserve approved stock, and pick/mark ready when direct Central Warehouse execution is needed.',steps:['REVIEW APPROVED','RESERVE','PICK / READY','HANDOVER TO BRANCH']},warehouse_employee:{title:'Warehouse Employee • Stock Execution',next:'Pick only after the Store/Warehouse Manager has reserved the approved work.',steps:['REVIEW RESERVED QUEUE','PICK / READY','HANDOVER TO BRANCH']},auditor:{title:'Auditor • Evidence & Review',next:'Review immutable activity, workflow and reconciliation evidence.',steps:['REVIEW','TRACE','VERIFY','REPORT EXCEPTION']}};
function openSopGuide(){const contextual=document.querySelector('.sl-training-panel');if(contextual){contextual.scrollIntoView({behavior:'smooth',block:'start'});contextual.classList.add('sl-sop-focus');setTimeout(()=>contextual.classList.remove('sl-sop-focus'),1600);return}const role=String(state.boot?.user?.role||'').toLowerCase();const g=roleSopGuide[role]||roleSopGuide.auditor;const ov=document.createElement('div');ov.className='sl-overlay';ov.innerHTML='<aside class="sl-drawer sl-sop-drawer" role="dialog" aria-modal="true" aria-labelledby="slSopGuideTitle"><div class="sl-drawer-head"><div><div class="sl-eyebrow">ROLE-BASED SOP GUIDE</div><h2 id="slSopGuideTitle">'+esc(g.title)+'</h2><p>Presentation guidance only. Server-side authority and workflow rules remain the source of truth.</p></div><button class="sl-icon-btn" id="slSopClose" aria-label="Close">×</button></div><div class="sl-sop-next"><span>NEXT BEST ACTION</span><b>'+esc(g.next)+'</b></div><div class="sl-sop-steps">'+g.steps.map((x,i)=>'<div class="sl-sop-step"><span>'+String(i+1).padStart(2,'0')+'</span><div>'+esc(x)+'</div></div>').join('')+'</div><div class="sl-notice sl-notice-soft"><b>ERP rule:</b> this guide does not create permission. The backend re-checks role, capability, location scope and workflow state on every write.</div></aside>';document.body.appendChild(ov);const close=()=>ov.remove();document.getElementById('slSopClose').onclick=close;ov.onclick=e=>{if(e.target===ov)close()}}
function openCommandPalette(){const current=document.getElementById('slCommandPalette');if(current){current.remove();return}const c=state.boot?.user?.capabilities||{};const canReports=!!(c.reports||state.boot?.user?.can_cost);const canDailyPos=['branch_manager','sales_employee'].includes(state.boot?.user?.role) && !!c.daily_import;const canPOS=['branch_manager','sales_employee'].includes(state.boot?.user?.role) && !!c.sale;const canHR=state.setupStatus?.hr?.ready===true&&(!!c.hr_view||['owner','management'].includes(String(state.boot?.user?.role||'')));const icons={dashboard:'home',intelligence:'chart',dailyreport:'report',finance:'chart',operate:'arrow',pos:'cash',daily:'upload',calendar:'calendar',inventory:'box',reorder:'list',warehouse:'warehouse',requests:'list',transfers:'transfer',wholesale:'chart',returns:'return',reconcile:'audit',closing:'close',reports:'report',products:'product',users:'users',settings:'settings',control:'settings',audit:'audit',health:'health',hr:'chart'};const rows=[['dashboard','Command Center',true],['dailyreport','Daily Inventory',true],['pos','Retail POS',canPOS],['calendar','Business Calendar',!!c.settings],['daily','Daily POS Import',canDailyPos],['inventory','Inventory',true],['reorder','Reorder Alerts',true],['requests','Requisitions',!!(c.request||c.review_request)],['warehouse','Central Warehouse',!!c.warehouse],['transfers','Transfers',!!c.transfer],['products','Product Master',!!c.products],['finance','Finance & Procurement',!!c.finance_view],['branchops','Branch Operations',!!c.branch_ops],['reports','Reports & Analytics',canReports],['intelligence','Management Intelligence',!!(c.finance_view&&['owner','management'].includes(state.boot?.user?.role))],['users','Users & Organization',!!c.settings],['hr','HR & Payroll',canHR],['audit','Audit Log',!!state.boot.user.can_cost]].filter(x=>x[2]);const ov=document.createElement('div');ov.id='slCommandPalette';ov.className='sl-overlay sl-command-overlay';ov.innerHTML='<div class="sl-command" role="dialog" aria-modal="true" aria-labelledby="slCommandTitle"><div class="sl-command-head"><div><div class="sl-eyebrow">COMMAND CENTER</div><h2 id="slCommandTitle">Jump to ERP function</h2></div><button class="sl-icon-btn" id="slCommandClose">×</button></div><input id="slCommandInput" class="sl-command-input" placeholder="Search module, task or screen…" autocomplete="off"><div class="sl-command-list">'+rows.map(r=>'<button data-command-view="'+r[0]+'"><span>'+uiIcon(icons[r[0]]||'list')+'</span><div><b>'+esc(r[1])+'</b><small>Open '+esc(r[1])+'</small></div><kbd>↵</kbd></button>').join('')+'</div><div class="sl-command-foot"><span>Tip</span><small>Press Ctrl/⌘ + K anytime to open this launcher.</small></div></div>';document.body.appendChild(ov);const input=document.getElementById('slCommandInput');const filter=()=>{const q=input.value.toLowerCase().trim();ov.querySelectorAll('[data-command-view]').forEach(b=>{b.hidden=!b.textContent.toLowerCase().includes(q)})};input.oninput=filter;ov.querySelectorAll('[data-command-view]').forEach(b=>b.onclick=()=>{state.view=b.dataset.commandView;ov.remove();render()});document.getElementById('slCommandClose').onclick=()=>ov.remove();ov.onclick=e=>{if(e.target===ov)ov.remove()};requestAnimationFrame(()=>input.focus())}
function openOpsToast(titleText,message,actionLabel,actionView){const ov=document.createElement('div');ov.className='sl-live-toast';ov.innerHTML='<div><span class="sl-live-toast-dot"></span><div><b>'+esc(titleText)+'</b><p>'+esc(message)+'</p></div></div>'+(actionLabel?'<button class="sl-btn sl-btn-sm">'+esc(actionLabel)+'</button>':'');document.body.appendChild(ov);const t=ov.querySelector('button');if(t)t.onclick=()=>{ov.remove();state.view=actionView;render()};setTimeout(()=>ov.remove(),8500)}
async function parseApiResponse(r){
  const text=await r.text();
  const contentType=String(r.headers.get('content-type')||'').toLowerCase();
  let d=null;
  try{d=text?JSON.parse(text):{}}catch(e){
    const looksHtml=/^\s*<!doctype|^\s*<html|<body|<p>There has been a critical error|wordpress\.org\/documentation\/article\/faq-troubleshooting/i.test(text||'');
    const status='HTTP '+r.status;
    if(looksHtml) throw new Error('ERP server returned an HTML error page ('+status+'). No ERP data was changed. The loaded ERP build is '+String(A.build||'unknown')+'. Open System Health and server error logs before retrying.');
    throw new Error(contentType&&!contentType.includes('json')?('ERP server returned a non-JSON response ('+status+'). No ERP data was changed.') : ('ERP server returned invalid JSON ('+status+'). No ERP data was changed.'));
  }
  if(!r.ok)throw new Error((d&&d.message)||(d&&d.code)||('ERP request failed ('+r.status+').'));
  if(d===null||typeof d!=='object')throw new Error('ERP server returned an unexpected response. No ERP data was changed.');
  return d;
}
function resolveTrustedApiUrl(path,baseOverride){
  const raw=String(path??'');let target;
  try{
    if(/^https?:\/\//i.test(raw)){target=new URL(raw,window.location.href)}else{
      const base=new URL(String(baseOverride||A.api||''),window.location.href),q=raw.indexOf('?');
      const route=(q>=0?raw.slice(0,q):raw).replace(/^\/+|\/+$/g,'');if(!route)throw Error('empty route');
      if(base.searchParams.has('rest_route')){const rb=String(base.searchParams.get('rest_route')||'').replace(/\/+$/g,'');if(!rb)throw Error('empty REST route base');base.searchParams.set('rest_route',rb+'/'+route)}
      else base.pathname=base.pathname.replace(/\/+$/g,'')+'/'+route;
      if(q>=0)new URLSearchParams(raw.slice(q+1)).forEach((v,k)=>base.searchParams.append(k,v));target=base;
    }
  }catch(_){throw new Error('ERP request URL is invalid. No ERP data was changed.')}
  const derivedV16=String(A.api||'').replace(/\/v1$/,'/v16');const bases=[A.api,A.api_v16,derivedV16].filter(Boolean);
  const allowed=bases.some(v=>{let b;try{b=new URL(String(v),window.location.href)}catch(_){return false}if(target.origin!==b.origin)return false;
    const br=b.searchParams.get('rest_route'),tr=target.searchParams.get('rest_route');
    if(br!==null){const expected=br.replace(/\/+$/,'');return typeof tr==='string'&&(tr===expected||tr.indexOf(expected+'/')===0)}
    const prefix=b.pathname.replace(/\/+$/,'');return target.pathname===prefix||target.pathname.indexOf(prefix+'/')===0});
  if(!allowed)throw new Error('Blocked ERP request outside the configured WordPress REST API. No ERP data was changed.');return target.href;
}
async function api(path,opt={},baseOverride){const headers=Object.assign({'X-WP-Nonce':A.nonce},opt.headers||{});const multipart=typeof FormData!=='undefined'&&opt.body instanceof FormData;if(multipart){Object.keys(headers).forEach(k=>{if(k.toLowerCase()==='content-type')delete headers[k]})}else if(!Object.keys(headers).some(k=>k.toLowerCase()==='content-type')){headers['Content-Type']='application/json'}opt.headers=headers;const url=resolveTrustedApiUrl(path,baseOverride);let controller=null;let timer=null;if(!opt.signal){controller=new AbortController();opt.signal=controller.signal;timer=setTimeout(()=>controller.abort(),25000)}if(!opt.cache && (!opt.method || String(opt.method).toUpperCase()==='GET'))opt.cache='no-store';try{return await parseApiResponse(await fetch(url,opt))}catch(e){if(e?.name==='AbortError')throw new Error('ERP request timed out. No ERP data was changed. Please retry after checking System Health.');if(e instanceof TypeError && /failed to fetch/i.test(String(e.message||'')))throw new Error('ERP connection failed. The server could not be reached and no ERP data was changed. Check the ERP URL/hosting connection and retry.');throw e}finally{if(timer)clearTimeout(timer)}}
function setupProgressCard(titleText,pct,meta,cls){
const width=Math.max(0,Math.min(100,Number(pct||0)));
return '<div class="sl-panel" style="max-width:980px;margin:18px auto"><div class="sl-eyebrow">STOCKLAND ETHIOPIA • PRODUCTION SETUP</div><h2>'+esc(titleText)+'</h2><p>'+esc(meta||'Preparing the ERP safely in bounded browser requests.')+'</p><div style="height:10px;border-radius:999px;background:#e9eef5;overflow:hidden;margin:16px 0"><div style="height:100%;width:'+width+'%;background:#4f46e5;border-radius:999px;transition:width .35s"></div></div><div class="sl-statline"><div class="sl-stat"><b>'+width+'%</b><span>Installation progress</span></div><div class="sl-stat"><b>'+esc(cls||'BROWSER')+'</b><span>Execution model</span></div></div></div>';
}
function renderSetup(st,manualError){
const total=st.total||{present:0,total:null};
const pct=total.total?Math.round((Number(total.present)/Number(total.total))*100):0;
const c=st.core||{},f=st.finance||{},h=st.hr||{};
const can=!!st.can_advance;
let rows='';
rows+='<div class="sl-grid sl-grid-compact">'+card('Core',String(c.present??0)+' / '+String(c.total??'—'),c.missing?.length?'Installing':'Verified')+card('Finance & Procurement',String(f.present??0)+' / '+String(f.total??'—'),f.missing?.length?'Installing':(f.upgrade_gaps?.length?'Repair required':'Verified'))+card('HR & Payroll',String(h.present??0)+' / '+String(h.total??'—'),h.missing?.length?'Installing':'Verified')+card('Total schema',String(total.present??0)+' / '+String(total.total??'—'),st.ready?'Verified':'In progress')+'</div>';
const missingList=can&&Array.isArray(total.missing)&&total.missing.length?'<div class="sl-panel sl-setup-missing"><div class="sl-block-head"><div><div class="sl-eyebrow">SERVER-VERIFIED REMAINING SCHEMA</div><h3>'+esc(String(total.missing.length))+' table(s) still required</h3></div><span class="sl-pill bad">'+esc(st.state||'SETUP')+'</span></div><p class="sl-sub">'+esc(total.missing.join(', '))+'</p></div>':'';
const financeGaps=can&&Array.isArray(f.upgrade_gaps)&&f.upgrade_gaps.length?'<div class="sl-panel sl-setup-missing"><div class="sl-block-head"><div><div class="sl-eyebrow">SERVER-VERIFIED FINANCE REPAIR</div><h3>'+esc(String(f.upgrade_gaps.length))+' structural item(s) still required</h3></div><span class="sl-pill bad">FINANCE</span></div><p class="sl-sub">'+esc(f.upgrade_gaps.join(', '))+'</p></div>':'';
const hrApproval=can&&h.schema_approval_required?'<div class="sl-notice sl-notice-warn"><b>Owner approval required before HR/Payroll schema changes.</b> The next HR step is additive and version-bound ('+esc(h.schema_version||'version not reported')+'). It will be recorded in the immutable audit chain. Existing HR tables and records will not be dropped or reset.</div>':'';
let err='';
if(manualError)err='<div class="sl-notice sl-danger-block"><b>Setup request failed.</b> '+esc(manualError)+'</div>';
else if(st.error?.message&&can)err='<div class="sl-notice sl-danger-block"><b>Setup needs attention.</b> '+esc(st.error.message)+'</div>';
const action=st.ready
 ? '<div class="sl-notice"><b>ERP schema verified.</b> Loading the live Stockland PWA…</div>'
 : can
   ? '<button class="sl-btn primary" id="continueSetup">CONTINUE SECURE SETUP</button><button class="sl-btn" id="retrySetup" style="margin-left:8px">RETRY</button>'
   : '<div class="sl-notice"><b>Setup is controlled by the Stockland Owner / WordPress Administrator.</b><br>The application shell is ready. Please wait for authorized setup to complete.</div>';
root.innerHTML='<div class="sl-root" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px"><div style="width:min(1100px,100%)"><div class="sl-hero"><div><div class="sl-eyebrow">STOCKLAND ETHIOPIA</div><h1>Production ERP Setup</h1><p>The ERP shell is installed. Database and business operations remain locked until the complete source-declared ERP application schema is physically verified. Table totals are diagnostics, not schema approval; HR/Payroll changes require explicit Owner approval.</p></div><div class="sl-live-pill"><i></i> '+esc(st.state==='LIVE'?'LIVE':'SETUP')+'</div></div>'+setupProgressCard('Building the Stockland ERP foundation',pct,'The browser advances one bounded schema batch at a time. No Cron or background worker is required.',st.execution_model)+rows+err+missingList+financeGaps+hrApproval+'<div class="sl-panel" style="margin-top:18px"><div class="sl-block-head"><div><div class="sl-eyebrow">PWA ENTRY</div><h3>Dedicated Stockland ERP</h3><small>'+esc(st.pwa?.url||A.home||'')+'</small></div></div><div style="margin-top:14px">'+action+'</div><p class="sl-sub" style="margin-top:14px">Wallet+ remains a separate application surface. Stockland ERP does not replace or take ownership of it.</p></div></div></div>';
const go=async()=>{if(state.setupAdvancing)return;const buttons=[document.getElementById('continueSetup'),document.getElementById('retrySetup')].filter(Boolean);buttons.forEach(b=>{b.disabled=true;b.setAttribute('aria-busy','true')});try{await advanceSetup();}finally{buttons.forEach(b=>{b.disabled=false;b.removeAttribute('aria-busy')})}};
if(document.getElementById('continueSetup'))document.getElementById('continueSetup').onclick=go;
if(document.getElementById('retrySetup'))document.getElementById('retrySetup').onclick=go;
}
async function advanceSetup(){
if(state.setupAdvancing)return false;
state.setupAdvancing=true;
try{
 let st=await api16('/setup/status');
 renderSetup(st);
 if(!st.can_advance||st.ready)return;
 let previousPresent=Number(st.total?.present||0);
 let previousFinanceGaps=Array.isArray(st.finance?.upgrade_gaps)?st.finance.upgrade_gaps.length:0;
 let previousHRGaps=Array.isArray(st.hr?.upgrade_gaps)?st.hr.upgrade_gaps.length:0;
 for(let i=0;i<160 && !st.ready;i++){
   let requestBody={};
   if(st.state==='HR'&&st.hr?.schema_approval_required){
     const approvalReason=window.prompt('Owner approval required for additive HR/Payroll schema version '+String(st.hr.schema_version||'')+'. Confirm you reviewed the source DDL and have a restorable database/files backup. Enter the approval reason (at least 12 characters):','Owner-approved additive HR/Payroll schema migration after backup and source review');
     if(!approvalReason||approvalReason.trim().length<12){renderSetup(st,'HR/Payroll schema setup paused. No HR schema change was attempted because Owner approval was not provided.');return;}
     requestBody={approve_hr_schema:true,approval_reason:approvalReason.trim()};
   }
   await api16('/setup/advance',{method:'POST',body:JSON.stringify(requestBody)});
   // Server status is the sole progress authority. Re-read the physical table state.
   const fresh=await api16('/setup/status');
   state.setupStatus=fresh;
   renderSetup(fresh);
   // Stop browser auto-setup at the verified 49-key baseline. The remaining HR
   // work can be initiated only from the separate Owner-controlled System Control page.
   if(fresh.operational_ready===true){
     await load();
     return;
   }
   const currentPresent=Number(fresh.total?.present||0);
   const currentFinanceGaps=Array.isArray(fresh.finance?.upgrade_gaps)?fresh.finance.upgrade_gaps.length:0;
   const currentHRGaps=Array.isArray(fresh.hr?.upgrade_gaps)?fresh.hr.upgrade_gaps.length:0;
   if(fresh.ready){await load();return;}
   if(currentPresent<=previousPresent && currentFinanceGaps>=previousFinanceGaps && currentHRGaps>=previousHRGaps){
     throw new Error('Schema repair made no verified database progress. Review the exact server error and remaining table list shown above.');
   }
   previousPresent=currentPresent;
   previousFinanceGaps=currentFinanceGaps;
   previousHRGaps=currentHRGaps;
   st=fresh;
   await new Promise(r=>setTimeout(r,160));
 }
 if(!st.ready){
   const remaining=Array.isArray(st.total?.missing)?st.total.missing.join(', '):'unknown';
   renderSetup(st,'Setup paused before full server verification. Remaining: '+remaining);
 }
}catch(e){
 try{const st=await api16('/setup/status');renderSetup(st,e.message);}catch(_){root.innerHTML='<div class="sl-login"><div><h2>Stockland Ethiopia</h2><p class="sl-danger-text">'+esc(e.message)+'</p><button class="sl-btn primary" id="setupRetry">Retry</button></div></div>';document.getElementById('setupRetry')?.addEventListener('click',load);}
}finally{state.setupAdvancing=false;}
}

function mountSetupAccess(access){
 if(!access?.can_manage_users)return;
 const holder=document.createElement('div');holder.id='setupAccessAdmin';holder.className='sl-panel';
 const accounts=Array.isArray(access.accounts)?access.accounts:[];
 const rows=accounts.length?'<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Account</th><th>ERP status</th><th>Action</th></tr></thead><tbody>'+accounts.map(u=>'<tr><td data-label="Account"><b>'+esc(u.display_name||u.user_login)+'</b><div class="sl-sub">@'+esc(u.user_login)+'</div></td><td data-label="ERP status"><span class="sl-pill warn">'+esc(u.status_label||'Needs ERP activation')+'</span></td><td data-label="Action"><button class="sl-btn primary" data-setup-activate="'+esc(u.ID)+'">ACTIVATE ERP ACCESS</button></td></tr>').join('')+'</tbody></table></div>':'<div class="sl-empty-rich"><b>No pending ERP activations.</b><span>Accounts without an active Stockland profile will appear here.</span></div>';
 holder.innerHTML='<div class="sl-block-head"><div><div class="sl-eyebrow">FRONTEND USER ADMINISTRATION</div><h3>ERP account activation</h3><small>Schema setup may still be completing, but user role/profile assignment stays in the ERP frontend. Native WordPress roles are not changed.</small></div></div>'+rows;
 const target=root.querySelector('div.sl-root > div')||root.firstElementChild;if(target)target.appendChild(holder);else root.appendChild(holder);
 document.querySelectorAll('[data-setup-activate]').forEach(b=>b.onclick=()=>openExistingUserActivator(Number(b.dataset.setupActivate)));
}
function renderAccessGate(access,reason){
 const can=!!access?.can_manage_users;
 const accounts=Array.isArray(access?.accounts)?access.accounts:[];
 const rows=accounts.length?'<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Account</th><th>Native WordPress roles</th><th>ERP status</th><th>Action</th></tr></thead><tbody>'+accounts.map(u=>'<tr><td data-label="Account"><b>'+esc(u.display_name||u.user_login)+'</b><div class="sl-sub">@'+esc(u.user_login)+' • '+esc(u.user_email||'')+'</div></td><td data-label="Native WordPress roles">'+esc((u.native_roles||[]).join(', ')||'None')+'</td><td data-label="ERP status"><span class="sl-pill warn">'+esc(u.status_label||'Needs ERP activation')+'</span></td><td data-label="Action"><button class="sl-btn primary" data-access-activate="'+esc(u.ID)+'">ACTIVATE ERP ACCESS</button></td></tr>').join('')+'</tbody></table></div>':'<div class="sl-empty-rich"><b>No pending ERP activations.</b><span>Existing WordPress accounts with no active Stockland profile will appear here.</span></div>';
 const status=can
   ? '<div class="sl-notice"><b>ERP access administration is available.</b> This limited gateway exposes only account activation. Operational modules remain locked until your own ERP profile is active.</div>'
   : '<div class="sl-notice"><b>Your ERP account is not activated.</b> An Owner or Management user must assign your Stockland role and authorized location from the ERP Users & Organization screen. Assigning a WordPress role alone does not create an ERP profile.</div>';
 const err=reason?'<div class="sl-notice sl-danger-block" style="margin-top:12px"><b>ERP operational bootstrap:</b> '+esc(reason)+'</div>':'';
 root.innerHTML='<div class="sl-root" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px"><div style="width:min(1050px,100%)"><div class="sl-hero"><div><div class="sl-eyebrow">STOCKLAND ETHIOPIA</div><h1>ERP Access Control</h1><p>Operational ERP access is controlled separately from native WordPress administration.</p></div><div class="sl-live-pill"><i></i> ACCESS</div></div><div class="sl-panel"><div class="sl-page-title"><div><div class="sl-eyebrow">ACCOUNT</div><h2>'+esc(access?.display_name||'Signed-in account')+'</h2><p>@'+esc(access?.user_login||'')+'</p></div></div>'+status+err+'</div>'+(can?'<div class="sl-panel" style="margin-top:18px"><div class="sl-block-head"><div><div class="sl-eyebrow">FRONTEND ERP ADMINISTRATION</div><h3>Activate existing WordPress accounts</h3><small>Assign the Stockland ERP role and operational location here. Native WordPress roles are not replaced.</small></div></div><div id="accessAvailableWpUsers">'+rows+'</div></div>':'')+'<div class="sl-panel" style="margin-top:18px"><div class="sl-action-row"><a class="sl-btn" href="'+esc(A.home)+'">RETRY ERP ACCESS</a><a class="sl-btn" href="'+esc(A.logout||A.home)+'">SIGN OUT</a></div></div></div></div>';
 document.querySelectorAll('[data-access-activate]').forEach(b=>b.onclick=()=>openExistingUserActivator(Number(b.dataset.accessActivate)));
}
async function load(){try{
  const setup=await api16('/setup/status');
  state.setupStatus=setup;
  // POS, inventory and Finance/Procurement operate on the approved 49-key baseline.
  // HR lifecycle remains a distinct, visibly pending module until an active Owner approves it.
  const setupReady=setup.operational_ready===true&&
    !(setup.core?.missing||[]).length&&!(setup.finance?.missing||[]).length&&
    !(setup.finance?.upgrade_gaps||[]).length&&
    !(setup.hr?.legacy_missing||[]).length;
  if(!setupReady){
    renderSetup(setup);
    try{const access=await api('/app/access/bootstrap');state.access=access;mountSetupAccess(access);}catch(_){/* ordinary users simply remain on setup/pending screen */}
    if(setup.can_advance)setTimeout(advanceSetup,120);return;
  }
  try{ state.wholesaleAdvice=null; state.wholesaleAdviceError=''; state.boot=await api('/app/bootstrap'); state.access=null; render(); try{await loadNotifications();}catch(_e){} }
  catch(bootError){
    const access=await api('/app/access/bootstrap');
    state.access=access;
    renderAccessGate(access,bootError.message);
  }
}catch(e){
  try{const access=await api('/app/access/bootstrap');state.access=access;renderAccessGate(access,e.message);}catch(_){root.innerHTML='<div class="sl-login"><div><h2>Stockland Ethiopia</h2><p class="sl-danger-text">'+esc(e.message)+'</p><a class="sl-btn primary" href="'+esc(A.home)+'">Retry</a></div></div>';}
}}
function nav(){
const u=state.boot.user||{},c=u.capabilities||{};
const canDailyPos=['branch_manager','sales_employee'].includes(u.role)&&!!c.daily_import;
const canReports=!!(c.reports||u.can_cost);
const canHR=state.setupStatus?.hr?.ready===true&&(!!c.hr_view||['owner','management'].includes(String(u.role||'')));
const iconFor={dashboard:'home',intelligence:'chart',pos:'cash',daily:'upload',wholesale:'chart',returns:'return',dailyreport:'report',inventory:'box',reorder:'list',reconcile:'audit',warehouse:'warehouse',requests:'list',transfers:'transfer',operate:'arrow',closing:'close',finance:'chart',products:'product',hr:'users',users:'users',calendar:'calendar',control:'settings',audit:'audit',health:'health',settings:'settings',reports:'report'};
const button=(view,label,visible=true)=>visible?'<button type="button" title="'+esc(label)+'" aria-label="'+esc(label)+'" class="'+(state.view===view?'active ':'')+'sl-nav-item sl-nav-'+view+'" data-view="'+view+'"><span class="sl-nav-icon">'+uiIcon(iconFor[view]||'list')+'</span><span class="sl-nav-text">'+esc(label)+'</span></button>':'';
const group=(key,label,icon,items)=>{
 const visible=items.filter(x=>x[2]);
 if(!visible.length)return '';
 const active=visible.some(x=>state.view===x[0]);
 const expanded=active;
 return '<button type="button" class="sl-nav-group-toggle sl-nav-item'+(active?' has-active-child':'')+'" data-nav-toggle="'+esc(key)+'" aria-expanded="'+(expanded?'true':'false')+'" aria-controls="sl-nav-group-'+esc(key)+'" title="'+esc(label)+'"><span class="sl-nav-icon">'+uiIcon(icon)+'</span><span class="sl-nav-text">'+esc(label)+'</span><span class="sl-nav-caret" aria-hidden="true">⌄</span></button><div class="sl-nav-submenu" id="sl-nav-group-'+esc(key)+'" data-nav-panel="'+esc(key)+'" '+(expanded?'':'hidden')+'>'+visible.map(x=>button(x[0],x[1],true)).join('')+'</div>';
};
return '<div class="sl-nav-section">WORKSPACE</div>'+button('dashboard','Dashboard',true)+
 group('sales','Sales & POS','cash',[
 ['pos','Retail POS',!!c.sale],['daily','Daily POS Import',canDailyPos],['returns','Returns & Adjustments',!!c.return],['closing','Daily Closing',!!c.close]
 ])+
 group('inventory','Inventory','box',[
 ['dailyreport','Daily Inventory',true],['inventory','Inventory Control',true],['reorder','Reorder Alerts',true],['reconcile','Stock Count & Approval',!!(c.request_adjustment||c.settings)]
 ])+
 group('warehouse','Warehouse','warehouse',[
 ['warehouse','Central Warehouse',!!(c.warehouse||c.receive_warehouse)],['requests','Requisitions',!!(c.request||c.review_request)],['transfers','Transfers',!!c.transfer],['operate','Operations Overview',!!(c.warehouse||c.receive_transfer||c.return)]
 ])+
 group('procurement','Purchase & Procurement','report',[
 ['finance','Finance & Accounting',!!c.finance_view]
 ])+
 button('products','Product Master',!!c.products)+
 button('finance','Finance & Accounting',!!c.finance_view)+
 button('hr','HR & Payroll',canHR)+
 group('management','Management','users',[
 ['users','Users & Organization',!!c.settings],['calendar','Business Calendar',!!c.settings],['audit','Audit Log',!!u.can_cost],['health','System Health',!!c.system_health],['control','System Control',!!c.install_schema]
 ])+
 button('intelligence','Management Intelligence',!!(c.finance_view&&['owner','management'].includes(u.role)))+
 button('reports','Reports & Analytics',canReports)+
 button('settings','Settings',!!c.settings);
}
function bindNavGroups(scope){
 const rootNode=scope||document;
 rootNode.querySelectorAll('[data-nav-toggle]').forEach(toggle=>{
  if(toggle.__slNavGroupBound)return;
  toggle.__slNavGroupBound=true;
  toggle.addEventListener('click',()=>{
   const id=toggle.getAttribute('aria-controls');
   const panel=id?rootNode.querySelector('#'+CSS.escape(id)):null;
   if(!panel)return;
   const open=toggle.getAttribute('aria-expanded')==='true';
   toggle.setAttribute('aria-expanded',open?'false':'true');
   panel.hidden=open;
  });
 });
}
function shell(){
let app=document.getElementById('sl-app-shell');
const u=state.boot.user;
if(!app){
root.innerHTML='<div id="sl-app-shell" class="sl-shell"><aside class="sl-sidebar"><div class="sl-brand"><img src="'+esc(A.logo)+'" alt="Stockland Ethiopia"><div><strong>STOCKLAND</strong><small>ETHIOPIA • OPERATIONS</small></div></div><div class="sl-side-caption">Business Control Center</div><nav class="sl-nav" id="sl-nav"></nav><div class="sl-side-footer"><span class="sl-status-dot"></span><div><b>System online</b><small>Stockland ERP v'+esc(A.build||'')+'</small><small>Bootstrap 5.3.8</small></div></div></aside><main class="sl-main"><header class="sl-top"><div class="sl-top-left"><button class="sl-desktop-menu-btn" id="slDesktopMenuBtn" type="button" aria-label="Collapse navigation" aria-expanded="true" title="Collapse navigation"><span aria-hidden="true">☰</span></button><div class="sl-mobile"><button class="sl-btn sl-menu-btn" id="menu" type="button" aria-label="Open navigation menu" aria-expanded="false" aria-controls="sl-mobile-navigation"><span aria-hidden="true">☰</span><span class="sl-menu-label">MENU</span></button></div><img class="sl-top-logo" src="'+esc(A.logo)+'" alt="Stockland Ethiopia"><div class="sl-top-brand"><h1>'+esc(state.boot.settings.business_name)+'</h1><small>'+esc(state.boot.settings.tagline)+'</small></div></div><div class="sl-global-search"><label class="sl-global-search-control" for="slGlobalSearch"><span class="sl-global-search-icon">'+uiIcon('search')+'</span><input id="slGlobalSearch" type="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="slGlobalSearchResults" placeholder="Search product, SKU, order, customer…" autocomplete="off" spellcheck="false"><kbd>Ctrl + K</kbd></label><div class="sl-global-search-results" id="slGlobalSearchResults" role="listbox" aria-label="Global search results" hidden></div></div><div class="sl-top-center"><span class="sl-live-pill"><i></i> LIVE</span><span class="sl-location-chip">'+esc(u.location_name||u.branch_name||'Company-wide')+'</span><span class="sl-sync-indicator" id="slSyncIndicator"><i></i><span>LIVE SYNC</span></span></div><div class="sl-user"><button class="sl-notification-trigger" id="notificationBell" type="button" aria-label="Open operational alerts"><span class="sl-notification-icon">'+uiIcon('bell')+'</span><span class="sl-notification-label">ALERTS</span><span class="sl-notification-badge" id="notificationCount">0</span></button><div class="sl-user-copy"><b id="sl-user-name"></b><small id="sl-user-label"></small></div><div class="sl-profile-wrap"><button class="sl-avatar sl-profile-trigger" id="slProfileBtn" type="button" aria-label="Open ERP user menu" aria-haspopup="true" aria-expanded="false">'+uiIcon('profile')+'<span class="sl-profile-caret" aria-hidden="true">⌄</span></button><div class="sl-profile-menu" id="slProfileMenu" role="menu" hidden><div class="sl-profile-menu-head"><b>'+esc(u.name||'ERP User')+'</b><small>'+esc(String(u.role||'').replace(/_/g,' ').toUpperCase())+' · '+esc(u.location_name||u.branch_name||'Company-wide')+'</small></div><button type="button" role="menuitem" data-profile-view="dashboard">'+uiIcon('home')+'<span>Command Center</span></button>'+(u.capabilities?.finance_view?'<button type="button" role="menuitem" data-profile-view="finance">'+uiIcon('chart')+'<span>Finance & Procurement</span></button>':'')+(u.capabilities?.manage_users||u.capabilities?.settings?'<button type="button" role="menuitem" data-profile-view="users">'+uiIcon('users')+'<span>Users & Organization</span></button>':'')+'<button type="button" role="menuitem" id="slCommandBtn">'+uiIcon('search')+'<span>Command palette</span></button><button type="button" role="menuitem" id="slSopBtn">'+uiIcon('list')+'<span>Role-based SOP guide</span></button><button type="button" role="menuitem" id="slThemeBtn">'+uiIcon('settings')+'<span>Appearance / theme</span></button>'+(A.wp_admin?'<a role="menuitem" href="'+esc(A.wp_admin)+'">'+uiIcon('settings')+'<span>WordPress Dashboard</span></a>':'')+'<a role="menuitem" href="'+esc(A.logout||A.home)+'">'+uiIcon('arrow')+'<span>Sign out</span></a></div></div></div></header><section class="sl-content" id="sl-content"><div class="sl-content-inner container-fluid"></div></section></main></div><div id="toast" class="sl-toast" style="display:none"></div>';
app=document.getElementById('sl-app-shell');
const menu=document.getElementById('menu');if(menu)menu.onclick=mobileMenu;
const desktopToggle=document.getElementById('slDesktopMenuBtn');
if(desktopToggle&&!desktopToggle.__slBound){desktopToggle.__slBound=1;desktopToggle.addEventListener('click',()=>{const shell=document.getElementById('sl-app-shell');if(!shell)return;const collapsed=shell.classList.toggle('sl-sidebar-collapsed');desktopToggle.setAttribute('aria-expanded',collapsed?'false':'true');desktopToggle.setAttribute('aria-label',collapsed?'Expand navigation':'Collapse navigation');desktopToggle.title=collapsed?'Expand navigation':'Collapse navigation';});}
}
}
function closeGlobalSearch(){
 const input=document.getElementById('slGlobalSearch'),panel=document.getElementById('slGlobalSearchResults');
 if(panel)panel.hidden=true;
 if(input)input.setAttribute('aria-expanded','false');
}
function showGlobalSearchMessage(message,isError=false){
 const input=document.getElementById('slGlobalSearch'),panel=document.getElementById('slGlobalSearchResults');
 if(!panel)return;
 panel.innerHTML='<div class="sl-global-search-message'+(isError?' error':'')+'" role="status" aria-live="polite">'+esc(message)+'</div>';
 panel.hidden=false;if(input)input.setAttribute('aria-expanded','true');
}
function drawGlobalSearchResults(rows){
 const input=document.getElementById('slGlobalSearch'),panel=document.getElementById('slGlobalSearchResults');
 if(!panel)return;
 if(!Array.isArray(rows)||!rows.length){showGlobalSearchMessage('No matching records in your authorized scope.');return}
 const icons={product:'box',customer:'users',sale:'cash',purchase_order:'report'};
 const labels={product:'PRODUCT / SKU',customer:'CUSTOMER',sale:'RETAIL SALE',purchase_order:'PURCHASE ORDER'};
 panel.innerHTML=rows.map(r=>'<button type="button" class="sl-global-search-result" role="option" data-global-type="'+esc(r.type||'')+'" data-global-target="'+esc(r.target||'')+'" data-global-query="'+esc(r.query||'')+'" data-global-title="'+esc(r.title||'')+'"><span class="sl-global-search-result-icon">'+uiIcon(icons[r.type]||'search')+'</span><span class="sl-global-search-result-copy"><b>'+esc(r.title||'Record')+'</b><small>'+esc(r.subtitle||'')+'</small></span><span class="sl-global-search-result-type">'+esc(labels[r.type]||'ERP RECORD')+'</span></button>').join('');
 panel.hidden=false;if(input)input.setAttribute('aria-expanded','true');
}
function chooseGlobalSearchResult(button){
 const type=String(button.dataset.globalType||''),query=String(button.dataset.globalQuery||''),titleText=String(button.dataset.globalTitle||'ERP record'),target=String(button.dataset.globalTarget||'');
 const role=String(state.boot?.user?.role||''),caps=state.boot?.user?.capabilities||{};
 closeGlobalSearch();
 if(type==='product'){
  state.globalSearchSelection=null;state.view='products';state.productQuery=query;state.catalog=null;state.catalogQuery=null;state.catalogSearchPending=false;state.catalogLoading=false;state.catalogRequestId++;
 }else if(type==='customer'){
  state.view=['branch_manager','sales_employee'].includes(role)?'branchops':'finance';
  state.globalSearchSelection={type,query,title:titleText};
 }else if(type==='sale'){
  const [,saleDate,locationId]=target.split(':');
  state.view='pos';
  state.globalSearchSelection={type,query,title:titleText,saleDate,locationId};
 }else if(type==='purchase_order'){
  state.view='warehouse';
  state.globalSearchSelection={type,query,title:titleText};
 }else{
  return;
 }
 render();
 if(type==='product'){
  window.setTimeout(()=>{const target=document.getElementById('prodSearch');if(target){target.focus();target.setSelectionRange(target.value.length,target.value.length)}},0);
 }else{
  toast('Search match: '+titleText+' · reference '+query);
 }
}
function bindGlobalSearch(){
 const input=document.getElementById('slGlobalSearch'),panel=document.getElementById('slGlobalSearchResults');
 if(!input||!panel||input.__slGlobalSearchBound)return;
 input.__slGlobalSearchBound=true;
 input.addEventListener('input',()=>{
  const q=String(input.value||'').trim();
  window.clearTimeout(window.__stocklandGlobalSearchTimer);
  const seq=(Number(window.__stocklandGlobalSearchRequest)||0)+1;
  window.__stocklandGlobalSearchRequest=seq;
  if(q.length<2){if(q.length)showGlobalSearchMessage('Type at least 2 characters.');else closeGlobalSearch();return}
  showGlobalSearchMessage('Searching authorized ERP records…');
  window.__stocklandGlobalSearchTimer=window.setTimeout(async()=>{
   try{
    const data=await api('/app/global-search?q='+encodeURIComponent(q),{cache:'no-store'});
    if(seq!==window.__stocklandGlobalSearchRequest||String(input.value||'').trim()!==q)return;
    drawGlobalSearchResults(data.results);
   }catch(error){
    if(seq!==window.__stocklandGlobalSearchRequest)return;
    showGlobalSearchMessage(error.message||'Search could not be completed.',true);
   }
  },240);
 });
 input.addEventListener('keydown',event=>{
  if(event.key==='Escape'){closeGlobalSearch();return}
  if(event.key==='ArrowDown'&&!panel.hidden){const first=panel.querySelector('[data-global-type]');if(first){event.preventDefault();first.focus()}}
  if(event.key==='Enter'&&!panel.hidden){const first=panel.querySelector('[data-global-type]');if(first){event.preventDefault();first.click()}}
 });
 panel.addEventListener('click',event=>{
  const button=event.target.closest('[data-global-type]');
  if(button)chooseGlobalSearchResult(button);
 });
 panel.addEventListener('keydown',event=>{
  const items=[...panel.querySelectorAll('[data-global-type]')],index=items.indexOf(document.activeElement);
  if(event.key==='Escape'){closeGlobalSearch();input.focus()}
  else if(event.key==='ArrowDown'&&items.length){event.preventDefault();items[Math.min(items.length-1,index+1)].focus()}
  else if(event.key==='ArrowUp'&&items.length){event.preventDefault();if(index<=0)input.focus();else items[index-1].focus()}
 });
 if(!window.__stocklandGlobalSearchDocBound){
  window.__stocklandGlobalSearchDocBound=true;
  document.addEventListener('click',event=>{
   const box=document.querySelector('.sl-global-search');
   if(box&&!box.contains(event.target))closeGlobalSearch();
  });
 }
}
function updateChrome(){
const navMarkup=nav();const sideNav=document.getElementById('sl-nav');if(sideNav){sideNav.innerHTML=navMarkup;bindNavGroups(sideNav);}
const u=state.boot.user;
const label=document.getElementById('sl-user-label');
const name=document.getElementById('sl-user-name');
if(name)name.textContent=u.name||'User';
if(label)label.textContent=u.role||'Team member';
 bindGlobalSearch();
}
function closeMobileMenu(restoreFocus=true){
 const d=document.querySelector('.sl-mobile-menu');
 if(d)d.remove();
 document.body.classList.remove('sl-mobile-menu-open');
 const trigger=document.getElementById('menu');
 if(trigger){trigger.setAttribute('aria-expanded','false');if(restoreFocus){try{trigger.focus({preventScroll:true})}catch(_e){trigger.focus()}}}
}
function mobileMenu(){
 const existing=document.querySelector('.sl-mobile-menu');
 if(existing){closeMobileMenu(true);return;}
 const trigger=document.getElementById('menu');
 if(trigger){trigger.setAttribute('aria-expanded','true');trigger.setAttribute('aria-controls','sl-mobile-navigation');}
 const d=document.createElement('div');d.id='sl-mobile-navigation';d.className='sl-mobile-menu';d.setAttribute('role','dialog');d.setAttribute('aria-modal','true');d.setAttribute('aria-label','Stockland ERP navigation');
 d.innerHTML='<div class="sl-mobile-panel"><div class="sl-mobile-panel-head"><div class="sl-brand"><img src="'+esc(A.logo)+'" alt="Stockland Ethiopia"><div><strong>STOCKLAND</strong><small>ETHIOPIA • ERP</small></div></div><button class="sl-icon-btn sl-mobile-close" type="button" aria-label="Close navigation menu">×</button></div><nav class="sl-nav">'+nav()+'</nav><div class="sl-mobile-menu-foot">Live operational data • '+esc(state.boot.user.location_name||state.boot.user.branch_name||'Company-wide')+'</div></div>';
 d.onclick=e=>{
  if(e.target===d||e.target.closest('.sl-mobile-close')){closeMobileMenu(true);return;}
  const b=e.target.closest('[data-view]');
  if(b){state.view=b.dataset.view==='dailyInventory'?'dailyreport':b.dataset.view;closeMobileMenu(false);render();document.getElementById('menu')?.focus();}
 };
 d.addEventListener('keydown',e=>{
  if(e.key!=='Tab')return;
  const focusable=[...d.querySelectorAll('button:not(:disabled),a[href],input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex]:not([tabindex="-1"])')].filter(x=>x.getClientRects().length>0);
  if(!focusable.length)return;
  const first=focusable[0],last=focusable[focusable.length-1];
  if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}
  else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}
 });
 document.body.appendChild(d);bindNavGroups(d);document.body.classList.add('sl-mobile-menu-open');window.dispatchEvent(new CustomEvent('stockland:mobile-menu-open'));
 const closeButton=d.querySelector('.sl-mobile-close');if(closeButton)closeButton.focus();
}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){if(document.querySelector('.sl-mobile-menu'))closeMobileMenu(true);const c=document.getElementById('slCommandPalette');if(c)c.remove();const th=document.getElementById('slThemePicker');if(th)th.remove();}});
window.addEventListener('resize',debounce(()=>{if(window.matchMedia&&window.matchMedia('(min-width: 992px)').matches&&document.querySelector('.sl-mobile-menu'))closeMobileMenu(false)},80),{passive:true});
function systemControlPage(){return title('System Control','Protected ERP foundation control. This does not replace WordPress administration or change business data.')+'<div id="systemControlPage"><div class="sl-panel"><div class="sl-empty">Loading schema verification…</div></div></div>';}
async function loadSystemControl(){const el=document.getElementById('systemControlPage');if(!el)return;try{const st=await api16('/setup/status'),c=st.core||{},f=st.finance||{},h=st.hr||{},t=st.total||{},ready=!!st.ready,can=!!state.boot?.user?.capabilities?.install_schema,missing=Array.isArray(t.missing)?t.missing:[];const cards='<div class="sl-grid sl-grid-compact">'+card('Core',String(c.present??0)+' / '+String(c.total??'—'),c.missing?.length?'Needs repair':'Verified')+card('Finance & Procurement',String(f.present??0)+' / '+String(f.total??'—'),f.missing?.length?'Needs repair':(f.upgrade_gaps?.length?'Repair required':'Verified'))+card('HR & Payroll',String(h.present??0)+' / '+String(h.total??'—'),h.missing?.length?'Needs setup':(h.upgrade_gaps?.length?'Repair required':'Verified'))+card('Full ERP schema',String(t.present??0)+' / '+String(t.total??'—'),ready?'LIVE':'Incomplete')+'</div>';let action='';if(ready)action='<div class="sl-notice"><b>Production schema verified.</b> All required ERP tables are present. No schema action is required.</div>';else if(can)action='<div class="sl-notice sl-notice-soft"><b>Protected setup is resumable.</b> The next action advances only one bounded schema batch and never deletes or reseeds existing ERP records.</div><button class="sl-btn primary" id="advanceSchema">CONTINUE PROTECTED SETUP</button>';else action='<div class="sl-notice"><b>Setup is locked for this account.</b> A Stockland Owner / authorized schema administrator must continue the protected setup.</div>';const missingHtml=can&&(missing.length||(h.upgrade_gaps||[]).length||(f.upgrade_gaps||[]).length)?'<div class="sl-panel" style="margin-top:14px"><div class="sl-block-head"><div><div class="sl-eyebrow">SERVER-VERIFIED REMAINING WORK</div><h3>Tables and structural checks still required</h3></div><small>'+esc(String(missing.length+(h.upgrade_gaps||[]).length+(f.upgrade_gaps||[]).length))+' item(s)</small></div>'+(missing.length?'<div class="sl-sub"><b>Tables:</b> '+esc(missing.join(', '))+'</div>':'')+((f.upgrade_gaps||[]).length?'<div class="sl-sub"><b>Finance checks:</b> '+esc(f.upgrade_gaps.join(', '))+'</div>':'')+((h.upgrade_gaps||[]).length?'<div class="sl-sub"><b>HR checks:</b> '+esc(h.upgrade_gaps.join(', '))+'</div>':'')+'</div>':'';el.innerHTML=cards+'<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">SYSTEM FOUNDATION</div><h3>Source-declared ERP database readiness</h3></div><span class="sl-pill '+(ready?'ok':'bad')+'">'+(ready?'LIVE':'SETUP')+'</span></div>'+action+(st.error?.message&&can?'<div class="sl-notice sl-danger-block" style="margin-top:12px"><b>Last setup error:</b> '+esc(st.error.message)+'</div>':'')+'</div>'+missingHtml;const b=document.getElementById('advanceSchema');if(b)b.onclick=async()=>{if(state.setupAdvancing)return;state.setupAdvancing=true;b.disabled=true;b.setAttribute('aria-busy','true');try{let before=await api16('/setup/status');let previousPresent=Number(before.total?.present||0);let previousFinanceGaps=(before.finance?.upgrade_gaps||[]).length;let previousHRGaps=(before.hr?.upgrade_gaps||[]).length;for(let i=0;i<160&&!before.ready;i++){b.textContent='VERIFYING SETUP… '+(i+1);let requestBody={};if(before.state==='HR'&&before.hr?.schema_approval_required){const approvalReason=window.prompt('Owner approval required for additive HR/Payroll schema version '+String(before.hr.schema_version||'')+'. Confirm you reviewed the source DDL and have a restorable database/files backup. Enter the approval reason (at least 12 characters):','Owner-approved additive HR/Payroll schema migration after backup and source review');if(!approvalReason||approvalReason.trim().length<12)throw new Error('HR/Payroll setup paused. No HR schema change was attempted because Owner approval was not provided.');requestBody={approve_hr_schema:true,approval_reason:approvalReason.trim()};}await api16('/setup/advance',{method:'POST',body:JSON.stringify(requestBody)});const after=await api16('/setup/status');const present=Number(after.total?.present||0),financeGaps=(after.finance?.upgrade_gaps||[]).length,hrGaps=(after.hr?.upgrade_gaps||[]).length;if(after.ready){toast('Core, Finance and HR schema are physically verified');await loadSystemControl();return;}if(present<=previousPresent&&financeGaps>=previousFinanceGaps&&hrGaps>=previousHRGaps){throw new Error('No physically verified setup progress. Review the remaining tables, structural checks and server error before retrying.');}previousPresent=present;previousFinanceGaps=financeGaps;previousHRGaps=hrGaps;before=after;}if(!before.ready)throw new Error('Protected setup reached its per-run safety limit. Refresh status and continue; completed batches are retained.');await loadSystemControl();}catch(e){toast(e.message,true);await loadSystemControl();}finally{state.setupAdvancing=false;const current=document.getElementById('advanceSchema');if(current){current.disabled=false;current.removeAttribute('aria-busy');current.textContent='CONTINUE PROTECTED SETUP'}}};}catch(e){el.innerHTML='<div class="sl-notice sl-danger-block">'+esc(e.message)+'</div>';}}
function reportCsvField(value){const numeric=typeof value==='number'&&Number.isFinite(value);let text=value===null||value===undefined?'':String(value);if(!numeric&&/^\s*[=+\-@]/.test(text))text="'"+text;if(/[",\r\n]/.test(text))text='"'+text.replace(/"/g,'""')+'"';return text}
function buildPeriodSummaryCsv(report,user){const d=report&&typeof report==='object'?report:{},t=d.totals||{},ret=d.returns||{},canProfit=!!(user&&user.can_cost);const n=v=>{const x=Number(v);return Number.isFinite(x)?x:0};const rows=[['Stockland Ethiopia ERP — Period Summary'],['From',d.from||''],['To',d.to||''],['Scope','Current user authorized ERP scope'],[],['Summary','Metric','Value'],['Summary','Gross sales',n(t.sales)],['Summary','Net sales excluding VAT',n(t.net)],['Summary','Recorded VAT',n(t.vat)],['Summary','Units sold',n(t.units)],['Summary','Return units',n(ret.units)],['Summary','Return rows',n(ret.rows)]];if(canProfit)rows.push(['Summary','Gross profit',n(t.profit)]);rows.push([],['Branch performance','Branch','Units sold','Sales value',...(canProfit?['Gross profit']:[])]);for(const b of Array.isArray(d.branches)?d.branches:[]){const row=['Branch',b.location_name||'',n(b.retail_units),n(b.retail_sales)];if(canProfit)row.push(n(b.retail_profit));rows.push(row)}rows.push([],['Top products','SKU','Product','Units sold','Sales value',...(canProfit?['Gross profit']:[])]);for(const x of Array.isArray(d.top_products)?d.top_products:[]){const row=['Product',x.sku||'',x.product_name||'',n(x.units),n(x.sales)];if(canProfit)row.push(n(x.profit));rows.push(row)}rows.push([],['Transfers','Transfer ID','From location','To location','SKU','Quantity','Received quantity','Status']);for(const x of Array.isArray(d.transfers)?d.transfers:[])rows.push(['Transfer',x.transfer_id||'',x.from_location_name||'',x.to_location_name||'',x.sku||'',n(x.quantity),n(x.received_qty),x.status||'']);rows.push([],['Transfer variances','Transfer ID','SKU','Product','From location','To location','Sent quantity','Received quantity','Variance']);for(const x of Array.isArray(d.variances)?d.variances:[])rows.push(['Variance',x.transfer_id||'',x.sku||'',x.product_name||'',x.from_location_name||'',x.to_location_name||'',n(x.dispatched_qty),n(x.received_qty),n(x.variance_qty)]);rows.push([],['Physical counts','Batch ID','Location','Status','Lines','Variance lines','Counted units','System units','Variance units']);for(const x of Array.isArray(d.physical_counts)?d.physical_counts:[])rows.push(['Physical count',x.batch_id||'',x.location_name||'',x.status||'',n(x.lines),n(x.variance_lines),n(x.counted_qty),n(x.system_qty),n(x.variance_qty)]);return '\uFEFF'+rows.map(row=>row.map(reportCsvField).join(',')).join('\r\n')+'\r\n'}
function reportPresetRange(period){const [year,month,day]=localDate().split('-').map(Number);const end=new Date(year,month-1,day);const start=new Date(end);if(period==='weekly')start.setDate(start.getDate()-6);else if(period==='monthly')start.setDate(1);const fmt=x=>x.getFullYear()+'-'+String(x.getMonth()+1).padStart(2,'0')+'-'+String(x.getDate()).padStart(2,'0');return{from:fmt(start),to:fmt(end)}}
function downloadPeriodSummaryCsv(){const d=state.report;if(!d||!d.totals){toast('Run a report before downloading CSV.',true);return}const csv=buildPeriodSummaryCsv(d,state.boot?.user||{});const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});const url=URL.createObjectURL(blob);const link=document.createElement('a');link.href=url;link.download='stockland-summary-'+String(d.from||'start')+'-to-'+String(d.to||'end')+'.csv';link.style.display='none';document.body.appendChild(link);link.click();link.remove();window.setTimeout(()=>URL.revokeObjectURL(url),1000)}
function reportDateRange(period){
  const parts=localDate().split('-').map(Number);const today=new Date(parts[0],parts[1]-1,parts[2]);const from=new Date(today);const to=new Date(today);
  if(period==='yesterday')from.setDate(from.getDate()-1),to.setDate(to.getDate()-1);
  else if(period==='last7')from.setDate(from.getDate()-6);
  else if(period==='this_month')from.setDate(1);
  const fmt=d=>d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');return{from:fmt(from),to:fmt(to)};
}
function reportUsesSku(type){return['product_sku','inventory_availability','critical_reorder','transfer_status','transfer_variance'].includes(type)}
function reports(){
  const user=state.boot?.user||{};const canDailyImport=!!user.capabilities?.daily_import;const owner=user.role==='owner';
  const actions='<button type="button" class="sl-btn" id="printOpsReport" disabled>PRINT / SAVE PDF</button><button type="button" class="sl-btn primary" id="downloadOpsReportCsv" disabled>DOWNLOAD SELECTED CSV</button>'+(canDailyImport?'<button type="button" class="sl-btn" id="openDailyPosImportFromReport">DAILY POS CSV IMPORT</button>':'');
  const types=[['branch_performance','Branch performance'],['sales_performance','Sales performance'],['product_sku','Product / SKU performance'],['inventory_availability','Inventory availability'],...(owner?[['critical_reorder','Critical reorder (Owner only)']]:[]),['transfer_status','Receiving / transfer status'],['transfer_variance','Transfer variance'],['physical_count_variance','Physical-count variance'],['returns_adjustments','Returns & adjustments summary']];
  const typeOptions=types.map(([v,l])=>'<option value="'+esc(v)+'" '+(v==='branch_performance'?'selected':'')+'>'+esc(l)+'</option>').join('');
  const locations=(state.boot?.locations||[]).filter(l=>Number(l.is_active)!==0&&['Branch','Warehouse'].includes(String(l.location_type||'')));
  const locationOptions='<option value="all" selected>All authorized locations</option>'+locations.map(l=>'<option value="'+esc(String(l.id))+'">'+esc(l.location_name||('Location '+l.id))+' — '+esc(l.location_type||'')+'</option>').join('');
  return title('Reports & Analytics','Select one report and print or save it as PDF. Server-side permissions and authorized location scope remain in force.',actions)+
    '<div class="sl-panel sl-report-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">REPORT FILTER</div><h3>Build a report</h3></div><small>Read-only • live ERP records • authorized scope</small></div><form id="reportForm" class="sl-form sl-report-filter">'+
    '<div class="sl-field"><label for="reportType">Report type</label><select id="reportType" name="type">'+typeOptions+'</select></div>'+
    '<div class="sl-field"><label for="reportPeriod">Period</label><select id="reportPeriod" name="period"><option value="today" selected>Today</option><option value="yesterday">Yesterday</option><option value="last7">Last 7 days</option><option value="this_month">This month</option><option value="custom">Custom range</option></select></div>'+
    '<div class="sl-field"><label for="reportLocation">Location</label><select id="reportLocation" name="location_id">'+locationOptions+'</select></div>'+
    '<div class="sl-field" id="reportSkuField" hidden><label for="reportSku">SKU / product / barcode</label><input id="reportSku" name="sku" type="search" value="" placeholder="Search SKU, product or barcode…" autocomplete="off" aria-autocomplete="list" aria-controls="reportSkuSuggestions"><div id="reportSkuSuggestions" class="sl-sku-results" role="listbox" hidden></div><small class="sl-sub">Choose a Product Master match to filter by its exact SKU.</small></div>'+
    '<div id="reportCustomDates" class="sl-field full" hidden><div class="sl-form"><div class="sl-field"><label for="reportFrom">From</label><input id="reportFrom" name="from" type="date" value="'+esc(localDate())+'"></div><div class="sl-field"><label for="reportTo">To</label><input id="reportTo" name="to" type="date" value="'+esc(localDate())+'"></div></div></div>'+
    '<div class="sl-field full"><button type="submit" class="sl-btn primary" id="runOpsReport">RUN REPORT</button></div></form><div class="sl-sub">Today uses the ERP site-local date. Critical reorder is Owner-only on both the interface and server. PDF is produced through the browser print dialog.</div></div>'+
    '<div id="reportResult"><div class="sl-empty">Loading branch performance for today…</div></div><div id="reportRowDetail" class="sl-panel" hidden></div>';
}
function reportDisplayValue(value,format){
  if(value===null||value===undefined||value==='')return '—';
  if(format==='money')return money(Number(value)||0);
  if(format==='number'){const n=Number(value);return Number.isFinite(n)?String(n):esc(String(value));}
  return esc(String(value));
}
function renderSelectedReport(report){
  const el=document.getElementById('reportResult');if(!el)return;
  const cols=Array.isArray(report?.columns)?report.columns:[];const rows=Array.isArray(report?.rows)?report.rows:[];const critical=report?.type==='critical_reorder';
  const heading='<div class="sl-block-head"><div><div class="sl-eyebrow">STOCKLAND ETHIOPIA • REPORT</div><h3>'+esc(report?.title||'Selected report')+'</h3><small>'+esc(report?.from||'')+' to '+esc(report?.to||'')+' • '+esc(report?.location_label||'All authorized locations')+'</small></div><small>Generated '+esc(report?.generated_at||'')+'</small></div>';
  const headers=cols.map(c=>'<th>'+esc(c.label||c.key||'')+'</th>').join('')+(critical?'<th data-report-action-column>Details</th>':'');
  const body=rows.map((row,index)=>'<tr>'+cols.map(c=>'<td data-label="'+esc(c.label||c.key||'')+'">'+reportDisplayValue(row?.[c.key],c.format==='metric'?row?.format:c.format)+'</td>').join('')+(critical?'<td data-label="Details" data-report-action-column><button type="button" class="sl-btn" data-report-details="'+index+'">DETAILS</button></td>':'')+'</tr>').join('')||'<tr><td colspan="'+Math.max(1,cols.length+(critical?1:0))+'">No records matched this report and filter.</td></tr>';
  el.innerHTML='<div class="sl-panel" id="selectedOpsReport" data-report-type="'+esc(report?.type||'')+'">'+heading+'<div class="sl-sub">'+esc(report?.description||'Read-only report from authorized ERP records.')+'</div><div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr>'+headers+'</tr></thead><tbody>'+body+'</tbody></table></div><div class="sl-sub">Rows: '+String(rows.length)+'</div></div>';
  document.querySelectorAll('[data-report-details]').forEach(button=>{button.onclick=()=>{const record=rows[Number(button.dataset.reportDetails)];const detail=document.getElementById('reportRowDetail');if(!record||!detail)return;detail.hidden=false;detail.innerHTML='<div class="sl-block-head"><div><div class="sl-eyebrow">REORDER ROW DETAIL</div><h3>'+esc(record.product_name||record.sku||'Stock item')+'</h3></div><button type="button" class="sl-btn" id="closeReportRowDetail">CLOSE</button></div><div class="sl-table-wrap"><table class="sl-table"><tbody>'+cols.map(c=>'<tr><th>'+esc(c.label||c.key||'')+'</th><td>'+reportDisplayValue(record[c.key],c.format)+'</td></tr>').join('')+'</tbody></table></div><div class="sl-notice">Suggested replenishment is a read-only calculation; no purchase order, reservation or stock movement was created.</div>';document.getElementById('closeReportRowDetail')?.addEventListener('click',()=>{detail.hidden=true;detail.innerHTML=''});detail.scrollIntoView({behavior:'smooth',block:'start'});}});
}
function buildSelectedReportCsv(report){
  const cols=Array.isArray(report?.columns)?report.columns:[];const rows=Array.isArray(report?.rows)?report.rows:[];
  const data=[['Stockland Ethiopia ERP — '+String(report?.title||'Selected report')],['From',report?.from||''],['To',report?.to||''],['Location',report?.location_label||'All authorized locations'],['Generated',report?.generated_at||''],[],cols.map(c=>c.label||c.key||'')];
  rows.forEach(row=>data.push(cols.map(c=>row?.[c.key]??'')));
  return '\uFEFF'+data.map(row=>row.map(reportCsvField).join(',')).join('\r\n')+'\r\n';
}
function downloadSelectedReportCsv(){const report=state.report;if(!report||!Array.isArray(report.columns)){toast('Run a report before downloading CSV.',true);return}const blob=new Blob([buildSelectedReportCsv(report)],{type:'text/csv;charset=utf-8;'});const url=URL.createObjectURL(blob);const link=document.createElement('a');link.href=url;link.download='stockland-'+String(report.type||'report')+'-'+String(report.from||'start')+'-to-'+String(report.to||'end')+'.csv';link.hidden=true;document.body.appendChild(link);link.click();link.remove();window.setTimeout(()=>URL.revokeObjectURL(url),1000)}
async function loadReport(){
  const el=document.getElementById('reportResult');if(!el)return;const form=document.getElementById('reportForm');if(!form)return;
  const type=form.elements.type.value;const period=form.elements.period.value;let from,to;
  if(period==='custom'){from=String(form.elements.from.value||'');to=String(form.elements.to.value||'');}else({from,to}=reportDateRange(period));
  if(!/^\d{4}-\d{2}-\d{2}$/.test(from)||!/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(to)||from>to)throw new Error('Choose a valid date range. From must be on or before To.');
  if(period==='custom'){const validLocalDate=value=>{const p=value.split('-').map(Number);const d=new Date(p[0],p[1]-1,p[2]);return d.getFullYear()===p[0]&&d.getMonth()===p[1]-1&&d.getDate()===p[2]};if(!validLocalDate(from)||!validLocalDate(to))throw new Error('Choose valid calendar dates.');}
  const run=document.getElementById('runOpsReport');if(run){run.disabled=true;run.setAttribute('aria-busy','true');run.textContent='LOADING…';}
  const printButton=document.getElementById('printOpsReport');const csvButton=document.getElementById('downloadOpsReportCsv');if(printButton)printButton.disabled=true;if(csvButton)csvButton.disabled=true;
  const detail=document.getElementById('reportRowDetail');if(detail){detail.hidden=true;detail.innerHTML=''}el.innerHTML='<div class="sl-empty">Loading the selected authorized report…</div>';
  try{
    const params=new URLSearchParams({type,from,to,location_id:String(form.elements.location_id.value||'all')});const sku=reportUsesSku(type)?String(form.elements.sku?.value||'').trim():'';if(sku)params.set('sku',sku);
    const report=await api('/app/reports/explorer?'+params.toString());state.report=report||{};renderSelectedReport(state.report);
    if(printButton)printButton.disabled=!(report&&Array.isArray(report.columns));if(csvButton)csvButton.disabled=!(report&&Array.isArray(report.columns));
  }catch(e){state.report=null;el.innerHTML='<div class="sl-notice sl-danger-block"><b>Report could not load.</b> '+esc(e.message)+'<div class="sl-actions sl-report-retry-actions"><button type="button" class="sl-btn" id="retryOpsReport">RETRY REPORT</button></div></div>';document.getElementById('retryOpsReport')?.addEventListener('click',()=>loadReport());return null;
  }finally{if(run){run.disabled=false;run.removeAttribute('aria-busy');run.textContent='RUN REPORT';}}
}
function bindReportExplorer(){
  const $=selector=>document.querySelector(selector);const form=$('#reportForm');if(!form)return;
  const type=form.elements.type;const period=form.elements.period;const sku=form.elements.sku;const skuField=$('#reportSkuField');const suggestionBox=$('#reportSkuSuggestions');const customDates=$('#reportCustomDates');
  const syncFilters=()=>{const usesSku=reportUsesSku(type.value);if(skuField)skuField.hidden=!usesSku;if(!usesSku&&sku)sku.value='';if(!usesSku&&suggestionBox){suggestionBox.hidden=true;suggestionBox.innerHTML='';}if(customDates)customDates.hidden=period.value!=='custom';};
  const updatePresetDates=()=>{if(period.value==='custom')return;const range=reportDateRange(period.value);if(form.elements.from)form.elements.from.value=range.from;if(form.elements.to)form.elements.to.value=range.to;};
  type.addEventListener('change',syncFilters);period.addEventListener('change',()=>{syncFilters();updatePresetDates()});syncFilters();updatePresetDates();
  form.onsubmit=async e=>{e.preventDefault();try{await loadReport()}catch(err){toast(err.message,true)}};
  let timer=null;let querySequence=0;
  if(sku&&suggestionBox){sku.addEventListener('input',()=>{if(timer)window.clearTimeout(timer);const q=sku.value.trim();const seq=++querySequence;if(q.length<1){suggestionBox.hidden=true;suggestionBox.innerHTML='';return}timer=window.setTimeout(async()=>{try{const found=await api16('/sku-search?q='+encodeURIComponent(q)+'&limit=8');if(seq!==querySequence||!Array.isArray(found))return;const results=found.slice(0,8);suggestionBox.innerHTML=results.map(x=>'<button type="button" class="sl-btn" role="option" data-report-sku="'+esc(String(x.sku||''))+'"><b>'+esc(String(x.sku||''))+'</b> · '+esc(String(x.product_name||''))+' '+esc(String(x.color||''))+' '+esc(String(x.size||''))+' <small>'+esc(String(x.barcode||''))+'</small></button>').join('')||'<div class="sl-empty">No matching Product Master item.</div>';suggestionBox.hidden=false;suggestionBox.querySelectorAll('[data-report-sku]').forEach(button=>button.addEventListener('click',()=>{sku.value=button.dataset.reportSku||'';suggestionBox.hidden=true;suggestionBox.innerHTML='';sku.focus()}));}catch(e){if(seq===querySequence){suggestionBox.hidden=true;suggestionBox.innerHTML='';}}},250)});sku.addEventListener('keydown',e=>{if(e.key==='Escape'&&suggestionBox){suggestionBox.hidden=true;suggestionBox.innerHTML=''}});if(!document.__slReportSkuDismissBound){document.__slReportSkuDismissBound=true;document.addEventListener('click',e=>{const liveBox=document.getElementById('reportSkuSuggestions');if(!e.target.closest('#reportSkuField')&&liveBox){liveBox.hidden=true}});}}
  $('#downloadOpsReportCsv')?.addEventListener('click',downloadSelectedReportCsv);
  $('#printOpsReport')?.addEventListener('click',()=>{if(!document.getElementById('selectedOpsReport')||!state.report){toast('Run a report before printing.',true);return}const hiddenClass='sl-print-report-hidden';const filterPanel=document.querySelector('#stockland-app .sl-report-panel');const detail=document.getElementById('reportRowDetail');const actions=[...document.querySelectorAll('#selectedOpsReport [data-report-action-column]')];if(filterPanel)filterPanel.classList.add(hiddenClass);if(detail)detail.classList.add(hiddenClass);actions.forEach(node=>node.classList.add(hiddenClass));const restore=()=>{if(filterPanel)filterPanel.classList.remove(hiddenClass);if(detail)detail.classList.remove(hiddenClass);actions.forEach(node=>node.classList.remove(hiddenClass));window.removeEventListener('afterprint',restore)};window.addEventListener('afterprint',restore);window.print();});
  $('#openDailyPosImportFromReport')?.addEventListener('click',()=>{state.view='daily';render()});loadReport().catch(()=>{});
}
function title(h,p,actions='',extraClass=''){return '<div class="sl-page-title'+(extraClass?' '+esc(extraClass):'')+'"><div><div class="sl-eyebrow">STOCKLAND ETHIOPIA <span>•</span> OPERATIONS</div><h2>'+h+'</h2><p>'+p+'</p></div><div class="sl-actions">'+actions+'</div></div>'}
function card(label,value,meta=''){return '<div class="sl-card"><div class="label">'+label+'</div><div class="value">'+value+'</div><div class="meta">'+meta+'</div></div>'}
function renderKeepFocus(){const a=document.activeElement;const id=a&&a.id;let pos=null;try{pos=a&&a.selectionStart}catch(e){}render();if(id){const n=document.getElementById(id);if(n){n.focus();if(pos!==null){try{n.setSelectionRange(pos,pos)}catch(e){}}}}}
function applyFinanceTableLabels(){const page=document.getElementById('financePage');if(!page)return;page.querySelectorAll('table.sl-table').forEach(t=>{const heads=[...t.querySelectorAll('thead th')].map(h=>h.textContent.trim());t.querySelectorAll('tbody tr').forEach(tr=>[...tr.children].forEach((td,i)=>{if(heads[i])td.setAttribute('data-label',heads[i])}))})}
const financeLabelObserver=root?new MutationObserver(()=>applyFinanceTableLabels()):null;if(financeLabelObserver)financeLabelObserver.observe(root,{subtree:true,childList:true});
function render(){
if(!state.boot)return;
if(state.view!=='dashboard'){state.wholesaleAdvice=null;state.wholesaleAdviceError='';}
document.body.setAttribute('data-sl-view',String(state.view||'dashboard'));
shell();updateChrome();
let b='';
switch(state.view){
case'finance':b=finance();break;case'branchops':b=branchOperations();break;case'dailyreport':b=dailyInventoryPage();break;case'operate':b=operate();break;case'pos':b=pos();break;case'daily':b=dailyImportPage();break;case'reconcile':b=reconcile();break;case'reorder':b=reorder();break;;case'inventory':b=inventory();break;case'warehouse':b=warehouse();break;
case'requests':b=requests();break;case'transfers':b=transfers();break;case'wholesale':b=wholesale();break;
case'returns':b=returns();break;case'closing':b=closing();break;case'reports':b=reports();break;case'intelligence':b=managementIntelligence();break;
case'products':b=products();break;case'products-import':b=productImportPage();break;case'calendar':b=businessCalendarPage();break;case'users':b=users();break;
case'settings':b=settings();break;case'control':b=systemControlPage();break;case'hr':b=state.setupStatus?.hr?.ready===true?hr():hrSchemaPendingPage();break;case'audit':b=audit();break;case'health':b=health();break;
default:b=dashboard()
}
const content=document.querySelector('#sl-content .sl-content-inner');
if(content){
 content.innerHTML=b;
 if(state.setupStatus?.hr_pending){
  const pending=document.createElement('div');
  pending.className='sl-notice sl-notice-warn sl-operational-schema-banner';
  pending.setAttribute('role','status');
  pending.textContent=state.setupStatus.hr_pending_approval
   ? 'HR lifecycle and Payroll extension are pending explicit active-Owner approval and qualified statutory review. Core, Inventory, POS and Finance/Procurement remain available on the verified 49-key operational baseline. No unapproved HR schema changes were made.'
   : 'HR lifecycle and Payroll extension remain in the separately approved setup/validation process. Core, Inventory, POS and Finance/Procurement remain available on the verified 49-key operational baseline.';
  content.prepend(pending);
 }
}
applyFinanceTableLabels();
bind();
if(state.view==='intelligence')bindManagementIntelligence();
if(state.view==='dashboard'&&state.boot.user?.role==='owner'&&state.boot.user?.capabilities?.wholesale)loadWholesaleSurplusAdvice(false);
if(state.view==='products' && !state.catalogLoading && !state.catalogSearchPending && (state.catalog===null || state.catalogQuery!==String(state.productQuery||'').trim())) {
  // Keep Product Master search server-backed; the transactional bootstrap list is not the catalog authority.
  loadProductCatalog();
}
}
function managementIntelligence(){
const user=state.boot?.user||{},caps=user.capabilities||{},d=state.boot?.dashboard||{};
if(!caps.finance_view||!['owner','management'].includes(String(user.role||'')))return '<div class="sl-panel"><div class="sl-notice sl-danger-block">Management Intelligence is restricted to authorized Owner and Management profiles.</div></div>';
const insights=state.dashboardInsights||{};
const asMoney=value=>'ETB '+new Intl.NumberFormat('en-ET',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(value||0));
const sum=(rows,key)=>Array.isArray(rows)?rows.reduce((total,row)=>total+Number(row?.[key]||0),0):0;
const trend=Array.isArray(insights.trend)?insights.trend:[];
const weekSales=sum(trend,'sales');
const monthSales=sum(insights.branches,'sales');
const status=state.dashboardInsightsLoading?'Refreshing authorized figures':(insights.range?'Loaded from the authorized ERP insights source':'Loading authorized data');
const kpi=(label,value,note,tone)=>'<div class="sl-card sl-dashboard-kpi-card sl-dashboard-kpi-'+tone+'"><div class="sl-dashboard-kpi-copy"><div class="label">'+esc(label)+'</div><div class="value" data-mi-kpi="'+esc(label)+'">'+esc(value)+'</div><div class="meta">'+esc(note)+'</div></div></div>';
return '<div class="sl-dashboard-page sl-management-intelligence">'+
 title('Management Intelligence','Decision support from authorized Stockland ERP records. Each metric retains the source module and ERP site-local date meaning.', '<button class="sl-btn" id="refreshManagementIntelligence" type="button">Refresh intelligence</button>')+
 '<div class="sl-dashboard-head-actions"><span class="sl-pill info">'+esc(status)+'</span><span class="sl-sub">'+esc(insights.range?.from||'')+(insights.range?.to?' to '+esc(insights.range.to):'')+' · closed retail sales only</span></div>'+
 '<div class="sl-grid sl-kpi-grid sl-dashboard-kpis">'+
 kpi('Retail sales · last 7 days',insights.range?asMoney(weekSales):'Loading…','Sum of closed retail sales by ERP site-local date','blue')+
 kpi('Retail sales · month to date',insights.range?asMoney(monthSales):'Loading…','Authorized branch sales; wholesale excluded from this metric','green')+
 kpi('Authorized branch rows',Array.isArray(insights.branches)?String(insights.branches.length):'Loading…','Rows returned within your reporting permissions','purple')+
 kpi('Low-stock snapshot',String(d.low_stock??'—'),'ERP bootstrap snapshot, not a substitute for the live reorder report','orange')+
 '</div>'+
 '<div class="sl-dashboard-workspace-grid"><div class="sl-dashboard-main"><div class="sl-dashboard-chart-grid">'+
 '<section class="sl-block sl-dashboard-card sl-dashboard-trend"><div class="sl-block-head"><div><h3>Retail sales trend</h3><small>Last 7 days · ETB</small></div><small>Closed retail sales only</small></div><div id="slDashboardSalesTrend" class="sl-dashboard-chart-body"><div class="sl-empty">Loading posted sales trend…</div></div></section>'+
 '<section class="sl-block sl-dashboard-card sl-dashboard-category"><div class="sl-block-head"><div><h3>Sales by category</h3><small>Month to date</small></div></div><div id="slDashboardCategories" class="sl-dashboard-chart-body"><div class="sl-empty">Loading category totals…</div></div></section>'+
 '</div><section class="sl-block sl-dashboard-card sl-dashboard-branch-stock"><div class="sl-block-head"><div><h3>Branch performance</h3><small>Month to date · authorized branch scope</small></div><button type="button" class="sl-btn sl-btn-sm" data-view="reports">Open Reports</button></div><div id="slDashboardBranches"><div class="sl-empty">Loading branch performance…</div></div></section>'+
 '<section class="sl-block sl-dashboard-card sl-dashboard-transactions"><div class="sl-block-head"><div><h3>Recent posted sales</h3><small>Recent rows in the source window; not a total transaction count</small></div><div class="sl-dashboard-head-actions"><small id="slDashboardInsightsStatus">'+esc(status)+'</small><button type="button" class="sl-btn sl-btn-sm" data-view="reports">Open reports</button></div></div><div id="slDashboardTransactions"><div class="sl-empty">Loading recent posted transactions…</div></div></section>'+
 '</div><aside class="sl-dashboard-side">'+
 '<section class="sl-block sl-dashboard-card"><div class="sl-block-head"><div><h3>Decision queue</h3><small>Review and use the authorized workflow; this page does not post transactions.</small></div></div><div class="sl-dashboard-quick-grid">'+
 '<button type="button" class="sl-dashboard-action" data-view="reorder"><span class="sl-dashboard-action-icon">'+uiIcon('box')+'</span><span><b>Critical stock & reorder</b><small>Review physical, reserved, available and inbound separately</small></span><span class="sl-control-arrow">›</span></button>'+
 '<button type="button" class="sl-dashboard-action" data-view="finance"><span class="sl-dashboard-action-icon">'+uiIcon('chart')+'</span><span><b>Finance & PLEDGER</b><small>Review posted accounting, balances and approvals</small></span><span class="sl-control-arrow">›</span></button>'+
 '<button type="button" class="sl-dashboard-action" data-view="hr"><span class="sl-dashboard-action-icon">'+uiIcon('users')+'</span><span><b>HR & compensation</b><small>Review current compensation and payroll readiness exceptions</small></span><span class="sl-control-arrow">›</span></button>'+
 '<button type="button" class="sl-dashboard-action" data-view="audit"><span class="sl-dashboard-action-icon">'+uiIcon('audit')+'</span><span><b>Audit trail</b><small>Trace posted records and authorized decisions</small></span><span class="sl-control-arrow">›</span></button>'+
 '<button type="button" class="sl-dashboard-action" data-view="health"><span class="sl-dashboard-action-icon">'+uiIcon('health')+'</span><span><b>System Health</b><small>Review schema and integrity exceptions</small></span><span class="sl-control-arrow">›</span></button>'+
 '</div></section><section class="sl-block sl-dashboard-card"><div class="sl-block-head"><div><h3>Metric scope & safeguards</h3></div></div><p class="sl-sub">Sales trend uses closed retail sales from the server-authorized insights endpoint. Branch rows remain location-scoped. Wholesale is excluded from these KPIs because the source does not provide reliable per-location ownership for this view. Low-stock is shown as a bootstrap snapshot only; use Critical Stock & Reorder for live item-level availability. Review the source report before making purchasing, staffing or financial decisions.</p></section>'+
 '</aside></div>'+
 '<section class="sl-block sl-dashboard-card" id="slMiSocial" aria-labelledby="slMiSocialTitle"><div class="sl-block-head"><div><h3 id="slMiSocialTitle">Social media analysis</h3><small>Official platform sign-in, campaign planning and timestamped export snapshots</small></div></div><div id="slMiSocialBody"><div class="sl-empty">Loading social workspace…</div></section></div>';
}

async function loadManagementSocial(){
 const body=document.getElementById('slMiSocialBody');if(!body)return;
 let social={campaigns:[],snapshots:[]},warning='';
 if(state.setupStatus?.hr?.ready!==true){warning='Campaign records and export snapshots become available after the HR/Payroll schema is approved and verified in System Control. Official platform sign-in links work now.'}else{try{social=await api16('/hr/management-social')}catch(e){warning='Campaign records are not available to this role: '+String(e.message||e)}}
 if(!document.getElementById('slMiSocialBody'))return;
 const canManage=!!(state.boot?.user?.capabilities?.hr_manage||['owner'].includes(String(state.boot?.user?.role||'')));
 let h='<h4 class="sl-sub"><b>Sign in on the official platform</b></h4>'+hrlcSocialLinks();
 if(warning)h+='<div class="sl-notice sl-notice-warn" style="margin-top:12px">'+esc(warning)+'</div>';
 else{
  h+='<h4 class="sl-sub" style="margin-top:16px"><b>Campaigns and export snapshots</b></h4>';
  if(canManage)h+=hrlcCycleForm('create-campaign','Create campaign',[['title','Campaign title','text','',true],['platform','Platform','select','INSTAGRAM',true,[['INSTAGRAM','Instagram'],['FACEBOOK','Facebook'],['TIKTOK','TikTok'],['YOUTUBE','YouTube'],['OTHER','Other']]],['account_label','Account label (not credentials)','text','',true],['start_date','Start date','date',localDate(),true],['end_date','End date','date','',false],['budget','Approved campaign budget ETB','number','0',true],['manager_user_id','Assigned influencer manager user ID','number','',false]],'Create campaign');
  h+=hrlcCycleTable(social.campaigns||[],[['campaign_code','Code'],['title','Campaign'],['platform','Platform'],['account_label','Account'],['budget','Budget','money'],['status','State','status']],r=>canManage?'<button class="sl-btn sl-btn-sm" type="button" data-hrcycle-action="capture-social" data-id="'+Number(r.id)+'" data-platform="'+hrlcEsc(r.platform)+'" data-account="'+hrlcEsc(r.account_label)+'">Record export snapshot</button>':'');
  h+=hrlcCycleTable(social.snapshots||[],[['snapshot_code','Snapshot'],['platform','Platform'],['account_label','Account'],['period_start','From'],['period_end','To'],['report_as_of','As of'],['reach_count','Reach'],['engagement_count','Engagement'],['spend_amount','Spend','money'],['attributed_sales','Attributed sales','money']],()=>'');
 }
 body.innerHTML=h;
 bindHRPayrollCycleForms();
}
function bindManagementIntelligence(){
 const btn=document.getElementById('refreshManagementIntelligence');
 if(btn&&!btn.__slMiBound){btn.__slMiBound=true;btn.addEventListener('click',async()=>{btn.disabled=true;btn.setAttribute('aria-busy','true');try{await loadDashboardInsights(true);await loadManagementSocial()}catch(e){toast(e.message||'Refresh failed',true)}finally{btn.disabled=false;btn.removeAttribute('aria-busy')}})}
 loadDashboardInsights(false);
 loadManagementSocial();
}
function updateManagementIntelligenceKpis(payload){
 if(state.view!=='intelligence')return;
 const trend=Array.isArray(payload?.trend)?payload.trend:[],branches=Array.isArray(payload?.branches)?payload.branches:[];
 const sum=(rows,key)=>rows.reduce((total,row)=>total+Number(row?.[key]||0),0);
 const asMoney=value=>'ETB '+new Intl.NumberFormat('en-ET',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(value||0));
 const values={ 'Retail sales · last 7 days':asMoney(sum(trend,'sales')), 'Retail sales · month to date':asMoney(sum(branches,'sales')), 'Authorized branch rows':String(branches.length) };
 document.querySelectorAll('[data-mi-kpi]').forEach(node=>{const key=node.getAttribute('data-mi-kpi');if(Object.prototype.hasOwnProperty.call(values,key))node.textContent=values[key]});
}

async function loadFinance(force){
const viewAtStart=state.view;
try{
const role=state.boot?.user?.role||'';
if(['branch_manager','warehouse_manager'].includes(role)){
const rows=await api('/accounting/expenses');const month=localDate().slice(0,7);
state.finance={expense_rows:Array.isArray(rows)?rows:[],expenses_month:(Array.isArray(rows)?rows:[]).filter(x=>String(x.expense_date||'').slice(0,7)===month&&x.status==='POSTED').reduce((n,x)=>n+Number(x.amount||0)+Number(x.tax_amount||0),0)};
}else{
const d=await api('/accounting/bootstrap');let rows=[];try{rows=await api('/accounting/expenses')}catch(reportError){toast('Expense report could not load: '+reportError.message,true)}state.finance={...d,expense_rows:Array.isArray(rows)?rows:[]};
}
state.financeLoaded=true;
if(force||(viewAtStart==='finance'&&state.view==='finance'))render();
}catch(e){toast(e.message,true)}
}
function dashboard(){
const d=state.boot.dashboard||{},brand=state.boot.brand||{},u=state.boot.user||{},c=u.capabilities||{};
const locations=Array.isArray(state.boot.locations)?state.boot.locations:[];
const stockRows=Array.isArray(state.boot.stock)?state.boot.stock:[];
const productRows=Array.isArray(state.boot.products)?state.boot.products:[];
const scope=u.can_cost?'MANAGEMENT VIEW':'OPERATIONAL VIEW';
const canDailyPos=['branch_manager','sales_employee'].includes(u.role)&&!!c.daily_import;
const actions=[
 ['pos','New Sale','Create POS sale',!!c.sale,'cash'],
 ['products','Add Product','Register new item',!!c.products,'product'],
 ['warehouse','Warehouse Receiving','Record stock entry',!!(c.receive_warehouse||c.warehouse),'warehouse'],
 ['finance','Purchase Order','Create purchase order',!!(c.manage_po||c.po_create||c.finance_view),'report'],
 ['wholesale','Owner Wholesale','Review protected surplus before a bulk sale',u.role==='owner'&&!!c.wholesale,'chart'],
 ['products-import','Bulk Import','Product master import',!!c.product_import,'upload'],
 ['reports','Reports','View analytics',!!(c.reports||u.can_cost),'report']
].filter(x=>x[3]);
const now=new Date();
const dateLabel=new Intl.DateTimeFormat('en-ET',{weekday:'short',day:'2-digit',month:'short',year:'numeric'}).format(now);
const timeLabel=new Intl.DateTimeFormat('en-ET',{hour:'2-digit',minute:'2-digit'}).format(now);
const insights=state.dashboardInsights||{};
const branchLocations=locations.filter(x=>String(x.location_type||'').toLowerCase()==='branch');
const branchCount=branchLocations.length;
const activeBranchCount=branchLocations.filter(x=>x.is_active===undefined||Number(x.is_active)===1).length;
const dashboardStatus=state.dashboardInsightsLoading?'Refreshing analytics':(insights.range?'Updated from posted ERP sales':'Authorized live data');
const firstName=String(u.name||'there').trim().split(/\s+/)[0]||'there';
const salesKpi='ETB '+new Intl.NumberFormat('en-ET',{maximumFractionDigits:0}).format(Number(d.today_sales||0));
const metricCard=(icon,label,value,meta,tone)=>'<div class="sl-card sl-dashboard-kpi-card sl-dashboard-kpi-'+tone+'"><span class="sl-dashboard-kpi-icon">'+uiIcon(icon)+'</span><div class="sl-dashboard-kpi-copy"><div class="label">'+esc(label)+'</div><div class="value">'+esc(value)+'</div><div class="meta">'+esc(meta)+'</div></div></div>';
const bySku=new Map(productRows.map(x=>[String(x.sku||''),x]));
const byLocation=new Map(locations.map(x=>[String(x.id),x]));
const lowRows=stockRows.map(r=>{
 const product=bySku.get(String(r.sku||''))||r;
 const loc=byLocation.get(String(r.location_id))||{};
 const reorder=String(loc.location_type||'').toLowerCase()==='warehouse'?Number(product.central_reorder_level||0):Number(product.branch_reorder_level||0);
 return {...r,_location:loc,_reorder:reorder,_available:Number(r.available_stock??Math.max(0,Number(r.physical_stock||0)-Number(r.reserved_stock||0)))};
}).filter(r=>r._reorder>0&&r._available<=r._reorder).sort((a,b)=>Number(a._available||0)-Number(b._available||0)).slice(0,4);
const lowStockList=lowRows.length?lowRows.map(r=>'<div class="sl-dashboard-low-row"><span class="sl-dashboard-low-icon">'+uiIcon('box')+'</span><div class="sl-dashboard-low-copy"><b>'+esc(r.product_name||r.sku||'Product')+'</b><small>'+esc(r.sku||'')+' · '+esc(r._location.location_name||'Authorized location')+'</small></div><span class="sl-dashboard-low-qty '+(r._available<=0?'is-zero':'')+'">'+esc(Number(r._available||0).toLocaleString('en-ET',{maximumFractionDigits:2}))+' left</span></div>').join(''):'<div class="sl-dashboard-empty-state"><span class="sl-dashboard-ok-mark">✓</span><div><b>'+(Number(d.low_stock||0)>0?'Low-stock review needed':'Stock levels look healthy')+'</b><small>'+(Number(d.low_stock||0)>0?esc(d.low_stock)+' item(s) are within the configured reorder threshold. Open Reorder to review the complete server-scoped list.':'No low-stock items were reported in the current authorized snapshot.')+'</small></div></div>';
const branchSummary=locations.filter(l=>String(l.location_type||'').toLowerCase()==='branch'||String(l.location_type||'').toLowerCase()==='warehouse').slice(0,4).map(l=>{
 const rows=stockRows.filter(r=>Number(r.location_id)===Number(l.id));
 const physical=rows.reduce((sum,r)=>sum+Number(r.physical_stock||0),0);
 const available=rows.reduce((sum,r)=>sum+Number(r.available_stock??Math.max(0,Number(r.physical_stock||0)-Number(r.reserved_stock||0))),0);
 return {...l,_physical:physical,_available:available,_lines:rows.length};
});
const maxBranchStock=Math.max(1,...branchSummary.map(l=>l._physical));
const branchMarkup=branchSummary.length?'<div class="sl-dashboard-branch-table-head"><span>Branch</span><span>Total Stock</span></div>'+branchSummary.map(l=>'<div class="sl-dashboard-branch-row"><div class="sl-dashboard-branch-head"><span class="sl-dashboard-branch-dot"></span><span class="sl-dashboard-branch-name">'+esc(l.location_name)+'</span><b>'+esc(l._physical.toLocaleString('en-ET',{maximumFractionDigits:2}))+'</b></div><div class="sl-dashboard-branch-track"><i style="width:'+Math.min(100,l._physical/maxBranchStock*100).toFixed(2)+'%"></i></div><small>'+esc(l._lines.toLocaleString('en-ET'))+' stock line(s) · '+esc(l._available.toLocaleString('en-ET',{maximumFractionDigits:2}))+' available</small></div>').join(''):'<div class="sl-empty">No stock totals are available for the current location scope.</div>';
const alerts=Number(d.low_stock||0)>0
 ? '<div class="sl-dashboard-alert-banner is-danger"><span class="sl-dashboard-alert-symbol">!</span><div><b>'+esc(d.low_stock)+' low-stock item(s)</b><small>Sellable available stock (after active reservations) is at or below its configured reorder threshold.</small></div><button class="sl-btn sl-btn-sm" data-view="reorder" type="button">View all</button></div>'
 : '<div class="sl-dashboard-alert-banner is-ok"><span class="sl-dashboard-alert-symbol">✓</span><div><b>No low-stock alerts</b><small>Based on the current authorized inventory snapshot.</small></div></div>';
return '<div class="sl-dashboard-page">'+
 '<section class="sl-dashboard-welcome"><div class="sl-dashboard-welcome-copy"><span class="sl-dashboard-welcome-mark"></span><div><div class="sl-eyebrow">STOCKLAND ETHIOPIA · OPERATIONS</div><h2>Welcome back, '+esc(firstName)+'!</h2><p>Here’s what’s happening in your business today.</p></div></div><div class="sl-dashboard-date"><span class="sl-dashboard-date-icon">'+uiIcon('calendar')+'</span><div><b>'+esc(dateLabel)+'</b><small>'+esc(timeLabel)+'</small></div><button class="sl-btn sl-btn-sm" id="refresh" type="button">Refresh</button></div></section>'+
 '<div class="sl-grid sl-kpi-grid sl-dashboard-kpis">'+
 metricCard('cart',"Today's Sales",salesKpi,'Closed retail transactions','blue')+
 metricCard('box','Total Inventory',Number(d.stock_total||0).toLocaleString('en-ET',{maximumFractionDigits:2}),'Physical stock units','green')+
 metricCard('bell','Low Stock Items',String(d.low_stock||0),'Configured reorder thresholds','orange')+
 metricCard('users','Active Branches',activeBranchCount+' / '+branchCount,activeBranchCount===branchCount?'All active branches online':(activeBranchCount+' active of '+branchCount+' configured'),'purple')+
 '</div>'+
 '<div class="sl-dashboard-workspace-grid"><div class="sl-dashboard-main">'+
 '<div class="sl-dashboard-chart-grid">'+
 '<section class="sl-block sl-dashboard-card sl-dashboard-trend"><div class="sl-block-head"><div><h3>Sales Overview</h3><small>Last 7 days · ETB</small></div><small>Authorized sales</small></div><div id="slDashboardSalesTrend" class="sl-dashboard-chart-body"><div class="sl-empty">Loading posted sales trend…</div></div></section>'+
 '<section class="sl-block sl-dashboard-card sl-dashboard-category"><div class="sl-block-head"><div><h3>Sales by Category</h3></div><small>Current month</small></div><div id="slDashboardCategories" class="sl-dashboard-chart-body"><div class="sl-empty">Loading category totals…</div></div></section>'+
 '</div>'+
 '<section class="sl-block sl-dashboard-card sl-dashboard-quick"><div class="sl-block-head"><div><h3>Quick Actions</h3><small>Access the most used features</small></div></div><div class="sl-dashboard-quick-grid">'+actions.map(x=>'<button class="sl-dashboard-action" type="button" data-view="'+x[0]+'"><span class="sl-dashboard-action-icon">'+uiIcon(x[4])+'</span><span><b>'+esc(x[1])+'</b><small>'+esc(x[2])+'</small></span><span class="sl-control-arrow">›</span></button>').join('')+'</div></section>'+
 '<section class="sl-block sl-dashboard-card sl-dashboard-transactions"><div class="sl-block-head"><div><h3>Recent Transactions</h3><small>Latest posted retail sales</small></div><div class="sl-dashboard-head-actions"><small id="slDashboardInsightsStatus">'+esc(dashboardStatus)+'</small><button type="button" class="sl-btn sl-btn-sm" data-view="reports">View all transactions</button></div></div><div id="slDashboardTransactions"><div class="sl-empty">Loading recent posted transactions…</div></div></section>'+
 '</div><aside class="sl-dashboard-side">'+
 '<section class="sl-block sl-dashboard-card sl-dashboard-low-stock"><div class="sl-block-head"><div><h3>Low Stock Alert</h3></div><button class="sl-link-btn" type="button" data-view="reorder">View all</button></div><div class="sl-dashboard-low-list">'+lowStockList+'</div></section>'+
 '<section class="sl-block sl-dashboard-card sl-dashboard-branch-stock"><div class="sl-block-head"><div><h3>Branch Stock Summary</h3></div><button class="sl-link-btn" type="button" data-view="inventory">View all</button></div><div class="sl-dashboard-branch-list">'+branchMarkup+'</div></section>'+
 '<section class="sl-block sl-dashboard-card sl-dashboard-activity"><div class="sl-block-head"><div><h3>Recent Activity</h3></div><button class="sl-link-btn" type="button" data-view="audit">View all</button></div><div id="slActivityFeed"><div class="sl-skeleton-feed"><div></div><div></div><div></div><div></div></div></div></section>'+
 '</aside></div>'+
 (u.role==='owner'&&c.wholesale?'<section class="sl-block sl-dashboard-card sl-owner-wholesale-advice" id="ownerWholesaleAdvicePanel"><div class="sl-block-head"><div><div class="sl-eyebrow">OWNER DECISION DESK</div><h3>Wholesale surplus &amp; branch protection</h3><small>Read-only advisory · no stock is reserved or sold by this calculation</small></div><div class="sl-owner-advice-head-actions"><span class="sl-pill info">OWNER ONLY</span><button type="button" id="refreshWholesaleAdvice" class="sl-btn sl-btn-sm">Recalculate</button></div></div><div id="ownerWholesaleAdviceBody"><div class="sl-empty">Checking branch needs, open requests and the warehouse reserve floor…</div></div></section>':'')+
 '<footer class="sl-dashboard-footer sl-dashboard-promo"><div class="sl-dashboard-promo-image" aria-hidden="true"></div><div class="sl-dashboard-promo-copy"><span class="sl-dashboard-promo-kicker">SMARTER OPERATIONS</span><b>Everything you need. In one place.</b><span>Manage stock, sales, procurement and people across authorized locations — with live operational records.</span></div><button class="sl-btn sl-dashboard-promo-action" type="button" data-view="operate">Explore ERP features <span aria-hidden="true">→</span></button></footer>'+
 '</div>';
}
function surplusQty(value){const n=Number(value);return Number.isFinite(n)?new Intl.NumberFormat('en-ET',{maximumFractionDigits:2}).format(n):'—'}
function renderWholesaleSurplus(payload){
const summary=payload?.summary||{},rows=Array.isArray(payload?.rows)?payload.rows:[];
const n=key=>surplusQty(summary[key]??0);
const metric=(label,value,meta,kind)=>'<div class="sl-owner-advice-metric '+kind+'"><span>'+esc(label)+'</span><b>'+esc(value)+'</b><small>'+esc(meta)+'</small></div>';
const totals='<div class="sl-owner-advice-metrics">'+metric('SKUs reviewed',n('sku_count'),'Active stockable variants','blue')+metric('Potential wholesale SKUs',n('potential_sku_count'),'Safe surplus above internal need','green')+metric('Potential surplus units',n('safe_wholesale_units'),'After protected needs and central floor','teal')+metric('Branches needing top-up',n('branch_need_skus'),'Across all active branches','amber')+metric('Pending requested units',n('pending_request_units'),'Requests not yet fulfilled','purple')+metric('Integrity holds',n('integrity_hold_skus'),'Withheld until reconciled','red')+'</div>';
if(!rows.length)return totals+'<div class="sl-empty-rich"><b>No advisory rows were returned.</b><span>No sale quantity is suggested when the source query cannot resolve active product/location scope. Retry after verifying System Health.</span></div>';
const filtered=rows.filter(r=>{const c=r.internal_commitments||{};const queueNeed=['unapproved_requisitions','approved_unreserved_requisitions','direct_transfer_requests','approved_unreserved_transfers'].some(k=>Number(c[k]||0)>0);return Number(r.central_physical_qty||0)>0||Number(r.central_reserved_qty||0)>0||queueNeed||Number(r.wholesale_surplus_qty||0)>0||r.integrity_hold;});
const trs=filtered.map(r=>{
 const branches=Array.isArray(r.branches)?r.branches:[];
 const branchRows=branches.map(b=>'<tr><td>'+esc(b.location_name)+'</td><td>'+surplusQty(b.available_qty)+'</td><td>'+surplusQty(b.reorder_level)+'</td><td>'+surplusQty(b.pending_inbound_qty)+'</td><td>'+surplusQty(b.open_request_qty)+'</td><td>'+surplusQty(b.approved_uncollected_qty)+'</td><td>'+surplusQty(b.additional_topup_need_qty)+'</td></tr>').join('');
 const q=Number(r.wholesale_surplus_qty||0),hold=!!r.integrity_hold,status=String(r.status||'NO_SAFE_SURPLUS');
 const label=hold?'INTEGRITY HOLD':q>0?'SURPLUS AVAILABLE':(Number(r.branch_protected_need_qty||0)>0?'PROTECT INTERNAL NEED':'NO SAFE SURPLUS');
 const attrSearch=[r.sku,r.product_name,r.brand,r.color,r.size,...branches.map(b=>b.location_name)].join(' ').toLowerCase();
 const search=esc(attrSearch);
 const branchDetail='<details class="sl-owner-branch-detail"><summary>Branch-by-branch protection ('+branches.length+')</summary><div class="sl-table-wrap"><table class="sl-table"><thead><tr><th>Branch</th><th>Available</th><th>Reorder floor</th><th>In transit</th><th>Open request</th><th>Approved uncollected</th><th>Extra top-up</th></tr></thead><tbody>'+branchRows+'</tbody></table></div></details>';
 const action=hold?'<button class="sl-btn sl-btn-sm" disabled title="Resolve stock/ledger integrity before sale">Hold</button>':'<button type="button" class="sl-btn '+(q>0?'primary':'')+' sl-btn-sm" data-wholesale-prepare="'+esc(r.sku)+'">Review sale</button>';
 return '<tr data-wholesale-row data-status="'+esc(status)+'" data-search="'+search+'"><td data-label="Product"><b>'+esc(r.product_name||'Product')+'</b><div class="sl-sub">'+esc(r.sku)+' · '+esc([r.brand,r.color,r.size].filter(Boolean).join(' / ')||'Variant')+'</div>'+branchDetail+'</td><td data-label="Central stock"><b>'+surplusQty(r.central_physical_qty)+'</b><div class="sl-sub">Reserved '+surplusQty(r.central_reserved_qty)+' · Available '+surplusQty(r.central_available_qty)+'</div></td><td data-label="Keep at warehouse"><b>'+surplusQty(r.central_reorder_level)+'</b><div class="sl-sub">Configured Central Warehouse reorder level</div></td><td data-label="Internal commitments"><b>'+surplusQty(r.internal_commitment_total_qty)+'</b><div class="sl-sub">Branch needs '+surplusQty(r.branch_protected_need_qty)+'</div></td><td data-label="Suggested surplus"><span class="sl-owner-status '+(hold?'hold':q>0?'safe':'protect')+'">'+esc(label)+'</span><div class="sl-owner-surplus-number">'+surplusQty(q)+' <small>'+esc(r.unit||'units')+'</small></div></td><td data-label="Owner decision"><div class="sl-owner-advice-edit"><label>Sale qty<input type="number" min="0" step="1" max="'+esc(q)+'" value="'+esc(q)+'" data-wholesale-qty-for="'+esc(r.sku)+'" '+(hold?'disabled':'')+' aria-label="Owner-selected wholesale quantity for '+esc(r.sku)+'"></label>'+action+'</div></td></tr>';
}).join('');
return totals+'<div class="sl-owner-advice-controls"><input id="wholesaleAdviceSearch" type="search" placeholder="Search SKU, product, brand, color, size or branch…" aria-label="Search surplus recommendations"><select id="wholesaleAdviceFilter" aria-label="Filter surplus recommendations"><option value="all">All needs and surplus</option><option value="SURPLUS_AVAILABLE">Surplus available</option><option value="PROTECT_INTERNAL_NEED">Protect internal need</option><option value="NO_SAFE_SURPLUS">No safe surplus</option><option value="INTEGRITY_HOLD">Integrity hold</option></select><span>Checked '+esc(payload.checked_at||'')+'</span></div><div class="sl-notice sl-notice-soft sl-owner-advice-safety"><b>Advisory only.</b> The model protects every active branch’s per-SKU reorder level—even without a submitted request—then accounts for open requests, approved-uncollected quantities, active reservations, in-transit branch supply, and the Central Warehouse reorder floor. It does not reserve or sell anything. The Owner makes the final decision.</div><div class="sl-table-wrap sl-owner-advice-table-wrap"><table class="sl-table sl-owner-advice-table"><thead><tr><th>Product / branch detail</th><th>Central ledger qty</th><th>Warehouse floor</th><th>Protected internal need</th><th>Potential surplus</th><th>Owner decision</th></tr></thead><tbody>'+trs+'</tbody></table></div><div class="sl-owner-advice-empty sl-empty" hidden>No rows match the current search/filter.</div>';
}
function bindWholesaleSurplusAdvice(host){
 if(!host||host.__slWholesaleBound)return;host.__slWholesaleBound=true;
 const search=host.querySelector('#wholesaleAdviceSearch'),filter=host.querySelector('#wholesaleAdviceFilter'),empty=host.querySelector('.sl-owner-advice-empty');
 const apply=()=>{const q=String(search?.value||'').trim().toLowerCase(),f=String(filter?.value||'all');let visible=0;host.querySelectorAll('[data-wholesale-row]').forEach(row=>{const ok=(!q||String(row.dataset.search||'').includes(q))&&(f==='all'||row.dataset.status===f);row.hidden=!ok;if(ok)visible++;});if(empty)empty.hidden=visible>0;};
 search?.addEventListener('input',apply);filter?.addEventListener('change',apply);
 host.querySelectorAll('[data-wholesale-prepare]').forEach(button=>button.addEventListener('click',()=>{
  const sku=String(button.dataset.wholesalePrepare||''),row=button.closest('tr'),qty=Number(row?.querySelector('[data-wholesale-qty-for]')?.value||0),advice=state.wholesaleAdvice?.rows?.find(x=>String(x.sku)===sku);
  if(!sku||!advice||advice.integrity_hold){toast('No sale prefill: current stock has an integrity hold or advice is unavailable.',true);return;}
  if(!Number.isFinite(qty)||qty<=0){toast('Enter a positive quantity to review. This does not create a sale.',true);return;}
  if(qty>Number(advice.wholesale_surplus_qty||0)){const ok=window.confirm('You selected '+qty+' units, above the current suggested surplus of '+Number(advice.wholesale_surplus_qty||0)+'. Continue to the Owner sale form? You will need to provide a reason and the server will recheck stock before any sale.');if(!ok)return;}
  state.wholesalePrefill={sku,quantity:qty,price:Number(advice.wholesale_price||0),advisory_surplus_qty:Number(advice.wholesale_surplus_qty||0)};state.view='wholesale';render();
 }));
}
async function loadWholesaleSurplusAdvice(force=false){
 const target=document.getElementById('ownerWholesaleAdviceBody');
 if(!target||state.view!=='dashboard'||state.boot?.user?.role!=='owner'||!state.boot?.user?.capabilities?.wholesale)return;
 if(state.wholesaleAdviceLoading)return;
 if(state.wholesaleAdvice&&!force){target.innerHTML=renderWholesaleSurplus(state.wholesaleAdvice);bindWholesaleSurplusAdvice(target);return;}
 const request=++state.wholesaleAdviceRequestId;state.wholesaleAdviceLoading=true;state.wholesaleAdviceError='';
 target.innerHTML='<div class="sl-empty">Rechecking ledger stock, reservations, branch reorder levels, open requests and warehouse commitments…</div>';
 try{
  const payload=await api('/wholesale/surplus-advice');
  if(request!==state.wholesaleAdviceRequestId||state.view!=='dashboard')return;
  if(!payload||payload.read_only!==true||!Array.isArray(payload.rows)||!payload.summary)throw new Error('The server did not confirm a complete read-only surplus response. No sale recommendation is shown.');
  state.wholesaleAdvice=payload;target.innerHTML=renderWholesaleSurplus(payload);bindWholesaleSurplusAdvice(target);
 }catch(e){if(request!==state.wholesaleAdviceRequestId)return;state.wholesaleAdvice=null;state.wholesaleAdviceError=String(e.message||'');target.innerHTML='<div class="sl-notice sl-danger-block"><b>Wholesale advice unavailable — no sale is suggested.</b><p>'+esc(state.wholesaleAdviceError||'The server could not confirm stock and internal need.')+'</p><button type="button" class="sl-btn sl-btn-sm" id="retryWholesaleAdvice">Retry read-only calculation</button></div>';const hostButton=document.getElementById('retryWholesaleAdvice');if(hostButton)hostButton.onclick=()=>loadWholesaleSurplusAdvice(true);
 }finally{if(request===state.wholesaleAdviceRequestId)state.wholesaleAdviceLoading=false;}
}

function dashboardTrendHtml(payload){
const rows=Array.isArray(payload?.trend)?payload.trend:[];
if(!rows.length)return '<div class="sl-empty">No sales-trend data was returned for the authorized scope.</div>';
const values=rows.map(x=>Math.max(0,Number(x.sales||0))),rawMax=Math.max(1,...values),axisStep=Math.pow(10,Math.floor(Math.log10(rawMax))),max=Math.max(axisStep*4,Math.ceil(rawMax/(axisStep*4))*(axisStep*4)),left=48,right=624,top=18,bottom=184;
const x=i=>left+(rows.length<2?0:i*(right-left)/(rows.length-1));
const y=v=>bottom-(v/max)*(bottom-top);
const path=values.map((v,i)=>(i?'L':'M')+x(i).toFixed(2)+' '+y(v).toFixed(2)).join(' ');
const area=path+' L '+right+' '+bottom+' L '+left+' '+bottom+' Z';
const grid=[0,0.25,0.5,0.75,1].map(k=>{const yy=bottom-k*(bottom-top);const val=max*k;return '<line x1="'+left+'" y1="'+yy.toFixed(2)+'" x2="'+right+'" y2="'+yy.toFixed(2)+'" stroke="#e8eef6" stroke-width="1"/><text x="39" y="'+(yy+3).toFixed(2)+'" text-anchor="end" font-size="10" fill="#73839a">'+esc(new Intl.NumberFormat('en-ET',{notation:'compact',maximumFractionDigits:1}).format(val))+'</text>'}).join('');
const points=values.map((v,i)=>'<circle cx="'+x(i).toFixed(2)+'" cy="'+y(v).toFixed(2)+'" r="3.5" fill="#2563eb" stroke="#fff" stroke-width="2"><title>'+esc(rows[i].date)+': '+esc(money(v))+'</title></circle>').join('');
const labels=rows.map((r,i)=>{const dt=new Date(String(r.date||'')+'T12:00:00');const label=Number.isNaN(dt.getTime())?String(r.date||''):new Intl.DateTimeFormat('en-ET',{month:'short',day:'numeric'}).format(dt);return '<text x="'+x(i).toFixed(2)+'" y="211" text-anchor="middle" font-size="10" fill="#73839a">'+esc(label)+'</text>'}).join('');
const total=values.reduce((a,v)=>a+v,0);
return '<svg class="sl-dashboard-linechart" viewBox="0 0 650 228" role="img" aria-label="Posted retail sales for the last seven dates"><title>Posted retail sales by date</title><desc>Values are retrieved from closed retail sales in the authorized location scope.</desc>'+grid+'<path d="'+area+'" fill="#eaf2ff"/><path d="'+path+'" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>'+points+labels+'</svg>';
}
function dashboardCategoryHtml(payload){
const all=Array.isArray(payload?.categories)?payload.categories.map(x=>({category:String(x.category||'Uncategorized'),sales:Math.max(0,Number(x.sales||0))})).filter(x=>x.sales>0):[];
const total=all.reduce((sum,x)=>sum+x.sales,0);
if(!all.length||total<=0)return '<div class="sl-empty">No posted category sales this month. The chart will populate from actual posted transactions.</div>';
const colors=['#2563eb','#10b981','#f59e0b','#8b5cf6','#64748b','#0ea5e9','#ef4444','#94a3b8'];
let cursor=0;
const stops=all.map((x,i)=>{const a=cursor;cursor+=x.sales/total*100;return colors[i%colors.length]+' '+a.toFixed(3)+'% '+cursor.toFixed(3)+'%'}).join(',');
const legend=all.map((x,i)=>'<div class="sl-dashboard-category-row"><span class="sl-dashboard-legend-dot" style="--sl-category-color:'+colors[i%colors.length]+'"></span><span>'+esc(x.category)+'</span><b>'+Math.round(x.sales/total*100)+'%</b><small>'+esc(money(x.sales))+'</small></div>').join('');
return '<div class="sl-dashboard-donut-wrap"><div class="sl-dashboard-donut" style="background:conic-gradient('+stops+')"><div><small>ETB</small><b>'+esc(new Intl.NumberFormat('en-ET',{maximumFractionDigits:0}).format(total))+'</b></div></div><div class="sl-dashboard-category-legend">'+legend+'</div></div>';
}
function dashboardBranchesHtml(payload){
const rows=Array.isArray(payload?.branches)?payload.branches:[];
if(!rows.length)return '<div class="sl-empty">No active branches are available in the current reporting scope.</div>';
const maximum=Math.max(1,...rows.map(x=>Math.max(0,Number(x.sales||0))));
return '<div class="sl-dashboard-performance-list">'+rows.map((x,i)=>{const amount=Math.max(0,Number(x.sales||0)),percent=amount/maximum*100;return '<div class="sl-dashboard-performance-row"><div class="sl-dashboard-performance-head"><span class="sl-dashboard-location-pin">'+uiIcon('home')+'</span><div><b>'+esc(x.location_name||'Authorized location')+'</b><small>'+esc(money(amount))+'</small></div><strong>'+Math.round(percent)+'%</strong></div><div class="sl-dashboard-performance-track"><i style="width:'+percent.toFixed(2)+'%"></i></div></div>'}).join('')+'</div>';
}
function dashboardTransactionsHtml(payload){
const rows=Array.isArray(payload?.recent_sales)?payload.recent_sales:[];
if(!rows.length)return '<div class="sl-empty">No posted retail transactions were found in the recent 30-day window.</div>';
return '<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Date &amp; Time</th><th>Type</th><th>Document No.</th><th>Branch</th><th>Total Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>'+rows.map((x,i)=>{
 const id=String(x.sale_id||'');
 const attrs=' data-sale-id="'+esc(id)+'" data-sale-date="'+esc(String(x.sale_date||'').slice(0,10))+'" data-location-id="'+esc(x.location_id||'')+'"';
 return '<tr><td data-label="Date & Time">'+esc(x.sale_date||'')+'</td><td data-label="Type"><span class="sl-dashboard-transaction-type">'+uiIcon('cart')+' Sales</span></td><td data-label="Document No."><b>'+esc(id)+'</b></td><td data-label="Branch">'+esc(x.location_name||'—')+'</td><td data-label="Total Amount"><b>'+esc(money(x.sales_total||0))+'</b></td><td data-label="Status"><span class="sl-pill '+(String(x.posting_status||'CLOSED').toUpperCase()==='CLOSED'?'ok':'warn')+'">'+esc(x.posting_status||'CLOSED')+'</span></td><td data-label="Action"><div class="sl-dashboard-sale-actions"><button type="button" class="sl-btn sl-btn-sm sl-dashboard-sale-view" data-dashboard-sale-view'+attrs+'>View</button><button type="button" class="sl-btn sl-btn-sm sl-dashboard-sale-more" data-dashboard-sale-more="'+i+'" aria-label="More actions for '+esc(id)+'" aria-expanded="false" aria-controls="slDashboardSaleMenu'+i+'">⌄</button><div class="sl-dashboard-sale-menu" id="slDashboardSaleMenu'+i+'" hidden><button type="button" data-dashboard-sale-view'+attrs+'>Open in POS</button><button type="button" data-dashboard-sale-copy="'+esc(id)+'">Copy document number</button></div></div></td></tr>';
}).join('')+'</tbody></table></div>';
}
async function loadDashboardInsights(force=false){
const host=document.getElementById('slDashboardSalesTrend');if(!host||!['dashboard','intelligence'].includes(state.view)||!state.boot)return;
if(state.dashboardInsightsLoading&&!force)return;
const requestId=++state.dashboardInsightsRequestId;state.dashboardInsightsLoading=true;
const status=document.getElementById('slDashboardInsightsStatus');if(status)status.textContent='Loading authorized data…';
try{
 const payload=await api('/app/dashboard/insights');
 if(requestId!==state.dashboardInsightsRequestId||!['dashboard','intelligence'].includes(state.view))return;
 state.dashboardInsights=payload||{};
 const target=(id,html)=>{const node=document.getElementById(id);if(node)node.innerHTML=html};
 target('slDashboardSalesTrend',dashboardTrendHtml(state.dashboardInsights));
 target('slDashboardCategories',dashboardCategoryHtml(state.dashboardInsights));
 target('slDashboardBranches',dashboardBranchesHtml(state.dashboardInsights));
 target('slDashboardTransactions',dashboardTransactionsHtml(state.dashboardInsights));
 document.querySelectorAll('[data-dashboard-sale-view]').forEach(b=>{if(b.__slDashboardSaleViewBound)return;b.__slDashboardSaleViewBound=true;b.addEventListener('click',()=>{const saleId=String(b.dataset.saleId||'').trim();if(!saleId)return;state.globalSearchSelection={type:'sale',query:saleId,title:saleId,saleDate:String(b.dataset.saleDate||''),locationId:String(b.dataset.locationId||'')};state.view='pos';render();});});
 document.querySelectorAll('[data-dashboard-sale-more]').forEach(b=>{if(b.__slDashboardSaleMoreBound)return;b.__slDashboardSaleMoreBound=true;b.addEventListener('click',e=>{e.stopPropagation();const menu=document.getElementById(b.getAttribute('aria-controls'));if(!menu)return;const open=menu.hidden;document.querySelectorAll('.sl-dashboard-sale-menu').forEach(m=>{m.hidden=true;});document.querySelectorAll('[data-dashboard-sale-more]').forEach(x=>x.setAttribute('aria-expanded','false'));menu.hidden=!open;b.setAttribute('aria-expanded',String(open));});});
 document.querySelectorAll('[data-dashboard-sale-copy]').forEach(b=>{if(b.__slDashboardSaleCopyBound)return;b.__slDashboardSaleCopyBound=true;b.addEventListener('click',async()=>{const value=String(b.getAttribute('data-dashboard-sale-copy')||'');try{if(!navigator.clipboard?.writeText)throw new Error('Clipboard is unavailable in this browser context.');await navigator.clipboard.writeText(value);toast('Document number copied');}catch(e){toast(e.message||'Could not copy document number.',true);}const menu=b.closest('.sl-dashboard-sale-menu');if(menu)menu.hidden=true;});});
 if(!document.__slDashboardSaleMenuDismissBound){document.__slDashboardSaleMenuDismissBound=true;document.addEventListener('click',e=>{if(e.target.closest('.sl-dashboard-sale-actions'))return;document.querySelectorAll('.sl-dashboard-sale-menu').forEach(m=>{m.hidden=true;});document.querySelectorAll('[data-dashboard-sale-more]').forEach(b=>b.setAttribute('aria-expanded','false'));});document.addEventListener('keydown',e=>{if(e.key!=='Escape')return;document.querySelectorAll('.sl-dashboard-sale-menu').forEach(m=>{m.hidden=true;});document.querySelectorAll('[data-dashboard-sale-more]').forEach(b=>{b.setAttribute('aria-expanded','false');});});}
 const st=document.getElementById('slDashboardInsightsStatus');if(st)st.textContent=payload?.range?'Live • '+String(payload.range.from||'')+' to '+String(payload.range.to||''):'Live insights';updateManagementIntelligenceKpis(state.dashboardInsights);
}catch(e){
 if(requestId!==state.dashboardInsightsRequestId)return;
 const msg=esc(e.message||'Dashboard analytics are unavailable.');
 ['slDashboardSalesTrend','slDashboardCategories','slDashboardBranches','slDashboardTransactions'].forEach(id=>{const node=document.getElementById(id);if(node)node.innerHTML='<div class="sl-empty">'+msg+'</div>'});
 const st=document.getElementById('slDashboardInsightsStatus');if(st)st.textContent='Analytics unavailable';
}finally{
 if(requestId===state.dashboardInsightsRequestId)state.dashboardInsightsLoading=false;
}
}

/* 16.1.44 production runtime. Retail POS and external Daily POS reconciliation share server-side posting rules. */
function stockTable(rows){return '<div class="sl-table-wrap"><table class="sl-table"><thead><tr><th>Style</th><th>Variant SKU</th><th>Color</th><th>Size</th><th>Barcode</th><th>Physical</th><th>Reserved</th><th>Available</th><th>Price</th>'+'</tr></thead><tbody>'+rows.map(r=>'<tr><td><b>'+esc(r.style_code||r.parent_sku||r.sku)+'</b><div class="sl-sub">'+esc(r.product_name)+'</div></td><td>'+esc(r.sku)+'</td><td>'+esc(r.color||'—')+'</td><td>'+esc(r.size||'—')+'</td><td>'+esc(r.barcode||r.gtin||'—')+'</td><td>'+r.physical_stock+'</td><td>'+r.reserved_stock+'</td><td><span class="sl-pill '+(Number(r.available_stock)<=0?'bad':'ok')+'">'+r.available_stock+'</span></td><td>'+money(r.retail_price)+'</td>'+'</tr>').join('')+'</tbody></table></div>'}
function reconcile(){
const c=state.boot?.user?.capabilities||{},u=state.boot?.user||{},locations=authorizedLocations().filter(l=>['Branch','Warehouse'].includes(l.location_type)&&Number(l.is_active)!==0),primary=Number(u.branch_location_id||0);
const locationOptions=locations.map(l=>'<option value="'+esc(l.id)+'" '+(Number(l.id)===primary?'selected':'')+'>'+esc(l.location_name)+' • '+esc(l.location_type)+'</option>').join('');
const canSubmit=!!(c.request_adjustment||c.settings),canReviewRecon=!!(c.settings||u.can_all||['management','branch_manager','auditor','owner'].includes(u.role)),canViewCounts=!!(c.reports||c.warehouse||u.can_all||c.settings);
const countUpload=canSubmit?'<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">PHYSICAL COUNT • XLSX</div><h3>Upload count workbook</h3><small>Preview and validate all rows before an explicit commit.</small></div></div><form id="countUploadForm" class="sl-form"><div class="sl-field full"><label for="countXlsxFile">Physical Count workbook (.xlsx)</label><input id="countXlsxFile" name="file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required><small class="sl-sub">Use a PHYSICAL COUNT worksheet. Required fields include Count Date, Location, Product identity (SKU or Brand + Product Name + Size + Color), and Quantity Found.</small></div><div class="sl-field full"><button class="sl-btn primary" type="submit">PREVIEW WORKBOOK</button></div></form><div id="countPreview" aria-live="polite" style="margin-top:12px"></div></div>':'<div class="sl-notice">Your ERP profile is not authorized to upload or commit physical counts.</div>';
const manualRecon=canSubmit?'<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">CONTROLLED VARIANCE REQUEST</div><h3>Request a stock adjustment</h3><small>Submits a variance for independent management approval; it does not directly change stock.</small></div></div><form id="reconForm" class="sl-form"><div class="sl-field"><label for="reconLocation">Authorized location</label><select id="reconLocation" name="location_id" required><option value="">Select location…</option>'+locationOptions+'</select></div><div class="sl-field"><label>Live Product Master SKU</label>'+skuPickerField('sku','',{label:'SKU for reconciliation'})+'</div><div class="sl-field"><label for="reconCountedQty">Physical quantity counted</label><input id="reconCountedQty" name="counted_qty" type="number" min="0" step="0.01" required></div><div class="sl-field full"><label for="reconReason">Variance reason</label><textarea id="reconReason" name="reason" rows="3" required placeholder="Describe the physical count evidence and why a variance is requested."></textarea></div><div class="sl-field full"><button class="sl-btn primary" type="submit">SUBMIT FOR MANAGEMENT APPROVAL</button></div></form></div>':'<div class="sl-notice">Your profile can review inventory but cannot submit stock-adjustment requests.</div>';
const batchSection=canViewCounts?'<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">PHYSICAL COUNT BATCHES</div><h3>Submitted counts and approval</h3><small>Approval of a physical-count batch is separate from a manual variance request.</small></div><button class="sl-btn" id="refreshCountBatches" type="button">Refresh</button></div><div id="countBatches"><div class="sl-empty">Loading physical-count batches…</div></div></div>':'';
const reconSection=canReviewRecon?'<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">VARIANCE APPROVAL</div><h3>Manual stock-adjustment requests</h3><small>Approval is permitted only for the independent management authority enforced by the server.</small></div><button class="sl-btn" id="refreshRecon" type="button">Refresh</button></div><div id="reconTable"><div class="sl-empty">Loading adjustment requests…</div></div></div>':'';
return title('Stock Count & Approval','Compare physical counts with the live ledger, submit documented stock variances, and review the right approval queue. The server revalidates scope, stock and authority before posting.')+
'<div class="sl-grid sl-grid-compact">'+card('Physical count','Preview first','Explicit commit required')+card('Manual variance','Approval required','No direct stock edit')+card('Location scope','Server enforced','Authorized locations only')+card('Audit trail','Immutable history','Ledger and workflow evidence')+'</div>'+countUpload+batchSection+manualRecon+reconSection;
}

function inventory(){const q=String(state.inventoryQuery||'').trim().toLowerCase();const rows=state.boot.stock.filter(r=>!q||String(r.sku+' '+r.product_name+' '+(r.color||'')+' '+(r.size||'')+' '+(r.barcode||r.gtin||'')).toLowerCase().includes(q));return title('Inventory','Live physical, reserved and available stock by location. No manually typed balances.')+'<div class="sl-panel"><div class="sl-search"><input id="invSearch" placeholder="Search SKU, product, color, size, barcode or GTIN…" value="'+esc(state.inventoryQuery)+'"><button class="sl-btn" id="clearInvSearch">Clear</button></div>'+stockTable(rows)+'</div>'}
function warehouse(){
    const canReceive=state.boot?.user?.role==='warehouse_manager' && !!(state.boot?.user?.capabilities?.receive_warehouse);
    const receiving=canReceive?'<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">SOP-CONTROLLED EXTERNAL SUPPLY</div><h3>PO-linked goods receiving only</h3></div><small>New supplier stock cannot be entered here directly.</small></div><div class="sl-notice sl-notice-soft">Accept only quantities from an approved Central Warehouse purchase order below. Record actual quantities against the original PO lines; any short balance stays open on that same PO. Legacy manual receipts are read-only and cannot post stock.</div><div id="receiptQueue"><div class="sl-empty">Loading legacy receipt exceptions…</div></div></div>':'';
    const canViewPO=['owner','management','warehouse_manager'].includes(state.boot?.user?.role) && !!(state.boot?.user?.capabilities?.warehouse || state.boot?.user?.capabilities?.finance_view || state.boot?.user?.role==='warehouse_manager');
    const purchaseOrderQueue=canViewPO?'<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">PROCUREMENT → GOODS RECEIVING</div><h3>'+(state.boot?.user?.capabilities?.po_approve?'Owner stock-purchase approval queue':'Purchase-order action queue')+'</h3></div><button type="button" class="sl-btn" id="refreshPOQueue">Refresh</button></div><div id="poActionQueue"><div class="sl-empty">Loading purchase orders awaiting your action…</div></div></div>':'';
    return title('Central Warehouse','Warehouse team controls reservation, picking and pickup readiness. External supply is received only after Management entry and separate Owner approval.','<button type="button" class="sl-btn" id="refreshWarehouse">Refresh</button>')+'<div class="sl-panel sl-warehouse-queue-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">WAREHOUSE ACTION QUEUE</div><h3>Approved requisitions</h3></div><small>Reserve → Pick → Ready for branch</small></div><div id="warehouseReqQueue"><div class="sl-empty">Loading warehouse queue…</div></div></div>'+receiving+purchaseOrderQueue+'<div class="sl-panel"><h3>Central Warehouse stock</h3>'+stockTable(state.boot.stock.filter(x=>Number(x.location_id)===Number(state.boot.locations.find(l=>l.location_name==='Central Warehouse')?.id)))+'</div>';
}
function transfers(){const branches=state.boot.locations.filter(l=>l.location_type==='Branch');const rows=state.boot.transfers||[];return title('Transfers','Controlled workflow: Requested → Approved → Reserved → Picked → In Transit → Received → Completed. Picked means warehouse preparation/dispatch recorded; In Transit means the shipment has left the warehouse and is awaiting branch receipt.')+'<div class="sl-panel"><form id="transferForm" class="sl-form"><div class="sl-field"><label>From</label><select name="from_location_id">'+(function(){const cw=state.boot.locations.find(l=>l.location_name==='Central Warehouse');return cw?'<option value="'+cw.id+'">Central Warehouse</option>':''})()+''+branches.map(l=>'<option value="'+l.id+'">'+esc(l.location_name)+'</option>').join('')+'</select></div><div class="sl-field"><label>To</label><select name="to_location_id">'+branches.map(l=>'<option value="'+l.id+'">'+esc(l.location_name)+'</option>').join('')+'</select></div><div class="sl-field"><label>SKU</label>'+skuPickerField('sku','',{label:'SKU'})+'</div><div class="sl-field"><label>Quantity</label><input name="quantity" type="number" min="1" step="1"></div><div class="sl-field full"><button class="sl-btn primary">CREATE TRANSFER REQUEST</button></div></form></div><div class="sl-panel"><h3>Transfer queue</h3><div class="sl-table-wrap"><table class="sl-table"><tr><th>ID</th><th>Route</th><th>SKU</th><th>Qty</th><th>Status</th><th>Next</th></tr>'+rows.map(r=>{let next={'Requested':'Approved','Approved':'Reserved','Reserved':'Picked','Picked':'In Transit','In Transit':'Received','Received':'Completed'}[r.status];let c=state.boot.user.capabilities||{};let ids=(state.boot.user.location_ids||[]).map(Number);let dest=Number(r.to_location_id||0);let canReceiveAtDestination=!!c.receive_transfer&&(!!state.boot.user.can_all||ids.includes(dest)||Number(state.boot.user.branch_location_id||0)===dest);let canNext=next==='Approved'?c.approve_transfer:next==='Reserved'||next==='Picked'||next==='In Transit'?c.transfer:next==='Received'||next==='Completed'?canReceiveAtDestination:false;return '<tr><td>'+esc(r.transfer_id)+'</td><td>'+esc(r.from_location_name+' → '+r.to_location_name)+'</td><td>'+esc(r.sku)+'</td><td>'+r.quantity+'</td><td><span class="sl-pill info">'+esc(r.status)+'</span></td><td>'+((next&&canNext)?'<button class="sl-btn" data-transfer="'+esc(r.transfer_id)+'" data-next="'+esc(next)+'">'+esc(next)+'</button>':(next&&['Received','Completed'].includes(next)&&c.receive_transfer?'<span class="sl-sub">Destination scope required</span>':''))+'</td></tr>'}).join('')+'</table></div></div>'}
function wholesale(){
const u=state.boot?.user||{},c=u.capabilities||{};
if(u.role!=='owner'||!c.wholesale)return title('Wholesale access restricted','Wholesale stock disposition and bulk sales are reserved to the active Stockland Owner profile.')+'<div class="sl-notice sl-notice-warn">This screen is Owner-only. The server independently enforces the same restriction; no sale form is available to this profile.</div><button class="sl-btn" type="button" data-view="dashboard">Return to dashboard</button>';
const seed=state.wholesalePrefill||{};
return title('Owner Wholesale & Surplus','Owner decision only. A fresh calculation protects every branch reorder level, internal requests, reservations, in-transit stock and the Central Warehouse reserve floor. A suggestion never posts or reserves stock.')+
'<div class="sl-notice sl-notice-soft sl-wholesale-policy-note"><b>Decision rule:</b> Keep stock for internal operations by default. Sale above the live safe-surplus amount requires a documented Owner override. If ledger/stock integrity is uncertain, the server blocks the sale. No customer order, stock movement, notification or ledger entry is created by the recommendation itself.</div>'+
'<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">OWNER-AUTHORIZED TRANSACTION</div><h3>Complete a wholesale sale</h3><small>Only the Owner may submit. Server recalculates protected surplus at submission.</small></div><button class="sl-btn sl-btn-sm" type="button" data-view="dashboard">Back to Owner dashboard</button></div><form id="whForm" class="sl-form"><div class="sl-field"><label>Customer</label><input name="customer" maxlength="190" required></div><div class="sl-field"><label>Product Master SKU</label>'+skuPickerField('sku',seed.sku||'',{label:'Wholesale Product Master SKU'})+'</div><div class="sl-field"><label>Sale quantity</label><input name="quantity" type="number" min="1" step="1" value="'+esc(seed.quantity||'')+'" required></div><div class="sl-field"><label>Wholesale price ETB per unit</label><input name="price" type="number" min="0" step="0.01" value="'+esc(seed.price??'')+'" placeholder="Use Product Master price"></div><div class="sl-field"><label>Payment status</label><select name="payment_status"><option>Paid</option><option selected>Unpaid</option><option>Partial</option></select></div><div class="sl-field full"><div class="sl-notice" id="wholesaleFreshAdvice">The server will perform a fresh protected-surplus check when you submit. No stock has been reserved.</div></div><div class="sl-field full"><button class="sl-btn primary" type="submit">COMPLETE WHOLESALE SALE</button></div></form></div>';
}
function returns(){return title('Returns & Adjustments','Every inventory correction has a reason and creates an auditable ledger movement.')+'<div class="sl-panel"><form id="retForm" class="sl-form"><div class="sl-field"><label>Location</label><select name="location_id">'+state.boot.locations.filter(l=>l.location_type!=='Sales channel').map(l=>'<option value="'+l.id+'">'+esc(l.location_name)+'</option>').join('')+'</select></div><div class="sl-field"><label>Type</label><select name="return_type"><option>Return</option><option>Damage</option><option>Adjustment</option></select></div><div class="sl-field"><label>SKU</label>'+skuPickerField('sku','',{label:'SKU'})+'</div><div class="sl-field"><label>Quantity</label><input name="quantity" type="number" min="1" step="1"></div><div class="sl-field full"><label>Reason</label><textarea name="reason" required></textarea></div><div class="sl-field full"><button class="sl-btn primary">RECORD MOVEMENT</button></div></form></div>'}
function closing(){const locs=authorizedBranches();const allowMulti=state.boot.user.can_all||locs.length>1;return title('Daily Closing','Close the selected authorized branch day. Closed history cannot be edited; corrections require authorized reversal/adjustment.')+'<div class="sl-panel"><form id="closeForm" class="sl-form"><div class="sl-field"><label>Business date</label><input name="business_date" type="date" value="'+localDate()+'"></div><div class="sl-field"><label>Branch</label>'+(allowMulti?'<select name="branch_location_id">'+(state.boot.user.can_all?'<option value="">Select branch…</option>':'')+locs.map(l=>'<option value="'+l.id+'">'+esc(l.location_name)+'</option>').join('')+'</select>':'<div class="sl-readonly-field">'+esc(locs[0]?.location_name||'Assigned branch')+'</div><input type="hidden" name="branch_location_id" value="'+esc(locs[0]?.id||'')+'">')+'</div><div class="sl-field full"><label>Notes</label><textarea name="notes"></textarea></div><div class="sl-field full"><button class="sl-btn primary">CLOSE DAY</button></div></form></div>'}
function products(){
const can=!!state.boot.user.capabilities?.products;
const canImport=!!state.boot.user.capabilities?.product_import;
const hasCatalog=Array.isArray(state.catalog);
const rows=hasCatalog?state.catalog:[];
const loading=state.catalogLoading||!hasCatalog?'<div class="sl-notice" role="status" aria-live="polite">Loading the authoritative Product Master…</div>':'';
const countNote=!state.catalogLoading&&hasCatalog?'<div class="sl-sub" style="margin:8px 0">Live Product Master records returned: <b>'+esc(state.catalog.length)+'</b>. Stock quantity is not used to determine catalog identity.</div>':'';
const empty=!state.catalogLoading&&hasCatalog&&!rows.length?'<div class="sl-empty-rich"><b>No Product Master match.</b><span>Search uses SKU, product name, parent/style SKU, style code, brand, color, size, barcode and GTIN from the ERP catalog. Do not substitute an Inventory or stock row.</span></div>':'';
return title('Product Master','Authoritative catalog: Style → Variant SKU → Color + Size + Barcode/GTIN. Product identity is independent of stock quantity.',(can?'<button class="sl-btn gold" id="newProduct" type="button">+ New Product / Variant</button> ':'')+(canImport?'<button class="sl-btn" id="openProductImport" type="button">BULK MASTER UPDATE</button>':''))+
'<div class="sl-panel"><div class="sl-search"><input id="prodSearch" aria-label="Search Product Master" placeholder="Search exact SKU, style, brand, color, size, barcode or GTIN…" value="'+esc(state.productQuery)+'" autocomplete="off"><button class="sl-btn" id="clearProdSearch" type="button">Clear</button></div>'+loading+'<div class="sl-table-wrap"><table class="sl-table"><thead><tr><th>Style</th><th>SKU</th><th>Type</th><th>Brand</th><th>Color</th><th>Size</th><th>Barcode / GTIN</th><th>Age</th><th>Retail</th><th>Status</th><th></th></tr></thead><tbody>'+
rows.map(p=>'<tr><td><b>'+esc(p.style_code||p.parent_sku||p.sku)+'</b><div class="sl-sub">'+esc(p.product_name)+'</div></td><td><b>'+esc(p.sku)+'</b></td><td>'+esc(String(p.product_type||'VARIANT').toUpperCase())+'</td><td>'+esc(p.brand||'')+'</td><td>'+esc(p.color||'—')+'</td><td>'+esc(p.size||'—')+'</td><td>'+esc(p.barcode||p.gtin||'—')+'</td><td>'+esc(p.age_group||'')+'</td><td>'+money(p.retail_price)+'</td><td><span class="sl-pill '+(Number(p.is_active)===1?'ok':'warn')+'">'+(Number(p.is_active)===1?'ACTIVE':'INACTIVE')+'</span></td><td>'+(can?'<button class="sl-btn" type="button" data-edit-product="'+esc(p.sku)+'">Edit</button> <button class="sl-btn gold" type="button" data-replace-sku="'+esc(p.variant_id)+'" data-current-sku="'+esc(p.sku)+'">Replace SKU</button>':'')+'</td></tr>').join('')+
'</tbody></table></div>'+countNote+empty+'</div>';
}
async function loadProductCatalog(){
if(state.view!=='products'||!state.boot){state.catalogSearchPending=false;return;}
state.catalogSearchPending=false;
const requestId=++state.catalogRequestId;state.catalogLoading=true;render();
try{
 const qs=[];const q=String(state.productQuery||'').trim();if(q){const enc=encodeURIComponent(q);qs.push('q='+enc);qs.push('search='+enc);}
 if(state.boot.user?.capabilities?.products)qs.push('include_inactive=1');
 qs.push('limit=1000');
 const rows=await api('/products?'+qs.join('&'));
 if(requestId!==state.catalogRequestId)return;
 state.catalog=Array.isArray(rows)?rows:[];state.catalogQuery=String(state.productQuery||'').trim();
}catch(e){
 if(requestId!==state.catalogRequestId)return;
 state.catalog=[];state.catalogQuery=String(state.productQuery||'').trim();toast(e.message,true);
}finally{
 if(requestId===state.catalogRequestId){state.catalogLoading=false;render();}
}
}
function productFieldValue(p,key,fallback=''){return p[key]!==null&&p[key]!==undefined&&p[key]!==''?p[key]:fallback}
function syncProductFormRules(form){
const type=String(form.querySelector('[name="product_type"]')?.value||'SIMPLE').toUpperCase();
const skuLabel=form.querySelector('[data-product-sku-label]');
const parent=form.querySelector('[name="parent_sku"]');
const color=form.querySelector('[name="color"]');
const size=form.querySelector('[name="size"]');
if(skuLabel)skuLabel.textContent=type==='STYLE'?'Style SKU':(type==='VARIANT'?'Variant SKU':'Product SKU');
if(parent)parent.required=type==='VARIANT';
if(color)color.required=type==='VARIANT';
if(size)size.required=type==='VARIANT';
}
function productForm(p){
p=p||{sku:'',product_name:'',product_type:'SIMPLE',parent_sku:'',style_code:'',brand:'',department:'',sub_category:'',collection_name:'',season:'',material:'',fit:'',country_of_origin:'',barcode:'',gtin:'',color:'',color_code:'',size:'',size_code:'',size_system:'',category:'',gender:'',age_group:'',unit:'PCS',purchase_cost:'',retail_price:'',wholesale_price:'',notes:'',branch_reorder_level:2,central_reorder_level:200};
const editing=!!p.sku;
const type=String(p.product_type||'SIMPLE').toUpperCase();
const branchReorder=productFieldValue(p,'branch_reorder_level',2); const centralReorder=productFieldValue(p,'central_reorder_level',200);
const purchase=productFieldValue(p,'purchase_cost',''); const retail=productFieldValue(p,'retail_price',''); const wholesale=productFieldValue(p,'wholesale_price','');
return '<div class="sl-drawer"><div class="sl-page-title"><div><h2 style="font-size:21px">'+(editing?'Edit Product / Variant':'New Product / Variant')+'</h2><p>Product Master controls catalog identity only. Inventory quantity enters through Warehouse Receiving, not this form.</p></div><button class="sl-btn" id="closeProduct" type="button">Close</button></div><form id="productForm" class="sl-form" novalidate>'+
'<div class="sl-field"><label>Product type</label><select name="product_type"><option '+(type==='SIMPLE'?'selected':'')+'>SIMPLE</option><option '+(type==='STYLE'?'selected':'')+'>STYLE</option><option '+(type==='VARIANT'?'selected':'')+'>VARIANT</option></select></div>'+ 
'<div class="sl-field"><label data-product-sku-label>'+(type==='STYLE'?'Style SKU':(type==='VARIANT'?'Variant SKU':'Product SKU'))+'</label><input name="sku" value="'+esc(p.sku)+'" pattern="[A-Za-z0-9][A-Za-z0-9._-]{0,63}" title="Use 1–64 letters, numbers, dot, hyphens or underscores." '+(editing?'readonly':'required')+'></div>'+ 
'<div class="sl-field full"><label>Product name</label><input name="product_name" value="'+esc(p.product_name||'')+'" required></div>'+ 
'<div class="sl-field"><label>Parent / style SKU</label><input name="parent_sku" value="'+esc(p.parent_sku||'')+'"></div><div class="sl-field"><label>Style code</label><input name="style_code" value="'+esc(p.style_code||'')+'"></div>'+ 
'<div class="sl-field"><label>Brand</label><input name="brand" value="'+esc(p.brand||'')+'"></div><div class="sl-field"><label>Department</label><input name="department" value="'+esc(p.department||'')+'"></div>'+ 
'<div class="sl-field"><label>Category</label><input name="category" value="'+esc(p.category||'')+'"></div><div class="sl-field"><label>Sub-category</label><input name="sub_category" value="'+esc(p.sub_category||'')+'"></div>'+ 
'<div class="sl-field"><label>Gender</label><input name="gender" value="'+esc(p.gender||'')+'"></div><div class="sl-field"><label>Age group</label><input name="age_group" value="'+esc(p.age_group||'')+'"></div>'+ 
'<div class="sl-field"><label>Color</label><input name="color" value="'+esc(p.color||'')+'"></div><div class="sl-field"><label>Color code</label><input name="color_code" value="'+esc(p.color_code||'')+'"></div>'+ 
'<div class="sl-field"><label>Size</label><input name="size" value="'+esc(p.size||'')+'"></div><div class="sl-field"><label>Size code</label><input name="size_code" value="'+esc(p.size_code||'')+'"></div>'+ 
'<div class="sl-field"><label>Size system</label><select name="size_system"><option value="">—</option><option '+(p.size_system==='AGE'?'selected':'')+'>AGE</option><option '+(p.size_system==='US'?'selected':'')+'>US</option><option '+(p.size_system==='EU'?'selected':'')+'>EU</option><option '+(p.size_system==='UK'?'selected':'')+'>UK</option><option '+(p.size_system==='INTL'?'selected':'')+'>INTL</option></select></div><div class="sl-field"><label>Barcode</label><input name="barcode" value="'+esc(p.barcode||'')+'"></div>'+ 
'<div class="sl-field"><label>GTIN</label><input name="gtin" value="'+esc(p.gtin||'')+'"></div><div class="sl-field"><label>Material / fabric</label><input name="material" value="'+esc(p.material||'')+'"></div>'+ 
(state.boot.user.can_cost?'<div class="sl-field"><label>Purchase cost ETB</label><input name="purchase_cost" type="number" min="0" step="0.01" value="'+esc(purchase)+'"></div>':'')+
'<div class="sl-field"><label>Retail price ETB</label><input name="retail_price" type="number" min="0" step="0.01" value="'+esc(retail)+'"></div>'+ 
'<div class="sl-field"><label>Wholesale price ETB</label><input name="wholesale_price" type="number" min="0" step="0.01" value="'+esc(wholesale)+'"></div>'+ 
'<div class="sl-field"><label>Branch reorder level</label><input name="branch_reorder_level" type="number" min="0" step="1" value="'+esc(branchReorder)+'"></div><div class="sl-field"><label>Central reorder level</label><input name="central_reorder_level" type="number" min="0" step="1" value="'+esc(centralReorder)+'"></div>'+ 
'<div class="sl-field full"><label>Notes</label><textarea name="notes">'+esc(p.notes||'')+'</textarea></div>'+ 
'<div class="sl-field full"><button type="submit" class="sl-btn primary" id="productSaveButton">'+(editing?'SAVE PRODUCT':'CREATE PRODUCT')+'</button></div>'+(editing&&type==='STYLE'?'<div class="sl-field full"><button type="button" class="sl-btn gold" id="generateMatrix">GENERATE COLOR × SIZE MATRIX</button><small class="sl-sub">Creates only missing VARIANT SKUs from this STYLE. Existing variants are never overwritten.</small></div>':'')+'</form></div>';
}
async function openProductEditor(sku){
let ov=null;
try{
let p=null;
if(sku){
 const suffix=state.boot.user.capabilities?.products?'?include_inactive=1':'';
 p=await api('/products/'+encodeURIComponent(sku)+suffix);
}
ov=document.createElement('div');ov.className='sl-overlay';ov.id='productOverlay';ov.innerHTML=productForm(p);document.body.appendChild(ov);const form=document.getElementById('productForm');
syncProductFormRules(form);
ov.onclick=e=>{if(e.target===ov)ov.remove()};document.getElementById('closeProduct').onclick=()=>ov.remove();
const typeSelect=form.querySelector('[name="product_type"]');if(typeSelect)typeSelect.onchange=()=>syncProductFormRules(form);
const gm=document.getElementById('generateMatrix');if(gm)gm.onclick=async()=>{
const colorText=await slReasonDialog('Colors, comma separated','');if(colorText===null)return;
const sizeText=await slReasonDialog('Sizes, comma separated','');if(sizeText===null)return;
const colors=colorText.split(',').map(x=>x.trim()).filter(Boolean).map(x=>({name:x,code:x.toUpperCase().replace(/[^A-Z0-9]+/g,'-')}));
const sizes=sizeText.split(',').map(x=>x.trim()).filter(Boolean).map(x=>({name:x,code:x.toUpperCase().replace(/[^A-Z0-9]+/g,'-')}));
if(!colors.length||!sizes.length){toast('Enter at least one color and one size.',true);return;}
gm.disabled=true;gm.setAttribute('aria-busy','true');
try{
 const d=await api('/products/variants/generate',{method:'POST',body:JSON.stringify({parent_sku:p.sku,colors,sizes})});
 ov.remove();state.productQuery=String(p.sku);state.catalog=null;state.catalogQuery=null;try{await load();}catch(refreshErr){toast('Matrix committed; ERP screen refresh failed. Reopen Product Master to verify.',true)}try{await loadNotifications()}catch(_e){}
 toast('Matrix verified: '+d.count+' new variants; '+(Array.isArray(d.skipped)?d.skipped.length:0)+' already existed.');
}catch(err){toast(err.message,true);gm.disabled=false;gm.removeAttribute('aria-busy')}
};
form.onsubmit=async e=>{
e.preventDefault();if(form.dataset.busy==='1')return;syncProductFormRules(form);
const type=String(form.querySelector('[name="product_type"]')?.value||'SIMPLE').toUpperCase();const skuValue=String(form.querySelector('[name="sku"]')?.value||'').trim();
if(!/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/.test(skuValue)){toast('SKU must use 1–64 letters, numbers, hyphens or underscores.',true);return}
if(!form.checkValidity()){form.reportValidity();return}
if(type==='VARIANT'){
 const parent=String(form.querySelector('[name="parent_sku"]')?.value||'').trim(),color=String(form.querySelector('[name="color"]')?.value||'').trim(),size=String(form.querySelector('[name="size"]')?.value||'').trim();
 if(!parent||!color||!size){toast('A VARIANT requires parent/style SKU, color and size.',true);return}
}
form.dataset.busy='1';const submitBtn=form.querySelector('#productSaveButton,[type="submit"]');const originalText=submitBtn?submitBtn.textContent:'';if(submitBtn){submitBtn.disabled=true;submitBtn.setAttribute('aria-busy','true');submitBtn.textContent='SAVING…'}
const d=Object.fromEntries(new FormData(form));
let numericError='';
['purchase_cost','retail_price','wholesale_price','branch_reorder_level','central_reorder_level'].forEach(k=>{
 if(numericError)return;
 if(d[k]===undefined||d[k]===''){delete d[k];return}
 const n=Number(d[k]);if(!Number.isFinite(n))numericError='Invalid numeric value for '+k+'.';else d[k]=n;
});
if(numericError){toast(numericError,true);form.dataset.busy='0';if(submitBtn){submitBtn.disabled=false;submitBtn.removeAttribute('aria-busy');submitBtn.textContent=originalText|| (sku?'SAVE PRODUCT':'CREATE PRODUCT')}return}
let saved=null;
try{
 saved=sku?await api('/products/'+encodeURIComponent(sku),{method:'POST',body:JSON.stringify(d)}):await api('/products',{method:'POST',body:JSON.stringify(d)});
 if(!saved||!saved.sku)throw new Error('ERP saved the request but returned no Product Master record.');
 ov.remove();state.productQuery=String(saved.sku);state.catalog=null;state.catalogQuery=null;
 toast((sku?'Product updated: ':'Product created: ')+saved.sku);
}catch(err){toast(err.message,true);form.dataset.busy='0';if(submitBtn){submitBtn.disabled=false;submitBtn.removeAttribute('aria-busy');submitBtn.textContent=originalText|| (sku?'SAVE PRODUCT':'CREATE PRODUCT')}return}
try{await load();}catch(refreshErr){toast('Product saved and verified by the Product Master response, but the ERP screen could not refresh. Reopen Product Master to search '+String(saved.sku)+'.',true)}
try{await loadNotifications()}catch(_e){}
toast((sku?'Product updated and verified: ':'Product created and verified: ')+saved.sku);
};
}catch(e){toast(e.message,true);if(ov&&!ov.isConnected){}else if(ov&&ov.isConnected){document.getElementById('productSaveButton')?.removeAttribute('aria-busy')}}
}
function editUserForm(u){
const roleList=[['stockland_management','Management'],['stockland_branch_manager','Branch Manager'],['stockland_sales_employee','Sales Employee'],['stockland_warehouse_manager','Warehouse Manager'],['stockland_warehouse_employee','Warehouse Employee'],['stockland_auditor','Auditor']];
if(state.boot.user.role==='owner')roleList.push(['stockland_owner','Owner']);
const roles=roleList.map(x=>'<option value="'+x[0]+'" '+(x[0]==='stockland_'+u.role?'selected':'')+'>'+x[1]+'</option>').join('');
const locs=(state.boot.locations||[]).filter(l=>l.location_type==='Branch'||l.location_type==='Warehouse');
const assigned=(u.location_ids||[]).map(Number);
return '<div class="sl-drawer"><div class="sl-page-title"><div><h2 style="font-size:21px">Edit User</h2><p>Role is WHAT the user can do; authorized locations are WHERE the user may operate.</p></div><button class="sl-btn" id="closeUser">Close</button></div><form id="editUserForm" class="sl-form"><div class="sl-field full"><label>Display name</label><input name="display_name" value="'+esc(u.display_name||'')+'"></div><div class="sl-field"><label>Email</label><input name="email" type="email" value="'+esc(u.user_email||'')+'"></div><div class="sl-field"><label>Role</label><select name="role">'+roles+'</select></div><div class="sl-field full"><label>Authorized locations <small>(first selected is primary; branch roles may have multiple branches)</small></label><select name="location_ids[]" multiple size="6">'+locs.map(l=>'<option value="'+l.id+'" '+(assigned.includes(Number(l.id))?'selected':'')+'>'+esc(l.location_name)+' — '+esc(l.location_type)+'</option>').join('')+'</select></div><div class="sl-field"><label>Status</label><select name="is_active"><option value="1" '+(Number(u.is_active)?'selected':'')+'>Active</option><option value="0" '+(!Number(u.is_active)?'selected':'')+'>Inactive</option></select></div><div class="sl-field"><label>New password (optional)</label><input name="password" type="password" minlength="10"></div><div class="sl-field full"><button class="sl-btn primary">SAVE USER</button></div></form></div>';
}
async function openUserEditor(id){
const u=state.users.find(x=>Number(x.wp_user_id)===Number(id));if(!u)return;
const ov=document.createElement('div');ov.className='sl-overlay';ov.innerHTML=editUserForm(u);document.body.appendChild(ov);
ov.onclick=e=>{if(e.target===ov)ov.remove()};document.getElementById('closeUser').onclick=()=>ov.remove();
document.getElementById('editUserForm').onsubmit=async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(e.currentTarget));d.location_ids=[...e.currentTarget.querySelectorAll('[name="location_ids[]"] option:checked')].map(o=>Number(o.value));d.branch_location_id=d.location_ids[0]||null;d.is_active=Number(d.is_active);try{await api('/app/users/'+encodeURIComponent(id),{method:'POST',body:JSON.stringify(d)});toast('User updated');ov.remove();await loadUsers();await load()}catch(err){toast(err.message,true)}};
}
function settings(){
const s=state.boot.settings||{};
return title('System Settings','Central controls for VAT, business identity and reorder levels.')+
`<div class="sl-panel"><form id="settingsForm" class="sl-form">
<div class="sl-field"><label>VAT rate (decimal)</label><input name="vat_rate" type="number" min="0" max="1" step="0.0001" value="${esc(s.vat_rate||0)}"></div>
<div class="sl-field"><label>VAT basis</label><select name="vat_basis"><option value="INCLUSIVE" ${s.vat_basis==='INCLUSIVE'?'selected':''}>INCLUSIVE</option><option value="EXCLUSIVE" ${s.vat_basis==='EXCLUSIVE'?'selected':''}>EXCLUSIVE</option></select></div>
<div class="sl-field"><label>Business name</label><input name="business_name" value="${esc(s.business_name||'')}"></div>
<div class="sl-field"><label>Tagline</label><input name="tagline" value="${esc(s.tagline||'')}"></div>
<div class="sl-field"><label>Branch reorder alert</label><input name="branch_reorder_alert_level" type="number" min="0" value="${esc(s.branch_reorder_alert_level||2)}"></div>
<div class="sl-field"><label>Central reorder alert</label><input name="central_reorder_alert_level" type="number" min="0" value="${esc(s.central_reorder_alert_level||200)}"></div>

<div class="sl-field full"><button class="sl-btn primary">SAVE CENTRAL SETTINGS</button></div>
</form></div>
<div class="sl-block"><div class="sl-block-head"><h3>Enterprise identity</h3><small>Premium operational design with live business data</small></div><div class="sl-block-body sl-brand-block"><img src="${esc(s.brand_logo||A.logo)}" alt="Stockland logo"><div><h3>Stockland Ethiopia ERP</h3><p>Global Brands. Local Access.</p><p>Variant-first inventory • transactional ledger • manual/on-demand POS imports • no Cron dependency.</p></div></div></div>`;
}

function audit(){return title('Audit Log','Permanent activity history for management and authorized auditors.')+'<div class="sl-panel"><div id="auditTable"><div class="sl-empty">Loading…</div></div></div>'}
function health(){return title('System Health','Production readiness checks for WordPress, database, ledger, REST and secure transport.','','sl-page-title-static')+'<div class="sl-panel sl-system-health-panel"><div class="sl-action-row"><button class="sl-btn primary" id="runHealth">RUN QUICK HEALTH CHECK</button><button class="sl-btn" id="runDeepHealth">RUN DEEP INTEGRITY AUDIT</button></div><p class="sl-sub">Quick checks are lightweight. The deep audit is read-only but may take longer on shared hosting; run it intentionally after a backup and during a quiet period.</p><div id="healthResult" style="margin-top:14px"><div class="sl-empty">Run the check.</div></div></div>'}
function bind(){
const $=s=>document.querySelector(s);
document.querySelectorAll('[data-clear-global-search]').forEach(b=>{if(!b.__slClearSearchBound){b.__slClearSearchBound=1;b.addEventListener('click',()=>{state.globalSearchSelection=null;render()});}});
if($('#saleSku'))$('#saleSku').onchange=e=>{const p=state.boot.products.find(x=>x.sku===e.target.value);if(p){$('#salePrice').value=p.retail_price;if($('#saleSize'))$('#saleSize').value=p.size||'';if($('#saleColor'))$('#saleColor').value=p.color||''}};
['receiveForm','transferForm','whForm','retForm','closeForm','userForm','settingsForm','reqForm'].forEach(id=>{const f=document.getElementById(id);if(f&&!f.__slBound){f.__slBound=1;f.addEventListener('submit',submit)}});
if($('#refresh'))$('#refresh').onclick=()=>load();document.querySelectorAll('.sl-nav [data-view]').forEach(b=>{b.onclick=()=>{state.view=b.dataset.view==='dailyInventory'?'dailyreport':b.dataset.view;render()}});document.querySelectorAll('[data-view]').forEach(b=>{if(!b.closest('.sl-nav'))b.onclick=()=>{state.view=b.dataset.view==='dailyInventory'?'dailyreport':b.dataset.view;render()}});
if($('#openProductImport'))$('#openProductImport').onclick=()=>{state.view='products-import';render()};
if($('#refreshUsers'))$('#refreshUsers').onclick=()=>{loadUsers();loadAvailableWPUsers()};if($('#systemControlPage'))loadSystemControl();
if(!document.__slNewProductCaptureBound){
  document.__slNewProductCaptureBound=true;
  document.addEventListener('click',function(e){
    const target=e.target instanceof Element?e.target.closest('#newProduct'):null;
    const appRoot=document.getElementById('stockland-app');
    if(!target||!appRoot||!appRoot.contains(target)||target.disabled)return;
    e.preventDefault();e.stopImmediatePropagation();openProductEditor(null);
  },true);
}
if($('#newProduct'))$('#newProduct').onclick=()=>openProductEditor(null);
document.querySelectorAll('[data-edit-product]').forEach(b=>b.onclick=()=>openProductEditor(b.dataset.editProduct));
if($('#addReq')){
let n=0;
const add=()=>{n++;const d=document.createElement('div');d.className='sl-form';d.style.marginBottom='10px';d.innerHTML='<div class="sl-field"><label>SKU</label>'+skuPickerField('reqsku[]','',{label:'Requested SKU'})+'</div><div class="sl-field"><label>Quantity</label><input name="reqqty[]" type="number" min="1" value="1"></div>';$('#reqItems').appendChild(d);bindSkuPickers()};
add();$('#addReq').onclick=add;loadReqs();
}
document.querySelectorAll('[data-transfer]').forEach(b=>b.onclick=async()=>{try{await api('/transfers/'+encodeURIComponent(b.dataset.transfer)+'/status',{method:'POST',body:JSON.stringify({status:b.dataset.next})});toast('Transfer moved to '+b.dataset.next);await load()}catch(e){toast(e.message,true)}});


if($('#auditTable'))loadAudit();
if($('#usersTable'))loadUsers();
if($('#runHealth'))$('#runHealth').onclick=()=>runHealth('quick');if($('#runDeepHealth'))$('#runDeepHealth').onclick=()=>runHealth('deep');if($('#receiptQueue'))loadReceipts();
if($('#refreshReorder'))$('#refreshReorder').onclick=loadReorder;if($('#reorderTable'))loadReorder();
if($('#financePage')&&!state.financeLoaded)loadFinance();
if($('#refreshFinance'))$('#refreshFinance').onclick=()=>loadFinance(true);
if($('#supplierFinanceForm'))$('#supplierFinanceForm').onsubmit=financeSubmit;
    document.querySelectorAll('[data-replace-sku]').forEach(b=>{b.onclick=async()=>{const next=await slReasonDialog('New live SKU',b.dataset.currentSku||'');if(next===null)return;const reason=await slReasonDialog('Reason for SKU replacement','Replaced Product Master SKU; historical ledger identity must remain unchanged.');if(reason===null||!String(next).trim())return;b.disabled=true;try{await api('/products/'+encodeURIComponent(b.dataset.replaceSku)+'/replace-sku',{method:'POST',body:JSON.stringify({new_sku:String(next).trim(),reason})});toast('Current Product Master SKU changed; historical transactions were preserved');await load()}catch(e){toast(e.message,true);b.disabled=false}}});
if($('#customerFinanceForm'))$('#customerFinanceForm').onsubmit=financeSubmit;
/* Purchase Order line initialization is owned by bindExtended() to prevent duplicate blank mandatory lines. */
if($('#paymentFinanceForm')){const pf=$('#paymentFinanceForm');pf.onsubmit=financeSubmit;const pt=$('#paymentType');const ps=$('#paymentSupplier');const pc=$('#paymentCustomer');const sync=()=>{const sup=pt?.value==='SUPPLIER';if(ps)ps.disabled=!sup;if(pc)pc.disabled=sup;};pt?.addEventListener('change',sync);sync();}
if($('#expenseFinanceForm'))$('#expenseFinanceForm').onsubmit=financeSubmit;
if($('#financeGL'))loadFinanceGL();
bindReportExplorer();
if($('#dailyForm'))$('#dailyForm').onsubmit=async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(e.currentTarget));try{await loadDaily(d.date,d.location_id)}catch(err){toast(err.message,true)}};if($('#printDaily'))$('#printDaily').onclick=()=>{if(!document.getElementById('dailyResult')?.querySelector('.sl-panel')){toast('Run the daily view first',true);return}window.print()};
if($('#runDaily'))$('#runDaily').onclick=()=>{const f=document.getElementById('dailyForm');if(f)f.requestSubmit()};



const sopBtn=document.getElementById('slSopBtn');if(sopBtn)sopBtn.onclick=openSopGuide;const themeBtn=document.getElementById('slThemeBtn');if(themeBtn)themeBtn.onclick=openThemePicker;const cmdBtn=document.getElementById('slCommandBtn');if(cmdBtn)cmdBtn.onclick=openCommandPalette;const ds=document.getElementById('dashboardSopBtn');if(ds)ds.onclick=openSopGuide;const alertAction=document.getElementById('openHeaderNotifications');if(alertAction)alertAction.onclick=()=>document.getElementById('notificationBell')?.click();if(document.getElementById('slActivityFeed'))loadActivityFeed();if(document.getElementById('slDashboardSalesTrend'))loadDashboardInsights();const miRefresh=document.getElementById('refreshManagementIntelligence');if(miRefresh)miRefresh.onclick=()=>loadDashboardInsights(true);if(document.getElementById('v16ReqForm')){}bindExtended();bindSkuPickers();
}
function addFinanceLine(container,type,preset){
const box=document.getElementById(container);if(!box)return;
const p=preset||{};const div=document.createElement('div');div.className='sl-form sl-fin-line';
const optsSku=String(p.sku||'');
if(type==='po')div.innerHTML='<div class="sl-field"><label>SKU</label>'+skuPickerField('sku',optsSku,{label:'SKU'})+'</div><div class="sl-field"><label>Qty</label><input name="quantity" type="number" min="0.01" step="0.01" value="1"></div><div class="sl-field"><label>Unit cost</label><input name="unit_cost" type="number" min="0.01" step="0.01" placeholder="Required" required></div><div class="sl-field"><label>Tax rate</label><input name="tax_rate" type="number" min="0" step="0.0001" value="'+esc(state.boot.settings.vat_rate||0)+'"></div><button type="button" class="sl-btn" data-remove-fin-line>Remove</button>';
else div.innerHTML='<div class="sl-field"><label>SKU</label>'+skuPickerField('sku',optsSku,{label:'SKU'})+'</div><div class="sl-field"><label>Qty</label><input name="quantity" type="number" min="0.01" step="0.01" value="'+esc(p.quantity||1)+'"></div><div class="sl-field"><label>Unit price</label><input name="unit_price" type="number" min="0" step="0.01" value="'+esc(p.retail_price||0)+'"></div><div class="sl-field"><label>VAT mode</label><select name="vat_mode"><option>VAT_INCLUDED</option><option>WITHOUT_VAT</option></select></div><button type="button" class="sl-btn" data-remove-fin-line>Remove</button>';
box.appendChild(div);div.querySelector('[data-remove-fin-line]').onclick=()=>{if(type==='po'&&box.querySelectorAll('.sl-fin-line').length<=1){toast('A purchase order must contain at least one SKU line.',true);return;}div.remove();};bindSkuPickers();
}
async function financeSubmit(e){
e.preventDefault();
const f=e.currentTarget;
if(f.dataset.busy==='1')return;
f.dataset.busy='1';
const btn=f.querySelector('button[type="submit"],button:not([type])');
const original=btn?btn.textContent:'';
if(btn){btn.disabled=true;btn.setAttribute('aria-busy','true');btn.textContent='SAVING…';}
let successMessage='';
let refreshPO=false;
try{
let result=null;
if(f.id==='supplierFinanceForm'){
const d=Object.fromEntries(new FormData(f));
d.credit_limit=Number(d.credit_limit||0);d.opening_balance=Number(d.opening_balance||0);
result=await api('/accounting/suppliers',{method:'POST',body:JSON.stringify(d)});
if(!result||Number(result.id)<=0)throw new Error('The server did not confirm a saved supplier record. Refresh Supplier Master before retrying.');
successMessage='Supplier saved. Supplier balance remains ledger-derived.';
}else if(f.id==='customerFinanceForm'){
const d=Object.fromEntries(new FormData(f));
d.credit_limit=Number(d.credit_limit||0);d.opening_balance=Number(d.opening_balance||0);
result=await api('/accounting/customers',{method:'POST',body:JSON.stringify(d)});
if(!result||Number(result.id)<=0)throw new Error('The server did not confirm a saved customer record. Refresh Customer Master before retrying.');
successMessage='Customer saved. Customer balance remains AR-derived.';
}else if(f.id==='poFinanceForm'){
const d=Object.fromEntries(new FormData(f));
const rows=[...f.querySelectorAll('#poFinanceLines .sl-fin-line')].map(x=>({sku:String(x.querySelector('[name="sku"]')?.value||'').trim(),quantity:Number(x.querySelector('[name="quantity"]')?.value),unit_cost:Number(x.querySelector('[name="unit_cost"]')?.value),tax_rate:Number(x.querySelector('[name="tax_rate"]')?.value)}));
if(!rows.length||rows.some(x=>!x.sku||!Number.isFinite(x.quantity)||x.quantity<=0||!Number.isFinite(x.unit_cost)||x.unit_cost<=0||!Number.isFinite(x.tax_rate)||x.tax_rate<0||x.tax_rate>1))throw new Error('Add at least one valid stockable SKU line. Quantity and unit cost must be positive; tax rate must be between 0 and 1.');
d.supplier_id=Number(d.supplier_id);d.lines=rows;
result=await api('/accounting/purchase-orders',{method:'POST',body:JSON.stringify(d)});
const validStates=['DRAFT','SUBMITTED','OWNER_APPROVED','APPROVED','PARTIALLY_RECEIVED','RECEIVED','CLOSED','REJECTED','CANCELLED'];
if(!result||Number(result.id)<=0||!String(result.po_number||'').trim()||!validStates.includes(String(result.status||'').toUpperCase()))throw new Error('The server did not confirm the PO number and status. Refresh the purchase-order queue before retrying.');
window.__slLastPOResponse=result;refreshPO=true;
successMessage=result.replayed?'Purchase order '+result.po_number+' already exists with status '+result.status+'; no duplicate was created.':'Purchase order '+result.po_number+' saved as DRAFT. It is not submitted yet; use SUBMIT TO OWNER in the purchase-order queue.';
}else if(f.id==='paymentFinanceForm'){
const d=Object.fromEntries(new FormData(f));
d.amount=Number(d.amount);d.supplier_id=Number(d.supplier_id||0);d.customer_id=Number(d.customer_id||0);
const inputs=[...f.querySelectorAll('[data-payment-allocation]')];
const allocations=inputs.map(input=>({invoice_id:Number(input.dataset.invoiceId||0),amount:Number(input.value||0),available:Number(input.dataset.outstanding||input.max||0)})).filter(x=>x.amount>0);
if(allocations.some(x=>!Number.isInteger(x.invoice_id)||x.invoice_id<=0||!Number.isFinite(x.amount)||x.amount<=0||!Number.isFinite(x.available)||x.amount>x.available+0.00001))throw new Error('Check invoice allocations: amounts must be positive and cannot exceed each posted invoice’s outstanding balance.');
if(String(d.payment_type||'').toUpperCase()==='SUPPLIER'&&!allocations.length)throw new Error('Supplier payments must be allocated to at least one posted AP invoice with an outstanding balance.');
const allocatedCents=allocations.reduce((sum,x)=>sum+Math.round(x.amount*100),0), paymentCents=Math.round(Number(d.amount||0)*100);
if(allocations.length&&allocatedCents!==paymentCents)throw new Error('Invoice allocations must equal the payment amount exactly. Allocated ETB '+money(allocatedCents/100)+'; payment ETB '+money(paymentCents/100)+'.');
d.allocations=allocations.map(x=>({invoice_id:x.invoice_id,amount:x.amount}));
result=await api('/accounting/payments',{method:'POST',body:JSON.stringify(d)});
const status=String(result?.status||'').toUpperCase();
if(!result||Number(result.id)<=0||!String(result.payment_number||'').trim()||!['PENDING','POSTED','REJECTED'].includes(status))throw new Error('The server did not confirm the payment reference and workflow status. Refresh Finance before retrying.');
window.__slLastPaymentResponse=result;
successMessage=result.replayed?'Payment '+result.payment_number+' already exists with status '+status+'; no duplicate was created.':status==='PENDING'?'Payment '+result.payment_number+' submitted for Owner approval. No GL posting has occurred yet.':'Payment '+result.payment_number+' status: '+status+'.';
}else if(f.id==='expenseFinanceForm'){
const d=Object.fromEntries(new FormData(f));d.amount=Number(d.amount);d.tax_amount=Number(d.tax_amount||0);
result=await api('/accounting/expenses',{method:'POST',body:JSON.stringify(d)});
const status=String(result?.status||'').toUpperCase();
if(!result||Number(result.id)<=0||!String(result.expense_number||'').trim()||!['PENDING','POSTED','REJECTED'].includes(status))throw new Error('The server did not confirm the expense reference and status. Refresh Expense History before retrying.');
window.__slLastExpenseResponse=result;
successMessage=result.replayed?'Expense '+result.expense_number+' already exists with status '+status+'; no duplicate was created.':status==='POSTED'?'Corporate expense '+result.expense_number+' posted and audited.':state.boot?.user?.role==='management'?'Corporate expense '+result.expense_number+' submitted to Owner approval; GL posts after approval.':'Expense '+result.expense_number+' submitted to the assigned location authority; GL posts after approval.';
}else{throw new Error('This form has no registered Finance/Procurement save action.');}
toast(successMessage);
f.reset();
if(['poFinanceForm','paymentFinanceForm','expenseFinanceForm'].includes(f.id)&&f.elements.request_key)f.elements.request_key.value=newRequestKey();
state.financeLoaded=false;
try{await loadFinance(true);if(refreshPO&&document.getElementById('poActionQueue'))await loadPurchaseOrders();}
catch(refreshError){toast('The save was confirmed, but Finance/Procurement could not fully refresh. Use Refresh and verify the saved reference before retrying.',true);}
try{await loadNotifications();}catch(notificationError){toast('The save was confirmed, but notifications could not refresh. Open Notifications to verify the workflow message.',true);}
}catch(err){toast(err.message||'Finance/Procurement save failed. Verify the current record state before retrying.',true);}
finally{f.dataset.busy='0';if(btn){btn.disabled=false;btn.removeAttribute('aria-busy');btn.textContent=original||'SAVE';}}
}
function updatePaymentAllocationSummary(form){
const summary=form?.querySelector('#paymentAllocationSummary');if(!summary)return;
const inputs=[...form.querySelectorAll('[data-payment-allocation]')];
const allocatedCents=inputs.reduce((sum,input)=>sum+Math.max(0,Math.round((Number(input.value)||0)*100)),0);
const amountCents=Math.round((Number(form.elements.amount?.value)||0)*100);
const type=String(form.elements.payment_type?.value||'SUPPLIER').toUpperCase();
const hasAllocations=inputs.some(input=>Number(input.value)>0);
let message='Allocated '+money(allocatedCents/100)+' of '+money(amountCents/100)+'.';
if(type==='SUPPLIER')message+=' Supplier payments require allocations equal to the full payment amount.';
else if(!hasAllocations)message+=' Customer payments may remain unapplied only when intended as on-account credit.';
else message+=allocatedCents===amountCents?' Allocations reconcile.':' Adjust invoice allocations to equal the payment amount.';
summary.textContent=message;
summary.classList.toggle('sl-warning-block',type==='SUPPLIER'&&allocatedCents!==amountCents);
summary.dataset.valid=String(type==='SUPPLIER'?hasAllocations&&allocatedCents===amountCents:!hasAllocations||allocatedCents===amountCents);
}
async function loadPaymentAllocations(){
const form=document.getElementById('paymentFinanceForm');if(!form)return;
const rowsEl=form.querySelector('#paymentAllocationRows');if(!rowsEl)return;
const seq=Number(form.dataset.paymentAllocationSeq||0)+1;form.dataset.paymentAllocationSeq=String(seq);
const type=String(form.elements.payment_type?.value||'SUPPLIER').toUpperCase();
const partySelect=form.querySelector(type==='SUPPLIER'?'#paymentSupplier':'#paymentCustomer');
const partyId=Number(partySelect?.value||0);updatePaymentAllocationSummary(form);
if(!partyId){rowsEl.innerHTML='<div class="sl-empty-rich"><b>Choose a '+(type==='SUPPLIER'?'supplier':'customer')+'.</b><span>Posted invoices with outstanding balances will appear here.</span></div>';return;}
rowsEl.innerHTML='<div class="sl-empty">Loading posted invoices and outstanding balances…</div>';
try{
const path=type==='SUPPLIER'?'/accounting/supplier-invoices?status=POSTED':'/accounting/customer-invoices';
const data=await api(path);
if(seq!==Number(form.dataset.paymentAllocationSeq||0)||!form.isConnected)return;
if(!Array.isArray(data))throw new Error('The invoice endpoint returned an unexpected response.');
const key=type==='SUPPLIER'?'supplier_id':'customer_id';
const rows=data.filter(row=>String(row.status||'').toUpperCase()==='POSTED'&&Number(row[key]||0)===partyId&&Number(row.outstanding_amount||0)>0.005);
if(!rows.length){
rowsEl.innerHTML=type==='SUPPLIER'?'<div class="sl-notice sl-warning-block"><b>No open posted AP invoices for this supplier.</b><span> A Supplier payment cannot be submitted until it is allocated to one or more posted invoices.</span></div>':'<div class="sl-empty-rich"><b>No open posted AR invoices for this customer.</b><span>An unapplied customer receipt can still be submitted as on-account credit if intended.</span></div>';
updatePaymentAllocationSummary(form);return;
}
rowsEl.innerHTML='<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Already allocated</th><th>Outstanding</th><th>Allocate now</th></tr></thead><tbody>'+rows.map(row=>{
const id=Number(row.id||0),outstanding=Math.max(0,Number(row.outstanding_amount||0));
return '<tr><td data-label="Invoice"><b>'+esc(row.invoice_number||row.invoice_num||('INV-'+id))+'</b><div class="sl-sub">'+esc(row.po_number||'Posted invoice')+'</div></td><td data-label="Date">'+esc(row.invoice_date||'—')+'</td><td data-label="Total">'+money(row.total_amount||0)+'</td><td data-label="Already allocated">'+money(row.allocated_amount||0)+'</td><td data-label="Outstanding">'+money(outstanding)+'</td><td data-label="Allocate now"><input type="number" min="0" max="'+outstanding.toFixed(2)+'" step="0.01" value="0" data-payment-allocation data-invoice-id="'+id+'" data-outstanding="'+outstanding.toFixed(2)+'" aria-label="Amount allocated to '+esc(row.invoice_number||('INV-'+id))+'" style="width:120px;max-width:100%"></td></tr>';
}).join('')+'</tbody></table></div>';
rowsEl.querySelectorAll('[data-payment-allocation]').forEach(input=>input.addEventListener('input',()=>updatePaymentAllocationSummary(form)));
updatePaymentAllocationSummary(form);
}catch(error){if(seq!==Number(form.dataset.paymentAllocationSeq||0))return;rowsEl.innerHTML='<div class="sl-notice sl-danger-block"><b>Invoice balances could not load.</b> '+esc(error.message||'Request failed')+' Please retry after checking finance permissions and System Health.</div>';updatePaymentAllocationSummary(form);}
}
async function loadFinanceApprovals(){
    const el=document.getElementById('financeApprovalQueue');if(!el)return;
    const c=state.boot?.user?.capabilities||{};
    if(!c.finance_approve&&!c.expense_approve){el.innerHTML='<div class="sl-notice">No finance decision authority is assigned to this ERP profile.</div>';return;}
    el.innerHTML='<div class="sl-empty">Loading submitted finance records…</div>';
    const tasks=[];
    if(c.expense_approve)tasks.push(['expense',api('/accounting/expenses?status=PENDING')]);
    if(c.finance_approve){tasks.push(['supplier_invoice',api('/accounting/supplier-invoices?status=PENDING')]);tasks.push(['payment',api('/accounting/payments?status=PENDING')]);}
    const results=await Promise.all(tasks.map(async t=>{try{return {type:t[0],rows:await t[1],error:null}}catch(e){return {type:t[0],rows:[],error:e.message||'Request failed'}}}));
    const errors=results.filter(x=>x.error),all=[],role=state.boot?.user?.role||'';
    results.forEach(group=>{
      let rows=Array.isArray(group.rows)?group.rows:[];
      if(group.type==='expense'){
        if(role==='owner')rows=rows.filter(x=>Number(x.location_id||0)===0&&Number(x.created_by||0)!==Number(state.boot?.user?.id||0));
        else if(['branch_manager','warehouse_manager'].includes(role))rows=rows.filter(x=>Number(x.location_id||0)>0);
      }
      rows.forEach(x=>all.push({type:group.type,row:x}));
    });
    const priority={expense:0,supplier_invoice:1,payment:2};
    all.sort((a,b)=>priority[a.type]-priority[b.type]||String((b.row.created_at||b.row.expense_date||b.row.invoice_date||b.row.payment_date)||'').localeCompare(String((a.row.created_at||a.row.expense_date||a.row.invoice_date||a.row.payment_date)||'')));
    let html=errors.length?'<div class="sl-notice sl-warning-block"><b>Some approval sources could not be loaded.</b><div>'+errors.map(x=>esc(x.type)+': '+esc(x.error)).join('<br>')+'</div><button type="button" class="sl-btn" id="retryFinanceApprovalErrors">RETRY APPROVAL QUEUE</button></div>':'';
    if(!all.length){if(errors.length){html+='<div class="sl-empty-rich"><b>Approval results are incomplete.</b><span>A failed source is not equivalent to zero pending items. Retry the queue before treating it as empty.</span></div>';}else{html+='<div class="sl-empty-rich"><b>No pending finance approvals.</b><span>Only records explicitly submitted and still pending appear here.</span></div>'; }el.innerHTML=html;el.querySelector('#retryFinanceApprovalErrors')?.addEventListener('click',loadFinanceApprovals);return;}
    html+='<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Type / Reference</th><th>Supplier / Branch / Party</th><th>Submitted by</th><th>Date</th><th>Amount (ETB)</th><th>Decision</th></tr></thead><tbody>';
    all.forEach(({type,row:r})=>{
      const id=Number(r.id||0),amount=type==='expense'?Number(r.amount||0)+Number(r.tax_amount||0):Number(r.total_amount||r.amount||0);
      const kind=type==='expense'?(Number(r.location_id||0)>0?'Location expense':'Management corporate expense'):type==='supplier_invoice'?'Supplier invoice':'Finance payment';
      const ref=type==='expense'?(r.expense_number||('EXP-'+id)):type==='supplier_invoice'?(r.invoice_number||('AP-'+id)):(r.payment_number||('PAY-'+id));
      const party=type==='expense'?(r.location_name||'Corporate / Management'):type==='supplier_invoice'?(r.supplier_name||'Supplier'):(r.supplier_name||r.customer_name||r.payment_type||'Finance');
      const detail=type==='expense'?(r.category||r.description||''):type==='supplier_invoice'?(r.po_number?'PO '+r.po_number:'PO required'):(r.method||'');
      const date=r.expense_date||r.invoice_date||r.payment_date||r.created_at||'';
      html+='<tr><td><b>'+esc(kind)+'</b><div class="sl-sub">'+esc(ref)+'</div><div class="sl-sub">'+esc(detail)+'</div></td><td>'+esc(party)+'</td><td>'+esc(r.created_by_name||'Management')+'</td><td>'+esc(date)+'</td><td>'+money(amount)+'</td><td><div class="sl-action-row"><button class="sl-btn primary" data-finance-action="APPROVE" data-finance-type="'+type+'" data-finance-id="'+id+'">APPROVE</button><button class="sl-btn" data-finance-action="REJECT" data-finance-type="'+type+'" data-finance-id="'+id+'">REJECT</button></div></td></tr>';
    });
    html+='</tbody></table></div>';el.innerHTML=html;
    el.querySelector('#retryFinanceApprovalErrors')?.addEventListener('click',loadFinanceApprovals);
    el.querySelectorAll('[data-finance-action]').forEach(btn=>{btn.onclick=async()=>{
      const action=String(btn.dataset.financeAction||''),type=String(btn.dataset.financeType||''),id=Number(btn.dataset.financeId||0);
      if(!id||!['expense','supplier_invoice','payment'].includes(type)||!['APPROVE','REJECT'].includes(action))return;
      let note='';
      if(action==='REJECT'){
        const value=await slReasonDialog('Reason for rejecting '+(type==='expense'?'expense':type==='supplier_invoice'?'supplier invoice':'payment'),'');
        if(value===null)return;
        note=value;
        if(note.trim().length<5){toast('Provide a meaningful rejection reason (at least 5 characters).',true);return;}
      }
      const row=btn.closest('tr'),originalLabel=btn.textContent;
      btn.disabled=true;btn.setAttribute('aria-busy','true');btn.textContent=action==='APPROVE'?'APPROVING…':'REJECTING…';
      if(row)row.querySelectorAll('button').forEach(b=>b.disabled=true);
      const segment=type==='expense'?'expenses':type==='supplier_invoice'?'supplier-invoices':'payments';
      let out;
      try{
        out=await api('/accounting/'+segment+'/'+id+'/status',{method:'POST',body:JSON.stringify({action,note})});
        const expectedStatus=action==='APPROVE'?'POSTED':'REJECTED';
        if(!out||Number(out.id)!==id||String(out.status||'').toUpperCase()!==expectedStatus||(action==='APPROVE'&&Number(out.journal_id||0)<=0)){
          throw new Error('The server did not confirm the expected '+expectedStatus+' status and required ledger journal. Refresh the queue to verify the saved state before retrying.');
        }
      }catch(e){
        toast((e.message||'Decision failed')+' No success was assumed; refresh the approval queue to verify whether the decision committed.',true);
        if(row)row.querySelectorAll('button').forEach(b=>b.disabled=false);
        btn.textContent=originalLabel;btn.removeAttribute('aria-busy');return;
      }
      toast((type==='expense'?'Expense':type==='supplier_invoice'?'Supplier invoice':'Payment')+' '+out.status+(action==='APPROVE'?' and journal '+out.journal_id+' recorded.':' recorded as rejected.'));
      try{
        await loadFinanceApprovals();state.financeLoaded=false;await loadFinance(true);await loadFinanceGL();await loadNotifications();
      }catch(refreshError){toast('The '+type.replace('_',' ')+' decision was saved, but the board could not fully refresh. Use Refresh to verify current balances and queues.',true);}
    }});
}
async function loadFinanceGL(){
const el=document.getElementById('financeGL');if(!el)return;try{
const d=await api('/accounting/financials');const p=d.profit_and_loss||{};const b=d.balance_sheet||{};
el.innerHTML='<div class="sl-grid">'+card('Revenue',money(p.revenue||0),'Posted revenue')+card('COGS',money(p.cogs||0),'Cost of goods sold')+card('Operating Expenses',money(p.expenses||0),'Posted expenses')+card('Net Income',money(p.net_income||0),'Revenue less COGS and expenses')+'</div><div class="sl-notice">Balance sheet check: <b>'+money(b.check||0)+'</b> (should be 0.00)</div><div class="sl-table-wrap"><table class="sl-table"><tr><th>Code</th><th>Account</th><th>Type</th><th>Debit</th><th>Credit</th><th>Balance</th></tr>'+((d.trial_balance||[]).map(x=>'<tr><td>'+esc(x.account_code)+'</td><td>'+esc(x.account_name)+'</td><td>'+esc(x.account_type)+'</td><td>'+money(x.debit)+'</td><td>'+money(x.credit)+'</td><td>'+money(x.balance)+'</td></tr>').join(''))+'</table></div>';
}catch(e){el.innerHTML='<p class="sl-danger-text">'+esc(e.message)+'</p>'}
}
async function submit(e){
e.preventDefault();const f=e.currentTarget;if(f.dataset.busy==='1')return;f.dataset.busy='1';const btn=f.querySelector('button[type="submit"]');const original=btn?btn.textContent:'';if(btn){btn.disabled=true;btn.setAttribute('aria-busy','true');btn.textContent='PROCESSING…'}const fd=new FormData(f);let successMessage='Saved successfully';
try{
if(f.id==='userForm'){const d=Object.fromEntries(fd);d.location_ids=[...f.querySelectorAll('[name="location_ids[]"] option:checked')].map(o=>Number(o.value));d.branch_location_id=d.location_ids[0]||null;await api('/app/users',{method:'POST',body:JSON.stringify(d)});toast('User created with operational scope');f.reset();await loadUsers();return}

if(f.id==='saleForm'){return}
else if(f.id==='receiveForm'){const d=Object.fromEntries(fd);const r=await api('/receipts',{method:'POST',body:JSON.stringify({sku:d.sku,quantity_received:Number(d.quantity),supplier_source:d.source||'',unit_cost:Number(d.unit_cost||0)})});if(!r||!r.receipt_id)throw new Error('ERP did not return a receipt tracking ID.');successMessage='Receipt '+r.receipt_id+' created. Verify the physical quantity in the receipt queue, then press RECEIVED to post stock.';f.reset();}
else if(f.id==='transferForm'){const d=Object.fromEntries(fd);await api('/transfers',{method:'POST',body:JSON.stringify({from_location_id:Number(d.from_location_id),to_location_id:Number(d.to_location_id),sku:d.sku,quantity:Number(d.quantity)})})}
else if(f.id==='whForm'){
 const role=state.boot?.user?.role||'';
 if(role!=='owner'||!state.boot?.user?.capabilities?.wholesale)throw new Error('Only the Stockland Owner can create a wholesale sale. No data was changed.');
 const d=Object.fromEntries(fd),sku=String(d.sku||'').trim(),quantity=Number(d.quantity);
 if(!sku||!Number.isFinite(quantity)||quantity<=0)throw new Error('Select a live Product Master SKU and enter a positive sale quantity.');
 const live=await api('/wholesale/surplus-advice?sku='+encodeURIComponent(sku));
 if(!live||live.read_only!==true||!Array.isArray(live.rows)||!live.rows.length)throw new Error('Fresh wholesale surplus could not be verified. The sale is stopped; no data was changed.');
 const advice=live.rows[0];
 if(advice.integrity_hold)throw new Error('Wholesale sale blocked because the SKU has a stock/ledger integrity hold. Reconcile stock and audit evidence before deciding whether to sell.');
 const safe=Number(advice.wholesale_surplus_qty);
 if(!Number.isFinite(safe)||safe<0)throw new Error('Fresh safe-surplus amount is unknown. The sale is stopped.');
 const payload={...d,sku,quantity,advisory_surplus_qty:safe};
 if(String(d.price??'').trim()!==''){payload.price=Number(d.price)}else{delete payload.price}
 if(quantity>safe+0.0001){
  const answer=window.prompt('OWNER DECISION — protected internal needs and the Central Warehouse reorder floor leave a suggested wholesale surplus of '+safe+' unit(s) for '+sku+'. You entered '+quantity+'. To continue above the suggestion, enter a clear reason (minimum 10 characters). Choose Cancel to keep the stock internal.');
  if(answer===null||String(answer).trim().length<10)throw new Error('Sale cancelled. Internal stock remains unchanged; an override requires a documented reason of at least 10 characters.');
  payload.override_internal_need_confirmed=true;payload.override_reason=String(answer).trim();
 }
 const status=document.getElementById('wholesaleFreshAdvice');if(status)status.textContent='Fresh server check: '+safe+' units suggested as saleable after internal needs and warehouse reserve. '+(quantity>safe?'Owner override reason provided; server will independently revalidate and audit this decision.':'Your requested quantity is within the current suggestion. Server will independently revalidate before posting.');
 const saved=await api('/wholesale',{method:'POST',body:JSON.stringify(payload)});state.wholesalePrefill=null;state.wholesaleAdvice=null;successMessage='Wholesale sale '+String(saved.sale_id||'')+' completed and posted through the existing order, stock ledger, accounting and audit workflow.';
}
else if(f.id==='retForm'){const d=Object.fromEntries(fd);await api('/returns',{method:'POST',body:JSON.stringify({...d,location_id:Number(d.location_id),quantity:Number(d.quantity)})})}
else if(f.id==='closeForm'){const d=Object.fromEntries(fd);await api('/daily-closings/close',{method:'POST',body:JSON.stringify({branch_location_id:Number(d.branch_location_id),business_date:d.business_date})})}
else if(f.id==='settingsForm'){const d=Object.fromEntries(fd);d.vat_rate=Number(d.vat_rate);await api('/app/settings',{method:'POST',body:JSON.stringify(d)})}
else if(f.id==='reqForm'){const skus=[...f.querySelectorAll('[name="reqsku[]"]')].map(x=>x.value),qty=[...f.querySelectorAll('[name="reqqty[]"]')].map(x=>Number(x.value));await api('/requisitions',{method:'POST',body:JSON.stringify({items:skus.map((sku,i)=>({sku,quantity:qty[i]}))})})}
toast(successMessage);await load();await loadNotifications();
}catch(err){toast(err.message,true)}finally{f.dataset.busy='0';if(btn){btn.disabled=false;btn.removeAttribute('aria-busy');btn.textContent=original||'SUBMIT'}}
}
async function loadReceipts(){try{const rows=await api('/receipts');const el=document.getElementById('receiptQueue');if(!el)return;if(!rows.length){el.innerHTML='<div class="sl-empty">No legacy manual receipts. New supplier deliveries are processed from the approved PO action queue.</div>';return;}el.innerHTML='<div class="sl-table-wrap"><table class="sl-table"><thead><tr><th>Receipt</th><th>SKU</th><th>Product</th><th>Expected</th><th>Actual</th><th>Source</th><th>Status / control</th></tr></thead><tbody>'+rows.map(r=>{const completed=r.status==='Completed';return '<tr><td>'+esc(r.receipt_id)+'</td><td>'+esc(r.sku)+'</td><td>'+esc(r.product_name||'')+'</td><td>'+esc(r.quantity_received)+'</td><td>'+(completed?esc(r.actual_quantity_received):'—')+'</td><td>'+esc(r.supplier_source||'')+'</td><td>'+(completed?'<span class="sl-pill ok">Historical posting</span>':'<span class="sl-pill warn">HOLD — legacy receipt</span><div class="sl-sub">Do not post here. Reconcile to an Owner-approved PO.</div>')+'</td></tr>';}).join('')+'</tbody></table></div>'}catch(e){toast(e.message,true)}}
async function loadRecon(){
const el=document.getElementById('reconTable');if(!el)return;
el.innerHTML='<div class="sl-empty">Loading stock-adjustment requests…</div>';
try{
 const rows=await api('/app/reconciliation');
 if(!Array.isArray(rows))throw new Error('The reconciliation service returned an unexpected response.');
 if(!rows.length){el.innerHTML='<div class="sl-empty-rich"><b>No manual variance requests.</b><span>New requests will appear here after they are submitted for management review.</span></div>';return;}
 const canDecide=!!(state.boot?.user?.capabilities?.settings);
 const data=rows.map(r=>{
  const variance=Number(r.variance_qty||0),pending=String(r.status||'').toLowerCase()==='pending',batchLinked=!!r.count_batch_id;
  const decision=canDecide&&pending&&!batchLinked?'<button type="button" class="sl-btn primary sl-btn-sm" data-recon-decision="approve" data-recon-id="'+esc(r.id)+'">Approve & post</button><button type="button" class="sl-btn sl-btn-sm" data-recon-decision="reject" data-recon-id="'+esc(r.id)+'">Reject</button>':(batchLinked?'<span class="sl-sub">Use whole count-batch approval</span>':'<span class="sl-sub">'+(pending?'Awaiting authorized decision':'No action required')+'</span>');
  return '<tr><td data-label="Request"><b>'+esc(r.adjustment_id||('ADJ-'+r.id))+'</b><div class="sl-sub">'+esc(r.created_at||'')+'</div></td><td data-label="Location">'+esc(r.location_name||'—')+'</td><td data-label="SKU / Product"><b>'+esc(r.sku||'—')+'</b><div class="sl-sub">'+esc(r.product_name||'')+'</div></td><td data-label="System qty">'+esc(r.system_qty??'—')+'</td><td data-label="Counted qty">'+esc(r.counted_qty??'—')+'</td><td data-label="Variance"><span class="sl-pill '+(variance<0?'bad':(variance>0?'warn':'ok'))+'">'+(variance>0?'+':'')+esc(variance)+'</span></td><td data-label="Status"><span class="sl-pill '+(pending?'warn':(String(r.status).toLowerCase()==='approved'?'ok':'bad'))+'">'+esc(r.status||'Unknown')+'</span></td><td data-label="Reason">'+esc(r.reason||'—')+'</td><td data-label="Action">'+decision+'</td></tr>';
 }).join('');
 el.innerHTML='<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Request</th><th>Location</th><th>SKU / Product</th><th>System qty</th><th>Counted qty</th><th>Variance</th><th>Status</th><th>Reason</th><th>Action</th></tr></thead><tbody>'+data+'</tbody></table></div>';
 el.querySelectorAll('[data-recon-decision]').forEach(b=>b.addEventListener('click',async()=>{
  const id=String(b.dataset.reconId||'');if(!id)return;let payload={decision:b.dataset.reconDecision==='approve'?'Approved':'Rejected'};
  if(payload.decision==='Rejected'){const reason=await slReasonDialog('Stock adjustment rejection reason','');if(reason===null)return;payload.reason=reason;}
  b.disabled=true;try{await api('/app/reconciliation/'+encodeURIComponent(id)+'/decision',{method:'POST',body:JSON.stringify(payload)});toast(payload.decision==='Approved'?'Stock adjustment approved and posted':'Stock adjustment rejected');await load();}catch(e){toast(e.message,true);b.disabled=false;}
 }));
}catch(e){el.innerHTML='<div class="sl-notice sl-danger-block">'+esc(e.message||'Reconciliation requests could not be loaded.')+'</div>';}
}

async function previewCount(e){e.preventDefault();const file=document.getElementById('countXlsxFile').files[0];if(!file)return;const fd=new FormData();fd.append('file',file);const box=document.getElementById('countPreview');box.innerHTML='<div class="sl-notice">Reading and matching the count workbook…</div>';try{const d=await api('/app/counts/preview',{method:'POST',body:fd});box.innerHTML='<div class="sl-statline">'+Object.entries(d.summary||{}).map(([k,v])=>'<div class="sl-stat"><b>'+esc(v)+'</b><span>'+esc(k)+'</span></div>').join('')+'</div>'+(d.errors&&d.errors.length?'<div class="sl-notice" style="margin-top:12px;background:#ffebee;color:#9b2432"><b>Fix these rows before commit</b><ul>'+d.errors.map(x=>'<li>'+esc(x)+'</li>').join('')+'</ul></div>':'<div class="sl-notice" style="margin-top:12px">Preview passed. '+esc((d.preview||[]).length)+' variants matched.</div><button class="sl-btn primary" id="commitCount" type="button">CONFIRM & SUBMIT COUNT</button>');const c=document.getElementById('commitCount');if(c)c.onclick=async()=>{c.disabled=true;try{const x=await api('/app/counts/commit',{method:'POST',body:JSON.stringify({confirm:true})});toast(x.message||'Physical count submitted');await loadCountBatches();await load()}catch(err){toast(err.message,true);c.disabled=false}}}catch(err){box.innerHTML='<p class="sl-danger-text">'+esc(err.message)+'</p>'}}
async function loadCountBatches(){try{const response=await api('/app/counts');const rows=Array.isArray(response)?response:[];const el=document.getElementById('countBatches');if(!el)return;if(!rows.length){el.innerHTML='<div class=\"sl-empty-rich\"><b>No physical-count batches yet.</b><span>Validated uploads appear here after they are committed.</span></div>';return;}el.innerHTML='<div class=\"sl-table-wrap\"><table class=\"sl-table sl-responsive-cards\"><thead><tr><th>Batch</th><th>Location</th><th>Date</th><th>Status</th><th>Lines</th><th>Absolute variance units</th><th>Action</th></tr></thead><tbody>'+rows.map(x=>'<tr><td data-label=\"Batch\">'+esc(x.batch_id)+'</td><td data-label=\"Location\">'+esc(x.location_name)+'</td><td data-label=\"Date\">'+esc(x.count_date)+'</td><td data-label=\"Status\">'+esc(x.status)+'</td><td data-label=\"Lines\">'+esc(x.line_count)+'</td><td data-label=\"Absolute variance units\">'+esc(x.total_abs_variance??0)+'</td><td data-label=\"Action\"><button class=\"sl-btn\" data-count-batch=\"'+esc(x.id)+'\">VIEW'+(state.boot.user.can_all&&x.status==='Pending'?' / APPROVE':'')+'</button></td></tr>').join('')+'</tbody></table></div>';el.querySelectorAll('[data-count-batch]').forEach(b=>b.onclick=()=>openCountBatch(b.dataset.countBatch))}catch(e){const el=document.getElementById('countBatches');if(el)el.innerHTML='<div class=\"sl-notice sl-danger-block\">'+esc(e.message||'Physical-count batches could not be loaded.')+'</div>';}}
async function openCountBatch(id){try{const d=await api('/app/counts/'+encodeURIComponent(id)+'/report');const b=d.batch,s=d.summary||{};const canApprove=state.boot.user.can_all&&b.status==='Pending';const ov=document.createElement('div');ov.className='sl-overlay';ov.innerHTML='<div class="sl-drawer"><div class="sl-page-title"><div><h2 style="font-size:21px">'+esc(b.batch_id)+'</h2><p>'+esc(b.location_name)+' • '+esc(b.count_date)+' • '+esc(b.status)+'</p></div><button class="sl-btn" id="closeCountBatch">Close</button></div><div class="sl-grid">'+card('Lines',s.lines,'Counted variants')+card('System Qty',s.system_qty,'Snapshot')+card('Counted Qty',s.counted_qty,'Physical')+card('Variance',s.variance_qty,'Net difference')+'</div><div class="sl-table-wrap"><table class="sl-table"><tr><th>SKU</th><th>Brand</th><th>Product</th><th>Size</th><th>Color</th><th>System</th><th>Found</th><th>Variance</th></tr>'+d.lines.map(x=>'<tr><td>'+esc(x.sku)+'</td><td>'+esc(x.brand)+'</td><td>'+esc(x.product_name)+'</td><td>'+esc(x.size||'—')+'</td><td>'+esc(x.color||'—')+'</td><td>'+esc(x.system_qty)+'</td><td>'+esc(x.counted_qty)+'</td><td>'+esc(x.variance_qty)+'</td></tr>').join('')+'</table></div>'+(canApprove?'<div style="margin-top:15px;display:flex;gap:8px"><button class="sl-btn primary" id="approveCountBatch">APPROVE & POST FINAL INVENTORY</button><button class="sl-btn" id="rejectCountBatch">REJECT COUNT</button></div>':'')+'</div>';document.body.appendChild(ov);document.getElementById('closeCountBatch').onclick=()=>ov.remove();const ab=document.getElementById('approveCountBatch');if(ab)ab.onclick=async()=>{ab.disabled=true;try{await api('/app/counts/'+b.id+'/decision',{method:'POST',body:JSON.stringify({decision:'Approved'})});toast('Physical count approved; final inventory updated');ov.remove();await load()}catch(e){toast(e.message,true);ab.disabled=false}};const rb=document.getElementById('rejectCountBatch');if(rb)rb.onclick=async()=>{const reason=await slReasonDialog('Physical count rejection reason','');if(reason===null)return;rb.disabled=true;try{await api('/app/counts/'+b.id+'/decision',{method:'POST',body:JSON.stringify({decision:'Rejected',reason})});toast('Physical count rejected');ov.remove();await load()}catch(e){toast(e.message,true);rb.disabled=false}}}catch(e){toast(e.message,true)}}
async function loadReqs(){
    try{const d=await api16('/requisitions');const el=document.getElementById('reqTable');if(!el)return;
      const stages=['Requested','Reviewed','Approved','Reserved','Picked','Received'];
      el.innerHTML='<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Requisition</th><th>Branch</th><th>Requested by</th><th>Date</th><th>Units (Requested / Approved)</th><th>Stage</th><th>Pending action</th><th>Action</th></tr></thead><tbody>'+d.map(r=>{
        const actions=r.available_actions||[];const first=actions[0];
        const review=first==='REVIEW'?'<button class="sl-btn sl-btn-sm sl-btn-gold" data-review-req="'+esc(r.requisition_id)+'" aria-label="Review requisition '+esc(r.requisition_id)+'">REVIEW</button>':'';
        const quickApprove=(first==='MANAGEMENT_APPROVE'||first==='OWNER_APPROVE')?'<button class="sl-btn sl-btn-sm sl-btn-primary" data-req-detail-v16="'+esc(r.requisition_id)+'" aria-label="Review and decide requisition '+esc(r.requisition_id)+'">REVIEW & DECIDE</button>':'';
        const operational=(first==='RESERVE'||first==='PICK'||first==='RECEIVE')?'<button class="sl-btn sl-btn-sm '+(first==='RECEIVE'?'sl-btn-gold':'sl-btn-primary')+'" data-req-detail-v16="'+esc(r.requisition_id)+'" aria-label="'+esc(first==='RESERVE'?'Reserve requisition ':first==='PICK'?'Pick requisition ':'Receive requisition ')+esc(r.requisition_id)+'">'+esc(first==='RESERVE'?'RESERVE':first==='PICK'?'PICK':'RECEIVE')+'</button>':'';
        const act=review||quickApprove||operational||'<button class="sl-btn sl-btn-sm" data-req-detail-v16="'+esc(r.requisition_id)+'" aria-label="View requisition '+esc(r.requisition_id)+'">VIEW DETAILS</button>';
        const idx=stages.indexOf(r.status);const step=idx>=0?'<span class="sl-stage-inline"><b>'+(idx+1)+'</b>/6 '+esc(r.status)+'</span>': '<span class="sl-pill bad">'+esc(r.status)+'</span>';
        return '<tr><td data-label="Requisition"><b>'+esc(r.requisition_id)+'</b></td><td data-label="Branch">'+esc(r.location_name)+'</td><td data-label="Requested by">'+esc(r.requester_name||'')+'</td><td data-label="Date">'+esc(r.date_requested||'')+'</td><td data-label="Units">'+esc(r.requested_units)+' / '+esc(r.approved_units)+'</td><td data-label="Stage">'+step+'</td><td data-label="Pending action">'+esc(r.pending_action_owner||'—')+'</td><td data-label="Action">'+act+'</td></tr>';
      }).join('')+'</tbody></table></div>';
      document.querySelectorAll('[data-review-req]').forEach(b=>b.onclick=()=>openReqDetailV16(b.dataset.reviewReq));document.querySelectorAll('[data-req-detail-v16]').forEach(b=>b.onclick=()=>openReqDetailV16(b.dataset.reqDetailV16));
    }catch(e){toast(e.message,true)}
    if(document.getElementById('warehouseReqQueue'))loadWarehouseQueue();
}
async function loadWarehouseQueue(){
    const el=document.getElementById('warehouseReqQueue');if(!el)return;
    try{
      const rows=await api16('/requisitions');const queue=rows.filter(q=>['Approved','Reserved','Picked'].includes(q.status));
      const role=state.boot.user.role;const canReserve=role==='warehouse_manager';const canPick=role==='warehouse_employee'||role==='warehouse_manager';
      el.innerHTML='<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Requisition</th><th>Branch</th><th>Units</th><th>Status</th><th>Pickup day</th><th>Action</th></tr></thead><tbody>'+(queue.length?queue.map(q=>{const canDirectPick=role==='warehouse_manager'&&q.status==='Approved';let a='';if(q.status==='Approved'){a=canDirectPick?'<div class="sl-action-row"><button class="sl-btn sl-btn-sm sl-btn-primary" data-warehouse-req="'+esc(q.requisition_id)+'" data-warehouse-action="pick">PICK NOW</button><button class="sl-btn sl-btn-sm sl-btn-gold" data-warehouse-req="'+esc(q.requisition_id)+'" data-warehouse-action="reserve">RESERVE / ASSIGN</button></div>':'<span class="sl-sub">Pending: Central Warehouse Manager</span>';}else if(q.status==='Reserved'){a=canPick?'<button class="sl-btn sl-btn-sm sl-btn-primary" data-warehouse-req="'+esc(q.requisition_id)+'" data-warehouse-action="pick">PICK / READY</button>':'<span class="sl-sub">Pending: Central Warehouse pick</span>';}else{a='<span class="sl-ready-chip">READY FOR BRANCH RECEIVE</span>';}return '<tr><td data-label="Requisition"><b>'+esc(q.requisition_id)+'</b></td><td data-label="Branch">'+esc(q.location_name)+'</td><td data-label="Units">'+esc(q.approved_units)+'</td><td data-label="Status"><span class="sl-pill '+(['Picked','Received','Approved','Reserved'].includes(q.status)?'ok':'info')+'">'+esc(q.status)+'</span></td><td data-label="Pickup day">'+esc(q.pickup_date||'Not scheduled')+'</td><td data-label="Action">'+a+'</td></tr>';}).join(''):'<tr><td colspan="6"><div class="sl-empty">No approved requisitions waiting for warehouse action.</div></td></tr>')+'</tbody></table></div>';
      document.querySelectorAll('[data-warehouse-req]').forEach(b=>b.onclick=async()=>{const row=queue.find(q=>q.requisition_id===b.dataset.warehouseReq);if(!row)return;const action=b.dataset.warehouseAction||(row.status==='Approved'?'reserve':'pick');let pickup=row.pickup_date||'';if(action==='reserve'){pickup=await slDateDialog('Select branch pickup date',pickup||localDate());if(!pickup)return}const payload={action};if(action==='reserve')payload.pickup_date=pickup;b.disabled=true;try{await api16('/requisitions/'+encodeURIComponent(row.requisition_id)+'/warehouse-action',{method:'POST',body:JSON.stringify(payload)});toast(action==='reserve'?'Stock reserved and pickup appointment confirmed':'Items picked and ready for branch receipt');await loadWarehouseQueue();await loadReqs();await loadNotifications()}catch(e){toast(e.message,true);b.disabled=false}});
    }catch(e){el.innerHTML='<div class="sl-notice sl-danger-block">'+esc(e.message)+'</div>'}
}
function slReceiveDialog(transferId,defaultQty){
  return new Promise(resolve=>{
    const ov=document.createElement('div');ov.className='sl-overlay';
    ov.innerHTML='<div class="sl-modal sl-accessible-dialog" role="dialog" aria-modal="true"><div class="sl-drawer-head"><div><div class="sl-eyebrow">BRANCH RECEIVING</div><h2 style="font-size:21px">Confirm '+esc(transferId)+'</h2><p>Enter actual quantity received. Add a comment only when there is a deviation or receiving note.</p></div><button class="sl-icon-btn" id="slRecvCancel" aria-label="Close">×</button></div><div class="sl-form"><div class="sl-field"><label>Actual received quantity</label><input id="slRecvQty" type="number" min="0" step="0.01" value="'+esc(defaultQty)+'"></div><div class="sl-field full"><label>Comment / deviation note (optional)</label><textarea id="slRecvNote" rows="4" placeholder="Required when actual received differs from dispatched."></textarea></div></div><div class="sl-action-row"><button class="sl-btn" id="slRecvCancel2">Cancel</button><button class="sl-btn sl-btn-gold" id="slRecvOk">CONFIRM RECEIVE</button></div></div>';
    document.body.appendChild(ov);const close=v=>{ov.remove();resolve(v)};const qty=document.getElementById('slRecvQty'),note=document.getElementById('slRecvNote');document.getElementById('slRecvCancel').onclick=()=>close(null);document.getElementById('slRecvCancel2').onclick=()=>close(null);document.getElementById('slRecvOk').onclick=()=>{const q=Number(qty.value);const n=String(note.value||'').trim();if(!Number.isFinite(q)||q<0){qty.focus();return}if(Math.abs(q-Number(defaultQty))>0.00001&&!n){note.focus();return}close({qty:q,note:n})};ov.onclick=e=>{if(e.target===ov)close(null)};requestAnimationFrame(()=>qty.focus());
  });
}
async function advanceTransferToReceive(transferId,receivedQty,reason){
    const payload={status:'Received',received_qty:Number(receivedQty||0),reason:String(reason||'')};
    return api('/transfers/'+encodeURIComponent(transferId)+'/status',{method:'POST',body:JSON.stringify(payload)});
}
async function openReqDetailV16(id){
  try{
    const d=await api16('/requisitions/'+encodeURIComponent(id));
    const q=d.requisition,items=d.items||[],lines=d.approval_lines||[],role=state.boot.user.role;
    const canReview=role==='branch_manager'&&q.status==='Requested';
    const approverLine=lines.find(a=>a.can_act&&q.status==='Reviewed');
    const isApprover=!!approverLine&&(role==='management'||role==='owner');
    const canReserve=role==='warehouse_manager'&&q.status==='Approved';
    const canDirectPick=role==='warehouse_manager'&&q.status==='Approved';
    const canPick=(role==='warehouse_employee'||role==='warehouse_manager')&&q.status==='Reserved';
    const canReceive=role==='branch_manager'&&q.status==='Picked'&&!!state.boot.user.capabilities?.receive_transfer;
    const stages=['Requested','Reviewed','Approved','Reserved','Picked','Received'];
    const idx=stages.indexOf(q.status);
    const stepper=stages.map((st,i)=>'<div class="sl-req-step '+(i<idx?'done ':i===idx?'current ':'')+'"><span>'+(i+1)+'</span><b>'+st+'</b></div>').join('');
    const totals={requested:0,available:0,shortfall:0,approved:0};
    items.forEach(i=>{const requested=Number(i.quantity_requested||0),available=Math.max(0,Number(i.availability?.available||0));totals.requested+=requested;totals.available+=available;totals.shortfall+=Math.max(0,requested-available);totals.approved+=Number(i.quantity_approved||0)});
    const fullyAvailable=items.length>0&&items.every(i=>Number(i.quantity_requested||0)<=Math.max(0,Number(i.availability?.available||0)));
    const decisionStage=approverLine?.stage||role;
    const availabilityRows=items.map(i=>{const requested=Number(i.quantity_requested||0),available=Math.max(0,Number(i.availability?.available||0)),shortfall=Math.max(0,requested-available),max=Math.min(requested,available);return '<tr><td data-label="SKU / Product"><b>'+esc(i.sku)+'</b><div class="sl-sub">'+esc(i.product_name||'')+'</div></td><td data-label="Requested">'+esc(requested)+'</td><td data-label="Available now"><b>'+esc(available)+'</b></td><td data-label="Shortfall"><span class="sl-pill '+(shortfall?'warn':'ok')+'">'+esc(shortfall)+'</span></td><td data-label="Approval quantity"><input class="sl-inline-number sl-approval-qty" data-approval-item="'+esc(i.id)+'" type="number" min="0" max="'+esc(max)+'" step="0.01" value="'+esc(max)+'" '+(isApprover?'':'disabled')+'></td></tr>'}).join('');
    const approvalPanel=isApprover?'<div class="sl-panel sl-live-approval-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">LIVE ERP DECISION</div><h3>Approve / Partial / Reject</h3><small>Checked '+esc(d.availability_checked_at||'now')+'. Values come from the ERP inventory ledger and active reservations.</small></div></div><div class="sl-approval-summary"><div><span>Requested</span><b>'+esc(totals.requested)+'</b></div><div><span>Available now</span><b>'+esc(totals.available)+'</b></div><div><span>Shortfall</span><b>'+esc(totals.shortfall)+'</b></div></div>'+(fullyAvailable?'<div class="sl-notice sl-notice-soft"><b>FULL APPROVAL AVAILABLE.</b> Current Central Warehouse availability covers every requested line.</div>':'<div class="sl-notice sl-notice-warn"><b>FULL APPROVAL BLOCKED.</b> At least one requested line exceeds live Central Warehouse availability. Use partial quantities up to the available amount, or reject. The server will re-check before commit.</div>')+'<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>SKU / Product</th><th>Requested</th><th>Available now</th><th>Shortfall</th><th>Approval quantity</th></tr></thead><tbody>'+availabilityRows+'</tbody></table></div><div class="sl-action-row"><button class="sl-btn sl-btn-primary" id="reqApproveFull" '+(fullyAvailable?'':'disabled')+'>APPROVE FULL</button><button class="sl-btn sl-btn-gold" id="reqApprovePartial" '+(totals.available>0?'':'disabled')+'>APPROVE PARTIAL</button><button class="sl-btn danger" id="reqReject">REJECT</button></div><div class="sl-sub sl-approval-note">Full approval is available only when every line is currently covered. Partial approval requires a reason and cannot exceed current available stock. The server re-checks availability at decision time.</div></div>':'';
    const approvalRouting='<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">APPROVAL ROUTING</div><h3>Decision history</h3></div></div><div class="sl-approval-grid">'+lines.map(a=>'<div class="sl-approval-line '+(String(a.decision||'').toUpperCase()==='REJECT'?'danger':'')+'"><div class="sl-approval-person"><span class="sl-approval-avatar">'+esc(a.label.slice(0,1))+'</span><span><strong>'+esc(a.label)+'</strong><small>'+esc(a.decision||'Awaiting action')+(a.approved_at?' • '+esc(a.approved_at):'')+(a.notes?' • '+esc(a.notes):'')+'</small></span></div><div>'+approvalPill(a.decision)+'</div></div>').join('')+'</div></div>';
    const reviewBtn=canReview?'<div class="sl-action-row"><button class="sl-btn sl-btn-gold" id="reviewReqV16">REVIEW & SEND TO OWNER / MANAGEMENT</button></div>':'';
    const warehouseActions=(canReserve||canPick)?'<div class="sl-panel sl-warehouse-action-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">STORE / WAREHOUSE</div><h3>'+(canReserve?'Reserve or pick approved stock':'Pick reserved stock')+'</h3></div><small>'+(canReserve?'Reserve with a pickup date, or direct-PICK approved stock now.':'Warehouse employees and managers may pick only after reservation.')+'</small></div><div class="sl-form">'+(canReserve?'<div class="sl-field"><label>Pickup day / appointment (for Reserve)</label><input id="warehousePickupDate" type="date" value="'+esc(q.pickup_date||localDate())+'"></div>':'')+'<div class="sl-field" style="align-self:end">'+(canReserve?'<button class="sl-btn sl-btn-gold" id="warehouseActionBtn">RESERVE STOCK</button> <button class="sl-btn sl-btn-primary" id="warehouseDirectPickBtn">PICK / READY NOW</button>':'<button class="sl-btn sl-btn-primary" id="warehouseActionBtn">PICK / READY FOR BRANCH</button>')+'</div></div></div>':'';
    const availabilityCards=(d.availability||[]).map(g=>'<div class="sl-availability-card"><b>'+esc(g.sku)+'</b><span>Requested '+esc(g.requested)+'</span><span>Available '+esc(g.available)+'</span><span>'+(Number(g.shortage)>0?'Short '+esc(g.shortage):'Fully available')+'</span><span class="sl-pill '+(g.warehouse_reorder_alert?'warn':'ok')+'">'+(g.warehouse_reorder_alert?'Reorder alert':'Within reorder')+'</span></div>').join('');
    const sourcingRows=(d.sourcing_advice||[]).map(g=>{const best=g.recommended_source;const src=(g.sources||[]).slice(0,4);return '<div class="sl-panel sl-sourcing-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">SYSTEM STOCK SOURCING</div><h4 style="margin:0">'+esc(g.sku||'')+' • '+esc(g.product_name||'')+'</h4></div><span class="sl-pill info">'+esc(g.status||'CHECK')+'</span></div><div class="sl-grid sl-grid-compact"><div><span class="sl-sub">Branch available</span><b>'+esc(g.branch?.available??0)+'</b></div><div><span class="sl-sub">Requested</span><b>'+esc(g.requested??0)+'</b></div><div><span class="sl-sub">Central available</span><b>'+esc(g.central_available??0)+'</b></div><div><span class="sl-sub">Peer branch total</span><b>'+esc(g.peer_branch_available??0)+'</b></div></div>'+(best?'<div class="sl-notice sl-notice-soft"><b>Best current source:</b> '+esc(best.location_name)+' • '+esc(best.location_type)+' • Available '+esc(best.available)+' • Claimable '+esc(best.claimable)+'. The system only informs; transfer, reservation and approval remain under the existing workflow.</div>':'<div class="sl-notice sl-notice-soft">No external stock is currently available from Central Warehouse or peer branches.</div>')+(src.length?'<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Source</th><th>Type</th><th>Available</th><th>Claimable</th></tr></thead><tbody>'+src.map(x=>'<tr><td data-label="Source"><b>'+esc(x.location_name)+'</b></td><td data-label="Type">'+esc(x.location_type)+'</td><td data-label="Available">'+esc(x.available)+'</td><td data-label="Claimable">'+esc(x.claimable)+'</td></tr>').join('')+'</tbody></table></div>':'')+'</div>';}).join('');
    const supplyRows=(d.transfers||[]).map(t=>'<tr><td data-label="Transfer">'+esc(t.transfer_id)+'</td><td data-label="SKU">'+esc(t.sku)+'</td><td data-label="Qty">'+esc(t.quantity)+'</td><td data-label="Status"><span class="sl-pill '+(['Received','Picked','Reserved','Approved'].includes(t.status)?'ok':'info')+'">'+esc(t.status)+'</span></td><td data-label="Picked">'+esc(t.dispatched_qty||0)+'</td><td data-label="Received">'+esc(t.received_qty||0)+'</td></tr>').join('')||'<tr><td colspan="6">No warehouse supply lines yet.</td></tr>';
    const receivePanel=canReceive?'<div class="sl-panel sl-branch-receive-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">BRANCH RECEIVING</div><h3>Receive picked stock</h3></div><small>No In Transit step. Picked goes directly to branch receipt.</small></div><div class="sl-notice">Confirm the actual quantity received. A reason is required for any variance.</div><button class="sl-btn sl-btn-gold" id="receiveReqV16">CONFIRM BRANCH RECEIVE</button></div>':'';
    const body='<div class="sl-drawer sl-req-drawer"><div class="sl-drawer-head"><div><div class="sl-eyebrow">REQUISITION • 6-STAGE SOP</div><h2>'+esc(q.requisition_id)+'</h2><p>'+esc(q.location_name)+' • '+esc(q.date_requested)+' • '+esc(q.requester_name||'')+'</p></div><button class="sl-icon-btn" id="closeReqV16" aria-label="Close requisition">×</button></div><div class="sl-req-stepper" aria-label="Requisition workflow">'+stepper+'</div><div class="sl-request-summary"><div><span>Current stage</span><b>'+esc(q.status)+'</b></div><div><span>Requested</span><b>'+esc(totals.requested)+'</b></div><div><span>Approved</span><b>'+esc(totals.approved)+'</b></div><div><span>Pending</span><b>'+esc(q.status==='Requested'?'Branch Manager':q.status==='Reviewed'?'Owner / Management':q.status==='Approved'?'Store Manager':q.status==='Reserved'?'Store/Warehouse':q.status==='Picked'?'Branch Manager':'—')+'</b></div></div><div class="sl-section-label">1 • Live ERP availability</div><div class="sl-availability-grid">'+availabilityCards+'</div><div class="sl-section-label">2 • System stock sourcing</div>'+sourcingRows+approvalRouting+reviewBtn+approvalPanel+warehouseActions+receivePanel+'<div class="sl-section-label">3 • Warehouse supply lines</div><div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Transfer</th><th>SKU</th><th>Qty</th><th>Status</th><th>Picked</th><th>Received</th></tr></thead><tbody>'+supplyRows+'</tbody></table></div><div class="sl-notice sl-sop-boundary"><b>SOP:</b> Requested → Reviewed → Approved → Reserved → Picked → Received. <b>No In Transit state exists for requisitions.</b></div></div>';
    const ov=document.createElement('div');ov.className='sl-overlay';ov.innerHTML=body;document.body.appendChild(ov);const close=()=>ov.remove();document.getElementById('closeReqV16').onclick=close;ov.onclick=e=>{if(e.target===ov)close()};
    const review=document.getElementById('reviewReqV16');if(review)review.onclick=async()=>{review.disabled=true;try{await api16('/requisitions/'+encodeURIComponent(q.requisition_id)+'/decision',{method:'POST',body:JSON.stringify({stage:'review',decision:'REVIEW'})});toast('Reviewed and routed to Owner / Management');close();await loadReqs();await loadNotifications()}catch(e){toast(e.message,true);review.disabled=false}};
    const values=()=>{const out={};items.forEach(i=>{const el=ov.querySelector('[data-approval-item="'+CSS.escape(String(i.id))+'"]');out[i.id]=Number(el?.value||0)});return out};
    const submitDecision=async(decision,note)=>{const buttons=ov.querySelectorAll('#reqApproveFull,#reqApprovePartial,#reqReject');buttons.forEach(b=>b.disabled=true);try{const approved=decision==='REJECT'?{}:values();const result=await api16('/requisitions/'+encodeURIComponent(q.requisition_id)+'/decision',{method:'POST',body:JSON.stringify({stage:decisionStage,decision,approved_items:approved,note:String(note||'')})});toast(result.requisition?.status==='Approved'?'Approval complete — Warehouse notified':'Approval recorded');close();await loadReqs();await loadNotifications()}catch(e){toast(e.message,true);buttons.forEach(b=>b.disabled=false)}};
    document.getElementById('reqApproveFull')?.addEventListener('click',()=>submitDecision('APPROVE',''));
    document.getElementById('reqApprovePartial')?.addEventListener('click',async()=>{const vals=values();const invalid=items.some(i=>{const requested=Number(i.quantity_requested||0),available=Math.max(0,Number(i.availability?.available||0)),v=Number(vals[i.id]||0);return !Number.isFinite(v)||v<0||v>requested||v>available});if(invalid){toast('Partial quantities must be between zero and both the requested and live available quantities.',true);return}if(items.every(i=>Number(vals[i.id]||0)>=Number(i.quantity_requested||0)-0.00001)){toast('All requested quantities are fully available. Use APPROVE FULL.',true);return}if(Object.values(vals).every(v=>v<=0)){toast('Enter at least one positive partial quantity, or use REJECT.',true);return}const note=await slReasonDialog('Partial approval reason','Record why the requested quantity is being reduced.');if(note===null)return;await submitDecision('PARTIAL',note)});
    document.getElementById('reqReject')?.addEventListener('click',async()=>{const note=await slReasonDialog('Rejection reason','');if(note===null)return;await submitDecision('REJECT',note)});
    const runWarehouseAction=async(action,button)=>{if(button)button.disabled=true;try{const date=action==='reserve'?(document.getElementById('warehousePickupDate')?.value||''):'';if(action==='reserve'&&!date){toast('Select the branch pickup date before reserving stock.',true);if(button)button.disabled=false;return}const result=await api16('/requisitions/'+encodeURIComponent(q.requisition_id)+'/warehouse-action',{method:'POST',body:JSON.stringify({action,pickup_date:date})});toast(result.message||(action==='reserve'?'Stock reserved and pickup appointment confirmed':'Items picked and marked ready for pickup'));close();await loadReqs();await loadNotifications()}catch(e){toast(e.message,true);if(button)button.disabled=false}};const wh=document.getElementById('warehouseActionBtn');if(wh)wh.onclick=()=>runWarehouseAction(canReserve?'reserve':'pick',wh);const directPick=document.getElementById('warehouseDirectPickBtn');if(directPick)directPick.onclick=()=>runWarehouseAction('pick',directPick);
    const rec=document.getElementById('receiveReqV16');if(rec)rec.onclick=async()=>{rec.disabled=true;try{const picked=(d.transfers||[]).filter(t=>t.status==='Picked');const receiptLines=[];for(const t of picked){const result=await slReceiveDialog(t.transfer_id,Number(t.dispatched_qty||t.quantity||0));if(result===null){rec.disabled=false;return}receiptLines.push({transfer_id:t.transfer_id,received_qty:result.qty,reason:result.note});}if(!receiptLines.length)throw new Error('No picked supply line is available for receipt.');const result=await api16('/requisitions/'+encodeURIComponent(q.requisition_id)+'/receive',{method:'POST',body:JSON.stringify({receipts:receiptLines})});toast(result.requisition?.status==='Received'?'Branch receipt completed and Central / Owner / Management notified':'Branch receipt recorded');close();await loadReqs();await loadNotifications()}catch(e){toast(e.message,true);rec.disabled=false}};
  }catch(e){toast(e.message,true)}
}

function users(){
    const roleOptions='<option value="stockland_management">Management</option><option value="stockland_branch_manager">Branch Manager</option><option value="stockland_sales_employee">Sales Employee</option><option value="stockland_warehouse_manager">Warehouse Manager</option><option value="stockland_warehouse_employee">Warehouse Employee</option><option value="stockland_auditor">Auditor</option>'+(state.boot.user.role==='owner'?'<option value="stockland_owner">Owner</option>':'');
    const locs=state.boot.locations.filter(l=>l.location_type==='Branch'||l.location_type==='Warehouse');
    const canUsers=!!state.boot.user.capabilities?.manage_users;
    const skeleton='<div class="sl-skeleton"><div class="sl-skeleton-line w40"></div><div class="sl-skeleton-line w85"></div><div class="sl-skeleton-line w60"></div></div>';
    return title('Users & Organization','One frontend access center for users, roles, branches and responsibility scope. WordPress remains the technical host, not the day-to-day ERP access console.','<button class="sl-btn" id="refreshUsers">Refresh</button>')+
      (canUsers?'<div class="sl-status-banner"><div><strong>System Access</strong><br><small>Select a user, assign the ERP role and authorized location, then save. No second password or approval layer is required.</small></div><span class="sl-pill ok">ADMINISTRABLE</span></div>':'')+
      (canUsers?'<div class="sl-two"><div class="sl-panel"><div class="sl-block-head"><h3>Create individual ERP user</h3><small>Creates the WordPress account and ERP profile in one controlled action.</small></div><form id="userForm" class="sl-form"><div class="sl-field"><label>Username</label><input name="user_login" required></div><div class="sl-field"><label>Display name</label><input name="display_name"></div><div class="sl-field"><label>Email</label><input name="email" type="email"></div><div class="sl-field"><label>Password</label><input name="password" type="password" required minlength="10"></div><div class="sl-field"><label>ERP role</label><select name="role">'+roleOptions+'</select></div><div class="sl-field full"><label>Authorized locations <small>(select one or more; first selected is primary)</small></label><select name="location_ids[]" multiple size="5">'+locs.map(l=>'<option value="'+l.id+'">'+esc(l.location_name)+' — '+esc(l.location_type)+'</option>').join('')+'</select></div><div class="sl-field full"><button class="sl-btn primary">CREATE ERP USER</button></div></form></div>'+((state.boot.user.capabilities||{}).manage_locations?'<div class="sl-panel"><div class="sl-block-head"><h3>Branch & Location Registry</h3><small>Open, rename or deactivate operational locations from the ERP frontend.</small></div><form id="locationForm" class="sl-form"><div class="sl-field"><label>Name</label><input name="location_name" required></div><div class="sl-field"><label>Type</label><select name="location_type"><option>Branch</option><option>Sales channel</option></select></div><div class="sl-field"><label>Branch / Location Code</label><input name="branch_code" placeholder="Auto for branches (e.g. BR007)"></div><div class="sl-field full"><label>Description</label><input name="role_description"></div><div class="sl-field full"><button class="sl-btn gold">ADD LOCATION</button></div></form><div id="locationsRegistry">'+skeleton+'</div></div>':'')+'</div>':'')+
      (canUsers?'<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">SYSTEM ACCESS</div><h3>Assign existing WordPress accounts</h3><small>Choose the ERP role and branch/location inside the ERP. Native WordPress roles are preserved.</small></div></div><div id="availableWpUsers">'+skeleton+'</div></div>':'')+
      '<div class="sl-panel"><div class="sl-block-head"><h3>Active ERP users</h3><small>Role, location scope and active status are controlled by the ERP authorization layer.</small></div><div id="usersTable">'+skeleton+'</div></div>';
}
function hrSchemaPendingPage(){
return title('HR & Payroll','This module is held behind a separate schema approval gate.','')+
'<div class="sl-panel" data-hr-schema-pending-page="1" role="status"><div class="sl-eyebrow">OWNER APPROVAL REQUIRED</div><h3>HR & Payroll lifecycle is not enabled</h3><p>The active Owner must review and approve the exact HR DDL fingerprint before the 37 lifecycle tables or changes to existing HR columns can be applied.</p><p>Core, POS, Inventory and Finance remain available on the verified 49-key operational baseline.</p><p>Payroll finalization remains disabled until qualified Ethiopian statutory rules are approved.</p></div>';
}
function hr(){if(!state.hrLifecycleLoaded){return title('HR & People Operations','Loading the full employee lifecycle, legal rule registry and payroll workflow…','')+'<div id="hrPage"><div class="sl-panel"><div class="sl-skeleton"><div class="sl-skeleton-line w85"></div><div class="sl-skeleton-line w60"></div><div class="sl-skeleton-line w40"></div></div></div></div>';}return hrlcLifecycleLists(state.hrLifecycle||{})+hrlcPayrollCyclePanel(state.hrLifecycle||{});}
/* Full Stockland HRIS/Payroll work area. Accessible native forms; all decisions are server-authorized. */
function hrlcEsc(v){return esc(v==null?'':String(v))}
function hrlcStatus(v){const s=String(v||'DRAFT');const cl=/APPROVED|ACTIVE|PAID|COMPLETE|CONFIRMED/.test(s)?'ok':/REJECT|FAILED|BLOCKED/.test(s)?'bad':'warn';return '<span class="sl-pill '+cl+'">'+hrlcEsc(s.replace(/_/g,' '))+'</span>'}
function hrlcField(f){const n=f[0],l=f[1],t=f[2]||'text',v=f[3]||'',req=f[4]?' required':'',opts=f[5];let c='';if(t==='textarea'||t==='json')c='<textarea name="'+n+'" rows="'+(t==='json'?4:2)+'"'+req+'>'+hrlcEsc(v)+'</textarea>';else if(t==='select')c='<select name="'+n+'"'+req+'>'+opts.map(o=>'<option value="'+hrlcEsc(o[0])+'">'+hrlcEsc(o[1])+'</option>').join('')+'</select>';else c='<input name="'+n+'" type="'+(t==='money'?'number':t)+'"'+(t==='money'?' step="0.01" min="0"':'')+' value="'+hrlcEsc(v)+'"'+req+'>';return '<div class="sl-field"><label>'+hrlcEsc(l)+(req?' *':'')+'</label>'+c+'</div>'}
function hrlcForm(slug,title,fields,submit,wide){return '<details class="sl-hrlc-form"><summary><b>'+hrlcEsc(title)+'</b><span class="sl-pill info">Draft workflow</span></summary><form class="sl-form sl-hrlc-grid" data-hrlc-form data-route="'+slug+'">'+fields.map(hrlcField).join('')+(slug==='compensation'?'<div class="sl-field full"><button type="button" class="sl-btn" id="hrlcAllowancePreview">Preview legal allowance split</button><div id="hrlcAllowancePreviewResult" class="sl-sub" aria-live="polite">Preview uses only approved effective-dated rules. Unknown categories remain fully taxable.</div></div>':'')+'<div class="sl-field full"><button class="sl-btn primary" type="submit">'+hrlcEsc(submit||'Save draft')+'</button></div><p class="sl-sub full">Saved records are auditable. Approval is a separate, permission-checked action. Legal-rule changes never take effect until reviewed and approved.</p></form></details>'}
function classifyERPHealthScope(data){
 const d=data||{},q=d.diagnostics||{},checks=d.checks||{},failed=Array.isArray(d.failed_checks)?d.failed_checks:Object.keys(checks).filter(k=>checks[k]===false&&k!=='ok'&&k!=='live_ready');
 const ready=!!checks.live_ready,quickOnly=q.health_mode==='quick',setupRequired=q.health_mode==='setup';
 const reviewOnly=new Set(['payroll_compensation_classification','schema_contract_approved','release_approval']);
 const reviewOnlyFailure=failed.length>0&&failed.every(k=>reviewOnly.has(k))&&!quickOnly&&!setupRequired;
 const names={payroll_compensation_classification:'payroll compensation classification',schema_contract_approved:'the schema contract',release_approval:'final release authorization'};
 const affected=failed.map(k=>names[k]||k).join(', ');
 if(ready)return {kind:'ready',badge:'is-ready',label:'LIVE READY',bannerText:'All mandatory production checks passed.'};
 if(setupRequired)return {kind:'setup',badge:'is-not-ready',label:'SETUP REPAIR REQUIRED',bannerText:'The ERP is showing the exact schema gate that must be repaired before business controllers can register. Use the protected setup action; this check did not change business data.'};
 if(quickOnly)return {kind:'quick',badge:'is-not-ready',label:'QUICK HEALTH ONLY',bannerText:'Quick checks completed; deep integrity status is UNVERIFIED because the full scan was deferred.'};
 if(reviewOnlyFailure)return {kind:'scoped-review',badge:'is-operational-warning',label:'OPERATIONAL • TARGETED REVIEW REQUIRED',bannerText:'Owner/HR review remains required for '+affected+'. This review-only state does not disable existing authorized POS, inventory, warehouse, finance or other approved workflows. Payroll, schema migration and final release are not certified by this status.'};
 return {kind:'blocked',badge:'is-not-ready',label:'NOT LIVE READY',bannerText:'Resolve the failed checks shown below before relying on the affected workflows.'};
}
function hrlcTable(entity,rows,cols,opts){opts=opts||{};if(!rows||!rows.length)return '<div class="sl-empty">No records yet. Create a draft using the form above.</div>';return '<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr>'+cols.map(c=>'<th>'+hrlcEsc(c[1])+'</th>').join('')+'<th>Workflow</th></tr></thead><tbody>'+rows.map(r=>'<tr>'+cols.map(c=>'<td>'+ (c[2]==='status'?hrlcStatus(r[c[0]]):c[2]==='money'?money(r[c[0]]||0):hrlcEsc(r[c[0]]??''))+'</td>').join('')+'<td>'+hrlcStatus(r[opts.statusKey||'status'])+hrlcActions(entity,r,opts)+'</td></tr>').join('')+'</tbody></table></div>'}
function hrlcActions(entity,r,opts){opts=opts||{};const id=Number(r.id||0),s=String(r[opts.statusKey||'status']||r.review_status||r.approval_status||'DRAFT').toUpperCase(),c=state.boot.user.capabilities||{},role=String(state.boot.user.role||'').toLowerCase(),owner=role==='owner';let out='';
if(entity==='employees'&&s==='PENDING'&&owner)out+='<button type="button" class="sl-btn sl-btn-sm primary" data-hrlc-hire-activate="'+id+'">Activate verified hire</button>';
if(entity==='applications'){if(s==='PENDING'&&c.hr_manage&&!owner)out+='<button type="button" class="sl-btn sl-btn-sm" data-hrlc-decision data-entity="applications" data-id="'+id+'" data-decision="SUBMIT">Submit for selection review</button>';if(s==='SUBMITTED'&&owner)out+='<button type="button" class="sl-btn sl-btn-sm primary" data-hrlc-decision data-entity="applications" data-id="'+id+'" data-decision="APPROVE">Approve selection</button><button type="button" class="sl-btn sl-btn-sm" data-hrlc-decision data-entity="applications" data-id="'+id+'" data-decision="REJECT">Reject</button>';if(s==='APPROVED'&&owner)out+='<button type="button" class="sl-btn sl-btn-sm primary" data-hrlc-select-candidate="'+id+'">Create pending hire</button>';}
if(entity==='contracts'&&s==='DRAFT'&&c.hr_manage)out+='<button type="button" class="sl-btn sl-btn-sm" data-hrlc-contract-sign="'+id+'">Register signed contract</button>';
if(entity==='contracts'&&s==='SUBMITTED'&&c.payroll_approve)out+='<button type="button" class="sl-btn sl-btn-sm primary" data-hrlc-decision data-entity="contracts" data-id="'+id+'" data-decision="APPROVE">Approve contract</button><button type="button" class="sl-btn sl-btn-sm" data-hrlc-decision data-entity="contracts" data-id="'+id+'" data-decision="REJECT">Reject</button>';/* legacy mutable payroll controls removed; use frozen payroll runs below */
if(entity==='rules'&&c.payroll_approve&&s==='DRAFT')out+='<button type="button" class="sl-btn sl-btn-sm primary" data-hrlc-rule-approve="'+id+'">Approve rule</button>';
if(entity==='compensation-components'&&c.payroll_approve&&(s==='DRAFT'||s==='REVIEW_REQUIRED'||(s==='APPROVED'&&(Number(r.created_by)<=0||Number(r.approved_by)<=0||Number(r.approved_by)===Number(r.created_by)||!r.approved_at||r.approved_at==='0000-00-00 00:00:00'))))out+='<button type="button" class="sl-btn sl-btn-sm primary" data-hrlc-comp-approve="'+id+'">'+(s==='APPROVED'?'Re-review legacy approval':'Approve change')+'</button>';
if(!['rules','compensation-components','employees','payroll','applications','interviews','exits','contracts'].includes(entity)){if(['DRAFT','CALCULATED','PENDING'].includes(s)&&c.hr_manage)out+='<button type="button" class="sl-btn sl-btn-sm" data-hrlc-decision data-entity="'+entity+'" data-id="'+id+'" data-decision="SUBMIT">Submit</button>';if(['SUBMITTED','PENDING_REVIEW'].includes(s)&&c.payroll_approve)out+='<button type="button" class="sl-btn sl-btn-sm primary" data-hrlc-decision data-entity="'+entity+'" data-id="'+id+'" data-decision="APPROVE">Approve</button><button type="button" class="sl-btn sl-btn-sm" data-hrlc-decision data-entity="'+entity+'" data-id="'+id+'" data-decision="REJECT">Reject</button>';}
if(entity==='onboarding'&&s!=='COMPLETE')out+='<button type="button" class="sl-btn sl-btn-sm" data-hrlc-decision data-entity="'+entity+'" data-id="'+id+'" data-decision="COMPLETE">Complete task</button>';
if(entity==='exits'&&s==='CALCULATED'&&c.payroll_approve)out+='<button type="button" class="sl-btn sl-btn-sm primary" data-hrlc-exit-approve="'+id+'">Approve settlement</button>';if(entity==='exits'&&s==='APPROVED'&&c.payroll_approve)out+='<button type="button" class="sl-btn sl-btn-sm primary" data-hrlc-exit-pay="'+id+'">Record payment + post</button>';
return out}
function hrlcSection(title,desc,body){const k=String(title||''),id='hrlc-section-'+k.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,''),saved=Array.isArray(state.hrlcOpenSections)?state.hrlcOpenSections:[],op=saved.includes(k)?' open':'',expanded=op?'true':'false';return '<details id="'+id+'" data-hrlc-section="'+hrlcEsc(k)+'" class="sl-panel sl-hrlc-panel sl-hrlc-section"'+op+'><summary id="'+id+'-summary" class="sl-hrlc-section-summary" aria-controls="'+id+'-body" aria-expanded="'+expanded+'"><span class="sl-hrlc-section-heading"><span class="sl-hrlc-section-titles"><span class="sl-hrlc-section-title" role="heading" aria-level="3">'+hrlcEsc(k)+'</span><small>'+hrlcEsc(desc)+'</small></span></span><span class="sl-hrlc-section-toggle" aria-hidden="true"></span></summary><div id="'+id+'-body" aria-labelledby="'+id+'-summary" class="sl-hrlc-section-body">'+body+'</div></details>'}
function hrlcSopLibrary(d){const data=d.sops||{},items=Array.isArray(data.items)?data.items:[],cards=items.map(x=>{const words=[x.id,x.title,x.owner,x.summary].join(' ');const size=Number(x.bytes||0);return '<article class="sl-panel" data-sop-card data-sop-searchable="'+hrlcEsc(words.toLowerCase())+'"><div class="sl-eyebrow">'+hrlcEsc(x.id)+'</div><h4>'+hrlcEsc(x.title)+'</h4><p class="sl-sub">'+hrlcEsc(x.summary||'ERP operating procedure')+'</p><div class="sl-statline"><div class="sl-stat"><b>'+hrlcEsc(x.version||'v1.0-DRAFT')+'</b><span>Document version</span></div><div class="sl-stat"><b>'+hrlcEsc(x.status||'DRAFT_NOT_APPROVED').replace(/_/g,' ')+'</b><span>Approval state</span></div></div><p class="sl-sub">Owner: '+hrlcEsc(x.owner||'Process owner')+' · '+(size?Math.max(1,Math.round(size/1024))+' KB':'Size unavailable')+'</p><button type="button" class="sl-btn sl-btn-sm primary" data-sop-download="'+hrlcEsc(x.id)+'" '+(x.downloadable?'':'disabled')+'>'+ (x.downloadable?'Download DOCX':'Document unavailable')+'</button></article>'}).join('');const body='<div class="sl-notice sl-notice-soft"><b>Controlled business procedures.</b> These 24 documents are review drafts. They are not effective policies until approved by the designated Stockland authority. This catalogue does not contain employee contracts or private personnel files.</div>'+(data.error?'<div class="sl-notice sl-danger-block">SOP library could not be loaded: '+hrlcEsc(data.error)+'</div>':'')+'<div class="sl-hrlc-toolbar"><label class="sl-hrlc-search"><span class="sl-hrlc-search-icon" aria-hidden="true">⌕</span><input id="hrlcSopSearch" type="search" aria-label="Search ERP procedures" placeholder="Search procedure, code or owner"></label><span class="sl-hrlc-module-count" id="hrlcSopCount">'+items.length+' documents</span></div><div class="sl-grid" id="hrlcSopGrid">'+(cards||'<div class="sl-empty">No SOP documents are available to this role.</div>')+'</div>';return hrlcSection('ERP Policy & SOP Library','24 controlled operating procedures for inventory, sales, finance, HR, payroll and system administration.',body)}
async function downloadHRLSop(code,button){if(!/^STK-ERP-SOP-\d{3}$/.test(String(code||'')))throw new Error('Invalid SOP document reference.');const base=A.api_v16||(A.api||'').replace(/\/v1$/,'/v16');const url=resolveTrustedApiUrl('/hr/sops/'+encodeURIComponent(code)+'/download',base);const response=await fetch(url,{method:'GET',headers:{'X-WP-Nonce':A.nonce},credentials:'same-origin',cache:'no-store'});if(!response.ok){let payload={};try{payload=await response.json()}catch(_e){}throw new Error(payload.message||payload.code||('Document download failed ('+response.status+').'))}const blob=await response.blob();if(!blob||!blob.size)throw new Error('The server returned an empty document.');let filename=code+'.docx';const disposition=response.headers.get('Content-Disposition')||'';const match=disposition.match(/filename\*?=(?:UTF-8''|\")?([^;\"]+)/i);if(match){try{filename=decodeURIComponent(match[1].trim().replace(/^\"|\"$/g,''))}catch(_e){filename=match[1].trim().replace(/^\"|\"$/g,'')}}const objectUrl=URL.createObjectURL(blob),link=document.createElement('a');link.href=objectUrl;link.download=filename;link.style.display='none';document.body.appendChild(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(objectUrl),1500);toast('Document downloaded; the server recorded the audit event.');}
function hrlcLifecycleLists(d){const l=d.lists||{};let out='';out+=hrlcSection('Employee master','All new hires must come from an approved recruitment decision. Direct employee creation is disabled; pending hires are activated only after signed contract, verified documents, completed onboarding and approved compensation.',hrlcTable('employees',d.employees||[],[['employee_id','Employee code'],['full_name','Full name'],['position_title','Position'],['branch_location_id','Branch'],['status','State','status']]));
out+=hrlcSection('1 · Workforce planning','Plan headcount and budgets before opening positions.',hrlcForm('workforce-plans','Create manpower plan',[['plan_code','Plan code','text','',true],['fiscal_year','Fiscal year','text','',true],['location_id','Location ID (blank = company)','number',''],['department','Department','text','',true],['planned_headcount','Planned headcount','number','0',true],['annual_salary_budget','Annual salary budget (ETB)','money','0',true],['other_cost_budget','Other employee cost budget (ETB)','money','0'],['justification','Business justification','textarea','',true]])+hrlcForm('positions','Create position',[['position_code','Position code','text','',true],['title','Position title','text','',true],['department','Department','text','',true],['grade_code','Grade/band','text',''],['location_id','Location ID','number',''],['headcount_slots','Approved headcount slots','number','1',true],['annual_salary_min','Annual salary minimum (ETB)','money','0'],['annual_salary_max','Annual salary maximum (ETB)','money','0'],['workforce_plan_id','Workforce plan ID','number','']])+hrlcTable('workforce-plans',l['workforce-plans'],[['plan_code','Plan'],['fiscal_year','Year'],['department','Department'],['planned_headcount','Headcount'],['annual_salary_budget','Salary budget','money'],['status','Status','status']])+hrlcTable('positions',l.positions,[['position_code','Code'],['title','Position'],['department','Department'],['grade_code','Grade'],['location_id','Location'],['status','State','status']]));
out+=hrlcSection('2 · Recruitment and selection','Document the vacancy, applicants, screening, interviews, evidence and human decision.',hrlcForm('requisitions','Open approved vacancy',[['requisition_code','Requisition code','text','',true],['position_id','Position ID','number','',true],['workforce_plan_id','Plan ID','number',''],['location_id','Location ID','number',''],['openings','Openings','number','1',true],['employment_type','Employment type','select','',true,[['FULL_TIME','Full-time'],['PART_TIME','Part-time'],['FIXED_TERM','Fixed-term'],['CONTRACT','Contract']]],['target_start_date','Target start date','date',''],['justification','Justification','textarea','',true]])+hrlcForm('candidates','Register candidate',[['candidate_code','Candidate code','text','',true],['full_name','Full name','text','',true],['email','Email','email',''],['phone','Phone','tel',''],['source','Candidate source','text',''],['consent_at','Consent date','date',''],['notes','Screening notes (no sensitive data)','textarea','']])+hrlcForm('applications','Link candidate to vacancy',[['requisition_id','Requisition ID','number','',true],['candidate_id','Candidate ID','number','',true],['stage','Application stage','select','APPLIED',true,[['APPLIED','Applied'],['SCREENING','Screening'],['INTERVIEW','Interview'],['REFERENCE_CHECK','Reference check'],['OFFER','Offer'],['SELECTED','Selected'],['DECLINED','Declined']]],['screening_score','Screening score','number',''],['decision_reason','Decision rationale','textarea','']])+hrlcForm('interviews','Record interview panel and scorecard',[['application_id','Application ID','number','',true],['interview_round','Interview round','text','INITIAL',true],['scheduled_at','Interview date/time','datetime-local',''],['panel_json','Panel JSON','json','[]'],['scorecard_json','Scorecard JSON','json','{}'],['total_score','Total score','number',''],['recommendation','Recommendation','select','PENDING',true,[['PENDING','Pending'],['RECOMMEND','Recommend'],['HOLD','Hold'],['NOT_RECOMMEND','Do not recommend']]],['notes','Decision rationale','textarea','']])+hrlcTable('requisitions',l.requisitions,[['requisition_code','Vacancy'],['position_id','Position ID'],['location_id','Location'],['openings','Openings'],['status','State','status']])+hrlcTable('candidates',l.candidates,[['candidate_code','Candidate'],['full_name','Name'],['source','Source'],['status','State','status']])+hrlcTable('applications',l.applications,[['requisition_id','Vacancy ID'],['candidate_id','Candidate ID'],['stage','Stage'],['decision','Decision']],{statusKey:'decision'})+hrlcTable('interviews',l.interviews,[['application_id','Application ID'],['interview_round','Round'],['total_score','Score'],['recommendation','Recommendation']],{statusKey:'recommendation'}));
out+=hrlcSection('3 · Contracts, personnel documents and onboarding','Metadata references only; private-document storage integration remains a required production gate.',hrlcForm('contracts','Create employment contract',[['employee_id','Employee ID (after selected hire)','number','',true],['contract_code','Contract code','text','',true],['contract_type','Contract type','select','FULL_TIME',true,[['FULL_TIME','Full-time'],['PART_TIME','Part-time'],['FIXED_TERM','Fixed-term'],['PROBATION','Probation'],['CONTRACT','Contract']]],['start_date','Start date','date','',true],['end_date','End date','date',''],['probation_end','Probation end','date',''],['position_id','Position ID','number',''],['location_id','Branch/location ID','number',''],['terms_json','Approved terms JSON','json','{}'],['document_id','Private document metadata ID','number','']])+hrlcForm('documents','Register personnel document metadata',[['subject_type','Subject type','select','EMPLOYEE',true,[['EMPLOYEE','Employee'],['CANDIDATE','Candidate'],['CONTRACT','Contract']]],['subject_id','Subject ID','number','',true],['document_type','Document type','text','',true],['document_reference','Private vault reference (not a public URL)','text','',true],['document_sha256','Document SHA-256','text',''],['issued_on','Issued on','date',''],['expires_on','Expires on','date',''],['notes','Verification note','textarea','']])+hrlcForm('onboarding','Create onboarding task',[['employee_id','Employee ID','number','',true],['task_code','Task code','text','',true],['task_title','Task','text','',true],['responsible_role','Responsible role','select','MANAGEMENT',true,[['MANAGEMENT','Management'],['OWNER','Owner'],['EMPLOYEE','Employee'],['IT','IT/Systems']]],['due_date','Due date','date',''],['evidence_reference','Evidence reference','text','']])+hrlcTable('contracts',l.contracts,[['employee_id','Employee'],['contract_code','Contract'],['contract_type','Type'],['start_date','Start'],['signed_at','Signed at'],['document_id','Document ID'],['status','State','status']])+hrlcTable('documents',l.documents,[['subject_type','Subject'],['subject_id','Subject ID'],['document_type','Document'],['expires_on','Expiry'],['status','State','status']])+hrlcTable('onboarding',l.onboarding,[['employee_id','Employee'],['task_code','Task'],['task_title','Task'],['due_date','Due'],['status','State','status']]));
out+=hrlcSection('4 · Shifts, attendance and leave','Approved schedule/time data feeds payroll; manual changes are reasoned and audited.',hrlcForm('shifts','Create shift',[['shift_code','Shift code','text','',true],['shift_name','Shift name','text','',true],['location_id','Location ID','number',''],['start_time','Start time','time','',true],['end_time','End time','time','',true],['break_minutes','Break minutes','number','0'],['effective_from','Effective from','date','',true],['effective_to','Effective to','date',''],['rule_set_id','Approved overtime ruleset ID','number','']])+hrlcForm('attendance','Record attendance / correction',[['employee_id','Employee ID','number','',true],['location_id','Location ID','number',''],['shift_id','Shift ID','number',''],['work_date','Work date','date','',true],['check_in','Check-in','datetime-local',''],['check_out','Check-out','datetime-local',''],['ordinary_minutes','Ordinary minutes','number','0'],['overtime_minutes','Overtime minutes','number','0'],['absence_type','Absence type','text',''],['source','Source','select','MANUAL',true,[['MANUAL','Manual'],['DEVICE','Device import'],['CORRECTION','Correction']]],['correction_reason','Correction reason / evidence','textarea','']])+hrlcForm('leave-policies','Propose leave policy version',[['policy_code','Policy code','text','',true],['leave_type','Leave type','text','',true],['annual_entitlement_days','Annual entitlement (days)','number','0',true],['max_carry_days','Maximum carry-forward','number','0'],['min_notice_days','Minimum notice days','number','0'],['paid_status','Paid status','select','PAID',true,[['PAID','Paid'],['UNPAID','Unpaid'],['MIXED','Mixed by approved rule']]],['rule_set_id','Approved leave legal rule ID','number',''],['effective_from','Effective from','date','',true],['effective_to','Effective to','date','']])+hrlcForm('leave-requests','Submit leave request',[['employee_id','Employee ID','number','',true],['leave_type','Leave type','text','',true],['start_date','Start date','date','',true],['end_date','End date','date','',true],['requested_days','Days requested','number','',true],['reason','Reason','textarea',''],['evidence_reference','Evidence reference (if required)','text','']])+hrlcTable('shifts',l.shifts,[['shift_code','Shift'],['shift_name','Name'],['location_id','Location'],['effective_from','From'],['status','State','status']])+hrlcTable('attendance',l.attendance,[['employee_id','Employee'],['work_date','Date'],['ordinary_minutes','Minutes'],['overtime_minutes','OT minutes'],['status','State','status']])+hrlcForm('leave-balances','Record reviewed leave balance adjustment',[['employee_id','Employee ID','number','',true],['leave_type','Leave type','text','',true],['leave_year','Leave year','number',String(new Date().getFullYear()),true],['opening_days','Opening days','number','0'],['accrued_days','Accrued days','number','0'],['used_days','Used days','number','0'],['adjusted_days','Approved adjustment','number','0'],['closing_days','Reconciled closing balance','number','0',true]])+hrlcTable('leave-policies',l['leave-policies'],[['policy_code','Policy'],['leave_type','Type'],['annual_entitlement_days','Entitlement'],['effective_from','From'],['status','State','status']])+hrlcTable('leave-requests',l['leave-requests'],[['employee_id','Employee'],['leave_type','Type'],['start_date','Start'],['end_date','End'],['requested_days','Days'],['status','State','status']])+hrlcTable('leave-balances',l['leave-balances'],[['employee_id','Employee'],['leave_type','Type'],['leave_year','Year'],['closing_days','Balance']]));
out+=hrlcSection('5 · Compensation, increments, allowances and benefits','Effective-dated components are split only by an approved rule set; unknown categories are taxable by default.',hrlcForm('compensation','Prepare salary / compensation revision',[['employee_id','Employee ID','number','',true],['effective_from','Effective from','date',localDate(),true],['base_salary','Base salary ETB','money','0',true],['pensionable_salary','Pensionable salary ETB','money','0',true],['standard_deduction_total','Other deductions ETB','money','0'],['allowances','Allowance rows JSON [{category,label,amount}]','json','[]',true],['notes','Change reason / contract reference','textarea','',true]],'Preview and save draft')+(d.payroll_visible?hrlcTable('compensation-components',d.compensation||[],[['employee_code','Employee'],['full_name','Name'],['effective_from','Effective'],['base_salary','Base ETB','money'],['taxable_allowance_total','Taxable','money'],['exempt_allowance_total','Exempt','money'],['approval_status','Approval'] ],{statusKey:'approval_status'}):'<div class="sl-notice">Compensation details are restricted to Owner/Management with payroll-view permission.</div>')+hrlcTable('rules',d.rule_sets||[],[['rule_code','Rule'],['rule_type','Type'],['version_label','Version'],['effective_from','Effective'],['review_status','State','status'],['source_title','Legal source']],{statusKey:'review_status'}));
out+=hrlcSection('6 · Statutory rule register','Source-backed and effective-dated. Draft rules are not active; Maker cannot approve own rule.',hrlcForm('rules','Create statutory rule draft',[['rule_code','Rule code','text','',true],['rule_type','Rule type','select','PAYROLL',true,[['PAYROLL','PAYE / pension payroll'],['ALLOWANCE','Allowance classification'],['LEAVE','Leave entitlements'],['OVERTIME','Overtime'],['FINAL_SETTLEMENT','Final settlement']]],['version_label','Version label','text','',true],['jurisdiction','Jurisdiction','text','ETHIOPIA-FEDERAL',true],['effective_from','Effective from','date','',true],['effective_to','Effective to','date',''],['source_title','Official instrument title','text','',true],['source_url','Official HTTPS source URL','url','',true],['source_clause','Article/clause','text','',true],['source_publication_date','Publication date','date',''],['source_sha256','Official source document SHA-256','text','',true],['rules_json','Full rules JSON with approved test vectors','json','{}',true],['components','Allowance components JSON [{category,tax_treatment,exemption_cap,pensionable_treatment,source_clause}]','json','[]']],'Save rule as DRAFT')+'<div class="sl-notice">Never enter ETB 1,000 or any exemption cap merely from an example. The exact law, effective date, rule JSON and independent reviewer evidence must be reconciled first.</div>');
out+=hrlcSection('7 · Legacy payroll history','Legacy period mutations are retired in this candidate. Use the frozen payroll-run workflow below for calculation, approval, posting, payout results and reconciliation.','<div class="sl-notice">Legacy payroll history is preserved. New payroll run writes are handled only by the frozen-run API after the legal rule gate passes.</div>');
out+=hrlcSection('8 · Training, performance and employee changes','All history remains effective-dated and auditable.',hrlcForm('training','Record training',[['employee_id','Employee ID','number','',true],['training_code','Training code','text','',true],['title','Title','text','',true],['provider','Provider','text',''],['start_date','Start','date',''],['end_date','End','date',''],['status','State','select','PLANNED',true,[['PLANNED','Planned'],['IN_PROGRESS','In progress'],['COMPLETE','Complete'],['CANCELLED','Cancelled']]],['evidence_reference','Evidence reference','text','']])+hrlcForm('performance','Record performance review',[['employee_id','Employee ID','number','',true],['review_period','Review period','text','',true],['reviewer_id','Reviewer ID','number','',true],['goals_json','Goals JSON','json','[]'],['rating','Rating','number',''],['outcome','Outcome','select','DRAFT',true,[['DRAFT','Draft'],['IN_REVIEW','In review'],['COMPLETE','Complete']]],['notes','Review notes','textarea','']])+hrlcForm('adjustments','Prepare payroll adjustment',[['employee_id','Employee ID','number','',true],['source_period_id','Source payroll period ID','number',''],['target_period_key','Target period YYYY-MM','text','',true],['adjustment_type','Adjustment type','select','ARREARS',true,[['ARREARS','Arrears'],['CORRECTION','Correction'],['BONUS','Bonus'],['DEDUCTION','Deduction']]],['amount','Amount ETB','money','0',true],['taxable_amount','Taxable portion ETB','money','0',true],['pensionable_amount','Pensionable portion ETB','money','0'],['reason','Reason and evidence','textarea','',true]])+hrlcTable('training',l.training,[['employee_id','Employee'],['training_code','Code'],['title','Training'],['status','State','status']])+hrlcTable('performance',l.performance,[['employee_id','Employee'],['review_period','Period'],['rating','Rating'],['outcome','State','status']])+hrlcTable('adjustments',l.adjustments,[['employee_id','Employee'],['target_period_key','Period'],['adjustment_type','Type'],['amount','Amount','money'],['status','State','status']]));
out+=hrlcSection('9 · Exit and final settlement','Calculate only against an Owner-approved final-settlement rule and payroll rule version; approval/posting/payment remain separate.',hrlcForm('exits','Calculate final settlement draft',[['employee_id','Employee ID','number','',true],['exit_type','Exit type','select','RESIGNATION',true,[['RESIGNATION','Resignation'],['TERMINATION','Termination'],['RETIREMENT','Retirement'],['REDUNDANCY','Redundancy'],['CONTRACT_END','Contract end'],['OTHER','Other approved exit']]],['exit_date','Exit date','date','',true],['last_work_date','Last work date','date','',true],['reason','Approved exit reason / evidence','textarea','',true],['components','Settlement components JSON [{category,label,amount,legal_basis,source_reference}]','json','[]',true],['other_deductions','Other lawful deductions ETB','money','0']],'Calculate from approved rules')+hrlcTable('exits',l.exits,[['settlement_code','Settlement'],['employee_id','Employee'],['exit_type','Type'],['exit_date','Exit date'],['gross_total','Gross','money'],['net_total','Net','money'],['rule_version','Rule version'],['status','State','status']]));
out+=hrlcSopLibrary(d);
return title('HR & People Operations','Full employee lifecycle: workforce plan → recruit/select → contract/onboard → time/leave → compensation → statutory payroll → payment/GL → exit/final settlement. All branches share the same source-backed rule registry and audit chain.', '<button class="sl-btn" id="refreshHR">Refresh HR & Payroll</button>')+'<div id="hrPage"><div class="sl-hrlc-toolbar"><label class="sl-hrlc-search"><span class="sl-hrlc-search-icon" aria-hidden="true">⌕</span><input id="hrlcSectionSearch" type="search" aria-label="Search HR functions" placeholder="Search HR modules or descriptions" value="'+hrlcEsc(state.hrlcSearchTerm||'')+'"></label><span data-hrlc-visible-count class="sl-hrlc-module-count"></span></div><div data-hrlc-search-empty class="sl-hrlc-search-empty" hidden>No matching HR functions. Try another search.</div><div class="sl-grid">'+card('Employees',(d.employees||[]).length,'Employee master')+card('Open payroll periods',(d.payroll||[]).filter(x=>!['PAID','CLOSED'].includes(x.status)).length,'Draft/review/approval')+card('Approved rule versions',(d.rule_sets||[]).filter(x=>x.review_status==='APPROVED').length,'Effective-dated rules')+card('Lifecycle modules','10+','Recruitment through final settlement')+'</div><div class="sl-hrlc-flow"><span>Plan & budget</span><span>Recruit & select</span><span>Contract & onboard</span><span>Attendance & leave</span><span>Compensation</span><span>Payroll + GL</span><span>Final settlement</span></div>'+out+'</div>'}
function loadHRLifecycle(){const p=document.getElementById('hrPage');if(!p||state.hrLifecycleLoading)return;state.hrLifecycleLoading=true;api16('/hr/lifecycle/bootstrap').then(async d=>{const tasks=[['cycleRuns','/hr/payroll-runs'],['cycleBatches','/hr/payroll-payment-batches'],['cyclePayees','/hr/individual-payees'],['cycleObligations','/hr/individual-payee-obligations'],['cycleResources','/hr/resources'],['cycleReimbursements','/hr/employee-reimbursements'],['cycleRemittances','/hr/statutory-remittances']];d.cycleAccessErrors=[];await Promise.all(tasks.map(async t=>{try{d[t[0]]=await api16(t[1])}catch(e){d[t[0]]=[];d.cycleAccessErrors.push(t[1]+': '+String(e.message||e))}}));try{d.socialCycle=await api16('/hr/management-social')}catch(e){d.socialCycle={campaigns:[],snapshots:[],warning:'Social workspace unavailable to this role: '+String(e.message||e)}};try{d.sops=await api16('/hr/sops')}catch(e){d.sops={items:[],error:String(e.message||e)}};state.hrLifecycle=d;state.hrLifecycleLoaded=true;render()}).catch(e=>{const el=document.getElementById('hrPage');if(el)el.insertAdjacentHTML('afterbegin','<div class="sl-notice sl-danger-block">HR & Payroll unavailable: '+esc(e.message)+'</div>')}).finally(()=>{state.hrLifecycleLoading=false})}
function bindHRLCSectionState(){const root=document.getElementById('hrPage');if(!root)return;const openSections=Array.from(root.querySelectorAll('[data-hrlc-section][open]'));if(openSections.length>1){const saved=Array.isArray(state.hrlcOpenSections)?state.hrlcOpenSections:[],keep=openSections.find(section=>saved.includes(section.getAttribute('data-hrlc-section')))||openSections[0];openSections.forEach(section=>{if(section!==keep)section.open=false;});state.hrlcOpenSections=[keep.getAttribute('data-hrlc-section')];}root.querySelectorAll('[data-hrlc-section]').forEach(s=>{const summary=s.querySelector(':scope > summary');if(summary)summary.setAttribute('aria-expanded',String(!!s.open));if(s.__hrlcSectionBound)return;s.__hrlcSectionBound=true;s.addEventListener('toggle',()=>{const key=s.getAttribute('data-hrlc-section'),summaryEl=s.querySelector(':scope > summary');if(summaryEl)summaryEl.setAttribute('aria-expanded',String(!!s.open));if(s.open){state.hrlcOpenSections=[key];root.querySelectorAll('[data-hrlc-section][open]').forEach(other=>{if(other!==s)other.open=false;});}else{state.hrlcOpenSections=(Array.isArray(state.hrlcOpenSections)?state.hrlcOpenSections:[]).filter(savedKey=>savedKey!==key);}});});}
function bindHRLCSectionSearch(){const root=document.getElementById('hrPage');if(!root)return;const input=root.querySelector('#hrlcSectionSearch'),empty=root.querySelector('[data-hrlc-search-empty]'),countNode=root.querySelector('[data-hrlc-visible-count]');if(!input||input.__hrlcSearchBound)return;input.__hrlcSearchBound=true;const apply=()=>{const q=String(input.value||'').trim().toLowerCase();state.hrlcSearchTerm=input.value||'';let visible=0;root.querySelectorAll('[data-hrlc-section]').forEach(section=>{const heading=section.querySelector('.sl-hrlc-section-titles'),haystack=heading?heading.textContent.toLowerCase():section.getAttribute('data-hrlc-section').toLowerCase(),show=!q||haystack.includes(q);section.hidden=!show;if(show)visible++;else if(section.open)section.open=false;});if(empty)empty.hidden=visible>0;if(countNode)countNode.textContent=q?visible+' matching sections':visible+' sections';};input.addEventListener('input',apply);apply();}
function bindHRLifecycleForms(){bindHRLCSectionState();bindHRLCSectionSearch();const sopSearch=document.getElementById('hrlcSopSearch');if(sopSearch&&!sopSearch.__sopBound){sopSearch.__sopBound=true;sopSearch.addEventListener('input',()=>{const q=String(sopSearch.value||'').trim().toLowerCase();let visible=0;document.querySelectorAll('[data-sop-card]').forEach(card=>{const show=!q||String(card.dataset.sopSearchable||'').includes(q);card.hidden=!show;if(show)visible++});const count=document.getElementById('hrlcSopCount');if(count)count.textContent=visible+' matching documents';});}document.querySelectorAll('[data-sop-download]').forEach(button=>{if(button.__sopBound)return;button.__sopBound=true;button.addEventListener('click',async()=>{const code=button.dataset.sopDownload;button.disabled=true;try{await downloadHRLSop(code,button)}catch(e){toast(e.message||'Document download failed.',true)}finally{button.disabled=false}})});document.querySelectorAll('[data-hrlc-form]').forEach(f=>{if(f.__hrlcBound)return;f.__hrlcBound=true;f.addEventListener('submit',async e=>{e.preventDefault();if(!f.reportValidity())return;const b=f.querySelector('[type=submit]');if(b)b.disabled=true;try{const d=Object.fromEntries(new FormData(f).entries());const route=f.dataset.route;for(const k of ['employee_id','location_id','workforce_plan_id','position_id','openings','planned_headcount','headcount_slots','requisition_id','candidate_id','application_id','subject_id','document_id','break_minutes','shift_id','ordinary_minutes','overtime_minutes','annual_entitlement_days','max_carry_days','min_notice_days','requested_days','leave_year','reviewer_id','source_period_id','rating','rule_set_id'])if(d[k]!==''&&d[k]!=null)d[k]=Number(d[k]);if(['allowances','rules_json','components','terms_json','panel_json','scorecard_json','goals_json'].includes('never')){}if(['allowances','components'].includes('x')){}if(route==='employees'){await api16('/hr/employees',{method:'POST',body:JSON.stringify(d)})}else if(route==='rules'){d.rules_json=JSON.parse(d.rules_json||'{}');d.components=JSON.parse(d.components||'[]');await api16('/hr/lifecycle/rules',{method:'POST',body:JSON.stringify(d)})}else if(route==='compensation'){d.employee_id=Number(d.employee_id);d.allowances=JSON.parse(d.allowances||'[]');await api16('/hr/lifecycle/compensation',{method:'POST',body:JSON.stringify(d)})}else if(route==='exits'){d.employee_id=Number(d.employee_id);d.components=JSON.parse(d.components||'[]');await api16('/hr/lifecycle/exits/calculate',{method:'POST',body:JSON.stringify(d)})}else if(route==='payroll'){if(!/^\d{4}-(0[1-9]|1[0-2])$/.test(d.period_key||''))throw new Error('Period must be YYYY-MM.');const m=d.period_key.split('-').map(Number);d.start_date=m[0]+'-'+String(m[1]).padStart(2,'0')+'-01';d.end_date=m[0]+'-'+String(m[1]).padStart(2,'0')+'-'+String(new Date(m[0],m[1],0).getDate()).padStart(2,'0');await api16('/hr/payroll',{method:'POST',body:JSON.stringify(d)})}else await api16('/hr/lifecycle/'+encodeURIComponent(route),{method:'POST',body:JSON.stringify(d)});toast('Saved. Audit event recorded; next-stage approval remains separate.');state.hrLifecycleLoaded=false;await loadHRLifecycle()}catch(err){toast(err.message||'Save failed',true)}finally{if(b)b.disabled=false}})});
document.querySelectorAll('[data-hrlc-decision]').forEach(b=>{if(b.__hrlcBound)return;b.__hrlcBound=true;b.onclick=async()=>{const decision=b.dataset.decision;if(decision==='APPROVE'&&!confirm('Confirm independent approval? The action is audited and may authorize a workflow transition.'))return;b.disabled=true;try{await api16('/hr/lifecycle/'+encodeURIComponent(b.dataset.entity)+'/'+encodeURIComponent(b.dataset.id)+'/decision',{method:'POST',body:JSON.stringify({decision,note:'Reviewed source evidence and supporting documentation.'})});toast('Workflow transition saved');state.hrLifecycleLoaded=false;await loadHRLifecycle()}catch(e){toast(e.message,true)}finally{b.disabled=false}}});
document.querySelectorAll('[data-hrlc-rule-approve]').forEach(b=>{if(b.__hrlcBound)return;b.__hrlcBound=true;b.onclick=async()=>{const note=prompt('Independent legal/payroll reviewer, official effective-date reconciliation and test-vector sign-off:');if(!note||note.trim().length<30){toast('Independent review evidence is mandatory.',true);return}b.disabled=true;try{await api16('/hr/lifecycle/rules/'+b.dataset.hrlcRuleApprove+'/approve',{method:'POST',body:JSON.stringify({independent_review_note:note})});toast('Rule approved. Its effective date and source hash are recorded.');state.hrLifecycleLoaded=false;await loadHRLifecycle()}catch(e){toast(e.message,true)}finally{b.disabled=false}}});

document.querySelectorAll('[data-hrlc-select-candidate]').forEach(b=>{if(b.__hrlcBound)return;b.__hrlcBound=true;b.onclick=async()=>{const rationale=prompt('Document the human selection rationale (minimum 20 characters).');if(!rationale||rationale.trim().length<20){toast('Selection rationale is required.',true);return}b.disabled=true;try{await api16('/hr/lifecycle/applications/'+b.dataset.hrlcSelectCandidate+'/select',{method:'POST',body:JSON.stringify({selection_rationale:rationale})});toast('Pending hire, contract draft and onboarding checklist created.');state.hrLifecycleLoaded=false;await loadHRLifecycle()}catch(e){toast(e.message,true)}finally{b.disabled=false}}});
document.querySelectorAll('[data-hrlc-contract-sign]').forEach(b=>{if(b.__hrlcBound)return;b.__hrlcBound=true;b.onclick=async()=>{const doc=prompt('Enter the ID of the APPROVED private-vault EMPLOYMENT_CONTRACT document linked to this contract.');if(!doc||!/^\\d+$/.test(doc))return;const signed=prompt('Contract signature date (YYYY-MM-DD):',localDate());if(!signed||!/^\\d{4}-\\d{2}-\\d{2}$/.test(signed))return;b.disabled=true;try{await api16('/hr/lifecycle/contracts/'+b.dataset.hrlcContractSign+'/sign',{method:'POST',body:JSON.stringify({document_id:Number(doc),signed_at:signed})});toast('Signed contract submitted for Owner approval.');state.hrLifecycleLoaded=false;await loadHRLifecycle()}catch(e){toast(e.message,true)}finally{b.disabled=false}}});
document.querySelectorAll('[data-hrlc-exit-approve]').forEach(b=>{if(b.__hrlcBound)return;b.__hrlcBound=true;b.onclick=async()=>{const note=prompt('Independent Owner review: verify legal rule, leave balance, final pay, deductions and gross/net reconciliation (minimum 20 characters).');if(!note||note.trim().length<20)return;b.disabled=true;try{await api16('/hr/lifecycle/exits/'+b.dataset.hrlcExitApprove+'/approve',{method:'POST',body:JSON.stringify({approval_note:note})});toast('Settlement approved; payment is still separate.');state.hrLifecycleLoaded=false;await loadHRLifecycle()}catch(e){toast(e.message,true)}finally{b.disabled=false}}});
document.querySelectorAll('[data-hrlc-hire-activate]').forEach(b=>{if(b.__hrlcBound)return;b.__hrlcBound=true;b.onclick=async()=>{if(!confirm('Activate this employee after verifying the selection, signed contract and mandatory documents?'))return;b.disabled=true;try{await api16('/hr/lifecycle/employees/'+b.dataset.hrlcHireActivate+'/activate',{method:'POST',body:JSON.stringify({approval_note:'Owner verified signed contract, required documents, onboarding and compensation.'})});toast('Hire activated after all prerequisite checks');state.hrLifecycleLoaded=false;await loadHRLifecycle()}catch(e){toast(e.message,true)}finally{b.disabled=false}}});
document.querySelectorAll('[data-hrlc-comp-approve]').forEach(b=>{if(b.__hrlcBound)return;b.__hrlcBound=true;b.onclick=async()=>{if(!confirm('Review and approve this effective-dated salary/allowance change?'))return;const approvalNote=prompt('Owner approval rationale (minimum 10 characters):');if(!approvalNote||approvalNote.trim().length<10){toast('Owner approval rationale is required.',true);return}b.disabled=true;try{await api16('/hr/lifecycle/compensation/'+b.dataset.hrlcCompApprove+'/approve',{method:'POST',body:JSON.stringify({approval_note:approvalNote.trim()})});toast('Compensation change approved and audit rationale recorded');state.hrLifecycleLoaded=false;await loadHRLifecycle()}catch(e){toast(e.message,true)}finally{b.disabled=false}}});
document.querySelectorAll('[data-hrlc-exit-pay]').forEach(b=>{if(b.__hrlcBound)return;b.__hrlcBound=true;b.onclick=async()=>{const ref=prompt('Bank/cash/cheque payment reference:');if(!ref)return;b.disabled=true;try{await api16('/hr/lifecycle/exits/'+b.dataset.hrlcExitPay+'/pay',{method:'POST',body:JSON.stringify({payment_reference:ref,payment_method:'BANK'})});toast('Final settlement journal and payment receipt recorded');state.hrLifecycleLoaded=false;await loadHRLifecycle()}catch(e){toast(e.message,true)}finally{b.disabled=false}}});
const rf=document.getElementById('refreshHR');if(rf&&!rf.__hrlcBound){rf.__hrlcBound=true;rf.onclick=()=>{state.hrLifecycleLoaded=false;loadHRLifecycle()}}
document.querySelectorAll('[data-hrlc-payroll]').forEach(b=>{if(b.__hrlcBound)return;b.__hrlcBound=true;b.onclick=async()=>{const action=b.dataset.action,id=b.dataset.hrlcPayroll;b.disabled=true;try{if(action==='generate')await api16('/hr/payroll/'+id+'/generate',{method:'POST',body:'{}'});else if(action==='submit')await api16('/hr/payroll/'+id+'/submit',{method:'POST',body:'{}'});else if(action==='approve')await api16('/hr/payroll/'+id+'/approve',{method:'POST',body:'{}'});else if(action==='pay')await api16('/hr/payroll/'+id+'/pay',{method:'POST',body:JSON.stringify({method:'BANK'})});toast('Payroll action completed');state.hrLifecycleLoaded=false;await loadHRLifecycle()}catch(e){toast(e.message,true)}finally{b.disabled=false}}});
document.getElementById('hrlcAllowancePreview')?.addEventListener('click',async function(){try{const f=this.closest('form');const d=await api16('/hr/lifecycle/allowance-preview',{method:'POST',body:JSON.stringify({effective_date:f.elements.effective_from.value||localDate(),allowances:JSON.parse(f.elements.allowances.value||'[]')})});document.getElementById('hrlcAllowancePreviewResult').textContent='Input '+d.input_total+' = exempt '+d.exempt_total+' + taxable '+d.taxable_total+'; rule '+(d.rule_version||'none')+'. '+(d.reconciled?'reconciled':'NOT reconciled')}catch(e){toast(e.message,true)}})
}



function hrlcCycleInput(n,l,t,v,required,opts){opts=opts||{};let a='';if(t==='file'){a='<input name="'+hrlcEsc(n)+'" type="file" accept=".pdf,.docx,.xlsx,.txt,.png,.jpg,.jpeg"'+(required?' required':'')+'>'}else if(t==='select'){a='<select name="'+hrlcEsc(n)+'"'+(required?' required':'')+'>'+opts.map(o=>'<option value="'+hrlcEsc(o[0])+'">'+hrlcEsc(o[1])+'</option>').join('')+'</select>'}else if(t==='textarea'){a='<textarea name="'+hrlcEsc(n)+'" rows="2"'+(required?' required':'')+'>'+hrlcEsc(v||'')+'</textarea>'}else{a='<input name="'+hrlcEsc(n)+'" type="'+hrlcEsc(t||'text')+'" value="'+hrlcEsc(v||'')+'"'+(required?' required':'')+(t==='number'?' step="0.01" min="0"':'')+'>'}return '<div class="sl-field"><label>'+hrlcEsc(l)+(required?' *':'')+'</label>'+a+'</div>'}
function hrlcCycleForm(kind,title,fields,submit){return '<details class="sl-hrlc-form"><summary><b>'+hrlcEsc(title)+'</b><span class="sl-pill info">Controlled workflow</span></summary><form class="sl-form sl-hrlc-grid" data-hrcycle-form="'+hrlcEsc(kind)+'">'+fields.map(f=>hrlcCycleInput(f[0],f[1],f[2],f[3],!!f[4],f[5])).join('')+'<div class="sl-field full"><button type="submit" class="sl-btn primary">'+hrlcEsc(submit||'Save')+'</button></div><p class="sl-sub full">All approvals and external settlement references remain separately auditable. Do not enter bank passwords, social passwords or recovery tokens.</p></form></details>'}
function hrlcCycleTable(rows,cols,actions){if(!Array.isArray(rows)||!rows.length)return '<div class="sl-empty">No records yet.</div>';return '<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr>'+cols.map(c=>'<th>'+hrlcEsc(c[1])+'</th>').join('')+'<th>Actions / State</th></tr></thead><tbody>'+rows.map(r=>'<tr>'+cols.map(c=>'<td>'+(c[2]==='money'?money(r[c[0]]||0):c[2]==='status'?hrlcStatus(r[c[0]]):hrlcEsc(r[c[0]]??''))+'</td>').join('')+'<td>'+hrlcStatus(r.status||'DRAFT')+(actions?actions(r):'')+'</td></tr>').join('')+'</tbody></table></div>'}
function hrlcSocialLinks(){const platforms=[{key:'facebook',name:'Facebook / Meta Business Suite',desc:'Campaign planning, audience tools and ad insights',url:'https://business.facebook.com/'},{key:'instagram',name:'Instagram',desc:'Product reels, outfit stories and engagement insights',url:'https://www.instagram.com/'},{key:'tiktok',name:'TikTok Business Center',desc:'Short-video campaigns, creator work and trend insights',url:'https://business.tiktok.com/'},{key:'youtube',name:'YouTube Studio',desc:'Product videos, Shorts and channel analytics',url:'https://studio.youtube.com/'}];return '<div class="sl-hrlc-social-grid">'+platforms.map(x=>'<a class="sl-hrlc-social-card" href="'+x.url+'" target="_blank" rel="noopener noreferrer"><span class="sl-hrlc-social-copy"><strong>'+hrlcEsc(x.name)+'</strong><small>'+hrlcEsc(x.desc)+'</small><span class="sl-hrlc-social-open">Open official platform <span aria-hidden="true">↗</span></span></span></a>').join('')+'</div><p class="sl-sub sl-hrlc-social-note"><strong>Reporting mode: MANUAL EXPORT.</strong> Metrics must be entered from timestamped platform exports; this is not a real-time API connection. Sign in on official sites with assigned account permissions and 2FA. Never store passwords, API keys or recovery tokens here. These links do not publish or schedule content.</p>'}
function hrlcPayrollCyclePanel(d){
 const runs=d.cycleRuns||[],batches=d.cycleBatches||[],payees=d.cyclePayees||[],obls=d.cycleObligations||[],resources=d.cycleResources||[],reimbursements=d.cycleReimbursements||[],remits=d.cycleRemittances||[],social=d.socialCycle||{campaigns:[],snapshots:[]};
 const errors=d.cycleAccessErrors||[];
 let out='';
 out+=hrlcSection('10 · Frozen payroll runs & legal gate','Separate frozen calculation, submission, Owner maker-checker posting, recipient-level bank/cash outcomes and statutory reconciliation. A journal is not proof an employee was paid.',hrlcCycleForm('create-run','Create payroll run draft',[['period_key','Period key (YYYY-MM)','text',localDate().slice(0,7),true],['start_date','Period start','date',localDate().slice(0,7)+'-01',true],['end_date','Period end','date',localDate(),true],['run_kind','Run kind','select','NORMAL',true,[['NORMAL','Normal run'],['ADJUSTMENT','Approved adjustment'],['REVERSAL','Controlled reversal']]],['parent_run_id','Prior run ID (adjustment/reversal only)','number','',false],['idempotency_key','Unique idempotency key (12+ chars)','text','',true]],'Create frozen-run draft')+hrlcCycleTable(runs,[['run_code','Run'],['period_key','Period'],['employee_count','Employees'],['gross_total','Gross','money'],['net_total','Net','money'],['rule_version','Rule version'],['status','Status','status']],r=>{let a='';if(r.status==='DRAFT')a+='<button class="sl-btn sl-btn-sm" type="button" data-hrcycle-action="generate-run" data-id="'+Number(r.id)+'">Generate</button>';if(r.status==='CALCULATED')a+='<button class="sl-btn sl-btn-sm primary" type="button" data-hrcycle-action="submit-run" data-id="'+Number(r.id)+'">Submit for Owner</button>';if(r.status==='SUBMITTED')a+='<button class="sl-btn sl-btn-sm primary" type="button" data-hrcycle-action="approve-run" data-id="'+Number(r.id)+'">Owner approve + post</button>';if(['POSTED','PAYMENT_PARTIAL'].includes(r.status))a+='<button class="sl-btn sl-btn-sm" type="button" data-hrcycle-action="batch" data-id="'+Number(r.id)+'">Instruct payout batch</button>';return a})+(errors.length?'<div class="sl-notice sl-danger-block">Some HR/payroll data could not be loaded for this user: '+hrlcEsc(errors.join(' · '))+'</div>':'<div class="sl-notice">If an approved rule and written legal opinion are missing, generation remains blocked. No default PAYE or allowance exemption is inferred.</div>'));
 out+=hrlcSection('11 · Recipient payout batches','Manual bank/cash settlement evidence. Every recipient is marked PAID/FAILED/RETURNED before the batch can reconcile.',hrlcCycleTable(batches,[['batch_code','Batch'],['run_id','Run ID'],['payment_method','Method'],['total_requested','Requested','money'],['total_paid','Paid','money'],['external_batch_reference','External batch reference'],['bank_statement_reference','Statement/voucher'],['status','Status','status']],b=>{let a='';(b.items||[]).forEach(i=>{if(i.status==='PENDING')a+='<button class="sl-btn sl-btn-sm" type="button" data-hrcycle-action="payout-result" data-batch="'+Number(b.id)+'" data-item="'+Number(i.id)+'" data-amount="'+hrlcEsc(i.amount)+'" data-code="'+hrlcEsc(i.employee_code)+'">Result '+hrlcEsc(i.employee_code)+'</button>'});if(['RESULTS_COMPLETE','PARTIAL_RESULT','FAILED'].includes(b.status))a+='<button class="sl-btn sl-btn-sm primary" type="button" data-hrcycle-action="reconcile-batch" data-id="'+Number(b.id)+'" data-paid="'+hrlcEsc(b.total_paid)+'" data-ref="'+hrlcEsc(b.external_batch_reference)+'">Reconcile external total</button>';return a}));
 out+=hrlcSection('12 · Statutory remittance obligations','PAYE, employee pension and employer pension stay open until supported by official filing/payment references, an approved private receipt and exact amount reconciliation.',hrlcCycleTable(remits,[['remittance_code','Remittance'],['statutory_type','Type'],['run_id','Run'],['amount_due','Due','money'],['due_date','Due date'],['status','State','status'],['payment_reference','Payment reference']],r=>r.status==='DRAFT'?'<button class="sl-btn sl-btn-sm primary" type="button" data-hrcycle-action="reconcile-remit" data-id="'+Number(r.id)+'" data-amount="'+hrlcEsc(r.amount_due)+'">Record receipt</button>':''));
 out+=hrlcSection('13 · Employee expenses & reimbursements','Actual receipt-backed business expenses are kept separate from salary and flat-cash allowances.',hrlcCycleForm('create-reimbursement','Submit employee expense claim',[['employee_id','Employee ID','number','',true],['expense_category','Category','select','TRAVEL',true,[['TRAVEL','Travel'],['TRANSPORT','Transport'],['MEALS_BUSINESS','Business meals'],['ACCOMMODATION','Accommodation'],['SUPPLIES','Supplies'],['REPAIRS','Repairs'],['TELEPHONE_DATA','Telephone/data'],['DELIVERY','Delivery'],['OTHER_APPROVED_BUSINESS','Other approved business expense']]],['incurred_date','Date incurred','date',localDate(),true],['amount','Amount ETB','number','',true],['receipt_resource_id','Approved private EXPENSE_RECEIPT resource ID','number','',true],['description','Expense description','text','',true],['business_purpose','Business purpose / evidence note','textarea','',true]],'Submit claim')+hrlcCycleTable(reimbursements,[['claim_code','Claim'],['employee_id','Employee'],['expense_category','Category'],['incurred_date','Date'],['amount','Amount','money'],['status','Status','status']],r=>{let a='';if(r.status==='SUBMITTED')a+='<button class="sl-btn sl-btn-sm primary" type="button" data-hrcycle-action="approve-reimbursement" data-id="'+Number(r.id)+'">Approve / accrue</button>';if(r.status==='APPROVED')a+='<button class="sl-btn sl-btn-sm" type="button" data-hrcycle-action="pay-reimbursement" data-id="'+Number(r.id)+'">Record settlement</button>';return a}));
 out+=hrlcSection('14 · Individual payees — creator, influencer, freelancer & services','No fake employee or company records. Separate identity facts from legal tax classification. Obligations stay in LEGAL_REVIEW until the correct effective rule and qualified written opinion are approved.',hrlcCycleForm('create-payee','Register individual payee',[['full_name','Legal name','text','',true],['payee_type','Relationship','select','DIGITAL_CREATOR',true,[['DIGITAL_CREATOR','Digital content creator'],['INFLUENCER','Influencer'],['FREELANCER','Freelancer'],['SERVICE_PROVIDER','Service provider'],['OTHER_INDIVIDUAL','Other individual payee']]],['residency_status','Residency status','select','UNKNOWN',true,[['UNKNOWN','Unknown / not yet verified'],['RESIDENT','Resident'],['NON_RESIDENT','Non-resident']]],['registration_status','Registration status','select','UNKNOWN',true,[['UNKNOWN','Unknown / not yet verified'],['REGISTERED','Registered / evidence pending'],['UNREGISTERED','Unregistered / evidence pending'],['NOT_APPLICABLE','Not applicable after review']]],['tin','TIN (encrypted if configured)','text','',false],['business_license','Business licence (encrypted if configured)','text','',false],['contact_email','Email','email','',false],['contact_phone','Phone','tel','',false]],'Create individual payee')+hrlcCycleTable(payees,[['payee_code','Payee code'],['full_name','Name'],['payee_type','Relationship'],['registration_status','Registration'],['tin_last4','TIN • last 4'],['status','Status','status']],r=>r.status==='DRAFT'?'<button class="sl-btn sl-btn-sm primary" type="button" data-hrcycle-action="approve-payee" data-id="'+Number(r.id)+'">Approve identity</button>':'<button class="sl-btn sl-btn-sm" type="button" data-hrcycle-action="create-obligation" data-id="'+Number(r.id)+'">New obligation</button>')+hrlcCycleTable(obls,[['obligation_code','Obligation'],['full_name','Payee'],['obligation_type','Type'],['cash_amount','Cash','money'],['in_kind_value','In-kind','money'],['tax_decision_status','Tax decision','status'],['status','Status','status']],o=>{if(o.status==='DRAFT')return '<button class="sl-btn sl-btn-sm primary" type="button" data-hrcycle-action="decide-obligation" data-id="'+Number(o.id)+'">Qualified tax review + approve</button><button class="sl-btn sl-btn-sm" type="button" data-hrcycle-action="reject-obligation" data-id="'+Number(o.id)+'">Reject</button>';if(o.status==='APPROVED')return '<button class="sl-btn sl-btn-sm primary" type="button" data-hrcycle-action="pay-obligation" data-id="'+Number(o.id)+'" data-withholding="'+hrlcEsc(o.withholding_amount||'0.00')+'">Record payment</button>';if(o.status==='CASH_PAID_IN_KIND_OPEN')return '<button class="sl-btn sl-btn-sm" type="button" data-hrcycle-action="settle-in-kind" data-id="'+Number(o.id)+'">Settle in-kind</button>';return ''}));
 out+=hrlcSection('15 · Private HR document library','Uploads require STOCKLAND_PRIVATE_VAULT_DIR outside the web root. Downloads are authenticated, role-scoped and approval-gated; draft employment-term sheets are not legal contracts.',hrlcCycleForm('upload-resource','Upload a private HR document',[['title','Title','text','',true],['resource_type','Resource type','select','JOB_DESCRIPTION',true,[['JOB_DESCRIPTION','Job description'],['EMPLOYMENT_CONTRACT','Approved/signed employment contract'],['EMPLOYMENT_POLICY','Employment policy'],['POLICY','Company policy'],['EMPLOYMENT_TERMS_DRAFT','Draft employment terms sheet'],['CODE_OF_CONDUCT','Code of Conduct'],['SOP','Role SOP'],['FORM','Form'],['TAX_RECEIPT','Tax receipt'],['PENSION_RECEIPT','Pension receipt'],['STATUTORY_RECEIPT','Statutory receipt'],['EXPENSE_RECEIPT','Expense receipt'],['IN_KIND_SETTLEMENT_EVIDENCE','In-kind delivery evidence'],['AGREEMENT','Agreement'],['IDENTITY_EVIDENCE','Identity evidence'],['OTHER','Other']],],['role_code','Role code (ALL or precise role)','text','ALL',true],['language_code','Language','select','en-am',true,[['en-am','English + Amharic'],['en','English'],['am','Amharic']]],['version_label','Version','text','1.0',true],['resource_code','Resource code (optional)','text','',false],['description','Description','textarea','',false],['file','Private file (PDF/DOCX/XLSX/TXT/PNG/JPG, max 10 MiB)','file','',true]],'Upload private file')+hrlcCycleTable(resources,[['resource_code','Code'],['title','Title'],['role_code','Role'],['resource_type','Type'],['language_code','Language'],['version_label','Version'],['status','State','status']],r=>{let a='';if(r.status==='DRAFT')a+='<button class="sl-btn sl-btn-sm primary" type="button" data-hrcycle-action="approve-resource" data-id="'+Number(r.id)+'">Approve resource</button>';if(r.status==='APPROVED')a+='<button class="sl-btn sl-btn-sm" type="button" data-hrcycle-action="download-resource" data-id="'+Number(r.id)+'">Download</button><button class="sl-btn sl-btn-sm" type="button" data-hrcycle-action="revise-resource" data-id="'+Number(r.id)+'" data-title="'+hrlcEsc(r.title)+'" data-description="'+hrlcEsc(r.description||'')+'" data-resource-code="'+hrlcEsc(r.resource_code)+'" data-type="'+hrlcEsc(r.resource_type)+'" data-role="'+hrlcEsc(r.role_code)+'" data-language="'+hrlcEsc(r.language_code)+'" data-version="'+hrlcEsc(r.version_label)+'">Edit / save new version</button>';if(r.status==='SUPERSEDED')a+='<button class="sl-btn sl-btn-sm" type="button" data-hrcycle-action="download-resource" data-id="'+Number(r.id)+'">History download</button>';return a}));
 out+=hrlcSection('16 · Social media analysis','Social campaigns, snapshots and official platform sign-in now live in Management Intelligence, separate from payroll.','<button type="button" class="sl-btn" data-view="intelligence">Open Management Intelligence</button>');
 return '<div class="sl-hrlc-cycle">'+out+'</div>';
}
function openHRResourceRevisionDialog(button){
 return new Promise((resolve,reject)=>{
  const id=Number(button.dataset.id||0);if(!id){reject(new Error('Document ID is missing.'));return;}
  const d=document.createElement('dialog');d.className='sl-hrlc-resource-dialog';d.setAttribute('aria-labelledby','hr-resource-revision-title');
  const field=(name,label,value,type='text',required=false)=>'<label class="sl-hrlc-resource-field"><span>'+hrlcEsc(label)+'</span><input name="'+name+'" type="'+type+'" value="'+hrlcEsc(value||'')+'" '+(required?'required':'')+'></label>';
  d.innerHTML='<form class="sl-hrlc-resource-editor" enctype="multipart/form-data"><header><div><p class="sl-eyebrow">DOCUMENT CONTROL</p><h3 id="hr-resource-revision-title">Edit and save a new version</h3><p class="sl-sub">The approved version stays active until an independent approver approves this new draft.</p></div><button type="button" class="sl-btn sl-btn-sm" data-close-dialog aria-label="Close editor">Close</button></header><div class="sl-hrlc-resource-grid">'+field('title','Document title',button.dataset.title,'text',true)+field('resource_code','New document code (optional)',(button.dataset.resourceCode||'')+'-REV','text',false)+field('resource_type','Resource type (locked)',button.dataset.type,'text',true)+field('role_code','Role access (locked)',button.dataset.role,'text',true)+field('language_code','Language (locked)',button.dataset.language,'text',true)+field('version_label','New version',String(button.dataset.version||'1.0')+'.1','text',true)+'<label class="sl-hrlc-resource-field sl-hrlc-resource-wide"><span>Description / change note</span><textarea name="description" rows="3">'+hrlcEsc(button.dataset.description||'')+'</textarea></label><label class="sl-hrlc-resource-field sl-hrlc-resource-wide"><span>Replacement file (PDF, DOCX, XLSX, TXT or image; max 10 MiB)</span><input name="file" type="file" accept=".pdf,.docx,.xlsx,.txt,.png,.jpg,.jpeg" required></label></div><footer><span class="sl-sub">A new version is draft-only until separately approved. File hashes and revision history are recorded.</span><button type="submit" class="sl-btn primary">Save new draft version</button></footer><div class="sl-hrlc-resource-status" role="status" aria-live="polite"></div></form>';
  const form=d.querySelector('form'),status=d.querySelector('.sl-hrlc-resource-status'),submit=form.querySelector('[type=submit]');
  ['resource_type','role_code','language_code'].forEach(k=>{if(form.elements[k])form.elements[k].readOnly=true;});
  const close=()=>{if(d.open)d.close();d.remove();};
  d.querySelector('[data-close-dialog]').addEventListener('click',close);
  d.addEventListener('cancel',e=>{e.preventDefault();close();});
  form.addEventListener('submit',async e=>{
   e.preventDefault();if(!form.reportValidity())return;
   const file=form.elements.file.files[0];if(!file){status.textContent='Select the replacement document.';return;}
   submit.disabled=true;status.textContent='Saving private draft…';
   try{
    const fd=new FormData();['title','resource_code','resource_type','role_code','language_code','version_label','description'].forEach(k=>fd.set(k,form.elements[k].value));
    fd.set('file',file);
    const result=await api16('/hr/resources/'+id+'/revise',{method:'POST',body:fd});
    if(!result||String(result.status||'').toUpperCase()!=='DRAFT'||!Number(result.id))throw new Error('The server did not confirm a saved draft version.');
    status.textContent='Draft version saved. Previous approved version is unchanged.';
    toast('New document version saved as DRAFT. Independent approval is required.');
    close();state.hrLifecycleLoaded=false;await loadHRLifecycle();resolve(result);
   }catch(err){status.textContent=err.message||'Revision save failed.';submit.disabled=false;reject(err);}
  });
  document.body.appendChild(d);d.showModal();form.elements.title.focus();
 });
}
async function downloadHRResourceFile(id){
 const path='/hr/resources/'+Number(id)+'/download';
 const base=state.boot.api_v16||String(state.boot.api||'').replace('/v1','/v16');
 const url=resolveTrustedApiUrl(path,base);
 const response=await fetch(url,{method:'GET',headers:{'X-WP-Nonce':A.nonce,'Accept':'application/octet-stream'},credentials:'same-origin',cache:'no-store'});
 const blob=await response.blob();
 if(!response.ok){
  let message='Document download failed ('+response.status+').';
  try{const payload=JSON.parse(await blob.text());if(payload&&payload.message)message=String(payload.message)}catch(_e){}
  throw new Error(message);
 }
 if((blob.type||'').toLowerCase().includes('application/json'))throw new Error('The server returned JSON instead of the private document. Check access and document approval.');
 const disposition=response.headers.get('Content-Disposition')||'';
 let filename='Stockland-HR-document-'+Number(id);
 const match=disposition.match(/filename="?([^";]+)"?/i);
 if(match&&match[1]&&!match[1].includes('/')&&!match[1].includes('\\'))filename=match[1];
 const objectUrl=URL.createObjectURL(blob),anchor=document.createElement('a');
 anchor.href=objectUrl;anchor.download=filename;anchor.rel='noopener';anchor.style.display='none';
 document.body.appendChild(anchor);anchor.click();anchor.remove();setTimeout(()=>URL.revokeObjectURL(objectUrl),1500);
 return {filename};
}
function bindHRPayrollCycleForms(){
 document.querySelectorAll('[data-hrcycle-form]').forEach(f=>{if(f.__hrCycleBound)return;f.__hrCycleBound=true;f.addEventListener('submit',async e=>{e.preventDefault();if(!f.reportValidity())return;const kind=f.dataset.hrcycleForm;const btn=f.querySelector('[type=submit]');if(btn)btn.disabled=true;try{const fd=new FormData(f);let d=Object.fromEntries(fd.entries());let path='',body=d,options={method:'POST'};
  if(kind==='upload-resource'){const file=f.querySelector('input[name=file]')?.files?.[0];if(!file)throw new Error('Choose a private file.');fd.delete('file');fd.set('file',file);options.body=fd;path='/hr/resources';}
  else if(kind==='create-run'){path='/hr/payroll-runs';if(!/^\d{4}-(0[1-9]|1[0-2])$/.test(d.period_key))throw new Error('Period key must be YYYY-MM.');if(['ADJUSTMENT','REVERSAL'].includes(d.run_kind))d.parent_run_id=Number(d.parent_run_id);else delete d.parent_run_id;options.body=JSON.stringify(d);}
  else if(kind==='create-payee'){path='/hr/individual-payees';options.body=JSON.stringify(d);}
  else if(kind==='create-reimbursement'){path='/hr/employee-reimbursements';d.employee_id=Number(d.employee_id);d.receipt_resource_id=Number(d.receipt_resource_id);d.amount=Number(d.amount);options.body=JSON.stringify(d);}
  else if(kind==='create-campaign'){path='/hr/management-social';d.budget=Number(d.budget);if(d.manager_user_id)d.manager_user_id=Number(d.manager_user_id);else delete d.manager_user_id;options.body=JSON.stringify(d);}
  await api16(path,options);toast('Saved. The next approval/settlement step remains separate.');if(state.view==='intelligence'){await loadManagementSocial()}else{state.hrLifecycleLoaded=false;await loadHRLifecycle()}
 }catch(err){toast(err.message||'Save failed',true)}finally{if(btn)btn.disabled=false;}});});
 document.querySelectorAll('[data-hrcycle-action]').forEach(b=>{if(b.__hrCycleBound)return;b.__hrCycleBound=true;b.addEventListener('click',async()=>{const action=b.dataset.hrcycleAction,id=Number(b.dataset.id||0);if(!id&&!['create-run'].includes(action))return;b.disabled=true;try{let path='',body={},opts={method:'POST'};
  if(action==='revise-resource'){await openHRResourceRevisionDialog(b);return;}
   if(action==='generate-run'){path='/hr/payroll-runs/'+id+'/generate';}
  else if(action==='submit-run'){const note=prompt('Preparation review note (minimum 10 characters):');if(!note||note.trim().length<10)throw new Error('Review note is mandatory.');path='/hr/payroll-runs/'+id+'/submit';body={review_note:note};}
  else if(action==='approve-run'){const note=prompt('Independent Owner review: verify headcount, variances, legal rule source/hash, gross/net and liabilities. (minimum 20 characters)');if(!note||note.trim().length<20)throw new Error('Owner approval note is mandatory.');path='/hr/payroll-runs/'+id+'/approve';body={approval_note:note};}
  else if(action==='batch'){const method=prompt('Payment method: BANK or CASH','BANK');if(!['BANK','CASH'].includes(String(method||'').toUpperCase()))throw new Error('Choose BANK or CASH.');const ref=prompt('External bank batch or cash voucher reference:');if(!ref)throw new Error('External batch reference is required.');path='/hr/payroll-runs/'+id+'/payment-batches';body={payment_method:String(method).toUpperCase(),external_batch_reference:ref,idempotency_key:'batch-'+id+'-'+Date.now()};}
  else if(action==='payout-result'){const result=String(prompt('External recipient result: PAID, FAILED or RETURNED','PAID')||'').toUpperCase();if(!['PAID','FAILED','RETURNED'].includes(result))throw new Error('Choose PAID, FAILED or RETURNED.');body.result=result;if(result==='PAID'){body.amount=b.dataset.amount;body.external_payment_reference=prompt('External recipient transfer reference:')||'';}else body.failure_reason=prompt('External failure/return reason (minimum 5 characters):')||'';path='/hr/payroll-payment-batches/'+Number(b.dataset.batch)+'/items/'+Number(b.dataset.item)+'/result';}
  else if(action==='reconcile-batch'){const statement=prompt('Bank statement or cash voucher reconciliation reference:');if(!statement)throw new Error('Reconciliation reference is mandatory.');const total=prompt('Confirm total successful amount (must equal recorded recipient successes):',b.dataset.paid||'0.00');path='/hr/payroll-payment-batches/'+id+'/reconcile';body={bank_statement_reference:statement,external_batch_reference:b.dataset.ref,confirmed_paid_total:total};}
  else if(action==='reconcile-remit'){const filing=prompt('Official filing reference:');const payment=prompt('Official tax/pension payment reference:');const resource=prompt('Approved private receipt resource ID (TAX_RECEIPT/PENSION_RECEIPT/STATUTORY_RECEIPT):');if(!filing||!payment||!/^\d+$/.test(resource||''))throw new Error('Filing/payment references and approved receipt ID required.');path='/hr/statutory-remittances/'+id+'/reconcile';body={filing_reference:filing,payment_reference:payment,receipt_resource_id:Number(resource),confirmed_amount:b.dataset.amount};}
  else if(action==='approve-payee'){const note=prompt('Independent identity verification note (min 10 chars):');if(!note||note.trim().length<10)throw new Error('Identity review note required.');path='/hr/individual-payees/'+id+'/approve';body={approval_note:note};}
  else if(action==='create-obligation'){const t=String(prompt('Type: DIGITAL_CONTENT, INFLUENCER_CAMPAIGN, FREELANCE_SERVICE, PROFESSIONAL_SERVICE or OTHER_INDIVIDUAL_PAYMENT','DIGITAL_CONTENT')||'').toUpperCase();const cash=prompt('Cash contractual amount ETB','0.00');const inkind=prompt('In-kind value ETB (if any)','0.00');const detail=prompt('Specific deliverable(s), one paragraph:');if(!detail)throw new Error('Deliverables required.');const url=prompt('Content/deliverable URL (optional)')||'';const contract=prompt('Agreement/contract reference (optional)')||'';const campaign=prompt('Campaign reference (optional)')||'';const resource=prompt('Approved private AGREEMENT resource ID (optional)')||'';path='/hr/individual-payees/'+id+'/obligations';body={obligation_type:t,cash_amount:cash,in_kind_value:inkind,deliverables:[{description:detail}],content_url:url,contract_reference:contract,campaign_reference:campaign,agreement_resource_id:resource?Number(resource):null};}
  else if(action==='decide-obligation'){const rule=prompt('ID of independently approved INDIVIDUAL_PAYEE tax rule:');const expense=prompt('Existing expense GL account code:');const payable=prompt('Existing AP liability GL account code:');const note=prompt('Qualified reviewer decision and supporting official clause (minimum 20 characters):');if(!/^\d+$/.test(rule||'')||!expense||!payable||!note||note.trim().length<20)throw new Error('Approved rule ID, GL account codes and substantive review note are required.');path='/hr/individual-payee-obligations/'+id+'/decision';body={decision:'APPROVE',tax_rule_set_id:Number(rule),expense_account_code:expense,payable_account_code:payable,review_note:note};}
  else if(action==='reject-obligation'){const note=prompt('Reason for rejection:');if(!note||note.trim().length<20)throw new Error('A detailed rejection reason is required.');path='/hr/individual-payee-obligations/'+id+'/decision';body={decision:'REJECT',review_note:note};}
  else if(action==='pay-obligation'){const method=String(prompt('Payment method BANK or CASH','BANK')||'').toUpperCase();const ref=prompt('External payment reference:');const bank=method==='BANK'?prompt('Bank statement reference:')||'':'';let w='';if(Number(b.dataset.withholding)>0)w='HR_RESOURCE:'+prompt('ID of approved private tax/statutory withholding receipt resource:');if(!['BANK','CASH'].includes(method)||!ref||(Number(b.dataset.withholding)>0&&!/^HR_RESOURCE:\d+$/.test(w)))throw new Error('Payment and withholding evidence references are required.');path='/hr/individual-payee-obligations/'+id+'/pay';body={payment_method:method,external_payment_reference:ref,bank_statement_reference:bank,withholding_receipt_reference:w,withholding_payable_account_code:Number(b.dataset.withholding)>0?prompt('Existing withholding payable GL account code:'):''};}
  else if(action==='settle-in-kind'){const ref=prompt('Goods/services delivery acceptance reference:');const account=prompt('Approved existing inventory/settlement GL account code:');const evidence=prompt('Approved private IN_KIND_SETTLEMENT_EVIDENCE resource ID:');const note=prompt('Independent Finance settlement note, min 20 chars:');if(!ref||!/^\d+$/.test(account||'')||!/^\d+$/.test(evidence||'')||!note||note.trim().length<20)throw new Error('In-kind settlement evidence and accounts are required.');path='/hr/individual-payee-obligations/'+id+'/settle-in-kind';body={in_kind_settlement_reference:ref,in_kind_settlement_account_code:account,evidence_resource_id:Number(evidence),review_note:note};}
  else if(action==='approve-resource'){const note=prompt('Independent resource approval note:');if(!note||note.trim().length<10)throw new Error('Approval note required.');path='/hr/resources/'+id+'/approve';body={approval_note:note};}
   else if(action==='download-resource'){const result=await downloadHRResourceFile(id);toast('Downloaded '+result.filename);return;}
  else if(action==='approve-reimbursement'){const expense=prompt('Existing approved expense GL account code:');const payable=prompt('Existing AP liability GL account code:');const note=prompt('Independent receipt/business-purpose review note, min 15 characters:');if(!/^\d+$/.test(expense||'')||!/^\d+$/.test(payable||'')||!note||note.trim().length<15)throw new Error('Review note and existing account codes required.');path='/hr/employee-reimbursements/'+id+'/approve';body={expense_account_code:expense,payable_account_code:payable,reviewer_note:note};}
  else if(action==='pay-reimbursement'){const method=String(prompt('Payment method BANK or CASH','BANK')||'').toUpperCase();const ref=prompt('Bank transaction/cash voucher reference:');const statement=method==='BANK'?prompt('Bank statement reference:')||'':'';if(!['BANK','CASH'].includes(method)||!ref||(method==='BANK'&&!statement))throw new Error('External payout evidence is required.');path='/hr/employee-reimbursements/'+id+'/pay';body={payment_method:method,payment_reference:ref,bank_statement_reference:statement};}
  else if(action==='capture-social'){const start=prompt('Reporting period start (YYYY-MM-DD):');const end=prompt('Reporting period end (YYYY-MM-DD):');const asof=prompt('Report-as-of timestamp YYYY-MM-DD HH:MM:SS (local account time):');const source=prompt('Platform export/Insights evidence reference:');const hash=prompt('SHA-256 of saved export/evidence file:');if(!start||!end||!asof||!source||! /^[a-f0-9]{64}$/i.test(hash||''))throw new Error('A valid reporting period, as-of timestamp, source reference and evidence SHA-256 are mandatory.');const reach=prompt('Reach (optional integer):')||'';const views=prompt('Views (optional integer):')||'';const engage=prompt('Engagements (optional integer):')||'';const clicks=prompt('Clicks (optional integer):')||'';const leads=prompt('Leads/DM inquiries (optional integer):')||'';const spend=prompt('Spend ETB (optional):')||'';const sales=prompt('Attributable sales ETB (optional; only if measured):')||'';path='/hr/management-social/'+id+'/snapshots';body={platform:b.dataset.platform,account_label:b.dataset.account,period_start:start,period_end:end,report_as_of:asof,source_reference:source,source_sha256:hash,reach_count:reach===''?null:Number(reach),view_count:views===''?null:Number(views),engagement_count:engage===''?null:Number(engage),click_count:clicks===''?null:Number(clicks),lead_count:leads===''?null:Number(leads),spend_amount:spend===''?null:spend,attributed_sales:sales===''?null:sales};}
  if(!path)throw new Error('Unsupported workflow action.');opts.body=JSON.stringify(body);await api16(path,opts);toast('Action recorded. Verify the next workflow state and external evidence.');if(state.view==='intelligence'){await loadManagementSocial()}else{state.hrLifecycleLoaded=false;await loadHRLifecycle()}
 }catch(e){toast(e.message||'Workflow action failed',true)}finally{b.disabled=false;}});});
}
function slPayrollMinor(value){return Math.round((Number(value)||0)*100)}
function slPayrollPercentMinor(amountMinor,rateValue){
  if(rateValue===null||rateValue===undefined||String(rateValue).trim()==='')return null;
  const match=String(rateValue).trim().match(/^(\d+)(?:\.(\d+))?$/);
  if(!match)return null;
  const fraction=(match[2]||'').replace(/0+$/,'');
  const denominator=10**fraction.length;
  const numerator=Number(match[1])*denominator+(fraction?Number(fraction):0);
  if(!Number.isFinite(numerator)||numerator>denominator)return null;
  const product=amountMinor*numerator;
  return Math.floor((product+denominator/2)/denominator);
}
function slPayrollTaxPreview(income,rules){
  const minor=Math.max(0,slPayrollMinor(income));const bands=Array.isArray(rules?.monthly_tax_bands)?rules.monthly_tax_bands:[];
  if(!bands.length)return {valid:false,label:'No approved PAYE schedule'};
  for(const band of bands){if(!band||band.rate===null||band.rate===undefined||band.credit===null||band.credit===undefined)return {valid:false,label:'Incomplete approved PAYE schedule'};const rateMinor=slPayrollPercentMinor(10000,band.rate);if(rateMinor===null)return {valid:false,label:'Invalid approved PAYE rate'};const cap=band.up_to===null?Infinity:slPayrollMinor(band.up_to);if(!Number.isFinite(cap)&&band.up_to!==null)return {valid:false,label:'Invalid PAYE band limit'};if(minor<=cap){const amountMinor=Math.max(0,slPayrollPercentMinor(minor,band.rate)-slPayrollMinor(band.credit));return {valid:true,amount:amountMinor/100,rate:Number(band.rate)*100,label:String(band.label||'Approved monthly PAYE band')}}}
  return {valid:false,label:'Income is outside the approved PAYE schedule'};
}
function slPayrollAmount(value){return Number.isFinite(Number(value))?Number(value):0}
function slPayrollAllowanceRow(catalog,item={}){
  const keys=Object.keys(catalog||{});const category=keys.includes(String(item.category||''))?String(item.category):'GENERAL_CASH';
  const treatment=String(item.tax_treatment||'TAXABLE').toUpperCase()==='EXEMPT'?'EXEMPT':'TAXABLE';
  const options=keys.map(k=>'<option value="'+esc(k)+'" '+(k===category?'selected':'')+'>'+esc(catalog[k].label||k)+'</option>').join('');
  return '<div class="sl-payroll-allowance-row" data-pay-allowance-row><div class="sl-payroll-allowance-row-head"><b>Allowance component</b><span class="sl-pill '+(treatment==='EXEMPT'?'warn':'info')+'" data-treatment-badge>'+(treatment==='EXEMPT'?'EXEMPTION CLAIM':'TAXABLE BY DEFAULT')+'</span></div><div class="sl-payroll-allowance-grid"><div class="sl-field"><label>Allowance category</label><select data-allowance-category>'+options+'</select><small class="sl-sub" data-allowance-hint>'+esc(catalog[category]?.description||'Use a specific compensation category.')+'</small></div><div class="sl-field"><label>Monthly amount (ETB)</label><input data-allowance-amount type="number" min="0" step="0.01" inputmode="decimal" value="'+esc(item.amount||'0.00')+'" required></div><div class="sl-field"><label>Tax treatment</label><select data-allowance-treatment><option value="TAXABLE" '+(treatment==='TAXABLE'?'selected':'')+'>Taxable (default)</option><option value="EXEMPT" '+(treatment==='EXEMPT'?'selected':'')+'>Claim statutory exemption</option></select></div><button type="button" class="sl-btn sl-btn-sm sl-remove-allowance" data-remove-allowance aria-label="Remove allowance row">Remove</button></div><div class="sl-payroll-exemption-fields" data-exemption-fields '+(treatment!=='EXEMPT'?'hidden':'')+'><div class="sl-field"><label>Current legal clause / authority</label><textarea data-allowance-basis placeholder="Identify the current proclamation, regulation and exact clause.">'+esc(item.legal_basis||'')+'</textarea></div><div class="sl-field"><label>Evidence reference</label><input data-allowance-evidence value="'+esc(item.evidence_reference||'')+'" placeholder="File or document reference"></div><label class="sl-payroll-owner-check"><input type="checkbox" data-owner-verified '+(item.owner_verified?'checked':'')+'><span>I have verified the legal basis and supporting evidence for this exact exemption as Owner.</span></label></div></div>';
}
function openCompensationEditor(button){
  const employee=(state.hr?.employees||[]).find(e=>String(e.id)===String(button.dataset.hrComp));
  if(!employee){toast('Employee row is stale. Refresh HR and try again.',true);return}
  const rules=state.hr?.statutory_rules||{};const catalog=rules.allowance_catalog||{};
  if(!Object.keys(catalog).length){toast('The compensation catalogue is unavailable. Refresh HR; no compensation was saved.',true);return}
  let rows=[];try{const parsed=JSON.parse(employee.current_allowance_breakdown_json||'[]');if(Array.isArray(parsed))rows=parsed}catch(_e){}
  if(!rows.length){const tx=slPayrollAmount(employee.current_taxable_allowance_total??employee.current_allowance_total??0);const ex=slPayrollAmount(employee.current_exempt_allowance_total??0);if(tx>0)rows.push({category:'GENERAL_CASH',amount:tx,tax_treatment:'TAXABLE'});if(ex>0)rows.push({category:'GENERAL_CASH',amount:ex,tax_treatment:'EXEMPT'});}
  if(!rows.length)rows=[{category:'GENERAL_CASH',amount:'0.00',tax_treatment:'TAXABLE'}];
  const ov=document.createElement('div');ov.className='sl-overlay';ov.setAttribute('data-payroll-editor-overlay','');
  ov.innerHTML='<div class="sl-payroll-editor" role="dialog" aria-modal="true" aria-labelledby="slPayrollEditTitle"><div class="sl-payroll-editor-head"><div><div class="sl-eyebrow" style="color:#9ed6ff">OWNER-VERIFIED · STATUTORY PAYROLL</div><h2 id="slPayrollEditTitle">Compensation & allowance classification</h2><p>'+esc(employee.full_name)+' · '+esc(employee.employee_id)+' · ETB monthly compensation. Tax and pension preview recalculates as you type; the server validates again before saving.</p></div><button type="button" class="sl-icon-btn" data-payroll-editor-close aria-label="Close compensation editor">×</button></div><form class="sl-payroll-editor-body" id="slPayrollCompForm" novalidate><div class="sl-payroll-rule-banner"><span aria-hidden="true">ⓘ</span><div><b>Statutory rule state: '+esc(rules.rule_version||'Approved rule set required')+'</b>'+((rules.status==='APPROVED'&&rules.employee_pension_rate!==null&&rules.employer_pension_rate!==null&&Array.isArray(rules.monthly_tax_bands)&&rules.monthly_tax_bands.length)?('Effective from '+esc(rules.effective_from||'')+' · Pension rates supplied by approved rule set: employee '+(Number(rules.employee_pension_rate)*100).toFixed(2)+'% · employer '+(Number(rules.employer_pension_rate)*100).toFixed(2)+'%.'):'No effective, independently reviewed statutory rule set is available. This screen can record a compensation draft, but it does not calculate PAYE or pension; payroll calculation remains blocked until an approved rule set is configured.')+'</div></div><section class="sl-payroll-section"><div class="sl-payroll-section-head"><div><div class="sl-eyebrow">01 / FIXED COMPENSATION</div><h3>Monthly salary & deductions</h3><p>Set amounts in Ethiopian birr. Pensionable salary normally matches base salary unless an approved rule says otherwise.</p></div></div><div class="sl-payroll-base-grid"><div class="sl-field"><label>Base monthly salary (ETB)</label><input name="base_salary" type="number" min="0" step="0.01" inputmode="decimal" value="'+esc(employee.current_base_salary||'0.00')+'" required></div><div class="sl-field"><label>Pensionable salary (ETB)</label><input name="pensionable_salary" type="number" min="0" step="0.01" inputmode="decimal" value="'+esc(employee.current_pensionable_salary??employee.current_base_salary??'0.00')+'" required><small class="sl-sub">Pension treatment is applied only from the approved effective-dated rule set.</small></div><div class="sl-field"><label>Other monthly deductions (ETB)</label><input name="other_deduction_total" type="number" min="0" step="0.01" inputmode="decimal" value="'+esc(employee.current_deduction_total||'0.00')+'" required></div></div></section><section class="sl-payroll-section"><div class="sl-payroll-section-head"><div><div class="sl-eyebrow">02 / VARIABLE COMPONENTS</div><h3>Allowance classification</h3><p>Every allowance is taxable by default. Do not select an exemption just because an item is labelled transport, housing, meal or reimbursement.</p></div><button type="button" class="sl-btn" id="slPayrollAddAllowance">+ Add component</button></div><div class="sl-payroll-allowance-list" id="slPayrollAllowanceList">'+rows.map(row=>slPayrollAllowanceRow(catalog,row)).join('')+'</div><div class="sl-sub" style="margin-top:8px">A claimed exemption requires an exact current legal clause, supporting evidence reference, and explicit Owner verification. The system does not assume historic allowance caps remain valid.</div></section><section class="sl-payroll-section"><div class="sl-payroll-section-head"><div><div class="sl-eyebrow">03 / LIVE CALCULATION</div><h3>Statutory preview</h3><p>Illustrative live preview from the rule schedule. Save and payroll generation re-calculate on the server.</p></div><span class="sl-pill info" data-payroll-tax-band>Monthly PAYE</span></div><div class="sl-payroll-summary-grid"><div class="sl-payroll-summary-card"><span>Gross pay</span><strong data-preview-gross>0.00 ETB</strong><small>Base + taxable + approved exempt components</small></div><div class="sl-payroll-summary-card"><span>Taxable income</span><strong data-preview-taxable>0.00 ETB</strong><small>PAYE base after employee pension contribution</small></div><div class="sl-payroll-summary-card"><span>PAYE income tax</span><strong data-preview-paye>0.00 ETB</strong><small data-preview-rate>Approved rule required</small></div><div class="sl-payroll-summary-card"><span>Employee pension</span><strong data-preview-employee-pension>0.00 ETB</strong><small>Approved employee rate × pensionable salary</small></div><div class="sl-payroll-summary-card"><span>Employer pension</span><strong data-preview-employer-pension>0.00 ETB</strong><small>Approved employer rate × pensionable salary</small></div><div class="sl-payroll-summary-card emphasis"><span>Estimated net pay</span><strong data-preview-net>0.00 ETB</strong><small>After PAYE, employee pension and other deductions</small></div><div class="sl-payroll-summary-card"><span>Employer total cost</span><strong data-preview-cost>0.00 ETB</strong><small>Gross + employer pension</small></div><div class="sl-payroll-summary-card"><span>Allowance split</span><strong data-preview-split>0.00 / 0.00</strong><small>Taxable / approved exempt</small></div></div><div class="sl-notice-warn" data-payroll-preview-warning hidden></div></section><section class="sl-payroll-section"><div class="sl-payroll-section-head"><div><div class="sl-eyebrow">04 / AUDIT CLASSIFICATION</div><h3>Owner confirmation & audit note</h3><p>Required where claiming a legal exemption or where pensionable salary differs from base salary.</p></div></div><div class="sl-field"><label>Legal / contract classification note</label><textarea name="statutory_classification_note" rows="3" placeholder="Document the current statutory/contract rule, interpretation and reason for the classification.">'+esc(employee.current_statutory_classification_note||'')+'</textarea></div></section><div class="sl-payroll-foot"><small>No existing payroll history is recalculated by saving a new effective-dated compensation record. This save creates an audit event; approved/paid periods remain locked.</small><div class="sl-action-row"><button type="button" class="sl-btn" data-payroll-editor-close>Cancel</button><button type="submit" class="sl-btn primary" id="slPayrollCompSave">Save compensation</button></div></div></form></div>';
  document.body.appendChild(ov);
  const form=ov.querySelector('#slPayrollCompForm'),list=ov.querySelector('#slPayrollAllowanceList'),save=ov.querySelector('#slPayrollCompSave');
  const close=()=>ov.remove();ov.querySelectorAll('[data-payroll-editor-close]').forEach(x=>x.addEventListener('click',close));ov.addEventListener('click',e=>{if(e.target===ov)close()});
  const fmt=n=>money(Math.round((Number(n)||0)*100)/100);
  function recalc(){
    let taxableAllow=0,exemptAllow=0;list.querySelectorAll('[data-pay-allowance-row]').forEach(row=>{const treatment=row.querySelector('[data-allowance-treatment]').value;const amount=Math.max(0,slPayrollAmount(row.querySelector('[data-allowance-amount]').value));if(treatment==='EXEMPT')exemptAllow+=amount;else taxableAllow+=amount;const f=row.querySelector('[data-exemption-fields]');if(f)f.hidden=treatment!=='EXEMPT';const badge=row.querySelector('[data-treatment-badge]');if(badge){badge.textContent=treatment==='EXEMPT'?'EXEMPTION CLAIM':'TAXABLE BY DEFAULT';badge.className='sl-pill '+(treatment==='EXEMPT'?'warn':'info')}const hint=row.querySelector('[data-allowance-hint]');if(hint)hint.textContent=catalog[row.querySelector('[data-allowance-category]').value]?.description||'';});
    const base=Math.max(0,slPayrollAmount(form.elements.base_salary.value)),pensionable=Math.max(0,slPayrollAmount(form.elements.pensionable_salary.value)),other=Math.max(0,slPayrollAmount(form.elements.other_deduction_total.value));
    const baseMinor=slPayrollMinor(base),taxableAllowMinor=slPayrollMinor(taxableAllow),exemptAllowMinor=slPayrollMinor(exemptAllow),pensionableMinor=slPayrollMinor(pensionable),otherMinor=slPayrollMinor(other);
    const grossMinor=baseMinor+taxableAllowMinor+exemptAllowMinor,empMinor=slPayrollPercentMinor(pensionableMinor,rules.employee_pension_rate),employerMinor=slPayrollPercentMinor(pensionableMinor,rules.employer_pension_rate),ratesReady=rules.status==='APPROVED'&&empMinor!==null&&employerMinor!==null,taxableIncomeMinor=ratesReady?Math.max(0,baseMinor+taxableAllowMinor-empMinor):null,taxableIncome=ratesReady?taxableIncomeMinor/100:null,tax=ratesReady?slPayrollTaxPreview(taxableIncome,rules):{valid:false,label:'No approved statutory rule set'},netMinor=tax.valid?grossMinor-slPayrollMinor(tax.amount)-empMinor-otherMinor:null,costMinor=ratesReady?grossMinor+employerMinor:null;
    const vals={'gross':grossMinor/100,'taxable':taxableIncome,'paye':tax.valid?tax.amount:null,'employee-pension':ratesReady?empMinor/100:null,'employer-pension':ratesReady?employerMinor/100:null,'net':netMinor===null?null:netMinor/100,'cost':costMinor===null?null:costMinor/100};Object.entries(vals).forEach(([k,v])=>{const el=ov.querySelector('[data-preview-'+k+']');if(el)el.textContent=v===null?'—':fmt(v)});
    const rate=ov.querySelector('[data-preview-rate]');if(rate)rate.textContent=tax.valid?tax.rate.toFixed(2)+'% · '+tax.label:tax.label;
    const split=ov.querySelector('[data-preview-split]');if(split)split.textContent=fmt(taxableAllow)+' / '+fmt(exemptAllow);
    const band=ov.querySelector('[data-payroll-tax-band]');if(band)band.textContent='Rule '+rules.rule_version;
    const warning=ov.querySelector('[data-payroll-preview-warning]');const issues=[];if(!tax.valid||!ratesReady)issues.push('Statutory preview unavailable: no effective approved PAYE/pension rule set. Payroll calculation and posting stay blocked.');if(pensionable>gross)issues.push('Pensionable salary exceeds gross pay; save is blocked by the server.');if(netMinor!==null&&netMinor<0)issues.push('Deductions exceed gross pay; save is blocked by the server.');list.querySelectorAll('[data-pay-allowance-row]').forEach((row,i)=>{if(row.querySelector('[data-allowance-treatment]').value==='EXEMPT'){const basis=row.querySelector('[data-allowance-basis]').value.trim(),ev=row.querySelector('[data-allowance-evidence]').value.trim(),ok=row.querySelector('[data-owner-verified]').checked;if(basis.length<20||ev.length<4||!ok)issues.push('Allowance component '+(i+1)+' is only a claim until legal basis, evidence and Owner verification are complete.')}});if((Math.abs(pensionable-base)>0.005||exemptAllow>0)&&form.elements.statutory_classification_note.value.trim().length<20)issues.push('Add a 20+ character legal/contract classification note for exemptions or non-standard pensionable salary.');warning.hidden=!issues.length;warning.textContent=issues.join(' ');
  }
  list.addEventListener('input',recalc);list.addEventListener('change',recalc);form.addEventListener('input',recalc);form.addEventListener('change',recalc);
  list.addEventListener('click',e=>{const remove=e.target.closest('[data-remove-allowance]');if(remove){const row=remove.closest('[data-pay-allowance-row]');row?.remove();if(!list.children.length)list.insertAdjacentHTML('beforeend',slPayrollAllowanceRow(catalog));recalc()}});
  ov.querySelector('#slPayrollAddAllowance').onclick=()=>{if(list.children.length>=40){toast('Maximum of 40 allowance components per employee.',true);return}list.insertAdjacentHTML('beforeend',slPayrollAllowanceRow(catalog));recalc()};
  list.addEventListener('change',e=>{if(e.target.matches('[data-allowance-category]')){const row=e.target.closest('[data-pay-allowance-row]'),hint=row.querySelector('[data-allowance-hint]');if(hint)hint.textContent=catalog[e.target.value]?.description||''}});
  form.onsubmit=async e=>{e.preventDefault();if(!form.reportValidity())return;const base=slPayrollAmount(form.elements.base_salary.value),pensionable=slPayrollAmount(form.elements.pensionable_salary.value),other=slPayrollAmount(form.elements.other_deduction_total.value),note=form.elements.statutory_classification_note.value.trim();const allowances=[];let invalid=false;
    list.querySelectorAll('[data-pay-allowance-row]').forEach((row,i)=>{const amount=slPayrollAmount(row.querySelector('[data-allowance-amount]').value);if(amount<=0)return;const treatment=row.querySelector('[data-allowance-treatment]').value;const item={category:row.querySelector('[data-allowance-category]').value,amount:amount.toFixed(2),tax_treatment:treatment,legal_basis:row.querySelector('[data-allowance-basis]')?.value.trim()||'',evidence_reference:row.querySelector('[data-allowance-evidence]')?.value.trim()||'',owner_verified:!!row.querySelector('[data-owner-verified]')?.checked};if(treatment==='EXEMPT'&&(item.legal_basis.length<20||item.evidence_reference.length<4||!item.owner_verified)){toast('Allowance '+(i+1)+' exemption requires current legal clause, evidence reference and Owner verification.',true);invalid=true}allowances.push(item)});
    if(invalid)return;if((allowances.some(a=>a.tax_treatment==='EXEMPT')||Math.abs(pensionable-base)>0.005)&&note.length<20){toast('A specific legal/contract classification note of at least 20 characters is required.',true);form.elements.statutory_classification_note.focus();return}if(pensionable>base+allowances.reduce((n,a)=>n+Number(a.amount),0)+0.001){toast('Pensionable salary cannot exceed gross pay.',true);form.elements.pensionable_salary.focus();return}
    save.disabled=true;save.textContent='Saving…';try{await api16('/hr/employees/'+encodeURIComponent(employee.id)+'/compensation',{method:'POST',body:JSON.stringify({base_salary:base.toFixed(2),allowances:allowances,pensionable_salary:pensionable.toFixed(2),other_deduction_total:other.toFixed(2),statutory_classification_note:note,effective_from:localDate()})});close();toast('Compensation saved; statutory rules and allowance evidence recorded.');await loadHR()}catch(err){toast(err.message||'Compensation save failed.',true)}finally{if(save.isConnected){save.disabled=false;save.textContent='Save compensation'}}
  };
  recalc();requestAnimationFrame(()=>form.elements.base_salary.focus());
}

function slPayrollLineEditForm(line,periodId){
  const legacy=String(line.statutory_status||'LEGACY').toUpperCase()!=='CALCULATED';
  const currentBreakdown=(()=>{try{const v=JSON.parse(line.allowance_breakdown_json||'[]');return Array.isArray(v)?v:[]}catch(_e){return[]}})();
  const minor=value=>Math.round((Number(value)||0)*100);
  const breakdownTaxable=currentBreakdown.reduce((sum,item)=>String(item.tax_treatment||'TAXABLE').toUpperCase()==='EXEMPT'?sum:sum+minor(item.amount),0);
  const breakdownExempt=currentBreakdown.reduce((sum,item)=>String(item.tax_treatment||'TAXABLE').toUpperCase()==='EXEMPT'?sum+minor(item.amount):sum,0);
  const legacyTaxableDefault=currentBreakdown.length?(breakdownTaxable/100).toFixed(2):(line.allowance_total??'0.00');
  const legacyExemptDefault=currentBreakdown.length?(breakdownExempt/100).toFixed(2):'0.00';
  return '<details class="sl-payroll-line-edit"><summary>Edit draft amounts</summary><form class="sl-payroll-line-form" data-payroll-line-form data-period-id="'+esc(periodId)+'" data-line-id="'+esc(line.id)+'">'+
    (legacy?'<div class="sl-notice sl-notice-warn">Legacy draft line: confirm the allowance split, pensionable salary and other deductions. The previous total deduction is not copied into “Other deductions” because it may already include PAYE or pension.</div>':'')+
    '<div class="sl-payroll-edit-grid">'+
      '<div class="sl-field"><label>Taxable allowances (ETB)</label><input name="taxable_allowance_total" type="number" min="0" step="0.01" inputmode="decimal" value="'+esc(legacy?legacyTaxableDefault:(line.taxable_allowance_total??line.allowance_total??'0.00'))+'" required></div>'+
      '<div class="sl-field"><label>Exempt allowances (ETB)</label><input name="exempt_allowance_total" type="number" min="0" step="0.01" inputmode="decimal" value="'+esc(legacy?legacyExemptDefault:(line.exempt_allowance_total??'0.00'))+'" readonly required><small class="sl-sub">The Owner must classify each allowance component separately. This amount is read-only in the Management draft editor.</small></div>'+
      '<div class="sl-field"><label>Pensionable salary (ETB)</label><input name="pensionable_salary" type="number" min="0" step="0.01" inputmode="decimal" value="'+esc(legacy?(line.base_salary??'0.00'):(line.pensionable_salary??line.base_salary??'0.00'))+'" required></div>'+
      '<div class="sl-field"><label>Other deductions (ETB)</label><input name="other_deduction_total" type="number" min="0" step="0.01" inputmode="decimal" value="'+esc(line.other_deduction_total??'0.00')+'" required></div>'+
      '<div class="sl-field full"><small class="sl-sub">The exemption amount is locked here. The Owner must review each allowance component and record legal basis/evidence in the separate Owner classification action.</small></div>'+
      '<div class="sl-field full"><label>Adjustment reason / notes</label><textarea name="notes" rows="2" placeholder="Keep a short reason for this draft adjustment">'+esc(line.notes||'')+'</textarea></div>'+
      '<div class="sl-field full"><button class="sl-btn primary" type="submit">SAVE & RECALCULATE LINE</button></div>'+
    '</div></form></details>';
}

function slPayrollOwnerClassificationForm(line,periodId,componentIndex,component,currentRule){
  const treatment=String(component.tax_treatment||'TAXABLE').toUpperCase()==='EXEMPT'?'EXEMPT':'TAXABLE';
  const evidenceOk=treatment==='TAXABLE'||(!!component.owner_verified&&String(component.legal_basis||'').trim().length>=20&&String(component.evidence_reference||'').trim().length>=4);
  const status=String(line.statutory_status||'').toUpperCase();
  if(status!=='CALCULATED'||!currentRule||String(line.tax_rule_version||'')!==currentRule){
    return '<div class="sl-notice sl-notice-warn">Owner classification is blocked until Management recalculates this line under the current statutory rule.</div>';
  }
  return '<details class="sl-payroll-line-edit sl-payroll-owner-classification"><summary>Owner review · '+esc(component.label||component.category||('Allowance '+(Number(componentIndex)+1)))+' <span class="sl-pill '+(evidenceOk?'ok':'warn')+'">'+(evidenceOk?(treatment==='EXEMPT'?'EXEMPT VERIFIED':'TAXABLE CLASSIFICATION'):'EVIDENCE REVIEW REQUIRED')+'</span></summary>'+
    '<form class="sl-payroll-line-form" data-payroll-classification-form data-period-id="'+esc(periodId)+'" data-line-id="'+esc(line.id)+'" data-component-index="'+esc(componentIndex)+'">'+
      '<div class="sl-payroll-owner-component"><span>Saved amount — amount cannot be changed here</span><strong>'+money(component.amount||0)+' ETB</strong></div>'+
      '<div class="sl-payroll-edit-grid">'+
        '<div class="sl-field"><label>Tax treatment</label><select name="tax_treatment" required><option value="TAXABLE" '+(treatment==='TAXABLE'?'selected':'')+'>Taxable — included in PAYE base</option><option value="EXEMPT" '+(treatment==='EXEMPT'?'selected':'')+'>Exempt — requires legal evidence</option></select></div>'+
        '<div class="sl-field full"><label>Legal/contract basis or reason for classification</label><textarea name="statutory_classification_note" minlength="20" required>'+esc(component.legal_basis||'')+'</textarea></div>'+
        '<div class="sl-field full"><label>Evidence reference (required for EXEMPT)</label><input name="exemption_evidence_reference" value="'+esc(component.evidence_reference||'')+'" placeholder="Document, contract or legal evidence reference"></div>'+
        '<label class="sl-payroll-owner-check"><input type="checkbox" name="owner_confirmed_classification" value="1" required><span>As Owner, I have reviewed this component and confirm its selected tax treatment.</span></label>'+
        '<div class="sl-field full"><button class="sl-btn primary" type="submit">SAVE OWNER CLASSIFICATION</button></div>'+
      '</div></form></details>';
}

function renderPayrollReview(period){
  document.querySelector('[data-payroll-review-overlay]')?.remove();
  const caps=state.boot.user.capabilities||{};
  const status=String(period.status||'UNKNOWN').toUpperCase();
  const lines=Array.isArray(period.lines)?period.lines:[];
  const canEdit=status==='DRAFT'&&!!caps.payroll_manage;
  const canOwnerClassify=status==='DRAFT'&&!!caps.payroll_approve;
  const canApprove=status==='SUBMITTED'&&!!caps.payroll_approve;
  const canPay=status==='APPROVED'&&!!caps.payroll_approve;
  const currentRule=String(state.hr?.statutory_rules?.rule_version||'');
  const allLinesCalculated=lines.length>0&&!!currentRule&&lines.every(line=>String(line.statutory_status||'').toUpperCase()==='CALCULATED'&&String(line.tax_rule_version||'')===currentRule);
  const rows=lines.map(line=>{
    const breakdown=(()=>{try{const v=JSON.parse(line.allowance_breakdown_json||'[]');return Array.isArray(v)?v:[]}catch(_e){return[]}})();
    const parts=breakdown.map(x=>{
      const type=String(x.tax_treatment||'TAXABLE').toUpperCase();
      const verified=type!=='EXEMPT'||(!!x.owner_verified&&String(x.legal_basis||'').trim().length>=20&&String(x.evidence_reference||'').trim().length>=4);
      return '<span class="sl-payroll-component">'+esc(x.label||x.category||'Allowance')+' · '+money(x.amount||0)+' '+esc(type)+(type==='EXEMPT'?' · '+(verified?'OWNER VERIFIED':'REVIEW REQUIRED'):'')+'</span>';
    }).join('');
    const ownerForms=canOwnerClassify?breakdown.map((component,index)=>slPayrollOwnerClassificationForm(line,period.id,index,component,currentRule)).join(''):'';
    return '<article class="sl-payroll-review-line"><div class="sl-payroll-review-line-head"><div><b>'+esc(line.employee_name||'Employee')+'</b><small>'+esc(line.employee_code||'')+' · '+esc(line.branch_location_id||'No branch')+'</small></div><div class="sl-payroll-line-net"><span>NET PAY</span><strong>'+money(line.net_pay||0)+'</strong></div></div>'+
      '<div class="sl-payroll-review-metrics">'+
        '<div><span>Base</span><b>'+money(line.base_salary||0)+'</b></div>'+
        '<div><span>Gross</span><b>'+money(line.gross_pay||0)+'</b></div>'+
        '<div><span>Taxable income</span><b>'+money(line.taxable_income||0)+'</b></div>'+
        '<div><span>PAYE</span><b>'+money(line.income_tax||0)+'</b></div>'+
        '<div><span>Employee pension</span><b>'+money(line.employee_pension||0)+'</b></div>'+
        '<div><span>Employer pension</span><b>'+money(line.employer_pension||0)+'</b></div>'+
        '<div><span>Other deductions</span><b>'+money(line.other_deduction_total||0)+'</b></div>'+
        '<div><span>Rule</span><b>'+esc(line.tax_rule_version||line.statutory_status||'Legacy review required')+'</b></div>'+
      '</div>'+
      '<div class="sl-payroll-review-allowances"><span>Taxable allowances '+money(line.taxable_allowance_total||0)+'</span><span>Exempt allowances '+money(line.exempt_allowance_total||0)+'</span></div>'+
      (parts?'<details class="sl-payroll-components"><summary>Allowance components ('+breakdown.length+')</summary>'+parts+'</details>':'<p class="sl-sub">No allowance components are present in this payroll snapshot.</p>')+
      (canEdit?slPayrollLineEditForm(line,period.id):'')+
      (canOwnerClassify?(breakdown.length?ownerForms:'<div class="sl-notice sl-notice-warn">This line has no classified allowance component. Add or correct compensation in the employee’s approved compensation setup before generating a new draft.</div>'):'')+
    '</article>';
  }).join('');
  let actionHtml='';
  if(canEdit&&allLinesCalculated){
    actionHtml='<button type="button" class="sl-btn primary" data-pay-review-action="SUBMIT" data-pay-id="'+esc(period.id)+'">SUBMIT FOR OWNER APPROVAL</button>';
  }else if(canEdit&&lines.length){
    actionHtml='<p class="sl-notice sl-notice-warn">Recalculate every legacy or older-rule line before submission. Each line must use '+esc(currentRule)+' and show CALCULATED status. No journal is posted by this review.</p>';
  }else if(canApprove){
    actionHtml='<button type="button" class="sl-btn primary" data-pay-review-action="APPROVE" data-pay-id="'+esc(period.id)+'">OWNER APPROVE & POST GL</button>';
  }else if(canPay){
    actionHtml='<button type="button" class="sl-btn gold" data-pay-review-action="PAY_BANK" data-pay-id="'+esc(period.id)+'">RECORD BANK PAYMENT</button> <button type="button" class="sl-btn" data-pay-review-action="PAY_CASH" data-pay-id="'+esc(period.id)+'">RECORD CASH PAYMENT</button>';
  }else if(status==='DRAFT'&&caps.payroll_approve){
    actionHtml='<p class="sl-sub">Owner may verify each allowance classification in this draft. Management remains responsible for submitting the period for independent approval.</p>';
  }else if(status==='DRAFT'&&canEdit&&!lines.length){
    actionHtml='<p class="sl-sub">Generate the payroll lines from approved employee compensation before review or submission.</p>';
  }else if(status==='SUBMITTED'){
    actionHtml='<p class="sl-sub">Awaiting the independent Owner decision. This view does not post a journal.</p>';
  }else if(status==='APPROVED'){
    actionHtml='<p class="sl-sub">Approved; awaiting the Owner to record the actual bank or cash payment.</p>';
  }else if(status==='PAID'){
    actionHtml='<p class="sl-success-text">Payment has been recorded. This period is read-only.</p>';
  }
  const overlay=document.createElement('div');
  overlay.className='sl-overlay';
  overlay.setAttribute('data-payroll-review-overlay','');
  overlay.innerHTML='<div class="sl-payroll-review" role="dialog" aria-modal="true" aria-labelledby="slPayrollReviewTitle">'+
    '<div class="sl-payroll-review-head"><div><div class="sl-eyebrow">MONTHLY PAYROLL REVIEW</div><h2 id="slPayrollReviewTitle">'+esc(period.period_key||'Payroll period')+'</h2><p>'+esc(period.start_date||'')+' → '+esc(period.end_date||'')+' · '+esc(period.employee_count??lines.length)+' employee line(s)</p></div><div class="sl-payroll-review-head-actions"><span class="sl-pill '+(status==='PAID'?'ok':status==='DRAFT'?'warn':'info')+'">'+esc(status)+'</span><button type="button" class="sl-icon-btn" data-payroll-review-close aria-label="Close payroll review">×</button></div></div>'+
    '<div class="sl-payroll-review-body">'+
      '<div class="sl-payroll-summary-grid">'+
        '<div class="sl-payroll-summary-card"><span>Gross pay</span><strong>'+money(period.gross_total||0)+'</strong></div>'+
        '<div class="sl-payroll-summary-card"><span>PAYE</span><strong>'+money(period.income_tax_total||0)+'</strong></div>'+
        '<div class="sl-payroll-summary-card"><span>Employee pension</span><strong>'+money(period.employee_pension_total||0)+'</strong></div>'+
        '<div class="sl-payroll-summary-card"><span>Employer pension</span><strong>'+money(period.employer_pension_total||0)+'</strong></div>'+
        '<div class="sl-payroll-summary-card"><span>Other deductions</span><strong>'+money(period.other_deduction_total||0)+'</strong></div>'+
        '<div class="sl-payroll-summary-card emphasis"><span>Net paid to employees</span><strong>'+money(period.net_total||0)+'</strong></div>'+
      '</div>'+
      '<div class="sl-payroll-rule-banner"><b>Calculation and posting guard</b><span>Rule version is stored on each line. The server recalculates draft adjustments. Only the Owner may approve/post the payroll journal or record payment; submitted, approved and paid periods cannot be edited.</span></div>'+
      (lines.length?'<div class="sl-payroll-review-lines">'+rows+'</div>':'<div class="sl-empty-rich"><b>No payroll lines yet.</b><span>Use Generate Payroll after employee compensation is active and statutory classifications are complete.</span></div>')+
      '<div class="sl-payroll-review-footer"><div class="sl-sub">Status changes are audited. Cancel closes this view without changing payroll.</div><div class="sl-action-row"><button type="button" class="sl-btn" data-payroll-review-close>Close</button>'+actionHtml+'</div></div>'+
    '</div></div>';
  document.body.appendChild(overlay);
  const close=()=>overlay.remove();
  overlay.querySelectorAll('[data-payroll-review-close]').forEach(b=>b.addEventListener('click',close));
  overlay.addEventListener('click',e=>{if(e.target===overlay)close()});
  overlay.querySelectorAll('[data-payroll-line-form]').forEach(form=>form.addEventListener('submit',async e=>{
    e.preventDefault();
    if(!form.reportValidity())return;
    const button=form.querySelector('button[type="submit"]');
    if(button){button.disabled=true;button.textContent='Recalculating…'}
    const payload=Object.fromEntries(new FormData(form));
    payload.owner_confirmed_exemption=!!form.elements.namedItem('owner_confirmed_exemption')?.checked;
    try{
      const out=await api16('/hr/payroll/'+encodeURIComponent(form.dataset.periodId)+'/line/'+encodeURIComponent(form.dataset.lineId),{method:'POST',body:JSON.stringify(payload)});
      toast('Draft line recalculated and audited. Review the updated totals before submission.');
      await loadHR();
      await load();
      renderPayrollReview(out);
    }catch(e2){
      toast(e2.message,true);
      if(button){button.disabled=false;button.textContent='SAVE & RECALCULATE LINE'}
    }
  }));
  overlay.querySelectorAll('[data-payroll-classification-form]').forEach(form=>{
    const treatmentField=form.elements.namedItem('tax_treatment');
    const evidenceField=form.elements.namedItem('exemption_evidence_reference');
    const syncEvidence=()=>{if(evidenceField&&treatmentField)evidenceField.required=treatmentField.value==='EXEMPT';};
    treatmentField?.addEventListener('change',syncEvidence);
    syncEvidence();
    form.addEventListener('submit',async e=>{
      e.preventDefault();
      if(!form.reportValidity())return;
      const payload=Object.fromEntries(new FormData(form));
      payload.component_index=Number(form.dataset.componentIndex);
      payload.owner_confirmed_classification=!!form.elements.namedItem('owner_confirmed_classification')?.checked;
      const button=form.querySelector('button[type="submit"]');
      if(button){button.disabled=true;button.textContent='Saving Owner classification…';}
      try{
        const out=await api16('/hr/payroll/'+encodeURIComponent(form.dataset.periodId)+'/line/'+encodeURIComponent(form.dataset.lineId)+'/classification',{method:'POST',body:JSON.stringify(payload)});
        toast('Owner classification saved and audited. All other allowance components were preserved.');
        await loadHR();
        await load();
        renderPayrollReview(out);
      }catch(err){
        toast(err.message||'Owner classification failed; no classification was saved.',true);
        if(button){button.disabled=false;button.textContent='SAVE OWNER CLASSIFICATION';}
      }
    });
  });
  overlay.querySelectorAll('[data-pay-review-action]').forEach(button=>button.addEventListener('click',async()=>{
    const action=button.dataset.payReviewAction,id=button.dataset.payId;
    button.disabled=true;button.textContent='Processing…';
    try{
      let out;
      if(action==='SUBMIT')out=await api16('/hr/payroll/'+encodeURIComponent(id)+'/submit',{method:'POST',body:'{}'});
      else if(action==='APPROVE')out=await api16('/hr/payroll/'+encodeURIComponent(id)+'/approve',{method:'POST',body:'{}'});
      else out=await api16('/hr/payroll/'+encodeURIComponent(id)+'/pay',{method:'POST',body:JSON.stringify({method:action==='PAY_BANK'?'BANK':'CASH'})});
      toast(action==='SUBMIT'?'Payroll submitted for independent Owner approval.':action==='APPROVE'?'Owner approved payroll and the GL journal was posted.':'Payroll payment recorded and audited.');
      await loadHR();
      await load();
      renderPayrollReview(out);
    }catch(e2){
      toast(e2.message,true);
      button.disabled=false;
      button.textContent=action==='SUBMIT'?'SUBMIT FOR OWNER APPROVAL':action==='APPROVE'?'OWNER APPROVE & POST GL':action==='PAY_BANK'?'RECORD BANK PAYMENT':'RECORD CASH PAYMENT';
    }
  }));
}
async function openPayrollReview(id){
  try{
    const period=await api16('/hr/payroll/'+encodeURIComponent(id));
    if(!period||!Array.isArray(period.lines))throw new Error('The payroll detail response was incomplete. No approval or payment action was taken.');
    renderPayrollReview(period);
  }catch(e){toast('Payroll review could not be loaded. '+e.message,true)}
}
function renderPayrollTable(d,c){
  const pe=document.getElementById('hrPayroll');
  if(!pe)return;
  const periods=Array.isArray(d.payroll)?d.payroll:[];
  if(!periods.length){
    pe.innerHTML='<div class="sl-empty-rich"><b>No payroll periods yet.</b><span>Management can create a draft for the current calendar month above.</span></div>';
    return;
  }
  const rows=periods.map(p=>{
    const status=String(p.status||'UNKNOWN').toUpperCase();
    let action='';
    if(status==='DRAFT'&&Number(p.employee_count||0)===0){
      action=c.payroll_manage?'<button class="sl-btn primary" data-pay-action="GENERATE" data-pay-id="'+esc(p.id)+'">GENERATE PAYROLL</button>':'<span class="sl-sub">Awaiting Management generation</span>';
    }else if(status==='DRAFT'){
      action=c.payroll_manage?'<button class="sl-btn primary" data-pay-review="'+esc(p.id)+'">REVIEW / EDIT DRAFT</button>':'<span class="sl-sub">Draft review unavailable</span>';
    }else if(status==='SUBMITTED'){
      action='<button class="sl-btn primary" data-pay-review="'+esc(p.id)+'">'+(c.payroll_approve?'REVIEW FOR OWNER APPROVAL':'VIEW SUBMITTED PAYROLL')+'</button>';
    }else if(status==='APPROVED'){
      action='<button class="sl-btn gold" data-pay-review="'+esc(p.id)+'">'+(c.payroll_approve?'REVIEW & RECORD PAYMENT':'VIEW APPROVED PAYROLL')+'</button>';
    }else{
      action='<button class="sl-btn" data-pay-review="'+esc(p.id)+'">VIEW PAYROLL</button>';
    }
    return '<tr><td data-label="Period"><b>'+esc(p.period_key)+'</b><div class="sl-sub">'+esc(p.start_date)+' → '+esc(p.end_date)+'</div></td>'+
      '<td data-label="Status"><span class="sl-pill '+(status==='PAID'?'ok':status==='DRAFT'?'warn':'info')+'">'+esc(status)+'</span></td>'+
      '<td data-label="Employees">'+esc(p.employee_count||0)+'</td>'+
      '<td data-label="Payroll total"><strong>Net '+money(p.net_total||0)+'</strong><div class="sl-sub">Gross '+money(p.gross_total||0)+'</div></td>'+
      '<td data-label="Deductions / employer cost"><div class="sl-sub">PAYE '+money(p.income_tax_total||0)+' · Employee pension '+money(p.employee_pension_total||0)+'</div><div class="sl-sub">Other '+money(p.other_deduction_total||0)+' · Employer pension '+money(p.employer_pension_total||0)+'</div></td>'+
      '<td data-label="Next step">'+action+'</td></tr>';
  }).join('');
  pe.innerHTML='<div class="sl-table-wrap sl-payroll-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Period</th><th>Status</th><th>Employees</th><th>Payroll total</th><th>Deductions / employer cost</th><th>Next step</th></tr></thead><tbody>'+rows+'</tbody></table></div>';
}
async function loadHR(){const page=document.getElementById('hrPage');if(!page)return;try{const d=await api16('/hr/bootstrap');state.hr=d;const c=state.boot.user.capabilities||{};const ee=document.getElementById('hrEmployees');if(ee)ee.innerHTML=(d.employees||[]).length?'<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Employee</th><th>Branch</th><th>Position</th><th>Status</th>'+ ((c.payroll_manage||c.payroll_approve)?'<th>Salary components / statutory status</th>':'')+'<th>Action</th></tr></thead><tbody>'+(d.employees||[]).map(e=>'<tr><td><b>'+esc(e.employee_id)+'</b><div class="sl-sub">'+esc(e.full_name)+'</div></td><td>'+esc(e.location_name||'Unassigned')+'</td><td>'+esc(e.position_title||'')+'</td><td><span class="sl-pill '+(e.status==='ACTIVE'?'ok':'warn')+'">'+esc(e.status)+'</span></td>'+((c.payroll_manage||c.payroll_approve)?'<td>'+money(e.current_base_salary)+' / '+money(e.current_allowance_total)+' / '+money(e.current_deduction_total)+'<div class="sl-sub">Tax class: '+esc(e.current_statutory_classification_status||'REVIEW_REQUIRED')+'</div><div class="sl-sub">Taxable '+money(e.current_taxable_allowance_total||0)+' · Exempt '+money(e.current_exempt_allowance_total||0)+'</div>'+'</td>':'')+'<td>'+(e.status==='PENDING'&&state.boot.user.role==='owner'&&!!c.hr_manage?'<button class="sl-btn primary" data-hr-employee-approve="'+esc(e.id)+'">OWNER APPROVE EMPLOYEE</button> ':'')+(String(e.employment_type||'').toUpperCase()==='RETAINER_SERVICE'?'<span class="sl-sub">Service contract — outside employee PAYE payroll; follow approved contract/payment SOP</span>':(c.hr_manage?'<button class="sl-btn" data-hr-comp="'+esc(e.id)+'" data-hr-name="'+esc(e.full_name)+'">SET COMPENSATION</button>':'<span class="sl-sub">View only</span>'))+'</td></tr>').join('')+'</tbody></table></div>':'<div class="sl-empty-rich"><b>No employee records yet.</b><span>Management can create the first employee from the HR panel.</span></div>';if(c.payroll_manage||c.payroll_approve){renderPayrollTable(d,c);}document.querySelectorAll('[data-hr-comp]').forEach(b=>b.onclick=()=>openCompensationEditor(b));document.querySelectorAll('[data-hr-employee-approve]').forEach(b=>b.onclick=async()=>{const id=b.dataset.hrEmployeeApprove;if(!id)return;b.disabled=true;try{await api16('/hr/employees/'+encodeURIComponent(id)+'/decision',{method:'POST',body:JSON.stringify({decision:'APPROVE'})});toast('Employee file approved and activated');await loadHR()}catch(e){toast(e.message,true)}finally{b.disabled=false}});document.querySelectorAll('[data-pay-review]').forEach(b=>b.onclick=()=>openPayrollReview(b.dataset.payReview));
document.querySelectorAll('[data-pay-action]').forEach(b=>b.onclick=async()=>{const a=b.dataset.payAction,id=b.dataset.payId;b.disabled=true;try{let out;if(a==='GENERATE')out=await api16('/hr/payroll/'+encodeURIComponent(id)+'/generate',{method:'POST',body:'{}'});else throw new Error('Unsupported payroll action. Open the period review to continue.');if(a==='GENERATE'&&out&&Array.isArray(out.excluded_service_contracts)&&out.excluded_service_contracts.length){toast('Payroll generated; '+out.excluded_service_contracts.length+' service contract(s) were excluded from employee PAYE payroll. Use the approved contract/payment process for them.')}else{toast('Payroll lines generated. Review employee-level calculations before submission.')}await loadHR();await load();await openPayrollReview(id)}catch(e){toast(e.message,true)}finally{b.disabled=false}});const ef=document.getElementById('hrEmployeeForm');if(ef)ef.onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;const d=Object.fromEntries(new FormData(form));try{await api16('/hr/employees',{method:'POST',body:JSON.stringify(d)});toast('Employee file created and sent to Owner for approval; it is not active yet');form.reset();form.closest('details')?.removeAttribute('open');await loadHR()}catch(x){toast(x.message,true)}};const pf=document.getElementById('hrPayrollPeriodForm');if(pf){pf.onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;if(!form.reportValidity())return;const d=Object.fromEntries(new FormData(form));const month=String(d.payroll_month||'');if(!/^\d{4}-(0[1-9]|1[0-2])$/.test(month)){toast('Choose a valid payroll month.',true);return}const monthParts=month.split('-').map(Number);const lastDay=String(new Date(monthParts[0],monthParts[1],0).getDate()).padStart(2,'0');d.period_key=month;d.start_date=month+'-01';d.end_date=month+'-'+lastDay;delete d.payroll_month;try{await api16('/hr/payroll',{method:'POST',body:JSON.stringify(d)});toast('Draft payroll period created. Generate its lines, review them, then submit for independent Owner approval.');form.reset();const monthField=form.elements.namedItem('payroll_month');if(monthField)monthField.value=localDate().slice(0,7);form.closest('details')?.removeAttribute('open');await loadHR()}catch(x){toast(x.message,true)}}}}catch(e){toast(e.message,true)}}
async function loadBranchOperations(filters={}){
    const el=document.getElementById('branchOpsPage');if(!el)return;
    const prior=state.branchOpsFilters||{};
    const next={date_from:String(filters.date_from??prior.date_from??localDate()),date_to:String(filters.date_to??prior.date_to??localDate()),category:String(filters.category??prior.category??''),location_id:Number(filters.location_id??prior.location_id??0),page:1};
    state.branchOpsFilters=next;
    el.innerHTML='<div class="sl-empty">Loading scoped branch operations and server-calculated expense totals…</div>';
    try{
        const qs=new URLSearchParams({report:'1',page:'1',per_page:'30',date_from:next.date_from,date_to:next.date_to});
        if(next.category)qs.set('category',next.category);
        if(next.location_id)qs.set('location_id',String(next.location_id));
        const results=await Promise.all([api('/accounting/customers'),api('/accounting/expenses?'+qs.toString())]);
        if(!results[1]||!Array.isArray(results[1].rows)||!results[1].summary)throw new Error('The expense report returned an incomplete response. No partial totals were displayed.');
        state.branchOps={customers:Array.isArray(results[0])?results[0]:[],expenseReport:results[1],filters:next};
        el.innerHTML=branchOperationsBody();bindBranchOperations();
    }catch(e){el.innerHTML='<div class="sl-notice sl-danger-block"><b>Branch operations / daily cost report could not be loaded safely.</b> '+esc(e.message)+'<div style="margin-top:10px"><button class="sl-btn" type="button" id="retryBranchOps">Retry</button></div></div>';document.getElementById('retryBranchOps')?.addEventListener('click',()=>loadBranchOperations(state.branchOpsFilters||{}));}
}
async function loadMoreBranchCosts(){
    const d=state.branchOps||{},report=d.expenseReport||{},filters=d.filters||{};
    if(!report.has_more)return;
    const page=Number(report.page||1)+1;
    const qs=new URLSearchParams({report:'1',page:String(page),per_page:'30',date_from:String(filters.date_from||''),date_to:String(filters.date_to||'')});
    if(filters.category)qs.set('category',filters.category);
    if(Number(filters.location_id||0))qs.set('location_id',String(filters.location_id));
    const button=document.getElementById('loadMoreBranchCosts');if(button){button.disabled=true;button.textContent='Loading…';}
    try{
        const next=await api('/accounting/expenses?'+qs.toString());
        if(!next||!Array.isArray(next.rows)||!next.summary)throw new Error('The server did not return a complete next page.');
        next.rows=(report.rows||[]).concat(next.rows);
        state.branchOps.expenseReport=next;
        const el=document.getElementById('branchOpsPage');if(el){el.innerHTML=branchOperationsBody();bindBranchOperations();}
    }catch(e){toast('More expense rows could not be loaded. '+String(e.message||''),true);if(button){button.disabled=false;button.textContent='Load more rows';}}
}
function branchOperationsBody(){
    const d=state.branchOps||{customers:[],expenseReport:{rows:[],summary:{},page:1,total:0,has_more:false},filters:{}};
    const customers=d.customers||[],report=d.expenseReport||{},expenses=report.rows||[],summary=report.summary||{},filters=d.filters||{};
    const customerSearch=state.globalSearchSelection?.type==='customer'?String(state.globalSearchSelection.query||'').trim().toLowerCase():'';
    const customerRows=customerSearch?customers.filter(x=>[x.customer_name,x.customer_code].some(v=>String(v||'').toLowerCase().includes(customerSearch))):customers;
    const canAll=!!state.boot?.user?.can_all;
    const locations=(state.boot?.locations||[]).filter(l=>Number(l.is_active)!==0&&['Branch','Warehouse'].includes(String(l.location_type||'')));
    const locationOptions=canAll?'<option value="0" '+(!Number(filters.location_id||0)?'selected':'')+'>All authorized locations</option>'+locations.map(l=>'<option value="'+esc(l.id)+'" '+(Number(filters.location_id)===Number(l.id)?'selected':'')+'>'+esc(l.location_name)+'</option>').join(''):'';
    const statusPill=s=>{s=String(s||'UNKNOWN').toUpperCase();return '<span class="sl-pill '+(s==='POSTED'?'ok':s==='REJECTED'?'bad':'warn')+'">'+esc(s)+'</span>';};
    const expenseRows=expenses.map(x=>'<tr><td data-label="Date">'+esc(x.expense_date||'')+'</td><td data-label="Location">'+esc(x.location_name||'Corporate')+'</td><td data-label="Category">'+esc(x.category||'')+'</td><td data-label="Description">'+esc(x.description||'')+'</td><td data-label="Total ETB">'+money(Number(x.amount||0)+Number(x.tax_amount||0))+'</td><td data-label="Status">'+statusPill(x.status)+'</td></tr>').join('')||'<tr><td colspan="6"><div class="sl-empty">No expenses match the selected filters.</div></td></tr>';
    return '<div class="sl-grid">'+card('Customers',customerSearch?customerRows.length+' / '+customers.length:customers.length,customerSearch?'Matching header-search records':'Operational registration list')+card('Posted cost',money(summary.posted_total||0),String(summary.posted_count||0)+' posted records')+card('Awaiting authority',money(summary.pending_total||0),String(summary.pending_count||0)+' pending records')+card('Rejected',money(summary.rejected_total||0),String(summary.rejected_count||0)+' rejected records')+'</div>'+
    '<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">DAILY COST REPORT</div><h3>Branch / warehouse expenses</h3><small>Totals are calculated server-side across all matching records; the table is paginated. Pending expenses are not treated as posted costs.</small></div><span class="sl-pill info">'+esc(report.total||0)+' records</span></div><form id="branchCostReportFilters" class="sl-form"><div class="sl-field"><label for="branchCostFrom">From date</label><input id="branchCostFrom" name="date_from" type="date" value="'+esc(filters.date_from||localDate())+'" required></div><div class="sl-field"><label for="branchCostTo">To date</label><input id="branchCostTo" name="date_to" type="date" value="'+esc(filters.date_to||localDate())+'" required></div><div class="sl-field"><label for="branchCostCategory">Cost category</label><select id="branchCostCategory" name="category"><option value="">All categories</option>'+['Transport','Labor','Customer refreshments','Packing material','Other'].map(x=>'<option value="'+esc(x)+'" '+(filters.category===x?'selected':'')+'>'+esc(x)+'</option>').join('')+'</select></div>'+(canAll?'<div class="sl-field"><label for="branchCostLocation">Location</label><select id="branchCostLocation" name="location_id">'+locationOptions+'</select></div>':'')+'<div class="sl-field full"><button class="sl-btn primary" type="submit">Run cost report</button></div></form><div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Date</th><th>Location</th><th>Category</th><th>Description</th><th>Total ETB</th><th>Status</th></tr></thead><tbody>'+expenseRows+'</tbody></table></div>'+(report.has_more?'<div class="sl-action-row" style="margin-top:12px"><button class="sl-btn" id="loadMoreBranchCosts" type="button">Load more rows ('+esc(expenses.length)+' of '+esc(report.total||0)+')</button></div>':'')+'</div>'+
    '<div class="sl-two"><div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">CUSTOMER REGISTRATION</div><h3>Register a branch customer</h3></div><small>Identity/contact only. Credit and opening balances remain management-controlled.</small></div><form id="branchCustomerForm" class="sl-form"><div class="sl-field"><label for="branchCustomerName">Customer name</label><input id="branchCustomerName" name="customer_name" required></div><div class="sl-field"><label for="branchCustomerType">Customer type</label><select id="branchCustomerType" name="customer_type"><option value="RETAIL">Retail</option><option value="WHOLESALE">Wholesale</option><option value="CORPORATE">Corporate</option><option value="ONLINE">Online / Reseller</option><option value="OTHER">Other</option></select></div><div class="sl-field"><label for="branchCustomerPhone">Phone</label><input id="branchCustomerPhone" name="phone"></div><div class="sl-field"><label for="branchCustomerEmail">Email</label><input id="branchCustomerEmail" name="email" type="email"></div><div class="sl-field"><label for="branchCustomerAddress">Address</label><input id="branchCustomerAddress" name="address"></div><div class="sl-field full"><button class="sl-btn primary" type="submit">REGISTER CUSTOMER</button></div></form></div>'+
    '<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">DAILY BRANCH EXPENSE</div><h3>Record an operational expense</h3></div><small>Branch Manager is the final authority for assigned-branch expenses. A creator cannot approve their own record; unresolved Owner-origin or self-created expenses stay pending until the approved SOP defines an independent authority.</small></div><form id="branchExpenseForm" class="sl-form"><input type="hidden" name="request_key" value="'+esc(newRequestKey())+'"><div class="sl-field"><label for="branchExpenseDate">Date</label><input id="branchExpenseDate" name="expense_date" type="date" value="'+esc(localDate())+'" required></div><div class="sl-field"><label for="branchExpenseCategory">Category</label><select id="branchExpenseCategory" name="category" required><option value="">Select category</option><option value="Transport">Transport</option><option value="Labor">Labor</option><option value="Customer refreshments">Customer refreshments</option><option value="Packing material">Packing material</option><option value="Other">Other</option></select></div><div class="sl-field"><label for="branchExpenseDescription">Description</label><input id="branchExpenseDescription" name="description" required></div><div class="sl-field"><label for="branchExpenseAmount">Amount before tax (ETB)</label><input id="branchExpenseAmount" name="amount" type="number" min="0.01" step="0.01" inputmode="decimal" required></div><div class="sl-field"><label for="branchExpenseTax">Input VAT (ETB)</label><input id="branchExpenseTax" name="tax_amount" type="number" min="0" step="0.01" inputmode="decimal" value="0"></div><div class="sl-field"><label for="branchExpenseMethod">Payment method</label><select id="branchExpenseMethod" name="payment_method"><option value="CASH">Cash</option><option value="BANK">Bank</option></select></div><div class="sl-field full"><button class="sl-btn primary" type="submit">SAVE EXPENSE FOR AUTHORITY REVIEW</button></div></form><div class="sl-notice">An expense submission is not a ledger posting. Only a different authorized final decision-maker may approve it; unresolved authority stays pending and unposted.</div></div></div>'+
    '<div class="sl-panel"><div class="sl-block-head"><h3>Recent customers'+(customerSearch?' · filtered':'')+'</h3>'+(customerSearch?'<button class="sl-btn sl-btn-sm" type="button" data-clear-global-search>Clear header search</button>':'')+'<button class="sl-btn sl-btn-sm" id="refreshBranchOps">Refresh</button></div><div class="sl-table-wrap"><table class="sl-table"><thead><tr><th>Customer</th><th>Type</th><th>Phone</th><th>Email</th></tr></thead><tbody>'+((customerRows.slice(0,30).map(x=>'<tr><td>'+esc(x.customer_name||'')+'</td><td>'+esc(x.customer_type||'')+'</td><td>'+esc(x.phone||'—')+'</td><td>'+esc(x.email||'—')+'</td></tr>').join(''))||'<tr><td colspan="4">'+(customerSearch?'No customer matches the selected header search.':'No registered customers.')+'</td></tr>')+'</tbody></table></div></div>';
}
function branchOperations(){return '<div id="branchOpsPage">'+title('Branch Operations','Customer registration and a paginated daily cost report, with authority and ledger status clearly separated.')+'<div class="sl-empty">Loading branch operations…</div></div>';}
function bindBranchOperations(){
    const refresh=document.getElementById('refreshBranchOps');if(refresh&&!refresh.__bound){refresh.__bound=1;refresh.onclick=()=>loadBranchOperations(state.branchOpsFilters||{});}
    const retry=document.getElementById('retryBranchOps');if(retry&&!retry.__bound){retry.__bound=1;retry.onclick=()=>loadBranchOperations(state.branchOpsFilters||{});}
    const filter=document.getElementById('branchCostReportFilters');if(filter&&!filter.__bound){filter.__bound=1;filter.onsubmit=e=>{e.preventDefault();const v=Object.fromEntries(new FormData(filter));loadBranchOperations({date_from:v.date_from,date_to:v.date_to,category:v.category,location_id:v.location_id||0});};}
    const more=document.getElementById('loadMoreBranchCosts');if(more&&!more.__bound){more.__bound=1;more.onclick=loadMoreBranchCosts;}
    const cf=document.getElementById('branchCustomerForm');if(cf&&!cf.__bound){cf.__bound=1;cf.onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;const btn=form.querySelector('button[type="submit"]');if(btn)btn.disabled=true;try{const d=Object.fromEntries(new FormData(form));d.opening_balance=0;d.credit_limit=0;await api('/accounting/customers',{method:'POST',body:JSON.stringify(d)});toast('Customer registered and saved');form.reset();await loadBranchOperations(state.branchOpsFilters||{});}catch(err){toast(err.message,true);}finally{if(btn)btn.disabled=false;}};}
    const ef=document.getElementById('branchExpenseForm');if(ef&&!ef.__bound){ef.__bound=1;ef.onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;const btn=form.querySelector('button[type="submit"]');if(btn)btn.disabled=true;try{const d=Object.fromEntries(new FormData(form));d.amount=Number(d.amount);d.tax_amount=Number(d.tax_amount||0);const out=await api('/accounting/expenses',{method:'POST',body:JSON.stringify(d)});toast(out&&out.status==='POSTED'?'Expense was posted and audited.':out&&out.status==='PENDING'?'Expense saved as PENDING. The creator cannot decide their own record; no GL posting occurred.':'Expense saved; verify its server-confirmed state in the cost report.');form.reset();if(form.elements.request_key)form.elements.request_key.value=newRequestKey();const date=document.getElementById('branchExpenseDate');if(date)date.value=localDate();await loadBranchOperations(state.branchOpsFilters||{});}catch(err){toast(err.message,true);}finally{if(btn)btn.disabled=false;}};}
}


async function loadPurchaseOrders(){
    const el=document.getElementById('poActionQueue');if(!el)return;
    el.innerHTML='<div class="sl-empty">Loading purchase-order statuses…</div>';
    try{
        const rows=await api('/accounting/purchase-orders');
        if(!Array.isArray(rows))throw new Error('Purchase-order endpoint returned an unexpected response.');
        const selectedPO=state.globalSearchSelection?.type==='purchase_order'?state.globalSearchSelection:null;
        const visibleRows=selectedPO?rows.filter(p=>String(p.po_number||'').trim().toLowerCase()===String(selectedPO.query||'').trim().toLowerCase()):rows;
        if(!visibleRows.length){
            if(selectedPO){el.innerHTML='<div class="sl-empty-rich"><b>The selected purchase order is not present in your authorized queue.</b><span>No procurement record was changed.</span> <button class="sl-btn sl-btn-sm" type="button" data-clear-global-search>Clear search</button></div>';return;}
            const owner=state.boot?.user?.role==='owner';el.innerHTML='<div class="sl-empty-rich"><b>No purchase orders found.</b><span>'+(owner?'Management must create and submit a stock purchase before it appears in the Owner approval queue.':'Create and submit a purchase order, then refresh the queue.')+'</span></div>';return;
        }
        const labels={SUBMIT:'SUBMIT TO OWNER',APPROVE:'OWNER APPROVE',OWNER_REVIEW:'OWNER RE-REVIEW',REJECT:'REJECT',CANCEL:'CANCEL',RECEIVE:'ACCEPT RECEIVED QTY',CLOSE:'CLOSE'};
        el.innerHTML=(selectedPO?'<div class="sl-notice">Filtered to header-search result <b>'+esc(selectedPO.query)+'</b>. <button class="sl-btn sl-btn-sm" type="button" data-clear-global-search>Clear search</button></div>':'')+'<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>PO</th><th>Supplier</th><th>Location</th><th>Order date</th><th>Expected</th><th>Status</th><th>Total</th><th>Actions</th></tr></thead><tbody>'+visibleRows.map(p=>{
            const actions=(p.available_actions||[]).map(a=>'<button type="button" class="sl-btn sl-btn-sm '+(a==='RECEIVE'?'sl-btn-gold':a==='APPROVE'?'sl-btn-primary':'')+'" data-po-action="'+esc(a)+'" data-po-id="'+esc(p.id)+'">'+esc(labels[a]||a)+'</button>').join(' ');
            return '<tr><td data-label="PO"><b>'+esc(p.po_number)+'</b><div class="sl-sub">'+esc(String(p.notes||'').replace(/^\[\[SLPOREQ:[A-Fa-f0-9-]{16,80}:[a-f0-9]{64}\]\]\s*/i,''))+'</div></td><td data-label="Supplier">'+esc(p.supplier_name||'')+'</td><td data-label="Location">'+esc(p.location_name||'')+'</td><td data-label="Order date">'+esc(p.order_date||'')+'</td><td data-label="Expected">'+esc(p.expected_date||'—')+'</td><td data-label="Status"><span class="sl-pill '+(['RECEIVED','CLOSED'].includes(p.status)?'ok':['REJECTED','CANCELLED'].includes(p.status)?'bad':'info')+'">'+esc(p.status||'')+'</span></td><td data-label="Total">'+(p.total_amount!==undefined?money(p.total_amount):'Restricted')+'</td><td data-label="Actions"><div class="sl-action-row"><button type="button" class="sl-btn sl-btn-sm" data-po-action="VIEW" data-po-id="'+esc(p.id)+'">DETAILS</button>'+actions+'</div></td></tr>';
        }).join('')+'</tbody></table></div>';
        el.querySelectorAll('[data-po-action]').forEach(b=>b.addEventListener('click',async()=>{
            const id=Number(b.dataset.poId),action=String(b.dataset.poAction||'VIEW');if(!id)return;
            if(action==='VIEW'){await openPODetail(id);return;}
            if(action==='RECEIVE'){await openPOReceiveDialog(id);return;}
            const next={SUBMIT:'SUBMITTED',APPROVE:'OWNER_APPROVED',OWNER_REVIEW:'OWNER_REVIEW',REJECT:'REJECTED',CANCEL:'CANCELLED',CLOSE:'CLOSED'}[action];if(!next)return;const expectedStatus=action==='OWNER_REVIEW'?'SUBMITTED':next;if(action==='SUBMIT'&&String(b.closest('tr')?.querySelector('[data-label="Status"]')?.textContent||'').trim()!=='DRAFT'){toast('Only a draft purchase order can be submitted to Owner approval.',true);return;}if(action==='APPROVE'&&String(b.closest('tr')?.querySelector('[data-label="Status"]')?.textContent||'').trim()!=='SUBMITTED'){toast('Owner approval is available only after Management submits the purchase order.',true);return;}
            let note='';
            if(action==='REJECT'||action==='CANCEL'||action==='OWNER_REVIEW'){const prompt=action==='REJECT'?'Purchase-order rejection reason':action==='OWNER_REVIEW'?'Why is this legacy PO being re-reviewed by the Owner?':'Purchase-order cancellation reason';note=await slReasonDialog(prompt,'');if(note===null)return;if((action==='REJECT'||action==='OWNER_REVIEW'||(action==='CANCEL'&&String(b.closest('tr')?.querySelector('[data-label="Status"]')?.textContent||'').trim()!=='DRAFT'))&&!String(note).trim()){toast('A reason is required for this action.',true);return;}}
            b.disabled=true;b.setAttribute('aria-busy','true');const originalLabel=b.textContent;b.textContent=action==='SUBMIT'?'SUBMITTING…':action==='APPROVE'?'APPROVING…':'SAVING…';
            let result;
            try{
                result=await api('/accounting/purchase-orders/'+encodeURIComponent(id)+'/status',{method:'POST',body:JSON.stringify({status:next,note})});
                if(!result||String(result.action||'')!==String(next)||String(result.status||'')!==String(expectedStatus)){
                    throw new Error('The server did not confirm the expected purchase-order action/status. Refresh the queue and verify the saved state before retrying.');
                }
            }catch(e){
                toast((e&&e.message?e.message:'Purchase-order update failed')+' No success was assumed; refresh the queue to verify the saved state before retrying.',true);
                b.disabled=false;b.textContent=originalLabel;b.removeAttribute('aria-busy');return;
            }
            toast(action==='SUBMIT'?'Purchase order submitted to Owner approval. An Owner notification was recorded.':action==='APPROVE'?'Purchase order approved. Central Warehouse has been notified to receive the goods.':action==='OWNER_REVIEW'?'Legacy purchase order returned to the Owner approval queue. Management was notified; no stock or ledger posting occurred.':'Purchase order updated to '+expectedStatus);
            try{await loadPurchaseOrders();}catch(refreshError){toast('The purchase-order transition is confirmed as '+expectedStatus+', but the queue could not refresh. Use Refresh Queue before taking another action. '+String(refreshError?.message||''),true);}
            try{await loadFinance(true);}catch(refreshError){toast('The purchase-order transition is confirmed as '+expectedStatus+', but Finance could not refresh. Use Refresh to verify current balances. '+String(refreshError?.message||''),true);}
            try{await loadNotifications();}catch(notificationError){toast('The purchase-order transition is confirmed as '+expectedStatus+', but notifications could not refresh. Open Notifications to verify the workflow message.',true);}
            b.removeAttribute('aria-busy');
        }));
    }catch(e){el.innerHTML='<div class="sl-notice sl-danger-block"><b>Purchase-order queue could not load.</b> '+esc(e.message)+'<div style="margin-top:10px"><button type="button" class="sl-btn" id="retryPOQueue">RETRY</button></div></div>';document.getElementById('retryPOQueue')?.addEventListener('click',loadPurchaseOrders);}
}
async function openPODetail(id){
    try{
        const p=await api('/accounting/purchase-orders/'+encodeURIComponent(id));
        const ov=document.createElement('div');ov.className='sl-overlay';
        ov.innerHTML='<div class="sl-drawer"><div class="sl-page-title"><div><div class="sl-eyebrow">PROCUREMENT RECORD</div><h2 style="font-size:21px">'+esc(p.po_number||('PO #'+id))+'</h2><p>'+esc(p.supplier_name||'')+' • '+esc(p.location_name||'')+' • '+esc(p.status||'')+'</p></div><button type="button" class="sl-btn" id="closePODetail">Close</button></div><div class="sl-grid">'+card('Status',p.status||'—','Owner approval and warehouse receiving state')+card('Ordered',money(p.total_amount||0),'Gross order value')+card('Items',(p.items||[]).length,'Stockable SKU lines')+'</div><div class="sl-table-wrap"><table class="sl-table"><thead><tr><th>SKU</th><th>Description</th><th>Ordered</th><th>Received</th><th>Remaining</th><th>Unit cost</th></tr></thead><tbody>'+((p.items||[]).map(x=>'<tr><td>'+esc(x.sku)+'</td><td>'+esc(x.description||'')+'</td><td>'+esc(x.quantity_ordered)+'</td><td>'+esc(x.quantity_received||0)+'</td><td>'+Math.max(0,Number(x.quantity_ordered||0)-Number(x.quantity_received||0))+'</td><td>'+(x.unit_cost!==undefined?money(x.unit_cost):'Restricted')+'</td></tr>').join('')||'<tr><td colspan="6">No PO line items returned.</td></tr>')+'</tbody></table></div></div>';
        document.body.appendChild(ov);ov.onclick=e=>{if(e.target===ov)ov.remove();};document.getElementById('closePODetail').onclick=()=>ov.remove();
    }catch(e){toast(e.message,true);}
}
async function openPOReceiveDialog(id){
    let p;try{p=await api('/accounting/purchase-orders/'+encodeURIComponent(id));}catch(e){toast(e.message,true);return;}
    if(!['OWNER_APPROVED','PARTIALLY_RECEIVED'].includes(String(p.status||''))){toast('Only an Owner-approved or Partially Received purchase order can be received.',true);return;}
    const items=(p.items||[]).map(x=>{const remaining=Math.max(0,Number(x.quantity_ordered||0)-Number(x.quantity_received||0));return {...x,remaining};}).filter(x=>x.remaining>0);
    if(!items.length){toast('No remaining quantity is available to receive for this purchase order.',true);return;}
    const requestKey=newRequestKey();const ov=document.createElement('div');ov.className='sl-overlay';
    ov.innerHTML='<div class="sl-drawer"><div class="sl-page-title"><div><div class="sl-eyebrow">GOODS RECEIPT NOTE</div><h2 style="font-size:21px">Receive '+esc(p.po_number||('PO #'+id))+'</h2><p>Enter physical quantities counted at delivery. Only these confirmed lines will update stock.</p></div><button type="button" class="sl-btn" id="cancelPOReceive">Cancel</button></div><div class="sl-notice">Supplier: <b>'+esc(p.supplier_name||'')+'</b> • Destination: <b>'+esc(p.location_name||'')+'</b>. Received quantity cannot exceed the open PO balance.</div><form id="poReceiveForm" class="sl-form"><div class="sl-field"><label>Receipt date</label><input type="date" name="receipt_date" value="'+esc(localDate())+'" required></div><div class="sl-field"><label>Receiving note (optional)</label><input name="notes" maxlength="240"></div><div class="full sl-table-wrap"><table class="sl-table"><thead><tr><th>SKU</th><th>Description</th><th>Ordered</th><th>Already received</th><th>Remaining</th><th>Actual received now</th></tr></thead><tbody>'+items.map(x=>'<tr><td>'+esc(x.sku)+'</td><td>'+esc(x.description||'')+'</td><td>'+esc(x.quantity_ordered)+'</td><td>'+esc(x.quantity_received||0)+'</td><td>'+x.remaining+'</td><td><input type="number" min="0" max="'+x.remaining+'" step="0.01" value="0" data-po-qty="'+esc(x.id)+'" aria-label="Actual quantity received for '+esc(x.sku)+'" style="width:100px"></td></tr>').join('')+'</tbody></table></div><div class="sl-field full"><button type="submit" class="sl-btn sl-btn-gold">CONFIRM RECEIPT & POST STOCK</button></div></form></div>';
    document.body.appendChild(ov);ov.onclick=e=>{if(e.target===ov)ov.remove();};document.getElementById('cancelPOReceive').onclick=()=>ov.remove();
    const form=document.getElementById('poReceiveForm');form.onsubmit=async e=>{
        e.preventDefault();if(form.dataset.busy==='1')return;form.dataset.busy='1';const btn=form.querySelector('[type="submit"]');if(btn){btn.disabled=true;btn.textContent='POSTING RECEIPT…';}
        try{
            const fd=new FormData(form);const lines=[];
            form.querySelectorAll('[data-po-qty]').forEach(input=>{const qty=Number(input.value);const max=Number(input.max);if(!Number.isFinite(qty)||qty<0||qty>max)throw new Error('Received quantity for PO line '+input.dataset.poQty+' must be between zero and the remaining quantity '+max+'.');lines.push({po_item_id:Number(input.dataset.poQty),quantity:qty});});
            if(!lines.some(x=>x.quantity>0)){throw new Error('Enter a positive actual received quantity on at least one line.');}
            const result=await api('/accounting/purchase-orders/'+encodeURIComponent(id)+'/receive',{method:'POST',body:JSON.stringify({request_key:requestKey,receipt_date:String(fd.get('receipt_date')||localDate()),notes:String(fd.get('notes')||''),lines})});
            if(!result||!String(result.receipt_number||'').trim()||!String(result.status||'').trim()||(!result.replayed&&Number(result.accepted_lines||0)<=0))throw new Error('The server did not confirm a receipt number, final PO status and accepted line count. Refresh the receiving queue before retrying.');
            ov.remove();
            toast(result.replayed?'Goods Receipt '+result.receipt_number+' was already posted; no duplicate inventory movement was created.':'Goods Receipt '+result.receipt_number+' posted. Inventory and purchase-order balance updated; PO status is '+result.status+'.');
            const refreshErrors=[];
            try{await load();}catch(refreshError){refreshErrors.push('inventory');}
            try{await loadNotifications();}catch(refreshError){refreshErrors.push('notifications');}
            if(refreshErrors.length)toast('Receipt '+result.receipt_number+' was confirmed and posted, but '+refreshErrors.join(' and ')+' could not refresh. Use Refresh and verify current stock/PO balance.',true);
        }catch(e){toast(e.message,true);form.dataset.busy='0';if(btn){btn.disabled=false;btn.textContent='CONFIRM ACCEPTED QTY & POST STOCK';}}
    };
}

function finance(){
    const f=state.finance||{},c=state.boot.user.capabilities||{},role=state.boot.user.role,canPO=role==='management'&&!!c.po_create,canPay=role==='management'&&!!(c.manage_payments||c.settings),canExp=['owner','management'].includes(role)&&!!(c.manage_expenses||c.settings),canMaster=role==='management'&&!!(c.manage_suppliers||c.settings),canCustomer=role==='management'&&!!(c.manage_customers||c.settings);
    const customerSearch=state.globalSearchSelection?.type==='customer'?String(state.globalSearchSelection.query||'').trim().toLowerCase():'';
    const customerRows=(Array.isArray(f.customer_rows)?f.customer_rows:[]).filter(x=>!customerSearch||[x.customer_name,x.customer_code].some(v=>String(v||'').toLowerCase().includes(customerSearch)));
    if(['branch_manager','warehouse_manager'].includes(role)){
      const rows=(f.expense_rows||[]).slice(0,200);
      const scope=role==='branch_manager'?'Assigned branch expense authority':'Central Warehouse expense authority';
      const scopeName=role==='branch_manager'?(state.boot.user.location_name||state.boot.user.branch_name||'Assigned branch'):'Central Warehouse';
      const history=rows.length?'<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Reference / Description</th><th>Date</th><th>Status</th><th>Amount (ETB)</th></tr></thead><tbody>'+rows.map(x=>'<tr><td><b>'+esc(x.expense_number||('EXP-'+x.id))+'</b><div class="sl-sub">'+esc(x.category||'')+' · '+esc(x.description||'')+'</div></td><td>'+esc(x.expense_date||'')+'</td><td>'+esc(x.status||'')+'</td><td>'+money(Number(x.amount||0)+Number(x.tax_amount||0))+'</td></tr>').join('')+'</tbody></table></div>':'<div class="sl-empty-rich"><b>No expenses recorded for this location yet.</b><span>New submissions and decisions will appear here.</span></div>';
      return '<div id="financePage">'+title('Location Expenses','Enter expenses for your assigned operational location. Other branches and corporate expenses are not selectable.','<button class="sl-btn" id="refreshFinance">Refresh</button>')+
      '<div class="sl-notice"><b>'+esc(scope)+'</b><div>Scope: '+esc(scopeName)+'. This role can enter and make the final decision only for its assigned location. Decisions are locked, audited and journal-posted transactionally.</div></div>'+
      '<div class="sl-panel"><h3>New Expense</h3><form id="expenseFinanceForm" class="sl-form"><input type="hidden" name="request_key" value="'+esc(newRequestKey())+'"><div class="sl-field"><label for="expense-date">Expense date</label><input id="expense-date" name="expense_date" type="date" value="'+esc(localDate())+'" required></div><div class="sl-field"><label for="expense-category">Category</label><input id="expense-category" name="category" required maxlength="100"></div><div class="sl-field full"><label for="expense-description">Description</label><input id="expense-description" name="description" required maxlength="255"></div><div class="sl-field"><label for="expense-amount">Amount before tax (ETB)</label><input id="expense-amount" name="amount" type="number" min="0.01" step="0.01" required></div><div class="sl-field"><label for="expense-tax">Input VAT (ETB)</label><input id="expense-tax" name="tax_amount" type="number" min="0" step="0.01" value="0" required></div><div class="sl-field"><label for="expense-payment-method">Payment method</label><select id="expense-payment-method" name="payment_method"><option value="CASH">Cash</option><option value="BANK">Bank</option></select></div><div class="sl-field"><label for="expense-reference">Reference (optional)</label><input id="expense-reference" name="reference" maxlength="100"></div><div class="sl-field full"><button class="sl-btn primary">SUBMIT EXPENSE</button></div></form></div>'+
      (c.expense_approve?'<div class="sl-panel"><div class="sl-block-head"><h3>Final Approval Queue — '+(role==='branch_manager'?'Assigned Branch':'Central Warehouse')+'</h3><small>Approve or reject only expenses in this assigned location.</small></div><button class="sl-btn" id="refreshFinanceApprovals">Refresh Queue</button><div id="financeApprovalQueue" aria-live="polite"><div class="sl-empty">Loading pending expenses…</div></div></div>':'')+
      '<div class="sl-panel"><div class="sl-block-head"><h3>Location Expense History</h3><small>Live records in your assigned scope</small></div><div id="financeExpenseHistory" aria-live="polite">'+history+'</div></div></div>';
    }
    const expenseRows=(f.expense_rows||[]).slice(0,200);
    const expenseHistory=expenseRows.length?'<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Reference / Description</th><th>Location</th><th>Submitted by</th><th>Date</th><th>Status</th><th>Amount (ETB)</th></tr></thead><tbody>'+expenseRows.map(x=>'<tr><td><b>'+esc(x.expense_number||('EXP-'+x.id))+'</b><div class="sl-sub">'+esc(x.category||'')+' · '+esc(x.description||'')+'</div></td><td>'+esc(x.location_name||'Corporate / No branch attribution')+'</td><td>'+esc(x.created_by_name||'')+'</td><td>'+esc(x.expense_date||'')+'</td><td>'+esc(x.status||'')+'</td><td>'+money(Number(x.amount||0)+Number(x.tax_amount||0))+'</td></tr>').join('')+'</tbody></table></div>':'<div class="sl-empty-rich"><b>No expenses recorded.</b><span>New corporate expenses and location reports will appear here.</span></div>';
    const supplierOptions=(f.supplier_rows||[]).map(x=>'<option value="'+esc(x.id)+'">'+esc(x.supplier_name)+' — '+money(x.balance)+'</option>').join('');
    return '<div id="financePage">'+title('Finance & Procurement','Purchase Orders, supplier/AP, customer/AR, expenses, payments and double-entry accounting. Retail sales post through the canonical Retail POS; external POS workbooks use Daily POS Import for reconciliation.','<button class="sl-btn" id="refreshFinance">Refresh</button>')+
      '<div class="sl-grid">'+card('Accounts Payable',money(f.accounts_payable||0),'Supplier invoices less posted payments')+card('Accounts Receivable',money(f.accounts_receivable||0),'Customer invoices less posted payments')+card('Month Expenses',money(f.expenses_month||0),'Posted expenses')+card('Month Payments',money(f.payments_month||0),'Posted cash/bank payments')+'</div>'+
      '<div class="sl-notice"><b>Retail POS:</b> use the canonical Retail POS screen for direct ERP checkout. Sales Employees may also upload/stage external POS reconciliation; Branch Manager reviews/edits and RELEASES TO ERP.</div><div class="sl-panel"><div class="sl-block-head"><h3>'+(c.po_approve?'Owner Approval Queue — Stock Purchases':'Purchase Order Action Queue')+'</h3><small>Management submits procurement; only Owner final approval advances approved POs.</small></div><button class="sl-btn" id="refreshPOQueue">Refresh Queue</button><div id="poActionQueue"><div class="sl-empty">Loading…</div></div></div>'+(c.finance_approve||c.expense_approve?'<div class="sl-panel"><div class="sl-block-head"><h3>Owner Approval Queue — Finance & Expenses</h3><small>Pending expenses, supplier invoices and finance payments. Approvals are server-locked, audited and post balanced journals only after decision.</small></div><button class="sl-btn" id="refreshFinanceApprovals">Refresh Approval Queue</button><div id="financeApprovalQueue"><div class="sl-empty">Loading pending approvals…</div></div></div>':'')+'<div class="sl-two">'+
      '<div class="sl-panel"><div class="sl-block-head"><h3>Supplier Master</h3><small>Supplier balances are ledger-derived</small></div>'+(canMaster?'<form id="supplierFinanceForm" class="sl-form"><div class="sl-field"><label>Supplier name</label><input name="supplier_name" required></div><div class="sl-field"><label>Phone</label><input name="phone"></div><div class="sl-field"><label>Tax ID</label><input name="tax_id"></div><div class="sl-field"><label>Opening payable</label><input name="opening_balance" type="number" step="0.01" value="0"></div><div class="sl-field full"><button class="sl-btn primary">SAVE SUPPLIER</button></div></form>':'<div class="sl-notice">Supplier master is management-controlled.</div>')+'<div class="sl-table-wrap"><table class="sl-table"><tr><th>Supplier</th><th>Phone</th><th>Balance</th></tr>'+((f.supplier_rows||[]).slice(0,20).map(x=>'<tr><td>'+esc(x.supplier_name)+'</td><td>'+esc(x.phone||'')+'</td><td>'+money(x.balance)+'</td></tr>').join('')||'<tr><td colspan="3">No suppliers</td></tr>')+'</table></div></div>'+
      '<div class="sl-panel"><div class="sl-block-head"><h3>Customer Master'+(customerSearch?' · filtered':'')+'</h3><small>Customer balances are AR-derived</small>'+(customerSearch?'<button class="sl-btn sl-btn-sm" type="button" data-clear-global-search>Clear header search</button>':'')+'</div>'+(canCustomer?'<form id="customerFinanceForm" class="sl-form"><div class="sl-field"><label>Customer name</label><input name="customer_name" required></div><div class="sl-field"><label>Customer type</label><select name="customer_type" required><option value="RETAIL">Retail</option><option value="WHOLESALE">Wholesale</option><option value="CORPORATE">Corporate</option><option value="ONLINE">Online / Reseller</option><option value="OTHER">Other</option></select></div><div class="sl-field"><label>Phone</label><input name="phone"></div><div class="sl-field"><label>Credit limit</label><input name="credit_limit" type="number" step="0.01" value="0"></div><div class="sl-field full"><button class="sl-btn primary">SAVE CUSTOMER</button></div></form>':'')+'<div class="sl-table-wrap"><table class="sl-table"><tr><th>Customer</th><th>Type</th><th>Balance</th></tr>'+(customerRows.slice(0,20).map(x=>'<tr><td>'+esc(x.customer_name)+'</td><td>'+esc(x.customer_type)+'</td><td>'+money(x.balance)+'</td></tr>').join('')||(customerSearch?'<tr><td colspan="3">No customer matches the selected header search.</td></tr>':'<tr><td colspan="3">No customers</td></tr>'))+'</table></div></div></div>'+
      (canPO?'<div class="sl-panel"><div class="sl-block-head"><h3>Purchase Order</h3><small>Draft → Submit → Approve → Receive → Invoice → Payment</small></div><form id="poFinanceForm" class="sl-form"><input type="hidden" name="request_key" value="'+esc(newRequestKey())+'"><div class="sl-field"><label>Supplier</label><select name="supplier_id" required>'+supplierOptions+'</select></div><div class="sl-field"><label>Expected date</label><input name="expected_date" type="date"></div><div id="poFinanceLines" class="full"></div><div class="sl-field full"><button type="button" class="sl-btn" id="addPoFinanceLine">+ Add PO line</button> <button class="sl-btn primary">CREATE PURCHASE ORDER</button></div></form></div>':'')+
      (canPay||canExp?'<div class="sl-two">'+(canPay?'<div class="sl-panel"><h3>AP / AR Payment</h3><form id="paymentFinanceForm" class="sl-form"><input type="hidden" name="request_key" value="'+esc(newRequestKey())+'"><div class="sl-field"><label>Type</label><select name="payment_type" id="paymentType"><option value="SUPPLIER">Supplier / AP</option><option value="CUSTOMER">Customer / AR</option></select></div><div class="sl-field"><label>Supplier</label><select name="supplier_id" id="paymentSupplier"><option value="">Select supplier…</option>'+supplierOptions+'</select></div><div class="sl-field"><label>Customer</label><select name="customer_id" id="paymentCustomer"><option value="">Select customer…</option>'+((f.customer_rows||[]).map(x=>'<option value="'+esc(x.id)+'">'+esc(x.customer_name)+' — '+money(x.balance)+'</option>').join(''))+'</select></div><div class="sl-field"><label>Amount</label><input name="amount" type="number" min="0.01" step="0.01" required></div><div class="sl-field full" id="paymentAllocationPanel"><label>Invoice allocations</label><div id="paymentAllocationRows"><div class="sl-empty-rich"><b>Choose a supplier or customer.</b><span>Posted invoices with outstanding balances will appear here.</span></div></div><div id="paymentAllocationSummary" class="sl-sub" aria-live="polite">Supplier allocations must equal the payment amount.</div></div><div class="sl-field"><label>Method</label><select name="method"><option>CASH</option><option>BANK</option></select></div><div class="sl-field full"><div class="sl-notice"><b>Approval:</b> payments stay pending until Owner approval. Supplier payments must be allocated to posted AP invoices; customer allocations are optional for on-account receipts.</div></div><div class="sl-field full"><button class="sl-btn primary">SUBMIT PAYMENT FOR APPROVAL</button></div></form></div>':'')+(canExp?'<div class="sl-panel"><h3>Expense Management</h3><form id="expenseFinanceForm" class="sl-form"><input type="hidden" name="request_key" value="'+esc(newRequestKey())+'"><div class="sl-field full"><label>Expense scope</label><div class="sl-readonly-field">'+(role==='owner'?'Owner / Corporate expense — no branch attribution':'Management / Corporate expense — no branch attribution')+'</div></div><div class="sl-field"><label>Category</label><input name="category" required></div><div class="sl-field"><label>Description</label><input name="description" required></div><div class="sl-field"><label>Amount before tax</label><input name="amount" type="number" step="0.01" required></div><div class="sl-field"><label>Input VAT</label><input name="tax_amount" type="number" step="0.01" value="0"></div><div class="sl-field full"><button class="sl-btn primary">SUBMIT EXPENSE</button></div></form></div>':'')+'</div>':'')+
      '<div class="sl-panel"><div class="sl-block-head"><h3>Expense Report — Read Only</h3><small>Owner and Management review only; location expense decisions remain with the assigned Branch/Warehouse Manager.</small></div>'+expenseHistory+'</div>'+
      '<div class="sl-panel"><div class="sl-block-head"><h3>General Ledger / Financial Statements</h3><small>Posted double-entry journals only</small></div><div id="financeGL"><div class="sl-empty">Loading financial ledger…</div></div></div></div>';
}
function operate(){
    return title('Operations','Live inventory and warehouse work. Retail POS can be posted directly; external branch POS can still be reconciled through Daily POS CSV or Excel.','<button class="sl-btn" id="refresh">Refresh</button>')+
      '<div class="sl-grid">'+card('Retail POS','Internal + External','ERP checkout + Daily Excel reconciliation')+card('Inventory',state.boot.dashboard.stock_total||0,'Live ledger units')+card('Warehouse','Controlled','Receipts • reservations • dispatch')+card('Notifications','Live','Approval and exception workflow')+'</div>'+
      '<div class="sl-panel"><div class="sl-block-head"><h3>Retail sales control</h3><small>Use ERP POS for direct checkout or Daily POS Import for external reconciliation.</small></div><div class="sl-notice"><b>Two supported paths:</b> post a controlled ERP POS sale immediately, or upload the external branch POS workbook for staged review and manager release.</div></div>'+
      (state.boot.user.role==='warehouse_manager' && state.boot.user.capabilities.receive_warehouse?'<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">SOP-CONTROLLED EXTERNAL SUPPLY</div><h3>PO-linked goods receiving only</h3></div><small>Use the Owner-approved PO action queue. Free-form receipts cannot post inventory.</small></div><div class="sl-notice sl-notice-soft">Accept actual quantities from the approved PO lines. Any balance stays open on the original PO. Legacy manual receipts are read-only exception records.</div><div id="receiptQueue"><div class="sl-empty">Loading legacy receipt exceptions…</div></div></div>':'')+
      '<div class="sl-panel"><div class="sl-block-head"><h3>Live stock</h3><small>Physical • Reserved • Available</small></div>'+stockTable(state.boot.stock)+'</div>';
}
function pos(){
    const c=state.boot?.user?.capabilities||{};
    const selectedSale=state.globalSearchSelection?.type==='sale'?state.globalSearchSelection:null;
    if(!c.sale&&!selectedSale)return title('Retail POS','POS sales are limited to authorized Sales Employees and Branch Managers.')+'<div class="sl-panel"><div class="sl-notice sl-warning-block"><b>Access boundary:</b> your role can view operations but cannot post retail POS transactions.</div></div>';
    if(!c.sale&&selectedSale)return title('Retail Sale Lookup','Read-only record lookup. Your role cannot create or post a sale.')+'<div class="sl-notice">Selected record: <b>'+esc(selectedSale.title||selectedSale.query)+'</b> · '+esc(selectedSale.saleDate||'date unavailable')+' <button class="sl-btn sl-btn-sm" type="button" data-clear-global-search>Clear search</button></div><div id="posSalesTable"><div class="sl-empty">Loading authorized sale record…</div></div>';
    const branches=authorizedBranches(),company=!!state.boot.user.can_all,multi=company||branches.length>1;
    const branchField=multi?'<select id="posBranch" name="branch_location_id" required>'+(company?'<option value="">Select branch…</option>':'')+branches.map(l=>'<option value="'+esc(l.id)+'" '+(selectedSale&&Number(selectedSale.locationId)===Number(l.id)?'selected':'')+'>'+esc(l.location_name)+'</option>').join('')+'</select>':('<div class="sl-readonly-field">'+esc(branches[0]?.location_name||'Assigned branch')+'</div><input type="hidden" name="branch_location_id" value="'+esc(branches[0]?.id||'')+'">');
    return title('Retail POS','Atomic ERP checkout: live Product Master SKU → stock validation → sale → inventory ledger → GL → audit in one server transaction. Use Daily POS Import only for reconciliation from an external POS.', '<button class="sl-btn" type="button" data-view="daily">External Daily POS Import</button>')+(selectedSale?'<div class="sl-notice">Filtered to header-search result <b>'+esc(selectedSale.title||selectedSale.query)+'</b>. <button class="sl-btn sl-btn-sm" type="button" data-clear-global-search>Clear search</button></div>':'')+
      '<div class="sl-grid sl-grid-compact">'+card('Posting','Immediate','No OPEN cart rows are created by this screen')+card('SKU','Product Master','Only active stockable variants')+card('Stock','Server checked','Insufficient available stock blocks posting')+card('Calendar','Authoritative','Closed business dates block sales')+'</div>'+ 
      '<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">RETAIL CART</div><h3>Build and post a complete basket</h3></div><span class="sl-pill info">ATOMIC CART</span></div><form id="posCartForm" class="sl-form sl-pos-cart-form"><div class="sl-pos-cart-meta"><div class="sl-field"><label>Business date</label><input name="sale_date" type="date" value="'+esc(selectedSale?.saleDate||localDate())+'" required></div><div class="sl-field"><label>Branch</label>'+branchField+'</div><div class="sl-field"><label>VAT mode</label><select name="vat_mode"><option value="VAT_INCLUDED">VAT included</option><option value="WITHOUT_VAT">Without VAT</option></select></div><div class="sl-field"><label>External receipt no. <small>(optional)</small></label><input name="external_receipt_no" maxlength="80" placeholder="Receipt/reference"></div><div class="sl-field full"><label>Add Product Master SKU</label>'+skuPickerField('cartSku','',{label:'Add item by SKU or product name'})+'<small class="sl-sub">Select an active stockable variant. The server re-reads its current price and unit before posting.</small></div><div class="sl-field full sl-pos-cart-add-row"><button class="sl-btn" id="posCartAddLine" type="button">+ Add item to cart</button><span class="sl-sub" id="posCartSkuHint">Choose a live Product Master result, then add it to the basket.</span></div></div><div class="sl-pos-cart-items" id="posCartItems"><div class="sl-empty">Your cart is empty. Add a Product Master SKU above.</div></div><div class="sl-pos-cart-summary"><div><span>Items</span><b id="posCartLineCount">0 lines</b></div><div><span>Gross total preview</span><b id="posCartGross">ETB 0.00</b></div><div><span>VAT preview</span><b id="posCartVat">ETB 0.00</b></div><div><span>Net sales preview</span><b id="posCartNet">ETB 0.00</b></div></div><div class="sl-notice sl-notice-soft sl-pos-cart-disclaimer">Preview only. Product Master prices, VAT, stock, inventory ledger, GL and audit validations are authoritative on the server. This release supports one atomic multi-line sale with a shared VAT mode; cart discounts and split tender are not enabled.</div><div class="sl-field full sl-pos-cart-actions"><button class="sl-btn primary" type="submit" id="posCartSubmit">POST COMPLETE CART</button><button class="sl-btn" type="button" id="posCartClear">Clear cart</button><span class="sl-sub">If the request times out, the exact cart ID/payload is retained for safe retry.</span></div></form><div id="posCartResult" class="sl-pos-cart-result" aria-live="polite"></div></div>'+ 
      '<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">RECENT POSTED SALES</div><h3>Today / selected date</h3></div><button class="sl-btn sl-btn-sm" type="button" id="refreshPosSales">Refresh</button></div><div id="posSalesTable"><div class="sl-empty">Loading posted sales…</div></div></div>';
}
/* Responsive POS cart client for the atomic /sales/cart endpoint. Preview totals are never authoritative. */
function posCartSessionKey(){const u=state.boot?.user||{};return 'stockland.pos.cart.v1.'+String(u.id||u.user_id||u.user_login||u.username||'current')}
function ensurePosCartState(){
 if(state.posCart&&Array.isArray(state.posCart.lines))return state.posCart;
 let saved=null;try{saved=JSON.parse(window.sessionStorage.getItem(posCartSessionKey())||'null')}catch(_e){}
 state.posCart=saved&&Array.isArray(saved.lines)?saved:{cartId:'',lines:[],pendingPayload:null,meta:{}};
 if(!state.posCart.meta||typeof state.posCart.meta!=='object')state.posCart.meta={};
 return state.posCart;
}
function savePosCartState(){try{window.sessionStorage.setItem(posCartSessionKey(),JSON.stringify(ensurePosCartState()))}catch(_e){}}
function newPosCartId(){const b=new Uint8Array(4);if(window.crypto?.getRandomValues)window.crypto.getRandomValues(b);else for(let i=0;i<b.length;i++)b[i]=Math.floor(Math.random()*256);return ('C'+Date.now().toString(36).toUpperCase().slice(-5)+Array.from(b,x=>x.toString(16).padStart(2,'0')).join('').toUpperCase()).slice(0,14)}
function posCartDiscrete(unit){return ['', 'PCS','PC','PIECE','PIECES','EA','EACH','UNIT','UNITS'].includes(String(unit||'PCS').trim().toUpperCase())}
function normalizePosCartLineInput(quantityRaw,priceRaw,unit){
 const qText=String(quantityRaw??'').trim(),pText=String(priceRaw??'').trim();
 if(!/^\d+(?:\.\d{1,2})?$/.test(qText))throw new Error('Enter a positive quantity with no more than two decimal places.');
 const q=Number(qText);if(!Number.isFinite(q)||q<=0)throw new Error('Quantity must be greater than zero.');
 if(posCartDiscrete(unit)&&Math.abs(q-Math.round(q))>0.000001)throw new Error('This Product Master UOM counts whole pieces/units. Fractional quantity is not allowed.');
 let price=null;if(pText!==''){if(!/^\d+(?:\.\d{1,2})?$/.test(pText))throw new Error('Enter a valid line price with no more than two decimal places.');const n=Number(pText);if(!Number.isFinite(n)||n<=0)throw new Error('An overridden line price must be greater than zero.');price=Math.round(n*100)/100;}
 return {quantity_sold:q,selling_price_override:price};
}
function posCartMoney(n){return money(Math.round((Number(n)||0)*100)/100)}
function posCartMetaFromForm(f){return {sale_date:f.elements.sale_date?.value||localDate(),branch_location_id:String(f.elements.branch_location_id?.value||''),vat_mode:String(f.elements.vat_mode?.value||'VAT_INCLUDED'),external_receipt_no:String(f.elements.external_receipt_no?.value||'').trim()}}
function posCartRate(){const n=state.boot?.settings?.vat_rate;if(n===undefined||n===null||n===''||!Number.isFinite(Number(n))||Number(n)<0)return null;return Number(n)>1?Number(n)/100:Number(n)}
function posCartLineMath(line,mode,rate){
 const q=Number(line.quantity_sold)||0,p=Number(line.selling_price_override==null?line.masterPrice:line.selling_price_override)||0,base=Math.round(q*p*100)/100;
 if(rate===null)return {gross:null,vat:null,net:null};
 if(mode==='VAT_INCLUDED'){const vat=Math.round(base*rate/(1+rate)*100)/100;return {gross:base,vat,net:Math.round((base-vat)*100)/100}}
 const vat=Math.round(base*rate*100)/100;return {gross:Math.round((base+vat)*100)/100,vat,net:base};
}
function posCartTotals(lines,mode,rate){return lines.reduce((a,line)=>{const m=posCartLineMath(line,mode,rate);if(m.gross===null){a.unknown=true;return a}a.gross=Math.round((a.gross+m.gross)*100)/100;a.vat=Math.round((a.vat+m.vat)*100)/100;a.net=Math.round((a.net+m.net)*100)/100;return a},{gross:0,vat:0,net:0,unknown:false})}
function renderPosCart(){
 const f=document.getElementById('posCartForm');if(!f)return;
 const c=ensurePosCartState(),locked=!!c.pendingPayload,container=f.querySelector('#posCartItems'),rate=posCartRate(),mode=f.elements.vat_mode?.value||c.meta?.vat_mode||'VAT_INCLUDED';
 f.querySelectorAll('[name="sale_date"],[name="branch_location_id"],[name="vat_mode"],[name="external_receipt_no"]').forEach(x=>{x.disabled=locked});
 const add=f.querySelector('#posCartAddLine'),clear=f.querySelector('#posCartClear'),submit=f.querySelector('#posCartSubmit');
 if(add)add.disabled=locked;if(clear)clear.textContent=locked?'Clear after verification':'Clear cart';if(submit)submit.textContent=locked?'RETRY SAME CART':'POST COMPLETE CART';
 if(!c.lines.length){container.innerHTML='<div class="sl-empty">Your cart is empty. Search and add a Product Master SKU above.</div>'}
 else{
  container.innerHTML='<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards sl-pos-cart-table"><thead><tr><th>Product / SKU</th><th>Qty</th><th>Master ETB</th><th>Override ETB</th><th>Line total preview</th><th>Action</th></tr></thead><tbody>'+c.lines.map((l,i)=>{const m=posCartLineMath(l,mode,rate);return '<tr><td data-label="Product / SKU"><b>'+esc(l.sku)+'</b><small class="sl-pos-cart-product">'+esc(l.product_name||l.sku)+'</small><small class="sl-pos-cart-product">'+esc([l.size,l.color].filter(Boolean).join(' / '))+' · UOM '+esc(l.unit||'PCS')+'</small></td><td data-label="Qty"><input class="sl-pos-cart-cell-input" data-cart-qty="'+i+'" type="number" min="'+(posCartDiscrete(l.unit)?'1':'0.01')+'" step="'+(posCartDiscrete(l.unit)?'1':'0.01')+'" value="'+esc(l.quantity_sold)+'" '+(locked?'disabled':'')+' required></td><td data-label="Master ETB">'+posCartMoney(l.masterPrice)+'</td><td data-label="Override ETB"><input class="sl-pos-cart-cell-input" data-cart-price="'+i+'" type="number" min="0.01" step="0.01" placeholder="Use master" value="'+(l.selling_price_override==null?'':esc(l.selling_price_override))+'" '+(locked?'disabled':'')+'></td><td data-label="Line total preview"><b>'+(m.gross===null?'Server calculated':posCartMoney(m.gross))+'</b><small class="sl-pos-cart-product">'+(m.vat===null?'VAT preview unavailable':'VAT '+posCartMoney(m.vat))+'</small></td><td data-label="Action"><button class="sl-btn sl-btn-sm" type="button" data-cart-remove="'+i+'" '+(locked?'disabled':'')+'>Remove</button></td></tr>'}).join('')+'</tbody></table></div>';
 }
 const t=posCartTotals(c.lines,mode,rate);
 f.querySelector('#posCartLineCount').textContent=c.lines.length+' line'+(c.lines.length===1?'':'s');
 f.querySelector('#posCartGross').textContent=t.unknown?'Server calculated':posCartMoney(t.gross);
 f.querySelector('#posCartVat').textContent=t.unknown?'Server calculated':posCartMoney(t.vat);
 f.querySelector('#posCartNet').textContent=t.unknown?'Server calculated':posCartMoney(t.net);
 const hint=f.querySelector('#posCartSkuHint');if(hint&&locked)hint.textContent='Checkout is pending. The exact saved payload is locked for safe retry.';
 savePosCartState();
}
function updatePosCartPreview(){
 const f=document.getElementById('posCartForm');if(!f)return;const c=ensurePosCartState(),t=posCartTotals(c.lines,f.elements.vat_mode?.value||'VAT_INCLUDED',posCartRate());
 [['#posCartGross','gross'],['#posCartVat','vat'],['#posCartNet','net']].forEach(([sel,k])=>{const el=f.querySelector(sel);if(el)el.textContent=t.unknown?'Server calculated':posCartMoney(t[k])});
 f.querySelectorAll('tbody tr').forEach((tr,i)=>{const l=c.lines[i];if(!l)return;const m=posCartLineMath(l,f.elements.vat_mode?.value||'VAT_INCLUDED',posCartRate()),td=tr.querySelector('td[data-label="Line total preview"]');if(td)td.innerHTML='<b>'+(m.gross===null?'Server calculated':posCartMoney(m.gross))+'</b><small class="sl-pos-cart-product">'+(m.vat===null?'VAT preview unavailable':'VAT '+posCartMoney(m.vat))+'</small>'});
}
function addPosCartLine(){
 const f=document.getElementById('posCartForm');if(!f)return;const c=ensurePosCartState();if(c.pendingPayload){toast('This cart is pending. Retry this same cart or verify Recent Posted Sales before clearing.',true);return}
 const picker=f.querySelector('[data-sku-picker]'),hidden=picker?.querySelector('[data-sku-value]'),sku=String(hidden?.value||'').trim();let r=null;try{r=JSON.parse(picker?.dataset.selectedRecord||'null')}catch(_e){}
 if(!sku||!r||String(r.sku||'')!==sku){toast('Choose an active Product Master result before adding a line.',true);return}
 if(String(r.product_type||'SIMPLE').toUpperCase()==='STYLE'){toast('Choose a sellable size/color variant, not a style template.',true);return}
 if(c.lines.length>=50){toast('A cart can contain at most 50 distinct SKU lines.',true);return}
 const price=Number(r.retail_price);if(!Number.isFinite(price)||price<=0){toast('The Product Master SKU must have a positive retail price.',true);return}
 const existing=c.lines.find(x=>String(x.sku).toUpperCase()===sku.toUpperCase());
 if(existing)existing.quantity_sold=Math.round((Number(existing.quantity_sold)+1)*100)/100;
 else c.lines.push({sku,product_name:String(r.product_name||sku),unit:String(r.unit||'PCS'),size:String(r.size||''),color:String(r.color||''),product_type:String(r.product_type||'SIMPLE'),masterPrice:price,quantity_sold:1,selling_price_override:null});
 if(!c.cartId)c.cartId=newPosCartId();c.meta=posCartMetaFromForm(f);savePosCartState();renderPosCart();
 if(hidden){hidden.value='';hidden.dispatchEvent(new Event('input',{bubbles:true}));hidden.dispatchEvent(new Event('change',{bubbles:true}))}
 if(picker){picker.dataset.selectedRecord='';const q=picker.querySelector('[data-sku-search]'),sel=picker.querySelector('[data-sku-selected]'),clr=picker.querySelector('[data-sku-clear]');if(q)q.value='';if(sel)sel.hidden=true;if(clr)clr.hidden=true}
}
function bindPosCart(){
 const f=document.getElementById('posCartForm');if(!f||f.__slPosCartBound)return;f.__slPosCartBound=true;const c=ensurePosCartState(),m=c.meta||{};
 ['sale_date','branch_location_id','vat_mode','external_receipt_no'].forEach(k=>{const e=f.elements[k];if(e&&m[k]!==undefined&&m[k]!==null){if(k==='branch_location_id'&&e.tagName!=='SELECT'&&String(e.value)!==String(m[k]))return;if(e.tagName==='SELECT'&&m[k]&&!Array.from(e.options).some(o=>o.value===String(m[k])))return;e.value=String(m[k])}});
 f.querySelector('#posCartAddLine')?.addEventListener('click',addPosCartLine);
 f.querySelector('#posCartClear')?.addEventListener('click',()=>{const x=ensurePosCartState();if(x.pendingPayload&&!confirm('The previous response may have been lost. Check Recent Posted Sales for all cart lines before clearing this exact retry payload. Clear anyway?'))return;if(x.lines.length&&!x.pendingPayload&&!confirm('Clear all unposted cart lines?'))return;x.lines=[];x.cartId='';x.pendingPayload=null;x.meta=posCartMetaFromForm(f);const out=document.getElementById('posCartResult');if(out)out.innerHTML='';savePosCartState();renderPosCart()});
 f.addEventListener('change',e=>{const x=ensurePosCartState();if(x.pendingPayload)return;if(e.target.matches('[name="sale_date"],[name="branch_location_id"],[name="vat_mode"],[name="external_receipt_no"]')){x.meta=posCartMetaFromForm(f);savePosCartState();updatePosCartPreview()}});
 f.addEventListener('input',e=>{const x=ensurePosCartState();if(x.pendingPayload)return;if(e.target.matches('[data-cart-qty]')){const i=Number(e.target.dataset.cartQty),line=x.lines[i],q=Number(e.target.value);if(line&&Number.isFinite(q)&&q>0&&(!posCartDiscrete(line.unit)||Math.abs(q-Math.round(q))<0.000001)){line.quantity_sold=q;savePosCartState();updatePosCartPreview()}}else if(e.target.matches('[data-cart-price]')){const i=Number(e.target.dataset.cartPrice),line=x.lines[i],raw=e.target.value;if(line){if(raw==='')line.selling_price_override=null;else{const p=Number(raw);if(Number.isFinite(p)&&p>0)line.selling_price_override=Math.round(p*100)/100}savePosCartState();updatePosCartPreview()}}else if(e.target.matches('[name="sale_date"],[name="branch_location_id"],[name="vat_mode"],[name="external_receipt_no"]')){x.meta=posCartMetaFromForm(f);savePosCartState();updatePosCartPreview()}});
 f.addEventListener('click',e=>{const b=e.target.closest('[data-cart-remove]');if(!b)return;const x=ensurePosCartState();if(x.pendingPayload)return;const i=Number(b.dataset.cartRemove);if(!Number.isInteger(i)||!x.lines[i])return;x.lines.splice(i,1);if(!x.lines.length)x.cartId='';savePosCartState();renderPosCart()});
 f.addEventListener('submit',submitPOSCart);bindSkuPickers();renderPosCart();
}
async function submitPOSCart(e){
 e.preventDefault();const f=e.currentTarget,c=ensurePosCartState();if(f.dataset.busy==='1')return;f.dataset.busy='1';const b=f.querySelector('#posCartSubmit'),out=document.getElementById('posCartResult');if(b){b.disabled=true;b.textContent=c.pendingPayload?'RETRYING SAME CART…':'POSTING ALL LINES…'}
 try{
  if(!c.pendingPayload){if(!f.reportValidity())throw new Error('Correct the highlighted cart fields before checkout.');if(!c.lines.length)throw new Error('Add at least one item before checkout.');c.lines.forEach((line,i)=>{const qty=f.querySelector('[data-cart-qty="'+i+'"]'),price=f.querySelector('[data-cart-price="'+i+'"]');const normalized=normalizePosCartLineInput(qty?qty.value:'',price?price.value:'',line.unit);line.quantity_sold=normalized.quantity_sold;line.selling_price_override=normalized.selling_price_override;});const m=posCartMetaFromForm(f);if(!m.branch_location_id)throw new Error('Select an authorized branch before checkout.');if(!['VAT_INCLUDED','WITHOUT_VAT'].includes(m.vat_mode))throw new Error('Choose a VAT mode.');c.cartId=c.cartId||newPosCartId();c.meta=m;const lines=c.lines.map(l=>{const x={sku:l.sku,quantity_sold:String(l.quantity_sold)};if(l.selling_price_override!==null)x.selling_price=Number(l.selling_price_override).toFixed(2);return x});c.pendingPayload={cart_id:c.cartId,branch_location_id:Number(m.branch_location_id),sale_date:m.sale_date,vat_mode:m.vat_mode,external_receipt_no:m.external_receipt_no,lines};savePosCartState();renderPosCart()}
  let result;try{result=await api16('/sales/cart',{method:'POST',body:JSON.stringify(c.pendingPayload)})}catch(err){if(out)out.innerHTML='<div class="sl-notice sl-danger-block"><b>Cart outcome needs verification.</b> '+esc(err.message||'Request failed')+' Retry the same cart ID or inspect Recent Posted Sales before clearing it. The exact payload is retained.</div>';toast(err.message||'Cart posting failed; the saved retry payload is retained.',true);return}
  const n=c.pendingPayload.lines.length,rows=Array.isArray(result?.lines)?result.lines:[];if(String(result?.status||'').toUpperCase()!=='CLOSED'||Number(result?.line_count)!==n||rows.length!==n||rows.some(x=>String(x.posting_status||'').toUpperCase()!=='CLOSED')){const msg='The server did not verify every line CLOSED. Inspect Recent Posted Sales, then retry the same cart ID; do not begin a new cart.';if(out)out.innerHTML='<div class="sl-notice sl-danger-block"><b>Cart requires verification.</b> '+esc(msg)+'</div>';toast(msg,true);return}
  const t=result.totals||{};if(out)out.innerHTML='<div class="sl-notice"><b>Cart '+esc(result.cart_id||c.cartId)+' posted and verified.</b> '+n+' lines · Gross '+money(t.sales_total||0)+' · VAT '+money(t.vat_amount||0)+' · Net '+money(t.net_sales_excl_vat||0)+'. Inventory, GL and audit were posted in one transaction.</div>';toast('Cart posted and verified: '+String(result.cart_id||c.cartId));
  const meta=c.meta||{};c.lines=[];c.cartId='';c.pendingPayload=null;c.meta={...meta,sale_date:localDate(),external_receipt_no:''};savePosCartState();renderPosCart();const picker=f.querySelector('[data-sku-picker]');if(picker){picker.dataset.selectedRecord='';const h=picker.querySelector('[data-sku-value]'),q=picker.querySelector('[data-sku-search]'),sel=picker.querySelector('[data-sku-selected]'),clear=picker.querySelector('[data-sku-clear]');if(h)h.value='';if(q)q.value='';if(sel)sel.hidden=true;if(clear)clear.hidden=true}
  try{await loadPosSales();await load();await loadNotifications()}catch(refresh){toast('Cart posted successfully; dashboard refresh failed. Do not repost a new cart.',true)}
 }catch(err){toast(err.message||'Cart posting failed. Retry the same cart after reviewing the result.',true)}
 finally{f.dataset.busy='0';if(b){b.disabled=false;b.textContent=ensurePosCartState().pendingPayload?'RETRY SAME CART':'POST COMPLETE CART'}renderPosCart()}
}

function businessCalendarPage(){
    const can=!!state.boot?.user?.capabilities?.settings;
    if(!can)return title('Business Calendar','Management and Owner control for business-day policy.')+'<div class="sl-panel"><div class="sl-notice sl-warning-block"><b>Read-only boundary:</b> calendar changes are restricted to Management / Owner.</div><div id="calendarReadOnly"></div></div>';
    return title('Business Calendar','Authoritative date policy used by POS, Daily POS Import and Daily Closing. Default policy is every date open until Management explicitly defines weekend / holiday rules.','<button class="sl-btn" id="refreshCalendar">Refresh</button>')+
      '<div class="sl-grid sl-grid-compact"><div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">WEEKEND POLICY</div><h3>Recurring closed weekdays</h3></div></div><form id="calendarWeekendForm" class="sl-form"><div class="sl-field full"><label class="sl-check"><input type="checkbox" name="weekend_day" value="0"> Sunday</label><label class="sl-check"><input type="checkbox" name="weekend_day" value="1"> Monday</label><label class="sl-check"><input type="checkbox" name="weekend_day" value="2"> Tuesday</label><label class="sl-check"><input type="checkbox" name="weekend_day" value="3"> Wednesday</label><label class="sl-check"><input type="checkbox" name="weekend_day" value="4"> Thursday</label><label class="sl-check"><input type="checkbox" name="weekend_day" value="5"> Friday</label><label class="sl-check"><input type="checkbox" name="weekend_day" value="6"> Saturday</label></div><div class="sl-field full"><button class="sl-btn primary" type="submit">SAVE WEEKEND POLICY</button></div></form></div><div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">DATE EXCEPTION</div><h3>Holiday / open override</h3></div></div><form id="calendarDateForm" class="sl-form"><div class="sl-field"><label>Date</label><input name="date" type="date" value="'+esc(localDate())+'" required></div><div class="sl-field"><label>Status</label><select name="status"><option value="CLOSED">Closed</option><option value="OPEN">Open override</option><option value="RESET">Reset to policy</option></select></div><div class="sl-field full"><label>Label / reason</label><input name="label" maxlength="120" placeholder="Holiday, public closure, special opening…"></div><div class="sl-field full"><button class="sl-btn primary" type="submit">SAVE DATE EXCEPTION</button></div></form></div></div>'+ 
      '<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">CALENDAR VIEW</div><h3 id="calendarMonthTitle">Selected month</h3></div><div class="sl-actions"><button class="sl-btn sl-btn-sm" id="calendarPrev" type="button">←</button><button class="sl-btn sl-btn-sm" id="calendarNext" type="button">→</button></div></div><div class="sl-calendar-grid" id="businessCalendarGrid"><div class="sl-empty">Loading Business Calendar…</div></div></div>';
}

async function createRequisitionV16(e){e.preventDefault();const f=e.currentTarget;if(f.dataset.busy==='1')return;const skus=[...f.querySelectorAll('[name="reqsku[]"]')].map(x=>String(x.value||'').trim()),qty=[...f.querySelectorAll('[name="reqqty[]"]')].map(x=>Number(x.value));const items=skus.map((sku,i)=>({sku,quantity:qty[i]})).filter(x=>x.sku&&Number.isFinite(x.quantity)&&x.quantity>0);const location_id=Number(f.querySelector('[name="location_id"]')?.value||0);const note=String(f.querySelector('[name="notes"]')?.value||'').trim();if(!location_id){toast('Select an authorized branch.',true);return}if(!items.length){toast('Add at least one active Product Master SKU and quantity.',true);return}if(items.some(x=>!Number.isInteger(x.quantity))){toast('Requisition quantities must be whole units.',true);return}const btn=f.querySelector('button[type="submit"]');if(btn)btn.disabled=true;f.dataset.busy='1';const request_key=f.dataset.requestKey||(f.dataset.requestKey=newRequestKey());try{const result=await api16('/requisitions',{method:'POST',body:JSON.stringify({request_key,items,location_id,notes:note})});toast(result?.replayed?'Existing requisition recovered after retry':'Requisition submitted. No stock was moved by this request.');f.reset();const branchAfterReset=f.querySelector('[name="location_id"]');if(branchAfterReset)branchAfterReset.value=String(location_id);delete f.dataset.requestKey;const itemBox=document.getElementById('v16ReqItems');if(itemBox)itemBox.innerHTML='';addReqItemV16();refreshAllReqStockPreviews();await loadReqs();await loadNotifications()}catch(err){toast(err.message,true)}finally{f.dataset.busy='0';if(btn)btn.disabled=false}}
async function loadLocationsRegistry(){try{const rows=await api16('/organization/locations');const el=document.getElementById('locationsRegistry');if(!el)return;el.innerHTML='<div class="sl-table-wrap"><table class="sl-table"><thead><tr><th>Location</th><th>Code</th><th>Type</th><th>Status</th><th>Action</th></tr></thead><tbody>'+rows.map(r=>'<tr><td><b>'+esc(r.location_name)+'</b><div class="sl-sub">'+esc(r.role_description||'')+'</div></td><td><span class="sl-pill info">'+esc(r.branch_code||'—')+'</span></td><td>'+esc(r.location_type)+'</td><td><span class="sl-pill '+(Number(r.is_active)?'ok':'bad')+'">'+(Number(r.is_active)?'Active':'Inactive')+'</span></td><td><button class="sl-btn" data-edit-location="'+esc(r.id)+'" data-location-name="'+esc(r.location_name)+'" data-location-active="'+esc(r.is_active)+'" data-location-type="'+esc(r.location_type)+'">Edit</button></td></tr>').join('')+'</tbody></table></div>';document.querySelectorAll('[data-edit-location]').forEach(b=>b.onclick=async()=>{const edit=await slLocationDialog(b.dataset.locationName,b.dataset.locationActive);if(!edit)return;try{await api16('/organization/locations/'+encodeURIComponent(b.dataset.editLocation),{method:'POST',body:JSON.stringify({location_name:edit.name,is_active:edit.active})});toast('Organization updated');await load();}catch(e){toast(e.message,true)}})}catch(e){toast(e.message,true)}}
function bindFilePickers(){
    document.querySelectorAll('[data-file-picker]').forEach(box=>{
        if(box.__slFileBound)return;
        box.__slFileBound=1;
        const input=box.querySelector('input[type=file]'),name=box.querySelector('[data-file-name]');
        if(!input)return;
        const show=()=>{const f=input.files?.[0];if(name)name.textContent=f?(f.name+' • '+Math.max(1,Math.round(f.size/1024))+' KB'):'No file selected';box.classList.toggle('has-file',!!f)};
        input.addEventListener('change',show);
        box.addEventListener('dragover',e=>{e.preventDefault();box.classList.add('is-dragover')});
        box.addEventListener('dragleave',()=>box.classList.remove('is-dragover'));
        box.addEventListener('drop',e=>{e.preventDefault();box.classList.remove('is-dragover');if(e.dataTransfer?.files?.length){try{input.files=e.dataTransfer.files}catch(_){return}show()}});
    });
}

function positionProfileDropdown(){
    const trigger=document.getElementById('slProfileBtn'),menu=document.getElementById('slProfileMenu');
    if(!trigger||!menu||menu.hidden)return;
    const pad=10;
    const viewportWidth=Math.max(1,document.documentElement.clientWidth||window.innerWidth||1);
    const viewportHeight=Math.max(1,window.visualViewport?.height||window.innerHeight||document.documentElement.clientHeight||1);
    const width=Math.max(1,Math.min(280,viewportWidth-pad*2));
    menu.style.position='fixed';
    menu.style.width=width+'px';
    menu.style.maxWidth='calc(100vw - '+(pad*2)+'px)';
    menu.style.maxHeight=Math.max(120,viewportHeight-pad*2)+'px';
    menu.style.overflowY='auto';
    menu.style.overflowX='hidden';
    menu.style.left=pad+'px';
    menu.style.right='auto';
    menu.style.top=pad+'px';
    const rect=trigger.getBoundingClientRect();
    const menuWidth=menu.getBoundingClientRect().width||width;
    const menuHeight=Math.min(menu.scrollHeight,viewportHeight-pad*2);
    let left=rect.right-menuWidth;
    left=Math.max(pad,Math.min(left,viewportWidth-menuWidth-pad));
    let top=rect.bottom+8;
    if(top+menuHeight>viewportHeight-pad)top=rect.top-menuHeight-8;
    if(top<pad)top=Math.max(pad,Math.min(rect.bottom+8,viewportHeight-menuHeight-pad));
    top=Math.max(pad,Math.min(top,Math.max(pad,viewportHeight-menuHeight-pad)));
    menu.style.left=Math.round(left)+'px';
    menu.style.top=Math.round(top)+'px';
}
function closeProfileDropdown(restoreFocus=false){
    const trigger=document.getElementById('slProfileBtn'),menu=document.getElementById('slProfileMenu');
    if(menu)menu.hidden=true;
    if(trigger){trigger.setAttribute('aria-expanded','false');if(restoreFocus){try{trigger.focus({preventScroll:true})}catch(_e){trigger.focus()}}}
}
function bindProfileDropdown(){
    const trigger=document.getElementById('slProfileBtn'),menu=document.getElementById('slProfileMenu');
    if(!trigger||!menu||trigger.__slProfileBound)return;
    trigger.__slProfileBound=1;
    trigger.addEventListener('click',e=>{
        e.preventDefault();e.stopPropagation();
        const open=menu.hidden;
        menu.hidden=!open;
        trigger.setAttribute('aria-expanded',open?'true':'false');
        if(open){positionProfileDropdown();window.requestAnimationFrame(positionProfileDropdown);}
    });
    menu.addEventListener('click',e=>e.stopPropagation());
    menu.querySelectorAll('[data-profile-view]').forEach(b=>b.addEventListener('click',()=>{state.view=b.dataset.profileView;closeProfileDropdown();render()}));
    menu.querySelectorAll('[data-profile-action]').forEach(b=>b.addEventListener('click',()=>{const action=b.dataset.profileAction;closeProfileDropdown();if(action==='sop')openSopGuide();if(action==='theme')openThemePicker()}));
    menu.querySelectorAll('#slCommandBtn,#slSopBtn,#slThemeBtn').forEach(b=>b.addEventListener('click',()=>closeProfileDropdown()));
    if(!window.__stocklandProfileDocBound){
        window.__stocklandProfileDocBound=true;
        document.addEventListener('click',e=>{const m=document.getElementById('slProfileMenu'),t=document.getElementById('slProfileBtn');if(m&&t&&!m.hidden&&!m.contains(e.target)&&!t.contains(e.target))closeProfileDropdown()});
        document.addEventListener('keydown',e=>{const m=document.getElementById('slProfileMenu'),t=document.getElementById('slProfileBtn');if(e.key==='Escape'&&m&&t&&!m.hidden){e.preventDefault();closeProfileDropdown(true)}});
        const reposition=()=>{if(!document.getElementById('slProfileMenu')?.hidden)positionProfileDropdown()};
        window.addEventListener('resize',reposition,{passive:true});
        window.addEventListener('orientationchange',reposition,{passive:true});
        window.addEventListener('scroll',reposition,{passive:true,capture:true});
        if(window.visualViewport){window.visualViewport.addEventListener('resize',reposition,{passive:true});window.visualViewport.addEventListener('scroll',reposition,{passive:true});}
    }
}
function bindExtended(){
    bindProfileDropdown();
    bindFilePickers();
    const $=s=>document.querySelector(s);
    if($('#posCartForm')&&!$('#posCartForm').__slBound){$('#posCartForm').__slBound=1;bindPosCart();loadPosSales();}
    else if($('#posForm')&&!$('#posForm').__slBound){$('#posForm').__slBound=1;$('#posForm').onsubmit=submitPOS;bindSkuPickers();loadPosSales();}
    if(!$('#posForm')&&!$('#posCartForm')&&$('#posSalesTable'))loadPosSales();
    if($('#refreshPosSales'))$('#refreshPosSales').onclick=loadPosSales;
    if($('#businessCalendarGrid')){if(!state.calendarMonth)state.calendarMonth=localMonthStart().slice(0,7);loadBusinessCalendar(state.calendarMonth);}
    if($('#refreshCalendar'))$('#refreshCalendar').onclick=()=>loadBusinessCalendar(state.calendarMonth||localMonthStart().slice(0,7));
    if($('#calendarPrev'))$('#calendarPrev').onclick=()=>shiftCalendar(-1);
    if($('#calendarNext'))$('#calendarNext').onclick=()=>shiftCalendar(1);
    if($('#calendarWeekendForm')&&!$('#calendarWeekendForm').__slBound){$('#calendarWeekendForm').__slBound=1;$('#calendarWeekendForm').onsubmit=saveCalendarWeekends;}
    if($('#calendarDateForm')&&!$('#calendarDateForm').__slBound){$('#calendarDateForm').__slBound=1;$('#calendarDateForm').onsubmit=saveCalendarDate;}
    if($('#invSearch'))$('#invSearch').oninput=e=>{state.inventoryQuery=e.target.value;renderKeepFocus()};
    if($('#prodSearch'))$('#prodSearch').oninput=e=>{state.productQuery=e.target.value;state.catalogSearchPending=true;state.catalogRequestId++;state.catalogLoading=false;renderKeepFocus();clearTimeout(window.__stocklandProductSearch);window.__stocklandProductSearch=setTimeout(()=>loadProductCatalog(),220)};
    if($('#clearInvSearch'))$('#clearInvSearch').onclick=()=>{state.inventoryQuery='';render()};if($('#clearProdSearch'))$('#clearProdSearch').onclick=()=>{clearTimeout(window.__stocklandProductSearch);state.productQuery='';state.catalog=null;state.catalogQuery=null;state.catalogSearchPending=false;state.catalogRequestId++;state.catalogLoading=false;render()};
    if($('#refresh'))$('#refresh').onclick=()=>load();
    document.querySelectorAll('.sl-nav [data-view], [data-view]').forEach(b=>{if(!b.__slViewBound){b.__slViewBound=1;b.onclick=()=>{state.view=b.dataset.view==='dailyInventory'?'dailyreport':b.dataset.view;render()}}});
    if($('#refreshUsers'))$('#refreshUsers').onclick=()=>{loadUsers();loadLocationsRegistry()};
    if($('#userForm')&&!$('#userForm').__slBound){$('#userForm').__slBound=1;$('#userForm').addEventListener('submit',submit)}
    if($('#locationForm')&&!$('#locationForm').__slBound){$('#locationForm').__slBound=1;$('#locationForm').onsubmit=async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(e.currentTarget));try{await api16('/organization/locations',{method:'POST',body:JSON.stringify(d)});toast('Location added without code changes');e.currentTarget.reset();await load()}catch(err){toast(err.message,true)}}}
    if($('#settingsForm')&&!$('#settingsForm').__slBound){$('#settingsForm').__slBound=1;$('#settingsForm').addEventListener('submit',submit)}
    if($('#receiveForm')&&!$('#receiveForm').__slBound){$('#receiveForm').__slBound=1;$('#receiveForm').addEventListener('submit',submit)}
    if($('#closeForm')&&!$('#closeForm').__slBound){$('#closeForm').__slBound=1;$('#closeForm').addEventListener('submit',submit)}
    if($('#transferForm')&&!$('#transferForm').__slBound){$('#transferForm').__slBound=1;$('#transferForm').addEventListener('submit',submit)}
    if($('#whForm')&&!$('#whForm').__slBound){$('#whForm').__slBound=1;$('#whForm').addEventListener('submit',submit)}
    if($('#retForm')&&!$('#retForm').__slBound){$('#retForm').__slBound=1;$('#retForm').addEventListener('submit',submit)}
    if($('#supplierFinanceForm'))$('#supplierFinanceForm').onsubmit=financeSubmit;
    if($('#customerFinanceForm'))$('#customerFinanceForm').onsubmit=financeSubmit;
    if($('#poFinanceForm')){if(!document.querySelector('#poFinanceLines .sl-fin-line'))addFinanceLine('poFinanceLines','po');$('#addPoFinanceLine').onclick=()=>addFinanceLine('poFinanceLines','po');$('#poFinanceForm').onsubmit=financeSubmit}
    if($('#paymentFinanceForm')){const pf=$('#paymentFinanceForm');pf.onsubmit=financeSubmit;const pt=$('#paymentType');const ps=$('#paymentSupplier');const pc=$('#paymentCustomer');const refreshAllocations=()=>loadPaymentAllocations();const sync=()=>{const sup=pt?.value==='SUPPLIER';if(ps)ps.disabled=!sup;if(pc)pc.disabled=sup;refreshAllocations();};pt?.addEventListener('change',sync);ps?.addEventListener('change',refreshAllocations);pc?.addEventListener('change',refreshAllocations);pf.elements.amount?.addEventListener('input',()=>updatePaymentAllocationSummary(pf));sync();}if($('#expenseFinanceForm'))$('#expenseFinanceForm').onsubmit=financeSubmit;
    if($('#branchOpsPage'))loadBranchOperations();
    if($('#financePage')&&!state.financeLoaded)loadFinance();if($('#refreshFinance'))$('#refreshFinance').onclick=()=>loadFinance(true);if($('#financeApprovalQueue'))loadFinanceApprovals();if($('#refreshFinanceApprovals'))$('#refreshFinanceApprovals').onclick=loadFinanceApprovals;if($('#financeGL'))loadFinanceGL();if($('#poActionQueue'))loadPurchaseOrders();if($('#refreshPOQueue'))$('#refreshPOQueue').onclick=loadPurchaseOrders;
    if($('#dailyPosImportForm'))$('#dailyPosImportForm').onsubmit=previewDailyImport;
    if($('#productImportForm'))$('#productImportForm').onsubmit=previewProductImport;if($('#codeMapForm'))$('#codeMapForm').onsubmit=saveCodeMap;if($('#codeMapList'))loadCodeMaps();if($('#dailyImportQueue'))loadDailyImportQueue();if($('#dailyInventoryDate')){const di=document.getElementById('dailyInventoryDate');if(!di.value)di.value=localDate();di.onchange=loadDailyInventoryReport;const rb=document.getElementById('refreshDailyInventory');if(rb&&!rb.__bound){rb.__bound=1;rb.addEventListener('click',loadDailyInventoryReport)}loadDailyInventoryReport();}
    if($('#v16ReqForm')){if(!document.querySelector('#v16ReqItems .sl-req-line'))addReqItemV16();$('#addV16ReqItem').onclick=addReqItemV16;$('#v16ReqForm').onsubmit=createRequisitionV16;const branchSelect=$('#v16ReqForm [name="location_id"]');if(branchSelect&&!branchSelect.dataset.stockPreviewBound){branchSelect.dataset.stockPreviewBound='1';branchSelect.addEventListener('change',refreshAllReqStockPreviews)}}
    if($('#refreshReqs'))$('#refreshReqs').onclick=loadReqs;if($('#reqTable'))loadReqs();if($('#refreshWarehouse'))$('#refreshWarehouse').onclick=()=>{load();};if($('#warehouseReqQueue'))loadWarehouseQueue();if($('#openHeaderNotifications'))$('#openHeaderNotifications').onclick=()=>document.getElementById('notificationBell')?.click();
    if($('#usersTable')){loadUsers();loadAvailableWPUsers();}if($('#locationsRegistry'))loadLocationsRegistry();if($('#hrPage')&&!state.hrLifecycleLoaded&&!state.hrLifecycleLoading)loadHRLifecycle();if($('#refreshHR'))$('#refreshHR').onclick=()=>{if(!state.hrLifecycleLoading)loadHRLifecycle();};bindHRLifecycleForms();bindHRPayrollCycleForms();
    if($('#auditTable'))loadAudit();if($('#runHealth'))$('#runHealth').onclick=()=>runHealth('quick');if($('#runDeepHealth'))$('#runDeepHealth').onclick=()=>runHealth('deep');if($('#receiptQueue'))loadReceipts();if($('#refreshReorder'))$('#refreshReorder').onclick=loadReorder;if($('#reorderTable'))loadReorder();
    if($('#countUploadForm'))$('#countUploadForm').onsubmit=previewCount;if($('#countBatches'))loadCountBatches();if($('#refreshCountBatches'))$('#refreshCountBatches').onclick=loadCountBatches;if($('#refreshRecon'))$('#refreshRecon').onclick=()=>loadRecon();if($('#reconForm'))$('#reconForm').onsubmit=async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(e.currentTarget));d.location_id=Number(d.location_id);d.counted_qty=Number(d.counted_qty);try{await api('/app/reconciliation',{method:'POST',body:JSON.stringify(d)});toast('Variance submitted for Management approval');e.currentTarget.reset();await loadRecon()}catch(err){toast(err.message,true)}};if($('#reconTable'))loadRecon();
}

async function loadUsers(){
try{
const d=await api('/app/users');state.users=d;
const el=document.getElementById('usersTable');if(!el)return;
el.innerHTML='<div class="sl-table-wrap"><table class="sl-table"><thead><tr><th>User</th><th>Role</th><th>Authorized locations</th><th>Status</th><th></th></tr></thead><tbody>'+d.map(r=>{const names=(r.location_names||[]).join(', ');return '<tr><td><b>'+esc(r.display_name)+'</b> <span class="sl-mini">@'+esc(r.user_login)+'</span></td><td>'+esc(r.role)+'</td><td>'+esc(names||'Company-wide')+'</td><td><span class="sl-pill '+(Number(r.is_active)?'ok':'bad')+'">'+(Number(r.is_active)?'Active':'Inactive')+'</span></td><td><button class="sl-btn" data-edit-user="'+esc(r.wp_user_id)+'">Edit</button></td></tr>'}).join('')+'</tbody></table></div>';
document.querySelectorAll('[data-edit-user]').forEach(b=>b.onclick=()=>openUserEditor(b.dataset.editUser));
}catch(e){toast(e.message,true)}
}
async function loadAvailableWPUsers(){
try{
 const d=await api('/app/users/available');const el=document.getElementById('availableWpUsers');if(!el)return;
 if(!d.length){el.innerHTML='<div class="sl-empty-rich"><b>No unactivated WordPress accounts.</b><span>All listed accounts already have an active ERP profile.</span></div>';return;}
 const eligibleOwner=state.boot?.user?.role==='owner'||state.access?.is_owner_eligible||!!state.boot?.user?.can_all;
 const roleOptions='<option value="stockland_management">Management</option><option value="stockland_branch_manager">Branch Manager</option><option value="stockland_sales_employee">Sales Employee</option><option value="stockland_warehouse_manager">Warehouse Manager</option><option value="stockland_warehouse_employee">Warehouse Employee</option><option value="stockland_auditor">Auditor</option>'+(eligibleOwner?'<option value="stockland_owner">Owner</option>':'');
 const locs=(state.boot?.locations||state.access?.locations||[]).filter(l=>l.location_type==='Branch'||l.location_type==='Warehouse');
 el.innerHTML='<div class="sl-access-helper"><span class="sl-helper-icon">✓</span><div><b>One-step ERP access assignment</b><small>Select the responsibility and location, then save. No second password, WordPress user editing or approval loop.</small></div></div><div class="sl-table-wrap"><table class="sl-table sl-responsive-cards sl-access-matrix"><thead><tr><th>Account</th><th>ERP responsibility</th><th>Location / branch</th><th>Status</th><th>Action</th></tr></thead><tbody>'+d.map(u=>'<tr data-access-row="'+esc(u.ID)+'"><td data-label="Account"><b>'+esc(u.display_name||u.user_login)+'</b><div class="sl-sub">@'+esc(u.user_login)+' • '+esc(u.user_email||'')+'</div></td><td data-label="ERP responsibility"><select data-access-role="'+esc(u.ID)+'">'+roleOptions+'</select></td><td data-label="Location / branch"><select data-access-locations="'+esc(u.ID)+'" multiple size="3">'+locs.map(l=>'<option value="'+esc(l.id)+'">'+esc(l.location_name)+' • '+esc(l.location_type)+'</option>').join('')+'</select><small class="sl-sub">Select one or more where the SOP allows location scope.</small></td><td data-label="Status"><span class="sl-pill warn">PENDING</span></td><td data-label="Action"><button class="sl-btn primary sl-btn-sm" data-inline-activate="'+esc(u.ID)+'" data-activate-wp-user="'+esc(u.ID)+'">SAVE ACCESS</button></td></tr>').join('')+'</tbody></table></div>';
 document.querySelectorAll('[data-inline-activate]').forEach(b=>b.onclick=async()=>{const id=Number(b.dataset.inlineActivate);const role=document.querySelector('[data-access-role="'+CSS.escape(String(id))+'"]')?.value||'';const locSel=document.querySelector('[data-access-locations="'+CSS.escape(String(id))+'"]');const ids=[...(locSel?.querySelectorAll('option:checked')||[])].map(o=>Number(o.value));b.disabled=true;b.textContent='SAVING…';try{await api('/app/users/activate-existing',{method:'POST',body:JSON.stringify({wp_user_id:id,role,location_ids:ids,branch_location_id:ids[0]||null})});toast('ERP access assigned successfully');await loadAvailableWPUsers();await loadUsers();await load()}catch(e){toast(e.message,true);b.disabled=false;b.textContent='SAVE ACCESS'}});
}catch(e){const el=document.getElementById('availableWpUsers');if(el)el.innerHTML='<div class="sl-notice sl-danger-block">'+esc(e.message)+'</div>';}
}
async function openExistingUserActivator(id){
 let rows;try{rows=await api('/app/users/available')}catch(e){toast(e.message,true);return}
 const u=rows.find(x=>Number(x.ID)===Number(id));if(!u){toast('WordPress account is no longer available for activation.',true);return;}
 const eligibleOwner=!!(state.boot?.user?.role==='owner'||state.access?.is_owner_eligible); const roleOptions='<option value="stockland_management">Management</option><option value="stockland_branch_manager">Branch Manager</option><option value="stockland_sales_employee">Sales Employee</option><option value="stockland_warehouse_manager">Warehouse Manager</option><option value="stockland_warehouse_employee">Warehouse Employee</option><option value="stockland_auditor">Auditor</option>'+(eligibleOwner?'<option value="stockland_owner">Owner</option>':'');
 const locs=(state.boot?.locations||state.access?.locations||[]).filter(l=>l.location_type==='Branch'||l.location_type==='Warehouse');
 const ov=document.createElement('div');ov.className='sl-overlay';ov.innerHTML='<aside class="sl-drawer sl-accessible-dialog" role="dialog" aria-modal="true" aria-labelledby="slActivateUserTitle"><div class="sl-drawer-head"><div><div class="sl-eyebrow">SYSTEM ACCESS</div><h2 id="slActivateUserTitle">Assign ERP access</h2><p>No secondary approval screen. Save the assignment and the server applies the ERP role and location scope.</p></div><button class="sl-icon-btn" id="slActivateCancel" aria-label="Close">×</button></div><div class="sl-status-banner"><div><strong>'+esc(u.display_name||u.user_login)+'</strong><br><small>@'+esc(u.user_login)+' • '+esc(u.user_email||'')+'</small></div><span class="sl-pill info">WordPress account</span></div><div class="sl-form"><div class="sl-field"><label>ERP role</label><select id="slActivateRole">'+roleOptions+'</select></div><div class="sl-field"><label>Application status</label><div class="sl-readonly-field">Enabled after Save</div></div><div class="sl-field full"><label>Authorized locations <small>(first selected location becomes primary)</small></label><select id="slActivateLocations" multiple size="6">'+locs.map(l=>'<option value="'+l.id+'">'+esc(l.location_name)+' — '+esc(l.location_type)+'</option>').join('')+'</select></div><div class="sl-action-row"><button type="button" class="sl-btn" id="slActivateCancel2">Cancel</button><button type="button" class="sl-btn primary" id="slActivateConfirm">SAVE ERP ACCESS</button></div></div></aside>';
 document.body.appendChild(ov);const close=()=>ov.remove();document.getElementById('slActivateCancel').onclick=close;document.getElementById('slActivateCancel2').onclick=close;ov.onclick=e=>{if(e.target===ov)close()};
 const role=document.getElementById('slActivateRole'),locSel=document.getElementById('slActivateLocations');const syncLocState=()=>{locSel.disabled=!['stockland_branch_manager','stockland_sales_employee','stockland_warehouse_employee','stockland_warehouse_manager'].includes(role.value);};role.onchange=syncLocState;syncLocState();
 document.getElementById('slActivateConfirm').onclick=async()=>{const ids=[...locSel.querySelectorAll('option:checked')].map(o=>Number(o.value));const b=document.getElementById('slActivateConfirm');b.disabled=true;try{await api('/app/users/activate-existing',{method:'POST',body:JSON.stringify({wp_user_id:Number(u.ID),role:role.value,location_ids:ids,branch_location_id:ids[0]||null})});toast('ERP access activated from the ERP dashboard');close();await loadAvailableWPUsers();await loadUsers();await load();}catch(e){toast(e.message,true);b.disabled=false}};
}
async function loadAudit(){
try{
const d=await api('/app/audit');const el=document.getElementById('auditTable');if(!el)return;
el.innerHTML='<div class="sl-table-wrap"><table class="sl-table"><thead><tr><th>Time</th><th>User</th><th>Branch</th><th>Action</th><th>SKU</th><th>Qty</th><th>Reference</th><th>Hash</th></tr></thead><tbody>'+
d.map(r=>'<tr><td>'+esc(r.action_time)+'</td><td>'+esc(r.display_name||r.user_id)+'</td><td>'+esc(r.location_name||'Company-wide')+'</td><td>'+esc(r.action)+'</td><td>'+esc(r.sku||'')+'</td><td>'+esc(r.quantity||'')+'</td><td>'+esc(r.reference||'')+'</td><td><span class="sl-mini">'+esc((r.event_hash||'legacy').slice(0,12))+'</span></td></tr>').join('')+'</tbody></table></div>';
}catch(e){toast(e.message,true)}
}
async function runHealth(mode){
  const el=document.getElementById('healthResult');
  const deep=mode==='deep';
  if(deep&&!window.confirm('Run the full read-only integrity scan? It can take longer on shared hosting. It does not repair or change business data.'))return;
  try{
    el.innerHTML='<div class="sl-empty">'+(deep?'Running deep read-only integrity scans. Leave this page open until the result appears.':'Running quick read-only service and schema checks.')+'</div>';
    let d;
    if(deep){
      let resume=window.stocklandHealthResume&&typeof window.stocklandHealthResume==='object'?window.stocklandHealthResume:{scan_id:'',offset:0};
      let batches=0;
      while(batches<256){
        batches++;
        let requestPath='/system/health?mode=deep&scan_offset='+encodeURIComponent(String(Math.max(0,Number(resume.offset)||0)));
        if(resume.scan_id)requestPath+='&scan_id='+encodeURIComponent(String(resume.scan_id));
        d=await api16(requestPath);
        const diag=(d&&d.diagnostics)||{};
        const scan=diag.supplemental_integrity_scan||{};
        const next=Number(scan.next_offset);
        const currentOffset=Math.max(0,Number(resume.offset)||0);
        if(scan.overall_status==='SCAN_PARTIAL'&&scan.complete===false&&scan.scan_id&&Number.isFinite(next)&&next>currentOffset){
          resume={scan_id:String(scan.scan_id),offset:next};
          window.stocklandHealthResume=resume;
          el.innerHTML='<div class="sl-empty">Deep integrity review: '+esc(scan.attempted_check_count||next)+' / '+esc(scan.required_check_count||'?')+' relational checks completed. Continuing in bounded batches…</div>';
          continue;
        }
        if(scan.complete===true&&scan.scan_complete===true){delete window.stocklandHealthResume;}
        else if(scan.overall_status){delete window.stocklandHealthResume;}
        break;
      }
      if(batches>=256)throw new Error('Deep integrity scan exceeded its bounded batch limit. Resume the review from the health screen; no partial result is treated as complete.');
    }else{
      d=await api16('/system/health');
    }
    const q=d.diagnostics||{};
    const checks=d.checks||{};
    const ready=!!checks.live_ready;
    const failed=Array.isArray(d.failed_checks)?d.failed_checks:[];
    const healthScope=classifyERPHealthScope(d);
    const badge=healthScope.badge;
    const label=healthScope.label;
    const bannerText=healthScope.bannerText;
    let html='<div class="sl-live-ready-banner '+badge+'"><strong>'+label+'</strong><span>'+bannerText+'</span></div>';
    html+='<div class="sl-statline">';
    Object.keys(checks).forEach(function(k){if(k==='ok'||k==='live_ready')return;html+='<div class="sl-stat"><b>'+((checks[k])?'PASS':'FAIL')+'</b><span>'+esc(k)+'</span></div>';});
    html+='</div>';
    html+='<div class="sl-health-grid"><div><b>Core schema</b><span>'+esc(q.core_schema_present||0)+' / '+esc(q.core_schema_total||0)+'</span></div><div><b>Full ERP schema</b><span>'+esc(q.full_schema_present||0)+' / '+esc(q.full_schema_total||0)+'</span></div><div><b>REST v16 routes</b><span>'+esc(q.rest_v16_route_count||0)+'</span></div><div><b>Execution</b><span>'+esc(q.execution_model||'MANUAL_ON_DEMAND')+'</span></div><div><b>PHP</b><span>'+esc(q.php_runtime||d.php||'')+'</span></div><div><b>Database</b><span>'+esc(q.database_server||'')+'</span></div><div><b>Upload / POST</b><span>'+esc(q.upload_max_filesize||'?')+' / '+esc(q.post_max_size||'?')+'</span></div><div><b>Memory</b><span>'+esc(q.memory_limit||'')+'</span></div></div>';
    if(!ready && Array.isArray(d.failed_checks) && d.failed_checks.length) html+='<div class="sl-notice '+(healthScope.kind==='scoped-review'?'sl-notice-warn':'sl-danger-block')+'" style="margin-top:12px"><b>'+(healthScope.kind==='scoped-review'?'Targeted review items':'Failed production gates')+'</b><div style="margin-top:5px">'+esc(d.failed_checks.join(', '))+'</div></div>';
    const payrollDiag=q.payroll_compensation_classification;
    if(payrollDiag&&typeof payrollDiag==='object'&&(payrollDiag.unresolved_active_employees!==null&&payrollDiag.unresolved_active_employees!==undefined||payrollDiag.scan_truncated||payrollDiag.scan_query_failed)){
      const queryFailed=!!payrollDiag.scan_query_failed,scanTruncated=!!payrollDiag.scan_truncated;
      const unresolved=payrollDiag.unresolved_active_employees===null||payrollDiag.unresolved_active_employees===undefined?null:Number(payrollDiag.unresolved_active_employees);
      const prepared=Number(payrollDiag.safe_management_prepared_employees)||0;
      const excluded=Number(payrollDiag.excluded_retainer_service_contracts)||0;
      const reasonRows=(!queryFailed&&!scanTruncated&&unresolved!==null&&unresolved>0&&payrollDiag.unresolved_reasons&&typeof payrollDiag.unresolved_reasons==='object')?Object.entries(payrollDiag.unresolved_reasons).filter(([reason,count])=>String(reason).trim()&&Number(count)>0).sort((a,b)=>Number(b[1])-Number(a[1])).slice(0,8):[];
      const needsReview=queryFailed||scanTruncated||unresolved===null||unresolved>0;
      const summary=queryFailed?'Payroll readiness could not be evaluated because the diagnostic query failed. No records were assumed healthy.':(scanTruncated?'More than 5,000 active employee records matched; the diagnostic stopped at its safety limit and the count is unknown.':(unresolved+' active employee record(s) need compensation review.'));
      html+='<div class="sl-notice '+(needsReview?'sl-notice-warn':'')+'" style="margin-top:12px"><b>Payroll compensation readiness</b><div style="margin-top:5px">'+esc(summary)+' '+esc(prepared)+' standard Management-prepared record(s) accepted • '+esc(excluded)+' RETAINER_SERVICE contract(s) excluded from employee PAYE.</div>'+(needsReview?'<div style="margin-top:6px">Open HR & Payroll and review each affected active employee. Standard Management-prepared compensation is accepted only when all allowance amounts are taxable, exempt total is zero, and pensionable salary equals base salary. Exempt allowances or non-standard pensionable salary require Owner classification and supporting evidence. No salaries or classifications are changed by this health check.</div><div style="margin-top:8px"><button type="button" class="sl-btn" data-view="hr">OPEN HR & PAYROLL</button></div>':'<div style="margin-top:6px">The same compensation-readiness rules used by payroll generation are satisfied.</div>')+'</div>';
      if(reasonRows.length){html+='<div class="sl-notice sl-notice-soft" style="margin-top:8px"><b>Review reasons</b><ul class="sl-payroll-readiness-reasons">'+reasonRows.map(([reason,count])=>'<li>'+esc(count)+' record(s): '+esc(reason)+'</li>').join('')+'</ul><div class="sl-sub">Counts are grouped by validation reason; no employee salary or personal details are exposed here.</div></div>';}
    }
    html+='<div class="sl-notice" style="margin-top:12px">PHP '+esc(d.php)+' • WordPress '+esc(d.wordpress)+' • Plugin '+esc(d.plugin)+' • DB '+esc(d.db_version)+'</div>';
    if(d.missing_tables&&d.missing_tables.length){html+='<div class="sl-notice sl-danger-block" style="margin-top:12px"><b>Missing schema:</b> '+esc(d.missing_tables.join(', '))+'</div>';}
    const schemaDetails=q.schema_contract_details&&typeof q.schema_contract_details==='object'?q.schema_contract_details:{};
    const recognizedHrx=Array.isArray(q.recognized_hrx_extension_tables)?q.recognized_hrx_extension_tables:[];
    if(recognizedHrx.length){html+='<div class="sl-notice" style="margin-top:12px"><b>Recognized HRX extension tables</b><div style="margin-top:5px">'+esc(schemaDetails.hrx_extension_present||recognizedHrx.length)+' / '+esc(schemaDetails.hrx_extension_expected||33)+' known tables are preserved separately from the source-declared ERP application schema inventory. This release does not activate or consume the HRX extension, and recognition does not constitute architecture approval.</div><div class="sl-sub" style="margin-top:5px">Version marker: '+esc(schemaDetails.hrx_schema_version_marker||'NOT_RECORDED')+'</div><div style="margin-top:5px">'+esc(recognizedHrx.join(', '))+'</div></div>';}
    const extraSchemaTables=Array.isArray(q.unexpected_schema_tables)?q.unexpected_schema_tables:[];if(extraSchemaTables.length){html+='<div class="sl-notice sl-danger-block" style="margin-top:12px"><b>Unrecognized Stockland tables detected</b><div style="margin-top:5px">These tables are outside the ERP base and known HRX allowlists. They block exact schema acceptance and the supplemental integrity scan. Preserve them; do not delete without a reviewed migration plan.</div><div style="margin-top:5px">'+esc(extraSchemaTables.join(', '))+'</div></div>'; }
    if(q.last_fatal&&q.last_fatal.reference){html+='<div class="sl-notice sl-danger-block" style="margin-top:12px"><b>Last PHP fatal evidence</b><div style="margin-top:5px">Ref '+esc(q.last_fatal.reference)+' • '+esc(q.last_fatal.file)+':'+esc(q.last_fatal.line)+' • stage '+esc(q.last_fatal.stage||'unknown')+'</div><div style="margin-top:5px">'+esc(q.last_fatal.message||'')+'</div><div class="sl-sub" style="margin-top:5px">'+esc(q.last_fatal.request_path||'')+'</div></div>';}
    const ts=q.target_schema||{}; const tsKeys=Object.keys(ts); if(tsKeys.length){html+='<div class="sl-notice" style="margin-top:12px"><b>Target schema evidence</b><div style="margin-top:5px">'+tsKeys.map(k=>{const v=ts[k]||{};return esc(k)+': '+(v.table_present?'present':'MISSING')+(v.missing_columns&&v.missing_columns.length?' — missing '+esc(v.missing_columns.join(', ')):' ✓');}).join('<br>')+'</div></div>';}
    const integrity=q.supplemental_integrity_scan;
    if(!quickOnly && integrity && typeof integrity==='object'){
      const scanChecks=integrity.checks||{};
      const scanIssues=Object.entries(scanChecks).filter(([name,value])=>value===null || value===undefined || Number(value)!==0);
      const scanUnknown=Array.isArray(integrity.unknown_checks)?integrity.unknown_checks:[];
      const requiredCount=Number(integrity.required_check_count||Object.keys(scanChecks).length);
      const completedCount=Number(integrity.completed_check_count||0);
      const attemptedCount=Number(integrity.attempted_check_count||0);
      const scanComplete=integrity.scan_complete===true && requiredCount>0 && completedCount===requiredCount && attemptedCount===requiredCount && scanUnknown.length===0;
      const scanHealthy=!!integrity.healthy && scanComplete && scanIssues.length===0;
      const scanReceipt=integrity.scan_id?' • Scan '+esc(integrity.scan_id):'';
      html+='<div class="sl-notice '+(scanHealthy?'':'sl-danger-block')+'" style="margin-top:12px"><b>Supplemental integrity scan detail</b><div style="margin-top:5px">Status: '+esc(scanHealthy?'RELATIONAL CHECKS PASS — database FK metadata is reported separately':(integrity.overall_status||'NOT CLEAN'))+' • Read-only: '+(integrity.read_only?'yes':'not confirmed')+' • Checks completed: '+esc(completedCount)+' / '+esc(requiredCount)+' • Non-zero/unknown checks: '+esc(scanIssues.length)+scanReceipt+'</div>';
      if(scanIssues.length){html+='<div style="margin-top:8px"><b>Checks requiring review</b><ul>'+scanIssues.map(([name,value])=>'<li><code>'+esc(name)+'</code>: '+(value===null||value===undefined?'UNKNOWN — query did not return a reliable result':esc(value)+' affected row(s) or mismatch(es)')+'</li>').join('')+'</ul></div>';}
      if(scanUnknown.length){html+='<div style="margin-top:8px"><b>Unknown checks</b>: '+esc(scanUnknown.join(', '))+'</div>';}
      if(!scanIssues.length && scanHealthy){html+='<div style="margin-top:8px">Every required relational check was attempted and completed with a zero result. This is a transient read-only result, not a persisted or signed release receipt.</div>';}
      if(integrity.scan_receipt_persisted===false){html+='<div class="sl-sub" style="margin-top:6px">This scan receipt is generated for this response only; durable server-side receipt storage is not configured.</div>';}
      const fk=integrity.database_foreign_keys||{};
      if(fk.status){html+='<div style="margin-top:8px"><b>Database foreign-key metadata:</b> '+esc(fk.status)+'. '+esc(fk.note||'Metadata only; no schema changes were attempted.')+'</div>';}
      html+='</div>';
    }
    const upgradeGaps=q.schema_upgrade_gaps;
    if(setupRequired && upgradeGaps && typeof upgradeGaps==='object'){
      Object.entries(upgradeGaps).forEach(([area,gaps])=>{if(Array.isArray(gaps)&&gaps.length)html+='<div class="sl-notice sl-danger-block" style="margin-top:12px"><b>'+esc(area.toUpperCase())+' SCHEMA REPAIR ITEMS</b><div style="margin-top:5px">'+esc(gaps.join(', '))+'</div></div>';});
      html+='<div class="sl-notice sl-notice-soft" style="margin-top:12px">Use the protected setup action for the allow-listed, bounded schema repair. This diagnostic does not post transactions.</div>';
    }
    if(setupRequired){const deferred=q.deferred_checks||d.deferred_checks||[];html+='<div class="sl-notice" style="margin-top:12px"><b>Full integrity scan has not run in setup mode.</b><div style="margin-top:5px">'+esc(deferred.join(', '))+'</div></div>';}else if(quickOnly){const deferred=q.deferred_checks||d.deferred_checks||[];html+='<div class="sl-notice sl-danger-block" style="margin-top:12px"><b>Deep integrity checks pending — status UNVERIFIED</b><div style="margin-top:5px">'+esc(deferred.join(', '))+'</div><div style="margin-top:5px">This lightweight request avoids unbounded scans on shared hosting. It has not checked ledger, PLEDGER, AP/AR, GL, stock or audit-chain integrity.</div></div>';}else{html+=d.audit&&d.audit.ok?'<p class="sl-success-text">Audit chain integrity check passed.</p>':'<p class="sl-danger-text">'+esc((d.audit&&d.audit.reason)||'Audit chain integrity check failed.')+'</p>'; }
    el.innerHTML=html;
  }catch(e){
    try{
      const rt=await api16('/system/runtime');
      const f=rt.last_fatal||{}; const env=rt.environment||{}; const sc=rt.schema||{};
      let html='<div class="sl-notice sl-danger-block"><b>System Health request failed.</b><div style="margin-top:5px">'+esc(e.message)+'</div></div>';
      if(f.reference) html+='<div class="sl-notice sl-danger-block" style="margin-top:12px"><b>REAL PHP fatal captured</b><div style="margin-top:5px"><b>'+esc(f.file)+':'+esc(f.line)+'</b> • stage '+esc(f.stage||'unknown')+' • ref '+esc(f.reference)+'</div><div style="margin-top:5px">'+esc(f.message||'')+'</div><div class="sl-sub" style="margin-top:5px">'+esc(f.request_path||'')+'</div></div>';
      html+='<div class="sl-notice" style="margin-top:12px"><b>Runtime</b><div style="margin-top:5px">PHP '+esc(env.php_version||'?')+' • DB '+esc(env.db_version||'?')+' • memory '+esc(env.memory_limit||'?')+' • XLSX XMLReader::fromStream '+(env.xml_reader_from_stream?'available':'NOT AVAILABLE')+'</div></div>';
      const keys=Object.keys(sc); if(keys.length) html+='<div class="sl-notice" style="margin-top:12px"><b>Three-issue target schema</b><div style="margin-top:5px">'+keys.map(k=>{const v=sc[k]||{};return esc(k)+': '+(v.table_present?'present':'MISSING')+(v.missing_columns&&v.missing_columns.length?' — missing '+esc(v.missing_columns.join(', ')):' ✓');}).join('<br>')+'</div></div>';
      el.innerHTML=html;
    }catch(rtErr){
      el.innerHTML='<p class="sl-danger-text">'+esc(e.message)+'<br>'+esc(rtErr.message||'Runtime diagnostic endpoint also failed.')+'</p>';
    }
  }
}
function api16(path,opt={}){
    const base=A.api_v16||(A.api||'').replace(/\/v1$/,'/v16');
    let pth='/'+String(path).replace(/^\//,'');
    const isSetup=/^\/setup\/(status|advance)(?:$|\?)/.test(pth);
    const cfg=Object.assign({},opt);
    cfg.headers=Object.assign({},cfg.headers||{});
    if(!cfg.cache && (!cfg.method || String(cfg.method).toUpperCase()==='GET'))cfg.cache='no-store';
    if(isSetup){
      cfg.cache='no-store';
      if(!cfg.method || String(cfg.method).toUpperCase()==='GET'){pth+=(pth.indexOf('?')>=0?'&':'?')+'_slts='+Date.now();}
    }
    return api(pth,cfg,base);
}
function skuPickerField(fieldName,value='',opts={}){
    const safeName=String(fieldName||'sku');
    const val=String(value||'');
    const label=String(opts.label||'Live Stockland SKU');
    const placeholder=String(opts.placeholder||'Search SKU, product, color, size, barcode or GTIN…');
    state.skuPickerSeq=(state.skuPickerSeq||0)+1;
    const id='slSkuPicker_'+String(state.skuPickerSeq);
    return '<div class="sl-sku-picker" id="'+id+'" data-sku-picker data-field-name="'+esc(safeName)+'">'
        +'<input type="hidden" name="'+esc(safeName)+'" value="'+esc(val)+'" data-sku-value>'
        +'<div class="sl-sku-picker-control">'
        +'<input type="search" class="sl-sku-search" value="'+esc(val)+'" placeholder="'+esc(placeholder)+'" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="'+id+'_results" aria-label="'+esc(label)+'" data-sku-search>'
        +'<button type="button" class="sl-btn sl-btn-sm sl-sku-clear" data-sku-clear '+(val?'':'hidden')+'>Clear</button>'
        +'</div>'
        +'<div class="sl-sku-selected" data-sku-selected '+(val?'':'hidden')+'><span>Selected</span><b>'+esc(val)+'</b></div>'
        +'<div class="sl-sku-results" id="'+id+'_results" data-sku-results role="listbox" aria-label="Live Product Master matches" hidden></div>'
        +'</div>';
}
function bindSkuPickers(){
    document.querySelectorAll('[data-sku-picker]').forEach(function(picker){
        if(picker.__slBound)return;
        picker.__slBound=1;
        const input=picker.querySelector('[data-sku-search]');
        const hidden=picker.querySelector('[data-sku-value]');
        const results=picker.querySelector('[data-sku-results]');
        const clear=picker.querySelector('[data-sku-clear]');
        const selected=picker.querySelector('[data-sku-selected]');
        if(!input||!hidden||!results)return;
        let timer=0,requestSeq=0;
        const hide=()=>{results.hidden=true;input.setAttribute('aria-expanded','false')};
        const syncSelected=(sku,label,record=null)=>{
            hidden.value=String(sku||'');
            picker.dataset.selectedRecord=record?JSON.stringify(record):'';
            if(String(sku||'')){
                input.value=String(label||sku);
                if(selected){selected.hidden=false;selected.innerHTML='<span>Selected</span><b>'+esc(sku)+'</b>'}
                if(clear)clear.hidden=false;
            }else{
                if(selected)selected.hidden=true;
                if(clear)clear.hidden=true;
            }
            hidden.dispatchEvent(new Event('input',{bubbles:true}));
            hidden.dispatchEvent(new Event('change',{bubbles:true}));
        };
        const renderResults=(rows,q)=>{
            if(!Array.isArray(rows)||!rows.length){results.innerHTML='<div class="sl-sku-empty">No active stockable Product Master SKU matches '+(q?'“'+esc(q)+'”':'this list')+'.</div>';results.hidden=false;input.setAttribute('aria-expanded','true');return;}
            results.innerHTML=rows.map(function(r){
                const sku=String(r.sku||'');
                const title=String(r.product_name||'');
                const meta=[r.color||'',r.size||'',r.brand||''].filter(Boolean).join(' • ');
                const code=r.barcode||r.gtin||'';
                return '<button type="button" role="option" aria-selected="false" class="sl-sku-result" data-pick-sku="'+esc(sku)+'" data-pick-label="'+esc(sku+' — '+title)+'" data-pick-product="'+esc(title)+'" data-pick-price="'+esc(r.retail_price??'')+'" data-pick-unit="'+esc(r.unit||'PCS')+'" data-pick-size="'+esc(r.size||'')+'" data-pick-color="'+esc(r.color||'')+'" data-pick-type="'+esc(r.product_type||'SIMPLE')+'"><span><b>'+esc(sku)+'</b><small>'+esc(title+(meta?' • '+meta:''))+'</small></span><em>'+esc(code||'')+'</em></button>';
            }).join('');
            results.querySelectorAll('[data-pick-sku]').forEach(function(btn){btn.onclick=function(){syncSelected(btn.dataset.pickSku,btn.dataset.pickLabel,{sku:btn.dataset.pickSku,product_name:btn.dataset.pickProduct,retail_price:btn.dataset.pickPrice,unit:btn.dataset.pickUnit,size:btn.dataset.pickSize,color:btn.dataset.pickColor,product_type:btn.dataset.pickType});hide();}});
            results.hidden=false;input.setAttribute('aria-expanded','true');
        };
        const run=async(q)=>{
            const current=++requestSeq;
            results.hidden=false;results.innerHTML='<div class="sl-sku-loading">Loading live Product Master SKUs…</div>';input.setAttribute('aria-expanded','true');
            try{
                const rows=await api16('/sku-search?q='+encodeURIComponent(String(q||''))+'&limit=100',{cache:'no-store'});
                if(current!==requestSeq)return;
                renderResults(rows,q);
            }catch(e){
                if(current!==requestSeq)return;
                results.innerHTML='<div class="sl-sku-empty sl-sku-error">'+esc(e.message||'Live SKU search is unavailable right now.')+'</div>';results.hidden=false;input.setAttribute('aria-expanded','true');
            }
        };
        input.addEventListener('focus',function(){if(!String(input.value||'').trim()&&String(hidden.value||'').trim()){input.value=String(hidden.value);};run(String(input.value||'').trim());});
        // A tap on an already-focused picker must reopen its live results.
        input.addEventListener('click',function(){if(results.hidden)run(String(input.value||'').trim());});
        // Prevent touch browsers from blurring the input before option click runs.
        results.addEventListener('pointerdown',function(event){if(event.target.closest('[data-pick-sku]'))event.preventDefault();});
        input.addEventListener('input',function(){
            hidden.value=''; if(selected)selected.hidden=true;if(clear)clear.hidden=true;
            clearTimeout(timer);const q=String(input.value||'').trim();timer=setTimeout(()=>run(q),220);
        });
        input.addEventListener('keydown',function(e){
            if(e.key==='Escape'){hide();return;}
            if(e.key==='ArrowDown'){e.preventDefault();const first=results.querySelector('[data-pick-sku]');if(first)first.focus();}
            if(e.key==='Enter'){
                const first=results.querySelector('[data-pick-sku]');
                if(first){e.preventDefault();first.click();}
            }
        });
        input.addEventListener('blur',function(){setTimeout(function(){if(!picker.contains(document.activeElement))hide();},140);});
        if(clear)clear.onclick=function(){hidden.value='';input.value='';if(selected)selected.hidden=true;clear.hidden=true;hide();hidden.dispatchEvent(new Event('input',{bubbles:true}));hidden.dispatchEvent(new Event('change',{bubbles:true}));input.focus();run('');};
    });
}

function authorizedLocations(){
    const locs=(state.boot.locations||[]).filter(l=>Number(l.is_active)!==0);
    if(state.boot.user?.can_all)return locs;
    const ids=new Set((state.boot.user?.location_ids||[]).map(Number));
    const primary=Number(state.boot.user?.branch_location_id||0);
    return locs.filter(l=>ids.has(Number(l.id)) || Number(l.id)===primary);
}
function authorizedBranches(){return authorizedLocations().filter(l=>l.location_type==='Branch');}
function branchOptions(includeAll=false){
    const locs=authorizedBranches();
    let html=includeAll?'<option value="">Select branch…</option>':'';
    html+=locs.map(l=>'<option value="'+l.id+'">'+esc(l.location_name)+'</option>').join('');
    return html;
}
function roleLabel(r){return ({owner:'Owner',management:'Management',branch_manager:'Branch Manager',sales_employee:'Sales Employee',warehouse_manager:'Warehouse Manager',warehouse_employee:'Warehouse Employee',auditor:'Auditor'})[r]||r}
function approvalPill(decision){if(!decision)return '<span class="sl-pill warn">PENDING</span>';const d=String(decision).toUpperCase();return '<span class="sl-pill '+(d==='REJECT'?'bad':(d==='PARTIAL'?'warn':'ok'))+'">'+esc(d)+'</span>'}

function slDateDialog(titleText,initial){
  return new Promise(resolve=>{
    const ov=document.createElement('div');ov.className='sl-overlay';
    ov.innerHTML='<div class="sl-modal sl-accessible-dialog" role="dialog" aria-modal="true" aria-labelledby="slDateTitle"><div class="sl-page-title"><div><div class="sl-eyebrow">WAREHOUSE APPOINTMENT</div><h2 id="slDateTitle" style="font-size:21px">'+esc(titleText)+'</h2></div></div><div class="sl-field full"><label for="slDateInput">Pickup date</label><input id="slDateInput" type="date" value="'+esc(initial||'')+'" required></div><div class="sl-action-row"><button type="button" class="sl-btn" id="slDateCancel">Cancel</button><button type="button" class="sl-btn primary" id="slDateOk">Continue</button></div></div>';
    document.body.appendChild(ov);const input=document.getElementById('slDateInput');const close=v=>{ov.remove();resolve(v)};
    document.getElementById('slDateCancel').onclick=()=>close(null);document.getElementById('slDateOk').onclick=()=>{const v=String(input.value||'');if(!/^\d{4}-\d{2}-\d{2}$/.test(v)){input.focus();return}close(v)};ov.onclick=e=>{if(e.target===ov)close(null)};requestAnimationFrame(()=>input.focus());
  });
}
function slReasonDialog(titleText,initial){
  return new Promise(resolve=>{
    const ov=document.createElement('div');ov.className='sl-overlay';
    ov.innerHTML='<div class="sl-modal sl-accessible-dialog" role="dialog" aria-modal="true" aria-labelledby="slReasonTitle"><div class="sl-page-title"><div><div class="sl-eyebrow">REASON / COMMENT</div><h2 id="slReasonTitle" style="font-size:21px">'+esc(titleText)+'</h2></div></div><div class="sl-field full"><label for="slReasonInput">Comment</label><textarea id="slReasonInput" rows="4" required>'+esc(initial||'')+'</textarea></div><div class="sl-action-row"><button type="button" class="sl-btn" id="slReasonCancel">Cancel</button><button type="button" class="sl-btn primary" id="slReasonOk">Continue</button></div></div>';
    document.body.appendChild(ov);
    const input=document.getElementById('slReasonInput');
    const close=v=>{ov.remove();resolve(v)};
    document.getElementById('slReasonCancel').onclick=()=>close(null);
    document.getElementById('slReasonOk').onclick=()=>{const v=String(input.value||'').trim();if(!v){input.focus();return}close(v)};
    ov.onclick=e=>{if(e.target===ov)close(null)};
    requestAnimationFrame(()=>input.focus());
  });
}
function slLocationDialog(initialName,initialActive){
  return new Promise(resolve=>{
    const ov=document.createElement('div');ov.className='sl-overlay';
    ov.innerHTML='<div class="sl-modal sl-accessible-dialog" role="dialog" aria-modal="true" aria-labelledby="slLocationTitle"><div class="sl-page-title"><div><div class="sl-eyebrow">ORGANIZATION</div><h2 id="slLocationTitle" style="font-size:21px">Edit branch / location</h2></div></div><div class="sl-field full"><label for="slLocationName">Name</label><input id="slLocationName" value="'+esc(initialName||'')+'" required></div><label class="sl-check"><input id="slLocationActive" type="checkbox" '+(Number(initialActive)!==0?'checked':'')+'> Active</label><div class="sl-action-row"><button type="button" class="sl-btn" id="slLocationCancel">Cancel</button><button type="button" class="sl-btn primary" id="slLocationOk">Save</button></div></div>';
    document.body.appendChild(ov);
    const name=document.getElementById('slLocationName'),active=document.getElementById('slLocationActive');
    const close=v=>{ov.remove();resolve(v)};
    document.getElementById('slLocationCancel').onclick=()=>close(null);
    document.getElementById('slLocationOk').onclick=()=>{const v=String(name.value||'').trim();if(!v){name.focus();return}close({name:v,active:!!active.checked})};
    ov.onclick=e=>{if(e.target===ov)close(null)};
    requestAnimationFrame(()=>name.focus());
  });
}
async function refreshReqStockPreview(line){
 if(!line||!line.isConnected)return;
 const out=line.querySelector('[data-req-stock-preview]');
 if(!out)return;
 const sku=String(line.querySelector('[name="reqsku[]"]')?.value||'').trim();
 const quantity=Math.max(0,Number(line.querySelector('[name="reqqty[]"]')?.value||0));
 const loc=Number(document.querySelector('#v16ReqForm [name="location_id"]')?.value||0);
 const seq=(Number(line.dataset.stockPreviewSeq||0)+1);
 line.dataset.stockPreviewSeq=String(seq);
 if(!sku){out.innerHTML='<div class="sl-notice sl-notice-soft">Select an active Product Master SKU to see branch stock and available warehouse sources.</div>';return}
 if(!loc){out.innerHTML='<div class="sl-notice sl-notice-soft">Select an authorized branch to check live stock.</div>';return}
 out.innerHTML='<div class="sl-notice sl-notice-soft">Checking live branch stock and current sources…</div>';
 try{
  const rows=await api('/stock?location_id='+encodeURIComponent(loc)+'&sku='+encodeURIComponent(sku),{cache:'no-store'});
  if(!line.isConnected||Number(line.dataset.stockPreviewSeq)!==seq)return;
  if(!Array.isArray(rows))throw new Error('Branch stock endpoint returned an unexpected response.');
  const row=rows.find(x=>Number(x.location_id)===loc&&String(x.sku||'')===sku);
  if(!row)throw new Error('No authoritative branch stock row was returned for this SKU. Refresh; a missing row is not assumed to mean zero stock.');
  const physical=Number(row.physical_stock||0);
  const reserved=Number(row.reserved_stock||0);
  const available=Math.max(0,Number(row.available_stock??(physical-reserved)));
  const shortfall=Math.max(0,quantity-available);
  const shortage=shortfall>0?'<div class="sl-sub"><b>Branch gap:</b> '+esc(shortfall)+' unit(s) above the branch’s available balance. This does not block a request.</div>':'<div class="sl-sub">'+(quantity>0?'The branch balance covers the requested quantity.':'Enter a quantity to calculate any branch shortfall.')+'</div>';
  let sourceHtml='';
  try{
   const response=await api16('/requisitions/source-preview/availability?location_id='+encodeURIComponent(loc)+'&sku='+encodeURIComponent(sku)+'&quantity='+encodeURIComponent(quantity||1),{cache:'no-store'});
   if(!line.isConnected||Number(line.dataset.stockPreviewSeq)!==seq)return;
   const source=response?.sourcing||null;
   if(source&&source.status==='SOURCING_UNAVAILABLE'){
    sourceHtml='<div class="sl-notice sl-notice-warn"><b>Source availability not verified.</b> '+esc(source.message||'The ERP could not read one or more source ledgers safely.')+' The branch balance is shown separately; do not treat unavailable source data as zero.</div>';
   }else if(source){
    const best=source.recommended_source;
    const locations=(source.sources||[]).slice(0,3);
    const sourceRows=locations.map(x=>'<div class="sl-source-preview-row"><span><b>'+esc(x.location_name)+'</b><small>'+esc(x.location_type==='CENTRAL_WAREHOUSE'?'Central Warehouse':'Peer branch')+'</small></span><span><b>'+esc(x.available)+'</b><small>available</small></span><span><b>'+esc(x.claimable)+'</b><small>claimable</small></span></div>').join('');
    sourceHtml='<div class="sl-source-preview"><div class="sl-block-head"><b>Live source availability</b><span class="sl-pill '+(best?'ok':'warn')+'">'+esc(source.status||'CHECK')+'</span></div><div class="sl-statline"><div class="sl-stat"><b>'+esc(source.central_available??0)+'</b><span>Central Warehouse available</span></div><div class="sl-stat"><b>'+esc(source.peer_branch_available??0)+'</b><span>Peer-branch source total*</span></div><div class="sl-stat"><b>'+esc(source.demand?.suggested_request??quantity)+'</b><span>Suggested replenishment</span></div></div>'+(best?'<div class="sl-sub"><b>Best current source:</b> '+esc(best.location_name)+' — '+esc(best.available)+' available, '+esc(best.claimable)+' claimable. '+esc(best.reason||'Availability is advisory only.')+'</div>':'<div class="sl-sub">No eligible external source is currently available for this SKU. Review supply timing or procurement options; do not invent a variant or change stock here.</div>')+(sourceRows?'<div class="sl-source-preview-list">'+sourceRows+'</div>':'')+'<div class="sl-sub">*Peer-branch source total respects active reservations and the source branch reorder threshold. These are advisory figures; approval and a posted warehouse pick are still required.</div></div>';
   }else{sourceHtml='<div class="sl-notice sl-notice-warn"><b>Source availability not verified.</b> The branch balance is shown, but Central Warehouse / peer-branch sourcing could not be confirmed. Refresh before using source availability.</div>';}
  }catch(sourceError){if(!line.isConnected||Number(line.dataset.stockPreviewSeq)!==seq)return;sourceHtml='<div class="sl-notice sl-notice-warn"><b>Source availability not verified.</b> '+esc(sourceError.message||'The source preview service is unavailable.')+' The branch balance is shown separately.</div>';}
  if(!line.isConnected||Number(line.dataset.stockPreviewSeq)!==seq)return;
  out.innerHTML='<div class="sl-notice sl-notice-soft"><div class="sl-block-head"><b>Live branch stock · '+esc(sku)+'</b><span class="sl-pill ok">LIVE BALANCE</span></div><div class="sl-statline"><div class="sl-stat"><b>'+esc(physical)+'</b><span>Posted on-hand</span></div><div class="sl-stat"><b>'+esc(reserved)+'</b><span>Reserved</span></div><div class="sl-stat"><b>'+esc(available)+'</b><span>Available now</span></div><div class="sl-stat"><b>'+esc(quantity)+'</b><span>Request qty</span></div><div class="sl-stat"><b>'+esc(shortfall)+'</b><span>Branch gap</span></div></div>'+shortage+'<div class="sl-sub">Available = posted on-hand minus active reservations. This preview is read-only and refreshes when SKU, quantity or branch changes.</div></div>'+sourceHtml;
 }catch(e){if(!line.isConnected||Number(line.dataset.stockPreviewSeq)!==seq)return;out.innerHTML='<div class="sl-notice sl-danger-block"><b>Live branch stock could not be verified.</b> '+esc(e.message||'The stock service is unavailable.')+' Do not rely on stale stock figures; refresh before submitting the request.</div>';}
}
function refreshAllReqStockPreviews(){document.querySelectorAll('#v16ReqItems .sl-req-line').forEach(line=>refreshReqStockPreview(line))}
function addReqItemV16(){
 const box=document.getElementById('v16ReqItems');if(!box)return;
 const d=document.createElement('div');d.className='sl-form sl-req-line';
 d.innerHTML='<div class="sl-field"><label>SKU</label>'+skuPickerField('reqsku[]','',{label:'Requested SKU'})+'</div><div class="sl-field"><label>Quantity</label><input name="reqqty[]" type="number" min="1" step="1" value="1" required></div><div class="sl-field full sl-req-live-rule"><div data-req-stock-preview><div class="sl-notice sl-notice-soft">Select a Product Master SKU to view live stock and sources.</div></div></div><button type="button" class="sl-btn sl-btn-sm" data-remove-req-line>Remove</button>';
 box.appendChild(d);bindSkuPickers();
 const sku=d.querySelector('[name="reqsku[]"]');const qty=d.querySelector('[name="reqqty[]"]');
 if(sku)sku.addEventListener('change',()=>refreshReqStockPreview(d));
 if(qty)qty.addEventListener('input',()=>refreshReqStockPreview(d));
 d.querySelector('[data-remove-req-line]').onclick=()=>d.remove();
 refreshReqStockPreview(d);
}
function reorder(){
  return title('Reorder Alerts','Ledger-derived stock levels by authorized location. Zero and negative stock are critical. Branch replenishment creates a normal requisition; it never moves stock directly.','<button class="sl-btn" type="button" id="refreshReorder">Refresh alerts</button>')+
  '<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">STOCK CONTROL</div><h3>Replenishment actions</h3><small>Use a branch requisition for branch demand. Central Warehouse purchasing follows the Owner-approved PO workflow.</small></div></div><div id="reorderTable"><div class="sl-empty">Loading live reorder status…</div></div></div>';
}
async function loadReorder(){
 const el=document.getElementById('reorderTable');if(!el)return;
 el.innerHTML='<div class="sl-notice">Reading ledger-derived reorder status…</div>';
 try{
  const rows=await api16('/stock/reorder');
  if(!Array.isArray(rows))throw new Error('Reorder endpoint returned an unexpected response.');
  if(!rows.length){el.innerHTML='<div class="sl-empty-rich"><b>No reorder rows returned.</b><span>Verify your assigned location and Product Master reorder levels.</span></div>';return;}
  const caps=state.boot?.user?.capabilities||{};
  el.innerHTML='<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Location</th><th>SKU / Product</th><th>Physical on-hand</th><th>Reserved</th><th>Available</th><th>Inbound transfer</th><th>Reorder position</th><th>Reorder level</th><th>Suggested qty</th><th>Status</th><th>Branch action</th></tr></thead><tbody>'+rows.map(x=>{
   const current=Number(x.current_stock||0),reserved=Number(x.reserved_stock||0),available=Number(x.available_stock??Math.max(0,current-reserved)),inbound=Number(x.inbound_stock||0),position=Number(x.reorder_position??(available+inbound)),level=Math.max(0,Number(x.reorder_level||0));
   const status=String(x.status||'')||(level<=0?'NO_LEVEL':(position<=0?'CRITICAL':(position<=level?'REORDER NOW':'OK')));
   const branch=String(x.location_type||'')==='Branch';
   const canRequest=branch&&!!caps.request&&Number(x.location_id)>0&&level>0&&['CRITICAL','REORDER NOW'].includes(status);
   const suggested=Math.max(0,Number(x.suggested_replenishment??(level>0&&position<=level?Math.max(1,Math.ceil(level-position)):0)));
   const qty=Math.max(1,Math.ceil(suggested));
   const action=canRequest?'<div class="sl-reorder-action"><label class="sl-sr-only" for="reorderQty-'+esc(x.location_id)+'-'+esc(x.variant_id||x.sku)+'">Requisition quantity for '+esc(x.sku)+'</label><input id="reorderQty-'+esc(x.location_id)+'-'+esc(x.variant_id||x.sku)+'" class="sl-reorder-qty" type="number" min="1" step="1" value="'+qty+'" data-reorder-qty aria-label="Requisition quantity for '+esc(x.sku)+'"><button type="button" class="sl-btn sl-btn-sm primary" data-reorder-create data-location-id="'+esc(x.location_id)+'" data-sku="'+esc(x.sku)+'" data-current="'+esc(current)+'" data-reserved="'+esc(reserved)+'" data-available="'+esc(available)+'" data-inbound="'+esc(inbound)+'" data-position="'+esc(position)+'" data-level="'+esc(level)+'">Create requisition</button></div>':'<span class="sl-sub">'+(branch?(level<=0?'Set this branch reorder level in Product Master.':(status==='OK'?'No replenishment required.':'Review this branch and follow the authorization workflow.')):(String(x.location_type||'')==='Warehouse'?'Use the approved purchase-order workflow.':'No action required.'))+'</span>';
   return '<tr><td data-label="Location"><b>'+esc(x.location_name||'')+'</b><div class="sl-sub">'+esc(x.location_type||'')+'</div></td><td data-label="SKU / Product"><b>'+esc(x.sku||'')+'</b><div class="sl-sub">'+esc(x.product_name||'')+'</div></td><td data-label="Physical on-hand">'+esc(current)+'</td><td data-label="Reserved">'+esc(reserved)+'</td><td data-label="Available"><b>'+esc(available)+'</b>'+(x.availability_exception?'<div><span class="sl-pill bad">'+esc(x.availability_exception)+'</span></div>':'')+'</td><td data-label="Inbound transfer">'+esc(inbound)+'</td><td data-label="Reorder position"><b>'+esc(position)+'</b></td><td data-label="Reorder level">'+esc(level>0?level:'Not set')+'</td><td data-label="Suggested qty">'+esc(suggested)+'</td><td data-label="Status"><span class="sl-pill '+(status==='CRITICAL'?'bad':(status==='REORDER NOW'||status==='NO_LEVEL')?'warn':'ok')+'">'+esc(status)+'</span></td><td data-label="Branch action">'+action+'</td></tr>';
  }).join('')+'</tbody></table></div>';
  el.querySelectorAll('[data-reorder-create]').forEach(button=>button.addEventListener('click',async()=>{
   if(button.dataset.busy==='1')return;
   const location_id=Number(button.dataset.locationId||0),sku=String(button.dataset.sku||'').trim();
   const input=button.parentElement.querySelector('[data-reorder-qty]');
   const quantity=Number(input?.value);
   if(!location_id||!sku||!Number.isInteger(quantity)||quantity<1){toast('Choose a valid whole-unit replenishment quantity.',true);return;}
   const key=button.dataset.requestKey||(button.dataset.requestKey=newRequestKey());
   const old=button.textContent;button.disabled=true;button.dataset.busy='1';button.textContent='Submitting…';
   try{
    const result=await api16('/requisitions',{method:'POST',body:JSON.stringify({request_key:key,location_id,items:[{sku,quantity}],notes:'Reorder review: physical on-hand '+button.dataset.current+', active reserved '+button.dataset.reserved+', available '+button.dataset.available+', dispatched inbound '+button.dataset.inbound+', reorder position '+button.dataset.position+', configured reorder level '+button.dataset.level+'. Please review and approve under the existing SOP.'})});
    if(!result||(!Number(result.id||0)&&!result.replayed))throw new Error('Server did not confirm the requisition record. Refresh and verify before retrying.');
    delete button.dataset.requestKey;
    toast(result.replayed?'Existing requisition recovered after retry.':'Requisition submitted. Stock was not changed; approval and warehouse pick remain separate.');
    await loadReorder();
    try{await loadReqs();}catch(_e){}
    try{await loadNotifications();}catch(_e){}
   }catch(e){toast((e?.message||'Reorder requisition failed')+' The same request key is retained for a safe retry.',true);button.disabled=false;button.textContent=old;}
   finally{button.dataset.busy='0';}
  }));
 }catch(e){
  el.innerHTML='<div class="sl-notice sl-danger-block"><b>Reorder status could not be loaded safely.</b> '+esc(e.message)+'<div style="margin-top:10px"><button type="button" class="sl-btn" id="retryReorder">Retry</button></div></div>';
  document.getElementById('retryReorder')?.addEventListener('click',loadReorder);
 }
}
function dailyInventoryPage(){
    const locs=authorizedLocations().filter(l=>['Branch','Warehouse'].includes(l.location_type));
    const allowMulti=state.boot.user.can_all||locs.length>1;
    const selectedId=Number(state.dailyInventoryLocation||0);
    const locationFilter=allowMulti?'<label>Location <select id="dailyInventoryLocation">'+(state.boot.user.can_all?'<option value="0" '+(!selectedId?'selected':'')+'>All company</option>':'')+locs.map((l,i)=>'<option value="'+esc(l.id)+'" '+((selectedId&&selectedId===Number(l.id))||(!selectedId&&!state.boot.user.can_all&&i===0)?'selected':'')+'>'+esc(l.location_name)+'</option>').join('')+'</select></label>':(locs[0]?'<input type="hidden" id="dailyInventoryLocation" value="'+esc(locs[0].id)+'">':'');
    const guidance='<div class="sl-panel sl-training-panel sl-inventory-sop"><div class="sl-block-head"><div><div class="sl-eyebrow">STEP-BY-STEP SOP • DAILY INVENTORY</div><h3>Read the report before taking action</h3></div><span class="sl-pill info">READ-ONLY CONTROL</span></div><div class="sl-training-steps"><div class="sl-training-step"><b>01</b><span>Choose the correct business date and authorized location scope.</span></div><div class="sl-training-step"><b>02</b><span>Use the report to verify Opening → Receipts / Transfers → Sales → Closing → Reserved → Available.</span></div><div class="sl-training-step"><b>03</b><span>Review REORDER / SHORTFALL rows before raising a requisition or warehouse action.</span></div><div class="sl-training-step"><b>04</b><span>Daily Inventory is read-only. Stock changes only through receiving, approved transfers, adjustments or posted Daily POS.</span></div><div class="sl-training-step"><b>05</b><span>When the report cannot load, stop. Do not infer stock from a stale screen; open System Health and report the exact reference.</span></div></div><div class="sl-notice sl-notice-soft"><b>Authority:</b> this screen never creates inventory. It reports server-side ledger and live balance evidence.</div></div>';
    return title('Daily Inventory Control','Server-derived opening, movement, closing, reservations, available stock and reorder position. This report is read-only and never creates stock.','<button class="sl-btn primary" id="refreshDailyInventory">Refresh report</button>')+guidance+
      '<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">INVENTORY CONTROL</div><h3>Report scope</h3></div><small>Company scope stays available, but a single location is the lighter operational read.</small></div><div class="sl-report-filter"><label>Date <input type="date" id="dailyInventoryDate"></label>'+locationFilter+
      '</div><div id="dailyInventoryReport"><div class="sl-empty">Loading daily inventory…</div></div></div>';
}
function importTrainingPanel(type){
    const role=String(state.boot?.user?.role||'').toLowerCase();
    if(type==='daily'){
      const manager=role==='branch_manager';
      const sales=role==='sales_employee';
      const steps=manager?[
        'STEP 1 — Confirm the authorized branch and business date.',
        'STEP 2 — Upload the actual external POS CSV or Excel source file.',
        'STEP 3 — Validate and stage. Nothing changes in sales, inventory or finance yet.',
        'STEP 4 — Review staged rows; use EDIT when a real source value needs correction.',
        'STEP 5 — Confirm every SKU is a live Product Master identity and every quantity/price is real.',
        'STEP 6 — Press RELEASE TO ERP. ERP then processes the staged job in bounded chunks.',
        'STEP 7 — Verify the final Sales + Inventory + Finance + Audit result; do not retry blindly after an error.'
      ]:[
        'STEP 1 — Work on customers, products, sales and stock requests in the assigned branch.',
        'STEP 2 — Use the live Product Master SKU identity; zero stock does not mean a new SKU is needed.',
        'STEP 3 — When assigned, upload the actual branch POS CSV or workbook and complete validation/staging only.',
        'STEP 4 — Do not edit or release the staged job; the Branch Manager owns review/edit/release.',
        'STEP 5 — Request stock when replenishment is needed and use live warehouse availability.',
        'STEP 6 — Report POS/import exceptions to the Branch Manager with the relevant receipt/reference.'
      ];
      return '<div class="sl-panel sl-training-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">STEP-BY-STEP SOP • DAILY POS</div><h3>How Daily POS is controlled</h3></div><span class="sl-pill info">'+esc(manager?'BRANCH MANAGER':sales?'SALES EMPLOYEE':'SALES OPERATIONS')+'</span></div><div class="sl-training-steps">'+steps.map((x,i)=>'<div class="sl-training-step"><b>'+String(i+1).padStart(2,'0')+'</b><span>'+esc(x.replace(/^STEP \d+ — /,''))+'</span></div>').join('')+'</div><div class="sl-notice sl-notice-soft"><b>Control boundary:</b> '+esc(manager?'Sales Employees may upload/stage. Branch Manager alone reviews, edits and RELEASES TO ERP.':sales?'You may upload/validate/stage only. Branch Manager alone reviews, edits and RELEASES TO ERP.':'Daily POS actions are limited to assigned branch roles; Branch Manager is the only release authority.')+'</div></div>';
    }
    const steps=[
      'Prepare the approved Product Master XLSX using the exact catalog identity fields.',
      'Choose the workbook and press PREVIEW PRODUCT MASTER.',
      'Review the detected mapping and every validation error before committing.',
      'Existing SKU means UPDATE; new valid SKU means INSERT; duplicate/invalid identity means STOP.',
      'Press the commit action only after the preview passes.',
      'Wait for the server result and verify the committed catalog record.',
      'Product Master import never creates inventory quantity. Physical stock belongs to Warehouse Receiving.',
      'Do not create a second SKU because a search or inventory balance appears empty.'
    ];
    return '<div class="sl-panel sl-training-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">STEP-BY-STEP SOP • PRODUCT MASTER</div><h3>How to use Product Master Import</h3></div><span class="sl-pill info">MANAGEMENT / OWNER</span></div><div class="sl-training-steps">'+steps.map((x,i)=>'<div class="sl-training-step"><b>'+String(i+1).padStart(2,'0')+'</b><span>'+esc(x)+'</span></div>').join('')+'</div><div class="sl-notice sl-notice-soft"><b>Control boundary:</b> Product Master is catalog identity; inventory quantity enters only through the receiving/ledger workflow.</div></div>';
}
function dailyImportPage(){
    const c=state.boot?.user?.capabilities||{};
    const role=state.boot?.user?.role||'';
    if(!['branch_manager','sales_employee'].includes(role) || !c.daily_import){
      return title('Daily POS Control','This workflow is available to assigned Sales Employees for upload/staging and to Branch Managers for review/edit/release.')+'<div class="sl-panel"><div class="sl-notice sl-warning-block"><b>Access boundary:</b> Sales Employees may upload and stage assigned-branch POS evidence. Branch Managers alone review, edit and RELEASE TO ERP.</div></div>';
    }
    const branches=authorizedBranches();const company=!!state.boot.user.can_all;const multi=company||branches.length>1;
    const branchField=multi?'<select name="branch_location_id" required>'+(company?'<option value="">Select branch…</option>':'')+branches.map(l=>'<option value="'+esc(l.id)+'">'+esc(l.location_name)+'</option>').join('')+'</select>':('<div class="sl-readonly-field">'+esc(branches[0]?.location_name||'Assigned branch')+'</div><input type="hidden" name="branch_location_id" value="'+esc(branches[0]?.id||'')+'">');
    return title('Daily POS Control','Sales Employee / Branch Manager. Upload transaction-level external POS end-of-day CSV or Excel evidence, then review the staged rows. Only the Branch Manager can explicitly RELEASE TO ERP.','<button class="sl-btn" id="refresh">Refresh</button>')+importTrainingPanel('daily')+
      '<div class="sl-import-purpose-grid"><div class="sl-purpose-card is-current"><b>1 • CSV / EXCEL UPLOAD</b><span>Use the transaction-level end-of-day CSV or workbook exported from the separate branch POS.</span><em>VALIDATE → STAGE</em></div><div class="sl-purpose-card"><b>2 • REVIEW / EDIT</b><span>Correct only real source values in the staged batch before release.</span><em>REVIEW → EDIT</em></div><div class="sl-purpose-card"><b>3 • RELEASE</b><span>Only Branch Manager releases verified evidence into sales, inventory, finance and audit.</span><em>RELEASE TO ERP</em></div></div>'+ 
      '<div class="sl-panel sl-upload-callout"><div class="sl-eyebrow">EXTERNAL POS • CSV / EXCEL EVIDENCE</div><h3>Upload Daily POS CSV or Excel</h3><p>Upload the actual end-of-day CSV or Excel file. The ERP validates and stages it. Nothing posts at upload time and there is no alternate POS/cart workflow here.</p><form id="dailyPosImportForm" class="sl-form"><div class="sl-field"><label>Business date</label><input id="dailyPosBusinessDate" name="business_date" type="date" value="'+esc(localDate())+'" required></div><div class="sl-field"><label>Branch</label>'+branchField+'</div><div class="sl-field full"><label>Daily POS CSV or Excel (.csv / .xlsx)</label><div class="sl-file-picker" data-file-picker><input id="dailyPosFile" name="file" type="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required><label class="sl-file-button" for="dailyPosFile"><span class="sl-file-button-main">Choose CSV or Excel file</span><span class="sl-file-name" data-file-name>No file selected</span></label></div></div><div class="sl-field full"><button class="sl-btn primary" type="submit">VALIDATE & STAGE</button></div></form><div id="dailyImportPreview" style="margin-top:14px"></div></div>'+ 
      '<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">RELEASE CONTROL</div><h3>Daily POS staged queue</h3></div><small>Sales Employee: upload/stage • Branch Manager: review/edit/release.</small></div><div id="dailyImportQueue"><div class="sl-empty">Loading staged Daily POS jobs…</div></div></div>'+ 
      '<div class="sl-panel sl-upload-callout"><div class="sl-eyebrow">INVENTORY CONTROL</div><h3>Daily Inventory Control</h3><p>After a Daily POS release, review the authoritative ledger-driven Daily Inventory report.</p><button class="sl-btn" type="button" data-view="dailyreport">OPEN DAILY INVENTORY</button></div>'+ 
      '<div class="sl-grid">'+card('Upload / Stage','Sales Employee + Branch Manager','Assigned branch only')+card('Release','Branch Manager only','Review • edit • release')+card('Entry method','CSV or Excel','External POS end-of-day source file')+card('Inventory','Post after release','No stock mutation during staging')+card('Finance','GL linked after release','Sales + VAT + COGS + audit')+'</div>'+ 
      '<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">SOURCE REQUIREMENT</div><h3>Quantity behavior</h3></div></div><div class="sl-notice sl-warning-block"><b>Real quantity is mandatory.</b> The ERP blocks a row when the source does not provide a real quantity. It never invents units, price, cost or SKU.</div></div>';
}

function productImportPage(){
    return title('Product Master Reconciliation','Management and Owner only. This import controls product identity/catalog data. It NEVER creates inventory. New arrivals belong in Warehouse → Receiving; completed branch sales belong in Daily POS Import.','<button class="sl-btn" id="refresh">Refresh</button>')+
      importTrainingPanel('product')+
      '<div class="sl-import-purpose-grid"><div class="sl-purpose-card is-current"><b>1 • PRODUCT MASTER</b><span>Create, update or reconcile SKU, product identity, price and reorder master fields.</span><em>CATALOG ONLY • STOCK UNCHANGED</em></div><div class="sl-purpose-card"><b>2 • NEW ARRIVAL → RECEIVING</b><span>Record physical goods received into Central Warehouse through the receiving workflow.</span><em>INVENTORY LEDGER +</em></div><div class="sl-purpose-card"><b>3 • DAILY POS IMPORT</b><span>Import completed external branch POS end-of-day sales through preview, validation and posting.</span><em>SALES / STOCK −</em></div></div>'+ 
      '<div class="sl-panel"><div class="sl-upload-callout"><div class="sl-eyebrow">MASTER DATA CONTROL</div><h3>Bulk Product Master XLSX</h3><p>Upload the approved Product Master workbook. Product code resolves to the live Stockland SKU. Default Value / Default Tax remain source fields unless explicitly mapped by Product Master rules.</p></div><form id="productImportForm" class="sl-form"><div class="sl-field full"><label>Product Master Excel (.xlsx)</label><div class="sl-file-picker" data-file-picker><input id="productImportFile" name="file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required><label class="sl-file-button" for="productImportFile"><span class="sl-file-button-main">Choose Excel file</span><span class="sl-file-name" data-file-name>No file selected</span></label></div></div><div class="sl-field full"><button class="sl-btn primary" type="submit">PREVIEW PRODUCT MASTER</button></div></form><div id="productImportPreview" style="margin-top:14px"></div></div>'+ 
      '<div class="sl-grid">'+card('Authority','Management + Owner','Product Master only')+card('History','Preserved','Price history recorded when explicit prices change')+card('Inventory','Unchanged','No stock is created by product import')+card('POS','Separate workflow','Use Daily POS Import for external sales')+'</div>';
}

async function previewDailyImport(e){
    e.preventDefault();const f=e.currentTarget,file=document.getElementById('dailyPosFile')?.files[0],box=document.getElementById('dailyImportPreview'),submit=f?.querySelector('button[type=submit]');
    if(!file){toast('Choose the branch POS Excel file first.',true);return} if(submit?.dataset.busy==='1')return;
    if(submit){submit.dataset.busy='1';submit.disabled=true;submit.textContent='VALIDATING…'}
    const fd=new FormData();fd.append('file',file);const bf=f.querySelector('[name=branch_location_id]');if(bf&&bf.value)fd.append('branch_location_id',bf.value);const df=f.querySelector('[name=business_date]');if(df?.value)fd.append('business_date',df.value);
    box.innerHTML='<div class="sl-notice">Reading, validating and staging the Daily POS workbook…</div>';
    try{const d=await api16('/import/daily-sales/preview',{method:'POST',body:fd});const mappingRows=Object.entries(d.mapping?.fields||{}).map(([k,v])=>'<tr><td data-label="ERP field">'+esc(k)+'</td><td data-label="Source header"><b>'+esc(v)+'</b></td><td data-label="Mode">'+esc(d.mapping?.mode||'AUTO')+'</td></tr>').join('');
      box.innerHTML='<div class="sl-statline">'+Object.entries(d.summary||{}).map(([k,v])=>'<div class="sl-stat"><b>'+esc(v)+'</b><span>'+esc(k)+'</span></div>').join('')+'</div>'
      +(d.mapping?'<div class="sl-panel-inset"><div class="sl-block-head"><div><div class="sl-eyebrow">MAPPING</div><h4>Workbook → Daily POS</h4></div><small>Saved by header signature when valid.</small></div><div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>ERP field</th><th>Source header</th><th>Mode</th></tr></thead><tbody>'+mappingRows+'</tbody></table></div></div>':'')
      +(d.warnings?.length?'<div class="sl-notice sl-warning-block"><b>Warnings</b><ul>'+d.warnings.map(x=>'<li>'+esc(x)+'</li>').join('')+'</ul></div>':'')
      +(d.errors?.length?'<div class="sl-notice sl-danger-block"><b>Blocking errors</b><ul>'+d.errors.map(x=>'<li>'+esc(x)+'</li>').join('')+'</ul><p>Correct the source workbook and re-upload it. No ERP transaction was released.</p></div>':'<div class="sl-notice sl-notice-soft"><b>STAGED — NOTHING POSTED.</b> Open the queue, review/edit the rows, then RELEASE TO ERP.</div>')
      +'<div class="sl-actions" style="margin-top:10px"><button class="sl-btn" type="button" id="openDailyJobAfterStage">OPEN STAGED JOB</button></div>';
      document.getElementById('openDailyJobAfterStage')?.addEventListener('click',()=>openDailyJobEditor(Number(d.job_id)));await loadDailyImportQueue();
    }catch(err){const msg=(err instanceof TypeError&&/failed to fetch/i.test(String(err.message||'')))?'ERP connection failed. No ERP data was changed.':err.message;box.innerHTML='<div class="sl-notice sl-danger-block"><b>Daily POS staging failed.</b><div style="margin-top:4px">'+esc(msg)+'</div></div>'}
    finally{if(submit){submit.dataset.busy='0';submit.disabled=false;submit.textContent='VALIDATE & STAGE'}}
}



async function openDailyJobEditor(id){
    let d;try{d=await api16('/import/daily-sales/jobs/'+encodeURIComponent(id))}catch(e){toast(e.message,true);return}
    const job=d.job,rows=d.rows||[];const ov=document.createElement('div');ov.className='sl-overlay';
    const canRelease=!!state.boot.user.capabilities?.daily_post && ['READY','PARTIAL'].includes(job.status) && Number(job.processed_rows||0)===0;
    const rowsHtml=rows.map(r=>'<tr data-edit-row="'+esc(r.id)+'"><td data-label="Row"><b>#'+esc(r.row_number)+'</b></td><td data-label="SKU / Receipt"><b>'+esc(r.sku||'—')+'</b><div class="sl-sub">'+esc(r.external_receipt_no||'')+'</div></td><td data-label="Quantity"><input data-eqty type="number" min="0.01" step="0.01" value="'+esc(r.quantity||'')+'" '+(!canRelease||r.posted_sale_id?'disabled':'')+'></td><td data-label="Price"><input data-eprice type="number" min="0.01" step="0.01" value="'+esc(r.selling_price||'')+'" '+(!canRelease||r.posted_sale_id?'disabled':'')+'></td><td data-label="VAT"><select data-evat '+(!canRelease||r.posted_sale_id?'disabled':'')+'><option value="VAT_INCLUDED" '+(r.vat_mode==='VAT_INCLUDED'?'selected':'')+'>VAT included</option><option value="WITHOUT_VAT" '+(r.vat_mode==='WITHOUT_VAT'?'selected':'')+'>Without VAT</option></select></td><td data-label="Edit">'+(canRelease&&!r.posted_sale_id?'<button type="button" class="sl-btn sl-btn-sm" data-save-row="'+esc(r.id)+'">SAVE EDIT</button>':'<span class="sl-sub">Manager review</span>')+'</td><td data-label="Status"><span class="sl-pill '+(r.status==='POSTED'?'ok':r.error_message?'bad':'info')+'">'+esc(r.status)+'</span></td></tr>').join('');
    ov.innerHTML='<aside class="sl-drawer sl-accessible-dialog" role="dialog" aria-modal="true" aria-labelledby="dailyJobTitle"><div class="sl-drawer-head"><div><div class="sl-eyebrow">DAILY POS • RELEASE CONTROL</div><h2 id="dailyJobTitle">Job #'+esc(job.id)+'</h2><p>'+esc(job.location_name||'')+' • '+esc(job.business_date||'')+' • '+esc(job.source_filename||'')+'</p></div><button class="sl-icon-btn" id="closeDailyJob" aria-label="Close">×</button></div><div class="sl-request-summary"><div><span>Status</span><b>'+esc(job.status)+'</b></div><div><span>Rows</span><b>'+esc(job.valid_rows)+' / '+esc(job.total_rows)+'</b></div><div><span>Processed</span><b>'+esc(job.processed_rows)+'</b></div><div><span>Release authority</span><b>Branch Manager</b></div></div><div class="sl-notice sl-notice-soft"><b>Review before release:</b> Save any correction first. When satisfied, press RELEASE TO ERP. Until then, sales, inventory and finance remain unchanged.</div><div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Row</th><th>SKU / Receipt</th><th>Quantity</th><th>Price</th><th>VAT</th><th>Edit</th><th>Status</th></tr></thead><tbody>'+rowsHtml+'</tbody></table></div><div class="sl-action-row" style="justify-content:flex-end"><button type="button" class="sl-btn" id="cancelDailyJob">Close</button>'+(canRelease?'<button type="button" class="sl-btn primary" id="releaseDailyJob">RELEASE TO ERP</button>':'')+'</div></aside>';
    document.body.appendChild(ov);const close=()=>ov.remove();document.getElementById('closeDailyJob').onclick=close;document.getElementById('cancelDailyJob').onclick=close;ov.onclick=e=>{if(e.target===ov)close()};
    document.querySelectorAll('[data-save-row]').forEach(btn=>btn.onclick=async()=>{const tr=btn.closest('tr'),data={job_id:Number(job.id),row_id:Number(btn.dataset.saveRow),quantity:Number(tr.querySelector('[data-eqty]')?.value||0),selling_price:Number(tr.querySelector('[data-eprice]')?.value||0),vat_mode:tr.querySelector('[data-evat]')?.value||'',};btn.disabled=true;try{await api16('/import/daily-sales/edit-row',{method:'POST',body:JSON.stringify(data)});toast('Staged Daily POS row saved. Nothing was posted.');await openDailyJobEditor(Number(job.id));}catch(e){toast(e.message,true);btn.disabled=false}});
    document.getElementById('releaseDailyJob')?.addEventListener('click',async()=>{const b=document.getElementById('releaseDailyJob');b.disabled=true;b.textContent='RELEASING…';try{let done=false;while(!done){const x=await api16('/import/daily-sales/commit',{method:'POST',body:JSON.stringify({job_id:Number(job.id)})});done=x.status==='POSTED';if(!done){await new Promise(r=>setTimeout(r,300));}}toast('Daily POS released to ERP. Sales, inventory, finance and audit were updated and verified.');close();await loadDailyImportQueue();await loadNotifications();await loadDailyInventoryReport();await load()}catch(e){toast(e.message,true);b.disabled=false;b.textContent='RELEASE TO ERP'}});
}



async function loadDailyImportQueue(){try{const rows=await api16('/import/daily-sales/jobs');const el=document.getElementById('dailyImportQueue');if(!el)return;const canRelease=!!state.boot.user.capabilities?.daily_post;el.innerHTML=rows.length?'<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Job</th><th>Date</th><th>Branch</th><th>Source</th><th>Rows</th><th>Released</th><th>Status</th><th>Next action</th></tr></thead><tbody>'+rows.map(j=>{const next=(canRelease&&['READY','PARTIAL'].includes(j.status)&&Number(j.processed_rows||0)===0)?'<button class="sl-btn sl-btn-sm sl-btn-gold" data-edit-daily-job="'+esc(j.id)+'">EDIT / RELEASE</button>':j.status==='POSTED'?'<span class="sl-sub">Completed</span>':j.status==='REVIEW'?'<span class="sl-sub">Fix source and re-upload</span>':'<span class="sl-sub">Pending Branch Manager</span>';return '<tr><td data-label="Job"><b>#'+esc(j.id)+'</b></td><td data-label="Date">'+esc(j.business_date||'')+'</td><td data-label="Branch">'+esc(j.location_name||'')+'</td><td data-label="Source">'+esc(j.source_filename||'')+'</td><td data-label="Rows">'+esc(j.valid_rows)+' / '+esc(j.total_rows)+'</td><td data-label="Released">'+esc(j.processed_rows)+'</td><td data-label="Status"><span class="sl-pill '+(j.status==='POSTED'?'ok':j.status==='REVIEW'?'bad':'info')+'">'+esc(j.status)+'</span></td><td data-label="Next action">'+next+'</td></tr>'}).join('')+'</tbody></table></div>':'<div class="sl-empty-rich"><b>No staged Daily POS jobs.</b><span>Upload an external POS workbook .</span></div>';document.querySelectorAll('[data-edit-daily-job]').forEach(b=>b.onclick=()=>openDailyJobEditor(Number(b.dataset.editDailyJob)))}catch(e){const el=document.getElementById('dailyImportQueue');if(el)el.innerHTML='<div class="sl-notice sl-danger-block">'+esc(e.message)+'</div>'}}

async function previewProductImport(e){
    e.preventDefault();const f=e.currentTarget,file=document.getElementById('productImportFile')?.files[0],box=document.getElementById('productImportPreview'),submit=f.querySelector('button[type=submit]');
    if(!file){toast('Choose the Product Master Excel file first.',true);return}
    if(submit?.dataset.busy==='1')return;
    if(submit){submit.dataset.busy='1';submit.disabled=true;submit.textContent='READING & MAPPING…'}
    const fd=new FormData();fd.append('file',file);box.innerHTML='<div class="sl-notice">Reading, auto-mapping and staging Product Master rows…</div>';
    try{const d=await api16('/import/products/preview',{method:'POST',body:fd});
      const mappingRows=Object.entries(d.mapping?.fields||{}).map(([k,v])=>'<tr><td data-label="ERP field">'+esc(k)+'</td><td data-label="Workbook header">'+esc(v)+'</td><td data-label="Mode">'+esc(d.mapping?.mode||'AUTO')+'</td></tr>').join('');
      box.innerHTML='<div class="sl-statline">'+Object.entries(d.summary||{}).map(([k,v])=>'<div class="sl-stat"><b>'+esc(v)+'</b><span>'+esc(k)+'</span></div>').join('')+'</div>'
      +'<div class="sl-notice sl-notice-soft"><b>Important:</b> Code maps to the live Stockland SKU. Default Value / Default Tax are stored as source fields only; they are not silently treated as retail price or VAT configuration. Existing optional live fields stay unchanged when they are absent from the workbook.</div>'
      +'<div class="sl-panel-inset"><div class="sl-block-head"><div><div class="sl-eyebrow">MAPPING</div><h4>Workbook → Product Master</h4></div><small>Saved for reuse by header signature.</small></div><div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>ERP field</th><th>Workbook header</th><th>Mode</th></tr></thead><tbody>'+mappingRows+'</tbody></table></div></div>'
      +(d.status==='ERROR'||Number(d.summary?.errors)>0?'<div class="sl-notice sl-danger-block"><b>Correct validation errors before commit.</b></div>':'<div class="sl-notice sl-notice-soft">Preview passed. Product Master changes are staged and are not live yet.</div><button class="sl-btn primary" id="commitProductImport" type="button">COMMIT PRODUCT MASTER</button><div id="productImportCommitStatus" class="sl-sub" style="margin-top:10px"></div>')
      +'<div class="sl-table-wrap" style="margin-top:12px"><table class="sl-table sl-responsive-cards"><thead><tr><th>SKU</th><th>Product</th><th>Category</th><th>UOM</th><th>Action</th><th>Error</th></tr></thead><tbody>'+((d.preview||[]).map(x=>'<tr><td data-label="SKU">'+esc(x.sku)+'</td><td data-label="Product">'+esc(x.product_name)+'</td><td data-label="Category">'+esc(x.category||'')+'</td><td data-label="UOM">'+esc(x.unit||'')+'</td><td data-label="Action"><span class="sl-pill info">'+esc(x.action)+'</span></td><td data-label="Error">'+esc(x.error||'')+'</td></tr>').join('')||'<tr><td colspan="6">No preview rows.</td></tr>')+'</tbody></table></div>';
      const btn=document.getElementById('commitProductImport');if(btn){btn.onclick=async()=>{if(btn.dataset.busy==='1')return;btn.dataset.busy='1';btn.disabled=true;const statusBox=document.getElementById('productImportCommitStatus');let done=false;try{while(!done){const x=await api16('/import/products/commit',{method:'POST',body:JSON.stringify({job_id:Number(d.job_id)})});if(statusBox)statusBox.textContent=x.message+' Remaining: '+x.remaining+'.';done=x.status==='POSTED';if(!done){btn.textContent='COMMITTING…';await new Promise(r=>setTimeout(r,300));}}toast('Product Master import completed and the authoritative catalog state was committed. Inventory was not changed.');await loadNotifications();await load();}catch(err){toast(err.message,true)}finally{btn.dataset.busy='0';btn.disabled=false;btn.textContent='COMMIT PRODUCT MASTER CHUNK'}}}
    }catch(err){const msg=(err instanceof TypeError&&/failed to fetch/i.test(String(err.message||'')))?'ERP connection failed. The server could not be reached. No ERP data was changed.':err.message;box.innerHTML='<div class="sl-notice sl-danger-block"><b>Import request failed.</b><div style="margin-top:4px">'+esc(msg)+'</div><button class="sl-btn sl-btn-sm" type="button" onclick="location.reload()" style="margin-top:10px">RETRY PAGE</button></div>'}
    finally{if(submit){submit.dataset.busy='0';submit.disabled=false;submit.textContent='PREVIEW PRODUCT MASTER'}}
}

async function loadDailyInventoryReport(){const el=document.getElementById('dailyInventoryReport');if(!el)return;const date=document.getElementById('dailyInventoryDate')?.value||localDate();el.innerHTML='<div class="sl-notice">Loading authoritative inventory evidence…</div>';try{const id=Number(document.getElementById('dailyInventoryLocation')?.value||state.dailyInventoryLocation||(!state.boot.user.can_all?(state.boot.user.location_ids?.[0]||state.boot.user.branch_location_id||0):0));state.dailyInventoryLocation=id;const qs='?business_date='+encodeURIComponent(date)+(id?'&location_id='+id:'')+'&detail_limit=500';const d=await api16('/reports/daily-inventory'+qs);const s=d.summary||{};const statusClass=x=>x==='OK'?'ok':x==='SHORTFALL'?'bad':'warn';const rows=(d.rows||[]).filter(x=>Number(x.closing)!==0||Number(x.sales)!==0||Number(x.reorder_qty)!==0||Number(x.receipts)!==0);const q=d.finance_data_quality||{};const quality=(Number(q.cost_missing_sale_lines||0)||Number(q.master_price_missing_sale_lines||0))?'<div class="sl-notice sl-danger-block"><b>Finance data quality notice:</b> '+esc(q.cost_missing_sale_lines||0)+' imported sale lines have no authoritative purchase cost and '+esc(q.master_price_missing_sale_lines||0)+' have no authoritative Master Price. No values were invented; complete Product Master data before relying on those finance fields.</div>':'';const trunc=d.rows_truncated?'<div class="sl-notice sl-notice-soft"><b>Detail rows bounded for production performance.</b> Showing '+esc(rows.length)+' of '+esc(d.total_matching_rows||rows.length)+' matching rows. KPI totals above cover the full selected scope.</div>':'';el.innerHTML=quality+trunc+'<div class="sl-grid sl-grid-compact">'+card('Opening',s.opening_units||0,'Units at start of business date')+card('Sales',s.sales||0,'Units posted from external POS')+card('Closing',s.closing_units||0,'Ledger-derived closing balance')+card('Available',s.available_units||0,'After active reservations')+card('Reorder',s.reorder_alerts||0,'Items below reorder level')+card('Shortfall',s.warehouse_shortfall||0,'Units unavailable in Warehouse')+'</div><div class="sl-grid sl-grid-compact">'+card('Sales Value',money(s.sales_gross||0),'Imported gross sales')+card('Net Sales',money(s.sales_net||0),'Excluding VAT')+card('VAT',money(s.sales_vat||0),'Imported VAT')+card('COGS',money(s.sales_cogs||0),'Cost of imported sales')+card('Gross Profit',money(s.sales_gross_profit||0),'Imported sales GP')+card('Sale Lines',s.sales_lines||0,'Posted source rows')+'</div><div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Location</th><th>SKU</th><th>Opening</th><th>Receipts</th><th>In</th><th>Out</th><th>Sales Units</th><th>Sales Value</th><th>Closing</th><th>Reserved</th><th>Available</th><th>Reorder</th><th>Warehouse (current)</th><th>Status</th></tr></thead><tbody>'+(rows.length?rows.map(x=>'<tr><td data-label="Location"><b>'+esc(x.location_name)+'</b></td><td data-label="SKU"><b>'+esc(x.sku)+'</b><div class="sl-sub">'+esc(x.product_name)+'</div></td><td data-label="Opening">'+esc(x.opening)+'</td><td data-label="Receipts">'+esc(x.receipts)+'</td><td data-label="In">'+esc(x.transfer_in)+'</td><td data-label="Out">'+esc(x.transfer_out)+'</td><td data-label="Sales Units">'+esc(x.sales)+'</td><td data-label="Sales Value" class="sl-report-finance-cell">'+money(x.sales_gross||0)+'</td><td data-label="Closing"><b>'+esc(x.closing)+'</b></td><td data-label="Reserved">'+esc(x.reserved)+'</td><td data-label="Available"><b>'+esc(x.available)+'</b></td><td data-label="Reorder">'+esc(x.reorder_qty)+' / '+esc(x.reorder_level)+'</td><td data-label="Warehouse (current)">'+esc(x.warehouse_available)+' avail • '+esc(x.warehouse_fulfillable)+' fillable</td><td data-label="Status"><span class="sl-pill '+statusClass(x.reorder_status)+'">'+esc(x.reorder_status)+'</span></td></tr>').join(''):'<tr><td colspan="14"><div class="sl-empty">No movement / reorder rows for the selected date.</div></td></tr>')+'</tbody></table></div>';const ld=document.getElementById('dailyInventoryLocation');if(ld){ld.value=String(state.dailyInventoryLocation||id||0);ld.onchange=()=>{state.dailyInventoryLocation=Number(ld.value||0);loadDailyInventoryReport()}}}catch(e){const msg=String(e?.message||'ERP server did not return a usable Daily Inventory report.');el.innerHTML='<div class="sl-notice sl-danger-block"><b>Daily Inventory could not be loaded safely.</b><div style="margin-top:5px">'+esc(msg)+'</div><div class="sl-sub" style="margin-top:5px">No inventory data was changed by this report. Open System Health and inspect the exact server error before retrying.</div><button class="sl-btn primary sl-btn-sm" type="button" id="retryDailyInventory" style="margin-top:10px">RETRY</button></div>';document.getElementById('retryDailyInventory')?.addEventListener('click',loadDailyInventoryReport)}}

async function loadCodeMaps(){const el=document.getElementById('codeMapList');if(!el)return;try{const rows=await api16('/import/code-maps');el.innerHTML=rows.length?'<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>External code</th><th>Live SKU</th><th>Source</th><th>Effective</th><th>Note</th><th></th></tr></thead><tbody>'+rows.map(x=>'<tr><td data-label="External code"><b>'+esc(x.external_code)+'</b></td><td data-label="Live SKU"><b>'+esc(x.live_sku)+'</b></td><td data-label="Source">'+esc(x.source_system)+'</td><td data-label="Effective">'+esc(x.effective_from||'open')+' → '+esc(x.effective_to||'open')+'</td><td data-label="Note">'+esc(x.mapping_note||'')+'</td><td data-label="Action"><button class="sl-btn sl-btn-sm" data-delete-code-map="'+esc(x.id)+'">Deactivate</button></td></tr>').join('')+'</tbody></table></div>':'<div class="sl-empty">No external POS-code mappings have been added yet.</div>';document.querySelectorAll('[data-delete-code-map]').forEach(btn=>btn.onclick=async()=>{btn.disabled=true;try{await api16('/import/code-maps/'+encodeURIComponent(btn.dataset.deleteCodeMap),{method:'DELETE'});await loadCodeMaps();toast('Code mapping deactivated')}catch(e){toast(e.message,true);btn.disabled=false}})}catch(e){el.innerHTML='<div class="sl-notice sl-danger-block">'+esc(e.message)+'</div>'}}

async function saveCodeMap(e){e.preventDefault();const f=e.currentTarget;if(f.dataset.busy==='1')return;f.dataset.busy='1';const btn=f.querySelector('button[type="submit"]');const original=btn?btn.textContent:'';if(btn){btn.disabled=true;btn.setAttribute('aria-busy','true');btn.textContent='SAVING…'}const d=Object.fromEntries(new FormData(f));try{const out=await api16('/import/code-maps',{method:'POST',body:JSON.stringify(d)});f.reset();await loadCodeMaps();toast(out?.message||'External POS code mapping saved')}catch(e){toast(e.message,true)}finally{f.dataset.busy='0';if(btn){btn.disabled=false;btn.removeAttribute('aria-busy');btn.textContent=original||'SAVE SKU MAPPING'}}}

function requests(){const c=state.boot.user.capabilities||{};const canCreate=!!c.request;return title('Requisitions','Requested → Reviewed → Approved → Reserved → Picked → Received. Requisitions do not use an In Transit state.','<button class="sl-btn" id="refreshReqs">Refresh</button>')+(canCreate?'<div class="sl-panel sl-request-create-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">BRANCH REQUEST</div><h3>Seek Stock — System Sourcing</h3><small>The ERP compares Central Warehouse first and eligible peer-branch stock second. It only advises; approval, reservation and transfer remain in the existing SOP workflow.</small></div><span class="sl-workflow-chip">EXTERNAL POS REMAINS SEPARATE</span></div><form id="v16ReqForm" class="sl-form"><div class="sl-field full"><label>Requesting branch</label><select name="location_id">'+((state.boot.user.can_all?((state.boot.locations||[]).filter(l=>l.location_type==='Branch'&&Number(l.is_active)!==0)):(state.boot.locations||[]).filter(l=>l.location_type==='Branch'&&(state.boot.user.location_ids||[]).map(Number).includes(Number(l.id))))).map(l=>'<option value="'+l.id+'" '+(Number(l.id)===Number(state.boot.user.location_ids?.[0]||state.boot.user.branch_location_id||0)?'selected':'')+'>'+esc(l.location_name)+'</option>').join('')+'</select><small class="sl-sub">Only your authorized branch scope is available.</small></div><div id="v16ReqItems"></div><div class="sl-field full"><button class="sl-btn" type="button" id="addV16ReqItem">+ Add item</button></div><div class="sl-field full"><label>Request note</label><textarea name="notes" rows="3" placeholder="Customer demand, priority or operational reason"></textarea></div><div class="sl-field full"><button class="sl-btn primary" type="submit">SUBMIT BRANCH REQUISITION</button></div></form></div>':'')+'<div class="sl-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">ORDER CONTROL</div><h3>Requisition queue</h3></div><small>Role, workflow state and live inventory availability are enforced by the ERP.</small></div><div id="reqTable"><div class="sl-empty">Loading requisitions…</div></div></div>'}


async function submitPOS(e){
    e.preventDefault();
    const f=e.currentTarget;
    if(f.dataset.busy==='1')return;
    f.dataset.busy='1';
    const btn=f.querySelector('button[type="submit"]');
    const old=btn?.textContent;
    if(btn){btn.disabled=true;btn.textContent='POSTING…';}
    const d=Object.fromEntries(new FormData(f));
    d.quantity_sold=Number(d.quantity_sold);
    d.branch_location_id=Number(d.branch_location_id||0);
    if(d.selling_price!=='')d.selling_price=Number(d.selling_price);else delete d.selling_price;
    // Keep one stable sale_id for retries on this form. If the response is lost after
    // the server commits, retrying identical details recovers the original sale.
    if(!f.dataset.saleRequestId){const requestSuffix=window.crypto&&typeof window.crypto.getRandomValues==='function'?Array.from(window.crypto.getRandomValues(new Uint8Array(6)),v=>v.toString(16).padStart(2,'0')).join('').toUpperCase():Math.random().toString(36).slice(2,14).toUpperCase();f.dataset.saleRequestId=('P'+Date.now().toString(36).toUpperCase().slice(-7)+requestSuffix).slice(0,20);}
    d.sale_id=f.dataset.saleRequestId;
    try{
        const out=await api16('/sales',{method:'POST',body:JSON.stringify(d)});
        const saleId=String(out?.sale_id||'').trim();
        const postingStatus=String(out?.posting_status||'').toUpperCase();
        if(!saleId||postingStatus!=='CLOSED'){
            const msg='The ERP response did not confirm a CLOSED POS sale. Check Recent Posted Sales before retrying to avoid a duplicate; no automatic retry was sent.';
            const box=document.getElementById('posResult');
            if(box)box.innerHTML='<div class="sl-notice sl-danger-block"><b>POS result needs verification.</b><div>'+esc(msg)+'</div></div>';
            toast(msg,true);
            return;
        }
        // Clear the retry key only after the server confirms a persisted CLOSED sale.
        delete f.dataset.saleRequestId;
        const box=document.getElementById('posResult');
        if(box)box.innerHTML='<div class="sl-notice"><b>Sale '+esc(saleId)+' posted and verified.</b> Gross '+money(out.sales_total||0)+' • VAT '+money(out.vat_amount||0)+' • Net '+money(out.net_sales_excl_vat||0)+'. Inventory, GL and audit were committed atomically.</div>';
        toast('Retail POS sale posted and verified: '+saleId);
        f.reset();
        const hidden=f.querySelector('[data-sku-value]');if(hidden){hidden.value='';hidden.dispatchEvent(new Event('input',{bubbles:true}));hidden.dispatchEvent(new Event('change',{bubbles:true}));}
        const skuSearch=f.querySelector('[data-sku-search]');if(skuSearch)skuSearch.value='';
        const selected=f.querySelector('[data-sku-selected]');if(selected)selected.hidden=true;
        const clear=f.querySelector('[data-sku-clear]');if(clear)clear.hidden=true;
        const date=f.querySelector('[name="sale_date"]');if(date)date.value=localDate();
        // Posting is already confirmed. A later refresh error must not imply the sale
        // failed or was not created; tell the user which step failed separately.
        try{await loadPosSales();await load();await loadNotifications();}
        catch(refreshErr){toast('Sale '+saleId+' was posted successfully. The dashboard refresh failed: '+String(refreshErr?.message||'refresh unavailable')+'. Do not repost this sale.',true);}
    }catch(err){toast(err.message,true)}finally{f.dataset.busy='0';if(btn){btn.disabled=false;btn.textContent=old||'POST RETAIL SALE';}}
}
async function loadPosSales(){
    const el=document.getElementById('posSalesTable');if(!el)return;
    const f=document.getElementById('posCartForm')||document.getElementById('posForm');
    const date=f?.querySelector('[name="sale_date"]')?.value||localDate();
    const branch=f?.querySelector('[name="branch_location_id"]')?.value||'';
    el.innerHTML='<div class="sl-empty">Loading posted sales…</div>';
    try{
        const selectedSale=state.globalSearchSelection?.type==='sale'?state.globalSearchSelection:null;
        const effectiveDate=selectedSale?.saleDate||date;
        const effectiveBranch=selectedSale?.locationId||branch;
        const qs='?date='+encodeURIComponent(effectiveDate)+(effectiveBranch?'&branch_location_id='+encodeURIComponent(effectiveBranch):'');
        const rows=await api16('/sales'+qs,{cache:'no-store'});
        const scopedRows=Array.isArray(rows)?rows:[];
        state.posSales=selectedSale?scopedRows.filter(x=>String(x.sale_id||'')===String(selectedSale.query||'')):scopedRows;
        const canCost=!!state.boot.user.can_cost;
        el.innerHTML=state.posSales.length?'<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Sale</th><th>SKU</th><th>Qty</th><th>Unit</th><th>VAT</th><th>Gross</th><th>Status</th>'+(canCost?'<th>COGS / GP</th>':'')+'</tr></thead><tbody>'+state.posSales.map(x=>'<tr><td data-label="Sale"><b>'+esc(x.sale_id)+'</b><div class="sl-sub">'+esc(x.sale_date)+'</div></td><td data-label="SKU"><b>'+esc(x.sku)+'</b><div class="sl-sub">'+esc((x.size||'')+(x.color?' • '+x.color:''))+'</div></td><td data-label="Qty">'+esc(x.quantity_sold)+'</td><td data-label="Unit">'+money(x.selling_price)+'</td><td data-label="VAT">'+money(x.vat_amount)+'</td><td data-label="Gross"><b>'+money(x.sales_total)+'</b></td><td data-label="Status"><span class="sl-pill '+(x.posting_status==='CLOSED'?'ok':'warn')+'">'+esc(x.posting_status)+'</span></td>'+(canCost?'<td data-label="COGS / GP">'+money(x.cogs||0)+' / '+money(x.gross_profit||0)+'</td>':'')+'</tr>').join('')+'</tbody></table></div>':(selectedSale?'<div class="sl-empty-rich"><b>The selected sale was not returned in your authorized scope.</b><span>It may have changed or the current profile may no longer be authorized to view it. No new transaction was posted.</span> <button class="sl-btn sl-btn-sm" type="button" data-clear-global-search>Clear search</button></div>':'<div class="sl-empty-rich"><b>No posted sales for '+esc(date)+'.</b><span>Use the checkout above or external Daily POS Import.</span></div>');
    }catch(e){el.innerHTML='<div class="sl-notice sl-danger-block">'+esc(e.message)+'</div>'}
}
function shiftCalendar(delta){
    const base=(state.calendarMonth||localMonthStart().slice(0,7))+'-01';const d=new Date(base+'T00:00:00');d.setMonth(d.getMonth()+delta);state.calendarMonth=localDate(d).slice(0,7);loadBusinessCalendar(state.calendarMonth);
}
async function loadBusinessCalendar(month){
    const el=document.getElementById('businessCalendarGrid');if(!el)return;
    state.calendarMonth=month;
    el.innerHTML='<div class="sl-empty">Loading Business Calendar…</div>';
    try{
        const d=await api16('/business-calendar?month='+encodeURIComponent(month),{cache:'no-store'});state.calendarData=d;
        const days=d.days||[],cfg=d.config||{};const titleEl=document.getElementById('calendarMonthTitle');if(titleEl)titleEl.textContent=month;
        const wk=new Set((cfg.weekend_days||[]).map(Number));document.querySelectorAll('[name="weekend_day"]').forEach(x=>x.checked=wk.has(Number(x.value)));
        el.innerHTML='<div class="sl-calendar-legend"><span class="sl-pill ok">OPEN</span><span class="sl-pill bad">CLOSED</span><span class="sl-sub">Open override wins over holiday/weekend. POS, Daily POS Import and Daily Closing all use this policy.</span></div><div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Date</th><th>Status</th><th>Reason</th><th>Label</th><th>Action</th></tr></thead><tbody>'+days.map(x=>'<tr><td data-label="Date"><b>'+esc(x.date)+'</b></td><td data-label="Status"><span class="sl-pill '+(x.status==='OPEN'?'ok':'bad')+'">'+esc(x.status)+'</span></td><td data-label="Reason">'+esc(x.reason)+'</td><td data-label="Label">'+esc(x.label)+'</td><td data-label="Action"><button class="sl-btn sl-btn-sm" type="button" data-calendar-date="'+esc(x.date)+'" data-calendar-status="'+esc(x.status)+'" data-calendar-label="'+esc(x.label||'')+'">'+(x.status==='OPEN'?'CLOSE DATE':'OPEN DATE')+'</button></td></tr>').join('')+'</tbody></table></div>';
        el.querySelectorAll('[data-calendar-date]').forEach(b=>b.onclick=async()=>{const date=b.dataset.calendarDate,next=b.dataset.calendarStatus==='OPEN'?'CLOSED':'OPEN';const label=window.prompt(next==='CLOSED'?'Closure label':'Open override label',b.dataset.calendarLabel||'');if(label===null)return;b.disabled=true;try{await api16('/business-calendar/date',{method:'POST',body:JSON.stringify({date,status:next,label})});toast('Business Calendar updated for '+date);await loadBusinessCalendar(month)}catch(e){toast(e.message,true);b.disabled=false}});
    }catch(e){el.innerHTML='<div class="sl-notice sl-danger-block">'+esc(e.message)+'</div>'}
}
async function saveCalendarWeekends(e){
    e.preventDefault();const f=e.currentTarget;const btn=f.querySelector('button[type="submit"]');const weekend_days=[...f.querySelectorAll('[name="weekend_day"]:checked')].map(x=>Number(x.value));if(btn)btn.disabled=true;try{await api16('/business-calendar/weekends',{method:'POST',body:JSON.stringify({weekend_days})});toast('Weekend policy saved');await loadBusinessCalendar(state.calendarMonth||localMonthStart().slice(0,7))}catch(err){toast(err.message,true)}finally{if(btn)btn.disabled=false}
}
async function saveCalendarDate(e){
    e.preventDefault();const f=e.currentTarget;const d=Object.fromEntries(new FormData(f));const btn=f.querySelector('button[type="submit"]');if(btn)btn.disabled=true;try{await api16('/business-calendar/date',{method:'POST',body:JSON.stringify(d)});toast('Business Calendar date saved');state.calendarMonth=String(d.date||'').slice(0,7)||state.calendarMonth;await loadBusinessCalendar(state.calendarMonth);f.reset();const date=f.querySelector('[name="date"]');if(date)date.value=localDate()}catch(err){toast(err.message,true)}finally{if(btn)btn.disabled=false}
}

async function loadActivityFeed(){const el=document.getElementById('slActivityFeed');if(!el)return;try{const rows=await api('/app/notifications',{cache:'no-store'});if(!Array.isArray(rows)){el.innerHTML='<div class="sl-empty">No activity available.</div>';return}el.innerHTML=rows.slice(0,8).map(n=>{const type=String(n.entity_type||'Workflow event');const lower=type.toLowerCase();const icon=/product|sku/i.test(lower)?'product':/sale|pos/i.test(lower)?'cash':/receiv|stock|invent/i.test(lower)?'warehouse':/user|login/i.test(lower)?'users':/transfer/i.test(lower)?'transfer':'list';return '<div class="sl-activity-row"><span class="sl-dashboard-activity-icon">'+uiIcon(icon)+'</span><div class="sl-activity-main"><b>'+esc(type)+'</b><span>'+esc((n.from_status||'')+(n.to_status?' → '+n.to_status:''))+'</span><small>'+esc(n.display_name||'System')+(n.location_name?' • '+esc(n.location_name):'')+'</small></div><time>'+esc(n.created_at||'')+'</time></div>'}).join('')||'<div class="sl-empty">No workflow events yet.</div>'}catch(e){el.innerHTML='<div class="sl-empty">Activity feed unavailable.</div>'}}
const refreshLiveIndicators=debounce(async()=>{try{await loadNotifications();if(document.getElementById('slActivityFeed'))await loadActivityFeed();const si=document.getElementById('slSyncIndicator');if(si){si.classList.add('pulse');setTimeout(()=>si.classList.remove('pulse'),700)}}catch(e){}} ,400);
if(!window.__stocklandLiveTimer){window.__stocklandLiveTimer=setInterval(()=>{if(document.visibilityState==='visible')refreshLiveIndicators()},60000);document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')refreshLiveIndicators()});window.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();openCommandPalette()}if(e.key==='Escape'){document.getElementById('slCommandPalette')?.remove()}})}
function notificationDrawer(rows){
  const unread = Array.isArray(rows) ? rows : [];
  const cards = unread.map(n => {
    const entity = String(n.entity_type || '').toLowerCase();
    const isReq = entity === 'requisition' && String(n.entity_id || '') !== '';
    const isPO = entity === 'purchase_order' && String(n.entity_id || '') !== '';
    const titleText = String(n.title || 'Notification').trim() || 'Notification';
    const messageText = String(n.message || '').trim();
    const showMessage = messageText && messageText.toLowerCase() !== titleText.toLowerCase();
    const metadata = [n.created_at, n.location_name].filter(Boolean).map(String).join(' • ');
    const reviewAction = isReq
      ? '<button type="button" class="sl-btn sl-btn-sm sl-btn-gold" data-review-notification="'+esc(n.entity_id)+'" data-review-notification-id="'+esc(n.id)+'">REVIEW &amp; DECIDE</button>'
      : (isPO ? '<button type="button" class="sl-btn sl-btn-sm sl-btn-gold" data-review-purchase-order="'+esc(n.entity_id)+'" data-review-purchase-order-id="'+esc(n.id)+'">OPEN PURCHASE ORDER</button>' : '');
    return '<article class="sl-notification-card '+esc(n.severity || 'info')+'">'+
      '<div class="sl-alert-main"><span class="sl-alert-dot" aria-hidden="true"></span><div>'+
      '<strong>'+esc(titleText)+'</strong>'+
      (showMessage ? '<div class="sl-sub">'+esc(messageText)+'</div>' : '')+
      (metadata ? '<small>'+esc(metadata)+'</small>' : '')+
      '</div></div><div class="sl-notification-actions">'+reviewAction+
      '<button type="button" class="sl-btn sl-btn-sm" data-read-notification="'+esc(n.id)+'">Mark read</button></div></article>';
  }).join('');
  return '<div class="sl-drawer sl-alert-drawer"><div class="sl-drawer-head"><div>'+
    '<div class="sl-eyebrow">CONTROL CENTER</div><h2>Alerts &amp; approvals</h2>'+
    '<p>Review alerts and open the linked ERP task.</p></div>'+
    '<button type="button" class="sl-icon-btn" id="closeNotificationDrawer" aria-label="Close alerts">×</button></div>'+
    '<div class="sl-alert-summary"><span><b>'+esc(unread.length)+'</b> unread</span><span>Newest first</span></div>'+
    (unread.length ? '<div class="sl-alert-list">'+cards+'</div>' :
      '<div class="sl-empty-rich" role="status"><div class="sl-empty-icon">'+uiIcon('audit')+'</div><b>All clear</b><span>No unread operational alerts.</span></div>')+
    '</div>';
}
async function loadNotifications(){
  try{
    const rows=await api16('/notifications',{cache:'no-store'});state.notifications=Array.isArray(rows)?rows:[];state.unreadNotifications=state.notifications.length;const newest=rows[0]?.id||0;
    if(state.lastNotificationId&&newest&&String(newest)!==String(state.lastNotificationId)&&document.visibilityState==='visible')openOpsToast(rows[0].title||'New workflow alert',rows[0].message||'A new ERP workflow event is available.','OPEN ALERTS','notifications');
    if(newest)state.lastNotificationId=newest;
    const countEl=document.getElementById('notificationCount');if(countEl){countEl.textContent=String(state.unreadNotifications);countEl.hidden=state.unreadNotifications===0}const owner=document.getElementById('ownerUnreadCount');if(owner)owner.textContent=String(state.unreadNotifications);
    const trigger=document.getElementById('notificationBell');if(trigger&&!trigger.__slBound){trigger.__slBound=1;trigger.onclick=async()=>{try{const latest=await api16('/notifications',{cache:'no-store'});state.notifications=Array.isArray(latest)?latest:[];state.unreadNotifications=state.notifications.length;const ov=document.createElement('div');ov.className='sl-overlay';ov.innerHTML=notificationDrawer(state.notifications);document.body.appendChild(ov);const close=()=>ov.remove();document.getElementById('closeNotificationDrawer')?.addEventListener('click',close);ov.onclick=e=>{if(e.target===ov)close();};ov.querySelectorAll('[data-review-notification]').forEach(btn=>btn.onclick=async()=>{const id=btn.dataset.reviewNotification,nid=btn.dataset.reviewNotificationId;btn.disabled=true;try{await api16('/notifications/'+encodeURIComponent(nid)+'/read',{method:'POST'});close();await openReqDetailV16(id);await loadNotifications();}catch(err){toast(err.message,true);btn.disabled=false}});ov.querySelectorAll('[data-review-purchase-order]').forEach(btn=>btn.onclick=async()=>{const id=Number(btn.dataset.reviewPurchaseOrder),nid=btn.dataset.reviewPurchaseOrderId,role=String(state.boot?.user?.role||'');btn.disabled=true;try{await api16('/notifications/'+encodeURIComponent(nid)+'/read',{method:'POST'});close();state.view=role==='warehouse_manager'?'warehouse':'finance';render();await loadNotifications();if(role==='warehouse_manager'){await openPOReceiveDialog(id);}else{await loadPurchaseOrders();const queue=document.getElementById('poActionQueue');if(queue)queue.scrollIntoView({behavior:'smooth',block:'start'});}}catch(err){toast(err.message,true);btn.disabled=false}});ov.querySelectorAll('[data-read-notification]').forEach(btn=>btn.onclick=async()=>{btn.disabled=true;try{await api16('/notifications/'+encodeURIComponent(btn.dataset.readNotification)+'/read',{method:'POST'});const readCard=btn.closest('.sl-notification-card');if(readCard)readCard.remove();state.unreadNotifications=Math.max(0,state.unreadNotifications-1);const alertSummary=ov.querySelector('.sl-alert-summary b');if(alertSummary)alertSummary.textContent=String(state.unreadNotifications);if(countEl){countEl.textContent=String(state.unreadNotifications);countEl.hidden=state.unreadNotifications===0}const o=document.getElementById('ownerUnreadCount');if(o)o.textContent=String(state.unreadNotifications);if(!ov.querySelector('.sl-notification-card')){const list=ov.querySelector('.sl-alert-list');if(list)list.outerHTML='<div class="sl-empty-rich" role="status"><div class="sl-empty-icon">'+uiIcon('audit')+'</div><b>All shown alerts reviewed</b><span>Close and reopen Alerts to refresh the list.</span></div>'} }catch(err){toast(err.message,true);btn.disabled=false}})}catch(err){toast(err.message,true)}}}
  }catch(e){state.unreadNotifications=0;const c=document.getElementById('notificationCount');if(c){c.textContent='0';c.hidden=true}}
}

function toast(msg,bad){let t=document.getElementById('toast');if(!t){t=document.createElement('div');t.id='toast';document.body.appendChild(t)}t.className='sl-toast '+(bad?'bad':'ok');t.style.display='block';t.textContent=msg;clearTimeout(window.__stocklandToast);window.__stocklandToast=setTimeout(()=>{t.style.display='none'},3600)}

load();})();
