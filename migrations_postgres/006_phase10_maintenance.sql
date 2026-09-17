-- =====================================================================
-- Phase 10 — Maintenance role + unit blocking for trucks / gensets / trailers.
-- PostgreSQL / Supabase port. Idempotent.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. units / trailer — fast-check flags
-- ---------------------------------------------------------------------
ALTER TABLE units
    ADD COLUMN IF NOT EXISTS maintenance_blocked         BOOLEAN      NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS maintenance_reason          VARCHAR(200) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS maintenance_expected_return DATE         NULL,
    ADD COLUMN IF NOT EXISTS maintenance_blocked_at      TIMESTAMP    NULL;
CREATE INDEX IF NOT EXISTS idx_units_block ON units(maintenance_blocked);

ALTER TABLE trailer
    ADD COLUMN IF NOT EXISTS maintenance_blocked         BOOLEAN      NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS maintenance_reason          VARCHAR(200) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS maintenance_expected_return DATE         NULL,
    ADD COLUMN IF NOT EXISTS maintenance_blocked_at      TIMESTAMP    NULL;
CREATE INDEX IF NOT EXISTS idx_trailer_block ON trailer(maintenance_blocked);

-- ---------------------------------------------------------------------
-- 2. unit_maintenance — append-only history
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS unit_maintenance (
    um_id           INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    unit_kind       VARCHAR(20)   NOT NULL,
    unit_code       VARCHAR(50)   NOT NULL,
    category        VARCHAR(40)   NOT NULL DEFAULT 'other',
    severity        VARCHAR(20)   NOT NULL DEFAULT 'med',
    reason          VARCHAR(500)  NOT NULL DEFAULT '',
    photo_path      VARCHAR(255)  NOT NULL DEFAULT '',
    expected_return DATE          NULL,
    blocked_by      INTEGER       NULL,
    blocked_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    released_by     INTEGER       NULL,
    released_at     TIMESTAMP     NULL,
    release_notes   VARCHAR(500)  NOT NULL DEFAULT '',
    cost_labor      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cost_parts      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status          VARCHAR(20)   NOT NULL DEFAULT 'active'
);
CREATE INDEX IF NOT EXISTS idx_um_kind_code_status ON unit_maintenance(unit_kind, unit_code, status);
CREATE INDEX IF NOT EXISTS idx_um_status           ON unit_maintenance(status);
