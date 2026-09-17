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
$toDate = trim((string)($_POST['toDate'] ?? ''));
$violationType = trim((string)($_POST['violation'] ?? ''));
$draw = (int)($_POST['draw'] ?? 0);
$start = max(0, (int)($_POST['start'] ?? 0));
$length = (int)($_POST['length'] ?? 10);
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
    0 => 'v.vr_type',
    1 => 'v.vr_description',
    2 => 'v.vr_status',
    3 => 'v.vr_date',
    4 => 'v.vr_done_date',
    5 => 'v.vr_recordedBy',
];

$where = ["v.driver_id = ?"];
$bindTypes = "i";
$bindValues = [$driverId];

if ($fromDate !== '' && $toDate !== '') {
    $where[] = "DATE(v.vr_date) BETWEEN ? AND ?";
    $bindTypes .= "ss";
    $bindValues[] = $fromDate;
    $bindValues[] = $toDate;
}

if ($violationType !== '') {
    $where[] = "v.vr_type = ?";
    $bindTypes .= "s";
    $bindValues[] = $violationType;
}

$search = trim((string)($_POST['search']['value'] ?? ''));
if ($search !== '') {
    $where[] = "(v.vr_type ILIKE ? OR v.vr_description ILIKE ? OR v.vr_status ILIKE ? OR CAST(v.vr_recordedBy AS TEXT) ILIKE ?)";
    $like = '%' . $search . '%';
    $bindTypes .= "ssss";
    for ($i = 0; $i < 4; $i++) {
        $bindValues[] = $like;
    }
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$baseFrom = '
    FROM violation_record v
    LEFT JOIN "user" u ON u.user_id = v.vr_recordedby
    ' . $whereSql . '
';

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM violation_record v WHERE v.driver_id = ?");
$stmt->execute([$driverId]);
$recordsTotal = (int)($stmt->fetch()['total'] ?? 0);
$stmt = $conn->prepare("SELECT COUNT(*) AS total $baseFrom");
$stmt->execute($bindValues);
$recordsFiltered = (int)($stmt->fetch()['total'] ?? 0);
$orderBy = " ORDER BY v.vr_date DESC";
if (isset($_POST['order'][0]['column'], $_POST['order'][0]['dir'])) {
    $colIndex = (int)$_POST['order'][0]['column'];
    $dir = strtolower((string)$_POST['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';
    if (isset($columns[$colIndex])) {
        $orderBy = " ORDER BY {$columns[$colIndex]} $dir";
    }
}

$sql = "
    SELECT
        v.vr_type,
        v.vr_description,
        v.vr_status,
        TO_CHAR(v.vr_date, 'YYYY-MM-DD HH12:MI AM') AS vr_date_display,
        CASE
            WHEN v.vr_done_date IS NULL THEN '-'
            ELSE TO_CHAR(v.vr_done_date, 'YYYY-MM-DD HH12:MI AM')
        END AS vr_done_date_display,
        COALESCE(
            NULLIF(CONCAT_WS(', ', NULLIF(TRIM(u.user_lname), ''), NULLIF(TRIM(u.user_fname), '')), ''),
            NULLIF(TRIM(u.user_name), ''),
            CAST(v.vr_recordedBy AS TEXT)
        ) AS recorded_by
    $baseFrom
    $orderBy
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$pageBindTypes = $bindTypes . "ii";
$pageBindValues = $bindValues;
$pageBindValues[] = $length;
$pageBindValues[] = $start;
$stmt->execute($pageBindValues);
$res = $stmt;

$data = [];
while ($row = $res->fetch()) {
    $badgeClass = $row['vr_status'] === 'Done' ? 'success' : 'danger';
    $data[] = [
        htmlspecialchars($row['vr_type']),
        htmlspecialchars($row['vr_description']),
        '<span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($row['vr_status']) . '</span>',
        htmlspecialchars($row['vr_date_display']),
        htmlspecialchars($row['vr_done_date_display']),
        htmlspecialchars($row['recorded_by'] ?: '-'),
    ];
}
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data,
]);
