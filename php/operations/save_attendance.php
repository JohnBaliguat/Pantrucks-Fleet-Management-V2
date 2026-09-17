<?php
include '../config/config.php';
date_default_timezone_set("Asia/Manila");

header('Content-Type: application/json');

// Force MySQL error reporting (optional but recommended)


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

$raw = $_POST['attendance'] ?? '';
$attendanceData = json_decode($raw, true);

if (!is_array($attendanceData)) {
    echo json_encode(["status" => "error", "message" => "Invalid attendance data."]);
    exit;
}

$date = date('Y-m-d');
$allowedStatuses = ['Present', 'VL', 'SL', 'Absent'];
$saved = 0;
$skipped = 0;

try {
    foreach ($attendanceData as $item) {
        $driver_id = isset($item['driver_id']) ? intval($item['driver_id']) : 0;
        $status = $item['status'] ?? '';
        $remarks = $item['remarks'] ?? '';
        $datePrepared = $item['datePrepared'] ?? null;
        $dateStart = $item['dateStart'] ?? null;
        $vlslDate = $item['vlslDate'] ?? null;
        $timeIn = $item['timeIn'] ?? null;

        if (empty($driver_id) || !in_array($status, $allowedStatuses, true)) {
            $skipped++;
            continue;
        }

        // Prevent duplicate attendance for same driver on same date (prepared)
        $checkStmt = $conn->prepare("SELECT da_id FROM drivers_attendance 
            WHERE da_status IN ('Present', 'VL', 'SL', 'Absent') AND driver_id = ? AND da_date = ? LIMIT 1");
        $checkStmt->execute([$driver_id, $date]);
if ($checkStmt->rowCount() > 0) {
$skipped++;
            continue;
        }
        // Insert using prepared statements
        if (!empty($datePrepared) && !empty($dateStart) && !empty($vlslDate)) {
            $stmt = $conn->prepare("INSERT INTO drivers_attendance
                (driver_id, da_status, da_remarks, da_date, vl_sl_dateprepared, vl_sl_datestart, vl_sl_date)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$driver_id, $status, $remarks, $date, $datePrepared, $dateStart, $vlslDate]);
        } elseif (!empty($timeIn)) {
            $stmt = $conn->prepare("INSERT INTO drivers_attendance
                (driver_id, da_status, da_remarks, da_date, da_timein)
                VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$driver_id, $status, $remarks, $date, $timeIn]);
        } else {
            $skipped++;
            continue;
        }
        $saved++;

        $cs = $conn->prepare("SELECT driver_status FROM drivers WHERE driver_id = ?");
        $cs->execute([$driver_id]);
        $currentStatus = $cs->fetchColumn();
        if ($currentStatus === false) {
            $currentStatus = '';
        }

        $hasActiveViolation = pt_driver_has_active_violation($conn, $driver_id);

        // Update driver status (prepared)
        $newStatus = null;
        if ($currentStatus !== "Dispatch" && $hasActiveViolation) {
            $newStatus = "With Violation";
        } else {
            switch ($status) {
                case "Present":
                    if ($currentStatus !== "Dispatch") {
                        $newStatus = "Good";
                    }
                    break;
                case "Absent": $newStatus = "Absent"; break;
                case "VL":     $newStatus = "VL"; break;
                case "SL":     $newStatus = "SL"; break;
            }
        }
        if ($newStatus !== null) {
            $up = $conn->prepare("UPDATE drivers SET driver_status = ? WHERE driver_id = ?");
            $up->execute([$newStatus, $driver_id]);
}
    }

    echo json_encode([
        "status"  => "success",
        "message" => "Attendance saved.",
        "saved"   => $saved,
        "skipped" => $skipped
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
exit;
