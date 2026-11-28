# Import-layer audit and corrections — 16.2.18 (28 Nov 2026)

Scope: Product Master import, Daily POS import, POS device connector, import mappings and the audit chain, read layer by layer (PHP REST → XLSX parser → staging tables → browser JS). Source = the certified `de7b0879…` build lineage plus the later delivery commits (superset). Every item below was fixed in PR #160 in the same pass and is covered by `s23_product_master_import_e2e.php` or the browser check.

| # | What was found | What was done |
|---|---|---|
| 1 | No way to cancel a staged import job (the code referred to a CANCELLED state that nothing set); a wrongly staged file stayed stuck. | `POST /import/jobs/{id}/cancel` + CANCEL buttons. Product Master: Management/Owner. Daily POS: assigned Branch Manager within branch scope. Released/partly released jobs refused (409). Product file key freed so the file can be re-checked. |
| 2 | Product Master preview returned only the first 200 rows, so errors further down a large workbook were invisible. | Rows with errors are listed first; the screen says "showing X of N". |
| 3 | Re-uploading an already-imported identical Product Master file showed "Preview passed" with a commit button. | Existing job is reported (`existing_job`) with a status-specific message; only status-appropriate buttons appear. |
| 4 | `mapping_save` lower-cased the import type (profiles saved under a different key than the lookup), accepted columns that do not exist, skipped the required-field contract, and was not audited. | Type whitelisted and upper-cased, mapped columns must exist, contract validated, save written to the audit chain. |
| 5 | Audit chain had no event for job cancellation, Product Master job completion or mapping saves. | `IMPORT_JOB_CANCELLED`, `PRODUCT_IMPORT_JOB_POSTED`, `IMPORT_MAPPING_SAVED` added; hash chain re-verified after the tests. |
| 6 | Excel can silently turn a long barcode/GTIN into scientific notation (e.g. 6.11E+15). | Rejected at preview and again at commit with an instruction to format the column as Text. |
| 7 | An import could re-type a STYLE that has variants and orphan them. | Blocked at preview and at commit. |
| 8 | Both browser commit loops could spin forever if the server stopped making progress. | Shared stall-guarded loop; clear error after three non-advancing replies; button shows COMPLETED / RETRY COMMIT. |
| 9 | (Earlier, PR #155) wrong `prepare()` argument order broke preview; a clobbered closure variable broke commit. | Fixed; s23 fails 6 / 5 checks if either fix is reverted. |

Not changed (needs an Owner decision): the template says SKUs must begin with `STK-` but the server does not enforce it.

Verification on the rebuilt installer (128 files, SHA-256 `401d55d7a4f96d469deb9f8a7689d16c4b3e6c4f24583eb92b99f8afb97fe470`): fresh MySQL 8 site, installed through WordPress from the ZIP, master-installer verifier PASS, 24 e2e suites 674 PASS / 0 FAIL, approval-chain browser buttons PASS (Branch Manager → Management → Warehouse Manager → Warehouse Employee → Branch Manager), Product Master screen browser check PASS, GitHub Actions green on the merged commit `aa370d8` (MySQL 8 and MariaDB × PHP 8.3/8.4, security gates, Windows agent tests).
