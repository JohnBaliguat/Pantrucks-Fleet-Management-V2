<?php
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/error_log.txt");

$columns = [
    'd_drivername',
    'd_dispatchhub',
    'trip_from',
    'trip_haulingsegment',
    'd_datetime',
    'd_dispatcher'
];

// -------------------------------
// Base Query (ONE ROW PER TRIP)
// -------------------------------
$baseQuery = "
SELECT 
    d.*, 
    t.trip_id,
    t.trip_type,
    t.trip_container,
    t.trip_containerstat,
    t.trip_haulingsegment,
    t.trip_haulingtype,
    t.trip_from,
    t.trip_to,
    t.trip_status
FROM dispatch d
INNER JOIN trips t ON d.d_id = t.d_id
";

// -------------------------------
// WHERE conditions
// -------------------------------
$whereClauses = ["t.trip_status != 'Done'"];

// Customer filter
if (!empty($_POST['customer'])) {
    $customer = pt_pg_escape($conn, $_POST['customer']);
    $whereClauses[] = "t.costumer = '$customer'";
}

// Search filter
if (!empty($_POST["search"]["value"])) {
    $search = pt_pg_escape($conn, $_POST["search"]["value"]);
    $whereClauses[] = "(d.d_driverName LIKE '%$search%' 
                       OR d.d_dispatchHub LIKE '%$search%' 
                       OR d.d_truck LIKE '%$search%' 
                       OR t.trip_container LIKE '%$search%'
                       OR t.trip_haulingSegment LIKE '%$search%')";
}

$query = $baseQuery;

if (!empty($whereClauses)) {
    $query .= " WHERE " . implode(" AND ", $whereClauses);
}

// -------------------------------
// Total records
// -------------------------------
$totalQuery = "SELECT COUNT(*) AS total FROM trips";
$totalRes = $conn->query($totalQuery);
$totalData = ($totalRes) ? intval(($totalRes)->fetch()['total']) : 0;

// -------------------------------
// Filtered count
// -------------------------------
$filteredRes = $conn->query($query);
$totalFiltered = ($filteredRes) ? ($filteredRes)->rowCount() : 0;

// -------------------------------
// Ordering
// -------------------------------
if (isset($_POST["order"])) {
    $colIndex = intval($_POST['order'][0]['column']);
    $colDir = ($_POST['order'][0]['dir'] === 'desc') ? 'DESC' : 'ASC';
    $query .= " ORDER BY {$columns[$colIndex]} $colDir";
} else {
    $query .= " ORDER BY d.d_id DESC";
}

// -------------------------------
// Pagination
// -------------------------------
$start = intval($_POST['start']);
$length = intval($_POST['length']);
$query .= " LIMIT $length OFFSET $start";

// -------------------------------
// Fetch Data
// -------------------------------
$data = [];
$result = $conn->query($query);

if ($result && ($result)->rowCount() > 0) {
    while ($row = ($result)->fetch()) {

        $sub_array = [];
        $sub_array[] = '';

        // Driver Info
        $sub_array[] = '
        <div class="d-flex align-items-center">
          <img src="../assets/images/profile/user-3.jpg" class="rounded-circle" width="40">
          <div class="ms-3">
            <h6 class="mb-0 fw-bolder">' . htmlspecialchars($row['d_drivername']) . '</h6>
            <span class="fw-bolder">' . htmlspecialchars($row['d_truck']) . '</span>
            <span class="fw-bolder">' . htmlspecialchars($row['d_trailer']) . '</span>
            <span class="fw-bolder">' . htmlspecialchars($row['d_genset']) . '</span>
          </div>
        </div>';

        // Date
        $sub_array[] = date('M d, Y h:i A', strtotime($row['d_datetime']));

        // Trip Info (Single Trip Only)
        $sub_array[] = '
        <div class="d-flex flex-column">
            <span class="fw-bold">' . htmlspecialchars($row['trip_type']) . '</span>
            <span>Route: ' . htmlspecialchars($row['trip_from']) . ' - ' . htmlspecialchars($row['trip_to']) . '</span>
            <span>Container: ' . htmlspecialchars($row['trip_container']) . '</span>
            <span>Status: ' . htmlspecialchars($row['trip_status']) . '</span>
        </div>';

        // Segment & Hauling
        $sub_array[] = '
        <div class="d-flex flex-column">
            <span class="fw-bold">Segment: ' . htmlspecialchars($row['trip_haulingsegment']) . '</span>
            <span class="fw-bold">Hauling: ' . htmlspecialchars($row['trip_haulingtype']) . '</span>
        </div>';

        // Dispatcher
        $sub_array[] = htmlspecialchars($row['d_dispatcher'].'/'.$row['d_dispatchhub']);

        // Actions (IMPORTANT: use trip_id now)
        $sub_array[] = '
        <div class="d-flex gap-2 justify-content-end">
          <button class="btn btn-sm btn-success" onclick="markDone(' . $row['trip_id'] . ')">Done</button>
          <button class="btn btn-sm btn-primary" onclick="editTrip(' . $row['trip_id'] . ')" hidden>Edit</button>
          <button class="btn btn-sm btn-danger" onclick="deleteTrip(' . $row['trip_id'] . ')">Delete</button>
        </div>';

        $data[] = $sub_array;
    }
}

// -------------------------------
// JSON Response
// -------------------------------
$output = [
    "draw" => intval($_POST["draw"]),
    "recordsTotal" => $totalData,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
];

echo json_encode($output);
