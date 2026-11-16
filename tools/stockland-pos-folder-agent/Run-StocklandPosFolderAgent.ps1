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
    if ([string]::IsNullOrWhiteSpace($EncryptedPassword)) {
        throw 'The branch POS agent has no configured WordPress Application Password.'
    }
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
        $folder = [string]$folders[$name]
        if ([string]::IsNullOrWhiteSpace($folder)) {
            throw "The configured folder '$name' is missing."
        }
        if (-not (Test-Path -LiteralPath $folder)) {
            New-Item -ItemType Directory -Path $folder -Force | Out-Null
        }
    }

    $logRoot = Split-Path -Parent ([string]$folders['Incoming'])
    $logPath = Join-Path $logRoot 'agent.log'

    # Validate the destination before decrypting credentials or making a network request.
    $base = [string]$Config.SiteUrl
    try {
        $site = [Uri]::new($base, [UriKind]::Absolute)
        $null = $site.Port
    }
    catch {
        throw 'The ERP endpoint must be a valid HTTPS base URL.'
    }
    if ($base -ne $base.Trim() -or
        -not $site.IsAbsoluteUri -or
        $site.Scheme -ne 'https' -or
        [string]::IsNullOrWhiteSpace($site.Host) -or
        -not [string]::IsNullOrEmpty($site.UserInfo) -or
        -not [string]::IsNullOrEmpty($site.Query) -or
        -not [string]::IsNullOrEmpty($site.Fragment)) {
        throw 'The ERP endpoint must use HTTPS and cannot contain embedded credentials, query strings or fragments.'
    }

    $base = $site.AbsoluteUri.TrimEnd('/')
    $endpoint = $base + '/wp-json/stockland/v16/import/daily-sales/preview'

    $deviceId = ''
    if ($Config.ContainsKey('PosDeviceId')) {
        $deviceId = ([string]$Config.PosDeviceId).Trim().ToUpperInvariant()
    }
    if ($deviceId -ne '' -and ($deviceId.Length -ne 16 -or $deviceId -match '[^A-F0-9]')) {
        throw 'Configured POS device ID must be a 16-character hexadecimal ID copied from the Stockland POS Integration Registry.'
    }

    # The Windows account's DPAPI context protects this Application Password.
    $password = $null
    $rawAuth = [byte[]]@()
    try {
        $password = Get-StocklandAgentPassword -EncryptedPassword ([string]$Config.PasswordBlob)
        $rawAuth = [System.Text.Encoding]::UTF8.GetBytes(([string]$Config.UserName) + ':' + $password)
        $headers = @{ Authorization = 'Basic ' + [Convert]::ToBase64String($rawAuth) }
    }
    finally {
        $password = $null
        if ($rawAuth.Length -gt 0) {
            [Array]::Clear($rawAuth, 0, $rawAuth.Length)
        }
    }

    $counts = @{ Submitted = 0; Review = 0; Attention = 0; RetainedForRetry = 0 }
    $stableSeconds = if ($null -ne $Config.StableSeconds) { [Math]::Max(0, [int]$Config.StableSeconds) } else { 5 }
    $files = @(
        Get-ChildItem -LiteralPath $folders['Incoming'] -File |
            Where-Object { $_.Extension.ToLowerInvariant() -in @('.xlsx', '.csv') } |
            Sort-Object LastWriteTimeUtc, Name
    )

    foreach ($file in $files) {
        try {
            if ($stableSeconds -gt 0 -and $file.LastWriteTimeUtc -gt [DateTime]::UtcNow.AddSeconds(-$stableSeconds)) {
                Write-StocklandAgentLog -Path $logPath -Message "Waiting for source file to finish writing: $($file.Name)."
                continue
            }
            if ($file.Length -gt 15MB) {
                Write-StocklandAgentLog -Path $logPath -Message "NeedsAttention: $($file.Name) exceeds the 15 MB agent safety limit."
                Move-StocklandAgentFile -File $file -DestinationFolder $folders['NeedsAttention']
                $counts.Attention++
                continue
            }

            # The agent never sends a branch override or calls the commit/release endpoint.
            # The ERP derives branch scope from this dedicated Sales Employee account.
            $formData = @{ file = $file }
            if ($deviceId -ne '') {
                # This reference selects only the per-device product-code mapping namespace.
                # It is not device authentication; the ERP still authenticates the uploader.
                $formData['pos_device_id'] = $deviceId
            }

            $response = Invoke-RestMethod -Uri $endpoint -Method Post -Headers $headers -Form $formData -TimeoutSec 120 -MaximumRedirection 0
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
                Move-StocklandAgentFile -File $file -DestinationFolder $folders['Submitted']
                $counts.Submitted++
            }
            elseif ($status -eq 'REVIEW') {
                Write-StocklandAgentLog -Path $logPath -Message "NeedsReview: $($file.Name); job #$($response.job_id). Manager review is required; nothing was released."
                Move-StocklandAgentFile -File $file -DestinationFolder $folders['NeedsReview']
                $counts.Review++
            }
            else {
                Write-StocklandAgentLog -Path $logPath -Message "NeedsAttention: $($file.Name); unexpected ERP response status '$status'."
                Move-StocklandAgentFile -File $file -DestinationFolder $folders['NeedsAttention']
                $counts.Attention++
            }
        }
        catch {
            $httpCode = Get-StocklandHttpStatusCode -ErrorRecord $_
            # Invalid credentials, branch scope, input and permanent provider errors
            # are escalated. Timeout/rate-limit/5xx responses are retained for retry.
            if ($httpCode -ge 300 -and $httpCode -lt 500 -and $httpCode -notin @(408, 425, 429)) {
                Write-StocklandAgentLog -Path $logPath -Message "NeedsAttention: $($file.Name); ERP HTTP $httpCode. Check user role, branch assignment, file format or closed business day."
                Move-StocklandAgentFile -File $file -DestinationFolder $folders['NeedsAttention']
                $counts.Attention++
            }
            else {
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
    if ($result.RetainedForRetry -gt 0) {
        exit 2
    }
    exit 0
}
