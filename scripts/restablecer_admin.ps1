# ==============================================================================
# restablecer_admin.ps1
# Recuperacion de contrasena del usuario administrador
#
# Uso:
#   Ejecutar directamente en la consola desde la carpeta del proyecto.
#   NO debe ejecutarse de forma remota ni automatizada.
#
# Requisitos:
#   - PHP 8+ accesible (en .\php\php.exe, PATH o XAMPP)
#   - La base de datos debe estar accesible en %APPDATA%\SistemaEmpleados\
# ==============================================================================

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
[Console]::Title = "Restablecer Contrasena de Administrador"

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "   RESTABLECIMIENTO DE CONTRASENA DE ADMINISTRADOR" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  Este script cambia UNICAMENTE la contrasena del usuario"
Write-Host "  'admin'. No toca ni borra ningun dato de empleados."
Write-Host ""
Write-Host "  IMPORTANTE: Ejecuta este script directamente en la" -ForegroundColor Yellow
Write-Host "  computadora del servidor, no de forma remota." -ForegroundColor Yellow
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Localizar PHP
$phpBin = $null
$candidatos = @(
    (Join-Path (Split-Path $PSScriptRoot) "php\php.exe"),
    "php",
    "C:\xampp\php\php.exe",
    "C:\laragon\bin\php\php.exe",
    "C:\php\php.exe"
)

foreach ($candidato in $candidatos) {
    if ($candidato -eq "php") {
        $cmd = Get-Command php -ErrorAction SilentlyContinue
        if ($cmd) { $phpBin = $cmd.Source; break }
        continue
    }
    if (Test-Path $candidato) { $phpBin = $candidato; break }
}

if (-not $phpBin) {
    Write-Host "[ERROR] No se encontro PHP. Coloca php.exe en la carpeta .\php\" -ForegroundColor Red
    Read-Host "Presiona Enter para salir..."
    exit 1
}

Write-Host "[PHP] Usando: $phpBin" -ForegroundColor DarkGray

# 2. Verificar que existe la base de datos
$phpExtDir = Join-Path (Split-Path -Parent $phpBin) "ext"
$phpIni    = Join-Path (Split-Path $PSScriptRoot) "config\php.ini"

# PHP debe ejecutarse con el directorio de trabajo = raÃ­z del proyecto
# para que require_once 'backend/storage.php' funcione correctamente.
$phpArgs = @("-d", "extension_dir=`"$phpExtDir`"")
if (Test-Path $phpIni) {
    $phpArgs = @("-c", "`"$phpIni`"") + $phpArgs
}

# FunciÃ³n auxiliar: ejecuta cÃ³digo PHP inline con el CWD del proyecto
function Invoke-PhpInProject {
    param([string]$Code)
    # Escribir a archivo temporal para evitar problemas de escapado con inline -r
    $tmpFile = Join-Path $env:TEMP "restablece_$([System.Guid]::NewGuid().ToString('N')).php"
    Set-Content -Encoding UTF8 -Path $tmpFile -Value "<?php`nchdir('$((Split-Path $PSScriptRoot).Replace('\','/'))');" 
    Add-Content -Encoding UTF8 -Path $tmpFile -Value $Code
    try {
        return & $phpBin @phpArgs $tmpFile 2>$null
    } finally {
        Remove-Item $tmpFile -Force -ErrorAction SilentlyContinue
    }
}

$dbPath = Invoke-PhpInProject @"
require_once 'backend/storage.php';
echo obtenerRutaSQLite();
"@

if (-not $dbPath -or -not (Test-Path $dbPath)) {
    Write-Host ""
    Write-Host "[ERROR] No se encontro la base de datos en: $dbPath" -ForegroundColor Red
    Write-Host "        Asegurate de que el sistema haya sido iniciado al menos una vez." -ForegroundColor Yellow
    Read-Host "Presiona Enter para salir..."
    exit 1
}

Write-Host "[BD]  Base de datos: $dbPath" -ForegroundColor DarkGray
Write-Host ""

