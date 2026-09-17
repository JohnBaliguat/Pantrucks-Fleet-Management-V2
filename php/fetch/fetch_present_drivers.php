<?php
include "../config/config.php";

/* MAIN QUERY: present drivers.
   Postgres requires every non-aggregated SELECT column to be grouped or
   functionally dependent on a grouped primary key. We group by drivers.driver_id
   (its PK, which covers the name columns) and aggregate the dispatch/attendance
   columns. MAX(dp.d_id) picks the driver's latest dispatch for today. */
$sql = "
SELECT
    d.driver_id,
    MAX(dp.d_id) AS d_id,
    CONCAT(d.driver_fname, ' ', d.driver_lname) AS driver_name,
    MIN(da.da_timein) AS da_timein,
    MIN(dp.d_datetime) AS first_dispatch,
    CASE
        WHEN COUNT(dp.d_id) > 0 THEN 1
        ELSE 0
    END AS has_dispatch
FROM drivers d
INNER JOIN drivers_attendance da
    ON da.driver_id = d.driver_id
    AND da.da_date = CURRENT_DATE
    AND da.da_status = 'Present'
LEFT JOIN dispatch dp
    ON dp.driver_id = d.driver_id
    AND DATE(dp.d_datetime) = CURRENT_DATE
GROUP BY d.driver_id, d.driver_fname, d.driver_lname
ORDER BY da_timein ASC
";

$result = $conn->query($sql);
$data = [];

// Prepared once — count active trips for a driver's dispatch.
$countStmt = $conn->prepare(
    "SELECT COUNT(*) FROM trips WHERE d_id = ? AND trip_status = 'Active'"
);

while ($row = ($result)->fetch()) {
    /* SECOND QUERY: count ACTIVE trips for this driver's dispatch (if any). */
    $d_id = $row['d_id'];
    if ($d_id !== null && $d_id !== '') {
        $countStmt->execute([$d_id]);
        $row['active_booking_count'] = (int)$countStmt->fetchColumn();
    } else {
        $row['active_booking_count'] = 0;
    }
    $data[] = $row;
}

echo json_encode($data);
