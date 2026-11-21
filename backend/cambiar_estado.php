<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth_required.php';
require_once __DIR__ . '/db.php';

function responder(bool $exito, string $mensaje): void
{
    echo json_encode(['exito' => $exito, 'mensaje' => $mensaje]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responder(false, 'Método no permitido.');
}

$empleadoId   = isset($_POST['empleado_id']) ? (int)$_POST['empleado_id'] : 0;
$nuevoEstado  = trim($_POST['nuevo_estado'] ?? '');
$motivo       = trim($_POST['motivo'] ?? '');

$estadosPermitidos = ['en_evaluacion', 'finalizado', 'interrumpido'];

if ($empleadoId <= 0 || !in_array($nuevoEstado, $estadosPermitidos, true)) {
    responder(false, 'Parámetros inválidos.');
}

try {
    $stmt = $pdo->prepare("SELECT id, nombre, apellido, observaciones FROM empleados WHERE id = :id");
    $stmt->execute([':id' => $empleadoId]);
    $emp = $stmt->fetch();

    if (!$emp) {
        responder(false, 'El empleado no existe.');
    }

    $observacionesActuales = $emp['observaciones'] ?? '';
    if ($motivo !== '') {
        $fechaHoy = date('d/m/Y');
        $notaEstado = "[Cambio a '{$nuevoEstado}' el {$fechaHoy}: {$motivo}]";
        $observacionesActuales = $observacionesActuales !== ''
            ? $observacionesActuales . "\n" . $notaEstado
            : $notaEstado;
    }

    $update = $pdo->prepare("
        UPDATE empleados 
        SET estado = :nuevoEstado,
            observaciones = :obs
        WHERE id = :id
    ");

    $update->execute([
        ':nuevoEstado' => $nuevoEstado,
        ':obs'         => $observacionesActuales !== '' ? $observacionesActuales : null,
        ':id'          => $empleadoId
    ]);

    $etiquetas = [
        'en_evaluacion' => 'En Evaluación',
        'finalizado'    => 'Finalizado con Éxito',
        'interrumpido'  => 'No Finalizado / Interrumpido'
    ];

    $nombreCompleto = trim($emp['nombre'] . ' ' . $emp['apellido']);
    responder(true, "El estado de {$nombreCompleto} ha sido actualizado a '{$etiquetas[$nuevoEstado]}'.");

} catch (\PDOException $e) {
    responder(false, 'Error al actualizar el estado: ' . $e->getMessage());
}
