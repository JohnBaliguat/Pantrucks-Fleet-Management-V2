<?php
include "../config/config.php";

$data = json_decode(file_get_contents("php://input"), true);

$trailer = $data["trailer"] ?? "";
$customer = $data["customer"] ?? "";
$from = $data["fromDate"] ?? "";
$to = $data["toDate"] ?? "";

/* =============================================
   1. GET ALL TRAILERS (NEVER FILTER THIS)
============================================= */
$allUnits = [];
$unitQuery = $conn->query("SELECT trailer_name FROM trailer");

while ($u = $unitQuery->fetch()) {
    $allUnits[] = $u["trailer_name"];
}


/* =============================================
   2. GET USED TRAILERS FROM dispatch (WITH FILTERS)
============================================= */
$usedList = [];

$sql = "
    SELECT DISTINCT d.d_trailer
    FROM dispatch d
    LEFT JOIN trips t ON d.d_id = t.d_id
    WHERE d.d_trailer IS NOT NULL AND d.d_trailer != ''
";

if (!empty($trailer)) {
    $sql .= " AND d.d_trailer LIKE '%$trailer%' ";
}

if (!empty($customer) && $customer != 'Select Customer') {
    $sql .= " AND d.costumer = '$customer' ";
}

if (!empty($from) && !empty($to)) {
    $sql .= " AND d.d_datetime BETWEEN '$from' AND '$to' ";
}

$res = $conn->query($sql);

while ($row = $res->fetch()) {
    $usedList[] = $row["d_trailer"];
}


/* =============================================
   3. BUILD FINAL LIST (ONLY USED + UNUSED)
============================================= */

$tableData = [];
$usedCount = 0;
$unusedCount = 0;

foreach ($allUnits as $unitName) {

    // FILTER BY TRAILER NAME
    if (!empty($trailer) && stripos($unitName, $trailer) === false) {
        continue;
    }

    if (in_array($unitName, $usedList)) {
         $usedCount++;
        continue; 
    } else {
        $status = "UNUSED";
        $unusedCount++;
    }

    // Add to table
    $tableData[] = [
        "trailer_name" => $unitName,
        "status" => $status
    ];
}


/* =============================================
   4. RETURN JSON
============================================= */
echo json_encode([
    "used" => $usedCount,
    "unused" => $unusedCount,
    "list" => $tableData
]);
?>
