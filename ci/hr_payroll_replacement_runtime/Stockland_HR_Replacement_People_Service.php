<?php
/**
 * Transactional people/recruitment/contract/onboarding service.
 *
 * Isolated candidate runtime. It is not loaded by the production plugin. It requires
 * the Stage 3 HRX schema and never reads/writes the four legacy HR/payroll tables.
 * It stops at PENDING_PAYROLL_SETUP; later compensation/payroll stages must approve
 * pay setup before any employee becomes ACTIVE or receives application access.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) { exit; }

interface Stockland_HR_Replacement_People_Access {
	public function actor_id();
	public function can_view();
	public function can_prepare();
	public function can_approve();
	public function can_access_location( $location_id );
}

interface Stockland_HR_Replacement_Private_Vault {
	public function verify_reference( $private_reference, $expected_sha256, $actor_id );
}

/** Default adapter is intentionally unavailable: it never pretends a path is private. */
final class Stockland_HR_Replacement_Unavailable_Private_Vault implements Stockland_HR_Replacement_Private_Vault {
	public function verify_reference( $private_reference, $expected_sha256, $actor_id ) {
		return array( 'verified' => false, 'public_access' => true, 'reason' => 'No approved private-vault adapter is configured.' );
	}
}

final class Stockland_HR_Replacement_People_Service {
	private $repo;
	private $access;
	private $vault;
	public function __construct( Stockland_HR_Replacement_People_Repository $repo, Stockland_HR_Replacement_People_Access $access, Stockland_HR_Replacement_Private_Vault $vault = null ) {
		$this->repo = $repo; $this->access = $access;
		$this->vault = $vault instanceof Stockland_HR_Replacement_Private_Vault ? $vault : new Stockland_HR_Replacement_Unavailable_Private_Vault();
	}
	private function require_schema() {
		if ( ! $this->repo->tables_ready() ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_SCHEMA_NOT_READY', 'The isolated People lifecycle schema is unavailable; legacy HR tables are not a fallback.', 503 ); }
	}
	private function actor() {
		$id = (int) $this->access->actor_id();
		if ( $id < 1 ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_ACTOR_REQUIRED', 'An authenticated actor is required.', 401 ); }
		return $id;
	}
	private function require_view() {
		if ( ! $this->access->can_view() ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_FORBIDDEN', 'You do not have permission to view HR lifecycle records.', 403 ); }
		$this->require_schema();
	}
	private function require_candidate_access() {
		// Candidate identity and recruitment notes require the restricted HR authority,
		// not ordinary branch inventory access or UI-only hiding.
		if ( ! $this->access->can_prepare() ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_CANDIDATE_ACCESS_DENIED', 'Restricted candidate/person information is available only to authorized HR preparers.', 403 ); }
		$this->require_schema();
	}
	private function require_prepare() {
		if ( ! $this->access->can_prepare() ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_FORBIDDEN', 'Only authorized HR preparers can change lifecycle records.', 403 ); }
		$this->require_schema();
	}
	private function require_approve() {
		if ( ! $this->access->can_approve() ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_OWNER_APPROVAL_REQUIRED', 'An independently authorized Owner decision is required.', 403 ); }
		$this->require_schema();
	}
	private function location( $id ) {
		$id = Stockland_HR_Replacement_People_Contract::positive_id( $id, 'location_id' );
		if ( ! $this->access->can_access_location( $id ) ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_LOCATION_FORBIDDEN', 'The selected workplace is outside your authorized location scope.', 403 ); }
		return $id;
	}
	private function code( $payload, $key ) {
		return Stockland_HR_Replacement_People_Contract::code( $payload[ $key ] ?? null, $key );
	}
	private function required_id( $payload, $key ) {
		return Stockland_HR_Replacement_People_Contract::positive_id( $payload[ $key ] ?? null, $key );
	}
	private function idempotency( $key, $payload ) {
		$key = is_string( $key ) ? trim( $key ) : '';
		if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,119}$/D', $key ) ) {
			Stockland_HR_Replacement_People_Contract::deny( 'HRXP_IDEMPOTENCY_KEY_REQUIRED', 'A unique Idempotency-Key of 8–120 safe characters is required.' );
		}
		return array( $key, hash( 'sha256', (string) json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
	}
	private function replay( $key, $hash ) {
		$event = $this->repo->find_event_by_key( $key );
		if ( ! $event ) { return null; }
		if ( ! hash_equals( (string) $event['request_hash'], $hash ) ) {
			Stockland_HR_Replacement_People_Contract::deny( 'HRXP_IDEMPOTENCY_CONFLICT', 'This request key was already used for different content.', 409 );
		}
		return $event;
	}
	private function event( $key, $hash, $type, $id, $action, $from, $to, $actor, $maker, $reason, $version, $metadata = array() ) {
		$event = array(
			'idempotency_key' => $key,
			'request_hash' => $hash,
			'entity_type' => $type,
			'entity_id' => (int) $id,
			'event_type' => $action,
			'from_status' => $from,
			'to_status' => $to,
			'actor_id' => (int) $actor,
			'maker_id' => (int) $maker ?: null,
			'reason' => null === $reason ? null : substr( (string) $reason, 0, 190 ),
			'evidence_sha256' => null,
			'record_version' => max( 1, (int) $version ),
			'metadata_json' => (string) json_encode( $metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'occurred_at' => function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' ),
		);
		$this->repo->append_event( $event );
	}
	private function load( $entity, $id, $for_update = false ) {
		$row = $this->repo->find( $entity, (int) $id, $for_update );
		if ( ! is_array( $row ) ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_RECORD_NOT_FOUND', 'The requested HR record does not exist.', 404 ); }
		return $row;
	}
	private function expected_version( $row, $payload ) {
		$version = (int) ( $payload['version_no'] ?? 0 );
		if ( $version < 1 || $version !== (int) $row['version_no'] ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_STALE_VERSION', 'The record has changed. Refresh before retrying.', 409 ); }
		return $version;
	}
	private function require_same_location( $row ) {
		if ( ! $this->access->can_access_location( (int) ( $row['location_id'] ?? 0 ) ) ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_LOCATION_FORBIDDEN', 'The record is outside your authorized workplace scope.', 403 ); }
	}
	public function get_candidate( $candidate_id ) {
		$this->require_candidate_access();
		$candidate = $this->load( 'candidates', Stockland_HR_Replacement_People_Contract::positive_id( $candidate_id, 'candidate_id' ) );
		$person = $this->load( 'people', (int) $candidate['person_id'] );
		unset( $person['personal_data_class'] );
		return array( 'candidate' => $candidate, 'person' => $person );
	}
	public function list_applications( $filters = array(), $limit = 50 ) {
		$this->require_candidate_access();
		$allowed = array();
		foreach ( array( 'stage', 'candidate_id', 'requisition_id' ) as $field ) {
			if ( isset( $filters[ $field ] ) && '' !== $filters[ $field ] ) { $allowed[ $field ] = $filters[ $field ]; }
		}
		if ( isset( $filters['location_id'] ) && '' !== $filters['location_id'] ) { $allowed['location_id'] = $this->location( $filters['location_id'] ); }
		return $this->repo->list_records( 'applications', $allowed, $limit );
	}
	public function create_candidate( $payload, $idempotency_key ) {
		$this->require_candidate_access(); $actor = $this->actor();
		$person_code = $this->code( $payload, 'person_code' );
		$candidate_code = $this->code( $payload, 'candidate_code' );
		$first = Stockland_HR_Replacement_People_Contract::text( $payload['first_name'] ?? null, 'first_name', 1, 100 );
		$last = Stockland_HR_Replacement_People_Contract::text( $payload['last_name'] ?? null, 'last_name', 1, 100 );
		$email = isset( $payload['email'] ) && '' !== trim( (string) $payload['email'] ) ? strtolower( trim( (string) $payload['email'] ) ) : null;
		if ( null !== $email && ( strlen( $email ) > 190 || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_INVALID_EMAIL', 'Email address is invalid.' ); }
		$phone = isset( $payload['phone'] ) && '' !== trim( (string) $payload['phone'] ) ? Stockland_HR_Replacement_People_Contract::text( trim( (string) $payload['phone'] ), 'phone', 5, 40 ) : null;
		if ( null === $email && null === $phone ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_CONTACT_REQUIRED', 'At least one contact channel is required for candidate follow-up.' ); }
		if ( empty( $payload['privacy_acknowledged'] ) ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_PRIVACY_ACK_REQUIRED', 'The candidate privacy notice must be acknowledged before creating an applicant profile.' ); }
		$notice = Stockland_HR_Replacement_People_Contract::text( $payload['privacy_notice_version'] ?? null, 'privacy_notice_version', 1, 48 );
		$purpose = Stockland_HR_Replacement_People_Contract::text( $payload['collection_purpose'] ?? 'Recruitment and candidate communication', 'collection_purpose', 10, 190 );
		$source = strtoupper( Stockland_HR_Replacement_People_Contract::text( $payload['source_channel'] ?? 'OTHER', 'source_channel', 2, 40 ) );
		if ( ! in_array( $source, array( 'REFERRAL','COMPANY_WEBSITE','WALK_IN','JOB_BOARD','SOCIAL_PROFESSIONAL','AGENCY','OTHER' ), true ) ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_SOURCE_CHANNEL_INVALID', 'Select a supported candidate source channel.' ); }
		$retention = Stockland_HR_Replacement_People_Contract::date( $payload['retention_review_on'] ?? null, 'retention_review_on' );
		$normalized = array( 'person_code'=>$person_code,'candidate_code'=>$candidate_code,'first_name'=>$first,'last_name'=>$last,'email'=>$email,'phone'=>$phone,'privacy_notice_version'=>$notice,'collection_purpose'=>$purpose,'source_channel'=>$source,'retention_review_on'=>$retention );
		list( $key, $hash ) = $this->idempotency( $idempotency_key, $normalized );
		return $this->repo->transaction( function () use ( $key, $hash, $normalized, $actor ) {
			$prior = $this->replay( $key, $hash );
			if ( $prior ) { $candidate = $this->load( 'candidates', (int) $prior['entity_id'] ); return array( 'candidate' => $candidate, 'person' => $this->load( 'people', (int) $candidate['person_id'] ), 'replayed' => true ); }
			if ( null !== $normalized['email'] && $this->repo->person_email_exists( $normalized['email'] ) ) { Stockland_HR_Replacement_People_Contract::deny( 'HRXP_POSSIBLE_DUPLICATE_PERSON', 'A candidate with this email may already exist. Review the existing person record before creating another profile.', 409 ); }
			$person = $this->repo->insert( 'people', array( 'person_code'=>$normalized['person_code'],'first_name'=>$normalized['first_name'],'last_name'=>$normalized['last_name'],'email'=>$normalized['email'],'phone'=>$normalized['phone'],'personal_data_class'=>'RESTRICTED','status'=>'ACTIVE','version_no'=>1,'created_by'=>$actor,'updated_by'=>$actor,'created_at'=>$this->now(),'updated_at'=>$this->now() ) );
			$candidate = $this->repo->insert( 'candidates', array( 'person_id'=>(int) $person['id'],'candidate_code'=>$normalized['candidate_code'],'privacy_notice_version'=>$normalized['privacy_notice_version'],'privacy_ack_at'=>$this->now(),'collection_purpose'=>$normalized['collection_purpose'],'source_channel'=>$normalized['source_channel'],'source_detail'=>null,'retention_review_on'=>$normalized['retention_review_on'],'status'=>'ACTIVE','version_no'=>1,'created_by'=>$actor,'updated_by'=>$actor,'created_at'=>$this->now(),'updated_at'=>$this->now() ) );
			$this->event( $key,$hash,'CANDIDATE',(int)$candidate['id'],'CANDIDATE_CREATED',null,'ACTIVE',$actor,$actor,'Candidate profile created with privacy acknowledgement',1,array('source_channel'=>$normalized['source_channel'],'privacy_notice_version'=>$normalized['privacy_notice_version']) );
			return array( 'candidate'=>$candidate,'person'=>$person,'replayed'=>false );
		} );
	}
	private function now() { return function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' ); }
	private function today() { return function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' ); }
	public function create_application( $payload, $idempotency_key ) {
		$this->require_candidate_access(); $actor = $this->actor();
		$app_code = $this->code( $payload, 'application_code' ); $candidate_id = $this->required_id( $payload, 'candidate_id' ); $req_id = $this->required_id( $payload, 'requisition_id' );
		$location = $this->location( $payload['location_id'] ?? null );
		list( $key, $hash ) = $this->idempotency( $idempotency_key, array('application_code'=>$app_code,'candidate_id'=>$candidate_id,'requisition_id'=>$req_id,'location_id'=>$location) );
		return $this->repo->transaction( function () use ( $key,$hash,$actor,$app_code,$candidate_id,$req_id,$location ) {
			$prior=$this->replay($key,$hash); if($prior){return array('record'=>$this->load('applications',(int)$prior['entity_id']),'replayed'=>true);}
			$candidate=$this->load('candidates',$candidate_id,true);
			if('ACTIVE'!==strtoupper((string)$candidate['status'])){Stockland_HR_Replacement_People_Contract::deny('HRXP_CANDIDATE_INACTIVE','Candidate is withdrawn or outside the active retention period.');}
			if(!empty($candidate['retention_review_on'])&&substr((string)$candidate['retention_review_on'],0,10)<$this->today()){Stockland_HR_Replacement_People_Contract::deny('HRXP_RETENTION_REVIEW_DUE','Candidate retention review is due. The profile must be reviewed before further processing.');}
			$req=$this->repo->requisition($req_id,true);
			if(!$req||'OPEN'!==strtoupper((string)$req['status'])){Stockland_HR_Replacement_People_Contract::deny('HRXP_VACANCY_NOT_OPEN','Applications require an approved and open vacancy.');}
			if((int)($req['location_id']??0)!==$location){Stockland_HR_Replacement_People_Contract::deny('HRXP_LOCATION_MISMATCH','Application location must match the approved vacancy location.');}
			if(!$this->repo->requisition_slot_available($req_id)){Stockland_HR_Replacement_People_Contract::deny('HRXP_VACANCY_FULL','The vacancy has no unreserved approved openings.',409);}
			$record=$this->repo->insert('applications',array('application_code'=>$app_code,'candidate_id'=>$candidate_id,'requisition_id'=>$req_id,'location_id'=>$location,'stage'=>'APPLIED','decision_rationale'=>null,'decision_by'=>null,'decision_at'=>null,'human_decision'=>0,'version_no'=>1,'created_by'=>$actor,'updated_by'=>$actor,'created_at'=>$this->now(),'updated_at'=>$this->now()));
			$this->event($key,$hash,'APPLICATION',(int)$record['id'],'APPLICATION_CREATED',null,'APPLIED',$actor,$actor,'Application created against an approved open vacancy',1,array('requisition_id'=>$req_id,'location_id'=>$location));
			return array('record'=>$record,'replayed'=>false);
		});
	}
	public function record_interview( $payload, $idempotency_key ) {
		$this->require_candidate_access(); $actor=$this->actor();
		$code=$this->code($payload,'interview_code');$app_id=$this->required_id($payload,'application_id');$round=(int)($payload['round_no']??0);
		if($round<1||$round>20){Stockland_HR_Replacement_People_Contract::deny('HRXP_INTERVIEW_ROUND_INVALID','Interview round must be 1–20.');}
		$rubric=Stockland_HR_Replacement_People_Contract::text($payload['rubric_version']??null,'rubric_version',1,48);
		$score=null;if(isset($payload['score'])&&''!==(string)$payload['score']){if(!preg_match('/^(?:0|[1-9][0-9]?|100)(?:\.[0-9]{1,2})?$/D',(string)$payload['score'])||(float)$payload['score']>100){Stockland_HR_Replacement_People_Contract::deny('HRXP_INTERVIEW_SCORE_INVALID','Interview score must be between 0 and 100.');}$score=(string)$payload['score'];}
		$recommendation=strtoupper((string)($payload['recommendation']??'PENDING'));
		if(!in_array($recommendation,array('PENDING','RECOMMEND','DO_NOT_RECOMMEND','DISCUSS'),true)){Stockland_HR_Replacement_People_Contract::deny('HRXP_INTERVIEW_RECOMMENDATION_INVALID','Use a human reviewer recommendation status.');}
		$notes=isset($payload['notes_reference'])&&''!==(string)$payload['notes_reference']?Stockland_HR_Replacement_People_Contract::private_reference($payload['notes_reference']):null;
		$scheduled=isset($payload['scheduled_at'])&&''!==(string)$payload['scheduled_at']?Stockland_HR_Replacement_People_Contract::datetime($payload['scheduled_at'],'scheduled_at',true):null;
		$evidence=isset($payload['evidence_sha256'])&&''!==(string)$payload['evidence_sha256']?Stockland_HR_Replacement_People_Contract::hash($payload['evidence_sha256'],'evidence_sha256'):null;
		list($key,$hash)=$this->idempotency($idempotency_key,array('interview_code'=>$code,'application_id'=>$app_id,'round_no'=>$round,'rubric_version'=>$rubric,'score'=>$score,'recommendation'=>$recommendation,'notes_reference'=>$notes,'evidence_sha256'=>$evidence,'scheduled_at'=>$scheduled));
		return $this->repo->transaction(function()use($key,$hash,$actor,$code,$app_id,$round,$rubric,$score,$recommendation,$notes,$evidence,$scheduled){
			$prior=$this->replay($key,$hash);if($prior){return array('record'=>$this->load('interviews',(int)$prior['entity_id']),'replayed'=>true);}
			$app=$this->load('applications',$app_id,true);$this->require_same_location($app);
			if(!in_array(strtoupper((string)$app['stage']),array('SCREENING','INTERVIEW','REFERENCE_REVIEW'),true)){Stockland_HR_Replacement_People_Contract::deny('HRXP_INTERVIEW_STAGE_INVALID','Interviews are allowed only during screening/interview/reference-review stages.');}
			$r=$this->repo->requisition((int)$app['requisition_id']);if(!$r||'OPEN'!==strtoupper((string)$r['status'])){Stockland_HR_Replacement_People_Contract::deny('HRXP_VACANCY_NOT_OPEN','The linked vacancy is no longer open.');}
			$record=$this->repo->insert('interviews',array('interview_code'=>$code,'application_id'=>$app_id,'round_no'=>$round,'scheduled_at'=>$scheduled,'interviewed_at'=>$this->now(),'interviewer_id'=>$actor,'rubric_version'=>$rubric,'score'=>$score,'recommendation'=>$recommendation,'notes_reference'=>$notes,'evidence_sha256'=>$evidence,'version_no'=>1,'created_by'=>$actor,'created_at'=>$this->now(),'updated_at'=>$this->now()));
			$this->event($key,$hash,'INTERVIEW',(int)$record['id'],'INTERVIEW_RECORDED',null,$recommendation,$actor,$actor,'Human interviewer evidence captured; not an automated selection decision',1,array('application_id'=>$app_id,'round_no'=>$round,'rubric_version'=>$rubric,'score_present'=>null!==$score));
			return array('record'=>$record,'replayed'=>false);
		});
	}
	public function move_application( $payload, $idempotency_key ) {
		$this->require_candidate_access();$actor=$this->actor();$id=$this->required_id($payload,'application_id');$target=strtoupper(Stockland_HR_Replacement_People_Contract::text($payload['to_stage']??null,'to_stage',2,30));
		if('HIRED'===$target){Stockland_HR_Replacement_People_Contract::deny('HRXP_INTERNAL_TRANSITION_ONLY','HIRED is system-managed when a pending employment record is created.');}
		$maker=(int)($payload['created_by']??0);unset($payload['created_by']);
		$rationale=isset($payload['rationale'])?(string)$payload['rationale']:'';$human=!empty($payload['human_decision']);
		$row=$this->load('applications',$id);$this->require_same_location($row);
		$context=array('actor_id'=>$actor,'created_by'=>(int)$row['created_by'],'human_decision'=>$human,'rationale'=>$rationale);
		$transition=Stockland_HR_Replacement_People_Contract::application_stage($row['stage'],$target,$context);
		$final=in_array($target,array('SELECTED','NOT_SELECTED','ACCEPTED','DECLINED'),true);
		if($final){$this->require_approve();}else{$this->require_prepare();}
		$this->expected_version($row,$payload);list($key,$hash)=$this->idempotency($idempotency_key,array('application_id'=>$id,'from'=>$transition['from'],'to'=>$transition['to'],'version_no'=>(int)$payload['version_no'],'rationale'=>$rationale,'human_decision'=>$human));
		return $this->repo->transaction(function()use($key,$hash,$id,$actor,$target,$rationale,$human,$final,$payload){
			$prior=$this->replay($key,$hash);if($prior){return array('record'=>$this->load('applications',(int)$prior['entity_id']),'replayed'=>true);}
			$row=$this->load('applications',$id,true);$this->require_same_location($row);$v=$this->expected_version($row,$payload);
			if('SELECTED'===$target&&!$this->repo->requisition_slot_available((int)$row['requisition_id'])){Stockland_HR_Replacement_People_Contract::deny('HRXP_VACANCY_FULL','The vacancy no longer has an unreserved opening.',409);}
			$from=$row['stage'];$updated=$this->repo->update_expected('applications',$id,$v,$from,array('stage'=>$target,'decision_rationale'=>$final?$rationale:$row['decision_rationale'],'decision_by'=>$final?$actor:$row['decision_by'],'decision_at'=>$final?$this->now():$row['decision_at'],'human_decision'=>$final?1:(int)$row['human_decision'],'version_no'=>$v+1,'updated_by'=>$actor,'updated_at'=>$this->now()));
			$this->event($key,$hash,'APPLICATION',$id,'APPLICATION_STAGE_CHANGED',$from,$target,$actor,(int)$row['created_by'],$final?$rationale:'Stage transition', $v+1,array('human_decision'=>$final,'rationale_present'=>''!==trim($rationale)));
			return array('record'=>$updated,'replayed'=>false);
		});
	}
	public function create_pending_hire( $payload, $idempotency_key ) {
		$this->require_candidate_access();$actor=$this->actor();$app_id=$this->required_id($payload,'application_id');$employment_code=$this->code($payload,'employment_code');$contract_code=$this->code($payload,'contract_code');
		$legal_employer=Stockland_HR_Replacement_People_Contract::code($payload['legal_employer_code']??null,'legal_employer_code');
		$start=Stockland_HR_Replacement_People_Contract::date($payload['start_date']??null,'start_date');
		$end=Stockland_HR_Replacement_People_Contract::date($payload['end_date']??null,'end_date',true);
		if($end&&$end<$start){Stockland_HR_Replacement_People_Contract::deny('HRXP_CONTRACT_DATES_INVALID','Employment end date cannot precede the start date.');}
		$type=strtoupper(Stockland_HR_Replacement_People_Contract::text($payload['employment_type']??'FULL_TIME','employment_type',2,24));
		if(!in_array($type,array('FULL_TIME','PART_TIME','FIXED_TERM','TEMPORARY','APPRENTICE'),true)){Stockland_HR_Replacement_People_Contract::deny('HRXP_EMPLOYMENT_TYPE_INVALID','Select an approved employment type.');}
		$contract_type=strtoupper(Stockland_HR_Replacement_People_Contract::text($payload['contract_type']??$type,'contract_type',2,32));
		$probation=Stockland_HR_Replacement_People_Contract::date($payload['probation_end']??null,'probation_end',true);
		if($probation&&($probation<$start||($end&&$probation>$end))){Stockland_HR_Replacement_People_Contract::deny('HRXP_PROBATION_DATES_INVALID','Probation end must be within the employment date interval.');}
		$loc=$this->location($payload['location_id']??null);
		$managerId=isset($payload['reporting_manager_id'])&&''!==(string)$payload['reporting_manager_id']?Stockland_HR_Replacement_People_Contract::positive_id($payload['reporting_manager_id'],'reporting_manager_id'):null;
		list($key,$hash)=$this->idempotency($idempotency_key,array('application_id'=>$app_id,'employment_code'=>$employment_code,'contract_code'=>$contract_code,'legal_employer_code'=>$legal_employer,'start_date'=>$start,'end_date'=>$end,'employment_type'=>$type,'contract_type'=>$contract_type,'probation_end'=>$probation,'location_id'=>$loc,'reporting_manager_id'=>$managerId));
		return $this->repo->transaction(function()use($key,$hash,$actor,$app_id,$employment_code,$contract_code,$legal_employer,$start,$end,$type,$contract_type,$probation,$loc,$managerId){
			$prior=$this->replay($key,$hash);if($prior){return array('employment'=>$this->load('employments',(int)$prior['entity_id']),'replayed'=>true);}
			$app=$this->load('applications',$app_id,true);$this->require_same_location($app);
			if('ACCEPTED'!==strtoupper((string)$app['stage'])){Stockland_HR_Replacement_People_Contract::deny('HRXP_OFFER_ACCEPTANCE_REQUIRED','A pending hire can be created only after a human-approved offer acceptance.');}
			if((int)$app['location_id']!==$loc){Stockland_HR_Replacement_People_Contract::deny('HRXP_LOCATION_MISMATCH','Employment location must match the approved vacancy location.');}
			$req=$this->repo->requisition((int)$app['requisition_id'],true);if(!$req||'OPEN'!==strtoupper((string)$req['status'])){Stockland_HR_Replacement_People_Contract::deny('HRXP_VACANCY_NOT_OPEN','The source vacancy is not open at pending-hire creation.');}
			if((int)$req['filled_openings'] >= (int)$req['openings']){Stockland_HR_Replacement_People_Contract::deny('HRXP_VACANCY_FULL','No unfilled approved opening remains for this accepted offer.',409);}
			$employment=$this->repo->insert('employments',array('employment_code'=>$employment_code,'person_id'=>(int)$this->load('candidates',(int)$app['candidate_id'])['person_id'],'application_id'=>$app_id,'position_id'=>(int)$req['position_id'],'legal_employer_code'=>$legal_employer,'location_id'=>$loc,'reporting_manager_id'=>$managerId,'start_date'=>$start,'end_date'=>$end,'employment_type'=>$type,'status'=>'PENDING_CONTRACT','version_no'=>1,'created_by'=>$actor,'updated_by'=>$actor,'created_at'=>$this->now(),'updated_at'=>$this->now()));
			$contract=$this->repo->insert('contracts',array('contract_code'=>$contract_code,'employment_id'=>(int)$employment['id'],'version_no'=>1,'contract_version_no'=>1,'contract_type'=>$contract_type,'effective_from'=>$start,'effective_to'=>$end,'probation_end'=>$probation,'private_document_id'=>null,'signed_at'=>null,'status'=>'DRAFT','submitted_by'=>null,'submitted_at'=>null,'approved_by'=>null,'approved_at'=>null,'approval_note'=>null,'created_by'=>$actor,'updated_by'=>$actor,'created_at'=>$this->now(),'updated_at'=>$this->now()));
			foreach(array('PERSONNEL_DOCS','INDUCTION','CODE_OF_CONDUCT','SAFETY_BRIEFING','ROLE_SOP_ACKNOWLEDGEMENT','PAYROLL_SETUP','ACCESS_PROVISIONING') as $taskType){
				$this->repo->insert('onboarding',array('task_code'=>substr($employment_code,0,14).'-'.substr(hash('sha256',$employment_code),0,8).'-'.$taskType,'employment_id'=>(int)$employment['id'],'task_type'=>$taskType,'title'=>str_replace('_',' ',ucfirst(strtolower($taskType))),'assigned_role'=>in_array($taskType,array('PAYROLL_SETUP','ACCESS_PROVISIONING'),true)?'OWNER':'MANAGEMENT','owner_user_id'=>null,'due_on'=>null,'mandatory'=>1,'status'=>'PENDING','evidence_reference'=>null,'completed_by'=>null,'completed_at'=>null,'version_no'=>1,'created_by'=>$actor,'updated_by'=>$actor,'created_at'=>$this->now(),'updated_at'=>$this->now()));
			}
			$appVersion=(int)$app['version_no'];
			$this->repo->update_expected('applications',$app_id,$appVersion,'ACCEPTED',array('stage'=>'HIRED','human_decision'=>1,'version_no'=>$appVersion+1,'updated_by'=>$actor,'updated_at'=>$this->now()));
			$reqVersion=(int)$req['version_no'];
			$this->repo->update_expected('requisitions',(int)$req['id'],$reqVersion,'OPEN',array('filled_openings'=>(int)$req['filled_openings']+1,'version_no'=>$reqVersion+1,'updated_by'=>$actor,'updated_at'=>$this->now()));
			$this->event($key,$hash,'EMPLOYMENT',(int)$employment['id'],'PENDING_HIRE_CREATED','OFFER_ACCEPTED','PENDING_CONTRACT',$actor,$actor,'Pending employment created; payroll setup and access remain gated',1,array('application_id'=>$app_id,'position_id'=>(int)$req['position_id'],'contract_id'=>(int)$contract['id'],'onboarding_tasks'=>7,'requisition_openings_filled'=>(int)$req['filled_openings']+1));
			return array('employment'=>$employment,'contract'=>$contract,'onboarding_tasks'=>7,'activation_allowed'=>false,'replayed'=>false);
		});
	}
	public function register_private_document( $payload, $idempotency_key ) {
		$this->require_candidate_access();$actor=$this->actor();$code=$this->code($payload,'document_code');
		$type=strtoupper(Stockland_HR_Replacement_People_Contract::text($payload['subject_type']??null,'subject_type',3,32));
		if(!in_array($type,array('CANDIDATE','EMPLOYMENT','CONTRACT'),true)){Stockland_HR_Replacement_People_Contract::deny('HRXP_DOCUMENT_SUBJECT_INVALID','Document subject must be candidate, employment or contract.');}
		$subject_id=$this->required_id($payload,'subject_id');$doc_type=strtoupper(Stockland_HR_Replacement_People_Contract::text($payload['document_type']??null,'document_type',3,40));
		$ref=Stockland_HR_Replacement_People_Contract::private_reference($payload['private_reference']??null);$sha=Stockland_HR_Replacement_People_Contract::hash($payload['content_sha256']??null,'content_sha256');
		$issued=Stockland_HR_Replacement_People_Contract::date($payload['issued_on']??null,'issued_on',true);$expires=Stockland_HR_Replacement_People_Contract::date($payload['expires_on']??null,'expires_on',true);
		if($issued&&$expires&&$expires<$issued){Stockland_HR_Replacement_People_Contract::deny('HRXP_DOCUMENT_DATES_INVALID','Document expiry cannot precede issuance.');}
		list($key,$hash)=$this->idempotency($idempotency_key,array('document_code'=>$code,'subject_type'=>$type,'subject_id'=>$subject_id,'document_type'=>$doc_type,'private_reference'=>$ref,'content_sha256'=>$sha,'issued_on'=>$issued,'expires_on'=>$expires));
		return $this->repo->transaction(function()use($key,$hash,$actor,$code,$type,$subject_id,$doc_type,$ref,$sha,$issued,$expires){
			$prior=$this->replay($key,$hash);if($prior){return array('record'=>$this->load('documents',(int)$prior['entity_id']),'replayed'=>true);}
			$allowedTypes=array('IDENTITY','EMPLOYMENT_CONTRACT','QUALIFICATION','WORK_ELIGIBILITY','TAX_PROFILE','PENSION_PROFILE','CERTIFICATE','OTHER');
			if(!in_array($doc_type,$allowedTypes,true)){Stockland_HR_Replacement_People_Contract::deny('HRXP_DOCUMENT_TYPE_INVALID','Select a supported personnel document type.');}
			if('CONTRACT'===$type){$this->load('contracts',$subject_id,true);}elseif('EMPLOYMENT'===$type){$this->load('employments',$subject_id,true);}else{$this->load('candidates',$subject_id,true);}
			$record=$this->repo->insert('documents',array('document_code'=>$code,'subject_type'=>$type,'subject_id'=>$subject_id,'document_type'=>$doc_type,'private_reference'=>$ref,'content_sha256'=>$sha,'issued_on'=>$issued,'expires_on'=>$expires,'status'=>'PENDING_VERIFICATION','version_no'=>1,'verified_by'=>null,'verified_at'=>null,'review_note'=>null,'created_by'=>$actor,'created_at'=>$this->now(),'updated_at'=>$this->now()));
			$this->event($key,$hash,'PRIVATE_DOCUMENT',(int)$record['id'],'PRIVATE_DOCUMENT_REGISTERED',null,'PENDING_VERIFICATION',$actor,$actor,'Private document metadata registered; underlying vault access must be independently verified',1,array('subject_type'=>$type,'document_type'=>$doc_type,'content_sha256'=>$sha));
			return array('record'=>$record,'replayed'=>false);
		});
	}
	public function verify_private_document( $payload, $idempotency_key ) {
		$this->require_approve();$actor=$this->actor();$id=$this->required_id($payload,'document_id');$note=Stockland_HR_Replacement_People_Contract::text($payload['review_note']??null,'review_note',20,500);
		$row=$this->load('documents',$id);list($key,$hash)=$this->idempotency($idempotency_key,array('document_id'=>$id,'review_note'=>$note,'version_no'=>(int)($payload['version_no']??0)));
		return $this->repo->transaction(function()use($key,$hash,$actor,$id,$note,$payload){
			$prior=$this->replay($key,$hash);if($prior){return array('record'=>$this->load('documents',(int)$prior['entity_id']),'replayed'=>true);}
			$row=$this->load('documents',$id,true);$v=$this->expected_version($row,$payload);if('PENDING_VERIFICATION'!==strtoupper((string)$row['status'])){Stockland_HR_Replacement_People_Contract::deny('HRXP_DOCUMENT_STATE_INVALID','Only a pending private document can be verified.');}
			Stockland_HR_Replacement_People_Contract::assert_independent($actor,(int)$row['created_by'],'verify');
			$verification = $this->vault->verify_reference( (string) $row['private_reference'], (string) $row['content_sha256'], $actor );
			if ( ! is_array( $verification ) || empty( $verification['verified'] ) || ! empty( $verification['public_access'] ) || ! isset( $verification['content_sha256'] ) || ! hash_equals( (string) $row['content_sha256'], strtolower( (string) $verification['content_sha256'] ) ) ) {
				Stockland_HR_Replacement_People_Contract::deny( 'HRXP_PRIVATE_VAULT_NOT_VERIFIED', 'The host has not proven that the file exists in authorized private storage with the expected hash and no public access. No verification status was changed.' );
			}
			$updated=$this->repo->update_expected('documents',$id,$v,$row['status'],array('status'=>'VERIFIED','verified_by'=>$actor,'verified_at'=>$this->now(),'review_note'=>$note,'version_no'=>$v+1,'updated_at'=>$this->now()));
			$this->event($key,$hash,'PRIVATE_DOCUMENT',$id,'PRIVATE_DOCUMENT_VERIFIED',$row['status'],'VERIFIED',$actor,(int)$row['created_by'],$note,$v+1,array('content_sha256'=>$row['content_sha256']));
			return array('record'=>$updated,'replayed'=>false);
		});
	}
	public function sign_contract( $payload, $idempotency_key ) {
		$this->require_candidate_access();$actor=$this->actor();$id=$this->required_id($payload,'contract_id');$doc=$this->required_id($payload,'document_id');$signed=Stockland_HR_Replacement_People_Contract::date($payload['signed_on']??null,'signed_on');
		$row=$this->load('contracts',$id);$v=$this->expected_version($row,$payload);list($key,$hash)=$this->idempotency($idempotency_key,array('contract_id'=>$id,'document_id'=>$doc,'signed_on'=>$signed,'version_no'=>$v));
		return $this->repo->transaction(function()use($key,$hash,$actor,$id,$doc,$signed,$payload){
			$prior=$this->replay($key,$hash);if($prior){return array('record'=>$this->load('contracts',(int)$prior['entity_id']),'replayed'=>true);}
			$row=$this->load('contracts',$id,true);$v=$this->expected_version($row,$payload);if('DRAFT'!==strtoupper((string)$row['status'])){Stockland_HR_Replacement_People_Contract::deny('HRXP_CONTRACT_STATE_INVALID','Only a draft contract can be signed.');}
			$document=$this->load('documents',$doc,true);
			if('VERIFIED'!==strtoupper((string)$document['status'])||'CONTRACT'!==strtoupper((string)$document['subject_type'])||(int)$document['subject_id']!==$id||'EMPLOYMENT_CONTRACT'!==strtoupper((string)$document['document_type'])){Stockland_HR_Replacement_People_Contract::deny('HRXP_SIGNED_CONTRACT_DOCUMENT_REQUIRED','A verified private-vault employment-contract document must be linked to this contract.');}
			if(!empty($document['expires_on'])&&$signed>$document['expires_on']){Stockland_HR_Replacement_People_Contract::deny('HRXP_CONTRACT_DOCUMENT_EXPIRED','The verified contract evidence is expired for the signature date.');}
			$updated=$this->repo->update_expected('contracts',$id,$v,$row['status'],array('private_document_id'=>$doc,'signed_at'=>$signed.' 00:00:00','status'=>'SIGNED','updated_by'=>$actor,'updated_at'=>$this->now(),'version_no'=>$v+1));
			$this->event($key,$hash,'CONTRACT',$id,'CONTRACT_SIGNED','DRAFT','SIGNED',$actor,(int)$row['created_by'],'Signed contract evidence linked', $v+1,array('document_id'=>$doc,'document_sha256'=>$document['content_sha256']));
			return array('record'=>$updated,'replayed'=>false);
		});
	}
	public function approve_contract( $payload, $idempotency_key ) {
		$this->require_approve();$actor=$this->actor();$id=$this->required_id($payload,'contract_id');$note=Stockland_HR_Replacement_People_Contract::text($payload['approval_note']??null,'approval_note',20,500);
		$row=$this->load('contracts',$id);$v=$this->expected_version($row,$payload);list($key,$hash)=$this->idempotency($idempotency_key,array('contract_id'=>$id,'approval_note'=>$note,'version_no'=>$v));
		return $this->repo->transaction(function()use($key,$hash,$actor,$id,$note,$payload){
			$prior=$this->replay($key,$hash);if($prior){return array('record'=>$this->load('contracts',(int)$prior['entity_id']),'replayed'=>true);}
			$row=$this->load('contracts',$id,true);$v=$this->expected_version($row,$payload);
			if('SIGNED'!==strtoupper((string)$row['status'])||(int)$row['created_by']===$actor){Stockland_HR_Replacement_People_Contract::deny('HRXP_CONTRACT_APPROVAL_BLOCKED','Owner must independently approve a signed contract prepared by another actor.',403);}
			$doc=$this->load('documents',(int)$row['private_document_id'],true);if('VERIFIED'!==strtoupper((string)$doc['status'])||'CONTRACT'!==strtoupper((string)$doc['subject_type'])||(int)$doc['subject_id']!==$id||'EMPLOYMENT_CONTRACT'!==strtoupper((string)$doc['document_type'])){Stockland_HR_Replacement_People_Contract::deny('HRXP_CONTRACT_DOCUMENT_UNVERIFIED','Contract evidence must be verified before approval.');}
			$updated=$this->repo->update_expected('contracts',$id,$v,$row['status'],array('status'=>'APPROVED','approved_by'=>$actor,'approved_at'=>$this->now(),'approval_note'=>$note,'updated_by'=>$actor,'updated_at'=>$this->now(),'version_no'=>$v+1));
			$employment=$this->load('employments',(int)$row['employment_id'],true);$ev=(int)$employment['version_no'];if('PENDING_CONTRACT'!==strtoupper((string)$employment['status'])){Stockland_HR_Replacement_People_Contract::deny('HRXP_EMPLOYMENT_STATE_INVALID','Employment record is no longer pending contract approval.');}
			$employment=$this->repo->update_expected('employments',(int)$employment['id'],$ev,$employment['status'],array('status'=>'PENDING_ONBOARDING','updated_by'=>$actor,'updated_at'=>$this->now(),'version_no'=>$ev+1));
			$this->event($key,$hash,'CONTRACT',$id,'CONTRACT_APPROVED','SIGNED','APPROVED',$actor,(int)$row['created_by'],$note,$v+1,array('employment_id'=>(int)$employment['id'],'approved_by_owner'=>true));
			return array('record'=>$updated,'employment'=>$employment,'activation_allowed'=>false,'replayed'=>false);
		});
	}
	public function complete_onboarding_task( $payload, $idempotency_key ) {
		$this->require_prepare();$actor=$this->actor();$id=$this->required_id($payload,'task_id');$evidence=Stockland_HR_Replacement_People_Contract::private_reference($payload['evidence_reference']??null);
		$row=$this->load('onboarding',$id);$this->require_same_location($this->load('employments',(int)$row['employment_id']));
		$v=$this->expected_version($row,$payload);list($key,$hash)=$this->idempotency($idempotency_key,array('task_id'=>$id,'evidence_reference'=>$evidence,'version_no'=>$v));
		return $this->repo->transaction(function()use($key,$hash,$actor,$id,$evidence,$payload){
			$prior=$this->replay($key,$hash);if($prior){return array('record'=>$this->load('onboarding',(int)$prior['entity_id']),'replayed'=>true);}
			$row=$this->load('onboarding',$id,true);$v=$this->expected_version($row,$payload);$employment=$this->load('employments',(int)$row['employment_id'],true);
			if('PENDING_ONBOARDING'!==strtoupper((string)$employment['status'])){Stockland_HR_Replacement_People_Contract::deny('HRXP_EMPLOYMENT_STATE_INVALID','Onboarding cannot be completed before an independently approved contract.');}
			$approvedContracts=$this->repo->list_records('contracts',array('employment_id'=>(int)$employment['id'],'status'=>'APPROVED'),10);
			if(!$approvedContracts){Stockland_HR_Replacement_People_Contract::deny('HRXP_CONTRACT_APPROVAL_REQUIRED','The linked employment contract must be approved before onboarding tasks can be completed.');}
			if(!in_array(strtoupper((string)$row['task_type']),array('PERSONNEL_DOCS','INDUCTION','CODE_OF_CONDUCT','SAFETY_BRIEFING','ROLE_SOP_ACKNOWLEDGEMENT'),true)){Stockland_HR_Replacement_People_Contract::deny('HRXP_TASK_RESERVED_FOR_LATER_STAGE','Payroll setup and access provisioning remain blocked until the compensation/access stages pass.');}
			if('COMPLETE'!==strtoupper((string)$row['status'])){$updated=$this->repo->update_expected('onboarding',$id,$v,$row['status'],array('status'=>'COMPLETE','evidence_reference'=>$evidence,'completed_by'=>$actor,'completed_at'=>$this->now(),'updated_by'=>$actor,'updated_at'=>$this->now(),'version_no'=>$v+1));}
			else{Stockland_HR_Replacement_People_Contract::deny('HRXP_TASK_ALREADY_COMPLETE','The onboarding task is already complete.');}
			$this->event($key,$hash,'ONBOARDING_TASK',$id,'ONBOARDING_TASK_COMPLETED',$row['status'],'COMPLETE',$actor,(int)$row['created_by'],'Evidence reference captured',$v+1,array('employment_id'=>(int)$row['employment_id'],'task_type'=>$row['task_type']));
			return array('record'=>$updated,'replayed'=>false);
		});
	}
	public function list_onboarding( $employment_id ) {
		$this->require_candidate_access();$employment=$this->load('employments',(int)$employment_id);$this->require_same_location($employment);
		return $this->repo->list_records('onboarding',array('employment_id'=>(int)$employment_id),100);
	}
}
