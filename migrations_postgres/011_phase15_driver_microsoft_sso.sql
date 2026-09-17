-- =====================================================================
-- Phase 15 — Microsoft (Entra ID) SSO for drivers.
-- PostgreSQL / Supabase port. Idempotent.
-- =====================================================================

ALTER TABLE drivers
    ADD COLUMN IF NOT EXISTS microsoft_oid       VARCHAR(64) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS microsoft_tenant_id VARCHAR(64) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS microsoft_linked_at TIMESTAMP   NULL;

CREATE INDEX IF NOT EXISTS idx_drivers_ms_oid ON drivers(microsoft_oid);
