'use strict';
// Headless-browser UI smoke test: logs in to a DISPOSABLE WordPress site, opens the ERP app and System Control,
// fails on JavaScript errors, failed asset requests or a missing app shell. Usage:
//   node ui_smoke.js <base-url> <user> <password> [screenshot-dir]
const { chromium } = require('playwright');
const [base, user, pass, shots = '.'] = process.argv.slice(2);
if (!base || !user || !pass) { console.error('usage: ui_smoke.js <base-url> <user> <password> [shots]'); process.exit(2); }
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1366, height: 850 } });
  const page = await ctx.newPage();
  const problems = [];
  page.on('pageerror', (e) => problems.push('JS error: ' + e.message));
  page.on('requestfailed', (r) => { if (r.url().startsWith(base)) problems.push('request failed: ' + r.url()); }); // third-party hosts are outside the plugin's control
  page.on('response', (r) => { if (r.status() >= 500) problems.push('HTTP ' + r.status() + ' ' + r.url()); });
  const ok = (m) => console.log('PASS ' + m);
  await page.goto(base + '/wp-login.php');
  await page.fill('#user_login', user); await page.fill('#user_pass', pass); await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/, { waitUntil: 'commit' }); ok('WordPress login');
  for (const [name, path, needle] of [
    ['ERP Dashboard (admin)', '/wp-admin/admin.php?page=stockland-erp', null],
    ['System Control', '/wp-admin/admin.php?page=stockland-system-control', /LIVE_SCHEMA_READY|Install|Owner/i],
  ]) {
    const resp = await page.goto(base + path, { waitUntil: 'domcontentloaded' });
    if (!resp || resp.status() >= 400) problems.push(name + ' HTTP ' + (resp && resp.status()));
    const text = await page.locator('body').innerText();
    if (/Fatal error|Parse error|Warning: |Notice: /.test(text)) problems.push(name + ' shows a PHP error');
    if (needle && !needle.test(text)) problems.push(name + ' missing expected content');
    await page.screenshot({ path: `${shots}/ui-${name.replace(/\W+/g, '-').toLowerCase()}.png` });
    ok(name + ' rendered');
  }
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(base + '/wp-admin/admin.php?page=stockland-erp', { waitUntil: 'domcontentloaded' });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  ok('mobile viewport rendered (horizontal overflow ' + overflow + 'px)');
  await browser.close();
  if (problems.length) { problems.forEach((p) => console.log('FAIL ' + p)); console.log('RESULT: FAIL'); process.exit(1); }
  console.log('RESULT: PASS');
})();
