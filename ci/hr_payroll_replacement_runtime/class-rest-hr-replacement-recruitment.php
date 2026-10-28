<?php
/**
 * REST routes for HR replacement recruitment and human-selection workflows.
 *
 * Auth is checked at the route and again at the record/location boundary inside
 * the domain service. Mutations require an idempotency key and remain transactional.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
if ( ! class_exists( 'Stockland_HR_Replacement_Workforce_WPDB_Repository' ) || ! class_exists( 'Stockland_HR_Replacement_Workforce_WP_Access' ) || ! class_exists( 'Stockland_HR_Replacement_Workforce_Service' ) || ! class_exists( 'Stockland_HR_Replacement_Recruitment_Service' ) ) {
	return;
}

final class Stockland_REST_HR_Replacement_Recruitment {
	const FALLBACK_NAMESPACE = 'stockland/v1';
	private $service;
	private $commands;
	private $access;

	public function __construct() {
		$this->access = new Stockland_HR_Replacement_Workforce_WP_Access();
		$repo = new Stockland_HR_Replacement_Workforce_WPDB_Repository();
		$this->commands = new Stockland_HR_Replacement_Workforce_Service( $repo, $this->access );
		$this->service = new Stockland_HR_Replacement_Recruitment_Service( $repo, $this->access, $this->commands );
		add_action( 'stockland_register_rest_routes', array( $this, 'register_routes' ) );
	}

	private function namespace_v1() {
		return defined( 'STOCKLAND_REST_NAMESPACE' ) ? STOCKLAND_REST_NAMESPACE : self::FALLBACK_NAMESPACE;
	}

	public function can_view() { return $this->access->can_view(); }
	public function can_prepare() { return $this->access->can_prepare(); }
	public function can_approve() { return $this->access->can_approve(); }

	private function body( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		return is_array( $body ) ? $body : array();
	}

	private function key( WP_REST_Request $request ) {
		$key = trim( (string) $request->get_header( 'Idempotency-Key' ) );
		if ( '' === $key ) {
			$body = $this->body( $request );
			$key = isset( $body['idempotency_key'] ) ? trim( (string) $body['idempotency_key'] ) : '';
		}
		return $key;
	}

	private function command( WP_REST_Request $request, $operation, $callback ) {
		$key = $this->key( $request );
		$data = $this->body( $request );
		return $this->commands->execute_idempotent(
			$key,
			$operation,
			$data,
			function () use ( $callback, $data, $key, $request ) {
				return call_user_func( $callback, $request, $data, $key );
			}
		);
	}

	private function filters( WP_REST_Request $request ) {
		$filters = array();
		foreach ( array( 'status', 'stage', 'requisition_id' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value && '' !== (string) $value ) {
				$filters[ $key ] = 'requisition_id' === $key ? absint( $value ) : sanitize_text_field( (string) $value );
			}
		}
		return $filters;
	}

	public function register_routes() {
		$ns = $this->namespace_v1();

		register_rest_route( $ns, '/hr/replacement/recruitment/candidates', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'create_candidate' ),
			'permission_callback' => array( $this, 'can_prepare' ),
		) );
		register_rest_route( $ns, '/hr/replacement/recruitment/candidates/(?P<id>\d+)', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_candidate' ),
			'permission_callback' => array( $this, 'can_view' ),
		) );
		register_rest_route( $ns, '/hr/replacement/recruitment/candidates/(?P<id>\d+)/privacy-review', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'review_candidate_privacy' ),
			'permission_callback' => array( $this, 'can_approve' ),
		) );
		register_rest_route( $ns, '/hr/replacement/recruitment/applications', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'list_applications' ), 'permission_callback' => array( $this, 'can_view' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_application' ), 'permission_callback' => array( $this, 'can_prepare' ) ),
		) );
		register_rest_route( $ns, '/hr/replacement/recruitment/applications/(?P<id>\d+)/interviews', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'list_interviews' ), 'permission_callback' => array( $this, 'can_view' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'schedule_interview' ), 'permission_callback' => array( $this, 'can_prepare' ) ),
		) );
		register_rest_route( $ns, '/hr/replacement/recruitment/interviews/(?P<id>\d+)/complete', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'complete_interview' ),
			'permission_callback' => array( $this, 'can_prepare' ),
		) );
		register_rest_route( $ns, '/hr/replacement/recruitment/applications/(?P<id>\d+)/selection-recommendation', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'recommend_selection' ),
			'permission_callback' => array( $this, 'can_prepare' ),
		) );
		register_rest_route( $ns, '/hr/replacement/recruitment/selection-decisions/(?P<id>\d+)/decision', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'decide_selection' ),
			'permission_callback' => array( $this, 'can_approve' ),
		) );
	}

	public function create_candidate( WP_REST_Request $request ) {
		return $this->command( $request, 'recruitment.candidate.create', function ( $req, $data, $key ) { return $this->service->create_candidate( $data, $key ); } );
	}

	public function get_candidate( WP_REST_Request $request ) {
		return rest_ensure_response( $this->service->get_candidate( absint( $request['id'] ) ) );
	}

	public function review_candidate_privacy( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->command( $request, 'recruitment.candidate.privacy_review', function ( $req, $data, $key ) use ( $id ) { return $this->service->review_candidate_privacy( $id, $data, $key ); } );
	}

	public function list_applications( WP_REST_Request $request ) {
		return rest_ensure_response( $this->service->list_applications( $this->filters( $request ) ) );
	}

	public function create_application( WP_REST_Request $request ) {
		return $this->command( $request, 'recruitment.application.create', function ( $req, $data, $key ) { return $this->service->create_application( $data, $key ); } );
	}

	public function list_interviews( WP_REST_Request $request ) {
		return rest_ensure_response( $this->service->list_interviews( absint( $request['id'] ) ) );
	}

	public function schedule_interview( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->command( $request, 'recruitment.interview.schedule', function ( $req, $data, $key ) use ( $id ) { return $this->service->schedule_interview( $id, $data, $key ); } );
	}

	public function complete_interview( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->command( $request, 'recruitment.interview.complete', function ( $req, $data, $key ) use ( $id ) { return $this->service->complete_interview( $id, $data, $key ); } );
	}

	public function recommend_selection( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->command( $request, 'recruitment.selection.recommend', function ( $req, $data, $key ) use ( $id ) { return $this->service->recommend_selection( $id, $data, $key ); } );
	}

	public function decide_selection( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->command( $request, 'recruitment.selection.decide', function ( $req, $data, $key ) use ( $id ) { return $this->service->decide_selection( $id, $data, $key ); } );
	}
}
