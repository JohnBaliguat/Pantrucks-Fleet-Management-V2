<?php
include '../config/config.php';
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rfid'])) {
    $rfid = trim($_POST['rfid']);
    $response = ['status' => 'error', 'message' => 'Something went wrong.'];

    if ($rfid === "") {
        $response['message'] = "RFID is empty.";
        echo json_encode($response);
        exit;
    }

    // Get driver info
    $stmt = $conn->prepare("SELECT * FROM drivers WHERE driver_rfid = ?");
    $stmt->execute([$rfid]);
    $driverResult = $stmt;

    if ($driverResult->rowCount() === 0) {
        $response['message'] = "RFID not recognized.";
        echo json_encode($response);
        exit;
    }

    $driver = $driverResult->fetch();
    $driverNameFormatted = strtoupper(trim($driver['driver_lname'])) . ", " . strtoupper(trim($driver['driver_fname']));

    // Get dispatch info
    $stmt = $conn->prepare("SELECT * FROM dispatch WHERE driver_id = ? ORDER BY d_datetime DESC LIMIT 1");
    $stmt->execute([$driver['driver_id']]);
    $dispatchResult = $stmt;

    $dispatch = $dispatchResult->rowCount() > 0 ? $dispatchResult->fetch() : null;

    // Get Trip 1 segment if there is a dispatch
    $tripSegment = "";
    if ($dispatch) {
        $stmt = $conn->prepare("SELECT * FROM trips WHERE d_id = ? AND trip_type = 'Trip 1' LIMIT 1");
        $stmt->execute([$dispatch['d_id']]);
        $tripResult = $stmt;

        if ($tripResult->rowCount() > 0) {
            $trip = $tripResult->fetch();
            $tripSegment = $trip['trip_haulingsegment'];

            if ($trip['trip_status'] !== "On Trip") {
                $conn->query("UPDATE trips SET trip_status = 'On Trip' WHERE trip_id = {$trip['trip_id']}");
            }
        }
    }

    // Check the last in/out record
    $stmt = $conn->prepare("SELECT * FROM outin WHERE drivers_name = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$driverNameFormatted]);
    $outinResult = $stmt;

    $status = "Check In"; // default to Check In
    if ($outinResult->rowCount() > 0) {
        $lastOutin = $outinResult->fetch();
        $status = ($lastOutin['status'] === "Check In") ? "Check Out" : "Check In";
    }

    // ✅ Restrict checkout
    if ($status === "Check Out" && $driver['driver_status'] !== "Dispatch") {
        $response['status'] = "error";
        $response['message'] = "Driver is not dispatched. Cannot Check Out.";
        echo json_encode($response);
        exit;
    }

    // Insert to outin
    $stmt = $conn->prepare("INSERT INTO outin (drivers_name, truck_name, trailer_name, genset_name, segment_trip, date, time, status) 
                            VALUES (?, ?, ?, ?, ?, CURRENT_DATE, CURRENT_TIME, ?)");
    $truck = $dispatch ? $dispatch['d_truck'] : "";
    $trailer = $dispatch ? $dispatch['d_trailer'] : "";
    $genset = $dispatch ? $dispatch['d_genset'] : "";
    

    try {
        $stmt->execute([$driverNameFormatted, $truck, $trailer, $genset, $tripSegment, $status]);
        $newStatus = ($status === "Check In") ? "On Trip" : "Good";

        $stmtDrv = $conn->prepare("UPDATE drivers SET driver_status = ? WHERE driver_id = ?");
        $stmtDrv->execute([$newStatus, $driver['driver_id']]);

        if (!empty($truck)) {
            $stmt = $conn->prepare("UPDATE units SET unit_status = ? WHERE unit_name = ?");
            $stmt->execute([$newStatus, $truck]);
        }

        if (!empty($genset)) {
            $stmt = $conn->prepare("UPDATE units SET unit_status = ? WHERE unit_name = ?");
            $stmt->execute([$newStatus, $genset]);
        }

        if (!empty($trailer)) {
            $stmt = $conn->prepare("UPDATE trailer SET trailer_status = ? WHERE trailer_name = ?");
            $stmt->execute([$newStatus, $trailer]);
        }

        $response['status'] = "success";
        $response['message'] = "$status successful for $driverNameFormatted.";
        $response['driver_name'] = $driverNameFormatted;
        $response['truck'] = $truck;
        $response['trailer'] = $trailer;
        $response['genset'] = $genset;
        $response['segment'] = $tripSegment;
        $response['profile_image'] = !empty($driver['driver_image']) ? $driver['driver_image'] : 'assets/images/profile/user-7.jpg';
    } catch (PDOException $e) {
        $response['message'] = "Failed to insert into outin table.";
    }

    echo json_encode($response);
}
?>
