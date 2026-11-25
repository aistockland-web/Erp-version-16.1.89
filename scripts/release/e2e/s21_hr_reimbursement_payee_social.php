<?php
/**
 * HR operations the earlier suites did not exercise: employee expense reimbursement, individual payees and their
 * payable obligations (fail-closed tax review), and manual social-reporting snapshots.
 *
 * SYNTHETIC DATA ONLY, on a DISPOSABLE site. Fixtures are written to a disposable encrypted vault removed at the end.
 * No statutory payroll/tax rule is created, approved or inferred here; the obligation path proves it stays blocked.
 * Runs AFTER s16_payroll_cycle.php (it approves the HR lifecycle schema and creates the fixture users/employees).
 * Run: STOCKLAND_DISPOSABLE_SITE=yes wp eval-file scripts/release/e2e/s21_hr_reimbursement_payee_social.php
 */
if ( 'yes' !== getenv( 'STOCKLAND_DISPOSABLE_SITE' ) ) { fwrite( STDERR, "Refusing to run: set STOCKLAND_DISPOSABLE_SITE=yes\n" ); exit( 2 ); }
$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
if ( ! in_array( DB_HOST, array( '127.0.0.1', 'localhost', '::1', '127.0.0.1:3306', 'localhost:3306' ), true ) || ! in_array( $home_host, array( '127.0.0.1', 'localhost', '::1' ), true ) ) {
	fwrite( STDERR, "Refusing to run: the WordPress home and database host must be loopback/disposable.\n" ); exit( 2 );
}
require_once __DIR__ . '/lib.php';
$vault_dir = rtrim( sys_get_temp_dir(), '/' ) . '/stockland-s21-vault-' . bin2hex( random_bytes( 6 ) );
if ( ! defined( 'STOCKLAND_PRIVATE_VAULT_KEY' ) ) { define( 'STOCKLAND_PRIVATE_VAULT_KEY', bin2hex( random_bytes( 32 ) ) ); }
if ( ! defined( 'STOCKLAND_HR_PII_ENCRYPTION_KEY' ) ) { define( 'STOCKLAND_HR_PII_ENCRYPTION_KEY', bin2hex( random_bytes( 32 ) ) ); }
if ( ! defined( 'STOCKLAND_PRIVATE_VAULT_DIR' ) ) { define( 'STOCKLAND_PRIVATE_VAULT_DIR', $vault_dir ); }
$fail = 0; $pass = 0;
$ok = function ( $label, $cond, $detail = '' ) use ( &$fail, &$pass ) { if ( $cond ) { ++$pass; echo "PASS $label\n"; } else { ++$fail; echo "FAIL $label" . ( '' !== $detail ? " :: $detail" : '' ) . "\n"; } return (bool) $cond; };
$finish = function () use ( &$fail, &$pass, &$vault_dir ) {
	if ( is_dir( $vault_dir ) && false !== strpos( $vault_dir, '/stockland-s21-vault-' ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $vault_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) { $f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() ); }
		@rmdir( $vault_dir );
	}
	echo ( $fail ? "RESULT FAIL ($fail failed, $pass passed)\n" : "RESULT PASS ($pass assertions)\n" ); exit( $fail ? 1 : 0 );
};
$uid = function ( $login ) { $u = get_user_by( 'login', $login ); return $u ? (int) $u->ID : 0; };
$mg = $uid( 'pay-mgmt' ); $o2 = $uid( 'pay-owner2' ); $o1 = 1;
if ( ! $mg || ! $o2 || ! qv( "SHOW TABLES LIKE '" . Stockland_DB::table( 'hr_individual_payees' ) . "'" ) ) { fwrite( STDERR, "Run s16_payroll_cycle.php first.\n" ); exit( 2 ); }
$emp = (int) qv( 'SELECT id FROM ' . Stockland_DB::table( 'employees' ) . " WHERE UPPER(status)='ACTIVE' ORDER BY id ASC LIMIT 1" );
$ok( 'an active synthetic employee exists from the payroll-cycle fixtures', $emp > 0 );
if ( ! $emp ) { $finish(); }
$code = function ( $r ) { return is_array( $r[1] ) ? (string) ( $r[1]['code'] ?? '' ) : ''; };

