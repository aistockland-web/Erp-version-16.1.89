# CUTTING\_MARKER\_AI\_ASSISTANT

**Feature class:** Optional assisted decision-support feature.\
**Domain:** Pattern Engineering → Fabric Planning → Marker/Lay → Cutting.\
**Release posture:** Specification only; no model, solver, CAD parser or cutting-machine connector is installed or run in this workspace.

## 1. Product purpose

Provide a conversational pre-cut assistant that helps a pattern master/cutting planner understand pattern/fabric constraints, prepare marker alternatives, estimate fabric use and waste, render a clear drawing and explain why a candidate layout is safe or unsafe.

This feature is intended for the **planning desk before material is laid or cut**. It is not an autonomous cutter controller, machine interface or automatic approval process.

### User question examples

* “For this order and size ratio, compare marker plans using the available 58-inch usable width and the accepted roll lots.”
* “Why does this sleeve piece fail the grainline rule?”
* “Show candidate placements that avoid the mapped defect zone on roll R-104.”
* “Compare the marker length and estimated material yield for the two approved lay quantities.”
* “Which pieces constrain rotation because the fabric has nap or a directional print?”
* “Explain why the proposed plan has a lower yield than the previous approved marker.”

## 2. Functional boundary: three separate engines

### A. Conversation and intent layer

* Understand natural-language requests and ask for missing order/style/size/fabric details.
* Retrieve only the authenticated, tenant-scoped records the user is authorized to inspect.
* Explain rule failures and candidate metrics using the deterministic engine's outputs.
* Prepare a typed proposal request and summarize the returned result.
* Never invent geometry, dimensions, quantities, material availability, quality status or approval.

### B. Deterministic pattern/marker engine

* Parse/validate canonical pattern geometry and grade nest.
* Resolve garment order size/colour quantities into piece demand using the approved pattern piece count.
* Check usable fabric width/roll length, selvage and edge clearance, grainline/orientation, nap, print direction, stripe/plaid matching, shade lot, defect map, splice/join policy, fabric relaxation and cutter limitations.
* Generate valid candidate placements via a deterministic nesting/optimization method.
* Calculate marker length, covered area, usable fabric area, material utilization/yield, waste, splice count, number of lays and expected cut bundle quantities.
* Run an independent validator after optimization. A solver result failing validation is rejected, not rounded into a pass.

### C. Drawing/preview layer

* Render the exact validated placements as vector SVG/PDF with piece code, size, rotation, coordinates, grainline, defects, usable fabric boundary, selvage, marker length and legend.
* Store source pattern hashes, roll/lot hashes, solver version and configuration hash with each preview.
* A drawing must be reproducible from the same input snapshot and deterministic solver release. The preview is not itself a cutting command.

## 3. Inputs and dependencies

### Order and product inputs

* Tenant, site/factory, buyer order, production order/lay request and due date.
* Style ID and exact approved style/tech-pack revision.
* Exact pattern-set revision and grade-rule/grade-nest revision.
* Size/colour ratio and target garment quantity; piece count per garment and paired/mirrored cut quantities.
* Cut plan/lay quantity, ply count, spread method, splice policy and allowed remnant policy.

### Pattern inputs

* Canonical piece geometries with closed cut boundaries, internal lines, grainline, seam/cut allowance, notch/drill, mirror/pair, rotation and orientation rules.
* Geometry unit, scale, CAD source dialect, parser/version, file hash and validation report.
* Material/layer assigned to each piece and approved replacement or interlining relation.

### Fabric/roll inputs

* Accepted usable roll/lot, supplier lot, roll number, shade group and quality status.
* Nominal and measured usable width; selvage/edge exclusion; length available; material weight/GSM; nap/direction; print repeat; stripe/plaid repeat and matching tolerance.
* Shrinkage/relaxation allowance, splice/join policy, lay length/width, defects and defect polygons/intervals mapped to the roll coordinate system.
* Warehouse/bin, ownership, reservation/issue state and measurement provenance.

### Machine/process constraints

