[CmdletBinding()]
param(
    [int]$TodoLimit = 4,
    [int]$ChangelogLimit = 3
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-CommandVersionLine {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Command,

        [Parameter(Mandatory = $true)]
        [string[]]$Arguments
    )

    $commandInfo = Get-Command $Command -ErrorAction SilentlyContinue
    if (-not $commandInfo) {
        return "${Command}: not found"
    }

    try {
        $output = & $Command @Arguments 2>$null | Select-Object -First 1
        if ([string]::IsNullOrWhiteSpace([string]$output)) {
            return "${Command}: available"
        }

        return [string]$output
    }
    catch {
        return "${Command}: available"
    }
}

function Get-WorkspaceTaskLabels {
    param(
        [Parameter(Mandatory = $true)]
        [string]$TasksFilePath
    )

    if (-not (Test-Path -LiteralPath $TasksFilePath)) {
        return @()
    }

    try {
        $tasksConfig = Get-Content -LiteralPath $TasksFilePath -Raw -Encoding UTF8 | ConvertFrom-Json
        if (-not $tasksConfig.tasks) {
            return @()
        }

        return @($tasksConfig.tasks | ForEach-Object { [string]$_.label } | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
    }
    catch {
        return @()
    }
}

function Get-TodoSummary {
    param(
        [Parameter(Mandatory = $true)]
        [string]$TodoPath,

        [Parameter(Mandatory = $true)]
        [int]$Limit
    )

    $result = [ordered]@{
        CurrentTarget = $null
        Items = @()
        FirstOpen = $null
    }

    if (-not (Test-Path -LiteralPath $TodoPath)) {
        return [pscustomobject]$result
    }

    $lines = Get-Content -LiteralPath $TodoPath -Encoding UTF8
    $currentSection = ''
    $currentSubsection = ''
    $beforePostPlanning = $true

    foreach ($line in $lines) {
        if ($line -match '^Current target:\s*(.+)$') {
            $result.CurrentTarget = $Matches[1].Trim()
            continue
        }

        if ($line -match '^##\s+Post-v0\.7 planning') {
            $beforePostPlanning = $false
        }

        if ($line -match '^##\s+(.+)$') {
            $currentSection = $Matches[1].Trim()
            $currentSubsection = ''
            continue
        }

        if ($line -match '^###\s+(.+)$') {
            $currentSubsection = $Matches[1].Trim()
            continue
        }

        if (-not $beforePostPlanning) {
            continue
        }

        if ($line -match '^- \[ \] (.+)$') {
            $item = [pscustomobject]@{
                Section = $currentSection
                Subsection = $currentSubsection
                Text = $Matches[1].Trim()
            }

            if (-not $result.FirstOpen) {
                $result.FirstOpen = $item
            }

            if ($result.Items.Count -lt $Limit) {
                $result.Items += $item
            }
        }
    }

    return [pscustomobject]$result
}

function Get-RecentChangelogEntries {
    param(
        [Parameter(Mandatory = $true)]
        [string]$ChangelogPath,

        [Parameter(Mandatory = $true)]
        [int]$Limit
    )

    if (-not (Test-Path -LiteralPath $ChangelogPath)) {
        return @()
    }

    $entries = Get-Content -LiteralPath $ChangelogPath -Encoding UTF8 |
        Where-Object { $_ -match '^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}\s+-\s+' } |
        Select-Object -First $Limit

    return @($entries)
}

$repoRoot = Split-Path -Parent $PSScriptRoot
$workspaceRoot = Split-Path -Parent (Split-Path -Parent $repoRoot)
$tasksPath = Join-Path $workspaceRoot '.vscode\tasks.json'
$todoPath = Join-Path $repoRoot 'docs\TODO.md'
$changelogPath = Join-Path $repoRoot 'docs\CHANGELOG.md'

$gitStatus = git -C $repoRoot status --short --branch 2>$null
if (-not $gitStatus) {
    $gitStatus = @('git status unavailable')
}

$taskLabels = @(Get-WorkspaceTaskLabels -TasksFilePath $tasksPath)
$todoSummary = Get-TodoSummary -TodoPath $todoPath -Limit $TodoLimit
$recentChanges = @(Get-RecentChangelogEntries -ChangelogPath $changelogPath -Limit $ChangelogLimit)

$phpVersion = Get-CommandVersionLine -Command 'php' -Arguments @('-v')
$pythonVersion = Get-CommandVersionLine -Command 'python' -Arguments @('--version')

$shellVersion = 'PowerShell {0}' -f $PSVersionTable.PSVersion.ToString()

Write-Output 'bandPromo fast startup'
Write-Output ('Repo: {0}' -f $repoRoot)
Write-Output ('Workspace: {0}' -f $workspaceRoot)
Write-Output ('OS: {0}' -f [System.Environment]::OSVersion.VersionString)
Write-Output ('Shell: {0}' -f $shellVersion)
Write-Output ('PHP: {0}' -f $phpVersion)
Write-Output ('Python: {0}' -f $pythonVersion)
Write-Output ''

Write-Output 'Git'
$gitStatus | ForEach-Object { Write-Output ('  {0}' -f $_) }
Write-Output ''

Write-Output 'Workspace tasks'
if ($taskLabels.Count -gt 0) {
    $taskLabels | ForEach-Object { Write-Output ('  - {0}' -f $_) }
}
else {
    Write-Output '  - none found'
}
Write-Output ''

if ($todoSummary.CurrentTarget) {
    Write-Output ('Current target: {0}' -f $todoSummary.CurrentTarget)
}

Write-Output 'Next open v0.7 tasks'
if ($todoSummary.Items.Count -gt 0) {
    $todoSummary.Items | ForEach-Object {
        $scope = @($_.Section, $_.Subsection) | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
        $prefix = if ($scope.Count -gt 0) { '[' + ($scope -join ' > ') + '] ' } else { '' }
        Write-Output ('  - {0}{1}' -f $prefix, $_.Text)
    }
}
else {
    Write-Output '  - none found'
}
Write-Output ''

Write-Output 'Recent changes'
if ($recentChanges.Count -gt 0) {
    $recentChanges | ForEach-Object { Write-Output ('  - {0}' -f $_) }
}
else {
    Write-Output '  - none found'
}
Write-Output ''

Write-Output 'Recommended focus'
if ($todoSummary.FirstOpen) {
    $focusScope = @($todoSummary.FirstOpen.Section, $todoSummary.FirstOpen.Subsection) | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
    if ($focusScope.Count -gt 0) {
        Write-Output ('  {0}: {1}' -f ($focusScope -join ' > '), $todoSummary.FirstOpen.Text)
    }
    else {
        Write-Output ('  {0}' -f $todoSummary.FirstOpen.Text)
    }
}
else {
    Write-Output '  No open v0.7 tasks found in docs/TODO.md.'
}