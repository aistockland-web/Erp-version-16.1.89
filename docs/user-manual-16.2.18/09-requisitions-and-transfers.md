# 9. Requisitions and transfers

## Create a requisition

{% stepper %}
{% step %}
### Open Requisitions

Choose Warehouse → Requisitions.
{% endstep %}
{% step %}
### Select branch and item

Confirm the authorized branch, select the live Product Master SKU and enter the requested quantity and required details.
{% endstep %}
{% step %}
### Submit

Submit the requisition and note its reference and Requested status.
{% endstep %}
{% step %}
### Track stages

The normal workflow is Requested → Reviewed → Approved → Reserved → Picked → Received. Available actions depend on role and current stage.
{% endstep %}
{% endstepper %}

## Review and approval

Management/Owner reviews demand according to authority. Check SKU, quantity, branch and available stock before approval. If requested quantity exceeds availability, follow the application's shortage/availability result; do not approve an impossible quantity or create a false stock balance.

## Central Warehouse fulfillment

- Warehouse Manager: reserve approved stock and assign pickup, or use the permitted direct-pick action.
- Warehouse Employee: pick/mark ready after reservation.
- Branch recipient: confirm receipt when goods arrive.
- The requisition status and audit trail are evidence of progress.

## Create and complete a transfer

{% stepper %}
{% step %}
### Open Transfers

Choose Warehouse → Transfers.
{% endstep %}
{% step %}
### Create a transfer request

Select From location, To branch, SKU and quantity, then select CREATE TRANSFER REQUEST.
{% endstep %}
{% step %}
### Review and approve

The authorized reviewer checks source, destination, SKU and quantity. Approval is separate from reservation and dispatch.
{% endstep %}
{% step %}
### Reserve and pick

The authorized warehouse user reserves stock and records picking. Picked means warehouse preparation/dispatch has been recorded; it is not branch receipt.
{% endstep %}
{% step %}
### Mark in transit

The authorized operator records that the shipment has left the source location. The transfer becomes In Transit and awaits destination receipt.
{% endstep %}
{% step %}
### Receive at destination

The authorized destination user checks actual received quantity and records receipt. Resolve variance through the displayed controlled workflow.
{% endstep %}
{% step %}
### Confirm completion

Confirm the transfer shows Completed. Verify source/destination stock and review any variance record.
{% endstep %}
{% endstepper %}

## Transfer status sequence

**Requested → Approved → Reserved → Picked → In Transit → Received → Completed**

Notifications are generated for key transfer stages. Trace the existing transfer reference rather than creating a duplicate request.
