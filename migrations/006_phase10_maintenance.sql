-- =====================================================================
-- Phase 10 — Maintenance role + unit blocking for trucks / gensets / trailers.
-- Database: ptsifleet_db2
-- Idempotent: safe to run multiple times.
-- =====================================================================
USE `ptsifleet_db2`;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. units / trailer — fast-check flag for "is this currently blocked?"
--    The current open block row in unit_maintenance is the source of
--    truth; these columns are denormalised mirrors so dispatch pickers
--    can stay a single AND in the WHERE clause.
-- ---------------------------------------------------------------------
ALTER TABLE `units`
    ADD COLUMN IF NOT EXISTS `maintenance_blocked`          TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `maintenance_reason`           VARCHAR(200) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `maintenance_expected_return`  DATE         NULL,
    ADD COLUMN IF NOT EXISTS `maintenance_blocked_at`       DATETIME     NULL,
    ADD KEY IF NOT EXISTS `idx_units_block` (`maintenance_blocked`);

ALTER TABLE `trailer`
    ADD COLUMN IF NOT EXISTS `maintenance_blocked`          TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `maintenance_reason`           VARCHAR(200) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `maintenance_expected_return`  DATE         NULL,
    ADD COLUMN IF NOT EXISTS `maintenance_blocked_at`       DATETIME     NULL,
    ADD KEY IF NOT EXISTS `idx_trailer_block` (`maintenance_blocked`);

-- ---------------------------------------------------------------------
-- 2. unit_maintenance — append-only history (one row per block event).
--    Open rows have status='active' and released_at IS NULL.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `unit_maintenance` (
    `um_id`            INT(11)      NOT NULL AUTO_INCREMENT,
    `unit_kind`        VARCHAR(20)  NOT NULL,                            -- truck | genset | trailer
    `unit_code`        VARCHAR(50)  NOT NULL,                            -- units.unit_name or trailer.trailer_name
    `category`         VARCHAR(40)  NOT NULL DEFAULT 'other',            -- engine | brakes | tyres | electrical | body | scheduled | accident | other
    `severity`         VARCHAR(20)  NOT NULL DEFAULT 'med',              -- low | med | high
    `reason`           VARCHAR(500) NOT NULL DEFAULT '',
    `photo_path`       VARCHAR(255) NOT NULL DEFAULT '',
    `expected_return`  DATE         NULL,
    `blocked_by`       INT(11)      NULL,
    `blocked_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `released_by`      INT(11)      NULL,
    `released_at`      DATETIME     NULL,
    `release_notes`    VARCHAR(500) NOT NULL DEFAULT '',
    `cost_labor`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `cost_parts`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status`           VARCHAR(20)  NOT NULL DEFAULT 'active',           -- active | released
    PRIMARY KEY (`um_id`),
    KEY `idx_um_kind_code_status` (`unit_kind`, `unit_code`, `status`),
    KEY `idx_um_status`           (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 3. The 'Maintenance' user_type is a new accepted value. The user
--    table column is VARCHAR — no DDL needed; just documenting the
--    new role here. Application code accepts it and the user dropdown
--    on admin/user.php was updated to expose it.
-- ---------------------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 1;
-- =====================================================================
-- Verify with: SHOW TABLES LIKE 'unit_maintenance';
--             DESCRIBE units;
-- =====================================================================
