'use strict';

const fs = require('fs');
const path = require('path');
const postcss = require('postcss');

const root = process.argv[2];
if (!root || !fs.existsSync(root)) {
  throw new Error('Pass extracted stockland-erp-app directory');
}

function walk(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(directory, entry.name);
    if (entry.isSymbolicLink()) throw new Error('Unexpected symlink: ' + full);
    return entry.isDirectory() ? walk(full) : [full];
  });
}

const files = walk(root);
const jsonFiles = files.filter((file) => file.toLowerCase().endsWith('.json'));
for (const file of jsonFiles) JSON.parse(fs.readFileSync(file, 'utf8'));

const assets = path.join(root, 'assets');
const manifestPath = path.join(assets, 'css', 'modules.json');
let cssModuleCount = 0;
if (fs.existsSync(manifestPath)) {
  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  if (!Array.isArray(manifest.modules) || manifest.modules.length < 1 ||
      new Set(manifest.modules).size !== manifest.modules.length) {
    throw new Error('CSS source-module manifest is empty or contains duplicate entries');
  }
  const moduleRoot = path.resolve(assets, 'css');
  for (const name of manifest.modules) {
    if (typeof name !== 'string' || path.basename(name) !== name || !name.endsWith('.css')) {
      throw new Error('Unsafe CSS module name: ' + String(name));
    }
    const file = path.resolve(moduleRoot, name);
    if (!file.startsWith(moduleRoot + path.sep) || !fs.existsSync(file)) {
      throw new Error('Missing or unsafe CSS source module: ' + file);
    }
    postcss.parse(fs.readFileSync(file, 'utf8'), { from: file });
    cssModuleCount += 1;
  }
}

let parsedCss = 0;
for (const file of files.filter((item) => item.toLowerCase().endsWith('.css'))) {
  postcss.parse(fs.readFileSync(file, 'utf8'), { from: file });
  parsedCss += 1;
}
console.log(JSON.stringify({
  result: 'PASS',
  files: files.length,
  json_files_parsed: jsonFiles.length,
  css_files_parsed: parsedCss,
  css_source_modules_validated: cssModuleCount,
  javascript_files_for_node_check: files.filter((item) => item.toLowerCase().endsWith('.js')).length,
  css_bundle_byte_parity: 'NOT ASSERTED BY THIS GENERIC PACKAGE CHECK'
}, null, 2));
