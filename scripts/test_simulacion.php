<?php
declare(strict_types=1);

$rootDir = dirname(__DIR__);
chdir($rootDir);

$phpBin = $rootDir . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe';
$phpIni = $rootDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'php.ini';
$extDir = $rootDir . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'ext';
$worker = $rootDir . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'sim_worker.php';

$persistentSession = [];

function ejecutarEndpoint(string $script, array $params = [], string $method = 'POST'): array
{
    global $phpBin, $phpIni, $extDir, $worker, $rootDir, $persistentSession;

    $tmp = tempnam(sys_get_temp_dir(), 'sim_');
    $payload = [
        'script'  => $rootDir . DIRECTORY_SEPARATOR . $script,
        'method'  => $method,
        'post'    => ($method === 'POST') ? $params : [],
        'get'     => ($method === 'GET') ? $params : [],
        'session' => $persistentSession
    ];
    file_put_contents($tmp, json_encode($payload));

    $cmd = sprintf(
        '"%s" -c "%s" -d extension_dir="%s" "%s" "%s"',
        $phpBin,
        $phpIni,
        $extDir,
        $worker,
        $tmp
    );

    $salida = [];
    $retorno = 0;
    exec($cmd, $salida, $retorno);

    $sessFile = $tmp . '.sess';
    if (file_exists($sessFile)) {
        $persistentSession = json_decode(file_get_contents($sessFile), true) ?: [];
        @unlink($sessFile);
    }
    @unlink($tmp);

    $raw = implode("\n", $salida);
    $data = json_decode($raw, true);

    return [
        'retorno' => $retorno,
        'raw'     => $raw,
        'json'    => $data,
        'session' => $persistentSession
    ];
}

echo "\n" . str_repeat('=', 68) . "\n";
echo "   EVALUACION DEL SISTEMA Y SIMULACION INTEGRAL DE USUARIO\n";
echo "   Sistema de Gestión de Personal y Evaluaciones (v2.0)\n";
echo str_repeat('=', 68) . "\n\n";

$pruebas = 0;
$errores = 0;

function reportar(string $num, string $titulo, bool $ok, string $detalle = ''): void {
    global $pruebas, $errores;
    $pruebas++;
    if ($ok) {
        echo "  [OK]  Paso $num: $titulo\n";
        if ($detalle !== '') echo "        -> $detalle\n";
    } else {
        $errores++;
        echo "  [ERR] Paso $num: $titulo\n";
        if ($detalle !== '') echo "        -> ERROR: $detalle\n";
    }
}

// ── PASO 1: Comprobación de Interfaz Estática ──────────────────────────────────
$accessHtml = file_exists('public/html/access.html');
$indexHtml  = file_exists('public/html/index.html');
$css        = file_exists('public/css/style.css');
$js         = file_exists('public/js/script.js');
reportar("1/8", "Estructura de interfaz web (HTML5/CSS3/JS)", 
    $accessHtml && $indexHtml && $css && $js,
    "access.html, index.html, style.css y script.js localizados y listos.");

// ── PASO 2: Intento de Acceso con Contraseña INCORRECTA ────────────────────────
$res1 = ejecutarEndpoint('backend/login.php', ['Password' => 'clave_falsa_999'], 'POST');
$bloqueoOk = ($res1['json'] !== null && $res1['json']['exito'] === false);
reportar("2/8", "Protección contra contraseñas incorrectas", 
    $bloqueoOk,
    "Mensaje del backend: '{$res1['json']['mensaje']}'");

// ── PASO 3: Acceso con Contraseña Correcta de Administrador ('ana2') ───────────
$res2 = ejecutarEndpoint('backend/login.php', ['Password' => 'ana2'], 'POST');
$loginOk = ($res2['json'] !== null && $res2['json']['exito'] === true && !empty($res2['session']['autenticado']));
reportar("3/8", "Autenticación de usuario Administrador", 
    $loginOk,
    "Acceso concedido. Redirigiendo a: {$res2['json']['redirect']} | Clave de fábrica: " . ($res2['json']['clave_fabrica'] ? 'Sí' : 'No'));

// ── PASO 4: Verificación de Sesión Activa (auth_check.php) ──────────────────────
$res3 = ejecutarEndpoint('backend/auth_check.php', [], 'GET');
$authOk = ($res3['json'] !== null && $res3['json']['autenticado'] === true && $res3['json']['usuario'] === 'admin');
reportar("4/8", "Validación de sesión administrativa (auth_check.php)", 
    $authOk,
    "Usuario en sesión: '{$res3['json']['usuario']}' | Alerta de clave inicial: " . ($res3['json']['claves_fabrica']['alerta'] ? 'Detectada (recordatorio de cambio)' : 'No'));

