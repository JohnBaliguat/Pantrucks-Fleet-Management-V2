-- =====================================================================
-- Phase 6 — Billing close, trip receipts, client notification, push log.
-- PostgreSQL / Supabase port. Idempotent.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. dispatch_receipt
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dispatch_receipt (
    dr_id        INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    d_id         INTEGER      NOT NULL,
    receipt_type VARCHAR(50)  NOT NULL DEFAULT 'other',
    title        VARCHAR(150) NOT NULL DEFAULT '',
    file_path    VARCHAR(255) NOT NULL,
    mime_type    VARCHAR(100) NOT NULL DEFAULT '',
    requires_ack BOOLEAN      NOT NULL DEFAULT TRUE,
    attached_by  INTEGER      NULL,
    attached_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_dr_dispatch ON dispatch_receipt(d_id);

-- ---------------------------------------------------------------------
-- 2. receipt_acknowledge
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS receipt_acknowledge (
    ra_id           INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    dr_id           INTEGER   NOT NULL,
    driver_id       INTEGER   NOT NULL,
    acknowledged_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uniq_ra_driver_receipt UNIQUE (dr_id, driver_id)
);

-- ---------------------------------------------------------------------
-- 3. push_send_log
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS push_send_log (
    psl_id  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    to_role VARCHAR(20)   NOT NULL,
    to_id   INTEGER       NULL,
    channel VARCHAR(20)   NOT NULL DEFAULT 'webpush',
    subject VARCHAR(200)  NOT NULL DEFAULT '',
    body    VARCHAR(1000) NOT NULL DEFAULT '',
    d_id    INTEGER       NULL,
    outcome VARCHAR(50)   NOT NULL DEFAULT 'queued',
    error   VARCHAR(500)  NOT NULL DEFAULT '',
    sent_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_psl_dispatch ON push_send_log(d_id);
CREATE INDEX IF NOT EXISTS idx_psl_outcome  ON push_send_log(outcome);

-- ---------------------------------------------------------------------
-- 4. customer — contact fields for client_notified flow
-- ---------------------------------------------------------------------
ALTER TABLE customer
    ADD COLUMN IF NOT EXISTS notify_email VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS notify_phone VARCHAR(50)  NOT NULL DEFAULT '';

-- ---------------------------------------------------------------------
-- 5. dispatch — billing summary
-- ---------------------------------------------------------------------
ALTER TABLE dispatch
    ADD COLUMN IF NOT EXISTS billing_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS billing_currency VARCHAR(10)   NOT NULL DEFAULT 'PHP',
    ADD COLUMN IF NOT EXISTS billing_notes    VARCHAR(500)  NOT NULL DEFAULT '';
