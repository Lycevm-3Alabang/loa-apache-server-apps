<#
.SYNOPSIS
    All-in-one pipeline: reset → test → dump auth → dump cert → dump consult → open builds folder.

.DESCRIPTION
    Runs the full LOA workflow in one go with visible progress:
      1. Reset all (teardown + rebuild + migrate + seed + Swagger)
      2. Run all tests (auth + cert + consult)
      3. Dump auth (dist build + SQL regeneration)
      4. Dump cert (dist build + SQL regeneration)
      5. Dump consult (dist build + SQL regeneration)
      6. Open D:\builds

.PARAMETER SkipReset
    Skip the reset-all step (useful when infra is already fresh).

.PARAMETER SkipTests
    Skip the test step.

.PARAMETER DumpPath
    Output directory for dump artifacts. Defaults to D:\builds.

.EXAMPLE
    .\mega.ps1
    .\mega.ps1 -SkipReset
    .\mega.ps1 -DumpPath E:\builds
#>

[CmdletBinding()]
param(
    [switch]$SkipReset,
    [switch]$SkipTests,
    [string]$DumpPath = 'D:\builds'
)

$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

$steps = @(
    @{ Name = 'Reset All';       Skip = $SkipReset },
    @{ Name = 'Run Tests';       Skip = $SkipTests },
    @{ Name = 'Dump Auth';       Skip = $false },
    @{ Name = 'Dump Cert';       Skip = $false },
    @{ Name = 'Dump Consult';    Skip = $false },
    @{ Name = 'Open Builds';     Skip = $false }
)

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
    $filled = $pct / 5
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
$ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
Write-Host "  $ts" -ForegroundColor DarkGray
Write-Host "  Output: $DumpPath" -ForegroundColor DarkGray
Write-Host ""

# ── Step 1: Reset ───────────────────────────────────────────────────────
if (-not $SkipReset) {
    Show-Header -StepNum 1 -Total $total -Name 'Reset All (rebuild + migrate + seed + Swagger)'
    & (Join-Path $PSScriptRoot 'scripts\reset-all.ps1')
    if ($LASTEXITCODE -ne 0) { throw "Reset failed (exit $LASTEXITCODE)" }
    $passed++
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "  [$ts] Step 1 complete" -ForegroundColor Green
    Show-Progress -Current $passed -Total $total -Name 'Reset All' -Elapsed ((Get-Date) - $startTime)
} else {
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "  [$ts] [SKIP] Step 1: Reset All" -ForegroundColor DarkYellow
    $passed++
}

# ── Step 2: Tests ───────────────────────────────────────────────────────
if (-not $SkipTests) {
    Show-Header -StepNum 2 -Total $total -Name 'Run Tests (auth + cert + consult)'
    & (Join-Path $PSScriptRoot 'scripts\run-tests.ps1')
    if ($LASTEXITCODE -ne 0) { throw "Tests failed (exit $LASTEXITCODE)" }
    $passed++
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "  [$ts] Step 2 complete" -ForegroundColor Green
    Show-Progress -Current $passed -Total $total -Name 'Run Tests' -Elapsed ((Get-Date) - $startTime)
} else {
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "  [$ts] [SKIP] Step 2: Run Tests" -ForegroundColor DarkYellow
    $passed++
}

# ── Step 3: Dump Auth ───────────────────────────────────────────────────
Show-Header -StepNum 3 -Total $total -Name 'Dump Auth'
& (Join-Path $PSScriptRoot 'dump.ps1') -Target auth -Path $DumpPath
if ($LASTEXITCODE -ne 0) { throw "Dump auth failed (exit $LASTEXITCODE)" }
$passed++
$ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
Write-Host "  [$ts] Step 3 complete" -ForegroundColor Green
Show-Progress -Current $passed -Total $total -Name 'Dump Auth' -Elapsed ((Get-Date) - $startTime)

# ── Step 4: Dump Cert ──────────────────────────────────────────────────
Show-Header -StepNum 4 -Total $total -Name 'Dump Cert'
& (Join-Path $PSScriptRoot 'dump.ps1') -Target cert -Path $DumpPath
if ($LASTEXITCODE -ne 0) { throw "Dump cert failed (exit $LASTEXITCODE)" }
$passed++
$ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
Write-Host "  [$ts] Step 4 complete" -ForegroundColor Green
Show-Progress -Current $passed -Total $total -Name 'Dump Cert' -Elapsed ((Get-Date) - $startTime)

# ── Step 5: Dump Consult ───────────────────────────────────────────────
Show-Header -StepNum 5 -Total $total -Name 'Dump Consult'
& (Join-Path $PSScriptRoot 'dump.ps1') -Target consult -Path $DumpPath
if ($LASTEXITCODE -ne 0) { throw "Dump consult failed (exit $LASTEXITCODE)" }
$passed++
$ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
Write-Host "  [$ts] Step 5 complete" -ForegroundColor Green
Show-Progress -Current $passed -Total $total -Name 'Dump Consult' -Elapsed ((Get-Date) - $startTime)

# ── Step 6: Open Builds ────────────────────────────────────────────────
Show-Header -StepNum 6 -Total $total -Name 'Open Builds Folder'
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
Write-Host "  Total time: $elapsedStr" -ForegroundColor Green
Write-Host "  Artifacts:  $DumpPath" -ForegroundColor Green
Write-Host "  Completed at: $ts" -ForegroundColor DarkGray
Write-Host "  ============================================" -ForegroundColor Green
Write-Host ""
