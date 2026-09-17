<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}

// Pull the booking's customer_segment and the active trip's hauling segment
// so the verification cards can show both alongside the trip route — the
// dispatcher uses these to spot mismatched paperwork at a glance.
$sql = "SELECT d.d_id, d.booking_no, d.costumer, d.d_truck, d.d_drivername, d.driver_id,
               d.workflow_stage, d.driver_accepted_at, d.trip_started_at,
               b.customer_segment, b.trip_from, b.trip_to,
               t.trip_haulingsegment, t.trip_haulingtype, t.trip_containerstat,
               p.pod_id, p.photo1_path, p.photo2_path, p.photo3_path, p.signature_path,
               p.signed_by, p.lat, p.lng, p.captured_at, p.verified_by, p.verified_at,
               FLOOR(EXTRACT(EPOCH FROM (p.captured_at - COALESCE(d.trip_started_at, d.driver_accepted_at))) / 60) AS trip_minutes
        FROM dispatch d
        LEFT JOIN booking b ON b.booking_no = d.booking_no
        LEFT JOIN trips t
               ON t.trip_id = (
                    -- Booking leg ('Trip 1'); multi-leg tickets add extra legs
                    -- with higher trip_id, so prefer Trip 1 over a plain MAX.
                    SELECT t2.trip_id
                    FROM trips t2
                    WHERE t2.d_id = d.d_id
                    ORDER BY (t2.trip_type = 'Trip 1') DESC, t2.trip_id DESC
                    LIMIT 1
               )
        LEFT JOIN pod_capture p
               ON p.pod_id = (
                    SELECT MAX(p2.pod_id)
                    FROM pod_capture p2
                    WHERE p2.d_id = d.d_id
               )
        WHERE d.workflow_stage = 'pending_verification'
        ORDER BY d.d_id DESC LIMIT 100";
$res = $conn->query($sql);
$rows = [];
while ($r = $res->fetch()) { $rows[] = $r; }
echo json_encode(['status' => 'success', 'rows' => $rows]);
