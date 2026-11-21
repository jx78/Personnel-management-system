# ==============================================================================
# Servidor Web Local - Sistema de Gestion de Personal y Evaluaciones
# - Asignacion dinamica de puerto (inicia en 8040; si esta ocupado, busca 8041..8090)
# - Cierre garantizado de PHP al salir o al cerrar la ventana (Job Object de Windows)
# - Limpieza previa de instancias huerfanas de este proyecto
# ==============================================================================

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
[Console]::Title = "Servidor Web - Sistema de Gestion de Personal"

# 1. Definir Job Object de Windows para terminacion automatica de procesos hijos al cerrar la ventana
$jobTrackerSource = @'
using System;
using System.Diagnostics;
using System.Runtime.InteropServices;

public static class ProcessJobTracker {
    [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    static extern IntPtr CreateJobObject(IntPtr lpJobAttributes, string lpName);

    [DllImport("kernel32.dll", SetLastError = true)]
    static extern bool SetInformationJobObject(IntPtr hJob, int JobObjectInfoClass, IntPtr lpJobObjectInfo, uint cbJobObjectInfoLength);

    [DllImport("kernel32.dll", SetLastError = true)]
    static extern bool AssignProcessToJobObject(IntPtr hJob, IntPtr hProcess);

    [StructLayout(LayoutKind.Sequential)]
    struct JOBOBJECT_BASIC_LIMIT_INFORMATION {
        public long PerProcessUserTimeLimit;
        public long PerJobUserTimeLimit;
        public uint LimitFlags;
        public UIntPtr MinimumWorkingSetSize;
        public UIntPtr MaximumWorkingSetSize;
        public uint ActiveProcessLimit;
        public UIntPtr Affinity;
        public uint PriorityClass;
        public uint SchedulingClass;
    }

    [StructLayout(LayoutKind.Sequential)]
    struct IO_COUNTERS {
        public ulong ReadOperationCount;
        public ulong WriteOperationCount;
        public ulong OtherOperationCount;
        public ulong ReadTransferCount;
        public ulong WriteTransferCount;
        public ulong OtherTransferCount;
    }

    [StructLayout(LayoutKind.Sequential)]
    struct JOBOBJECT_EXTENDED_LIMIT_INFORMATION {
        public JOBOBJECT_BASIC_LIMIT_INFORMATION BasicLimitInformation;
        public IO_COUNTERS IoInfo;
        public UIntPtr ProcessMemoryLimit;
        public UIntPtr JobMemoryLimit;
        public UIntPtr PeakProcessMemoryLimit;
        public UIntPtr PeakJobMemoryLimit;
    }

    static IntPtr s_jobHandle = IntPtr.Zero;

    public static bool Initialize() {
        if (s_jobHandle != IntPtr.Zero) return true;
        s_jobHandle = CreateJobObject(IntPtr.Zero, null);
        if (s_jobHandle == IntPtr.Zero) return false;

        var info = new JOBOBJECT_EXTENDED_LIMIT_INFORMATION();
        info.BasicLimitInformation.LimitFlags = 0x2000; // JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE
        int length = Marshal.SizeOf(typeof(JOBOBJECT_EXTENDED_LIMIT_INFORMATION));
        IntPtr pInfo = Marshal.AllocHGlobal(length);
        try {
            Marshal.StructureToPtr(info, pInfo, false);
            return SetInformationJobObject(s_jobHandle, 9, pInfo, (uint)length);
        } finally {
            Marshal.FreeHGlobal(pInfo);
        }
    }

    public static bool TrackProcess(IntPtr hProcess) {
        if (s_jobHandle == IntPtr.Zero) {
            if (!Initialize()) return false;
        }
        return AssignProcessToJobObject(s_jobHandle, hProcess);
    }
}
'@

try {
    Add-Type -TypeDefinition $jobTrackerSource -Language CSharp -ErrorAction SilentlyContinue
    [ProcessJobTracker]::Initialize() | Out-Null
} catch {}

# 2. Localizar ejecutable de PHP
# Orden de busqueda (de mayor a menor prioridad):
#   1. .\php\php.exe      â€” PHP portable incluido junto al programa (autÃ³nomo)
#   2. php en el PATH     â€” instalaciÃ³n del sistema (XAMPP, Laragon, winget, etc.)
#   3. Rutas de instalaciÃ³n frecuentes en Windows
$phpBin = $null

$candidatos = @(
    (Join-Path (Split-Path $PSScriptRoot) "php\php.exe"),          # 1. Portable junto al .bat
    "php",                                             # 2. PATH del sistema
    "C:\xampp\php\php.exe",                           # 3. XAMPP
    "C:\laragon\bin\php\php.exe",                     # 4. Laragon (ultima version)
    "C:\php\php.exe",                                 # 5. Instalacion manual tipica
    "$env:ProgramFiles\PHP\php.exe",                  # 6. Program Files
    "$env:ProgramFiles\php\php.exe"
)

foreach ($candidato in $candidatos) {
    # Para rutas relativas ("php"), verificar con Get-Command
    if ($candidato -eq "php") {
        $cmd = Get-Command php -ErrorAction SilentlyContinue
        if ($cmd) {
            $phpBin = $cmd.Source
            break
        }
        continue
    }
    # Para rutas absolutas, verificar que el archivo exista
    if (Test-Path $candidato) {
        $phpBin = $candidato
        break
    }
}

if (-not $phpBin) {
    Write-Host ""
    Write-Host "[ERROR] No se encontro PHP en ninguna ubicacion conocida." -ForegroundColor Red
    Write-Host ""
    Write-Host "  Opciones para resolver esto:" -ForegroundColor Yellow
    Write-Host "    A) Coloca una copia de PHP 8+ portable en:"            -ForegroundColor Yellow
    Write-Host "         $(Join-Path (Split-Path $PSScriptRoot) 'php\php.exe')"         -ForegroundColor Cyan
    Write-Host "    B) Instala XAMPP (https://www.apachefriends.org)"       -ForegroundColor Yellow
    Write-Host "    C) Instala PHP con winget:"                             -ForegroundColor Yellow
    Write-Host "         winget install PHP.PHP"                            -ForegroundColor Cyan
    Write-Host ""
    Read-Host "Presiona Enter para salir..."
    exit 1
}

Write-Host "[PHP] Usando: $phpBin" -ForegroundColor DarkGray

# 3. Validar presencia del Runtime de Microsoft Visual C++ (VCRUNTIME140.dll / x64)
# PHP compilado para Windows (VS16/VS17) depende de este runtime en equipos limpios.
function Test-VCRuntimeInstalado {
    $sys32 = [System.Environment]::GetFolderPath('System')
    foreach ($dll in @("vcruntime140.dll", "vcruntime140_1.dll", "msvcp140.dll")) {
        if (-not (Test-Path (Join-Path $sys32 $dll))) {
            return $false
        }
    }
    return $true
}

if (-not (Test-VCRuntimeInstalado)) {
    # Buscar si el instalador de Visual C++ fue empaquetado en php\ o en la raÃ­z
    $vcLocal = @(
        (Join-Path (Split-Path $PSScriptRoot) "php\vc_redist.x64.exe"),
        (Join-Path (Split-Path $PSScriptRoot) "vc_redist.x64.exe")
    ) | Where-Object { Test-Path $_ } | Select-Object -First 1

    if ($vcLocal) {
        Write-Host ""
        Write-Host "[INSTALANDO] Se detecto el instalador del Runtime de Microsoft Visual C++ ($vcLocal)..." -ForegroundColor Yellow
        Write-Host "Instalando componentes requeridos por PHP de manera silenciosa..." -ForegroundColor Yellow
        try {
            $pVc = Start-Process -FilePath $vcLocal -ArgumentList "/install", "/quiet", "/norestart" -Wait -PassThru
            if ($pVc.ExitCode -eq 0 -or $pVc.ExitCode -eq 3010) {
                Write-Host "[OK] Microsoft Visual C++ Redistributable instalado con exito." -ForegroundColor Green
            }
        } catch {
            Write-Host "[AVISO] No se pudo ejecutar la instalacion automatica: $($_.Exception.Message)" -ForegroundColor Yellow
        }
    }

    # Re-verificar tras el intento de instalacion
    if (-not (Test-VCRuntimeInstalado)) {
        Write-Host ""
        Write-Host "===============================================================" -ForegroundColor Red
        Write-Host "  REQUISITO FALTANTE: MICROSOFT VISUAL C++ RUNTIME (X64)       " -ForegroundColor Red
        Write-Host "===============================================================" -ForegroundColor Red
        Write-Host "  PHP para Windows requiere 'VCRUNTIME140.dll' para funcionar." -ForegroundColor Yellow
        Write-Host "  En equipos limpios de Windows, este componente debe instalarse una vez."
        Write-Host ""
        Write-Host "  Opciones para instalarlo:" -ForegroundColor Cyan
        Write-Host "    A) Descarga oficial directa de Microsoft:" -ForegroundColor Cyan
        Write-Host "       https://aka.ms/vs/17/release/vc_redist.x64.exe" -ForegroundColor Yellow
        Write-Host "    B) Desde consola con winget:" -ForegroundColor Cyan
        Write-Host "       winget install Microsoft.VCRedist.2015+.x64" -ForegroundColor Yellow
        Write-Host "===============================================================" -ForegroundColor Red
        Write-Host ""
        $abrir = Read-Host "Â¿Deseas abrir el enlace de descarga oficial de Microsoft ahora? (S/N)"
        if ($abrir -match "^[sSyY]") {
            Start-Process "https://aka.ms/vs/17/release/vc_redist.x64.exe"
        }
        Read-Host "Presiona Enter para salir..."
        exit 1
    }
}

# 4. Validar version de PHP (requiere PHP 8.0+)
try {
    $phpVersionId = [int](& $phpBin -r "echo PHP_VERSION_ID;" 2>$null)
    if ($phpVersionId -gt 0 -and $phpVersionId -lt 80000) {
        Write-Host "[ADVERTENCIA] Se detecto una version de PHP inferior a 8.0." -ForegroundColor Yellow
        Write-Host "Este sistema requiere PHP 8 o superior debido al uso de 'str_contains'." -ForegroundColor Yellow
        & $phpBin -v
        Write-Host ""
    }
} catch {}

# 5. Limpieza preventiva de instancias huerfanas de este mismo proyecto
$scriptDir = (Resolve-Path (Split-Path $PSScriptRoot)).Path.TrimEnd('\')
try {
    $orphans = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" -ErrorAction SilentlyContinue | Where-Object {
        $_.CommandLine -and $_.CommandLine -like "*-S *" -and ($_.CommandLine -like "*$scriptDir*" -or $_.CommandLine -like "*php.ini*")
    }
    if ($orphans) {
        Write-Host "[INFO] Cerrando procesos PHP huerfanos anteriores..." -ForegroundColor Yellow
        foreach ($orphan in $orphans) {
            Stop-Process -Id $orphan.ProcessId -Force -ErrorAction SilentlyContinue
        }
        Start-Sleep -Milliseconds 400
    }
} catch {}

# 5. Deteccion dinamica de puerto disponible (inicia en 8040)
function Obtener-PuertoDisponible([int]$preferido = 8040, [int]$inicio = 8040, [int]$fin = 8100) {
    if ($preferido -gt 0) {
        try {
            $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $preferido)
            $listener.ExclusiveAddressUse = $true
            $listener.Start()
            $listener.Stop()
            return $preferido
        } catch {}
    }

