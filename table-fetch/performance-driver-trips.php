<?php
session_start();
header('Content-Type: application/json');
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['HR-Admin', 'Visual'], true)) {
    http_response_code(403);
    echo json_encode([
        'draw' => (int)($_POST['draw'] ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Not authorised'
    ]);
    exit;
}

$driverId = (int)($_POST['driver_id'] ?? 0);
$fromDate = trim((string)($_POST['fromDate'] ?? ''));
$toDate   = trim((string)($_POST['toDate'] ?? ''));
$draw     = (int)($_POST['draw'] ?? 0);
$start    = max(0, (int)($_POST['start'] ?? 0));
$length   = (int)($_POST['length'] ?? 10);
if ($length <= 0) {
    $length = 10;
}

if ($driverId <= 0) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
    ]);
    exit;
}

$columns = [
    0 => 't.trip_id',
    1 => 'd.d_truck',
    2 => 't.costumer',
    3 => 't.trip_haulingSegment',
    4 => 't.trip_status',
    5 => 'd.d_datetime',
];

$where = ["d.driver_id = ?"];
$bindTypes = "i";
$bindValues = [$driverId];

if ($fromDate !== '' && $toDate !== '') {
    $where[] = "DATE(d.d_datetime) BETWEEN ? AND ?";
    $bindTypes .= "ss";
    $bindValues[] = $fromDate;
    $bindValues[] = $toDate;
}

$search = trim((string)($_POST['search']['value'] ?? ''));
if ($search !== '') {
    $where[] = "(CAST(t.trip_id AS CHAR) LIKE ? OR d.d_truck LIKE ? OR t.costumer LIKE ? OR t.trip_haulingSegment LIKE ? OR t.trip_status LIKE ?)";
    $like = '%' . $search . '%';
    $bindTypes .= "sssss";
    for ($i = 0; $i < 5; $i++) {
        $bindValues[] = $like;
    }
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$baseFrom = "
    FROM dispatch d
    INNER JOIN trips t ON t.d_id = d.d_id
    $whereSql
";

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM dispatch d INNER JOIN trips t ON t.d_id = d.d_id WHERE d.driver_id = ?");
$stmt->execute([$driverId]);
$recordsTotal = (int)($stmt->fetch()['total'] ?? 0);
$stmt = $conn->prepare("SELECT COUNT(*) AS total $baseFrom");
$stmt->execute($bindValues);
$recordsFiltered = (int)($stmt->fetch()['total'] ?? 0);
$orderBy = " ORDER BY d.d_datetime DESC, t.trip_id DESC";
if (isset($_POST['order'][0]['column'], $_POST['order'][0]['dir'])) {
    $colIndex = (int)$_POST['order'][0]['column'];
    $dir = strtolower((string)$_POST['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';
    if (isset($columns[$colIndex])) {
        $orderBy = " ORDER BY {$columns[$colIndex]} $dir";
    }
}

$sql = "
    SELECT
        t.trip_id,
        COALESCE(d.d_truck, '-') AS truck,
        COALESCE(t.costumer, '-') AS customer,
        COALESCE(NULLIF(TRIM(t.trip_haulingSegment), ''), '-') AS segment,
        COALESCE(t.trip_status, '-') AS status,
        TO_CHAR(d.d_datetime, 'YYYY-MM-DD HH12:MI AM') AS dispatched_at
    $baseFrom
    $orderBy
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$pageBindTypes = $bindTypes . "ii";
$pageBindValues = $bindValues;
// Postgres: LIMIT <length> OFFSET <start> (MySQL's "LIMIT start, length" isn't supported).
$pageBindValues[] = $length;
$pageBindValues[] = $start;
$stmt->execute($pageBindValues);
$res = $stmt;

$data = [];
while ($row = $res->fetch()) {
    $data[] = [
        $row['trip_id'],
        htmlspecialchars($row['truck']),
        htmlspecialchars($row['customer']),
        htmlspecialchars($row['segment']),
        htmlspecialchars($row['status']),
        htmlspecialchars($row['dispatched_at']),
    ];
}
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data,
]);
