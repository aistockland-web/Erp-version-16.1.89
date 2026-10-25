'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = process.argv[2];
if (!path) throw new Error('Usage: node test-period-summary-csv.js <assets/app.js>');
const source = fs.readFileSync(path, 'utf8');
function block(name, nextName) {
  const start = source.indexOf('function ' + name + '(');
  const end = source.indexOf('\nfunction ' + nextName + '(', start);
  assert.ok(start >= 0 && end > start, 'Could not isolate ' + name + '() from app.js');
  return source.slice(start, end).trim();
}
const context = { localDate: () => '2026-10-20' };
vm.createContext(context);
vm.runInContext(block('reportCsvField', 'buildPeriodSummaryCsv'), context);
vm.runInContext(block('buildPeriodSummaryCsv', 'reportPresetRange'), context);
vm.runInContext(block('reportPresetRange', 'downloadPeriodSummaryCsv'), context);

assert.equal(context.reportCsvField('Plain'), 'Plain');
assert.equal(context.reportCsvField('Megenagna, Main'), '"Megenagna, Main"');
assert.equal(context.reportCsvField('Kids "Jacket"'), '"Kids ""Jacket"""');
assert.equal(context.reportCsvField('=HYPERLINK("x")'), '"\'=HYPERLINK(""x"")"');
assert.equal(context.reportCsvField(-12.5), '-12.5', 'numeric negative values remain numeric');

const report = {
  from: '2026-10-14', to: '2026-10-20', scope_location_id: 12,
  totals: { sales: 12500.5, net: 11000, vat: 1500.5, units: 47, profit: 2700 },
  returns: { units: 2, rows: 1 },
  branches: [{ location_name: 'Megenagna, Main', retail_units: 21, retail_sales: 6200, retail_profit: 1400 }],
  top_products: [{ sku: 'TCP-1234', product_name: 'Kids "Jacket"', units: 8, sales: 2800, profit: 800 }],
  transfers: [{ transfer_id: 'TR-100', from_location_name: 'Central Warehouse', to_location_name: 'Gerji Branch', sku: 'TCP-1234', quantity: 10, received_qty: 9, status: 'Received' }],
  variances: [{ transfer_id: 'TR-100', sku: 'TCP-1234', product_name: 'Kids Jacket', from_location_name: 'Central Warehouse', to_location_name: 'Gerji Branch', dispatched_qty: 10, received_qty: 9, variance_qty: -1 }],
  physical_counts: [{ batch_id: 'CNT-100', location_name: 'Gerji Branch', status: 'Reviewed', lines: 20, variance_lines: 1, counted_qty: 59, system_qty: 60, variance_qty: -1 }],
  people: [{ display_name: 'Staff PII Should Never Be Exported', revenue: 999999 }],
  stock: [{ sku: 'INVENTORY SNAPSHOT SHOULD NOT BE EXPORTED' }],
};
const financeCsv = context.buildPeriodSummaryCsv(report, { can_cost: true });
const operationsCsv = context.buildPeriodSummaryCsv(report, { can_cost: false });
assert.equal(financeCsv.charCodeAt(0), 0xFEFF, 'CSV should have a UTF-8 BOM for spreadsheet compatibility');
assert.match(financeCsv, /Summary,Gross profit,2700/);
assert.match(financeCsv, /"Megenagna, Main"/);
assert.match(financeCsv, /"Kids ""Jacket"""/);
assert.match(financeCsv, /2026-10-14[\s\S]*2026-10-20/);
assert.match(financeCsv, /TR-100/);
assert.match(financeCsv, /CNT-100/);
assert.doesNotMatch(operationsCsv, /Gross profit|retail_profit/);
assert.doesNotMatch(operationsCsv, /Staff PII Should Never Be Exported/);
assert.doesNotMatch(operationsCsv, /INVENTORY SNAPSHOT SHOULD NOT BE EXPORTED/);
assert.match(operationsCsv, /Summary,Gross sales,12500\.5/);
assert.match(operationsCsv, /Branch,"Megenagna, Main"/);

assert.deepEqual(JSON.parse(JSON.stringify(context.reportPresetRange('daily'))), { from: '2026-10-20', to: '2026-10-20' });
assert.deepEqual(JSON.parse(JSON.stringify(context.reportPresetRange('weekly'))), { from: '2026-10-14', to: '2026-10-20' });
assert.deepEqual(JSON.parse(JSON.stringify(context.reportPresetRange('monthly'))), { from: '2026-10-01', to: '2026-10-20' });
assert.match(source, /data-report-preset="daily"/);
assert.match(source, /data-report-preset="weekly"/);
assert.match(source, /data-report-preset="monthly"/);
assert.match(source, /state\.report=null;const csvButton=/, 'stale reports must not remain exportable while refreshing');
assert.match(source, /openDailyPosImportFromReport/, 'the existing gated raw POS import remains discoverable');
console.log('Period summary CSV QA: PASS (CSV escaping/injection, period presets, financial-field gate, PII exclusion, read-only report scope)');
