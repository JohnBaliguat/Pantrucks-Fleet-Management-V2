<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
$id   = (int)($_SESSION['user_id'] ?? 0);
if (!in_array($role, ['Driver', 'Dispatcher', 'Dispatch Admin', 'Admin'], true) || $id <= 0) {
    echo json_encode(['status' => 'success', 'count' => 0, 'recent' => []]); exit;
}

// When include_recent=1, the realtime-alerts script can read the
// latest unseen messages and toast them.
$includeRecent = !empty($_GET['include_recent']);
$sinceId       = (int)($_GET['since'] ?? 0);

$count = 0;
$recent = [];

if ($role === 'Driver') {
    // Optional to_role scopes the count to a single thread
    // (dispatcher / rescue / maintenance) — used for per-tab badges.
    $threadRole = strtolower(trim((string)($_GET['to_role'] ?? '')));
    if ($threadRole !== '' && !in_array($threadRole, ['dispatcher', 'rescue', 'maintenance'], true)) {
        $threadRole = '';
    }
    if ($threadRole !== '') {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) c FROM message
             WHERE read_at IS NULL
               AND from_role = ?
               AND to_role = 'driver' AND (to_id = ? OR to_id IS NULL)"
        );
        $stmt->execute([$threadRole, $id]);
    } else {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) c FROM message
             WHERE read_at IS NULL
               AND from_role <> 'driver'
               AND ((to_role = 'driver' AND (to_id = ? OR to_id IS NULL)))"
        );
        $stmt->execute([$id]);
    }
    $count = (int)$stmt->fetch()['c'];
    if ($includeRecent) {
        // Resolve the actual dispatcher name so the toast says
        // "Dispatcher: Nathan Sulatan" instead of just "Dispatcher".
        $stmt = $conn->prepare(
            "SELECT m.msg_id, m.from_role, m.from_id, m.body, m.sent_at, m.d_id,
                    TRIM(CONCAT(u.user_fname, ' ', u.user_lname)) AS user_name
             FROM message m
             LEFT JOIN \"user\" u ON u.user_id = m.from_id
             WHERE m.msg_id > ?
               AND m.from_role <> 'driver'
               AND ((m.to_role = 'driver' AND (m.to_id = ? OR m.to_id IS NULL)))
             ORDER BY m.msg_id ASC LIMIT 20"
        );
        $stmt->execute([$sinceId, $id]);
        $res = $stmt;
        while ($r = $res->fetch()) {
            if ($r['from_role'] === 'system') {
                $r['sender_label'] = 'System';
            } else {
                $name = trim($r['user_name'] ?? '');
                if ($name === '') $name = 'Dispatcher #' . $r['from_id'];
                $r['sender_label'] = 'Dispatcher: ' . $name;
            }
            unset($r['user_name']);
            $recent[] = $r;
        }
}
} else {
    // Dispatcher / Admin. Optional from_role scopes the count to a
    // single counterpart kind (driver / rescue / maintenance) — used by
    // the chat-tab badges. Default counts all three.
    $countRole = strtolower(trim((string)($_GET['from_role'] ?? '')));
    if (!in_array($countRole, ['driver', 'rescue', 'maintenance'], true)) {
        $countRole = '';
    }
    if ($countRole !== '') {
        $stmt = $conn->prepare("SELECT COUNT(*) c FROM message WHERE read_at IS NULL AND from_role = ?");
        $stmt->execute([$countRole]);
        $count = (int)$stmt->fetch()['c'];
} else {
        $r = $conn->query("SELECT COUNT(*) c FROM message WHERE read_at IS NULL AND from_role IN ('driver','rescue','maintenance')");
        $count = (int)$r->fetch()['c'];
    }

    if ($includeRecent) {
        // Pull recent driver messages + resolve sender name.
        $stmt = $conn->prepare(
            "SELECT m.msg_id, m.from_id, m.body, m.sent_at, m.d_id,
                    CONCAT(d.driver_lname, ', ', d.driver_fname) AS sender_label
             FROM message m
             LEFT JOIN drivers d ON d.driver_id = m.from_id
             WHERE m.msg_id > ?
               AND m.from_role = 'driver'
             ORDER BY m.msg_id ASC LIMIT 20"
        );
        $stmt->execute([$sinceId]);
        $res = $stmt;
        while ($row = $res->fetch()) {
            $row['from_role']    = 'driver';
            $row['sender_label'] = $row['sender_label'] ?: ('Driver #' . $row['from_id']);
            $recent[] = $row;
        }
}
}

echo json_encode([
    'status' => 'success',
    'count'  => $count,
    'role'   => $role,
    'recent' => $recent,
]);
