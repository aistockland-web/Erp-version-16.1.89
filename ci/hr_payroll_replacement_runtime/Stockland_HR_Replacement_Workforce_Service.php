<?php
/**
 * Workforce planning/positions/vacancy workflow service for the HR replacement.
 *
 * This is a candidate-stage vertical slice. It deliberately does not create people,
 * contracts, attendance, leave, compensation, payroll, settlement or payee records.
 * Those domains must call through separately tested services in later phases.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

final class Stockland_HR_Replacement_Workforce_Service {
	private $repo;
	private $access;

	public function __construct( $repo, $access ) {
		$this->repo = $repo;
		$this->access = $access;
	}

	private function error( $code, $message, $status = 400 ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private function required_text( $data, $key, $label, $max = 190 ) {
		$value = isset( $data[ $key ] ) ? trim( sanitize_text_field( (string) $data[ $key ] ) ) : '';
		if ( '' === $value || strlen( $value ) > $max ) {
			throw new InvalidArgumentException( $label . ' is required and must be no longer than ' . $max . ' characters.' );
		}
		return $value;
	}

	private function optional_text( $data, $key, $max = 190 ) {
		$value = isset( $data[ $key ] ) ? trim( sanitize_text_field( (string) $data[ $key ] ) ) : '';
		if ( strlen( $value ) > $max ) {
			throw new InvalidArgumentException( 'A text field is longer than the allowed limit.' );
		}
		return '' === $value ? null : $value;
	}

	private function positive_int( $value, $label, $max = 2147483647 ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^[0-9]+$/', (string) $value ) ) {
			throw new InvalidArgumentException( $label . ' must be a positive whole number.' );
		}
		$value = (int) $value;
		if ( $value < 1 || $value > $max ) {
			throw new InvalidArgumentException( $label . ' is outside the allowed range.' );
		}
		return $value;
	}

	private function bounded_count( $value, $label, $max = 1000 ) {
		$count = $this->positive_int( $value, $label, $max );
		return $count;
	}

	private function nonnegative_money( $value, $label ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/', (string) $value ) ) {
			throw new InvalidArgumentException( $label . ' must be a non-negative ETB amount with no more than two decimals.' );
		}
		$parts = explode( '.', (string) $value, 2 );
		$whole = $parts[0];
		$fraction = isset( $parts[1] ) ? $parts[1] : '';
		return $whole . '.' . str_pad( $fraction, 2, '0' );
	}

	private function compare_money( $left, $right ) {
		$parts = array();
		foreach ( array( (string) $left, (string) $right ) as $amount ) {
			if ( ! preg_match( '/^[0-9]+(?:\.[0-9]{1,2})?$/', $amount ) ) {
				throw new InvalidArgumentException( 'A monetary comparison value is invalid.' );
			}
			$split = explode( '.', $amount, 2 );
			$parts[] = array( ltrim( $split[0], '0' ) ?: '0', str_pad( isset( $split[1] ) ? $split[1] : '', 2, '0' ) );
		}
		$a = $parts[0];
		$b = $parts[1];
		if ( strlen( $a[0] ) !== strlen( $b[0] ) ) {
			return strlen( $a[0] ) < strlen( $b[0] ) ? -1 : 1;
		}
		$whole = strcmp( $a[0], $b[0] );
		if ( 0 !== $whole ) {
			return $whole < 0 ? -1 : 1;
		}
		$fraction = strcmp( $a[1], $b[1] );
		return $fraction < 0 ? -1 : ( $fraction > 0 ? 1 : 0 );
	}

	private function record_or_error( $entity, $id, $location_field = 'location_id' ) {
		$row = $this->repo->get( $entity, $id, true );
		if ( ! $row ) {
			return $this->error( 'hrx_record_not_found', 'The requested workforce record does not exist.', 404 );
		}
		if ( isset( $row[ $location_field ] ) ) {
			$auth = $this->access->assert_branch( $row[ $location_field ] );
			if ( is_wp_error( $auth ) ) {
				return $auth;
			}
		}
		return $row;
	}

	private function write_event( $entity, $row, $event, $from, $to, $key, $reason = '', $evidence = '' ) {
		$this->repo->append_event( $entity, (int) $row['id'], $event, $from, $to, get_current_user_id(), $key, $reason, $evidence );
	}

	/**
	 * Execute a command once per idempotency key and frozen request hash.
	 *
	 * A key reused for different work is rejected. A concurrent replay returns the
	 * committed result or a retryable conflict; it never silently executes twice.
	 */
	public function execute_idempotent( $key, $operation, $payload, $callback ) {
		$key = sanitize_text_field( (string) $key );
		if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{15,127}$/', $key ) ) {
			return $this->error( 'hrx_idempotency_key_required', 'Provide a unique request Idempotency-Key (16–128 safe characters).', 400 );
		}
		$actor = get_current_user_id();
		if ( $actor < 1 ) {
			return $this->error( 'hrx_authentication_required', 'Sign in with an active Stockland ERP account.', 401 );
		}
		$request_hash = hash( 'sha256', wp_json_encode( array( 'operation' => $operation, 'payload' => $payload ) ) );
		try {
			$prior = $this->repo->receipt( $key );
			if ( $prior ) {
				if ( (int) $prior['actor_id'] !== $actor || $prior['operation_code'] !== sanitize_key( $operation ) || ! hash_equals( $prior['request_sha256'], $request_hash ) ) {
					return $this->error( 'hrx_idempotency_key_conflict', 'This idempotency key was already used for a different request.', 409 );
				}
				if ( 'COMPLETED' === $prior['result_status'] && is_string( $prior['result_json'] ) ) {
					$result = json_decode( $prior['result_json'], true );
					if ( is_array( $result ) ) {
						return rest_ensure_response( $result );
					}
				}
				return $this->error( 'hrx_command_in_progress', 'The prior request is not yet confirmed complete. Refresh before retrying.', 409 );
			}
			$this->repo->begin();
			$receipt_id = $this->repo->create_receipt( $key, $actor, $operation, $request_hash );
			$result = call_user_func( $callback );
			if ( is_wp_error( $result ) ) {
				$this->repo->rollback();
				return $result;
			}
			if ( ! is_array( $result ) ) {
				throw new RuntimeException( 'Command returned an unsupported result.' );
			}
			$entity_type = isset( $result['entity_type'] ) ? $result['entity_type'] : null;
			$entity_id = isset( $result['id'] ) ? $result['id'] : null;
			$public_result = $result;
			unset( $public_result['entity_type'] );
			$this->repo->finish_receipt( $receipt_id, $public_result, $entity_type, $entity_id );
			$this->repo->commit();
			return rest_ensure_response( $public_result );
		} catch ( Throwable $e ) {
			$this->repo->rollback();
			// Retry path for a concurrent duplicate key: only return a result after commit.
			try {
				$prior = $this->repo->receipt( $key );
				if ( $prior && (int) $prior['actor_id'] === $actor && $prior['operation_code'] === sanitize_key( $operation ) && hash_equals( $prior['request_sha256'], $request_hash ) && 'COMPLETED' === $prior['result_status'] ) {
					$result = json_decode( (string) $prior['result_json'], true );
					if ( is_array( $result ) ) {
						return rest_ensure_response( $result );
					}
				}
			} catch ( Throwable $ignored ) {
				// Intentionally return a stable failure below; SQL details are logged only server-side.
			}
			error_log( '[Stockland HRX] operation failed (' . get_class( $e ) . ').' );
			return $this->error( 'hrx_operation_failed', 'The workforce operation was not confirmed. Refresh the record and request status before retrying.', 409 );
		}
	}

	public function list_org_units( $filters = array() ) {
		return array( 'org_units' => $this->repo->list_rows( 'org_units', $filters, array(), true ) );
	}

	public function list_plans( $filters = array() ) {
		return array( 'plans' => $this->repo->list_rows( 'workforce_plans', $filters, $this->access->scoped_location_ids(), $this->access->company_scope() ) );
	}

	public function get_plan( $id ) {
		$row = $this->record_or_error( 'workforce_plans', $id );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		return array( 'plan' => $row, 'lines' => $this->repo->list_rows( 'plan_lines', array( 'plan_id' => (int) $id ), $this->access->scoped_location_ids(), $this->access->company_scope() ) );
	}

	public function create_plan( $data, $key ) {
		try {
			$location_id = $this->positive_int( $data['location_id'] ?? null, 'Work location' );
			$access = $this->access->assert_branch( $location_id );
			if ( is_wp_error( $access ) ) {
				return $access;
			}
			$plan_code = $this->required_text( $data, 'plan_code', 'Plan code', 80 );
			$fiscal_year = $this->required_text( $data, 'fiscal_year', 'Fiscal year', 20 );
			if ( ! preg_match( '/^(?:20[0-9]{2}(?:[-\/]20?[0-9]{2})?|20[0-9]{2}\/[0-9]{2})$/', $fiscal_year ) ) {
				throw new InvalidArgumentException( 'Fiscal year must use a year or year-range format such as 2026/27.' );
			}
			$department = $this->required_text( $data, 'department', 'Department', 190 );
			$justification = trim( sanitize_textarea_field( (string) ( $data['justification'] ?? '' ) ) );
			if ( strlen( $justification ) < 20 || strlen( $justification ) > 4000 ) {
				throw new InvalidArgumentException( 'Workforce justification must contain 20–4000 characters.' );
			}
			$row = $this->repo->insert( 'workforce_plans', array(
				'plan_code' => $plan_code, 'fiscal_year' => $fiscal_year, 'department' => $department,
				'location_id' => $location_id, 'justification' => $justification,
				'status' => 'DRAFT', 'version_no' => 1, 'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			) );
			$this->write_event( 'workforce-plan', $row, 'WORKFORCE_PLAN_CREATED', null, 'DRAFT', $key, 'Workforce plan created.' );
			return array( 'id' => (int) $row['id'], 'entity_type' => 'workforce-plan', 'record' => $row, 'message' => 'Draft workforce plan created.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_validation_failed', $e->getMessage(), 400 );
		}
	}

	public function update_plan( $id, $data, $key ) {
		try {
			$row = $this->record_or_error( 'workforce_plans', $id );
			if ( is_wp_error( $row ) ) return $row;
			if ( 'DRAFT' !== $row['status'] ) return $this->error( 'hrx_plan_locked', 'Only a draft workforce plan can be edited.', 409 );
			$version = $this->positive_int( $data['version_no'] ?? null, 'Record version' );
			if ( $version !== (int) $row['version_no'] ) return $this->error( 'hrx_stale_version', 'This plan changed since it was opened. Reload it before editing.', 409 );
			$changes = array();
			if ( array_key_exists( 'fiscal_year', $data ) ) {
				$fiscal = $this->required_text( $data, 'fiscal_year', 'Fiscal year', 20 );
				if ( ! preg_match( '/^(?:20[0-9]{2}(?:[-\/]20?[0-9]{2})?|20[0-9]{2}\/[0-9]{2})$/', $fiscal ) ) throw new InvalidArgumentException( 'Fiscal year format is invalid.' );
				$changes['fiscal_year'] = $fiscal;
			}
			if ( array_key_exists( 'department', $data ) ) $changes['department'] = $this->required_text( $data, 'department', 'Department', 190 );
			if ( array_key_exists( 'justification', $data ) ) {
				$j = trim( sanitize_textarea_field( (string) $data['justification'] ) );
				if ( strlen( $j ) < 20 || strlen( $j ) > 4000 ) throw new InvalidArgumentException( 'Justification must contain 20–4000 characters.' );
				$changes['justification'] = $j;
			}
			$updated = $this->repo->update_versioned( 'workforce_plans', $id, $version, $changes );
			$this->write_event( 'workforce-plan', $updated, 'WORKFORCE_PLAN_UPDATED', 'DRAFT', 'DRAFT', $key, 'Draft workforce plan fields updated.' );
			return array( 'id' => (int) $updated['id'], 'entity_type' => 'workforce-plan', 'record' => $updated, 'message' => 'Draft workforce plan updated.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_validation_failed', $e->getMessage(), 400 );
		}
	}

	public function create_plan_line( $plan_id, $data, $key ) {
		try {
			$plan = $this->record_or_error( 'workforce_plans', $plan_id );
			if ( is_wp_error( $plan ) ) return $plan;
			if ( 'DRAFT' !== $plan['status'] ) return $this->error( 'hrx_plan_locked', 'Lines may be added only while the workforce plan is DRAFT.', 409 );
			$line_code = $this->required_text( $data, 'line_code', 'Plan line code', 80 );
			$title = $this->required_text( $data, 'position_title', 'Position title', 190 );
			$openings = $this->bounded_count( $data['openings'] ?? 1, 'Openings', 1000 );
			$monthly = $this->nonnegative_money( $data['estimated_monthly_cost_etb'] ?? '0', 'Estimated monthly cost' );
			$business_case = trim( sanitize_textarea_field( (string) ( $data['business_case'] ?? '' ) ) );
			if ( strlen( $business_case ) < 10 || strlen( $business_case ) > 4000 ) throw new InvalidArgumentException( 'Business case must contain 10–4000 characters.' );
			$row = $this->repo->insert( 'plan_lines', array(
				'plan_id' => (int) $plan_id, 'line_code' => $line_code, 'position_title' => $title,
				'grade_code' => $this->optional_text( $data, 'grade_code', 80 ), 'openings' => $openings,
				'estimated_monthly_cost_etb' => $monthly, 'location_id' => (int) $plan['location_id'],
				'business_case' => $business_case, 'status' => 'DRAFT', 'version_no' => 1,
				'created_by' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ),
			) );
			$this->write_event( 'workforce-plan-line', $row, 'PLAN_LINE_CREATED', null, 'DRAFT', $key, 'Plan line created.' );
			return array( 'id' => (int) $row['id'], 'entity_type' => 'plan-line', 'record' => $row, 'message' => 'Workforce plan line created.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_validation_failed', $e->getMessage(), 400 );
		}
	}

	public function update_plan_line( $plan_id, $line_id, $data, $key ) {
		try {
			$plan = $this->record_or_error( 'workforce_plans', $plan_id );
			if ( is_wp_error( $plan ) ) return $plan;
			$row = $this->record_or_error( 'plan_lines', $line_id );
			if ( is_wp_error( $row ) ) return $row;
			if ( (int) $row['plan_id'] !== (int) $plan_id ) return $this->error( 'hrx_line_parent_mismatch', 'The plan line does not belong to this workforce plan.', 404 );
			if ( 'DRAFT' !== $plan['status'] || 'DRAFT' !== $row['status'] ) return $this->error( 'hrx_plan_locked', 'Only draft workforce plan lines may be edited.', 409 );
			$version = $this->positive_int( $data['version_no'] ?? null, 'Record version' );
			if ( $version !== (int) $row['version_no'] ) return $this->error( 'hrx_stale_version', 'This plan line changed. Reload it before editing.', 409 );
			$changes = array();
			if ( array_key_exists( 'position_title', $data ) ) $changes['position_title'] = $this->required_text( $data, 'position_title', 'Position title', 190 );
			if ( array_key_exists( 'grade_code', $data ) ) $changes['grade_code'] = $this->optional_text( $data, 'grade_code', 80 );
			if ( array_key_exists( 'openings', $data ) ) $changes['openings'] = $this->bounded_count( $data['openings'], 'Openings', 1000 );
			if ( array_key_exists( 'estimated_monthly_cost_etb', $data ) ) $changes['estimated_monthly_cost_etb'] = $this->nonnegative_money( $data['estimated_monthly_cost_etb'], 'Estimated monthly cost' );
			if ( array_key_exists( 'business_case', $data ) ) {
				$j = trim( sanitize_textarea_field( (string) $data['business_case'] ) );
				if ( strlen( $j ) < 10 || strlen( $j ) > 4000 ) throw new InvalidArgumentException( 'Business case must contain 10–4000 characters.' );
				$changes['business_case'] = $j;
			}
			$updated = $this->repo->update_versioned( 'plan_lines', $line_id, $version, $changes );
			$this->write_event( 'workforce-plan-line', $updated, 'PLAN_LINE_UPDATED', 'DRAFT', 'DRAFT', $key, 'Draft workforce plan line fields updated.' );
			return array( 'id' => (int) $updated['id'], 'entity_type' => 'plan-line', 'record' => $updated, 'message' => 'Draft workforce plan line updated.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_validation_failed', $e->getMessage(), 400 );
		}
	}

	public function submit_plan( $id, $data, $key ) {
		try {
			$plan = $this->record_or_error( 'workforce_plans', $id );
			if ( is_wp_error( $plan ) ) return $plan;
			if ( 'DRAFT' !== $plan['status'] ) return $this->error( 'hrx_invalid_transition', 'Only a DRAFT workforce plan may be submitted.', 409 );
			$lines = $this->repo->list_rows( 'plan_lines', array( 'plan_id' => (int) $id ), $this->access->scoped_location_ids(), $this->access->company_scope() );
			if ( ! $lines ) return $this->error( 'hrx_plan_requires_lines', 'Add at least one planned position before submitting the plan.', 422 );
			$version = $this->positive_int( $data['version_no'] ?? null, 'Record version' );
			if ( $version !== (int) $plan['version_no'] ) return $this->error( 'hrx_stale_version', 'The workforce plan changed. Reload it before submitting.', 409 );
			$updated = $this->repo->transition( 'workforce_plans', $id, $version, 'DRAFT', 'SUBMITTED', array(
				'submitted_by' => get_current_user_id(), 'submitted_at' => current_time( 'mysql', true ),
			) );
			$this->write_event( 'workforce-plan', $updated, 'WORKFORCE_PLAN_SUBMITTED', 'DRAFT', 'SUBMITTED', $key, 'Plan submitted for independent approval.' );
			return array( 'id' => (int) $updated['id'], 'entity_type' => 'workforce-plan', 'record' => $updated, 'message' => 'Workforce plan submitted for independent Owner review.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_operation_failed', 'The workforce plan was not submitted. Reload and retry if it remains in DRAFT.', 409 );
		}
	}

	public function decide_plan( $id, $data, $key ) {
		try {
			$plan = $this->record_or_error( 'workforce_plans', $id );
			if ( is_wp_error( $plan ) ) return $plan;
			if ( 'SUBMITTED' !== $plan['status'] ) return $this->error( 'hrx_invalid_transition', 'Only a submitted workforce plan may be decided.', 409 );
			if ( (int) $plan['created_by'] === get_current_user_id() ) return $this->error( 'hrx_self_approval_blocked', 'The maker cannot approve their own workforce plan.', 403 );
			$decision = strtoupper( sanitize_key( (string) ( $data['decision'] ?? '' ) ) );
			if ( ! in_array( $decision, array( 'APPROVE', 'REJECT' ), true ) ) return $this->error( 'hrx_decision_invalid', 'Choose APPROVE or REJECT.', 400 );
			$note = trim( sanitize_textarea_field( (string) ( $data['decision_note'] ?? '' ) ) );
			if ( strlen( $note ) < 20 || strlen( $note ) > 2000 ) return $this->error( 'hrx_decision_reason_required', 'Record a decision rationale of 20–2000 characters.', 422 );
			$version = $this->positive_int( $data['version_no'] ?? null, 'Record version' );
			if ( $version !== (int) $plan['version_no'] ) return $this->error( 'hrx_stale_version', 'The workforce plan changed. Reload it before deciding.', 409 );
			$to = 'APPROVE' === $decision ? 'APPROVED' : 'REJECTED';
			$changes = array( 'decision_note' => $note );
			$evidence = '';
			if ( 'APPROVE' === $decision ) {
				$evidence = $this->required_text( $data, 'budget_reference', 'Budget authorization reference', 190 );
				$budget = $this->nonnegative_money( $data['approved_budget_etb'] ?? null, 'Approved budget' );
				if ( 0 === $this->compare_money( $budget, '0.00' ) ) return $this->error( 'hrx_budget_invalid', 'Approved budget must be greater than zero.', 422 );
				$annual_cost = $this->repo->estimated_annual_cost_for_plan( $id );
				if ( $this->compare_money( $budget, $annual_cost ) < 0 ) return $this->error( 'hrx_budget_below_plan_cost', 'The approved budget is below the annualized estimate for the proposed workforce plan.', 422 );
				$changes['budget_reference'] = $evidence;
				$changes['approved_budget_etb'] = $budget;
				$changes['approved_by'] = get_current_user_id();
				$changes['approved_at'] = current_time( 'mysql', true );
			}
			$updated = $this->repo->transition( 'workforce_plans', $id, $version, 'SUBMITTED', $to, $changes );
			$lines = $this->repo->list_rows( 'plan_lines', array( 'plan_id' => (int) $id ), $this->access->scoped_location_ids(), $this->access->company_scope() );
			foreach ( $lines as $line ) {
				if ( 'DRAFT' === $line['status'] ) {
					$line_updated = $this->repo->transition( 'plan_lines', (int) $line['id'], (int) $line['version_no'], 'DRAFT', $to, array() );
					$this->write_event( 'workforce-plan-line', $line_updated, 'PLAN_LINE_' . $decision, 'DRAFT', $to, hash( 'sha256', $key . ':line:' . $line['id'] ), $note, $evidence );
				}
			}
			$this->write_event( 'workforce-plan', $updated, 'WORKFORCE_PLAN_' . $decision, 'SUBMITTED', $to, $key, $note, $evidence );
			return array( 'id' => (int) $updated['id'], 'entity_type' => 'workforce-plan', 'record' => $updated, 'message' => 'Workforce plan ' . strtolower( $to ) . '.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_operation_failed', 'The workforce plan decision was not committed. Reload and verify its state.', 409 );
		}
	}

	public function list_positions( $filters = array() ) {
		return array( 'positions' => $this->repo->list_rows( 'positions', $filters, $this->access->scoped_location_ids(), $this->access->company_scope() ) );
	}

	public function create_position( $data, $key ) {
		try {
			$plan_id = $this->positive_int( $data['workforce_plan_id'] ?? null, 'Approved workforce plan' );
			$line_id = $this->positive_int( $data['plan_line_id'] ?? null, 'Approved workforce plan line' );
			$plan = $this->record_or_error( 'workforce_plans', $plan_id );
			if ( is_wp_error( $plan ) ) return $plan;
			$line = $this->record_or_error( 'plan_lines', $line_id );
			if ( is_wp_error( $line ) ) return $line;
			if ( 'APPROVED' !== $plan['status'] || 'APPROVED' !== $line['status'] || (int) $line['plan_id'] !== $plan_id ) return $this->error( 'hrx_approved_plan_required', 'A position can only be created from a line on an approved workforce plan.', 422 );
			$code = $this->required_text( $data, 'position_code', 'Position code', 80 );
			$title = $this->required_text( array( 'title' => $line['position_title'] ), 'title', 'Position title', 190 );
			$org_id = isset( $data['org_unit_id'] ) && '' !== (string) $data['org_unit_id'] ? $this->positive_int( $data['org_unit_id'], 'Organization unit' ) : null;
			if ( $org_id ) {
				$org = $this->repo->find_org_unit( $org_id );
				if ( ! $org || 'ACTIVE' !== $org['status'] ) return $this->error( 'hrx_org_unit_invalid', 'Select an existing active organization unit.', 422 );
			}
			$reporting_id = isset( $data['reporting_position_id'] ) && '' !== (string) $data['reporting_position_id'] ? $this->positive_int( $data['reporting_position_id'], 'Reporting position' ) : null;
			if ( $reporting_id ) {
				$manager_position = $this->repo->get( 'positions', $reporting_id );
				if ( ! $manager_position || (int) $manager_position['location_id'] !== (int) $plan['location_id'] || ! in_array( $manager_position['status'], array( 'APPROVED', 'ACTIVE' ), true ) ) return $this->error( 'hrx_reporting_position_invalid', 'The reporting position must be approved and at the same location.', 422 );
				if ( isset( $data['position_code'] ) && $manager_position['position_code'] === $code ) return $this->error( 'hrx_reporting_cycle', 'A position cannot report to itself.', 422 );
			}
			$row = $this->repo->insert( 'positions', array(
				'position_code' => $code, 'title' => $title, 'department' => $plan['department'],
				'org_unit_id' => $org_id, 'location_id' => (int) $line['location_id'],
				'workforce_plan_id' => $plan_id, 'plan_line_id' => $line_id,
				'grade_code' => $this->optional_text( array( 'grade_code' => $line['grade_code'] ), 'grade_code', 80 ),
				'reporting_position_id' => $reporting_id, 'planned_headcount' => (int) $line['openings'],
				'filled_headcount' => 0, 'status' => 'DRAFT', 'version_no' => 1,
				'created_by' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ),
			) );
			$this->write_event( 'position', $row, 'POSITION_CREATED_FROM_APPROVED_PLAN', null, 'DRAFT', $key, 'Position created from an approved workforce plan line.', $plan['plan_code'] . ':' . $line['line_code'] );
			return array( 'id' => (int) $row['id'], 'entity_type' => 'position', 'record' => $row, 'message' => 'Draft position created from approved workforce plan.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_validation_failed', $e->getMessage(), 400 );
		}
	}

	public function submit_position( $id, $data, $key ) {
		try {
			$row = $this->record_or_error( 'positions', $id );
			if ( is_wp_error( $row ) ) return $row;
			if ( 'DRAFT' !== $row['status'] ) return $this->error( 'hrx_invalid_transition', 'Only a draft position can be submitted.', 409 );
			$version = $this->positive_int( $data['version_no'] ?? null, 'Record version' );
			if ( $version !== (int) $row['version_no'] ) return $this->error( 'hrx_stale_version', 'The position changed. Reload before submitting.', 409 );
			$updated = $this->repo->transition( 'positions', $id, $version, 'DRAFT', 'SUBMITTED', array() );
			$this->write_event( 'position', $updated, 'POSITION_SUBMITTED', 'DRAFT', 'SUBMITTED', $key, 'Position submitted for Owner approval.' );
			return array( 'id' => (int) $updated['id'], 'entity_type' => 'position', 'record' => $updated, 'message' => 'Position submitted for independent approval.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_operation_failed', 'Position submission failed; confirm current state before retrying.', 409 );
		}
	}

	public function decide_position( $id, $data, $key ) {
		try {
			$row = $this->record_or_error( 'positions', $id );
			if ( is_wp_error( $row ) ) return $row;
			if ( 'SUBMITTED' !== $row['status'] ) return $this->error( 'hrx_invalid_transition', 'Only a submitted position may be decided.', 409 );
			if ( (int) $row['created_by'] === get_current_user_id() ) return $this->error( 'hrx_self_approval_blocked', 'The maker cannot approve their own position.', 403 );
			$decision = strtoupper( sanitize_key( (string) ( $data['decision'] ?? '' ) ) );
			if ( ! in_array( $decision, array( 'APPROVE', 'REJECT' ), true ) ) return $this->error( 'hrx_decision_invalid', 'Choose APPROVE or REJECT.', 400 );
			$note = trim( sanitize_textarea_field( (string) ( $data['decision_note'] ?? '' ) ) );
			if ( strlen( $note ) < 20 || strlen( $note ) > 2000 ) return $this->error( 'hrx_decision_reason_required', 'A decision rationale of 20–2000 characters is required.', 422 );
			$version = $this->positive_int( $data['version_no'] ?? null, 'Record version' );
			if ( $version !== (int) $row['version_no'] ) return $this->error( 'hrx_stale_version', 'The position changed. Reload before deciding.', 409 );
			$to = 'APPROVE' === $decision ? 'APPROVED' : 'REJECTED';
			$changes = array( 'decision_note' => $note );
			$evidence = '';
			if ( 'APPROVE' === $decision ) {
				$evidence = $this->required_text( $data, 'approval_reference', 'Position approval reference', 190 );
				$changes['approval_reference'] = $evidence;
				$changes['approved_by'] = get_current_user_id();
				$changes['approved_at'] = current_time( 'mysql', true );
			}
			$updated = $this->repo->transition( 'positions', $id, $version, 'SUBMITTED', $to, $changes );
			$this->write_event( 'position', $updated, 'POSITION_' . $decision, 'SUBMITTED', $to, $key, $note, $evidence );
			return array( 'id' => (int) $updated['id'], 'entity_type' => 'position', 'record' => $updated, 'message' => 'Position ' . strtolower( $to ) . '.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_operation_failed', 'The position decision was not committed. Verify its current state.', 409 );
		}
	}

	public function activate_position( $id, $data, $key ) {
		try {
			$row = $this->record_or_error( 'positions', $id );
			if ( is_wp_error( $row ) ) return $row;
			if ( 'APPROVED' !== $row['status'] || empty( $row['approval_reference'] ) || empty( $row['approved_by'] ) ) return $this->error( 'hrx_position_approval_required', 'An Owner-approved position and approval reference are required before activation.', 422 );
			$version = $this->positive_int( $data['version_no'] ?? null, 'Record version' );
			if ( $version !== (int) $row['version_no'] ) return $this->error( 'hrx_stale_version', 'The position changed. Reload before activation.', 409 );
			$updated = $this->repo->transition( 'positions', $id, $version, 'APPROVED', 'ACTIVE', array() );
			$this->write_event( 'position', $updated, 'POSITION_ACTIVATED', 'APPROVED', 'ACTIVE', $key, 'Approved position activated for requisitions.' );
			return array( 'id' => (int) $updated['id'], 'entity_type' => 'position', 'record' => $updated, 'message' => 'Approved position activated.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_operation_failed', 'Position activation was not confirmed.', 409 );
		}
	}

	public function list_requisitions( $filters = array() ) {
		return array( 'requisitions' => $this->repo->list_rows( 'requisitions', $filters, $this->access->scoped_location_ids(), $this->access->company_scope() ) );
	}

	public function create_requisition( $data, $key ) {
		try {
			$position_id = $this->positive_int( $data['position_id'] ?? null, 'Approved position' );
			$position = $this->record_or_error( 'positions', $position_id );
			if ( is_wp_error( $position ) ) return $position;
			if ( 'ACTIVE' !== $position['status'] ) return $this->error( 'hrx_active_position_required', 'A vacancy requisition must reference an active, approved position.', 422 );
			$openings = $this->bounded_count( $data['openings'] ?? 1, 'Openings', 1000 );
			$available = (int) $position['planned_headcount'] - (int) $position['filled_headcount'];
			if ( $openings > $available ) return $this->error( 'hrx_headcount_exceeded', 'Requested openings exceed the unfilled approved headcount.', 422 );
			$justification = trim( sanitize_textarea_field( (string) ( $data['justification'] ?? '' ) ) );
			if ( strlen( $justification ) < 20 || strlen( $justification ) > 4000 ) return $this->error( 'hrx_justification_required', 'Vacancy justification must contain 20–4000 characters.', 422 );
			$row = $this->repo->insert( 'requisitions', array(
				'requisition_code' => $this->required_text( $data, 'requisition_code', 'Requisition code', 80 ),
				'plan_id' => (int) $position['workforce_plan_id'], 'plan_line_id' => (int) $position['plan_line_id'],
				'position_id' => $position_id, 'location_id' => (int) $position['location_id'],
				'openings' => $openings, 'justification' => $justification,
				'status' => 'DRAFT', 'version_no' => 1, 'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			) );
			$this->write_event( 'vacancy-requisition', $row, 'REQUISITION_CREATED', null, 'DRAFT', $key, 'Vacancy requisition created.', $position['position_code'] );
			return array( 'id' => (int) $row['id'], 'entity_type' => 'requisition', 'record' => $row, 'message' => 'Draft vacancy requisition created.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_validation_failed', $e->getMessage(), 400 );
		}
	}

	public function submit_requisition( $id, $data, $key ) {
		try {
			$row = $this->record_or_error( 'requisitions', $id );
			if ( is_wp_error( $row ) ) return $row;
			if ( 'DRAFT' !== $row['status'] ) return $this->error( 'hrx_invalid_transition', 'Only a draft requisition can be submitted.', 409 );
			$version = $this->positive_int( $data['version_no'] ?? null, 'Record version' );
			if ( $version !== (int) $row['version_no'] ) return $this->error( 'hrx_stale_version', 'The requisition changed. Reload before submitting.', 409 );
			$updated = $this->repo->transition( 'requisitions', $id, $version, 'DRAFT', 'SUBMITTED', array( 'submitted_by' => get_current_user_id(), 'submitted_at' => current_time( 'mysql', true ) ) );
			$this->write_event( 'vacancy-requisition', $updated, 'REQUISITION_SUBMITTED', 'DRAFT', 'SUBMITTED', $key, 'Requisition submitted for Owner approval.' );
			return array( 'id' => (int) $updated['id'], 'entity_type' => 'requisition', 'record' => $updated, 'message' => 'Requisition submitted for independent approval.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_operation_failed', 'The requisition was not submitted. Verify its current state.', 409 );
		}
	}

	public function decide_requisition( $id, $data, $key ) {
		try {
			$row = $this->record_or_error( 'requisitions', $id );
			if ( is_wp_error( $row ) ) return $row;
			if ( 'SUBMITTED' !== $row['status'] ) return $this->error( 'hrx_invalid_transition', 'Only a submitted requisition may be decided.', 409 );
			if ( (int) $row['created_by'] === get_current_user_id() ) return $this->error( 'hrx_self_approval_blocked', 'The maker cannot approve their own requisition.', 403 );
			$decision = strtoupper( sanitize_key( (string) ( $data['decision'] ?? '' ) ) );
			if ( ! in_array( $decision, array( 'APPROVE', 'REJECT' ), true ) ) return $this->error( 'hrx_decision_invalid', 'Choose APPROVE or REJECT.', 400 );
			$note = trim( sanitize_textarea_field( (string) ( $data['decision_note'] ?? '' ) ) );
			if ( strlen( $note ) < 20 || strlen( $note ) > 2000 ) return $this->error( 'hrx_decision_reason_required', 'A decision rationale of 20–2000 characters is required.', 422 );
			$version = $this->positive_int( $data['version_no'] ?? null, 'Record version' );
			if ( $version !== (int) $row['version_no'] ) return $this->error( 'hrx_stale_version', 'The requisition changed. Reload before deciding.', 409 );
			$to = 'APPROVE' === $decision ? 'APPROVED' : 'REJECTED';
			$changes = array( 'decision_note' => $note );
			$evidence = '';
			if ( 'APPROVE' === $decision ) {
				$evidence = $this->required_text( $data, 'budget_reference', 'Budget authorization reference', 190 );
				$changes['budget_reference'] = $evidence;
				$changes['approved_by'] = get_current_user_id();
				$changes['approved_at'] = current_time( 'mysql', true );
			}
			$updated = $this->repo->transition( 'requisitions', $id, $version, 'SUBMITTED', $to, $changes );
			$this->write_event( 'vacancy-requisition', $updated, 'REQUISITION_' . $decision, 'SUBMITTED', $to, $key, $note, $evidence );
			return array( 'id' => (int) $updated['id'], 'entity_type' => 'requisition', 'record' => $updated, 'message' => 'Requisition ' . strtolower( $to ) . '.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_operation_failed', 'The requisition decision was not committed. Verify its current state.', 409 );
		}
	}

	public function open_requisition( $id, $data, $key ) {
		try {
			$row = $this->record_or_error( 'requisitions', $id );
			if ( is_wp_error( $row ) ) return $row;
			if ( 'APPROVED' !== $row['status'] || empty( $row['budget_reference'] ) || empty( $row['approved_by'] ) ) return $this->error( 'hrx_requisition_approval_required', 'An approved requisition with budget evidence is required before opening a vacancy.', 422 );
			$position = $this->record_or_error( 'positions', (int) $row['position_id'] );
			if ( is_wp_error( $position ) ) return $position;
			if ( 'ACTIVE' !== $position['status'] ) return $this->error( 'hrx_position_inactive', 'The linked position is not active.', 422 );
			$version = $this->positive_int( $data['version_no'] ?? null, 'Record version' );
			if ( $version !== (int) $row['version_no'] ) return $this->error( 'hrx_stale_version', 'The requisition changed. Reload before opening the vacancy.', 409 );
			$updated = $this->repo->transition( 'requisitions', $id, $version, 'APPROVED', 'OPEN', array( 'opened_by' => get_current_user_id(), 'opened_at' => current_time( 'mysql', true ) ) );
			$this->write_event( 'vacancy-requisition', $updated, 'VACANCY_OPENED', 'APPROVED', 'OPEN', $key, 'Vacancy opened after independent approval.', $row['budget_reference'] );
			return array( 'id' => (int) $updated['id'], 'entity_type' => 'requisition', 'record' => $updated, 'message' => 'Approved vacancy opened for recruitment.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_operation_failed', 'Vacancy opening was not confirmed.', 409 );
		}
	}
}
