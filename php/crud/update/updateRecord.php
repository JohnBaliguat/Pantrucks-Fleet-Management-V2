<?php
include __DIR__ . "/../../config/config.php";
header('Content-Type: text/plain');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo 'POST required'; exit; }

$id = (int)($_POST['record_id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo 'Missing record_id'; exit; }

// Dispatch-level fields (unchanged keys for backward compat).
$driverName = $_POST['drivers_name1'] ?? '';
$truck      = $_POST['truck1']        ?? '';
$trailer    = $_POST['trailer1']      ?? '';
$genset     = $_POST['genset1']       ?? '';
$tripReceipt= $_POST['tr1']           ?? '';
$ecs        = $_POST['ecs1']          ?? '';

$sql_dispatch = "UPDATE dispatch SET d_drivername=?, d_truck=?, d_trailer=?, d_genset=?,
                                       d_tripreceipt=?, d_ecs=?
                 WHERE d_id=?";
$stmt = $conn->prepare($sql_dispatch);
$stmt->execute([$driverName, $truck, $trailer, $genset, $tripReceipt, $ecs, $id]);
// Dynamic trips — preferred path:
//   POST trip[<trip_id>][container], [status], [segment], [from], [to], [seg_costumer], [seg_status]
$tripsUpdated = 0;
if (isset($_POST['trip']) && is_array($_POST['trip'])) {
    foreach ($_POST['trip'] as $tripId => $fields) {
        $tid = (int)$tripId;
        if ($tid <= 0 || !is_array($fields)) continue;

        $container   = $fields['container']      ?? '';
        $cstatus     = $fields['status']         ?? '';
        $segment     = $fields['segment']        ?? '';
        $tripFrom    = $fields['from']           ?? '';
        $tripTo      = $fields['to']             ?? '';
        $segCost     = $fields['seg_costumer']   ?? '';
        $segStatus   = $fields['seg_status']     ?? '';

        // Look up hauling_type for the chosen segment.
        $hauType = '';
        $s = $conn->prepare("SELECT hauling_type FROM hauling WHERE hauling_segment = ? LIMIT 1");
        if ($s) {
            $s->execute([$segment]);
            $col = $s->fetchColumn();
            if ($col !== false) {
                $hauType = $col;
            }
        }

        $u = $conn->prepare(
            "UPDATE trips SET trip_container=?, trip_containerstat=?, trip_haulingsegment=?,
                                trip_haulingtype=?, trip_from=?, trip_to=?,
                                segment_costumer=?, segment_status=?
             WHERE trip_id=? AND d_id=?"
        );
        if ($u) {
            $u->execute([$container, $cstatus, $segment, $hauType,
                $tripFrom, $tripTo, $segCost, $segStatus, $tid, $id]);
            $tripsUpdated += $u->rowCount() > 0 ? 1 : 0;
}
    }
} else {
    // ---- Legacy path: fall back to the old Trip 1 / Trip 2 keys when
    // the caller hasn't been updated yet. ----------------------------
    $legacyTrips = [
        ['Trip 1', $_POST['container_no1'] ?? '', $_POST['container_status1'] ?? '',
            $_POST['hauling_segment1'] ?? '', $_POST['destination_from1'] ?? '',
            $_POST['destination_to1'] ?? ''],
        ['Trip 2', $_POST['container_no2'] ?? '', $_POST['container_status2'] ?? '',
            $_POST['hauling_segment2'] ?? '', $_POST['destination_from12'] ?? '',
            $_POST['destination_to12'] ?? ''],
    ];
    foreach ($legacyTrips as $row) {
        list($type, $container, $cstatus, $segment, $tripFrom, $tripTo) = $row;
        if ($container === '' && $segment === '' && $tripFrom === '' && $tripTo === '') continue;
        $hauType = '';
        $s = $conn->prepare("SELECT hauling_type FROM hauling WHERE hauling_segment = ? LIMIT 1");
        $s->execute([$segment]);
        $col = $s->fetchColumn();
        if ($col !== false) {
            $hauType = $col;
        }
        $u = $conn->prepare(
            "UPDATE trips SET trip_container=?, trip_containerstat=?, trip_haulingsegment=?,
                                trip_haulingtype=?, trip_from=?, trip_to=?
             WHERE d_id=? AND trip_type=?"
        );
        $u->execute([$container, $cstatus, $segment, $hauType,
            $tripFrom, $tripTo, $id, $type]);
        $tripsUpdated += $u->rowCount() > 0 ? 1 : 0;
}
}

echo "Dispatch updated. Trips touched: $tripsUpdated.";
