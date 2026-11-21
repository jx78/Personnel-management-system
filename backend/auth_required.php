<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$autenticado = !empty($_SESSION['autenticado']) && $_SESSION['autenticado'] === true;

if ($autenticado) {
    $tiempoMaximoInactividad = 8 * 3600;
    $ultimoAcceso = $_SESSION['ultimo_acceso'] ?? $_SESSION['login_time'] ?? 0;
    if (time() - $ultimoAcceso > $tiempoMaximoInactividad) {
        $_SESSION = [];
        session_destroy();
        $autenticado = false;
    } else {
        $_SESSION['ultimo_acceso'] = time();
    }
}

if (!$autenticado) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode([
        'exito'   => false,
        'codigo'  => 401,
        'mensaje' => 'No autorizado. Se requiere una sesión activa para acceder a este recurso.'
    ]);
    exit;
}
