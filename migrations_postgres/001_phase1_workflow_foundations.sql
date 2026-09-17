-- =====================================================================
-- Phase 1 — Workflow Foundations (PostgreSQL / Supabase port)
-- Idempotent: safe to run multiple times.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. units — distinguish trucks from gensets explicitly
-- ---------------------------------------------------------------------
ALTER TABLE units
    ADD COLUMN IF NOT EXISTS unit_type VARCHAR(20) NOT NULL DEFAULT 'truck';
CREATE INDEX IF NOT EXISTS idx_units_type ON units(unit_type);

UPDATE units
SET unit_type = 'genset'
WHERE UPPER(LEFT(TRIM(unit_name), 2)) = 'GS'
  AND unit_type <> 'genset';

UPDATE units
SET unit_type = 'truck'
WHERE UPPER(LEFT(TRIM(unit_name), 2)) <> 'GS'
  AND unit_type <> 'truck';

-- ---------------------------------------------------------------------
-- 2. booking — Import / Export / Local + port fields
-- ---------------------------------------------------------------------
ALTER TABLE booking
    ADD COLUMN IF NOT EXISTS booking_type        VARCHAR(20)  NOT NULL DEFAULT 'Local',
    ADD COLUMN IF NOT EXISTS vessel_name         VARCHAR(150) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS voyage_no           VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS container_no_port   VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS bill_of_lading      VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS port_location       VARCHAR(150) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS customs_cleared     BOOLEAN      NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS customs_cleared_at  TIMESTAMP    NULL,
    ADD COLUMN IF NOT EXISTS client_notified_at  TIMESTAMP    NULL;

-- ---------------------------------------------------------------------
-- 3. trips — segment-level billing, foul-trip, status
-- ---------------------------------------------------------------------
ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS segment_costumer VARCHAR(200) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS segment_status   VARCHAR(50)  NOT NULL DEFAULT 'Pending',
    ADD COLUMN IF NOT EXISTS foul_trip        BOOLEAN      NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS cancelled_at     TIMESTAMP    NULL,
    ADD COLUMN IF NOT EXISTS cancelled_reason VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS scheduled_at     TIMESTAMP    NULL;

-- ---------------------------------------------------------------------
-- 4. dispatch — 9-stage workflow tracking
-- ---------------------------------------------------------------------
ALTER TABLE dispatch
    ADD COLUMN IF NOT EXISTS workflow_stage      VARCHAR(40)  NOT NULL DEFAULT 'dispatcher_assigned',
    ADD COLUMN IF NOT EXISTS workflow_updated_at TIMESTAMP    NULL,
    ADD COLUMN IF NOT EXISTS driver_accepted_at  TIMESTAMP    NULL,
    ADD COLUMN IF NOT EXISTS driver_declined_at  TIMESTAMP    NULL,
    ADD COLUMN IF NOT EXISTS decline_reason      VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS gate_cleared_at     TIMESTAMP    NULL,
    ADD COLUMN IF NOT EXISTS billing_closed_at   TIMESTAMP    NULL,
    ADD COLUMN IF NOT EXISTS client_notified_at  TIMESTAMP    NULL;

-- ---------------------------------------------------------------------
-- 5. drivers — shift truck + location
-- ---------------------------------------------------------------------
ALTER TABLE drivers
    ADD COLUMN IF NOT EXISTS shift_truck      VARCHAR(50)   NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS shift_started_at TIMESTAMP     NULL,
    ADD COLUMN IF NOT EXISTS last_seen_at     TIMESTAMP     NULL,
    ADD COLUMN IF NOT EXISTS last_lat         DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS last_lng         DECIMAL(10,7) NULL;

