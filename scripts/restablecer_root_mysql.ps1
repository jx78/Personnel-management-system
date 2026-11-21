# ==============================================================================
# Script de Restablecimiento de ContraseÃ±a Root de MySQL a 'ana1'
# Compatible con MySQL 8.0 y MySQL 26.7 / 8.4+ en Windows
# ==============================================================================

#Requires -Version 5.1
$ErrorActionPreference = "Continue"

$targetPassword = "ana1"
$logFile = Join-Path (Split-Path $PSScriptRoot) "reset_mysql_log.txt"

function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $timestamp = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    $logEntry = "[$timestamp] [$Level] $Message"
    Add-Content -Path $logFile -Value $logEntry -Encoding utf8
    
    switch ($Level) {
        "SUCCESS" { Write-Host $Message -ForegroundColor Green }
        "WARNING" { Write-Host $Message -ForegroundColor Yellow }
        "ERROR"   { Write-Host $Message -ForegroundColor Red }
        Default   { Write-Host $Message -ForegroundColor Cyan }
    }
}

# Inicializar archivo de log
"=====================================================" | Out-File -FilePath $logFile -Encoding utf8
" INICIO DE RESTABLECIMIENTO DE CONTRASEÃ‘A ROOT A '$targetPassword'" | Out-File -FilePath $logFile -Append -Encoding utf8
"=====================================================" | Out-File -FilePath $logFile -Append -Encoding utf8

