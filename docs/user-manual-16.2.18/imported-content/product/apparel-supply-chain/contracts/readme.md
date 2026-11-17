# README

All files in `schemas/` are proposed, versioned JSON Schema 2020-12 contracts. They are not database migrations, REST route implementations, or production-valid business rules. IDs use the reserved `.invalid` domain to avoid implying a public schema endpoint.

| File                                  | Domain                                            |
| ------------------------------------- | ------------------------------------------------- |
| `design-brief.schema.json`            | Design brief / season / collection intent         |
| `design-asset.schema.json`            | Original design/drawing asset metadata and hash   |
| `design-review.schema.json`           | Design and technical review decision              |
| `measurement-spec.schema.json`        | Finished-garment POM and tolerance                |
| `size-grade.schema.json`              | Grade-rule snapshot by size and POM               |
| `pattern-piece.schema.json`           | Pattern piece geometry metadata and revision      |
| `pattern-grade.schema.json`           | Pattern grade rule revision                       |
| `tech-pack.schema.json`               | Controlled tech-pack snapshot                     |
| `bom-material-line.schema.json`       | Material/BOM component and consumption            |
| `sample-fit-session.schema.json`      | Sample fit measurements, comments and disposition |
| `lay-request.schema.json`             | Lay/marker request and constraints                |
| `marker-plan.schema.json`             | Marker candidate output and metrics               |
| `cutting-defect-map.schema.json`      | Roll-coordinate fabric defect polygons/regions    |
| `material-roll-lot.schema.json`       | Supplier roll/lot/shade/QC identity               |
| `production-order.schema.json`        | Production order snapshot and state               |
| `bundle.schema.json`                  | Cut bundle identity and operation state           |
| `operation-event.schema.json`         | MES/SFC immutable operation event                 |
| `quality-inspection.schema.json`      | Inspection plan/result record                     |
| `quality-capa.schema.json`            | Corrective/preventive action                      |
| `subcontract-order.schema.json`       | External factory/process custody                  |
| `advance-shipping-notice.schema.json` | Supplier ASN/dispatch notice                      |
| `pack-spec.schema.json`               | Buyer-specific packing instructions               |
| `carton.schema.json`                  | Carton contents, label and logistic unit identity |
| `cost-sheet.schema.json`              | Style/order cost snapshot                         |
| `event-envelope.schema.json`          | Versioned cross-domain/application event envelope |

## Registry QA

Install only the QA dependency from `qa/requirements.txt`, then run:

```bash
python -m unittest qa/test_schema_registry.py -v
```

The test checks Draft 2020-12 meta-schema compliance, unique schema IDs, required properties, unknown-field rejection, and exact registry count. Business fixtures, route permission tests, DB transaction checks, and runtime execution are separate release gates.
