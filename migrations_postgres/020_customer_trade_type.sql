-- =====================================================================
-- Customer trade direction: Import vs Export.
--
-- Export (default) → booking starts EMPTY, dispatcher "Mark Loaded" spawns
--                    the loaded leg (existing flow).
-- Import           → booking starts LOADED, "Create Empty Return" spawns the
--                    empty leg (the existing CTH lifecycle direction,
--                    generalized). Import customers do NOT inherit CTH's
--                    customs / EIR / SN-DO / CTH ticket — only the direction.
--
-- Additive + idempotent.
-- =====================================================================

ALTER TABLE customer
    ADD COLUMN IF NOT EXISTS trade_type VARCHAR(10) NOT NULL DEFAULT 'Export';

-- CTH is the pre-existing Import-direction customer.
UPDATE customer SET trade_type = 'Import' WHERE customer_code = 'CTH' AND trade_type <> 'Import';
