# Stockland Ethiopia HR Resource Pack — Delivery & Acceptance Register

**Date:** 26 October 2026  
**Status:** Draft documentation package generated; Owner and professional review required.  
**Scope:** HR/payroll lifecycle, role contract/JD templates, code of conduct, operational SOPs, allowance and payee dropdown catalogues, social-account reporting, and HR resource-library design.

This is a documentation-only handoff. It does not authorize production code, DDL, payroll posting, statutory calculator activation, migration, or retirement of legacy HR data.

## 1. Pack inventory

### Package A — Bilingual HR Process Center resource pack

Chat deliverable filename: `Stockland_HR_Process_Center_Resource_Pack_v1.zip`

Archive contains 31 files:
- 25 individual role-specific bilingual Amharic/English one-page contract/JD templates.
- `Stockland_Bilingual_Role_Contracts_and_JDs_v1.docx` (25 pages, one role per page, Amharic and English side by side, blank employment particulars).
- `Stockland_Bilingual_Code_of_Conduct_and_Function_SOP_Playbook_v1.docx` (21 pages: cover plus 20 controls/SOP pages).
- `Stockland_Bilingual_Code_of_Conduct_and_SOP_Catalogue_v1.docx`.
- `Stockland_HR_Dropdowns_Legal_Rules_Social_Reporting_and_Replacement_Roadmap_v1.docx` (4 pages).
- `Stockland_HR_Process_Center_Resource_Catalogue_v1.docx` (1 page).
- README.

### Package B — Modular HR / Payroll / Operations document library

Chat deliverable filename: `Stockland_HR_Library_Pack_v1.zip`

Archive contains 37 files: role descriptions, function SOP drafts, code-of-conduct draft, allowance and individual-payee dropdown catalogue, roadmap, CSV manifest and README. It is a modular reference pack, not an ERP deployment.

## 2. Verification performed

- Both ZIP archives passed `unzip -t` with no compressed-data errors.
- Primary DOCX files rendered successfully with the document renderer.
- Rendered page counts: role template bundle 25; SOP playbook 21; dropdown/legal/social-reporting/replacement roadmap 4; HR Process Center resource catalogue 1.
- Contact-sheet review showed consistent page structure and no obvious global clipping/overflow in the reviewed renders.
- Full all-pages, 100%-zoom visual acceptance remains outstanding; Owner/HR/Finance review and legal review are outstanding.
- No live ERP code, database, employee data, payroll run, or production configuration was changed.

## 3. Functional requirements

### Employee earnings and allowance dropdowns

Capture the factual payment category (base wage/salary; overtime; shift/night/rest-day/holiday premium; commission; bonus/target incentive; housing; transport; per diem; actual-expense reimbursement; medical; communications; in-kind benefits; arrears/corrections; leave/final settlement; other requiring review).

A dropdown label does not determine tax or pension treatment. Each rule must be approved, effective-dated, source-traceable and testable. Historic allowance exemptions or transport caps must not be hard-coded until current applicability is confirmed.

### Digital creators, influencers and service providers

Capture creator income such as sponsored content, brand ambassadorship, affiliate fees, platform monetization, livestream/podcast, subscriptions, tips and non-cash product consideration. Capture service-provider categories such as design, production, editing, translation, software/IT, consultancy, repair, transport/delivery, cleaning/security, events and other reviewed service.

Record the agreement, deliverable, dates, payer/payee identity, cash and non-cash value, supporting evidence, TIN/licence/residency facts where relevant, classification and withholding decision, source clause, reviewer and payment evidence. Do not assume an unregistered creator or service provider is automatically tax-free; do not apply a withholding rule solely because of missing registration without checking statutory scope and exceptions.

### One central Finance/AP workflow

Approved creator, influencer and freelancer/service-provider payments route through a central Finance/AP control and payment workflow with maker-checker, supporting documents, payee balance, withholding assessment, idempotent payment references and ledger reconciliation. Branches submit requests and evidence; they do not operate separate payment departments.

### Social accounts and management reporting

Phase 1: official platform links/native sign-in with per-user delegated provider permissions. No ERP password, MFA, recovery-code or private-session storage; no scraping, automated posting, messaging, settings changes or ad spend. Influencer Manager records manual platform Insights with source and timestamp.

Management views should show account/campaign/owner/operator, content status, views/reach/engagement, link/coupon attribution, POS/e-commerce sales where attributable, fees plus gifted-stock value, conversion/return metrics only where source data exists, last updated and missing/stale data. Direct sign-in alone does not provide auto-refreshing analytics. Read-only API/OAuth is a later phase after scopes and privacy/security review.

### HR resource download workflow

Target menu: HR → HR Process Center → Downloadable Resources → Function → Role → Language → Download. Actual implementation requires authenticated server-side storage, approved version and owner metadata, effective date, approval status/hash, role-based access and download audit. Do not expose personnel documents through predictable public URLs.

## 4. Law and acceptance gates

Official starting points (source discovery is not legal certification):
- Labour Proclamation No. 1156/2019 — Ministry of Justice: https://justice.gov.et/en/law/labour-proclamation/
- Income Tax (Amendment) Proclamation No. 1395/2025 — official publication copy: https://www.ffic.gov.et/Portals/0/1395-2017.pdf
- Ministry of Finance tax directive register: https://www.mofed.gov.et/mof-directive/tax-directive/
- Personal Data Protection Proclamation No. 1321/2024 — Ministry of Justice: https://justice.gov.et/en/law/personal-data-protection-proclamation/

Before activation, qualified Ethiopian labour, tax and pension reviewers must confirm full text, amendments, scope, exceptions, effective dates, taxable base, rounding, withholding and reporting. Explicitly review post-amendment exemption/transport treatment, digital-content classification/implementing thresholds and interaction with Article 97/Schedule D, and the current pensionable-pay basis.

Store rule instrument/clause, official source file/hash, publication/effective dates, applicability, reviewer, approval, test vectors and status. Unknown or conflicting cases fail closed to review. Blank contract fields are not a waiver of statutory rights.

## 5. Production-safe roadmap

1. Complete Owner/HR/Finance review of role duties, reporting lines, internal policy decisions and approval authority.
2. Complete professional rule-by-rule legal/tax/pension review and record dispositions.
3. Finish full-resolution document QA and establish controlled template versions.
4. Audit the actual installed ERP code, DDL, dependencies, legacy data and CI gates; do not invent or freeze a new table count from this document.
5. Build access-controlled HR resource delivery, role workflows and approved effective-dated legal rules only through a separately reviewed implementation PR.
6. Run synthetic boundary tests, historical payroll replay, parallel payroll comparison, AP/GL/PLEDGER reconciliation, security/privacy review, migration rehearsal, backup/restore and rollback proof.
7. Cut over only after Owner + HR + Finance acceptance and verified evidence. Preserve legacy HR data until migration and rollback gates pass.

## 6. Project links

- HR/Payroll design, legal-rule and acceptance gates: https://github.com/aistockland-web/Erp-version-16.1.89/issues/23
- Independent CI/code corrective workstream: https://github.com/aistockland-web/Erp-version-16.1.89/issues/17 (not closed or resolved by this documentation handoff).
- Notion delivery/acceptance register: https://app.notion.com/p/4056db0f767e810d8ca2cbc1fe3e830e?pvs=204
- Notion HR design authority page: https://app.notion.com/p/b2c96e7d1c2c45f7a0b269152fad0b2d?pvs=204

**Do not mark this pack legally approved, production-ready, deployed, or 100% compliant.**