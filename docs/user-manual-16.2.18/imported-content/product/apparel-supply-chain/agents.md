# AGENTS

These instructions apply to this product folder when it is imported as `product/apparel-supply-chain/` inside the canonical Stockland repository. The repository-root `AGENTS.md` remains authoritative and takes precedence.

## Mission

Build a configurable multi-company Apparel & Lifestyle Supply Chain product on the existing Stockland ERP foundation. Manufacturing is optional by tenant capability. HR & Workforce and Finance are separately owned applications; never merge their data authority into Apparel.

## Source and Change Rules

1. Refresh the canonical delivery branch and exact commit before every change. The snapshot used to prepare this folder was `fe937ea97123a243c410696e4007c48d3332803d`; it may be stale by the time this folder is imported.
2. Read current root `AGENTS.md`, Notion Chief Architect Hub, CAP, release/upgrade runbook, current app README, `RELEASE_MANIFEST.json`, routes/services and exact source/data contract before touching runtime.
3. Preserve the existing 49 operational schema keys, Product Master STYLE/VARIANT IDs, stock ledger, reservations, posted journals, payroll history, tenant/branch/warehouse identity, audit and permissions.
4. The separate 37-table HR lifecycle extension is Owner-approved/fingerprint-controlled; HRX 33 is preservation-only. Do not infer schema authority from counts or a README.
5. All apparel DDL is additive, separate, versioned, fingerprinted, reviewed, backed up, physically verified and tested with rollback/forward-fix.
6. Never create a duplicate Product Master, stock ledger, AP/AR/GL or payroll engine.
7. Keep the current WordPress/PHP modular monolith for the existing tenant. Do not replace it with an external repo's stack.
8. Use one narrow feature branch and reviewable commits. No direct `main` writes, force pushes or unreviewed external code import.

## External Repository Use

BengalTex, T-VERSE, Production Quality ERP and Thread ERP are process/architecture references only. Verify current HEAD, licence, exact source and security before any external code reuse. This workspace copies no external source code, CSS, UI assets, image, SQL dump, credential or demo record. Re-implement business ideas in Stockland conventions.

## UI / CSS Rules

* Existing Stockland 14-module CSS source order, `app.css` and `app.min.css` build are protected.
* Do not add global or tail CSS, import unknown themes, use CDN styles, or copy reference-repo CSS.
* Reuse current Stockland components/tokens/Bootstrap asset. Any required visual change must identify its owning source module, prove the defect with a browser/DOM test, be scoped and preserve responsive/accessibility behavior.
* A CSS asset's hash proves file identity only; computed contrast and behavior must be tested on the exact app shell.

## Apparel Domain Rules

* Every commercial order and production record pins exact approved style, tech-pack, pattern, grade, BOM, routing and pack-spec revisions.
* No production order may use a draft/unapproved pattern, BOM, grade or routing.
* Fabric roll/lot/shade/defect/QC state follows raw material through lay, cut ticket, bundle, WIP, finished goods, carton and shipment.
* Reservation is not physical issue; issue is not shipment. All physical movements use the existing inventory ledger.
* MRP creates proposals; it cannot silently issue material or approve POs.
* Cutting optimizer output is only a candidate. Pattern Master and Cutting Manager must approve exact hashes before release.
* AI may explain, draft, compare and request review. It cannot approve, mutate approved geometry, reserve/issue stock, release cut, post Finance/HR or start a machine.
* Quality hold prevents sellable FG, carton sealing and shipment. Rework, scrap, return and claim are explicit auditable states.

## Engineering/Test Rules

* Every REST route has server-side permission plus tenant/site/object/state checks. A nonce is not authorization.
* Every write has idempotency and concurrency protection. Audit/workflow/event failures roll back business writes.
* Unknown fields, schema versions, units, stale revision hashes and unsupported CAD entities fail closed.
* Test positive, negative role, wrong tenant, stale version, duplicate retry, concurrent execution, partial failure, rollback, quantity reconciliation and audit continuity.
* Keep runtime tests on disposable synthetic databases. Never import the live company backup, real payroll, employee vault, customer, supplier or financial records.
* Every claim is tied to exact source commit, artifact hash, test run and environment. Use `PASS`, `FAIL`, `BLOCKED`, `NOT RUN` and `OWNER INPUT` precisely.

## Standards, HR and Finance

* Standards registry stores standard issuer, edition, applicability, license/source, effective status and customer adoption. Do not copy normative text without rights or claim certification from a field setting.
* Ethiopian tax, pension, labour and payroll rates remain fail-closed until sourced from current official effective-dated references, reviewed by qualified local professionals and independently approved.
* HR owns employee PII, attendance, compensation and payroll. Finance owns journals, AP/AR and close. Apparel receives only minimum approved workforce/financial events.

## Release Safety

* Hash source and every file. Never describe SHA-256 as proof of quality or certification.
* Release ladder: workspace → feature branch → reviewed PR → CI accepted → package built → disposable installer verified → Owner approved → published → deployed.
* Do not run/launch Codespaces or application services without the task explicitly calling for it. This prepared workspace was created without running WordPress, DB, AI provider or external CAD tool.
* Do not deploy, write production DB, post live money, run/disburse payroll, change statutory rules or alter protected schema without the required explicit Owner authorization.
