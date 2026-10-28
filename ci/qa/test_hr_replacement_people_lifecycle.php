<?php
/**
 * Synthetic end-to-end contract test for the Stage 4 People lifecycle.
 * This is an in-memory test; it does not connect to WordPress or MariaDB.
 */
define( 'STOCKLAND_HR_REPLACEMENT_TESTING', true );
require_once __DIR__ . '/../hr_payroll_replacement_runtime/Stockland_HR_Replacement_People_Contract.php';
require_once __DIR__ . '/../hr_payroll_replacement_runtime/Stockland_HR_Replacement_People_Repository.php';
require_once __DIR__ . '/../hr_payroll_replacement_runtime/Stockland_HR_Replacement_People_Service.php';
require_once __DIR__ . '/../hr_payroll_replacement_runtime/Stockland_HR_Replacement_People_Schema.php';

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = false ) {
		if ( 'Y-m-d' === $type ) { return '2026-10-28'; }
		return '2026-10-28 00:00:00';
	}
}

final class HRXP_People_Memory_Repo implements Stockland_HR_Replacement_People_Repository {
	public $data = array();
	public $events = array();
	public $ready = true;
	private $next = array();
	public function __construct() {
		foreach ( array( 'people','candidates','applications','interviews','employments','contracts','documents','onboarding','requisitions','positions','plan_lines' ) as $e ) { $this->data[$e]=array(); $this->next[$e]=1; }
		$this->data['positions'][22]=array('id'=>22,'position_code'=>'POS-OPS-01','org_unit_id'=>2,'location_id'=>1,'status'=>'ACTIVE','version_no'=>1);
		$this->data['requisitions'][5]=array('id'=>5,'requisition_code'=>'REQ-OPS-01','plan_line_id'=>4,'position_id'=>22,'openings'=>1,'filled_openings'=>0,'location_id'=>1,'status'=>'OPEN','version_no'=>1,'created_by'=>9,'updated_by'=>9);
		$this->next['positions']=23; $this->next['requisitions']=6;
	}
	public function transaction( callable $callback ) {
		$before = unserialize( serialize( array( $this->data, $this->events, $this->next ) ) );
		try { return $callback(); }
		catch ( Throwable $e ) { $this->data=$before[0]; $this->events=$before[1]; $this->next=$before[2]; throw $e; }
	}
	public function find( $entity, $id, $for_update = false ) {
		return isset($this->data[$entity][(int)$id]) ? $this->data[$entity][(int)$id] : null;
	}
	public function list_records( $entity, $filters = array(), $limit = 50 ) {
		$rows=array_values($this->data[$entity]??array());
		foreach($filters as $field=>$value){if(null===$value||''===$value)continue;$rows=array_values(array_filter($rows,function($r)use($field,$value){return array_key_exists($field,$r)&&(string)$r[$field]===(string)$value;}));}
		return array_slice($rows,0,max(1,min(100,(int)$limit)));
	}
	public function insert( $entity, $data ) {
		if(!isset($this->data[$entity]))throw new InvalidArgumentException('unknown memory table');
		foreach($this->data[$entity] as $row){
			if(isset($data['person_code'],$row['person_code'])&&$data['person_code']===$row['person_code'])throw new DomainException('HRXP_UNIQUE_CONFLICT: duplicate person code',409);
			if(isset($data['candidate_code'],$row['candidate_code'])&&$data['candidate_code']===$row['candidate_code'])throw new DomainException('HRXP_UNIQUE_CONFLICT: duplicate candidate code',409);
			if(isset($data['application_code'],$row['application_code'])&&$data['application_code']===$row['application_code'])throw new DomainException('HRXP_UNIQUE_CONFLICT: duplicate application code',409);
			if(isset($data['candidate_id'],$data['requisition_id'],$row['candidate_id'],$row['requisition_id'])&&(int)$data['candidate_id']===(int)$row['candidate_id']&&(int)$data['requisition_id']===(int)$row['requisition_id'])throw new DomainException('HRXP_UNIQUE_CONFLICT: duplicate candidate application',409);
			if(isset($data['interview_code'],$row['interview_code'])&&$data['interview_code']===$row['interview_code'])throw new DomainException('HRXP_UNIQUE_CONFLICT: duplicate interview code',409);
			if(isset($data['application_id'],$data['round_no'],$row['application_id'],$row['round_no'],$data['interview_code'])&&isset($data['round_no'])&& (int)$data['application_id']===(int)$row['application_id']&&(int)$data['round_no']===(int)$row['round_no'])throw new DomainException('HRXP_UNIQUE_CONFLICT: duplicate interview round',409);
			if(isset($data['employment_code'],$row['employment_code'])&&$data['employment_code']===$row['employment_code'])throw new DomainException('HRXP_UNIQUE_CONFLICT: duplicate employment code',409);
			if(isset($data['contract_code'],$row['contract_code'])&&$data['contract_code']===$row['contract_code'])throw new DomainException('HRXP_UNIQUE_CONFLICT: duplicate contract code',409);
			if(isset($data['employment_id'],$data['task_type'],$row['employment_id'],$row['task_type'])&&(int)$data['employment_id']===(int)$row['employment_id']&&$data['task_type']===$row['task_type'])throw new DomainException('HRXP_UNIQUE_CONFLICT: duplicate onboarding task',409);
		}
		$id=$this->next[$entity]++;$data['id']=$id;$this->data[$entity][$id]=$data;return $data;
	}
	public function update_expected( $entity, $id, $version, $status, $data ) {
		$row=$this->find($entity,$id);
		if(!$row||(int)$row['version_no']!==(int)$version||(string)$row['status']!==(string)$status)throw new DomainException('HRXP_STALE_OR_MISSING: changed',409);
		$new=array_merge($row,$data);$this->data[$entity][(int)$id]=$new;return $new;
	}
	public function find_event_by_key( $key ) { return $this->events[$key]??null; }
	public function append_event( $event ) {
		if(isset($this->events[$event['idempotency_key']]))throw new DomainException('HRXP_IDEMPOTENCY_CONFLICT: duplicate',409);
		$this->events[$event['idempotency_key']]=$event;return count($this->events);
	}
	public function person_email_exists( $email ) {
		foreach($this->data['people'] as $p)if(!empty($p['email'])&&strtolower($p['email'])===strtolower($email))return true;
		return false;
	}
	public function requisition( $id, $for_update = false ) { return $this->find('requisitions',$id,$for_update); }
	public function requisition_slot_available( $id ) {
		$r=$this->requisition($id);if(!$r||'OPEN'!==$r['status'])return false;
		$reserved=0;foreach($this->data['applications'] as $a)if((int)$a['requisition_id']===(int)$id&&in_array($a['stage'],array('SELECTED','OFFERED','ACCEPTED'),true))$reserved++;
		return ((int)$r['openings']-(int)$r['filled_openings']-$reserved)>0;
	}
	public function mandatory_onboarding_complete( $employment_id ) {
		foreach($this->data['onboarding'] as $t)if((int)$t['employment_id']===(int)$employment_id&&1===(int)$t['mandatory']&&'COMPLETE'!==$t['status'])return false;
		return true;
	}
	public function tables_ready() { return $this->ready; }
}

