@echo off
chcp 65001 >nul
:: ==============================================================================
:: Lanzador de Restablecimiento de ContraseÃ±a Root de MySQL a 'ana1'
:: ==============================================================================
title Restablecer ContraseÃ±a Root MySQL a ana1
color 0b

echo ============================================================
echo   RESTABLECIMIENTO DE CONTRASEÃ‘A ROOT MYSQL A 'ana1'
echo ============================================================
echo.

net session >nul 2>&1
if %errorLevel% == 0 (
    echo [OK] Ejecutando con privilegios de Administrador...
    echo.
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0restablecer_root_mysql.ps1"
) else (
    echo Solicitando elevaciÃ³n de Administrador (UAC)...
    echo Haz clic en "SÃ­" en la ventana de confirmaciÃ³n de Windows.
    echo.
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell.exe -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File \"\"%~dp0restablecer_root_mysql.ps1\"\"' -Verb RunAs -Wait"
)

if exist "%~dp0..\reset_mysql_log.txt" (
    echo.
    echo ============================================================
    echo   REPORTE DE EJECUCIÃ“N:
    echo ============================================================
    powershell.exe -NoProfile -Command "Get-Content '%~dp0..\reset_mysql_log.txt' -Tail 15"
)

echo.
echo Presione cualquier tecla para salir...
pause >nul

