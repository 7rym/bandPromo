[CmdletBinding()]
param(
    [string]$DevRoot = 'C:\dev',
    [string]$ProjectName = 'bandpromo',
    [string]$RepoUrl = 'https://github.com/7rym/bandpromo.git',
    [string]$SourcePath = 'C:\Users\Trym\Desktop\Code\bandPromo',
    [ValidateSet('auto', 'clone', 'migrate')]
    [string]$Mode = 'auto',
    [switch]$SkipPull,
    [switch]$SkipIdeTasks,
    [switch]$StartDevServer
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
# PowerShell 7+ would otherwise treat git/robocopy stderr as terminating errors.
if (Get-Variable -Name PSNativeCommandUseErrorActionPreference -ErrorAction SilentlyContinue) {
    $PSNativeCommandUseErrorActionPreference = $false
}

function Write-Step {
    param([Parameter(Mandatory = $true)][string]$Message)
    Write-Output ''
    Write-Output "==> $Message"
}

function Test-CommandAvailable {
    param([Parameter(Mandatory = $true)][string]$Name)
    return $null -ne (Get-Command $Name -ErrorAction SilentlyContinue)
}

function Invoke-NativeCommand {
    param(
        [Parameter(Mandatory = $true)][string]$FilePath,
        [Parameter(Mandatory = $true)][string[]]$ArgumentList,
        [int[]]$SuccessExitCodes = @(0)
    )

    $previousEap = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        & $FilePath @ArgumentList 2>&1 | ForEach-Object {
            if ($_ -is [System.Management.Automation.ErrorRecord]) {
                Write-Host ("  {0}" -f $_.Exception.Message)
            }
            else {
                Write-Host ("  {0}" -f $_)
            }
        }
        $exitCode = $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $previousEap
    }

    if ($SuccessExitCodes -notcontains $exitCode) {
        throw ("{0} {1} failed with exit code {2}." -f $FilePath, ($ArgumentList -join ' '), $exitCode)
    }

    return $exitCode
}

function Get-CommandVersionLine {
    param(
        [Parameter(Mandatory = $true)][string]$Command,
        [Parameter(Mandatory = $true)][string[]]$Arguments
    )

    if (-not (Test-CommandAvailable -Name $Command)) {
        return "${Command}: not found"
    }

    $previousEap = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
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
    finally {
        $ErrorActionPreference = $previousEap
    }
}

function Ensure-Directory {
    param([Parameter(Mandatory = $true)][string]$Path)
    if (-not (Test-Path -LiteralPath $Path)) {
        New-Item -ItemType Directory -Path $Path -Force | Out-Null
    }
}

function Test-IncompleteProjectPath {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        return $false
    }

    $items = @(Get-ChildItem -LiteralPath $Path -Force)
    if ($items.Count -eq 0) {
        return $true
    }

    $names = @($items | ForEach-Object { $_.Name })
    return ($names.Count -eq 1 -and $names[0] -eq '.git')
}

function Remove-IncompleteProjectPath {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-IncompleteProjectPath -Path $Path)) {
        return
    }

    Write-Output "Removing leftover incomplete folder: $Path"
    Remove-Item -LiteralPath $Path -Recurse -Force
}

function Copy-DirectoryTree {
    param(
        [Parameter(Mandatory = $true)][string]$Source,
        [Parameter(Mandatory = $true)][string]$Destination
    )

    Ensure-Directory -Path $Destination
    $previousEap = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        & robocopy $Source $Destination /E /XD .git /NFL /NDL /NJH /NJS /NC /NS /NP | Out-Null
        $exitCode = $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $previousEap
    }

    # robocopy: 0-7 are success/partial-success; 8+ is failure.
    if ($exitCode -ge 8) {
        throw "robocopy failed while copying from $Source to $Destination (exit $exitCode)."
    }
}

function Copy-RuntimeState {
    param(
        [Parameter(Mandatory = $true)][string]$SourceRoot,
        [Parameter(Mandatory = $true)][string]$DestinationRoot
    )

    foreach ($dirName in @('data', 'media', 'log', 'backups')) {
        $srcDir = Join-Path $SourceRoot $dirName
        $dstDir = Join-Path $DestinationRoot $dirName
        if (Test-Path -LiteralPath $srcDir) {
            Write-Output "Copying runtime folder: $dirName"
            Copy-DirectoryTree -Source $srcDir -Destination $dstDir
        }
    }

    foreach ($fileName in @('web-config.json', '.env')) {
        $srcFile = Join-Path $SourceRoot $fileName
        $dstFile = Join-Path $DestinationRoot $fileName
        if ((Test-Path -LiteralPath $srcFile) -and -not (Test-Path -LiteralPath $dstFile)) {
            Copy-Item -LiteralPath $srcFile -Destination $dstFile
            Write-Output "Copied $fileName"
        }
    }
}

