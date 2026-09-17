<?php
include __DIR__ . '/../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

header('Content-Type: application/json');

function json_fail($message)
{
    echo json_encode([
        'draw' => (int)($_POST['draw'] ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => $message,
    ]);
    exit;
}

if (!$conn) {
    json_fail('Database connection failed.');
}

$orderableColumns = [
    1 => 'd.d_driverName',
    2 => 'd.d_datetime',
    5 => 'd.d_dispatcher',
];

$whereClauses = [
    "EXISTS (
        SELECT 1
        FROM trips tx
        WHERE tx.d_id = d.d_id
          AND (tx.trip_status IS NULL OR tx.trip_status != 'Done')
    )",
];

if (!empty($_POST['customer'])) {
    $customer = pt_pg_escape($conn, $_POST['customer']);
    $whereClauses[] = "(
        d.costumer = '{$customer}'
        OR EXISTS (
            SELECT 1
            FROM trips tc
            WHERE tc.d_id = d.d_id
              AND tc.costumer = '{$customer}'
              AND (tc.trip_status IS NULL OR tc.trip_status != 'Done')
        )
    )";
}

if (!empty($_POST['search']['value'])) {
    $search = pt_pg_escape($conn, $_POST['search']['value']);
    $whereClauses[] = "(
        d.booking_no LIKE '%{$search}%'
        OR d.d_driverName LIKE '%{$search}%'
        OR d.d_dispatchHub LIKE '%{$search}%'
        OR d.d_dispatcher LIKE '%{$search}%'
        OR d.d_truck LIKE '%{$search}%'
        OR d.d_trailer LIKE '%{$search}%'
        OR d.d_genset LIKE '%{$search}%'
        OR EXISTS (
            SELECT 1
            FROM trips ts
            WHERE ts.d_id = d.d_id
              AND (
                  ts.trip_container LIKE '%{$search}%'
                  OR ts.trip_haulingSegment LIKE '%{$search}%'
                  OR ts.trip_from LIKE '%{$search}%'
                  OR ts.trip_to LIKE '%{$search}%'
                  OR ts.costumer LIKE '%{$search}%'
              )
        )
    )";
}

$whereSql = 'WHERE ' . implode(' AND ', $whereClauses);

$totalSql = "
    SELECT COUNT(*) AS total
    FROM dispatch d
    WHERE EXISTS (
        SELECT 1
        FROM trips tx
        WHERE tx.d_id = d.d_id
          AND (tx.trip_status IS NULL OR tx.trip_status != 'Done')
    )
";
$totalRes = $conn->query($totalSql);
if (!$totalRes) {
    json_fail((($conn->errorInfo()[2]) ?? ""));
}
$recordsTotal = (int)(($totalRes)->fetch()['total'] ?? 0);

$filteredSql = "SELECT COUNT(*) AS total FROM dispatch d {$whereSql}";
$filteredRes = $conn->query($filteredSql);
if (!$filteredRes) {
    json_fail((($conn->errorInfo()[2]) ?? ""));
}
$recordsFiltered = (int)(($filteredRes)->fetch()['total'] ?? 0);

$orderBy = 'd.d_datetime DESC';
if (isset($_POST['order'][0]['column'])) {
    $columnIndex = (int)$_POST['order'][0]['column'];
    if (isset($orderableColumns[$columnIndex])) {
        $dir = strtolower($_POST['order'][0]['dir'] ?? 'desc');
        $dir = $dir === 'asc' ? 'ASC' : 'DESC';
        $orderBy = $orderableColumns[$columnIndex] . ' ' . $dir;
    }
}

$start = max(0, (int)($_POST['start'] ?? 0));
$length = (int)($_POST['length'] ?? 10);
if ($length < 1) {
    $length = 10;
}

$dispatchSql = "
    SELECT
        d.d_id,
        d.booking_no,
        d.costumer,
        d.d_datetime,
        d.d_dispatcher,
        d.d_dispatchHub,
        d.d_driverName,
        d.d_truck,
        d.d_trailer,
        d.d_genset,
        d.workflow_stage,
        d.billing_amount,
        d.billing_currency
    FROM dispatch d
    {$whereSql}
    ORDER BY {$orderBy}
    LIMIT {$start}, {$length}
";
$dispatchRes = $conn->query($dispatchSql);
if (!$dispatchRes) {
    json_fail((($conn->errorInfo()[2]) ?? ""));
}

$dispatches = [];
$dispatchIds = [];
while ($row = ($dispatchRes)->fetch()) {
    $dispatches[(int)$row['d_id']] = $row;
    $dispatchIds[] = (int)$row['d_id'];
}

