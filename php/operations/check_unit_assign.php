<?php
include "../config/config.php";

$unit_id = $_GET['unit_id'] ?? 0;

/* 1. GET UNIT STATUS */
$unitQ = $conn->query("
  SELECT unit_status, unit_name
  FROM units
  WHERE unit_id = '$unit_id'
");

if (!$unit = ($unitQ)->fetch()) {
  echo json_encode([
    'allowed' => false,
    'message' => 'Invalid unit selected.'
  ]);
  exit;
}

/* BLOCK SHOP / RESCUE */
if (in_array($unit['unit_status'], ['Shop Unit', 'Rescue Unit', 'Good'])) {
  if($unit['unit_status'] == 'Good'){
     echo json_encode([
    'allowed' => false,
    'message' => 'This unit is currently not Available and cannot be assigned.'
  ]);
  exit;
  } else {
    echo json_encode([
      'allowed' => false,
      'message' => 'This unit is currently under '.$unit['unit_status'].' and cannot be assigned.'
    ]);
    exit;
  }
  
}

/* 2. COUNT ACTIVE TRIPS */
$tripQ = $conn->query("
  SELECT COUNT(*) AS total
  FROM trips t
  JOIN dispatch d ON t.d_id = d.d_id
  WHERE d.d_truck = '{$unit['unit_name']}'
    AND t.trip_status = 'Active'
");

$trip = ($tripQ)->fetch();

if ($trip['total'] >= 2) {
  echo json_encode([
    'allowed' => false,
    'message' => 'This unit already has 2 active trips.'
  ]);
  exit;
}

/* ALLOWED */
echo json_encode([
  'allowed' => true
]);
