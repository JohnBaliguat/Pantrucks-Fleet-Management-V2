<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $segment = pt_pg_escape($conn, $_POST['hauling_segment']);
    $type = pt_pg_escape($conn, $_POST['hauling_type']);

    if (empty($segment) || empty($type)) {
        echo "Please fill all fields.";
        exit;
    }

    $sql = "INSERT INTO hauling (hauling_segment, hauling_type) VALUES ('$segment', '$type')";

    if ($conn->query($sql)) {
        echo "Hauling data inserted successfully.";
    } else {
        echo "Error: " . (($conn->errorInfo()[2]) ?? "");
    }
}
?>
