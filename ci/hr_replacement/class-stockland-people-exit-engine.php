<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Target-domain exit calculation. Only formula codes explicitly reviewed in
 * the active EXIT rule set are executed. Unsupported/unknown benefits block.
 */
final class Stockland_People_Exit_Engine {
	public static function calculate_case( $case_id ) {
		global $wpdb;
		$id=absint($case_id);if(!$id)throw new InvalidArgumentException('A valid exit-case ID is required.');
		$wpdb->query('START TRANSACTION');
		try{
			$t=Stockland_People_Replacement::table('people_exit_cases');
			$case=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',$t,$id),ARRAY_A);
			if(!$case||!in_array($case['status'],array('DRAFT','SUBMITTED'),true))throw new RuntimeException('Only a new draft or submitted exit case may be calculated. Previous calculations are not overwritten.');
			$employment=Stockland_DB::read_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE',Stockland_People_Replacement::table('people_employments'),(int)$case['employment_id']),ARRAY_A);
			if(!$employment)throw new RuntimeException('Employment relationship is missing.');
			$start=(string)$employment['start_date'];$separation=(string)$case['separation_date'];
			if(!$start||$separation<$start||!self::valid_date($separation))throw new RuntimeException('Exit separation date is invalid for the employment.');
			$rule=self::exit_rule($separation);$params=json_decode((string)$rule['parameters_json'],true);
			if(!is_array($params)||strtoupper((string)($params['legal_review_gate']??''))!=='SIGNED')throw new RuntimeException('The EXIT rule pack does not have signed legal review.');
			$items=$params['exit_components']??null;if(!is_array($items)||!$items)throw new RuntimeException('Approved EXIT rule pack has no explicitly reviewed settlement formula list.');
			$lastRun=Stockland_DB::read_row($wpdb->prepare(
				"SELECT r.id,r.period_end FROM %i r INNER JOIN %i l ON l.run_id=r.id
				WHERE l.employment_id=%d AND r.status='SETTLED' AND r.period_end<%s
				ORDER BY r.period_end DESC,r.id DESC LIMIT 1",
				Stockland_People_Replacement::table('people_payroll_runs'),
				Stockland_People_Replacement::table('people_payroll_lines'),
				(int)$employment['id'],$separation
			),ARRAY_A);
			if(!$lastRun)throw new RuntimeException('A last settled payroll cutoff is not available. Finance must reconcile payroll before final settlement.');
			$from=date('Y-m-d',strtotime($lastRun['period_end'].' +1 day'));
			$to=$separation;
			$assignments=Stockland_DB::read_results($wpdb->prepare(
				"SELECT * FROM %i WHERE employment_id=%d AND status='EFFECTIVE' AND approved_by IS NOT NULL
				AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from,id",
				Stockland_People_Replacement::table('people_compensation_assignments'),(int)$employment['id'],$to,$from
			),ARRAY_A);
			if(!is_array($assignments)||!$assignments)throw new RuntimeException('Approved effective compensation is missing after the last settled payroll.');
			$dailyByDate=array();$baseTotal='0.00';$cursor=$from;
			while($cursor<=$to){
				$matches=array();foreach($assignments as $a)if($a['effective_from']<=$cursor&&(empty($a['effective_to'])||$a['effective_to']>=$cursor))$matches[]=$a;
				if(count($matches)!==1)throw new RuntimeException('Missing or overlapping effective compensation on '.$cursor.'.');
				$a=$matches[0];$days=(int)date('t',strtotime($cursor));$daily=self::ratio($a['base_salary'],1,$days);$dailyByDate[$cursor]=array('assignment_id'=>(int)$a['id'],'base_salary'=>$a['base_salary'],'daily_base'=>$daily);
				$baseTotal=Stockland_Money::add($baseTotal,$daily);$cursor=date('Y-m-d',strtotime($cursor.' +1 day'));
			}
			$leaveBalance=self::leave_balance((int)$employment['id']);
			if(Stockland_Money::minor($leaveBalance)<0)throw new RuntimeException('Posted leave ledger balance is negative. Resolve the ledger before final settlement.');
			$dailyAtExit=$dailyByDate[$to]['daily_base']??null;if($dailyAtExit===null)throw new RuntimeException('Daily base at separation date is unresolved.');
			$existing=Stockland_DB::read_results($wpdb->prepare("SELECT id FROM %i WHERE exit_case_id=%d",Stockland_People_Replacement::table('people_exit_items'),$id),ARRAY_A);
			if($existing)throw new RuntimeException('Exit case already has settlement lines. Use a separately linked recalculation/correction, not destructive overwrite.');
			$created=array();$total='0.00';$signable=array();
			foreach($items as $formula){
				if(!is_array($formula)||empty($formula['code'])||empty($formula['method'])||empty($formula['source_clause']))throw new RuntimeException('Exit formula is missing its code, method or legal source clause.');
				$code=strtoupper(sanitize_key((string)$formula['code']));$method=strtoupper((string)$formula['method']);$sign=strtoupper((string)($formula['sign']??'EARNING'));
				$amount='0.00';$basis=array();
				if($method==='PRO_RATA_CALENDAR_DAYS'){
					if($code!=='FINAL_SALARY')throw new RuntimeException('PRO_RATA_CALENDAR_DAYS is only approved for FINAL_SALARY.');
					$amount=$baseTotal;$basis=array('from'=>$from,'to'=>$to,'days'=>count($dailyByDate),'daily_assignment_ids'=>array_column($dailyByDate,'assignment_id'));
				}elseif($method==='DAILY_BASE_X_POSTED_LEAVE_BALANCE'){
					if($code!=='UNUSED_LEAVE')throw new RuntimeException('DAILY_BASE_X_POSTED_LEAVE_BALANCE is only approved for UNUSED_LEAVE.');
					$amount=Stockland_Money::multiply($dailyAtExit,$leaveBalance);$basis=array('leave_balance'=>$leaveBalance,'daily_base'=>$dailyAtExit,'leave_method'=>'POSTED_LEAVE_LEDGER');
				}elseif($method==='DAILY_BASE_X_COMPLETED_SERVICE_YEAR_FACTOR'){
					$perYearDays=$formula['days_per_completed_year']??null;if(!is_numeric($perYearDays)||(float)$perYearDays<0)throw new RuntimeException('A reviewed days_per_completed_year factor is required.');
					$years=max(0,(int)floor((strtotime($separation)-strtotime($start))/(365.2425*86400)));
					$amount=Stockland_Money::multiply(Stockland_Money::multiply($dailyAtExit,(string)$perYearDays),(string)$years);
					$basis=array('completed_service_years'=>$years,'days_per_completed_year'=>(string)$perYearDays,'daily_base'=>$dailyAtExit);
				}else{
					throw new RuntimeException('Unsupported exit formula code: '.$method.'. Do not substitute a guessed legal formula.');
				}
				if($sign==='DEDUCTION'){$amount=Stockland_Money::decimal(-Stockland_Money::minor($amount));}
				elseif($sign!=='EARNING')throw new RuntimeException('Exit formula sign must be EARNING or DEDUCTION.');
				$reference='RULESET-'.$rule['rule_set_code'].'-'.$rule['version_label'].'-'.$code;
				$calc=array('method'=>$method,'formula'=>$formula,'inputs'=>$basis,'source_sha256'=>$rule['source_sha256'],'source_clause'=>$formula['source_clause'],'last_settled_payroll_period_end'=>$lastRun['period_end']);
				$wpdb->insert(Stockland_People_Replacement::table('people_exit_items'),array(
					'record_uuid'=>wp_generate_uuid4(),'status'=>'CALCULATED','version_no'=>1,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'),
					'exit_case_id'=>$id,'item_code'=>'EXIT-'.$id.'-'.$code,'item_type'=>$code,'amount'=>$amount,'rule_set_id'=>(int)$rule['id'],'source_reference'=>$reference,'calculation_json'=>wp_json_encode($calc)
				));
				if(!$wpdb->insert_id||$wpdb->last_error)throw new RuntimeException('Exit settlement item could not be persisted: '.$code);
				$created[]=(int)$wpdb->insert_id;$total=Stockland_Money::add($total,$amount);$signable[]=$code;
			}
			$changed=$wpdb->update($t,array('status'=>'CALCULATED','settlement_run_id'=>(int)$lastRun['id'],'version_no'=>(int)$case['version_no']+1,'updated_by'=>get_current_user_id(),'updated_at'=>current_time('mysql')),array('id'=>$id,'version_no'=>(int)$case['version_no']));
			if(false===$changed||$changed===0)throw new RuntimeException('Exit case changed concurrently.');
			if(!Stockland_People_Replacement::event('exit-case',$id,'SETTLEMENT_CALCULATED',$case['status'],'CALCULATED','Calculated using approved target-domain sources',array('item_ids'=>$created,'formula_codes'=>$signable,'total'=>$total,'rule_set_id'=>(int)$rule['id'],'last_settled_run_id'=>(int)$lastRun['id']),(int)$employment['location_id']?:null))throw new RuntimeException('Exit audit event failed.');
			if(false===$wpdb->query('COMMIT'))throw new RuntimeException('Exit calculation transaction commit failed.');
			return array('id'=>$id,'status'=>'CALCULATED','items'=>$created,'total'=>$total,'formula_codes'=>$signable,'rule_version'=>$rule['version_label'],'message'=>'Calculated from target-domain compensation, leave ledger, last settled payroll and the signed EXIT rule set. Owner approval and payment remain separate.');
		}catch(Throwable $e){$wpdb->query('ROLLBACK');throw $e;}
	}
	private static function exit_rule($date){
		global $wpdb;$rows=Stockland_DB::read_results($wpdb->prepare("SELECT * FROM %i WHERE rule_type='EXIT' AND status='ACTIVE' AND review_status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s)",Stockland_People_Replacement::table('people_rule_sets'),$date,$date),ARRAY_A);
		if(!is_array($rows)||count($rows)!==1)throw new RuntimeException('Exactly one active independently reviewed EXIT rule set must cover the separation date.');
		$r=$rows[0];if(empty($r['reviewer_user_id'])||empty($r['reviewer_qualification'])||empty($r['review_reference'])||empty($r['reviewed_at'])||(int)$r['created_by']===(int)$r['reviewer_user_id']||(int)$r['approved_by']===(int)$r['reviewer_user_id']||(int)$r['created_by']===(int)$r['approved_by']||!preg_match('/^[a-f0-9]{64}$/i',(string)$r['source_sha256']))throw new RuntimeException('EXIT rule set lacks distinct preparer, qualified reviewer, Owner approval and source hash.');
		return $r;
	}
	private static function leave_balance($employment_id){
		global $wpdb;$rows=Stockland_DB::read_results($wpdb->prepare("SELECT id,event_kind,units,reversal_of_id FROM %i WHERE employment_id=%d AND status='POSTED' ORDER BY id",Stockland_People_Replacement::table('people_leave_events'),$employment_id),ARRAY_A);
		if(!is_array($rows))throw new RuntimeException('Could not read posted leave ledger.');
		$byId=array();$balance='0.00';
		foreach($rows as $row){$kind=strtoupper((string)$row['event_kind']);$units=Stockland_Money::decimal(Stockland_Money::minor($row['units']));
			if(in_array($kind,array('ACCRUAL','GRANT','CARRY_FORWARD'),true)){$signed=$units;}
			elseif($kind==='USE'){$signed=Stockland_Money::decimal(-Stockland_Money::minor($units));}
			elseif($kind==='REVERSAL'){$original=$byId[(int)$row['reversal_of_id']]??null;if($original===null)throw new RuntimeException('Leave reversal does not point to an earlier posted source entry.');$signed=Stockland_Money::decimal(-Stockland_Money::minor($original));}
			else continue;
			$byId[(int)$row['id']]=$signed;$balance=Stockland_Money::add($balance,$signed);
		}
		return $balance;
	}
	private static function ratio($amount,$numerator,$denominator){$d=(int)$denominator;$n=(int)$numerator;if($d<=0||$n<0)throw new InvalidArgumentException('Invalid pro-rata ratio.');$v=Stockland_Money::minor($amount)*$n;return Stockland_Money::decimal(intdiv($v+intdiv($d,2),$d));}
	private static function valid_date($s){$d=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$s);return $d&&$d->format('Y-m-d')===$s;}
}