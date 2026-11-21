# ==============================================================================
# diagnostico.ps1 Ã¢â‚¬â€ Diagnostico en Profundidad del Sistema
# Sistema de Gestion de Personal y Evaluaciones
# ==============================================================================

[CmdletBinding()]
param(
    [switch]$NoWait
)

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
[Console]::Title = "Diagnostico en Profundidad del Sistema"

function Resultado-Ok    { param([string]$msg) Write-Host "  [OK] $msg" -ForegroundColor Green }
function Resultado-Warn  { param([string]$msg) Write-Host "  [AVISO] $msg" -ForegroundColor Yellow }
function Resultado-Error { param([string]$msg) Write-Host "  [ERROR] $msg" -ForegroundColor Red }
function Titulo          { param([string]$msg) Write-Host "`n  === $msg ===" -ForegroundColor Cyan }

$errores = 0
$avisos  = 0

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "   DIAGNOSTICO INTEGRAL DE INSTALACION Y EJECUCION" -ForegroundColor Cyan
Write-Host "   $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" -ForegroundColor DarkGray
Write-Host "============================================================" -ForegroundColor Cyan

# Ã¢â€â‚¬Ã¢â€â‚¬ 1. Sistema Operativo y Espacio en Disco Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
Titulo "1. Sistema Operativo y Almacenamiento"
$is64 = [System.Environment]::Is64BitOperatingSystem
$os = [System.Environment]::OSVersion
Write-Host "  Windows: $($os.VersionString)"

if ($is64) {
    Resultado-Ok "Arquitectura de 64 bits detectada (compatible con PHP x64)."
} else {
    Resultado-Error "El sistema es de 32 bits. Los binarios portables de PHP requieren Windows de 64 bits."
    $errores++
}

try {
    $driveC = Get-PSDrive C -ErrorAction SilentlyContinue
    $freeBytes = $null
    if ($driveC -and $driveC.Free) {
        $freeBytes = $driveC.Free
    } else {
        $di = New-Object System.IO.DriveInfo('C')
        if ($di.IsReady) { $freeBytes = $di.AvailableFreeSpace }
    }
    if ($freeBytes -ne $null) {
        $freeGB = [Math]::Round($freeBytes / 1GB, 2)
        if ($freeGB -ge 1.0) {
            Resultado-Ok "Espacio libre en disco C: : $freeGB GB disponible."
        } elseif ($freeGB -ge 0.3) {
            Resultado-Warn "Espacio bajo en disco C: : $freeGB GB libre."
            $avisos++
        } else {
            Resultado-Error "Espacio critico en disco C: ($freeGB GB). Se requieren al menos 500 MB."
            $errores++
        }
    }
} catch {}

# Ã¢â€â‚¬Ã¢â€â‚¬ 2. Componentes de Microsoft Visual C++ 2015-2022 (x64) Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
Titulo "2. Dependencias de Microsoft Visual C++ (x64)"
$phpDir = Join-Path (Split-Path $PSScriptRoot) "php"
$sys32  = [System.Environment]::GetFolderPath('System')
$vcDlls = @("vcruntime140.dll", "vcruntime140_1.dll", "msvcp140.dll")
$vcFaltantes = @()

foreach ($dll in $vcDlls) {
    $enPhp = Test-Path (Join-Path $phpDir $dll)
    $enSys = Test-Path (Join-Path $sys32 $dll)
    if (-not $enPhp -and -not $enSys) {
        $vcFaltantes += $dll
    }
}

if ($vcFaltantes.Count -eq 0) {
    Resultado-Ok "Bibliotecas de Visual C++ listas (embebidas en .\php\ y/o en System32): $($vcDlls -join ', ')"
} else {
    Resultado-Error "Faltan bibliotecas criticas de Visual C++: $($vcFaltantes -join ', ')"
    Write-Host "         PHP 8 no podra arrancar sin estas bibliotecas." -ForegroundColor Yellow
    $errores++
}

