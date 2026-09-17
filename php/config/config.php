<?php
// Database credentials are loaded from (in order of precedence):
//   1. Environment variables PT_DB_DSN / PT_DB_USER / PT_DB_PASS
//   2. A git-ignored php/config/db.php returning ['dsn','user','pass']
// Credentials are no longer hard-coded here. See db.example.php.
$dsn     = getenv('PT_DB_DSN')  ?: '';
$db_user = getenv('PT_DB_USER') ?: '';
$db_pass = getenv('PT_DB_PASS');
if ($db_pass === false) { $db_pass = ''; }

if ($dsn === '' || $db_user === '') {
    $dbFile = __DIR__ . '/db.php';
    if (is_file($dbFile)) {
        $cfg = require $dbFile;
        if (is_array($cfg)) {
            if ($dsn === '')             $dsn     = (string)($cfg['dsn']  ?? '');
            if ($db_user === '')         $db_user = (string)($cfg['user'] ?? '');
            if ($db_pass === '')         $db_pass = (string)($cfg['pass'] ?? '');
        }
    }
}

if ($dsn === '' || $db_user === '') {
    http_response_code(500);
    exit('Database is not configured. Copy php/config/db.example.php to db.php (or set PT_DB_* env vars).');
}

try {
    $conn = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // Don't leak DSN/schema details to the client; log server-side instead.
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Service temporarily unavailable.');
}

if (!function_exists('pt_pg_escape')) {
    /**
     * Drop-in replacement for mysqli_real_escape_string when porting code that
     * still builds SQL with inline string interpolation. Returns the escaped
     * payload WITHOUT surrounding quotes (caller wraps with '...' in their SQL).
     *
     * Prefer parameterised queries over this; only here to keep legacy code
     * working while the conversion happens.
     */
    function pt_pg_escape(PDO $conn, $value): string {
        if ($value === null) return '';
        $quoted = $conn->quote((string)$value);
        // PDO::quote returns "'escaped'"; strip the surrounding quotes.
        return ($quoted !== false && strlen($quoted) >= 2)
            ? substr($quoted, 1, -1)
            : str_replace("'", "''", (string)$value);
    }
}

if (!function_exists('pt_mark_table_exists')) {
    function pt_mark_table_exists(string $table, bool $exists): void {
        $table = strtolower($table);
        $GLOBALS['pt_table_exists_cache'][$table] = $exists;
    }
}

if (!function_exists('pt_mark_column_exists')) {
    function pt_mark_column_exists(string $table, string $column, bool $exists): void {
        $table = strtolower($table);
        $column = strtolower($column);
        $GLOBALS['pt_column_exists_cache'][$table . '.' . $column] = $exists;
    }
}

