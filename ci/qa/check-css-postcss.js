'use strict';
const fs = require('fs');
const path = require('path');
const postcss = require('postcss');
const crypto = require('crypto');
const root = process.argv[2];
if (!root || !fs.existsSync(root)) throw new Error('Pass extracted stockland-erp-app path');
const assets = path.join(root, 'assets');
const manifest = JSON.parse(fs.readFileSync(path.join(assets, 'css/modules.json'), 'utf8'));
const releaseManifest = JSON.parse(fs.readFileSync(path.join(root, 'RELEASE_MANIFEST.json'), 'utf8'));
const expectedModuleCount = ['16.2.0', '16.2.1', '16.2.2', '16.2.3', '16.2.4', '16.2.5', '16.2.6', '16.2.7', '16.2.9'].includes(releaseManifest.release) ? 12 : 6;
if (!releaseManifest.release || manifest.release !== releaseManifest.release ||
    !Array.isArray(manifest.modules) || manifest.modules.length !== expectedModuleCount ||
    new Set(manifest.modules).size !== expectedModuleCount) {
  throw new Error('CSS source-module count, uniqueness, or release marker is invalid');
}
const chunks = manifest.modules.map(name => fs.readFileSync(path.join(assets, 'css', name), 'utf8').trim());
const built = chunks.join('\n\n') + '\n';
const appCss = fs.readFileSync(path.join(assets, 'app.css'), 'utf8');
const appMin = fs.readFileSync(path.join(assets, 'app.min.css'), 'utf8').trim();
if (appCss.includes('\\n#hrPage .sl-hrlc-social-icon') || appCss.includes('\\n#hrPage .sl-hrlc-social-copy') || appMin.includes('\\n#hrPage .sl-hrlc-social-icon')) {
  throw new Error('HR/social SVG stylesheet contains literal backslash-n characters that break selector boundaries');
}

// Match the release builder: remove comments and collapse whitespace outside
// quoted strings only. Do not strip whitespace around punctuation globally.
function minifyCssPreservingSemantics(source) {
  let out = '';
  let index = 0;
  let quote = null;
  let escaped = false;
  let pendingSpace = false;
  while (index < source.length) {
    const ch = source[index];
    if (quote !== null) {
      out += ch;
      if (escaped) escaped = false;
      else if (ch === '\\') escaped = true;
      else if (ch === quote) quote = null;
      index += 1;
      continue;
    }
    if (ch === '"' || ch === "'") {
      if (pendingSpace && out.length) out += ' ';
      pendingSpace = false;
      quote = ch;
      out += ch;
      index += 1;
      continue;
    }
    if (ch === '/' && source[index + 1] === '*') {
      const end = source.indexOf('*/', index + 2);
      if (end < 0) throw new Error('Unterminated CSS comment.');
      index = end + 2;
      continue;
    }
    if (/\s/.test(ch)) {
      pendingSpace = true;
      index += 1;
      continue;
    }
    if (pendingSpace && out.length) out += ' ';
    pendingSpace = false;
    out += ch;
    index += 1;
  }
  if (quote !== null) throw new Error('Unterminated CSS quoted string.');
  return out.trim();
}
const fixture = '.desc :hover{content:"ratio: 9 : 16";width:calc(100% - 2rem)} /* test */ .next{color:red}';
const fixtureMin = minifyCssPreservingSemantics(fixture);
if (!fixtureMin.includes('.desc :hover') ||
    !fixtureMin.includes('"ratio: 9 : 16"') ||
    !fixtureMin.includes('calc(100% - 2rem)') ||
    fixtureMin.includes('/* test */')) {
  throw new Error('CSS minifier semantic safety fixture failed');
}
if (built !== appCss) throw new Error('app.css differs from ordered CSS source modules');
if (['16.2.0', '16.2.1', '16.2.2', '16.2.3', '16.2.4', '16.2.5', '16.2.6', '16.2.7', '16.2.9'].includes(releaseManifest.release)) {
  const expectedTail = [
    '07-dashboard-operations.css',
    '08-reporting-worksheet.css',
    '09-command-center-theme.css',
    '10-bootstrap-desktop-search.css',
    '11-operational-workflows.css',
    '12-hr-payroll-lifecycle.css'
  ];
  if (JSON.stringify(manifest.modules.slice(-6)) !== JSON.stringify(expectedTail)) {
    throw new Error('16.2.0 CSS modules are not in the approved cascade order');
  }
  if (minifyCssPreservingSemantics(built) !== appMin) {
    throw new Error('app.min.css differs from semantics-safe minification of source modules');
  }
} else {
  // Keep historical release artifacts reproducible; the new builder uses the
  // safer minifier on 16.2.0+ candidate builds.
  const legacyNormalize = value => value.replace(/\/\*[\s\S]*?\*\//g, ' ')
    .replace(/\s+/g, ' ').trim().replace(/\s*([{}:;,])\s*/g, '$1').replace(/;}/g, '}');
  if (legacyNormalize(built) !== appMin) {
    throw new Error('Historical app.min.css differs from its release-era normalizer');
  }
}
if (['16.2.0', '16.2.1', '16.2.2', '16.2.3', '16.2.4', '16.2.5', '16.2.6', '16.2.7', '16.2.9'].includes(releaseManifest.release)) {
  const bootstrap = fs.readFileSync(path.join(assets, 'bootstrap.min.css'));
  const header = bootstrap.toString('utf8', 0, 220);
  const digest = crypto.createHash('sha256').update(bootstrap).digest('hex');
  if (!header.includes('Bootstrap  v5.3.8')) throw new Error('Bundled Bootstrap must be stable v5.3.8');
  if (!releaseManifest.frontend ||
      releaseManifest.frontend.bootstrap_version !== '5.3.8' ||
      releaseManifest.frontend.bootstrap_asset_sha256 !== digest) {
    throw new Error('Bootstrap version/hash must match the release manifest');
  }
  console.log('PASS Bootstrap 5.3.8 local asset version and SHA-256');
}
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
if (!source.includes(':focus-visible') || !source.includes('prefers-reduced-motion')) {
  throw new Error('Focus-visible and reduced-motion accessibility rules are required');
}
console.log('PASS keyboard focus and reduced-motion CSS controls');
if (scope === 'release-candidate') {
  const candidateSelectors = [
    ['.sl-overlay .sl-req-drawer .sl-approval-grid', 'body-level requisition approval drawer layout'],
    ['.sl-overlay .sl-req-drawer .sl-approval-person small', 'approval name and timestamp wrapping'],
    ['body[data-sl-view="pos"] #stockland-app #posForm', 'legacy Retail POS compatibility selector'],
    ['#stockland-app #posCartForm', 'modern atomic POS cart form'],
    ['#stockland-app .sl-pos-cart-summary', 'POS cart total preview cards'],
    ['#stockland-app .sl-pos-cart-table', 'POS cart line editing table'],
    ['#stockland-app .sl-pos-cart-meta', 'POS cart filter and checkout metadata grid'],
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
  if (!/#stockland-app\s+\.sl-pos-cart-summary/.test(source) ||
      !/#stockland-app\s+\.sl-pos-cart-table/.test(source) ||
      !/#stockland-app\s+\.sl-pos-cart-meta/.test(source) ||
      !/@media\s*\(max-width\s*:\s*620px\)/.test(source)) {
    throw new Error('Atomic POS cart table, summary, checkout metadata or mobile breakpoints are missing');
  }
  console.log('PASS requisition approval and POS cart phone/tablet/desktop CSS guards');
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
