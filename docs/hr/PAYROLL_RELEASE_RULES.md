# Ethiopian statutory payroll rules — release control

**Disposition: BLOCKED / NO SHIPPED STATUTORY DEFAULT**

The ERP must not insert an Ethiopian PAYE, pension, allowance, withholding, overtime, leave, or final-settlement rule as APPROVED during plugin initialization. No rate, effective date, tax base, pensionable-pay basis, component treatment, or exemption is a product default.

## Release process

1. HR/Finance prepares a versioned rule in DRAFT using the exact official instrument, article/clause, publication date, provision-specific effective date, jurisdiction and a checksum of the retained original source file.
2. A qualified independent Ethiopian tax, pension, or labour professional reviews the exact parameter set and provides a signed written opinion. Store the original opinion in the restricted HR private vault and record its stable evidence reference and SHA-256. A rule JSON digest is not a substitute for the signed source or opinion.
3. The review record must identify the reviewer, professional registration/qualification basis, review date, independence, exact clauses, all approved parameters, signed-opinion reference and hash. Unknown, expired, conflicting or out-of-scope provisions remain blocked.
4. A different authorized Stockland Owner performs the separate business approval through the Owner-only maker-checker workflow. Owner declaration, WP Administrator status, rule creator identity, or an unsigned note never qualifies as independent legal review.
5. The approved rule must pass exact-version boundary tests, payroll/allowance/final-settlement calculation tests, payroll-to-payout/remittance evidence checks and balanced GL/PLEDGER reconciliation before use. Historical posted payroll and ledger entries are never rewritten by rule approval.

The runtime validator rejects OWNER_DECLARATION. The initialization hook is intentionally a no-op. Existing rows from an older package are not deleted or rewritten by this source correction; they are rejected for use unless they independently satisfy the current evidence requirements and receive the required separate Owner approval.

## Official source register — research inputs, not legal certification

The following official records are mandatory review inputs. Listing a source here does not establish its application to Stockland, settle its interpretation, or ratify a payroll calculation.

| Domain | Official instrument / source | Release treatment |
|---|---|---|
| Employment income tax | Federal Income Tax Proclamation No. 979/2016, as amended by Income Tax (Amendment) Proclamation No. 1395/2025, Federal Negarit Gazette No. 64 | Map every applicable article, rate, base and provision-specific effective date. The amended monthly PAYE schedule is in Article 11; exact implementation still requires professional review. |
| Income-tax regulation | Council of Ministers Federal Income Tax Regulation No. 410/2017, as amended, including Regulation No. 597/2026 (Federal Negarit Gazette, 33rd Year No. 4, 28 October 2026; indexed by the Ministry of Justice on 6 November 2026) | Review the new digital-content platform and remote/freelance service provisions, including the cross-reference to withholding and Article 20. Do not infer that those provisions amend employee monthly PAYE bands. |
| Private-organization pension | Private Organization Employees’ Pension Proclamation No. 1268/2022 | This is the current source to review; do not cite repealed Proclamation No. 715/2011 as the current governing instrument. Verify coverage, salary/pensionable-pay basis, contribution percentages, effective dates and exceptions against the original text. |
| Pension implementation | Private Organizations Employees’ Pension Implementation Directive No. 1085/2025 | Review current implementation requirements and interaction with the 1268/2022 Proclamation. |
| Pension contribution collection | Directive No. 1087/2025 on revenue plans, collection, transfer and supervision of private-organization pension contributions | Map remittance, timing, evidence, transfer and reconciliation obligations. |
| Employment rights | Labour Proclamation No. 1156/2019 and current amendments/directives | Review contracts, probation, hours, overtime, weekly rest, public holidays, leave, termination, notice and final settlement. |
| Employee information | Personal Data Protection Proclamation No. 1321/2024 | Review lawful basis, purpose, retention, employee access, sensitive-data controls, vault, processor and breach obligations. |

### Official public starting points

- [Federal Negarit Gazette — Income Tax (Amendment) Proclamation No. 1395/2025, Gazette No. 64](https://www.ffic.gov.et/Portals/0/1395-2017.pdf)
- [Ministry of Justice — Finance & Tax register (Regulation No. 597/2026)](https://justice.gov.et/en/sector/finance-tax/)
- [Ministry of Justice — Social Security & Pensions register](https://justice.gov.et/am/sector/social-security-pensions/)
- [Ministry of Justice — Private Organizations Employees’ Pension Implementation Directive No. 1085/2025](https://justice.gov.et/am/directives/private-organizations-employees-pension-implementation-directive-2/)
- [Ministry of Justice — Private Organization Employees’ Social Security Administration (including Directive No. 1087/2025)](https://justice.gov.et/en/entity/private-organizatio-employees-social-security-administration/)
- [Ministry of Finance — Tax Directives](https://www.mofed.gov.et/mof-directive/tax-directive/)
- [Ministry of Justice — Labour Proclamation No. 1156/2019](https://justice.gov.et/en/law/labour-proclamation/)
- [Ministry of Justice — Personal Data Protection Proclamation No. 1321/2024](https://justice.gov.et/en/law/personal-data-protection-proclamation/)

## What remains required before legal ratification

- Original official Gazette/regulation/directive PDFs preserved in the ERP legal evidence library, with exact SHA-256 and publication/effective dates.
- Qualified independent Ethiopian legal/tax/pension reviewer’s signed opinion mapped to each parameter and rule version.
- Separate Stockland Owner approval after the independent review.
- HR and Finance acceptance of the applicable operational SOPs, payout/remittance controls and payroll-to-GL/PLEDGER reconciliation.
- Two complete parallel payroll periods with employee-level calculations, payslip comparison, remittance/payment evidence and a reviewed variance register.
- A retained rule change history, expiry/renewal control and regression evidence for every new effective-dated version.

Until those artifacts and approvals exist for the exact candidate, payroll generation that requires statutory rules must remain blocked. This page is a technical evidence register, not legal advice or a claim that the laws have been ratified for Stockland.
