# 7. Daily POS Import

Daily POS Import reconciles completed sales from an external branch POS into Stockland ERP. It is separate from direct Retail POS checkout.

## Intake options in 16.2.18

1. **Manual Excel / CSV upload** — supported in the application.
2. **Automatic folder pickup** — optional Windows branch-PC agent. It submits files into the same staging workflow and does not release sales.
3. **Future POS API** — reserved for a supplier's official authenticated API. No vendor API is connected in 16.2.18.

## Role responsibilities

- Sales Employee: upload and stage source evidence for the assigned branch.
- Branch Manager: review, edit, reconcile and RELEASE TO ERP.
- A staged import does not post sales, inventory or finance.

## Manual upload and staging

{% stepper %}
{% step %}
### Open Daily POS Import

Choose Sales & POS → Daily POS Import. If the menu is hidden, your role or Daily POS capability may not be assigned.
{% endstep %}
{% step %}
### Select branch and business date

Confirm the assigned branch. Check the date detected from the source file. Enter a date only when the supported report does not provide one.
{% endstep %}
{% step %}
### Select the original report

Choose the original third-party POS Excel or CSV report (XLSX or CSV). Do not retype it into another template unless the approved source process requires it.
{% endstep %}
{% step %}
### Validate and stage

Select VALIDATE & STAGE. The ERP checks supported report dates, article/product codes, quantities, average amounts, line totals, VAT and report totals.
{% endstep %}
{% step %}
### Review the preview

Check source format, business date, row summary, field mapping, source-to-ERP reconciliation, warnings and blocking errors. Correct the original workbook if there are blocking errors.
{% endstep %}
{% step %}
### Open the staged job

Select OPEN STAGED JOB and confirm the job number and status. If the ERP reports FILE ALREADY RECEIVED — NO DUPLICATE CREATED, open the existing job instead of uploading the same file again.
{% endstep %}
{% endstepper %}

## Branch Manager review and release

{% stepper %}
{% step %}
### Open the staged queue

The Branch Manager opens the Daily POS staged queue for the assigned branch.
{% endstep %}
{% step %}
### Open and inspect the job

Review source date, branch, rows, mappings, totals and warnings.
{% endstep %}
{% step %}
### Correct exceptions

Use available edit/review controls to resolve valid source differences. Do not force a release when source totals or SKU mapping are wrong.
{% endstep %}
{% step %}
### Release to ERP

Only after review is complete, use RELEASE TO ERP. This is the step that posts approved staged sales into ERP.
{% endstep %}
{% step %}
### Verify posting

Confirm final job status and check sales, inventory and reports for the same branch/date. Do not release the same report twice.
{% endstep %}
{% endstepper %}

## Automatic folder pickup

The optional agent is a separate Windows component for an agreed branch-PC Incoming folder. It submits reports into the same validation/staging route. It stages only; it never performs the Branch Manager release decision. IT/Management must approve the branch-PC configuration.

## Common errors

- Duplicate file: open the existing job; do not upload again.
- Date missing/wrong: correct the source or enter the confirmed business date.
- Unknown article/SKU: ask Management to correct Product Master mapping.
- Source totals do not reconcile: investigate the original report; do not force release.
- Job staged but not released: Branch Manager review is still required.
