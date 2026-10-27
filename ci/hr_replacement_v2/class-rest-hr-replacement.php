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
	}

	public function register_routes() {
		$ns = self::NAMESPACE_V1;
		register_rest_route( $ns, '/hr/replacement/bootstrap', array( 'methods'=>'GET', 'callback'=>array( $this,'bootstrap' ), 'permission_callback'=>array( $this,'permission_view_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/schema/batch', array( 'methods'=>'POST', 'callback'=>array( $this,'schema_batch' ), 'permission_callback'=>array( $this,'permission_schema_admin' ) ) );
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
		register_rest_route( $ns, '/hr/replacement/payout-items/(?P<id>\d+)/outcome', array( 'methods'=>'POST','callback'=>array( $this,'record_payout_outcome' ),'permission_callback'=>array( $this,'permission_approve_payroll' ) ) );
		register_rest_route( $ns, '/hr/replacement/payroll/runs/(?P<id>\d+)/close', array( 'methods'=>'POST','callback'=>array( $this,'close_payroll_run' ),'permission_callback'=>array( $this,'permission_approve_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/legal-rules/(?P<id>\d+)/review', array( 'methods'=>'POST','callback'=>array( $this,'review_legal_rule' ),'permission_callback'=>array( $this,'permission_approve_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/payee-requests/(?P<id>\d+)/classify', array( 'methods'=>'POST','callback'=>array( $this,'classify_payee_request' ),'permission_callback'=>array( $this,'permission_approve_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/payee-requests/(?P<id>\d+)/approve', array( 'methods'=>'POST','callback'=>array( $this,'approve_payee_request' ),'permission_callback'=>array( $this,'permission_approve_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/exits', array( 'methods'=>'POST','callback'=>array( $this,'create_exit_settlement' ),'permission_callback'=>array( $this,'permission_manage_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/exits/(?P<id>\d+)/items', array( 'methods'=>'POST','callback'=>array( $this,'add_exit_item' ),'permission_callback'=>array( $this,'permission_manage_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/exits/(?P<id>\d+)/calculate', array( 'methods'=>'POST','callback'=>array( $this,'calculate_exit_settlement' ),'permission_callback'=>array( $this,'permission_manage_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/exits/(?P<id>\d+)/approve', array( 'methods'=>'POST','callback'=>array( $this,'approve_exit_settlement' ),'permission_callback'=>array( $this,'permission_approve_hr' ) ) );
		register_rest_route( $ns, '/hr/replacement/documents/(?P<id>\d+)/authorize', array( 'methods'=>'POST','callback'=>array( $this,'document_authorize' ),'permission_callback'=>array( $this,'permission_manage_hr' ) ) );
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
	private function decode( $request, $key, $default = null ) { $v=$request->get_param( $key ); return null === $v ? $default : $v; }

	public function bootstrap() {
		$schema = Stockland_HR_Replacement::schema_status();
		$can_payroll = $this->view_payroll();
		return rest_ensure_response( array(
			'module'=>'HR_PAYROLL_REPLACEMENT','version'=>Stockland_HR_Replacement::SCHEMA_VERSION,
			'status'=>$schema['ready']?'SCHEMA_READY':'SCHEMA_NOT_INSTALLED','schema'=>$schema,
			'legacy_tables_read_only'=>array('employees','employee_compensation','payroll_periods','payroll_lines'),
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
			'applications'=>array('application_code','requisition_id','person_id','privacy_notice_version','consent_or_lawful_basis','applied_at','stage','screening_score','recommendation','selection_rationale','retention_until','status'),
			'interviews'=>array('application_id','interview_round','interview_at','interviewer_user_id','rubric_version','score','recommendation','evidence_ref','notes_redacted'),
			'offers'=>array('offer_code','application_id','employment_type','proposed_start','proposed_end','position_id','location_id','compensation_assignment_id','terms_version','contract_ref_id','status','accepted_at'),
			'contracts'=>array('employment_id','contract_code','contract_version','contract_type','start_date','end_date','probation_end','terms_hash','private_document_ref_id','signed_by_person_at','status'),
			'onboarding-tasks'=>array('employment_id','task_code','task_title','owner_role','due_date','evidence_ref_id','status'),
			'assignments'=>array('employment_id','position_id','org_unit_id','location_id','manager_employment_id','effective_from','effective_to','reason_code','approval_status'),
			'schedules'=>array('schedule_code','employment_id','effective_from','effective_to','timezone','monday_json','tuesday_json','wednesday_json','thursday_json','friday_json','saturday_json','sunday_json','status'),
			'time-events'=>array('event_code','employment_id','location_id','event_type','event_date','time_in','time_out','ordinary_minutes','overtime_minutes','night_minutes','rest_day_minutes','holiday_minutes','source_type','source_reference','correction_of_id','correction_reason','status'),
			'leave-policies'=>array('policy_code','leave_type','policy_version','effective_from','effective_to','accrual_method','accrual_amount','annual_limit','carry_forward_limit','paid_status','legal_rule_id','approval_status'),
			'leave-transactions'=>array('transaction_code','employment_id','leave_policy_id','transaction_type','start_date','end_date','quantity_days','source_transaction_id','reason','evidence_ref_id','status'),
			'compensation-assignments'=>array('assignment_code','employment_id','currency','base_salary','effective_from','effective_to','reason_code','reason_text','source_type','source_reference'),
			'compensation-items'=>array('compensation_assignment_id','component_code','component_name','component_type','amount','frequency','cash_payable','taxable_status','pensionable_status','legal_rule_id','evidence_ref_id'),
			'legal-rules'=>array('rule_code','rule_family','jurisdiction','source_title','source_article','source_url','source_sha256','publication_date','effective_from','effective_to','formula_key','formula_parameters','applicability','exceptions','review_status','reviewer_name','reviewer_qualification','test_vectors_sha256'),
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
		$table=$this->table($entity);
		$row=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d LIMIT 1',$table,(int)$id),ARRAY_A);
		if($wpdb->last_error)return $this->error('hrx_read_failed','Could not read HR record.',500);
		if(!$row)return $this->error('hrx_not_found','HR record not found.',404);
		if(isset($row['location_id']) && (int)$row['location_id']>0 && !Stockland_Authority::can_access_location((int)$row['location_id']))return $this->error('hrx_location_forbidden','This HR record is outside your authorized location scope.',403);
		unset($row['private_storage_key']);
		if(!$this->view_payroll()) foreach(array('base_salary','band_min','band_max','budget_amount','gross_total','taxable_total','paye_total','employee_pension_total','employer_pension_total','deduction_total','net_total','employer_cost_total','cash_amount','in_kind_value','gross_amount','withholding_amount','net_amount') as $sensitive){unset($row[$sensitive]);}
		return $row;
	}

	public function list_entity( $request ) {
		global $wpdb;
		$ready=$this->require_schema(); if(is_wp_error($ready))return $ready;
		$entity=sanitize_key($request['entity']);
		if(!isset(self::ENTITY_MAP[$entity]))return $this->error('hrx_unknown_entity','Unknown HR replacement entity.',404);
		$limit=max(1,min(100,(int)$request->get_param('limit')?:50));$offset=max(0,min(50000,(int)$request->get_param('offset')));
		$table=$this->table($entity);
		$where='1=1';$args=array();
		$location_id=(int)$request->get_param('location_id');
		if(!Stockland_Authority::company_scope() && !empty($location_id)){
			if(!Stockland_Authority::can_access_location($location_id))return $this->error('hrx_location_forbidden','Location access denied.',403);
			if(in_array($entity,array('org-units','positions','workforce-plans','requisitions','time-events','assignments','social-snapshots'),true)){$where.=' AND location_id=%d';$args[]=$location_id;}
		}
		$sql='SELECT * FROM %i WHERE '.$where.' ORDER BY id DESC LIMIT %d OFFSET %d';$args=array_merge(array($table),$args,array($limit,$offset));
		$rows=Stockland_DB::read_results($wpdb->prepare($sql,$args),ARRAY_A);
		if($wpdb->last_error)return $this->error('hrx_list_failed','Could not list HR records.',500);
		foreach($rows as &$row){
			unset($row['private_storage_key']);
			if(!$this->view_payroll())foreach(array('base_salary','band_min','band_max','budget_amount','gross_total','taxable_total','paye_total','employee_pension_total','employer_pension_total','deduction_total','net_total','employer_cost_total','cash_amount','in_kind_value','gross_amount','withholding_amount','net_amount') as $sensitive){unset($row[$sensitive]);}
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
		if(in_array($entity,array('legal-rules','legal-rule-bands','leave-policies','compensation-assignments','compensation-items','payee-requests','payee-classifications'),true)&&!$this->manage_hr())return $this->error('hrx_sensitive_write_forbidden','Sensitive HR/payroll writes require HR Management.',403);
		$payload=$this->body($request);$allowed=$this->allowed_fields($entity);$row=array();
		foreach($payload as $key=>$value){
			if(!in_array($key,$allowed,true))continue;
			$clean=$this->sanitize_value($key,$value);
			if(null===$clean)return $this->error('hrx_field_invalid','Invalid field value: '.$key,422);
			$row[$key]=$clean;
		}
		if(empty($row))return $this->error('hrx_payload_empty','No allowed fields supplied.',422);
		if(isset($row['location_id'])&&!empty($row['location_id'])&&!Stockland_Authority::can_access_location((int)$row['location_id']))return $this->error('hrx_location_forbidden','This record is outside your authorized location scope.',403);
		if(in_array($entity,array('legal-rules','leave-policies'),true))$row[$entity==='legal-rules'?'review_status':'approval_status']='LEGAL_REVIEW';
		if(in_array($entity,array('compensation-assignments','compensation-items'),true))$row['status']='DRAFT';
		if('payee-requests'===$entity){$row['status']='DRAFT';$row['classification_status']='LEGAL_REVIEW';}
		if(isset($map['state'])&&'active'!==$map['state']&&isset($row[$map['state']]))$row[$map['state']]='DRAFT';
		if('people'===$entity)$row['privacy_state']='ACTIVE';
		if('interviews'===$entity)$row['recommendation']='PENDING';
		if('payee-classifications'===$entity){$row['status']='LEGAL_REVIEW';}
		if('applications'===$entity){$row['stage']='RECEIVED';$row['status']='OPEN';}
		if('assignments'===$entity){$row['approval_status']='DRAFT';}
		if('social-snapshots'===$entity){$row['freshness_status']='CURRENT';}
		$row['created_by']=get_current_user_id();$row['created_at']=Stockland_HR_Replacement::now();
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
		if(isset($map['state'])&&isset($current[$map['state']])&&!in_array(strtoupper((string)$current[$map['state']]),array('DRAFT','OPEN','RECEIVED','PENDING','LEGAL_REVIEW'),true))return $this->error('hrx_record_locked','Only draft or review records may be edited.',409);
		$payload=$this->body($request);$allowed=$this->allowed_fields($entity);$data=array();
		foreach($payload as $key=>$value){
			if(!in_array($key,$allowed,true))continue;
			$clean=$this->sanitize_value($key,$value);if(null===$clean)return $this->error('hrx_field_invalid','Invalid field value: '.$key,422);$data[$key]=$clean;
		}
		if(empty($data))return $this->error('hrx_payload_empty','No mutable fields supplied.',422);
		unset($data['status'],$data['review_status'],$data['approval_status'],$data['stage'],$data['recommendation']);
		if(!$data)return $this->error('hrx_transition_required','Workflow state changes require the transition endpoint.',422);
		$data['updated_at']=Stockland_HR_Replacement::now();
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start HR transaction.',500);
		$ok=Stockland_DB::update_record($this->table($entity),$data,array('id'=>(int)$request['id']));
		$event=Stockland_HR_Replacement::approval_event($entity,(int)$request['id'],'UPDATED',$current[$map['state']]??null,$current[$map['state']]??'DRAFT','Mutable draft fields updated',wp_generate_uuid4());
		$audit=Stockland_HR_Replacement::audit('updated',$entity,(int)$request['id'],'HRX draft updated',isset($current['location_id'])?(int)$current['location_id']:null);
		if(false===$ok||is_wp_error($event)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_update_failed','Update/audit failed and was rolled back.',500);}
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
			'assignments'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'schedules'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'time-events'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'leave-policies'=>array('SUBMIT'=>array('LEGAL_REVIEW','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','LEGAL_REVIEW')),
			'leave-transactions'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED'),'CANCEL'=>array('APPROVED','CANCELLED')),
			'compensation-assignments'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'compensation-items'=>array('SUBMIT'=>array('DRAFT','SUBMITTED'),'APPROVE'=>array('SUBMITTED','APPROVED'),'REJECT'=>array('SUBMITTED','REJECTED')),
			'legal-rules'=>array('SUBMIT_LEGAL_REVIEW'=>array('DRAFT','LEGAL_REVIEW'),'APPROVE'=>array('LEGAL_REVIEW','APPROVED'),'RETIRE'=>array('APPROVED','RETIRED')),
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
		$is_approval=in_array($action,array('APPROVE','ACCEPT','ACTIVATE','COMPLETE','CLOSE','RETIRE'),true);
		if($is_approval&&!$this->approve_payroll())return $this->error('hrx_owner_required','Owner approval is required for this transition.',403);
		if(in_array($action,array('APPROVE','RETIRE'),true)&&isset($current['created_by'])&&(int)$current['created_by']===get_current_user_id())return $this->error('hrx_no_self_approval','The maker may not approve their own HR/payroll record.',403);
		if(in_array($entity,array('legal-rules','leave-policies'),true)&&'APPROVE'===$action){
			$extra=$body['review_attestation']??'';if(strlen(trim((string)$extra))<30)return $this->error('hrx_legal_review_required','Qualified review attestation and official-source effective-date confirmation are required.',422);
			if('legal-rules'===$entity){
				if(empty($current['source_sha256'])||!preg_match('/^[a-f0-9]{64}$/i',(string)$current['source_sha256'])||empty($current['test_vectors_sha256'])||!preg_match('/^[a-f0-9]{64}$/i',(string)$current['test_vectors_sha256'])||empty($current['reviewer_qualification']))return $this->error('hrx_legal_evidence_missing','Legal rule requires original-source hash, test-vector hash and reviewer qualification before approval.',422);
			}
		}
		if(in_array($entity,array('contracts'),true)&&'APPROVE'===$action&&empty($current['signed_by_person_at']))return $this->error('hrx_signed_contract_required','A signed contract record is required before approval.',409);
		if('compensation-assignments'===$entity&&'APPROVE'===$action){
			global $wpdb;$items=Stockland_DB::read_results($wpdb->prepare('SELECT taxable_status,pensionable_status,amount FROM %i WHERE compensation_assignment_id=%d',Stockland_HR_Replacement::table('hrx_compensation_items'),$id),ARRAY_A);
			foreach((array)$items as $item){if(!in_array($item['taxable_status'],array('TAXABLE','EXEMPT'),true)||!in_array($item['pensionable_status'],array('PENSIONABLE','NON_PENSIONABLE'),true))return $this->error('hrx_compensation_classification','Compensation component classification remains LEGAL_REVIEW and blocks approval.',409);}
		}
		$to=$rule[1];$extra=array('updated_at'=>Stockland_HR_Replacement::now());
		if('org-units'===$entity)$extra['active']='ACTIVATE'===$action?1:0;
		elseif('interviews'===$entity)$extra[$state_field]=$to;
		else $extra[$state_field]=$to;
		if($is_approval){$extra['approved_by']=get_current_user_id();if(in_array($state_field,array('status','approval_status','review_status'),true))$extra['approved_at']=Stockland_HR_Replacement::now();}
		if('applications'===$entity&&'SELECT'===$action){$selection=sanitize_textarea_field((string)($body['selection_rationale']??''));if(strlen(trim($selection))<20)return $this->error('hrx_selection_rationale_required','Document the human selection rationale (minimum 20 characters).',422);$extra['selection_rationale']=$selection;}
		if('applications'===$entity&&'REJECT'===$action){$extra['status']='REJECTED';$extra['retention_until']=Stockland_HR_Replacement::valid_date($body['retention_until']??'')?:null;}
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start HR transaction.',500);
		$ok=Stockland_DB::update_record($this->table($entity),$extra,array('id'=>$id,$state_field=>$current[$state_field]));
		if(false===$ok){$this->rollback();return $this->error('hrx_transition_write_failed','Record state update failed; no transition was recorded.',500);}
		$evt=Stockland_HR_Replacement::approval_event($entity,$id,$action,$from,$to,$reason,$key,isset($body['evidence_ref_id'])?(int)$body['evidence_ref_id']:null,$is_approval?get_current_user_id():null);
		$audit=Stockland_HR_Replacement::audit('transition_'.$action,$entity,$id,$reason,isset($current['location_id'])?(int)$current['location_id']:null,$is_approval?get_current_user_id():null);
		if(is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_transition_audit_failed','Workflow was rolled back because audit evidence could not be written.',500);}
		$this->commit();return rest_ensure_response(array('id'=>$id,'from'=>$from,'to'=>$to,'action'=>$action,'message'=>'Workflow state and audit evidence saved.'));
	}

	public function create_employment_from_offer( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$id=(int)$request['id'];$offer=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_HR_Replacement::table('hrx_offers'),$id),ARRAY_A);
		if(!$offer||'ACCEPTED'!==strtoupper((string)$offer['status']))return $this->error('hrx_offer_not_accepted','Only an approved and accepted offer may create a pending employment relationship.',409);
		$app=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_applications'),(int)$offer['application_id']),ARRAY_A);
		if(!$app||'OPEN'!==strtoupper((string)$app['status'])||'SELECTED'!==strtoupper((string)$app['stage'])||empty($app['selection_rationale'])||strlen(trim((string)$app['selection_rationale']))<20)return $this->error('hrx_human_selection_missing','A documented human selection decision and open approved application are required.',409);
		$person=(int)$app['person_id'];$employment_code='EMPX-'.gmdate('Ymd').'-'.wp_generate_password(8,false,false);
		$row=array('employment_code'=>$employment_code,'person_id'=>$person,'offer_id'=>$id,'legal_employer_code'=>sanitize_key((string)($request->get_param('legal_employer_code')?:'STOCKLAND_ET_PLC')),'employment_type'=>sanitize_key((string)$offer['employment_type'],'full_time'),'start_date'=>$offer['proposed_start'],'end_date'=>$offer['proposed_end'],'status'=>'PENDING_ACTIVATION','created_by'=>get_current_user_id(),'created_at'=>Stockland_HR_Replacement::now(),'version_no'=>1);
		if(false===$this->begin())return $this->error('hrx_tx_begin_failed','Could not start HR transaction.',500);
		$ok=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_employments'),$row);
		if(false===$ok){$this->rollback();return $this->error('hrx_employment_insert_failed','Could not create pending employment. Check unique identity/offer constraints.',409);}
		$eid=(int)$wpdb->insert_id;
		$contract=array('employment_id'=>$eid,'contract_code'=>'CTX-'.wp_generate_password(12,false,false),'contract_version'=>1,'contract_type'=>$row['employment_type'],'start_date'=>$row['start_date'],'end_date'=>$row['end_date'],'probation_end'=>null,'terms_hash'=>hash('sha256',(string)$offer['terms_version']),'status'=>'DRAFT','created_by'=>get_current_user_id(),'created_at'=>Stockland_HR_Replacement::now());
		$ok2=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_contracts'),$contract);
		$evt=Stockland_HR_Replacement::approval_event('employment',$eid,'PENDING_HIRE_CREATED',null,'PENDING_ACTIVATION','From accepted offer',$employment_code);
		$audit=Stockland_HR_Replacement::audit('pending_hire_created','employment',$eid,'From approved offer');
		if(false===$ok2||is_wp_error($evt)||is_wp_error($audit)||false===$audit){$this->rollback();return $this->error('hrx_hire_transaction_failed','Hire creation was rolled back because contract/audit did not complete.',500);}
		$this->commit();return rest_ensure_response(array('employment_id'=>$eid,'employment_code'=>$employment_code,'status'=>'PENDING_ACTIVATION','contract_id'=>(int)$wpdb->insert_id,'message'=>'Pending hire created; signed contract, verified documents, onboarding and approved compensation must be completed before activation.'));
	}

	public function create_payroll_run( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;
		$body=$this->body($request);$start=Stockland_HR_Replacement::valid_date($body['period_start']??'');$end=Stockland_HR_Replacement::valid_date($body['period_end']??'');
		if(!$start||!$end||substr($start,0,7)!==substr($end,0,7)||$start>$end||substr($start,8,2)!=='01')return $this->error('hrx_period_invalid','Payroll requires a valid complete calendar month.',422);
		$lastday=gmdate('Y-m-t',strtotime($start));
		if($lastday!==$end)return $this->error('hrx_period_invalid','Payroll period must end on the final calendar day of the month.',422);
		$existing=Stockland_DB::read_row($wpdb->prepare('SELECT id,status,run_version FROM %i WHERE period_start=%s AND period_end=%s ORDER BY run_version DESC LIMIT 1',Stockland_HR_Replacement::table('hrx_payroll_runs'),$start,$end),ARRAY_A);
		if($existing&&in_array(strtoupper((string)$existing['status']),array('APPROVED','POSTED','SETTLEMENT_PENDING','SETTLED','RECONCILED','CLOSED'),true))return $this->error('hrx_period_already_committed','This pay period already has a committed run. Create a linked correction/reversal workflow; do not create a duplicate run.',409);
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
			$items=Stockland_DB::read_results($wpdb->prepare("SELECT * FROM %i WHERE compensation_assignment_id=%d AND status='APPROVED'",Stockland_HR_Replacement::table('hrx_compensation_items'),(int)$comp['id']),ARRAY_A);
			foreach((array)$items as $item){if(!in_array($item['taxable_status'],array('TAXABLE','EXEMPT'),true)||!in_array($item['pensionable_status'],array('PENSIONABLE','NON_PENSIONABLE'),true)){$issues[]=array('code'=>'COMPENSATION_CLASSIFICATION_REVIEW','employment_code'=>$e['employment_code'],'component'=>$item['component_code'],'message'=>'Compensation item has unresolved tax/pension classification.');}}
			if($e['start_date']>$run['period_start']||(!empty($e['end_date'])&&$e['end_date']<$run['period_end']))$issues[]=array('code'=>'PARTIAL_PERIOD_LEGAL_RULE_REQUIRED','employment_code'=>$e['employment_code'],'message'=>'Joiner/leaver is inside the pay month. A legally approved partial-period rule must be configured before this run can calculate.');
			$time_count=(int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE employment_id=%d AND event_date BETWEEN %s AND %s AND status IN ('DRAFT','SUBMITTED')",Stockland_HR_Replacement::table('hrx_time_events'),(int)$e['id'],$run['period_start'],$run['period_end']));
			if($time_count)$issues[]=array('code'=>'UNAPPROVED_TIME','employment_code'=>$e['employment_code'],'message'=>'Unapproved attendance/time exceptions remain in the pay period.');
			$leave_count=(int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE employment_id=%d AND start_date<=%s AND (end_date IS NULL OR end_date>=%s) AND status IN ('DRAFT','SUBMITTED')",Stockland_HR_Replacement::table('hrx_leave_transactions'),(int)$e['id'],$run['period_end'],$run['period_start']));
			if($leave_count)$issues[]=array('code'=>'UNAPPROVED_LEAVE','employment_code'=>$e['employment_code'],'message'=>'Unapproved leave transactions overlap the pay period.');
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
			$comp_items=Stockland_DB::read_results($wpdb->prepare("SELECT * FROM %i WHERE compensation_assignment_id=%d AND status='APPROVED'",Stockland_HR_Replacement::table('hrx_compensation_items'),(int)$comp['id']),ARRAY_A);
			$items=array();foreach((array)$comp_items as $i)$items[]=array('id'=>(int)$i['id'],'component_code'=>$i['component_code'],'component_name'=>$i['component_name'],'component_type'=>$i['component_type'],'amount'=>$i['amount'],'cash_payable'=>(int)$i['cash_payable'],'taxable_status'=>$i['taxable_status'],'pensionable_status'=>$i['pensionable_status'],'legal_rule_id'=>(int)$i['legal_rule_id']);
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
			$input=array('employment_id'=>(int)$e['id'],'person_id'=>(int)$e['person_id'],'source_kind'=>'COMPENSATION','source_id'=>(int)$comp['id'],'source_version'=>(int)$comp['version_no'],'snapshot'=>array('employment_code'=>$e['employment_code'],'person_id'=>(int)$e['person_id'],'compensation_assignment_id'=>(int)$comp['id'],'base_salary'=>$comp['base_salary'],'items'=>$items,'calculation'=>$result,'rule_ids'=>array_map(static function($r){return (int)$r['id'];},$rules));
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

	public function approve_payroll_run( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;$id=(int)$request['id'];$run=$this->run_row($id);if(is_wp_error($run))return $run;
		if('SUBMITTED'!==$run['status'])return $this->error('hrx_run_not_submitted','Only a submitted payroll run can be approved.',409);
		if((int)$run['submitted_by']===get_current_user_id())return $this->error('hrx_no_self_approval','The payroll preparer cannot approve the same payroll run.',403);
		$body=$this->body($request);$note=sanitize_textarea_field((string)($body['approval_note']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));
		if(strlen(trim($note))<30||!$key)return $this->error('hrx_approval_evidence_required','Owner approval note (30+ characters) and idempotency key are required.',422);
		$pre=$this->payroll_preflight($request);$pd=(array)$pre->get_data();if(empty($pd['ready']))return $this->error('hrx_preflight_changed','Current source data or legal rules no longer pass preflight. Do not approve this stale run.',409);
		$config=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC LIMIT 1",Stockland_HR_Replacement::table('hrx_accounting_config'),$run['period_end'],$run['period_start']),ARRAY_A);
		if(!$config)return $this->error('hrx_gl_mapping_missing','No approved effective-dated payroll GL account mapping is available.',409);
		$line_totals=Stockland_DB::read_row($wpdb->prepare('SELECT COUNT(*) n,COALESCE(SUM(gross_pay),0) gross,COALESCE(SUM(paye),0) paye,COALESCE(SUM(employee_pension),0) employee_pension,COALESCE(SUM(employer_pension),0) employer_pension,COALESCE(SUM(other_deductions),0) other_deductions,COALESCE(SUM(net_pay),0) net FROM %i WHERE payroll_run_id=%d',Stockland_HR_Replacement::table('hrx_payroll_lines'),$id),ARRAY_A);
		if(!$line_totals||(int)$line_totals['n']!==(int)$run['employee_count'])return $this->error('hrx_line_reconciliation_failed','Payroll line count does not reconcile to the frozen run.',409);
		foreach(array('gross'=>'gross_total','paye'=>'paye_total','employee_pension'=>'employee_pension_total','employer_pension'=>'employer_pension_total','other_deductions'=>'deduction_total','net'=>'net_total') as $col=>$runcol){if(Stockland_Money::minor($line_totals[$col])!==Stockland_Money::minor($run[$runcol]))return $this->error('hrx_total_mismatch','Payroll totals do not reconcile. Approval is blocked.',409);}
		$debit_base=Stockland_Money::add($run['gross_total'],$run['employer_pension_total']);
		$credit=Stockland_Money::add($run['net_total'],Stockland_Money::add($run['paye_total'],Stockland_Money::add($run['employee_pension_total'],Stockland_Money::add($run['employer_pension_total'],$run['deduction_total']))));
		$delta=Stockland_Money::subtract($debit_base,$credit);
		if(Stockland_Money::minor($delta)!==0)return $this->error('hrx_journal_unbalanced','Payroll journal preview does not balance; fix supported earning/deduction mappings before approval.',409);
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

	public function close_payroll_run( $request ) {
		global $wpdb;
		$ready=$this->require_schema();if(is_wp_error($ready))return $ready;$id=(int)$request['id'];$run=$this->run_row($id);if(is_wp_error($run))return $run;
		$body=$this->body($request);$note=sanitize_textarea_field((string)($body['reconciliation_note']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));
		if(!in_array($run['status'],array('SETTLEMENT_PENDING','SETTLED'),true)||strlen(trim($note))<30||!$key)return $this->error('hrx_close_evidence_required','Close requires settlement state, bank/payout and remittance reconciliation evidence, and an idempotency key.',422);
		$payout=Stockland_DB::read_row($wpdb->prepare("SELECT COUNT(*) total,SUM(CASE WHEN status='SETTLED' THEN 1 ELSE 0 END) settled,SUM(CASE WHEN status IN ('FAILED','RETURNED') THEN 1 ELSE 0 END) failed FROM %i WHERE payout_batch_id IN (SELECT id FROM %i WHERE payroll_run_id=%d)",Stockland_HR_Replacement::table('hrx_payout_items'),Stockland_HR_Replacement::table('hrx_payout_batches'),$id),ARRAY_A);
		if(!$payout||(int)$payout['total']===0||(int)$payout['settled']!==(int)$payout['total']||(int)$payout['failed']>0)return $this->error('hrx_payout_reconciliation_incomplete','Every employee payout must be individually settled. Failed/returned payments need a resolved recovery workflow before close.',409);
		$open= (int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE payroll_run_id=%d AND status<>'RECONCILED'",Stockland_HR_Replacement::table('hrx_statutory_obligations'),$id));
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
		if(empty($rule['reviewer_qualification'])||empty($rule['source_sha256'])||empty($rule['test_vectors_sha256']))return $this->error('hrx_rule_evidence_missing','Qualified reviewer, official-source SHA-256 and test-vector SHA-256 are required.',422);
		$to='APPROVE'===$decision?'APPROVED':'LEGAL_REVIEW';if('APPROVE'===$decision&&'LEGAL_REVIEW'!==$rule['review_status'])return $this->error('hrx_rule_state_invalid','Only a LEGAL_REVIEW rule can be approved.',409);
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_legal_rules'),array('review_status'=>$to,'reviewer_name'=>sanitize_text_field($body['reviewer_name']??''),'reviewer_qualification'=>sanitize_text_field($body['reviewer_qualification']??$rule['reviewer_qualification']),'reviewed_at'=>Stockland_HR_Replacement::now(),'approved_by'=>'APPROVE'===$decision?get_current_user_id():null,'approved_at'=>'APPROVE'===$decision?Stockland_HR_Replacement::now():null),array('id'=>$id,'review_status'=>$rule['review_status']));
		if(false===$ok)return $this->error('hrx_rule_update_failed','Rule review did not persist.',500);
		$evt=Stockland_HR_Replacement::approval_event('legal-rule',$id,'LEGAL_RULE_'.$decision,$rule['review_status'],$to,$note,$key,null,get_current_user_id());$audit=Stockland_HR_Replacement::audit('legal_rule_'.$decision,'legal-rule',$id,'Legal rule review state changed.');
		if(is_wp_error($evt)||is_wp_error($audit)||false===$audit)return $this->error('hrx_rule_audit_failed','Rule state changed but the audit write failed; investigate immediately and do not use this rule.');
		return rest_ensure_response(array('id'=>$id,'review_status'=>$to,'message'=>'Rule review captured. Do not enable any payroll rule until the qualified professional confirms the authoritative instrument and effective dates.'));
	}

	public function classify_payee_request( $request ) {
		global $wpdb;$id=(int)$request['id'];$body=$this->body($request);
		$req=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_payee_requests'),$id),ARRAY_A);if(!$req)return $this->error('hrx_payee_request_not_found','Payee request not found.',404);
		$classification=sanitize_key($body['classification']??'');$status=strtoupper(sanitize_key($body['status']??'LEGAL_REVIEW'));$reason=sanitize_textarea_field((string)($body['reason']??''));$rule_id=(int)($body['rule_id']??0);$rate=$body['withholding_rate']??null;
		if(strlen(trim($reason))<40||!in_array($status,array('APPROVED','LEGAL_REVIEW','NEEDS_INFORMATION','REJECTED'),true))return $this->error('hrx_payee_classification_invalid','Classification decision requires a reason and valid review state.',422);
		if('APPROVED'===$status){$rule=$rule_id?Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d AND review_status="APPROVED"',Stockland_HR_Replacement::table('hrx_legal_rules'),$rule_id),ARRAY_A):null;if(!$rule)return $this->error('hrx_payee_rule_unapproved','An approved effective-dated legal rule is required to clear tax classification.',409);}
		$ok=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_payee_classifications'),array('payee_request_id'=>$id,'rule_id'=>$rule_id?:null,'classification'=>$classification,'withholding_required'=>'APPROVED'===$status?'RULE_DECIDED':'LEGAL_REVIEW','withholding_rate'=>null===$rate?null:(string)$rate,'reason'=>$reason,'reviewer_user_id'=>get_current_user_id(),'reviewed_at'=>Stockland_HR_Replacement::now(),'status'=>$status,'created_by'=>get_current_user_id(),'created_at'=>Stockland_HR_Replacement::now()));
		if(false===$ok)return $this->error('hrx_payee_classification_save_failed','Classification record could not be saved.',500);
		Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payee_requests'),array('classification_status'=>$status,'withholding_rule_id'=>$rule_id?:null,'updated_at'=>Stockland_HR_Replacement::now()),array('id'=>$id));
		Stockland_HR_Replacement::audit('payee_classified','payee-request',$id,'Payee classification recorded; no payment was made.');
		return rest_ensure_response(array('id'=>$id,'classification_status'=>$status,'payment_allowed'=>'APPROVED'===$status));
	}

	public function approve_payee_request( $request ) {
		global $wpdb;$id=(int)$request['id'];$body=$this->body($request);
		$req=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_payee_requests'),$id),ARRAY_A);if(!$req)return $this->error('hrx_payee_request_not_found','Payee request not found.',404);
		if('APPROVED'!==$req['classification_status'])return $this->error('hrx_payee_tax_review_required','An approved legal/tax classification is mandatory before approval for payment.',409);
		if('SUBMITTED'!==$req['status'])return $this->error('hrx_payee_state_invalid','Only a submitted payee request can be approved.',409);
		$reason=sanitize_textarea_field((string)($body['approval_note']??''));if(strlen(trim($reason))<30)return $this->error('hrx_payee_approval_note_required','Independent approval rationale is required.',422);
		if((int)$req['created_by']===get_current_user_id())return $this->error('hrx_no_self_approval','Request creator cannot approve the request.',403);
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_payee_requests'),array('status'=>'APPROVED','approved_by'=>get_current_user_id(),'approved_at'=>Stockland_HR_Replacement::now(),'updated_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'status'=>'SUBMITTED','classification_status'=>'APPROVED'));
		if(false===$ok)return $this->error('hrx_payee_approval_failed','Payee request approval failed.',409);
		Stockland_HR_Replacement::approval_event('payee-request',$id,'PAYEE_APPROVED','SUBMITTED','APPROVED',$reason,wp_generate_uuid4(),null,get_current_user_id());
		Stockland_HR_Replacement::audit('payee_approved','payee-request',$id,'Approved for central Finance/AP settlement; no branch payment.');
		return rest_ensure_response(array('id'=>$id,'status'=>'APPROVED','message'=>'Approved for central AP processing. Payment and remittance evidence must be handled by the controlled finance service.'));
	}

	public function create_exit_settlement( $request ) {
		global $wpdb;$body=$this->body($request);$employment_id=(int)($body['employment_id']??0);
		$emp=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_employments'),$employment_id),ARRAY_A);if(!$emp)return $this->error('hrx_employment_not_found','Employment relationship not found.',404);
		$exit_type=sanitize_key($body['exit_type']??'');$reason=sanitize_textarea_field((string)($body['exit_reason']??''));$last=Stockland_HR_Replacement::valid_date($body['final_working_date']??'');$effective=Stockland_HR_Replacement::valid_date($body['effective_end_date']??'');
		if(!$exit_type||strlen(trim($reason))<20||!$last||!$effective||$effective<$last)return $this->error('hrx_exit_input_invalid','Exit type/reason and valid final/effective dates are required; effective date must be on or after final working day.',422);
		$code='EXITX-'.gmdate('Ymd').'-'.wp_generate_password(8,false,false);
		$row=array('settlement_code'=>$code,'employment_id'=>$employment_id,'exit_type'=>$exit_type,'exit_reason'=>$reason,'notice_date'=>Stockland_HR_Replacement::valid_date($body['notice_date']??'')?:null,'final_working_date'=>$last,'effective_end_date'=>$effective,'status'=>'DRAFT','created_by'=>get_current_user_id(),'created_at'=>Stockland_HR_Replacement::now(),'version_no'=>1);
		if(false===Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_exit_settlements'),$row))return $this->error('hrx_exit_insert_failed','Final settlement request could not be created.',409);
		$id=(int)$wpdb->insert_id;Stockland_HR_Replacement::approval_event('exit-settlement',$id,'EXIT_DRAFT_CREATED',null,'DRAFT','Exit request created',$code);Stockland_HR_Replacement::audit('exit_draft_created','exit-settlement',$id,'Exit initiated; employee remains active until required approvals.');
		return rest_ensure_response(array('id'=>$id,'settlement_code'=>$code,'status'=>'DRAFT','message'=>'Draft created. The settlement remains blocked until every earning/deduction has qualified legal-rule evidence.'));
	}

	public function add_exit_item( $request ) {
		global $wpdb;$id=(int)$request['id'];$body=$this->body($request);
		$exit=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_exit_settlements'),$id),ARRAY_A);if(!$exit)return $this->error('hrx_exit_not_found','Final settlement not found.',404);
		if(!in_array($exit['status'],array('DRAFT','CALCULATED'),true))return $this->error('hrx_exit_locked','Only draft/freshly calculated settlement items may be edited.',409);
		$code=sanitize_key($body['item_code']??'');$label=sanitize_text_field($body['item_label']??'');$type=strtoupper(sanitize_key($body['item_type']??''));$amount=$body['amount']??null;$rule_id=(int)($body['rule_id']??0);$source=sanitize_text_field((string)($body['source_reference']??''));$trace=sanitize_textarea_field((string)($body['calculation_trace']??''));
		if(!$code||!$label||!in_array($type,array('EARNING','DEDUCTION'),true)||null===$amount||$rule_id<1||strlen(trim($trace))<20)return $this->error('hrx_exit_item_evidence_required','Item code/type/amount, approved rule ID and calculation trace are required.',422);
		$rule=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND review_status='APPROVED'",Stockland_HR_Replacement::table('hrx_legal_rules'),$rule_id),ARRAY_A);if(!$rule)return $this->error('hrx_exit_rule_unapproved','Final settlement item requires a currently approved legal rule.',409);
		try{$money=Stockland_Money::decimal(Stockland_Money::minor($amount));}catch(Throwable $e){return $this->error('hrx_exit_amount_invalid','Invalid settlement amount.',422);}
		if(Stockland_Money::minor($money)<0)return $this->error('hrx_exit_amount_negative','Use positive amount and correct earning/deduction type.',422);
		$ok=Stockland_DB::insert_record(Stockland_HR_Replacement::table('hrx_exit_items'),array('settlement_id'=>$id,'item_code'=>$code,'item_label'=>$label,'item_type'=>$type,'amount'=>$money,'rule_id'=>$rule_id,'source_reference'=>$source,'calculation_trace'=>$trace,'created_at'=>Stockland_HR_Replacement::now()));
		if(false===$ok)return $this->error('hrx_exit_item_insert_failed','Settlement item could not be saved.',500);
		Stockland_HR_Replacement::audit('exit_item_added','exit-settlement',$id,'Approved-rule-based settlement item added.');
		return rest_ensure_response(array('settlement_id'=>$id,'added'=>true));
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
		global $wpdb;$id=(int)$request['id'];$body=$this->body($request);$exit=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',Stockland_HR_Replacement::table('hrx_exit_settlements'),$id),ARRAY_A);if(!$exit)return $this->error('hrx_exit_not_found','Final settlement not found.',404);
		$note=sanitize_textarea_field((string)($body['approval_note']??''));$key=sanitize_text_field((string)($body['idempotency_key']??''));
		if('CALCULATED'!==$exit['status']||strlen(trim($note))<40||!$key)return $this->error('hrx_exit_approval_evidence_required','Calculated settlement, independent approval note (40+ characters) and idempotency key are required.',409);
		if((int)$exit['created_by']===get_current_user_id())return $this->error('hrx_no_self_approval','Settlement creator may not approve the final settlement.',403);
		$ok=Stockland_DB::update_record(Stockland_HR_Replacement::table('hrx_exit_settlements'),array('status'=>'APPROVED','approved_by'=>get_current_user_id(),'approved_at'=>Stockland_HR_Replacement::now()),array('id'=>$id,'status'=>'CALCULATED'));
		if(false===$ok)return $this->error('hrx_exit_approval_failed','Final settlement approval failed.',409);
		Stockland_HR_Replacement::approval_event('exit-settlement',$id,'EXIT_APPROVED','CALCULATED','APPROVED',$note,$key,null,get_current_user_id());Stockland_HR_Replacement::audit('exit_approved','exit-settlement',$id,'Owner approved rule-backed final settlement.');
		// Employment is not deactivated here: only the separately evidenced payout/clearance path may finalize it.
		return rest_ensure_response(array('id'=>$id,'status'=>'APPROVED','message'=>'Approved settlement; employee status and access are not changed by this endpoint.'));
	}

	public function document_authorize( $request ) {
		// Private storage adapter is deliberately fail-closed until a host-approved private vault exists.
		$id=(int)$request['id'];
		return $this->error('hrx_private_vault_not_configured','Secure private document storage is not configured in this candidate. No public media URL or document bytes are returned.',409);
	}

	private function begin() {
		$ok=Stockland_DB::execute_query('START TRANSACTION');
		return false!==$ok;
	}
	private function commit() { return Stockland_DB::execute_query('COMMIT'); }
	private function rollback() { return Stockland_DB::execute_query('ROLLBACK'); }
}
