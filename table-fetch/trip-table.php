<?php
session_start();
include '../php/config/config.php';
require_once __DIR__ . '/../php/lib/trip_deletion_requests.php';

$sessionRole = $_SESSION['user_type'] ?? '';
$isApprover  = in_array($sessionRole, ['Admin', 'Dispatch Admin'], true);
$isDispatcher = $sessionRole === 'Dispatcher';

// Map of d_id => pending request (for swapping action buttons).
try {
    $pendingByDid = td_pending_by_dispatch($conn);
} catch (Throwable $e) {
    $pendingByDid = [];
}

// -------------------------------
// Prevent notices from breaking JSON
// -------------------------------
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Log errors to file (best-effort — read-only dirs shouldn't kill us)
ini_set("log_errors", 1);
$logTarget = __DIR__ . "/error_log.txt";
if (is_writable(__DIR__) || (file_exists($logTarget) && is_writable($logTarget))) {
    ini_set("error_log", $logTarget);
}

// Always emit a parseable DataTables JSON envelope, even on fatal/uncaught.
$ptDtFallback = function (string $message): string {
    return json_encode([
        'draw'            => intval($_POST['draw'] ?? 0),
        'recordsTotal'    => 0,
        'recordsFiltered' => 0,
        'data'            => [],
        'error'           => $message,
    ]);
};

set_exception_handler(function (Throwable $e) use ($ptDtFallback) {
    error_log('trip-table.php exception: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(200); // 200 so DataTables consumes the JSON envelope.
        header('Content-Type: application/json');
    }
    echo $ptDtFallback('Server error: ' . $e->getMessage());
    exit;
});
register_shutdown_function(function () use ($ptDtFallback) {
    $err = error_get_last();
    if ($err && in_array($err['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json');
        }
        echo $ptDtFallback('Server error: ' . ($err['message'] ?? 'unknown'));
    }
});

// -------------------------------
// Detect optional table/columns so the SELECT degrades gracefully on
// older schemas (verification fields were added in phase-7 migration 003).
// -------------------------------
$hasPodCapture        = pt_table_exists($conn, 'pod_capture');
$hasVerifiedAt        = $hasPodCapture && pt_column_exists($conn, 'pod_capture', 'verified_at');
$hasVerificationNotes = $hasPodCapture && pt_column_exists($conn, 'pod_capture', 'verification_notes');

if ($hasPodCapture) {
    $podJoin = "LEFT JOIN pod_capture pc ON pc.pod_id = (
        SELECT MAX(pc2.pod_id) FROM pod_capture pc2 WHERE pc2.d_id = d.d_id
    )";
    $podSelect = "
        pc.photo1_path AS pod_photo1,
        pc.photo2_path AS pod_photo2,
        pc.photo3_path AS pod_photo3,
        pc.signature_path AS pod_signature,
        pc.signed_by AS pod_signed_by,
        " . ($hasVerifiedAt ? "pc.verified_at AS pod_verified_at," : "NULL::timestamp AS pod_verified_at,") . "
        " . ($hasVerificationNotes ? "pc.verification_notes AS pod_verification_notes" : "''::text AS pod_verification_notes");
} else {
    $podJoin = "";
    $podSelect = "
        NULL::text AS pod_photo1,
        NULL::text AS pod_photo2,
        NULL::text AS pod_photo3,
        NULL::text AS pod_signature,
        NULL::text AS pod_signed_by,
        NULL::timestamp AS pod_verified_at,
        ''::text AS pod_verification_notes";
}

function pod_asset_href(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^(?:https?:)?//#i', $path)) {
        return $path;
    }

    return ltrim(str_replace('\\', '/', $path), '/');
}

$dispatcherDisplayExpr = "COALESCE(
    NULLIF(TRIM(CONCAT(disp.user_lname, ', ', disp.user_fname)), ','),
    NULLIF(disp.user_name, ''),
    d.d_dispatcher
)";

$columns = [
    1 => 'd.d_driverName',
    2 => 'd.d_datetime',
    3 => 'd.d_dispatchHub',
    4 => 't1.trip_from',
    5 => 't2.trip_from',
    6 => 't1.trip_haulingSegment',
    7 => 't1.trip_haulingType',
    8 => 'd.workflow_stage',
    9 => 'd.d_datetime',
    10 => $dispatcherDisplayExpr,
];

