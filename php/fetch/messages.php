<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
$id   = (int)($_SESSION['user_id'] ?? 0);
if (!in_array($role, ['Driver', 'Dispatcher', 'Dispatch Admin', 'Admin'], true) || $id <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Login required']); exit;
}
$since = (int)($_GET['since'] ?? 0);
$limit = (int)($_GET['limit'] ?? 100);
if ($limit > 200) $limit = 200;

// Driver sees: messages they sent + messages targeted at them or at the chosen group.
// Optional to_role query param scopes the thread (dispatcher / rescue / maintenance).
// Dispatcher sees: messages from any driver to dispatcher + their own replies.
if ($role === 'Driver') {
    $threadRole = strtolower(trim((string)($_GET['to_role'] ?? 'dispatcher')));
    if (!in_array($threadRole, ['dispatcher', 'rescue', 'maintenance'], true)) {
        $threadRole = 'dispatcher';
    }
    // Pull only the leg of the conversation between this driver and the selected role.
    $sql = "SELECT msg_id, from_role, from_id, to_role, to_id, body, d_id, sent_at, read_at
            FROM message
            WHERE msg_id > ?
              AND (
                    (from_role = 'driver' AND from_id = ? AND to_role = ?)
                 OR (from_role = ? AND to_role = 'driver' AND (to_id IS NULL OR to_id = ?))
              )
            ORDER BY msg_id ASC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$since, $id, $threadRole, $threadRole, $id, $limit]);
} else {
    // Dispatcher / Dispatch Admin / Admin.
    //
    // New params:
    //   target_role = driver | rescue | maintenance  (default: driver)
    //   target_id   = int id of the chosen counterpart
    //
    // Back-compat: if only driver_id is provided (legacy callers), it's
    // treated as target_role=driver, target_id=driver_id.
    $targetRole = strtolower(trim((string)($_GET['target_role'] ?? '')));
    $targetId   = isset($_GET['target_id']) && $_GET['target_id'] !== '' ? (int)$_GET['target_id'] : 0;
    if ($targetRole === '' && isset($_GET['driver_id'])) {
        $targetRole = 'driver';
        $targetId   = (int)$_GET['driver_id'];
    }
    if (!in_array($targetRole, ['driver', 'rescue', 'maintenance'], true)) {
        $targetRole = '';
    }

    if ($targetRole !== '' && $targetId > 0) {
        // For dispatcher ↔ driver the "dispatcher" side is the entire
        // group (multiple users may reply). For rescue/maintenance there
        // is exactly one counterpart user, identified by user_id.
        $sql = "SELECT msg_id, from_role, from_id, to_role, to_id, body, d_id, sent_at, read_at
                FROM message
                WHERE msg_id > ?
                  AND ((from_role = ? AND from_id = ?)
                       OR (to_role   = ? AND to_id   = ?))
                ORDER BY msg_id ASC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$since, $targetRole, $targetId, $targetRole, $targetId, $limit]);
    } else {
        $sql = "SELECT msg_id, from_role, from_id, to_role, to_id, body, d_id, sent_at, read_at
                FROM message
                WHERE msg_id > ?
                ORDER BY msg_id ASC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$since, $limit]);
    }
}
$res = $stmt;
$rows = [];
while ($r = $res->fetch()) { $rows[] = $r; }
// --- Resolve a human-friendly sender_label per row ------------------
// One bulk query per source table so we don't fan out N queries.
$driverIds = [];
$userIds   = [];
foreach ($rows as $r) {
    $fid = (int)$r['from_id'];
    if ($fid <= 0) continue;
    if ($r['from_role'] === 'driver') {
        $driverIds[$fid] = true;
    } elseif (in_array($r['from_role'], ['dispatcher', 'admin', 'rescue', 'maintenance'], true)) {
        $userIds[$fid] = true;
    }
}
$driverNames = [];
$userNames   = [];
if (!empty($driverIds)) {
    $idList = implode(',', array_map('intval', array_keys($driverIds)));
    $q = $conn->query("SELECT driver_id, CONCAT(driver_lname, ', ', driver_fname) AS name FROM drivers WHERE driver_id IN ($idList)");
    while ($r = $q->fetch()) { $driverNames[(int)$r['driver_id']] = $r['name']; }
}
if (!empty($userIds)) {
    $idList = implode(',', array_map('intval', array_keys($userIds)));
    $q = $conn->query("SELECT user_id, TRIM(CONCAT(user_fname, ' ', user_lname)) AS name, user_type FROM \"user\" WHERE user_id IN ($idList)");
    while ($r = $q->fetch()) { $userNames[(int)$r['user_id']] = $r['name'] !== '' ? $r['name'] : ($r['user_type'] . ' #' . $r['user_id']); }
}
foreach ($rows as &$r) {
    $fid = (int)$r['from_id'];
    if ($r['from_role'] === 'driver') {
        $r['sender_label'] = $driverNames[$fid] ?? ('Driver #' . $fid);
    } elseif ($r['from_role'] === 'dispatcher' || $r['from_role'] === 'admin') {
        $name = $userNames[$fid] ?? ('Dispatcher #' . $fid);
        $r['sender_label'] = 'Dispatcher: ' . $name;
    } elseif ($r['from_role'] === 'rescue') {
        $name = $userNames[$fid] ?? ('Rescuer #' . $fid);
        $r['sender_label'] = 'Rescuer: ' . $name;
    } elseif ($r['from_role'] === 'maintenance') {
        $name = $userNames[$fid] ?? ('Maintenance #' . $fid);
        $r['sender_label'] = 'Maintenance: ' . $name;
    } elseif ($r['from_role'] === 'system') {
        $r['sender_label'] = 'System';
    } else {
        $r['sender_label'] = ucfirst($r['from_role']);
    }
}
unset($r);

// Mark unread messages as read for the current viewer (best-effort).
if ($role === 'Driver') {
    // Only clear the thread the driver is currently looking at, so the
    // badges for the other two roles stay accurate.
    $stmtMark = $conn->prepare(
        "UPDATE message SET read_at = NOW()
         WHERE read_at IS NULL AND to_role = 'driver' AND (to_id = ? OR to_id IS NULL)
           AND from_role = ?"
    );
    $stmtMark->execute([$id, $threadRole]);
} elseif (isset($targetRole) && $targetRole !== '' && $targetId > 0) {
    // Dispatcher / Admin viewing a specific counterpart thread —
    // mark messages from that counterpart as read.
    $stmtMark = $conn->prepare(
        "UPDATE message SET read_at = NOW()
         WHERE read_at IS NULL AND from_role = ? AND from_id = ?"
    );
    $stmtMark->execute([$targetRole, $targetId]);
}

echo json_encode(['status' => 'success', 'rows' => $rows]);
