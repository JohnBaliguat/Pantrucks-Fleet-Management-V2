<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $id     = $_POST['smp_id1'];
    $fname  = $_POST['smp_fname1'];
    $mname  = $_POST['smp_mname1'];
    $lname  = $_POST['smp_lname1'];
    $role   = $_POST['smp_role1'];
    $status = $_POST['smp_status1'];

    $sql = "UPDATE shop_manpower SET
                smp_fname  = '$fname',
                smp_mname  = '$mname',
                smp_lname  = '$lname',
                smp_role   = '$role',
                smp_status = '$status'
            WHERE smp_id = '$id'";

    if ($conn->query($sql)) {
        echo "Shop manpower updated successfully!";
    } else {
        http_response_code(500);
        echo "Error: " . (($conn->errorInfo()[2]) ?? "");
    }
}
?>
