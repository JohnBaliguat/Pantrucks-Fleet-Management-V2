<?php
// Read-only feed for admin/fuel-reconciliation.php.
// Lists reconciled fuel tickets (flagged first), with a filter for flag.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if (($_SESSION['user_type'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin only']);
    exit;
}

$flag = trim($_GET['flag'] ?? '');   // '', 'flagged', 'liters_flag', 'km_flag', 'ok', 'no_data'

$sql = "SELECT fr.fr_id, fr.f_id, fr.unit_name, fr.period_start, fr.period_end,
               fr.ticket_liters, fr.geotab_liters, fr.liters_variance,
               fr.ticket_km, fr.geotab_km, fr.km_variance, fr.flag, fr.computed_at
          FROM fuel_reconciliation fr
         WHERE 1=1";
$params = [];
if ($flag === 'flagged') {
    $sql .= " AND fr.flag IN ('liters_flag','km_flag')";
} elseif (in_array($flag, ['liters_flag', 'km_flag', 'ok', 'no_data'], true)) {
    $sql .= " AND fr.flag = ?";
    $params[] = $flag;
}
// Flagged rows first, then most recent.
$sql .= " ORDER BY CASE WHEN fr.flag IN ('liters_flag','km_flag') THEN 0 ELSE 1 END,
                   fr.period_end DESC NULLS LAST
          LIMIT 1000";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = array_map(function ($r) {
        $f = function ($v) { return $v === null ? null : (float)$v; };
        return [
            'f_id'            => (int)$r['f_id'],
            'unit_name'       => $r['unit_name'],
            'period_start'    => $r['period_start'],
            'period_end'      => $r['period_end'],
            'ticket_liters'   => $f($r['ticket_liters']),
            'geotab_liters'   => $f($r['geotab_liters']),
            'liters_variance' => $f($r['liters_variance']),
            'ticket_km'       => $f($r['ticket_km']),
            'geotab_km'       => $f($r['geotab_km']),
            'km_variance'     => $f($r['km_variance']),
            'flag'            => $r['flag'],
            'computed_at'     => $r['computed_at'],
        ];
    }, $stmt->fetchAll());

    // Small summary of open flags.
    $counts = $conn->query(
        "SELECT flag, COUNT(*) c FROM fuel_reconciliation GROUP BY flag"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    echo json_encode(['status' => 'success', 'rows' => $rows, 'counts' => $counts]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
