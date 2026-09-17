<?php
// CSRF protection helper.
//
// The app currently has no CSRF defense: any state-changing POST can be forged
// from another site against a logged-in staff member. Roll this out gradually:
//
//   1. On any page that renders a form or issues AJAX POSTs, print the token:
//        <?php require_once __DIR__ . '/php/helpers/csrf_helper.php'; ?>
//        <meta name="csrf-token" content="<?= csrf_token() ?>">
//      (or a hidden field: <?= csrf_field() ?>)
//
//   2. Send it with every request. For jQuery AJAX, once per page:
//        $.ajaxSetup({ headers: { 'X-CSRF-Token':
//            $('meta[name=csrf-token]').attr('content') } });
//
//   3. At the top of each POST handler, verify it:
//        require_once __DIR__ . '/../helpers/csrf_helper.php';
//        csrf_verify();          // exits 419 on mismatch
//
// The token is per session and constant-time compared.

if (!function_exists('pt_csrf_boot_session')) {
    function pt_csrf_boot_session(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        pt_csrf_boot_session();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }
}

if (!function_exists('csrf_check')) {
    /**
     * Non-fatal check: returns true when the submitted token matches. Reads the
     * token from the X-CSRF-Token header or a csrf_token POST field.
     */
    function csrf_check(): bool {
        pt_csrf_boot_session();
        $expected = $_SESSION['csrf_token'] ?? '';
        if ($expected === '') {
            return false;
        }
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? ($_POST['csrf_token'] ?? '');
        return is_string($sent) && hash_equals($expected, $sent);
    }
}

if (!function_exists('csrf_verify')) {
    /**
     * Fatal check for POST handlers: only enforces on unsafe methods, exits with
     * 419 (token mismatch) on failure. $mode 'json' returns a JSON body.
     */
    function csrf_verify(string $mode = 'json'): void {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        if (!csrf_check()) {
            http_response_code(419);
            if ($mode === 'json') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Session expired or invalid request token. Please reload the page.']);
            } else {
                echo 'Invalid or expired request token. Please reload the page and try again.';
            }
            exit;
        }
    }
}
