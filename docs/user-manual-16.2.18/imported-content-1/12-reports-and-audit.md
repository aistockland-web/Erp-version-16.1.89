# 12. Reports and audit

## Watch the walkthrough

![Animated walkthrough: choose a report type, period and location, run the report, print it, then trace an event in the Audit Log. Recorded from the 16.2.18 front end with training sample data.](.gitbook/assets/w10-reports-audit.gif)

_Follow the highlighted sequence: choose the report type, date period and authorized location scope, run the report, print or export the selected result, then open Audit Log to trace the actor, record, event and timestamp. All displayed values are training samples._

## Build a report

### Screenshot — Auditor reports view in 16.2.18

![Auditor reports view — exact 16.2.18 frontend, illustrative sample fixture data](.gitbook/assets/auditor-reports.jpg)

_Exact 16.2.18 frontend capture under the Auditor capability profile. Sample fixture data only; this does not represent live financial or branch reporting data._

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

![Auditor report result after RUN REPORT, showing the selected type, period and location scope. Sample data from the 16.2.18 front end.](.gitbook/assets/S26-report-run.png)

_Auditor report result with synthetic training data. It illustrates the selected filters and output, not live financial or branch reporting data._

Select RUN REPORT and wait for the live result.
{% endstep %}

{% step %}
### Print or export

![Auditor printable report preview from PRINT / SAVE PDF. Sample data from the 16.2.18 front end.](.gitbook/assets/S26b-report-print.png)

_Auditor print-preview with synthetic training data. It illustrates the output format only._

Use PRINT / SAVE PDF for a printable report or DOWNLOAD SELECTED CSV for the selected report data, when enabled.
{% endstep %}
{% endstepper %}

## Figure 1 — Report and audit review

### Screenshot — Auditor audit view in 16.2.18

![Auditor audit view — exact 16.2.18 frontend, illustrative sample fixture data](.gitbook/assets/auditor-audit.jpg)

_Exact 16.2.18 frontend capture under the Auditor capability profile. Sample fixture data only; this is not an audit export or a real production audit event._

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

_Figure 1: reports are read-only analysis. Audit Log is the trace of recorded events; neither is an edit path for correcting transactions._

## Daily Inventory versus Reports

Daily Inventory is a read-only ledger-derived stock movement view. Reports & Analytics is a report builder for performance, availability, transfers, counts and returns. Neither screen is a stock-entry function.

## Audit Log

Use Management → Audit Log when authorized. Inspect the relevant user, record and event. Use transaction reference and timestamp to trace what happened. An audit record is evidence, not an edit control.

## Reconciliation checklist

* Date and location match the intended business period.
* Report type matches the question being investigated.
* User has the correct authorized scope.
* Source documents and transaction references are retained.
* Differences are resolved through the proper workflow, not by editing history.
