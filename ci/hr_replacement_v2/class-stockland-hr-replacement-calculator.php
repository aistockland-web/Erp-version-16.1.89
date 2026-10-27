<?php
/**
 * Deterministic rule-driven HR/payroll arithmetic. Contains no Ethiopia rates or legal assumptions.
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Pure calculation helpers; callers must supply effective-dated rules that passed legal approval. */
final class Stockland_HR_Replacement_Calculator {
	const RATE_SCALE = 1000000;

	private static function fail( $code, $message ) { return new WP_Error( $code, $message, array( 'status' => 422 ) ); }

	private static function rate_units( $value ) {
		$s = trim( (string) $value );
		if ( ! preg_match( '/^(0|1)(?:\.(\d{1,6}))?$/', $s, $m ) ) { throw new InvalidArgumentException( 'Rate must be an unsigned decimal between 0 and 1 with at most six decimal places.' ); }
		$whole = (int) $m[1];
		$fraction = isset( $m[2] ) ? str_pad( $m[2], 6, '0', STR_PAD_RIGHT ) : '000000';
		$units = ( $whole * self::RATE_SCALE ) + (int) $fraction;
		if ( $units < 0 || $units > self::RATE_SCALE ) { throw new InvalidArgumentException( 'Rate is outside 0..1.' ); }
		return $units;
	}

	private static function round_div( $numerator, $denominator ) {
		if ( $denominator <= 0 ) { throw new InvalidArgumentException( 'Invalid calculation divisor.' ); }
		$negative = $numerator < 0;
		$n = abs( (int) $numerator );
		$q = intdiv( $n, $denominator );
		$r = $n % $denominator;
		if ( $r * 2 >= $denominator ) { ++$q; }
		return $negative ? -$q : $q;
	}

	/** Apply a versioned percentage to an amount in ETB with explicit cent rounding. */
	public static function percentage( $amount, $rate ) {
		$minor = Stockland_Money::minor( $amount );
		$units = self::rate_units( $rate );
		return Stockland_Money::decimal( self::round_div( $minor * $units, self::RATE_SCALE ) );
	}

	/**
	 * Progressive marginal-band PAYE calculation. Bands are half-open [lower, upper), last upper is null.
	 * All bounds/rates must come from an APPROVED, effective-dated legal rule.
	 */
	public static function progressive_tax( $taxable_base, $bands ) {
		$income = Stockland_Money::minor( $taxable_base );
		if ( $income < 0 || ! is_array( $bands ) || empty( $bands ) ) { return self::fail( 'hrx_tax_input_invalid', 'A non-negative taxable base and approved progressive tax bands are required.' ); }
		usort( $bands, static function ( $a, $b ) { return (int) ( $a['band_order'] ?? 0 ) <=> (int) ( $b['band_order'] ?? 0 ); } );
		$expected_lower = 0;
		$tax_numerator = 0;
		$seen_unbounded = false;
		foreach ( $bands as $i => $band ) {
			if ( ! isset( $band['lower_bound'], $band['rate'], $band['band_order'] ) ) { return self::fail( 'hrx_tax_band_invalid', 'A tax band is missing a required bound, order or rate.' ); }
			$lower = Stockland_Money::minor( $band['lower_bound'] );
			if ( $lower !== $expected_lower ) { return self::fail( 'hrx_tax_band_gap', 'Approved tax bands are not contiguous from zero; calculation is blocked.' ); }
			try { $rate_units = self::rate_units( $band['rate'] ); } catch ( Throwable $e ) { return self::fail( 'hrx_tax_band_rate_invalid', 'An approved tax band has an invalid rate.' ); }
			$upper_value = $band['upper_bound'] ?? null;
			if ( null === $upper_value || '' === $upper_value ) {
				if ( $i !== count( $bands ) - 1 ) { return self::fail( 'hrx_tax_band_unbounded', 'Only the final tax band may be unbounded.' ); }
				$seen_unbounded = true;
				$upper = null;
			} else {
				$upper = Stockland_Money::minor( $upper_value );
				if ( $upper <= $lower ) { return self::fail( 'hrx_tax_band_order', 'A tax band upper bound must exceed its lower bound.' ); }
				$expected_lower = $upper;
			}
			$taxable_segment = max( 0, min( $income, null === $upper ? $income : $upper ) - $lower );
			$tax_numerator += $taxable_segment * $rate_units;
		}
		if ( ! $seen_unbounded ) { return self::fail( 'hrx_tax_band_unbounded_required', 'The last approved tax band must cover all higher taxable income.' ); }
		return array( 'tax' => Stockland_Money::decimal( self::round_div( $tax_numerator, self::RATE_SCALE ) ), 'taxable_base' => Stockland_Money::decimal( $income ), 'rounding' => 'HALF_AWAY_FROM_ZERO_CENT', 'band_count' => count( $bands ) );
	}

