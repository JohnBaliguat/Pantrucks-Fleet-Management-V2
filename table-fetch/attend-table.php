<?php
header('Content-Type: application/json');
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

// Columns used for client-side mapping (but we're ignoring sort override)
$columns = [
    'drivers_name', 'segment_trip', 'date', 'time', 'status'
];

// Base query
$query = "SELECT id, drivers_name, truck_name, trailer_name, genset_name, segment_trip, date, time, status FROM outin";

// Add search filter if needed
if (!empty($_POST['search']['value'])) {
    $search_value = pt_pg_escape($conn, $_POST['search']['value']);
    $query .= " WHERE 
        drivers_name LIKE '%$search_value%' OR 
        truck_name LIKE '%$search_value%' OR 
        trailer_name LIKE '%$search_value%' OR 
        segment_trip LIKE '%$search_value%' OR 
        status LIKE '%$search_value%'";
}

// Get total filtered (before pagination)
$filtered_result = $conn->query($query);
$totalFiltered = ($filtered_result)->rowCount();

// 🚨 Force ORDER BY id DESC regardless of DataTables sort request
$query .= " ORDER BY id DESC";

// Pagination
$start = intval($_POST['start']);
$length = intval($_POST['length']);
$query .= " LIMIT $length OFFSET $start";

// Execute final paginated query
$result = $conn->query($query);

// Format data for output
$data = [];
while ($row = ($result)->fetch()) {
    $profile_img = "../assets/images/profile/user-3.jpg"; // Replace with actual dynamic logic if needed
    $driver_name = $row['drivers_name'];
    $truck_name = $row['truck_name'];
    $segment = $row['segment_trip'];
    $date = date("F j, Y", strtotime($row['date']));
    $time = date("h:i A", strtotime($row['time']));

    $status_badge = '<span class="badge bg-secondary">Unknown</span>';
    if ($row['status'] === 'Check In') {
        $status_badge = '<span class="badge bg-info">Enter the Base</span>';
    } elseif ($row['status'] === 'Check Out') {
        $status_badge = '<span class="badge text-bg-primary">Out the Base</span>';
    }

    $data[] = [
        '<div class="d-flex align-items-center">
            <img src="' . $profile_img . '" class="rounded-circle" width="40" alt="profile" />
            <div class="ms-3">
                <h6 class="mb-0 fw-bolder">' . $driver_name . '</h6>
                <span class="text-muted">' . $truck_name . '</span>
            </div>
        </div>',
        $segment,
        $date,
        $time,
        $status_badge
    ];
}

// Output response for DataTables
echo json_encode([
    "draw" => intval($_POST["draw"]),
    "recordsTotal" => $totalFiltered,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);
