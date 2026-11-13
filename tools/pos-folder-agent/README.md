# Stockland branch POS folder agent

This agent is the second Daily POS input method. It runs on the **branch computer** that receives the third-party POS export and uploads the completed file to the ERP's existing Daily POS **preview/staging** endpoint.

It does not release sales, modify stock, post VAT/COGS/GL, or bypass the Branch Manager approval step.

## The three methods

1. **Manual upload** — a Sales Employee or Branch Manager selects the daily XLSX or CSV from Daily POS Control and clicks **VALIDATE & STAGE**.
2. **Branch folder** — this agent checks a configured local folder on the branch PC and stages stable daily files automatically. A Branch Manager still reviews the staged job and clicks **RELEASE TO ERP**.
3. **POS API** — reserved for a documented API from the POS supplier. There is no live supplier API configured by this agent today. When the supplier provides documentation and credentials, a provider adapter can normalize its response and call the same preview endpoint; it must not create a second sales-posting path.

## Branch PC requirements

- Python 3.10 or newer.
- HTTPS access to the Stockland ERP WordPress URL.
- One dedicated ERP **Sales Employee** account with access to that one branch only.
- A WordPress Application Password for that account. Do not use an Owner, WordPress administrator, or Branch Manager credential for the unattended uploader.
- A local POS export folder that the third-party POS software writes to.

## Configure one agent per branch

Copy stockland_pos_folder_agent.py to a stable directory on the branch PC. Create an inbox folder and a separate processed folder, for example:

~~~text
C:\StocklandPOS\agent\stockland_pos_folder_agent.py
C:\StocklandPOS\inbox\
C:\StocklandPOS\processed\
C:\StocklandPOS\state\
~~~

Set these environment variables for the Windows account that will run the scheduled task:

| Variable | Purpose |
|---|---|
| STOCKLAND_ERP_URL | Base WordPress site URL, for example https://your-erp.example |
| STOCKLAND_ERP_USERNAME | Dedicated Sales Employee username |
| STOCKLAND_ERP_APP_PASSWORD | WordPress Application Password; keep private |
| STOCKLAND_POS_BRANCH_ID | Exact ERP branch location ID assigned to that account |
| STOCKLAND_POS_INBOX | Folder where the POS software exports its completed daily files |
| STOCKLAND_POS_PROCESSED | Optional destination for successfully staged files |
| STOCKLAND_POS_STATE_DB | Optional local SQLite state file |
| STOCKLAND_POS_STABLE_SECONDS | Optional file-stability delay; default 60 |
| STOCKLAND_POS_MAX_BYTES | Optional upload cap; cannot exceed 8 MiB |
| STOCKLAND_POS_BUSINESS_DATE | Optional fallback date only when the filename has no date |

Keep the Application Password out of the repository, task arguments, shared folders, screenshots, and logs. Use Windows user-level environment variables or a protected local secret-management mechanism. Restrict the inbox, processed and state folders to the branch account.

## Schedule the upload

Use **Windows Task Scheduler** on the branch PC. Create a task that runs under the dedicated Sales Employee Windows account:

- Trigger: repeat every 5 minutes, indefinitely (or the shortest interval supported by local policy).
- Program: the installed python.exe path.
- Arguments: the full path to stockland_pos_folder_agent.py.
- Start in: the agent directory.
- Settings: start as soon as possible after a missed run; do not start a second instance if one is already running; stop a hung run after 10 minutes.
- Run with the same Windows user whose protected environment variables contain the ERP credentials.

First run the script manually with --dry-run. It lists eligible files without contacting the ERP. Then run it once without --dry-run and confirm the job appears in the ERP's Daily POS staged queue. Only after that verification should the scheduled task be enabled.

Do not configure a server cron job for a folder that exists only on a branch PC. The ERP cannot read a local branch drive from InfinityFree/shared hosting. The branch agent initiates an authenticated HTTPS upload; the ERP performs preview and validation within the request. If a server-side cron/queue is considered later, first verify that the hosting plan actually supports persistent scheduling, the required PHP runtime, and a server-accessible source file.

## File rules and duplicate protection

- Only XLSX and CSV files are considered.
- Files must be non-empty, within the 8 MiB ERP upload limit, and unchanged for at least the stability delay.
- The filename should contain the business date as YYYY-MM-DD, e.g. POS-GORO-2026-11-07.xlsx.
- A missing or invalid date is not guessed.
- Successfully staged files move to the processed folder; rejected files remain in inbox.
- Local state is keyed by branch + business date + SHA-256. Existing local unscoped state records are migrated without deleting the original state table.
- The ERP remains the authoritative duplicate guard, including branch/date/file hashes and duplicate summary-day protection.

A folder upload returning READY means rows are staged and eligible for manager review. REVIEW means the manager or source owner must resolve the displayed row-level issue. Neither status means the sales have posted.

## API mode acceptance contract

Do not configure an API adapter until the supplier provides the real API documentation and test credentials. The adapter must establish:

- HTTPS base URL, authentication method, credential rotation and expiry;
- endpoint, pagination, rate limits, retry rules and error semantics;
- exact business-date/time-zone rules and the supplier's completed/void/issued-state filters;
- source item code, quantity, average/unit price, line total, VAT/tax and receipt fields;
- stable source IDs or idempotency keys, plus a reconciliation report for totals;
- sandbox/test environment and a provider version contract.

After normalization, the adapter must submit to the **same** ERP preview/staging service and preserve branch-level authorization and Branch Manager release. There is no assumed API, no fabricated endpoint, and no automatic posting.

## Memory and execution model

The PHP workbook reader is bounded and streams worksheet rows using ZIP/XML streaming instead of loading a complete worksheet XML document into PHP memory. The current guard rails include an 8 MiB upload cap, workbook ZIP-entry and expansion limits, worksheet XML limits, a physical-row cap, and a 5,000-row import cap. The branch agent itself streams SHA-256 calculation in 1 MiB chunks and does not parse the whole workbook in memory.

These are code-level controls, not proof of a particular peak-memory figure on every shared host. The actual production host still needs a representative workbook benchmark that captures PHP peak memory, request duration, upload limits and successful preview completion.

## Operational ownership

- Sales Employee: upload/stage their assigned branch file.
- Branch Manager: verify the date, branch, item mapping, row counts, source total and any warnings; then release the staged job.
- Management / Owner: maintain POS-code-to-live-SKU mapping and resolve master-data gaps.
- Daily Closing: reconcile released sales, VAT, cash/payment records and ledger totals with the source report.
