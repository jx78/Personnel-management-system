<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config_auth_required.php';
require_once __DIR__ . '/storage.php';

$backupPath = obtenerRutaRespaldo();

if (!file_exists($backupPath)) {
    echo json_encode(['existe' => false]);
    exit;
}

clearstatcache(true, $backupPath);
$mtime  = filemtime($backupPath);
$tamano = filesize($backupPath);

echo json_encode([
    'existe'  => true,
    'fecha'   => date('d/m/Y H:i:s', $mtime !== false ? $mtime : time()),
    'tamano'  => $tamano,
]);
