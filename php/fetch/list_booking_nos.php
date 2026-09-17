<?php
header('Content-Type: application/json');
include __DIR__ . '/../config/config.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = 25;

if ($q === '') {
    $stmt = $conn->prepare("SELECT booking_no, costumer FROM booking ORDER BY booking_id DESC LIMIT ?");
    $stmt->execute([$limit]);
} else {
    $like = '%' . $q . '%';
    $stmt = $conn->prepare("SELECT booking_no, costumer FROM booking WHERE booking_no LIKE ? OR costumer LIKE ? ORDER BY booking_id DESC LIMIT ?");
    $stmt->execute([$like, $like, $limit]);
}
$res = $stmt;
$rows = [];
while ($r = $res->fetch()) { $rows[] = $r; }

echo json_encode(['status' => 'success', 'rows' => $rows]);
