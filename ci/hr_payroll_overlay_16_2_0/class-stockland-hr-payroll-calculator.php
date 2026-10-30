<?php
/**
 * Stockland candidate payroll calculation service; approved legal rules required.
 *
 * This file is not wired into the production ERP and is not a statutory calculator release.
 * It requires a reviewed, versioned, effective-dated rule bundle. Test-only bundles are
 * accepted solely when the caller explicitly enables synthetic tests; every result carries
 * a synthetic_only marker and must never be used for live payroll.
 */
final class Stockland_HR_Payroll_Calculator {
	const RULE_STATUS_APPROVED = 'APPROVED';
	const RULE_STATUS_SYNTHETIC = 'SYNTHETIC_TEST_ONLY';
	const RATE_SCALE = 1000000;

	private static function fail( $code, $message ) {
		// Expose a safe machine-readable error code only; never echo rule or payroll input details.
		unset( $message );
		if ( ! is_string( $code ) || 1 !== preg_match( '/^[a-z0-9_]{3,64}$/D', $code ) ) {
			$code = 'validation_failed';
		}
		throw new InvalidArgumentException( $code . ': Payroll calculation validation failed.' );
	}

	private static function is_money_string( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/D', $value );
	}

	private static function money_minor( $value, $field ) {
		if ( ! self::is_money_string( $value ) ) {
			self::fail( 'invalid_money', $field . ' must be a non-negative decimal string with at most two decimal places; floats and implicit rounding are rejected' );
		}
		$parts = explode( '.', $value, 2 );
		$whole = $parts[0];
		$frac = isset( $parts[1] ) ? str_pad( $parts[1], 2, '0' ) : '00';
		if ( strlen( $whole ) > 10 ) {
			self::fail( 'money_overflow', $field . ' exceeds the supported bound' );
		}
		return ( (int) $whole * 100 ) + (int) $frac;
	}

	private static function minor_money( $minor ) {
		if ( ! is_int( $minor ) || $minor < 0 ) {
			self::fail( 'invalid_internal_amount', 'amount must be a non-negative integer number of minor units' );
		}
		return intdiv( $minor, 100 ) . '.' . str_pad( (string) ( $minor % 100 ), 2, '0', STR_PAD_LEFT );
	}

