<?php
// =====================================================================
// Geofence engine — Phase 2.
//
// Decides which synced Geotab zone a point falls in (ray-casting
// point-in-polygon) and turns a unit's position change into zone
// entry/exit events. Called by the poller after positions are updated, and
// used ad-hoc by save_gateless.php to name the delivery location.
//
// Zones are stored in geotab_zone with points as [{lat,lng}, ...] (see
// migration 030). Kept dependency-free so it can run in a CLI poll.
// =====================================================================

if (!function_exists('pt_geofence_point_in_polygon')) {
    /**
     * Ray-casting test. $poly is [[lat,lng], ...]. Returns true if (lat,lng)
     * is inside the polygon.
     */
    function pt_geofence_point_in_polygon(float $lat, float $lng, array $poly): bool {
        $n = count($poly);
        if ($n < 3) {
            return false;
        }
        $inside = false;
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $yi = $poly[$i][0]; $xi = $poly[$i][1];   // lat, lng
            $yj = $poly[$j][0]; $xj = $poly[$j][1];
            $intersect = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }
        }
        return $inside;
    }
}

if (!function_exists('pt_geofence_load_zones')) {
    /**
     * Load active zones with parsed polygons. Cached per-request.
     * Returns [ ['zone_id'=>, 'name'=>, 'kind'=>, 'poly'=>[[lat,lng],...]], ... ]
     */
    function pt_geofence_load_zones(PDO $conn): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        try {
            $rows = $conn->query(
                "SELECT zone_id, name, kind, points FROM geotab_zone WHERE active = TRUE"
            )->fetchAll();
        } catch (Throwable $e) {
            return $cache; // table may not exist yet
        }
        foreach ($rows as $r) {
            $pts = json_decode((string)$r['points'], true);
            if (!is_array($pts) || count($pts) < 3) {
                continue;
            }
            $poly = [];
            foreach ($pts as $p) {
                if (isset($p['lat'], $p['lng']) && is_numeric($p['lat']) && is_numeric($p['lng'])) {
                    $poly[] = [(float)$p['lat'], (float)$p['lng']];
                }
            }
            if (count($poly) >= 3) {
                $cache[] = [
                    'zone_id' => (string)$r['zone_id'],
                    'name'    => (string)$r['name'],
                    'kind'    => (string)$r['kind'],
                    'poly'    => $poly,
                ];
            }
        }
        return $cache;
    }
}

if (!function_exists('pt_geofence_zone_for_point')) {
    /**
     * First active zone containing the point, or null. Smaller/earlier zones
     * win only by row order; for overlapping zones prefer specificity by
     * ordering base/gate last if needed (not required for typical setups).
     */
    function pt_geofence_zone_for_point(PDO $conn, float $lat, float $lng): ?array {
        foreach (pt_geofence_load_zones($conn) as $z) {
            if (pt_geofence_point_in_polygon($lat, $lng, $z['poly'])) {
                return $z;
            }
        }
        return null;
    }
}

if (!function_exists('pt_geofence_apply')) {
    /**
     * Reconcile a unit's current zone against where it is now. Logs an exit
     * for the old zone and/or an entry for the new one, updates units zone
     * state, and refreshes current_location to the zone name on entry.
     *
     * @param string      $posAt   'Y-m-d H:i:s' (UTC) of the fix, for the event time.
     * @return string|null The new zone_id (or null if now outside all zones).
     */
    function pt_geofence_apply(
        PDO $conn, int $unitId, string $unitName,
        float $lat, float $lng, ?string $posAt,
        ?string $currentZoneId
    ): ?string {
        $zone   = pt_geofence_zone_for_point($conn, $lat, $lng);
        $newId  = $zone['zone_id'] ?? null;
        $occurredAt = $posAt ?: date('Y-m-d H:i:s');

        if ($newId === $currentZoneId) {
            return $newId; // no boundary crossed
        }

        $logEvent = $conn->prepare(
            "INSERT INTO geotab_zone_event
                (unit_id, unit_name, zone_id, zone_name, zone_kind, event, lat, lng, occurred_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        // Exit the previous zone.
        if ($currentZoneId !== null && $currentZoneId !== '') {
            $prev = null;
            foreach (pt_geofence_load_zones($conn) as $z) {
                if ($z['zone_id'] === $currentZoneId) { $prev = $z; break; }
            }
            $logEvent->execute([
                $unitId, $unitName, $currentZoneId,
                $prev['name'] ?? '', $prev['kind'] ?? 'other',
                'exit', $lat, $lng, $occurredAt,
            ]);
        }

        // Enter the new zone.
        if ($newId !== null) {
            $logEvent->execute([
                $unitId, $unitName, $newId,
                $zone['name'], $zone['kind'],
                'entry', $lat, $lng, $occurredAt,
            ]);
            $conn->prepare(
                "UPDATE units
                    SET current_zone_id = ?, zone_entered_at = ?,
                        current_location = ?, current_location_updated_at = ?
                  WHERE unit_id = ?"
            )->execute([$newId, $occurredAt, $zone['name'], $occurredAt, $unitId]);
        } else {
            // Left all zones — clear the zone link but keep the last location text.
            $conn->prepare(
                "UPDATE units SET current_zone_id = NULL, zone_entered_at = NULL WHERE unit_id = ?"
            )->execute([$unitId]);
        }

        return $newId;
    }
}
