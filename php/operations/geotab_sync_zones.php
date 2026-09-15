<?php
// =====================================================================
// Sync Geotab Zones into geotab_zone. Admin-run (CLI or browser).
//
//   php "…/php/operations/geotab_sync_zones.php"
//
// Upserts each Geotab Zone's name + polygon. Preserves our own `kind` and
// `active` flags on rows that already exist (those are set on the admin
// zones page), so a re-sync never clobbers your classification.
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

try {
    $zones = pt_geotab_get('Zone');

    $insert = $conn->prepare(
        "INSERT INTO geotab_zone (zone_id, name, points, synced_at)
              VALUES (:id, :name, CAST(:points AS JSONB), NOW())
         ON CONFLICT (zone_id) DO UPDATE
              SET name = EXCLUDED.name,
                  points = EXCLUDED.points,
                  synced_at = NOW()"
    );

    $count = 0;
    foreach ($zones as $z) {
        $zoneId = (string)($z['id'] ?? '');
        if ($zoneId === '') {
            continue;
        }
        // Geotab points are {x: longitude, y: latitude}. Store as {lat,lng}.
        $poly = [];
        foreach (($z['points'] ?? []) as $p) {
            if (isset($p['x'], $p['y']) && is_numeric($p['x']) && is_numeric($p['y'])) {
                $poly[] = ['lat' => (float)$p['y'], 'lng' => (float)$p['x']];
            }
        }
        $insert->execute([
            ':id'     => $zoneId,
            ':name'   => (string)($z['name'] ?? ''),
            ':points' => json_encode($poly),
        ]);
        $count++;
    }

    if ($isCli) {
        echo "[geotab_sync_zones] OK — synced $count zones\n";
    } else {
        echo json_encode(['status' => 'success', 'synced' => $count]);
    }
} catch (Throwable $e) {
    error_log('[geotab_sync_zones] ' . $e->getMessage());
    if ($isCli) {
        fwrite(STDERR, '[geotab_sync_zones] ERROR — ' . $e->getMessage() . "\n");
        exit(1);
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
