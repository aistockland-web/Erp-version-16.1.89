<?php
/**
 * Source-backed candidate APIs for frozen payroll runs, recipient settlement,
 * remittances, non-employee payees, private HR resources and manual social reports.
 * No live posting or payment is authorized until rule, approval and service gates pass.
 *
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Stockland_REST_HR_Payroll_Cycle extends Stockland_REST_Base {
	private function bad( $code, $message, $status = 409 ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
	private function table( $key ) { return Stockland_DB::table( $key ); }
	private function is_owner() { return Stockland_Authority::can_approve_payroll(); }
	private function can_finance() { return Stockland_Authority::can_manage_payroll() || $this->is_owner(); }
	private function date_value( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value, $m ) || ! checkdate( (int) substr( $value, 5, 2 ), (int) substr( $value, 8, 2 ), (int) substr( $value, 0, 4 ) ) ) { return false; }
		return $value;
	}
	private function event( $entity, $id, $type, $from, $to, $meta = array(), $rule_id = null, $version = null ) {
		return Stockland_HR_Lifecycle::write_event( $entity, (int) $id, $type, $from, $to, $meta, $rule_id, $version );
	}
	private function row( $key, $id, $lock = false ) {
		global $wpdb;
		return Stockland_DB::read_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d' . ( $lock ? ' FOR UPDATE' : '' ), $this->table( $key ), (int) $id ), ARRAY_A );
	}
	private function json( $value ) {
		$out = wp_json_encode( $value );
		return is_string( $out ) ? $out : '';
	}
	private function safe_run( $r ) {
		if ( ! is_array( $r ) ) { return array(); }
		unset( $r['rules_json'], $r['tin_ciphertext'], $r['license_ciphertext'], $r['payload_json'] );
		return $r;
	}

	public function __construct() { add_action( 'stockland_register_rest_routes', array( $this, 'register_routes' ) ); }

	public function register_routes() {
		$ns = self::NAMESPACE_V1;
		register_rest_route( $ns, '/hr/payroll-runs', array(
			array( 'methods'=>'GET', 'callback'=>array( $this, 'list_runs' ), 'permission_callback'=>function(){ return Stockland_Authority::can_view_payroll(); } ),
			array( 'methods'=>'POST', 'callback'=>array( $this, 'create_run' ), 'permission_callback'=>function(){ return Stockland_Authority::can_manage_payroll(); } ),
		) );
		register_rest_route( $ns, '/hr/payroll-runs/(?P<id>\d+)/generate', array( 'methods'=>'POST', 'callback'=>array( $this, 'generate_run' ), 'permission_callback'=>function(){ return Stockland_Authority::can_manage_payroll(); } ) );
		register_rest_route( $ns, '/hr/payroll-runs/(?P<id>\d+)/submit', array( 'methods'=>'POST', 'callback'=>array( $this, 'submit_run' ), 'permission_callback'=>function(){ return Stockland_Authority::can_manage_payroll(); } ) );
		register_rest_route( $ns, '/hr/payroll-runs/(?P<id>\d+)/approve', array( 'methods'=>'POST', 'callback'=>array( $this, 'approve_run' ), 'permission_callback'=>function(){ return Stockland_Authority::can_approve_payroll(); } ) );
		register_rest_route( $ns, '/hr/payroll-runs/(?P<id>\d+)/payment-batches', array( 'methods'=>'POST', 'callback'=>array( $this, 'create_payment_batch' ), 'permission_callback'=>function(){ return $this->can_finance(); } ) );
		register_rest_route( $ns, '/hr/payroll-payment-batches/(?P<id>\d+)/items/(?P<item_id>\d+)/result', array( 'methods'=>'POST', 'callback'=>array( $this, 'record_payment_result' ), 'permission_callback'=>function(){ return $this->can_finance(); } ) );
		register_rest_route( $ns, '/hr/payroll-payment-batches/(?P<id>\d+)/reconcile', array( 'methods'=>'POST', 'callback'=>array( $this, 'reconcile_payment_batch' ), 'permission_callback'=>function(){ return Stockland_Authority::can_approve_payroll(); } ) );
		register_rest_route( $ns, '/hr/statutory-remittances/(?P<id>\d+)/reconcile', array( 'methods'=>'POST', 'callback'=>array( $this, 'reconcile_remittance' ), 'permission_callback'=>function(){ return $this->can_finance(); } ) );
		register_rest_route( $ns, '/hr/employee-reimbursements', array( array( 'methods'=>'GET', 'callback'=>array( $this, 'list_reimbursements' ), 'permission_callback'=>function(){ return Stockland_Authority::can_view_hr(); } ), array( 'methods'=>'POST', 'callback'=>array( $this, 'create_reimbursement' ), 'permission_callback'=>function(){ return Stockland_Authority::can_manage_hr() || Stockland_Authority::can_view_hr(); } ) ) );
		register_rest_route( $ns, '/hr/employee-reimbursements/(?P<id>\d+)/approve', array( 'methods'=>'POST', 'callback'=>array( $this, 'approve_reimbursement' ), 'permission_callback'=>function(){ return Stockland_Authority::can_approve_payroll(); } ) );
		register_rest_route( $ns, '/hr/employee-reimbursements/(?P<id>\d+)/pay', array( 'methods'=>'POST', 'callback'=>array( $this, 'pay_reimbursement' ), 'permission_callback'=>function(){ return Stockland_Authority::can_approve_payroll(); } ) );

		register_rest_route( $ns, '/hr/individual-payees', array(
			array( 'methods'=>'GET', 'callback'=>array( $this, 'list_payees' ), 'permission_callback'=>function(){ return Stockland_Authority::can_view_hr(); } ),
			array( 'methods'=>'POST', 'callback'=>array( $this, 'create_payee' ), 'permission_callback'=>function(){ return Stockland_Authority::can_manage_hr(); } ),
		) );
		register_rest_route( $ns, '/hr/individual-payees/(?P<id>\d+)/approve', array( 'methods'=>'POST', 'callback'=>array( $this, 'approve_payee' ), 'permission_callback'=>function(){ return Stockland_Authority::can_approve_payroll(); } ) );
		register_rest_route( $ns, '/hr/individual-payees/(?P<id>\d+)/obligations', array( 'methods'=>'POST', 'callback'=>array( $this, 'create_payee_obligation' ), 'permission_callback'=>function(){ return Stockland_Authority::can_manage_hr(); } ) );
		register_rest_route( $ns, '/hr/individual-payee-obligations/(?P<id>\d+)/decision', array( 'methods'=>'POST', 'callback'=>array( $this, 'decide_payee_obligation' ), 'permission_callback'=>function(){ return Stockland_Authority::can_approve_payroll(); } ) );
		register_rest_route( $ns, '/hr/individual-payee-obligations/(?P<id>\d+)/pay', array( 'methods'=>'POST', 'callback'=>array( $this, 'pay_payee_obligation' ), 'permission_callback'=>function(){ return Stockland_Authority::can_approve_payroll(); } ) );

		register_rest_route( $ns, '/hr/resources', array(
			array( 'methods'=>'GET', 'callback'=>array( $this, 'list_resources' ), 'permission_callback'=>function(){ return Stockland_Authority::can_view_hr(); } ),
			array( 'methods'=>'POST', 'callback'=>array( $this, 'upload_resource' ), 'permission_callback'=>function(){ return Stockland_Authority::can_manage_hr(); } ),
		) );
		register_rest_route( $ns, '/hr/resources/(?P<id>\d+)/approve', array( 'methods'=>'POST', 'callback'=>array( $this, 'approve_resource' ), 'permission_callback'=>function(){ return Stockland_Authority::can_approve_payroll(); } ) );
		register_rest_route( $ns, '/hr/resources/(?P<id>\d+)/download', array( 'methods'=>'GET', 'callback'=>array( $this, 'download_resource' ), 'permission_callback'=>function(){ return Stockland_Authority::can_view_hr(); } ) );

		register_rest_route( $ns, '/hr/management-social', array(
			array( 'methods'=>'GET', 'callback'=>array( $this, 'list_social' ), 'permission_callback'=>function(){ return Stockland_Authority::can_view_hr(); } ),
			array( 'methods'=>'POST', 'callback'=>array( $this, 'create_social_campaign' ), 'permission_callback'=>function(){ return Stockland_Authority::can_manage_hr(); } ),
		) );
		register_rest_route( $ns, '/hr/management-social/(?P<id>\d+)/snapshots', array( 'methods'=>'POST', 'callback'=>array( $this, 'create_social_snapshot' ), 'permission_callback'=>function(){ return Stockland_Authority::can_manage_hr(); } ) );
	}

	private function legal_rule_bundle( $start, $end ) {
		$rules = Stockland_HR_Lifecycle::payroll_rules_for_period( $start, $end );
		if ( is_wp_error( $rules ) ) { return $rules; }
		$review = $rules['qualified_legal_review'] ?? array();
		if ( ! Stockland_HR_Lifecycle::has_qualified_legal_review( $rules ) ) {
			return $this->bad( 'hr_legal_review_required', 'No independently reviewed rule bundle is approved for this exact pay period. Payroll is blocked.', 409 );
		}
		$parameters = $review['approved_parameters'] ?? array();
		foreach ( array( 'paye_bands','paye_tax_base_method','pensionable_base_method','employee_pension_rate','employer_pension_rate','component_treatments','rounding_method' ) as $required ) {
			if ( ! is_array( $parameters ) || ! in_array( $required, $parameters, true ) ) {
				return $this->bad( 'hr_rule_parameter_not_signed', 'The independent written opinion does not explicitly approve calculator parameter: ' . $required . '.', 409 );
			}
		}
		$bundle = $rules;
		$bundle['status'] = 'APPROVED';
		$bundle['reviewer_id'] = (string) ( $review['reviewer_id'] ?? '' );
		$bundle['approved_at'] = (string) ( $rules['approved_at'] ?? '' );
		$bundle['official_source_url'] = (string) ( $rules['source_url'] ?? '' );
		$bundle['approval_evidence_ref'] = (string) ( $review['opinion_reference'] ?? '' );
		$bundle['source_sha256'] = strtolower( (string) ( $rules['source_sha256'] ?? '' ) );
		$bundle['paye_tax_base_method'] = $rules['paye_tax_base_method'] ?? null;
		if ( ! in_array( $bundle['paye_tax_base_method'], array( 'TAXABLE_EARNINGS', 'TAXABLE_EARNINGS_LESS_EMPLOYEE_PENSION' ), true ) ) {
			return $this->bad( 'hr_rule_tax_base_invalid', 'The approved rule lacks a supported, explicitly reviewed PAYE base method.', 409 );
		}
		if ( ! isset( $bundle['paye_bands'], $bundle['component_treatments'], $bundle['pensionable_base_method'], $bundle['rounding_method'] ) ) {
			return $this->bad( 'hr_rule_calculator_contract_invalid', 'The approved rule does not match the payroll calculator contract.', 409 );
		}
		return $bundle;
	}

	public function list_runs( WP_REST_Request $req ) {
		global $wpdb;
		$rows = Stockland_DB::read_results( $wpdb->prepare( 'SELECT id,run_code,run_kind,parent_run_id,period_key,start_date,end_date,status,rule_version,exception_count,employee_count,gross_total,taxable_total,exempt_total,paye_total,employee_pension_total,employer_pension_total,net_total,posted_journal_id,created_by,created_at,approved_by,approved_at FROM %i ORDER BY id DESC LIMIT 100', $this->table( 'hr_payroll_runs' ) ), ARRAY_A );
		return rest_ensure_response( is_array( $rows ) ? $rows : array() );
	}

	public function create_run( WP_REST_Request $req ) {
		$p = $req->get_json_params();
		$key = sanitize_text_field( (string) ( $p['idempotency_key'] ?? '' ) );
		$period = sanitize_text_field( (string) ( $p['period_key'] ?? '' ) );
		$start = $this->date_value( $p['start_date'] ?? '' );
		$end = $this->date_value( $p['end_date'] ?? '' );
		$kind = strtoupper( sanitize_key( (string) ( $p['run_kind'] ?? 'NORMAL' ) ) );
		$parent = (int) ( $p['parent_run_id'] ?? 0 );
		if ( strlen( $key ) < 12 || strlen( $key ) > 100 || '' === $period || strlen( $period ) > 40 || false === $start || false === $end || $start > $end || ! in_array( $kind, array( 'NORMAL','ADJUSTMENT','REVERSAL' ), true ) ) {
			return $this->bad( 'hr_payroll_run_invalid', 'A unique idempotency key (12–100 chars), period key, valid dates and supported run kind are required.', 400 );
		}
		if ( 'NORMAL' !== $kind && $parent < 1 ) { return $this->bad( 'hr_payroll_run_parent_required', 'Adjustment/reversal runs must reference a prior payroll run.', 400 ); }
		global $wpdb;
		$existing = Stockland_DB::read_row( $wpdb->prepare( 'SELECT * FROM %i WHERE idempotency_key=%s', $this->table( 'hr_payroll_runs' ), $key ), ARRAY_A );
		if ( $existing ) {
			if ( $existing['period_key'] !== $period || $existing['start_date'] !== $start || $existing['end_date'] !== $end || $existing['run_kind'] !== $kind ) { return $this->bad( 'hr_idempotency_conflict', 'The idempotency key already belongs to a different run request.', 409 ); }
			return rest_ensure_response( array( 'id'=>(int)$existing['id'], 'run_code'=>$existing['run_code'], 'status'=>$existing['status'], 'idempotent_replay'=>true ) );
		}
		if ( 'NORMAL' === $kind ) {
			$active = Stockland_DB::read_row( $wpdb->prepare( "SELECT id,run_code,status FROM %i WHERE period_key=%s AND run_kind='NORMAL' AND status NOT IN ('CANCELLED','REVERSED') LIMIT 1", $this->table( 'hr_payroll_runs' ), $period ), ARRAY_A );
			if ( $active ) { return $this->bad( 'hr_payroll_period_run_exists', 'A normal run already exists for this period. Continue that run or create a separately authorized adjustment/reversal.', 409 ); }
		}
		if ( false === Stockland_DB::execute_query( 'START TRANSACTION' ) ) { return $this->bad( 'hr_transaction_failed', 'Could not start payroll-run creation transaction.', 500 ); }
		try {
			$code = 'PR-' . gmdate( 'Ymd' ) . '-' . strtoupper( bin2hex( random_bytes( 5 ) ) );
			$row = array( 'run_code'=>$code,'idempotency_key'=>$key,'run_kind'=>$kind,'parent_run_id'=>null === $parent ? null : ( $parent ?: null ),'period_key'=>$period,'start_date'=>$start,'end_date'=>$end,'status'=>'DRAFT','created_by'=>get_current_user_id() );
			if ( Stockland_DB::insert_record( $this->table( 'hr_payroll_runs' ), $row ) === false ) { throw new RuntimeException( 'Could not save the payroll run. Check for an idempotency conflict.' ); }
			global $wpdb; $id = (int) $wpdb->insert_id;
			if ( ! $this->event( 'payroll-run', $id, 'RUN_CREATED', null, 'DRAFT', array( 'period_key'=>$period,'start_date'=>$start,'end_date'=>$end,'run_kind'=>$kind,'parent_run_id'=>$parent ?: null ) ) ) { throw new RuntimeException( 'Payroll-run audit event failed.' ); }
			if ( false === Stockland_DB::execute_query( 'COMMIT' ) ) { throw new RuntimeException( 'Payroll-run creation commit failed.' ); }
			return rest_ensure_response( array( 'id'=>$id,'run_code'=>$code,'status'=>'DRAFT','message'=>'Draft created. Generate is blocked until independently reviewed rules cover the entire period.' ) );
		} catch ( Throwable $e ) { Stockland_DB::execute_query( 'ROLLBACK' ); return $this->bad( 'hr_run_create_failed', $e->getMessage(), 409 ); }
	}

	private function employee_source_items( $employee, $comp, $bundle ) {
		$items = array();
		$base = Stockland_Money::decimal( Stockland_Money::minor( $comp['base_salary'] ?? '0.00' ) );
		if ( Stockland_Money::minor( $base ) > 0 ) { $items[] = array( 'code'=>'BASE','amount'=>$base,'source_ref'=>'compensation:' . (int)$comp['id'] . ':base' ); }
		$deduction = Stockland_Money::decimal( Stockland_Money::minor( $comp['standard_deduction_total'] ?? '0.00' ) );
		if ( Stockland_Money::minor( $deduction ) > 0 ) { $items[] = array( 'code'=>'OTHER_DEDUCTION','amount'=>$deduction,'source_ref'=>'compensation:' . (int)$comp['id'] . ':standard-deduction' ); }
		$allowances = json_decode( (string) ( $comp['allowance_breakdown_json'] ?? '' ), true );
		$allowance_total = Stockland_Money::decimal( Stockland_Money::minor( $comp['allowance_total'] ?? '0.00' ) );
		if ( Stockland_Money::minor( $allowance_total ) > 0 && ( ! is_array( $allowances ) || ! $allowances ) ) {
			throw new RuntimeException( 'Approved compensation has allowances but no itemized, source-backed allowance snapshot.' );
		}
		$seen = 0; $line_total = '0.00'; $taxable_total = '0.00'; $exempt_total = '0.00';
		foreach ( (array) $allowances as $index => $a ) {
			if ( ! is_array( $a ) || ! isset( $a['amount'] ) ) { throw new RuntimeException( 'Allowance snapshot contains a malformed component.' ); }
			$amount = Stockland_Money::decimal( Stockland_Money::minor( $a['amount'] ) );
			if ( Stockland_Money::minor( $amount ) < 0 ) { throw new RuntimeException( 'Allowance amount is negative.' ); }
			$treatment = strtoupper( (string) ( $a['tax_treatment'] ?? 'TAXABLE' ) );
			if ( 'EXEMPT' === $treatment ) {
				if ( empty( $a['owner_verified'] ) || empty( $a['rule_set_id'] ) || empty( $a['rule_set_version'] ) || empty( $a['source_hash'] ) ) { throw new RuntimeException( 'Exempt allowance lacks independent approval and source hash.' ); }
				$code = 'EXEMPT_CANDIDATE'; $exempt_total = Stockland_Money::add( $exempt_total, $amount );
			} elseif ( 'TAXABLE' === $treatment ) {
				$code = 'TAXABLE_ALLOWANCE'; $taxable_total = Stockland_Money::add( $taxable_total, $amount );
			} else { throw new RuntimeException( 'Allowance tax category is unresolved; this employee blocks the run.' ); }
			$items[] = array( 'code'=>$code,'amount'=>$amount,'source_ref'=>'compensation:' . (int)$comp['id'] . ':allowance:' . (int)$index );
			$line_total = Stockland_Money::add( $line_total, $amount ); ++$seen;
		}
		if ( Stockland_Money::minor( $line_total ) !== Stockland_Money::minor( $allowance_total ) ) { throw new RuntimeException( 'Allowance items do not reconcile to the approved compensation total.' ); }
		if ( Stockland_Money::minor( $taxable_total ) !== Stockland_Money::minor( $comp['taxable_allowance_total'] ?? '0.00' ) || Stockland_Money::minor( $exempt_total ) !== Stockland_Money::minor( $comp['exempt_allowance_total'] ?? '0.00' ) ) { throw new RuntimeException( 'Taxable/exempt component totals do not reconcile to the compensation snapshot.' ); }
		if ( Stockland_Money::minor( $base ) <= 0 ) { throw new RuntimeException( 'An approved employee compensation record must have positive base salary.' ); }
		return array( 'items'=>$items,'allowance_count'=>$seen,'allowance_total'=>$line_total,'taxable_allowance_total'=>$taxable_total,'exempt_allowance_total'=>$exempt_total );
	}

	public function generate_run( WP_REST_Request $req ) {
		$id = (int) $req['id']; $run = $this->row( 'hr_payroll_runs', $id );
		if ( ! $run || 'DRAFT' !== $run['status'] ) { return $this->bad( 'hr_run_state_invalid', 'Only a draft run can be generated. Failed/blocked runs are retained; correct source inputs and create a fresh run.', 409 ); }
		$bundle = $this->legal_rule_bundle( $run['start_date'], $run['end_date'] );
		if ( is_wp_error( $bundle ) ) { return $bundle; }
		if ( false === Stockland_DB::execute_query( 'START TRANSACTION' ) ) { return $this->bad( 'hr_transaction_failed', 'Could not begin frozen payroll generation.', 500 ); }
		try {
			global $wpdb;
			$run = Stockland_DB::read_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d FOR UPDATE', $this->table( 'hr_payroll_runs' ), $id ), ARRAY_A );
			if ( ! $run || 'DRAFT' !== $run['status'] ) { throw new RuntimeException( 'Run state changed during generation; no totals were changed.' ); }
			$employees = Stockland_DB::read_results( $wpdb->prepare( "SELECT * FROM %i WHERE UPPER(status)='ACTIVE' ORDER BY id", $this->table( 'employees' ) ), ARRAY_A );
			if ( ! is_array( $employees ) || ! $employees ) { throw new RuntimeException( 'No active employees were found. Payroll generation is blocked.' ); }
			$lines = array(); $exceptions = array(); $totals = array( 'gross_total'=>'0.00','taxable_total'=>'0.00','exempt_total'=>'0.00','reimbursement_total'=>'0.00','paye_total'=>'0.00','employee_pension_total'=>'0.00','employer_pension_total'=>'0.00','other_deduction_total'=>'0.00','employer_cost_total'=>'0.00','net_total'=>'0.00' );
			foreach ( $employees as $emp ) {
				$employee_id = (int) $emp['id'];
				$loc = (int) ( $emp['branch_location_id'] ?? 0 );
				try {
					if ( $loc > 0 && ! Stockland_Authority::can_access_location( $loc ) ) { throw new RuntimeException( 'Employee is outside the authenticated payroll-location scope.' ); }
					$comp = Stockland_DB::read_row( $wpdb->prepare( "SELECT * FROM %i WHERE employee_id=%d AND effective_from<=%s AND COALESCE(approval_status,'APPROVED')='APPROVED' ORDER BY effective_from DESC,id DESC LIMIT 1", $this->table( 'employee_compensation' ), $employee_id, $run['end_date'] ), ARRAY_A );
					if ( ! $comp ) { throw new RuntimeException( 'No approved effective-dated compensation exists.' ); }
					$src = $this->employee_source_items( $emp, $comp, $bundle );
					$input = array( 'employee_ref'=>(string)$emp['employee_id'],'period_date'=>$run['end_date'],'items'=>$src['items'] );
					$calc = Stockland_HR_Payroll_Calculator::calculate( $input, $bundle, false );
					$stored_pensionable = Stockland_Money::minor( $comp['pensionable_salary'] ?? $comp['base_salary'] );
					$calculated_pensionable = Stockland_Money::minor( $calc['totals']['pensionable_earnings'] );
					if ( $stored_pensionable !== $calculated_pensionable ) { throw new RuntimeException( 'Pensionable base mismatch between approved compensation and reviewed component treatments; legal classification must be reconciled.' ); }
					$snapshot = array( 'employee_id'=>$employee_id,'employee_code'=>$emp['employee_id'],'location_id'=>$loc ?: null,'compensation_id'=>(int)$comp['id'],'effective_from'=>$comp['effective_from'],'base_salary'=>$comp['base_salary'],'allowances'=>json_decode((string)($comp['allowance_breakdown_json']??''),true),'standard_deduction_total'=>$comp['standard_deduction_total']??'0.00','approved_rule_set_id'=>$bundle['rule_set_id'],'approved_rule_version'=>$bundle['rule_version'],'rule_source_sha256'=>$bundle['source_sha256'] );
					$snapshot_json = $this->json( $snapshot ); $calc_json = $this->json( $calc );
					$lines[] = array( 'employee_id'=>$employee_id,'employee_code'=>(string)$emp['employee_id'],'snapshot'=>$snapshot_json,'calculation'=>$calc_json,'snapshot_hash'=>hash('sha256',$snapshot_json),'calculation_hash'=>hash('sha256',$calc_json),'totals'=>$calc['totals'] );
					$totals['gross_total'] = Stockland_Money::add( $totals['gross_total'], $calc['totals']['earnings'] );
					$totals['taxable_total'] = Stockland_Money::add( $totals['taxable_total'], $calc['totals']['taxable_earnings'] );
					$totals['exempt_total'] = Stockland_Money::add( $totals['exempt_total'], Stockland_Money::subtract( $calc['totals']['earnings'], $calc['totals']['taxable_earnings'] ) );
					$totals['paye_total'] = Stockland_Money::add( $totals['paye_total'], $calc['totals']['payable_income_tax'] );
					$totals['employee_pension_total'] = Stockland_Money::add( $totals['employee_pension_total'], $calc['totals']['employee_pension'] );
					$totals['employer_pension_total'] = Stockland_Money::add( $totals['employer_pension_total'], $calc['totals']['employer_pension'] );
					$totals['other_deduction_total'] = Stockland_Money::add( $totals['other_deduction_total'], $calc['totals']['other_deductions'] );
					$totals['employer_cost_total'] = Stockland_Money::add( $totals['employer_cost_total'], $calc['totals']['employer_other_costs'] );
					$totals['net_total'] = Stockland_Money::add( $totals['net_total'], $calc['totals']['net_pay'] );
				} catch ( Throwable $e ) {
					$exceptions[] = array( 'employee_id'=>$employee_id,'exception_code'=>'EMPLOYEE_INPUT_BLOCKED','severity'=>'BLOCKER','summary'=>substr( sanitize_text_field( $e->getMessage() ), 0, 950 ) );
				}
			}
			if ( $exceptions ) {
				foreach ( $exceptions as $ex ) {
					Stockland_DB::insert_record( $this->table( 'hr_payroll_run_exceptions' ), array( 'run_id'=>$id,'employee_id'=>$ex['employee_id'],'exception_code'=>$ex['exception_code'],'severity'=>$ex['severity'],'summary'=>$ex['summary'],'status'=>'OPEN','created_by'=>get_current_user_id() ) );
				}
				Stockland_DB::update_record( $this->table( 'hr_payroll_runs' ), array( 'status'=>'BLOCKED','rule_set_id'=>$bundle['rule_set_id'],'rule_version'=>$bundle['rule_version'],'rule_source_sha256'=>$bundle['source_sha256'],'rules_snapshot_json'=>$this->json($bundle),'exception_count'=>count($exceptions),'employee_count'=>count($employees) ), array( 'id'=>$id,'status'=>'DRAFT' ) );
				if ( ! $this->event( 'payroll-run', $id, 'RUN_BLOCKED', 'DRAFT', 'BLOCKED', array( 'exception_count'=>count($exceptions),'rule_version'=>$bundle['rule_version'] ), $bundle['rule_set_id'], $bundle['rule_version'] ) ) { throw new RuntimeException( 'Run blocker audit event failed.' ); }
				if ( false === Stockland_DB::execute_query( 'COMMIT' ) ) { throw new RuntimeException( 'Blocked-run commit failed.' ); }
				return $this->bad( 'hr_run_blocked', 'Payroll run was blocked for ' . count($exceptions) . ' employee input(s). Resolve source exceptions and create a new idempotent run; no partial payroll was calculated or posted.', 409 );
			}
			$snapshot_refs = array(); $hash_parts = array();
			foreach ( $lines as $line ) {
				$row = array( 'run_id'=>$id,'employee_id'=>$line['employee_id'],'employee_code'=>$line['employee_code'],'source_snapshot_json'=>$line['snapshot'],'calculation_json'=>$line['calculation'],'source_snapshot_sha256'=>$line['snapshot_hash'],'calculation_sha256'=>$line['calculation_hash'] );
				foreach ( array( 'gross_total'=>'earnings','taxable_total'=>'taxable_earnings','paye_total'=>'payable_income_tax','employee_pension_total'=>'employee_pension','employer_pension_total'=>'employer_pension','other_deduction_total'=>'other_deductions','employer_cost_total'=>'employer_other_costs','net_total'=>'net_pay' ) as $col=>$key ) { $row[$col]=$line['totals'][$key]; }
				$row['exempt_total']=Stockland_Money::subtract($line['totals']['earnings'],$line['totals']['taxable_earnings']);
				$row['reimbursement_total']='0.00';
				if ( Stockland_DB::insert_record( $this->table('hr_payroll_run_lines'), $row ) === false ) { throw new RuntimeException('Could not save immutable payroll run line.'); }
				$hash_parts[] = array( $line['snapshot_hash'], $line['calculation_hash'] );
			}
			$input_hash = hash( 'sha256', $this->json( $hash_parts ) );
			$calc_hash = hash( 'sha256', $this->json( array_column($hash_parts,1) ) );
			$update = array_merge( $totals, array( 'status'=>'CALCULATED','rule_set_id'=>$bundle['rule_set_id'],'rule_version'=>$bundle['rule_version'],'rule_source_sha256'=>$bundle['source_sha256'],'rules_snapshot_json'=>$this->json($bundle),'input_snapshot_sha256'=>$input_hash,'calculation_sha256'=>$calc_hash,'exception_count'=>0,'employee_count'=>count($lines) ) );
			if ( false === Stockland_DB::update_record( $this->table('hr_payroll_runs'), $update, array('id'=>$id,'status'=>'DRAFT') ) ) { throw new RuntimeException('Could not freeze the calculated payroll run.'); }
			if ( ! $this->event( 'payroll-run', $id, 'RUN_CALCULATED', 'DRAFT', 'CALCULATED', array('employee_count'=>count($lines),'input_snapshot_sha256'=>$input_hash,'calculation_sha256'=>$calc_hash,'rule_version'=>$bundle['rule_version']), $bundle['rule_set_id'], $bundle['rule_version'] ) ) { throw new RuntimeException('Payroll-run calculation event failed.'); }
			if ( false === Stockland_DB::execute_query('COMMIT') ) { throw new RuntimeException('Payroll-run calculation commit failed.'); }
			return rest_ensure_response( array('id'=>$id,'run_code'=>$run['run_code'],'status'=>'CALCULATED','employee_count'=>count($lines),'totals'=>$totals,'input_snapshot_sha256'=>$input_hash,'calculation_sha256'=>$calc_hash,'message'=>'Frozen candidate run only; submit and independent approval remain separate.') );
		} catch ( Throwable $e ) { Stockland_DB::execute_query('ROLLBACK'); return $this->bad('hr_run_generate_failed',$e->getMessage(),409); }
	}

	public function submit_run( WP_REST_Request $req ) {
		$id=(int)$req['id']; $p=$req->get_json_params(); $note=sanitize_textarea_field((string)($p['review_note']??''));
		if(strlen(trim($note))<10)return $this->bad('hr_run_review_note_required','Record a meaningful payroll-preparation review note.');
		global $wpdb;
		if(false===Stockland_DB::execute_query('START TRANSACTION'))return $this->bad('hr_transaction_failed','Could not begin payroll submission.',500);
		try {
			$r=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hr_payroll_runs'),$id),ARRAY_A);
			if(!$r||$r['status']!=='CALCULATED'||(int)$r['exception_count']!==0)throw new RuntimeException('Only a fully calculated, exception-free run can be submitted.');
			if(!preg_match('/^[a-f0-9]{64}$/',(string)$r['input_snapshot_sha256'])||!hash_equals((string)$r['input_snapshot_sha256'],hash('sha256',$this->json($this->run_line_hashes($id)))))throw new RuntimeException('Frozen input snapshot hash is missing or fails integrity verification.');
			if(Stockland_DB::update_record($this->table('hr_payroll_runs'),array('status'=>'SUBMITTED','submitted_by'=>get_current_user_id(),'submitted_at'=>current_time('mysql')),array('id'=>$id,'status'=>'CALCULATED'))===false)throw new RuntimeException('Could not submit payroll run.');
			if(!$this->event('payroll-run',$id,'RUN_SUBMITTED','CALCULATED','SUBMITTED',array('review_note'=>$note,'input_hash'=>$r['input_snapshot_sha256'],'calculation_hash'=>$r['calculation_sha256']),$r['rule_set_id'],$r['rule_version']))throw new RuntimeException('Submission audit event failed.');
			if(false===Stockland_DB::execute_query('COMMIT'))throw new RuntimeException('Submission commit failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>'SUBMITTED'));
		} catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->bad('hr_run_submit_failed',$e->getMessage(),409);}
	}
	private function run_line_hashes( $run_id ) {
		global $wpdb;
		$rows=Stockland_DB::read_results($wpdb->prepare('SELECT source_snapshot_sha256,calculation_sha256 FROM %i WHERE run_id=%d ORDER BY employee_id',$this->table('hr_payroll_run_lines'),$run_id),ARRAY_A);
		return array_map(static function($r){return array($r['source_snapshot_sha256'],$r['calculation_sha256']);},(array)$rows);
	}

	public function approve_run( WP_REST_Request $req ) {
		$id=(int)$req['id'];$p=$req->get_json_params();$note=sanitize_textarea_field((string)($p['approval_note']??''));
		if(strlen(trim($note))<20)return $this->bad('hr_run_approval_note_required','Independent Owner approval note must explain the review.');
		global $wpdb;
		if(false===Stockland_DB::execute_query('START TRANSACTION'))return $this->bad('hr_transaction_failed','Could not begin payroll approval.',500);
		try {
			$r=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hr_payroll_runs'),$id),ARRAY_A);
			if(!$r||$r['status']!=='SUBMITTED')throw new RuntimeException('Only a submitted payroll run can be approved.');
			if((int)$r['created_by']===(int)get_current_user_id()||(int)$r['submitted_by']===(int)get_current_user_id())throw new RuntimeException('Maker-checker: Owner approver must differ from the preparer and submitter.');
			$bundle=$this->legal_rule_bundle($r['start_date'],$r['end_date']);if(is_wp_error($bundle))throw new RuntimeException($bundle->get_error_message());
			if((int)$bundle['rule_set_id']!==(int)$r['rule_set_id']||(string)$bundle['rule_version']!==(string)$r['rule_version']||!hash_equals((string)$r['rule_source_sha256'],(string)$bundle['source_sha256']))throw new RuntimeException('The exact approved statutory rule source changed after the run was calculated.');
			$lines=Stockland_DB::read_results($wpdb->prepare('SELECT * FROM %i WHERE run_id=%d ORDER BY employee_id',$this->table('hr_payroll_run_lines'),$id),ARRAY_A);
			if(!is_array($lines)||count($lines)!==(int)$r['employee_count']||!$lines)throw new RuntimeException('Run line count does not reconcile to the frozen headcount.');
			$totals=array('gross_total'=>'0.00','taxable_total'=>'0.00','exempt_total'=>'0.00','reimbursement_total'=>'0.00','paye_total'=>'0.00','employee_pension_total'=>'0.00','employer_pension_total'=>'0.00','other_deduction_total'=>'0.00','employer_cost_total'=>'0.00','net_total'=>'0.00');
			$hashes=array();
			foreach($lines as $line){
				if(!preg_match('/^[a-f0-9]{64}$/',(string)$line['source_snapshot_sha256'])||!preg_match('/^[a-f0-9]{64}$/',(string)$line['calculation_sha256'])||!hash_equals(hash('sha256',(string)$line['source_snapshot_json']),(string)$line['source_snapshot_sha256'])||!hash_equals(hash('sha256',(string)$line['calculation_json']),(string)$line['calculation_sha256']))throw new RuntimeException('A frozen employee input or calculation hash fails integrity verification.');
				$calc=json_decode((string)$line['calculation_json'],true);if(!is_array($calc)||!isset($calc['totals']))throw new RuntimeException('A stored calculator trace is malformed.');
				foreach(array('gross_total'=>'earnings','taxable_total'=>'taxable_earnings','paye_total'=>'payable_income_tax','employee_pension_total'=>'employee_pension','employer_pension_total'=>'employer_pension','other_deduction_total'=>'other_deductions','employer_cost_total'=>'employer_other_costs','net_total'=>'net_pay') as $col=>$k){if(Stockland_Money::minor($line[$col])!==Stockland_Money::minor($calc['totals'][$k]))throw new RuntimeException('Stored payroll amount differs from its calculation trace.');$totals[$col]=Stockland_Money::add($totals[$col],$line[$col]);}
				$totals['exempt_total']=Stockland_Money::add($totals['exempt_total'],$line['exempt_total']);$hashes[]=array($line['source_snapshot_sha256'],$line['calculation_sha256']);
			}
			foreach($totals as $key=>$v){if(Stockland_Money::minor($v)!==Stockland_Money::minor($r[$key]))throw new RuntimeException('Payroll header total failed reconciliation: '.$key);}
			if(!hash_equals((string)$r['input_snapshot_sha256'],hash('sha256',$this->json($hashes))))throw new RuntimeException('Frozen run input hash failed integrity verification.');
			if(!hash_equals((string)$r['calculation_sha256'],hash('sha256',$this->json(array_column($hashes,1)))))throw new RuntimeException('Frozen run calculation hash failed integrity verification.');
			$accounts=array('salary_expense'=>'6200','net_payable'=>'2020','paye_payable'=>'2031','employee_pension_payable'=>'2030','employer_pension_expense'=>'6210','employer_pension_payable'=>'2032','other_deductions_payable'=>'2033');
			$dr=$r['gross_total'];$cr=Stockland_Money::add($r['net_total'],Stockland_Money::add($r['paye_total'],Stockland_Money::add($r['employee_pension_total'],Stockland_Money::add($r['employer_pension_total'],$r['other_deduction_total']))));
			if(Stockland_Money::minor(Stockland_Money::add($r['gross_total'],$r['employer_pension_total']))!==Stockland_Money::minor($cr))throw new RuntimeException('Payroll debits and liabilities do not balance; reclassify missing employer costs/deductions before approval.');
			$journal=array();
			foreach(array(array($accounts['salary_expense'],$r['gross_total'],'0.00','Salary expense'),array($accounts['net_payable'],'0.00',$r['net_total'],'Employee net-pay liability'),array($accounts['paye_payable'],'0.00',$r['paye_total'],'PAYE liability'),array($accounts['employee_pension_payable'],'0.00',$r['employee_pension_total'],'Employee pension liability'),array($accounts['employer_pension_expense'],$r['employer_pension_total'],'0.00','Employer pension expense'),array($accounts['employer_pension_payable'],'0.00',$r['employer_pension_total'],'Employer pension liability'),array($accounts['other_deductions_payable'],'0.00',$r['other_deduction_total'],'Other employee deductions liability')) as $item){if(Stockland_Money::minor($item[1])===0&&Stockland_Money::minor($item[2])===0)continue;$journal[]=array('account_code'=>$item[0],'debit'=>$item[1],'credit'=>$item[2],'description'=>$item[3],'location_id'=>null);}
			if(Stockland_Money::minor($r['employer_cost_total'])>0||Stockland_Money::minor($r['reimbursement_total'])>0)throw new RuntimeException('This candidate cannot post unclassified employer-cost/reimbursement items; route those through their separately approved accounting flows.');
			$jid=Stockland_Accounting::post_journal($r['end_date'],'HR_PAYROLL_RUN',(string)$id,'Payroll run '.$r['run_code'].' / '.$r['rule_version'],$journal,get_current_user_id());
			if(!$jid)throw new RuntimeException('GL/PLEDGER rejected payroll posting.');
			if(Stockland_DB::update_record($this->table('hr_payroll_runs'),array('status'=>'POSTED','posted_journal_id'=>(int)$jid,'approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql')),array('id'=>$id,'status'=>'SUBMITTED'))===false)throw new RuntimeException('Could not post/approve the immutable payroll run.');
			$remits=array(array('type'=>'PAYE','amount'=>$r['paye_total']),array('type'=>'EMPLOYEE_PENSION','amount'=>$r['employee_pension_total']),array('type'=>'EMPLOYER_PENSION','amount'=>$r['employer_pension_total']));
			foreach($remits as $rem){if(Stockland_Money::minor($rem['amount'])<=0)continue;$code='REM-'.$r['run_code'].'-'.$rem['type'];$existing=Stockland_DB::read_row($wpdb->prepare('SELECT id FROM %i WHERE remittance_code=%s',$this->table('hr_statutory_remittances'),$code),ARRAY_A);if(!$existing){if(Stockland_DB::insert_record($this->table('hr_statutory_remittances'),array('remittance_code'=>$code,'run_id'=>$id,'statutory_type'=>$rem['type'],'amount_due'=>$rem['amount'],'status'=>'DRAFT','rule_set_id'=>$r['rule_set_id'],'rule_version'=>$r['rule_version'],'created_by'=>get_current_user_id()))===false)throw new RuntimeException('Could not create statutory remittance obligation.');}}
			if(!$this->event('payroll-run',$id,'RUN_POSTED','SUBMITTED','POSTED',array('journal_id'=>(int)$jid,'gross_total'=>$r['gross_total'],'net_total'=>$r['net_total'],'rule_version'=>$r['rule_version'],'remittance_obligations'=>count($remits)),$r['rule_set_id'],$r['rule_version']))throw new RuntimeException('Payroll posting audit failed.');
			if(false===Stockland_DB::execute_query('COMMIT'))throw new RuntimeException('Payroll posting transaction failed to commit.');
			return rest_ensure_response(array('id'=>$id,'status'=>'POSTED','journal_id'=>(int)$jid,'net_total'=>$r['net_total'],'message'=>'Posted to GL/PLEDGER. This is not evidence of bank settlement; create a payout batch and reconcile recipient outcomes separately.'));
		} catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->bad('hr_run_approval_failed',$e->getMessage(),409);}
	}

	public function create_payment_batch( WP_REST_Request $req ) {
		$run_id=(int)$req['id'];$p=$req->get_json_params();$method=strtoupper(sanitize_key((string)($p['payment_method']??'')));$ref=sanitize_text_field((string)($p['external_batch_reference']??''));$key=sanitize_text_field((string)($p['idempotency_key']??''));$failedOnly=!empty($p['retry_failed_only']);
		if(!in_array($method,array('BANK','CASH'),true)||''===$ref||strlen($key)<12)return $this->bad('hr_batch_input_invalid','Bank/cash method, external batch/voucher reference and idempotency key are required.',400);
		$run=$this->row('hr_payroll_runs',$run_id);if(!$run||!in_array($run['status'],array('POSTED','PAYMENT_PARTIAL','SETTLED'),true))return $this->bad('hr_run_not_posted','Only a posted payroll run can be settled.',409);
		global $wpdb;
		$old=Stockland_DB::read_row($wpdb->prepare('SELECT id,batch_code,status FROM %i WHERE idempotency_key=%s',$this->table('hr_payroll_payment_batches'),$key),ARRAY_A);
		if($old)return rest_ensure_response(array('id'=>(int)$old['id'],'batch_code'=>$old['batch_code'],'status'=>$old['status'],'idempotent_replay'=>true));
		$lines=Stockland_DB::read_results($wpdb->prepare('SELECT l.* FROM %i l WHERE l.run_id=%d ORDER BY l.employee_id',$this->table('hr_payroll_run_lines'),$run_id),ARRAY_A);
		$selected=array();foreach((array)$lines as $line){
			$items=Stockland_DB::read_results($wpdb->prepare('SELECT i.status FROM %i i INNER JOIN %i b ON b.id=i.batch_id WHERE i.run_line_id=%d ORDER BY b.id DESC',$this->table('hr_payroll_payment_items'),$this->table('hr_payroll_payment_batches'),(int)$line['id']),ARRAY_A);
			$statuses=array_column((array)$items,'status');if(in_array('PAID',$statuses,true))continue;
			if($failedOnly&&!array_intersect($statuses,array('FAILED','RETURNED')))continue;
			if(!$failedOnly&&$items)continue;
			$selected[]=$line;
		}
		if(!$selected)return $this->bad('hr_batch_no_unsettled_items','No eligible unpaid payroll recipients were found for this batch.',409);
		if(false===Stockland_DB::execute_query('START TRANSACTION'))return $this->bad('hr_transaction_failed','Could not begin payment-batch creation.',500);
		try{
			$code='PB-'.$run['run_code'].'-'.strtoupper(bin2hex(random_bytes(4)));$total='0.00';
			foreach($selected as $line)$total=Stockland_Money::add($total,$line['net_total']);
			$row=array('batch_code'=>$code,'idempotency_key'=>$key,'run_id'=>$run_id,'payment_method'=>$method,'currency'=>'ETB','total_requested'=>$total,'total_paid'=>'0.00','external_batch_reference'=>$ref,'status'=>'INSTRUCTED','created_by'=>get_current_user_id());
			if(Stockland_DB::insert_record($this->table('hr_payroll_payment_batches'),$row)===false)throw new RuntimeException('Payment batch could not be created or idempotency key is already used.');
			$batch_id=(int)$wpdb->insert_id;
			foreach($selected as $line){if(Stockland_DB::insert_record($this->table('hr_payroll_payment_items'),array('batch_id'=>$batch_id,'run_line_id'=>(int)$line['id'],'employee_id'=>(int)$line['employee_id'],'employee_code'=>$line['employee_code'],'amount'=>$line['net_total'],'status'=>'PENDING'))===false)throw new RuntimeException('Recipient payout instruction could not be saved.');}
			if(!$this->event('payroll-payment-batch',$batch_id,'PAYMENT_BATCH_INSTRUCTED',null,'INSTRUCTED',array('run_id'=>$run_id,'recipient_count'=>count($selected),'requested'=>$total,'external_batch_reference'=>$ref)))throw new RuntimeException('Payment batch audit failed.');
			if(false===Stockland_DB::execute_query('COMMIT'))throw new RuntimeException('Batch transaction failed to commit.');
			return rest_ensure_response(array('id'=>$batch_id,'batch_code'=>$code,'status'=>'INSTRUCTED','recipient_count'=>count($selected),'total_requested'=>$total,'message'=>'Manual bank/cash instruction only. Record each recipient success/failure from external evidence before reconciliation.'));
		}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->bad('hr_batch_create_failed',$e->getMessage(),409);}
	}

	public function record_payment_result( WP_REST_Request $req ) {
		$id=(int)$req['id'];$item_id=(int)$req['item_id'];$p=$req->get_json_params();$result=strtoupper(sanitize_key((string)($p['result']??'')));$reference=sanitize_text_field((string)($p['external_payment_reference']??''));$reason=sanitize_textarea_field((string)($p['failure_reason']??''));$amount=isset($p['amount'])?Stockland_Money::decimal(Stockland_Money::minor($p['amount'])):null;
		if(!in_array($result,array('PAID','FAILED','RETURNED'),true))return $this->bad('hr_payment_result_invalid','Payment result must be PAID, FAILED or RETURNED.',400);
		if('PAID'===$result&&($reference===''||null===$amount))return $this->bad('hr_payment_confirmation_required','Successful payment requires the external reference and exact amount.');
		if(in_array($result,array('FAILED','RETURNED'),true)&&strlen(trim($reason))<5)return $this->bad('hr_payment_failure_reason_required','Failed or returned payout needs an external evidence reason.');
		global $wpdb;if(false===Stockland_DB::execute_query('START TRANSACTION'))return $this->bad('hr_transaction_failed','Could not start payout result transaction.',500);
		try{$b=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hr_payroll_payment_batches'),$id),ARRAY_A);$i=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hr_payroll_payment_items'),$item_id),ARRAY_A);
			if(!$b||!$i||(int)$i['batch_id']!==$id||!in_array($b['status'],array('INSTRUCTED','PARTIAL_RESULT','FAILED'),true))throw new RuntimeException('Payout batch/recipient is missing or already reconciled.');
			if('PAID'===$result&&Stockland_Money::minor($amount)!==Stockland_Money::minor($i['amount']))throw new RuntimeException('Confirmed recipient amount differs from the exact instructed net amount.');
			if('PAID'===$result&&in_array($i['status'],array('PAID'),true))throw new RuntimeException('A paid recipient cannot be paid twice.');
			$update=array('status'=>$result,'external_payment_reference'=>$reference?:null,'failure_reason'=>$reason?:null,'settled_by'=>get_current_user_id(),'settled_at'=>current_time('mysql'));
			if(Stockland_DB::update_record($this->table('hr_payroll_payment_items'),$update,array('id'=>$item_id,'batch_id'=>$id))===false)throw new RuntimeException('Could not record recipient result.');
			$rows=Stockland_DB::read_results($wpdb->prepare('SELECT status,amount FROM %i WHERE batch_id=%d',$this->table('hr_payroll_payment_items'),$id),ARRAY_A);$paid='0.00';$pending=0;$failed=0;
			foreach((array)$rows as $row){if($row['status']==='PAID')$paid=Stockland_Money::add($paid,$row['amount']);elseif($row['status']==='PENDING')++$pending;else ++$failed;}
			$state=$pending?'PARTIAL_RESULT':($failed?($paid!=='0.00'?'PARTIAL_RESULT':'FAILED'):'RESULTS_COMPLETE');
			Stockland_DB::update_record($this->table('hr_payroll_payment_batches'),array('total_paid'=>$paid,'status'=>$state),array('id'=>$id));
			if(!$this->event('payroll-payment-item',$item_id,'PAYOUT_'.$result,$i['status'],$result,array('batch_id'=>$id,'employee_code'=>$i['employee_code'],'amount'=>$i['amount'],'external_reference'=>$reference,'reason'=>$reason)))throw new RuntimeException('Payout result audit failed.');
			if(false===Stockland_DB::execute_query('COMMIT'))throw new RuntimeException('Payout result commit failed.');
			return rest_ensure_response(array('batch_id'=>$id,'item_id'=>$item_id,'recipient_status'=>$result,'batch_status'=>$state,'confirmed_paid_total'=>$paid,'pending_recipients'=>$pending,'failed_recipients'=>$failed));
		}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->bad('hr_payment_result_failed',$e->getMessage(),409);}
	}

	public function reconcile_payment_batch( WP_REST_Request $req ) {
		$id=(int)$req['id'];$p=$req->get_json_params();$statement=sanitize_text_field((string)($p['bank_statement_reference']??''));$ref=sanitize_text_field((string)($p['external_batch_reference']??''));$confirmed=isset($p['confirmed_paid_total'])?Stockland_Money::decimal(Stockland_Money::minor($p['confirmed_paid_total'])):null;
		if($statement===''||$ref===''||null===$confirmed)return $this->bad('hr_payment_reconciliation_evidence_required','Bank statement/cash voucher reference, batch reference and confirmed amount are mandatory.');
		global $wpdb;if(false===Stockland_DB::execute_query('START TRANSACTION'))return $this->bad('hr_transaction_failed','Could not begin batch reconciliation.',500);
		try{$b=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hr_payroll_payment_batches'),$id),ARRAY_A);if(!$b||!in_array($b['status'],array('RESULTS_COMPLETE','FAILED','PARTIAL_RESULT'),true))throw new RuntimeException('Batch has not recorded final recipient outcomes or is already reconciled.');
			if(!hash_equals((string)$b['external_batch_reference'],$ref))throw new RuntimeException('External batch reference does not match the original instruction.');
			$items=Stockland_DB::read_results($wpdb->prepare('SELECT status,amount FROM %i WHERE batch_id=%d',$this->table('hr_payroll_payment_items'),$id),ARRAY_A);$sum='0.00';foreach((array)$items as $i){if($i['status']==='PENDING')throw new RuntimeException('Every recipient must have a confirmed paid/failed/returned result before reconciliation.');if($i['status']==='PAID')$sum=Stockland_Money::add($sum,$i['amount']);}
			if(Stockland_Money::minor($sum)!==Stockland_Money::minor($confirmed)||Stockland_Money::minor($sum)!==Stockland_Money::minor($b['total_paid']))throw new RuntimeException('Bank/cash confirmed total differs from recipient-level successful payouts.');
			$jid=null;if(Stockland_Money::minor($sum)>0){$bank='BANK'===$b['payment_method']?'1010':'1000';$journal=array(array('account_code'=>'2020','debit'=>$sum,'credit'=>'0.00','description'=>'Clear paid employee net-pay liability','location_id'=>null),array('account_code'=>$bank,'debit'=>'0.00','credit'=>$sum,'description'=>'Verified payroll payout batch','location_id'=>null));$jid=Stockland_Accounting::post_journal(current_time('Y-m-d'),'HR_PAYROLL_BATCH_PAYMENT',(string)$id,'Payroll payment batch '.$b['batch_code'],$journal,get_current_user_id());if(!$jid)throw new RuntimeException('GL/PLEDGER payment liability clearing failed or was not idempotent.');}
			$state=Stockland_Money::minor($sum)===Stockland_Money::minor($b['total_requested'])?'RECONCILED':'PARTIAL_RECONCILED';
			Stockland_DB::update_record($this->table('hr_payroll_payment_batches'),array('status'=>$state,'bank_statement_reference'=>$statement,'payment_journal_id'=>$jid?(int)$jid:null,'reconciled_by'=>get_current_user_id(),'reconciled_at'=>current_time('mysql')),array('id'=>$id));
			$all=Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE run_id=%d AND status NOT IN ('RECONCILED')",$this->table('hr_payroll_payment_batches'),(int)$b['run_id']));
			$notPaid=Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i l WHERE l.run_id=%d AND NOT EXISTS (SELECT 1 FROM %i i INNER JOIN %i b ON b.id=i.batch_id WHERE i.run_line_id=l.id AND i.status='PAID' AND b.status IN ('RECONCILED','PARTIAL_RECONCILED'))",$this->table('hr_payroll_run_lines'),(int)$b['run_id'],$this->table('hr_payroll_payment_items'),$this->table('hr_payroll_payment_batches')));
			if((int)$notPaid===0&&(int)$all===0)Stockland_DB::update_record($this->table('hr_payroll_runs'),array('status'=>'SETTLED','closed_by'=>get_current_user_id(),'closed_at'=>current_time('mysql')),array('id'=>(int)$b['run_id'],'status'=>'POSTED'));
			else { $runNow=Stockland_DB::read_row($wpdb->prepare('SELECT status FROM %i WHERE id=%d FOR UPDATE',$this->table('hr_payroll_runs'),(int)$b['run_id']),ARRAY_A); if($runNow&&in_array($runNow['status'],array('POSTED','PAYMENT_PARTIAL'),true))Stockland_DB::update_record($this->table('hr_payroll_runs'),array('status'=>'PAYMENT_PARTIAL'),array('id'=>(int)$b['run_id'],'status'=>$runNow['status'])); }
			if(!$this->event('payroll-payment-batch',$id,'PAYMENT_BATCH_RECONCILED',$b['status'],$state,array('paid_total'=>$sum,'requested_total'=>$b['total_requested'],'statement_reference'=>$statement,'journal_id'=>$jid?:null)))throw new RuntimeException('Payment reconciliation audit failed.');
			if(false===Stockland_DB::execute_query('COMMIT'))throw new RuntimeException('Payout reconciliation commit failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>$state,'paid_total'=>$sum,'journal_id'=>$jid,'message'=>'GL liability clearing recorded separately from the payroll accrual. Failed recipients remain payable and require a new idempotent retry batch.'));
		}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->bad('hr_payment_reconciliation_failed',$e->getMessage(),409);}
	}

	public function reconcile_remittance( WP_REST_Request $req ) {
		$id=(int)$req['id'];$p=$req->get_json_params();$filing=sanitize_text_field((string)($p['filing_reference']??''));$payment=sanitize_text_field((string)($p['payment_reference']??''));$receipt=(int)($p['receipt_resource_id']??0);$confirmed=isset($p['confirmed_amount'])?Stockland_Money::decimal(Stockland_Money::minor($p['confirmed_amount'])):null;
		if($filing===''||$payment===''||$receipt<1||null===$confirmed)return $this->bad('hr_remittance_evidence_required','Filing reference, statutory payment reference, approved private receipt resource and exact confirmed amount are required.');
		global $wpdb;if(false===Stockland_DB::execute_query('START TRANSACTION'))return $this->bad('hr_transaction_failed','Could not begin statutory remittance reconciliation.',500);
		try{$r=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hr_statutory_remittances'),$id),ARRAY_A);$doc=Stockland_DB::read_row($wpdb->prepare("SELECT id,status,resource_type,file_sha256 FROM %i WHERE id=%d",$this->table('hr_resources'),$receipt),ARRAY_A);
			if(!$r||!$doc||$doc['status']!=='APPROVED'||!in_array($doc['resource_type'],array('TAX_RECEIPT','PENSION_RECEIPT','STATUTORY_RECEIPT'),true))throw new RuntimeException('Remittance reconciliation requires an approved private statutory receipt resource.');
			if(Stockland_Money::minor($confirmed)!==Stockland_Money::minor($r['amount_due']))throw new RuntimeException('Confirmed statutory remittance amount must match the obligation exactly; differences require a separate adjustment and review.');
			if(!$this->is_owner()&&(int)$r['created_by']===(int)get_current_user_id())throw new RuntimeException('Maker-checker: an obligation creator cannot reconcile their own remittance.');
			$data=array('status'=>'RECONCILED','filing_reference'=>$filing,'payment_reference'=>$payment,'receipt_resource_id'=>$receipt,'confirmed_amount'=>$confirmed,'reconciled_by'=>get_current_user_id(),'reconciled_at'=>current_time('mysql'));
			if(Stockland_DB::update_record($this->table('hr_statutory_remittances'),$data,array('id'=>$id,'status'=>'DRAFT'))===false)throw new RuntimeException('Could not reconcile statutory obligation.');
			if(!$this->event('statutory-remittance',$id,'REMITTANCE_RECONCILED',$r['status'],'RECONCILED',array('type'=>$r['statutory_type'],'amount'=>$confirmed,'receipt_sha256'=>$doc['file_sha256']??null)))throw new RuntimeException('Remittance audit failed.');
			if(false===Stockland_DB::execute_query('COMMIT'))throw new RuntimeException('Remittance commit failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>'RECONCILED','confirmed_amount'=>$confirmed));
		}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->bad('hr_remittance_reconciliation_failed',$e->getMessage(),409);}
	}

	private function pii_key() {
		if ( ! defined( 'STOCKLAND_HR_PII_ENCRYPTION_KEY' ) || strlen( (string) STOCKLAND_HR_PII_ENCRYPTION_KEY ) < 32 ) { return $this->bad( 'hr_pii_key_missing', 'Define STOCKLAND_HR_PII_ENCRYPTION_KEY as a dedicated secret of at least 32 characters before saving TIN/licence numbers.', 503 ); }
		return hash( 'sha256', (string) STOCKLAND_HR_PII_ENCRYPTION_KEY, true );
	}
	private function encrypt_pii( $value ) {
		if ( '' === trim( (string) $value ) ) { return array( 'ciphertext'=>null,'last4'=>null ); }
		$key=$this->pii_key();if(is_wp_error($key))return $key;
		if(!function_exists('openssl_encrypt'))return $this->bad('hr_pii_crypto_unavailable','OpenSSL encryption is required before storing TIN/licence identifiers.',503);
		$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt(trim((string)$value),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'stockland-hr-pii-v1',16);
		if(false===$cipher)return $this->bad('hr_pii_encrypt_failed','Could not encrypt payee identifier.',500);
		return array('ciphertext'=>base64_encode($iv.$tag.$cipher),'last4'=>substr(preg_replace('/\s+/','',trim((string)$value)),-4));
	}
	public function list_payees( WP_REST_Request $req ) {
		global $wpdb;
		$rows=Stockland_DB::read_results($wpdb->prepare('SELECT id,payee_code,full_name,payee_type,residency_status,registration_status,tin_last4,license_last4,contact_email,contact_phone,status,created_by,approved_by,approved_at,created_at FROM %i ORDER BY id DESC LIMIT 200',$this->table('hr_individual_payees')),ARRAY_A);
		return rest_ensure_response(is_array($rows)?$rows:array());
	}
	public function create_payee( WP_REST_Request $req ) {
		$p=$req->get_json_params();$name=sanitize_text_field((string)($p['full_name']??''));$type=strtoupper(sanitize_key((string)($p['payee_type']??'')));
		$types=array('DIGITAL_CREATOR','INFLUENCER','FREELANCER','SERVICE_PROVIDER','OTHER_INDIVIDUAL');
		if(''===$name||!in_array($type,$types,true))return $this->bad('hr_payee_input_invalid','A payee name and approved payee relationship type are required.',400);
		$tin=$this->encrypt_pii((string)($p['tin']??''));if(is_wp_error($tin))return $tin;$lic=$this->encrypt_pii((string)($p['business_license']??''));if(is_wp_error($lic))return $lic;
		$code=sanitize_text_field((string)($p['payee_code']??('PAYEE-'.strtoupper(bin2hex(random_bytes(5))))));
		$row=array('payee_code'=>$code,'full_name'=>$name,'payee_type'=>$type,'residency_status'=>strtoupper(sanitize_key((string)($p['residency_status']??'UNKNOWN'))),'registration_status'=>strtoupper(sanitize_key((string)($p['registration_status']??'UNKNOWN'))),'tin_ciphertext'=>$tin['ciphertext'],'tin_last4'=>$tin['last4'],'license_ciphertext'=>$lic['ciphertext'],'license_last4'=>$lic['last4'],'identity_resource_id'=>isset($p['identity_resource_id'])?(int)$p['identity_resource_id']:null,'contact_email'=>sanitize_email((string)($p['contact_email']??'')),'contact_phone'=>sanitize_text_field((string)($p['contact_phone']??'')),'status'=>'DRAFT','created_by'=>get_current_user_id());
		if(Stockland_DB::insert_record($this->table('hr_individual_payees'),$row)===false)return $this->bad('hr_payee_create_failed','Could not save individual payee.',409);
		global $wpdb;$id=(int)$wpdb->insert_id;if(!$this->event('individual-payee',$id,'PAYEE_CREATED',null,'DRAFT',array('type'=>$type,'tin_present'=>!empty($tin['ciphertext']),'license_present'=>!empty($lic['ciphertext']))))return $this->bad('hr_payee_audit_failed','Payee was created but its audit event failed; do not approve until reviewed.',500);
		return rest_ensure_response(array('id'=>$id,'payee_code'=>$code,'status'=>'DRAFT','message'=>'Individual payee only; not an employee and not a registered vendor by inference.'));
	}
	public function approve_payee( WP_REST_Request $req ) {
		$id=(int)$req['id'];$p=$req->get_json_params();$note=sanitize_textarea_field((string)($p['approval_note']??''));
		if(strlen(trim($note))<10)return $this->bad('hr_payee_approval_note_required','Independent payee identity approval note is required.');
		global $wpdb;if(false===Stockland_DB::execute_query('START TRANSACTION'))return $this->bad('hr_transaction_failed','Could not begin payee approval.',500);
		try{$r=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hr_individual_payees'),$id),ARRAY_A);if(!$r||$r['status']!=='DRAFT')throw new RuntimeException('Only a draft payee can be approved.');if((int)$r['created_by']===(int)get_current_user_id())throw new RuntimeException('Maker-checker: payee creator cannot approve the payee.');if(Stockland_DB::update_record($this->table('hr_individual_payees'),array('status'=>'APPROVED','approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql')),array('id'=>$id,'status'=>'DRAFT'))===false)throw new RuntimeException('Payee approval update failed.');if(!$this->event('individual-payee',$id,'PAYEE_APPROVED','DRAFT','APPROVED',array('approval_note'=>$note,'tin_last4'=>$r['tin_last4'],'license_last4'=>$r['license_last4'])))throw new RuntimeException('Payee approval audit failed.');if(false===Stockland_DB::execute_query('COMMIT'))throw new RuntimeException('Payee approval commit failed.');return rest_ensure_response(array('id'=>$id,'status'=>'APPROVED'));}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->bad('hr_payee_approval_failed',$e->getMessage(),409);}
	}
	public function create_payee_obligation( WP_REST_Request $req ) {
		$payee_id=(int)$req['id'];$p=$req->get_json_params();$type=strtoupper(sanitize_key((string)($p['obligation_type']??'')));$deliverables=$p['deliverables']??null;
		if(!in_array($type,array('DIGITAL_CONTENT','INFLUENCER_CAMPAIGN','FREELANCE_SERVICE','PROFESSIONAL_SERVICE','OTHER_INDIVIDUAL_PAYMENT'),true)||!is_array($deliverables)||!$deliverables)return $this->bad('hr_payee_obligation_invalid','Obligation type and specific deliverables are mandatory.',400);
		$payee=$this->row('hr_individual_payees',$payee_id);if(!$payee||$payee['status']!=='APPROVED')return $this->bad('hr_payee_not_approved','Approve the individual payee identity before creating a payable obligation.',409);
		$cash=Stockland_Money::decimal(Stockland_Money::minor($p['cash_amount']??'0.00'));$inkind=Stockland_Money::decimal(Stockland_Money::minor($p['in_kind_value']??'0.00'));
		if(Stockland_Money::minor($cash)<=0&&Stockland_Money::minor($inkind)<=0)return $this->bad('hr_payee_amount_required','Cash or documented in-kind value must be positive.');
		$code=sanitize_text_field((string)($p['obligation_code']??('OBL-'.strtoupper(bin2hex(random_bytes(5))))));
		$row=array('obligation_code'=>$code,'payee_id'=>$payee_id,'obligation_type'=>$type,'contract_reference'=>sanitize_text_field((string)($p['contract_reference']??'')),'campaign_reference'=>sanitize_text_field((string)($p['campaign_reference']??'')),'agreement_resource_id'=>isset($p['agreement_resource_id'])?(int)$p['agreement_resource_id']:null,'deliverables_json'=>$this->json($deliverables),'content_url'=>esc_url_raw((string)($p['content_url']??'')),'cash_amount'=>$cash,'in_kind_value'=>$inkind,'currency'=>sanitize_text_field((string)($p['currency']??'ETB')),'tax_decision_status'=>'LEGAL_REVIEW','status'=>'DRAFT','created_by'=>get_current_user_id());
		if(Stockland_DB::insert_record($this->table('hr_individual_payee_obligations'),$row)===false)return $this->bad('hr_payee_obligation_save_failed','Could not save payee obligation.',409);
		global $wpdb;$id=(int)$wpdb->insert_id;if(!$this->event('individual-payee-obligation',$id,'OBLIGATION_CREATED',null,'DRAFT',array('payee_id'=>$payee_id,'type'=>$type,'cash_amount'=>$cash,'in_kind_value'=>$inkind,'tax_decision_status'=>'LEGAL_REVIEW')))return $this->bad('hr_payee_obligation_audit_failed','Obligation record saved but its audit event failed; keep it unapproved.',500);
		return rest_ensure_response(array('id'=>$id,'obligation_code'=>$code,'status'=>'DRAFT','tax_decision_status'=>'LEGAL_REVIEW','message'=>'No tax/withholding rule is inferred from the payee dropdown. Qualified rule review is required before approval or payment.'));
	}
	public function decide_payee_obligation( WP_REST_Request $req ) {
		$id=(int)$req['id'];$p=$req->get_json_params();$decision=strtoupper(sanitize_key((string)($p['decision']??'')));$note=sanitize_textarea_field((string)($p['review_note']??''));$rule_id=(int)($p['tax_rule_set_id']??0);$expense=sanitize_text_field((string)($p['expense_account_code']??''));$payable=sanitize_text_field((string)($p['payable_account_code']??''));
		if(!in_array($decision,array('APPROVE','REJECT'),true)||strlen(trim($note))<20)return $this->bad('hr_payee_decision_invalid','APPROVE/REJECT and a substantive review note are required.',400);
		global $wpdb;if(false===Stockland_DB::execute_query('START TRANSACTION'))return $this->bad('hr_transaction_failed','Could not begin payee obligation decision.',500);
		try{$o=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hr_individual_payee_obligations'),$id),ARRAY_A);$payee=$o?Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',$this->table('hr_individual_payees'),(int)$o['payee_id']),ARRAY_A):null;if(!$o||!$payee||$o['status']!=='DRAFT')throw new RuntimeException('Only a draft obligation for an approved payee can be decided.');if((int)$o['created_by']===(int)get_current_user_id())throw new RuntimeException('Maker-checker: obligation preparer cannot approve it.');
			if($decision==='REJECT'){Stockland_DB::update_record($this->table('hr_individual_payee_obligations'),array('status'=>'REJECTED','tax_decision_status'=>'REJECTED'),array('id'=>$id,'status'=>'DRAFT'));if(!$this->event('individual-payee-obligation',$id,'OBLIGATION_REJECTED','DRAFT','REJECTED',array('review_note'=>$note)))throw new RuntimeException('Rejection audit failed.');if(false===Stockland_DB::execute_query('COMMIT'))throw new RuntimeException('Rejection commit failed.');return rest_ensure_response(array('id'=>$id,'status'=>'REJECTED'));}
			if($expense===''||$payable===''||!ctype_digit($expense)||!ctype_digit($payable))throw new RuntimeException('Finance must explicitly select valid existing expense and AP liability account codes; no account is guessed.');
			$rule=Stockland_DB::read_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND rule_type='INDIVIDUAL_PAYEE' AND review_status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s)",$this->table('hr_rule_sets'),$rule_id,current_time('Y-m-d'),current_time('Y-m-d')),ARRAY_A);
			if(!$rule)throw new RuntimeException('A separately approved INDIVIDUAL_PAYEE rule set effective on the decision date is required.');
			$rules=json_decode((string)$rule['rules_json'],true);if(!Stockland_HR_Lifecycle::has_qualified_legal_review($rules))throw new RuntimeException('The exact payee tax rule lacks independent qualified legal/tax opinion evidence.');
			$review=$rules['qualified_legal_review'];if(!in_array('withholding_method',$review['approved_parameters']??array(),true)||!in_array('cash_in_kind_tax_basis',$review['approved_parameters']??array(),true))throw new RuntimeException('The written opinion must explicitly approve withholding method and cash/in-kind tax basis.');
			$method=strtoupper((string)($rules['withholding_method']??''));if(!in_array($method,array('RATE_ON_GROSS_CASH','RATE_ON_CASH_PLUS_IN_KIND','NO_WITHHOLDING'),true))throw new RuntimeException('Approved payee tax rule has unsupported/absent withholding method.');
			$basis='RATE_ON_GROSS_CASH'===$method?$o['cash_amount']:$o['cash_amount'];if('RATE_ON_CASH_PLUS_IN_KIND'===$method)$basis=Stockland_Money::add($o['cash_amount'],$o['in_kind_value']);
			$withholding='0.00';if('NO_WITHHOLDING'!==$method){$rate=$rules['withholding_rate']??null;if(!is_string($rate)||!preg_match('/^(0|1)(\.[0-9]{1,6})?$/D',$rate))throw new RuntimeException('Approved tax rule withholding_rate is invalid.');$withholding=Stockland_Money::percentage($basis,$rate);}
			$net=Stockland_Money::subtract($o['cash_amount'],$withholding);if(Stockland_Money::minor($net)<0)throw new RuntimeException('Withholding exceeds the cash payable; legal review required.');
			$total=Stockland_Money::add($o['cash_amount'],$o['in_kind_value']);
			$journal=array(array('account_code'=>$expense,'debit'=>$total,'credit'=>'0.00','description'=>'Approved individual payee obligation '.$o['obligation_code'],'location_id'=>null),array('account_code'=>$payable,'debit'=>'0.00','credit'=>$total,'description'=>'Individual payee payable '.$o['obligation_code'],'location_id'=>null));
			$jid=Stockland_Accounting::post_journal(current_time('Y-m-d'),'HR_INDIVIDUAL_PAYEE_OBLIGATION',$id,'Individual payee obligation '.$o['obligation_code'],$journal,get_current_user_id());if(!$jid)throw new RuntimeException('AP accrual could not be posted through existing GL/PLEDGER.');
			$upd=array('status'=>'APPROVED','approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'),'tax_decision_status'=>'APPROVED','tax_decision_json'=>$this->json(array('rule_set_id'=>$rule_id,'rule_version'=>$rule['version_label'],'rule_source_sha256'=>$rule['source_sha256'],'legal_review'=>$review,'withholding_method'=>$method,'basis_amount'=>$basis,'rate'=>$rules['withholding_rate']??null,'withholding'=>$withholding,'net_cash_payable'=>$net,'review_note'=>$note)),'tax_rule_set_id'=>$rule_id,'tax_rule_version'=>$rule['version_label'],'withholding_amount'=>$withholding,'net_payable_amount'=>$net,'expense_account_code'=>$expense,'payable_account_code'=>$payable,'payable_journal_id'=>(int)$jid);
			if(Stockland_DB::update_record($this->table('hr_individual_payee_obligations'),$upd,array('id'=>$id,'status'=>'DRAFT'))===false)throw new RuntimeException('Could not save approved payee obligation decision.');
			if(!$this->event('individual-payee-obligation',$id,'OBLIGATION_APPROVED','DRAFT','APPROVED',array('rule_version'=>$rule['version_label'],'journal_id'=>(int)$jid,'gross'=>$total,'cash_net'=>$net,'withholding'=>$withholding),$rule_id,$rule['version_label']))throw new RuntimeException('Payee obligation audit failed.');
			if(false===Stockland_DB::execute_query('COMMIT'))throw new RuntimeException('Payee obligation approval commit failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>'APPROVED','tax_decision_status'=>'APPROVED','gross_obligation'=>$total,'withholding'=>$withholding,'net_cash_payable'=>$net,'journal_id'=>(int)$jid,'message'=>'AP/GL accrual saved. Payout and statutory remittance are separate.');
		}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->bad('hr_payee_obligation_approval_failed',$e->getMessage(),409);}
	}
	public function pay_payee_obligation( WP_REST_Request $req ) {
		$id=(int)$req['id'];$p=$req->get_json_params();$reference=sanitize_text_field((string)($p['external_payment_reference']??''));$method=strtoupper(sanitize_key((string)($p['payment_method']??'')));$bankRef=sanitize_text_field((string)($p['bank_statement_reference']??''));$withholdingReceipt=sanitize_text_field((string)($p['withholding_receipt_reference']??''));
		if($reference===''||!in_array($method,array('BANK','CASH'),true))return $this->bad('hr_payee_payment_input_invalid','External payment reference and BANK/CASH method are required.',400);
		global $wpdb;if(false===Stockland_DB::execute_query('START TRANSACTION'))return $this->bad('hr_transaction_failed','Could not begin individual payment transaction.',500);
		try{$o=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hr_individual_payee_obligations'),$id),ARRAY_A);if(!$o||$o['status']!=='APPROVED'||$o['tax_decision_status']!=='APPROVED')throw new RuntimeException('Only an approved obligation with approved legal/tax decision can be paid.');
			if(Stockland_Money::minor($o['withholding_amount']??0)>0&&$withholdingReceipt==='')throw new RuntimeException('A statutory withholding receipt/remittance reference is required before closing a withheld payee payment.');
			$net=Stockland_Money::decimal(Stockland_Money::minor($o['net_payable_amount']));$cashAcc='BANK'===$method?'1010':'1000';$journal=array(array('account_code'=>$o['payable_account_code'],'debit'=>$net,'credit'=>'0.00','description'=>'Clear individual payee net cash payable','location_id'=>null),array('account_code'=>$cashAcc,'debit'=>'0.00','credit'=>$net,'description'=>'Individual payee payment','location_id'=>null));
			if(Stockland_Money::minor($o['withholding_amount']??0)>0){$withholdingAccount=sanitize_text_field((string)($p['withholding_payable_account_code']??''));if($withholdingAccount===''||!ctype_digit($withholdingAccount))throw new RuntimeException('Finance must select the existing statutory withholding payable account code; no account is guessed.');$journal[]=array('account_code'=>$o['payable_account_code'],'debit'=>(string)$o['withholding_amount'],'credit'=>'0.00','description'=>'Clear withheld portion of payee payable','location_id'=>null);$journal[]=array('account_code'=>$withholdingAccount,'debit'=>'0.00','credit'=>(string)$o['withholding_amount'],'description'=>'Statutory withholding liability','location_id'=>null);}
			$jid=Stockland_Accounting::post_journal(current_time('Y-m-d'),'HR_INDIVIDUAL_PAYEE_PAYMENT',$id,'Individual payee payment '.$o['obligation_code'],$journal,get_current_user_id());if(!$jid)throw new RuntimeException('Payee cash payment journal was rejected.');
			$payee=(int)$o['payee_id'];$code='IPP-'.$o['obligation_code'];if(Stockland_DB::insert_record($this->table('hr_individual_payee_payments'),array('payment_code'=>$code,'obligation_id'=>$id,'payee_id'=>$payee,'gross_cash_amount'=>$o['cash_amount'],'withholding_amount'=>$o['withholding_amount']??'0.00','net_paid_amount'=>$net,'payment_method'=>$method,'external_payment_reference'=>$reference,'bank_statement_reference'=>$bankRef?:null,'withholding_receipt_reference'=>$withholdingReceipt?:null,'payment_journal_id'=>(int)$jid,'status'=>'RECORDED','recorded_by'=>get_current_user_id()))===false)throw new RuntimeException('Payee payment reference already exists or could not be saved.');
			if(Stockland_DB::update_record($this->table('hr_individual_payee_obligations'),array('status'=>'PAID'),array('id'=>$id,'status'=>'APPROVED'))===false)throw new RuntimeException('Could not close approved payee obligation.');
			if(!$this->event('individual-payee-obligation',$id,'OBLIGATION_PAID','APPROVED','PAID',array('payment_reference'=>$reference,'payment_method'=>$method,'journal_id'=>(int)$jid,'net_paid'=>$net,'withholding_receipt'=>$withholdingReceipt?:null),$o['tax_rule_set_id'],$o['tax_rule_version']))throw new RuntimeException('Payee payment audit failed.');
			if(false===Stockland_DB::execute_query('COMMIT'))throw new RuntimeException('Individual payment commit failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>'PAID','net_paid'=>$net,'journal_id'=>(int)$jid,'payment_reference'=>$reference));
		}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->bad('hr_payee_payment_failed',$e->getMessage(),409);}
	}

	public function list_resources( WP_REST_Request $req ) {
		global $wpdb;$role=strtoupper(sanitize_key((string)Stockland_Authority::role()));
		$rows=Stockland_DB::read_results($wpdb->prepare("SELECT id,resource_code,title,description,role_code,language_code,resource_type,version_label,file_sha256,file_name,mime_type,status,approved_by,approved_at,created_at FROM %i WHERE status='APPROVED' AND (UPPER(role_code)='ALL' OR UPPER(role_code)=%s) ORDER BY role_code,title",$this->table('hr_resources'),$role),ARRAY_A);
		return rest_ensure_response(is_array($rows)?$rows:array());
	}
	public function upload_resource( WP_REST_Request $req ) {
		$files=$req->get_file_params();$file=is_array($files)&&isset($files['file'])?$files['file']:null;$stored=Stockland_HR_Private_Vault::store_upload($file);if(is_wp_error($stored))return $stored;
		$p=$req->get_params();$title=sanitize_text_field((string)($p['title']??''));$type=strtoupper(sanitize_key((string)($p['resource_type']??'')));$role=strtoupper(sanitize_key((string)($p['role_code']??'ALL')));$lang=strtolower(sanitize_text_field((string)($p['language_code']??'en-am')));$version=sanitize_text_field((string)($p['version_label']??'1.0'));
		if($title===''||!in_array($type,array('JOB_DESCRIPTION','EMPLOYMENT_TERMS_DRAFT','CODE_OF_CONDUCT','SOP','FORM','TAX_RECEIPT','PENSION_RECEIPT','STATUTORY_RECEIPT','AGREEMENT','IDENTITY_EVIDENCE','OTHER','EXPENSE_RECEIPT'),true)){return $this->bad('hr_resource_metadata_invalid','Title and an approved resource type are required.',400);}
		$code=sanitize_text_field((string)($p['resource_code']??('HRDOC-'.strtoupper(bin2hex(random_bytes(5))))));
		$row=array('resource_code'=>$code,'title'=>$title,'description'=>sanitize_textarea_field((string)($p['description']??'')),'role_code'=>$role,'language_code'=>$lang,'resource_type'=>$type,'version_label'=>$version,'private_file_ref'=>$stored['private_file_ref'],'file_sha256'=>$stored['file_sha256'],'file_name'=>$stored['file_name'],'mime_type'=>$stored['mime_type'],'status'=>'DRAFT','created_by'=>get_current_user_id());
		if(Stockland_DB::insert_record($this->table('hr_resources'),$row)===false){$fileInfo=Stockland_HR_Private_Vault::resolve($stored['private_file_ref'],$stored['file_sha256']);if(!is_wp_error($fileInfo))@unlink($fileInfo['path']);return $this->bad('hr_resource_metadata_save_failed','Private file metadata could not be saved; the new file was discarded.',409);}
		global $wpdb;$id=(int)$wpdb->insert_id;if(!$this->event('hr-resource',$id,'RESOURCE_UPLOADED',null,'DRAFT',array('resource_code'=>$code,'resource_type'=>$type,'file_sha256'=>$stored['file_sha256'],'file_size'=>$stored['size_bytes'])))return $this->bad('hr_resource_audit_failed','Resource uploaded but audit event failed; do not approve it.',500);
		return rest_ensure_response(array('id'=>$id,'resource_code'=>$code,'status'=>'DRAFT','file_sha256'=>$stored['file_sha256'],'message'=>'Private file stored outside web root; Owner approval is required before users can download it.'));
	}
	public function approve_resource( WP_REST_Request $req ) {
		$id=(int)$req['id'];$p=$req->get_json_params();$note=sanitize_textarea_field((string)($p['approval_note']??''));if(strlen(trim($note))<10)return $this->bad('hr_resource_approval_note_required','Resource approval note is required.');
		global $wpdb;if(false===Stockland_DB::execute_query('START TRANSACTION'))return $this->bad('hr_transaction_failed','Could not begin resource approval.',500);
		try{$r=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hr_resources'),$id),ARRAY_A);if(!$r||$r['status']!=='DRAFT')throw new RuntimeException('Only draft HR resources can be approved.');if((int)$r['created_by']===(int)get_current_user_id())throw new RuntimeException('Maker-checker: a resource creator cannot approve their own resource.');$f=Stockland_HR_Private_Vault::resolve($r['private_file_ref'],$r['file_sha256']);if(is_wp_error($f))throw new RuntimeException($f->get_error_message());if(!hash_equals((string)$r['mime_type'],(string)$f['mime_type'])&&!($r['mime_type']==='application/zip'&&in_array(pathinfo((string)$r['file_name'],PATHINFO_EXTENSION),array('docx','xlsx'),true)))throw new RuntimeException('Stored MIME type no longer matches resource metadata.');if(Stockland_DB::update_record($this->table('hr_resources'),array('status'=>'APPROVED','approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql')),array('id'=>$id,'status'=>'DRAFT'))===false)throw new RuntimeException('Resource approval update failed.');if(!$this->event('hr-resource',$id,'RESOURCE_APPROVED','DRAFT','APPROVED',array('approval_note'=>$note,'file_sha256'=>$r['file_sha256'])))throw new RuntimeException('Resource approval audit failed.');if(false===Stockland_DB::execute_query('COMMIT'))throw new RuntimeException('Resource approval commit failed.');return rest_ensure_response(array('id'=>$id,'status'=>'APPROVED'));}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->bad('hr_resource_approval_failed',$e->getMessage(),409);}
	}
	public function download_resource( WP_REST_Request $req ) {
		$id=(int)$req['id'];$r=$this->row('hr_resources',$id);if(!$r||$r['status']!=='APPROVED')return $this->bad('hr_resource_not_available','Only approved resources are downloadable.',404);
		$role=strtoupper(sanitize_key((string)Stockland_Authority::role()));if('ALL'!==strtoupper((string)$r['role_code'])&&$role!==strtoupper((string)$r['role_code'])&&!$this->is_owner())return $this->bad('hr_resource_forbidden','This resource is not assigned to the authenticated role.',403);
		$f=Stockland_HR_Private_Vault::resolve($r['private_file_ref'],$r['file_sha256']);if(is_wp_error($f))return $f;
		$this->event('hr-resource',$id,'RESOURCE_DOWNLOADED','APPROVED','APPROVED',array('actor_id'=>get_current_user_id(),'file_sha256'=>$r['file_sha256']));
		if(!headers_sent()){nocache_headers();header('Content-Type: '.$f['mime_type']);header('Content-Length: '.(string)$f['size_bytes']);header('Content-Disposition: attachment; filename="'.str_replace('"','',sanitize_file_name((string)$r['file_name'])).'"');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, max-age=0');}
		readfile($f['path']);exit;
	}

	public function list_reimbursements( WP_REST_Request $req ) {
		global $wpdb;
		$user_id=get_current_user_id();
		if(Stockland_Authority::can_manage_hr()||$this->is_owner()){
			$rows=Stockland_DB::read_results($wpdb->prepare('SELECT id,claim_code,employee_id,location_id,expense_category,description,business_purpose,incurred_date,amount,currency,receipt_resource_id,status,submitted_at,approved_at,expense_account_code,payable_account_code,payment_method,payment_reference,bank_statement_reference,paid_at,created_by,created_at FROM %i ORDER BY id DESC LIMIT 200',$this->table('hr_employee_reimbursements')),ARRAY_A);
		}else{
			$emp=Stockland_DB::read_row($wpdb->prepare('SELECT id FROM %i WHERE wp_user_id=%d LIMIT 1',$this->table('employees'),$user_id),ARRAY_A);
			if(!$emp)return rest_ensure_response(array());
			$rows=Stockland_DB::read_results($wpdb->prepare('SELECT id,claim_code,employee_id,location_id,expense_category,description,business_purpose,incurred_date,amount,currency,receipt_resource_id,status,submitted_at,approved_at,payment_reference,paid_at,created_by,created_at FROM %i WHERE employee_id=%d ORDER BY id DESC LIMIT 100',$this->table('hr_employee_reimbursements'),(int)$emp['id']),ARRAY_A);
		}
		return rest_ensure_response(is_array($rows)?$rows:array());
	}
	public function create_reimbursement( WP_REST_Request $req ) {
		$p=$req->get_json_params();$employee_id=(int)($p['employee_id']??0);$resource_id=(int)($p['receipt_resource_id']??0);$amount=Stockland_Money::decimal(Stockland_Money::minor($p['amount']??'0.00'));$date=$this->date_value($p['incurred_date']??'');
		if($employee_id<1||$resource_id<1||false===$date||Stockland_Money::minor($amount)<=0)return $this->bad('hr_reimbursement_input_invalid','Employee, approved receipt resource, incurred date and positive amount are mandatory.',400);
		global $wpdb;
		$emp=Stockland_DB::read_row($wpdb->prepare('SELECT id,employee_id,wp_user_id,branch_location_id,status FROM %i WHERE id=%d',$this->table('employees'),$employee_id),ARRAY_A);
		if(!$emp||strtoupper((string)$emp['status'])!=='ACTIVE')return $this->bad('hr_reimbursement_employee_invalid','Reimbursement requires an active employee.',409);
		if(!Stockland_Authority::can_manage_hr()&&!$this->is_owner()&&(int)$emp['wp_user_id']!==(int)get_current_user_id())return $this->bad('hr_reimbursement_self_only','Employees may submit only their own claim; HR can submit on behalf of an employee.',403);
		if(!empty($emp['branch_location_id'])&&!Stockland_Authority::can_access_location((int)$emp['branch_location_id']))return $this->bad('hr_location_forbidden','Employee is outside the authenticated location scope.',403);
		$receipt=Stockland_DB::read_row($wpdb->prepare("SELECT id,status,resource_type,file_sha256 FROM %i WHERE id=%d",$this->table('hr_resources'),$resource_id),ARRAY_A);
		if(!$receipt||$receipt['status']!=='APPROVED'||$receipt['resource_type']!=='EXPENSE_RECEIPT')return $this->bad('hr_reimbursement_receipt_required','An approved private EXPENSE_RECEIPT resource is required.',409);
		$category=strtoupper(sanitize_key((string)($p['expense_category']??'')));$categories=array('TRAVEL','TRANSPORT','MEALS_BUSINESS','ACCOMMODATION','SUPPLIES','REPAIRS','TELEPHONE_DATA','DELIVERY','OTHER_APPROVED_BUSINESS');
		if(!in_array($category,$categories,true))return $this->bad('hr_reimbursement_category_invalid','Select a documented business-expense category.',400);
		$purpose=sanitize_textarea_field((string)($p['business_purpose']??''));$description=sanitize_textarea_field((string)($p['description']??''));
		if(strlen(trim($purpose))<10||strlen(trim($description))<5)return $this->bad('hr_reimbursement_purpose_required','Describe the business purpose and expense in enough detail for independent review.',400);
		$code=sanitize_text_field((string)($p['claim_code']??('EXP-'.strtoupper(bin2hex(random_bytes(5))))));
		$row=array('claim_code'=>$code,'employee_id'=>$employee_id,'location_id'=>!empty($emp['branch_location_id'])?(int)$emp['branch_location_id']:null,'expense_category'=>$category,'description'=>$description,'business_purpose'=>$purpose,'incurred_date'=>$date,'amount'=>$amount,'currency'=>sanitize_text_field((string)($p['currency']??'ETB')),'receipt_resource_id'=>$resource_id,'status'=>'SUBMITTED','submitted_by'=>get_current_user_id(),'submitted_at'=>current_time('mysql'),'created_by'=>get_current_user_id());
		if(Stockland_DB::insert_record($this->table('hr_employee_reimbursements'),$row)===false)return $this->bad('hr_reimbursement_save_failed','Could not save employee reimbursement claim.',409);
		$id=(int)$wpdb->insert_id;if(!$this->event('employee-reimbursement',$id,'REIMBURSEMENT_SUBMITTED',null,'SUBMITTED',array('employee_id'=>$employee_id,'category'=>$category,'amount'=>$amount,'receipt_sha256'=>$receipt['file_sha256'])))return $this->bad('hr_reimbursement_audit_failed','Claim saved but its audit event failed; it must not be approved until reviewed.',500);
		return rest_ensure_response(array('id'=>$id,'claim_code'=>$code,'status'=>'SUBMITTED','message'=>'Receipt-backed business expense claim created. It remains separate from salary, allowances and statutory payroll.'));
	}
	public function approve_reimbursement( WP_REST_Request $req ) {
		$id=(int)$req['id'];$p=$req->get_json_params();$note=sanitize_textarea_field((string)($p['reviewer_note']??''));$expense=sanitize_text_field((string)($p['expense_account_code']??''));$payable=sanitize_text_field((string)($p['payable_account_code']??''));
		if(strlen(trim($note))<15||!ctype_digit($expense)||!ctype_digit($payable))return $this->bad('hr_reimbursement_approval_fields','Independent review note and explicit existing expense/AP account codes are required.',400);
		global $wpdb;if(false===Stockland_DB::execute_query('START TRANSACTION'))return $this->bad('hr_transaction_failed','Could not begin reimbursement approval.',500);
		try{$r=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hr_employee_reimbursements'),$id),ARRAY_A);if(!$r||$r['status']!=='SUBMITTED')throw new RuntimeException('Only a submitted claim can be approved.');if((int)$r['created_by']===(int)get_current_user_id())throw new RuntimeException('Maker-checker: claim creator cannot approve their own claim.');
			$receipt=Stockland_DB::read_row($wpdb->prepare("SELECT id,status,resource_type,file_sha256 FROM %i WHERE id=%d",$this->table('hr_resources'),(int)$r['receipt_resource_id']),ARRAY_A);if(!$receipt||$receipt['status']!=='APPROVED'||$receipt['resource_type']!=='EXPENSE_RECEIPT')throw new RuntimeException('Claim receipt is not an approved private expense receipt.');
			$j=array(array('account_code'=>$expense,'debit'=>$r['amount'],'credit'=>'0.00','description'=>'Employee business-expense reimbursement '.$r['claim_code'],'location_id'=>$r['location_id']),array('account_code'=>$payable,'debit'=>'0.00','credit'=>$r['amount'],'description'=>'Employee reimbursement payable '.$r['claim_code'],'location_id'=>$r['location_id']));
			$jid=Stockland_Accounting::post_journal($r['incurred_date'],'HR_EMPLOYEE_REIMBURSEMENT',$id,'Employee reimbursement '.$r['claim_code'],$j,get_current_user_id());if(!$jid)throw new RuntimeException('Reimbursement accrual journal was rejected by GL/PLEDGER.');
			if(Stockland_DB::update_record($this->table('hr_employee_reimbursements'),array('status'=>'APPROVED','approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'),'expense_account_code'=>$expense,'payable_account_code'=>$payable,'accrual_journal_id'=>(int)$jid,'reviewer_note'=>$note),array('id'=>$id,'status'=>'SUBMITTED'))===false)throw new RuntimeException('Could not persist reimbursement approval.');
			if(!$this->event('employee-reimbursement',$id,'REIMBURSEMENT_APPROVED','SUBMITTED','APPROVED',array('expense_account_code'=>$expense,'payable_account_code'=>$payable,'accrual_journal_id'=>(int)$jid,'amount'=>$r['amount'],'receipt_sha256'=>$receipt['file_sha256'])))throw new RuntimeException('Reimbursement approval audit failed.');
			if(false===Stockland_DB::execute_query('COMMIT'))throw new RuntimeException('Reimbursement approval commit failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>'APPROVED','accrual_journal_id'=>(int)$jid,'message'=>'Expense/payable posted. Actual settlement is still separate.'));
		}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->bad('hr_reimbursement_approval_failed',$e->getMessage(),409);}
	}
	public function pay_reimbursement( WP_REST_Request $req ) {
		$id=(int)$req['id'];$p=$req->get_json_params();$method=strtoupper(sanitize_key((string)($p['payment_method']??'')));$reference=sanitize_text_field((string)($p['payment_reference']??''));$statement=sanitize_text_field((string)($p['bank_statement_reference']??''));
		if(!in_array($method,array('BANK','CASH'),true)||$reference===''||($method==='BANK'&&$statement===''))return $this->bad('hr_reimbursement_payment_evidence','Payment method/reference is required, plus bank-statement reference for a bank transfer.',400);
		global $wpdb;if(false===Stockland_DB::execute_query('START TRANSACTION'))return $this->bad('hr_transaction_failed','Could not begin reimbursement settlement.',500);
		try{$r=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$this->table('hr_employee_reimbursements'),$id),ARRAY_A);if(!$r||$r['status']!=='APPROVED')throw new RuntimeException('Only an approved unpaid reimbursement can be settled.');if((int)$r['created_by']===(int)get_current_user_id())throw new RuntimeException('Maker-checker: claim creator cannot settle their own reimbursement.');
			$bank='BANK'===$method?'1010':'1000';$j=array(array('account_code'=>$r['payable_account_code'],'debit'=>$r['amount'],'credit'=>'0.00','description'=>'Clear employee reimbursement payable '.$r['claim_code'],'location_id'=>$r['location_id']),array('account_code'=>$bank,'debit'=>'0.00','credit'=>$r['amount'],'description'=>'Reimbursement cash/bank settlement '.$r['claim_code'],'location_id'=>$r['location_id']));
			$jid=Stockland_Accounting::post_journal(current_time('Y-m-d'),'HR_EMPLOYEE_REIMBURSEMENT_PAYMENT',$id,'Employee reimbursement settlement '.$r['claim_code'],$j,get_current_user_id());if(!$jid)throw new RuntimeException('Reimbursement settlement journal failed.');
			if(Stockland_DB::update_record($this->table('hr_employee_reimbursements'),array('status'=>'PAID','payment_method'=>$method,'payment_reference'=>$reference,'bank_statement_reference'=>$statement?:null,'payment_journal_id'=>(int)$jid,'paid_by'=>get_current_user_id(),'paid_at'=>current_time('mysql')),array('id'=>$id,'status'=>'APPROVED'))===false)throw new RuntimeException('Could not persist reimbursement settlement.');
			if(!$this->event('employee-reimbursement',$id,'REIMBURSEMENT_PAID','APPROVED','PAID',array('payment_method'=>$method,'payment_reference'=>$reference,'bank_statement_reference'=>$statement?:null,'journal_id'=>(int)$jid,'amount'=>$r['amount'])))throw new RuntimeException('Reimbursement settlement audit failed.');
			if(false===Stockland_DB::execute_query('COMMIT'))throw new RuntimeException('Reimbursement settlement commit failed.');
			return rest_ensure_response(array('id'=>$id,'status'=>'PAID','journal_id'=>(int)$jid,'payment_reference'=>$reference));
		}catch(Throwable $e){Stockland_DB::execute_query('ROLLBACK');return $this->bad('hr_reimbursement_payment_failed',$e->getMessage(),409);}
	}

	public function list_social( WP_REST_Request $req ) {
		global $wpdb;
		$c=Stockland_DB::read_results($wpdb->prepare('SELECT id,campaign_code,title,platform,account_label,manager_user_id,start_date,end_date,budget,status,approved_by,approved_at,created_at FROM %i ORDER BY id DESC LIMIT 100',$this->table('hr_social_campaigns')),ARRAY_A);
		$s=Stockland_DB::read_results($wpdb->prepare('SELECT id,snapshot_code,campaign_id,platform,account_label,period_start,period_end,report_as_of,source_kind,source_reference,source_sha256,reach_count,view_count,watch_time_minutes,engagement_count,follower_delta,click_count,lead_count,spend_amount,attributed_sales,attribution_method,notes,captured_by,created_at FROM %i ORDER BY report_as_of DESC,id DESC LIMIT 200',$this->table('hr_social_snapshots')),ARRAY_A);
		return rest_ensure_response(array('campaigns'=>is_array($c)?$c:array(),'snapshots'=>is_array($s)?$s:array(),'reporting_mode'=>'MANUAL_OR_EXPORT_SNAPSHOT','warning'=>'Metrics are current only as of each recorded report_as_of timestamp; no live platform integration or auto-posting is enabled.'));
	}
	public function create_social_campaign( WP_REST_Request $req ) {
		$p=$req->get_json_params();$code=sanitize_text_field((string)($p['campaign_code']??('CMP-'.strtoupper(bin2hex(random_bytes(5)))));$title=sanitize_text_field((string)($p['title']??''));$platform=strtoupper(sanitize_key((string)($p['platform']??'')));$account=sanitize_text_field((string)($p['account_label']??''));
		if($title===''||$account===''||!in_array($platform,array('FACEBOOK','INSTAGRAM','TIKTOK','YOUTUBE','OTHER'),true))return $this->bad('hr_social_campaign_invalid','Campaign title, known platform and account label are required.',400);
		$start=$this->date_value($p['start_date']??'');$end=empty($p['end_date'])?null:$this->date_value($p['end_date']);if(false===$start||(false!==$end&&null!==$end&&$end<$start))return $this->bad('hr_social_campaign_dates_invalid','Campaign date range is invalid.',400);
		$budget=Stockland_Money::decimal(Stockland_Money::minor($p['budget']??'0.00'));if(Stockland_Money::minor($budget)<0)return $this->bad('hr_social_campaign_budget_invalid','Campaign budget cannot be negative.',400);
		$row=array('campaign_code'=>$code,'title'=>$title,'platform'=>$platform,'account_label'=>$account,'manager_user_id'=>isset($p['manager_user_id'])?(int)$p['manager_user_id']:null,'start_date'=>$start,'end_date'=>$end,'budget'=>$budget,'status'=>'DRAFT','created_by'=>get_current_user_id());
		if(Stockland_DB::insert_record($this->table('hr_social_campaigns'),$row)===false)return $this->bad('hr_social_campaign_save_failed','Could not save social campaign.',409);
		global $wpdb;$id=(int)$wpdb->insert_id;$this->event('social-campaign',$id,'CAMPAIGN_CREATED',null,'DRAFT',array('platform'=>$platform,'campaign_code'=>$code,'manager_user_id'=>$row['manager_user_id']));
		return rest_ensure_response(array('id'=>$id,'campaign_code'=>$code,'status'=>'DRAFT','reporting_mode'=>'MANUAL_OR_EXPORT_SNAPSHOT'));
	}
	public function create_social_snapshot( WP_REST_Request $req ) {
		$campaign=(int)$req['id'];$p=$req->get_json_params();$platform=strtoupper(sanitize_key((string)($p['platform']??'')));$account=sanitize_text_field((string)($p['account_label']??''));$start=$this->date_value($p['period_start']??'');$end=$this->date_value($p['period_end']??'');$asof=sanitize_text_field((string)($p['report_as_of']??''));
		if(!in_array($platform,array('FACEBOOK','INSTAGRAM','TIKTOK','YOUTUBE','OTHER'),true)||$account===''||false===$start||false===$end||$start>$end||!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}[ T][0-9]{2}:[0-9]{2}(:[0-9]{2})?$/D',$asof))return $this->bad('hr_social_snapshot_invalid','Platform, account, valid period and explicit report-as-of timestamp are required.',400);
		$source=sanitize_text_field((string)($p['source_reference']??''));$hash=strtolower(sanitize_text_field((string)($p['source_sha256']??'')));if($source===''||!preg_match('/^[a-f0-9]{64}$/',$hash))return $this->bad('hr_social_snapshot_evidence_required','Record the platform export/Insights reference and SHA-256 of the captured source evidence.');
		$metrics=array();foreach(array('reach_count','view_count','engagement_count','click_count','lead_count') as $k){$v=$p[$k]??null;if(null!==$v&&(!is_numeric($v)||(float)$v<0||floor((float)$v)!==(float)$v))return $this->bad('hr_social_metric_invalid','Count metrics must be non-negative integers.');$metrics[$k]=null===$v?null:(int)$v;}
		$dec=array();foreach(array('watch_time_minutes','spend_amount','attributed_sales') as $k){$v=$p[$k]??null;if(null!==$v){$n=Stockland_Money::decimal(Stockland_Money::minor($v));if(Stockland_Money::minor($n)<0)return $this->bad('hr_social_metric_invalid','Time and monetary metrics cannot be negative.');$dec[$k]=$n;}else{$dec[$k]=null;}}
		$delta=$p['follower_delta']??null;if(null!==$delta&&!is_numeric($delta))return $this->bad('hr_social_delta_invalid','Follower delta must be an integer.');$code=sanitize_text_field((string)($p['snapshot_code']??('SOC-'.strtoupper(bin2hex(random_bytes(5)))));$row=array_merge(array('snapshot_code'=>$code,'campaign_id'=>$campaign?:null,'platform'=>$platform,'account_label'=>$account,'period_start'=>$start,'period_end'=>$end,'report_as_of'=>str_replace('T',' ',$asof),'source_kind'=>'MANUAL_EXPORT','source_reference'=>$source,'source_sha256'=>$hash,'follower_delta'=>null===$delta?null:(int)$delta,'attribution_method'=>sanitize_text_field((string)($p['attribution_method']??'')),'notes'=>sanitize_textarea_field((string)($p['notes']??'')),'captured_by'=>get_current_user_id()),$metrics,$dec);
		if($campaign){$c=$this->row('hr_social_campaigns',$campaign);if(!$c)return $this->bad('hr_social_campaign_missing','Campaign not found.',404);if($c['platform']!==$platform||$c['account_label']!==$account)return $this->bad('hr_social_campaign_identity_mismatch','Snapshot platform/account must match its campaign.');}
		if(Stockland_DB::insert_record($this->table('hr_social_snapshots'),$row)===false)return $this->bad('hr_social_snapshot_save_failed','Could not save social reporting snapshot.',409);
		global $wpdb;$id=(int)$wpdb->insert_id;if(!$this->event('social-snapshot',$id,'SNAPSHOT_CAPTURED',null,'RECORDED',array('platform'=>$platform,'account_label'=>$account,'as_of'=>$asof,'source_sha256'=>$hash)))return $this->bad('hr_social_snapshot_audit_failed','Snapshot saved but audit event failed.',500);
		return rest_ensure_response(array('id'=>$id,'snapshot_code'=>$code,'status'=>'RECORDED','report_as_of'=>$asof,'reporting_mode'=>'MANUAL_EXPORT_NOT_REALTIME'));
	}
}