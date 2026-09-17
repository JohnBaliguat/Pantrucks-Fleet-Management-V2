<?php
// Equipment Utilization grid data. For Admin / Dispatch Admin / Dispatcher.
// Returns every truck, trailer and genset with its trip counts in the selected
// date range so the front-end can colour each cell:
//   • white      — not utilized (no trips in range)
//   • light blue — has active trip(s) (count shown)
//   • orange     — utilized but idle (had trips in range, none active)
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'Dispatch Admin', 'Dispatcher'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

// Date range — defaults to today. Validated to YYYY-MM-DD.
function eu_date(?string $v, string $default): string {
    $v = trim((string)$v);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $default;
}
$today = date('Y-m-d');
$from  = eu_date($_GET['from'] ?? '', $today);
$to    = eu_date($_GET['to'] ?? '', $today);
if ($from > $to) { [$from, $to] = [$to, $from]; }

// Per-equipment trip counts in range, keyed by the dispatch field. Returns
// map name => ['total' => n, 'active' => n].
function eu_counts(PDO $conn, string $field, string $from, string $to): array {
    $sql = "SELECT d.$field AS name,
                   COUNT(*) AS total,
                   SUM(CASE WHEN t.trip_status = 'Active' THEN 1 ELSE 0 END) AS active
            FROM trips t
            JOIN dispatch d ON d.d_id = t.d_id
            WHERE d.d_datetime::date BETWEEN ? AND ?
              AND COALESCE(TRIM(d.$field), '') <> ''
            GROUP BY d.$field";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$from, $to]);
    $map = [];
    while ($r = $stmt->fetch()) {
        $map[strtoupper(trim((string)$r['name']))] = [
            'total'  => (int)$r['total'],
            'active' => (int)$r['active'],
        ];
    }
    return $map;
}

$truckCounts   = eu_counts($conn, 'd_truck', $from, $to);
$trailerCounts = eu_counts($conn, 'd_trailer', $from, $to);
$gensetCounts  = eu_counts($conn, 'd_genset', $from, $to);

// Build a typed list against the full equipment roster so idle units show too.
function eu_build(array $names, array $counts): array {
    $out = [];
    foreach ($names as $name) {
        $key = strtoupper(trim((string)$name));
        $c   = $counts[$key] ?? ['total' => 0, 'active' => 0];
        $status = $c['active'] > 0 ? 'active' : ($c['total'] > 0 ? 'idle' : 'unused');
        $out[] = [
            'name'   => $name,
            'total'  => $c['total'],
            'active' => $c['active'],
            'status' => $status,
        ];
    }
    return $out;
}

$truckNames = [];
$res = $conn->query("SELECT unit_name FROM units WHERE unit_type = 'truck' ORDER BY unit_name ASC");
while ($r = $res->fetch()) { $truckNames[] = $r['unit_name']; }

$gensetNames = [];
$res = $conn->query("SELECT unit_name FROM units WHERE unit_type = 'genset' ORDER BY unit_name ASC");
while ($r = $res->fetch()) { $gensetNames[] = $r['unit_name']; }

$trailerNames = [];
$res = $conn->query("SELECT trailer_name FROM trailer ORDER BY trailer_name ASC");
while ($r = $res->fetch()) { $trailerNames[] = $r['trailer_name']; }

$trucks   = eu_build($truckNames, $truckCounts);
$trailers = eu_build($trailerNames, $trailerCounts);
$gensets  = eu_build($gensetNames, $gensetCounts);

function eu_summary(array $list): array {
    $s = ['total' => count($list), 'active' => 0, 'idle' => 0, 'unused' => 0];
    foreach ($list as $e) { $s[$e['status']]++; }
    return $s;
}

echo json_encode([
    'status'   => 'success',
    'from'     => $from,
    'to'       => $to,
    'trucks'   => $trucks,
    'trailers' => $trailers,
    'gensets'  => $gensets,
    'summary'  => [
        'trucks'   => eu_summary($trucks),
        'trailers' => eu_summary($trailers),
        'gensets'  => eu_summary($gensets),
    ],
]);
