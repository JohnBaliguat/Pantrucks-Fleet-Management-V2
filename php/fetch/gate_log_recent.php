<?php
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$limit = (int)($_GET['limit'] ?? 20);
if ($limit > 100) $limit = 100;

$sql = "SELECT g.gl_id, g.d_id, g.direction, g.truck_plate, g.trailer_code, g.genset_code,
               g.driver_id, g.qr_payload, g.verified, g.mismatch_reason, g.authorised, g.logged_at,
               d.d_drivername AS driver_name, d.booking_no
        FROM gate_log g
        LEFT JOIN dispatch d ON d.d_id = g.d_id
        ORDER BY g.gl_id DESC LIMIT ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$limit]);
$res = $stmt;
$rows = [];
while ($r = $res->fetch()) { $rows[] = $r; }
// Today's IN/OUT counters.
$today = date('Y-m-d');
$cIn = 0; $cOut = 0;
$res = $conn->query("SELECT direction, COUNT(*) c FROM gate_log WHERE DATE(logged_at) = '" . $today . "' GROUP BY direction");
while ($r = $res->fetch()) {
    if ($r['direction'] === 'IN')  $cIn  = (int)$r['c'];
    if ($r['direction'] === 'OUT') $cOut = (int)$r['c'];
}

echo json_encode([
    'status'   => 'success',
    'rows'     => $rows,
    'today_in' => $cIn,
    'today_out'=> $cOut,
]);
