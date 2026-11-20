# Critical imports in depth

Three workbooks change live data: the **Product Master bulk update**, the **Daily POS report** and the **physical count workbook**. Each follows the same rule: _preview first, fix every error, then commit or release as a separate action._ A preview never changes the ledger or stock.

## Watch the walkthrough

![Animated walkthrough: Product Master bulk update with an error preview and a corrected commit, then Daily POS Import with a blocking error, a refused duplicate file and the Branch Manager release. Recorded from the 16.2.18 front end with training sample data.](.gitbook/assets/w13-critical-imports.gif)

_Training sample data. The errors, job numbers and totals shown are examples, not live Stockland records._

## A. Product Master bulk update (Management / Owner)

{% stepper %}
{% step %}
### Open the bulk update

Choose Product Master, then BULK MASTER UPDATE. Use the approved Stockland Master Import template; do not change its headers.
{% endstep %}

{% step %}
### Choose the workbook and preview

Select the file and PREVIEW PRODUCT MASTER. The ERP maps your headers to Product Master fields and lists each row as INSERT, UPDATE or ERROR.
{% endstep %}

{% step %}
### Fix every error in the workbook

When the Errors count is above zero the page shows _Correct validation errors before commit_ and **no commit button**. Typical row errors: _Duplicate SKU in workbook_ and _Duplicate Barcode in workbook (also row N)_. Correct the file and preview again.
{% endstep %}

{% step %}
### Commit

When the preview passes, the page states that changes are staged and not live. Select COMMIT PRODUCT MASTER once and wait for the confirmation. The import changes the catalogue only; it does not change inventory balances.
{% endstep %}
{% endstepper %}

## B. Daily POS Import (Sales Employee stages, Branch Manager releases)

{% stepper %}
{% step %}
### Stage the original report

Choose the original XLSX or CSV exported from the branch POS and select VALIDATE & STAGE. The branch comes from your account and the business date from the report.
{% endstep %}

{% step %}
### Read blocking errors

A blocking error stops release. Example: _Unknown POS code … Management must add a POS-code → live-SKU mapping before this row can post._ Ask Management for the mapping, then upload the corrected or same report again.
{% endstep %}

{% step %}
### Do not upload the same file twice

An identical file for the same branch and date is refused with a message naming the existing job (_already staged/imported as job #N. Reuse that job instead of uploading it again_). Open that job in the staged queue.
{% endstep %}

{% step %}
### Branch Manager reviews and releases

Open the staged job, check date, rows, mapping and totals, correct valid exceptions, then select RELEASE TO ERP once. Confirm the job shows POSTED. Never release the same report twice.
{% endstep %}
{% endstepper %}

## C. Physical count workbook

The count workbook is previewed by the counter and approved by an authorised approver. See [Inventory and physical counts](05-inventory-and-counts.md) for the full steps. A preview never posts stock; only the approver's Approve & post does.

## Quick reference

| Symptom                                        | Meaning                                     | What to do                              |
| ---------------------------------------------- | ------------------------------------------- | --------------------------------------- |
| No commit button on the Product Master preview | Errors remain                               | Fix the rows listed, preview again      |
| Unknown POS code                               | The POS article is not mapped to a live SKU | Ask Management to add the mapping       |
| File already staged or imported                | Identical file for this branch and date     | Open the existing job; do not re-upload |
| Job staged but not posted                      | Release has not happened                    | Branch Manager reviews and releases     |
