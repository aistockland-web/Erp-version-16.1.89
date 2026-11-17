# INDUSTRIAL\_ENGINEERING\_AND\_CAPACITY

**Status:** target operating and data specification for garment factories; not an implemented line-balancing or time-study engine.

## 1. Industrial engineering master data

* Factory, building, floor, sewing line, cell, work centre and workstation.
* Machine class and machine asset: lockstitch, overlock, coverstitch, flatlock, buttonhole, button attach, bartack, feed-off-arm, double-needle, blind stitch, press, fusing, embroidery, printing or approved special-process equipment.
* Operation library: operation code, garment/operation family, description, skill level, machine class, attachment/guide, stitch type, seam class, input/output, quality checkpoints and sequence constraints.
* Method bulletin: operation breakdown, approved method, workstation layout, handling direction, bundle size, motion/ergonomic risk and effective version.
* SMV/SAM standard, source study, observer, date, rating method, allowances and approval. Never accept a manually typed SMV without a source or authorized override reason.
* Work calendar, working minutes, break policy, overtime policy reference, shift, planned maintenance, machine availability and holiday schedule.
* Skill matrix and worker eligibility comes from HR if connected; MES stores only a minimum worker reference/skill result and must not own HR personal or payroll records.

## 2. Operation bulletin and method study

An operation bulletin is pinned to a style/routing revision. It captures:

* Operation sequence, operation code and machine/work area.
* Work method steps and critical method controls.
* SMV/SAM, unit of measure, study source and effective date.
* Machine/attachment/needle/thread/guide/pressure/temperature setting references where relevant.
* Input bundle condition and accepted quantity.
* Output good, rework, reject and scrap categories.
* Quality checkpoint and sampling rule.
* Skill/authorization requirement, PPE/safety note and ergonomic risk assessment.
* Predecessor/successor operation and allowed bypass/optional designation.

Method changes create a new routing/bulletin revision. An active production order retains the approved routing version unless a controlled engineering change/rework instruction authorizes a revision migration.

## 3. Time study and SMV governance

Time studies use observed element times and a documented rating/allowance method. Store raw observations separately from the approved standard. A study record includes observer, style/operation/machine/operator context, date, cycle count, abnormal-cycle exclusions and reasons, rating factor, personal/fatigue/delay allowance, calculated standard and reviewer approval.

The ERP must not invent a universal allowance or assume that a sample is statistically valid. Company industrial-engineering policy and qualified review define study count, exclusion policy, rating and allowances. Store the underlying data and formula version so an SMV can be recalculated and audited.

## 4. Capacity planning formulas

The following are explicit planning definitions. Tenant/company can configure assumptions, and each run stores those assumptions and its formula version.

* **Available line minutes** = scheduled working minutes − breaks − planned downtime − planned maintenance − approved non-production allocation.
* **Theoretical line capacity (pieces)** = available line minutes × number of eligible operators × planned efficiency ÷ approved garment SMV. The exact numerator/denominator and efficiency unit must be consistent; do not multiply by manpower twice if the SMV basis is already team-based.
* **Operation capacity** = available workstation minutes × eligible parallel workstations ÷ operation SMV, adjusted by configured efficiency and skill/machine constraints.
* **Bottleneck capacity** = the minimum feasible capacity across mandatory routing operations after considering predecessor/successor calendars and subcontract stages.
* **Planned output** = the lesser of order demand, bottleneck capacity and the quantity supported by material/quality constraints.

{% hint style="info" %}
Every chart must label actual vs theoretical, time basis, SMV version, planned efficiency, manpower basis, downtime treatment and site timezone. A productivity metric cannot silently be reused as a payroll amount.
{% endhint %}

## 5. Line balancing and pitch

Line balancing assigns work elements to workstations/teams subject to operation precedence, machine class, skill and cycle-time constraints. The optimizer may propose work allocations, but a production/IE manager approves the line plan. Metrics include takt/cycle time, station load, bottleneck, work-in-process, waiting, rework, output and line efficiency, with explicit formula versions.

A rebalanced line plan is a new plan revision. The change record lists moved operations, new workstation/machine assignment, training/skill requirement, target changes, cutover time and open WIP impact. Existing hour logs and operator events remain immutable.

## 6. Hourly production and WIP

An hourly board is sourced from immutable operation events, not hand-edited totals. Every row has production order/style revision, line, operation, hour window, target, good output, rejected output, rework, downtime, WIP in/out and source event IDs. Unique event IDs prevent duplicate scans. Hourly target carry-forward is an explicit rule and cannot create output or payroll credit.

WIP at operation n is reconciled from accepted input, accepted output, open bundles, hold, rework, scrap and approved corrections. Downstream output cannot exceed predecessor accepted input plus an explicit authorized adjustment. A line may stop for quality, material, machine, safety or stale tech-pack reasons; resume requires an authorized release event.

## 7. Scheduling and exception management

Schedulers must consider:

* Buyer order delivery window and order priorities.
* Approved style routing and SMV.
* Fabric/trim arrival, QC acceptance, roll/shade availability and MRP result.
* Factory calendar, machine capacity, line skill matrix and maintenance.
* Cutting table capacity, lay size, marker availability and cut-to-sew synchronization.
* Special process vendor lead time and in-transit WIP.
* Quality inspection capacity, rework and finishing/packing capacity.

Output is a proposed schedule with conflict/exception reasons, not a silent change to a confirmed buyer date. Replanning records old/new schedules, cause, owner and buyer communication requirement.

## 8. AI role in industrial engineering

AI may summarize bottleneck evidence, explain capacity conflicts, propose alternative line assignments or draft a work-study checklist. It cannot invent SMV, alter an approved efficiency assumption, assign a worker without HR authorization, modify wages, approve overtime, create a production output event or publish a line schedule. All numerical candidates must be calculated by deterministic services and carry formula/version/input hashes.

## 9. Required acceptance cases

* Duplicate hourly scan is idempotent.
* Concurrent output on one bundle does not double-complete operation quantity.
* WIP transfer rejects more than accepted predecessor output.
* Machine/skill incompatibility blocks an assignment.
* Downtime reduces available capacity once and cannot be counted twice.
* Rebalanced routing revision does not rewrite historic production events.
* Synthetic staffing data cannot reach the live HR database or payroll.
* Capacity, planned efficiency, target and actual formulas reconcile against a transparent independent oracle.
