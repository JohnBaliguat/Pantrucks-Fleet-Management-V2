<?php
include "../../config/config.php";

/* =======================
   HELPER FUNCTIONS
======================= */

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

function pointNearRoute($routeCoords, $checkLat, $checkLon, $toleranceMeters = 100)
{
    foreach ($routeCoords as $coord) {
        $lon = $coord[0];
        $lat = $coord[1];

        if (haversineMeters($lat, $lon, $checkLat, $checkLon) <= $toleranceMeters) {
            return true;
        }
    }
    return false;
}

function getOSRMDistanceWithGeometry($lat1, $lon1, $lat2, $lon2)
{
    $url = "http://router.project-osrm.org/route/v1/driving/"
        . "$lon1,$lat1;$lon2,$lat2"
        . "?overview=full&geometries=geojson";

    $response = @file_get_contents($url);
    if ($response === false) return 0;

    $data = json_decode($response, true);

    if (!isset($data['routes'][0])) return 0;

    $route = $data['routes'][0];
    $distanceKm = $route['distance'] / 1000;
    $coords = $route['geometry']['coordinates'];

    // SPECIAL POINT
    $specialLat = 7.333325;
    $specialLon = 125.626795;

    if (pointNearRoute($coords, $specialLat, $specialLon, 100)) {
        $distanceKm += 2.4;
    }

    return round($distanceKm, 2);
}

function getKmRun($conn, $from, $to)
{
    if (empty($from) || empty($to)) return 0;

    $sql = "SELECT location_name, latitude, longitude
            FROM location
            WHERE location_name IN (?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$from, $to]);
    $res = $stmt;

    $locations = [];
    while ($row = $res->fetch()) {
        $locations[$row['location_name']] = $row;
    }
