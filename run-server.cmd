@echo off
REM Menjalankan TKJ Profile Web App
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "Set-Location -LiteralPath '%~dp0'; node server.js"
