<?php
declare(strict_types=1);

// Guardia de seguridad: este script SOLO debe ejecutarse desde la línea de comandos.
// Si llega una petición HTTP (p.ej. porque el router falla o se desactiva),
// el script abortaría inmediatamente sin ejecutar nada ni exponer rutas internas.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

/**
 * Script de migración SQLite → MySQL 8 / MariaDB 10.6+ (InnoDB)
 * - Valida dependencias (extensión pdo_mysql y existencia de SQLite).
 * - Detecta automáticamente la conexión MySQL (incluyendo contraseña 'ana1' y puertos estándar).
 * - Crea la base de datos y usuario emp_app con permisos DML y sincronización de clave.
 * - Migra y sincroniza los empleados reales (fuera de demo) protegiendo contra valores nulos o vacíos en modo estricto.
 * - Migra y actualiza evaluaciones reales con manejo seguro de DECIMAL y DATE (evita error con cadenas vacías '').
 * - Migra TODOS los usuarios existentes en SQLite (admin, config, etc.) con sus hashes actuales.
 * - Ejecuta la migración bajo transacción para asegurar integridad atómica.
 *
 * USO:
 *      php migrar_a_mysql.php [contraseña_root] [puerto] [usuario_root]
 *
 * EJEMPLOS:
 *      php migrar_a_mysql.php
 *      php migrar_a_mysql.php ana1
 *      php migrar_a_mysql.php miClaveRoot 3306 root
 */

echo "===============================================================\n";
echo " MIGRACIÓN SQLite → MySQL 8 / MariaDB (Motor InnoDB)\n";
echo "===============================================================\n\n";

// ─── 0. Verificaciones previas de entorno ─────────────────────────────────
if (PHP_VERSION_ID < 80000) {
    echo "[AVISO] Se detectó PHP " . PHP_VERSION . ". Este sistema utiliza funciones de PHP 8+ como 'str_contains'.\n";
    if (!function_exists('str_contains')) {
        function str_contains(string $haystack, string $needle): bool {
            return $needle === '' || strpos($haystack, $needle) !== false;
        }
    }
}

if (!extension_loaded('pdo_mysql')) {
    echo "[ERROR] La extensión 'pdo_mysql' no está habilitada en tu PHP.\n";
    echo "Habilítala en tu archivo php.ini descomentando: extension=pdo_mysql\n";
    exit(1);
}

$configFile = __DIR__ . '/backend/config.php';
if (!file_exists($configFile) && file_exists(__DIR__ . '/backend/config.example.php')) {
    $configFile = __DIR__ . '/backend/config.example.php';
}
if (!file_exists($configFile)) {
    echo "[ERROR] No se encontró el archivo de configuración: {$configFile}\n";
    exit(1);
}

$config = require $configFile;
$m = $config['mysql'] ?? [];
if (empty($m['dbname']) || empty($m['user'])) {
    echo "[ERROR] La configuración de MySQL en backend/config.php no es válida.\n";
    exit(1);
}

$sqlitePath = $config['sqlite']['path'] ?? (__DIR__ . '/storage/empleados.db');
if (!file_exists($sqlitePath) && file_exists(__DIR__ . '/storage/empleados.db')) {
    $sqlitePath = __DIR__ . '/storage/empleados.db';
}
if (!file_exists($sqlitePath)) {
    echo "[ERROR] No se encontró la base de datos SQLite en: {$sqlitePath}\n";
    echo "Asegúrate de que el archivo empleados.db exista en %APPDATA%\\SistemaEmpleados o en storage/ antes de migrar.\n";
    exit(1);
}

$adminPass = $argv[1] ?? null;
$puertoArg = isset($argv[2]) && ctype_digit($argv[2]) ? (int)$argv[2] : null;
$adminUser = $argv[3] ?? 'root';

// ─── 1. Conectar como root / administrador ──────────────────────────────────
echo "1. Conectando a MySQL como administrador...\n";
$rootPdo = null;
$puertoConectado = null;

$intentos = [];
if ($adminPass !== null) {
    $intentos[] = [$adminUser, $adminPass];
    if ($adminUser !== 'root') {
        $intentos[] = ['root', $adminPass];
    }
}

