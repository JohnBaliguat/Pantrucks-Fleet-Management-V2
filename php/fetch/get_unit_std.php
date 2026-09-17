<?php
include __DIR__ . '/../config/config.php';

if (!isset($_GET['unit'])) { echo '0'; exit; }
$unit_name = $_GET['unit'];

$stmt = $conn->prepare("SELECT unit_std FROM units WHERE unit_name = ? LIMIT 1");
$stmt->execute([$unit_name]);
$unit_std = $stmt->fetchColumn();
if ($unit_std !== false) {
    echo $unit_std;
} else {
    echo '0';
}
