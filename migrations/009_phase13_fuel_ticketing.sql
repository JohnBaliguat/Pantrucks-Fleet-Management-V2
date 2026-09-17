-- =====================================================================
-- Phase 13 — Fuel Ticketing module (ported from PTSI Fuel System Base)
--
-- Adds the tables needed for the Gastender role:
--   * fuel_report      one row per refuel transaction (control no, hubo, ratio, etc.)
--   * fuel_inventory   stock received (PO/IV), FIFO-consumed by tickets
--   * consume_fuel     audit trail of each FIFO deduction
--   * ticket_code      6-char unique codes issued by sub-admin
--   * trip_receipts    TR rows linked to a ticket code OR a control no
--   * update_log       audit trail for fuel_report edits
--
-- All statements are idempotent: safe to re-run.
-- =====================================================================

-- ---------------------------------------------------------------------
-- fuel_report
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fuel_report` (
  `f_id` int(11) NOT NULL AUTO_INCREMENT,
  `f_date` datetime NOT NULL,
  `f_unit` varchar(200) NOT NULL,
  `f_lastHubo` double(11,2) NOT NULL DEFAULT 0,
  `f_hubo` double(11,2) NOT NULL DEFAULT 0,
  `calculated_hubo` double(11,2) NOT NULL DEFAULT 0,
  `f_kmRun` double(11,2) NOT NULL DEFAULT 0,
  `f_noOfLit` double(11,2) NOT NULL DEFAULT 0,
  `f_actRatio` double(11,2) NOT NULL DEFAULT 0,
  `f_driver` varchar(200) NOT NULL DEFAULT '',
  `f_driverId` varchar(50) NOT NULL DEFAULT '',
  `f_STDRatio` double(11,2) NOT NULL DEFAULT 0,
  `f_excess_Saving` double(11,2) NOT NULL DEFAULT 0,
  `f_hourMeter` varchar(200) NOT NULL DEFAULT '0',
  `f_controlNo` varchar(255) NOT NULL,
  `f_ideNoLt` double(11,2) NOT NULL DEFAULT 0,
  `f_tripTicket` varchar(200) NOT NULL DEFAULT '',
  `f_tripsegment` varchar(200) NOT NULL DEFAULT '',
  `f_trasactionBy` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`f_id`),
  KEY `idx_fuel_report_controlNo` (`f_controlNo`),
  KEY `idx_fuel_report_unit_date` (`f_unit`, `f_date`),
  KEY `idx_fuel_report_driverId` (`f_driverId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ensure the newer columns exist for upgrades from older PTSI dumps
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fuel_report' AND COLUMN_NAME = 'calculated_hubo');
SET @sql := IF(@col = 0, 'ALTER TABLE `fuel_report` ADD COLUMN `calculated_hubo` double(11,2) NOT NULL DEFAULT 0 AFTER `f_hubo`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fuel_report' AND COLUMN_NAME = 'f_driverId');
SET @sql := IF(@col = 0, 'ALTER TABLE `fuel_report` ADD COLUMN `f_driverId` varchar(50) NOT NULL DEFAULT '''' AFTER `f_driver`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- fuel_inventory
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fuel_inventory` (
  `fi_id` int(11) NOT NULL AUTO_INCREMENT,
  `fi_poNo` varchar(200) NOT NULL DEFAULT '',
  `fi_noOfLiters` double(11,2) NOT NULL DEFAULT 0,
  `fi_receiveBy` varchar(200) NOT NULL DEFAULT '',
  `fi_date` datetime NOT NULL,
  `fi_inVo` varchar(200) NOT NULL DEFAULT '',
  `fi_plateNo` varchar(200) NOT NULL DEFAULT '',
  `fi_consumableLtr` decimal(11,2) NOT NULL DEFAULT 0,
  `fi_consumeLtr` decimal(11,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`fi_id`),
  KEY `idx_fuel_inventory_date` (`fi_date`),
  KEY `idx_fuel_inventory_consumable` (`fi_consumableLtr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- consume_fuel
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `consume_fuel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unit` varchar(200) NOT NULL,
  `driver` varchar(200) NOT NULL DEFAULT '',
  `hubo` decimal(11,2) NOT NULL DEFAULT 0,
  `consumeLtr` decimal(11,2) NOT NULL DEFAULT 0,
  `date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_consume_fuel_unit` (`unit`),
  KEY `idx_consume_fuel_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- ticket_code (sub-admin issues a 6-char alphanumeric code per trip plan)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ticket_code` (
  `tc_id` int(11) NOT NULL AUTO_INCREMENT,
  `tc_code` varchar(12) NOT NULL,
  `f_driverId` varchar(50) NOT NULL DEFAULT '',
  `unit_name` varchar(200) NOT NULL DEFAULT '',
  `tc_date` datetime NOT NULL,
  `tc_status` varchar(20) NOT NULL DEFAULT 'Unused',
  PRIMARY KEY (`tc_id`),
  UNIQUE KEY `uk_ticket_code` (`tc_code`),
  KEY `idx_ticket_code_status` (`tc_status`),
  KEY `idx_ticket_code_driver` (`f_driverId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- trip_receipts (one row per TR; either bound to a ticket_code via tc_id,
-- or linked to a fuel_report via control_no when redeemed)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `trip_receipts` (
  `receipts_id` int(11) NOT NULL AUTO_INCREMENT,
  `tc_id` int(11) NOT NULL DEFAULT 0,
  `control_no` varchar(255) NOT NULL DEFAULT '',
  `tr_number` varchar(100) NOT NULL DEFAULT '',
  `location_from` varchar(200) NOT NULL DEFAULT '',
  `location_to` varchar(200) NOT NULL DEFAULT '',
  `total_km` decimal(11,2) NOT NULL DEFAULT 0,
  `maptotal_kmRun` decimal(11,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`receipts_id`),
  KEY `idx_trip_receipts_tc` (`tc_id`),
  KEY `idx_trip_receipts_control` (`control_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- update_log (audit for edits to fuel_report)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `update_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `f_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `upLog_datetime` datetime NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_update_log_fid` (`f_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Make sure units.unit_std exists (it should in FM-New, but guard anyway)
-- ---------------------------------------------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'units' AND COLUMN_NAME = 'unit_std');
SET @sql := IF(@col = 0, 'ALTER TABLE `units` ADD COLUMN `unit_std` double(11,2) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
