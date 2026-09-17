<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Maintenance', 'Admin', 'Dispatcher'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}

$q = trim($_GET['q'] ?? '');
$where = '';
if ($q !== '') {
    $like = pt_pg_escape($conn, $q);
    $where = "WHERE unit_code LIKE '%$like%'";
}

$sql = "SELECT um_id, unit_kind, unit_code, category, severity, reason,
               photo_path, expected_return, blocked_by, blocked_at,
               released_by, released_at, release_notes,
               cost_labor, cost_parts, status
        FROM unit_maintenance
        $where
        ORDER BY um_id DESC LIMIT 500";
$res = $conn->query($sql);
$rows = [];
while ($r = $res->fetch()) { $rows[] = $r; }

echo json_encode(['status' => 'success', 'rows' => $rows]);
