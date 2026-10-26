'use strict';
const fs = require('fs');
const path = require('path');
const postcss = require('postcss');
const root = process.argv[2];
if (!root || !fs.existsSync(root)) throw new Error('Pass extracted stockland-erp-app path');
const assets = path.join(root, 'assets');
const manifest = JSON.parse(fs.readFileSync(path.join(assets, 'css/modules.json'), 'utf8'));
const releaseManifest = JSON.parse(fs.readFileSync(path.join(root, 'RELEASE_MANIFEST.json'), 'utf8'));
if (!releaseManifest.release || manifest.release !== releaseManifest.release || manifest.modules.length !== 6 || new Set(manifest.modules).size !== 6) {
  throw new Error('Expected six unique CSS modules with a release marker matching RELEASE_MANIFEST.json');
}
const chunks = manifest.modules.map(name => fs.readFileSync(path.join(assets, 'css', name), 'utf8').trim());
const built = chunks.join('\n\n') + '\n';
const appCss = fs.readFileSync(path.join(assets, 'app.css'), 'utf8');
const appMin = fs.readFileSync(path.join(assets, 'app.min.css'), 'utf8').trim();
const normalize = value => value.replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\s+/g, ' ').trim().replace(/\s*([{}:;,])\s*/g, '$1').replace(/;}/g, '}');
if (built !== appCss) throw new Error('app.css differs from ordered CSS source modules');
if (normalize(built) !== appMin) throw new Error('app.min.css differs from deterministic normalized CSS source');
for (const file of ['app.css', 'app.min.css', 'bootstrap.min.css']) {
  postcss.parse(fs.readFileSync(path.join(assets, file), 'utf8'), { from: file });
  console.log('PASS PostCSS parse ' + file);
}
const source = chunks.join('\n');
const cliArgs = process.argv.slice(3);
let scope = 'baseline';
for (let i = 0; i < cliArgs.length; i++) {
  if (cliArgs[i] === '--scope') {
    scope = cliArgs[i + 1];
    i++;
  } else {
    throw new Error('Unknown argument: ' + cliArgs[i]);
  }
}
if (!['baseline', 'release-candidate'].includes(scope)) {
  throw new Error('CSS validation scope must be baseline or release-candidate');
}
console.log('CSS validation scope: ' + scope);

const baselineSelectors = [
  ['.sl-payroll-readiness-reasons', 'payroll diagnostics styling'],
  ['.sl-mobile-menu', 'mobile navigation styling']
];
for (const [selector, label] of baselineSelectors) {
  if (!source.includes(selector)) throw new Error('Missing ' + label);
  console.log('PASS ' + label);
}
if (scope === 'release-candidate') {
  const candidateSelectors = [
    ['.sl-overlay .sl-req-drawer .sl-approval-grid', 'body-level requisition approval drawer layout'],
    ['.sl-overlay .sl-req-drawer .sl-approval-person small', 'approval name and timestamp wrapping'],
    ['body[data-sl-view="pos"] #stockland-app #posForm', 'Retail POS responsive checkout form'],
    ['#stockland-app .sl-global-search', 'desktop global-search sizing'],
    ['#stockland-app #slGlobalSearchResults', 'desktop global-search popup bounds'],
    ['#stockland-app .sl-content-inner>.sl-page-title>.sl-actions', 'desktop Product Master title-action hit-target layer'],
    ['.sl-report-retry-actions', 'report retry spacing utility'],
    ['.sl-print-report-hidden', 'selected-report print state utility']
  ];
  for (const [selector, label] of candidateSelectors) {
    if (!source.includes(selector)) throw new Error('Missing ' + label);
    console.log('PASS ' + label);
  }
  if (!/@media\s*\(max-width\s*:\s*767px\)/.test(source)) {
    throw new Error('Phone approval drawer breakpoint (767px) is missing');
  }
  if (!/flex:\s*0 1 auto/.test(source) || !/overflow-wrap:\s*anywhere/.test(source)) {
    throw new Error('Approval label/status wrapping and flex sizing protection is missing');
  }
  if (!/body\[data-sl-view="pos"\] #stockland-app #posForm/.test(source) ||
      !/min-height:\s*52px/.test(source) ||
      !/@media\s*\(max-width\s*:\s*359px\)/.test(source)) {
    throw new Error('Responsive POS checkout touch targets or narrow-phone breakpoint are missing');
  }
  console.log('PASS requisition approval phone/tablet/desktop CSS guards');
}
if (!/@media\s*\(max-width/.test(source)) {
  throw new Error('Responsive max-width media query is missing');
}
const mediaRules = (source.match(/@media\b/g) || []).length;
if (mediaRules < 5 || !/max-width\s*:/.test(source)) {
  throw new Error('Responsive breakpoint coverage missing: expected at least five @media blocks and a max-width breakpoint');
}
console.log('PASS responsive media-query coverage (' + mediaRules + ' @media rules)');
const appJs = fs.readFileSync(path.join(assets, 'app.js'), 'utf8');
if (appJs.includes('sl-desktop-title-action-layer-fix') || appJs.includes("style.textContent='@media (min-width:992px)")) {
  throw new Error('Desktop CSS must be compiled by Python into the ordered CSS modules, not injected by JavaScript');
}
console.log('PASS desktop CSS is compiled, not injected at runtime');
console.log('PASS ordered CSS source/build/minified equivalence');