$register = function ( $rcode, $title, $type, $bytes ) use ( $ok ) {
	$stored = Stockland_HR_Private_Vault::store_content( $bytes, $rcode . '.pdf' );
	if ( ! $ok( "fixture $rcode stored encrypted", ! is_wp_error( $stored ), is_wp_error( $stored ) ? $stored->get_error_message() : '' ) ) { return 0; }
	$tmpl = qv( 'SELECT id FROM ' . Stockland_DB::table( 'hr_resources' ) . ' ORDER BY id DESC LIMIT 1' );
	$row  = q( 'SELECT * FROM ' . Stockland_DB::table( 'hr_resources' ) . ' WHERE id=' . (int) $tmpl )[0] ?? array();
	unset( $row['id'], $row['created_at'], $row['updated_at'] );
	$row = array_merge( $row, array( 'resource_code' => $rcode, 'title' => $title, 'description' => 'SYNTHETIC fixture', 'resource_type' => $type, 'status' => 'DRAFT', 'approved_by' => null, 'approved_at' => null, 'created_by' => 0, 'supersedes_resource_id' => null, 'version_label' => '1.0' ) );
	foreach ( (array) $stored as $k => $v ) { if ( array_key_exists( $k, $row ) ) { $row[ $k ] = $v; } }
	Stockland_DB::insert_record( Stockland_DB::table( 'hr_resources' ), $row );
	global $wpdb; return (int) $wpdb->insert_id;
};

