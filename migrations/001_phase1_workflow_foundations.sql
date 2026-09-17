-- =====================================================================
-- Phase 1 — Workflow Foundations
-- Database: ptsifleet_db2
-- Idempotent: safe to run multiple times.
-- =====================================================================
--
-- This migration is ADDITIVE only. It does NOT drop or modify any
-- existing column or row. Existing flows keep working unchanged.
--
-- New entities:
--   gate_log                  — entry/exit log with QR + plate-match verification
--   gate_queue                — vehicles waiting at gate awaiting dispatcher approval
--   incident                  — delays, breakdowns, exceptions (links to dispatch/segment)
--   pre_departure_checklist   — fuel/tyres/lights/cargo/genset confirmations
--   pod_capture               — proof-of-delivery photos + e-signature
--   gateless_completion       — GPS + photos for destinations without a gate
--   trailer_jackup            — driver detaches trailer at location, billing continues
--   message                   — driver <-> dispatcher in-app chat
--   push_subscription         — Web Push (VAPID) subscriptions for the driver PWA
--   workflow_event            — append-only audit of the 9-stage workflow pipeline
--
-- Booking model extension:
--   booking.booking_type            (Import / Export / Local)
--   booking.vessel_name, voyage_no, container_no_port, bill_of_lading,
--   port_location, customs_cleared  (Import/Export only)
--
-- Segment model extension (the existing `trips` table already encodes
-- per-leg trip details linked to a single dispatch via d_id, so we extend
-- it rather than introduce a parallel segments table):
--   trips.segment_costumer          (per-leg billing — overrides booking-level)
--   trips.segment_status            (Pending / Assigned / EnRoute / Delivered / Cancelled / Foul)
--   trips.foul_trip                 (1 if cancelled after assignment)
--   trips.cancelled_at, cancelled_reason
--
-- Workflow stage on dispatch:
--   dispatch.workflow_stage         (one of: order_created, dispatcher_assigned,
--                                   driver_accepted, gate_cleared, en_route,
--                                   delivered, pod_captured, billing_closed,
--                                   client_notified)
--   dispatch.workflow_updated_at
--
-- Driver-app status:
--   drivers.fcm_token / push_endpoint stored separately in push_subscription.
--   drivers.shift_truck             (truck selected at shift-start)
--   drivers.shift_started_at
-- =====================================================================

USE `ptsifleet_db2`;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. units — distinguish trucks from gensets explicitly
--
-- Trucks (PM*, WV*, YG*, DT*, CT*) and gensets (GS*) both live in the
-- `units` table, currently identified only by `unit_name` prefix. We
-- add an explicit `unit_type` so reports/queries can filter cleanly
-- without string-prefix matching, and backfill from the prefix.
-- ---------------------------------------------------------------------
ALTER TABLE `units`
    ADD COLUMN IF NOT EXISTS `unit_type` VARCHAR(20) NOT NULL DEFAULT 'truck' AFTER `unit_name`,
    ADD KEY IF NOT EXISTS `idx_units_type` (`unit_type`);

UPDATE `units`
SET `unit_type` = 'genset'
WHERE UPPER(LEFT(TRIM(`unit_name`), 2)) = 'GS'
  AND `unit_type` <> 'genset';

UPDATE `units`
SET `unit_type` = 'truck'
WHERE UPPER(LEFT(TRIM(`unit_name`), 2)) <> 'GS'
  AND `unit_type` <> 'truck';

-- ---------------------------------------------------------------------
-- 2. booking — Import / Export / Local + port fields
-- ---------------------------------------------------------------------
ALTER TABLE `booking`
    ADD COLUMN IF NOT EXISTS `booking_type`     VARCHAR(20)  NOT NULL DEFAULT 'Local'  AFTER `booking_no`,
    ADD COLUMN IF NOT EXISTS `vessel_name`      VARCHAR(150) NOT NULL DEFAULT ''       AFTER `return_location`,
    ADD COLUMN IF NOT EXISTS `voyage_no`        VARCHAR(100) NOT NULL DEFAULT ''       AFTER `vessel_name`,
    ADD COLUMN IF NOT EXISTS `container_no_port` VARCHAR(100) NOT NULL DEFAULT ''      AFTER `voyage_no`,
    ADD COLUMN IF NOT EXISTS `bill_of_lading`   VARCHAR(100) NOT NULL DEFAULT ''       AFTER `container_no_port`,
    ADD COLUMN IF NOT EXISTS `port_location`    VARCHAR(150) NOT NULL DEFAULT ''       AFTER `bill_of_lading`,
    ADD COLUMN IF NOT EXISTS `customs_cleared`  TINYINT(1)   NOT NULL DEFAULT 0        AFTER `port_location`,
    ADD COLUMN IF NOT EXISTS `customs_cleared_at` DATETIME   NULL                      AFTER `customs_cleared`,
    ADD COLUMN IF NOT EXISTS `client_notified_at` DATETIME   NULL                      AFTER `customs_cleared_at`;

