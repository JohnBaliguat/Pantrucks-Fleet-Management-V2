<?php
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$fromDate = $_POST['fromDate'] ?? '';
$toDate   = $_POST['toDate'] ?? '';

$totalDays = 1;
if ($fromDate !== '' && $toDate !== '') {
    $start = new DateTime($fromDate);
    $end   = new DateTime($toDate);
    $totalDays = max(1, $start->diff($end)->days + 1);
}

$customer = '';
if (!empty($_POST['customer']) && $_POST['customer'] !== 'Select Customer') {
    $customer = pt_pg_escape($conn, $_POST['customer']);
}

$trailer = '';
if (!empty($_POST['trailer'])) {
    $trailer = pt_pg_escape($conn, $_POST['trailer']);
}

$search = '';
if (!empty($_POST['search']['value'])) {
    $search = pt_pg_escape($conn, $_POST['search']['value']);
}

$dispatchFilters = [];
if ($customer !== '') {
    $dispatchFilters[] = "d.costumer = '$customer'";
}
if ($fromDate !== '' && $toDate !== '') {
    $dispatchFilters[] = "DATE(d.d_datetime) BETWEEN '$fromDate' AND '$toDate'";
}
$dispatchWhere = !empty($dispatchFilters) ? ' AND ' . implode(' AND ', $dispatchFilters) : '';

$latestDispatchSql = "
    SELECT
        d.d_id,
        d.d_drivername,
        d.d_datetime,
        d.d_dispatcher,
        d.d_dispatchhub,
        t1.trip_haulingsegment,
        t1.trip_haulingtype,
        t_last.trip_to AS last_location
    FROM dispatch d
    LEFT JOIN trips t1
        ON d.d_id = t1.d_id AND t1.trip_type = 'Trip 1'
    LEFT JOIN trips t_last
        ON d.d_id = t_last.d_id
       AND t_last.trip_id = (
            SELECT MAX(t3.trip_id)
            FROM trips t3
            WHERE t3.d_id = d.d_id
       )
    WHERE d.d_trailer = tr.trailer_name{$dispatchWhere}
    ORDER BY d.d_datetime DESC, d.d_id DESC
    LIMIT 1
";

$statsSql = "
    SELECT
        COUNT(DISTINCT DATE(d.d_datetime)) AS days_used,
        COUNT(*) AS total_dispatch
    FROM dispatch d
    WHERE d.d_trailer = tr.trailer_name{$dispatchWhere}
";

$baseQuery = "
SELECT
    tr.trailer_name,
    latest.d_drivername,
    latest.d_datetime,
    latest.d_dispatcher,
    latest.d_dispatchhub,
    latest.trip_haulingsegment,
    latest.trip_haulingtype,
    latest.last_location,
    COALESCE(stats.days_used, 0) AS days_used,
    COALESCE(stats.total_dispatch, 0) AS total_dispatch
FROM trailer tr
LEFT JOIN LATERAL (
    {$latestDispatchSql}
) AS latest ON TRUE
LEFT JOIN LATERAL (
    {$statsSql}
) AS stats ON TRUE
";

$whereClauses = ["1=1"];

if ($trailer !== '') {
    $whereClauses[] = "tr.trailer_name = '$trailer'";
}

if ($customer !== '' || ($fromDate !== '' && $toDate !== '')) {
    $whereClauses[] = "COALESCE(stats.total_dispatch, 0) > 0";
}

if ($search !== '') {
    $whereClauses[] = "(
        tr.trailer_name LIKE '%$search%' OR
        COALESCE(latest.d_drivername, '') LIKE '%$search%' OR
        COALESCE(latest.last_location, '') LIKE '%$search%' OR
        COALESCE(latest.trip_haulingsegment, '') LIKE '%$search%' OR
        COALESCE(latest.trip_haulingtype, '') LIKE '%$search%' OR
        COALESCE(latest.d_dispatcher, '') LIKE '%$search%' OR
        COALESCE(latest.d_dispatchhub, '') LIKE '%$search%'
    )";
}