if (!function_exists('pt_table_exists')) {
    function pt_table_exists(PDO $conn, string $table): bool {
        $table = strtolower($table);
        if (isset($GLOBALS['pt_table_exists_cache'][$table])) {
            return (bool)$GLOBALS['pt_table_exists_cache'][$table];
        }

        try {
            $stmt = $conn->prepare(
                "SELECT 1 FROM information_schema.tables
                 WHERE table_schema = 'public' AND table_name = ?
                 LIMIT 1"
            );
            $stmt->execute([$table]);
            $exists = (bool)$stmt->fetchColumn();
            pt_mark_table_exists($table, $exists);
            return $exists;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('pt_column_exists')) {
    function pt_column_exists(PDO $conn, string $table, string $column): bool {
        $table = strtolower($table);
        $column = strtolower($column);
        $cacheKey = $table . '.' . $column;
        if (isset($GLOBALS['pt_column_exists_cache'][$cacheKey])) {
            return (bool)$GLOBALS['pt_column_exists_cache'][$cacheKey];
        }

        try {
            $stmt = $conn->prepare(
                "SELECT 1 FROM information_schema.columns
                 WHERE table_schema = 'public' AND table_name = ? AND column_name = ?
                 LIMIT 1"
            );
            $stmt->execute([$table, $column]);
            $exists = (bool)$stmt->fetchColumn();
            pt_mark_column_exists($table, $column, $exists);
            return $exists;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('pt_ensure_column')) {
    function pt_ensure_column(PDO $conn, string $table, string $column, string $definition): void {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $table) || !preg_match('/^[a-z_][a-z0-9_]*$/i', $column)) {
            throw new InvalidArgumentException('Invalid table or column identifier.');
        }

        $table = strtolower($table);
        $column = strtolower($column);

        if (pt_column_exists($conn, $table, $column)) {
            return;
        }

        $conn->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS {$column} {$definition}");
        pt_mark_table_exists($table, true);
        pt_mark_column_exists($table, $column, true);
    }
}

if (!function_exists('pt_ensure_trailer_jackup_table')) {
    function pt_ensure_trailer_jackup_table(PDO $conn): void {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $conn->exec(
            "CREATE TABLE IF NOT EXISTS trailer_jackup (
                tj_id INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                d_id INTEGER NOT NULL,
                trailer_code VARCHAR(100) NOT NULL,
                driver_id INTEGER NOT NULL,
                lat NUMERIC(10,7) NULL,
                lng NUMERIC(10,7) NULL,
                photo_path VARCHAR(255) NOT NULL DEFAULT '',
                billing_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_trailer_jackup_dispatch ON trailer_jackup (d_id)");
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_trailer_jackup_trailer ON trailer_jackup (trailer_code)");
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_trailer_jackup_driver ON trailer_jackup (driver_id)");
        pt_mark_table_exists('trailer_jackup', true);
        $ensured = true;
    }
}

if (!function_exists('pt_ensure_pickup_capture_table')) {
    function pt_ensure_pickup_capture_table(PDO $conn): void {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $conn->exec(
            "CREATE TABLE IF NOT EXISTS pickup_capture (
                pc_id INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                d_id INTEGER NOT NULL,
                trip_id INTEGER NOT NULL,
                driver_id INTEGER NOT NULL,
                driver_id_number VARCHAR(100) NOT NULL DEFAULT '',
                container_no VARCHAR(100) NOT NULL,
                photo_path VARCHAR(255) NOT NULL,
                lat NUMERIC(10,7) NULL,
                lng NUMERIC(10,7) NULL,
                captured_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_pickup_capture_dispatch ON pickup_capture (d_id)");
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_pickup_capture_trip ON pickup_capture (trip_id)");
        pt_mark_table_exists('pickup_capture', true);
        pt_mark_column_exists('pickup_capture', 'driver_id_number', true);
        $ensured = true;
    }
}

if (!function_exists('pt_driver_has_active_violation')) {
    function pt_driver_has_active_violation(PDO $conn, int $driverId): bool {
        try {
            if ($driverId <= 0 || !pt_table_exists($conn, 'violation_record')) {
                return false;
            }

            $stmt = $conn->prepare(
                "SELECT 1
                 FROM violation_record
                 WHERE driver_id = ?
                   AND vr_status = 'Active'
                 LIMIT 1"
            );
            $stmt->execute([$driverId]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('pt_driver_violation_block_message')) {
    function pt_driver_violation_block_message(): string {
        return 'Driver is blocked by HR due to an active violation and cannot be dispatched until cleared.';
    }
}

if (!function_exists('pt_apply_scheduled_maintenance')) {
    function pt_apply_scheduled_maintenance(PDO $conn): void {
        try {
            if (!pt_table_exists($conn, 'unit_maintenance')) {
                return;
            }

            if (!pt_column_exists($conn, 'unit_maintenance', 'scheduled_start_at')) {
                $conn->exec("ALTER TABLE unit_maintenance ADD COLUMN IF NOT EXISTS scheduled_start_at TIMESTAMP NULL");
            }
            if (!pt_column_exists($conn, 'unit_maintenance', 'activated_at')) {
                $conn->exec("ALTER TABLE unit_maintenance ADD COLUMN IF NOT EXISTS activated_at TIMESTAMP NULL");
            }

            $rows = $conn->query(
                "SELECT um_id, unit_kind, unit_code, reason, expected_return
                 FROM unit_maintenance
                 WHERE status = 'scheduled'
                   AND scheduled_start_at IS NOT NULL
                   AND scheduled_start_at <= NOW()
                 ORDER BY um_id ASC"
            );
            if (!$rows) {
                return;
            }

            while ($row = $rows->fetch()) {
                $umId           = (int)$row['um_id'];
                $kind           = (string)$row['unit_kind'];
                $code           = (string)$row['unit_code'];
                $reason         = (string)($row['reason'] ?? '');
                $expectedReturn = $row['expected_return'] !== null ? (string)$row['expected_return'] : null;

                if ($kind === 'trailer') {
                    $upd = $conn->prepare(
                        "UPDATE trailer
                         SET maintenance_blocked = TRUE,
                             maintenance_reason = ?,
                             maintenance_expected_return = ?,
                             maintenance_blocked_at = NOW(),
                             trailer_status = 'Under Maintenance'
                         WHERE trailer_name = ?"
                    );
                    $upd->execute([$reason, $expectedReturn, $code]);
                } else {
                    $upd = $conn->prepare(
                        "UPDATE units
                         SET maintenance_blocked = TRUE,
                             maintenance_reason = ?,
                             maintenance_expected_return = ?,
                             maintenance_blocked_at = NOW(),
                             unit_status = 'Under Maintenance'
                         WHERE unit_name = ? AND unit_type = ?"
                    );
                    $upd->execute([$reason, $expectedReturn, $code, $kind]);
                }

                $upd = $conn->prepare(
                    "UPDATE unit_maintenance
                     SET status = 'active', activated_at = NOW()
                     WHERE um_id = ? AND status = 'scheduled'"
                );
                $upd->execute([$umId]);
            }
        } catch (Throwable $e) {
            // Never break page rendering because of a maintenance scheduler bootstrap check.
        }
    }
}

if (!function_exists('pt_should_run_scheduled_maintenance')) {
    function pt_should_run_scheduled_maintenance(int $intervalSeconds = 60): bool {
        if ($intervalSeconds <= 0) {
            return true;
        }

        $lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fleet_scheduled_maintenance.lock';
        $fp = @fopen($lockPath, 'c+');
        if ($fp === false) {
            return true;
        }

        try {
            if (!@flock($fp, LOCK_EX)) {
                fclose($fp);
                return true;
            }

            clearstatcache(true, $lockPath);
            $lastRun = @filemtime($lockPath);
            $now = time();
            if ($lastRun !== false && ($now - $lastRun) < $intervalSeconds) {
                flock($fp, LOCK_UN);
                fclose($fp);
                return false;
            }

            @touch($lockPath, $now);
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        } catch (Throwable $e) {
            @flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        }
    }
}

$maintenanceBootstrapInterval = (int)(getenv('PT_MAINTENANCE_BOOTSTRAP_INTERVAL') ?: 60);
if (pt_should_run_scheduled_maintenance($maintenanceBootstrapInterval)) {
    pt_apply_scheduled_maintenance($conn);
}
?>
