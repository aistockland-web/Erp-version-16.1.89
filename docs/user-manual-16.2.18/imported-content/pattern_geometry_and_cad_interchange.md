# PATTERN\_GEOMETRY\_AND\_CAD\_INTERCHANGE

**Purpose:** Define the canonical engineering representation between Design/PLM, Pattern Engineering, Grading, Marker Planning, and Cutting. This is a target contract, not a CAD parser or production pattern engine.

## 1. Layered garment geometry model

Keep the following layers distinct:

1. **Body measurement profile** — Anthropometric body dimensions associated with a defined population/market/size system and source edition.
2. **Finished-garment measurement specification** — POM, finished dimensions, ease intent, tolerance, method, reference image/diagram, and size applicability.
3. **Pattern construction geometry** — Pattern outline, internal construction lines, seam allowance, darts, notches, drill points, fold lines, grainline, labels, and piece pairing.
4. **Graded pattern set** — Base-size piece geometry plus explicit grade rules and approved graded sizes.
5. **Cutting geometry** — Selected size/colour/order quantities, marker placement transforms, roll width/usable boundary, defects, nap/print direction, and lay parameters.
6. **Machine output** — Only an approved adapter export for a named cutter/CAD/CAM system with exact dialect, units, scale, tool version, and acceptance evidence.

{% hint style="warning" %}
Never treat a PNG/JPEG sketch as production pattern geometry. Raster/AI drawings are references for a qualified pattern maker to digitize and validate.
{% endhint %}

## 2. Coordinate and units contract

* Canonical internal geometry uses millimetres (mm), fixed-point decimal coordinates, an explicit scale, and a declared origin/axis orientation.
* Original files retain original declared units and bytes. Unit conversion is an immutable transformation record with from/to units, factor, rounding rule, tool/version, and source/output SHA-256.
* Pattern geometry is stored in a style-local coordinate frame; marker geometry is stored in roll/lay coordinates. Transformations link the two explicitly.
* Geometry records declare curve type/control points, closed/open status, winding, hole nesting, tolerance/precision, and any self-intersection or invalid topology issue.
* Boundary, grainline, notch, and drill positions must be in the correct coordinate frame. Do not silently flip axes or scale to fit a fabric width.

## 3. Canonical pattern piece contents

A pattern piece record includes:

* Piece code/name and garment component role.
* Style ID, style revision, pattern-set revision, size/grade revision.
* Material/layer assignment and consumption factor.
* Piece count per garment, pair/mirror relationship, left/right identity.
* Cut boundary and internal line collections.
* Seam allowance by edge/segment and allowance revision.
* Grainline vector and allowed rotation range.
* Nap, stretch, one-way print, stripe/plaid, and matching constraints.
* Notches: type, point/edge reference, and tolerance.
* Drill points and fold references.
* Label annotations/marking, orientation, and face/upside-down rules.
* Source CAD format/dialect, original source-file SHA-256, parser/version, and conversion report.

{% hint style="danger" %}
Geometry validation must fail on an open cut boundary, self-intersection, invalid hole nesting, degenerate segment, unknown units, missing grainline where mandatory, notch outside edge tolerance, seam allowance discontinuity, or unapproved revision.
{% endhint %}

## 4. Grading model

Grade rules are explicit deltas by size and POM/point/vertex. A rule has:

* A base size.
* Grade size order.
* Horizontal/vertical increments.
* Sign/direction.
* Applicable piece/point.
* Method.

The graded geometry is generated deterministically from the approved base pattern and approved rule revision.

### Acceptance

* Size codes are unique and ordered per the size-scale master.
* Every required size has a grade result.
* Base geometry equals the approved base pattern.
* No grade result has invalid topology, seam discontinuity, or out-of-bounds notch/drill.
* POM measurement checks match the finished-garment measurement table within approved tolerance.
* A size-set sample is recorded and signed before grade release where the buyer/style policy requires it.

{% hint style="warning" %}
AI may explain or propose grade adjustments but cannot silently change grade increments or release graded patterns.
{% endhint %}

## 5. Marker/lay geometry handoff

Marker engine inputs must snapshot exact:

* Pattern-set revision and graded geometry hashes.
* Order/style/size/colour breakdown and piece quantities.
* Material/roll/lot/shade identifiers and accepted QC status.
* Usable width, selvage exclusion, roll length, defect maps, and fabric-direction rules.
* Lay height/ply, cut-table dimensions, edge clearance, kerf, and splice policies.
* Cutter profile, solver version, rule set, and deterministic config hash.

The validator independently verifies:

* Quantity coverage.
* No overlap.
* Boundary clearance.
* Grain/napping/print alignment.
* Defect avoidance.
* Shade grouping.
* Table/lay capacity.

The candidate output stores:

* Placed polygons/transforms.
* Marker length.
* Used width.
* Utilization formula version.
* Waste.
* Splice count.
* Ply.
* Lays.
* Any exception.

## 6. CAD import/export architecture

The core product must not be coupled to one CAD vendor. Define adapters that implement a stable interface:

```
inspect_source(file) → detected format, units, version, byte hash and parser warnings;
import_to_canonical(file, profile) → canonical geometry, unsupported entity report and source linkage;
validate_round_trip(canonical, source_profile) → tolerance results and lost/unsupported features;
export_for_machine(marker_candidate, machine_profile) → output bytes, dialect/version, warnings and hashes.
```

Adapters are added one at a time, based on actual customer files and named target systems.

{% hint style="info" %}
DXF-AAMA/ASTM is not assumed to be the universal format; licensing and normative spec must be confirmed before using it.
{% endhint %}

{% hint style="warning" %}
Native file bytes and customer licensed reference files are never included in a public workspace or test fixtures.
{% endhint %}

## 7. Security and hostile file handling

* File extension and declared MIME type are not trusted. Inspect signature/content and apply a strict allowlist.
* Enforce per-file size, entity-count, vertex-count, recursion-depth, compression-ratio, and CPU/memory budgets.
* Parse untrusted CAD in an isolated resource-limited process/container, never inside a normal PHP REST request.
* Reject macros, scripts, external references, embedded executables, and path traversal.
* Virus/malware scan and private object storage; signed short-lived downloads; role/scope checks at every file read/export.
* Store immutable original and conversion output with SHA-256. No overwrite; replacement means new revision.

## 8. Required geometry fixture suite

Synthetic fixtures should include:

* A simple closed panel.
* Curved sleeve.
* Mirrored pair.
* Notched waistband.
* Nested pocket opening.
* Darts.
* Seam allowance by edge.
* One-way nap.
* Stripe matching.
* Plaid repeat.
* Stretch direction.
* Roll defect exclusion.
* Different units.
* Supported/unsupported CAD entity.
* Malformed file.
* Self-intersection.
* Oversized coordinate count.
* Stale pattern revision.

{% hint style="info" %}
The fixture suite proves parser/geometry rules only. It must not substitute for factory pattern-master review, sample fit approval, or actual cutter qualification.
{% endhint %}
