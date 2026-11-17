# 8. Central Warehouse and receiving

Central Warehouse manages approved branch demand, reservation, picking and pickup readiness. External supplier goods enter inventory through the controlled purchase-order receiving workflow.

## Warehouse requisition queue

{% stepper %}
{% step %}
### Open Central Warehouse

Choose Warehouse → Central Warehouse.
{% endstep %}
{% step %}
### Review approved requisitions

The queue lists approved requisitions waiting for warehouse action, including branch, approved units, status and pickup date.
{% endstep %}
{% step %}
### Reserve and assign pickup

A Warehouse Manager can select RESERVE / ASSIGN for an approved requisition and choose the branch pickup date. This reserves approved quantity and records the pickup appointment.
{% endstep %}
{% step %}
### Pick the goods

The authorized warehouse role selects PICK / READY after the work has been reserved. A Warehouse Manager may also use PICK NOW for an approved requisition when that action is available.
{% endstep %}
{% step %}
### Hand over for branch receipt

After picking, the queue shows the items are ready for branch receipt. The receiving branch confirms receipt in the transfer/requisition workflow.
{% endstep %}
{% endstepper %}

## Supplier goods receiving

{% hint style="warning" %}
16.2.18 uses PO-linked goods receiving. New supplier stock cannot be entered as a free-form manual receipt.
{% endhint %}

{% stepper %}
{% step %}
### Confirm the approved purchase order

Management enters the purchase order and the Owner completes the separate stock-purchase approval. Warehouse receives only against the approved PO.
{% endstep %}
{% step %}
### Open the receiving queue

In Central Warehouse, review the purchase-order action queue and open the approved PO awaiting receiving.
{% endstep %}
{% step %}
### Compare delivery with the PO

Check each original PO line, SKU, unit and actual delivered quantity. Record actual quantities against those same lines.
{% endstep %}
{% step %}
### Confirm receipt

Submit the PO-linked receipt using the available receiving action. A short balance remains open on the same PO; do not create a second manual receipt to hide a shortage.
{% endstep %}
{% step %}
### Verify inventory

Check Central Warehouse stock and the related purchase-order/receipt record. Investigate variance before further transfer or sale.
{% endstep %}
{% endstepper %}

Legacy manual receipt records are read-only exceptions and cannot post stock in this release.
