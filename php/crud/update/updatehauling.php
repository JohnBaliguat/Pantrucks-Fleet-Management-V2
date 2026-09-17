<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = pt_pg_escape($conn, $_POST['hauling_id']);
    $segment = pt_pg_escape($conn, $_POST['hauling_segment']);
    $type = pt_pg_escape($conn, $_POST['hauling_type']);

    if (empty($id) || empty($segment) || empty($type)) {
        echo "All fields are required.";
        exit;
    }

    $sql = "UPDATE hauling 
            SET hauling_segment = '$segment', hauling_type = '$type' 
            WHERE hauling_id = '$id'";

    if ($conn->query($sql)) {
        echo "Hauling record updated successfully.";
    } else {
        echo "Update failed: " . (($conn->errorInfo()[2]) ?? "");
    }
}
?>
