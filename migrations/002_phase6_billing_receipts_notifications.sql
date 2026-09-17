-- =====================================================================
-- Phase 6 — Billing close, trip receipts, client notification, push log.
-- Database: ptsifleet_db2
-- Idempotent: safe to run multiple times.
-- =====================================================================
USE `ptsifleet_db2`;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. dispatch_receipt — files the dispatcher attaches to a dispatch
--    (cargo manifest, gate pass, customs doc, etc.). The driver views
--    them in the PWA and may need to tap Acknowledge before going en-route.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dispatch_receipt` (
    `dr_id`           INT(11)      NOT NULL AUTO_INCREMENT,
    `d_id`            INT(11)      NOT NULL,
    `receipt_type`    VARCHAR(50)  NOT NULL DEFAULT 'other',  -- manifest|gate_pass|customs|delivery_note|other
    `title`           VARCHAR(150) NOT NULL DEFAULT '',
    `file_path`       VARCHAR(255) NOT NULL,
    `mime_type`       VARCHAR(100) NOT NULL DEFAULT '',
    `requires_ack`    TINYINT(1)   NOT NULL DEFAULT 1,        -- driver must tap Acknowledge before en-route
    `attached_by`     INT(11)      NULL,
    `attached_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`dr_id`),
    KEY `idx_dr_dispatch` (`d_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 2. receipt_acknowledge — driver-tap audit trail
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `receipt_acknowledge` (
    `ra_id`        INT(11)   NOT NULL AUTO_INCREMENT,
    `dr_id`        INT(11)   NOT NULL,
    `driver_id`    INT(11)   NOT NULL,
    `acknowledged_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ra_id`),
    UNIQUE KEY `uniq_ra_driver_receipt` (`dr_id`, `driver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 3. push_send_log — append-only outbound notification audit
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `push_send_log` (
    `psl_id`     INT(11)      NOT NULL AUTO_INCREMENT,
    `to_role`    VARCHAR(20)  NOT NULL,                       -- driver|dispatcher|client
    `to_id`      INT(11)      NULL,
    `channel`    VARCHAR(20)  NOT NULL DEFAULT 'webpush',     -- webpush|email|sms
    `subject`    VARCHAR(200) NOT NULL DEFAULT '',
    `body`       VARCHAR(1000) NOT NULL DEFAULT '',
    `d_id`       INT(11)      NULL,
    `outcome`    VARCHAR(50)  NOT NULL DEFAULT 'queued',      -- queued|sent|failed|skipped
    `error`      VARCHAR(500) NOT NULL DEFAULT '',
    `sent_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`psl_id`),
    KEY `idx_psl_dispatch` (`d_id`),
    KEY `idx_psl_outcome` (`outcome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 4. customer — add contact fields used by client_notified flow
-- ---------------------------------------------------------------------
ALTER TABLE `customer`
    ADD COLUMN IF NOT EXISTS `notify_email` VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `notify_phone` VARCHAR(50)  NOT NULL DEFAULT '';

-- ---------------------------------------------------------------------
-- 5. dispatch — billing summary (kept lightweight; line items live on
--    the trips table via segment_costumer). Adding a snapshot total so
--    closed bills don't need to be re-derived.
-- ---------------------------------------------------------------------
ALTER TABLE `dispatch`
    ADD COLUMN IF NOT EXISTS `billing_amount`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `billing_currency` VARCHAR(10)   NOT NULL DEFAULT 'PHP',
    ADD COLUMN IF NOT EXISTS `billing_notes`    VARCHAR(500)  NOT NULL DEFAULT '';

SET FOREIGN_KEY_CHECKS = 1;
-- =====================================================================
-- End of Phase 6 migration.
-- Verify with: SHOW TABLES LIKE 'dispatch_receipt';
--             DESCRIBE customer;
-- =====================================================================
