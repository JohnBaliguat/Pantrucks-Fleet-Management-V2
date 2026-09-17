<?php
include "../../config/config.php";

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);

    $sql = "UPDATE rescue_unit SET r_status = 'dispatched' WHERE r_id = ?";
    $stmt = $conn->prepare($sql);

    try {
        $stmt->execute([$id]);
        echo json_encode(["status" => "success", "message" => "Status updated."]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Failed to update status."]);
    }
}
?>