// -------------------------------
// Base query
// -------------------------------
$baseQuery = "
SELECT 
    d.*, 
    {$dispatcherDisplayExpr} AS dispatcher_display,
    t1.trip_container AS trip1_container,
    t1.trip_containerstat AS trip1_containerStat,
    t1.trip_haulingsegment AS trip1_segment,
    t1.trip_haulingtype AS trip1_haulingType,
    t1.trip_from AS trip1_from,
    t1.trip_to AS trip1_to,
    t1.trip_status AS trip1_status,
    t2.trip_container AS trip2_container,
    t2.trip_containerstat AS trip2_containerStat,
    t2.trip_haulingsegment AS trip2_segment,
    t2.trip_haulingtype AS trip2_haulingType,
    t2.trip_from AS trip2_from,
    t2.trip_to AS trip2_to,
    t2.trip_status AS trip2_status,
    {$podSelect}
FROM dispatch d
LEFT JOIN \"user\" disp
    ON disp.user_id = CASE
        WHEN COALESCE(d.d_dispatcher, '') ~ '^[0-9]+$' THEN CAST(d.d_dispatcher AS INTEGER)
        ELSE NULL
    END
LEFT JOIN trips t1 ON d.d_id = t1.d_id AND t1.trip_type = 'Trip 1'
LEFT JOIN trips t2 ON d.d_id = t2.d_id AND t2.trip_type = 'Trip 2'
{$podJoin}
";

// -------------------------------
// WHERE clauses.
//   $baseClauses   — defines the dataset (completed/Done trips). Drives
//                    recordsTotal (the "… of N total" figure).
//   $filterClauses — the user's customer / date / search filters. Together with
//                    the base, drives recordsFiltered + the shown rows.
// -------------------------------
$baseClauses   = ["(t1.trip_status IN ('Done') OR t2.trip_status IN ('Done'))"];
$filterClauses = [];

// Customer filter
if (!empty($_POST['customer'])) {
    $customer = pt_pg_escape($conn, $_POST['customer']);
    $filterClauses[] = "d.costumer = '$customer'";
}

// Date range — compare on the DATE so the whole end day is included (a plain
// timestamp BETWEEN would drop trips after 00:00 on toDate).
if (!empty($_POST['fromDate']) && !empty($_POST['toDate'])) {
    $fromDate = pt_pg_escape($conn, $_POST['fromDate']);
    $toDate   = pt_pg_escape($conn, $_POST['toDate']);
    $filterClauses[] = "d.d_datetime::date BETWEEN '$fromDate' AND '$toDate'";
}

// Search filter
if (!empty($_POST["search"]["value"])) {
    $search = pt_pg_escape($conn, $_POST["search"]["value"]);
    $filterClauses[] = "(d.d_driverName LIKE '%$search%'
                       OR d.d_dispatchHub LIKE '%$search%'
                       OR d.d_truck LIKE '%$search%'
                       OR d.d_dispatcher LIKE '%$search%'
                       OR {$dispatcherDisplayExpr} LIKE '%$search%'
                       OR t1.trip_container LIKE '%$search%'
                       OR t2.trip_container LIKE '%$search%'
                       OR t1.trip_haulingSegment LIKE '%$search%'
                       OR t2.trip_haulingSegment LIKE '%$search%')";
}

$allClauses = array_merge($baseClauses, $filterClauses);

// Apply WHERE (base + filters) to the data query.
$query = $baseQuery;
if (!empty($allClauses)) {
    $query .= " WHERE " . implode(" AND ", $allClauses);
}

// Count query mirrors the data query's joins — including the dispatcher "user"
// join, since the search filter references it ({$dispatcherDisplayExpr}).
// Without this join a search errors with "missing FROM-clause entry for disp".
$countBase = "SELECT COUNT(DISTINCT d.d_id) AS total FROM dispatch d
LEFT JOIN \"user\" disp
    ON disp.user_id = CASE
        WHEN COALESCE(d.d_dispatcher, '') ~ '^[0-9]+$' THEN CAST(d.d_dispatcher AS INTEGER)
        ELSE NULL
    END
LEFT JOIN trips t1 ON d.d_id = t1.d_id AND t1.trip_type = 'Trip 1'
LEFT JOIN trips t2 ON d.d_id = t2.d_id AND t2.trip_type = 'Trip 2'";

// recordsTotal — base dataset only (unfiltered by the user's search/date/customer).
$totalQuery = $countBase;
if (!empty($baseClauses)) {
    $totalQuery .= " WHERE " . implode(" AND ", $baseClauses);
}
$totalRes = $conn->query($totalQuery);
$totalData = ($totalRes) ? intval(($totalRes)->fetch()['total']) : 0;

