<?php
include "../../config/config.php";

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // Get related info from dispatch (driver_id, trailer, genset)
    $dispatchQuery = $conn->query("SELECT driver_id, d_trailer, d_genset FROM dispatch WHERE d_id = $id");
    $dispatchRow = ($dispatchQuery)->fetch();

    $driver_id   = $dispatchRow ? intval($dispatchRow['driver_id']) : 0;
    $trailerName = $dispatchRow ? $dispatchRow['d_trailer'] : '';
    $gensetName  = $dispatchRow ? $dispatchRow['d_genset'] : '';

    // Update dispatch status
    $updateDispatch = "UPDATE dispatch SET d_status = 'Done' WHERE d_id = $id";
    $ok1 = $conn->query($updateDispatch);

    // Update trips status
    $updateTrips = "UPDATE trips SET trip_status = 'Done' WHERE d_id = $id";
    $ok2 = $conn->query($updateTrips);

    // Update driver status (if driver exists)
    $ok3 = true;
    if ($driver_id > 0) {
        $updateDriver = "UPDATE drivers SET driver_status = 'Good' WHERE driver_id = $driver_id";
        $ok3 = $conn->query($updateDriver);
    }

    // Update truck (clear assignment)
    $ok4 = true;
    if ($driver_id > 0) {
        $updateTruck = "
            UPDATE units 
            SET unit_assign = '', driver_id = '', unit_assignGenset = '', unit_assignTrailer = '', unit_status = 'Good' 
            WHERE driver_id = $driver_id
        ";
        $ok4 = $conn->query($updateTruck);
    }

    // Update trailer unit (clear assignment)
    $ok5 = true;
    if (!empty($trailerName)) {
        $ok5 = $conn->query("
            UPDATE trailer 
            SET trailer_assignTo = '', driver_id = '', trailer_status = 'Good'
            WHERE trailer_name = '$trailerName'
        ");
    }

    // Update genset unit (clear assignment)
    $ok6 = true;
    if (!empty($gensetName)) {
        $ok6 = $conn->query("
            UPDATE units 
            SET unit_assign = '', driver_id = '', unit_assignGenset = '', unit_assignTrailer = '', unit_status = 'Good'
            WHERE unit_name = '$gensetName'
        ");
    }

    $success = $ok1 && $ok2 && $ok3 && $ok4 && $ok5 && $ok6;

    echo json_encode(["success" => $success]);
}
?>
