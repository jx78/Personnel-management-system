<?php
declare(strict_types=1);

// Guardia de seguridad: solo CLI.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

/**
 * Script de instalación y verificación para MySQL 8 (InnoDB) y MariaDB 10.6+ (InnoDB).
 *
 * Uso:
 *     php database/instalar_mysql.php [admin_password] [puerto] [admin_user]
 *
 * Ejemplo:
 *     php database/instalar_mysql.php miClaveRoot 3306 root
 */

echo "===============================================================\n";
echo " Instalador / Verificador de Base de Datos para MySQL 8 / MariaDB 10.6\n";
echo "===============================================================\n\n";

$configFile = __DIR__ . '/../backend/config.php';
if (!file_exists($configFile) && file_exists(__DIR__ . '/../backend/config.example.php')) {
    $configFile = __DIR__ . '/../backend/config.example.php';
}
if (!file_exists($configFile)) {
    die("[ERROR] No se encontró el archivo de configuración: {$configFile}\n");
}

$config = require $configFile;
$m = $config['mysql'];

$adminUser = $argv[3] ?? 'root';
$adminPass = $argv[1] ?? '';
$puerto    = isset($argv[2]) ? (int)$argv[2] : (int)$m['port'];
$host      = $m['host'];
$dbname    = $m['dbname'];

echo "Configuración de aplicación detectada:\n";
echo " - Base de datos destino: {$dbname}\n";
echo " - Usuario de aplicación: {$m['user']}\n";
echo " - Host servidor:        {$host}\n";
echo " - Puerto servidor:      {$puerto}\n";
echo " - Charset:              {$m['charset']} (COLLATE utf8mb4_unicode_ci)\n";
echo " - Motor:                InnoDB\n\n";

// Conectar con el usuario administrador para crear esquema y usuarios
echo "1. Conectando al servidor MySQL 8 / MariaDB con usuario administrador '{$adminUser}'...\n";

$adminPdo = null;
$puertosAIntentar = array_unique([$puerto, 3306, 3307]);

foreach ($puertosAIntentar as $p) {
    try {
        $dsnAdmin = "mysql:host={$host};port={$p};charset={$m['charset']}";
        $adminPdo = new PDO($dsnAdmin, $adminUser, $adminPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        $puerto = $p;
        echo "   [OK] Conexión administrativa exitosa en el puerto {$puerto}.\n";
        break;
    } catch (PDOException $e) {
        // Continuar intentando si hay puertos alternativos
    }
}

if (!$adminPdo) {
    echo "   [AVISO] No se pudo conectar automáticamente como '{$adminUser}' en los puertos probados.\n";
    echo "   Si tu usuario '{$adminUser}' tiene contraseña, pásala como primer argumento:\n";
    echo "       php database/instalar_mysql.php TU_CONTRASEÑA [PUERTO]\n\n";
} else {
    $version = $adminPdo->query("SELECT VERSION()")->fetchColumn();
    echo "   [INFO] Versión del servidor detectada: {$version}\n\n";

    echo "2. Creando base de datos '{$dbname}' si no existe...\n";
    $adminPdo->exec("
        CREATE DATABASE IF NOT EXISTS `{$dbname}`
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci;
    ");
    echo "   [OK] Base de datos lista.\n\n";

    echo "3. Ejecutando schema.sql (Tablas InnoDB + Usuario de Aplicación)...\n";
    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    if ($schemaSql === false) {
        die("[ERROR] No se pudo leer database/schema.sql\n");
    }

    // Ejecutar statements del schema
    $adminPdo->exec("USE `{$dbname}`;");
    
    // Dividir sentencias respetando delimitadores básicos
    $adminPdo->exec($schemaSql);
    echo "   [OK] Esquema, tablas InnoDB y permisos aplicados con éxito.\n\n";
}

// 4. Probar la conexión con las credenciales de la aplicación ('emp_app')
echo "4. Validando conexión como usuario de aplicación '{$m['user']}'...\n";
try {
    $dsnApp = "mysql:host={$host};port={$puerto};dbname={$dbname};charset={$m['charset']}";
    $appPdo = new PDO($dsnApp, $m['user'], $m['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 3,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$m['charset']} COLLATE utf8mb4_unicode_ci",
    ]);
    echo "   [OK] Conexión de aplicación verificada correctamente.\n\n";

    // Verificar tablas
    echo "5. Verificando integridad de tablas y motor InnoDB...\n";
    $tablas = $appPdo->query("SHOW TABLE STATUS FROM `{$dbname}`")->fetchAll();
    foreach ($tablas as $t) {
        $nombreTabla = $t['Name'] ?? $t['name'] ?? '';
        $motor = $t['Engine'] ?? $t['engine'] ?? '';
        $cotejamiento = $t['Collation'] ?? $t['collation'] ?? '';
        echo "   - Tabla '{$nombreTabla}': Motor={$motor}, Cotejamiento={$cotejamiento}\n";
    }

    // Inicializar datos si está configurado
    require_once __DIR__ . '/../backend/inicializar_bd.php';
    asegurarTablasYDatos($appPdo, 'mysql');
    echo "\n   [OK] Inicialización completa con éxito.\n";

} catch (PDOException $e) {
    echo "   [PENDIENTE] No se pudo conectar con el usuario '{$m['user']}': " . $e->getMessage() . "\n";
    echo "   Para aplicar el esquema manualmente ejecuta con root:\n";
    echo "       mysql -u root -p < database/schema.sql\n";
    echo "   O con este mismo script:\n";
    echo "       php database/instalar_mysql.php TU_CLAVE_ROOT\n";
}

echo "\n===============================================================\n";
echo " Finalizado.\n";
echo "===============================================================\n";
