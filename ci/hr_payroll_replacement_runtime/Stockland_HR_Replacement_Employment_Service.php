<?php
/**
 * Contract, encrypted document, onboarding and guarded employment activation service.
 *
 * A selected applicant is only PENDING_OFFER until independent contract approval,
 * required onboarding, approved effective-dated compensation, approved applicable
 * statutory rules and separate Owner activation are all present.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
if ( ! class_exists( 'Stockland_HR_Replacement_Workforce_WPDB_Repository' ) || ! class_exists( 'Stockland_HR_Replacement_Workforce_WP_Access' ) || ! class_exists( 'Stockland_HR_Replacement_Workforce_Service' ) || ! class_exists( 'Stockland_HR_Replacement_Private_Vault' ) ) {
	return;
}

final class Stockland_HR_Replacement_Employment_Service {
	private $repo;
	private $access;
	private $commands;
	private $vault;

	public function __construct( $repo, $access, $commands, $vault ) {
		$this->repo = $repo;
		$this->access = $access;
		$this->commands = $commands;
		$this->vault = $vault;
	}

	private function error( $code, $message, $status = 400 ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private function text( $data, $key, $label, $max = 190 ) {
		$value = isset( $data[ $key ] ) ? trim( sanitize_text_field( (string) $data[ $key ] ) ) : '';
		if ( '' === $value || strlen( $value ) > $max ) {
			throw new InvalidArgumentException( $label . ' is required and must not exceed ' . $max . ' characters.' );
		}
		return $value;
	}

	private function optional_date( $value, $label, $required = false ) {
		$value = trim( (string) $value );
		if ( '' === $value && ! $required ) {
			return null;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		$errors = DateTimeImmutable::getLastErrors();
		if ( false === $date || $date->format( 'Y-m-d' ) !== $value || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) ) {
			throw new InvalidArgumentException( $label . ' must be a valid YYYY-MM-DD date.' );
		}
		return $value;
	}

	private function rationale( $value, $label, $min = 20 ) {
		$value = trim( sanitize_textarea_field( (string) $value ) );
		if ( strlen( $value ) < $min || strlen( $value ) > 4000 ) {
			throw new InvalidArgumentException( $label . ' must contain ' . $min . '–4000 characters.' );
		}
		return $value;
	}

	private function positive_id( $value, $label ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^[1-9][0-9]{0,9}$/', (string) $value ) ) {
			throw new InvalidArgumentException( $label . ' must be a positive whole number.' );
		}
		return (int) $value;
	}

	private function event_key( $key, $suffix ) {
		return hash( 'sha256', (string) $key . ':' . (string) $suffix );
	}

	private function event( $entity, $row, $event, $from, $to, $key, $note = '', $evidence = '' ) {
		$this->repo->append_event( $entity, (int) $row['id'], $event, $from, $to, get_current_user_id(), $key, $note, $evidence );
	}

	private function employment( $id, $for_update = true ) {
		$row = $this->repo->get( 'employments', $id, $for_update );
		if ( ! $row ) {
			return $this->error( 'hrx_employment_not_found', 'The employment record does not exist.', 404 );
		}
		$access = $this->access->assert_branch( (int) $row['location_id'] );
		return is_wp_error( $access ) ? $access : $row;
	}

	private function contract( $id, $for_update = true ) {
		$contract = $this->repo->get( 'contracts', $id, $for_update );
		if ( ! $contract ) {
			return $this->error( 'hrx_contract_not_found', 'The contract record does not exist.', 404 );
		}
		$employment = $this->employment( (int) $contract['employment_id'], $for_update );
		return is_wp_error( $employment ) ? $employment : array( 'contract' => $contract, 'employment' => $employment );
	}

	private function active_employer_code() {
		return strtoupper( sanitize_key( (string) get_option( 'stockland_hrx_legal_employer_code', '' ) ) );
	}

	public function list_employments( $filters = array() ) {
		return array( 'employments' => $this->repo->list_rows( 'employments', $filters, $this->access->scoped_location_ids(), $this->access->company_scope() ) );
	}

	public function list_contracts( $employment_id ) {
		$employment = $this->employment( $employment_id, false );
		if ( is_wp_error( $employment ) ) return $employment;
		return array( 'employment_id' => (int) $employment['id'], 'contracts' => $this->repo->list_rows( 'contracts', array( 'employment_id' => (int) $employment['id'] ), $this->access->scoped_location_ids(), $this->access->company_scope() ) );
	}

	public function list_onboarding_tasks( $employment_id ) {
		$employment = $this->employment( $employment_id, false );
		if ( is_wp_error( $employment ) ) return $employment;
		return array( 'employment_id' => (int) $employment['id'], 'tasks' => $this->repo->list_rows( 'onboarding_tasks', array( 'employment_id' => (int) $employment['id'] ), $this->access->scoped_location_ids(), $this->access->company_scope() ) );
	}

	public function create_contract( $employment_id, $data, $key ) {
		try {
			$employment = $this->employment( $employment_id );
			if ( is_wp_error( $employment ) ) return $employment;
			if ( 'PENDING_OFFER' !== $employment['status'] ) return $this->error( 'hrx_contract_requires_pending_offer', 'A new offer contract can only be drafted for a selected PENDING_OFFER employment.', 409 );
			$existing = $this->repo->list_rows( 'contracts', array( 'employment_id' => (int) $employment['id'] ), $this->access->scoped_location_ids(), $this->access->company_scope() );
			foreach ( $existing as $row ) {
				if ( ! in_array( $row['status'], array( 'VOIDED', 'REJECTED' ), true ) ) return $this->error( 'hrx_contract_already_exists', 'An active contract version already exists for this employment.', 409 );
			}
			$contract_code = $this->text( $data, 'contract_code', 'Contract code', 80 );
			$contract_type = strtoupper( sanitize_key( $this->text( $data, 'contract_type', 'Contract type', 32 ) ) );
			if ( ! in_array( $contract_type, array( 'PERMANENT', 'FIXED_TERM', 'TEMPORARY', 'PART_TIME', 'INTERN', 'OTHER_REVIEW' ), true ) ) {
				return $this->error( 'hrx_contract_type_invalid', 'Choose a controlled contract type.', 422 );
			}
			if ( 'OTHER_REVIEW' === $contract_type ) return $this->error( 'hrx_contract_type_review_required', 'The proposed contract type requires professional review before use.', 422 );
			$start = $this->optional_date( $data['start_date'] ?? '', 'Start date', true );
			$end = $this->optional_date( $data['end_date'] ?? '', 'End date' );
			$probation = $this->optional_date( $data['probation_end'] ?? '', 'Probation end date' );
			if ( $end && $end < $start ) return $this->error( 'hrx_contract_date_range_invalid', 'Contract end date cannot precede its start date.', 422 );
			if ( $probation && ( $probation < $start || ( $end && $probation > $end ) ) ) return $this->error( 'hrx_probation_date_invalid', 'Probation end must be within the approved contract date range.', 422 );
			$row = $this->repo->insert( 'contracts', array(
				'contract_code' => $contract_code,
				'employment_id' => (int) $employment['id'],
				'contract_type' => $contract_type,
				'contract_version' => count( $existing ) + 1,
				'start_date' => $start,
				'end_date' => $end,
				'probation_end' => $probation,
				'document_id' => null,
				'status' => 'DRAFT',
				'employee_signed_at' => null,
				'employer_signed_at' => null,
				'approved_by' => null,
				'approved_at' => null,
				'version_no' => 1,
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
				'updated_by' => null,
				'updated_at' => null,
			) );
			$this->event( 'contract', $row, 'CONTRACT_DRAFT_CREATED', null, 'DRAFT', $key, 'Contract draft prepared for an independently selected applicant.', $employment['employment_code'] );
			return array( 'id' => (int) $row['id'], 'contract_id' => (int) $row['id'], 'status' => 'DRAFT', 'contract_code' => $contract_code, 'message' => 'Contract draft created. It is not a signed contract and does not activate the employee.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_contract_create_failed', 'The contract was not created. Verify the employment and contract code before retrying.', 409 );
		}
	}

	public function submit_contract( $contract_id, $data, $key ) {
		try {
			$ctx = $this->contract( $contract_id );
			if ( is_wp_error( $ctx ) ) return $ctx;
			$c = $ctx['contract'];
			if ( 'DRAFT' !== $c['status'] ) return $this->error( 'hrx_contract_transition_invalid', 'Only a draft contract may be submitted for signature.', 409 );
			$note = $this->rationale( $data['submission_note'] ?? '', 'Contract submission note', 20 );
			$updated = $this->repo->transition( 'contracts', (int) $c['id'], (int) $c['version_no'], 'DRAFT', 'PENDING_SIGNATURE', array() );
			$this->event( 'contract', $updated, 'CONTRACT_SUBMITTED', 'DRAFT', 'PENDING_SIGNATURE', $key, $note );
			return array( 'id' => (int) $updated['id'], 'status' => 'PENDING_SIGNATURE', 'message' => 'Contract submitted for signature evidence.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_contract_submit_failed', 'The contract submission was not committed. Check status before retrying.', 409 );
		}
	}

	public function upload_document( $data, $file, $key ) {
		$subject_type = strtoupper( sanitize_key( (string) ( $data['subject_type'] ?? '' ) ) );
		$subject_id = isset( $data['subject_id'] ) ? absint( $data['subject_id'] ) : 0;
		if ( $subject_id < 1 ) return $this->error( 'hrx_document_subject_required', 'A linked HR record is required.', 422 );
		if ( 'CONTRACT' === $subject_type ) {
			$ctx = $this->contract( $subject_id );
			if ( is_wp_error( $ctx ) ) return $ctx;
			if ( ! in_array( $ctx['contract']['status'], array( 'DRAFT', 'PENDING_SIGNATURE', 'EMPLOYEE_SIGNED' ), true ) ) {
				return $this->error( 'hrx_contract_document_state_invalid', 'A contract document cannot be attached at this workflow stage.', 409 );
			}
		} elseif ( 'EMPLOYMENT' === $subject_type ) {
			$employment = $this->employment( $subject_id );
			if ( is_wp_error( $employment ) ) return $employment;
		} elseif ( 'CANDIDATE' === $subject_type ) {
			$candidate = $this->repo->get( 'candidates', $subject_id );
			if ( ! $candidate || ! ( $this->access->company_scope() || $this->access->can_manage_structure() ) ) return $this->error( 'hrx_candidate_document_access_denied', 'The candidate document subject is not available in this HR scope.', 403 );
		} elseif ( 'EXIT_CASE' === $subject_type ) {
			$exit = $this->repo->get( 'exit_cases', $subject_id );
			if ( ! $exit ) return $this->error( 'hrx_exit_case_not_found', 'The exit case does not exist.', 404 );
			$employment = $this->employment( (int) $exit['employment_id'] );
			if ( is_wp_error( $employment ) ) return $employment;
		} else {
			return $this->error( 'hrx_document_subject_unsupported', 'This document subject is not yet supported by the employment workflow.', 422 );
		}
		$stored = $this->vault->store( array(
			'document_code' => $this->text( $data, 'document_code', 'Document code', 80 ),
			'subject_type' => $subject_type,
			'subject_id' => $subject_id,
			'document_type' => $this->text( $data, 'document_type', 'Document type', 64 ),
			'issued_on' => $data['issued_on'] ?? null,
			'expires_on' => $data['expires_on'] ?? null,
			'retention_until' => $data['retention_until'] ?? null,
		), $file );
		if ( is_wp_error( $stored ) ) return $stored;
		$this->repo->append_event( 'document', (int) $stored['id'], 'PRIVATE_DOCUMENT_UPLOADED', null, 'PENDING_VERIFICATION', get_current_user_id(), $key, 'Encrypted private HR document stored pending independent verification.', (string) $stored['document_sha256'] );
		return $stored;
	}

	public function verify_document( $document_id, $data, $key ) {
		$note = $this->rationale( $data['verification_note'] ?? '', 'Verification note', 20 );
		$evidence = $this->text( $data, 'evidence_reference', 'Verification evidence reference', 190 );
		$doc = $this->repo->get( 'documents', $document_id, true );
		if ( ! $doc ) return $this->error( 'hrx_document_not_found', 'The document does not exist.', 404 );
		if ( ! $this->document_scope_allowed( $doc ) ) return $this->error( 'hrx_document_scope_denied', 'The document is outside your permitted HR scope.', 403 );
		return $this->vault->verify_document( $document_id, $note, $evidence );
	}

	public function approve_document( $document_id, $data, $key ) {
		$note = $this->rationale( $data['approval_note'] ?? '', 'Approval note', 20 );
		$evidence = $this->text( $data, 'evidence_reference', 'Approval evidence reference', 190 );
		$doc = $this->repo->get( 'documents', $document_id, true );
		if ( ! $doc ) return $this->error( 'hrx_document_not_found', 'The document does not exist.', 404 );
		if ( ! $this->document_scope_allowed( $doc ) ) return $this->error( 'hrx_document_scope_denied', 'The document is outside your permitted HR scope.', 403 );
		return $this->vault->approve_document( $document_id, $note, $evidence );
	}

	private function document_scope_allowed( $doc ) {
		$subject = strtoupper( (string) $doc['subject_type'] );
		if ( 'EMPLOYMENT' === $subject ) {
			$row = $this->employment( (int) $doc['subject_id'], false );
			return ! is_wp_error( $row );
		}
		if ( 'CONTRACT' === $subject ) {
			$row = $this->contract( (int) $doc['subject_id'], false );
			return ! is_wp_error( $row );
		}
		if ( 'EXIT_CASE' === $subject ) {
			$exit = $this->repo->get( 'exit_cases', (int) $doc['subject_id'] );
			if ( ! $exit ) return false;
			$row = $this->employment( (int) $exit['employment_id'], false );
			return ! is_wp_error( $row );
		}
		return $this->access->company_scope() || $this->access->can_manage_structure();
	}

	public function sign_contract( $contract_id, $data, $key ) {
		try {
			$ctx = $this->contract( $contract_id );
			if ( is_wp_error( $ctx ) ) return $ctx;
			$c = $ctx['contract'];
			if ( 'PENDING_SIGNATURE' !== $c['status'] ) return $this->error( 'hrx_contract_not_pending_signature', 'Only a submitted contract can record employee signature evidence.', 409 );
			$doc_id = $this->positive_id( $data['document_id'] ?? null, 'Approved signed contract document ID' );
			$doc = $this->repo->get( 'documents', $doc_id );
			if ( ! $doc || 'APPROVED' !== $doc['status'] || 'CONTRACT' !== strtoupper( $doc['subject_type'] ) || (int) $doc['subject_id'] !== (int) $c['id'] || 'EMPLOYMENT_CONTRACT' !== strtoupper( $doc['document_type'] ) ) {
				return $this->error( 'hrx_signed_contract_document_required', 'An approved, verified private EMPLOYMENT_CONTRACT document linked to this contract is required.', 422 );
			}
			$note = $this->rationale( $data['employee_signature_note'] ?? '', 'Employee signature evidence note', 20 );
			$date = $this->optional_date( $data['employee_signed_on'] ?? '', 'Employee signature date', true );
			if ( $date < $c['start_date'] && isset( $data['signature_is_offer_acceptance'] ) && false === $data['signature_is_offer_acceptance'] ) {
				return $this->error( 'hrx_signature_date_review', 'The signature date requires manual contract review.', 422 );
			}
			$updated = $this->repo->transition( 'contracts', (int) $c['id'], (int) $c['version_no'], 'PENDING_SIGNATURE', 'EMPLOYEE_SIGNED', array(
				'employee_signed_at' => $date . ' 00:00:00',
				'document_id' => $doc_id,
			) );
			$this->event( 'contract', $updated, 'EMPLOYEE_SIGNATURE_RECORDED', 'PENDING_SIGNATURE', 'EMPLOYEE_SIGNED', $key, $note, (string) $doc['document_sha256'] );
			return array( 'id' => (int) $updated['id'], 'status' => 'EMPLOYEE_SIGNED', 'message' => 'Employee signature evidence recorded. Employer signature and Owner approval are still required.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_contract_signature_failed', 'Employee signature evidence was not committed. Verify the document and contract state.', 409 );
		}
	}

	private function ensure_onboarding_tasks( $employment, $key ) {
		$existing = $this->repo->list_rows( 'onboarding_tasks', array( 'employment_id' => (int) $employment['id'] ), $this->access->scoped_location_ids(), $this->access->company_scope() );
		$existing_codes = array();
		foreach ( $existing as $task ) $existing_codes[] = $task['task_type'];
		$templates = array(
			array( 'PERSONNEL_DOCS', 'Verify required personnel records', 'HR', 1 ),
			array( 'CODE_OF_CONDUCT', 'Issue and acknowledge code of conduct', 'MANAGEMENT', 1 ),
			array( 'SAFETY_ORIENTATION', 'Complete workplace safety orientation', 'MANAGEMENT', 1 ),
			array( 'TIME_LEAVE_ORIENTATION', 'Explain attendance, shifts and leave SOPs', 'HR', 1 ),
			array( 'PAYROLL_SETUP', 'Verify approved compensation and statutory profile', 'OWNER', 1 ),
			array( 'ERP_ACCESS_APPROVAL', 'Approve least-privilege ERP access and location scope', 'OWNER', 1 ),
			array( 'EQUIPMENT_HANDOVER', 'Record required equipment and asset handover', 'MANAGEMENT', 1 ),
			array( 'INDUCTION', 'Complete employee induction and role training', 'MANAGEMENT', 1 ),
		);
		foreach ( $templates as $template ) {
			if ( in_array( $template[0], $existing_codes, true ) ) continue;
			$task_code = 'ONB-' . substr( hash( 'sha256', $employment['employment_code'] . ':' . $template[0] ), 0, 24 );
			$task = $this->repo->insert( 'onboarding_tasks', array(
				'task_code' => $task_code,
				'employment_id' => (int) $employment['id'],
				'task_type' => $template[0],
				'title' => $template[1],
				'mandatory' => (int) $template[3],
				'assigned_to_role' => $template[2],
				'assigned_to_user' => null,
				'due_on' => null,
				'status' => 'OPEN',
				'evidence_reference' => null,
				'completed_by' => null,
				'completed_at' => null,
				'completion_note' => null,
				'version_no' => 1,
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
				'updated_by' => null,
				'updated_at' => null,
			) );
			$this->event( 'onboarding-task', $task, 'ONBOARDING_TASK_CREATED', null, 'OPEN', $this->event_key( $key, $template[0] ), 'Mandatory onboarding control created.', $employment['employment_code'] );
		}
	}

	public function approve_contract( $contract_id, $data, $key ) {
		try {
			$ctx = $this->contract( $contract_id );
			if ( is_wp_error( $ctx ) ) return $ctx;
			$c = $ctx['contract'];
			$employment = $ctx['employment'];
			if ( 'EMPLOYEE_SIGNED' !== $c['status'] ) return $this->error( 'hrx_contract_not_employee_signed', 'A contract must have verified employee signature evidence before Owner approval.', 409 );
			if ( (int) $c['created_by'] === get_current_user_id() ) return $this->error( 'hrx_contract_self_approval_blocked', 'The contract maker cannot approve the final employer contract.', 403 );
			$note = $this->rationale( $data['owner_approval_note'] ?? '', 'Owner contract approval rationale', 30 );
			$employer_evidence = $this->text( $data, 'employer_signature_reference', 'Employer signature evidence reference', 190 );
			$doc = $this->repo->get( 'documents', (int) $c['document_id'] );
			if ( ! $doc || 'APPROVED' !== $doc['status'] || (int) $doc['approved_by'] === (int) $c['created_by'] ) return $this->error( 'hrx_contract_document_not_independently_approved', 'The signed contract document has not passed independent private-document approval.', 422 );
			if ( 'PENDING_OFFER' !== $employment['status'] ) return $this->error( 'hrx_employment_state_invalid', 'The linked employment is not awaiting contract approval.', 409 );
			$updated = $this->repo->transition( 'contracts', (int) $c['id'], (int) $c['version_no'], 'EMPLOYEE_SIGNED', 'APPROVED', array(
				'employer_signed_at' => current_time( 'mysql', true ),
				'approved_by' => get_current_user_id(),
				'approved_at' => current_time( 'mysql', true ),
			) );
			$employment_updated = $this->repo->transition( 'employments', (int) $employment['id'], (int) $employment['version_no'], 'PENDING_OFFER', 'PENDING_ONBOARDING', array(
				'start_date' => $c['start_date'],
			) );
			$this->ensure_onboarding_tasks( $employment_updated, $key );
			$this->event( 'contract', $updated, 'CONTRACT_OWNER_APPROVED', 'EMPLOYEE_SIGNED', 'APPROVED', $this->event_key( $key, 'contract' ), $note, $employer_evidence );
			$this->event( 'employment', $employment_updated, 'EMPLOYMENT_CONTRACT_APPROVED', 'PENDING_OFFER', 'PENDING_ONBOARDING', $this->event_key( $key, 'employment' ), 'Employment is waiting for all mandatory onboarding, compensation and statutory prerequisites.', $updated['contract_code'] );
			return array( 'id' => (int) $updated['id'], 'contract_id' => (int) $updated['id'], 'employment_id' => (int) $employment_updated['id'], 'contract_status' => 'APPROVED', 'employment_status' => 'PENDING_ONBOARDING', 'message' => 'Contract approved; mandatory onboarding tasks created. Employment remains inactive until all activation prerequisites pass.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_contract_approval_failed', 'The contract approval was not committed. Verify contract, document and employment state before retrying.', 409 );
		}
	}

	public function complete_onboarding_task( $task_id, $data, $key ) {
		try {
			$task = $this->repo->get( 'onboarding_tasks', $task_id, true );
			if ( ! $task ) return $this->error( 'hrx_onboarding_task_not_found', 'The onboarding task does not exist.', 404 );
			$employment = $this->employment( (int) $task['employment_id'] );
			if ( is_wp_error( $employment ) ) return $employment;
			if ( 'OPEN' !== $task['status'] ) return $this->error( 'hrx_onboarding_task_state_invalid', 'Only an open onboarding task can be completed.', 409 );
			if ( ! empty( $task['assigned_to_user'] ) && (int) $task['assigned_to_user'] !== get_current_user_id() ) return $this->error( 'hrx_onboarding_assignment_mismatch', 'This onboarding task is assigned to another user.', 403 );
			$role = strtoupper( (string) $task['assigned_to_role'] );
			if ( 'OWNER' === $role ) {
				if ( ! $this->access->can_approve() ) return $this->error( 'hrx_onboarding_owner_only', 'Only an authorized independent Owner can complete this onboarding control.', 403 );
			} elseif ( ! $this->access->can_prepare() ) {
				return $this->error( 'hrx_onboarding_prepare_denied', 'You are not authorized to complete this onboarding task.', 403 );
			}
			$evidence = $this->text( $data, 'evidence_reference', 'Completion evidence reference', 190 );
			$note = $this->rationale( $data['completion_note'] ?? '', 'Completion note', 15 );
			$updated = $this->repo->transition( 'onboarding_tasks', (int) $task['id'], (int) $task['version_no'], 'OPEN', 'COMPLETED', array(
				'evidence_reference' => $evidence,
				'completed_by' => get_current_user_id(),
				'completed_at' => current_time( 'mysql', true ),
				'completion_note' => $note,
			) );
			$this->event( 'onboarding-task', $updated, 'ONBOARDING_TASK_COMPLETED', 'OPEN', 'COMPLETED', $key, $note, $evidence );
			return array( 'id' => (int) $updated['id'], 'status' => 'COMPLETED', 'message' => 'Onboarding completion recorded with evidence.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_onboarding_task_failed', 'The onboarding task was not completed. Verify current state before retrying.', 409 );
		}
	}

	private function approved_statutory_rule_for_date( $rule_type, $effective_date ) {
		$rules = $this->repo->list_rows( 'statutory_rule_sets', array( 'rule_type' => $rule_type, 'status' => 'APPROVED' ), array(), true );
		foreach ( $rules as $rule ) {
			if ( $rule['effective_from'] <= $effective_date && ( empty( $rule['effective_to'] ) || $rule['effective_to'] >= $effective_date ) && ! empty( $rule['legal_reviewer_reference'] ) && ! empty( $rule['legal_reviewed_by'] ) && ! empty( $rule['approved_by'] ) && ! empty( $rule['test_vectors_sha256'] ) ) {
				$vectors = $this->repo->list_rows( 'statutory_rule_vectors', array( 'rule_set_id' => (int) $rule['id'], 'status' => 'PASS' ), array(), true );
				if ( $vectors ) return $rule;
			}
		}
		return null;
	}

	public function activate_employment( $employment_id, $data, $key ) {
		try {
			$employment = $this->employment( $employment_id );
			if ( is_wp_error( $employment ) ) return $employment;
			if ( 'PENDING_ONBOARDING' !== $employment['status'] ) return $this->error( 'hrx_employment_not_ready', 'Only an employment waiting for onboarding may be considered for activation.', 409 );
			if ( (int) $employment['created_by'] === get_current_user_id() ) return $this->error( 'hrx_employment_self_activation_blocked', 'The employment maker cannot perform the final Owner activation.', 403 );
			$note = $this->rationale( $data['activation_note'] ?? '', 'Owner activation rationale', 30 );
			$employer_code = $this->active_employer_code();
			if ( '' === $employer_code || ! hash_equals( $employer_code, strtoupper( (string) $employment['legal_employer_code'] ) ) ) return $this->error( 'hrx_legal_employer_unconfigured', 'The employment legal employer is not configured and verified in HR company settings.', 422 );
			$contract_list = $this->repo->list_rows( 'contracts', array( 'employment_id' => (int) $employment['id'], 'status' => 'APPROVED' ), $this->access->scoped_location_ids(), $this->access->company_scope() );
			if ( ! $contract_list ) return $this->error( 'hrx_approved_contract_required', 'A signed and independently approved contract is required before activation.', 422 );
			$contract = $contract_list[ count( $contract_list ) - 1 ];
			if ( empty( $contract['employee_signed_at'] ) || empty( $contract['employer_signed_at'] ) || empty( $contract['document_id'] ) || $contract['start_date'] !== $employment['start_date'] ) return $this->error( 'hrx_contract_prerequisites_incomplete', 'Contract signatures, approved private document or effective start date do not reconcile.', 422 );
			$doc = $this->repo->get( 'documents', (int) $contract['document_id'] );
			if ( ! $doc || 'APPROVED' !== $doc['status'] || 'CONTRACT' !== strtoupper( $doc['subject_type'] ) || (int) $doc['subject_id'] !== (int) $contract['id'] ) return $this->error( 'hrx_private_contract_document_required', 'An approved private contract file must be linked to the approved contract.', 422 );
			$today = current_time( 'Y-m-d' );
			if ( $employment['start_date'] > $today ) return $this->error( 'hrx_start_date_in_future', 'An employee cannot be activated before the approved employment start date.', 422 );
			$tasks = $this->repo->list_rows( 'onboarding_tasks', array( 'employment_id' => (int) $employment['id'] ), $this->access->scoped_location_ids(), $this->access->company_scope() );
			$open_mandatory = array_filter( $tasks, function ( $task ) { return (int) $task['mandatory'] === 1 && 'COMPLETED' !== $task['status']; } );
			if ( ! $tasks || $open_mandatory ) return $this->error( 'hrx_onboarding_incomplete', 'All mandatory onboarding tasks must be completed with evidence before activation.', 422 );
			$compensations = $this->repo->list_rows( 'compensation_assignments', array( 'employment_id' => (int) $employment['id'], 'status' => 'APPROVED' ), array(), true );
			$approved_compensation = null;
			foreach ( $compensations as $comp ) {
				if ( $comp['effective_from'] <= $employment['start_date'] && ( empty( $comp['effective_to'] ) || $comp['effective_to'] >= $employment['start_date'] ) && (float) $comp['base_amount'] > 0 && (int) $comp['approved_by'] !== (int) $comp['created_by'] ) {
					$approved_compensation = $comp;
				}
			}
			if ( ! $approved_compensation ) return $this->error( 'hrx_approved_compensation_required', 'Approved effective-dated compensation is required. No employee salary is invented or copied from another employee.', 422 );
			$lines = $this->repo->list_rows( 'compensation_assignment_lines', array( 'compensation_assignment_id' => (int) $approved_compensation['id'] ), array(), true );
			foreach ( $lines as $line ) {
				if ( 'APPROVED' !== $line['status'] ) return $this->error( 'hrx_compensation_line_unapproved', 'Every compensation component line must be approved before employment activation.', 422 );
			}
			$paye = $this->approved_statutory_rule_for_date( 'PAYE', $employment['start_date'] );
			$pension = $this->approved_statutory_rule_for_date( 'PENSION', $employment['start_date'] );
			if ( ! $paye || ! $pension ) return $this->error( 'hrx_statutory_rules_not_approved', 'Current effective PAYE and pension rule sets with professional legal review and passing test vectors are required before activation.', 422 );
			$position = $this->repo->get( 'positions', (int) $employment['position_id'], true );
			if ( ! $position || (int) $position['reserved_headcount'] < 1 ) return $this->error( 'hrx_position_reservation_missing', 'A reserved approved position slot must exist before activation.', 409 );
			$position_updated = $this->repo->convert_reserved_headcount_to_filled( (int) $position['id'], (int) $position['version_no'] );
			$updated = $this->repo->transition( 'employments', (int) $employment['id'], (int) $employment['version_no'], 'PENDING_ONBOARDING', 'ACTIVE', array(
				'activated_by' => get_current_user_id(),
				'activated_at' => current_time( 'mysql', true ),
			) );
			$this->event( 'position', $position_updated, 'POSITION_SLOT_FILLED', 'ACTIVE', 'ACTIVE', $this->event_key( $key, 'position' ), 'Position reservation converted to filled headcount on employment activation.', $employment['employment_code'] );
			$this->event( 'employment', $updated, 'EMPLOYMENT_ACTIVATED', 'PENDING_ONBOARDING', 'ACTIVE', $this->event_key( $key, 'employment' ), $note, 'PAYE:' . $paye['rule_version'] . ';PENSION:' . $pension['rule_version'] );
			return array( 'id' => (int) $updated['id'], 'employment_id' => (int) $updated['id'], 'status' => 'ACTIVE', 'message' => 'Employment activated after independent contract approval, private-document verification, mandatory onboarding, approved compensation and legally reviewed statutory prerequisites.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_employment_activation_failed', 'Employment activation was not committed. Refresh and verify all prerequisites before retrying.', 409 );
		}
	}

	public function decline_offer( $employment_id, $data, $key ) {
		try {
			$employment = $this->employment( $employment_id );
			if ( is_wp_error( $employment ) ) return $employment;
			if ( 'PENDING_OFFER' !== $employment['status'] ) return $this->error( 'hrx_offer_not_declineable', 'Only an unactivated PENDING_OFFER can be declined through this action.', 409 );
			$contracts = $this->repo->list_rows( 'contracts', array( 'employment_id' => (int) $employment['id'] ), $this->access->scoped_location_ids(), $this->access->company_scope() );
			if ( $contracts ) return $this->error( 'hrx_signed_offer_requires_review', 'A contract record already exists. Do not treat a signed or approved offer as a simple decline; use the reviewed separation workflow.', 409 );
			$note = $this->rationale( $data['decline_reason'] ?? '', 'Offer-decline rationale', 30 );
			$position = $this->repo->get( 'positions', (int) $employment['position_id'], true );
			if ( ! $position || (int) $position['reserved_headcount'] < 1 ) return $this->error( 'hrx_reservation_missing', 'The pending offer has no reserved position slot to release.', 409 );
			$position_updated = $this->repo->release_reserved_headcount( (int) $position['id'], (int) $position['version_no'] );
			$updated = $this->repo->transition( 'employments', (int) $employment['id'], (int) $employment['version_no'], 'PENDING_OFFER', 'DECLINED', array( 'end_date' => current_time( 'Y-m-d' ) ) );
			$app = $this->repo->get( 'applications', (int) $employment['application_id'], true );
			if ( $app && 'SELECTED' === $app['status'] ) {
				$app_updated = $this->repo->transition( 'applications', (int) $app['id'], (int) $app['version_no'], 'SELECTED', 'DECLINED', array( 'stage' => 'CLOSED', 'decision_note' => $note ) );
				$this->event( 'application', $app_updated, 'SELECTED_APPLICANT_DECLINED_OFFER', 'SELECTED', 'DECLINED', $this->event_key( $key, 'application' ), $note );
			}
			$this->event( 'employment', $updated, 'OFFER_DECLINED', 'PENDING_OFFER', 'DECLINED', $this->event_key( $key, 'employment' ), $note );
			$this->event( 'position', $position_updated, 'POSITION_RESERVATION_RELEASED', 'ACTIVE', 'ACTIVE', $this->event_key( $key, 'position' ), 'Unaccepted offer reservation released.', $employment['employment_code'] );
			return array( 'id' => (int) $updated['id'], 'employment_id' => (int) $updated['id'], 'status' => 'DECLINED', 'message' => 'Offer decline recorded and reserved headcount released; the selection decision remains in audit history.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_offer_decline_failed', 'Offer decline was not committed. Verify employment and position state before retrying.', 409 );
		}
	}
}
