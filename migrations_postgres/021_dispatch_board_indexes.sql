-- =====================================================================
-- Dispatch Board (dispatch-tiles) performance indexes.
--
-- The board feed (php/fetch/dispatch_tiles.php) runs correlated subqueries
-- against the dispatch table for every booking and every on-shift driver:
--   • customer summary: latest workflow_stage per booking_no
--       (SELECT workflow_stage FROM dispatch WHERE booking_no = ? ORDER BY d_id DESC LIMIT 1)
--   • driver tiles: latest active dispatch id + active-trip count per driver
--       (WHERE driver_id = ? AND workflow_stage IN (...) ORDER BY d_id DESC)
--
-- The dispatch table had no index on booking_no or driver_id, so each of
-- those subqueries did a sequential scan. With ~3.5k bookings × ~5.4k
-- dispatches the customer-summary query alone took ~4.6s per 60s poll.
-- These two indexes turn the subqueries into index scans (measured:
-- customer summary 4.6s -> ~0.2s, driver tiles 0.54s -> ~0.17s).
--
-- Additive + idempotent.
-- =====================================================================

-- Latest dispatch per booking_no (customer summary + any booking_no lookup).
CREATE INDEX IF NOT EXISTS idx_dispatch_booking_did
    ON dispatch (booking_no, d_id);

-- Active dispatch lookups per driver, ordered by recency (driver tiles).
CREATE INDEX IF NOT EXISTS idx_dispatch_driver_stage
    ON dispatch (driver_id, workflow_stage, d_id);
