<?php
/**
 * Pure, deterministic payroll calculation core for the HR replacement.
 *
 * This class has no database or WordPress side effects. It deliberately contains
 * no default Ethiopian tax bands, pension rates, allowance exemptions or rounding
 * choices. Only an approved, effective-dated, source-fingerprinted rule bundle can
 * be used. Production activation still requires qualified Ethiopian legal review.
 */
if ( ! defined( 'STOCKLAND_HR_CALCULATOR_STANDALONE' ) && ! defined( 'ABSPATH' ) ) {
	// The file can be loaded by the CLI regression harness and by the WordPress plugin.
}

final class Stockland_HR_Payroll_Calculator {
	const TAXABLE_GROSS = 'TAXABLE_GROSS';
	const TAXABLE_GROSS_LESS_EMPLOYEE_PENSION = 'TAXABLE_GROSS_LESS_EMPLOYEE_PENSION';
	const ROUND_PER_BAND = 'PER_BAND_HALF_UP';
	const ROUND_TOTAL = 'TOTAL_HALF_UP';

	/**
	 * Calculate gross-to-net from already-approved item classifications and rules.
	 *
	 * @param array  $items   Itemized earning/reimbursement/deduction inputs; no PII.
	 * @param array  $rule    Approved effective-dated legal rule bundle.
	 * @param string $period_end ISO-8601 earning-period end date.
	 * @return array Explainable result with a deterministic SHA-256.
	 * @throws DomainException For any missing, stale, ambiguous or unapproved input.
	 */
	public static function calculate( array $items, array $rule, $period_end ) {
		self::validate_rule( $rule, (string) $period_end );
		if ( ! self::valid_date( (string) $period_end ) ) {
			throw new DomainException( 'Payroll period end must be an ISO calendar date.' );
		}
		if ( ! $items ) {
			throw new DomainException( 'At least one approved payroll input item is required.' );
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				throw new DomainException( 'Every payroll input must be a structured item.' );
			}
		}
		usort(
			$items,
			static function ( $left, $right ) {
				$code_order = strcmp( strtoupper( (string) ( $left['item_code'] ?? '' ) ), strtoupper( (string) ( $right['item_code'] ?? '' ) ) );
				return 0 !== $code_order ? $code_order : strcmp( (string) ( $left['input_reference'] ?? '' ), (string) ( $right['input_reference'] ?? '' ) );
			}
		);