    for ($p = $inicio; $p -le $fin; $p++) {
        if ($p -eq $preferido) { continue }
        try {
            $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $p)
            $listener.ExclusiveAddressUse = $true
            $listener.Start()
            $listener.Stop()
            return $p
        } catch {}
    }

    try {
        $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
        $listener.Start()
        $dinamico = $listener.LocalEndpoint.Port
        $listener.Stop()
        return $dinamico
    } catch {}

    return 0
}

# 6. Lanzar servidor PHP usando el php.ini del proyecto
$phpIni    = Join-Path (Split-Path $PSScriptRoot) "config\php.ini"
$routerPhp = Join-Path (Split-Path $PSScriptRoot) "config\router.php"
$extDir    = Join-Path (Split-Path -Parent $phpBin) "ext"

$script:port = 0
$candidatoPort = 8040
$iniciado = $false

for ($intento = 0; $intento -lt 20; $intento++) {
    $script:port = Obtener-PuertoDisponible -preferido $candidatoPort -inicio 8040 -fin 8100
    if ($script:port -eq 0) { break }

    if ($script:port -ne 8040) {
        Write-Host "[AVISO] El puerto 8040 no esta disponible." -ForegroundColor Yellow
        Write-Host "[OK] Asignando automaticamente el puerto alternativo: $($script:port)" -ForegroundColor Green
    }

    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName         = $phpBin
    $psi.Arguments        = "-c `"$phpIni`" -d `"extension_dir=$extDir`" -S 127.0.0.1:$($script:port) `"$routerPhp`""
    $psi.WorkingDirectory = (Split-Path $PSScriptRoot)
    $psi.UseShellExecute  = $false
    $psi.CreateNoWindow   = $true

    $script:phpProc = [System.Diagnostics.Process]::Start($psi)
    if ($script:phpProc) {
        Start-Sleep -Milliseconds 1200
        if (-not $script:phpProc.HasExited) {
            $iniciado = $true
            break
        }
        $script:phpProc.Dispose()
        $script:phpProc = $null
    }

    $candidatoPort = $script:port + 1
}