if (
        isset(
            $locations[$from]['latitude'],
            $locations[$from]['longitude'],
            $locations[$to]['latitude'],
            $locations[$to]['longitude']
        )
    ) {
        return getOSRMDistanceWithGeometry(
            $locations[$from]['latitude'],
            $locations[$from]['longitude'],
            $locations[$to]['latitude'],
            $locations[$to]['longitude']
        );
    }

    return 0;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $booking_no = $_POST["booking_no"];
    date_default_timezone_set("Asia/Manila");
    $d_datetime = date("Y-m-d H:i:s");
    $d_dispatcher = $_POST['userName'];
    $d_dispatchHub = $_POST['assinglocation'];

    // Main dispatch data
    $d_driverName = $_POST['driver'];  // changed
    $driver_id    = $_POST['driver_id'];
    $d_truck = $_POST['unit_name'];    // changed
    $d_trailer = $_POST['trailer'];
    $d_genset = $_POST['genset'];
    $d_tripReceipt = $_POST['tr'];
    $d_ecs = $_POST['ecs'];

    // Trip 1
    $trip1_costumer = $_POST['costumer'] ?? '';
    $trip1_containerSeal = $_POST['container_seal'];
    $trip1_container = $_POST['container_no'];
    $trip1_status = $_POST['container_status'];
    $trip1_activity = $_POST['booking_activity'];
    $trip1_segment = $_POST['hauling_segment'];
    $trip1_type = "Trip 1";
    $trip1_from = $_POST['destination_from'];
    $trip1_to = $_POST['destination_to'];

    // Trip 2
    $trip2_trailer = $_POST['trailer2'] ?? '';
    $trip2_genset = $_POST['genset2'] ?? '';
    $trip2_costumer = $_POST['costumer2'] ?? '';
    $trip2_containerSeal = $_POST['container_seal2'];
    $trip2_container = $_POST['container_no2'];
    $trip2_status = $_POST['container_status2'];
    $trip2_activity = $_POST['booking_activity2'];
    $trip2_segment = $_POST['hauling_segment2'];
    $trip2_type = "Trip 2";
    $trip2_from = $_POST['destination_from1'];
    $trip2_to = $_POST['destination_to1'];

    // Trip 3

    $trip3_trailer = $_POST['trailer3'] ?? '';
    $trip3_genset = $_POST['genset3'] ?? '';
    $trip3_costumer = $_POST['costumer3'] ?? '';
    $trip3_containerSeal = $_POST['container_seal3'];
    $trip3_container = $_POST['container_no3'];
    $trip3_status = $_POST['container_status3'];
    $trip3_activity = $_POST['booking_activity3'];
    $trip3_segment = $_POST['hauling_segment3'];
    $trip3_type = "Trip 3";
    $trip3_from = $_POST['destination_from3'];
    $trip3_to = $_POST['destination_to3'];

    // Trip 4

    $trip4_trailer = $_POST['trailer4'] ?? '';
    $trip4_genset = $_POST['genset4'] ?? '';
    $trip4_costumer = $_POST['costumer4'] ?? '';
    $trip4_containerSeal = $_POST['container_seal4'];
    $trip4_container = $_POST['container_no4'];
    $trip4_status = $_POST['container_status4'];
    $trip4_activity = $_POST['booking_activity4'];
    $trip4_segment = $_POST['hauling_segment4'];
    $trip4_type = "Trip 4";
    $trip4_from = $_POST['destination_from4'];
    $trip4_to = $_POST['destination_to4'];


    // Fetch hauling types
    $trip1_hauling_type = '';
    $trip2_hauling_type = '';
    $trip3_hauling_type = '';
    $trip4_hauling_type = '';



    $trip1_km_run = getKmRun($conn, $trip1_from, $trip1_to);
    $trip2_km_run = 0;
    $trip3_km_run = 0;
    $trip4_km_run = 0;

    if (!empty($trip2_from) && !empty($trip2_to)) {
        $trip2_km_run = getKmRun($conn, $trip2_from, $trip2_to);
    }

    if (!empty($trip3_from) && !empty($trip3_to)) {
        $trip3_km_run = getKmRun($conn, $trip3_from, $trip3_to);
    }

    if (!empty($trip4_from) && !empty($trip4_to)) {
        $trip4_km_run = getKmRun($conn, $trip4_from, $trip4_to);
    }


    // 0.5️⃣ Validate Unit (Truck) registration + status + assignment
    if (!empty($d_truck)) {
        $checkUnit = "SELECT unit_status, unit_assign, driver_id FROM units WHERE unit_name = ?";
        $stmt = $conn->prepare($checkUnit);
        $stmt->execute([$d_truck]);
        $result = $stmt;
        $unitRow = $result->fetch();
if (!$unitRow) {
            echo json_encode(["status" => "error", "message" => "Truck is not registered!"]);
            exit;
        }

        // 🚫 Status check
        if (in_array($unitRow['unit_status'], ['Rescue', 'Shop Unit'])) {
            echo json_encode(["status" => "error", "message" => "Truck is unavailable! (" . $unitRow['unit_status'] . ")"]);
            exit;
        }

        // 🚫 Assignment check
        if (!empty($unitRow['unit_assign']) && (int)$unitRow['driver_id'] !== 0) {
            if ($unitRow['unit_assign'] !== $d_driverName || (int)$unitRow['driver_id'] !== (int)$driver_id) {
                echo json_encode(["status" => "error", "message" => "Truck is already assigned to another driver!"]);
                exit;
            }
        }
    }

    $gensets = array_filter([$d_genset, $trip2_genset, $trip3_genset, $trip4_genset]);

    // 0.5️⃣ Validate Unit (Genset) registration + status + assignment
    foreach ($gensets as $gensetName) {

        $checkGenset = "SELECT unit_status, unit_assign, driver_id 
                        FROM units 
                        WHERE unit_name = ?";
        $stmt = $conn->prepare($checkGenset);
        $stmt->execute([$gensetName]);
        $result = $stmt;
        $gensetRow = $result->fetch();
if (!$gensetRow) {
            echo json_encode([
                "status" => "error",
                "message" => "Genset $gensetName is not registered!"
            ]);
            exit;
        }

        if (in_array($gensetRow['unit_status'], ['Rescue', 'Shop Unit'])) {
            echo json_encode([
                "status" => "error",
                "message" => "Genset $gensetName is unavailable! (" . $gensetRow['unit_status'] . ")"
            ]);
            exit;
        }

        // if (!empty($gensetRow['unit_assign']) && (int)$gensetRow['driver_id'] !== 0) {
        //     if ($gensetRow['unit_assign'] !== $d_driverName || (int)$gensetRow['driver_id'] !== (int)$driver_id) {
        //         echo json_encode(["status" => "error", "message" => "Genset is already assigned to another driver!"]);
        //         exit;
        //     }
        // }
    }

    // 0.6️⃣ Validate Trailer registration + status + assignment
    $trailers = array_filter([$d_trailer, $trip2_trailer, $trip3_trailer, $trip4_trailer]);

    foreach ($trailers as $trailerName) {
        $checkTrailer = "SELECT trailer_status, trailer_assignto, driver_id FROM trailer WHERE trailer_name = ?";
        $stmt = $conn->prepare($checkTrailer);
        $stmt->execute([$trailerName]);
        $result = $stmt;
        $trailerRow = $result->fetch();
if (!$trailerRow) {
            echo json_encode(["status" => "error", "message" => "Trailer $trailerName is not registered!"]);
            exit;
        }

        if (in_array($trailerRow['trailer_status'], ['Rescue', 'Shop Unit'])) {
            echo json_encode(["status" => "error", "message" => "Trailer $trailerName is unavailable! (" . $trailerRow['trailer_status'] . ")"]);
            exit;
        }
    }

    // 0.7️⃣ Check if driver already has live dispatch work in-flight.
    $checkActiveTrips = "SELECT COUNT(*) AS active_count
            FROM trips t
            INNER JOIN dispatch d ON t.d_id = d.d_id
            WHERE d.driver_id = ?
              AND d.workflow_stage IN (
                  'dispatcher_assigned',
                  'reassigned',
                  'driver_accepted',
                  'gate_cleared',
                  'en_route',
                  'pending_verification'
              )
        ";
    $stmt = $conn->prepare($checkActiveTrips);
    $stmt->execute([$driver_id]);
    $result = $stmt;
    $activeData = $result->fetch();
