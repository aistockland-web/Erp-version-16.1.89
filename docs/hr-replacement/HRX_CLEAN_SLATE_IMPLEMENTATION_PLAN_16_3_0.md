# Stockland HRX clean-slate implementation plan — 16.3.0 candidate

**Status:** engineering candidate; NOT a production deployment authorization.  
**Authority:** HR Design Reset v0.5 in Notion, issue #15 owner-approved scope, issue #23 design controls, SOP/resource library in `Stockland_HR_Library_Pack_v1`.  
**Source installer:** canonical 16.1.95 ZIP, SHA-256 `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`.  
**Build rule:** deterministic package builder; no remote production DB connection and no business-data mutation.

## 1. Product boundary

This candidate replaces the old four-table HR authority with an independent, normalized HRX domain. The four legacy tables—`employees`, `employee_compensation`, `payroll_periods`, and `payroll_lines`—are not read or written by the new HRX workflow and are not created by its schema installer. They are eligible for retirement only by an explicit, reviewed, allow-listed reset after backup and dependency checks; ordinary plugin activation must never drop or truncate them. Production cutover remains blocked until parity and acceptance are proven.

The 47-table HRX DDL currently copied into this version is a **versioned implementation proposal**, not a frozen company-wide table-count requirement. The final logical/physical model must remain traceable to source use, the ERD, SOPs, indexes, access paths, data retention, and demonstrable operational needs. Redundant or unused entities must be challenged before final acceptance.

## 2. Full lifecycle modules

| Module | Business lifecycle and system record | Key gates |
|---|---|---|
| Workforce planning | Organization units, roles/grades, position plans, headcount/budget rationale and requisitions | Budget/need approval, effective dates, duplicate/idempotent request, location scope |
| Recruitment | Person identity, candidate/application, screening, structured interviews, evidence, human selection, offer | Candidate privacy, retention basis, human decision rationale, independent approval |
| Contract/onboarding | Offer acceptance, employment relationship, versioned contract, private documents, induction and access tasks | Signed/approved contract, private-vault integrity, completed mandatory checklist, Owner approval |
| Assignment and time | Effective-dated workplace/manager/position, shift schedule, time events, overtime/night/rest/holiday markers, corrections | Approved schedules, location-scoped time entry, immutable correction links and cutoff snapshot |
| Leave | Versioned policies, leave request/approval, accrual transactions, carry-forward and balances | Current rule source, maker/checker, approved time/leave before payroll cutoff |
| Compensation | Effective-dated salary/grade and itemized allowances, benefits, incentives, reimbursement, deductions and adjustments | No inferred salary; evidence, source reference, independent approval and effective-date overlap checks |
| Statutory rule register | PAYE bands/base, pension, allowance treatment, overtime, leave, severance/final-settlement and withholding rules | Official source hash/clause, effective dates, applicability/exceptions, qualified reviewer, test vectors and independent approval |
| Payroll cycle | Monthly draft, input snapshot, preflight exceptions, calculation detail, frozen run/rule hashes, review/submit/Owner approval | Full calendar month, one run per period, no unresolved legal/data exceptions, deterministic money arithmetic |
| Payout/settlement | Recipient payout batches, external references, attempt/retry/failure/return, reconciliation, payslip and payment evidence | Idempotent payment references, status reflects confirmed external settlement, retries cannot double-pay |
| Statutory remittance | PAYE/pension obligations, remittance instructions, government reference, receipt/evidence, reconciliation | Separately verified remittance, period/rule trace, central Finance control |
| Employee exit | Notice/status change, clearance/handover, final pay/leave and other approved items, final settlement, payment, access revocation | Explicit approved components/rules, net reconciliation, independent approval and linked corrections |
| Individual payee/AP | Creator/freelancer/service engagement, deliverables, cash/in-kind valuation, legal/withholding classification, request, AP journal/payment | Nonemployee remains separate from employee payroll, central AP, rule/evidence approval, gift-stock trace |
| HR resource/reporting | Role/JD/contract/SOP library, private docs, reports and timestamped manual social/insight snapshots | Non-public document access, exact file hash, audit log, no unsupported live metrics or social credential storage |

## 3. SOP traceability

The target UI/workflows must map to the governing operating procedures:
- HR-SOP-01 Workforce plan and hiring authorization
- HR-SOP-02 Recruitment, human selection and candidate privacy
- HR-SOP-03 Contract, employment changes and onboarding
- HR-SOP-04 Attendance, shift, overtime, night/rest/holiday time
- HR-SOP-05 Leave, protected absence, accrual and balance
- HR-SOP-06 Grade, increment, promotion and compensation change
- PAY-SOP-07 Monthly payroll, PAYE, review and approval
- PAY-SOP-08 Pension, statutory remittance and reconciliation
- HR-SOP-09 Separation, clearance and final settlement
- DATA-SOP-10 Employee data, least-privilege access, retention and incidents
- FIN-SOP-11 Payroll/AP payment, GL/PLEDGER, correction and audit
- GOV-SOP-12 Legal/policy change control

