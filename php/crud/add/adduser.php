<?php
include "../../config/config.php";
require_once __DIR__ . '/../../helpers/auth_guard.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';
// Only administrators may create accounts. Previously this endpoint accepted
// unauthenticated POSTs (anonymous account creation + file upload).
require_role(['Admin', 'HR-Admin'], 'json');
csrf_verify('json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $firstname = trim($_POST['user_fname'] ?? '');
    $lastname = trim($_POST['user_lname'] ?? '');
    $middlename = trim($_POST['user_mname'] ?? '');
    $useremail = trim($_POST['user_email'] ?? '');
    $userpass = (string)($_POST['user_pass'] ?? '');
    $user_type = trim($_POST['user_type'] ?? '');
    $user_assignLocation = trim($_POST['user_assignLocation'] ?? ($_POST['user_assignlocation'] ?? ''));
    $customer_code = ($user_type === 'Client') ? trim($_POST['customer_code'] ?? '') : '';

    if (
        $username === '' ||
        $firstname === '' ||
        $lastname === '' ||
        $middlename === '' ||
        $useremail === '' ||
        $userpass === '' ||
        $user_type === '' ||
        $user_assignLocation === ''
    ) {
        http_response_code(422);
        echo "Error: Missing required user fields.";
        exit;
    }

    if ($user_type === 'Client' && $customer_code === '') {
        http_response_code(422);
        echo "Error: Customer code is required for Client logins.";
        exit;
    }

    $hashedPassword = password_hash($userpass, PASSWORD_DEFAULT);
    $image = null;

    if (isset($_FILES['user_image']) && (int)($_FILES['user_image']['size'] ?? 0) > 0) {
        $originalName = (string)$_FILES['user_image']['name'];
        $tempname = (string)$_FILES['user_image']['tmp_name'];
        $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($fileExt, ['png', 'jpg', 'jpeg'], true)) {
            http_response_code(422);
            echo "Error: Only PNG, JPG, and JPEG images are allowed.";
            exit;
        }

        $image = uniqid('user_', true) . '.' . $fileExt;
        $uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads';
        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $image;

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            http_response_code(500);
            echo "Error: Failed to prepare upload directory.";
            exit;
        }

        if (!move_uploaded_file($tempname, $targetPath)) {
            http_response_code(500);
            echo "Error: Failed to move uploaded file.";
            exit;
        }
    }

    try {
        // user_code + user_image are NOT NULL columns with no default. user_code
        // is a legacy field nothing reads (seed_admin uses 1) — default it to 0.
        // No-image accounts store an empty string so the NOT NULL holds.
        if ($image !== null) {
            $stmt = $conn->prepare(
                'INSERT INTO "user" (
                    user_name, user_fname, user_lname, user_mname, user_assignlocation,
                    user_email, user_pass, user_type, user_image, user_accountstat, customer_code, user_code
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $username,
                $firstname,
                $lastname,
                $middlename,
                $user_assignLocation,
                $useremail,
                $hashedPassword,
                $user_type,
                $image,
                'Pending',
                $customer_code,
                0,
            ]);
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO "user" (
                    user_name, user_fname, user_lname, user_mname, user_assignlocation,
                    user_email, user_pass, user_type, user_image, user_accountstat, customer_code, user_code
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $username,
                $firstname,
                $lastname,
                $middlename,
                $user_assignLocation,
                $useremail,
                $hashedPassword,
                $user_type,
                '',
                'Pending',
                $customer_code,
                0,
            ]);
        }

        echo "Data inserted successfully!";
    } catch (Throwable $e) {
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
}
?>
