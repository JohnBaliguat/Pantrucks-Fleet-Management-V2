<?php
include '../php/config/config.php';

$whereShop = [];
$whereRescue = [];
$params = [];
$types = '';

if (!empty($_GET['fromDate'])) {
    $whereShop[]   = "DATE(s_startDateTime) >= ?";
    $whereRescue[] = "DATE(r_startDateTime) >= ?";
    $params[] = $_GET['fromDate'];
    $types .= 's';
}

if (!empty($_GET['toDate'])) {
    $whereShop[]   = "DATE(s_startDateTime) <= ?";
    $whereRescue[] = "DATE(r_startDateTime) <= ?";
    $params[] = $_GET['toDate'];
    $types .= 's';
}

$whereShopSQL = $whereShop ? 'WHERE ' . implode(' AND ', $whereShop) : '';
$whereRescueSQL = $whereRescue ? 'WHERE ' . implode(' AND ', $whereRescue) : '';

$data = [];

/* SHOP UNIT */
$sqlShop = "
    SELECT DATE(s_startDateTime) AS d, COUNT(*) AS total
    FROM shop_unit
    $whereShopSQL
    GROUP BY DATE(s_startDateTime)
";

$stmtShop = $conn->prepare($sqlShop);
$stmtShop->execute($params);
$resShop = $stmtShop;

while ($row = $resShop->fetch()) {
    $date = $row['d'];
    if (!isset($data[$date])) {
        $data[$date] = ['shop' => 0, 'rescue' => 0];
    }
    $data[$date]['shop'] = (int)$row['total'];
}

/* RESCUE UNIT */
$sqlRescue = "
    SELECT DATE(r_startDateTime) AS d, COUNT(*) AS total
    FROM rescue_unit
    $whereRescueSQL
    GROUP BY DATE(r_startDateTime)
";

$stmtRescue = $conn->prepare($sqlRescue);
$stmtRescue->execute($params);
$resRescue = $stmtRescue;

while ($row = $resRescue->fetch()) {
    $date = $row['d'];
    if (!isset($data[$date])) {
        $data[$date] = ['shop' => 0, 'rescue' => 0];
    }
    $data[$date]['rescue'] = (int)$row['total'];
}

/* SORT + FORMAT */
ksort($data);

$dates = [];
$shopCounts = [];
$rescueCounts = [];

foreach ($data as $date => $counts) {
    $dates[] = date('d M', strtotime($date)); // 06 Feb
    $shopCounts[] = $counts['shop'];
    $rescueCounts[] = $counts['rescue'];
}

echo json_encode([
    'dates'  => $dates,
    'shop'   => $shopCounts,
    'rescue' => $rescueCounts
]);
