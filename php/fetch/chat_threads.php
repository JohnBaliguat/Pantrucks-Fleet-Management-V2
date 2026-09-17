<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}

// Which role's threads are we listing? Defaults to drivers for back-compat.
$targetRole = strtolower(trim((string)($_GET['role'] ?? 'driver')));
if (!in_array($targetRole, ['driver', 'rescue', 'maintenance'], true)) {
    $targetRole = 'driver';
}

$rows = [];

if ($targetRole === 'driver') {
    // One row per driver who has ever sent or received a message.
    $sql = "SELECT
                d.driver_id AS target_id,
                CONCAT(d.driver_lname, ', ', d.driver_fname) AS target_name,
                d.shift_truck AS sub_label,
                (
                    SELECT m.body FROM message m
                    WHERE (m.from_role='driver' AND m.from_id = d.driver_id)
                       OR (m.to_role='driver'   AND m.to_id   = d.driver_id)
                    ORDER BY m.msg_id DESC LIMIT 1
                ) AS last_body,
                (
                    SELECT m.sent_at FROM message m
                    WHERE (m.from_role='driver' AND m.from_id = d.driver_id)
                       OR (m.to_role='driver'   AND m.to_id   = d.driver_id)
                    ORDER BY m.msg_id DESC LIMIT 1
                ) AS last_at,
                (
                    SELECT COUNT(*) FROM message m
                    WHERE m.from_role='driver' AND m.from_id = d.driver_id AND m.read_at IS NULL
                ) AS unread
            FROM drivers d
            WHERE EXISTS (
                SELECT 1 FROM message m
                WHERE (m.from_role='driver' AND m.from_id = d.driver_id)
                   OR (m.to_role='driver'   AND m.to_id   = d.driver_id)
            )
            ORDER BY last_at DESC
            LIMIT 200";
    $res = $conn->query($sql);
    while ($r = $res->fetch()) { $r['target_role'] = 'driver'; $rows[] = $r; }
} else {
    // For rescue / maintenance: list ALL users of that user_type, so the
    // dispatcher can start a brand-new conversation even if there's no
    // history yet. Sort active threads first.
    $userType = $targetRole === 'rescue' ? 'Rescue' : 'Maintenance';
    $sql = "SELECT
                u.user_id AS target_id,
                TRIM(CONCAT(u.user_fname, ' ', u.user_lname)) AS target_name,
                u.user_type AS sub_label,
                (
                    SELECT m.body FROM message m
                    WHERE (m.from_role = ? AND m.from_id = u.user_id)
                       OR (m.to_role   = ? AND m.to_id   = u.user_id)
                    ORDER BY m.msg_id DESC LIMIT 1
                ) AS last_body,
                (
                    SELECT m.sent_at FROM message m
                    WHERE (m.from_role = ? AND m.from_id = u.user_id)
                       OR (m.to_role   = ? AND m.to_id   = u.user_id)
                    ORDER BY m.msg_id DESC LIMIT 1
                ) AS last_at,
                (
                    SELECT COUNT(*) FROM message m
                    WHERE m.from_role = ? AND m.from_id = u.user_id AND m.read_at IS NULL
                ) AS unread
            FROM \"user\" u
            WHERE u.user_type = ?
              AND (u.user_accountstat IS NULL OR u.user_accountstat <> 'Pending')
            ORDER BY (last_at IS NULL) ASC, last_at DESC, target_name ASC
            LIMIT 200";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$targetRole, $targetRole, $targetRole, $targetRole, $targetRole, $userType]);
    $res = $stmt;
    while ($r = $res->fetch()) {
        if ($r['target_name'] === '') $r['target_name'] = $userType . ' #' . $r['target_id'];
        $r['target_role'] = $targetRole;
        $rows[] = $r;
    }
}

echo json_encode(['status' => 'success', 'role' => $targetRole, 'rows' => $rows]);
