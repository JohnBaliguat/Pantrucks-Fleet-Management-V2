<?php
include "../config/config.php";

$query = "
    SELECT unit_status, COUNT(*) as count 
    FROM units 
    WHERE unit_name NOT LIKE 'GS%' 
    GROUP BY unit_status
";

$result = $conn->query($query);

$counts = [
    'good' => 0,
    'dispatch' => 0,
    'shop unit' => 0,
    'rescue' => 0
];

$totalCount = 0;

while ($row = ($result)->fetch()) {
    $status = strtolower($row['unit_status']);
    $count  = (int)$row['count'];

    $totalCount += $count;

    if (isset($counts[$status])) {
        $counts[$status] = $count;
    }
}

echo json_encode([
    'total' => $totalCount,
    'good' => $counts['good'],
    'dispatch' => $counts['dispatch'],
    'rescue' => $counts['rescue'],
    'shop' => $counts['shop unit']
]);
