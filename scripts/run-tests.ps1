<#
.SYNOPSIS
    Runs the full PHPUnit test suite for both LOA assemblies.

.DESCRIPTION
    Executes PHPUnit inside the Docker containers for the auth-platform and
    cert-platform assemblies.  The auth-platform uses an in-memory SQLite
    database (no setup required).  The cert-platform requires a MySQL test
    database which this script creates if missing.

.PARAMETER AuthFilter
    Optional PHPUnit --filter expression for the auth-platform only.

.PARAMETER CertFilter
    Optional PHPUnit --filter expression for the cert-platform only.

.PARAMETER Filter
    Optional PHPUnit --filter expression applied to BOTH assemblies.

.PARAMETER TestDox
    Pass --testdox to PHPUnit for human-readable output.

.EXAMPLE
    .\scripts\run-tests.ps1
    # Run all tests for both assemblies.

.EXAMPLE
    .\scripts\run-tests.ps1 -Filter AuthSsoTest
    # Run only AuthSsoTest in both assemblies.

.EXAMPLE
    .\scripts\run-tests.ps1 -CertFilter EventTest -TestDox
    # Run EventTest in cert-platform with testdox output.
#>

[CmdletBinding()]
param(
    [string] $AuthFilter,
    [string] $CertFilter,
    [string] $Filter,
    [switch] $TestDox
)

$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

$CertApp   = 'cert-app'
$AuthApp   = 'auth-app'
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

# Ensure cert test database exists
Ensure-TestDatabase -DatabaseName 'loa_cert_test'

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

# ── Done ──────────────────────────────────────────────────────────

Write-Host "`nAll tests passed." -ForegroundColor Green
