<?php
include '../config/config.php';
date_default_timezone_set("Asia/Manila");

header('Content-Type: application/json');

// ==============================
// FORCE MYSQL ERROR REPORTING
// ==============================


try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    // ==============================
    // GET INPUTS SAFELY
    // ==============================
    $driver_id     = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 0;
    $controlNo     = $_POST['controlNo'] ?? '';
    $status        = $_POST['status'] ?? '';
    $remarks       = $_POST['remarks'] ?? '';
    $dateStart     = $_POST['dateStart'] ?? null;
    $dateEnd       = $_POST['dateEnd'] ?? null;
    $datePrepared  = $_POST['datePrepared'] ?? null;

    $date        = date('Y-m-d');
    $currentTime = date('Y-m-d H:i:s');

    if (empty($driver_id) || empty($status)) {
        throw new Exception("Driver ID and Status are required.");
    }

    // ==============================
    // START TRANSACTION (IMPORTANT)
    // ==============================
    $conn->beginTransaction();

    // ==============================
    // CHECK DUPLICATE
    // ==============================
    $checkStmt = $conn->prepare("SELECT da_id FROM drivers_attendance WHERE driver_id=? AND da_date=? LIMIT 1");
    $checkStmt->execute([$driver_id, $date]);
    if ($checkStmt->rowCount() > 0) {
        throw new Exception("Attendance already recorded for today.");
    }

    // ==============================
    // INSERT LOGIC
    // ==============================
    if ($status === "Present") {

        $stmt = $conn->prepare("INSERT INTO drivers_attendance
            (driver_id, da_status, da_remarks, da_date, da_timein, da_controlno)
            VALUES (?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception("Failed to prepare insert statement.");
        }
        $stmt->execute([$driver_id, $status, $remarks, $date, $currentTime, $controlNo]);

    } elseif ($status === "VL" || $status === "SL") {

        if (empty($dateStart) || empty($dateEnd) || empty($datePrepared)) {
            throw new Exception("Date Prepared, Start Date, and End Date are required for Leave.");
        }

        if ($dateStart > $dateEnd) {
            throw new Exception("End date must be after Start date.");
        }

        // NOTE: the end-of-leave column is `vl_sl_date` (no "End" suffix).
        $stmt = $conn->prepare("INSERT INTO drivers_attendance
            (driver_id, da_status, da_remarks, da_date, vl_sl_dateprepared, vl_sl_datestart, vl_sl_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception("Failed to prepare insert statement.");
        }
        $stmt->execute([$driver_id, $status, $remarks, $date, $datePrepared, $dateStart, $dateEnd]);

    } else {
        throw new Exception("Unsupported status: " . $status);
    }

    $insert_id = $conn->lastInsertId();

    // ==============================
    // GET DRIVER CURRENT STATUS
    // ==============================
    $checkStatusStmt = $conn->prepare("SELECT driver_status FROM drivers WHERE driver_id=?");
    $checkStatusStmt->execute([$driver_id]);
    $currentDriverStatus = $checkStatusStmt->fetchColumn();

    $hasActiveViolation = pt_driver_has_active_violation($conn, $driver_id);
    $newStatus = null;

    if ($currentDriverStatus !== "Dispatch" && $hasActiveViolation) {
        $newStatus = "With Violation";
    } else {
        switch ($status) {
            case "Present":
                if ($currentDriverStatus !== "Dispatch") {
                    $newStatus = "Good";
                }
                break;

            case "Absent":
                $newStatus = "Absent";
                break;

            case "VL":
                $newStatus = "VL";
                break;

            case "SL":
                $newStatus = "SL";
                break;
        }
    }

    if (!empty($newStatus)) {
        $updateStmt = $conn->prepare("UPDATE drivers SET driver_status=? WHERE driver_id=?");
        $updateStmt->execute([$newStatus, $driver_id]);
}

    // ==============================
    // COMMIT TRANSACTION
    // ==============================
    $conn->commit();

    echo json_encode([
        "status"    => "success",
        "message"   => "Attendance saved successfully.",
        "insert_id" => $insert_id
    ]);

} catch (Exception $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
exit;
?>
