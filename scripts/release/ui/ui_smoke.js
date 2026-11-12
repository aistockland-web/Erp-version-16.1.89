#!/usr/bin/env node
'use strict';

// Headless browser acceptance for the Stockland ERP standalone shell and WordPress doorway.
// Usage: node ui_smoke.js <base-url> <wp-user> <qa-password> [screenshot-dir]
// Run only against a disposable WordPress database with synthetic data.
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const [baseArg, user, password, screenshotDir = '.'] = process.argv.slice(2);
if (!baseArg || !user || !password) {
  console.error('usage: ui_smoke.js <base-url> <wp-user> <qa-password> [screenshot-dir]');
  process.exit(2);
}

const base = new URL(baseArg);
const origin = base.origin;
const at = (pathname) => new URL(pathname, origin).toString();
const issues = [];
const screenshots = path.resolve(screenshotDir);
fs.mkdirSync(screenshots, { recursive: true });

const note = (message) => console.log('PASS ' + message);
const fail = (message) => {
  issues.push(message);
  console.error('FAIL ' + message);
};
const isLocal = (url) => {
  try { return new URL(url).origin === origin; } catch { return false; }
};

(async () => {
  let browser;
  try {
    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
      viewport: { width: 1366, height: 900 },
      deviceScaleFactor: 1,
      ignoreHTTPSErrors: false,
    });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);

    page.on('pageerror', (error) => fail('uncaught page error: ' + error.message));
    page.on('console', (message) => {
      if (message.type() === 'error') fail('browser console error: ' + message.text());
    });
    page.on('requestfailed', (request) => {
      const reason = request.failure()?.errorText || 'unknown';
      // Navigation replaces the current document and may legitimately cancel its in-flight requests.
      if (isLocal(request.url()) && !/ERR_ABORTED/.test(reason)) {
        fail('same-origin request failed: ' + request.url() + ' (' + reason + ')');
      }
    });
    page.on('response', (response) => {
      const url = response.url();
      if (!isLocal(url)) return;
      const pathname = new URL(url).pathname;
      if (response.status() >= 500) {
        fail('same-origin HTTP ' + response.status() + ': ' + url);
      } else if (response.status() >= 400 && (
        pathname.includes('/wp-content/plugins/stockland-erp-app/assets/') ||
        pathname.includes('/wp-json/stockland/')
      )) {
        fail('ERP asset/API HTTP ' + response.status() + ': ' + url);
      }
    });

    await page.goto(at('/wp-login.php'), { waitUntil: 'domcontentloaded' });
    await page.locator('#user_login').fill(user);
    await page.locator('#user_pass').fill(password);
    await Promise.all([
      page.waitForURL((url) => url.pathname.includes('/wp-admin/'), { waitUntil: 'domcontentloaded' }),
      page.locator('#wp-submit').click(),
    ]);
    note('disposable WordPress administrator login');

    // Verify that the native WordPress administration remains available and exposes
    // the ERP launcher. Its redirect callback is separately tracked in issue #98 because
    // WordPress 7.1 may emit admin font CSS before that callback attempts a redirect.
    await page.goto(at('/wp-admin/index.php'), { waitUntil: 'domcontentloaded' });
    const launcherCount = await page.locator('#adminmenu a[href*="page=stockland-erp"]').count();
    if (launcherCount < 1) {
      fail('WordPress admin menu does not expose the Stockland ERP launcher');
    } else {
      note('WordPress administrator dashboard and Stockland ERP launcher menu entry');
    }

    await page.goto(at('/wp-admin/admin.php?page=stockland-system-control'), { waitUntil: 'domcontentloaded' });
    const controlText = (await page.locator('body').innerText()).toLowerCase();
    if (!/system control|system health|setup|schema/.test(controlText)) {
      fail('System Control page did not render expected setup/health content');
    } else {
      note('Owner System Control page renders');
    }
    if (/fatal error|parse error|warning: |notice: /.test(controlText)) {
      fail('System Control page contains a PHP runtime diagnostic');
    }

    for (const [name, pathname] of [
      ['published-erp-page', '/stockland-erp-app/'],
      ['direct-erp-route', '/stockland-erp/'],
    ]) {
      await page.goto(at(pathname), { waitUntil: 'domcontentloaded' });
      const status = await page.evaluate(() => document.readyState);
      if (status === 'loading') fail(name + ' document did not finish loading');
      const shell = await page.locator('body.stockland-erp-standalone').count();
      const root = await page.locator('#stockland-app.sl-root').count();
      const config = await page.evaluate(() => ({
        build: window.StocklandApp?.build || '',
        loggedIn: window.StocklandApp?.user?.logged_in === true,
        api: window.StocklandApp?.api || '',
      }));
      if (!shell || !root) fail(name + ' missing standalone document or #stockland-app root');
      if (!config.loggedIn || !config.api) fail(name + ' missing authenticated ERP bootstrap configuration');
      if (config.build && config.build !== '16.2.18') fail(name + ' unexpected ERP build version: ' + config.build);
      if (shell && root && config.loggedIn && config.api) {
        note(name + ' standalone shell and authenticated bootstrap');
      }

      const manifestUrl = await page.locator('link[rel="manifest"]').getAttribute('href');
      if (!manifestUrl) {
        fail(name + ' PWA manifest link is missing');
      } else {
        const manifest = await page.evaluate(async (url) => {
          const response = await fetch(url, { credentials: 'same-origin' });
          if (!response.ok) return { ok: false, status: response.status };
          const data = await response.json();
          return { ok: true, display: data.display, name: data.name, startUrl: data.start_url };
        }, manifestUrl);
        if (!manifest.ok || manifest.display !== 'standalone' || !manifest.name) {
          fail(name + ' PWA manifest is invalid: ' + JSON.stringify(manifest));
        } else {
          note(name + ' PWA manifest responds with standalone metadata');
        }
      }

      for (const viewport of [
        { label: 'phone-390', width: 390, height: 844 },
        { label: 'tablet-768', width: 768, height: 1024 },
        { label: 'desktop-1366', width: 1366, height: 900 },
      ]) {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        // Do not count the splash/loading placeholder as a rendered responsive ERP view.
        await page.waitForFunction(() => {
          const root = document.querySelector('#stockland-app');
          if (!root) return false;
          const visibleText = root.innerText || '';
          return /Welcome back,/i.test(visibleText) &&
            !!visibleText.includes('Loading Stockland Ethiopia...');
        }, { timeout: 30000 });
        await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        const dimensions = await page.evaluate(() => ({
          viewport: window.innerWidth,
          document: document.documentElement.scrollWidth,
          body: document.body.scrollWidth,
        }));
        const overflow = Math.max(dimensions.document, dimensions.body) - dimensions.viewport;
        await page.screenshot({
          path: path.join(screenshots, name + '-' + viewport.label + '.png'),
          fullPage: true,
        });
        if (overflow > 4) {
          fail(name + ' ' + viewport.label + ' horizontal overflow ' + overflow + 'px');
        } else {
          note(name + ' ' + viewport.label + ' layout (horizontal overflow ' + overflow + 'px)');
        }
      }
    }

    await context.close();
  } catch (error) {
    fail('UI smoke runner exception: ' + (error && error.stack ? error.stack : String(error)));
  } finally {
    if (browser) await browser.close();
  }

  console.log(issues.length ? 'RESULT: FAIL (' + issues.length + ' issue(s))' : 'RESULT: PASS');
  process.exit(issues.length ? 1 : 0);
})();