		$seen_sources = array();
		$calculated   = array();
		$earnings     = 0;
		$reimbursements = 0;
		$taxable_gross = 0;
		$exempt_earnings = 0;
		$pensionable_base = 0;
		$other_deductions = 0;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				throw new DomainException( 'Every payroll input must be a structured item.' );
			}
			$code = strtoupper( trim( (string) ( $item['item_code'] ?? '' ) ) );
			if ( ! preg_match( '/^[A-Z][A-Z0-9_]{1,63}$/', $code ) || ! isset( $rule['components'][ $code ] ) ) {
				throw new DomainException( 'Unknown payroll item code; legal classification review is required.' );
			}
			$input_ref = trim( (string) ( $item['input_reference'] ?? '' ) );
			$source_ref = trim( (string) ( $item['source_reference'] ?? '' ) );
			if ( '' === $input_ref || isset( $seen_sources[ $input_ref ] ) ) {
				throw new DomainException( 'Every payroll item needs a unique input reference to prevent duplicate inclusion.' );
			}
			if ( '' === $source_ref ) {
				throw new DomainException( 'Every payroll item needs a source reference for traceability.' );
			}
			$seen_sources[ $input_ref ] = true;
			$amount = self::money_to_minor( $item['amount'] ?? null );
			if ( $amount <= 0 ) {
				throw new DomainException( 'Payroll input amounts must be positive; submit a separately approved correction for negative adjustments.' );
			}
			$classification = $rule['components'][ $code ];
			self::validate_component_rule( $code, $classification );
			$kind = $classification['kind'];
			$tax_treatment = $classification['tax_treatment'];
			$pension_treatment = $classification['pension_treatment'];
			$evidence = trim( (string) ( $item['evidence_reference'] ?? '' ) );
			$basis = trim( (string) ( $item['basis_reference'] ?? '' ) );
			if ( ! empty( $classification['evidence_required'] ) && '' === $evidence ) {
				throw new DomainException( 'Required payroll evidence is missing for ' . $code . '.' );
			}
			if ( ( 'EXEMPT' === $tax_treatment || 'NON_TAXABLE_REIMBURSEMENT' === $tax_treatment ) && ( '' === $basis || '' === $evidence ) ) {
				throw new DomainException( 'Exempt/non-taxable treatment needs an approved rule basis and evidence reference for ' . $code . '.' );
			}
			if ( 'DEDUCTION' === $kind && '' === $basis ) {
				throw new DomainException( 'A deduction requires a documented legal or contractual basis.' );
			}

			if ( 'EARNING' === $kind ) {
				$earnings = self::safe_add_minor( $earnings, $amount );
				if ( 'TAXABLE' === $tax_treatment ) {
					$taxable_gross = self::safe_add_minor( $taxable_gross, $amount );
				} elseif ( 'EXEMPT' === $tax_treatment ) {
					$exempt_earnings = self::safe_add_minor( $exempt_earnings, $amount );
				}
				if ( 'PENSIONABLE' === $pension_treatment ) {
					$pensionable_base = self::safe_add_minor( $pensionable_base, $amount );
				}
			} elseif ( 'REIMBURSEMENT' === $kind ) {
				$reimbursements = self::safe_add_minor( $reimbursements, $amount );
				if ( 'TAXABLE' === $tax_treatment ) {
					$taxable_gross = self::safe_add_minor( $taxable_gross, $amount );
				}
				if ( 'PENSIONABLE' === $pension_treatment ) {
					$pensionable_base = self::safe_add_minor( $pensionable_base, $amount );
				}
			} elseif ( 'DEDUCTION' === $kind ) {
				$other_deductions = self::safe_add_minor( $other_deductions, $amount );
			} else {
				throw new DomainException( 'Unsupported component kind.' );
			}

			$calculated[] = array(
				'item_code' => $code,
				'kind' => $kind,
				'amount' => self::minor_to_money( $amount ),
				'tax_treatment' => $tax_treatment,
				'pension_treatment' => $pension_treatment,
				'rule_version' => (string) $rule['version'],
				'classification_source_reference' => (string) $classification['source_reference'],
				'classification_source_sha256' => strtolower( (string) $classification['source_sha256'] ),
				'input_reference' => $input_ref,
				'source_reference' => $source_ref,
				'evidence_reference' => $evidence,
				'basis_reference' => $basis,
			);
		}

		$employee_pension = self::apply_rate( $pensionable_base, (int) $rule['employee_pension_rate_bps'] );
		$employer_pension = self::apply_rate( $pensionable_base, (int) $rule['employer_pension_rate_bps'] );
		$tax_base = $taxable_gross;
		if ( self::TAXABLE_GROSS_LESS_EMPLOYEE_PENSION === $rule['taxable_base_mode'] ) {
			$tax_base = max( 0, $taxable_gross - $employee_pension );
		}
		$tax_breakdown = self::calculate_tax( $tax_base, $rule['tax_bands'], $rule['tax_rounding_mode'] );
		$income_tax = $tax_breakdown['tax_minor'];
		$cash_due = self::safe_add_minor( $earnings, $reimbursements );
		$deductions_due = self::safe_add_minor( self::safe_add_minor( $income_tax, $employee_pension ), $other_deductions );
		$net_payable = $cash_due - $deductions_due;
		if ( $net_payable < 0 ) {
			throw new DomainException( 'Calculated net pay is negative; payroll must be held for review.' );
		}
		$employer_cost = self::safe_add_minor( self::safe_add_minor( $earnings, $reimbursements ), $employer_pension );

		$result = array(
			'period_end' => (string) $period_end,
			'rule_version' => (string) $rule['version'],
			'rule_source_sha256' => strtolower( (string) $rule['source_sha256'] ),
			'rule_source_reference' => (string) $rule['source_reference'],
			'rule_approval_reference' => (string) $rule['approval_reference'],
			'rule_reviewer_id' => (int) $rule['reviewer_id'],
			'rule_reviewed_at' => (string) $rule['reviewed_at'],
			'taxable_base_mode' => (string) $rule['taxable_base_mode'],
			'rounding_mode' => (string) $rule['tax_rounding_mode'],
			'items' => $calculated,
			'totals' => array(
				'earnings' => self::minor_to_money( $earnings ),
				'reimbursements' => self::minor_to_money( $reimbursements ),
				'exempt_earnings' => self::minor_to_money( $exempt_earnings ),
				'taxable_gross' => self::minor_to_money( $taxable_gross ),
				'taxable_income' => self::minor_to_money( $tax_base ),
				'pensionable_base' => self::minor_to_money( $pensionable_base ),
				'income_tax' => self::minor_to_money( $income_tax ),
				'employee_pension' => self::minor_to_money( $employee_pension ),
				'employer_pension' => self::minor_to_money( $employer_pension ),
				'other_deductions' => self::minor_to_money( $other_deductions ),
				'net_payable' => self::minor_to_money( $net_payable ),
				'employer_cost' => self::minor_to_money( $employer_cost ),
			),
			'tax_band_breakdown' => $tax_breakdown['bands'],
		);
		$result['calculation_sha256'] = hash( 'sha256', self::canonical_json( $result ) );
		return $result;
	}

	private static function validate_rule( array $rule, $as_of ) {
		$required = array( 'version', 'approval_status', 'effective_from', 'effective_to', 'source_reference', 'source_sha256', 'reviewer_id', 'reviewed_at', 'reviewer_qualification', 'approval_reference', 'taxable_base_mode', 'tax_rounding_mode', 'employee_pension_rate_bps', 'employer_pension_rate_bps', 'tax_bands', 'components' );
		foreach ( $required as $key ) {
			if ( ! array_key_exists( $key, $rule ) ) {
				throw new DomainException( 'Approved legal rule bundle is missing ' . $key . '; payroll cannot calculate.' );
			}
		}
		if ( 'APPROVED' !== strtoupper( (string) $rule['approval_status'] ) ) {
			throw new DomainException( 'The statutory rule bundle is not approved; payroll fails closed.' );
		}
		if ( '' === trim( (string) $rule['version'] ) || '' === trim( (string) $rule['source_reference'] ) || ! preg_match( '/^[a-f0-9]{64}$/i', (string) $rule['source_sha256'] ) ) {
			throw new DomainException( 'The rule version/source reference/hash is invalid.' );
		}
		if ( (int) $rule['reviewer_id'] <= 0 || '' === trim( (string) $rule['reviewed_at'] ) || 'QUALIFIED_ET_TAX_PENSION_REVIEWER' !== strtoupper( (string) $rule['reviewer_qualification'] ) || '' === trim( (string) $rule['approval_reference'] ) ) {
			throw new DomainException( 'A named qualified reviewer and review timestamp are required before rule activation.' );
		}
		if ( ! self::valid_date( (string) $rule['effective_from'] ) || ( null !== $rule['effective_to'] && '' !== $rule['effective_to'] && ! self::valid_date( (string) $rule['effective_to'] ) ) ) {
			throw new DomainException( 'Rule effective dates are invalid.' );
		}
		if ( (string) $as_of < (string) $rule['effective_from'] || ( null !== $rule['effective_to'] && '' !== $rule['effective_to'] && (string) $as_of > (string) $rule['effective_to'] ) ) {
			throw new DomainException( 'No approved statutory rule version is effective for the selected payroll date.' );
		}
		if ( ! in_array( $rule['taxable_base_mode'], array( self::TAXABLE_GROSS, self::TAXABLE_GROSS_LESS_EMPLOYEE_PENSION ), true ) ) {
			throw new DomainException( 'Unknown tax-base method. Legal review must explicitly approve the base.' );
		}
		if ( ! in_array( $rule['tax_rounding_mode'], array( self::ROUND_PER_BAND, self::ROUND_TOTAL ), true ) ) {
			throw new DomainException( 'Unsupported or unapproved tax rounding method.' );
		}
		foreach ( array( 'employee_pension_rate_bps', 'employer_pension_rate_bps' ) as $rate ) {
			if ( ! is_int( $rule[ $rate ] ) || $rule[ $rate ] < 0 || $rule[ $rate ] > 10000 ) {
				throw new DomainException( 'Pension rates must be integer basis points in the approved rule bundle.' );
			}
		}
		self::validate_bands( $rule['tax_bands'] );
		if ( ! is_array( $rule['components'] ) || ! $rule['components'] ) {
			throw new DomainException( 'The approved rule bundle has no classified compensation components.' );
		}
	}

	private static function validate_bands( $bands ) {
		if ( ! is_array( $bands ) || ! $bands ) {
			throw new DomainException( 'Approved marginal PAYE bands are required.' );
		}
		$expected_lower = 0;
		$last = count( $bands ) - 1;
		foreach ( array_values( $bands ) as $index => $band ) {
			if ( ! is_array( $band ) || ! isset( $band['lower_minor'], $band['rate_bps'] ) || ! is_int( $band['lower_minor'] ) || ! is_int( $band['rate_bps'] ) || $band['lower_minor'] !== $expected_lower || $band['rate_bps'] < 0 || $band['rate_bps'] > 10000 ) {
				throw new DomainException( 'PAYE bands must be valid, ordered and contiguous from zero.' );
			}
			$upper = $band['upper_minor'] ?? null;
			if ( $index === $last ) {
				if ( null !== $upper ) {
					throw new DomainException( 'The final PAYE band must be open-ended.' );
				}
			} else {
				if ( ! is_int( $upper ) || $upper <= $expected_lower ) {
					throw new DomainException( 'Every non-final PAYE band needs a greater upper limit.' );
				}
				$expected_lower = $upper;
			}
		}
	}

	private static function validate_component_rule( $code, $component ) {
		if ( ! is_array( $component ) || 'APPROVED' !== strtoupper( (string) ( $component['classification_status'] ?? '' ) ) ) {
			throw new DomainException( 'Payroll item ' . $code . ' has no approved classification.' );
		}
		$kind = $component['kind'] ?? '';
		if ( ! in_array( $kind, array( 'EARNING', 'REIMBURSEMENT', 'DEDUCTION' ), true ) ) {
			throw new DomainException( 'Payroll item ' . $code . ' has an unsupported kind.' );
		}
		if ( ! in_array( $component['tax_treatment'] ?? '', array( 'TAXABLE', 'EXEMPT', 'NON_TAXABLE_REIMBURSEMENT', 'NOT_APPLICABLE' ), true ) ) {
			throw new DomainException( 'Payroll item ' . $code . ' has unresolved tax treatment.' );
		}
		if ( 'LEGAL_REVIEW' === ( $component['tax_treatment'] ?? '' ) || 'LEGAL_REVIEW' === ( $component['pension_treatment'] ?? '' ) ) {
			throw new DomainException( 'Payroll item ' . $code . ' is held for legal/tax review.' );
		}
		if ( ! in_array( $component['pension_treatment'] ?? '', array( 'PENSIONABLE', 'NON_PENSIONABLE', 'NOT_APPLICABLE' ), true ) ) {
			throw new DomainException( 'Payroll item ' . $code . ' has unresolved pension treatment.' );
		}
		if ( ! isset( $component['source_reference'] ) || '' === trim( (string) $component['source_reference'] ) || ! isset( $component['source_sha256'] ) || ! preg_match( '/^[a-f0-9]{64}$/i', (string) $component['source_sha256'] ) ) {
			throw new DomainException( 'Payroll item ' . $code . ' has no source rule reference/hash.' );
		}
		if ( 'DEDUCTION' === $kind && ( 'NOT_APPLICABLE' !== $component['tax_treatment'] || 'NOT_APPLICABLE' !== $component['pension_treatment'] ) ) {
			throw new DomainException( 'Deductions cannot choose wage tax or pension treatment; the rule bundle must define them as not applicable.' );
		}
		if ( 'REIMBURSEMENT' === $kind && ! in_array( $component['tax_treatment'], array( 'TAXABLE', 'NON_TAXABLE_REIMBURSEMENT' ), true ) ) {
			throw new DomainException( 'A reimbursement must be classified as taxable or specifically approved non-taxable reimbursement.' );
		}
		if ( 'EARNING' === $kind && ! in_array( $component['tax_treatment'], array( 'TAXABLE', 'EXEMPT' ), true ) ) {
			throw new DomainException( 'An earning must be approved taxable or exempt compensation.' );
		}
		if ( 'REIMBURSEMENT' === $kind && 'PENSIONABLE' === $component['pension_treatment'] ) {
			throw new DomainException( 'Reimbursement cannot be pensionable without a separately modeled approved treatment.' );
		}
	}

	private static function calculate_tax( $taxable_minor, array $bands, $rounding_mode ) {
		$numerator_total = 0;
		$tax_total = 0;
		$breakdown = array();
		foreach ( $bands as $band ) {
			$lower = $band['lower_minor'];
			$upper = $band['upper_minor'] ?? null;
			if ( $taxable_minor <= $lower ) {
				$slice = 0;
			} else {
				$limit = null === $upper ? $taxable_minor : min( $taxable_minor, $upper );
				$slice = max( 0, $limit - $lower );
			}
			$numerator = $slice * $band['rate_bps'];
			$numerator_total = self::safe_add_integer( $numerator_total, $numerator );
			$band_tax = self::ROUND_TOTAL === $rounding_mode ? intdiv( $numerator, 10000 ) : self::round_ratio( $numerator, 10000 );
			if ( self::ROUND_PER_BAND === $rounding_mode ) {
				$tax_total = self::safe_add_minor( $tax_total, $band_tax );
			}
			$breakdown[] = array(
				'lower_minor' => $lower,
				'upper_minor' => $upper,
				'rate_bps' => $band['rate_bps'],
				'taxable_slice' => self::minor_to_money( $slice ),
				'tax_minor' => $band_tax,
				'remainder' => $numerator % 10000,
			);
		}
		if ( self::ROUND_TOTAL === $rounding_mode ) {
			$tax_total = self::round_ratio( $numerator_total, 10000 );
			$floor_total = 0;
			$remainder_order = array();
			foreach ( $breakdown as $index => $row ) {
				$floor_total = self::safe_add_minor( $floor_total, (int) $row['tax_minor'] );
				$remainder_order[] = array( 'index'=>$index, 'remainder'=>(int) $row['remainder'] );
			}
			$remaining = $tax_total - $floor_total;
			if ( $remaining < 0 || $remaining > count( $remainder_order ) ) {
				throw new DomainException( 'Aggregate tax rounding could not be allocated to the band breakdown.' );
			}
			usort( $remainder_order, static function ( $left, $right ) {
				if ( $left['remainder'] === $right['remainder'] ) { return $left['index'] <=> $right['index']; }
				return $right['remainder'] <=> $left['remainder'];
			} );
			for ( $i = 0; $i < $remaining; $i++ ) {
				$index = $remainder_order[$i]['index'];
				$breakdown[$index]['tax_minor'] = self::safe_add_minor( (int) $breakdown[$index]['tax_minor'], 1 );
			}
		}
		foreach ( $breakdown as &$row ) {
			$row['tax'] = self::minor_to_money( (int) $row['tax_minor'] );
			unset( $row['tax_minor'], $row['remainder'] );
		}
		unset( $row );
		return array( 'tax_minor' => $tax_total, 'bands' => $breakdown );
	}

	private static function apply_rate( $base_minor, $rate_bps ) {
		return self::round_ratio( $base_minor * $rate_bps, 10000 );
	}

	private static function round_ratio( $numerator, $denominator ) {
		if ( $numerator < 0 || $denominator <= 0 ) {
			throw new DomainException( 'Invalid rounding input.' );
		}
		return intdiv( $numerator + intdiv( $denominator, 2 ), $denominator );
	}

	private static function money_to_minor( $amount ) {
		if ( ! is_string( $amount ) && ! is_int( $amount ) ) {
			throw new DomainException( 'Money must be provided as a decimal string or integer; floating-point currency is rejected.' );
		}
		$value = trim( (string) $amount );
		if ( ! preg_match( '/^(?:0|[1-9][0-9]*)(?:\\.([0-9]{1,2}))?$/', $value ) ) {
			throw new DomainException( 'Invalid non-negative money amount.' );
		}
		$parts = explode( '.', $value, 2 );
		$fraction = isset( $parts[1] ) ? str_pad( $parts[1], 2, '0' ) : '00';
		if ( strlen( $parts[0] ) > 15 ) {
			throw new DomainException( 'Money amount exceeds the supported calculation range.' );
		}
		$whole = (int) $parts[0];
		if ( $whole > intdiv( self::max_safe_minor() - 99, 100 ) ) {
			throw new DomainException( 'Money amount exceeds the safe calculation range.' );
		}
		$minor = $whole * 100 + (int) $fraction;
		if ( $minor > self::max_safe_minor() ) {
			throw new DomainException( 'Money amount exceeds the safe calculation range.' );
		}
		return $minor;
	}

	private static function max_safe_minor() {
		return intdiv( PHP_INT_MAX - 10000, 10000 );
	}

	private static function safe_add_minor( $left, $right ) {
		$limit = self::max_safe_minor();
		if ( $left < 0 || $right < 0 || $left > $limit - $right ) {
			throw new DomainException( 'Monetary total exceeds safe calculation bounds.' );
		}
		return $left + $right;
	}

	private static function safe_add_integer( $left, $right ) {
		if ( $left < 0 || $right < 0 || $left > PHP_INT_MAX - $right ) {
			throw new DomainException( 'Calculation precision exceeds safe integer bounds.' );
		}
		return $left + $right;
	}

	private static function minor_to_money( $minor ) {
		return sprintf( '%d.%02d', intdiv( $minor, 100 ), $minor % 100 );
	}

	private static function valid_date( $date ) {
		$d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		return $d instanceof \DateTimeImmutable && $d->format( 'Y-m-d' ) === $date;
	}

	private static function canonical_json( array $data ) {
		try {
			return json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			throw new DomainException( 'Payroll output could not be canonically serialized for its audit hash.' );
		}
	}
}
