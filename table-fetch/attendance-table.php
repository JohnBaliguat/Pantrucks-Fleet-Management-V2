<?php 
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();
header('Content-Type: application/json');
error_reporting(0);

$columns = ['', 'driver_name', 'da_date', 'da_timein', 'da_timeout', 'da_status', 'da_remarks'];

// -------------------------------
// Base Query
// -------------------------------
$baseQuery = "
  SELECT 
    d.driver_id,
    CONCAT(d.driver_fname, ' ', d.driver_mname, ' ', d.driver_lname) AS driver_name,
    d.driver_image,
    da.da_id,
    da.da_status,
    da.da_remarks,
    da.da_timein,
    da.da_timeout,
    da.vl_sl_dateprepared,
    da.vl_sl_date,
    da.da_date
  FROM drivers d
  INNER JOIN drivers_attendance da ON d.driver_id = da.driver_id
";

// -------------------------------
// WHERE Clause (Optional Filters)
// -------------------------------
$where = [];

if (!empty($_POST['fromDate']) && !empty($_POST['toDate'])) {
  $from = pt_pg_escape($conn, $_POST['fromDate']);
  $to = pt_pg_escape($conn, $_POST['toDate']);
  $where[] = "da.da_date BETWEEN '$from' AND '$to'";
}

if (!empty($_POST["search"]["value"])) {
  $search = pt_pg_escape($conn, $_POST["search"]["value"]);
  $where[] = "(d.driver_fname LIKE '%$search%' 
            OR d.driver_lname LIKE '%$search%' 
            OR da.da_status LIKE '%$search%'
            OR da.da_remarks LIKE '%$search%')";
}

if (!empty($where)) {
  $baseQuery .= " WHERE " . implode(" AND ", $where);
}

// -------------------------------
// Order & Pagination
// -------------------------------
$orderColumnIndex = $_POST['order'][0]['column'] ?? 2;
$orderColumn = $columns[$orderColumnIndex] ?: 'da_date';
$orderDir = $_POST['order'][0]['dir'] ?? 'DESC';
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);

$query = "$baseQuery ORDER BY $orderColumn $orderDir LIMIT $length OFFSET $start";

// -------------------------------
// Fetch data
// -------------------------------
$result = $conn->query($query);
$data = [];

while ($row = ($result)->fetch()) {
  $sub = [];
  $sub[] = '';
  $sub[] = '
    <div class="d-flex align-items-center">
      <img src="../assets/images/profile/user-3.jpg" 
           class="rounded-circle" width="40" height="40" alt="Driver Image">
      <div class="ms-3">
        <h6 class="mb-0 fw-bolder">' . htmlspecialchars($row['driver_name']) . '</h6>
      </div>
    </div>';
  $sub[] = htmlspecialchars($row['da_date'] ?? '');
  $sub[] = htmlspecialchars(date('H:i:s', strtotime($row['da_timein'])) ?? '');
  $sub[] = htmlspecialchars($row['da_timeout'] ?? '');
  $sub[] = htmlspecialchars($row['vl_sl_dateprepared'] ?? '');
  $sub[] = htmlspecialchars($row['vl_sl_date'] ?? '');
  $sub[] = htmlspecialchars($row['da_status'] ?? '');
  $sub[] = htmlspecialchars($row['da_remarks'] ?? '');
  $data[] = $sub;
}

// -------------------------------
// Get counts
// -------------------------------
$totalQuery = "SELECT COUNT(*) AS total FROM drivers";
$totalRes = $conn->query($totalQuery);
$totalData = ($totalRes)->fetch()['total'] ?? 0;

$countQuery = "
  SELECT COUNT(*) AS total 
  FROM drivers d
  LEFT JOIN drivers_attendance da ON d.driver_id = da.driver_id
";
if (!empty($where)) {
  $countQuery .= " WHERE " . implode(" AND ", $where);
}
$filteredRes = $conn->query($countQuery);
$totalFiltered = ($filteredRes)->fetch()['total'] ?? 0;

// -------------------------------
// Output JSON
// -------------------------------
echo json_encode([
  "draw" => intval($_POST["draw"] ?? 0),
  "recordsTotal" => intval($totalData),
  "recordsFiltered" => intval($totalFiltered),
  "data" => $data
]);
