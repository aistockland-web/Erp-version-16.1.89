# DESIGN\_PATTERN\_SAMPLE\_WORKFLOW

## 1. Purpose and ownership

This domain converts the brand/buyer design intent into a technically validated, versioned garment definition.

* The designer owns appearance and design intent.
* The technical designer/pattern master owns construction geometry.
* The grader owns grade rules and size nests.
* The sample room records physical validation.
* The buyer/brand quality owner accepts the production baseline.

{% hint style="warning" %}
No AI image, CAD conversion, pattern import, measurement table, or sample status can be considered approved only because a file uploaded successfully.
{% endhint %}

## 2. Design Studio records

### Design brief

Required attributes:

* `tenant_id`, `brand_id`, `season_id`, `collection_id`, `division_id`, `category_id`, `style_id` (or requested style creation), `buyer_id` when applicable.
* Name/description, garment type, intended fit, silhouette, occasion/use, gender/age population segment where configured, market, climate/use conditions, target price, target cost, target margin, and planned launch date.
* Design references, artwork license/source metadata, original creator, created date, brief owner, reviewer, and approval reason.

### Design assets

* Moodboard image
* Hand sketch
* AI concept image (draft only)
* CAD flat
* Detail drawing
* Print/embroidery artwork
* Logo/placement file
* Colour board
* Construction callout

Each asset stores:

* File type
* Size
* Source
* Checksum
* Uploader
* Parser/scanner result
* Access scope
* Revision
* Approval state
* Retention classification

{% hint style="info" %}
Separate the concept image (appearance) from the technical flat (construction) and CAD pattern (geometry).
{% endhint %}

### Technical flat contents

* Front/back/side view
* Silhouette
* Closure
* Neckline
* Collar
* Cuffs
* Sleeve
* Pockets
* Placket
* Yoke
* Waistband
* Fly
* Hem
* Seam/stitch types
* Reinforcement
* Trims
* Topstitch placement
* Label/print location
* Opening/finish
* Construction annotations
* Linked POM codes

## 3. Garment taxonomy and construction library

The product must support tenant-configurable categories and reusable construction definitions. Do not hard-code one company's garment taxonomy.

### Examples of garment families

* **Tops:** T-shirt, polo, shirt, blouse, tunic, sweatshirt, hoodie, knit top.
* **Bottoms:** trouser, chino, denim, short, skirt, legging, jogger.
* **Outerwear:** jacket, coat, vest, blazer, rainwear.
* **Dresses / one-piece:** dress, jumpsuit, romper, coverall.
* **Other:** intimates, sleepwear, activewear, swimwear, workwear, uniform, safety apparel.
* Accessories and soft goods where the tenant enables them.

{% hint style="info" %}
Construction libraries are versioned recipes, not mandatory operations.
{% endhint %}

A shirt routing differs from a knit T-shirt; a denim jean may require wash/finishing; a jacket may contain lining and interlining; a printed garment may add print and curing. A style's approved routing determines which operations are required.

## 4. Pattern Engineering data model

### Pattern set

A pattern set is bound to:

* One style revision
* One tech-pack revision
* A measurement specification
* A grade-rule version

It includes:

* Source CAD file(s)
* Converted canonical geometry
* Piece list
* Size coverage
* Revision
* Author
* Checker
* Test status
* SHA-256 values

### Pattern piece

Each piece stores:

* Stable piece code and name
* Panel/garment part
* Source pattern revision
* Material layer
* Piece quantity per garment
* Size applicability
* Units and scale
* Origin and coordinate reference
* Closed cut-boundary geometry
* Seam line and cut line
* Edge classification
* Seam allowance map
* Fold/selvage edge
* Grainline vector/axis
* Orientation/rotation rules
* Notches
* Drill holes
* Punch marks
* Darts
* Pleats
* Internal lines
* Pocket/zip placement
* Matching marks
* Balance points
* Labels
* Annotations
* Mirror/pair grouping
* Left/right/symmetric flag
* Face/lining/interlining relationship
* Piece pairing
* Sewing match edges
* Geometry file hash
* Canonical geometry hash
* CAD source dialect/version
* Parser version
* Conversion report

