<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth_required.php';
require_once __DIR__ . '/db.php';

function responder(bool $exito, string $mensaje, array $extra = []): void
{
    echo json_encode(array_merge(['exito' => $exito, 'mensaje' => $mensaje], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responder(false, 'Método no permitido.');
}

$empleadoId       = isset($_POST['empleado_id']) ? (int)$_POST['empleado_id'] : 0;
$numeroEvaluacion = isset($_POST['numero_evaluacion']) ? (int)$_POST['numero_evaluacion'] : 0;
$fechaRealizada   = trim($_POST['fecha_realizada'] ?? '');
$calificacionRaw  = trim($_POST['calificacion'] ?? '');
$observaciones    = trim($_POST['observaciones'] ?? '');

if ($empleadoId <= 0 || !in_array($numeroEvaluacion, [1, 2, 3], true)) {
    responder(false, 'Parámetros de empleado o evaluación inválidos.');
}

$calificacion = null;
if ($calificacionRaw !== '') {
    if (!is_numeric($calificacionRaw)) {
        responder(false, 'La calificación debe ser un valor numérico.');
    }
    $califFloat = (float)$calificacionRaw;
    if ($califFloat < 0 || $califFloat > 100) {
        responder(false, 'La calificación debe estar entre 0 y 100.');
    }
    $calificacion = $califFloat;
}

if ($fechaRealizada !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $fechaRealizada);
    if (!$d || $d->format('Y-m-d') !== $fechaRealizada) {
        responder(false, 'La fecha realizada no tiene un formato válido (AAAA-MM-DD).');
    }
} else {
    $fechaRealizada = null;
}

try {
    $stmtCheck = $pdo->prepare("SELECT id FROM evaluaciones WHERE empleado_id = :empId AND numero_evaluacion = :num");
    $stmtCheck->execute([':empId' => $empleadoId, ':num' => $numeroEvaluacion]);
    $evalId = $stmtCheck->fetchColumn();

    if (!$evalId) {
        $stmtEmp = $pdo->prepare("SELECT fecha_entrada, termino_contrato FROM empleados WHERE id = :id");
        $stmtEmp->execute([':id' => $empleadoId]);
        $emp = $stmtEmp->fetch();
        if (!$emp) {
            responder(false, 'El empleado especificado no existe.');
        }

        require_once __DIR__ . '/inicializar_bd.php';
        crearEvaluacionesPorDefecto($pdo, $empleadoId, $emp['fecha_entrada'], $emp['termino_contrato']);
    }

    $stmt = $pdo->prepare("
        UPDATE evaluaciones 
        SET fecha_realizada = :fechaReal,
            calificacion = :calif,
            observaciones = :obs
        WHERE empleado_id = :empId AND numero_evaluacion = :num
    ");

    $stmt->execute([
        ':fechaReal' => $fechaRealizada,
        ':calif'     => $calificacion,
        ':obs'       => $observaciones !== '' ? $observaciones : null,
        ':empId'     => $empleadoId,
        ':num'       => $numeroEvaluacion
    ]);

    responder(true, "Evaluación {$numeroEvaluacion} actualizada correctamente.");

} catch (\PDOException $e) {
    responder(false, 'Error al guardar la evaluación: ' . $e->getMessage());
}
