<?php
include "../../config/config.php";
require_once "../../lib/container_lifecycle.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $trip_id      = $_POST['trip_id'] ?? null;
    $container    = isset($_POST['container_no']) ? strtoupper(trim((string)$_POST['container_no'])) : null;
    $arrival_cy   = $_POST['arrival_cy'] ?? null;
    $departure    = $_POST['departure'] ?? null;
    $arrival_ph   = $_POST['arrival_ph'] ?? null;
    $action       = $_POST['action'] ?? null;

    if (!$trip_id) {
        echo json_encode(['status' => 'error', 'message' => 'Missing trip ID']);
        exit;
    }

    if ($container !== null && $container !== '' && !preg_match('/^[A-Z]{4}[0-9]{7}$/', $container)) {
        echo json_encode(['status' => 'error', 'message' => 'Container number must be exactly 4 capital letters followed by 7 numbers']);
        exit;
    }

    /* ---------------------------------------
       1. BUILD UPDATE QUERY DYNAMICALLY
    --------------------------------------- */
    $sql = "UPDATE trips SET
              trip_arrivaldatetime = ?,
              trip_departuredatetime = ?,
              trip_pharrivaldatetime = ?";

    $params = [$arrival_cy, $departure, $arrival_ph];

    // only update container if provided
    if (!empty($container)) {
        $sql .= ", trip_container = ?";
        $params[] = $container;
    }

    if ($action === 'done') {
        $sql .= ", trip_status = 'Done'";
    }

    $sql .= " WHERE trip_id = ?";
    $params[] = $trip_id;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => ($conn->errorInfo()[2] ?? '')]);
        exit;
    }

    if (!$stmt->execute($params)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update trip']);
        exit;
    }
if (!empty($container)) {
        $tripLookup = $conn->prepare("SELECT d_id FROM trips WHERE trip_id = ? LIMIT 1");
        $tripLookup->execute([$trip_id]);
        $tripRow = $tripLookup->fetch();
if ($tripRow && !empty($tripRow['d_id'])) {
            cl_advance_dispatch($conn, (int)$tripRow['d_id'], 'pickup');
        }
    }

    /* ---------------------------------------
       2. IF DONE → UPDATE DRIVER & UNIT
    --------------------------------------- */
    if ($action === 'done') {

        $getDispatch = $conn->prepare("
            SELECT d.driver_id, d.d_truck 
            FROM dispatch d
            INNER JOIN trips t ON t.d_id = d.d_id
            WHERE t.trip_id = ?
        ");
        $getDispatch->execute([$trip_id]);
        $result = $getDispatch;

        if ($row = $result->fetch()) {

            // update driver
            $updateDriver = $conn->prepare("
                UPDATE drivers 
                SET driver_status = 'Good' 
                WHERE driver_id = ?
            ");
            $updateDriver->execute([$row['driver_id']]);
// update unit
            $updateUnit = $conn->prepare("
                UPDATE units 
                SET unit_assign = '', 
                    driver_id = NULL, 
                    unit_assigngenset = '', 
                    unit_assigntrailer = '', 
                    unit_status = 'Good'
                WHERE unit_name = ?
            ");
            $updateUnit->execute([$row['d_truck']]);
}
}
echo json_encode(['status' => 'success', 'message' => 'Trip updated successfully']);
}
?>
