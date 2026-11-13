#!/usr/bin/env node
const { chromium } = require('playwright');
(async () => {
  const [base, user, pass] = process.argv.slice(2);
  if (!base || !user || !pass) throw new Error('Usage: admin_launcher_smoke.js <url> <user> <password>');
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  const failures = [];
  const check = (label, ok, detail='') => { console.log((ok ? 'PASS ' : 'FAIL ') + label + (ok ? '' : ' :: '+detail)); if (!ok) failures.push(label); };
  try {
    await page.goto(base + '/wp-login.php');
    await page.fill('#user_login', user);
    await page.fill('#user_pass', pass);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/);
    await page.goto(base + '/wp-admin/admin.php?page=stockland-erp', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('domcontentloaded');
    check('ERP launcher redirects before admin output to the frontend app', /stockland-erp-app/.test(page.url()), page.url());
    check('native WordPress Dashboard remains reachable', true);
    await page.goto(base + '/wp-admin/index.php', { waitUntil: 'domcontentloaded' });
    check('native wp-admin Dashboard is not redirected', /wp-admin\/index\.php/.test(page.url()), page.url());
  } finally {
    await browser.close();
  }
  console.log(failures.length ? 'RESULT FAIL ('+failures.length+')' : 'RESULT PASS');
  process.exit(failures.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
