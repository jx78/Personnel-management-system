-- Respaldo del Sistema de Empleados
-- Generado : 2026-11-23 14:50:04
-- Motor    : SQLITE
-- Tablas   : empleados, evaluaciones, usuarios

PRAGMA foreign_keys = OFF;

-- Tabla: empleados
DROP TABLE IF EXISTS "empleados";
CREATE TABLE empleados (
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

INSERT INTO "empleados" ("id", "nombre", "apellido", "edad", "nivel_academico", "demografia", "puesto", "codigo_empleado", "supervisor", "fecha_entrada", "termino_contrato", "observaciones", "estado", "fecha_registro") VALUES ('1', 'Mariana', 'Villalobos Ruiz', '30', 'Licenciatura', 'Urbana', 'Coordinadora de Calidad', 'EMP-4649', 'Ing. Javier Morales', '2026-11-01', '2027-02-01', 'Alta de prueba en simulación de usuario.', 'en_evaluacion', '2026-11-17 19:48:42');
INSERT INTO "empleados" ("id", "nombre", "apellido", "edad", "nivel_academico", "demografia", "puesto", "codigo_empleado", "supervisor", "fecha_entrada", "termino_contrato", "observaciones", "estado", "fecha_registro") VALUES ('2', 'Mariana', 'Villalobos Ruiz', '30', 'Licenciatura', 'Urbana', 'Coordinadora de Calidad', 'EMP-6237', 'Ing. Javier Morales', '2026-11-01', '2027-02-01', 'Alta de prueba en simulación de usuario.', 'en_evaluacion', '2026-11-17 19:49:39');
INSERT INTO "empleados" ("id", "nombre", "apellido", "edad", "nivel_academico", "demografia", "puesto", "codigo_empleado", "supervisor", "fecha_entrada", "termino_contrato", "observaciones", "estado", "fecha_registro") VALUES ('3', 'Mariana', 'Villalobos Ruiz', '30', 'Licenciatura', 'Urbana', 'Coordinadora de Calidad', 'EMP-5297', 'Ing. Javier Morales', '2026-11-01', '2027-02-01', 'Alta de prueba en simulación de usuario.', 'en_evaluacion', '2026-11-17 19:50:42');
INSERT INTO "empleados" ("id", "nombre", "apellido", "edad", "nivel_academico", "demografia", "puesto", "codigo_empleado", "supervisor", "fecha_entrada", "termino_contrato", "observaciones", "estado", "fecha_registro") VALUES ('4', 'Mariana', 'Villalobos Ruiz', '30', 'Licenciatura', 'Urbana', 'Coordinadora de Calidad', 'EMP-3372', 'Ing. Javier Morales', '2026-11-01', '2027-02-01', 'Alta de prueba en simulación de usuario.', 'en_evaluacion', '2026-11-17 19:51:06');
INSERT INTO "empleados" ("id", "nombre", "apellido", "edad", "nivel_academico", "demografia", "puesto", "codigo_empleado", "supervisor", "fecha_entrada", "termino_contrato", "observaciones", "estado", "fecha_registro") VALUES ('5', 'Mariana', 'Villalobos Ruiz', '30', 'Licenciatura', 'Urbana', 'Coordinadora de Calidad', 'EMP-4918', 'Ing. Javier Morales', '2026-11-01', '2027-02-01', 'Alta de prueba en simulación de usuario.', 'en_evaluacion', '2026-11-21 01:15:48');

-- Tabla: evaluaciones
DROP TABLE IF EXISTS "evaluaciones";
CREATE TABLE evaluaciones (
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

INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('1', '1', '1', '2026-12-01', NULL, NULL, NULL);
INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('2', '1', '2', '2026-12-31', NULL, NULL, NULL);
INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('3', '1', '3', '2027-02-01', NULL, NULL, NULL);
INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('4', '2', '1', '2026-12-01', NULL, NULL, NULL);
INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('5', '2', '2', '2026-12-31', NULL, NULL, NULL);
INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('6', '2', '3', '2027-02-01', NULL, NULL, NULL);
INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('7', '3', '1', '2026-12-01', '2026-12-01', '96.5', 'Excelente desempeño técnico y adaptación al equipo.');
INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('8', '3', '2', '2026-12-31', NULL, NULL, NULL);
INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('9', '3', '3', '2027-02-01', NULL, NULL, NULL);
INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('10', '4', '1', '2026-12-01', '2026-12-01', '96.5', 'Excelente desempeño técnico y adaptación al equipo.');
INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('11', '4', '2', '2026-12-31', NULL, NULL, NULL);
INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('12', '4', '3', '2027-02-01', NULL, NULL, NULL);
INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('13', '5', '1', '2026-12-01', '2026-12-01', '96.5', 'Excelente desempeño técnico y adaptación al equipo.');
INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('14', '5', '2', '2026-12-31', NULL, NULL, NULL);
INSERT INTO "evaluaciones" ("id", "empleado_id", "numero_evaluacion", "fecha_esperada", "fecha_realizada", "calificacion", "observaciones") VALUES ('15', '5', '3', '2027-02-01', NULL, NULL, NULL);

-- Tabla: usuarios
DROP TABLE IF EXISTS "usuarios";
CREATE TABLE usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                rol TEXT NOT NULL DEFAULT 'admin',
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

INSERT INTO "usuarios" ("id", "usuario", "password_hash", "rol", "fecha_creacion") VALUES ('1', 'config', '$2y$10$qLfRHkf1Orx/1NWgOjBWx./rGQEXNvGPaAxk/Iz8HzByptF4yJyaW', 'admin', '2026-11-17 19:38:33');
INSERT INTO "usuarios" ("id", "usuario", "password_hash", "rol", "fecha_creacion") VALUES ('2', 'admin', '$2y$10$UczdR7VFl6qG9aXwgSYkHeZDSjgV8nvweUdGUeYq0wFzEyEaum4pi', 'admin', '2026-11-17 19:38:33');

PRAGMA foreign_keys = ON;