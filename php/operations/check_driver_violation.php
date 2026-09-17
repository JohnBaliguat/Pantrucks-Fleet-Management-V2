<?php
include '../config/config.php';
header('Content-Type: application/json');

$driver_id = $_POST['driver_id'] ?? '';

if (empty($driver_id)) {
    echo json_encode([
        "status" => "error",
        "message" => "Driver ID is missing."
    ]);
    exit;
}

$sql = "
    SELECT vr_type, vr_description, vr_date
    FROM violation_record
    WHERE driver_id = ?
    AND vr_status = 'Active'
";

$stmt = $conn->prepare($sql);
$stmt->execute([$driver_id]);
$result = $stmt;

if ($result->rowCount() > 0) {

    $violations = [];
    while ($row = $result->fetch()) {
        $violations[] = $row;
    }

    echo json_encode([
        "status" => "violation",
        "violations" => $violations
    ]);
    exit;
}

echo json_encode([
    "status" => "clear"
]);
