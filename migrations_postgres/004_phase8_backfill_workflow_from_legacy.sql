-- =====================================================================
-- Phase 8 — Backfill workflow_stage for legacy-completed dispatches.
-- PostgreSQL / Supabase port. Idempotent.
-- =====================================================================

-- 1) Bulk update the workflow_stage and stamp completion timestamps.
UPDATE dispatch
SET workflow_stage      = 'pod_captured',
    trip_started_at     = COALESCE(trip_started_at,     d_datetime),
    trip_completed_at   = COALESCE(trip_completed_at,   d_datetime),
    workflow_updated_at = COALESCE(workflow_updated_at, d_datetime)
WHERE workflow_stage = 'dispatcher_assigned'
  AND EXISTS (
        SELECT 1 FROM trips t
        WHERE t.d_id = dispatch.d_id AND t.trip_status = 'Done'
      )
  AND NOT EXISTS (
        SELECT 1 FROM trips t
        WHERE t.d_id = dispatch.d_id
          AND t.trip_status NOT IN ('Done', '')
      );

-- 2) Append-only audit row per backfilled dispatch
INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, notes, event_at)
SELECT d.d_id,
       d.booking_no,
       'pod_captured',
       'system',
       'Backfilled from legacy trips.trip_status = Done',
       COALESCE(d.trip_completed_at, d.d_datetime)
FROM dispatch d
LEFT JOIN workflow_event we
  ON we.d_id = d.d_id AND we.stage = 'pod_captured'
WHERE d.workflow_stage = 'pod_captured'
  AND we.we_id IS NULL;
