# 9. Requisitions and transfers

## Watch the walkthrough

![Animated walkthrough: Branch Manager opens a requisition to read its stage, then an authorized destination user works the transfer queue to record Received and Completed. Recorded from the 16.2.18 front end with training sample data.](.gitbook/assets/w07-requisitions-transfers.gif)

_The Branch Manager opens a requisition, reads the six-stage lifecycle, then an authorized destination user confirms destination receipt in Transfers. Values shown are training sample data._

## Figure 1 — Requisition lifecycle

```mermaid
stateDiagram-v2
    [*] --> Requested
    Requested --> Reviewed
    Reviewed --> Approved
    Approved --> Reserved
    Reserved --> Picked
    Picked --> Received
    Received --> Completed
    Reviewed --> Rejected
    Approved --> Rejected
    Rejected --> [*]
    Completed --> [*]
```

_Figure 1 illustrates the normal branch-demand lifecycle. A transfer has its own location-to-location controls and dispatch/receipt evidence; a Picked status is not the same as destination receipt._

## Figure 2 — Transfer dispatch and receipt

```mermaid
flowchart TD
    A["Create transfer request"] --> B["Authorize source, destination, SKU and quantity"]
    B --> C["Reserve stock"]
    C --> D["Pick and prepare dispatch"]
    D --> E["Mark In Transit"]
    E --> F["Destination checks actual received quantity"]
    F --> G{"Received quantity reconciles?"}
    G -->|No| H["Record and resolve variance through controlled workflow"]
    G -->|Yes| I["Confirm Received / Completed"]
    H --> I
    I --> J["Verify source and destination balances"]
```

_Figure 2: dispatch is not receipt. Destination staff must confirm the actual goods received before the transfer is treated as complete._

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

![Branch Manager requisition detail showing the six-stage SOP, current stage and live availability. Sample data from the 16.2.18 front end.](.gitbook/assets/S21-requisition-stages.png)

_Branch Manager requisition-stage view with synthetic training data. It illustrates the lifecycle and available stage controls, not a submitted requisition outcome._

The normal workflow is Requested → Reviewed → Approved → Reserved → Picked → Received. Available actions depend on role and current stage.
{% endstep %}
{% endstepper %}

## Review and approval

Management/Owner reviews demand according to authority. Check SKU, quantity, branch and available stock before approval. If requested quantity exceeds availability, follow the application's shortage/availability result; do not approve an impossible quantity or create a false stock balance.

## Central Warehouse fulfillment

* Warehouse Manager: reserve approved stock and assign pickup, or use the permitted direct-pick action.
* Warehouse Employee: pick/mark ready after reservation.
* Branch recipient: confirm receipt when goods arrive.
* The requisition status and audit trail are evidence of progress.

## Create and complete a transfer

![Warehouse Manager — Transfers form and queue. Exact 16.2.18 frontend; the displayed source and destination are example selections and the queue is fixture-only, not a live transfer.](.gitbook/assets/warehouse-manager-transfers-16-2-18.jpg)

_Figure: the authorized warehouse role selects the source and destination, SKU and quantity to create a transfer request. Continue through approval, reservation, picking, dispatch and destination receipt; dispatch alone does not complete the transfer._

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