if (-not $iniciado) {
    Write-Host ""
    Write-Host "===============================================================" -ForegroundColor Red
    Write-Host "  [ERROR] No se pudo iniciar el servidor PHP en ningun puerto disponible." -ForegroundColor Red
    Write-Host "===============================================================" -ForegroundColor Red
    Write-Host "  Ejecuta: diagnostico.bat para identificar el problema." -ForegroundColor Cyan
    Write-Host ""
    Read-Host "Presiona Enter para salir..."
    exit 1
}

# Vincular al Job Object del kernel para terminacion automatica si se cierra la ventana
try {
    [ProcessJobTracker]::TrackProcess($script:phpProc.Handle) | Out-Null
} catch {}

# Rutina de limpieza garantizada
$cleanup = {
    if ($script:phpProc -and -not $script:phpProc.HasExited) {
        Write-Host "`n[CERRANDO] Deteniendo servidor PHP (PID $($script:phpProc.Id))..." -ForegroundColor Yellow
        try {
            $script:phpProc.Kill()
            $script:phpProc.WaitForExit(3000)
        } catch {}
        Write-Host "[OK] Servidor PHP detenido y puerto $($script:port) liberado." -ForegroundColor Green
    }
}

# Registrar limpieza al salir del proceso PowerShell
[AppDomain]::CurrentDomain.add_ProcessExit({
    & $cleanup
})

