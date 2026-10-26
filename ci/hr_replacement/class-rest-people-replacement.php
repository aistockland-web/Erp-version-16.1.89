<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Replacement People & Payroll REST API. It never reads or writes legacy
 * employees/compensation/payroll rows. Legacy data is only migrated after a
 * separate rehearsal, reconciliation, backup/restore, and cutover approval.
 */
final class Stockland_REST_People_Replacement extends Stockland_REST_Base {
	public function __construct() {
		add_action( 'stockland_register_rest_routes', array( $this, 'register_routes' ) );
	}
	public function register_routes() {
		$base = '/people-replacement';
		register_rest_route( self::NAMESPACE_V1, $base . '/status', array( 'methods'=>'GET', 'callback'=>array($this,'status'), 'permission_callback'=>array($this,'can_view') ) );
		register_rest_route( self::NAMESPACE_V1, $base . '/schema/install', array( 'methods'=>'POST', 'callback'=>array($this,'install_schema'), 'permission_callback'=>array($this,'can_install_schema') ) );
		register_rest_route( self::NAMESPACE_V1, $base . '/bootstrap', array( 'methods'=>'GET', 'callback'=>array($this,'bootstrap'), 'permission_callback'=>array($this,'can_view') ) );
		register_rest_route( self::NAMESPACE_V1, $base . '/(?P<entity>[a-z\-]+)', array(
			array('methods'=>'GET','callback'=>array($this,'collection'),'permission_callback'=>array($this,'authorize_collection')),
			array('methods'=>'POST','callback'=>array($this,'collection'),'permission_callback'=>array($this,'authorize_collection')),
		) );
		register_rest_route( self::NAMESPACE_V1, $base . '/(?P<entity>[a-z\-]+)/(?P<id>\d+)', array(
			array('methods'=>'GET','callback'=>array($this,'record'),'permission_callback'=>array($this,'authorize_record')),
			array('methods'=>'POST','callback'=>array($this,'record'),'permission_callback'=>array($this,'authorize_record')),
		) );
		register_rest_route( self::NAMESPACE_V1, $base . '/(?P<entity>[a-z\-]+)/(?P<id>\d+)/decision', array('methods'=>'POST','callback'=>array($this,'decision'),'permission_callback'=>array($this,'authorize_decision')) );
		register_rest_route( self::NAMESPACE_V1, $base . '/applications/(?P<id>\d+)/convert', array('methods'=>'POST','callback'=>array($this,'convert_application'),'permission_callback'=>array($this,'can_prepare_hr')) );
		register_rest_route( self::NAMESPACE_V1, $base . '/employments/(?P<id>\d+)/activate', array('methods'=>'POST','callback'=>array($this,'activate_employment'),'permission_callback'=>array($this,'can_approve_hr')) );
		register_rest_route( self::NAMESPACE_V1, $base . '/payroll-runs/(?P<id>\d+)/calculate', array('methods'=>'POST','callback'=>array($this,'calculate_payroll'),'permission_callback'=>array($this,'can_prepare_payroll')) );
		register_rest_route( self::NAMESPACE_V1, $base . '/payroll-runs/(?P<id>\d+)/submit', array('methods'=>'POST','callback'=>array($this,'submit_payroll'),'permission_callback'=>array($this,'can_prepare_payroll')) );
		register_rest_route( self::NAMESPACE_V1, $base . '/payroll-runs/(?P<id>\d+)/approve', array('methods'=>'POST','callback'=>array($this,'approve_payroll'),'permission_callback'=>array($this,'can_approve_payroll')) );
		register_rest_route( self::NAMESPACE_V1, $base . '/payroll-runs/(?P<id>\d+)/post', array('methods'=>'POST','callback'=>array($this,'post_payroll'),'permission_callback'=>array($this,'can_approve_payroll')) );
		register_rest_route( self::NAMESPACE_V1, $base . '/payroll-runs/(?P<id>\d+)/payment-batch', array('methods'=>'POST','callback'=>array($this,'create_payment_batch'),'permission_callback'=>array($this,'can_finance')) );
		register_rest_route( self::NAMESPACE_V1, $base . '/payment-items/(?P<id>\d+)/result', array('methods'=>'POST','callback'=>array($this,'update_payment_result'),'permission_callback'=>array($this,'can_finance')) );
		register_rest_route( self::NAMESPACE_V1, $base . '/exit-cases/(?P<id>\d+)/calculate', array('methods'=>'POST','callback'=>array($this,'calculate_exit'),'permission_callback'=>array($this,'can_prepare_hr')) );
		register_rest_route( self::NAMESPACE_V1, $base . '/exit-cases/(?P<id>\d+)/approve', array('methods'=>'POST','callback'=>array($this,'approve_exit'),'permission_callback'=>array($this,'can_approve_hr')) );
	}
	public function can_view($r=null) { return Stockland_Authority::can_view_hr() || Stockland_Authority::can_view_payroll() || $this->can_finance() || $this->can_review_legal_rule(); }
	public function can_prepare_hr($r=null) { return Stockland_Authority::can_manage_hr(); }
	public function can_approve_hr($r=null) { return Stockland_Authority::can_approve_payroll(); }
	public function can_prepare_payroll($r=null) { return Stockland_Authority::can_manage_payroll(); }
	public function can_approve_payroll($r=null) { return Stockland_Authority::can_approve_payroll(); }
	public function can_finance($r=null) { return current_user_can('stockland_manage_payables') || Stockland_Authority::is_owner(); }
	public function can_review_legal_rule($r=null) { return current_user_can('stockland_review_statutory_rules'); }
	public function can_install_schema($r=null) { return current_user_can('stockland_install_schema') && Stockland_Authority::can_manage_hr(); }
	private function ready() { $s=Stockland_People_Replacement::schema_status(); return !empty($s['ready']); }

