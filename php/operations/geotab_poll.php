<?php
// =====================================================================
// Geotab position poller — Phase 1.
//
// Pulls the DeviceStatusInfo feed (the current live state of every device)
// and writes each linked truck's position onto its `units` row. Runs from
// Windows Task Scheduler on the XAMPP box every ~60s:
//
//   php "C:\xampp\htdocs\Fleet Management New\php\operations\geotab_poll.php"
//
// This is deliberately OFF the request hot path (CLAUDE.md §11.5). It is
// CLI-only unless an Admin runs it in the browser (handy for a manual test).
//
// GetFeed gives us only records changed since the last version token, which
// we persist in geotab_feed_state so each run is a cheap delta.
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

const GEOTAB_POSITION_FEED = 'DeviceStatusInfo';

/** Parse a Geotab ISO-8601 UTC timestamp into 'Y-m-d H:i:s' (UTC), or null. */
function pt_geotab_parse_dt(?string $iso): ?string {
    if (!$iso) return null;
    try {
        $dt = new DateTime($iso, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

$startedAt = microtime(true);
$updated = 0;
$seen    = 0;

try {
    // Ensure the feed_state row exists, then read the last version token.
    $conn->prepare(
        "INSERT INTO geotab_feed_state (feed_name) VALUES (?)
         ON CONFLICT (feed_name) DO NOTHING"
    )->execute([GEOTAB_POSITION_FEED]);

    $st = $conn->prepare("SELECT last_version FROM geotab_feed_state WHERE feed_name = ?");
    $st->execute([GEOTAB_POSITION_FEED]);
    $fromVersion = $st->fetchColumn();
    $fromVersion = ($fromVersion === false) ? null : (string)$fromVersion;

    $feed = pt_geotab_get_feed(GEOTAB_POSITION_FEED, $fromVersion);

    $update = $conn->prepare(
        "UPDATE units
            SET last_lat         = :lat,
                last_lng         = :lng,
                last_speed       = :speed,
                bearing          = :bearing,
                last_position_at = :pos_at,
                is_communicating = :comm
          WHERE geotab_device_id = :device_id"
    );

    $conn->beginTransaction();
    foreach ($feed['data'] as $rec) {
        $seen++;
        $deviceId = $rec['device']['id'] ?? null;
        if (!$deviceId) {
            continue;
        }
        $lat = isset($rec['latitude'])  && is_numeric($rec['latitude'])  ? (float)$rec['latitude']  : null;
        $lng = isset($rec['longitude']) && is_numeric($rec['longitude']) ? (float)$rec['longitude'] : null;
        // Skip the null-island / out-of-range junk a not-yet-fixed device sends.
        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $lat = null;
            $lng = null;
        }

        $update->execute([
            ':lat'       => $lat,
            ':lng'       => $lng,
            ':speed'     => isset($rec['speed'])   && is_numeric($rec['speed'])   ? (float)$rec['speed']   : null,
            ':bearing'   => isset($rec['bearing']) && is_numeric($rec['bearing']) ? (float)$rec['bearing'] : null,
            ':pos_at'    => pt_geotab_parse_dt($rec['dateTime'] ?? null),
            ':comm'      => !empty($rec['isDeviceCommunicating']),
            ':device_id' => (string)$deviceId,
        ]);
        // rowCount() > 0 only when a unit is linked to this device.
        $updated += $update->rowCount();
    }
    $conn->commit();

    // Persist the new cursor + health.
    $conn->prepare(
        "UPDATE geotab_feed_state
            SET last_version = ?, last_run_at = NOW(), last_error = NULL
          WHERE feed_name = ?"
    )->execute([$feed['toVersion'], GEOTAB_POSITION_FEED]);

    $elapsed = round(microtime(true) - $startedAt, 2);
    $summary = "seen=$seen updated=$updated elapsed={$elapsed}s";
    if ($isCli) {
        echo "[geotab_poll] OK — $summary\n";
    } else {
        echo json_encode(['status' => 'success', 'seen' => $seen, 'updated' => $updated]);
    }
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    // Record the failure so it's visible instead of silent (CLAUDE.md §11.7).
    try {
        $conn->prepare(
            "UPDATE geotab_feed_state SET last_run_at = NOW(), last_error = ? WHERE feed_name = ?"
        )->execute([$e->getMessage(), GEOTAB_POSITION_FEED]);
    } catch (Throwable $ignore) {
        // feed_state itself may be unreachable — fall through to the log.
    }
    error_log('[geotab_poll] ' . $e->getMessage());
    if ($isCli) {
        fwrite(STDERR, '[geotab_poll] ERROR — ' . $e->getMessage() . "\n");
        exit(1);
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
