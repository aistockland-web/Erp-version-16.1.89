# 6. Retail POS

Retail POS is the direct Stockland ERP checkout. It posts a complete cart as one controlled transaction.

## Before starting

- Posting is limited to authorized Sales Employees and Branch Managers.
- Confirm the correct branch and business date.
- Select an active stockable Product Master variant.
- Check stock availability.
- Do not use Daily POS Import for a sale already posted directly in ERP.

## Create a retail sale

{% stepper %}
{% step %}
### Open Retail POS

Choose Sales & POS → Retail POS.
{% endstep %}
{% step %}
### Confirm the sale header

Check Business date, Branch, VAT mode and optional External receipt no. If only one branch is assigned, the branch may be read-only.
{% endstep %}
{% step %}
### Add the first item

Use Add Product Master SKU to search by SKU or product name. Select the correct active stockable variant, then click + Add item to cart.
{% endstep %}
{% step %}
### Add remaining items

Repeat the SKU search and add action. Verify product name, SKU, size, color and unit in the cart.
{% endstep %}
{% step %}
### Check quantities and totals

Review each quantity, master price, any permitted override and line total preview. Check Items, Gross total preview, VAT preview and Net sales preview.
{% endstep %}
{% step %}
### Post the complete cart

When all lines are correct, select POST COMPLETE CART once. Wait for the server response and sale reference.
{% endstep %}
{% step %}
### Verify the result

Confirm the sale in authorized sales history and check the stock movement in Inventory. If the request times out, check the sale record before retrying.
{% endstep %}
{% endstepper %}

## VAT mode

The POS offers VAT included and Without VAT. The preview is not the final authority: Product Master price, VAT configuration, stock availability and ledger validation are checked by the server when posting.

## Insufficient stock

If available stock is insufficient, posting is blocked. Do not reduce the stock figure manually or choose another SKU without checking. Ask the Branch Manager to review stock or raise a requisition.

## Cart retry and duplicate protection

If the cart shows RETRY SAME CART, use it only to retry the same pending submission. Do not clear the cart or create a second sale while the result is uncertain. Verify the existing transaction first. The POS uses an idempotent cart submission pattern.

## Returns

Use Returns & Adjustments for a genuine return or correction. Do not reverse a completed sale by creating a negative sale or changing a posted ledger record.