* Cutter/table usable width and length, minimum edge clearance, maximum lay height/ply, vacuum/holding constraints, permitted cut speed where configured, blade/knife kerf allowance, drill/notch tooling restrictions and machine profile version.
* Human-readable constraints are not treated as engineering values until entered/approved in the machine profile.

## 4. Marker planning controls

### Hard constraints

A candidate must fail if violated.

1. Every required piece quantity is present exactly once per required cut set (including mirrored pairs where appropriate).
2. Pattern geometry is closed, valid, scaled and approved for this style/size/tech-pack revision.
3. No placement extends beyond the usable fabric boundary, selvage exclusion or permitted lay length.
4. No two cut boundaries overlap; required clearance/kerf is respected.
5. Grainline direction and rotation conform to the approved fabric/style rules.
6. Nap, one-way print, pile direction, stretch direction, stripe/plaid and matching constraints are honored.
7. Defect regions are avoided according to approved defect class/severity and customer concession policy.
8. Shade continuity is respected: a lay cannot mix incompatible shade groups unless explicitly approved and segregated.
9. All allowed edge joins/splices obey the tenant's approved cutting policy.
10. Ply count and lay dimensions fit the configured cutting table and process capacity.
11. Any output passed to a CAD/cutter adapter has a supported file version/dialect and has been independently validated.

### Soft objectives

Rank feasible candidates.

* Minimize marker length/material consumption/waste.
* Maximize material utilization subject to correct directionality and matching.
* Minimize number of lays, splices, roll changeovers and remnant losses.
* Respect order ratio and minimize short-cut/re-cut risk.
* Reduce setup/handling effort, balancing the marker yield against line/factory schedule.

The objective weights are explicit, versioned and visible; no hidden AI ranking changes a material release decision.

## 5. Conversational tool contract

The AI may call only these typed operations:

* `get_cutting_context(tenant_id, production_order_id, lay_request_id)` — resolves permitted input snapshot.
* `validate_pattern_set(pattern_revision_id, grade_revision_id)` — returns geometry validation only.
* `build_marker_request(lay_request_id, roll_ids, constraint_profile_id)` — creates immutable proposal inputs.
* `generate_marker_candidates(marker_request_id, solver_profile_id, max_candidates)` — returns candidate IDs and deterministic solver metadata.
* `validate_marker_candidate(candidate_id)` — independently checks hard constraints.
* `render_marker_preview(candidate_id, format)` — SVG/PDF preview of already validated candidate.
* `compare_marker_candidates(candidate_ids)` — metrics and trade-offs.
* `request_pattern_review(candidate_id, reason)` — workflow request; does not approve.

It cannot call `approve_marker`, `release_cut_ticket`, `issue_material`, `post_inventory`, `start_machine`, `ship_carton`, or any general-purpose database/action tool.

## 6. Output contract

Each marker candidate stores:

* `marker_candidate_id`, `tenant_id`, `site_id`, `production_order_id`, `lay_request_id`.
* `style_id`, `tech_pack_revision_id`, `pattern_set_revision_id`, `grade_revision_id`, `order_breakdown_hash`.
* `roll_lot_ids`, `roll_snapshot_hashes`, `defect_map_hashes`, `machine_profile_id/version`.
* `solver_name/version`, `solver_config_hash`, `random_seed` if applicable, generation timestamp and deterministic replay metadata.
* Candidate geometry file, preview file, each SHA-256, output format/dialect, validation report and validation ruleset version.
* `marker_length_mm`, `usable_width_mm`, `fabric_area_mm2`, `piece_area_mm2`, `utilization_percent`, `waste_percent`, `lay_count`, `plies`, `garments_per_lay`, `estimated_roll_consumption`, `splice_count`.
* Warnings, hard failures, accepted human trade-offs, status and approval evidence.

Calculation definitions must be explicit. For example, `utilization_percent` must define which boundary/area is in the numerator and denominator; do not compare results from different formulas as if identical.

## 7. Mandatory human gates

`DRAFT_REQUEST → GEOMETRY_VALIDATED → CANDIDATES_GENERATED → CANDIDATE_VALIDATED → PATTERN_MASTER_REVIEW → CUTTING_MANAGER_APPROVAL → RELEASED_TO_CUT → CUT_IN_PROGRESS → CUT_RECONCILED`

