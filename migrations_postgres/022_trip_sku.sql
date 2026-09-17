-- =====================================================================
-- Dedicated SKU column on trips.
--
-- The trip's SKU (Segment.HaulingType.<Loaded|Empty>) previously had no home
-- of its own — coded SKUs were landing in container_activity (via the payroll
-- rating flow), colliding with the operational verb it is meant to hold
-- (WITHDRAW / DELIVER). This gives the SKU its own column, derived from the
-- trip's own fields so it is always present and never depends on payroll.
--
-- container_activity is left untouched by this migration.
--
-- The container status is reduced to its first word, normalised to
-- Loaded / Empty — the piece rate only depends on that ("Empty Container
-- Delivered" -> "Empty"). An all-blank trip yields '' (nothing to key on).
--
-- Additive + idempotent.
-- =====================================================================

ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS trip_sku VARCHAR(300) NOT NULL DEFAULT '';

-- Pure builder — used by both the trigger and the backfill so the rule lives
-- in one place.
CREATE OR REPLACE FUNCTION fleet_trip_sku(seg TEXT, htype TEXT, stat TEXT)
RETURNS TEXT AS $$
DECLARE
    s  TEXT := btrim(COALESCE(seg,   ''));
    h  TEXT := btrim(COALESCE(htype, ''));
    w  TEXT := split_part(btrim(COALESCE(stat, '')), ' ', 1);   -- first word only
    cs TEXT;
BEGIN
    cs := CASE
            WHEN upper(w) = 'EMPTY'  THEN 'Empty'
            WHEN upper(w) = 'LOADED' THEN 'Loaded'
            ELSE w
          END;
    IF s = '' AND h = '' AND cs = '' THEN
        RETURN '';
    END IF;
    RETURN s || '.' || h || '.' || cs;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

-- Keep trip_sku in sync with the source fields on every write.
CREATE OR REPLACE FUNCTION fleet_stamp_trip_sku() RETURNS TRIGGER AS $$
BEGIN
    NEW.trip_sku := fleet_trip_sku(NEW.trip_haulingsegment, NEW.trip_haulingtype, NEW.trip_containerstat);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_trips_stamp_sku ON trips;
CREATE TRIGGER trg_trips_stamp_sku
BEFORE INSERT OR UPDATE ON trips
FOR EACH ROW EXECUTE FUNCTION fleet_stamp_trip_sku();

-- Backfill existing rows (only those whose stored value differs).
UPDATE trips
   SET trip_sku = fleet_trip_sku(trip_haulingsegment, trip_haulingtype, trip_containerstat)
 WHERE trip_sku IS DISTINCT FROM fleet_trip_sku(trip_haulingsegment, trip_haulingtype, trip_containerstat);
