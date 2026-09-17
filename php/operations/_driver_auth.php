<?php
// Phase 5 — small helper used by every driver-app endpoint.
// Usage:
//   require __DIR__ . '/_driver_auth.php';
//   $driverId = require_driver_session();   // exits with JSON error otherwise
//
// Side effect: installs a JSON-safety net (display_errors off + exception
// + shutdown handlers) so any fatal/uncaught throw bubbles up as a JSON
// 500 response instead of an HTML error page — keeps the frontend's
// JSON.parse(xhr.responseText) from silently degrading to "Network error".

if (!function_exists('pt_install_json_safety_net')) {
    function pt_install_json_safety_net(): void {
        static $installed = false;
        if ($installed) return;
        $installed = true;

        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        set_exception_handler(function (Throwable $e) {
            if (!headers_sent()) http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Server error: ' . $e->getMessage(),
                'where'   => basename($e->getFile()) . ':' . $e->getLine(),
            ]);
            exit;
        });
        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err && in_array($err['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                if (!headers_sent()) http_response_code(500);
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'Server error: ' . $err['message'],
                    'where'   => basename($err['file'] ?? '') . ':' . ($err['line'] ?? 0),
                ]);
            }
        });
    }
}

function require_driver_session(): int {
    pt_install_json_safety_net();
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (($_SESSION['user_type'] ?? '') !== 'Driver') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Driver session required']);
        exit;
    }
    return (int)($_SESSION['user_id'] ?? 0);
}

function require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'POST required']);
        exit;
    }
}

function json_out(array $payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