-- Note: MySQL 8 supports `IF NOT EXISTS` on ADD COLUMN. On MariaDB 10.x,
-- the same syntax also works since 10.0.2. If your server rejects it,
-- run the ALTERs without the clause one-by-one — column creation will
-- error harmlessly when re-run.

-- ---------------------------------------------------------------------
-- 3. trips — segment-level billing, foul-trip, status
-- ---------------------------------------------------------------------
ALTER TABLE `trips`
    ADD COLUMN IF NOT EXISTS `segment_costumer` VARCHAR(200) NOT NULL DEFAULT ''      AFTER `costumer`,
    ADD COLUMN IF NOT EXISTS `segment_status`   VARCHAR(50)  NOT NULL DEFAULT 'Pending' AFTER `trip_status`,
    ADD COLUMN IF NOT EXISTS `foul_trip`        TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `cancelled_at`     DATETIME     NULL,
    ADD COLUMN IF NOT EXISTS `cancelled_reason` VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `scheduled_at`     DATETIME     NULL;

-- ---------------------------------------------------------------------
-- 4. dispatch — 9-stage workflow tracking
-- ---------------------------------------------------------------------
ALTER TABLE `dispatch`
    ADD COLUMN IF NOT EXISTS `workflow_stage`     VARCHAR(40) NOT NULL DEFAULT 'dispatcher_assigned',
    ADD COLUMN IF NOT EXISTS `workflow_updated_at` DATETIME   NULL,
    ADD COLUMN IF NOT EXISTS `driver_accepted_at`  DATETIME   NULL,
    ADD COLUMN IF NOT EXISTS `driver_declined_at`  DATETIME   NULL,
    ADD COLUMN IF NOT EXISTS `decline_reason`      VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `gate_cleared_at`     DATETIME   NULL,
    ADD COLUMN IF NOT EXISTS `billing_closed_at`   DATETIME   NULL,
    ADD COLUMN IF NOT EXISTS `client_notified_at`  DATETIME   NULL;

-- ---------------------------------------------------------------------
-- 5. drivers — shift truck + signature already exists
-- ---------------------------------------------------------------------
ALTER TABLE `drivers`
    ADD COLUMN IF NOT EXISTS `shift_truck`       VARCHAR(50)  NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `shift_started_at`  DATETIME     NULL,
    ADD COLUMN IF NOT EXISTS `last_seen_at`      DATETIME     NULL,
    ADD COLUMN IF NOT EXISTS `last_lat`          DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS `last_lng`          DECIMAL(10,7) NULL;

-- ---------------------------------------------------------------------
-- 6. user — add Gate-Guard role (existing roles: Admin, Dispatcher,
--    HR, HR-Admin, Visual). user_type stays VARCHAR; nothing to alter,
--    just documenting the new accepted value.
-- ---------------------------------------------------------------------
-- (no DDL needed — value 'Gate-Guard' is now allowed by application)

