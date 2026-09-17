<?php
include '../php/config/config.php';

// Count utilized trailers (those that have history)
$utilizedResult = $conn->query("
    SELECT COUNT(DISTINCT tm_trailername) AS utilized
    FROM trailer_movement
");
$utilized = $utilizedResult->fetch()['utilized'] ?? 0;

// Count total trailers
$totalResult = $conn->query("SELECT COUNT(*) AS total FROM trailers");
$total = $totalResult->fetch()['total'] ?? 0;

// Compute unutilized
$unutilized = $total - $utilized;

// Compute percentages safely
$utilizedPercent = ($total > 0) ? round(($utilized / $total) * 100, 1) : 0;
$unutilizedPercent = ($total > 0) ? round(($unutilized / $total) * 100, 1) : 0;

// Return JSON
echo json_encode([
    'utilized' => $utilized,
    'unutilized' => $unutilized,
    'utilizedPercent' => $utilizedPercent,
    'unutilizedPercent' => $unutilizedPercent,
    'total' => $total
]);
?>
