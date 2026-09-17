<?php
// Auto end-shift worker.
//
// Closes any open driver_shift row where:
//   1. The driver has NO currently active dispatch
//      (active = dispatcher_assigned, reassigned, driver_accepted,
//       gate_cleared, en_route, delivered, pending_verification)
//   2. The latest activity for that driver — whichever is later between
//      shift start and the most recent dispatch.workflow_updated_at —
//      was more than IDLE_MINUTES ago.
//
// Designed to be called three ways:
//   (a) CLI cron / Task Scheduler:   php auto_end_idle_shifts.php
//   (b) HTTP from another PHP file:  pt_auto_end_idle_shifts($conn);
//   (c) HTTP directly (Admin only):  /php/operations/auto_end_idle_shifts.php
//
// Returns a small JSON summary of the shifts that were closed.

require_once __DIR__ . '/../config/config.php';

const PT_AUTO_END_IDLE_MINUTES = 60;

if (!function_exists('pt_auto_end_idle_shifts')) {
    /**
     * Close idle shifts. Returns array of closed shift rows.
     * Safe to call from anywhere — uses its own transaction per shift so a
     * single bad row doesn't block the others.
     *
     * @param PDO $conn
     * @param int $idleMinutes
     * @return array{closed:int, shifts:array}
     */
    function pt_auto_end_idle_shifts(PDO $conn, int $idleMinutes = PT_AUTO_END_IDLE_MINUTES): array {
        // Find candidate shifts. Postgres-flavoured SQL with positional bind.
        $sql = "
            SELECT
                ds.ds_id,
                ds.driver_id,
                ds.started_at,
                ds.truck_code,
                drv.shift_truck,
                GREATEST(
                    ds.started_at,
                    COALESCE(
                        (SELECT MAX(d.workflow_updated_at)
                           FROM dispatch d
                          WHERE d.driver_id = ds.driver_id),
                        ds.started_at
                    )
                ) AS last_activity_at
            FROM driver_shift ds
            LEFT JOIN drivers drv ON drv.driver_id = ds.driver_id
            WHERE ds.ended_at IS NULL
              AND NOT EXISTS (
                    SELECT 1 FROM dispatch d
                     WHERE d.driver_id = ds.driver_id
                       AND d.workflow_stage IN (
                            'dispatcher_assigned','reassigned',
                            'driver_accepted','gate_cleared',
                            'en_route','delivered','pending_verification'
                       )
              )
              AND GREATEST(
                    ds.started_at,
                    COALESCE(
                        (SELECT MAX(d.workflow_updated_at)
                           FROM dispatch d
                          WHERE d.driver_id = ds.driver_id),
                        ds.started_at
                    )
              ) < NOW() - (? || ' minutes')::interval
            ORDER BY ds.ds_id ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([(string)$idleMinutes]);
        $candidates = $stmt->fetchAll();

        $closed = [];
        foreach ($candidates as $cand) {
            $driverId = (int)$cand['driver_id'];
            $dsId     = (int)$cand['ds_id'];
            // Prefer the live shift_truck from the drivers row (kept in sync
            // by start_shift); fall back to the shift's recorded truck_code.
            $truckCode = trim((string)($cand['shift_truck'] ?? '')) !== ''
                ? (string)$cand['shift_truck']
                : (string)($cand['truck_code'] ?? '');

            // Look up what's attached to the truck so we can release them
            // — same cleanup that the manual end_shift endpoint does.
            $assignedTrailer = '';
            $assignedGenset  = '';
            if ($truckCode !== '') {
                $u = $conn->prepare(
                    "SELECT unit_assigntrailer, unit_assigngenset
                       FROM units WHERE unit_name = ? LIMIT 1"
                );
                $u->execute([$truckCode]);
                $urow = $u->fetch();
                $assignedTrailer = trim((string)($urow['unit_assigntrailer'] ?? ''));
                $assignedGenset  = trim((string)($urow['unit_assigngenset']  ?? ''));
            }

            try {
                $conn->beginTransaction();

                // Close the shift row + record machine hours.
                $u = $conn->prepare(
                    "UPDATE driver_shift
                        SET ended_at = NOW(),
                            machine_hours = ROUND(EXTRACT(EPOCH FROM (NOW() - started_at)) / 3600.0, 2),
                            notes = CASE
                                WHEN COALESCE(notes,'') = '' THEN 'auto-ended (idle ' || ? || ' min)'
                                ELSE notes || ' | auto-ended (idle ' || ? || ' min)'
                            END
                      WHERE ds_id = ? AND ended_at IS NULL"
                );
                $u->execute([$idleMinutes, $idleMinutes, $dsId]);
                if ($u->rowCount() === 0) {
                    // Someone else (e.g. a concurrent manual end-shift) beat us to it.
                    $conn->rollBack();
                    continue;
                }

                // Release trailer.
                if ($assignedTrailer !== '') {
                    $u = $conn->prepare(
                        "UPDATE trailer
                            SET trailer_assignto = '', driver_id = 0, trailer_status = 'Good'
                          WHERE trailer_name = ?"
                    );
                    $u->execute([$assignedTrailer]);
                }
                // Release genset.
                if ($assignedGenset !== '') {
                    $u = $conn->prepare(
                        "UPDATE units
                            SET unit_assign = '', driver_id = 0, unit_status = 'Good'
                          WHERE unit_name = ?"
                    );
                    $u->execute([$assignedGenset]);
                }
                // Release truck.
                if ($truckCode !== '') {
                    $u = $conn->prepare(
                        "UPDATE units
                            SET unit_assign = '', driver_id = 0,
                                unit_assigntrailer = '', unit_assigngenset = '',
                                unit_status = 'Good'
                          WHERE unit_name = ?"
                    );
                    $u->execute([$truckCode]);
                }
                // Mirror onto drivers row so fast filters see the change.
                $u = $conn->prepare(
                    "UPDATE drivers
                        SET shift_ended_at = NOW(),
                            shift_truck = '',
                            driver_status = 'Active'
                      WHERE driver_id = ?"
                );
                $u->execute([$driverId]);

                $conn->commit();
                $closed[] = [
                    'ds_id'     => $dsId,
                    'driver_id' => $driverId,
                    'truck'     => $truckCode,
                    'idle_since'=> $cand['last_activity_at'] ?? null,
                ];
            } catch (Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                error_log('[auto_end_idle_shifts] driver_id=' . $driverId . ' failed: ' . $e->getMessage());
            }
        }

        return ['closed' => count($closed), 'shifts' => $closed];
    }
}

// ---- CLI / HTTP entry point -----------------------------------------------
// When this file is invoked directly (cron or curl), run the worker.
// When it's `require`d from another script, only the function is defined.
if (PHP_SAPI === 'cli') {
    $minutes = isset($argv[1]) ? max(1, (int)$argv[1]) : PT_AUTO_END_IDLE_MINUTES;
    $result  = pt_auto_end_idle_shifts($conn, $minutes);
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
    exit(0);
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    // HTTP entry — Admin / Dispatch Admin / Dispatcher only so it can't be
    // used as a tool by drivers to nuke arbitrary shifts.
    if (session_status() === PHP_SESSION_NONE) session_start();
    $role = $_SESSION['user_type'] ?? '';
    if (!in_array($role, ['Admin', 'Dispatch Admin', 'Dispatcher'], true)) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
        exit;
    }
    $minutes = isset($_GET['minutes']) ? max(1, (int)$_GET['minutes']) : PT_AUTO_END_IDLE_MINUTES;
    $result  = pt_auto_end_idle_shifts($conn, $minutes);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success'] + $result);
}
