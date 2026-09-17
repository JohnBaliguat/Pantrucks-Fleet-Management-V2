<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id      = pt_pg_escape($conn, $_POST['customer_id']);
    $code    = pt_pg_escape($conn, $_POST['customer_code']);
    $name    = pt_pg_escape($conn, $_POST['customer_name']);
    $segment = pt_pg_escape($conn, trim($_POST['customer_segment'] ?? ''));
    $trade_type = (strcasecmp(trim($_POST['trade_type'] ?? ''), 'Import') === 0) ? 'Import' : 'Export';

    if (empty($id) || empty($code) || empty($name)) {
        echo "Please fill all fields.";
        exit;
    }

    // Prevent duplicate customer_code except for current record
    $check = $conn->query("SELECT * FROM customer WHERE customer_code = '$code' AND customer_id != '$id'");
    if (($check)->rowCount() > 0) {
        echo "Customer Code already exists.";
        exit;
    }

    $sql = "UPDATE customer
            SET customer_code = '$code', customer_name = '$name', customer_segment = '$segment', trade_type = '$trade_type'
            WHERE customer_id = '$id'";

    if ($conn->query($sql)) {
        echo "Customer updated successfully.";
    } else {
        echo "Error: " . (($conn->errorInfo()[2]) ?? "");
    }
}
?>
