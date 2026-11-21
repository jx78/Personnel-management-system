<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth_required.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/buscador.php';
require_once __DIR__ . '/inicializar_bd.php';

$config = require __DIR__ . '/config.php';

$nombre     = trim($_GET['nombre'] ?? '');
$codigo     = trim($_GET['codigo'] ?? '');
$pagina     = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$porPagina  = isset($_GET['por_pagina']) ? (int)$_GET['por_pagina'] : ($config['registros_por_pagina'] ?? 10);

try {
    $resultado = buscarPorEstado($pdo, 'en_evaluacion', $nombre, $codigo, $pagina, $porPagina);
    echo json_encode([
        'exito'      => true,
        'datos'      => $resultado['datos'],
        'paginacion' => $resultado['paginacion']
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'exito'   => false,
        'mensaje' => 'Error al buscar los datos: ' . $e->getMessage()
    ]);
}
