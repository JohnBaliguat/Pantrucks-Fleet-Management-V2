<?php
include '../php/config/config.php';
require_once __DIR__ . '/../php/helpers/datatables_helper.php';
dt_install_safety_net();

if(isset($_POST['trailer_id'])) {

    $trailer_id = $_POST['trailer_id'];
    $fromDate   = $_POST['fromDate'] ?? '';
    $toDate     = $_POST['toDate'] ?? '';

    $query = "
        SELECT * 
        FROM trailer_movement
        WHERE tm_trailername = ?
    ";

    // Add date filter if provided
    if(!empty($fromDate) && !empty($toDate)) {
        $query .= " AND DATE(tm_date) BETWEEN ? AND ? ";
    }

    $query .= " ORDER BY tm_date DESC";

    $stmt = $conn->prepare($query);

    if(!empty($fromDate) && !empty($toDate)) {
        $stmt->execute([$trailer_id, $fromDate, $toDate]);
    } else {
        $stmt->execute([$trailer_id]);
    }
    $result = $stmt;

    if($result->rowCount() > 0) {
        while($row = $result->fetch()) {
            echo "<tr>
                    <td>".date("F d, Y h:i A", strtotime($row['tm_date']))."</td>
                    <td>{$row['tm_location']}</td>
                    <td>{$row['tm_recordedtype']}</td>
                    <td>{$row['tm_driverassign']}</td>
                    <td>{$row['tm_container']}</td>
                    <td>{$row['tm_remarks']}</td>
                    <td>{$row['tm_recordedby']}</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='7' class='text-center'>No movement record found</td></tr>";
    }
}
?>
