<?php
/**
 * Stockland HRIS replacement REST API.
 *
 * All target writes use the new hris_* authority tables. The legacy /hr routes
 * remain migration-only until a separately approved, verified cutover.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Stockland_REST_HRIS {
	const NS = 'stockland/v16';

	public function __construct() {
		add_action('stockland_register_rest_routes',array($this,'register_routes'));
	}

	public function register_routes() {
		register_rest_route(self::NS,'/hris/schema/status',array('methods'=>'GET','callback'=>array($this,'schema_status'),'permission_callback'=>function(){return Stockland_Authority::can_view_hr();}));
		register_rest_route(self::NS,'/hris/setup/advance',array('methods'=>'POST','callback'=>array($this,'setup_advance'),'permission_callback'=>function(){return is_user_logged_in() && current_user_can('stockland_install_schema') && Stockland_Authority::is_owner();}));
		register_rest_route(self::NS,'/hris/bootstrap',array('methods'=>'GET','callback'=>array($this,'bootstrap'),'permission_callback'=>function(){return Stockland_Authority::can_view_hr();}));
		register_rest_route(self::NS,'/hris/(?P<entity>people|positions|workforce-plans|requisitions|applications|assessments|contracts|documents|onboarding-tasks|work-schedules|time-entries|leave-transactions|compensation-assignments|compensation-items|legal-rules|legal-rule-components|payee-engagements|payee-requests|training-records|performance-reviews|social-snapshots|employments|payroll-runs|payroll-lines|payroll-line-items|payment-batches|payment-instructions|statutory-obligations|remittances|exits|payee-decisions|payee-payments)/(?P<id>\d+)/decision',array('methods'=>'POST','callback'=>array($this,'decision'),'permission_callback'=>function(){return Stockland_Authority::can_manage_hr();}));
		register_rest_route(self::NS,'/hris/(?P<entity>people|positions|workforce-plans|requisitions|applications|assessments|contracts|documents|onboarding-tasks|work-schedules|time-entries|leave-transactions|compensation-assignments|compensation-items|legal-rules|legal-rule-components|payee-engagements|payee-requests|training-records|performance-reviews|social-snapshots|exits)',array(
			array('methods'=>'GET','callback'=>array($this,'list_entity'),'permission_callback'=>function(){return Stockland_Authority::can_view_hr();}),
			array('methods'=>'POST','callback'=>array($this,'create_entity'),'permission_callback'=>function(){return Stockland_Authority::can_manage_hr();})
		));
		register_rest_route(self::NS,'/hris/applications/(?P<id>\d+)/create-pending-hire',array('methods'=>'POST','callback'=>array($this,'create_pending_hire'),'permission_callback'=>function(){return Stockland_Authority::can_approve_payroll();}));
		register_rest_route(self::NS,'/hris/employments/(?P<id>\d+)/activate',array('methods'=>'POST','callback'=>array($this,'activate_hire'),'permission_callback'=>function(){return Stockland_Authority::can_approve_payroll();}));
		register_rest_route(self::NS,'/hris/payroll/runs',array('methods'=>'POST','callback'=>array($this,'create_payroll_run'),'permission_callback'=>function(){return Stockland_Authority::can_manage_payroll();}));
		register_rest_route(self::NS,'/hris/payroll/runs/(?P<id>\d+)/calculate',array('methods'=>'POST','callback'=>array($this,'calculate_payroll_run'),'permission_callback'=>function(){return Stockland_Authority::can_manage_payroll();}));
		register_rest_route(self::NS,'/hris/payroll/runs/(?P<id>\d+)/submit',array('methods'=>'POST','callback'=>array($this,'submit_payroll_run'),'permission_callback'=>function(){return Stockland_Authority::can_manage_payroll();}));
		register_rest_route(self::NS,'/hris/payroll/runs/(?P<id>\d+)/approve',array('methods'=>'POST','callback'=>array($this,'approve_payroll_run'),'permission_callback'=>function(){return Stockland_Authority::can_approve_payroll();}));
		register_rest_route(self::NS,'/hris/payroll/runs/(?P<id>\d+)/batch',array('methods'=>'POST','callback'=>array($this,'create_payment_batch'),'permission_callback'=>function(){return Stockland_Authority::can_approve_payroll();}));
		register_rest_route(self::NS,'/hris/payroll/instructions/(?P<id>\d+)/result',array('methods'=>'POST','callback'=>array($this,'record_payment_result'),'permission_callback'=>function(){return Stockland_Authority::can_approve_payroll();}));
		register_rest_route(self::NS,'/hris/payroll/obligations/(?P<id>\d+)/remittances',array('methods'=>'POST','callback'=>array($this,'create_remittance'),'permission_callback'=>function(){return Stockland_Authority::can_approve_payroll();}));
		register_rest_route(self::NS,'/hris/payroll/remittances/(?P<id>\d+)/confirm',array('methods'=>'POST','callback'=>array($this,'confirm_remittance'),'permission_callback'=>function(){return Stockland_Authority::can_approve_payroll();}));
		register_rest_route(self::NS,'/hris/exits/(?P<id>\d+)/calculate',array('methods'=>'POST','callback'=>array($this,'calculate_exit'),'permission_callback'=>function(){return Stockland_Authority::can_manage_payroll();}));
		register_rest_route(self::NS,'/hris/exits/(?P<id>\d+)/approve',array('methods'=>'POST','callback'=>array($this,'approve_exit'),'permission_callback'=>function(){return Stockland_Authority::can_approve_payroll();}));
		register_rest_route(self::NS,'/hris/exits/(?P<id>\d+)/pay',array('methods'=>'POST','callback'=>array($this,'pay_exit'),'permission_callback'=>function(){return Stockland_Authority::can_approve_payroll();}));
		register_rest_route(self::NS,'/hris/payees/requests/(?P<id>\d+)/classify',array('methods'=>'POST','callback'=>array($this,'classify_payee_request'),'permission_callback'=>function(){return Stockland_Authority::can_approve_payroll();}));
		register_rest_route(self::NS,'/hris/payees/requests/(?P<id>\d+)/pay',array('methods'=>'POST','callback'=>array($this,'pay_payee_request'),'permission_callback'=>function(){return Stockland_Authority::can_approve_payroll();}));
	}

	private function err($message,$code='hris_invalid',$status=400){return new WP_Error($code,$message,array('status'=>$status));}
	private function table($key){return Stockland_DB::table($key);}
	private function date($value,$optional=true){
		if(($value===null||$value==='')&&$optional)return null;
		$v=sanitize_text_field((string)$value);
		if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$v,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))return false;
		return $v;
	}
	private function money($value,$allow_negative=false){
		try{$v=Stockland_Money::decimal(Stockland_Money::minor($value));if(!$allow_negative&&Stockland_Money::minor($v)<0)return false;return $v;}catch(Throwable $e){return false;}
	}
	private function safe_json($value,$name,$required=false){
		if($value===null||$value===''){if($required)return $this->err($name.' is required JSON.');return null;}
		$encoded=is_string($value)?$value:wp_json_encode($value);
		if(!is_string($encoded)){return $this->err($name.' must be valid JSON.');}
		json_decode($encoded,true);if(json_last_error()!==JSON_ERROR_NONE)return $this->err($name.' must be valid JSON.');
		return $encoded;
	}
	private function own_or_company_scope($row) {
		if(Stockland_Authority::company_scope())return true;
		$location=(int)($row['location_id']??0);
		return $location>0&&Stockland_Authority::can_access_location($location);
	}
	private function require_ready() {
		$s=Stockland_HRIS::schema_status();
		if(!$s['ready'])return $this->err('HRIS candidate schema is not ready. Missing: '.implode(',',$s['missing']).'; drift: '.implode(',',$s['drift']).'. Use the protected bounded setup flow on an isolated test/staging copy.','hris_schema_not_ready',409);
		return true;
	}
	public function schema_status(){return rest_ensure_response(Stockland_HRIS::schema_status());}
	public function setup_advance(WP_REST_Request $r) {
		$offset=max(0,(int)$r->get_param('offset'));
		if($offset>count(Stockland_HRIS::table_keys()))return $this->err('Setup offset is outside the schema plan.');
		try {
			$answer=Stockland_HRIS::install_batch($offset,4);
			if($answer['done']&&$answer['schema']['ready'])update_option(Stockland_HRIS::OPTION_SCHEMA_VERSION,Stockland_HRIS::SCHEMA_VERSION,false);
			return rest_ensure_response($answer);
		} catch(Throwable $e) {
			return $this->err($e->getMessage(),'hris_setup_failed',500);
		}
	}

	private function map() {
		return array(
			'people'=>array('table'=>'hris_people','fields'=>array('person_code','legal_name','preferred_name','email','phone','privacy_notice_version','privacy_basis','retention_until')),
			'employments'=>array('table'=>'hris_employments','fields'=>array('person_id','employee_code','legal_employer','employment_type','position_id','department','location_id','manager_employment_id','wp_user_id','start_date','end_date','probation_end','legacy_employee_id')),
			'positions'=>array('table'=>'hris_positions','fields'=>array('position_code','title','department','grade_code','location_id','headcount_slots','annual_salary_min','annual_salary_max','job_description')),
			'workforce-plans'=>array('table'=>'hris_workforce_plans','fields'=>array('plan_code','fiscal_year','department','location_id','headcount_budget','annual_salary_budget','other_cost_budget','justification')),
			'requisitions'=>array('table'=>'hris_requisitions','fields'=>array('requisition_code','position_id','plan_id','location_id','openings','employment_type','target_start_date','justification')),
			'applications'=>array('table'=>'hris_applications','fields'=>array('application_code','requisition_id','person_id','screening_score','consent_or_privacy_reference')),
			'assessments'=>array('table'=>'hris_assessments','fields'=>array('application_id','assessment_type','scheduled_at','scorecard_version','scores_json','total_score','recommendation','reviewer_note')),
			'contracts'=>array('table'=>'hris_contracts','fields'=>array('contract_code','record_type','version_no','application_id','employment_id','contract_type','start_date','end_date','probation_end','terms_hash','document_id')),
			'documents'=>array('table'=>'hris_documents','fields'=>array('subject_type','subject_id','document_type','vault_reference','content_sha256','version_label','issued_on','expires_on','access_class')),
			'onboarding-tasks'=>array('table'=>'hris_onboarding_tasks','fields'=>array('employment_id','task_code','task_title','responsible_role','due_date','evidence_reference')),
			'work-schedules'=>array('table'=>'hris_work_schedules','fields'=>array('schedule_code','location_id','shift_name','start_time','end_time','break_minutes','effective_from','effective_to','rule_id')),
			'time-entries'=>array('table'=>'hris_time_entries','fields'=>array('employment_id','location_id','schedule_id','work_date','check_in','check_out','ordinary_minutes','overtime_minutes','time_type','source_type','correction_reason')),
			'leave-transactions'=>array('table'=>'hris_leave_transactions','fields'=>array('transaction_code','employment_id','leave_type','transaction_type','start_date','end_date','days','reason','evidence_reference','rule_id','rule_version','reverses_id')),
			'compensation-assignments'=>array('table'=>'hris_compensation_assignments','fields'=>array('assignment_code','employment_id','effective_from','effective_to','base_salary','currency','change_reason','budget_reference')),
			'compensation-items'=>array('table'=>'hris_compensation_items','fields'=>array('assignment_id','component_code','category','component_type','label','amount','frequency','currency','source_reference')),
			'legal-rules'=>array('table'=>'hris_legal_rules','fields'=>array('rule_code','rule_type','version_label','jurisdiction','effective_from','effective_to','source_title','source_url','source_clause','source_sha256','publication_date','rules_json')),
			'legal-rule-components'=>array('table'=>'hris_legal_rule_components','fields'=>array('rule_id','component_code','category','lower_bound','upper_bound','rate','fixed_credit','tax_treatment','exemption_cap','pensionable_treatment','source_clause','parameters_json')),
			'exits'=>array('table'=>'hris_exit_settlements','fields'=>array('settlement_code','employment_id','exit_date','exit_reason','last_work_date','notice_reference','leave_balance_days','components_json')),
			'payee-engagements'=>array('table'=>'hris_payee_engagements','fields'=>array('engagement_code','person_id','engagement_type','campaign_or_contract','start_date','end_date','description')),
			'payee-requests'=>array('table'=>'hris_payee_requests','fields'=>array('request_code','engagement_id','deliverable_reference','delivered_on','cash_amount','in_kind_value','currency','tin_status','business_licence_status','residency_status')),
			'training-records'=>array('table'=>'hris_training_records','fields'=>array('employment_id','training_code','title','training_type','started_on','completed_on','evidence_reference')),
			'performance-reviews'=>array('table'=>'hris_performance_reviews','fields'=>array('employment_id','review_period','reviewer_id','competency_framework_version','scores_json','narrative','employee_comments')),
			'social-snapshots'=>array('table'=>'hris_social_snapshots','fields'=>array('platform','account_label','account_owner','campaign_code','period_from','period_to','captured_at','source_reference','metrics_json','fees','gifted_stock_value','attributed_sales','staleness_status'))
		);
	}

	private function fields_types() {
		return array(
			'person_code'=>'text','legal_name'=>'text','preferred_name'=>'text','email'=>'email','phone'=>'text','privacy_notice_version'=>'text','privacy_basis'=>'text','retention_until'=>'date',
			'person_id'=>'int','employee_code'=>'text','legal_employer'=>'text','employment_type'=>'text','position_id'=>'int','department'=>'text','location_id'=>'int','manager_employment_id'=>'int','wp_user_id'=>'int','start_date'=>'date','end_date'=>'date','probation_end'=>'date','legacy_employee_id'=>'int',
			'position_code'=>'text','title'=>'text','grade_code'=>'text','headcount_slots'=>'int','annual_salary_min'=>'money','annual_salary_max'=>'money','job_description'=>'textarea',
			'plan_code'=>'text','fiscal_year'=>'text','headcount_budget'=>'int','annual_salary_budget'=>'money','other_cost_budget'=>'money','justification'=>'textarea',
			'requisition_code'=>'text','plan_id'=>'int','openings'=>'int','target_start_date'=>'date',
			'application_code'=>'text','requisition_id'=>'int','screening_score'=>'number','consent_or_privacy_reference'=>'text',
			'assessment_type'=>'text','scheduled_at'=>'datetime','scorecard_version'=>'text','scores_json'=>'json','total_score'=>'number','recommendation'=>'text','reviewer_note'=>'textarea',
			'contract_code'=>'text','record_type'=>'text','version_no'=>'int','application_id'=>'int','contract_type'=>'text','terms_hash'=>'hash','document_id'=>'int',
			'subject_type'=>'text','subject_id'=>'int','document_type'=>'text','vault_reference'=>'private_ref','content_sha256'=>'hash','version_label'=>'text','issued_on'=>'date','expires_on'=>'date','access_class'=>'text',
			'employment_id'=>'int','task_code'=>'text','task_title'=>'text','responsible_role'=>'text','due_date'=>'date','evidence_reference'=>'private_ref',
			'schedule_code'=>'text','shift_name'=>'text','start_time'=>'time','end_time'=>'time','break_minutes'=>'int',
			'schedule_id'=>'int','work_date'=>'date','check_in'=>'datetime','check_out'=>'datetime','ordinary_minutes'=>'int','overtime_minutes'=>'int','time_type'=>'text','source_type'=>'text','correction_reason'=>'textarea',
			'transaction_code'=>'text','leave_type'=>'text','transaction_type'=>'text','days'=>'number','reason'=>'textarea','rule_id'=>'int','rule_version'=>'text','reverses_id'=>'int',
			'assignment_code'=>'text','effective_from'=>'date','effective_to'=>'date','base_salary'=>'money','currency'=>'text','change_reason'=>'textarea','budget_reference'=>'text',
			'assignment_id'=>'int','component_code'=>'text','category'=>'text','component_type'=>'text','label'=>'text','amount'=>'money','frequency'=>'text','source_reference'=>'private_ref',
			'rule_code'=>'text','rule_type'=>'text','jurisdiction'=>'text','source_title'=>'text','source_url'=>'https_url','source_clause'=>'text','source_sha256'=>'hash','publication_date'=>'date','rules_json'=>'json',
			'lower_bound'=>'money_nullable','upper_bound'=>'money_nullable','rate'=>'rate_nullable','fixed_credit'=>'money_nullable','tax_treatment'=>'text','exemption_cap'=>'money_nullable','pensionable_treatment'=>'text','parameters_json'=>'json',
			'settlement_code'=>'text','exit_date'=>'date','exit_reason'=>'text','last_work_date'=>'date','notice_reference'=>'private_ref','leave_balance_days'=>'number_nullable','components_json'=>'json',
			'engagement_code'=>'text','engagement_type'=>'text','campaign_or_contract'=>'text','description'=>'textarea','request_code'=>'text','deliverable_reference'=>'private_ref','delivered_on'=>'date','cash_amount'=>'money','in_kind_value'=>'money','tin_status'=>'text','business_licence_status'=>'text','residency_status'=>'text',
			'training_code'=>'text','training_type'=>'text','started_on'=>'date','completed_on'=>'date','review_period'=>'text','reviewer_id'=>'int','competency_framework_version'=>'text','narrative'=>'textarea','employee_comments'=>'textarea',
			'platform'=>'text','account_label'=>'text','account_owner'=>'text','campaign_code'=>'text','period_from'=>'date','period_to'=>'date','captured_at'=>'datetime','metrics_json'=>'json','fees'=>'money','gifted_stock_value'=>'money','attributed_sales'=>'money_nullable','staleness_status'=>'text'
		);
	}

	private function sanitize_payload($payload,$fields) {
		$allowed=$this->fields_types();$out=array();
		foreach($fields as $field) {
			if(!array_key_exists($field,$payload))continue;
			$v=$payload[$field];$type=$allowed[$field]??'text';
			switch($type){
				case 'date': $v=$this->date($v,true);if($v===false)return $this->err('Invalid date for '.$field.'.');break;
				case 'datetime':
					if($v===null||$v===''){$v=null;break;}
					$s=sanitize_text_field((string)$v);$dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$s);
					if(!$dt)return $this->err('Invalid date/time for '.$field.'.');$v=$dt->format('Y-m-d H:i:s');break;
				case 'money':
					$v=$this->money($v,false);if($v===false)return $this->err('Invalid non-negative money amount for '.$field.'.');break;
				case 'money_nullable':
					if($v===null||$v===''){$v=null;break;}$v=$this->money($v,false);if($v===false)return $this->err('Invalid money amount for '.$field.'.');break;
				case 'number':
					if(!is_numeric($v)||!is_finite((float)$v)||abs((float)$v)>1000000000)return $this->err('Invalid number for '.$field.'.');$v=(string)$v;break;
				case 'number_nullable':
					if($v===null||$v===''){$v=null;break;}if(!is_numeric($v)||!is_finite((float)$v))return $this->err('Invalid number for '.$field.'.');$v=(string)$v;break;
				case 'int':
					if($v===null||$v===''){$v=null;break;}$v=filter_var($v,FILTER_VALIDATE_INT);if($v===false||$v<0)return $this->err('Invalid non-negative integer for '.$field.'.');break;
				case 'rate_nullable':
					if($v===null||$v===''){$v=null;break;}if(!is_numeric($v)||(float)$v<0||(float)$v>1)return $this->err('Rate for '.$field.' must be between 0 and 1 as a fraction.');$v=(string)$v;break;
				case 'json': $v=$this->safe_json($v,$field,$field==='rules_json'||$field==='components_json'||$field==='metrics_json');if(is_wp_error($v))return $v;break;
				case 'email': $v=sanitize_email((string)$v);if(($payload[$field]??'')!==''&&!is_email($v))return $this->err('Invalid email address.');break;
				case 'https_url': $v=esc_url_raw((string)$v);if(!preg_match('~^https://~i',$v))return $this->err('Rule source must use HTTPS.');break;
				case 'hash': $v=strtolower(sanitize_text_field((string)$v));if(!preg_match('/^[a-f0-9]{64}$/',$v))return $this->err($field.' must be a SHA-256 hex string.');break;
				case 'private_ref':
					$v=sanitize_text_field((string)$v);if($v===''||preg_match('~^(https?:)?//~i',$v)||strpos($v,'..')!==false)return $this->err($field.' must be a private-vault reference, not a public URL or filesystem path.');break;
				case 'textarea': $v=sanitize_textarea_field((string)$v);break;
				case 'time': $v=sanitize_text_field((string)$v);if(!preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$v))return $this->err('Invalid time for '.$field.'.');break;
				default: $v=sanitize_text_field((string)$v);
			}
			$out[$field]=$v;
		}
		return $out;
	}

	private function list_rows($entity,$limit=100) {
		global $wpdb;
		$map=$this->map();if(!isset($map[$entity]))return array();
		$s=$this->require_ready();if(is_wp_error($s))return $s;
		$table=$this->table($map[$entity]['table']);
		$rows=Stockland_DB::read_results($wpdb->prepare('SELECT * FROM %i ORDER BY id DESC LIMIT %d',$table,max(1,min(200,(int)$limit))),ARRAY_A);
		if($wpdb->last_error)return $this->err('HRIS list query failed. Check System Health.','hris_query_failed',500);
		return array_values(array_filter((array)$rows,function($row){return $this->own_or_company_scope($row);}));
	}

	public function bootstrap() {
		$status=Stockland_HRIS::schema_status();
		if(!$status['ready'])return rest_ensure_response(array('schema'=>$status,'authority_active'=>Stockland_HRIS::is_authority_active(),'requires_setup'=>true,'capabilities'=>$this->capabilities(),'lists'=>array()));
		$entities=array('people','employments','positions','workforce-plans','requisitions','applications','assessments','contracts','documents','onboarding-tasks','work-schedules','time-entries','leave-transactions','compensation-assignments','compensation-items','legal-rules','legal-rule-components','payroll-runs','payroll-lines','payment-batches','payment-instructions','statutory-obligations','remittances','exits','payee-engagements','payee-requests','payee-decisions','payee-payments','training-records','performance-reviews','social-snapshots');
		$lists=array();
		foreach($entities as $e){
			if(in_array($e,array('payroll-runs','payroll-lines','payment-batches','payment-instructions','statutory-obligations','remittances','payee-decisions','payee-payments','compensation-assignments','compensation-items','legal-rules','legal-rule-components','exits'),true)&&!Stockland_Authority::can_view_payroll()){$lists[$e]=array();continue;}
			$result=$this->list_rows($e,50);$lists[$e]=is_wp_error($result)?array():$result;
		}
		return rest_ensure_response(array('schema'=>$status,'authority_active'=>Stockland_HRIS::is_authority_active(),'requires_setup'=>false,'capabilities'=>$this->capabilities(),'lists'=>$lists,'migration_note'=>'Legacy HR tables remain preserved as migration evidence; new HRIS writes only target hris_* tables.'));
	}
	private function capabilities() {
		return array('view_hr'=>Stockland_Authority::can_view_hr(),'manage_hr'=>Stockland_Authority::can_manage_hr(),'prepare_hr'=>Stockland_Authority::can_prepare_hr(),'view_payroll'=>Stockland_Authority::can_view_payroll(),'manage_payroll'=>Stockland_Authority::can_manage_payroll(),'approve_payroll'=>Stockland_Authority::can_approve_payroll(),'install_schema'=>current_user_can('stockland_install_schema') && Stockland_Authority::is_owner(),'company_scope'=>Stockland_Authority::company_scope());
	}

	public function list_entity(WP_REST_Request $r) {
		$entity=sanitize_key((string)$r['entity']);$rows=$this->list_rows($entity,$r->get_param('limit')?:100);if(is_wp_error($rows))return $rows;return rest_ensure_response(array('entity'=>$entity,'rows'=>$rows));
	}
	public function create_entity(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;
		$entity=sanitize_key((string)$r['entity']);$map=$this->map();if(!isset($map[$entity]))return $this->err('Unknown HRIS entity.');
		$allowed=$map[$entity]['fields'];
		if(in_array($entity,array('employments','assessments','documents','onboarding-tasks','time-entries','leave-transactions','compensation-assignments','compensation-items','legal-rules','legal-rule-components','exits','payee-engagements','payee-requests','social-snapshots'),true)&&!Stockland_Authority::can_manage_payroll()&&!Stockland_Authority::can_manage_hr())return $this->err('HR/payroll management permission required.','hris_forbidden',403);
		$p=$r->get_json_params();if(!is_array($p))return $this->err('JSON object body required.');
		$data=$this->sanitize_payload($p,$allowed);if(is_wp_error($data))return $data;
		$required=array(
			'people'=>array('person_code','legal_name'),'positions'=>array('position_code','title','department'),
			'workforce-plans'=>array('plan_code','fiscal_year','department','justification'),'requisitions'=>array('requisition_code','position_id','openings','employment_type','justification'),
			'applications'=>array('application_code','requisition_id','person_id'),'assessments'=>array('application_id','assessment_type'),
			'contracts'=>array('contract_code','record_type','contract_type','start_date'),'documents'=>array('subject_type','subject_id','document_type','vault_reference'),
			'onboarding-tasks'=>array('employment_id','task_code','task_title','responsible_role'),'work-schedules'=>array('schedule_code','shift_name','start_time','end_time','effective_from'),
			'time-entries'=>array('employment_id','work_date'),'leave-transactions'=>array('transaction_code','employment_id','leave_type','transaction_type','days'),
			'compensation-assignments'=>array('assignment_code','employment_id','effective_from','base_salary','change_reason'),'compensation-items'=>array('assignment_id','component_code','category','component_type','label','amount'),
			'legal-rules'=>array('rule_code','rule_type','version_label','effective_from','source_title','source_url','source_clause','source_sha256','rules_json'),
			'legal-rule-components'=>array('rule_id','component_code','category','source_clause'),'exits'=>array('settlement_code','employment_id','exit_date','exit_reason','components_json'),
			'payee-engagements'=>array('engagement_code','person_id','engagement_type','campaign_or_contract','start_date'),
			'payee-requests'=>array('request_code','engagement_id','deliverable_reference','cash_amount','in_kind_value'),
			'training-records'=>array('employment_id','training_code','title','training_type'),'performance-reviews'=>array('employment_id','review_period','reviewer_id'),
			'social-snapshots'=>array('platform','account_label','period_from','period_to','captured_at','source_reference','metrics_json')
		);
		foreach($required[$entity]??array() as $f)if(!isset($data[$f])||$data[$f]==='')return $this->err('Required field missing: '.$f.'.');
		if(isset($data['location_id'])&&$data['location_id']!==null&&!Stockland_Authority::can_access_location((int)$data['location_id']))return $this->err('The selected location is outside your assigned scope.','hris_location_forbidden',403);
		if($entity==='people'&&strlen(trim($data['legal_name']??''))<2)return $this->err('A legal name is required.');
		if($entity==='applications'){
			$req=Stockland_DB::read_row($GLOBALS['wpdb']->prepare("SELECT * FROM %i WHERE id=%d AND status='APPROVED'",$this->table('hris_requisitions'),(int)$data['requisition_id']),ARRAY_A);
			if(!$req)return $this->err('Applications can only be added to an approved requisition.','hris_requisition_not_approved',409);
			$person=Stockland_DB::read_row($GLOBALS['wpdb']->prepare('SELECT id FROM %i WHERE id=%d AND status=%s',$this->table('hris_people'),(int)$data['person_id'],'ACTIVE'),ARRAY_A);if(!$person)return $this->err('Candidate/person record does not exist.');
		}
		if($entity==='contracts'&& !in_array(strtoupper($data['record_type']??''),array('OFFER','CONTRACT','AMENDMENT'),true))return $this->err('record_type must be OFFER, CONTRACT or AMENDMENT.');
		if($entity==='documents'&&preg_match('~^https?://~i',(string)$data['vault_reference']))return $this->err('Signed HR documents must use an approved private vault reference, never a public URL.');
		if($entity==='legal-rules'){
			$data['rule_type']=strtoupper(sanitize_key((string)$data['rule_type']));
			if(!in_array($data['rule_type'],array('PAYE','PENSION','ALLOWANCE','OVERTIME','LEAVE','FINAL_SETTLEMENT','PAYEE_WITHHOLDING','PAYROLL'),true))return $this->err('Unsupported statutory/policy rule type.');
			if($data['effective_to']&&$data['effective_to']<$data['effective_from'])return $this->err('Rule effective_to cannot precede effective_from.');
			$data['review_status']='DRAFT';
		}
		if($entity==='compensation-assignments'){
			$data['approval_status']='DRAFT';
			$emp=Stockland_DB::read_row($GLOBALS['wpdb']->prepare('SELECT * FROM %i WHERE id=%d',$this->table('hris_employments'),(int)$data['employment_id']),ARRAY_A);
			if(!$emp||!$this->own_or_company_scope($emp))return $this->err('Employment does not exist or is outside scope.');
		}
		if($entity==='exits'){$data['status']='DRAFT';}
		$base=array('created_by'=>get_current_user_id());
		if($entity==='people')$base['status']='ACTIVE';
		if($entity==='applications'){$base['stage']='APPLIED';$base['decision']='PENDING';}
		if($entity==='assessments')$base['reviewer_id']=get_current_user_id();
		if($entity==='documents'){$base['status']='PENDING_REVIEW';}
		if($entity==='onboarding-tasks')$base['status']='OPEN';
		if($entity==='work-schedules')$base['status']='DRAFT';
		if($entity==='time-entries'){$base['status']='DRAFT';$base['source_type']=sanitize_key((string)($data['source_type']??'MANUAL'));}
		if($entity==='leave-transactions'){$base['status']='DRAFT';if(!in_array(strtoupper($data['transaction_type']),array('REQUEST','ACCRUAL','OPENING','ADJUSTMENT','REVERSAL','USAGE'),true))return $this->err('Invalid leave transaction type.');}
		if($entity==='compensation-items')$base['status']='DRAFT';
		if($entity==='legal-rule-components'){$base['created_by']=get_current_user_id();}
		if($entity==='payee-engagements')$base['status']='DRAFT';
		if($entity==='payee-requests'){$base['status']='DRAFT';$base['classification_status']='NEEDS_REVIEW';}
		if($entity==='performance-reviews'){$base['status']='DRAFT';}
		if($entity==='social-snapshots'){if($data['period_to']<$data['period_from'])return $this->err('Report period end must not precede period start.');$base['created_by']=get_current_user_id();}
		$insert=array_merge($data,$base);
		$insert=$this->null_optionals($insert);
		$ok=Stockland_DB::insert_record($this->table($map[$entity]['table']),$insert);
		if(false===$ok)return $this->err('HRIS record could not be created. Check duplicate keys, required fields and System Health.','hris_insert_failed',409);
		$id=(int)$GLOBALS['wpdb']->insert_id;
		if(!Stockland_HRIS::log_event($entity,$id,'RECORD_CREATED',null,$insert['status']??$insert['approval_status']??'DRAFT',array('record_code'=>$insert['person_code']??$insert['employee_code']??$insert['position_code']??$insert['requisition_code']??$insert['request_code']??$insert['rule_code']??$insert['contract_code']??$insert['assignment_code']??$insert['transaction_code']??('id-'.$id))))return $this->err('Audit chain could not record the create event; review logs before retry.','hris_audit_failed',500);
		return rest_ensure_response(array('id'=>$id,'entity'=>$entity,'status'=>$insert['status']??$insert['approval_status']??$insert['review_status']??'DRAFT','message'=>'Saved to the separate HRIS candidate schema; the legacy four-table source remains unchanged.'));
	}
	private function null_optionals($data){foreach($data as $k=>$v)if($v==='')$data[$k]=null;return $data;}

	public function decision(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;
		$entity=sanitize_key((string)$r['entity']);$id=(int)$r['id'];$p=$r->get_json_params();$action=strtoupper(sanitize_key((string)($p['decision']??'')));$note=sanitize_textarea_field((string)($p['note']??''));$map=$this->map();
		$key_map=array('requisitions'=>'hris_requisitions','applications'=>'hris_applications','contracts'=>'hris_contracts','documents'=>'hris_documents','onboarding-tasks'=>'hris_onboarding_tasks','work-schedules'=>'hris_work_schedules','time-entries'=>'hris_time_entries','leave-transactions'=>'hris_leave_transactions','compensation-assignments'=>'hris_compensation_assignments','compensation-items'=>'hris_compensation_items','legal-rules'=>'hris_legal_rules','payee-engagements'=>'hris_payee_engagements','payee-requests'=>'hris_payee_requests','training-records'=>'hris_training_records','performance-reviews'=>'hris_performance_reviews','exits'=>'hris_exit_settlements');
		if(!isset($key_map[$entity]))return $this->err('This entity has a dedicated workflow or is read-only.');
		$table=$this->table($key_map[$entity]);global $wpdb;
		if(Stockland_DB::execute_query('START TRANSACTION')===false)return $this->err('Could not start HRIS workflow transaction.','hris_transaction_failed',500);
		try {
			$row=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$table,$id),ARRAY_A);
			if(!$row||!$this->own_or_company_scope($row))throw new RuntimeException('Record not found or outside location scope.');
			$from=(string)($row['status']??$row['approval_status']??$row['review_status']??'');
			$updates=array();$to=$from;$reviewer=Stockland_Authority::can_approve_payroll();
			$approval_actions=array('APPROVE','REJECT','SIGN','SELECT','ACTIVATE','CONFIRM','CLOSE');
			if(in_array($action,$approval_actions,true)&&(int)($row['created_by']??$row['submitted_by']??0)===get_current_user_id())throw new RuntimeException('Maker-checker: the creator/preparer cannot approve the same record.');
			if(in_array($action,array('APPROVE','SELECT','ACTIVATE','CONFIRM','CLOSE'),true)&&!$reviewer)throw new RuntimeException('Owner approval is required for this decision.');
			switch($entity){
				case 'requisitions':
					if($action==='SUBMIT'&&$from==='DRAFT'){$to='SUBMITTED';$updates=array('status'=>$to,'submitted_by'=>get_current_user_id(),'submitted_at'=>current_time('mysql'));}
					elseif($action==='APPROVE'&&$from==='SUBMITTED'){$to='APPROVED';$updates=array('status'=>$to,'approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'),'decision_note'=>$note);}
					elseif($action==='REJECT'&&$from==='SUBMITTED'&&strlen($note)>=10){$to='REJECTED';$updates=array('status'=>$to,'approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'),'decision_note'=>$note);}
					else throw new RuntimeException('Invalid requisition transition.');
					break;
				case 'applications':
					if($action==='SCREEN'&&in_array($from,array('APPLIED','SCREENING'),true)){$to='SCREENING';$updates=array('stage'=>'SCREENING');}
					elseif($action==='INTERVIEW'&&in_array($from,array('APPLIED','SCREENING','INTERVIEW'),true)){$to='INTERVIEW';$updates=array('stage'=>'INTERVIEW');}
					elseif($action==='SELECT'&&in_array($from,array('PENDING','APPLIED','SCREENING','INTERVIEW'),true)&&strlen($note)>=20){$to='SELECTED';$updates=array('stage'=>'SELECTED','decision'=>'SELECTED','selection_rationale'=>$note,'decided_by'=>get_current_user_id(),'decided_at'=>current_time('mysql'));}
					elseif($action==='REJECT'&&strlen($note)>=20&&in_array($from,array('PENDING','APPLIED','SCREENING','INTERVIEW'),true)){$to='REJECTED';$updates=array('stage'=>'REJECTED','decision'=>'REJECTED','selection_rationale'=>$note,'decided_by'=>get_current_user_id(),'decided_at'=>current_time('mysql'));}
					else throw new RuntimeException('Invalid application transition. Human decisions require at least 20 characters of rationale.');
					break;
				case 'contracts':
					if($action==='SUBMIT'&&$from==='DRAFT'){$to='SUBMITTED';$updates=array('status'=>$to,'issued_at'=>current_time('mysql'));}
					elseif($action==='SIGN'&&$from==='SUBMITTED'&&(int)($row['document_id']??0)>0){$doc=Stockland_DB::read_row($wpdb->prepare("SELECT id,status,document_type,vault_reference FROM %i WHERE id=%d",$this->table('hris_documents'),(int)$row['document_id']),ARRAY_A);if(!$doc||$doc['status']!=='VERIFIED'||strtoupper($doc['document_type'])!=='EMPLOYMENT_CONTRACT')throw new RuntimeException('A verified private-vault EMPLOYMENT_CONTRACT document is required.');$to='SIGNED';$updates=array('status'=>$to,'signed_at'=>current_time('mysql'));}
					elseif($action==='APPROVE'&&$from==='SIGNED'&&strlen($note)>=20){$to='APPROVED';$updates=array('status'=>$to,'approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'));}
					elseif($action==='REJECT'&&in_array($from,array('SUBMITTED','SIGNED'),true)&&strlen($note)>=20){$to='REJECTED';$updates=array('status'=>$to);}
					else throw new RuntimeException('Invalid offer/contract state transition.');
					break;
				case 'documents':
					if($action==='VERIFY'&&$from==='PENDING_REVIEW'&&preg_match('/^[a-f0-9]{64}$/i',(string)($row['content_sha256']??''))&&strlen($note)>=10){$to='VERIFIED';$updates=array('status'=>$to,'verified_by'=>get_current_user_id(),'verified_at'=>current_time('mysql'));}
					elseif($action==='REJECT'&&$from==='PENDING_REVIEW'){$to='REJECTED';$updates=array('status'=>$to);}
					else throw new RuntimeException('Document verification requires a private reference, content hash and review note.');
					break;
				case 'onboarding-tasks':
					if($action==='COMPLETE'&&$from==='OPEN'&&!empty($row['evidence_reference'])){$to='COMPLETE';$updates=array('status'=>$to,'completed_by'=>get_current_user_id(),'completed_at'=>current_time('mysql'));}
					elseif($action==='REOPEN'&&$from==='COMPLETE'&&strlen($note)>=10&&Stockland_Authority::is_owner()){$to='OPEN';$updates=array('status'=>$to,'completed_by'=>null,'completed_at'=>null);}
					else throw new RuntimeException('Task completion needs evidence and an authorized state transition.');
					break;
				case 'work-schedules':
					if($action==='SUBMIT'&&$from==='DRAFT'){$to='SUBMITTED';$updates=array('status'=>$to);}
					elseif($action==='APPROVE'&&$from==='SUBMITTED'){$to='APPROVED';$updates=array('status'=>$to,'approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'));}
					else throw new RuntimeException('Invalid work schedule transition.');
					break;
				case 'time-entries':
					if($action==='SUBMIT'&&$from==='DRAFT'){$to='SUBMITTED';$updates=array('status'=>$to,'submitted_by'=>get_current_user_id());}
					elseif($action==='APPROVE'&&$from==='SUBMITTED'&&strlen($note)>=10){$to='APPROVED';$updates=array('status'=>$to,'approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'));}
					elseif($action==='REJECT'&&$from==='SUBMITTED'){$to='REJECTED';$updates=array('status'=>$to,'correction_reason'=>$note);}
					else throw new RuntimeException('Invalid time entry transition. Corrections need a reason and approval; approved entries are not silently overwritten.');
					break;
				case 'leave-transactions':
					if($action==='SUBMIT'&&$from==='DRAFT'&&strtoupper($row['transaction_type'])==='REQUEST'){$to='SUBMITTED';$updates=array('status'=>$to,'submitted_by'=>get_current_user_id());}
					elseif($action==='APPROVE'&&$from==='SUBMITTED'){$to='APPROVED';$updates=array('status'=>$to,'decided_by'=>get_current_user_id(),'decided_at'=>current_time('mysql'),'decision_note'=>$note);}
					elseif($action==='REJECT'&&$from==='SUBMITTED'){$to='REJECTED';$updates=array('status'=>$to,'decided_by'=>get_current_user_id(),'decided_at'=>current_time('mysql'),'decision_note'=>$note);}
					elseif($action==='POST'&&$from==='DRAFT'&&in_array(strtoupper($row['transaction_type']),array('OPENING','ACCRUAL','ADJUSTMENT'),true)&&Stockland_Authority::is_owner()&&strlen($note)>=20){$to='POSTED';$updates=array('status'=>$to,'decided_by'=>get_current_user_id(),'decided_at'=>current_time('mysql'),'decision_note'=>$note);}
					else throw new RuntimeException('Invalid leave workflow transition. Opening/accrual/adjustment entries require documented Owner approval.');
					break;
				case 'compensation-assignments':
					if($action==='SUBMIT'&&$from==='DRAFT'){$to='SUBMITTED';$updates=array('approval_status'=>$to);}
					elseif($action==='APPROVE'&&$from==='SUBMITTED'){
						if((int)$row['created_by']===get_current_user_id())throw new RuntimeException('Compensation maker cannot approve their own salary change.');
						$overlap=Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE employment_id=%d AND id<>%d AND approval_status='APPROVED' AND effective_from<=COALESCE(%s,'9999-12-31') AND COALESCE(effective_to,'9999-12-31')>=%s",$table,(int)$row['employment_id'],$id,$row['effective_to'],$row['effective_from']));
						if((int)$overlap>0)throw new RuntimeException('Approved compensation effective dates overlap. Close the prior assignment first.');
						$to='APPROVED';$updates=array('approval_status'=>$to,'approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'));
						Stockland_DB::update_record($this->table('hris_compensation_items'),array('status'=>'APPROVED'),array('assignment_id'=>$id,'status'=>'DRAFT'));
					}else throw new RuntimeException('Invalid compensation assignment transition.');
					break;
				case 'compensation-items':
					throw new RuntimeException('Compensation items inherit independent approval from their parent assignment; create a new assignment instead of editing approved items.');
				case 'legal-rules':
					if($action==='SUBMIT'&&$from==='DRAFT'){$to='LEGAL_REVIEW';$updates=array('review_status'=>$to,'submitted_by'=>get_current_user_id(),'submitted_at'=>current_time('mysql'));}
					elseif($action==='APPROVE'&&$from==='LEGAL_REVIEW'){
						if((int)$row['created_by']===get_current_user_id())throw new RuntimeException('Rule maker cannot approve their own legal rule.');
						if(strlen($note)<30)throw new RuntimeException('An independent Ethiopian legal/payroll review note (30+ characters) and golden test evidence are required.');
						$this->validate_rule($row);
						$overlap=Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE rule_type=%s AND review_status='APPROVED' AND effective_from<=COALESCE(%s,'9999-12-31') AND COALESCE(effective_to,'9999-12-31')>=%s",$table,$row['rule_type'],$row['effective_to'],$row['effective_from']));
						if((int)$overlap>0)throw new RuntimeException('An approved rule of this type overlaps the requested effective interval.');
						$to='APPROVED';$updates=array('review_status'=>$to,'reviewer_note'=>$note,'approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'));
					}else throw new RuntimeException('Invalid legal-rule transition.');
					break;
				case 'payee-engagements':
					if($action==='SUBMIT'&&$from==='DRAFT'){$to='SUBMITTED';$updates=array('status'=>$to);}
					elseif($action==='APPROVE'&&$from==='SUBMITTED'&&strlen($note)>=20){$to='APPROVED';$updates=array('status'=>$to,'approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'));}
					else throw new RuntimeException('Invalid payee engagement transition.');
					break;
				case 'payee-requests':
					if($action==='SUBMIT'&&$from==='DRAFT'){$to='SUBMITTED';$updates=array('status'=>$to,'submitted_by'=>get_current_user_id());}
					elseif($action==='APPROVE'&&$from==='SUBMITTED'&&$row['classification_status']==='APPROVED'&&strlen($note)>=20){$to='APPROVED';$updates=array('status'=>$to,'approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'));}
					else throw new RuntimeException('Payee request needs accepted deliverable, approved classification/withholding decision and independent approval.');
					break;
				case 'training-records':
					if($action==='COMPLETE'&&in_array($from,array('PLANNED','IN_PROGRESS'),true)&&!empty($row['evidence_reference'])){$to='COMPLETE';$updates=array('status'=>$to,'completed_on'=>current_time('Y-m-d'));}
					else throw new RuntimeException('Training completion requires evidence.');
					break;
				case 'performance-reviews':
					if($action==='SUBMIT'&&$from==='DRAFT'){$to='SUBMITTED';$updates=array('status'=>$to);}
					elseif($action==='APPROVE'&&$from==='SUBMITTED'&&strlen($note)>=10){$to='APPROVED';$updates=array('status'=>$to,'approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'));}
					else throw new RuntimeException('Invalid performance review transition.');
					break;
				case 'exits':
					if($action==='SUBMIT'&&$from==='DRAFT'){$to='SUBMITTED';$updates=array('status'=>$to);}
					elseif($action==='REOPEN'&&$from==='CALCULATED'&&Stockland_Authority::is_owner()&&strlen($note)>=20){$to='DRAFT';$updates=array('status'=>$to);}
					else throw new RuntimeException('Exit settlements require their dedicated calculate → approve → pay path.');
					break;
			}
			$updated=Stockland_DB::update_record($table,$updates,array('id'=>$id));
			if(false===$updated)throw new RuntimeException('The state transition could not be saved.');
			if(!Stockland_HRIS::log_event($entity,$id,'STATE_TRANSITION',$from,$to,array('decision'=>$action,'note_hash'=>hash('sha256',$note)),in_array($action,$approval_actions,true)?get_current_user_id():null))throw new RuntimeException('Immutable audit-chain event failed.');
			if(Stockland_DB::execute_query('COMMIT')===false)throw new RuntimeException('Transaction commit failed.');
			return rest_ensure_response(array('id'=>$id,'entity'=>$entity,'from'=>$from,'status'=>$to,'message'=>'Workflow transition accepted and audited.'));
		}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->err($e->getMessage(),'hris_transition_failed',409);}
	}
	private function validate_rule($r) {
		global $wpdb;$type=strtoupper($r['rule_type']);$params=json_decode((string)$r['rules_json'],true);
		if(!is_array($params))throw new RuntimeException('Rule parameters are invalid JSON.');
		if(!preg_match('/^[a-f0-9]{64}$/i',(string)$r['source_sha256'])||!preg_match('~^https://~i',(string)$r['source_url'])||empty($r['source_clause']))throw new RuntimeException('Official source, clause and SHA-256 evidence are mandatory.');
		if($type==='PAYE'||$type==='PAYROLL'){
			if(!in_array($params['tax_base_method']??'',array('TAXABLE_GROSS','TAXABLE_GROSS_LESS_EMPLOYEE_PENSION'),true))throw new RuntimeException('Approved PAYE rule must explicitly specify its tax base method.');
			$cs=Stockland_HRIS::rule_components((int)$r['id']);$bands=array_values(array_filter($cs,function($c){return strtoupper($c['category'])==='PAYE_BAND';}));
			if(!$bands||$bands[0]['lower_bound']===null||Stockland_Money::minor($bands[0]['lower_bound'])!==0)throw new RuntimeException('PAYE component schedule must start at zero.');
			$last=null;$open=false;
			foreach($bands as $b){if(!is_numeric($b['rate'])||(float)$b['rate']<0||(float)$b['rate']>1||$b['lower_bound']===null)throw new RuntimeException('Invalid PAYE band lower bound/rate.');
				$low=Stockland_Money::minor($b['lower_bound']);$up=$b['upper_bound']===null?null:Stockland_Money::minor($b['upper_bound']);
				if($last!==null&&$low!==$last)throw new RuntimeException('PAYE bands must be contiguous, non-overlapping and ascending.');
				if($up!==null&&$up<=$low)throw new RuntimeException('PAYE upper bounds must exceed lower bounds.');
				if($open)throw new RuntimeException('No PAYE band can follow an open-ended band.');$last=$up;$open=$up===null;
			}
			if(!$open)throw new RuntimeException('PAYE schedule must end with an open-ended band.');
		}
		if($type==='PENSION'){
			if(!isset($params['employee_rate'],$params['employer_rate'],$params['pensionable_base_method'])||!is_numeric($params['employee_rate'])||!is_numeric($params['employer_rate'])||(float)$params['employee_rate']<0||(float)$params['employee_rate']>1||(float)$params['employer_rate']<0||(float)$params['employer_rate']>1)throw new RuntimeException('Pension rule must contain qualified-review-approved employee/employer rates and pensionable base method.');
			if(!in_array($params['pensionable_base_method'],array('BASE_ONLY','TAXABLE_EARNINGS','GROSS_EARNINGS'),true))throw new RuntimeException('Unsupported pensionable base method.');
		}
		if($type==='ALLOWANCE'){
			$cs=Stockland_HRIS::rule_components((int)$r['id']);if(!$cs)throw new RuntimeException('Allowance rule requires category components.');
			foreach($cs as $c){if(!in_array($c['tax_treatment'],array('TAXABLE','EXEMPT_CAP','EXEMPT_FULL','REVIEW_REQUIRED'),true))throw new RuntimeException('Invalid allowance tax treatment.');if($c['tax_treatment']==='EXEMPT_CAP'&&$c['exemption_cap']===null)throw new RuntimeException('Capped exemption lacks a legally approved cap.');if(!in_array($c['pensionable_treatment'],array('INCLUDED','EXCLUDED','REVIEW_REQUIRED'),true))throw new RuntimeException('Invalid pensionable treatment.');}
		}
		if(in_array($type,array('FINAL_SETTLEMENT','PAYEE_WITHHOLDING'),true)&&strlen((string)$r['reviewer_note'])<30) { /* note is supplied in decision and audited separately */ }
	}
	private function do_create_pending_hire(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;
		$id=(int)$r['id'];global $wpdb;$p=$r->get_json_params();$note=sanitize_textarea_field((string)($p['selection_rationale']??''));
		if(strlen($note)<20)return $this->err('Selection rationale must be documented (20+ characters).');
		$app=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hris_applications'),$id),ARRAY_A);
		if(!$app||$app['decision']!=='SELECTED')return $this->err('Only a human-selected application may become a pending hire.','hris_hire_precondition',409);
		if(!Stockland_Authority::is_owner())return $this->err('Owner approval is required to create a pending hire.','hris_forbidden',403);
		$req=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND status='APPROVED'",$this->table('hris_requisitions'),(int)$app['requisition_id']),ARRAY_A);
		if(!$req)return $this->err('The source requisition is not approved.');
		$exist=Stockland_DB::read_row($wpdb->prepare('SELECT id,employee_code,status FROM %i WHERE person_id=%d AND start_date=%s ORDER BY id DESC LIMIT 1',$this->table('hris_employments'),(int)$app['person_id'],$req['target_start_date']?:current_time('Y-m-d')),ARRAY_A);
		if($exist)return rest_ensure_response(array('id'=>(int)$exist['id'],'employee_code'=>$exist['employee_code'],'status'=>$exist['status'],'idempotent'=>true));
		$position=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',$this->table('hris_positions'),(int)$req['position_id']),ARRAY_A);
		if(!$position)return $this->err('The approved requisition position is missing.');
		$employee_code=sanitize_text_field((string)($p['employee_code']??''));if($employee_code===''||!preg_match('/^[A-Za-z0-9._-]{2,48}$/',$employee_code))return $this->err('Enter the approved unique employee code.');
		$hire_date=$this->date($p['start_date']??$req['target_start_date'],false);if($hire_date===false)return $this->err('A valid employment start date is required.');
		$values=array('person_id'=>(int)$app['person_id'],'employee_code'=>$employee_code,'legal_employer'=>sanitize_text_field((string)($p['legal_employer']??'Stockland ET PLC')),'employment_type'=>$req['employment_type'],'position_id'=>(int)$position['id'],'department'=>$position['department'],'location_id'=>$req['location_id'],'start_date'=>$hire_date,'probation_end'=>$this->date($p['probation_end']??null,true),'status'=>'PENDING_ACTIVATION','created_by'=>get_current_user_id());
		if(Stockland_DB::insert_record($this->table('hris_employments'),$values)===false)return $this->err('Pending employment could not be created; check employee code uniqueness.','hris_hire_insert_failed',409);
		$empid=(int)$wpdb->insert_id;
		foreach(array(array('CONTRACT_SIGN','Record signed employment contract','MANAGEMENT'),array('PERSONNEL_DOCS','Verify required personnel documents','MANAGEMENT'),array('PAYROLL_SETUP','Enter and approve compensation/rule basis','OWNER'),array('INDUCTION','Complete induction and safety checklist','MANAGEMENT'),array('ACCESS_REVIEW','Approve least-privilege access scope','OWNER')) as $task){
			Stockland_DB::insert_record($this->table('hris_onboarding_tasks'),array('employment_id'=>$empid,'task_code'=>$task[0],'task_title'=>$task[1],'responsible_role'=>$task[2],'status'=>'OPEN','created_by'=>get_current_user_id()));
		}
		Stockland_HRIS::log_event('employment',$empid,'PENDING_HIRE_CREATED',null,'PENDING_ACTIVATION',array('application_id'=>$id,'employee_code_hash'=>hash('sha256',$employee_code)));
		Stockland_DB::update_record($this->table('hris_applications'),array('stage'=>'OFFER','selection_rationale'=>$note,'selected_employment_id'=>$empid),array('id'=>$id));
		return rest_ensure_response(array('id'=>$empid,'employee_code'=>$employee_code,'status'=>'PENDING_ACTIVATION','message'=>'Pending hire created in new HRIS. Legacy employees table was not written.'));
	}
	public function create_pending_hire(WP_REST_Request $r){return $this->do_create_pending_hire($r);}
	public function activate_hire(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;
		$id=(int)$r['id'];global $wpdb;
		$emp=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hris_employments'),$id),ARRAY_A);
		if(!$emp||$emp['status']!=='PENDING_ACTIVATION')return $this->err('Only a pending HRIS employment may be activated.','hris_hire_precondition',409);
		$contract=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE employment_id=%d AND record_type='CONTRACT' AND status='APPROVED' AND start_date<=%s AND (end_date IS NULL OR end_date>=%s) ORDER BY version_no DESC LIMIT 1",$this->table('hris_contracts'),$id,$emp['start_date'],$emp['start_date']),ARRAY_A);
		if(!$contract||empty($contract['signed_at'])||(int)$contract['document_id']<1)return $this->err('A signed, Owner-approved and document-linked contract is required before activation.','hris_contract_missing',409);
		$doc=Stockland_DB::read_row($wpdb->prepare("SELECT id FROM %i WHERE id=%d AND status='VERIFIED' AND document_type='EMPLOYMENT_CONTRACT'",$this->table('hris_documents'),(int)$contract['document_id']),ARRAY_A);
		if(!$doc)return $this->err('Verified private contract document is required before activation.','hris_private_document_missing',409);
		$open=Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE employment_id=%d AND status<>'COMPLETE'",$this->table('hris_onboarding_tasks'),$id));
		if((int)$open>0)return $this->err('All required onboarding tasks must be completed before activation.','hris_onboarding_incomplete',409);
		$comp=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE employment_id=%d AND approval_status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC LIMIT 1",$this->table('hris_compensation_assignments'),$id,$emp['start_date'],$emp['start_date']),ARRAY_A);
		if(!$comp)return $this->err('Approved effective-dated compensation is required before activation. Do not invent or autofill salary.','hris_compensation_missing',409);
		if(!Stockland_HRIS::log_event('employment',$id,'HIRE_ACTIVATED','PENDING_ACTIVATION','ACTIVE',array('contract_id'=>(int)$contract['id'],'compensation_id'=>(int)$comp['id'])))return $this->err('Audit chain failed; activation was blocked.','hris_audit_failed',500);
		Stockland_DB::update_record($this->table('hris_employments'),array('status'=>'ACTIVE'),array('id'=>$id,'status'=>'PENDING_ACTIVATION'));
		return rest_ensure_response(array('id'=>$id,'status'=>'ACTIVE','message'=>'Hire activated after contract, private document, onboarding and approved compensation checks.'));
	}
	private function do_create_payroll_run(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;
		$p=$r->get_json_params();$month=sanitize_text_field((string)($p['period_key']??''));if(!preg_match('/^\d{4}-\d{2}$/',$month))return $this->err('Payroll period key must be YYYY-MM.');
		$start=$month.'-01';$date=DateTimeImmutable::createFromFormat('!Y-m-d',$start);if(!$date||$date->format('Y-m')!==$month)return $this->err('Invalid payroll month.');
		$end=$date->modify('last day of this month')->format('Y-m-d');
		global $wpdb;$existing=Stockland_DB::read_row($wpdb->prepare('SELECT id,status,version_no FROM %i WHERE period_key=%s ORDER BY version_no DESC LIMIT 1',$this->table('hris_payroll_runs'),$month),ARRAY_A);
		if($existing&& !in_array($existing['status'],array('CLOSED','VOIDED'),true))return $this->err('A non-closed payroll run already exists for '.$month.'. Create a linked correction run only after the original is closed.','hris_payroll_duplicate_period',409);
		$version=$existing?(int)$existing['version_no']+1:1;
		if(Stockland_DB::insert_record($this->table('hris_payroll_runs'),array('period_key'=>$month,'start_date'=>$start,'end_date'=>$end,'version_no'=>$version,'status'=>'DRAFT','created_by'=>get_current_user_id()))===false)return $this->err('Could not create a draft payroll run.','hris_payroll_create_failed',409);
		$id=(int)$wpdb->insert_id;Stockland_HRIS::log_event('payroll-run',$id,'RUN_CREATED',null,'DRAFT',array('period_key'=>$month,'version'=>$version));
		return rest_ensure_response(array('id'=>$id,'period_key'=>$month,'version_no'=>$version,'status'=>'DRAFT'));
	}
	public function create_payroll_run(WP_REST_Request $r){return $this->do_create_payroll_run($r);}

	public function calculate_payroll_run(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;
		$id=(int)$r['id'];global $wpdb;
		if(Stockland_DB::execute_query('START TRANSACTION')===false)return $this->err('Could not start payroll calculation transaction.','hris_transaction_failed',500);
		try {
			$run=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hris_payroll_runs'),$id),ARRAY_A);
			if(!$run||!in_array($run['status'],array('DRAFT','CALCULATION_FAILED'),true))throw new RuntimeException('Only a new draft run can be calculated; calculated/posted runs are frozen.');
			$payeRule=Stockland_HRIS::approved_rule('PAYE',$run['end_date']);if(is_wp_error($payeRule))throw new RuntimeException($payeRule->get_error_message());
			$pension=Stockland_HRIS::approved_rule('PENSION',$run['end_date']);if(is_wp_error($pension))throw new RuntimeException($pension->get_error_message());
			$payeParams=json_decode($payeRule['rules_json'],true);$pensionParams=json_decode($pension['rules_json'],true);
			if(!is_array($payeParams)||!is_array($pensionParams))throw new RuntimeException('Approved PAYE/Pension rule payload is invalid.');
			if(!in_array($payeParams['tax_base_method']??'',array('TAXABLE_GROSS','TAXABLE_GROSS_LESS_EMPLOYEE_PENSION'),true))throw new RuntimeException('PAYE rule lacks an explicitly approved tax base method.');
			if(!in_array($pensionParams['pensionable_base_method']??'',array('BASE_ONLY','TAXABLE_EARNINGS','GROSS_EARNINGS'),true))throw new RuntimeException('Pension rule lacks an explicitly approved pensionable base method.');
			$bands=array_values(array_filter(Stockland_HRIS::rule_components((int)$payeRule['id']),function($c){return strtoupper($c['category'])==='PAYE_BAND';}));
			if(!$bands)throw new RuntimeException('PAYE rule has no explicit progressive tax bands.');
			$allowRule=Stockland_HRIS::approved_rule('ALLOWANCE',$run['end_date']);
			$allowComponents=is_wp_error($allowRule)?array():Stockland_HRIS::rule_components((int)$allowRule['id']);
			$allowMap=array();foreach($allowComponents as $c)$allowMap[strtoupper($c['category'])]=$c;
			$employees=Stockland_DB::read_results($wpdb->prepare("SELECT e.*,p.legal_name FROM %i e JOIN %i p ON p.id=e.person_id WHERE e.status='ACTIVE' AND e.start_date<=%s AND (e.end_date IS NULL OR e.end_date>=%s) ORDER BY e.id",$this->table('hris_employments'),$this->table('hris_people'),$run['end_date'],$run['start_date']),ARRAY_A);
			if(!is_array($employees)||!$employees)throw new RuntimeException('No active HRIS employments intersect this payroll period.');
			$createdLines=array();$grossTotal='0.00';$payeTotal='0.00';$empPenTotal='0.00';$erPenTotal='0.00';$dedTotal='0.00';$netTotal='0.00';$employerCost='0.00';$snap=array();
			foreach($employees as $emp) {
				if(!$this->own_or_company_scope($emp))continue;
				$compRows=Stockland_DB::read_results($wpdb->prepare("SELECT * FROM %i WHERE employment_id=%d AND approval_status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from,id",$this->table('hris_compensation_assignments'),(int)$emp['id'],$run['end_date'],$run['start_date']),ARRAY_A);
				if(!$compRows)throw new RuntimeException('Approved compensation is missing for '.$emp['employee_code'].'. No salary was created or defaulted.');
				$days=(int)substr($run['end_date'],8,2);$byDate=array();$basePay='0.00';
				$dt=new DateTimeImmutable($run['start_date']);$end=new DateTimeImmutable($run['end_date']);
				for($cur=$dt;$cur<=$end;$cur=$cur->modify('+1 day')){
					$day=$cur->format('Y-m-d');if($emp['start_date']>$day||($emp['end_date']!==null&&$emp['end_date']<$day))continue;
					$match=array_values(array_filter($compRows,function($c)use($day){return $c['effective_from']<=$day&&($c['effective_to']===null||$c['effective_to']>=$day);}));
					if(count($match)!==1)throw new RuntimeException('Compensation effective-date gap/overlap for '.$emp['employee_code'].' on '.$day.'.');
					$byDate[$match[0]['id']]=$match[0];
				}
				if(!$byDate)continue;
				$calendarDays=(int)$dt->diff($end)->days+1;
				foreach($byDate as $c){
					$count=0;$from=max($c['effective_from'],$run['start_date']);$to=min($c['effective_to']?:$run['end_date'],$run['end_date']);
					for($d=new DateTimeImmutable($from),$until=new DateTimeImmutable($to);$d<=$until;$d=$d->modify('+1 day'))if($d->format('Y-m-d')>=$emp['start_date']&&($emp['end_date']===null||$d->format('Y-m-d')<=$emp['end_date']))$count++;
					if($count>0)$basePay=Stockland_Money::add($basePay,Stockland_Money::proportion_of_quantity($c['base_salary'],$count,$calendarDays));
				}
				$earnings=$basePay;$taxable=$basePay;$exempt='0.00';$pensionable=$basePay;$deductions='0.00';$lineItems=array();
				$lineItems[]=array('component_code'=>'BASE_SALARY','component_label'=>'Base salary','component_type'=>'EARNING','amount'=>$basePay,'taxable_amount'=>$basePay,'exempt_amount'=>'0.00','pensionable_amount'=>$basePay,'rule_id'=>(int)$pension['id'],'rule_version'=>$pension['version_label'],'source_reference'=>null,'calculation_json'=>wp_json_encode(array('monthly_proration'=>'calendar_days','period_days'=>$calendarDays)));
				$allItems=array();
				foreach($byDate as $c){
					$items=Stockland_DB::read_results($wpdb->prepare("SELECT * FROM %i WHERE assignment_id=%d AND status='APPROVED' ORDER BY id",$this->table('hris_compensation_items'),(int)$c['id']),ARRAY_A);
					foreach((array)$items as $item){
						$freq=strtoupper($item['frequency']);$amount=(string)$item['amount'];
						if($freq==='MONTHLY')$amount=Stockland_Money::proportion_of_quantity($amount,1,$calendarDays);
						elseif($freq!=='ONE_OFF')throw new RuntimeException('Unsupported compensation frequency '.$freq.' in '.$emp['employee_code'].'.');
						$category=strtoupper($item['category']);if(!isset($allowMap[$category]))throw new RuntimeException('No approved allowance classification for category '.$category.'.');
						$rule=$allowMap[$category];$type=strtoupper($item['component_type']);
						if(!in_array($type,array('EARNING','ALLOWANCE','DEDUCTION','REIMBURSEMENT','BENEFIT'),true))throw new RuntimeException('Unknown compensation item type '.$type.'.');
						$taxAmount='0.00';$exemptAmount='0.00';
						switch(strtoupper($rule['tax_treatment']??'REVIEW_REQUIRED')){
							case 'TAXABLE':$taxAmount=$amount;break;
							case 'EXEMPT_FULL':$exemptAmount=$amount;break;
							case 'EXEMPT_CAP':$cap=Stockland_Money::minor($rule['exemption_cap']);$exemptAmount=Stockland_Money::decimal(min(Stockland_Money::minor($amount),$cap));$taxAmount=Stockland_Money::subtract($amount,$exemptAmount);break;
							default:throw new RuntimeException('Legal review is still required for allowance category '.$category.'.');
						}
						$pensRule=strtoupper($rule['pensionable_treatment']??'REVIEW_REQUIRED');
						if($pensRule==='REVIEW_REQUIRED')throw new RuntimeException('Pension classification review is required for '.$category.'.');
						$pensAmount=$pensRule==='INCLUDED'?$amount:'0.00';
						if($type==='DEDUCTION'){$deductions=Stockland_Money::add($deductions,$amount);}
						else{$earnings=Stockland_Money::add($earnings,$amount);$taxable=Stockland_Money::add($taxable,$taxAmount);$exempt=Stockland_Money::add($exempt,$exemptAmount);$pensionable=Stockland_Money::add($pensionable,$pensAmount);}
						$lineItems[]=array('component_code'=>$item['component_code'],'component_label'=>$item['label'],'component_type'=>$type,'amount'=>$amount,'taxable_amount'=>$taxAmount,'exempt_amount'=>$exemptAmount,'pensionable_amount'=>$pensAmount,'rule_id'=>(int)$allowRule['id'],'rule_version'=>$allowRule['version_label'],'source_reference'=>$item['source_reference'],'calculation_json'=>wp_json_encode(array('source_component_id'=>(int)$item['id'],'category'=>$category,'frequency'=>$freq)));
					}
				}
				$gross=Stockland_Money::add($basePay,$earnings===$basePay?'0.00':Stockland_Money::subtract($earnings,$basePay));
				// An explicit approved PAYE tax-base method controls treatment; the engine does not infer the rule.
				$taxBase=$payeParams['tax_base_method']==='TAXABLE_GROSS_LESS_EMPLOYEE_PENSION'?Stockland_Money::subtract($taxable,Stockland_Money::percentage($pensionable,(string)$pensionParams['employee_rate'])):$taxable;
				$empPen=Stockland_Money::percentage($pensionable,(string)$pensionParams['employee_rate']);$erPen=Stockland_Money::percentage($pensionable,(string)$pensionParams['employer_rate']);
				$payeAmount=Stockland_HRIS::progressive_tax($taxBase,$bands);if(is_wp_error($payeAmount))throw new RuntimeException($payeAmount->get_error_message());
				$net=Stockland_Money::subtract(Stockland_Money::subtract(Stockland_Money::subtract($gross,$payeAmount),$empPen),$deductions);
				if(Stockland_Money::minor($net)<0)throw new RuntimeException('Calculated net pay is negative for '.$emp['employee_code'].'.');
				$cost=Stockland_Money::add($gross,$erPen);
				$lineId=null;
				if(Stockland_DB::insert_record($this->table('hris_payroll_lines'),array('run_id'=>$id,'employment_id'=>(int)$emp['id'],'person_id'=>(int)$emp['person_id'],'employee_code'=>$emp['employee_code'],'employee_name_snapshot'=>$emp['legal_name'],'location_id'=>$emp['location_id'],'base_pay'=>$basePay,'earnings_total'=>Stockland_Money::subtract($earnings,$basePay),'gross_pay'=>$gross,'taxable_base'=>$taxBase,'paye'=>$paye,'pensionable_base'=>$pensionable,'employee_pension'=>$empPen,'employer_pension'=>$erPen,'other_deductions'=>$deductions,'net_pay'=>$net,'employer_cost'=>$cost,'status'=>'CALCULATED'))===false)throw new RuntimeException('Could not save payroll line for '.$emp['employee_code'].'.');
				$lineId=(int)$wpdb->insert_id;$calculation=array('run_id'=>$id,'employment_id'=>(int)$emp['id'],'base_pay'=>$basePay,'gross_pay'=>$gross,'taxable_base'=>$taxBase,'paye'=>$payeAmount,'employee_pension'=>$empPen,'employer_pension'=>$erPen,'other_deductions'=>$deductions,'net_pay'=>$net,'employer_cost'=>$cost,'paye_rule'=>$paye['version_label']??null);
				foreach($lineItems as $li){$li['run_id']=$id;$li['line_id']=$lineId; if(Stockland_DB::insert_record($this->table('hris_payroll_line_items'),$li)===false)throw new RuntimeException('Could not save itemized payroll calculation.');}
				foreach(array(array('PAYE','TAX',$payeAmount,(int)$payeRule['id'],$payeRule['version_label']),array('EMPLOYEE_PENSION','PENSION',$empPen,(int)$pension['id'],$pension['version_label']),array('EMPLOYER_PENSION','PENSION',$erPen,(int)$pension['id'],$pension['version_label'])) as $x){
					$snap[]=array('employment_id'=>(int)$emp['id'],'component'=>$x[0],'amount'=>$x[2],'rule_id'=>$x[3],'rule_version'=>$x[4]);
				}
				$grossTotal=Stockland_Money::add($grossTotal,$gross);$payeTotal=Stockland_Money::add($payeTotal,$paye);$empPenTotal=Stockland_Money::add($empPenTotal,$empPen);$erPenTotal=Stockland_Money::add($erPenTotal,$erPen);$dedTotal=Stockland_Money::add($dedTotal,$deductions);$netTotal=Stockland_Money::add($netTotal,$net);$employerCost=Stockland_Money::add($employerCost,$cost);
				$createdLines[]=$lineId;
			}
			if(!$createdLines)throw new RuntimeException('No payroll lines fell within your authorized scope.');
			$recon=Stockland_Money::add(Stockland_Money::add($payeTotal,$empPenTotal),$dedTotal);
			if(Stockland_Money::minor(Stockland_Money::subtract($grossTotal,Stockland_Money::add($netTotal,$recon)))!==0)throw new RuntimeException('Gross-to-net payroll reconciliation failed.');
			$inputHash=hash('sha256',wp_json_encode(array('period'=>$run['period_key'],'rules'=>array($paye['id'],$paye['version_label'],$pension['id'],$pension['version_label']),'lines'=>$createdLines,'totals'=>array($grossTotal,$payeTotal,$empPenTotal,$erPenTotal,$dedTotal,$netTotal),'snapshot'=>$snap)));
			$updated=Stockland_DB::update_record($this->table('hris_payroll_runs'),array('status'=>'CALCULATED','input_sha256'=>$inputHash,'paye_rule_id'=>(int)$payeRule['id'],'paye_rule_version'=>$payeRule['version_label'],'pension_rule_id'=>(int)$pension['id'],'pension_rule_version'=>$pension['version_label'],'employee_count'=>count($createdLines),'gross_total'=>$grossTotal,'paye_total'=>$payeTotal,'employee_pension_total'=>$empPenTotal,'employer_pension_total'=>$erPenTotal,'other_deduction_total'=>$dedTotal,'net_total'=>$netTotal,'employer_cost_total'=>$employerCost),array('id'=>$id,'status'=>$run['status']));
			if(false===$updated)throw new RuntimeException('Payroll run could not be frozen.');
			if(!Stockland_HRIS::log_event('payroll-run',$id,'RUN_CALCULATED',$run['status'],'CALCULATED',array('input_sha256'=>$inputHash,'line_count'=>count($createdLines))))throw new RuntimeException('Audit-chain event failed.');
			if(Stockland_DB::execute_query('COMMIT')===false)throw new RuntimeException('Payroll calculation transaction commit failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>'CALCULATED','employee_count'=>count($createdLines),'gross_total'=>$grossTotal,'paye_total'=>$payeTotal,'employee_pension_total'=>$empPenTotal,'employer_pension_total'=>$erPenTotal,'net_total'=>$netTotal,'input_sha256'=>$inputHash));
		}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->err($e->getMessage(),'hris_calculation_failed',409);}
	}
	public function submit_payroll_run(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;
		$id=(int)$r['id'];$p=$r->get_json_params();$note=sanitize_textarea_field((string)($p['review_note']??''));if(strlen($note)<20)return $this->err('Management review note is required (20+ characters).');global $wpdb;
		$run=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND status='CALCULATED'",$this->table('hris_payroll_runs'),$id),ARRAY_A);if(!$run)return $this->err('Only a calculated run can be submitted.','hris_payroll_state',409);
		if((int)$run['created_by']===get_current_user_id()){} // Preparer may submit; independent Owner must approve.
		if(!Stockland_HRIS::log_event('payroll-run',$id,'RUN_SUBMITTED','CALCULATED','SUBMITTED',array('note_hash'=>hash('sha256',$note))))return $this->err('Audit-chain event failed.');
		Stockland_DB::update_record($this->table('hris_payroll_runs'),array('status'=>'SUBMITTED','submitted_by'=>get_current_user_id(),'submitted_at'=>current_time('mysql')),array('id'=>$id,'status'=>'CALCULATED'));
		return rest_ensure_response(array('id'=>$id,'status'=>'SUBMITTED'));
	}
	public function approve_payroll_run(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;
		$id=(int)$r['id'];$p=$r->get_json_params();$note=sanitize_textarea_field((string)($p['approval_note']??''));if(strlen($note)<30)return $this->err('Independent Owner review note (30+ characters) is required.');
		global $wpdb;if(Stockland_DB::execute_query('START TRANSACTION')===false)return $this->err('Could not start payroll approval transaction.','hris_transaction_failed',500);
		try{
			$run=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hris_payroll_runs'),$id),ARRAY_A);
			if(!$run||$run['status']!=='SUBMITTED')throw new RuntimeException('Only a submitted calculated run can be approved.');
			if((int)$run['created_by']===get_current_user_id())throw new RuntimeException('Payroll maker cannot approve their own run.');
			$paye=Stockland_HRIS::approved_rule('PAYE',$run['end_date']);$pens=Stockland_HRIS::approved_rule('PENSION',$run['end_date']);if(is_wp_error($paye)||is_wp_error($pens))throw new RuntimeException('Approved statutory rules are not available.');
			if((int)$paye['id']!==(int)$run['paye_rule_id']||(string)$paye['version_label']!==$run['paye_rule_version']||(int)$pens['id']!==(int)$run['pension_rule_id']||(string)$pens['version_label']!==$run['pension_rule_version'])throw new RuntimeException('Rule version changed after calculation. Recalculate in a new run; this frozen run cannot be edited.');
			$lines=Stockland_DB::read_results($wpdb->prepare('SELECT * FROM %i WHERE run_id=%d ORDER BY id',$this->table('hris_payroll_lines'),$id),ARRAY_A);
			if(!$lines)throw new RuntimeException('Payroll run has no lines.');
			$gross='0.00';$payeT='0.00';$empPenT='0.00';$erPenT='0.00';$ded='0.00';$net='0.00';
			foreach($lines as $l){if($l['status']!=='CALCULATED')throw new RuntimeException('A payroll line is not in CALCULATED state.');
				$gross=Stockland_Money::add($gross,$l['gross_pay']);$payeT=Stockland_Money::add($payeT,$l['paye']);$empPenT=Stockland_Money::add($empPenT,$l['employee_pension']);$erPenT=Stockland_Money::add($erPenT,$l['employer_pension']);$ded=Stockland_Money::add($ded,$l['other_deductions']);$net=Stockland_Money::add($net,$l['net_pay']);}
			foreach(array(array($gross,$run['gross_total']),(array($payeT,$run['paye_total'])),array($empPenT,$run['employee_pension_total']),array($erPenT,$run['employer_pension_total']),array($ded,$run['other_deduction_total']),array($net,$run['net_total'])) as $pair)if(Stockland_Money::minor(Stockland_Money::subtract($pair[0],$pair[1]))!==0)throw new RuntimeException('Payroll totals changed after the frozen run; approval blocked.');
			$debits=Stockland_Money::add($gross,$erPenT);$credits=Stockland_Money::add(Stockland_Money::add(Stockland_Money::add($net,$payeT),$empPenT),Stockland_Money::add($ded,$erPenT));
			if(Stockland_Money::minor(Stockland_Money::subtract($debits,$credits))!==0)throw new RuntimeException('Debit/credit payroll equation does not balance to the cent.');
			if(!Stockland_Accounting::ensure_payroll_statutory_accounts())throw new RuntimeException('Required payroll/statutory GL accounts are missing or inactive.');
			$jl=array();$push=function($code,$debit,$credit)use(&$jl){if(Stockland_Money::minor($debit)>0||Stockland_Money::minor($credit)>0)$jl[]=array('account_code'=>$code,'debit'=>$debit,'credit'=>$credit,'location_id'=>null,'memo'=>'HRIS payroll');};
			$push('6200',$gross,'0.00');$push('6210',$erPenT,'0.00');$push('2020','0.00',$net);$push('2031','0.00',$payeT);$push('2030','0.00',$empPenT);$push('2032','0.00',$erPenT);$push('2033','0.00',$ded);
			$jid=Stockland_Accounting::post_journal($run['end_date'],'HRIS_PAYROLL',(string)$id,'HRIS Payroll '.$run['period_key'].'/v'.$run['version_no'].' '.$run['paye_rule_version'],$jl,get_current_user_id());
			if(!$jid)throw new RuntimeException('Accounting service refused the payroll journal; check chart of accounts and idempotency.');
			if(false===Stockland_DB::update_record($this->table('hris_payroll_runs'),array('status'=>'POSTED','approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'),'journal_id'=>(int)$jid),array('id'=>$id,'status'=>'SUBMITTED')))throw new RuntimeException('Payroll posting state could not be recorded.');
			$obl=array(array('PAYE',$payeT,(int)$paye['id'],$paye['version_label']),array('EMPLOYEE_PENSION',$empPenT,(int)$pens['id'],$pens['version_label']),array('EMPLOYER_PENSION',$erPenT,(int)$pens['id'],$pens['version_label']));
			foreach($obl as $o){$code='HRISO-'.$id.'-'.$o[0];if(Stockland_DB::read_var($wpdb->prepare('SELECT id FROM %i WHERE obligation_code=%s',$this->table('hris_statutory_obligations'),$code)))continue;
				if(Stockland_DB::insert_record($this->table('hris_statutory_obligations'),array('obligation_code'=>$code,'obligation_type'=>$o[0],'period_key'=>$run['period_key'],'run_id'=>$id,'rule_id'=>$o[2],'rule_version'=>$o[3],'amount'=>$o[1],'status'=>'OPEN','created_by'=>get_current_user_id()))===false)throw new RuntimeException('Could not create statutory obligation '.$o[0].'.');}
			if(!Stockland_HRIS::log_event('payroll-run',$id,'RUN_POSTED','SUBMITTED','POSTED',array('journal_id'=>(int)$jid,'gross_total'=>$gross,'net_total'=>$net,'approval_note_hash'=>hash('sha256',$note)),get_current_user_id()))throw new RuntimeException('Audit-chain event failed.');
			if(Stockland_DB::execute_query('COMMIT')===false)throw new RuntimeException('Payroll approval transaction could not commit.');
			return rest_ensure_response(array('id'=>$id,'status'=>'POSTED','journal_id'=>(int)$jid,'message'=>'Posted through Stockland_Accounting; employee payment settlement remains separate.'));
		}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->err($e->getMessage(),'hris_payroll_approval_failed',409);}
	}
	public function create_payment_batch(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;$id=(int)$r['id'];$p=$r->get_json_params();$method=strtoupper(sanitize_key((string)($p['payment_method']??'')));if(!in_array($method,array('BANK_TRANSFER','CASH','CHEQUE'),true))return $this->err('Select an approved payment method.');
		global $wpdb;$run=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND status='POSTED'",$this->table('hris_payroll_runs'),$id),ARRAY_A);if(!$run)return $this->err('A GL-posted payroll is required before payout batch preparation.','hris_payout_state',409);
		$existing=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE run_id=%d AND status NOT IN ('VOIDED') ORDER BY id DESC LIMIT 1",$this->table('hris_payment_batches'),$id),ARRAY_A);if($existing)return rest_ensure_response(array('id'=>(int)$existing['id'],'status'=>$existing['status'],'idempotent'=>true));
		$lines=Stockland_DB::read_results($wpdb->prepare('SELECT * FROM %i WHERE run_id=%d ORDER BY id',$this->table('hris_payroll_lines'),$id),ARRAY_A);if(!$lines)return $this->err('The posted run has no payout lines.');
		$code='PAYB-'.$id.'-'.wp_generate_password(6,false,false);$total='0.00';foreach($lines as $l)$total=Stockland_Money::add($total,$l['net_pay']);
		if(Stockland_DB::insert_record($this->table('hris_payment_batches'),array('batch_code'=>$code,'run_id'=>$id,'payment_method'=>$method,'batch_reference'=>sanitize_text_field((string)($p['batch_reference']??''))?:null,'instruction_count'=>count($lines),'amount_total'=>$total,'status'=>'PREPARED','created_by'=>get_current_user_id()))===false)return $this->err('Could not create payout batch.','hris_batch_failed',409);
		$batch=(int)$wpdb->insert_id;
		foreach($lines as $line){$key='hris-run-'.$id.'-line-'.$line['id'];if(Stockland_DB::insert_record($this->table('hris_payment_instructions'),array('batch_id'=>$batch,'run_id'=>$id,'line_id'=>(int)$line['id'],'employment_id'=>(int)$line['employment_id'],'amount'=>$line['net_pay'],'idempotency_key'=>$key,'status'=>'PREPARED'))===false)return $this->err('Payout instruction failed for line '.$line['id'].'.','hris_instruction_failed',409);}
		Stockland_HRIS::log_event('payment-batch',$batch,'BATCH_PREPARED',null,'PREPARED',array('run_id'=>$id,'instruction_count'=>count($lines),'amount_total'=>$total,'method'=>$method));
		return rest_ensure_response(array('id'=>$batch,'batch_code'=>$code,'status'=>'PREPARED','amount_total'=>$total,'instruction_count'=>count($lines),'message'=>'File/records prepared only; no money is considered paid until each recipient result is confirmed.'));
	}
	public function record_payment_result(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;$id=(int)$r['id'];$p=$r->get_json_params();$result=strtoupper(sanitize_key((string)($p['result']??'')));$ref=sanitize_text_field((string)($p['external_reference']??''));$evidence=sanitize_text_field((string)($p['evidence_reference']??''));$failure=sanitize_textarea_field((string)($p['failure_reason']??''));
		if(!in_array($result,array('PAID','FAILED','RETURNED'),true))return $this->err('Payment result must be PAID, FAILED or RETURNED.');
		if(in_array($result,array('PAID','RETURNED'),true)&&strlen($ref)<3)return $this->err('External bank/cash/cheque reference is required for PAID or RETURNED.');
		if($result==='FAILED'&&strlen($failure)<5)return $this->err('Failure reason is required.');
		global $wpdb;if(Stockland_DB::execute_query('START TRANSACTION')===false)return $this->err('Could not start payment result transaction.');
		try{
			$inst=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hris_payment_instructions'),$id),ARRAY_A);if(!$inst)throw new RuntimeException('Payment instruction not found.');
			if(in_array($inst['status'],array('PAID','CANCELLED'),true))throw new RuntimeException('A settled/cancelled payment instruction is immutable.');
			if(in_array($result,array('PAID','RETURNED'),true)){$dup=Stockland_DB::read_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE external_reference=%s AND id<>%d',$this->table('hris_payment_instructions'),$ref,$id));if((int)$dup>0)throw new RuntimeException('External reference is already linked to another instruction.');}
			$attempt=(int)Stockland_DB::read_var($wpdb->prepare('SELECT COALESCE(MAX(attempt_no),0)+1 FROM %i WHERE instruction_id=%d',$this->table('hris_payment_attempts'),$id));
			if(Stockland_DB::insert_record($this->table('hris_payment_attempts'),array('instruction_id'=>$id,'attempt_no'=>$attempt,'result'=>$result,'external_reference'=>$ref?:null,'evidence_reference'=>$evidence?:null,'failure_reason'=>$failure?:null,'recorded_by'=>get_current_user_id()))===false)throw new RuntimeException('Payment attempt could not be saved.');
			$to=$result==='PAID'?'PAID':($result==='FAILED'?'FAILED':'RETURNED');
			if(false===Stockland_DB::update_record($this->table('hris_payment_instructions'),array('status'=>$to,'external_reference'=>$ref?:null,'last_result_at'=>current_time('mysql'),'last_result_by'=>get_current_user_id()),array('id'=>$id)))throw new RuntimeException('Payment instruction state could not be updated.');
			$batch=(int)$inst['batch_id'];$counts=Stockland_DB::read_row($wpdb->prepare("SELECT COUNT(*) total,SUM(status='PAID') paid,SUM(status IN ('FAILED','RETURNED')) failed FROM %i WHERE batch_id=%d",$this->table('hris_payment_instructions'),$batch),ARRAY_A);
			$batchStatus=((int)$counts['paid']===(int)$counts['total'])?'SETTLED':(((int)$counts['paid']>0)?'PARTIALLY_SETTLED':(((int)$counts['failed']===(int)$counts['total'])?'FAILED':'SUBMITTED'));
			Stockland_DB::update_record($this->table('hris_payment_batches'),array('status'=>$batchStatus,'submitted_at'=>current_time('mysql')),array('id'=>$batch));
			if(!Stockland_HRIS::log_event('payment-instruction',$id,'PAYMENT_'.$to,$inst['status'],$to,array('attempt_no'=>$attempt,'external_reference_hash'=>$ref?hash('sha256',$ref):null,'evidence_reference_hash'=>$evidence?hash('sha256',$evidence):null)))throw new RuntimeException('Audit-chain event failed.');
			if(Stockland_DB::execute_query('COMMIT')===false)throw new RuntimeException('Payment state transaction failed to commit.');
			return rest_ensure_response(array('id'=>$id,'status'=>$to,'attempt_no'=>$attempt,'batch_status'=>$batchStatus));
		}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->err($e->getMessage(),'hris_payment_result_failed',409);}
	}
	public function create_remittance(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;$id=(int)$r['id'];$p=$r->get_json_params();$amount=$this->money($p['amount']??null,false);if($amount===false||Stockland_Money::minor($amount)<=0)return $this->err('Positive remittance amount required.');
		$code=sanitize_text_field((string)($p['remittance_code']??''));$code=$code?:'REM-'.wp_generate_password(10,false,false);
		$insert=array('obligation_id'=>$id,'remittance_code'=>$code,'amount'=>$amount,'submission_reference'=>sanitize_text_field((string)($p['submission_reference']??''))?:null,'receipt_reference'=>sanitize_text_field((string)($p['receipt_reference']??''))?:null,'payment_reference'=>sanitize_text_field((string)($p['payment_reference']??''))?:null,'paid_on'=>$this->date($p['paid_on']??null,true),'evidence_reference'=>sanitize_text_field((string)($p['evidence_reference']??''))?:null,'status'=>'DRAFT','created_by'=>get_current_user_id());
		if(!empty($p['evidence_reference'])&&preg_match('~^https?://~i',$insert['evidence_reference']))return $this->err('Remittance evidence must be a private repository reference, not a public URL.');
		global $wpdb;$o=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',$this->table('hris_statutory_obligations'),$id),ARRAY_A);if(!$o)return $this->err('Statutory obligation not found.');
		if(Stockland_DB::insert_record($this->table('hris_remittances'),$insert)===false)return $this->err('Remittance evidence record could not be saved.');
		$rid=(int)$wpdb->insert_id;Stockland_HRIS::log_event('remittance',$rid,'REMITTANCE_DRAFT',null,'DRAFT',array('obligation_id'=>$id,'amount'=>$amount));
		return rest_ensure_response(array('id'=>$rid,'status'=>'DRAFT'));
	}
	public function confirm_remittance(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;$id=(int)$r['id'];$p=$r->get_json_params();$note=sanitize_textarea_field((string)($p['review_note']??''));if(strlen($note)<20)return $this->err('Independent remittance review note is required.');
		global $wpdb;if(Stockland_DB::execute_query('START TRANSACTION')===false)return $this->err('Could not start remittance confirmation transaction.');
		try{$rem=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hris_remittances'),$id),ARRAY_A);if(!$rem||$rem['status']!=='DRAFT')throw new RuntimeException('Only a draft remittance may be confirmed.');if((int)$rem['created_by']===get_current_user_id())throw new RuntimeException('Remittance maker cannot approve their own evidence.');if(empty($rem['payment_reference'])||empty($rem['evidence_reference'])||empty($rem['paid_on']))throw new RuntimeException('Payment reference, private evidence reference and payment date are required.');
			Stockland_DB::update_record($this->table('hris_remittances'),array('status'=>'CONFIRMED','confirmed_by'=>get_current_user_id(),'confirmed_at'=>current_time('mysql')),array('id'=>$id,'status'=>'DRAFT'));
			$o=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hris_statutory_obligations'),(int)$rem['obligation_id']),ARRAY_A);$paid=Stockland_DB::read_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM %i WHERE obligation_id=%d AND status='CONFIRMED'",$this->table('hris_remittances'),(int)$o['id']));$status=Stockland_Money::minor($paid)>Stockland_Money::minor($o['amount'])?'OVER_REMITTED':(Stockland_Money::minor($paid)===Stockland_Money::minor($o['amount'])?'SETTLED':'PARTIALLY_REMITTED');
			Stockland_DB::update_record($this->table('hris_statutory_obligations'),array('status'=>$status),array('id'=>(int)$o['id']));
			if(!Stockland_HRIS::log_event('remittance',$id,'REMITTANCE_CONFIRMED','DRAFT','CONFIRMED',array('amount'=>$rem['amount'],'payment_reference_hash'=>hash('sha256',$rem['payment_reference'])),get_current_user_id()))throw new RuntimeException('Audit-chain event failed.');
			if(Stockland_DB::execute_query('COMMIT')===false)throw new RuntimeException('Remittance confirmation commit failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>'CONFIRMED','obligation_status'=>$status));
		}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->err($e->getMessage(),'hris_remittance_confirm_failed',409);}
	}
	public function calculate_exit(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;$id=(int)$r['id'];global $wpdb;
		$sett=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND status IN ('DRAFT','CALCULATION_FAILED')",$this->table('hris_exit_settlements'),$id),ARRAY_A);if(!$sett)return $this->err('Only a new draft settlement can be calculated.','hris_exit_state',409);
		$finalRule=Stockland_HRIS::approved_rule('FINAL_SETTLEMENT',$sett['exit_date']);if(is_wp_error($finalRule))return $finalRule;$payeRule=Stockland_HRIS::approved_rule('PAYE',$sett['exit_date']);if(is_wp_error($payeRule))return $payeRule;
		$items=json_decode((string)$sett['components_json'],true);if(!is_array($items)||!$items)return $this->err('Final settlement components must be itemized and evidenced.');
		$map=array();foreach(Stockland_HRIS::rule_components((int)$finalRule['id']) as $c)$map[strtoupper($c['category'])]=$c;
		$gross='0.00';$taxable='0.00';$exempt='0.00';$ded='0.00';
		foreach($items as $item){if(!is_array($item)||empty($item['category'])||!isset($item['amount']))return $this->err('Each final settlement item needs category and amount.');$amount=$this->money($item['amount'],false);if($amount===false)return $this->err('Invalid final settlement amount.');
			$cat=strtoupper(sanitize_key((string)$item['category']));if(!isset($map[$cat]))return $this->err('No approved final-settlement rule component for '.$cat.'.');
			$rule=$map[$cat];$type=strtoupper(sanitize_key((string)($item['component_type']??'EARNING')));
			if($type==='DEDUCTION'){$ded=Stockland_Money::add($ded,$amount);continue;}
			if(!in_array($type,array('EARNING','ALLOWANCE','BENEFIT','REIMBURSEMENT'),true))return $this->err('Unsupported settlement component type.');
			$gross=Stockland_Money::add($gross,$amount);
			switch(strtoupper($rule['tax_treatment']??'REVIEW_REQUIRED')){case 'TAXABLE':$taxable=Stockland_Money::add($taxable,$amount);break;case 'EXEMPT_FULL':$exempt=Stockland_Money::add($exempt,$amount);break;case 'EXEMPT_CAP':$cap=Stockland_Money::minor($rule['exemption_cap']);$ex=Stockland_Money::decimal(min(Stockland_Money::minor($amount),$cap));$exempt=Stockland_Money::add($exempt,$ex);$taxable=Stockland_Money::add($taxable,Stockland_Money::subtract($amount,$ex));break;default:return $this->err('Final-settlement tax treatment remains under legal review for '.$cat.'.');}
		}
		$payeParams=json_decode((string)$payeRule['rules_json'],true);if(!in_array($payeParams['tax_base_method']??'',array('TAXABLE_GROSS','TAXABLE_GROSS_LESS_EMPLOYEE_PENSION'),true))return $this->err('Approved PAYE rule tax base method is missing.');
		$taxableBase=$taxable; if($payeParams['tax_base_method']==='TAXABLE_GROSS_LESS_EMPLOYEE_PENSION')return $this->err('The final-settlement PAYE pension-base interaction requires an explicit approved pensionable settlement calculation; do not infer a deduction.');
		$bands=array_values(array_filter(Stockland_HRIS::rule_components((int)$payeRule['id']),function($c){return strtoupper($c['category'])==='PAYE_BAND';}));$tax=Stockland_HRIS::progressive_tax($taxableBase,$bands);if(is_wp_error($tax))return $tax;
		$net=Stockland_Money::subtract(Stockland_Money::subtract($gross,$tax),$ded);if(Stockland_Money::minor($net)<0)return $this->err('Net settlement cannot be negative.');
		if(Stockland_DB::update_record($this->table('hris_exit_settlements'),array('rule_id'=>(int)$finalRule['id'],'rule_version'=>$finalRule['version_label'],'gross_total'=>$gross,'taxable_total'=>$taxable,'tax_total'=>$tax,'deductions_total'=>$ded,'net_total'=>$net,'status'=>'CALCULATED'),array('id'=>$id,'status'=>$sett['status']))===false)return $this->err('Settlement calculation could not be saved.');
		Stockland_HRIS::log_event('exit-settlement',$id,'SETTLEMENT_CALCULATED',$sett['status'],'CALCULATED',array('gross'=>$gross,'taxable'=>$taxable,'tax'=>$tax,'deductions'=>$ded,'net'=>$net,'rule_version'=>$finalRule['version_label']));
		return rest_ensure_response(array('id'=>$id,'status'=>'CALCULATED','gross_total'=>$gross,'taxable_total'=>$taxable,'tax_total'=>$tax,'deductions_total'=>$ded,'net_total'=>$net,'rule_version'=>$finalRule['version_label']));
	}
	public function approve_exit(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;$id=(int)$r['id'];$p=$r->get_json_params();$note=sanitize_textarea_field((string)($p['approval_note']??''));if(strlen($note)<30)return $this->err('Independent Owner final-settlement review note is required.');
		global $wpdb;$sett=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND status='CALCULATED'",$this->table('hris_exit_settlements'),$id),ARRAY_A);if(!$sett)return $this->err('Only a calculated settlement can be approved.');
		if((int)$sett['created_by']===get_current_user_id())return $this->err('Settlement maker cannot approve the same settlement.','hris_maker_checker',409);
		$emp=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',$this->table('hris_employments'),(int)$sett['employment_id']),ARRAY_A);if(!$emp||$emp['status']!=='ACTIVE')return $this->err('Only an active employment can be settled.');
		if(Stockland_DB::update_record($this->table('hris_exit_settlements'),array('status'=>'APPROVED','approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql')),array('id'=>$id,'status'=>'CALCULATED'))===false)return $this->err('Settlement approval failed.');
		Stockland_HRIS::log_event('exit-settlement',$id,'SETTLEMENT_APPROVED','CALCULATED','APPROVED',array('net_total'=>$sett['net_total'],'approval_note_hash'=>hash('sha256',$note)),get_current_user_id());
		return rest_ensure_response(array('id'=>$id,'status'=>'APPROVED'));
	}
	public function pay_exit(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;$id=(int)$r['id'];$p=$r->get_json_params();$ref=sanitize_text_field((string)($p['payment_reference']??''));$method=strtoupper(sanitize_key((string)($p['payment_method']??'')));if(strlen($ref)<3||!in_array($method,array('BANK_TRANSFER','CASH','CHEQUE'),true))return $this->err('Payment method and external reference are required.');
		global $wpdb;$s=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND status='APPROVED'",$this->table('hris_exit_settlements'),$id),ARRAY_A);if(!$s)return $this->err('Only an approved final settlement can be paid.');
		if(Stockland_DB::read_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE payment_reference=%s AND id<>%d',$this->table('hris_exit_settlements'),$ref,$id)))return $this->err('Payment reference already used.');
		$jid=Stockland_Accounting::post_journal($s['exit_date'],'HRIS_EXIT_SETTLEMENT',(string)$id,'HRIS final settlement '.$s['settlement_code'],array(
			array('account_code'=>'6200','debit'=>$s['gross_total'],'credit'=>'0.00','memo'=>'Final settlement'),
			array('account_code'=>'2020','debit'=>'0.00','credit'=>$s['net_total'],'memo'=>'Net final settlement payable'),
			array('account_code'=>'2031','debit'=>'0.00','credit'=>$s['tax_total'],'memo'=>'PAYE withheld'),
			array('account_code'=>'2033','debit'=>'0.00','credit'=>$s['deductions_total'],'memo'=>'Other reviewed deductions')
		),get_current_user_id());
		if(!$jid)return $this->err('Accounting journal could not be posted; verify account mappings and debit/credit balance.');
		Stockland_DB::update_record($this->table('hris_exit_settlements'),array('status'=>'PAID','payment_reference'=>$ref,'paid_by'=>get_current_user_id(),'paid_at'=>current_time('mysql')),array('id'=>$id,'status'=>'APPROVED'));
		Stockland_DB::update_record($this->table('hris_employments'),array('status'=>'EXITED','end_date'=>$s['exit_date']),array('id'=>(int)$s['employment_id'],'status'=>'ACTIVE'));
		Stockland_HRIS::log_event('exit-settlement',$id,'SETTLEMENT_PAID','APPROVED','PAID',array('journal_id'=>(int)$jid,'payment_reference_hash'=>hash('sha256',$ref)));
		return rest_ensure_response(array('id'=>$id,'status'=>'PAID','journal_id'=>(int)$jid,'message'=>'Payment reference is recorded and accounting is posted; verify actual bank/cash settlement and remittances before closure.'));
	}
	public function classify_payee_request(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;$id=(int)$r['id'];$p=$r->get_json_params();$decision=strtoupper(sanitize_key((string)($p['decision']??'LEGAL_TAX_REVIEW')));$basis=sanitize_textarea_field((string)($p['basis_note']??''));$ruleId=(int)($p['rule_id']??0);$rate=$p['withholding_rate']??null;
		if(strlen($basis)<30)return $this->err('Classification needs source-backed independent reviewer rationale (30+ characters).');
		if(!in_array($decision,array('EMPLOYMENT_REVIEW','BUSINESS_INCOME','SCHEDULE_D_REVIEW','CONTRACT_SERVICE_REVIEW','NO_WITHHOLDING_APPROVED','LEGAL_TAX_REVIEW'),true))return $this->err('Select a recognized fact-based classification decision; do not classify from registration label alone.');
		$row=Stockland_DB::read_row($GLOBALS['wpdb']->prepare('SELECT * FROM %i WHERE id=%d',$this->table('hris_payee_requests'),$id),ARRAY_A);if(!$row)return $this->err('Payee request not found.');
		$eng=Stockland_DB::read_row($GLOBALS['wpdb']->prepare('SELECT * FROM %i WHERE id=%d',$this->table('hris_payee_engagements'),(int)$row['engagement_id']),ARRAY_A);if(!$eng)return $this->err('Payee engagement not found.');
		$employment=Stockland_DB::read_var($GLOBALS['wpdb']->prepare("SELECT COUNT(*) FROM %i WHERE person_id=%d AND status='ACTIVE'",$this->table('hris_employments'),(int)$eng['person_id']));
		if((int)$employment>0&&$decision!=='EMPLOYMENT_REVIEW')return $this->err('The person has an active employment relationship; route the case for employment classification review before contractor payment.','hris_payee_employee_conflict',409);
		$rule=null;if($ruleId){$rule=Stockland_DB::read_row($GLOBALS['wpdb']->prepare("SELECT * FROM %i WHERE id=%d AND rule_type='PAYEE_WITHHOLDING' AND review_status='APPROVED'",$this->table('hris_legal_rules'),$ruleId),ARRAY_A);if(!$rule)return $this->err('Selected withholding rule is not an approved PAYEE_WITHHOLDING rule.');}
		if(in_array($decision,array('BUSINESS_INCOME','SCHEDULE_D_REVIEW','CONTRACT_SERVICE_REVIEW'),true)&&!$rule)return $this->err('Tax-affecting payee decisions require an approved effective-dated PAYEE_WITHHOLDING rule or must remain LEGAL_TAX_REVIEW.');
		if($rule&&($rate===null||!is_numeric($rate)||(float)$rate<0||(float)$rate>1))return $this->err('Approved withholding rate as a fraction is required.');
		if($decision==='NO_WITHHOLDING_APPROVED'&&!$rule)return $this->err('No-withholding decision requires an approved rule and written legal basis.');
		$with=$rule?Stockland_Money::percentage(Stockland_Money::add($row['cash_amount'],$row['in_kind_value']),(string)$rate):null;
		$data=array('request_id'=>$id,'decision'=>$decision,'rule_id'=>$rule?(int)$rule['id']:null,'rule_version'=>$rule?$rule['version_label']:null,'source_clause'=>$rule?$rule['source_clause']:null,'basis_note'=>$basis,'withholding_rate'=>$rate===null?null:(string)$rate,'withholding_amount'=>$with,'reviewer_id'=>get_current_user_id());
		if(Stockland_DB::insert_record($this->table('hris_payee_decisions'),$data)===false)return $this->err('Could not append payee classification decision.');
		$status=$decision==='LEGAL_TAX_REVIEW'||$decision==='EMPLOYMENT_REVIEW'?'NEEDS_REVIEW':'APPROVED';
		Stockland_DB::update_record($this->table('hris_payee_requests'),array('classification_status'=>$status),array('id'=>$id));
		Stockland_HRIS::log_event('payee-request',$id,'CLASSIFICATION_'.$decision,null,$status,array('rule_version'=>$rule['version_label']??null,'withholding'=>$with));
		return rest_ensure_response(array('id'=>$id,'classification_status'=>$status,'decision_id'=>(int)$GLOBALS['wpdb']->insert_id,'withholding_amount'=>$with,'message'=>$status==='APPROVED'?'Decision recorded against an approved rule.':'Held for qualified legal/employment review.'));
	}
	public function pay_payee_request(WP_REST_Request $r) {
		$ready=$this->require_ready();if(is_wp_error($ready))return $ready;$id=(int)$r['id'];$p=$r->get_json_params();$method=strtoupper(sanitize_key((string)($p['payment_method']??'')));$ref=sanitize_text_field((string)($p['payment_reference']??''));
		if(!in_array($method,array('BANK_TRANSFER','CASH','CHEQUE'),true)||strlen($ref)<3)return $this->err('Approved payment method and external reference required.');
		global $wpdb;$req=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND status='APPROVED' AND classification_status='APPROVED'",$this->table('hris_payee_requests'),$id),ARRAY_A);if(!$req)return $this->err('Only an independently approved payee request with a completed classification can be paid.');
		$decision=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE request_id=%d ORDER BY id DESC LIMIT 1',$this->table('hris_payee_decisions'),$id),ARRAY_A);if(!$decision||in_array($decision['decision'],array('LEGAL_TAX_REVIEW','EMPLOYMENT_REVIEW'),true))return $this->err('Payee case is held for legal/employment review.');
		$gross=Stockland_Money::add($req['cash_amount'],$req['in_kind_value']);$with=$decision['withholding_amount']?:'0.00';$net=Stockland_Money::subtract($req['cash_amount'],$with);if(Stockland_Money::minor($net)<0)return $this->err('Withholding exceeds cash amount; legal/finance review required.');
		if(Stockland_DB::read_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE payment_reference=%s',$this->table('hris_payee_payments'),$ref)))return $this->err('Payee payment reference already used.');
		if(Stockland_DB::insert_record($this->table('hris_payee_payments'),array('payment_code'=>'PAYEE-'.$id.'-'.wp_generate_password(5,false,false),'request_id'=>$id,'gross_amount'=>$gross,'withholding_amount'=>$with,'net_amount'=>$net,'payment_method'=>$method,'payment_reference'=>$ref,'status'=>'PREPARED','recorded_by'=>get_current_user_id()))===false)return $this->err('Payee payment record failed.');
		$pid=(int)$wpdb->insert_id;Stockland_HRIS::log_event('payee-payment',$pid,'PAYEE_PAYMENT_PREPARED',null,'PREPARED',array('request_id'=>$id,'net_amount'=>$net,'payment_reference_hash'=>hash('sha256',$ref)));
		return rest_ensure_response(array('id'=>$pid,'status'=>'PREPARED','net_amount'=>$net,'message'=>'Payment prepared only. Finance must confirm external settlement and reconcile AP/GL under the approved account map before marking it paid.'));
	}
}
