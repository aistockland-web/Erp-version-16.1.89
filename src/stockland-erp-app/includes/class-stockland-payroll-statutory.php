<?php
/**
 * Deterministic statutory payroll arithmetic driven only by an approved rule bundle.
 * This class does not assert current Ethiopian legal applicability or ship statutory rates.
 *
 * @package Stockland_ERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Approved, effective-dated payroll arithmetic. Current rates and rule dates are
 * supplied from the database only after independent qualified review and Owner approval.
 * No historic percentages, tax bands, dates or legal interpretations are defaults.
 */
final class Stockland_Payroll_Statutory {
	const RULE_VERSION          = 'NO_APPROVED_RULE_SET';
	const EMPLOYEE_PENSION_RATE = '';
	const EMPLOYER_PENSION_RATE = '';
	const LAW_EFFECTIVE_DATE    = '';
	const EFFECTIVE_DATE        = '';

	/**
	 * Server-authoritative component catalog for the compensation editor.
	 * Cash/benefit categories are not automatically exempted by their label. Any
	 * non-taxable treatment must come from an exact effective-dated approved rule
	 * bundle and supporting evidence; no legal conclusion is embedded here.
	 */
	public static function allowance_catalog() {
		return array(
			'GENERAL_CASH'  => array(
				'label'             => 'General cash allowance',
				'default_treatment' => 'TAXABLE',
				'description'       => 'No exemption is inferred from the component label; apply only an approved current rule.',
			),
			'HOUSING'       => array(
				'label'             => 'Housing / rent allowance',
				'default_treatment' => 'TAXABLE',
				'description'       => 'Taxable by default; require current legal authority for any exception.',
			),
			'TRANSPORT'     => array(
				'label'             => 'Transport allowance',
				'default_treatment' => 'TAXABLE',
				'description'       => 'Taxable by default; do not use old caps unless verified under current rules.',
			),
			'MEAL'          => array(
				'label'             => 'Meal / subsistence allowance',
				'default_treatment' => 'TAXABLE',
				'description'       => 'Taxable by default unless an applicable current provision is documented.',
			),
			'OVERTIME'      => array(
				'label'             => 'Overtime pay',
				'default_treatment' => 'TAXABLE',
				'description'       => 'Include in employment taxable income.',
			),
			'BONUS'         => array(
				'label'             => 'Bonus / incentive',
				'default_treatment' => 'TAXABLE',
				'description'       => 'Include in employment taxable income.',
			),
			'COMMISSION'    => array(
				'label'             => 'Commission',
				'default_treatment' => 'TAXABLE',
				'description'       => 'Include in employment taxable income.',
			),
			'PER_DIEM'      => array(
				'label'             => 'Duty travel / per diem',
				'default_treatment' => 'TAXABLE',
				'description'       => 'Retain trip dates, policy and receipts; tax exemption is not presumed.',
			),
			'REIMBURSEMENT' => array(
				'label'             => 'Expense reimbursement',
				'default_treatment' => 'TAXABLE',
				'description'       => 'Only reclassify when it meets a currently applicable legal test and the evidence is retained.',
			),
			'OTHER'         => array(
				'label'             => 'Other allowance / benefit',
				'default_treatment' => 'TAXABLE',
				'description'       => 'Taxable by default; describe and reference the applicable law if claiming an exemption.',
			),
		);
	}

