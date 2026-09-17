<?php
session_start();
include "php/config/config.php";
require_once __DIR__ . '/php/helpers/activity_log_helper.php';

if (isset($_POST['login-btn'])) {
    function validate($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    $uname = validate($_POST['uname']);
    $pass  = validate($_POST['pass']);

    if (empty($uname)) {
        header("Location: login.php?error=User Name is required");
        exit();
    } else if (empty($pass)) {
        header("Location: login.php?error=Password is required");
        exit();
    } else {
        // 1️⃣ Check in user table first ("user" is reserved in PostgreSQL).
        $stmt = $conn->prepare('SELECT * FROM "user" WHERE user_name = ?');
        $stmt->execute([$uname]);
        $row = $stmt->fetch();

        if ($row) {
            $hashedPassword = $row['user_pass'];

            if ($row['user_accountstat'] === "Pending") {
                header("Location: login.php?error=Account is still pending approval");
                exit();
            }

            if (password_verify($pass, $hashedPassword)) {
                $_SESSION['user_id']   = $row['user_id'];
                $_SESSION['user_type'] = $row['user_type'];

                $loginName = trim(implode(' ', array_filter([
                    trim((string)($row['user_fname'] ?? '')),
                    trim((string)($row['user_lname'] ?? '')),
                ], 'strlen'))) ?: (string)($row['user_name'] ?? $uname);
                pt_log_activity($conn, (int)$row['user_id'], (string)$row['user_type'], $loginName, 'Login', 'Signed in');

                if ($row['user_type'] === "Admin") {
                    header("Location: dashboard");
                } else if ($row['user_type'] === "Dispatcher") {
                    header("Location: dispatch-dashboard");
                } else if ($row['user_type'] === "Dispatch Admin") {
                    header("Location: dispatch-admin-dashboard");
                } else if ($row['user_type'] === "Booker") {
                    header("Location: dispatch-addbook");
                } else if ($row['user_type'] === "Shop") {
                    header("Location: shop-dashboard");
                } else if ($row['user_type'] === "User") {
                    header("Location: hr-dashboard");
                } else if ($row['user_type'] === "Rescue") {
                    header("Location: shop-dashboard");
                } else if ($row['user_type'] === "HR-Admin") {
                    header("Location: hra-dashboard");
                } else if ($row['user_type'] === "Visual") {
                    header("Location: visual-dashboard");
                } else if ($row['user_type'] === "Gate-Guard") {
                    header("Location: gate-dashboard");
                } else if ($row['user_type'] === "Maintenance") {
                    header("Location: maintenance-dashboard");
                } else if ($row['user_type'] === "Client") {
                    header("Location: client-dashboard");
                } else if ($row['user_type'] === "Gastender") {
                    header("Location: gas-ticketing");
                } else if ($row['user_type'] === "Payroll") {
                    header("Location: payroll-dashboard");
                } else if ($row['user_type'] === "Executive") {
                    header("Location: executive");
                }
                exit();
            } else {
                header("Location: login.php?error=Incorrect username or password");
                exit();
            }
        } else {
            // 2️⃣ If not found in user table, check drivers table.
            $stmtDriver = $conn->prepare("SELECT * FROM drivers WHERE driver_uname = ?");
            $stmtDriver->execute([$uname]);
            $driver = $stmtDriver->fetch();

            if ($driver) {
                $hashedPassword = $driver['driver_pass'];

                if ($driver['driver_account_status'] === "Pending") {
                    header("Location: login.php?error=Driver account is still pending approval");
                    exit();
                }

                if (password_verify($pass, $hashedPassword)) {
                    $_SESSION['user_type'] = "Driver";
                    $_SESSION['user_id']   = $driver['driver_id'];
                    $_SESSION['user_name'] = $driver['driver_uname'];
                    $_SESSION['user_rfid'] = $driver['driver_rfid'];

                    $drvName = trim(($driver['driver_lname'] ?? '') . ', ' . ($driver['driver_fname'] ?? ''));
                    pt_log_activity($conn, (int)$driver['driver_id'], 'Driver', $drvName ?: (string)$driver['driver_uname'], 'Login', 'Signed in');

                    header("Location: driver-dashboard");
                    exit();
                } else {
                    header("Location: login.php?error=Incorrect username or password");
                    exit();
                }
            } else {
                header("Location: login.php?error=Incorrect username or password");
                exit();
            }
        }
    }
}
?>
