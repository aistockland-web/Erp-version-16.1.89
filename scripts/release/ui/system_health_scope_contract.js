'use strict';

// Regression contract: runHealth must own the mode flags that its result renderer reads.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '../../..');
const appPath = path.join(root, 'src', 'stockland-erp-app', 'assets', 'app.js');
const source = fs.readFileSync(appPath, 'utf8');
const runStart = source.indexOf('async function runHealth(mode)');
assert.notEqual(runStart, -1, 'runHealth(mode) must exist');
const nextFunction = source.slice(runStart + 1).search(/\\n(?:async\\s+)?function\\s/);
const runSource = source.slice(runStart, nextFunction < 0 ? source.length : runStart + 1 + nextFunction);

const diagnostics = runSource.indexOf('const q=d.diagnostics||{};');
assert.notEqual(diagnostics, -1, 'runHealth must read the diagnostics object');
const declaration = runSource.indexOf("const quickOnly=q.health_mode==='quick',setupRequired=q.health_mode==='setup';", diagnostics);
assert.notEqual(declaration, -1, 'runHealth must declare quickOnly and setupRequired in its own scope');
const quickUse = runSource.indexOf('if(!quickOnly && integrity', diagnostics);
const setupUse = runSource.indexOf('if(setupRequired && upgradeGaps', diagnostics);
assert.ok(quickUse > declaration, 'quickOnly must be declared before supplemental scan rendering');
assert.ok(setupUse > declaration, 'setupRequired must be declared before schema-gap rendering');
console.log('PASS System Health renderer owns quickOnly/setupRequired flags before using them');