# Ã¢â€â‚¬Ã¢â€â‚¬ 3. Motor PHP Portable y Ejecucion Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
Titulo "3. Motor PHP Portable y Arranque"
$phpBin = $null
$phpOrigen = ""
$candidatos = @(
    @{ ruta = (Join-Path (Split-Path $PSScriptRoot) "php\php.exe"); origen = "Portable (.\php\php.exe)" },
    @{ ruta = "php";                                    origen = "PATH del sistema" },
    @{ ruta = "C:\xampp\php\php.exe";                  origen = "XAMPP" },
    @{ ruta = "C:\laragon\bin\php\php.exe";            origen = "Laragon" },
    @{ ruta = "C:\php\php.exe";                        origen = "C:\php\" }
)

foreach ($c in $candidatos) {
    if ($c.ruta -eq "php") {
        $cmd = Get-Command php -ErrorAction SilentlyContinue
        if ($cmd) { $phpBin = $cmd.Source; $phpOrigen = $c.origen; break }
        continue
    }
    if (Test-Path $c.ruta) { $phpBin = $c.ruta; $phpOrigen = $c.origen; break }
}

if ($phpBin) {
    Resultado-Ok "Ejecutable localizado: $phpBin ($phpOrigen)"
    
    $phpExtDir = Join-Path (Split-Path -Parent $phpBin) "ext"
    $phpIni    = Join-Path (Split-Path $PSScriptRoot) "config\php.ini"
    $phpArgs   = @("-d", "extension_dir=`"$phpExtDir`"")
    if (Test-Path $phpIni) { $phpArgs = @("-c", "`"$phpIni`"") + $phpArgs }

    $versionStr = (& $phpBin @phpArgs -r "echo phpversion();" 2>$null)
    $versionId  = (& $phpBin @phpArgs -r "echo PHP_VERSION_ID;" 2>$null)

    if ($versionStr -and $versionId) {
        if ([int]$versionId -ge 80000) {
            Resultado-Ok "PHP responde correctamente: version $versionStr (64-bit)"
        } else {
            Resultado-Error "La version de PHP ($versionStr) es inferior a 8.0."
            $errores++
        }
    } else {
        Resultado-Error "PHP no pudo arrancar al invocarlo. Codigo de salida o bloqueo de DLL/Antivirus."
        $errores++
    }
} else {
    Resultado-Error "No se encontro PHP. Coloca PHP portable en: $(Join-Path (Split-Path $PSScriptRoot) 'php\php.exe')"
    $errores++
}

# Ã¢â€â‚¬Ã¢â€â‚¬ 4. Extensiones PHP Obligatorias y Opcionales Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if ($phpBin -and $versionStr) {
    Titulo "4. Extensiones de PHP Requeridas"
    $extRequeridas = @(
        @{ nombre = "pdo_sqlite"; critica = $true;  desc = "Driver SQLite (Base de datos principal)" },
        @{ nombre = "sqlite3";   critica = $true;  desc = "Motor SQLite3" },
        @{ nombre = "pdo";       critica = $true;  desc = "Capa de abstraccion PDO" },
        @{ nombre = "mbstring";  critica = $true;  desc = "Manejo de cadenas UTF-8" },
        @{ nombre = "openssl";   critica = $true;  desc = "Cifrado y seguridad" },
        @{ nombre = "json";      critica = $true;  desc = "Respuestas JSON" },
        @{ nombre = "session";   critica = $true;  desc = "Control de sesiones y login" },
        @{ nombre = "curl";      critica = $false; desc = "Peticiones HTTP externas" },
        @{ nombre = "intl";      critica = $false; desc = "Formato e internacionalizacion" },
        @{ nombre = "gd";        critica = $false; desc = "Generacion de graficos/imagenes" },
        @{ nombre = "zip";       critica = $false; desc = "Compresion de respaldos ZIP" },
        @{ nombre = "fileinfo";  critica = $false; desc = "Deteccion de tipos de archivo" }
    )

    $extCargadas = (& $phpBin @phpArgs -r "echo implode(',', get_loaded_extensions());" 2>$null) -split ","

    foreach ($ext in $extRequeridas) {
        if ($extCargadas -contains $ext.nombre) {
            Resultado-Ok "$($ext.nombre) disponible ($($ext.desc))"
        } elseif ($ext.critica) {
            Resultado-Error "$($ext.nombre) NO esta cargada ($($ext.desc))"
            $errores++
        } else {
            Resultado-Warn "$($ext.nombre) no disponible ($($ext.desc))"
            $avisos++
        }
    }
}

# Ã¢â€â‚¬Ã¢â€â‚¬ 5. Permisos de Directorio de Datos (%APPDATA%) Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
Titulo "5. Permisos en Carpeta de Datos (storage/ - Modo Portable)"
$storageDir = Join-Path (Split-Path $PSScriptRoot) "storage"

if (-not (Test-Path $storageDir)) {
    try {
        New-Item -ItemType Directory -Path $storageDir -Force | Out-Null
    } catch {}
}

if (Test-Path $storageDir) {
    $guid = [System.Guid]::NewGuid().ToString('N')
    $testFile = Join-Path $storageDir ".test_$guid.tmp"
    try {
        [System.IO.File]::WriteAllText($testFile, "test_permisos")
        $readBack = [System.IO.File]::ReadAllText($testFile)
        Remove-Item $testFile -Force -ErrorAction SilentlyContinue
        if ($readBack -eq "test_permisos") {
            Resultado-Ok "Lectura y escritura verificadas en almacenamiento portable: $storageDir"
        } else {
            Resultado-Error "Fallo la verificacion de lectura en: $storageDir"
            $errores++
        }
    } catch {
        Resultado-Error "No se tienen permisos de escritura en: $storageDir ($($_.Exception.Message))"
        $errores++
    }
} else {
    Resultado-Error "No se pudo crear ni acceder al directorio de datos: $storageDir"
    $errores++
}

# Ã¢â€â‚¬Ã¢â€â‚¬ 6. Integridad de la Base de Datos SQLite Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
Titulo "6. Integridad de la Base de Datos SQLite"
$dbPath = Join-Path $storageDir "empleados.db"
if (-not (Test-Path $dbPath)) {
    $altDb = Join-Path $env:APPDATA "SistemaEmpleados\empleados.db"
    if (Test-Path $altDb) { $dbPath = $altDb }
}

if (Test-Path $dbPath) {
    $sizeKB = [Math]::Round((Get-Item $dbPath).Length / 1KB, 1)
    Resultado-Ok "Archivo de base de datos encontrado: $dbPath ($sizeKB KB)"

    if ($phpBin -and $versionStr) {
        $dbPhp = $dbPath.Replace('\', '/')
        $scriptIntegridad = "try { `$p = new PDO('sqlite:$dbPhp'); `$p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); `$c = `$p->query('PRAGMA integrity_check;')->fetchColumn(); `$n = `$p->query('SELECT COUNT(*) FROM empleados')->fetchColumn(); echo 'OK|' . `$c . '|' . `$n; } catch (Exception `$e) { echo 'FAIL|' . `$e->getMessage(); }"
        
        $diagDb = & $phpBin @phpArgs -r $scriptIntegridad 2>$null
        if ($diagDb -match '^OK\|([^|]+)\|(\d+)$') {
            $estadoPragma = $Matches[1]
            $numEmpleados = $Matches[2]
            if ($estadoPragma -eq 'ok') {
                Resultado-Ok "Integridad estructural SQLite: CORRECTA (PRAGMA integrity_check = ok)"
                Resultado-Ok "Registros activos: $numEmpleados empleados registrados."
            } else {
                Resultado-Error "Integridad SQLite comprometida: $estadoPragma"
                $errores++
            }
        } elseif ($diagDb -match '^FAIL\|(.*)$') {
            Resultado-Error "Error de conexion con la base de datos: $($Matches[1])"
            $errores++
        } else {
            Resultado-Warn "Respuesta no estandar al comprobar la base de datos: $diagDb"
            $avisos++
        }
    }
} else {
    Resultado-Warn "No existe la base de datos aun en storage ($dbPath). Se creara automaticamente en el primer inicio."
    $avisos++
}

# Ã¢â€â‚¬Ã¢â€â‚¬ 7. Red y Disponibilidad de Puertos TCP Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
Titulo "7. Red y Puertos TCP (8040 - 8090)"

# Resolucion loopback
try {
    $loopbackEntry = [System.Net.Dns]::GetHostAddresses("127.0.0.1")
    if ($loopbackEntry) {
        Resultado-Ok "Adaptador Loopback 127.0.0.1 responde correctamente."
    }
} catch {
    Resultado-Warn "No se pudo resolver 127.0.0.1 via DNS local."
    $avisos++
}

# Verificacion del puerto preferido 8040
$puerto8040Libre = $false
try {
    $l8040 = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 8040)
    $l8040.ExclusiveAddressUse = $true
    $l8040.Start()
    $l8040.Stop()
    $puerto8040Libre = $true
} catch {}

if ($puerto8040Libre) {
    Resultado-Ok "Puerto por defecto 8040: DISPONIBLE."
} else {
    Resultado-Warn "Puerto 8040 ocupado por otro proceso. El sistema usara asignacion automatica de puerto libre."
    $avisos++
}

# Rango alternativo (8041 - 8060)
$puertosLibres = 0
for ($p = 8041; $p -le 8060; $p++) {
    try {
        $l = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $p)
        $l.ExclusiveAddressUse = $true
        $l.Start()
        $l.Stop()
        $puertosLibres++
    } catch {}
}

