-- =====================================================================
-- 033 — Geotab integration, Phase 5: driver behavior -> HR violations.
--
-- Pulls Geotab ExceptionEvents (speeding, harsh braking, idling, seatbelt,
-- …) into geotab_driver_event, attributed to a driver. HR reviews them and
-- converts the ones that warrant action into the existing violation_record
-- pipeline (which blocks the driver + notifies via WhatsApp). Nothing
-- auto-creates a violation.
--
-- Driver attribution: prefer a Geotab-user link (drivers.geotab_user_id);
-- fall back to the unit's currently-assigned driver when no driver key.
--
-- Additive & idempotent (§10). Lowercase identifiers.
-- =====================================================================

-- Optional link from our driver to a Geotab User/Driver id.
ALTER TABLE drivers
    ADD COLUMN IF NOT EXISTS geotab_user_id VARCHAR(60) NULL;
CREATE INDEX IF NOT EXISTS idx_drivers_geotab_user ON drivers (geotab_user_id);

CREATE TABLE IF NOT EXISTS geotab_driver_event (
    event_id        VARCHAR(60) PRIMARY KEY,   -- Geotab ExceptionEvent.id
    device_id       VARCHAR(50)  NOT NULL DEFAULT '',
    unit_id         INTEGER      NULL,
    unit_name       VARCHAR(200) NOT NULL DEFAULT '',
    geotab_driver_id VARCHAR(60) NOT NULL DEFAULT '',
    driver_id       INTEGER      NULL,          -- attributed fleet driver
    rule_id         VARCHAR(60)  NOT NULL DEFAULT '',
    rule_name       VARCHAR(200) NOT NULL DEFAULT '',
    occurred_at     TIMESTAMP    NULL,          -- activeFrom
    ended_at        TIMESTAMP    NULL,          -- activeTo
    duration_s      INTEGER      NULL,
    distance_km     NUMERIC(10,2) NULL,
    dismissed       BOOLEAN      NOT NULL DEFAULT FALSE,
    violation_id    INTEGER      NULL,          -- set once converted to a violation
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_gde_driver ON geotab_driver_event (driver_id, occurred_at DESC);
CREATE INDEX IF NOT EXISTS idx_gde_open   ON geotab_driver_event (dismissed, violation_id);
CREATE INDEX IF NOT EXISTS idx_gde_rule   ON geotab_driver_event (rule_id);
