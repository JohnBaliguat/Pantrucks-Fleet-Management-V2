<?php
/**
 * API endpoint for Reefer Container Monitoring.
 *
 * GET  -> Returns JSON array of container records.
 * POST -> Updates a container record (status/stages/remarks).
 *
 * Note: This file assumes the host session already uses user authentication.
 */

header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
  http_response_code(403);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

include __DIR__ . '../../php/config/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $sql = "SELECT * FROM container_monitoring ORDER BY id ASC";
  $result = $conn->query($sql);
  if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'details' => ($conn->errorInfo()[2] ?? '')]);
    exit;
  }

  $items = [];
  while ($row = $result->fetch()) {
    $items[] = [
      'id' => (int) $row['id'],
      'van' => $row['container_number'] ?? '',
      'pm' => $row['pm_number'] ?? '',
      'line' => $row['shipping_line'] ?? '',
      'ph' => $row['location_tag'] ?? '',
      'status' => $row['status'] ?? '',
      'priority' => $row['priority'] ?? 'medium',
      'reference' => $row['reference'] ?? '',
      'tr_gs' => $row['tr_gs'] ?? '',
      'driver' => $row['driver_name'] ?? '',
      'driver_contact' => $row['driver_contact'] ?? '',
      'etd' => $row['etd'] ?? null,
      'seal_number' => $row['seal_number'] ?? '',
      'vessel_etd' => $row['vessel_etd'] ?? null,
      'week' => $row['week_of_year'] ?? null,
      'customer' => $row['customer'] ?? '',
      'remarks' => $row['remarks'] ?? '',
      'last_update' => $row['updated_at'] ?? $row['created_at'] ?? null,
      'stages' => [
        'cy-arrival' => [
          'completed' => !empty($row['cy_arrival']),
          'date' => $row['cy_arrival'] ? date('Y-m-d', strtotime($row['cy_arrival'])) : '',
          'time' => $row['cy_arrival'] ? date('H:i', strtotime($row['cy_arrival'])) : ''
        ],
        'cy-departure' => [
          'completed' => !empty($row['cy_departure']),
          'date' => $row['cy_departure'] ? date('Y-m-d', strtotime($row['cy_departure'])) : '',
          'time' => $row['cy_departure'] ? date('H:i', strtotime($row['cy_departure'])) : ''
        ],
        'ph-arrival' => [
          'completed' => !empty($row['ph_arrival']),
          'date' => $row['ph_arrival'] ? date('Y-m-d', strtotime($row['ph_arrival'])) : '',
          'time' => $row['ph_arrival'] ? date('H:i', strtotime($row['ph_arrival'])) : ''
        ],
        'ptsi' => [
          'completed' => !empty($row['ptsi']),
          'date' => $row['ptsi'] ? date('Y-m-d', strtotime($row['ptsi'])) : '',
          'time' => $row['ptsi'] ? date('H:i', strtotime($row['ptsi'])) : ''
        ],
      ],
    ];
  }

  echo json_encode(['data' => $items]);
  exit;
}

if ($method === 'POST') {
  $payload = json_decode(file_get_contents('php://input'), true);
  if (!$payload || !isset($payload['container_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
  }

  $id = (int) $payload['container_id'];
  $status = pt_pg_escape($conn, $payload['status'] ?? '');
  $remarks = pt_pg_escape($conn, $payload['remarks'] ?? '');

  $updates = [];
  if ($status !== '') {
    $updates[] = "status = '$status'";
  }
  $updates[] = "remarks = '$remarks'";

  // Update stage timestamps if provided
  if (!empty($payload['stages']) && is_array($payload['stages'])) {
    foreach (['cy-arrival', 'cy-departure', 'ph-arrival', 'ptsi'] as $stage) {
      if (isset($payload['stages'][$stage])) {
        $stageData = $payload['stages'][$stage];
        $date = trim($stageData['date'] ?? '');
        $time = trim($stageData['time'] ?? '');
        if ($date !== '' && $time !== '') {
          $dt = pt_pg_escape($conn, $date . ' ' . $time);
          // Map stage name to DB column
          $col = str_replace('-', '_', $stage);
          $updates[] = "$col = '$dt'";
        } elseif ($date === '' && $time === '') {
          $col = str_replace('-', '_', $stage);
          $updates[] = "$col = NULL";
        }
      }
    }
  }

  if (count($updates) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Nothing to update']);
    exit;
  }

  $sql = "UPDATE container_monitoring SET " . implode(', ', $updates) . " WHERE id = $id";
  if ($conn->query($sql) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update', 'details' => ($conn->errorInfo()[2] ?? '')]);
    exit;
  }

  echo json_encode(['success' => true]);
  exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit;
