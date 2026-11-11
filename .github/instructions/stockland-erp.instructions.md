---
description: Mandatory Stockland ERP engineering safeguards and acceptance rules.
applyTo: "**/*"
---

Follow /AGENTS.md and .github/copilot-instructions.md. Read and search latest Notion decisions before every task. Every plan/commit must identify the Notion decision, exact Git SHA, scope, invariants, test plan and open findings.

Verify the real .13 and .15 ZIP bytes against committed sidecars; inspect exact contents and .13-to-.15 upgrade behavior. A higher version is not approval. Do not rename, repack or overwrite the original ZIPs to obtain a pass.

No production DB, live WordPress, deployment, release tag, signed certificate, destructive migration, employee/customer data or production credentials in this workspace. Synthetic QA only. No unapproved HR schema, payroll/statutory or SOP approval assumptions.

One coherent commit per target; branch/PR review; exact-source CI; transparent failure/not-run reports. No force-push, auto-merge or disabled gate.