function Install-IdeTasks {
    param([Parameter(Mandatory = $true)][string]$RepoRoot)

    $templatePath = Join-Path $RepoRoot 'scripts\templates\vscode-tasks.json'
    if (-not (Test-Path -LiteralPath $templatePath)) {
        Write-Warning "Missing $templatePath - skipping IDE task install."
        return
    }

    $vscodeDir = Join-Path $RepoRoot '.vscode'
    $tasksPath = Join-Path $vscodeDir 'tasks.json'
    Ensure-Directory -Path $vscodeDir
    Copy-Item -LiteralPath $templatePath -Destination $tasksPath -Force
    Write-Output "Installed local IDE tasks at $tasksPath"
}

function Test-GitRepository {
    param([Parameter(Mandatory = $true)][string]$Path)
    if (-not (Test-Path -LiteralPath $Path)) {
        return $false
    }

    $previousEap = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        Push-Location -LiteralPath $Path
        try {
            git rev-parse --is-inside-work-tree 1>$null 2>$null
            return $LASTEXITCODE -eq 0
        }
        finally {
            Pop-Location
        }
    }
    finally {
        $ErrorActionPreference = $previousEap
    }
}

function Set-GitOriginUrl {
    param(
        [Parameter(Mandatory = $true)][string]$RepoRoot,
        [Parameter(Mandatory = $true)][string]$Url
    )

    Push-Location -LiteralPath $RepoRoot
    try {
        $previousEap = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        try {
            $remotes = @(& git remote)
        }
        finally {
            $ErrorActionPreference = $previousEap
        }

        if ($remotes -contains 'origin') {
            Invoke-NativeCommand -FilePath 'git' -ArgumentList @('remote', 'set-url', 'origin', $Url) | Out-Null
            Write-Output "Set origin to $Url"
        }
        else {
            Invoke-NativeCommand -FilePath 'git' -ArgumentList @('remote', 'add', 'origin', $Url) | Out-Null
            Write-Output "Added origin remote: $Url"
        }
    }
    finally {
        Pop-Location
    }
}

function Invoke-RepositorySync {
    param(
        [Parameter(Mandatory = $true)][string]$RepoRoot,
        [switch]$SkipPull
    )

    if (-not (Test-GitRepository -Path $RepoRoot)) {
        throw "Expected a git repository at $RepoRoot"
    }

    Set-GitOriginUrl -RepoRoot $RepoRoot -Url $RepoUrl

    if ($SkipPull) {
        return
    }

    Write-Output 'Pulling latest main from origin...'
    Push-Location -LiteralPath $RepoRoot
    try {
        Invoke-NativeCommand -FilePath 'git' -ArgumentList @('fetch', 'origin', 'main') | Out-Null
        Invoke-NativeCommand -FilePath 'git' -ArgumentList @('checkout', 'main') | Out-Null
        Invoke-NativeCommand -FilePath 'git' -ArgumentList @('pull', '--ff-only', 'origin', 'main') | Out-Null
    }
    finally {
        Pop-Location
    }
}

function Resolve-SetupMode {
    param(
        [string]$RequestedMode,
        [string]$TargetPath,
        [string]$SourcePath
    )

    if ($RequestedMode -ne 'auto') {
        return $RequestedMode
    }

    if ((Test-Path -LiteralPath $TargetPath) -and -not (Test-IncompleteProjectPath -Path $TargetPath)) {
        return 'existing'
    }

    if ((Test-Path -LiteralPath $SourcePath) -and (Test-GitRepository -Path $SourcePath)) {
        return 'migrate'
    }

    return 'clone'
}

$devRoot = [System.IO.Path]::GetFullPath($DevRoot)
$targetPath = Join-Path $devRoot $ProjectName
$sourcePath = [System.IO.Path]::GetFullPath($SourcePath)
$resolvedMode = Resolve-SetupMode -RequestedMode $Mode -TargetPath $targetPath -SourcePath $sourcePath

Write-Output 'bandPromo Windows dev setup'
Write-Output "Dev root:      $devRoot"
Write-Output "Project path:  $targetPath"
Write-Output "Source path:   $sourcePath"
Write-Output "Mode:          $resolvedMode"
Write-Output "Repo URL:      $RepoUrl"
Write-Output ''

Write-Step 'Ensure C:\dev exists'
Ensure-Directory -Path $devRoot
Remove-IncompleteProjectPath -Path $targetPath

