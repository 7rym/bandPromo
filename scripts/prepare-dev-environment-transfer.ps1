<#
.SYNOPSIS
  Stage a full developer-environment transfer bundle for bandPromo.

.DESCRIPTION
  Copies the local working tree (including gitignored runtime: data/, media/,
  log/, backups/, web-config.json, .env, .cursor/, scripts/vendor/, etc.) plus
  the operator-private %USERPROFILE%\.bandpromo folder into a destination
  bundle the operator can move to a new machine.

  Does NOT delete or modify the source checkout.
  Does NOT wipe data/, media/, log/, or backups/.

.PARAMETER DestinationRoot
  Folder that will receive the bundle (created if missing).
  Default: C:\dev\bandpromo-env-transfer

.PARAMETER SkipMedia
  Omit media/ (saves ~20+ GB). Only for code+catalogue-only moves; player
  files and artwork will be missing until media is copied separately.

.PARAMETER SkipDist
  Omit dist/ rebuildable ZIPs.

.PARAMETER IncludeConflictWorktrees
  Also copy C:\dev\bandpromo-conflict-PR* sibling worktrees if present.
#>
param(
    [string]$DestinationRoot = 'C:\dev\bandpromo-env-transfer',
    [switch]$SkipMedia,
    [switch]$SkipDist,
    [switch]$IncludeConflictWorktrees,
    [switch]$WhatIf
)

$ErrorActionPreference = 'Stop'

$SourceRoot = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path -LiteralPath (Join-Path $SourceRoot 'VERSION'))) {
    throw "Source root does not look like bandPromo: $SourceRoot"
}

$stamp = Get-Date -Format 'yyyy-MM-dd HH:mm'
$bundleName = 'bandpromo-dev-bundle'
$bundleRoot = Join-Path $DestinationRoot $bundleName
$checkoutDest = Join-Path $bundleRoot 'bandpromo'
$privateDest = Join-Path $bundleRoot 'dot-bandpromo'
$cursorRulesDest = Join-Path $bundleRoot 'dot-cursor-rules'
$manifestPath = Join-Path $bundleRoot 'MANIFEST.txt'
$handoffPath = Join-Path $bundleRoot 'NEW-AGENT-HANDOFF.md'
$verifyPath = Join-Path $bundleRoot 'VERIFY-ON-NEW-MACHINE.ps1'

function Write-Step([string]$Message) {
    Write-Output $Message
}

function Invoke-Robo([string]$From, [string]$To, [string[]]$ExtraArgs) {
    if ($WhatIf) {
        Write-Step ("WHATIF robocopy `{0}` -> `{1}` {2}" -f $From, $To, ($ExtraArgs -join ' '))
        return
    }
    New-Item -ItemType Directory -Force -Path $To | Out-Null
    $args = @($From, $To, '/E', '/COPY:DAT', '/R:2', '/W:3', '/NFL', '/NDL', '/NP', '/XJ') + $ExtraArgs
    & robocopy @args | Out-Null
    # robocopy exit codes 0-7 are success-ish; >=8 is failure
    if ($LASTEXITCODE -ge 8) {
        throw ("robocopy failed ({0}): {1} -> {2}" -f $LASTEXITCODE, $From, $To)
    }
}

Write-Step 'bandPromo developer environment transfer'
Write-Step ("  Source:      {0}" -f $SourceRoot)
Write-Step ("  Destination: {0}" -f $bundleRoot)
Write-Step ("  Started:     {0}" -f $stamp)
Write-Step ''

if (-not $WhatIf) {
    New-Item -ItemType Directory -Force -Path $bundleRoot | Out-Null
}

$excludeDirs = @()
if ($SkipMedia) { $excludeDirs += 'media' }
if ($SkipDist) { $excludeDirs += 'dist' }
# Scratch only — keep folder via source .gitkeep; no need for probe leftovers
$excludeDirs += 'temp'

$roboExtra = @()
if ($excludeDirs.Count -gt 0) {
    $roboExtra += '/XD'
    $roboExtra += $excludeDirs
}

