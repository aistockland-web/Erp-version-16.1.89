#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Run this command from the Stockland ERP Git workspace." >&2
  exit 2
fi

if printenv OPENAI_API_KEY >/dev/null 2>&1; then
  echo "This project uses ChatGPT sign-in, not API-key billing. Unset OPENAI_API_KEY and retry." >&2
  exit 2
fi

export PATH="$HOME/.local/bin:$PATH"
if ! command -v codex >/dev/null 2>&1; then
  bash scripts/codespaces/bootstrap_codex.sh
  export PATH="$HOME/.local/bin:$PATH"
fi
if ! command -v codex >/dev/null 2>&1; then
  echo "Codex CLI is not installed. Check npm access and rerun the bootstrap." >&2
  exit 2
fi

test -s AGENTS.md
test -s docs/AGENT_FIRST_PROMPT.md
test -s docs/engineering/STOCKLAND_ERP_FINAL_CODESPACES_ONE_CLICK_RELEASE_RUNBOOK_2026-11-11.md
test -s .codex/config.toml

echo "=== Stockland ERP final-completion agent ==="
echo "Repository: $ROOT"
echo "Branch: $(git branch --show-current)"
echo "HEAD: $(git rev-parse HEAD)"
echo "Working tree (preserve all existing edits):"
git status --short --branch
echo
echo "Codex: $(codex --version 2>&1 | head -n 1)"
echo "Sandbox: workspace-write; approvals: on-request; API-key billing: disabled."
echo "Do not approve live-data, production deployment, force-push or release-publication actions."

PROMPT="$(cat <<'PROMPT_EOF'
Continue the existing Stockland Ethiopia ERP to its full, cumulative, end-to-end delivery goal. Do not restart, replace the framework, create a demo, or reduce scope.

First read AGENTS.md, docs/AGENT_FIRST_PROMPT.md, the full Codespaces release runbook, the project history/lineage documents, and the latest accessible Notion Target 6 / release decisions. Refresh Git refs, PR #84 and #89, current Actions, artifacts and exact package hashes. Preserve every uncommitted or untracked change. Never hard-reset, git-clean, force-push, overwrite the editable source, or work in a disposable WordPress copy as canonical source.

Use the existing specialized Codex agents: stockland_explorer (read-only), stockland_implementer (workspace-write), stockland_qa (isolated tests), and stockland_reviewer (read-only). Work sequentially under the project's one-thread Free-plan setting. Do not stop after a plan or audit. For each target, reproduce, fix, add regression tests, run exact checks, commit to a dedicated review branch, and record exact evidence. Continue to the next safe target until technical work is exhausted or a genuinely Owner/legal/credential prerequisite blocks only its dependent function.

Immediate target: reconcile the existing qa/stockland-resumable-schema-e2e-2026-11-11 branch against the current delivery line without merging it wholesale. Inspect its setup runner, schema fingerprint and database scripts. The current implementation's Stockland_HR::table_keys() combines four legacy HR/payroll keys with the unapproved HR lifecycle extension; the setup status/advance route has a separate HR approval boundary. Make the acceptance logic module-scoped. On disposable MariaDB 10.11 and MySQL 8.0.46 databases, run the actual bounded authenticated setup wizard for the approved Core and Finance work, capture before/after SQL snapshots and full physical fingerprints (tables, columns/defaults/nullability, indexes, foreign keys, engine, and safe row-count observations), verify idempotent retries and non-destructive upgrade behavior, and stop cleanly at the HR approval boundary. Never send approve_hr_schema, never invent an approval reason, never auto-create the 37 HR lifecycle tables, and never ALTER existing legacy HR/payroll tables without exact written Owner approval tied to the reviewed DDL/data fingerprint. Report the four legacy keys separately; do not mistake lifecycle gaps for global ERP failure.

Keep the project-wide engineering status GO and make module readiness explicit. Preserve all current operational work: Central Warehouse, Goro, Gerji, Mexico, Megenagna, Product Master/SKU, retail/wholesale POS/cart, inventory, receiving, reservations, requisitions, transfers, returns, physical counts, procurement, AP/AR, expenses, cash/bank, GL/PLEDGER, reports, SOP resources, responsive UI/PWA, HR lifecycle and secure private-document workflows. Test positive/negative role, capability and branch/location access, maker-checker/no-self-approval, idempotency, retries, concurrency and ledger/stock reconciliation. Keep all fixtures synthetic and isolated.

Payroll finalization, statutory payment/remittance, SOP approval, HR lifecycle schema changes, and live operations remain fail-closed where required official Ethiopian sources, qualified review, process-owner approval, or exact Owner authorization are missing. Do not invent rates, employee data, approved SOPs, or release approvals. Do not disable quality/security gates to get green CI.

After completing all technical targets, build exactly one cumulative WordPress plugin ZIP from the final canonical source commit; validate its manifest, per-file SHA-256, SBOM, package hash, fresh install, upgrade, rollback, responsive browser/PWA, security, privacy and backup/restore. Publish it as a downloadable CI artifact only after the named technical gates pass. Do not merge PRs, create a production tag/GitHub Release, install on the live site, or deploy without the applicable final Owner release authorization. A green package smoke test is not full production acceptance.

For every target report PASS, FAIL, BLOCKED, NOT RUN or OWNER INPUT with exact branch/SHA, command, run/job ID, artifact/hash, and next blocking prerequisite. Keep working on safe engineering tasks rather than returning an optimistic completion claim.
PROMPT_EOF
)"

exec codex --cd "$ROOT" --sandbox workspace-write --ask-for-approval on-request "$PROMPT"
