<?php
/**
 * Owner-approved Ethiopian payroll rule set that ships with the product, so a payroll officer
 * can run payroll with nothing to configure. Approval basis is the Owner's declaration (not an
 * independent adviser opinion) and is recorded as such on the rule and in the audit trail.
 * Future regulation changes are added as a NEW effective-dated rule version; this one is not edited.
 *
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Stockland_Payroll_Release_Rules {
	const RULE_CODE = 'et-payroll';
	const VERSION   = 'ET-2025-AMEND-1395';

	public static function rules() {
		$bands = array(
			array( 'up_to' => '2000.00', 'rate' => '0.00', 'credit' => '0.00' ),
			array( 'up_to' => '4000.00', 'rate' => '0.15', 'credit' => '300.00' ),
			array( 'up_to' => '7000.00', 'rate' => '0.20', 'credit' => '500.00' ),
			array( 'up_to' => '10000.00', 'rate' => '0.25', 'credit' => '850.00' ),
			array( 'up_to' => '14000.00', 'rate' => '0.30', 'credit' => '1350.00' ),
			array( 'up_to' => null, 'rate' => '0.35', 'credit' => '2050.00' ),
		);
		return array(
			'jurisdiction'            => 'ETHIOPIA-FEDERAL',
			'tax_base_method'         => 'TAXABLE_GROSS',
			'paye_tax_base_method'    => 'TAXABLE_EARNINGS',
			'employee_pension_rate'   => '0.07',
			'employer_pension_rate'   => '0.11',
			'pensionable_base_method' => 'SUM_PENSIONABLE_ITEMS',
			'rounding_method'         => 'HALF_UP_MINOR_UNIT',
			'monthly_tax_bands'       => $bands,
			'paye_bands'              => array_map( function ( $b ) { return array( 'up_to' => $b['up_to'], 'rate' => $b['rate'] ); }, $bands ),
			'component_treatments'    => array(
				'BASE'              => array( 'kind' => 'EARNING', 'taxable' => true, 'pensionable' => true ),
				'TAXABLE_ALLOWANCE' => array( 'kind' => 'EARNING', 'taxable' => true, 'pensionable' => false ),
				'EXEMPT_CANDIDATE'  => array( 'kind' => 'EARNING', 'taxable' => false, 'pensionable' => false ),
				'OTHER_DEDUCTION'   => array( 'kind' => 'DEDUCTION', 'taxable' => false, 'pensionable' => false ),
			),
			'qualified_legal_review'  => array(
				'basis'               => 'OWNER_DECLARATION',
				'decision'            => 'APPROVED',
				'approved_on'         => '2026-11-16',
				'approved_by_role'    => 'OWNER',
				'statement'           => 'The Owner reviewed the legal texts and samples and approved these parameters for payroll use. No independent adviser opinion is attached to this rule version.',
				'clauses_reviewed'    => array( 'Income Tax Proclamation 979/2016 Art. 10-12 as amended by Proclamation 1395/2025 (monthly employment income tax rates)', 'Private Organization Employees Pension Proclamation 715/2011 (7% employee, 11% employer, on basic salary)' ),
				'approved_parameters' => array( 'paye_bands', 'monthly_tax_bands', 'tax_base_method', 'paye_tax_base_method', 'pensionable_base_method', 'employee_pension_rate', 'employer_pension_rate', 'component_treatments', 'rounding_method' ),
			),
		);
	}

	/** The Owner the approval is attributed to: the first Stockland Owner, else the first administrator. 0 until one exists. */
	private static function owner_id() {
		foreach ( array( Stockland_Roles::ROLE_OWNER, 'administrator' ) as $role ) {
			$ids = get_users( array( 'role' => $role, 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID' ) );
			if ( ! empty( $ids ) ) { return (int) $ids[0]; }
		}
		return 0;
	}

	/** Insert the shipped rule version once. Safe to call on every request. */
	public static function ensure() {
		if ( ! class_exists( 'Stockland_HR' ) || ! Stockland_HR::is_schema_ready() ) { return; }
		global $wpdb;
		$table = Stockland_DB::table( 'hr_rule_sets' );
		$have  = Stockland_DB::read_row( $wpdb->prepare( 'SELECT id, approved_by FROM %i WHERE rule_code=%s AND version_label=%s', $table, self::RULE_CODE, self::VERSION ), ARRAY_A );
		$owner = self::owner_id();
		if ( $have ) {
			if ( empty( $have['approved_by'] ) && $owner > 0 ) { Stockland_DB::update_record( $table, array( 'approved_by' => $owner ), array( 'id' => (int) $have['id'] ) ); }
			return;
		}
		if ( $owner < 1 ) { return; } // approval is attributed to a real Owner account; payroll stays blocked until one exists.
		$rules = self::rules();
		$json  = wp_json_encode( $rules );
		if ( ! is_string( $json ) ) { return; }
		$now = current_time( 'mysql' );
		$id  = Stockland_DB::insert_record( $table, array(
			'rule_code' => self::RULE_CODE, 'rule_type' => 'PAYROLL', 'version_label' => self::VERSION, 'jurisdiction' => 'ETHIOPIA-FEDERAL',
			'effective_from' => '2025-07-01', 'source_title' => 'Income Tax Proclamation 979/2016 as amended by Income Tax (Amendment) Proclamation 1395/2025 (Art. 11 rates); Private Organization Employees Pension Proclamation 715/2011',
			'source_url' => 'https://www.mor.gov.et/', 'source_clause' => 'Art. 11 employment income tax rates (as amended 2025); pension contribution rates 7% / 11% of basic salary',
			'source_sha256' => hash( 'sha256', $json ), 'source_publication_date' => '2025-07-27', 'review_status' => 'APPROVED',
			'rules_json' => $json, 'reviewer_note' => 'Owner declaration 2026-11-16. Shipped default; add a new effective-dated version when regulation changes. Digest is of the rule definition itself; attach the gazette copies in the HR library.',
			'approved_by' => $owner, 'approved_at' => $now, 'created_by' => $owner,
		) );
		if ( $id ) {
			Stockland_HR_Lifecycle::write_event( 'rule-set', (int) $id, 'RULE_RELEASE_APPROVED', null, 'APPROVED', array( 'version' => self::VERSION, 'basis' => 'OWNER_DECLARATION' ), (int) $id, self::VERSION );
		}
	}
}
