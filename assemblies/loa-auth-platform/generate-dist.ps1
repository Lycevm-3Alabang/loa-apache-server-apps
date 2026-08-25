param(
    [Parameter(Mandatory = $true)]
    [string]$Path,

    [switch]$SkipVendor,

    [switch]$Force
)

$ErrorActionPreference = 'Stop'

$app = 'loa-auth-platform'
$container = 'loa-platform-auth-app-1'
$dst = Join-Path $Path "$app-dist"
$stageRoot = Join-Path $PSScriptRoot '.dist-stage'
$stage = Join-Path $stageRoot $app

if (-not (Test-Path -LiteralPath $Path)) {
    New-Item -ItemType Directory -Path $Path -Force | Out-Null
}

$rebuild = $Force -or -not (Test-Path -LiteralPath $dst)

if ($rebuild) {
    if (Test-Path -LiteralPath $dst) {
        Remove-Item -LiteralPath $dst -Recurse -Force
    }
    if (Test-Path -LiteralPath $stage) {
        Remove-Item -LiteralPath $stage -Recurse -Force
    }
    New-Item -ItemType Directory -Path $stage | Out-Null

    Get-ChildItem -LiteralPath $PSScriptRoot | Where-Object {
        $_.Name -notin @(
            '.git', 'node_modules', 'vendor', 'docker', 'docker-compose.yml',
            '.phpunit.cache', '.phpunit.result.cache', 'tmp', 'loa_auth',
            '.dist-stage', 'generate-dist.ps1'
        )
    } | ForEach-Object {
        Copy-Item $_.FullName -Destination $stage -Recurse -Force
    }

    Get-ChildItem $stage -Recurse -Include '.env*', 'tests', 'phpunit.xml', 'phpunit.xml.dist', 'DEPLOY.md' -Force -ErrorAction SilentlyContinue |
        Remove-Item -Recurse -Force -ErrorAction SilentlyContinue

    Get-ChildItem $stage -Recurse -Filter '*.md' -Force -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -ne 'README.md' } |
        Remove-Item -Force -ErrorAction SilentlyContinue

    foreach ($sub in @('cache', 'cache\data', 'sessions', 'views', 'logs')) {
        $dir = Join-Path $stage "storage\framework\$sub"
        if (Test-Path -LiteralPath $dir) {
            Get-ChildItem -LiteralPath $dir -Force -ErrorAction SilentlyContinue |
                Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
        }
    }

    if (-not $SkipVendor) {
        Write-Host 'Installing production dependencies (linux, via docker)...'
        $prevEap = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        docker exec $container composer install --no-dev --optimize-autoloader --working-dir "/var/www/html/.dist-stage/$app" --no-interaction --prefer-dist
        $composerExit = $LASTEXITCODE
        $ErrorActionPreference = $prevEap
        if ($composerExit -ne 0) {
            throw "composer install failed inside $container (exit $composerExit)"
        }
    }

    Move-Item -LiteralPath $stage -Destination $dst
    if (-not (Get-ChildItem -LiteralPath $stageRoot -Force -ErrorAction SilentlyContinue)) {
        Remove-Item -LiteralPath $stageRoot -Recurse -Force
    }
}
else {
    Write-Host "Existing dist found at '$dst' - rebuilding zip only (pass -Force to rebuild)."
    if (-not (Test-Path -LiteralPath (Join-Path $dst 'vendor\autoload.php'))) {
        Write-Host 'WARNING: existing dist has no vendor\autoload.php - the zip may be incomplete.'
    }
}

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

Write-Host "Dist ready:"
Write-Host "  Folder: $dst"
Write-Host "  Zip:    $zip"