* Pattern Master confirms pattern integrity, grainline, grade and style revision.
* Cutting Manager confirms selected roll/lot, shade group, lay/ply, marker preview, material reservation/issue and operational readiness.
* Material controller confirms the roll is physically available, QC accepted and issued to the exact lay.
* The release references the exact pattern/grade/roll/marker hashes. If any input changes, the approval is invalidated and a new candidate/approval is required.
* Rejected or infeasible candidates are retained as evidence but cannot release a cut ticket.
* No AI chat message, image, SVG preview or optimization score can act as approval.

## 8. AI privacy and security

* Tenant-configured, opt-in provider only; AI provider credentials are held outside source and database logs.
* No salary/HR data, personal employee files, supplier banking information or customer confidential files are sent to an AI model.
* Only the minimal authorized pattern/fabric/order context is used. File uploads are scanned, parsed in a sandbox and constrained by type, bytes, object count, coordinate range and processing time.
* Treat PDF/DXF/SVG/ZIP metadata and embedded text as untrusted data, never as model instructions. Prevent path traversal, entity-expansion, decompression bombs, script-bearing SVG, external resource fetches and macro execution.
* Do not send CAD source to a third-party model unless the tenant explicitly accepts the provider, processing region, retention and IP terms.
* Prompt injection cannot grant additional tools or change release rules. All tool inputs are schema-validated and scope-checked by the ERP service.
* The assistant's memory is tenant- and user-scoped; no cross-company pattern or buyer data can be retrieved.

## 9. Human-readable failure messages

* `PATTERN_GEOMETRY_INVALID`: Piece `SLEEVE_L` has a self-intersecting cut boundary. Pattern Master must correct/re-import the source revision.
* `PATTERN_UNIT_AMBIGUOUS`: Input geometry has no validated unit declaration. Select the source unit and rerun conversion.
* `GRAINLINE_ROTATION_BLOCKED`: Piece `FRONT_PANEL` cannot rotate 90 degrees because the approved fabric direction disallows it.
* `ROLL_WIDTH_INSUFFICIENT`: Usable width after selvage allowance is below the minimum feasible placement width.
* `SHADE_GROUP_MISMATCH`: Selected rolls contain incompatible shade groups.
* `DEFECT_REGION_INTERSECTION`: Candidate placement intersects an approved defect region.
* `ORDER_RATIO_NOT_SATISFIED`: Candidate output does not meet size/colour/ply quantities.
* `MACHINE_PROFILE_LIMIT`: Lay exceeds table or ply constraints.
* `CANDIDATE_STALE`: Pattern, grade, roll, order, or constraint snapshot changed after generation; regenerate.

## 10. Evaluation and release tests

* Deterministic replay of identical input snapshot produces identical canonical output/hash for a fixed solver build/configuration.
* Rotation/nap/print/stripe/plaid/grainline constraint fixtures.
* Concave and curved piece geometry, piece overlap, boundary containment, clearance, notch/mark containment, invalid polygons and ambiguous units.
* Defect avoidance, shade-lot segregation, roll width/length, splice policy, ply and cutter limits.
* Order size-ratio completeness, paired/mirrored quantities and shortages.
* Tampered source/preview hash is rejected; stale snapshot invalidates approval.
* AI assistant is evaluated for tool-call accuracy, refusal of forbidden commands, data leakage, prompt injection, explanation grounded only in solver output and helpful fallback when AI is unavailable.
* Human review time, fabric yield and re-cut rates are measured as business outcomes; AI quality is not declared from sample screenshots alone.

## 11. Standards / CAD interchange caution

ISO 8559 is relevant to body measurement and clothing sizing methodology, not a complete sewing-pattern CAD file definition. DXF-AAMA/ASTM is a candidate pattern interchange family mentioned by industry education sources, but Stockland must confirm the licensed/current dialect specification and test it against the customer's actual CAD software before promising import/export compatibility. The initial contract should be a vendor-neutral canonical geometry model with adapters only for verified file formats.
