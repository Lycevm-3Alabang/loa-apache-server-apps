<#
.SYNOPSIS
  Builds sanitized deployable dist folders + zips for LOA assemblies.

.EXAMPLE
  ./dump.ps1 --target auth
  ./dump.ps1 --target cert
  ./dump.ps1 -Target auth -Path D:\somewhere-else
#>
param(
    [ValidateSet('auth', 'cert')]
    [string]$Target = 'auth',

    # Default dump destination when not supplied.
    [string]$Path = 'D:\builds',

    [switch]$SkipVendor
)

$ErrorActionPreference = 'Stop'

$repoRoot = $PSScriptRoot

$targets = @{
    auth = @{ App = 'loa-auth-platform'; Container = 'loa-platform-auth-app-1' }
    cert = @{ App = 'loa-cert-platform'; Container = 'loa-platform-cert-app-1' }
}

$t       = $targets[$Target]
$app     = $t.App
$container = $t.Container
$appRoot   = Join-Path $repoRoot "assemblies\$app"
$dst       = Join-Path $Path "$app-dist"
$stageRoot = Join-Path $appRoot '.dist-stage'
$stage     = Join-Path $stageRoot $app

if (-not (Test-Path -LiteralPath $Path)) {
    New-Item -ItemType Directory -Path $Path -Force | Out-Null
}

# ── Stage a fresh sanitized copy ────────────────────────────────────────
if (Test-Path -LiteralPath $stage) {
    Remove-Item -LiteralPath $stage -Recurse -Force
}
New-Item -ItemType Directory -Path $stage | Out-Null

Get-ChildItem -LiteralPath $appRoot | Where-Object {
    $_.Name -notin @(
        '.git', 'node_modules', 'vendor', 'docker', 'docker-compose.yml',
        '.phpunit.cache', '.phpunit.result.cache', 'tmp', 'loa_auth',
        '.dist-stage', 'generate-dist.ps1'
    )
} | ForEach-Object {
    Copy-Item $_.FullName -Destination $stage -Recurse -Force
}

# Strip secrets, tests, IDE junk from the stage.
Get-ChildItem $stage -Recurse -Include '.env*', 'tests', 'phpunit.xml', 'phpunit.xml.dist', 'DEPLOY.md' -Force -ErrorAction SilentlyContinue |
    Remove-Item -Recurse -Force -ErrorAction SilentlyContinue

foreach ($sub in @('cache', 'cache\data', 'sessions', 'views', 'logs')) {
    $dir = Join-Path $stage "storage\framework\$sub"
    if (Test-Path -LiteralPath $dir) {
        Get-ChildItem -LiteralPath $dir -Force -ErrorAction SilentlyContinue |
            Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
    }
}

# ── Production vendor (linux binaries) via the assembly's own container ─
if (-not $SkipVendor) {
    Write-Host "Installing production dependencies for '$Target' (linux, via docker)..."
    $prevEap = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    # COMPOSER_PROCESS_TIMEOUT: default 300s aborts single large package
    # downloads on slow uplinks (exit 1 mid-install).
    docker exec -e COMPOSER_PROCESS_TIMEOUT=3600 $container composer install --no-dev --optimize-autoloader --working-dir "/var/www/html/.dist-stage/$app" --no-interaction --prefer-dist
    $composerExit = $LASTEXITCODE
    $ErrorActionPreference = $prevEap
    if ($composerExit -ne 0) {
        throw "composer install failed inside $container (exit $composerExit)"
    }
}

# ── Post-vendor doc purge ───────────────────────────────────────────────
# MUST run after composer install (vendor re-downloads package docs).
# All markdown + AI cruft goes; only a root-level README survives.
if (-not $SkipVendor) {
    $rootReadme = Join-Path $stage 'README.md'
    Get-ChildItem $stage -Recurse -Include '*.md', '.claude' -Force -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -ne $rootReadme } |
        Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
}

# ── Upsert into the dist folder (incremental mirror: fast re-runs) ──────
New-Item -ItemType Directory -Path $dst -Force | Out-Null
robocopy $stage $dst /MIR /NFL /NDL /NJH /NJS /NP | Out-Null
$robocopyExit = $LASTEXITCODE
if ($robocopyExit -ge 8) {
    throw "robocopy mirror into '$dst' failed (exit $robocopyExit)"
}
Remove-Item -LiteralPath $stageRoot -Recurse -Force -ErrorAction SilentlyContinue

# ── Zip (sanitized, no password) ────────────────────────────────────────
$zip = Join-Path $Path "$app-dist.zip"
if (Test-Path -LiteralPath $zip) {
    Remove-Item -LiteralPath $zip -Force
}

