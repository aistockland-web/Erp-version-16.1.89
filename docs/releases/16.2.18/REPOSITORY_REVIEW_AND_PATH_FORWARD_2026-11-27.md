# Repository 16.1.89 — full review and path forward (27 Nov 2026)

Scope: `aistockland-web/Erp-version-16.1.89` read through the GitHub API on 27 Nov 2026: 142 PRs (80 merged, 57 closed unmerged, 5 open), 118 branches, open issues #15, #17, #23, #76, #136, workflows, tags/releases. Facts below were read from the repo; items marked *inferred* are judgement.

## 1. Where the work actually lives
- Default branch `main` is stale (last real change 12 Nov). The delivery branch `delivery/stockland-full-erp-completion-2026-11-11` is **467 commits ahead** of `main` (main has 2 commits the delivery branch lacks: a docs update and a 2026-11-11 archaeology ZIP blob). All 16.2.x work, CI and installers are on the delivery branch.
- No git tags and no GitHub Releases exist. "Released" in the PRs means *CI artifact*, not a Release object.

## 2. Four different files all named `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip`
| SHA-256 (prefix) | What it is |
|---|---|
| `de7b0879…` | Certified artifact of 14 Nov (commit `387c193`, Actions artifact 14104031868). Evidence certificate: "artifact verified, **go-live not certified**". Issue #136 forbids mutating/rebuilding it. Predates the POS hub, Owner-declared payroll rules and the GitBook manual link. |
| `e6aa3d45…` | Candidate from PR #151 (20 Nov), "policy sign-off pending". |
| `96f41b48…` | Delivery head `03c162a` (PR #154, 22 Nov), all CI lanes green. |
| `aed7090c…` | Delivery head + PR #155 (import preview/commit fixes, contrast). All CI lanes green on `b29f79b`. **Current candidate.** |
Risk: identical file names hide which bytes are installed. **Fix:** release assets and the pack must carry the short hash in the file name; the certified `de7b0879…` stays untouched as the rollback/reference artifact.

## 3. Work-stream status
| Stream | State | Evidence / gap |
|---|---|---|
| Master installer (49 operational + 37 HR tables, HR gated) | Done, CI green | MySQL 8 and MariaDB, PHP 8.3/8.4, upgrade path 16.2.13→16.2.18 included in CI |
| Approval chain / maker-checker | Done | s21 + browser button test through four roles |
| Daily POS import (3 channels, CNET adapter, BM-only release) | Done (issue #136 acceptance gates 1–6 largely met) | s12, s19, s22 (real multipart upload→release→ledger/GL→replay) |
| POS device connector (Integration Hub) | Done | s19 41/41; supplier-API option deliberately disabled |
| Windows folder agent | Done, **CI-tested only** | needs one on-site run on a branch PC |
| Product Master import | Was broken (2 defects) → fixed in #155 | **No regression test yet** |
| GitBook manual + read-only bridge | Manual link in ERP merged; bridge PR #150 **unmerged**, base is an old branch | GitBook-side registration needs public HTTPS endpoint + token |
| HR/payroll (#15, #23) | Engine and Owner-declared ET-2025-AMEND-1395 rules shipped; HR tables gated | Independent Ethiopian payroll/tax adviser sign-off still absent (Owner decision) |
| MySQL stock-balance DDL (#76, P0) | Fresh install and CI upgrade proof pass on MySQL 8 | Issue still open; close with evidence once the Owner confirms the live site's starting version |
| Corrective workstream (#17) | Mostly superseded | Candidate branch `fix/issue-17-pos-import-management-lifecycle` (campaign/content-calendar lifecycle) is 53+ commits behind and flagged "do not merge wholesale" |

## 4. Open PRs — recommendation
- **#153** (approval/POS e2e test, draft): superseded by merged #154 → close.
- **#152** (21 Nov installer evidence docs): stale vs current evidence → close or fold into the 27 Nov record.
- **#150** (GitBook bridge, draft, base = old certificate branch): retarget to delivery, re-run its CI, then merge as an *optional* plugin (kept out of the master installer).
- **#149** (384-file animated walkthrough docs, merge state *dirty*): rebase on delivery; docs only, low risk.
- **#145** (final delivery hub docs, draft): refresh or close.
- Old 13 Nov branches (`feat/daily-pos-import-options`, `feat/stockland-pos-import-modes` incl. a Python folder agent, `fix/issue-17…`) are 200+ commits behind; their intent is already delivered via #138/#148/#154. **Archive (tag then delete); do not merge.**

## 5. Gaps that block a *production* claim (none are code defects found today)
1. **Live upgrade rehearsal:** CI proves 16.2.13→16.2.18. The Owner must say which version the live site runs; rehearse that exact snapshot (backup → restore → upgrade → compare) on a copy.
2. **Independent security review / pen test** and third-party code audit (this repo has strong automated gates, not an auditor's attestation).
3. **Payroll legal sign-off** (issue #15) and HR schema-contract approval (Owner).
4. **Backup/restore, TLS, hosting parity**, production POS workbook dry run.
5. **Regression tests:** add a Product Master import suite (barcode preview, commit, idempotency, duplicate barcode); add a populated-site axe run to CI (CI only tests an empty dashboard).
6. **Channel hardening from #136's control list** that the shipped agent only partly meets (it uses an Application Password; no request signing/replay protection/rate-limit design yet) — required *before* enabling the supplier-API channel, not for the current folder agent.

## 6. Recommended order of work
1. Owner creates the GitHub Release (pre-release) from `e422be7` or later using `RELEASE_NOTES_16.2.18.md`; add short hashes to asset names.
2. Merge/retarget #150, #149; close #145, #152, #153; archive stale branches.
3. Add the two missing CI tests (import suite, populated-site axe).
4. Rehearse the live upgrade on a copy of the real snapshot; then close #76 with evidence.
5. Owner decisions: payroll adviser sign-off or recorded acceptance, HR schema approval, SOPs; engage an independent security tester.
6. Promote delivery → `main` (or switch the default branch) so the default branch shows the real product.
7. Only then switch the pre-release flag off and schedule the production cut-over with a backup and rollback to `de7b0879…`.
