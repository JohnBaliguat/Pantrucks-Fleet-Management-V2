<?php
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$columns = [
  'd.driver_fname',
  'vr_type',
  'vr_description',
  'vr_status',
  'vr_date'
];

/* ===============================
   BASE QUERY
================================ */
$baseQuery = "
SELECT
  d.driver_id,
  CONCAT(d.driver_fname, ' ', d.driver_lname) AS driver_name,
  d.driver_assignunit,
  v.vr_type,
  v.vr_description,
  v.vr_status,
  v.vr_date,
  v.vr_done_date
FROM drivers d
INNER JOIN violation_record v ON d.driver_id = v.driver_id
";

/* ===============================
   WHERE CONDITIONS
================================ */
$where = [];

if (!empty($_POST['violation'])) {
  $violation = pt_pg_escape($conn, $_POST['violation']);
  $where[] = "v.vr_type = '$violation'";
}

if (!empty($_POST['fromDate']) && !empty($_POST['toDate'])) {
  $from = pt_pg_escape($conn, $_POST['fromDate']);
  $to   = pt_pg_escape($conn, $_POST['toDate']);
  $where[] = "v.vr_date BETWEEN '$from' AND '$to'";
}

if (!empty($_POST['search']['value'])) {
  $search = pt_pg_escape($conn, $_POST['search']['value']);
  $where[] = "(d.driver_fname LIKE '%$search%'
               OR d.driver_lname LIKE '%$search%'
               OR v.vr_type LIKE '%$search%')";
}

$query = $baseQuery;
if ($where) {
  $query .= " WHERE " . implode(" AND ", $where);
}

/* ===============================
   COUNT
================================ */
$totalQuery = "SELECT COUNT(*) total FROM violation_record";
$totalRes = $conn->query($totalQuery);
$totalData = ($totalRes)->fetch()['total'];

$filteredRes = $conn->query($query);
$totalFiltered = ($filteredRes)->rowCount();

/* ===============================
   ORDER
================================ */
if (isset($_POST['order'])) {
  // Whitelist the column by index and force the direction — never interpolate
  // raw request values into ORDER BY (PDO/pgsql allows stacked statements).
  $colIdx = (int)($_POST['order'][0]['column'] ?? 0);
  $col = $columns[$colIdx] ?? 'v.vr_date';
  $dir = strtolower((string)($_POST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
  $query .= " ORDER BY $col $dir";
} else {
  $query .= " ORDER BY v.vr_date DESC";
}

/* ===============================
   PAGINATION
================================ */
$start = intval($_POST['start']);
$length = intval($_POST['length']);
$query .= " LIMIT $length OFFSET $start";

/* ===============================
   DATA
================================ */
$data = [];
$res = $conn->query($query);

while ($row = ($res)->fetch()) {
  $routePrefix = trim((string)($_POST['routePrefix'] ?? 'hra-violationDriver'));
  $detailUrl = 'index.php?route=' . urlencode($routePrefix)
    . '&driver_id=' . urlencode((string)$row['driver_id'])
    . '&fromDate=' . urlencode((string)($_POST['fromDate'] ?? ''))
    . '&toDate=' . urlencode((string)($_POST['toDate'] ?? ''))
    . '&violation=' . urlencode((string)($_POST['violation'] ?? ''));

  $data[] = [
    '',
    '<a href="' . htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') . '" class="fw-semibold text-primary text-decoration-none">' . htmlspecialchars($row['driver_name']) . '</a>',
    htmlspecialchars($row['vr_type']),
    htmlspecialchars($row['vr_description']),
    '<span class="badge bg-' . ($row['vr_status'] == 'Done' ? 'success' : 'warning') . '">' .
      htmlspecialchars($row['vr_status']) .
    '</span>',
    date('Y-m-d', strtotime($row['vr_date']))
  ];
}

/* ===============================
   RESPONSE
================================ */
echo json_encode([
  "draw" => intval($_POST['draw']),
  "recordsTotal" => $totalData,
  "recordsFiltered" => $totalFiltered,
  "data" => $data
]);
