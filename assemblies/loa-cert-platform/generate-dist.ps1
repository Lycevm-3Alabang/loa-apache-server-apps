param(
    [Parameter(Mandatory = $true)]
    [string]$Path
)

$ErrorActionPreference = 'Stop'

$app = 'loa-cert-platform'
$dst = Join-Path $Path "$app-dist"

if (-not (Test-Path -LiteralPath $Path)) {
    New-Item -ItemType Directory -Path $Path -Force | Out-Null
}

if (Test-Path -LiteralPath $dst) {
    Remove-Item -LiteralPath $dst -Recurse -Force
}
New-Item -ItemType Directory -Path $dst | Out-Null

Get-ChildItem -LiteralPath $PSScriptRoot | Where-Object {
    $_.Name -notin @(
        '.git', 'node_modules', 'vendor', 'docker', 'docker-compose.yml',
        'cert-app', '.phpunit.cache', '.phpunit.result.cache', 'tmp', 'loa_auth',
        'generate-dist.ps1'
    )
} | ForEach-Object {
    Copy-Item $_.FullName -Destination $dst -Recurse -Force
}

Get-ChildItem $dst -Recurse -Include '.env*', 'tests', 'phpunit.xml', 'phpunit.xml.dist', 'DEPLOY.md' -Force -ErrorAction SilentlyContinue |
    Remove-Item -Recurse -Force -ErrorAction SilentlyContinue

Get-ChildItem $dst -Recurse -Filter '*.md' -Force -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -ne 'README.md' } |
    Remove-Item -Force -ErrorAction SilentlyContinue

foreach ($sub in @('cache', 'cache\data', 'sessions', 'views', 'logs')) {
    $dir = Join-Path $dst "storage\framework\$sub"
    if (Test-Path -LiteralPath $dir) {
        Get-ChildItem -LiteralPath $dir -Force -ErrorAction SilentlyContinue |
            Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
    }
}

$zip = Join-Path $Path "$app-dist.zip"
if (Test-Path -LiteralPath $zip) {
    Remove-Item -LiteralPath $zip -Force
}
Compress-Archive -Path "$dst\*" -DestinationPath $zip

Write-Host "Dist ready:"
Write-Host "  Folder: $dst"
Write-Host "  Zip:    $zip"
