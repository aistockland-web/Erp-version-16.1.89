# Product Master import and POS connector — test record (27 Nov 2026)

New suite `scripts/release/e2e/s23_product_master_import_e2e.php`, wired into `run_all.sh` (now 24 suites). It builds real XLSX files and uses real multipart HTTP with Application Passwords on a disposable site.

Covered: Sales denial; valid STYLE + two VARIANT preview; duplicate-file guard; preview writes nothing; commit; stored barcodes/prices; no physical stock created; replay creates no duplicates; negative price, duplicate barcode and parent-less VARIANT rejected; unknown job refused.

Mutation proof: reverting the PR #155 prepare()-argument fix makes 6 checks fail; reverting the closure-variable fix makes 5 fail. With both fixes: 15/15 pass.

Full sequence on a freshly seeded MySQL 8 site: 24 suites, 652 PASS, 0 FAIL, including POS suites s12 (contract), s19 (device connector, 41/41) and s22 (real upload → release → sale, stock ledger, balanced GL, replay guard).

## SKU scheme (resolved 28 Nov 2026)
The Owner confirmed SKUs may start with anything and the scheme may change. The `STK-` prefix is no longer required or suggested anywhere. The format is defined once in `Stockland_Product_Service::valid_sku()` (letter/number first; letters, numbers, `.` `_` `-`; max 64) and used by the product form, REST create, SKU replacement, Product Master import and the Excel template. A future scheme change is one constant (or the `stockland_sku_pattern` filter).
