<?php
session_start();

require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/microsoft_auth.php";

function redirectMicrosoftError(string $message): void
{
    header("Location: login?error=" . urlencode($message));
    exit();
}

if (!microsoftAuthConfigured()) {
    redirectMicrosoftError("Microsoft sign-in is not configured yet");
}

if (isset($_GET["error"])) {
    $message = (string) ($_GET["error_description"] ?? $_GET["error"]);
    redirectMicrosoftError($message !== "" ? $message : "Microsoft sign-in failed");
}

$state = (string) ($_GET["state"] ?? "");
$expectedState = (string) ($_SESSION["microsoft_oauth_state"] ?? "");
unset($_SESSION["microsoft_oauth_state"]);

if ($state === "" || $expectedState === "" || !hash_equals($expectedState, $state)) {
    redirectMicrosoftError("Invalid Microsoft sign-in state");
}

$code = trim((string) ($_GET["code"] ?? ""));
if ($code === "") {
    redirectMicrosoftError("Microsoft sign-in code is missing");
}

try {
    $microsoftUser = getMicrosoftUserClaims($code);
} catch (Throwable $exception) {
    redirectMicrosoftError($exception->getMessage());
}

$email = $microsoftUser["email"]; // already lowercased by the helper

// 1) Look up in the user table (admins / dispatchers / HR / etc).
//    "user" is reserved in PostgreSQL.
$stmt = $conn->prepare('SELECT * FROM "user" WHERE LOWER(user_email) = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    if (($user["user_accountstat"] ?? "") === "Pending") {
        redirectMicrosoftError("Your account is pending approval.");
    }

    $_SESSION["user_id"]   = (int) $user["user_id"];
    $_SESSION["user_type"] = $user["user_type"];
    $_SESSION["user_name"] = $user["user_name"];
    $_SESSION["ms_authed"] = true;

    $dest = "dashboard";
    switch ($user["user_type"]) {
        case "Admin":          $dest = "dashboard";             break;
        case "Dispatcher":     $dest = "dispatch-dashboard";    break;
        case "Dispatch Admin": $dest = "dispatch-monitoring";   break;
        case "Booker":         $dest = "dispatch-addbook";      break;
        case "Shop":           $dest = "shop-dashboard";        break;
        case "User":           $dest = "hr-dashboard";          break;
        case "Rescue":         $dest = "shop-dashboard";        break;
        case "HR-Admin":       $dest = "hra-dashboard";         break;
        case "Visual":         $dest = "visual-dashboard";      break;
        case "Gate-Guard":     $dest = "gate-dashboard";        break;
        case "Maintenance":    $dest = "maintenance-dashboard"; break;
    }
    header("Location: " . $dest);
    exit();
}

// 2) Fall back to the drivers table — drivers sign in with their company
//    Microsoft email instead of registering.
$stmt = $conn->prepare("SELECT * FROM drivers WHERE LOWER(driver_email) = ? LIMIT 1");
$stmt->execute([$email]);
$driver = $stmt->fetch();

if (!$driver) {
    redirectMicrosoftError(
        "No local account is linked to this Microsoft work email: " . $email
    );
}

if (($driver["driver_account_status"] ?? "") === "Pending") {
    redirectMicrosoftError("Driver account is still pending approval.");
}

$_SESSION["user_id"]   = (int) $driver["driver_id"];
$_SESSION["user_type"] = "Driver";
$_SESSION["user_name"] = !empty($driver["driver_uname"]) ? $driver["driver_uname"] : $email;
$_SESSION["user_rfid"] = $driver["driver_rfid"] ?? "";
$_SESSION["ms_authed"] = true;

header("Location: driver-dashboard");
exit();
