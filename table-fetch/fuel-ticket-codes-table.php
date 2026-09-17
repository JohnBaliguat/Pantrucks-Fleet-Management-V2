<?php
// Server-side processing endpoint for the Gastender "Ticket Codes" table.
// Returns DataTables-formatted JSON: tc_code, f_driverId, unit_name, tc_date, tc_status, action.
include __DIR__ . '/../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

if (!pt_table_exists($conn, 'ticket_code')) {
    echo json_encode([
        'draw'            => intval($_POST['draw'] ?? 0),
        'recordsTotal'    => 0,
        'recordsFiltered' => 0,
        'data'            => []
    ]);
    exit;
}

$columns = ['tc_code', 'f_driverid', 'unit_name', 'tc_date', 'tc_status'];

// Total (unfiltered)
$totalQuery   = $conn->query("SELECT COUNT(*) AS c FROM ticket_code");
$recordsTotal = ($r = ($totalQuery)->fetch()) ? (int)$r['c'] : 0;

// Filter
$where = '';
if (!empty($_POST['search']['value'])) {
    $s = pt_pg_escape($conn, $_POST['search']['value']);
    $where = " WHERE tc_code LIKE '%$s%'
               OR f_driverId LIKE '%$s%'
               OR unit_name LIKE '%$s%'
               OR tc_status LIKE '%$s%'
               OR tc_date LIKE '%$s%'";
}

$filteredQuery   = $conn->query("SELECT COUNT(*) AS c FROM ticket_code $where");
$recordsFiltered = ($r = ($filteredQuery)->fetch()) ? (int)$r['c'] : 0;

// Order
$orderClause = " ORDER BY tc_date DESC";
if (isset($_POST['order'][0])) {
    $idx = intval($_POST['order'][0]['column']);
    $dir = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
    if (isset($columns[$idx])) {
        $orderClause = " ORDER BY {$columns[$idx]} $dir";
    }
}

// Paging
$start  = max(0, intval($_POST['start']  ?? 0));
$length = intval($_POST['length'] ?? 10);
if ($length < 0) $length = 10;

$sql = "SELECT tc_id, tc_code, f_driverId, unit_name, tc_date, tc_status
        FROM ticket_code
        $where
        $orderClause
        LIMIT $length OFFSET $start";
$result = $conn->query($sql);

$data = [];
while ($result && $row = ($result)->fetch()) {
    $code   = htmlspecialchars($row['tc_code']);
    $drv    = htmlspecialchars($row['f_driverid']);
    $unit   = htmlspecialchars($row['unit_name']);
    $date   = htmlspecialchars(date('M j, Y g:i A', strtotime($row['tc_date'])));
    $status = strtolower($row['tc_status']) === 'used'
        ? '<span class="badge bg-secondary">Used</span>'
        : '<span class="badge bg-success">Unused</span>';

    $printUrl = 'php/fetch/print_ticket_code.php?tc_id=' . (int)$row['tc_id']
              . '&unit=' . rawurlencode($row['unit_name']);
    $action = '<a target="_blank" class="btn btn-sm btn-outline-primary" href="' . $printUrl . '">'
            . '<i class="ti ti-printer"></i> Print</a>';

    $data[] = [
        '<strong>' . $code . '</strong>',
        $drv,
        $unit,
        $date,
        $status,
        $action
    ];
}

echo json_encode([
    'draw'            => intval($_POST['draw'] ?? 0),
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => $data
]);
