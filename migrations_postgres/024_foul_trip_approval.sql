-- =====================================================================
-- Foul-trip payroll approval.
--
-- A foul trip (a leg cancelled AFTER assignment) is not paid automatically:
-- a dispatch admin must confirm it before its rate counts toward driver
-- earnings. These columns record that approval. Clean cancellations
-- (segment_status = 'Cancelled', foul_trip = FALSE) are never paid and need
-- no approval.
--
-- Additive + idempotent.
-- =====================================================================

ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS foul_approved    BOOLEAN   NOT NULL DEFAULT FALSE;
ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS foul_approved_by INTEGER   NULL;
ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS foul_approved_at TIMESTAMP NULL;

-- Pending-approval lookups (foul, not yet approved).
CREATE INDEX IF NOT EXISTS idx_trips_foul_pending
    ON trips (foul_trip, foul_approved) WHERE foul_trip = TRUE;
