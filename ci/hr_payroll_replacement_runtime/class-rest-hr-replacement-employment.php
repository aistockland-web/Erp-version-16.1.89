<?php
/**
 * REST boundary for HR replacement contract, onboarding and private documents.
 *
 * The controller only exposes domain operations; all validation, current-state,
 * duplicate, legal, and record-scope checks are delegated to the service layer.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
if ( ! class_exists( 'Stockland_HR_Replacement_Workforce_WPDB_Repository' ) || ! class_exists( 'Stockland_HR_Replacement_Workforce_WP_Access' ) || ! class_exists( 'Stockland_HR_Replacement_Workforce_Service' ) || ! class_exists( 'Stockland_HR_Replacement_Private_Vault' ) || ! class_exists( 'Stockland_HR_Replacement_Employment_Service' ) ) {
	return;
}

final class Stockland_REST_HR_Replacement_Employment {
	const FALLBACK_NAMESPACE = 'stockland/v1';
	private $access;
	private $commands;
	private $service;
	private $vault;

	public function __construct() {
		$this->access = new Stockland_HR_Replacement_Workforce_WP_Access();
		$repo = new Stockland_HR_Replacement_Workforce_WPDB_Repository();
		$this->commands = new Stockland_HR_Replacement_Workforce_Service( $repo, $this->access );
		$this->vault = new Stockland_HR_Replacement_Private_Vault();
		$this->service = new Stockland_HR_Replacement_Employment_Service( $repo, $this->access, $this->commands, $this->vault );
		add_action( 'stockland_register_rest_routes', array( $this, 'register_routes' ) );
	}

	private function namespace_v1() {
		return defined( 'STOCKLAND_REST_NAMESPACE' ) ? STOCKLAND_REST_NAMESPACE : self::FALLBACK_NAMESPACE;
	}

	public function can_view() { return $this->access->can_view(); }
	public function can_prepare() { return $this->access->can_prepare(); }
	public function can_approve() { return $this->access->can_approve(); }
	public function can_view_payroll() { return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_view_payroll(); }

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

	private function command( WP_REST_Request $request, $operation, $callback, $include_files = false ) {
		$key = $this->key( $request );
		$data = $this->body( $request );
		if ( $include_files ) {
			$files = $request->get_file_params();
			if ( ! isset( $files['document'] ) || ! is_array( $files['document'] ) || empty( $files['document']['tmp_name'] ) || ! is_file( $files['document']['tmp_name'] ) ) {
				return new WP_Error( 'hrx_document_upload_missing', 'Attach the document in the multipart field named document.', array( 'status' => 422 ) );
			}
			$data['_upload_sha256'] = hash_file( 'sha256', $files['document']['tmp_name'] );
			$data['_upload_size'] = isset( $files['document']['size'] ) ? (int) $files['document']['size'] : 0;
		}
		return $this->commands->execute_idempotent(
			$key,
			$operation,
			$data,
			function () use ( $callback, $request, $data, $key ) {
				return call_user_func( $callback, $request, $data, $key );
			}
		);
	}

	private function filters( WP_REST_Request $request ) {
		$filters = array();
		foreach ( array( 'status', 'employment_id' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value && '' !== (string) $value ) {
				$filters[ $key ] = 'employment_id' === $key ? absint( $value ) : sanitize_text_field( (string) $value );
			}
		}
		return $filters;
	}

	public function register_routes() {
		$ns = $this->namespace_v1();

		register_rest_route( $ns, '/hr/replacement/employments', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'list_employments' ),
			'permission_callback' => array( $this, 'can_view' ),
		) );

		register_rest_route( $ns, '/hr/replacement/employments/(?P<id>\d+)/contracts', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'list_contracts' ), 'permission_callback' => array( $this, 'can_view' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_contract' ), 'permission_callback' => array( $this, 'can_prepare' ) ),
		) );

		register_rest_route( $ns, '/hr/replacement/contracts/(?P<id>\d+)/submit', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'submit_contract' ),
			'permission_callback' => array( $this, 'can_prepare' ),
		) );

		register_rest_route( $ns, '/hr/replacement/contracts/(?P<id>\d+)/employee-signature', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'sign_contract' ),
			'permission_callback' => array( $this, 'can_prepare' ),
		) );

		register_rest_route( $ns, '/hr/replacement/contracts/(?P<id>\d+)/owner-approval', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'approve_contract' ),
			'permission_callback' => array( $this, 'can_approve' ),
		) );

		register_rest_route( $ns, '/hr/replacement/employments/(?P<id>\d+)/onboarding-tasks', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'list_onboarding_tasks' ),
			'permission_callback' => array( $this, 'can_view' ),
		) );

		register_rest_route( $ns, '/hr/replacement/onboarding-tasks/(?P<id>\d+)/complete', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'complete_onboarding_task' ),
			'permission_callback' => array( $this, 'can_prepare' ),
		) );

		register_rest_route( $ns, '/hr/replacement/employments/(?P<id>\d+)/activate', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'activate_employment' ),
			'permission_callback' => array( $this, 'can_approve' ),
		) );

		register_rest_route( $ns, '/hr/replacement/employments/(?P<id>\d+)/decline-offer', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'decline_offer' ),
			'permission_callback' => array( $this, 'can_approve' ),
		) );

		register_rest_route( $ns, '/hr/replacement/documents', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'upload_document' ),
			'permission_callback' => array( $this, 'can_prepare' ),
		) );

		register_rest_route( $ns, '/hr/replacement/documents/(?P<id>\d+)/verify', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'verify_document' ),
			'permission_callback' => array( $this, 'can_prepare' ),
		) );

		register_rest_route( $ns, '/hr/replacement/documents/(?P<id>\d+)/approve', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'approve_document' ),
			'permission_callback' => array( $this, 'can_approve' ),
		) );

		register_rest_route( $ns, '/hr/replacement/documents/(?P<id>\d+)/download-url', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_document_download_url' ),
			'permission_callback' => array( $this, 'can_view' ),
		) );
	}

	public function list_employments( WP_REST_Request $request ) {
		return rest_ensure_response( $this->service->list_employments( $this->filters( $request ) ) );
	}
	public function list_contracts( WP_REST_Request $request ) {
		return rest_ensure_response( $this->service->list_contracts( absint( $request['id'] ) ) );
	}
	public function create_contract( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->command( $request, 'employment.contract.create', function ( $req, $data, $key ) use ( $id ) { return $this->service->create_contract( $id, $data, $key ); } );
	}
	public function submit_contract( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->command( $request, 'employment.contract.submit', function ( $req, $data, $key ) use ( $id ) { return $this->service->submit_contract( $id, $data, $key ); } );
	}
	public function sign_contract( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->command( $request, 'employment.contract.employee_signature', function ( $req, $data, $key ) use ( $id ) { return $this->service->sign_contract( $id, $data, $key ); } );
	}
	public function approve_contract( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->command( $request, 'employment.contract.owner_approval', function ( $req, $data, $key ) use ( $id ) { return $this->service->approve_contract( $id, $data, $key ); } );
	}
	public function list_onboarding_tasks( WP_REST_Request $request ) {
		return rest_ensure_response( $this->service->list_onboarding_tasks( absint( $request['id'] ) ) );
	}
	public function complete_onboarding_task( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->command( $request, 'employment.onboarding.complete', function ( $req, $data, $key ) use ( $id ) { return $this->service->complete_onboarding_task( $id, $data, $key ); } );
	}
	public function activate_employment( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->command( $request, 'employment.activate', function ( $req, $data, $key ) use ( $id ) { return $this->service->activate_employment( $id, $data, $key ); } );
	}
	public function decline_offer( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->command( $request, 'employment.decline_offer', function ( $req, $data, $key ) use ( $id ) { return $this->service->decline_offer( $id, $data, $key ); } );
	}
	public function upload_document( WP_REST_Request $request ) {
		return $this->command( $request, 'employment.document.upload', function ( $req, $data, $key ) use ( $request ) {
			$files = $request->get_file_params();
			return isset( $files['document'] ) ? $this->service->upload_document( $data, $files['document'], $key ) : new WP_Error( 'hrx_document_upload_missing', 'Attach the file in the multipart field named document.', array( 'status' => 422 ) );
		}, true );
	}
	public function verify_document( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->command( $request, 'employment.document.verify', function ( $req, $data, $key ) use ( $id ) { return $this->service->verify_document( $id, $data, $key ); } );
	}
	public function approve_document( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->command( $request, 'employment.document.approve', function ( $req, $data, $key ) use ( $id ) { return $this->service->approve_document( $id, $data, $key ); } );
	}
	public function get_document_download_url( WP_REST_Request $request ) {
		return rest_ensure_response( $this->service->get_document_download_url( absint( $request['id'] ) ) );
	}
}