switch ($resolvedMode) {
    'clone' {
        if (Test-Path -LiteralPath $targetPath) {
            throw "Target already exists: $targetPath. Remove it or choose migrate/existing."
        }

        if (-not (Test-CommandAvailable -Name 'git')) {
            throw 'git is required. Install Git for Windows first: https://git-scm.com/download/win'
        }

        Write-Step "Clone repository into $targetPath"
        Invoke-NativeCommand -FilePath 'git' -ArgumentList @('clone', $RepoUrl, $targetPath) | Out-Null
    }

    'migrate' {
        if (Test-Path -LiteralPath $targetPath) {
            throw "Target already exists: $targetPath. Remove it first or open the existing folder."
        }

        if (-not (Test-Path -LiteralPath $sourcePath)) {
            throw "Source path not found: $sourcePath"
        }

        if (-not (Test-CommandAvailable -Name 'git')) {
            throw 'git is required. Install Git for Windows first: https://git-scm.com/download/win'
        }

        Write-Step "Migrate working copy from $sourcePath"
        Write-Output 'Cloning from GitHub so origin points at the remote, then copying local runtime...'
        try {
            Invoke-NativeCommand -FilePath 'git' -ArgumentList @('clone', $RepoUrl, $targetPath) | Out-Null
        }
        catch {
            Write-Warning "GitHub clone failed; cloning local copy instead. $($_.Exception.Message)"
            Invoke-NativeCommand -FilePath 'git' -ArgumentList @('clone', $sourcePath, $targetPath) | Out-Null
            Set-GitOriginUrl -RepoRoot $targetPath -Url $RepoUrl
        }

        Copy-RuntimeState -SourceRoot $sourcePath -DestinationRoot $targetPath
    }

    'existing' {
        Write-Step "Use existing project at $targetPath"
        if ((Test-Path -LiteralPath $sourcePath) -and ($sourcePath -ne $targetPath)) {
            Copy-RuntimeState -SourceRoot $sourcePath -DestinationRoot $targetPath
        }
    }

    default {
        throw "Unsupported setup mode: $resolvedMode"
    }
}

if (-not (Test-Path -LiteralPath $targetPath)) {
    throw "Setup failed - project path does not exist: $targetPath"
}

Write-Step 'Sync repository'
Invoke-RepositorySync -RepoRoot $targetPath -SkipPull:$SkipPull

if (-not $SkipIdeTasks) {
    Write-Step 'Install local Cursor/VS Code tasks'
    Install-IdeTasks -RepoRoot $targetPath
}

Write-Step 'Check local tooling'
Write-Output ('  git:    {0}' -f (Get-CommandVersionLine -Command 'git' -Arguments @('--version')))
Write-Output ('  php:    {0}' -f (Get-CommandVersionLine -Command 'php' -Arguments @('-v')))
Write-Output ('  python: {0}' -f (Get-CommandVersionLine -Command 'python' -Arguments @('--version')))
Write-Output ('  ffmpeg: {0}' -f (Get-CommandVersionLine -Command 'ffmpeg' -Arguments @('--version')))

$missing = @()
if (-not (Test-CommandAvailable -Name 'git')) { $missing += 'git' }
if (-not (Test-CommandAvailable -Name 'php')) { $missing += 'php' }
if (-not (Test-CommandAvailable -Name 'python')) { $missing += 'python' }

if ($missing.Count -gt 0) {
    Write-Warning ("Missing tools: {0}. Install them before running setup.php or builds." -f ($missing -join ', '))
}

$configPath = Join-Path $targetPath 'web-config.json'
$setupCompletePath = Join-Path $targetPath 'data\.setup_complete'
$hasRuntime = (Test-Path -LiteralPath $configPath) -or (Test-Path -LiteralPath $setupCompletePath)

if ($StartDevServer -or $hasRuntime) {
    Write-Step 'Start local PHP dev server'
    $startScript = Join-Path $targetPath 'scripts\start-dev-server.ps1'
    if (Test-Path -LiteralPath $startScript) {
        $previousEap = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        try {
            & powershell -ExecutionPolicy Bypass -File $startScript
        }
        finally {
            $ErrorActionPreference = $previousEap
        }
    }
}

Write-Step 'Next steps'
Write-Output "1. Open this folder in Cursor: $targetPath"
Write-Output '2. Run task: bandPromo: Session start'
if (-not $hasRuntime) {
    Write-Output '3. Fresh local site: open http://127.0.0.1:8000/setup.php and complete the wizard'
    Write-Output '   (This creates local data/, media/, and web-config.json - they stay on your PC only.)'
}
else {
    Write-Output '3. Existing runtime detected - open http://127.0.0.1:8000/admin.php after starting the dev server'
}
Write-Output '4. Remote Linux servers: deploy published GitHub Release packages (bootstrap/Site update), not your C:\dev folder'
Write-Output '5. After the Desktop copy is verified, you can archive or delete:'
Write-Output "   $sourcePath"
Write-Output ''
Write-Output "Setup complete. Canonical project path: $targetPath"
