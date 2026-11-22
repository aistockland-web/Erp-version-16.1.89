'use strict';
const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../../..');
const app = fs.readFileSync(path.join(root, 'src/stockland-erp-app/assets/app.js'), 'utf8');
const requisitions = fs.readFileSync(path.join(root, 'src/stockland-erp-app/includes/rest/class-rest-requisition-v16.php'), 'utf8');
const authority = fs.readFileSync(path.join(root, 'src/stockland-erp-app/includes/class-stockland-authority.php'), 'utf8');
const importer = fs.readFileSync(path.join(root, 'src/stockland-erp-app/includes/rest/class-rest-import-v16.php'), 'utf8');
const runner = fs.readFileSync(path.join(root, 'scripts/release/e2e/run_all.sh'), 'utf8');
const approval = fs.readFileSync(path.join(root, 'scripts/release/e2e/s21_requisition_approval_e2e.php'), 'utf8');
const pos = fs.readFileSync(path.join(root, 'scripts/release/e2e/s22_daily_pos_real_import_e2e.php'), 'utf8');
function assert(ok, message) { if (!ok) throw new Error('Stockland approval/POS contract failed: ' + message); }
function section(source, startText, endText) {
  const start = source.indexOf(startText);
  const end = source.indexOf(endText, start + startText.length);
  assert(start >= 0 && end > start, 'Cannot locate ' + startText);
  return source.slice(start, end);
}
const ui = section(app, 'async function openReqDetailV16(id){', '\nfunction users(){');
for (const token of ['id="reviewReqV16"', 'id="reqApproveFull"', 'id="reqApprovePartial"', 'id="reqReject"', 'id="warehouseActionBtn"', 'id="warehouseDirectPickBtn"', 'id="receiveReqV16"', 'FULL APPROVAL BLOCKED.', 'Shortfall']) {
  assert(ui.includes(token), 'Rendered approval-chain UI state/control missing: ' + token);
}
for (const token of ["document.getElementById('reviewReqV16')", "document.getElementById('reqApproveFull')?.addEventListener", "document.getElementById('reqApprovePartial')?.addEventListener", "document.getElementById('reqReject')?.addEventListener", "api16('/requisitions/'+encodeURIComponent(q.requisition_id)+'/decision'", "api16('/requisitions/'+encodeURIComponent(q.requisition_id)+'/warehouse-action'", "api16('/requisitions/'+encodeURIComponent(q.requisition_id)+'/receive'"]) {
  assert(ui.includes(token), 'UI control is not bound to its real REST action: ' + token);
}
assert(requisitions.includes("'/requisitions/(?P<id>[A-Za-z0-9\\-]+)/decision'"), 'Decision route is missing.');
assert(requisitions.includes("'/requisitions/(?P<id>[A-Za-z0-9\\-]+)/warehouse-action'"), 'Warehouse route is missing.');
assert(requisitions.includes("'/requisitions/(?P<id>[A-Za-z0-9\\-]+)/receive'"), 'Receiving route is missing.');
assert(requisitions.includes("'approval_mode'           => 'ONE_OF'"), 'Only one Owner-or-Management final decision is allowed.');
assert(requisitions.includes("$request->set_param( 'id', $id );"), 'Post-action refresh must explicitly set the requisition route ID before calling detail.');
assert((requisitions.split('return $this->refreshed_detail( $id );').length - 1) === 3, 'Review, final decision and branch receipt must all return the refreshed requisition detail.');
assert(requisitions.includes('Stockland_Authority::can_review_requisition()') && requisitions.includes('Stockland_Authority::can_approve_requisition( $stage )'), 'Server-side actor authorization is missing.');
assert(requisitions.includes("'SELECT r.*,l.location_name FROM %i r JOIN %i l ON l.id=r.branch_location_id WHERE r.requisition_id=%s FOR UPDATE'"), 'Approval row lock is missing.');
assert(authority.includes('return self::role( $user_id ) === self::BRANCH_MANAGER;'), 'Branch Manager review role gate is missing.');
assert(authority.includes('return self::role( $user_id ) === self::WAREHOUSE_MANAGER;'), 'Warehouse Manager reservation gate is missing.');
assert(authority.includes("return self::role( $user_id ) === self::BRANCH_MANAGER && current_user_can( 'stockland_receive_transfer' );"), 'Branch receiving role/capability gate is missing.');
assert(importer.includes("'/import/daily-sales/preview'") && importer.includes("'/import/daily-sales/commit'"), 'Daily POS preview/release routes are missing.');
assert(importer.includes('Upload never counts as approval') && importer.includes('Only an assigned Branch Manager may post the branch daily POS import.'), 'Daily POS upload/release authority contract is missing.');
assert(approval.includes('Warehouse Employee picks only after Manager reservation') && approval.includes('Owner is not required for a second approval'), 'Approval E2E coverage is incomplete.');
assert(pos.includes('new CURLFile(') && pos.includes('WP_Application_Passwords::create_new_application_password') && pos.includes('WP_Application_Passwords::delete_application_password') && pos.includes("'/import/daily-sales/preview'"), 'POS E2E does not perform a genuine authenticated multipart upload with disposable credentials.');
assert(runner.includes('s21_requisition_approval_e2e') && runner.includes('s22_daily_pos_real_import_e2e'), 'Both new E2E suites must be in the mandatory runner.');
console.log('RESULT PASS: approval UI, ONE_OF authority, warehouse receiving, real HTTP multipart POS upload and branch-manager release contracts are present.');
