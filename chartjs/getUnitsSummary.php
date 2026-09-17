<?php
include '../php/config/config.php'; // DB connection

// Count Truck & Genset from units table
$sqlUnits = "
    SELECT 
        SUM(CASE WHEN unit_name LIKE '%GS%' THEN 1 ELSE 0 END) AS genset,
        SUM(CASE WHEN unit_name NOT LIKE '%GS%' THEN 1 ELSE 0 END) AS truck
    FROM units
";

$resultUnits = $conn->query($sqlUnits);
$units = $resultUnits->fetch();

// Count Trailer
$sqlTrailer = "SELECT COUNT(*) AS trailer FROM trailer";
$resultTrailer = $conn->query($sqlTrailer);
$trailer = $resultTrailer->fetch();

echo json_encode([
    'truck'   => (int)$units['truck'],
    'trailer' => (int)$trailer['trailer'],
    'genset'  => (int)$units['genset']
]);
