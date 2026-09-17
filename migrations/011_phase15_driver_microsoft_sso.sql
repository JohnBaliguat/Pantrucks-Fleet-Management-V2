-- =====================================================================
-- Phase 15 — Microsoft (Entra ID) SSO for drivers.
-- Adds the binding columns that let us recognise a Microsoft-signed-in
-- driver as the same row as an existing drivers record (matched by
-- driver_email on first login, then by microsoft_oid afterwards).
-- =====================================================================
USE `ptsifleet_db2`;

ALTER TABLE `drivers`
    ADD COLUMN IF NOT EXISTS `microsoft_oid`        VARCHAR(64)  NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `microsoft_tenant_id`  VARCHAR(64)  NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `microsoft_linked_at`  DATETIME     NULL,
    ADD KEY IF NOT EXISTS `idx_drivers_ms_oid` (`microsoft_oid`);

-- =====================================================================
-- Verify with: DESCRIBE drivers;
-- =====================================================================
