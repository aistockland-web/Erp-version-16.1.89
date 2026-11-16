// Payroll rule builder (form fields instead of JSON): runs the real app.js builder code in a browser page and
// checks that the generated rule has exactly the shape the server's calculator contract accepts.
// Synthetic test values only. Usage: NODE_PATH=<playwright> node scripts/release/ui/rule_builder_unit.js
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const src = fs.readFileSync(path.join(__dirname, '../../../src/stockland-erp-app/assets/app.js'), 'utf8');
const cut = (from, to) => { const a = src.indexOf(from); const b = src.indexOf(to, a); if (a < 0 || b < 0) throw new Error('marker missing: ' + from); return src.slice(a, b); };
const code = 'const esc=v=>String(v).replace(/[&<>"\']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;","\'":"&#39;"}[c]));window.__toasts=[];const toast=(m,e)=>window.__toasts.push(String(m));\n'
  + cut('function hrlcEsc(', 'function hrlcForm(') .split('\n')[0] + '\n' + cut('function hrlcRuleBuilderHtml()', 'function hrlcField(')
  + '\nwindow.hrlcRuleBuilderHtml=hrlcRuleBuilderHtml;window.bindRuleBuilder=bindRuleBuilder;window.rbPctToDec=rbPctToDec;window.rbMoney=rbMoney;';
