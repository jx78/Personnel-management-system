<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

$autenticado = !empty($_SESSION['autenticado']);
$configAutenticado = !empty($_SESSION['config_autenticado']);

$clavesFabrica = [
    'admin'  => false,
    'config' => false,
    'alerta' => false,
];

if ($autenticado) {
    try {
        $stmt = $pdo->query("SELECT usuario, password_hash FROM usuarios WHERE usuario IN ('admin', 'config')");
        $hashes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if (isset($hashes['admin']) && password_verify('ana2', (string)$hashes['admin'])) {
            $clavesFabrica['admin'] = true;
            $clavesFabrica['alerta'] = true;
        }
        if (isset($hashes['config']) && password_verify('ana3', (string)$hashes['config'])) {
            $clavesFabrica['config'] = true;
            $clavesFabrica['alerta'] = true;
        }
    } catch (\Throwable $e) { }
}

echo json_encode([
    'autenticado'        => $autenticado,
    'config_autenticado' => $configAutenticado,
    'usuario'            => $_SESSION['usuario'] ?? null,
    'claves_fabrica'     => $clavesFabrica,
]);
