<?php
/**
 * Server-authoritative API for the new HR/payroll system of record.
 * No endpoint reads or writes the legacy four-table HR/payroll system.
 *
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Stockland_REST_HR_Replacement {
	const NS = STOCKLAND_REST_NAMESPACE;
	const CALCULATOR_VERSION = 'stockland-hrx-calc-v1.0.0';

	public function __construct() {
		add_action( 'stockland_register_rest_routes', array( $this, 'register_routes' ) );
	}

	private static function entities() {
		return array(
			'people' => array( 'table'=>'people', 'fields'=>array('person_code','display_name','legal_name','email','phone','privacy_notice_version','lawful_basis','retention_review_on'), 'required'=>array('person_code','display_name'), 'edit'=>array('display_name','legal_name','email','phone','retention_review_on'), 'pii'=>array('legal_name','email','phone'), 'group'=>'hr', 'location'=>false ),
			'workforce-plans' => array( 'table'=>'workforce_plans', 'fields'=>array('plan_code','fiscal_year','department','location_id','position_id','requested_headcount','budget_amount','currency','justification','effective_from','effective_to'), 'required'=>array('plan_code','fiscal_year','department','justification'), 'edit'=>array('requested_headcount','budget_amount','justification','effective_from','effective_to'), 'group'=>'hr', 'location'=>true ),
			'positions' => array( 'table'=>'positions', 'fields'=>array('position_code','title','department','grade_code','location_id','manager_person_id','reporting_position_id','budget_code','effective_from','effective_to'), 'required'=>array('position_code','title','department'), 'edit'=>array('title','department','grade_code','manager_person_id','effective_to'), 'group'=>'hr', 'location'=>true ),
			'requisitions' => array( 'table'=>'requisitions', 'fields'=>array('requisition_code','workforce_plan_id','position_id','requester_user_id','location_id','openings','priority','justification','open_date','close_date'), 'required'=>array('requisition_code','position_id','requester_user_id','openings','justification'), 'edit'=>array('priority','justification','open_date','close_date'), 'group'=>'hr', 'location'=>true ),
			'applications' => array( 'table'=>'applications', 'fields'=>array('application_code','requisition_id','person_id','applied_at','source','retention_review_on','privacy_notice_version'), 'required'=>array('application_code','requisition_id','person_id','applied_at'), 'edit'=>array('source','retention_review_on'), 'group'=>'hr', 'location'=>false ),
			'interview-events' => array( 'table'=>'interview_events', 'fields'=>array('application_id','scheduled_at','interviewer_user_id','rubric_version','score_decimal','evidence_ref','decision','rationale'), 'required'=>array('application_id','interviewer_user_id','rubric_version'), 'edit'=>array(), 'group'=>'hr', 'location'=>false, 'immutable'=>true ),
			'offers' => array( 'table'=>'offers', 'fields'=>array('offer_code','application_id','contract_type','proposed_start','proposed_end','probation_end','terms_ref'), 'required'=>array('offer_code','application_id','contract_type','proposed_start'), 'edit'=>array('proposed_end','probation_end','terms_ref'), 'group'=>'hr', 'location'=>false ),
			'employment-contracts' => array( 'table'=>'employment_contracts', 'fields'=>array('contract_code','person_id','offer_id','contract_type','legal_employer','department','job_title','location_id','manager_person_id','start_date','end_date','probation_end','weekly_hours','schedule_json','notice_terms_ref','supersedes_contract_id'), 'required'=>array('contract_code','person_id','contract_type','legal_employer','department','job_title','start_date'), 'edit'=>array('department','job_title','location_id','manager_person_id','end_date','probation_end','weekly_hours','schedule_json','notice_terms_ref'), 'group'=>'hr', 'location'=>true ),
			'onboarding-tasks' => array( 'table'=>'onboarding_tasks', 'fields'=>array('case_code','person_id','contract_id','task_key','task_title','mandatory','owner_user_id','due_at','evidence_ref','blocker_note'), 'required'=>array('case_code','person_id','task_key','task_title'), 'edit'=>array('owner_user_id','due_at','evidence_ref','blocker_note'), 'group'=>'hr', 'location'=>false ),
			'employee-documents' => array( 'table'=>'employee_documents', 'fields'=>array('subject_type','subject_id','document_type','private_file_ref','file_sha256','issued_on','expires_on','access_class','retention_review_on'), 'required'=>array('subject_type','subject_id','document_type','private_file_ref'), 'edit'=>array(), 'group'=>'hr', 'location'=>false, 'immutable'=>true, 'pii'=>array('private_file_ref','file_sha256') ),
			'shift-patterns' => array( 'table'=>'shift_patterns', 'fields'=>array('pattern_code','title','timezone','schedule_json','weekly_rest_json','effective_from','effective_to'), 'required'=>array('pattern_code','title','schedule_json','effective_from'), 'edit'=>array('title','schedule_json','weekly_rest_json','effective_to'), 'group'=>'hr', 'location'=>false ),
			'shift-assignments' => array( 'table'=>'shift_assignments', 'fields'=>array('person_id','pattern_id','location_id','effective_from','effective_to'), 'required'=>array('person_id','pattern_id','effective_from'), 'edit'=>array('effective_to'), 'group'=>'hr', 'location'=>true ),
			'attendance-records' => array( 'table'=>'attendance_records', 'fields'=>array('attendance_code','person_id','shift_assignment_id','location_id','work_date','clock_in','clock_out','break_minutes','ordinary_minutes','overtime_minutes','time_source','evidence_ref','correction_of_id','correction_reason'), 'required'=>array('attendance_code','person_id','work_date','time_source'), 'edit'=>array('evidence_ref','correction_reason'), 'group'=>'hr', 'location'=>true ),
			'leave-policies' => array( 'table'=>'leave_policies', 'fields'=>array('policy_code','title','leave_kind','paid_flag','eligibility_json','accrual_method','accrual_amount','carry_cap','effective_from','effective_to'), 'required'=>array('policy_code','title','leave_kind','eligibility_json','accrual_method','effective_from'), 'edit'=>array('eligibility_json','carry_cap','effective_to'), 'group'=>'hr', 'location'=>false ),
			'leave-requests' => array( 'table'=>'leave_requests', 'fields'=>array('request_code','person_id','policy_id','date_from','date_to','units','reason','evidence_ref'), 'required'=>array('request_code','person_id','policy_id','date_from','date_to','units'), 'edit'=>array('reason','evidence_ref'), 'group'=>'hr', 'location'=>false ),
			'compensation-components' => array( 'table'=>'compensation_components', 'fields'=>array('component_code','label','component_kind','unit_kind','frequency','description','default_rule_tag','requires_evidence','effective_from','effective_to'), 'required'=>array('component_code','label','component_kind','effective_from'), 'edit'=>array('label','description','default_rule_tag','effective_to'), 'group'=>'payroll', 'location'=>false ),
			'compensation-assignments' => array( 'table'=>'compensation_assignments', 'fields'=>array('assignment_code','person_id','component_id','amount','currency','frequency','quantity','effective_from','effective_to','reason_code','evidence_ref','supersedes_id'), 'required'=>array('assignment_code','person_id','component_id','amount','effective_from','reason_code'), 'edit'=>array('effective_to','reason_code','evidence_ref'), 'group'=>'payroll', 'location'=>false ),
			'benefit-enrollments' => array( 'table'=>'benefit_enrollments', 'fields'=>array('enrollment_code','person_id','benefit_code','coverage_from','coverage_to','employee_share','employer_share','currency','evidence_ref'), 'required'=>array('enrollment_code','person_id','benefit_code','coverage_from'), 'edit'=>array('coverage_to','evidence_ref'), 'group'=>'payroll', 'location'=>false ),
			'rule-sets' => array( 'table'=>'rule_sets', 'fields'=>array('rule_code','jurisdiction','rule_kind','title','version_label','source_title','source_clause','official_url','source_sha256','publication_date','effective_from','effective_to','tax_base_basis','rounding_method','reviewer_user_id','professional_signoff_ref','test_vector_sha256'), 'required'=>array('rule_code','rule_kind','title','version_label','source_title','source_clause','official_url','effective_from'), 'edit'=>array('effective_to','professional_signoff_ref','test_vector_sha256'), 'group'=>'owner', 'location'=>false ),
			'rule-components' => array( 'table'=>'rule_components', 'fields'=>array('rule_set_id','component_code','component_kind','parameters_json','sort_order','test_case_ref'), 'required'=>array('rule_set_id','component_code','component_kind','parameters_json'), 'edit'=>array('parameters_json','sort_order','test_case_ref'), 'group'=>'owner', 'location'=>false ),
			'payroll-periods' => array( 'table'=>'payroll_periods', 'fields'=>array('period_code','period_kind','period_start','period_end','input_cutoff','payment_date'), 'required'=>array('period_code','period_start','period_end','input_cutoff'), 'edit'=>array('input_cutoff','payment_date'), 'group'=>'payroll', 'location'=>false ),
			'payroll-runs' => array( 'table'=>'payroll_runs', 'fields'=>array('run_code','period_id','run_version','run_kind','calculator_version','rule_set_id'), 'required'=>array('run_code','period_id','rule_set_id'), 'edit'=>array(), 'group'=>'payroll', 'location'=>false, 'immutable'=>true ),
			'separation-cases' => array( 'table'=>'separation_cases', 'fields'=>array('case_code','person_id','exit_type','reason_code','notice_date','proposed_exit_date','legal_review_ref'), 'required'=>array('case_code','person_id','exit_type','reason_code','proposed_exit_date'), 'edit'=>array('notice_date','legal_review_ref'), 'group'=>'hr', 'location'=>false ),
			'learning-records' => array( 'table'=>'learning_records', 'fields'=>array('person_id','record_code','learning_kind','title','provider','started_on','completed_on','expires_on','evidence_ref'), 'required'=>array('person_id','record_code','learning_kind','title'), 'edit'=>array('completed_on','expires_on','evidence_ref'), 'group'=>'hr', 'location'=>false ),
			'performance-reviews' => array( 'table'=>'performance_reviews', 'fields'=>array('review_code','person_id','period_start','period_end','reviewer_user_id','rubric_version','ratings_json','evidence_ref','outcome_code'), 'required'=>array('review_code','person_id','period_start','period_end','reviewer_user_id','rubric_version','ratings_json'), 'edit'=>array('ratings_json','evidence_ref','outcome_code'), 'group'=>'hr', 'location'=>false, 'pii'=>array('ratings_json') ),
			'individual-payees' => array( 'table'=>'individual_payees', 'fields'=>array('payee_code','payee_kind','display_name','contact_email','contact_phone','taxpayer_identity_ref','tin_status','business_license_status','residency_code','privacy_notice_version','retention_review_on'), 'required'=>array('payee_code','payee_kind','display_name'), 'edit'=>array('contact_email','contact_phone','tin_status','business_license_status','residency_code','retention_review_on'), 'group'=>'payroll', 'location'=>false, 'pii'=>array('contact_email','contact_phone','taxpayer_identity_ref') ),
			'payee-engagements' => array( 'table'=>'payee_engagements', 'fields'=>array('engagement_code','payee_id','engagement_kind','campaign_code','contract_ref','location_id','requested_by','agreed_on','service_from','service_to','cash_fee','noncash_value','currency','acceptance_required','budget_ref'), 'required'=>array('engagement_code','payee_id','engagement_kind','requested_by','service_from'), 'edit'=>array('contract_ref','service_to','cash_fee','noncash_value','budget_ref'), 'group'=>'payroll', 'location'=>true ),
			'payee-deliverables' => array( 'table'=>'payee_deliverables', 'fields'=>array('deliverable_code','engagement_id','title','due_on','evidence_ref','cash_value','noncash_value'), 'required'=>array('deliverable_code','engagement_id','title'), 'edit'=>array('due_on','evidence_ref','cash_value','noncash_value'), 'group'=>'payroll', 'location'=>false ),
			'payee-tax-decisions' => array( 'table'=>'payee_tax_decisions', 'fields'=>array('decision_code','engagement_id','classification','rule_set_id','source_clause','decision_reason','withholding_rate','withholding_amount','base_amount','evidence_ref'), 'required'=>array('decision_code','engagement_id','classification','decision_reason'), 'edit'=>array('evidence_ref'), 'group'=>'owner', 'location'=>false ),
			'payee-payments' => array( 'table'=>'payee_payments', 'fields'=>array('payment_code','engagement_id','payee_id','deliverable_id','tax_decision_id','gross_amount','withholding_amount','net_amount','currency','payment_method','payment_ref','idempotency_key'), 'required'=>array('payment_code','engagement_id','payee_id','gross_amount','net_amount','payment_method','idempotency_key'), 'edit'=>array(), 'group'=>'payroll', 'location'=>false, 'immutable'=>true ),
			'social-accounts' => array( 'table'=>'social_accounts', 'fields'=>array('account_code','platform','display_label','official_url','owner_user_id','manager_user_id','permitted_scopes_json'), 'required'=>array('account_code','platform','display_label','official_url'), 'edit'=>array('display_label','official_url','manager_user_id','permitted_scopes_json'), 'group'=>'hr', 'location'=>false ),
			'campaigns' => array( 'table'=>'campaigns', 'fields'=>array('campaign_code','title','account_id','payee_id','engagement_id','start_date','end_date','cash_budget','gifted_stock_cost','currency','owner_user_id'), 'required'=>array('campaign_code','title','start_date','owner_user_id'), 'edit'=>array('end_date','cash_budget','gifted_stock_cost'), 'group'=>'payroll', 'location'=>false ),
			'campaign-snapshots' => array( 'table'=>'campaign_snapshots', 'fields'=>array('campaign_id','period_start','period_end','captured_at','source_platform','source_ref','metrics_json'), 'required'=>array('campaign_id','period_start','period_end','captured_at','source_platform','metrics_json'), 'edit'=>array(), 'group'=>'hr', 'location'=>false, 'immutable'=>true ),
			'resource-documents' => array( 'table'=>'resource_documents', 'fields'=>array('document_code','function_name','role_code','language_code','document_type','version_label','title','state','private_file_ref','file_sha256','mime_type','size_bytes','effective_on','supersedes_id','legal_source_ref'), 'required'=>array('document_code','function_name','language_code','document_type','version_label','title','private_file_ref','file_sha256','mime_type','size_bytes'), 'edit'=>array(), 'group'=>'hr', 'location'=>false, 'immutable'=>true ),
		);
	}

	public function register_routes() {
		register_rest_route( self::NS, '/hrx/bootstrap', array( 'methods'=>'GET', 'callback'=>array($this,'bootstrap'), 'permission_callback'=>array($this,'can_view_hr') ) );
		register_rest_route( self::NS, '/hrx/schema/status', array( 'methods'=>'GET', 'callback'=>array($this,'schema_status'), 'permission_callback'=>array($this,'can_view_hr') ) );
		register_rest_route( self::NS, '/hrx/schema/install', array( 'methods'=>'POST', 'callback'=>array($this,'install_schema'), 'permission_callback'=>array($this,'can_install_schema') ) );
		register_rest_route( self::NS, '/hrx/schema/cutover', array( 'methods'=>'POST', 'callback'=>array($this,'cutover'), 'permission_callback'=>array($this,'can_install_schema') ) );
		$entity_pattern = implode( '|', array_map( 'preg_quote', array_keys( self::entities() ) ) );
		register_rest_route( self::NS, '/hrx/(?P<entity>' . $entity_pattern . ')', array(
			array( 'methods'=>'GET', 'callback'=>array($this,'list_records'), 'permission_callback'=>array($this,'can_access_entity') ),
			array( 'methods'=>'POST', 'callback'=>array($this,'create_record'), 'permission_callback'=>array($this,'can_access_entity') ),
		) );
		register_rest_route( self::NS, '/hrx/(?P<entity>' . $entity_pattern . ')/(?P<id>\d+)', array(
			array( 'methods'=>'GET', 'callback'=>array($this,'get_record'), 'permission_callback'=>array($this,'can_access_entity') ),
			array( 'methods'=>'POST', 'callback'=>array($this,'update_record'), 'permission_callback'=>array($this,'can_access_entity') ),
		) );
		register_rest_route( self::NS, '/hrx/(?P<entity>' . $entity_pattern . ')/(?P<id>\d+)/transition', array( 'methods'=>'POST', 'callback'=>array($this,'transition'), 'permission_callback'=>array($this,'can_access_entity') ) );
		register_rest_route( self::NS, '/hrx/payroll-runs/(?P<id>\d+)/calculate', array( 'methods'=>'POST', 'callback'=>array($this,'calculate_run'), 'permission_callback'=>array($this,'can_manage_payroll') ) );
		register_rest_route( self::NS, '/hrx/payroll-runs/(?P<id>\d+)/submit', array( 'methods'=>'POST', 'callback'=>array($this,'submit_run'), 'permission_callback'=>array($this,'can_manage_payroll') ) );
		register_rest_route( self::NS, '/hrx/payroll-runs/(?P<id>\d+)/approve', array( 'methods'=>'POST', 'callback'=>array($this,'approve_run'), 'permission_callback'=>array($this,'can_approve_payroll') ) );
		register_rest_route( self::NS, '/hrx/payroll-runs/(?P<id>\d+)/post', array( 'methods'=>'POST', 'callback'=>array($this,'post_run'), 'permission_callback'=>array($this,'can_approve_payroll') ) );
		register_rest_route( self::NS, '/hrx/payroll-runs/(?P<id>\d+)/create-disbursements', array( 'methods'=>'POST', 'callback'=>array($this,'create_disbursements'), 'permission_callback'=>array($this,'can_approve_payroll') ) );
		register_rest_route( self::NS, '/hrx/payroll-runs/(?P<id>\d+)/disbursement-results', array( 'methods'=>'POST', 'callback'=>array($this,'disbursement_results'), 'permission_callback'=>array($this,'can_approve_payroll') ) );
		register_rest_route( self::NS, '/hrx/separation-cases/(?P<id>\d+)/prepare-final-run', array( 'methods'=>'POST', 'callback'=>array($this,'prepare_final_run'), 'permission_callback'=>array($this,'can_approve_payroll') ) );
		register_rest_route( self::NS, '/hrx/reports/summary', array( 'methods'=>'GET', 'callback'=>array($this,'reports_summary'), 'permission_callback'=>array($this,'can_view_hr') ) );
	}

	public function can_view_hr() {
		return ( class_exists('Stockland_Authority') && Stockland_Authority::can_view_hr() ) || ( class_exists('Stockland_Authority') && Stockland_Authority::can_view_payroll() );
	}
	public function can_install_schema() {
		return current_user_can('stockland_install_schema') && class_exists('Stockland_Authority') && Stockland_Authority::is_owner();
	}
	public function can_manage_payroll() {
		return class_exists('Stockland_Authority') && Stockland_Authority::can_manage_payroll();
	}
	public function can_approve_payroll() {
		return class_exists('Stockland_Authority') && Stockland_Authority::can_approve_payroll();
	}
	public function can_access_entity( $request ) {
		$entity = $this->entity_for_request($request);
		if ( ! $entity ) { return false; }
		if ( ! $this->can_view_hr() ) { return false; }
		if ( 'GET' === $request->get_method() ) { return true; }
		$meta = self::entities()[$entity];
		if ( 'owner' === $meta['group'] ) { return $this->can_approve_payroll(); }
		if ( 'payroll' === $meta['group'] ) { return $this->can_manage_payroll() || $this->can_approve_payroll(); }
		return class_exists('Stockland_Authority') && Stockland_Authority::can_manage_hr();
	}
	private function entity_for_request( $request ) {
		$entity = sanitize_key( (string) $request->get_param('entity') );
		return isset(self::entities()[$entity]) ? $entity : '';
	}

	public function bootstrap() {
		$schema = Stockland_HR_Replacement::schema_status();
		$active = (bool) get_option('stockland_hr_replacement_active',0);
		$summary = array();
		if ( $schema['schema_ready'] ) {
			global $wpdb;
			foreach ( self::entities() as $slug=>$meta ) {
				if ( in_array($slug,array('employee-documents','interview-events','campaign-snapshots','payroll-approvals'),true) ) { continue; }
				$summary[$slug] = (int) Stockland_DB::read_var( $wpdb->prepare('SELECT COUNT(*) FROM %i',Stockland_HR_Replacement::table($meta['table'])) );
			}
		}
		return rest_ensure_response(array(
			'mode'=>$active?'REPLACEMENT':'LEGACY',
			'schema'=>$schema,
			'integrity'=>$schema['schema_ready']?Stockland_HR_Replacement::integrity_summary():null,
			'summary'=>$summary,
			'principles'=>array('legacy_hr_tables_read_only_migration_sources','tax_and_pension_rules_require_professional_signoff','gl_posting_and_external_settlement_are_distinct','no_sample_employee_or_salary_data'),
		));
	}
	public function schema_status() { return rest_ensure_response( Stockland_HR_Replacement::schema_status() ); }
	public function install_schema() { return Stockland_HR_Replacement::install(); }
	public function cutover( $request ) {
		$p = $request->get_json_params(); $active = ! empty($p['active']);
		if ( $active && empty($p['acceptance_reference']) ) { return new WP_Error('hrx_acceptance_reference_required','Cutover activation requires the signed staging acceptance reference.',array('status'=>400)); }
		$result = Stockland_HR_Replacement::set_cutover_active($active);
		if ( is_wp_error($result) ) { return $result; }
		if ( ! $this->audit_event('HRX_CUTOVER',0,null,$active?'ACTIVE':'INACTIVE','Acceptance ref withheld from general logs') ) { return new WP_Error('hrx_audit_failed','Cutover state changed but audit logging did not confirm. Stop and reconcile before any further action.',array('status'=>500)); }
		$result['acceptance_reference_recorded'] = true;
		return rest_ensure_response($result);
	}

	public function list_records( $request ) {
		global $wpdb;
		$entity = $this->entity_for_request($request); if(!$entity)return new WP_Error('hrx_entity_unknown','Unknown HR entity.',array('status'=>404));
		if ( ! Stockland_HR_Replacement::schema_status()['schema_ready'] ) { return new WP_Error('hrx_schema_pending','Replacement schema is not installed; no HR data was read.',array('status'=>503)); }
		$meta = self::entities()[$entity]; $table = Stockland_HR_Replacement::table($meta['table']);
		$limit = min(100,max(1,absint($request->get_param('per_page')?:50)));
		$offset = max(0,absint($request->get_param('offset')?:0));
		$where = array(); $values = array();
		$filter = sanitize_key((string)$request->get_param('status'));
		if ( $filter && $this->table_has_column($table,'status') ) { $where[]='status=%s'; $values[]=$filter; }
		if ( ! Stockland_Authority::company_scope() ) {
			if ( ! $meta['location'] ) { return new WP_Error('hrx_scope_denied','This record type is not available outside company-wide HR scope.',array('status'=>403)); }
			$ids = Stockland_Authority::location_ids();
			if ( ! $ids ) { return rest_ensure_response(array('items'=>array(),'total'=>0,'limit'=>$limit,'offset'=>$offset)); }
			$placeholders = implode(',',array_fill(0,count($ids),'%d'));
			$where[] = 'location_id IN (' . $placeholders . ')';
			foreach($ids as $id)$values[]=(int)$id;
		}
		$sql = 'SELECT * FROM %i' . ( $where ? ' WHERE '.implode(' AND ',$where) : '' ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		array_unshift($values,$table); $values[]=$limit; $values[]=$offset;
		$prepared = $wpdb->prepare($sql,$values);
		$rows = Stockland_DB::read_results($prepared,ARRAY_A);
		if ( '' !== (string)$wpdb->last_error ) { return new WP_Error('hrx_query_failed','HR query failed; no zero or empty result is being reported as success.',array('status'=>500)); }
		$count_sql = 'SELECT COUNT(*) FROM %i' . ( $where ? ' WHERE '.implode(' AND ',$where) : '' );
		$count_values = array_merge(array($table),$values);
		array_pop($count_values); array_pop($count_values);
		$total = (int)Stockland_DB::read_var($wpdb->prepare($count_sql,$count_values));
		$rows = array_map(array($this,'redact_row'),$rows);
		return rest_ensure_response(array('items'=>$rows,'total'=>$total,'limit'=>$limit,'offset'=>$offset));
	}

	public function get_record( $request ) {
		if ( ! Stockland_HR_Replacement::schema_status()['schema_ready'] ) { return new WP_Error('hrx_schema_pending','Replacement schema is not installed.',array('status'=>503)); }
		$entity=$this->entity_for_request($request); $meta=self::entities()[$entity]; $id=absint($request['id']);
		$row=$this->read_one($meta['table'],$id); if(!$row)return new WP_Error('hrx_not_found','HR record not found.',array('status'=>404));
		if(!$this->row_access($meta,$row))return new WP_Error('hrx_scope_denied','The record is outside your authorized scope.',array('status'=>403));
		return rest_ensure_response($this->redact_row($row));
	}

	public function create_record( $request ) {
		global $wpdb;
		if ( ! Stockland_HR_Replacement::schema_status()['schema_ready'] ) { return new WP_Error('hrx_schema_pending','Replacement schema is not installed.',array('status'=>503)); }
		$entity=$this->entity_for_request($request); if(!$entity)return new WP_Error('hrx_entity_unknown','Unknown HR entity.',array('status'=>404));
		$meta=self::entities()[$entity];
		if ( in_array($entity,array('payroll-runs','payee-payments'),true) ) { return new WP_Error('hrx_append_only','This entity is created only by its controlled workflow.',array('status'=>409)); }
		$payload=$request->get_json_params(); if(!is_array($payload))return new WP_Error('hrx_json_required','A JSON object is required.',array('status'=>400));
		$data=$this->clean_payload($payload,$meta['fields']);
		if(is_wp_error($data))return $data;
		foreach($meta['required'] as $field){if(!array_key_exists($field,$data)||null===$data[$field]||''===$data[$field])return new WP_Error('hrx_required_field','Required field missing: '.$field,array('status'=>400));}
		if('people'===$entity){if(isset($payload['person_kind'])&&'CANDIDATE'!==strtoupper(sanitize_key($payload['person_kind'])))return new WP_Error('hrx_person_kind','A new person must enter as a candidate; employee activation is a controlled transition.',array('status'=>400));$data['person_kind']='CANDIDATE';}
		if('rule-sets'===$entity){$data['status']='LEGAL_REVIEW';}
		if('rule-components'===$entity){$data['status']='DRAFT';}
		if('resource-documents'===$entity){$data['state']='DRAFT';}
		if('employee-documents'===$entity){if(preg_match('~^https?://~i',(string)$data['private_file_ref']))return new WP_Error('hrx_public_document_url','Public URLs are prohibited for employee documents.',array('status'=>400));$data['status']='PENDING_REVIEW';}
		if('payee-tax-decisions'===$entity){$data['status']='LEGAL_REVIEW';$data['reviewer_user_id']=get_current_user_id();$data['decided_at']=current_time('mysql');}
		if('payee-payments'===$entity){$data['status']='REQUESTED';}
		if('employment-contracts'===$entity){if(isset($payload['signed_document_ref'])||isset($payload['signed_document_sha256'])||isset($payload['signed_at']))return new WP_Error('hrx_contract_sign_route','A contract cannot be marked signed during creation. Use the controlled signing workflow.',array('status'=>400));}
		if('resource-documents'!==$entity){$data['status'] = $data['status'] ?? $this->default_status($entity);}
		$data['created_by']=get_current_user_id();$data['created_at']=current_time('mysql');$data['updated_at']=$data['created_at'];$data['row_version']=1;
		if(isset($data['location_id'])&&!$this->location_access($data['location_id']))return new WP_Error('hrx_location_denied','Location is outside your authorized scope.',array('status'=>403));
		if(!$this->validate_relationships($entity,$data))return new WP_Error('hrx_reference_invalid','One or more related records do not exist or are not accessible.',array('status'=>422));
		if(false===$wpdb->query('START TRANSACTION'))return new WP_Error('hrx_txn_failed','Could not start HR transaction.',array('status'=>500));
		$ok=$wpdb->insert(Stockland_HR_Replacement::table($meta['table']),$data);
		if(false===$ok){$wpdb->query('ROLLBACK');return new WP_Error('hrx_insert_failed','HR record was not saved. Check uniqueness and required data.',array('status'=>409));}
		$id=(int)$wpdb->insert_id;
		if(!$this->audit_event('HRX_CREATE',$id,$entity,null,'record-created')){$wpdb->query('ROLLBACK');return new WP_Error('hrx_audit_failed','Audit logging failed; record creation was rolled back.',array('status'=>500));}
		$wpdb->query('COMMIT');
		return new WP_REST_Response(array('id'=>$id,'entity'=>$entity,'status'=>$data['status']??null,'message'=>'Record created.'),201);
	}

	public function update_record( $request ) {
		global $wpdb;
		$entity=$this->entity_for_request($request);$meta=self::entities()[$entity];$id=absint($request['id']);$current=$this->read_one($meta['table'],$id);
		if(!$current)return new WP_Error('hrx_not_found','HR record not found.',array('status'=>404));
		if(!$this->row_access($meta,$current))return new WP_Error('hrx_scope_denied','The record is outside your authorized scope.',array('status'=>403));
		if(!empty($meta['immutable'])||empty($meta['edit']))return new WP_Error('hrx_immutable','This record is immutable or has no editable fields.',array('status'=>409));
		if(!in_array(strtoupper((string)($current['status']??'DRAFT')),array('DRAFT','LEGAL_REVIEW','PENDING_REVIEW','REVIEW_REQUIRED','OPEN'),true))return new WP_Error('hrx_state_locked','Only unposted draft/review records may be edited.',array('status'=>409));
		$p=$request->get_json_params();$expected=absint($p['expected_version']??0);unset($p['expected_version']);
		if(!$expected||(int)$current['row_version']!==$expected)return new WP_Error('hrx_stale_version','The record has changed. Refresh and retry with the latest version.',array('status'=>409));
		$data=$this->clean_payload($p,$meta['edit']);if(is_wp_error($data))return $data;
		if(!$data)return new WP_Error('hrx_no_changes','No editable fields were submitted.',array('status'=>400));
		$data['updated_at']=current_time('mysql');$data['row_version']=$expected+1;
		if(isset($data['location_id'])&&!$this->location_access($data['location_id']))return new WP_Error('hrx_location_denied','Location is outside your authorized scope.',array('status'=>403));
		$where=array('id'=>$id,'row_version'=>$expected);
		$wpdb->query('START TRANSACTION');
		$ok=$wpdb->update(Stockland_HR_Replacement::table($meta['table']),$data,$where);
		if(false===$ok||0===$ok){$wpdb->query('ROLLBACK');return new WP_Error('hrx_concurrent_update','Update failed or another user changed the record.',array('status'=>409));}
		if(!$this->audit_event('HRX_UPDATE',$id,$entity,null,'draft-field-update')){$wpdb->query('ROLLBACK');return new WP_Error('hrx_audit_failed','Audit logging failed; update was rolled back.',array('status'=>500));}
		$wpdb->query('COMMIT');
		return rest_ensure_response(array('id'=>$id,'row_version'=>$expected+1,'message'=>'Draft updated.'));
	}

	public function transition( $request ) {
		$entity=$this->entity_for_request($request);$meta=self::entities()[$entity];$id=absint($request['id']);
		$row=$this->read_one($meta['table'],$id);if(!$row)return new WP_Error('hrx_not_found','HR record not found.',array('status'=>404));
		if(!$this->row_access($meta,$row))return new WP_Error('hrx_scope_denied','The record is outside your authorized scope.',array('status'=>403));
		$p=$request->get_json_params();$action=strtoupper(sanitize_key((string)($p['action']??'')));$reason=sanitize_textarea_field((string)($p['reason']??''));$actor=get_current_user_id();
		if('rule-sets'===$entity)return $this->transition_rule($row,$action,$reason);
		if('employment-contracts'===$entity&&'SIGN'===$action)return $this->sign_contract($row,$p);
		if('employment-contracts'===$entity&&'ACTIVATE'===$action)return $this->activate_contract($row,$reason);
		if('employee-documents'===$entity&&'VERIFY'===$action)return $this->verify_document($row,$reason);
		if('payroll-periods'===$entity&&'CREATE_RUN'===$action)return $this->create_payroll_run($row,$p);
		if('resource-documents'===$entity&&'APPROVE_RESOURCE'===$action)return $this->transition_resource($row,'APPROVED',$reason);
		if('resource-documents'===$entity&&'RETIRE'===$action)return $this->transition_resource($row,'RETIRED',$reason);
		if('leave-requests'===$entity&&'APPROVE'===$action)return $this->approve_leave($row,$reason);
		$map=$this->transitions();
		if(!isset($map[$entity][$action]))return new WP_Error('hrx_transition_invalid','Unsupported transition for this record type.',array('status'=>400));
		list($from,$to,$role)=$map[$entity][$action];
		if(!in_array(strtoupper((string)($row['status']??'')),(array)$from,true))return new WP_Error('hrx_state_transition','The current state does not permit this action.',array('status'=>409));
		if('owner'===$role&&!$this->can_approve_payroll())return new WP_Error('hrx_owner_required','Independent Owner approval is required.',array('status'=>403));
		if('payroll'===$role&&!($this->can_manage_payroll()||$this->can_approve_payroll()))return new WP_Error('hrx_payroll_required','Payroll permission is required.',array('status'=>403));
		if(in_array($action,array('SELECT','REJECT','APPROVE','ACCEPT','DECLINE'),true)&&strlen(trim($reason))<10)return new WP_Error('hrx_reason_required','A reason of at least 10 characters is required for this decision.',array('status'=>400));
		if('applications'===$entity&&'SELECT'===$action){$appId=(int)$row['id'];$open=(int)Stockland_DB::read_var($this->prep('SELECT COUNT(*) FROM %i WHERE application_id=%d AND decision IS NOT NULL',Stockland_HR_Replacement::table('interview_events'),$appId));if($open<1)return new WP_Error('hrx_interview_evidence_required','Record an interview/assessment decision before selecting a candidate.',array('status'=>409));}
		if('payee-deliverables'===$entity&&'ACCEPT'===$action&&(int)($row['created_by']??0)===$actor)return new WP_Error('hrx_self_acceptance','The deliverable submitter cannot accept their own deliverable.',array('status'=>403));
		if('payee-tax-decisions'===$entity&&'APPROVE'===$action){if(empty($row['professional_signoff_ref'])||empty($row['rule_set_id']))return new WP_Error('hrx_tax_signoff_required','Qualified tax reviewer/source sign-off is required before this decision can be approved.',array('status'=>409));}
		if('payee-payments'===$entity&&'APPROVE'===$action){$check=$this->validate_payee_payment($row);if(is_wp_error($check))return $check;}
		if('separation-cases'===$entity&&'CLEARANCE_COMPLETE'===$action){$c=json_decode((string)($row['clearance_json']??''),true);if(!is_array($c)||empty($c['assets_returned'])||empty($c['cash_reconciled'])||empty($c['documents_handed_over'])||empty($row['leave_reconciled']))return new WP_Error('hrx_clearance_incomplete','Asset, cash, document and leave clearance must be reconciled before final calculation.',array('status'=>409));}
		global $wpdb;$table=Stockland_HR_Replacement::table($meta['table']);
		if(false===$wpdb->query('START TRANSACTION'))return new WP_Error('hrx_txn_failed','Could not start HR transaction.',array('status'=>500));
		$updated=$wpdb->update($table,array('status'=>$to,'row_version'=>(int)$row['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>$id,'row_version'=>(int)$row['row_version'],'status'=>$row['status']));
		if(false===$updated||0===$updated){$wpdb->query('ROLLBACK');return new WP_Error('hrx_concurrent_update','State changed while the action was being processed; refresh and retry.',array('status'=>409));}
		if(!$this->append_lifecycle_if_person($entity,$row,$action,$reason,$actor)||!$this->audit_event('HRX_'.$action,$id,$entity,$row['status']??null,$to)){$wpdb->query('ROLLBACK');return new WP_Error('hrx_audit_failed','Transition was rolled back because audit evidence could not be saved.',array('status'=>500));}
		$wpdb->query('COMMIT');
		return rest_ensure_response(array('id'=>$id,'entity'=>$entity,'status'=>$to,'message'=>'Transition recorded.'));
	}

	private function transitions() {
		return array(
			'requisitions'=>array('SUBMIT'=>array(array('DRAFT'),'PENDING_APPROVAL','hr'),'APPROVE'=>array(array('PENDING_APPROVAL'),'APPROVED','owner'),'REJECT'=>array(array('PENDING_APPROVAL'),'REJECTED','owner'),'OPEN'=>array(array('APPROVED'),'OPEN','owner'),'CLOSE'=>array(array('OPEN'),'CLOSED','hr')),
			'applications'=>array('SCREEN'=>array(array('APPLIED'),'SCREENING','hr'),'INTERVIEW'=>array(array('SCREENING'),'INTERVIEW','hr'),'SELECT'=>array(array('INTERVIEW'),'SELECTED','owner'),'REJECT'=>array(array('APPLIED','SCREENING','INTERVIEW','HOLD'),'REJECTED','owner'),'HOLD'=>array(array('APPLIED','SCREENING','INTERVIEW'),'HOLD','hr')),
			'offers'=>array('SUBMIT'=>array(array('DRAFT'),'PENDING_APPROVAL','hr'),'APPROVE'=>array(array('PENDING_APPROVAL'),'APPROVED','owner'),'ISSUE'=>array(array('APPROVED'),'ISSUED','owner'),'ACCEPT'=>array(array('ISSUED'),'ACCEPTED','hr'),'DECLINE'=>array(array('ISSUED'),'DECLINED','hr')),
			'onboarding-tasks'=>array('COMPLETE'=>array(array('OPEN','IN_PROGRESS'),'COMPLETE','hr'),'BLOCK'=>array(array('OPEN','IN_PROGRESS'),'BLOCKED','hr')),
			'shift-patterns'=>array('SUBMIT'=>array(array('DRAFT'),'PENDING_APPROVAL','hr'),'APPROVE'=>array(array('PENDING_APPROVAL'),'APPROVED','owner')),
			'shift-assignments'=>array('SUBMIT'=>array(array('DRAFT'),'PENDING_APPROVAL','hr'),'APPROVE'=>array(array('PENDING_APPROVAL'),'APPROVED','owner')),
			'attendance-records'=>array('SUBMIT'=>array(array('DRAFT'),'SUBMITTED','hr'),'APPROVE'=>array(array('SUBMITTED'),'APPROVED','hr'),'REJECT'=>array(array('SUBMITTED'),'EXCEPTION','hr')),
			'leave-requests'=>array('SUBMIT'=>array(array('DRAFT'),'SUBMITTED','hr'),'REJECT'=>array(array('SUBMITTED'),'REJECTED','hr')),
			'compensation-assignments'=>array('SUBMIT'=>array(array('DRAFT'),'PENDING_APPROVAL','hr'),'APPROVE'=>array(array('PENDING_APPROVAL'),'APPROVED','owner'),'REJECT'=>array(array('PENDING_APPROVAL'),'REJECTED','owner')),
			'benefit-enrollments'=>array('SUBMIT'=>array(array('DRAFT'),'PENDING_APPROVAL','hr'),'APPROVE'=>array(array('PENDING_APPROVAL'),'APPROVED','owner')),
			'payroll-periods'=>array('CLOSE'=>array(array('OPEN'),'CLOSED','owner')),
			'separation-cases'=>array('SUBMIT'=>array(array('DRAFT'),'PENDING_REVIEW','hr'),'APPROVE_EXIT'=>array(array('PENDING_REVIEW'),'APPROVED_TO_EXIT','owner'),'CLEARANCE_START'=>array(array('APPROVED_TO_EXIT'),'CLEARANCE_IN_PROGRESS','hr'),'CLEARANCE_COMPLETE'=>array(array('CLEARANCE_IN_PROGRESS'),'READY_FOR_FINAL_CALCULATION','owner')),
			'payee-engagements'=>array('SUBMIT'=>array(array('REQUESTED'),'PENDING_APPROVAL','hr'),'APPROVE'=>array(array('PENDING_APPROVAL'),'APPROVED','owner'),'CONTRACT'=>array(array('APPROVED'),'CONTRACTED','owner')),
			'payee-deliverables'=>array('SUBMIT'=>array(array('PENDING'),'SUBMITTED','hr'),'ACCEPT'=>array(array('SUBMITTED'),'ACCEPTED','owner'),'REJECT'=>array(array('SUBMITTED'),'REJECTED','owner')),
			'payee-tax-decisions'=>array('APPROVE'=>array(array('LEGAL_REVIEW','PENDING_REVIEW'),'APPROVED','owner'),'REJECT'=>array(array('LEGAL_REVIEW','PENDING_REVIEW'),'REJECTED','owner')),
			'payee-payments'=>array('SUBMIT'=>array(array('REQUESTED'),'PENDING_REVIEW','payroll'),'APPROVE'=>array(array('PENDING_REVIEW'),'APPROVED','owner'),'SUBMIT_PAYMENT'=>array(array('APPROVED'),'PAYMENT_SUBMITTED','owner'),'RECONCILE'=>array(array('PAYMENT_SUBMITTED','PARTIALLY_PAID'),'RECONCILED','owner')),
			'resource-documents'=>array('APPROVE_RESOURCE'=>array(array('DRAFT','LEGAL_REVIEW'),'APPROVED','owner'),'RETIRE'=>array(array('APPROVED'),'RETIRED','owner')),
		);
	}

	private function transition_rule($row,$action,$reason) {
		$actor=get_current_user_id();
		if('SUBMIT_REVIEW'===$action&&'LEGAL_REVIEW'===strtoupper($row['status']))return rest_ensure_response(array('id'=>$row['id'],'status'=>'LEGAL_REVIEW'));
		if('TECHNICAL_TESTED'===$action&&in_array(strtoupper($row['status']),array('DRAFT','LEGAL_REVIEW'),true)){
			if(!preg_match('/^[a-f0-9]{64}$/i',(string)$row['test_vector_sha256']))return new WP_Error('hrx_test_vectors_required','A SHA-256 reference to approved deterministic test vectors is required.',array('status'=>409));
			global $wpdb;$ok=$wpdb->update(Stockland_HR_Replacement::table('rule_sets'),array('status'=>'TECHNICAL_TESTED','row_version'=>(int)$row['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>(int)$row['id'],'row_version'=>(int)$row['row_version']));
			if(!$ok)return new WP_Error('hrx_rule_update','Rule set state changed or could not be saved.',array('status'=>409));
			return rest_ensure_response(array('id'=>$row['id'],'status'=>'TECHNICAL_TESTED'));
		}
		if('APPROVE_RULE'===$action&&'TECHNICAL_TESTED'===strtoupper($row['status'])){
			if(!$this->can_approve_payroll())return new WP_Error('hrx_owner_required','Owner approval is required.',array('status'=>403));
			if((int)($row['reviewer_user_id']??0)<1||empty($row['professional_signoff_ref'])||!preg_match('/^[a-f0-9]{64}$/i',(string)$row['source_sha256'])||!preg_match('/^[a-f0-9]{64}$/i',(string)$row['test_vector_sha256'])||empty($row['official_url'])||empty($row['source_clause'])||empty($row['effective_from']))return new WP_Error('hrx_rule_evidence_incomplete','Source checksum, clause, effective date, tested vectors, reviewer and professional sign-off are mandatory.',array('status'=>409));
			if((int)($row['reviewer_user_id']??0)===$actor)return new WP_Error('hrx_rule_self_approval','The rule reviewer cannot approve their own statutory rule.',array('status'=>403));
			$approved_hash=hash('sha256',wp_json_encode(array('code'=>$row['rule_code'],'version'=>$row['version_label'],'source'=>$row['source_sha256'],'test'=>$row['test_vector_sha256'],'effective_from'=>$row['effective_from'],'effective_to'=>$row['effective_to'],'tax_base'=>$row['tax_base_basis'])));
			global $wpdb;$ok=$wpdb->update(Stockland_HR_Replacement::table('rule_sets'),array('status'=>'APPROVED','approved_by'=>$actor,'approved_at'=>current_time('mysql'),'approved_hash'=>$approved_hash,'row_version'=>(int)$row['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>(int)$row['id'],'row_version'=>(int)$row['row_version']));
			if(!$ok)return new WP_Error('hrx_rule_update','Rule set state changed or could not be saved.',array('status'=>409));
			if(!$this->audit_event('HRX_RULE_APPROVED',(int)$row['id'],'rule-sets',$row['status'],'APPROVED'))return new WP_Error('hrx_audit_failed','Rule approval could not be audited; investigate before use.',array('status'=>500));
			return rest_ensure_response(array('id'=>$row['id'],'status'=>'APPROVED','approved_hash'=>$approved_hash));
		}
		return new WP_Error('hrx_rule_transition','A legal rule must be technically tested and independently approved with complete evidence.',array('status'=>409));
	}

	private function activate_contract($contract,$reason) {
		if(!$this->can_approve_payroll())return new WP_Error('hrx_owner_required','Owner approval is required for employee activation.',array('status'=>403));
		if(strlen(trim($reason))<10)return new WP_Error('hrx_activation_reason','Document the activation decision (at least 10 characters).',array('status'=>400));
		if(!in_array(strtoupper($contract['status']),array('APPROVED','SIGNED'),true)||empty($contract['signed_at'])||empty($contract['signed_document_sha256'])||!preg_match('/^[a-f0-9]{64}$/i',(string)$contract['signed_document_sha256']))return new WP_Error('hrx_contract_incomplete','An approved signed contract with verified file hash is required.',array('status'=>409));
		$docCount=(int)Stockland_DB::read_var($this->prep("SELECT COUNT(*) FROM %i WHERE subject_type='PERSON' AND subject_id=%d AND status='VERIFIED'",Stockland_HR_Replacement::table('employee_documents'),(int)$contract['person_id']));
		$pending=(int)Stockland_DB::read_var($this->prep("SELECT COUNT(*) FROM %i WHERE person_id=%d AND mandatory=1 AND status<>'COMPLETE'",Stockland_HR_Replacement::table('onboarding_tasks'),(int)$contract['person_id']));
		$comp=(int)Stockland_DB::read_var($this->prep("SELECT COUNT(*) FROM %i WHERE person_id=%d AND status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s)",Stockland_HR_Replacement::table('compensation_assignments'),(int)$contract['person_id'],$contract['start_date'],$contract['start_date']));
		if($docCount<1||$pending>0||$comp<1)return new WP_Error('hrx_activation_prerequisites','Activation blocked: verified required document, completed mandatory onboarding, and approved effective compensation must all exist.',array('status'=>409));
		global $wpdb;$person= $this->read_one('people',(int)$contract['person_id']);if(!$person)return new WP_Error('hrx_person_missing','Canonical person does not exist.',array('status'=>409));
		$wpdb->query('START TRANSACTION');
		$employeeNo=trim((string)($person['employee_number']??''));if(''===$employeeNo)$employeeNo='STK-EMP-'.str_pad((string)$person['id'],6,'0',STR_PAD_LEFT);
		$u1=$wpdb->update(Stockland_HR_Replacement::table('people'),array('person_kind'=>'EMPLOYEE','employee_number'=>$employeeNo,'row_version'=>(int)$person['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>(int)$person['id'],'row_version'=>(int)$person['row_version']));
		$u2=$wpdb->update(Stockland_HR_Replacement::table('employment_contracts'),array('status'=>'ACTIVE','approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'),'row_version'=>(int)$contract['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>(int)$contract['id'],'row_version'=>(int)$contract['row_version']));
		if(false===$u1||0===$u1||false===$u2||0===$u2||!$this->append_lifecycle('HIRE_ACTIVATED',(int)$person['id'],$contract['start_date'],'employment-contract',(int)$contract['id'],$reason)||!$this->audit_event('HRX_HIRE_ACTIVATED',(int)$person['id'],'people','CANDIDATE','EMPLOYEE')){
			$wpdb->query('ROLLBACK');return new WP_Error('hrx_activation_failed','Employee activation was rolled back because a required write/audit failed.',array('status'=>500));
		}
		$wpdb->query('COMMIT');return rest_ensure_response(array('person_id'=>(int)$person['id'],'employee_number'=>$employeeNo,'status'=>'ACTIVE'));
	}

	private function approve_leave($row,$reason) {
		if(!$this->can_manage_payroll()&&!$this->can_approve_payroll())return new WP_Error('hrx_leave_permission','Leave approval permission is required.',array('status'=>403));
		if((int)$row['submitted_by']===get_current_user_id())return new WP_Error('hrx_self_approval','A leave requester cannot approve their own request.',array('status'=>403));
		$units=(float)$row['units'];if($units<=0||$row['date_to']<$row['date_from'])return new WP_Error('hrx_leave_dates','Leave dates or units are invalid.',array('status'=>400));
		$overlap=(int)Stockland_DB::read_var($this->prep("SELECT COUNT(*) FROM %i WHERE person_id=%d AND status='APPROVED' AND NOT (date_to < %s OR date_from > %s)",Stockland_HR_Replacement::table('leave_requests'),(int)$row['person_id'],$row['date_from'],$row['date_to']));
		if($overlap>0)return new WP_Error('hrx_leave_overlap','Approved leave overlaps another approved request.',array('status'=>409));
		global $wpdb;$ledger=Stockland_HR_Replacement::table('leave_ledger');$reqId=(int)$row['id'];$key='LEAVE-REQ-'.$reqId;
		$available=(float)Stockland_DB::read_var($this->prep('SELECT COALESCE(SUM(units),0) FROM %i WHERE person_id=%d AND policy_id=%d', $ledger,(int)$row['person_id'],(int)$row['policy_id']));
		if($available<$units)return new WP_Error('hrx_leave_balance','Leave balance is insufficient; no direct balance edit is allowed.',array('status'=>409));
		$wpdb->query('START TRANSACTION');
		$updated=$wpdb->update(Stockland_HR_Replacement::table('leave_requests'),array('status'=>'APPROVED','approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'),'decision_reason'=>$reason,'row_version'=>(int)$row['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>$reqId,'row_version'=>(int)$row['row_version'],'status'=>'SUBMITTED'));
		$exists=(int)Stockland_DB::read_var($this->prep('SELECT COUNT(*) FROM %i WHERE idempotency_key=%s',$ledger,$key));
		if(false===$updated||0===$updated||$exists>0){$wpdb->query('ROLLBACK');return new WP_Error('hrx_leave_race','Leave request changed or was already posted.',array('status'=>409));}
		$entry=array('entry_uuid'=>wp_generate_uuid4(),'person_id'=>(int)$row['person_id'],'policy_id'=>(int)$row['policy_id'],'entry_type'=>'USE','units'=>number_format(-$units,3,'.',''),'effective_on'=>$row['date_from'],'source_type'=>'LEAVE_REQUEST','source_id'=>$reqId,'idempotency_key'=>$key,'approved_by'=>get_current_user_id(),'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'),'row_version'=>1);
		if(false===$wpdb->insert($ledger,$entry)||!$this->audit_event('HRX_LEAVE_APPROVED',$reqId,'leave-requests','SUBMITTED','APPROVED') ){$wpdb->query('ROLLBACK');return new WP_Error('hrx_leave_post_failed','Leave decision/ledger/audit failed and was rolled back.',array('status'=>500));}
		$wpdb->query('COMMIT');return rest_ensure_response(array('id'=>$reqId,'status'=>'APPROVED','ledger_entry_id'=>(int)$wpdb->insert_id));
	}

	private function validate_payee_payment($row) {
		$eng=$this->read_one('payee_engagements',(int)$row['engagement_id']);if(!$eng||'ACCEPTED'!==strtoupper($eng['status'])&&'CONTRACTED'!==strtoupper($eng['status']))return new WP_Error('hrx_engagement_not_accepted','Engagement must be approved/contracted before payment.',array('status'=>409));
		$deliverableId=(int)($row['deliverable_id']??0);if(!empty($eng['acceptance_required'])){if(!$deliverableId)return new WP_Error('hrx_deliverable_required','An accepted deliverable is required.',array('status'=>409));$d=$this->read_one('payee_deliverables',$deliverableId);if(!$d||'ACCEPTED'!==strtoupper($d['status']))return new WP_Error('hrx_deliverable_not_accepted','Payment requires an independently accepted deliverable.',array('status'=>409));}
		$decision=$this->read_one('payee_tax_decisions',(int)($row['tax_decision_id']??0));if(!$decision||'APPROVED'!==strtoupper($decision['status'])||empty($decision['professional_signoff_ref'])||empty($decision['rule_set_id']))return new WP_Error('hrx_tax_decision_pending','An approved source-backed tax decision is required before payment.',array('status'=>409));
		$gross=Stockland_Money::minor($row['gross_amount']);$wh=Stockland_Money::minor($row['withholding_amount']);$net=Stockland_Money::minor($row['net_amount']);if($gross<0||$wh<0||$net<0||$gross!==$wh+$net)return new WP_Error('hrx_payee_amounts','Gross must equal withholding plus net payment.',array('status'=>422));
		return true;
	}

	public function calculate_run($request) {
		global $wpdb;$id=absint($request['id']);$run=$this->read_one('payroll_runs',$id);
		if(!$run)return new WP_Error('hrx_run_not_found','Payroll run not found.',array('status'=>404));
		if(!in_array(strtoupper($run['status']),array('DRAFT','BLOCKED'),true))return new WP_Error('hrx_run_locked','Only an unposted draft run may be calculated.',array('status'=>409));
		$lineTable=Stockland_HR_Replacement::table('payroll_line_details');$existing=(int)Stockland_DB::read_var($this->prep('SELECT COUNT(*) FROM %i WHERE run_id=%d',$lineTable,$id));
		if($existing>0)return new WP_Error('hrx_run_immutable','A run with persisted calculations cannot be recalculated. Create a new version/correction run.',array('status'=>409));
		$period=$this->read_one('payroll_periods',(int)$run['period_id']);$rules=$this->read_one('rule_sets',(int)$run['rule_set_id']);
		if(!$period||!$rules)return new WP_Error('hrx_run_reference','Payroll period or rule set is missing.',array('status'=>409));
		if('OPEN'!==strtoupper($period['status']))return new WP_Error('hrx_period_locked','Payroll period is not open.',array('status'=>409));
		$ruleError=$this->validate_active_rules($rules,$period['period_start'],$period['period_end']);if(is_wp_error($ruleError))return $ruleError;
		$ruleComponents=Stockland_DB::read_results($this->prep("SELECT * FROM %i WHERE rule_set_id=%d ORDER BY sort_order,id",Stockland_HR_Replacement::table('rule_components'),(int)$rules['id']),ARRAY_A);
		$ruleMap=array();foreach((array)$ruleComponents as $rc){if('APPROVED'!==strtoupper((string)$rc['status']))return new WP_Error('hrx_rule_component_not_approved','Every rule component must be individually approved before use.',array('status'=>409));$params=json_decode((string)$rc['parameters_json'],true);if(!is_array($params))return new WP_Error('hrx_rule_json','A rule component contains invalid JSON.',array('status'=>409));$ruleMap[strtoupper($rc['component_kind'])]=$params;}
		if(empty($ruleMap['PAYE_SCHEDULE']['bands'])||empty($ruleMap['PENSION'])||empty($ruleMap['ITEM_CLASSIFICATION'])||empty($ruleMap['PRORATION']))return new WP_Error('hrx_rule_components_missing','Approved rules must contain PAYE_SCHEDULE, PENSION, ITEM_CLASSIFICATION and PRORATION components.',array('status'=>409));
		$classification=$ruleMap['ITEM_CLASSIFICATION']['mapping']??array();$bands=$ruleMap['PAYE_SCHEDULE']['bands'];$pension=$ruleMap['PENSION'];$proration=strtoupper((string)($ruleMap['PRORATION']['method']??''));
		if(!in_array(strtoupper((string)$rules['tax_base_basis']),array('TAXABLE_GROSS','TAXABLE_GROSS_LESS_EMPLOYEE_PENSION'),true))return new WP_Error('hrx_tax_base_unapproved','PAYE base formula is not explicitly approved in the rule set.',array('status'=>409));
		if(!in_array($proration,array('CALENDAR_DAYS','NO_PRORATION'),true))return new WP_Error('hrx_proration_unapproved','Proration method is missing or unsupported in the approved rule set.',array('status'=>409));
		$people=Stockland_DB::read_results($this->prep("SELECT * FROM %i WHERE person_kind='EMPLOYEE' AND status='ACTIVE' ORDER BY id",Stockland_HR_Replacement::table('people')),ARRAY_A);
		if(!$people)return new WP_Error('hrx_no_employees','No active employees exist in the new HR master; the run was not calculated.',array('status'=>409));
		$prepared=array();$exceptions=array();$totals=array('gross'=>'0.00','taxable'=>'0.00','tax'=>'0.00','employee_pension'=>'0.00','employer_pension'=>'0.00','deductions'=>'0.00','net'=>'0.00','employer_cost'=>'0.00');
		foreach($people as $person){
			$pid=(int)$person['id'];$contracts=Stockland_DB::read_results($this->prep("SELECT * FROM %i WHERE person_id=%d AND status='ACTIVE' AND start_date<=%s AND (end_date IS NULL OR end_date>=%s)",Stockland_HR_Replacement::table('employment_contracts'),$pid,$period['period_end'],$period['period_start']),ARRAY_A);
			if(count((array)$contracts)!==1){$exceptions[]=array('person_id'=>$pid,'code'=>'ACTIVE_CONTRACT_COUNT','message'=>'Exactly one active contract must overlap the full payroll period.');continue;}
			$assignments=Stockland_DB::read_results($this->prep("SELECT a.*,c.component_code,c.component_kind,c.frequency AS component_frequency FROM %i a INNER JOIN %i c ON c.id=a.component_id WHERE a.person_id=%d AND a.status='APPROVED' AND a.effective_from<=%s AND (a.effective_to IS NULL OR a.effective_to>=%s) AND c.status='ACTIVE' AND c.effective_from<=%s AND (c.effective_to IS NULL OR c.effective_to>=%s) ORDER BY a.id",Stockland_HR_Replacement::table('compensation_assignments'),Stockland_HR_Replacement::table('compensation_components'),$pid,$period['period_end'],$period['period_start'],$period['period_end'],$period['period_start']),ARRAY_A);
			if(!$assignments){$exceptions[]=array('person_id'=>$pid,'code'=>'COMPENSATION_MISSING','message'=>'No approved effective compensation assignment exists.');continue;}
			$personLines=array();$gross='0.00';$taxable='0.00';$pensionable='0.00';$deductions='0.00';$baseSalary='0.00';$employerExtra='0.00';$overtimeMinutes=0;
			foreach((array)$assignments as $a){
				$code=(string)$a['component_code'];$kind=strtoupper((string)$a['component_kind']);$map=$classification[$code]??$classification[$kind]??null;
				if(!is_array($map)||!array_key_exists('taxable',$map)||!array_key_exists('pensionable',$map)||!isset($map['kind'])){$exceptions[]=array('person_id'=>$pid,'code'=>'COMPONENT_CLASSIFICATION_MISSING','component_code'=>$code,'message'=>'The active rule set does not classify this earning/deduction component.');continue 2;}
				$amount=Stockland_Money::multiply($a['amount'],$a['quantity']);
				$freq=strtoupper((string)$a['frequency']);$effectiveFrom=$a['effective_from']>$period['period_start']?$a['effective_from']:$period['period_start'];$assignmentEnd=empty($a['effective_to'])?$period['period_end']:$a['effective_to'];$effectiveTo=$assignmentEnd<$period['period_end']?$assignmentEnd:$period['period_end'];
				if('MONTHLY'!==$freq&&'ONE_TIME'!==$freq){$conversions=$ruleMap['FREQUENCY_CONVERSION']['multipliers']??array();if(!isset($conversions[$freq])){$exceptions[]=array('person_id'=>$pid,'code'=>'FREQUENCY_RULE_MISSING','component_code'=>$code,'message'=>'No approved conversion exists for compensation frequency '.$freq.'.');continue 2;}$amount=Stockland_Money::multiply($amount,(string)$conversions[$freq]);}
				if('CALENDAR_DAYS'===$proration){$startTs=strtotime($period['period_start']);$endTs=strtotime($period['period_end']);$fromTs=strtotime($effectiveFrom);$toTs=strtotime($effectiveTo);$periodDays=(int)(($endTs-$startTs)/86400)+1;$eligibleDays=(int)(($toTs-$fromTs)/86400)+1;if($periodDays<1||$eligibleDays<1){$amount='0.00';}elseif($eligibleDays<$periodDays){$amount=Stockland_Money::divide(Stockland_Money::multiply($amount,(string)$eligibleDays),(string)$periodDays);}}
				elseif($a['effective_from']>$period['period_start']||(!empty($a['effective_to'])&&$a['effective_to']<$period['period_end'])){$exceptions[]=array('person_id'=>$pid,'code'=>'PRORATION_REQUIRED','component_code'=>$code,'message'=>'Partial-period assignment requires a reviewed proration rule.');continue 2;}
				$lineKind=strtoupper((string)$map['kind']);
				if(!in_array($lineKind,array('EARNING','DEDUCTION','EMPLOYER_COST'),true)){$exceptions[]=array('person_id'=>$pid,'code'=>'COMPONENT_KIND_INVALID','component_code'=>$code,'message'=>'The approved component classification has an unsupported kind.');continue 2;}
				if('EARNING'===$lineKind){$gross=Stockland_Money::add($gross,$amount);if(!empty($map['taxable']))$taxable=Stockland_Money::add($taxable,$amount);if(!empty($map['pensionable']))$pensionable=Stockland_Money::add($pensionable,$amount);if('BASE_PAY'===$kind)$baseSalary=Stockland_Money::add($baseSalary,$amount);}
				elseif('DEDUCTION'===$lineKind){$deductions=Stockland_Money::add($deductions,$amount);}
				else{$employerExtra=Stockland_Money::add($employerExtra,$amount);}
				$personLines[]=array('component_id'=>(int)$a['component_id'],'line_kind'=>$lineKind,'label'=>(string)$code,'quantity'=>(string)$a['quantity'],'unit_rate'=>(string)$a['amount'],'base_amount'=>$amount,'amount'=>$amount,'taxable_base'=>!empty($map['taxable'])?$amount:'0.00','pensionable_base'=>!empty($map['pensionable'])?$amount:'0.00','rule_set_id'=>(int)$rules['id'],'rule_version'=>$rules['version_label'],'calculation_trace_json'=>wp_json_encode(array('assignment_id'=>(int)$a['id'],'effective_from'=>$a['effective_from'],'effective_to'=>$a['effective_to'],'frequency'=>$freq,'proration'=>$proration,'classification'=>$map)),'source_type'=>'COMPENSATION_ASSIGNMENT','source_id'=>(int)$a['id']);
			}
			if(Stockland_Money::minor($baseSalary)<=0){$exceptions[]=array('person_id'=>$pid,'code'=>'BASE_PAY_MISSING','message'=>'A positive approved BASE_PAY component is required.');continue;}
			if(!empty($ruleMap['OVERTIME'])) {
				$overtimeMinutes=(int)Stockland_DB::read_var($this->prep("SELECT COALESCE(SUM(overtime_minutes),0) FROM %i WHERE person_id=%d AND work_date BETWEEN %s AND %s AND status='APPROVED'",Stockland_HR_Replacement::table('attendance_records'),$pid,$period['period_start'],$period['period_end']));
				if($overtimeMinutes>0){$hours=Stockland_Money::divide((string)$overtimeMinutes,'60');$monthlyHours=(string)($ruleMap['OVERTIME']['monthly_hours']??'');$multiplier=(string)($ruleMap['OVERTIME']['multiplier']??'');if(''===$monthlyHours||''===$multiplier||Stockland_Money::minor($monthlyHours)<=0){$exceptions[]=array('person_id'=>$pid,'code'=>'OVERTIME_RULE_INVALID','message'=>'Approved overtime rule must specify monthly_hours and multiplier.');continue;}$hourly=Stockland_Money::divide($baseSalary,$monthlyHours);$rate=Stockland_Money::multiply($hourly,$multiplier);$overtime=Stockland_Money::multiply($hours,$rate);$gross=Stockland_Money::add($gross,$overtime);$taxable=Stockland_Money::add($taxable,$overtime);if(!empty($ruleMap['OVERTIME']['pensionable']))$pensionable=Stockland_Money::add($pensionable,$overtime);$personLines[]=array('component_id'=>null,'line_kind'=>'EARNING','label'=>'APPROVED_OVERTIME','quantity'=>$hours,'unit_rate'=>$rate,'base_amount'=>$overtime,'amount'=>$overtime,'taxable_base'=>$overtime,'pensionable_base'=>!empty($ruleMap['OVERTIME']['pensionable'])?$overtime:'0.00','rule_set_id'=>(int)$rules['id'],'rule_version'=>$rules['version_label'],'calculation_trace_json'=>wp_json_encode(array('approved_overtime_minutes'=>$overtimeMinutes,'monthly_hours'=>$monthlyHours,'multiplier'=>$multiplier,'rule_component'=>'OVERTIME')),'source_type'=>'ATTENDANCE','source_id'=>null);}
			}
			$employeeRate=(string)($pension['employee_rate']??'');$employerRate=(string)($pension['employer_rate']??'');$pensionBasis=strtoupper((string)($pension['basis']??''));
			if(''===$employeeRate||''===$employerRate||!in_array($pensionBasis,array('BASE_PAY','PENSIONABLE_GROSS'),true)){$exceptions[]=array('person_id'=>$pid,'code'=>'PENSION_RULE_INVALID','message'=>'Pension rates and basis must be explicitly defined in the approved rule set.');continue;}
			$pensionBase='BASE_PAY'===$pensionBasis?$baseSalary:$pensionable;$employeePension=Stockland_Money::percentage($pensionBase,$employeeRate);$employerPension=Stockland_Money::percentage($pensionBase,$employerRate);
			$taxBase='TAXABLE_GROSS'===strtoupper($rules['tax_base_basis'])?$taxable:Stockland_Money::subtract($taxable,$employeePension);if(Stockland_Money::minor($taxBase)<0)$taxBase='0.00';
			$tax=$this->calculate_tax($taxBase,$bands);if(is_wp_error($tax)){$exceptions[]=array('person_id'=>$pid,'code'=>'PAYE_SCHEDULE_INVALID','message'=>$tax->get_error_message());continue;}
			$net=Stockland_Money::subtract(Stockland_Money::subtract(Stockland_Money::subtract($gross,$tax),$employeePension),$deductions);if(Stockland_Money::minor($net)<0){$exceptions[]=array('person_id'=>$pid,'code'=>'NEGATIVE_NET_PAY','message'=>'Net pay would be negative; the run is blocked for review.');continue;}
			$employerCost=Stockland_Money::add(Stockland_Money::add($gross,$employerPension),$employerExtra);
			$personLines[]=array('component_id'=>null,'line_kind'=>'EMPLOYEE_PENSION','label'=>'EMPLOYEE_PENSION','quantity'=>'1','unit_rate'=>$employeeRate,'base_amount'=>$pensionBase,'amount'=>$employeePension,'taxable_base'=>'0.00','pensionable_base'=>$pensionBase,'rule_set_id'=>(int)$rules['id'],'rule_version'=>$rules['version_label'],'calculation_trace_json'=>wp_json_encode(array('basis'=>$pensionBasis,'rate'=>$employeeRate)),'source_type'=>'RULE_SET','source_id'=>(int)$rules['id']);
			$personLines[]=array('component_id'=>null,'line_kind'=>'EMPLOYER_PENSION','label'=>'EMPLOYER_PENSION','quantity'=>'1','unit_rate'=>$employerRate,'base_amount'=>$pensionBase,'amount'=>$employerPension,'taxable_base'=>'0.00','pensionable_base'=>$pensionBase,'rule_set_id'=>(int)$rules['id'],'rule_version'=>$rules['version_label'],'calculation_trace_json'=>wp_json_encode(array('basis'=>$pensionBasis,'rate'=>$employerRate)),'source_type'=>'RULE_SET','source_id'=>(int)$rules['id']);
			$personLines[]=array('component_id'=>null,'line_kind'=>'PAYE','label'=>'PAYE','quantity'=>'1','unit_rate'=>null,'base_amount'=>$taxBase,'amount'=>$tax,'taxable_base'=>$taxBase,'pensionable_base'=>'0.00','rule_set_id'=>(int)$rules['id'],'rule_version'=>$rules['version_label'],'calculation_trace_json'=>wp_json_encode(array('tax_base_basis'=>$rules['tax_base_basis'],'bands'=>$bands,'result'=>$tax)),'source_type'=>'RULE_SET','source_id'=>(int)$rules['id']);
			$personLines[]=array('component_id'=>null,'line_kind'=>'NET_PAY','label'=>'NET_PAY','quantity'=>'1','unit_rate'=>null,'base_amount'=>$gross,'amount'=>$net,'taxable_base'=>'0.00','pensionable_base'=>'0.00','rule_set_id'=>(int)$rules['id'],'rule_version'=>$rules['version_label'],'calculation_trace_json'=>wp_json_encode(array('gross'=>$gross,'tax'=>$tax,'employee_pension'=>$employeePension,'deductions'=>$deductions,'net'=>$net)),'source_type'=>'PAYROLL_CALCULATION','source_id'=>$id);
			$totals['gross']=Stockland_Money::add($totals['gross'],$gross);$totals['taxable']=Stockland_Money::add($totals['taxable'],$taxable);$totals['tax']=Stockland_Money::add($totals['tax'],$tax);$totals['employee_pension']=Stockland_Money::add($totals['employee_pension'],$employeePension);$totals['employer_pension']=Stockland_Money::add($totals['employer_pension'],$employerPension);$totals['deductions']=Stockland_Money::add($totals['deductions'],$deductions);$totals['net']=Stockland_Money::add($totals['net'],$net);$totals['employer_cost']=Stockland_Money::add($totals['employer_cost'],$employerCost);
			$prepared[] = array('person_id'=>$pid,'lines'=>$personLines,'net'=>$net);
		}
		if($exceptions){$wpdb->update(Stockland_HR_Replacement::table('payroll_runs'),array('status'=>'BLOCKED','exception_count'=>count($exceptions),'calculator_version'=>self::CALCULATOR_VERSION,'row_version'=>(int)$run['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>$id,'row_version'=>(int)$run['row_version']));return new WP_Error('hrx_calculation_blocked','Payroll calculation blocked by exceptions; no payroll lines were posted.',array('status'=>409,'exceptions'=>$exceptions));}
		if(!$prepared)return new WP_Error('hrx_no_calculations','No employees were eligible for this payroll run.',array('status'=>409));
		$snapshot=array('run_id'=>$id,'period_id'=>(int)$period['id'],'period_start'=>$period['period_start'],'period_end'=>$period['period_end'],'rule_set_id'=>(int)$rules['id'],'rule_version'=>$rules['version_label'],'rule_hash'=>$rules['approved_hash'],'calculator_version'=>self::CALCULATOR_VERSION,'employee_ids'=>array_column($prepared,'person_id'),'totals'=>$totals,'calculated_at'=>current_time('mysql'));
		$snapshotJson=wp_json_encode($snapshot);$snapshotHash=hash('sha256',$snapshotJson);
		if(false===$wpdb->query('START TRANSACTION'))return new WP_Error('hrx_txn_failed','Could not start payroll calculation transaction.',array('status'=>500));
		foreach($prepared as $p){$index=0;foreach($p['lines'] as $line){$line['run_id']=$id;$line['person_id']=$p['person_id'];$line['idempotency_key']='HRX-RUN-'.$id.'-P-'.$p['person_id'].'-L-'.$index++;$line['created_by']=get_current_user_id();$line['created_at']=current_time('mysql');$line['updated_at']=$line['created_at'];$line['row_version']=1;if(false===$wpdb->insert($lineTable,$line)){$wpdb->query('ROLLBACK');return new WP_Error('hrx_line_insert_failed','Payroll line insert failed; calculation transaction rolled back.',array('status'=>500));}}}
		$updated=$wpdb->update(Stockland_HR_Replacement::table('payroll_runs'),array('source_snapshot_json'=>$snapshotJson,'source_snapshot_sha256'=>$snapshotHash,'calculator_version'=>self::CALCULATOR_VERSION,'gross_total'=>$totals['gross'],'taxable_total'=>$totals['taxable'],'tax_total'=>$totals['tax'],'employee_pension_total'=>$totals['employee_pension'],'employer_pension_total'=>$totals['employer_pension'],'deductions_total'=>$totals['deductions'],'net_total'=>$totals['net'],'employer_cost_total'=>$totals['employer_cost'],'exception_count'=>0,'status'=>'CALCULATED','row_version'=>(int)$run['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>$id,'row_version'=>(int)$run['row_version']));
		if(false===$updated||0===$updated||!$this->audit_event('HRX_PAYROLL_CALCULATED',$id,'payroll-runs',$run['status'],'CALCULATED')){$wpdb->query('ROLLBACK');return new WP_Error('hrx_calc_commit_failed','Calculation header/audit failed; all new lines were rolled back.',array('status'=>500));}
		$wpdb->query('COMMIT');return rest_ensure_response(array('id'=>$id,'status'=>'CALCULATED','employees'=>count($prepared),'totals'=>$totals,'snapshot_sha256'=>$snapshotHash,'rule_version'=>$rules['version_label'],'message'=>'Frozen calculation created. Review the exact run before submission.'));
	}

	private function validate_active_rules($rules,$from,$to) {
		if('APPROVED'!==strtoupper((string)$rules['status']))return new WP_Error('hrx_rule_not_approved','The statutory rule set is not approved.',array('status'=>409));
		if(empty($rules['reviewer_user_id'])||empty($rules['professional_signoff_ref'])||empty($rules['approved_hash'])||empty($rules['test_vector_sha256'])||empty($rules['source_sha256'])||empty($rules['official_url'])||empty($rules['source_clause']))return new WP_Error('hrx_rule_evidence_missing','Statutory rule source, hash, professional sign-off and tests are incomplete.',array('status'=>409));
		if($rules['effective_from']>$from||(!empty($rules['effective_to'])&&$rules['effective_to']<$to))return new WP_Error('hrx_rule_out_of_period','The statutory rule set does not cover the entire payroll period.',array('status'=>409));
		return true;
	}
	private function calculate_tax($income,$bands) {
		try{$minor=Stockland_Money::minor($income);}catch(Throwable $e){return new WP_Error('hrx_tax_base_invalid','PAYE taxable base is invalid.');}
		if($minor<0)return new WP_Error('hrx_tax_base_negative','PAYE taxable base cannot be negative.');
		$seenOpen=false;
		foreach($bands as $i=>$band){
			if(!is_array($band)||!isset($band['rate'],$band['deduction'])||!is_numeric($band['rate'])||!is_numeric($band['deduction']))return new WP_Error('hrx_tax_band_invalid','A PAYE band is missing its rate or deduction.');
			if(!array_key_exists('up_to',$band))return new WP_Error('hrx_tax_band_limit_missing','Every PAYE band must declare its upper limit or null for the last band.');
			if(null===$band['up_to']){if($i!==count($bands)-1)return new WP_Error('hrx_tax_band_order','An open-ended PAYE band must be last.');$seenOpen=true;$match=true;}
			else{$limit=Stockland_Money::minor($band['up_to']);$match=$minor<=$limit;}
			$rate=(float)$band['rate'];if($rate<0||$rate>1)return new WP_Error('hrx_tax_rate_invalid','Tax rate must be stored as a fractional rate between 0 and 1.');
			if($match){$raw=Stockland_Money::percentage($income,(string)$band['rate']);$tax=Stockland_Money::subtract($raw,(string)$band['deduction']);return Stockland_Money::minor($tax)<0?'0.00':$tax;}
		}
		return new WP_Error('hrx_tax_band_not_covered','No approved PAYE band covers this taxable base.');
	}

	public function submit_run($request) {
		$id=absint($request['id']);$run=$this->read_one('payroll_runs',$id);if(!$run)return new WP_Error('hrx_run_not_found','Payroll run not found.',array('status'=>404));
		if('CALCULATED'!==strtoupper($run['status'])||(int)$run['exception_count']>0||empty($run['source_snapshot_sha256']))return new WP_Error('hrx_run_not_ready','Only an exception-free calculated run can be submitted.',array('status'=>409));
		if(!Stockland_Authority::can_manage_payroll())return new WP_Error('hrx_maker_submit','Only authorized Management/Payroll can submit the run for independent Owner approval.',array('status'=>403));
		$hash=hash('sha256',(string)$run['source_snapshot_json']);if(!hash_equals((string)$run['source_snapshot_sha256'],$hash))return new WP_Error('hrx_snapshot_hash','Payroll snapshot integrity check failed.',array('status'=>409));
		global $wpdb;$wpdb->query('START TRANSACTION');$ok=$wpdb->update(Stockland_HR_Replacement::table('payroll_runs'),array('status'=>'SUBMITTED','submitted_by'=>get_current_user_id(),'row_version'=>(int)$run['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>$id,'row_version'=>(int)$run['row_version'],'status'=>'CALCULATED'));
		$approval=$wpdb->insert(Stockland_HR_Replacement::table('payroll_approvals'),array('approval_uuid'=>wp_generate_uuid4(),'run_id'=>$id,'stage'=>'SUBMISSION','decision'=>'SUBMITTED','actor_user_id'=>get_current_user_id(),'reason'=>'Submitted for independent Owner approval.','run_hash'=>$hash,'request_key'=>'HRX-SUBMIT-'.$id.'-'.$hash,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'),'row_version'=>1));
		if(false===$ok||0===$ok||false===$approval||!$this->audit_event('HRX_PAYROLL_SUBMITTED',$id,'payroll-runs','CALCULATED','SUBMITTED')){$wpdb->query('ROLLBACK');return new WP_Error('hrx_submit_failed','Payroll submission and its audit could not be completed; transaction rolled back.',array('status'=>500));}$wpdb->query('COMMIT');return rest_ensure_response(array('id'=>$id,'status'=>'SUBMITTED'));
	}

	public function approve_run($request) {
		global $wpdb;$id=absint($request['id']);$run=$this->read_one('payroll_runs',$id);if(!$run)return new WP_Error('hrx_run_not_found','Payroll run not found.',array('status'=>404));
		if('SUBMITTED'!==strtoupper($run['status']))return new WP_Error('hrx_run_not_submitted','Only a submitted run can be approved.',array('status'=>409));
		if((int)$run['created_by']===get_current_user_id()||(int)$run['submitted_by']===get_current_user_id())return new WP_Error('hrx_self_approval','The Owner approver must be independent from the run preparer and submitter.',array('status'=>403));
		$hash=hash('sha256',(string)$run['source_snapshot_json']);if(!hash_equals((string)$run['source_snapshot_sha256'],$hash))return new WP_Error('hrx_snapshot_hash','Frozen payroll snapshot failed integrity validation.',array('status'=>409));
		$rule=$this->read_one('rule_sets',(int)$run['rule_set_id']);if(!$rule)return new WP_Error('hrx_rule_missing','Approved rule set was not found.',array('status'=>409));$check=$this->validate_active_rules($rule,(string)$this->read_one('payroll_periods',(int)$run['period_id'])['period_start'],(string)$this->read_one('payroll_periods',(int)$run['period_id'])['period_end']);if(is_wp_error($check))return $check;
		$lineSums=Stockland_DB::read_row($this->prep("SELECT COALESCE(SUM(CASE WHEN line_kind='EARNING' THEN amount ELSE 0 END),0) gross,COALESCE(SUM(CASE WHEN line_kind='PAYE' THEN amount ELSE 0 END),0) tax,COALESCE(SUM(CASE WHEN line_kind='EMPLOYEE_PENSION' THEN amount ELSE 0 END),0) employee_pension,COALESCE(SUM(CASE WHEN line_kind='EMPLOYER_PENSION' THEN amount ELSE 0 END),0) employer_pension,COALESCE(SUM(CASE WHEN line_kind='DEDUCTION' THEN amount ELSE 0 END),0) deductions,COALESCE(SUM(CASE WHEN line_kind='NET_PAY' THEN amount ELSE 0 END),0) net FROM %i WHERE run_id=%d",Stockland_HR_Replacement::table('payroll_line_details'),$id),ARRAY_A);
		if(!$lineSums||Stockland_Money::minor($lineSums['gross'])!==Stockland_Money::minor($run['gross_total'])||Stockland_Money::minor($lineSums['tax'])!==Stockland_Money::minor($run['tax_total'])||Stockland_Money::minor($lineSums['employee_pension'])!==Stockland_Money::minor($run['employee_pension_total'])||Stockland_Money::minor($lineSums['net'])!==Stockland_Money::minor($run['net_total']))return new WP_Error('hrx_run_reconciliation','Line totals do not match the frozen run header.',array('status'=>409));
		$wpdb->query('START TRANSACTION');$ok=$wpdb->update(Stockland_HR_Replacement::table('payroll_runs'),array('status'=>'APPROVED','approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'),'row_version'=>(int)$run['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>$id,'row_version'=>(int)$run['row_version'],'status'=>'SUBMITTED'));
		$ap=$wpdb->insert(Stockland_HR_Replacement::table('payroll_approvals'),array('approval_uuid'=>wp_generate_uuid4(),'run_id'=>$id,'stage'=>'OWNER_APPROVAL','decision'=>'APPROVED','actor_user_id'=>get_current_user_id(),'reason'=>sanitize_textarea_field((string)($request->get_json_params()['reason']??'Owner approval after independent reconciliation.')),'run_hash'=>$hash,'request_key'=>'HRX-APPROVE-'.$id.'-'.$hash,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'),'row_version'=>1));
		if(false===$ok||0===$ok||false===$ap||!$this->audit_event('HRX_PAYROLL_APPROVED',$id,'payroll-runs','SUBMITTED','APPROVED')){$wpdb->query('ROLLBACK');return new WP_Error('hrx_approval_failed','Payroll approval and audit were rolled back.',array('status'=>500));}$wpdb->query('COMMIT');return rest_ensure_response(array('id'=>$id,'status'=>'APPROVED','run_hash'=>$hash));
	}

	public function post_run($request) {
		global $wpdb;$id=absint($request['id']);$run=$this->read_one('payroll_runs',$id);if(!$run)return new WP_Error('hrx_run_not_found','Payroll run not found.',array('status'=>404));
		if('APPROVED'!==strtoupper($run['status']))return new WP_Error('hrx_run_not_approved','Only an Owner-approved run can be posted.',array('status'=>409));
		$map=get_option('stockland_hrx_payroll_account_map',array());$required=array('wage_expense','employer_pension_expense','paye_payable','employee_pension_payable','other_deductions_payable','net_wages_payable');
		foreach($required as $k){if(empty($map[$k]))return new WP_Error('hrx_account_map_missing','Owner must configure the payroll account map before journal posting.',array('status'=>409,'missing_key'=>$k));$exists=(int)Stockland_DB::read_var($this->prep('SELECT COUNT(*) FROM %i WHERE account_code=%s AND is_active=1',Stockland_DB::table('gl_accounts'),sanitize_text_field($map[$k])));if(!$exists)return new WP_Error('hrx_account_invalid','Configured account is not active in the existing chart of accounts.',array('status'=>409,'account_key'=>$k));}
		if(Stockland_Money::minor($run['gross_total'])<=0)return new WP_Error('hrx_run_amount_invalid','A positive payroll gross total is required.',array('status'=>409));
		$debit=Stockland_Money::add($run['gross_total'],$run['employer_pension_total']);
		$credits=Stockland_Money::add(Stockland_Money::add(Stockland_Money::add($run['tax_total'],$run['employee_pension_total']),$run['deductions_total']),$run['net_total']);
		if(Stockland_Money::minor($debit)!==Stockland_Money::minor($credits))return new WP_Error('hrx_gl_unbalanced','Payroll liability/expense totals do not reconcile; journal was not posted.',array('status'=>409,'debits'=>$debit,'credits'=>$credits));
		$period=$this->read_one('payroll_periods',(int)$run['period_id']);if(!$period||'OPEN'!==strtoupper($period['status']))return new WP_Error('hrx_period_closed','The target period is missing or not open.',array('status'=>409));
		$journalLines=array(
			array('account_code'=>$map['wage_expense'],'debit'=>$run['gross_total'],'credit'=>'0.00','memo'=>'Gross employee payroll'),
			array('account_code'=>$map['employer_pension_expense'],'debit'=>$run['employer_pension_total'],'credit'=>'0.00','memo'=>'Employer pension'),
			array('account_code'=>$map['paye_payable'],'debit'=>'0.00','credit'=>$run['tax_total'],'memo'=>'PAYE liability'),
			array('account_code'=>$map['employee_pension_payable'],'debit'=>'0.00','credit'=>$run['employee_pension_total'],'memo'=>'Employee pension liability'),
			array('account_code'=>$map['other_deductions_payable'],'debit'=>'0.00','credit'=>$run['deductions_total'],'memo'=>'Other payroll deductions'),
			array('account_code'=>$map['net_wages_payable'],'debit'=>'0.00','credit'=>$run['net_total'],'memo'=>'Net wages payable'),
		);
		$date=$period['payment_date']?:$period['period_end'];$wpdb->query('START TRANSACTION');
		$jid=Stockland_Accounting::post_journal($date,'HRX_PAYROLL_RUN',(string)$id,'Stockland HR payroll run '.$run['run_code'],$journalLines,get_current_user_id());
		if(!$jid){$wpdb->query('ROLLBACK');return new WP_Error('hrx_gl_post_failed','The existing GL journal service rejected payroll posting; run remains unposted.',array('status'=>500));}
		$ok=$wpdb->update(Stockland_HR_Replacement::table('payroll_runs'),array('status'=>'POSTED','posted_journal_id'=>(int)$jid,'row_version'=>(int)$run['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>$id,'row_version'=>(int)$run['row_version'],'status'=>'APPROVED'));
		if(false===$ok||0===$ok||!$this->audit_event('HRX_PAYROLL_POSTED',$id,'payroll-runs','APPROVED','POSTED')){$wpdb->query('ROLLBACK');return new WP_Error('hrx_post_commit_failed','Journal/run/audit consistency could not be confirmed; transaction was rolled back.',array('status'=>500));}
		$wpdb->query('COMMIT');return rest_ensure_response(array('id'=>$id,'status'=>'POSTED','journal_id'=>(int)$jid));
	}

	public function create_disbursements($request) {
		global $wpdb;$id=absint($request['id']);$run=$this->read_one('payroll_runs',$id);if(!$run)return new WP_Error('hrx_run_not_found','Payroll run not found.',array('status'=>404));
		if('POSTED'!==strtoupper($run['status']))return new WP_Error('hrx_run_not_posted','GL posting must be completed before disbursement batch preparation.',array('status'=>409));
		$table=Stockland_HR_Replacement::table('payroll_disbursements');$existing=(int)Stockland_DB::read_var($this->prep('SELECT COUNT(*) FROM %i WHERE run_id=%d',$table,$id));if($existing)return new WP_Error('hrx_disbursements_exist','Disbursement instructions already exist. Reuse the existing batch and record per-worker results.',array('status'=>409));
		$lines=Stockland_DB::read_results($this->prep("SELECT person_id,amount FROM %i WHERE run_id=%d AND line_kind='NET_PAY' ORDER BY person_id",Stockland_HR_Replacement::table('payroll_line_details'),$id),ARRAY_A);if(!$lines)return new WP_Error('hrx_no_net_lines','No net-payment lines exist.',array('status'=>409));
		$p=$request->get_json_params();$method=strtoupper(sanitize_key((string)($p['payment_method']??'')));$batch=sanitize_text_field((string)($p['external_batch_ref']??''));if(!in_array($method,array('BANK','CASH','CHEQUE'),true)||''===$batch)return new WP_Error('hrx_batch_reference_required','Select the actual payment method and the external batch/register reference before preparing the batch.',array('status'=>400));
		$wpdb->query('START TRANSACTION');$made=array();
		foreach($lines as $line){$personId=(int)$line['person_id'];$uuid=wp_generate_uuid4();$key='HRX-DISBURSE-'.$id.'-'.$personId;$ok=$wpdb->insert($table,array('disbursement_uuid'=>$uuid,'run_id'=>$id,'person_id'=>$personId,'amount'=>$line['amount'],'currency'=>'ETB','payment_method'=>$method,'external_batch_ref'=>$batch,'idempotency_key'=>$key,'status'=>'PENDING','created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'),'row_version'=>1));if(false===$ok){$wpdb->query('ROLLBACK');return new WP_Error('hrx_batch_create_failed','Disbursement batch creation failed and was rolled back.',array('status'=>500));}$made[]=(int)$wpdb->insert_id;}
		if(!$this->audit_event('HRX_DISBURSEMENTS_PREPARED',$id,'payroll-runs','POSTED','DISBURSEMENT_PENDING')){$wpdb->query('ROLLBACK');return new WP_Error('hrx_batch_audit_failed','Batch audit failed; disbursement preparation was rolled back.',array('status'=>500));}
		$wpdb->update(Stockland_HR_Replacement::table('payroll_runs'),array('status'=>'DISBURSEMENT_PENDING','row_version'=>(int)$run['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>$id,'row_version'=>(int)$run['row_version'],'status'=>'POSTED'));$wpdb->query('COMMIT');
		return rest_ensure_response(array('run_id'=>$id,'payment_method'=>$method,'external_batch_ref'=>$batch,'disbursements_created'=>count($made),'disbursement_ids'=>$made,'message'=>'Payment instructions prepared; actual settlement must be confirmed from bank/cash evidence.'));
	}

	public function disbursement_results($request) {
		global $wpdb;$id=absint($request['id']);$run=$this->read_one('payroll_runs',$id);if(!$run)return new WP_Error('hrx_run_not_found','Payroll run not found.',array('status'=>404));
		if(!in_array(strtoupper($run['status']),array('DISBURSEMENT_PENDING','PARTIALLY_SETTLED'),true))return new WP_Error('hrx_disbursement_state','Run is not waiting for disbursement results.',array('status'=>409));
		$p=$request->get_json_params();$items=$p['results']??null;if(!is_array($items)||!$items)return new WP_Error('hrx_results_required','Provide per-employee settlement results from the real payment batch.',array('status'=>400));
		$table=Stockland_HR_Replacement::table('payroll_disbursements');$attempts=Stockland_HR_Replacement::table('disbursement_attempts');$wpdb->query('START TRANSACTION');$seen=array();
		foreach($items as $item){$personId=absint($item['person_id']??0);$status=strtoupper(sanitize_key((string)($item['status']??'')));$ref=sanitize_text_field((string)($item['external_payment_ref']??''));$code=sanitize_text_field((string)($item['failure_code']??''));$evidence=sanitize_text_field((string)($item['evidence_ref']??''));if(!$personId||!in_array($status,array('SETTLED','FAILED'),true)){$wpdb->query('ROLLBACK');return new WP_Error('hrx_result_invalid','Each settlement result must name a person and be SETTLED or FAILED.',array('status'=>400));}
			$d=$this->read_by($table,array('run_id'=>$id,'person_id'=>$personId));if(!$d||in_array(strtoupper($d['status']),array('SETTLED'),true)){$wpdb->query('ROLLBACK');return new WP_Error('hrx_result_duplicate','Unknown or already-settled employee payment; no duplicate settlement was recorded.',array('status'=>409));}
			if('SETTLED'===$status&&''===$ref){$wpdb->query('ROLLBACK');return new WP_Error('hrx_payment_ref_required','A real unique bank/cash/cheque settlement reference is required for a settled result.',array('status'=>400));}
			$existing=(int)Stockland_DB::read_var($this->prep('SELECT COUNT(*) FROM %i WHERE external_payment_ref=%s',$table,$ref));if('SETTLED'===$status&&$existing>0){$wpdb->query('ROLLBACK');return new WP_Error('hrx_payment_ref_duplicate','External payment reference was already used.',array('status'=>409));}
			$attemptNo=1+(int)Stockland_DB::read_var($this->prep('SELECT COALESCE(MAX(attempt_no),0) FROM %i WHERE disbursement_id=%d',$attempts,(int)$d['id']));
			$attemptKey='HRX-ATTEMPT-'.$d['id'].'-'.$attemptNo;$wpdb->update($table,array('status'=>$status,'external_payment_ref'=>''===$ref?null:$ref,'failure_code'=>''===$code?null:$code,'settled_at'=>'SETTLED'===$status?current_time('mysql'):null,'reconciliation_ref'=>''===$evidence?null:$evidence,'row_version'=>(int)$d['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>(int)$d['id'],'row_version'=>(int)$d['row_version']));
			$insert=$wpdb->insert($attempts,array('disbursement_id'=>(int)$d['id'],'attempt_no'=>$attemptNo,'action'=>$status,'external_payment_ref'=>''===$ref?null:$ref,'response_code'=>''===$code?null:$code,'evidence_ref'=>''===$evidence?null:$evidence,'actor_user_id'=>get_current_user_id(),'attempted_at'=>current_time('mysql'),'request_uuid'=>wp_generate_uuid4(),'idempotency_key'=>$attemptKey,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'),'row_version'=>1));
			if(false===$insert){$wpdb->query('ROLLBACK');return new WP_Error('hrx_attempt_log_failed','Payment attempt history could not be recorded; the transaction was rolled back.',array('status'=>500));}$seen[]=$personId;
		}
		$counts=Stockland_DB::read_row($this->prep("SELECT SUM(status='SETTLED') settled,SUM(status='FAILED') failed,SUM(status='PENDING') pending FROM %i WHERE run_id=%d",$table,$id),ARRAY_A);
		$newStatus=((int)$counts['settled']>0&&(int)$counts['pending']===0&&(int)$counts['failed']===0)?'SETTLED':'PARTIALLY_SETTLED';$wpdb->update(Stockland_HR_Replacement::table('payroll_runs'),array('status'=>$newStatus,'row_version'=>(int)$run['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>$id,'row_version'=>(int)$run['row_version']));
		if(!$this->audit_event('HRX_DISBURSEMENT_RESULTS',$id,'payroll-runs','DISBURSEMENT_PENDING',$newStatus)){$wpdb->query('ROLLBACK');return new WP_Error('hrx_payment_audit_failed','Payment results could not be audited; all results were rolled back.',array('status'=>500));}$wpdb->query('COMMIT');return rest_ensure_response(array('run_id'=>$id,'status'=>$newStatus,'settled'=>(int)$counts['settled'],'failed'=>(int)$counts['failed'],'pending'=>(int)$counts['pending'],'updated_people'=>$seen));
	}

	public function prepare_final_run($request) {
		global $wpdb;$id=absint($request['id']);$case=$this->read_one('separation_cases',$id);if(!$case)return new WP_Error('hrx_separation_missing','Separation case not found.',array('status'=>404));
		if(!in_array(strtoupper($case['status']),array('READY_FOR_FINAL_CALCULATION','CLEARANCE_IN_PROGRESS'),true))return new WP_Error('hrx_final_state','Separation case must be approved and ready for final calculation.',array('status'=>409));
		$clear=json_decode((string)($case['clearance_json']??''),true);if(!is_array($clear)||empty($clear['assets_returned'])||empty($clear['cash_reconciled'])||empty($clear['documents_handed_over'])||empty($case['leave_reconciled']))return new WP_Error('hrx_final_clearance','Complete asset/cash/document clearance and reconcile leave before preparing final settlement.',array('status'=>409));
		if(!empty($case['final_run_id']))return new WP_Error('hrx_final_run_exists','A final settlement run already exists. Use that run or create a reviewed correction; no duplicate run is created.',array('status'=>409));
		$rules=absint($request->get_param('rule_set_id'));$rule=$this->read_one('rule_sets',$rules);if(!$rule)return new WP_Error('hrx_rule_missing','Choose a source-backed approved rule set for the final period.',array('status'=>400));
		$start=substr((string)$case['notice_date'],0,7);if(!$start||'0000-00'===$start)$start=substr((string)$case['proposed_exit_date'],0,7);$periodStart=$start.'-01';$periodEnd=(string)$case['proposed_exit_date'];$periodId=$wpdb->insert_id;
		$wpdb->query('START TRANSACTION');$periodCode='FINAL-'.$case['case_code'];$wpdb->insert(Stockland_HR_Replacement::table('payroll_periods'),array('period_code'=>$periodCode,'period_kind'=>'FINAL_SETTLEMENT','period_start'=>$periodStart,'period_end'=>$periodEnd,'input_cutoff'=>$periodEnd.' 23:59:59','payment_date'=>$periodEnd,'status'=>'OPEN','created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'),'row_version'=>1));
		$periodId=(int)$wpdb->insert_id;if(!$periodId){$wpdb->query('ROLLBACK');return new WP_Error('hrx_final_period_failed','Could not create final settlement period.',array('status'=>500));}
		$runCode=$periodCode.'-V1';$wpdb->insert(Stockland_HR_Replacement::table('payroll_runs'),array('run_code'=>$runCode,'period_id'=>$periodId,'run_version'=>1,'run_kind'=>'FINAL_SETTLEMENT','calculator_version'=>self::CALCULATOR_VERSION,'rule_set_id'=>$rules,'separation_case_id'=>$id,'gross_total'=>'0.00','taxable_total'=>'0.00','tax_total'=>'0.00','employee_pension_total'=>'0.00','employer_pension_total'=>'0.00','deductions_total'=>'0.00','net_total'=>'0.00','employer_cost_total'=>'0.00','exception_count'=>0,'status'=>'DRAFT','created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'),'row_version'=>1));
		$runId=(int)$wpdb->insert_id;if(!$runId){$wpdb->query('ROLLBACK');return new WP_Error('hrx_final_run_failed','Could not create final payroll run.',array('status'=>500));}
		$wpdb->update(Stockland_HR_Replacement::table('separation_cases'),array('final_run_id'=>$runId,'status'=>'FINAL_RUN_CALCULATED','row_version'=>(int)$case['row_version']+1,'updated_at'=>current_time('mysql')),array('id'=>$id,'row_version'=>(int)$case['row_version']));
		if(!$this->audit_event('HRX_FINAL_RUN_PREPARED',$id,'separation-cases',$case['status'],'FINAL_RUN_CALCULATED')){$wpdb->query('ROLLBACK');return new WP_Error('hrx_final_audit_failed','Final run creation audit failed; operation rolled back.',array('status'=>500));}$wpdb->query('COMMIT');return rest_ensure_response(array('separation_case_id'=>$id,'payroll_run_id'=>$runId,'period_id'=>$periodId,'run_kind'=>'FINAL_SETTLEMENT','status'=>'DRAFT','message'=>'A separate final-settlement run was created. Add/review only evidence-backed approved final-pay components, then use the ordinary rule-locked payroll workflow.'));
	}

	public function reports_summary($request) {
		if(!Stockland_HR_Replacement::schema_status()['schema_ready'])return new WP_Error('hrx_schema_pending','Replacement schema is not installed.',array('status'=>503));
		global $wpdb;$counts=array();$errors=array();
		foreach(array('people','positions','requisitions','employment_contracts','attendance_records','leave_requests','compensation_assignments','payroll_periods','payroll_runs','individual_payees','payee_engagements','payee_payments','separation_cases') as $table){$n=Stockland_DB::read_var($this->prep('SELECT COUNT(*) FROM %i',Stockland_HR_Replacement::table($table)));if(''!==(string)$wpdb->last_error)$errors[]=$table;else$counts[$table]=(int)$n;}
		if($errors)return new WP_Error('hrx_report_query_failed','Management report has query errors; values are not presented as zero.',array('status'=>500,'failed_sources'=>$errors));
		return rest_ensure_response(array('as_of'=>current_time('mysql'),'scope'=>'current-authorized-user','counts'=>$counts,'note'=>'Counts are source-backed row counts. They are not substituted for payroll, bank or GL reconciliation.'));
	}

	private function clean_payload($payload,$allow) {
		$out=array();$unknown=array_diff(array_keys($payload),$allow);$reserved=array('id','status','row_version','created_by','created_at','updated_at','approved_by','approved_at','signed_at','signed_document_sha256','posted_journal_id','employee_number','person_kind');
		if($unknown||array_intersect(array_keys($payload),$reserved))return new WP_Error('hrx_field_not_allowed','The request contains a protected or unsupported field.',array('status'=>400,'unsupported_fields'=>array_values(array_unique(array_merge($unknown,array_intersect(array_keys($payload),$reserved))))));
		$intFields=array('fiscal_year','location_id','position_id','manager_person_id','reporting_position_id','requested_headcount','approved_headcount','requisition_id','requester_user_id','openings','person_id','application_id','interviewer_user_id','contract_id','owner_user_id','subject_id','manager_user_id','shift_assignment_id','break_minutes','ordinary_minutes','overtime_minutes','correction_of_id','policy_id','mandatory','paid_flag','requires_evidence','pattern_id','component_id','supersedes_id','rule_set_id','sort_order','period_id','run_version','size_bytes','account_id','payee_id','engagement_id','deliverable_id','tax_decision_id','reviewer_user_id','supersedes_contract_id');
		$decimalFields=array('budget_amount','score_decimal','weekly_hours','accrual_amount','carry_cap','units','amount','quantity','employee_share','employer_share','cash_fee','noncash_value','cash_value','noncash_value','withholding_rate','withholding_amount','base_amount','gross_amount','net_amount','cash_budget','gifted_stock_cost','requested_headcount');
		$dateFields=array('retention_review_on','effective_from','effective_to','open_date','close_date','applied_at','proposed_start','proposed_end','probation_end','start_date','end_date','issued_on','expires_on','work_date','date_from','date_to','coverage_from','coverage_to','notice_date','proposed_exit_date','agreed_on','service_from','service_to','due_on','period_start','period_end','payment_date','publication_date');
		$datetimeFields=array('scheduled_at','clock_in','clock_out','due_at','input_cutoff','captured_at');
		$jsonFields=array('schedule_json','weekly_rest_json','eligibility_json','ratings_json','permitted_scopes_json','metrics_json','parameters_json');
		$booleanFields=array('mandatory','paid_flag','requires_evidence','acceptance_required');
		foreach($payload as $key=>$value){
			if(in_array($key,$intFields,true)){if(null===$value||''===$value){$out[$key]=null;continue;}if(!is_numeric($value)||(int)$value<0)return new WP_Error('hrx_integer_invalid','Invalid integer field: '.$key,array('status'=>400));$out[$key]=(int)$value;continue;}
			if(in_array($key,$decimalFields,true)){if(null===$value||''===$value){$out[$key]=null;continue;}if(!preg_match('/^-?\d+(?:\.\d{1,4})?$/',(string)$value))return new WP_Error('hrx_decimal_invalid','Invalid decimal field: '.$key,array('status'=>400));if((float)$value<0&&!in_array($key,array('units'),true))return new WP_Error('hrx_decimal_negative','Negative amount is not allowed for field: '.$key,array('status'=>400));$out[$key]=(string)$value;continue;}
			if(in_array($key,$dateFields,true)){if(null===$value||''===$value){$out[$key]=null;continue;}$date=sanitize_text_field((string)$value);if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!$this->valid_date($date))return new WP_Error('hrx_date_invalid','Invalid date field: '.$key,array('status'=>400));$out[$key]=$date;continue;}
			if(in_array($key,$datetimeFields,true)){if(null===$value||''===$value){$out[$key]=null;continue;}$dt=sanitize_text_field((string)$value);if(!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/',$dt))return new WP_Error('hrx_datetime_invalid','Invalid datetime field: '.$key,array('status'=>400));$out[$key]=str_replace('T',' ',$dt);continue;}
			if(in_array($key,$jsonFields,true)){if(is_string($value))$decoded=json_decode($value,true);else$decoded=$value;if(!is_array($decoded))return new WP_Error('hrx_json_invalid','Invalid JSON object/array for field: '.$key,array('status'=>400));$out[$key]=wp_json_encode($decoded);continue;}
			if('email'===$key||'contact_email'===$key){$s=sanitize_email((string)$value);if(''!==$value&&!is_email($s))return new WP_Error('hrx_email_invalid','Invalid email address.',array('status'=>400));$out[$key]=$s;continue;}
			if('official_url'===$key){$s=esc_url_raw((string)$value,array('http','https'));if(!$s)return new WP_Error('hrx_url_invalid','A valid HTTP(S) source URL is required.',array('status'=>400));$out[$key]=$s;continue;}
			if(preg_match('/(?:sha256|hash)$/i',$key)&&null!==$value&&''!==$value){$s=strtolower(sanitize_text_field((string)$value));if(!preg_match('/^[a-f0-9]{64}$/',$s))return new WP_Error('hrx_hash_invalid','Expected a 64-character SHA-256 value for '.$key,array('status'=>400));$out[$key]=$s;continue;}
			if(in_array($key,$booleanFields,true)){$out[$key]=filter_var($value,FILTER_VALIDATE_BOOLEAN)?1:0;continue;}
			$s=is_scalar($value)?(string)$value:'';if(in_array($key,array('justification','reason','decision_reason','rationale','acceptance_note','description'),true))$out[$key]=sanitize_textarea_field($s);else$out[$key]=sanitize_text_field($s);
			if(strlen($out[$key])>5000)return new WP_Error('hrx_text_too_long','Text field exceeds the allowed length: '.$key,array('status'=>400));
		}
		return $out;
	}

	private function validate_relationships($entity,$data) {
		$relations=array(
			'workforce-plans'=>array('position_id'=>'positions'),
			'requisitions'=>array('workforce_plan_id'=>'workforce_plans','position_id'=>'positions'),
			'applications'=>array('requisition_id'=>'requisitions','person_id'=>'people'),
			'interview-events'=>array('application_id'=>'applications'),
			'offers'=>array('application_id'=>'applications'),
			'employment-contracts'=>array('person_id'=>'people','offer_id'=>'offers'),
			'onboarding-tasks'=>array('person_id'=>'people','contract_id'=>'employment_contracts'),
			'employee-documents'=>array('subject_id'=>null),
			'shift-assignments'=>array('person_id'=>'people','pattern_id'=>'shift-patterns'),
			'attendance-records'=>array('person_id'=>'people','shift_assignment_id'=>'shift-assignments','correction_of_id'=>'attendance-records'),
			'leave-requests'=>array('person_id'=>'people','policy_id'=>'leave-policies'),
			'compensation-assignments'=>array('person_id'=>'people','component_id'=>'compensation-components','supersedes_id'=>'compensation-assignments'),
			'benefit-enrollments'=>array('person_id'=>'people'),
			'rule-components'=>array('rule_set_id'=>'rule-sets'),
			'payroll-runs'=>array('period_id'=>'payroll-periods','rule_set_id'=>'rule-sets'),
			'separation-cases'=>array('person_id'=>'people'),
			'learning-records'=>array('person_id'=>'people'),
			'performance-reviews'=>array('person_id'=>'people'),
			'payee-engagements'=>array('payee_id'=>'individual-payees'),
			'payee-deliverables'=>array('engagement_id'=>'payee-engagements'),
			'payee-tax-decisions'=>array('engagement_id'=>'payee-engagements','rule_set_id'=>'rule-sets'),
			'payee-payments'=>array('engagement_id'=>'payee-engagements','payee_id'=>'individual-payees','deliverable_id'=>'payee-deliverables','tax_decision_id'=>'payee-tax-decisions'),
			'campaigns'=>array('account_id'=>'social-accounts','payee_id'=>'individual-payees','engagement_id'=>'payee-engagements'),
			'campaign-snapshots'=>array('campaign_id'=>'campaigns'),
		);
		if(!isset($relations[$entity]))return true;
		foreach($relations[$entity] as $field=>$parent){if(empty($data[$field]))continue;if(null===$parent)continue;$meta=self::entities()[$parent]??null;if(!$meta)return false;$row=$this->read_one($meta['table'],(int)$data[$field]);if(!$row)return false;}
		return true;
	}

	private function default_status($entity) {
		$defaults=array('people'=>'ACTIVE','applications'=>'APPLIED','offers'=>'DRAFT','employment-contracts'=>'DRAFT','onboarding-tasks'=>'OPEN','employee-documents'=>'PENDING_REVIEW','shift-patterns'=>'DRAFT','shift-assignments'=>'DRAFT','attendance-records'=>'DRAFT','leave-policies'=>'DRAFT','leave-requests'=>'DRAFT','compensation-components'=>'DRAFT','compensation-assignments'=>'DRAFT','benefit-enrollments'=>'DRAFT','rule-sets'=>'LEGAL_REVIEW','rule-components'=>'DRAFT','payroll-periods'=>'OPEN','payroll-runs'=>'DRAFT','separation-cases'=>'DRAFT','individual-payees'=>'ACTIVE','payee-engagements'=>'REQUESTED','payee-deliverables'=>'PENDING','payee-tax-decisions'=>'LEGAL_REVIEW','payee-payments'=>'REQUESTED','social-accounts'=>'ACTIVE','campaigns'=>'PLANNED','campaign-snapshots'=>'CURRENT','resource-documents'=>'DRAFT');return $defaults[$entity]??'DRAFT';
	}
	private function redact_row($row) {
		if(!is_array($row))return $row;
		// Payroll data is hidden unless the user has the dedicated capability.
		if(!Stockland_Authority::can_view_payroll()){
			foreach(array('amount','employee_share','employer_share','cash_fee','noncash_value','gross_amount','withholding_amount','net_amount','taxable_total','tax_total','employee_pension_total','employer_pension_total','deductions_total','net_total','employer_cost_total','budget_amount','cash_budget','gifted_stock_cost','withholding_rate','base_amount','pensionable_base','taxable_base') as $f)unset($row[$f]);
		}
		if(!Stockland_Authority::is_owner())foreach(array('taxpayer_identity_ref','private_file_ref','file_sha256','signed_document_sha256','signed_document_ref') as $f)unset($row[$f]);
		return $row;
	}
	private function row_access($meta,$row) {
		if(!Stockland_Authority::company_scope()){if(!$meta['location']||empty($row['location_id'])||!Stockland_Authority::can_access_location((int)$row['location_id']))return false;}
		return true;
	}
	private function location_access($id) { return empty($id)||Stockland_Authority::can_access_location((int)$id); }
	private function read_one($tableSuffix,$id) {
		global $wpdb;$table=Stockland_HR_Replacement::table($tableSuffix);return Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',$table,(int)$id),ARRAY_A);
	}
	private function read_by($table,$where) {
		global $wpdb;$clauses=array();$values=array($table);foreach($where as $k=>$v){$clauses[]=$k.'=%s';$values[]=(string)$v;}return Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE '.implode(' AND ',$clauses).' LIMIT 1',$values),ARRAY_A);
	}
	private function table_has_column($table,$column) {
		global $wpdb;$cols=Stockland_DB::read_results($wpdb->prepare('SHOW COLUMNS FROM %i',$table),ARRAY_A);foreach((array)$cols as $c)if(($c['Field']??'')===$column)return true;return false;
	}
	private function prep($sql,...$values) { global $wpdb;return $wpdb->prepare($sql,$values); }
	private function valid_date($date) { if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$date,$m))return false;return checkdate((int)$m[2],(int)$m[3],(int)$m[1]); }
	private function audit_event($action,$id,$entity,$old,$new) {
		$ok=Stockland_Audit::log(get_current_user_id(),null,$action,null,null,$old,$new,$entity.' workflow',$entity.':'.$id,null);
		return (bool)$ok;
	}
	private function append_lifecycle_if_person($entity,$row,$action,$reason,$actor) {
		$personId=0;if(isset($row['person_id']))$personId=(int)$row['person_id'];elseif('people'===$entity)$personId=(int)$row['id'];elseif('applications'===$entity)$personId=(int)$row['person_id'];elseif('employment-contracts'===$entity)$personId=(int)$row['person_id'];
		if(!$personId)return true;
		return $this->append_lifecycle($action,$personId,current_time('Y-m-d'),$entity,(int)($row['id']??0),$reason,$actor);
	}
	private function append_lifecycle($action,$personId,$effective,$refType,$refId,$reason,$actor=null) {
		global $wpdb;$table=Stockland_HR_Replacement::table('lifecycle_events');$uuid=wp_generate_uuid4();$prior=(string)Stockland_DB::read_var($this->prep('SELECT event_hash FROM %i ORDER BY id DESC LIMIT 1',$table));
		$body=array('uuid'=>$uuid,'person_id'=>$personId,'event_type'=>$action,'effective_on'=>$effective,'reference_type'=>$refType,'reference_id'=>$refId,'reason_code'=>substr(sanitize_key($reason?:$action),0,60),'actor'=>$actor?:get_current_user_id(),'prior_hash'=>$prior);
		$hash=hash('sha256',wp_json_encode($body));
		return false!==$wpdb->insert($table,array('person_id'=>$personId,'event_type'=>$action,'effective_on'=>$effective,'previous_ref'=>$refType.':'.$refId,'new_ref'=>null,'reason_code'=>substr(sanitize_key($reason?:$action),0,60),'evidence_ref'=>null,'actor_user_id'=>$actor?:get_current_user_id(),'approver_user_id'=>null,'event_uuid'=>$uuid,'prior_event_hash'=>$prior?:null,'event_hash'=>$hash,'created_by'=>$actor?:get_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'),'row_version'=>1));
	}
}
