<?php
session_start();
include '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId        = (int)$_POST['booking_id'];
    $bookingDate      = $_POST['booking_date1'];
    $booking_required = $_POST['booking_required1'];
    $booking_sn       = trim($_POST['booking_sn1'] ?? '');
    $booking_do       = trim($_POST['booking_do1'] ?? '');
    $haulingStart     = trim($_POST['haulingStart1'] ?? '');
    $lastDayStorage   = trim($_POST['lastDayStorage1'] ?? '');
    $lastDayDemurrage = trim($_POST['lastDayDemurrage1'] ?? '');
    $lastDayDetention = trim($_POST['lastDayDetention1'] ?? '');
    $costumer         = $_POST['costumer1'];
    $container_seal   = $_POST['container_seal1'];
    $container        = $_POST['container1'];
    $status           = $_POST['container_status1'];
    $segment          = $_POST['hauling_segment1'];
    $tripFrom         = $_POST['trip_from1'];
    $tripTo           = $_POST['trip_to1'];
    $returnLocation   = trim($_POST['return_location1'] ?? '');
    $quantity         = (int)$_POST['quantity1'];
    $booking_activity = $_POST['booking_activity1'];

    // Phase 2 — booking type + port fields.
    $booking_type      = $_POST['booking_type1']      ?? 'Local';
    $vessel_name       = $_POST['vessel_name1']       ?? '';
    $voyage_no         = $_POST['voyage_no1']         ?? '';
    $container_no_port = $_POST['container_no_port1'] ?? '';
    $bill_of_lading    = $_POST['bill_of_lading1']    ?? '';
    $port_location     = $_POST['port_location1']     ?? '';
    $customs_cleared   = isset($_POST['customs_cleared1']) ? 1 : 0;

    if (!in_array($booking_type, ['Local', 'Import', 'Export'], true)) {
        $booking_type = 'Local';
    }
    if ($booking_type === 'Local') {
        $vessel_name = $voyage_no = $container_no_port = $bill_of_lading = $port_location = '';
        $customs_cleared = FALSE;
    }
    if ($costumer !== 'CTH') {
        $booking_sn = $booking_do = $haulingStart = $lastDayStorage = $lastDayDemurrage = $lastDayDetention = '';
    } elseif ($booking_required === '' && $lastDayDetention !== '') {
        $booking_required = $lastDayDetention;
    }

    // Phase 11 — container_status lifecycle whitelist (mirrors addbooking.php).
    $allowedContainerStatus = [
        'Empty', 'Empty Container Pickup', 'Empty Container On Trip', 'Empty Container Delivered',
        'Loaded', 'Loaded Container Pickup', 'Loaded Container On Trip', 'Loaded Container Delivered',
    ];
    $legacyContainerStatusMap = [
        'EMPTY'  => 'Empty',
        'LOADED' => 'Loaded',
        'N/A'    => 'Empty',
    ];
    if (isset($legacyContainerStatusMap[$status])) {
        $status = $legacyContainerStatusMap[$status];
    }
    if (!in_array($status, $allowedContainerStatus, true)) {
        $status = ($costumer === 'CTH') ? 'Loaded' : 'Empty';
    }
    if ($costumer === 'CTH' && str_starts_with($status, 'Loaded') && $returnLocation === '') {
        echo json_encode(['status' => 'error', 'message' => 'Return Location is required for CTH loaded bookings.']);
        exit;
    }

    // Phase 11 — re-derive customer_segment from the (possibly changed) customer.
    $customer_segment = '';
    $segStmt = $conn->prepare("SELECT customer_segment FROM customer WHERE customer_code = ? LIMIT 1");
    if ($segStmt) {
        $segStmt->execute([$costumer]);
        $segResult = $segStmt->fetchColumn();
        if ($segResult !== false) {
            $customer_segment = $segResult ?? '';
        }
    }
    if ($customer_segment === '' && !empty($_POST['customer_segment1'])) {
        $customer_segment = trim($_POST['customer_segment1']);
    }

    $haulingType = "";
    $stmtType = $conn->prepare("SELECT hauling_type FROM hauling WHERE hauling_segment = ? LIMIT 1");
    $stmtType->execute([$segment]);
    $haulingType = (string)$stmtType->fetchColumn();
    if (empty($haulingType)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid hauling segment.']);
        exit;
    }

    $stmtCheck = $conn->prepare("SELECT quantity_use FROM booking WHERE booking_id = ? LIMIT 1");
    $stmtCheck->execute([$bookingId]);
    $quantityUse = (int)$stmtCheck->fetchColumn();
    if ($quantity < $quantityUse) {
        echo json_encode([
            'status' => 'error',
            'message' => "Quantity cannot be less than already used quantity ($quantityUse)."
        ]);
        exit;
    }

    // Stamp customs_cleared_at when the flag flips on (Export only).
    $customsClearedAtSql = "";
    if ($customs_cleared === 1) {
        $customsClearedAtSql = ", customs_cleared_at = COALESCE(customs_cleared_at, NOW())";
    } else {
        $customsClearedAtSql = ", customs_cleared_at = NULL";
    }

    $query = "UPDATE booking
              SET booking_type = ?, booking_date = ?, booking_dateRequired = ?, booking_sn = ?, booking_do = ?,
                  booking_haulingStartDate = ?, booking_LastDateStorage = ?, booking_LastDateDemurrage = ?, booking_LastDateDetention = ?,
                  costumer = ?, customer_segment = ?, container_seal = ?, container = ?, booking_activity = ?, container_status = ?,
                  hauling_segment = ?, hauling_type = ?, trip_from = ?, trip_to = ?, return_location = ?, quantity = ?,
                  vessel_name = ?, voyage_no = ?, container_no_port = ?, bill_of_lading = ?,
                  port_location = ?, customs_cleared = ?
                  $customsClearedAtSql
              WHERE booking_id = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . ($conn->errorInfo()[2] ?? '')]);
        exit;
    }

    // PostgreSQL rejects '' for DATE columns — coerce empty strings to NULL.
    $toDate = function ($v) {
        $v = is_string($v) ? trim($v) : $v;
        return ($v === '' || $v === null) ? null : $v;
    };
    // PostgreSQL also rejects '' for BOOLEAN. PDO's default PARAM_STR coerces
    // PHP false to ''. Send '0'/'1' explicitly which Postgres accepts.
    $customsClearedParam = $customs_cleared ? '1' : '0';

    try {
        // bindValue + PARAM_NULL ensures DATE NULLs and BOOLEAN '0'/'1' round-trip
        // cleanly under the Postgres PDO driver.
        $params = [
            $booking_type,
            $toDate($bookingDate),
            $toDate($booking_required),
            $booking_sn,
            $booking_do,
            $toDate($haulingStart),
            $toDate($lastDayStorage),
            $toDate($lastDayDemurrage),
            $toDate($lastDayDetention),
            $costumer,
            $customer_segment,
            $container_seal,
            $container,
            $booking_activity,
            $status,
            $segment,
            $haulingType,
            $tripFrom,
            $tripTo,
            $returnLocation,
            $quantity,
            $vessel_name,
            $voyage_no,
            $container_no_port,
            $bill_of_lading,
            $port_location,
            $customsClearedParam,
            $bookingId,
        ];
        foreach ($params as $i => $val) {
            $idx = $i + 1;
            if ($val === null) {
                $stmt->bindValue($idx, null, PDO::PARAM_NULL);
            } elseif ($idx === 21 || $idx === 28) { // quantity, booking_id
                $stmt->bindValue($idx, (int)$val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($idx, $val, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        echo json_encode(['status' => 'success', 'message' => 'Booking updated.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update booking: ' . $e->getMessage()]);
    }

}
?>
