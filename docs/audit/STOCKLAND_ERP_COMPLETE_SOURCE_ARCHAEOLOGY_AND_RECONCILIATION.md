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
3. 24 Amharic-first SOP/JD/contract files — not present anywhere checked.
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
| Medium | No SOP binaries in any package |
