# 2. Roles and locations

Access depends on the signed-in ERP profile, capabilities and assigned location scope. The server checks authorization again when a record is changed.

## Role guide

| ERP role | Main responsibility |
|---|---|
| Owner | Company-wide authority, release/schema approvals and high-level control |
| Management | Review and approval, procurement and finance decisions within authority |
| Branch Manager | Assigned-branch operations, demand review, Daily POS release, branch receiving and close |
| Sales Employee | Customer sales, SKU selection, POS work and Daily POS upload/staging where assigned |
| Warehouse Manager | Central Warehouse fulfillment, reservation, picking and PO-linked receiving |
| Warehouse Employee | Picking/ready work after reservation |
| Auditor | Review records, trace activity and report exceptions |

Your account may see only a subset of modules. This is intentional.

## Stockland locations

The 16.2.18 Stockland installation is seeded for one company and these locations:

- Central Warehouse
- Goro Branch
- Gerji Branch
- Mexico Branch
- Megenagna Branch

These are Stockland-specific locations, not generic defaults for another business.

## Location scope

- Branch staff normally work within their assigned branch.
- Warehouse staff work within their assigned warehouse responsibilities.
- Owner, Management and Auditor may have broader read access according to capabilities.
- Multi-location users may see a location selector.
- If the wrong location appears, do not use another employee's location as a workaround. Request an administrator correction.

## Maker/checker

Some actions are split between different people. A Sales Employee can stage an external POS report, but the Branch Manager must review and release it. A user cannot grant themselves authority by changing browser values or using another screen.
