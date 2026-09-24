<#
.SYNOPSIS
  Compares assembly files against D:\builds\<app>-dist by content.
  Packages only differing/new files. If no dist exists, builds it first.

.EXAMPLE
  ./hotfix-dump.ps1 -Target auth
  ./hotfix-dump.ps1 -Target cert
#>
param(
    [ValidateSet('auth', 'cert')]
    [string]$Target = 'auth',

    [string]$OutRoot = 'D:\builds\hotfix'
)

$ErrorActionPreference = 'Stop'

function Main {
    $repoRoot = $PSScriptRoot

    $targets = @{
        auth = 'loa-auth-platform'
        cert = 'loa-cert-platform'
    }

    $app        = $targets[$Target]
    $appDir     = Join-Path $repoRoot "assemblies\$app"
    $distBase   = 'D:\builds'
    $distDir    = Join-Path $distBase "$app-dist"

    # ── If no dist exists, build it first via dump.ps1 ──────────────────
    if (-not (Test-Path -LiteralPath $distDir)) {
        Write-Host "No existing dist at $distDir - running dump.ps1 -Target $Target ..."
        & (Join-Path $repoRoot 'dump.ps1') -Target $Target -Path $distBase
        Write-Host ""
        Write-Host "Full dist created. Run this script again next time for hotfix-only packaging."
        return
    }

    # ── Diff source against dist: modified + new files ──────────────────
    #    Cache, dev artifacts, and non-prod files are excluded.
    $skipDirs  = @('vendor', 'storage', 'node_modules', '.git', 'bootstrap\cache', 'tmp')
    $skipFiles = @('.env', '.env.backup', '.env.local', '.env.production', '.env.cpanel',
                   '.phpunit.result.cache', 'phpunit.xml', 'phpunit.xml.dist',
                   'DEPLOY.md', 'docker-compose.yml')
    $skipExt   = @('.md')
    $changed   = @()

    # ── Pass 1: files in dist that differ (M) ──────────────────────────
    Get-ChildItem -LiteralPath $distDir -Recurse -File | Where-Object {
        $rel = $_.FullName.Substring($distDir.Length).TrimStart('\')
        foreach ($d in $skipDirs) {
            if ($rel -match "^$([regex]::Escape($d))\\") { return $false }
        }
        return $true
    } | ForEach-Object {
        $relative = $_.FullName.Substring($distDir.Length).TrimStart('\', '/')
        $srcPath  = Join-Path $appDir $relative

        if (-not (Test-Path -LiteralPath $srcPath)) { return }

        $baseName = Split-Path $relative -Leaf
        if ($baseName -in $skipFiles) { return }
        if ([IO.Path]::GetExtension($baseName) -in $skipExt -and $baseName -ne 'README.md') { return }

        try {
            $srcHash = (Get-FileHash -LiteralPath $srcPath -Algorithm MD5 -ErrorAction Stop).Hash
            $dstHash = (Get-FileHash -LiteralPath $_.FullName -Algorithm MD5 -ErrorAction Stop).Hash
        } catch { return }

        if ($srcHash -ne $dstHash) {
            $changed += [pscustomobject]@{
                Status  = 'M'
                File    = $relative
                SrcPath = $srcPath
            }
        }
    }

    # ── Pass 2: new files in source not in dist (A) ────────────────────
    Get-ChildItem -LiteralPath $appDir -Recurse -File | Where-Object {
        $rel = $_.FullName.Substring($appDir.Length).TrimStart('\')
        foreach ($d in $skipDirs) {
            if ($rel -match "^$([regex]::Escape($d))\\") { return $false }
        }
        return $true
    } | ForEach-Object {
        $relative = $_.FullName.Substring($appDir.Length).TrimStart('\', '/')
        $distPath = Join-Path $distDir $relative

        if (Test-Path -LiteralPath $distPath) { return }

        $baseName = Split-Path $relative -Leaf
        if ($baseName -in $skipFiles) { return }
        if ([IO.Path]::GetExtension($baseName) -in $skipExt -and $baseName -ne 'README.md') { return }

        $changed += [pscustomobject]@{
            Status  = 'A'
            File    = $relative
            SrcPath = $_.FullName
        }
    }

    if ($changed.Count -eq 0) {
        Write-Host "[$Target] All dist files match source. Nothing to package."
        return
    }

    Write-Host "[$Target] $($changed.Count) file(s) differ from dist:"
    $changed | ForEach-Object { Write-Host "  [$($_.Status)] $($_.File)" }

    # ── Copy to temp stage preserving the dist's folder structure ───────
    $stamp = Get-Date -Format 'yyyy-MM-dd_HHmm'
    $name  = "$app-hotfix-$stamp"
    $stage = Join-Path ([IO.Path]::GetTempPath()) $name
    New-Item -ItemType Directory -Force -Path $stage | Out-Null

    $head = git log --oneline -1

    foreach ($f in $changed) {
        $dest = Join-Path $stage $f.File
        New-Item -ItemType Directory -Force -Path (Split-Path $dest) | Out-Null
        Copy-Item $f.SrcPath $dest -Force
    }

    # ── Write manifest ──────────────────────────────────────────────────
    $manifest = @"
HOTFIX DUMP - $Target ($app)
Generated : $(Get-Date)
HEAD      : $head
Dist base : $distDir
Files     : $($changed.Count)

$(($changed | Format-Table Status, File -AutoSize | Out-String).TrimEnd())
"@

    Set-Content (Join-Path $stage 'MANIFEST.txt') $manifest -Encoding UTF8

    # ── Zip ─────────────────────────────────────────────────────────────
    New-Item -ItemType Directory -Force -Path $OutRoot | Out-Null

    $zip = Join-Path $OutRoot "$name.zip"
    if (Test-Path -LiteralPath $zip) {
        throw "Refusing to overwrite existing $zip"
    }

    Compress-Archive -Path (Join-Path $stage '*') -DestinationPath $zip -Force:$false
    Remove-Item $stage -Recurse -Force

    Write-Host ""
    Write-Host "CREATED: $zip ($($changed.Count) files)"
    $changed | Format-Table Status, File -AutoSize
}

Main
