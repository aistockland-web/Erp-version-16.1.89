// Interactive browser acceptance on a disposable site.
// Installs the ZIP through wp-admin, verifies the 49-key operational baseline,
// confirms the 37-table HR lifecycle remains unapproved, and checks the ERP shell.
// This test must never record HR DDL approval or live-release authorization.
const { chromium } = require('playwright');
const path = require('path');
(async () => {
  const [base, user, pass, zip, shots] = process.argv.slice(2);
  if (!base || !user || !pass || !zip || !shots) throw new Error('Usage: node interactive_setup.js <site-url> <admin-user> <admin-pass> <zip> <shots-dir>');
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
  const page = await context.newPage();
  page.setDefaultTimeout(120000);
  const failures = [];
  const check = (label, ok, detail = '') => { console.log((ok ? 'PASS ' : 'FAIL ') + label + (ok ? '' : ' :: ' + detail)); if (!ok) failures.push(label); };
  try {
    await page.goto(base + '/wp-login.php');
    await page.fill('#user_login', user);
    await page.fill('#user_pass', pass);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/, { waitUntil: 'commit' });
    await page.goto(base + '/wp-admin/plugin-install.php?tab=upload', { waitUntil: 'domcontentloaded' });
    await page.setInputFiles('input#pluginzip', path.resolve(zip));
    await page.click('#install-plugin-submit');
    await page.waitForSelector('a.button-primary:has-text("Activate Plugin")');
    check('plugin uploaded through wp-admin', true);
    await page.click('a.button-primary:has-text("Activate Plugin")');
    await page.waitForLoadState('domcontentloaded');
    const activation = (await page.locator('body').innerText()).slice(0, 4000);
    check('plugin activates without error', /Plugin activated|activated/i.test(activation) && !/fatal error|Parse error/i.test(activation), activation.slice(0, 200));
    await page.goto(base + '/wp-admin/admin.php?page=stockland-system-control', { waitUntil: 'domcontentloaded' });
    const systemControl = await page.locator('body').innerText();
    check('System Control opens for the administrator', /Stockland System Control/.test(systemControl), systemControl.slice(0, 160));
    check('Core inventory/POS baseline is physically complete', /all 29 present/.test(systemControl), systemControl.slice(0, 500));
    check('four legacy HR tables present and lifecycle schema pending', /4 of 41 present/.test(systemControl) && /Owner approval reason/.test(systemControl), systemControl.slice(0, 800));
    check('live release authorization withheld before full HR approval', await page.locator('#stockland_release_reason').count() === 0);
    await page.screenshot({ path: path.join(shots, 'interactive-system-control.png'), fullPage: true });
    await page.goto(base + '/?pagename=stockland-erp-app', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(3000);
    const appText = await page.locator('body').innerText();
    check('ERP shell renders on the operational baseline', /Dashboard|Inventory|Sales/i.test(appText), appText.slice(0, 160));
    await page.screenshot({ path: path.join(shots, 'interactive-app.png') });
  } finally {
    await browser.close();
  }
  console.log(failures.length ? 'RESULT FAIL (' + failures.length + ')' : 'RESULT PASS');
  process.exit(failures.length ? 1 : 0);
})();
