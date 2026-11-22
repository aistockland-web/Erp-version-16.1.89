'use strict';

const { chromium } = require('playwright');
const args = process.argv.slice(2);
const baseArg = args[0];
const requisitionId = args[1];
if (!baseArg || !requisitionId) {
  console.error('Usage: node requisition_approval_buttons.js <base-url> <requisition-id>');
  process.exit(2);
}
const base = new URL(baseArg);
const users = [
  { label: 'Branch Manager review', login: 'brmgr', password: 'Pw!brmgr12345' },
  { label: 'Management final approval', login: 'mgmt', password: 'Pw!mgmt12345' },
  { label: 'Warehouse Manager reservation', login: 'whmgr', password: 'Pw!whmgr12345' },
  { label: 'Warehouse Employee pick', login: 'whstaff', password: 'Pw!whstaff12345' },
  { label: 'Branch Manager receipt', login: 'brmgr', password: 'Pw!brmgr12345' },
];
const failures = [];
const contexts = [];
const pass = (message) => console.log('PASS UI ' + message);
const route = (suffix) => '/wp-json/stockland/v16/requisitions/' + encodeURIComponent(requisitionId) + suffix;

async function login(browser, user) {
  const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
  contexts.push(context);
  const page = await context.newPage();
  page.setDefaultTimeout(20000);
  await page.goto(new URL('/wp-login.php', base).toString(), { waitUntil: 'domcontentloaded' });
  await page.locator('#user_login').fill(user.login);
  await page.locator('#user_pass').fill(user.password);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/wp-login.php'), { waitUntil: 'domcontentloaded' }),
    page.locator('#wp-submit').click(),
  ]);
  await page.goto(new URL('/stockland-erp/', base).toString(), { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#stockland-app', { state: 'attached', timeout: 30000 });
  await page.waitForFunction(() => window.StocklandApp && window.StocklandApp.user && window.StocklandApp.user.logged_in === true && !!window.StocklandApp.api, null, { timeout: 30000 });
  return page;
}

async function navigate(page, view) {
  const selector = 'button[data-view="' + view + '"]';
  const viewButton = page.locator(selector).first();
  if (!(await viewButton.isVisible().catch(() => false))) {
    const group = page.locator('button[data-nav-toggle="warehouse"]');
    if (await group.count()) await group.click();
  }
  await viewButton.waitFor({ state: 'visible' });
  await viewButton.click();
}

async function clickAndWait(page, selector, suffix, label) {
  const button = page.locator(selector).first();
  await button.waitFor({ state: 'visible' });
  if (!(await button.isEnabled())) throw new Error(label + ' button is rendered disabled');
  const responsePromise = page.waitForResponse((response) =>
    response.url().includes(route(suffix)) && response.request().method() === 'POST'
  );
  await button.click();
  const response = await responsePromise;
  if (response.status() !== 200) {
    throw new Error(label + ' returned HTTP ' + response.status() + ': ' + await response.text());
  }
  pass(label + ' button returned HTTP 200');
}

(async () => {
  let browser;
  try {
    browser = await chromium.launch({ headless: true });

    // Branch Manager's visible REVIEW row action and modal button.
    let page = await login(browser, users[0]);
    await navigate(page, 'requests');
    const reviewRow = '#reqTable button[data-review-req="' + requisitionId + '"]';
    await page.locator(reviewRow).waitFor({ state: 'visible' });
    await page.locator(reviewRow).click();
    await clickAndWait(page, '#reviewReqV16', '/decision', 'Branch Manager REVIEW & SEND');
    await page.waitForFunction((id) => {
      const row = [...document.querySelectorAll('#reqTable tbody tr')].find((tr) => tr.innerText.includes(id));
      return !!row && row.innerText.includes('Reviewed');
    }, requisitionId);
    await contexts[0].close();

    // Management opens the live detail and clicks APPROVE FULL.
    page = await login(browser, users[1]);
    await navigate(page, 'requests');
    const detailRow = '#reqTable button[data-req-detail-v16="' + requisitionId + '"]';
    await page.locator(detailRow).waitFor({ state: 'visible' });
    await page.locator(detailRow).click();
    await clickAndWait(page, '#reqApproveFull', '/decision', 'Management APPROVE FULL');
    await contexts[1].close();

    // Warehouse Manager clicks RESERVE and confirms the pickup-date dialog.
    page = await login(browser, users[2]);
    await navigate(page, 'warehouse');
    const reserveSelector = '#warehouseReqQueue button[data-warehouse-req="' + requisitionId + '"][data-warehouse-action="reserve"]';
    await page.locator(reserveSelector).waitFor({ state: 'visible' });
    await page.locator(reserveSelector).click();
    await page.locator('#slDateInput').waitFor({ state: 'visible' });
    await page.locator('#slDateInput').fill('2026-11-24');
    const reservePromise = page.waitForResponse((response) =>
      response.url().includes(route('/warehouse-action')) && response.request().method() === 'POST'
    );
    await page.locator('#slDateOk').click();
    const reserveResponse = await reservePromise;
    if (reserveResponse.status() !== 200) throw new Error('Warehouse Manager RESERVE returned HTTP ' + reserveResponse.status());
    pass('Warehouse Manager RESERVE and pickup-date confirmation returned HTTP 200');
    await contexts[2].close();

    // Warehouse Employee clicks PICK / READY on the reserved line.
    page = await login(browser, users[3]);
    await navigate(page, 'warehouse');
    const pickSelector = '#warehouseReqQueue button[data-warehouse-req="' + requisitionId + '"][data-warehouse-action="pick"]';
    await page.locator(pickSelector).waitFor({ state: 'visible' });
    const pickPromise = page.waitForResponse((response) =>
      response.url().includes(route('/warehouse-action')) && response.request().method() === 'POST'
    );
    await page.locator(pickSelector).click();
    const pickResponse = await pickPromise;
    if (pickResponse.status() !== 200) throw new Error('Warehouse Employee PICK returned HTTP ' + pickResponse.status());
    pass('Warehouse Employee PICK / READY button returned HTTP 200');
    await contexts[3].close();

    // Branch Manager opens the requisition, uses the receive dialog and confirms 1 unit.
    page = await login(browser, users[4]);
    await navigate(page, 'requests');
    await page.locator(detailRow).waitFor({ state: 'visible' });
    await page.locator(detailRow).click();
    await page.locator('#receiveReqV16').waitFor({ state: 'visible' });
    await page.locator('#receiveReqV16').click();
    await page.locator('#slRecvQty').waitFor({ state: 'visible' });
    const actualReceived = Number(await page.locator('#slRecvQty').inputValue());
    if (actualReceived !== 1) throw new Error('Expected one-unit receipt default, got ' + actualReceived);
    const receivePromise = page.waitForResponse((response) =>
      response.url().includes(route('/receive')) && response.request().method() === 'POST'
    );
    await page.locator('#slRecvOk').click();
    const receiveResponse = await receivePromise;
    if (receiveResponse.status() !== 200) throw new Error('Branch Manager RECEIVE returned HTTP ' + receiveResponse.status());
    pass('Branch Manager CONFIRM BRANCH RECEIVE button returned HTTP 200');
    const finalState = await page.evaluate(async (id) => {
      const response = await fetch('/wp-json/stockland/v16/requisitions/' + encodeURIComponent(id), { credentials: 'same-origin' });
      return { status: response.status, data: await response.json() };
    }, requisitionId);
    if (finalState.status !== 200 || !finalState.data || !finalState.data.requisition || finalState.data.requisition.status !== 'Received') {
      throw new Error('Button journey did not persist Received state: ' + JSON.stringify(finalState));
    }
    pass('button journey persisted final Received status');
    console.log('RESULT PASS: requisition approval buttons end-to-end (Branch Manager → Management → Warehouse Manager → Warehouse Employee → Branch Manager).');
  } catch (error) {
    failures.push(error && error.stack ? error.stack : String(error));
    console.log('FAIL UI requisition approval button journey — ' + failures[0]);
    process.exitCode = 1;
  } finally {
    for (const context of contexts) await context.close().catch(() => {});
    if (browser) await browser.close();
  }
})();
