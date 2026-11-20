<?php
/**
 * Statutory payroll release-rule fail-closed regression.
 * Uses a disposable WordPress/database and synthetic review metadata only.
 */
if ( 'yes' !== getenv( 'STOCKLAND_DISPOSABLE_SITE' ) ) {
	fwrite( STDERR, "Refusing to run outside a disposable site.\n" );
	exit( 2 );
}
require_once __DIR__ . '/lib.php';

$failed = 0;
$passed = 0;
$check = static function ( $label, $ok, $detail = '' ) use ( &$failed, &$passed ) {
	if ( $ok ) {
		++$passed;
		echo "PASS {$label}\n";
	} else {
		++$failed;
		echo "FAIL {$label}" . ( '' !== $detail ? " :: {$detail}" : '' ) . "\n";
	}
};

$owner_ids = get_users( array( 'role' => 'stockland_owner', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID' ) );
$owner = (int) ( $owner_ids[0] ?? 0 );
$check( 'disposable site has a Stockland Owner for separate approval tests', $owner > 0 );

global $wpdb;
$table = Stockland_DB::table( 'hr_rule_sets' );
$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE rule_type=%s", $table, 'PAYROLL' ) );

$check( 'release helper ships no statutory PAYE or pension parameters', array() === Stockland_Payroll_Release_Rules::rules() );
Stockland_Payroll_Release_Rules::ensure();
Stockland_Payroll_Release_Rules::ensure();
$seeded = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM %i WHERE rule_type=%s",
	$table,
	'PAYROLL'
) );
$check( 'init helper never inserts or approves a statutory payroll rule', 0 === $seeded, 'PAYROLL rows=' . $seeded );

$blocked = Stockland_HR_Lifecycle::payroll_rules_for_period( '2026-11-01', '2026-11-30' );
$check(
	'payroll generation remains blocked when no independently reviewed approved rule exists',
	is_wp_error( $blocked ) && 'payroll_rules_unapproved' === $blocked->get_error_code(),
	is_wp_error( $blocked ) ? $blocked->get_error_code() : 'Unexpected rule bundle returned'
);

$owner_declaration = array(
	'qualified_legal_review' => array(
		'basis' => 'OWNER_DECLARATION',
		'decision' => 'APPROVED',
		'approved_on' => '2026-11-16',
		'approved_by_role' => 'OWNER',
		'statement' => 'Owner reviewed the source text and approved parameters.',
		'clauses_reviewed' => array( 'Synthetic clause — not a legal opinion' ),
		'approved_parameters' => array(
			'paye_bands', 'monthly_tax_bands', 'tax_base_method', 'paye_tax_base_method',
			'pensionable_base_method', 'employee_pension_rate', 'employer_pension_rate',
			'component_treatments', 'rounding_method',
		),
	),
);
$check(
	'Owner declaration is rejected as qualified legal review',
	! Stockland_HR_Lifecycle::has_qualified_legal_review( $owner_declaration )
);

$missing_basis = $synthetic_review = array(
	'qualified_legal_review' => array(
		'decision' => 'APPROVED',
		'independence_confirmed' => true,
		'reviewer_name' => 'Synthetic Reviewer — TEST ONLY',
		'reviewer_id' => 'SYNTH-REVIEWER-ONLY',
		'professional_basis' => 'Synthetic qualification fixture, not a real credential',
		'review_date' => '2026-01-02',
		'opinion_reference' => 'SYNTHETIC-OPINION-ONLY',
		'opinion_sha256' => hash( 'sha256', 'Synthetic opinion only; not legal evidence' ),
		'clauses_reviewed' => array( 'SYNTHETIC-CLAUSE-ONLY' ),
		'approved_parameters' => array( 'paye_bands', 'monthly_tax_bands', 'tax_base_method', 'paye_tax_base_method', 'pensionable_base_method', 'employee_pension_rate', 'employer_pension_rate', 'component_treatments', 'rounding_method' ),
	),
);
$check( 'review metadata without an explicit independent-opinion basis is rejected', ! Stockland_HR_Lifecycle::has_qualified_legal_review( $missing_basis ) );
$owner_basis = $missing_basis;
$owner_basis['qualified_legal_review']['basis'] = 'OWNER_DECLARATION';
$check( 'Owner-declaration basis is rejected even when reviewer-like fields are present', ! Stockland_HR_Lifecycle::has_qualified_legal_review( $owner_basis ) );

$synthetic_independent_review = array(
	'qualified_legal_review' => array(
		'basis' => 'INDEPENDENT_PROFESSIONAL_OPINION',
		'opinion_document_id' => 999999,
		'decision' => 'APPROVED',
		'independence_confirmed' => true,
		'reviewer_name' => 'Synthetic Reviewer — TEST ONLY',
		'reviewer_id' => 'SYNTH-REVIEWER-ONLY',
		'professional_basis' => 'Synthetic qualification fixture, not a real credential',
		'review_date' => '2026-01-02',
		'opinion_reference' => 'SYNTHETIC-OPINION-ONLY',
		'opinion_sha256' => hash( 'sha256', 'Synthetic opinion only; not legal evidence' ),
		'clauses_reviewed' => array( 'SYNTHETIC-CLAUSE-ONLY' ),
		'approved_parameters' => array(
			'paye_bands', 'monthly_tax_bands', 'tax_base_method', 'paye_tax_base_method',
			'pensionable_base_method', 'employee_pension_rate', 'employer_pension_rate',
			'component_treatments', 'rounding_method',
		),
	),
);