$query = $baseQuery . ' WHERE ' . implode(' AND ', $whereClauses);

$columns = [
    0 => 'tr.trailer_name',
    1 => 'latest.d_drivername',
    2 => 'latest.d_datetime',
    3 => 'latest.last_location',
    4 => 'stats.days_used',
    5 => 'latest.trip_haulingsegment',
    6 => 'latest.d_dispatcher',
];

$totalRes = $conn->query("SELECT COUNT(*) AS total FROM trailer");
$totalData = ($totalRes) ? intval(($totalRes)->fetch()['total']) : 0;

$filteredCountQuery = "SELECT COUNT(*) AS total FROM ({$query}) AS filtered_trailers";
$filteredRes = $conn->query($filteredCountQuery);
$totalFiltered = ($filteredRes) ? intval(($filteredRes)->fetch()['total']) : 0;

if (isset($_POST['order'])) {
    $colIndex = intval($_POST['order'][0]['column']);
    $colDir = ($_POST['order'][0]['dir'] === 'desc') ? 'DESC' : 'ASC';
    if (isset($columns[$colIndex])) {
        $query .= " ORDER BY {$columns[$colIndex]} {$colDir}";
    } else {
        $query .= " ORDER BY tr.trailer_name ASC";
    }
} else {
    $query .= " ORDER BY tr.trailer_name ASC";
}

$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$query .= " LIMIT {$length} OFFSET {$start}";

$data = [];
$result = $conn->query($query);

if ($result && ($result)->rowCount() > 0) {
    while ($row = ($result)->fetch()) {
        $sub_array = [];

        $daysUsed = intval($row['days_used']);
        $dispatchCount = intval($row['total_dispatch']);
        $utilization = min(100, round(($daysUsed / $totalDays) * 100));

        if ($utilization >= 70) {
            $color = 'bg-success';
        } elseif ($utilization >= 40) {
            $color = 'bg-warning';
        } else {
            $color = 'bg-danger';
        }

        $sub_array[] = '
        <div class="d-flex align-items-center">
            <i class="bi bi-truck me-2"></i>
            <h6 class="mb-0 fw-bold">' . htmlspecialchars($row['trailer_name']) . '</h6>
        </div>';

        $sub_array[] = !empty($row['d_drivername'])
            ? htmlspecialchars($row['d_drivername'])
            : '<span class="text-muted">Idle</span>';

        $sub_array[] = !empty($row['d_datetime'])
            ? date('Y-m-d H:i A', strtotime($row['d_datetime']))
            : '<span class="text-muted">N/A</span>';

        $sub_array[] = !empty($row['last_location'])
            ? htmlspecialchars($row['last_location'])
            : '<span class="text-muted">N/A</span>';

        $sub_array[] = '
            <div class="progress" style="height:10px;" title="' . $dispatchCount . ' dispatches / ' . $daysUsed . ' days used">
                <div class="progress-bar ' . $color . '" style="width:' . $utilization . '%"></div>
            </div>
            <small class="text-muted">' . $utilization . '% utilized</small>
        ';

        $segment = trim((string)($row['trip_haulingsegment'] ?? ''));
        $type = trim((string)($row['trip_haulingtype'] ?? ''));
        $sub_array[] = $segment !== '' || $type !== ''
            ? '<div class="fw-semibold mb-1">' . htmlspecialchars(trim($segment . ' ' . $type)) . '</div>'
            : '<span class="text-muted">No Activity</span>';

        $sub_array[] = !empty($row['d_dispatcher'])
            ? htmlspecialchars($row['d_dispatcher'] . ' - ' . ($row['d_dispatchhub'] ?? ''))
            : '<span class="text-muted">N/A</span>';

        $data[] = $sub_array;
    }
}

echo json_encode([
    'draw' => intval($_POST['draw'] ?? 0),
    'recordsTotal' => $totalData,
    'recordsFiltered' => $totalFiltered,
    'data' => $data,
]);
?>
