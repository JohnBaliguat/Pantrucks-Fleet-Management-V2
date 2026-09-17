-- =====================================================================
-- Phase 9 — Microsoft (Entra ID) work-account SSO.
-- Adds the binding column that lets us recognise a Microsoft-signed-in
-- user as the same row as an existing username/password user.
-- =====================================================================
USE `ptsifleet_db2`;

ALTER TABLE `user`
    ADD COLUMN IF NOT EXISTS `microsoft_oid`        VARCHAR(64)  NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `microsoft_tenant_id`  VARCHAR(64)  NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `microsoft_email`      VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `microsoft_linked_at`  DATETIME     NULL,
    ADD KEY IF NOT EXISTS `idx_user_ms_oid` (`microsoft_oid`);

-- =====================================================================
-- Verify with: DESCRIBE user;
-- =====================================================================
