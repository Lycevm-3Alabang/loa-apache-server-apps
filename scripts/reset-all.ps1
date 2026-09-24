<#
.SYNOPSIS
    Full reset of the LOA Docker Compose environment (optionally one target).

.DESCRIPTION
    Tears down EVERY Docker Compose stack defined in this repo (root + auth + cert
    assemblies), removes their volumes, prunes dangling Docker resources, then
    rebuilds and starts the root stack. Provisioning (migrate -> seed -> Swagger)
    runs for the selected target(s) only; consult has no seeds per docker-compose-spec §7.

    Teardown is always global: the assembly-local stacks ('loa-auth', 'loa-cert')
    share host ports (MySQL 33060, nginx 8080/9001, Mailpit 1025/8026, Seq 5341)
    with the root stack. If either is still running, the root stack fails with:
        "Bind for 0.0.0.0:<port> failed: port is already allocated"
    leaving services in 'Created' and `docker compose exec ...` failing with
    'service "<name>" is not running'. This script tears them all down first.
    (Consult has no assembly-local stack — it runs in the root stack only.)

.PARAMETER Target
    Which app(s) to provision: all | auth | cert | consult | consultation.
    consultation is an alias for consult. Default: all.
    Teardown/up remains global regardless of Target.

.PARAMETER SkipProvision
    Only reset infrastructure (down -v + prune + up --build).
    Skip the migrate/seed/Swagger exec steps (which require the apps to be healthy).

.PARAMETER SkipUp
    Only tear down + prune; do not start the stack afterwards.

.EXAMPLE
    .\scripts\reset-all.ps1
    # Full teardown, rebuild, and provisioning of all three apps.

.EXAMPLE
    .\scripts\reset-all.ps1 -Target cert
    # Full teardown + rebuild; provision only the cert app.

.EXAMPLE
    .\scripts\reset-all.ps1 -SkipProvision
    # Tear down + rebuild infrastructure only (e.g. to clear a port conflict).
#>

[CmdletBinding()]
param(
    [ValidateSet('all', 'auth', 'cert', 'consult', 'consultation')]
    [string] $Target = 'all',

    [switch] $SkipProvision,
    [switch] $SkipUp
)

$ErrorActionPreference = 'Stop'

# This script lives in <repo>/scripts, so the repo root is one level up.
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

if ($Target -eq 'consultation') { $Target = 'consult' }

$selected = if ($Target -eq 'all') {
    @('auth', 'cert', 'consult')
} else {
    @($Target)
}

$ComposeAuth = 'assemblies/loa-auth-platform/docker-compose.yml'
$ComposeCert = 'assemblies/loa-cert-platform/docker-compose.yml'

Write-Host "1) Stopping and removing all stacks (containers + volumes)... [provision target: $Target]"
docker compose down -v
docker compose -f $ComposeAuth down -v
docker compose -f $ComposeCert down -v

Write-Host '2) Pruning dangling Docker resources (containers/images/volumes/networks)...'
docker system prune -f --volumes

if ($SkipUp) {
    Write-Host 'SkipUp set: infrastructure torn down, stack not restarted.'
    exit 0
}

Write-Host '3) Rebuilding and starting the root stack...'
docker compose up -d --build

Write-Host "4) Ensuring cache directories exist with correct ownership... [$Target]"
foreach ($app in $selected) {
    $svc = "$app-app"
    docker compose exec $svc mkdir -p /var/www/html/storage/framework/cache/data
    docker compose exec $svc chown -R www-data:www-data /var/www/html/storage/framework/cache
}

if ($SkipProvision) {
    Write-Host 'SkipProvision set: skipping migrate/seed/Swagger. Once the apps are healthy, run:'
    if ($selected -contains 'auth') {
        Write-Host '  docker compose exec auth-app php artisan migrate --force'
        Write-Host '  docker compose exec auth-app php artisan db:seed --force'
        Write-Host '  docker compose exec auth-app php artisan l5-swagger:generate'
    }
    if ($selected -contains 'cert') {
        Write-Host '  docker compose exec cert-app php artisan migrate --force'
        Write-Host '  docker compose exec cert-app php artisan db:seed --force'
        Write-Host '  docker compose exec cert-app php artisan l5-swagger:generate'
    }
    if ($selected -contains 'consult') {
        Write-Host '  docker compose exec consult-app php artisan migrate --force'
        Write-Host '  docker compose exec consult-app php artisan l5-swagger:generate'
        Write-Host '  (consult has no db:seed — no seeds per docker-compose-spec §7)'
    }
    exit 0
}

$step = 5

if ($selected -contains 'auth') {
    Write-Host "$step) Installing dependencies + migrating and seeding the auth app..."
    docker compose exec auth-app composer install --no-interaction
    docker compose exec auth-app php artisan migrate --force
    docker compose exec auth-app php artisan db:seed --force
    docker compose exec auth-app php artisan l5-swagger:generate
    $step++
}

if ($selected -contains 'cert') {
    Write-Host "$step) Installing dependencies + migrating and seeding the cert app..."
    docker compose exec cert-app composer install --no-interaction
    docker compose exec cert-app php artisan migrate --force
    docker compose exec cert-app php artisan db:seed --force
    docker compose exec cert-app php artisan l5-swagger:generate
    $step++
}

if ($selected -contains 'consult') {
    Write-Host "$step) Installing dependencies + migrating the consult app (no seeds per docker-compose-spec §7)..."
    docker compose exec consult-app composer install --no-interaction
    docker compose exec consult-app php artisan migrate --force
    # Swagger is best-effort for consult: app/ has no #[OA\Info] attributes yet,
    # so l5-swagger:generate exits 1. Warn and continue — the API is unaffected.
    docker compose exec consult-app php artisan l5-swagger:generate
    if ($LASTEXITCODE -ne 0) {
        Write-Warning 'consult l5-swagger:generate skipped (no @OA\Info in app/ yet) — continuing.'
        # NOTE: must write session-global. A bare `$LASTEXITCODE = 0` only shadows
        # it in this script's scope and mega.ps1 would still read exit 1.
        $global:LASTEXITCODE = 0
    }
    $step++
}

Write-Host 'Done.  Auth UI: http://localhost:8080  |  Cert UI: http://localhost:9001  |  Consult API: http://localhost:9002  |  Seq: http://localhost:5341'
