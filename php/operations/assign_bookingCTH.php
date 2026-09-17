<?php
include "../config/config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $broker   = trim($_POST['broker']);
    $EIROut   = trim($_POST['EIROut']);
    $booking_no   = trim($_POST['booking_no']);
    $booking_sn   = trim($_POST['booking_sn']);
    $booking_do   = trim($_POST['booking_do']);
    $trip_receipt = trim($_POST['trip_receipt']);
    $ecs          = trim($_POST['ecs']);
    $driver       = trim($_POST['driver']);
    $driver_id    = trim($_POST['driver_id']);
    $genset       = trim($_POST['genset']);
    $trailer      = trim($_POST['trailer']);
    $dispatcher   = trim($_POST['userName']);
    $dispatchHub  = trim($_POST['assignLocation']);
    $unitName     = trim($_POST['unitName']);

    $conn->beginTransaction();

    try {
        // 0️⃣ Check if booking still has available quantity
        $checkQty = "SELECT quantity, quantity_use, costumer FROM booking WHERE booking_no = ?";
        $stmt = $conn->prepare($checkQty);
        $stmt->execute([$booking_no]);
        $result = $stmt;
        $bookingQty = $result->fetch();
if (!$bookingQty) {
            echo json_encode(["status" => "error", "message" => "Booking not found!"]);
            exit;
        }

        $costumer = $bookingQty['costumer'];

        if (pt_driver_has_active_violation($conn, (int)$driver_id)) {
            echo json_encode(["status" => "error", "message" => pt_driver_violation_block_message()]);
            exit;
        }

        if ((int)$bookingQty['quantity_use'] >= (int)$bookingQty['quantity']) {
            echo json_encode(["status" => "error", "message" => "Booking is already fully assigned!"]);
            exit;
        }

        // 0.5️⃣ Validate Unit (Truck) registration + status + assignment
        if (!empty($unitName)) {
            $checkUnit = "SELECT unit_status, maintenance_blocked, dispatch_blocked, unit_assign, driver_id FROM units WHERE unit_name = ?";
            $stmt = $conn->prepare($checkUnit);
            $stmt->execute([$unitName]);
            $result = $stmt;
            $unitRow = $result->fetch();
if (!$unitRow) {
                echo json_encode(["status" => "error", "message" => "Truck is not registered!"]);
                exit;
            }

            if ((int)($unitRow['maintenance_blocked'] ?? 0) === 1) {
                echo json_encode(["status" => "error", "message" => "Truck is blocked for maintenance!"]);
                exit;
            }

            if ((int)($unitRow['dispatch_blocked'] ?? 0) === 1) {
                echo json_encode(["status" => "error", "message" => "Truck is blocked by Dispatch Admin and cannot be assigned!"]);
                exit;
            }

            // 🚫 Status check
            if (in_array($unitRow['unit_status'], ['Rescue', 'Shop Unit'])) {
                echo json_encode(["status" => "error", "message" => "Truck is unavailable! (".$unitRow['unit_status'].")"]);
                exit;
            }

            // 🚫 Assignment check
            if (!empty($unitRow['unit_assign']) && (int)$unitRow['driver_id'] !== 0) {
                if ($unitRow['unit_assign'] !== $driver || (int)$unitRow['driver_id'] !== (int)$driver_id) {
                    echo json_encode(["status" => "error", "message" => "Truck is already assigned to another driver!"]);
                    exit;
                }
            }
        }

        // 0.5️⃣ Validate Unit (Genset) registration + status + assignment
        if (!empty($genset)) {
            $checkGenset = "SELECT unit_status, maintenance_blocked, unit_assign, driver_id FROM units WHERE unit_name = ?";
            $stmt = $conn->prepare($checkGenset);
            $stmt->execute([$genset]);
            $result = $stmt;
            $gensetRow = $result->fetch();
if (!$gensetRow) {
                echo json_encode(["status" => "error", "message" => "Genset is not registered!"]);
                exit;
            }

            if ((int)($gensetRow['maintenance_blocked'] ?? 0) === 1) {
                echo json_encode(["status" => "error", "message" => "Genset is blocked for maintenance!"]);
                exit;
            }

            if (in_array($gensetRow['unit_status'], ['Rescue', 'Shop Unit'])) {
                echo json_encode(["status" => "error", "message" => "Genset is unavailable! (".$gensetRow['unit_status'].")"]);
                exit;
            }

            if (!empty($gensetRow['unit_assign']) && (int)$gensetRow['driver_id'] !== 0) {
                if ($gensetRow['unit_assign'] !== $driver || (int)$gensetRow['driver_id'] !== (int)$driver_id) {
                    echo json_encode(["status" => "error", "message" => "Genset is already assigned to another driver!"]);
                    exit;
                }
            }
        }

        // 0.6️⃣ Validate Trailer registration + status + assignment
        if (!empty($trailer)) {
            $checkTrailer = "SELECT trailer_status, maintenance_blocked, trailer_assignto, driver_id FROM trailer WHERE trailer_name = ?";
            $stmt = $conn->prepare($checkTrailer);
            $stmt->execute([$trailer]);
            $result = $stmt;
            $trailerRow = $result->fetch();
if (!$trailerRow) {
                echo json_encode(["status" => "error", "message" => "Trailer is not registered!"]);
                exit;
            }

            if ((int)($trailerRow['maintenance_blocked'] ?? 0) === 1) {
                echo json_encode(["status" => "error", "message" => "Trailer is blocked for maintenance!"]);
                exit;
            }

            if (in_array($trailerRow['trailer_status'], ['Rescue', 'Shop Unit'])) {
                echo json_encode(["status" => "error", "message" => "Trailer is unavailable! (".$trailerRow['trailer_status'].")"]);
                exit;
            }

            if (!empty($trailerRow['trailer_assignto']) && (int)$trailerRow['driver_id'] !== 0) {
                if ($trailerRow['trailer_assignto'] !== $driver || (int)$trailerRow['driver_id'] !== (int)$driver_id) {
                    echo json_encode(["status" => "error", "message" => "Trailer is already assigned to another driver!"]);
                    exit;
                }
            }
        }

        // 0.7️⃣ Check if driver already has 2 active trips
        $checkActiveTrips = "SELECT COUNT(*) AS active_count
            FROM dispatch d
            WHERE d.driver_id = ?
              AND d.workflow_stage IN ('dispatcher_assigned','reassigned','driver_accepted','gate_cleared','en_route','pending_verification','delivered')
        ";
        $stmt = $conn->prepare($checkActiveTrips);
        $stmt->execute([$driver_id]);
        $result = $stmt;
        $activeData = $result->fetch();
if ((int)$activeData['active_count'] == 2) {
            echo json_encode(["status" => "error", "message" => "Driver already has 2 active trips!"]);
            exit;
        }

        $errors = [];

        // Only check if both receipt and ECS are given
        if (!empty($trip_receipt) && !empty($ecs)) {

            // === Check Trip Receipt for LOADED trips ===
            $checkReceiptLoaded = "
                SELECT 1 
                FROM dispatch d
                INNER JOIN trips t ON d.d_id = t.d_id
                WHERE d.d_tripreceipt = ?
                AND t.trip_containerstat = 'LOADED'
                LIMIT 1
            ";
            $stmt = $conn->prepare($checkReceiptLoaded);
            $stmt->execute([$trip_receipt]);
if ($stmt->rowCount() > 0) {
                $errors[] = "Trip Receipt already exists in a LOADED trip!";
            }
// === Check Trip Receipt for EMPTY trips ===
            $checkReceiptEmpty = "
                SELECT 1 
                FROM dispatch d
                INNER JOIN trips t ON d.d_id = t.d_id
                WHERE d.d_tripreceipt = ?
                AND t.trip_containerstat = 'EMPTY'
                LIMIT 1
            ";
            $stmt = $conn->prepare($checkReceiptEmpty);
            $stmt->execute([$trip_receipt]);
if ($stmt->rowCount() > 0) {
                $errors[] = "Trip Receipt already exists in an EMPTY trip!";
            }
// === Check ECS for LOADED trips ===
            $checkEcsLoaded = "
                SELECT 1 
                FROM dispatch d
                INNER JOIN trips t ON d.d_id = t.d_id
                WHERE d.d_ecs = ?
                AND t.trip_containerstat = 'LOADED'
                LIMIT 1
            ";
            $stmt = $conn->prepare($checkEcsLoaded);
            $stmt->execute([$ecs]);
if ($stmt->rowCount() > 0) {
                $errors[] = "ECS already exists in a LOADED trip!";
            }
// === Check ECS for EMPTY trips ===
            $checkEcsEmpty = "
                SELECT 1 
                FROM dispatch d
                INNER JOIN trips t ON d.d_id = t.d_id
                WHERE d.d_ecs = ?
                AND t.trip_containerstat = 'EMPTY'
                LIMIT 1
            ";
            $stmt = $conn->prepare($checkEcsEmpty);
            $stmt->execute([$ecs]);
if ($stmt->rowCount() > 0) {
                $errors[] = "ECS already exists in an EMPTY trip!";
            }
// If any error found, return JSON
            if (!empty($errors)) {
                echo json_encode([
                    "status" => "error",
                    "message" => implode(" ", $errors)
                ]);
                exit;
            }
        }

        date_default_timezone_set("Asia/Manila");
        $now = date("Y-m-d H:i:s");

        // 2️⃣ Insert into dispatch
        $insertDispatch = "INSERT INTO dispatch
            (booking_no, booking_sn, booking_do, cth_broker, cth_eirout, d_datetime, d_dispatcher, d_dispatchhub, d_drivername, driver_id, d_truck, d_trailer, d_genset, d_tripreceipt, d_ecs, costumer)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING d_id";

        $stmt = $conn->prepare($insertDispatch);
        $stmt->execute([
            $booking_no,
            $booking_sn,
            $booking_do,
            $broker,
            $EIROut,
            $now,
            $dispatcher,
            $dispatchHub,
            $driver,
            $driver_id,
            $unitName,
            $trailer,
            $genset,
            $trip_receipt,
            $ecs,
            $costumer
        ]);
        $dispatch_id = (int)$stmt->fetchColumn();
// 3️⃣ Get booking data for trips
        $getBooking = "SELECT container, container_status, hauling_segment, hauling_type, trip_from, trip_to, return_location, booking_daterequired, booking_activity
               FROM booking WHERE booking_no = ?";
        $stmt = $conn->prepare($getBooking);
        $stmt->execute([$booking_no]);
        $result = $stmt;
        $bookingData = $result->fetch();
if (!$bookingData) {
            throw new Exception("Booking details not found for booking_no: " . $booking_no);
        }
        // 🧮 Compute km_run using Haversine formula (safe even if coords missing)
         function getOSRMDistanceWithGeometry($lat1, $lon1, $lat2, $lon2)
        {
            $url = "http://router.project-osrm.org/route/v1/driving/"
                . "$lon1,$lat1;$lon2,$lat2"
                . "?overview=full&geometries=geojson";

            $response = @file_get_contents($url);
            if ($response === false) return 0;

            $data = json_decode($response, true);

            if (
                !isset($data['routes'][0]['distance']) ||
                !isset($data['routes'][0]['geometry']['coordinates'])
            ) {
                return 0;
            }

            $route = $data['routes'][0];
            $distanceKm = $route['distance'] / 1000;
            $coords = $route['geometry']['coordinates'];

            // 📍 SPECIAL POINT
            $specialLat = 7.333325;
            $specialLon = 125.626795;

            // 🔍 Check if route passes near this point
            if (pointNearRoute($coords, $specialLat, $specialLon, 100)) {
                $distanceKm += 2.4;
            }

            return round($distanceKm, 2);
        }

            function pointNearRoute($routeCoords, $checkLat, $checkLon, $toleranceMeters = 100)
            {
                foreach ($routeCoords as $coord) {
                    // OSRM uses [lon, lat]
                    $lon = $coord[0];
                    $lat = $coord[1];

                    $distance = haversineMeters($lat, $lon, $checkLat, $checkLon);

                    if ($distance <= $toleranceMeters) {
                        return true;
                    }
                }
                return false;
            }

            function haversineMeters($lat1, $lon1, $lat2, $lon2)
            {
                $earthRadius = 6371000;

                $dLat = deg2rad($lat2 - $lat1);
                $dLon = deg2rad($lon2 - $lon1);

                $a = sin($dLat / 2) ** 2 +
                    cos(deg2rad($lat1)) *
                    cos(deg2rad($lat2)) *
                    sin($dLon / 2) ** 2;

                return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
            }

        $from = $bookingData['trip_from'];
        $to   = $bookingData['trip_to'];
        $sqlLoc = "SELECT location_name, latitude, longitude FROM location WHERE location_name IN (?, ?)";
        $stmt = $conn->prepare($sqlLoc);
        $stmt->execute([$from, $to]);
        $resLoc = $stmt;
        $locations = [];
        while ($row = $resLoc->fetch()) {
            $locations[$row['location_name']] = $row;
        }
$km_run = 0; // default if no lat/lng
        if (
            isset(
                $locations[$from]['latitude'], 
                $locations[$from]['longitude'],
                $locations[$to]['latitude'], 
                $locations[$to]['longitude']
            )
            && is_numeric($locations[$from]['latitude'])
            && is_numeric($locations[$from]['longitude'])
            && is_numeric($locations[$to]['latitude'])
            && is_numeric($locations[$to]['longitude'])
        ) {
            $km_run = getOSRMDistanceWithGeometry(
                $locations[$from]['latitude'],
                $locations[$from]['longitude'],
                $locations[$to]['latitude'],
                $locations[$to]['longitude']
            );
        }

        // Rate stamped at dispatch time via the SAME matcher the coupon uses,
        // so trips.piece_rate agrees with the Trip Verification Coupon.
        require_once __DIR__ . '/../helpers/trip_rate_lookup.php';
        $pieceRate = fleet_compute_trip_piece_rate(
            $conn,
            $bookingData['hauling_segment'],
            $bookingData['hauling_type'],
            $bookingData['container_status']
        );

        // 4️⃣ Insert into trips
        $insertTrip = "INSERT INTO trips
            (d_id, trip_type, costumer, trip_container, container_activity, trip_containerstat, trip_haulingsegment, trip_haulingtype,
            trip_from, trip_to, return_location, km_run, required_date, trip_status, piece_rate)
            VALUES (?, 'Trip 1', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)";

        $stmt = $conn->prepare($insertTrip);
        $stmt->execute([
            $dispatch_id,
            $costumer,
            $bookingData['container'],
            $bookingData['booking_activity'],
            $bookingData['container_status'],
            $bookingData['hauling_segment'],
            $bookingData['hauling_type'],
            $bookingData['trip_from'],
            $bookingData['trip_to'],
            $bookingData['return_location'],
            $km_run,
            $bookingData['booking_daterequired'],
            $pieceRate
        ]);

        // Auto-add a 0.00 placeholder Trip Rate for an un-priced SKU, so it
        // surfaces on the Trip Rates page for the admin.
        require_once __DIR__ . '/../helpers/trip_rate_lookup.php';
        fleet_ensure_trip_rate_stub($conn, $bookingData['hauling_segment'], $bookingData['hauling_type'], $bookingData['container_status']);

        // ✅ Insert into container_activity
        if (!empty($bookingData['container'])) {
            $insertContainer = "INSERT INTO container_activity
                (container_name, container_location, truck_no, trailer_no, genset_no, driver_name, container_status, trip_status, date)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?)";
            $stmt = $conn->prepare($insertContainer);
            $stmt->execute([
                $bookingData['container'],
                $bookingData['trip_to'],
                $unitName,
                $trailer,
                $genset,
                $driver,
                $bookingData['container_status'],
                $now
            ]);
        }

        // 5️⃣ Update booking quantity_use
        $updateBooking = "UPDATE booking SET quantity_use = quantity_use + 1, status = 'Complete' WHERE booking_no = ?";
        $stmt = $conn->prepare($updateBooking);
        $stmt->execute([$booking_no]);

        // 🔟 After updating quantity_use, check if complete
        $checkComplete = "SELECT quantity, quantity_use FROM booking WHERE booking_no = ?";
        $stmt = $conn->prepare($checkComplete);
        $stmt->execute([$booking_no]);
        $row = $stmt->fetch();
        if ($row && (int)$row['quantity_use'] >= (int)$row['quantity']) {
            $updateStatus = "UPDATE booking SET status = 'Complete' WHERE booking_no = ?";
            $stmt = $conn->prepare($updateStatus);
            $stmt->execute([$booking_no]);
        }

        // 6️⃣ Update driver status to Dispatch
        $updateDriver = "UPDATE drivers SET driver_status = 'Dispatch' WHERE driver_id = ?";
        $stmt = $conn->prepare($updateDriver);
        $stmt->execute([$driver_id]);

        // 7️⃣ Assign driver to Unit (Truck)
        if (!empty($unitName)) {
            $updateUnit = "UPDATE units SET unit_assign = ?, driver_id = ?, unit_assigngenset = ?,  unit_assigntrailer = ?, unit_status = 'Dispatch' WHERE unit_name = ?";
            $stmt = $conn->prepare($updateUnit);
            $stmt->execute([$driver, $driver_id, $genset, $trailer, $unitName]);
        }

        // 8️⃣ Assign driver to Trailer
        if (!empty($trailer)) {
            $updateTrailer = "UPDATE trailer SET trailer_assignto = ?, driver_id = ? WHERE trailer_name = ?";
            $stmt = $conn->prepare($updateTrailer);
            $stmt->execute([$driver, $driver_id, $trailer]);
        }

        // 8.1️⃣ Insert into trailer_movement
        if (!empty($trailer)) {
            $insertTrailerMovement = "INSERT INTO trailer_movement
                (tm_trailername, tm_driverassign, tm_location, tm_recordedtype, tm_recordedby, tm_date)
                VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertTrailerMovement);
            $recordedType = "Dispatch";
            $stmt->execute([$trailer, $driver, $to, $recordedType, $dispatcher, $now]);
        }

        // 9️⃣ Assign driver to Genset
        if (!empty($genset)) {
            $updateGenset = "UPDATE units SET unit_assign = ?, driver_id = ?, unit_status = 'Dispatch' WHERE unit_name = ?";
            $stmt = $conn->prepare($updateGenset);
            $stmt->execute([$driver, $driver_id, $genset]);
        }

        // ✅ Commit transaction
        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Booking assigned successfully!", "insert_id" => $dispatch_id]);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