Role-specific bilingual contracts/JDs, code of conduct, department SOPs, allowance/payee catalogue and social-account reporting SOPs in the resource library are implementation inputs and remain subject to Owner/HR/Finance and legal review. Template existence alone is not proof that the ERP download/approval handler works.

## 4. Domain and integration controls

- Canonical person identity is separate from employment and from external service-payee identity. Employment is only created from an approved, accepted offer; direct employee creation is not allowed.
- HRX records live in a separate prefix and explicit opt-in schema. The installer must have no automatic destructive migration, no implicit data reset and no fallback writes to the old tables.
- Money is stored as fixed precision decimal/cent arithmetic. No binary-float totals, silent currency conversion, guessed salary, inferred tax classification, or hard-coded Ethiopian statutory rates.
- Every sensitive transition validates server-side capability, role, employment/location scope, state/version, maker/checker separation, idempotency and audit evidence.
- Payroll freezes input and rules hashes; totals must reconcile; posted history is not edited. Corrections use linked reversal/adjustment records.
- GL posting must use the existing accounting journal service, closed-period guards, balanced lines and a unique HRX source reference. A journal creation is not proof that the bank paid the recipient.
- Private document reads/downloads require authenticated permission, ownership/location check, payload hash, no-store headers, auditable access, and verified private-storage handling. Do not use predictable public Media Library URLs for signed contracts, identity or payslips.
- New HRX schema is installed in bounded, explicit Owner/schema-admin batches. The new UI must remain available before schema install so that it can expose the actual status and gated setup button.
- Any retirement/reset of the four legacy HR tables uses a dedicated command with a strict allowlist, explicit environment/confirmation checks, preflight, backup receipt and audit. It must never run during plugin activation or normal updates.

## 5. Ethiopian legal and policy release gates

Keep these topics in `LEGAL_REVIEW` until qualified independent Ethiopian labour/payroll/tax/pension advice supplies source-linked disposition, exact clause, effective date, applicability, exception handling, test vectors, reviewer qualification and signed approval:
- current PAYE schedule, taxable base and rounding;
- current employee/employer pensionable-pay basis and rates;
- transport/allowance exemptions and caps after amendments;
- overtime, night/rest-day/holiday formulas, minimum time units and divisor;
- partial-month proration, joiners/leavers, leave accrual/carry-forward, severance and final-settlement components;
- digital creator/freelancer/service-payee classification, cash and in-kind consideration, withholding scope and remittance;
- retention/privacy rules for applicants and employees.

Official source discovery is not legal certification. No salary, classification, rule or allowance value may be invented to clear System Health. Unknown/conflicting legal treatment must block final calculation, approval, posting and payout.

## 6. Build and acceptance sequence

1. **Exact baseline:** verify installer hash, ZIP CRC, unique members, root path, version and schema inventory.
2. **Domain source:** include HRX schema, REST controller and deterministic calculator; retire the old HR REST controller from registration and make the old four-table schema class a non-mutating compatibility shim.
3. **UI:** replace the legacy HR screen with a responsive HRX workspace for workflow tabs, lists, forms, decisions, payroll/run actions, resources, documents and clear schema-readiness messages. Download and state-transition controls must have click handlers and success/error feedback.
4. **Static checks:** PHP lint every packaged PHP file on PHP 8.3 and 8.4; JavaScript syntax; CSS/PostCSS; schema allowlist and old table non-mutation; REST route/capability matrix; no destructive SQL in activation/build code.
5. **Functional tests:** synthetic end-to-end workforce→recruitment→offer→contract→onboarding→activation→attendance/leave→approved compensation→legal rules→payroll preflight→calculate→submit→Owner approve→balanced GL→payout/failed retry/reconcile→remittance→exit/final settlement, plus nonemployee payee/AP, downloads, duplicate submissions, race/retry, and branch-role denials.
6. **Environment acceptance:** disposable WordPress/MariaDB install; responsive click-through at 320/360/390 px and desktop; private-document authorization; REST state/nonce behavior; accounting integrity; deep audit; backup/restore and rollback.
7. **Final certification:** two parallel payroll periods using synthetic/approved rule fixtures; production source parity with the actually installed ZIP; official legal-source and professional review; Owner, HR and Finance acceptance. Only then may cutover/retirement of old HR tables be considered.

## 7. Current status and limits

The clean HRX schema/controller/calculator source exists as a disconnected repository branch. This 16.3.0 engineering branch is the integration candidate built from the known 16.1.95 installer. The live installation was previously reported as 16.1.97; exact source parity with the installed live ZIP has not been obtained. Therefore this candidate is not an in-place update for the live site and must not be installed over production.

No live site, employee records, payroll history, stock, AP/AR, GL/PLEDGER or production database is modified by this plan or the package builder. Any unexecuted or skipped check must be reported as pending, never as a pass.
