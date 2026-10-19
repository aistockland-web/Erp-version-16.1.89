'use strict';
const fs = require('fs');
const path = require('path');
const postcss = require('postcss');
const root = process.argv[2];
if (!root || !fs.existsSync(root)) throw new Error('Pass extracted stockland-erp-app path');
const assets = path.join(root, 'assets');
const manifest = JSON.parse(fs.readFileSync(path.join(assets, 'css/modules.json'), 'utf8'));
if (manifest.release !== '16.1.89' || manifest.modules.length !== 6 || new Set(manifest.modules).size !== 6) {
  throw new Error('Expected six unique 16.1.89 CSS modules');
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
  ['.sl-content-inner>.sl-page-title{position:sticky', 'sticky title layout'],
  ['.sl-mobile-menu-open .sl-mobile-menu{display:grid}', 'mobile navigation layout']
]) {
  if (!source.includes(selector)) throw new Error('Missing ' + label);
  console.log('PASS ' + label);
}
console.log('PASS ordered CSS source/build/minified equivalence');
