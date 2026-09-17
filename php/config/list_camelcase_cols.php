<?php
require_once __DIR__ . '/config.php';
$rows = $conn->query(
    "SELECT table_name, column_name
     FROM information_schema.columns
     WHERE table_schema='public' AND column_name <> lower(column_name)
     ORDER BY table_name, column_name"
)->fetchAll();
echo count($rows) . " camelCase columns total\n";
foreach ($rows as $r) echo "  {$r['table_name']}.{$r['column_name']}\n";
