<?php
// Deletes a dispatch row + its child trip rows (and best-effort child rows
// in related tables that reference d_id). Wrapped in a transaction.
//
// Approval gate: a Dispatcher calling this endpoint creates a pending
// deletion request instead of deleting. Admin / Dispatch Admin delete
// directly (they ARE the approval) and any matching pending request is
// auto-marked approved.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/trip_deletion_requests.php';

$role = $_SESSION['user_type'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
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

$dId = (int)($_POST['d_id'] ?? 0);
if ($dId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'd_id required']);
    exit;
}

// Dispatcher path — create a pending request, don't touch the data.
if ($role === 'Dispatcher') {
    $stmt = $conn->prepare("SELECT user_fname, user_lname FROM \"user\" WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    $userName = trim(($u['user_fname'] ?? '') . ' ' . ($u['user_lname'] ?? ''));

    $created = td_create_request($conn, $dId, $userId, $userName, $role);
    echo json_encode([
        'status'  => 'pending',
        'message' => $created
            ? 'Deletion request submitted. Awaiting Admin / Dispatch Admin approval.'
            : 'A deletion request for this row is already pending approval.',
    ]);
    exit;
}

try {
    $conn->beginTransaction();

    // Delete from related tables when they exist. Order matters only for
    // tables that have FKs referencing one another — dispatch is the parent.
    $childTables = [
        'trips',
        'workflow_event',
        'pod_capture',
        'pickup_capture',
        'trailer_jackup',
        'gate_queue',
        'receipt_acknowledge',  // skipped automatically if it has no d_id col
        'dispatch_receipt',
    ];

    foreach ($childTables as $tbl) {
        if (!pt_table_exists($conn, $tbl)) continue;
        if (!pt_column_exists($conn, $tbl, 'd_id')) continue;
        try {
            $stmt = $conn->prepare("DELETE FROM {$tbl} WHERE d_id = ?");
            $stmt->execute([$dId]);
        } catch (Throwable $e) {
            // Don't abort the whole delete if one child table refuses (e.g. FK
            // chain we don't know about) — record the issue and continue.
            error_log("delete_trip_report: child delete from {$tbl} failed for d_id={$dId}: " . $e->getMessage());
        }
    }

    $stmt = $conn->prepare("DELETE FROM dispatch WHERE d_id = ?");
    $stmt->execute([$dId]);
    $deleted = $stmt->rowCount();

    if ($deleted === 0) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'No matching dispatch found.']);
        exit;
    }

    // If this delete satisfies a pending Dispatcher request, mark it approved
    // so the audit trail shows who approved and when.
    td_mark_approved($conn, $dId, $userId);

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Trip report entry deleted.']);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Delete failed: ' . $e->getMessage()]);
}
