<?php
/**
 * CAP-04 — full HR payroll cycle, end to end, through the real REST routes, on a DISPOSABLE site.
 *
 * SYNTHETIC DATA ONLY. The payroll, allowance and final-settlement rule sets below are labelled SYNTHETIC-*,
 * cite https://example.invalid sources and fake SHA-256 values. They are NOT Ethiopian law and exist only to prove
 * the mechanism (maker-checker, fail-closed rules, frozen calculations, GL posting, payout and remittance
 * reconciliation). They are written only into this disposable database; this script refuses to run elsewhere.
 * A real payroll run still requires an Owner-approved, sourced and independently reviewed statutory rule set.
 *
 * Run: STOCKLAND_DISPOSABLE_SITE=yes wp eval-file scripts/release/e2e/s14_payroll_cycle.php
 * This script approves the HR lifecycle schema on the disposable site, so it runs LAST in run_all.sh.
 */
if ( 'yes' !== getenv( 'STOCKLAND_DISPOSABLE_SITE' ) ) { fwrite( STDERR, "Refusing to run: set STOCKLAND_DISPOSABLE_SITE=yes\n" ); exit( 2 ); }
$loopback = array( '127.0.0.1', 'localhost', '::1', '127.0.0.1:3306', 'localhost:3306' );
$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
if ( ! in_array( DB_HOST, $loopback, true ) || ! in_array( $home_host, array( '127.0.0.1', 'localhost', '::1' ), true ) || ( getenv( 'STOCKLAND_DISPOSABLE_DB_NAME' ) && getenv( 'STOCKLAND_DISPOSABLE_DB_NAME' ) !== DB_NAME ) ) {
	fwrite( STDERR, "Refusing to run: the WordPress home and database host must be loopback/disposable.\n" ); exit( 2 );
}
require_once __DIR__ . '/lib.php';

// Disposable private HR vault: a random per-run key and a 0700 directory outside the web root (encrypted receipt storage).
$vault_dir = rtrim( sys_get_temp_dir(), '/' ) . '/stockland-s16-vault-' . bin2hex( random_bytes( 6 ) );
if ( ! defined( 'STOCKLAND_PRIVATE_VAULT_KEY' ) ) { define( 'STOCKLAND_PRIVATE_VAULT_KEY', bin2hex( random_bytes( 32 ) ) ); }
if ( ! defined( 'STOCKLAND_PRIVATE_VAULT_DIR' ) ) { define( 'STOCKLAND_PRIVATE_VAULT_DIR', $vault_dir ); }

$fail = 0; $pass = 0;
$ok = function ( $label, $cond, $detail = '' ) use ( &$fail, &$pass ) {
	if ( $cond ) { ++$pass; echo "PASS $label\n"; } else { ++$fail; echo "FAIL $label" . ( '' !== $detail ? " :: $detail" : '' ) . "\n"; }
	return (bool) $cond;
};
$finish = function () use ( &$fail, &$pass, &$vault_dir ) {
	if ( is_string( $vault_dir ) && false !== strpos( $vault_dir, '/stockland-s16-vault-' ) && is_dir( $vault_dir ) ) { // remove the disposable encrypted fixtures
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $vault_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) { $f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() ); }
		@rmdir( $vault_dir );
	}
	echo ( $fail ? "RESULT FAIL ($fail failed, $pass passed)\n" : "RESULT PASS ($pass assertions)\n" ); exit( $fail ? 1 : 0 );
};
$err = function ( $res ) { $d = $res[1]; return is_array( $d ) ? (string) ( $d['code'] ?? '' ) . ' ' . (string) ( $d['message'] ?? '' ) : ''; };
$need = function ( $label, $res ) use ( $ok, $finish ) { // success required; anything later depends on it.
	$good = $res[0] >= 200 && $res[0] < 300;
	$ok( $label, $good, 'HTTP ' . $res[0] . ' ' . substr( wp_json_encode( $res[1] ), 0, 400 ) );
	if ( ! $good ) { $finish(); }
	return $res[1];
};
$refused = function ( $label, $res, $status, $needle = '' ) use ( $ok, $err ) {
	$text = $err( $res ) . ' ' . ( is_array( $res[1] ) ? wp_json_encode( $res[1] ) : '' );
	return $ok( $label, (int) $res[0] === (int) $status && ( '' === $needle || false !== stripos( $text, $needle ) ), 'HTTP ' . $res[0] . ' ' . substr( $text, 0, 260 ) );
};

/* ---- independent decimal/minor-unit oracle (no plugin money helpers, no floats) ---- */
$minor = function ( $v ) { $v = trim( (string) $v ); if ( ! preg_match( '/^(\d+)(?:\.(\d{1,2}))?$/D', $v, $m ) ) { throw new RuntimeException( "bad amount $v" ); } return (int) $m[1] * 100 + (int) str_pad( $m[2] ?? '', 2, '0' ); };
$money = function ( $m ) { return intdiv( $m, 100 ) . '.' . str_pad( (string) ( $m % 100 ), 2, '0', STR_PAD_LEFT ); };
$micro = function ( $rate ) { preg_match( '/^(0|1)(?:\.(\d{1,6}))?$/D', (string) $rate, $m ); return (int) $m[1] * 1000000 + (int) str_pad( $m[2] ?? '', 6, '0' ); };
$rhu = function ( $minor_amount, $rate_micro ) { return intdiv( $minor_amount * $rate_micro + 500000, 1000000 ); };
$RATES = array( 'employee' => '0.05', 'employer' => '0.08' );
$BANDS = array( array( 'up_to' => '1000.00', 'rate' => '0.00' ), array( 'up_to' => '5000.00', 'rate' => '0.10' ), array( 'up_to' => null, 'rate' => '0.20' ) ); // progressive slices (calculator contract)
$progressive = function ( $taxable ) use ( $BANDS, $minor, $micro, $rhu ) {
	$tax = 0; $prev = 0;
	foreach ( $BANDS as $b ) {
		$cap = null === $b['up_to'] ? null : $minor( $b['up_to'] );
		$end = null === $cap ? $taxable : min( $taxable, $cap );
		$tax += $rhu( max( 0, $end - $prev ), $micro( $b['rate'] ) );
		if ( null !== $cap ) { $prev = $cap; if ( $taxable <= $cap ) { break; } }
	}
	return $tax;
};
$oracle = function ( $base, $taxable_allowance, $deduction ) use ( $minor, $micro, $rhu, $RATES, $progressive ) {
	$base = $minor( $base ); $ta = $minor( $taxable_allowance ); $ded = $minor( $deduction );
	$gross = $base + $ta; $ep = $rhu( $base, $micro( $RATES['employee'] ) ); $er = $rhu( $base, $micro( $RATES['employer'] ) );
	$tax = $progressive( max( 0, $gross - $ep ) );
	return array( 'gross' => $gross, 'ep' => $ep, 'er' => $er, 'tax' => $tax, 'ded' => $ded, 'net' => $gross - $ep - $tax - $ded );
};

