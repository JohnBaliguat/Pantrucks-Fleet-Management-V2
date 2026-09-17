<?php
include "../config/config.php";

// Customers to display
$customers = [];
$customerResult = $conn->query("SELECT customer_code FROM customer ORDER BY customer_code ASC");
if ($customerResult) {
    while ($row = ($customerResult)->fetch()) {
        $code = trim((string)($row['customer_code'] ?? ''));
        if ($code !== '') {
            $customers[] = $code;
        }
    }
}

$query = "
    SELECT costumer, COUNT(*) AS count
    FROM booking
    WHERE status = 'Active'
      AND (quantity - quantity_use) > 0
      AND costumer <> ''
    GROUP BY costumer
";

$result = $conn->query($query);

// Initialize all customers to 0
$counts = array_fill_keys($customers, 0);

while ($row = ($result)->fetch()) {
    $cust = trim((string)($row['costumer'] ?? ''));
    if (array_key_exists($cust, $counts)) {
        $counts[$cust] = $row['count'];
    } elseif ($cust !== '') {
        $counts[$cust] = $row['count'];
    }
}

echo json_encode($counts);
?>
