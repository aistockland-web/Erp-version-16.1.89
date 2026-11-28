#!/usr/bin/env node
'use strict';

// Disposable WordPress acceptance: verify the published GitBook guide and create/read back one synthetic Product Master item.
const { chromium } = require('playwright');
const [baseArg, user, password] = process.argv.slice(2);
if (!baseArg || !user || !password) {
  console.error('usage: node product_create_ui.js <base-url> <wp-user> <qa-password>');
  process.exit(2);
}
const origin = new URL(baseArg).origin;
const manualUrl = 'https://stockland-one-member-plc.gitbook.io/docs-user-manual-16.2.18/';
let failed = 0;
const pass = (m) => console.log('PASS ' + m);
const fail = (m) => { failed++; console.log('FAIL ' + m); };
const check = (ok, m) => ok ? pass(m) : fail(m);

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
  const page = await context.newPage();
  page.setDefaultTimeout(15000);
  const pageErrors = [];
  page.on('pageerror', (error) => {
    const stack = String(error.stack || '');
    if (/user-profile\.min\.js/.test(stack) && /serialize/.test(String(error))) return;
    pageErrors.push(String(error) + ' ' + stack.split('\n').slice(1, 3).join(' ; '));
  });

  try {
    await page.goto(new URL('/wp-login.php', origin).toString(), { waitUntil: 'domcontentloaded' });
    await page.locator('#user_login').fill(user);
    await page.locator('#user_pass').fill(password);
    await Promise.all([
      page.waitForURL((url) => url.pathname.includes('/wp-admin/'), { waitUntil: 'domcontentloaded' }),
      page.locator('#wp-submit').click()
    ]);
    pass('disposable WordPress administrator login');

    await page.goto(new URL('/stockland-erp-app/', origin).toString(), { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#stockland-app');
    await page.waitForSelector('.sl-dashboard-side');
    pass('ERP Command Center loaded');

    const domEvidence = await page.evaluate(() => ({
      dashboardLinkCount: document.querySelectorAll('#slDashboardUserManualLink').length,
      profileLinkCount: document.querySelectorAll('#slUserManualLink').length,
      dashboardManualHeading: Array.from(document.querySelectorAll('.sl-dashboard-side h3')).map(x => x.textContent.trim()).slice(0, 8),
      sideHtml: (document.querySelector('.sl-dashboard-side')?.innerHTML || '').slice(0, 2600),
      profileMenuHtml: (document.querySelector('#slProfileMenu')?.innerHTML || '').slice(0, 1600)
    }));
    console.log('GUIDE DOM EVIDENCE ' + JSON.stringify(domEvidence));

    const card = page.locator('#slDashboardUserManualLink');
    const cardCount = await card.count();
    let cardData = null;
    if (cardCount === 1) cardData = await card.evaluate((a) => ({ href: a.href, target: a.target, rel: a.rel }));
    check(cardData && cardData.href === manualUrl && cardData.target === '_blank' &&
      cardData.rel.includes('noopener') && cardData.rel.includes('noreferrer'),
      'dashboard guide card links to the published 16.2.18 GitBook manual');

    const profileLink = page.locator('#slUserManualLink');
    const profileCount = await profileLink.count();
    let profileData = null;
    if (profileCount === 1) profileData = await profileLink.evaluate((a) => ({ href: a.href, target: a.target, rel: a.rel }));
    check(profileData && profileData.href === manualUrl && profileData.target === '_blank' &&
      profileData.rel.includes('noopener') && profileData.rel.includes('noreferrer'),
      'profile menu uses the same real GitBook manual link');

    if (cardCount === 1) {
      const popupPromise = context.waitForEvent('page');
      await card.click();
      const popup = await popupPromise;
      await popup.waitForURL((url) =>
        url.hostname === 'stockland-one-member-plc.gitbook.io' &&
        url.pathname.startsWith('/docs-user-manual-16.2.18'), { timeout: 10000 });
      const opened = new URL(popup.url());
      check(opened.hostname === 'stockland-one-member-plc.gitbook.io' &&
        opened.pathname.startsWith('/docs-user-manual-16.2.18'),
        'clicking the dashboard guide card opens the real 16.2.18 GitBook manual');
      await popup.close();
    } else {
      fail('dashboard manual anchor was not rendered; see GUIDE DOM EVIDENCE above');
    }

    const quickAdd = page.locator('.sl-dashboard-action[data-open-product="1"]');
    check(await quickAdd.count() === 1, 'dashboard Add Product shortcut is present');
    await quickAdd.click();
    await page.waitForSelector('#productForm');
    pass('dashboard Add Product opens the new Product Master form');
    await page.locator('#productOverlay').evaluate((element) => element.remove());

    await page.locator('.sl-nav [data-view="products"]').click();
    await page.waitForSelector('#newProduct');
    await page.locator('#newProduct').click();
    await page.waitForSelector('#productForm');
    const form = page.locator('#productForm');
    const initialSku = await form.locator('[name="sku"]').inputValue();
    check(initialSku === '', 'New Product form does not force any SKU prefix');

    const productName = 'UI Product Creation Acceptance ' + Date.now();
    await form.locator('[name="product_name"]').fill(productName);
    await form.locator('[name="sku"]').fill('-bad sku!');
    await page.locator('#productSaveButton').click();
    await page.waitForFunction(() => {
      const error = document.getElementById('productFormError');
      return !!error && !error.hidden;
    });
    const inlineError = await page.locator('#productFormError').innerText();
    check(/must start with a letter or number/i.test(inlineError), 'invalid SKU displays a clear inline explanation');

    const sku = 'UI.' + Date.now().toString(36).toUpperCase();
    await form.locator('[name="sku"]').fill(sku);
    const responsePromise = page.waitForResponse((response) => {
      const request = response.request();
      let pathname = '';
      try { pathname = new URL(response.url()).pathname; } catch (_) {}
      return request.method() === 'POST' && /\/products$/.test(pathname) && response.status() < 400;
    });
    await page.locator('#productSaveButton').click();
    const createResponse = await responsePromise;
    const created = await createResponse.json();
    check(created && String(created.sku || '') === sku, 'server creates the requested Product Master SKU');

    await page.waitForSelector('#productOverlay', { state: 'detached' });
    await page.waitForFunction((expectedSku) =>
      Array.from(document.querySelectorAll('#sl-content tr')).some((row) => row.innerText.includes(expectedSku)),
      sku, { timeout: 15000 });
    pass('created product is visible in the refreshed catalog');

    if (pageErrors.length) fail('uncaught ERP browser errors: ' + pageErrors.join(' | ').slice(0, 500));
    else pass('no uncaught ERP browser errors');
  } catch (error) {
    fail('browser test stopped: ' + String(error && error.message || error));
  } finally {
    await browser.close();
  }
  console.log('RESULT ' + (failed ? 'FAIL' : 'PASS') + ' (' + failed + ' failures)');
  process.exitCode = failed ? 1 : 0;
})();
