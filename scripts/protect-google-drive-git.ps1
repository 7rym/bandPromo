param(
    [string]$TargetGitDir = $(Join-Path $env:USERPROFILE ".local-gitdirs\bandPromo")
)

# Legacy helper: only needed if this clone still lives under a Google Drive sync folder.
# Normal checkout is C:\dev\bandpromo with .git in-tree — do not run this unless Drive sync is back.

$ErrorActionPreference = "Stop"

function Remove-DesktopIniFiles {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    if (-not (Test-Path -LiteralPath $Path)) {
        return
    }

    Get-ChildItem -LiteralPath $Path -Force -Recurse -Filter "desktop.ini" -ErrorAction SilentlyContinue |
        Remove-Item -Force -ErrorAction SilentlyContinue
    Get-ChildItem -LiteralPath $Path -Force -Recurse -Filter "Desktop.ini" -ErrorAction SilentlyContinue |
        Remove-Item -Force -ErrorAction SilentlyContinue
}

function Ensure-ExcludePatterns {
    param(
        [Parameter(Mandatory = $true)]
        [string]$GitDir
    )

    $excludePath = Join-Path $GitDir "info\exclude"
    if (-not (Test-Path -LiteralPath $excludePath)) {
        return
    }

    $excludeContent = Get-Content -LiteralPath $excludePath -ErrorAction SilentlyContinue
    if ($excludeContent -notcontains "desktop.ini") {
        Add-Content -LiteralPath $excludePath -Value ""
        Add-Content -LiteralPath $excludePath -Value "# Google Drive metadata"
        Add-Content -LiteralPath $excludePath -Value "desktop.ini"
        Add-Content -LiteralPath $excludePath -Value "Desktop.ini"
    }
}

$repoRoot = (git rev-parse --show-toplevel).Trim()
if (-not $repoRoot) {
    throw "Could not determine repository root. Run this script from inside the repository worktree."
}

Set-Location -LiteralPath $repoRoot

$currentGitDirRaw = (git rev-parse --git-dir).Trim()
$currentGitDir = if ([System.IO.Path]::IsPathRooted($currentGitDirRaw)) {
    [System.IO.Path]::GetFullPath($currentGitDirRaw)
} else {
    [System.IO.Path]::GetFullPath((Join-Path $repoRoot $currentGitDirRaw))
}

$targetGitDir = [System.IO.Path]::GetFullPath($TargetGitDir)
$gitFilePath = Join-Path $repoRoot ".git"

if ($currentGitDir -eq $targetGitDir) {
    Remove-DesktopIniFiles -Path $repoRoot
    Remove-DesktopIniFiles -Path $targetGitDir
    Ensure-ExcludePatterns -GitDir $targetGitDir
    Write-Output "Git directory is already outside Google Drive: $targetGitDir"
    exit 0
}

if (-not (Test-Path -LiteralPath $currentGitDir)) {
    throw "Current git directory not found: $currentGitDir"
}

if ((Test-Path -LiteralPath $gitFilePath) -and -not (Test-Path -LiteralPath $gitFilePath -PathType Container)) {
    throw "Repository already uses a gitdir file at $gitFilePath. Refusing to overwrite it automatically."
}

$targetParent = Split-Path -Parent $targetGitDir
if ($targetParent) {
    New-Item -ItemType Directory -Path $targetParent -Force | Out-Null
}

if (Test-Path -LiteralPath $targetGitDir) {
    throw "Target git directory already exists: $targetGitDir"
}

Remove-DesktopIniFiles -Path $repoRoot
Move-Item -LiteralPath $currentGitDir -Destination $targetGitDir
Set-Content -LiteralPath $gitFilePath -Value "gitdir: $targetGitDir" -NoNewline -Encoding ascii

Remove-DesktopIniFiles -Path $repoRoot
Remove-DesktopIniFiles -Path $targetGitDir
Ensure-ExcludePatterns -GitDir $targetGitDir

git status --short | Out-Null

Write-Output "Moved git directory to $targetGitDir"
Write-Output "Repository metadata is now outside Google Drive."