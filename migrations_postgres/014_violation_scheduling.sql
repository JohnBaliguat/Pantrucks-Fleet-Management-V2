-- 014 — Schedule driver violations.
-- A violation can be scheduled for a future date/time: it is recorded with
-- vr_status='Scheduled' and does NOT block the driver until vr_scheduled_at is
-- reached, at which point an auto-activation sweep flips it to 'Active' and
-- blocks the driver.

ALTER TABLE violation_record
    ADD COLUMN IF NOT EXISTS vr_scheduled_at TIMESTAMP DEFAULT NULL;
