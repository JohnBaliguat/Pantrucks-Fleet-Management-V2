-- =====================================================================
-- 026 — One-time BACKFILL of trips.piece_rate (data, not schema).
--
-- Fills piece_rate for trips that have none, by matching the trip's
-- SEGMENT + Empty/Loaded status (from trip_containerstat) to the rate
-- table's own Empty/Loaded rows. No amounts are invented.
--
-- SAFE:
--   * runs in one transaction
--   * only touches rows where piece_rate = 0 (never overwrites a real rate)
--   * skips Service trips
--   * records every change in pt_piece_rate_backfill so it is reversible
--   * safe to re-run (already-rated rows are skipped; backup upserts)
--
-- Expected: ~10,510 trips updated, ~₱4,771,795 total.
-- =====================================================================

BEGIN;

-- 1) Reversible backup / audit table.
CREATE TABLE IF NOT EXISTS pt_piece_rate_backfill (
    trip_id    INTEGER PRIMARY KEY,
    old_rate   NUMERIC(12,2) NOT NULL,
    new_rate   NUMERIC(12,2) NOT NULL,
    segment    VARCHAR(200)  NOT NULL DEFAULT '',
    status     VARCHAR(10)   NOT NULL DEFAULT '',
    applied_at TIMESTAMP     NOT NULL DEFAULT NOW()
);

-- 2) Record exactly what will change.
INSERT INTO pt_piece_rate_backfill (trip_id, old_rate, new_rate, segment, status)
SELECT t.trip_id, t.piece_rate, r.rate, t.trip_haulingsegment,
       CASE WHEN t.trip_containerstat ILIKE '%loaded%' THEN 'loaded' ELSE 'empty' END
FROM trips t
JOIN LATERAL (
    SELECT tr.total_rates AS rate
    FROM trip_rates tr
    WHERE LOWER(TRIM(tr.segment)) = LOWER(TRIM(t.trip_haulingsegment))
      AND tr.total_rates > 0
      AND ( (t.trip_containerstat ILIKE '%loaded%' AND LOWER(tr.activity) LIKE '%loaded')
         OR (t.trip_containerstat ILIKE '%empty%'  AND LOWER(tr.activity) LIKE '%empty') )
    ORDER BY tr.total_rates DESC
    LIMIT 1
) r ON TRUE
WHERE t.piece_rate = 0
  AND (t.trip_purpose IS NULL OR t.trip_purpose <> 'Service')
  AND (t.trip_containerstat ILIKE '%loaded%' OR t.trip_containerstat ILIKE '%empty%')
ON CONFLICT (trip_id) DO NOTHING;

-- 3) Apply the rates.
UPDATE trips t
   SET piece_rate = b.new_rate
  FROM pt_piece_rate_backfill b
 WHERE t.trip_id = b.trip_id
   AND t.piece_rate = 0
   AND b.new_rate > 0;

COMMIT;

-- ---------------------------------------------------------------------
-- VERIFY (run after committing):
--   SELECT COUNT(*) AS rows, SUM(new_rate) AS pesos FROM pt_piece_rate_backfill;
--   SELECT COUNT(*) FILTER (WHERE piece_rate>0) rated,
--          COUNT(*) FILTER (WHERE piece_rate=0) unrated FROM trips;
--
-- REVERT (undo everything this did):
--   UPDATE trips t SET piece_rate = b.old_rate
--     FROM pt_piece_rate_backfill b WHERE t.trip_id = b.trip_id;
--   DROP TABLE pt_piece_rate_backfill;
-- ---------------------------------------------------------------------
