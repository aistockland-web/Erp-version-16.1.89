# Stockland ERP — Source Archaeology and Reconciliation (Phase 1)

Date: 2026-11-06. Scope: evidence only. No application code was edited, no schema activated, no installer issued.
Status vocabulary: PASS / FAIL / BLOCKED / NOT RUN / OWNER INPUT REQUIRED.

## 1. Repository inventory (exact, reproducible)
| Item | Result | Command |
|---|---|---|
| Remote branches | 68 | `git branch -r` after full fetch (initial clone was shallow; unshallowed) |
| Tags / GitHub Releases | 0 tags; no published Release verified | `gh api .../tags` |
| Reachable commits | 1206 | `git rev-list --all --count` |
| `main` head at inventory | `7f51474` (was `a46214f` in the 5 Nov handover) | |
| PRs | #1–#59 listed; #48 and #57 open; #39, #50, #55, #56, #58, #59 merged | `gh api pulls?state=all` |

**Handover is stale on three points:** PR #55 is merged (not open); PR #56 (module-scoped health fix, 30752e6), #58 and #59 (HR SVG removal, 476b0b6 / f60b5a4) are merged into main after the handover's base `a46214f`. PR #48 is open/draft; the handover's "closed" claim for #48 conflicts with the API. PR #57 (a Codespace branch) is open and unreviewed here.

**Main contains no plugin source tree.** Tracked: installer ZIPs 16.1.89–16.1.92, `ci/` builders/patch scripts/overlays, workflows, docs. The plugin is produced by builders (`ci/build_*`, `ci/hr_payroll_*`) applied to a release input lock. Consequence: "source of truth" = builder + locked input, not a browsable tree. Canonical source reconstruction is the first engineering task.

## 2. Supplied package verification
Input archive `…ARCHAEOLOGY_INPUTS_2026-11-05_6.zip` SHA-256 `0344a8ae8882b04aa6e7d5486cdcea2b7f5efc1c4def6bea07e517c4ce37832d`; CRC PASS; all 30 inner entries match `SHA256SUMS.txt` (30/30 OK).

| Package | Inner SHA-256 (recomputed) | Files | PHP | Header ver. | CRC |
|---|---|---|---|---|---|
| 16.1.95 protected master | `494e7772…81625` | 75 | 48 | 16.1.95 | PASS |
| 16.2.9 HR/Payroll candidate | `c9f6459c…d9c01` | 88 | 55 | 16.2.9 | PASS |
| 16.3.0 HRX candidate (artifact 13276687926) | `f4899349…72412` | 76 | 49 | 16.3.0 | PASS |