### Geometry model

Use physical units, with canonical millimetres in the internal model and explicit units in import/export files. Do not interpret unitless coordinates by guess.

Canonical geometry must represent:

* Outer cut boundary as a closed polygon/path.
* Internal paths/marks as typed line/curve elements.
* Grainline and allowed orientation as vectors/angles.
* Seam/cut allowance, notches, drills, fold lines, and match points as typed geometry.
* Piece properties: fabric/material, layer, size, count, mirror, and cutting face.

### Geometry checks

{% stepper %}
{% step %}
## Validate file parser inputs

The file parser validates extension, declared format, declared units, scale, size nesting, piece names, and limits.
{% endstep %}

{% step %}
## Validate cut boundaries

Every cut boundary is closed, finite, non-self-intersecting, and has a positive area.
{% endstep %}

{% step %}
## Validate internal geometry

Internal geometry is within the approved piece boundary unless explicitly allowed.
{% endstep %}

{% step %}
## Validate grainline and orientation

Grainline is present where required and rotation is constrained by material, print, and nap rules.
{% endstep %}

{% step %}
## Validate notches and drill marks

Notches and drill marks are within tolerance and do not fall outside the cut boundary.
{% endstep %}

{% step %}
## Validate seam allowance

Seam allowance exists for edges that require it and has approved value by edge/construction.
{% endstep %}

{% step %}
## Validate mirror and pair pieces

Mirror/pair pieces are consistent; mirrored pieces are not unintentionally duplicated.
{% endstep %}

{% step %}
## Validate grade nests

Grade nests preserve valid topology and piece/edge correspondence for all required sizes.
{% endstep %}

{% step %}
## Report CAD conversion limitations

CAD conversion reports any unsupported entity, unit, or curve simplification; fail closed on ambiguous geometry.
{% endstep %}
{% endstepper %}

## 5. Measurement specification and grading

### Point-of-measure record

A POM record stores:

* POM code (stable)
* Label
* Garment area
* Diagram pointer
* Method
* Measurement path
* Flat/circumference/vertical/diagonal/distance type
* Unit
* Base size
* Target value
* Positive tolerance
* Negative tolerance
* Rounding precision
* Owner
* Effective revision

{% hint style="info" %}
Tolerance may vary by buyer, product category, and market; do not store one universal tolerance for all styles.
{% endhint %}

### Size scale and grade rules

* Tenant size scale: alpha/numeric/waist-inseam/body-category/custom, active size codes, order, and label code.
* Base size and base pattern are selected explicitly.
* Grade rules are a table of POM increments per size step, including split-direction and independent front/back or left/right increments where needed.
* Store grade method, reference body/garment measurements, intended ease, grade rule author, approver, and version.
* Each graded pattern set stores the generated geometry for each size; do not rely only on a text grade increment after production begins.

### Grade validation

* Every required POM must exist on every required size.
* The declared base-size value must equal the approved base-size measurement.
* Grade increment values must have valid units, limits, sign, and direction.
* Shape/topology checks compare each size piece to the base piece; no missing panels, collapsed segments, self-intersection, or invalid seam correspondence.
* Grade nest visual preview supports measurement delta and edge comparison.
* The sample room records actual measurements and tolerance result for each sample size; failed POMs create correction tasks linked to the exact pattern revision.

### Standards registry

Store:

* Standard reference
* Issuing body
* Edition/year
* Territory/adoption
* Licensed copy/source
* Applicable POM codes
* Approval date
* Customer/product scope

{% hint style="warning" %}
ISO 8559-1 concerns anthropometric definitions for body measurement; it is not a buyer-specific finished-garment tolerance table.
{% endhint %}

The current ISO page states ISO 8559-1:2017 was reviewed and confirmed in 2026. Ethiopia's ES ISO 8559-1:2021 edition is also published by Ethiopian Standards Institute. The ISO catalogue lists ISO 8559-2:2025 and ISO 8559-3:2018.

Check current official catalogue/licensing and local adoptions before claiming conformance.

## 6. Seam, stitch and construction libraries

A reusable construction operation/component stores:

