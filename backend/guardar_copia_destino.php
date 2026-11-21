<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config_auth_required.php';
require_once __DIR__ . '/storage.php';

$configPath = obtenerRutaConfigCopiaAutomatica();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cfg = [
        'habilitada'     => false,
        'ruta_destino'   => '',
        'ultimo_exito'   => null,
        'ultimo_archivo' => null,
    ];

    if (file_exists($configPath)) {
        $saved = json_decode((string)file_get_contents($configPath), true);
        if (is_array($saved)) {
            $cfg = array_merge($cfg, $saved);
        }
    }

    $esAccesible = false;
    if (!empty($cfg['ruta_destino'])) {
        $esAccesible = is_dir($cfg['ruta_destino']) && is_writable($cfg['ruta_destino']);
    }

    echo json_encode([
        'exito'        => true,
        'configuracion'=> $cfg,
        'accesible'    => $esAccesible,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $habilitada  = isset($_POST['habilitada']) && (
        $_POST['habilitada'] === '1' || $_POST['habilitada'] === 'true' || $_POST['habilitada'] === true
    );
    $rutaDestino = trim((string)($_POST['ruta_destino'] ?? ''));

    if ($habilitada && empty($rutaDestino)) {
        http_response_code(400);
        echo json_encode([
            'exito'   => false,
            'mensaje' => 'Debes indicar una ruta de carpeta válida para la copia externa.',
        ]);
        exit;
    }

    if ($habilitada) {
        if (!is_dir($rutaDestino)) {
            @mkdir($rutaDestino, 0755, true);
        }

        if (!is_dir($rutaDestino)) {
            http_response_code(400);
            echo json_encode([
                'exito'   => false,
                'mensaje' => 'La ruta especificada no existe o la unidad no está conectada.',
            ]);
            exit;
        }

        if (!is_writable($rutaDestino)) {
            http_response_code(400);
            echo json_encode([
                'exito'   => false,
                'mensaje' => 'La carpeta no tiene permisos de escritura o el almacenamiento está protegido.',
            ]);
            exit;
        }
    }

    $cfgActual = [];
    if (file_exists($configPath)) {
        $prev = json_decode((string)file_get_contents($configPath), true);
        if (is_array($prev)) {
            $cfgActual = $prev;
        }
    }

    $cfgActual['habilitada']   = $habilitada;
    $cfgActual['ruta_destino'] = $rutaDestino;

    $guardado = file_put_contents(
        $configPath,
        json_encode($cfgActual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );

    if ($guardado === false) {
        http_response_code(500);
        echo json_encode([
            'exito'   => false,
            'mensaje' => 'No se pudo guardar la configuración de copia automática.',
        ]);
        exit;
    }

    echo json_encode([
        'exito'   => true,
        'mensaje' => $habilitada 
            ? 'Copia automática configurada correctamente en: ' . $rutaDestino
            : 'Copia automática desactivada.',
        'configuracion' => $cfgActual,
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido.']);
