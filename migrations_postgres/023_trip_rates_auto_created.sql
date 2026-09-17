-- =====================================================================
-- Flag auto-created (placeholder) trip_rates rows.
--
-- When a dispatch is assigned and the trip's SKU has no matching rate, the
-- app inserts a 0.00 placeholder so the admin sees the missing SKU on the
-- Trip Rates page and can price it. This column marks those rows so the UI
-- can badge them "needs rate" and distinguish them from hand-added rows.
--
-- Additive + idempotent.
-- =====================================================================

ALTER TABLE trip_rates
    ADD COLUMN IF NOT EXISTS auto_created BOOLEAN NOT NULL DEFAULT FALSE;