/* ---- actors ---- */
$o1 = 1; // bootstrap Owner (Owner approver / selector)
$mg = mkuser( 'pay-mgmt', 'subscriber', 'management' );
$o2 = mkuser( 'pay-owner2', 'subscriber', 'owner' );
( new WP_User( $mg ) )->set_role( Stockland_Roles::ROLE_MANAGEMENT );
( new WP_User( $o2 ) )->set_role( Stockland_Roles::ROLE_OWNER );
Stockland_Roles::sync_roles();
$ok( 'actors: two Owners and one Management user with distinct identities', Stockland_Authority::is_owner( $o1 ) && Stockland_Authority::is_owner( $o2 ) && Stockland_Authority::role( $mg ) === 'management' && count( array_unique( array( $o1, $o2, $mg ) ) ) === 3 );

/* ---- 1. HR lifecycle schema through the real Owner approval path ---- */
if ( ! Stockland_HR::is_schema_ready() ) {
	$r = call( 'POST', '/setup/advance', array( 'approve_hr_schema' => false ), $o1 );
	$refused( 'HR schema creation is refused without Owner approval', $r, 409, 'approval' );
	$r = call( 'POST', '/setup/advance', array( 'approve_hr_schema' => true, 'approval_reason' => 'x' ), $o1 );
	$refused( 'HR schema approval needs a written reason', $r, 409, 'approval' );
	$r = call( 'POST', '/setup/advance', array( 'approve_hr_schema' => true, 'approval_reason' => 'SYNTHETIC disposable-site Owner approval for payroll cycle E2E' ), $mg );
	$ok( 'HR schema approval by a non-Owner is refused', $r[0] >= 400 && ! get_option( 'stockland_hr_schema_migration_approval' ), 'HTTP ' . $r[0] );
	for ( $i = 0; $i < 80 && ! Stockland_HR::is_schema_ready(); $i++ ) {
		$r = call( 'POST', '/setup/advance', array( 'approve_hr_schema' => true, 'approval_reason' => 'SYNTHETIC disposable-site Owner approval for payroll cycle E2E' ), $o1 );
		if ( $r[0] >= 400 ) { break; }
	}
}
$ok( 'HR lifecycle schema is ready after the Owner approval (37 tables)', Stockland_HR::is_schema_ready() && ! Stockland_HR::missing_tables() );
$GLOBALS['wp_rest_server'] = null; // rebuild the REST server so the now-eligible HR routes register.
$ok( 'HR payroll routes registered', (bool) rest_get_server()->get_routes()['/stockland/v16/hr/payroll-runs'] );

