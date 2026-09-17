-- =====================================================================
-- Direct rate lookup by a trip's Segment.Activity.
--
-- The trips' own fields (trip_haulingsegment = "GOOD FARMER",
-- container_activity = "WITHDRAW") don't match the curated
-- trip_rates.(segment, activity) codes, so rates rarely auto-stamp.
--
-- trip_key holds the trip-side lookup value — the admin enters the
-- "Segment.Activity" combination this rate applies to (e.g.
-- "GOOD FARMER.WITHDRAW"). The coupon / rate lookup builds the same key
-- from the trip and matches it here to display the piece rate.
--
-- Additive + idempotent.
-- =====================================================================

ALTER TABLE trip_rates
    ADD COLUMN IF NOT EXISTS trip_key VARCHAR(255) NOT NULL DEFAULT '';

-- Case-insensitive lookup on the populated keys.
CREATE INDEX IF NOT EXISTS idx_trip_rates_trip_key
    ON trip_rates (LOWER(TRIM(trip_key))) WHERE trip_key <> '';
