<?php
// =====================================================================
// Geotab vehicle-health poller — Phase 3.
//
// Two jobs, both cheap enough to run together on a slower cadence than the
// position poller (Task Scheduler, ~every 15 min):
//
//   1. FaultData feed  -> upsert geotab_fault (engine fault codes / DTCs).
//   2. StatusData (odometer + engine hours) -> latest values onto units.
//
// Faults are surfaced only; nothing here pulls a truck from service. Odometer
// / engine hours feed distance/hours-based preventive maintenance.
//
//   php "…/php/operations/geotab_poll_health.php"
// =====================================================================

$isCli = (PHP_SAPI === 'cli');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/geotab_client.php';

if (!$isCli) {
    session_start();
    header('Content-Type: application/json');
    if (($_SESSION['user_type'] ?? '') !== 'Admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Admin only']);
        exit;
    }
}

const GEOTAB_FAULT_FEED = 'FaultData';

function pt_geotab_h_dt(?string $iso): ?string {
    if (!$iso) return null;
    try {
        return (new DateTime($iso, new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

$faultsUpserted = 0;
$diagUnits = 0;

try {
    // device_id => [unit_id, unit_name] for all linked trucks.
    $deviceMap = [];
    $lu = $conn->query(
        "SELECT unit_id, unit_name, geotab_device_id
           FROM units WHERE geotab_device_id IS NOT NULL"
    );
    foreach ($lu->fetchAll() as $u) {
        $deviceMap[(string)$u['geotab_device_id']] = [(int)$u['unit_id'], (string)$u['unit_name']];
    }

    // ---- 1. Faults ---------------------------------------------------
    $conn->prepare(
        "INSERT INTO geotab_feed_state (feed_name) VALUES (?) ON CONFLICT (feed_name) DO NOTHING"
    )->execute([GEOTAB_FAULT_FEED]);
    $st = $conn->prepare("SELECT last_version FROM geotab_feed_state WHERE feed_name = ?");
    $st->execute([GEOTAB_FAULT_FEED]);
    $fromVersion = $st->fetchColumn();
    $fromVersion = ($fromVersion === false) ? null : (string)$fromVersion;

    $feed = pt_geotab_get_feed(GEOTAB_FAULT_FEED, $fromVersion);

    $upsert = $conn->prepare(
        "INSERT INTO geotab_fault
            (fault_id, unit_id, unit_name, device_id, diagnostic_id, code, description,
             fault_state, active, occurrences, occurred_at, cleared_at, updated_at)
         VALUES
            (:fid, :uid, :uname, :dev, :diag, :code, :desc,
             :state, :active, :occ, :from, :to, NOW())
         ON CONFLICT (fault_id) DO UPDATE SET
            fault_state = EXCLUDED.fault_state,
            active      = EXCLUDED.active,
            occurrences = EXCLUDED.occurrences,
            cleared_at  = EXCLUDED.cleared_at,
            description = CASE WHEN EXCLUDED.description <> '' THEN EXCLUDED.description ELSE geotab_fault.description END,
            updated_at  = NOW()"
    );

    $conn->beginTransaction();
    foreach ($feed['data'] as $f) {
        $faultId = (string)($f['id'] ?? '');
        $deviceId = (string)($f['device']['id'] ?? '');
        if ($faultId === '' || $deviceId === '') {
            continue;
        }
        // Only track faults for trucks we've linked.
        if (!isset($deviceMap[$deviceId])) {
            continue;
        }
        [$unitId, $unitName] = $deviceMap[$deviceId];

        $state   = (string)($f['faultState'] ?? '');
        $activeTo = pt_geotab_h_dt($f['activeTo'] ?? null);
        // Active if Geotab says so, or if there's no clear time yet.
        $isActive = ($state === '' ) ? ($activeTo === null)
                   : (stripos($state, 'active') !== false || stripos($state, 'pending') !== false);
        if ($activeTo !== null) {
            $isActive = false;
        }

        $fm   = $f['failureMode'] ?? [];
        $code = (string)($fm['code'] ?? '');
        $desc = (string)($fm['name'] ?? '');

        $upsert->execute([
            ':fid'    => $faultId,
            ':uid'    => $unitId,
            ':uname'  => $unitName,
            ':dev'    => $deviceId,
            ':diag'   => (string)($f['diagnostic']['id'] ?? ''),
            ':code'   => $code,
            ':desc'   => $desc,
            ':state'  => $state,
            ':active' => $isActive ? 1 : 0,
            ':occ'    => (int)($f['count'] ?? 1),
            ':from'   => pt_geotab_h_dt($f['activeFrom'] ?? ($f['dateTime'] ?? null)),
            ':to'     => $activeTo,
        ]);
        $faultsUpserted++;
    }
    $conn->commit();

    $conn->prepare(
        "UPDATE geotab_feed_state SET last_version = ?, last_run_at = NOW(), last_error = NULL WHERE feed_name = ?"
    )->execute([$feed['toVersion'], GEOTAB_FAULT_FEED]);

    // ---- 2. Odometer & engine hours ---------------------------------
    $diagOdo = defined('GEOTAB_DIAG_ODOMETER') ? GEOTAB_DIAG_ODOMETER : null;
    $diagEng = defined('GEOTAB_DIAG_ENGINE_HOURS') ? GEOTAB_DIAG_ENGINE_HOURS : null;

    if ($deviceMap && ($diagOdo || $diagEng)) {
        $fromDate = (new DateTime('-3 days', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');

        // Latest StatusData value for one device+diagnostic. Returns [value, dt].
        $latestStatus = function (string $deviceId, string $diagId) use ($fromDate): ?array {
            $rows = pt_geotab_get('StatusData', [
                'deviceSearch'     => ['id' => $deviceId],
                'diagnosticSearch' => ['id' => $diagId],
                'fromDate'         => $fromDate,
            ], 500);
            $best = null;
            foreach ($rows as $r) {
                if (!isset($r['data']) || !is_numeric($r['data'])) continue;
                $dt = $r['dateTime'] ?? '';
                if ($best === null || strcmp((string)$dt, (string)$best['dt']) > 0) {
                    $best = ['value' => (float)$r['data'], 'dt' => $dt];
                }
            }
            return $best;
        };

        $updDiag = $conn->prepare(
            "UPDATE units
                SET odometer_km    = COALESCE(:odo, odometer_km),
                    engine_hours   = COALESCE(:eng, engine_hours),
                    diagnostics_at = NOW()
              WHERE unit_id = :uid"
        );

        foreach ($deviceMap as $deviceId => [$unitId, $unitName]) {
            $odoKm = null; $engHr = null;
            try {
                if ($diagOdo) {
                    $o = $latestStatus($deviceId, $diagOdo);
                    if ($o) { $odoKm = round($o['value'] / 1000, 1); }   // metres -> km
                }
                if ($diagEng) {
                    $e = $latestStatus($deviceId, $diagEng);
                    if ($e) { $engHr = round($e['value'] / 3600, 1); }   // seconds -> hours
                }
            } catch (Throwable $de) {
                error_log("[geotab_poll_health] diagnostics for $deviceId: " . $de->getMessage());
                continue;
            }
            if ($odoKm !== null || $engHr !== null) {
                $updDiag->execute([':odo' => $odoKm, ':eng' => $engHr, ':uid' => $unitId]);
                $diagUnits++;
            }
        }
    }

    $summary = "faults=$faultsUpserted diag_units=$diagUnits";
    if ($isCli) {
        echo "[geotab_poll_health] OK — $summary\n";
    } else {
        echo json_encode(['status' => 'success', 'faults' => $faultsUpserted, 'diag_units' => $diagUnits]);
    }
} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    try {
        $conn->prepare(
            "UPDATE geotab_feed_state SET last_run_at = NOW(), last_error = ? WHERE feed_name = ?"
        )->execute([$e->getMessage(), GEOTAB_FAULT_FEED]);
    } catch (Throwable $ignore) {}
    error_log('[geotab_poll_health] ' . $e->getMessage());
    if ($isCli) {
        fwrite(STDERR, '[geotab_poll_health] ERROR — ' . $e->getMessage() . "\n");
        exit(1);
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