	public function status($r) {
		return rest_ensure_response(array('schema'=>Stockland_People_Replacement::schema_status(),'legacy_tables_are_targets'=>false,'legacy_tables_are_migration_sources'=>true,'production_certified'=>false,'can_install_schema'=>$this->can_install_schema(),'message'=>'Replacement target only; cutover requires a separate approved migration and reconciliation.'));
	}
	public function install_schema($r) {
		if (!$this->can_install_schema($r)) return new WP_Error('people_schema_forbidden','Owner schema and HR-management permissions are required.',array('status'=>403));
		try { return rest_ensure_response(Stockland_People_Replacement::install_batch(absint($r->get_param('offset')),max(1,min(4,absint($r->get_param('limit')?:4)))); }
		catch(Throwable $e) { Stockland_Runtime_Diagnostics::log_error('People schema setup: '.$e->getMessage()); return new WP_Error('people_schema_failed','New schema setup failed. Legacy HR tables were not targeted.',array('status'=>500)); }
	}
	public function bootstrap($r) {
		$schema=Stockland_People_Replacement::schema_status();
		if (empty($schema['ready'])) return new WP_REST_Response(array('ready'=>false,'schema'=>$schema,'legacy_fallback'=>false,'message'=>'New target tables must be installed by an authorized administrator.'),503);
		$entities=array();
		foreach(self::specs() as $k=>$s) if($this->can_read($s)) $entities[]=array('id'=>$k,'title'=>$s['title'],'description'=>$s['description'],'can_create'=>$this->can_write($s),'fields'=>$s['fields'],'required'=>$s['required'],'list_fields'=>$s['list']);
		return rest_ensure_response(array('ready'=>true,'schema'=>$schema,'company'=>'STOCKLAND ETHIOPIA','locations'=>array('CENTRAL WAREHOUSE','GORO BRANCH','GERJI BRANCH','MEXICO BRANCH','MEGENAGNA BRANCH'),'entities'=>$entities,'capabilities'=>array('hr_prepare'=>$this->can_prepare_hr(),'hr_approve'=>$this->can_approve_hr(),'payroll_prepare'=>$this->can_prepare_payroll(),'payroll_approve'=>$this->can_approve_payroll(),'finance'=>$this->can_finance(),'legal_review'=>$this->can_review_legal_rule()),'workflow'=>array('employment'=>array('Workforce plan','Vacancy','Candidate review','Human selection','Contract','Onboarding','Employee activation','Time and leave','Approved compensation','Payroll','Exit and final settlement'),'payroll'=>array('Draft','Calculated','Submitted','Approved','Posted','Payment batch','Confirmed recipient payment','Remittance','Reconciled','Closed'),'payees'=>array('Draft','Submitted','Legal review','Approved','Ready to pay','Paid or failed','Reconciled','Closed'))));
	}

	private static function specs() {
		return array(
			'people'=>array('title'=>'People','description'=>'Canonical identity shared across applicant, employee and individual payee','table'=>'people_people','permission'=>'hr','required'=>array('full_name'),'list'=>array('id','person_code','person_kind','full_name','display_name','status','created_at'),'fields'=>array('person_code'=>'text','person_kind'=>'enum:UNCLASSIFIED,CANDIDATE,EMPLOYEE,INDIVIDUAL_PAYEE','full_name'=>'text','display_name'=>'text','contact_email'=>'email','contact_phone'=>'text','identity_reference_key'=>'private_ref','residency_code'=>'text','tin_reference_key'=>'private_ref','license_reference_key'=>'private_ref','privacy_notice_version'=>'text','privacy_notice_at'=>'datetime','retention_until'=>'date','notes_redacted'=>'text')),
			'employments'=>array('title'=>'Employment','description'=>'Effective-dated employment and location history','table'=>'people_employments','permission'=>'hr','required'=>array('person_id','employee_code','legal_employer','department','contract_basis','work_schedule','start_date'),'list'=>array('id','employee_code','person_id','department','position_id','location_id','start_date','end_date','lifecycle_status','status'),'fields'=>array('person_id'=>'int','employee_code'=>'text','legal_employer'=>'text','department'=>'text','position_id'=>'int','manager_person_id'=>'int','location_id'=>'int','cost_center'=>'text','contract_basis'=>'enum:INDEFINITE,FIXED_TERM,SPECIFIC_TASK,PART_TIME,OTHER_APPROVED','work_schedule'=>'enum:FULL_TIME,PART_TIME,SHIFT,OTHER_APPROVED','start_date'=>'date','end_date'=>'date','probation_end'=>'date','last_worked_date'=>'date','source_legacy_employee_id_unused'=>'text')),
			'workforce-plans'=>array('title'=>'Workforce Plans','description'=>'Headcount and budget approvals','table'=>'people_workforce_plans','permission'=>'hr','required'=>array('plan_code','fiscal_period','department','justification'),'list'=>array('id','plan_code','fiscal_period','department','location_id','planned_headcount','budget_amount','status'),'fields'=>array('plan_code'=>'text','fiscal_period'=>'text','department'=>'text','location_id'=>'int','planned_headcount'=>'int','budget_amount'=>'money','budget_reference'=>'text','justification'=>'longtext')),
			'positions'=>array('title'=>'Positions and Grades','description'=>'Job position, grade, pay band and location','table'=>'people_positions','permission'=>'hr','required'=>array('position_code','title','department','effective_from'),'list'=>array('id','position_code','title','department','grade_code','pay_band_code','location_id','effective_from','effective_to','status'),'fields'=>array('position_code'=>'text','title'=>'text','department'=>'text','grade_code'=>'text','pay_band_code'=>'text','manager_position_id'=>'int','location_id'=>'int','cost_center'=>'text','headcount_limit'=>'int','effective_from'=>'date','effective_to'=>'date')),
			'requisitions'=>array('title'=>'Vacancies','description'=>'Workforce-backed recruitment requisitions','table'=>'people_requisitions','permission'=>'hr','required'=>array('requisition_code','position_id','reason'),'list'=>array('id','requisition_code','position_id','location_id','openings','required_from','close_by','status'),'fields'=>array('requisition_code'=>'text','workforce_plan_id'=>'int','position_id'=>'int','location_id'=>'int','openings'=>'int','reason'=>'longtext','required_from'=>'date','close_by'=>'date','recruiter_user_id'=>'int','budget_amount'=>'money')),
			'applications'=>array('title'=>'Applications','description'=>'Candidate screening and human selection rationale','table'=>'people_applications','permission'=>'hr','required'=>array('application_code','requisition_id','person_id','notice_acknowledged_at'),'list'=>array('id','application_code','requisition_id','person_id','source_code','status','decision_at'),'fields'=>array('application_code'=>'text','requisition_id'=>'int','person_id'=>'int','source_code'=>'text','notice_version'=>'text','notice_acknowledged_at'=>'datetime','screening_summary'=>'longtext','interview_score_json'=>'json','selection_rationale'=>'longtext','retention_until'=>'date')),
			'contracts'=>array('title'=>'Contracts','description'=>'Signed terms and effective dates; private references only','table'=>'people_contracts','permission'=>'hr','required'=>array('contract_code','employment_id','contract_basis','start_date'),'list'=>array('id','contract_code','employment_id','contract_version','contract_basis','start_date','end_date','document_id','signed_at','status'),'fields'=>array('contract_code'=>'text','employment_id'=>'int','contract_version'=>'int','contract_basis'=>'enum:INDEFINITE,FIXED_TERM,SPECIFIC_TASK,PART_TIME,OTHER_APPROVED','start_date'=>'date','end_date'=>'date','probation_end'=>'date','terms_reference'=>'private_ref','document_id'=>'int','signed_at'=>'datetime','signed_by_person_id'=>'int','supersedes_contract_id'=>'int')),
			'documents'=>array('title'=>'Document References','description'=>'Opaque approved vault references and hashes','table'=>'people_documents','permission'=>'hr','required'=>array('document_code','subject_type','subject_id','document_type','vault_reference'),'list'=>array('id','document_code','subject_type','subject_id','document_type','document_sha256','issued_on','expires_on','sensitivity','status'),'fields'=>array('document_code'=>'text','subject_type'=>'enum:PERSON,APPLICATION,CONTRACT,EMPLOYMENT,PAYROLL,EXIT,PAYEE','subject_id'=>'int','document_type'=>'text','vault_reference'=>'private_ref','document_sha256'=>'sha256','issued_on'=>'date','expires_on'=>'date','sensitivity'=>'enum:RESTRICTED,HIGHLY_RESTRICTED,INTERNAL','retention_until'=>'date')),
			'tasks'=>array('title'=>'Onboarding, Training and Clearance','description'=>'Assigned tasks and completion evidence','table'=>'people_tasks','permission'=>'hr','required'=>array('task_code','task_type','subject_type','subject_id'),'list'=>array('id','task_code','task_type','subject_type','subject_id','assigned_user_id','due_date','completed_at','status'),'fields'=>array('task_code'=>'text','task_type'=>'text','subject_type'=>'text','subject_id'=>'int','assigned_user_id'=>'int','due_date'=>'date','evidence_document_id'=>'int','reason'=>'longtext')),
			'shifts'=>array('title'=>'Shifts and Rosters','description'=>'Planned work time with timezone','table'=>'people_shifts','permission'=>'hr','required'=>array('shift_code','shift_start','shift_end','timezone'),'list'=>array('id','shift_code','employment_id','location_id','shift_start','shift_end','timezone','status'),'fields'=>array('shift_code'=>'text','employment_id'=>'int','location_id'=>'int','shift_start'=>'datetime','shift_end'=>'datetime','timezone'=>'text','break_minutes'=>'int','calendar_rule_set_id'=>'int')),
			'time-entries'=>array('title'=>'Attendance','description'=>'Time records and linked corrections','table'=>'people_time_entries','permission'=>'hr','required'=>array('entry_code','employment_id','work_date','timezone'),'list'=>array('id','entry_code','employment_id','shift_id','work_date','clock_in','clock_out','ordinary_minutes','overtime_minutes','status'),'fields'=>array('entry_code'=>'text','employment_id'=>'int','shift_id'=>'int','work_date'=>'date','clock_in'=>'datetime','clock_out'=>'datetime','timezone'=>'text','ordinary_minutes'=>'int','overtime_minutes'=>'int','night_minutes'=>'int','rest_day_minutes'=>'int','holiday_minutes'=>'int','source_event_key'=>'text','correction_of_id'=>'int','correction_reason'=>'longtext')),
			'leave-events'=>array('title'=>'Leave Ledger and Requests','description'=>'Request, accrual, use and reversal events','table'=>'people_leave_events','permission'=>'hr','required'=>array('leave_code','employment_id','leave_type','event_kind'),'list'=>array('id','leave_code','employment_id','leave_type','event_kind','start_date','end_date','units','policy_version','status'),'fields'=>array('leave_code'=>'text','employment_id'=>'int','leave_type'=>'text','event_kind'=>'enum:REQUEST,ACCRUAL,GRANT,USE,REVERSAL,CARRY_FORWARD','start_date'=>'date','end_date'=>'date','units'=>'money','policy_version'=>'text','reason'=>'longtext','evidence_document_id'=>'int','reversal_of_id'=>'int')),
			'compensation-assignments'=>array('title'=>'Compensation Assignments','description'=>'Effective-dated base salary and approved grade','table'=>'people_compensation_assignments','permission'=>'hr','required'=>array('assignment_code','employment_id','effective_from','base_salary','reason'),'list'=>array('id','assignment_code','employment_id','effective_from','effective_to','base_salary','grade_code','pay_band_code','status'),'fields'=>array('assignment_code'=>'text','employment_id'=>'int','effective_from'=>'date','effective_to'=>'date','base_salary'=>'money','currency'=>'enum:ETB','grade_code'=>'text','pay_band_code'=>'text','contract_reference'=>'private_ref','reason'=>'longtext','supersedes_assignment_id'=>'int')),
			'compensation-items'=>array('title'=>'Compensation Items','description'=>'Earnings and deductions classified by approved rule','table'=>'people_compensation_items','permission'=>'hr','required'=>array('item_code','assignment_id','component_code','amount'),'list'=>array('id','item_code','assignment_id','component_code','amount','cadence','recurring','rule_item_id','status'),'fields'=>array('item_code'=>'text','assignment_id'=>'int','component_code'=>'text','amount'=>'money','cadence'=>'enum:MONTHLY,ONE_TIME,PER_HOUR,PER_UNIT','recurring'=>'bool','rule_item_id'=>'int','evidence_document_id'=>'int','contract_basis_reference'=>'private_ref','notes_redacted'=>'text')),
			'rule-sets'=>array('title'=>'Statutory Rule Sets','description'=>'Source-hashed effective dates and reviewer approval','table'=>'people_rule_sets','permission'=>'rules','required'=>array('rule_set_code','rule_type','version_label','effective_from','source_title','source_url','source_sha256','source_clause','method_code','parameters_json'),'list'=>array('id','rule_set_code','rule_type','version_label','effective_from','effective_to','source_title','source_clause','review_status','status'),'fields'=>array('rule_set_code'=>'text','rule_type'=>'enum:PAYROLL,LEAVE,ATTENDANCE,EXIT,CREATOR_WITHHOLDING,SERVICE_WITHHOLDING','jurisdiction'=>'text','version_label'=>'text','effective_from'=>'date','effective_to'=>'date','source_title'=>'text','source_url'=>'source_url','source_sha256'=>'sha256','source_clause'=>'text','method_code'=>'text','parameters_json'=>'json','supersedes_rule_set_id'=>'int')),
			'rule-items'=>array('title'=>'Component Classifications','description'=>'Classification and evidence requirements','table'=>'people_rule_items','permission'=>'rules','required'=>array('rule_item_code','rule_set_id','component_code','classification_code','parameters_json','source_clause','effective_from'),'list'=>array('id','rule_item_code','rule_set_id','component_code','classification_code','effective_from','effective_to','status'),'fields'=>array('rule_item_code'=>'text','rule_set_id'=>'int','component_code'=>'text','classification_code'=>'enum:TAXABLE_PENSIONABLE,TAXABLE_NONPENSIONABLE,EXEMPT_PENSIONABLE,EXEMPT_NONPENSIONABLE,DEDUCTION,REIMBURSEMENT_REVIEW,LEGAL_REVIEW','parameters_json'=>'json','evidence_required'=>'bool','source_clause'=>'text','effective_from'=>'date','effective_to'=>'date')),
			'payroll-lines'=>array('title'=>'Payroll Line Details','description'=>'Itemized run calculation and held-exception reason','table'=>'people_payroll_lines','permission'=>'payroll','required'=>array(),'list'=>array('id','run_id','person_id','employment_id','status','base_amount','taxable_earnings','approved_exempt_earnings','gross_amount','paye_amount','employee_pension_amount','employer_pension_amount','other_deduction_amount','net_amount','hold_reason'),'fields'=>array()),
			'payroll-runs'=>array('title'=>'Monthly Payroll Runs','description'=>'Snapshot, blocking exceptions and independent approval','table'=>'people_payroll_runs','permission'=>'payroll','required'=>array('run_code','period_start','period_end'),'list'=>array('id','run_code','period_start','period_end','status','exception_count','gross_total','paye_total','net_total','posted_journal_id'),'fields'=>array('run_code'=>'text','period_start'=>'date','period_end'=>'date')),
			'payment-batches'=>array('title'=>'Payment Batches','description'=>'Batch instructions distinct from paid outcomes','table'=>'people_payment_batches','permission'=>'finance','required'=>array('batch_code','payment_purpose','source_type','source_id','idempotency_key'),'list'=>array('id','batch_code','payment_purpose','source_type','source_id','total_amount','external_batch_reference','status'),'fields'=>array('batch_code'=>'text','payment_purpose'=>'enum:PAYROLL,EXIT,INDIVIDUAL_PAYEE,REIMBURSEMENT','source_type'=>'text','source_id'=>'int','total_amount'=>'money','currency'=>'enum:ETB','payment_method'=>'enum:BANK_TRANSFER,ACCOUNT_PAYEE_CHEQUE,APPROVED_OTHER','external_batch_reference'=>'private_ref','idempotency_key'=>'text')),
			'payment-items'=>array('title'=>'Recipient Payment Outcomes','description'=>'External references and recipient-specific results','table'=>'people_payment_items','permission'=>'finance','required'=>array('payment_item_code','batch_id','payee_type','payee_id','amount'),'list'=>array('id','payment_item_code','batch_id','payee_type','payee_id','amount','external_payment_reference','paid_at','failure_code','status'),'fields'=>array('payment_item_code'=>'text','batch_id'=>'int','payee_type'=>'enum:EMPLOYMENT,EXIT,INDIVIDUAL_PAYEE,REIMBURSEMENT','payee_id'=>'int','amount'=>'money','retry_of_id'=>'int')),
			'remittances'=>array('title'=>'Statutory Remittances','description'=>'PAYE/pension liabilities, returns, receipts and reconciliation','table'=>'people_remittances','permission'=>'finance','required'=>array('remittance_code','remittance_type','period_start','period_end'),'list'=>array('id','remittance_code','remittance_type','period_start','period_end','liability_amount','due_date','status'),'fields'=>array('remittance_code'=>'text','remittance_type'=>'enum:PAYE,PENSION,OTHER_STATUTORY','period_start'=>'date','period_end'=>'date','rule_set_id'=>'int','liability_amount'=>'money','due_date'=>'date','return_reference'=>'private_ref','submission_reference'=>'private_ref','receipt_reference'=>'private_ref','payment_reference'=>'private_ref')),
			'exit-cases'=>array('title'=>'Exit and Final Settlement','description'=>'Clearance, itemized settlement and independent approval','table'=>'people_exit_cases','permission'=>'hr','required'=>array('exit_code','employment_id','exit_type','reason_code','last_worked_date','separation_date'),'list'=>array('id','exit_code','employment_id','exit_type','reason_code','last_worked_date','separation_date','status','settlement_run_id'),'fields'=>array('exit_code'=>'text','employment_id'=>'int','exit_type'=>'enum:RESIGNATION,CONTRACT_END,RETIREMENT,TERMINATION,OTHER_LEGAL_CATEGORY','reason_code'=>'text','notice_date'=>'date','last_worked_date'=>'date','separation_date'=>'date','clearance_json'=>'json')),
			'exit-items'=>array('title'=>'Settlement Details','description'=>'Evidence-linked items with reviewed rule references','table'=>'people_exit_items','permission'=>'hr','required'=>array('exit_case_id','item_code','item_type','amount'),'list'=>array('id','exit_case_id','item_code','item_type','amount','rule_set_id','status'),'fields'=>array('exit_case_id'=>'int','item_code'=>'text','item_type'=>'text','amount'=>'money','rule_set_id'=>'int','source_reference'=>'private_ref','calculation_json'=>'json')),
			'individual-payables'=>array('title'=>'Creators, Freelancers and Individual Payees','description'=>'Central natural-person payable path, not employee payroll by default','table'=>'people_individual_payables','permission'=>'finance','required'=>array('payable_code','person_id','relationship_type','payment_category','cash_amount'),'list'=>array('id','payable_code','person_id','relationship_type','payment_category','cash_amount','in_kind_amount','tax_classification_status','status'),'fields'=>array('payable_code'=>'text','person_id'=>'int','relationship_type'=>'enum:DIGITAL_CONTENT_CREATOR,SERVICE_SUPPLIER,EXPENSE_REIMBURSEMENT,OTHER_INDIVIDUAL','payment_category'=>'text','contract_reference'=>'private_ref','deliverable_reference'=>'text','service_period_start'=>'date','service_period_end'=>'date','cash_amount'=>'money','in_kind_amount'=>'money','currency'=>'enum:ETB','tin_status'=>'enum:PROVIDED,MISSING,NOT_APPLICABLE,REVIEW_REQUIRED','license_status'=>'enum:PROVIDED,MISSING,NOT_APPLICABLE,REVIEW_REQUIRED','supporting_document_id'=>'int','legacy_supplier_id'=>'int')),
			'social-snapshots'=>array('title'=>'Social Account Snapshots','description'=>'Timestamped manual insights, no stored account credentials','table'=>'people_social_snapshots','permission'=>'hr','required'=>array('snapshot_code','platform_code','account_label','reporting_period_start','reporting_period_end','captured_at'),'list'=>array('id','snapshot_code','platform_code','account_label','campaign_code','reporting_period_start','reporting_period_end','captured_at','views_count','reach_count','engagement_count'),'fields'=>array('snapshot_code'=>'text','platform_code'=>'text','account_label'=>'text','campaign_code'=>'text','owner_user_id'=>'int','reporting_period_start'=>'date','reporting_period_end'=>'date','captured_at'=>'datetime','source_reference'=>'source_url','views_count'=>'int','reach_count'=>'int','engagement_count'=>'int','attributed_revenue'=>'money','creator_fees'=>'money','gifted_stock_value'=>'money','notes_redacted'=>'text')),
			'resources'=>array('title'=>'HR Process Center Resources','description'=>'Approved, versioned role-specific SOPs and templates','table'=>'people_resources','permission'=>'hr','required'=>array('resource_code','title','function_code','role_code','resource_version','vault_reference','resource_sha256'),'list'=>array('id','resource_code','title','function_code','role_code','language_code','resource_version','effective_from','approval_status','status'),'fields'=>array('resource_code'=>'text','title'=>'text','function_code'=>'text','role_code'=>'text','language_code'=>'text','resource_version'=>'text','effective_from'=>'date','effective_to'=>'date','vault_reference'=>'private_ref','resource_sha256'=>'sha256'))
		);
	}
	private function can_read($s) {
		if ($s['permission']==='payroll') return Stockland_Authority::can_view_payroll();
		if ($s['permission']==='finance') return $this->can_finance() || Stockland_Authority::can_view_payroll();
		if ($s['permission']==='rules') return Stockland_Authority::can_view_hr() || $this->can_review_legal_rule();
		return Stockland_Authority::can_view_hr();
	}
	private function can_write($s) {
		if ($s['permission']==='payroll') return $this->can_prepare_payroll() && !empty($s['fields']);
		if ($s['permission']==='finance') return $this->can_finance();
		if ($s['permission']==='rules') return $this->can_review_legal_rule() || Stockland_Authority::can_manage_hr();
		return $this->can_prepare_hr();
	}
	public function authorize_collection($r) {
		$s=self::specs()[sanitize_key((string)$r['entity'])]??null;
		if(!$s||!$this->can_read($s)) return new WP_Error('people_entity_forbidden','This entity is not available to your role.',array('status'=>403));
		if($r->get_method()==='GET') return true;
		return $this->can_write($s) ? true : new WP_Error('people_entity_readonly','You cannot create this record.',array('status'=>403));
	}
	public function authorize_record($r) {
		$s=self::specs()[sanitize_key((string)$r['entity'])]??null;
		return $s&&$this->can_read($s) ? true : new WP_Error('people_record_forbidden','This record is not available to your role.',array('status'=>403));
	}
	public function authorize_decision($r) {
		$entity=sanitize_key((string)$r['entity']); $decision=strtoupper(sanitize_key((string)$r->get_param('decision'))); $s=self::specs()[$entity]??null;
		if(!$s||!$this->can_read($s)) return new WP_Error('people_decision_forbidden','This decision is outside your role.',array('status'=>403));
		if($decision==='LEGAL_SIGNOFF') return $this->can_review_legal_rule() ? true : new WP_Error('legal_review_forbidden','A separately assigned statutory reviewer is required.',array('status'=>403));
		if(in_array($decision,array('APPROVE','APPROVE_RULE','ACTIVATE','EFFECTIVE','LOCK','PUBLISH','CLOSE','REJECT'),true)) {
			if(in_array($entity,array('payroll-runs','payment-batches','payment-items','remittances','individual-payables'),true)) return ($this->can_approve_payroll()||$this->can_finance())?true:new WP_Error('people_approval_forbidden','Independent Owner or Finance authority is required.',array('status'=>403));
			return $this->can_approve_hr() ? true : new WP_Error('people_approval_forbidden','Independent Owner approval is required.',array('status'=>403));
		}
		return $this->can_write($s) ? true : new WP_Error('people_decision_forbidden','This workflow action is outside your authority.',array('status'=>403));
	}
	public function collection($r) {
		$e=sanitize_key((string)$r['entity']); $s=self::specs()[$e]??null;
		if(!$s) return new WP_Error('people_entity_unknown','Unknown People & Payroll entity.',array('status'=>404));
		if(!$this->ready()) return new WP_REST_Response(array('ready'=>false,'schema'=>Stockland_People_Replacement::schema_status(),'legacy_fallback'=>false),503);
		return $r->get_method()==='GET' ? $this->list_rows($e,$s,$r) : $this->create_row($e,$s,$r);
	}
	private function list_rows($e,$s,$r) {
		global $wpdb;
		$limit=max(1,min(100,absint($r->get_param('limit')?:50))); $offset=min(50000,absint($r->get_param('offset')));
		$where=array();$args=array();
		if($r->get_param('status')) {$where[]='status=%s';$args[]=sanitize_key((string)$r->get_param('status'));}
		$loc=absint($r->get_param('location_id'));
		if($loc&&!Stockland_Authority::can_access_location($loc)) return new WP_Error('people_location_forbidden','Location outside assigned scope.',array('status'=>403));
		if($loc){$where[]='location_id=%d';$args[]=$loc;}
		$select=implode(',',array_map(array($this,'safe_column'),$s['list']));
		$q='SELECT '.$select.' FROM %i'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY id DESC LIMIT %d OFFSET %d';
		array_unshift($args,Stockland_People_Replacement::table($s['table']));$args[]=$limit;$args[]=$offset;
		$rows=Stockland_DB::read_results($wpdb->prepare($q,$args),ARRAY_A);
		if($wpdb->last_error||!is_array($rows)) return new WP_Error('people_read_failed','Could not load the authorized queue.',array('status'=>500));
		$visible=array();foreach($rows as $row) if($this->in_scope($row)) $visible[]=$this->redact($row);
		return rest_ensure_response(array('items'=>$visible,'limit'=>$limit,'offset'=>$offset,'has_more'=>count($rows)===$limit,'entity'=>$e));
	}
	public function record($r) {
		global $wpdb;$e=sanitize_key((string)$r['entity']);$s=self::specs()[$e]??null;$id=absint($r['id']);
		if(!$s||!$id) return new WP_Error('people_record_invalid','Unknown entity or invalid ID.',array('status'=>400));
		if(!$this->ready()) return new WP_REST_Response(array('ready'=>false,'schema'=>Stockland_People_Replacement::schema_status()),503);
		$table=Stockland_People_Replacement::table($s['table']);
		if($r->get_method()==='GET') {
			$row=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',$table,$id),ARRAY_A);
			if(!$row) return new WP_Error('people_record_missing','Record not found.',array('status'=>404));
			if(!$this->in_scope($row)) return new WP_Error('people_record_scope','Record outside your assigned scope.',array('status'=>403));
			return rest_ensure_response($this->redact($row));
		}
		return $this->update_row($e,$s,$id,$r);
	}
	private function create_row($e,$s,$r) {
		global $wpdb;$input=$r->get_json_params();if(!is_array($input))return new WP_Error('people_json_required','Submit a JSON object.',array('status'=>400));
		$data=$this->clean_fields($input,$s['fields']);if(is_wp_error($data))return $data;
		foreach($s['required'] as $f) if(!array_key_exists($f,$data)||$data[$f]===null||$data[$f]==='')return new WP_Error('people_field_required','Required field missing: '.$f,array('status'=>400,'field'=>$f));
		$rel=$this->relations($e,$data);if(is_wp_error($rel))return $rel;
		if($e==='applications'&&empty($data['notice_acknowledged_at']))return new WP_Error('candidate_notice_required','Candidate privacy notice and acknowledgement are required.',array('status'=>400));
		if($e==='leave-events'){
			$kind=strtoupper((string)($data['event_kind']??''));
			if($kind!=='REQUEST'){
				if(!Stockland_Authority::can_manage_hr())return new WP_Error('leave_ledger_forbidden','Only HR management can prepare leave accrual/grant/use/carry-forward/reversal ledger entries.',array('status'=>403));
				if(strlen(trim((string)($data['reason']??'')))<20||empty($data['policy_version']))return new WP_Error('leave_ledger_evidence_required','Ledger entries require policy version and a specific evidence-backed reason.',array('status'=>400));
				if(!in_array($kind,array('ACCRUAL','GRANT','USE','REVERSAL','CARRY_FORWARD'),true))return new WP_Error('leave_ledger_kind_invalid','Unsupported leave ledger event.',array('status'=>400));
				if(!isset($data['units'])||Stockland_Money::minor($data['units'])<0)return new WP_Error('leave_ledger_units_invalid','Leave units must be a non-negative quantity.',array('status'=>400));
				if($kind==='REVERSAL'){
					if(empty($data['reversal_of_id']))return new WP_Error('leave_reversal_target_required','A reversal must reference the original posted leave event.',array('status'=>400));
					$original=Stockland_DB::read_row($wpdb->prepare("SELECT id,employment_id,status,event_kind FROM %i WHERE id=%d",Stockland_People_Replacement::table('people_leave_events'),(int)$data['reversal_of_id']),ARRAY_A);
					if(!$original||$original['status']!=='POSTED'||(int)$original['employment_id']!==(int)$data['employment_id']||$original['event_kind']==='REVERSAL')return new WP_Error('leave_reversal_source_invalid','The reversal must reference a posted event for the same employment.',array('status'=>400));
				}
			}
		}
		if($e==='individual-payables')$data['tax_classification_status']='LEGAL_REVIEW';
		if($e==='rule-sets')$data['review_status']='LEGAL_REVIEW';
		if($e==='payroll-runs'){$v=$this->full_month($data['period_start'],$data['period_end']);if(is_wp_error($v))return $v;}
		$row=array_merge(array('record_uuid'=>wp_generate_uuid4(),'status'=>'DRAFT','version_no'=>1,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'),'updated_by'=>null,'updated_at'=>null),$data);
		if($e==='people'&&empty($row['person_kind']))$row['person_kind']='UNCLASSIFIED';
		$wpdb->query('START TRANSACTION');
		try {
			if(false===$wpdb->insert(Stockland_People_Replacement::table($s['table']),$row))throw new RuntimeException('Draft insert failed.');
			$id=(int)$wpdb->insert_id;
			if(!Stockland_People_Replacement::event($e,$id,'RECORD_CREATED',null,'DRAFT','Draft created',array('field_names'=>array_keys($data)),isset($row['location_id'])?(int)$row['location_id']:null))throw new RuntimeException('Audit event failed.');
			if(false===$wpdb->query('COMMIT'))throw new RuntimeException('Commit failed.');
			return rest_ensure_response(array('id'=>$id,'entity'=>$e,'status'=>'DRAFT','message'=>'Draft saved; independent approval is a separate action.'));
		}catch(Throwable $x){$wpdb->query('ROLLBACK');return new WP_Error('people_create_failed','Could not save this draft. No success was confirmed.',array('status'=>409));}
	}
	private function update_row($e,$s,$id,$r) {
		global $wpdb;$input=$r->get_json_params();if(!is_array($input)||empty($input['version_no']))return new WP_Error('people_version_required','Include the current version_no for safe concurrent editing.',array('status'=>400));
		$expected=absint($input['version_no']);unset($input['version_no']);$data=$this->clean_fields($input,$s['fields']);if(is_wp_error($data))return $data;if(!$data)return new WP_Error('people_empty_update','Provide editable fields.',array('status'=>400));
		$table=Stockland_People_Replacement::table($s['table']);$wpdb->query('START TRANSACTION');
		try{
			$row=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$table,$id),ARRAY_A);
			if(!$row)throw new RuntimeException('Record not found.');
			if($row['status']!=='DRAFT'||(int)$row['version_no']!==$expected)throw new RuntimeException('Only the current draft version can be edited.');
			if((int)$row['created_by']!==get_current_user_id()&&!Stockland_Authority::can_manage_hr()&&!$this->can_finance())throw new RuntimeException('Only the draft author or authorized manager may edit this draft.');
			$rel=$this->relations($e,$data);if(is_wp_error($rel))throw new RuntimeException($rel->get_error_message());
			$data['version_no']=$expected+1;$data['updated_by']=get_current_user_id();$data['updated_at']=current_time('mysql');
			$n=$wpdb->update($table,$data,array('id'=>$id,'version_no'=>$expected));if(false===$n||0===$n)throw new RuntimeException('Concurrent update detected; refresh and retry.');
			if(!Stockland_People_Replacement::event($e,$id,'DRAFT_UPDATED','DRAFT','DRAFT','Draft updated',array('field_names'=>array_keys($data))))throw new RuntimeException('Audit update failed.');
			if(false===$wpdb->query('COMMIT'))throw new RuntimeException('Commit failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>'DRAFT','version_no'=>$expected+1));
		}catch(Throwable $x){$wpdb->query('ROLLBACK');return new WP_Error('people_update_failed',$x->getMessage(),array('status'=>409));}
	}
	public function decision($r) {
		global $wpdb;$e=sanitize_key((string)$r['entity']);$s=self::specs()[$e]??null;$id=absint($r['id']);$decision=strtoupper(sanitize_key((string)$r->get_param('decision')));$reason=sanitize_textarea_field((string)$r->get_param('reason'));
		if(!$s||!$id)return new WP_Error('people_decision_invalid','Unknown entity or ID.',array('status'=>400));
		$table=Stockland_People_Replacement::table($s['table']);$wpdb->query('START TRANSACTION');
		try{
			$row=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$table,$id),ARRAY_A);if(!$row)throw new RuntimeException('Record not found.');
			if(!$this->in_scope($row))throw new RuntimeException('Record outside your assigned scope.');
			$from=strtoupper((string)$row['status']);$to=$this->transition($e,$from,$decision);if(!$to)throw new RuntimeException('Workflow transition not allowed from current state.');
			if(in_array($decision,array('APPROVE','APPROVE_RULE','ACTIVATE','EFFECTIVE','LEGAL_SIGNOFF'),true)&&(int)$row['created_by']===get_current_user_id())throw new RuntimeException('Self-approval is prohibited.');
			if(in_array($decision,array('REJECT','CANCEL','LEGAL_SIGNOFF'),true)&&strlen(trim($reason))<20)throw new RuntimeException('A specific decision reason of at least 20 characters is required.');
			$extra=array();
			if($e==='applications'&&$decision==='SELECT'){$rat=sanitize_textarea_field((string)$r->get_param('selection_rationale'));if(strlen(trim($rat))<20)throw new RuntimeException('Human selection rationale is required.');$extra['selection_rationale']=$rat;$extra['decision_user_id']=get_current_user_id();$extra['decision_at']=current_time('mysql');}
			if($e==='rule-sets'&&$decision==='LEGAL_SIGNOFF'){
				if(!$this->can_review_legal_rule())throw new RuntimeException('A separately assigned legal reviewer is required.');
				$name=sanitize_text_field((string)$r->get_param('reviewer_name'));$qual=sanitize_text_field((string)$r->get_param('reviewer_qualification'));$ref=sanitize_text_field((string)$r->get_param('review_reference'));
				if(strlen($name)<3||strlen($qual)<5||strlen($ref)<4)throw new RuntimeException('Reviewer identity, qualification and written sign-off reference are required.');
				$extra=array_merge($extra,array('review_status'=>'APPROVED','reviewer_user_id'=>get_current_user_id(),'reviewer_name'=>$name,'reviewer_qualification'=>$qual,'review_reference'=>$ref,'reviewed_at'=>current_time('mysql')));
			}
			if($e==='rule-sets'&&$decision==='APPROVE_RULE'&&(strtoupper((string)$row['review_status'])!=='APPROVED'||empty($row['reviewer_user_id'])||(int)$row['reviewer_user_id']===(int)$row['created_by']))throw new RuntimeException('Independent legal sign-off must precede Owner approval.');
			if($e==='rule-sets'&&$decision==='ACTIVATE'){
				if(!preg_match('/^[a-f0-9]{64}$/i',(string)$row['source_sha256']))throw new RuntimeException('Verified source SHA-256 is required.');
				if($row['rule_type']==='PAYROLL'){$p=json_decode((string)$row['parameters_json'],true);if(!is_array($p)||strtoupper((string)($p['legal_review_gate']??''))!=='SIGNED')throw new RuntimeException('Payroll rule pack lacks signed legal-review gate.');}
			}
			if($e==='individual-payables'&&$decision==='APPROVE'&&strtoupper((string)$row['tax_classification_status'])!=='APPROVED')throw new RuntimeException('Tax classification is unresolved; keep this payable on hold.');
			$update=array_merge(array('status'=>$to,'version_no'=>(int)$row['version_no']+1,'updated_by'=>get_current_user_id(),'updated_at'=>current_time('mysql')),$extra);
			$columns=$this->columns($table);
			if(in_array($decision,array('APPROVE','APPROVE_RULE','ACTIVATE','EFFECTIVE','PUBLISH','LOCK'),true)){if(in_array('approved_by',$columns,true))$update['approved_by']=get_current_user_id();if(in_array('approved_at',$columns,true))$update['approved_at']=current_time('mysql');}
			$n=$wpdb->update($table,$update,array('id'=>$id,'version_no'=>(int)$row['version_no']));if(false===$n||0===$n)throw new RuntimeException('Concurrent workflow decision detected.');
			if(!Stockland_People_Replacement::event($e,$id,$decision,$from,$to,$reason,array('version'=>(int)$row['version_no']+1),isset($row['location_id'])?(int)$row['location_id']:null))throw new RuntimeException('Workflow audit event failed.');
			if(false===$wpdb->query('COMMIT'))throw new RuntimeException('Commit failed.');
			return rest_ensure_response(array('id'=>$id,'entity'=>$e,'from'=>$from,'status'=>$to));
		}catch(Throwable $x){$wpdb->query('ROLLBACK');return new WP_Error('people_decision_failed',$x->getMessage(),array('status'=>409));}
	}
	private function transition($e,$from,$d) {
		$m=array(
			'workforce-plans'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'APPROVE'=>array('SUBMITTED'=>'APPROVED'),'REJECT'=>array('SUBMITTED'=>'REJECTED'),'CLOSE'=>array('APPROVED'=>'CLOSED')),
			'positions'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'APPROVE'=>array('SUBMITTED'=>'APPROVED'),'REJECT'=>array('SUBMITTED'=>'REJECTED'),'PUBLISH'=>array('APPROVED'=>'OPEN'),'CLOSE'=>array('OPEN'=>'CLOSED')),
			'requisitions'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'APPROVE'=>array('SUBMITTED'=>'APPROVED'),'REJECT'=>array('SUBMITTED'=>'REJECTED'),'PUBLISH'=>array('APPROVED'=>'PUBLISHED'),'PAUSE'=>array('PUBLISHED'=>'PAUSED'),'FILL'=>array('PUBLISHED'=>'FILLED')),
			'applications'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'SCREEN'=>array('SUBMITTED'=>'SCREENING'),'INTERVIEW'=>array('SCREENING'=>'INTERVIEW'),'OFFER'=>array('INTERVIEW'=>'OFFER'),'SELECT'=>array('OFFER'=>'SELECTED'),'REJECT'=>array('SUBMITTED'=>'REJECTED','SCREENING'=>'REJECTED','INTERVIEW'=>'REJECTED','OFFER'=>'REJECTED'),'WITHDRAW'=>array('SUBMITTED'=>'WITHDRAWN','SCREENING'=>'WITHDRAWN','INTERVIEW'=>'WITHDRAWN','OFFER'=>'WITHDRAWN')),
			'contracts'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'SIGN'=>array('SUBMITTED'=>'SIGNED'),'APPROVE'=>array('SIGNED'=>'APPROVED'),'ACTIVATE'=>array('APPROVED'=>'ACTIVE'),'CLOSE'=>array('ACTIVE'=>'CLOSED')),
			'tasks'=>array('START'=>array('DRAFT'=>'IN_PROGRESS','OPEN'=>'IN_PROGRESS'),'COMPLETE'=>array('IN_PROGRESS'=>'COMPLETE','BLOCKED'=>'COMPLETE'),'BLOCK'=>array('IN_PROGRESS'=>'BLOCKED')),
			'shifts'=>array('PUBLISH'=>array('DRAFT'=>'PUBLISHED'),'CANCEL'=>array('DRAFT'=>'CANCELLED','PUBLISHED'=>'CANCELLED')),
			'time-entries'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'APPROVE'=>array('SUBMITTED'=>'APPROVED'),'REJECT'=>array('SUBMITTED'=>'REJECTED'),'LOCK'=>array('APPROVED'=>'LOCKED')),
			'leave-events'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'APPROVE'=>array('SUBMITTED'=>'APPROVED'),'REJECT'=>array('SUBMITTED'=>'REJECTED'),'POST'=>array('APPROVED'=>'POSTED'),'CANCEL'=>array('DRAFT'=>'CANCELLED','SUBMITTED'=>'CANCELLED','APPROVED'=>'CANCELLED')),
			'compensation-assignments'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'APPROVE'=>array('SUBMITTED'=>'APPROVED'),'EFFECTIVE'=>array('APPROVED'=>'EFFECTIVE'),'REJECT'=>array('SUBMITTED'=>'REJECTED')),
			'compensation-items'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'APPROVE'=>array('SUBMITTED'=>'APPROVED'),'REJECT'=>array('SUBMITTED'=>'REJECTED')),
			'rule-sets'=>array('SUBMIT'=>array('DRAFT'=>'LEGAL_REVIEW'),'LEGAL_SIGNOFF'=>array('LEGAL_REVIEW'=>'LEGAL_APPROVED'),'APPROVE_RULE'=>array('LEGAL_APPROVED'=>'APPROVED'),'ACTIVATE'=>array('APPROVED'=>'ACTIVE'),'REJECT'=>array('LEGAL_REVIEW'=>'REJECTED','LEGAL_APPROVED'=>'REJECTED')),
			'rule-items'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'APPROVE'=>array('SUBMITTED'=>'APPROVED'),'ACTIVATE'=>array('APPROVED'=>'ACTIVE')),
			'payment-batches'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED','PREPARED'=>'SUBMITTED'),'APPROVE'=>array('SUBMITTED'=>'APPROVED'),'CANCEL'=>array('DRAFT'=>'CANCELLED')),
			'remittances'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'APPROVE'=>array('SUBMITTED'=>'APPROVED'),'CLOSE'=>array('RECONCILED'=>'CLOSED')),
			'exit-cases'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'CLOSE'=>array('SETTLED'=>'CLOSED')),
			'exit-items'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'APPROVE'=>array('SUBMITTED'=>'APPROVED')),
			'individual-payables'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'APPROVE'=>array('SUBMITTED'=>'APPROVED'),'REJECT'=>array('SUBMITTED'=>'REJECTED'),'CLOSE'=>array('RECONCILED'=>'CLOSED')),
			'social-snapshots'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'APPROVE'=>array('SUBMITTED'=>'APPROVED'),'CLOSE'=>array('APPROVED'=>'CLOSED')),
			'resources'=>array('SUBMIT'=>array('DRAFT'=>'SUBMITTED'),'APPROVE'=>array('SUBMITTED'=>'APPROVED'),'REJECT'=>array('SUBMITTED'=>'REJECTED'),'CLOSE'=>array('APPROVED'=>'SUPERSEDED'))
		);
		return $m[$e][$d][$from]??false;
	}
	private function clean_fields($input,$allowed) {
		foreach($input as $k=>$v) if(!array_key_exists($k,$allowed))return new WP_Error('people_field_not_writable','Field not writable: '.sanitize_key((string)$k),array('status'=>400,'field'=>sanitize_key((string)$k)));
		$out=array();
		foreach($input as $k=>$v){
			$t=$allowed[$k];if($v===null||$v===''){$out[$k]=null;continue;}
			if(strpos($t,'enum:')===0){$c=explode(',',substr($t,5));$x=strtoupper(sanitize_key((string)$v));if(!in_array($x,$c,true))return new WP_Error('people_enum_invalid','Invalid controlled choice for '.$k,array('status'=>400));$out[$k]=$x;}
			elseif($t==='int'){if(!is_numeric($v)||(int)$v<0||(string)(int)$v!==(string)$v)return new WP_Error('people_integer_invalid','Invalid non-negative integer for '.$k,array('status'=>400));$out[$k]=(int)$v;}
			elseif($t==='money'){try{$minor=Stockland_Money::minor((string)$v);}catch(Throwable $x){return new WP_Error('people_money_invalid','Invalid money for '.$k,array('status'=>400));}if($minor<0)return new WP_Error('people_money_negative','Negative values require a linked adjustment.',array('status'=>400));$out[$k]=Stockland_Money::decimal($minor);}
			elseif($t==='bool'){if(!in_array($v,array(true,false,0,1,'0','1'),true))return new WP_Error('people_bool_invalid','Invalid boolean for '.$k,array('status'=>400));$out[$k]=(int)(bool)$v;}
			elseif($t==='date'){if(!$this->date($v))return new WP_Error('people_date_invalid','Invalid date for '.$k,array('status'=>400));$out[$k]=sanitize_text_field((string)$v);}
			elseif($t==='datetime'){$x=sanitize_text_field((string)$v);if(!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(?::\d{2})?$/',$x))return new WP_Error('people_datetime_invalid','Invalid datetime for '.$k,array('status'=>400));$out[$k]=str_replace('T',' ',$x);}
			elseif($t==='email'){$x=sanitize_email((string)$v);if(!is_email($x))return new WP_Error('people_email_invalid','Invalid email for '.$k,array('status'=>400));$out[$k]=$x;}
			elseif($t==='sha256'){$x=strtolower(sanitize_text_field((string)$v));if(!preg_match('/^[a-f0-9]{64}$/',$x))return new WP_Error('people_hash_invalid','SHA-256 required for '.$k,array('status'=>400));$out[$k]=$x;}
			elseif($t==='json'){if(!is_array($v)&&!is_object($v)){$d=json_decode((string)$v,true);if(JSON_ERROR_NONE!==json_last_error()||!is_array($d))return new WP_Error('people_json_invalid','Valid JSON required for '.$k,array('status'=>400));$v=$d;}$out[$k]=wp_json_encode($v);}
			elseif($t==='private_ref'){$x=sanitize_text_field((string)$v);if(strlen($x)>190||preg_match('~https?://|(^|/)\.\.(?:/|$)~i',$x))return new WP_Error('people_private_ref_invalid','Use an opaque vault reference, not a URL/path.',array('status'=>400));$out[$k]=$x;}
			elseif($t==='source_url'){$x=esc_url_raw((string)$v,array('https'));if(!$x||strpos($x,'https://')!==0)return new WP_Error('people_source_url_invalid','HTTPS source URL required.',array('status'=>400));$out[$k]=$x;}
			elseif($t==='text')$out[$k]=sanitize_text_field((string)$v);
			else $out[$k]=sanitize_textarea_field((string)$v);
			if(is_string($out[$k])&&strlen($out[$k])>10000)return new WP_Error('people_field_too_large','Field too long.',array('status'=>400));
		}
		return $out;
	}
	private function relations($e,$d) {
		$map=array('employments'=>array('person_id'=>'people_people'),'applications'=>array('person_id'=>'people_people','requisition_id'=>'people_requisitions'),'contracts'=>array('employment_id'=>'people_employments','document_id'=>'people_documents'),'tasks'=>array('evidence_document_id'=>'people_documents'),'shifts'=>array('employment_id'=>'people_employments'),'time-entries'=>array('employment_id'=>'people_employments','shift_id'=>'people_shifts','correction_of_id'=>'people_time_entries'),'leave-events'=>array('employment_id'=>'people_employments','evidence_document_id'=>'people_documents','reversal_of_id'=>'people_leave_events'),'compensation-assignments'=>array('employment_id'=>'people_employments','supersedes_assignment_id'=>'people_compensation_assignments'),'compensation-items'=>array('assignment_id'=>'people_compensation_assignments','rule_item_id'=>'people_rule_items','evidence_document_id'=>'people_documents'),'rule-items'=>array('rule_set_id'=>'people_rule_sets'),'payment-items'=>array('batch_id'=>'people_payment_batches'),'remittances'=>array('rule_set_id'=>'people_rule_sets'),'exit-cases'=>array('employment_id'=>'people_employments'),'exit-items'=>array('exit_case_id'=>'people_exit_cases','rule_set_id'=>'people_rule_sets'),'individual-payables'=>array('person_id'=>'people_people','supporting_document_id'=>'people_documents'));
		foreach($map[$e]??array() as $field=>$t){if(empty($d[$field]))continue;$found=Stockland_DB::read_var($GLOBALS['wpdb']->prepare('SELECT id FROM %i WHERE id=%d',Stockland_People_Replacement::table($t),(int)$d[$field]));if(!$found)return new WP_Error('people_reference_missing','Referenced record missing: '.$field,array('status'=>400));}
		if(isset($d['location_id'])&&$d['location_id']&&!Stockland_Authority::can_access_location((int)$d['location_id']))return new WP_Error('people_location_forbidden','Location outside assigned scope.',array('status'=>403));
		return true;
	}
	private function full_month($start,$end) {
		if(!$this->date($start)||!$this->date($end)||$end<$start||substr($start,8,2)!=='01'||substr($start,0,7)!==substr($end,0,7)||date('Y-m-t',strtotime($start))!==$end)return new WP_Error('people_period_invalid','Payroll must cover one complete calendar month; corrections use linked adjustment runs.',array('status'=>400));
		return true;
	}
	private function date($v) { $v=(string)$v;return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$v,$m)&&checkdate((int)$m[2],(int)$m[3],(int)$m[1]); }
	private function safe_column($name) { if(!preg_match('/^[a-z_][a-z0-9_]*$/',(string)$name))throw new InvalidArgumentException('Invalid allow-listed column.');return $name; }
	private function columns($table) { global $wpdb;$rows=$wpdb->get_results($wpdb->prepare('SHOW COLUMNS FROM %i',$table),ARRAY_A);return array_map(static function($r){return $r['Field'];},(array)$rows); }
	private function in_scope($row) {
		$loc=(int)($row['location_id']??0);
		if(!$loc)foreach(array('employment_id','subject_id') as $key)if(!empty($row[$key])){$x=Stockland_DB::read_row($GLOBALS['wpdb']->prepare('SELECT location_id FROM %i WHERE id=%d',Stockland_People_Replacement::table('people_employments'),(int)$row[$key]),ARRAY_A);if($x&&!empty($x['location_id'])){$loc=(int)$x['location_id'];break;}}
		if(!$loc)return Stockland_Authority::can_view_hr()||Stockland_Authority::can_view_payroll()||$this->can_finance();
		return Stockland_Authority::can_access_location($loc);
	}
	private function redact($row) {
		if(Stockland_Authority::can_manage_hr()||$this->can_finance())return $row;
		foreach(array('contact_email','contact_phone','identity_reference_key','tin_reference_key','license_reference_key','parameters_json','source_url','clearance_json','calculation_trace_json','input_snapshot_json','interview_score_json') as $k)unset($row[$k]);
		foreach(array('notes_redacted','reason_redacted','screening_summary','selection_rationale') as $k)if(array_key_exists($k,$row))$row[$k]='Restricted';
		return $row;
	}
	private function columns_are($table,$names) { return count(array_intersect($names,$this->columns($table)))===count($names); }

	public function convert_application($r) {
		global $wpdb;$id=absint($r['id']);$body=$r->get_json_params();$code=sanitize_text_field((string)($body['employee_code']??''));$start=(string)($body['start_date']??'');$dept=sanitize_text_field((string)($body['department']??''));$basis=strtoupper(sanitize_key((string)($body['contract_basis']??'')));$schedule=strtoupper(sanitize_key((string)($body['work_schedule']??'')));$loc=absint($body['location_id']??0);
		if(!$code||!$this->date($start)||!$dept||!in_array($basis,array('INDEFINITE','FIXED_TERM','SPECIFIC_TASK','PART_TIME','OTHER_APPROVED'),true)||!in_array($schedule,array('FULL_TIME','PART_TIME','SHIFT','OTHER_APPROVED'),true))return new WP_Error('people_conversion_invalid','Employee code, valid start date, department, contract basis and schedule are required.',array('status'=>400));
		if($loc&&!Stockland_Authority::can_access_location($loc))return new WP_Error('people_location_forbidden','Location outside scope.',array('status'=>403));
		$wpdb->query('START TRANSACTION');
		try{$app=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_People_Replacement::table('people_applications'),$id),ARRAY_A);if(!$app||$app['status']!=='SELECTED')throw new RuntimeException('Only a human-selected application can become a pending hire.');
			$person=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_People_Replacement::table('people_people'),(int)$app['person_id']),ARRAY_A);if(!$person)throw new RuntimeException('Canonical person is missing.');
			if(Stockland_DB::read_var($wpdb->prepare('SELECT id FROM %i WHERE employee_code=%s',Stockland_People_Replacement::table('people_employments'),$code)))throw new RuntimeException('Employee code already exists.');
			$now=current_time('mysql');$actor=get_current_user_id();
			$wpdb->insert(Stockland_People_Replacement::table('people_employments'),array('record_uuid'=>wp_generate_uuid4(),'status'=>'PENDING','version_no'=>1,'created_by'=>$actor,'created_at'=>$now,'person_id'=>(int)$person['id'],'employee_code'=>$code,'legal_employer'=>'STOCKLAND ETHIOPIA','department'=>$dept,'location_id'=>$loc?:null,'contract_basis'=>$basis,'work_schedule'=>$schedule,'start_date'=>$start,'lifecycle_status'=>'PRE_HIRE'));
			if(!$wpdb->insert_id||$wpdb->last_error)throw new RuntimeException('Pending employment insert failed.');$emp=(int)$wpdb->insert_id;
			foreach(array('CONTRACT_SIGN','PERSONNEL_DOCS','PAYROLL_SETUP','INDUCTION') as $task){$wpdb->insert(Stockland_People_Replacement::table('people_tasks'),array('record_uuid'=>wp_generate_uuid4(),'status'=>'OPEN','version_no'=>1,'created_by'=>$actor,'created_at'=>$now,'task_code'=>'ONB-'.$emp.'-'.$task,'task_type'=>$task,'subject_type'=>'EMPLOYMENT','subject_id'=>$emp,'reason'=>'Generated from approved application '.$id));if($wpdb->last_error)throw new RuntimeException('Onboarding task insert failed.');}
			$wpdb->update(Stockland_People_Replacement::table('people_people'),array('person_kind'=>'EMPLOYEE','status'=>'PENDING','updated_by'=>$actor,'updated_at'=>$now,'version_no'=>(int)$person['version_no']+1),array('id'=>(int)$person['id'],'version_no'=>(int)$person['version_no']));if(!$wpdb->rows_affected)throw new RuntimeException('Person changed concurrently.');
			if(!Stockland_People_Replacement::event('application',$id,'CONVERTED_TO_PENDING_HIRE','SELECTED','PENDING','Candidate selected and converted',array('employment_id'=>$emp),$loc?:null))throw new RuntimeException('Conversion event failed.');
			if(false===$wpdb->query('COMMIT'))throw new RuntimeException('Commit failed.');
			return rest_ensure_response(array('application_id'=>$id,'employment_id'=>$emp,'person_id'=>(int)$person['id'],'status'=>'PENDING','onboarding_tasks'=>4,'message'=>'Pending hire created; contract, document, compensation and separate activation gates remain.'));
		}catch(Throwable $x){$wpdb->query('ROLLBACK');return new WP_Error('people_conversion_failed',$x->getMessage(),array('status'=>409));}
	}
	public function activate_employment($r) {
		global $wpdb;$id=absint($r['id']);$b=$r->get_json_params();$reason=sanitize_textarea_field((string)($b['reason']??''));if(strlen(trim($reason))<20)return new WP_Error('people_activation_reason','Independent activation rationale of at least 20 characters required.',array('status'=>400));$wpdb->query('START TRANSACTION');
		try{$table=Stockland_People_Replacement::table('people_employments');$e=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$table,$id),ARRAY_A);if(!$e||$e['lifecycle_status']!=='PRE_HIRE')throw new RuntimeException('Only a pre-hire employment can be activated.');if((int)$e['created_by']===get_current_user_id())throw new RuntimeException('Hire preparer cannot approve own activation.');
			$c=Stockland_DB::read_results($wpdb->prepare("SELECT id FROM %i WHERE employment_id=%d AND status='APPROVED' AND signed_at IS NOT NULL",Stockland_People_Replacement::table('people_contracts'),$id),ARRAY_A);if(!is_array($c)||count($c)!==1)throw new RuntimeException('Exactly one signed approved contract is required.');
			$t=(int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE subject_type='EMPLOYMENT' AND subject_id=%d AND task_type IN ('PERSONNEL_DOCS','PAYROLL_SETUP','INDUCTION') AND status<>'COMPLETE'",Stockland_People_Replacement::table('people_tasks'),$id));if($t>0)throw new RuntimeException('Required onboarding tasks are incomplete.');
			$n=(int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE employment_id=%d AND status='EFFECTIVE' AND approved_by IS NOT NULL AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s)",Stockland_People_Replacement::table('people_compensation_assignments'),$id,$e['start_date'],$e['start_date']));if($n!==1)throw new RuntimeException('Exactly one effective approved compensation assignment is required.');
			$upd=$wpdb->update($table,array('lifecycle_status'=>'ACTIVE','status'=>'ACTIVE','version_no'=>(int)$e['version_no']+1,'updated_by'=>get_current_user_id(),'updated_at'=>current_time('mysql')),array('id'=>$id,'version_no'=>(int)$e['version_no']));if(false===$upd||$upd===0)throw new RuntimeException('Concurrent employment update.');
			if(!Stockland_People_Replacement::event('employment',$id,'HIRE_ACTIVATED','PRE_HIRE','ACTIVE',$reason,array('contract_id'=>(int)$c[0]['id']),(int)$e['location_id']?:null))throw new RuntimeException('Audit event failed.');if(false===$wpdb->query('COMMIT'))throw new RuntimeException('Commit failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>'ACTIVE','message'=>'Activated only after signed contract, completed tasks and approved compensation.'));
		}catch(Throwable $x){$wpdb->query('ROLLBACK');return new WP_Error('people_activation_failed',$x->getMessage(),array('status'=>409));}
	}
	public function calculate_payroll($r) { if(!$this->ready())return new WP_REST_Response(array('ready'=>false,'schema'=>Stockland_People_Replacement::schema_status()),503);if(!class_exists('Stockland_People_Payroll_Engine'))return new WP_Error('people_payroll_engine_missing','No payroll engine is loaded; legacy calculation fallback is prohibited.',array('status'=>503));try{return rest_ensure_response(Stockland_People_Payroll_Engine::calculate_run(absint($r['id'])));}catch(Throwable $x){Stockland_Runtime_Diagnostics::log_error('People payroll blocked: '.$x->getMessage());return new WP_Error('people_payroll_blocked',$x->getMessage(),array('status'=>409));} }
	public function submit_payroll($r) { return $this->payroll_state(absint($r['id']),'SUBMIT'); }
	public function approve_payroll($r) { return $this->payroll_state(absint($r['id']),'APPROVE'); }
	private function payroll_state($id,$action) {
		global $wpdb;$t=Stockland_People_Replacement::table('people_payroll_runs');$wpdb->query('START TRANSACTION');
		try{$run=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$t,$id),ARRAY_A);if(!$run)throw new RuntimeException('Payroll run not found.');
			if($action==='SUBMIT'){if($run['status']!=='CALCULATED'||(int)$run['exception_count']>0)throw new RuntimeException('Only zero-exception calculated payroll can be submitted.');$to='SUBMITTED';$data=array('submitted_by'=>get_current_user_id(),'submitted_at'=>current_time('mysql'));}
			else{if($run['status']!=='SUBMITTED')throw new RuntimeException('Only submitted payroll can be approved.');if((int)$run['submitted_by']===get_current_user_id()||(int)$run['created_by']===get_current_user_id())throw new RuntimeException('Preparer cannot approve own payroll.');if(empty($run['input_snapshot_hash'])||(int)$run['exception_count']>0)throw new RuntimeException('Snapshot or exception gate failed.');if((int)Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE run_id=%d AND status='HELD'",Stockland_People_Replacement::table('people_payroll_lines'),$id))>0)throw new RuntimeException('Held payroll lines block approval.');$to='APPROVED';$data=array('approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'));}
			$data=array_merge($data,array('status'=>$to,'version_no'=>(int)$run['version_no']+1,'updated_by'=>get_current_user_id(),'updated_at'=>current_time('mysql')));$n=$wpdb->update($t,$data,array('id'=>$id,'version_no'=>(int)$run['version_no']));if(false===$n||$n===0)throw new RuntimeException('Concurrent payroll decision.');
			if(!Stockland_People_Replacement::event('payroll-run',''.$id,'PAYROLL_'.$action,$run['status'],$to,'',array('snapshot_hash'=>$run['input_snapshot_hash'])))throw new RuntimeException('Audit event failed.');if(false===$wpdb->query('COMMIT'))throw new RuntimeException('Commit failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>$to));
		}catch(Throwable $x){$wpdb->query('ROLLBACK');return new WP_Error('people_payroll_transition_failed',$x->getMessage(),array('status'=>409));}
	}
	public function post_payroll($r) {
		global $wpdb; $id=absint($r['id']); $t=Stockland_People_Replacement::table('people_payroll_runs'); $wpdb->query('START TRANSACTION');
		try {
			$run=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$t,$id),ARRAY_A);
			if(!$run||$run['status']!=='APPROVED')throw new RuntimeException('Only Owner-approved payroll can be posted.');
			if((int)$run['created_by']===(int)$run['approved_by']||(int)$run['submitted_by']===(int)$run['approved_by']||empty($run['input_snapshot_hash'])||(int)$run['exception_count']>0)throw new RuntimeException('Independent approval, frozen snapshot, or exception gate failed.');
			$ids=json_decode((string)$run['rule_set_ids_json'],true);$rule_id=is_array($ids)?absint($ids[0]??0):0;
			$rule=$rule_id?Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND rule_type='PAYROLL' AND status='ACTIVE' AND review_status='APPROVED'",Stockland_People_Replacement::table('people_rule_sets'),$rule_id),ARRAY_A):null;
			if(!$rule||empty($rule['reviewer_user_id'])||(int)$rule['created_by']===(int)$rule['reviewer_user_id']||(int)$rule['approved_by']===(int)$rule['reviewer_user_id'])throw new RuntimeException('Current rule set lacks independent legal review and approval.');
			$p=json_decode((string)$rule['parameters_json'],true);$m=is_array($p)?($p['gl_mapping']??array()):array();
			$keys=array('gross_expense','employer_pension_expense','net_payable','paye_payable','employee_pension_payable','employer_pension_payable','other_deductions_payable');
			foreach($keys as $k)if(empty($m[$k])||!is_string($m[$k]))throw new RuntimeException('Finance-approved GL mapping is missing: '.$k);
			$journal_lines=array(
				array('account_code'=>$m['gross_expense'],'debit'=>$run['gross_total'],'credit'=>'0.00','memo'=>'Gross payroll expense'),
				array('account_code'=>$m['employer_pension_expense'],'debit'=>$run['employer_pension_total'],'credit'=>'0.00','memo'=>'Employer pension expense'),
				array('account_code'=>$m['paye_payable'],'debit'=>'0.00','credit'=>$run['paye_total'],'memo'=>'PAYE payable'),
				array('account_code'=>$m['employee_pension_payable'],'debit'=>'0.00','credit'=>$run['employee_pension_total'],'memo'=>'Employee pension payable'),
				array('account_code'=>$m['employer_pension_payable'],'debit'=>'0.00','credit'=>$run['employer_pension_total'],'memo'=>'Employer pension payable'),
				array('account_code'=>$m['net_payable'],'debit'=>'0.00','credit'=>$run['net_total'],'memo'=>'Net wages payable'),
				array('account_code'=>$m['other_deductions_payable'],'debit'=>'0.00','credit'=>$run['deduction_total'],'memo'=>'Other approved deductions payable')
			);
			$jid=Stockland_Accounting::post_journal($run['period_end'],'PEOPLE_PAYROLL_RUN',$id,'People payroll '.$run['run_code'],$journal_lines,get_current_user_id());
			if(!$jid)throw new RuntimeException('Shared GL service rejected posting; verify mappings, period locks and reconciliation.');
			$n=$wpdb->update($t,array('status'=>'POSTED','posted_journal_id'=>(int)$jid,'posted_at'=>current_time('mysql'),'version_no'=>(int)$run['version_no']+1,'updated_by'=>get_current_user_id(),'updated_at'=>current_time('mysql')),array('id'=>$id,'version_no'=>(int)$run['version_no']));
			if(false===$n||$n===0)throw new RuntimeException('Concurrent payroll posting or missing journal link.');
			if(!Stockland_People_Replacement::event('payroll-run',$id,'PAYROLL_POSTED','APPROVED','POSTED','Shared GL journal posted',array('journal_id'=>(int)$jid,'snapshot_hash'=>$run['input_snapshot_hash'])))throw new RuntimeException('Payroll posting audit event failed.');
			if(false===$wpdb->query('COMMIT'))throw new RuntimeException('Posting transaction commit failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>'POSTED','journal_id'=>(int)$jid,'message'=>'Payroll posted through the shared GL service. Payment and statutory remittance remain separate workflows.'));
		}catch(Throwable $x){$wpdb->query('ROLLBACK');return new WP_Error('people_payroll_post_failed',$x->getMessage(),array('status'=>409));}
	}
	public function create_payment_batch($r) {
		global $wpdb; $id=absint($r['id']); $b=$r->get_json_params(); $method=strtoupper(sanitize_key((string)($b['payment_method']??''))); $idem=sanitize_text_field((string)($b['idempotency_key']??'')); $reference=sanitize_text_field((string)($b['external_batch_reference']??''));
		if(!in_array($method,array('BANK_TRANSFER','ACCOUNT_PAYEE_CHEQUE','APPROVED_OTHER'),true)||strlen($idem)<12||strlen($reference)<4)return new WP_Error('people_payment_input','Payment method, external batch reference and a unique idempotency key of at least 12 characters are required.',array('status'=>400));
		$wpdb->query('START TRANSACTION');
		try {
			$run=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_People_Replacement::table('people_payroll_runs'),$id),ARRAY_A);
			if(!$run||$run['status']!=='POSTED')throw new RuntimeException('Only a posted payroll run can create payment instructions.');
			$table=Stockland_People_Replacement::table('people_payment_batches');
			$old=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE idempotency_key=%s',$table,$idem),ARRAY_A);
			if($old){if($old['source_type']==='PAYROLL_RUN'&&(int)$old['source_id']===$id){$wpdb->query('ROLLBACK');return rest_ensure_response(array('id'=>(int)$old['id'],'status'=>$old['status'],'idempotent_replay'=>true));}throw new RuntimeException('Idempotency key belongs to another source.');}
			$existing=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE source_type='PAYROLL_RUN' AND source_id=%d",$table,$id),ARRAY_A);
			if($existing)throw new RuntimeException('A payment batch already exists for this payroll run. Use the existing batch and reconcile its recipient items.');
			$lines=Stockland_DB::read_results($wpdb->prepare("SELECT * FROM %i WHERE run_id=%d AND status='CALCULATED' ORDER BY id",Stockland_People_Replacement::table('people_payroll_lines'),$id),ARRAY_A);
			if(!is_array($lines)||!$lines)throw new RuntimeException('No calculated recipient lines were found.');
			foreach($lines as $line)if($line['net_amount']===null||Stockland_Money::minor($line['net_amount'])<0)throw new RuntimeException('Invalid recipient net amount blocks payment batch.');
			$now=current_time('mysql');$code='PB-'.gmdate('Ymd').'-'.strtoupper(wp_generate_password(8,false,false));
			$wpdb->insert($table,array('record_uuid'=>wp_generate_uuid4(),'status'=>'PREPARED','version_no'=>1,'created_by'=>get_current_user_id(),'created_at'=>$now,'batch_code'=>$code,'payment_purpose'=>'PAYROLL','source_type'=>'PAYROLL_RUN','source_id'=>$id,'total_amount'=>$run['net_total'],'currency'=>'ETB','payment_method'=>$method,'external_batch_reference'=>$reference,'idempotency_key'=>$idem));
			if(!$wpdb->insert_id||$wpdb->last_error)throw new RuntimeException('Payment batch insert failed.');
			$batch=(int)$wpdb->insert_id;
			foreach($lines as $line){
				$wpdb->insert(Stockland_People_Replacement::table('people_payment_items'),array('record_uuid'=>wp_generate_uuid4(),'status'=>'PENDING','version_no'=>1,'created_by'=>get_current_user_id(),'created_at'=>$now,'batch_id'=>$batch,'payment_item_code'=>'PI-'.$batch.'-'.$line['id'],'payee_type'=>'EMPLOYMENT','payee_id'=>(int)$line['employment_id'],'amount'=>$line['net_amount']));
				if(!$wpdb->insert_id||$wpdb->last_error)throw new RuntimeException('Recipient payment item insert failed.');
			}
			if(!Stockland_People_Replacement::event('payment-batch',$batch,'PAYMENT_BATCH_PREPARED',null,'PREPARED','',array('run_id'=>$id,'recipient_count'=>count($lines),'external_batch_reference_recorded'=>true)))throw new RuntimeException('Payment batch audit event failed.');
			if(false===$wpdb->query('COMMIT'))throw new RuntimeException('Payment batch commit failed.');
			return rest_ensure_response(array('id'=>$batch,'batch_code'=>$code,'status'=>'PREPARED','recipients'=>count($lines),'paid'=>false,'message'=>'Payment instructions prepared; external recipient confirmation is still required.'));
		}catch(Throwable $x){$wpdb->query('ROLLBACK');return new WP_Error('people_payment_batch_failed',$x->getMessage(),array('status'=>409));}
	}
	public function update_payment_result($r) {
		global $wpdb; $id=absint($r['id']); $b=$r->get_json_params(); $outcome=strtoupper(sanitize_key((string)($b['outcome']??''))); $ref=sanitize_text_field((string)($b['external_payment_reference']??'')); $reason=sanitize_text_field((string)($b['failure_reason']??''));
		if(!in_array($outcome,array('CONFIRMED','FAILED','RETURNED','REVERSED'),true)||($outcome==='CONFIRMED'&&strlen($ref)<4)||($outcome==='FAILED'&&strlen($reason)<5))return new WP_Error('people_payment_result_invalid','Confirmed payment requires external reference; failed payment requires a reason.',array('status'=>400));
		$wpdb->query('START TRANSACTION');
		try{
			$t=Stockland_People_Replacement::table('people_payment_items');$item=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$t,$id),ARRAY_A);if(!$item)throw new RuntimeException('Payment item not found.');
			if(in_array($item['status'],array('CONFIRMED','RECONCILED'),true))throw new RuntimeException('Confirmed payments are immutable. Use a separately linked reversal/return record.');
			if($outcome==='CONFIRMED'){$dupe=Stockland_DB::read_var($wpdb->prepare('SELECT id FROM %i WHERE external_payment_reference=%s AND id<>%d',$t,$ref,$id));if($dupe)throw new RuntimeException('External payment reference already belongs to another recipient.');}
			$n=$wpdb->update($t,array('status'=>$outcome,'external_payment_reference'=>$ref?:null,'paid_at'=>$outcome==='CONFIRMED'?current_time('mysql'):null,'failure_reason'=>$reason?:null,'version_no'=>(int)$item['version_no']+1,'updated_by'=>get_current_user_id(),'updated_at'=>current_time('mysql')),array('id'=>$id,'version_no'=>(int)$item['version_no']));if(false===$n||$n===0)throw new RuntimeException('Payment result changed concurrently.');
			if(!Stockland_People_Replacement::event('payment-item',$id,'PAYMENT_'.$outcome,$item['status'],$outcome,$reason,array('external_reference_recorded'=>(bool)$ref)))throw new RuntimeException('Payment audit event failed.');
			$batch=(int)$item['batch_id'];$counts=Stockland_DB::read_row($wpdb->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status='CONFIRMED' THEN 1 ELSE 0 END) AS confirmed, SUM(CASE WHEN status IN ('FAILED','RETURNED','REVERSED') THEN 1 ELSE 0 END) AS failed FROM %i WHERE batch_id=%d",$t,$batch),ARRAY_A);
			if(!$counts||(int)$counts['total']===0)throw new RuntimeException('Payment batch recipient items are missing.');
			$state=(int)$counts['confirmed']===(int)$counts['total']?'CONFIRMED':((int)$counts['confirmed']>0|| (int)$counts['failed']>0?'PARTIAL':'SUBMITTED');
			$wpdb->update(Stockland_People_Replacement::table('people_payment_batches'),array('status'=>$state,'version_no'=>1,'updated_by'=>get_current_user_id(),'updated_at'=>current_time('mysql')),array('id'=>$batch));
			if(false===$wpdb->query('COMMIT'))throw new RuntimeException('Payment result commit failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>$outcome,'batch_status'=>$state,'message'=>'Recipient payment outcome recorded. Batch reconciliation and statutory remittance remain separate.'));
		}catch(Throwable $x){$wpdb->query('ROLLBACK');return new WP_Error('people_payment_result_failed',$x->getMessage(),array('status'=>409));}
	}
	public function calculate_exit($r) {
		if(!$this->ready())return new WP_REST_Response(array('ready'=>false,'schema'=>Stockland_People_Replacement::schema_status()),503);
		if(!class_exists('Stockland_People_Exit_Engine'))return new WP_Error('people_exit_engine_missing','The replacement exit engine is not loaded.',array('status'=>503));
		try{return rest_ensure_response(Stockland_People_Exit_Engine::calculate_case(absint($r['id'])));}
		catch(Throwable $x){Stockland_Runtime_Diagnostics::log_error('People exit calculation blocked: '.$x->getMessage());return new WP_Error('people_exit_calculation_blocked',$x->getMessage(),array('status'=>409));}
	}
	public function approve_exit($r) {
		global $wpdb;$id=absint($r['id']);$b=$r->get_json_params();$reason=sanitize_textarea_field((string)($b['reason']??''));
		if(strlen(trim($reason))<20)return new WP_Error('people_exit_approval_reason','Independent Owner settlement review and rationale of at least 20 characters are required.',array('status'=>400));
		$t=Stockland_People_Replacement::table('people_exit_cases');$wpdb->query('START TRANSACTION');
		try{
			$case=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$t,$id),ARRAY_A);
			if(!$case||$case['status']!=='CALCULATED')throw new RuntimeException('Only a calculated final-settlement case may be approved.');
			if((int)$case['created_by']===get_current_user_id())throw new RuntimeException('Settlement preparer cannot approve their own settlement.');
			$items=Stockland_DB::read_results($wpdb->prepare("SELECT * FROM %i WHERE exit_case_id=%d AND status='CALCULATED' ORDER BY id",Stockland_People_Replacement::table('people_exit_items'),$id),ARRAY_A);
			if(!is_array($items)||!$items)throw new RuntimeException('Itemized settlement evidence is missing.');
			foreach($items as $item){
				$rule=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND rule_type='EXIT' AND status='ACTIVE' AND review_status='APPROVED'",Stockland_People_Replacement::table('people_rule_sets'),(int)$item['rule_set_id']),ARRAY_A);
				if(!$rule||empty($rule['reviewer_user_id'])||(int)$rule['reviewer_user_id']===(int)$rule['created_by']||(int)$rule['reviewer_user_id']===(int)$rule['approved_by'])throw new RuntimeException('Settlement rule is no longer independently reviewed and approved.');
			}
			$n=$wpdb->update($t,array('status'=>'APPROVED','approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'),'version_no'=>(int)$case['version_no']+1,'updated_by'=>get_current_user_id(),'updated_at'=>current_time('mysql')),array('id'=>$id,'version_no'=>(int)$case['version_no']));
			if(false===$n||$n===0)throw new RuntimeException('Exit case changed concurrently.');
			if(!Stockland_People_Replacement::event('exit-case',$id,'SETTLEMENT_APPROVED','CALCULATED','APPROVED',$reason,array('item_count'=>count($items))))throw new RuntimeException('Settlement approval audit event failed.');
			if(false===$wpdb->query('COMMIT'))throw new RuntimeException('Settlement approval transaction failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>'APPROVED','message'=>'Final settlement approved. Payment instructions, external confirmation, remittance and reconciliation remain separate.'));
		}catch(Throwable $x){$wpdb->query('ROLLBACK');return new WP_Error('people_exit_approval_failed',$x->getMessage(),array('status'=>409));}
	}
}