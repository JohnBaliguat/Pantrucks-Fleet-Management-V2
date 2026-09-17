<?php
// Flat list of distinct activities in trip_rates, each with its total rate.
// Used by the trip-verification modal to populate per-trip activity dropdowns.
// "All activities across all segments" — when an activity name exists with
// both a segment-specific and a segment-blank row, we return the one with
// the highest id (last edited wins).

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$role = $_SESSION['user_type'] ?? '';
if (!in_array($role, ['Dispatcher', 'Dispatch Admin', 'Admin', 'Payroll'], true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorised', 'rows' => []]);
    exit;
}

try {
    // DISTINCT ON keeps just one row per activity, preferring the most
    // recently edited (highest id).
    $sql = "
        SELECT DISTINCT ON (LOWER(TRIM(activity)))
               activity, segment, base_rate, additional, total_rates
          FROM trip_rates
          WHERE TRIM(activity) <> ''
         ORDER BY LOWER(TRIM(activity)), id DESC
    ";
    $stmt = $conn->query($sql);
    $rows = [];
    while ($r = $stmt->fetch()) {
        $rows[] = [
            'activity'    => $r['activity'],
            'segment'     => $r['segment'],
            'base_rate'   => (float)$r['base_rate'],
            'additional'  => (float)$r['additional'],
            'total_rates' => (float)$r['total_rates'],
        ];
    }
    // Sort case-insensitively for the dropdown.
    usort($rows, function ($a, $b) {
        return strcasecmp($a['activity'], $b['activity']);
    });
    echo json_encode(['status' => 'success', 'rows' => $rows, 'count' => count($rows)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'rows' => []]);
}
