<?php
declare(strict_types=1);

require_once __DIR__ . '/storage.php';

const RATE_LIMIT_MAX_INTENTOS = 5;
const RATE_LIMIT_BLOQUEO_SEGUNDOS = 300;

function obtenerRutaIntentosLogin(): string
{
    return obtenerDirectorioStorage() . DIRECTORY_SEPARATOR . 'intentos_login.json';
}

function obtenerIpCliente(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if ($ip === '::1') {
        $ip = '127.0.0.1';
    }
    return preg_replace('/[^0-9a-fA-F:\.]/', '', (string)$ip);
}

function cargarRegistroIntentos(): array
{
    $ruta = obtenerRutaIntentosLogin();
    if (!file_exists($ruta)) {
        return [];
    }
    $contenido = @file_get_contents($ruta);
    if ($contenido === false || $contenido === '') {
        return [];
    }
    $datos = json_decode($contenido, true);
    return is_array($datos) ? $datos : [];
}

function guardarRegistroIntentos(array $registro): void
{
    $ruta = obtenerRutaIntentosLogin();
    $dir = dirname($ruta);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($ruta, json_encode($registro, JSON_PRETTY_PRINT), LOCK_EX);
}

function purgarIntentosObsoletos(array &$registro): void
{
    $ahora = time();
    foreach ($registro as $clave => $item) {
        $bloqueadoHasta = $item['bloqueado_hasta'] ?? 0;
        $ultimoIntento  = $item['ultimo_intento'] ?? 0;
        if ($bloqueadoHasta < $ahora && ($ahora - $ultimoIntento) > 3600) {
            unset($registro[$clave]);
        }
    }
}

function verificarBloqueoAcceso(string $accion = 'login'): ?int
{
    $ip = obtenerIpCliente();
    $clave = $ip . '_' . $accion;
    $registro = cargarRegistroIntentos();

    if (!isset($registro[$clave])) {
        return null;
    }

    $item = $registro[$clave];
    $bloqueadoHasta = (int)($item['bloqueado_hasta'] ?? 0);
    $ahora = time();

    if ($bloqueadoHasta > $ahora) {
        return $bloqueadoHasta - $ahora;
    }

    return null;
}

function registrarIntentoFallido(string $accion = 'login'): array
{
    $ip = obtenerIpCliente();
    $clave = $ip . '_' . $accion;
    $registro = cargarRegistroIntentos();
    $ahora = time();

    purgarIntentosObsoletos($registro);

    $intentos = (int)($registro[$clave]['intentos'] ?? 0) + 1;
    $bloqueado = false;
    $segundosRestantes = 0;

    if ($intentos >= RATE_LIMIT_MAX_INTENTOS) {
        $bloqueado = true;
        $bloqueadoHasta = $ahora + RATE_LIMIT_BLOQUEO_SEGUNDOS;
        $segundosRestantes = RATE_LIMIT_BLOQUEO_SEGUNDOS;
        $registro[$clave] = [
            'ip'                => $ip,
            'accion'            => $accion,
            'intentos'          => $intentos,
            'ultimo_intento'    => $ahora,
            'bloqueado_hasta'   => $bloqueadoHasta,
        ];
    } else {
        $registro[$clave] = [
            'ip'                => $ip,
            'accion'            => $accion,
            'intentos'          => $intentos,
            'ultimo_intento'    => $ahora,
            'bloqueado_hasta'   => 0,
        ];
    }

    guardarRegistroIntentos($registro);

    return [
        'intentos'           => $intentos,
        'maximo'             => RATE_LIMIT_MAX_INTENTOS,
        'bloqueado'          => $bloqueado,
        'segundos_restantes' => $segundosRestantes,
    ];
}

function limpiarIntentosFallidos(string $accion = 'login'): void
{
    $ip = obtenerIpCliente();
    $clave = $ip . '_' . $accion;
    $registro = cargarRegistroIntentos();

    if (isset($registro[$clave])) {
        unset($registro[$clave]);
        guardarRegistroIntentos($registro);
    }
}
