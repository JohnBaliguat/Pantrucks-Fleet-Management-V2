<?php
include "../config/config.php";
session_start();

$driverID = (int)($_SESSION['user_id'] ?? ($_GET['driverID'] ?? 0));

if ($driverID <= 0) {
    echo json_encode([
        "active" => 0,
        "done"   => 0
    ]);
    exit;
}

// Active dispatches:
// - must still be in an in-flight workflow stage
// - must NOT already have any legacy trip row marked Done
// Stages must match driver/dashboard.php classification so the cards and the
// booking list stay in sync.
$activeSql = "
    SELECT COUNT(*) AS activeCount
    FROM dispatch d
    WHERE d.driver_id = ?
      AND d.workflow_stage IN (
        'dispatcher_assigned',
        'reassigned',
        'driver_accepted',
        'gate_cleared',
        'en_route',
        'delivered',
        'pending_verification'
      )
      AND NOT EXISTS (
        SELECT 1
        FROM trips t
        WHERE t.d_id = d.d_id
          AND t.trip_status = 'Done'
      )
";
$stmt = $conn->prepare($activeSql);
$stmt->execute([$driverID]);
$activeRow = $stmt->fetch();
$activeCount = (int)($activeRow['activeCount'] ?? 0);

// Completed dispatches:
// - terminal workflow states
// - OR legacy rows where any child trip is already Done
$doneSql = "
    SELECT COUNT(*) AS doneCount
    FROM dispatch d
    WHERE d.driver_id = ?
      AND (
        d.workflow_stage IN (
          'pod_captured',
          'billing_closed',
          'client_notified'
        )
        OR EXISTS (
          SELECT 1
          FROM trips t
          WHERE t.d_id = d.d_id
            AND t.trip_status = 'Done'
        )
      )
";
$stmt = $conn->prepare($doneSql);
$stmt->execute([$driverID]);
$doneRow = $stmt->fetch();
$doneCount = (int)($doneRow['doneCount'] ?? 0);

echo json_encode([
    "active" => $activeCount,
    "done"   => $doneCount
]);
