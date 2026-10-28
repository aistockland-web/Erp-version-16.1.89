<?php
/**
 * WordPress REST adapter for the HR replacement workforce vertical slice.
 *
 * Route permission callbacks are only the first authorization layer; each record
 * operation also performs branch-scope checks and the service enforces workflow
 * state, effective evidence, version and idempotency contracts.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
if ( ! class_exists( 'Stockland_HR_Replacement_Workforce_WPDB_Repository' ) || ! class_exists( 'Stockland_HR_Replacement_Workforce_WP_Access' ) || ! class_exists( 'Stockland_HR_Replacement_Workforce_Service' ) ) {
	return;
}

final class Stockland_REST_HR_Replacement_Workforce {
	const FALLBACK_NAMESPACE = 'stockland/v1';
	private $service;
	private $access;

	public function __construct() {
		$this->access = new Stockland_HR_Replacement_Workforce_WP_Access();
		$this->service = new Stockland_HR_Replacement_Workforce_Service(
			new Stockland_HR_Replacement_Workforce_WPDB_Repository(),
			$this->access
		);
		add_action( 'stockland_register_rest_routes', array( $this, 'register_routes' ) );
	}

	private function namespace_v1() {
		return defined( 'STOCKLAND_REST_NAMESPACE' ) ? STOCKLAND_REST_NAMESPACE : self::FALLBACK_NAMESPACE;
	}

	public function can_view() {
		return $this->access->can_view();
	}

	public function can_prepare() {
		return $this->access->can_prepare();
	}

	public function can_approve() {
		return $this->access->can_approve();
	}

	public function can_manage_structure() {
		return $this->access->can_manage_structure();
	}

	/**
	 * Require a caller-generated, stable idempotency key for every mutation.
	 */
	private function idempotency_key( WP_REST_Request $request ) {
		$key = trim( (string) $request->get_header( 'Idempotency-Key' ) );
		if ( '' === $key ) {
			$body = $request->get_json_params();
			if ( is_array( $body ) && isset( $body['idempotency_key'] ) ) {
				$key = trim( (string) $body['idempotency_key'] );
			}
		}
		return $key;
	}

	private function body( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		return is_array( $body ) ? $body : array();
	}

	private function list_filters( WP_REST_Request $request ) {
		$filters = array();
		foreach ( array( 'status', 'fiscal_year', 'department', 'location_id', 'org_unit_id', 'position_id', 'plan_id', 'unit_type' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value && '' !== (string) $value ) {
				$filters[ $key ] = is_numeric( $value ) && false !== strpos( $key, '_id' ) ? absint( $value ) : sanitize_text_field( (string) $value );
			}
		}
		return $filters;
	}

	private function command( WP_REST_Request $request, $operation, $key, $callback ) {
		try {
			return $this->service->execute_idempotent( $key, $operation, $this->body( $request ), $callback );
		} catch ( Throwable $e ) {
			error_log( '[Stockland HRX] operation failed (' . get_class( $e ) . ').' );
			return new WP_Error( 'hrx_operation_failed', 'The workforce operation was not confirmed. Refresh and verify status before retrying.', array( 'status' => 409 ) );
		}
	}

	public function register_routes() {
		$namespace = $this->namespace_v1();

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/org-units',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'list_org_units' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/plans',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'list_plans' ), 'permission_callback' => array( $this, 'can_view' ) ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_plan' ), 'permission_callback' => array( $this, 'can_prepare' ) ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/plans/(?P<id>\d+)',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_plan' ), 'permission_callback' => array( $this, 'can_view' ) ),
				array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_plan' ), 'permission_callback' => array( $this, 'can_prepare' ) ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/plans/(?P<id>\d+)/lines',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'create_plan_line' ),
				'permission_callback' => array( $this, 'can_prepare' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/plans/(?P<id>\d+)/lines/(?P<line_id>\d+)',
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'update_plan_line' ),
				'permission_callback' => array( $this, 'can_prepare' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/plans/(?P<id>\d+)/submit',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'submit_plan' ),
				'permission_callback' => array( $this, 'can_prepare' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/plans/(?P<id>\d+)/decision',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'decide_plan' ),
				'permission_callback' => array( $this, 'can_approve' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/positions',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'list_positions' ), 'permission_callback' => array( $this, 'can_view' ) ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_position' ), 'permission_callback' => array( $this, 'can_manage_structure' ) ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/positions/(?P<id>\d+)/submit',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'submit_position' ),
				'permission_callback' => array( $this, 'can_manage_structure' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/positions/(?P<id>\d+)/decision',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'decide_position' ),
				'permission_callback' => array( $this, 'can_approve' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/positions/(?P<id>\d+)/activate',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'activate_position' ),
				'permission_callback' => array( $this, 'can_approve' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/requisitions',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'list_requisitions' ), 'permission_callback' => array( $this, 'can_view' ) ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_requisition' ), 'permission_callback' => array( $this, 'can_prepare' ) ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/requisitions/(?P<id>\d+)/submit',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'submit_requisition' ),
				'permission_callback' => array( $this, 'can_prepare' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/requisitions/(?P<id>\d+)/decision',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'decide_requisition' ),
				'permission_callback' => array( $this, 'can_approve' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/requisitions/(?P<id>\d+)/open',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'open_requisition' ),
				'permission_callback' => array( $this, 'can_manage_structure' ),
			)
		);
	}

	public function list_org_units( WP_REST_Request $request ) {
		return rest_ensure_response( $this->service->list_org_units( $this->list_filters( $request ) ) );
	}

	public function list_plans( WP_REST_Request $request ) {
		return rest_ensure_response( $this->service->list_plans( $this->list_filters( $request ) ) );
	}

	public function get_plan( WP_REST_Request $request ) {
		return rest_ensure_response( $this->service->get_plan( absint( $request['id'] ) ) );
	}

	public function create_plan( WP_REST_Request $request ) {
		$key = $this->idempotency_key( $request );
		$data = $this->body( $request );
		return $this->command( $request, 'workforce_plan.create', $key, function () use ( $data, $key ) { return $this->service->create_plan( $data, $key ); } );
	}

	public function update_plan( WP_REST_Request $request ) {
		$key = $this->idempotency_key( $request );
		$data = $this->body( $request );
		$id = absint( $request['id'] );
		return $this->command( $request, 'workforce_plan.update', $key, function () use ( $id, $data, $key ) { return $this->service->update_plan( $id, $data, $key ); } );
	}

	public function create_plan_line( WP_REST_Request $request ) {
		$key = $this->idempotency_key( $request );
		$data = $this->body( $request );
		$id = absint( $request['id'] );
		return $this->command( $request, 'workforce_plan_line.create', $key, function () use ( $id, $data, $key ) { return $this->service->create_plan_line( $id, $data, $key ); } );
	}

	public function update_plan_line( WP_REST_Request $request ) {
		$key = $this->idempotency_key( $request );
		$data = $this->body( $request );
		$id = absint( $request['id'] );
		$line_id = absint( $request['line_id'] );
		return $this->command( $request, 'workforce_plan_line.update', $key, function () use ( $id, $line_id, $data, $key ) { return $this->service->update_plan_line( $id, $line_id, $data, $key ); } );
	}

	public function submit_plan( WP_REST_Request $request ) {
		$key = $this->idempotency_key( $request );
		$data = $this->body( $request );
		$id = absint( $request['id'] );
		return $this->command( $request, 'workforce_plan.submit', $key, function () use ( $id, $data, $key ) { return $this->service->submit_plan( $id, $data, $key ); } );
	}

	public function decide_plan( WP_REST_Request $request ) {
		$key = $this->idempotency_key( $request );
		$data = $this->body( $request );
		$id = absint( $request['id'] );
		return $this->command( $request, 'workforce_plan.decide', $key, function () use ( $id, $data, $key ) { return $this->service->decide_plan( $id, $data, $key ); } );
	}

	public function list_positions( WP_REST_Request $request ) {
		return rest_ensure_response( $this->service->list_positions( $this->list_filters( $request ) ) );
	}

	public function create_position( WP_REST_Request $request ) {
		$key = $this->idempotency_key( $request );
		$data = $this->body( $request );
		return $this->command( $request, 'position.create', $key, function () use ( $data, $key ) { return $this->service->create_position( $data, $key ); } );
	}

	public function submit_position( WP_REST_Request $request ) {
		$key = $this->idempotency_key( $request );
		$data = $this->body( $request );
		$id = absint( $request['id'] );
		return $this->command( $request, 'position.submit', $key, function () use ( $id, $data, $key ) { return $this->service->submit_position( $id, $data, $key ); } );
	}

	public function decide_position( WP_REST_Request $request ) {
		$key = $this->idempotency_key( $request );
		$data = $this->body( $request );
		$id = absint( $request['id'] );
		return $this->command( $request, 'position.decide', $key, function () use ( $id, $data, $key ) { return $this->service->decide_position( $id, $data, $key ); } );
	}

	public function activate_position( WP_REST_Request $request ) {
		$key = $this->idempotency_key( $request );
		$data = $this->body( $request );
		$id = absint( $request['id'] );
		return $this->command( $request, 'position.activate', $key, function () use ( $id, $data, $key ) { return $this->service->activate_position( $id, $data, $key ); } );
	}

	public function list_requisitions( WP_REST_Request $request ) {
		return rest_ensure_response( $this->service->list_requisitions( $this->list_filters( $request ) ) );
	}

	public function create_requisition( WP_REST_Request $request ) {
		$key = $this->idempotency_key( $request );
		$data = $this->body( $request );
		return $this->command( $request, 'vacancy_requisition.create', $key, function () use ( $data, $key ) { return $this->service->create_requisition( $data, $key ); } );
	}

	public function submit_requisition( WP_REST_Request $request ) {
		$key = $this->idempotency_key( $request );
		$data = $this->body( $request );
		$id = absint( $request['id'] );
		return $this->command( $request, 'vacancy_requisition.submit', $key, function () use ( $id, $data, $key ) { return $this->service->submit_requisition( $id, $data, $key ); } );
	}

	public function decide_requisition( WP_REST_Request $request ) {
		$key = $this->idempotency_key( $request );
		$data = $this->body( $request );
		$id = absint( $request['id'] );
		return $this->command( $request, 'vacancy_requisition.decide', $key, function () use ( $id, $data, $key ) { return $this->service->decide_requisition( $id, $data, $key ); } );
	}

	public function open_requisition( WP_REST_Request $request ) {
		$key = $this->idempotency_key( $request );
		$data = $this->body( $request );
		$id = absint( $request['id'] );
		return $this->command( $request, 'vacancy_requisition.open', $key, function () use ( $id, $data, $key ) { return $this->service->open_requisition( $id, $data, $key ); } );
	}
}
