<?php
/**
 * Transaction-oriented workforce planning and vacancy service.
 *
 * This is an isolated replacement candidate. It is not loaded by the production
 * plugin bootstrap, does not touch legacy HR tables, and must not be enabled
 * until the separate replacement schema and acceptance gates are approved.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) {
	exit;
}

interface Stockland_HR_Replacement_Workforce_Access {
	public function actor_id();
	public function can_view();
	public function can_prepare();
	public function can_approve();
	public function can_manage_org_structure();
	public function can_access_location( $location_id );
}

final class Stockland_HR_Replacement_Workforce_Service {
	private $repo;
	private $access;

	public function __construct( Stockland_HR_Replacement_Workforce_Repository $repo, Stockland_HR_Replacement_Workforce_Access $access ) {
		$this->repo   = $repo;
		$this->access = $access;
	}

	private function require_view() {
		if ( ! $this->access->can_view() ) {
			Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_FORBIDDEN', 'You do not have permission to view workforce planning.', 403 );
		}
		$this->require_schema();
	}

	private function require_prepare() {
		if ( ! $this->access->can_prepare() ) {
			Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_FORBIDDEN', 'Only an authorized HR preparer may create or submit workforce records.', 403 );
		}
		$this->require_schema();
	}

	private function require_approve() {
		if ( ! $this->access->can_approve() ) {
			Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_FORBIDDEN', 'Only the authorized independent Owner may approve this decision.', 403 );
		}
		$this->require_schema();
	}

	private function require_schema() {
		if ( ! $this->repo->tables_ready() ) {
			Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_SCHEMA_NOT_READY', 'The isolated HR replacement schema is not installed or failed its InnoDB readiness check. Legacy HR tables are not used as fallback.', 503 );
		}
	}

	private function actor() {
		$id = (int) $this->access->actor_id();
		if ( $id < 1 ) {
			Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_ACTOR_REQUIRED', 'An authenticated actor is required.', 401 );
		}
		return $id;
	}

	private function location( $id ) {
		$id = Stockland_HR_Replacement_Workforce_Contract::optional_id( $id, 'location_id' );
		if ( null !== $id ) {
			if ( ! $this->repo->location_exists( $id ) ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_LOCATION_NOT_FOUND', 'The selected Stockland location does not exist.', 422 );
			}
			if ( ! $this->access->can_access_location( $id ) ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_LOCATION_FORBIDDEN', 'The selected location is outside your authorized scope.', 403 );
			}
		}
		return $id;
	}

	private function idempotency( $key ) {
		return Stockland_HR_Replacement_Workforce_Contract::idempotency_key( $key );
	}

	private function replay( $key, $hash, $entity, $event_type ) {
		$event = $this->repo->find_event_by_key( $key );
		if ( ! $event ) {
			return null;
		}
		if ( ! hash_equals( (string) $event['request_hash'], (string) $hash ) ||
			strtoupper( (string) $event['entity_type'] ) !== strtoupper( (string) $entity ) ||
			strtoupper( (string) $event['event_type'] ) !== strtoupper( (string) $event_type ) ) {
			Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_IDEMPOTENCY_CONFLICT', 'This Idempotency-Key was already used for a different operation or payload.', 409 );
		}
		$record = $this->repo->find( $entity, (int) $event['entity_id'] );
		if ( ! $record ) {
			Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_IDEMPOTENCY_ORPHAN', 'The prior operation receipt refers to a missing record; stop for integrity review.', 500 );
		}
		return array( 'record' => $record, 'replayed' => true );
	}

	private function write_event( $key, $hash, $entity, $record, $event_type, $from, $to, $reason, $metadata = array(), $evidence_hash = null ) {
		$this->repo->append_event(
			array(
				'idempotency_key' => $key,
				'request_hash'    => $hash,
				'entity_type'     => strtoupper( $entity ),
				'entity_id'       => (int) $record['id'],
				'event_type'      => strtoupper( $event_type ),
				'from_status'     => $from,
				'to_status'       => $to,
				'actor_id'        => $this->actor(),
				'maker_id'        => isset( $record['created_by'] ) ? (int) $record['created_by'] : null,
				'reason'          => $reason ? substr( trim( (string) $reason ), 0, 190 ) : null,
				'evidence_sha256' => $evidence_hash,
				'record_version'  => (int) ( $record['version_no'] ?? 1 ),
				'metadata'        => $metadata,
			)
		);
	}

	private function get_or_404( $entity, $id, $for_update = false ) {
		$record = $this->repo->find( $entity, (int) $id, $for_update );
		if ( ! $record ) {
			Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_NOT_FOUND', 'The requested workforce record was not found.', 404 );
		}
		if ( isset( $record['location_id'] ) && null !== $record['location_id'] ) {
			$this->location( $record['location_id'] );
		}
		return $record;
	}

	private function expected_version( $record, $expected ) {
		$expected = Stockland_HR_Replacement_Workforce_Contract::positive_int( $expected, 'version_no' );
		if ( $expected !== (int) $record['version_no'] ) {
			Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_STALE_VERSION', 'This record was changed by another user. Refresh and review the latest version.', 409 );
		}
	}

	private function transition( $aggregate, $from, $to, $context ) {
		return Stockland_HR_Replacement_Workforce_Contract::require_transition( $aggregate, $from, $to, $context );
	}

	private function plan_totals( $plan_id ) {
		$totals = $this->repo->sum_plan_lines( (int) $plan_id );
		if ( $totals['line_count'] < 1 || $totals['headcount_total'] < 1 || (int) $totals['currency_count'] !== 1 ) {
			Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_PLAN_LINES_INCOMPLETE', 'A plan needs at least one valid position line, positive headcount and one consistent currency before submission.', 409 );
		}
		$budget = Stockland_HR_Replacement_Workforce_Contract::money( (string) $totals['budget_total'], 'budget_total', false );
		return array(
			'line_count'      => (int) $totals['line_count'],
			'headcount_total' => (int) $totals['headcount_total'],
			'budget_total'    => $budget,
			'currency'        => Stockland_HR_Replacement_Workforce_Contract::currency( (string) $totals['currency'] ),
		);
	}

	public function list_org_units( $filters = array(), $limit = 50 ) {
		$this->require_view();
		return $this->repo->list_records( 'org_units', $filters, $limit );
	}

	public function create_org_unit( $input, $key ) {
		$this->require_schema();
		if ( ! $this->access->can_manage_org_structure() ) {
			Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_FORBIDDEN', 'Only authorized organization owners may create organization units.', 403 );
		}
		$key = $this->idempotency( $key );
		$data = array(
			'org_code'  => Stockland_HR_Replacement_Workforce_Contract::code( $input['org_code'] ?? null, 'org_code' ),
			'name'      => Stockland_HR_Replacement_Workforce_Contract::text( $input['name'] ?? null, 'name', 2, 120 ),
			'parent_id' => Stockland_HR_Replacement_Workforce_Contract::optional_id( $input['parent_id'] ?? null, 'parent_id' ),
		);
		$hash = Stockland_HR_Replacement_Workforce_Contract::request_hash( 'ORG_UNIT_CREATE', $data );
		return $this->repo->transaction( function () use ( $key, $hash, $data ) {
			$prior = $this->replay( $key, $hash, 'org_units', 'ORG_UNIT_CREATED' );
			if ( $prior ) {
				return $prior;
			}
			if ( $this->repo->find_by_code( 'org_units', 'org_code', $data['org_code'] ) ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_UNIQUE_CONFLICT', 'That organization-unit code already exists.', 409 );
			}
			if ( null !== $data['parent_id'] && ! $this->repo->org_unit_exists( $data['parent_id'] ) ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_PARENT_NOT_FOUND', 'The parent organization unit must exist and be active.', 422 );
			}
			$actor = $this->actor();
			$record = $this->repo->insert(
				'org_units',
				array_merge(
					$data,
					array(
						'status'      => 'ACTIVE',
						'version_no'  => 1,
						'created_by'  => $actor,
						'updated_by'  => $actor,
					)
				)
			);
			$this->write_event( $key, $hash, 'ORG_UNITS', $record, 'ORG_UNIT_CREATED', null, 'ACTIVE', 'Organization unit created', array( 'org_code' => $data['org_code'] ) );
			return array( 'record' => $record, 'replayed' => false );
		} );
	}

	public function list_plans( $filters = array(), $limit = 50 ) {
		$this->require_view();
		if ( isset( $filters['location_id'] ) && '' !== $filters['location_id'] ) {
			$filters['location_id'] = $this->location( $filters['location_id'] );
		}
		return $this->repo->list_records( 'workforce_plans', $filters, $limit );
	}

	public function get_plan( $id ) {
		$this->require_view();
		$plan = $this->get_or_404( 'workforce_plans', $id );
		$plan['lines'] = $this->repo->list_plan_lines( (int) $plan['id'] );
		return $plan;
	}

	public function create_plan( $input, $key ) {
		$this->require_prepare();
		$key = $this->idempotency( $key );
		$data = array(
			'plan_code'     => Stockland_HR_Replacement_Workforce_Contract::code( $input['plan_code'] ?? null, 'plan_code' ),
			'fiscal_cycle'  => Stockland_HR_Replacement_Workforce_Contract::text( $input['fiscal_cycle'] ?? null, 'fiscal_cycle', 4, 24 ),
			'org_unit_id'   => Stockland_HR_Replacement_Workforce_Contract::positive_int( $input['org_unit_id'] ?? null, 'org_unit_id' ),
			'location_id'   => $this->location( $input['location_id'] ?? null ),
			'title'         => Stockland_HR_Replacement_Workforce_Contract::text( $input['title'] ?? null, 'title', 3, 160 ),
			'justification' => Stockland_HR_Replacement_Workforce_Contract::text( $input['justification'] ?? null, 'justification', 20, 500 ),
		);
		$hash = Stockland_HR_Replacement_Workforce_Contract::request_hash( 'WORKFORCE_PLAN_CREATE', $data );
		return $this->repo->transaction( function () use ( $key, $hash, $data ) {
			$prior = $this->replay( $key, $hash, 'workforce_plans', 'WORKFORCE_PLAN_CREATED' );
			if ( $prior ) {
				return $prior;
			}
			if ( ! $this->repo->org_unit_exists( $data['org_unit_id'] ) ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_ORG_UNIT_NOT_FOUND', 'The organization unit must exist and be active.', 422 );
			}
			if ( $this->repo->find_by_code( 'workforce_plans', 'plan_code', $data['plan_code'] ) ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_UNIQUE_CONFLICT', 'That workforce-plan code already exists.', 409 );
			}
			$actor = $this->actor();
			$record = $this->repo->insert(
				'workforce_plans',
				array_merge(
					$data,
					array(
						'status'                    => 'DRAFT',
						'version_no'                => 1,
						'requested_headcount_total'  => 0,
						'approved_headcount_total'  => 0,
						'requested_budget_total'    => '0.00',
						'approved_budget_total'     => '0.00',
						'budget_currency'           => null,
						'budget_reference'          => null,
						'submitted_by'              => null,
						'submitted_at'              => null,
						'approved_by'               => null,
						'approved_at'               => null,
						'decision_note'             => null,
						'created_by'                => $actor,
						'updated_by'                => $actor,
					)
				)
			);
			$this->write_event( $key, $hash, 'WORKFORCE_PLANS', $record, 'WORKFORCE_PLAN_CREATED', null, 'DRAFT', 'Workforce plan created', array( 'plan_code' => $data['plan_code'], 'org_unit_id' => $data['org_unit_id'] ) );
			return array( 'record' => $record, 'replayed' => false );
		} );
	}

	public function add_plan_line( $plan_id, $input, $key ) {
		$this->require_prepare();
		$key = $this->idempotency( $key );
		$plan_id = Stockland_HR_Replacement_Workforce_Contract::positive_int( $plan_id, 'plan_id' );
		$data = array(
			'plan_id'            => $plan_id,
			'position_id'        => Stockland_HR_Replacement_Workforce_Contract::positive_int( $input['position_id'] ?? null, 'position_id' ),
			'budgeted_headcount' => Stockland_HR_Replacement_Workforce_Contract::positive_int( $input['budgeted_headcount'] ?? null, 'budgeted_headcount', 10000 ),
			'budget_amount'      => Stockland_HR_Replacement_Workforce_Contract::money( $input['budget_amount'] ?? null, 'budget_amount', false ),
			'currency'           => Stockland_HR_Replacement_Workforce_Contract::currency( $input['currency'] ?? 'ETB' ),
			'justification'      => Stockland_HR_Replacement_Workforce_Contract::text( $input['justification'] ?? null, 'justification', 20, 500 ),
		);
		$hash = Stockland_HR_Replacement_Workforce_Contract::request_hash( 'WORKFORCE_PLAN_LINE_CREATE', $data );
		return $this->repo->transaction( function () use ( $key, $hash, $plan_id, $data ) {
			$prior = $this->replay( $key, $hash, 'plan_lines', 'WORKFORCE_PLAN_LINE_CREATED' );
			if ( $prior ) {
				return $prior;
			}
			$plan = $this->get_or_404( 'workforce_plans', $plan_id, true );
			if ( 'DRAFT' !== $plan['status'] ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_PLAN_LOCKED', 'Plan lines may only be added while the workforce plan is DRAFT.', 409 );
			}
			$position = $this->get_or_404( 'positions', $data['position_id'], true );
			if ( ! in_array( $position['status'], array( 'APPROVED', 'ACTIVE' ), true ) ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_POSITION_NOT_APPROVED', 'Only an approved position may be included in a workforce plan.', 409 );
			}
			if ( (int) $position['org_unit_id'] !== (int) $plan['org_unit_id'] ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_ORG_UNIT_MISMATCH', 'The position and workforce plan must belong to the same organization unit.', 422 );
			}
			if ( null !== $plan['location_id'] && null !== $position['location_id'] && (int) $plan['location_id'] !== (int) $position['location_id'] ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_LOCATION_MISMATCH', 'The position location must match the workforce-plan location or be company-wide.', 422 );
			}
			$existing = $this->repo->list_plan_lines( $plan_id );
			foreach ( $existing as $line ) {
				if ( (int) $line['position_id'] === (int) $data['position_id'] ) {
					Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_DUPLICATE_PLAN_POSITION', 'A position may appear only once in each workforce plan.', 409 );
				}
				if ( (string) $line['currency'] !== (string) $data['currency'] ) {
					Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_MIXED_CURRENCY', 'One workforce plan must use a single currency; create a separate plan for another currency.', 422 );
				}
			}
			$actor = $this->actor();
			$record = $this->repo->insert(
				'plan_lines',
				array_merge(
					$data,
					array(
						'version_no' => 1,
						'created_by' => $actor,
						'updated_by' => $actor,
					)
				)
			);
			$this->write_event( $key, $hash, 'PLAN_LINES', $record, 'WORKFORCE_PLAN_LINE_CREATED', 'DRAFT', 'DRAFT', 'Position budget line added', array( 'plan_id' => $plan_id, 'position_id' => $data['position_id'], 'headcount' => $data['budgeted_headcount'] ) );
			return array( 'record' => $record, 'replayed' => false );
		} );
	}

	public function update_plan_line( $plan_id, $line_id, $input, $expected_version, $key ) {
		$this->require_prepare();
		$key = $this->idempotency( $key );
		$plan_id = Stockland_HR_Replacement_Workforce_Contract::positive_int( $plan_id, 'plan_id' );
		$line_id = Stockland_HR_Replacement_Workforce_Contract::positive_int( $line_id, 'line_id' );
		$expected_version = Stockland_HR_Replacement_Workforce_Contract::positive_int( $expected_version, 'version_no' );
		$data = array(
			'budgeted_headcount' => Stockland_HR_Replacement_Workforce_Contract::positive_int( $input['budgeted_headcount'] ?? null, 'budgeted_headcount', 10000 ),
			'budget_amount'      => Stockland_HR_Replacement_Workforce_Contract::money( $input['budget_amount'] ?? null, 'budget_amount', false ),
			'currency'           => Stockland_HR_Replacement_Workforce_Contract::currency( $input['currency'] ?? null ),
			'justification'      => Stockland_HR_Replacement_Workforce_Contract::text( $input['justification'] ?? null, 'justification', 20, 500 ),
		);
		$hash = Stockland_HR_Replacement_Workforce_Contract::request_hash( 'WORKFORCE_PLAN_LINE_UPDATE', array_merge( $data, array( 'plan_id' => $plan_id, 'line_id' => $line_id, 'version_no' => $expected_version ) ) );
		return $this->repo->transaction( function () use ( $key, $hash, $plan_id, $line_id, $expected_version, $data ) {
			$prior = $this->replay( $key, $hash, 'plan_lines', 'WORKFORCE_PLAN_LINE_UPDATED' );
			if ( $prior ) {
				return $prior;
			}
			$plan = $this->get_or_404( 'workforce_plans', $plan_id, true );
			if ( 'DRAFT' !== $plan['status'] ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_PLAN_LOCKED', 'Plan lines may only be edited while the workforce plan is DRAFT.', 409 );
			}
			$line = $this->get_or_404( 'plan_lines', $line_id, true );
			if ( (int) $line['plan_id'] !== $plan_id ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_LINE_PLAN_MISMATCH', 'The selected plan line does not belong to this workforce plan.', 404 );
			}
			$this->expected_version( $line, $expected_version );
			$position = $this->get_or_404( 'positions', (int) $line['position_id'], true );
			if ( (string) $line['currency'] !== (string) $data['currency'] ) {
				$others = array_filter( $this->repo->list_plan_lines( $plan_id ), function ( $other ) use ( $line_id ) {
					return (int) $other['id'] !== $line_id;
				} );
				foreach ( $others as $other ) {
					if ( (string) $other['currency'] !== (string) $data['currency'] ) {
						Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_MIXED_CURRENCY', 'One workforce plan must use a single currency.', 422 );
					}
				}
			}
			$record = $this->repo->update_line_expected(
				$line_id,
				$expected_version,
				array_merge( $data, array( 'version_no' => $expected_version + 1, 'updated_by' => $this->actor() ) )
			);
			$this->write_event( $key, $hash, 'PLAN_LINES', $record, 'WORKFORCE_PLAN_LINE_UPDATED', 'DRAFT', 'DRAFT', 'Position budget line amended', array( 'plan_id' => $plan_id, 'position_id' => (int) $position['id'], 'headcount' => $data['budgeted_headcount'] ) );
			return array( 'record' => $record, 'replayed' => false );
		} );
	}

	public function list_positions( $filters = array(), $limit = 50 ) {
		$this->require_view();
		if ( isset( $filters['location_id'] ) && '' !== $filters['location_id'] ) {
			$filters['location_id'] = $this->location( $filters['location_id'] );
		}
		return $this->repo->list_records( 'positions', $filters, $limit );
	}

	public function create_position( $input, $key ) {
		$this->require_prepare();
		$key = $this->idempotency( $key );
		$data = array(
			'position_code'       => Stockland_HR_Replacement_Workforce_Contract::code( $input['position_code'] ?? null, 'position_code' ),
			'org_unit_id'         => Stockland_HR_Replacement_Workforce_Contract::positive_int( $input['org_unit_id'] ?? null, 'org_unit_id' ),
			'title'               => Stockland_HR_Replacement_Workforce_Contract::text( $input['title'] ?? null, 'title', 2, 120 ),
			'grade_code'          => isset( $input['grade_code'] ) && '' !== $input['grade_code'] ? Stockland_HR_Replacement_Workforce_Contract::code( $input['grade_code'], 'grade_code', 32 ) : null,
			'parent_position_id'  => Stockland_HR_Replacement_Workforce_Contract::optional_id( $input['parent_position_id'] ?? null, 'parent_position_id' ),
			'location_id'         => $this->location( $input['location_id'] ?? null ),
			'position_justification' => Stockland_HR_Replacement_Workforce_Contract::text( $input['position_justification'] ?? null, 'position_justification', 20, 500 ),
		);
		$hash = Stockland_HR_Replacement_Workforce_Contract::request_hash( 'POSITION_CREATE', $data );
		return $this->repo->transaction( function () use ( $key, $hash, $data ) {
			$prior = $this->replay( $key, $hash, 'positions', 'POSITION_CREATED' );
			if ( $prior ) {
				return $prior;
			}
			if ( ! $this->repo->org_unit_exists( $data['org_unit_id'] ) ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_ORG_UNIT_NOT_FOUND', 'The organization unit must exist and be active.', 422 );
			}
			if ( $this->repo->find_by_code( 'positions', 'position_code', $data['position_code'] ) ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_UNIQUE_CONFLICT', 'That position code already exists.', 409 );
			}
			if ( null !== $data['parent_position_id'] ) {
				$parent = $this->get_or_404( 'positions', $data['parent_position_id'], true );
				if ( (int) $parent['org_unit_id'] !== (int) $data['org_unit_id'] || ! in_array( $parent['status'], array( 'APPROVED', 'ACTIVE' ), true ) ) {
					Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_PARENT_POSITION_INVALID', 'The reporting position must be approved and belong to the same organization unit.', 422 );
				}
			}
			$actor = $this->actor();
			$record = $this->repo->insert(
				'positions',
				array_merge(
					$data,
					array(
						'status'        => 'DRAFT',
						'version_no'    => 1,
						'submitted_by'  => null,
						'submitted_at'  => null,
						'approved_by'   => null,
						'approved_at'   => null,
						'decision_note' => null,
						'created_by'    => $actor,
						'updated_by'    => $actor,
					)
				)
			);
			$this->write_event( $key, $hash, 'POSITIONS', $record, 'POSITION_CREATED', null, 'DRAFT', 'Position profile created', array( 'position_code' => $data['position_code'], 'org_unit_id' => $data['org_unit_id'] ) );
			return array( 'record' => $record, 'replayed' => false );
		} );
	}

	public function submit_position( $id, $expected_version, $key ) {
		return $this->transition_record( 'positions', $id, $expected_version, $key, 'POSITION', 'SUBMITTED', 'POSITION_SUBMITTED', 'Submitted by authorized HR preparer for independent review', null );
	}

	public function decide_position( $id, $expected_version, $decision, $reason, $key ) {
		$decision = Stockland_HR_Replacement_Workforce_Contract::decode_decision( $decision );
		return $this->transition_record( 'positions', $id, $expected_version, $key, 'POSITION', $decision, 'POSITION_DECISION', $reason, null );
	}

	public function activate_position( $id, $expected_version, $reason, $key ) {
		return $this->transition_record( 'positions', $id, $expected_version, $key, 'POSITION', 'ACTIVE', 'POSITION_ACTIVATED', $reason, null );
	}

	public function submit_plan( $id, $expected_version, $key ) {
		$this->require_prepare();
		$key = $this->idempotency( $key );
		$id  = Stockland_HR_Replacement_Workforce_Contract::positive_int( $id, 'plan_id' );
		$expected_version = Stockland_HR_Replacement_Workforce_Contract::positive_int( $expected_version, 'version_no' );
		$hash = Stockland_HR_Replacement_Workforce_Contract::request_hash( 'WORKFORCE_PLAN_SUBMIT', array( 'id' => $id, 'version_no' => $expected_version ) );
		return $this->repo->transaction( function () use ( $id, $expected_version, $key, $hash ) {
			$prior = $this->replay( $key, $hash, 'workforce_plans', 'WORKFORCE_PLAN_SUBMITTED' );
			if ( $prior ) {
				return $prior;
			}
			$plan = $this->get_or_404( 'workforce_plans', $id, true );
			$this->expected_version( $plan, $expected_version );
			$totals = $this->plan_totals( $id );
			foreach ( $this->repo->list_plan_lines( $id ) as $line ) {
				if ( ! in_array( $line['position_status'], array( 'APPROVED', 'ACTIVE' ), true ) ) {
					Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_PLAN_POSITION_NOT_APPROVED', 'All positions in the plan must be approved before the plan can be submitted.', 409 );
				}
			}
			$this->transition( 'WORKFORCE_PLAN', $plan['status'], 'SUBMITTED', array( 'actor_id' => (string) $this->actor(), 'maker_id' => (string) $plan['created_by'] ) );
			$actor = $this->actor();
			$record = $this->repo->update_expected(
				'workforce_plans',
				$id,
				$expected_version,
				$plan['status'],
				array(
					'status'                   => 'SUBMITTED',
					'version_no'               => $expected_version + 1,
					'requested_headcount_total' => $totals['headcount_total'],
					'requested_budget_total'   => $totals['budget_total'],
					'budget_currency'          => $totals['currency'],
					'submitted_by'             => $actor,
					'submitted_at'             => current_time( 'mysql', true ),
					'updated_by'               => $actor,
					'approved_headcount_total' => 0,
					'approved_budget_total'    => '0.00',
				)
			);
			$this->write_event( $key, $hash, 'WORKFORCE_PLANS', $record, 'WORKFORCE_PLAN_SUBMITTED', $plan['status'], 'SUBMITTED', null, $totals );
			return array( 'record' => $record, 'replayed' => false );
		} );
	}

	public function decide_plan( $id, $expected_version, $decision, $reason, $budget_reference, $key ) {
		$decision = Stockland_HR_Replacement_Workforce_Contract::decode_decision( $decision );
		return $this->transition_record( 'workforce_plans', $id, $expected_version, $key, 'WORKFORCE_PLAN', $decision, 'WORKFORCE_PLAN_DECISION', $reason, array( 'budget_reference' => $budget_reference ) );
	}

	private function transition_record( $entity, $id, $expected_version, $key, $aggregate, $decision, $event_type, $reason, $extra ) {
		$intent = strtoupper( (string) $decision );
		if ( in_array( $intent, array( 'APPROVE', 'APPROVED', 'REJECT', 'REJECTED', 'NEEDS_INFORMATION', 'ACTIVE', 'INACTIVE' ), true ) ) {
			$this->require_approve();
		} else {
			$this->require_prepare();
		}
		$key = $this->idempotency( $key );
		$id  = Stockland_HR_Replacement_Workforce_Contract::positive_int( $id, 'record_id' );
		$expected_version = Stockland_HR_Replacement_Workforce_Contract::positive_int( $expected_version, 'version_no' );
		$reason = Stockland_HR_Replacement_Workforce_Contract::text( (string) $reason, 'decision_reason', 20, 500 );
		$extra = (array) $extra;
		$hash_data = array_merge( $extra, array( 'id' => $id, 'version_no' => $expected_version, 'decision' => $decision, 'reason' => $reason ) );
		$hash = Stockland_HR_Replacement_Workforce_Contract::request_hash( $event_type, $hash_data );
		return $this->repo->transaction( function () use ( $entity, $id, $expected_version, $key, $aggregate, $decision, $event_type, $reason, $extra, $hash ) {
			$prior = $this->replay( $key, $hash, $entity, $event_type );
			if ( $prior ) {
				return $prior;
			}
			$record = $this->get_or_404( $entity, $id, true );
			$this->expected_version( $record, $expected_version );
			$from = strtoupper( (string) $record['status'] );
			$to   = strtoupper( (string) $decision );
			if ( 'APPROVE' === $to ) {
				$to = 'APPROVED';
			} elseif ( 'REJECT' === $to ) {
				$to = 'REJECTED';
			} elseif ( 'NEEDS_INFORMATION' === $to ) {
				$to = 'NEEDS_INFORMATION';
			}
			if ( in_array( $to, array( 'APPROVED', 'ACTIVE' ), true ) && (int) $record['created_by'] === $this->actor() ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_SELF_APPROVAL_BLOCKED', 'The maker cannot approve or activate their own record.', 403 );
			}
			$context = array( 'actor_id' => (string) $this->actor(), 'maker_id' => (string) $record['created_by'] );
			if ( 'workforce_plans' === $entity && 'APPROVED' === $to ) {
				$budget_reference = Stockland_HR_Replacement_Workforce_Contract::text( $extra['budget_reference'] ?? null, 'budget_reference', 3, 190 );
				$context['budget_reference'] = $budget_reference;
				$context['budget_positive'] = Stockland_HR_Replacement_Workforce_Contract::money( (string) $record['requested_budget_total'], 'budget_total', false ) !== '0.00';
				$context['headcount_valid']  = (int) $record['requested_headcount_total'] > 0;
			}
			if ( 'positions' === $entity && 'APPROVED' === $to ) {
				$context['position_justification'] = (string) $record['position_justification'];
				$context['org_unit_active']        = $this->repo->org_unit_exists( (int) $record['org_unit_id'] );
			}
			if ( 'requisitions' === $entity && 'APPROVED' === $to ) {
				$context['budget_reference'] = Stockland_HR_Replacement_Workforce_Contract::text( $extra['budget_reference'] ?? null, 'budget_reference', 3, 190 );
				$line = $this->repo->find( 'plan_lines', (int) $record['plan_line_id'], true );
				if ( ! $line ) {
					Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_PLAN_LINE_NOT_FOUND', 'The approved budget line no longer exists.', 409 );
				}
				$plan = $this->get_or_404( 'workforce_plans', (int) $line['plan_id'], true );
				$position = $this->get_or_404( 'positions', (int) $line['position_id'], true );
				if ( 'APPROVED' !== $plan['status'] || ! in_array( $position['status'], array( 'ACTIVE', 'APPROVED' ), true ) ) {
					Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_REQUISITION_PREREQUISITE_FAILED', 'An approved plan and approved position are required.', 409 );
				}
				if ( ! hash_equals( (string) $plan['budget_reference'], (string) $context['budget_reference'] ) ) {
					Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_BUDGET_REFERENCE_MISMATCH', 'The requisition must cite the approved budget reference on its workforce plan.', 409 );
				}
				$reserved = $this->repo->count_reserved_openings( (int) $line['id'], (int) $record['id'] );
				$context['headcount_capacity_verified'] = ( $reserved + (int) $record['openings'] ) <= (int) $line['budgeted_headcount'];
			}
			if ( 'REJECTED' === $to || 'NEEDS_INFORMATION' === $to ) {
				$context['decision_reason'] = $reason;
			}
			$this->transition( $aggregate, $from, $to, $context );
			$changes = array(
				'status'        => $to,
				'version_no'    => $expected_version + 1,
				'updated_by'    => $this->actor(),
				'decision_note' => $reason,
			);
			if ( 'SUBMITTED' === $to ) {
				$changes['submitted_by'] = $this->actor();
				$changes['submitted_at'] = current_time( 'mysql', true );
			}
			if ( 'APPROVED' === $to ) {
				$changes['approved_by'] = $this->actor();
				$changes['approved_at'] = current_time( 'mysql', true );
			}
			if ( 'workforce_plans' === $entity && 'APPROVED' === $to ) {
				$changes['budget_reference'] = $context['budget_reference'];
				$changes['approved_headcount_total'] = (int) $record['requested_headcount_total'];
				$changes['approved_budget_total'] = (string) $record['requested_budget_total'];
			}
			if ( 'requisitions' === $entity && 'APPROVED' === $to ) {
				$changes['budget_reference'] = $context['budget_reference'];
			}
			$updated = $this->repo->update_expected( $entity, $id, $expected_version, $from, $changes );
			$this->write_event( $key, $hash, strtoupper( $entity ), $updated, $event_type, $from, $to, $reason, array( 'decision' => $to, 'decision_reason_sha256' => hash( 'sha256', $reason ) ) );
			return array( 'record' => $updated, 'replayed' => false );
		} );
	}

	public function list_requisitions( $filters = array(), $limit = 50 ) {
		$this->require_view();
		if ( isset( $filters['location_id'] ) && '' !== $filters['location_id'] ) {
			$filters['location_id'] = $this->location( $filters['location_id'] );
		}
		if ( isset( $filters['plan_line_id'] ) && '' !== $filters['plan_line_id'] ) {
			$line = $this->get_or_404( 'plan_lines', (int) $filters['plan_line_id'] );
			$this->get_or_404( 'workforce_plans', (int) $line['plan_id'] );
		}
		return $this->repo->list_records( 'requisitions', $filters, $limit );
	}

	public function decide_requisition( $id, $expected_version, $decision, $reason, $budget_reference, $key ) {
		$decision = Stockland_HR_Replacement_Workforce_Contract::decode_decision( $decision );
		$extra = array();
		if ( 'APPROVE' === $decision ) {
			$extra['budget_reference'] = Stockland_HR_Replacement_Workforce_Contract::text( (string) $budget_reference, 'budget_reference', 3, 190 );
		}
		return $this->transition_record( 'requisitions', $id, $expected_version, $key, 'REQUISITION', $decision, 'REQUISITION_DECISION', $reason, $extra );
	}

	public function create_requisition( $input, $key ) {
		$this->require_prepare();
		$key = $this->idempotency( $key );
		$data = array(
			'requisition_code'     => Stockland_HR_Replacement_Workforce_Contract::code( $input['requisition_code'] ?? null, 'requisition_code' ),
			'plan_line_id'         => Stockland_HR_Replacement_Workforce_Contract::positive_int( $input['plan_line_id'] ?? null, 'plan_line_id' ),
			'openings'             => Stockland_HR_Replacement_Workforce_Contract::positive_int( $input['openings'] ?? null, 'openings', 10000 ),
			'priority'             => Stockland_HR_Replacement_Workforce_Contract::choice( $input['priority'] ?? 'NORMAL', 'priority', array( 'NORMAL', 'HIGH', 'URGENT' ) ),
			'requested_start_date' => Stockland_HR_Replacement_Workforce_Contract::date( $input['requested_start_date'] ?? null, 'requested_start_date', true ),
			'justification'        => Stockland_HR_Replacement_Workforce_Contract::text( $input['justification'] ?? null, 'justification', 20, 500 ),
			'location_id'          => $this->location( $input['location_id'] ?? null ),
		);
		$hash = Stockland_HR_Replacement_Workforce_Contract::request_hash( 'REQUISITION_CREATE', $data );
		return $this->repo->transaction( function () use ( $key, $hash, $data ) {
			$prior = $this->replay( $key, $hash, 'requisitions', 'REQUISITION_CREATED' );
			if ( $prior ) {
				return $prior;
			}
			if ( $this->repo->find_by_code( 'requisitions', 'requisition_code', $data['requisition_code'] ) ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_UNIQUE_CONFLICT', 'That requisition code already exists.', 409 );
			}
			$line = $this->get_or_404( 'plan_lines', $data['plan_line_id'], true );
			$plan = $this->get_or_404( 'workforce_plans', (int) $line['plan_id'], true );
			$position = $this->get_or_404( 'positions', (int) $line['position_id'], true );
			if ( 'APPROVED' !== $plan['status'] || ! in_array( $position['status'], array( 'APPROVED', 'ACTIVE' ), true ) ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_REQUISITION_PREREQUISITE_FAILED', 'The budget line must belong to an approved plan and an approved position.', 409 );
			}
			if ( (int) $data['openings'] > (int) $line['budgeted_headcount'] ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_HEADCOUNT_EXCEEDED', 'Requested openings exceed the approved budgeted headcount for this position.', 409 );
			}
			if ( null === $data['location_id'] ) {
				$data['location_id'] = $position['location_id'];
			}
			$actor = $this->actor();
			$record = $this->repo->insert(
				'requisitions',
				array_merge(
					$data,
					array(
						'filled_openings' => 0,
						'status'          => 'DRAFT',
						'version_no'      => 1,
						'budget_reference' => $plan['budget_reference'],
						'submitted_by'    => null,
						'submitted_at'    => null,
						'approved_by'     => null,
						'approved_at'     => null,
						'decision_note'   => null,
						'created_by'      => $actor,
						'updated_by'      => $actor,
					)
				)
			);
			$this->write_event( $key, $hash, 'REQUISITIONS', $record, 'REQUISITION_CREATED', null, 'DRAFT', 'Vacancy requisition created', array( 'plan_line_id' => $data['plan_line_id'], 'openings' => $data['openings'] ) );
			return array( 'record' => $record, 'replayed' => false );
		} );
	}

	public function submit_requisition( $id, $expected_version, $key ) {
		$this->require_prepare();
		$key = $this->idempotency( $key );
		$id  = Stockland_HR_Replacement_Workforce_Contract::positive_int( $id, 'requisition_id' );
		$expected_version = Stockland_HR_Replacement_Workforce_Contract::positive_int( $expected_version, 'version_no' );
		$hash = Stockland_HR_Replacement_Workforce_Contract::request_hash( 'REQUISITION_SUBMIT', array( 'id' => $id, 'version_no' => $expected_version ) );
		return $this->repo->transaction( function () use ( $id, $expected_version, $key, $hash ) {
			$prior = $this->replay( $key, $hash, 'requisitions', 'REQUISITION_SUBMITTED' );
			if ( $prior ) {
				return $prior;
			}
			$record = $this->get_or_404( 'requisitions', $id, true );
			$this->expected_version( $record, $expected_version );
			$line = $this->get_or_404( 'plan_lines', (int) $record['plan_line_id'], true );
			$plan = $this->get_or_404( 'workforce_plans', (int) $line['plan_id'], true );
			$position = $this->get_or_404( 'positions', (int) $line['position_id'], true );
			if ( 'APPROVED' !== $plan['status'] || ! in_array( $position['status'], array( 'APPROVED', 'ACTIVE' ), true ) ) {
				Stockland_HR_Replacement_Workforce_Contract::deny( 'HRX_REQUISITION_PREREQUISITE_FAILED', 'Plan approval and an approved position are required before submission.', 409 );
			}
			$context = array( 'actor_id' => (string) $this->actor(), 'maker_id' => (string) $record['created_by'] );
			$this->transition( 'REQUISITION', $record['status'], 'SUBMITTED', $context );
			$record = $this->repo->update_expected(
				'requisitions',
				$id,
				$expected_version,
				$record['status'],
				array(
					'status'       => 'SUBMITTED',
					'version_no'   => $expected_version + 1,
					'submitted_by' => $this->actor(),
					'submitted_at' => current_time( 'mysql', true ),
					'updated_by'   => $this->actor(),
				)
			);
			$this->write_event( $key, $hash, 'REQUISITIONS', $record, 'REQUISITION_SUBMITTED', 'DRAFT', 'SUBMITTED', null, array( 'plan_line_id' => (int) $line['id'], 'openings' => (int) $record['openings'] ) );
			return array( 'record' => $record, 'replayed' => false );
		} );
	}

	public function open_requisition( $id, $expected_version, $key ) {
		$this->require_prepare();
		$key = $this->idempotency( $key );
		$id  = Stockland_HR_Replacement_Workforce_Contract::positive_int( $id, 'requisition_id' );
		$expected_version = Stockland_HR_Replacement_Workforce_Contract::positive_int( $expected_version, 'version_no' );
		$hash = Stockland_HR_Replacement_Workforce_Contract::request_hash( 'REQUISITION_OPEN', array( 'id' => $id, 'version_no' => $expected_version ) );
		return $this->repo->transaction( function () use ( $id, $expected_version, $key, $hash ) {
			$prior = $this->replay( $key, $hash, 'requisitions', 'REQUISITION_OPENED' );
			if ( $prior ) {
				return $prior;
			}
			$record = $this->get_or_404( 'requisitions', $id, true );
			$this->expected_version( $record, $expected_version );
			$this->transition( 'REQUISITION', $record['status'], 'OPEN', array( 'actor_id' => (string) $this->actor(), 'maker_id' => (string) $record['created_by'] ) );
			$record = $this->repo->update_expected( 'requisitions', $id, $expected_version, $record['status'], array( 'status' => 'OPEN', 'version_no' => $expected_version + 1, 'updated_by' => $this->actor() ) );
			$this->write_event( $key, $hash, 'REQUISITIONS', $record, 'REQUISITION_OPENED', 'APPROVED', 'OPEN', 'Approved vacancy opened for recruitment', array( 'openings' => (int) $record['openings'] ) );
			return array( 'record' => $record, 'replayed' => false );
		} );
	}
}
