<?php
include "../../config/config.php";

$response = ["valid" => false, "msg" => "Invalid request"];

if (isset($_POST['smp_id'])) {

    $id = $_POST['smp_id'];

    $sql = $conn->query("DELETE FROM shop_manpower WHERE smp_id = '$id'");

    if ($sql) {
        $response = ["valid" => true, "msg" => "Shop manpower deleted successfully"];
    } else {
        $response = ["valid" => false, "msg" => "Delete unsuccessful"];
    }
}

echo json_encode($response);
?>
