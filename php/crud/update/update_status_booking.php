<?php
include "../../config/config.php";

if (isset($_POST['d_id'])) {
    $id = intval($_POST['d_id']);

    // Get trip container activity
    $tripQuery = $conn->query("SELECT trip_container, container_activity, trip_to, trip_containerStat FROM trips WHERE d_id = $id");
    $tripRow = ($tripQuery)->fetch();
    $containerActivity = $tripRow ? trim(strtolower($tripRow['container_activity'])) : "";
    $tripContainer = $tripRow ? $tripRow['trip_container'] : "";
    $tripDestination = $tripRow ? $tripRow['trip_to'] : "";
    $containerStat = $tripRow ? $tripRow['trip_containerstat'] : "";

    // Get driver_id and truck used from dispatch
    $dispatchQuery = $conn->query("SELECT driver_id, d_truck, d_trailer, d_genset, d_driverName FROM dispatch WHERE d_id = $id");
    $dispatchRow = ($dispatchQuery)->fetch();

    $driver_id   = $dispatchRow ? intval($dispatchRow['driver_id']) : 0;
    $truckName   = $dispatchRow ? pt_pg_escape($conn, $dispatchRow['d_truck']) : "";
    $trailerName = $dispatchRow ? pt_pg_escape($conn, $dispatchRow['d_trailer']) : "";
    $gensetName  = $dispatchRow ? pt_pg_escape($conn, $dispatchRow['d_genset']) : "";
    $driverName  = $dispatchRow ? pt_pg_escape($conn, $dispatchRow['d_drivername']) : "";

    // Prepare extra update based on container activity
    $extraUpdate = "";
    if ($containerActivity === "deliver") {
        $extraUpdate = ", deliver_dateTime = NOW()";
    } elseif ($containerActivity === "withdraw") {
        $extraUpdate = ", withdraw_dateTime = NOW()";
    } else {
        // If no activity, update both
        $extraUpdate = ", deliver_dateTime = NOW(), withdraw_dateTime = NOW()";
    }

    // Update trips status + dateTime field
    $ok2 = $conn->query("
        UPDATE trips 
        SET trip_status = 'Done' $extraUpdate
        WHERE d_id = $id
    ");

    // ✅ INSERT into container_activity (NOT UPDATE)
    $okContainer = true;
    if (!empty($tripContainer)) {
        $insertContainer = "
            INSERT INTO container_activity 
                (container_name, container_location, truck_no, trailer_no, genset_no, driver_name, container_status, trip_status, Date)
            VALUES
                ('$tripContainer', '$tripDestination', '$truckName', '$trailerName', '$gensetName', '$driverName', '$containerStat', 'Done', NOW())
        ";
        $okContainer = $conn->query($insertContainer);
    }

    // Update driver status (if driver exists)
    $ok3 = true;
    if ($driver_id > 0) {
        $ok3 = $conn->query("UPDATE drivers SET driver_status = 'Good' WHERE driver_id = $driver_id");
    }

    // Update truck unit (clear assignment + driver)
    $ok4 = true;
    if (!empty($truckName)) {
        $ok4 = $conn->query("
            UPDATE units 
            SET unit_assign = '', driver_id = '', unit_assignGenset = '', unit_assignTrailer = '', unit_status = 'Good'
            WHERE unit_name = '$truckName'
        ");
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

    $success = $ok2 && $ok3 && $ok4 && $ok5 && $ok6;

    echo json_encode(["success" => $success]);
}
?>
