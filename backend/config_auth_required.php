<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$autenticado = !empty($_SESSION['autenticado']) && $_SESSION['autenticado'] === true;
$configAutenticado = !empty($_SESSION['config_autenticado']) && $_SESSION['config_autenticado'] === true;

if ($autenticado) {
    $tiempoMaximoInactividad = 8 * 3600;
    $ultimoAcceso = $_SESSION['ultimo_acceso'] ?? $_SESSION['login_time'] ?? 0;
    if (time() - $ultimoAcceso > $tiempoMaximoInactividad) {
        $_SESSION = [];
        session_destroy();
        $autenticado = false;
        $configAutenticado = false;
    } else {
        $_SESSION['ultimo_acceso'] = time();
    }
}

if (!$autenticado || !$configAutenticado) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode([
        'exito'    => false,
        'codigo'   => 403,
        'mensaje'  => 'Acceso denegado. Se requiere haber iniciado sesión previamente y autenticarse en el panel de configuraciones.',
        'redirect' => '/public/html/access_config.html'
    ]);
    exit;
}
