<?php
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$status = $_GET['status'] ?? 'pending';   // pending | approved | denied | consumed | all

$sql = "SELECT q.gq_id, q.d_id, q.truck_plate, q.driver_id, q.direction, q.decision,
               q.requested_at, q.decided_at, q.decided_by, q.notes,
               d.d_drivername, d.booking_no, d.d_truck, d.d_trailer, d.d_genset
        FROM gate_queue q
        LEFT JOIN dispatch d ON d.d_id = q.d_id";
if ($status !== 'all') {
    $sql .= " WHERE q.decision = '" . pt_pg_escape($conn, $status) . "'";
}
$sql .= " ORDER BY q.gq_id DESC LIMIT 100";

$res = $conn->query($sql);
$rows = [];
while ($r = $res->fetch()) { $rows[] = $r; }
echo json_encode(['status' => 'success', 'rows' => $rows]);
