<?php
declare(strict_types=1);

function asegurarEvaluacionesEnEvaluacion(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT e.id, e.fecha_entrada, e.termino_contrato, COUNT(ev.id) AS total_evals
        FROM empleados e
        LEFT JOIN evaluaciones ev ON ev.empleado_id = e.id
        WHERE e.estado = 'en_evaluacion'
        GROUP BY e.id, e.fecha_entrada, e.termino_contrato
        HAVING total_evals < 3
    ");
    $faltantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($faltantes)) {
        $stmtNum = $pdo->prepare("SELECT numero_evaluacion FROM evaluaciones WHERE empleado_id = ?");
        foreach ($faltantes as $f) {
            $stmtNum->execute([(int)$f['id']]);
            $nums = $stmtNum->fetchAll(PDO::FETCH_COLUMN);
            completarEvaluacionesFaltantes($pdo, (int)$f['id'], $f['fecha_entrada'], $f['termino_contrato'], $nums);
        }
    }
}

function calcularDetalleProximaEvaluacion(array $evaluaciones, DateTimeInterface $hoy): ?array
{
    $etiquetas = [
        1 => '30 días',
        2 => '60 días',
        3 => '90 días',
    ];

    foreach ($evaluaciones as $ev) {
        $calif = $ev['calificacion'] ?? null;
        $fReal = $ev['fecha_realizada'] ?? null;
        $pendiente = ($calif === null || $calif === '') && empty($fReal);

        if ($pendiente && !empty($ev['fecha_esperada'])) {
            $num = (int)$ev['numero_evaluacion'];
            $fEsperada = new DateTime($ev['fecha_esperada']);
            $diff = (int)$hoy->diff($fEsperada)->format('%r%a');

            $estado = 'en_tiempo';
            if ($diff < 0) {
                $estado = 'vencida';
            } elseif ($diff === 0) {
                $estado = 'hoy';
            } elseif ($diff <= 7) {
                $estado = 'urgente';
            } elseif ($diff <= 15) {
                $estado = 'proxima';
            }

            return [
                'id'             => (int)($ev['id'] ?? 0),
                'numero'         => $num,
                'etiqueta'       => $etiquetas[$num] ?? "Eval {$num}",
                'fecha_esperada' => $ev['fecha_esperada'],
                'dias_restantes' => $diff,
                'estado'         => $estado,
                'vencida'        => $diff < 0,
                'es_hoy'         => $diff === 0,
                'urgente'        => $diff >= 0 && $diff <= 7,
                'dias_vencida'   => $diff < 0 ? abs($diff) : 0,
            ];
        }
    }

    return null;
}