/* ---------------- 1. Employee reimbursement ---------------- */
$pdf = "%PDF-1.4\n% SYNTHETIC RECEIPT\n";
$rid = $register( 'S21-RCPT-' . bin2hex( random_bytes( 3 ) ), 'S21 synthetic receipt', 'EXPENSE_RECEIPT', $pdf );
$ok( 'receipt fixture registered', $rid > 0 );
if ( ! $rid ) { $finish(); }
$base = array( 'employee_id' => $emp, 'receipt_resource_id' => $rid, 'amount' => '350.00', 'incurred_date' => gmdate( 'Y-m-d' ), 'expense_category' => 'TRANSPORT', 'business_purpose' => 'Taxi to supplier meeting (synthetic)', 'description' => 'Taxi fare' );
$r = call( 'POST', '/hr/employee-reimbursements', $base, $mg );
$ok( 'a claim is refused while the receipt is not yet approved', 409 === $r[0] || 400 === $r[0] || 403 === $r[0], 'HTTP ' . $r[0] );
$r = call( 'POST', "/hr/resources/$rid/approve", array( 'approval_note' => 'Synthetic receipt verified for the test.' ), $o2 );
$ok( 'independent Owner approves the receipt resource', 200 === $r[0], 'HTTP ' . $r[0] . ' ' . substr( wp_json_encode( $r[1] ), 0, 200 ) );
$bad = $base; $bad['amount'] = '0.00';
$r = call( 'POST', '/hr/employee-reimbursements', $bad, $mg ); $ok( 'zero amount is refused', 400 === $r[0], 'HTTP ' . $r[0] );
$bad = $base; $bad['expense_category'] = 'LUXURY_YACHT';
$r = call( 'POST', '/hr/employee-reimbursements', $bad, $mg ); $ok( 'undocumented expense category is refused', 400 === $r[0], 'HTTP ' . $r[0] );
$bad = $base; $bad['business_purpose'] = 'x';
$r = call( 'POST', '/hr/employee-reimbursements', $bad, $mg ); $ok( 'a one-letter business purpose is refused', 400 === $r[0], 'HTTP ' . $r[0] );
$r = call( 'POST', '/hr/employee-reimbursements', $base, $mg );
$ok( 'Management submits a receipt-backed claim', 200 === $r[0] && 'SUBMITTED' === ( $r[1]['status'] ?? '' ), 'HTTP ' . $r[0] . ' ' . substr( wp_json_encode( $r[1] ), 0, 220 ) );
$cid = (int) ( $r[1]['id'] ?? 0 );
if ( ! $cid ) { $finish(); }
$r = call( 'POST', "/hr/employee-reimbursements/$cid/approve", array( 'reviewer_note' => 'short', 'expense_account_code' => '6000', 'payable_account_code' => '2000' ), $o2 );
$ok( 'approval without a substantive review note is refused', in_array( $r[0], array( 400, 409 ), true ), 'HTTP ' . $r[0] );
$r = call( 'POST', "/hr/employee-reimbursements/$cid/approve", array( 'reviewer_note' => 'Receipt matches claim; business purpose accepted.', 'expense_account_code' => '6000', 'payable_account_code' => '2000' ), $mg );
$ok( 'Management cannot approve (Owner payroll authority only)', in_array( $r[0], array( 401, 403 ), true ), 'HTTP ' . $r[0] );
$r = call( 'POST', "/hr/employee-reimbursements/$cid/pay", array( 'payment_method' => 'CASH', 'payment_reference' => 'CASH-S21-1' ), $o2 );
$ok( 'payment before approval is refused', 409 === $r[0], 'HTTP ' . $r[0] );
$jbefore = (int) qv( 'SELECT COUNT(*) FROM ' . Stockland_DB::table( 'gl_journals' ) );
$r = call( 'POST', "/hr/employee-reimbursements/$cid/approve", array( 'reviewer_note' => 'Receipt matches claim; business purpose accepted.', 'expense_account_code' => '6000', 'payable_account_code' => '2000' ), $o2 );
$ok( 'Owner approves: expense/payable journal posted', 200 === $r[0] && (int) ( $r[1]['accrual_journal_id'] ?? 0 ) > 0, 'HTTP ' . $r[0] . ' ' . substr( wp_json_encode( $r[1] ), 0, 220 ) );
$ok( 'approval adds exactly one journal entry', (int) qv( 'SELECT COUNT(*) FROM ' . Stockland_DB::table( 'gl_journals' ) ) === $jbefore + 1 );
$r = call( 'POST', "/hr/employee-reimbursements/$cid/approve", array( 'reviewer_note' => 'Receipt matches claim; business purpose accepted.', 'expense_account_code' => '6000', 'payable_account_code' => '2000' ), $o2 );
$ok( 'a second approval of the same claim is refused (no double posting)', 409 === $r[0], 'HTTP ' . $r[0] );
$r = call( 'POST', "/hr/employee-reimbursements/$cid/pay", array( 'payment_method' => 'BANK', 'payment_reference' => 'BANK-S21-1' ), $o2 );
$ok( 'bank settlement without a statement reference is refused', 400 === $r[0], 'HTTP ' . $r[0] );
$r = call( 'POST', "/hr/employee-reimbursements/$cid/pay", array( 'payment_method' => 'CASH', 'payment_reference' => 'CASH-S21-1' ), $o2 );
$ok( 'cash settlement with a reference marks the claim PAID', 200 === $r[0] && 'PAID' === ( $r[1]['status'] ?? '' ), 'HTTP ' . $r[0] . ' ' . substr( wp_json_encode( $r[1] ), 0, 200 ) );
$r = call( 'POST', "/hr/employee-reimbursements/$cid/pay", array( 'payment_method' => 'CASH', 'payment_reference' => 'CASH-S21-1' ), $o2 );
$ok( 'a second payment of the same claim is refused (no double settlement)', 409 === $r[0], 'HTTP ' . $r[0] );
$unbal = (int) qv( 'SELECT COUNT(*) FROM (SELECT journal_id FROM ' . Stockland_DB::table( 'gl_lines' ) . ' GROUP BY journal_id HAVING ROUND(SUM(debit)-SUM(credit),2)<>0) t' );
$ok( 'every journal in the ledger is balanced after the reimbursement cycle', 0 === $unbal, "$unbal unbalanced" );

