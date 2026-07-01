param(
    [int]$Port = 8000,
    [string]$BindHost = "localhost",
    [string]$Timezone = "Europe/Oslo"
)

$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
$logDir = Join-Path $repoRoot "log"
$serverLog = Join-Path $logDir "dev-server.log"

$listener = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
    Select-Object -First 1

if ($null -ne $listener) {
    Stop-Process -Id $listener.OwningProcess -Force
}

if (-not (Test-Path -LiteralPath $logDir)) {
    New-Item -ItemType Directory -Path $logDir | Out-Null
}

$php = (Get-Command php -ErrorAction Stop).Source
$argumentList = @(
    "-d", "date.timezone=$Timezone",
    "-S", "${BindHost}:$Port"
)

Start-Process `
    -FilePath $php `
    -ArgumentList $argumentList `
    -WorkingDirectory $repoRoot `
    -WindowStyle Hidden `
    -RedirectStandardOutput $serverLog | Out-Null

Start-Sleep -Milliseconds 750

$listener = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
    Select-Object -First 1

if ($null -eq $listener) {
    $tail = ""
    if (Test-Path -LiteralPath $serverLog) {
        $tail = (Get-Content -LiteralPath $serverLog -Tail 8 -ErrorAction SilentlyContinue) -join [Environment]::NewLine
    }
    if ($tail -ne "") {
        Write-Error "Dev server failed to start on port $Port.`n$tail"
    } else {
        Write-Error "Dev server failed to start on port $Port."
    }
    exit 1
}

Write-Output "bandPromo dev server running at http://${BindHost}:$Port (log: log/dev-server.log)"