	private static function sum_minor( $items ) {
		$total = 0;
		foreach ( $items as $item ) { $total += Stockland_Money::minor( $item ); }
		return Stockland_Money::decimal( $total );
	}

	/**
	 * Gross-to-net calculator. The caller supplies itemized frozen inputs and approved rule records.
	 *
	 * Expected rules: base_salary_classification params, PAYE_BASE formula_key, PAYE bands,
	 * EMPLOYEE_PENSION rate, EMPLOYER_PENSION rate, all already scoped to the whole period.
	 */
	public static function payroll_line( $input, $rules ) {
		$required_rules = array( 'base_salary_classification', 'paye_base', 'paye', 'employee_pension', 'employer_pension' );
		foreach ( $required_rules as $name ) {
			if ( empty( $rules[ $name ] ) || 'APPROVED' !== strtoupper( (string) ( $rules[ $name ]['review_status'] ?? 'APPROVED' ) ) ) {
				return self::fail( 'hrx_rule_missing', 'Payroll requires an approved effective-dated rule: ' . $name . '.');
			}
		}
		$base = Stockland_Money::decimal( Stockland_Money::minor( $input['base_salary'] ?? '0.00' ) );
		if ( Stockland_Money::minor( $base ) < 0 ) { return self::fail( 'hrx_base_negative', 'Base salary cannot be negative.' ); }
		$params = json_decode( (string) ( $rules['base_salary_classification']['formula_parameters'] ?? '' ), true );
		if ( ! is_array( $params ) || ! in_array( $params['taxable_status'] ?? '', array( 'TAXABLE','EXEMPT' ), true ) || ! in_array( $params['pensionable_status'] ?? '', array( 'PENSIONABLE','NON_PENSIONABLE' ), true ) ) {
			return self::fail( 'hrx_salary_classification_unapproved', 'Base salary tax/pension classification is not approved in the effective rule set.' );
		}
		$gross_cash = Stockland_Money::minor( $base );
		$taxable_minor = 'TAXABLE' === $params['taxable_status'] ? Stockland_Money::minor( $base ) : 0;
		$pensionable_minor = 'PENSIONABLE' === $params['pensionable_status'] ? Stockland_Money::minor( $base ) : 0;
		$exempt_minor = 'EXEMPT' === $params['taxable_status'] ? Stockland_Money::minor( $base ) : 0;
		$other_deduction_minor = 0;
		$noncash_benefit_minor = 0;
		$line_items = array( array( 'item_code'=>'BASE_SALARY','item_label'=>'Base salary','item_type'=>'EARNING','amount'=>$base,'cash_payable'=>1,'taxable_status'=>$params['taxable_status'],'pensionable_status'=>$params['pensionable_status'],'legal_rule_id'=>(int) ( $rules['base_salary_classification']['id'] ?? 0 ),'source_type'=>'COMPENSATION_ASSIGNMENT','source_id'=>(int) ( $input['compensation_assignment_id'] ?? 0 ),'calculation_trace'=>'Base salary copied from the approved effective assignment; no salary was inferred.' ) );
		foreach ( (array) ( $input['items'] ?? array() ) as $item ) {
			$amount = Stockland_Money::decimal( Stockland_Money::minor( $item['amount'] ?? '0.00' ) );
			$minor = Stockland_Money::minor( $amount );
			if ( $minor < 0 ) { return self::fail( 'hrx_negative_component', 'Compensation item amounts must be non-negative; use a typed deduction/correction entry instead.' ); }
			$type = strtoupper( (string) ( $item['component_type'] ?? '' ) );
			$tax = strtoupper( (string) ( $item['taxable_status'] ?? 'LEGAL_REVIEW' ) );
			$pension = strtoupper( (string) ( $item['pensionable_status'] ?? 'LEGAL_REVIEW' ) );
			$cash = ! empty( $item['cash_payable'] );
			if ( ! in_array( $type, array( 'EARNING','BENEFIT','REIMBURSEMENT','DEDUCTION','BONUS','COMMISSION','OVERTIME','ADJUSTMENT' ), true ) ) { return self::fail( 'hrx_component_type_invalid', 'A compensation component has an unsupported type.' ); }
			if ( 'DEDUCTION' === $type ) {
				if ( ! in_array( $tax, array( 'NOT_APPLICABLE','NON_TAXABLE' ), true ) || ! in_array( $pension, array( 'NOT_APPLICABLE','NON_PENSIONABLE' ), true ) ) { return self::fail( 'hrx_deduction_classification', 'Deduction components must use an approved non-taxable/non-pensionable classification.' ); }
				$other_deduction_minor += $minor;
			} else {
				if ( ! in_array( $tax, array( 'TAXABLE','EXEMPT' ), true ) || ! in_array( $pension, array( 'PENSIONABLE','NON_PENSIONABLE' ), true ) ) { return self::fail( 'hrx_component_classification_review', 'A component has unresolved tax or pension classification; payroll calculation is blocked.' ); }
				if ( 'TAXABLE' === $tax ) { $taxable_minor += $minor; } else { $exempt_minor += $minor; }
				if ( 'PENSIONABLE' === $pension ) { $pensionable_minor += $minor; }
				if ( $cash ) { $gross_cash += $minor; }
				if ( 'BENEFIT' === $type && ! $cash ) { $noncash_benefit_minor += $minor; }
			}
			$line_items[] = array( 'item_code'=>sanitize_key( (string) ( $item['component_code'] ?? '' ) ),'item_label'=>sanitize_text_field( (string) ( $item['component_name'] ?? '' ) ),'item_type'=>$type,'amount'=>$amount,'cash_payable'=>$cash ? 1 : 0,'taxable_status'=>$tax,'pensionable_status'=>$pension,'legal_rule_id'=>(int) ( $item['legal_rule_id'] ?? 0 ),'source_type'=>'COMPENSATION_ITEM','source_id'=>(int) ( $item['id'] ?? 0 ),'calculation_trace'=>'Copied from approved effective compensation component; classification pinned to effective rule review.' );
		}
		$taxable_earnings = Stockland_Money::decimal( $taxable_minor );
		$pensionable_base = Stockland_Money::decimal( $pensionable_minor );
		try {
			$employee_rate = self::rule_rate( $rules['employee_pension'] );
			$employer_rate = self::rule_rate( $rules['employer_pension'] );
			$employee_pension = self::percentage( $pensionable_base, $employee_rate );
			$employer_pension = self::percentage( $pensionable_base, $employer_rate );
		} catch ( Throwable $e ) { return self::fail( 'hrx_pension_rule_invalid', 'Approved pension rule is invalid: ' . $e->getMessage() ); }
		$paye_base_key = strtoupper( (string) ( $rules['paye_base']['formula_key'] ?? '' ) );
		if ( 'TAXABLE_EARNINGS' === $paye_base_key ) { $taxable_base = $taxable_earnings; }
		elseif ( 'TAXABLE_EARNINGS_LESS_EMPLOYEE_PENSION' === $paye_base_key ) { $taxable_base = Stockland_Money::subtract( $taxable_earnings, $employee_pension ); if ( Stockland_Money::minor( $taxable_base ) < 0 ) { $taxable_base = '0.00'; } }
		else { return self::fail( 'hrx_paye_base_rule_unsupported', 'The approved PAYE-base rule uses an unsupported method; legal/payroll review is required.' ); }
		if ( 'PROGRESSIVE_MARGINAL_BANDS' !== strtoupper( (string) ( $rules['paye']['formula_key'] ?? '' ) ) ) { return self::fail( 'hrx_paye_method_unsupported', 'The approved PAYE rule is not configured for progressive marginal bands.' ); }
		$tax_result = self::progressive_tax( $taxable_base, (array) ( $rules['paye']['bands'] ?? array() ) );
		if ( is_wp_error( $tax_result ) ) { return $tax_result; }
		$paye = $tax_result['tax'];
		$net_minor = $gross_cash - Stockland_Money::minor( $paye ) - Stockland_Money::minor( $employee_pension ) - $other_deduction_minor;
		if ( $net_minor < 0 ) { return self::fail( 'hrx_negative_net', 'Deductions exceed cash-payable gross. Hold the run for approved correction/recovery; do not create negative net pay.' ); }
		$gross = Stockland_Money::decimal( $gross_cash );
		$deductions = Stockland_Money::decimal( $other_deduction_minor );
		$net = Stockland_Money::decimal( $net_minor );
		$employer_cost = Stockland_Money::decimal( $gross_cash + Stockland_Money::minor( $employer_pension ) + $noncash_benefit_minor );
		$line_items[] = array( 'item_code'=>'PAYE','item_label'=>'Employment income tax withholding','item_type'=>'TAX','amount'=>$paye,'cash_payable'=>0,'taxable_status'=>'NOT_APPLICABLE','pensionable_status'=>'NOT_APPLICABLE','legal_rule_id'=>(int) ( $rules['paye']['id'] ?? 0 ),'source_type'=>'LEGAL_RULE','source_id'=>(int) ( $rules['paye']['id'] ?? 0 ),'calculation_trace'=>'Progressive marginal bands from approved legal rule; taxable base method is pinned separately.' );
		$line_items[] = array( 'item_code'=>'EMPLOYEE_PENSION','item_label'=>'Employee pension contribution','item_type'=>'DEDUCTION','amount'=>$employee_pension,'cash_payable'=>0,'taxable_status'=>'NOT_APPLICABLE','pensionable_status'=>'NOT_APPLICABLE','legal_rule_id'=>(int) ( $rules['employee_pension']['id'] ?? 0 ),'source_type'=>'LEGAL_RULE','source_id'=>(int) ( $rules['employee_pension']['id'] ?? 0 ),'calculation_trace'=>'Rate and pensionable basis come from approved rule inputs.' );
		$line_items[] = array( 'item_code'=>'EMPLOYER_PENSION','item_label'=>'Employer pension contribution','item_type'=>'EMPLOYER_COST','amount'=>$employer_pension,'cash_payable'=>0,'taxable_status'=>'NOT_APPLICABLE','pensionable_status'=>'NOT_APPLICABLE','legal_rule_id'=>(int) ( $rules['employer_pension']['id'] ?? 0 ),'source_type'=>'LEGAL_RULE','source_id'=>(int) ( $rules['employer_pension']['id'] ?? 0 ),'calculation_trace'=>'Rate and pensionable basis come from approved rule inputs.' );
		return array(
			'base_salary'=>$base,'taxable_earnings'=>$taxable_earnings,'exempt_earnings'=>Stockland_Money::decimal($exempt_minor),
			'gross_pay'=>$gross,'taxable_base'=>$taxable_base,'paye'=>$paye,'employee_pension'=>$employee_pension,
			'employer_pension'=>$employer_pension,'other_deductions'=>$deductions,'net_pay'=>$net,
			'employer_cost'=>$employer_cost,'pensionable_base'=>$pensionable_base,'line_items'=>$line_items,
			'calculation' => array('paye_base_rule_id'=>(int)($rules['paye_base']['id']??0),'paye_rule_id'=>(int)($rules['paye']['id']??0),'employee_pension_rule_id'=>(int)($rules['employee_pension']['id']??0),'employer_pension_rule_id'=>(int)($rules['employer_pension']['id']??0),'rounding'=>'HALF_AWAY_FROM_ZERO_CENT')
		);
	}

