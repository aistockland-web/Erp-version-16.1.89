# 15. Screenshot capture checklist

The written instructions are grounded in the 16.2.18 release. The manual contains screenshots of the exact 16.2.18 frontend, some with synthetic QA fixture values clearly identified in their captions. These demonstrate genuine application interface layout only; they are not proof of live production records, saved account changes, completed checks or release readiness. For operational sign-off, capture each required action and outcome from an authenticated 16.2.18 environment using approved, privacy-safe training data. Never use AI-generated mockups or screenshots from later releases.

## Visual coverage audit — current published manual plus this change request

This audit distinguishes embedded application screenshots from Mermaid diagrams. A diagram explains a workflow; it does not count as a screenshot of a real ERP screen. UI captures using sample fixtures illustrate controls only and must not be counted as proof of a successful save, check, approval or posting.

| Manual chapter                              | Current embedded application screenshots                                                                                                                          | Still required for complete step-by-step evidence                                                                                                                                                         |
| ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1. Getting started                          | None                                                                                                                                                              | Sign-in, successful landing/profile, displayed branch or warehouse, version indicator and mobile navigation                                                                                               |
| 2. Roles and locations                      | Owner navigation; Auditor audit and reports                                                                                                                       | Role/profile views for Management, Branch Manager, Sales Employee and Warehouse Manager; confirm branch/location access in the relevant user account                                                      |
| 3. Command Center                           | None                                                                                                                                                              | Desktop navigation, mobile menu, command launcher/search, notification and role/SOP guide                                                                                                                 |
| 4. Product Master                           | Catalogue/search view                                                                                                                                             | New/edit form, variant/size/color editing, bulk import mapping, validation preview and commit result                                                                                                      |
| 5. Inventory and physical counts            | Stock-count approval view                                                                                                                                         | Inventory search/location balance, Daily Inventory report, count workbook preview, variance list and final approval result                                                                                |
| 6. Retail POS                               | Retail POS view                                                                                                                                                   | Empty cart/header, populated cart/totals, tender/payment selection, successful posting/reference and retry-safe result                                                                                    |
| 7. Daily POS Import                         | Sales Employee import screen; Branch Manager review queue                                                                                                         | File selection/staging detail, validation/reconciliation preview, exception correction, final release confirmation and posted result                                                                      |
| 8. Central Warehouse and receiving          | Central Warehouse view                                                                                                                                            | Approved requisition detail, pick/reservation/handover and PO receiving queue with line-level receipt and confirmation                                                                                    |
| 9. Requisitions and transfers               | Transfer form/queue                                                                                                                                               | Branch requisition create/submit, Management approval, warehouse pick/dispatch, destination receipt and completion result                                                                                 |
| 10. Returns and adjustments                 | Returns & Adjustments view                                                                                                                                        | Separate return, damage and stock-adjustment forms; approval and resulting ledger/balance where applicable                                                                                                |
| 11. Finance, procurement and daily closing  | Finance/Procurement overview; Daily Closing form                                                                                                                  | Supplier/customer records, PO create/approval, AP payment, AR invoice/receipt, expenses, ledger and completed daily-close result                                                                          |
| 12. Reports and audit                       | Auditor reports and audit views                                                                                                                                   | Report filters/output/print preview, audit record detail, export/print result                                                                                                                             |
| 13. Users, settings and system control      | Owner navigation; Users & Organization form controls (sample fixtures); System Health action panel (sample UI); System Control status-card layout (sample values) | Saved active-user result; existing-account role/location reassignment and saved result; live Quick and Deep Health results; verified live System Control status and schema fingerprint; Business Calendar |
| 14. HR and payroll                          | HR & People Operations landing layout (sample fixtures)                                                                                                           | Actual lifecycle-gate state; payroll statutory-rule approval state; SOP resource list and safe preview; no fictional active employee or payroll screens                                                   |
| 15. Screenshot checklist                    | No application screenshot expected                                                                                                                                | Reference/checklist chapter; retain diagrams and capture instructions                                                                                                                                     |
| 16. Troubleshooting                         | None                                                                                                                                                              | Sanitized real error/result screens for supported failures such as duplicate import, POS pending/timeout, stock shortage, transfer in transit and blocked HR/payroll action                               |
| 17. GitBook Ask and MCP maintainer workflow | No ERP screenshot expected                                                                                                                                        | Live Ask test and cited answer after an authenticated Ask tool is actually exposed to the agent session                                                                                                   |

## Device connection and training-media evidence

The staff training sequence now introduces the supported desktop/laptop, phone/tablet and Retail POS workstation access paths before the operational modules. The diagram and training animation are instructional; they do not prove a live device registration, a peripheral hardware integration or a production login. Capture a genuine 16.2.18 screen only when the corresponding action exists and can be shown safely.