-- ---------------------------------------------------------------------
-- 7. gate_log — every vehicle entry/exit, timestamped, verified
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gate_log (
    gl_id           INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    d_id            INTEGER      NULL,
    direction       VARCHAR(10)  NOT NULL,
    truck_plate     VARCHAR(50)  NOT NULL DEFAULT '',
    trailer_code    VARCHAR(50)  NOT NULL DEFAULT '',
    genset_code     VARCHAR(50)  NOT NULL DEFAULT '',
    driver_id       INTEGER      NULL,
    qr_payload      VARCHAR(255) NOT NULL DEFAULT '',
    verified        BOOLEAN      NOT NULL DEFAULT FALSE,
    mismatch_reason VARCHAR(255) NOT NULL DEFAULT '',
    authorised      BOOLEAN      NOT NULL DEFAULT FALSE,
    guard_user_id   INTEGER      NULL,
    logged_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_gate_log_dispatch  ON gate_log(d_id);
CREATE INDEX IF NOT EXISTS idx_gate_log_logged_at ON gate_log(logged_at);

-- ---------------------------------------------------------------------
-- 8. gate_queue — vehicles waiting for dispatcher approval to enter
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gate_queue (
    gq_id        INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    d_id         INTEGER      NULL,
    truck_plate  VARCHAR(50)  NOT NULL DEFAULT '',
    driver_id    INTEGER      NULL,
    direction    VARCHAR(10)  NOT NULL DEFAULT 'IN',
    requested_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at   TIMESTAMP    NULL,
    decision     VARCHAR(20)  NOT NULL DEFAULT 'pending',
    decided_by   INTEGER      NULL,
    notes        VARCHAR(255) NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_gate_queue_decision ON gate_queue(decision);

-- ---------------------------------------------------------------------
-- 9. incident — delays, breakdowns, exceptions
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS incident (
    inc_id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    d_id            INTEGER       NULL,
    trip_id         INTEGER       NULL,
    driver_id       INTEGER       NULL,
    incident_type   VARCHAR(40)   NOT NULL,
    severity        VARCHAR(20)   NOT NULL DEFAULT 'low',
    description     TEXT          NOT NULL,
    lat             DECIMAL(10,7) NULL,
    lng             DECIMAL(10,7) NULL,
    photo_path      VARCHAR(255)  NOT NULL DEFAULT '',
    assistance      VARCHAR(40)   NOT NULL DEFAULT '',
    reassigned_d_id INTEGER       NULL,
    status          VARCHAR(20)   NOT NULL DEFAULT 'open',
    reported_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at     TIMESTAMP     NULL
);
CREATE INDEX IF NOT EXISTS idx_incident_dispatch ON incident(d_id);
CREATE INDEX IF NOT EXISTS idx_incident_status   ON incident(status);

-- ---------------------------------------------------------------------
-- 10. pre_departure_checklist — fuel, tyres, lights, cargo area, genset
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pre_departure_checklist (
    pdc_id        INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    driver_id     INTEGER      NOT NULL,
    truck_code    VARCHAR(50)  NOT NULL DEFAULT '',
    trailer_code  VARCHAR(50)  NOT NULL DEFAULT '',
    genset_code   VARCHAR(50)  NOT NULL DEFAULT '',
    fuel_ok       BOOLEAN      NOT NULL DEFAULT FALSE,
    tyres_ok      BOOLEAN      NOT NULL DEFAULT FALSE,
    lights_ok     BOOLEAN      NOT NULL DEFAULT FALSE,
    cargo_area_ok BOOLEAN      NOT NULL DEFAULT FALSE,
    genset_ok     BOOLEAN      NOT NULL DEFAULT FALSE,
    remarks       VARCHAR(255) NOT NULL DEFAULT '',
    signed_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_pdc_driver_time ON pre_departure_checklist(driver_id, signed_at);

-- ---------------------------------------------------------------------
-- 11. pod_capture — proof-of-delivery
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pod_capture (
    pod_id         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    d_id           INTEGER       NOT NULL,
    trip_id        INTEGER       NULL,
    driver_id      INTEGER       NOT NULL,
    photo1_path    VARCHAR(255)  NOT NULL DEFAULT '',
    photo2_path    VARCHAR(255)  NOT NULL DEFAULT '',
    photo3_path    VARCHAR(255)  NOT NULL DEFAULT '',
    signature_path VARCHAR(255)  NOT NULL DEFAULT '',
    signed_by      VARCHAR(150)  NOT NULL DEFAULT '',
    lat            DECIMAL(10,7) NULL,
    lng            DECIMAL(10,7) NULL,
    captured_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_pod_dispatch ON pod_capture(d_id);

-- ---------------------------------------------------------------------
-- 12. gateless_completion — GPS-stamped completion
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gateless_completion (
    gc_id       INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    d_id        INTEGER       NOT NULL,
    trip_id     INTEGER       NULL,
    driver_id   INTEGER       NOT NULL,
    lat         DECIMAL(10,7) NOT NULL,
    lng         DECIMAL(10,7) NOT NULL,
    accuracy_m  INTEGER       NOT NULL DEFAULT 0,
    photo1_path VARCHAR(255)  NOT NULL DEFAULT '',
    photo2_path VARCHAR(255)  NOT NULL DEFAULT '',
    captured_at TIMESTAMP     NOT NULL,
    synced_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    offline     BOOLEAN       NOT NULL DEFAULT FALSE
);
CREATE INDEX IF NOT EXISTS idx_gc_dispatch ON gateless_completion(d_id);

-- ---------------------------------------------------------------------
-- 13. trailer_jackup
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trailer_jackup (
    tj_id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    d_id           INTEGER       NULL,
    trailer_code   VARCHAR(50)   NOT NULL,
    driver_id      INTEGER       NOT NULL,
    lat            DECIMAL(10,7) NULL,
    lng            DECIMAL(10,7) NULL,
    photo_path     VARCHAR(255)  NOT NULL DEFAULT '',
    detached_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    returned_at    TIMESTAMP     NULL,
    billing_active BOOLEAN       NOT NULL DEFAULT TRUE
);
CREATE INDEX IF NOT EXISTS idx_tj_trailer ON trailer_jackup(trailer_code);
CREATE INDEX IF NOT EXISTS idx_tj_active  ON trailer_jackup(billing_active);

-- ---------------------------------------------------------------------
-- 14. message
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS message (
    msg_id    INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    from_role VARCHAR(20) NOT NULL,
    from_id   INTEGER     NOT NULL,
    to_role   VARCHAR(20) NOT NULL,
    to_id     INTEGER     NULL,
    body      TEXT        NOT NULL,
    d_id      INTEGER     NULL,
    read_at   TIMESTAMP   NULL,
    sent_at   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_msg_to       ON message(to_role, to_id, read_at);
CREATE INDEX IF NOT EXISTS idx_msg_dispatch ON message(d_id);

-- ---------------------------------------------------------------------
-- 15. push_subscription
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS push_subscription (
    ps_id        INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    driver_id    INTEGER      NOT NULL,
    endpoint     VARCHAR(500) NOT NULL,
    p256dh_key   VARCHAR(255) NOT NULL,
    auth_key     VARCHAR(255) NOT NULL,
    user_agent   VARCHAR(255) NOT NULL DEFAULT '',
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP    NULL,
    CONSTRAINT uniq_endpoint UNIQUE (endpoint)
);
CREATE INDEX IF NOT EXISTS idx_ps_driver ON push_subscription(driver_id);

-- ---------------------------------------------------------------------
-- 16. workflow_event
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS workflow_event (
    we_id      INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    d_id       INTEGER      NULL,
    booking_no VARCHAR(200) NOT NULL DEFAULT '',
    stage      VARCHAR(40)  NOT NULL,
    actor_role VARCHAR(20)  NOT NULL DEFAULT '',
    actor_id   INTEGER      NULL,
    notes      VARCHAR(500) NOT NULL DEFAULT '',
    event_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_we_dispatch ON workflow_event(d_id, event_at);
CREATE INDEX IF NOT EXISTS idx_we_booking  ON workflow_event(booking_no, event_at);

-- Seed workflow_event with the current state of every existing dispatch
INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, notes, event_at)
SELECT
    d.d_id,
    d.booking_no,
    'dispatcher_assigned',
    'dispatcher',
    'Backfilled from existing dispatch row',
    d.d_datetime
FROM dispatch d
LEFT JOIN workflow_event we
    ON we.d_id = d.d_id AND we.stage = 'dispatcher_assigned'
WHERE we.we_id IS NULL;
