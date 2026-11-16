# 14. HR and payroll — controlled availability

The HR & Payroll menu exists in 16.2.18, but the full HR lifecycle is not automatically active merely because the operational installer is installed.

## Setup boundary

The installer creates and verifies the 49-table operational baseline: 29 Core/Operations, 16 Accounting/Procurement and four legacy HR/payroll tables. The separate 37-table HR lifecycle extension and legacy-HR column migrations remain subject to explicit active-Owner approval against the exact schema version/fingerprint, backup confirmation and written reason.

If the HR page reports that lifecycle schema is pending, do not bypass the gate. Core, Inventory, POS and Finance/Procurement can be operational independently.

## Payroll and statutory rules

The release does not ship a default current Ethiopian PAYE/pension rule schedule. PAYE/pension preview, payroll calculation, finalization and posting remain blocked until an effective-dated rule set is supported by exact official references, qualified Ethiopian payroll/legal review and independent approval.

- Do not calculate statutory deductions from old examples or historical constants.
- Do not treat a draft compensation record as approved.
- Approval requires explicit status, independent approver identity and genuine approval timestamp.
- Historical payroll records must not be silently recalculated.

## SOP documents

The release includes 24 Amharic-first DOCX SOP resources. They are marked DRAFT_NOT_APPROVED. File-integrity checks confirm file integrity only; they do not mean HR, Finance, legal or process owners have approved the procedure.

Use an SOP as an effective company policy, contract or statutory instruction only after the appropriate Stockland process owners formally approve it.

When an HR module is pending, unavailable or blocked, contact Owner/Management rather than attempting a workaround.
