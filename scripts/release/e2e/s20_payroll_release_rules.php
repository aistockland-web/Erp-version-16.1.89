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

$synthetic_independent_review = array(
	'qualified_legal_review' => array(
		'basis' => 'INDEPENDENT_PROFESSIONAL_OPINION',
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

echo $failed ? "RESULT FAIL ({$failed} failed, {$passed} passed)\n" : "RESULT PASS ({$passed} assertions)\n";
exit( $failed ? 1 : 0 );
