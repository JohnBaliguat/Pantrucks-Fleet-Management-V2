<?php
require __DIR__ . '/../php/config/config.php';
$q = $conn->query("
    SELECT column_name, column_default, is_nullable, data_type
    FROM information_schema.columns
    WHERE table_name = 'booking'
      AND column_name IN ('booking_sn','booking_do','booking_haulingstartdate','return_location','status','quantity_use')
    ORDER BY column_name
");
foreach ($q->fetchAll() as $r) {
    echo json_encode($r) . "\n";
}
