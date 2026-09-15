-- =====================================================================
-- 031 — Geotab integration, Phase 3: vehicle health (faults + odometer).
--
-- Pulls engine fault codes (FaultData) and diagnostics (odometer / engine
-- hours) from Geotab into the fleet system so the maintenance module can act
-- on hardware signals: surface active faults per truck and enable
-- distance/hours-based preventive maintenance.
--
-- Faults are surfaced only (an admin dismisses them or blocks the unit via
-- the existing maintenance flow) — nothing auto-pulls a truck from service.
--
-- Additive & idempotent (§10). Lowercase identifiers.
-- =====================================================================

-- Latest diagnostics on each unit.
ALTER TABLE units
    ADD COLUMN IF NOT EXISTS odometer_km    NUMERIC(12,1) NULL,
    ADD COLUMN IF NOT EXISTS engine_hours   NUMERIC(12,1) NULL,
    ADD COLUMN IF NOT EXISTS diagnostics_at TIMESTAMP     NULL;

-- Engine fault codes from Geotab FaultData, upserted by Geotab's fault id.
CREATE TABLE IF NOT EXISTS geotab_fault (
    fault_id      VARCHAR(60) PRIMARY KEY,   -- Geotab FaultData.id
    unit_id       INTEGER      NULL,
    unit_name     VARCHAR(200) NOT NULL DEFAULT '',
    device_id     VARCHAR(50)  NOT NULL DEFAULT '',
    diagnostic_id VARCHAR(60)  NOT NULL DEFAULT '',
    code          VARCHAR(60)  NOT NULL DEFAULT '',
    description   VARCHAR(300) NOT NULL DEFAULT '',
    fault_state   VARCHAR(30)  NOT NULL DEFAULT '',   -- Active / Inactive / PendingDtc …
    active        BOOLEAN      NOT NULL DEFAULT TRUE,
    occurrences   INTEGER      NOT NULL DEFAULT 1,
    occurred_at   TIMESTAMP    NULL,                  -- activeFrom / dateTime
    cleared_at    TIMESTAMP    NULL,                  -- activeTo
    dismissed     BOOLEAN      NOT NULL DEFAULT FALSE,
    dismissed_by  INTEGER      NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_geotab_fault_unit   ON geotab_fault (unit_id, active, dismissed);
CREATE INDEX IF NOT EXISTS idx_geotab_fault_device ON geotab_fault (device_id);
CREATE INDEX IF NOT EXISTS idx_geotab_fault_active ON geotab_fault (active, dismissed);
