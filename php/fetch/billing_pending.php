<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']); exit;
}

$status = $_GET['status'] ?? 'pending';   // pending | closed | all
$where  = '';
if ($status === 'pending') {
    $where = "WHERE d.workflow_stage IN ('delivered', 'pod_captured')";
} elseif ($status === 'closed') {
    $where = "WHERE d.workflow_stage IN ('billing_closed', 'client_notified')";
}

$sql = "SELECT d.d_id, d.booking_no, d.costumer, d.d_truck, d.d_driverName, d.workflow_stage,
               d.billing_amount, d.billing_currency, d.billing_closed_at, d.client_notified_at,
               b.booking_type, b.customs_cleared, b.notify_email
        FROM dispatch d
        LEFT JOIN booking b ON b.booking_no = d.booking_no
        $where
        ORDER BY d.d_id DESC LIMIT 200";
$res = $conn->query($sql);
$rows = [];
while ($r = $res->fetch()) {
    // Also pull whether all customs_cleared etc. for the badge.
    // Compute notify-readiness: client has email or phone configured.
    $cs = $conn->prepare("SELECT notify_email, notify_phone FROM customer WHERE customer_code = ? LIMIT 1");
    $cs->execute([$r['costumer']]);
    $cust = $cs->fetch();
$r['notify_ready'] = $cust && (!empty($cust['notify_email']) || !empty($cust['notify_phone']));
    $rows[] = $r;
}
echo json_encode(['status' => 'success', 'rows' => $rows]);
