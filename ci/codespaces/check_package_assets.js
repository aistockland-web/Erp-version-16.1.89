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
if (fs.existsSync(manifestPath)) {
  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  if (!Array.isArray(manifest.modules) || manifest.modules.length < 1 ||
      new Set(manifest.modules).size !== manifest.modules.length) {
    throw new Error('CSS module manifest empty or contains duplicates');
  }
  const chunks = manifest.modules.map((name) => {
    if (path.basename(name) !== name || !name.endsWith('.css')) {
      throw new Error('Unsafe CSS module name: ' + name);
    }
    const file = path.join(assets, 'css', name);
    if (!fs.existsSync(file)) throw new Error('Missing CSS source module: ' + file);
    return fs.readFileSync(file, 'utf8').trim();
  });
  const compiled = chunks.join('\n\n') + '\n';
  const appCss = path.join(assets, 'app.css');
  if (!fs.existsSync(appCss) || compiled !== fs.readFileSync(appCss, 'utf8')) {
    throw new Error('app.css differs from ordered CSS source modules');
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
  javascript_files_for_node_check: files.filter((item) => item.toLowerCase().endsWith('.js')).length,
  css_module_parity: fs.existsSync(manifestPath) ? 'PASS' : 'NOT APPLICABLE'
}, null, 2));
