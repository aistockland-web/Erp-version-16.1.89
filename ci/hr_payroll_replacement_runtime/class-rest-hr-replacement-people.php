<?php
/**
 * Isolated REST surface for People/Recruitment/Contract/Onboarding.
 * This file is not included by the production plugin bootstrap.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! defined( 'STOCKLAND_PLUGIN_DIR' ) || ! class_exists( 'Stockland_REST_Base' ) || ! class_exists( 'Stockland_Authority' ) ) { return; }

require_once STOCKLAND_PLUGIN_DIR . 'includes/Stockland_HR_Replacement_People_Contract.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/Stockland_HR_Replacement_People_Repository.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/Stockland_HR_Replacement_People_Service.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/Stockland_HR_Replacement_People_Schema.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/Stockland_HR_Replacement_People_WPDB_Repository.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/Stockland_HR_Replacement_People_WP_Access.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/Stockland_HR_Replacement_WP_Private_Vault.php';

final class Stockland_REST_HR_Replacement_People {
	private $service;
	private $access;
	public function __construct() { add_action( 'stockland_register_rest_routes', array( $this, 'register_routes' ) ); }
	private function access() {
		if ( ! $this->access ) { $this->access = new Stockland_HR_Replacement_People_WP_Access(); }
		return $this->access;
	}
	private function service() {
		if ( ! $this->service ) {
			$this->service = new Stockland_HR_Replacement_People_Service(
				new Stockland_HR_Replacement_People_WPDB_Repository(),
				$this->access(),
				new Stockland_HR_Replacement_WP_Private_Vault()
			);
		}
		return $this->service;
	}
	public function can_view() { return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_view_hr(); }
	public function can_prepare() { return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_prepare_hr(); }
	public function can_approve() { return class_exists( 'Stockland_Authority' ) && Stockland_Authority::is_owner() && Stockland_Authority::can_approve_payroll(); }
	public function can_prepare_or_approve() { return $this->can_prepare() || $this->can_approve(); }
	private function invoke( callable $callback ) {
		try { return rest_ensure_response( $callback() ); }
		catch ( DomainException $e ) {
			$parts = explode( ':', $e->getMessage(), 2 ); $code = sanitize_key( strtolower( $parts[0] ) );
			$status = (int) $e->getCode(); if ( $status < 400 || $status > 599 ) { $status = 409; }
			return new WP_Error( $code, isset( $parts[1] ) ? trim( $parts[1] ) : 'HR lifecycle operation rejected.', array( 'status' => $status ) );
		} catch ( InvalidArgumentException $e ) {
			$parts = explode( ':', $e->getMessage(), 2 );
			return new WP_Error( sanitize_key( strtolower( $parts[0] ) ), isset( $parts[1] ) ? trim( $parts[1] ) : 'Invalid HR lifecycle input.', array( 'status' => 422 ) );
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) { error_log( '[Stockland HRXP People] operation failed (' . get_class( $e ) . ').' ); }
			return new WP_Error( 'hrxp_people_internal_error', 'The request could not be completed; do not assume it succeeded. Contact an authorized ERP administrator.', array( 'status' => 500 ) );
		}
	}
	private function payload( $request, $key = null ) {
		$p = $request->get_json_params(); if ( ! is_array( $p ) ) { $p = array(); }
		if ( null !== $key ) { $p[ $key ] = (int) $request['id']; }
		return $p;
	}
	private function idempotency_key( $request ) { return (string) $request->get_header( 'Idempotency-Key' ); }
	private function register( $path, $methods, $callback, $permission ) {
		register_rest_route( Stockland_REST_Base::NAMESPACE_V1, $path, array( 'methods' => $methods, 'callback' => array( $this, $callback ), 'permission_callback' => array( $this, $permission ) ) );
	}
	public function register_routes() {
		$this->register( '/hr/replacement/people/candidates/(?P<id>\d+)', WP_REST_Server::READABLE, 'candidate_get', 'can_prepare' );
		$this->register( '/hr/replacement/people/candidates', WP_REST_Server::CREATABLE, 'candidate_create', 'can_prepare' );
		$this->register( '/hr/replacement/people/applications', WP_REST_Server::READABLE, 'applications_list', 'can_prepare' );
		$this->register( '/hr/replacement/people/applications', WP_REST_Server::CREATABLE, 'application_create', 'can_prepare' );
		$this->register( '/hr/replacement/people/applications/(?P<id>\d+)/stage', WP_REST_Server::CREATABLE, 'application_stage', 'can_prepare' );
		$this->register( '/hr/replacement/people/interviews', WP_REST_Server::CREATABLE, 'interview_create', 'can_prepare' );
		$this->register( '/hr/replacement/people/pending-hires', WP_REST_Server::CREATABLE, 'hire_create', 'can_prepare' );
		$this->register( '/hr/replacement/people/private-documents', WP_REST_Server::CREATABLE, 'document_create', 'can_prepare' );
		$this->register( '/hr/replacement/people/private-documents/(?P<id>\d+)/verify', WP_REST_Server::CREATABLE, 'document_verify', 'can_approve' );
		$this->register( '/hr/replacement/people/contracts/(?P<id>\d+)/sign', WP_REST_Server::CREATABLE, 'contract_sign', 'can_prepare' );
		$this->register( '/hr/replacement/people/contracts/(?P<id>\d+)/approve', WP_REST_Server::CREATABLE, 'contract_approve', 'can_approve' );
		$this->register( '/hr/replacement/people/employments/(?P<id>\d+)/onboarding', WP_REST_Server::READABLE, 'onboarding_list', 'can_prepare' );
		$this->register( '/hr/replacement/people/onboarding/(?P<id>\d+)/complete', WP_REST_Server::CREATABLE, 'onboarding_complete', 'can_prepare' );
	}
	public function candidate_get( $r ) { return $this->invoke( function() use($r){ return $this->service()->get_candidate((int)$r['id']); } ); }
	public function candidate_create( $r ) { return $this->invoke( function() use($r){ return $this->service()->create_candidate($this->payload($r),$this->idempotency_key($r)); } ); }
	public function applications_list( $r ) { return $this->invoke( function() use($r){ return $this->service()->list_applications(array('stage'=>$r->get_param('stage'),'candidate_id'=>$r->get_param('candidate_id'),'requisition_id'=>$r->get_param('requisition_id'),'location_id'=>$r->get_param('location_id')),$r->get_param('limit')?:50); } ); }
	public function application_create( $r ) { return $this->invoke( function() use($r){ return $this->service()->create_application($this->payload($r),$this->idempotency_key($r)); } ); }
	public function application_stage( $r ) { return $this->invoke( function() use($r){ return $this->service()->move_application($this->payload($r,'application_id'),$this->idempotency_key($r)); } ); }
	public function interview_create( $r ) { return $this->invoke( function() use($r){ return $this->service()->record_interview($this->payload($r),$this->idempotency_key($r)); } ); }
	public function hire_create( $r ) { return $this->invoke( function() use($r){ return $this->service()->create_pending_hire($this->payload($r),$this->idempotency_key($r)); } ); }
	public function document_create( $r ) { return $this->invoke( function() use($r){ return $this->service()->register_private_document($this->payload($r),$this->idempotency_key($r)); } ); }
	public function document_verify( $r ) { return $this->invoke( function() use($r){ return $this->service()->verify_private_document($this->payload($r,'document_id'),$this->idempotency_key($r)); } ); }
	public function contract_sign( $r ) { return $this->invoke( function() use($r){ return $this->service()->sign_contract($this->payload($r,'contract_id'),$this->idempotency_key($r)); } ); }
	public function contract_approve( $r ) { return $this->invoke( function() use($r){ return $this->service()->approve_contract($this->payload($r,'contract_id'),$this->idempotency_key($r)); } ); }
	public function onboarding_list( $r ) { return $this->invoke( function() use($r){ return $this->service()->list_onboarding((int)$r['id']); } ); }
	public function onboarding_complete( $r ) { return $this->invoke( function() use($r){ return $this->service()->complete_onboarding_task($this->payload($r,'task_id'),$this->idempotency_key($r)); } ); }
}
