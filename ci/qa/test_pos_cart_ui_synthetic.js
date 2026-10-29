'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const file = process.argv[2];
if (!file || !fs.existsSync(file)) {
  process.stderr.write('Usage: node test_pos_cart_ui_synthetic.js <pos-cart-ui.js>\n');
  process.exit(2);
}
const source = fs.readFileSync(file, 'utf8');
const sandbox = { console };
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: file, timeout: 2000 });

const normalize = sandbox.normalizePosCartLineInput;
assert.equal(typeof normalize, 'function', 'input normalizer must be exported by the overlay');

function accepted(qty, price, unit, expected) {
  const actual = normalize(qty, price, unit);
  assert.equal(actual.quantity_sold, expected.quantity_sold);
  assert.equal(actual.selling_price_override, expected.selling_price_override);
}
function rejected(qty, price, unit, label) {
  assert.throws(() => normalize(qty, price, unit), Error, label);
}

accepted('1', '', 'PCS', { quantity_sold: 1, selling_price_override: null });
accepted('2', '19.99', 'PCS', { quantity_sold: 2, selling_price_override: 19.99 });
accepted('0.25', '', 'KG', { quantity_sold: 0.25, selling_price_override: null });
accepted('1.25', '12.5', 'M', { quantity_sold: 1.25, selling_price_override: 12.5 });

rejected('', '', 'PCS', 'empty quantity is rejected');
rejected('0', '', 'PCS', 'zero quantity is rejected');
rejected('-1', '', 'PCS', 'negative quantity is rejected');
rejected('1.5', '', 'PCS', 'fractional PCS quantity is rejected');
rejected('1.234', '', 'KG', 'more than two quantity decimals are rejected');
rejected('1', '0', 'PCS', 'zero price override is rejected');
rejected('1', '-2.00', 'PCS', 'negative price override is rejected');
rejected('1', '10.999', 'PCS', 'more than two price decimals are rejected');

console.log('PASS POS cart edited-field normalizer (13 synthetic-only cases; no DOM, WordPress, database or sale writes)');
