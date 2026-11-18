# 12. Reports and audit

## Build a report

{% stepper %}
{% step %}
### Open Reports & Analytics

Choose Reports & Analytics from navigation.
{% endstep %}
{% step %}
### Select report type

Available report types include Branch performance, Sales performance, Product / SKU performance, Inventory availability, Transfer status, Transfer variance, Physical-count variance and Returns & adjustments summary. Critical reorder is Owner-only.
{% endstep %}
{% step %}
### Select the period

Choose Today, Yesterday, Last 7 days, This month or Custom range.
{% endstep %}
{% step %}
### Select location scope

Choose All authorized locations or a specific authorized branch/warehouse. The server enforces your access scope.
{% endstep %}
{% step %}
### Run the report

Select RUN REPORT and wait for the live result.

<figure><img src="assets/screens/S26-report-run.png" alt="Reports and Analytics filters with a generated report"><figcaption><p>Report filters and a generated report. Role shown: Owner. Stockland ERP 16.2.18 front end with training sample data.</p></figcaption></figure>
{% endstep %}
{% step %}
### Print or export

Use PRINT / SAVE PDF for a printable report or DOWNLOAD SELECTED CSV for the selected report data, when enabled.

<figure><img src="assets/screens/S26b-report-print.png" alt="Report in print preview layout"><figcaption><p>Print layout used by PRINT / SAVE PDF. Role shown: Owner. Stockland ERP 16.2.18 front end with training sample data.</p></figcaption></figure>
{% endstep %}
{% endstepper %}

## Figure 1 — Report and audit review

```mermaid
flowchart TD
    A["Open Reports & Analytics"] --> B["Choose report type"]
    B --> C["Set date range"]
    C --> D["Choose authorized location scope"]
    D --> E["RUN REPORT"]
    E --> F["Review result and filters"]
    F --> G{"Need a copy?"}
    G -->|Print / PDF| H["PRINT / SAVE PDF"]
    G -->|Data file| I["DOWNLOAD SELECTED CSV"]
    H --> J["Retain report with date and scope"]
    I --> J
    J --> K["For an event trace, open Audit Log"]
    K --> L["Match actor, record, event and timestamp"]
```

*Figure 1: reports are read-only analysis. Audit Log is the trace of recorded events; neither is an edit path for correcting transactions.*

## Daily Inventory versus Reports

Daily Inventory is a read-only ledger-derived stock movement view. Reports & Analytics is a report builder for performance, availability, transfers, counts and returns. Neither screen is a stock-entry function.

## Audit Log

Use Management → Audit Log when authorized. Inspect the relevant user, record and event. Use transaction reference and timestamp to trace what happened. An audit record is evidence, not an edit control.

<figure><img src="assets/screens/S27-audit-log.png" alt="Audit Log table with user, branch, action, reference and hash"><figcaption><p>Audit Log: time, user, branch, action, reference and hash. Role shown: Owner. Stockland ERP 16.2.18 front end with training sample data.</p></figcaption></figure>

## Reconciliation checklist

- Date and location match the intended business period.
- Report type matches the question being investigated.
- User has the correct authorized scope.
- Source documents and transaction references are retained.
- Differences are resolved through the proper workflow, not by editing history.
