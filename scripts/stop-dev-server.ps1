param(
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

$listeners = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
    Select-Object -ExpandProperty OwningProcess -Unique

if (-not $listeners) {
    Write-Output "No bandPromo dev server is listening on port $Port."
    exit 0
}

foreach ($processId in $listeners) {
    Stop-Process -Id $processId -Force
}

Write-Output "Stopped bandPromo dev server on port $Port."