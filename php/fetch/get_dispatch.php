<?php
include __DIR__ . "/../config/config.php";

if (isset($_POST['d_id'])) {
    $d_id = intval($_POST['d_id']);

    // Fetch dispatch row.
    $stmt = $conn->prepare("SELECT * FROM dispatch WHERE d_id = ? LIMIT 1");
    $stmt->execute([$d_id]);
    $dispatch = $stmt->fetch();
// Fetch ALL trips for this dispatch (ordered by trip_id).
    $stmt = $conn->prepare(
        "SELECT trip_id, trip_type, trip_container, trip_containerstat, container_activity,
                trip_haulingsegment, trip_haulingtype, trip_from, trip_to, trip_status,
                segment_costumer, segment_status, foul_trip
         FROM trips WHERE d_id = ? ORDER BY trip_id ASC"
    );
    $stmt->execute([$d_id]);
    $tripResult = $stmt;

    $trips = [];
    $trip1 = null;
    $trip2 = null;
    while ($row = $tripResult->fetch()) {
        $trips[] = $row;
        if ($row['trip_type'] === 'Trip 1' && !$trip1) { $trip1 = $row; }
        if ($row['trip_type'] === 'Trip 2' && !$trip2) { $trip2 = $row; }
    }
echo json_encode([
        'success'  => true,
        'dispatch' => $dispatch,
        'trips'    => $trips,
        'trip1'    => $trip1,
        'trip2'    => $trip2,
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Missing d_id']);
}
