-- =====================================================================
-- 028 — Fix the piece_rate auto-stamp so NEW trips get rated at dispatch.
--
-- The original trigger (012) matched trip_rates.activity against
-- trips.container_activity (WITHDRAW / DELIVER) — which never matches the
-- rate table (keyed on Empty/Loaded), so every new trip landed at ₱0.
--
-- This replaces it with the same rule the backfill uses:
--   rate = trip_rates row for the trip's SEGMENT whose activity ends in the
--          trip's Empty/Loaded status (from trip_containerstat), total > 0.
--
-- Fires BEFORE INSERT OR UPDATE so the rate is stamped at dispatch, and also
-- filled in later if the container status is set after the trip is created.
-- Never overrides an explicitly-set rate (payroll assignment / import) and
-- skips Service trips. Idempotent.
-- =====================================================================

CREATE OR REPLACE FUNCTION trips_stamp_piece_rate() RETURNS TRIGGER AS $$
DECLARE
    seg  TEXT := btrim(COALESCE(NEW.trip_haulingsegment, ''));
    stat TEXT := lower(COALESCE(NEW.trip_containerstat, ''));
    st   TEXT;
    rate NUMERIC;
BEGIN
    -- Respect a rate the caller set on purpose (Payroll coupon, manual, import).
    IF NEW.piece_rate IS NOT NULL AND NEW.piece_rate <> 0 THEN
        RETURN NEW;
    END IF;

    -- Service trips are non-revenue.
    IF COALESCE(NEW.trip_purpose, '') = 'Service' THEN
        NEW.piece_rate := 0;
        RETURN NEW;
    END IF;

    -- Determine Empty / Loaded (first word of e.g. "Empty Container Delivered").
    IF stat LIKE '%loaded%' THEN
        st := 'loaded';
    ELSIF stat LIKE '%empty%' THEN
        st := 'empty';
    ELSE
        NEW.piece_rate := 0;      -- no status yet — nothing to key on
        RETURN NEW;
    END IF;

    SELECT tr.total_rates INTO rate
    FROM trip_rates tr
    WHERE lower(btrim(tr.segment)) = lower(seg)
      AND tr.total_rates > 0
      AND ( (st = 'loaded' AND lower(tr.activity) LIKE '%loaded')
         OR (st = 'empty'  AND lower(tr.activity) LIKE '%empty') )
    ORDER BY tr.total_rates DESC
    LIMIT 1;

    NEW.piece_rate := COALESCE(rate, 0);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Re-point the trigger to fire on INSERT and UPDATE.
DROP TRIGGER IF EXISTS trg_trips_stamp_piece_rate ON trips;
CREATE TRIGGER trg_trips_stamp_piece_rate
BEFORE INSERT OR UPDATE ON trips
FOR EACH ROW EXECUTE FUNCTION trips_stamp_piece_rate();

-- ---------------------------------------------------------------------
-- TEST (optional, safe — rolls back):
--   BEGIN;
--   INSERT INTO trips (d_id, trip_type, costumer, trip_container, container_activity,
--                      trip_containerstat, trip_haulingsegment, trip_haulingtype,
--                      trip_from, trip_to, km_run, required_date, trip_status)
--   VALUES (0,'Trip 1','TEST','TESTCTN','WITHDRAW','Loaded','DOLE','', 'A','B','0', CURRENT_DATE,'Active')
--   RETURNING trip_id, piece_rate;   -- expect piece_rate = 295 (DOLE Loaded)
--   ROLLBACK;
-- ---------------------------------------------------------------------
