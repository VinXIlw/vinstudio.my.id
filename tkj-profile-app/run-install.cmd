@echo off
REM Launcher untuk instalasi dependensi (menghindari masalah encoding path non-ASCII)
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup.ps1"
exit /b %ERRORLEVEL%
