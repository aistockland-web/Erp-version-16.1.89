# Stockland ERP — Cross-Chat, Notion, GitHub and Release/CI Reconciliation

**Evidence cutoff:** 7 November 2026 (Africa/Addis_Ababa)  
**Repository:** https://github.com/aistockland-web/Erp-version-16.1.89  
**Default branch:** main  
**Main SHA at cutoff:** 412574b2b78be1706414496ce7e80cdd58c5b379  
**Audit branch:** audit/cross-source-reconciliation-2026-11-07  
**Disposition:** Coordination and source inventory are in progress. **No production release is certified by this report.**

## 1. Executive reconciliation

Evidence in accessible conversation summaries, Notion release records, GitHub history, current CI and the Codespaces/source-archaeology branch does not describe one linear version sequence. It describes several source lines and candidate installers. Branch names, plugin version strings, merged PRs and successful workflows are not equivalent to an approved cumulative production release.

Current GitHub API snapshot:

| Register | Verified result |
|---|---:|
| Main commit history retrieved | 792 commits |
| Oldest returned main commit | 46cb59616cd39d687ab4810b84c776e7c62bdcd5 — 19 Oct 2026 |
| Pre-existing remote branch refs inventoried | 69 |
| Pull requests inventoried | 57 |
| Merged PRs | 34 |
| Closed but unmerged PRs | 21 |
| Open PRs | 2 |
| Git tags | 0 |
| GitHub Release objects | 0 |
| Main branch protection | Disabled (protected=false) |

Machine-readable inventories are stored in this audit branch:
- STOCKLAND_ERP_MAIN_COMMIT_INVENTORY_2026-11-07.csv — all 792 commit records returned by the GitHub API.
- STOCKLAND_ERP_BRANCH_HEAD_INVENTORY_2026-11-07.csv — all 69 pre-existing branch heads and protection status; excludes this audit branch.
- STOCKLAND_ERP_PULL_REQUEST_INVENTORY_2026-11-07.csv — all 57 PRs with state, base/head, exact head SHA and merge SHA.
- STOCKLAND_ERP_RELEASE_CI_EVIDENCE_2026-11-07.csv — milestone installer/checksum/CI and acceptance index.

**History boundary:** the accessible main-branch commit history begins on 19 October 2026. It does not substantiate the full August–October history described in prior conversations. Older Library artifacts and conversation summaries provide additional context, but full private chat/session transcripts are not available as one queryable archive in this run. Notion agent-session search was unavailable in this workspace connection; ordinary Notion page search/fetch was used. Do not claim every past conversation or historical source was independently recovered.

## 2. Source-of-truth hierarchy and key decisions

1. **GitHub** is the implementation, commit, PR and Actions record for this repository.
2. **Notion** is the working agreement, product/architecture decisions, audit findings and acceptance authority.
3. **Exact installer binaries and SHA-256 values** identify what a workflow built; a branch name or version label does not.
4. **Live host inventory** is required to prove what is installed. A reported plugin version or owner-supplied ZIP is not proof of live file parity.
5. **Successful CI is not** live WordPress/MariaDB acceptance, business-owner sign-off, statutory approval or a signed software certificate.

