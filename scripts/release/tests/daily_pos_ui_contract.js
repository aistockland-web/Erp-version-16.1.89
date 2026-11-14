'use strict';

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../../..');
const app = fs.readFileSync(path.join(root, 'src/stockland-erp-app/assets/app.js'), 'utf8');
const agent = fs.readFileSync(path.join(root, 'tools/stockland-pos-folder-agent/Run-StocklandPosFolderAgent.ps1'), 'utf8');
const importer = fs.readFileSync(path.join(root, 'src/stockland-erp-app/includes/rest/class-rest-import-v16.php'), 'utf8');
const health = fs.readFileSync(path.join(root, 'src/stockland-erp-app/includes/rest/class-rest-app.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'src/stockland-erp-app/assets/app.css'), 'utf8');

function assert(ok, message) {
  if (!ok) throw new Error('Daily POS UI contract failed: ' + message);
}
function section(source, startText, endText) {
  const start = source.indexOf(startText);
  const end = source.indexOf(endText, start + startText.length);
  assert(start >= 0 && end > start, 'Cannot locate ' + startText);
  return source.slice(start, end);
}

const page = section(app, 'function dailyImportPage(){', '\nfunction ');
assert(page.includes("['branch_manager','sales_employee'].includes(role)") && page.includes('!c.daily_import'), 'Daily POS page must be role and capability gated.');
assert(page.includes('1 • MANUAL EXCEL / CSV UPLOAD'), 'Manual upload block must exist.');
assert(page.includes('2 • AUTOMATIC FOLDER PICKUP'), 'Folder pickup block must exist.');
assert(page.includes('3 • FUTURE POS API'), 'Future API block must exist.');
assert(page.includes('sl-daily-pos-intake-grid'), 'The three intake choices must be presented as one dedicated group.');
assert(css.includes('.sl-import-purpose-grid.sl-daily-pos-intake-grid{grid-template-columns:repeat(3,minmax(0,1fr))}'), 'Desktop must show all three intake choices side by side.');
assert(css.includes('@media(max-width:575.98px){.sl-import-purpose-grid.sl-daily-pos-intake-grid{grid-template-columns:1fr}}'), 'Mobile layout must collapse the choices to one column.');
assert(page.includes('NOT CONNECTED • AWAITING VENDOR'), 'Future API must be explicitly disabled.');
assert(page.includes('dailyPosImportForm'), 'Manual original workbook upload must be on this page.');
assert(!page.includes('/import/products/preview'), 'Product Master upload must stay separate.');

const preview = section(app, 'async function previewDailyImport(e){', '\n\n\nasync function openDailyJobEditor(');
assert(preview.includes("'/import/daily-sales/preview'") && preview.includes('existing_job'), 'Manual upload must use the same idempotent server preview/stage route.');
assert(preview.includes('reconciliation'), 'Preview must expose source-to-ERP reconciliation.');

const editor = section(app, 'async function openDailyJobEditor(', '\n\n\nasync function loadDailyImportQueue');
assert(editor.includes("['READY','PARTIAL','REVIEW'].includes(job.status)"), 'Manager must be able to review/edit an unposted REVIEW job.');
assert(editor.includes("['READY','PARTIAL'].includes(job.status)"), 'Only READY/PARTIAL may be released.');

assert(agent.includes('/wp-json/stockland/v16/import/daily-sales/preview'), 'Folder agent must use the established preview/stage endpoint.');
assert(importer.includes('CNET_SUMMARIZED_BY_ARTICLE'), 'Importer must detect the exact summarized POS source format.');
assert(importer.includes('function article_summary_layout_self_test()'), 'Importer must expose a real deterministic source-layout health test.');
assert(health.includes("$checks['daily_pos_article_summary_layout']") && health.includes("Stockland_REST_Import_V16::article_summary_layout_self_test()"), 'Deep System Health must execute the exact Article Code alignment/mapping self-test.');
assert(health.includes("$checks['daily_pos_contract'] && $checks['daily_pos_article_summary_layout']"), 'Exact source mapping health must be mandatory for live_ready.');
assert(health.includes("'daily_pos_article_summary_layout',") && health.includes("'DEFERRED_NOT_RUN'"), 'Quick Health must disclose that deep POS layout verification was deferred.');
assert(importer.includes('DAILY-UNREFERENCED'), 'Summary rows must not be represented as invented receipt numbers.');
assert(importer.includes('receipt_count') && importer.includes("'' !== $external_receipt ? 1 : 0"), 'Receipt-free report summaries must not be counted as a known customer receipt.');
assert(importer.includes('already has a released or partially released Daily POS job'), 'A second changed export for the same branch/date must be blocked after posting starts.');
assert(importer.includes('Only the assigned Branch Manager may edit and release'), 'Manager edit authority must be enforced in the API, not only the UI.');
assert(!agent.includes('/wp-json/stockland/v16/import/daily-sales/commit'), 'Folder agent must never commit/release.');
assert(agent.includes("response.Contains('existing_job')"), 'Folder agent must safely handle optional idempotency response.');
assert(!agent.includes("branch_location_id ="), 'Agent must not set or override a branch id.');
console.log('RESULT PASS: Daily POS three-option UI, role boundary, common staging route, reconciliation, manager release gate and no-auto-commit folder agent.');
