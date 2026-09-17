<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['user_Id1'];
    $username = $_POST['username1'];
    $firstname = $_POST['user_fname1'];
    $lastname = $_POST['user_lname1'];
    $middlename = $_POST['user_mname1'];
    $useremail = $_POST['user_email1'];
    $userpass = $_POST['user_pass1'];
 
    $image = '';

    if (isset($_FILES['input-file1']['name']) && !empty($_FILES['input-file1']['name'])) {
        $image = $_FILES['input-file1']['name'];
        $tempname = $_FILES['input-file1']['tmp_name'];
        $folder = '../../assets/uploads/' . $image;

        move_uploaded_file($tempname, $folder);
    }

    if (!empty($userpass)) {
        $hashedPassword = password_hash($userpass, PASSWORD_DEFAULT);
        if (!empty($image)) {
            $sql = "UPDATE \"user\" SET user_name='$username', user_fname='$firstname', user_lname='$lastname', user_mname='$middlename', user_email='$useremail', user_pass='$hashedPassword', user_image='$image' WHERE user_id='$id'";
        } else {
            $sql = "UPDATE \"user\" SET user_name='$username', user_fname='$firstname', user_lname='$lastname', user_mname='$middlename', user_email='$useremail', user_pass='$hashedPassword' WHERE user_id='$id'";
        }
    } else {
        if (!empty($image)) {
            $sql = "UPDATE \"user\" SET user_name='$username', user_fname='$firstname', user_lname='$lastname', user_mname='$middlename', user_email='$useremail', user_image='$image' WHERE user_id='$id'";
        } else {
            $sql = "UPDATE \"user\" SET user_name='$username', user_fname='$firstname', user_lname='$lastname', user_mname='$middlename', user_email='$useremail' WHERE user_id='$id'";
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
