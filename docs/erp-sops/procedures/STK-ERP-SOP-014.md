# STK-ERP-SOP-014 — Accounts Payable፣ ወጪዎች እና ክፍያ

**English title:** Accounts Payable, expenses and payments  
**Document ID:** STK-ERP-SOP-014  
**Version:** v1.0-DRAFT  
**Status:** DRAFT — NOT APPROVED / NOT EFFECTIVE  
**Issue date:** 29 October 2026  
**Effective date:** only after written approval  
**Process owner:** Finance / AP  
**Approval authority:** Owner / delegated approver  
**Review cadence:** annual and on legal, workflow, hosting or software change  
**Classification:** Internal controlled document

> ማስጠንቀቂያ፦ ይህ ሰነድ ረቂቅ ነው፤ ያልጸደቀ ወይም ያልተፈተነ የሶፍትዌር ችሎታ እንዳለ አያረጋግጥም።

## 1. ዓላማ / Purpose

Accounts Payable, expenses and payments. ሂደቱ በማስረጃ፣ በፈቃድ፣ በገምጋሚና በተመዘገበ ውጤት ይፈጸማል።

## 2. የሥራ ደረጃዎች / Procedure

1. invoice፣ beneficiary፣ tax treatment እና የማጽደቅ ሥልጣን ይፈትሹ።
2. የውጫዊ ክፍያ ማስረጃ እና reconciliation ሳይኖር settled አትበሉ።

## 3. English operating controls

1. Separate requester, approver and payer for material transactions.
2. Hold uncertain tax/withholding classification for qualified review.

## 4. Shared controls

- Separate maker/reviewer/approver for high-risk operations.
- Enforce authorization, location scope and record state on the server; UI hiding is not security.
- Keep event/ledger history immutable; correct posted stock/financial entries through linked reversals.
- Missing evidence, authorization, legal rule or reconciliation means HOLD; do not guess values.

## 5. Required evidence

- Source/reference document, actor, timestamp, location and record ID where applicable.
- Approval/exception record, reconciliation and linked ledger/journal IDs.
- Relevant CI/staging test receipt and exact artifact hash where the process depends on release.

## 6. Baseline source-table map

DDL locations below are from locked 16.1.95 source ZIP SHA-256 `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`. This does not prove live 16.1.97 schema parity.

| Logical key | DDL path and line |
|---|---|
| `expenses` | `includes/class-stockland-accounting.php:231` |
| `gl_accounts` | `includes/class-stockland-accounting.php:274` |
| `gl_journals` | `includes/class-stockland-accounting.php:285` |
| `gl_lines` | `includes/class-stockland-accounting.php:299` |
| `payment_allocations` | `includes/class-stockland-accounting.php:265` |
| `payments` | `includes/class-stockland-accounting.php:248` |
| `supplier_invoice_items` | `includes/class-stockland-accounting.php:183` |
| `supplier_invoices` | `includes/class-stockland-accounting.php:166` |
| `suppliers` | `includes/class-stockland-accounting.php:64` |

## 7. Ethiopian legal / privacy review

References for qualified review, not a legal opinion: [Labour Proclamation 1156/2019](https://justice.gov.et/en/law/labour-proclamation/); [Personal Data Protection Proclamation 1321/2024](https://justice.gov.et/en/law/personal-data-protection-proclamation/); [MOF tax directives](https://www.mofed.gov.et/mof-directive/tax-directive/); [income-tax amendment reference requiring Gazette citation reconciliation](https://www.ffic.gov.et/Portals/0/1395-2017.pdf); [pension/social security](https://justice.gov.et/en/entity/private-organizatio-employees-social-security-administration/). A qualified Ethiopian reviewer must confirm applicability and effective dates.

## 8. Exceptions

Hold unresolved cases. Record exception ID, owner, due date and evidence. Never imply an external payment or cloud alert succeeded without delivery/settlement proof.

## 9. Sign-off

| Role | Name/signature | Date |
|---|---|---|
| Process owner | __________________ | __________ |
| Independent reviewer | __________________ | __________ |
| Owner / authorized approver | __________________ | __________ |
| Qualified legal/tax/pension reviewer (where applicable) | __________________ | __________ |

## 10. Change history

| Version | Date | Change | Status |
|---|---|---|---|
| v1.0-DRAFT | 29 October 2026 | Initial controlled source mapped to locked baseline. | Not approved |
