# Skrip bantu: jalankan server di background lalu uji endpoint
$ErrorActionPreference = 'Continue'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $root

$outLog = Join-Path $root 'server-test.log'
$errLog = Join-Path $root 'server-test.err'

if (Test-Path $outLog) { Remove-Item $outLog -Force }
if (Test-Path $errLog) { Remove-Item $errLog -Force }

$p = Start-Process -FilePath 'node' -ArgumentList 'server.js' -WindowStyle Hidden -PassThru -RedirectStandardOutput $outLog -RedirectStandardError $errLog
Write-Output ("PID: " + $p.Id)
Start-Sleep -Seconds 3
Write-Output '--- STDOUT ---'
if (Test-Path $outLog) { Get-Content $outLog -Tail 20 }
Write-Output '--- STDERR ---'
if (Test-Path $errLog) { Get-Content $errLog -Tail 20 }
