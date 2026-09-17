-- =====================================================================
-- Phase 7 — Shift end, POD verification gate, equipment location tracking.
-- PostgreSQL / Supabase port. Idempotent.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. drivers — shift end timestamp
-- ---------------------------------------------------------------------
ALTER TABLE drivers
    ADD COLUMN IF NOT EXISTS shift_ended_at TIMESTAMP NULL;

-- ---------------------------------------------------------------------
-- 2. driver_shift
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS driver_shift (
    ds_id         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    driver_id     INTEGER       NOT NULL,
    truck_code    VARCHAR(50)   NOT NULL DEFAULT '',
    started_at    TIMESTAMP     NOT NULL,
    ended_at      TIMESTAMP     NULL,
    machine_hours DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pdc_id        INTEGER       NULL,
    notes         VARCHAR(255)  NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_ds_driver_open ON driver_shift(driver_id, ended_at);
CREATE INDEX IF NOT EXISTS idx_ds_truck       ON driver_shift(truck_code);

-- ---------------------------------------------------------------------
-- 3. dispatch — trip timer + completion stamp + verification audit
-- ---------------------------------------------------------------------
ALTER TABLE dispatch
    ADD COLUMN IF NOT EXISTS trip_started_at     TIMESTAMP    NULL,
    ADD COLUMN IF NOT EXISTS trip_completed_at   TIMESTAMP    NULL,
    ADD COLUMN IF NOT EXISTS verified_by         INTEGER      NULL,
    ADD COLUMN IF NOT EXISTS verified_at         TIMESTAMP    NULL,
    ADD COLUMN IF NOT EXISTS verification_notes  VARCHAR(500) NOT NULL DEFAULT '';

-- Backfill trip_started_at from driver_accepted_at
UPDATE dispatch
SET trip_started_at = driver_accepted_at
WHERE trip_started_at IS NULL AND driver_accepted_at IS NOT NULL;

-- ---------------------------------------------------------------------
-- 4. units — current location for trucks AND gensets
-- ---------------------------------------------------------------------
ALTER TABLE units
    ADD COLUMN IF NOT EXISTS current_location            VARCHAR(100) NOT NULL DEFAULT 'PTSI Base',
    ADD COLUMN IF NOT EXISTS current_location_updated_at TIMESTAMP    NULL;

-- ---------------------------------------------------------------------
-- 5. trailer — current base location
-- ---------------------------------------------------------------------
ALTER TABLE trailer
    ADD COLUMN IF NOT EXISTS current_base            VARCHAR(100) NOT NULL DEFAULT 'PTSI Base',
    ADD COLUMN IF NOT EXISTS current_base_updated_at TIMESTAMP    NULL;

-- ---------------------------------------------------------------------
-- 6. pod_capture — verification fields
-- ---------------------------------------------------------------------
ALTER TABLE pod_capture
    ADD COLUMN IF NOT EXISTS verified_by         INTEGER      NULL,
    ADD COLUMN IF NOT EXISTS verified_at         TIMESTAMP    NULL,
    ADD COLUMN IF NOT EXISTS verification_notes  VARCHAR(500) NOT NULL DEFAULT '';
