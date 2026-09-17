<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['user_Id1'];
    $userStat = $_POST['user_stat1'];
    $username = $_POST['username1'];
    $firstname = $_POST['user_fname1'];
    $lastname = $_POST['user_lname1'];
    $middlename = $_POST['user_mname1'];
    $useremail = $_POST['user_email1'];
    $userpass = $_POST['user_pass1'];
    $user_type = $_POST['user_type1'];
    $user_assignLocation = $_POST['user_assignLocation1'];
    // Phase 12 — only set customer_code when the user is a Client;
    // switching to another role clears the link.
    $customer_code = ($user_type === 'Client') ? trim($_POST['customer_code1'] ?? '') : '';
    $customer_code = pt_pg_escape($conn, $customer_code);

    $image = '';

    if (isset($_FILES['user_image1']['name']) && !empty($_FILES['user_image1']['name'])) {
        $image = $_FILES['user_image1']['name'];
        $tempname = $_FILES['user_image1']['tmp_name'];
        $folder = 'uploads/' . $image;

        move_uploaded_file($tempname, $folder);
    }

    if (!empty($userpass)) {
        $hashedPassword = password_hash($userpass, PASSWORD_DEFAULT);
        if (!empty($image)) {
            $sql = "UPDATE \"user\" SET user_name='$username', user_fname='$firstname', user_lname='$lastname', user_mname='$middlename', user_assignLocation='$user_assignLocation', user_email='$useremail', user_pass='$hashedPassword', user_type='$user_type', user_image='$image', user_accountStat = '$userStat', customer_code='$customer_code' WHERE user_id='$id'";
        } else {
            $sql = "UPDATE \"user\" SET user_name='$username', user_fname='$firstname', user_lname='$lastname', user_mname='$middlename', user_assignLocation='$user_assignLocation', user_email='$useremail', user_pass='$hashedPassword', user_type='$user_type', user_accountStat = '$userStat', customer_code='$customer_code' WHERE user_id='$id'";
        }
    } else {
        if (!empty($image)) {
            $sql = "UPDATE \"user\" SET user_name='$username', user_fname='$firstname', user_lname='$lastname', user_mname='$middlename', user_assignLocation='$user_assignLocation', user_email='$useremail', user_type='$user_type', user_image='$image', user_accountStat = '$userStat', customer_code='$customer_code' WHERE user_id='$id'";
        } else {
            $sql = "UPDATE \"user\" SET user_name='$username', user_fname='$firstname', user_lname='$lastname', user_mname='$middlename', user_assignLocation='$user_assignLocation', user_email='$useremail', user_type='$user_type', user_accountStat = '$userStat', customer_code='$customer_code' WHERE user_id='$id'";
        }
    }

    $result = $conn->query($sql);

    if ($result) {
        echo "Data updated successfully!";
    } else {
        $error_message = "Error: " . (($conn->errorInfo()[2]) ?? "");
        http_response_code(500);
        echo $error_message;
        error_log($error_message, 0);
    }
}

?>
