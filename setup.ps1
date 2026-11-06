# Script instalasi dependensi TKJ Profile Web App
# Path kelas TKJ di-resolve via wildcard untuk menghindari masalah encoding karakter non-ASCII

$ErrorActionPreference = 'Continue'
$proj = (Get-ChildItem 'C:\Users\yonda\OneDrive\*\GokilTzzy\tkj-profile-app' | Select-Object -First 1).FullName
Set-Location $proj

$log = Join-Path $proj 'install.log'
Write-Host "Project : $proj"
Write-Host "Mulai instalasi dependensi..."

$output = & npm.cmd install 2>&1 | Out-String
Set-Content -Path $log -Value $output -Encoding UTF8

if (Test-Path 'node_modules') {
    $pkgs = (Get-ChildItem 'node_modules' -Directory -ErrorAction SilentlyContinue).Count
    Write-Host "Selesai. $pkgs paket terinstal di node_modules"
} else {
    Write-Host "GAGAL: node_modules tidak terbentuk. Lihat install.log"
}