	private static function rule_rate( $rule ) {
		if ( 'PERCENT_OF_PENSIONABLE_BASE' !== strtoupper( (string) ( $rule['formula_key'] ?? '' ) ) ) { throw new InvalidArgumentException( 'Pension rule formula is unsupported.' ); }
		$p = json_decode( (string) ( $rule['formula_parameters'] ?? '' ), true );
		if ( ! is_array( $p ) || ! array_key_exists( 'rate', $p ) || 'HALF_AWAY_FROM_ZERO_CENT' !== ( $p['rounding_method'] ?? '' ) ) { throw new InvalidArgumentException( 'Rate or approved rounding method is missing.' ); }
		self::rate_units( $p['rate'] );
		return (string) $p['rate'];
	}

	/**
	 * Calculate a final settlement only from explicit, separately approved item amounts.
	 * Legal liability formulas are resolved by the caller's approved rules; no severance/leave assumption is invented here.
	 */
	public static function final_settlement( $items ) {
		$gross = 0; $deductions = 0;
		foreach ( (array) $items as $item ) {
			if ( empty( $item['approved'] ) || empty( $item['rule_id'] ) || ! preg_match( '/^[a-f0-9]{64}$/i', (string) ( $item['rule_source_sha256'] ?? '' ) ) ) {
				return self::fail( 'hrx_exit_rule_unverified', 'Every final-settlement item must have an approved rule, source hash and verified amount.' );
			}
			$amount = Stockland_Money::minor( $item['amount'] ?? '0.00' );
			if ( $amount < 0 ) { return self::fail( 'hrx_exit_item_negative', 'Settlement item amounts must be non-negative and typed as earning or deduction.' ); }
			if ( 'DEDUCTION' === strtoupper( (string) ( $item['item_type'] ?? '' ) ) ) { $deductions += $amount; } else { $gross += $amount; }
		}
		if ( $deductions > $gross ) { return self::fail( 'hrx_exit_negative_net', 'Final-settlement deductions exceed earnings; hold for legal review.' ); }
		return array( 'gross_final_amount'=>Stockland_Money::decimal($gross), 'deduction_total'=>Stockland_Money::decimal($deductions), 'net_final_amount'=>Stockland_Money::decimal($gross-$deductions) );
	}
}