/* ---------------- 2. Individual payees and obligations ---------------- */
$r = call( 'POST', '/hr/individual-payees', array( 'full_name' => 'Synthetic Creator', 'payee_type' => 'MARTIAN' ), $mg );
$ok( 'an unknown payee relationship type is refused', 400 === $r[0], 'HTTP ' . $r[0] );
$r = call( 'POST', '/hr/individual-payees', array( 'full_name' => 'Synthetic Creator', 'payee_type' => 'DIGITAL_CREATOR', 'tin' => '0012345678', 'residency_status' => 'RESIDENT' ), $mg );
$ok( 'Management drafts an individual payee (TIN stored encrypted)', 200 === $r[0] && 'DRAFT' === ( $r[1]['status'] ?? '' ), 'HTTP ' . $r[0] . ' ' . substr( wp_json_encode( $r[1] ), 0, 200 ) );
$pid = (int) ( $r[1]['id'] ?? 0 );
if ( ! $pid ) { $finish(); }
$ok( 'the raw TIN is not stored in clear text', 0 === (int) qv( 'SELECT COUNT(*) FROM ' . Stockland_DB::table( 'hr_individual_payees' ) . " WHERE id=$pid AND tin_ciphertext LIKE '%0012345678%'" ) );
$r = call( 'POST', "/hr/individual-payees/$pid/obligations", array( 'obligation_type' => 'DIGITAL_CONTENT', 'deliverables' => array( 'Two videos' ), 'cash_amount' => '5000.00' ), $mg );
$ok( 'an obligation cannot be created for an unapproved payee', 409 === $r[0], 'HTTP ' . $r[0] );
$r = call( 'POST', "/hr/individual-payees/$pid/approve", array( 'approval_note' => 'Identity checked against synthetic ID.' ), $mg );
$ok( 'the payee maker cannot approve their own payee', in_array( $r[0], array( 401, 403, 409 ), true ), 'HTTP ' . $r[0] );
$r = call( 'POST', "/hr/individual-payees/$pid/approve", array( 'approval_note' => 'Identity checked against synthetic ID.' ), $o2 );
$ok( 'an independent Owner approves the payee identity', 200 === $r[0], 'HTTP ' . $r[0] . ' ' . substr( wp_json_encode( $r[1] ), 0, 200 ) );
$r = call( 'POST', "/hr/individual-payees/$pid/obligations", array( 'obligation_type' => 'DIGITAL_CONTENT', 'deliverables' => array(), 'cash_amount' => '5000.00' ), $mg );
$ok( 'an obligation without deliverables is refused', 400 === $r[0], 'HTTP ' . $r[0] );
$r = call( 'POST', "/hr/individual-payees/$pid/obligations", array( 'obligation_type' => 'DIGITAL_CONTENT', 'deliverables' => array( 'Two videos' ) ), $mg );
$ok( 'an obligation with no cash or in-kind value is refused', in_array( $r[0], array( 400, 409 ), true ), 'HTTP ' . $r[0] );
$r = call( 'POST', "/hr/individual-payees/$pid/obligations", array( 'obligation_type' => 'DIGITAL_CONTENT', 'deliverables' => array( 'Two videos' ), 'cash_amount' => '5000.00' ), $mg );
$ok( 'a valid obligation is created in LEGAL_REVIEW (no tax rule inferred)', 200 === $r[0] && 'LEGAL_REVIEW' === ( $r[1]['tax_decision_status'] ?? '' ), 'HTTP ' . $r[0] . ' ' . substr( wp_json_encode( $r[1] ), 0, 220 ) );
$oid = (int) ( $r[1]['id'] ?? 0 );
if ( ! $oid ) { $finish(); }
$r = call( 'POST', "/hr/individual-payee-obligations/$oid/pay", array( 'payment_method' => 'CASH', 'payment_reference' => 'S21-PAY' ), $o2 );
$ok( 'payment of an unapproved obligation is refused', in_array( $r[0], array( 400, 409 ), true ), 'HTTP ' . $r[0] );
$r = call( 'POST', "/hr/individual-payee-obligations/$oid/decision", array( 'decision' => 'APPROVE', 'review_note' => 'Attempting approval with no qualified tax rule on file.', 'expense_account_code' => '6000', 'payable_account_code' => '2000' ), $o2 );
$ok( 'approval is refused without an approved qualified tax rule (fail closed)', in_array( $r[0], array( 400, 409 ), true ), 'HTTP ' . $r[0] . ' ' . substr( wp_json_encode( $r[1] ), 0, 200 ) );
$ok( 'the obligation is still DRAFT after the refused approval', 'DRAFT' === qv( 'SELECT status FROM ' . Stockland_DB::table( 'hr_individual_payee_obligations' ) . " WHERE id=$oid" ) );
$r = call( 'POST', "/hr/individual-payee-obligations/$oid/decision", array( 'decision' => 'REJECT', 'review_note' => 'Rejected in the synthetic test: no qualified review available.' ), $o2 );
$ok( 'an Owner can reject the obligation with a substantive note', 200 === $r[0], 'HTTP ' . $r[0] . ' ' . substr( wp_json_encode( $r[1] ), 0, 200 ) );
$ok( 'the obligation is REJECTED', 'REJECTED' === qv( 'SELECT status FROM ' . Stockland_DB::table( 'hr_individual_payee_obligations' ) . " WHERE id=$oid" ) );

