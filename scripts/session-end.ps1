[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$CommitMessage,

    [string]$ReleaseSummary = '',
    [switch]$Publish,
    [switch]$Push,
    [switch]$SkipValidation,
    [switch]$SkipPackage,
    # Default false: Site update falls back to GitHub /releases/latest when api.github.com
    # is blocked; prerelease-only packages are invisible on those hosts.
    [switch]$Prerelease
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Test-ForbiddenPath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$RelativePath
    )

    $normalized = $RelativePath.Replace('/', '\').TrimStart('.\')
    $forbiddenPatterns = @(
        '^desktop\.ini$',
        '^Desktop\.ini$',
        '^web-config\.json$',
        '^\.env$',
        '^\.editorconfig$',
        '^\.vscode\\',
        '^\.htaccess$',
        '^\.user\.ini$',
        '^data\\',
        '^media\\',
        '^log\\',
        '^backups\\',
        '^dist\\',
        '^play\\\.htaccess$'
    )

    foreach ($pattern in $forbiddenPatterns) {
        if ($normalized -match $pattern) {
            return $true
        }
    }

    return $false
}

function Test-AllowedRepositoryPath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$RelativePath
    )

    $normalized = $RelativePath.Replace('\', '/')
    if ($normalized.StartsWith('./')) {
        $normalized = $normalized.Substring(2)
    }
    $allowedPrefixes = @(
        'scripts/',
        'docs/',
        'biblioteca/',
        '.github/',
        'play/'
    )

    foreach ($prefix in $allowedPrefixes) {
        if ($normalized.StartsWith($prefix, [System.StringComparison]::Ordinal)) {
            return $true
        }
    }

    $allowedFiles = @(
        'admin.php',
        'setup.php',
        'bootstrap.php',
        'VERSION',
        'README.md',
        'LICENSE'
    )

    return $allowedFiles -contains $normalized
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
$changelogPath = Join-Path $repoRoot 'docs\CHANGELOG.md'
$versionPath = Join-Path $repoRoot 'VERSION'
$issues = New-Object System.Collections.Generic.List[string]

Write-Output 'bandPromo session end'

$statusLines = @(git -C $repoRoot status --porcelain 2>$null)
if (-not $statusLines) {
    $issues.Add('git status unavailable.')
}
else {
    $trackedChanges = @($statusLines | Where-Object { $_ -notmatch '^\?\?' })
    $untracked = @($statusLines | Where-Object { $_ -match '^\?\?' } | ForEach-Object { $_.Substring(3).Trim() })
    $newRepositoryFiles = @($untracked | Where-Object { Test-AllowedRepositoryPath -RelativePath $_ })

    if ($trackedChanges.Count -eq 0 -and $newRepositoryFiles.Count -eq 0) {
        $issues.Add('No changes to checkpoint. Stage your work before session end.')
    }

    foreach ($relativePath in $untracked) {
        if (Test-ForbiddenPath -RelativePath $relativePath) {
            continue
        }

        if ($relativePath -match '\.(mov|mp4|avi|flac|mp3|wav)$') {
            continue
        }

        if (Test-AllowedRepositoryPath -RelativePath $relativePath) {
            continue
        }

        $issues.Add("Unexpected untracked file: $relativePath")
    }

    $changelogChanged = @($statusLines | Where-Object { $_ -match 'CHANGELOG\.md' }).Count -gt 0
    if (-not $changelogChanged) {
        $issues.Add('docs/CHANGELOG.md is not modified. Add a timestamped entry before checkpointing.')
    }
}

if ($issues.Count -gt 0 -and -not $SkipValidation) {
    Write-Output ''
    Write-Output 'Validation failed'
    $issues | ForEach-Object { Write-Output ('  - {0}' -f $_) }
    Write-Output ''
    Write-Output 'Fix the issues above or rerun with -SkipValidation if you accept the risk.'
    exit 1
}

Write-Output ''
Write-Output 'Build bump'
$previousVersion = Get-VersionLine -VersionPath $versionPath
$bumpOutput = python (Join-Path $repoRoot 'scripts\bump_version.py') 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Error ('VERSION bump failed: {0}' -f ($bumpOutput -join ' '))
}

$newVersion = Get-VersionLine -VersionPath $versionPath
Write-Output ('  {0} -> {1}' -f $previousVersion, $newVersion)

Write-Output ''
Write-Output 'Commit'
foreach ($line in $statusLines) {
    if ($line.Length -lt 4) {
        continue
    }

    $relativePath = $line.Substring(3).Trim()
    if (Test-ForbiddenPath -RelativePath $relativePath) {
        continue
    }

    git -C $repoRoot add -- $relativePath
}

if (Test-Path -LiteralPath $versionPath) {
    git -C $repoRoot add -- VERSION
}

git -C $repoRoot commit -m $CommitMessage
if ($LASTEXITCODE -ne 0) {
    Write-Error 'git commit failed.'
}

if ($Push) {
    Write-Output ''
    Write-Output 'Push'
    git -C $repoRoot push origin main
    if ($LASTEXITCODE -ne 0) {
        Write-Error 'git push failed.'
    }

    Write-Output 'Pull after push'
    git -C $repoRoot pull --ff-only origin main
}

if (-not $SkipPackage) {
    Write-Output ''
    Write-Output 'Local release package'
    python (Join-Path $repoRoot 'scripts\build_release_package.py') --clean
    if ($LASTEXITCODE -ne 0) {
        Write-Error 'build_release_package.py failed.'
    }
}

if ($Publish) {
    if (-not $Push) {
        Write-Output ''
        Write-Output 'Publish skipped: use -Push with -Publish so GitHub Actions builds from the pushed commit.'
        exit 1
    }

    $tagName = python (Join-Path $repoRoot 'scripts\version_format.py') tag
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($tagName)) {
        Write-Error 'Could not derive release tag from VERSION.'
    }

    if ([string]::IsNullOrWhiteSpace($ReleaseSummary)) {
        $ReleaseSummary = "bandPromo $newVersion"
    }
    else {
        $ReleaseSummary = "bandPromo $newVersion - $ReleaseSummary"
    }

    Write-Output ''
    Write-Output 'Publish GitHub release package'
    $prereleaseValue = if ($Prerelease) { 'true' } else { 'false' }
    gh workflow run 'Publish release package' `
        -f tag_name=$tagName `
        -f release_name=$ReleaseSummary `
        -f prerelease=$prereleaseValue `
        -f draft=false

    if ($LASTEXITCODE -ne 0) {
        Write-Error 'gh workflow run failed.'
    }

    Write-Output ('  Triggered tag {0} (prerelease={1})' -f $tagName, $prereleaseValue)
}

Write-Output ''
Write-Output 'Session end complete'
Write-Output ('  VERSION: {0}' -f $newVersion)
if (-not $SkipPackage) {
    Write-Output ('  Package: dist/bandpromo-{0}.zip' -f ((python (Join-Path $repoRoot 'scripts\version_format.py') tag) -replace '^v', ''))
}
