-- =====================================================================
-- Phase 13 — Fuel Ticketing module (ported from PTSI Fuel System Base)
-- PostgreSQL / Supabase port. Idempotent.
-- =====================================================================

-- ---------------------------------------------------------------------
-- fuel_report
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fuel_report (
  f_id              INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  f_date            TIMESTAMP     NOT NULL,
  f_unit            VARCHAR(200)  NOT NULL,
  f_lastHubo        NUMERIC(11,2) NOT NULL DEFAULT 0,
  f_hubo            NUMERIC(11,2) NOT NULL DEFAULT 0,
  calculated_hubo   NUMERIC(11,2) NOT NULL DEFAULT 0,
  f_kmRun           NUMERIC(11,2) NOT NULL DEFAULT 0,
  f_noOfLit         NUMERIC(11,2) NOT NULL DEFAULT 0,
  f_actRatio        NUMERIC(11,2) NOT NULL DEFAULT 0,
  f_driver          VARCHAR(200)  NOT NULL DEFAULT '',
  f_driverId        VARCHAR(50)   NOT NULL DEFAULT '',
  f_STDRatio        NUMERIC(11,2) NOT NULL DEFAULT 0,
  f_excess_Saving   NUMERIC(11,2) NOT NULL DEFAULT 0,
  f_hourMeter       VARCHAR(200)  NOT NULL DEFAULT '0',
  f_controlNo       VARCHAR(255)  NOT NULL,
  f_ideNoLt         NUMERIC(11,2) NOT NULL DEFAULT 0,
  f_tripTicket      VARCHAR(200)  NOT NULL DEFAULT '',
  f_tripsegment     VARCHAR(200)  NOT NULL DEFAULT '',
  f_trasactionBy    VARCHAR(200)  NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_fuel_report_controlNo  ON fuel_report(f_controlNo);
CREATE INDEX IF NOT EXISTS idx_fuel_report_unit_date  ON fuel_report(f_unit, f_date);
CREATE INDEX IF NOT EXISTS idx_fuel_report_driverId   ON fuel_report(f_driverId);

-- Defensive ADDs (in case older base schemas don't have these columns)
ALTER TABLE fuel_report
    ADD COLUMN IF NOT EXISTS calculated_hubo NUMERIC(11,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS f_driverId      VARCHAR(50)   NOT NULL DEFAULT '';

-- ---------------------------------------------------------------------
-- fuel_inventory
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fuel_inventory (
  fi_id            INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  fi_poNo          VARCHAR(200)  NOT NULL DEFAULT '',
  fi_noOfLiters    NUMERIC(11,2) NOT NULL DEFAULT 0,
  fi_receiveBy     VARCHAR(200)  NOT NULL DEFAULT '',
  fi_date          TIMESTAMP     NOT NULL,
  fi_inVo          VARCHAR(200)  NOT NULL DEFAULT '',
  fi_plateNo       VARCHAR(200)  NOT NULL DEFAULT '',
  fi_consumableLtr DECIMAL(11,2) NOT NULL DEFAULT 0,
  fi_consumeLtr    DECIMAL(11,2) NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_fuel_inventory_date       ON fuel_inventory(fi_date);
CREATE INDEX IF NOT EXISTS idx_fuel_inventory_consumable ON fuel_inventory(fi_consumableLtr);

-- ---------------------------------------------------------------------
-- consume_fuel
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS consume_fuel (
  id         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  unit       VARCHAR(200)  NOT NULL,
  driver     VARCHAR(200)  NOT NULL DEFAULT '',
  hubo       DECIMAL(11,2) NOT NULL DEFAULT 0,
  consumeLtr DECIMAL(11,2) NOT NULL DEFAULT 0,
  date       TIMESTAMP     NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_consume_fuel_unit ON consume_fuel(unit);
CREATE INDEX IF NOT EXISTS idx_consume_fuel_date ON consume_fuel(date);

-- ---------------------------------------------------------------------
-- ticket_code
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_code (
  tc_id      INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tc_code    VARCHAR(12)  NOT NULL,
  f_driverId VARCHAR(50)  NOT NULL DEFAULT '',
  unit_name  VARCHAR(200) NOT NULL DEFAULT '',
  tc_date    TIMESTAMP    NOT NULL,
  tc_status  VARCHAR(20)  NOT NULL DEFAULT 'Unused',
  CONSTRAINT uk_ticket_code UNIQUE (tc_code)
);
CREATE INDEX IF NOT EXISTS idx_ticket_code_status ON ticket_code(tc_status);
CREATE INDEX IF NOT EXISTS idx_ticket_code_driver ON ticket_code(f_driverId);

-- ---------------------------------------------------------------------
-- trip_receipts
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trip_receipts (
  receipts_id    INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tc_id          INTEGER       NOT NULL DEFAULT 0,
  control_no     VARCHAR(255)  NOT NULL DEFAULT '',
  tr_number      VARCHAR(100)  NOT NULL DEFAULT '',
  location_from  VARCHAR(200)  NOT NULL DEFAULT '',
  location_to    VARCHAR(200)  NOT NULL DEFAULT '',
  total_km       DECIMAL(11,2) NOT NULL DEFAULT 0,
  maptotal_kmRun DECIMAL(11,2) NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_trip_receipts_tc      ON trip_receipts(tc_id);
CREATE INDEX IF NOT EXISTS idx_trip_receipts_control ON trip_receipts(control_no);

-- ---------------------------------------------------------------------
-- update_log
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS update_log (
  log_id         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  f_id           INTEGER   NOT NULL,
  user_id        INTEGER   NOT NULL,
  upLog_datetime TIMESTAMP NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_update_log_fid ON update_log(f_id);

-- ---------------------------------------------------------------------
-- units.unit_std safety
-- ---------------------------------------------------------------------
ALTER TABLE units
    ADD COLUMN IF NOT EXISTS unit_std NUMERIC(11,2) NOT NULL DEFAULT 0;
