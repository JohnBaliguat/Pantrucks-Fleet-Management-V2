<?php
// Quick-add equipment by NAME only. For Dispatcher / Dispatch Admin (and Admin).
// Trucks + gensets land in `units` (unit_type), trailers in `trailer`. All the
// other NOT NULL columns get safe placeholder defaults so the unit can be
// fleshed out later from the full Admin forms.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Admin', 'Dispatch Admin', 'Dispatcher'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$type = strtolower(trim($_POST['type'] ?? ''));
$name = trim($_POST['name'] ?? '');

if (!in_array($type, ['truck', 'trailer', 'genset'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid equipment type.']);
    exit;
}
if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Name is required.']);
    exit;
}
if (mb_strlen($name) > 100) {
    echo json_encode(['status' => 'error', 'message' => 'Name is too long (max 100 characters).']);
    exit;
}

$recordedBy = trim((string)($_SESSION['user_name'] ?? $_SESSION['user_fullname'] ?? ''));

try {
    if ($type === 'trailer') {
        // Duplicate guard.
        $chk = $conn->prepare("SELECT 1 FROM trailer WHERE UPPER(TRIM(trailer_name)) = UPPER(?) LIMIT 1");
        $chk->execute([$name]);
        if ($chk->fetchColumn()) {
            echo json_encode(['status' => 'error', 'message' => "Trailer '{$name}' already exists."]);
            exit;
        }
        $sql = "INSERT INTO trailer (
                    trailer_name, trailer_plateno, trailer_assignto, driver_id,
                    trailer_location, trailer_status,
                    t_recordedby, t_approvedby, t_date,
                    trailer_container, trailer_remarks
                ) VALUES (?, '', '', 0, '', 'good', ?, '', NOW(), '', '')";
        $conn->prepare($sql)->execute([$name, $recordedBy]);
    } else {
        // Truck or genset → units table (unit_type distinguishes them).
        $chk = $conn->prepare("SELECT 1 FROM units WHERE UPPER(TRIM(unit_name)) = UPPER(?) LIMIT 1");
        $chk->execute([$name]);
        if ($chk->fetchColumn()) {
            echo json_encode(['status' => 'error', 'message' => "A unit named '{$name}' already exists."]);
            exit;
        }
        // Every NOT NULL column with no default gets a safe placeholder.
        $sql = "INSERT INTO units (
                    unit_name, unit_type, unit_plate, unit_address, unit_assign, driver_id,
                    unit_assigngenset, unit_assigntrailer, unit_std, unit_status,
                    unit_or, unit_cr, unit_brand, unit_modal, unit_year, unit_engineno,
                    unit_chassisno, unit_fueltype, unit_capacity, unit_remarks,
                    unit_frontview, unit_leftview, unit_rightview, unit_backview, unit_assignsegment
                ) VALUES (?, ?, '', '', '', 0, '', '', 0, 'Good',
                          '', '', '', '', '', '', '', '', '', '', '', '', '', '', '')";
        $conn->prepare($sql)->execute([$name, $type]);
    }

    echo json_encode([
        'status'  => 'success',
        'message' => ucfirst($type) . " '{$name}' added.",
        'type'    => $type,
        'name'    => $name,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Add failed: ' . $e->getMessage()]);
}
