'use strict';

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../../..');
const app = fs.readFileSync(path.join(root, 'src/stockland-erp-app/assets/app.js'), 'utf8');
const registry = fs.readFileSync(path.join(root, 'src/stockland-erp-app/includes/rest/class-rest-pos-integrations.php'), 'utf8');
const importer = fs.readFileSync(path.join(root, 'src/stockland-erp-app/includes/rest/class-rest-import-v16.php'), 'utf8');
const bootstrap = fs.readFileSync(path.join(root, 'src/stockland-erp-app/stockland-inventory.php'), 'utf8');
const e2e = fs.readFileSync(path.join(root, 'scripts/release/e2e/s17_pos_integration_hub.php'), 'utf8');
const runner = fs.readFileSync(path.join(root, 'scripts/release/e2e/run_all.sh'), 'utf8');

function assert(ok, message) {
  if (!ok) throw new Error('Universal POS integration contract failed: ' + message);
}
function section(source, startText, endText) {
  const start = source.indexOf(startText);
  const end = source.indexOf(endText, start + startText.length);
  assert(start >= 0 && end > start, 'Cannot locate ' + startText);
  return source.slice(start, end);
}

const nav = section(app, 'function nav(){', 'function bindNavGroups(scope){');
assert(nav.includes("['integrations','POS Integrations',['owner','management','branch_manager'].includes(u.role)]"), 'POS Integrations menu must be available to Owner, Management and Branch Managers.');
const page = section(app, 'function posIntegrationsPage(){', '\nfunction productImportPage(){');
assert(page.includes('Universal POS Integration Hub'), 'Universal integration workspace must be present.');
assert(page.includes('id="posDeviceRegisterForm"'), 'Branch device registration form must be present.');
assert(page.includes('REST_API') && page.includes('WEBHOOK') && page.includes('LOCAL_GATEWAY') && page.includes('DEVICE_SDK') && page.includes('FILE_EXCHANGE'), 'UI must expose all vendor-neutral connection patterns.');
assert(page.includes('NOT CONNECTED') && page.includes('No credentials here'), 'UI must not imply live connections or expose credential entry.');
assert(app.includes("api('/pos-integrations/adapters')") && app.includes("api('/pos-integrations/devices')"), 'UI must use the actual registry REST endpoints.');

const daily = section(app, 'function dailyImportPage(){', '\nfunction posIntegrationsPage(){');
assert(daily.includes('id="dailyPosDevice"') && daily.includes('name="pos_device_id"'), 'Daily POS page must select an optional registered source device.');
const preview = section(app, 'async function previewDailyImport(e){', '\n\n\nasync function openDailyJobEditor(');
assert(preview.includes("fd.append('pos_device_id',pf.value)"), 'Selected device identity must be submitted with the existing Daily POS preview.');

assert(registry.includes('extends Stockland_REST_Base'), 'Registry must use the existing common REST base.');
assert(registry.includes("'/pos-integrations/adapters'") && registry.includes("'/pos-integrations/devices'"), 'Registry routes must be registered.');
assert(registry.includes('Stockland_Authority::can_daily_import()'), 'Assigned Daily POS role is required for Branch Manager/Sales Employee scope.');
assert(registry.includes('Stockland_Authority::can_access_location( $branch_id )'), 'Device registration must enforce server-side branch scope.');
assert(registry.includes('Stockland_Authority::is_management()'), 'Owner/Management company-wide authorization must be explicit.');
assert(registry.includes('pos_int_device_') && registry.includes("Stockland_DB::table( 'settings' )"), 'Registry must use existing Core settings storage, not another table.');
assert(registry.includes('Stockland_Audit::log(') && registry.includes('CORE_SETTINGS:'), 'Each registration must have a hash-bound immutable audit event.');
assert(registry.includes("'connection_status' => 'NOT_CONNECTED'") && registry.includes("'credential_status' => 'NOT_CONFIGURED'"), 'A device record must not imply connection or credentials.');
assert(registry.includes('resolve_device_for_import') && registry.includes('pos_device_branch_mismatch'), 'A registered device must be verified against the exact branch before import mapping.');
assert(registry.includes("'api_key', 'token', 'secret', 'client_secret'"), 'Device registration must reject supplied credentials.');
assert(!registry.includes('/import/daily-sales/commit'), 'Integration registry must not expose a direct sales commit/post route.');
assert(!registry.includes('CREATE TABLE'), 'Integration registry must not modify the existing schema.');

assert(bootstrap.includes("class-rest-pos-integrations.php") && bootstrap.includes('new Stockland_REST_POS_Integrations()'), 'Registry controller must be loaded by the 16.2.18 plugin bootstrap.');
assert(importer.includes('Stockland_REST_POS_Integrations::resolve_device_for_import'), 'Daily POS import must resolve registered device identity.');
assert(importer.includes('$mapping_source_system = $registered_device ?'), 'Registered devices must use isolated SKU mapping namespaces.');
assert(importer.includes(": 'THIRD_PARTY_POS';"), 'Existing generic POS mappings must remain compatible.');
assert(importer.includes('/import/daily-sales/preview') && importer.includes('/import/daily-sales/commit'), 'All intake still uses the established preview/release routes.');

assert(runner.includes('s17_pos_integration_hub'), 'The database-backed integration registry test must run in the full synthetic E2E sequence.');
assert(e2e.includes('Branch Manager cannot register a device for another branch'), 'E2E must test branch boundary.');
assert(e2e.includes('device registration rejects inline API credentials'), 'E2E must test credential rejection.');
assert(e2e.includes('no sales, inventory-ledger or GL records'), 'E2E must test that device registration has no financial or stock side effects.');
assert(e2e.includes('/import/code-maps'), 'E2E must exercise the real Management product-code mapping route for registered device sources.');
assert(e2e.includes('same external PLU resolves to device A Product Master SKU only in device A namespace') && e2e.includes('same external PLU resolves to device B Product Master SKU only in device B namespace'), 'E2E must prove overlapping PLU codes resolve independently for two POS devices.');
assert(importer.includes("$mapping_source_system = $registered_device ? (string) $registered_device['source_system'] : 'THIRD_PARTY_POS';"), 'Device imports must use per-device mapping namespaces while generic imports retain legacy mappings.');

console.log('RESULT PASS: universal connector catalogue, Branch Manager registry UI, branch/source isolation, no-secret/no-schema boundary, shared Daily POS staging and E2E coverage.');
