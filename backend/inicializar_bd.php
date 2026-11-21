<?php
declare(strict_types=1);

function asegurarTablasYDatos(PDO $pdo, string $driver): void
{
    static $inicializado = false;
    if ($inicializado) return;
    $inicializado = true;

    if ($driver === 'sqlite') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS empleados (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL,
                apellido TEXT NOT NULL,
                edad INTEGER NOT NULL,
                nivel_academico TEXT NOT NULL,
                demografia TEXT NOT NULL,
                puesto TEXT NOT NULL,
                codigo_empleado TEXT NOT NULL UNIQUE,
                supervisor TEXT NOT NULL,
                fecha_entrada DATE NOT NULL,
                termino_contrato DATE NOT NULL,
                observaciones TEXT,
                estado TEXT NOT NULL DEFAULT 'en_evaluacion',
                fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS evaluaciones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                empleado_id INTEGER NOT NULL,
                numero_evaluacion INTEGER NOT NULL,
                fecha_esperada DATE NOT NULL,
                fecha_realizada DATE,
                calificacion REAL,
                observaciones TEXT,
                FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
                UNIQUE(empleado_id, numero_evaluacion)
            );

            CREATE TABLE IF NOT EXISTS usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                rol TEXT NOT NULL DEFAULT 'admin',
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        try {
            $cols = $pdo->query("PRAGMA table_info(empleados)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                if ($col['name'] === 'codigo_empleado' && strtoupper((string)$col['type']) !== 'TEXT') {
                    $pdo->exec("
                        PRAGMA foreign_keys = OFF;
                        CREATE TABLE IF NOT EXISTS empleados_nueva (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            nombre TEXT NOT NULL,
                            apellido TEXT NOT NULL,
                            edad INTEGER NOT NULL,
                            nivel_academico TEXT NOT NULL,
                            demografia TEXT NOT NULL,
                            puesto TEXT NOT NULL,
                            codigo_empleado TEXT NOT NULL UNIQUE,
                            supervisor TEXT NOT NULL,
                            fecha_entrada DATE NOT NULL,
                            termino_contrato DATE NOT NULL,
                            observaciones TEXT,
                            estado TEXT NOT NULL DEFAULT 'en_evaluacion',
                            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        );
                        INSERT INTO empleados_nueva (id, nombre, apellido, edad, nivel_academico, demografia, puesto, codigo_empleado, supervisor, fecha_entrada, termino_contrato, observaciones, estado, fecha_registro)
                        SELECT id, nombre, apellido, edad, nivel_academico, demografia, puesto, CAST(codigo_empleado AS TEXT), supervisor, fecha_entrada, termino_contrato, observaciones, estado, fecha_registro
                        FROM empleados;
                        DROP TABLE empleados;
                        ALTER TABLE empleados_nueva RENAME TO empleados;
                        PRAGMA foreign_keys = ON;
                    ");
                    break;
                }
            }
        } catch (\Throwable $ign) { }
    } else {
        $tablasRequeridas = ['empleados', 'evaluaciones', 'usuarios'];
        $faltantes = [];
        foreach ($tablasRequeridas as $tabla) {
            $existe = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = '{$tabla}'"
            )->fetchColumn();
            if ((int)$existe === 0) {
                $faltantes[] = $tabla;
            }
        }

        if (!empty($faltantes)) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS empleados (
                        id               INT AUTO_INCREMENT PRIMARY KEY,
                        nombre           VARCHAR(100) NOT NULL,
                        apellido         VARCHAR(100) NOT NULL,
                        edad             TINYINT UNSIGNED NOT NULL,
                        nivel_academico  VARCHAR(100) NOT NULL,
                        demografia       VARCHAR(100) NOT NULL,
                        puesto           VARCHAR(100) NOT NULL,
                        codigo_empleado  VARCHAR(20) NOT NULL,
                        supervisor       VARCHAR(100) NOT NULL,
                        fecha_entrada    DATE NOT NULL,
                        termino_contrato DATE NOT NULL,
                        observaciones    TEXT NULL,
                        estado           ENUM('en_evaluacion','finalizado','interrumpido')
                                         NOT NULL DEFAULT 'en_evaluacion',
                        fecha_registro   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_codigo_empleado (codigo_empleado),
                        KEY idx_estado_registro (estado, fecha_registro)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                    CREATE TABLE IF NOT EXISTS evaluaciones (
                        id                INT AUTO_INCREMENT PRIMARY KEY,
                        empleado_id       INT NOT NULL,
                        numero_evaluacion TINYINT NOT NULL,
                        fecha_esperada    DATE NOT NULL,
                        fecha_realizada   DATE NULL,
                        calificacion      DECIMAL(5,2) NULL,
                        observaciones     TEXT NULL,
                        CONSTRAINT fk_evaluaciones_empleado FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
                        UNIQUE KEY uq_empleado_eval (empleado_id, numero_evaluacion)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                    CREATE TABLE IF NOT EXISTS usuarios (
                        id             INT AUTO_INCREMENT PRIMARY KEY,
                        usuario        VARCHAR(50)  NOT NULL UNIQUE,
                        password_hash  VARCHAR(255) NOT NULL,
                        rol            VARCHAR(20)  NOT NULL DEFAULT 'admin',
                        fecha_creacion TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                $faltantes = [];
                foreach ($tablasRequeridas as $tabla) {
                    $existe = $pdo->query(
                        "SELECT COUNT(*) FROM information_schema.tables
                         WHERE table_schema = DATABASE()
                           AND table_name = '{$tabla}'"
                    )->fetchColumn();
                    if ((int)$existe === 0) {
                        $faltantes[] = $tabla;
                    }
                }
            } catch (\PDOException $eCrear) { }
        }

        if (!empty($faltantes)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'exito'   => false,
                'mensaje' => 'La base de datos MySQL / MariaDB no está inicializada. Ejecuta database/schema.sql con un usuario administrador. Tablas faltantes: ' . implode(', ', $faltantes),
            ]);
            exit;
        }

        try {
            $tipoCol = $pdo->query("
                SELECT DATA_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'empleados'
                  AND COLUMN_NAME = 'codigo_empleado'
            ")->fetchColumn();
            if ($tipoCol && strtolower((string)$tipoCol) !== 'varchar') {
                $pdo->exec("ALTER TABLE empleados MODIFY codigo_empleado VARCHAR(20) NOT NULL;");
            }
        } catch (\Throwable $ign) { }
    }

    $config = require __DIR__ . '/config.php';
    if (!empty($config['sembrar_datos_demo'])) {
        $totalEmpleados = (int)$pdo->query("SELECT COUNT(*) FROM empleados")->fetchColumn();
        if ($totalEmpleados === 0) {
            sembrarDatosIniciales($pdo);
        }
    }

    try {
        $checkConfig = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = 'config'");
        $checkConfig->execute();
        if ((int)$checkConfig->fetchColumn() === 0) {
            $passConfigHash = password_hash('ana3', PASSWORD_DEFAULT);
            $pdo->prepare("
                INSERT INTO usuarios (usuario, password_hash, rol)
                VALUES ('config', :hash, 'admin')
            ")->execute([':hash' => $passConfigHash]);
        }
    } catch (\Throwable $e) { }

    try {
        $checkAdmin = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = 'admin'");
        $checkAdmin->execute();
        if ((int)$checkAdmin->fetchColumn() === 0) {
            $passAdminHash = password_hash('ana2', PASSWORD_DEFAULT);
            $pdo->prepare("
                INSERT INTO usuarios (usuario, password_hash, rol)
                VALUES ('admin', :hash, 'admin')
            ")->execute([':hash' => $passAdminHash]);
        }
    } catch (\Throwable $e) { }
}

function crearEvaluacionesPorDefecto(PDO $pdo, int $empleadoId, string $fechaEntrada, ?string $terminoContrato): void
{
    $fEntrada = new DateTime($fechaEntrada);
    $fEval3 = (!empty($terminoContrato))
        ? $terminoContrato
        : (clone $fEntrada)->modify('+89 days')->format('Y-m-d');

    $fEval1 = (clone $fEntrada)->modify('+30 days')->format('Y-m-d');
    $fEval2 = (clone $fEntrada)->modify('+60 days')->format('Y-m-d');

    $stmt = $pdo->prepare("
        INSERT INTO evaluaciones (empleado_id, numero_evaluacion, fecha_esperada)
        VALUES 
            (:id1, 1, :f1),
            (:id2, 2, :f2),
            (:id3, 3, :f3)
    ");
    $stmt->execute([
        ':id1' => $empleadoId,
        ':id2' => $empleadoId,
        ':id3' => $empleadoId,
        ':f1'  => $fEval1,
        ':f2'  => $fEval2,
        ':f3'  => $fEval3,
    ]);
}

function completarEvaluacionesFaltantes(PDO $pdo, int $empleadoId, string $fechaEntrada, ?string $terminoContrato, array $numerosExistentes): void
{
    $fEntrada = new DateTime($fechaEntrada);
    $fTermino = (!empty($terminoContrato))
        ? $terminoContrato
        : (clone $fEntrada)->modify('+89 days')->format('Y-m-d');

    $fechasPorNumero = [
        1 => (clone $fEntrada)->modify('+30 days')->format('Y-m-d'),
        2 => (clone $fEntrada)->modify('+60 days')->format('Y-m-d'),
        3 => $fTermino,
    ];

    $stmt = $pdo->prepare("
        INSERT INTO evaluaciones (empleado_id, numero_evaluacion, fecha_esperada)
        VALUES (:empId, :num, :fecha)
    ");

    $existentes = array_map('intval', $numerosExistentes);

    foreach ([1, 2, 3] as $num) {
        if (!in_array($num, $existentes, true)) {
            $stmt->execute([
                ':empId' => $empleadoId,
                ':num'   => $num,
                ':fecha' => $fechasPorNumero[$num],
            ]);
        }
    }
}

function sembrarDatosIniciales(PDO $pdo): void
{
    $hoy = new DateTime();

    $fEntrada1 = (clone $hoy)->modify('-84 days')->format('Y-m-d');
    $fTermino1 = (clone $hoy)->modify('+5 days')->format('Y-m-d');

    $fEntrada2 = (clone $hoy)->modify('-45 days')->format('Y-m-d');
    $fTermino2 = (clone $hoy)->modify('+44 days')->format('Y-m-d');

    $fEntrada3 = (clone $hoy)->modify('-10 days')->format('Y-m-d');
    $fTermino3 = (clone $hoy)->modify('+79 days')->format('Y-m-d');

    $fEntrada4 = (clone $hoy)->modify('-120 days')->format('Y-m-d');
    $fTermino4 = (clone $hoy)->modify('-31 days')->format('Y-m-d');

    $fEntrada5 = (clone $hoy)->modify('-90 days')->format('Y-m-d');
    $fTermino5 = (clone $hoy)->modify('-1 days')->format('Y-m-d');

    $empleados = [
        [
            'nombre' => 'Juan Carlos',
            'apellido' => 'Pérez Morales',
            'edad' => 28,
            'nivel_academico' => 'Preparatoria',
            'demografia' => 'CEDIS TEPIC',
            'puesto' => 'Chofer Repartidor',
            'codigo_empleado' => '10101',
            'supervisor' => 'Roberto Gómez Cruz',
            'fecha_entrada' => $fEntrada1,
            'termino_contrato' => $fTermino1,
            'observaciones' => 'Excelente desempeño, pendiente última evaluación previa a renovación.',
            'estado' => 'en_evaluacion'
        ],
        [
            'nombre' => 'María Elena',
            'apellido' => 'González López',
            'edad' => 24,
            'nivel_academico' => 'Licenciatura en Administración',
            'demografia' => 'PLANTA TEPIC',
            'puesto' => 'Operadora de Envasado',
            'codigo_empleado' => '10102',
            'supervisor' => 'Laura Sánchez Peña',
            'fecha_entrada' => $fEntrada2,
            'termino_contrato' => $fTermino2,
            'observaciones' => 'Buena adaptación al equipo de trabajo en planta.',
            'estado' => 'en_evaluacion'
        ],
        [
            'nombre' => 'Carlos Alberto',
            'apellido' => 'Ruiz Díaz',
            'edad' => 32,
            'nivel_academico' => 'Licenciatura en Contabilidad',
            'demografia' => 'OFICINA CORPORATIVA',
            'puesto' => 'Auxiliar Contable',
            'codigo_empleado' => '10103',
            'supervisor' => 'Martín Herrera Ruiz',
            'fecha_entrada' => $fEntrada3,
            'termino_contrato' => $fTermino3,
            'observaciones' => 'En período inicial de capacitación de sistemas internos.',
            'estado' => 'en_evaluacion'
        ],
        [
            'nombre' => 'Ana Sofía',
            'apellido' => 'Ramos Torres',
            'edad' => 29,
            'nivel_academico' => 'Ingeniería Química',
            'demografia' => 'PLANTA TEPIC',
            'puesto' => 'Inspectora de Calidad',
            'codigo_empleado' => '10201',
            'supervisor' => 'Laura Sánchez Peña',
            'fecha_entrada' => $fEntrada4,
            'termino_contrato' => $fTermino4,
            'observaciones' => 'Aprobó con honores todas las evaluaciones. Contrato definitivo formalizado.',
            'estado' => 'finalizado'
        ],
        [
            'nombre' => 'Pedro Antonio',
            'apellido' => 'Morales Vega',
            'edad' => 35,
            'nivel_academico' => 'Secundaria',
            'demografia' => 'CEDIS TEPIC',
            'puesto' => 'Almacenista',
            'codigo_empleado' => '10301',
            'supervisor' => 'Roberto Gómez Cruz',
            'fecha_entrada' => $fEntrada5,
            'termino_contrato' => $fTermino5,
            'observaciones' => 'Baja voluntaria por motivos de cambio de domicilio a otro estado.',
            'estado' => 'interrumpido'
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO empleados (
            nombre, apellido, edad, nivel_academico, demografia, puesto,
            codigo_empleado, supervisor, fecha_entrada, termino_contrato, observaciones, estado
        ) VALUES (
            :nombre, :apellido, :edad, :nivel_academico, :demografia, :puesto,
            :codigo_empleado, :supervisor, :fecha_entrada, :termino_contrato, :observaciones, :estado
        )
    ");

    $empIdMap = [];
    foreach ($empleados as $emp) {
        $stmt->execute([
            ':nombre' => $emp['nombre'],
            ':apellido' => $emp['apellido'],
            ':edad' => $emp['edad'],
            ':nivel_academico' => $emp['nivel_academico'],
            ':demografia' => $emp['demografia'],
            ':puesto' => $emp['puesto'],
            ':codigo_empleado' => $emp['codigo_empleado'],
            ':supervisor' => $emp['supervisor'],
            ':fecha_entrada' => $emp['fecha_entrada'],
            ':termino_contrato' => $emp['termino_contrato'],
            ':observaciones' => $emp['observaciones'],
            ':estado' => $emp['estado'],
        ]);
        $empId = (int)$pdo->lastInsertId();
        $empIdMap[$emp['codigo_empleado']] = $empId;
        crearEvaluacionesPorDefecto($pdo, $empId, $emp['fecha_entrada'], $emp['termino_contrato']);
    }

    $stmtEval = $pdo->prepare("
        UPDATE evaluaciones 
        SET fecha_realizada = :fReal, calificacion = :calif, observaciones = :obs 
        WHERE empleado_id = :empId AND numero_evaluacion = :num
    ");

    $empId10101 = $empIdMap['10101'] ?? 1;
    $empId10102 = $empIdMap['10102'] ?? 2;
    $empId10201 = $empIdMap['10201'] ?? 4;
    $empId10301 = $empIdMap['10301'] ?? 5;

    $stmtEval->execute([
        ':fReal' => (clone $hoy)->modify('-54 days')->format('Y-m-d'),
        ':calif' => 88.5,
        ':obs' => 'Cumple satisfactoriamente con tiempos de ruta.',
        ':empId' => $empId10101,
        ':num' => 1
    ]);
    $stmtEval->execute([
        ':fReal' => (clone $hoy)->modify('-24 days')->format('Y-m-d'),
        ':calif' => 92.0,
        ':obs' => 'Muy buen trato con los clientes y cuidado de unidad.',
        ':empId' => $empId10101,
        ':num' => 2
    ]);

    $stmtEval->execute([
        ':fReal' => (clone $hoy)->modify('-15 days')->format('Y-m-d'),
        ':calif' => 95.0,
        ':obs' => 'Excelente agilidad y apego a normas de seguridad.',
        ':empId' => $empId10102,
        ':num' => 1
    ]);

    $stmtEval->execute([
        ':fReal' => (clone $hoy)->modify('-90 days')->format('Y-m-d'),
        ':calif' => 95.0,
        ':obs' => 'Conocimiento técnico superior al estándar.',
        ':empId' => $empId10201,
        ':num' => 1
    ]);
    $stmtEval->execute([
        ':fReal' => (clone $hoy)->modify('-60 days')->format('Y-m-d'),
        ':calif' => 98.0,
        ':obs' => 'Resolución de desviaciones en línea de producción.',
        ':empId' => $empId10201,
        ':num' => 2
    ]);
    $stmtEval->execute([
        ':fReal' => (clone $hoy)->modify('-31 days')->format('Y-m-d'),
        ':calif' => 97.0,
        ':obs' => 'Recomendada ampliamente para contrato permanente.',
        ':empId' => $empId10201,
        ':num' => 3
    ]);

    $stmtEval->execute([
        ':fReal' => (clone $hoy)->modify('-60 days')->format('Y-m-d'),
        ':calif' => 75.0,
        ':obs' => 'Faltas reiteradas por trámites de traslado.',
        ':empId' => $empId10301,
        ':num' => 1
    ]);
}
