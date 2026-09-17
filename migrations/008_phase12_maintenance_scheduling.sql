-- =====================================================================
-- Phase 12 — Scheduled maintenance support for unit_maintenance.
-- Database: ptsifleet_db2
-- Idempotent: safe to run multiple times.
-- =====================================================================
USE `ptsifleet_db2`;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- unit_maintenance — add scheduling / activation timestamps so
-- maintenance can schedule a future block and the application can
-- activate it automatically at the scheduled time.
--
-- Existing status values:
--   active
--   released
--
-- New status value introduced by the application:
--   scheduled
-- ---------------------------------------------------------------------
ALTER TABLE `unit_maintenance`
    ADD COLUMN IF NOT EXISTS `scheduled_start_at` DATETIME NULL AFTER `expected_return`,
    ADD COLUMN IF NOT EXISTS `activated_at`       DATETIME NULL AFTER `scheduled_start_at`,
    ADD KEY IF NOT EXISTS `idx_um_scheduled_start` (`scheduled_start_at`);

SET FOREIGN_KEY_CHECKS = 1;
-- =====================================================================
-- Verify with:
--   DESCRIBE unit_maintenance;
--   SHOW INDEX FROM unit_maintenance;
-- =====================================================================
