-- =====================================================================
-- Phase 7 — Shift end, POD verification gate, equipment location tracking.
-- Database: ptsifleet_db2
-- Idempotent: safe to run multiple times.
-- =====================================================================
USE `ptsifleet_db2`;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. drivers — track shift end so we can compute machine hours.
--    shift_started_at + shift_truck already exist from Phase 1.
-- ---------------------------------------------------------------------
ALTER TABLE `drivers`
    ADD COLUMN IF NOT EXISTS `shift_ended_at` DATETIME NULL AFTER `shift_started_at`;

-- ---------------------------------------------------------------------
-- 2. driver_shift — historical shift records, one row per shift.
--    Closed shifts contribute their (end - start) duration to the
--    truck's machine-hour total.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `driver_shift` (
    `ds_id`          INT(11)      NOT NULL AUTO_INCREMENT,
    `driver_id`      INT(11)      NOT NULL,
    `truck_code`     VARCHAR(50)  NOT NULL DEFAULT '',
    `started_at`     DATETIME     NOT NULL,
    `ended_at`       DATETIME     NULL,
    `machine_hours`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,    -- (ended_at - started_at) in hours, set on End Shift
    `pdc_id`         INT(11)      NULL,                       -- pre_departure_checklist row that opened this shift
    `notes`          VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`ds_id`),
    KEY `idx_ds_driver_open` (`driver_id`, `ended_at`),
    KEY `idx_ds_truck`       (`truck_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 3. dispatch — trip timer + completion stamp + verification audit.
--    driver_accepted_at (Phase 1) already serves as trip_started_at;
--    the new column lets us derive trip duration without a join.
-- ---------------------------------------------------------------------
ALTER TABLE `dispatch`
    ADD COLUMN IF NOT EXISTS `trip_started_at`  DATETIME NULL,
    ADD COLUMN IF NOT EXISTS `trip_completed_at` DATETIME NULL,
    ADD COLUMN IF NOT EXISTS `verified_by`       INT(11)  NULL,
    ADD COLUMN IF NOT EXISTS `verified_at`       DATETIME NULL,
    ADD COLUMN IF NOT EXISTS `verification_notes` VARCHAR(500) NOT NULL DEFAULT '';

-- Backfill: any row where the driver has accepted should have a
-- trip_started_at = driver_accepted_at. Idempotent (only fills NULLs).
UPDATE `dispatch`
SET `trip_started_at` = `driver_accepted_at`
WHERE `trip_started_at` IS NULL AND `driver_accepted_at` IS NOT NULL;

-- ---------------------------------------------------------------------
-- 4. units — current location for trucks AND gensets, set by gate scans.
--    Possible values: 'PTSI Base' | 'Consol Base' | 'In Transit' | other.
-- ---------------------------------------------------------------------
ALTER TABLE `units`
    ADD COLUMN IF NOT EXISTS `current_location` VARCHAR(100) NOT NULL DEFAULT 'PTSI Base',
    ADD COLUMN IF NOT EXISTS `current_location_updated_at` DATETIME NULL;

-- ---------------------------------------------------------------------
-- 5. trailer — same idea. trailer_location already exists; we add a
--    structured base-name + last-update.
-- ---------------------------------------------------------------------
ALTER TABLE `trailer`
    ADD COLUMN IF NOT EXISTS `current_base` VARCHAR(100) NOT NULL DEFAULT 'PTSI Base',
    ADD COLUMN IF NOT EXISTS `current_base_updated_at` DATETIME NULL;

-- ---------------------------------------------------------------------
-- 6. pod_capture — verification fields (driver-side POD waits for
--    a dispatcher to confirm before the trip flips Completed).
-- ---------------------------------------------------------------------
ALTER TABLE `pod_capture`
    ADD COLUMN IF NOT EXISTS `verified_by`        INT(11)  NULL,
    ADD COLUMN IF NOT EXISTS `verified_at`        DATETIME NULL,
    ADD COLUMN IF NOT EXISTS `verification_notes` VARCHAR(500) NOT NULL DEFAULT '';

SET FOREIGN_KEY_CHECKS = 1;
-- =====================================================================
-- End of Phase 7 migration.
-- Verify with: DESCRIBE drivers; SHOW TABLES LIKE 'driver_shift';
-- =====================================================================
