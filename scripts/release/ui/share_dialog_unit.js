// Share dialog + vacancy composer: runs the real app.js code in a browser page. No network: window.open is stubbed.
// Usage: NODE_PATH=<playwright> node scripts/release/ui/share_dialog_unit.js
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const src = fs.readFileSync(path.join(__dirname, '../../../src/stockland-erp-app/assets/app.js'), 'utf8');
const cut = (from, to) => { const a = src.indexOf(from); const b = src.indexOf(to, a); if (a < 0 || b < 0) throw new Error('marker missing: ' + from); return src.slice(a, b); };
const code = 'const esc=v=>String(v).replace(/[&<>"\']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;","\'":"&#39;"}[c]));window.__toasts=[];const toast=(m,e)=>window.__toasts.push(String(m));\n'
  + 'const state={boot:{settings:{company_legal_name:"Demo Trading PLC",careers_url:"https://example.com/careers",company_email:"hr@example.com"}},hrLifecycle:{locations:[{id:3,location_name:"Branch A",profile:{city:"Adama"}}],lists:{positions:[{id:7,title:"Store Keeper"}],requisitions:[{id:11,position_id:7,location_id:3,openings:2,employment_type:"FULL_TIME"}]}}};\n'
  + cut('const SL_SHARE_NETWORKS', 'function nextVersionLabel') + '\nwindow.__n=SL_SHARE_NETWORKS;window.slOpenVacancyShare=slOpenVacancyShare;window.slOpenResourceShare=slOpenResourceShare;window.slVacancyText=slVacancyText;';
let fail = 0;
const check = (l, c, d) => { console.log((c ? 'PASS ' : 'FAIL ') + l + (c ? '' : ' :: ' + JSON.stringify(d))); if (!c) fail++; };
(async () => {
  const browser = await chromium.launch(); const page = await browser.newPage(); const errors = [];
  page.on('pageerror', e => errors.push(String(e)));
  await page.setContent('<body></body>');
  await page.evaluate(() => { window.__opened = []; window.open = (u) => { window.__opened.push(u); return null; }; });
  await page.addScriptTag({ content: code });
  const ids = await page.evaluate(() => window.__n.map(n => n.id));
  for (const id of ['facebook','telegram','tiktok','youtube','pinterest','google','gmb','twitter','x','instagram','threads','snapchat','linkedin']) {
    if (id === 'google') continue; check('network present: ' + id, ids.includes(id), ids);
  }
  await page.evaluate(() => window.slOpenVacancyShare(11));
  const text = await page.$eval('#slShareText', e => e.value);
  check('vacancy text has title, company, branch, openings, how to apply', /Store Keeper/.test(text) && /Demo Trading PLC/.test(text) && /Branch A/.test(text) && /2 openings/.test(text) && /hr@example\.com/.test(text), text);
  check('careers link prefilled', (await page.$eval('#slShareUrl', e => e.value)) === 'https://example.com/careers');
  await page.fill('#slShare_deadline', '2026-12-31');
  check('deadline recomposes message', /Apply by: 2026-12-31/.test(await page.$eval('#slShareText', e => e.value)));
  const expect = { facebook: 'facebook.com/sharer', x: 'x.com/intent/post', twitter: 'twitter.com/intent/tweet', linkedin: 'linkedin.com/feed', telegram: 't.me/share/url', whatsapp: 'wa.me/', pinterest: 'pinterest.com/pin/create', threads: 'threads.net/intent/post', snapchat: 'snapchat.com/scan', email: 'mailto:' };
  for (const [id, frag] of Object.entries(expect)) {
    await page.click('[data-network=' + id + ']');
    const u = await page.evaluate(() => window.__opened.pop());
    check('share ' + id + ' opens real intent', !!u && u.includes(frag) && (id === 'snapchat' || decodeURIComponent(u).includes('Store Keeper')), u);
  }
  for (const [id, host] of Object.entries({ instagram: 'instagram.com', tiktok: 'tiktok.com', youtube: 'youtube.com', gmb: 'business.google.com' })) {
    await page.click('[data-network=' + id + ']');
    const u = await page.evaluate(() => window.__opened.pop());
    check(id + ' opens platform (message copied)', !!u && u.includes(host) && /copied|Could not copy/.test(await page.textContent('#slShareStatus')), u);
  }
  await page.fill('#slShareUrl', 'javascript:alert(1)');
  check('non-https link blocks URL-only networks', await page.$eval('[data-network=facebook]', e => e.disabled) && await page.$eval('[data-network=snapchat]', e => e.disabled));
  await page.fill('#slShareUrl', 'https://example.com/careers?a=1&b=2');
  await page.click('[data-network=facebook]');
  const fb = await page.evaluate(() => window.__opened.pop());
  check('link is URL-encoded', fb.includes(encodeURIComponent('https://example.com/careers?a=1&b=2')), fb);
  await page.click('#slShareClose');
  check('dialog closes', (await page.$('.sl-share')) === null);
  await page.evaluate(() => window.slOpenResourceShare({ dataset: { title: 'Leave Policy', resourceCode: 'HR-1' } }));
  const privNets = await page.$$eval('[data-network]', els => els.map(e => e.dataset.network));
  check('private HR documents offer only private channels', privNets.sort().join() === 'email,telegram,whatsapp', privNets);
  check('no page errors', errors.length === 0, errors);
  await browser.close(); process.exit(fail ? 1 : 0);
})();
