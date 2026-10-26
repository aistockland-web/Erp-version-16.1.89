<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Deterministic, rule-driven payroll engine for the replacement domain.
 *
 * It never reads legacy payroll tables and has no statutory-rate defaults.
 * It requires one active, independently reviewed and Owner-approved rule set.
 * Missing/unapproved coverage produces HELD lines and an EXCEPTIONS run.
 */
final class Stockland_People_Payroll_Engine {
	public static function calculate_run( $run_id ) {
		global $wpdb;
		$run_id = absint( $run_id );
		if ( ! $run_id ) { throw new InvalidArgumentException( 'A valid payroll run ID is required.' ); }
		$run_table = Stockland_People_Replacement::table( 'people_payroll_runs' );
		$line_table = Stockland_People_Replacement::table( 'people_payroll_lines' );
		$wpdb->query( 'START TRANSACTION' );
		try {
			$run = Stockland_DB::read_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d FOR UPDATE', $run_table, $run_id ), ARRAY_A );
			if ( ! $run || 'DRAFT' !== strtoupper( (string) $run['status'] ) ) {
				throw new RuntimeException( 'Only a new draft can be calculated. For a correction, create a linked adjustment run; do not overwrite a previous run.' );
			}
			self::validate_period( $run['period_start'], $run['period_end'] );
			$rule = self::approved_rule_for_period( $run['period_start'], $run['period_end'] );
			$params = json_decode( (string) $rule['parameters_json'], true );
			self::validate_rule_parameters( $params );
			$rule_items = self::load_rule_items( (int) $rule['id'], $run['period_start'], $run['period_end'] );
			$people = Stockland_People_Replacement::table( 'people_people' );
			$employment_table = Stockland_People_Replacement::table( 'people_employments' );
			$employments = Stockland_DB::read_results(
				$wpdb->prepare(
					"SELECT e.*,p.person_kind FROM %i e INNER JOIN %i p ON p.id=e.person_id
					WHERE e.lifecycle_status IN ('ACTIVE','ON_LEAVE','NOTICE')
					AND e.start_date<=%s AND (e.end_date IS NULL OR e.end_date>=%s)
					AND p.person_kind='EMPLOYEE'
					ORDER BY e.id",
					$employment_table, $people, $run['period_end'], $run['period_start']
				),
				ARRAY_A
			);
			if ( ! is_array( $employments ) ) { throw new RuntimeException( 'Could not read target-domain employee relationships.' ); }
			// Re-entrant generation is intentionally prohibited after this point. The
			// draft is calculated exactly once; errors roll back the entire transaction.
			$line_ids = array();
			$totals = array( 'gross'=>'0.00','paye'=>'0.00','employee_pension'=>'0.00','employer_pension'=>'0.00','deductions'=>'0.00','net'=>'0.00','employer_cost'=>'0.00' );
			$exceptions = 0;
			$now = current_time( 'mysql' );
			foreach ( $employments as $employment ) {
				$line = self::calculate_employment_line( $employment, $run, $rule, $params, $rule_items, $now );
				$line['record_uuid'] = wp_generate_uuid4();
				$line['status'] = $line['hold_reason'] ? 'HELD' : 'CALCULATED';
				$line['version_no'] = 1;
				$line['created_by'] = get_current_user_id();
				$line['created_at'] = $now;
				$line['run_id'] = $run_id;
				$line['person_id'] = (int) $employment['person_id'];
				$line['employment_id'] = (int) $employment['id'];
				$line['legacy_payroll_line_id'] = null;
				$line['source_key'] = 'EMP-' . (int) $employment['id'];
				$line['currency'] = 'ETB';
				$line['calculated_at'] = $now;
				if ( false === $wpdb->insert( $line_table, $line ) || $wpdb->last_error ) {
					throw new RuntimeException( 'Could not persist calculated payroll line for target employment ID ' . (int) $employment['id'] . '.' );
				}
				$line_ids[] = (int) $wpdb->insert_id;
				if ( $line['hold_reason'] ) {
					++$exceptions;
				} else {
					foreach ( array( 'gross'=>'gross_amount','paye'=>'paye_amount','employee_pension'=>'employee_pension_amount','employer_pension'=>'employer_pension_amount','deductions'=>'other_deduction_amount','net'=>'net_amount','employer_cost'=>'employer_cost_amount' ) as $key => $field ) {
						$totals[ $key ] = Stockland_Money::add( $totals[ $key ], $line[ $field ] );
					}
				}
			}
			$snapshot_hash = hash( 'sha256', wp_json_encode( array(
				'run_id'=>$run_id,
				'period'=>array( $run['period_start'], $run['period_end'] ),
				'rule_set'=>array( (int) $rule['id'], $rule['version_label'], $rule['source_sha256'] ),
				'line_ids'=>$line_ids,
				'totals'=>$totals,
				'exceptions'=>$exceptions,
			) ) );
			$status = $exceptions ? 'EXCEPTIONS' : 'CALCULATED';
			$changed = $wpdb->update( $run_table, array(
				'status'=>$status, 'input_cutoff'=>$now, 'input_snapshot_hash'=>$snapshot_hash,
				'rule_set_ids_json'=>wp_json_encode( array( (int) $rule['id'] ) ),
				'generated_at'=>$now, 'exception_count'=>$exceptions,
				'gross_total'=>$totals['gross'], 'paye_total'=>$totals['paye'],
				'employee_pension_total'=>$totals['employee_pension'], 'employer_pension_total'=>$totals['employer_pension'],
				'deduction_total'=>$totals['deductions'], 'net_total'=>$totals['net'],
				'employer_cost_total'=>$totals['employer_cost'],
				'version_no'=>(int) $run['version_no']+1, 'updated_by'=>get_current_user_id(), 'updated_at'=>$now,
			), array( 'id'=>$run_id, 'version_no'=>(int) $run['version_no'] ) );
			if ( false === $changed || 0 === $changed ) { throw new RuntimeException( 'Payroll run changed concurrently; no calculation was committed.' ); }
			if ( ! $exceptions ) {
				// A payroll cutoff locks only the approved target-domain time entries
				// within this period, within the same transaction as the snapshot.
				$time_table = Stockland_People_Replacement::table( 'people_time_entries' );
				$lock_sql = $wpdb->prepare( "UPDATE %i SET status='LOCKED',locked_at=%s,version_no=version_no+1,updated_by=%d,updated_at=%s WHERE status='APPROVED' AND work_date BETWEEN %s AND %s", $time_table, $now, get_current_user_id(), $now, $run['period_start'], $run['period_end'] );
				if ( false === $wpdb->query( $lock_sql ) ) { throw new RuntimeException( 'Could not lock approved target-domain time entries for payroll cutoff.' ); }
			}
			if ( ! Stockland_People_Replacement::event( 'payroll-run', $run_id, 'PAYROLL_CALCULATED', 'DRAFT', $status, $exceptions ? 'Held for blocking exceptions' : 'Deterministic calculation completed', array( 'snapshot_hash'=>$snapshot_hash,'line_count'=>count($line_ids),'exception_count'=>$exceptions,'rule_set_id'=>(int)$rule['id'] ) ) ) {
				throw new RuntimeException( 'Payroll calculation audit event failed.' );
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'Payroll calculation commit failed.' ); }
			return array(
				'id'=>$run_id, 'status'=>$status, 'line_count'=>count($line_ids), 'exception_count'=>$exceptions,
				'totals'=>$totals, 'snapshot_hash'=>$snapshot_hash, 'rule_version'=>$rule['version_label'],
				'message'=>$exceptions ? 'Blocking exceptions exist. Approval and posting are prohibited.' : 'Calculated from the frozen target-domain inputs and approved rule version; submission and Owner approval remain separate.',
			);
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}
	}

	private static function validate_period( $start, $end ) {
		$start_dt = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $start );
		$end_dt = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $end );
		if ( ! $start_dt || ! $end_dt || $start_dt->format( 'Y-m-d' ) !== $start || $end_dt->format( 'Y-m-d' ) !== $end || $start_dt->format( 'd' ) !== '01' || $start_dt->format( 'Y-m' ) !== $end_dt->format( 'Y-m' ) || $end_dt->format( 'Y-m-t' ) !== $end ) {
			throw new RuntimeException( 'Payroll run must cover one complete calendar month.' );
		}
	}
	private static function approved_rule_for_period( $start, $end ) {
		global $wpdb;
		$rows = Stockland_DB::read_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE rule_type='PAYROLL' AND status='ACTIVE' AND review_status='APPROVED'
			AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC",
			Stockland_People_Replacement::table( 'people_rule_sets' ), $start, $end
		), ARRAY_A );
		if ( ! is_array( $rows ) || 1 !== count( $rows ) ) {
			throw new RuntimeException( 'Exactly one active, approved PAYROLL rule set must cover the full period. Missing or overlapping versions block payroll.' );
		}
		$r = $rows[0];
		if ( empty( $r['reviewer_user_id'] ) || empty( $r['approved_by'] ) || (int)$r['created_by'] === (int)$r['reviewer_user_id'] || (int)$r['created_by'] === (int)$r['approved_by'] || (int)$r['reviewer_user_id'] === (int)$r['approved_by'] || empty( $r['reviewer_qualification'] ) || empty( $r['review_reference'] ) || empty( $r['reviewed_at'] ) || ! preg_match( '/^[a-f0-9]{64}$/i', (string)$r['source_sha256'] ) ) {
			throw new RuntimeException( 'Rule set lacks distinct preparer, qualified reviewer, Owner approval, written review evidence or source hash.' );
		}
		return $r;
	}
	private static function validate_rule_parameters( $p ) {
		if ( ! is_array( $p ) || strtoupper( (string)( $p['legal_review_gate'] ?? '' ) ) !== 'SIGNED' ) {
			throw new RuntimeException( 'Payroll rule parameters lack a signed legal-review gate.' );
		}
		if ( ! in_array( strtoupper( (string)( $p['tax_base_method'] ?? '' ) ), array( 'TAXABLE_GROSS','TAXABLE_GROSS_LESS_EMPLOYEE_PENSION' ), true ) ) {
			throw new RuntimeException( 'A legally approved explicit PAYE base method is required; there is no legacy formula fallback.' );
		}
		if ( ! in_array( strtoupper( (string)( $p['paye_method'] ?? '' ) ), array( 'BRACKET_RATE_MINUS_CREDIT','PROGRESSIVE_MARGINAL' ), true ) ) {
			throw new RuntimeException( 'An explicitly approved PAYE method is required.' );
		}
		foreach ( array( 'employee_pension_rate','employer_pension_rate' ) as $key ) {
			if ( ! isset( $p[$key] ) || ! is_numeric( $p[$key] ) || (float)$p[$key] < 0 || (float)$p[$key] > 1 ) {
				throw new RuntimeException( 'Approved rule parameter missing or invalid: ' . $key );
			}
		}
		if ( empty( $p['paye_bands'] ) || ! is_array( $p['paye_bands'] ) ) {
			throw new RuntimeException( 'An approved PAYE band schedule is required.' );
		}
		if ( strtoupper( (string)( $p['rounding_method'] ?? '' ) ) !== 'HALF_UP_TO_CENT' ) {
			throw new RuntimeException( 'Only the explicitly reviewed HALF_UP_TO_CENT rounding method is implemented.' );
		}
		$last = -1;
		foreach ( $p['paye_bands'] as $band ) {
			if ( ! is_array($band) || ! isset($band['lower'], $band['rate']) || ! is_numeric($band['rate']) || (float)$band['rate'] < 0 || (float)$band['rate'] > 1 ) {
				throw new RuntimeException( 'Malformed progressive PAYE band.' );
			}
			$lower = Stockland_Money::minor( $band['lower'] );
			if ( $lower < $last ) { throw new RuntimeException( 'PAYE bands overlap or lower bounds are not increasing.' ); }
			if ( ! array_key_exists( 'upper', $band ) || null === $band['upper'] ) {
				$last = PHP_INT_MAX;
			} else {
				$upper = Stockland_Money::minor( $band['upper'] );
				if ( $upper <= $lower || $upper < $last ) { throw new RuntimeException( 'PAYE band bounds overlap or are not increasing.' ); }
				$last = $upper;
			}
			if ( 'BRACKET_RATE_MINUS_CREDIT' === strtoupper((string)$p['paye_method']) && ! isset($band['credit']) ) {
				throw new RuntimeException( 'Bracket-rate method requires an explicit credit for every band.' );
			}
		}
		if ( ! isset( $p['gl_mapping'] ) || ! is_array( $p['gl_mapping'] ) ) {
			throw new RuntimeException( 'Finance-approved GL mappings must be recorded before posting.' );
		}
	}

	private static function load_rule_items( $rule_set_id, $start, $end ) {
		global $wpdb;
		$rows = Stockland_DB::read_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE rule_set_id=%d AND status='ACTIVE'
			AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s)",
			Stockland_People_Replacement::table( 'people_rule_items' ), $rule_set_id, $start, $end
		), ARRAY_A );
		if ( ! is_array( $rows ) ) { throw new RuntimeException( 'Could not read component rules.' ); }
		$map = array();
		foreach ( $rows as $row ) {
			$code = strtoupper( (string) $row['component_code'] );
			if ( isset( $map[$code] ) ) { throw new RuntimeException( 'Multiple active classifications for component ' . $code . '.' ); }
			$map[$code] = $row;
		}
		return $map;
	}

	private static function calculate_employment_line( $employment, $run, $rule, $params, $rule_items, $now ) {
		global $wpdb;
		$start = $run['period_start']; $end = $run['period_end'];
		$days_in_month = (int) date( 't', strtotime( $start ) );
		$active_start = max( $start, (string)$employment['start_date'] );
		$active_end = $employment['end_date'] && $employment['end_date'] < $end ? $employment['end_date'] : $end;
		$hold = '';
		$base = '0.00'; $components = array(); $component_evidence = array(); $assignment_by_day = array();
		if ( $active_start > $active_end ) { $hold = 'No active employment days overlap this payroll period.'; }
		$assignments = Stockland_DB::read_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE employment_id=%d AND status='EFFECTIVE' AND approved_by IS NOT NULL
			AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from,id",
			Stockland_People_Replacement::table( 'people_compensation_assignments' ), (int)$employment['id'], $end, $start
		), ARRAY_A );
		if ( ! is_array( $assignments ) ) { $hold = $hold ?: 'Approved compensation assignments could not be read.'; $assignments = array(); }
		$unpaid_days = self::unpaid_days( (int)$employment['id'], $start, $end, $active_start, $active_end );
		$cursor = $active_start;
		while ( ! $hold && $cursor <= $active_end ) {
			$matching = array();
			foreach ( $assignments as $a ) {
				if ( $a['effective_from'] <= $cursor && ( empty($a['effective_to']) || $a['effective_to'] >= $cursor ) ) { $matching[] = $a; }
			}
			if ( count( $matching ) !== 1 ) {
				$hold = 0 === count($matching) ? 'Missing approved compensation assignment for ' . $cursor . '.' : 'Overlapping compensation assignments for ' . $cursor . '.';
				break;
			}
			$a = $matching[0];
			$assignment_by_day[$cursor] = $a;
			if ( ! isset( $unpaid_days[$cursor] ) ) {
				$base = Stockland_Money::add( $base, self::prorata( $a['base_salary'], 1, $days_in_month ) );
			}
			$cursor = date( 'Y-m-d', strtotime( $cursor . ' +1 day' ) );
		}
		if ( ! $hold && empty( $rule_items['BASE_SALARY'] ) ) { $hold = 'BASE_SALARY classification is missing from the active reviewed rule set.'; }
		$base_class = strtoupper( (string) ( $rule_items['BASE_SALARY']['classification_code'] ?? '' ) );
		if ( ! $hold && ! in_array( $base_class, array( 'TAXABLE_PENSIONABLE','TAXABLE_NONPENSIONABLE','EXEMPT_PENSIONABLE','EXEMPT_NONPENSIONABLE' ), true ) ) {
			$hold = 'BASE_SALARY classification is not an approved taxable/exempt earning.';
		}
		if ( ! $hold ) {
			$component_evidence[] = array( 'component_code'=>'BASE_SALARY', 'amount'=>$base, 'rule_item_id'=>(int)$rule_items['BASE_SALARY']['id'], 'classification_code'=>$base_class );
		}
		$assignment_ids = array();
		foreach ( $assignment_by_day as $day => $a ) { if ( ! isset($unpaid_days[$day]) ) $assignment_ids[(int)$a['id']] = $a; }
		foreach ( $assignment_ids as $a ) {
			if ( $hold ) { break; }
			$items = Stockland_DB::read_results( $wpdb->prepare(
				"SELECT * FROM %i WHERE assignment_id=%d AND status='APPROVED' AND recurring=1 AND cadence='MONTHLY' ORDER BY id",
				Stockland_People_Replacement::table( 'people_compensation_items' ), (int)$a['id']
			), ARRAY_A );
			if ( ! is_array( $items ) ) { $hold = 'Compensation components could not be read.'; break; }
			foreach ( $items as $item ) {
				$code = strtoupper( (string)$item['component_code'] );
				if ( empty( $rule_items[$code] ) || empty( $item['rule_item_id'] ) || (int)$rule_items[$code]['id'] !== (int)$item['rule_item_id'] ) {
					$hold = 'Component ' . $code . ' lacks its linked active classification rule.'; break;
				}
				$count_days = 0;
				foreach ( $assignment_by_day as $day => $assigned ) {
					if ( (int)$assigned['id'] === (int)$a['id'] && ! isset($unpaid_days[$day]) ) { ++$count_days; }
				}
				$value = self::prorata( $item['amount'], $count_days, $days_in_month );
				$components[$code] = Stockland_Money::add( $components[$code] ?? '0.00', $value );
				$component_evidence[] = array( 'assignment_id'=>(int)$a['id'], 'component_code'=>$code, 'amount'=>$value, 'rule_item_id'=>(int)$item['rule_item_id'] );
			}
		}
		// Premium pay is calculated only from approved, locked time entries and an
		// explicitly reviewed time_pay block. Missing rules hold the employee line.
		$time_rows = Stockland_DB::read_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE employment_id=%d AND work_date BETWEEN %s AND %s AND status='LOCKED' ORDER BY work_date,id",
			Stockland_People_Replacement::table( 'people_time_entries' ), (int)$employment['id'], $start, $end
		), ARRAY_A );
		foreach ( (array)$time_rows as $time ) {
			$total_minutes = (int)$time['overtime_minutes'] + (int)$time['night_minutes'] + (int)$time['rest_day_minutes'] + (int)$time['holiday_minutes'];
			if ( $total_minutes <= 0 ) { continue; }
			$time_pay = $params['time_pay'] ?? null;
			if ( ! is_array($time_pay) || empty($time_pay['monthly_hours_divisor']) || empty($time_pay['multipliers']) ) { $hold = $hold ?: 'Approved time-premium formulas are missing.'; break; }
			$assignment = $assignment_by_day[$time['work_date']] ?? null;
			if ( ! $assignment ) { $hold = $hold ?: 'No effective compensation on time-entry date ' . $time['work_date'] . '.'; break; }
			try { $hourly = Stockland_Money::divide( $assignment['base_salary'], $time_pay['monthly_hours_divisor'] ); }
			catch ( Throwable $e ) { $hold = 'Invalid monthly hours divisor in approved rule.'; break; }
			$map = array( 'overtime_minutes'=>'OVERTIME', 'night_minutes'=>'NIGHT_PREMIUM', 'rest_day_minutes'=>'REST_DAY', 'holiday_minutes'=>'PUBLIC_HOLIDAY' );
			foreach ( $map as $column => $code ) {
				$minutes = (int)$time[$column];
				if ( $minutes <= 0 ) { continue; }
				if ( empty($rule_items[$code]) || ! isset($time_pay['multipliers'][$code]) || ! is_numeric($time_pay['multipliers'][$code]) ) { $hold = 'Missing approved time-premium classification or multiplier for ' . $code . '.'; break; }
				$hour_amount = self::prorata( $hourly, $minutes, 60 );
				$amount = Stockland_Money::percentage( $hour_amount, (string)$time_pay['multipliers'][$code] );
				$components[$code] = Stockland_Money::add( $components[$code] ?? '0.00', $amount );
				$component_evidence[] = array( 'component_code'=>$code, 'amount'=>$amount, 'minutes'=>$minutes, 'time_entry_id'=>(int)$time['id'], 'rule_item_id'=>(int)$rule_items[$code]['id'] );
			}
			if ( $hold ) { break; }
		}
		$taxable = '0.00'; $exempt = '0.00'; $pensionable = '0.00'; $deductions = '0.00';
		if ( ! $hold ) {
			if ( in_array( $base_class, array( 'TAXABLE_PENSIONABLE','TAXABLE_NONPENSIONABLE' ), true ) ) { $taxable = $base; }
			if ( in_array( $base_class, array( 'EXEMPT_PENSIONABLE','EXEMPT_NONPENSIONABLE' ), true ) ) { $exempt = $base; }
			if ( in_array( $base_class, array( 'TAXABLE_PENSIONABLE','EXEMPT_PENSIONABLE' ), true ) ) { $pensionable = $base; }
			foreach ( $components as $code => $amount ) {
				$item = $rule_items[$code] ?? null;
				if ( ! $item ) { $hold = 'Missing classification rule for ' . $code . '.'; break; }
				$class = strtoupper((string)$item['classification_code']);
				if ( in_array($class,array('TAXABLE_PENSIONABLE','TAXABLE_NONPENSIONABLE'),true) ) { $taxable = Stockland_Money::add($taxable,$amount); }
				elseif ( in_array($class,array('EXEMPT_PENSIONABLE','EXEMPT_NONPENSIONABLE'),true) ) { $exempt = Stockland_Money::add($exempt,$amount); }
				elseif ( $class === 'DEDUCTION' ) { $deductions = Stockland_Money::add($deductions,$amount); }
				else { $hold = 'Component ' . $code . ' is still under legal review and cannot be calculated.'; break; }
				if ( in_array($class,array('TAXABLE_PENSIONABLE','EXEMPT_PENSIONABLE'),true) ) { $pensionable = Stockland_Money::add($pensionable,$amount); }
			}
		}
		$component_taxable_total = in_array( $base_class, array( 'TAXABLE_PENSIONABLE','TAXABLE_NONPENSIONABLE' ), true ) ? Stockland_Money::subtract( $taxable, $base ) : $taxable;
		$component_exempt_total = in_array( $base_class, array( 'EXEMPT_PENSIONABLE','EXEMPT_NONPENSIONABLE' ), true ) ? Stockland_Money::subtract( $exempt, $base ) : $exempt;
		$gross = Stockland_Money::add( $base, Stockland_Money::add( $component_taxable_total, $component_exempt_total ) );
		$employee_pension = '0.00'; $employer_pension = '0.00'; $paye = '0.00'; $net = '0.00'; $cost = '0.00'; $taxable_base = $taxable;
		if ( ! $hold ) {
			$employee_pension = Stockland_Money::percentage( $pensionable, (string)$params['employee_pension_rate'] );
			$employer_pension = Stockland_Money::percentage( $pensionable, (string)$params['employer_pension_rate'] );
			if ( strtoupper((string)$params['tax_base_method']) === 'TAXABLE_GROSS_LESS_EMPLOYEE_PENSION' ) {
				$taxable_base = Stockland_Money::subtract($taxable,$employee_pension);
			} elseif ( strtoupper((string)$params['tax_base_method']) !== 'TAXABLE_GROSS' ) {
				$hold = 'Unsupported approved PAYE base method.';
			}
			if ( ! $hold ) {
				$paye = self::calculate_paye( $taxable_base, $params );
				$net = Stockland_Money::subtract( Stockland_Money::subtract( Stockland_Money::subtract( $gross, $employee_pension ), $paye ), $deductions );
				$cost = Stockland_Money::add($gross,$employer_pension);
				if ( Stockland_Money::minor($net) < 0 ) { $hold = 'Net pay is negative; deductions or inputs require authorized review.'; }
			}
		}
		$snapshot = array(
			'employment_id'=>(int)$employment['id'],'person_id'=>(int)$employment['person_id'],
			'period'=>array($start,$end),'contract_basis'=>$employment['contract_basis'],
			'employment_dates'=>array($employment['start_date'],$employment['end_date']),
			'assignment_by_day'=>array_map(static function($a){return (int)$a['id'];},$assignment_by_day),
			'unpaid_days'=>array_keys($unpaid_days),'rule_set_id'=>(int)$rule['id'],'rule_version'=>$rule['version_label'],
			'rule_source_sha256'=>$rule['source_sha256'],'component_evidence'=>$component_evidence,
			'components'=>$components,'taxable_components'=>$component_taxable_total,'exempt_components'=>$component_exempt_total,'taxable_base'=>$taxable_base,'pensionable_base'=>$pensionable,
			'gross'=>$gross,'paye'=>$paye,'employee_pension'=>$employee_pension,'employer_pension'=>$employer_pension,'deductions'=>$deductions,'net'=>$net,
		);
		return array(
			'base_amount'=>$hold? '0.00' : $base,'taxable_earnings'=>$hold? '0.00' : $taxable,'approved_exempt_earnings'=>$hold? '0.00' : $exempt,
			'gross_amount'=>$hold? '0.00' : $gross,'taxable_base'=>$hold? null : $taxable_base,'paye_amount'=>$hold? null : $paye,
			'employee_pension_base'=>$hold? null : $pensionable,'employee_pension_amount'=>$hold? null : $employee_pension,
			'employer_pension_amount'=>$hold? null : $employer_pension,'other_deduction_amount'=>$hold? '0.00' : $deductions,
			'net_amount'=>$hold? null : $net,'employer_cost_amount'=>$hold? null : $cost,
			'calculation_trace_json'=>wp_json_encode(array('rule_set_id'=>(int)$rule['id'],'rule_version'=>$rule['version_label'],'rule_hash'=>$rule['source_sha256'],'tax_base_method'=>$params['tax_base_method'],'paye_method'=>$params['paye_method'],'components'=>$component_evidence)),
			'input_snapshot_json'=>wp_json_encode($snapshot),'hold_reason'=>$hold?:null,
		);
	}
	private static function unpaid_days( $employment_id, $start, $end, $active_start, $active_end ) {
		global $wpdb;$days=array();
		$rows=Stockland_DB::read_results($wpdb->prepare("SELECT start_date,end_date FROM %i WHERE employment_id=%d AND leave_type='UNPAID' AND event_kind='REQUEST' AND status='APPROVED' AND start_date<=%s AND end_date>=%s",Stockland_People_Replacement::table('people_leave_events'),$employment_id,$end,$start),ARRAY_A);
		foreach((array)$rows as $r){$from=max($start,(string)$r['start_date'],$active_start);$to=min($end,(string)$r['end_date'],$active_end);for($d=$from;$d<=$to;$d=date('Y-m-d',strtotime($d.' +1 day')))$days[$d]=true;}
		return $days;
	}
	private static function prorata( $amount, $numerator, $denominator ) {
		$denominator=(int)$denominator;$numerator=(int)$numerator;
		if($denominator<=0||$numerator<0)throw new InvalidArgumentException('Invalid proportional amount ratio.');
		$minor=Stockland_Money::minor($amount);$product=$minor*$numerator;
		$rounded=$product>=0?intdiv($product+intdiv($denominator,2),$denominator):-intdiv(abs($product)+intdiv($denominator,2),$denominator);
		return Stockland_Money::decimal($rounded);
	}
	private static function calculate_paye( $taxable, $params ) {
		$minor=Stockland_Money::minor($taxable);if($minor<0)throw new RuntimeException('Negative PAYE base is blocked.');
		$bands=$params['paye_bands'];$method=strtoupper((string)$params['paye_method']);$tax='0.00';
		if($method==='BRACKET_RATE_MINUS_CREDIT'){
			foreach($bands as $band){$lower=Stockland_Money::minor($band['lower']);$upper=array_key_exists('upper',$band)&&$band['upper']!==null?Stockland_Money::minor($band['upper']):null;if($minor<$lower)continue;if($upper===null||$minor<$upper){$tax=Stockland_Money::subtract(Stockland_Money::percentage($taxable,(string)$band['rate']),(string)$band['credit']);if(Stockland_Money::minor($tax)<0)$tax='0.00';return $tax;}}
			throw new RuntimeException('Taxable income is outside the approved PAYE brackets.');
		}
		if($method==='PROGRESSIVE_MARGINAL'){
			$covered=false;
			foreach($bands as $band){$lower=Stockland_Money::minor($band['lower']);$upper=array_key_exists('upper',$band)&&$band['upper']!==null?Stockland_Money::minor($band['upper']):null;if($minor<$lower)break;$end=$upper===null?$minor:min($minor,$upper);$slice=max(0,$end-$lower);if($slice>0)$tax=Stockland_Money::add($tax,Stockland_Money::percentage(Stockland_Money::decimal($slice),(string)$band['rate']));if($upper===null||$minor<$upper){$covered=true;break;}}
			if(!$covered)throw new RuntimeException('Taxable income is outside the approved progressive PAYE schedule.');
			return $tax;
		}
		throw new RuntimeException('Unsupported PAYE method.');
	}
}