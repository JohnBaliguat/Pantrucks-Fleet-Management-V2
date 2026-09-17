<?php
// Foul trips for the dispatch-admin approval queue. Returns foul legs with the
// driver, rate, reason and approval status so an admin can confirm which ones
// get paid.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'Dispatch Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised', 'rows' => []]);
    exit;
}

$status = strtolower(trim($_GET['status'] ?? 'pending'));   // pending | approved | all
$where  = ['t.foul_trip = TRUE'];
if ($status === 'pending')      { $where[] = 't.foul_approved = FALSE'; }
elseif ($status === 'approved') { $where[] = 't.foul_approved = TRUE'; }
$whereSql = implode(' AND ', $where);

try {
    $sql = "
        SELECT t.trip_id, t.d_id, t.trip_type, t.trip_haulingsegment, t.trip_sku,
               t.trip_from, t.trip_to, t.piece_rate,
               t.cancelled_at, t.cancelled_reason, t.foul_approved, t.foul_approved_at,
               d.booking_no, d.d_truck, d.d_drivername, d.d_datetime,
               u.user_fname AS ap_fname, u.user_lname AS ap_lname
          FROM trips t
          JOIN dispatch d ON d.d_id = t.d_id
          LEFT JOIN \"user\" u ON u.user_id = t.foul_approved_by
         WHERE $whereSql
         ORDER BY t.foul_approved ASC, t.cancelled_at DESC NULLS LAST, t.trip_id DESC
         LIMIT 500
    ";
    $stmt = $conn->query($sql);
    $rows = [];
    while ($r = $stmt->fetch()) {
        $approver = trim(trim((string)($r['ap_fname'] ?? '')) . ' ' . trim((string)($r['ap_lname'] ?? '')));
        $rows[] = [
            'trip_id'          => (int)$r['trip_id'],
            'd_id'             => (int)$r['d_id'],
            'trip_type'        => $r['trip_type'],
            'booking_no'       => $r['booking_no'],
            'driver'           => $r['d_drivername'],
            'truck'            => $r['d_truck'],
            'date'             => $r['d_datetime'] ? date('M d, Y', strtotime((string)$r['d_datetime'])) : '',
            'segment'          => $r['trip_haulingsegment'],
            'trip_sku'         => $r['trip_sku'],
            'route'            => trim((string)$r['trip_from']) . ' → ' . trim((string)$r['trip_to']),
            'piece_rate'       => (float)$r['piece_rate'],
            'cancelled_at'     => $r['cancelled_at'] ? date('M d, Y H:i', strtotime((string)$r['cancelled_at'])) : '',
            'cancelled_reason' => $r['cancelled_reason'],
            'approved'         => (bool)$r['foul_approved'],
            'approved_at'      => $r['foul_approved_at'] ? date('M d, Y H:i', strtotime((string)$r['foul_approved_at'])) : '',
            'approved_by'      => $approver,
        ];
    }
    // Pending count for the badge.
    $pending = (int)$conn->query("SELECT COUNT(*) FROM trips WHERE foul_trip = TRUE AND foul_approved = FALSE")->fetchColumn();
    echo json_encode(['status' => 'success', 'rows' => $rows, 'count' => count($rows), 'pending' => $pending]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'rows' => []]);
}
