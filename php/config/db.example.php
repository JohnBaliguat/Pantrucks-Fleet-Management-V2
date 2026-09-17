<?php
// Template for php/config/db.php. Copy this file to db.php and fill in the real
// values, OR provide them via environment variables (PT_DB_DSN / PT_DB_USER /
// PT_DB_PASS), which take precedence. db.php is git-ignored; this example is not.
return [
    'dsn'  => 'pgsql:host=YOUR_HOST;port=5432;dbname=postgres',
    'user' => 'YOUR_DB_USER',
    'pass' => 'YOUR_DB_PASSWORD',
];
