<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $fname = $_POST['smp_fname'];
    $mname = $_POST['smp_mname'];
    $lname = $_POST['smp_lname'];
    $role  = $_POST['smp_role'];

    $sql = "INSERT INTO shop_manpower 
            (smp_fname, smp_mname, smp_lname, smp_role, smp_status)
            VALUES 
            ('$fname', '$mname', '$lname', '$role', 'Active')";

    if ($conn->query($sql)) {
        echo "Shop manpower added successfully!";
    } else {
        echo "Error: " . (($conn->errorInfo()[2]) ?? "");
    }
}
?>
