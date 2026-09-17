-- =====================================================================
-- Dispatch approval coupon + payroll hand-off.
--
-- Verification becomes a pure "approve" step: on approval the dispatch
-- gets a printable coupon (control_no) with a public QR token, and is
-- handed to the new Payroll role for piece-rate assignment.
--
--   control_no       - human-typable coupon number (CTL-000123)
--   qr_token         - unguessable token for the public coupon-view page
--   approved_by/at    - who approved the trip in verification
--   payroll_status    - '' -> 'pending' (awaiting rate) -> 'rated'
--   payroll_rated_by/at - who assigned the piece-rate
--
-- Additive + idempotent, matching the rest of migrations_postgres/*.
-- =====================================================================

ALTER TABLE dispatch
    ADD COLUMN IF NOT EXISTS control_no       VARCHAR(64)  NOT NULL DEFAULT '';
ALTER TABLE dispatch
    ADD COLUMN IF NOT EXISTS qr_token         VARCHAR(64)  NOT NULL DEFAULT '';
ALTER TABLE dispatch
    ADD COLUMN IF NOT EXISTS approved_by      INTEGER          NULL;
ALTER TABLE dispatch
    ADD COLUMN IF NOT EXISTS approved_at      TIMESTAMP        NULL;
ALTER TABLE dispatch
    ADD COLUMN IF NOT EXISTS payroll_status   VARCHAR(20)  NOT NULL DEFAULT '';
ALTER TABLE dispatch
    ADD COLUMN IF NOT EXISTS payroll_rated_by INTEGER          NULL;
ALTER TABLE dispatch
    ADD COLUMN IF NOT EXISTS payroll_rated_at TIMESTAMP        NULL;

-- Control numbers are unique once assigned (blank rows are ignored).
CREATE UNIQUE INDEX IF NOT EXISTS uniq_dispatch_control_no
    ON dispatch (control_no) WHERE control_no <> '';

-- Public coupon page looks the dispatch up by token.
CREATE INDEX IF NOT EXISTS idx_dispatch_qr_token
    ON dispatch (qr_token) WHERE qr_token <> '';

-- Payroll queue filters on the pending rows.
CREATE INDEX IF NOT EXISTS idx_dispatch_payroll_status
    ON dispatch (payroll_status) WHERE payroll_status <> '';