* Code
* Title
* Diagram
* Seam class
* Stitch class
* SPI/stitch density target
* Machine type/attachment
* Thread
* Needle
* Seam allowance
* Operation minutes basis
* Quality checkpoint
* Applicability
* Version

It should support typical operations such as:

* Lockstitch, chainstitch, overlock, coverstitch, flatlock, and bartack where appropriate.
* Plain seam, French seam, flat-felled seam, bound seam, taped seam, elastic insertion, topstitch, edge stitch, blind hem, waistband attachment, collar/cuff/placket construction, and zipper insertion.
* Reinforcement, pocket setting, label attach, buttonhole/button attach, snap/rivet, bar-tack, and special process steps.

{% hint style="info" %}
These libraries support technical design, routing, operator guidance, and quality inspections; they do not replace process engineering review or customer workmanship standards.
{% endhint %}

## 7. Sample room management

### Sample request

Stores:

* Buyer/style
* Reason
* Sample category
* Size
* Colour
* Due date
* Destination
* Quantity
* Approved tech-pack/pattern/BOM revision
* Allocated fabric/trim
* Required sample approvals

### Sample job stages

```
REQUESTED → SPEC_REVIEW → PATTERN_READY → MATERIAL_READY → CUT → SEWING → FINISHING → QA → FIT_REVIEW → BUYER_REVIEW → APPROVED / REWORK / REJECTED → HANDOVER → CLOSED
```

Conditional stages are defined per sample type and garment category. Skipped stages require reason and authorized role.

### Fit session

* Sample size
* Body/fit model reference
* Fitting context
* POM measurement results
* Fit comments
* Issue locations
* Severity
* Photos
* Assigned owner
* Correction due date
* Result

{% hint style="warning" %}
Correction may affect pattern, measurement spec, construction, material, or styling. Every correction creates a change record and new pattern/tech-pack revision.
{% endhint %}

Retest records which old issue is closed and what new evidence proves the correction.

### Sample closure rules

* Approval is independent from the creator where maker/checker applies.
* A `REJECTED` sample cannot be marked `APPROVED` without a new linked sample/revision.
* Buyer feedback and final approval have timestamp, actor, and scope.
* PPS (pre-production sample) references the exact production-intent materials, labels, construction, pattern, grade, BOM, routing, care, and pack specification.

## 8. AI and image generation in design

AI may draft a concept sketch, propose colourway variations, or write an initial design brief.

{% hint style="warning" %}
AI-generated design content remains draft content until human conversion and review are complete.
{% endhint %}

AI content must:

* Store an explicit `AI_DRAFT` asset classification and model/prompt/provenance metadata.
* Avoid inventing brand assets or implying licensed imagery ownership.
* Never generate a production pattern solely from a raster sketch and claim it is cut-ready.
* Require human conversion/review to technical flat, CAD geometry, measurements, and BOM.
* Keep the accepted file and AI source file separately hashed.

## 9. Release gates to downstream domains

A style may enter buyer costing/production planning only when:

* Design/technical flat is approved.
* Measurement specification and grade revision are approved.
* Pattern set is validated for every order size and revision-pinned.
* BOM materials and approved alternates are effective.
* Operation routing and mandatory process steps are validated.
* Sample/PPS approvals exist when required by buyer/product policy.
* Quality plan, care/label, and packaging requirements are attached.
* All linked files pass integrity/security checks and are accessible to the authorized roles.

## 10. Implementation and test requirements

* Pattern file parsers are adapters; canonical geometry is independent of vendor CAD format.
* Never transform CAD files silently; save original source bytes and a conversion/validation report.
* Do not run CAD parsers as unbounded PHP requests. Use a controlled, resource-limited processing path in the commercial architecture or a manual import/preview fallback on shared hosting.

### Tests

Tests cover:

* Unit confusion (inch/mm)
* Malformed/oversized files
* Self-intersecting boundaries
* Missing grainline
* Invalid notch
* Invalid grade
* Duplicate pieces
* Mirrored pair mismatch
* Invalid seam allowance
* File tampering
* Unsupported CAD entities
* Change impact to open orders
