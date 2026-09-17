<?php
// Centralized authentication / authorization guard.
//
// Per-page role checks were previously copy-pasted (and often forgotten) across
// the codebase, which is a privilege-escalation risk: a missing check on a new
// endpoint = an open door. Include this once at the very top of any page or
// endpoint that must not be public:
//
//     require_once __DIR__ . '/../helpers/auth_guard.php';   // adjust depth
//     require_login();                    // any signed-in user
//     require_role(['Admin', 'HR-Admin']); // specific roles only
//
// For JSON/AJAX endpoints, pass 'json' so a rejection returns a 401/403 JSON
// body instead of an HTML redirect:
//
//     require_login('json');
//     require_role(['Admin'], 'json');
//
// Roles are the user_type values set at login (see login-php.php): Admin,
// Dispatcher, Dispatch Admin, Booker, Shop, Rescue, User, HR-Admin, Visual,
// Gate-Guard, Maintenance, Client, Gastender, Payroll, Driver, Executive.

if (!function_exists('pt_auth_boot_session')) {
    function pt_auth_boot_session(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('pt_auth_reject')) {
    function pt_auth_reject(string $mode, int $status, string $message): void {
        http_response_code($status);
        if ($mode === 'json') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $message]);
        } else {
            // Redirect browsers back to the login form.
            header('Location: /login.php?error=' . rawurlencode($message));
        }
        exit;
    }
}

if (!function_exists('require_login')) {
    /**
     * Ensure a user is signed in. $mode 'html' (default) redirects to login;
     * 'json' returns a 401 JSON body. Returns the user_id on success.
     */
    function require_login(string $mode = 'html') {
        pt_auth_boot_session();
        if (empty($_SESSION['user_id'])) {
            pt_auth_reject($mode, 401, 'Please sign in to continue.');
        }
        return $_SESSION['user_id'];
    }
}

if (!function_exists('require_role')) {
    /**
     * Ensure the signed-in user's role is one of $roles (case-sensitive, matching
     * the stored user_type). $mode 'html' redirects, 'json' returns 403 JSON.
     * Returns the current user_type on success.
     */
    function require_role(array $roles, string $mode = 'html'): string {
        require_login($mode);
        $type = (string)($_SESSION['user_type'] ?? '');
        if (!in_array($type, $roles, true)) {
            pt_auth_reject($mode, 403, 'You do not have access to this resource.');
        }
        return $type;
    }
}

if (!function_exists('current_user_type')) {
    function current_user_type(): string {
        pt_auth_boot_session();
        return (string)($_SESSION['user_type'] ?? '');
    }
}
