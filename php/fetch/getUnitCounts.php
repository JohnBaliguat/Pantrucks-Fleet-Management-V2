<?php
include "../config/config.php";

$response = [];

/* ============================
   COUNT TRUCK & GENSET
============================ */
$sql_units = "
SELECT 
    SUM(CASE WHEN unit_name LIKE 'GS%' THEN 1 ELSE 0 END) AS genset_count,
    SUM(CASE WHEN unit_name NOT LIKE 'GS%' THEN 1 ELSE 0 END) AS truck_count
FROM units
";

$result_units = $conn->query($sql_units);
$row_units = $result_units->fetch();

/* ============================
   COUNT TRAILER
============================ */
$sql_trailer = "SELECT COUNT(trailer_name) AS trailer_count FROM trailer";
$result_trailer = $conn->query($sql_trailer);
$row_trailer = $result_trailer->fetch();

$response = [
    'truck'   => $row_units['truck_count'] ?? 0,
    'genset'  => $row_units['genset_count'] ?? 0,
    'trailer' => $row_trailer['trailer_count'] ?? 0
];

echo json_encode($response);
?>
