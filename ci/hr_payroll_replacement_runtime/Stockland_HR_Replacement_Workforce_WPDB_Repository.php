<?php
/**
 * Fixed-allowlist repository for the HR replacement workforce domain.
 *
 * This adapter intentionally uses the installed WordPress DB connection only.
 * All SQL identifiers are selected from a closed server-side list; values use
 * wpdb prepare/insert/update APIs. DDL is owned by the separate opt-in schema class.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

final class Stockland_HR_Replacement_Workforce_WPDB_Repository {
	private $tables;

	public function __construct() {
		$this->tables = Stockland_HR_Replacement_Workforce_Schema::table_names();
	}

	public function document_blob_table() {
		return $this->table( 'document_blobs' );
	}

	private function table( $key ) {
		if ( ! isset( $this->tables[ $key ] ) ) {
			throw new InvalidArgumentException( 'Unknown HR replacement table key.' );
		}
		return $this->tables[ $key ];
	}

	public function begin() {
		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			throw new RuntimeException( 'Database transaction could not start.' );
		}
	}

	public function commit() {
		global $wpdb;
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			throw new RuntimeException( 'Database transaction could not commit.' );
		}
	}

	public function rollback() {
		global $wpdb;
		$wpdb->query( 'ROLLBACK' );
	}

	public function receipt( $key ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE idempotency_key = %s LIMIT 1', $this->table( 'command_receipts' ), $key ),
			ARRAY_A
		);
	}

	public function create_receipt( $key, $actor, $operation, $request_hash ) {
		global $wpdb;
		$ok = $wpdb->insert(
			$this->table( 'command_receipts' ),
			array(
				'idempotency_key' => $key,
				'actor_id'        => absint( $actor ),
				'operation_code'  => sanitize_key( $operation ),
				'request_sha256'  => $request_hash,
				'result_status'   => 'PROCESSING',
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
		if ( false === $ok ) {
			throw new RuntimeException( 'Command receipt could not be recorded.' );
		}
		return (int) $wpdb->insert_id;
	}

	public function finish_receipt( $receipt_id, $result, $entity_type = null, $entity_id = null ) {
		global $wpdb;
		$updated = $wpdb->update(
			$this->table( 'command_receipts' ),
			array(
				'result_json' => wp_json_encode( $result ),
				'result_status' => 'COMPLETED',
				'entity_type' => $entity_type ? sanitize_key( $entity_type ) : null,
				'entity_id' => $entity_id ? absint( $entity_id ) : null,
				'completed_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $receipt_id ), 'result_status' => 'PROCESSING' ),
			array( '%s', '%s', '%s', '%d', '%s' ),
			array( '%d', '%s' )
		);
		if ( false === $updated || 1 !== (int) $updated ) {
			throw new RuntimeException( 'Command receipt completion could not be verified.' );
		}
	}

	public function get( $entity, $id, $for_update = false ) {
		global $wpdb;
		$table = $this->table( $entity );
		$sql = $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ), $table, absint( $id ) );
		$row = $wpdb->get_row( $sql, ARRAY_A );
		if ( '' !== (string) $wpdb->last_error ) {
			throw new RuntimeException( 'Record read failed.' );
		}
		return is_array( $row ) ? $row : null;
	}

	public function estimated_annual_cost_for_plan( $plan_id ) {
		global $wpdb;
		$total = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT CAST(COALESCE(SUM(estimated_monthly_cost_etb * openings * 12), 0.00) AS DECIMAL(24,2)) FROM %i WHERE plan_id = %d',
				$this->table( 'plan_lines' ),
				absint( $plan_id )
			)
		);
		if ( '' !== (string) $wpdb->last_error || ! is_scalar( $total ) || ! preg_match( '/^[0-9]+(?:\\.[0-9]{1,2})?$/', (string) $total ) ) {
			throw new RuntimeException( 'Planned annual workforce cost could not be calculated.' );
		}
		$parts = explode( '.', (string) $total, 2 );
		return $parts[0] . '.' . str_pad( isset( $parts[1] ) ? $parts[1] : '', 2, '0' );
	}

	public function find_open_employment_for_candidate( $candidate_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE candidate_id = %d AND status NOT IN ('ENDED','CANCELLED','DECLINED','REJECTED','WITHDRAWN') ORDER BY id DESC LIMIT 1",
				$this->table( 'employments' ),
				absint( $candidate_id )
			),
			ARRAY_A
		);
		if ( '' !== (string) $wpdb->last_error ) {
			throw new RuntimeException( 'Candidate employment state could not be checked.' );
		}
		return is_array( $row ) ? $row : null;
	}

	public function count_applications_for_requisition( $requisition_id, $status ) {
		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE requisition_id = %d AND status = %s',
				$this->table( 'applications' ),
				absint( $requisition_id ),
				sanitize_key( $status )
			)
		);
		if ( '' !== (string) $wpdb->last_error || ! is_numeric( $count ) ) {
			throw new RuntimeException( 'Requisition application count could not be checked.' );
		}
		return (int) $count;
	}

	public function reserve_position_slot( $position_id, $expected_version ) {
		global $wpdb;
		$table = $this->table( 'positions' );
		$result = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET reserved_headcount = reserved_headcount + 1, version_no = version_no + 1, updated_by = %d, updated_at = %s WHERE id = %d AND version_no = %d AND status = %s AND (filled_headcount + reserved_headcount) < planned_headcount',
				$table,
				get_current_user_id(),
				current_time( 'mysql', true ),
				absint( $position_id ),
				absint( $expected_version ),
				'ACTIVE'
			)
		);
		if ( false === $result || 1 !== (int) $result ) {
			throw new RuntimeException( 'No unreserved position headcount is available or the position changed concurrently.' );
		}
		return $this->get( 'positions', $position_id );
	}

	public function convert_reserved_headcount_to_filled( $position_id, $expected_version ) {
		global $wpdb;
		$table = $this->table( 'positions' );
		$result = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET reserved_headcount = reserved_headcount - 1, filled_headcount = filled_headcount + 1, version_no = version_no + 1, updated_by = %d, updated_at = %s WHERE id = %d AND version_no = %d AND reserved_headcount > 0 AND (filled_headcount + reserved_headcount) <= planned_headcount',
				$table,
				get_current_user_id(),
				current_time( 'mysql', true ),
				absint( $position_id ),
				absint( $expected_version )
			)
		);
		if ( false === $result || 1 !== (int) $result ) {
			throw new RuntimeException( 'Reserved headcount could not be converted to filled headcount.' );
		}
		return $this->get( 'positions', $position_id );
	}

	public function release_reserved_headcount( $position_id, $expected_version ) {
		global $wpdb;
		$table = $this->table( 'positions' );
		$result = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET reserved_headcount = reserved_headcount - 1, version_no = version_no + 1, updated_by = %d, updated_at = %s WHERE id = %d AND version_no = %d AND reserved_headcount > 0',
				$table,
				get_current_user_id(),
				current_time( 'mysql', true ),
				absint( $position_id ),
				absint( $expected_version )
			)
		);
		if ( false === $result || 1 !== (int) $result ) {
			throw new RuntimeException( 'Reserved headcount could not be released safely.' );
		}
		return $this->get( 'positions', $position_id );
	}

	public function find_org_unit( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1', $this->table( 'org_units' ), absint( $id ) ), ARRAY_A );
		if ( '' !== (string) $wpdb->last_error ) {
			throw new RuntimeException( 'Organization unit read failed.' );
		}
		return is_array( $row ) ? $row : null;
	}

	public function list_rows( $entity, $filters = array(), $location_ids = array(), $company_scope = false ) {
		global $wpdb;
		$table = $this->table( $entity );
		$allowed_filters = array(
			'org_units' => array( 'status', 'unit_type' ),
			'workforce_plans' => array( 'status', 'fiscal_year', 'department', 'location_id' ),
			'plan_lines' => array( 'plan_id', 'status', 'location_id' ),
			'positions' => array( 'status', 'department', 'location_id', 'org_unit_id' ),
			'requisitions' => array( 'status', 'position_id', 'plan_id', 'location_id' ),
			'applications' => array( 'status', 'stage', 'candidate_id', 'requisition_id', 'location_id' ),
			'employments' => array( 'status', 'person_id', 'position_id', 'requisition_id', 'location_id' ),
			'interviews' => array( 'status', 'application_id', 'stage_code' ),
			'selection_decisions' => array( 'status', 'application_id', 'candidate_id', 'recommendation' ),
			'contracts' => array( 'status', 'employment_id' ),
			'onboarding_tasks' => array( 'status', 'employment_id', 'task_type', 'assigned_to_user' ),
			'documents' => array( 'status', 'subject_type', 'subject_id', 'document_type' ),
			'shift_assignments' => array( 'status', 'employment_id', 'shift_template_id' ),
			'time_events' => array( 'status', 'employment_id', 'work_date', 'event_code' ),
			'attendance_days' => array( 'status', 'employment_id', 'work_date' ),
			'leave_requests' => array( 'status', 'employment_id', 'leave_type' ),
			'leave_movements' => array( 'employment_id', 'leave_type', 'movement_type' ),
			'compensation_assignments' => array( 'status', 'employment_id' ),
			'payroll_lines' => array( 'status', 'payroll_run_id', 'employment_id' ),
			'payroll_adjustments' => array( 'status', 'employment_id', 'payroll_run_id' ),
			'payout_instructions' => array( 'status', 'payee_type', 'payee_id', 'employment_id', 'payout_batch_id' ),
			'final_settlements' => array( 'status', 'exit_case_id' ),
			'compensation_assignment_lines' => array( 'status', 'compensation_assignment_id', 'component_id' ),
			'statutory_rule_sets' => array( 'rule_type', 'status' ),
			'statutory_rule_vectors' => array( 'rule_set_id', 'status' ),
			'work_calendars' => array( 'status' ),
			'calendar_days' => array( 'calendar_id', 'day_date', 'day_type' ),
			'shift_templates' => array( 'status', 'work_calendar_id' ),
			'leave_policies' => array( 'status', 'leave_type' ),
			'payroll_runs' => array( 'status', 'period_year', 'period_month' ),
			'payroll_approvals' => array( 'payroll_run_id', 'stage_code', 'decision' ),
			'payroll_postings' => array( 'payroll_run_id', 'status' ),
			'payout_batches' => array( 'status', 'payroll_run_id', 'settlement_id' ),
			'payout_attempts' => array( 'payout_instruction_id', 'status' ),
			'statutory_obligations' => array( 'status', 'obligation_type', 'payroll_run_id' ),
			'statutory_remittances' => array( 'status', 'obligation_id' ),
			'exit_cases' => array( 'status', 'employment_id' ),
			'exit_clearance_tasks' => array( 'status', 'exit_case_id' ),
			'final_settlement_items' => array( 'settlement_id', 'component_code' ),
			'individual_payees' => array( 'status', 'person_id', 'person_kind' ),
			'service_engagements' => array( 'status', 'payee_id', 'engagement_type' ),
			'service_deliverables' => array( 'status', 'engagement_id' ),
			'withholding_assessments' => array( 'status', 'payee_id', 'engagement_id' ),
			'payee_payment_requests' => array( 'status', 'payee_id', 'engagement_id' ),
			'payee_payment_lines' => array( 'request_id', 'component_code' ),
			'payee_payment_attempts' => array( 'request_id', 'status' ),
			'access_audit_events' => array( 'subject_type', 'subject_id', 'actor_id', 'action_code' ),
			'social_insight_snapshots' => array( 'platform_code', 'reporting_period_start', 'reporting_period_end' ),
		);
		if ( ! isset( $allowed_filters[ $entity ] ) ) {
			throw new InvalidArgumentException( 'Unsupported HR replacement list entity.' );
		}
		$where = array( '1=1' );
		$args = array();
		foreach ( $allowed_filters[ $entity ] as $key ) {
			if ( ! array_key_exists( $key, $filters ) || '' === (string) $filters[ $key ] || null === $filters[ $key ] ) {
				continue;
			}
			$value = $filters[ $key ];
			if ( in_array( $key, array( 'location_id', 'plan_id', 'position_id', 'org_unit_id' ), true ) ) {
				$where[] = $key . ' = %d';
				$args[] = absint( $value );
			} else {
				$where[] = $key . ' = %s';
				$args[] = sanitize_text_field( (string) $value );
			}
		}
		$linked_table = null;
		if ( ! $company_scope ) {
			$ids = array_values( array_unique( array_filter( array_map( 'absint', $location_ids ) ) ) );
			if ( ! $ids ) {
				return array();
			}
			if ( in_array( 'location_id', $this->columns_for( $entity ), true ) ) {
				$where[] = 'location_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
				$args = array_merge( $args, $ids );
			} elseif ( in_array( $entity, array( 'interviews', 'selection_decisions' ), true ) ) {
				$linked_table = $this->table( 'applications' );
				$where[] = 'application_id IN (SELECT id FROM %i WHERE location_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . '))';
				$args[] = $linked_table;
				$args = array_merge( $args, $ids );
			} elseif ( in_array( $entity, array( 'contracts', 'onboarding_tasks', 'shift_assignments', 'time_events', 'attendance_days', 'leave_requests', 'leave_movements', 'compensation_assignments', 'payroll_lines', 'payroll_adjustments', 'payout_instructions' ), true ) ) {
				$linked_table = $this->table( 'employments' );
				$where[] = 'employment_id IN (SELECT id FROM %i WHERE location_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . '))';
				$args[] = $linked_table;
				$args = array_merge( $args, $ids );
			} elseif ( 'final_settlements' === $entity ) {
				$exit_table = $this->table( 'exit_cases' );
				$employment_table = $this->table( 'employments' );
				$where[] = 'exit_case_id IN (SELECT id FROM %i WHERE employment_id IN (SELECT id FROM %i WHERE location_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')))';
				$args[] = $exit_table;
				$args[] = $employment_table;
				$args = array_merge( $args, $ids );
			} elseif ( 'documents' === $entity ) {
				return array();
			} else {
				return array();
			}
		}
		$sql = 'SELECT * FROM %i WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT 250';
		array_unshift( $args, $table );
		$query = $wpdb->prepare( $sql, $args );
		$rows = $wpdb->get_results( $query, ARRAY_A );
		if ( '' !== (string) $wpdb->last_error ) {
			throw new RuntimeException( 'HR replacement list query failed.' );
		}
		return is_array( $rows ) ? $rows : array();
	}

	private function columns_for( $entity ) {
		$columns = array(
			'org_units' => array( 'id', 'unit_code', 'unit_name', 'unit_type', 'parent_unit_id', 'status', 'version_no', 'created_by', 'created_at', 'updated_by', 'updated_at' ),
			'workforce_plans' => array( 'id', 'plan_code', 'fiscal_year', 'department', 'location_id', 'justification', 'budget_reference', 'approved_budget_etb', 'status', 'version_no', 'created_by', 'created_at', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'decision_note' ),
			'plan_lines' => array( 'id', 'plan_id', 'line_code', 'position_title', 'grade_code', 'openings', 'estimated_monthly_cost_etb', 'location_id', 'business_case', 'status', 'version_no', 'created_by', 'created_at', 'updated_by', 'updated_at' ),
			'positions' => array( 'id', 'position_code', 'title', 'department', 'org_unit_id', 'location_id', 'grade_code', 'reporting_position_id', 'planned_headcount', 'filled_headcount', 'reserved_headcount', 'status', 'version_no', 'created_by', 'created_at', 'updated_by', 'updated_at', 'approval_reference', 'approved_by', 'approved_at', 'decision_note', 'workforce_plan_id', 'plan_line_id' ),
			'requisitions' => array( 'id', 'requisition_code', 'plan_id', 'plan_line_id', 'position_id', 'location_id', 'openings', 'justification', 'budget_reference', 'status', 'version_no', 'created_by', 'created_at', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'opened_by', 'opened_at', 'decision_note' ),
			'people' => array( 'id', 'person_code', 'full_name', 'preferred_name', 'primary_email', 'primary_phone', 'privacy_notice_version', 'processing_basis', 'processing_basis_reference', 'consent_status', 'consent_recorded_at', 'consent_withdrawn_at', 'retention_until', 'person_status', 'version_no', 'created_by', 'created_at', 'updated_by', 'updated_at' ),
			'candidates' => array( 'id', 'candidate_code', 'person_id', 'source_code', 'status', 'privacy_notice_version', 'retention_until', 'withdrawn_at', 'withdrawal_reason', 'version_no', 'created_by', 'created_at', 'updated_by', 'updated_at' ),
			'applications' => array( 'id', 'application_code', 'candidate_id', 'person_id', 'requisition_id', 'location_id', 'stage', 'status', 'applied_at', 'withdrawn_at', 'decision_note', 'version_no', 'created_by', 'created_at', 'updated_by', 'updated_at' ),
			'interviews' => array( 'id', 'interview_code', 'application_id', 'stage_code', 'scheduled_start', 'scheduled_end', 'timezone_code', 'panel_user_ids_json', 'status', 'evaluation_json', 'reviewer_summary', 'evidence_reference', 'completed_by', 'completed_at', 'version_no', 'created_by', 'created_at', 'updated_by', 'updated_at' ),
			'selection_decisions' => array( 'id', 'decision_code', 'application_id', 'candidate_id', 'recommendation', 'rationale', 'evidence_reference', 'recommended_by', 'recommended_at', 'status', 'owner_decision', 'owner_decision_note', 'owner_decided_by', 'owner_decided_at', 'version_no', 'created_by', 'created_at' ),
			'employments' => array( 'id', 'employment_code', 'person_id', 'candidate_id', 'application_id', 'selection_decision_id', 'position_id', 'requisition_id', 'location_id', 'job_title_snapshot', 'department_snapshot', 'employment_type', 'legal_employer_code', 'start_date', 'end_date', 'status', 'activated_by', 'activated_at', 'version_no', 'created_by', 'created_at', 'updated_by', 'updated_at' ),
			'contracts' => array( 'id', 'contract_code', 'employment_id', 'contract_type', 'contract_version', 'start_date', 'end_date', 'probation_end', 'document_id', 'status', 'employee_signed_at', 'employer_signed_at', 'approved_by', 'approved_at', 'version_no', 'created_by', 'created_at', 'updated_by', 'updated_at' ),
			'documents' => array( 'id', 'document_code', 'subject_type', 'subject_id', 'document_type', 'original_filename', 'vault_reference', 'document_sha256', 'content_type', 'file_size_bytes', 'status', 'verified_by', 'verified_at', 'approved_by', 'approved_at', 'issued_on', 'expires_on', 'retention_until', 'version_no', 'created_by', 'created_at' ),
			'onboarding_tasks' => array( 'id', 'task_code', 'employment_id', 'task_type', 'title', 'mandatory', 'assigned_to_role', 'assigned_to_user', 'due_on', 'status', 'evidence_reference', 'completed_by', 'completed_at', 'completion_note', 'version_no', 'created_by', 'created_at', 'updated_by', 'updated_at' ),
			'work_calendars' => array( 'id', 'calendar_code', 'calendar_name', 'timezone_code', 'work_week_json', 'effective_from', 'effective_to', 'status', 'version_no', 'created_by', 'created_at', 'approved_by', 'approved_at' ),
			'calendar_days' => array( 'id', 'calendar_id', 'day_date', 'day_type', 'work_minutes', 'legal_source_reference', 'version_no', 'created_by', 'created_at' ),
			'shift_templates' => array( 'id', 'shift_code', 'shift_name', 'starts_at', 'ends_at', 'break_minutes', 'crosses_midnight', 'work_calendar_id', 'effective_from', 'effective_to', 'status', 'version_no', 'created_by', 'created_at', 'approved_by', 'approved_at' ),
			'shift_assignments' => array( 'id', 'assignment_code', 'employment_id', 'shift_template_id', 'effective_from', 'effective_to', 'status', 'version_no', 'created_by', 'created_at', 'approved_by', 'approved_at' ),
			'time_events' => array( 'id', 'idempotency_key', 'employment_id', 'work_date', 'event_code', 'event_at', 'timezone_code', 'source_code', 'source_reference', 'event_payload_sha256', 'status', 'recorded_by', 'recorded_at', 'voided_by', 'voided_at', 'void_reason' ),
			'attendance_days' => array( 'id', 'attendance_code', 'employment_id', 'work_date', 'shift_assignment_id', 'scheduled_minutes', 'worked_minutes', 'break_minutes', 'overtime_minutes', 'night_minutes', 'rest_day_minutes', 'holiday_minutes', 'absence_minutes', 'source_snapshot_sha256', 'status', 'version_no', 'prepared_by', 'prepared_at', 'approved_by', 'approved_at' ),
			'attendance_adjustments' => array( 'id', 'adjustment_code', 'attendance_day_id', 'before_snapshot_json', 'proposed_snapshot_json', 'change_sha256', 'reason', 'evidence_reference', 'status', 'version_no', 'requested_by', 'requested_at', 'decided_by', 'decided_at', 'decision_note' ),
			'leave_policies' => array( 'id', 'policy_code', 'policy_version', 'leave_type', 'eligibility_json', 'accrual_rules_json', 'carry_forward_rules_json', 'effective_from', 'effective_to', 'legal_source_reference', 'legal_source_sha256', 'status', 'version_no', 'created_by', 'created_at', 'legal_reviewed_by', 'legal_reviewed_at', 'approved_by', 'approved_at' ),
			'leave_requests' => array( 'id', 'request_code', 'employment_id', 'leave_type', 'start_date', 'end_date', 'requested_units', 'reason', 'evidence_reference', 'leave_policy_id', 'status', 'version_no', 'requested_by', 'requested_at', 'submitted_at', 'decided_by', 'decided_at', 'decision_note' ),
			'leave_movements' => array( 'id', 'movement_code', 'employment_id', 'leave_type', 'effective_date', 'units_delta', 'movement_type', 'source_type', 'source_id', 'policy_version', 'idempotency_key', 'reason', 'actor_id', 'created_at' ),
			'compensation_assignments' => array( 'id', 'assignment_code', 'employment_id', 'grade_code', 'pay_frequency', 'currency_code', 'base_amount', 'effective_from', 'effective_to', 'reason', 'contract_reference', 'status', 'version_no', 'created_by', 'created_at', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at' ),
			'compensation_components' => array( 'id', 'component_code', 'component_name', 'component_kind', 'calculation_method', 'description', 'status', 'version_no', 'created_by', 'created_at', 'approved_by', 'approved_at' ),
			'compensation_assignment_lines' => array( 'id', 'line_code', 'compensation_assignment_id', 'component_id', 'amount', 'quantity', 'unit_rate', 'currency_code', 'effective_from', 'effective_to', 'source_reference', 'status', 'version_no', 'created_by', 'created_at' ),
			'statutory_rule_sets' => array( 'id', 'rule_code', 'rule_version', 'rule_type', 'jurisdiction', 'instrument_title', 'article_clause', 'official_source_url', 'official_source_sha256', 'publication_date', 'effective_from', 'effective_to', 'scope_json', 'parameters_json', 'interpretation_note', 'test_vectors_sha256', 'status', 'version_no', 'created_by', 'created_at', 'legal_reviewer_reference', 'legal_reviewed_by', 'legal_reviewed_at', 'approved_by', 'approved_at', 'supersedes_rule_id' ),
			'statutory_rule_vectors' => array( 'id', 'vector_code', 'rule_set_id', 'input_json', 'expected_output_json', 'input_sha256', 'expected_sha256', 'last_result_json', 'last_result_sha256', 'status', 'runner_version', 'validated_by', 'validated_at' ),
			'payroll_runs' => array( 'id', 'run_code', 'period_year', 'period_month', 'cutoff_at', 'input_snapshot_json', 'input_snapshot_sha256', 'calculation_sha256', 'rule_set_versions_json', 'employee_count', 'gross_total', 'taxable_total', 'paye_total', 'employee_pension_total', 'employer_pension_total', 'deduction_total', 'net_total', 'employer_cost_total', 'status', 'version_no', 'prepared_by', 'prepared_at', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at', 'posted_by', 'posted_at', 'closed_by', 'closed_at', 'journal_reference' ),
			'payroll_lines' => array( 'id', 'line_code', 'payroll_run_id', 'employment_id', 'compensation_assignment_id', 'input_snapshot_json', 'input_snapshot_sha256', 'rule_set_versions_json', 'gross_amount', 'taxable_base', 'paye_amount', 'employee_pension', 'employer_pension', 'other_deductions', 'net_amount', 'employer_cost', 'calculation_trace_json', 'calculation_sha256', 'status', 'version_no', 'created_by', 'created_at' ),
			'payroll_line_items' => array( 'id', 'item_code', 'payroll_line_id', 'component_id', 'source_type', 'source_id', 'description', 'amount', 'quantity', 'unit_rate', 'taxable_decision', 'pensionable_decision', 'statutory_rule_set_id', 'statutory_rule_version', 'source_snapshot_sha256' ),
			'payroll_approvals' => array( 'id', 'approval_code', 'payroll_run_id', 'stage_code', 'decision', 'run_snapshot_sha256', 'decision_note', 'actor_id', 'acted_at' ),
			'payroll_adjustments' => array( 'id', 'adjustment_code', 'payroll_run_id', 'original_payroll_line_id', 'employment_id', 'adjustment_type', 'amount_delta', 'reason', 'source_reference', 'status', 'version_no', 'created_by', 'created_at', 'approved_by', 'approved_at' ),
			'payroll_postings' => array( 'id', 'posting_code', 'payroll_run_id', 'source_hash', 'gl_journal_id', 'debit_total', 'credit_total', 'status', 'idempotency_key', 'posted_by', 'posted_at', 'reversal_of_posting_id' ),
			'payout_batches' => array( 'id', 'batch_code', 'payroll_run_id', 'settlement_id', 'currency_code', 'total_amount', 'status', 'version_no', 'prepared_by', 'prepared_at', 'approved_by', 'approved_at', 'submitted_by', 'submitted_at' ),
			'payout_instructions' => array( 'id', 'instruction_code', 'payout_batch_id', 'payroll_line_id', 'settlement_id', 'employment_id', 'payee_type', 'payee_id', 'amount', 'currency_code', 'method_code', 'recipient_reference_ciphertext', 'status', 'external_reference', 'failure_code', 'failure_note', 'idempotency_key', 'version_no', 'created_by', 'created_at', 'settled_by', 'settled_at' ),
			'payout_attempts' => array( 'id', 'attempt_code', 'payout_instruction_id', 'attempt_no', 'request_sha256', 'response_code', 'response_sha256', 'status', 'external_reference', 'attempted_by', 'attempted_at' ),
			'statutory_obligations' => array( 'id', 'obligation_code', 'obligation_type', 'period_start', 'period_end', 'due_date', 'payroll_run_id', 'rule_set_id', 'amount_due', 'amount_paid', 'status', 'version_no', 'created_by', 'created_at' ),
			'statutory_remittances' => array( 'id', 'remittance_code', 'obligation_id', 'submission_reference', 'receipt_reference', 'submitted_at', 'paid_amount', 'paid_at', 'evidence_document_id', 'status', 'version_no', 'prepared_by', 'prepared_at', 'approved_by', 'approved_at' ),
			'statutory_remittance_items' => array( 'id', 'id', 'remittance_id', 'payroll_line_id', 'employment_id', 'amount', 'reference' ),
			'exit_cases' => array( 'id', 'exit_code', 'employment_id', 'separation_type', 'reason_code', 'reason_note', 'notice_date', 'last_worked_date', 'effective_date', 'evidence_reference', 'dispute_hold', 'dispute_reference', 'status', 'version_no', 'created_by', 'created_at', 'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at' ),
			'exit_clearance_tasks' => array( 'id', 'task_code', 'exit_case_id', 'task_type', 'title', 'assigned_to_role', 'assigned_to_user', 'due_on', 'status', 'evidence_reference', 'completed_by', 'completed_at', 'completion_note' ),
			'final_settlements' => array( 'id', 'settlement_code', 'exit_case_id', 'period_start', 'period_end', 'input_snapshot_json', 'input_snapshot_sha256', 'calculation_trace_json', 'calculation_sha256', 'gross_due', 'tax_due', 'pension_due', 'deductions_due', 'net_due', 'status', 'version_no', 'prepared_by', 'prepared_at', 'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at', 'payout_instruction_id', 'gl_journal_id', 'settled_at' ),
			'final_settlement_items' => array( 'id', 'item_code', 'settlement_id', 'component_code', 'amount', 'source_type', 'source_id', 'rule_set_id', 'rule_version', 'evidence_reference' ),
			'individual_payees' => array( 'id', 'payee_code', 'person_id', 'person_kind', 'legal_name', 'display_name', 'tax_identifier_ciphertext', 'license_reference', 'residency_code', 'registration_status', 'privacy_notice_version', 'status', 'version_no', 'created_by', 'created_at', 'approved_by', 'approved_at' ),
			'service_engagements' => array( 'id', 'engagement_code', 'payee_id', 'engagement_type', 'contract_reference', 'campaign_reference', 'service_description', 'agreed_cash_amount', 'agreed_non_cash_amount', 'currency_code', 'start_date', 'end_date', 'status', 'version_no', 'created_by', 'created_at', 'approved_by', 'approved_at' ),
			'service_deliverables' => array( 'id', 'deliverable_code', 'engagement_id', 'deliverable_type', 'description', 'evidence_reference', 'accepted_by', 'accepted_at', 'accepted_value', 'status', 'created_by', 'created_at' ),
			'withholding_assessments' => array( 'id', 'assessment_code', 'payee_id', 'engagement_id', 'cash_value', 'non_cash_value', 'tax_rule_set_id', 'classification_code', 'withholding_amount', 'legal_source_reference', 'reviewer_reference', 'status', 'version_no', 'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at' ),
			'payee_payment_requests' => array( 'id', 'request_code', 'payee_id', 'engagement_id', 'deliverable_id', 'withholding_assessment_id', 'requested_amount', 'approved_amount', 'payment_due_date', 'status', 'version_no', 'requested_by', 'requested_at', 'approved_by', 'approved_at', 'payout_instruction_id' ),
			'payee_payment_lines' => array( 'id', 'line_code', 'request_id', 'component_code', 'amount', 'source_reference' ),
			'payee_payment_attempts' => array( 'id', 'attempt_code', 'request_id', 'attempt_no', 'idempotency_key', 'request_sha256', 'external_reference', 'result_code', 'result_sha256', 'status', 'attempted_by', 'attempted_at' ),
			'access_audit_events' => array( 'id', 'event_code', 'actor_id', 'subject_type', 'subject_id', 'action_code', 'outcome', 'request_id', 'client_ip_hash', 'created_at' ),
			'social_insight_snapshots' => array( 'id', 'snapshot_code', 'platform_code', 'account_label', 'campaign_reference', 'reporting_period_start', 'reporting_period_end', 'captured_at', 'source_reference', 'metrics_json', 'source_sha256', 'staleness_code', 'captured_by', 'created_at' ),
		);
		if ( ! isset( $columns[ $entity ] ) ) {
			throw new InvalidArgumentException( 'Unsupported HR replacement entity.' );
		}
		return $columns[ $entity ];
	}

	public function insert( $entity, $data ) {
		global $wpdb;
		$allowed = $this->columns_for( $entity );
		$clean = array_intersect_key( $data, array_flip( $allowed ) );
		unset( $clean['id'] );
		$formats = array();
		foreach ( $clean as $key => $value ) {
			$formats[] = is_int( $value ) ? '%d' : ( is_float( $value ) ? '%f' : '%s' );
		}
		$result = $wpdb->insert( $this->table( $entity ), $clean, $formats );
		if ( false === $result ) {
			throw new RuntimeException( 'HR replacement record could not be created.' );
		}
		return $this->get( $entity, (int) $wpdb->insert_id );
	}

	public function update_versioned( $entity, $id, $expected_version, $data ) {
		global $wpdb;
		$allowed = $this->columns_for( $entity );
		$clean = array_intersect_key( $data, array_flip( $allowed ) );
		foreach ( array( 'id', 'created_by', 'created_at', 'version_no', 'status', 'person_status', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'opened_by', 'opened_at', 'recommended_by', 'recommended_at', 'owner_decided_by', 'owner_decided_at', 'verified_by', 'verified_at', 'completed_by', 'completed_at' ) as $immutable ) {
			unset( $clean[ $immutable ] );
		}
		if ( ! $clean ) {
			throw new InvalidArgumentException( 'No allowed fields were supplied for update.' );
		}
		$clean['version_no'] = absint( $expected_version ) + 1;
		if ( in_array( 'updated_by', $allowed, true ) ) {
			$clean['updated_by'] = get_current_user_id();
			$clean['updated_at'] = current_time( 'mysql', true );
		}
		$formats = array();
		foreach ( $clean as $key => $value ) {
			$formats[] = is_int( $value ) ? '%d' : ( is_float( $value ) ? '%f' : '%s' );
		}
		$result = $wpdb->update(
			$this->table( $entity ),
			$clean,
			array( 'id' => absint( $id ), 'version_no' => absint( $expected_version ) ),
			$formats,
			array( '%d', '%d' )
		);
		if ( false === $result || 1 !== (int) $result ) {
			throw new RuntimeException( 'Record changed concurrently or could not be updated; reload it before retrying.' );
		}
		return $this->get( $entity, $id );
	}

	public function transition( $entity, $id, $expected_version, $expected_state, $new_state, $changes = array() ) {
		global $wpdb;
		$allowed_changes = array(
			'workforce_plans' => array( 'decision_note', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'budget_reference', 'approved_budget_etb' ),
			'plan_lines' => array(),
			'positions' => array( 'decision_note', 'approval_reference', 'approved_by', 'approved_at' ),
			'requisitions' => array( 'decision_note', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'opened_by', 'opened_at', 'budget_reference' ),
			'people' => array( 'consent_status', 'consent_recorded_at', 'consent_withdrawn_at', 'retention_until' ),
			'candidates' => array( 'retention_until', 'withdrawn_at', 'withdrawal_reason' ),
			'applications' => array( 'stage', 'decision_note', 'withdrawn_at' ),
			'interviews' => array( 'evaluation_json', 'reviewer_summary', 'evidence_reference', 'completed_by', 'completed_at' ),
			'selection_decisions' => array( 'owner_decision', 'owner_decision_note', 'owner_decided_by', 'owner_decided_at' ),
			'employments' => array( 'activated_by', 'activated_at', 'start_date', 'end_date' ),
			'contracts' => array( 'employee_signed_at', 'employer_signed_at', 'approved_by', 'approved_at', 'document_id' ),
			'documents' => array( 'verified_by', 'verified_at', 'approved_by', 'approved_at', 'expires_on', 'retention_until' ),
			'onboarding_tasks' => array( 'evidence_reference', 'completed_by', 'completed_at', 'completion_note' ),
		);
		if ( ! isset( $allowed_changes[ $entity ] ) ) {
			throw new InvalidArgumentException( 'Unsupported HR replacement transition entity.' );
		}
		$state_column = 'people' === $entity ? 'person_status' : 'status';
		$clean = array_intersect_key( $changes, array_flip( $allowed_changes[ $entity ] ) );
		$clean[ $state_column ] = sanitize_key( $new_state );
		$clean['version_no'] = absint( $expected_version ) + 1;
		$result = $wpdb->update(
			$this->table( $entity ),
			$clean,
			array(
				'id' => absint( $id ),
				'version_no' => absint( $expected_version ),
				$state_column => sanitize_key( $expected_state ),
			),
			array_fill( 0, count( $clean ), '%s' ),
			array( '%d', '%d', '%s' )
		);
		if ( false === $result || 1 !== (int) $result ) {
			throw new RuntimeException( 'State transition lost a version/state race or could not be verified.' );
		}
		return $this->get( $entity, $id );
	}

	public function append_event( $entity, $id, $event, $from, $to, $actor, $correlation, $reason = '', $evidence = '' ) {
		global $wpdb;
		$payload = array(
			'entity_type' => sanitize_key( $entity ),
			'entity_id' => absint( $id ),
			'event_type' => sanitize_key( $event ),
			'from_state' => $from ? sanitize_key( $from ) : null,
			'to_state' => sanitize_key( $to ),
			'actor_id' => absint( $actor ),
			'approver_id' => in_array( strtoupper( (string) $to ), array( 'APPROVED', 'REJECTED', 'SELECTED' ), true ) ? absint( $actor ) : null,
			'reason_code' => '',
			'reason_text' => sanitize_textarea_field( (string) $reason ),
			'evidence_reference' => sanitize_text_field( (string) $evidence ),
			'correlation_id' => sanitize_text_field( $correlation ),
			'event_payload_sha256' => hash( 'sha256', wp_json_encode( array( $entity, $id, $event, $from, $to, $actor, $reason, $evidence ) ) ),
			'created_at' => current_time( 'mysql', true ),
		);
		$formats = array( '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' );
		if ( false === $wpdb->insert( $this->table( 'workflow_events' ), $payload, $formats ) ) {
			throw new RuntimeException( 'Workflow history could not be recorded.' );
		}
		return (int) $wpdb->insert_id;
	}
}