### Priority evidence still outstanding

1. **User creation outcome:** the create-user form with role and location controls is now illustrated. Still capture the submitted training user’s active-user row and the separate existing-account role/location reassignment with its saved result.
2. **System Health results:** the manual now shows the action panel with RUN QUICK HEALTH CHECK and RUN DEEP INTEGRITY AUDIT. Both returned result views and timestamps remain required; do not treat the action panel as a PASS.
3. **System Control proof:** the manual now shows the status-card layout. Its 29/29, 16/16, 4/4, 49/49 and LIVE values are sample fixtures only. Capture the actual live 16.2.18 state tied to the installed release and exact schema fingerprint, with secrets masked.
4. **HR lifecycle:** the manual now shows an HR landing UI illustration. Capture the real lifecycle approval/pending/blocked state only if the installed app exposes it. Do not infer activation from this sample dashboard.
5. **Payroll:** capture a genuine statutory-rule approval/readiness gate only where that screen exists. No approved effective-dated Ethiopian PAYE/pension rule set is established by the current evidence; do not create fictional payroll calculations, payslips, deductions or posting screens.
6. **SOP library:** capture the actual SOP document list and safe preview, keeping DRAFT\_NOT\_APPROVED visible and private employee information hidden.

### Image quality and placement standard

* Use original screenshots from the exact 16.2.18 frontend; do not use AI-generated screen mockups or later-version screens.
* Synthetic QA fixture values may demonstrate the genuine interface, but captions must say so. They are not evidence of a live production transaction.
* Preserve original resolution. Target at least 1440 pixels wide for desktop captures, or native device resolution for mobile. Avoid small thumbnails where text becomes unreadable.
* Keep the page title, relevant menu, fields, action button and resulting status visible. Mask personal and sensitive data before upload.
* Place each screenshot immediately after the exact step it illustrates. One module overview image is not enough to illustrate every step.
* Do not mark a module visually complete until the required action states and successful result are shown and reviewed in GitBook Preview on desktop and mobile.

## Capture rules

* Capture the actual Stockland ERP screen with the 16.2.18 build visible where practical.
* Use an approved account and non-sensitive records appropriate for the capture session.
* Hide customer and employee personal data, email addresses, phone numbers, credentials, payroll amounts and private HR documents.
* Never capture passwords, nonces, API keys, private vault paths or browser developer tools.
* Keep the menu and page title visible.
* Retain the unmodified original capture securely before adding numbered callouts.
* Capture desktop and phone views where the workflow is commonly performed on both.
* Record capture provenance: release, module, screen, role used, capture date and whether it is production or another approved environment. Never label a non-production image as production evidence.

## GitBook Ask and agent-assisted chapter verification

GitBook Ask is a documentation cross-check. It can explain what the published manual currently says; it is not evidence that an ERP screen, permission, workflow or release behavior exists.

### Keep the two MCP connections distinct

* **Authenticated GitBook management MCP:** `https://mcp.gitbook.com/mcp`. This connection is used by an authorized agent to read and edit GitBook content and open change requests. Published pages must be changed through a change request.
* **Published Stockland manual MCP:** `https://stockland-one-member-plc.gitbook.io/docs-user-manual-16.2.18/~gitbook/mcp`. This is the site's documentation endpoint for agents to search and read the published manual. GitBook documents an `askQuestion` tool on the published-docs MCP when the site's AI Assistant is enabled.

The management connection does not automatically expose the published site's `askQuestion` tool in every AI session. Before claiming that Ask was used, confirm that `askQuestion` is actually available to the connected agent and that it returned an answer. Do not assume an OAuth scope name or claim an Ask check was completed without that tool-level confirmation.

### Required workflow for each manual chapter

1. **Identify the exact release source.** Confirm the 16.2.18 installer/artifact and the relevant source implementation. Trace the screen, action, validation and role/permission checks for the workflow.
2. **Ask the published manual.** Use the published-site Ask tool to request the documented navigation, prerequisites, role restrictions, actions, warnings and expected result for that chapter. Ask it to cite the manual pages it used.
3. **Compare documentation with implementation.** Check each claimed screen and action against the exact 16.2.18 source and server-side role permissions. The installer and its source remain authoritative for ERP behavior; an Ask answer is not implementation evidence.
4. **Resolve discrepancies before illustrating.** Correct the draft wording in the change request when it conflicts with the verified release.
5. **Capture the real screen.** Use the authenticated 16.2.18 application and an approved account. Preserve the original image and privacy-review it.
6. **Insert the image at the exact step.** Upload the actual asset into the change request, place it beside the relevant instruction, and add descriptive alternative text and a caption naming the page, screen and release.
7. **Verify the rendered draft.** Preview desktop and mobile rendering. Confirm that images load, are readable, have useful alt text and are beside the correct instruction. Re-check links and captions.
8. **Submit for review.** Keep the request in review until the content and evidence have been reviewed. Do not merge or publish before required approval.