function buscarPorEstado(
    PDO $pdo,
    string $estado,
    string $nombre = "",
    string $codigoEmpleado = "",
    int $pagina = 1,
    int $porPagina = 10
): array {
    if ($estado === 'en_evaluacion') {
        asegurarEvaluacionesEnEvaluacion($pdo);
    }

    $where = ["estado = :estado"];
    $params = [":estado" => $estado];

    if ($nombre !== "") {
        $where[] = "(nombre LIKE :nombre1 OR apellido LIKE :nombre2)";
        $params[":nombre1"] = "%{$nombre}%";
        $params[":nombre2"] = "%{$nombre}%";
    }

    if ($codigoEmpleado !== "") {
        $where[] = "codigo_empleado = :codigo";
        $params[":codigo"] = $codigoEmpleado;
    }

    $whereSql = implode(" AND ", $where);

    $countSql = "SELECT COUNT(*) FROM empleados WHERE {$whereSql}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRegistros = (int)$countStmt->fetchColumn();

    if ($estado === 'en_evaluacion') {
        $proximaEvalSql = "(
            SELECT MIN(ev.fecha_esperada)
            FROM evaluaciones ev
            WHERE ev.empleado_id = empleados.id
              AND (ev.calificacion IS NULL OR ev.calificacion = '')
              AND ev.fecha_realizada IS NULL
        )";

        $sql = "SELECT id, nombre, apellido, edad, nivel_academico, demografia, puesto,
                       codigo_empleado, supervisor, fecha_entrada, termino_contrato,
                       observaciones, estado, fecha_registro,
                       {$proximaEvalSql} AS proxima_eval_fecha
                FROM empleados
                WHERE {$whereSql}
                ORDER BY
                    CASE WHEN ({$proximaEvalSql}) IS NULL THEN 1 ELSE 0 END,
                    ({$proximaEvalSql}) ASC,
                    fecha_entrada DESC,
                    id DESC";
    } else {
        $sql = "SELECT id, nombre, apellido, edad, nivel_academico, demografia, puesto,
                       codigo_empleado, supervisor, fecha_entrada, termino_contrato,
                       observaciones, estado, fecha_registro
                FROM empleados
                WHERE {$whereSql}
                ORDER BY fecha_entrada DESC, id DESC";
    }

    if ($porPagina > 0) {
        $pagina = max(1, $pagina);
        $offset = ($pagina - 1) * $porPagina;
        $sql .= " LIMIT {$porPagina} OFFSET {$offset}";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hoy = new DateTime('today');
    $stmtEvals = $pdo->prepare("
        SELECT id, numero_evaluacion, fecha_esperada, fecha_realizada, calificacion, observaciones
        FROM evaluaciones
        WHERE empleado_id = :empId
        ORDER BY numero_evaluacion ASC
    ");

    foreach ($empleados as &$emp) {
        $diasRestantes = null;
        $proximoVencer = false;
        $vencido = false;

        if (!empty($emp['termino_contrato'])) {
            $fTermino = new DateTime($emp['termino_contrato']);
            $diff = (int)$hoy->diff($fTermino)->format('%r%a');
            $diasRestantes = $diff;
            $proximoVencer = ($diff >= 0 && $diff <= 15);
            $vencido = ($diff < 0);
        }

        $emp['dias_restantes'] = $diasRestantes;
        $emp['proximo_a_vencer'] = $proximoVencer;
        $emp['vencido'] = $vencido;

        $stmtEvals->execute([':empId' => $emp['id']]);
        $evals = $stmtEvals->fetchAll(PDO::FETCH_ASSOC);

        if (count($evals) < 3) {
            $numerosExistentes = array_column($evals, 'numero_evaluacion');
            completarEvaluacionesFaltantes($pdo, (int)$emp['id'], $emp['fecha_entrada'], $emp['termino_contrato'], $numerosExistentes);
            $stmtEvals->execute([':empId' => $emp['id']]);
            $evals = $stmtEvals->fetchAll(PDO::FETCH_ASSOC);
        }

        $emp['evaluaciones'] = $evals;

        $proxEval = calcularDetalleProximaEvaluacion($evals, $hoy);
        $emp['proxima_evaluacion'] = $proxEval;
        $emp['proxima_eval_numero'] = $proxEval['numero'] ?? null;
        $emp['proxima_eval_etiqueta'] = $proxEval['etiqueta'] ?? null;
        $emp['proxima_eval_dias'] = $proxEval['dias_restantes'] ?? null;

        $calificaciones = [];
        foreach ($evals as $ev) {
            if ($ev['calificacion'] !== null && $ev['calificacion'] !== '') {
                $calificaciones[] = (float)$ev['calificacion'];
            }
        }
        $emp['promedio_calificaciones'] = count($calificaciones) > 0
            ? round(array_sum($calificaciones) / count($calificaciones), 1)
            : null;
    }
    unset($emp);

    $totalPaginas = $porPagina > 0 ? (int)ceil($totalRegistros / $porPagina) : 1;

    return [
        'datos' => $empleados,
        'paginacion' => [
            'total'          => $totalRegistros,
            'pagina'         => $pagina,
            'por_pagina'     => $porPagina,
            'total_paginas'  => max(1, $totalPaginas),
        ]
    ];
}