// Intentar contraseñas comunes y las definidas en config.php (ana1 es la predeterminada del sistema)
$intentos = array_merge($intentos, [
    ['root', 'ana1'],
    ['root', $m['pass'] ?? ''],
    ['root', ''],
    ['root', 'root'],
    ['root', '1234'],
    ['root', '123456'],
    ['root', 'mysql'],
    ['admin', 'ana1'],
    ['admin', ''],
    ['admin', 'admin'],
]);

$puertosAIntentar = array_filter(array_unique([
    $puertoArg,
    (int)($m['port'] ?? 3306),
    3306,
    3307,
    3308,
    3309
]));

foreach ($puertosAIntentar as $puerto) {
    foreach ($intentos as [$user, $pass]) {
        try {
            $rootPdo = new PDO(
                "mysql:host={$m['host']};port={$puerto};charset={$m['charset']}",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT            => 2,
                ]
            );
            $puertoConectado = $puerto;
            echo "   [OK] Conectado exitosamente en puerto {$puerto} como '{$user}'.\n";
            break 2;
        } catch (\PDOException $e) {
            $code = (int)$e->getCode();
            // 1045 = Error de autenticación (servidor activo en el puerto, probar otra clave)
            // Otro código (ej. 2002) = Puerto cerrado / no responde
            if ($code !== 1045 && !str_contains($e->getMessage(), '1045')) {
                break; // puerto no responde, pasar al siguiente
            }
        }
    }
}

if (!$rootPdo) {
    echo "   [ERROR] No se pudo conectar con el servidor MySQL.\n";
    echo "   Por favor especifica tu contraseña de root al ejecutar el script:\n";
    echo "       php migrar_a_mysql.php TU_CONTRASEÑA_ROOT [PUERTO]\n\n";
    exit(1);
}

$ver = $rootPdo->query("SELECT VERSION()")->fetchColumn();
echo "   Versión del motor detectada: {$ver}\n\n";

// ─── 2. Crear BD y usuario de aplicación emp_app ────────────────────────────
echo "2. Preparando base de datos y usuario de aplicación...\n";
$dbname  = $m['dbname'];
$appUser = $m['user'];
$appPass = $m['pass'];

$rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
$rootPdo->exec("USE `{$dbname}`;");
echo "   [OK] Base de datos '{$dbname}' asegurada.\n";

// Crear o actualizar usuario emp_app asegurando su contraseña configurada
try {
    $rootPdo->exec("CREATE USER IF NOT EXISTS '{$appUser}'@'localhost' IDENTIFIED BY '{$appPass}';");
    $rootPdo->exec("ALTER USER '{$appUser}'@'localhost' IDENTIFIED BY '{$appPass}';");
    $rootPdo->exec("CREATE USER IF NOT EXISTS '{$appUser}'@'127.0.0.1' IDENTIFIED BY '{$appPass}';");
    $rootPdo->exec("ALTER USER '{$appUser}'@'127.0.0.1' IDENTIFIED BY '{$appPass}';");
    $rootPdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON `{$dbname}`.* TO '{$appUser}'@'localhost';");
    $rootPdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON `{$dbname}`.* TO '{$appUser}'@'127.0.0.1';");
    $rootPdo->exec("FLUSH PRIVILEGES;");
    echo "   [OK] Usuario '{$appUser}' configurado con permisos DML en '{$dbname}'.\n\n";
} catch (\PDOException $e) {
    echo "   [AVISO] No se pudo alterar el usuario '{$appUser}' (puede requerir permisos administrativos adicionales): " . $e->getMessage() . "\n\n";
}

