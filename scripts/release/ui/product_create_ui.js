#!/usr/bin/env node
'use strict';
// Real-browser product creation: click "+ New Product / Variant", fill the form, save, and confirm the product is in the catalog.
// Usage: node product_create_ui.js <base-url> <wp-user> <password> [screenshot-dir]. Disposable site with synthetic data only.
const fs = require('node:fs');
const { chromium } = require('playwright');
const [baseArg, user, password, shotDir = '.'] = process.argv.slice(2);
if (!baseArg || !user || !password) { console.error('usage: product_create_ui.js <base-url> <user> <password> [dir]'); process.exit(2); }
const origin = new URL(baseArg).origin; fs.mkdirSync(shotDir, { recursive: true });
let failed = 0; const ok = (m) => console.log('PASS ' + m); const bad = (m) => { failed++; console.log('FAIL ' + m); };
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 } })).newPage(); page.setDefaultTimeout(15000);
    const errors = []; const track = () => page.on('pageerror', (e) => String(e.stack || '').includes('/wp-admin/js/') || errors.push(String(e) + ' @ ' + String(e.stack || '').split('\n').slice(1, 3).join(' ; ')));
    await page.goto(origin + '/wp-login.php'); await page.fill('#user_login', user); await page.fill('#user_pass', password);
    await Promise.all([page.waitForURL((u) => u.pathname.includes('/wp-admin/')), page.click('#wp-submit')]);
    track();
    await page.goto(origin + '/stockland-erp-app/', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('.sl-nav [data-view="products"]'); ok('ERP shell loaded');
    const quick = page.locator('.sl-dashboard-action[data-open-product]');
    if (await quick.count()) { await quick.first().click(); await page.waitForSelector('#productForm', { timeout: 8000 }).then(() => ok('dashboard Add Product opens the new product form'), () => bad('dashboard Add Product did not open the form')); await page.click('#closeProduct'); }
    await page.click('.sl-nav [data-view="products"]');
    await page.waitForSelector('#newProduct'); ok('Product Master shows the New Product button');
    await page.click('#newProduct');
    await page.waitForSelector('#productForm', { timeout: 8000 }).then(() => ok('clicking New Product opens the form'), () => bad('clicking New Product did not open the form'));
    const sku = 'STK-UIT-' + Date.now().toString(36).toUpperCase();
    await page.screenshot({ path: shotDir + '/product-form.png' });
    const form = page.locator('#productForm');
    const preset = await form.locator('[name="sku"]').inputValue();
    preset === 'STK-' ? ok('SKU field starts with the required STK- prefix') : bad('SKU field should start with STK- but has ' + preset);
    await form.locator('[name="sku"]').fill('PLAIN-1'); await form.locator('[name="product_name"]').fill('x');
    await form.locator('[type="submit"]').first().click();
    await page.waitForTimeout(400);
    const inline = await page.locator('#productFormError').isVisible().catch(() => false);
    const hint = await form.locator('[name="sku"]').evaluate((e) => e.validationMessage).catch(() => '');
    (inline || hint) ? ok('a SKU without STK- is explained on the form (' + (inline ? 'inline message' : 'browser validation') + ')') : bad('a SKU without STK- gave no visible explanation');
    await form.locator('[name="sku"]').fill(sku); await form.locator('[name="product_name"]').fill('UI test product ' + sku);
    for (const [n, v] of [['purchase_cost', '100'], ['retail_price', '180'], ['wholesale_price', '150']]) { const f = form.locator('[name="' + n + '"]'); if (await f.count()) await f.fill(v); }
    const resp = page.waitForResponse((r) => /\/products/.test(r.url()) && r.request().method() === 'POST', { timeout: 10000 }).catch(() => null);
    await form.locator('[type="submit"]').first().click();
    const r = await resp;
    if (!r) bad('Save sent no create request');
    else { const body = await r.text().catch(() => ''); (r.status() < 300) ? ok('Save created the product (HTTP ' + r.status() + ')') : bad('Save failed HTTP ' + r.status() + ' ' + body.slice(0, 300)); }
    const found = await page.evaluate(async (s) => { const c = window.StocklandApp; const x = await fetch(c.api + '/products/' + encodeURIComponent(s), { headers: { 'X-WP-Nonce': c.nonce }, credentials: 'same-origin' }); return x.status; }, sku).catch(() => 0);
    found === 200 ? ok('the new product is retrievable from the catalog') : bad('the new product was not found (HTTP ' + found + ')');
    await page.screenshot({ path: shotDir + '/after-save.png' });
    errors.length ? bad('page errors: ' + errors.join(' | ').slice(0, 300)) : ok('no uncaught page errors');
  } finally { await browser.close(); }
  console.log(failed ? 'RESULT FAIL (' + failed + ')' : 'RESULT PASS'); process.exit(failed ? 1 : 0);
})();
