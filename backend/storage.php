<?php
declare(strict_types=1);

if (!function_exists('obtenerDirectorioStorage')) {
    function obtenerDirectorioStorage(): string
    {
        static $dirStorage = null;
        if ($dirStorage !== null) {
            return $dirStorage;
        }

        $localStorage = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';

        // Candidatos alternativos si storage local no tiene permisos de escritura
        $alternativas = [];
        $appData = getenv('APPDATA') ?: ($_SERVER['APPDATA'] ?? ($_ENV['APPDATA'] ?? ''));
        if (!empty($appData)) {
            $alternativas[] = rtrim($appData, "\\/") . DIRECTORY_SEPARATOR . 'SistemaEmpleados';
        }
        $localAppData = getenv('LOCALAPPDATA') ?: ($_SERVER['LOCALAPPDATA'] ?? ($_ENV['LOCALAPPDATA'] ?? ''));
        if (!empty($localAppData)) {
            $alternativas[] = rtrim($localAppData, "\\/") . DIRECTORY_SEPARATOR . 'SistemaEmpleados';
        }
        $userProfile = getenv('USERPROFILE') ?: ($_SERVER['USERPROFILE'] ?? ($_ENV['USERPROFILE'] ?? ''));
        if (!empty($userProfile)) {
            $alternativas[] = rtrim($userProfile, "\\/") . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Roaming' . DIRECTORY_SEPARATOR . 'SistemaEmpleados';
        }
        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? ($_ENV['HOME'] ?? ''));
        if (!empty($home)) {
            $alternativas[] = rtrim($home, "\\/") . DIRECTORY_SEPARATOR . '.sistema_empleados';
        }
        $alternativas[] = rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . 'SistemaEmpleados';

        $dirElegido = null;

        // Prioridad 1: Almacenamiento local en el proyecto (Modo Portable / USB)
        if (!is_dir($localStorage)) {
            @mkdir($localStorage, 0777, true);
        }
        if (is_dir($localStorage) && is_writable($localStorage)) {
            $dirElegido = $localStorage;

            // Migración automática: si no existe base de datos local pero sí una previa en AppData,
            // copiarla a la carpeta local para no perder los datos existentes.
            $dbLocal = $localStorage . DIRECTORY_SEPARATOR . 'empleados.db';
            if (!file_exists($dbLocal)) {
                foreach ($alternativas as $alt) {
                    $dbAlt = $alt . DIRECTORY_SEPARATOR . 'empleados.db';
                    if (file_exists($dbAlt) && is_readable($dbAlt)) {
                        @copy($dbAlt, $dbLocal);
                        $respaldoAlt = $alt . DIRECTORY_SEPARATOR . 'respaldo_sistema.sql';
                        if (file_exists($respaldoAlt) && !file_exists($localStorage . DIRECTORY_SEPARATOR . 'respaldo_sistema.sql')) {
                            @copy($respaldoAlt, $localStorage . DIRECTORY_SEPARATOR . 'respaldo_sistema.sql');
                        }
                        break;
                    }
                }
            }
        } else {
            // Alternativa: Si storage local fuese de solo lectura, recurrir a AppData
            foreach ($alternativas as $candidato) {
                if (!is_dir($candidato)) {
                    @mkdir($candidato, 0777, true);
                }
                if (is_dir($candidato) && is_writable($candidato)) {
                    $dirElegido = $candidato;
                    break;
                }
            }
        }

        if ($dirElegido === null) {
            $dirElegido = $localStorage;
        }

        $dirStorage = $dirElegido;
        return $dirStorage;
    }
}

if (!function_exists('obtenerRutaSQLite')) {
    function obtenerRutaSQLite(): string
    {
        return obtenerDirectorioStorage() . DIRECTORY_SEPARATOR . 'empleados.db';
    }
}

if (!function_exists('obtenerRutaRespaldo')) {
    function obtenerRutaRespaldo(): string
    {
        return obtenerDirectorioStorage() . DIRECTORY_SEPARATOR . 'respaldo_sistema.sql';
    }
}

if (!function_exists('obtenerRutaRespaldoLock')) {
    function obtenerRutaRespaldoLock(): string
    {
        return obtenerDirectorioStorage() . DIRECTORY_SEPARATOR . 'respaldo.lock';
    }
}

if (!function_exists('obtenerRutaRespaldoLog')) {
    function obtenerRutaRespaldoLog(): string
    {
        return obtenerDirectorioStorage() . DIRECTORY_SEPARATOR . 'respaldo_error.log';
    }
}

if (!function_exists('obtenerRutaRespaldoTmp')) {
    function obtenerRutaRespaldoTmp(string $id = ''): string
    {
        $idStr = $id !== '' ? $id : uniqid('', true);
        return obtenerDirectorioStorage() . DIRECTORY_SEPARATOR . 'respaldo_tmp_' . $idStr . '.sql';
    }
}

if (!function_exists('obtenerRutaErrorLog')) {
    function obtenerRutaErrorLog(): string
    {
        return obtenerDirectorioStorage() . DIRECTORY_SEPARATOR . 'php_errors.log';
    }
}

if (!function_exists('obtenerRutaConfigCopiaAutomatica')) {
    function obtenerRutaConfigCopiaAutomatica(): string
    {
        return obtenerDirectorioStorage() . DIRECTORY_SEPARATOR . 'copia_automatica.json';
    }
}

if (!function_exists('obtenerRutaRespaldoRollback')) {
    function obtenerRutaRespaldoRollback(): string
    {
        return obtenerDirectorioStorage() . DIRECTORY_SEPARATOR . 'empleados_pre_restore.bak';
    }
}

(static function (): void {
    static $configurado = false;
    if ($configurado) {
        return;
    }
    $logPath = obtenerRutaErrorLog();
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('html_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $logPath);
    $configurado = true;
})();
