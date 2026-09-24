param(
    [Parameter(Mandatory = $true)]
    [string]$Path,

    [switch]$Force
)

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$apps = @('loa-cert-platform', 'loa-auth-platform', 'loa-consult-platform')

foreach ($app in $apps) {
    Write-Host "==> Building dist for $app"
    $buildArgs = @{ Path = $Path }
    if ($Force) { $buildArgs['Force'] = $true }
    & (Join-Path $root "assemblies\$app\generate-dist.ps1") @buildArgs
}

Write-Host ""
Write-Host "All dists built into: $Path"
