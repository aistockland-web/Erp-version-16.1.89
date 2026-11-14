# Stockland Daily POS Folder Pickup (Windows branch PC)

This is Option 2 for the Daily POS workflow. It is a **branch-PC agent**, not a web-server cron job. It watches a local Incoming folder and submits each supported CSV/XLSX file to the exact same Stockland REST preview/staging endpoint used by the manual upload.

## Safety and workflow

- Use a dedicated WordPress **Sales Employee** account assigned to exactly one branch and permitted to upload/stage Daily POS data. Do not use an Owner or Branch Manager credential in the agent.
- The agent sends the authenticated file to `/wp-json/stockland/v16/import/daily-sales/preview`.
- It sends neither a branch override nor a business-date override. The ERP derives branch scope from the assigned user's authenticated account and detects the business date from supported reports.
- The agent does **not** call the commit/release endpoint. It cannot post sales, reduce inventory or create journals.
- READY/duplicate files move to `Submitted`; REVIEW files move to `NeedsReview`; permanent HTTP 4xx errors move to `NeedsAttention`; network/5xx errors stay in `Incoming` for retry.
- The agent waits five seconds after the last file write before upload, to avoid reading a report while the POS program is still writing it. Processed file moves include a unique suffix to avoid evidence-name collisions.
- Duplicate detection and release authorization remain server-side. A Branch Manager must review the staged ERP job and use the normal **RELEASE TO ERP** action.

## Install

1. On the branch Windows PC, install PowerShell 7.4 or later.
2. In ERP, create a dedicated Sales Employee account, assign only the correct branch and create a WordPress Application Password for it.
3. Open PowerShell 7 and run:

   ```powershell
   Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
   .\Install-StocklandPosFolderAgent.ps1 -SiteUrl "https://YOUR-ERP-DOMAIN"
   ```

4. Enter the dedicated branch account name and Application Password when prompted.
5. Put original branch POS reports into:

   ```text
   %LOCALAPPDATA%\StocklandPOS\Incoming
   ```

The installer stores the Application Password protected by Windows DPAPI under the signed-in Windows account. The scheduled task runs every minute as that same interactive user. Keep the PC signed in and connected to the Internet. The agent log is `%LOCALAPPDATA%\StocklandPOS\agent.log`.

## File handling

- `Submitted`: server accepted the file and returned an existing or newly staged job.
- `NeedsReview`: ERP staged the file as REVIEW; the Branch Manager should open that job and reconcile/correct it.
- `NeedsAttention`: account, scope, file-size, date, closed-day or validation request failed permanently; review the log.
- `Incoming`: temporary connection or server errors are retried at the next scheduled pass.

Do not rename or edit the source report to force acceptance. Preserve the POS export as evidence. The current supported source adapter recognizes the CNET Cash Sales Voucher report summarized by article and common mapped CSV/XLSX exports.

## Limits

- HTTPS is mandatory.
- Maximum file size enforced by this agent is 15 MB. The ERP remains the final upload validator.
- This agent requires an interactive Windows session and is not an InfinityFree-hosted background service.
- The future supplier API option remains unconnected until the POS supplier provides official API documentation, authentication details, test credentials and owner approval.
