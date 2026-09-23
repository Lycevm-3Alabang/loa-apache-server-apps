<#
.SYNOPSIS
    Runs the full PHPUnit test suite for all three LOA assemblies.

.DESCRIPTION
    Executes tests inside the Docker containers for the auth-platform,
    cert-platform, and consult-platform assemblies.  The auth-platform uses an
    in-memory SQLite database (no setup required).  The cert-platform and
    consult-platform require MySQL test databases which this script creates
    if missing (loa_cert_test, loa_consult_test). Suites run sequentially —
    never overlapping — since they share the MySQL server.

.PARAMETER AuthFilter
    Optional PHPUnit --filter expression for the auth-platform only.

.PARAMETER CertFilter
    Optional PHPUnit --filter expression for the cert-platform only.

.PARAMETER ConsultFilter
    Optional --filter expression for the consult-platform only.

.PARAMETER Filter
    Optional PHPUnit --filter expression applied to ALL assemblies.

.PARAMETER TestDox
    Pass --testdox to PHPUnit for human-readable output.

.EXAMPLE
    .\scripts\run-tests.ps1
    # Run all tests for all three assemblies.

.EXAMPLE
    .\scripts\run-tests.ps1 -Filter AuthSsoTest
    # Run only AuthSsoTest in all assemblies.

.EXAMPLE
    .\scripts\run-tests.ps1 -CertFilter EventTest -TestDox
    # Run EventTest in cert-platform with testdox output.

.EXAMPLE
    .\scripts\run-tests.ps1 -ConsultFilter AppointmentTest
    # Run AppointmentTest in consult-platform only.
#>

[CmdletBinding()]
param(
    [string] $AuthFilter,
    [string] $CertFilter,
    [string] $ConsultFilter,
    [string] $Filter,
    [switch] $TestDox
)

$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

$CertApp   = 'cert-app'
$AuthApp   = 'auth-app'
$ConsultApp = 'consult-app'
$Mysql     = 'mysql'

# ── Helpers ───────────────────────────────────────────────────────

function Invoke-Container {
    param(
        [string] $Service,
        [string] $Description,
        [string[]] $Command
    )

    Write-Host "`n>> $Description" -ForegroundColor Cyan
    Write-Host "   docker compose exec $Service $($Command -join ' ')" -ForegroundColor DarkGray

    & docker compose exec $Service @Command
    if ($LASTEXITCODE -ne 0) {
        throw "$Description failed (exit code $LASTEXITCODE)"
    }
}

function Ensure-TestDatabase {
    param([string] $DatabaseName)

    $prevEAP = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    $existing = & docker compose exec $Mysql mysql -uroot -proot-secret -N -e "SHOW DATABASES LIKE '$DatabaseName'" 2>$null
    $ErrorActionPreference = $prevEAP

    if ($existing -notmatch [regex]::Escape($DatabaseName)) {
        Write-Host "   Creating test database '$DatabaseName'..." -ForegroundColor DarkYellow
        $ErrorActionPreference = 'Continue'
        & docker compose exec $Mysql mysql -uroot -proot-secret -e "CREATE DATABASE IF NOT EXISTS $DatabaseName" 2>$null | Out-Null
        $ErrorActionPreference = $prevEAP
        if ($LASTEXITCODE -ne 0) {
            throw "Failed to create database $DatabaseName"
        }
    }
}

# ── Pre-flight ────────────────────────────────────────────────────

Write-Host 'LOA Test Runner' -ForegroundColor Green
Write-Host "Root: $Root" -ForegroundColor DarkGray

# Ensure cert + consult test databases exist
Ensure-TestDatabase -DatabaseName 'loa_cert_test'
Ensure-TestDatabase -DatabaseName 'loa_consult_test'

# ── Auth-platform tests ───────────────────────────────────────────

$authArgs = @('php', 'vendor/bin/phpunit')
if ($TestDox) { $authArgs += '--testdox' }
if ($Filter)  { $authArgs += @('--filter', $Filter) }
if ($AuthFilter) { $authArgs += @('--filter', $AuthFilter) }

Write-Host "`n============================================" -ForegroundColor Yellow
Write-Host ' Auth-platform tests' -ForegroundColor Yellow
Write-Host '============================================' -ForegroundColor Yellow

Invoke-Container -Service $AuthApp -Description 'Auth-platform PHPUnit' -Command $authArgs

# ── Cert-platform tests ───────────────────────────────────────────

$certArgs = @('php', 'vendor/bin/phpunit')
if ($TestDox) { $certArgs += '--testdox' }
if ($Filter)  { $certArgs += @('--filter', $Filter) }
if ($CertFilter) { $certArgs += @('--filter', $CertFilter) }

Write-Host "`n============================================" -ForegroundColor Yellow
Write-Host ' Cert-platform tests' -ForegroundColor Yellow
Write-Host '============================================' -ForegroundColor Yellow

Invoke-Container -Service $CertApp -Description 'Cert-platform PHPUnit' -Command $certArgs

# ── Consult-platform tests ────────────────────────────────────────
# Consult runs via `php artisan test` per test-suite.md D-7 (RefreshDatabase
# against loa_consult_test; bootstrap pins the test DB).

$consultArgs = @('php', 'artisan', 'test')
if ($TestDox) { $consultArgs += '--testdox' }
if ($Filter)  { $consultArgs += @('--filter', $Filter) }
if ($ConsultFilter) { $consultArgs += @('--filter', $ConsultFilter) }

Write-Host "`n============================================" -ForegroundColor Yellow
Write-Host ' Consult-platform tests' -ForegroundColor Yellow
Write-Host '============================================' -ForegroundColor Yellow

Invoke-Container -Service $ConsultApp -Description 'Consult-platform artisan test' -Command $consultArgs

# ── Done ──────────────────────────────────────────────────────────

Write-Host "`nAll tests passed." -ForegroundColor Green
