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

$lockPath   = obtenerRutaRespaldoLock();
$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    if ($lockHandle !== false) fclose($lockHandle);
    echo json_encode(['exito' => false, 'mensaje' => 'Hay otra operación de base de datos en proceso. Intenta nuevamente en unos segundos.']);
    exit;
}

$origen = trim((string)($_POST['origen'] ?? 'ultimo'));
$sqlContenido = '';

try {
    if ($origen === 'ultimo') {
        $backupPath = obtenerRutaRespaldo();
        if (!file_exists($backupPath) || filesize($backupPath) === 0) {
            throw new \RuntimeException('No existe un respaldo previo guardado en el servidor.');
        }
        $sqlContenido = (string)file_get_contents($backupPath);
    } elseif ($origen === 'archivo') {
        if (!isset($_FILES['archivo_sql']) || $_FILES['archivo_sql']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['archivo_sql']['error'] ?? -1;
            throw new \RuntimeException("Error al subir el archivo de respaldo (código: {$errCode}).");
        }

        $tmpFile = $_FILES['archivo_sql']['tmp_name'];
        $nombreOrig = (string)$_FILES['archivo_sql']['name'];
        if (strtolower(pathinfo($nombreOrig, PATHINFO_EXTENSION)) !== 'sql') {
            throw new \RuntimeException('El archivo seleccionado debe tener extensión .sql.');
        }

        $tamano = (int)$_FILES['archivo_sql']['size'];
        if ($tamano <= 0 || $tamano > 50 * 1024 * 1024) {
            throw new \RuntimeException('El archivo de respaldo está vacío o excede el límite de 50 MB.');
        }

        $sqlContenido = (string)file_get_contents($tmpFile);
    } else {
        throw new \RuntimeException('Origen de restauración no válido.');
    }

    if (trim($sqlContenido) === '') {
        throw new \RuntimeException('El archivo de respaldo no contiene instrucciones SQL.');
    }

    if (!stripos($sqlContenido, 'empleados') || !stripos($sqlContenido, 'usuarios')) {
        throw new \RuntimeException('El archivo no parece ser un respaldo válido de este sistema (faltan definiciones de empleados/usuarios).');
    }

    $sqlitePath   = obtenerRutaSQLite();
    $rollbackPath = obtenerRutaRespaldoRollback();

    if ($driverUsado === 'sqlite') {
        if (file_exists($sqlitePath)) {
            @copy($sqlitePath, $rollbackPath);
        }

        try {
            $pdo->exec('PRAGMA foreign_keys = OFF;');
            $pdo->exec($sqlContenido);
            $pdo->exec('PRAGMA foreign_keys = ON;');

            $stmtEmp = $pdo->query('SELECT COUNT(*) FROM "empleados"');
            $numEmp = (int)$stmtEmp->fetchColumn();

            $stmtEval = $pdo->query('SELECT COUNT(*) FROM "evaluaciones"');
            $numEval = (int)$stmtEval->fetchColumn();

            $stmtAdmin = $pdo->query("SELECT COUNT(*) FROM \"usuarios\" WHERE usuario = 'admin'");
            $adminExiste = (int)$stmtAdmin->fetchColumn() > 0;

            if (!$adminExiste) {
                throw new \RuntimeException('La restauración no generó el usuario administrador obligatorio.');
            }

            if (file_exists($rollbackPath)) {
                @unlink($rollbackPath);
            }

            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);

            echo json_encode([
                'exito'        => true,
                'mensaje'      => 'Base de datos restaurada con éxito.',
                'empleados'    => $numEmp,
                'evaluaciones' => $numEval,
            ]);
            exit;

        } catch (\Throwable $exRestauracion) {
            if (file_exists($rollbackPath)) {
                unset($pdo);
                @copy($rollbackPath, $sqlitePath);
                @unlink($rollbackPath);
            }
            throw new \RuntimeException('Fallo al ejecutar el respaldo: ' . $exRestauracion->getMessage() . '. Se restableció el estado anterior de la base de datos.');
        }

    } else {
        $pdo->beginTransaction();
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
            $pdo->exec($sqlContenido);
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');

            $stmtAdmin = $pdo->query("SELECT COUNT(*) FROM `usuarios` WHERE usuario = 'admin'");
            if ((int)$stmtAdmin->fetchColumn() === 0) {
                throw new \RuntimeException('La restauración no generó el usuario administrador en MySQL.');
            }

            $numEmp = (int)$pdo->query('SELECT COUNT(*) FROM `empleados`')->fetchColumn();
            $numEval = (int)$pdo->query('SELECT COUNT(*) FROM `evaluaciones`')->fetchColumn();

            $pdo->commit();

            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);

            echo json_encode([
                'exito'        => true,
                'mensaje'      => 'Base de datos MySQL restaurada con éxito.',
                'empleados'    => $numEmp,
                'evaluaciones' => $numEval,
            ]);
            exit;

        } catch (\Throwable $exMySQL) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new \RuntimeException('Error al restaurar en MySQL: ' . $exMySQL->getMessage());
        }
    }

} catch (\Throwable $e) {
    if (isset($lockHandle) && is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }

    http_response_code(500);
    echo json_encode([
        'exito'   => false,
        'mensaje' => $e->getMessage(),
    ]);
}
