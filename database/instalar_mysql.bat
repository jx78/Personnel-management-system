@echo off
chcp 65001 > nul
echo ===============================================================
echo  Instalador de Base de Datos MySQL 8 / MariaDB 10.6 (InnoDB)
echo ===============================================================
echo.
set /p ADMIN_PASS="Ingresa la contrasea de root (deja vaco si no tiene): "
set /p ADMIN_PORT="Ingresa el puerto (por defecto 3306): "

if "%ADMIN_PORT%"=="" set ADMIN_PORT=3306

if exist "%~dp0..\config\php.ini" (
    php -c "%~dp0..\config\php.ini" "%~dp0instalar_mysql.php" "%ADMIN_PASS%" "%ADMIN_PORT%" root
) else (
    php "%~dp0instalar_mysql.php" "%ADMIN_PASS%" "%ADMIN_PORT%" root
)
echo.
pause

