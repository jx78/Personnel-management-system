@echo off
chcp 65001 >nul
title Servidor Web - Sistema de Gestión de Personal
color 0a

cd /d "%~dp0"

where powershell.exe >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] No se encontró PowerShell en el sistema.
    pause
    exit /b 1
)

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0iniciar_servidor.ps1" %*
