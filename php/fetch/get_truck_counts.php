<?php
include "../config/config.php";

$data = json_decode(file_get_contents("php://input"), true);

$truck = $data["truck"] ?? "";
$customer = $data["customer"] ?? "";
$from = $data["fromDate"] ?? "";
$to = $data["toDate"] ?? "";

/* ======================================================
   1. GET ALL TRUCKS (NEVER FILTER THIS)
====================================================== */
$allUnits = [];
$unitQuery = $conn->query("SELECT unit_name FROM units WHERE unit_name NOT LIKE 'GS%'");

while ($u = $unitQuery->fetch()) {
    $allUnits[] = $u["unit_name"];
}


/* ======================================================
   2. GET USED TRUCKS FROM dispatch (WITH FILTERS)
====================================================== */
$usedList = [];

$sql = "
    SELECT DISTINCT d.d_truck
    FROM dispatch d
    LEFT JOIN trips t ON d.d_id = t.d_id
    WHERE d.d_truck IS NOT NULL AND d.d_truck != ''
";

if (!empty($truck)) {
    $sql .= " AND d.d_truck LIKE '%$truck%' ";
}

if (!empty($customer) && $customer != 'Select Customer') {
    $sql .= " AND d.costumer = '$customer' ";
}

if (!empty($from) && !empty($to)) {
    $sql .= " AND d.d_datetime BETWEEN '$from' AND '$to' ";
}

$res = $conn->query($sql);

while ($row = $res->fetch()) {
    $usedList[] = $row["d_truck"];
}


/* ======================================================
   3. GET SHOP TRUCKS (WITH DATE FILTER)
====================================================== */
$shopList = [];

$shopSql = "
    SELECT s_unit
    FROM shop_unit
    WHERE 1
";

if (!empty($from) && !empty($to)) {
    $shopSql .= "
        AND (
            (s_startDateTime <= '$to')
            AND (s_endDateTime >= '$from' OR s_endDateTime IS NULL)
        )
    ";
}

$shopRes = $conn->query($shopSql);

while ($s = $shopRes->fetch()) {
    $shopList[] = $s["s_unit"];
}


/* ======================================================
   4. BUILD FINAL TABLE STATUS LIST (APPLY FILTERS)
====================================================== */
$tableData = [];
$usedCount = 0;
$inShopCount = 0;
$unusedCount = 0;

foreach ($allUnits as $unitName) {

    // --- FILTER BY TRUCK NAME ---
    if (!empty($truck) && stripos($unitName, $truck) === false) {
        continue; // skip this truck
    }

   // Determine status
    if (in_array($unitName, $usedList)) {
        
        // COUNT BUT DO NOT DISPLAY
        $usedCount++;
        continue; // skip used trucks (DO NOT ADD TO TABLE)

    }
    else if (in_array($unitName, $shopList)) {

        $status = "IN SHOP";
        $inShopCount++;

    }
    else {

        $status = "UNUSED";
        $unusedCount++;

    }

    // Now table only gets UNUSED + IN SHOP
    $tableData[] = [
        "unit_name" => $unitName,
        "status" => $status
    ];
}


/* ======================================================
   5. RETURN JSON
====================================================== */
echo json_encode([
    "used" => $usedCount,
    "inshop" => $inShopCount,
    "unused" => $unusedCount,
    "list" => $tableData
]);
?>
