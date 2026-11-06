'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const appRoot = process.argv[2];
if (!appRoot || !fs.existsSync(path.join(appRoot, 'assets/app.js'))) {
  throw new Error('Usage: node test_hr_text_only_ui.js <extracted-plugin-root>');
}

const app = fs.readFileSync(path.join(appRoot, 'assets/app.js'), 'utf8');
const hrCss = fs.readFileSync(path.join(appRoot, 'assets/css/12-hr-payroll-lifecycle.css'), 'utf8');
const builtCss = fs.readFileSync(path.join(appRoot, 'assets/app.css'), 'utf8');
const minCss = fs.readFileSync(path.join(appRoot, 'assets/app.min.css'), 'utf8');
const overlayRoot = process.argv[3] || null;

for (const name of [
  'function hrlcSectionIcon(',
  'function hrlcSocialIcon(',
  'sl-hrlc-section-icon',
  'sl-hrlc-icon-art',
  'sl-hrlc-social-icon',
  'sl-hrlc-social-icon-art'
]) {
  assert.equal(app.includes(name), false, 'generated app.js must not include ' + name);
  assert.equal(hrCss.includes(name), false, 'HR stylesheet must not include ' + name);
}
assert.equal(/\bsvg\b/i.test(hrCss), false, 'HR stylesheet contains no SVG icon rules');
for (const name of ['sl-hrlc-section-icon', 'sl-hrlc-icon-art', 'sl-hrlc-social-icon', 'sl-hrlc-social-icon-art']) {
  assert.equal(builtCss.includes(name), false, 'compiled ERP CSS must not contain HR icon selector ' + name);
  assert.equal(minCss.includes(name), false, 'minified ERP CSS must not contain HR icon selector ' + name);
}
console.log('PASS HR/payroll JS and all CSS outputs remove HR-specific SVG icons');

const sectionStart = app.indexOf('function hrlcSection(title,desc,body){');
const sectionEnd = app.indexOf('function hrlcLifecycleLists(d){', sectionStart);
assert.ok(sectionStart >= 0 && sectionEnd > sectionStart, 'text-only HR section renderer exists');
const sectionDefinition = app.slice(sectionStart, sectionEnd).trim();

const socialStart = app.indexOf('function hrlcSocialLinks(){');
const socialEnd = app.indexOf('function hrlcPayrollCyclePanel(d){', socialStart);
assert.ok(socialStart >= 0 && socialEnd > socialStart, 'text-only social links renderer exists');
const socialDefinition = app.slice(socialStart, socialEnd).trim();

const runtime = {
  state: { hrlcOpenSections: ['Payroll'] },
  hrlcEsc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[ch]);
  }
};
vm.createContext(runtime);
vm.runInContext(sectionDefinition, runtime, { timeout: 1000 });
vm.runInContext(socialDefinition, runtime, { timeout: 1000 });

const section = runtime.hrlcSection(
  'Payroll',
  'Controlled, approved payroll workflows',
  '<button data-hrlc-test-action>Save draft</button>'
);
assert.match(section, /<details[^>]*open/);
assert.match(section, /data-hrlc-section="Payroll"/);
assert.match(section, /aria-controls="hrlc-section-payroll-body"/);
assert.match(section, /aria-expanded="true"/);
assert.match(section, /role="heading" aria-level="3"/);
assert.match(section, /data-hrlc-test-action/);
assert.doesNotMatch(section, /<svg\b/i);
assert.doesNotMatch(section, /sl-hrlc-(?:section-icon|icon-art)/);
console.log('PASS HR accordion retains state, heading, ARIA controls and action markup without icons');

const social = runtime.hrlcSocialLinks();
for (const name of ['Facebook / Meta Business Suite', 'Instagram', 'TikTok Business Center', 'YouTube Studio']) {
  assert.ok(social.includes(name), 'social card retains ' + name);
}
for (const url of ['https://business.facebook.com/', 'https://www.instagram.com/', 'https://business.tiktok.com/', 'https://studio.youtube.com/']) {
  assert.ok(social.includes(url), 'social card retains official destination ' + url);
}
assert.match(social, /MANUAL EXPORT/);
assert.match(social, /timestamped platform exports/);
assert.doesNotMatch(social, /<svg\b/i);
assert.doesNotMatch(social, /sl-hrlc-social-icon/);
console.log('PASS social platform cards retain all labels/links/disclosure as text only');

assert.ok(app.includes('const uiIcon=name=>'), 'global ERP navigation icon helper is preserved');
assert.ok(app.includes('sl-dashboard-linechart'), 'dashboard chart renderer is preserved');
console.log('PASS non-HR ERP shell icons and dashboard chart remain present');

if (overlayRoot) {
  const overlayJs = fs.readFileSync(path.join(overlayRoot, 'hr-lifecycle-ui.js'), 'utf8');
  const overlayCss = fs.readFileSync(path.join(overlayRoot, 'hr-lifecycle-ui.css'), 'utf8');
  for (const token of ['function hrlcSectionIcon(', 'function hrlcSocialIcon(', 'sl-hrlc-section-icon', 'sl-hrlc-icon-art', 'sl-hrlc-social-icon', 'sl-hrlc-social-icon-art', '<svg']) {
    assert.equal(overlayJs.includes(token), false, 'raw HR overlay JS must not contain ' + token);
  }
  assert.equal(/\bsvg\b/i.test(overlayCss), false, 'raw HR overlay CSS contains no SVG token');
  for (const token of ['sl-hrlc-section-icon', 'sl-hrlc-icon-art', 'sl-hrlc-social-icon', 'sl-hrlc-social-icon-art']) {
    assert.equal(overlayCss.includes(token), false, 'raw HR overlay CSS must not contain ' + token);
  }
  console.log('PASS raw HR overlay source files are text-only with no SVG markup or selectors');
}

console.log('HR TEXT-ONLY UI: generated package and source overlay assertions passed');