if ((int)$activeData['active_count'] > 0) {
        echo json_encode(["status" => "error", "message" => "Driver still has an active dispatch workflow."]);
        exit;
    }

    $stmt1 = $conn->prepare("SELECT hauling_type FROM hauling WHERE hauling_segment = ?");
    $stmt1->execute([$trip1_segment]);
    $row1 = $stmt1->fetch();
    $trip1_hauling_type = $row1['hauling_type'] ?? '';

    if (!empty($trip2_segment)) {
        $stmt2 = $conn->prepare("SELECT hauling_type FROM hauling WHERE hauling_segment = ?");
        $stmt2->execute([$trip2_segment]);
        $row2 = $stmt2->fetch();
        $trip2_hauling_type = $row2['hauling_type'] ?? '';
    }

    if (!empty($trip3_segment)) {
        $stmt3 = $conn->prepare("SELECT hauling_type FROM hauling WHERE hauling_segment = ?");
        $stmt3->execute([$trip3_segment]);
        $row3 = $stmt3->fetch();
        $trip3_hauling_type = $row3['hauling_type'] ?? '';
    }

    if (!empty($trip4_segment)) {
        $stmt4 = $conn->prepare("SELECT hauling_type FROM hauling WHERE hauling_segment = ?");
        $stmt4->execute([$trip4_segment]);
        $row4 = $stmt4->fetch();
        $trip4_hauling_type = $row4['hauling_type'] ?? '';
    }

    // Get driver_id from DB
    $stmt = $conn->prepare("SELECT driver_id FROM drivers WHERE driver_lname = ?");
    $lastname = strtok($d_driverName, ","); // get last name before comma
    $stmt->execute([$lastname]);
    $driverRow = $stmt->fetch();
    if ($driverRow && !empty($driverRow['driver_id'])) {
        $driver_id = $driverRow['driver_id'];
    }

    // Insert into dispatch
    $stmt = $conn->prepare("INSERT INTO dispatch
        (booking_no, d_datetime, d_dispatcher, d_dispatchhub, d_drivername, driver_id, d_truck, d_trailer, d_genset, d_tripreceipt, d_ecs)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([$booking_no, $d_datetime, $d_dispatcher, $d_dispatchHub, $d_driverName, $driver_id, $d_truck, $d_trailer, $d_genset, $d_tripReceipt, $d_ecs]);
    $dispatch_id = $conn->lastInsertId();

        // Trip 1 insert
        $trip1_stmt = $conn->prepare("INSERT INTO trips 
            (d_id, trip_type, costumer, trip_container, trip_containerstat, container_activity, trip_haulingsegment, trip_haulingtype, trip_from, trip_to, km_run, trip_status, trip_trailer, trip_genset) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?)");
        $trip1_stmt->execute([$dispatch_id, $trip1_type, $trip1_costumer, $trip1_container, $trip1_status, $trip1_activity, $trip1_segment, $trip1_hauling_type, $trip1_from, $trip1_to, $trip1_km_run, $d_trailer, $d_genset]);
// ✅ Insert container_activity for Trip 1
        if (!empty($trip1_container)) {
            $insertContainer1 = $conn->prepare("INSERT INTO container_activity 
                (container_name, container_location, truck_no, trailer_no, genset_no, driver_name, container_status, trip_status, date)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
            $insertContainer1->execute([$trip1_container,
                $trip1_to,
                $d_truck,
                $d_trailer,
                $d_genset,
                $d_driverName,
                $trip1_status,
                $d_datetime]);
        }

        // Trip 2 insert (optional)
        if (!empty($trip2_costumer) || !empty($trip2_container) || !empty($trip2_status) || !empty($trip2_segment) || !empty($trip2_from) || !empty($trip2_to)) {
            $trip2_stmt = $conn->prepare("INSERT INTO trips 
                (d_id, trip_type, costumer, trip_container, trip_containerstat, container_activity, trip_haulingsegment, trip_haulingtype, trip_from, trip_to, km_run, trip_status, trip_trailer, trip_genset) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?)");
            $trip2_stmt->execute([$dispatch_id, $trip2_type, $trip2_costumer, $trip2_container, $trip2_status, $trip2_activity, $trip2_segment, $trip2_hauling_type, $trip2_from, $trip2_to, $trip2_km_run, $trip2_trailer, $trip2_genset]);
// ✅ Insert container_activity for Trip 2
            if (!empty($trip2_container)) {
                $insertContainer2 = $conn->prepare("INSERT INTO container_activity 
                    (container_name, container_location, truck_no, trailer_no, genset_no, driver_name, container_status, trip_status, date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
                $insertContainer2->execute([$trip2_container,
                    $trip2_to,
                    $d_truck,
                    $trip2_trailer,
                    $trip2_genset,
                    $d_driverName,
                    $trip2_status,
                    $d_datetime]);
            }

            if (!empty($trip2_trailer)) {
                $insertTrailerMovement2 = "INSERT INTO trailer_movement
                    (tm_trailername, tm_driverassign, tm_location, tm_recordedtype, tm_recordedby, tm_date)
                    VALUES (?, ?, ?, ?, ?, ?)";
                $stmt2 = $conn->prepare($insertTrailerMovement2);
                $recordedType2 = "Dispatch";
                $location2 = !empty($trip2_to) ? $trip2_to : "";

                $stmt2->execute([$trip2_trailer, $d_driverName, $location2, $recordedType2, $d_dispatcher, $d_datetime]);
            }
        }

        if (!empty($trip3_costumer) || !empty($trip3_container) || !empty($trip3_status) || !empty($trip3_segment) || !empty($trip3_from) || !empty($trip3_to)) {
            $trip3_stmt = $conn->prepare("INSERT INTO trips 
                (d_id, trip_type, costumer, trip_container, trip_containerstat, container_activity, trip_haulingsegment, trip_haulingtype, trip_from, trip_to, km_run, trip_status, trip_trailer, trip_genset) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?)");
            $trip3_stmt->execute([$dispatch_id, $trip3_type, $trip3_costumer, $trip3_container, $trip3_status, $trip3_activity, $trip3_segment, $trip3_hauling_type, $trip3_from, $trip3_to, $trip3_km_run, $trip3_trailer, $trip3_genset]);
// ✅ Insert container_activity for Trip 4
            if (!empty($trip3_container)) {
                $insertContainer3 = $conn->prepare("INSERT INTO container_activity 
                    (container_name, container_location, truck_no, trailer_no, genset_no, driver_name, container_status, trip_status, date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
                $insertContainer3->execute([$trip3_container,
                    $trip3_to,
                    $d_truck,
                    $trip3_trailer,
                    $trip3_genset,
                    $d_driverName,
                    $trip3_status,
                    $d_datetime]);
            }

            if (!empty($trip3_trailer)) {
                $insertTrailerMovement3 = "INSERT INTO trailer_movement
                    (tm_trailername, tm_driverassign, tm_location, tm_recordedtype, tm_recordedby, tm_date)
                    VALUES (?, ?, ?, ?, ?, ?)";
                $stmt3 = $conn->prepare($insertTrailerMovement3);
                $recordedType3 = "Dispatch";
                $location3 = !empty($trip3_to) ? $trip3_to : "";

                $stmt3->execute([$trip3_trailer, $d_driverName, $location3, $recordedType3, $d_dispatcher, $d_datetime]);
            }
        }

        if (!empty($trip4_costumer) || !empty($trip4_container) || !empty($trip4_status) || !empty($trip4_segment) || !empty($trip4_from) || !empty($trip4_to)) {
            $trip4_stmt = $conn->prepare("INSERT INTO trips 
                (d_id, trip_type, costumer, trip_container, trip_containerstat, container_activity, trip_haulingsegment, trip_haulingtype, trip_from, trip_to, km_run, trip_status, trip_trailer, trip_genset) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?)");
            $trip4_stmt->execute([$dispatch_id, $trip4_type, $trip4_costumer, $trip4_container, $trip4_status, $trip4_activity, $trip4_segment, $trip4_hauling_type, $trip4_from, $trip4_to, $trip4_km_run, $trip4_trailer, $trip4_genset]);
// ✅ Insert container_activity for Trip 4
            if (!empty($trip4_container)) {
                $insertContainer4 = $conn->prepare("INSERT INTO container_activity 
                    (container_name, container_location, truck_no, trailer_no, genset_no, driver_name, container_status, trip_status, date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
                $insertContainer4->execute([$trip4_container,
                    $trip4_to,
                    $d_truck,
                    $trip4_trailer,
                    $trip4_genset,
                    $d_driverName,
                    $trip4_status,
                    $d_datetime]);
            }

            if (!empty($trip4_trailer)) {
                $insertTrailerMovement4 = "INSERT INTO trailer_movement
                    (tm_trailername, tm_driverassign, tm_location, tm_recordedtype, tm_recordedby, tm_date)
                    VALUES (?, ?, ?, ?, ?, ?)";
                $stmt4 = $conn->prepare($insertTrailerMovement4);
                $recordedType4 = "Dispatch";
                $location4 = !empty($trip4_to) ? $trip4_to : "";

                $stmt4->execute([$trip4_trailer, $d_driverName, $location4, $recordedType4, $d_dispatcher, $d_datetime]);
            }
        }
        echo json_encode([
            'status' => 'success',
            'message' => 'Dispatch and trip(s) added!',
            'insert_id' => $dispatch_id
        ]);

        // Insert containers into container_monitoring if not already present
        $containers = array_filter([$trip1_container, $trip2_container, $trip3_container, $trip4_container]);
        foreach ($containers as $container) {
            if (!empty($container)) {
                // Check if container already exists in container_monitoring
                $checkStmt = $conn->prepare("SELECT id FROM container_monitoring WHERE container_number = ?");
                $checkStmt->execute([$container]);
                if ($checkStmt->rowCount() == 0) {
                    // Insert new container with initial status
                    $insertStmt = $conn->prepare("INSERT INTO container_monitoring (container_number, status, stage, remarks) VALUES (?, 'Active', 'Dispatched', 'Added from dispatch')");
                    $insertStmt->execute([$container]);
                }
            }
        }

    // 6️⃣ Update driver status to Dispatch
    $updateDriver = "UPDATE drivers SET driver_status = 'Dispatch' WHERE driver_id = ?";
    $stmt = $conn->prepare($updateDriver);
    $stmt->execute([$driver_id]);

    // 6.1️⃣ Auto-start a shift if the dispatcher assigns a driver who
    // hasn't opened one in the mobile checklist flow yet. This keeps
    // legacy/manual dispatch workable for drivers not yet using the app.
    if ($driver_id > 0 && !empty($d_truck)) {
        $openShiftExists = false;
        $stmt = $conn->prepare("SELECT ds_id FROM driver_shift WHERE driver_id = ? AND ended_at IS NULL ORDER BY ds_id DESC LIMIT 1");
        $stmt->execute([$driver_id]);
        $openShift = $stmt->fetch();
        $openShiftExists = !empty($openShift);

        if (!$openShiftExists) {
            $autoShiftNote = "Auto-started by dispatcher assignment for {$booking_no}";
            $stmt = $conn->prepare(
                "INSERT INTO driver_shift (driver_id, truck_code, started_at, notes)
                 VALUES (?, ?, NOW(), ?)"
            );
            $stmt->execute([$driver_id, $d_truck, $autoShiftNote]);

            $stmt = $conn->prepare(
                "UPDATE drivers
                 SET shift_truck = ?, shift_started_at = NOW(), shift_ended_at = NULL
                 WHERE driver_id = ?"
            );
            $stmt->execute([$d_truck, $driver_id]);
        }
    }

    // 7️⃣ Assign driver to Unit (Truck)
    if (!empty($d_truck)) {
        $updateUnit = "UPDATE units SET unit_assign = ?, driver_id = ?, unit_assigngenset = ?,  unit_assigntrailer = ?, unit_status = 'Dispatch' WHERE unit_name = ?";
        $stmt = $conn->prepare($updateUnit);
        $stmt->execute([$d_driverName, $driver_id, $d_genset, $d_trailer, $d_truck]);
    }

    // 8️⃣ Assign driver to Trailer
    if (!empty($d_trailer)) {
        $updateTrailer = "UPDATE trailer SET trailer_assignto = ?, driver_id = ? WHERE trailer_name = ?";
        $stmt = $conn->prepare($updateTrailer);
        $stmt->execute([$d_driverName, $driver_id, $d_trailer]);
    }

    // 8.1️⃣ Insert into trailer_movement
    if (!empty($d_trailer)) {
        $insertTrailerMovement = "INSERT INTO trailer_movement
            (tm_trailername, tm_driverassign, tm_location, tm_recordedtype, tm_recordedby, tm_date)
            VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertTrailerMovement);
        $recordedType = "Dispatch";
        $location = !empty($trip1_to) ? $trip1_to : "";
        $stmt->execute([$d_trailer, $d_driverName, $location, $recordedType, $d_dispatcher, $d_datetime]);
    }

    // 9️⃣ Assign driver to Genset
    if (!empty($d_genset)) {
        $updateGenset = "UPDATE units SET unit_assign = ?, driver_id = ? WHERE unit_name = ?";
        $stmt = $conn->prepare($updateGenset);
        $stmt->execute([$d_driverName, $driver_id, $d_genset]);
    }
}
