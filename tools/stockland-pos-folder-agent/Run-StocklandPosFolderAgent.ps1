[CmdletBinding()]
param(
    [string]$ConfigPath = (Join-Path $env:LOCALAPPDATA 'StocklandPOS\config.json')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Write-StocklandAgentLog {
    param([string]$Path, [string]$Message)
    $stamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Add-Content -LiteralPath $Path -Value "[$stamp] $Message" -Encoding UTF8
}

function Get-StocklandAgentPassword {
    param([string]$EncryptedPassword)
    $secure = ConvertTo-SecureString -String $EncryptedPassword
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
        $secure.Dispose()
    }
}

function Get-StocklandHttpStatusCode {
    param([System.Management.Automation.ErrorRecord]$ErrorRecord)

    $exception = $ErrorRecord.Exception
    for ($depth = 0; $null -ne $exception -and $depth -lt 6; $depth++) {
        try {
            if ($null -ne $exception.StatusCode) {
                return [int]$exception.StatusCode
            }
        }
        catch { }
        try {
            if ($exception.Data -is [System.Collections.IDictionary] -and $exception.Data.Contains('HttpStatusCode')) {
                return [int]$exception.Data['HttpStatusCode']
            }
        }
        catch { }
        try {
            if ($null -ne $exception.Response -and $null -ne $exception.Response.StatusCode) {
                return [int]$exception.Response.StatusCode
            }
        }
        catch { }
        $exception = $exception.InnerException
    }
    return 0
}

function Move-StocklandAgentFile {
    param([System.IO.FileInfo]$File, [string]$DestinationFolder)
    if (-not (Test-Path -LiteralPath $DestinationFolder)) {
        New-Item -ItemType Directory -Path $DestinationFolder -Force | Out-Null
    }
    $suffix = Get-Date -Format 'yyyyMMdd-HHmmss'
    $destination = Join-Path $DestinationFolder ($File.BaseName + '-' + $suffix + '-' + [Guid]::NewGuid().ToString('N') + $File.Extension)
    Move-Item -LiteralPath $File.FullName -Destination $destination -Force
}

function Invoke-StocklandFolderAgent {
    [CmdletBinding()]
    param([Parameter(Mandatory)][hashtable]$Config)

    $folders = $Config.Folders
    foreach ($name in @('Incoming', 'Submitted', 'NeedsReview', 'NeedsAttention')) {
        if (-not (Test-Path -LiteralPath $folders[$name])) {
            New-Item -ItemType Directory -Path $folders[$name] -Force | Out-Null
        }
    }
    $logPath = Join-Path (Split-Path -Parent $ConfigPath) 'agent.log'
    $base = [string]$Config.SiteUrl
    $endpoint = $base.TrimEnd('/') + '/wp-json/stockland/v16/import/daily-sales/preview'
    if ($base -notmatch '^https://') {
        throw 'The ERP endpoint must use HTTPS.'
    }

    $password = Get-StocklandAgentPassword -EncryptedPassword ([string]$Config.PasswordBlob)
    $rawAuth = [byte[]]@()
    try {
        $rawAuth = [System.Text.Encoding]::UTF8.GetBytes(([string]$Config.UserName) + ':' + $password)
        $headers = @{ Authorization = 'Basic ' + [Convert]::ToBase64String($rawAuth) }
    }
    finally {
        $password = $null
        if ($rawAuth) { [Array]::Clear($rawAuth, 0, $rawAuth.Length) }
    }

    $counts = @{ Submitted = 0; Review = 0; Attention = 0; RetainedForRetry = 0 }
    $files = @(Get-ChildItem -LiteralPath $folders.Incoming -File | Where-Object { $_.Extension.ToLowerInvariant() -in @('.xlsx', '.csv') } | Sort-Object LastWriteTimeUtc, Name)
    $stableSeconds = if ($null -ne $Config.StableSeconds) { [Math]::Max(0, [int]$Config.StableSeconds) } else { 5 }
    foreach ($file in $files) {
        try {
            if ($stableSeconds -gt 0 -and $file.LastWriteTimeUtc -gt [DateTime]::UtcNow.AddSeconds(-$stableSeconds)) {
                Write-StocklandAgentLog -Path $logPath -Message "Waiting for source file to finish writing: $($file.Name)."
                continue
            }
            if ($file.Length -gt 15MB) {
                Write-StocklandAgentLog -Path $logPath -Message "NeedsAttention: $($file.Name) exceeds the 15 MB agent safety limit."
                Move-StocklandAgentFile -File $file -DestinationFolder $folders.NeedsAttention
                $counts.Attention++
                continue
            }
            # The server derives the branch from this dedicated user's assigned branch
            # and detects the business date from supported POS reports. This agent never
            # sends a branch override and never calls the commit/release endpoint.
            $response = Invoke-RestMethod -Uri $endpoint -Method Post -Headers $headers -Form @{ file = $file } -TimeoutSec 120
            $status = [string]$response.status
            $isExistingJob = $false
            if ($response -is [System.Collections.IDictionary]) {
                $isExistingJob = $response.Contains('existing_job') -and [bool]$response['existing_job']
            }
            elseif ($null -ne $response -and $null -ne $response.PSObject.Properties['existing_job']) {
                $isExistingJob = [bool]$response.existing_job
            }
            if ($isExistingJob -or $status -in @('READY', 'PARTIAL', 'POSTED')) {
                Write-StocklandAgentLog -Path $logPath -Message "Submitted/staged: $($file.Name); job #$($response.job_id); status $status."
                Move-StocklandAgentFile -File $file -DestinationFolder $folders.Submitted
                $counts.Submitted++
            }
            elseif ($status -eq 'REVIEW') {
                Write-StocklandAgentLog -Path $logPath -Message "NeedsReview: $($file.Name); job #$($response.job_id). Manager review is required; nothing was released."
                Move-StocklandAgentFile -File $file -DestinationFolder $folders.NeedsReview
                $counts.Review++
            }
            else {
                Write-StocklandAgentLog -Path $logPath -Message "NeedsAttention: $($file.Name); unexpected ERP response status '$status'."
                Move-StocklandAgentFile -File $file -DestinationFolder $folders.NeedsAttention
                $counts.Attention++
            }
        }
        catch {
            $httpCode = Get-StocklandHttpStatusCode -ErrorRecord $_
            if ($httpCode -ge 400 -and $httpCode -lt 500) {
                Write-StocklandAgentLog -Path $logPath -Message "NeedsAttention: $($file.Name); ERP HTTP $httpCode. Check user role, branch assignment, file format or closed business day."
                Move-StocklandAgentFile -File $file -DestinationFolder $folders.NeedsAttention
                $counts.Attention++
            }
            else {
                # Transient network/5xx errors leave the file in Incoming for the next
                # scheduled pass. The server's source hash prevents duplicate posting.
                Write-StocklandAgentLog -Path $logPath -Message "Retry later: $($file.Name); transport/ERP error. File retained in Incoming."
                $counts.RetainedForRetry++
            }
        }
    }
    return [pscustomobject]$counts
}

if ($MyInvocation.InvocationName -ne '.') {
    if (-not (Test-Path -LiteralPath $ConfigPath)) {
        throw "Agent configuration was not found at $ConfigPath. Run Install-StocklandPosFolderAgent.ps1 first."
    }
    $configuration = Get-Content -LiteralPath $ConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json -AsHashtable
    $result = Invoke-StocklandFolderAgent -Config $configuration
    if ($result.RetainedForRetry -gt 0) { exit 2 }
    exit 0
}
