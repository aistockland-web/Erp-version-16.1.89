<?php
/**
 * REST surface for the isolated HR replacement workforce planning slice.
 *
 * This controller is not loaded by the live plugin. It is packaged/registered only
 * after the complete replacement integration and exact-installer gates pass.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) {
	exit;
}

final class Stockland_REST_HR_Replacement_Workforce extends Stockland_REST_Base {
	private $service_instance;

	public function __construct() {
		add_action( 'stockland_register_rest_routes', array( $this, 'register_routes' ) );
	}

	private function service() {
		if ( null === $this->service_instance ) {
			$repo = new Stockland_HR_Replacement_Workforce_WPDB_Repository();
			$access = new Stockland_HR_Replacement_WordPress_Access();
			$this->service_instance = new Stockland_HR_Replacement_Workforce_Service( $repo, $access );
		}
		return $this->service_instance;
	}

	private function view_permission() {
		return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_view_hr();
	}

	private function prepare_permission() {
		return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_prepare_hr();
	}

	private function owner_permission() {
		return class_exists( 'Stockland_Authority' )
			&& Stockland_Authority::is_owner()
			&& Stockland_Authority::can_manage_hr();
	}

	private function register( $route, $methods, $callback, $permission ) {
		register_rest_route(
			self::NAMESPACE_V1,
			$route,
			array(
				'methods'             => $methods,
				'callback'            => array( $this, $callback ),
				'permission_callback' => array( $this, $permission ),
			)
		);
	}

	public function register_routes() {
		$this->register( '/hr/replacement/workforce/bootstrap', 'GET', 'bootstrap', 'view_permission' );
		$this->register( '/hr/replacement/org-units', 'GET', 'org_units_list', 'view_permission' );
		$this->register( '/hr/replacement/org-units', 'POST', 'org_units_create', 'owner_permission' );

		$this->register( '/hr/replacement/workforce-plans', 'GET', 'plans_list', 'view_permission' );
		$this->register( '/hr/replacement/workforce-plans', 'POST', 'plans_create', 'prepare_permission' );
		$this->register( '/hr/replacement/workforce-plans/(?P<id>\d+)', 'GET', 'plans_get', 'view_permission' );
		$this->register( '/hr/replacement/workforce-plans/(?P<id>\d+)/lines', 'POST', 'plan_lines_create', 'prepare_permission' );
		$this->register( '/hr/replacement/workforce-plans/(?P<id>\d+)/lines/(?P<line_id>\d+)', 'POST', 'plan_lines_update', 'prepare_permission' );
		$this->register( '/hr/replacement/workforce-plans/(?P<id>\d+)/submit', 'POST', 'plans_submit', 'prepare_permission' );
		$this->register( '/hr/replacement/workforce-plans/(?P<id>\d+)/decision', 'POST', 'plans_decide', 'owner_permission' );

		$this->register( '/hr/replacement/positions', 'GET', 'positions_list', 'view_permission' );
		$this->register( '/hr/replacement/positions', 'POST', 'positions_create', 'prepare_permission' );
		$this->register( '/hr/replacement/positions/(?P<id>\d+)/submit', 'POST', 'positions_submit', 'prepare_permission' );
		$this->register( '/hr/replacement/positions/(?P<id>\d+)/decision', 'POST', 'positions_decide', 'owner_permission' );
		$this->register( '/hr/replacement/positions/(?P<id>\d+)/activate', 'POST', 'positions_activate', 'owner_permission' );

		$this->register( '/hr/replacement/requisitions', 'GET', 'requisitions_list', 'view_permission' );
		$this->register( '/hr/replacement/requisitions', 'POST', 'requisitions_create', 'prepare_permission' );
		$this->register( '/hr/replacement/requisitions/(?P<id>\d+)/submit', 'POST', 'requisitions_submit', 'prepare_permission' );
		$this->register( '/hr/replacement/requisitions/(?P<id>\d+)/decision', 'POST', 'requisitions_decide', 'owner_permission' );
		$this->register( '/hr/replacement/requisitions/(?P<id>\d+)/open', 'POST', 'requisitions_open', 'prepare_permission' );
	}

	private function payload( $request ) {
		$value = $request->get_json_params();
		return is_array( $value ) ? $value : array();
	}

	private function key( $request ) {
		return (string) $request->get_header( 'Idempotency-Key' );
	}

	private function input( $request, $name, $default = null ) {
		return $request->get_param( $name ) ?? $default;
	}

	private function run( callable $callback ) {
		try {
			return rest_ensure_response( $callback() );
		} catch ( DomainException $e ) {
			$message = (string) $e->getMessage();
			$code = 'hrx_request_failed';
			if ( false !== strpos( $message, ':' ) ) {
				$code = sanitize_key( strtolower( substr( $message, 0, strpos( $message, ':' ) ) ) );
			}
			$status = (int) $e->getCode();
			if ( $status < 400 || $status > 599 ) {
				$status = 409;
			}
			return new WP_Error( $code, $message, array( 'status' => $status ) );
		} catch ( Throwable $e ) {
			if ( class_exists( 'Stockland_Runtime_Diagnostics' ) ) {
				Stockland_Runtime_Diagnostics::log_error( 'HR replacement workforce request failed: ' . $e->getMessage() );
			}
			return new WP_Error( 'hrx_internal_error', 'The workforce request could not be completed. No success is reported; contact the ERP Owner with the request time.', array( 'status' => 500 ) );
		}
	}

	public function bootstrap( $request ) {
		return $this->run( function () {
			$ready = ( new Stockland_HR_Replacement_Workforce_WPDB_Repository() )->tables_ready();
			return array(
				'module'                  => 'HR_WORKFORCE_REPLACEMENT',
				'candidate_version'       => '0.1.0',
				'feature_state'           => $ready ? 'SCHEMA_READY_CANDIDATE_ONLY' : 'SCHEMA_NOT_READY',
				'runtime_activation'      => false,
				'production_certified'    => false,
				'legacy_tables_are_target'=> false,
				'features'                => array( 'organization_units', 'workforce_plans', 'positions', 'budget_lines', 'vacancy_requisitions' ),
				'message'                 => 'This replacement candidate is not part of the production plugin until all exact-package, security, migration and acceptance gates pass.',
			);
		} );
	}

	public function org_units_list( $request ) {
		return $this->run( function () use ( $request ) {
			return $this->service()->list_org_units( array( 'parent_id' => $this->input( $request, 'parent_id' ), 'status' => $this->input( $request, 'status' ) ), $this->input( $request, 'limit', 50 ) );
		} );
	}

	public function org_units_create( $request ) {
		return $this->run( function () use ( $request ) {
			return $this->service()->create_org_unit( $this->payload( $request ), $this->key( $request ) );
		} );
	}

	public function plans_list( $request ) {
		return $this->run( function () use ( $request ) {
			return $this->service()->list_plans( array(
				'org_unit_id' => $this->input( $request, 'org_unit_id' ),
				'location_id' => $this->input( $request, 'location_id' ),
				'status'      => $this->input( $request, 'status' ),
				'after_id'    => $this->input( $request, 'after_id' ),
			), $this->input( $request, 'limit', 50 ) );
		} );
	}

	public function plans_get( $request ) {
		return $this->run( function () use ( $request ) {
			return $this->service()->get_plan( (int) $request['id'] );
		} );
	}

	public function plans_create( $request ) {
		return $this->run( function () use ( $request ) {
			return $this->service()->create_plan( $this->payload( $request ), $this->key( $request ) );
		} );
	}

	public function plan_lines_create( $request ) {
		return $this->run( function () use ( $request ) {
			return $this->service()->add_plan_line( (int) $request['id'], $this->payload( $request ), $this->key( $request ) );
		} );
	}

	public function plan_lines_update( $request ) {
		return $this->run( function () use ( $request ) {
			$data = $this->payload( $request );
			return $this->service()->update_plan_line( (int) $request['id'], (int) $request['line_id'], $data, $data['version_no'] ?? null, $this->key( $request ) );
		} );
	}

	public function plans_submit( $request ) {
		return $this->run( function () use ( $request ) {
			$data = $this->payload( $request );
			return $this->service()->submit_plan( (int) $request['id'], $data['version_no'] ?? null, $this->key( $request ) );
		} );
	}

	public function plans_decide( $request ) {
		return $this->run( function () use ( $request ) {
			$data = $this->payload( $request );
			return $this->service()->decide_plan( (int) $request['id'], $data['version_no'] ?? null, $data['decision'] ?? null, $data['reason'] ?? null, $data['budget_reference'] ?? null, $this->key( $request ) );
		} );
	}

	public function positions_list( $request ) {
		return $this->run( function () use ( $request ) {
			return $this->service()->list_positions( array(
				'org_unit_id'        => $this->input( $request, 'org_unit_id' ),
				'location_id'        => $this->input( $request, 'location_id' ),
				'parent_position_id' => $this->input( $request, 'parent_position_id' ),
				'status'             => $this->input( $request, 'status' ),
				'after_id'            => $this->input( $request, 'after_id' ),
			), $this->input( $request, 'limit', 50 ) );
		} );
	}

	public function positions_create( $request ) {
		return $this->run( function () use ( $request ) {
			return $this->service()->create_position( $this->payload( $request ), $this->key( $request ) );
		} );
	}

	public function positions_submit( $request ) {
		return $this->run( function () use ( $request ) {
			$data = $this->payload( $request );
			return $this->service()->submit_position( (int) $request['id'], $data['version_no'] ?? null, $this->key( $request ) );
		} );
	}

	public function positions_decide( $request ) {
		return $this->run( function () use ( $request ) {
			$data = $this->payload( $request );
			return $this->service()->decide_position( (int) $request['id'], $data['version_no'] ?? null, $data['decision'] ?? null, $data['reason'] ?? null, $this->key( $request ) );
		} );
	}

	public function positions_activate( $request ) {
		return $this->run( function () use ( $request ) {
			$data = $this->payload( $request );
			return $this->service()->activate_position( (int) $request['id'], $data['version_no'] ?? null, $data['reason'] ?? null, $this->key( $request ) );
		} );
	}

	public function requisitions_list( $request ) {
		return $this->run( function () use ( $request ) {
			return $this->service()->list_requisitions( array(
				'plan_line_id' => $this->input( $request, 'plan_line_id' ),
				'location_id'  => $this->input( $request, 'location_id' ),
				'status'       => $this->input( $request, 'status' ),
				'after_id'     => $this->input( $request, 'after_id' ),
			), $this->input( $request, 'limit', 50 ) );
		} );
	}

	public function requisitions_create( $request ) {
		return $this->run( function () use ( $request ) {
			return $this->service()->create_requisition( $this->payload( $request ), $this->key( $request ) );
		} );
	}

	public function requisitions_submit( $request ) {
		return $this->run( function () use ( $request ) {
			$data = $this->payload( $request );
			return $this->service()->submit_requisition( (int) $request['id'], $data['version_no'] ?? null, $this->key( $request ) );
		} );
	}

	public function requisitions_decide( $request ) {
		return $this->run( function () use ( $request ) {
			$data = $this->payload( $request );
			$reference = $data['budget_reference'] ?? null;
			$reason = $data['reason'] ?? null;
			return $this->service()->decide_requisition( (int) $request['id'], $data['version_no'] ?? null, $data['decision'] ?? null, $reason, $reference, $this->key( $request ) );
		} );
	}

	public function requisitions_open( $request ) {
		return $this->run( function () use ( $request ) {
			$data = $this->payload( $request );
			return $this->service()->open_requisition( (int) $request['id'], $data['version_no'] ?? null, $this->key( $request ) );
		} );
	}
}