# 3. Verificar que el usuario 'admin' existe en la BD
$adminExiste = Invoke-PhpInProject @"
require_once 'backend/storage.php';
require_once 'backend/db.php';
`$stmt = `$pdo->query("SELECT COUNT(*) FROM usuarios WHERE usuario = 'admin'");
echo (int)`$stmt->fetchColumn();
"@

if ($adminExiste -ne "1") {
    Write-Host "[ERROR] El usuario 'admin' no existe en la base de datos." -ForegroundColor Red
    Write-Host "        El sistema puede no haber sido inicializado correctamente." -ForegroundColor Yellow
    Read-Host "Presiona Enter para salir..."
    exit 1
}

# Lista de contrasenas de fabrica prohibidas
$prohibidas = @("ana1", "ana2", "ana3", "admin", "123456", "12345678", "password", "sistema", "root", "qwerty", "1234")

# 4. Pedir la nueva contrasena (oculta con asteriscos)
function Leer-PassSegura {
    param([string]$Prompt)
    Write-Host -NoNewline $Prompt
    $ss = New-Object System.Security.SecureString
    while ($true) {
        $key = [Console]::ReadKey($true)
        if ($key.Key -eq [ConsoleKey]::Enter) {
            Write-Host ""
            break
        } elseif ($key.Key -eq [ConsoleKey]::Backspace) {
            if ($ss.Length -gt 0) {
                $ss.RemoveAt($ss.Length - 1)
                Write-Host -NoNewline "`b `b"
            }
        } elseif ($key.KeyChar -ne [char]0) {
            $ss.AppendChar($key.KeyChar)
            Write-Host -NoNewline "*"
        }
    }
    # Convertir SecureString a texto plano
    $bstr = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($ss)
    try {
        return [System.Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
    } finally {
        [System.Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
        $ss.Dispose()
    }
}

$intentos = 0
$passNueva = ""
$passConfirm = ""

while ($true) {
    $intentos++
    if ($intentos -gt 5) {
        Write-Host ""
        Write-Host "[ERROR] Demasiados intentos fallidos. Vuelve a ejecutar el script." -ForegroundColor Red
        exit 1
    }

    $passNueva   = Leer-PassSegura "  Nueva contrasena de admin (min. 6 caracteres): "
    $passConfirm = Leer-PassSegura "  Confirmar nueva contrasena                    : "

    if ($passNueva -ne $passConfirm) {
        Write-Host "  [X] Las contrasenas no coinciden. Intenta de nuevo." -ForegroundColor Red
        Write-Host ""
        continue
    }

    if ($passNueva.Length -lt 6) {
        Write-Host "  [X] La contrasena debe tener al menos 6 caracteres." -ForegroundColor Red
        Write-Host ""
        continue
    }

    if ($prohibidas -contains $passNueva.ToLower()) {
        Write-Host "  [X] Esa contrasena esta prohibida por ser una clave de fabrica." -ForegroundColor Red
        Write-Host "      Elige una contrasena unica que no sea: ana1, ana2, ana3, admin, etc." -ForegroundColor Yellow
        Write-Host ""
        continue
    }

    break
}

# 5. Generar hash bcrypt y actualizar la BD
Write-Host ""
Write-Host "  Actualizando contrasena en la base de datos..." -ForegroundColor DarkGray

# Usar archivo temporal PHP para evitar problemas de escapado con inline -r
$phpUpdateCode = @"
require_once 'backend/storage.php';
require_once 'backend/db.php';
`$pass = file_get_contents('$($env:TEMP.Replace('\','/'))/restablece_pass.tmp');
`$hash = password_hash(trim(`$pass), PASSWORD_BCRYPT, ['cost' => 12]);
`$stmt = `$pdo->prepare("UPDATE usuarios SET password_hash = :h WHERE usuario = 'admin'");
`$ok = `$stmt->execute([':h' => `$hash]);
echo `$ok && `$stmt->rowCount() > 0 ? 'OK' : 'FAIL';
"@

# Guardar contraseÃ±a en archivo temporal seguro (sin que pase por argumentos de lÃ­nea de comandos)
$tmpPassFile = Join-Path $env:TEMP "restablece_pass.tmp"
try {
    [System.IO.File]::WriteAllText($tmpPassFile, $passNueva, [System.Text.Encoding]::UTF8)
    $resultado = Invoke-PhpInProject $phpUpdateCode
} finally {
    # Eliminar inmediatamente el temporal con la contraseÃ±a
    if (Test-Path $tmpPassFile) { Remove-Item $tmpPassFile -Force -ErrorAction SilentlyContinue }
}

if ($resultado -eq "OK") {
    Write-Host ""
    Write-Host "============================================================" -ForegroundColor Green
    Write-Host "  [OK] Contrasena de administrador actualizada con exito." -ForegroundColor Green
    Write-Host "============================================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "  La proxima vez que ingreses al sistema, usa la nueva clave." -ForegroundColor Cyan
    Write-Host "  Usuario : admin" -ForegroundColor Cyan
    Write-Host "  Clave   : (la que acabas de registrar)" -ForegroundColor Cyan
    Write-Host ""
} else {
    Write-Host ""
    Write-Host "[ERROR] No se pudo actualizar la contrasena en la base de datos." -ForegroundColor Red
    Write-Host "        Resultado: $resultado" -ForegroundColor Red
    Write-Host "        Revisa que la BD no este bloqueada (cierra el servidor primero)." -ForegroundColor Yellow
    Write-Host ""
    Read-Host "Presiona Enter para salir..."
    exit 1
}

Read-Host "Presiona Enter para cerrar..."


