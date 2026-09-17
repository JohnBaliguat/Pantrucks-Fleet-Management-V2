<?php
include "../config/config.php";

$response = [
    "total_booking" => 0,
    "today_booking" => 0,
    "today_completed" => 0
];

// TOTAL BOOKINGS
$sqlTotal = "SELECT COUNT(*) AS total FROM booking";
$resTotal = $conn->query($sqlTotal);
$response['total_booking'] = ($resTotal)->fetch()['total'];

// TODAY'S BOOKINGS
$sqlToday = "
    SELECT COUNT(*) AS total 
    FROM booking 
    WHERE DATE(booking_date) = CURRENT_DATE
";
$resToday = $conn->query($sqlToday);
$response['today_booking'] = ($resToday)->fetch()['total'];

// TODAY'S COMPLETED TRIPS
$sqlCompleted = "
    SELECT COUNT(*) AS total
    FROM trips
    WHERE trip_status = 'Done'
      AND (
            (deliver_datetime >= CURRENT_DATE
             AND deliver_datetime < CURRENT_DATE + INTERVAL '1 day')
         OR (withdraw_datetime >= CURRENT_DATE
             AND withdraw_datetime < CURRENT_DATE + INTERVAL '1 day')
      )
";
$resCompleted = $conn->query($sqlCompleted);
$response['today_completed'] = ($resCompleted)->fetch()['total'];

echo json_encode($response);
