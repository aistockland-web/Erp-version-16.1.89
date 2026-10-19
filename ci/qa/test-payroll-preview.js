'use strict';
const fs = require('fs');
const assert = require('assert');
const source = fs.readFileSync(process.argv[2], 'utf8');
const start = source.indexOf('function slPayrollMinor(');
const end = source.indexOf('function slPayrollAmount(', start);
assert(start >= 0 && end > start, 'payroll preview helpers are present');
const functions = new Function(source.slice(start, end) + '; return {slPayrollMinor, slPayrollPercentMinor, slPayrollTaxPreview};')();
const rules = {
  rule_version: 'ET-PAYE-1395-2025-v3-PENSION-BASE',
  employee_pension_rate: '0.07',
  employer_pension_rate: '0.11',
  monthly_tax_bands: [
    {up_to:'2000.00',rate:'0.00',credit:'0.00',label:'ETB 0–2,000'},
    {up_to:'4000.00',rate:'0.15',credit:'300.00',label:'ETB 2,001–4,000'},
    {up_to:'7000.00',rate:'0.20',credit:'500.00',label:'ETB 4,001–7,000'},
    {up_to:'10000.00',rate:'0.25',credit:'850.00',label:'ETB 7,001–10,000'},
    {up_to:'14000.00',rate:'0.30',credit:'1350.00',label:'ETB 10,001–14,000'},
    {up_to:null,rate:'0.35',credit:'2050.00',label:'Above ETB 14,000'}
  ]
};
const gross = functions.slPayrollMinor(30000);
const pension = functions.slPayrollPercentMinor(gross, rules.employee_pension_rate);
const taxable = Math.max(0, gross-pension);
const tax = functions.slPayrollTaxPreview(taxable/100, rules);
const net = gross-Math.round(tax.amount*100)-pension;
assert.strictEqual(rules.rule_version, 'ET-PAYE-1395-2025-v3-PENSION-BASE');
assert.strictEqual(taxable/100, 27900.00);
assert.strictEqual(pension/100, 2100.00);
assert.strictEqual(tax.amount, 7715.00);
assert.strictEqual(net/100, 20185.00);
console.log('PASS browser preview retains v3 pension-first PAYE rule');
console.log('PASS ETB 30,000 regression: taxable base 27,900; PAYE 7,715; pension 2,100; net pay 20,185');
console.log('PASS payroll preview assertions');
