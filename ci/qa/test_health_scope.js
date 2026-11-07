'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const appPath = process.argv[2];
if (!appPath || !fs.existsSync(appPath)) {
  throw new Error('Usage: node test_health_scope.js <extracted-plugin>/assets/app.js');
}

const source = fs.readFileSync(appPath, 'utf8');
const helper = source.split(/\r?\n/).find((line) => line.startsWith('function classifyERPHealthScope('));
assert.ok(helper, 'module-scoped health classifier exists in the exact packaged app.js');

const context = {};
vm.runInNewContext(
  helper + '\nthis.classifyERPHealthScope = classifyERPHealthScope;',
  context,
  { timeout: 1000 }
);
const classify = context.classifyERPHealthScope;

const payroll = classify(
  ['payroll_compensation_classification'],
  false,
  false,
  false
);
assert.equal(payroll.scoped, true);
assert.equal(payroll.payrollOnly, true);
assert.equal(payroll.detail, 'Payroll compensation review');
console.log('PASS payroll compensation failure is scoped to payroll');

const combined = classify(
  ['payroll_compensation_classification', 'schema_contract_approved', 'release_approval'],
  false,
  false,
  false
);
assert.equal(combined.scoped, true);
assert.equal(combined.payrollOnly, false);
assert.equal(
  combined.detail,
  'Payroll compensation review, Schema architecture approval, Owner release authorization'
);
console.log('PASS the three supplied review gates are scoped to their dependent actions');

const governance = classify(
  ['schema_contract_approved', 'release_approval'],
  false,
  false,
  false
);
assert.equal(governance.scoped, true);
assert.equal(governance.payrollOnly, false);
console.log('PASS schema/release approvals do not become an ERP-wide readiness label');

for (const [name, failed] of [
  ['deep integrity scan', ['supplemental_integrity_scan']],
  ['ledger reconciliation', ['general_ledger_balanced']],
  ['mixed review and integrity failure', ['release_approval', 'general_ledger_balanced']]
]) {
  const result = classify(failed, false, false, false);
  assert.equal(result.scoped, false, name + ' must not be hidden as a targeted approval review');
  console.log('PASS ' + name + ' remains a visible independent integrity failure');
}

assert.equal(classify(['release_approval'], false, true, false).scoped, false);
assert.equal(classify(['release_approval'], false, false, true).scoped, false);
assert.equal(classify(['release_approval'], true, false, false).scoped, false);
assert.equal(classify([], false, false, false).scoped, false);
console.log('PASS setup, quick-only, live-ready and empty states remain distinct');

console.log('HEALTH SCOPE: 10 assertions passed, 0 failed');
