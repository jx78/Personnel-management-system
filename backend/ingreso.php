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

function fechaValida(string $f): ?DateTime
{
    $d = DateTime::createFromFormat('Y-m-d', $f);
    return ($d && $d->format('Y-m-d') === $f) ? $d : null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responder(false, 'Método no permitido.');
}

$nombre         = trim($_POST['nombre'] ?? '');
$apellido       = trim($_POST['apellido'] ?? '');
$edad           = trim($_POST['edad'] ?? '');
$nivelacademico = trim($_POST['nivelacademico'] ?? '');
$demografia     = trim($_POST['demografia'] ?? '');
$puesto         = trim($_POST['puesto'] ?? '');
$codigoEmpleado = trim($_POST['Codigoempleado'] ?? '');
$supervisor     = trim($_POST['supervisor'] ?? '');
$fecha          = trim($_POST['fecha'] ?? '');
$termino        = trim($_POST['termino'] ?? '');
$observaciones  = trim($_POST['observaciones'] ?? '');

if (
    $nombre === '' || $apellido === '' || $edad === '' || $nivelacademico === '' ||
    $demografia === '' || $puesto === '' || $codigoEmpleado === '' ||
    $supervisor === '' || $fecha === ''
) {
    responder(false, 'Todos los campos obligatorios deben completarse.');
}

if (!preg_match('/^[A-Za-z0-9\-_]{1,20}$/', $codigoEmpleado)) {
    responder(false, 'El código de empleado debe tener entre 1 y 20 caracteres (letras, números o guiones).');
}

if (!ctype_digit($edad) || (int)$edad < 14 || (int)$edad > 100) {
    responder(false, 'La edad no es válida.');
}

$fechaObj = fechaValida($fecha);
if (!$fechaObj) {
    responder(false, 'La fecha de entrada no es válida.');
}

if ($termino === '') {
    $termino = (clone $fechaObj)->modify('+89 days')->format('Y-m-d');
} else {
    $terminoObj = fechaValida($termino);
    if (!$terminoObj) {
        responder(false, 'La fecha de término de contrato no es válida.');
    }
    if ($terminoObj < $fechaObj) {
        responder(false, 'El término de contrato no puede ser anterior a la fecha de entrada.');
    }
}

try {
    $stmt = $pdo->prepare('SELECT id FROM empleados WHERE codigo_empleado = :codigo LIMIT 1');
    $stmt->execute([':codigo' => $codigoEmpleado]);

    if ($stmt->fetch()) {
        responder(false, 'Ya existe un empleado registrado con ese código.');
    }

    $sql = 'INSERT INTO empleados
                (nombre, apellido, edad, nivel_academico, demografia, puesto,
                 codigo_empleado, supervisor, fecha_entrada, termino_contrato, observaciones, estado)
            VALUES
                (:nombre, :apellido, :edad, :nivelacademico, :demografia, :puesto,
                 :codigo, :supervisor, :fecha, :termino, :observaciones, :estado)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nombre'         => $nombre,
        ':apellido'       => $apellido,
        ':edad'           => (int)$edad,
        ':nivelacademico' => $nivelacademico,
        ':demografia'     => $demografia,
        ':puesto'         => $puesto,
        ':codigo'         => $codigoEmpleado,
        ':supervisor'     => $supervisor,
        ':fecha'          => $fecha,
        ':termino'        => $termino,
        ':observaciones'  => $observaciones !== '' ? $observaciones : null,
        ':estado'         => 'en_evaluacion',
    ]);

    $nuevoId = (int)$pdo->lastInsertId();
    crearEvaluacionesPorDefecto($pdo, $nuevoId, $fecha, $termino);

    responder(true, 'Empleado registrado correctamente. Se programaron automáticamente sus 3 evaluaciones periódicas.');

} catch (\PDOException $e) {
    if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE')) {
        responder(false, 'Ya existe un empleado registrado con ese código.');
    }
    responder(false, 'Error al guardar los datos: ' . $e->getMessage());
}
