# 5. Inventory and physical counts

## Read current stock

{% stepper %}
{% step %}
### Open Inventory Control

Choose Inventory → Inventory Control.
{% endstep %}
{% step %}
### Search the item

Search by SKU, product, color, size, barcode or GTIN.

<figure><img src="assets/screens/S08-inventory-control.png" alt="Inventory Control search results with physical, reserved and available stock"><figcaption><p>Inventory Control search with physical, reserved and available stock. Role shown: Branch Manager. Stockland ERP 16.2.18 front end with training sample data.</p></figcaption></figure>
{% endstep %}
{% step %}
### Read the location balance

Review physical, reserved and available quantities for each authorized location. This is a read-only view; do not type or manually override balances here.
{% endstep %}
{% endstepper %}

## Daily Inventory report

Daily Inventory is a read-only ledger-derived report. Choose the business date and authorized location scope, then review:

1. Opening stock.
2. Receipts and transfers.
3. Sales.
4. Closing stock.
5. Reserved quantity.
6. Available quantity.
7. Reorder or shortfall indicators.

If the report cannot load, stop and report the error. Do not estimate stock from an old screen or spreadsheet.

<figure><img src="assets/screens/S09-daily-inventory.png" alt="Daily Inventory report scope and opening, sales and closing figures"><figcaption><p>Daily Inventory report scope with opening, sales, closing and available figures. Role shown: Branch Manager. Stockland ERP 16.2.18 front end with training sample data.</p></figcaption></figure>

## Reorder Alerts

Use Reorder Alerts to identify items needing replenishment. Check branch/warehouse, SKU and availability before creating a requisition. An alert is a signal for review, not a stock transaction.

## Figure 1 — Physical count approval path

```mermaid
flowchart TD
    A["Download current Stockland count template"] --> B["Count actual items at one location"]
    B --> C["Record one row per SKU / size / color"]
    C --> D["Upload workbook to Stock Count & Approval"]
    D --> E["PREVIEW WORKBOOK"]
    E --> F{"Validation correct?"}
    F -->|No| G["Correct workbook and preview again"]
    G --> D
    F -->|Yes| H["Commit validated count batch"]
    H --> I["Compare System Qty, Counted Qty and Variance"]
    I --> J{"Authorized approver decision"}
    J -->|Reject| K["REJECT COUNT with reason"]
    J -->|Approve| L["APPROVE & POST FINAL INVENTORY"]
    L --> M["Verify updated stock and audit reference"]
```

*Figure 1: previewing or committing a batch is not the same as posting final inventory. Only the authorized approval action completes the inventory correction.*

## Physical count workbook

The ERP provides a Stockland Physical Count Excel template. Use the current template rather than inventing column headings. The workbook requires a PHYSICAL COUNT worksheet and, at minimum, count date, location, product identity (SKU or Brand + Product Name + Size + Color) and Quantity Found.

{% stepper %}
{% step %}
### Download the template

Use the template link provided in the ERP and save a working copy with the location and count date in its filename.
{% endstep %}
{% step %}
### Count physical stock

Count actual stock at the selected branch or warehouse. Record one row for each variant. Do not combine sizes or colors.
{% endstep %}
{% step %}
### Complete identity and quantity

Enter the SKU where known. If using descriptive identity, complete brand, product name, size and color. Enter the actual quantity found.
{% endstep %}
{% step %}
### Preview the workbook

Open Inventory → Stock Count & Approval, choose the XLSX workbook and select PREVIEW WORKBOOK. Review validation messages and detected location/date.

<figure><img src="assets/screens/S10-count-preview.png" alt="Physical count workbook preview with variance summary"><figcaption><p>Physical count workbook preview before submission. Role shown: Branch Manager. Stockland ERP 16.2.18 front end with training sample data.</p></figcaption></figure>
{% endstep %}
{% step %}
### Commit the validated count

Use the commit action only after the preview is correct and the authorized user confirms the count. A preview does not post inventory.
{% endstep %}
{% step %}
### Review the batch

Open the created batch. Compare System Qty, Counted Qty and Variance for each SKU.
{% endstep %}
{% step %}
### Approver decision

The authorized approver can select APPROVE & POST FINAL INVENTORY or REJECT COUNT. Rejection requires a reason. Approval updates final inventory through the controlled workflow.

<figure><img src="assets/screens/S11-count-approval.png" alt="Count batch detail with approve and reject buttons"><figcaption><p>Count batch variance with the approval and reject controls. Role shown: Owner. Stockland ERP 16.2.18 front end with training sample data.</p></figcaption></figure>
{% endstep %}
{% endstepper %}

## Stock adjustment request

A manual variance request is not an immediate stock edit. Select the authorized location, live Product Master SKU, counted quantity and reason, then submit. It must pass the separate approval workflow before stock changes.

{% hint style="danger" %}
Never correct a difference by editing a database row, changing an old transaction or entering a fabricated receipt. Use the controlled count or adjustment workflow.
{% endhint %}
