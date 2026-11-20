# Ethiopian payroll statutory-rule release gate

**Current ERP state:** no statutory PAYE, employee-pension or allowance rule is shipped as a current default. The runtime does not seed a statutory PAYE/pension schedule. Payroll calculation, approval, posting and final settlement remain blocked until a new effective-dated rule version passes the independent-evidence and separate Stockland Owner ratification workflow.

This document is an engineering control register, not a legal opinion, tax computation, or statement that a statutory rate has been legally certified.

## Required source-backed rule bundle

For each rule version, retain the exact official instrument and clause, official publication identifier, publication date, applicable effective date, jurisdiction, employer/employee scope, parameter values, test vectors, reviewer scope, approval, and an SHA-256 computed from the actual downloaded official source file. A hash of a JSON rule definition is not a source-document hash.

The reviewer must issue a signed PDF or DOCX opinion that identifies the reviewer’s professional qualification/registration, independence, review date, exact instruments and clauses considered, the parameters reviewed, each approved value and effective date, assumptions/exceptions, and any unresolved matters. The document is stored through the encrypted private HR vault. The ERP calculates the file SHA-256 itself and binds it to one specific rule-set ID. A different authorized Stockland Owner must ratify the exact attached evidence. A typed note or a SHA-shaped string is not legal evidence.

## Official source discovery register

The following sources were checked on 21 November 2026. They are source-discovery evidence only; they do not establish Stockland’s legal interpretation or authorise the ERP to activate numerical rules.

| Instrument / source | Official reference | ERP treatment pending professional review |
|---|---|---|
| Federal Income Tax Proclamation No. 979/2016 | Ministry of Justice catalogue: https://justice.gov.et/en/law/proclamation-to-amend-federal-income-tax-proclamation-no-1395-2024/ (related laws include 979/2016) | Confirm the exact consolidated text and the provisions still in force after amendments. |
| Income Tax (Amendment) Proclamation | Federal Negarit Gazette No. 64 PDF: https://www.ffic.gov.et/Portals/0/1395-2017.pdf . Gazette text captions it Proclamation No. 1395/2025. Ministry of Justice English catalogue title uses “1395/2024” and its Amharic record uses Ethiopian-year “1395/2017”. Ministry of Finance lists “Income tax (amendment) proc no. 1395-2017”, uploaded 23 July 2025: https://www.mofed.gov.et/mof-directive/tax-directive/ | Reconcile the law’s canonical number, language/calendar-year labels, Gazette publication, and provision-specific effective dates before the rule bundle is signed. Do not create duplicate rule sets for the same law based on naming alone. |
| Regulation No. 597/2026 | Ministry of Justice: https://justice.gov.et/en/law/ (search exact title “REGULATION No 597/2026 A REGULATION ISSUED TO FURTHER AMEND THE COUNCIL OF MINISTERS FEDERAL INCOME TAX REGULATION”); Gazette copy: https://justice.gov.et/wp-content/uploads/2026/11/ደንብ-ቁጥር-597-2019.pdf | Official catalogue date: 6 November 2026. Its added Article 20(2) addresses income derived by residents from digital/freelance/remote services by reference to Article 98 and the regulation. Obtain and review the full provision and qualified advice for Stockland’s non-employee creator/freelancer/service-provider classification and withholding. Do not mistake this amendment for an employee monthly PAYE schedule. |
| Ministry of Finance tax directive register | https://www.mofed.gov.et/mof-directive/tax-directive/ | The page listed the Income Tax Amendment instrument and, on 21 November 2026, a draft income-tax regulation amendment uploaded 26 February 2026. A draft is not treated as enacted law unless its final adoption/publication is verified. |
| Private Organization Employees’ Pension Proclamation No. 1268/2022 | Federal Negarit Gazette No. 16, 18 March 2022, official English publication PDF: https://www.ffic.gov.et/Portals/0/______1268_1.pdf ; Ministry of Justice Social Security & Pensions catalogue: https://justice.gov.et/am/sector/social-security-pensions/ | Article 62 expressly repeals Proclamation 715/2011 and Amendment 908/2015; Article 63 states entry into force upon Gazette publication. Review the current private-organization pension provisions, coverage, contribution and pensionable-salary basis and the 2025 implementation directives. Do not cite 715/2011 as current operative law. |
| Private Organizations Employees’ Pension Implementation Directive No. 1085/2025 | Ministry of Justice, Private Organization Employees’ Social Security Administration: https://justice.gov.et/en/entity/private-organizatio-employees-social-security-administration/ | Catalogue date 10 February 2026. Obtain and review the actual directive, not only the explanatory note. |
| Pension contribution revenue planning, collection, transfer and supervision Directive No. 1087/2025 | Same official administration catalogue: https://justice.gov.et/en/entity/private-organizatio-employees-social-security-administration/ | Catalogue date 13 March 2026. Review remittance workflow, declaration, due dates, employer evidence and reconciliation. |
| Labour Proclamation No. 1156/2019 | Ministry of Justice: https://justice.gov.et/en/law/labour-proclamation/ | Review employment-contract terms, hours, overtime, leave, termination and final settlement independently of tax/pension computations. |
| Personal Data Protection Proclamation No. 1321/2024 | Ministry of Justice: https://justice.gov.et/en/law/personal-data-protection-proclamation/ | Review employee-data purpose, minimisation, sensitive documents, access scope, retention, security, processor arrangements and cross-border handling before enabling HR document workflows on the intended host. |

