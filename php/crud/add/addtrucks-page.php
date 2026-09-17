<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Text fields
    $unit_name      = trim($_POST['unit_name']      ?? '');
    $unit_std       = trim($_POST['std']            ?? '');
    $unit_plateNo   = trim($_POST['plate_number']   ?? '');
    $unit_or        = trim($_POST['OR']             ?? '');
    $unit_cr        = trim($_POST['CR']             ?? '');
    $unit_brand     = trim($_POST['brand']          ?? '');
    $unit_modal     = trim($_POST['model']          ?? '');
    $unit_year      = trim($_POST['year']           ?? '');
    $unit_engineNo  = trim($_POST['engine_number']  ?? '');
    $unit_chassisNo = trim($_POST['chassis_number'] ?? '');
    $unit_fuelType  = trim($_POST['fuel_type']      ?? '');
    $unit_capacity  = trim($_POST['capacity']       ?? '');
    $unit_remarks   = trim($_POST['remarks']        ?? '');

    if ($unit_name === '') {
        echo "Error: Unit Name is required.";
        exit;
    }

    // Duplicate check — prepared so values with quotes can't break/inject.
    // Blank OR/CR/plate shouldn't count as a duplicate of other blanks.
    $check = $conn->prepare(
        "SELECT unit_id FROM units
          WHERE unit_name = ?
             OR (? <> '' AND unit_plate = ?)
             OR (? <> '' AND unit_or    = ?)
             OR (? <> '' AND unit_cr    = ?)
          LIMIT 1"
    );
    $check->execute([
        $unit_name,
        $unit_plateNo, $unit_plateNo,
        $unit_or, $unit_or,
        $unit_cr, $unit_cr,
    ]);
    if ($check->fetch()) {
        echo "Error: Duplicate record found (Unit Name, Plate Number, OR, or CR already exists).";
        exit;
    }

    // Upload directory
    $uploadDir = "truckphoto/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Sanitize unit name for filenames
    $safeUnitName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $unit_name);

    // Handle image uploads (optional)
    $frontView = uploadImage('front_view', $uploadDir, $safeUnitName . "_front");
    $backView  = uploadImage('back_view', $uploadDir, $safeUnitName . "_back");
    $leftView  = uploadImage('left_view', $uploadDir, $safeUnitName . "_left");
    $rightView = uploadImage('right_view', $uploadDir, $safeUnitName . "_right");

    $defaultImg = 'noimage.png'; // must exist in truckphoto/
    $frontView = $frontView ?: $defaultImg;
    $backView  = $backView ?: $defaultImg;
    $leftView  = $leftView ?: $defaultImg;
    $rightView = $rightView ?: $defaultImg;

    // Insert.
    //  * driver_id is NOT NULL with no default — omitting it made every insert
    //    fail with a not-null violation. 0 = unassigned (the app's convention).
    //  * unit_status must be 'Good' or the new truck is not dispatchable
    //    (the assign guards require status 'good').
    //  * unit_type 'truck' — this page adds trucks.
    $sql = "INSERT INTO units (
                unit_name, unit_type, unit_std, unit_plate, unit_or, unit_cr,
                unit_brand, unit_modal, unit_year, unit_engineno, unit_chassisno,
                unit_fueltype, unit_capacity, unit_remarks,
                unit_frontview, unit_leftview, unit_rightview, unit_backview,
                driver_id, unit_status
            ) VALUES (
                ?, 'truck', ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                0, 'Good'
            )";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $unit_name, $unit_std, $unit_plateNo, $unit_or, $unit_cr,
            $unit_brand, $unit_modal, $unit_year, $unit_engineNo, $unit_chassisNo,
            $unit_fuelType, $unit_capacity, $unit_remarks,
            $frontView, $leftView, $rightView, $backView,
        ]);
        echo "Truck unit added successfully!";
    } catch (Throwable $e) {
        echo "Error: " . $e->getMessage();
    }
}

// Image upload helper with custom filename (optional)
function uploadImage($fieldName, $uploadDir, $newBaseName) {
    if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['size'] > 0) {
        $fileName = basename($_FILES[$fieldName]['name']);
        $fileTmp  = $_FILES[$fieldName]['tmp_name'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Allow only certain extensions
        if (!in_array($fileExt, ['png', 'jpg', 'jpeg'])) {
            return null;
        }

        $newName = $newBaseName . "." . $fileExt;
        if (move_uploaded_file($fileTmp, $uploadDir . $newName)) {
            return $newName;
        }
        return null;
    }
    return null; // No file uploaded
}
?>
