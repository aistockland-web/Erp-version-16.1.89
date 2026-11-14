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
  console.log('FAIL ' + message);
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
        try {
          await page.waitForFunction(() => {
            const root = document.querySelector('#stockland-app');
            if (!root) return false;
            const visibleText = root.innerText || '';
            return /Welcome back,/i.test(visibleText) &&
              !visibleText.includes('Loading Stockland Ethiopia...');
          }, null, { timeout: 45000 });
        } catch (error) {
          const renderState = await page.locator('#stockland-app').innerText().catch(() => '(no app root text)');
          throw new Error(viewport.label + ' ERP dashboard did not hydrate within 45 seconds. Render state: ' + renderState.slice(0, 1200) + '. ' + error.message);
        }
        const hrNavigationCount = await page.locator('.sl-nav-hr').count();
        if (hrNavigationCount !== 0) fail(name + ' ' + viewport.label + ' exposed HR navigation before active-Owner schema approval');
        await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        const pendingNotice = await page.locator('.sl-operational-schema-banner').textContent().catch(() => '');
        if (!pendingNotice || !pendingNotice.includes('verified 49-key operational baseline')) {
          fail(name + ' ' + viewport.label + ' does not expose the HR-pending / Core-Finance-operational boundary');
        } else {
          note(name + ' ' + viewport.label + ' explains HR-pending operational readiness');
        }
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

    // Verify both indirect dashboard access and the command palette respect the HR gate.
    await page.keyboard.press('Control+k');
    const paletteCount = await page.locator('#slCommandPalette').count();
    if (paletteCount !== 1) {
      fail('command palette did not open for HR visibility check');
    } else {
      const hrCommandCount = await page.locator('#slCommandPalette [data-command-view="hr"]').count();
      if (hrCommandCount !== 0) fail('command palette exposed HR before active-Owner schema approval');
      else note('command palette keeps unapproved HR lifecycle hidden');
      await page.evaluate(() => document.getElementById('slCommandPalette')?.remove());
    }
    const dashboardHrActionCount = await page.locator('#sl-content [data-view="hr"]').count();
    if (dashboardHrActionCount !== 0) {
      fail('dashboard exposed an HR action before active-Owner schema approval');
    } else {
      note('dashboard quick actions keep unapproved HR lifecycle hidden');
    }

    // Management Intelligence is a Core-owned workspace, independent of HR lifecycle.
    // Verify the browser calls its own route and never falls back to the former HR endpoint.
    // Dashboard report filters: period + branch dropdowns drive the insights request.
    const dashNav = page.locator('[data-view="dashboard"]').first();
    if (await dashNav.count()) await dashNav.click();
    const filterBar = await page.waitForSelector('#slDashboardFilters', { timeout: 15000 }).catch(() => null);
    if (!filterBar) fail('dashboard report filter bar missing');
    else {
      const wait30 = page.waitForRequest(r => r.url().includes('dashboard/insights') && /period(=|%3D)30d/.test(r.url()), { timeout: 10000 }).catch(() => null);
      await page.selectOption('#slDashPeriod', '30d');
      if (!(await wait30)) fail('selecting 30 days did not request period=30d');
      else {
        await page.waitForFunction(() => document.querySelectorAll('#slDashboardSalesTrend circle').length >= 30 || document.querySelector('#slDashboardSalesTrend .sl-empty'), { timeout: 10000 }).catch(() => {});
        const points = await page.locator('#slDashboardSalesTrend circle').count();
        if (points && points !== 30) fail('trend chart shows ' + points + ' points for a 30-day period');
        else note('dashboard filters: period dropdown re-queries insights (' + (points || 'empty-state') + ' trend points)');
      }
      await page.click('#slDashReset');
    }
    const miNav = page.locator('[data-view="intelligence"]').first();
    if (await miNav.count() < 1) {
      fail('Owner dashboard does not expose Management Intelligence');
    } else {
      const managementRequests = [];
      const captureManagementRequest = (request) => {
        const url = request.url();
        if (url.includes('/management/social') || url.includes('/hr/management-social')) {
          managementRequests.push(url);
        }
      };
      page.on('request', captureManagementRequest);
      await miNav.click();
      await page.waitForSelector('#refreshManagementIntelligence', { timeout: 15000 })
        .catch(() => fail('Management Intelligence did not render'));
      await page.waitForSelector('#slMiSocialBody .sl-hrlc-social-card', { timeout: 15000 })
        .catch(() => fail('Management Intelligence official platform links did not render'));
      const platformLinks = await page.locator('#slMiSocialBody .sl-hrlc-social-card').count();
      if (platformLinks !== 4) {
        fail('Management Intelligence rendered ' + platformLinks + ' official platform links; expected 4');
      }
      const usedManagementRoute = managementRequests.some((url) => url.includes('/management/social'));
      const usedHrRoute = managementRequests.some((url) => url.includes('/hr/management-social'));
      if (!usedManagementRoute) {
        fail('Management Intelligence did not request its independent /management/social route');
      }
      if (usedHrRoute) {
        fail('Management Intelligence called the deprecated HR /hr/management-social route');
      }
      if (usedManagementRoute && !usedHrRoute && platformLinks === 4) {
        note('Management Intelligence uses independent Core route with four official platform links; no HR social API call');
      }
      page.off('request', captureManagementRequest);
    }


    // Table holders: verify first enhancement, filters, row appends, row replacement and search suggestions.
    const holder = await page.evaluate(async () => {
      const app = document.getElementById('stockland-app');
      if (!app) return { error: 'no app root' };
      const wrap = document.createElement('div');
      wrap.className = 'sl-table-wrap';
      wrap.innerHTML = '<table class="sl-table"><thead><tr><th>Item</th><th>State</th></tr></thead><tbody>' +
        ['alpha:OPEN', 'bravo:OPEN', 'charlie:CLOSED', 'delta:CLOSED', 'echo:OPEN', 'foxtrot:CLOSED'].map(x => { const [a, b] = x.split(':'); return '<tr><td>' + a + '</td><td>' + b + '</td></tr>'; }).join('') + '</tbody></table>';
      app.appendChild(wrap);
      await new Promise(r => setTimeout(r, 400));
      const bar = wrap.previousElementSibling;
      if (!bar || !bar.classList.contains('sl-table-tools')) return { error: 'toolbar missing' };
      const q = bar.querySelector('input[type=search]');
      q.value = 'bravo'; q.dispatchEvent(new Event('input', { bubbles: true }));
      const afterSearch = wrap.querySelectorAll('tbody tr:not(.sl-row-filtered)').length;
      q.value = ''; q.dispatchEvent(new Event('input', { bubbles: true }));
      const sel = bar.querySelector('select[data-col="1"]');
      if (!sel || bar.querySelectorAll('select').length !== 1) return { error: 'expected exactly one useful dropdown' };
      sel.value = 'CLOSED'; sel.dispatchEvent(new Event('change', { bubbles: true }));
      const afterFilter = wrap.querySelectorAll('tbody tr:not(.sl-row-filtered)').length;
      const hidden = getComputedStyle(wrap.querySelector('tbody tr.sl-row-filtered')).display;
      bar.querySelector('[data-clear]').click();

      // Append a row while retaining the same enhanced wrapper.
      const appended = document.createElement('tr');
      appended.innerHTML = '<td>golf</td><td>PENDING</td>';
      wrap.querySelector('tbody').appendChild(appended);
      await new Promise(r => setTimeout(r, 400));
      const countAfterAppend = bar.querySelector('.sl-table-count').textContent;
      const appendSuggestion = [...bar.querySelectorAll('datalist option')].some(o => o.value === 'golf');

      // Replace all rows in the existing table; the same toolbar must refresh its rows and options.
      wrap.querySelector('tbody').innerHTML =
        ['hotel:PENDING', 'india:PENDING', 'juliet:CLOSED', 'kilo:CLOSED', 'lima:OPEN', 'mike:OPEN', 'november:PENDING']
        .map(x => { const [a, b] = x.split(':'); return '<tr><td>' + a + '</td><td>' + b + '</td></tr>'; }).join('');
      await new Promise(r => setTimeout(r, 400));
      const barAfterUpdate = wrap.previousElementSibling;
      const qAfterUpdate = barAfterUpdate.querySelector('input[type=search]');
      const datalist = barAfterUpdate.querySelector('datalist');
      const hasNewSuggestion = !!datalist && [...datalist.options].some(o => o.value === 'hotel');
      const updatedSelect = barAfterUpdate.querySelector('select[data-col="1"]');
      const hasPendingOption = !!updatedSelect && [...updatedSelect.options].some(o => o.value === 'PENDING');
      qAfterUpdate.value = 'hotel'; qAfterUpdate.dispatchEvent(new Event('input', { bubbles: true }));
      const afterUpdatedSearch = wrap.querySelectorAll('tbody tr:not(.sl-row-filtered)').length;
      qAfterUpdate.value = ''; qAfterUpdate.dispatchEvent(new Event('input', { bubbles: true }));
      updatedSelect.value = 'PENDING'; updatedSelect.dispatchEvent(new Event('change', { bubbles: true }));
      const afterUpdatedFilter = wrap.querySelectorAll('tbody tr:not(.sl-row-filtered)').length;
      const updatedCount = barAfterUpdate.querySelector('.sl-table-count').textContent;
      const adjacentToolbarCount = [...app.querySelectorAll('.sl-table-tools')].filter(el => el.nextElementSibling === wrap).length;
      const sameToolbar = barAfterUpdate === bar;
      wrap.remove(); barAfterUpdate.remove();
      return { afterSearch, afterFilter, hidden, countAfterAppend, appendSuggestion, hasNewSuggestion, hasPendingOption, afterUpdatedSearch, afterUpdatedFilter, updatedCount, adjacentToolbarCount, sameToolbar };
    });
    if (holder.error) fail('table holder: ' + holder.error);
    else if (holder.afterSearch !== 1 || holder.afterFilter !== 3 || holder.hidden !== 'none' ||
      holder.countAfterAppend !== '7 of 7 rows' || !holder.appendSuggestion || !holder.hasNewSuggestion ||
      !holder.hasPendingOption || holder.afterUpdatedSearch !== 1 || holder.afterUpdatedFilter !== 3 ||
      holder.updatedCount !== '3 of 7 rows' || holder.adjacentToolbarCount !== 1 || !holder.sameToolbar) {
      fail('table holder dynamic refresh failed: ' + JSON.stringify(holder));
    } else note('table holder: search, dropdowns, suggestions, append and row replacement PASS (' + holder.updatedCount + ')');

    // Report tables with fewer than five rows must still receive the same accessible search/filter holder.
    const shortReportHolder = await page.evaluate(async () => {
      const app = document.getElementById('stockland-app');
      if (!app) return { error: 'no app root' };
      const wrap = document.createElement('div');
      wrap.className = 'sl-table-wrap';
      wrap.setAttribute('data-sl-force-filter', '');
      wrap.innerHTML = '<table class="sl-table"><thead><tr><th>Item</th><th>Status</th></tr></thead><tbody>' +
        ['alpha:OPEN', 'bravo:OPEN', 'charlie:CLOSED'].map(x => { const [a, b] = x.split(':'); return '<tr><td>' + a + '</td><td>' + b + '</td></tr>'; }).join('') +
        '</tbody></table>';
      app.appendChild(wrap);
      await new Promise(r => setTimeout(r, 400));
      const bar = wrap.previousElementSibling;
      if (!bar || !bar.classList.contains('sl-table-tools')) { wrap.remove(); return { error: 'short report toolbar missing' }; }
      const q = bar.querySelector('input[type=search]');
      if (!q) { wrap.remove(); bar.remove(); return { error: 'short report search missing' }; }
      q.value = 'bravo'; q.dispatchEvent(new Event('input', { bubbles: true }));
      const searchCount = wrap.querySelectorAll('tbody tr:not(.sl-row-filtered)').length;
      q.value = ''; q.dispatchEvent(new Event('input', { bubbles: true }));
      const status = bar.querySelector('select[data-col="1"]');
      if (!status) { wrap.remove(); bar.remove(); return { error: 'short report dropdown missing' }; }
      status.value = 'CLOSED'; status.dispatchEvent(new Event('change', { bubbles: true }));
      const filterCount = wrap.querySelectorAll('tbody tr:not(.sl-row-filtered)').length;
      const count = bar.querySelector('.sl-table-count')?.textContent || '';
      wrap.remove(); bar.remove();
      return { searchCount, filterCount, count };
    });
    if (shortReportHolder.error) fail('short report table holder: ' + shortReportHolder.error);
    else if (shortReportHolder.searchCount !== 1 || shortReportHolder.filterCount !== 1 || shortReportHolder.count !== '1 of 3 rows') {
      fail('short report table search/dropdown failed: ' + JSON.stringify(shortReportHolder));
    } else note('short report table (3 rows): search suggestions and column dropdown PASS');

    const jsText = await page.evaluate(async () => {
      const l = [...document.querySelectorAll('script[src]')].map(x => x.src).find(h => /app\.js/.test(h));
      return l ? (await (await fetch(l)).text()) : '';
    });
    if (!jsText || jsText.includes('Owner-approved additive HR/Payroll schema migration after backup and source review')) fail('HR schema approval prompt contains a prefilled approval statement');
    else note('HR schema approval prompt requires an unprefilled Owner decision');
    if (!jsText.includes('both monthly_tax_bands (legacy) and paye_bands (frozen runs)')) fail('HR payroll rule form does not explain the dual calculator schedule contract');
    else note('HR payroll rule form explains the dual calculator contract');
    const legalReviewFields = ['Independent legal review decision','Reviewer independent of rule preparation','Independent reviewer full name','Reviewer professional registration / licence ID','Professional qualification / licence basis','Review date','Signed opinion reference / document ID','Signed opinion document SHA-256','Legal clauses reviewed (one per line)','Approved parameter keys (comma-separated)'];
    if (!legalReviewFields.every(label => jsText.includes(label)) || !jsText.includes('qualified_legal_review') || !jsText.includes('clauses_reviewed') || !jsText.includes('approved_parameters')) fail('HR statutory rule form is missing structured legal-review evidence capture');
    else note('HR statutory rule form captures independent reviewer, signed opinion hash, clauses and approved parameter scope');
    if (!jsText.includes('data-sl-force-filter')) fail('Reports Explorer does not force table tools for short result sets');
    else note('Reports Explorer marks short result tables for search/filter tools');

    const cssText = await page.evaluate(async () => { const l = [...document.querySelectorAll('link[rel=stylesheet]')].map(x => x.href).find(h => /app(\.min)?\.css/.test(h)); return l ? (await (await fetch(l)).text()) : ''; });
    if (!cssText.includes('@supports not (--a:0)')) fail('legacy-browser colour fallbacks missing from the CSS bundle');
    else note('legacy-browser colour fallbacks present in the CSS bundle');
    if (!cssText.includes('#hrPage{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))')) fail('HR & Payroll two-block row layout missing from the CSS bundle');
    else note('HR & Payroll two-block row layout present in the CSS bundle');

    await context.close();
  } catch (error) {
    fail('UI smoke runner exception: ' + (error && error.stack ? error.stack : String(error)));
  } finally {
    if (browser) await browser.close();
  }

  console.log(issues.length ? 'RESULT: FAIL (' + issues.length + ' issue(s))' : 'RESULT: PASS');
  process.exit(issues.length ? 1 : 0);
})();
