<?php
include "../config/config.php";

$rescue_id = intval($_POST['rescue_id']);
$manpower  = $_POST['manpower']; // array

$conn->beginTransaction();

try {

    // 1️⃣ Update rescue status
    $stmt = $conn->prepare(
        "UPDATE rescue_unit SET r_status = 'dispatched' WHERE r_id = ?"
    );
    $stmt->execute([$rescue_id]);

    // 2️⃣ Insert rescue_record
    $stmt = $conn->prepare(
        "INSERT INTO rescue_record (r_id, r_description, r_date)
         VALUES (?, 'Rescue Dispatched', NOW())"
    );
    $stmt->execute([$rescue_id]);

    $rr_id = $conn->lastInsertId();

    // 3️⃣ Insert assign_manpower (multiple)
    $stmt = $conn->prepare(
        "INSERT INTO assign_manpower (rr_id, smp_id)
         VALUES (?, ?)"
    );

    foreach ($manpower as $smp_id) {
        $smp_id = intval($smp_id);
        $stmt->execute([$rr_id, $smp_id]);
    }

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Rescue dispatched and manpower assigned."
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode([
        "status" => "error",
        "message" => "Transaction failed."
    ]);
}
