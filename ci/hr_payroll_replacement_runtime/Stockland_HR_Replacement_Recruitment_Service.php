<?php
/**
 * Candidate privacy, applications, interview evidence and human-selection service.
 *
 * This is a candidate-only vertical slice. It never writes the legacy employees,
 * compensation, payroll, GL or PLEDGER tables. Selected applicants create a
 * PENDING_OFFER employment record only; activation belongs to the later contract,
 * statutory setup, onboarding and Owner-approval service.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

final class Stockland_HR_Replacement_Recruitment_Service {
	private $repo;
	private $access;
	private $commands;

	public function __construct( $repo, $access, $commands ) {
		$this->repo = $repo;
		$this->access = $access;
		$this->commands = $commands;
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

	private function optional( $data, $key, $max = 190 ) {
		$value = isset( $data[ $key ] ) ? trim( sanitize_text_field( (string) $data[ $key ] ) ) : '';
		if ( strlen( $value ) > $max ) {
			throw new InvalidArgumentException( 'A text field exceeds its maximum length.' );
		}
		return '' === $value ? null : $value;
	}

	private function positive_id( $value, $label ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^[1-9][0-9]{0,9}$/', (string) $value ) ) {
			throw new InvalidArgumentException( $label . ' must be a positive whole number.' );
		}
		return (int) $value;
	}

	private function rationale( $value, $label = 'Decision rationale', $minimum = 20 ) {
		$value = trim( sanitize_textarea_field( (string) $value ) );
		if ( strlen( $value ) < $minimum || strlen( $value ) > 4000 ) {
			throw new InvalidArgumentException( $label . ' must contain ' . $minimum . '–4000 characters.' );
		}
		return $value;
	}

	private function key_for_event( $key, $suffix ) {
		return hash( 'sha256', (string) $key . ':' . (string) $suffix );
	}

	private function event( $entity, $row, $event, $from, $to, $key, $reason = '', $evidence = '' ) {
		$this->repo->append_event(
			$entity,
			(int) $row['id'],
			$event,
			$from,
			$to,
			get_current_user_id(),
			$key,
			$reason,
			$evidence
		);
	}

	private function scoped_application( $application_id ) {
		$application = $this->repo->get( 'applications', $application_id, true );
		if ( ! $application ) {
			return $this->error( 'hrx_application_not_found', 'The application does not exist.', 404 );
		}
		$branch = $this->access->assert_branch( (int) $application['location_id'] );
		return is_wp_error( $branch ) ? $branch : $application;
	}

	private function scoped_requisition( $requisition_id ) {
		$row = $this->repo->get( 'requisitions', $requisition_id, true );
		if ( ! $row ) {
			return $this->error( 'hrx_requisition_not_found', 'The vacancy requisition does not exist.', 404 );
		}
		$branch = $this->access->assert_branch( (int) $row['location_id'] );
		return is_wp_error( $branch ) ? $branch : $row;
	}

	private function scoped_position( $position_id ) {
		$row = $this->repo->get( 'positions', $position_id, true );
		if ( ! $row ) {
			return $this->error( 'hrx_position_not_found', 'The position does not exist.', 404 );
		}
		$branch = $this->access->assert_branch( (int) $row['location_id'] );
		return is_wp_error( $branch ) ? $branch : $row;
	}

	private function iso_local_datetime( $value, $timezone, $label ) {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
			throw new InvalidArgumentException( $label . ' must use YYYY-MM-DD HH:MM:SS local time.' );
		}
		try {
			$zone = new DateTimeZone( $timezone );
		} catch ( Throwable $e ) {
			throw new InvalidArgumentException( 'Choose a valid IANA time-zone identifier.' );
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, $zone );
		$errors = DateTimeImmutable::getLastErrors();
		if ( false === $date || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $date->format( 'Y-m-d H:i:s' ) !== $value ) {
			throw new InvalidArgumentException( $label . ' is not a valid calendar date/time.' );
		}
		return $date;
	}

	private function scoped_interview_application( $interview_id ) {
		$interview = $this->repo->get( 'interviews', $interview_id, true );
		if ( ! $interview ) {
			return $this->error( 'hrx_interview_not_found', 'The interview does not exist.', 404 );
		}
		$application = $this->scoped_application( (int) $interview['application_id'] );
		return is_wp_error( $application ) ? $application : array( 'interview' => $interview, 'application' => $application );
	}

	public function get_candidate( $candidate_id ) {
		$candidate = $this->repo->get( 'candidates', $candidate_id );
		if ( ! $candidate ) {
			return $this->error( 'hrx_candidate_not_found', 'The candidate does not exist.', 404 );
		}
		$person = $this->repo->get( 'people', (int) $candidate['person_id'] );
		if ( ! $person ) {
			return $this->error( 'hrx_person_not_found', 'The canonical person record is unavailable.', 409 );
		}
		// Return a minimized profile. Contact details and private documents are not in this view.
		return array(
			'candidate' => array(
				'id' => (int) $candidate['id'],
				'candidate_code' => (string) $candidate['candidate_code'],
				'source_code' => (string) $candidate['source_code'],
				'status' => (string) $candidate['status'],
				'privacy_notice_version' => (string) $candidate['privacy_notice_version'],
				'retention_until' => $candidate['retention_until'],
				'created_at' => $candidate['created_at'],
			),
			'person' => array(
				'id' => (int) $person['id'],
				'person_code' => (string) $person['person_code'],
				'full_name' => (string) $person['full_name'],
				'preferred_name' => $person['preferred_name'],
				'person_status' => (string) $person['person_status'],
				'consent_status' => (string) $person['consent_status'],
				'processing_basis' => (string) $person['processing_basis'],
				'privacy_notice_version' => (string) $person['privacy_notice_version'],
			),
		);
	}

	public function list_applications( $filters = array() ) {
		$rows = $this->repo->list_rows(
			'applications',
			$filters,
			$this->access->scoped_location_ids(),
			$this->access->company_scope()
		);
		return array( 'applications' => $rows );
	}

	public function list_interviews( $application_id ) {
		$application = $this->scoped_application( $application_id );
		if ( is_wp_error( $application ) ) {
			return $application;
		}
		return array(
			'application_id' => (int) $application['id'],
			'interviews' => $this->repo->list_rows(
				'interviews',
				array( 'application_id' => (int) $application['id'] ),
				$this->access->scoped_location_ids(),
				$this->access->company_scope()
			),
		);
	}

	public function create_candidate( $data, $key ) {
		try {
			$basis = strtoupper( sanitize_key( $this->text( $data, 'processing_basis', 'Documented processing basis', 40 ) ) );
			if ( ! in_array( $basis, array( 'CONSENT', 'PRE_CONTRACTUAL_STEPS', 'LEGITIMATE_INTEREST', 'LEGAL_OBLIGATION', 'PENDING_LEGAL_REVIEW' ), true ) ) {
				throw new InvalidArgumentException( 'Use an approved processing-basis category; an unresolved basis must be PENDING_LEGAL_REVIEW.' );
			}
			$notice = $this->text( $data, 'privacy_notice_version', 'Privacy notice version', 80 );
			$basis_ref = $this->text( $data, 'processing_basis_reference', 'Approved policy/legal review reference', 190 );
			$person_code = $this->text( $data, 'person_code', 'Person code', 80 );
			$candidate_code = $this->text( $data, 'candidate_code', 'Candidate code', 80 );
			$full_name = $this->text( $data, 'full_name', 'Candidate full name', 190 );
			$raw_email = isset( $data['primary_email'] ) ? trim( (string) $data['primary_email'] ) : '';
			$email = '' !== $raw_email ? sanitize_email( $raw_email ) : '';
			if ( '' !== $raw_email && ( '' === $email || ! is_email( $email ) ) ) {
				throw new InvalidArgumentException( 'Candidate email is not valid.' );
			}
			$phone = $this->optional( $data, 'primary_phone', 40 );
			$preferred = $this->optional( $data, 'preferred_name', 190 );
			$source = $this->text( $data, 'source_code', 'Candidate source', 80 );
			$retention = $this->optional( $data, 'retention_until', 10 );
			if ( null !== $retention && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $retention ) ) {
				throw new InvalidArgumentException( 'Retention date must use YYYY-MM-DD.' );
			}
			$now = current_time( 'mysql', true );
			$person = $this->repo->insert( 'people', array(
				'person_code' => $person_code,
				'full_name' => $full_name,
				'preferred_name' => $preferred,
				'primary_email' => $email ?: null,
				'primary_phone' => $phone,
				'privacy_notice_version' => $notice,
				'processing_basis' => $basis,
				'processing_basis_reference' => $basis_ref,
				'consent_status' => 'PENDING',
				'consent_recorded_at' => null,
				'consent_withdrawn_at' => null,
				'retention_until' => $retention,
				'person_status' => 'PENDING_REVIEW',
				'version_no' => 1,
				'created_by' => get_current_user_id(),
				'created_at' => $now,
			) );
			$candidate = $this->repo->insert( 'candidates', array(
				'candidate_code' => $candidate_code,
				'person_id' => (int) $person['id'],
				'source_code' => $source,
				'status' => 'PENDING_REVIEW',
				'privacy_notice_version' => $notice,
				'retention_until' => $retention,
				'withdrawn_at' => null,
				'withdrawal_reason' => null,
				'version_no' => 1,
				'created_by' => get_current_user_id(),
				'created_at' => $now,
			) );
			$this->event( 'person', $person, 'CANDIDATE_PERSON_CREATED', null, 'PENDING_REVIEW', $this->key_for_event( $key, 'person' ), 'Candidate identity created pending privacy review.', $basis_ref );
			$this->event( 'candidate', $candidate, 'CANDIDATE_CREATED', null, 'PENDING_REVIEW', $this->key_for_event( $key, 'candidate' ), 'Candidate created pending privacy review.' );
			return array(
				'id' => (int) $candidate['id'],
				'candidate_id' => (int) $candidate['id'],
				'person_id' => (int) $person['id'],
				'candidate_code' => $candidate_code,
				'status' => 'PENDING_REVIEW',
				'message' => 'Candidate saved. Privacy notice and processing-basis evidence must be reviewed before applications can be opened.',
			);
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_candidate_validation_failed', $e->getMessage(), 422 );
		}
	}

	public function review_candidate_privacy( $candidate_id, $data, $key ) {
		try {
			$candidate = $this->repo->get( 'candidates', $candidate_id, true );
			if ( ! $candidate ) {
				return $this->error( 'hrx_candidate_not_found', 'The candidate does not exist.', 404 );
			}
			if ( 'PENDING_REVIEW' !== $candidate['status'] ) {
				return $this->error( 'hrx_candidate_privacy_locked', 'Only a candidate awaiting privacy review can be decided.', 409 );
			}
			$person = $this->repo->get( 'people', (int) $candidate['person_id'], true );
			if ( ! $person || 'PENDING_REVIEW' !== $person['person_status'] ) {
				return $this->error( 'hrx_person_privacy_state_invalid', 'The canonical person record is not awaiting privacy review.', 409 );
			}
			$notice = $this->text( $data, 'confirmed_notice_version', 'Confirmed privacy notice version', 80 );
			if ( ! hash_equals( (string) $candidate['privacy_notice_version'], $notice ) || ! hash_equals( (string) $person['privacy_notice_version'], $notice ) ) {
				return $this->error( 'hrx_privacy_notice_mismatch', 'The reviewed privacy notice version does not match the stored record.', 422 );
			}
			$decision = strtoupper( sanitize_key( $this->text( $data, 'decision', 'Privacy review decision', 16 ) ) );
			if ( ! in_array( $decision, array( 'APPROVE', 'REJECT' ), true ) ) {
				return $this->error( 'hrx_privacy_decision_invalid', 'Choose APPROVE or REJECT.', 400 );
			}
			$note = $this->rationale( $data['decision_note'] ?? '', 'Privacy review rationale', 30 );
			$evidence = $this->text( $data, 'review_evidence_reference', 'Privacy review evidence reference', 190 );
			if ( 'APPROVE' === $decision ) {
				$basis = strtoupper( (string) $person['processing_basis'] );
				if ( 'PENDING_LEGAL_REVIEW' === $basis || '' === (string) $person['processing_basis_reference'] ) {
					return $this->error( 'hrx_legal_basis_unresolved', 'A candidate cannot be released for recruitment until the lawful processing basis and versioned policy reference have been reviewed.', 422 );
				}
				$consent = strtoupper( sanitize_key( $this->text( $data, 'consent_status', 'Consent/legal assessment status', 40 ) ) );
				if ( ! in_array( $consent, array( 'RECORDED', 'NOT_REQUIRED_LEGALLY_REVIEWED' ), true ) ) {
					return $this->error( 'hrx_consent_status_invalid', 'Consent must be recorded or a non-consent basis must have documented legal review.', 422 );
				}
				if ( 'CONSENT' === $basis && 'RECORDED' !== $consent ) {
					return $this->error( 'hrx_consent_required', 'The selected processing basis requires a recorded consent event.', 422 );
				}
				if ( 'RECORDED' === $consent && strlen( $evidence ) < 10 ) {
					return $this->error( 'hrx_consent_evidence_required', 'A recorded consent must have a reference to its evidence.', 422 );
				}
				$person_updated = $this->repo->transition( 'people', (int) $person['id'], (int) $person['version_no'], 'PENDING_REVIEW', 'ACTIVE', array(
					'consent_status' => $consent,
					'consent_recorded_at' => 'RECORDED' === $consent ? current_time( 'mysql', true ) : null,
				) );
				$candidate_updated = $this->repo->transition( 'candidates', (int) $candidate['id'], (int) $candidate['version_no'], 'PENDING_REVIEW', 'ACTIVE', array() );
				$this->event( 'person', $person_updated, 'CANDIDATE_PRIVACY_APPROVED', 'PENDING_REVIEW', 'ACTIVE', $this->key_for_event( $key, 'person' ), $note, $evidence );
				$this->event( 'candidate', $candidate_updated, 'CANDIDATE_PRIVACY_APPROVED', 'PENDING_REVIEW', 'ACTIVE', $this->key_for_event( $key, 'candidate' ), $note, $evidence );
				return array( 'id' => (int) $candidate_updated['id'], 'candidate_id' => (int) $candidate_updated['id'], 'person_id' => (int) $person_updated['id'], 'status' => 'ACTIVE', 'consent_status' => $consent, 'message' => 'Candidate is available for applications after documented privacy review.' );
			}
			$person_updated = $this->repo->transition( 'people', (int) $person['id'], (int) $person['version_no'], 'PENDING_REVIEW', 'WITHDRAWN', array( 'consent_withdrawn_at' => current_time( 'mysql', true ) ) );
			$candidate_updated = $this->repo->transition( 'candidates', (int) $candidate['id'], (int) $candidate['version_no'], 'PENDING_REVIEW', 'WITHDRAWN', array( 'withdrawn_at' => current_time( 'mysql', true ), 'withdrawal_reason' => $note ) );
			$this->event( 'person', $person_updated, 'CANDIDATE_PRIVACY_REJECTED', 'PENDING_REVIEW', 'WITHDRAWN', $this->key_for_event( $key, 'person' ), $note, $evidence );
			$this->event( 'candidate', $candidate_updated, 'CANDIDATE_PRIVACY_REJECTED', 'PENDING_REVIEW', 'WITHDRAWN', $this->key_for_event( $key, 'candidate' ), $note, $evidence );
			return array( 'id' => (int) $candidate_updated['id'], 'candidate_id' => (int) $candidate_updated['id'], 'status' => 'WITHDRAWN', 'message' => 'Candidate was not released for recruitment; the decision and retention evidence were recorded.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_candidate_privacy_failed', 'The privacy review was not completed. Reload and verify status before retrying.', 409 );
		}
	}

	public function create_application( $data, $key ) {
		try {
			$candidate_id = $this->positive_id( $data['candidate_id'] ?? null, 'Candidate ID' );
			$candidate = $this->repo->get( 'candidates', $candidate_id, true );
			if ( ! $candidate ) {
				return $this->error( 'hrx_candidate_not_found', 'The candidate does not exist.', 404 );
			}
			if ( 'ACTIVE' !== $candidate['status'] ) {
				return $this->error( 'hrx_candidate_not_available', 'Only a candidate cleared for recruitment can apply.', 422 );
			}
			$person = $this->repo->get( 'people', (int) $candidate['person_id'], true );
			if ( ! $person || 'ACTIVE' !== $person['person_status'] || ! in_array( $person['consent_status'], array( 'RECORDED', 'NOT_REQUIRED_LEGALLY_REVIEWED' ), true ) || empty( $person['processing_basis_reference'] ) ) {
				return $this->error( 'hrx_privacy_review_required', 'The person record has not passed privacy and processing-basis review.', 422 );
			}
			$requisition_id = $this->positive_id( $data['requisition_id'] ?? null, 'Vacancy requisition ID' );
			$requisition = $this->scoped_requisition( $requisition_id );
			if ( is_wp_error( $requisition ) ) {
				return $requisition;
			}
			if ( 'OPEN' !== $requisition['status'] ) {
				return $this->error( 'hrx_vacancy_not_open', 'Applications are accepted only for an OPEN approved vacancy.', 422 );
			}
			$position = $this->scoped_position( (int) $requisition['position_id'] );
			if ( is_wp_error( $position ) ) {
				return $position;
			}
			if ( 'ACTIVE' !== $position['status'] ) {
				return $this->error( 'hrx_position_not_active', 'The approved position is not active.', 422 );
			}
			$existing = $this->repo->list_rows( 'applications', array( 'candidate_id' => $candidate_id, 'requisition_id' => $requisition_id ), $this->access->scoped_location_ids(), $this->access->company_scope() );
			if ( $existing ) {
				return $this->error( 'hrx_duplicate_application', 'This candidate already has an application for this vacancy.', 409 );
			}
			$code = $this->text( $data, 'application_code', 'Application code', 80 );
			$row = $this->repo->insert( 'applications', array(
				'application_code' => $code,
				'candidate_id' => $candidate_id,
				'person_id' => (int) $person['id'],
				'requisition_id' => $requisition_id,
				'location_id' => (int) $requisition['location_id'],
				'stage' => 'APPLIED',
				'status' => 'OPEN',
				'applied_at' => current_time( 'mysql', true ),
				'withdrawn_at' => null,
				'decision_note' => null,
				'version_no' => 1,
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			) );
			$this->event( 'application', $row, 'APPLICATION_CREATED', null, 'APPLIED', $key, 'Candidate application created after privacy review.', $requisition['requisition_code'] );
			return array( 'id' => (int) $row['id'], 'application_id' => (int) $row['id'], 'application_code' => $code, 'stage' => 'APPLIED', 'status' => 'OPEN', 'message' => 'Application created for the approved open vacancy.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_application_create_failed', 'The application was not created. Check the application code and referenced records, then retry after verifying status.', 409 );
		}
	}

	public function schedule_interview( $application_id, $data, $key ) {
		try {
			$application = $this->scoped_application( $application_id );
			if ( is_wp_error( $application ) ) {
				return $application;
			}
			if ( 'OPEN' !== $application['status'] || ! in_array( $application['stage'], array( 'APPLIED', 'SCREENING', 'INTERVIEW_SCHEDULED', 'INTERVIEWED' ), true ) ) {
				return $this->error( 'hrx_application_stage_invalid', 'Interviews may be scheduled only while the application remains open and in a screening/interview stage.', 422 );
			}
			$timezone = $this->optional( $data, 'timezone_code', 64 );
			$timezone = $timezone ?: 'Africa/Addis_Ababa';
			$start = $this->iso_local_datetime( $this->text( $data, 'scheduled_start', 'Interview start', 40 ), $timezone, 'Interview start' );
			$end = $this->iso_local_datetime( $this->text( $data, 'scheduled_end', 'Interview end', 40 ), $timezone, 'Interview end' );
			if ( $end <= $start ) {
				return $this->error( 'hrx_interview_range_invalid', 'Interview end must be after its start.', 422 );
			}
			$panel = $data['panel_user_ids'] ?? array();
			if ( ! is_array( $panel ) || ! $panel ) {
				return $this->error( 'hrx_interview_panel_required', 'At least one authorized panel member must be selected.', 422 );
			}
			$panel_ids = array();
			foreach ( $panel as $user_id ) {
				$user_id = $this->positive_id( $user_id, 'Panel user ID' );
				if ( ! function_exists( 'get_userdata' ) || ! get_userdata( $user_id ) ) {
					return $this->error( 'hrx_interview_panel_user_invalid', 'A selected panel member is not an existing WordPress user.', 422 );
				}
				$panel_ids[] = $user_id;
			}
			$panel_ids = array_values( array_unique( $panel_ids ) );
			$code = $this->text( $data, 'interview_code', 'Interview code', 80 );
			$stage = strtoupper( sanitize_key( $this->text( $data, 'stage_code', 'Interview stage', 40 ) ) );
			$row = $this->repo->insert( 'interviews', array(
				'interview_code' => $code,
				'application_id' => (int) $application['id'],
				'stage_code' => $stage,
				'scheduled_start' => $start->format( 'Y-m-d H:i:s' ),
				'scheduled_end' => $end->format( 'Y-m-d H:i:s' ),
				'timezone_code' => $timezone,
				'panel_user_ids_json' => wp_json_encode( $panel_ids ),
				'status' => 'PLANNED',
				'evaluation_json' => null,
				'reviewer_summary' => null,
				'evidence_reference' => null,
				'completed_by' => null,
				'completed_at' => null,
				'version_no' => 1,
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			) );
			if ( 'APPLIED' === $application['stage'] || 'SCREENING' === $application['stage'] ) {
				$app_updated = $this->repo->update_versioned( 'applications', (int) $application['id'], (int) $application['version_no'], array( 'stage' => 'INTERVIEW_SCHEDULED' ) );
				$this->event( 'application', $app_updated, 'APPLICATION_INTERVIEW_SCHEDULED', $application['stage'], 'INTERVIEW_SCHEDULED', $this->key_for_event( $key, 'application' ), 'Interview scheduled.', $code );
			}
			$this->event( 'interview', $row, 'INTERVIEW_SCHEDULED', null, 'PLANNED', $this->key_for_event( $key, 'interview' ), 'Interview scheduled with a human panel.' );
			return array( 'id' => (int) $row['id'], 'interview_id' => (int) $row['id'], 'application_id' => (int) $application['id'], 'status' => 'PLANNED', 'message' => 'Interview scheduled.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_interview_schedule_failed', 'The interview was not scheduled. Refresh and verify the application before retrying.', 409 );
		}
	}

	public function complete_interview( $interview_id, $data, $key ) {
		try {
			$context = $this->scoped_interview_application( $interview_id );
			if ( is_wp_error( $context ) ) {
				return $context;
			}
			$interview = $context['interview'];
			$application = $context['application'];
			if ( 'PLANNED' !== $interview['status'] ) {
				return $this->error( 'hrx_interview_not_planned', 'Only a planned interview may be completed.', 409 );
			}
			if ( 'OPEN' !== $application['status'] ) {
				return $this->error( 'hrx_application_closed', 'An interview cannot be completed for a closed application.', 409 );
			}
			$evaluation = $data['evaluation'] ?? null;
			if ( ! is_array( $evaluation ) || empty( $evaluation['rubric_version'] ) || empty( $evaluation['ratings'] ) || ! is_array( $evaluation['ratings'] ) ) {
				return $this->error( 'hrx_interview_evaluation_invalid', 'Record the rubric version and itemized human ratings before completing the interview.', 422 );
			}
			$summary = $this->rationale( $data['reviewer_summary'] ?? '', 'Interview summary', 20 );
			$evidence = $this->text( $data, 'evidence_reference', 'Interview evidence reference', 190 );
			$updated = $this->repo->transition( 'interviews', (int) $interview['id'], (int) $interview['version_no'], 'PLANNED', 'COMPLETED', array(
				'evaluation_json' => wp_json_encode( $evaluation ),
				'reviewer_summary' => $summary,
				'evidence_reference' => $evidence,
				'completed_by' => get_current_user_id(),
				'completed_at' => current_time( 'mysql', true ),
			) );
			$this->event( 'interview', $updated, 'INTERVIEW_COMPLETED', 'PLANNED', 'COMPLETED', $key, 'Human interview assessment recorded.', $evidence );
			$completed = $this->repo->list_rows( 'interviews', array( 'application_id' => (int) $application['id'], 'status' => 'COMPLETED' ), $this->access->scoped_location_ids(), $this->access->company_scope() );
			if ( $completed && 'INTERVIEW_SCHEDULED' === $application['stage'] ) {
				$app_updated = $this->repo->update_versioned( 'applications', (int) $application['id'], (int) $application['version_no'], array( 'stage' => 'INTERVIEWED' ) );
				$this->event( 'application', $app_updated, 'APPLICATION_INTERVIEWED', 'INTERVIEW_SCHEDULED', 'INTERVIEWED', $this->key_for_event( $key, 'application' ), 'At least one completed human interview exists.', $evidence );
			}
			return array( 'id' => (int) $updated['id'], 'interview_id' => (int) $updated['id'], 'status' => 'COMPLETED', 'message' => 'Interview evidence recorded; the system does not make the hiring decision.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_interview_complete_failed', 'The interview assessment was not committed. Verify the interview state before retrying.', 409 );
		}
	}

	public function recommend_selection( $application_id, $data, $key ) {
		try {
			$application = $this->scoped_application( $application_id );
			if ( is_wp_error( $application ) ) {
				return $application;
			}
			if ( 'OPEN' !== $application['status'] || ! in_array( $application['stage'], array( 'INTERVIEWED', 'INTERVIEW_SCHEDULED' ), true ) ) {
				return $this->error( 'hrx_interview_required', 'A human recommendation requires an open application with interview assessment.', 422 );
			}
			$completed = $this->repo->list_rows( 'interviews', array( 'application_id' => (int) $application['id'], 'status' => 'COMPLETED' ), $this->access->scoped_location_ids(), $this->access->company_scope() );
			if ( ! $completed ) {
				return $this->error( 'hrx_interview_evidence_required', 'At least one completed interview is required before a selection recommendation.', 422 );
			}
			$existing = $this->repo->list_rows( 'selection_decisions', array( 'application_id' => (int) $application['id'] ), $this->access->scoped_location_ids(), $this->access->company_scope() );
			foreach ( $existing as $record ) {
				if ( in_array( $record['status'], array( 'PENDING_OWNER', 'SELECTED' ), true ) ) {
					return $this->error( 'hrx_selection_already_exists', 'A pending or approved selection decision already exists for this application.', 409 );
				}
			}
			$recommendation = strtoupper( sanitize_key( $this->text( $data, 'recommendation', 'Human recommendation', 24 ) ) );
			if ( ! in_array( $recommendation, array( 'RECOMMEND', 'DO_NOT_RECOMMEND', 'HOLD' ), true ) ) {
				return $this->error( 'hrx_recommendation_invalid', 'Choose RECOMMEND, DO_NOT_RECOMMEND or HOLD.', 422 );
			}
			$rationale = $this->rationale( $data['rationale'] ?? '', 'Human recommendation rationale', 30 );
			$evidence = $this->text( $data, 'evidence_reference', 'Selection evidence reference', 190 );
			$code = $this->text( $data, 'decision_code', 'Decision code', 80 );
			$row = $this->repo->insert( 'selection_decisions', array(
				'decision_code' => $code,
				'application_id' => (int) $application['id'],
				'candidate_id' => (int) $application['candidate_id'],
				'recommendation' => $recommendation,
				'rationale' => $rationale,
				'evidence_reference' => $evidence,
				'recommended_by' => get_current_user_id(),
				'recommended_at' => current_time( 'mysql', true ),
				'status' => 'PENDING_OWNER',
				'owner_decision' => null,
				'owner_decision_note' => null,
				'owner_decided_by' => null,
				'owner_decided_at' => null,
				'version_no' => 1,
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			) );
			$app_updated = $this->repo->update_versioned( 'applications', (int) $application['id'], (int) $application['version_no'], array( 'stage' => 'RECOMMENDATION_PENDING' ) );
			$this->event( 'application', $app_updated, 'APPLICATION_RECOMMENDATION_PENDING', $application['stage'], 'RECOMMENDATION_PENDING', $this->key_for_event( $key, 'application' ), 'Human recommendation submitted for independent Owner decision.', $evidence );
			$this->event( 'selection-decision', $row, 'SELECTION_RECOMMENDED', null, 'PENDING_OWNER', $this->key_for_event( $key, 'decision' ), $rationale, $evidence );
			return array( 'id' => (int) $row['id'], 'selection_decision_id' => (int) $row['id'], 'status' => 'PENDING_OWNER', 'recommendation' => $recommendation, 'message' => 'Human recommendation submitted for independent Owner decision.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_selection_recommendation_failed', 'The recommendation was not committed. Refresh and verify application status before retrying.', 409 );
		}
	}

	public function decide_selection( $decision_id, $data, $key ) {
		try {
			$decision = $this->repo->get( 'selection_decisions', $decision_id, true );
			if ( ! $decision ) {
				return $this->error( 'hrx_selection_decision_not_found', 'The selection decision does not exist.', 404 );
			}
			$application = $this->scoped_application( (int) $decision['application_id'] );
			if ( is_wp_error( $application ) ) {
				return $application;
			}
			if ( 'PENDING_OWNER' !== $decision['status'] ) {
				return $this->error( 'hrx_selection_decision_locked', 'Only a pending selection decision may be decided.', 409 );
			}
			if ( (int) $decision['recommended_by'] === get_current_user_id() ) {
				return $this->error( 'hrx_selection_self_approval_blocked', 'The recommender cannot make the final selection decision.', 403 );
			}
			if ( 'RECOMMENDATION_PENDING' !== $application['stage'] || 'OPEN' !== $application['status'] ) {
				return $this->error( 'hrx_application_not_decidable', 'The application is no longer awaiting selection.', 409 );
			}
			$decision_code = strtoupper( sanitize_key( $this->text( $data, 'decision', 'Owner decision', 16 ) ) );
			if ( ! in_array( $decision_code, array( 'SELECT', 'REJECT' ), true ) ) {
				return $this->error( 'hrx_owner_decision_invalid', 'Choose SELECT or REJECT.', 422 );
			}
			$note = $this->rationale( $data['owner_decision_note'] ?? '', 'Owner decision rationale', 30 );
			if ( 'SELECT' === $decision_code ) {
				$employment_type = strtoupper( sanitize_key( $this->text( $data, 'employment_type', 'Proposed employment type', 32 ) ) );
				if ( ! in_array( $employment_type, array( 'FULL_TIME', 'PART_TIME', 'FIXED_TERM', 'TEMPORARY', 'INTERN', 'OTHER_REVIEW' ), true ) ) {
					return $this->error( 'hrx_employment_type_invalid', 'Choose a controlled employment type; OTHER_REVIEW remains blocked pending review.', 422 );
				}
				if ( 'OTHER_REVIEW' === $employment_type ) {
					return $this->error( 'hrx_employment_type_review_required', 'The proposed employment type must be reviewed before selection.', 422 );
				}
				$legal_employer = strtoupper( sanitize_key( $this->text( $data, 'legal_employer_code', 'Legal employer code', 80 ) ) );
				$configured_employer = function_exists( 'get_option' ) ? strtoupper( sanitize_key( (string) get_option( 'stockland_hrx_legal_employer_code', '' ) ) ) : '';
				if ( '' === $configured_employer || ! hash_equals( $configured_employer, $legal_employer ) ) {
					return $this->error( 'hrx_legal_employer_unconfigured', 'The requested legal employer is not configured in reviewed HR company settings. Do not create an employment record until the legal-employer master is approved.', 422 );
				}
				$requisition = $this->scoped_requisition( (int) $application['requisition_id'] );
				if ( is_wp_error( $requisition ) ) {
					return $requisition;
				}
				if ( 'OPEN' !== $requisition['status'] ) {
					return $this->error( 'hrx_vacancy_not_open', 'The vacancy was closed before selection.', 409 );
				}
				$already_selected = $this->repo->count_applications_for_requisition( (int) $requisition['id'], 'SELECTED' );
				if ( $already_selected >= (int) $requisition['openings'] ) {
					return $this->error( 'hrx_requisition_openings_filled', 'All openings authorized by this requisition are already reserved for selected hires.', 409 );
				}
				$existing_employment = $this->repo->find_open_employment_for_candidate( (int) $application['candidate_id'] );
				if ( $existing_employment ) {
					return $this->error( 'hrx_candidate_already_employed_or_pending', 'This candidate already has an active or pending employment record. Resolve the existing employment workflow before selecting again.', 409 );
				}
				$position = $this->scoped_position( (int) $requisition['position_id'] );
				if ( is_wp_error( $position ) ) {
					return $position;
				}
				$reserved = $this->repo->reserve_position_slot( (int) $position['id'], (int) $position['version_no'] );
				$owner_updated = $this->repo->transition( 'selection_decisions', (int) $decision['id'], (int) $decision['version_no'], 'PENDING_OWNER', 'SELECTED', array(
					'owner_decision' => 'SELECT',
					'owner_decision_note' => $note,
					'owner_decided_by' => get_current_user_id(),
					'owner_decided_at' => current_time( 'mysql', true ),
				) );
				$application_updated = $this->repo->transition( 'applications', (int) $application['id'], (int) $application['version_no'], 'OPEN', 'SELECTED', array(
					'stage' => 'SELECTED',
					'decision_note' => $note,
				) );
				$person = $this->repo->get( 'people', (int) $application['person_id'], true );
				$candidate = $this->repo->get( 'candidates', (int) $application['candidate_id'], true );
				if ( ! $person || 'ACTIVE' !== $person['person_status'] || ! $candidate || 'ACTIVE' !== $candidate['status'] ) {
					return $this->error( 'hrx_candidate_not_active', 'The selected candidate or person record is no longer active.', 409 );
				}
				$employment_code = $this->text( $data, 'employment_code', 'Employment code', 80 );
				$employment = $this->repo->insert( 'employments', array(
					'employment_code' => $employment_code,
					'person_id' => (int) $person['id'],
					'candidate_id' => (int) $candidate['id'],
					'application_id' => (int) $application['id'],
					'selection_decision_id' => (int) $decision['id'],
					'position_id' => (int) $position['id'],
					'requisition_id' => (int) $requisition['id'],
					'location_id' => (int) $requisition['location_id'],
					'job_title_snapshot' => (string) $position['title'],
					'department_snapshot' => (string) $position['department'],
					'employment_type' => $employment_type,
					'legal_employer_code' => $legal_employer,
					'start_date' => null,
					'end_date' => null,
					'status' => 'PENDING_OFFER',
					'activated_by' => null,
					'activated_at' => null,
					'version_no' => 1,
					'created_by' => get_current_user_id(),
					'created_at' => current_time( 'mysql', true ),
				) );
				$this->event( 'selection-decision', $owner_updated, 'OWNER_SELECTION_APPROVED', 'PENDING_OWNER', 'SELECTED', $this->key_for_event( $key, 'decision' ), $note, (string) $decision['evidence_reference'] );
				$this->event( 'application', $application_updated, 'APPLICATION_SELECTED', 'RECOMMENDATION_PENDING', 'SELECTED', $this->key_for_event( $key, 'application' ), $note );
				$this->event( 'employment', $employment, 'PENDING_HIRE_CREATED', null, 'PENDING_OFFER', $this->key_for_event( $key, 'employment' ), 'Employment shell created from independent human selection; activation is blocked until contract, onboarding and payroll prerequisites pass.', $legal_employer );
				$this->event( 'position', $reserved, 'POSITION_HEADCOUNT_RESERVED', 'ACTIVE', 'ACTIVE', $this->key_for_event( $key, 'headcount' ), 'One approved position slot reserved for a selected pending hire.', $employment_code );
				return array( 'id' => (int) $employment['id'], 'employment_id' => (int) $employment['id'], 'application_id' => (int) $application['id'], 'selection_decision_id' => (int) $decision['id'], 'status' => 'PENDING_OFFER', 'message' => 'Selection approved. Employment is pending offer and cannot activate until contract, private document, onboarding and approved payroll prerequisites pass.' );
			}
			$owner_updated = $this->repo->transition( 'selection_decisions', (int) $decision['id'], (int) $decision['version_no'], 'PENDING_OWNER', 'REJECTED', array(
				'owner_decision' => 'REJECT',
				'owner_decision_note' => $note,
				'owner_decided_by' => get_current_user_id(),
				'owner_decided_at' => current_time( 'mysql', true ),
			) );
			$application_updated = $this->repo->transition( 'applications', (int) $application['id'], (int) $application['version_no'], 'OPEN', 'REJECTED', array(
				'stage' => 'CLOSED',
				'decision_note' => $note,
			) );
			$this->event( 'selection-decision', $owner_updated, 'OWNER_SELECTION_REJECTED', 'PENDING_OWNER', 'REJECTED', $this->key_for_event( $key, 'decision' ), $note, (string) $decision['evidence_reference'] );
			$this->event( 'application', $application_updated, 'APPLICATION_REJECTED', 'RECOMMENDATION_PENDING', 'REJECTED', $this->key_for_event( $key, 'application' ), $note );
			return array( 'id' => (int) $owner_updated['id'], 'selection_decision_id' => (int) $owner_updated['id'], 'application_id' => (int) $application['id'], 'status' => 'REJECTED', 'message' => 'Owner decision recorded. The system retains the human rationale and history.' );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_selection_decision_failed', 'The selection decision was not committed. Refresh the application and confirm status before retrying.', 409 );
		}
	}
}
