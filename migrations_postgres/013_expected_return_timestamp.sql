-- 013 — Store maintenance "expected return" as a full datetime (was DATE).
-- Lets a block specify the exact time a unit is expected back, and lets the
-- auto-release sweep compare against NOW() instead of only the calendar date.
-- Safe/idempotent: only alters columns that are still DATE.

ALTER TABLE unit_maintenance
    ALTER COLUMN expected_return TYPE TIMESTAMP USING expected_return::timestamp;

ALTER TABLE units
    ALTER COLUMN maintenance_expected_return TYPE TIMESTAMP USING maintenance_expected_return::timestamp;

ALTER TABLE trailer
    ALTER COLUMN maintenance_expected_return TYPE TIMESTAMP USING maintenance_expected_return::timestamp;
