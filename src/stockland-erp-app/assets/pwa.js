(function(){
  'use strict';
  const A=window.StocklandApp||{};
  let deferredPrompt=null;
  let installationComplete=false;
  const isStandalone=()=>window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
  const isIOS=()=>/iphone|ipad|ipod/i.test(navigator.userAgent);
  const isMobile=()=>/android|iphone|ipad|ipod/i.test(navigator.userAgent);
  function addInstallButton(){
    if(installationComplete||isStandalone()) return;
    const mobile=isMobile() || window.innerWidth<=767;
    const host=mobile
      ? document.querySelector('.sl-mobile-menu .sl-mobile-menu-foot')
      : (document.querySelector('.sl-profile-menu')||document.querySelector('.sl-user'));
    if(!host || document.getElementById('sl-pwa-install'))return;
    const btn=document.createElement('button');
    btn.type='button'; btn.id='sl-pwa-install'; btn.className='sl-btn sl-pwa-install'+(mobile?' sl-pwa-install-mobile':'');
    btn.textContent=mobile?(isIOS()?'ADD HOME':'INSTALL'):(isIOS()?'Add to Home':'Install App');
    btn.title=isIOS()?'Add Stockland ERP to your home screen':'Install Stockland ERP as an app';
    btn.setAttribute('aria-label',btn.title);
    btn.addEventListener('click',async()=>{
      if(deferredPrompt){
        const promptEvent=deferredPrompt;
        try{
          await promptEvent.prompt();
          const choice=await promptEvent.userChoice;
          if(choice&&choice.outcome==='accepted') installationComplete=true;
        }catch(e){}
        deferredPrompt=null; btn.remove();
        // The shell mutation observer must not recreate an install control
        // after the browser has accepted installation in the current tab.
        if(installationComplete) return;
        return;
      }
      const overlay=document.createElement('div');
      overlay.className='sl-overlay';
      const ua=String(navigator.userAgent||'');
      const isWindows=/windows/i.test(ua);
      const isAndroid=/android/i.test(ua);
      const help=isIOS()
        ? 'On iPhone/iPad: tap Share, then choose “Add to Home Screen”. Stockland opens from its own home-screen icon.'
        : (isAndroid
          ? 'On Android: use the Install prompt when it appears, or the browser menu → Install app / Add to Home screen. This installs the secure online ERP as a standalone app; it does not download an APK.'
          : (isWindows
            ? 'On Windows: choose Install app from the browser install prompt/menu. Stockland opens in its own app window and can be pinned to Start/taskbar; this is a PWA install, not an .exe installer.'
            : 'Choose the browser’s Install app option when available to add Stockland as a standalone app.'));
      overlay.innerHTML='<div class="sl-drawer sl-pwa-help"><div class="sl-page-title"><div><div class="sl-eyebrow">STOCKLAND ETHIOPIA</div><h2 style="font-size:21px">Install Stockland ERP</h2><p>'+help+'</p></div><button class="sl-btn" id="sl-pwa-close" type="button">Close</button></div><div class="sl-notice"><b>Real standalone installation.</b> The installed ERP keeps the live database online. It does not create a second offline copy of inventory or sales data. Native EXE/APK packages are not offered until separately built and signed.</div></div>';
      document.body.appendChild(overlay);
      overlay.onclick=e=>{if(e.target===overlay)overlay.remove()};
      const close=overlay.querySelector('#sl-pwa-close'); if(close)close.onclick=()=>overlay.remove();
    });
    if(mobile)host.appendChild(btn); else host.insertBefore(btn,host.querySelector('.sl-exit')||null);
  }

  function observeShell(){
    addInstallButton();
    const root=document.getElementById('stockland-app');
    if(!root||window.__stocklandPwaObserver)return;
    window.__stocklandPwaObserver=new MutationObserver(()=>addInstallButton());
    window.__stocklandPwaObserver.observe(root,{subtree:true,childList:true});
  }
  window.addEventListener('stockland:mobile-menu-open',()=>addInstallButton());
  window.addEventListener('resize',()=>{ if(!isStandalone()) { const b=document.getElementById('sl-pwa-install'); if(b) b.remove(); addInstallButton(); } });
  window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();deferredPrompt=e;addInstallButton();});
  window.addEventListener('appinstalled',()=>{installationComplete=true;deferredPrompt=null;const b=document.getElementById('sl-pwa-install');if(b)b.remove();});
  function publishPwaStatus(status,extra={}){
    const state=Object.assign({supported:('serviceWorker' in navigator),status,scope:'',errorName:''},extra);
    window.StocklandPwaStatus=state;
    try{window.dispatchEvent(new CustomEvent('stockland:pwa-status',{detail:state}));}catch(_e){}
  }
  async function registerShellServiceWorker(){
    if(!('serviceWorker' in navigator)){publishPwaStatus('unsupported');return;}
    if(!A.sw || !A.home){publishPwaStatus('not-configured');return;}
    if(!window.isSecureContext){publishPwaStatus('insecure-context');return;}
    try{
      const origin=window.location.origin;
      const home=new URL(A.home,origin);
      const worker=new URL(A.sw,origin);
      if(home.origin!==origin || worker.origin!==origin){publishPwaStatus('blocked-origin');return;}
      // The worker must be served from the ERP entry route and may control only
      // that known subdirectory, never the WordPress site root or a sibling app.
      const path=home.pathname.endsWith('/')?home.pathname:(home.pathname+'/');
      if(path==='/' || !/\/stockland(?:-erp(?:-app)?)?\/$/.test(path)){publishPwaStatus('blocked-scope');return;}
      if(worker.pathname!==path || worker.searchParams.get('stockland_pwa_sw')!=='1'){publishPwaStatus('blocked-worker-route',{scope:path});return;}
      const scopeUrl=new URL(path,origin).href;
      const existing=await navigator.serviceWorker.getRegistration(scopeUrl);
      const registration=await navigator.serviceWorker.register(worker.href,{scope:scopeUrl,updateViaCache:'none'});
      if(existing && typeof registration.update==='function'){try{await registration.update();}catch(_e){}}
      publishPwaStatus('registered',{scope:scopeUrl});
    }catch(e){publishPwaStatus('error',{errorName:(e&&e.name)?String(e.name):'UnknownError'});}
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>{observeShell();registerShellServiceWorker();}); else {observeShell();registerShellServiceWorker();}
})();