	private static function rate_micro( $value, $field ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(0|1)(\.[0-9]{1,6})?$/D', $value ) ) {
			self::fail( 'invalid_rate', $field . ' must be a decimal fraction from 0 through 1, with at most six decimal places' );
		}
		$parts = explode( '.', $value, 2 );
		$fraction = isset( $parts[1] ) ? str_pad( $parts[1], 6, '0' ) : '000000';
		return ( (int) $parts[0] * self::RATE_SCALE ) + (int) $fraction;
	}

	private static function rounded_product( $minor, $rate_micro ) {
		if ( $minor < 0 || $rate_micro < 0 || $rate_micro > self::RATE_SCALE ) {
			self::fail( 'invalid_rate_product', 'invalid amount/rate pair' );
		}
		// Round to the nearest minor unit using half-up integer arithmetic.
		return intdiv( ( $minor * $rate_micro ) + intdiv( self::RATE_SCALE, 2 ), self::RATE_SCALE );
	}

	private static function assert_rule_bundle( $rules, $period_date, $allow_synthetic ) {
		if ( ! is_array( $rules ) ) {
			self::fail( 'rule_bundle_missing', 'a versioned legal-rule bundle is required; no hardcoded statutory fallback exists' );
		}
		$status = isset( $rules['status'] ) ? $rules['status'] : '';
		$synthetic = self::RULE_STATUS_SYNTHETIC === $status;
		if ( $synthetic ) {
			if ( true !== $allow_synthetic || true !== ( $rules['synthetic_only'] ?? false ) ) {
				self::fail( 'synthetic_rules_blocked', 'synthetic test rules may only be used by an explicit isolated-test caller' );
			}
		} elseif ( self::RULE_STATUS_APPROVED !== $status ) {
			self::fail( 'rule_not_approved', 'rule bundle is not approved; final payroll calculation is blocked' );
		}
		foreach ( array( 'rule_version', 'jurisdiction', 'effective_from', 'source_sha256', 'reviewer_id', 'approved_at', 'paye_tax_base_method', 'paye_bands', 'employee_pension_rate', 'employer_pension_rate', 'pensionable_base_method', 'component_treatments', 'rounding_method' ) as $required ) {
			if ( ! array_key_exists( $required, $rules ) || null === $rules[ $required ] || '' === $rules[ $required ] ) {
				self::fail( 'rule_bundle_incomplete', 'required rule field missing: ' . $required );
			}
		}
		if ( ! is_string( $rules['rule_version'] ) || '' === trim( $rules['rule_version'] ) || ! is_string( $rules['jurisdiction'] ) || '' === trim( $rules['jurisdiction'] ) ) {
			self::fail( 'rule_identity_invalid', 'rule version and jurisdiction must be non-empty strings' );
		}
		if ( ! preg_match( '/^[a-f0-9]{64}$/i', (string) $rules['source_sha256'] ) ) {
			self::fail( 'rule_source_hash_invalid', 'rule bundle must cite a SHA-256 source hash' );
		}
		if ( ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', (string) $rules['effective_from'] ) || ( isset( $rules['effective_to'] ) && null !== $rules['effective_to'] && ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', (string) $rules['effective_to'] ) ) ) {
			self::fail( 'rule_dates_invalid', 'rule effective dates must be ISO calendar dates' );
		}
		if ( $period_date < $rules['effective_from'] || ( isset( $rules['effective_to'] ) && null !== $rules['effective_to'] && $period_date > $rules['effective_to'] ) ) {
			self::fail( 'rule_out_of_date', 'no approved rule is effective for the requested payroll date' );
		}
		if ( self::RULE_STATUS_APPROVED === $status && ( empty( $rules['reviewer_id'] ) || empty( $rules['approved_at'] ) || empty( $rules['official_source_url'] ) || empty( $rules['approval_evidence_ref'] ) ) ) {
			self::fail( 'rule_approval_evidence_missing', 'approved rule requires reviewer, approval time, official source and approval evidence reference' );
		}
		if ( ! in_array( $rules['paye_tax_base_method'], array( 'TAXABLE_EARNINGS', 'TAXABLE_EARNINGS_LESS_EMPLOYEE_PENSION' ), true ) ) {
			self::fail( 'tax_base_method_unsupported', 'tax base method is not supported and must be reviewed before calculation' );
		}
		if ( ! in_array( $rules['pensionable_base_method'], array( 'SUM_PENSIONABLE_ITEMS' ), true ) ) {
			self::fail( 'pension_base_method_unsupported', 'pensionable base method is not supported and must be reviewed before calculation' );
		}
		if ( 'HALF_UP_MINOR_UNIT' !== $rules['rounding_method'] ) {
			self::fail( 'rounding_method_unsupported', 'only the explicitly specified integer half-up minor-unit method is implemented in this candidate' );
		}
		self::rate_micro( $rules['employee_pension_rate'], 'employee_pension_rate' );
		self::rate_micro( $rules['employer_pension_rate'], 'employer_pension_rate' );
		if ( ! is_array( $rules['component_treatments'] ) || ! $rules['component_treatments'] ) {
			self::fail( 'component_treatment_missing', 'component treatment dictionary is required' );
		}
		if ( ! is_array( $rules['paye_bands'] ) || ! $rules['paye_bands'] ) {
			self::fail( 'paye_bands_missing', 'a progressive band schedule is required; there is no default tax schedule' );
		}
		$last_cap = 0;
		$unbounded_seen = false;
		foreach ( $rules['paye_bands'] as $i => $band ) {
			if ( ! is_array( $band ) || ! isset( $band['rate'] ) || ! array_key_exists( 'up_to', $band ) ) {
				self::fail( 'paye_band_invalid', 'each progressive band must declare rate and up_to (null only for final unbounded band)' );
			}
			$rate = self::rate_micro( $band['rate'], 'paye_bands[' . $i . '].rate' );
			if ( $unbounded_seen ) {
				self::fail( 'paye_band_order_invalid', 'no bands may follow an unbounded final band' );
			}
			if ( null === $band['up_to'] ) {
				$unbounded_seen = true;
			} else {
				$cap = self::money_minor( $band['up_to'], 'paye_bands[' . $i . '].up_to' );
				if ( $cap <= $last_cap ) {
					self::fail( 'paye_band_order_invalid', 'band upper limits must be strictly increasing' );
				}
				$last_cap = $cap;
			}
		}
		if ( ! $unbounded_seen ) {
			self::fail( 'paye_bands_incomplete', 'the final band must be unbounded or explicitly cover every input' );
		}
		foreach ( $rules['component_treatments'] as $code => $treatment ) {
			if ( ! is_string( $code ) || '' === $code || ! is_array( $treatment ) || ! isset( $treatment['kind'], $treatment['taxable'], $treatment['pensionable'] ) ) {
				self::fail( 'component_treatment_invalid', 'each component requires kind, taxable and pensionable treatment' );
			}
			if ( ! in_array( $treatment['kind'], array( 'EARNING', 'DEDUCTION', 'REIMBURSEMENT', 'EMPLOYER_COST' ), true ) || ! is_bool( $treatment['taxable'] ) || ! is_bool( $treatment['pensionable'] ) ) {
				self::fail( 'component_treatment_invalid', 'component treatment contains an unsupported kind or a non-boolean flag' );
			}
			if ( 'EARNING' !== $treatment['kind'] && ( true === $treatment['taxable'] || true === $treatment['pensionable'] ) ) {
				self::fail( 'component_treatment_conflict', 'deductions, reimbursements and employer-cost components cannot be marked taxable/pensionable by this calculation model' );
			}
		}
		return $synthetic;
	}

	private static function progressive_tax( $taxable_minor, $bands ) {
		$tax = 0;
		$previous_cap = 0;
		$trace = array();
		foreach ( $bands as $i => $band ) {
			$cap = null === $band['up_to'] ? null : self::money_minor( $band['up_to'], 'paye band upper limit' );
			$segment_end = null === $cap ? $taxable_minor : min( $taxable_minor, $cap );
			$segment = max( 0, $segment_end - $previous_cap );
			$rate = self::rate_micro( $band['rate'], 'paye band rate' );
			$segment_tax = self::rounded_product( $segment, $rate );
			$tax += $segment_tax;
			$trace[] = array(
				'band_index' => $i + 1,
				'lower_exclusive_minor' => $previous_cap,
				'upper_minor' => $cap,
				'taxable_segment' => self::minor_money( $segment ),
				'rate_fraction' => $band['rate'],
				'tax_amount' => self::minor_money( $segment_tax ),
			);
			if ( null !== $cap ) {
				$previous_cap = $cap;
			}
			if ( null !== $cap && $taxable_minor <= $cap ) {
				break;
			}
		}
		return array( $tax, $trace );
	}

	/**
	 * Calculate one synthetic/reviewed payroll line from approved inputs.
	 *
	 * $input = ['employee_ref'=>'masked/test reference', 'period_date'=>'YYYY-MM-DD',
	 *           'items'=>[['code'=>'BASE','amount'=>'100.00','source_ref'=>'contract-approval-id']]]
	 * $rules must be a reviewed effective-dated bundle. Never call this with unapproved rules in production.
	 *
	 * @param array $input Payroll period date and itemized approved inputs.
	 * @param array $rules Effective-dated legal rule bundle.
	 * @param bool  $allow_synthetic_test_rules Explicit test-only switch; false by default.
	 * @return array Itemized result and calculation trace.
	 */
	public static function calculate( $input, $rules, $allow_synthetic_test_rules = false ) {
		if ( ! is_array( $input ) || ! isset( $input['period_date'], $input['items'] ) || ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', (string) $input['period_date'] ) || ! is_array( $input['items'] ) || ! $input['items'] ) {
			self::fail( 'input_invalid', 'period_date and a non-empty itemized inputs list are required' );
		}
		$synthetic = self::assert_rule_bundle( $rules, (string) $input['period_date'], $allow_synthetic_test_rules );
		$sum = array( 'earnings' => 0, 'taxable_earnings' => 0, 'pensionable_earnings' => 0, 'deductions' => 0, 'reimbursements' => 0, 'employer_costs' => 0 );
		$seen_sources = array();
		$line_trace = array();
		foreach ( $input['items'] as $index => $item ) {
			if ( ! is_array( $item ) || ! isset( $item['code'], $item['amount'], $item['source_ref'] ) || ! is_string( $item['code'] ) || '' === $item['code'] || ! is_string( $item['source_ref'] ) || '' === trim( $item['source_ref'] ) ) {
				self::fail( 'input_item_invalid', 'each item must provide code, amount and non-empty source reference' );
			}
			if ( isset( $seen_sources[ $item['source_ref'] ] ) ) {
				self::fail( 'duplicate_input_source', 'duplicate source reference found in the same frozen input snapshot' );
			}
			$seen_sources[ $item['source_ref'] ] = true;
			if ( ! isset( $rules['component_treatments'][ $item['code'] ] ) ) {
				self::fail( 'component_unknown', 'no approved component treatment for code ' . $item['code'] );
			}
			$minor = self::money_minor( $item['amount'], 'items[' . $index . '].amount' );
			$treatment = $rules['component_treatments'][ $item['code'] ];
			$trace = array( 'code' => $item['code'], 'source_ref' => $item['source_ref'], 'amount' => self::minor_money( $minor ), 'kind' => $treatment['kind'], 'taxable' => $treatment['taxable'], 'pensionable' => $treatment['pensionable'] );
			switch ( $treatment['kind'] ) {
				case 'EARNING':
					$sum['earnings'] += $minor;
					if ( $treatment['taxable'] ) {
						$sum['taxable_earnings'] += $minor;
					}
					if ( $treatment['pensionable'] ) {
						$sum['pensionable_earnings'] += $minor;
					}
					break;
				case 'DEDUCTION':
					$sum['deductions'] += $minor;
					break;
				case 'REIMBURSEMENT':
					$sum['reimbursements'] += $minor;
					break;
				case 'EMPLOYER_COST':
					$sum['employer_costs'] += $minor;
					break;
			}
			$line_trace[] = $trace;
		}
		$employee_rate = self::rate_micro( $rules['employee_pension_rate'], 'employee_pension_rate' );
		$employer_rate = self::rate_micro( $rules['employer_pension_rate'], 'employer_pension_rate' );
		$employee_pension = self::rounded_product( $sum['pensionable_earnings'], $employee_rate );
		$employer_pension = self::rounded_product( $sum['pensionable_earnings'], $employer_rate );
		$tax_base = $sum['taxable_earnings'];
		if ( 'TAXABLE_EARNINGS_LESS_EMPLOYEE_PENSION' === $rules['paye_tax_base_method'] ) {
			$tax_base = max( 0, $tax_base - $employee_pension );
		}
		list( $income_tax, $tax_trace ) = self::progressive_tax( $tax_base, $rules['paye_bands'] );
		$deductions_total = $sum['deductions'] + $employee_pension + $income_tax;
		$net_pay = $sum['earnings'] + $sum['reimbursements'] - $deductions_total;
		$employer_total = $sum['earnings'] + $sum['reimbursements'] + $sum['employer_costs'] + $employer_pension;
		if ( $net_pay < 0 ) {
			self::fail( 'negative_net_pay', 'calculated net pay would be negative; review inputs and approved deductions' );
		}
		$totals = array(
			'earnings' => self::minor_money( $sum['earnings'] ),
			'taxable_earnings' => self::minor_money( $sum['taxable_earnings'] ),
			'pensionable_earnings' => self::minor_money( $sum['pensionable_earnings'] ),
			'reimbursements' => self::minor_money( $sum['reimbursements'] ),
			'other_deductions' => self::minor_money( $sum['deductions'] ),
			'employee_pension' => self::minor_money( $employee_pension ),
			'employer_pension' => self::minor_money( $employer_pension ),
			'payable_income_tax' => self::minor_money( $income_tax ),
			'total_deductions' => self::minor_money( $deductions_total ),
			'net_pay' => self::minor_money( $net_pay ),
			'employer_other_costs' => self::minor_money( $sum['employer_costs'] ),
			'employer_total_cost' => self::minor_money( $employer_total ),
		);
		return array(
			'status' => $synthetic ? 'SYNTHETIC_TEST_ONLY' : 'CALCULATED_REQUIRES_APPROVAL',
			'synthetic_only' => $synthetic,
			'rule_bundle_status_confirmed' => $rules['status'],
			'employee_ref' => isset( $input['employee_ref'] ) && is_string( $input['employee_ref'] ) ? $input['employee_ref'] : null,
			'period_date' => $input['period_date'],
			'rule_version' => $rules['rule_version'],
			'rule_source_sha256' => strtolower( $rules['source_sha256'] ),
			'rule_status' => $rules['status'],
			'calculation_method' => array(
				'rounding_method' => $rules['rounding_method'],
				'tax_base_method' => $rules['paye_tax_base_method'],
				'pensionable_base_method' => $rules['pensionable_base_method'],
				'employee_pension_rate_fraction' => $rules['employee_pension_rate'],
				'employer_pension_rate_fraction' => $rules['employer_pension_rate'],
			),
			'items' => $line_trace,
			'paye_progressive_band_trace' => $tax_trace,
			'totals' => $totals,
			'posting_allowed' => false,
			'payment_allowed' => false,
			'note' => $synthetic ? 'TOY FIXTURE ONLY — not Ethiopian law, not an approved payroll result, and must never be posted or paid.' : 'Calculated candidate preview only. Posting/payment require separately authorized workflow, exact frozen snapshot, review, legal gates and GL reconciliation.',
		);
	}
}