# 7. Abrir navegador directamente en /public/html/access.html
$urlAcceso = "http://localhost:$($script:port)/public/html/access.html"
Start-Process $urlAcceso

# 8. Panel de control en consola
Write-Host ""
Write-Host "===============================================================" -ForegroundColor Green
Write-Host "   SISTEMA DE GESTION DE PERSONAL Y EVALUACIONES" -ForegroundColor Green
Write-Host "   Servidor Web Local Iniciado Correctamente" -ForegroundColor Green
Write-Host "===============================================================" -ForegroundColor Green
Write-Host "  URL de Acceso    : " -NoNewline; Write-Host $urlAcceso -ForegroundColor Yellow
Write-Host "  Puerto Asignado  : $($script:port)"
Write-Host "  PID de PHP       : $($script:phpProc.Id)"
Write-Host "  php.ini Cargado  : $phpIni"
$appDataPath = [System.Environment]::GetFolderPath('ApplicationData')
$errorLogPath = Join-Path $appDataPath "SistemaEmpleados\php_errors.log"
Write-Host "  Log de Errores   : $errorLogPath"
Write-Host "===============================================================" -ForegroundColor Green
Write-Host "  Presiona [ENTER] o [Q] para detener el servidor y salir." -ForegroundColor Cyan
Write-Host "  (Si cierras esta ventana, PHP se detendra automaticamente)." -ForegroundColor DarkGray
Write-Host "===============================================================" -ForegroundColor Green
Write-Host ""

# 9. Bucle de supervision y escucha de teclado
try {
    while (-not $script:phpProc.HasExited) {
        $hayTecla = $false
        try {
            $hayTecla = [Console]::KeyAvailable
        } catch {
            $hayTecla = $false
        }

        if ($hayTecla) {
            try {
                $tecla = [Console]::ReadKey($true)
                if ($tecla.Key -eq [ConsoleKey]::Enter -or $tecla.Key -eq [ConsoleKey]::Q -or $tecla.Key -eq [ConsoleKey]::Escape) {
                    break
                }
            } catch {}
        }
        Start-Sleep -Milliseconds 300
    }
} finally {
    & $cleanup
}


