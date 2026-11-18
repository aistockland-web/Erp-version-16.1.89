# 9. Requisitions and transfers

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

*Figure 1 illustrates the normal branch-demand lifecycle. A transfer has its own location-to-location controls and dispatch/receipt evidence; a Picked status is not the same as destination receipt.*

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

*Figure 2: dispatch is not receipt. Destination staff must confirm the actual goods received before the transfer is treated as complete.*

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

<figure><img src="assets/screens/S21-requisition-stages.png" alt="Requisition detail with the six stages"><figcaption><p>Requisition detail with the six stages and approval routing. Role shown: Branch Manager. Stockland ERP 16.2.18 front end with training sample data.</p></figcaption></figure>
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

<figure><img src="assets/screens/S22-transfer-queue.png" alt="Transfer queue with status and next action buttons"><figcaption><p>Transfer queue with statuses and the next-action buttons. Role shown: Branch Manager. Stockland ERP 16.2.18 front end with training sample data.</p></figcaption></figure>
{% endstep %}
{% step %}
### Confirm completion

Confirm the transfer shows Completed. Verify source/destination stock and review any variance record.
{% endstep %}
{% endstepper %}

## Transfer status sequence

**Requested → Approved → Reserved → Picked → In Transit → Received → Completed**

Notifications are generated for key transfer stages. Trace the existing transfer reference rather than creating a duplicate request.