Write-Step '1/4 Copy working tree (includes .git and gitignored runtime)'
Invoke-Robo -From $SourceRoot -To $checkoutDest -ExtraArgs $roboExtra

# Ensure empty temp exists on destination
if (-not $WhatIf) {
    $tempDest = Join-Path $checkoutDest 'temp'
    New-Item -ItemType Directory -Force -Path $tempDest | Out-Null
    $gitkeep = Join-Path $SourceRoot 'temp\.gitkeep'
    if (Test-Path -LiteralPath $gitkeep) {
        Copy-Item -LiteralPath $gitkeep -Destination (Join-Path $tempDest '.gitkeep') -Force
    }
}

Write-Step '2/4 Copy operator-private %USERPROFILE%\.bandpromo'
$privateSrc = Join-Path $env:USERPROFILE '.bandpromo'
if (Test-Path -LiteralPath $privateSrc) {
    Invoke-Robo -From $privateSrc -To $privateDest -ExtraArgs @()
}
else {
    Write-Step '  (missing — skip)'
}

Write-Step '3/4 Copy user Cursor rules under %USERPROFILE%\.cursor\rules (if any)'
$cursorRulesSrc = Join-Path $env:USERPROFILE '.cursor\rules'
if (Test-Path -LiteralPath $cursorRulesSrc) {
    Invoke-Robo -From $cursorRulesSrc -To $cursorRulesDest -ExtraArgs @()
}
else {
    Write-Step '  (missing — skip)'
}

if ($IncludeConflictWorktrees) {
    Write-Step 'Extra: conflict worktrees'
    foreach ($name in @('bandpromo-conflict-PR1', 'bandpromo-conflict-PR3', 'bandpromo-conflict-PR4')) {
        $wt = Join-Path (Split-Path -Parent $SourceRoot) $name
        if (Test-Path -LiteralPath $wt) {
            Invoke-Robo -From $wt -To (Join-Path $bundleRoot $name) -ExtraArgs @('/XD', 'media', 'dist', 'temp')
        }
    }
}

Write-Step '4/4 Write manifest, verify script, and agent handoff'

$versionLine = ''
$versionPath = Join-Path $SourceRoot 'VERSION'
if (Test-Path -LiteralPath $versionPath) {
    $versionLine = (Get-Content -LiteralPath $versionPath -Raw -Encoding utf8).Trim()
}

$gitHead = ''
$gitStatus = ''
Push-Location $SourceRoot
try {
    $gitHead = (git rev-parse --short HEAD 2>$null)
    $gitStatus = (git status -sb 2>$null | Select-Object -First 1)
}
finally {
    Pop-Location
}

$phpPath = (Get-Command php -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source)
$pythonPath = (Get-Command python -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source)
$ffmpegPath = (Get-Command ffmpeg -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source)

$manifest = @"
bandPromo developer environment transfer manifest
Created: $stamp
Source: $SourceRoot
VERSION: $versionLine
Git HEAD: $gitHead
Git status: $gitStatus
Remote: https://github.com/7rym/bandpromo.git

Bundle layout:
  bandpromo\              Full checkout including .git + gitignored runtime
  dot-bandpromo\          Copy of %USERPROFILE%\.bandpromo
  dot-cursor-rules\       Copy of %USERPROFILE%\.cursor\rules (if present)
  NEW-AGENT-HANDOFF.md    Prompt for the new Cursor agent
  VERIFY-ON-NEW-MACHINE.ps1
  MANIFEST.txt            This file

Flags:
  SkipMedia=$SkipMedia
  SkipDist=$SkipDist
  IncludeConflictWorktrees=$IncludeConflictWorktrees

Source tool paths (install equivalents on the new machine):
  php=$phpPath
  python=$pythonPath
  ffmpeg=$ffmpegPath

