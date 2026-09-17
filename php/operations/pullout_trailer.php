<?php
include '../config/config.php';

if (!isset($_POST['trailer_id'])) {
    echo "invalid";
    exit;
}

$trailer_id = (int)$_POST['trailer_id'];
if ($trailer_id <= 0) {
    echo "invalid";
    exit;
}

$stmt = $conn->prepare("SELECT trailer_name, driver_id FROM trailer WHERE trailer_id = ? LIMIT 1");
$stmt->execute([$trailer_id]);
$trailer = $stmt->fetch();
if (!$trailer) {
    echo "invalid";
    exit;
}

$trailerName = trim($trailer['trailer_name'] ?? '');
$driverId = (int)($trailer['driver_id'] ?? 0);

$conn->beginTransaction();

try {
    $stmt = $conn->prepare(
        "UPDATE trailer
         SET trailer_assignto = '',
             driver_id = 0,
             trailer_status = 'Good'
         WHERE trailer_id = ?"
    );
    $stmt->execute([$trailer_id]);
if ($trailerName !== '') {
        $stmt = $conn->prepare(
            "UPDATE units
             SET unit_assigntrailer = ''
             WHERE unit_assigntrailer = ?"
        );
        $stmt->execute([$trailerName]);
}

    if ($driverId > 0) {
        $stmt = $conn->prepare(
            "UPDATE units
             SET unit_assigntrailer = ''
             WHERE driver_id = ?"
        );
        $stmt->execute([$driverId]);
}

    $conn->commit();
    echo "success";
} catch (Throwable $e) {
    $conn->rollBack();
    echo "error: " . $e->getMessage();
}
