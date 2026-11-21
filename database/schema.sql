-- =============================================================
--  schema.sql — Esquema completo + usuario de aplicación
--  Compatible al 100% con MySQL 8+ (InnoDB) y MariaDB 10.6+ (InnoDB)
-- =============================================================

-- 1. Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS sistema_empleados
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sistema_empleados;

-- 2. Tablas con Motor de Almacenamiento InnoDB -------------------------

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
    numero_evaluacion TINYINT NOT NULL, -- 1: +30 días, 2: +60 días, 3: Término
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

-- 3. Usuario de aplicación con permisos mínimos -------------------
--
--  El usuario 'emp_app' solo puede leer y escribir filas (DML).
--  NO tiene CREATE, DROP, ALTER, GRANT ni privilegios estructurales.
--  Se crea para 'localhost' y '127.0.0.1' para garantizar compatibilidad
--  con conexiones TCP en Windows, Linux y contenedores Docker.
--

CREATE USER IF NOT EXISTS 'emp_app'@'localhost' IDENTIFIED BY 'ContraseñaSeguraApp1!';
CREATE USER IF NOT EXISTS 'emp_app'@'127.0.0.1' IDENTIFIED BY 'ContraseñaSeguraApp1!';

-- Permisos sobre empleados
GRANT SELECT, INSERT, UPDATE, DELETE ON sistema_empleados.empleados TO 'emp_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON sistema_empleados.empleados TO 'emp_app'@'127.0.0.1';

-- Permisos sobre evaluaciones
GRANT SELECT, INSERT, UPDATE, DELETE ON sistema_empleados.evaluaciones TO 'emp_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON sistema_empleados.evaluaciones TO 'emp_app'@'127.0.0.1';

-- Permisos sobre usuarios (lectura, inserción y actualización)
GRANT SELECT, INSERT, UPDATE ON sistema_empleados.usuarios TO 'emp_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON sistema_empleados.usuarios TO 'emp_app'@'127.0.0.1';

FLUSH PRIVILEGES;

-- 4. Usuario admin inicial -----------------------------------------
--
--  Inserta el usuario 'admin' con contraseña 'CambiarEstoClave1!'
--  (hash bcrypt). Cámbiala inmediatamente después del primer login.
--
--  Para generar un hash de tu propia contraseña:
--      php -r "echo password_hash('TuContraseña', PASSWORD_DEFAULT);"
--
INSERT INTO usuarios (usuario, password_hash, rol)
SELECT 'admin',
       '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       'admin'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE usuario = 'admin');

-- 5. Usuario config inicial ----------------------------------------
-- Clave predeterminada para el módulo de configuraciones: 'ana3'
INSERT INTO usuarios (usuario, password_hash, rol)
SELECT 'config',
       '$2y$12$VM/XK7Byh0v8rt2.09cEg.1BBMqWjB0J5RZjfOmjRJruMDU32MF1i',
       'admin'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE usuario = 'config');