-- Phase 16 — Dispatch-Admin truck block.
--
-- A lightweight, dispatch-level block that is INDEPENDENT of the Maintenance
-- block (units.maintenance_blocked / unit_maintenance tickets). A Dispatch
-- Admin can block a truck so it can no longer be assigned to a booking, and
-- unblock it later. It does not change unit_status and does not appear in the
-- Maintenance "Blocked Units" views.
--
-- Enforced server-side in every assign flow and hidden from the truck picker.

ALTER TABLE units ADD COLUMN IF NOT EXISTS dispatch_blocked      BOOLEAN      NOT NULL DEFAULT FALSE;
ALTER TABLE units ADD COLUMN IF NOT EXISTS dispatch_block_reason VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE units ADD COLUMN IF NOT EXISTS dispatch_blocked_by   INTEGER      NOT NULL DEFAULT 0;
ALTER TABLE units ADD COLUMN IF NOT EXISTS dispatch_blocked_at   TIMESTAMP    NULL;
