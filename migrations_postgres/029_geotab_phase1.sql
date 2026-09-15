-- =====================================================================
-- 029 — Geotab integration, Phase 1: live vehicle position.
--
-- Adds the device link + hardware telematics position to `units`, plus a
-- small state table so the poller (php/operations/geotab_poll.php) can pull
-- only what changed via the MyGeotab GetFeed version token.
--
-- Position source of truth becomes the truck's Geotab device; the driver
-- phone heartbeat (drivers.last_lat/last_lng) stays as a fallback for units
-- with no device linked.
--
-- Matching a Geotab device to a unit is done once in the admin mapping page
-- (admin/geotab-devices.php). Units in this system have no VIN recorded yet,
-- so `vin` is captured FROM Geotab at link time and stored here — it backfills
-- the missing VIN and lets future re-syncs auto-match.
--
-- Additive & idempotent (§10). Lowercase identifiers.
-- =====================================================================

ALTER TABLE units
    ADD COLUMN IF NOT EXISTS geotab_device_id VARCHAR(50)  NULL,
    ADD COLUMN IF NOT EXISTS vin              VARCHAR(20)  NULL,
    ADD COLUMN IF NOT EXISTS last_lat         NUMERIC(10,7) NULL,
    ADD COLUMN IF NOT EXISTS last_lng         NUMERIC(10,7) NULL,
    ADD COLUMN IF NOT EXISTS last_speed       NUMERIC(6,2)  NULL,
    ADD COLUMN IF NOT EXISTS bearing          NUMERIC(6,2)  NULL,
    ADD COLUMN IF NOT EXISTS last_position_at TIMESTAMP     NULL,
    ADD COLUMN IF NOT EXISTS is_communicating BOOLEAN NOT NULL DEFAULT FALSE;

-- One Geotab device maps to at most one unit. Partial index so the many
-- rows with a NULL device_id don't collide.
CREATE UNIQUE INDEX IF NOT EXISTS idx_units_geotab_device
    ON units (geotab_device_id)
    WHERE geotab_device_id IS NOT NULL;

-- GetFeed cursor + last-run health, one row per Geotab feed we poll.
CREATE TABLE IF NOT EXISTS geotab_feed_state (
    feed_name    VARCHAR(50) PRIMARY KEY,
    last_version VARCHAR(100) NULL,
    last_run_at  TIMESTAMP NULL,
    last_error   TEXT NULL
);