// recordsFiltered — base + the user's filters.
$countQuery = $countBase;
if (!empty($allClauses)) {
    $countQuery .= " WHERE " . implode(" AND ", $allClauses);
}
$countRes = $conn->query($countQuery);
$totalFiltered = ($countRes) ? intval(($countRes)->fetch()['total']) : 0;

// -------------------------------
// Ordering
// -------------------------------
if (isset($_POST["order"])) {
    $colIndex = intval($_POST['order'][0]['column']);
    $colDir = ($_POST['order'][0]['dir'] === 'desc') ? 'DESC' : 'ASC';
    if (isset($columns[$colIndex])) {
        $query .= " ORDER BY {$columns[$colIndex]} $colDir";
    } else {
        $query .= " ORDER BY d.d_id DESC";
    }
} else {
    $query .= " ORDER BY d.d_id DESC";
}

// -------------------------------
// Pagination
// -------------------------------
$start  = max(0, intval($_POST['start'] ?? 0));
$length = intval($_POST['length'] ?? 10);
// DataTables sends length = -1 for "All" — Postgres rejects a negative LIMIT,
// so only add LIMIT/OFFSET when a positive page size was requested.
if ($length >= 0) {
    $query .= " LIMIT $length OFFSET $start";
}

// -------------------------------
// Fetch data
// -------------------------------
$data = [];
$result = $conn->query($query);

