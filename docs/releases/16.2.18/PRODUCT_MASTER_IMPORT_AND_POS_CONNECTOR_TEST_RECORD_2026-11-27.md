# Product Master import and POS connector — test record (27 Nov 2026)

New suite `scripts/release/e2e/s23_product_master_import_e2e.php`, wired into `run_all.sh` (now 24 suites). It builds real XLSX files and uses real multipart HTTP with Application Passwords on a disposable site.

Covered: Sales denial; valid STYLE + two VARIANT preview; duplicate-file guard; preview writes nothing; commit; stored barcodes/prices; no physical stock created; replay creates no duplicates; negative price, duplicate barcode and parent-less VARIANT rejected; unknown job refused.

Mutation proof: reverting the PR #155 prepare()-argument fix makes 6 checks fail; reverting the closure-variable fix makes 5 fail. With both fixes: 15/15 pass.

Full sequence on a freshly seeded MySQL 8 site: 24 suites, 652 PASS, 0 FAIL, including POS suites s12 (contract), s19 (device connector, 41/41) and s22 (real upload → release → sale, stock ledger, balanced GL, replay guard).

## Open decision for the Owner
The import template says "Primary SKU must begin with STK-", but the server does not enforce it: a SIMPLE product with SKU `BAD-…` previews and commits. Not changed here because it alters import rules (legacy SKUs may exist). Options: enforce `STK-` (reject), or reword the template text.
