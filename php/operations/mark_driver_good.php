<?php
include '../config/config.php'; // adjust path if needed
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $driver_id = $_POST['driver_id'];

    date_default_timezone_set("Asia/Manila");
    $done_date = date("Y-m-d H:i:s");

    // Make sure the snapshot column exists (self-bootstrapping). It holds the
    // driver's operational status captured at the moment a violation blocked
    // them, so clearing the violation restores that status instead of forcing
    // everyone back to 'Good'.
    try { pt_ensure_column($conn, 'drivers', 'driver_prev_status', "VARCHAR(50) DEFAULT NULL"); }
    catch (Throwable $e) { /* best-effort */ }

    // Start transaction for safety
    $conn->beginTransaction();

    try {

        // 1️⃣ Restore the driver's pre-violation status (fall back to 'Good'
        //    when nothing was captured), and clear the snapshot.
        $updateDriver = $conn->prepare("
            UPDATE drivers
            SET driver_status = COALESCE(NULLIF(driver_prev_status, ''), 'Good'),
                driver_prev_status = NULL
            WHERE driver_id = ?
        ");
        $updateDriver->execute([$driver_id]);

        // 2️⃣ Update violation status to DONE (only active violations)
        $updateViolation = $conn->prepare("
            UPDATE violation_record
            SET 
                vr_status = 'Done',
                vr_done_date = ?
            WHERE driver_id = ?
              AND (vr_status IS NULL OR vr_status != 'Done')
        ");
        $updateViolation->execute([$done_date, $driver_id]);

        // Commit if all successful
        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Driver marked as GOOD, violations set to DONE, and dispatch availability restored.'
        ]);

    } catch (Exception $e) {

        // Rollback on error
        $conn->rollBack();

        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update driver and violations.'
        ]);
    }
}