# 1. Comprobar elevaciÃ³n (Privilegios de Administrador)
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Log "El script no tiene privilegios de Administrador. Solicitando elevaciÃ³n UAC..." "WARNING"
    try {
        $process = Start-Process powershell.exe -ArgumentList "-ExecutionPolicy Bypass -NoProfile -File `"$PSCommandPath`"" -Verb RunAs -PassThru -Wait
        exit $process.ExitCode
    } catch {
        Write-Log "No se concedieron permisos de Administrador: $($_.Exception.Message)" "ERROR"
        exit 1
    }
}

Write-Log "Permisos de Administrador verificados correctamente." "SUCCESS"

# 2. Definir servicios e instancias de MySQL a gestionar
$instances = @(
    @{
        ServiceName = "MySQL267"
        DisplayName = "MySQL Server 26.7"
        Port        = 3306
        MyIni       = "C:\ProgramData\MySQL\MySQL Server 26.7\my.ini"
        MysqldExe   = "C:\Program Files\MySQL\MySQL Server 26.7\bin\mysqld.exe"
        MysqlExe    = "C:\Program Files\MySQL\MySQL Server 26.7\bin\mysql.exe"
    },
    @{
        ServiceName = "MySQL80"
        DisplayName = "MySQL Server 8.0"
        Port        = 3307
        MyIni       = "C:\ProgramData\MySQL\MySQL Server 8.0\my.ini"
        MysqldExe   = "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqld.exe"
        MysqlExe    = "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe"
    }
)

# 3. Preparar el archivo SQL de inicializaciÃ³n
$initDir = "C:\ProgramData\MySQL"
if (-not (Test-Path $initDir)) {
    New-Item -ItemType Directory -Path $initDir -Force | Out-Null
}
$initSqlFile = Join-Path $initDir "mysql-init.txt"
$initSqlContent = @"
ALTER USER 'root'@'localhost' IDENTIFIED BY '$targetPassword';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '$targetPassword';
ALTER USER 'root'@'127.0.0.1' IDENTIFIED BY '$targetPassword';
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '$targetPassword';
ALTER USER 'root'@'%' IDENTIFIED BY '$targetPassword';
CREATE USER IF NOT EXISTS 'emp_app'@'localhost' IDENTIFIED BY '$targetPassword';
ALTER USER 'emp_app'@'localhost' IDENTIFIED BY '$targetPassword';
CREATE USER IF NOT EXISTS 'emp_app'@'127.0.0.1' IDENTIFIED BY '$targetPassword';
ALTER USER 'emp_app'@'127.0.0.1' IDENTIFIED BY '$targetPassword';
CREATE DATABASE IF NOT EXISTS `sistema_empleados` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `sistema_empleados`.* TO 'emp_app'@'localhost';
GRANT ALL PRIVILEGES ON `sistema_empleados`.* TO 'emp_app'@'127.0.0.1';
FLUSH PRIVILEGES;
"@

[System.IO.File]::WriteAllText($initSqlFile, $initSqlContent, [System.Text.Encoding]::ASCII)
Write-Log "Archivo SQL de inicializaciÃ³n creado en: $initSqlFile" "INFO"

# FunciÃ³n para esperar estado de un servicio
function Wait-ServiceStatus {
    param([string]$ServiceName, [string]$TargetStatus, [int]$TimeoutSeconds = 20)
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    while ($sw.Elapsed.TotalSeconds -lt $TimeoutSeconds) {
        $svc = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
        if ($null -ne $svc -and $svc.Status.ToString() -eq $TargetStatus) {
            return $true
        }
        Start-Sleep -Milliseconds 500
    }
    return $false
}

# FunciÃ³n para verificar si la contraseÃ±a funciona
function Test-RootLogin {
    param([string]$MysqlExe, [int]$Port, [string]$Password)
    if (-not (Test-Path $MysqlExe)) { return $false }
    try {
        $output = & $MysqlExe -u root "-p$Password" -P $Port -h 127.0.0.1 -e "SELECT 'OK_CONN';" 2>&1
        return ($output -like "*OK_CONN*")
    } catch {
        return $false
    }
}

# 4. Procesar cada servicio MySQL
$resumen = @()

foreach ($inst in $instances) {
    $sName = $inst.ServiceName
    $dName = $inst.DisplayName
    $port  = $inst.Port
    $ini   = $inst.MyIni
    $mExe  = $inst.MysqlExe
    $mdExe = $inst.MysqldExe

    Write-Host "`n-----------------------------------------------------" -ForegroundColor Cyan
    Write-Log "Procesando $dName (Servicio: $sName, Puerto: $port)..." "INFO"

    $svc = Get-Service -Name $sName -ErrorAction SilentlyContinue
    if ($null -eq $svc) {
        Write-Log "El servicio '$sName' no estÃ¡ instalado en este equipo. Omitiendo." "WARNING"
        continue
    }

    if (-not (Test-Path $ini)) {
        Write-Log "No se encontrÃ³ el archivo de configuraciÃ³n: $ini. Omitiendo." "WARNING"
        continue
    }

    # A) Detener servicio
    Write-Log "Deteniendo el servicio $sName..." "INFO"
    Stop-Service -Name $sName -Force -ErrorAction SilentlyContinue
    $stopped = Wait-ServiceStatus -ServiceName $sName -TargetStatus "Stopped" -TimeoutSeconds 15

    if (-not $stopped) {
        Write-Log "Forzando terminaciÃ³n de procesos bloqueantes para $sName..." "WARNING"
        Get-Process mysqld -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
    }

    # B) Modificar my.ini temporalmente para inyectar init-file
    $iniBak = "$ini.bak_reset"
    Copy-Item -Path $ini -Destination $iniBak -Force
    $iniContent = Get-Content -Path $ini -Raw
    
    # Inyectar init-file justo debajo de [mysqld]
    $unixInitPath = $initSqlFile.Replace("\", "/")
    $initOption = "init-file=$unixInitPath"
    
    if ($iniContent -match "(?i)\[mysqld\]") {
        $newIniContent = $iniContent -replace "(?i)\[mysqld\]", "[mysqld]`r`n$initOption"
        Set-Content -Path $ini -Value $newIniContent -Encoding Ascii
        Write-Log "OpciÃ³n '$initOption' inyectada en $ini." "INFO"
    } else {
        Write-Log "No se encontrÃ³ la secciÃ³n [mysqld] en $ini." "ERROR"
        continue
    }

    # C) Iniciar servicio para que ejecute el init-file
    Write-Log "Iniciando servicio $sName para aplicar restablecimiento..." "INFO"
    Start-Service -Name $sName -ErrorAction SilentlyContinue
    $started = Wait-ServiceStatus -ServiceName $sName -TargetStatus "Running" -TimeoutSeconds 20

    if ($started) {
        Write-Log "Servicio $sName iniciado. Esperando sincronizaciÃ³n (3s)..." "INFO"
        Start-Sleep -Seconds 3
    } else {
        Write-Log "El servicio $sName tardÃ³ en iniciar. Verificando procesos..." "WARNING"
    }

    # D) Restaurar my.ini limpio
    Copy-Item -Path $iniBak -Destination $ini -Force
    Remove-Item -Path $iniBak -Force -ErrorAction SilentlyContinue
    Write-Log "Archivo de configuraciÃ³n $ini restaurado a su estado original limpio." "INFO"

    # E) Probar si el login como root con 'ana1' funciona
    $loginOk = Test-RootLogin -MysqlExe $mExe -Port $port -Password $targetPassword

    if ($loginOk) {
        Write-Log "Â¡Ã‰XITO TOTAL! Usuario 'root' autentica correctamente con '$targetPassword' en puerto $port." "SUCCESS"
        $resumen += "$dName (Puerto $port): RESTABLECIDO Y VERIFICADO (Clave: $targetPassword)"
    } else {
        Write-Log "No se pudo verificar con init-file en $sName. Intentando mÃ©todo alternativo directo..." "WARNING"
        
        # MÃ©todo alternativo: mysqld directo con --skip-grant-tables
        Stop-Service -Name $sName -Force -ErrorAction SilentlyContinue
        Wait-ServiceStatus -ServiceName $sName -TargetStatus "Stopped" -TimeoutSeconds 10
        Get-Process mysqld -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2

        Write-Log "Iniciando mysqld temporal en modo mantenimiento..." "INFO"
        $proc = Start-Process -FilePath $mdExe -ArgumentList "--defaults-file=`"$ini`"", "--skip-grant-tables", "--shared-memory" -PassThru
        Start-Sleep -Seconds 4

        # Ejecutar SQL directamente
        & $mExe -u root -P $port -h 127.0.0.1 -e "FLUSH PRIVILEGES; ALTER USER 'root'@'localhost' IDENTIFIED BY '$targetPassword'; ALTER USER IF EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '$targetPassword'; ALTER USER IF EXISTS 'root'@'%' IDENTIFIED BY '$targetPassword'; FLUSH PRIVILEGES;" 2>&1 | Out-Null
        
        # Detener proceso temporal
        if ($null -ne $proc -and -not $proc.HasExited) {
            Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
        }
        Get-Process mysqld -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2

        # Reiniciar servicio normal
        Start-Service -Name $sName -ErrorAction SilentlyContinue
        Wait-ServiceStatus -ServiceName $sName -TargetStatus "Running" -TimeoutSeconds 20
        Start-Sleep -Seconds 3

        $loginOkAlt = Test-RootLogin -MysqlExe $mExe -Port $port -Password $targetPassword
        if ($loginOkAlt) {
            Write-Log "Â¡Ã‰XITO (MÃ©todo Mantenimiento)! Root verificado con '$targetPassword' en puerto $port." "SUCCESS"
            $resumen += "$dName (Puerto $port): RESTABLECIDO Y VERIFICADO (Clave: $targetPassword)"
        } else {
            Write-Log "No se pudo restablecer la contraseÃ±a en $sName." "ERROR"
            $resumen += "$dName (Puerto $port): ERROR AL RESTABLECER"
        }
    }
}

# 5. Limpieza de archivos temporales
if (Test-Path $initSqlFile) {
    Remove-Item -Path $initSqlFile -Force -ErrorAction SilentlyContinue
}

Write-Host "`n=====================================================" -ForegroundColor Green
Write-Log "RESUMEN FINAL DEL PROCESO:" "SUCCESS"
foreach ($r in $resumen) {
    Write-Log "  -> $r" "SUCCESS"
}
Write-Host "=====================================================" -ForegroundColor Green
Write-Host "Completado en $(Get-Date). La ventana se cerrarÃ¡ en 4 segundos..." -ForegroundColor Gray
Start-Sleep -Seconds 4

