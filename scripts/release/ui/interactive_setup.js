// Interactive browser acceptance: install the ZIP through wp-admin, activate, open System Control, authorize live operation as Owner.
// Disposable site only. Usage: node interactive_setup.js <site-url> <admin-user> <admin-pass> <zip> <shots-dir>
const { chromium } = require('playwright');
const path = require('path');
(async () => {
  const [base, user, pass, zip, shots] = process.argv.slice(2);
  const b = await chromium.launch(); const ctx = await b.newContext({ viewport: { width: 1366, height: 900 } }); const p = await ctx.newPage();
  p.setDefaultTimeout(120000); const fails = []; const step = (n, ok, d = '') => { console.log((ok ? 'PASS ' : 'FAIL ') + n + (ok ? '' : ' :: ' + d)); if (!ok) fails.push(n); };
  await p.goto(base + '/wp-login.php'); await p.fill('#user_login', user); await p.fill('#user_pass', pass); await p.click('#wp-submit'); await p.waitForURL(/wp-admin/, { waitUntil: 'commit' });
  await p.goto(base + '/wp-admin/plugin-install.php?tab=upload', { waitUntil: 'domcontentloaded' });
  await p.setInputFiles('input#pluginzip', path.resolve(zip)); await p.click('#install-plugin-submit');
  await p.waitForSelector('a.button-primary:has-text("Activate Plugin")'); step('plugin uploaded through wp-admin', true);
  await p.click('a.button-primary:has-text("Activate Plugin")'); await p.waitForLoadState('domcontentloaded');
  const act = (await p.locator('body').innerText()).slice(0, 4000);
  step('plugin activates without error', /Plugin activated|activated/i.test(act) && !/fatal error|Parse error/i.test(act), act.slice(0, 200));
  await p.goto(base + '/wp-admin/admin.php?page=stockland-system-control', { waitUntil: 'domcontentloaded' });
  let sc = await p.locator('body').innerText();
  step('System Control opens for the administrator', /System Control/.test(sc), sc.slice(0, 160));
  step('one-click installation reports complete during activation', /Installation complete/.test(sc), (sc.match(/One-click installation[^\n]*\n[^\n]*/i) || [''])[0]);
  step('86 application tables reported', /86/.test(sc));
  await p.screenshot({ path: path.join(shots, 'interactive-system-control.png'), fullPage: true });
  if (await p.locator('#stockland_release_reason').count()) {
    await p.fill('#stockland_release_reason', 'Interactive acceptance on a disposable site'); await p.check('input[name="stockland_release_confirm"]'); await p.click('button[name="stockland_authorize_release"]'); await p.waitForLoadState('domcontentloaded');
    sc = await p.locator('body').innerText(); step('Owner authorization recorded through the form', /authorized for live operation|Live operation authorized/i.test(sc), sc.slice(0, 200));
  } else { step('authorization form shown to the Owner', false, 'form missing'); }
  await p.goto(base + '/?pagename=stockland-erp-app', { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(3000);
  const app = await p.locator('body').innerText(); step('ERP app shell renders after setup', /Dashboard|Inventory|Sales/i.test(app), app.slice(0, 120));
  await p.screenshot({ path: path.join(shots, 'interactive-app.png') });
  await b.close(); console.log(fails.length ? 'RESULT FAIL (' + fails.length + ')' : 'RESULT PASS'); process.exit(fails.length ? 1 : 0);
})();
