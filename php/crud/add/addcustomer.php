<?php
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_code    = pt_pg_escape($conn, $_POST['customer_code']);
    $customer_name    = pt_pg_escape($conn, $_POST['customer_name']);
    // Phase 11 — segment is optional at creation; client portal uses it
    // to auto-fill bookings and segment-monitoring pages filter by it.
    $customer_segment = pt_pg_escape($conn, trim($_POST['customer_segment'] ?? ''));
    // Import → booking starts Loaded (+ empty return); Export (default) starts Empty.
    $trade_type = (strcasecmp(trim($_POST['trade_type'] ?? ''), 'Import') === 0) ? 'Import' : 'Export';

    if (empty($customer_code) || empty($customer_name)) {
        echo "Please fill all fields.";
        exit;
    }

    // Prevent duplicate customer_code
    $check = $conn->query("SELECT * FROM customer WHERE customer_code = '$customer_code'");
    if (($check)->rowCount() > 0) {
        echo "Customer Code already exists.";
        exit;
    }

    $sql = "INSERT INTO customer (customer_code, customer_name, customer_segment, trade_type)
            VALUES ('$customer_code', '$customer_name', '$customer_segment', '$trade_type')";

    if ($conn->query($sql)) {
        echo "Customer added successfully.";
    } else {
        echo "Error: " . (($conn->errorInfo()[2]) ?? "");
    }
}
?>
