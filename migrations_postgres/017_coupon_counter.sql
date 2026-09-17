-- =====================================================================
-- Per-year running serial for coupon control numbers.
--
-- Control numbers switch to a YEAR-serial format (e.g. 2026-000125).
-- This table hands out the next serial per calendar year atomically via
-- an UPSERT ... RETURNING inside the approval transaction, so concurrent
-- approvals can't collide and rolled-back approvals don't burn a number.
-- =====================================================================

CREATE TABLE IF NOT EXISTS coupon_counter (
    yr          INTEGER PRIMARY KEY,
    last_serial INTEGER NOT NULL DEFAULT 0
);
