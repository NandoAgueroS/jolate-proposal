-- JOLATE 2026 — Registration schema
-- Runs once on first MySQL container start (docker-entrypoint-initdb.d).

CREATE DATABASE IF NOT EXISTS `jolate` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `jolate`;

-- Registration type lookup (Expositor / Asistente).
-- Identifier contains a space — always backtick-quoted.
CREATE TABLE `tipo inscripto` (
    `id`     INT          NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(64)  NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tipo_inscripto_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tipo inscripto` (`id`, `nombre`) VALUES
    (1, 'Expositor'),
    (2, 'Asistente');

-- One row per registration submission.
-- Paper-related columns are nullable (Asistente does not submit a paper).
-- No duplicate detection, no status column — deferred to a future change.
CREATE TABLE `inscriptos` (
    `id`                  INT           NOT NULL AUTO_INCREMENT,
    `id_tipo_inscripto`   INT           NOT NULL,
    `nombre`              VARCHAR(150)  NOT NULL,
    `institucion`         VARCHAR(200)  NOT NULL,
    `email`               VARCHAR(200)  NOT NULL,
    `dni`                 VARCHAR(20)   NOT NULL,
    `titulo_ponencia`     VARCHAR(300)  DEFAULT NULL,
    `eje_tematico`        VARCHAR(120)  DEFAULT NULL,
    `archivo_filename`    VARCHAR(255)  DEFAULT NULL,
    `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_inscriptos_tipo`
        FOREIGN KEY (`id_tipo_inscripto`) REFERENCES `tipo inscripto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin accounts for the dashboard at /admin. Credentials checked with bcrypt.
-- No seed row: create the first admin with `php backend/bin/seed-admin.php <user> <pass>`.
CREATE TABLE `admins` (
    `id`            INT           NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(64)   NOT NULL,
    `password_hash` VARCHAR(255)  NOT NULL,
    `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed login attempts by IP. Used for brute-force rate-limit.
CREATE TABLE `admin_auth_attempts` (
    `id`        INT          NOT NULL AUTO_INCREMENT,
    `ip`        VARCHAR(45)  NOT NULL,
    `failed_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_auth_ip` (`ip`),
    KEY `idx_admin_auth_ip_failed` (`ip`, `failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
