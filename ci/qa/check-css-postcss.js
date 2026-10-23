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
for (const [selector, label] of [
  ['.sl-payroll-readiness-reasons', 'payroll diagnostics styling'],
  ['.sl-mobile-menu', 'mobile navigation styling'],
  ['.sl-overlay .sl-req-drawer .sl-approval-grid', 'body-level requisition approval drawer layout'],
  ['.sl-overlay .sl-req-drawer .sl-approval-person small', 'approval name and timestamp wrapping']
]) {
  if (!source.includes(selector)) throw new Error('Missing ' + label);
  console.log('PASS ' + label);
}
if (!/@media\s*\(max-width\s*:\s*767px\)/.test(source)) {
  throw new Error('Phone approval drawer breakpoint (767px) is missing');
}
if (!/flex:\s*0 1 auto/.test(source) || !/overflow-wrap:\s*anywhere/.test(source)) {
  throw new Error('Approval label/status wrapping and flex sizing protection is missing');
}
const mediaRules = (source.match(/@media\b/g) || []).length;
if (mediaRules < 5 || !/max-width\s*:/.test(source)) {
  throw new Error('Responsive breakpoint coverage missing: expected at least five @media blocks and a max-width breakpoint');
}
console.log('PASS requisition approval phone/tablet/desktop CSS guards');
console.log('PASS responsive media-query coverage (' + mediaRules + ' @media rules)');
console.log('PASS ordered CSS source/build/minified equivalence');