$tripsByDispatch = [];
if ($dispatchIds) {
    $idList = implode(',', $dispatchIds);
    $tripSql = "
        SELECT
            trip_id,
            d_id,
            trip_type,
            trip_container,
            trip_containerStat,
            trip_haulingSegment,
            trip_haulingType,
            trip_from,
            trip_to,
            trip_status,
            required_date
        FROM trips
        WHERE d_id IN ({$idList})
          AND (trip_status IS NULL OR trip_status != 'Done')
        ORDER BY d_id ASC, trip_id ASC
    ";
    $tripRes = $conn->query($tripSql);
    if (!$tripRes) {
        json_fail((($conn->errorInfo()[2]) ?? ""));
    }
    while ($trip = ($tripRes)->fetch()) {
        $dId = (int)$trip['d_id'];
        if (!isset($tripsByDispatch[$dId])) {
            $tripsByDispatch[$dId] = [];
        }
        $tripsByDispatch[$dId][] = $trip;
    }
}

$data = [];
foreach ($dispatchIds as $dispatchId) {
    $dispatch = $dispatches[$dispatchId];
    $trips = $tripsByDispatch[$dispatchId] ?? [];
    if (!$trips) {
        continue;
    }

    $tripHtmlParts = [];
    $segmentNames = [];
    $haulingNames = [];
    $primaryTripId = (int)$trips[0]['trip_id'];

    foreach ($trips as $trip) {
        $tripHtmlParts[] = '
        <div class="d-flex align-items-center mb-2">
            <div class="ms-3">
                <h6 class="mb-0 fw-bolder">' . htmlspecialchars($trip['trip_type'] ?: ('Trip #' . $trip['trip_id'])) . ': '
                    . htmlspecialchars($trip['trip_from'] ?: '-') . ' - ' . htmlspecialchars($trip['trip_to'] ?: '-') . '</h6>
                <span class="fw-bolder">Container: ' . htmlspecialchars($trip['trip_container'] ?: '-')
                    . ', Status: ' . htmlspecialchars($trip['trip_status'] ?: 'Pending')
                    . ', Segment: ' . htmlspecialchars($trip['trip_haulingsegment'] ?: '-') . '</span>
            </div>
        </div>';

        if (!empty($trip['trip_haulingsegment']) && !in_array($trip['trip_haulingsegment'], $segmentNames, true)) {
            $segmentNames[] = $trip['trip_haulingsegment'];
        }
        if (!empty($trip['trip_haulingtype']) && !in_array($trip['trip_haulingtype'], $haulingNames, true)) {
            $haulingNames[] = $trip['trip_haulingtype'];
        }
    }

    $assignedHtml = '
    <div class="d-flex align-items-center">
        <img src="assets/images/profile/user-3.jpg" class="rounded-circle" width="40" alt="driver">
        <div class="ms-3">
            <h6 class="mb-0 fw-bolder">' . htmlspecialchars($dispatch['d_drivername']) . '</h6>
            <span class="fw-bolder">' . htmlspecialchars($dispatch['d_truck']) . '</span>
            <span class="fw-bolder">' . htmlspecialchars($dispatch['d_trailer']) . '</span>
            <span class="fw-bolder">' . htmlspecialchars($dispatch['d_genset']) . '</span>
        </div>
    </div>';

    $haulingHtml = '
    <div class="d-flex flex-column">
        <span class="fw-bold">Segment: ' . htmlspecialchars($segmentNames ? implode(' - ', $segmentNames) : '-') . '</span>
        <span class="fw-bold">Hauling: ' . htmlspecialchars($haulingNames ? implode(' - ', $haulingNames) : '-') . '</span>
    </div>';

    $data[] = [
        '<button type="button" class="btn btn-sm btn-outline-primary drill-toggle" data-d-id="' . $dispatchId . '" title="Show / hide trips"><i class="ti ti-plus"></i></button>',
        $assignedHtml,
        date('M d, Y h:i A', strtotime($dispatch['d_datetime'])),
        implode('', $tripHtmlParts),
        $haulingHtml,
        htmlspecialchars($dispatch['d_dispatcher'] . '/' . $dispatch['d_dispatchhub']),
        '
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-sm btn-secondary view-time" data-id="' . $primaryTripId . '"><i class="ti ti-edit"></i></button>
            <button class="btn btn-sm btn-success" onclick="markDone(' . $dispatchId . ')">Done</button>
            <button class="btn btn-sm btn-primary" onclick="editDispatch(' . $dispatchId . ')">Edit</button>
        </div>',
    ];
}

echo json_encode([
    'draw' => (int)($_POST['draw'] ?? 0),
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data,
]);
