-- =====================================================================
-- Phase 11 — Client portal foundations:
--   customer_segment, container_status lifecycle, Client role
-- Database: ptsifleet_db2
-- Idempotent: safe to run multiple times.
-- =====================================================================
--
-- This migration is ADDITIVE only. No drops, no destructive ALTERs.
-- Existing flows keep working unchanged. Pre-existing rows keep their
-- current container_status / costumer values; the new enum is enforced
-- at the application layer (php/crud/add/addbooking.php) rather than as
-- a MySQL ENUM constraint, so legacy values ('EMPTY', 'LOADED', 'N/A')
-- continue to read fine while new writes pick from the 8 lifecycle
-- states below.
--
-- Adds:
--   customer.customer_segment        — segment a client belongs to (e.g.
--                                      DOLE, SUMITOMO, CTH, DM, FARM, ABC).
--                                      Used by client-portal auto-populate
--                                      and unifies the per-segment
--                                      monitoring pages that currently
--                                      string-match on customer code.
--   booking.customer_segment         — denormalised snapshot stamped on
--                                      the booking at creation time so
--                                      historical reports stay coherent
--                                      if a customer's segment changes
--                                      later.
--   user_type = 'Client'             — new role for the client portal
--                                      (Phase 12). user_type is VARCHAR
--                                      so no DDL needed; documented here.
--   user.customer_code               — links a Client login to a customer
--                                      row so the portal can auto-populate
--                                      Client Name + Customer Segment.
--
-- Container status lifecycle (enforced at app layer):
--   Empty                          (default — booking just created)
--   Empty Container Pickup         (dispatcher confirmed pickup started)
--   Empty Container On Trip        (driver en-route with empty container)
--   Empty Container Delivered      (empty dropped — booking can be
--                                   re-flagged Loaded for a return trip)
--   Loaded
--   Loaded Container Pickup
--   Loaded Container On Trip
--   Loaded Container Delivered
-- =====================================================================

USE `ptsifleet_db2`;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. customer — segment attribute for client portal + monitoring
-- ---------------------------------------------------------------------
ALTER TABLE `customer`
    ADD COLUMN IF NOT EXISTS `customer_segment` VARCHAR(50) NOT NULL DEFAULT '' AFTER `customer_name`,
    ADD KEY IF NOT EXISTS `idx_customer_segment` (`customer_segment`);

-- Backfill: where the customer code matches a known segment monitoring
-- page (ABC, DOLE, SUMITOMO, CTH, DM, FARM), seed the segment from the
-- code. Customers without an obvious mapping keep the empty default;
-- ops can fill those in via dispatcher/segment.php.
UPDATE `customer`
SET `customer_segment` = UPPER(TRIM(`customer_code`))
WHERE `customer_segment` = ''
  AND UPPER(TRIM(`customer_code`)) IN ('ABC', 'DOLE', 'SUMI', 'CTH', 'DM', 'FARM');

-- ---------------------------------------------------------------------
-- 2. booking — snapshot the segment at creation time
-- ---------------------------------------------------------------------
ALTER TABLE `booking`
    ADD COLUMN IF NOT EXISTS `customer_segment` VARCHAR(50) NOT NULL DEFAULT '' AFTER `costumer`,
    ADD KEY IF NOT EXISTS `idx_booking_customer_segment` (`customer_segment`);

-- Backfill existing bookings from the customer table.
UPDATE `booking` b
JOIN `customer` c ON c.`customer_code` = b.`costumer`
SET b.`customer_segment` = c.`customer_segment`
WHERE b.`customer_segment` = ''
  AND c.`customer_segment` <> '';

-- ---------------------------------------------------------------------
-- 3. user — link Client logins to a customer row
-- ---------------------------------------------------------------------
-- user_type stays VARCHAR; the new value 'Client' is accepted by the
-- application layer (admin/user.php picker + login-php.php route).
ALTER TABLE `user`
    ADD COLUMN IF NOT EXISTS `customer_code` VARCHAR(100) NOT NULL DEFAULT '' AFTER `user_assignLocation`,
    ADD KEY IF NOT EXISTS `idx_user_customer_code` (`customer_code`);

-- ---------------------------------------------------------------------
-- 4. (No DDL — documented enum.) Allowed values for booking.container_status:
--      'Empty', 'Empty Container Pickup',
--      'Empty Container On Trip', 'Empty Container Delivered',
--      'Loaded', 'Loaded Container Pickup',
--      'Loaded Container On Trip', 'Loaded Container Delivered'
--
--    Legacy values 'EMPTY' / 'LOADED' / 'N/A' remain readable; the
--    application normalises them to 'Empty' / 'Loaded' on display so
--    the dashboard tile colours render correctly without a data fix.
-- ---------------------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- End of Phase 11 migration.
-- Verify with:
--   SHOW COLUMNS FROM customer LIKE 'customer_segment';
--   SHOW COLUMNS FROM booking  LIKE 'customer_segment';
--   SHOW COLUMNS FROM user     LIKE 'customer_code';
-- =====================================================================
