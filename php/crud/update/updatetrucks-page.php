<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $unit_id       = intval($_POST['unit_id']); // Make sure this is passed in your form hidden field
    $unit_name     = trim($_POST['unit_name']);
    $unit_std      = trim($_POST['std']);
    $unit_plateNo  = trim($_POST['plate_number']);
    $unit_or       = trim($_POST['OR']);
    $unit_cr       = trim($_POST['CR']);
    $unit_brand    = trim($_POST['brand']);
    $unit_modal    = trim($_POST['model']);
    $unit_year     = trim($_POST['year']);
    $unit_engineNo = trim($_POST['engine_number']);
    $unit_chassisNo= trim($_POST['chassis_number']);
    $unit_fuelType = trim($_POST['fuel_type']);
    $unit_capacity = trim($_POST['capacity']);
    $unit_remarks  = trim($_POST['remarks']);

    // Upload directory
    $uploadDir = "truckphoto/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Get current data to know old image names
    $oldDataQuery = $conn->query("SELECT unit_frontView, unit_backView, unit_leftView, unit_rightView FROM units WHERE unit_id = $unit_id");
    $oldData = ($oldDataQuery)->fetch();

    // Sanitize filename
    $safeUnitName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $unit_name);

    // Handle uploads and delete old if new file is uploaded
    $frontView = handleImageUpdate('front_view', $uploadDir, $safeUnitName . "_front", $oldData['unit_frontview']);
    $backView  = handleImageUpdate('back_view', $uploadDir, $safeUnitName . "_back",  $oldData['unit_backView']);
    $leftView  = handleImageUpdate('left_view', $uploadDir, $safeUnitName . "_left",  $oldData['unit_leftview']);
    $rightView = handleImageUpdate('right_view', $uploadDir, $safeUnitName . "_right", $oldData['unit_rightview']);

    // If no new image uploaded, keep the old one
    $frontView = $frontView ?: $oldData['unit_frontview'];
    $backView  = $backView  ?: $oldData['unit_backView'];
    $leftView  = $leftView  ?: $oldData['unit_leftview'];
    $rightView = $rightView ?: $oldData['unit_rightview'];

    // Update query
    $sql = "UPDATE units SET 
                unit_name = '$unit_name',
                unit_std = '$unit_std',
                unit_plate = '$unit_plateNo',
                unit_or = '$unit_or',
                unit_cr = '$unit_cr',
                unit_brand = '$unit_brand',
                unit_modal = '$unit_modal',
                unit_year = '$unit_year',
                unit_engineNo = '$unit_engineNo',
                unit_chassisNo = '$unit_chassisNo',
                unit_fuelType = '$unit_fuelType',
                unit_capacity = '$unit_capacity',
                unit_remarks = '$unit_remarks',
                unit_frontView = " . dbValue($frontView) . ",
                unit_leftView  = " . dbValue($leftView) . ",
                unit_rightView = " . dbValue($rightView) . ",
                unit_backView  = " . dbValue($backView) . "
            WHERE unit_id = $unit_id";

    if ($conn->query($sql)) {
        echo "Truck unit updated successfully!";
    } else {
        echo "Error: " . (($conn->errorInfo()[2]) ?? "");
    }
}

// Function to handle upload and delete old image
function handleImageUpdate($fieldName, $uploadDir, $newBaseName, $oldFile) {
    if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['size'] > 0) {
        $fileTmp  = $_FILES[$fieldName]['tmp_name'];
        $fileName = basename($_FILES[$fieldName]['name']);
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExt, ['png', 'jpg', 'jpeg'])) {
            return null;
        }

        // Delete old file if it exists
        if (!empty($oldFile) && file_exists($uploadDir . $oldFile)) {
            unlink($uploadDir . $oldFile);
        }

        $newName = $newBaseName . "." . $fileExt;
        if (move_uploaded_file($fileTmp, $uploadDir . $newName)) {
            return $newName;
        }
    }
    return null;
}

// Helper for NULL-safe SQL values
function dbValue($val) {
    return ($val === null || $val === "") ? "NULL" : "'" . addslashes($val) . "'";
}
?>
