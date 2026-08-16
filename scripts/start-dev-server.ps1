param(
    [int]$Port = 8000,
    [string]$BindHost = "127.0.0.1",
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
# Large PRP imports (e.g. Spandexual ~380MB) need upload/post ceilings above the
# PHP defaults (2M/8M). Hosted installs use biblioteca/templates/runtime/user.ini.
$argumentList = @(
    "-d", "date.timezone=$Timezone",
    "-d", "max_execution_time=0",
    "-d", "max_input_time=0",
    "-d", "memory_limit=1024M",
    "-d", "upload_max_filesize=512M",
    "-d", "post_max_size=520M",
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