// ── PASO 5: Registro de Empleado (ingreso.php) ──────────────────────────────────
$codigoEmp = 'EMP-' . random_int(1000, 9999);
$datosEmpleado = [
    'nombre'          => 'Mariana',
    'apellido'        => 'Villalobos Ruiz',
    'edad'            => '30',
    'nivelacademico'  => 'Licenciatura',
    'demografia'      => 'Urbana',
    'puesto'          => 'Coordinadora de Calidad',
    'Codigoempleado'  => $codigoEmp,
    'supervisor'      => 'Ing. Javier Morales',
    'fecha'           => '2026-11-01',
    'termino'         => '2027-02-01',
    'observaciones'   => 'Alta de prueba en simulación de usuario.'
];
$res4 = ejecutarEndpoint('backend/ingreso.php', $datosEmpleado, 'POST');
$ingresoOk = ($res4['json'] !== null && $res4['json']['exito'] === true);
reportar("5/8", "Registro de nuevo colaborador (ingreso.php)", 
    $ingresoOk,
    "Respuesta del backend: '{$res4['json']['mensaje']}' (Código: $codigoEmp)");

// ── PASO 6: Consulta de Empleados en Evaluación y Evaluaciones Creadas ────────
$resEvalList = ejecutarEndpoint('backend/en_evaluacion.php', [], 'GET');
$listaOk = ($resEvalList['json'] !== null && $resEvalList['json']['exito'] === true);
$empEncontrado = null;
if ($listaOk && !empty($resEvalList['json']['datos'])) {
    foreach ($resEvalList['json']['datos'] as $item) {
        if ($item['codigo_empleado'] === $codigoEmp) {
            $empEncontrado = $item;
            break;
        }
    }
}

$evalsOk = ($empEncontrado !== null && !empty($empEncontrado['evaluaciones']) && count($empEncontrado['evaluaciones']) === 3);
$fechasStr = '';
if ($empEncontrado && !empty($empEncontrado['evaluaciones'])) {
    $fechasStr = implode(', ', array_map(function($ev) {
        return "Eval #" . $ev['numero_evaluacion'] . " (" . $ev['fecha_esperada'] . ")";
    }, $empEncontrado['evaluaciones']));
}

$empId = (int)($empEncontrado['id'] ?? 0);
reportar("6/8", "Consulta y cálculo automático de evaluaciones periódicas", 
    $evalsOk,
    "Empleado localizado en lista activa. 3 evaluaciones programadas: $fechasStr");

// ── PASO 7: Calificación de Evaluación (+30 días) ──────────────────────────────
$datosCalificacion = [
    'empleado_id'       => (string)$empId,
    'numero_evaluacion' => '1',
    'calificacion'      => '96.50',
    'fecha_realizada'   => '2026-12-01',
    'observaciones'     => 'Excelente desempeño técnico y adaptación al equipo.'
];
$res5 = ejecutarEndpoint('backend/guardar_evaluacion.php', $datosCalificacion, 'POST');
$calificacionOk = ($res5['json'] !== null && $res5['json']['exito'] === true);
reportar("7/8", "Captura de evaluación periódica (guardar_evaluacion.php)", 
    $calificacionOk,
    "Evaluación #1 calificada con 96.50 / 100 y observaciones registradas.");

// ── PASO 8: Cierre de Sesión Seguro (logout.php) ────────────────────────────────
$res6 = ejecutarEndpoint('backend/logout.php', [], 'POST');
$logoutOk = ($res6['json'] !== null && $res6['json']['exito'] === true && empty($res6['session']['autenticado']));
reportar("8/8", "Cierre de sesión seguro y destrucción de sesión", 
    $logoutOk,
    "Sesión cerrada correctamente. Redirección a: {$res6['json']['redirect']}");

// Mantener sim_worker.php para futuras pruebas
// @unlink($worker);

echo "\n" . str_repeat('=', 68) . "\n";
echo "   DICTAMEN FINAL DE EVALUACION:\n";
if ($errores === 0) {
    echo "   [STATUS: EXCELENTE] El sistema pasó el 100% de las pruebas ($pruebas/$pruebas).\n";
    echo "   Autenticación, seguridad, base de datos SQLite y flujos de negocio\n";
    echo "   se encuentran completamente operativos y validados.\n";
} else {
    echo "   [STATUS: ATENCION] Se registraron $errores fallos en las pruebas.\n";
}
echo str_repeat('=', 68) . "\n\n";
