<?php
include __DIR__ . '/../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

if (!pt_table_exists($conn, 'fuel_inventory')) {
    echo json_encode([
        'draw' => intval($_POST['draw'] ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
    exit;
}

$columns = ['fi_id', 'fi_date', 'fi_noofliters', 'fi_pono', 'fi_invo', 'fi_plateno', 'fi_receiveby'];

$query = "SELECT * FROM fuel_inventory";
$filter_query = $query;

if (!empty($_POST['search']['value'])) {
    $s = pt_pg_escape($conn, $_POST['search']['value']);
    $filter_query .= " WHERE fi_poNo LIKE '%$s%' OR fi_inVo LIKE '%$s%' OR fi_receiveBy LIKE '%$s%' OR fi_plateNo LIKE '%$s%'";
}

$totalFiltered = ($conn->query($filter_query)->rowCount());

if (isset($_POST['order'])) {
    $idx = intval($_POST['order'][0]['column']);
    $dir = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
    if (isset($columns[$idx])) $filter_query .= " ORDER BY {$columns[$idx]} $dir";
} else {
    $filter_query .= " ORDER BY fi_date DESC";
}

$start  = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
if ($length < 0) $length = 10;
$filter_query .= " LIMIT $length OFFSET $start";

$result = $conn->query($filter_query);

$data = [];
while ($result && $row = ($result)->fetch()) {
    $data[] = [
        $row['fi_id'],
        date('M j, Y g:i A', strtotime($row['fi_date'])),
        $row['fi_noofliters'] . ' L (avail: ' . $row['fi_consumableltr'] . ')',
        $row['fi_pono'],
        $row['fi_invo'],
        $row['fi_plateno'],
        $row['fi_receiveby'],
        '<span class="text-muted">—</span>'
    ];
}

echo json_encode([
    'draw'            => intval($_POST['draw'] ?? 0),
    'recordsTotal'    => $totalFiltered,
    'recordsFiltered' => $totalFiltered,
    'data'            => $data
]);
