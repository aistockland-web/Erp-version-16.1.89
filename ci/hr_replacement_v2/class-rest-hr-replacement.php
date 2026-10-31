<?php
// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName -- Loaded explicitly by the Stockland ERP bootstrap.
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * HRX replacement API. This controller uses only hrx_* target tables; the legacy
 * four HR/payroll tables remain read-only migration sources.
 */
final class Stockland_REST_HR_Replacement extends Stockland_REST_Base {
	const ENTITY_MAP = array(
		'people' => array( 'table'=>'hrx_people', 'state'=>'privacy_state', 'create'=>true, 'update'=>true ),
		'org-units' => array( 'table'=>'hrx_org_units', 'state'=>'active', 'create'=>true, 'update'=>true ),
		'positions' => array( 'table'=>'hrx_positions', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'workforce-plans' => array( 'table'=>'hrx_workforce_plans', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'requisitions' => array( 'table'=>'hrx_requisitions', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'applications' => array( 'table'=>'hrx_applications', 'state'=>'stage', 'create'=>true, 'update'=>true ),
		'interviews' => array( 'table'=>'hrx_interviews', 'state'=>'recommendation', 'create'=>true, 'update'=>true ),
		'offers' => array( 'table'=>'hrx_offers', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'employments' => array( 'table'=>'hrx_employments', 'state'=>'status', 'create'=>false, 'update'=>false ),
		'contracts' => array( 'table'=>'hrx_contracts', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'onboarding-tasks' => array( 'table'=>'hrx_onboarding_tasks', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'assignments' => array( 'table'=>'hrx_assignments', 'state'=>'approval_status', 'create'=>true, 'update'=>true ),
		'schedules' => array( 'table'=>'hrx_schedules', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'time-events' => array( 'table'=>'hrx_time_events', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'leave-policies' => array( 'table'=>'hrx_leave_policies', 'state'=>'approval_status', 'create'=>true, 'update'=>true ),
		'leave-transactions' => array( 'table'=>'hrx_leave_transactions', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'compensation-assignments' => array( 'table'=>'hrx_compensation_assignments', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'compensation-items' => array( 'table'=>'hrx_compensation_items', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'legal-rules' => array( 'table'=>'hrx_legal_rules', 'state'=>'review_status', 'create'=>true, 'update'=>true ),
		'legal-rule-bands' => array( 'table'=>'hrx_legal_rule_bands', 'state'=>'', 'create'=>true, 'update'=>false ),
		'payee-engagements' => array( 'table'=>'hrx_payee_engagements', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'payee-requests' => array( 'table'=>'hrx_payee_requests', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'payee-deliverables' => array( 'table'=>'hrx_payee_deliverables', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'payee-classifications' => array( 'table'=>'hrx_payee_classifications', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'social-snapshots' => array( 'table'=>'hrx_social_snapshots', 'state'=>'freshness_status', 'create'=>true, 'update'=>false ),
		'exit-settlements' => array( 'table'=>'hrx_exit_settlements', 'state'=>'status', 'create'=>false, 'update'=>false ),
		'exit-items' => array( 'table'=>'hrx_exit_items', 'state'=>'', 'create'=>false, 'update'=>false ),
		'exit-clearance-tasks' => array( 'table'=>'hrx_exit_clearance_tasks', 'state'=>'status', 'create'=>false, 'update'=>false ),
		'exit-payouts' => array( 'table'=>'hrx_exit_payouts', 'state'=>'status', 'create'=>false, 'update'=>false ),
		'payee-payments' => array( 'table'=>'hrx_payee_payments', 'state'=>'status', 'create'=>false, 'update'=>false ),
		'payee-payment-attempts' => array( 'table'=>'hrx_payee_payment_attempts', 'state'=>'attempt_status', 'create'=>false, 'update'=>false ),
		'accounting-config' => array( 'table'=>'hrx_accounting_config', 'state'=>'status', 'create'=>true, 'update'=>true ),
		'payroll-runs' => array( 'table'=>'hrx_payroll_runs', 'state'=>'status', 'create'=>false, 'update'=>false ),
		'payroll-lines' => array( 'table'=>'hrx_payroll_lines', 'state'=>'line_status', 'create'=>false, 'update'=>false ),
		'payout-batches' => array( 'table'=>'hrx_payout_batches', 'state'=>'status', 'create'=>false, 'update'=>false ),
		'payout-items' => array( 'table'=>'hrx_payout_items', 'state'=>'status', 'create'=>false, 'update'=>false ),
		'statutory-obligations' => array( 'table'=>'hrx_statutory_obligations', 'state'=>'status', 'create'=>false, 'update'=>false ),
		'remittances' => array( 'table'=>'hrx_remittances', 'state'=>'status', 'create'=>false, 'update'=>false )
	);

	private function view_hr() { return is_user_logged_in() && Stockland_Authority::can_view_hr(); }
	private function manage_hr() { return is_user_logged_in() && Stockland_Authority::can_manage_hr(); }
	private function view_payroll() { return is_user_logged_in() && Stockland_Authority::can_view_payroll(); }
	private function manage_payroll() { return is_user_logged_in() && Stockland_Authority::can_manage_payroll(); }
	private function approve_payroll() { return is_user_logged_in() && Stockland_Authority::can_approve_payroll(); }
	private function schema_admin() { return is_user_logged_in() && ( current_user_can( 'manage_options' ) || current_user_can( 'stockland_install_schema' ) ); }

	public function __construct() {
		add_action( 'stockland_register_rest_routes', array( $this, 'register_routes' ) );
		add_action( 'admin_post_stockland_hrx_document_download', array( $this, 'download_document' ) );
		add_action( 'admin_post_nopriv_stockland_hrx_document_download', array( $this, 'deny_document_download' ) );
	}

	public function register_routes() {
		$ns = self::NAMESPACE_V1;
		register_rest_route( $ns, '/hr/replacement/bootstrap', array( 'methods'=>'GET', 'callback'=>array( $this,'bootstrap' ), 'permission_callback'=>array( $this,'permission_view_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/schema/batch', array( 'methods'=>'POST', 'callback'=>array( $this,'schema_batch' ), 'permission_callback'=>array( $this,'permission_schema_admin' ) ) );
		register_rest_route( $ns, '/hr/replacement/schema/reset-legacy', array( 'methods'=>'POST', 'callback'=>array( $this,'reset_legacy_tables' ), 'permission_callback'=>array( $this,'permission_schema_admin' ) ) );
		register_rest_route( $ns, '/hr/replacement/entities/(?P<entity>[a-z\-]+)/?', array( array( 'methods'=>'GET','callback'=>array( $this,'list_entity' ),'permission_callback'=>array( $this,'permission_view_hr' ) ), array( 'methods'=>'POST','callback'=>array( $this,'create_entity' ),'permission_callback'=>array( $this,'permission_manage_hr' ) ) ) );
		register_rest_route( $ns, '/hr/replacement/entities/(?P<entity>[a-z\-]+)/(?P<id>\d+)', array( array( 'methods'=>'GET','callback'=>array( $this,'get_entity' ),'permission_callback'=>array( $this,'permission_view_hr' ) ), array( 'methods'=>'POST','callback'=>array( $this,'update_entity' ),'permission_callback'=>array( $this,'permission_manage_hr' ) ) ) );
		register_rest_route( $ns, '/hr/replacement/entities/(?P<entity>[a-z\-]+)/(?P<id>\d+)/transition', array( 'methods'=>'POST','callback'=>array( $this,'transition_entity' ),'permission_callback'=>array( $this,'permission_manage_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/offers/(?P<id>\d+)/create-employment', array( 'methods'=>'POST','callback'=>array( $this,'create_employment_from_offer' ),'permission_callback'=>array( $this,'permission_approve_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/payroll/runs', array( 'methods'=>'POST','callback'=>array( $this,'create_payroll_run' ),'permission_callback'=>array( $this,'permission_manage_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/payroll/runs/(?P<id>\d+)/preflight', array( 'methods'=>'GET','callback'=>array( $this,'payroll_preflight' ),'permission_callback'=>array( $this,'permission_manage_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/payroll/runs/(?P<id>\d+)/calculate', array( 'methods'=>'POST','callback'=>array( $this,'calculate_payroll' ),'permission_callback'=>array( $this,'permission_manage_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/payroll/runs/(?P<id>\d+)/submit', array( 'methods'=>'POST','callback'=>array( $this,'submit_payroll' ),'permission_callback'=>array( $this,'permission_manage_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/payroll/runs/(?P<id>\d+)/approve', array( 'methods'=>'POST','callback'=>array( $this,'approve_payroll_run' ),'permission_callback'=>array( $this,'permission_approve_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/payroll/runs/(?P<id>\d+)/payout-batch', array( 'methods'=>'POST','callback'=>array( $this,'create_payout_batch' ),'permission_callback'=>array( $this,'permission_approve_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/payout-batches/(?P<id>\d+)/reconcile', array( 'methods'=>'POST','callback'=>array( $this,'reconcile_payout_batch' ),'permission_callback'=>array( $this,'permission_approve_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/payroll/runs/(?P<id>\d+)/remittances', array( 'methods'=>'POST','callback'=>array( $this,'create_remittance' ),'permission_callback'=>array( $this,'permission_manage_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/remittances/(?P<id>\d+)/verify', array( 'methods'=>'POST','callback'=>array( $this,'verify_remittance' ),'permission_callback'=>array( $this,'permission_approve_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/exits/(?P<id>\d+)/payout', array( 'methods'=>'POST','callback'=>array( $this,'pay_exit_settlement' ),'permission_callback'=>array( $this,'permission_manage_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/payee-requests/(?P<id>\d+)/calculate', array( 'methods'=>'POST','callback'=>array( $this,'calculate_payee_request' ),'permission_callback'=>array( $this,'permission_manage_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/payee-requests/(?P<id>\d+)/verify-in-kind', array( 'methods'=>'POST','callback'=>array( $this,'verify_payee_in_kind' ),'permission_callback'=>array( $this,'permission_approve_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/payee-requests/(?P<id>\d+)/pay', array( 'methods'=>'POST','callback'=>array( $this,'pay_payee_request' ),'permission_callback'=>array( $this,'permission_manage_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/employments/(?P<id>\d+)/activate', array( 'methods'=>'POST','callback'=>array( $this,'activate_employment' ),'permission_callback'=>array( $this,'permission_approve_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/employments/(?P<id>\d+)/revoke-access', array( 'methods'=>'POST','callback'=>array( $this,'revoke_employment_access' ),'permission_callback'=>array( $this,'permission_approve_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/payout-items/(?P<id>\d+)/outcome', array( 'methods'=>'POST','callback'=>array( $this,'record_payout_outcome' ),'permission_callback'=>array( $this,'permission_manage_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/payroll/runs/(?P<id>\d+)/close', array( 'methods'=>'POST','callback'=>array( $this,'close_payroll_run' ),'permission_callback'=>array( $this,'permission_approve_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/legal-rules/(?P<id>\d+)/review', array( 'methods'=>'POST','callback'=>array( $this,'review_legal_rule' ),'permission_callback'=>array( $this,'permission_approve_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/payee-requests/(?P<id>\d+)/classify', array( 'methods'=>'POST','callback'=>array( $this,'classify_payee_request' ),'permission_callback'=>array( $this,'permission_approve_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/payee-requests/(?P<id>\d+)/approve', array( 'methods'=>'POST','callback'=>array( $this,'approve_payee_request' ),'permission_callback'=>array( $this,'permission_approve_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/exits', array( 'methods'=>'POST','callback'=>array( $this,'create_exit_settlement' ),'permission_callback'=>array( $this,'permission_manage_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/exits/(?P<id>\d+)/items', array( 'methods'=>'POST','callback'=>array( $this,'add_exit_item' ),'permission_callback'=>array( $this,'permission_manage_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/exits/(?P<id>\d+)/calculate', array( 'methods'=>'POST','callback'=>array( $this,'calculate_exit_settlement' ),'permission_callback'=>array( $this,'permission_manage_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/exits/(?P<id>\d+)/approve', array( 'methods'=>'POST','callback'=>array( $this,'approve_exit_settlement' ),'permission_callback'=>array( $this,'permission_approve_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/documents', array( array( 'methods'=>'GET','callback'=>array( $this,'list_documents' ),'permission_callback'=>array( $this,'permission_view_hr' ) ), array( 'methods'=>'POST','callback'=>array( $this,'upload_document' ),'permission_callback'=>array( $this,'permission_manage_hr' ) ) ) );
		register_rest_route( $ns, '/hr/replacement/documents/(?P<id>\d+)/review', array( 'methods'=>'POST','callback'=>array( $this,'review_document' ),'permission_callback'=>array( $this,'permission_approve_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/contracts/(?P<id>\d+)/sign', array( 'methods'=>'POST','callback'=>array( $this,'sign_contract' ),'permission_callback'=>array( $this,'permission_manage_hr' ) ) );
	}

	public function permission_view_hr() { return $this->view_hr() ? true : new WP_Error( 'hrx_forbidden','HR access required.',array('status'=>403) ); }
	public function permission_manage_hr() { return $this->manage_hr() ? true : new WP_Error( 'hrx_forbidden','HR management access required.',array('status'=>403) ); }
	public function permission_approve_hr() { return $this->approve_payroll() ? true : new WP_Error( 'hrx_forbidden','Independent Owner approval required.',array('status'=>403) ); }
	public function permission_view_payroll() { return $this->view_payroll() ? true : new WP_Error( 'hrx_forbidden','Payroll access required.',array('status'=>403) ); }
	public function permission_manage_payroll() { return $this->manage_payroll() ? true : new WP_Error( 'hrx_forbidden','Payroll Management access required.',array('status'=>403) ); }
	public function permission_schema_admin() { return $this->schema_admin() ? true : new WP_Error( 'hrx_forbidden','Schema administration access required.',array('status'=>403) ); }

	private function error( $code, $message, $status = 400 ) { return new WP_Error( $code, $message, array( 'status'=>(int) $status ) ); }
	private function body( $request ) { $v = $request->get_json_params(); return is_array( $v ) ? $v : array(); }
	private function table( $entity ) {
		if ( ! isset( self::ENTITY_MAP[ $entity ] ) ) { throw new InvalidArgumentException( 'Unknown HR replacement entity.' ); }
		return Stockland_HR_Replacement::table( self::ENTITY_MAP[ $entity ]['table'] );
	}
	private function has_schema() { return class_exists( 'Stockland_HR_Replacement' ) && Stockland_HR_Replacement::schema_status()['ready']; }
	private function require_schema() { return $this->has_schema() ? true : $this->error( 'hrx_schema_not_ready', 'HR replacement schema is not complete. Ask an authorized schema administrator to review the read-only status and create missing HRX tables in bounded batches.', 409 ); }
	/**
	 * Fail-closed location scope for the generic entity reader. Entity lists without a proven
	 * row-to-location relationship are company-scope only; UI filters are never authorization.
	 */
	private function apply_location_scope( $entity, $row = null, $requested_location = 0 ) {
		if ( Stockland_Authority::company_scope() ) { return true; }
		$location = (int) $requested_location;
		if ( $location > 0 && ! Stockland_Authority::can_access_location( $location ) ) {
			return $this->error( 'hrx_location_forbidden', 'Location access denied.', 403 );
		}
		if ( is_array( $row ) ) {
			if ( array_key_exists( 'location_id', $row ) && (int) $row['location_id'] > 0 ) {
				return Stockland_Authority::can_access_location( (int) $row['location_id'] )
					? true
					: $this->error( 'hrx_location_forbidden', 'The record is outside your authorized location scope.', 403 );
			}
			return $this->error( 'hrx_location_scope_unresolved', 'This record has no direct, verified location scope. A company-scope HR/payroll role is required until relation-aware scope is available.', 403 );
		}
		$direct = array( 'org-units', 'requisitions', 'time-events', 'assignments' );
		if ( ! in_array( $entity, $direct, true ) || $location < 1 ) {
			return $this->error( 'hrx_location_scope_required', 'Select an authorized location. This entity requires a verified relation-aware scope and is not exposed to branch-scoped listing yet.', 403 );
		}
		return true;
	}
	private function decode( $request, $key, $default = null ) { $v=$request->get_param( $key ); return null === $v ? $default : $v; }

	public function bootstrap() {
		$schema = Stockland_HR_Replacement::schema_status();
		$can_payroll = $this->view_payroll();
		return rest_ensure_response( array(
			'module'=>'HR_PAYROLL_REPLACEMENT','version'=>Stockland_HR_Replacement::SCHEMA_VERSION,
			'status'=>$schema['ready']?'SCHEMA_READY':'SCHEMA_NOT_INSTALLED','schema'=>$schema,
			'legacy_tables_read_only'=>array('employees','employee_compensation','payroll_periods','payroll_lines'),
			'legacy_reset_available'=>$this->legacy_reset_environment_allowed(),
			'capabilities'=>array('hr_view'=>$this->view_hr(),'hr_manage'=>$this->manage_hr(),'payroll_view'=>$can_payroll,'payroll_manage'=>$this->manage_payroll(),'owner_approve'=>$this->approve_payroll(),'schema_admin'=>$this->schema_admin()),
			'workflows'=>array('workforce','recruitment','contract_onboarding','time_leave','compensation','legal_rules','payroll','payout_settlement','statutory_remittance','individual_payees','exit_final_settlement','reporting_audit')
		) );
	}

	public function schema_batch( $request ) {
		$this->schema_gate();
		$batch = max(1,min(8,(int)$request->get_param('limit')));
		return rest_ensure_response( Stockland_HR_Replacement::install_schema_batch( $batch ) );
	}
	private function schema_gate() {
		// Schema creation is additive and separately gated from the ordinary HR UI.
		return true;
	}

	/** Destructive reset is disabled by default and impossible on production environment types. */
	private function legacy_reset_environment_allowed() {
		if ( ! defined( 'STOCKLAND_HRX_ALLOW_LEGACY_RESET' ) || true !== STOCKLAND_HRX_ALLOW_LEGACY_RESET ) { return false; }
		if ( ! function_exists( 'wp_get_environment_type' ) ) { return false; }
		return in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true );
	}

	/**
	 * Explicit reset limited to the four retired HR/payroll tables. Never called from activation or schema install.
	 * The backup and dependency-review hashes are operator-supplied evidence identifiers, not automatic backups.
	 */
	public function reset_legacy_tables( $request ) {
		global $wpdb;
		if ( ! $this->schema_admin() || ! $this->legacy_reset_environment_allowed() ) {
			return $this->error( 'hrx_legacy_reset_disabled', 'Legacy reset is disabled. It requires STOCKLAND_HRX_ALLOW_LEGACY_RESET=true and an explicitly configured local/development/staging environment; production is blocked.', 403 );
		}
		$body = $this->body( $request );
		$confirm = sanitize_text_field( (string) ( $body['confirmation'] ?? '' ) );
		$backup = strtolower( sanitize_text_field( (string) ( $body['backup_sha256'] ?? '' ) ) );
		$review = strtolower( sanitize_text_field( (string) ( $body['dependency_review_sha256'] ?? '' ) ) );
		$reason = sanitize_textarea_field( (string) ( $body['reason'] ?? '' ) );
		if ( 'RESET_LEGACY_HR_PAYROLL_AFTER_BACKUP' !== $confirm || ! preg_match( '/^[a-f0-9]{64}$/', $backup ) || ! preg_match( '/^[a-f0-9]{64}$/', $review ) || strlen( trim( $reason ) ) < 60 ) {
			return $this->error( 'hrx_legacy_reset_evidence_required', 'Exact confirmation, 64-character verified backup SHA-256, 64-character dependency-review SHA-256 and a 60-character rationale are required.', 422 );
		}
		$allow = array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' );
		$tables = array();
		foreach ( $allow as $key ) {
			$table = Stockland_DB::table( $key );
			if ( ! preg_match( '/^[A-Za-z0-9_]+$/D', $table ) ) { return $this->error( 'hrx_legacy_reset_identifier_invalid', 'Legacy table identifier failed the allow-list check.', 500 ); }
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( $found && $found !== $table ) { return $this->error( 'hrx_legacy_reset_preflight_failed', 'A table-name match was ambiguous; no legacy table was dropped.', 409 ); }
			$tables[] = array( 'key' => $key, 'name' => $table, 'exists' => $found === $table );
		}
		$preflight_hash = hash( 'sha256', wp_json_encode( $tables ) );
		$pre_event = Stockland_HR_Replacement::audit( 'legacy_reset_preflight', 'legacy-hr-payroll', 0, 'Only four retired HR/payroll tables were allow-listed. Backup SHA256 ' . $backup . '; dependency review SHA256 ' . $review . '; preflight ' . $preflight_hash . '.' );
		if ( false === $pre_event ) { return $this->error( 'hrx_legacy_reset_audit_failed', 'Could not record reset preflight. No table was dropped.', 500 ); }
		$dropped = array();
		foreach ( $tables as $table ) {
			if ( ! $table['exists'] ) { continue; }
			$sql = 'DROP TABLE `' . $table['name'] . '`';
			$ok = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is from the fixed allow-list and validated above.
			if ( false === $ok ) {
				Stockland_HR_Replacement::audit( 'legacy_reset_partial_failure', 'legacy-hr-payroll', 0, 'Reset stopped at ' . $table['key'] . '. Previously dropped tables may need restoration from the verified backup.' );
				return $this->error( 'hrx_legacy_reset_partial_failure', 'Reset stopped on a table error; the operation is not transactional. Restore from backup and inspect the audit log.', 500 );
			}
			$dropped[] = $table['key'];
		}
		$audit = Stockland_HR_Replacement::audit( 'legacy_reset_completed', 'legacy-hr-payroll', 0, 'Dropped only allow-listed legacy tables: ' . implode( ',', $dropped ) . '; backup ' . $backup . '; dependency review ' . $review . '; reason ' . $reason );
		if ( false === $audit ) { return $this->error( 'hrx_legacy_reset_audit_failed_after_drop', 'Allow-listed reset ran but final audit failed. Stop and verify database state against the backup.', 500 ); }
		return rest_ensure_response( array( 'reset_completed' => true, 'dropped_tables' => $dropped, 'backup_sha256' => $backup, 'dependency_review_sha256' => $review, 'message' => 'The explicitly allow-listed legacy HR/payroll tables were removed in a non-production environment. Shared ERP tables and HRX tables were not changed.' ) );
	}

	private function sanitize_value( $key, $value ) {
		if ( is_array( $value ) || is_object( $value ) ) { return wp_json_encode( $value ); }
		if ( in_array( $key, array('base_salary','amount','budget_amount','band_min','band_max','accrual_amount','annual_limit','carry_forward_limit','quantity_days','gross_total','taxable_total','paye_total','employee_pension_total','employer_pension_total','deduction_total','net_total','employer_cost_total','cash_amount','in_kind_value','gross_amount','withholding_amount','net_amount'), true ) ) {
			try { return Stockland_Money::decimal( Stockland_Money::minor( $value ) ); } catch ( Throwable $e ) { return null; }
		}
		if ( preg_match('/(_date|^date$|_at)$/',$key) && '' !== (string)$value ) { $valid=Stockland_HR_Replacement::valid_date(substr((string)$value,0,10)); if(false===$valid)return null; if(strpos($key,'_at')!==false)return sanitize_text_field((string)$value); return $valid; }
		if ( is_bool( $value ) || in_array( $key, array('active','cash_payable'), true ) ) { return empty($value)?0:1; }
		if ( in_array( $key, array('formula_parameters','applicability','exceptions','snapshot_json','monday_json','tuesday_json','wednesday_json','thursday_json','friday_json','saturday_json','sunday_json'), true ) && is_string($value) ) {
			$decoded=json_decode($value,true); if(null===$decoded && 'null'!==$value)return null; return wp_json_encode($decoded);
		}
		return sanitize_textarea_field( (string) $value );
	}

	private function allowed_fields( $entity ) {
		$map = array(
			'people'=>array('person_code','first_name','middle_name','last_name','preferred_name','phone','email','privacy_state','source_record'),
			'org-units'=>array('unit_code','unit_name','unit_type','parent_id','location_id','manager_employment_id','active','effective_from','effective_to'),
			'positions'=>array('position_code','title','org_unit_id','grade_code','band_min','band_max','openings_planned','status','effective_from','effective_to'),
			'workforce-plans'=>array('plan_code','fiscal_year','org_unit_id','position_id','headcount_requested','budget_amount','justification','status'),
			'requisitions'=>array('requisition_code','workforce_plan_id','position_id','location_id','openings','requested_start','budget_amount','business_reason','status'),
			'applications'=>array('application_code','requisition_id','person_id','privacy_notice_version','consent_or_lawful_basis','applied_at','stage','screening_score','retention_until','status'),
			'interviews'=>array('application_id','interview_round','interview_at','interviewer_user_id','rubric_version','score','recommendation','evidence_ref','notes_redacted'),
			'offers'=>array('offer_code','application_id','employment_type','proposed_start','proposed_end','position_id','location_id','compensation_assignment_id','terms_version','contract_ref_id','status'),
			'contracts'=>array('employment_id','contract_code','contract_version','contract_type','start_date','end_date','probation_end','terms_hash','private_document_ref_id','signed_by_person_at','status'),
			'onboarding-tasks'=>array('employment_id','task_code','task_title','owner_role','due_date','evidence_ref_id','status'),
			'assignments'=>array('employment_id','position_id','org_unit_id','location_id','manager_employment_id','effective_from','effective_to','reason_code','approval_status'),
			'schedules'=>array('schedule_code','employment_id','effective_from','effective_to','timezone','monday_json','tuesday_json','wednesday_json','thursday_json','friday_json','saturday_json','sunday_json','status'),
			'time-events'=>array('event_code','employment_id','location_id','event_type','event_date','time_in','time_out','ordinary_minutes','overtime_minutes','night_minutes','rest_day_minutes','holiday_minutes','source_type','source_reference','correction_of_id','correction_reason','status'),
			'leave-policies'=>array('policy_code','leave_type','policy_version','effective_from','effective_to','accrual_method','accrual_amount','annual_limit','carry_forward_limit','paid_status','legal_rule_id','approval_status'),
			'leave-transactions'=>array('transaction_code','employment_id','leave_policy_id','transaction_type','start_date','end_date','quantity_days','source_transaction_id','reason','evidence_ref_id','status'),
			'compensation-assignments'=>array('assignment_code','employment_id','currency','base_salary','effective_from','effective_to','reason_code','reason_text','source_type','source_reference'),
			'compensation-items'=>array('compensation_assignment_id','component_code','component_name','component_type','amount','frequency','cash_payable','taxable_status','pensionable_status','legal_rule_id','evidence_ref_id'),
			'legal-rules'=>array('rule_code','rule_family','jurisdiction','source_title','source_article','source_url','source_sha256','publication_date','effective_from','effective_to','formula_key','formula_parameters','applicability','exceptions','review_status','test_vectors_sha256'),
			'accounting-config'=>array('config_code','wage_expense_account','employer_pension_expense_account','net_payable_account','paye_payable_account','employee_pension_payable_account','employer_pension_payable_account','other_deductions_payable_account','cash_account_code','payee_expense_account','payee_payable_account','payee_withholding_account','effective_from','effective_to'),
			'legal-rule-bands'=>array('legal_rule_id','band_order','lower_bound','upper_bound','rate','constant_credit','calculation_method'),
			'payee-engagements'=>array('engagement_code','payee_person_id','engagement_type','campaign_or_service','start_date','end_date','contract_ref_id','deliverable_terms','status'),
			'payee-requests'=>array('request_code','engagement_id','request_type','service_period_start','service_period_end','cash_amount','in_kind_value','currency','invoice_or_receipt_ref'),
			'payee-deliverables'=>array('payee_request_id','deliverable_code','deliverable_type','evidence_ref_id','completed_at','accepted_by','accepted_at','status','notes_redacted'),
			'payee-classifications'=>array('payee_request_id','rule_id','classification','withholding_required','withholding_rate','reason','evidence_ref_id'),
			'social-snapshots'=>array('snapshot_code','platform','account_label','campaign_code','reporting_period_start','reporting_period_end','as_of_at','views','reach','engagements','link_clicks','attributable_sales','campaign_fee','gifted_stock_value','source_reference','freshness_status')
		);
		return $map[$entity]??array();
	}

	private function entity_row( $entity, $id ) {
		global $wpdb;
		if ( ! isset(self::ENTITY_MAP[$entity]) ) return $this->error('hrx_unknown_entity','Unknown HR replacement entity.',404);
		if(in_array($entity,array('payroll-runs','payroll-lines','payout-batches','payout-items','statutory-obligations','remittances','payee-payments','payee-payment-attempts','exit-payouts'),true)&&!$this->view_payroll())return $this->error('hrx_payroll_forbidden','This entity requires payroll access.',403);
		$table=$this->table($entity);
		$row=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d LIMIT 1',$table,(int)$id),ARRAY_A);
		if($wpdb->last_error)return $this->error('hrx_read_failed','Could not read HR record.',500);
		if(!$row)return $this->error('hrx_not_found','HR record not found.',404);
		$scope=$this->apply_location_scope($entity,$row,0);if(is_wp_error($scope))return $scope;
		unset($row['private_storage_key']);
		if(!$this->view_payroll()) foreach(array('base_salary','band_min','band_max','budget_amount','gross_total','taxable_total','paye_total','employee_pension_total','employer_pension_total','deduction_total','net_total','employer_cost_total','cash_amount','in_kind_value','gross_amount','withholding_amount','net_amount','amount') as $sensitive){unset($row[$sensitive]);}
		return $row;
	}

	public function list_entity( $request ) {
		global $wpdb;
		$ready=$this->require_schema(); if(is_wp_error($ready))return $ready;
		$entity=sanitize_key($request['entity']);
		if(!isset(self::ENTITY_MAP[$entity]))return $this->error('hrx_unknown_entity','Unknown HR replacement entity.',404);
		if(in_array($entity,array('payroll-runs','payroll-lines','payout-batches','payout-items','statutory-obligations','remittances','payee-payments','payee-payment-attempts','exit-payouts'),true)&&!$this->view_payroll())return $this->error('hrx_payroll_forbidden','This entity requires payroll access.',403);
		$limit=max(1,min(100,(int)$request->get_param('limit')?:50));$offset=max(0,min(50000,(int)$request->get_param('offset')));
		$table=$this->table($entity);
		$where='1=1';$args=array();
		$location_id=(int)$request->get_param('location_id');
		$scope=$this->apply_location_scope($entity,null,$location_id);
		if(is_wp_error($scope))return $scope;
		if(!Stockland_Authority::company_scope()){
			$where.=' AND location_id=%d';$args[]=$location_id;
		}
		$sql='SELECT * FROM %i WHERE '.$where.' ORDER BY id DESC LIMIT %d OFFSET %d';$args=array_merge(array($table),$args,array($limit,$offset));
		$rows=Stockland_DB::read_results($wpdb->prepare($sql,$args),ARRAY_A);
		if($wpdb->last_error)return $this->error('hrx_list_failed','Could not list HR records.',500);
		foreach($rows as &$row){
			unset($row['private_storage_key']);
			if(!$this->view_payroll())foreach(array('base_salary','band_min','band_max','budget_amount','gross_total','taxable_total','paye_total','employee_pension_total','employer_pension_total','deduction_total','net_total','employer_cost_total','cash_amount','in_kind_value','gross_amount','withholding_amount','net_amount','amount') as $sensitive){unset($row[$sensitive]);}
		}
		return rest_ensure_response(array('entity'=>$entity,'records'=>$rows,'limit'=>$limit,'offset'=>$offset,'count'=>count($rows)));
	}

	public function get_entity( $request ) {
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$row=$this->entity_row(sanitize_key($request['entity']),(int)$request['id']);
		return is_wp_error($row)?$row:rest_ensure_response($row);
	}

	public function create_entity( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$entity=sanitize_key($request['entity']);$map=self::ENTITY_MAP[$entity]??null;
		if(!$map||empty($map['create']))return $this->error('hrx_create_forbidden','This entity must be created through its controlled workflow.',403);
		if(in_array($entity,array('legal-rules','legal-rule-bands','leave-policies','compensation-assignments','compensation-items','payee-requests','payee-classifications','accounting-config'),true)&&!$this->manage_hr())return $this->error('hrx_sensitive_write_forbidden','Sensitive HR/payroll writes require HR Management.',403);
		$payload=$this->body($request);$allowed=$this->allowed_fields($entity);$row=array();
		foreach($payload as $key=>$value){
			if(!in_array($key,$allowed,true))continue;
			$clean=$this->sanitize_value($key,$value);
			if(null===$clean)return $this->error('hrx_field_invalid','Invalid field value: '.$key,422);
			$row[$key]=$clean;
		}
		if(empty($row))return $this->error('hrx_payload_empty','No allowed fields supplied.',422);
		$scope=$this->apply_location_scope($entity,null,(int)($row['location_id']??0));
		if(is_wp_error($scope))return $scope;
		if(isset($row['location_id'])&&!empty($row['location_id'])&&!Stockland_Authority::can_access_location((int)$row['location_id']))return $this->error('hrx_location_forbidden','This record is outside your authorized location scope.',403);
		if(in_array($entity,array('legal-rules','leave-policies'),true))$row[$entity==='legal-rules'?'review_status':'approval_status']='LEGAL_REVIEW';
		if('accounting-config'===$entity)$row['status']='DRAFT';
		if(in_array($entity,array('compensation-assignments','compensation-items'),true))$row['status']='DRAFT';
		if('payee-requests'===$entity){$row['status']='DRAFT';$row['classification_status']='LEGAL_REVIEW';}
		if(isset($map['state'])&&'active'!==$map['state']&&isset($row[$map['state']]))$row[$map['state']]='DRAFT';
		if('legal-rules'===$entity)$row['review_status']='LEGAL_REVIEW';
		if('leave-policies'===$entity)$row['approval_status']='LEGAL_REVIEW';
		if('people'===$entity)$row['privacy_state']='ACTIVE';
		if('interviews'===$entity)$row['recommendation']='PENDING';
		if('payee-classifications'===$entity){$row['status']='LEGAL_REVIEW';}
		if('applications'===$entity){$row['stage']='RECEIVED';$row['status']='OPEN';}
		if('assignments'===$entity){$row['approval_status']='DRAFT';}
		if('social-snapshots'===$entity){$row['freshness_status']='CURRENT';}
		if('social-snapshots'===$entity){$row['entered_by']=get_current_user_id();}
		elseif('accounting-config'===$entity){$row['prepared_by']=get_current_user_id();}
		elseif(!in_array($entity,array('interviews','legal-rule-bands'),true)){$row['created_by']=get_current_user_id();}
		$row['created_at']=Stockland_HR_Replacement::now();
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start HR transaction.',500);
		$ok=Stockland_DB::insert_record($this->table($entity),$row);
		if(false===$ok){$this->rollback();return $this->error('hrx_insert_failed','Could not create HR record. Check required fields and uniqueness.',409);}
		$id=(int)$wpdb->insert_id;
		$event=Stockland_HR_Replacement::approval_event($entity,$id,'CREATED',null,$row[$map['state']]??'DRAFT','Record created',wp_generate_uuid4());
		$audit=Stockland_HR_Replacement::audit('created',$entity,$id,'HRX record created',isset($row['location_id'])?(int)$row['location_id']:null);
		if(is_wp_error($event)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_audit_failed','Creation was rolled back because required audit evidence failed.',500);}
		$this->commit();
		$record=$this->entity_row($entity,$id);return rest_ensure_response(array('id'=>$id,'record'=>$record));
	}

	public function update_entity( $request ) {
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$entity=sanitize_key($request['entity']);$map=self::ENTITY_MAP[$entity]??null;
		if(!$map||empty($map['update']))return $this->error('hrx_update_forbidden','This entity is immutable or must be changed through a controlled workflow.',403);
		$current=$this->entity_row($entity,(int)$request['id']);if(is_wp_error($current))return $current;
		$editable_states=array('DRAFT','OPEN','RECEIVED','PENDING','LEGAL_REVIEW');
		if('people'===$entity)$editable_states[]='ACTIVE';
		if('org-units'===$entity)$editable_states=array_merge($editable_states,array('0','1'));
		if(isset($map['state'])&&isset($current[$map['state']])&&!in_array(strtoupper((string)$current[$map['state']]),$editable_states,true))return $this->error('hrx_record_locked','Only editable draft/review records and explicitly mutable people/org-unit records may be changed.',409);
		$payload=$this->body($request);$allowed=$this->allowed_fields($entity);$data=array();
		foreach($payload as $key=>$value){
			if(!in_array($key,$allowed,true))continue;
			$clean=$this->sanitize_value($key,$value);if(null===$clean)return $this->error('hrx_field_invalid','Invalid field value: '.$key,422);$data[$key]=$clean;
		}
		if(empty($data))return $this->error('hrx_payload_empty','No mutable fields supplied.',422);
		unset($data['status'],$data['review_status'],$data['approval_status'],$data['stage'],$data['recommendation']);
		if(!empty($map['state']))unset($data[$map['state']]);
		if(!$data)return $this->error('hrx_transition_required','Workflow state changes require the transition endpoint.',422);
		// Apply metadata only when the installed table contract contains those columns.
		$update_where=array('id'=>(int)$request['id']);
		foreach($data as $field=>$value){if(array_key_exists($field,$current))$update_where[$field]=$current[$field];}
		if(isset($current[$map['state']]))$update_where[$map['state']]=$current[$map['state']];
		if(array_key_exists('version_no',$current)){$update_where['version_no']=(int)$current['version_no'];$data['version_no']=(int)$current['version_no']+1;}
		if(array_key_exists('updated_at',$current)){$update_where['updated_at']=$current['updated_at'];$data['updated_at']=Stockland_HR_Replacement::now();}
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start HR transaction.',500);
		$ok=Stockland_DB::update_record($this->table($entity),$data,$update_where);
		if(false===$ok||0===(int)$ok){$this->rollback();return $this->error('hrx_update_conflict','The record changed or no row was updated. Refresh it before retrying; no approval or audit event was written.',409);}
		$event=Stockland_HR_Replacement::approval_event($entity,(int)$request['id'],'UPDATED',$current[$map['state']]??null,$current[$map['state']]??'DRAFT','Mutable draft fields updated',wp_generate_uuid4());
		$audit=Stockland_HR_Replacement::audit('updated',$entity,(int)$request['id'],'HRX draft updated',isset($current['location_id'])?(int)$current['location_id']:null);
		if(is_wp_error($event)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_update_failed','Update/audit failed and was rolled back.',500);}
		$this->commit();return rest_ensure_response($this->entity_row($entity,(int)$request['id']));
	}

	private function transitions( $entity ) {
		$rules=array(
			'org-units'=>array('ACTIVATE'=>array('0','1'),'DEACTIVATE'=>array('1','0')),
			'positions'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'workforce-plans'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'requisitions'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED'),'OPEN'=>array('APPROVED','OPEN'),'CLOSE'=>array('OPEN','CLOSED')),
			'applications'=>array('SCREEN'=>array('RECEIVED','SCREENING'),'SHORTLIST'=>array('SCREENING','SHORTLISTED'),'REJECT'=>array('RECEIVED','REJECTED'),'WITHDRAW'=>array('RECEIVED','WITHDRAWN'),'SELECT'=>array('SHORTLISTED','SELECTED')),
			'interviews'=>array('RECOMMEND'=>array('PENDING','RECOMMENDED'),'REJECT'=>array('PENDING','NOT_RECOMMENDED')),
			'offers'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED'),'ACCEPT'=>array('APPROVED','ACCEPTED'),'DECLINE'=>array('APPROVED','DECLINED')),
			'contracts'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'onboarding-tasks'=>array('COMPLETE'=>array('OPEN','COMPLETE'),'REOPEN'=>array('COMPLETE','OPEN')),
			'exit-clearance-tasks'=>array('COMPLETE'=>array('OPEN','COMPLETE'),'REOPEN'=>array('COMPLETE','OPEN')),
			'assignments'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'schedules'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'time-events'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'leave-policies'=>array('SUBMIT'=>array('LEGAL_REVIEW','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','LEGAL_REVIEW')),
			'leave-transactions'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED'),'CANCEL'=>array('APPROVED','CANCELLED')),
			'compensation-assignments'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'compensation-items'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'legal-rules'=>array('SUBMIT_LEGAL_REVIEW'=>array('DRAFT','LEGAL_REVIEW'),'APPROVE'=>array('LEGAL_REVIEW','APPROVED'),'RETIRE'=>array('APPROVED','RETIRED')),
			'accounting-config'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'payee-engagements'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'payee-requests'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'HOLD'=>array('SUBMITTED','LEGAL_TAX_REVIEW'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'payee-deliverables'=>array('SUBMIT'=>array('PENDING','SUBMITTED'),'ACCEPT'=>array('SUBMITTED','ACCEPTED'),'REJECT'=>array('SUBMITTED','REJECTED'))
		);
		return $rules[$entity]??array();
	}

	public function transition_entity( $request ) {
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$entity=sanitize_key($request['entity']);$id=(int)$request['id'];$body=$this->body($request);$action=strtoupper(sanitize_key($body['action']??''));$reason=sanitize_textarea_field((string)($body['reason']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));
		if(!$key)return $this->error('hrx_idempotency_required','A client idempotency_key is required for a workflow transition.',422);
		$map=self::ENTITY_MAP[$entity]??null;if(!$map||empty($map['state']))return $this->error('hrx_transition_forbidden','This entity has no direct state transition.',403);
		$rule=$this->transitions($entity)[$action]??null;if(!$rule)return $this->error('hrx_transition_invalid','Unknown action for this workflow.',422);
		$current=$this->entity_row($entity,$id);if(is_wp_error($current))return $current;
		$state_field=$map['state'];$from=strtoupper((string)($current[$state_field]??''));if($from!==strtoupper((string)$rule[0]))return $this->error('hrx_transition_conflict','This action is not allowed from the current status.',409);
		$is_approval=in_array($action,array('APPROVE','ACCEPT','ACTIVATE','SELECT','CLOSE','RETIRE'),true);
		$owner_required=$is_approval&&!in_array($entity,array('time-events','leave-transactions','onboarding-tasks','interviews','applications'),true);
		if($owner_required&&!$this->approve_payroll())return $this->error('hrx_owner_required','Owner approval is required for this transition.',403);
		if($is_approval&&isset($current['created_by'])&&(int)$current['created_by']===get_current_user_id())return $this->error('hrx_no_self_approval','The preparer cannot approve their own HR/payroll record.',403);
		
		if('accounting-config'===$entity&&'APPROVE'===$action){
			$accounts=array('wage_expense_account','employer_pension_expense_account','net_payable_account','paye_payable_account','employee_pension_payable_account','employer_pension_payable_account','other_deductions_payable_account','cash_account_code','payee_expense_account','payee_payable_account','payee_withholding_account');
			foreach($accounts as $account){if(empty($current[$account])||!Stockland_Accounting::account_exists($current[$account]))return $this->error('hrx_accounting_config_account_invalid','Every configured payroll/AP account must exist and be active in the Stockland General Ledger.',422);}
			$from=Stockland_HR_Replacement::valid_date($current['effective_from']??'');$to=!empty($current['effective_to'])?Stockland_HR_Replacement::valid_date($current['effective_to']):null;
			if(!$from||($to&&$to<$from))return $this->error('hrx_accounting_config_dates_invalid','Accounting mapping requires valid effective dates.',422);
			global $wpdb;$overlap=Stockland_DB::read_row($wpdb->prepare("SELECT id FROM %i WHERE status='APPROVED' AND id<>%d AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) LIMIT 1",Stockland_HR_Replacement::table('hrx_accounting_config'),(int)$request['id'],$to?:'9999-12-31',$from),ARRAY_A);
			if($overlap)return $this->error('hrx_accounting_config_overlap','An approved accounting mapping already overlaps the requested effective period.',409);
		}
		if(in_array($entity,array('legal-rules','leave-policies'),true)&&'APPROVE'===$action){
			$extra=$body['review_attestation']??'';if(strlen(trim((string)$extra))<30)return $this->error('hrx_legal_review_required','Qualified review attestation and official-source effective-date confirmation are required.',422);
			if('legal-rules'===$entity){
				$evidence_id=(int)($current['review_evidence_ref_id']??0);$evidence=$evidence_id?Stockland_DB::read_row($wpdb->prepare("SELECT id FROM %i WHERE id=%d AND entity_type='LEGAL_RULE' AND entity_id=%d AND document_type='LEGAL_REVIEW_OPINION' AND approval_status='APPROVED'",Stockland_HR_Replacement::table('hrx_document_refs'),$evidence_id,(int)$request['id']),ARRAY_A):null;
				if(empty($current['source_sha256'])||!preg_match('/^[a-f0-9]{64}$/i',(string)$current['source_sha256'])||empty($current['test_vectors_sha256'])||!preg_match('/^[a-f0-9]{64}$/i',(string)$current['test_vectors_sha256'])||empty($current['reviewer_qualification'])||!$evidence)return $this->error('hrx_legal_evidence_missing','Legal rule requires original-source hash, test-vector hash, reviewer qualification and an independently approved private legal opinion/review reference before activation.',422);
			}
		}
		if(in_array($entity,array('contracts'),true)&&'APPROVE'===$action&&empty($current['signed_by_person_at']))return $this->error('hrx_signed_contract_required','A signed contract record is required before approval.',409);
		if('compensation-assignments'===$entity&&'APPROVE'===$action){
			global $wpdb;$items=Stockland_DB::read_results($wpdb->prepare('SELECT taxable_status,pensionable_status,amount FROM %i WHERE compensation_assignment_id=%d',Stockland_HR_Replacement::table('hrx_compensation_items'),$id),ARRAY_A);
			foreach((array)$items as $item){if(!in_array($item['taxable_status'],array('TAXABLE','EXEMPT'),true)||!in_array($item['pensionable_status'],array('PENSIONABLE','NON_PENSIONABLE'),true))return $this->error('hrx_compensation_classification','Compensation component classification remains LEGAL_REVIEW and blocks approval.',409);}
		}
		$to=$rule[1];$extra=array();
		if(array_key_exists('updated_at',$current))$extra['updated_at']=Stockland_HR_Replacement::now();
		if(array_key_exists('version_no',$current))$extra['version_no']=(int)$current['version_no']+1;
		if('org-units'===$entity)$extra['active']='ACTIVATE'===$action?1:0;
		elseif('interviews'===$entity)$extra[$state_field]=$to;
		else $extra[$state_field]=$to;
		if($is_approval){
			if('leave-transactions'===$entity){$extra['decided_by']=get_current_user_id();$extra['decided_at']=Stockland_HR_Replacement::now();}
			elseif('payee-deliverables'===$entity){$extra['accepted_by']=get_current_user_id();$extra['accepted_at']=Stockland_HR_Replacement::now();}
			else{
				if(array_key_exists('approved_by',$current))$extra['approved_by']=get_current_user_id();
				if(array_key_exists('approved_at',$current))$extra['approved_at']=Stockland_HR_Replacement::now();
			}
		}
		if('offers'===$entity&&'ACCEPT'===$action){$evidence=sanitize_textarea_field((string)($body['acceptance_evidence']??''));if(strlen(trim($evidence))<15)return $this->error('hrx_offer_acceptance_evidence_required','Record the applicant offer-acceptance evidence reference or verified note.',422);$extra['accepted_at']=Stockland_HR_Replacement::now();}
		if('applications'===$entity&&'SELECT'===$action){$selection=sanitize_textarea_field((string)($body['selection_rationale']??''));if(strlen(trim($selection))<20)return $this->error('hrx_selection_rationale_required','Document the human selection rationale (minimum 20 characters).',422);$extra['selection_rationale']=$selection;}
		if('applications'===$entity&&'REJECT'===$action){$extra['status']='REJECTED';$extra['retention_until']=Stockland_HR_Replacement::valid_date($body['retention_until']??'')?:null;}
		if('exit-clearance-tasks'===$entity&&'COMPLETE'===$action){
			$note=sanitize_textarea_field((string)($body['resolution_note']??$reason));
			if(strlen(trim($note))<20)return $this->error('hrx_exit_clearance_evidence_required','Clearance completion needs a short evidence/resolution note.',422);
			if('ACCESS_REVOKED'===$current['task_code']){
				global $wpdb;$emp=Stockland_DB::read_row($wpdb->prepare('SELECT access_revoked_at FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_employments'),(int)$current['employment_id']),ARRAY_A);
				if(!$emp||empty($emp['access_revoked_at']))return $this->error('hrx_access_not_revoked','Revoke the linked Stockland ERP profile and location scope before completing the access-clearance task.',409);
			} else {
				$evidence_id=(int)($body['evidence_ref_id']??0);
				$doc=$evidence_id?Stockland_DB::read_row($wpdb->prepare("SELECT id FROM %i WHERE id=%d AND entity_type='EXIT_SETTLEMENT' AND entity_id=%d AND document_type='EXIT_CLEARANCE' AND approval_status='APPROVED'",Stockland_HR_Replacement::table('hrx_document_refs'),$evidence_id,(int)$current['settlement_id']),ARRAY_A):null;
				if(!$doc)return $this->error('hrx_exit_clearance_document_missing','Clearance requires an independently approved private EXIT_CLEARANCE document linked to this settlement.',409);
				$extra['evidence_ref_id']=$evidence_id;
			}
			$extra['completed_by']=get_current_user_id();$extra['completed_at']=Stockland_HR_Replacement::now();$extra['resolution_note']=$note;
		}
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start HR transaction.',500);
		$update_where=array('id'=>$id,$state_field=>$current[$state_field]);
		if(array_key_exists('version_no',$current))$update_where['version_no']=(int)$current['version_no'];
		$ok=Stockland_DB::update_record($this->table($entity),$extra,$update_where);
		if(false===$ok||0===(int)$ok){$this->rollback();return $this->error('hrx_transition_conflict','The record changed before the transition could be applied. Refresh it; no approval or audit event was written.',409);}
		$evt=Stockland_HR_Replacement::approval_event($entity,$id,$action,$from,$to,$reason,$key,isset($body['evidence_ref_id'])?(int)$body['evidence_ref_id']:null,$is_approval?get_current_user_id():null);
		$audit=Stockland_HR_Replacement::audit('transition_'.$action,$entity,$id,$reason,isset($current['location_id'])?(int)$current['location_id']:null,$is_approval?get_current_user_id():null);
		if(is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_transition_audit_failed','Workflow was rolled back because audit evidence could not be written.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'from'=>$from,'to'=>$to,'action'=>$action,'message'=>'Workflow state and audit evidence saved.'));
	}

	public function create_employment_from_offer( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$id=(int)$request['id'];
		if($id<1)return $this->error('hrx_offer_not_found','Offer not found.',404);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start HR transaction.',500);
		$offer=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_HR_Replacement::table('hrx_offers'),$id),ARRAY_A);
		if(!$offer){$this->rollback();return $this->error('hrx_offer_not_found','Offer not found.',404);}
		$existing=Stockland_DB::read_row($wpdb->prepare('SELECT e.id,e.employment_code,e.status,c.id contract_id FROM %i e LEFT JOIN %i c ON c.employment_id=e.id WHERE e.offer_id=%d ORDER BY c.contract_version DESC LIMIT 1 FOR UPDATE',Stockland_HR_Replacement::table('hrx_employments'),Stockland_HR_Replacement::table('hrx_contracts'),$id),ARRAY_A);
		if($existing){
			$this->commit();
			return rest_ensure_response(array('employment_id'=>(int)$existing['id'],'employment_code'=>$existing['employment_code'],'status'=>$existing['status'],'contract_id'=>(int)($existing['contract_id']??0),'idempotent_replay'=>true,'message'=>'This accepted offer already has its pending employment record; no duplicate contract or onboarding tasks were created.'));
		}
		if('ACCEPTED'!==strtoupper((string)$offer['status'])){$this->rollback();return $this->error('hrx_offer_not_accepted','Only an approved and accepted offer may create a pending employment relationship.',409);}
		$app=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_HR_Replacement::table('hrx_applications'),(int)$offer['application_id']),ARRAY_A);
		if(!$app||'OPEN'!==strtoupper((string)$app['status'])||'SELECTED'!==strtoupper((string)$app['stage'])||empty($app['selection_rationale'])||strlen(trim((string)$app['selection_rationale']))<20){$this->rollback();return $this->error('hrx_human_selection_missing','A documented human selection decision and open approved application are required.',409);}
		$person=(int)$app['person_id'];$employment_code='EMPX-'.gmdate('Ymd').'-'.wp_generate_password(8,false,false);
		$row=array('employment_code'=>$employment_code,'person_id'=>$person,'offer_id'=>$id,'legal_employer_code'=>sanitize_key((string)($request->get_param('legal_employer_code')?:'STOCKLAND_ET_PLC')),'employment_type'=>sanitize_key((string)$offer['employment_type'],'full_time'),'start_date'=>$offer['proposed_start'],'end_date'=>$offer['proposed_end'],'status'=>'PENDING_ACTIVATION','created_by'=>get_current_user_id(),'created_at'=>Stockland_HR_Replacement::now(),'version_no'=>1);
		$ok=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_employments'),$row);
		if(false===$ok){$this->rollback();return $this->error('hrx_employment_insert_failed','Could not create pending employment. Recheck the accepted offer and existing employment link.',409);}
		$eid=(int)$wpdb->insert_id;
		$contract=array('employment_id'=>$eid,'contract_code'=>'CTX-'.wp_generate_password(12,false,false),'contract_version'=>1,'contract_type'=>$row['employment_type'],'start_date'=>$row['start_date'],'end_date'=>$row['end_date'],'probation_end'=>null,'terms_hash'=>hash('sha256',(string)$offer['terms_version']),'status'=>'DRAFT','created_by'=>get_current_user_id(),'created_at'=>Stockland_HR_Replacement::now());
		$ok2=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_contracts'),$contract);
		$contract_id=(int)$wpdb->insert_id;
		$tasks=array(
			array('CONTRACT_SIGNED','Sign and verify the employment contract','HR'),
			array('PERSONNEL_DOCS','Verify required personnel documents','HR'),
			array('COMPENSATION_SETUP','Confirm approved effective-dated compensation','FINANCE'),
			array('INDUCTION','Complete role induction and SOP acknowledgement','MANAGEMENT')
		);
		$task_failed=false;foreach($tasks as $task){$taskrow=array('employment_id'=>$eid,'task_code'=>$task[0],'task_title'=>$task[1],'owner_role'=>$task[2],'status'=>'OPEN','created_by'=>get_current_user_id(),'created_at'=>Stockland_HR_Replacement::now());if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_onboarding_tasks'),$taskrow)){$task_failed=true;break;}}
		$evt=Stockland_HR_Replacement::approval_event('employment',$eid,'PENDING_HIRE_CREATED',null,'PENDING_ACTIVATION','From accepted offer',$employment_code);
		$audit=Stockland_HR_Replacement::audit('pending_hire_created','employment',$eid,'From accepted offer');
		if(false===$ok2||$task_failed||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_hire_transaction_failed','Hire creation was rolled back because contract, onboarding or audit did not complete.',500);}
		$this->commit();return rest_ensure_response(array('employment_id'=>$eid,'employment_code'=>$employment_code,'status'=>'PENDING_ACTIVATION','contract_id'=>$contract_id,'idempotent_replay'=>false,'message'=>'Pending hire created; signed contract, verified documents, onboarding and approved compensation must be completed before activation.'));
	}

	public function create_payroll_run( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$body=$this->body($request);$start=Stockland_HR_Replacement::valid_date($body['period_start']??'');$end=Stockland_HR_Replacement::valid_date($body['period_end']??'');
		if(!$start||!$end||substr($start,0,7)!==substr($end,0,7)||$start>$end||substr($start,8,2)!=='01')return $this->error('hrx_period_invalid','Payroll requires a valid complete calendar month.',422);
		$lastday=gmdate('Y-m-t',strtotime($start));
		if($lastday!==$end)return $this->error('hrx_period_invalid','Payroll period must end on the final calendar day of the month.',422);
		$existing=Stockland_DB::read_row($wpdb->prepare('SELECT id,status,run_version FROM %i WHERE period_start=%s AND period_end=%s ORDER BY run_version DESC LIMIT 1',Stockland_HR_Replacement::table('hrx_payroll_runs'),$start,$end),ARRAY_A);
		if($existing)return $this->error('hrx_period_already_exists','This calendar month already has a payroll run. Do not create a second run to bypass a failed/closed run; any correction must be linked to the original and use a reviewed adjustment/reversal.',409);
		$version=$existing?(int)$existing['run_version']+1:1;$code='PRX-'.str_replace('-','',substr($start,0,7)).'-V'.$version;
		$row=array('run_code'=>$code,'period_start'=>$start,'period_end'=>$end,'business_cutoff'=>$end.' 23:59:59','run_version'=>$version,'status'=>'DRAFT','currency'=>'ETB','created_by'=>get_current_user_id(),'created_at'=>Stockland_HR_Replacement::now(),'version_no'=>1);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start payroll transaction.',500);
		if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_payroll_runs'),$row)){$this->rollback();return $this->error('hrx_run_create_failed','Payroll run could not be created. It may already exist.',409);}
		$id=(int)$wpdb->insert_id;$evt=Stockland_HR_Replacement::approval_event('payroll-run',$id,'RUN_CREATED',null,'DRAFT','Payroll month '.$start, $code);$audit=Stockland_HR_Replacement::audit('payroll_run_created','payroll-run',$id,'Draft run created');
		if(is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_run_audit_failed','Payroll run creation was rolled back due to audit failure.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'run_code'=>$code,'status'=>'DRAFT','message'=>'Draft created; preflight must be clear before calculation.'));
	}

	public function payroll_preflight( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$run=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_payroll_runs'),(int)$request['id']),ARRAY_A);
		if(!$run)return $this->error('hrx_run_not_found','Payroll run not found.',404);
		$issues=array();$people=Stockland_DB::read_results($wpdb->prepare("SELECT e.*,p.person_code FROM %i e INNER JOIN %i p ON p.id=e.person_id WHERE e.status='ACTIVE' AND e.start_date<=%s AND (e.end_date IS NULL OR e.end_date>=%s) ORDER BY e.id",Stockland_HR_Replacement::table('hrx_employments'),Stockland_HR_Replacement::table('hrx_people'),$run['period_end'],$run['period_start']),ARRAY_A);
		foreach((array)$people as $e){
			$comp=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE employment_id=%d AND status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_compensation_assignments'),(int)$e['id'],$run['period_end'],$run['period_start']),ARRAY_A);
			if(!$comp){$issues[]=array('code'=>'COMPENSATION_MISSING','employment_code'=>$e['employment_code'],'message'=>'No approved compensation assignment covers the pay period.');continue;}
			if('ETB'!==strtoupper((string)($comp['currency']??''))){$issues[]=array('code'=>'COMPENSATION_CURRENCY_UNSUPPORTED','employment_code'=>$e['employment_code'],'message'=>'Only ETB compensation is supported by this payroll run. Foreign-currency assignments require a separately approved conversion and accounting policy; no implicit conversion is applied.');}
			$open_exit=Stockland_DB::read_row($wpdb->prepare("SELECT id,effective_end_date,status FROM %i WHERE employment_id=%d AND status NOT IN('SETTLED','CANCELLED') AND effective_end_date<=%s ORDER BY effective_end_date DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_exit_settlements'),(int)$e['id'],$run['period_end']),ARRAY_A);
			if($wpdb->last_error){$issues[]=array('code'=>'EXIT_ELIGIBILITY_CHECK_FAILED','employment_code'=>$e['employment_code'],'message'=>'Could not verify exit-settlement eligibility; payroll is blocked until the data check succeeds.');}
			elseif($open_exit){$issues[]=array('code'=>'OPEN_EXIT_SETTLEMENT_OVERLAP','employment_code'=>$e['employment_code'],'message'=>'An unsettled exit is effective on or before the payroll period end. Resolve the final settlement before including this employment in ordinary payroll.');}
			$items=Stockland_DB::read_results($wpdb->prepare("SELECT * FROM %i WHERE compensation_assignment_id=%d AND status='APPROVED'",Stockland_HR_Replacement::table('hrx_compensation_items'),(int)$comp['id']),ARRAY_A);
			foreach((array)$items as $item){if(!in_array($item['taxable_status'],array('TAXABLE','EXEMPT'),true)||!in_array($item['pensionable_status'],array('PENSIONABLE','NON_PENSIONABLE'),true)){$issues[]=array('code'=>'COMPENSATION_CLASSIFICATION_REVIEW','employment_code'=>$e['employment_code'],'component'=>$item['component_code'],'message'=>'Compensation item has unresolved tax/pension classification.');}}
			if($e['start_date']>$run['period_start']||(!empty($e['end_date'])&&$e['end_date']<$run['period_end']))$issues[]=array('code'=>'PARTIAL_PERIOD_LEGAL_RULE_REQUIRED','employment_code'=>$e['employment_code'],'message'=>'Joiner/leaver is inside the pay month. A legally approved partial-period rule must be configured before this run can calculate.');
			$time_count=(int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE employment_id=%d AND event_date BETWEEN %s AND %s AND status IN ('DRAFT','SUBMITTED')",Stockland_HR_Replacement::table('hrx_time_events'),(int)$e['id'],$run['period_start'],$run['period_end']));
			if($time_count)$issues[]=array('code'=>'UNAPPROVED_TIME','employment_code'=>$e['employment_code'],'message'=>'Unapproved attendance/time exceptions remain in the pay period.');
			$leave_count=(int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE employment_id=%d AND start_date<=%s AND (end_date IS NULL OR end_date>=%s) AND status IN ('DRAFT','SUBMITTED')",Stockland_HR_Replacement::table('hrx_leave_transactions'),(int)$e['id'],$run['period_end'],$run['period_start']));
			if($leave_count)$issues[]=array('code'=>'UNAPPROVED_LEAVE','employment_code'=>$e['employment_code'],'message'=>'Unapproved leave transactions overlap the pay period.');
			$pay_affecting_time=(int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE employment_id=%d AND event_date BETWEEN %s AND %s AND status='APPROVED' AND (overtime_minutes>0 OR night_minutes>0 OR rest_day_minutes>0 OR holiday_minutes>0)",Stockland_HR_Replacement::table('hrx_time_events'),(int)$e['id'],$run['period_start'],$run['period_end']));
			if($pay_affecting_time)$issues[]=array('code'=>'APPROVED_TIME_PAYROLL_RULE_UNSUPPORTED','employment_code'=>$e['employment_code'],'message'=>'Approved overtime/night/rest-day/holiday time exists, but no effective approved payroll calculation method consumes these minutes. Payroll calculation is blocked rather than omitting the pay effect.');
			$unpaid_leave=(int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i t LEFT JOIN %i p ON p.id=t.leave_policy_id WHERE t.employment_id=%d AND t.start_date<=%s AND (t.end_date IS NULL OR t.end_date>=%s) AND t.status='APPROVED' AND COALESCE(UPPER(p.paid_status),'') NOT IN ('PAID','FULLY_PAID')",Stockland_HR_Replacement::table('hrx_leave_transactions'),Stockland_HR_Replacement::table('hrx_leave_policies'),(int)$e['id'],$run['period_end'],$run['period_start']));
			if($unpaid_leave)$issues[]=array('code'=>'APPROVED_UNPAID_LEAVE_PAYROLL_RULE_UNSUPPORTED','employment_code'=>$e['employment_code'],'message'=>'Approved unpaid or partially paid leave exists, but no effective approved salary-deduction calculation consumes it. Payroll calculation is blocked.');
		}
		$families=array('BASE_SALARY','PAYE_BASE','PAYE','EMPLOYEE_PENSION','EMPLOYER_PENSION');
		$rules=array();foreach($families as $family){$r=$this->active_rule($family,$run['period_start'],$run['period_end']);if(!$r)$issues[]=array('code'=>'LEGAL_RULE_UNAVAILABLE','rule_family'=>$family,'message'=>'No single approved effective-dated rule covers the entire pay period.');else $rules[$family]=$r;}
		$schema_count=count($people);
		return rest_ensure_response(array('run_code'=>$run['run_code'],'period'=>array('start'=>$run['period_start'],'end'=>$run['period_end']),'active_employees'=>$schema_count,'issues'=>$issues,'rule_families'=>array_keys($rules),'ready'=>empty($issues),'note'=>'No employee amounts are shown in the preflight response. Unknown or unapproved legal treatment blocks calculation.'));
	}

	private function active_rule($family,$start,$end){
		global $wpdb;
		$rows=Stockland_DB::read_results($wpdb->prepare("SELECT * FROM %i WHERE rule_family=%s AND review_status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC",Stockland_HR_Replacement::table('hrx_legal_rules'),$family,$start,$end),ARRAY_A);
		if(empty($rows))return null;
		// More than one rule is a conflict unless only the same version/range is returned.
		if(count($rows)>1)return null;
		$rule=$rows[0];$params=json_decode((string)$rule['formula_parameters'],true);$rule['parameters']=is_array($params)?$params:array();
		if('PAYE'===$family){
			$bands=Stockland_DB::read_results($wpdb->prepare('SELECT band_order,lower_bound,upper_bound,rate,constant_credit,calculation_method FROM %i WHERE legal_rule_id=%d ORDER BY band_order',Stockland_HR_Replacement::table('hrx_legal_rule_bands'),(int)$rule['id']),ARRAY_A);
			$rule['bands']=$bands;
		}
		return $rule;
	}

	public function calculate_payroll( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$id=(int)$request['id'];$run=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_payroll_runs'),$id),ARRAY_A);
		if(!$run)return $this->error('hrx_run_not_found','Payroll run not found.',404);
		if('DRAFT'!==strtoupper((string)$run['status']))return $this->error('hrx_run_locked','Only a DRAFT run can be calculated.',409);
		$pre=$this->payroll_preflight($request);$p=(array)$pre->get_data();if(empty($p['ready']))return $this->error('hrx_preflight_blocked','Payroll calculation blocked. Resolve all compensation, time, leave and legal-rule issues first.',409);
		$rules=array();foreach(array('BASE_SALARY','PAYE_BASE','PAYE','EMPLOYEE_PENSION','EMPLOYER_PENSION') as $f)$rules[$str=strtolower($f)]=$this->active_rule($f,$run['period_start'],$run['period_end']);
		$people=Stockland_DB::read_results($wpdb->prepare("SELECT e.*,p.id AS person_id FROM %i e INNER JOIN %i p ON p.id=e.person_id WHERE e.status='ACTIVE' AND e.start_date<=%s AND (e.end_date IS NULL OR e.end_date>=%s) ORDER BY e.id",Stockland_HR_Replacement::table('hrx_employments'),Stockland_HR_Replacement::table('hrx_people'),$run['period_end'],$run['period_start']),ARRAY_A);
		$lines=array();$inputs=array();$sum=array('gross'=>'0.00','taxable'=>'0.00','paye'=>'0.00','employee_pension'=>'0.00','employer_pension'=>'0.00','deductions'=>'0.00','net'=>'0.00','employer_cost'=>'0.00');
		foreach((array)$people as $e){
			$comp=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE employment_id=%d AND status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_compensation_assignments'),(int)$e['id'],$run['period_end'],$run['period_start']),ARRAY_A);
			if(!$comp)return $this->error('hrx_compensation_missing','Preflight/data changed: an active employment has no approved compensation assignment.',409);
			if('ETB'!==strtoupper((string)($comp['currency']??'')))return $this->error('hrx_compensation_currency_unsupported','Only ETB compensation can be calculated. Foreign-currency payroll requires an approved conversion and accounting policy.',409);
			$open_exit=Stockland_DB::read_row($wpdb->prepare("SELECT id,effective_end_date,status FROM %i WHERE employment_id=%d AND status NOT IN('SETTLED','CANCELLED') AND effective_end_date<=%s ORDER BY effective_end_date DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_exit_settlements'),(int)$e['id'],$run['period_end']),ARRAY_A);
			if($wpdb->last_error||$open_exit)return $this->error('hrx_open_exit_blocks_payroll','Exit-settlement eligibility changed or could not be verified. Resolve the exit or restore a confirmed payroll eligibility state before calculation.',409);
			$comp_items=Stockland_DB::read_results($wpdb->prepare("SELECT * FROM %i WHERE compensation_assignment_id=%d AND status='APPROVED' ORDER BY id",Stockland_HR_Replacement::table('hrx_compensation_items'),(int)$comp['id']),ARRAY_A);
			$items=array();foreach((array)$comp_items as $i)$items[]=array('id'=>(int)$i['id'],'component_code'=>$i['component_code'],'component_name'=>$i['component_name'],'component_type'=>$i['component_type'],'amount'=>$i['amount'],'frequency'=>$i['frequency'],'cash_payable'=>(int)$i['cash_payable'],'taxable_status'=>$i['taxable_status'],'pensionable_status'=>$i['pensionable_status'],'legal_rule_id'=>(int)$i['legal_rule_id']);
			$rule_input=array(
				'base_salary'=>$comp['base_salary'],'compensation_assignment_id'=>(int)$comp['id'],'items'=>$items
			);
			$calc_rules=array(
				'base_salary_classification'=>array('id'=>(int)$rules['base_salary']['id'],'review_status'=>$rules['base_salary']['review_status'],'formula_parameters'=>$rules['base_salary']['formula_parameters']),
				'paye_base'=>array('id'=>(int)$rules['paye_base']['id'],'review_status'=>$rules['paye_base']['review_status'],'formula_key'=>$rules['paye_base']['formula_key']),
				'paye'=>array('id'=>(int)$rules['paye']['id'],'review_status'=>$rules['paye']['review_status'],'formula_key'=>$rules['paye']['formula_key'],'bands'=>$rules['paye']['bands']),
				'employee_pension'=>array('id'=>(int)$rules['employee_pension']['id'],'review_status'=>$rules['employee_pension']['review_status'],'formula_key'=>$rules['employee_pension']['formula_key'],'formula_parameters'=>$rules['employee_pension']['formula_parameters']),
				'employer_pension'=>array('id'=>(int)$rules['employer_pension']['id'],'review_status'=>$rules['employer_pension']['review_status'],'formula_key'=>$rules['employer_pension']['formula_key'],'formula_parameters'=>$rules['employer_pension']['formula_parameters'])
			);
			$result=Stockland_HR_Replacement_Calculator::payroll_line($rule_input,$calc_rules);if(is_wp_error($result))return $result;
			$input=array('employment_id'=>(int)$e['id'],'person_id'=>(int)$e['person_id'],'source_kind'=>'COMPENSATION','source_id'=>(int)$comp['id'],'source_version'=>(int)$comp['version_no'],'snapshot'=>array('employment_code'=>$e['employment_code'],'person_id'=>(int)$e['person_id'],'compensation_assignment_id'=>(int)$comp['id'],'base_salary'=>$comp['base_salary'],'items'=>$items,'calculation'=>$result,'rule_ids'=>array_map(static function($r){return (int)$r['id'];},$rules)));
			$input_hash=hash('sha256',wp_json_encode($input));$inputs[]=array('payroll_run_id'=>$id,'employment_id'=>(int)$e['id'],'source_kind'=>'COMPENSATION','source_id'=>(int)$comp['id'],'source_version'=>(int)$comp['version_no'],'snapshot_json'=>wp_json_encode($input['snapshot']),'source_hash'=>$input_hash,'eligibility_status'=>'READY','created_at'=>Stockland_HR_Replacement::now());
			$lines[]=array('payroll_run_id'=>$id,'employment_id'=>(int)$e['id'],'person_id'=>(int)$e['person_id'],'line_status'=>'CALCULATED','base_salary'=>$result['base_salary'],'taxable_earnings'=>$result['taxable_earnings'],'exempt_earnings'=>$result['exempt_earnings'],'gross_pay'=>$result['gross_pay'],'taxable_base'=>$result['taxable_base'],'paye'=>$result['paye'],'employee_pension'=>$result['employee_pension'],'employer_pension'=>$result['employer_pension'],'other_deductions'=>$result['other_deductions'],'net_pay'=>$result['net_pay'],'employer_cost'=>$result['employer_cost'],'rule_bundle_sha256'=>hash('sha256',wp_json_encode($rules)),'created_at'=>Stockland_HR_Replacement::now(),'_items'=>$result['line_items']);
			foreach(array('gross_pay'=>'gross','taxable_earnings'=>'taxable','paye'=>'paye','employee_pension'=>'employee_pension','employer_pension'=>'employer_pension','other_deductions'=>'deductions','net_pay'=>'net','employer_cost'=>'employer_cost') as $key=>$target)$sum[$target]=Stockland_Money::add($sum[$target],$result[$key]);
		}
		if(empty($lines))return $this->error('hrx_empty_payroll','No active employees with complete data were found. An empty payroll cannot be submitted.',409);
		$snapshot_hash=hash('sha256',wp_json_encode($inputs));$rule_hash=hash('sha256',wp_json_encode($rules));
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start payroll calculation transaction.',500);
		$wpdb->last_error='';
		$existing=(int)Stockland_DB::read_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE payroll_run_id=%d',Stockland_HR_Replacement::table('hrx_payroll_lines'),$id));
		if($existing){$this->rollback();return $this->error('hrx_lines_already_exist','Calculated payroll lines already exist; use a linked correction run rather than overwriting.',409);}
		foreach($inputs as $input){if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_payroll_inputs'),$input)){$this->rollback();return $this->error('hrx_snapshot_write_failed','Frozen payroll inputs could not be persisted.',500);}}
		foreach($lines as $line){
			$item_rows=$line['_items'];unset($line['_items']);
			if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_payroll_lines'),$line)){$this->rollback();return $this->error('hrx_line_write_failed','A payroll line could not be saved; calculation was rolled back.',500);}
			$line_id=(int)$wpdb->insert_id;
			foreach($item_rows as $item){
				$row=array('payroll_line_id'=>$line_id,'item_code'=>sanitize_key($item['item_code']),'item_label'=>sanitize_text_field($item['item_label']),'item_type'=>sanitize_key($item['item_type']),'quantity'=>null,'rate'=>null,'amount'=>Stockland_Money::decimal(Stockland_Money::minor($item['amount'])),'cash_payable'=>(int)$item['cash_payable'],'taxable_status'=>sanitize_key($item['taxable_status']),'pensionable_status'=>sanitize_key($item['pensionable_status']),'legal_rule_id'=>!empty($item['legal_rule_id'])?(int)$item['legal_rule_id']:null,'source_type'=>sanitize_key($item['source_type']),'source_id'=>!empty($item['source_id'])?(int)$item['source_id']:null,'calculation_trace'=>sanitize_textarea_field($item['calculation_trace']),'created_at'=>Stockland_HR_Replacement::now());
				if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_payroll_line_items'),$row)){$this->rollback();return $this->error('hrx_line_item_write_failed','A payroll line item could not be saved; calculation was rolled back.',500);}
			}
		}
		$update=array('status'=>'CALCULATED','input_snapshot_sha256'=>$snapshot_hash,'rule_bundle_sha256'=>$rule_hash,'employee_count'=>count($lines),'gross_total'=>$sum['gross'],'taxable_total'=>$sum['taxable'],'paye_total'=>$sum['paye'],'employee_pension_total'=>$sum['employee_pension'],'employer_pension_total'=>$sum['employer_pension'],'deduction_total'=>$sum['deductions'],'net_total'=>$sum['net'],'employer_cost_total'=>$sum['employer_cost'],'updated_at'=>Stockland_HR_Replacement::now(),'version_no'=>(int)$run['version_no']+1);
		if(false===Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payroll_runs'),$update,array('id'=>$id,'status'=>'DRAFT'))){$this->rollback();return $this->error('hrx_run_update_failed','Run state changed concurrently; calculation was rolled back.',409);}
		$evt=Stockland_HR_Replacement::approval_event('payroll-run',$id,'CALCULATED','DRAFT','CALCULATED','Frozen inputs and approved rules calculated',wp_generate_uuid4());
		$audit=Stockland_HR_Replacement::audit('payroll_calculated','payroll-run',$id,'Input/rule hashes pinned; no real employee data enters source artifacts.');
		if(is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_calculation_audit_failed','Calculation was rolled back because audit evidence failed.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'status'=>'CALCULATED','employee_count'=>count($lines),'totals'=>$sum,'input_snapshot_sha256'=>$snapshot_hash,'rule_bundle_sha256'=>$rule_hash,'message'=>'Calculated values are frozen. Review variance and approve only after legal and finance review.'));
	}

	public function submit_payroll( $request ) {
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;$id=(int)$request['id'];$run=$this->run_row($id);
		if(is_wp_error($run))return $run;if('CALCULATED'!==$run['status'])return $this->error('hrx_run_not_calculated','Only a calculated run can be submitted.',409);
		if((int)$run['created_by']!==get_current_user_id())return $this->error('hrx_submitter_mismatch','Only the Management preparer may submit this run.',403);
		$key=sanitize_text_field((string)($this->body($request)['idempotency_key']??''));if(!$key)return $this->error('hrx_idempotency_required','Submission idempotency key is required.',422);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start payroll submission transaction.',500);
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payroll_runs'),array('status'=>'SUBMITTED','submitted_by'=>get_current_user_id(),'submitted_at'=>Stockland_HR_Replacement::now(),'updated_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'status'=>'CALCULATED'));
		$evt=Stockland_HR_Replacement::approval_event('payroll-run',$id,'SUBMITTED','CALCULATED','SUBMITTED','Submitted by preparer',$key);
		$audit=Stockland_HR_Replacement::audit('payroll_submitted','payroll-run',$id,'Submitted for Owner approval');
		if(false===$ok||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_submit_failed','Submission was rolled back.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'status'=>'SUBMITTED'));
	}

	private function run_row($id){
		global $wpdb;$row=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_payroll_runs'),(int)$id),ARRAY_A);
		return $row?$row:$this->error('hrx_run_not_found','Payroll run not found.',404);
	}

	private function remittance_due_date_from_rule( $rule, $period_end ) {
		$params=json_decode((string)($rule['formula_parameters']??''),true);
		$offset=isset($params['remittance_due_month_offset'])?(int)$params['remittance_due_month_offset']:-1;
		$day=isset($params['remittance_due_day_of_month'])?(int)$params['remittance_due_day_of_month']:0;
		if($offset<0||$offset>12||$day<1||$day>31)return null;
		$month_start=substr((string)$period_end,0,7).'-01';
		$base=DateTimeImmutable::createFromFormat('!Y-m-d',$month_start);
		if(!$base)return null;
		$due_month=$base->modify(($offset>=0?'+':'').$offset.' months');
		$last_day=(int)$due_month->format('t');$due_day=min($day,$last_day);
		return $due_month->setDate((int)$due_month->format('Y'),(int)$due_month->format('m'),$due_day)->format('Y-m-d');
	}

	private function frozen_payroll_sources_current( $run ) {
		global $wpdb;
		$period_start=(string)$run['period_start'];$period_end=(string)$run['period_end'];$run_id=(int)$run['id'];
		$current=Stockland_DB::read_results($wpdb->prepare("SELECT e.id,e.person_id,e.employment_code FROM %i e INNER JOIN %i p ON p.id=e.person_id WHERE e.status='ACTIVE' AND e.start_date<=%s AND (e.end_date IS NULL OR e.end_date>=%s) ORDER BY e.id",Stockland_HR_Replacement::table('hrx_employments'),Stockland_HR_Replacement::table('hrx_people'),$period_end,$period_start),ARRAY_A);
		if($wpdb->last_error)return $this->error('hrx_frozen_employee_read_failed','Could not re-read the active employee set; payroll approval is blocked.',500);
		$frozen=Stockland_DB::read_results($wpdb->prepare("SELECT employment_id,source_id,source_version,source_kind,snapshot_json FROM %i WHERE payroll_run_id=%d AND eligibility_status='READY' ORDER BY employment_id",Stockland_HR_Replacement::table('hrx_payroll_inputs'),$run_id),ARRAY_A);
		if($wpdb->last_error||!is_array($frozen))return $this->error('hrx_frozen_input_read_failed','Could not read the frozen payroll input set; approval is blocked.',500);
		$current_ids=array_map(static function($row){return (int)$row['id'];},(array)$current);
		$frozen_ids=array_map(static function($row){return (int)$row['employment_id'];},(array)$frozen);
		if($current_ids!==$frozen_ids||(int)$run['employee_count']!==count($current_ids))return $this->error('hrx_frozen_employee_set_changed','The currently eligible employee set differs from the frozen payroll inputs. Recalculate in a new draft before approval.',409);
		foreach($current as $employee){
			$employment_id=(int)$employee['id'];$input=null;
			foreach($frozen as $candidate){if((int)$candidate['employment_id']===$employment_id){$input=$candidate;break;}}
			if(!$input||'COMPENSATION'!==strtoupper((string)$input['source_kind']))return $this->error('hrx_frozen_input_identity_changed','A frozen payroll input is missing or has an unsupported source type.',409);
			$comp=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND employment_id=%d AND status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s)",Stockland_HR_Replacement::table('hrx_compensation_assignments'),(int)$input['source_id'],$employment_id,$period_end,$period_start),ARRAY_A);
			if(!$comp||(int)$comp['version_no']!==(int)$input['source_version'])return $this->error('hrx_frozen_compensation_changed','An approved compensation assignment was changed or is no longer effective after calculation. Approval is blocked.',409);
			$items=Stockland_DB::read_results($wpdb->prepare("SELECT * FROM %i WHERE compensation_assignment_id=%d AND status='APPROVED' ORDER BY id",Stockland_HR_Replacement::table('hrx_compensation_items'),(int)$comp['id']),ARRAY_A);
			if($wpdb->last_error)return $this->error('hrx_frozen_compensation_read_failed','Could not re-read approved compensation items; approval is blocked.',500);
			$current_items=array();foreach((array)$items as $item)$current_items[]=array('id'=>(int)$item['id'],'component_code'=>$item['component_code'],'component_name'=>$item['component_name'],'component_type'=>$item['component_type'],'amount'=>$item['amount'],'frequency'=>$item['frequency'],'cash_payable'=>(int)$item['cash_payable'],'taxable_status'=>$item['taxable_status'],'pensionable_status'=>$item['pensionable_status'],'legal_rule_id'=>(int)$item['legal_rule_id']);
			$snapshot=json_decode((string)$input['snapshot_json'],true);
			if(!is_array($snapshot)||Stockland_Money::minor($snapshot['base_salary']??'-1')!==Stockland_Money::minor($comp['base_salary'])||wp_json_encode($snapshot['items']??null)!==wp_json_encode($current_items)||(int)($snapshot['compensation_assignment_id']??0)!==(int)$comp['id'])return $this->error('hrx_frozen_compensation_items_changed','Current salary or allowance items differ from the approved frozen calculation inputs. Recalculate before approval.',409);
		}
		$rules=array();foreach(array('BASE_SALARY','PAYE_BASE','PAYE','EMPLOYEE_PENSION','EMPLOYER_PENSION') as $family)$rules[strtolower($family)]=$this->active_rule($family,$period_start,$period_end);
		foreach($rules as $rule){if(!$rule)return $this->error('hrx_frozen_rules_changed','An approved effective-dated payroll rule changed or is unavailable after calculation.',409);}
		$rule_hash=hash('sha256',wp_json_encode($rules));
		if(!hash_equals((string)$run['rule_bundle_sha256'],$rule_hash))return $this->error('hrx_frozen_rules_changed','The effective payroll rule bundle differs from the bundle used for calculation. Recalculate before approval.',409);
		return true;
	}

	public function approve_payroll_run( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;$id=(int)$request['id'];$run=$this->run_row($id);if(is_wp_error($run))return $run;
		if('SUBMITTED'!==$run['status'])return $this->error('hrx_run_not_submitted','Only a submitted payroll run can be approved.',409);
		if(Stockland_Money::minor($run['net_total'])<=0||Stockland_Money::minor($run['gross_total'])<=0)return $this->error('hrx_payroll_zero_payable','A payroll run must contain positive gross and net payable totals to proceed to approval and settlement.',409);
		if((int)$run['submitted_by']===get_current_user_id())return $this->error('hrx_no_self_approval','The payroll preparer cannot approve the same payroll run.',403);
		$body=$this->body($request);$note=sanitize_textarea_field((string)($body['approval_note']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));
		if(strlen(trim($note))<30||!$key)return $this->error('hrx_approval_evidence_required','Owner approval note (30+ characters) and idempotency key are required.',422);
		$pre=$this->payroll_preflight($request);$pd=(array)$pre->get_data();if(empty($pd['ready']))return $this->error('hrx_preflight_changed','Current source data or legal rules no longer pass preflight. Do not approve this stale run.',409);
		$frozen_check=$this->frozen_payroll_sources_current($run);if(is_wp_error($frozen_check))return $frozen_check;
		$config=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_accounting_config'),$run['period_end'],$run['period_start']),ARRAY_A);
		if(!$config)return $this->error('hrx_gl_mapping_missing','No approved effective-dated payroll GL account mapping is available.',409);
		$line_totals=Stockland_DB::read_row($wpdb->prepare('SELECT COUNT(*) n,COALESCE(SUM(gross_pay),0) gross,COALESCE(SUM(paye),0) paye,COALESCE(SUM(employee_pension),0) employee_pension,COALESCE(SUM(employer_pension),0) employer_pension,COALESCE(SUM(other_deductions),0) other_deductions,COALESCE(SUM(net_pay),0) net FROM %i WHERE payroll_run_id=%d',Stockland_HR_Replacement::table('hrx_payroll_lines'),$id),ARRAY_A);
		if(!$line_totals||(int)$line_totals['n']!==(int)$run['employee_count'])return $this->error('hrx_line_reconciliation_failed','Payroll line count does not reconcile to the frozen run.',409);
		foreach(array('gross'=>'gross_total','paye'=>'paye_total','employee_pension'=>'employee_pension_total','employer_pension'=>'employer_pension_total','other_deductions'=>'deduction_total','net'=>'net_total') as $col=>$runcol){if(Stockland_Money::minor($line_totals[$col])!==Stockland_Money::minor($run[$runcol]))return $this->error('hrx_total_mismatch','Payroll totals do not reconcile. Approval is blocked.',409);}
		$debit_base=Stockland_Money::add($run['gross_total'],$run['employer_pension_total']);
		$credit=Stockland_Money::add($run['net_total'],Stockland_Money::add($run['paye_total'],Stockland_Money::add($run['employee_pension_total'],Stockland_Money::add($run['employer_pension_total'],$run['deduction_total']))));
		$delta=Stockland_Money::subtract($debit_base,$credit);
		if(Stockland_Money::minor($delta)!==0)return $this->error('hrx_journal_unbalanced','Payroll journal preview does not balance; fix supported earning/deduction mappings before approval.',409);
		$obligation_specs=array(
			array('type'=>'PAYE','amount'=>$run['paye_total'],'rule_family'=>'PAYE'),
			array('type'=>'EMPLOYEE_PENSION','amount'=>$run['employee_pension_total'],'rule_family'=>'EMPLOYEE_PENSION'),
			array('type'=>'EMPLOYER_PENSION','amount'=>$run['employer_pension_total'],'rule_family'=>'EMPLOYER_PENSION')
		);
		$obligations=array();
		foreach($obligation_specs as $spec){
			$amt=Stockland_Money::decimal(Stockland_Money::minor($spec['amount']));
			$rule=$this->active_rule($spec['rule_family'],$run['period_start'],$run['period_end']);
			if(!$rule)return $this->error('hrx_obligation_rule_missing','Each statutory obligation needs an approved rule effective for the full payroll period.',409);
			$due=Stockland_Money::minor($amt)>0?$this->remittance_due_date_from_rule($rule,$run['period_end']):null;
			if(Stockland_Money::minor($amt)>0&&!$due)return $this->error('hrx_obligation_due_date_rule_missing','Approved statutory rule must include remittance_due_month_offset and remittance_due_day_of_month. No statutory deadline is guessed.',409);
			$obligations[]=array('obligation_code'=>'OBX-'.$run['run_code'].'-'.$spec['type'],'payroll_run_id'=>$id,'obligation_type'=>$spec['type'],'rule_id'=>(int)$rule['id'],'amount_due'=>$amt,'due_date'=>$due,'status'=>Stockland_Money::minor($amt)>0?'OPEN':'NOT_REQUIRED','created_at'=>Stockland_HR_Replacement::now());
		}
		$existing_obligations=(int)Stockland_DB::read_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE payroll_run_id=%d',Stockland_HR_Replacement::table('hrx_statutory_obligations'),$id));
		if($existing_obligations>0)return $this->error('hrx_obligation_duplicate','Statutory obligations already exist for this run. Stop and reconcile the prior approval attempt before retrying.',409);
		$lines=array(
			array('account_code'=>$config['wage_expense_account'],'debit'=>$run['gross_total'],'credit'=>'0.00','memo'=>'HRX payroll gross '.$run['run_code']),
			array('account_code'=>$config['employer_pension_expense_account'],'debit'=>$run['employer_pension_total'],'credit'=>'0.00','memo'=>'HRX employer pension '.$run['run_code']),
			array('account_code'=>$config['net_payable_account'],'debit'=>'0.00','credit'=>$run['net_total'],'memo'=>'HRX employee net payable '.$run['run_code']),
			array('account_code'=>$config['paye_payable_account'],'debit'=>'0.00','credit'=>$run['paye_total'],'memo'=>'HRX PAYE payable '.$run['run_code']),
			array('account_code'=>$config['employee_pension_payable_account'],'debit'=>'0.00','credit'=>$run['employee_pension_total'],'memo'=>'HRX employee pension payable '.$run['run_code']),
			array('account_code'=>$config['employer_pension_payable_account'],'debit'=>'0.00','credit'=>$run['employer_pension_total'],'memo'=>'HRX employer pension payable '.$run['run_code'])
		);
		if(Stockland_Money::minor($run['deduction_total'])>0){if(!$config['other_deductions_payable_account'])return $this->error('hrx_deduction_account_missing','Approved GL mapping lacks other deductions payable account.',409);$lines[]=array('account_code'=>$config['other_deductions_payable_account'],'debit'=>'0.00','credit'=>$run['deduction_total'],'memo'=>'HRX other deductions '.$run['run_code']);}
		// Existing Stockland accounting service controls GL idempotency and balancing.
		foreach($lines as $line){if(!Stockland_Accounting::account_exists($line['account_code']))return $this->error('hrx_gl_account_invalid','An approved GL account mapping refers to a non-existent account.',409);}
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start payroll posting transaction.',500);
		$journal=Stockland_Accounting::post_journal($run['period_end'],'HRX_PAYROLL_RUN',$id,'Payroll '.$run['run_code'],$lines,get_current_user_id());
		if(!$journal){$this->rollback();return $this->error('hrx_gl_post_failed','GL posting failed. Payroll remains unapproved and no payout was marked settled.',500);}
		$obligation_failed=false;foreach($obligations as $obligation){if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_statutory_obligations'),$obligation)){$obligation_failed=true;break;}}
		if($obligation_failed){$this->rollback();return $this->error('hrx_obligation_persist_failed','Payroll was not approved because statutory obligation records could not be created atomically.',500);}
		$link=array('source_type'=>'HRX_PAYROLL_RUN','source_id'=>$id,'journal_id'=>(int)$journal,'posted_at'=>Stockland_HR_Replacement::now(),'posted_by'=>get_current_user_id());
		if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_accounting_links'),$link)){$this->rollback();return $this->error('hrx_gl_link_failed','Could not link payroll run to its journal; transaction rolled back.',500);}
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payroll_runs'),array('status'=>'APPROVED','approved_by'=>get_current_user_id(),'approved_at'=>Stockland_HR_Replacement::now(),'journal_id'=>(int)$journal,'payout_state'=>'NOT_STARTED','updated_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'status'=>'SUBMITTED'));
		$evt=Stockland_HR_Replacement::approval_event('payroll-run',$id,'OWNER_APPROVED','SUBMITTED','APPROVED',$note,$key,null,get_current_user_id());
		$audit=Stockland_HR_Replacement::audit('payroll_owner_approved','payroll-run',$id,'Approved and posted through existing GL service');
		if(false===$ok||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_payroll_approval_failed','Approval/GL linking was rolled back due to audit/state failure.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'status'=>'APPROVED','journal_id'=>(int)$journal,'payout_state'=>'NOT_STARTED','message'=>'GL journal is posted. Employee payout and statutory remittances remain separate settlement steps.'));
	}

	public function create_payout_batch( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;$id=(int)$request['id'];$run=$this->run_row($id);if(is_wp_error($run))return $run;
		if('APPROVED'!==$run['status']||'NOT_STARTED'!==$run['payout_state'])return $this->error('hrx_payout_state_conflict','Payout batch can only be created once for an approved run that has not started settlement.',409);
		$body=$this->body($request);$method=strtoupper(sanitize_key($body['method']??''));if(!in_array($method,array('BANK_TRANSFER','CASH','CHEQUE'),true))return $this->error('hrx_payout_method_invalid','Choose BANK_TRANSFER, CASH, or CHEQUE.',422);
		$batch_code='PBX-'.str_replace('-','',substr($run['period_start'],0,7)).'-'.$run['run_code'];
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start payout batch transaction.',500);
		$batch=array('batch_code'=>$batch_code,'payroll_run_id'=>$id,'method'=>$method,'batch_total'=>$run['net_total'],'status'=>'DRAFT','created_by'=>get_current_user_id(),'approved_by'=>get_current_user_id(),'approved_at'=>Stockland_HR_Replacement::now(),'created_at'=>Stockland_HR_Replacement::now());
		if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_payout_batches'),$batch)){$this->rollback();return $this->error('hrx_payout_batch_duplicate','A payout batch already exists. Do not duplicate payment instructions.',409);}
		$batch_id=(int)$wpdb->insert_id;
		$lines=Stockland_DB::read_results($wpdb->prepare('SELECT id,net_pay FROM %i WHERE payroll_run_id=%d ORDER BY id',Stockland_HR_Replacement::table('hrx_payroll_lines'),$id),ARRAY_A);
		foreach((array)$lines as $line){
			if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_payout_items'),array('payout_batch_id'=>$batch_id,'payroll_line_id'=>(int)$line['id'],'amount'=>$line['net_pay'],'status'=>'PENDING','created_at'=>Stockland_HR_Replacement::now()))){$this->rollback();return $this->error('hrx_payout_items_failed','Payout items could not be created atomically.',500);}
		}
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payout_batches'),array('status'=>'READY'),array('id'=>$batch_id));
		$ok2=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payroll_runs'),array('status'=>'SETTLEMENT_PENDING','payout_state'=>'PENDING','updated_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'status'=>'APPROVED','payout_state'=>'NOT_STARTED'));
		$audit=Stockland_HR_Replacement::audit('payout_batch_created','payroll-run',$id,'Payout batch created; not marked paid');
		if(false===$ok||false===$ok2||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_payout_batch_failed','Payout batch transition was rolled back.',500);}
		$this->commit();return rest_ensure_response(array('batch_id'=>$batch_id,'batch_code'=>$batch_code,'count'=>count($lines),'status'=>'READY','payout_state'=>'PENDING','message'=>'Batch created; each external payment must be confirmed independently.'));
	}

	public function record_payout_outcome( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;$id=(int)$request['id'];$body=$this->body($request);
		$outcome=strtoupper(sanitize_key($body['outcome']??''));$ref=sanitize_text_field((string)($body['external_reference']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));$note=sanitize_textarea_field((string)($body['note']??''));
		if(!in_array($outcome,array('SETTLED','FAILED','RETURNED'),true)||strlen($ref)<4||!$key)return $this->error('hrx_payout_evidence_required','Outcome, external bank/cash reference (minimum 4 characters), and idempotency key are required.',422);
		$item=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_HR_Replacement::table('hrx_payout_items'),$id),ARRAY_A);if(!$item)return $this->error('hrx_payout_item_not_found','Payout item not found.',404);
		if('SETTLED'===$item['status'])return $this->error('hrx_payout_already_settled','This payout item is already settled; a second successful payout is prohibited.',409);
		if(in_array(strtoupper((string)$item['status']),array('CANCELLED'),true))return $this->error('hrx_payout_item_locked','Cancelled payout item cannot be changed.',409);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start payout outcome transaction.',500);
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payout_items'),array('status'=>$outcome,'payment_reference'=>$ref,'settled_at'=>'SETTLED'===$outcome?Stockland_HR_Replacement::now():null,'confirmed_by'=>get_current_user_id(),'updated_at'=>Stockland_HR_Replacement::now()),array('id'=>$id));
		$attempt=Stockland_DB::read_row($wpdb->prepare('SELECT MAX(attempt_no) n FROM %i WHERE payout_item_id=%d',Stockland_HR_Replacement::table('hrx_payout_attempts'),$id),ARRAY_A);$num=(int)($attempt['n']??0)+1;
		$att=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_payout_attempts'),array('payout_item_id'=>$id,'attempt_no'=>$num,'attempt_status'=>$outcome,'external_reference'=>$ref,'attempted_at'=>Stockland_HR_Replacement::now(),'outcome_at'=>Stockland_HR_Replacement::now(),'failure_code'=>('SETTLED'===$outcome?null:$outcome),'failure_note_redacted'=>($note?substr($note,0,500):null),'created_by'=>get_current_user_id()));
		$evt=Stockland_HR_Replacement::approval_event('payout-item',$id,'PAYOUT_'.$outcome,null,$outcome,$note,$key,null,get_current_user_id());
		$audit=Stockland_HR_Replacement::audit('payout_'.$outcome,'payout-item',$id,'Outcome recorded against external reference ending '.substr($ref,-4));
		if(false===$ok||false===$att||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_payout_outcome_failed','Payout outcome was rolled back because the state/audit evidence did not persist.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'status'=>$outcome,'message'=>'External payment result recorded; reconciliation and statutory remittances remain outstanding.'));
	}


	public function activate_employment( $request ) {
		global $wpdb;
		$ready=$this->require_schema(); if(is_wp_error($ready)) return $ready;
		$id=(int)$request['id']; $body=$this->body($request);
		$note=sanitize_textarea_field((string)($body['approval_note']??'')); $key=sanitize_text_field((string)($body['idempotency_key']??''));
		if(strlen(trim($note))<30||''===$key)return $this->error('hrx_activation_evidence_required','Owner activation needs a documented decision (30+ characters) and an idempotency key.',422);
		$emp=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_HR_Replacement::table('hrx_employments'),$id),ARRAY_A);
		if(!$emp)return $this->error('hrx_employment_not_found','Employment relationship not found.',404);
		if('PENDING_ACTIVATION'!==strtoupper((string)$emp['status']))return $this->error('hrx_employment_state_invalid','Only a pending employment relationship may be activated.',409);
		if((int)$emp['created_by']===get_current_user_id())return $this->error('hrx_no_self_approval','The person who prepared the hire cannot approve activation.',403);
		$contract=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE employment_id=%d AND status='APPROVED' AND signed_by_person_at IS NOT NULL ORDER BY contract_version DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_contracts'),$id),ARRAY_A);
		if(!$contract||empty($contract['private_document_ref_id']))return $this->error('hrx_contract_not_signed_approved','A signed, independently approved employment contract is required before activation.',409);
		$doc=Stockland_DB::read_row($wpdb->prepare("SELECT id FROM %i WHERE id=%d AND entity_type='CONTRACT' AND entity_id=%d AND document_type='EMPLOYMENT_CONTRACT' AND approval_status='APPROVED'",Stockland_HR_Replacement::table('hrx_document_refs'),(int)$contract['private_document_ref_id'],(int)$contract['id']),ARRAY_A);
		if(!$doc)return $this->error('hrx_private_contract_missing','The signed contract must be stored as a verified private document.',409);
		$pending=(int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE employment_id=%d AND status<>'COMPLETE'",Stockland_HR_Replacement::table('hrx_onboarding_tasks'),$id));
		if($pending>0)return $this->error('hrx_onboarding_incomplete','Complete required contract, personnel-document, compensation setup and induction tasks before activation.',409);
		$assignment=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE employment_id=%d AND approval_status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_assignments'),$id,$emp['start_date'],$emp['start_date']),ARRAY_A);
		if(!$assignment)return $this->error('hrx_assignment_missing','An approved effective-dated position/org/location assignment is required before activation.',409);
		if(!empty($assignment['location_id'])&&!Stockland_Authority::can_access_location((int)$assignment['location_id']))return $this->error('hrx_location_forbidden','The assignment location is outside your authorized scope.',403);
		$comp=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE employment_id=%d AND status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_compensation_assignments'),$id,$emp['start_date'],$emp['start_date']),ARRAY_A);
		if(!$comp)return $this->error('hrx_compensation_missing','An approved effective-dated compensation assignment must exist before activation. The system will not invent a salary.',409);
		$items=Stockland_DB::read_results($wpdb->prepare("SELECT * FROM %i WHERE compensation_assignment_id=%d AND status='APPROVED'",Stockland_HR_Replacement::table('hrx_compensation_items'),(int)$comp['id']),ARRAY_A);
		foreach((array)$items as $item){
			if(!in_array($item['taxable_status'],array('TAXABLE','EXEMPT'),true)||!in_array($item['pensionable_status'],array('PENSIONABLE','NON_PENSIONABLE'),true))return $this->error('hrx_compensation_classification','Compensation includes an unresolved tax/pension classification and cannot be activated.',409);
		}
		$wp_user_id=isset($body['wp_user_id'])?(int)$body['wp_user_id']:(int)($emp['wp_user_id']??0);
		if($wp_user_id>0){
			$target=get_userdata($wp_user_id);
			if(!$target)return $this->error('hrx_wp_user_not_found','Selected WordPress/ERP account does not exist.',404);
			if(user_can($wp_user_id,'manage_options')||Stockland_Authority::is_owner($wp_user_id))return $this->error('hrx_protected_user','An employee record cannot take ownership of a WordPress administrator or ERP Owner account.',403);
			$existing=Stockland_DB::read_row($wpdb->prepare('SELECT id FROM %i WHERE wp_user_id=%d AND status="ACTIVE" AND id<>%d LIMIT 1',Stockland_HR_Replacement::table('hrx_employments'),$wp_user_id,$id),ARRAY_A);
			if($existing)return $this->error('hrx_wp_user_already_linked','This account is already linked to another active employment record.',409);
		}
		$active_same_person=(int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE person_id=%d AND status='ACTIVE' AND (end_date IS NULL OR end_date>=%s)",Stockland_HR_Replacement::table('hrx_employments'),(int)$emp['person_id'],$emp['start_date']));
		if($active_same_person>0)return $this->error('hrx_duplicate_active_employment','This person already has an active overlapping employment relationship; resolve identity/contract scope first.',409);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start activation transaction.',500);
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_employments'),array('status'=>'ACTIVE','current_assignment_id'=>(int)$assignment['id'],'wp_user_id'=>$wp_user_id?:null,'approved_by'=>get_current_user_id(),'activated_at'=>Stockland_HR_Replacement::now(),'updated_at'=>Stockland_HR_Replacement::now(),'version_no'=>(int)$emp['version_no']+1),array('id'=>$id,'status'=>'PENDING_ACTIVATION'));
		$evt=Stockland_HR_Replacement::approval_event('employment',$id,'EMPLOYMENT_ACTIVATED','PENDING_ACTIVATION','ACTIVE',$note,$key,(int)$contract['private_document_ref_id'],get_current_user_id());
		$audit=Stockland_HR_Replacement::audit('employment_activated','employment',$id,'Activated after verified contract, onboarding, assignment and approved compensation.',(int)($assignment['location_id']??0)?:null,get_current_user_id());
		if(false===$ok||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_activation_failed','Activation and audit were rolled back.',500);}
		$this->commit();
		return rest_ensure_response(array('id'=>$id,'status'=>'ACTIVE','assignment_id'=>(int)$assignment['id'],'contract_id'=>(int)$contract['id'],'wp_user_linked'=>$wp_user_id>0,'message'=>'Employment activated. Compensation and legal rule values remain as approved records; none were generated or inferred.'));
	}

	public function revoke_employment_access( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$id=(int)$request['id'];$body=$this->body($request);$reason=sanitize_textarea_field((string)($body['reason']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));
		if(strlen(trim($reason))<30||''===$key)return $this->error('hrx_access_revocation_evidence','Owner access revocation needs a reason (30+ characters) and idempotency key.',422);
		$emp=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_HR_Replacement::table('hrx_employments'),$id),ARRAY_A);
		if(!$emp)return $this->error('hrx_employment_not_found','Employment relationship not found.',404);
		$uid=(int)($emp['wp_user_id']??0);
		if(!$uid)return $this->error('hrx_no_linked_erp_user','No linked ERP account exists for this employment. There is no user account to revoke.',409);
		if($uid===get_current_user_id())return $this->error('hrx_self_revocation_blocked','The current Owner account cannot revoke itself through an employee exit.',409);
		if(Stockland_Authority::is_owner($uid)||user_can($uid,'manage_options'))return $this->error('hrx_protected_user','Protected WordPress administrator/ERP Owner accounts cannot be revoked through an employee exit. Use the separately controlled administrator procedure.',403);
		$users=Stockland_DB::table('users');$locations=Stockland_DB::table('user_locations');
		$user=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE wp_user_id=%d', $users,$uid),ARRAY_A);
		if(!$user)return $this->error('hrx_erp_user_profile_missing','The linked WordPress account has no Stockland ERP user profile.',409);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start access-revocation transaction.',500);
		$deactivated=Stockland_DB::update_record($users,array('is_active'=>0),array('wp_user_id'=>$uid));
		$scope=Stockland_DB::update_record($locations,array('is_active'=>0,'effective_to'=>current_time('Y-m-d')),array('wp_user_id'=>$uid,'is_active'=>1));
		$employment=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_employments'),array('access_revoked_at'=>Stockland_HR_Replacement::now(),'access_revoked_by'=>get_current_user_id(),'updated_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'wp_user_id'=>$uid));
		$evt=Stockland_HR_Replacement::approval_event('employment',$id,'ERP_ACCESS_REVOKED',strtoupper((string)$emp['status']),strtoupper((string)$emp['status']),$reason,$key,null,get_current_user_id());
		$audit=Stockland_HR_Replacement::audit('erp_access_revoked','employment',$id,'ERP profile disabled and location scopes deactivated; WordPress account retained.');
		if(false===$deactivated||false===$scope||false===$employment||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_access_revocation_failed','Access revocation was rolled back due to failed persistence/audit.',500);}
		$settlement=Stockland_DB::read_row($wpdb->prepare("SELECT id FROM %i WHERE employment_id=%d AND status IN('DRAFT','CALCULATED','APPROVED') ORDER BY id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_exit_settlements'),$id),ARRAY_A);
		if($settlement){$task=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_exit_clearance_tasks'),array('status'=>'COMPLETE','completed_by'=>get_current_user_id(),'completed_at'=>Stockland_HR_Replacement::now(),'resolution_note'=>'ERP profile disabled and location scope deactivated under audited access-revocation workflow.'),array('settlement_id'=>(int)$settlement['id'],'task_code'=>'ACCESS_REVOKED','status'=>'OPEN'));if(false===$task){$this->rollback();return $this->error('hrx_access_task_failed','Access was deactivated but the exit clearance task update failed; investigate before settlement.',500);}}
		$this->commit();
		$wp_user=get_userdata($uid);
		if($wp_user){
			foreach((array)$wp_user->roles as $role){if(in_array($role,Stockland_Roles::supported_roles(),true))$wp_user->remove_role($role);}
			foreach(array('stockland_owner','stockland_install_schema','stockland_approve_purchase_orders','stockland_manage_users','stockland_view_hr','stockland_manage_hr','stockland_view_payroll','stockland_manage_payroll','stockland_view_all_branches','stockland_view_reports','stockland_operate_pos','stockland_manage_inventory') as $cap){$wp_user->remove_cap($cap);}
		}
		return rest_ensure_response(array('id'=>$id,'access_revoked'=>true,'wp_user_id'=>$uid,'account_deleted'=>false,'message'=>'Stockland ERP profile disabled and location assignments deactivated. The WordPress identity remains for retention/audit; confirm the separate account/SSO policy with IT.'));
	}

	public function reconcile_payout_batch( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$id=(int)$request['id'];$body=$this->body($request);$ref=sanitize_text_field((string)($body['external_batch_reference']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));$note=sanitize_textarea_field((string)($body['reconciliation_note']??''));
		if(strlen(trim($ref))<4||strlen(trim($note))<30||''===$key)return $this->error('hrx_payout_reconciliation_evidence','Batch reconciliation requires bank/cash reference, note (30+ characters) and idempotency key.',422);
		$batch=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_HR_Replacement::table('hrx_payout_batches'),$id),ARRAY_A);
		if(!$batch)return $this->error('hrx_payout_batch_not_found','Payout batch not found.',404);
		if('READY'!==strtoupper((string)$batch['status']))return $this->error('hrx_payout_batch_state_invalid','Only a READY payout batch can be reconciled once.',409);
		$summary=Stockland_DB::read_row($wpdb->prepare("SELECT COUNT(*) total,SUM(CASE WHEN status='SETTLED' THEN 1 ELSE 0 END) settled,SUM(CASE WHEN status IN('FAILED','RETURNED') THEN 1 ELSE 0 END) failed,COALESCE(SUM(CASE WHEN status='SETTLED' THEN amount ELSE 0 END),0) amount FROM %i WHERE payout_batch_id=%d",Stockland_HR_Replacement::table('hrx_payout_items'),$id),ARRAY_A);
		if(!$summary||(int)$summary['total']<1||(int)$summary['settled']!==(int)$summary['total']||(int)$summary['failed']>0||Stockland_Money::minor($summary['amount'])!==Stockland_Money::minor($batch['batch_total']))return $this->error('hrx_payout_items_unreconciled','Every individual payout must be SETTLED and the total must equal the approved batch amount.',409);
		$run=$this->run_row((int)$batch['payroll_run_id']);if(is_wp_error($run))return $run;
		$config=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_accounting_config'),$run['period_end'],$run['period_start']),ARRAY_A);
		if(!$config)return $this->error('hrx_gl_mapping_missing','Approved effective-dated GL account mapping is required to reconcile payout.',409);
		$journal_lines=array(
			array('account_code'=>$config['net_payable_account'],'debit'=>$batch['batch_total'],'credit'=>'0.00','memo'=>'Settlement of HRX payroll '.$run['run_code']),
			array('account_code'=>$config['cash_account_code'],'debit'=>'0.00','credit'=>$batch['batch_total'],'memo'=>'Bank/cash payout '.$ref)
		);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start payout reconciliation transaction.',500);
		$journal=Stockland_Accounting::post_journal($run['period_end'],'HRX_PAYOUT_BATCH',$id,'HRX payroll payout '.$batch['batch_code'],$journal_lines,get_current_user_id());
		if(!$journal){$this->rollback();return $this->error('hrx_payout_journal_failed','Payout settlement journal was not posted; batch remains unreconciled.',500);}
		$link=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_accounting_links'),array('source_type'=>'HRX_PAYOUT_BATCH','source_id'=>$id,'journal_id'=>(int)$journal,'journal_hash'=>hash('sha256',wp_json_encode($journal_lines)),'posted_at'=>Stockland_HR_Replacement::now(),'posted_by'=>get_current_user_id()));
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payout_batches'),array('status'=>'RECONCILED','external_batch_reference'=>$ref,'reconciled_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'status'=>'READY'));
		$ok2=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payroll_runs'),array('status'=>'SETTLED','payout_state'=>'SETTLED','updated_at'=>Stockland_HR_Replacement::now()),array('id'=>(int)$batch['payroll_run_id'],'status'=>'SETTLEMENT_PENDING','payout_state'=>'PENDING'));
		$evt=Stockland_HR_Replacement::approval_event('payout-batch',$id,'PAYOUT_BATCH_RECONCILED','READY','RECONCILED',$note,$key,null,get_current_user_id());
		$audit=Stockland_HR_Replacement::audit('payout_batch_reconciled','payout-batch',$id,'Bank/cash evidence confirmed; payout journal posted.');
		if(false===$link||false===$ok||false===$ok2||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_payout_reconciliation_failed','Payout reconciliation was rolled back.',500);}
		$this->commit();return rest_ensure_response(array('batch_id'=>$id,'status'=>'RECONCILED','journal_id'=>(int)$journal,'message'=>'Payout batch reconciled to bank/cash and net-payable GL account.'));
	}

	public function create_remittance( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$run_id=(int)$request['id'];$body=$this->body($request);$obligation_id=(int)($body['obligation_id']??0);
		$amount=$body['amount']??null;$date=Stockland_HR_Replacement::valid_date($body['payment_date']??'');$gov=sanitize_text_field((string)($body['government_reference']??''));
		if($obligation_id<1||null===$amount||!$date||strlen(trim($gov))<4)return $this->error('hrx_remittance_input_invalid','Obligation, amount, payment date and government reference are required.',422);
		try{$money=Stockland_Money::decimal(Stockland_Money::minor($amount));}catch(Throwable $e){return $this->error('hrx_remittance_amount_invalid','Remittance amount is invalid.',422);}
		if(Stockland_Money::minor($money)<=0)return $this->error('hrx_remittance_amount_invalid','Remittance amount must be greater than zero.',422);
		$run=$this->run_row($run_id);if(is_wp_error($run))return $run;
		$obligation=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d AND payroll_run_id=%d',Stockland_HR_Replacement::table('hrx_statutory_obligations'),$obligation_id,$run_id),ARRAY_A);
		if(!$obligation)return $this->error('hrx_obligation_not_found','Statutory obligation does not belong to this payroll run.',404);
		if(!in_array($obligation['status'],array('OPEN','PARTIALLY_SETTLED'),true))return $this->error('hrx_obligation_state_invalid','This statutory obligation is not open for remittance.',409);
		$paid=Stockland_DB::read_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM %i WHERE obligation_id=%d AND status='RECONCILED'",Stockland_HR_Replacement::table('hrx_remittances'),$obligation_id));
		$remaining=Stockland_Money::subtract($obligation['amount_due'],$paid);
		if(Stockland_Money::minor($money)>Stockland_Money::minor($remaining))return $this->error('hrx_remittance_overpay','Remittance amount exceeds the remaining statutory obligation.',409);
		$code='RMX-'.gmdate('Ymd').'-'.strtoupper(wp_generate_password(10,false,false));
		$row=array('remittance_code'=>$code,'obligation_id'=>$obligation_id,'amount'=>$money,'payment_date'=>$date,'government_reference'=>$gov,'status'=>'SUBMITTED','prepared_by'=>get_current_user_id(),'created_at'=>Stockland_HR_Replacement::now());
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start remittance transaction.',500);
		if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_remittances'),$row)){$this->rollback();return $this->error('hrx_remittance_create_failed','Remittance record could not be created.',409);}
		$id=(int)$wpdb->insert_id;
		$evt=Stockland_HR_Replacement::approval_event('remittance',$id,'REMITTANCE_SUBMITTED',null,'SUBMITTED','Remittance evidence submitted for independent verification',$code);
		$audit=Stockland_HR_Replacement::audit('remittance_submitted','remittance',$id,'Remittance pending receipt verification and accounting reconciliation.');
		if(is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_remittance_audit_failed','Remittance creation was rolled back due to audit failure.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'remittance_code'=>$code,'status'=>'SUBMITTED','message'=>'Upload the official tax/pension receipt to this remittance record, have it independently reviewed, then run Verify & Post.'));
	}

	public function verify_remittance( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$id=(int)$request['id'];$body=$this->body($request);$key=sanitize_text_field((string)($body['idempotency_key']??''));$note=sanitize_textarea_field((string)($body['verification_note']??''));
		if(!$key||strlen(trim($note))<30)return $this->error('hrx_remittance_verification_evidence','Independent remittance verification requires a note (30+ characters) and idempotency key.',422);
		$rem=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_HR_Replacement::table('hrx_remittances'),$id),ARRAY_A);if(!$rem)return $this->error('hrx_remittance_not_found','Remittance not found.',404);
		if('SUBMITTED'!==strtoupper((string)$rem['status']))return $this->error('hrx_remittance_state_invalid','Only a submitted remittance can be verified.',409);
		if((int)$rem['prepared_by']===get_current_user_id())return $this->error('hrx_no_self_approval','The preparer cannot verify their own statutory remittance.',403);
		$obligation=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_statutory_obligations'),(int)$rem['obligation_id']),ARRAY_A);if(!$obligation)return $this->error('hrx_obligation_not_found','Statutory obligation no longer exists.',409);
		$expected='PAYE'===$obligation['obligation_type']?'TAX_REMITTANCE_RECEIPT':'PENSION_REMITTANCE_RECEIPT';
		$doc_id=(int)($body['receipt_document_ref_id']??$rem['receipt_document_ref_id']??0);
		$doc=$doc_id?Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND entity_type='REMITTANCE' AND entity_id=%d AND document_type=%s AND approval_status='APPROVED'",Stockland_HR_Replacement::table('hrx_document_refs'),$doc_id,$id,$expected),ARRAY_A):null;
		if(!$doc)return $this->error('hrx_remittance_receipt_missing','An independently approved private official remittance receipt is required.',409);
		$run=$this->run_row((int)$obligation['payroll_run_id']);if(is_wp_error($run))return $run;
		$config=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_accounting_config'),$rem['payment_date'],$rem['payment_date']),ARRAY_A);if(!$config)return $this->error('hrx_gl_mapping_missing','Approved effective-dated GL mapping is required for remittance posting.',409);
		$type=strtoupper((string)$obligation['obligation_type']);
		$payable=array('PAYE'=>$config['paye_payable_account'],'EMPLOYEE_PENSION'=>$config['employee_pension_payable_account'],'EMPLOYER_PENSION'=>$config['employer_pension_payable_account']);
		if(empty($payable[$type]))return $this->error('hrx_obligation_type_unsupported','This statutory obligation has no reviewed GL mapping.',409);
		$paid_before=Stockland_DB::read_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM %i WHERE obligation_id=%d AND status='RECONCILED'",Stockland_HR_Replacement::table('hrx_remittances'),(int)$rem['obligation_id']));
		if($wpdb->last_error||null===$paid_before)return $this->error('hrx_remittance_reconcile_read_failed','Could not verify previously reconciled remittances.',500);
		$paid_with_current=Stockland_Money::add($paid_before,$rem['amount']);
		if(Stockland_Money::minor($paid_with_current)>Stockland_Money::minor($obligation['amount_due']))return $this->error('hrx_remittance_overpayment','This remittance would exceed the outstanding statutory obligation; correct the amount before verification.',409);
		$lines=array(
			array('account_code'=>$payable[$type],'debit'=>$rem['amount'],'credit'=>'0.00','memo'=>'Remittance '.$rem['remittance_code'].' clearing payable'),
			array('account_code'=>$config['cash_account_code'],'debit'=>'0.00','credit'=>$rem['amount'],'memo'=>'Statutory payment '.$rem['government_reference'])
		);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start remittance verification transaction.',500);
		$journal=Stockland_Accounting::post_journal($rem['payment_date'],'HRX_REMITTANCE',$id,'HRX remittance '.$rem['remittance_code'],$lines,get_current_user_id());
		if(!$journal){$this->rollback();return $this->error('hrx_remittance_journal_failed','Remittance accounting journal did not post.',500);}
		$link=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_accounting_links'),array('source_type'=>'HRX_REMITTANCE','source_id'=>$id,'journal_id'=>(int)$journal,'journal_hash'=>hash('sha256',wp_json_encode($lines)),'posted_at'=>Stockland_HR_Replacement::now(),'posted_by'=>get_current_user_id()));
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_remittances'),array('status'=>'RECONCILED','verified_by'=>get_current_user_id(),'verified_at'=>Stockland_HR_Replacement::now(),'journal_id'=>(int)$journal,'payment_reference'=>sanitize_text_field((string)($body['payment_reference']??$rem['government_reference'])), 'receipt_document_ref_id'=>$doc_id),array('id'=>$id,'status'=>'SUBMITTED','prepared_by'=>(int)$rem['prepared_by']));
		$paid=Stockland_DB::read_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM %i WHERE obligation_id=%d AND status='RECONCILED'",Stockland_HR_Replacement::table('hrx_remittances'),(int)$rem['obligation_id']));
		if($wpdb->last_error||null===$paid){$this->rollback();return $this->error('hrx_remittance_reconcile_read_failed','Could not verify the reconciled remittance total; posting was rolled back.',500);}
		$paid=Stockland_Money::decimal(Stockland_Money::minor($paid));
		$ob_status=Stockland_Money::minor($paid)>=Stockland_Money::minor($obligation['amount_due'])?'RECONCILED':'PARTIALLY_SETTLED';
		$ok2=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_statutory_obligations'),array('status'=>$ob_status),array('id'=>(int)$rem['obligation_id']));
		$evt=Stockland_HR_Replacement::approval_event('remittance',$id,'REMITTANCE_RECONCILED','SUBMITTED','RECONCILED',$note,$key,$doc_id,get_current_user_id());
		$audit=Stockland_HR_Replacement::audit('remittance_reconciled','remittance',$id,'Official receipt verified and statutory payable cleared to bank/cash.');
		if(false===$link||false===$ok||false===$ok2||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_remittance_verify_failed','Verification and posting were rolled back.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'status'=>'RECONCILED','journal_id'=>(int)$journal,'obligation_status'=>$ob_status));
	}

		public function verify_payee_in_kind( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$id=(int)$request['id'];$body=$this->body($request);
		$ref=sanitize_text_field((string)($body['stock_transaction_reference']??''));$doc_id=(int)($body['valuation_document_id']??0);$note=sanitize_textarea_field((string)($body['verification_note']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));
		if(!preg_match('/^[A-Za-z0-9_\-]{2,30}:[A-Za-z0-9_\-]{1,80}$/',$ref)||$doc_id<1||strlen(trim($note))<30||!$key)return $this->error('hrx_in_kind_evidence_required','Provide a posted Stockland stock transaction reference in TYPE:REFERENCE format, approved valuation evidence, a verification note and idempotency key.',422);
		$req=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_payee_requests'),$id),ARRAY_A);if(!$req)return $this->error('hrx_payee_request_not_found','Payee request not found.',404);
		if(Stockland_Money::minor($req['in_kind_value'])<=0)return $this->error('hrx_in_kind_not_present','This payee request does not have a positive in-kind component.',409);
		if(!in_array($req['status'],array('DRAFT','LEGAL_TAX_REVIEW','NEEDS_INFORMATION'),true))return $this->error('hrx_payee_locked','In-kind consideration cannot be changed after submission.',409);
		$parts=explode(':',$ref,2);
		if ( ! in_array( strtoupper( $parts[0] ), array( 'PAYEE_REQUEST', 'HRX_PAYEE_REQUEST' ), true ) || (string) (int) $parts[1] !== (string) $id ) {
			return $this->error( 'hrx_stock_issue_not_bound_to_request', 'The stock ledger reference must identify this exact payee request; unrelated stock issues are not accepted.', 409 );
		}
		$stock_ref=Stockland_DB::read_row($wpdb->prepare("SELECT COUNT(*) movements,COUNT(DISTINCT product_id) products,COALESCE(SUM(quantity_signed),0) quantity_delta FROM %i WHERE reference_type=%s AND reference_id=%s AND quantity_signed<0",Stockland_DB::table('transactions'),$parts[0],$parts[1]),ARRAY_A);
		if(!$stock_ref||(int)$stock_ref['movements']<1||(int)$stock_ref['products']<1||Stockland_Money::minor($stock_ref['quantity_delta'])>=0)return $this->error('hrx_stock_issue_not_found','The request-bound reference does not resolve to posted negative Stockland stock movements.',409);
		$valuation=Stockland_DB::read_row($wpdb->prepare("SELECT id FROM %i WHERE id=%d AND entity_type='PAYEE_REQUEST' AND entity_id=%d AND document_type='COMPENSATION_APPROVAL' AND approval_status='APPROVED'",Stockland_HR_Replacement::table('hrx_document_refs'),$doc_id,$id),ARRAY_A);
		if(!$valuation)return $this->error('hrx_in_kind_valuation_missing','Attach an independently approved private valuation/approval record to this payee request.',409);
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payee_requests'),array('in_kind_stock_reference'=>$ref,'in_kind_posting_verified'=>1,'updated_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'status'=>$req['status']));
		if(false===$ok)return $this->error('hrx_in_kind_verify_failed','Stock issue reference could not be bound to the payee request.',500);
		$evt=Stockland_HR_Replacement::approval_event('payee-request',$id,'IN_KIND_VERIFIED',$req['status'],$req['status'],$note,$key,$doc_id,get_current_user_id());
		$audit=Stockland_HR_Replacement::audit('payee_in_kind_verified','payee-request',$id,'Verified existing negative stock transaction and approved valuation evidence; no inventory was issued by this endpoint.');
		if(is_wp_error($evt)||is_wp_error($audit)||false===$audit)return $this->error('hrx_in_kind_audit_failed','In-kind evidence changed but audit failed; block payee payment pending investigation.',500);
		return rest_ensure_response(array('id'=>$id,'in_kind_posting_verified'=>true,'stock_reference'=>$ref,'valuation_document_id'=>$doc_id,'message'=>'Existing inventory ledger evidence and approved valuation were linked; this endpoint did not mutate stock.'));
	}

	private function approved_payee_engagement( $payee_request ) {
		global $wpdb;
		$engagement_id=(int)($payee_request['engagement_id']??0);
		$engagement=$engagement_id>0?Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND status='APPROVED'",Stockland_HR_Replacement::table('hrx_payee_engagements'),$engagement_id),ARRAY_A):null;
		if(!$engagement)return $this->error('hrx_payee_engagement_unapproved','An existing independently approved payee engagement is required before calculation, approval or payment.',409);
		$start=Stockland_HR_Replacement::valid_date($payee_request['service_period_start']??'');
		$end=Stockland_HR_Replacement::valid_date($payee_request['service_period_end']??'');
		if(!$start||!$end||$start>$end)return $this->error('hrx_payee_service_period_invalid','A valid service-period start and end are required.',422);
		if($start<(string)$engagement['start_date']||(!empty($engagement['end_date'])&&$end>(string)$engagement['end_date']))return $this->error('hrx_payee_service_period_outside_engagement','The requested service period falls outside the approved engagement dates.',409);
		return $engagement;
	}

	public function calculate_payee_request( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$id=(int)$request['id'];$body=$this->body($request);
		$req=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_HR_Replacement::table('hrx_payee_requests'),$id),ARRAY_A);if(!$req)return $this->error('hrx_payee_request_not_found','Payee request not found.',404);
		if(!in_array($req['status'],array('DRAFT','LEGAL_TAX_REVIEW','NEEDS_INFORMATION'),true))return $this->error('hrx_payee_state_invalid','Only an editable payee request may be calculated.',409);
		$engagement=$this->approved_payee_engagement($req);if(is_wp_error($engagement))return $engagement;
		$class=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE payee_request_id=%d AND status='APPROVED' ORDER BY id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_payee_classifications'),$id),ARRAY_A);
		if(!$class||empty($class['rule_id']))return $this->error('hrx_payee_classification_required','An independently approved legal/tax classification is required before calculation.',409);
		$rule=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND review_status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s)",Stockland_HR_Replacement::table('hrx_legal_rules'),(int)$class['rule_id'],$req['service_period_end']?:current_time('Y-m-d'),$req['service_period_start']?:current_time('Y-m-d')),ARRAY_A);
		if(!$rule||'PAYEE_WITHHOLDING'!==strtoupper((string)$rule['rule_family'])||'RATE_ON_TOTAL_CONSIDERATION'!==strtoupper((string)$rule['formula_key']))return $this->error('hrx_payee_rule_invalid','A currently effective approved PAYEE_WITHHOLDING rule is required.',409);
		$params=json_decode((string)$rule['formula_parameters'],true);if(!is_array($params)||!isset($params['rate'])||'HALF_AWAY_FROM_ZERO_CENT'!==($params['rounding_method']??''))return $this->error('hrx_payee_rule_parameters','Approved payee rule lacks a valid rate/rounding method.',409);
		if(Stockland_Money::minor($req['in_kind_value'])>0&&((int)$req['in_kind_posting_verified']!==1||empty($req['in_kind_stock_reference'])))return $this->error('hrx_in_kind_stock_evidence_missing','In-kind consideration requires verified Stockland stock-transaction source evidence before tax calculation.',409);
		$deliverables=Stockland_DB::read_row($wpdb->prepare("SELECT COUNT(*) total,SUM(CASE WHEN status='ACCEPTED' THEN 1 ELSE 0 END) accepted FROM %i WHERE payee_request_id=%d",Stockland_HR_Replacement::table('hrx_payee_deliverables'),$id),ARRAY_A);
		if(!$deliverables||(int)$deliverables['total']<1||(int)$deliverables['accepted']!==(int)$deliverables['total'])return $this->error('hrx_payee_deliverables_missing','All contract deliverables must be accepted by authorized staff before payment approval.',409);
		$cash=Stockland_Money::decimal(Stockland_Money::minor($req['cash_amount']));$inkind=Stockland_Money::decimal(Stockland_Money::minor($req['in_kind_value']));
		$gross=Stockland_Money::add($cash,$inkind);
		try{$withholding=Stockland_HR_Replacement_Calculator::percentage($gross,$params['rate']);}catch(Throwable $e){return $this->error('hrx_payee_rate_invalid','The approved payee withholding rate is malformed.',409);}
		if(Stockland_Money::minor($withholding)>Stockland_Money::minor($cash))return $this->error('hrx_payee_withholding_exceeds_cash','Withholding exceeds the cash consideration. Hold for qualified tax review; do not create negative cash payment.',409);
		$net=Stockland_Money::subtract($cash,$withholding);
		$update=array('gross_amount'=>$gross,'withholding_amount'=>$withholding,'net_amount'=>$net,'withholding_rule_id'=>(int)$rule['id'],'status'=>'SUBMITTED','classification_status'=>'APPROVED','updated_at'=>Stockland_HR_Replacement::now(),'version_no'=>(int)$req['version_no']+1);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start payee calculation transaction.',500);
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payee_requests'),$update,array('id'=>$id,'status'=>$req['status'],'classification_status'=>'APPROVED'));
		$evt=Stockland_HR_Replacement::approval_event('payee-request',$id,'PAYEE_CALCULATED',$req['status'],'SUBMITTED','Rule ID '.$rule['id'].'; source hash '.$rule['source_sha256'],wp_generate_uuid4());
		$audit=Stockland_HR_Replacement::audit('payee_calculated','payee-request',$id,'Cash and in-kind consideration calculated with approved rule; no payment was made.');
		if(false===$ok||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_payee_calculation_failed','Payee calculation/audit was rolled back.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'status'=>'SUBMITTED','cash_amount'=>$cash,'in_kind_value'=>$inkind,'gross_consideration'=>$gross,'withholding_amount'=>$withholding,'net_cash_payable'=>$net,'rule_code'=>$rule['rule_code'],'rule_version'=>(int)$rule['version_no'],'rule_source_sha256'=>$rule['source_sha256']));
	}

	public function pay_payee_request( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$id=(int)$request['id'];$body=$this->body($request);$ref=sanitize_text_field((string)($body['external_reference']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));$note=sanitize_textarea_field((string)($body['payment_note']??''));$doc_id=(int)($body['evidence_document_id']??0);
		if(strlen(trim($ref))<4||!$key||strlen(trim($note))<20)return $this->error('hrx_payee_payment_evidence','Payee settlement requires a unique external payment ref, note and idempotency key.',422);
		$req=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_HR_Replacement::table('hrx_payee_requests'),$id),ARRAY_A);if(!$req)return $this->error('hrx_payee_request_not_found','Payee request not found.',404);
		$engagement=$this->approved_payee_engagement($req);if(is_wp_error($engagement))return $engagement;
		if('APPROVED'!==strtoupper((string)$req['status']))return $this->error('hrx_payee_not_approved','Only an independently approved AP request can be paid.',409);
		if((int)$req['approved_by']===get_current_user_id())return $this->error('hrx_no_self_payment','The AP approver cannot record the payout as an independent settlement.',403);
		$doc=$doc_id?Stockland_DB::read_row($wpdb->prepare("SELECT id FROM %i WHERE id=%d AND entity_type='PAYEE_REQUEST' AND entity_id=%d AND approval_status='APPROVED' AND document_type IN('PAYEE_DELIVERABLE','OTHER_HR')",Stockland_HR_Replacement::table('hrx_document_refs'),$doc_id,$id),ARRAY_A):null;
		if(!$doc)return $this->error('hrx_payee_payment_receipt_missing','An independently approved private payout receipt or accepted deliverable reference is required.',409);
		if(Stockland_Money::minor($req['net_amount'])<=0)return $this->error('hrx_payee_net_invalid','Net payee amount must be greater than zero to record a payout.',409);
		$config=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_accounting_config'),$req['service_period_end']?:current_time('Y-m-d'),$req['service_period_start']?:current_time('Y-m-d')),ARRAY_A);if(!$config)return $this->error('hrx_gl_mapping_missing','Approved effective-dated central AP GL mapping is required.',409);
		$code='PAYX-'.gmdate('Ymd').'-'.strtoupper(wp_generate_password(10,false,false));
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start payee settlement transaction.',500);
		$payment=array('payment_code'=>$code,'payee_request_id'=>$id,'amount'=>$req['net_amount'],'method'=>strtoupper(sanitize_key($body['method']??'BANK_TRANSFER')),'external_reference'=>$ref,'evidence_ref_id'=>$doc_id,'status'=>'SETTLED','idempotency_key'=>$key,'created_by'=>(int)$req['created_by'],'paid_by'=>get_current_user_id(),'paid_at'=>Stockland_HR_Replacement::now(),'created_at'=>Stockland_HR_Replacement::now());
		if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_payee_payments'),$payment)){$this->rollback();return $this->error('hrx_payee_payment_duplicate','A payment for this request/reference already exists. No duplicate payment was recorded.',409);}
		$payment_id=(int)$wpdb->insert_id;
		$lines=array(
			array('account_code'=>$config['payee_payable_account'],'debit'=>$req['net_amount'],'credit'=>'0.00','memo'=>'Payee settlement '.$code),
			array('account_code'=>$config['cash_account_code'],'debit'=>'0.00','credit'=>$req['net_amount'],'memo'=>'Payee payment '.$ref)
		);
		$journal=Stockland_Accounting::post_journal(current_time('Y-m-d'),'HRX_PAYEE_PAYMENT',$payment_id,'Central payee payment '.$code,$lines,get_current_user_id());
		if(!$journal){$this->rollback();return $this->error('hrx_payee_payment_journal_failed','Payee payment journal failed; settlement rolled back.',500);}
		$link=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_accounting_links'),array('source_type'=>'HRX_PAYEE_PAYMENT','source_id'=>$payment_id,'journal_id'=>(int)$journal,'journal_hash'=>hash('sha256',wp_json_encode($lines)),'posted_at'=>Stockland_HR_Replacement::now(),'posted_by'=>get_current_user_id()));
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payee_payments'),array('journal_id'=>(int)$journal),array('id'=>$payment_id,'status'=>'SETTLED'));
		$ok2=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payee_requests'),array('status'=>'PAID','updated_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'status'=>'APPROVED'));
		$evt=Stockland_HR_Replacement::approval_event('payee-request',$id,'PAYEE_PAID','APPROVED','PAID',$note,$key,$doc_id,get_current_user_id());
		$audit=Stockland_HR_Replacement::audit('payee_paid','payee-request',$id,'Central AP payment, receipt and GL journal linked.');
		if(false===$link||false===$ok||false===$ok2||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_payee_payment_failed','Payee payment and journal link were rolled back.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'payment_id'=>$payment_id,'status'=>'PAID','journal_id'=>(int)$journal));
	}

	public function pay_exit_settlement( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$id=(int)$request['id'];$body=$this->body($request);$ref=sanitize_text_field((string)($body['external_reference']??''));$method=strtoupper(sanitize_key($body['method']??'BANK_TRANSFER'));$key=sanitize_text_field((string)($body['idempotency_key']??''));$note=sanitize_textarea_field((string)($body['payment_note']??''));$doc_id=(int)($body['evidence_document_id']??0);
		if(strlen(trim($ref))<4||!in_array($method,array('BANK_TRANSFER','CASH','CHEQUE'),true)||!$key||strlen(trim($note))<30)return $this->error('hrx_exit_payment_evidence','Final settlement payout requires method, external reference, note and idempotency key.',422);
		$exit=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_HR_Replacement::table('hrx_exit_settlements'),$id),ARRAY_A);if(!$exit)return $this->error('hrx_exit_not_found','Final settlement not found.',404);
		if('APPROVED'!==strtoupper((string)$exit['status']))return $this->error('hrx_exit_not_approved','Only an approved final settlement may be paid.',409);
		if(empty($exit['journal_id']))return $this->error('hrx_exit_accrual_missing','A final settlement accrual journal must exist before payment.',409);
		if((int)$exit['approved_by']===get_current_user_id())return $this->error('hrx_no_self_payment','The settlement approver cannot record the payment as the independent payer.',403);
		$doc=$doc_id?Stockland_DB::read_row($wpdb->prepare("SELECT id FROM %i WHERE id=%d AND entity_type='EXIT_SETTLEMENT' AND entity_id=%d AND document_type='FINAL_SETTLEMENT' AND approval_status='APPROVED'",Stockland_HR_Replacement::table('hrx_document_refs'),$doc_id,$id),ARRAY_A):null;
		if(!$doc)return $this->error('hrx_exit_payment_receipt_missing','An independently reviewed private final-settlement payment receipt is required.',409);
		$tasks=(int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE settlement_id=%d AND status<>'COMPLETE'",Stockland_HR_Replacement::table('hrx_exit_clearance_tasks'),$id));if($tasks>0)return $this->error('hrx_exit_clearance_incomplete','Complete all handover, equipment, stock/finance, archive and access-revocation tasks before final settlement payout.',409);
		$emp=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_employments'),(int)$exit['employment_id']),ARRAY_A);if(!$emp)return $this->error('hrx_employment_not_found','Employment relationship not found.',404);
		if('ACTIVE'!==strtoupper((string)$emp['status'])&&'EXIT_PENDING'!==strtoupper((string)$emp['status']))return $this->error('hrx_employment_exit_state','Employment must be active or approved for exit before final settlement payment.',409);
		$config=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_accounting_config'),$exit['effective_end_date'],$exit['effective_end_date']),ARRAY_A);if(!$config)return $this->error('hrx_gl_mapping_missing','Approved GL account mapping is required to settle final pay.',409);
		$code='EXPPX-'.gmdate('Ymd').'-'.strtoupper(wp_generate_password(10,false,false));
		if(false=== $this->begin())return $this->error('hrx_tx_begin_failed','Could not start exit payout transaction.',500);
		$pay=array('payout_code'=>$code,'settlement_id'=>$id,'amount'=>$exit['net_final_amount'],'method'=>$method,'external_reference'=>$ref,'evidence_ref_id'=>$doc_id,'status'=>'SETTLED','idempotency_key'=>$key,'created_by'=>(int)$exit['created_by'],'paid_by'=>get_current_user_id(),'paid_at'=>Stockland_HR_Replacement::now(),'created_at'=>Stockland_HR_Replacement::now());
		if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_exit_payouts'),$pay)){$this->rollback();return $this->error('hrx_exit_payout_duplicate','An exit payment with this reference/idempotency key already exists.',409);}
		$pay_id=(int)$wpdb->insert_id;
		$lines=array(array('account_code'=>$config['net_payable_account'],'debit'=>$exit['net_final_amount'],'credit'=>'0.00','memo'=>'Final settlement '.$exit['settlement_code']),array('account_code'=>$config['cash_account_code'],'debit'=>'0.00','credit'=>$exit['net_final_amount'],'memo'=>'Final settlement payout '.$ref));
		$journal=Stockland_Accounting::post_journal($exit['effective_end_date'],'HRX_EXIT_PAYOUT',$pay_id,'Final settlement payment '.$code,$lines,get_current_user_id());
		if(!$journal){$this->rollback();return $this->error('hrx_exit_payout_journal_failed','Final payout journal failed; no termination status was committed.',500);}
		$link=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_accounting_links'),array('source_type'=>'HRX_EXIT_PAYOUT','source_id'=>$pay_id,'journal_id'=>(int)$journal,'journal_hash'=>hash('sha256',wp_json_encode($lines)),'posted_at'=>Stockland_HR_Replacement::now(),'posted_by'=>get_current_user_id()));
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_exit_payouts'),array('journal_id'=>(int)$journal),array('id'=>$pay_id,'status'=>'SETTLED'));
		$attempt=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_exit_payout_attempts'),array('payout_id'=>$pay_id,'attempt_no'=>1,'attempt_status'=>'SETTLED','external_reference'=>$ref,'evidence_ref_id'=>$doc_id,'attempted_at'=>Stockland_HR_Replacement::now(),'outcome_at'=>Stockland_HR_Replacement::now(),'created_by'=>get_current_user_id()));
		$ok2=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_exit_settlements'),array('status'=>'SETTLED','payout_journal_id'=>(int)$journal,'settled_by'=>get_current_user_id(),'settled_at'=>Stockland_HR_Replacement::now(),'updated_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'status'=>'APPROVED'));
		$ok3=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_employments'),array('status'=>'TERMINATED','end_date'=>$exit['effective_end_date'],'updated_at'=>Stockland_HR_Replacement::now()),array('id'=>(int)$emp['id'],'status'=>$emp['status']));
		$evt=Stockland_HR_Replacement::approval_event('exit-settlement',$id,'EXIT_PAYOUT_SETTLED','APPROVED','SETTLED',$note,$key,$doc_id,get_current_user_id());
		$audit=Stockland_HR_Replacement::audit('exit_payout_settled','exit-settlement',$id,'Final settlement payment posted and employment termination date applied.');
		if(false===$link||false===$ok||false===$attempt||false===$ok2||false===$ok3||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_exit_payout_failed','Exit payout and finalization were rolled back.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'payout_id'=>$pay_id,'status'=>'SETTLED','journal_id'=>(int)$journal,'message'=>'Final settlement paid and employment ended. ERP account access revocation must be separately confirmed.'));
	}

	public function close_payroll_run( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;$id=(int)$request['id'];$run=$this->run_row($id);if(is_wp_error($run))return $run;
		$body=$this->body($request);$note=sanitize_textarea_field((string)($body['reconciliation_note']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));
		if(!in_array($run['status'],array('SETTLEMENT_PENDING','SETTLED'),true)||strlen(trim($note))<30||!$key)return $this->error('hrx_close_evidence_required','Close requires settlement state, bank/payout and remittance reconciliation evidence, and an idempotency key.',422);
		$batch_summary=Stockland_DB::read_row($wpdb->prepare("SELECT COUNT(*) total,SUM(CASE WHEN status='RECONCILED' THEN 1 ELSE 0 END) reconciled FROM %i WHERE payroll_run_id=%d",Stockland_HR_Replacement::table('hrx_payout_batches'),$id),ARRAY_A);
		if(!$batch_summary||$wpdb->last_error||(int)$batch_summary['total']<1||(int)$batch_summary['reconciled']!==(int)$batch_summary['total'])return $this->error('hrx_payout_batch_reconciliation_incomplete','Every payout batch must be reconciled to bank/cash and the net-payable journal before the payroll period can close.',409);
		$unlinked=(int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i b LEFT JOIN %i l ON l.source_id=b.id AND l.source_type='HRX_PAYOUT_BATCH' WHERE b.payroll_run_id=%d AND l.id IS NULL",Stockland_HR_Replacement::table('hrx_payout_batches'),Stockland_HR_Replacement::table('hrx_accounting_links'),$id));
		if($wpdb->last_error||$unlinked>0)return $this->error('hrx_payout_journal_link_missing','Every reconciled payout batch must have its own accounting journal link before close.',409);
		$payout=Stockland_DB::read_row($wpdb->prepare("SELECT COUNT(*) total,SUM(CASE WHEN status='SETTLED' THEN 1 ELSE 0 END) settled,SUM(CASE WHEN status IN ('FAILED','RETURNED') THEN 1 ELSE 0 END) failed FROM %i WHERE payout_batch_id IN (SELECT id FROM %i WHERE payroll_run_id=%d)",Stockland_HR_Replacement::table('hrx_payout_items'),Stockland_HR_Replacement::table('hrx_payout_batches'),$id),ARRAY_A);
		if(!$payout||(int)$payout['total']===0||(int)$payout['settled']!==(int)$payout['total']||(int)$payout['failed']>0)return $this->error('hrx_payout_reconciliation_incomplete','Every employee payout must be individually settled. Failed/returned payments need a resolved recovery workflow before close.',409);
		$open= (int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE payroll_run_id=%d AND status NOT IN('RECONCILED','NOT_REQUIRED')",Stockland_HR_Replacement::table('hrx_statutory_obligations'),$id));
		if($open>0)return $this->error('hrx_remittance_unreconciled','All statutory obligations must have verified, reconciled remittance evidence before period close.',409);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start close transaction.',500);
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payroll_runs'),array('status'=>'CLOSED','payout_state'=>'RECONCILED','closed_by'=>get_current_user_id(),'closed_at'=>Stockland_HR_Replacement::now(),'updated_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'status'=>$run['status']));
		$evt=Stockland_HR_Replacement::approval_event('payroll-run',$id,'CLOSED',$run['status'],'CLOSED',$note,$key,null,get_current_user_id());$audit=Stockland_HR_Replacement::audit('payroll_closed','payroll-run',$id,'All employee payouts and statutory remittances reconciled.');
		if(false===$ok||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_close_failed','Close was rolled back.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'status'=>'CLOSED'));
	}

	public function review_legal_rule( $request ) {
		global $wpdb;$id=(int)$request['id'];$body=$this->body($request);
		$rule=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_legal_rules'),$id),ARRAY_A);if(!$rule)return $this->error('hrx_rule_not_found','Rule not found.',404);
		$decision=strtoupper(sanitize_key($body['decision']??''));$note=sanitize_textarea_field((string)($body['review_note']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));
		if(!in_array($decision,array('APPROVE','RETURN_FOR_REVIEW'),true)||strlen(trim($note))<40||!$key)return $this->error('hrx_rule_review_evidence_required','Decision, detailed qualified reviewer note (40+ characters), and idempotency key required.',422);
		if((int)$rule['created_by']===get_current_user_id())return $this->error('hrx_no_self_approval','Legal rule creator cannot approve their own rule.',403);
		$reviewer_name=sanitize_text_field((string)($body['reviewer_name']??''));
		$reviewer_qualification=sanitize_text_field((string)($body['reviewer_qualification']??$rule['reviewer_qualification']??''));
		if(('APPROVE'===$decision&&(''===$reviewer_name||strlen($reviewer_qualification)<8))||empty($rule['source_sha256'])||!preg_match('/^[a-f0-9]{64}$/i',(string)$rule['source_sha256'])||empty($rule['test_vectors_sha256'])||!preg_match('/^[a-f0-9]{64}$/i',(string)$rule['test_vectors_sha256']))return $this->error('hrx_rule_evidence_missing','A qualified reviewer identity/qualification, valid official-source SHA-256 and valid test-vector SHA-256 are required.',422);
		$review_doc_id=(int)($body['review_evidence_document_id']??0);
		if('APPROVE'===$decision){$review_doc=$review_doc_id?Stockland_DB::read_row($wpdb->prepare("SELECT id FROM %i WHERE id=%d AND entity_type='LEGAL_RULE' AND entity_id=%d AND document_type='LEGAL_REVIEW_OPINION' AND approval_status='APPROVED'",Stockland_HR_Replacement::table('hrx_document_refs'),$review_doc_id,$id),ARRAY_A):null;if(!$review_doc)return $this->error('hrx_legal_opinion_missing','An independently reviewed private legal opinion/source review is required before a statutory rule can be activated.',409);}
		$to='APPROVE'===$decision?'APPROVED':'LEGAL_REVIEW';if('APPROVE'===$decision&&'LEGAL_REVIEW'!==$rule['review_status'])return $this->error('hrx_rule_state_invalid','Only a LEGAL_REVIEW rule can be approved.',409);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start legal-rule review transaction.',500);
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_legal_rules'),array('review_status'=>$to,'reviewer_name'=>$reviewer_name,'reviewer_qualification'=>$reviewer_qualification,'review_evidence_ref_id'=>'APPROVE'===$decision?$review_doc_id:null,'reviewed_at'=>Stockland_HR_Replacement::now(),'approved_by'=>'APPROVE'===$decision?get_current_user_id():null,'approved_at'=>'APPROVE'===$decision?Stockland_HR_Replacement::now():null),array('id'=>$id,'review_status'=>$rule['review_status']));
		if(false===$ok||0===(int)$ok){$this->rollback();return $this->error('hrx_rule_update_failed','The legal rule changed or did not update; no review or audit event was written.',409);}
		$evt=Stockland_HR_Replacement::approval_event('legal-rule',$id,'LEGAL_RULE_'.$decision,$rule['review_status'],$to,$note,$key,null,get_current_user_id());$audit=Stockland_HR_Replacement::audit('legal_rule_'.$decision,'legal-rule',$id,'Legal rule review state changed.');
		if(is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_rule_audit_failed','Rule review was rolled back because approval evidence could not be persisted.',500);}
		$this->commit();
		return rest_ensure_response(array('id'=>$id,'review_status'=>$to,'message'=>'Rule review captured. Do not enable any payroll rule until the qualified professional confirms the authoritative instrument and effective dates.'));
	}

	public function classify_payee_request( $request ) {
		global $wpdb;$id=(int)$request['id'];$body=$this->body($request);
		$req=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_payee_requests'),$id),ARRAY_A);if(!$req)return $this->error('hrx_payee_request_not_found','Payee request not found.',404);
		if((int)$req['created_by']===get_current_user_id())return $this->error('hrx_no_self_approval','Request creator cannot independently classify its tax treatment.',403);
		if(!in_array($req['status'],array('DRAFT','LEGAL_TAX_REVIEW','NEEDS_INFORMATION'),true))return $this->error('hrx_payee_request_locked','Classification cannot be changed after request submission/calculation.',409);
		$classification=sanitize_key($body['classification']??'');$status=strtoupper(sanitize_key($body['status']??'LEGAL_REVIEW'));$reason=sanitize_textarea_field((string)($body['reason']??''));$rule_id=(int)($body['rule_id']??0);
		if(strlen(trim($reason))<40||!in_array($status,array('APPROVED','LEGAL_REVIEW','NEEDS_INFORMATION','REJECTED'),true))return $this->error('hrx_payee_classification_invalid','Classification decision requires detailed basis and valid review state.',422);
		$rate=null;
		if('APPROVED'===$status){
			$start=Stockland_HR_Replacement::valid_date($req['service_period_start']??'')?:current_time('Y-m-d');
			$end=Stockland_HR_Replacement::valid_date($req['service_period_end']??'')?:$start;
			$rule=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND review_status='APPROVED' AND rule_family='PAYEE_WITHHOLDING' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s)",Stockland_HR_Replacement::table('hrx_legal_rules'),$rule_id,$start,$end),ARRAY_A);
			if(!$rule||empty($rule['source_sha256'])||empty($rule['test_vectors_sha256'])||empty($rule['reviewer_qualification']))return $this->error('hrx_payee_rule_unapproved','Payee classification must reference an effective approved PAYEE_WITHHOLDING legal rule with source/test evidence and qualified review.',409);
			$source=json_decode((string)$rule['formula_parameters'],true);
			if(!is_array($source)||!isset($source['rate'])||'HALF_AWAY_FROM_ZERO_CENT'!==($source['rounding_method']??''))return $this->error('hrx_payee_rule_parameters','Approved legal rule does not contain a supported rate and rounding method.',409);
			$rate=(string)$source['rate'];
		}
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start payee classification transaction.',500);
		$ok=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_payee_classifications'),array('payee_request_id'=>$id,'rule_id'=>$rule_id?:null,'classification'=>$classification,'withholding_required'=>'APPROVED'===$status?'RULE_DECIDED':'LEGAL_REVIEW','withholding_rate'=>$rate,'reason'=>$reason,'reviewer_user_id'=>get_current_user_id(),'reviewed_at'=>Stockland_HR_Replacement::now(),'status'=>$status,'created_by'=>get_current_user_id(),'created_at'=>Stockland_HR_Replacement::now()));
		if(false===$ok){$this->rollback();return $this->error('hrx_payee_classification_save_failed','Classification record could not be saved.',500);}
		$ok2=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payee_requests'),array('classification_status'=>$status,'withholding_rule_id'=>$rule_id?:null,'status'=>'APPROVED'===$status?'DRAFT':('NEEDS_INFORMATION'===$status?'NEEDS_INFORMATION':'LEGAL_TAX_REVIEW'),'updated_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'created_by'=>(int)$req['created_by']));
		$evt=Stockland_HR_Replacement::approval_event('payee-request',$id,'PAYEE_CLASSIFICATION_'.$status,$req['classification_status'],$status,$reason,wp_generate_uuid4(),null,get_current_user_id());
		$audit=Stockland_HR_Replacement::audit('payee_classified','payee-request',$id,'Payee classification captured; request remains unpaid until calculation, Owner approval and central AP settlement.');
		if(false===$ok2||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_payee_classification_failed','Classification and request state were rolled back.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'classification_status'=>$status,'rule_id'=>$rule_id?:null,'withholding_rate'=>$rate,'payment_allowed'=>false,'message'=>'Classification review captured. Calculation, Owner AP approval and independently evidenced payment remain separate steps.'));
	}
public function approve_payee_request( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$id=(int)$request['id'];$body=$this->body($request);
		$req=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_HR_Replacement::table('hrx_payee_requests'),$id),ARRAY_A);
		if(!$req)return $this->error('hrx_payee_request_not_found','Payee request not found.',404);
		$engagement=$this->approved_payee_engagement($req);if(is_wp_error($engagement))return $engagement;
		if('APPROVED'!==strtoupper((string)$req['classification_status'])||'SUBMITTED'!==strtoupper((string)$req['status']))return $this->error('hrx_payee_state_invalid','Payee request must be calculated and independently tax-classified before approval.',409);
		if((int)$req['created_by']===get_current_user_id())return $this->error('hrx_no_self_approval','Request creator cannot approve the request.',403);
		$reason=sanitize_textarea_field((string)($body['approval_note']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));
		if(strlen(trim($reason))<30||!$key)return $this->error('hrx_payee_approval_note_required','Independent approval rationale (30+ characters) and idempotency key are required.',422);
		if(Stockland_Money::minor($req['cash_amount'])!==Stockland_Money::minor(Stockland_Money::add($req['net_amount'],$req['withholding_amount'])))return $this->error('hrx_payee_calculation_mismatch','Cash amount does not reconcile to net payable plus withholding.',409);
		$rule_id=(int)($req['withholding_rule_id']??0);
		$rule=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND review_status='APPROVED' AND rule_family='PAYEE_WITHHOLDING'",Stockland_HR_Replacement::table('hrx_legal_rules'),$rule_id),ARRAY_A);
		if(!$rule||empty($rule['source_sha256'])||!preg_match('/^[a-f0-9]{64}$/i',(string)$rule['source_sha256']))return $this->error('hrx_payee_rule_no_longer_approved','The approved payee withholding rule or source hash is missing.',409);
		if(Stockland_Money::minor($req['in_kind_value'])>0&&((int)$req['in_kind_posting_verified']!==1||empty($req['in_kind_stock_reference'])))return $this->error('hrx_in_kind_stock_evidence_missing','In-kind stock transaction/valuation evidence is not verified.',409);
		$config=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_accounting_config'),$req['service_period_end']?:current_time('Y-m-d'),$req['service_period_start']?:current_time('Y-m-d')),ARRAY_A);
		if(!$config)return $this->error('hrx_gl_mapping_missing','Approved effective-dated central AP account mapping is required.',409);
		foreach(array($config['payee_expense_account'],$config['payee_payable_account'],$config['payee_withholding_account']) as $account){if(empty($account)||!Stockland_Accounting::account_exists($account))return $this->error('hrx_ap_account_invalid','All central AP expense/payable/withholding accounts must exist and be active.',409);}
		$net=Stockland_Money::minor($req['net_amount']);$withholding=Stockland_Money::minor($req['withholding_amount']);$cash=Stockland_Money::minor($req['cash_amount']);
		if($net<0||$withholding<0||$cash<0||$net+$withholding!==$cash)return $this->error('hrx_payee_amounts_invalid','Payee net/withholding amounts are not valid or balanced.',409);
		$lines=array();
		if($cash>0)$lines[]=array('account_code'=>$config['payee_expense_account'],'debit'=>Stockland_Money::decimal($cash),'credit'=>'0.00','memo'=>'Central payee service/cash consideration '.$req['request_code']);
		if($net>0)$lines[]=array('account_code'=>$config['payee_payable_account'],'debit'=>'0.00','credit'=>Stockland_Money::decimal($net),'memo'=>'Approved net payable '.$req['request_code']);
		if($withholding>0)$lines[]=array('account_code'=>$config['payee_withholding_account'],'debit'=>'0.00','credit'=>Stockland_Money::decimal($withholding),'memo'=>'Approved payee withholding '.$req['request_code']);
		if(empty($lines))return $this->error('hrx_payee_zero_value','Zero-value payee request cannot be posted.',409);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start AP approval transaction.',500);
		$journal=Stockland_Accounting::post_journal(current_time('Y-m-d'),'HRX_PAYEE_REQUEST',$id,'Central payee AP '.$req['request_code'],$lines,get_current_user_id());
		if(!$journal){$this->rollback();return $this->error('hrx_payee_gl_failed','AP approval journal did not post.',500);}
		$link=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_accounting_links'),array('source_type'=>'HRX_PAYEE_REQUEST','source_id'=>$id,'journal_id'=>(int)$journal,'journal_hash'=>hash('sha256',wp_json_encode($lines)),'posted_at'=>Stockland_HR_Replacement::now(),'posted_by'=>get_current_user_id()));
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payee_requests'),array('status'=>'APPROVED','approved_by'=>get_current_user_id(),'approved_at'=>Stockland_HR_Replacement::now(),'journal_id'=>(int)$journal,'updated_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'status'=>'SUBMITTED','classification_status'=>'APPROVED'));
		$evt=Stockland_HR_Replacement::approval_event('payee-request',$id,'PAYEE_APPROVED','SUBMITTED','APPROVED',$reason,$key,null,get_current_user_id());
		$audit=Stockland_HR_Replacement::audit('payee_approved','payee-request',$id,'Central AP liability and withholding journal posted; payment remains separate.');
		if(false===$link||false===$ok||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_payee_approval_failed','Approval/AP journal and audit were rolled back.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'status'=>'APPROVED','journal_id'=>(int)$journal,'message'=>'Central AP liability posted. A separate authorized Management payer must record and reconcile payment.'));
	}

	public function create_exit_settlement( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$body=$this->body($request);$employment_id=(int)($body['employment_id']??0);
		$emp=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_employments'),$employment_id),ARRAY_A);if(!$emp)return $this->error('hrx_employment_not_found','Employment relationship not found.',404);
		if(!in_array($emp['status'],array('ACTIVE','EXIT_PENDING'),true))return $this->error('hrx_employment_state_invalid','Only an active employment relationship can enter final-settlement workflow.',409);
		$existing=Stockland_DB::read_row($wpdb->prepare("SELECT id,status FROM %i WHERE employment_id=%d AND status NOT IN('SETTLED','CANCELLED') ORDER BY id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_exit_settlements'),$employment_id),ARRAY_A);
		if($existing)return $this->error('hrx_exit_already_open','An unsettled exit process already exists for this employment.',409);
		$exit_type=sanitize_key($body['exit_type']??'');$reason=sanitize_textarea_field((string)($body['exit_reason']??''));$last=Stockland_HR_Replacement::valid_date($body['final_working_date']??'');$effective=Stockland_HR_Replacement::valid_date($body['effective_end_date']??'');
		if(!$exit_type||strlen(trim($reason))<20||!$last||!$effective||$effective<$last)return $this->error('hrx_exit_input_invalid','Exit type/reason and valid final/effective dates are required; effective date must be on or after final working day.',422);
		$code='EXITX-'.gmdate('Ymd').'-'.strtoupper(wp_generate_password(8,false,false));
		$row=array('settlement_code'=>$code,'employment_id'=>$employment_id,'exit_type'=>$exit_type,'exit_reason'=>$reason,'notice_date'=>Stockland_HR_Replacement::valid_date($body['notice_date']??'')?:null,'final_working_date'=>$last,'effective_end_date'=>$effective,'status'=>'DRAFT','created_by'=>get_current_user_id(),'created_at'=>Stockland_HR_Replacement::now(),'version_no'=>1);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start exit request transaction.',500);
		if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_exit_settlements'),$row)){$this->rollback();return $this->error('hrx_exit_insert_failed','Final settlement request could not be created.',409);}
		$id=(int)$wpdb->insert_id;
		$tasks=array(
			array('HANDOVER','Complete role handover and return operational responsibilities'),
			array('EQUIPMENT_RETURN','Return issued equipment, keys and access tokens'),
			array('STOCK_FINANCE_CLEARANCE','Resolve stock custody, advances and finance exceptions'),
			array('DOCUMENT_ARCHIVE','Archive required documents under approved retention rules'),
			array('ACCESS_REVOKED','Revoke linked Stockland ERP profile and location scope')
		);
		$task_failed=false;
		foreach($tasks as $task){
			$task_status=('ACCESS_REVOKED'===$task[0]&&empty($emp['wp_user_id']))?'COMPLETE':'OPEN';
			$taskrow=array('settlement_id'=>$id,'employment_id'=>$employment_id,'task_code'=>$task[0],'task_title'=>$task[1],'responsible_role'=>'MANAGEMENT','status'=>$task_status,'resolution_note'=>('COMPLETE'===$task_status?'No linked Stockland ERP account exists.':null),'created_by'=>get_current_user_id(),'created_at'=>Stockland_HR_Replacement::now());
			if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_exit_clearance_tasks'),$taskrow)){$task_failed=true;break;}
		}
		$evt=Stockland_HR_Replacement::approval_event('exit-settlement',$id,'EXIT_DRAFT_CREATED',null,'DRAFT','Exit request created',$code);
		$audit=Stockland_HR_Replacement::audit('exit_draft_created','exit-settlement',$id,'Exit initiated; employment remains active until approved settlement and clearance.');
		if($task_failed||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_exit_setup_failed','Exit request, clearance tasks and audit must all persist atomically; transaction was rolled back.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'settlement_code'=>$code,'status'=>'DRAFT','clearance_tasks'=>count($tasks),'message'=>'Draft created. The settlement remains blocked until every earning/deduction has qualified legal-rule evidence and all exit clearances are complete.'));
	}

	public function add_exit_item( $request ) {
		global $wpdb;$id=(int)$request['id'];$body=$this->body($request);
		$exit=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_exit_settlements'),$id),ARRAY_A);if(!$exit)return $this->error('hrx_exit_not_found','Final settlement not found.',404);
		if(!in_array($exit['status'],array('DRAFT','CALCULATED'),true))return $this->error('hrx_exit_locked','Only draft/freshly calculated settlement items may be edited.',409);
		$code=sanitize_key($body['item_code']??'');$label=sanitize_text_field($body['item_label']??'');$type=strtoupper(sanitize_key($body['item_type']??''));$amount=$body['amount']??null;$rule_id=(int)($body['rule_id']??0);$source=sanitize_text_field((string)($body['source_reference']??''));$trace=sanitize_textarea_field((string)($body['calculation_trace']??''));
		if(!$code||!$label||!in_array($type,array('EARNING','DEDUCTION'),true)||null===$amount||$rule_id<1||strlen(trim($trace))<20)return $this->error('hrx_exit_item_evidence_required','Item code/type/amount, approved rule ID and calculation trace are required.',422);
		$rule=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND review_status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s)",Stockland_HR_Replacement::table('hrx_legal_rules'),$rule_id,$exit['effective_end_date'],$exit['effective_end_date']),ARRAY_A);if(!$rule||empty($rule['source_sha256']))return $this->error('hrx_exit_rule_unapproved','Settlement item requires a currently effective approved rule with source hash.',409);
		try{$money=Stockland_Money::decimal(Stockland_Money::minor($amount));}catch(Throwable $e){return $this->error('hrx_exit_amount_invalid','Invalid settlement amount.',422);}
		if(Stockland_Money::minor($money)<=0)return $this->error('hrx_exit_amount_invalid','Settlement items must be positive amounts; use the earning/deduction type to determine treatment.',422);
		$payable_account=null;
		if('DEDUCTION'===$type){
			$config=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_accounting_config'),$exit['effective_end_date'],$exit['effective_end_date']),ARRAY_A);
			if(!$config)return $this->error('hrx_gl_mapping_missing','Approved GL mapping is required before settlement deductions can be added.',409);
			$map=array('PAYE'=>$config['paye_payable_account'],'EMPLOYEE_PENSION'=>$config['employee_pension_payable_account'],'EMPLOYER_PENSION'=>$config['employer_pension_payable_account'],'OTHER_DEDUCTION'=>$config['other_deductions_payable_account']);
			$payable_account=$map[strtoupper($code)]??$config['other_deductions_payable_account'];
			if(empty($payable_account)||!Stockland_Accounting::account_exists($payable_account))return $this->error('hrx_exit_deduction_account_invalid','Settlement deduction maps to a missing/inactive payable account.',409);
		}
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start settlement item transaction.',500);
		$ok=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_exit_items'),array('settlement_id'=>$id,'item_code'=>$code,'item_label'=>$label,'item_type'=>$type,'amount'=>$money,'rule_id'=>$rule_id,'payable_account_code'=>$payable_account,'source_reference'=>$source,'calculation_trace'=>$trace,'created_at'=>Stockland_HR_Replacement::now()));
		$ok2=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_exit_settlements'),array('status'=>'DRAFT','updated_at'=>Stockland_HR_Replacement::now(),'version_no'=>(int)$exit['version_no']+1),array('id'=>$id,'status'=>$exit['status']));
		$audit=Stockland_HR_Replacement::audit('exit_item_added','exit-settlement',$id,'Rule-backed final settlement item added; item change resets calculation.');
		if(false===$ok||false===$ok2||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_exit_item_insert_failed','Settlement item and state/audit update were rolled back.',500);}
		$this->commit();return rest_ensure_response(array('settlement_id'=>$id,'added'=>true,'status'=>'DRAFT','payable_account_code'=>$payable_account));
	}

	public function calculate_exit_settlement( $request ) {
		global $wpdb;$id=(int)$request['id'];$exit=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_exit_settlements'),$id),ARRAY_A);if(!$exit)return $this->error('hrx_exit_not_found','Final settlement not found.',404);
		if(!in_array($exit['status'],array('DRAFT','CALCULATED'),true))return $this->error('hrx_exit_locked','Exit settlement is locked.',409);
		$rows=Stockland_DB::read_results($wpdb->prepare('SELECT i.*,r.source_sha256,r.review_status FROM %i i LEFT JOIN %i r ON r.id=i.rule_id WHERE i.settlement_id=%d',Stockland_HR_Replacement::table('hrx_exit_items'),Stockland_HR_Replacement::table('hrx_legal_rules'),$id),ARRAY_A);
		if(empty($rows))return $this->error('hrx_exit_items_missing','Add rule-backed settlement items before calculation.',409);
		$items=array();foreach($rows as $r)$items[]=array('item_type'=>$r['item_type'],'amount'=>$r['amount'],'approved'=>'APPROVED'===$r['review_status'],'rule_id'=>(int)$r['rule_id'],'rule_source_sha256'=>$r['source_sha256']);
		$calc=Stockland_HR_Replacement_Calculator::final_settlement($items);if(is_wp_error($calc))return $calc;
		$hash=hash('sha256',wp_json_encode($rows));$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_exit_settlements'),array_merge($calc,array('status'=>'CALCULATED','rule_bundle_sha256'=>$hash,'updated_at'=>Stockland_HR_Replacement::now(),'version_no'=>(int)$exit['version_no']+1)),array('id'=>$id));
		if(false===$ok)return $this->error('hrx_exit_calculation_failed','Final settlement calculation did not save.',500);
		Stockland_HR_Replacement::approval_event('exit-settlement',$id,'EXIT_CALCULATED',$exit['status'],'CALCULATED','Item/rule evidence hash pinned',wp_generate_uuid4());Stockland_HR_Replacement::audit('exit_calculated','exit-settlement',$id,'Calculated rule-backed final settlement.');
		return rest_ensure_response(array('id'=>$id,'status'=>'CALCULATED','gross_final_amount'=>$calc['gross_final_amount'],'deduction_total'=>$calc['deduction_total'],'net_final_amount'=>$calc['net_final_amount'],'rule_bundle_sha256'=>$hash));
	}

	public function approve_exit_settlement( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$id=(int)$request['id'];$body=$this->body($request);
		$exit=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_HR_Replacement::table('hrx_exit_settlements'),$id),ARRAY_A);
		if(!$exit)return $this->error('hrx_exit_not_found','Final settlement not found.',404);
		$note=sanitize_textarea_field((string)($body['approval_note']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));
		if('CALCULATED'!==strtoupper((string)$exit['status'])||strlen(trim($note))<40||!$key)return $this->error('hrx_exit_approval_evidence_required','Calculated settlement, independent approval note (40+ characters) and idempotency key are required.',422);
		if((int)$exit['created_by']===get_current_user_id())return $this->error('hrx_no_self_approval','Settlement creator may not approve the final settlement.',403);
		$tasks=(int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE settlement_id=%d AND status<>'COMPLETE'",Stockland_HR_Replacement::table('hrx_exit_clearance_tasks'),$id));
		if($tasks>0)return $this->error('hrx_exit_clearance_incomplete','Every handover, equipment, stock/finance, archive and account-access clearance must be complete before settlement approval.',409);
		$items=Stockland_DB::read_results($wpdb->prepare("SELECT i.*,r.review_status,r.source_sha256 FROM %i i LEFT JOIN %i r ON r.id=i.rule_id WHERE i.settlement_id=%d",Stockland_HR_Replacement::table('hrx_exit_items'),Stockland_HR_Replacement::table('hrx_legal_rules'),$id),ARRAY_A);
		if(empty($items))return $this->error('hrx_exit_items_missing','Settlement has no itemized earnings/deductions.',409);
		$sum_gross=0;$sum_deductions=0;$by_account=array();
		foreach($items as $item){
			if('APPROVED'!==strtoupper((string)$item['review_status'])||!preg_match('/^[a-f0-9]{64}$/i',(string)$item['source_sha256']))return $this->error('hrx_exit_legal_rule_invalid','Every final settlement item requires a currently approved legal rule and source hash.',409);
			if('EARNING'===strtoupper((string)$item['item_type'])){$sum_gross+=Stockland_Money::minor($item['amount']);}
			elseif('DEDUCTION'===strtoupper((string)$item['item_type'])){
				$account=sanitize_text_field((string)($item['payable_account_code']??''));
				if(''===$account||!Stockland_Accounting::account_exists($account))return $this->error('hrx_exit_deduction_account_missing','Every final settlement deduction must map to an existing payable GL account.',409);
				$by_account[$account]=($by_account[$account]??0)+Stockland_Money::minor($item['amount']);$sum_deductions+=Stockland_Money::minor($item['amount']);
			}else return $this->error('hrx_exit_item_type_invalid','Settlement has an unsupported item type.',409);
		}
		if($sum_gross!==Stockland_Money::minor($exit['gross_final_amount'])||$sum_deductions!==Stockland_Money::minor($exit['deduction_total'])||$sum_gross-$sum_deductions!==Stockland_Money::minor($exit['net_final_amount']))return $this->error('hrx_exit_totals_mismatch','Recomputed item totals do not match the frozen final settlement. Recalculate after correcting items.',409);
		$config=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_accounting_config'),$exit['effective_end_date'],$exit['effective_end_date']),ARRAY_A);
		if(!$config)return $this->error('hrx_gl_mapping_missing','Approved effective-dated GL mapping is required for exit settlement posting.',409);
		$lines=array(array('account_code'=>$config['wage_expense_account'],'debit'=>$exit['gross_final_amount'],'credit'=>'0.00','memo'=>'Exit settlement gross '.$exit['settlement_code']));
		if(Stockland_Money::minor($exit['net_final_amount'])>0)$lines[]=array('account_code'=>$config['net_payable_account'],'debit'=>'0.00','credit'=>$exit['net_final_amount'],'memo'=>'Exit settlement net payable '.$exit['settlement_code']);
		foreach($by_account as $account=>$minor){if($minor>0)$lines[]=array('account_code'=>$account,'debit'=>'0.00','credit'=>Stockland_Money::decimal($minor),'memo'=>'Exit settlement deduction '.$exit['settlement_code']);}
		$debits=Stockland_Money::minor($exit['gross_final_amount']);$credits=Stockland_Money::minor($exit['net_final_amount'])+$sum_deductions;
		if($debits!==$credits)return $this->error('hrx_exit_journal_unbalanced','Exit settlement journal does not balance; approval is blocked.',409);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start exit approval transaction.',500);
		$journal=Stockland_Accounting::post_journal($exit['effective_end_date'],'HRX_EXIT_SETTLEMENT',$id,'Final settlement accrual '.$exit['settlement_code'],$lines,get_current_user_id());
		if(!$journal){$this->rollback();return $this->error('hrx_exit_journal_failed','Final settlement accrual journal did not post.',500);}
		$link=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_accounting_links'),array('source_type'=>'HRX_EXIT_SETTLEMENT','source_id'=>$id,'journal_id'=>(int)$journal,'journal_hash'=>hash('sha256',wp_json_encode($lines)),'posted_at'=>Stockland_HR_Replacement::now(),'posted_by'=>get_current_user_id()));
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_exit_settlements'),array('status'=>'APPROVED','approved_by'=>get_current_user_id(),'approved_at'=>Stockland_HR_Replacement::now(),'journal_id'=>(int)$journal,'updated_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'status'=>'CALCULATED','created_by'=>(int)$exit['created_by']));
		$evt=Stockland_HR_Replacement::approval_event('exit-settlement',$id,'EXIT_APPROVED','CALCULATED','APPROVED',$note,$key,null,get_current_user_id());
		$audit=Stockland_HR_Replacement::audit('exit_approved','exit-settlement',$id,'Owner approved itemized rule-backed settlement and posted the accrual journal.');
		if(false===$link||false===$ok||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_exit_approval_failed','Final settlement approval, journal and audit were rolled back.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'status'=>'APPROVED','journal_id'=>(int)$journal,'message'=>'Settlement accrual posted. Management must separately pay net amount with private receipt, and access must already be revoked.'));
	}

	public function list_documents( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$entity_type=strtoupper(sanitize_key((string)$request->get_param('entity_type')));
		$entity_id=(int)$request->get_param('entity_id');
		$where='1=1';$args=array();
		if($entity_type){$where.=' AND entity_type=%s';$args[]=$entity_type;}
		if($entity_id>0){$where.=' AND entity_id=%d';$args[]=$entity_id;}
		$sql='SELECT id,document_code,entity_type,entity_id,document_type,content_sha256,mime_type,size_bytes,version_no,approval_status,effective_date,uploaded_by,verified_by,verified_at,retention_until,created_at FROM %i WHERE '.$where.' ORDER BY id DESC LIMIT 100';
		array_unshift($args,Stockland_HR_Replacement::table('hrx_document_refs'));
		$rows=Stockland_DB::read_results($wpdb->prepare($sql,$args),ARRAY_A);
		if($wpdb->last_error)return $this->error('hrx_document_list_failed','Could not list private document metadata.',500);
		$visible = array();
		foreach ( (array) $rows as $row ) {
			// Do not disclose even metadata for a document whose underlying HR/payroll record
			// cannot be viewed by this user's role/location scope.
			if ( ! $this->document_accessible( $row ) ) { continue; }
			$row['download_url'] = ( 'APPROVED' === $row['approval_status'] )
				? wp_nonce_url( add_query_arg( array( 'action' => 'stockland_hrx_document_download', 'document_id' => (int) $row['id'] ), admin_url( 'admin-post.php' ) ), 'stockland_hrx_document_download_' . (int) $row['id'], '_wpnonce' )
				: '';
			$visible[] = $row;
		}
		return rest_ensure_response( array( 'documents' => $visible, 'storage' => 'private-database-vault', 'limit' => 100 ) );
	}

	public function upload_document( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		if(empty($_FILES['file'])||!is_array($_FILES['file'])||!empty($_FILES['file']['error']))return $this->error('hrx_document_upload_missing','Select a document file that completed uploading successfully.',422);
		$file=$_FILES['file'];$max=8*1024*1024;
		if(!isset($file['tmp_name'],$file['size'])||!is_uploaded_file($file['tmp_name'])||(int)$file['size']<1||(int)$file['size']>$max)return $this->error('hrx_document_size_invalid','Private document size must be between 1 byte and 8 MiB.',413);
		$entity_type=strtoupper(sanitize_key((string)$request->get_param('entity_type')));$entity_id=(int)$request->get_param('entity_id');$type=strtoupper(sanitize_key((string)$request->get_param('document_type')));
		$allowed_types=array('APPLICATION','EMPLOYMENT_CONTRACT','IDENTITY_VERIFICATION','ONBOARDING','TRAINING','TIME_EVIDENCE','LEAVE_EVIDENCE','COMPENSATION_APPROVAL','PAYROLL_EVIDENCE','PAYSLIP','EXIT_CLEARANCE','FINAL_SETTLEMENT','PAYEE_CONTRACT','PAYEE_DELIVERABLE','TAX_REMITTANCE_RECEIPT','PENSION_REMITTANCE_RECEIPT','LEGAL_REVIEW_OPINION','SOP_ACKNOWLEDGEMENT','ROLE_JD','HR_SOP','CODE_OF_CONDUCT','CONTRACT_TEMPLATE','ALLOWANCE_CATALOGUE','OTHER_HR');
		$is_resource='HR_RESOURCE'===$entity_type;
		if((!$is_resource&&!$entity_id)||!in_array($entity_type,array('APPLICATION','EMPLOYMENT','CONTRACT','PAYROLL_RUN','PAYROLL_LINE','EXIT_SETTLEMENT','PAYEE_REQUEST','LEGAL_RULE','REMITTANCE','HR_RESOURCE'),true)||!in_array($type,$allowed_types,true))return $this->error('hrx_document_target_invalid','Document owner or document type is not in the approved allow-list.',422);
		if($is_resource&&!$this->manage_hr())return $this->error('hrx_resource_upload_forbidden','Only authorized HR Management may upload HR Process Center resources.',403);
		$target=$this->document_target($entity_type,$entity_id);if(is_wp_error($target))return $target;
		$mime='';if(class_exists('finfo')){$fi=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$fi->file($file['tmp_name']);}
		$ext=strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION));
		$mime_map=array('pdf'=>'application/pdf','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png');
		if(!isset($mime_map[$ext])||$mime_map[$ext]!==$mime)return $this->error('hrx_document_type_invalid','Only verified PDF, DOCX, XLSX, JPEG or PNG files are accepted and the extension must match the detected MIME type.',415);
		$bytes=file_get_contents($file['tmp_name']);if(false===$bytes||strlen($bytes)!==(int)$file['size'])return $this->error('hrx_document_read_failed','Uploaded document could not be read safely.',500);
		if('docx'===$ext||'xlsx'===$ext){if(0!==substr_compare($bytes,"PK\x03\x04",0,4)||false===strpos($bytes,'[Content_Types].xml'))return $this->error('hrx_office_package_invalid','Office file did not pass ZIP/OOXML signature validation.',415);}
		$hash=hash('sha256',$bytes);$code='DOCX-'.gmdate('Ymd').'-'.strtoupper(wp_generate_password(10,false,false));
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start private document transaction.',500);
		$meta=array('document_code'=>$code,'entity_type'=>$entity_type,'entity_id'=>$entity_id,'document_type'=>$type,'private_storage_key'=>'dbblob:pending','content_sha256'=>$hash,'mime_type'=>$mime,'size_bytes'=>strlen($bytes),'version_no'=>1,'approval_status'=>'DRAFT','effective_date'=>Stockland_HR_Replacement::valid_date($request->get_param('effective_date')??'')?:null,'uploaded_by'=>get_current_user_id(),'retention_until'=>Stockland_HR_Replacement::valid_date($request->get_param('retention_until')??'')?:null,'created_at'=>Stockland_HR_Replacement::now());
		if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_document_refs'),$meta)){$this->rollback();return $this->error('hrx_document_meta_failed','Document metadata could not be saved.',500);}
		$doc_id=(int)$wpdb->insert_id;
		Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_document_refs'),array('private_storage_key'=>'dbblob:'.$doc_id),array('id'=>$doc_id));
		$blob=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_document_blobs'),array('document_ref_id'=>$doc_id,'payload'=>$bytes,'created_at'=>Stockland_HR_Replacement::now()),array('%d','%s','%s'));
		$evt=Stockland_HR_Replacement::approval_event('document',$doc_id,'DOCUMENT_UPLOADED',null,'DRAFT','Private document metadata and content hash recorded',$code);
		$audit=Stockland_HR_Replacement::audit('document_uploaded','document',$doc_id,'Private document uploaded; content remains pending independent verification.');
		if(false===$blob||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_document_storage_failed','Private document upload was rolled back because blob or audit persistence failed.',500);}
		$this->commit();
		return rest_ensure_response(array('id'=>$doc_id,'document_code'=>$code,'document_type'=>$type,'content_sha256'=>$hash,'size_bytes'=>strlen($bytes),'approval_status'=>'DRAFT','download_url'=>'','message'=>'File is in private database storage. An independent Owner must verify and approve it before download/signing is enabled.'));
	}

	private function document_target( $entity_type, $entity_id ) {
		global $wpdb;
		if('HR_RESOURCE'===$entity_type){return array('id'=>0,'status'=>'ACTIVE','resource_library'=>true);}
		$tables=array('APPLICATION'=>'hrx_applications','EMPLOYMENT'=>'hrx_employments','CONTRACT'=>'hrx_contracts','PAYROLL_RUN'=>'hrx_payroll_runs','PAYROLL_LINE'=>'hrx_payroll_lines','EXIT_SETTLEMENT'=>'hrx_exit_settlements','PAYEE_REQUEST'=>'hrx_payee_requests','LEGAL_RULE'=>'hrx_legal_rules','REMITTANCE'=>'hrx_remittances');
		if(!isset($tables[$entity_type]))return $this->error('hrx_document_owner_type_invalid','Unknown document owner type.',422);
		$row=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table($tables[$entity_type]),$entity_id),ARRAY_A);
		if(!$row)return $this->error('hrx_document_owner_missing','The target record does not exist.',404);
		if(in_array($entity_type,array('PAYROLL_RUN','PAYROLL_LINE'),true)&&!$this->view_payroll())return $this->error('hrx_document_forbidden','Payroll document access is restricted.',403);
		return $row;
	}

	private function document_accessible( $document ) {
		global $wpdb;
		if(!is_array($document)||!is_user_logged_in())return false;
		$type=strtoupper((string)$document['entity_type']);$id=(int)$document['entity_id'];
		if('HR_RESOURCE'===$type)return $this->view_hr();
		// Personnel and payroll document relations are not exposed to branch-only scope yet.
		if(!Stockland_Authority::company_scope())return false;
		$target=$this->document_target($type,$id);if(is_wp_error($target))return false;
		if(in_array($type,array('PAYROLL_RUN','PAYROLL_LINE','PAYEE_REQUEST','REMITTANCE'),true)&&!$this->view_payroll())return false;
		$employment_id=0;
		if('EMPLOYMENT'===$type)$employment_id=$id;
		elseif('CONTRACT'===$type)$employment_id=(int)$target['employment_id'];
		elseif('EXIT_SETTLEMENT'===$type)$employment_id=(int)$target['employment_id'];
		if($employment_id){
			$assignment=Stockland_DB::read_row($wpdb->prepare("SELECT location_id FROM %i WHERE employment_id=%d AND approval_status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_assignments'),$employment_id,current_time('Y-m-d'),current_time('Y-m-d')),ARRAY_A);
			if($assignment&&!Stockland_Authority::can_access_location((int)$assignment['location_id']))return false;
		}
		return $this->view_hr();
	}

	public function review_document( $request ) {
		global $wpdb;$id=(int)$request['id'];$body=$this->body($request);
		$doc=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_document_refs'),$id),ARRAY_A);
		if(!$doc)return $this->error('hrx_document_not_found','Private document not found.',404);
		if('DRAFT'!==$doc['approval_status'])return $this->error('hrx_document_state_invalid','Only pending document metadata may be independently reviewed.',409);
		if((int)$doc['uploaded_by']===get_current_user_id())return $this->error('hrx_document_no_self_review','The document uploader cannot approve their own document.',403);
		$note=sanitize_textarea_field((string)($body['review_note']??''));$decision=strtoupper(sanitize_key($body['decision']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));
		if(!in_array($decision,array('APPROVE','REJECT'),true)||strlen(trim($note))<30||!$key)return $this->error('hrx_document_review_evidence_required','Review decision, rationale and idempotency key are required.',422);
		$blob=Stockland_DB::read_row($wpdb->prepare('SELECT payload FROM %i WHERE document_ref_id=%d',Stockland_HR_Replacement::table('hrx_document_blobs'),$id),ARRAY_A);
		if(!$blob||hash('sha256',(string)$blob['payload'])!==$doc['content_sha256'])return $this->error('hrx_document_hash_mismatch','Stored document does not match its SHA-256 metadata; download and signing are blocked.',409);
		$to='APPROVE'===$decision?'APPROVED':'REJECTED';
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start document review transaction.',500);
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_document_refs'),array('approval_status'=>$to,'verified_by'=>get_current_user_id(),'verified_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'approval_status'=>'DRAFT','uploaded_by'=>(int)$doc['uploaded_by']));
		$evt=Stockland_HR_Replacement::approval_event('document',$id,'DOCUMENT_'.$to,'DRAFT',$to,$note,$key,null,get_current_user_id());
		$audit=Stockland_HR_Replacement::audit('document_'.$to,'document',$id,'Private document review recorded.');
		if(false===$ok||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_document_review_failed','Document approval/audit failed and was rolled back.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'approval_status'=>$to,'content_sha256'=>$doc['content_sha256']));
	}

	public function sign_contract( $request ) {
		global $wpdb;$id=(int)$request['id'];$body=$this->body($request);
		$contract=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_contracts'),$id),ARRAY_A);
		if(!$contract)return $this->error('hrx_contract_not_found','Contract not found.',404);
		if(!in_array($contract['status'],array('DRAFT','SUBMITTED'),true)||!empty($contract['signed_by_person_at']))return $this->error('hrx_contract_sign_state','Only an unsigned draft/submitted contract may record a signature.',409);
		$doc_id=(int)($body['document_id']??0);$signed=Stockland_HR_Replacement::valid_date($body['signed_date']??'');$key=sanitize_text_field((string)($body['idempotency_key']??''));
		$doc=$doc_id?Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND entity_type='CONTRACT' AND entity_id=%d AND document_type='EMPLOYMENT_CONTRACT' AND approval_status='APPROVED'",Stockland_HR_Replacement::table('hrx_document_refs'),$doc_id,$id),ARRAY_A):null;
		if(!$signed||!$key||!$doc)return $this->error('hrx_contract_signature_evidence','A valid signature date, idempotency key, and independently verified private employment-contract document are required.',422);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start signature transaction.',500);
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_contracts'),array('signed_by_person_at'=>$signed.' 00:00:00','private_document_ref_id'=>$doc_id),array('id'=>$id,'status'=>$contract['status'],'signed_by_person_at'=>null));
		$evt=Stockland_HR_Replacement::approval_event('contract',$id,'CONTRACT_SIGNED',$contract['status'],$contract['status'],'Signed document reference recorded',$key,$doc_id);
		$audit=Stockland_HR_Replacement::audit('contract_signed','contract',$id,'Signed contract remains subject to independent Owner approval.');
		if(false===$ok||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_contract_sign_audit_failed','Signature record/audit failed and was rolled back.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'signed'=>true,'document_ref_id'=>$doc_id,'status'=>$contract['status']));
	}

	public function download_document() {
		global $wpdb;
		$id=isset($_GET['document_id'])?(int)$_GET['document_id']:0;
		if(!$id||!is_user_logged_in()){status_header(401);wp_die(esc_html__('Sign in to access private HR documents.','stockland-erp'),'',array('response'=>401));}
		check_admin_referer('stockland_hrx_document_download_'.$id,'_wpnonce');
		$doc=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND approval_status='APPROVED'",Stockland_HR_Replacement::table('hrx_document_refs'),$id),ARRAY_A);
		if(!$doc||!$this->document_accessible($doc)){status_header(403);wp_die(esc_html__('You do not have access to this private HR document.','stockland-erp'),'',array('response'=>403));}
		$blob=Stockland_DB::read_row($wpdb->prepare('SELECT payload FROM %i WHERE document_ref_id=%d',Stockland_HR_Replacement::table('hrx_document_blobs'),$id),ARRAY_A);
		$bytes=$blob['payload']??'';
		if(!is_string($bytes)||hash('sha256',$bytes)!==$doc['content_sha256']){status_header(500);wp_die(esc_html__('Private document integrity check failed.','stockland-erp'),'',array('response'=>500));}
		$audit=Stockland_HR_Replacement::audit('document_download','document',$id,'Private document download served to authorized user.');
		if(false===$audit){status_header(500);wp_die(esc_html__('Download audit could not be recorded. Access was denied.','stockland-erp'),'',array('response'=>500));}
		if(ob_get_level()){while(ob_get_level())ob_end_clean();}
		$ext=array('application/pdf'=>'pdf','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx','image/jpeg'=>'jpg','image/png'=>'png')[$doc['mime_type']]??'bin';
		$filename='Stockland-HR-'.$doc['document_code'].'.'.$ext;
		nocache_headers();header('Content-Type: '.$doc['mime_type']);header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.strlen($bytes));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, max-age=0, must-revalidate');
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Private binary document is intentionally streamed.
		exit;
	}

	public function deny_document_download() { status_header(401);wp_die(esc_html__('Sign in with an authorized Stockland HR account to access this private document.','stockland-erp'),'',array('response'=>401)); }

	private function begin() {
		$ok=Stockland_DB::execute_query('START TRANSACTION');
		return false!==$ok;
	}
	private function commit() { return Stockland_DB::execute_query('COMMIT'); }
	private function rollback() { return Stockland_DB::execute_query('ROLLBACK'); }
}
