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

$kind   = strtolower(trim($_GET['kind']   ?? 'all'));   // truck|genset|trailer|all
$status = strtolower(trim($_GET['status'] ?? 'all'));   // blocked|scheduled|available|all
$q      = trim($_GET['q'] ?? '');

$where = [];
if ($status === 'blocked')   $where[] = 'maintenance_blocked = TRUE';
if ($status === 'available') $where[] = 'maintenance_blocked = FALSE';
if ($q !== '') {
    $like = '%' . pt_pg_escape($conn, $q) . '%';
    $where[] = "code LIKE '$like'";
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// Union trucks + gensets (from units) with trailers.
$kindFilter = '';
if (in_array($kind, ['truck', 'genset'], true)) $kindFilter = " AND unit_type = '" . $kind . "'";
$blockOnly  = $status === 'blocked'   ? ' AND maintenance_blocked = TRUE' : '';
$availOnly  = $status === 'available' ? ' AND maintenance_blocked = FALSE' : '';

$rows = [];

if ($kind === 'all' || $kind === 'truck' || $kind === 'genset') {
    $sql = "SELECT unit_name AS unit_code, unit_type AS unit_kind, unit_status,
                   maintenance_blocked,
                   COALESCE(NULLIF(maintenance_reason, ''), (
                       SELECT um.reason FROM unit_maintenance um
                       WHERE um.unit_kind = units.unit_type AND um.unit_code = units.unit_name
                         AND um.status IN ('active', 'scheduled')
                       ORDER BY um.um_id DESC LIMIT 1
                   )) AS maintenance_reason,
                   COALESCE(maintenance_expected_return, (
                       SELECT um.expected_return FROM unit_maintenance um
                       WHERE um.unit_kind = units.unit_type AND um.unit_code = units.unit_name
                         AND um.status IN ('active', 'scheduled')
                       ORDER BY um.um_id DESC LIMIT 1
                   )) AS maintenance_expected_return,
                   maintenance_blocked_at,
                   (
                       SELECT um.scheduled_start_at FROM unit_maintenance um
                       WHERE um.unit_kind = units.unit_type AND um.unit_code = units.unit_name
                         AND um.status IN ('active', 'scheduled')
                       ORDER BY um.um_id DESC LIMIT 1
                   ) AS scheduled_start_at,
                   (
                       SELECT um.status FROM unit_maintenance um
                       WHERE um.unit_kind = units.unit_type AND um.unit_code = units.unit_name
                         AND um.status IN ('active', 'scheduled')
                       ORDER BY um.um_id DESC LIMIT 1
                   ) AS maintenance_ticket_status
            FROM units
            WHERE 1=1 $kindFilter $blockOnly $availOnly
              AND unit_type IN ('truck', 'genset')";
    if ($q !== '') { $like = pt_pg_escape($conn, $q); $sql .= " AND unit_name LIKE '%$like%'"; }
    $sql .= " ORDER BY unit_name ASC LIMIT 1000";
    $res = $conn->query($sql);
    while ($r = $res->fetch()) { $rows[] = $r; }
}
if ($kind === 'all' || $kind === 'trailer') {
    $sql = "SELECT trailer_name AS unit_code, 'trailer' AS unit_kind, trailer_status AS unit_status,
                   maintenance_blocked,
                   COALESCE(NULLIF(maintenance_reason, ''), (
                       SELECT um.reason FROM unit_maintenance um
                       WHERE um.unit_kind = 'trailer' AND um.unit_code = trailer.trailer_name
                         AND um.status IN ('active', 'scheduled')
                       ORDER BY um.um_id DESC LIMIT 1
                   )) AS maintenance_reason,
                   COALESCE(maintenance_expected_return, (
                       SELECT um.expected_return FROM unit_maintenance um
                       WHERE um.unit_kind = 'trailer' AND um.unit_code = trailer.trailer_name
                         AND um.status IN ('active', 'scheduled')
                       ORDER BY um.um_id DESC LIMIT 1
                   )) AS maintenance_expected_return,
                   maintenance_blocked_at,
                   (
                       SELECT um.scheduled_start_at FROM unit_maintenance um
                       WHERE um.unit_kind = 'trailer' AND um.unit_code = trailer.trailer_name
                         AND um.status IN ('active', 'scheduled')
                       ORDER BY um.um_id DESC LIMIT 1
                   ) AS scheduled_start_at,
                   (
                       SELECT um.status FROM unit_maintenance um
                       WHERE um.unit_kind = 'trailer' AND um.unit_code = trailer.trailer_name
                         AND um.status IN ('active', 'scheduled')
                       ORDER BY um.um_id DESC LIMIT 1
                   ) AS maintenance_ticket_status
            FROM trailer
            WHERE 1=1 $blockOnly $availOnly";
    if ($q !== '') { $like = pt_pg_escape($conn, $q); $sql .= " AND trailer_name LIKE '%$like%'"; }
    $sql .= " ORDER BY trailer_name ASC LIMIT 1000";
    $res = $conn->query($sql);
    while ($r = $res->fetch()) { $rows[] = $r; }
}

usort($rows, function ($a, $b) {
    return strcmp($a['unit_code'], $b['unit_code']);
});

if ($status === 'scheduled') {
    $rows = array_values(array_filter($rows, function ($row) {
        return ($row['maintenance_ticket_status'] ?? '') === 'scheduled';
    }));
} elseif ($status === 'available') {
    $rows = array_values(array_filter($rows, function ($row) {
        return ($row['maintenance_ticket_status'] ?? '') !== 'scheduled';
    }));
}

echo json_encode(['status' => 'success', 'rows' => $rows]);
