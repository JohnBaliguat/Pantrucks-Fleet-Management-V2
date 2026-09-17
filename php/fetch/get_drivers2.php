<?php
include "../config/config.php";

$today = date('Y-m-d');

$sql = "
    SELECT DISTINCT
        d.driver_id,
        d.driver_fname,
        d.driver_mname,
        d.driver_lname,
        d.driver_status,
        d.shift_truck,
        s.ds_id AS open_shift_id,
        u.maintenance_blocked
    FROM drivers d
    INNER JOIN drivers_attendance da
        ON da.driver_id = d.driver_id
    LEFT JOIN driver_shift s
        ON s.driver_id = d.driver_id
       AND s.ended_at IS NULL
    LEFT JOIN units u
        ON u.unit_name = d.shift_truck
       AND u.unit_type = 'truck'
    WHERE
        da.da_date = ?
        AND da.da_status = 'Present'
        AND da.da_timein IS NOT NULL
        AND da.da_timeout IS NULL
        AND NOT EXISTS (
            SELECT 1
            FROM violation_record v
            WHERE v.driver_id = d.driver_id
              AND v.vr_status = 'Active'
        )
        AND NOT EXISTS (
            SELECT 1
            FROM dispatch dp
            WHERE dp.driver_id = d.driver_id
              AND dp.workflow_stage IN (
                'dispatcher_assigned',
                'reassigned',
                'driver_accepted',
                'gate_cleared',
                'en_route',
                'pending_verification'
              )
        )
    ORDER BY d.driver_lname ASC, d.driver_fname ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute([$today]);
$result = $stmt;

$drivers = [];
while ($row = $result->fetch()) {
    $formattedName = strtoupper($row['driver_lname']) . ", " . strtoupper($row['driver_fname']) . ".";
    $shiftTruck = trim((string)($row['shift_truck'] ?? ''));
    $hasOpenShift = (int)($row['open_shift_id'] ?? 0) > 0;
    $blocked = (int)($row['maintenance_blocked'] ?? 0) === 1;

    $drivers[] = [
        'id' => $row['driver_id'],
        'name' => $formattedName,
        'shift_truck' => ($hasOpenShift && !$blocked && $shiftTruck !== '') ? $shiftTruck : '',
        'has_open_shift' => $hasOpenShift,
    ];
}
header('Content-Type: application/json');
echo json_encode($drivers);
