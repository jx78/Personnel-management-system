<?php
declare(strict_types=1);

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile) && file_exists(__DIR__ . '/config.example.php')) {
    $configFile = __DIR__ . '/config.example.php';
}
$config = require $configFile;

$pdo = null;
$driverUsado = 'sqlite';

if (($config['driver'] === 'mysql' || $config['driver'] === 'auto') && extension_loaded('pdo_mysql')) {
    $m = $config['mysql'];
    if (!empty($m['host']) && !empty($m['dbname']) && $m['dbname'] !== 'tu_base_de_datos') {
        $puertosAIntentar = array_unique([(int)$m['port'], 3306, 3307]);
        $ultimoError = null;

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
            $opciones[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = 2;
        }
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $opciones[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$m['charset']} COLLATE utf8mb4_unicode_ci";
        }

        foreach ($puertosAIntentar as $puerto) {
            try {
                $dsn = "mysql:host={$m['host']};port={$puerto};dbname={$m['dbname']};charset={$m['charset']}";
                $pdo = new PDO($dsn, $m['user'], $m['pass'], $opciones);
                $driverUsado = 'mysql';
                break;
            } catch (\PDOException $e) {
                $ultimoError = $e;
                $codigo = (int)$e->getCode();
                $mensaje = $e->getMessage();
                if ($codigo === 1045 || str_contains($mensaje, '1045') || str_contains($mensaje, '1049')) {
                    break;
                }
            }
        }

        if ($pdo === null && $config['driver'] === 'mysql') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'exito'   => false,
                'mensaje' => 'Error de conexión con MySQL 8 / MariaDB 10.6: ' . ($ultimoError ? $ultimoError->getMessage() : 'Conexión rechazada.'),
                'ayuda'   => 'Asegúrate de que el servidor MySQL 8 / MariaDB esté en ejecución y ejecuta database/schema.sql con un usuario administrador.'
            ]);
            exit;
        }
    }
}

if ($pdo === null) {
    try {
        $sqlitePath = $config['sqlite']['path'];
        $dbDir = dirname($sqlitePath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        $dsn = "sqlite:" . $sqlitePath;
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");
        $driverUsado = 'sqlite';
    } catch (\PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'exito'   => false,
            'mensaje' => 'Error al inicializar la base de datos local SQLite: ' . $e->getMessage()
        ]);
        exit;
    }
}

require_once __DIR__ . '/inicializar_bd.php';
asegurarTablasYDatos($pdo, $driverUsado);
