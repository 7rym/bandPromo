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

function Write-Step {
    param([Parameter(Mandatory = $true)][string]$Message)
    Write-Output ''
    Write-Output "==> $Message"
}

function Test-CommandAvailable {
    param([Parameter(Mandatory = $true)][string]$Name)
    return $null -ne (Get-Command $Name -ErrorAction SilentlyContinue)
}

function Get-CommandVersionLine {
    param(
        [Parameter(Mandatory = $true)][string]$Command,
        [Parameter(Mandatory = $true)][string[]]$Arguments
    )

    if (-not (Test-CommandAvailable -Name $Command)) {
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

function Ensure-Directory {
    param([Parameter(Mandatory = $true)][string]$Path)
    if (-not (Test-Path -LiteralPath $Path)) {
        New-Item -ItemType Directory -Path $Path -Force | Out-Null
    }
}

function Copy-DirectoryTree {
    param(
        [Parameter(Mandatory = $true)][string]$Source,
        [Parameter(Mandatory = $true)][string]$Destination
    )

    Ensure-Directory -Path $Destination
    $robocopyArgs = @(
        $Source,
        $Destination,
        '/MIR',
        '/XD', '.git',
        '/NFL', '/NDL', '/NJH', '/NJS', '/NC', '/NS', '/NP'
    )
    $exitCode = & robocopy @robocopyArgs
    if ($exitCode -ge 8) {
        throw "robocopy failed while copying from $Source to $Destination (exit $exitCode)."
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

    Push-Location -LiteralPath $Path
    try {
        git rev-parse --is-inside-work-tree 2>$null | Out-Null
        return $LASTEXITCODE -eq 0
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

    Push-Location -LiteralPath $RepoRoot
    try {
        $remotes = @(git remote)
        if ($remotes -notcontains 'origin') {
            git remote add origin $RepoUrl | Out-Null
            Write-Output "Added origin remote: $RepoUrl"
        }

        if (-not $SkipPull) {
            Write-Output 'Pulling latest main from origin...'
            git fetch origin main 2>&1 | ForEach-Object { Write-Output "  $_" }
            git checkout main 2>&1 | ForEach-Object { Write-Output "  $_" }
            git pull --ff-only origin main 2>&1 | ForEach-Object { Write-Output "  $_" }
        }
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

    if (Test-Path -LiteralPath $TargetPath) {
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

switch ($resolvedMode) {
    'clone' {
        if (Test-Path -LiteralPath $targetPath) {
            throw "Target already exists: $targetPath. Remove it or choose migrate/existing."
        }

        if (-not (Test-CommandAvailable -Name 'git')) {
            throw 'git is required. Install Git for Windows first: https://git-scm.com/download/win'
        }

        Write-Step "Clone repository into $targetPath"
        git clone $RepoUrl $targetPath 2>&1 | ForEach-Object { Write-Output "  $_" }
    }

    'migrate' {
        if (Test-Path -LiteralPath $targetPath) {
            throw "Target already exists: $targetPath. Remove it first or open the existing folder."
        }

        if (-not (Test-Path -LiteralPath $sourcePath)) {
            throw "Source path not found: $sourcePath"
        }

        Write-Step "Migrate working copy from $sourcePath"
        Ensure-Directory -Path $targetPath

        if (Test-GitRepository -Path $sourcePath) {
            Write-Output 'Source is a git repo - cloning metadata, then syncing tracked files...'
            git clone $sourcePath $targetPath 2>&1 | ForEach-Object { Write-Output "  $_" }

            $runtimeDirs = @('data', 'media', 'log', 'backups')
            foreach ($dirName in $runtimeDirs) {
                $srcDir = Join-Path $sourcePath $dirName
                $dstDir = Join-Path $targetPath $dirName
                if (Test-Path -LiteralPath $srcDir) {
                    Write-Output "Copying runtime folder: $dirName"
                    Copy-DirectoryTree -Source $srcDir -Destination $dstDir
                }
            }

            foreach ($fileName in @('web-config.json', '.env')) {
                $srcFile = Join-Path $sourcePath $fileName
                $dstFile = Join-Path $targetPath $fileName
                if ((Test-Path -LiteralPath $srcFile) -and -not (Test-Path -LiteralPath $dstFile)) {
                    Copy-Item -LiteralPath $srcFile -Destination $dstFile
                    Write-Output "Copied $fileName"
                }
            }
        }
        else {
            Write-Output 'Source is not a git repo - copying tree without .git, then initializing git...'
            Copy-DirectoryTree -Source $sourcePath -Destination $targetPath
            Push-Location -LiteralPath $targetPath
            try {
                git init
                git remote add origin $RepoUrl
                git fetch origin main
                git checkout -B main origin/main
            }
            finally {
                Pop-Location
            }
        }
    }

    'existing' {
        Write-Step "Use existing project at $targetPath"
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
Write-Output ('  ffmpeg: {0}' -f (Get-CommandVersionLine -Command 'ffmpeg' -Arguments @('-version')))

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
        & powershell -ExecutionPolicy Bypass -File $startScript
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
