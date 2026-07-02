[CmdletBinding()]
param(
    [int]$TodoLimit = 4,
    [int]$ChangelogLimit = 3,
    [switch]$SkipSessionBump,
    [switch]$SkipDevServer,
    [switch]$SkipPull
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
    $inCurrentMilestone = $false
    $collectOpenTasks = $false
    $currentSection = ''
    $currentSubsection = ''

    foreach ($line in $lines) {
        if ($line -match '^##\s+Current milestone') {
            $inCurrentMilestone = $true
            continue
        }

        if ($inCurrentMilestone -and $line -match '^\*\*(.+?)\*\*') {
            $result.CurrentTarget = $Matches[1].Trim()
            $inCurrentMilestone = $false
            continue
        }

        if ($line -match '^##\s+v0\.8 active work') {
            $collectOpenTasks = $true
            $currentSection = 'v0.8 active work'
            $currentSubsection = ''
            continue
        }

        if ($line -match '^##\s+v0\.7 exit gates') {
            break
        }

        if (-not $collectOpenTasks) {
            continue
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

function Get-VersionLine {
    param(
        [Parameter(Mandatory = $true)]
        [string]$VersionPath
    )

    if (-not (Test-Path -LiteralPath $VersionPath)) {
        return $null
    }

    return (Get-Content -LiteralPath $VersionPath -Encoding UTF8 | Select-Object -First 1).Trim()
}

$repoRoot = Split-Path -Parent $PSScriptRoot
$tasksPath = Join-Path $repoRoot '.vscode\tasks.json'
$todoPath = Join-Path $repoRoot 'docs\TODO.md'
$changelogPath = Join-Path $repoRoot 'docs\CHANGELOG.md'
$versionPath = Join-Path $repoRoot 'VERSION'
$startDevServerScript = Join-Path $repoRoot 'scripts\start-dev-server.ps1'

Write-Output 'bandPromo session start'
Write-Output ('Repo: {0}' -f $repoRoot)
Write-Output ('OS: {0}' -f [System.Environment]::OSVersion.VersionString)
Write-Output ('Shell: PowerShell {0}' -f $PSVersionTable.PSVersion.ToString())
Write-Output ('PHP: {0}' -f (Get-CommandVersionLine -Command 'php' -Arguments @('-v')))
Write-Output ('Python: {0}' -f (Get-CommandVersionLine -Command 'python' -Arguments @('--version')))
Write-Output ''

if (-not $SkipPull) {
    Write-Output 'Repository sync'
    try {
        $pullOutput = git -C $repoRoot pull --ff-only origin main 2>&1
        $pullOutput | ForEach-Object { Write-Output ('  {0}' -f $_) }
    }
    catch {
        Write-Output ('  git pull failed: {0}' -f $_.Exception.Message)
        Write-Output '  Continue locally, but resolve sync before checkpointing.'
    }
    Write-Output ''
}

$previousVersion = Get-VersionLine -VersionPath $versionPath
if (-not $SkipSessionBump) {
    Write-Output 'Session version'
    if ($previousVersion) {
        Write-Output ('  Previous: {0}' -f $previousVersion)
    }

    $bumpOutput = python (Join-Path $repoRoot 'scripts\bump_session.py') 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Output ('  Session bump failed: {0}' -f ($bumpOutput -join ' '))
    }
    else {
        Write-Output ('  Current:  {0}' -f ($bumpOutput | Select-Object -Last 1))
        Write-Output '  Build number is unchanged until session end checkpoint.'
    }
    Write-Output ''
}

if (-not $SkipDevServer) {
    Write-Output 'Dev server'
    try {
        $serverOutput = & $startDevServerScript 2>&1
        $serverOutput | ForEach-Object { Write-Output ('  {0}' -f $_) }
    }
    catch {
        Write-Output ('  Dev server start failed: {0}' -f $_.Exception.Message)
    }
    Write-Output ''
}

$gitStatus = git -C $repoRoot status --short --branch 2>$null
if (-not $gitStatus) {
    $gitStatus = @('git status unavailable')
}

$taskLabels = @(Get-WorkspaceTaskLabels -TasksFilePath $tasksPath)
$todoSummary = Get-TodoSummary -TodoPath $todoPath -Limit $TodoLimit
$recentChanges = @(Get-RecentChangelogEntries -ChangelogPath $changelogPath -Limit $ChangelogLimit)
$currentVersion = Get-VersionLine -VersionPath $versionPath

Write-Output ('VERSION: {0}' -f $(if ($currentVersion) { $currentVersion } else { 'missing' }))
Write-Output ''

Write-Output 'Git'
$gitStatus | ForEach-Object { Write-Output ('  {0}' -f $_) }
Write-Output ''

Write-Output 'Workspace tasks'
if ($taskLabels.Count -gt 0) {
    $taskLabels | ForEach-Object { Write-Output ('  - {0}' -f $_) }
}
else {
    Write-Output '  - none found (.vscode/tasks.json missing or empty)'
}
Write-Output ''

if ($todoSummary.CurrentTarget) {
    Write-Output ('Current target: {0}' -f $todoSummary.CurrentTarget)
}

Write-Output 'Next open v0.8 tasks'
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
    Write-Output '  No open v0.8 tasks found in docs/TODO.md.'
}

Write-Output ''
Write-Output 'Session end'
Write-Output '  When ready to checkpoint: run scripts/session-end.ps1 or task "bandPromo: Session end".'
