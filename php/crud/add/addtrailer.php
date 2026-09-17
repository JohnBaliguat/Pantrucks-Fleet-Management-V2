<?php
session_start();
include "../../config/config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "POST required.";
    exit;
}

$trailer_name     = trim($_POST['trailer_name']     ?? '');
// Accept both the camelCase name used by the form (trailer_plateNo) and the
// lowercase variant — PHP $_POST is case-sensitive, so this avoids silently
// dropping the value.
$trailer_plateNo  = trim($_POST['trailer_plateNo']  ?? $_POST['trailer_plateno'] ?? '');
$trailer_location = trim($_POST['trailer_location'] ?? '');

if ($trailer_name === '' || $trailer_plateNo === '' || $trailer_location === '') {
    http_response_code(400);
    echo "Trailer name, plate number, and location are required.";
    exit;
}

// Match the rest of the codebase: trailer_status is compared case-sensitively
// as 'good' in WHERE clauses (e.g. dispatch_tiles.php).
$trailer_status = 'good';
// session user (if any) makes the audit trail useful; otherwise blank.
$recordedBy = trim((string)($_SESSION['user_name'] ?? $_SESSION['user_fullname'] ?? ''));

// The trailer table has many NOT NULL columns with no defaults — provide
// safe placeholders so PostgreSQL accepts the insert. Unassigned trailers
// have no driver, no container, no remarks yet.
try {
    $sql = "INSERT INTO trailer (
                trailer_name, trailer_plateno, trailer_assignto, driver_id,
                trailer_location, trailer_status,
                t_recordedby, t_approvedby, t_date,
                trailer_container, trailer_remarks
            ) VALUES (?, ?, '', 0, ?, ?, ?, '', NOW(), '', '')";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $trailer_name,
        $trailer_plateNo,
        $trailer_location,
        $trailer_status,
        $recordedBy,
    ]);
    echo "Trailer added successfully!";
} catch (PDOException $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