## Questions that must be answered in the signed opinion

1. PAYE monthly bands, marginal method, thresholds, deductions/credits, taxable-income definition, employee pension interaction, rounding and exact provision-specific commencement dates.
2. Pension coverage and the operative private-organization contribution rates; the definition of pensionable salary; treatment of allowances, overtime, bonuses and variable pay; employer/employee share; registration, declaration, remittance, interest/penalties and record retention.
3. Allowance and benefit treatment, including transport, medical, housing, meals, per diem, reimbursements, in-kind benefits, company vehicles and any claimed exemptions or caps. A category label alone never creates an exemption.
4. Labour-law rules feeding payroll: ordinary hours, overtime multipliers, rest days, holidays, leave accrual/use/encashment and final-settlement components.
5. Separate classification and withholding analysis for creators, influencers, freelancers and other service providers, including cash plus in-kind value, resident/non-resident scope, TIN/licence facts, invoices, Article 98/Schedule D interaction, and Regulation No. 597/2026.
6. Effective dates, transitional provisions, superseded rules, conflicting/ambiguous instruments and which periods may safely use each version.

## ERP approval state machine

1. HR/Finance prepares a rule as DRAFT with official source identifiers and proposed parameters. No rule is activated by creating it.
2. A qualified independent Ethiopian reviewer supplies the signed legal/payroll opinion. An authorised HR user attaches the PDF/DOCX to the rule through the encrypted private-vault upload control.
3. The ERP stores the encrypted object, exact document ID and server-computed SHA-256 against the DRAFT rule. The verifier checks the hr_documents subject (RULE_SET), type (LEGAL_OPINION), status, document hash, decrypted bytes and rule-set scope.
4. A separate Stockland Owner reviews the opinion and ratifies the exact attachment. The Owner cannot approve a rule they created. The document is marked Owner-verified in the same transaction as the rule approval and audit event.
5. Payroll rechecks that exact approved private document and hash for every effective-dated payroll period. Missing, mismatched, unreadable, expired or not-ratified evidence fails closed.

The software’s synthetic fixtures exist only to test the workflow. They are labelled synthetic and must never be used as a real legal opinion or production rule.

## Current disposition

- **No current Ethiopian statutory payroll rule is ratified in this source branch.**
- **No signed independent legal-opinion file was found in the connected Stockland project Library/Notion sources inspected for this review.** Official law PDFs and prior legal research notes are source references, not a signed professional opinion.
- The new private-vault attachment workflow is implemented in this candidate, but still needs exact-head runtime testing on disposable WordPress and intended-host key/backup/recovery checks.
- Owner, HR, Finance and a qualified independent Ethiopian legal/tax/pension reviewer must complete separate sign-offs. A source analysis or arithmetic regression does not substitute for those approvals.
- Do not enable payroll calculation, finalization, posting or payment, and do not migrate/alter historical payroll, while these gates are open.

## Acceptance tests required before release

- Signed PDF and DOCX upload positive/negative cases, MIME/size/path/symlink checks and encrypted-at-rest verification.
- Wrong-rule document ID, wrong document type/subject, wrong hash, modified encrypted file, missing key, unauthenticated request, wrong role, wrong location, expired/rejected document and non-Owner approval all fail closed.
- Rule maker cannot Owner-approve their own rule; generic HR document decisions cannot ratify a statutory opinion.
- Only the exact Owner-ratified opinion document can unblock the exact effective-dated rule; later amendment creates a new DRAFT rule/version and new evidence.
- Separate tests for PAYE, pension, allowance, overtime, leave, final settlement and non-employee withholding.
- Two complete payroll periods with independent recomputation, payslip/payout evidence, remittance reconciliation and balanced GL/PLEDGER postings, all on synthetic or approved test data.

---

**Authority reminder:** This register is a controlled engineering research record. It is not a legal opinion and does not itself ratify any numerical parameter.
