<?php
/**
 * Read-only classifier for source-declared table-key inventories.
 *
 * Counts are diagnostic, not an Owner-approved schema contract. The legacy
 * operational subset (29 Core + 16 Accounting/Procurement + 4 legacy HR keys)
 * is 49 keys; the current HR lifecycle source adds further keys, but no final
 * HR table count or migration is approved by this class. Known HRX keys are
 * recognized for preservation only; they are not activated or consumed.
 * Never infer schema approval from a count. Exact columns, indexes, defaults,
 * relationships and existing rows require a separately approved reconciliation.
 *
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Stockland_Schema_Contract {
	/** Known additive HRX key names for read-only recognition; not schema approval. */
	public static function hrx_extension_keys() {
		return array(
			'hrx_org_units',
			'hrx_positions',
			'hrx_workforce_plans',
			'hrx_vacancies',
			'hrx_persons',
			'hrx_applications',
			'hrx_interviews',
			'hrx_offers',
			'hrx_employments',
			'hrx_contracts',
			'hrx_onboarding_tasks',
			'hrx_employee_events',
			'hrx_shift_assignments',
			'hrx_time_events',
			'hrx_leave_policies',
			'hrx_leave_requests',
			'hrx_leave_movements',
			'hrx_compensation_assignments',
			'hrx_compensation_items',
			'hrx_statutory_rules',
			'hrx_payroll_runs',
			'hrx_payroll_lines',
			'hrx_payroll_line_items',
			'hrx_payout_batches',
			'hrx_payout_results',
			'hrx_remittances',
			'hrx_separation_cases',
			'hrx_individual_payees',
			'hrx_payee_engagements',
			'hrx_resource_documents',
			'hrx_social_snapshots',
			'hrx_audit_events',
			'hrx_idempotency_requests',
		);
	}

	/**
	 * Classify existing table keys without mutating or migrating the database.
	 * A complete base contract plus only known extension keys is valid for read-only
	 * core integrity scans. HRX completeness/version is reported separately.
	 *
	 * @param array $found_keys Observed keys below the wp_stock_ prefix.
	 * @param array $base_expected Exact base ERP key set.
	 * @param array $extension_expected Known additive extension keys.
	 * @return array Contract assessment.
	 */
	public static function assess( $found_keys, $base_expected, $extension_expected = array() ) {
		$normalize = static function ( $values ) {
			$out = array();
			foreach ( (array) $values as $value ) {
				if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
					continue;
				}
				$value = trim( (string) $value );
				if ( '' !== $value ) {
					$out[] = $value;
				}
			}
			return array_values( array_unique( $out ) );
		};
		$found = $normalize( $found_keys );
		$base = $normalize( $base_expected );
		$extension = $normalize( $extension_expected );
		$allowed = array_values( array_unique( array_merge( $base, $extension ) ) );
		$base_present = array_values( array_intersect( $base, $found ) );
		$base_missing = array_values( array_diff( $base, $found ) );
		$extension_present = array_values( array_intersect( $extension, $found ) );
		$extension_missing = array_values( array_diff( $extension, $found ) );
		$unexpected = array_values( array_diff( $found, $allowed ) );
		return array(
			'base_expected_count' => count( $base ),
			'base_present_count' => count( $base_present ),
			'base_present' => $base_present,
			'base_missing' => $base_missing,
			'base_contract_exact' => ! empty( $base ) && count( $base_present ) === count( $base ) && empty( $base_missing ) && empty( $unexpected ),
			'extension_expected_count' => count( $extension ),
			'extension_present_count' => count( $extension_present ),
			'extension_present' => $extension_present,
			'extension_missing' => $extension_missing,
			'extension_complete' => ! empty( $extension ) && empty( $extension_missing ),
			'unexpected_keys' => $unexpected,
			'physical_table_count' => count( $found ),
			'allowlisted_table_count' => count( array_intersect( $allowed, $found ) ),
		);
	}
}