final class HRXP_People_Fake_Access implements Stockland_HR_Replacement_People_Access {
	public $actor=1; public $prepare=true; public $approve=false; public $view=true; public $locations=array(1=>true);
	public function actor_id(){return $this->actor;}
	public function can_view(){return $this->view;}
	public function can_prepare(){return $this->prepare;}
	public function can_approve(){return $this->approve;}
	public function can_access_location($id){return !empty($this->locations[(int)$id]);}
}
final class HRXP_People_Fake_Vault implements Stockland_HR_Replacement_Private_Vault {
	public $available=true;
	public function verify_reference($private_reference,$expected_sha256,$actor_id) {
		return $this->available?array('verified'=>true,'public_access'=>false,'content_sha256'=>strtolower($expected_sha256),'verification_reference'=>'vault-audit:TEST-VERIFY-001'):array('verified'=>false,'public_access'=>true);
	}
}
function hrxp_people_expect( $code, callable $fn ) {
	try { $fn(); } catch ( DomainException $e ) {
		if ( 0 === strpos( $e->getMessage(), $code . ':' ) ) { return; }
		throw new RuntimeException( 'Expected ' . $code . ', got ' . $e->getMessage() );
	}
	throw new RuntimeException( 'Expected exception ' . $code . ' but action succeeded.' );
}
function hrxp_people_assert( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } }

