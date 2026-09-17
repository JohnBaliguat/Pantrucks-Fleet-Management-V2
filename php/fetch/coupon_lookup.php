<?php
// Payroll lookup: given a coupon control number, return the dispatch header
// + its billable trips so Payroll can auto-fill details and assign rates.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Payroll', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}

$controlNo = strtoupper(trim($_GET['control_no'] ?? ''));
if ($controlNo === '') {
    echo json_encode(['status' => 'error', 'message' => 'Control number required']);
    exit;
}

try {
    $stmt = $conn->prepare(
        "SELECT d.d_id, d.control_no, d.booking_no, d.d_drivername, d.d_truck,
                d.d_datetime, d.approved_at, d.payroll_status,
                u.user_fname AS ap_fname, u.user_lname AS ap_lname
           FROM dispatch d
           LEFT JOIN \"user\" u ON u.user_id = d.approved_by
          WHERE UPPER(d.control_no) = ? LIMIT 1"
    );
    $stmt->execute([$controlNo]);
    $d = $stmt->fetch();
    if (!$d) {
        echo json_encode(['status' => 'error', 'message' => 'No approved trip found for ' . $controlNo]);
        exit;
    }

    // Billable legs only — same filter the dispatcher verification used:
    // Trip 1, or any leg that carries a container_activity.
    $ts = $conn->prepare(
        "SELECT trip_id, trip_type, trip_from, trip_to,
                trip_haulingsegment, trip_haulingtype,
                container_activity, trip_sku, trip_container, trip_containerstat,
                piece_rate
           FROM trips
          WHERE d_id = ?
            AND (trip_type = 'Trip 1' OR TRIM(COALESCE(container_activity, '')) <> '')
          ORDER BY trip_id ASC"
    );
    $ts->execute([(int)$d['d_id']]);
    $trips = [];
    while ($r = $ts->fetch()) {
        $trips[] = [
            'trip_id'             => (int)$r['trip_id'],
            'trip_type'           => $r['trip_type'],
            'trip_from'           => $r['trip_from'],
            'trip_to'             => $r['trip_to'],
            'trip_haulingsegment' => $r['trip_haulingsegment'],
            'trip_haulingtype'    => $r['trip_haulingtype'],
            'container_activity'  => $r['container_activity'],
            'trip_sku'            => $r['trip_sku'],
            'trip_container'      => $r['trip_container'],
            'trip_containerstat'  => $r['trip_containerstat'],
            'piece_rate'          => (float)$r['piece_rate'],
        ];
    }

    $approver = trim(trim((string)($d['ap_fname'] ?? '')) . ' ' . trim((string)($d['ap_lname'] ?? '')));
    $dateSrc  = $d['approved_at'] ?: ($d['d_datetime'] ?? null);

    echo json_encode([
        'status' => 'success',
        'dispatch' => [
            'd_id'           => (int)$d['d_id'],
            'control_no'     => $d['control_no'],
            'booking_no'     => $d['booking_no'],
            'driver'         => $d['d_drivername'],
            'truck'          => $d['d_truck'],
            'date'           => $dateSrc ? date('M d, Y H:i', strtotime((string)$dateSrc)) : '',
            'approved_by'    => $approver,
            'payroll_status' => $d['payroll_status'],
        ],
        'trips' => $trips,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
