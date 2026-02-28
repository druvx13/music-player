-- Music Player — database schema
-- Compatible with: MySQL 8.0+ / MariaDB 10.5+
-- Charset: utf8mb4 (full Unicode, including emoji)
-- Engine:  InnoDB (transactions, foreign-key support, crash recovery)

SET SQL_MODE   = 'NO_AUTO_VALUE_ON_ZERO';
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone  = '+00:00';
SET NAMES utf8mb4;

-- ── Table: songs ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `songs` (
  `id`          INT            NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(255)   NOT NULL,
  `file`        VARCHAR(255)   NOT NULL,
  `cover`       VARCHAR(255)   DEFAULT NULL,
  `artist`      VARCHAR(255)   DEFAULT NULL,
  `lyrics`      TEXT           DEFAULT NULL,
  `uploaded_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `idx_uploaded_at` (`uploaded_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

COMMIT;