$sevenZip = $null
foreach ($root in @($env:ProgramFiles, ${env:ProgramFiles(x86)}, $env:LOCALAPPDATA)) {
    if ($root) {
        $candidate = Join-Path $root '7-Zip\7z.exe'
        if (Test-Path -LiteralPath $candidate) {
            $sevenZip = $candidate
            break
        }
    }
}
if (-not $sevenZip) {
    $cmd = Get-Command 7z -ErrorAction SilentlyContinue
    if ($cmd) { $sevenZip = $cmd.Source }
}
if (-not $sevenZip) {
    throw '7-Zip not found. Install 7-Zip to build the sanitized dist archive.'
}

$excludeArgs = @('ade', 'adp', 'bat', 'chm', 'cmd', 'com', 'cpl', 'exe', 'hta', 'ins', 'isp', 'jse', 'lib', 'lnk', 'mde', 'msd', 'msp', 'mst', 'pif', 'scr', 'sct', 'shb', 'sys', 'vb', 'vbe', 'vbs', 'vxd', 'wsc', 'wsf', 'wsh') | ForEach-Object { "-xr!*.$_" }
& $sevenZip a -tzip $zip (Join-Path $dst '*') @excludeArgs
if ($LASTEXITCODE -ne 0) {
    throw "7-Zip failed to create the archive (exit $LASTEXITCODE)"
}

Write-Host "Dist ready ($Target):"
Write-Host "  Folder: $dst"
Write-Host "  Zip:    $zip"

# ── Regenerate cPanel SQL installer from Docker schema ───────────────────
Write-Host "Regenerating cPanel SQL installer for '$Target'..."

$dockerDb = @{ auth = 'loa_auth'; cert = 'loa_cert' }[$Target]
$cpanelDb = @{ auth = 'lyceumalabang_auth_db'; cert = 'lyceumalabang_e_cert_db' }[$Target]
$sqlOut   = Join-Path $appRoot "database\sql\cpanel-$Target-db-install.sql"

# Tables to exclude entirely (runtime data, not needed on cPanel).
$excludeTables = @(
    'sessions', 'jobs', 'failed_jobs', 'password_reset_tokens',
    'password_set_tokens', 'refresh_tokens', 'login_attempts',
    'activations', 'users', 'audit_logs', 'user_tenants',
    'user_user_group', 'user_permission', 'user_claim_overrides',
    'tenant_endpoint_overrides', 'tenant_api_keys', 'cache', 'cache_locks'
)

# Build mysqldump exclude args
$mysqlExcludeArgs = @()
foreach ($t in $excludeTables) {
    $mysqlExcludeArgs += "--ignore-table=$dockerDb.$t"
}

# Dump schema + seed data in one pass (exclude runtime tables)
$rawDump = docker exec loa-platform-mysql-1 mysqldump `
    -uroot -proot-secret `
    --skip-extended-insert `
    --complete-insert `
    --routines `
    --single-transaction `
    $mysqlExcludeArgs `
    $dockerDb 2>$null

if ($LASTEXITCODE -ne 0) {
    Write-Warning "mysqldump failed for $dockerDb - skipping SQL regeneration."
} else {
    # Process: replace DB name, add DROP TABLE IF EXISTS, remove USE statement
    $processed = @()
    foreach ($line in $rawDump) {
        $out = $line

        # Replace database name references
        $out = $out -replace [regex]::Escape($dockerDb), $cpanelDb

        # Add DROP TABLE IF EXISTS before each CREATE TABLE
        if ($out -match '^\s*CREATE TABLE') {
            $tbl = $out -replace '^\s*CREATE TABLE\s+`(\w+)`.*', '$1'
            $processed += ""
            $processed += "DROP TABLE IF EXISTS ``$tbl``;"
        }

        $processed += $out
    }

    # Remove USE statements (importer selects DB in phpMyAdmin)
    $processed = $processed | Where-Object { $_ -notmatch '^USE\s' }

    # Write the file
    $fullSql = $processed -join "`n"
    [System.IO.File]::WriteAllText($sqlOut, $fullSql, [System.Text.UTF8Encoding]::new($false))
    Write-Host "  SQL regenerated: $sqlOut"
}

# ── Copy the SQL installer to the output directory ─────────────────────
$sqlSrc = Join-Path $PSScriptRoot "assemblies\$app\database\sql\cpanel-$Target-db-install.sql"
if (Test-Path $sqlSrc) {
    Copy-Item $sqlSrc -Destination $Path -Force
    Write-Host "  SQL:    $(Join-Path $Path "cpanel-$Target-db-install.sql")"
} else {
    Write-Warning "SQL installer not found: $sqlSrc"
}
