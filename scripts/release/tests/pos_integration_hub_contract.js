'use strict';

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../../..');
const app = fs.readFileSync(path.join(root, 'src/stockland-erp-app/assets/app.js'), 'utf8');
const registry = fs.readFileSync(path.join(root, 'src/stockland-erp-app/includes/rest/class-rest-pos-integrations.php'), 'utf8');
const importer = fs.readFileSync(path.join(root, 'src/stockland-erp-app/includes/rest/class-rest-import-v16.php'), 'utf8');
const bootstrap = fs.readFileSync(path.join(root, 'src/stockland-erp-app/stockland-inventory.php'), 'utf8');
const e2e = fs.readFileSync(path.join(root, 'scripts/release/e2e/s19_pos_integration_hub.php'), 'utf8');
const folderAgent = fs.readFileSync(path.join(root, 'tools/stockland-pos-folder-agent/Run-StocklandPosFolderAgent.ps1'), 'utf8');
const folderAgentTests = fs.readFileSync(path.join(root, 'tools/stockland-pos-folder-agent/tests/Test-StocklandPosFolderAgent.ps1'), 'utf8');
const folderInstaller = fs.readFileSync(path.join(root, 'tools/stockland-pos-folder-agent/Install-StocklandPosFolderAgent.ps1'), 'utf8');
const runner = fs.readFileSync(path.join(root, 'scripts/release/e2e/run_all.sh'), 'utf8');
const browserSmoke = fs.readFileSync(path.join(root, 'scripts/release/ui/ui_smoke.js'), 'utf8');

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
assert(page.includes('id="posCodeMapForm"') && page.includes('id="posCodeMapSource"'), 'Owner/Management must have a source-specific POS-to-SKU mapping form.');
assert(page.includes('Central SKU mappings are controlled by Owner / Management'), 'Branch Managers must be told that central SKU mapping is not their authority.');
assert(app.includes("api16('/import/code-maps?source_system='") && app.includes("api16('/import/code-maps',{method:'POST'"), 'Mapping UI must call the real source-filtered list and audited save routes.');
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

assert(runner.includes('s19_pos_integration_hub'), 'The database-backed integration registry test must run in the full synthetic E2E sequence.');
assert(browserSmoke.includes("[data-nav-toggle=\"sales\"]") && browserSmoke.includes("[data-view=\"integrations\"]"), 'Browser acceptance must open POS Integrations from the actual Sales & POS navigation.');
assert(browserSmoke.includes('#posDeviceRegisterForm') && browserSmoke.includes('/pos-integrations/adapters') && browserSmoke.includes('/pos-integrations/devices'), 'Browser acceptance must verify the rendered integration form and both live registry endpoints.');
assert(browserSmoke.includes("POS Integrations ' + viewport.label + ' layout"), 'Browser acceptance must measure POS Integrations at phone, tablet and desktop sizes.');
assert(e2e.includes('Branch Manager cannot register a device for another branch'), 'E2E must test branch boundary.');
assert(e2e.includes("call(\n\t'POST',\n\t'/products'") && e2e.includes('Synthetic POS Mapping Product A') && e2e.includes('Synthetic POS Mapping Product B'), 'E2E must create disposable Product Master variants through the authorized REST service rather than assume pre-seeded SKUs.');
assert(e2e.includes("delete_record( Stockland_DB::table( 'product_code_maps' )") && e2e.includes("delete_record( Stockland_DB::table( 'products' )"), 'E2E must clean up synthetic product and mapping records in its disposable database.');
assert(e2e.includes('device registration rejects inline API credentials'), 'E2E must test credential rejection.');
assert(e2e.includes('no sales, inventory-ledger or GL records'), 'E2E must test that device registration has no financial or stock side effects.');
assert(e2e.includes('/import/code-maps'), 'E2E must exercise the real Management product-code mapping route for registered device sources.');
assert(e2e.includes('same external PLU resolves to device A Product Master SKU only in device A namespace') && e2e.includes('same external PLU resolves to device B Product Master SKU only in device B namespace'), 'E2E must prove overlapping PLU codes resolve independently for two POS devices.');
assert(importer.includes("$mapping_source_system = $registered_device ? (string) $registered_device['source_system'] : 'THIRD_PARTY_POS';"), 'Device imports must use per-device mapping namespaces while generic imports retain legacy mappings.');

assert(folderAgent.includes("PosDeviceId") && folderAgent.includes("formData['pos_device_id']"), 'Windows folder agent must support optional per-device mapping identity.');
assert(folderAgent.includes('$deviceId.Length -ne 16') && folderAgent.includes("match '[^A-F0-9]'"), 'Folder agent must validate the exact device ID length and hexadecimal characters.');
assert(folderAgent.includes('never sends a branch override'), 'Folder agent must retain its no-branch-override boundary.');
assert(folderInstaller.includes('[string]$PosDeviceId =') && folderInstaller.includes('$PosDeviceId.Length -ne 16'), 'Windows installer must validate and persist an optional registered device ID.');
assert(!folderAgent.includes("import/daily-sales/commit"), 'Folder agent must never release or post sales.');
assert(folderAgentTests.includes('Malformed registered POS device IDs must be rejected.') && folderAgentTests.includes('Registered device ID must be included in the Daily POS preview form.'), 'Windows tests must cover invalid, configured and generic device identity modes.');

console.log('RESULT PASS: universal connector catalogue, Branch Manager registry UI, branch/source isolation, no-secret/no-schema boundary, shared Daily POS staging and E2E coverage.');

assert(registry.includes('AUTOMATIC_VALIDATE_AND_POST') && registry.includes('mark_synced'), 'Registered sources must auto-post and become CONNECTED only after a real import.');
