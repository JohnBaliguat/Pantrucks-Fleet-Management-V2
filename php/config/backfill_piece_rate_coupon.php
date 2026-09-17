<?php
/**
 * Backfill trips.piece_rate for rows still at 0, using the SAME matcher the
 * Trip Verification Coupon uses (fleet_compute_trip_piece_rate /
 * fleet_lookup_rate_by_trip_key).
 *
 * WHY A PHP SCRIPT (not another SQL migration):
 *   The SQL backfills (026/027) match trip_rates only by a plain
 *   lower(segment) + activity-ends-with-Loaded/Empty rule, extended with a
 *   hand-maintained alias list. The coupon's PHP matcher also matches dotted
 *   trip_key SKUs and applies the alias maps in php/helpers/trip_rate_lookup.php
 *   (e.g. "ABC CATEEL"->"ABCCAT", "Reefer Van"->"RV"). So the coupon can price
 *   trips the SQL backfill left at 0. This script closes exactly that gap, so
 *   stored piece_rate == the rate the coupon shows.
 *
 * SAFE:
 *   * DRY RUN by default — prints what WOULD change and writes nothing.
 *     Pass  --apply  to actually update.
 *   * Only touches trips where piece_rate = 0 / NULL (never overwrites a rate).
 *   * Skips Service trips.
 *   * Only writes rows where the matcher returns a rate > 0.
 *   * Records every change to pt_piece_rate_backfill (same table 026/027 use),
 *     so it is reversible.
 *
 * USAGE (from the project root):
 *   php php/config/backfill_piece_rate_coupon.php            # dry run
 *   php php/config/backfill_piece_rate_coupon.php --apply     # apply changes
 *
 * REVERT (undo this + the 026/027 stamping):
 *   UPDATE trips t SET piece_rate = b.old_rate
 *     FROM pt_piece_rate_backfill b WHERE t.trip_id = b.trip_id;
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This backfill runs from the CLI only.\n");
}

require_once __DIR__ . '/config.php';                 // $conn
require_once __DIR__ . '/../helpers/trip_rate_lookup.php';

$APPLY = in_array('--apply', $argv, true);

fwrite(STDOUT, $APPLY
    ? "=== piece_rate backfill (APPLY — will write) ===\n"
    : "=== piece_rate backfill (DRY RUN — no changes; pass --apply to write) ===\n");

// Ensure the reversible audit table exists (same shape as 026/027).
$conn->exec(
    "CREATE TABLE IF NOT EXISTS pt_piece_rate_backfill (
        trip_id    INTEGER PRIMARY KEY,
        old_rate   NUMERIC(12,2) NOT NULL,
        new_rate   NUMERIC(12,2) NOT NULL,
        segment    VARCHAR(200)  NOT NULL DEFAULT '',
        status     VARCHAR(10)   NOT NULL DEFAULT '',
        applied_at TIMESTAMP     NOT NULL DEFAULT NOW()
    )"
);

// Candidate trips: unrated, non-service.
$sel = $conn->query(
    "SELECT trip_id, trip_haulingsegment, trip_haulingtype, trip_containerstat
       FROM trips
      WHERE (piece_rate IS NULL OR piece_rate = 0)
        AND (trip_purpose IS NULL OR trip_purpose <> 'Service')
      ORDER BY trip_id"
);

// Memoise lookups — trip_rates is tiny and many trips share a SKU.
$cache = [];
$rateFor = function (string $seg, string $type, string $stat) use ($conn, &$cache): float {
    $key = strtoupper(trim($seg)) . '|' . strtoupper(trim($type)) . '|' . strtoupper(trim($stat));
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = fleet_compute_trip_piece_rate($conn, $seg, $type, $stat);
    }
    return $cache[$key];
};

$upd = $conn->prepare("UPDATE trips SET piece_rate = ? WHERE trip_id = ? AND (piece_rate IS NULL OR piece_rate = 0)");
$bak = $conn->prepare(
    "INSERT INTO pt_piece_rate_backfill (trip_id, old_rate, new_rate, segment, status)
     VALUES (?, 0, ?, ?, ?)
     ON CONFLICT (trip_id) DO UPDATE SET new_rate = EXCLUDED.new_rate, applied_at = NOW()"
);

$scanned = 0;
$matched = 0;
$total   = 0.0;
$bySeg   = [];   // segment => ['n' => int, 'sum' => float]

if ($APPLY) { $conn->beginTransaction(); }

while ($t = $sel->fetch()) {
    $scanned++;
    $seg  = (string)($t['trip_haulingsegment'] ?? '');
    $type = (string)($t['trip_haulingtype'] ?? '');
    $stat = (string)($t['trip_containerstat'] ?? '');
    $rate = $rateFor($seg, $type, $stat);
    if ($rate <= 0) { continue; }

    $matched++;
    $total += $rate;
    $segLabel = trim($seg) !== '' ? trim($seg) : '(blank)';
    if (!isset($bySeg[$segLabel])) { $bySeg[$segLabel] = ['n' => 0, 'sum' => 0.0]; }
    $bySeg[$segLabel]['n']++;
    $bySeg[$segLabel]['sum'] += $rate;

    if ($APPLY) {
        $statWord = stripos($stat, 'loaded') !== false ? 'loaded'
                  : (stripos($stat, 'empty') !== false ? 'empty' : '');
        $bak->execute([(int)$t['trip_id'], $rate, $seg, $statWord]);
        $upd->execute([$rate, (int)$t['trip_id']]);
    }
}

if ($APPLY) { $conn->commit(); }

// ---- Report ---------------------------------------------------------
ksort($bySeg);
fwrite(STDOUT, "\nBy segment:\n");
fwrite(STDOUT, str_pad('SEGMENT', 26) . str_pad('TRIPS', 10) . "SUBTOTAL\n");
foreach ($bySeg as $label => $agg) {
    fwrite(STDOUT, str_pad($label, 26)
        . str_pad((string)$agg['n'], 10)
        . '₱' . number_format($agg['sum'], 2) . "\n");
}

fwrite(STDOUT, "\n--------------------------------------------\n");
fwrite(STDOUT, "Scanned (unrated, non-service): $scanned\n");
fwrite(STDOUT, "Matched by coupon rule        : $matched\n");
fwrite(STDOUT, "Still unmatched               : " . ($scanned - $matched) . "\n");
fwrite(STDOUT, "Total peso value              : ₱" . number_format($total, 2) . "\n");
fwrite(STDOUT, $APPLY
    ? "\nAPPLIED. Recorded to pt_piece_rate_backfill (reversible).\n"
    : "\nDRY RUN only — nothing written. Re-run with --apply to commit.\n");
