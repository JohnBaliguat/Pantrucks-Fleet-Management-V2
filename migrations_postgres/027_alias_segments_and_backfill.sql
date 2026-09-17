-- =====================================================================
-- 027 — Category B segment aliases + re-run of the piece_rate backfill.
--
-- Gives variant / typo segment names the canonical segment's Empty/Loaded
-- reefer-van rates, then re-runs the (idempotent) backfill so those trips
-- get rated. Reversible via pt_piece_rate_backfill. Safe to re-run.
--
-- Aliases applied (Empty / Loaded):
--   SUMI                    -> SUMIFRU        605 / 405
--   FARM                    -> FARMIND        655 / 405
--   ABC CATEEL              -> ABC Cateel     860 / 1315
--   ABC LUPON               -> ABC Lupon      602 / 542
--   ABC PANTUKAN            -> ABC Pantukan   562 / 542
--   DOLEDOLE (typo)         -> DOLE           535 / 295
--   GOOD FARMERGOOD FARMER  -> GOOD FARMER    430 / 270
-- =====================================================================

BEGIN;

-- Backup table (created by 026; ensure it exists if running 027 standalone).
CREATE TABLE IF NOT EXISTS pt_piece_rate_backfill (
    trip_id    INTEGER PRIMARY KEY,
    old_rate   NUMERIC(12,2) NOT NULL,
    new_rate   NUMERIC(12,2) NOT NULL,
    segment    VARCHAR(200)  NOT NULL DEFAULT '',
    status     VARCHAR(10)   NOT NULL DEFAULT '',
    applied_at TIMESTAMP     NOT NULL DEFAULT NOW()
);

-- 1a) Insert alias rate rows that don't exist yet.
INSERT INTO trip_rates (segment, activity, base_rate, additional)
SELECT v.segment, v.activity, v.base, 0
FROM (VALUES
    ('SUMI',                  'Reefer Van - Empty',   605),
    ('SUMI',                  'Reefer Van - Loaded',  405),
    ('FARM',                  'Reefer Van - Empty',   655),
    ('FARM',                  'Reefer Van - Loaded',  405),
    ('ABC CATEEL',            'Reefer Van - Empty',   860),
    ('ABC CATEEL',            'Reefer Van - Loaded', 1315),
    ('ABC LUPON',             'Reefer Van - Empty',   602),
    ('ABC LUPON',             'Reefer Van - Loaded',  542),
    ('ABC PANTUKAN',          'Reefer Van - Empty',   562),
    ('ABC PANTUKAN',          'Reefer Van - Loaded',  542),
    ('DOLEDOLE',              'Reefer Van - Empty',   535),
    ('DOLEDOLE',              'Reefer Van - Loaded',  295),
    ('GOOD FARMERGOOD FARMER','Reefer Van - Empty',   430),
    ('GOOD FARMERGOOD FARMER','Reefer Van - Loaded',  270)
) AS v(segment, activity, base)
WHERE NOT EXISTS (
    SELECT 1 FROM trip_rates tr
    WHERE LOWER(TRIM(tr.segment)) = LOWER(v.segment)
      AND LOWER(TRIM(tr.activity)) = LOWER(v.activity)
);

-- 1b) For alias rows that already exist but are 0.00 (e.g. ABC LUPON/PANTUKAN),
--     set the real amount. Never overwrites an existing non-zero rate.
UPDATE trip_rates tr
   SET base_rate = v.base, additional = 0
FROM (VALUES
    ('SUMI',                  'Reefer Van - Empty',   605),
    ('SUMI',                  'Reefer Van - Loaded',  405),
    ('FARM',                  'Reefer Van - Empty',   655),
    ('FARM',                  'Reefer Van - Loaded',  405),
    ('ABC CATEEL',            'Reefer Van - Empty',   860),
    ('ABC CATEEL',            'Reefer Van - Loaded', 1315),
    ('ABC LUPON',             'Reefer Van - Empty',   602),
    ('ABC LUPON',             'Reefer Van - Loaded',  542),
    ('ABC PANTUKAN',          'Reefer Van - Empty',   562),
    ('ABC PANTUKAN',          'Reefer Van - Loaded',  542),
    ('DOLEDOLE',              'Reefer Van - Empty',   535),
    ('DOLEDOLE',              'Reefer Van - Loaded',  295),
    ('GOOD FARMERGOOD FARMER','Reefer Van - Empty',   430),
    ('GOOD FARMERGOOD FARMER','Reefer Van - Loaded',  270)
) AS v(segment, activity, base)
WHERE LOWER(TRIM(tr.segment)) = LOWER(v.segment)
  AND LOWER(TRIM(tr.activity)) = LOWER(v.activity)
  AND tr.total_rates = 0;

-- 2) Re-run the backfill (only piece_rate = 0, non-service, records to backup).
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

UPDATE trips t
   SET piece_rate = b.new_rate
  FROM pt_piece_rate_backfill b
 WHERE t.trip_id = b.trip_id
   AND t.piece_rate = 0
   AND b.new_rate > 0;

COMMIT;

-- ---------------------------------------------------------------------
-- VERIFY:
--   SELECT COUNT(*) rows, SUM(new_rate) pesos FROM pt_piece_rate_backfill;
--   SELECT COUNT(*) FILTER (WHERE piece_rate>0) rated,
--          COUNT(*) FILTER (WHERE piece_rate=0) unrated FROM trips;
-- REVERT (undo 026 + 027 rate stamping):
--   UPDATE trips t SET piece_rate = b.old_rate
--     FROM pt_piece_rate_backfill b WHERE t.trip_id = b.trip_id;
--   DROP TABLE pt_piece_rate_backfill;
--   (alias rate rows can be found with: SELECT * FROM trip_rates
--    WHERE segment IN ('SUMI','FARM','ABC CATEEL','DOLEDOLE','GOOD FARMERGOOD FARMER');)
-- ---------------------------------------------------------------------
