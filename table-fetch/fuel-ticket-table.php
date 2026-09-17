<?php
include __DIR__ . '/../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

if (!pt_table_exists($conn, 'fuel_report')) {
    echo json_encode([
        'draw' => intval($_POST['draw'] ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
    exit;
}

$columns = ['f_controlno', 'f_date', 'f_unit', 'f_lasthubo', 'f_hubo',
            'f_kmrun', 'f_nooflit', 'f_actratio', 'f_driver',
            'f_stdratio', 'f_excess_saving', 'f_hourmeter'];

$query = "SELECT * FROM fuel_report";
$filter_query = $query;

if (!empty($_POST['search']['value'])) {
    $s = pt_pg_escape($conn, $_POST['search']['value']);
    $filter_query .= " WHERE f_controlNo LIKE '%$s%' OR f_unit LIKE '%$s%' OR f_driver LIKE '%$s%' OR f_date LIKE '%$s%'";
}

$filtered_result = $conn->query($filter_query);
$totalFiltered = $filtered_result ? ($filtered_result)->rowCount() : 0;

if (isset($_POST['order'])) {
    $idx = intval($_POST['order'][0]['column']);
    $dir = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
    if (isset($columns[$idx])) $filter_query .= " ORDER BY {$columns[$idx]} $dir";
} else {
    $filter_query .= " ORDER BY f_controlno DESC";
}

$start  = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
if ($length < 0) $length = 10;
$filter_query .= " LIMIT $length OFFSET $start";

$result = $conn->query($filter_query);

$data = [];
while ($result && $row = ($result)->fetch()) {
    $excess = $row['f_excess_saving'];
    $excess_fmt = ($excess < 0)
        ? '<span style="color:red;">(' . abs($excess) . ')</span>'
        : $excess;

    $action = '<button type="button" class="btn btn-sm btn-outline-primary" onclick="openViewModal('
        . "'" . $row['f_id']           . "',"
        . "'" . $row['f_date']         . "',"
        . "'" . $row['f_unit']         . "',"
        . "'" . $row['f_lasthubo']     . "',"
        . "'" . $row['f_hubo']         . "',"
        . "'" . $row['f_kmrun']        . "',"
        . "'" . $row['f_nooflit']      . "',"
        . "'" . $row['f_actratio']     . "',"
        . "'" . addslashes($row['f_driver']) . "',"
        . "'" . $row['f_stdratio']     . "',"
        . "'" . $row['f_excess_saving']. "',"
        . "'" . $row['f_hourmeter']    . "',"
        . "'" . $row['f_controlno']    . "',"
        . "'" . $row['f_idenolt']      . "'"
        . ');">View</button>';

    $data[] = [
        $row['f_controlno'],
        date('M j, Y', strtotime($row['f_date'])),
        $row['f_unit'],
        $row['f_lasthubo'],
        $row['f_hubo'],
        $row['f_kmrun'],
        $row['f_nooflit'],
        $row['f_actratio'],
        $row['f_driver'],
        $row['f_stdratio'],
        $excess_fmt,
        $row['f_hourmeter'],
        $action
    ];
}

echo json_encode([
    'draw'            => intval($_POST['draw'] ?? 0),
    'recordsTotal'    => $totalFiltered,
    'recordsFiltered' => $totalFiltered,
    'data'            => $data
]);
