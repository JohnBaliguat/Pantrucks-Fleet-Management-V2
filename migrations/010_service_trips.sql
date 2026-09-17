-- =====================================================================
-- Service Trips — dispatcher can assign a route to a driver without a
-- booking (repositioning, fuel run, shop visit, trailer pickup, etc.).
--
-- Idempotent: safe to re-run.
-- =====================================================================

-- 1) trips.trip_purpose — distinguishes booking-driven trips from service trips.
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'trips'
    AND COLUMN_NAME = 'trip_purpose'
);
SET @sql := IF(@col_exists = 0,
  "ALTER TABLE `trips`
     ADD COLUMN `trip_purpose` ENUM('Booking','Service') NOT NULL DEFAULT 'Booking'
     AFTER `trip_type`",
  "SELECT 'trips.trip_purpose already exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) trips.service_reason — what kind of service trip (Repositioning, Fuel, Shop, Other).
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'trips'
    AND COLUMN_NAME = 'service_reason'
);
SET @sql := IF(@col_exists = 0,
  "ALTER TABLE `trips`
     ADD COLUMN `service_reason` VARCHAR(60) NOT NULL DEFAULT ''
     AFTER `trip_purpose`",
  "SELECT 'trips.service_reason already exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) trips.service_remarks — free-text note from dispatcher.
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'trips'
    AND COLUMN_NAME = 'service_remarks'
);
SET @sql := IF(@col_exists = 0,
  "ALTER TABLE `trips`
     ADD COLUMN `service_remarks` VARCHAR(255) NOT NULL DEFAULT ''
     AFTER `service_reason`",
  "SELECT 'trips.service_remarks already exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Index for filtering reports by purpose.
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'trips'
    AND INDEX_NAME = 'idx_trips_purpose'
);
SET @sql := IF(@idx_exists = 0,
  "CREATE INDEX idx_trips_purpose ON trips(trip_purpose)",
  "SELECT 'idx_trips_purpose already exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
