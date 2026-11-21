@echo off
chcp 65001 >nul 2>&1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0diagnostico.ps1" %*
exit /b %errorlevel%
