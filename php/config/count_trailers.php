<?php
require_once __DIR__ . '/config.php';
echo "trailer count: " . $conn->query("SELECT COUNT(*) FROM trailer")->fetchColumn() . "\n";
echo "drivers count: " . $conn->query("SELECT COUNT(*) FROM drivers")->fetchColumn() . "\n";
echo "units count:   " . $conn->query("SELECT COUNT(*) FROM units")->fetchColumn() . "\n";
