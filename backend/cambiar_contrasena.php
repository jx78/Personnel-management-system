<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config_auth_required.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

$tipo             = trim($_POST['tipo'] ?? '');
$passActual       = $_POST['pass_actual'] ?? '';
$passNueva        = $_POST['pass_nueva'] ?? '';
$passConfirmacion = $_POST['pass_confirmacion'] ?? '';

if (!in_array($tipo, ['acceso', 'configuracion'], true)) {
    echo json_encode(['exito' => false, 'mensaje' => 'Tipo de contraseña no reconocido.']);
    exit;
}

if ($passNueva === '') {
    echo json_encode(['exito' => false, 'mensaje' => 'La nueva contraseña no puede estar vacía.']);
    exit;
}

if (strlen($passNueva) < 6) {
    echo json_encode(['exito' => false, 'mensaje' => 'La nueva contraseña debe tener al menos 6 caracteres.']);
    exit;
}

$clavesProhibidas = ['ana1', 'ana2', 'ana3', 'admin', '123456', '12345678', 'password', 'sistema', 'root', 'qwerty'];
if (in_array(strtolower($passNueva), $clavesProhibidas, true)) {
    echo json_encode(['exito' => false, 'mensaje' => 'Por seguridad, no se permite utilizar contraseñas de fábrica o comunes. Elige una clave más robusta.']);
    exit;
}

if ($passNueva !== $passConfirmacion) {
    echo json_encode(['exito' => false, 'mensaje' => 'La confirmación no coincide con la nueva contraseña.']);
    exit;
}

if ($tipo === 'acceso') {
    try {
        $stmt = $pdo->prepare("SELECT password_hash FROM usuarios WHERE usuario = 'admin' LIMIT 1");
        $stmt->execute();
        $hash = $stmt->fetchColumn();

        if ($hash === false) {
            echo json_encode(['exito' => false, 'mensaje' => 'No se encontró el usuario administrador en la base de datos.']);
            exit;
        }

        if (!password_verify($passActual, (string)$hash)) {
            http_response_code(403);
            echo json_encode(['exito' => false, 'mensaje' => 'La contraseña actual de acceso es incorrecta.']);
            exit;
        }

        $nuevoHash = password_hash($passNueva, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE usuarios SET password_hash = :hash WHERE usuario = 'admin'");
        $upd->execute([':hash' => $nuevoHash]);

        echo json_encode(['exito' => true, 'mensaje' => 'Contraseña de acceso actualizada correctamente.']);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['exito' => false, 'mensaje' => 'Error al actualizar la contraseña: ' . $e->getMessage()]);
    }
    exit;
}

if ($tipo === 'configuracion') {
    try {
        $stmt = $pdo->prepare("SELECT password_hash FROM usuarios WHERE usuario = 'config' LIMIT 1");
        $stmt->execute();
        $hash = $stmt->fetchColumn();

        if ($hash === false) {
            if ($passActual !== 'ana3') {
                http_response_code(403);
                echo json_encode(['exito' => false, 'mensaje' => 'La contraseña actual de configuración es incorrecta.']);
                exit;
            }
            $nuevoHash = password_hash($passNueva, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO usuarios (usuario, password_hash, rol) VALUES ('config', :hash, 'admin')");
            $ins->execute([':hash' => $nuevoHash]);

            echo json_encode(['exito' => true, 'mensaje' => 'Contraseña de configuración actualizada correctamente.']);
            exit;
        }

        if (!password_verify($passActual, (string)$hash)) {
            http_response_code(403);
            echo json_encode(['exito' => false, 'mensaje' => 'La contraseña actual de configuración es incorrecta.']);
            exit;
        }

        $nuevoHash = password_hash($passNueva, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE usuarios SET password_hash = :hash WHERE usuario = 'config'");
        $upd->execute([':hash' => $nuevoHash]);

        echo json_encode(['exito' => true, 'mensaje' => 'Contraseña de configuración actualizada correctamente.']);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['exito' => false, 'mensaje' => 'Error al actualizar la contraseña: ' . $e->getMessage()]);
    }
    exit;
}


