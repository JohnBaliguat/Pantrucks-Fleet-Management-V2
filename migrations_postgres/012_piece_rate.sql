-- =====================================================================
-- Piece-rate / driver earnings feature.
-- Mirrors the E-Pantrucks model: a trip_rates lookup table indexed by
-- (segment, activity), and a piece_rate column stamped on each trip at
-- dispatch time so historical payroll stays correct when rates change.
-- =====================================================================

CREATE TABLE IF NOT EXISTS trip_rates (
    id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    segment     VARCHAR(200) NOT NULL DEFAULT '',
    activity    VARCHAR(200) NOT NULL,
    base_rate   NUMERIC(12,2) NOT NULL DEFAULT 0,
    additional  NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_rates NUMERIC(12,2) GENERATED ALWAYS AS (base_rate + additional) STORED,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_trip_rates_segment_activity ON trip_rates (LOWER(segment), LOWER(activity));
CREATE UNIQUE INDEX IF NOT EXISTS uniq_trip_rates_segment_activity ON trip_rates (LOWER(segment), LOWER(activity));

-- Stamp the looked-up rate on each trip so payroll reflects the rate that
-- was in effect at dispatch time, not whatever's current.
ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS piece_rate NUMERIC(12,2) NOT NULL DEFAULT 0;

CREATE INDEX IF NOT EXISTS idx_trips_piece_rate ON trips (piece_rate) WHERE piece_rate > 0;

-- ---------------------------------------------------------------------
-- BEFORE INSERT trigger: stamps piece_rate from trip_rates automatically
-- so we don't have to touch the 6 PHP files that INSERT into trips.
-- Only fills piece_rate when caller didn't already set it.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION trips_stamp_piece_rate() RETURNS TRIGGER AS $$
DECLARE
    seg  TEXT := COALESCE(NEW.trip_haulingsegment, '');
    act  TEXT := COALESCE(NEW.container_activity, '');
    rate NUMERIC;
BEGIN
    IF NEW.piece_rate IS NOT NULL AND NEW.piece_rate <> 0 THEN
        RETURN NEW;
    END IF;
    IF act = '' THEN
        NEW.piece_rate := 0;
        RETURN NEW;
    END IF;
    SELECT total_rates INTO rate
    FROM trip_rates
    WHERE LOWER(TRIM(activity)) = LOWER(TRIM(act))
      AND (segment = '' OR LOWER(TRIM(segment)) = LOWER(TRIM(seg)))
    ORDER BY CASE WHEN LOWER(TRIM(segment)) = LOWER(TRIM(seg)) THEN 0 ELSE 1 END,
             id DESC
    LIMIT 1;
    NEW.piece_rate := COALESCE(rate, 0);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_trips_stamp_piece_rate ON trips;
CREATE TRIGGER trg_trips_stamp_piece_rate
BEFORE INSERT ON trips
FOR EACH ROW EXECUTE FUNCTION trips_stamp_piece_rate();
