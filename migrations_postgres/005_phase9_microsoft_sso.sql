-- =====================================================================
-- Phase 9 — Microsoft (Entra ID) work-account SSO for users.
-- PostgreSQL / Supabase port. Idempotent.
--
-- NOTE: `user` is a reserved word in PostgreSQL — must be double-quoted.
-- =====================================================================

ALTER TABLE "user"
    ADD COLUMN IF NOT EXISTS microsoft_oid       VARCHAR(64)  NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS microsoft_tenant_id VARCHAR(64)  NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS microsoft_email     VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS microsoft_linked_at TIMESTAMP    NULL;

CREATE INDEX IF NOT EXISTS idx_user_ms_oid ON "user"(microsoft_oid);
