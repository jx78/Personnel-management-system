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

if (empty($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    http_response_code(403);
    echo json_encode([
        'exito'    => false,
        'mensaje'  => 'Debes iniciar sesión con tu contraseña de acceso al sistema antes de entrar a configuraciones.',
        'redirect' => '/public/html/access.html',
    ]);
    exit;
}

$segundosBloqueo = verificarBloqueoAcceso('config');
if ($segundosBloqueo !== null) {
    http_response_code(429);
    $minutos = max(1, (int)ceil($segundosBloqueo / 60));
    echo json_encode([
        'exito'              => false,
        'mensaje'            => "Demasiados intentos fallidos. Panel de configuraciones bloqueado temporalmente. Intenta de nuevo en {$minutos} minuto(s) ({$segundosBloqueo} s).",
        'bloqueado'          => true,
        'segundos_restantes' => $segundosBloqueo,
    ]);
    exit;
}

$password = trim($_POST['Password'] ?? $_POST['password'] ?? '');

if ($password === '') {
    echo json_encode(['exito' => false, 'mensaje' => 'Por favor ingresa la contraseña de configuración.']);
    exit;
}

$valido = false;
try {
    $stmt = $pdo->prepare(
        "SELECT password_hash FROM usuarios WHERE usuario = 'config' LIMIT 1"
    );
    $stmt->execute();
    $hash = $stmt->fetchColumn();

    if ($hash !== false) {
        if (password_verify($password, (string)$hash)) {
            $valido = true;
        }
    } else {
        if ($password === 'ana3') {
            $valido = true;
            try {
                $nuevoHash = password_hash('ana3', PASSWORD_DEFAULT);
                $ins = $pdo->prepare(
                    "INSERT INTO usuarios (usuario, password_hash, rol) VALUES ('config', :hash, 'admin')"
                );
                $ins->execute([':hash' => $nuevoHash]);
            } catch (\Throwable $e) { }
        }
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'exito'   => false,
        'mensaje' => 'Error interno al verificar credenciales de configuración: ' . $e->getMessage(),
    ]);
    exit;
}

if ($valido) {
    limpiarIntentosFallidos('config');

    session_regenerate_id(true);
    $_SESSION['autenticado']        = true;
    $_SESSION['config_autenticado'] = true;
    if (empty($_SESSION['usuario'])) {
        $_SESSION['usuario'] = 'admin';
    }
    $_SESSION['login_time']         = time();
    $_SESSION['ultimo_acceso']      = time();

    $esFabrica = ($password === 'ana3');

    echo json_encode([
        'exito'         => true,
        'mensaje'       => 'Acceso a configuraciones concedido.',
        'redirect'      => '/public/html/configuraciones.html',
        'clave_fabrica' => $esFabrica,
    ]);
} else {
    $infoFallo = registrarIntentoFallido('config');
    http_response_code($infoFallo['bloqueado'] ? 429 : 401);

    if ($infoFallo['bloqueado']) {
        $msg = "Demasiados intentos fallidos (5). Panel de configuraciones bloqueado durante 5 minutos.";
    } else {
        $restantes = RATE_LIMIT_MAX_INTENTOS - $infoFallo['intentos'];
        $msg = "Contraseña de configuración incorrecta. Intentos restantes antes de bloqueo temporal: {$restantes}.";
    }

    echo json_encode([
        'exito'              => false,
        'mensaje'            => $msg,
        'intentos'           => $infoFallo['intentos'],
        'bloqueado'          => $infoFallo['bloqueado'],
        'segundos_restantes' => $infoFallo['segundos_restantes'],
    ]);
}