$checks=0;
$ddl=Stockland_HR_Replacement_People_Schema::ddl('wp_stockland_hrx_');
hrxp_people_assert(count($ddl)===8,'Stage 4 contains eight additive logical tables for this slice, not a final target table count');$checks++;
hrxp_people_assert(strpos(implode("\n",$ddl),'CREATE TABLE wp_stockland_hrx_private_documents')!==false,'private document metadata is isolated under HRX prefix');$checks++;
hrxp_people_assert(strpos(implode("\n",$ddl),'stock_payroll_lines')===false,'no legacy payroll table is declared in target DDL');$checks++;
hrxp_people_expect('HRXP_SCHEMA_PREFIX_INVALID',function(){Stockland_HR_Replacement_People_Schema::ddl('wp_;DROP');});$checks++;
hrxp_people_expect('HRXP_PUBLIC_DOCUMENT_REFERENCE',function(){Stockland_HR_Replacement_People_Contract::private_reference('https://public.example/contract.docx');});$checks++;

$repo=new HRXP_People_Memory_Repo();$access=new HRXP_People_Fake_Access();$vault=new HRXP_People_Fake_Vault();
$service=new Stockland_HR_Replacement_People_Service($repo,$access,$vault);
$candidatePayload=array('person_code'=>'PERS-001','candidate_code'=>'CAND-001','first_name'=>'Mekdes','last_name'=>'Tadesse','email'=>'mekdes.candidate@example.test','phone'=>'+251911000111','privacy_acknowledged'=>true,'privacy_notice_version'=>'HR-PRIVACY-1','collection_purpose'=>'Recruitment and candidate communication','source_channel'=>'COMPANY_WEBSITE','retention_review_on'=>'2027-10-28');
$candidate=$service->create_candidate($candidatePayload,'candidate.create.0001');$checks++;
hrxp_people_assert(!$candidate['replayed']&&$candidate['candidate']['status']==='ACTIVE','candidate created only after privacy acknowledgement and retention date');$checks++;
$replay=$service->create_candidate($candidatePayload,'candidate.create.0001');$checks++;
hrxp_people_assert($replay['replayed']===true,'same idempotency key and payload replays safely');$checks++;
$conflictPayload=$candidatePayload;$conflictPayload['last_name']='Different';
hrxp_people_expect('HRXP_IDEMPOTENCY_CONFLICT',function()use($service,$conflictPayload){$service->create_candidate($conflictPayload,'candidate.create.0001');});$checks++;
$duplicate=$candidatePayload;$duplicate['person_code']='PERS-002';$duplicate['candidate_code']='CAND-002';
hrxp_people_expect('HRXP_POSSIBLE_DUPLICATE_PERSON',function()use($service,$duplicate){$service->create_candidate($duplicate,'candidate.create.0002');});$checks++;
$withoutNotice=$candidatePayload;$withoutNotice['person_code']='PERS-003';$withoutNotice['candidate_code']='CAND-003';$withoutNotice['email']='other@example.test';$withoutNotice['privacy_acknowledged']=false;
hrxp_people_expect('HRXP_PRIVACY_ACK_REQUIRED',function()use($service,$withoutNotice){$service->create_candidate($withoutNotice,'candidate.create.0003');});$checks++;

$app=$service->create_application(array('application_code'=>'APP-001','candidate_id'=>$candidate['candidate']['id'],'requisition_id'=>5,'location_id'=>1),'application.create.0001');$checks++;
hrxp_people_assert($app['record']['stage']==='APPLIED','application links candidate to a currently open vacancy');$checks++;
hrxp_people_expect('HRXP_TRANSITION_NOT_ALLOWED',function()use($service,$app){$service->move_application(array('application_id'=>$app['record']['id'],'to_stage'=>'SELECTED','version_no'=>1,'human_decision'=>true,'rationale'=>'This is a long rationale but should not skip every review stage.'),'application.invalid.0001');});$checks++;

