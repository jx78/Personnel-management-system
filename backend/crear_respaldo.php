<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config_auth_required.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/storage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

$storageDir  = obtenerDirectorioStorage();
$backupPath  = obtenerRutaRespaldo();
$tempPath    = obtenerRutaRespaldoTmp();
$lockPath    = obtenerRutaRespaldoLock();
$logPath     = obtenerRutaRespaldoLog();

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    if ($lockHandle !== false) fclose($lockHandle);
    echo json_encode(['exito' => false, 'mensaje' => 'Ya hay un respaldo en proceso. Intenta en unos segundos.']);
    exit;
}

$tablas = ['empleados', 'evaluaciones', 'usuarios'];

try {
    $conteosAntes = [];
    foreach ($tablas as $tabla) {
        $id = $driverUsado === 'mysql' ? "`{$tabla}`" : "\"{$tabla}\"";
        $conteosAntes[$tabla] = (int)$pdo->query("SELECT COUNT(*) FROM {$id}")->fetchColumn();
    }

    $sqlDump = generarDumpSQL($pdo, $driverUsado, $tablas);

    $escrito = file_put_contents($tempPath, $sqlDump, LOCK_EX);
    if ($escrito === false || $escrito === 0) {
        throw new \RuntimeException('No se pudo escribir el archivo temporal del respaldo.');
    }

    clearstatcache(true, $tempPath);
    if (filesize($tempPath) === 0) {
        throw new \RuntimeException('El archivo temporal quedó vacío tras la escritura.');
    }

    foreach ($tablas as $tabla) {
        $esperadas = $conteosAntes[$tabla];
        if ($esperadas > 0) {
            $idCitado = $driverUsado === 'mysql' ? "`{$tabla}`" : "\"{$tabla}\"";
            $encontradas = preg_match_all('/^INSERT INTO ' . preg_quote($idCitado, '/') . ' /m', $sqlDump);
            if ($encontradas < $esperadas) {
                throw new \RuntimeException(
                    "Verificación fallida en '{$tabla}': esperadas {$esperadas} filas, generadas {$encontradas}."
                );
            }
        }
    }

    if (!rename($tempPath, $backupPath)) {
        throw new \RuntimeException('No se pudo mover el respaldo temporal al destino final.');
    }

    clearstatcache(true, $backupPath);
    $tamano = filesize($backupPath);
    $fecha  = date('d/m/Y H:i:s');

    $copiaExterna = ['habilitada' => false, 'exito' => false, 'mensaje' => 'No configurada'];
    $configCopiaPath = obtenerRutaConfigCopiaAutomatica();
    if (file_exists($configCopiaPath)) {
        $cfg = json_decode((string)file_get_contents($configCopiaPath), true);
        if (is_array($cfg) && !empty($cfg['habilitada']) && !empty($cfg['ruta_destino'])) {
            $destDir = rtrim((string)$cfg['ruta_destino'], "\\/");
            $copiaExterna['habilitada'] = true;
            $copiaExterna['destino'] = $destDir;
            if (is_dir($destDir) && is_writable($destDir)) {
                $nombreArchivoCopia = 'respaldo_empleados_' . date('Y-m-d_H-i-s') . '.sql';
                $rutaDestinoCopia   = $destDir . DIRECTORY_SEPARATOR . $nombreArchivoCopia;
                if (@copy($backupPath, $rutaDestinoCopia)) {
                    $copiaExterna['exito'] = true;
                    $copiaExterna['mensaje'] = "Copia guardada en {$rutaDestinoCopia}";
                    $cfg['ultimo_exito'] = date('Y-m-d H:i:s');
                    $cfg['ultimo_archivo'] = $nombreArchivoCopia;
                    @file_put_contents($configCopiaPath, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                } else {
                    $copiaExterna['mensaje'] = 'Error al copiar el archivo al directorio de destino.';
                }
            } else {
                $copiaExterna['mensaje'] = "La carpeta externa ({$destDir}) no está disponible o no tiene permisos de escritura (verifica que la memoria USB esté conectada).";
            }
        }
    }

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    $msgFinal = 'Respaldo creado correctamente.';
    if ($copiaExterna['habilitada']) {
        if ($copiaExterna['exito']) {
            $msgFinal .= ' Copia secundaria guardada en el destino externo.';
        } else {
            $msgFinal .= ' ADVERTENCIA: ' . $copiaExterna['mensaje'];
        }
    }

    echo json_encode([
        'exito'         => true,
        'mensaje'       => $msgFinal,
        'fecha'         => $fecha,
        'tamano'        => $tamano,
        'copia_externa' => $copiaExterna,
    ]);

} catch (\Throwable $e) {
    if (file_exists($tempPath)) {
        @unlink($tempPath);
    }

    $logMsg = '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $e->getMessage()
            . ' | ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    @file_put_contents($logPath, $logMsg, FILE_APPEND | LOCK_EX);

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    http_response_code(500);
    echo json_encode([
        'exito'   => false,
        'mensaje' => 'No se pudo crear el respaldo. El respaldo anterior permanece intacto.',
    ]);
}

function generarDumpSQL(PDO $pdo, string $driver, array $tablas): string
{
    $lines = [];
    $lines[] = '-- Respaldo del Sistema de Empleados';
    $lines[] = '-- Generado : ' . date('Y-m-d H:i:s');
    $lines[] = '-- Motor    : ' . strtoupper($driver);
    $lines[] = '-- Tablas   : ' . implode(', ', $tablas);
    $lines[] = '';

    if ($driver === 'mysql') {
        $lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
        $lines[] = "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';";
        $lines[] = 'SET NAMES utf8mb4;';
    } else {
        $lines[] = 'PRAGMA foreign_keys = OFF;';
    }
    $lines[] = '';

    $pdo->beginTransaction();

    try {
        foreach ($tablas as $tabla) {
            $idCitado = $driver === 'mysql' ? "`{$tabla}`" : "\"{$tabla}\"";

            $lines[] = "-- Tabla: {$tabla}";

            if ($driver === 'sqlite') {
                $sqlSchema = $pdo
                    ->query("SELECT sql FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote($tabla))
                    ->fetchColumn();
                if ($sqlSchema) {
                    $lines[] = "DROP TABLE IF EXISTS {$idCitado};";
                    $lines[] = $sqlSchema . ';';
                    $lines[] = '';
                }
            } else {
                $row = $pdo->query("SHOW CREATE TABLE {$idCitado}")->fetch(\PDO::FETCH_NUM);
                if ($row) {
                    $lines[] = "DROP TABLE IF EXISTS {$idCitado};";
                    $lines[] = $row[1] . ';';
                    $lines[] = '';
                }
            }

            $rows = $pdo->query("SELECT * FROM {$idCitado}")->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) {
                $lines[] = '';
                continue;
            }

            $cols = array_keys($rows[0]);
            $colsSql = implode(', ', array_map(
                fn(string $c) => $driver === 'mysql' ? "`{$c}`" : "\"{$c}\"",
                $cols
            ));

            foreach ($rows as $row) {
                $vals = array_map(function ($val) use ($pdo): string {
                    if ($val === null) return 'NULL';
                    return $pdo->quote((string)$val);
                }, array_values($row));

                $lines[] = "INSERT INTO {$idCitado} ({$colsSql}) VALUES (" . implode(', ', $vals) . ');';
            }
            $lines[] = '';
        }

        $pdo->rollBack();

    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($driver === 'mysql') {
        $lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
    } else {
        $lines[] = 'PRAGMA foreign_keys = ON;';
    }

    return implode(PHP_EOL, $lines);
}
