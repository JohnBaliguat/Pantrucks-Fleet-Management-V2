-- =====================================================================
-- Phase 12 — Scheduled maintenance support for unit_maintenance.
-- PostgreSQL / Supabase port. Idempotent.
-- =====================================================================

ALTER TABLE unit_maintenance
    ADD COLUMN IF NOT EXISTS scheduled_start_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS activated_at       TIMESTAMP NULL;

CREATE INDEX IF NOT EXISTS idx_um_scheduled_start ON unit_maintenance(scheduled_start_at);
