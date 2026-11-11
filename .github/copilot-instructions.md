# Repository-wide Copilot instructions

Read /AGENTS.md first and follow its strict Notion/GitHub change-control, safety and release rules.

This is the Stockland Ethiopia cumulative WordPress ERP, not a generic demo. Preserve existing business behavior and the five approved locations. Do not create sample ERP content, generic dashboards, fake customers/products/employees, invented payroll values or seeded business transactions. Synthetic fixtures are allowed only in isolated tests and must be explicitly named as fixtures.

Before changing code, refresh the branch, source package hashes, relevant PRs, Notion decisions and audit findings. The operational source is 16.2.13; 16.2.15 is the selected review candidate and must be proven as an update from .13. 16.1.95 is rollback/reference only. Never infer ancestry from version numbers.

Every target requires a focused plan, implementation, regression tests, exact-SHA CI, evidence and a review PR. Never push to main, force-push, weaken a security gate, hide WPCS/Semgrep findings or claim a check passed on a different artifact. Preserve POS/cart, inventory/ledger, warehouse, finance, HR history, approval authority, schema/data and SOP boundaries.

Do not use production credentials/data or test against live WordPress/business databases. Keep DB ports internal and preview ports private. Do not activate an unapproved schema or HR/payroll pathway. Never invent Ethiopian statutory interpretation or employee compensation.

Report PASS, FAIL, BLOCKED, NOT RUN and OWNER INPUT REQUIRED distinctly. A successful Codespaces build is not ERP production acceptance.
