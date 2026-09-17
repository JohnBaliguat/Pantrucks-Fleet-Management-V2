-- =====================================================================
-- Driver earnings leaderboard — opt-in flag.
-- Drivers choose whether to appear on the Top Earners board (shown to
-- others as first name + rank only). Additive & idempotent.
-- =====================================================================

ALTER TABLE drivers
    ADD COLUMN IF NOT EXISTS leaderboard_optin BOOLEAN NOT NULL DEFAULT FALSE;

-- Helps the per-cutoff leaderboard aggregate that filters opted-in drivers.
CREATE INDEX IF NOT EXISTS idx_drivers_leaderboard_optin
    ON drivers (leaderboard_optin)
    WHERE leaderboard_optin = TRUE;