/* Verify that an independently reviewed rule is bound to a real encrypted document object. */
$vault_dir = rtrim( sys_get_temp_dir(), '/' ) . '/stockland-s20-legal-vault-' . bin2hex( random_bytes( 6 ) );
if ( ! defined( 'STOCKLAND_PRIVATE_VAULT_KEY' ) ) { define( 'STOCKLAND_PRIVATE_VAULT_KEY', bin2hex( random_bytes( 32 ) ) ); }
if ( ! defined( 'STOCKLAND_PRIVATE_VAULT_DIR' ) ) { define( 'STOCKLAND_PRIVATE_VAULT_DIR', $vault_dir ); }
$pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
$stored = Stockland_HR_Private_Vault::store_content( $pdf, 'synthetic-legal-opinion.pdf' );
$test_rule_id = 990021;
$opinion_doc_id = 0;
if ( ! is_wp_error( $stored ) ) {
	$opinion_doc_id = (int) Stockland_DB::insert_record( Stockland_DB::table( 'hr_documents' ), array(
		'subject_type' => 'RULE_SET', 'subject_id' => $test_rule_id, 'document_type' => 'LEGAL_OPINION',
		'document_reference' => $stored['private_file_ref'], 'document_sha256' => $stored['file_sha256'],
		'status' => 'PENDING_REVIEW', 'created_by' => $owner,
		'notes' => 'Synthetic disposable-site legal opinion; not law or a professional opinion.',
	) );
}
$synthetic_independent_review['qualified_legal_review']['opinion_document_id'] = $opinion_doc_id;
$synthetic_independent_review['qualified_legal_review']['opinion_sha256'] = is_wp_error( $stored ) ? '' : strtolower( $stored['file_sha256'] );
$check( 'synthetic private PDF opinion is encrypted and registered', ! is_wp_error( $stored ) && $opinion_doc_id > 0 );
$check( 'pending legal-opinion document verifies for its exact rule-set ID', $opinion_doc_id > 0 && Stockland_HR_Lifecycle::verify_legal_opinion_document( $synthetic_independent_review, $test_rule_id, 'PENDING_REVIEW' ) );
$check( 'the same document cannot be replayed against another rule-set ID', $opinion_doc_id > 0 && ! Stockland_HR_Lifecycle::verify_legal_opinion_document( $synthetic_independent_review, $test_rule_id + 1, 'PENDING_REVIEW' ) );
$bad_opinion_hash = $synthetic_independent_review;
$bad_opinion_hash['qualified_legal_review']['opinion_sha256'] = str_repeat( '0', 64 );
$check( 'legal-opinion evidence fails closed when the recorded SHA differs', $opinion_doc_id > 0 && ! Stockland_HR_Lifecycle::verify_legal_opinion_document( $bad_opinion_hash, $test_rule_id, 'PENDING_REVIEW' ) );
if ( $opinion_doc_id > 0 ) {
	Stockland_DB::update_record( Stockland_DB::table( 'hr_documents' ), array( 'status' => 'APPROVED', 'verified_by' => $owner, 'verified_at' => current_time( 'mysql' ) ), array( 'id' => $opinion_doc_id ) );
	$check( 'Owner-ratified legal-opinion document verifies only in APPROVED state', Stockland_HR_Lifecycle::verify_legal_opinion_document( $synthetic_independent_review, $test_rule_id, 'APPROVED' ) );
	$check( 'approved-only verification rejects a status mismatch', ! Stockland_HR_Lifecycle::verify_legal_opinion_document( $synthetic_independent_review, $test_rule_id, 'PENDING_REVIEW' ) );
	Stockland_DB::execute_query( $wpdb->prepare( 'DELETE FROM %i WHERE id=%d', Stockland_DB::table( 'hr_documents' ), $opinion_doc_id ) );
	Stockland_HR_Private_Vault::delete( $stored['private_file_ref'] );
}

$check(
	'synthetic independent-opinion metadata exercises the validator in tests only',
	Stockland_HR_Lifecycle::has_qualified_legal_review( $synthetic_independent_review )
);

$missing_hash = $synthetic_independent_review;
unset( $missing_hash['qualified_legal_review']['opinion_sha256'] );
$check( 'independent-review payload without opinion hash is rejected', ! Stockland_HR_Lifecycle::has_qualified_legal_review( $missing_hash ) );

$missing_reviewer = $synthetic_independent_review;
unset( $missing_reviewer['qualified_legal_review']['reviewer_id'] );
$check( 'independent-review payload without reviewer identity is rejected', ! Stockland_HR_Lifecycle::has_qualified_legal_review( $missing_reviewer ) );

if ( is_dir( $vault_dir ) ) {
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $vault_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $items as $item ) { $item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() ); }
	@rmdir( $vault_dir );
}
echo $failed ? "RESULT FAIL ({$failed} failed, {$passed} passed)\n" : "RESULT PASS ({$passed} assertions)\n";
exit( $failed ? 1 : 0 );
