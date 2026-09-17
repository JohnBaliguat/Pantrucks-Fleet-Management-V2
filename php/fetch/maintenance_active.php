<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Maintenance', 'Admin', 'Dispatcher'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}

// Return units to the fleet automatically once their expected return date is up.
require_once __DIR__ . '/../lib/maintenance_auto_release.php';
pt_auto_release_expired_maintenance($conn);

$sql = "SELECT um_id, unit_kind, unit_code, category, severity, reason,
               blocked_at, expected_return, cost_labor, cost_parts
        FROM unit_maintenance
        WHERE status = 'active'
        ORDER BY blocked_at DESC LIMIT 500";
$res = $conn->query($sql);
$rows = [];
$summary = ['blocked' => 0, 'high' => 0, 'overdue' => 0, 'cost' => 0.0];
$today = date('Y-m-d');
while ($r = $res->fetch()) {
    $rows[] = $r;
    $summary['blocked']++;
    if ($r['severity'] === 'high') $summary['high']++;
    if (!empty($r['expected_return']) && $r['expected_return'] < $today) $summary['overdue']++;
    $summary['cost'] += (float)$r['cost_labor'] + (float)$r['cost_parts'];
}
$summary['cost'] = round($summary['cost'], 2);

echo json_encode(['status' => 'success', 'rows' => $rows, 'summary' => $summary]);
