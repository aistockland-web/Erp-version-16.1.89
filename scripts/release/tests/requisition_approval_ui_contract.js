'use strict';

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../../..');
const app = fs.readFileSync(path.join(root, 'src/stockland-erp-app/assets/app.js'), 'utf8');
const requisitions = fs.readFileSync(path.join(root, 'src/stockland-erp-app/includes/rest/class-rest-requisition-v16.php'), 'utf8');
const authority = fs.readFileSync(path.join(root, 'src/stockland-erp-app/includes/class-stockland-authority.php'), 'utf8');
const importer = fs.readFileSync(path.join(root, 'src/stockland-erp-app/includes/rest/class-rest-import-v16.php'), 'utf8');
const runner = fs.readFileSync(path.join(root, 'scripts/release/e2e/run_all.sh'), 'utf8');
const approvalE2E = fs.readFileSync(path.join(root, 'scripts/release/e2e/s21_requisition_approval_e2e.php'), 'utf8');
const posE2E = fs.readFileSync(path.join(root, 'scripts/release/e2e/s22_daily_pos_real_import_e2e.php'), 'utf8');

function assert(ok, message) {
  if (!ok) throw new Error('Stockland approval/POS end-to-end contract failed: ' + message);
}
function section(source, startText, endText) {
  const start = source.indexOf(startText);
  const end = source.indexOf(endText, start + startText.length);
  assert(start >= 0 && end > start, 'Cannot locate ' + startText);
  return source.slice(start, end);
}

const ui = section(app, 'async function openReqDetailV16(id){', '\nfunction users(){');
for (const token of ['id="reviewReqV16"', 'id="reqApproveFull"', 'id="reqApprovePartial"', 'id="reqReject"', 'id="warehouseActionBtn"', 'id="warehouseDirectPickBtn"', 'id="receiveReqV16"']) {
  assert(ui.includes(token), 'Rendered approval-chain UI control is missing: ' + token);
}
for (const token of [
  "document.getElementById('reviewReqV16')",
  "document.getElementById('reqApproveFull')?.addEventListener",
  "document.getElementById('reqApprovePartial')?.addEventListener",
  "document.getElementById('reqReject')?.addEventListener",
  "api16('/requisitions/'+encodeURIComponent(q.requisition_id)+'/decision'",
  "api16('/requisitions/'+encodeURIComponent(q.requisition_id)+'/warehouse-action'",
  "api16('/requisitions/'+encodeURIComponent(q.requisition_id)+'/receive'",
]) {
  assert(ui.includes(token), 'UI control is not bound to its real REST action: ' + token);
}

assert(requisitions.includes("'/requisitions/(?P<id>[A-Za-z0-9\\-]+)/decision'"), 'Approval decision route is not registered.');
assert(requisitions.includes("'/requisitions/(?P<id>[A-Za-z0-9\\-]+)/warehouse-action'"), 'Warehouse action route is not registered.');
assert(requisitions.includes("'/requisitions/(?P<id>[A-Za-z0-9\\-]+)/receive'"), 'Branch receiving route is not registered.');
assert(requisitions.includes("'approval_mode'           => 'ONE_OF'"), 'Requisition approval policy must remain a single Owner-or-Management decision.');
assert(requisitions.includes('Stockland_Authority::can_review_requisition()') && requisitions.includes('Stockland_Authority::can_approve_requisition( $stage )'), 'Server-side review and approval authority must be rechecked.');
assert(requisitions.includes("'SELECT r.*,l.location_name FROM %i r JOIN %i l ON l.id=r.branch_location_id WHERE r.requisition_id=%s FOR UPDATE'"), 'Approval transaction must lock the live requisition row.');
assert(authority.includes('return self::role( $user_id ) === self::BRANCH_MANAGER;'), 'Branch Manager review must remain role-bound.');
assert(authority.includes('return self::role( $user_id ) === self::WAREHOUSE_MANAGER;'), 'Reservation must remain Warehouse Manager-only.');
assert(authority.includes('return self::role( $user_id ) === self::BRANCH_MANAGER && current_user_can( \'stockland_receive_transfer\' );'), 'Branch receipt must remain assigned Branch Manager-only.');

assert(importer.includes("'/import/daily-sales/preview'") && importer.includes("'/import/daily-sales/commit'"), 'Daily POS preview and release routes must exist.');
assert(importer.includes("'callback'            => array( $this, 'daily_preview' )") && importer.includes("'callback'            => array( $this, 'daily_commit' )"), 'Daily POS routes must invoke the production importer callbacks.');
assert(importer.includes('Upload never counts as approval') && importer.includes('Only a Branch Manager may release it.'), 'Daily POS upload must not count as release approval.');
assert(approvalE2E.includes("'/requisitions/' . rawurlencode( $requisition_id ) . '/decision'") && approvalE2E.includes("'/requisitions/' . rawurlencode( $requisition_id ) . '/receive'"), 'Synthetic approval E2E must call the actual REST workflow routes.');
assert(posE2E.includes("'/import/daily-sales/preview'") && posE2E.includes("'/import/daily-sales/commit'"), 'Real multipart POS E2E must call the actual preview and Branch Manager commit routes.');
assert(posE2E.includes('new CURLFile(') && posE2E.includes('wp_create_application_password'), 'POS E2E must use a genuine HTTP multipart upload and short-lived synthetic REST credentials.');
assert(runner.includes('s21_requisition_approval_e2e') && runner.includes('s22_daily_pos_real_import_e2e'), 'Both approval and real POS end-to-end tests must run in the disposable suite.');

console.log('RESULT PASS: approval UI buttons, real REST bindings, ONE_OF authority, warehouse receipt and actual multipart POS release contracts are present.');