$move=function($to,$version,$key,$decision=false,$rationale='')use($service,$app){
	return $service->move_application(array('application_id'=>$app['record']['id'],'to_stage'=>$to,'version_no'=>$version,'human_decision'=>$decision,'rationale'=>$rationale),$key);
};
$screen=$move('SCREENING',1,'application.screen.0001');$checks++;
$screenReplay=$move('SCREENING',1,'application.screen.0001');hrxp_people_assert($screenReplay['replayed']===true,'application stage requests replay idempotently after the row version advances');$checks++;
$move('INTERVIEW',2,'application.interview.0001');$checks++;
$interview=$service->record_interview(array('interview_code'=>'INT-001','application_id'=>$app['record']['id'],'round_no'=>1,'rubric_version'=>'RUBRIC-1','score'=>'82.50','recommendation'=>'RECOMMEND','scheduled_at'=>'2026-10-29 09:30:00','notes_reference'=>'vault://interviews/INT-001','evidence_sha256'=>str_repeat('a',64)),'interview.record.0001');$checks++;
hrxp_people_assert($interview['record']['interviewer_id']===1&&$interview['record']['score']==='82.50','interview is attributed to a human interviewer and versioned rubric');$checks++;
$move('REFERENCE_REVIEW',3,'application.reference.0001');$checks++;
$move('RECOMMENDED',4,'application.recommend.0001');$checks++;
hrxp_people_expect('HRXP_MAKER_CHECKER',function()use($move){$move('SELECTED',5,'application.select.same.0001',true,'Documented panel recommendation supports the hiring decision.');});$checks++;

$access->actor=2;$access->approve=true;
$move('SELECTED',5,'application.select.owner.0001',true,'Documented human panel review supports selection against the approved vacancy.');
$checks++;
$move('OFFERED',6,'application.offer.0001');
$checks++;
$move('ACCEPTED',7,'application.accept.owner.0001',true,'The candidate explicitly accepted the offer and the authorized reviewer recorded evidence.');
$checks++;
$access->actor=1;$access->approve=false;
$hire=$service->create_pending_hire(array('application_id'=>$app['record']['id'],'employment_code'=>'EMP-0001','contract_code'=>'CON-0001','legal_employer_code'=>'STOCKLAND-ET','start_date'=>'2026-11-01','end_date'=>null,'employment_type'=>'FULL_TIME','contract_type'=>'FULL_TIME','probation_end'=>'2027-02-01','location_id'=>1),'hire.create.0001');$checks++;
hrxp_people_assert($hire['activation_allowed']===false&&$hire['employment']['status']==='PENDING_CONTRACT'&&$hire['onboarding_tasks']===7,'pending hire does not automatically activate employee or access');$checks++;
hrxp_people_assert($repo->data['requisitions'][5]['filled_openings']===1&&$repo->data['applications'][$app['record']['id']]['stage']==='HIRED','hire creation consumes the accepted vacancy reservation atomically');$checks++;

