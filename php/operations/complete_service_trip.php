<?php
// Mark a service trip as Done. Service trips (costumer = 'INTERNAL') have no
// driver POD flow, so a dispatcher closes them out here.
// For Admin / Dispatch Admin / Dispatcher.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'Dispatch Admin', 'Dispatcher'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$dId   = (int)($_POST['d_id'] ?? 0);
$actor = (int)($_SESSION['user_id'] ?? 0);
if ($dId <= 0) { echo json_encode(['status' => 'error', 'message' => 'd_id required']); exit; }

$conn->beginTransaction();
try {
    // Confirm this is a service-trip dispatch and grab its equipment.
    $stmt = $conn->prepare(
        "SELECT d.driver_id, d.d_truck, d.d_trailer, d.d_genset, d.booking_no, d.workflow_stage
         FROM dispatch d
         WHERE d.d_id = ? AND d.costumer = 'INTERNAL'
         LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$dId]);
    $d = $stmt->fetch();
    if (!$d) { throw new Exception('Service trip not found.'); }

    if (in_array($d['workflow_stage'], ['pod_captured', 'billing_closed', 'client_notified'], true)) {
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Service trip already done.']);
        exit;
    }

    $driverId = (int)$d['driver_id'];
    $truck    = trim((string)$d['d_truck']);
    $trailer  = trim((string)$d['d_trailer']);
    $genset   = trim((string)$d['d_genset']);

    // Close the trip + dispatch.
    $conn->prepare("UPDATE trips SET trip_status = 'Done' WHERE d_id = ? AND trip_purpose = 'Service'")->execute([$dId]);
    $conn->prepare(
        "UPDATE dispatch SET workflow_stage = 'pod_captured', trip_completed_at = NOW(), workflow_updated_at = NOW() WHERE d_id = ?"
    )->execute([$dId]);

    // If the driver has no other in-flight job, free them and their equipment.
    $hasOther = false;
    if ($driverId > 0) {
        $q = $conn->prepare(
            "SELECT 1 FROM dispatch
             WHERE driver_id = ? AND d_id <> ?
               AND workflow_stage IN ('dispatcher_assigned','reassigned','driver_accepted','gate_cleared','en_route','pending_verification','delivered')
             LIMIT 1"
        );
        $q->execute([$driverId, $dId]);
        $hasOther = (bool)$q->fetchColumn();
    }

    if (!$hasOther) {
        if ($driverId > 0) {
            $conn->prepare("UPDATE drivers SET driver_status = 'Active' WHERE driver_id = ?")->execute([$driverId]);
        }
        if ($truck !== '') {
            $conn->prepare("UPDATE units SET unit_status = 'Good', driver_id = 0, unit_assign = '' WHERE unit_name = ? AND driver_id = ?")
                 ->execute([$truck, $driverId]);
        }
        if ($trailer !== '') {
            $conn->prepare("UPDATE trailer SET trailer_assignto = '', driver_id = 0 WHERE trailer_name = ? AND driver_id = ?")
                 ->execute([$trailer, $driverId]);
        }
        if ($genset !== '') {
            $conn->prepare("UPDATE units SET unit_status = 'Good', driver_id = 0, unit_assign = '' WHERE unit_name = ? AND driver_id = ?")
                 ->execute([$genset, $driverId]);
        }
    }

    $conn->prepare(
        "INSERT INTO workflow_event (d_id, booking_no, stage, actor_role, actor_id, notes)
         VALUES (?, ?, 'manual_completed', ?, ?, ?)"
    )->execute([$dId, (string)$d['booking_no'], $role, $actor, 'Service trip marked done by ' . $role . ' #' . $actor]);

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Service trip marked as done.']);
} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