if ($puertosLibres -ge 5) {
    Resultado-Ok "Rango de contingencia: $puertosLibres puertos TCP libres entre 8041 y 8060."
} elseif ($puertosLibres -gt 0) {
    Resultado-Warn "Pocos puertos libres en rango alternativo ($puertosLibres disponibles)."
    $avisos++
} else {
    Resultado-Error "No se encontraron puertos disponibles en el rango 8040-8060."
    $errores++
}

# Ã¢â€â‚¬Ã¢â€â‚¬ 8. Integridad de Componentes del Proyecto Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
Titulo "8. Archivos Esenciales del Proyecto"
$archivosRequeridos = @(
    "SistemaEmpleados.exe",
    "config\app.ico",
    "config\router.php",
    "config\index.php",
    "config\php.ini",
    "scripts\iniciar_servidor.bat",
    "scripts\iniciar_servidor.ps1",
    "scripts\detener_servidor.bat",
    "database\schema.sql",
    "public\html\access.html",
    "public\html\index.html",
    "public\html\configuraciones.html",
    "public\js\script.js",
    "public\css\style.css",
    "backend\db.php",
    "backend\storage.php",
    "backend\auth_check.php"
)

$faltantes = @()
foreach ($arch in $archivosRequeridos) {
    $full = Join-Path (Split-Path $PSScriptRoot) $arch
    if (-not (Test-Path $full)) {
        $faltantes += $arch
    }
}

