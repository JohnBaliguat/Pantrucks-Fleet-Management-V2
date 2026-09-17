<?php
include "../config/config.php";

$s_id      = intval($_POST['rescue_id']); // shop_unit ID
$manpower  = $_POST['manpower'];          // array of smp_id

$conn->beginTransaction();

try {

    // 1️⃣ Update shop_unit status
    $stmt = $conn->prepare(
        "UPDATE shop_unit 
         SET s_status = 'assigned' 
         WHERE s_id = ?"
    );
    $stmt->execute([$s_id]);

    // 2️⃣ Insert shop_record
    $stmt = $conn->prepare(
        "INSERT INTO shop_record (s_id, sr_description, sr_date)
         VALUES (?, 'Unit Assigned', NOW())"
    );
    $stmt->execute([$s_id]);

    // Get inserted shop_record ID
    $sr_id = $conn->lastInsertId();

    // 3️⃣ Insert assign_manpower (multiple)
    $stmt = $conn->prepare(
        "INSERT INTO assign_manpower (sr_id, smp_id)
         VALUES (?, ?)"
    );

    foreach ($manpower as $smp_id) {
        $smp_id = intval($smp_id);
        $stmt->execute([$sr_id, $smp_id]);
    }

    $conn->commit();

    echo json_encode([
        "status"  => "success",
        "message" => "Unit successfully assigned."
    ]);

} catch (Exception $e) {

    $conn->rollBack();

    echo json_encode([
        "status"  => "error",
        "message" => "Assignment failed."
    ]);
}
