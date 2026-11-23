-- =============================================================================
-- ScoreLive : schéma MySQL
-- Compatible MySQL 5.7+ / 8.x et MariaDB 10.3+ (utf8mb4_unicode_ci)
--
-- Import (MAMP) :
--   /Applications/MAMP/Library/bin/mysql80/bin/mysql -h 127.0.0.1 -P 8889 -u root -proot < database.sql
-- ou via phpMyAdmin : onglet « Importer ».
-- Le script est ré-exécutable sans perte de données (CREATE … IF NOT EXISTS).
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `scorelive`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `scorelive`;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+00:00';

-- -----------------------------------------------------------------------------
-- Utilisateurs
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(30)  NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_username` (`username`),
  UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Favoris : équipes (ref_id = id de l'équipe) et compétitions (ref_id = code)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `favorites` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       ENUM('team','competition') NOT NULL,
  `ref_id`     VARCHAR(20)  NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `crest`      VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_favorites` (`user_id`, `type`, `ref_id`),
  CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Cache des réponses de l'API football-data.org (30 s par défaut)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_cache` (
  `cache_key`   CHAR(64)          NOT NULL,
  `endpoint`    VARCHAR(255)      NOT NULL,
  `status_code` SMALLINT UNSIGNED NOT NULL,
  `payload`     MEDIUMTEXT        NOT NULL,
  `fetched_at`  DATETIME          NOT NULL,
  `expires_at`  DATETIME          NOT NULL,
  PRIMARY KEY (`cache_key`),
  KEY `idx_api_cache_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des appels sortants : limite locale de N requêtes par minute
CREATE TABLE IF NOT EXISTS `api_requests` (
  `id`           BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `endpoint`     VARCHAR(255)      NOT NULL,
  `status_code`  SMALLINT UNSIGNED NULL DEFAULT NULL,
  `requested_at` DATETIME(3)       NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_api_requests_time` (`requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- État partagé de l'API (ex. blocage temporaire après un HTTP 429)
CREATE TABLE IF NOT EXISTS `api_state` (
  `name`       VARCHAR(50)  NOT NULL,
  `value`      VARCHAR(255) NOT NULL,
  `updated_at` DATETIME     NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Index local des compétitions et des équipes (recherche, menus)
-- Alimenté automatiquement par les réponses de l'API, sans requête supplémentaire.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `competitions` (
  `id`              INT UNSIGNED NOT NULL,
  `code`            VARCHAR(12)  NULL DEFAULT NULL,
  `name`            VARCHAR(150) NOT NULL,
  `type`            VARCHAR(20)  NULL DEFAULT NULL,
  `emblem`          VARCHAR(255) NULL DEFAULT NULL,
  `plan`            VARCHAR(20)  NULL DEFAULT NULL,
  `area_name`       VARCHAR(100) NULL DEFAULT NULL,
  `area_code`       VARCHAR(10)  NULL DEFAULT NULL,
  `area_flag`       VARCHAR(255) NULL DEFAULT NULL,
  `teams_synced_at` DATETIME     NULL DEFAULT NULL,
  `updated_at`      DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_competitions_code` (`code`),
  KEY `idx_competitions_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teams` (
  `id`         INT UNSIGNED NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `short_name` VARCHAR(100) NULL DEFAULT NULL,
  `tla`        VARCHAR(10)  NULL DEFAULT NULL,
  `crest`      VARCHAR(255) NULL DEFAULT NULL,
  `area_name`  VARCHAR(100) NULL DEFAULT NULL,
  `updated_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_teams_name` (`name`),
  KEY `idx_teams_short_name` (`short_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tentatives de connexion échouées (protection contre la force brute)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`           VARCHAR(45)  NOT NULL,
  `identifier`   VARCHAR(190) NULL DEFAULT NULL,
  `attempted_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_ip` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Les 12 compétitions de l'offre gratuite football-data.org (TIER_ONE)
-- -----------------------------------------------------------------------------
INSERT INTO `competitions`
  (`id`, `code`, `name`, `type`, `emblem`, `plan`, `area_name`, `area_code`, `area_flag`, `updated_at`)
VALUES
  (2013, 'BSA', 'Campeonato Brasileiro Série A', 'LEAGUE', 'https://crests.football-data.org/bsa.png', 'TIER_ONE', 'Brazil', 'BRA', 'https://crests.football-data.org/764.svg', UTC_TIMESTAMP()),
  (2016, 'ELC', 'Championship', 'LEAGUE', 'https://crests.football-data.org/ELC.png', 'TIER_ONE', 'England', 'ENG', 'https://crests.football-data.org/770.svg', UTC_TIMESTAMP()),
  (2021, 'PL', 'Premier League', 'LEAGUE', 'https://crests.football-data.org/PL.png', 'TIER_ONE', 'England', 'ENG', 'https://crests.football-data.org/770.svg', UTC_TIMESTAMP()),
  (2001, 'CL', 'UEFA Champions League', 'CUP', 'https://crests.football-data.org/CL.png', 'TIER_ONE', 'Europe', 'EUR', 'https://crests.football-data.org/EUR.svg', UTC_TIMESTAMP()),
  (2018, 'EC', 'European Championship', 'CUP', 'https://crests.football-data.org/ec.png', 'TIER_ONE', 'Europe', 'EUR', 'https://crests.football-data.org/EUR.svg', UTC_TIMESTAMP()),
  (2015, 'FL1', 'Ligue 1', 'LEAGUE', 'https://crests.football-data.org/FL1.png', 'TIER_ONE', 'France', 'FRA', 'https://crests.football-data.org/773.svg', UTC_TIMESTAMP()),
  (2002, 'BL1', 'Bundesliga', 'LEAGUE', 'https://crests.football-data.org/BL1.png', 'TIER_ONE', 'Germany', 'DEU', 'https://crests.football-data.org/759.svg', UTC_TIMESTAMP()),
  (2019, 'SA', 'Serie A', 'LEAGUE', 'https://crests.football-data.org/c111.png', 'TIER_ONE', 'Italy', 'ITA', 'https://crests.football-data.org/784.svg', UTC_TIMESTAMP()),
  (2003, 'DED', 'Eredivisie', 'LEAGUE', 'https://crests.football-data.org/ED.png', 'TIER_ONE', 'Netherlands', 'NLD', 'https://crests.football-data.org/8601.svg', UTC_TIMESTAMP()),
  (2017, 'PPL', 'Primeira Liga', 'LEAGUE', 'https://crests.football-data.org/PPL.png', 'TIER_ONE', 'Portugal', 'POR', 'https://crests.football-data.org/765.svg', UTC_TIMESTAMP()),
  (2014, 'PD', 'Primera Division', 'LEAGUE', 'https://crests.football-data.org/laliga.png', 'TIER_ONE', 'Spain', 'ESP', 'https://crests.football-data.org/760.svg', UTC_TIMESTAMP()),
  (2000, 'WC', 'FIFA World Cup', 'CUP', 'https://crests.football-data.org/wm26.png', 'TIER_ONE', 'World', 'INT', NULL, UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE
  `code` = VALUES(`code`), `name` = VALUES(`name`), `type` = VALUES(`type`),
  `emblem` = VALUES(`emblem`), `plan` = VALUES(`plan`), `area_name` = VALUES(`area_name`),
  `area_code` = VALUES(`area_code`), `area_flag` = VALUES(`area_flag`);
