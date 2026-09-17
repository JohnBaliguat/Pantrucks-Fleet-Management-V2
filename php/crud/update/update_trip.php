<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trip_id = intval($_POST['trip_id']);
    $action  = $_POST['action'];
    $now     = date("Y-m-d H:i:s");

    $field = "";

    // Get driver_id and truck used from dispatch (using trip_id to join)
    $dispatchQuery = $conn->query("
        SELECT driver_id, d_truck, d_trailer, d_genset, d_id 
        FROM dispatch 
        WHERE d_id = (SELECT d_id FROM trips WHERE trip_id = $trip_id LIMIT 1)
    ");
    $dispatchRow = ($dispatchQuery)->fetch();

    $driver_id   = $dispatchRow ? intval($dispatchRow['driver_id']) : 0;
    $truckName   = $dispatchRow ? pt_pg_escape($conn, $dispatchRow['d_truck']) : "";
    $trailerName = $dispatchRow ? pt_pg_escape($conn, $dispatchRow['d_trailer']) : "";
    $gensetName  = $dispatchRow ? pt_pg_escape($conn, $dispatchRow['d_genset']) : "";

    switch ($action) {
        case "arrival_ph":
            $field = "trip_pharrivalDateTime = '$now'";
            break;

        case "departure":
            $activity = $_POST['activity']; // Withdraw or Deliver
            if ($activity == "WITHDRAW") {
                $field = "trip_departureDateTime = '$now', withdraw_dateTime = '$now'";
            } else if ($activity == "DELIVER") {
                $field = "trip_departureDateTime = '$now', deliver_dateTime = '$now'";
            } else {
                $field = "trip_departureDateTime = '$now'";
            }
            break;

        case "arrival_cy":
            $field = "trip_arrivalDateTime = '$now'";
            break;

        case "done":
            // Mark trip done
            $field = "trip_status = 'Done'";

            // Update driver status
            if ($driver_id > 0) {
                $conn->query("UPDATE drivers SET driver_status = 'Good' WHERE driver_id = $driver_id");
            }

            // Update truck unit (clear assignment + driver)
            if (!empty($truckName)) {
                $conn->query("
                    UPDATE units 
                    SET unit_assign = '', driver_id = '', unit_assignGenset = '', unit_assignTrailer = ''
                    WHERE unit_name = '$truckName'
                ");
            }

            // Update trailer unit (clear assignment)
            if (!empty($trailerName)) {
                $conn->query("
                    UPDATE trailer 
                    SET trailer_assignTo = '',  driver_id = ''
                    WHERE trailer_name = '$trailerName'
                ");
            }

            // Update genset unit (clear assignment)
            if (!empty($gensetName)) {
                $conn->query("
                    UPDATE units 
                    SET unit_assign = '', driver_id = '', unit_assignGenset = '', unit_assignTrailer = '' 
                    WHERE unit_name = '$gensetName'
                ");
            }
            break;
    }

    if ($field != "") {
        $sql = "UPDATE trips SET $field WHERE trip_id = $trip_id";
        if ($conn->query($sql)) {
            echo "success";
        } else {
            echo "error: " . ($conn->errorInfo()[2] ?? '');
        }
    } else {
        echo "invalid";
    }
}
?>
