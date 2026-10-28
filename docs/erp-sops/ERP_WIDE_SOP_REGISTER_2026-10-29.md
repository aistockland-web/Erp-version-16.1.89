# ERP-wide SOP register — v1.0-DRAFT

Date: 29 October 2026. All items are **draft / review required**.

| ID | Amharic process area | Owner / acceptance focus |
|---|---|---|
| STK-ERP-SOP-001 | ሰነድ ቁጥጥር፣ ፖሊሲ እና የለውጥ አስተዳደር | Management / ERP Product Owner |
| STK-ERP-SOP-002 | የተጠቃሚ መዳረሻ፣ ሚና፣ ቅርንጫፍ እና የማጽደቅ ሥልጣን | Access owner / Owner |
| STK-ERP-SOP-003 | የProduct Master፣ SKU፣ መጠን/ቀለም እና የዋጋ ታሪክ | Product Master owner |
| STK-ERP-SOP-004 | የExcel/CSV ግቤት እና የዳታ ማረጋገጫ | Data Import owner |
| STK-ERP-SOP-005 | ዋና መጋዘን መቀበያ | Central Warehouse Manager |
| STK-ERP-SOP-006 | የመጋዘን/ቅርንጫፍ ዝውውር | Warehouse Operations |
| STK-ERP-SOP-007 | የፍላጎት ጥያቄ፣ የክምችት ማስያዣ እና መሙላት | Requisition / stock planning |
| STK-ERP-SOP-008 | የአክሲዮን አካላዊ ቆጠራ | Inventory Control |
| STK-ERP-SOP-009 | የተበላሸ ዕቃ፣ መመለሻ እና write-off | Inventory Control + Finance |
| STK-ERP-SOP-010 | ችርቻሮ POS፣ void እና refund | Retail Operations |
| STK-ERP-SOP-011 | የዕለት መዝጊያ እና cash reconciliation | Branch close + Finance reviewer |
| STK-ERP-SOP-012 | Wholesale ግዢ/ሽያጭ | Wholesale Sales |
| STK-ERP-SOP-013 | Procurement እና Supplier invoice | Procurement owner |
| STK-ERP-SOP-014 | Accounts Payable፣ ወጪዎች እና ክፍያ | Finance / AP |
| STK-ERP-SOP-015 | Accounts Receivable እና collections | Finance / AR |
| STK-ERP-SOP-016 | General Ledger፣ PLEDGER እና close | Finance Controller |
| STK-ERP-SOP-017 | Reports / Management Intelligence / social snapshots | Management Intelligence |
| STK-ERP-SOP-018 | System health፣ backup/restore እና release | Technical System Owner |
| STK-ERP-SOP-019 | Workforce plan፣ recruitment እና privacy | HR owner |
| STK-ERP-SOP-020 | Employment contract፣ onboarding እና history | HR owner |
| STK-ERP-SOP-021 | Attendance፣ shifts፣ overtime እና leave | HR + line manager |
| STK-ERP-SOP-022 | Compensation፣ payroll፣ PAYE/pension እና payslip | Payroll + Finance |
| STK-ERP-SOP-023 | Exit / final settlement / nonemployee payees | HR + central Finance/AP |
| STK-ERP-SOP-024 | Privacy, private documents, audit and retention | Data protection / ERP owner |

## Shared required controls

Maker/reviewer/approver are distinct for high-risk actions; authorization and location scope are checked server-side; finalized financial/stock records are corrected by linked reversals, not silent overwrites; import uses validation → preview → approval → commit; external payment evidence must exist before declaring settled; unknown legal/tax treatment is held for review; sensitive personal files require a real private vault; production release requires exact artifact identity, staging regression, backup/restore/rollback and Owner/HR/Finance acceptance.