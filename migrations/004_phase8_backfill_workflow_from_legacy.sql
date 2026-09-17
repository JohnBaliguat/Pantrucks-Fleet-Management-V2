-- =====================================================================
-- Phase 8 — Backfill workflow_stage for legacy-completed dispatches.
--
-- Phase 1 seeded every existing dispatch with workflow_stage =
-- 'dispatcher_assigned'. That made the new workflow timeline work
-- for fresh bookings, but it left 22k legacy-completed dispatches
-- stuck at the seed value, so the new Billing page (which filters
-- on workflow_stage IN 'delivered','pod_captured','billing_closed')
-- showed nothing.
--
-- Heuristic — flip a dispatch to 'pod_captured' iff:
--   • It's still at the seed default 'dispatcher_assigned', AND
--   • Every one of its trips is trip_status = 'Done', AND
--   • There's at least one trip (otherwise it's an orphan dispatch).
--
-- We use 'pod_captured' rather than 'delivered' because legacy trips
-- have no actual pod_capture row to verify; treating them as already
-- captured-and-verified is the most honest mapping for billing.
--
-- Idempotent: the WHERE clause excludes rows already migrated.
-- =====================================================================
USE `ptsifleet_db2`;

-- 1) Bulk update the workflow_stage and stamp completion timestamps.
UPDATE `dispatch` d
SET d.`workflow_stage`      = 'pod_captured',
    d.`trip_started_at`     = COALESCE(d.`trip_started_at`,    d.`d_datetime`),
    d.`trip_completed_at`   = COALESCE(d.`trip_completed_at`,  d.`d_datetime`),
    d.`workflow_updated_at` = COALESCE(d.`workflow_updated_at`, d.`d_datetime`)
WHERE d.`workflow_stage` = 'dispatcher_assigned'
  AND EXISTS (
        SELECT 1 FROM `trips` t
        WHERE t.`d_id` = d.`d_id` AND t.`trip_status` = 'Done'
      )
  AND NOT EXISTS (
        SELECT 1 FROM `trips` t
        WHERE t.`d_id` = d.`d_id`
          AND t.`trip_status` NOT IN ('Done', '')
      );

-- 2) Append-only audit row per backfilled dispatch so the workflow
--    timeline page surfaces them as well. Skip dispatches that
--    already have a 'pod_captured' event so re-running stays clean.
INSERT INTO `workflow_event` (`d_id`, `booking_no`, `stage`, `actor_role`, `notes`, `event_at`)
SELECT d.`d_id`,
       d.`booking_no`,
       'pod_captured',
       'system',
       'Backfilled from legacy trips.trip_status = Done',
       COALESCE(d.`trip_completed_at`, d.`d_datetime`)
FROM `dispatch` d
LEFT JOIN `workflow_event` we
  ON we.`d_id` = d.`d_id` AND we.`stage` = 'pod_captured'
WHERE d.`workflow_stage` = 'pod_captured'
  AND we.`we_id` IS NULL;

-- =====================================================================
-- Verify with:
--   SELECT workflow_stage, COUNT(*) FROM dispatch GROUP BY workflow_stage;
-- =====================================================================
