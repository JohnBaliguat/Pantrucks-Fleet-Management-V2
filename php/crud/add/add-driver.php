<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'POST required';
    exit;
}

$driverIdNumberRaw   = trim((string)($_POST['driver_IdNumber'] ?? $_POST['driver_idnumber'] ?? ''));
$driverRfid          = trim((string)($_POST['driver_rfid'] ?? ''));
$driverFname         = trim((string)($_POST['driver_fname'] ?? ''));
$driverMname         = trim((string)($_POST['driver_mname'] ?? ''));
$driverLname         = trim((string)($_POST['driver_lname'] ?? ''));
$driverAssignUnit    = trim((string)($_POST['driver_assignUnit'] ?? $_POST['driver_assignunit'] ?? ''));
$driverAssignSegment = trim((string)($_POST['driver_assignSegment'] ?? $_POST['driver_assignsegment'] ?? ''));
$driverAssignBase    = trim((string)($_POST['driver_assignBase'] ?? $_POST['driver_assignbase'] ?? 'PTSI'));
$driverUsername      = trim((string)($_POST['driver_username'] ?? ''));
$driverEmail         = trim((string)($_POST['driver_email'] ?? ''));
$driverContact       = trim((string)($_POST['driver_contact'] ?? ''));
$driverPassword      = (string)($_POST['driver_pass'] ?? '');

if (
    $driverIdNumberRaw === '' ||
    $driverFname === '' ||
    $driverLname === '' ||
    $driverAssignUnit === '' ||
    $driverAssignSegment === ''
) {
    http_response_code(400);
    echo 'Please fill in all required fields.';
    exit;
}

if (!ctype_digit($driverIdNumberRaw)) {
    http_response_code(400);
    echo 'Driver ID Number must be numeric.';
    exit;
}

$driverIdNumber = (int)$driverIdNumberRaw;

if ($driverUsername === '') {
    $driverUsername = $driverIdNumberRaw;
}

if ($driverPassword === '') {
    $driverPassword = $driverIdNumberRaw;
}

$driverImage = '';

if (!empty($_FILES['driver_image']['name'] ?? '')) {
    $imageName = (string)$_FILES['driver_image']['name'];
    $tmpName   = (string)$_FILES['driver_image']['tmp_name'];
    $ext       = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        http_response_code(400);
        echo 'Only JPG, JPEG, and PNG files are allowed.';
        exit;
    }

    $driverImage = uniqid('driver_', true) . '.' . $ext;
    $uploadPath  = '../../assets/uploads/' . $driverImage;

    if (!move_uploaded_file($tmpName, $uploadPath)) {
        http_response_code(500);
        echo 'Failed to upload image.';
        exit;
    }
}

try {
    $stmt = $conn->prepare(
        "INSERT INTO drivers (
            driver_idnumber,
            driver_rfid,
            driver_fname,
            driver_mname,
            driver_lname,
            driver_contact,
            driver_image,
            driver_signature,
            driver_assignunit,
            driver_assignsegment,
            driver_assignbase,
            driver_status,
            driver_email,
            driver_uname,
            driver_pass,
            driver_account_status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )"
    );

    $stmt->execute([
        $driverIdNumber,
        $driverRfid,
        $driverFname,
        $driverMname,
        $driverLname,
        $driverContact,
        $driverImage,
        '',
        $driverAssignUnit,
        $driverAssignSegment,
        $driverAssignBase !== '' ? $driverAssignBase : 'PTSI',
        'Good',
        $driverEmail,
        $driverUsername,
        password_hash($driverPassword, PASSWORD_DEFAULT),
        'Active',
    ]);

    echo 'Driver added successfully!';
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
    error_log('add-driver: ' . $e->getMessage());
}
?>