Critical gitignored paths that MUST arrive with the checkout:
  web-config.json
  .env
  data\          (catalogue, brands, terces, analytics, admin-integrations, .setup_complete)
  media\         (~20+ GB masters + delivery — omit only with -SkipMedia)
  log\
  backups\
  .cursor\rules\ (local Cursor project rules; gitignored)
  scripts\vendor\
  .htaccess, .user.ini, play\.htaccess, site.webmanifest

Do NOT run setup.php on the new machine if data\.setup_complete and web-config.json are present.
Do NOT wipe data/, media/, log/, or backups/ on either machine.
"@

$verifyScript = @'
param(
    [string]$CheckoutRoot = (Join-Path $PSScriptRoot 'bandpromo')
)
$ErrorActionPreference = 'Continue'
$required = @(
    'VERSION',
    'web-config.json',
    'data\.setup_complete',
    'data\terces',
    '.git',
    '.cursor\rules\session-handoff.mdc',
    'docs\SESSION-HANDOFF.md',
    'scripts\session-start.ps1'
)
Write-Output ("Verifying checkout: {0}" -f $CheckoutRoot)
$fail = 0
foreach ($rel in $required) {
    $p = Join-Path $CheckoutRoot $rel
    if (Test-Path -LiteralPath $p) {
        Write-Output ("  OK   {0}" -f $rel)
    }
    else {
        Write-Output ("  MISS {0}" -f $rel)
        $fail++
    }
}
$media = Join-Path $CheckoutRoot 'media'
if (Test-Path -LiteralPath $media) {
    Write-Output '  OK   media\'
}
else {
    Write-Output '  WARN media\ missing (was -SkipMedia used?)'
}
Write-Output ''
Write-Output 'Tools:'
foreach ($cmd in @('php','python','ffmpeg','git','gh')) {
    $c = Get-Command $cmd -ErrorAction SilentlyContinue
    if ($c) { Write-Output ("  OK   {0} = {1}" -f $cmd, $c.Source) }
    else { Write-Output ("  MISS {0}" -f $cmd); $fail++ }
}
if ($fail -gt 0) {
    Write-Output ("VERIFY FAILED ({0} missing)" -f $fail)
    exit 1
}
Write-Output 'VERIFY OK'
exit 0
'@

# Prefer the authored handoff beside this script's sibling docs if we embed later;
# for now write a stub pointer — the full handoff is written by the agent into the bundle.
$handoffStub = @"
# Placeholder

The full new-agent handoff should be at this path. If you only see this stub,
copy ``%USERPROFILE%\.bandpromo\NEW-AGENT-ENVIRONMENT-HANDOFF.md`` into this file
on the source machine before moving the bundle.
"@

if (-not $WhatIf) {
    Set-Content -LiteralPath $manifestPath -Value $manifest -Encoding utf8
    Set-Content -LiteralPath $verifyPath -Value $verifyScript -Encoding utf8
    if (-not (Test-Path -LiteralPath $handoffPath)) {
        Set-Content -LiteralPath $handoffPath -Value $handoffStub -Encoding utf8
    }
    # Also drop a copy of the private handoff into the bundle when present
    $privateHandoff = Join-Path $env:USERPROFILE '.bandpromo\NEW-AGENT-ENVIRONMENT-HANDOFF.md'
    if (Test-Path -LiteralPath $privateHandoff) {
        Copy-Item -LiteralPath $privateHandoff -Destination $handoffPath -Force
    }
}

Write-Step ''
Write-Step 'Transfer staging complete.'
Write-Step ("  Bundle: {0}" -f $bundleRoot)
Write-Step 'Next: copy the whole DestinationRoot to the new machine (external drive / network),'
Write-Step '      place bandpromo\ at the new checkout path, restore dot-bandpromo\ to %USERPROFILE%\.bandpromo,'
Write-Step '      install PHP/Python/ffmpeg/git/gh, then open NEW-AGENT-HANDOFF.md with the new Cursor agent.'
if ($SkipMedia) {
    Write-Step 'WARNING: media\ was skipped — copy media separately before expecting playback.'
}
