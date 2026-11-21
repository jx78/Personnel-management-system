@echo off
chcp 65001 >nul 2>&1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0restablecer_admin.ps1" %*
exit /b %errorlevel%