Note: the later HRX head run (#42057219158) reports a different inner SHA `1e30d52c…23406`; the supplied 16.3.0 is the earlier artifact. They are different binaries. Also a 16.1.95.1 package (user-supplied 2 Nov) differs from 16.1.95 in 16 files (assets, frontend, pwa, manifest) — a separate derivative, not the protected master.
None of the three contains a PDF or DOCX (SOP pack absent: 0/0/0).

## 3. File-level lineage
- **16.1.95 → 16.2.9:** 19 changed, 13 added. Added: CSS modules 07–12, `hr-lifecycle`, `hr-payroll-calculator`, `hr-private-vault`, `schema-contract`, `rest-hr-lifecycle`, `rest-hr-payroll-cycle`, **`rest-pos-cart`**. Changed **non-HR core**: `class-stockland-db.php` (+66/−28, rewrites the availability/reservation function), `rest-sales.php` (+10/−1: external receipt no. in replay comparison, whole-unit UOM guard, availability-lookup failure now aborts), `rest-app.php`, `rest-setup.php`, `business-calendar`, `admin`, `frontend`.
- **16.1.95 → 16.3.0:** 13 changed, 3 added, 2 removed (`payroll-statutory`, `rest-hr`). Legacy HR runtime disabled.
- **16.2.9 → 16.3.0:** 15 files removed, incl. POS cart, CSS 07–12, schema-contract; 3 HRX files added. **16.3.0 is a branch from 16.1.95, not a successor of 16.2.9.** It silently drops the POS cart and CSS 07–12.
- Therefore neither candidate is cumulative; both are selective sources.

## 4. Capability matrix (evidence class, not acceptance)
| Capability | 16.1.95 | 16.2.9 | 16.3.0 |
|---|---|---|---|
| Core ops (POS, stock, finance, 49 tables) | present | present + modified core | present |
| POS multi-line cart (REST + UI) | absent | present, unverified | absent (regressed) |
| CSS modules 07–12 | absent | present | absent (regressed) |
| HR lifecycle / payroll | legacy 4 tables | full-cycle; 37 added tables (86 total) | HRX 47 tables, legacy disabled |
| Synthetic payroll path in runtime | none | **present in shipped runtime** (calculator allows synthetic bundle) | gated by evidence rules |
| Schema contract | 49 | 86 proposed, unapproved | +47 proposed, unapproved |
| SOP/JD/contract files | 0 | 0 | 0 |
| Live WP/MariaDB run | **PASS locally (see §5)** | NOT RUN | NOT RUN |

## 5. Local live evidence already held (earlier in this engagement)
Run on real WordPress 6.8.3 + MariaDB against the 16.1.95.1 package: all seven roles, order→PLEDGER/GL, requisition/transfer/receipt, count and adjustment, Daily POS stage/post, Product Master import, concurrency, fault injection, backup/restore, deep integrity (52 checks). Nine product defects were found and fixed; the diff is preserved as `docs/audit/LIVE_RUN_FIXES_vs_16.1.95.1.patch` (SHA-256 `4a0d6d1aac87caa677c9bf7d829aaf43c811114a805eac3b598f6432b799f7df`, 9 files). Not yet reconciled against 16.2.9's own rewrite of the availability function — conflict likely, must be resolved by test, not assumption. Expenses, invoices, wholesale, direct transfer/receipt routes, PWA smoke and the upgrade path remain NOT RUN.

## 6. Gaps and OWNER INPUT REQUIRED
1. Exact installed 16.2.13 package/SHA — not recovered; no repo ref carries it. BLOCKS the upgrade-path decision.
2. 16.2.10, 16.1.98 (79 files, `1997b333…81f8b`), 16.1.101 (PR #49 head `28b9308d`), 16.3.1 (`36b215da…aa5`): no exact binary here. Repo refs to be searched for 16.1.98 and 16.1.101; 16.3.1 is a verification report only.
3. SOP files: RECOVERED in the 16.1.101 staging ZIP (see Addendum A); still need owner content approval. JD/contract forms not separately confirmed.
4. Payroll legal sign-off, compensation for the nine flagged employees, schema approval — owner/qualified reviewer.

## 7. Recommendation (canonical base)
- **Base = 16.1.95 protected master** (rollback reference, hash-verified, only package with local live evidence), rebuilt from builder + lock into a browsable source tree committed to a working branch.
- Integrate in order: (1) live-run fixes (re-tested; reconcile with 16.2.9 availability rewrite); (2) 16.2.9 POS cart + CSS 07–12 + non-HR hardening from the sales/db diffs, each as its own commit with tests; (3) HR from 16.2.9 **or** HRX only after the owner-approved ERD — remove the synthetic-payroll path from the shipped runtime; (4) 16.1.101 DDL replay fix and SOP tab as inputs; (5) module-isolated health semantics per the 6 Nov directive.
- Not recommended: wholesale 16.3.0 (drops POS/CSS), wholesale 16.2.9 (unapproved 86-table schema, synthetic path), either as "latest".
- Governing safeguard: no schema activation, no payroll rule activation and no readiness claim until the gates in the master handover §12 are evidenced against one exact final ZIP SHA.

## 8. Findings
| Sev | Finding |
|---|---|
| High | Main has no source tree; the release input chain must be rebuilt and verified |
| High | 16.2.9 ships a synthetic-payroll code path in runtime |
| High | 16.2.9 rewrites core availability logic; regression risk against protected inventory |
| High | 16.3.0 regresses POS cart and CSS 07–12 |
| Medium | Handover state stale (PR #55/#56/#58/#59, #48 status) |
| Medium | Two different "16.3.0" inner SHAs in circulation |
| Medium | SOP binaries exist only in the 16.1.101 package (PR #49 branch), not in the 16.1.95/16.2.9/16.3.0 lines |

## Addendum A — corrections after full-branch search (2026-11-06)
Searched every one of the 68 remote branches for ZIP/DOCX/PDF binaries and for version names in commit history.
1. **SOP pack recovered.** Branch `release/16.1.101-hrx-health-sop-tab` carries `releases/16.1.101-staging/STOCKLAND_ERP_16.1.101_HRX_HEALTH_FIX_STAGING.zip`, SHA-256 `2916d9ddd80f0174da8ccb8ab9b57f51050ed5aa3d65d210e6ea97283262b5d1`, CRC PASS, 103 files, header version `16.1.101-HRX-HEALTH-FIX-STAGING`. It contains `resources/erp-sops/files/` with **24 DOCX** (STK_ERP_SOP_001–024) plus `manifest.json`. All 24 open as valid OOXML ZIPs with Amharic text (about 800–1,100 Ethiopic characters each, 1.7k–3.3k characters total, so they are short). Per-file hashes: `SOP_PACK_16.1.101_SHA256SUMS.txt`. This contradicts the handover's "no DOCX/PDF anywhere" statement; the earlier 4 Nov tree audit looked at the tree of that PR, not this ZIP. Open: content accuracy, owner approval, JD/contract completeness, role-based download rules, authenticated download proof. SOP 022 covers PAYE/pension content and must not be treated as legal evidence.
2. **16.2.10 is not recoverable.** Branch `feature/stockland-erp-16-2-10-cumulative` tip equals `a46214f` (an ancestor of main); it contains no 16.2.10 code. Status: missing, as the handover says.
3. **16.2.7 / 16.2.8 / 16.2.1 / 16.1.98 are in history as commits and CI candidates**, on branches `codespace-reimagined-…`, `audit/local-consolidation-closure-2026-11-04`, `feature/hr-resource-download-handler-16-1-98`, `work/hr-replacement-source-export`, `audit/erp-wide-sop-library-2026-10-29`. They are builder/CI lineage with no checked-in installer; their exact binaries need a rebuild from the lock files or CI artifacts to be compared. NOT RUN.
4. Remaining unrecovered: 16.2.13 (installed), 16.3.1 binary, 16.1.98 binary.

## Addendum B — owner-supplied 16.2.10–16.2.13 packages (2026-11-06)
| Package | SHA-256 (recomputed) | Files | PHP | Header ver. | Archive integrity |
|---|---|---|---|---|---|
| 16.2.10 MASTER_FULL_PACK local candidate | `2ea7832efb6a42decfd53496aaaf309eb068228c54507a3b8022776860a1601f` | 88 | 55 | 16.2.10 | PASS |
| 16.2.11 MASTER_FULL_PACK local candidate | `27c66b65e999f5609d070c105517ecc491fb705a887bbdefb11851454750dee8` | 88 | 55 | 16.2.11 | PASS |
| 16.2.12 WORDPRESS_INSTALLER | `db82f3e8ec565539c0341d47d39e0382b4ba04e370464f59a742c2941d96ca75` | 112 | 80 | 16.2.12 | PASS |
| 16.2.13 WORDPRESS_INSTALLER (reported installed) | `74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13` | 113 | 80 | 16.2.13 | PASS |
All four have one `stockland-erp-app/` root. That this exact 16.2.13 ZIP is what runs on the live site is OWNER-REPORTED, not verifiable from here; confirm by hashing the installed plugin directory on the host (see gate list).

**Lineage (file-level).**
- 16.2.9 → 16.2.10: 13 files changed, none added/removed (HR lifecycle/calculator/statutory, frontend, rest-app, manifests).
- 16.2.10 → 16.2.11: 7 changed. 16.2.11 → 16.2.12: +26 files (REST SOP library route, SOP manifest and 24 SOP payload files), −2 (README.md, RELEASE_MANIFEST.json), 17 changed. 16.2.12 → 16.2.13: +1 (`assets/css/13-enterprise-saas-system.css`), 7 changed.
- 16.2.13 vs 16.2.9: +27 −2 ~16. Core `class-stockland-db.php`, `rest-sales.php` and `rest-pos-cart.php` are **byte-identical to 16.2.9**. So the 16.2.x line is a true cumulative descendant of 16.2.9 (the POS cart, CSS 07–12 and non-HR hardening survive to .13).
- 16.2.13 vs 16.3.0: 42 files only in .13, 5 only in 16.3.0, 17 differ. 16.3.0 contains none of 16.2.10–16.2.13 (SOP library, CSS 13, templates, uninstall policy).
- 16.2.13 vs 16.1.95: +40 −2 ~19.

**Findings on 16.2.13.**
1. SOP library ships as 24 PHP payload files (not DOCX), manifest `status: DRAFT_NOT_APPROVED`, version `1.0-DRAFT`, language am-ET. The payload SHA-256 values in the manifest (e.g. SOP-001 `60b1ef6c…ea29d`) equal the DOCX hashes in the 16.1.101 ZIP, so both packs are the same documents in two encodings. Approval is still owner-only.
2. `uninstall.php` preserves all `wp_stock_*` tables and options. This is a correct data-integrity safeguard; keep it.
3. Schema: `class-stockland-schema-contract.php` classifies a "fixed 86-table ERP base" (49 + 37 HR). Therefore the installed system may already hold 86 tables; the 16.2.9 receipt said that schema was *not approved*. The real table count of the live database must be read from the host before any migration is designed. OWNER INPUT REQUIRED (read-only System Health / table list).
4. The synthetic-payroll path is still in shipped runtime (15 references in `includes/*.php`).
5. 16.2.12/13 contain no `RELEASE_MANIFEST.json` or README: release identity is the plugin header/constant only. The build receipt for .12/.13 has not been supplied.
6. Helpful templates are bundled: `Stockland_Master_Import_Template.xlsx` and `Stockland_Physical_Count_Template.xlsx`.

**Revised canonical-base recommendation (supersedes §7).**
- **Operational/upgrade base = 16.2.13**, because it is what the owner runs, it is cumulative over 16.2.9, and any other base would drop or rewrite shipped behaviour (SOP library, CSS 13, templates). 16.1.95 stays the rollback *reference* and the source of the protected-master evidence, not the build base.
- 16.3.0 HRX is a comparison source only (it branches from 16.1.95, drops POS cart and CSS 07–13).
- Live-run fixes found on 16.1.95.1 must be re-proven on 16.2.13's core (which is 16.2.9 core). Several may already be fixed or conflict; decide by re-running the same live suites, not by assumption.
- Upgrade rehearsal target is now 16.2.13 → next build, on a sanitized copy of the live schema.

**Assessment of the pasted "Executive Summary" plan (recommend 16.3.0 from PR #48 head).** Not adopted. Checked against the packages:
- It states 16.3.0 is "the union of 16.1.95 plus all 16.2.x patches" and that "no existing 16.1.95 file was deleted". Both are false: 16.3.0 removes `class-stockland-payroll-statutory.php` and `rest-hr.php` and omits `rest-pos-cart.php` and CSS 07–12; it has none of 16.2.10–16.2.13.
- It asks to enable the 47-table HRX schema by default. The owner's 6 Nov directive says no table count is approved and schema creation requires an approved ERD, backup and rollback. Enabling it would breach a real data-integrity safeguard.
- It would remove `production_ready=false` rather than derive it from gates. Replacing a hardcoded flag with evidence-driven status is right; deleting the control and releasing is not.
- Its file table lists placeholder hashes, a "133 commits" claim, and `wp_hr_*` table names unrelated to the real `wp_stock_*` prefix, and its example tests (12.5% tax) contradict the encoded PAYE brackets. It is a planning sketch, not evidence.
- Its schedule (release by 19 Nov) is not a gate.
