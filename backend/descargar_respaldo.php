<?php
declare(strict_types=1);

require_once __DIR__ . '/config_auth_required.php';
require_once __DIR__ . '/storage.php';

$backupPath = obtenerRutaRespaldo();

if (!file_exists($backupPath)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode(['exito' => false, 'mensaje' => 'Aún no hay ningún respaldo disponible para descargar.']);
    exit;
}

clearstatcache(true, $backupPath);
$tamano   = filesize($backupPath);
$filename = 'respaldo_empleados_' . date('Y-m-d_H-i') . '.sql';

if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $tamano);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

readfile($backupPath);
exit;
