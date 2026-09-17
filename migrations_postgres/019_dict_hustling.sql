-- =====================================================================
-- DICT Hustling — one dispatch per driver per day, driver-added containers.
--
-- A hustling dispatch has no pre-set trip legs; the driver logs each
-- container (a child trips row) through the day. is_hustling marks the
-- dispatch; hustling_date is the operating day (one open day per driver).
--
-- Additive + idempotent.
-- =====================================================================

ALTER TABLE dispatch
    ADD COLUMN IF NOT EXISTS is_hustling BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE dispatch
    ADD COLUMN IF NOT EXISTS hustling_date DATE NULL;

CREATE INDEX IF NOT EXISTS idx_dispatch_hustling
    ON dispatch (driver_id, hustling_date) WHERE is_hustling;

-- Allow 'Hustling' as a trip purpose (the constraints only permitted
-- Booking / Service). Hustling containers are billable piece-rate legs, so
-- they must NOT be filed as 'Service' (which is treated as internal/non-billed).
-- Two redundant check constraints existed; drop both and keep one canonical.
ALTER TABLE trips DROP CONSTRAINT IF EXISTS chk_trips_trip_purpose;
ALTER TABLE trips DROP CONSTRAINT IF EXISTS trips_trip_purpose_check;
ALTER TABLE trips ADD CONSTRAINT chk_trips_trip_purpose
    CHECK (trip_purpose IN ('Booking', 'Service', 'Hustling'));

-- Flat per-container hustling rate, resolvable by trip_key = 'DICT HUSTLING'.
-- Seed a row if none exists yet (keeps the existing Hustling row's amount if
-- present; otherwise defaults to 85.00, which the admin can edit on Trip Rates).
INSERT INTO trip_rates (segment, activity, trip_key, base_rate, additional)
SELECT 'Hustling', 'DICT Hustling (per container)', 'DICT HUSTLING',
       COALESCE((SELECT total_rates FROM trip_rates
                  WHERE LOWER(segment) = 'hustling' AND LOWER(activity) LIKE '%hustling%'
                  ORDER BY id DESC LIMIT 1), 85.00),
       0
WHERE NOT EXISTS (
    SELECT 1 FROM trip_rates WHERE UPPER(TRIM(trip_key)) = 'DICT HUSTLING'
);
