'use strict';

const fs = require('node:fs');
const path = require('node:path');
const postcss = require('../cssqa/node_modules/postcss');

const root = path.resolve(process.argv[2] || '');
if (!root || !fs.existsSync(root)) {
  throw new Error('Pass the extracted stockland-erp-app directory.');
}

function walk(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const absolute = path.join(directory, entry.name);
    return entry.isDirectory() ? walk(absolute) : [absolute];
  });
}

const files = walk(root);
const cssFiles = files.filter((file) => file.endsWith('.css'));
const jsonFiles = files.filter((file) => file.endsWith('.json'));
const jsFiles = files.filter((file) => file.endsWith('.js'));

for (const file of jsonFiles) {
  JSON.parse(fs.readFileSync(file, 'utf8'));
}
for (const file of cssFiles) {
  postcss.parse(fs.readFileSync(file, 'utf8'), { from: file });
}

const moduleManifest = path.join(root, 'assets', 'css', 'modules.json');
const appCss = path.join(root, 'assets', 'app.css');
if (fs.existsSync(moduleManifest) && fs.existsSync(appCss)) {
  const manifest = JSON.parse(fs.readFileSync(moduleManifest, 'utf8'));
  if (!Array.isArray(manifest.modules) || manifest.modules.length === 0) {
    throw new Error('CSS modules manifest is present but its module list is empty.');
  }
  const chunks = manifest.modules.map((name) => {
    const source = path.join(root, 'assets', 'css', name);
    if (!fs.existsSync(source)) throw new Error('Missing CSS source module: ' + name);
    return fs.readFileSync(source, 'utf8').trim();
  });
  const expected = chunks.join('\n') + '\n';
  const actual = fs.readFileSync(appCss, 'utf8');
  if (actual !== expected) {
    throw new Error('Compiled app.css does not match the ordered source modules.');
  }
  console.log('PASS ordered CSS source/module parity (' + manifest.modules.length + ' modules)');
} else {
  console.log('NOT RUN CSS module parity: this historical package has no module manifest or app.css pair');
}

console.log('PASS PostCSS parsed ' + cssFiles.length + ' CSS files');
console.log('PASS parsed ' + jsonFiles.length + ' JSON manifests');
console.log('JavaScript files to syntax-check: ' + jsFiles.length);
