-- =====================================================================
-- Phase 11 — Client portal foundations:
--   customer_segment, container_status lifecycle, Client role
-- PostgreSQL / Supabase port. Idempotent.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. customer — segment attribute
-- ---------------------------------------------------------------------
ALTER TABLE customer
    ADD COLUMN IF NOT EXISTS customer_segment VARCHAR(50) NOT NULL DEFAULT '';
CREATE INDEX IF NOT EXISTS idx_customer_segment ON customer(customer_segment);

-- Backfill: seed segment from known customer codes.
UPDATE customer
SET customer_segment = UPPER(TRIM(customer_code))
WHERE customer_segment = ''
  AND UPPER(TRIM(customer_code)) IN ('ABC', 'DOLE', 'SUMI', 'CTH', 'DM', 'FARM');

-- ---------------------------------------------------------------------
-- 2. booking — snapshot the segment at creation time
-- ---------------------------------------------------------------------
ALTER TABLE booking
    ADD COLUMN IF NOT EXISTS customer_segment VARCHAR(50) NOT NULL DEFAULT '';
CREATE INDEX IF NOT EXISTS idx_booking_customer_segment ON booking(customer_segment);

-- Backfill existing bookings from the customer table (Postgres JOIN UPDATE).
UPDATE booking
SET customer_segment = c.customer_segment
FROM customer c
WHERE c.customer_code = booking.costumer
  AND booking.customer_segment = ''
  AND c.customer_segment <> '';

-- ---------------------------------------------------------------------
-- 3. user — link Client logins to a customer row
--    (`user` is a reserved word in PostgreSQL — must be double-quoted)
-- ---------------------------------------------------------------------
ALTER TABLE "user"
    ADD COLUMN IF NOT EXISTS customer_code VARCHAR(100) NOT NULL DEFAULT '';
CREATE INDEX IF NOT EXISTS idx_user_customer_code ON "user"(customer_code);
