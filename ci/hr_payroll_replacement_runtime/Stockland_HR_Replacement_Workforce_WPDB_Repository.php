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
			} elseif ( in_array( $entity, array( 'contracts', 'onboarding_tasks' ), true ) ) {
				$linked_table = $this->table( 'employments' );
				$where[] = 'employment_id IN (SELECT id FROM %i WHERE location_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . '))';
				$args[] = $linked_table;
				$args = array_merge( $args, $ids );
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
			'documents' => array( 'id', 'document_code', 'subject_type', 'subject_id', 'document_type', 'vault_reference', 'document_sha256', 'content_type', 'file_size_bytes', 'status', 'verified_by', 'verified_at', 'approved_by', 'approved_at', 'issued_on', 'expires_on', 'retention_until', 'version_no', 'created_by', 'created_at' ),
			'onboarding_tasks' => array( 'id', 'task_code', 'employment_id', 'task_type', 'title', 'mandatory', 'assigned_to_role', 'assigned_to_user', 'due_on', 'status', 'evidence_reference', 'completed_by', 'completed_at', 'completion_note', 'version_no', 'created_by', 'created_at', 'updated_by', 'updated_at' ),
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
