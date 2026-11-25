# Payroll evidence review, decision and operation verification — ERP 16.2.18 (2026-11-25)

Version stays **16.2.18** (series 16.1.95 → 16.2.0 … 16.2.18 only). This is an engineering record, not a legal opinion.

## 1. Evidence reviewed
| Source | What it contains | Bearing on payroll rules |
|---|---|---|
| GitHub `docs/hr/ETHIOPIA_STATUTORY_PAYROLL_EVIDENCE_AND_RATIFICATION_REGISTER_2026-11-21.md` | Official-source discovery list (Proclamations 979/2016, 1395, Regulation 597/2026, Pension 1268/2022, Directives 1085/1087, Labour 1156/2019, Data Protection 1321/2024) and the approval state machine | Source references only; states no current statutory rule is ratified and no signed opinion was found |
| GitHub `docs/hr/PAYROLL_RELEASE_RULES.md` | Release rules for rule versions | Same fail-closed gates |
| Notion Canonical Release Audit pages (Production Audit Closeout, Final Production-Readiness Decision 2 Nov, HR Design Reset v0.5) | Prior decisions | "No signed effective-dated PAYE/pension/final-settlement ruleset and no qualified written legal/tax/pension sign-off"; payroll gates OPEN; old sample amounts are regression fixtures only |
| Drive `HR_PAYROLL_ACCEPTANCE_AND_TRAINING_ADDENDUM.md` (Owner) | Release → Owner declaration → install → installed-environment proof → Owner live authorization; declaration is not an override; dedicated declaration control must go on a **new development branch**, never silently into the frozen 16.2.18 baseline | Confirms Owner/legal gates stay separate from engineering sign-off |
No signed independent legal/tax/pension opinion (PDF/DOCX) was found in GitHub, Notion or Drive.

## 2. Decisions
1. **No statutory PAYE, pension or allowance value is entered, defaulted or ratified.** The evidence contains no signed independent opinion, and an AI agent cannot supply one. Payroll generation, approval, posting and payment for real staff stay blocked exactly as designed.
2. **No change to payroll calculation code.** The existing suites (s7, s15, s16, s20) already prove the mechanism on synthetic rules; no evidence supports changing any number or formula.
3. **The Owner declaration control is not added to 16.2.18.** The Owner addendum says it belongs on a new development branch. It is a recommendation for the next version, subject to Owner approval.
4. **One genuine operational defect found and fixed** while verifying the operations the suites did not cover (below).
5. Owner / HR / Finance / qualified-reviewer sign-offs, `live_ready` authorization, any tag or GitHub Release: **not given and not claimed**.

## 3. Defect found and fixed
**Reimbursement settlement could never complete.** `pay_reimbursement` posted its ledger journal with source type `HR_EMPLOYEE_REIMBURSEMENT_PAYMENT` (33 characters) but `gl_journals.source_type` is VARCHAR(30). On MySQL 8 strict mode the insert is rejected and the API returned "Reimbursement settlement journal failed", so every approved claim stayed APPROVED. Renamed to `HR_EXPENSE_REIMB_PAYMENT` (24 characters). No historical rows can carry the old value (the insert never succeeded). All other HR journal source types were checked: the longest is exactly 30.

## 4. Operation-by-operation verification (MySQL 8.0.46, disposable site, synthetic data, rebuilt installer)
| Operation | Evidence | Result |
|---|---|---|
| Rule draft → independent opinion → Owner ratification; maker cannot approve own rule; Owner declaration rejected as legal review; wrong rule/hash/status fail closed | s15, s16, s20 | PASS |
| Payroll run: create (idempotent), generate (blocked without approved rule), freeze, submit, Owner approve/post, balanced GL | s16 | PASS |
| Recipient payout outcomes and batch reconciliation (GL journal is not proof of payment) | s16 | PASS |
| PAYE / employee pension / employer pension remittance obligations and reconciliation | s16 | PASS |
| Hiring chain: position, requisition, candidate, interview, selection, contract, onboarding, compensation, activation; maker-checker at each step | s16 | PASS |
| Exit and final settlement (rule-gated, posted, paid) | s16 | PASS |
| Private HR vault and document library (encrypt, edit, version, independent approval) | s16, s18 | PASS |
| **Employee expense reimbursement** (receipt-backed claim, review note, Owner-only approval, no double approval/payment, bank needs statement reference, journals balanced) | **s21 (new)** | PASS after fix |
| **Individual payees and obligations** (TIN encrypted, maker-checker, unapproved payee blocked, approval refused without qualified tax rule, rejection path) | **s21 (new)** | PASS |
| **Social snapshots** (platform/SHA-256/negative-metric validation, recorded as NOT real-time) | **s21 (new)** | PASS |
| Access control: cashier cannot list reimbursements or payees | s21 | PASS |
Totals from a fresh install of the rebuilt ZIP: official verifier PASS; 22 suites, 618 PASS / 0 FAIL.

## 5. Still NOT covered by an automated test
Attendance/shift/overtime and leave-request calculations; payslip rendering; payroll across two consecutive periods with independent recomputation; intended-host key/backup/restore of the private vault; any real statutory value. These remain open items for the Owner/HR/Finance acceptance phase.

## 6. Installer
`STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip` SHA-256 `58a939c8709d45631f930613217eeb1c6139e4e29f4bfd3b93f9235eaef78596`.