Controlling Notion records:
- [Canonical Release Audit & Acceptance Gates](https://app.notion.com/p/3fc6db0f767e81f2a3bbe644048054f9)
- [16.1.95 Protected Master — HR/Payroll Integration Decision, 4 Nov](https://app.notion.com/p/40d6db0f767e8140b744d32a043269f9)
- [Final Production Release Decision, 3 Nov](https://app.notion.com/p/40d6db0f767e81eb9ed1d7ddb6525d10)
- [16.3.0 Consolidation Evidence, 3 Nov](https://app.notion.com/p/40c6db0f767e8115b6e2fc0b437d4a0a)
- [HRX Exact-Package Recheck and Release Decision, 5 Nov](https://app.notion.com/p/40f6db0f767e813eadd0c736abd3d04c)
- [16.1.95 Production Audit Closeout, 24 Oct](https://app.notion.com/p/4026db0f767e81cfaebae7db56867d38)
- [HR Design Reset v0.5](https://app.notion.com/p/b2c96e7d1c2c45f7a0b269152fad0b2d)
- [HR Resource Pack Delivery & Acceptance Register](https://app.notion.com/p/4056db0f767e810d8ca2cbc1fe3e830e)

The 4 November protected-master decision remains controlling for preservation: exact 16.1.95 is a rollback/comparison reference, not a blanket instruction to overwrite a later installed version. The 6 November Codespaces archaeology addendum records later owner-supplied 16.2.10–16.2.13 package identities and recommends 16.2.13 as the operational upgrade base **subject to proving live installed source/hash and schema**. This later package evidence must be reconciled with the earlier Notion decision, not ignored or silently substituted.

## 3. Version, package and source-line reconciliation

| Line/package | Recorded installer SHA-256 | Reported contents/lineage | Current conclusion |
|---|---|---|---|
| **16.1.95 protected master** | 494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625 | 75 files; 49-table protected baseline; exact package CI run #40036018031; artifact #12536703884 | Preserve as rollback/reference. Production certification remains NO-GO pending host/data/payroll/recovery/Owner acceptance. |
| **16.2.9 selected HR/payroll source** | c9f6459c71087575d8554e599a1ed5025a9e21655f24886c933232ac194d9c01 | 88 files; PR #39 merged at bd6c78f180e3bc6871317aa32c1f11d4fab8a427; exact package workflow #41146685514 | HR/payroll source, not safe wholesale replacement. Notion identifies unrelated operational changes and unapproved schema expansion. |
| **16.2.9 legacy-default corrective build** | fc22346f6e1fafc9e47a50eba76a2c0846fb9f09407168564e1f295ce69290d8 | PR #61 head e6cf4d70b211edfe3308a5fe3672dff8afd0c2ec; exact workflow #42258772425; 88 files; outer artifact #13523269084 | Narrow CI-verified correction to legacy approval and pension defaults. PR #61 merged as ad127ae8f672243b4d5bd7bd703e6104beef995a; issue #60 closed. Not a final cumulative installer. |
| **16.2.10 owner-supplied package** | 2ea7832efb6a42decfd53496aaaf309eb068228c54507a3b8022776860a1601f | Prior Codespaces archaeology reports 88 files / 55 PHP; descendant of .2.9 | Hash and inventory are recorded in the source-archaeology addendum. ZIP bytes unavailable in this turn for fresh rehash. |
| **16.2.11 owner-supplied package** | 27c66b65e999f5609d070c105517ecc491fb705a887bbdefb11851454750dee8 | Prior archaeology reports 88 files / 55 PHP | Same limitation: recorded earlier; binary unavailable here for independent rehash. |
| **16.2.12 owner-supplied package** | db82f3e8ec565539c0341d47d39e0382b4ba04e370464f59a742c2941d96ca75 | Prior archaeology reports 112 files / 80 PHP; adds 24 SOP payloads | Same limitation; exact CI receipt not present in retrieved records. |
| **16.2.13 owner-supplied / reported installed package** | 74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13 | Prior archaeology reports 113 files / 80 PHP; 16.2.9 DB, sales and POS-cart core preserved; SOP payloads and CSS module 13 added | **Most recent reported operational/upgrade base, not independently confirmed as live installed directory.** ZIP unavailable here for fresh rehash; live host needs per-file hashes and schema inventory. |
| **16.3.0 HRX** | Candidate binaries recorded: earlier f48993499e7664c45a079595af8efedbd5881728952cf019428f5db2d1872412; later exact CI build 1e30d52c0c6c63fe643cb773ff606d74eccf0e95a95dad549099346323b23406 | PR #48; later run #42057219158; artifact #13428294191; outer SHA 7f84fcd1610037dd9052b7a69f92b4c52c948aa1cd413faac6f45bd0991523e6; 76 files | HRX candidate originally built from 16.1.95, not cumulative on 16.2.13. Proposed 47-table model unapproved/disabled. WPCS 687 errors / 145 warnings in 5 Nov recheck. PR #48 is now merged as source/docs, but this is not production approval or publication. |
| **16.3.1 local conditional record** | 36b215da876f297a6d6948eda0b050e4d8b4d9d5ef2c7009e5b5120f3ca2faa5 | Prior Notion note says 85 files / 63 assertions / 12 protected services preserved | Exact binary not recovered from current Library, workspace or GitHub releases; historical receipt only. |
| **16.1.101 HRX health/SOP staging** | 2916d9ddd80f0174da8ccb8ab9b57f51050ed5aa3d65d210e6ea97283262b5d1 | Branch release/16.1.101-hrx-health-sop-tab, PR #49 closed unmerged; 103 archive entries plus directories; source report records 24 DOCX SOP files | Staging only, not production release. SOP files await content/Owner review; payroll SOP is not legal authority. |
| **16.1.98 current-main exact CI build** | 1997b33326b45c7d0a32ba283dc7fb6ea8bd4a61d1392990944065add5c81f8b | 79 files, 52 PHP, 2 JS, 3 JSON; builder baseline 16.1.95; 73-table proposal (29 core +16 accounting +28 HR), 24 new tables | Exact current build inspected in this audit. Receipt says schema_contract_approved=false, production_ready=false, wordpress_activated=false, production_database_touched=false. **Do not install over the reported 16.2.13 line**: it is built from older 16.1.95 and is not the cumulative .2.13 successor. |

### Critical lineage conclusion

Do not select a build base by highest version label. The 16.3.0 HRX package is a side line from protected 16.1.95 and removes 16.2.9 operational files, including POS cart and CSS modules 07–12. The 16.2.x owner-supplied packages are a separate cumulative line per the 6 Nov archaeology addendum, with 16.2.13 the reported installed package. Exact .13 ZIP bytes and live file parity remain decisive missing evidence. The current 16.1.98 workflow also builds from 16.1.95, so green CI must not be interpreted as upgrade approval for 16.2.13.

The 16.2.13 source report flags a fixed 86-table schema claim and synthetic-payroll references in shipped runtime. This is a high-priority reconciliation item against the 16.1.95 49-table contract and HR design reset. No schema or payroll assumptions should be activated until approved ERD, actual live table inventory, data-health report and migration/rollback evidence exist.

## 4. Pull-request and commit highlights

The full 57-PR register is in the CSV. Material PRs:

| PR | State at cutoff | Identity | Significance |
|---|---|---|---|
| #14 | Closed, unmerged | 16.1.95 candidate head 559190fec4067a17f1cbedbf665060a565e32fdf | Title does not mean merged; baseline identity comes from exact artifact and CI. |
| #39 | Merged | merge bd6c78f180e3bc6871317aa32c1f11d4fab8a427 | 16.2.9 HR/payroll source. |
| #48 | Merged on 6 Nov | merge c1fdf5bf3ece55b3810ec3bfc847a502ce68d006 | HRX branch changes entered main history, but remain a separate non-cumulative candidate; no production certification. |
| #49 | Closed, unmerged | head 28b9308db879b5b298739170120338f139de7326 | 16.1.101 staging package and SOP resources. |
| #50 | Merged | merge a46214f6bd5e21a9948d5c896d2136d722d36f16 | 3 Nov consolidation evidence/docs. |
| #55 | Merged | merge 91a8399c79746622cb836f6fc301d05b2b0655db | Owner directive: incremental target commits through completion. |
| #56 | Merged | merge 30752e6ece6345fcbbf2d15ef3e348cf87beeb85 | Module-scoped health/readiness semantics. |
| #57 | Open | head 0f681fe28463da61bafbb2d03c794f588b52feb5; 5 commits, 7 documentation/config files | Codespace PR. Its .vscode/mcp.json contains a compressed Node --eval telemetry/MCP snippet; provenance and behavior need a security review before merge. Documentation-only diff, not ERP runtime update. |
| #58 | Merged | merge 476b0b6a819f7fbb63f32282791802b13e14d917 | HR/payroll text-only icon/UI correction. |
| #59 | Merged | merge f60b5a48b51c203cb7627e18b74621e98757298e | HR SVG overlay cleanup. |
| #61 | Merged | merge ad127ae8f672243b4d5bd7bd703e6104beef995a | Prevent implicit approval and pension defaults; issue #60 closed. |
| #62 | Merged | merge c1fdf5bf3ece55b3810ec3bfc847a502ce68d006 | Health/review docs, same main snapshot as HRX merge. |
| #64 | Open, inverted direction | base feature/hr-resource-download-handler-16-1-98; head main SHA 412574b2... | Not a normal feature-to-main PR. Do not merge as presented; reconcile/close or retarget after review. |

Closed-but-unmerged PRs are not part of mainline. This includes older staging, documentation, HR and UI branches. Mine their work selectively; do not assume it was delivered. The feature/stockland-erp-16-2-10-cumulative branch points to a historical main SHA and does not prove a distinct .2.10 release.

## 5. Current exact-main CI evidence — 16.1.98

All three records were queried against main SHA 412574b2b78be1706414496ce7e80cdd58c5b379:

| CI run | Purpose | Outcome | Evidence |
|---|---|---|---|
| [#42298602260](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42298602260) | Exact 16.1.98 package build, package checks and PHP 8.3/8.4 validation | **SUCCESS** | Actions artifact #13543375439. Outer SHA-256 33b0733ec9c0645006338f4c5bc128e3c444b834a8ba1a06a34cef22a1079cc0. Inner installer SHA-256 1997b33326b45c7d0a32ba283dc7fb6ea8bd4a61d1392990944065add5c81f8b. |
| [#42298602217](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42298602217) | Deep source, security, PHP matrix, Semgrep and PHPCS/WPCS review | **WORKFLOW SUCCESS; quality debt remains** | Semgrep: 46 PHP targets; zero findings and zero errors. PHPCS/WPCS: 57 errors, 266 warnings, 162 fixable entries across 47 files. Not a clean WPCS pass. |
| [#42298602494](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42298602494) | Local release-gate validation | **SUCCESS** | Validates the 16.1.89 local release path; supplementary, not live host or .2.13 acceptance. |

Artifacts downloaded and rechecked in this audit:
- Outer Actions evidence bundle: stockland-erp-16-1-98-canonical-ci.zip; SHA-256 33b0733ec9c0645006338f4c5bc128e3c444b834a8ba1a06a34cef22a1079cc0; 1,143,177 bytes.
- Inner installer: STOCKLAND_ERP_16.1.98_CANONICAL_WORDPRESS_UPDATE.zip; SHA-256 1997b33326b45c7d0a32ba283dc7fb6ea8bd4a61d1392990944065add5c81f8b; 1,149,047 bytes; 79 files; one plugin root; ZIP CRC PASS.
- PHPCS/WPCS report archive: stockland-erp-phpcs-report.zip; SHA-256 b1292be8cab078d9ff6eb64fdb63cfd53baa85555dd3674cc0451232e02068b5.
- Semgrep report archive: stockland-erp-semgrep-report.zip; SHA-256 e88641e0e3193fb28b4fd87e2eec5c2fe10ebee7e7d402f976aebed5ea9a1eca.

The 16.1.98 receipt says the proposed 73-table schema is not approved, production readiness false, WordPress activation did not occur, and no production database was touched. It is a CI-verified **candidate**, not a release certificate or install authorization. Artifact retention is time-limited (reported expiry 6 December 2026); preserve copies and hashes before expiry.

## 6. Earlier CI and artifact evidence index

- **16.1.95:** exact package workflow #40036018031; artifact #12536703884; inner SHA 494e777... . Notion classifies code/package/security gates as passed but production acceptance as blocked.
- **16.2.9:** exact workflow #41146685514 on PR #39 source line; inner SHA c9f645... . It does not remove schema and synthetic-payroll concerns.
- **16.2.9 corrective build:** #42258772425, artifact #13523269084, inner SHA fc22346... . Passed exact-package and PHP 8.3/8.4 workflow gates for the narrow unsafe-default correction.
- **16.3.0 HRX:** latest recorded exact candidate workflow #42057219158, artifact #13428294191; inner SHA 1e30d52...; outer SHA 7f84fcd... . Workflow succeeded, Semgrep/security passed; full PHPCS/WPCS remained 687 errors and 145 warnings. This was a 16.1.95-derived candidate, not the cumulative 16.2.x line.
- **16.1.91 historical:** Library record identifies release gate #39357021294, deep source/security #39357021293, enterprise security #39357021366; candidate inner SHA e3dfe61dcbdfd382147407daca4ec32ed737dc35e5931d55d1ffbaf5a7337b34.
- **16.1.90 and 16.1.92:** root checksum sidecars are present in repository, but this audit did not independently recover all exact CI artifacts/run certificates for these builds.
- **16.2.10/.11/.12/.13:** source archaeology has owner-supplied checksums and inventories; exact-package CI receipts were not recovered in the current accessible records. Mark exact CI **NOT EVIDENCED HERE**, not passed.
- **16.3.1:** historical local conditional result only; exact binary and exact-source CI could not be recovered here.
- **16.1.101:** staging binary and SOP hash manifest are present on the staging branch; not a production release.

## 7. Certificates, attestations and release governance

**No formal signed release certificate was found.** GitHub reports zero tags and zero Release objects. The current main tree contains older .sha256 sidecars for 16.1.90–16.1.92, but no release signing certificate, .sig, .pem signing material or signed attestation path was found in main. A SHA-256 sidecar establishes byte identity only when independently recomputed against the artifact. It does not prove who approved/signed it, legal acceptance or deployment.

Use precise terms:
- **PASS:** named automated gate completed successfully on the stated commit/artifact.
- **FAIL:** a specific test/diagnostic failed.
- **BLOCKED:** a required external approval, schema, host or source prerequisite is unresolved.
- **NOT RUN / NOT EVIDENCED:** check not performed on the exact package/environment.
- **CI-verified candidate:** exact package passed the named CI jobs, with remaining gates named.
- **Production certified:** only after exact final installer SHA passes runtime, data/recovery, statutory and stakeholder acceptance, and Owner authorizes release.

Do not call a green workflow a certificate. A release manifest/build receipt is provenance evidence, not a cryptographic signature.

## 8. Open issues and acceptance blockers

Material open GitHub issues:
- [#15 — full HR/payroll lifecycle](https://github.com/aistockland-web/Erp-version-16.1.89/issues/15): workflow acceptance, role/location authorization, contracts, compensation, payroll, payments, remittances and signed Owner/HR/Finance/legal evidence.
- [#17 — CI builder, POS import routing and Management Intelligence](https://github.com/aistockland-web/Erp-version-16.1.89/issues/17): exact builder boundaries, correct POS import workflow placement and read-only management reporting.
- [#23 — HR/payroll data model, Ethiopian law-to-SOP and PAYE](https://github.com/aistockland-web/Erp-version-16.1.89/issues/23): approved ERD, source-mapped legislation and effective dates, qualified reviewer and Owner/HR/Finance approvals.
- [#47 — schema contract, supplemental integrity scan and PWA registration](https://github.com/aistockland-web/Erp-version-16.1.89/issues/47): exact installed-vs-package schema/source parity, terminal per-check scan receipt, PWA worker registration and no false-green CI.

[Issue #60](https://github.com/aistockland-web/Erp-version-16.1.89/issues/60) is **closed** through merged PR #61 and covers only two unsafe defaults. It does not close broader HR, encryption, schema, legal, access-control or live-host acceptance.

## 9. Required next sequence

1. **Read-only live identity:** hash actual installed Stockland plugin files on the WordPress host; export sorted table keys, schema marker and plugin version. Do not modify production data. Confirm whether SHA 74e320... matches live 16.2.13.
2. **Recover/recheck owner packages:** make exact 16.2.10–16.2.13 ZIP bytes available, independently recompute each SHA, file inventory and CRC, and preserve originals.
3. **Reconcile live fixes:** rerun the 16.1.95.1 live-fix suite on the actual 16.2.13 line, resolving conflicts with 16.2.9 database availability and sales changes.
4. **Resolve schema/data safety:** compare actual live table set (49/73/86 claim) and dependencies. Approve source-mapped ERD, data-health map, migration and rollback before activation. Do not activate unapproved 73- or 86-table proposals.
5. **Reconcile HR/payroll:** remove/isolate synthetic calculation paths from production runtime; use approved effective-dated legal rules; preserve history; encrypt and scope private documents; prove full lifecycle and two payroll periods in disposable/restored environment.
6. **Preserve SOP work:** compare 24 DOCX from 16.1.101 staging with 24 PHP payloads in 16.2.12/.13, record hashes, obtain process-owner review and separate qualified legal approval. SOP text is not legal certification.
7. **Repair outstanding UI/PWA/CI/quality issues:** finish resource downloads, mobile acceptance, role/location deny tests, deep-integrity completion and PHPCS/WPCS diagnostics without hiding/skipping checks.
8. **Build one cumulative candidate from the proven live operational base**, with focused commits and PRs. Build the installer from that exact source commit; attach manifest, source inventory, tests, scans, SQL/data receipts and backup/restore evidence.
9. **Release only after exact-hash gates pass**, with Owner/HR/Finance/process-owner and qualified Ethiopian legal/statutory acceptance. A separate explicit release authorization is required before installation/deployment.

## 10. Non-actions and limitations

- No live WordPress site was activated, updated or deployed in this reconciliation.
- No production database, inventory, sales, ledger, employee or payroll records were read or modified.
- No schema migration, payroll calculation or synthetic transaction was run on production.
- No PR was merged, no release was published, and no issue was closed as part of this coordination.
- 16.2.10–16.2.13 ZIP bytes were not available in the current Library or workspace to independently recompute their recorded hashes; those values come from the prior Codespaces/source-archaeology report.
- The current Notion connector supports page search/fetch, but agent-session search was unavailable. This register consolidates accessible recent-chat summaries, memory context, fetched Notion pages, GitHub history and current CI artifacts—not every full transcript from every separate chat.

**Final state at cutoff:** main is 412574b2...; 16.1.98 is the latest exact package produced by current-main CI in this audit, but it is an older-baseline candidate and explicitly not production-ready. Do not select an upgrade base until reported 16.2.13 live package and schema are verified. No formal signed certificate or published GitHub release exists in the repository at this cutoff.


## Addendum — audit-register PR checks (7 Nov 2026)

Draft PR #65 was run through the repository's branch workflows on audit commit b9e34a15c3a6e01cc3a9141bab8328a0c8ca20ec:
- [Run #42301168417](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42301168417): local release-gate job successfully validated the exact 16.1.89 installer identity, package contract, assets and syntax. This is a documentation-branch check and validation of the existing 16.1.89 package, not the 16.1.98 installer or the 16.2.13 operational line.
- [Run #42301168443](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42301168443): ShellCheck/actionlint passed; its non-deployable remediation-candidate build job was skipped.
- [Run #42301168527](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42301168527): targeted PHPCS recheck was skipped.

Therefore the draft audit PR has passing documentation-branch checks, but no new application candidate was built from its documentation-only changes, and the skipped PHPCS job is not a pass. The main 16.1.98 CI findings in section 5 remain unchanged.
