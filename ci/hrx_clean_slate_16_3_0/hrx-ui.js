/*
 * Stockland HRX clean-slate workspace — 16.3.0
 * Uses the versioned /hr/replacement REST contract. No business logic is duplicated in the browser.
 */
const HRX_UI_TABS = {
  workforce: { label:'Workforce', icon:'▦', entities:['org-units','positions','workforce-plans','requisitions','assignments'] },
  recruitment: { label:'Recruitment', icon:'♙', entities:['people','applications','interviews','offers','employments','contracts','onboarding-tasks'] },
  timeleave: { label:'Time & Leave', icon:'◷', entities:['schedules','time-events','leave-policies','leave-transactions'] },
  compensation: { label:'Compensation & Rules', icon:'≋', entities:['compensation-assignments','compensation-items','legal-rules','legal-rule-bands','accounting-config'] },
  payroll: { label:'Payroll & Settlement', icon:'▤', entities:['payroll-runs','payroll-lines','payout-batches','payout-items','statutory-obligations','remittances'] },
  payees: { label:'Payees & Campaigns', icon:'◇', entities:['payee-engagements','payee-requests','payee-deliverables','payee-classifications','social-snapshots'] },
  exits: { label:'Exit & Final Settlement', icon:'⇢', entities:['exit-settlements','exit-items','exit-clearance-tasks','exit-payouts'] },
  documents: { label:'Documents & SOPs', icon:'▧', entities:['documents'] }
};
const HRX_ENTITY_META = {
 'org-units':{label:'Organization units',fields:[['unit_code','Unit code','text',1],['unit_name','Unit name','text',1],['unit_type','Unit type','text',1],['parent_id','Parent unit ID','number',0],['location_id','Work location ID','number',1],['effective_from','Effective from','date',1]],cols:['unit_code','unit_name','unit_type','location_id','active']},
 'positions':{label:'Positions & grades',fields:[['position_code','Position code','text',1],['title','Position title','text',1],['org_unit_id','Organization unit ID','number',1],['grade_code','Grade / band','text',0],['band_min','Band minimum (ETB)','number',0],['band_max','Band maximum (ETB)','number',0],['openings_planned','Planned openings','number',0],['effective_from','Effective from','date',1]],cols:['position_code','title','org_unit_id','grade_code','status']},
 'workforce-plans':{label:'Workforce plans',fields:[['plan_code','Plan code','text',1],['fiscal_year','Fiscal year','text',1],['org_unit_id','Organization unit ID','number',1],['position_id','Position ID','number',0],['headcount_requested','Headcount requested','number',1],['budget_amount','Budget amount ETB','number',1],['justification','Business justification','textarea',1]],cols:['plan_code','fiscal_year','org_unit_id','headcount_requested','budget_amount','status']},
 'requisitions':{label:'Vacancy requisitions',fields:[['requisition_code','Requisition code','text',1],['workforce_plan_id','Workforce plan ID','number',0],['position_id','Position ID','number',1],['location_id','Work location ID','number',1],['openings','Number of openings','number',1],['requested_start','Requested start date','date',0],['budget_amount','Approved budget ETB','number',0],['business_reason','Business reason','textarea',1]],cols:['requisition_code','position_id','location_id','openings','requested_start','status']},
 'people':{label:'Person records',fields:[['person_code','Person code','text',1],['first_name','First name','text',1],['middle_name','Middle name','text',0],['last_name','Last name','text',1],['preferred_name','Preferred name','text',0],['phone','Phone','tel',0],['email','Email','email',0],['source_record','Source / privacy notice reference','text',0]],cols:['person_code','first_name','middle_name','last_name','privacy_state']},
 'applications':{label:'Applications',fields:[['application_code','Application code','text',1],['requisition_id','Approved vacancy ID','number',1],['person_id','Person ID','number',1],['privacy_notice_version','Privacy notice version','text',1],['consent_or_lawful_basis','Lawful basis / consent reference','text',1],['applied_at','Applied at','datetime-local',1],['retention_until','Retention review date','date',0]],cols:['application_code','requisition_id','person_id','stage','status']},
 'interviews':{label:'Interviews',fields:[['application_id','Application ID','number',1],['interview_round','Interview round','text',1],['interview_at','Interview time','datetime-local',0],['interviewer_user_id','Interviewer user ID','number',1],['rubric_version','Assessment rubric version','text',1],['score','Score','number',0],['evidence_ref','Evidence reference','text',0],['notes_redacted','Redacted notes','textarea',0]],cols:['application_id','interview_round','interviewer_user_id','score','recommendation']},
 'offers':{label:'Offers',fields:[['offer_code','Offer code','text',1],['application_id','Application ID','number',1],['employment_type','Employment type','text',1],['proposed_start','Proposed start date','date',1],['proposed_end','Proposed end date','date',0],['position_id','Position ID','number',1],['location_id','Work location ID','number',1],['compensation_assignment_id','Approved compensation assignment ID','number',0],['terms_version','Contract / offer terms version','text',1]],cols:['offer_code','application_id','employment_type','proposed_start','status']},
 'employments':{label:'Employment relationships',fields:[],cols:['employment_code','person_id','employment_type','start_date','end_date','status']},
 'contracts':{label:'Contract versions',fields:[['employment_id','Employment ID','number',1],['contract_code','Contract code','text',1],['contract_version','Version number','number',1],['contract_type','Contract type','text',1],['start_date','Start date','date',1],['end_date','End date','date',0],['probation_end','Probation review date','date',0],['terms_hash','Approved terms SHA-256','text',1]],cols:['contract_code','employment_id','contract_version','status','signed_by_person_at']},
 'onboarding-tasks':{label:'Onboarding tasks',fields:[['employment_id','Employment ID','number',1],['task_code','Task code','text',1],['task_title','Task title','text',1],['owner_role','Responsible role','text',1],['due_date','Due date','date',0],['evidence_ref_id','Evidence document ID','number',0]],cols:['employment_id','task_code','task_title','owner_role','status']},
 'assignments':{label:'Assignments & transfers',fields:[['employment_id','Employment ID','number',1],['position_id','Position ID','number',1],['org_unit_id','Organization unit ID','number',1],['location_id','Work location ID','number',1],['manager_employment_id','Manager employment ID','number',0],['effective_from','Effective from','date',1],['effective_to','Effective to','date',0],['reason_code','Reason code','text',1]],cols:['employment_id','position_id','location_id','effective_from','effective_to','approval_status']},
 'schedules':{label:'Shift schedules',fields:[['schedule_code','Schedule code','text',1],['employment_id','Employment ID','number',1],['effective_from','Effective from','date',1],['effective_to','Effective to','date',0],['timezone','Time zone','text',1]],cols:['schedule_code','employment_id','effective_from','effective_to','status']},
 'time-events':{label:'Attendance & time events',fields:[['event_code','Time event code','text',1],['employment_id','Employment ID','number',1],['location_id','Work location ID','number',1],['event_type','Event type','text',1],['event_date','Work date','date',1],['time_in','Time in','datetime-local',0],['time_out','Time out','datetime-local',0],['ordinary_minutes','Ordinary minutes','number',0],['overtime_minutes','Overtime minutes','number',0],['night_minutes','Night minutes','number',0],['rest_day_minutes','Rest-day minutes','number',0],['holiday_minutes','Holiday minutes','number',0],['source_reference','Source reference','text',0],['correction_reason','Correction reason (if correction)','textarea',0]],cols:['event_code','employment_id','location_id','event_date','ordinary_minutes','overtime_minutes','status']},
 'leave-policies':{label:'Leave policy versions',fields:[['policy_code','Policy code','text',1],['leave_type','Leave type','text',1],['policy_version','Policy version','number',1],['effective_from','Effective from','date',1],['effective_to','Effective to','date',0],['accrual_method','Accrual method','text',1],['accrual_amount','Accrual amount','number',1],['annual_limit','Annual limit days','number',0],['carry_forward_limit','Carry-forward days','number',0],['paid_status','Paid status','text',1],['legal_rule_id','Reviewed legal rule ID','number',0]],cols:['policy_code','leave_type','effective_from','effective_to','approval_status']},
 'leave-transactions':{label:'Leave requests & ledger',fields:[['transaction_code','Transaction code','text',1],['employment_id','Employment ID','number',1],['leave_policy_id','Policy ID','number',1],['transaction_type','Transaction type','text',1],['start_date','Start date','date',1],['end_date','End date','date',1],['quantity_days','Requested days','number',1],['reason','Reason / protected absence code','textarea',0],['evidence_ref_id','Private evidence document ID','number',0]],cols:['transaction_code','employment_id','transaction_type','start_date','end_date','quantity_days','status']},
 'compensation-assignments':{label:'Effective-dated compensation',fields:[['assignment_code','Compensation code','text',1],['employment_id','Employment ID','number',1],['currency','Currency','text',1],['base_salary','Approved base salary ETB','number',1],['effective_from','Effective from','date',1],['effective_to','Effective to','date',0],['reason_code','Change reason','text',1],['reason_text','Approval evidence / explanation','textarea',1],['source_type','Approved instruction type','text',1],['source_reference','Instruction reference','text',1]],cols:['assignment_code','employment_id','base_salary','effective_from','effective_to','status']},
 'compensation-items':{label:'Allowances, benefits & deductions',fields:[['compensation_assignment_id','Compensation assignment ID','number',1],['component_code','Component code','text',1],['component_name','Component name','text',1],['component_type','EARNING / BENEFIT / DEDUCTION / etc.','text',1],['amount','Amount ETB','number',1],['frequency','Frequency','text',1],['cash_payable','Cash payable (1/0)','number',1],['taxable_status','TAXABLE / EXEMPT / LEGAL_REVIEW','text',1],['pensionable_status','PENSIONABLE / NON_PENSIONABLE / LEGAL_REVIEW','text',1],['legal_rule_id','Approved legal rule ID','number',0],['evidence_ref_id','Approval evidence document ID','number',0]],cols:['compensation_assignment_id','component_code','component_type','amount','taxable_status','pensionable_status','status']},
 'legal-rules':{label:'Statutory / payroll rule register',fields:[['rule_code','Rule code','text',1],['rule_family','Rule family','text',1],['jurisdiction','Jurisdiction','text',1],['source_title','Official instrument title','text',1],['source_article','Article / clause','text',1],['source_url','Official source URL','url',1],['source_sha256','Official source SHA-256','text',1],['publication_date','Publication date','date',0],['effective_from','Effective from','date',1],['effective_to','Effective to','date',0],['formula_key','Formula key','text',1],['formula_parameters','Approved formula parameters JSON','textarea',1],['applicability','Applicability and scope','textarea',1],['exceptions','Exceptions / exclusions','textarea',0],['test_vectors_sha256','Approved test vectors SHA-256','text',1]],cols:['rule_code','rule_family','source_article','effective_from','effective_to','review_status']},
 'legal-rule-bands':{label:'Progressive tax rule bands',fields:[['legal_rule_id','Legal rule ID','number',1],['band_order','Band order','number',1],['lower_bound','Lower bound ETB','number',1],['upper_bound','Upper bound ETB (blank = none)','number',0],['rate','Rate as decimal','number',1],['constant_credit','Constant credit ETB','number',1],['calculation_method','Calculation method','text',1]],cols:['legal_rule_id','band_order','lower_bound','upper_bound','rate']},
 'accounting-config':{label:'Payroll / AP GL mappings',fields:[['config_code','Mapping version code','text',1],['wage_expense_account','Wages expense account code','text',1],['employer_pension_expense_account','Employer pension expense account','text',1],['net_payable_account','Net wages payable account','text',1],['paye_payable_account','PAYE payable account','text',1],['employee_pension_payable_account','Employee pension payable account','text',1],['employer_pension_payable_account','Employer pension payable account','text',1],['other_deductions_payable_account','Other deductions payable account','text',1],['cash_account_code','Cash/bank account code','text',1],['payee_expense_account','Payee expense account','text',1],['payee_payable_account','Payee payable account','text',1],['payee_withholding_account','Payee withholding account','text',1],['effective_from','Effective from','date',1],['effective_to','Effective to','date',0]],cols:['config_code','effective_from','effective_to','status']},
 'payee-engagements':{label:'Creator / freelancer engagements',fields:[['engagement_code','Engagement code','text',1],['payee_person_id','Payee person ID','number',1],['engagement_type','Engagement type','text',1],['campaign_or_service','Campaign or service','text',1],['start_date','Start date','date',1],['end_date','End date','date',0],['contract_ref_id','Private contract document ID','number',0],['deliverable_terms','Deliverables / terms','textarea',1]],cols:['engagement_code','engagement_type','campaign_or_service','start_date','status']},
 'payee-requests':{label:'Payee requests & withholding',fields:[['request_code','Request code','text',1],['engagement_id','Engagement ID','number',1],['request_type','Cash / in-kind / mixed','text',1],['service_period_start','Service period start','date',0],['service_period_end','Service period end','date',0],['cash_amount','Cash amount ETB','number',1],['in_kind_value','In-kind consideration value ETB','number',1],['currency','Currency','text',1],['invoice_or_receipt_ref','Invoice / evidence reference','text',0]],cols:['request_code','engagement_id','cash_amount','in_kind_value','classification_status','status']},
 'payee-deliverables':{label:'Payee deliverables',fields:[['payee_request_id','Payee request ID','number',1],['deliverable_code','Deliverable code','text',1],['deliverable_type','Deliverable type','text',1],['evidence_ref_id','Evidence document ID','number',0],['notes_redacted','Redacted notes','textarea',0]],cols:['payee_request_id','deliverable_code','deliverable_type','status']},
 'payee-classifications':{label:'Payee tax classification reviews',fields:[['payee_request_id','Payee request ID','number',1],['rule_id','Effective approved withholding rule ID','number',1],['classification','Classification decision','text',1],['withholding_required','RULE_DECIDED / LEGAL_REVIEW','text',1],['reason','Legal basis / evidence rationale','textarea',1],['evidence_ref_id','Private evidence document ID','number',0]],cols:['payee_request_id','classification','withholding_required','status']},
 'social-snapshots':{label:'Manual social insights snapshots',fields:[['snapshot_code','Snapshot code','text',1],['platform','Platform','text',1],['account_label','Account label','text',1],['campaign_code','Campaign code','text',0],['reporting_period_start','Period start','date',1],['reporting_period_end','Period end','date',1],['as_of_at','Captured at','datetime-local',1],['views','Views','number',0],['reach','Reach','number',0],['engagements','Engagements','number',0],['link_clicks','Link clicks','number',0],['attributable_sales','Verified attributable sales ETB','number',0],['campaign_fee','Campaign fee ETB','number',0],['gifted_stock_value','Gifted stock value ETB','number',0],['source_reference','Export / screenshot reference','text',1]],cols:['snapshot_code','platform','account_label','reporting_period_start','reporting_period_end','as_of_at','freshness_status']},
 'exit-settlements':{label:'Exit settlements',fields:[],cols:['settlement_code','employment_id','exit_date','status','gross_total','deduction_total','net_total']},
 'exit-items':{label:'Final settlement items',fields:[],cols:['settlement_id','item_code','amount','item_type','legal_rule_id']},
 'exit-clearance-tasks':{label:'Exit clearance tasks',fields:[],cols:['settlement_id','task_code','title','status']},
 'exit-payouts':{label:'Final settlement payout evidence',fields:[],cols:['settlement_id','payment_reference','amount','status','settled_at']},
 'payroll-runs':{label:'Monthly frozen payroll runs',fields:[],cols:['run_code','period_start','period_end','status','employee_count','gross_total','paye_total','net_total','payout_state']},
 'payroll-lines':{label:'Payroll line details',fields:[],cols:['payroll_run_id','employment_id','person_id','line_status','gross_pay','paye','employee_pension','net_pay']},
 'payout-batches':{label:'Payroll payout batches',fields:[],cols:['batch_code','payroll_run_id','method','batch_total','status','external_batch_reference']},
 'payout-items':{label:'Payout items & outcomes',fields:[],cols:['payout_batch_id','payroll_line_id','payment_reference','amount','status','settled_at']},
 'statutory-obligations':{label:'Statutory obligations',fields:[],cols:['obligation_code','payroll_run_id','obligation_type','amount_due','due_date','status']},
 'remittances':{label:'Tax / pension remittances',fields:[],cols:['remittance_code','obligation_id','amount','payment_date','government_reference','status']},
 'documents':{label:'Private HR documents & resources',fields:[],cols:['document_code','entity_type','entity_id','document_type','content_sha256','mime_type','size_bytes','approval_status','effective_date','retention_until']}
};
function hr(){
 const s=window.__stocklandHrxUI||{tab:'workforce',entity:'org-units'};
 window.__stocklandHrxUI=s;
 const tabs=Object.keys(HRX_UI_TABS).map(k=>'<button type="button" class="sl-hrx-tab'+(s.tab===k?' active':'')+'" data-hrx-tab="'+k+'" aria-pressed="'+(s.tab===k?'true':'false')+'">'+HRX_UI_TABS[k].label+'</button>').join('');
 return '<div id="hrPage" class="sl-page sl-hrx-page"><div class="sl-page-title"><div><div class="sl-eyebrow">STOCKLAND ETHIOPIA · PEOPLE & PAYROLL</div><h2>HR & Payroll Lifecycle</h2><p class="sl-sub">A controlled workflow from workforce planning to final settlement. Statutory calculations remain blocked until official rule versions receive qualified review.</p></div><div class="sl-actions"><button type="button" id="refreshHR" class="sl-btn">Refresh workspace</button></div></div><div id="hrxSummary" class="sl-hrx-summary"><div class="sl-empty">Checking HR replacement schema and access…</div></div><div class="sl-hrx-tabs" role="tablist" aria-label="HR and payroll modules">'+tabs+'</div><section id="hrxContent" class="sl-hrx-content"><div class="sl-empty">Loading authorized HR workflows…</div></section><p class="sl-sub sl-hrx-footer">All actions are validated by the server and must be audited. Do not enter estimated salary or statutory values; use approved evidence and effective-dated rules.</p></div>';
}
function hrxLabel(entity){return HRX_ENTITY_META[entity]?.label||entity.replace(/-/g,' ')}
function hrxEsc(value){return esc(value===null||value===undefined?'':String(value))}
function hrxValueText(v){if(v===null||v===undefined||v==='')return '—';if(typeof v==='object')return '[structured value]';return String(v)}
function hrxRecordTitle(entity,row){
 const keys=['title','unit_name','plan_code','requisition_code','position_code','person_code','application_code','offer_code','employment_code','contract_code','task_title','schedule_code','event_code','transaction_code','policy_code','assignment_code','component_name','rule_code','config_code','engagement_code','request_code','snapshot_code','settlement_code','run_code','batch_code','obligation_code','remittance_code','document_code','id'];
 for(const k of keys)if(row[k]!==undefined&&row[k]!==null&&String(row[k])!=='')return String(row[k]);
 return entity+' #'+String(row.id||'?');
}
function hrxOptions(name){const opts={unit_type:['DEPARTMENT','TEAM','UNIT'],employment_type:['FULL_TIME','PART_TIME','FIXED_TERM','CASUAL','APPRENTICE'],component_type:['EARNING','BENEFIT','REIMBURSEMENT','DEDUCTION','BONUS','COMMISSION','OVERTIME','ADJUSTMENT'],taxable_status:['TAXABLE','EXEMPT','LEGAL_REVIEW'],pensionable_status:['PENSIONABLE','NON_PENSIONABLE','LEGAL_REVIEW'],frequency:['MONTHLY','ONE_TIME','HOURLY','DAILY'],leave_type:['ANNUAL','SICK','MATERNITY','PATERNITY','BEREAVEMENT','UNPAID','OTHER'],rule_family:['BASE_SALARY','PAYE_BASE','PAYE','EMPLOYEE_PENSION','EMPLOYER_PENSION','ALLOWANCE_TAX','OVERTIME','LEAVE','SEVERANCE','PAYEE_WITHHOLDING'],platform:['TikTok','Instagram','Facebook','YouTube','Other']};return opts[name]||null}
function hrxFormField(f){
 const name=f[0],label=f[1],type=f[2],required=!!f[3];let input='';
 const opts=hrxOptions(name);
 if(opts){input='<select name="'+hrxEsc(name)+'" '+(required?'required':'')+'><option value="">Select…</option>'+opts.map(o=>'<option value="'+hrxEsc(o)+'">'+hrxEsc(o)+'</option>').join('')+'</select>'}
 else if(type==='textarea')input='<textarea name="'+hrxEsc(name)+'" rows="2" '+(required?'required':'')+'></textarea>';
 else input='<input type="'+hrxEsc(type)+'" name="'+hrxEsc(name)+'" '+(required?'required':'')+(type==='number'?' step="any"':'')+'>';
 return '<label class="sl-hrx-field"><span>'+hrxEsc(label)+(required?' *':'')+'</span>'+input+'</label>';
}
function hrxActionButton(label,action,id,cls){
 return '<button type="button" class="sl-btn sl-btn-sm '+(cls||'')+'" data-hrx-action="'+hrxEsc(action)+'" data-hrx-id="'+hrxEsc(id||'')+'">'+hrxEsc(label)+'</button>';
}
function hrxActions(entity,row,cap){
 const id=Number(row.id||0),status=String(row.status||row.review_status||row.approval_status||row.stage||row.line_status||'').toUpperCase();
 let buttons='';
 if(entity==='payroll-runs'){
  if(status==='DRAFT')buttons+=hrxActionButton('Preflight','payroll-preflight',id)+hrxActionButton('Calculate frozen run','payroll-calculate',id,'primary');
  if(status==='CALCULATED')buttons+=hrxActionButton('Submit for review','payroll-submit',id);
  if(status==='SUBMITTED'&&cap.owner_approve)buttons+=hrxActionButton('Owner approve','payroll-approve',id,'primary');
  if(status==='APPROVED'&&cap.owner_approve)buttons+=hrxActionButton('Create payout batch','payout-create',id);
  if(['APPROVED','PAID'].includes(status)&&cap.payroll_manage)buttons+=hrxActionButton('Create remittance','remittance-create',id);
  if(status==='APPROVED'&&cap.owner_approve)buttons+=hrxActionButton('Close period','payroll-close',id);
 }else if(entity==='employments'&&status==='PENDING_ACTIVATION'&&cap.owner_approve)buttons+=hrxActionButton('Activate hire','employment-activate',id,'primary');
 else if(entity==='employments'&&status==='ACTIVE'&&cap.owner_approve)buttons+=hrxActionButton('Revoke access','employment-revoke',id);
 else if(entity==='exit-settlements'){
  if(status==='DRAFT'&&cap.hr_manage)buttons+=hrxActionButton('Calculate settlement','exit-calculate',id);
  if(status==='CALCULATED'&&cap.owner_approve)buttons+=hrxActionButton('Approve settlement','exit-approve',id,'primary');
  if(status==='APPROVED'&&cap.payroll_manage)buttons+=hrxActionButton('Record payout','exit-payout',id);
 }else if(entity==='documents'&&status==='DRAFT'&&cap.owner_approve)buttons+=hrxActionButton('Review / approve','document-review',id);
 else if(entity==='legal-rules'&&status==='LEGAL_REVIEW'&&cap.owner_approve)buttons+=hrxActionButton('Qualified rule review','legal-review',id);
 else{
  const transitions=[];
  if(['DRAFT','RECEIVED'].includes(status))transitions.push(['SUBMIT','Submit']);
  if(status==='LEGAL_REVIEW'&&entity==='leave-policies')transitions.push(['SUBMIT','Submit for review']);
  if(status==='LEGAL_REVIEW'&&entity==='legal-rules')transitions.push(['SUBMIT_LEGAL_REVIEW','Start review']);
  if(status==='SUBMITTED'&&cap.owner_approve&&!['applications','interviews','time-events','leave-transactions','onboarding-tasks'].includes(entity))transitions.push(['APPROVE','Approve']);
  if(status==='SUBMITTED')transitions.push(['REJECT','Reject']);
  if(status==='APPROVED'&&entity==='requisitions')transitions.push(['OPEN','Open vacancy']);
  if(status==='OPEN'&&entity==='requisitions')transitions.push(['CLOSE','Close vacancy']);
  if(status==='RECEIVED'&&entity==='applications')transitions.push(['SCREEN','Screen']);
  if(status==='SCREENING'&&entity==='applications')transitions.push(['SHORTLIST','Shortlist']);
  if(status==='SHORTLISTED'&&entity==='applications')transitions.push(['SELECT','Select with human rationale']);
  if(status==='PENDING'&&entity==='interviews')transitions.push(['RECOMMEND','Recommend']);
  if(status==='OPEN'&&['onboarding-tasks','exit-clearance-tasks'].includes(entity))transitions.push(['COMPLETE','Mark complete']);
  if(status==='DRAFT'&&entity==='time-events')transitions.push(['SUBMIT','Submit time']);
  if(status==='DRAFT'&&entity==='leave-transactions')transitions.push(['SUBMIT','Submit leave']);
  for(const [a,l] of transitions)buttons+=hrxActionButton(l,'transition:'+entity+':'+a,id, a==='APPROVE'?'primary':'');
 }
 if(['offers','contracts'].includes(entity)&&cap.hr_manage&&['DRAFT','SUBMITTED'].includes(status))buttons+=hrxActionButton('Open workflow details','inspect:'+entity,id);
 return buttons||'<span class="sl-sub">No permitted action for this state</span>';
}
function hrxTable(entity,data,cap){
 const meta=HRX_ENTITY_META[entity]||{label:entity,fields:[],cols:[]},rows=Array.isArray(data.records)?data.records:[];
 const canCreate=!!cap.hr_manage&&meta.fields.length>0&&entity!=='documents'&&entity!=='payroll-runs';
 let top='<div class="sl-hrx-list-head"><div><h3>'+hrxEsc(meta.label)+'</h3><p class="sl-sub">Showing '+rows.length+' record(s) in the authorized query window. Use Refresh to re-query.</p></div><div class="sl-actions"><button type="button" class="sl-btn" data-hrx-refresh>Refresh list</button></div></div>';
 if(canCreate)top+='<details class="sl-hrx-create"><summary>Create '+hrxEsc(meta.label.toLowerCase())+'</summary><form class="sl-hrx-form" data-hrx-create="'+hrxEsc(entity)+'"><div class="sl-hrx-fields">'+meta.fields.map(hrxFormField).join('')+'</div><div class="sl-hrx-form-actions"><button type="submit" class="sl-btn primary">Save draft</button><span class="sl-sub">This saves a draft. It does not approve, activate, calculate or pay.</span></div></form></details>';
 if(entity==='payroll-runs'&&cap.payroll_manage)top+='<details class="sl-hrx-create"><summary>Create monthly payroll draft</summary><form class="sl-hrx-form" data-hrx-payroll-create><div class="sl-hrx-fields"><label class="sl-hrx-field"><span>Payroll month *</span><input type="month" name="month" required></label></div><div class="sl-hrx-form-actions"><button type="submit" class="sl-btn primary">Create draft run</button><span class="sl-sub">Preflight blocks unresolved compensation, time, leave or legal rules.</span></div></form></details>';
 if(!rows.length)return top+'<div class="sl-empty-rich"><b>No records returned.</b><span>Use the authorized creation flow above or verify the record’s access scope.</span></div>';
 let cols=meta.cols||[];if(!cols.length)cols=Object.keys(rows[0]).filter(k=>!['created_by','updated_by','created_at','updated_at','private_storage_key','notes','terms_json','snapshot_json','formula_parameters','applicability','exceptions'].includes(k)).slice(0,7);
 const head=cols.map(k=>'<th>'+hrxEsc(k.replace(/_/g,' '))+'</th>').join('');
 const body=rows.map(row=>'<tr><td class="sl-hrx-title-cell" colspan="0"><strong>'+hrxEsc(hrxRecordTitle(entity,row))+'</strong><div class="sl-sub">#'+hrxEsc(row.id)+' · '+hrxEsc(row.status||row.review_status||row.approval_status||row.stage||row.line_status||'RECORDED')+'</div></td>'+cols.map(k=>'<td data-label="'+hrxEsc(k.replace(/_/g,' '))+'">'+hrxEsc(hrxValueText(row[k]))+'</td>').join('')+'<td data-label="Actions"><div class="sl-hrx-row-actions">'+hrxActions(entity,row,cap)+'</div></td></tr>').join('');
 return top+'<div class="sl-table-wrap sl-hrx-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Record</th>'+head+'<th>Actions</th></tr></thead><tbody>'+body+'</tbody></table></div>';
}
function hrxDocumentsUI(data,cap){
 const docs=Array.isArray(data.documents)?data.documents:[];
 const upload=cap.hr_manage?'<details class="sl-hrx-create"><summary>Upload HR SOP / JD / contract template / evidence</summary><form class="sl-hrx-form" id="hrxDocumentUpload"><div class="sl-hrx-fields"><label class="sl-hrx-field"><span>Document owner *</span><select name="entity_type" required><option value="HR_RESOURCE">HR Process Center resource (owner ID 0)</option><option value="APPLICATION">Candidate application</option><option value="EMPLOYMENT">Employment</option><option value="CONTRACT">Signed employment contract</option><option value="PAYROLL_RUN">Payroll run</option><option value="PAYROLL_LINE">Individual payroll line / payslip</option><option value="EXIT_SETTLEMENT">Exit settlement</option><option value="PAYEE_REQUEST">Payee request</option><option value="LEGAL_RULE">Legal rule</option><option value="REMITTANCE">Tax/pension remittance</option></select></label><label class="sl-hrx-field"><span>Owner record ID (0 only for HR Resource)</span><input type="number" min="0" name="entity_id" value="0" required></label><label class="sl-hrx-field"><span>Document type *</span><select name="document_type" required><option value="HR_SOP">Function SOP</option><option value="ROLE_JD">Role job description</option><option value="CONTRACT_TEMPLATE">Blank contract template</option><option value="EMPLOYMENT_CONTRACT">Signed employment contract</option><option value="PAYSLIP">Payslip</option><option value="LEGAL_REVIEW_OPINION">Legal reviewer opinion</option><option value="TAX_REMITTANCE_RECEIPT">Tax remittance receipt</option><option value="PENSION_REMITTANCE_RECEIPT">Pension remittance receipt</option><option value="CODE_OF_CONDUCT">Code of conduct</option><option value="ALLOWANCE_CATALOGUE">Allowance/payee catalogue</option><option value="OTHER_HR">Other HR resource</option></select></label><label class="sl-hrx-field"><span>Effective date</span><input type="date" name="effective_date"></label><label class="sl-hrx-field"><span>Review / retention date</span><input type="date" name="retention_until"></label><label class="sl-hrx-field sl-hrx-file-field"><span>Private file (PDF, DOCX, XLSX, JPG or PNG; up to 8 MiB) *</span><input type="file" name="file" accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png" required></label></div><div class="sl-hrx-form-actions"><button type="submit" class="sl-btn primary">Upload as private draft</button><span class="sl-sub">Upload creates an unapproved private record. Independent Owner review is required before download.</span></div></form></details>':'';
 const table=docs.length?'<div class="sl-table-wrap sl-hrx-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr><th>Document / Resource</th><th>Type</th><th>Format</th><th>Hash</th><th>Status</th><th>Access</th><th>Action</th></tr></thead><tbody>'+docs.map(d=>'<tr><td><strong>'+hrxEsc(d.document_code)+'</strong><div class="sl-sub">'+hrxEsc(d.entity_type)+' #'+hrxEsc(d.entity_id)+' · v'+hrxEsc(d.version_no)+'</div></td><td>'+hrxEsc(d.document_type)+'</td><td>'+hrxEsc(d.mime_type)+'<div class="sl-sub">'+hrxEsc(d.size_bytes)+' bytes</div></td><td><code>'+hrxEsc(String(d.content_sha256||'').slice(0,16))+'…</code></td><td><span class="sl-pill '+(d.approval_status==='APPROVED'?'ok':d.approval_status==='REJECTED'?'bad':'warn')+'">'+hrxEsc(d.approval_status)+'</span></td><td>'+(d.download_url?'<a class="sl-btn sl-btn-sm" href="'+hrxEsc(d.download_url)+'" target="_self">Download private</a>':'<span class="sl-sub">Approval/access required</span>')+'</td><td>'+hrxActions('documents',d,cap)+'</td></tr>').join('')+'</tbody></table></div>':'<div class="sl-empty-rich"><b>No private HR documents in this query.</b><span>Upload the approved role/JD, contract template, SOP or evidence file, then obtain independent review.</span></div>';
 return '<div class="sl-hrx-list-head"><div><h3>Private HR Resource Center</h3><p class="sl-sub">The Resource Center supports approved document versions, hashes, private download, and audit controls. No public Media Library URL is used.</p></div><button type="button" class="sl-btn" data-hrx-refresh>Refresh resources</button></div>'+upload+table;
}
function hrxPayrollSpecial(entity,id,action){
 const askNote=(label,min)=>{const x=prompt(label);return x&&x.trim().length>=min?x.trim():null};
 if(action==='payroll-preflight')return api16('/hr/replacement/payroll/runs/'+id+'/preflight');
 if(action==='payroll-calculate')return api16('/hr/replacement/payroll/runs/'+id+'/calculate',{method:'POST',body:JSON.stringify({})});
 if(action==='payroll-submit')return api16('/hr/replacement/payroll/runs/'+id+'/submit',{method:'POST',body:JSON.stringify({})});
 if(action==='payroll-approve'){const note=askNote('Independent Owner approval note (minimum 30 characters):',30);if(!note)throw new Error('A documented independent approval note is required.');return api16('/hr/replacement/payroll/runs/'+id+'/approve',{method:'POST',body:JSON.stringify({approval_note:note,idempotency_key:'ui-'+crypto.randomUUID()})})}
 if(action==='payroll-close'){if(!confirm('Close and lock this reconciled payroll period?'))throw new Error('Cancelled.');return api16('/hr/replacement/payroll/runs/'+id+'/close',{method:'POST',body:JSON.stringify({close_note:'Owner confirmed period closure.',idempotency_key:'ui-'+crypto.randomUUID()})})}
 if(action==='payout-create')return api16('/hr/replacement/payroll/runs/'+id+'/payout-batch',{method:'POST',body:JSON.stringify({method:'MANUAL_BANK',idempotency_key:'ui-'+crypto.randomUUID()})});
 if(action==='remittance-create')return api16('/hr/replacement/payroll/runs/'+id+'/remittances',{method:'POST',body:JSON.stringify({})});
 if(action==='employment-activate'){const note=askNote('Owner: verify contract, required documents, approved compensation and onboarding before activation (minimum 25 characters):',25);if(!note)throw new Error('Required activation evidence is missing.');return api16('/hr/replacement/employments/'+id+'/activate',{method:'POST',body:JSON.stringify({approval_note:note,idempotency_key:'ui-'+crypto.randomUUID()})})}
 if(action==='employment-revoke'){const note=askNote('Owner: state the documented access-revocation reason (minimum 20 characters):',20);if(!note)throw new Error('Reason is required.');return api16('/hr/replacement/employments/'+id+'/revoke-access',{method:'POST',body:JSON.stringify({reason:note,idempotency_key:'ui-'+crypto.randomUUID()})})}
 if(action==='exit-calculate')return api16('/hr/replacement/exits/'+id+'/calculate',{method:'POST',body:JSON.stringify({})});
 if(action==='exit-approve'){const note=askNote('Owner approval rationale for final settlement (minimum 30 characters):',30);if(!note)throw new Error('Approval rationale is required.');return api16('/hr/replacement/exits/'+id+'/approve',{method:'POST',body:JSON.stringify({approval_note:note,idempotency_key:'ui-'+crypto.randomUUID()})})}
 if(action==='exit-payout'){const ref=askNote('External bank/cash payment reference:',3);if(!ref)throw new Error('Payment reference required.');return api16('/hr/replacement/exits/'+id+'/payout',{method:'POST',body:JSON.stringify({payment_reference:ref,idempotency_key:'ui-'+crypto.randomUUID()})});
 }
 return Promise.reject(new Error('Unsupported HRX action.'));
}
async function loadHR(){
 const page=document.getElementById('hrPage');if(!page)return;
 const out=document.getElementById('hrxContent');const status=document.getElementById('hrxSummary');if(out)out.innerHTML='<div class="sl-empty">Loading HRX schema and authorized workflow…</div>';
 try{
  const boot=await api16('/hr/replacement/bootstrap');window.__stocklandHrxUI=window.__stocklandHrxUI||{tab:'workforce',entity:'org-units'};const s=window.__stocklandHrxUI;s.bootstrap=boot;
  const schema=boot.schema||{};const caps=boot.capabilities||{};
  if(status)status.innerHTML='<div class="sl-hrx-stat"><span>Replacement schema</span><strong class="'+(boot.status==='SCHEMA_READY'?'ok':'warn')+'">'+hrxEsc(boot.status||'UNKNOWN')+'</strong><small>'+hrxEsc(schema.present||0)+' / '+hrxEsc(schema.total||0)+' normalized HRX tables</small></div><div class="sl-hrx-stat"><span>Authority</span><strong>'+((caps.owner_approve)?'Owner approval':'Restricted role')+'</strong><small>Server-side role and scope enforcement</small></div><div class="sl-hrx-stat"><span>Payroll gate</span><strong>Fail closed</strong><small>Current approved legal rules and compensation are required</small></div><div class="sl-hrx-stat"><span>Legacy tables</span><strong>Not an HRX source</strong><small>Legacy HR REST/write path is disabled in this candidate</small></div>';
  if(!s.bound){s.bound=true;page.addEventListener('click',hrxClick);page.addEventListener('submit',hrxSubmit)}
  document.querySelectorAll('[data-hrx-tab]').forEach(b=>{const on=b.dataset.hrxTab===s.tab;b.classList.toggle('active',on);b.setAttribute('aria-pressed',on?'true':'false')});
  if(boot.status!=='SCHEMA_READY'){
   out.innerHTML='<div class="sl-panel sl-hrx-schema-gate"><div class="sl-eyebrow">SETUP IS SEPARATE FROM PLUGIN ACTIVATION</div><h3>HRX schema is not ready</h3><p>The legacy four-table HR subsystem is not the new system of record. HRX tables are created in bounded batches only after an authorized schema administrator clicks below. No old employee or payroll history is migrated or deleted by this screen.</p><div class="sl-hrx-schema-progress"><span>Present: '+hrxEsc(schema.present||0)+'</span><span>Required by this candidate: '+hrxEsc(schema.total||0)+'</span></div>'+(caps.schema_admin?'<button type="button" class="sl-btn primary" data-hrx-action="schema-batch">Create next approved schema batch</button>':'<p class="sl-sub">Ask the authorized ERP schema administrator / Owner to install the replacement schema.</p>')+'<ul><li>Schema installer performs CREATE-only, bounded, allow-listed DDL.</li><li>It never drops, truncates, renames or modifies shared ERP tables.</li><li>Legacy-table cleanup is a separate audited operation and is not enabled by this setup button.</li></ul></div>';
   return;
  }
  await hrxLoadCurrent();
 }catch(e){if(out)out.innerHTML='<div class="sl-notice sl-danger-block"><b>HRX workspace could not load safely.</b><p>'+hrxEsc(e.message||e)+'</p><button type="button" class="sl-btn" data-hrx-action="reload">Retry</button></div>';if(status)status.innerHTML='<div class="sl-hrx-stat"><strong>Check failed</strong><small>Schema and data were not changed by this read.</small></div>';}
}
async function hrxLoadCurrent(){
 const s=window.__stocklandHrxUI||{},boot=s.bootstrap||{},cap=boot.capabilities||{},tab=HRX_UI_TABS[s.tab]||HRX_UI_TABS.workforce;let entity=s.entity;
 if(!entity||!tab.entities.includes(entity))entity=tab.entities[0];s.entity=entity;
 document.querySelectorAll('[data-hrx-tab]').forEach(b=>{const on=b.dataset.hrxTab===s.tab;b.classList.toggle('active',on);b.setAttribute('aria-pressed',on?'true':'false')});
 const content=document.getElementById('hrxContent');if(!content)return;
 const entities=tab.entities.map(e=>'<button type="button" class="sl-hrx-subtab'+(entity===e?' active':'')+'" data-hrx-entity="'+hrxEsc(e)+'">'+hrxEsc(hrxLabel(e))+'</button>').join('');
 content.innerHTML='<div class="sl-hrx-subtabs" role="tablist" aria-label="'+hrxEsc(tab.label)+' records">'+entities+'</div><div class="sl-panel" id="hrxEntityContent"><div class="sl-empty">Loading '+hrxEsc(hrxLabel(entity))+'…</div></div>';
 const panel=document.getElementById('hrxEntityContent');
 try{
  if(entity==='documents'){
   const qs='/hr/replacement/documents';
   const d=await api16(qs);panel.innerHTML=hrxDocumentsUI(d,cap);
  }else{
   const d=await api16('/hr/replacement/entities/'+encodeURIComponent(entity)+'?limit=50&offset=0');
   panel.innerHTML=hrxTable(entity,d,cap);
  }
 }catch(e){panel.innerHTML='<div class="sl-notice sl-danger-block"><b>'+hrxEsc(hrxLabel(entity))+' could not load.</b><p>'+hrxEsc(e.message||e)+'</p><button type="button" class="sl-btn" data-hrx-refresh>Retry this list</button></div>';}
}
async function hrxClick(event){
 const tab=event.target.closest('[data-hrx-tab]');if(tab){const s=window.__stocklandHrxUI;s.tab=tab.dataset.hrxTab;s.entity=HRX_UI_TABS[s.tab].entities[0];await hrxLoadCurrent();return}
 const entity=event.target.closest('[data-hrx-entity]');if(entity){window.__stocklandHrxUI.entity=entity.dataset.hrxEntity;await hrxLoadCurrent();return}
 const refresh=event.target.closest('[data-hrx-refresh]');if(refresh){await hrxLoadCurrent();return}
 const b=event.target.closest('[data-hrx-action]');if(!b)return;
 const action=b.dataset.hrxAction,id=Number(b.dataset.hrxId||0),s=window.__stocklandHrxUI||{},boot=s.bootstrap||{},cap=boot.capabilities||{};
 b.disabled=true;
 try{
  if(action==='reload'){await loadHR();return}
  if(action==='schema-batch'){
   if(!confirm('Create the next bounded set of new HRX tables? This does not delete or change the legacy HR tables.'))return;
   const r=await api16('/hr/replacement/schema/batch',{method:'POST',body:JSON.stringify({limit:8})});
   toast(r.message||'Schema batch processed.');await loadHR();return;
  }
  if(action.startsWith('transition:')){
   const parts=action.split(':'),entityName=parts[1],decision=parts[2];let extra={action:decision,idempotency_key:'ui-'+crypto.randomUUID(),reason:'Workflow decision entered through the authorized HRX workspace.'};
   if(['APPROVE','ACCEPT','SELECT','RETIRE'].includes(decision)){const note=prompt('Enter the documented independent approval / decision rationale (minimum 30 characters):');if(!note||note.trim().length<30)throw new Error('A decision rationale of at least 30 characters is required.');extra.reason=note;if(decision==='ACCEPT')extra.acceptance_evidence=note;if(entityName==='legal-rules'||entityName==='leave-policies')extra.review_attestation=note}
   const r=await api16('/hr/replacement/entities/'+encodeURIComponent(entityName)+'/'+id+'/transition',{method:'POST',body:JSON.stringify(extra)});toast('HR workflow transition saved.');await hrxLoadCurrent();return;
  }
  if(action==='legal-review'){
   const reviewer=prompt('Name of qualified Ethiopian payroll/tax/legal reviewer:');if(!reviewer)throw new Error('Qualified reviewer is required.');
   const qualification=prompt('Reviewer qualification / licence:');if(!qualification)throw new Error('Reviewer qualification is required.');
   const doc=prompt('ID of independently approved private LEGAL_REVIEW_OPINION document:');if(!doc||!/^\\d+$/.test(doc))throw new Error('Approved private legal opinion document ID is required.');
   const note=prompt('Evidence-backed legal review note, source clauses and effective-date confirmation (40+ characters):');if(!note||note.trim().length<40)throw new Error('Detailed reviewer evidence is required.');
   await api16('/hr/replacement/legal-rules/'+id+'/review',{method:'POST',body:JSON.stringify({decision:'APPROVE',reviewer_name:reviewer,reviewer_qualification:qualification,review_note:note,review_evidence_document_id:Number(doc),idempotency_key:'ui-'+crypto.randomUUID()})});toast('Legal rule review decision recorded.');await hrxLoadCurrent();return;
  }
  if(action.startsWith('inspect:')){toast('Use the workflow tabs and record references; only signed private documents are accepted.');return}
  const result=await hrxPayrollSpecial(s.entity,id,action);
  if(action==='payroll-preflight'){const info=result||{};const issues=Array.isArray(info.issues)?info.issues:[];alert('Payroll preflight: '+(info.ready?'READY':'BLOCKED')+'\\nEmployees: '+(info.active_employees??'?')+'\\nExceptions: '+issues.length+'\\n'+issues.slice(0,12).map(x=>x.code+': '+x.message).join('\\n'));return}
  toast('HRX action saved.');await hrxLoadCurrent();
 }catch(e){if(e.message!=='Cancelled.')toast(e.message||String(e),true)}finally{b.disabled=false;}
}
async function hrxSubmit(event){
 const form=event.target.closest('form[data-hrx-create],form[data-hrx-payroll-create],form#hrxDocumentUpload');if(!form)return;
 event.preventDefault();if(!form.reportValidity())return;const button=form.querySelector('[type="submit"]');if(button)button.disabled=true;
 try{
  if(form.matches('[data-hrx-create]')){
   const entity=form.dataset.hrxCreate;const d=Object.fromEntries(new FormData(form).entries());
   for(const [k,v] of Object.entries(d)){if(v==='')delete d[k];else if(/_id$|^id$|^headcount_requested$|^openings$|^policy_version$|^contract_version$|^band_order$|^ordinary_minutes$|^overtime_minutes$|^night_minutes$|^rest_day_minutes$|^holiday_minutes$|^cash_payable$|^score$/.test(k))d[k]=Number(v);else if(/^(base_salary|amount|budget_amount|band_min|band_max|accrual_amount|annual_limit|carry_forward_limit|quantity_days|cash_amount|in_kind_value|views|reach|engagements|link_clicks|attributable_sales|campaign_fee|gifted_stock_value|lower_bound|upper_bound|rate|constant_credit)$/.test(k))d[k]=String(v);}
   const out=await api16('/hr/replacement/entities/'+encodeURIComponent(entity),{method:'POST',body:JSON.stringify(d)});
   toast('Draft saved. ID '+(out.id||out.record?.id||'created')+'.');form.reset();form.closest('details')?.removeAttribute('open');await hrxLoadCurrent();return;
  }
  if(form.matches('[data-hrx-payroll-create]')){
   const month=String(new FormData(form).get('month')||'');if(!/^\\d{4}-(0[1-9]|1[0-2])$/.test(month))throw new Error('Select a valid payroll month.');
   const [y,m]=month.split('-').map(Number);const last=String(new Date(y,m,0).getDate()).padStart(2,'0');const period_start=month+'-01',period_end=month+'-'+last;
   const out=await api16('/hr/replacement/payroll/runs',{method:'POST',body:JSON.stringify({period_start,period_end,idempotency_key:'ui-'+crypto.randomUUID()})});
   toast('Payroll draft created. Run preflight before any calculation.');form.reset();await hrxLoadCurrent();return;
  }
  if(form.id==='hrxDocumentUpload'){
   const fd=new FormData(form);const target=String(fd.get('entity_type')||'HR_RESOURCE');const targetId=Number(fd.get('entity_id')||0);if(target==='HR_RESOURCE'&&targetId!==0)throw new Error('HR Process Center resources use owner ID 0.');if(target!=='HR_RESOURCE'&&targetId<1)throw new Error('Enter the target record ID for a private attachment.');
   const out=await api16('/hr/replacement/documents',{method:'POST',body:fd});
   toast('Private resource stored as draft '+(out.document_code||out.id||'')+'. Independent review is required before download.');form.reset();await hrxLoadCurrent();return;
  }
 }catch(e){toast(e.message||String(e),true)}finally{if(button)button.disabled=false}
}
