<?php
header('Content-Type: application/json; charset=utf-8');

include "../config/config.php";

$segment = isset($_GET['segment']) ? trim((string)$_GET['segment']) : null;
$detail  = isset($_GET['detail']) ? trim((string)$_GET['detail']) : null;

if ($detail !== null && $detail !== '') {
    $sql = "
    SELECT d.*, t.*
    FROM dispatch d
    LEFT JOIN trips t ON d.d_id = t.d_id
    WHERE t.trip_id = ?
    LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([(int)$detail]);
    $row = $stmt->fetch();
    echo json_encode(['detail' => $row ?: null]);
    exit;
}

$todayCondition = "DATE(t.required_date) = CURRENT_DATE";
$nonCompletedContainersSubquery = "
  SELECT DISTINCT tt.trip_container
  FROM trips tt
  WHERE tt.trip_status IS NULL OR LOWER(tt.trip_status) NOT LIKE '%done%'
";

$whereParts = [];
$params = [];

$whereParts[] = "(" . $todayCondition . " OR t.trip_container IN (" . $nonCompletedContainersSubquery . "))";

if ($segment !== null && $segment !== '') {
    $whereParts[] = "(LOWER(d.costumer) = LOWER(?) OR LOWER(t.costumer) = LOWER(?))";
    $params[] = $segment;
    $params[] = $segment;
}

$where = implode(" AND ", $whereParts);

$sql = "
SELECT 
    d.d_id, d.booking_no, d.booking_sn, d.booking_do, d.cth_broker, d.cth_EIROut,
    d.d_datetime, d.d_dispatcher, d.d_dispatchHub, d.d_driverName, d.driver_id,
    d.d_truck, d.d_trailer, d.d_genset, d.d_tripReceipt, d.d_ecs, d.costumer AS dispatch_customer,

    t.trip_id, t.d_id AS t_d_id, t.trip_type, t.costumer AS trip_customer, t.trip_containerType,
    t.trip_container, t.container_activity, t.trip_containerStat,
    t.trip_haulingSegment, t.trip_haulingType, t.trip_from, t.trip_to,
    t.return_location, t.km_run,
    t.trip_departureDateTime, t.trip_arrivalDateTime, t.trip_pharrivalDateTime,
    t.deliver_location, t.deliver_dateTime, t.withdraw_location, t.withdraw_dateTime,
    t.required_date, t.trip_status,
    t.deliver_location, t.withdraw_location
FROM dispatch d
LEFT JOIN trips t ON d.d_id = t.d_id
WHERE {$where}
ORDER BY t.trip_departureDateTime DESC, t.trip_id DESC
LIMIT 1000
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$res = $stmt;

$empty = [];
$loaded = [];
$returned = [];

while ($row = $res->fetch()) {
    if (empty($row['trip_customer']) && !empty($row['dispatch_customer'])) {
        $row['trip_customer'] = $row['dispatch_customer'];
    }

    $activity = strtolower(trim((string)($row['container_activity'] ?? '')));
    if ($activity === 'empty' || $activity === 'empties' || strpos($activity, 'empty') !== false) {
        $empty[] = $row;
    } elseif ($activity === 'loaded' || $activity === 'load' || strpos($activity, 'load') !== false) {
        $loaded[] = $row;
    } elseif ($activity === 'return' || $activity === 'returned' || strpos($activity, 'return') !== false) {
        $returned[] = $row;
    } else {
        $stat = strtolower((string)($row['trip_containerstat'] ?? ''));
        if (strpos($stat, 'empty') !== false) {
            $empty[] = $row;
        } elseif (strpos($stat, 'full') !== false || strpos($stat, 'loaded') !== false) {
            $loaded[] = $row;
        } else {
            $loaded[] = $row;
        }
    }
}

echo json_encode([
    'empty' => $empty,
    'loaded' => $loaded,
    'returned' => $returned
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