if ($faltantes.Count -eq 0) {
    Resultado-Ok "Todos los archivos esenciales del sistema estan presentes ($($archivosRequeridos.Count) verificados)."
} else {
    Resultado-Error "Faltan archivos esenciales: $($faltantes -join ', ')"
    $errores++
}

# Ã¢â€â‚¬Ã¢â€â‚¬ Resumen y Diagnostico Final Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
if ($errores -eq 0 -and $avisos -eq 0) {
    Write-Host "  RESULTADO: EXCELENTE. Todo cumple con los requisitos." -ForegroundColor Green
    Write-Host "  El sistema puede instalarse y ejecutarse con total estabilidad." -ForegroundColor Green
} elseif ($errores -eq 0) {
    Write-Host "  RESULTADO: OPERATIVO con $avisos aviso(s) no critico(s)." -ForegroundColor Yellow
    Write-Host "  El sistema funcionara correctamente en este equipo." -ForegroundColor Yellow
} else {
    Write-Host "  RESULTADO: $errores ERROR(ES) critico(s) detectado(s)." -ForegroundColor Red
    Write-Host "  Corrige los elementos marcados en rojo antes de iniciar." -ForegroundColor Red
    if ($avisos -gt 0) {
        Write-Host "  (Tambien se registraron $avisos avisos secundarios)." -ForegroundColor Yellow
    }
}
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

if (-not $NoWait) {
    Read-Host "Presiona Enter para cerrar el diagnostico..."
}

exit $errores


