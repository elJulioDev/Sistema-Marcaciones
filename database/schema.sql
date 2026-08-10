-- ============================================================================
-- SISTEMA DE MARCACIONES
-- Esquema de base de datos (MariaDB / MySQL, InnoDB, utf8mb4)
--
-- Reconstruido a partir de las consultas del código fuente (rama Original).
-- Para instalarlo:
--     mysql -u usuario -p < database/schema.sql
-- o importarlo desde phpMyAdmin.
--
-- Las credenciales de conexión van en .env (archivo local, NO versionado;
-- ver .env.example). El primer usuario administrador se crea con
-- password_hash(); ver Instalación en README.md.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `marcaciones`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE `marcaciones`;

-- ============================================================================
-- 1) MARCACIONES IMPORTACIONES — Auditoría de cada archivo cargado al sistema.
--    Se inserta primero en la importación; luego se asocian las marcaciones
--    brutas vía id_importacion.
-- ============================================================================
CREATE TABLE `marcaciones_importaciones` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `nombre_archivo`    VARCHAR(255)    NOT NULL,
    `periodo`           CHAR(7)         NULL,               -- 'YYYY-MM' (null si no se indicó)
    `observacion`       VARCHAR(500)    NULL,
    `total_lineas`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `total_insertadas`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `total_duplicadas`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `total_invalidas`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `creado_por`        INT UNSIGNED    NULL,               -- id de usuarios_sistema (sin FK)
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_periodo` (`periodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2) MARCACIONES — Registros brutos tal como salen del reloj de control.
--    hash_registro = md5(dpto|nombre|numero|fecha_hora) en MAYÚSCULAS
--    (ver app/Services/ImportadorMarcaciones.php): impide duplicados al
--    reimportar.
--    rut_base = número del RUT sin el dígito verificador.
-- ============================================================================
CREATE TABLE `marcaciones` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `id_importacion`    INT UNSIGNED    NULL,
    `dpto`              VARCHAR(255)    NOT NULL,
    `nombre`            VARCHAR(255)    NOT NULL,
    `numero`            VARCHAR(50)     NOT NULL,           -- RUT del reloj, solo dígitos/K
    `rut_base`          VARCHAR(20)     NOT NULL,           -- número sin DV
    `fecha_hora`        DATETIME        NOT NULL,
    `fecha`             DATE            NOT NULL,
    `hora`              TIME            NOT NULL,
    `hash_registro`     CHAR(32)        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_hash_registro` (`hash_registro`),
    KEY `idx_rut_fecha` (`rut_base`, `fecha`),              -- usada por recalcular_parcial()
    KEY `idx_fecha` (`fecha`),                              -- usada por MesController (eliminar mes)
    CONSTRAINT `fk_marcaciones_importacion`
        FOREIGN KEY (`id_importacion`)
        REFERENCES `marcaciones_importaciones` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3) MARCACIONES RESUMEN — Resumen calculado por (rut_base, fecha).
--    Estados:
--      OK          = 2 marcaciones, entrada < salida
--      OBSERVADO   = 3 o más marcaciones
--      INCOMPLETO  = 1 sola marcación
--      ERROR       = salida anterior a entrada, o solo existe salida
--    editado_manual = 1 protege la fila ante reimportaciones
--    (recalcular_parcial() usa ON DUPLICATE KEY UPDATE con IF(editado_manual=1,...)).
-- ============================================================================
CREATE TABLE `marcaciones_resumen` (
    `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `rut_base`              VARCHAR(20)     NOT NULL,
    `numero`                VARCHAR(50)     NOT NULL,
    `nombre`                VARCHAR(255)    NOT NULL,
    `dpto`                  VARCHAR(255)    NOT NULL,
    `fecha`                 DATE            NOT NULL,
    `entrada`               TIME            NULL,
    `salida`                TIME            NULL,
    `total_horas`           TIME            NULL,
    `cantidad_marcaciones`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `estado`                ENUM('OK','OBSERVADO','INCOMPLETO','ERROR') NOT NULL DEFAULT 'OK',
    `observacion`           VARCHAR(500)    NOT NULL DEFAULT '',
    `editado_manual`        TINYINT(1)      NOT NULL DEFAULT 0,
    `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rut_fecha` (`rut_base`, `fecha`),
    KEY `idx_fecha` (`fecha`),
    KEY `idx_estado` (`estado`),
    KEY `idx_dpto` (`dpto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4) USUARIOS DEL SISTEMA
--    password = password_hash() de PHP (bcrypt, VARCHAR(255)).
--    rol: 'admin' | 'operador' (control de roles implementado en el Router).
-- ============================================================================
CREATE TABLE `usuarios_sistema` (
    `id`        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `rut`       VARCHAR(20)     NOT NULL,
    `password`  VARCHAR(255)    NOT NULL,
    `nombre`    VARCHAR(150)    NOT NULL,
    `rol`       VARCHAR(20)     NOT NULL DEFAULT 'operador',
    `activo`    TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rut` (`rut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Usuario inicial (ejemplo): se recomienda crearlo con hash.php para que la
-- contraseña se genere con password_hash(). Si se prefiere SQL directo, hay
-- que reemplazar '$2y$...' por un hash bcrypt válido:
--
-- INSERT INTO `usuarios_sistema` (`rut`,`password`,`nombre`,`rol`,`activo`)
-- VALUES ('11111111-1', '$2y$10$hash-bcrypt-aqui', 'Administrador', 'admin', 1);
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 1;
