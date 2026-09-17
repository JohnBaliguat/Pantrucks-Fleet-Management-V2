<?php
include "../../config/config.php";
require_once __DIR__ . '/../../lib/container_lifecycle.php';   // pt_customer_is_import()

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $booking_date = trim($_POST['booking_date'] ?? '');
    $booking_required = trim($_POST['booking_required'] ?? '');
    $booking_sn = trim($_POST['booking_sn'] ?? '');
    $booking_do = trim($_POST['booking_do'] ?? '');
    $haulingStart = trim($_POST['haulingStart'] ?? '');
    $lastDayStorage = trim($_POST['lastDayStorage'] ?? '');
    $lastDayDemurrage = trim($_POST['lastDayDemurrage'] ?? '');
    $lastDayDetention = trim($_POST['lastDayDetention'] ?? '');
    $costumer = trim($_POST['costumer'] ?? '');
    $container_seal = $_POST['container_seal'] ?? [];
    $container = $_POST['container'] ?? [];
    $container_status = $_POST['container_status'] ?? [];
    $hauling_segment = $_POST['hauling_segment'] ?? [];
    $trip_from = $_POST['trip_from'] ?? [];
    $trip_to = $_POST['trip_to'] ?? [];
    $return_location = $_POST['return_location'] ?? [];
    $booking_activity = $_POST['booking_activity'] ?? [];
    $quantity = $_POST['quantity'] ?? [];
    // CTH can have both import and export shipments. Import remains the default.
    $cthTradeType = trim((string)($_POST['cth_trade_type'] ?? 'Import'));
    if (!in_array($cthTradeType, ['Import', 'Export'], true)) {
        $cthTradeType = 'Import';
    }

    if ($booking_date === '' || $costumer === '') {
        echo json_encode(["status" => "error", "message" => "Booking date and customer are required."]);
        exit;
    }

    if (!is_array($container) || count($container) === 0) {
        echo json_encode(["status" => "error", "message" => "At least one booking row is required."]);
        exit;
    }

    $rowCount = count($container);
    $rowFields = [
        'container_seal' => $container_seal,
        'container_status' => $container_status,
        'hauling_segment' => $hauling_segment,
        'trip_from' => $trip_from,
        'trip_to' => $trip_to,
        'return_location' => $return_location,
        'booking_activity' => $booking_activity,
        'quantity' => $quantity,
    ];
    foreach ($rowFields as $field => $values) {
        if (!is_array($values) || count($values) !== $rowCount) {
            echo json_encode(["status" => "error", "message" => "Invalid multi-booking payload for {$field}."]);
            exit;
        }
    }

    if ($booking_required === '') {
        $booking_required = $lastDayDetention;
    }

    if ($costumer !== 'CTH') {
        $booking_sn = $booking_do = '';
        $haulingStart = $lastDayStorage = $lastDayDemurrage = $lastDayDetention = '';
    }

    $nullIfBlank = static fn($v) => ($v === '' || $v === null) ? null : $v;
    $booking_required = $nullIfBlank($booking_required);
    $haulingStart = $nullIfBlank($haulingStart);
    $lastDayStorage = $nullIfBlank($lastDayStorage);
    $lastDayDemurrage = $nullIfBlank($lastDayDemurrage);
    $lastDayDetention = $nullIfBlank($lastDayDetention);

    $booking_type = $costumer === 'CTH' ? $cthTradeType : 'Local';
    $customer_segment = '';
    $segStmt = $conn->prepare("SELECT customer_segment FROM customer WHERE customer_code = ? LIMIT 1");
    if ($segStmt) {
        $segStmt->execute([$costumer]);
        $segResult = $segStmt->fetchColumn();
        if ($segResult !== false) {
            $customer_segment = trim((string)$segResult);
        }
    }

    // --- get hauling_type for each segment ---
    function getHaulingType($conn, $segment) {
        $stmt = $conn->prepare("SELECT hauling_type FROM hauling WHERE hauling_segment = ? LIMIT 1");
        $stmt->execute([$segment]);
        $hauling_type_result = $stmt->fetchColumn();
        $type = '';
        if ($hauling_type_result !== false) {
            $type = $hauling_type_result;
        }
        return $type;
    }

    // Phase 14.1 — booking_no per row uses Customer-BN-From-To.
    require_once __DIR__ . '/../../lib/booking_no.php';

    $success = true;
    $messages = [];
    $allowedContainerStatus = [
        'Empty', 'Empty Container Pickup', 'Empty Container On Trip', 'Empty Container Delivered',
        'Loaded', 'Loaded Container Pickup', 'Loaded Container On Trip', 'Loaded Container Delivered',
    ];
    $legacyContainerStatusMap = [
        'EMPTY' => 'Empty',
        'LOADED' => 'Loaded',
        'N/A' => 'Empty',
    ];

    if ($costumer === 'CTH') {
        foreach ([
            ($cthTradeType === 'Export' ? 'ATW' : 'Shipment Number') => $booking_sn,
            'DO' => $booking_do,
            'Hauling Start' => $haulingStart,
            'Last Day of Storage' => $lastDayStorage,
            'Last Day of Demurrage' => $lastDayDemurrage,
            'Last Day of Detention' => $lastDayDetention,
        ] as $label => $value) {
            if ($value === null || trim((string)$value) === '') {
                echo json_encode(["status" => "error", "message" => "{$label} is required for CTH bookings."]);
                exit;
            }
        }
    } elseif ($booking_required === null) {
        echo json_encode(["status" => "error", "message" => "Date Required is required for non-CTH bookings."]);
        exit;
    }

    $stmt2 = $conn->prepare("
        INSERT INTO booking (
            booking_no, booking_type, booking_sn, booking_do, booking_date, booking_daterequired,
            booking_haulingstartdate, booking_lastdatestorage, booking_lastdatedemurrage, booking_lastdatedetention,
            costumer, customer_segment, container_seal, container, booking_activity, container_status,
            hauling_segment, hauling_type, trip_from, trip_to, return_location,
            quantity, quantity_use, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stage_log = $conn->prepare(
        "INSERT INTO workflow_event (booking_no, stage, actor_role, notes)
         VALUES (?, 'order_created', 'system', 'Booking created via multi-booking form')"
    );

    try {
        $conn->beginTransaction();

        // --- loop through all containers ---
        for ($i = 0; $i < $rowCount; $i++) {
            $rowContainer = strtoupper(trim((string)($container[$i] ?? '')));
            $rowSeal = trim((string)($container_seal[$i] ?? ''));
            $rowActivity = trim((string)($booking_activity[$i] ?? ''));
            $rowSegment = trim((string)($hauling_segment[$i] ?? ''));
            $rowTripFrom = trim((string)($trip_from[$i] ?? ''));
            $rowTripTo = trim((string)($trip_to[$i] ?? ''));
            $rowReturnLocation = trim((string)($return_location[$i] ?? ''));
            $rowQuantity = (int)($quantity[$i] ?? 1);

            if ($rowContainer === '' || $rowSeal === '' || $rowActivity === '' || $rowSegment === '' || $rowTripFrom === '' || $rowTripTo === '') {
                throw new RuntimeException("Please complete all required fields for row " . ($i + 1) . ".");
            }
            if ($rowQuantity < 1) {
                throw new RuntimeException("Quantity must be at least 1 for row " . ($i + 1) . ".");
            }

            $booking_no = bn_generate($conn, $costumer, $rowTripFrom, $rowTripTo);
            $hauling_type = getHaulingType($conn, $rowSegment);
            $rowContainerStatus = trim((string)($container_status[$i] ?? ''));
            if (isset($legacyContainerStatusMap[$rowContainerStatus])) {
                $rowContainerStatus = $legacyContainerStatusMap[$rowContainerStatus];
            }
            // CTH's selected shipment type controls its initial container lane.
            $rowIsImport = $costumer === 'CTH'
                ? $cthTradeType === 'Import'
                : pt_customer_is_import($conn, $costumer);
            if (!in_array($rowContainerStatus, $allowedContainerStatus, true)) {
                $rowContainerStatus = $rowIsImport ? 'Loaded' : 'Empty';
            }
            if ($rowIsImport && !str_starts_with($rowContainerStatus, 'Loaded')) {
                $rowContainerStatus = 'Loaded';
            }
            if ($rowIsImport && $rowReturnLocation === '') {
                throw new RuntimeException("Return Location is required for import row " . ($i + 1) . ".");
            }

        $quantity_use = 0;
        $status = 'Active';

            $stmt2->execute([$booking_no,
                $booking_type,
                $booking_sn,
                $booking_do,
                $booking_date,
                $booking_required,
                $haulingStart,
                $lastDayStorage,
                $lastDayDemurrage,
                $lastDayDetention,
                $costumer,
                $customer_segment,
                $rowSeal,
                $rowContainer,
                $rowActivity,
                $rowContainerStatus,
                $rowSegment,
                $hauling_type,
                $rowTripFrom,
                $rowTripTo,
                $rowReturnLocation,
                $rowQuantity,
                $quantity_use,
                $status]);

            if ($stage_log) {
                $stage_log->execute([$booking_no]);
            }

            $messages[] = "Booking $booking_no saved successfully.";
        }

        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $success = false;
        $messages[] = $e->getMessage();
    }

    if ($success && !empty($messages)) {
        echo json_encode(["status" => "success", "message" => implode("<br>", $messages)]);
    } else {
        echo json_encode(["status" => "error", "message" => implode("<br>", $messages)]);
    }
}
?>
