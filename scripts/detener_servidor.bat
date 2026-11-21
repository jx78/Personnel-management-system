@echo off
chcp 65001 >nul 2>&1
echo Deteniendo procesos del Sistema de Empleados y PHP...
taskkill /F /IM SistemaEmpleados.exe >nul 2>&1
taskkill /F /IM SistemaEmpleados_old.exe >nul 2>&1
taskkill /F /IM php.exe >nul 2>&1
if exist "%~dp0SistemaEmpleados_old.exe" del /f /q "%~dp0SistemaEmpleados_old.exe" >nul 2>&1
echo [OK] Servidor detenido y puerto liberado.
timeout /t 2 >nul