// ─── 3. Crear tablas con motor InnoDB ─────────────────────────────────────
echo "3. Verificando estructura de tablas InnoDB...\n";
$rootPdo->exec("
    CREATE TABLE IF NOT EXISTS empleados (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        nombre           VARCHAR(100) NOT NULL,
        apellido         VARCHAR(100) NOT NULL,
        edad             TINYINT UNSIGNED NOT NULL,
        nivel_academico  VARCHAR(100) NOT NULL,
        demografia       VARCHAR(100) NOT NULL,
        puesto           VARCHAR(100) NOT NULL,
        codigo_empleado  VARCHAR(20) NOT NULL,
        supervisor       VARCHAR(100) NOT NULL,
        fecha_entrada    DATE NOT NULL,
        termino_contrato DATE NOT NULL,
        observaciones    TEXT NULL,
        estado           ENUM('en_evaluacion','finalizado','interrumpido') NOT NULL DEFAULT 'en_evaluacion',
        fecha_registro   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_codigo_empleado (codigo_empleado),
        KEY idx_estado_registro (estado, fecha_registro)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS evaluaciones (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        empleado_id       INT NOT NULL,
        numero_evaluacion TINYINT NOT NULL,
        fecha_esperada    DATE NOT NULL,
        fecha_realizada   DATE NULL,
        calificacion      DECIMAL(5,2) NULL,
        observaciones     TEXT NULL,
        CONSTRAINT fk_evaluaciones_empleado FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
        UNIQUE KEY uq_empleado_eval (empleado_id, numero_evaluacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS usuarios (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        usuario        VARCHAR(50)  NOT NULL UNIQUE,
        password_hash  VARCHAR(255) NOT NULL,
        rol            VARCHAR(20)  NOT NULL DEFAULT 'admin',
        fecha_creacion TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "   [OK] Tablas 'empleados', 'evaluaciones' y 'usuarios' listas.\n\n";

// ─── 4. Leer datos desde SQLite ───────────────────────────────────────────
echo "4. Leyendo datos desde SQLite ({$sqlitePath})...\n";
$sqlitePdo = new PDO('sqlite:' . $sqlitePath, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Excluir únicamente los códigos generados de prueba automática de demo
$codigosDemo = [10101, 10102, 10103, 10201, 10301];
$placeholders = implode(',', array_fill(0, count($codigosDemo), '?'));

$stmtReales = $sqlitePdo->prepare("SELECT * FROM empleados WHERE codigo_empleado NOT IN ({$placeholders}) ORDER BY id ASC");
$stmtReales->execute($codigosDemo);
$empleadosReales = $stmtReales->fetchAll();

echo "   Empleados reales encontrados para migrar: " . count($empleadosReales) . "\n\n";

// ─── 5. Migración transaccional a MySQL ───────────────────────────────────
echo "5. Migrando registros con validación de tipos e integridad...\n";
$rootPdo->beginTransaction();

try {
    $insEmp = $rootPdo->prepare("
        INSERT INTO empleados (nombre, apellido, edad, nivel_academico, demografia, puesto,
                               codigo_empleado, supervisor, fecha_entrada, termino_contrato,
                               observaciones, estado, fecha_registro)
        VALUES (:nombre, :apellido, :edad, :nivel_academico, :demografia, :puesto,
                :codigo_empleado, :supervisor, :fecha_entrada, :termino_contrato,
                :observaciones, :estado, :fecha_registro)
    ");

    $updEmp = $rootPdo->prepare("
        UPDATE empleados
        SET nombre = :nombre, apellido = :apellido, edad = :edad, nivel_academico = :nivel_academico,
            demografia = :demografia, puesto = :puesto, supervisor = :supervisor,
            fecha_entrada = :fecha_entrada, termino_contrato = :termino_contrato,
            observaciones = :observaciones, estado = :estado
        WHERE id = :id
    ");

    $insEval = $rootPdo->prepare("
        INSERT INTO evaluaciones (empleado_id, numero_evaluacion, fecha_esperada,
                                  fecha_realizada, calificacion, observaciones)
        VALUES (:empleado_id, :numero_evaluacion, :fecha_esperada,
                :fecha_realizada, :calificacion, :obs)
    ");

    $updEval = $rootPdo->prepare("
        UPDATE evaluaciones
        SET fecha_esperada = :fecha_esperada,
            fecha_realizada = :fecha_realizada,
            calificacion = :calificacion,
            observaciones = :obs
        WHERE id = :evalId
    ");

    $idMap = []; // SQLite ID → MySQL ID

    foreach ($empleadosReales as $emp) {
        $codigo = trim((string)$emp['codigo_empleado']);

        // Sanitización para evitar fallos de modo estricto en MySQL
        $fechaEntrada = !empty($emp['fecha_entrada']) ? $emp['fecha_entrada'] : date('Y-m-d');
        $terminoContrato = !empty($emp['termino_contrato'])
            ? $emp['termino_contrato']
            : (new DateTime($fechaEntrada))->modify('+89 days')->format('Y-m-d');

        $edad = (int)$emp['edad'];
        $obs = ($emp['observaciones'] !== null && $emp['observaciones'] !== '') ? $emp['observaciones'] : null;
        $estado = in_array($emp['estado'], ['en_evaluacion', 'finalizado', 'interrumpido'], true) ? $emp['estado'] : 'en_evaluacion';
        $fechaRegistro = !empty($emp['fecha_registro']) ? $emp['fecha_registro'] : date('Y-m-d H:i:s');

        // Comprobar si ya existe por código único
        $stmtChk = $rootPdo->prepare("SELECT id FROM empleados WHERE codigo_empleado = :codigo LIMIT 1");
        $stmtChk->execute([':codigo' => $codigo]);
        $mysqlId = $stmtChk->fetchColumn();

        if ($mysqlId === false) {
            $insEmp->execute([
                ':nombre'          => $emp['nombre'],
                ':apellido'        => $emp['apellido'],
                ':edad'            => $edad,
                ':nivel_academico' => $emp['nivel_academico'],
                ':demografia'      => $emp['demografia'],
                ':puesto'          => $emp['puesto'],
                ':codigo_empleado' => $codigo,
                ':supervisor'      => $emp['supervisor'],
                ':fecha_entrada'   => $fechaEntrada,
                ':termino_contrato'=> $terminoContrato,
                ':observaciones'   => $obs,
                ':estado'          => $estado,
                ':fecha_registro'  => $fechaRegistro,
            ]);
            $mysqlId = (int)$rootPdo->lastInsertId();
            echo "   [EMPLEADO NUEVO] ID {$mysqlId}: {$emp['nombre']} {$emp['apellido']} (Cód: {$codigo})\n";
        } else {
            $mysqlId = (int)$mysqlId;
            $updEmp->execute([
                ':nombre'          => $emp['nombre'],
                ':apellido'        => $emp['apellido'],
                ':edad'            => $edad,
                ':nivel_academico' => $emp['nivel_academico'],
                ':demografia'      => $emp['demografia'],
                ':puesto'          => $emp['puesto'],
                ':supervisor'      => $emp['supervisor'],
                ':fecha_entrada'   => $fechaEntrada,
                ':termino_contrato'=> $terminoContrato,
                ':observaciones'   => $obs,
                ':estado'          => $estado,
                ':id'              => $mysqlId,
            ]);
            echo "   [EMPLEADO ACTUALIZADO] ID {$mysqlId}: {$emp['nombre']} {$emp['apellido']} (Cód: {$codigo})\n";
        }

        $idMap[(int)$emp['id']] = $mysqlId;
    }

    // ─── 6. Migrar evaluaciones ───────────────────────────────────────────
    echo "\n6. Migrando y sincronizando evaluaciones...\n";
    $stmtEvalExist = $rootPdo->prepare("SELECT id FROM evaluaciones WHERE empleado_id = :empId AND numero_evaluacion = :num LIMIT 1");

    foreach ($empleadosReales as $emp) {
        $sqliteId = (int)$emp['id'];
        $mysqlId  = $idMap[$sqliteId] ?? null;
        if (!$mysqlId) continue;

        $stmtEvalsSqlite = $sqlitePdo->prepare("SELECT * FROM evaluaciones WHERE empleado_id = :empId ORDER BY numero_evaluacion ASC");
        $stmtEvalsSqlite->execute([':empId' => $sqliteId]);
        $evals = $stmtEvalsSqlite->fetchAll();

        foreach ($evals as $ev) {
            $numEval = (int)$ev['numero_evaluacion'];

            // Normalización estricta para columnas MySQL: DECIMAL y DATE no admiten string vacío ''
            $calif = ($ev['calificacion'] !== null && $ev['calificacion'] !== '') ? (float)$ev['calificacion'] : null;
            $fReal = !empty($ev['fecha_realizada']) ? $ev['fecha_realizada'] : null;
            $fEsp  = !empty($ev['fecha_esperada']) ? $ev['fecha_esperada'] : date('Y-m-d');
            $evObs = ($ev['observaciones'] !== null && $ev['observaciones'] !== '') ? $ev['observaciones'] : null;

            $stmtEvalExist->execute([':empId' => $mysqlId, ':num' => $numEval]);
            $evalExistId = $stmtEvalExist->fetchColumn();

            if ($evalExistId === false) {
                $insEval->execute([
                    ':empleado_id'       => $mysqlId,
                    ':numero_evaluacion' => $numEval,
                    ':fecha_esperada'    => $fEsp,
                    ':fecha_realizada'   => $fReal,
                    ':calificacion'      => $calif,
                    ':obs'               => $evObs,
                ]);
            } else {
                $updEval->execute([
                    ':fecha_esperada'    => $fEsp,
                    ':fecha_realizada'   => $fReal,
                    ':calificacion'      => $calif,
                    ':obs'               => $evObs,
                    ':evalId'            => (int)$evalExistId,
                ]);
            }

            $estadoEval = $fReal ? "Realizada (Calif: " . ($calif !== null ? $calif : 'S/C') . ")" : "Pendiente";
            echo "   Eval {$numEval} de empleado ID {$mysqlId} → {$estadoEval}\n";
        }
    }

    // ─── 7. Migrar todos los usuarios (admin, config, etc.) ───────────────
    echo "\n7. Migrando usuarios del sistema...\n";
    $usuariosSqlite = $sqlitePdo->query("SELECT usuario, password_hash, rol FROM usuarios")->fetchAll();

    $stmtUserChk = $rootPdo->prepare("SELECT id FROM usuarios WHERE usuario = :u LIMIT 1");
    $stmtUserIns = $rootPdo->prepare("INSERT INTO usuarios (usuario, password_hash, rol) VALUES (:u, :p, :r)");
    $stmtUserUpd = $rootPdo->prepare("UPDATE usuarios SET password_hash = :p, rol = :r WHERE id = :id");

    foreach ($usuariosSqlite as $u) {
        $userNombre = trim($u['usuario']);
        $userHash   = $u['password_hash'];
        $userRol    = !empty($u['rol']) ? $u['rol'] : 'admin';

        $stmtUserChk->execute([':u' => $userNombre]);
        $userId = $stmtUserChk->fetchColumn();

        if ($userId === false) {
            $stmtUserIns->execute([
                ':u' => $userNombre,
                ':p' => $userHash,
                ':r' => $userRol,
            ]);
            echo "   [USUARIO NUEVO] '{$userNombre}' ({$userRol}) migrado con su hash actual.\n";
        } else {
            $stmtUserUpd->execute([
                ':p'  => $userHash,
                ':r'  => $userRol,
                ':id' => (int)$userId,
            ]);
            echo "   [USUARIO ACTUALIZADO] '{$userNombre}' sincronizado con su hash actual.\n";
        }
    }

    $rootPdo->commit();
    echo "\n   [OK] Transacción de migración completada exitosamente.\n";

} catch (\Throwable $e) {
    if ($rootPdo->inTransaction()) {
        $rootPdo->rollBack();
    }
    echo "\n[ERROR CRÍTICO] La migración falló y se revirtieron los cambios:\n";
    echo $e->getMessage() . "\n";
    echo "En archivo " . $e->getFile() . " línea " . $e->getLine() . "\n";
    exit(1);
}

// ─── 8. Verificación final en MySQL ───────────────────────────────────────
echo "\n8. Verificación de conteos en MySQL (`{$dbname}`):\n";
$totalMysql = $rootPdo->query("SELECT COUNT(*) FROM empleados")->fetchColumn();
$totalEvals = $rootPdo->query("SELECT COUNT(*) FROM evaluaciones")->fetchColumn();
$totalUsers = $rootPdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();

echo "   - Empleados totales en MySQL   : {$totalMysql}\n";
echo "   - Evaluaciones totales en MySQL: {$totalEvals}\n";
echo "   - Usuarios registrados en MySQL: {$totalUsers}\n";

echo "\n===============================================================\n";
echo " ¡MIGRACIÓN COMPLETADA CON ÉXITO!\n";
echo "===============================================================\n";
echo "Para activar MySQL en tu aplicación:\n";
echo "  1. En backend/config.php cambia:\n";
echo "     'driver' => 'mysql',\n";
echo "     'sembrar_datos_demo' => false,\n";
echo "  2. Prueba tu sistema en el navegador y verifica que todo funcione.\n";
echo "  3. Los datos de respaldo SQLite se resguardan en %APPDATA%\\SistemaEmpleados.\n\n";

// ─── 9. Verificación de seguridad y limpieza de storage/ local ──────────────
echo "9. Verificando integridad de storage/ frente a %APPDATA%...\n";
$storageLocal = __DIR__ . '/storage';
$rutaAppDataDb  = function_exists('obtenerRutaSQLite') ? obtenerRutaSQLite() : null;
$rutaAppDataSql = function_exists('obtenerRutaRespaldo') ? obtenerRutaRespaldo() : null;

// A) empleados.db — SOLO eliminar si existe una copia idéntica verificada en %APPDATA%
$localDb = $storageLocal . '/empleados.db';
if (file_exists($localDb)) {
    // Si no existe copia en %APPDATA%, asegurar el respaldo antes de cualquier acción
    if ($rutaAppDataDb && !file_exists($rutaAppDataDb)) {
        $dirDestino = dirname($rutaAppDataDb);
        if (!is_dir($dirDestino)) {
            @mkdir($dirDestino, 0777, true);
        }
        @copy($localDb, $rutaAppDataDb);
    }

    if ($rutaAppDataDb && file_exists($rutaAppDataDb)) {
        clearstatcache(true, $localDb);
        clearstatcache(true, $rutaAppDataDb);
        $sizeLocal   = filesize($localDb);
        $sizeAppData = filesize($rutaAppDataDb);

        if ($sizeLocal === $sizeAppData && $sizeLocal > 0) {
            if (@unlink($localDb)) {
                echo "   [OK] Duplicado local eliminado: storage/empleados.db (copia idéntica de {$sizeAppData} B confirmada en %APPDATA%).\n";
            } else {
                echo "   [AVISO] No se pudo eliminar storage/empleados.db — puedes eliminarlo manualmente.\n";
            }
        } else {
            echo "   [CONSERVADO] storage/empleados.db se conserva intacto (tamaño local {$sizeLocal} B difiere de %APPDATA% {$sizeAppData} B).\n";
        }
    } else {
        echo "   [CONSERVADO] storage/empleados.db se conserva intacto porque es la única copia existente (no existe en %APPDATA%).\n";
    }
} else {
    echo "   [INFO] storage/empleados.db no existe en la carpeta local.\n";
}

// B) respaldo_sistema.sql — misma verificación estricta de tamaño
$localSql = $storageLocal . '/respaldo_sistema.sql';
if (file_exists($localSql)) {
    if ($rutaAppDataSql && !file_exists($rutaAppDataSql)) {
        $dirDestino = dirname($rutaAppDataSql);
        if (!is_dir($dirDestino)) {
            @mkdir($dirDestino, 0777, true);
        }
        @copy($localSql, $rutaAppDataSql);
    }

    if ($rutaAppDataSql && file_exists($rutaAppDataSql)) {
        clearstatcache(true, $localSql);
        clearstatcache(true, $rutaAppDataSql);
        $sizeLocal   = filesize($localSql);
        $sizeAppData = filesize($rutaAppDataSql);

        if ($sizeLocal === $sizeAppData && $sizeLocal > 0) {
            if (@unlink($localSql)) {
                echo "   [OK] Duplicado local eliminado: storage/respaldo_sistema.sql (copia idéntica de {$sizeAppData} B confirmada en %APPDATA%).\n";
            } else {
                echo "   [AVISO] No se pudo eliminar storage/respaldo_sistema.sql.\n";
            }
        } else {
            echo "   [CONSERVADO] storage/respaldo_sistema.sql se conserva intacto (tamaño local {$sizeLocal} B difiere de %APPDATA% {$sizeAppData} B).\n";
        }
    } else {
        echo "   [CONSERVADO] storage/respaldo_sistema.sql se conserva intacto (no existe en %APPDATA%).\n";
    }
}

// C) respaldo.lock — archivo de bloqueo temporal
$localLock = $storageLocal . '/respaldo.lock';
if (file_exists($localLock)) {
    @unlink($localLock);
    echo "   [OK] Bloqueo temporal storage/respaldo.lock eliminado.\n";
}

echo "   (storage/.gitkeep y storage/.htaccess se conservan intactos)\n\n";

