$ErrorActionPreference = 'Stop'
$agent = Join-Path $PSScriptRoot '..\Run-StocklandPosFolderAgent.ps1'
. $agent

# Normal preview responses do not include the optional existing_job field.
$script:MockResult = @{ job_id = 501; status = 'READY' }
$script:MockHttpStatus = 0
$script:LastUri = $null
$script:LastForm = $null
$script:LastAuthorization = $null

function Invoke-RestMethod {
    param($Uri, $Method, $Headers, $Form, $TimeoutSec)
    $script:LastUri = [string]$Uri
    $script:LastForm = $Form
    $script:LastAuthorization = [string]$Headers.Authorization
    if ($script:MockHttpStatus -gt 0) {
        $failure = [System.Net.Http.HttpRequestException]::new('Synthetic HTTP failure for test', $null, [System.Net.HttpStatusCode]$script:MockHttpStatus)
        throw $failure
    }
    return $script:MockResult
}

function Assert-True {
    param([bool]$Condition, [string]$Message)
    if (-not $Condition) { throw "ASSERTION FAILED: $Message" }
}

$temp = Join-Path ([System.IO.Path]::GetTempPath()) ('stockland-agent-test-' + [Guid]::NewGuid().ToString('N'))
$folders = @{
    Incoming       = Join-Path $temp 'Incoming'
    Submitted      = Join-Path $temp 'Submitted'
    NeedsReview    = Join-Path $temp 'NeedsReview'
    NeedsAttention = Join-Path $temp 'NeedsAttention'
}
foreach ($folder in $folders.Values) { New-Item -ItemType Directory -Path $folder -Force | Out-Null }
$secure = ConvertTo-SecureString 'synthetic-application-password' -AsPlainText -Force
$config = @{
    SiteUrl      = 'https://erp.example.test'
    UserName     = 'branch.sales'
    PasswordBlob = ($secure | ConvertFrom-SecureString)
    StableSeconds = 5
    Folders      = $folders
}
$script:ConfigPath = Join-Path $temp 'config.json'
try {
    $writingPath = Join-Path $folders.Incoming 'still-writing.xlsx'
    Set-Content -LiteralPath $writingPath -Value 'fixture' -Encoding UTF8
    $waiting = Invoke-StocklandFolderAgent -Config $config
    Assert-True ($waiting.Submitted -eq 0) 'A file modified within the five-second settle window must not be uploaded while it may still be writing.'
    Assert-True (Test-Path -LiteralPath $writingPath) 'A file still being written must remain in Incoming.'
    [System.IO.File]::SetLastWriteTimeUtc($writingPath, [DateTime]::UtcNow.AddSeconds(-10))
    $script:MockResult = @{ job_id = 500; status = 'READY' }
    $settled = Invoke-StocklandFolderAgent -Config $config
    Assert-True ($settled.Submitted -eq 1) 'A settled source file must upload and stage normally.'
    Remove-Item -LiteralPath (Join-Path $folders.Submitted (Get-ChildItem -LiteralPath $folders.Submitted -File | Select-Object -First 1).Name) -Force

    Set-Content -LiteralPath (Join-Path $folders.Incoming 'daily.xlsx') -Value 'fixture' -Encoding UTF8
    [System.IO.File]::SetLastWriteTimeUtc((Join-Path $folders.Incoming 'daily.xlsx'), [DateTime]::UtcNow.AddSeconds(-10))
    $result = Invoke-StocklandFolderAgent -Config $config
    Assert-True ($result.Submitted -eq 1) 'READY file must be staged and moved to Submitted.'
    Assert-True ($script:LastUri -eq 'https://erp.example.test/wp-json/stockland/v16/import/daily-sales/preview') 'Agent must call the existing Daily POS preview endpoint.'
    Assert-True ($script:LastForm.ContainsKey('file')) 'Multipart request must contain the original file field.'
    Assert-True (-not $script:LastForm.ContainsKey('branch_location_id')) 'Agent must not override the authenticated branch assignment.'
    Assert-True (-not $script:LastForm.ContainsKey('business_date')) 'Agent must let the ERP detect the report date.'
    Assert-True ($script:LastAuthorization.StartsWith('Basic ')) 'Application Password must be sent over HTTPS Basic authentication.'
    Assert-True (@(Get-ChildItem -LiteralPath $folders.Submitted -File).Count -eq 1) 'Submitted report must be retained in its evidence folder.'

    $script:MockResult = @{ job_id = 502; status = 'REVIEW'; existing_job = $false }
    Set-Content -LiteralPath (Join-Path $folders.Incoming 'review.xlsx') -Value 'fixture' -Encoding UTF8
    [System.IO.File]::SetLastWriteTimeUtc((Join-Path $folders.Incoming 'review.xlsx'), [DateTime]::UtcNow.AddSeconds(-10))
    $review = Invoke-StocklandFolderAgent -Config $config
    Assert-True ($review.Review -eq 1) 'REVIEW response must be separated for Branch Manager review.'
    Assert-True (@(Get-ChildItem -LiteralPath $folders.NeedsReview -File).Count -eq 1) 'Review file must move to NeedsReview.'

    $script:MockHttpStatus = 503
    Set-Content -LiteralPath (Join-Path $folders.Incoming 'retry.xlsx') -Value 'fixture' -Encoding UTF8
    [System.IO.File]::SetLastWriteTimeUtc((Join-Path $folders.Incoming 'retry.xlsx'), [DateTime]::UtcNow.AddSeconds(-10))
    $retry = Invoke-StocklandFolderAgent -Config $config
    Assert-True ($retry.RetainedForRetry -eq 1) 'Transient HTTP 503 must keep the original file in Incoming.'
    Assert-True (@(Get-ChildItem -LiteralPath $folders.Incoming -File).Count -eq 1) 'Transient file must remain available for retry.'
    # Isolate the permanent-403 assertion from the retained transient-503 fixture.
    Remove-Item -LiteralPath (Join-Path $folders.Incoming 'retry.xlsx') -Force

    $script:MockHttpStatus = 403
    Set-Content -LiteralPath (Join-Path $folders.Incoming 'forbidden.xlsx') -Value 'fixture' -Encoding UTF8
    [System.IO.File]::SetLastWriteTimeUtc((Join-Path $folders.Incoming 'forbidden.xlsx'), [DateTime]::UtcNow.AddSeconds(-10))
    $attention = Invoke-StocklandFolderAgent -Config $config
    Assert-True ($attention.Attention -eq 1) 'Permanent HTTP 403 must be escalated, not retried forever.'
    Assert-True (@(Get-ChildItem -LiteralPath $folders.NeedsAttention -File).Count -eq 1) 'Forbidden file must move to NeedsAttention.'

    Write-Output 'RESULT PASS: READY staging, REVIEW routing, HTTPS endpoint, no branch/date override, retry retention and permanent-error handling.'
}
finally {
    Remove-Item -LiteralPath $temp -Recurse -Force -ErrorAction SilentlyContinue
}
