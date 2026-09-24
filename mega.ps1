<#
.SYNOPSIS
    All-in-one pipeline: reset → test → dump/zip for one or all LOA targets.

.DESCRIPTION
    Runs the LOA workflow with visible progress for a selected target:
      1. Reset (teardown + rebuild + migrate + seed + Swagger for selected app(s))
      2. Run tests (selected app(s) only)
      3. Dump (dist build + zip + SQL regeneration) for selected target(s)
      4. Open D:\builds

    -Target all   → auth + cert + consult (original behavior)
    -Target cert  → reset/provision cert, cert tests, dump cert only
    -Target auth  → auth only
    -Target consultation (or consult) → consult only

.PARAMETER Target
    Which assembly to process: all | auth | cert | consult | consultation.
    consultation is an alias for consult. Default: all.

.PARAMETER SkipReset
    Skip the reset step (useful when infra is already fresh).

.PARAMETER SkipTests
    Skip the test step.

.PARAMETER DumpPath
    Output directory for dump artifacts. Defaults to D:\builds.

.EXAMPLE
    .\mega.ps1
    .\mega.ps1 -Target cert
    .\mega.ps1 -Target auth -SkipReset
    .\mega.ps1 -Target consultation
    .\mega.ps1 -DumpPath E:\builds
#>

[CmdletBinding()]
param(
    [ValidateSet('all', 'auth', 'cert', 'consult', 'consultation')]
    [string]$Target = 'all',

    [switch]$SkipReset,
    [switch]$SkipTests,
    [string]$DumpPath = 'D:\builds'
)

$ErrorActionPreference = 'Stop'

# mega.ps1 lives at the repo root.
$Root = $PSScriptRoot
Set-Location $Root

# Normalize alias: consultation → consult (matches dump.ps1 / reset-all.ps1).
if ($Target -eq 'consultation') { $Target = 'consult' }

$selected = if ($Target -eq 'all') {
    @('auth', 'cert', 'consult')
} else {
    @($Target)
}

$targetLabel = if ($Target -eq 'all') { 'all' } else { $Target }
$testLabel = if ($selected.Count -eq 1) { $selected[0] } else { 'auth + cert + consult' }

$steps = @(
    @{ Name = "Reset ($targetLabel)"; Skip = $SkipReset },
    @{ Name = "Run Tests ($testLabel)"; Skip = $SkipTests }
)

foreach ($t in $selected) {
    $steps += @{ Name = "Dump $t"; Skip = $false; Target = $t }
}

$steps += @{ Name = 'Open Builds'; Skip = $false }

$total = $steps.Count
$passed = 0
$failed = $false
$startTime = Get-Date

function Show-Header {
    param([int]$StepNum, [int]$Total, [string]$Name)
    $border = '=' * 60
    Write-Host ""
    Write-Host $border -ForegroundColor DarkGray
    Write-Host "  STEP $StepNum/$Total  $Name" -ForegroundColor Cyan
    Write-Host $border -ForegroundColor DarkGray
}

function Show-Progress {
    param([int]$Current, [int]$Total, [string]$Name, [timespan]$Elapsed)
    $pct = [math]::Round(($Current / $Total) * 100)
    $filled = [math]::Min(20, [math]::Max(0, [int]($pct / 5)))
    $bar = ('#' * $filled) + ('-' * (20 - $filled))
    $m = $Elapsed.Minutes
    $s = $Elapsed.Seconds
    $elapsedStr = '{0:D2}:{1:D2}' -f $m, $s
    Write-Host ""
    Write-Host "  [$bar] $pct%  ($Current/$Total)" -ForegroundColor Green
    Write-Host "  Elapsed: $elapsedStr  |  Current: $Name" -ForegroundColor DarkGray
    Write-Host ""
}

Write-Host ""
Write-Host "  LOA MEGA PIPELINE" -ForegroundColor Yellow
Write-Host "  Target: $targetLabel" -ForegroundColor Cyan
$ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
Write-Host "  $ts" -ForegroundColor DarkGray
Write-Host "  Output: $DumpPath" -ForegroundColor DarkGray
Write-Host ""

$stepNum = 0

# ── Step: Reset ─────────────────────────────────────────────────────────
$stepNum++
$stepName = "Reset ($targetLabel)"
if (-not $SkipReset) {
    Show-Header -StepNum $stepNum -Total $total -Name "$stepName (rebuild + migrate + seed + Swagger)"
    & (Join-Path $PSScriptRoot 'scripts\reset-all.ps1') -Target $Target
    if ($LASTEXITCODE -ne 0) { throw "Reset failed (exit $LASTEXITCODE)" }
    $passed++
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "  [$ts] Step $stepNum complete" -ForegroundColor Green
    Show-Progress -Current $passed -Total $total -Name $stepName -Elapsed ((Get-Date) - $startTime)
} else {
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "  [$ts] [SKIP] Step $stepNum`: $stepName" -ForegroundColor DarkYellow
    $passed++
}

# ── Step: Tests ─────────────────────────────────────────────────────────
$stepNum++
$stepName = "Run Tests ($testLabel)"
if (-not $SkipTests) {
    Show-Header -StepNum $stepNum -Total $total -Name $stepName
    & (Join-Path $PSScriptRoot 'scripts\run-tests.ps1') -Target $Target
    if ($LASTEXITCODE -ne 0) { throw "Tests failed (exit $LASTEXITCODE)" }
    $passed++
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "  [$ts] Step $stepNum complete" -ForegroundColor Green
    Show-Progress -Current $passed -Total $total -Name $stepName -Elapsed ((Get-Date) - $startTime)
} else {
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "  [$ts] [SKIP] Step $stepNum`: $stepName" -ForegroundColor DarkYellow
    $passed++
}

# ── Steps: Dump selected target(s) ──────────────────────────────────────
foreach ($t in $selected) {
    $stepNum++
    $stepName = "Dump $t"
    Show-Header -StepNum $stepNum -Total $total -Name $stepName
    & (Join-Path $PSScriptRoot 'dump.ps1') -Target $t -Path $DumpPath
    if ($LASTEXITCODE -ne 0) { throw "Dump $t failed (exit $LASTEXITCODE)" }
    $passed++
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "  [$ts] Step $stepNum complete" -ForegroundColor Green
    Show-Progress -Current $passed -Total $total -Name $stepName -Elapsed ((Get-Date) - $startTime)
}

# ── Step: Open Builds ───────────────────────────────────────────────────
$stepNum++
Show-Header -StepNum $stepNum -Total $total -Name 'Open Builds Folder'
if (Test-Path -LiteralPath $DumpPath) {
    Start-Process explorer.exe $DumpPath
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "  [$ts] Opened: $DumpPath" -ForegroundColor Green
} else {
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "  [$ts] Directory not found: $DumpPath" -ForegroundColor Yellow
}
$passed++

# ── Summary ─────────────────────────────────────────────────────────────
$elapsed = (Get-Date) - $startTime
$elapsedStr = '{0:D2}:{1:D2}' -f $elapsed.Minutes, $elapsed.Seconds
$ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
Write-Host ""
Write-Host "  ============================================" -ForegroundColor Green
Write-Host "  ALL DONE  ($passed/$total steps completed)" -ForegroundColor Green
Write-Host "  Target:   $targetLabel" -ForegroundColor Green
Write-Host "  Total time: $elapsedStr" -ForegroundColor Green
Write-Host "  Artifacts:  $DumpPath" -ForegroundColor Green
Write-Host "  Completed at: $ts" -ForegroundColor DarkGray
Write-Host "  ============================================" -ForegroundColor Green
Write-Host ""