-- ---------------------------------------------------------------------
-- 7. gate_log — every vehicle entry/exit, timestamped, verified
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gate_log` (
    `gl_id`           INT(11)      NOT NULL AUTO_INCREMENT,
    `d_id`            INT(11)      NULL,                     -- NULL = unauthorised arrival
    `direction`       VARCHAR(10)  NOT NULL,                  -- 'IN' or 'OUT'
    `truck_plate`     VARCHAR(50)  NOT NULL DEFAULT '',
    `trailer_code`    VARCHAR(50)  NOT NULL DEFAULT '',
    `genset_code`     VARCHAR(50)  NOT NULL DEFAULT '',
    `driver_id`       INT(11)      NULL,
    `qr_payload`      VARCHAR(255) NOT NULL DEFAULT '',       -- raw scanned text
    `verified`        TINYINT(1)   NOT NULL DEFAULT 0,        -- truck+trailer+genset match dispatch?
    `mismatch_reason` VARCHAR(255) NOT NULL DEFAULT '',
    `authorised`      TINYINT(1)   NOT NULL DEFAULT 0,        -- dispatcher cleared driver?
    `guard_user_id`   INT(11)      NULL,
    `logged_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`gl_id`),
    KEY `idx_gate_log_dispatch` (`d_id`),
    KEY `idx_gate_log_logged_at` (`logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 8. gate_queue — vehicles waiting for dispatcher approval to enter
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gate_queue` (
    `gq_id`         INT(11)     NOT NULL AUTO_INCREMENT,
    `d_id`          INT(11)     NULL,
    `truck_plate`   VARCHAR(50) NOT NULL DEFAULT '',
    `driver_id`     INT(11)     NULL,
    `direction`     VARCHAR(10) NOT NULL DEFAULT 'IN',
    `requested_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `decided_at`    DATETIME    NULL,
    `decision`      VARCHAR(20) NOT NULL DEFAULT 'pending',   -- pending / approved / denied
    `decided_by`    INT(11)     NULL,                          -- user_id of dispatcher
    `notes`         VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`gq_id`),
    KEY `idx_gate_queue_decision` (`decision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 9. incident — delays, breakdowns, exceptions
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `incident` (
    `inc_id`         INT(11)      NOT NULL AUTO_INCREMENT,
    `d_id`           INT(11)      NULL,
    `trip_id`        INT(11)      NULL,
    `driver_id`      INT(11)      NULL,
    `incident_type`  VARCHAR(40)  NOT NULL,                    -- delay / breakdown / exception / accident / cargo / unauthorised
    `severity`       VARCHAR(20)  NOT NULL DEFAULT 'low',      -- low / med / high
    `description`    TEXT         NOT NULL,
    `lat`            DECIMAL(10,7) NULL,
    `lng`            DECIMAL(10,7) NULL,
    `photo_path`     VARCHAR(255) NOT NULL DEFAULT '',
    `assistance`     VARCHAR(40)  NOT NULL DEFAULT '',         -- tow / mechanic / cargo_transfer / emergency
    `reassigned_d_id` INT(11)     NULL,
    `status`         VARCHAR(20)  NOT NULL DEFAULT 'open',     -- open / acknowledged / resolved
    `reported_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_at`    DATETIME     NULL,
    PRIMARY KEY (`inc_id`),
    KEY `idx_incident_dispatch` (`d_id`),
    KEY `idx_incident_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 10. pre_departure_checklist — fuel, tyres, lights, cargo area, genset
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pre_departure_checklist` (
    `pdc_id`         INT(11)     NOT NULL AUTO_INCREMENT,
    `driver_id`      INT(11)     NOT NULL,
    `truck_code`     VARCHAR(50) NOT NULL DEFAULT '',
    `trailer_code`   VARCHAR(50) NOT NULL DEFAULT '',
    `genset_code`    VARCHAR(50) NOT NULL DEFAULT '',
    `fuel_ok`        TINYINT(1)  NOT NULL DEFAULT 0,
    `tyres_ok`       TINYINT(1)  NOT NULL DEFAULT 0,
    `lights_ok`      TINYINT(1)  NOT NULL DEFAULT 0,
    `cargo_area_ok`  TINYINT(1)  NOT NULL DEFAULT 0,
    `genset_ok`      TINYINT(1)  NOT NULL DEFAULT 0,
    `remarks`        VARCHAR(255) NOT NULL DEFAULT '',
    `signed_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`pdc_id`),
    KEY `idx_pdc_driver_time` (`driver_id`, `signed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 11. pod_capture — proof-of-delivery (photos + e-signature)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pod_capture` (
    `pod_id`         INT(11)      NOT NULL AUTO_INCREMENT,
    `d_id`           INT(11)      NOT NULL,
    `trip_id`        INT(11)      NULL,
    `driver_id`      INT(11)      NOT NULL,
    `photo1_path`    VARCHAR(255) NOT NULL DEFAULT '',
    `photo2_path`    VARCHAR(255) NOT NULL DEFAULT '',
    `photo3_path`    VARCHAR(255) NOT NULL DEFAULT '',
    `signature_path` VARCHAR(255) NOT NULL DEFAULT '',
    `signed_by`      VARCHAR(150) NOT NULL DEFAULT '',         -- name typed by recipient
    `lat`            DECIMAL(10,7) NULL,
    `lng`            DECIMAL(10,7) NULL,
    `captured_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`pod_id`),
    KEY `idx_pod_dispatch` (`d_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 12. gateless_completion — GPS-stamped completion at no-gate destinations
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gateless_completion` (
    `gc_id`        INT(11)      NOT NULL AUTO_INCREMENT,
    `d_id`         INT(11)      NOT NULL,
    `trip_id`      INT(11)      NULL,
    `driver_id`    INT(11)      NOT NULL,
    `lat`          DECIMAL(10,7) NOT NULL,
    `lng`          DECIMAL(10,7) NOT NULL,
    `accuracy_m`   INT(6)       NOT NULL DEFAULT 0,
    `photo1_path`  VARCHAR(255) NOT NULL DEFAULT '',
    `photo2_path`  VARCHAR(255) NOT NULL DEFAULT '',
    `captured_at`  DATETIME     NOT NULL,                       -- preserved from offline timestamp
    `synced_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `offline`      TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (`gc_id`),
    KEY `idx_gc_dispatch` (`d_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 13. trailer_jackup — trailer detached at field, billing continues
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `trailer_jackup` (
    `tj_id`           INT(11)      NOT NULL AUTO_INCREMENT,
    `d_id`            INT(11)      NULL,
    `trailer_code`    VARCHAR(50)  NOT NULL,
    `driver_id`       INT(11)      NOT NULL,
    `lat`             DECIMAL(10,7) NULL,
    `lng`             DECIMAL(10,7) NULL,
    `photo_path`      VARCHAR(255) NOT NULL DEFAULT '',
    `detached_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `returned_at`     DATETIME     NULL,                        -- when trailer comes back to compound
    `billing_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (`tj_id`),
    KEY `idx_tj_trailer` (`trailer_code`),
    KEY `idx_tj_active`  (`billing_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 14. message — driver <-> dispatcher chat + group alerts
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `message` (
    `msg_id`        INT(11)      NOT NULL AUTO_INCREMENT,
    `from_role`     VARCHAR(20)  NOT NULL,                      -- driver / dispatcher / system
    `from_id`       INT(11)      NOT NULL,                      -- driver_id or user_id
    `to_role`       VARCHAR(20)  NOT NULL,                      -- driver / dispatcher / group
    `to_id`         INT(11)      NULL,                          -- driver_id / user_id; NULL when broadcast
    `body`          TEXT         NOT NULL,
    `d_id`          INT(11)      NULL,                          -- optional dispatch context
    `read_at`       DATETIME     NULL,
    `sent_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`msg_id`),
    KEY `idx_msg_to` (`to_role`, `to_id`, `read_at`),
    KEY `idx_msg_dispatch` (`d_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 15. push_subscription — Web Push (VAPID) for driver PWA
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `push_subscription` (
    `ps_id`         INT(11)      NOT NULL AUTO_INCREMENT,
    `driver_id`     INT(11)      NOT NULL,
    `endpoint`      VARCHAR(500) NOT NULL,
    `p256dh_key`    VARCHAR(255) NOT NULL,
    `auth_key`      VARCHAR(255) NOT NULL,
    `user_agent`    VARCHAR(255) NOT NULL DEFAULT '',
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at`  DATETIME     NULL,
    PRIMARY KEY (`ps_id`),
    UNIQUE KEY `uniq_endpoint` (`endpoint`(255)),
    KEY `idx_ps_driver` (`driver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 16. workflow_event — append-only audit of the 9-stage pipeline
-- Stages: order_created -> dispatcher_assigned -> driver_accepted ->
--         gate_cleared  -> en_route          -> delivered ->
--         pod_captured  -> billing_closed    -> client_notified
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `workflow_event` (
    `we_id`        INT(11)      NOT NULL AUTO_INCREMENT,
    `d_id`         INT(11)      NULL,
    `booking_no`   VARCHAR(200) NOT NULL DEFAULT '',
    `stage`        VARCHAR(40)  NOT NULL,
    `actor_role`   VARCHAR(20)  NOT NULL DEFAULT '',
    `actor_id`     INT(11)      NULL,
    `notes`        VARCHAR(500) NOT NULL DEFAULT '',
    `event_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`we_id`),
    KEY `idx_we_dispatch` (`d_id`, `event_at`),
    KEY `idx_we_booking`  (`booking_no`, `event_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed workflow_event with the current state of every existing dispatch
-- so historical bookings show up on the timeline.
INSERT INTO `workflow_event` (`d_id`, `booking_no`, `stage`, `actor_role`, `notes`, `event_at`)
SELECT
    d.`d_id`,
    d.`booking_no`,
    'dispatcher_assigned',
    'dispatcher',
    'Backfilled from existing dispatch row',
    d.`d_datetime`
FROM `dispatch` d
LEFT JOIN `workflow_event` we
    ON we.`d_id` = d.`d_id` AND we.`stage` = 'dispatcher_assigned'
WHERE we.`we_id` IS NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- End of Phase 1 migration.
-- Verify with:  SHOW TABLES LIKE 'gate_log';
-- =====================================================================
