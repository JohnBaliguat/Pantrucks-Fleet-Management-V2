<?php
/**
 * Seed trip_rates rows for the common (segment, haulingType) combinations
 * seen in trips, one per Loaded / Empty container state.
 *
 * Rows are inserted with base_rate = additional = 0 (unpriced) and a
 * trip_key of "Segment.HaulingType.Loaded|Empty" — the admin then fills in
 * the peso amounts on the Trip Rates page. The coupon shows "Awaiting
 * payroll" until a non-zero rate is set.
 *
 * Idempotent: existing trip_rates.trip_key rows (compared on the normalised
 * key) are skipped, so re-running is safe. Near-duplicate segment / hauling
 * spellings collapse to a single row via the normaliser.
 *
 * Usage:  php php/config/seed_trip_rate_combos.php [minTrips]
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../helpers/trip_rate_lookup.php';

$minTrips = isset($argv[1]) ? max(1, (int)$argv[1]) : 20;

// Already-defined keys (normalised) — so we never duplicate.
$existing = [];
foreach ($conn->query("SELECT trip_key FROM trip_rates WHERE trip_key <> ''") as $r) {
    $existing[fleet_normalize_key_for_match((string)$r['trip_key'])] = true;
}

// Common (segment, haulingType) pairs, most-used spelling first.
$stmt = $conn->prepare(
    "SELECT trip_haulingsegment AS seg, trip_haulingtype AS ht, COUNT(*) AS c
       FROM trips
      WHERE COALESCE(trip_haulingsegment,'') <> '' AND COALESCE(trip_haulingtype,'') <> ''
      GROUP BY 1, 2
     HAVING COUNT(*) >= ?
      ORDER BY c DESC"
);
$stmt->execute([$minTrips]);

$ins = $conn->prepare(
    "INSERT INTO trip_rates (segment, activity, trip_key, base_rate, additional)
     VALUES (?, ?, ?, 0, 0)"
);

$added = 0; $skipped = 0; $seen = [];
foreach ($stmt as $row) {
    $seg = trim((string)$row['seg']);
    $ht  = trim((string)$row['ht']);
    foreach (['Loaded', 'Empty'] as $status) {
        $tripKey = $seg . '.' . $ht . '.' . $status;
        $nk = fleet_normalize_key_for_match($tripKey);
        if (isset($existing[$nk]) || isset($seen[$nk])) { $skipped++; continue; }
        $seen[$nk] = true;
        $activity = $ht . ' - ' . $status;   // readable, unique per (segment, activity)
        try {
            $ins->execute([$seg, $activity, $tripKey, ]);
            $added++;
            echo "  + {$tripKey}\n";
        } catch (PDOException $e) {
            // Unique (segment, activity) clash or similar — treat as already present.
            $skipped++;
        }
    }
}

echo "\nDone. Added {$added} rate row(s), skipped {$skipped} (already present).\n";
echo "Set the peso amounts on the Trip Rates page (base + additional).\n";
