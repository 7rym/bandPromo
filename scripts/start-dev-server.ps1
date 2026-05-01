param(
    [int]$Port = 8000,
    [string]$BindHost = "localhost",
    [string]$Timezone = "Europe/Oslo"
)

$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
$listener = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
    Select-Object -First 1

if ($null -ne $listener) {
    Stop-Process -Id $listener.OwningProcess -Force
}

Set-Location -LiteralPath $repoRoot
& php "-d" "date.timezone=$Timezone" "-S" "$BindHost`:$Port"