$src = "D:\loa\loa-apache-server-apps\assemblies\loa-auth-platform"
$dst = "D:\loa\loa-apache-server-apps\loa-auth-dist"

Remove-Item $dst -Recurse -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path $dst | Out-Null

Get-ChildItem $src | Where-Object { $_.Name -notin @(".git","node_modules","vendor","docker","docker-compose.yml") } | ForEach-Object {
    Copy-Item $_.FullName -Destination $dst -Recurse -Force
}

Get-ChildItem $dst -Recurse -Include ".env*","tests","phpunit.xml","DEPLOY.md" | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
Get-ChildItem $dst -Recurse -Filter "*.md" | Where-Object { $_.Name -ne "README.md" } | Remove-Item -Force -ErrorAction SilentlyContinue

Write-Host "Dist folder ready at: $dst"