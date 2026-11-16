# Shipped payroll rule set (ET-2025-AMEND-1395)

Payroll works out of the box: the product inserts this rule version once the HR schema is approved and an Owner account exists. Nobody enters SHA values or JSON to start payroll.

## Parameters
| Item | Value |
|---|---|
| Employment income tax, monthly | 0-2,000 exempt; 2,001-4,000 15%; 4,001-7,000 20%; 7,001-10,000 25%; 10,001-14,000 30%; over 14,000 35% (progressive slices) |
| Tax base | Gross taxable employment income (employee pension is not deducted before PAYE) |
| Pension | Employee 7%, employer 11%, on pensionable (basic) salary |
| Rounding | Half-up to the minor unit (cent) |
| Effective from | 2025-07-01 |
| Component treatment | BASE taxable + pensionable; TAXABLE_ALLOWANCE taxable, not pensionable; EXEMPT_CANDIDATE neither; OTHER_DEDUCTION deduction |

## Basis and what it is not
- Approval basis: **Owner declaration, 2026-11-16**. The rule and its audit event say `OWNER_DECLARATION`. No independent adviser opinion is claimed.
- Sources: Income Tax Proclamation 979/2016 (Art. 10-12) as amended by Income Tax (Amendment) Proclamation 1395/2025 (published 27 July 2025); Private Organization Employees' Pension Proclamation 715/2011. The 979/2016 original bands (0-600 ... 35% over 10,900) are superseded and are not used.
- The stored source digest is the hash of the rule definition itself. Attach the gazette copies in the HR library for the audit file.
- Allowances are taxable by default. Exemptions that depend on Ministry directive limits (transport, medical, etc.) are not pre-loaded; add them as a new rule version when the limits are confirmed.

## Changing regulation
Add a new effective-dated rule version (HR > Rules form). The shipped version is never edited; the engine picks the version in force for each pay period.

## Test
`scripts/release/e2e/s20_payroll_release_rules.php` compares the engine with an independent progressive-slice oracle at 13 income levels and checks pension, allowance treatment, fail-closed behaviour with no rule, and idempotent insertion.
