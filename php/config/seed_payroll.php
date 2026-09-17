<?php
/**
 * Seed a Payroll user so the payroll workflow can be tested.
 * Re-running is safe — it only inserts if no row exists with
 * user_name = 'payroll'.
 */
require_once __DIR__ . '/config.php';

$username = 'payroll';
$password = 'payroll123';
$hash     = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare('SELECT user_id FROM "user" WHERE user_name = ? LIMIT 1');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    echo "Payroll user '$username' already exists — skipping insert.\n";
    exit(0);
}

$stmt = $conn->prepare(
    'INSERT INTO "user"
        (user_name, user_fname, user_lname, user_mname, user_assignlocation,
         user_email, user_pass, user_type, user_image, user_accountstat, user_code)
     VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     RETURNING user_id'
);
$stmt->execute([
    $username,        // user_name
    'Payroll',        // user_fname
    'Officer',        // user_lname
    'P',              // user_mname
    'PTSI Base',      // user_assignLocation
    'payroll@local',  // user_email
    $hash,            // user_pass
    'Payroll',        // user_type
    '',               // user_image
    'Active',         // user_accountStat
    2,                // user_code
]);
$id = $stmt->fetchColumn();

echo "Created Payroll user:\n";
echo "  user_id:   $id\n";
echo "  username:  $username\n";
echo "  password:  $password\n";
echo "  Login at:  http://localhost/Fleet%20Management%20New/login\n";
