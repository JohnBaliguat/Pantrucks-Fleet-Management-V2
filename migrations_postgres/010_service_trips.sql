-- =====================================================================
-- Service Trips — dispatcher can assign a route without a booking.
-- PostgreSQL / Supabase port. Idempotent.
-- =====================================================================

ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS trip_purpose    VARCHAR(20)  NOT NULL DEFAULT 'Booking',
    ADD COLUMN IF NOT EXISTS service_reason  VARCHAR(60)  NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS service_remarks VARCHAR(255) NOT NULL DEFAULT '';

-- Enforce allowed values for trip_purpose at the DB layer (lightweight
-- equivalent of MySQL's ENUM('Booking','Service')). Use DO block so we
-- can guard against re-running.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'chk_trips_trip_purpose'
    ) THEN
        ALTER TABLE trips
            ADD CONSTRAINT chk_trips_trip_purpose
            CHECK (trip_purpose IN ('Booking', 'Service'));
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_trips_purpose ON trips(trip_purpose);
