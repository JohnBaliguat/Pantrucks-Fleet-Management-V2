<?php
include "php/config/config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver_id = $_POST['driver_id'];
    $uname     = $_POST['uname'];
    $pass      = $_POST['pass'];

    if (empty($driver_id) || empty($uname) || empty($pass)) {
        echo json_encode(["status" => "error", "message" => "All fields are required."]);
        exit;
    }

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $pass)) {
        echo json_encode(["status" => "error", "message" => "Password must be at least 8 characters, include uppercase, lowercase, number, and special character."]);
        exit;
    }

    $check = $conn->prepare("SELECT driver_id FROM drivers WHERE driver_uname = ?");
    $check->execute([$uname]);
    if ($check->fetch()) {
        echo json_encode(["status" => "error", "message" => "Username already taken!"]);
        exit;
    }

    $hashedPass = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        "UPDATE drivers
         SET driver_uname = ?, driver_pass = ?, driver_account_status = 'Pending'
         WHERE driver_id = ?"
    );
    $stmt->execute([$uname, $hashedPass, $driver_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["status" => "success", "message" => "Your account has been created successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Account already activated or invalid ID."]);
    }
}
?>