### Suggested Ask prompt

> Review the published Stockland Ethiopia ERP User Manual 16.2.18 for \[MODULE / WORKFLOW]. Summarize the documented navigation, prerequisites, responsible roles, permitted actions, validations, approval or release boundary, warnings and expected result. Cite the exact manual pages or sections. Identify missing steps, ambiguity and contradictions within the manual. Do not infer application behavior that the manual does not state. Actual ERP behavior must be checked against the exact 16.2.18 installer/source and role permissions.

### Evidence priority

1. Exact 16.2.18 installer/artifact and corresponding implementation and permission checks for ERP behavior.
2. A genuine screen captured from the installed 16.2.18 application for what the UI visibly shows.
3. The published GitBook manual and its Ask answer for what the documentation currently says.

A screenshot proves only what was visible in that captured session. It does not by itself prove server-side authorization, posting, release or accounting behavior. A Mermaid diagram is supplementary and is not a captured application screen.

## Required capture set

| ID  | Real screen to capture                                            | Manual page                |
| --- | ----------------------------------------------------------------- | -------------------------- |
| S01 | ERP sign-in and successful landing                                | Getting started            |
| S02 | Desktop navigation expanded                                       | Command Center             |
| S03 | Mobile navigation open                                            | Getting started            |
| S04 | Command launcher search result                                    | Command Center             |
| S05 | Product Master search results with SKU/size/color                 | Product Master             |
| S06 | Product Master new/edit form                                      | Product Master             |
| S07 | Product Master bulk import preview                                | Product Master             |
| S08 | Inventory Control search and location stock                       | Inventory and counts       |
| S09 | Daily Inventory report filters and movement columns               | Inventory and counts       |
| S10 | Physical Count workbook preview                                   | Inventory and counts       |
| S11 | Count batch variance and approval controls                        | Inventory and counts       |
| S12 | Retail POS sale header and empty cart                             | Retail POS                 |
| S13 | Retail POS populated cart and totals                              | Retail POS                 |
| S14 | POS successful posting/reference                                  | Retail POS                 |
| S15 | Daily POS manual upload form                                      | Daily POS Import           |
| S16 | Daily POS validation/reconciliation preview                       | Daily POS Import           |
| S17 | Daily POS staged queue and Branch Manager review                  | Daily POS Import           |
| S18 | Daily POS release confirmation/result                             | Daily POS Import           |
| S19 | Central Warehouse approved requisition queue                      | Central Warehouse          |
| S20 | PO receiving queue and line receipt                               | Central Warehouse          |
| S21 | Requisition stages                                                | Requisitions and transfers |
| S22 | Transfer stages and destination receipt                           | Requisitions and transfers |
| S23 | Returns & Adjustments form                                        | Returns and adjustments    |
| S24 | Finance & Accounting overview                                     | Finance and procurement    |
| S25 | Daily Closing form and result                                     | Finance and procurement    |
| S26 | Reports filter and printed report preview                         | Reports and audit          |
| S27 | Audit Log record detail                                           | Reports and audit          |
| S28 | Users & Organization create-user form and role selection          | Users and settings         |
| S29 | Branch/warehouse primary and additional location assignment       | Users and settings         |
| S30 | Existing user role/location edit or reassignment and saved result | Users and settings         |
| S31 | System Health — RUN QUICK HEALTH CHECK result                     | Users and settings         |
| S32 | System Health — RUN DEEP INTEGRITY AUDIT result                   | Users and settings         |
| S33 | System Control Core/Finance/HR/full-schema live status            | Users and settings         |
| S34 | HR & Payroll landing and lifecycle readiness/blocked state        | HR and payroll             |
| S35 | Payroll statutory-rule readiness/approval gate                    | HR and payroll             |
| S36 | SOP library document list and safe document preview               | HR and payroll             |

## Insert screenshots in GitBook

1. Open the relevant page in the active change request.
2. Upload the approved original screenshot file (or a separate annotated copy) to the change request.
3. Add the image immediately after the step it illustrates.
4. Write useful alternative text and a caption naming the manual page, screen and 16.2.18 release.
5. Check that the image is legible and no personal or confidential information is exposed.
6. Check desktop and mobile rendering in Preview.
7. Submit the updated page for review before publishing.

Do not mark this checklist complete until the required action and outcome screenshots are present in the manual, traced to the installed 16.2.18 application, and privacy-reviewed.

## GitBook agent reference

For details on GitBook's agent-facing documentation capabilities, see [How we made GitBook easier for AI agents to use](https://www.gitbook.com/blog/gitbook-agent-experience) and [GitBook MCP](https://www.gitbook.com/features/mcp).
