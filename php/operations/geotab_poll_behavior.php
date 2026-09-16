<?php
// =====================================================================
// Geotab driver-behavior poller — Phase 5.
//
// Pulls the ExceptionEvent feed (speeding, harsh braking, idling, seatbelt,
// …) into geotab_driver_event, attributed to a fleet driver. Surfaced for HR
// to convert into the violation_record pipeline; nothing here blocks a driver.
//
// Cadence: ~every 15 min from Task Scheduler.
//   php "…/php/operations/geotab_poll_behavior.php"
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

const GEOTAB_BEHAVIOR_FEED = 'ExceptionEvent';

function pt_beh_dt(?string $iso): ?string {
    if (!$iso) return null;
    try { return (new DateTime($iso, new DateTimeZone('UTC')))->format('Y-m-d H:i:s'); }
    catch (Throwable $e) { return null; }
}

// Geotab durations serialize as "HH:MM:SS" (optionally "D.HH:MM:SS"). -> seconds.
function pt_beh_duration_s($d): ?int {
    if ($d === null || $d === '') return null;
    $d = (string)$d;
    $days = 0;
    if (strpos($d, '.') !== false && substr_count($d, ':') === 2 && strpos($d, '.') < strpos($d, ':')) {
        [$days, $d] = explode('.', $d, 2);
        $days = (int)$days;
    }
    $parts = explode(':', $d);
    if (count($parts) !== 3) return null;
    return $days * 86400 + (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (int)round((float)$parts[2]);
}

$upserted = 0;

try {
    // Rule id -> human name (Speeding, Harsh braking, …). Small list; cache once.
    $ruleMap = [];
    foreach (pt_geotab_get('Rule') as $r) {
        if (!empty($r['id'])) {
            $ruleMap[(string)$r['id']] = (string)($r['name'] ?? '');
        }
    }

    // device_id -> [unit_id, unit_name, current driver_id]
    $deviceMap = [];
    foreach ($conn->query(
        "SELECT unit_id, unit_name, driver_id, geotab_device_id
           FROM units WHERE geotab_device_id IS NOT NULL"
    )->fetchAll() as $u) {
        $deviceMap[(string)$u['geotab_device_id']] = [
            (int)$u['unit_id'], (string)$u['unit_name'],
            $u['driver_id'] !== null ? (int)$u['driver_id'] : null,
        ];
    }

    // geotab_user_id -> fleet driver_id (explicit driver key links, if any).
    $geoDriverMap = [];
    foreach ($conn->query(
        "SELECT driver_id, geotab_user_id FROM drivers WHERE geotab_user_id IS NOT NULL AND geotab_user_id <> ''"
    )->fetchAll() as $d) {
        $geoDriverMap[(string)$d['geotab_user_id']] = (int)$d['driver_id'];
    }

    // Feed cursor.
    $conn->prepare(
        "INSERT INTO geotab_feed_state (feed_name) VALUES (?) ON CONFLICT (feed_name) DO NOTHING"
    )->execute([GEOTAB_BEHAVIOR_FEED]);
    $st = $conn->prepare("SELECT last_version FROM geotab_feed_state WHERE feed_name = ?");
    $st->execute([GEOTAB_BEHAVIOR_FEED]);
    $fromVersion = $st->fetchColumn();
    $fromVersion = ($fromVersion === false) ? null : (string)$fromVersion;

    $feed = pt_geotab_get_feed(GEOTAB_BEHAVIOR_FEED, $fromVersion);

    // Insert new events only; never clobber an already-converted/dismissed row.
    $insert = $conn->prepare(
        "INSERT INTO geotab_driver_event
            (event_id, device_id, unit_id, unit_name, geotab_driver_id, driver_id,
             rule_id, rule_name, occurred_at, ended_at, duration_s, distance_km)
         VALUES
            (:eid, :dev, :uid, :uname, :gdid, :drv, :rid, :rname, :from, :to, :dur, :dist)
         ON CONFLICT (event_id) DO NOTHING"
    );

    $conn->beginTransaction();
    foreach ($feed['data'] as $ev) {
        $eventId  = (string)($ev['id'] ?? '');
        $deviceId = (string)($ev['device']['id'] ?? '');
        if ($eventId === '' || $deviceId === '' || !isset($deviceMap[$deviceId])) {
            continue;   // only track linked trucks
        }
        [$unitId, $unitName, $unitDriverId] = $deviceMap[$deviceId];

        $gdid = (string)($ev['driver']['id'] ?? '');
        // Attribute: explicit Geotab-user link first, else the unit's driver.
        $driverId = $geoDriverMap[$gdid] ?? $unitDriverId;

        $ruleId = (string)($ev['rule']['id'] ?? '');
        $dist = $ev['distance'] ?? null;

        $insert->execute([
            ':eid'   => $eventId,
            ':dev'   => $deviceId,
            ':uid'   => $unitId,
            ':uname' => $unitName,
            ':gdid'  => $gdid,
            ':drv'   => $driverId,
            ':rid'   => $ruleId,
            ':rname' => $ruleMap[$ruleId] ?? '',
            ':from'  => pt_beh_dt($ev['activeFrom'] ?? null),
            ':to'    => pt_beh_dt($ev['activeTo'] ?? null),
            ':dur'   => pt_beh_duration_s($ev['duration'] ?? null),
            ':dist'  => is_numeric($dist) ? (float)$dist : null,
        ]);
        $upserted += $insert->rowCount();
    }
    $conn->commit();

    $conn->prepare(
        "UPDATE geotab_feed_state SET last_version = ?, last_run_at = NOW(), last_error = NULL WHERE feed_name = ?"
    )->execute([$feed['toVersion'], GEOTAB_BEHAVIOR_FEED]);

    $summary = "new_events=$upserted";
    if ($isCli) {
        echo "[geotab_poll_behavior] OK — $summary\n";
    } else {
        echo json_encode(['status' => 'success', 'new_events' => $upserted]);
    }
} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    try {
        $conn->prepare(
            "UPDATE geotab_feed_state SET last_run_at = NOW(), last_error = ? WHERE feed_name = ?"
        )->execute([$e->getMessage(), GEOTAB_BEHAVIOR_FEED]);
    } catch (Throwable $ignore) {}
    error_log('[geotab_poll_behavior] ' . $e->getMessage());
    if ($isCli) {
        fwrite(STDERR, '[geotab_poll_behavior] ERROR — ' . $e->getMessage() . "\n");
        exit(1);
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