$doc=$service->register_private_document(array('document_code'=>'DOC-CON-001','subject_type'=>'CONTRACT','subject_id'=>$hire['contract']['id'],'document_type'=>'EMPLOYMENT_CONTRACT','private_reference'=>'vault://hr/contracts/CON-0001','content_sha256'=>str_repeat('b',64),'issued_on'=>'2026-10-28','expires_on'=>null),'document.register.0001');$checks++;
$access->actor=2;$access->approve=true;
$withoutVault=new Stockland_HR_Replacement_People_Service($repo,$access);
hrxp_people_expect('HRXP_PRIVATE_VAULT_NOT_VERIFIED',function()use($withoutVault,$doc){$withoutVault->verify_private_document(array('document_id'=>$doc['record']['id'],'version_no'=>1,'review_note'=>'Reviewed file metadata; underlying private vault was not proven.'),'document.verify.no-vault.01');});$checks++;
$verified=$service->verify_private_document(array('document_id'=>$doc['record']['id'],'version_no'=>1,'review_note'=>'Verified the private object, ACL, and SHA-256 through the approved vault test.'),'document.verify.owner.01');$checks++;
hrxp_people_assert($verified['record']['status']==='VERIFIED'&&$verified['record']['version_no']===2,'document verification requires independent maker-checker and private-vault proof');$checks++;
$access->actor=1;
$signedPayload=array('contract_id'=>$hire['contract']['id'],'document_id'=>$doc['record']['id'],'signed_on'=>'2026-10-28','version_no'=>1);
$signed=$service->sign_contract($signedPayload,'contract.sign.0001');$checks++;
$signedReplay=$service->sign_contract($signedPayload,'contract.sign.0001');hrxp_people_assert($signedReplay['replayed']===true,'signing request safely replays after contract version advances');$checks++;
hrxp_people_assert($signed['record']['status']==='SIGNED'&&$signed['record']['private_document_id']===$doc['record']['id'],'contract cannot be signed without verified linked private document');$checks++;
$access->actor=2;$access->approve=true;
$approvalPayload=array('contract_id'=>$hire['contract']['id'],'version_no'=>2,'approval_note'=>'Independent Owner review confirmed signed evidence and approved particulars.');
$approved=$service->approve_contract($approvalPayload,'contract.approve.01');$checks++;
$approvalReplay=$service->approve_contract($approvalPayload,'contract.approve.01');hrxp_people_assert($approvalReplay['replayed']===true,'Owner contract approval request safely replays');$checks++;
hrxp_people_assert($approved['record']['status']==='APPROVED'&&$approved['employment']['status']==='PENDING_ONBOARDING'&&!$approved['activation_allowed'],'Owner approval advances pending onboarding only');$checks++;

$tasks=$service->list_onboarding($hire['employment']['id']);hrxp_people_assert(count($tasks)===7,'pending hire created seven mandatory onboarding tasks');$checks++;
$access->actor=1;$access->approve=false;
foreach($tasks as $task){
	if(in_array($task['task_type'],array('PERSONNEL_DOCS','INDUCTION','CODE_OF_CONDUCT','SAFETY_BRIEFING','ROLE_SOP_ACKNOWLEDGEMENT'),true)){
		$taskPayload=array('task_id'=>$task['id'],'version_no'=>1,'evidence_reference'=>'vault://onboarding/'.$task['task_type'].'/ack-001');
		$taskKey='onboarding.complete.'.strtolower(substr($task['task_type'],0,8)).'01';
		$done=$service->complete_onboarding_task($taskPayload,$taskKey);
		hrxp_people_assert($done['record']['status']==='COMPLETE','permitted onboarding task complete with evidence reference');$checks++;
		$taskReplay=$service->complete_onboarding_task($taskPayload,$taskKey);
		hrxp_people_assert($taskReplay['replayed']===true,'completed task request replays despite stale expected version');$checks++;
	}
	if('PAYROLL_SETUP'===$task['task_type']){
		hrxp_people_expect('HRXP_TASK_RESERVED_FOR_LATER_STAGE',function()use($service,$task){$service->complete_onboarding_task(array('task_id'=>$task['id'],'version_no'=>1,'evidence_reference'=>'vault://onboarding/payroll/pending'),'onboarding.payroll.block.01');});$checks++;
	}
}
hrxp_people_assert($repo->data['employments'][$hire['employment']['id']]['status']==='PENDING_ONBOARDING','Stage 4 cannot activate employee before compensation/payroll and access controls');$checks++;
hrxp_people_assert(count($repo->events)>=12,'all key lifecycle writes append idempotent workflow events');$checks++;

$access->view=false;$access->prepare=false;
hrxp_people_expect('HRXP_CANDIDATE_ACCESS_DENIED',function()use($service){$service->list_applications(array('location_id'=>1));});$checks++;
$access->prepare=true;$repo->ready=false;
hrxp_people_expect('HRXP_SCHEMA_NOT_READY',function()use($service){$service->list_applications(array('location_id'=>1));});$checks++;

echo 'PASS Stage 4 People lifecycle synthetic contract: '.$checks." assertions. In-memory only; no WordPress/MariaDB/live data. Private vault is mocked only for this test.\n";