/* ---------------- 3. Manual social-reporting snapshots ---------------- */
$r = call( 'POST', '/hr/management-social', array( 'title' => 'S21 campaign', 'platform' => 'MYSPACE', 'account_label' => 'Official' ), $mg );
$ok( 'an unknown social platform is refused', 400 === $r[0], 'HTTP ' . $r[0] );
$r = call( 'POST', '/hr/management-social', array( 'title' => 'S21 campaign', 'platform' => 'INSTAGRAM', 'account_label' => 'Official', 'start_date' => gmdate( 'Y-m-d' ), 'budget' => '1000.00' ), $mg );
$ok( 'Management drafts a social campaign', 200 === $r[0] && 'DRAFT' === ( $r[1]['status'] ?? '' ), 'HTTP ' . $r[0] . ' ' . substr( wp_json_encode( $r[1] ), 0, 200 ) );
$cmp = (int) ( $r[1]['id'] ?? 0 );
if ( $cmp ) {
	$snap = array( 'platform' => 'INSTAGRAM', 'account_label' => 'Official', 'period_start' => gmdate( 'Y-m-d', time() - 86400 * 7 ), 'period_end' => gmdate( 'Y-m-d' ), 'report_as_of' => gmdate( 'Y-m-d H:i' ), 'source_reference' => 'manual-export-s21.csv', 'source_sha256' => hash( 'sha256', 'synthetic export' ), 'reach_count' => 1200, 'view_count' => 3400 );
	$b = $snap; $b['source_sha256'] = 'nothex';
	$r = call( 'POST', "/hr/management-social/$cmp/snapshots", $b, $mg ); $ok( 'a snapshot without a valid source SHA-256 is refused', in_array( $r[0], array( 400, 409 ), true ), 'HTTP ' . $r[0] );
	$b = $snap; $b['reach_count'] = -5;
	$r = call( 'POST', "/hr/management-social/$cmp/snapshots", $b, $mg ); $ok( 'a negative metric is refused', in_array( $r[0], array( 400, 409 ), true ), 'HTTP ' . $r[0] );
	$b = $snap; $b['platform'] = 'TIKTOK';
	$r = call( 'POST', "/hr/management-social/$cmp/snapshots", $b, $mg ); $ok( 'a snapshot for a different platform than the campaign is refused', in_array( $r[0], array( 400, 409 ), true ), 'HTTP ' . $r[0] );
	$r = call( 'POST', "/hr/management-social/$cmp/snapshots", $snap, $mg );
	$ok( 'a valid manual snapshot is recorded as NOT real-time', 200 === $r[0] && 'MANUAL_EXPORT_NOT_REALTIME' === ( $r[1]['reporting_mode'] ?? '' ), 'HTTP ' . $r[0] . ' ' . substr( wp_json_encode( $r[1] ), 0, 220 ) );
}
$r = call( 'GET', '/hr/management-social', array(), $mg );
$ok( 'the social list states manual/export reporting mode', 200 === $r[0] && 'MANUAL_OR_EXPORT_SNAPSHOT' === ( $r[1]['reporting_mode'] ?? '' ) );
$clerk = get_user_by( 'login', 's21-clerk' ); $clerk = $clerk ? (int) $clerk->ID : (int) mkuser( 's21-clerk', 'subscriber', 'cashier' );
$r = call( 'GET', '/hr/employee-reimbursements', array(), $clerk ); $ok( 'a cashier cannot list reimbursements', in_array( $r[0], array( 401, 403 ), true ), 'HTTP ' . $r[0] );
$r = call( 'GET', '/hr/individual-payees', array(), $clerk ); $ok( 'a cashier cannot list individual payees', in_array( $r[0], array( 401, 403 ), true ), 'HTTP ' . $r[0] );
$finish();