let fail = 0;
const check = (label, cond, detail) => { console.log((cond ? 'PASS ' : 'FAIL ') + label + (cond ? '' : ' :: ' + JSON.stringify(detail))); if (!cond) fail++; };
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(String(e)));
  await page.setContent('<form id="f"><select name="rule_type"><option>PAYROLL</option><option>ALLOWANCE</option><option>LEAVE</option></select><div id="host"></div><textarea name="legal_review_approved_parameters"></textarea><button type="submit">save</button></form>');
  await page.addScriptTag({ content: code });
  await page.evaluate(() => { document.getElementById('host').innerHTML = window.hrlcRuleBuilderHtml(); window.bindRuleBuilder(); });
  const val = n => page.$eval('[name=' + n + ']', e => e.value);

  // Percent / money converters.
  const conv = await page.evaluate(() => ({ a: window.rbPctToDec('5'), b: window.rbPctToDec('7.5'), c: window.rbPctToDec('0'), d: window.rbPctToDec('100'), e: window.rbPctToDec('101'), f: window.rbPctToDec('abc'), g: window.rbPctToDec('0.0125'), m1: window.rbMoney('1000'), m2: window.rbMoney('600.5'), m3: window.rbMoney('-1'), m4: window.rbMoney('1.234') }));
  check('percent 5 -> 0.05', conv.a === '0.05', conv);
  check('percent 7.5 -> 0.075', conv.b === '0.075', conv);
  check('percent 0 -> 0.00 and 100 -> 1.00', conv.c === '0.00' && conv.d === '1.00', conv);
  check('percent over 100 or text rejected', conv.e === null && conv.f === null, conv);
  check('percent with 4 decimals -> 6-decimal rate', conv.g === '0.000125', conv);
  check('money normalised to 2 decimals; negatives and 3 decimals rejected', conv.m1 === '1000.00' && conv.m2 === '600.50' && conv.m3 === null && conv.m4 === null, conv);

  // PAYROLL: same synthetic values as the s16 payroll cycle test.
  check('empty form is incomplete (no rule emitted)', (await val('rules_json')) === '', await val('rules_json'));
  await page.fill('[data-rb=emp_pct]', '5');
  await page.fill('[data-rb=er_pct]', '8');
  await page.selectOption('[data-rb=base]', 'TAXABLE_GROSS_LESS_EMPLOYEE_PENSION');
  const rows = page.locator('[data-rb-bands] tr');
  await rows.nth(0).locator('.rb-up').fill('1000'); await rows.nth(0).locator('.rb-rate').fill('0');
  await rows.nth(1).locator('.rb-up').fill(''); await rows.nth(1).locator('.rb-rate').fill('20');
  await page.click('[data-rb-add-band]');
  const rows3 = page.locator('[data-rb-bands] tr');
  // Order: 1000 / middle / top. Put the new row in the middle by editing values.
  await rows3.nth(1).locator('.rb-up').fill('5000'); await rows3.nth(1).locator('.rb-rate').fill('10'); await rows3.nth(1).locator('.rb-credit').fill('100');
  await rows3.nth(2).locator('.rb-up').fill(''); await rows3.nth(2).locator('.rb-rate').fill('20'); await rows3.nth(2).locator('.rb-credit').fill('600');
  const rules = JSON.parse(await val('rules_json') || '{}');
  const expected = {
    tax_base_method: 'TAXABLE_GROSS_LESS_EMPLOYEE_PENSION', paye_tax_base_method: 'TAXABLE_EARNINGS_LESS_EMPLOYEE_PENSION', employee_pension_rate: '0.05', employer_pension_rate: '0.08',
    pensionable_base_method: 'SUM_PENSIONABLE_ITEMS', rounding_method: 'HALF_UP_MINOR_UNIT',
    monthly_tax_bands: [{ up_to: '1000.00', rate: '0.00', credit: '0.00' }, { up_to: '5000.00', rate: '0.10', credit: '100.00' }, { up_to: null, rate: '0.20', credit: '600.00' }],
    paye_bands: [{ up_to: '1000.00', rate: '0.00' }, { up_to: '5000.00', rate: '0.10' }, { up_to: null, rate: '0.20' }],
    component_treatments: { BASE: { kind: 'EARNING', taxable: true, pensionable: true }, TAXABLE_ALLOWANCE: { kind: 'EARNING', taxable: true, pensionable: false }, EXEMPT_CANDIDATE: { kind: 'EARNING', taxable: false, pensionable: false }, OTHER_DEDUCTION: { kind: 'DEDUCTION', taxable: false, pensionable: false } },
  };
  check('payroll form builds exactly the contract shape used by the payroll cycle test', JSON.stringify(rules) === JSON.stringify(expected), rules);
  const ap = await val('legal_review_approved_parameters');
  check('reviewer approved-parameter list is filled in automatically', ap.includes('monthly_tax_bands') && ap.includes('tax_base_method') && ap.includes('paye_bands'), ap);

  // Validation messages.
  await rows3.nth(2).locator('.rb-up').fill('9000');
  check('top band with an upper limit blocks the rule', (await val('rules_json')) === '' && /last band/i.test(await page.$eval('[data-rb-status]', e => e.textContent)));
  await rows3.nth(2).locator('.rb-up').fill('');
  await rows3.nth(1).locator('.rb-up').fill('500');
  check('non-increasing band limits block the rule', (await val('rules_json')) === '');
  await rows3.nth(1).locator('.rb-up').fill('5000');
  await page.fill('[data-rb=emp_pct]', 'abc');
  check('bad pension percent blocks the rule', (await val('rules_json')) === '');
  await page.fill('[data-rb=emp_pct]', '5');
  check('fixing the value restores the rule', (await val('rules_json')) !== '');

  // Submit is blocked while incomplete.
  await page.fill('[data-rb=er_pct]', '');
  const submitted = await page.evaluate(() => new Promise(res => { const f = document.getElementById('f'); let got = false; f.addEventListener('submit', e => { got = true; e.preventDefault(); }); f.requestSubmit(); setTimeout(() => res(got), 50); }));
  check('submitting an incomplete rule is stopped before the server call', submitted === false || (await page.evaluate(() => window.__toasts.length)) > 0);
  await page.fill('[data-rb=er_pct]', '8');

  // ALLOWANCE and generic parameter sections.
  await page.selectOption('[name=rule_type]', 'ALLOWANCE');
  check('allowance section replaces payroll section', await page.$eval('[data-rb-section=ALLOWANCE]', e => !e.hidden) && await page.$eval('[data-rb-section=PAYROLL]', e => e.hidden));
  await page.fill('[data-rb-allow] .rb-cat', 'transport');
  await page.selectOption('[data-rb-allow] .rb-tax', 'EXEMPT_CAP');
  await page.fill('[data-rb-allow] .rb-cap', '2200');
  await page.fill('[data-rb-allow] .rb-clause', 'Art. 1 (synthetic)');
  const comps = JSON.parse(await val('components'));
  check('allowance row builds a classified component', comps.length === 1 && comps[0].category === 'TRANSPORT' && comps[0].tax_treatment === 'EXEMPT_CAP' && comps[0].exemption_cap === '2200.00' && comps[0].pensionable_treatment === 'SALARY_ONLY', comps);
  await page.selectOption('[name=rule_type]', 'LEAVE');
  await page.fill('[data-rb-params] .rb-pn', 'Annual leave days');
  await page.fill('[data-rb-params] .rb-pv', '16');
  const leave = JSON.parse(await val('rules_json'));
  check('parameter rows build a named rule', leave.parameters && leave.parameters.annual_leave_days === '16', leave);
  check('no browser errors', errors.length === 0, errors);
  await browser.close();
  console.log(fail ? `RESULT: FAIL (${fail})` : 'RESULT: PASS');
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