if ($result && ($result)->rowCount() > 0) {
    while ($row = ($result)->fetch()) {
        $sub_array = [];
        $sub_array[] = '';
        // Driver & Truck
        $sub_array[] = '
        <div class="d-flex align-items-center">
          <img src="../assets/images/profile/user-3.jpg" class="rounded-circle" width="40" alt="driver" />
          <div class="ms-3">
            <h6 class="mb-0 fw-bolder">' . htmlspecialchars($row['d_drivername']) . '</h6>
            <span class="text-muted">' . htmlspecialchars($row['d_truck']) . '</span>
          </div>
        </div>';
      $sub_array[] = date('Y-m-d', strtotime($row['d_datetime']));
        // Dispatch Hub
        $sub_array[] = htmlspecialchars($row['d_dispatchhub']);

        // Trip 1
        if (!empty($row['trip1_from'])) {
            $sub_array[] = '
            <div class="d-flex align-items-center">
              <div class="ms-3">
                <h6 class="mb-0 fw-bolder">' . htmlspecialchars($row['trip1_from']) . ' - ' . htmlspecialchars($row['trip1_to']) . '</h6>
                <span class="text-muted">Container: ' . htmlspecialchars($row['trip1_container']) . 
                ', Status: ' . htmlspecialchars($row['trip1_containerStat']) . 
                ', Segment: ' . htmlspecialchars($row['trip1_segment']) . 
                ', Trip Status: ' . htmlspecialchars($row['trip1_status']) . '</span>
              </div>
            </div>';
        } else {
            $sub_array[] = '<span class="text-muted">No Trip 1</span>';
        }

        // Trip 2
        if (!empty($row['trip2_from'])) {
            $sub_array[] = '
            <div class="d-flex align-items-center">
              <div class="ms-3">
                <h6 class="mb-0 fw-bolder">' . htmlspecialchars($row['trip2_from']) . ' - ' . htmlspecialchars($row['trip2_to']) . '</h6>
                <span class="text-muted">Container: ' . htmlspecialchars($row['trip2_container']) . 
                ', Status: ' . htmlspecialchars($row['trip2_containerStat']) . 
                ', Segment: ' . htmlspecialchars($row['trip2_segment']) . 
                ', Trip Status: ' . htmlspecialchars($row['trip2_status']) . '</span>
              </div>
            </div>';
        } else {
            $sub_array[] = '<span class="text-muted">No Trip 2</span>';
        }

        // Hauling + Type
        $sub_array[] = htmlspecialchars($row['trip1_segment'] . '-' . $row['trip2_segment']);
        $sub_array[] = htmlspecialchars($row['trip1_haulingType'] . '-' . $row['trip2_haulingType']);

        // POD
        $podSummary = '<span class="text-muted">No POD</span>';
        if (!empty($row['pod_photo1']) || !empty($row['pod_photo2']) || !empty($row['pod_photo3'])) {
            $podStatus = 'Submitted';
            if (($row['workflow_stage'] ?? '') === 'pending_verification') {
                $podStatus = 'Awaiting Dispatch Verification';
            } elseif (in_array(($row['workflow_stage'] ?? ''), ['pod_captured', 'billing_closed', 'client_notified'], true)) {
                $podStatus = 'Verified';
            }

            $podParts = [];
            if (!empty($row['pod_signed_by'])) {
                $podParts[] = 'Signed by: ' . htmlspecialchars($row['pod_signed_by']);
            }
            if (!empty($row['pod_verified_at'])) {
                $podParts[] = 'Verified: ' . htmlspecialchars($row['pod_verified_at']);
            }
            if (!empty($row['pod_verification_notes'])) {
                $podParts[] = 'Notes: ' . htmlspecialchars($row['pod_verification_notes']);
            }

            $links = [];
            foreach ([
                'Photo 1' => $row['pod_photo1'] ?? '',
                'Photo 2' => $row['pod_photo2'] ?? '',
                'Photo 3' => $row['pod_photo3'] ?? '',
                'Signature' => $row['pod_signature'] ?? '',
            ] as $label => $path) {
                $href = pod_asset_href((string)$path);
                if ($href !== '') {
                    $links[] = '<a href="' . htmlspecialchars($href) . '" target="_blank" rel="noopener">' . htmlspecialchars($label) . '</a>';
                }
            }

            $podSummary = '<div class="d-flex align-items-center"><div class="ms-3">' .
                '<h6 class="mb-0 fw-bolder">' . htmlspecialchars($podStatus) . '</h6>' .
                (!empty($podParts) ? '<span class="text-muted">' . implode(' · ', $podParts) . '</span><br>' : '') .
                '<span class="text-muted">' . implode(' | ', $links) . '</span>' .
                '</div></div>';
        }
        $sub_array[] = $podSummary;

        // Time
        $sub_array[] = date('H:i', strtotime($row['d_datetime']));

        // Dispatcher
        $sub_array[] = htmlspecialchars((string)($row['dispatcher_display'] ?? $row['d_dispatcher']));

        // Action — Edit / Delete + approval-flow buttons (handled by js/trip-report.js).
        $dId = (int)$row['d_id'];
        $pending = $pendingByDid[$dId] ?? null;

        $actions = '<div class="d-flex gap-1 align-items-center flex-wrap">';
        $actions .= '<button type="button" class="btn btn-sm btn-primary trip-report-edit" data-d-id="' . $dId . '" title="Edit"><i class="ti ti-edit"></i></button>';

        if ($pending && $isApprover) {
            // Admin / Dispatch Admin viewing a row with a pending Dispatcher
            // request — show Approve + Reject in place of plain Delete.
            $requesterTip = htmlspecialchars(
                'Requested by ' . ($pending['requested_by_name'] ?: 'dispatcher') . ' on ' . $pending['requested_at'],
                ENT_QUOTES, 'UTF-8'
            );
            $actions .= '<button type="button" class="btn btn-sm btn-success trip-report-approve-delete" data-d-id="' . $dId . '" title="Approve deletion — ' . $requesterTip . '"><i class="ti ti-check"></i></button>';
            $actions .= '<button type="button" class="btn btn-sm btn-outline-secondary trip-report-reject-delete" data-d-id="' . $dId . '" title="Reject deletion request"><i class="ti ti-x"></i></button>';
            $actions .= '<span class="badge bg-warning text-dark" title="' . $requesterTip . '">Pending</span>';
        } elseif ($pending && $isDispatcher) {
            // Dispatcher viewing their own pending request — show the badge,
            // hide the delete button so they can't double-submit.
            $actions .= '<span class="badge bg-warning text-dark" title="Awaiting Admin / Dispatch Admin approval">Pending approval</span>';
        } else {
            // Default — show the Delete button. Backend gates create-vs-delete
            // by role, so this button is safe to render for all three roles.
            $actions .= '<button type="button" class="btn btn-sm btn-danger trip-report-delete" data-d-id="' . $dId . '" title="Delete"><i class="ti ti-trash"></i></button>';
        }
        $actions .= '</div>';
        $sub_array[] = $actions;

        $data[] = $sub_array;
    }
}

// -------------------------------
// JSON Response
// -------------------------------
$output = [
    "draw" => intval($_POST["draw"]),
    "recordsTotal" => $totalData,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
];

echo json_encode($output);