	/**
	 * Returns an actionable validation error for one effective-dated compensation record.
	 * This policy is shared by System Health and payroll generation to prevent readiness drift.
	 *
	 * @param array $record One employee_compensation row.
	 * @return string Empty when ready; otherwise a safe, actionable reason.
	 */
	public static function compensation_record_validation_error( $record ) {
		if ( ! is_array( $record ) ) {
			return 'Compensation record is missing or unreadable.';
		}
		$required = array( 'base_salary', 'taxable_allowance_total', 'exempt_allowance_total', 'pensionable_salary' );
		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $record ) || null === $record[ $field ] || '' === $record[ $field ] ) {
				return 'Compensation is missing a required salary, allowance, or pension field.';
			}
		}
		try {
			$base_minor        = Stockland_Money::minor( $record['base_salary'] );
			$taxable_minor     = Stockland_Money::minor( $record['taxable_allowance_total'] );
			$exempt_minor      = Stockland_Money::minor( $record['exempt_allowance_total'] );
			$pensionable_minor = Stockland_Money::minor( $record['pensionable_salary'] );
			$other_raw         = $record['standard_deduction_total'] ?? $record['other_deduction_total'] ?? '0.00';
			$other_minor       = Stockland_Money::minor( $other_raw );
		} catch ( Throwable $e ) {
			return 'Compensation contains an invalid monetary value.';
		}
		if ( min( $base_minor, $taxable_minor, $exempt_minor, $pensionable_minor, $other_minor ) < 0 ) {
			return 'Compensation contains a negative amount.';
		}
		$status       = strtoupper( trim( (string) ( $record['statutory_classification_status'] ?? '' ) ) );
		$creator_id       = (int) ( $record['created_by'] ?? 0 );
		$approver_id      = (int) ( $record['approved_by'] ?? 0 );
		$explicit_approval = 'APPROVED' === strtoupper( (string) ( $record['approval_status'] ?? '' ) )
			&& $creator_id > 0
			&& $approver_id > 0
			&& $approver_id !== $creator_id
			&& ! empty( $record['approved_at'] )
			&& '0000-00-00 00:00:00' !== (string) $record['approved_at'];
		if ( ! $explicit_approval ) {
			return 'Compensation is not supported by an explicit independent approval actor and timestamp.';
		}
		$manager_safe = 'MANAGEMENT_PREPARED' === $status && 0 === $exempt_minor && $pensionable_minor === $base_minor;
		$rule_classified = 'RULE_CLASSIFIED' === $status && ( 0 === $exempt_minor || ( ! empty( $record['allowance_rule_set_id'] ) && ! empty( $record['allowance_rule_version'] ) ) );
		if ( 'OWNER_CLASSIFIED' !== $status && ! $manager_safe && ! $rule_classified ) {
			return 'Compensation needs Owner classification; Management-prepared entries are accepted only for taxable allowances, zero exemptions, and pensionable salary equal to base salary.';
		}
		// This validator classifies and validates the saved compensation record only.
		// It deliberately does not call calculate() because no approved, effective-dated
		// statutory rule bundle is supplied here. The payroll-cycle generator performs
		// calculation separately with its independently approved rule bundle and fails
		// closed when that bundle is missing or out of date.
		$raw_components  = trim( (string) ( $record['allowance_breakdown_json'] ?? '' ) );
		$legacy_snapshot = '' === $raw_components;
		if ( $legacy_snapshot ) {
			// Only a genuinely absent legacy snapshot receives compatibility treatment.
			$components = array();
		} else {
			if ( '[' !== substr( $raw_components, 0, 1 ) ) {
				return 'Allowance component snapshot must be a JSON list; re-save compensation through HR.';
			}
			$components = json_decode( $raw_components, true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $components ) ) {
				return 'Allowance component snapshot is malformed; re-save compensation through HR.';
			}
		}
		$basis = trim( (string) ( $record['statutory_classification_note'] ?? '' ) );
		if ( $pensionable_minor !== $base_minor && strlen( $basis ) < 20 ) {
			return 'Non-standard pensionable salary requires a current 20+ character legal/contract basis.';
		}
		if ( $exempt_minor > 0 ) {
			if ( strlen( $basis ) < 20 ) {
				return 'Exempt allowance requires a current 20+ character legal/contract basis.';
			}
			$saved_exempt_minor = 0;
			foreach ( $components as $component ) {
				if ( ! is_array( $component ) ) {
					return 'Allowance component snapshot contains an invalid row.';
				}
				$treatment = strtoupper( trim( (string) ( $component['tax_treatment'] ?? 'TAXABLE' ) ) );
				if ( ! in_array( $treatment, array( 'TAXABLE', 'EXEMPT' ), true ) ) {
					return 'Allowance component has an unknown tax treatment; re-save compensation through HR.';
				}
				if ( 'EXEMPT' !== $treatment ) {
					continue;
				}
				if ( empty( $component['owner_verified'] ) || strlen( trim( (string) ( $component['legal_basis'] ?? '' ) ) ) < 20 || strlen( trim( (string) ( $component['evidence_reference'] ?? '' ) ) ) < 4 ) {
					return 'Exempt allowance is missing Owner verification or supporting legal evidence.';
				}
				try {
					$component_minor = Stockland_Money::minor( $component['amount'] ?? '0.00' );
				} catch ( Throwable $e ) {
					return 'Allowance component contains an invalid monetary value.';
				}
				if ( $component_minor < 0 ) {
					return 'Allowance component contains a negative amount.';
				}
				$saved_exempt_minor += $component_minor;
			}
			if ( $saved_exempt_minor !== $exempt_minor ) {
				return 'Exempt allowance component snapshot does not match its saved total; re-save it through Owner HR.';
			}
		}
		if ( $legacy_snapshot && ! $components && $taxable_minor > 0 ) {
			// Existing records with no stored snapshot remain compatible with the payroll engine.
			$components[] = array(
				'amount'        => Stockland_Money::decimal( $taxable_minor ),
				'tax_treatment' => 'TAXABLE',
			);
		}
		$snapshot_taxable_minor = 0;
		$snapshot_exempt_minor  = 0;
		foreach ( $components as $component ) {
			if ( ! is_array( $component ) ) {
				return 'Allowance component snapshot contains an invalid row.';
			}
			try {
				$component_minor = Stockland_Money::minor( $component['amount'] ?? '0.00' );
			} catch ( Throwable $e ) {
				return 'Allowance component contains an invalid monetary value.';
			}
			if ( $component_minor < 0 ) {
				return 'Allowance component contains a negative amount.';
			}
			$treatment = strtoupper( trim( (string) ( $component['tax_treatment'] ?? 'TAXABLE' ) ) );
			if ( ! in_array( $treatment, array( 'TAXABLE', 'EXEMPT' ), true ) ) {
				return 'Allowance component has an unknown tax treatment; re-save compensation through HR.';
			}
			if ( 'EXEMPT' === $treatment ) {
				$snapshot_exempt_minor += $component_minor;
			} else {
				$snapshot_taxable_minor += $component_minor;
			}
		}
		if ( $snapshot_taxable_minor !== $taxable_minor || $snapshot_exempt_minor !== $exempt_minor ) {
			return 'Allowance component breakdown does not match saved taxable/exempt totals; re-save the compensation from HR.';
		}
		return '';
	}

	/** Returns no default PAYE bands; only an approved database ruleset may supply them. */
	public static function monthly_tax_bands() {
		return array();
	}

	/**
	 * Calculates one monthly payroll line without floating-point money arithmetic.
	 *
	 * Allowance tax treatment must be verified and approved separately; this method
	 * only calculates amounts after the authoritative classifications are supplied.
	 *
	 * @param mixed $base_salary Base salary amount in ETB.
	 * @param mixed $taxable_allowance Total taxable allowance amount in ETB.
	 * @param mixed $exempt_allowance Owner-classified exempt allowance amount in ETB.
	 * @param mixed $pensionable_salary Salary amount subject to pension contribution.
	 * @param mixed $other_deduction Other authorized deduction amount in ETB.
	 * @return array<string, string> Calculated payroll amounts and rule metadata.
	 * @throws InvalidArgumentException When amounts are invalid or deductions exceed gross pay.
	 */
	public static function calculate( $base_salary, $taxable_allowance, $exempt_allowance, $pensionable_salary, $other_deduction, $rules = null ) {
		$base          = self::amount( $base_salary, 'Base salary' );
		$taxable_allow = self::amount( $taxable_allowance, 'Taxable allowance' );
		$exempt_allow  = self::amount( $exempt_allowance, 'Exempt allowance' );
		$pensionable   = self::amount( $pensionable_salary, 'Pensionable salary' );
		$other         = self::amount( $other_deduction, 'Other deduction' );

		$allowance_total = Stockland_Money::add( $taxable_allow, $exempt_allow );
		$gross           = Stockland_Money::add( Stockland_Money::add( $base, $taxable_allow ), $exempt_allow );
		if ( Stockland_Money::minor( $pensionable ) > Stockland_Money::minor( $gross ) ) {
			throw new InvalidArgumentException( 'Pensionable salary cannot exceed gross pay.' );
		}

		if ( ! is_array( $rules ) || 'APPROVED' !== strtoupper( (string) ( $rules['status'] ?? '' ) ) || empty( $rules['rule_version'] ) || empty( $rules['rule_set_id'] ) || (int) ( $rules['approved_by'] ?? 0 ) < 1 || empty( $rules['approved_at'] ) || ! array_key_exists( 'employee_pension_rate', $rules ) || null === $rules['employee_pension_rate'] || '' === $rules['employee_pension_rate'] || ! array_key_exists( 'employer_pension_rate', $rules ) || null === $rules['employer_pension_rate'] || '' === $rules['employer_pension_rate'] || empty( $rules['monthly_tax_bands'] ) || empty( $rules['tax_base_method'] ) ) {
			throw new InvalidArgumentException( 'An independently reviewed, Owner-approved, effective-dated statutory rule set is required; no legacy payroll fallback is permitted.' );
		}
		if ( ! preg_match( '/^[a-f0-9]{64}$/iD', (string) ( $rules['source_sha256'] ?? '' ) ) || empty( $rules['source_url'] ) || ! class_exists( 'Stockland_HR_Lifecycle' ) || ! Stockland_HR_Lifecycle::has_qualified_legacy_payroll_review( $rules ) ) {
			throw new InvalidArgumentException( 'The approved statutory rule set is missing its official source hash or independent qualified review evidence.' );
		}
		$employee_rate = $rules['employee_pension_rate'];
		$employer_rate = $rules['employer_pension_rate'];
		foreach ( array( $employee_rate, $employer_rate ) as $rate ) {
			if ( ! is_numeric( $rate ) || (float) $rate < 0 || (float) $rate > 1 ) {
				throw new InvalidArgumentException( 'Approved pension rule rate is invalid.' );
			}
		}
		$employee_pension = Stockland_Money::percentage( $pensionable, (string) $employee_rate );
		$employer_pension = Stockland_Money::percentage( $pensionable, (string) $employer_rate );

		// The payroll policy's PAYE base is taxable gross components less the
		// employee's mandatory pension contribution. Employer pension is not
		// deducted from employee earnings. Clamp only the tax base at zero;
		// gross pay and deduction validation remain independent.
		$taxable_gross = Stockland_Money::add( $base, $taxable_allow );
		$tax_base_method = $rules['tax_base_method'] ?? null;
		if ( 'TAXABLE_GROSS_LESS_EMPLOYEE_PENSION' === $tax_base_method ) {
			$taxable_income = Stockland_Money::subtract( $taxable_gross, $employee_pension );
		} elseif ( 'TAXABLE_GROSS' === $tax_base_method ) {
			$taxable_income = $taxable_gross;
		} else {
			throw new InvalidArgumentException( 'Approved PAYE rule has an unsupported tax base method.' );
		}
		if ( Stockland_Money::minor( $taxable_income ) < 0 ) {
			$taxable_income = '0.00';
		}
		list( $income_tax, $income_tax_rate ) = self::income_tax( $taxable_income, $rules['monthly_tax_bands'] ?? null );
		$deduction_total                      = Stockland_Money::add( Stockland_Money::add( $income_tax, $employee_pension ), $other );
		$net                                  = Stockland_Money::subtract( $gross, $deduction_total );
		if ( Stockland_Money::minor( $net ) < 0 ) {
			throw new InvalidArgumentException( 'Statutory and other deductions exceed gross pay.' );
		}

		return array(
			'base_salary'             => $base,
			'taxable_allowance_total' => $taxable_allow,
			'exempt_allowance_total'  => $exempt_allow,
			'allowance_total'         => $allowance_total,
			'other_deduction_total'   => $other,
			'taxable_income'          => $taxable_income,
			'tax_base_basis'          => $rules['tax_base_method'] ?? 'TAXABLE_GROSS_LESS_EMPLOYEE_PENSION',
			'income_tax'              => $income_tax,
			'income_tax_rate'         => $income_tax_rate,
			'pensionable_salary'      => $pensionable,
			'employee_pension_rate'   => (string) $employee_rate,
			'employer_pension_rate'   => (string) $employer_rate,
			'employee_pension'        => $employee_pension,
			'employer_pension'        => $employer_pension,
			'gross_pay'               => $gross,
			'deduction_total'         => $deduction_total,
			'net_pay'                 => $net,
			'employer_cost_total'     => Stockland_Money::add( $gross, $employer_pension ),
			'tax_rule_version'        => (string) $rules['rule_version'],
			'statutory_rule_set_id'   => isset( $rules['rule_set_id'] ) ? (int) $rules['rule_set_id'] : null,
			'statutory_rule_version'  => (string) $rules['rule_version'],
			'statutory_status'        => 'CALCULATED',
		);
	}

	/**
	 * Calculates PAYE using the published monthly brackets and credits.
	 *
	 * The bracket credits keep adjacent rates continuous. This method must only
	 * be used for a payroll period covered by this rule version.
	 *
	 * @param mixed $taxable_income Monthly taxable employment income in ETB.
	 * @return array{0: string, 1: string} The tax amount and applied percentage.
	 * @throws InvalidArgumentException When taxable income is invalid or negative.
	 */
	public static function income_tax( $taxable_income, $bands = null ) {
		if ( ! is_array( $bands ) || ! $bands ) {
			throw new InvalidArgumentException( 'Approved effective-dated PAYE bands are required; no legacy fallback is permitted.' );
		}
		$income = self::amount( $taxable_income, 'Taxable income' );
		$minor  = Stockland_Money::minor( $income );
		foreach ( $bands as $band ) {
			if ( ! is_array( $band ) || ! isset( $band['rate'], $band['credit'] ) || ! is_numeric( $band['rate'] ) || ! is_numeric( $band['credit'] ) || (float) $band['rate'] < 0 || (float) $band['rate'] > 1 ) {
				throw new InvalidArgumentException( 'Approved PAYE band is invalid.' );
			}
			$upper = array_key_exists( 'up_to', $band ) && null !== $band['up_to'] ? Stockland_Money::minor( $band['up_to'] ) : null;
			if ( null !== $upper && $minor > $upper ) {
				continue;
			}
			$rate   = (string) $band['rate'];
			$credit = self::amount( $band['credit'], 'PAYE band credit' );
			$tax    = Stockland_Money::subtract( Stockland_Money::percentage( $income, $rate ), $credit );
			if ( Stockland_Money::minor( $tax ) < 0 ) {
				$tax = '0.00';
			}
			return array( $tax, number_format( (float) $rate * 100, 2, '.', '' ) );
		}
		throw new InvalidArgumentException( 'Taxable income is outside the approved PAYE bands.' );
	}

	/**
	 * Normalizes a non-negative monetary amount.
	 *
	 * @param mixed  $value Amount to normalize.
	 * @param string $label Human-readable amount label for validation errors.
	 * @return string Normalized two-decimal ETB amount.
	 * @throws InvalidArgumentException When the amount is negative.
	 */
	private static function amount( $value, $label ) {
		$amount = Stockland_Money::decimal( Stockland_Money::minor( $value ) );
		if ( Stockland_Money::minor( $amount ) < 0 ) {
			throw new InvalidArgumentException( esc_html( $label ) . ' cannot be negative.' );
		}
		return $amount;
	}
}
