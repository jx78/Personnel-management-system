<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rate_limiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

$segundosBloqueo = verificarBloqueoAcceso('login');
if ($segundosBloqueo !== null) {
    http_response_code(429);
    $minutos = max(1, (int)ceil($segundosBloqueo / 60));
    echo json_encode([
        'exito'              => false,
        'mensaje'            => "Demasiados intentos fallidos. Acceso bloqueado temporalmente por seguridad. Intenta de nuevo en {$minutos} minuto(s) ({$segundosBloqueo} s).",
        'bloqueado'          => true,
        'segundos_restantes' => $segundosBloqueo,
    ]);
    exit;
}

$password = trim($_POST['Password'] ?? $_POST['password'] ?? '');

if ($password === '') {
    echo json_encode(['exito' => false, 'mensaje' => 'Por favor ingresa una contraseña.']);
    exit;
}

$valido = false;
try {
    $stmt = $pdo->prepare(
        "SELECT password_hash FROM usuarios WHERE usuario = 'admin' LIMIT 1"
    );
    $stmt->execute();
    $hash = $stmt->fetchColumn();

    if ($hash !== false && password_verify($password, (string)$hash)) {
        $valido = true;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'exito'   => false,
        'mensaje' => 'Error interno al verificar credenciales.',
    ]);
    exit;
}

if ($valido) {
    limpiarIntentosFallidos('login');

    session_regenerate_id(true);
    $_SESSION['autenticado']   = true;
    $_SESSION['usuario']       = 'admin';
    $_SESSION['login_time']    = time();
    $_SESSION['ultimo_acceso'] = time();

    $esFabrica = ($password === 'ana2');

    echo json_encode([
        'exito'         => true,
        'mensaje'       => 'Acceso autorizado.',
        'redirect'      => '/public/html/index.html',
        'clave_fabrica' => $esFabrica,
    ]);
} else {
    $infoFallo = registrarIntentoFallido('login');
    http_response_code($infoFallo['bloqueado'] ? 429 : 401);

    if ($infoFallo['bloqueado']) {
        $msg = "Demasiados intentos fallidos (5). Acceso bloqueado durante 5 minutos por seguridad.";
    } else {
        $restantes = RATE_LIMIT_MAX_INTENTOS - $infoFallo['intentos'];
        $msg = "Contraseña incorrecta. Intentos restantes antes de bloqueo temporal: {$restantes}.";
    }

    echo json_encode([
        'exito'              => false,
        'mensaje'            => $msg,
        'intentos'           => $infoFallo['intentos'],
        'bloqueado'          => $infoFallo['bloqueado'],
        'segundos_restantes' => $infoFallo['segundos_restantes'],
    ]);
}