/* ---- 2. fail-closed before any approved rule set ---- */
// The product ships an Owner-approved rule set (s20). This script proves the fail-closed mechanism and uses SYNTHETIC rules, so remove the shipped row on this disposable site first.
$GLOBALS['wpdb']->query( 'DELETE FROM ' . Stockland_DB::table( 'hr_rule_sets' ) . " WHERE rule_code='et-payroll'" );
$run1 = $need( 'run P1 created (Management)', call( 'POST', '/hr/payroll-runs', array( 'idempotency_key' => 'syn-cycle-p1-2026-09-001', 'period_key' => '2026-09', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30' ), $mg ) );
$run1_id = (int) $run1['id'];
$r = call( 'POST', "/hr/payroll-runs/$run1_id/generate", array(), $mg );
$refused( 'generate is refused while no approved rule set exists (fail closed)', $r, 409, 'payroll_rules_unapproved' );
$r = call( 'POST', '/hr/payroll-runs', array( 'idempotency_key' => 'syn-cycle-p1-2026-09-001', 'period_key' => '2026-09', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30' ), $mg );
$ok( 'create-run replay with the same idempotency key returns the same run', $r[0] === 200 && (int) ( $r[1]['id'] ?? 0 ) === $run1_id && ! empty( $r[1]['idempotent_replay'] ) );
$r = call( 'POST', '/hr/payroll-runs', array( 'idempotency_key' => 'syn-cycle-p1-2026-09-002', 'period_key' => '2026-09', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30' ), $mg );
$refused( 'a second normal run for the same period is refused', $r, 409, 'period_run_exists' );

/* ---- 3. SYNTHETIC rule sets: create, independent review, maker-checker ---- */
$review = array( 'basis' => 'INDEPENDENT_PROFESSIONAL_OPINION', 'decision' => 'APPROVED', 'independence_confirmed' => true, 'reviewer_name' => 'Synthetic Reviewer', 'reviewer_id' => 'SYN-REVIEWER-1', 'professional_basis' => 'synthetic test vector', 'review_date' => '2026-01-01', 'opinion_reference' => 'SYNTH-0001', 'opinion_sha256' => str_repeat( 'b', 64 ), 'clauses_reviewed' => array( 'synthetic' ), 'approved_parameters' => array( 'paye_bands', 'paye_tax_base_method', 'monthly_tax_bands', 'tax_base_method', 'pensionable_base_method', 'employee_pension_rate', 'employer_pension_rate', 'component_treatments', 'rounding_method' ) );
$payroll_rules = array(
	'jurisdiction' => 'SYNTHETIC-TEST-ONLY', 'tax_base_method' => 'TAXABLE_GROSS_LESS_EMPLOYEE_PENSION', 'paye_tax_base_method' => 'TAXABLE_EARNINGS_LESS_EMPLOYEE_PENSION',
	'employee_pension_rate' => $RATES['employee'], 'employer_pension_rate' => $RATES['employer'], 'pensionable_base_method' => 'SUM_PENSIONABLE_ITEMS', 'rounding_method' => 'HALF_UP_MINOR_UNIT',
	'monthly_tax_bands' => array( array( 'up_to' => '1000.00', 'rate' => '0.00', 'credit' => '0.00' ), array( 'up_to' => '5000.00', 'rate' => '0.10', 'credit' => '100.00' ), array( 'up_to' => null, 'rate' => '0.20', 'credit' => '600.00' ) ),
	'paye_bands' => $BANDS,
	'component_treatments' => array(
		'BASE' => array( 'kind' => 'EARNING', 'taxable' => true, 'pensionable' => true ), 'TAXABLE_ALLOWANCE' => array( 'kind' => 'EARNING', 'taxable' => true, 'pensionable' => false ),
		'EXEMPT_CANDIDATE' => array( 'kind' => 'EARNING', 'taxable' => false, 'pensionable' => false ), 'OTHER_DEDUCTION' => array( 'kind' => 'DEDUCTION', 'taxable' => false, 'pensionable' => false ),
	),
	'qualified_legal_review' => $review,
);
$rule_body = function ( $type, $version, $rules ) {
	return array( 'rule_type' => $type, 'rule_code' => strtolower( $type ) . '-synthetic', 'version_label' => $version, 'effective_from' => '2026-01-01', 'source_title' => 'SYNTHETIC test vector — not law', 'source_url' => 'https://example.invalid/synthetic-payroll-rules', 'source_clause' => 'synthetic clause 1', 'source_sha256' => str_repeat( 'a', 64 ), 'rules_json' => $rules );
};
$note30 = 'Independent synthetic reviewer confirms the test vectors for this disposable site only.';
$r = call( 'POST', '/hr/lifecycle/rules', $rule_body( 'PAYROLL', 'SYNTHETIC-NOREVIEW', array_diff_key( $payroll_rules, array( 'qualified_legal_review' => 1 ) ) ), $mg );
$norev = $need( 'a payroll rule draft without legal-review evidence can be drafted', $r );
$r = call( 'POST', "/hr/lifecycle/rules/{$norev['id']}/approve", array( 'independent_review_note' => $note30 ), $o1 );
$refused( 'a rule without independent legal-review evidence cannot be approved', $r, 409, 'review' );
$self = $need( 'Owner drafts a rule (to test self-approval)', call( 'POST', '/hr/lifecycle/rules', $rule_body( 'PAYROLL', 'SYNTHETIC-SELF', $payroll_rules ), $o1 ) );
$refused( 'maker-checker: an Owner cannot approve the rule they created', call( 'POST', "/hr/lifecycle/rules/{$self['id']}/approve", array( 'independent_review_note' => $note30 ), $o1 ), 409, 'Maker-checker' );
$rule = $need( 'SYNTHETIC payroll rule drafted by Management', call( 'POST', '/hr/lifecycle/rules', $rule_body( 'PAYROLL', 'SYNTHETIC-PAYROLL-V1', $payroll_rules ), $mg ) );
$refused( 'Management cannot approve a rule (Owner only)', call( 'POST', "/hr/lifecycle/rules/{$rule['id']}/approve", array( 'independent_review_note' => $note30 ), $mg ), 403 );
$need( 'SYNTHETIC payroll rule approved by an independent Owner', call( 'POST', "/hr/lifecycle/rules/{$rule['id']}/approve", array( 'independent_review_note' => $note30 ), $o1 ) );
$settle_rules = array( 'component_rules' => array( 'UNUSED_LEAVE' => array( 'tax_treatment' => 'TAXABLE', 'pensionable_treatment' => 'NOT_PENSIONABLE' ) ), 'qualified_legal_review' => $review );
$srule = $need( 'SYNTHETIC final-settlement rule drafted', call( 'POST', '/hr/lifecycle/rules', $rule_body( 'FINAL_SETTLEMENT', 'SYNTHETIC-SETTLE-V1', $settle_rules ), $mg ) );
$need( 'SYNTHETIC final-settlement rule approved', call( 'POST', "/hr/lifecycle/rules/{$srule['id']}/approve", array( 'independent_review_note' => $note30 ), $o1 ) );
$ok( 'only the reviewed rule versions are approved', (int) qv( 'select count(*) from ' . Stockland_DB::table( 'hr_rule_sets' ) . " where review_status='APPROVED'" ) === 2 );

/* ---- 4. hires: plan-free requisition -> candidate -> interview -> selection -> contract -> onboarding -> activation ---- */
$pos = $need( 'position drafted', call( 'POST', '/hr/lifecycle/positions', array( 'position_code' => 'SYN-POS-1', 'title' => 'Synthetic Clerk', 'department' => 'Operations' ), $mg ) );
$need( 'position submitted', call( 'POST', "/hr/lifecycle/positions/{$pos['id']}/decision", array( 'decision' => 'SUBMIT' ), $mg ) );
$need( 'position approved by Owner', call( 'POST', "/hr/lifecycle/positions/{$pos['id']}/decision", array( 'decision' => 'APPROVE' ), $o1 ) );
$req = $need( 'requisition drafted', call( 'POST', '/hr/lifecycle/requisitions', array( 'requisition_code' => 'SYN-REQ-1', 'position_id' => $pos['id'], 'openings' => 3, 'employment_type' => 'FULL_TIME', 'target_start_date' => '2026-08-01', 'justification' => 'Synthetic cycle hires' ), $mg ) );
$need( 'requisition submitted', call( 'POST', "/hr/lifecycle/requisitions/{$req['id']}/decision", array( 'decision' => 'SUBMIT' ), $mg ) );
$refused( 'Management cannot approve a requisition (Owner commitment)', call( 'POST', "/hr/lifecycle/requisitions/{$req['id']}/decision", array( 'decision' => 'APPROVE' ), $mg ), 403, 'Owner' );
$need( 'requisition approved by Owner', call( 'POST', "/hr/lifecycle/requisitions/{$req['id']}/decision", array( 'decision' => 'APPROVE' ), $o1 ) );

$staff = array( // synthetic people: base, taxable allowance, standard deduction
	array( 'name' => 'Synthetic Employee One', 'base' => '3000.00', 'allow' => '500.00', 'ded' => '50.00' ),
	array( 'name' => 'Synthetic Employee Two', 'base' => '12000.00', 'allow' => '1500.00', 'ded' => '0.00' ),
	array( 'name' => 'Synthetic Employee Three', 'base' => '5263.16', 'allow' => '0.00', 'ded' => '10.33' ),
);
foreach ( $staff as $i => &$s ) {
	$n = $i + 1;
	$cand = $need( "hire $n: candidate recorded", call( 'POST', '/hr/lifecycle/candidates', array( 'candidate_code' => "SYN-CAND-$n", 'full_name' => $s['name'], 'email' => "syn-cand-$n@example.invalid" ), $mg ) );
	$app = $need( "hire $n: application recorded", call( 'POST', '/hr/lifecycle/applications', array( 'requisition_id' => $req['id'], 'candidate_id' => $cand['id'] ), $mg ) );
	$refused( "hire $n: selection is refused before an interview recommendation", call( 'POST', "/hr/lifecycle/applications/{$app['id']}/select", array( 'selection_rationale' => 'Selected on synthetic merit for the cycle test.' ), $o1 ), 409, 'interview' );
	$need( "hire $n: interview with recommendation", call( 'POST', '/hr/lifecycle/interviews', array( 'application_id' => $app['id'], 'interview_round' => 1, 'recommendation' => 'RECOMMEND', 'total_score' => '90' ), $mg ) );
	$sel = $need( "hire $n: Owner selects the candidate (pending employee + draft contract)", call( 'POST', "/hr/lifecycle/applications/{$app['id']}/select", array( 'selection_rationale' => 'Selected on synthetic merit for the cycle test.' ), $o1 ) );
	$s['emp'] = (int) $sel['employee_id']; $s['code'] = (string) $sel['employee_code']; $s['contract'] = (int) $sel['contract_id'];
	$refused( "hire $n: activation is refused before contract, onboarding and compensation", call( 'POST', "/hr/lifecycle/employees/{$s['emp']}/activate", array( 'approval_note' => 'Owner hire approval attempt' ), $o2 ), 409, 'contract' );
	$doc = $need( "hire $n: contract document recorded against the private-vault reference", call( 'POST', '/hr/lifecycle/documents', array( 'subject_type' => 'CONTRACT', 'subject_id' => $s['contract'], 'document_type' => 'EMPLOYMENT_CONTRACT', 'document_reference' => 'hrv_' . str_repeat( dechex( $n ), 48 ) ), $mg ) );
	$need( "hire $n: document submitted", call( 'POST', "/hr/lifecycle/documents/{$doc['id']}/decision", array( 'decision' => 'SUBMIT' ), $mg ) );
	$refused( "hire $n: maker-checker — the document creator cannot verify it", call( 'POST', "/hr/lifecycle/documents/{$doc['id']}/decision", array( 'decision' => 'APPROVE' ), $mg ), 409, 'Maker-checker' );
	$need( "hire $n: document verified by an independent Owner", call( 'POST', "/hr/lifecycle/documents/{$doc['id']}/decision", array( 'decision' => 'APPROVE' ), $o1 ) );
	$need( "hire $n: contract signed", call( 'POST', "/hr/lifecycle/contracts/{$s['contract']}/sign", array( 'signed_at' => '2026-07-30', 'document_id' => $doc['id'] ), $mg ) );
	$refused( "hire $n: maker-checker — the selecting Owner cannot approve the contract they created", call( 'POST', "/hr/lifecycle/contracts/{$s['contract']}/decision", array( 'decision' => 'APPROVE' ), $o1 ), 409, 'Maker-checker' );
	$need( "hire $n: contract approved by the second Owner", call( 'POST', "/hr/lifecycle/contracts/{$s['contract']}/decision", array( 'decision' => 'APPROVE' ), $o2 ) );
	foreach ( q( 'select id from ' . Stockland_DB::table( 'hr_onboarding_tasks' ) . " where employee_id={$s['emp']}" ) as $t ) {
		$need( "hire $n: onboarding task {$t['id']} completed", call( 'POST', "/hr/lifecycle/onboarding/{$t['id']}/decision", array( 'decision' => 'COMPLETE' ), $mg ) );
	}
	$comp = $need( "hire $n: compensation drafted by the Owner (first compensation precedes activation)", call( 'POST', "/hr/employees/{$s['emp']}/compensation", array( 'effective_from' => '2026-08-01', 'base_salary' => $s['base'], 'allowances' => ( '0.00' === $s['allow'] ? array() : array( array( 'category' => 'TRANSPORT', 'amount' => $s['allow'] ) ) ), 'standard_deduction_total' => $s['ded'] ), $o1 ) );
	$s['comp'] = (int) $comp['id'];
	$refused( "hire $n: maker-checker — the compensation preparer cannot approve it", call( 'POST', "/hr/lifecycle/compensation/{$s['comp']}/approve", array( 'approval_note' => 'Self approval attempt' ), $o1 ), 409, 'Maker-checker' );
	$need( "hire $n: compensation approved by the second Owner", call( 'POST', "/hr/lifecycle/compensation/{$s['comp']}/approve", array( 'approval_note' => 'Approved after independent review.' ), $o2 ) );
	$need( "hire $n: hire activated by the second Owner", call( 'POST', "/hr/lifecycle/employees/{$s['emp']}/activate", array( 'approval_note' => 'Synthetic hire approval.' ), $o2 ) );
}
unset( $s );
$ok( 'three employees are ACTIVE', (int) qv( 'select count(*) from ' . Stockland_DB::table( 'employees' ) . " where status='ACTIVE'" ) === 3 );

/* ---- helpers for running a period ---- */
$gl = function ( $source_type, $source_id ) { return q( 'select l.account_code, sum(l.debit) d, sum(l.credit) c from ' . Stockland_DB::table( 'gl_journals' ) . ' j join ' . Stockland_DB::table( 'gl_lines' ) . ' l on l.journal_id=j.id where j.source_type=\'' . esc_sql( $source_type ) . '\' and j.source_id=\'' . esc_sql( (string) $source_id ) . '\' group by l.account_code' ); };
$gl_map = function ( $rows ) use ( $minor ) { $m = array(); foreach ( $rows as $r ) { $m[ $r['account_code'] ] = array( $minor( $r['d'] ), $minor( $r['c'] ) ); } return $m; };
$journal_count = function ( $source_type, $source_id ) { return (int) qv( 'select count(*) from ' . Stockland_DB::table( 'gl_journals' ) . " where source_type='" . esc_sql( $source_type ) . "' and source_id='" . esc_sql( (string) $source_id ) . "'" ); };
$vault_call = function ( $method, ...$args ) { $m = new ReflectionMethod( 'Stockland_HR_Private_Vault', $method ); $m->setAccessible( true ); return $m->invoke( null, ...$args ); };
$receipt_resource = function ( $label ) use ( $mg, $o1, $need, $vault_call, $ok ) { // HTTP upload cannot run under WP-CLI (is_uploaded_file), so the REAL vault encrypt/write path is invoked directly; approval below is the real REST route and re-reads and verifies the encrypted object.
	$code = 'SYN-RCPT-' . $label;
	$plain = 'SYNTHETIC statutory receipt ' . $label . ' (disposable fixture, not a real filing)';
	$ref = 'hrv_' . bin2hex( random_bytes( 24 ) );
	$keyring = $vault_call( 'key_material' );
	$enc = $vault_call( 'encrypt_payload', $plain, $ref, 'txt', $keyring );
	$root = $vault_call( 'root' );
	$sub = $root . DIRECTORY_SEPARATOR . gmdate( 'Y' ) . DIRECTORY_SEPARATOR . gmdate( 'm' );
	wp_mkdir_p( $sub ); chmod( dirname( $sub ), 0700 ); chmod( $sub, 0700 );
	$stored = ! is_wp_error( $keyring ) && ! is_wp_error( $enc ) && ! is_wp_error( $root ) && $vault_call( 'write_atomic', $sub, $sub . DIRECTORY_SEPARATOR . $ref . '.txt', $enc, false );
	$ok( "receipt $label: encrypted object written to the private vault", (bool) $stored );
	Stockland_DB::insert_record( Stockland_DB::table( 'hr_resources' ), array( 'resource_code' => $code, 'title' => 'Synthetic statutory receipt ' . $label, 'description' => 'SYNTHETIC fixture receipt', 'role_code' => 'ALL', 'language_code' => 'en', 'resource_type' => 'STATUTORY_RECEIPT', 'version_label' => '1.0', 'supersedes_resource_id' => null, 'private_file_ref' => $ref, 'file_sha256' => hash( 'sha256', $plain ), 'file_name' => 'synthetic-receipt.txt', 'mime_type' => 'text/plain', 'status' => 'DRAFT', 'created_by' => $mg ) );
	global $wpdb; $id = (int) $wpdb->insert_id;
	$need( "receipt $label approved by an independent Owner", call( 'POST', "/hr/resources/$id/approve", array( 'approval_note' => 'Synthetic receipt verified.' ), $o1 ) );
	return $id;
};
$expect = array(); // employee code => oracle result for the current period
$check_run = function ( $tag, $run_id, $staff_now ) use ( $ok, $oracle, $money, $minor, &$expect ) {
	$run = q( 'select * from ' . Stockland_DB::table( 'hr_payroll_runs' ) . " where id=$run_id" )[0];
	$lines = q( 'select * from ' . Stockland_DB::table( 'hr_payroll_run_lines' ) . " where run_id=$run_id order by employee_code" );
	$ok( "$tag: one frozen line per active employee", count( $lines ) === count( $staff_now ) && (int) $run['employee_count'] === count( $staff_now ) );
	$sum = array( 'gross' => 0, 'ep' => 0, 'er' => 0, 'tax' => 0, 'ded' => 0, 'net' => 0 );
	foreach ( $lines as $l ) {
		$exp = $staff_now[ $l['employee_code'] ];
		$o = $oracle( $exp['base'], $exp['allow'], $exp['ded'] );
		$got = array( 'gross' => $minor( $l['gross_total'] ), 'ep' => $minor( $l['employee_pension_total'] ), 'er' => $minor( $l['employer_pension_total'] ), 'tax' => $minor( $l['paye_total'] ), 'ded' => $minor( $l['other_deduction_total'] ), 'net' => $minor( $l['net_total'] ) );
		$ok( "$tag: {$l['employee_code']} matches the independent oracle to the cent", $got === $o, 'plugin=' . wp_json_encode( $got ) . ' oracle=' . wp_json_encode( $o ) );
		$ok( "$tag: {$l['employee_code']} gross = net + employee pension + PAYE + deductions", $got['gross'] === $got['net'] + $got['ep'] + $got['tax'] + $got['ded'] );
		foreach ( $sum as $k => $v ) { $sum[ $k ] += $o[ $k ]; }
		$expect[ $tag ][ $l['employee_code'] ] = $o;
	}
	$hdr = array( 'gross' => $minor( $run['gross_total'] ), 'ep' => $minor( $run['employee_pension_total'] ), 'er' => $minor( $run['employer_pension_total'] ), 'tax' => $minor( $run['paye_total'] ), 'ded' => $minor( $run['other_deduction_total'] ), 'net' => $minor( $run['net_total'] ) );
	$ok( "$tag: run header totals equal the sum of the oracle lines", $hdr === $sum, wp_json_encode( $hdr ) . ' vs ' . wp_json_encode( $sum ) );
	return $sum;
};
$cycle_period = function ( $tag, $run_id, $staff_now ) use ( $need, $ok, $refused, $mg, $o1, $o2, $check_run, $gl, $gl_map, $journal_count, $minor, $money, $receipt_resource ) {
	$gen = $need( "$tag: run generated (frozen calculation)", call( 'POST', "/hr/payroll-runs/$run_id/generate", array(), $mg ) );
	$ok( "$tag: generated run is CALCULATED with a frozen input hash", ( $gen['status'] ?? '' ) === 'CALCULATED' && preg_match( '/^[a-f0-9]{64}$/', (string) ( $gen['input_snapshot_sha256'] ?? '' ) ) === 1 );
	$sum = $check_run( $tag, $run_id, $staff_now );
	$refused( "$tag: approval before submission is refused", call( 'POST', "/hr/payroll-runs/$run_id/approve", array( 'approval_note' => 'Premature approval attempt by the owner.' ), $o1 ), 409, 'submitted' );
	$need( "$tag: run submitted by Management", call( 'POST', "/hr/payroll-runs/$run_id/submit", array( 'review_note' => 'Preparer review complete.' ), $mg ) );
	$refused( "$tag: Management cannot approve its own run (Owner only)", call( 'POST', "/hr/payroll-runs/$run_id/approve", array( 'approval_note' => 'Self approval attempt by the preparer.' ), $mg ), 403 );
	$ap = $need( "$tag: run approved by an independent Owner and posted to the GL", call( 'POST', "/hr/payroll-runs/$run_id/approve", array( 'approval_note' => 'Independent Owner review of the frozen synthetic run.' ), $o1 ) );
	$ok( "$tag: run status POSTED with a journal", ( $ap['status'] ?? '' ) === 'POSTED' && (int) ( $ap['journal_id'] ?? 0 ) > 0 );
	$g = $gl_map( $gl( 'HR_PAYROLL_RUN', $run_id ) );
	$ok( "$tag: accrual journal — expense equals gross", ( $g['6200'][0] ?? -1 ) === $sum['gross'] );
	$ok( "$tag: accrual journal — liabilities equal net, PAYE, pensions", ( $g['2020'][1] ?? -1 ) === $sum['net'] && ( $g['2031'][1] ?? -1 ) === $sum['tax'] && ( $g['2030'][1] ?? -1 ) === $sum['ep'] && ( $g['2032'][1] ?? -1 ) === $sum['er'] && ( $g['6210'][0] ?? -1 ) === $sum['er'] && ( $g['2033'][1] ?? -1 ) === $sum['ded'] );
	$d = 0; $c = 0; foreach ( $g as $v ) { $d += $v[0]; $c += $v[1]; }
	$ok( "$tag: accrual journal balances to the cent", $d === $c && $d > 0, "D=$d C=$c" );
	$before = $journal_count( 'HR_PAYROLL_RUN', $run_id ); $hash_before = qv( 'select calculation_sha256 from ' . Stockland_DB::table( 'hr_payroll_runs' ) . " where id=$run_id" );
	$refused( "$tag: re-approval of a posted run is rejected", call( 'POST', "/hr/payroll-runs/$run_id/approve", array( 'approval_note' => 'Second approval attempt on a posted run.' ), $o1 ), 409, 'submitted' );
	$refused( "$tag: re-submission of a posted run is rejected", call( 'POST', "/hr/payroll-runs/$run_id/submit", array( 'review_note' => 'Second submission attempt.' ), $mg ), 409 );
	$refused( "$tag: regeneration of a posted run is rejected (immutable)", call( 'POST', "/hr/payroll-runs/$run_id/generate", array(), $mg ), 409, 'draft' );
	$ok( "$tag: no duplicate posting and the frozen calculation is unchanged", $journal_count( 'HR_PAYROLL_RUN', $run_id ) === $before && 1 === $before && qv( 'select calculation_sha256 from ' . Stockland_DB::table( 'hr_payroll_runs' ) . " where id=$run_id" ) === $hash_before );
	$rem = q( 'select * from ' . Stockland_DB::table( 'hr_statutory_remittances' ) . " where run_id=$run_id order by statutory_type" );
	$due = array(); foreach ( $rem as $r ) { $due[ $r['statutory_type'] ] = $minor( $r['amount_due'] ); }
	$ok( "$tag: statutory obligations equal PAYE and both pension totals", ( $due['PAYE'] ?? -1 ) === $sum['tax'] && ( $due['EMPLOYEE_PENSION'] ?? -1 ) === $sum['ep'] && ( $due['EMPLOYER_PENSION'] ?? -1 ) === $sum['er'] && 3 === count( $rem ) );

	// payout: one failed recipient, retried in a second batch
	$b = $need( "$tag: payment batch instructed", call( 'POST', "/hr/payroll-runs/$run_id/payment-batches", array( 'payment_method' => 'BANK', 'external_batch_reference' => "SYN-BATCH-$tag", 'idempotency_key' => "syn-batch-$tag-0001" ), $mg ) );
	$ok( "$tag: batch requests exactly the run net total", $minor( $b['total_requested'] ) === $sum['net'] && 3 === (int) $b['recipient_count'] );
	$again = call( 'POST', "/hr/payroll-runs/$run_id/payment-batches", array( 'payment_method' => 'BANK', 'external_batch_reference' => "SYN-BATCH-$tag", 'idempotency_key' => "syn-batch-$tag-0001" ), $mg );
	$ok( "$tag: batch replay is idempotent", $again[0] === 200 && ! empty( $again[1]['idempotent_replay'] ) && (int) $again[1]['id'] === (int) $b['id'] );
	$items = q( 'select * from ' . Stockland_DB::table( 'hr_payroll_payment_items' ) . ' where batch_id=' . (int) $b['id'] . ' order by employee_code' );
	$paid = 0; $failed_item = null;
	foreach ( $items as $k => $it ) {
		if ( 2 === $k ) {
			$failed_item = $it;
			$refused( "$tag: a failed payout needs an evidence reason", call( 'POST', "/hr/payroll-payment-batches/{$b['id']}/items/{$it['id']}/result", array( 'result' => 'FAILED' ), $mg ), 409, 'evidence reason' );
			$need( "$tag: recipient 3 payout recorded FAILED", call( 'POST', "/hr/payroll-payment-batches/{$b['id']}/items/{$it['id']}/result", array( 'result' => 'FAILED', 'failure_reason' => 'Synthetic bank rejection' ), $mg ) );
		} else {
			$refused( "$tag: a paid result with the wrong amount is rejected", call( 'POST', "/hr/payroll-payment-batches/{$b['id']}/items/{$it['id']}/result", array( 'result' => 'PAID', 'external_payment_reference' => "SYN-PAY-$tag-$k", 'amount' => $money( $minor( $it['amount'] ) + 1 ) ), $mg ), 409, 'differs' );
			$need( "$tag: recipient " . ( $k + 1 ) . ' payout recorded PAID', call( 'POST', "/hr/payroll-payment-batches/{$b['id']}/items/{$it['id']}/result", array( 'result' => 'PAID', 'external_payment_reference' => "SYN-PAY-$tag-$k", 'amount' => $it['amount'] ), $mg ) );
			$paid += $minor( $it['amount'] );
		}
	}
	$refused( "$tag: reconciliation with a wrong confirmed total is rejected", call( 'POST', "/hr/payroll-payment-batches/{$b['id']}/reconcile", array( 'bank_statement_reference' => "SYN-STMT-$tag", 'external_batch_reference' => "SYN-BATCH-$tag", 'confirmed_paid_total' => $money( $paid + 100 ) ), $o1 ), 409, 'differs' );
	$rc = $need( "$tag: batch 1 reconciled to bank evidence (partial: one recipient failed)", call( 'POST', "/hr/payroll-payment-batches/{$b['id']}/reconcile", array( 'bank_statement_reference' => "SYN-STMT-$tag", 'external_batch_reference' => "SYN-BATCH-$tag", 'confirmed_paid_total' => $money( $paid ) ), $o1 ) );
	$ok( "$tag: batch 1 is PARTIAL_RECONCILED and cleared exactly the paid amount in the GL", ( $rc['status'] ?? '' ) === 'PARTIAL_RECONCILED' && ( $gl_map( $gl( 'HR_PAYROLL_BATCH_PAYMENT', $b['id'] ) )['2020'][0] ?? -1 ) === $paid );
	$retry = $need( "$tag: retry batch for the failed recipient only", call( 'POST', "/hr/payroll-runs/$run_id/payment-batches", array( 'payment_method' => 'BANK', 'external_batch_reference' => "SYN-BATCH-$tag-R", 'idempotency_key' => "syn-batch-$tag-0002", 'retry_failed_only' => true ), $mg ) );
	$ok( "$tag: retry batch holds exactly the failed recipient and amount", 1 === (int) $retry['recipient_count'] && $minor( $retry['total_requested'] ) === $minor( $failed_item['amount'] ) );
	$ritem = q( 'select * from ' . Stockland_DB::table( 'hr_payroll_payment_items' ) . ' where batch_id=' . (int) $retry['id'] )[0];
	$need( "$tag: retried payout recorded PAID", call( 'POST', "/hr/payroll-payment-batches/{$retry['id']}/items/{$ritem['id']}/result", array( 'result' => 'PAID', 'external_payment_reference' => "SYN-PAY-$tag-R", 'amount' => $ritem['amount'] ), $mg ) );
	$rr = $need( "$tag: retry batch reconciled", call( 'POST', "/hr/payroll-payment-batches/{$retry['id']}/reconcile", array( 'bank_statement_reference' => "SYN-STMT-$tag-R", 'external_batch_reference' => "SYN-BATCH-$tag-R", 'confirmed_paid_total' => $ritem['amount'] ), $o1 ) );
	$ok( "$tag: retry batch is RECONCILED", ( $rr['status'] ?? '' ) === 'RECONCILED' );
	$pay_total = $paid + $minor( $ritem['amount'] );
	$ok( "$tag: all payouts equal the run net total", $pay_total === $sum['net'] );
	$ok( "$tag: payroll payable (2020) clears to zero for this run", ( ( $gl_map( $gl( 'HR_PAYROLL_RUN', $run_id ) )['2020'][1] ?? -1 ) - ( $gl_map( $gl( 'HR_PAYROLL_BATCH_PAYMENT', $b['id'] ) )['2020'][0] ?? 0 ) - ( $gl_map( $gl( 'HR_PAYROLL_BATCH_PAYMENT', $retry['id'] ) )['2020'][0] ?? 0 ) ) === 0 );
	$refused( "$tag: a paid recipient cannot be paid twice (new batch has nothing eligible)", call( 'POST', "/hr/payroll-runs/$run_id/payment-batches", array( 'payment_method' => 'BANK', 'external_batch_reference' => "SYN-BATCH-$tag-X", 'idempotency_key' => "syn-batch-$tag-0003" ), $mg ), 409, 'eligible' );

	// statutory remittances
	$run_status = qv( 'select status from ' . Stockland_DB::table( 'hr_payroll_runs' ) . " where id=$run_id" );
	$ok( "$tag: run is not SETTLED until remittances are reconciled", 'POSTED' === $run_status || 'PAYMENT_PARTIAL' === $run_status, $run_status );
	foreach ( $rem as $k => $r ) {
		$receipt = $receipt_resource( $tag . '-' . $r['statutory_type'] );
		$refused( "$tag: {$r['statutory_type']} remittance with a wrong amount is rejected", call( 'POST', "/hr/statutory-remittances/{$r['id']}/reconcile", array( 'filing_reference' => "SYN-FILE-$tag", 'payment_reference' => "SYN-REMIT-$tag-$k", 'receipt_resource_id' => $receipt, 'confirmed_amount' => $money( $minor( $r['amount_due'] ) + 1 ) ), $mg ), 409, 'exactly' );
		$rm = $need( "$tag: {$r['statutory_type']} remittance reconciled with receipt evidence", call( 'POST', "/hr/statutory-remittances/{$r['id']}/reconcile", array( 'filing_reference' => "SYN-FILE-$tag", 'payment_reference' => "SYN-REMIT-$tag-$k", 'receipt_resource_id' => $receipt, 'confirmed_amount' => $r['amount_due'], 'payment_method' => 'BANK' ), $mg ) );
		$liab = array( 'PAYE' => '2031', 'EMPLOYEE_PENSION' => '2030', 'EMPLOYER_PENSION' => '2032' )[ $r['statutory_type'] ];
		$rg = $gl_map( $gl( 'HR_STATUTORY_REMITTANCE', $r['id'] ) );
		$ok( "$tag: {$r['statutory_type']} remittance posts a balanced journal clearing the liability", ( $rg[ $liab ][0] ?? -1 ) === $minor( $r['amount_due'] ) && ( $rg['1010'][1] ?? -1 ) === $minor( $r['amount_due'] ) && ! empty( $rm['journal_id'] ) );
		$refused( "$tag: {$r['statutory_type']} remittance cannot be reconciled twice", call( 'POST', "/hr/statutory-remittances/{$r['id']}/reconcile", array( 'filing_reference' => "SYN-FILE-$tag", 'payment_reference' => "SYN-REMIT-$tag-$k", 'receipt_resource_id' => $receipt, 'confirmed_amount' => $r['amount_due'] ), $mg ), 409 );
		$ok( "$tag: {$r['statutory_type']} remittance has exactly one journal", 1 === $journal_count( 'HR_STATUTORY_REMITTANCE', $r['id'] ) );
	}
	$ok( "$tag: run is SETTLED after payouts and remittances are reconciled", 'SETTLED' === qv( 'select status from ' . Stockland_DB::table( 'hr_payroll_runs' ) . " where id=$run_id" ) );
	$ok( "$tag: every payroll liability account for this run is cleared (2030/2031/2032)", ( function () use ( $gl_map, $gl, $run_id, $rem ) {
		$acc = array( '2030' => 0, '2031' => 0, '2032' => 0 ); $a = $gl_map( $gl( 'HR_PAYROLL_RUN', $run_id ) );
		foreach ( $acc as $k => $_ ) { $acc[ $k ] = ( $a[ $k ][1] ?? 0 ) - ( $a[ $k ][0] ?? 0 ); }
		foreach ( $rem as $r ) { $l = array( 'PAYE' => '2031', 'EMPLOYEE_PENSION' => '2030', 'EMPLOYER_PENSION' => '2032' )[ $r['statutory_type'] ]; $g2 = $gl_map( $gl( 'HR_STATUTORY_REMITTANCE', $r['id'] ) ); $acc[ $l ] -= ( $g2[ $l ][0] ?? 0 ); }
		return 0 === $acc['2030'] && 0 === $acc['2031'] && 0 === $acc['2032'];
	} )() );
	return $sum;
};

/* ---- 5. period 1 (2026-09) ---- */
$now = array(); foreach ( $staff as $s ) { $now[ $s['code'] ] = $s; }
$sum1 = $cycle_period( 'P1', $run1_id, $now );

/* ---- 6. correction in period 2: effective-dated raise through the lifecycle compensation route, plus an adjustment record ---- */
$e1 = $staff[0];
$raise = $need( 'P2 correction: Management drafts a raise for employee one (effective 2026-10-01)', call( 'POST', '/hr/lifecycle/compensation', array( 'employee_id' => $e1['emp'], 'effective_from' => '2026-10-01', 'base_salary' => '3200.00', 'allowances' => array( array( 'category' => 'TRANSPORT', 'amount' => '500.00' ) ), 'standard_deduction_total' => '50.00' ), $mg ) );
$refused( 'P2 correction: Management cannot approve compensation (Owner only)', call( 'POST', "/hr/lifecycle/compensation/{$raise['id']}/approve", array( 'approval_note' => 'Self approval attempt.' ), $mg ), 403 );
$need( 'P2 correction: Owner approves the raise', call( 'POST', "/hr/lifecycle/compensation/{$raise['id']}/approve", array( 'approval_note' => 'Approved after independent review.' ), $o1 ) );
$now[ $e1['code'] ]['base'] = '3200.00';
$adj = $need( 'P2 correction: adjustment record drafted', call( 'POST', '/hr/lifecycle/adjustments', array( 'employee_id' => $e1['emp'], 'source_period_id' => $run1_id, 'target_period_key' => '2026-10', 'adjustment_type' => 'CORRECTION', 'amount' => '100.00', 'taxable_amount' => '100.00', 'pensionable_amount' => '0.00', 'reason' => 'Synthetic correction record' ), $mg ) );
$need( 'P2 correction: adjustment submitted', call( 'POST', "/hr/lifecycle/adjustments/{$adj['id']}/decision", array( 'decision' => 'SUBMIT' ), $mg ) );
$refused( 'P2 correction: Management cannot approve an adjustment (Owner commitment)', call( 'POST', "/hr/lifecycle/adjustments/{$adj['id']}/decision", array( 'decision' => 'APPROVE' ), $mg ), 403, 'Owner' );
$need( 'P2 correction: Owner approves the adjustment record', call( 'POST', "/hr/lifecycle/adjustments/{$adj['id']}/decision", array( 'decision' => 'APPROVE' ), $o1 ) );

$run2 = $need( 'run P2 created (Management)', call( 'POST', '/hr/payroll-runs', array( 'idempotency_key' => 'syn-cycle-p2-2026-10-001', 'period_key' => '2026-10', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31' ), $mg ) );
$run2_id = (int) $run2['id'];
$sum2 = $cycle_period( 'P2', $run2_id, $now );
$ok( 'P2 reflects the correction: employee one is paid on the raised base while others are unchanged', $sum2['gross'] === $sum1['gross'] + 20000 );
echo "NOTE approved adjustment records are stored and audited but not consumed by run generation; the effective-dated compensation change above is the exercised correction path.\n";
echo 'NOTE adjustment record ' . $adj['id'] . ' status=' . qv( 'select status from ' . Stockland_DB::table( 'hr_payroll_adjustments' ) . ' where id=' . (int) $adj['id'] ) . "\n";

/* ---- 7. exit with final settlement for employee three ---- */
$e3 = $staff[2];
$exit_body = array( 'employee_id' => $e3['emp'], 'exit_date' => '2026-11-05', 'last_work_date' => '2026-11-05', 'exit_type' => 'RESIGNATION', 'reason' => 'Synthetic resignation', 'settlement_code' => 'SYN-SET-3', 'components' => array( array( 'category' => 'UNUSED_LEAVE', 'amount' => '6000.00', 'source_reference' => 'SYN-LEAVE-1', 'legal_basis' => 'SYNTHETIC clause' ) ), 'other_deductions' => '0.00' );
$unk = $exit_body; $unk['components'][0]['category'] = 'UNCLASSIFIED_PAYMENT'; $unk['settlement_code'] = 'SYN-SET-X';
$refused( 'exit: a settlement component with no approved legal classification is refused', call( 'POST', '/hr/lifecycle/exits/calculate', $unk, $mg ), 409, 'classification' );
$st = $need( 'exit: final settlement calculated by Management', call( 'POST', '/hr/lifecycle/exits/calculate', $exit_body, $mg ) );
$sid = (int) ( $st['id'] ?? qv( 'select id from ' . Stockland_DB::table( 'hr_exit_settlements' ) . " where settlement_code='SYN-SET-3'" ) );
$srow = q( 'select * from ' . Stockland_DB::table( 'hr_exit_settlements' ) . " where id=$sid" )[0];
$cum_tax = max( 0, $rhu( 600000, 200000 ) - 60000 ); // band 3: 0.20 x 6000.00 - credit 600.00 (cumulative form, independent of the calculator)
$ok( 'exit: settlement gross, PAYE and net match the independent oracle', $minor( $srow['gross_total'] ) === 600000 && $minor( $srow['tax_total'] ) === $cum_tax && $minor( $srow['net_total'] ) === 600000 - $cum_tax && 0 === $minor( $srow['employee_pension_total'] ), wp_json_encode( $srow ) );
$refused( 'exit: payment before approval is refused', call( 'POST', "/hr/lifecycle/exits/$sid/pay", array( 'payment_reference' => 'SYN-SET-PAY-0', 'payment_method' => 'BANK' ), $o1 ), 409, 'approved' );
$need( 'exit: settlement approved by the Owner', call( 'POST', "/hr/lifecycle/exits/$sid/approve", array( 'approval_note' => 'Independent Owner review of the synthetic settlement.' ), $o1 ) );
$pay = $need( 'exit: settlement paid and posted', call( 'POST', "/hr/lifecycle/exits/$sid/pay", array( 'payment_reference' => 'SYN-SET-PAY-1', 'payment_method' => 'BANK' ), $o1 ) );
$sg = $gl_map( $gl( 'HR_FINAL_SETTLEMENT', $sid ) ); $sd = 0; $sc = 0; foreach ( $sg as $v ) { $sd += $v[0]; $sc += $v[1]; }
$ok( 'exit: settlement journal balances and expenses the gross', $sd === $sc && ( $sg['6200'][0] ?? -1 ) === 600000, wp_json_encode( $sg ) );
$refused( 'exit: a paid settlement cannot be paid twice', call( 'POST', "/hr/lifecycle/exits/$sid/pay", array( 'payment_reference' => 'SYN-SET-PAY-2', 'payment_method' => 'BANK' ), $o1 ), 409, 'approved' );
$ok( 'exit: exactly one settlement journal', 1 === $journal_count( 'HR_FINAL_SETTLEMENT', $sid ) );

/* ---- 8. books: every journal balanced, trial balance equals the sum of journals ---- */
$unbal = (int) qv( 'select count(*) from (select journal_id from ' . Stockland_DB::table( 'gl_lines' ) . ' group by journal_id having sum(debit)<>sum(credit)) x' );
$ok( 'GL: no unbalanced journal exists', 0 === $unbal );
$tot = q( 'select sum(debit) d, sum(credit) c from ' . Stockland_DB::table( 'gl_lines' ) )[0];
$ok( 'GL: total debits equal total credits', $minor( $tot['d'] ) === $minor( $tot['c'] ) && $minor( $tot['d'] ) > 0 );
$tb = call( 'GET', '/accounting/trial-balance', array(), $o1 );
$tbd = 0; $tbc = 0; foreach ( is_array( $tb[1] ) ? $tb[1] : array() as $tbrow ) { $tbd += $minor( $tbrow['debit'] ); $tbc += $minor( $tbrow['credit'] ); }
$ok( 'GL: the trial balance route answers and its rows sum to the journal-line totals (debits = credits)', 200 === $tb[0] && $tbd === $minor( $tot['d'] ) && $tbc === $minor( $tot['c'] ) && $tbd === $tbc && $tbd > 0, "HTTP {$tb[0]} D=$tbd C=$tbc" );
$ok( 'audit: payroll cycle events are recorded', (int) qv( 'select count(*) from ' . Stockland_DB::table( 'hr_payroll_events' ) ) > 0 );

$finish();
