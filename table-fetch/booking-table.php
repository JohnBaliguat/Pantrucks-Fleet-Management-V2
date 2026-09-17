<?php
header('Content-Type: application/json');
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

// Index 0 reserved for the row-select checkbox (non-orderable on the client).
$columns = [
    '', 'booking_no', 'booking_date', 'booking_daterequired', 'age', 'costumer', 'container_seal',
    'container', 'container_status', 'hauling_segment', 'hauling_type',
    'trip_from', 'trip_to', 'quantity', 'quantity_use', 'status'
];

// Tab filter: 'active' (default) hides Complete rows, 'complete' shows only Complete.
$view = isset($_POST['view']) ? strtolower(trim($_POST['view'])) : 'active';
if ($view === 'complete') {
    $view_clause = " AND status = 'Complete'";
} else {
    $view_clause = " AND status <> 'Complete'";
}

$query = "SELECT booking_id, booking_no, booking_date, booking_daterequired, costumer, container_seal, container, booking_activity, container_status,
                 hauling_segment, hauling_type, trip_from, trip_to, quantity, quantity_use, status
          FROM booking WHERE status != 'Disable'" . $view_clause;
$filter_query = $query;

// Search
if (!empty($_POST['search']['value'])) {
    $search_value = pt_pg_escape($conn, $_POST['search']['value']);
    $filter_query .= " AND (booking_no LIKE '%$search_value%'
        OR costumer LIKE '%$search_value%'
        OR container_seal LIKE '%$search_value%'
        OR container LIKE '%$search_value%'
        OR container_status LIKE '%$search_value%'
        OR hauling_segment LIKE '%$search_value%'
        OR hauling_type LIKE '%$search_value%'
        OR trip_from LIKE '%$search_value%'
        OR trip_to LIKE '%$search_value%'
        OR status LIKE '%$search_value%')";
}

// Total filtered
$filtered_result = $conn->query($filter_query);
$totalFiltered = ($filtered_result)->rowCount();

// Ordering
if (isset($_POST["order"])) {
    $order_column_index = intval($_POST['order'][0]['column']);
    $order_dir = $_POST['order'][0]['dir'] === 'desc' ? 'ASC' : 'DESC';
    if (isset($columns[$order_column_index]) && $columns[$order_column_index] !== '') {
        $order_column_name = $columns[$order_column_index];
        $filter_query .= " ORDER BY $order_column_name $order_dir";
    }
} else {
    $filter_query .= " ORDER BY booking_id DESC";
}

// Pagination
$start = intval($_POST['start']);
$length = intval($_POST['length']);
$filter_query .= " LIMIT $length OFFSET $start";

// Fetch data
$result = $conn->query($filter_query);
$data = [];

while ($row = ($result)->fetch()) {
    // ✅ Calculate Age in days
    $requiredDate = new DateTime($row['booking_daterequired']);
    $today = new DateTime();
    $age = $today->diff($requiredDate)->days . ' Days';


    // ✅ Combined columns
    $containerCol = $row['container'] . " - " . '<span class="badge bg-info">'.$row['container_status'].'</span>';
    $segmentCol   = $row['hauling_segment'] . " - " . '<span class="badge bg-info">'.$row['hauling_type'].'</span>';
    $tripCol      = $row['trip_from'] . " ➝ " . $row['trip_to'];

    if ($row['container'] == '') {
         $containerCol = $row['container'] . "" . '<span class="badge bg-info">'.$row['container_status'].'</span>';
    }

    // ✅ Status badge color
    $statusBadge = '';
    switch (strtolower($row['status'])) {
        case 'approved':
            $statusBadge = '<span class="badge bg-success">'.$row['status'].'</span>';
            break;
        case 'pending':
            $statusBadge = '<span class="badge bg-warning text-dark">'.$row['status'].'</span>';
            break;
        case 'cancelled':
            $statusBadge = '<span class="badge bg-danger">'.$row['status'].'</span>';
            break;
        default:
            $statusBadge = '<span class="badge bg-secondary">'.$row['status'].'</span>';
    }

    // ✅ Action buttons based on quantity / quantity_use
    $actionBtns = '<div class="d-flex align-items-center list-user-action">';

    if ($row['quantity_use'] < $row['quantity']) {
        $actionBtns .= '<button type="button" class="btn btn-primary btn-sm" title="Edit" '
            . 'data-edit-booking="' . (int)$row['booking_id'] . '"><i class="ti ti-edit"></i></button>';
    }

    if ($row['quantity_use'] == 0) {
        $actionBtns .= '<button type="button" class="btn btn-danger btn-sm" title="Delete" '
            . 'data-delete-booking="' . (int)$row['booking_id'] . '" style="margin-left: 5px;">'
            . '<i class="ti ti-trash"></i></button>';
    }

    $actionBtns .= '</div>';

    // Row-select checkbox — only deletable rows (quantity_use = 0) get one,
    // matching the existing per-row delete button rule.
    $selectCell = '';
    if ((int)$row['quantity_use'] === 0) {
        $selectCell = '<input type="checkbox" class="booking-row-check form-check-input" value="'
            . (int)$row['booking_id'] . '">';
    }

    $data[] = [
        $selectCell,
        $row['booking_no'],
        $row['booking_date'],
        $row['booking_daterequired'],
        $age, // ✅ Age column
        $row['costumer'],
        $containerCol,
        $segmentCol,
        $tripCol,
        $row['quantity'],
        $row['quantity_use'],
        $statusBadge,
        $actionBtns
    ];
}

// Output to DataTables
echo json_encode([
    "draw" => intval($_POST["draw"]),
    "recordsTotal" => $totalFiltered,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);
?>
