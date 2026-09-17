<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'POST required';
    exit;
}

$id        = (int)($_POST['driver_id1'] ?? 0);
$rfid      = trim((string)($_POST['driver_rfid1']         ?? ''));
$idnumber  = trim((string)($_POST['driver_IdNumber1']     ?? ''));
$fname     = trim((string)($_POST['driver_fname1']        ?? ''));
$mname     = trim((string)($_POST['driver_mname1']        ?? ''));
$lname     = trim((string)($_POST['driver_lname1']        ?? ''));
$uname     = trim((string)($_POST['driver_username1']     ?? ''));
$email     = trim((string)($_POST['driver_email1']        ?? ''));
$pass      = (string)($_POST['driver_pass1']              ?? '');
$assUnit   = trim((string)($_POST['driver_assignUnit1']   ?? ''));
$assSeg    = trim((string)($_POST['driver_assignSegment1'] ?? ''));
$assBase   = trim((string)($_POST['driver_assignBase1']   ?? ''));
$status    = trim((string)($_POST['status']               ?? ''));

if ($id <= 0) {
    http_response_code(400);
    echo 'Missing driver id';
    exit;
}

// Optional image upload — only sets driver_image when a new file came in.
$image = '';
if (!empty($_FILES['driver_image1']['name'] ?? '')) {
    $image  = basename($_FILES['driver_image1']['name']);
    $tmp    = $_FILES['driver_image1']['tmp_name'];
    $folder = '../../assets/uploads/' . $image;
    @move_uploaded_file($tmp, $folder);
}

// Build the SET clause dynamically so password / image stay untouched
// when caller didn't supply one.
$sets   = [
    'driver_idnumber = ?',
    'driver_rfid = ?',
    'driver_fname = ?',
    'driver_mname = ?',
    'driver_lname = ?',
    'driver_assignunit = ?',
    'driver_assignsegment = ?',
    'driver_assignbase = ?',
    'driver_uname = ?',
    'driver_email = ?',
    'driver_account_status = ?',
];
$params = [$idnumber, $rfid, $fname, $mname, $lname, $assUnit, $assSeg, $assBase, $uname, $email, $status];

// Contact number — only touch it when the field was actually submitted, so
// callers/screens that don't include it won't blank an existing contact.
if (isset($_POST['driver_contact1'])) {
    $sets[]   = 'driver_contact = ?';
    $params[] = trim((string)$_POST['driver_contact1']);
}
if ($pass !== '') {
    $sets[]   = 'driver_pass = ?';
    $params[] = password_hash($pass, PASSWORD_DEFAULT);
}
if ($image !== '') {
    $sets[]   = 'driver_image = ?';
    $params[] = $image;
}

$params[] = $id;

try {
    $sql  = 'UPDATE drivers SET ' . implode(', ', $sets) . ' WHERE driver_id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    echo 'Driver data updated successfully!';
} catch (PDOException $e) {
    http_response_code(500);
    $msg = $e->getMessage();
    echo 'Error: ' . $msg;
    error_log('updatedriver: ' . $msg);
}
