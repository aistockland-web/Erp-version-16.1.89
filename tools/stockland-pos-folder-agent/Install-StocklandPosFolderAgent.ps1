[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$SiteUrl
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if ($env:OS -ne 'Windows_NT') {
    throw 'The Stockland branch folder agent installer requires Windows.'
}
if ($PSVersionTable.PSVersion.Major -lt 7) {
    throw 'Install PowerShell 7.4 or later (pwsh) before configuring the agent.'
}
$site = [Uri]$SiteUrl
if (-not $site.IsAbsoluteUri -or $site.Scheme -ne 'https') {
    throw 'Enter the HTTPS address of the Stockland ERP website.'
}

$root = Join-Path $env:LOCALAPPDATA 'StocklandPOS'
$agentFolder = Join-Path $root 'Agent'
$folders = @{
    Incoming      = Join-Path $root 'Incoming'
    Submitted     = Join-Path $root 'Submitted'
    NeedsReview   = Join-Path $root 'NeedsReview'
    NeedsAttention= Join-Path $root 'NeedsAttention'
}
New-Item -ItemType Directory -Path $agentFolder -Force | Out-Null
foreach ($folder in $folders.Values) { New-Item -ItemType Directory -Path $folder -Force | Out-Null }

$credential = Get-Credential -Message 'Enter the dedicated Stockland branch Sales Employee WordPress username and Application Password. The account must be assigned to this branch and must not have release authority.'
if (-not $credential) { throw 'Branch account credentials are required.' }
$encrypted = $credential.Password | ConvertFrom-SecureString
$config = @{
    SiteUrl      = $site.AbsoluteUri.TrimEnd('/')
    UserName     = $credential.UserName
    PasswordBlob = $encrypted
    StableSeconds = 5
    Folders      = $folders
}
$configPath = Join-Path $root 'config.json'
$config | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $configPath -Encoding UTF8

$runnerSource = Join-Path $PSScriptRoot 'Run-StocklandPosFolderAgent.ps1'
$runnerTarget = Join-Path $agentFolder 'Run-StocklandPosFolderAgent.ps1'
Copy-Item -LiteralPath $runnerSource -Destination $runnerTarget -Force

# The task runs as the currently signed-in branch Windows user. DPAPI-protected
# credentials cannot be decrypted by another Windows account or a server process.
$taskName = 'Stockland Daily POS Folder Pickup'
$action = 'pwsh.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy RemoteSigned -File "' + $runnerTarget + '" -ConfigPath "' + $configPath + '"'
& schtasks.exe /Create /SC MINUTE /MO 1 /TN $taskName /TR $action /IT /F | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Windows Task Scheduler could not create the one-minute folder pickup task.'
}

Write-Host 'Stockland Daily POS folder pickup is configured.'
Write-Host ('Incoming folder: ' + $folders.Incoming)
Write-Host ('Submitted files: ' + $folders.Submitted)
Write-Host ('Manager review: ' + $folders.NeedsReview)
Write-Host 'The agent only uploads/stages. It cannot release sales, inventory or finance.'
Write-Host 'Keep the branch PC signed in and connected. Files remain local; credentials are DPAPI-protected for this Windows user.'
