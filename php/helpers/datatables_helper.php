<?php
// Shared safety net for table-fetch/*.php endpoints.
//
// Every DataTables server-side endpoint should `require_once` this file at
// the top. It installs an exception/shutdown handler that ALWAYS emits a
// valid DataTables JSON envelope:
//
//     { draw, recordsTotal, recordsFiltered, data, error? }
//
// Without this, an uncaught PDO/mysqli exception (or a fatal warning with
// display_errors=off) leaves the client with an empty/HTML response, which
// DataTables surfaces to the user as "DataTables warning: Ajax error".
//
// Usage:
//     include '../php/config/config.php';
//     require_once __DIR__ . '/../php/helpers/datatables_helper.php';
//     dt_install_safety_net();

if (!function_exists('dt_envelope')) {
    function dt_envelope(?string $error = null, array $data = [], int $totalRecords = 0, int $filteredRecords = 0): string {
        $envelope = [
            'draw'            => intval($_POST['draw'] ?? $_GET['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data,
        ];
        if ($error !== null) {
            $envelope['error'] = $error;
        }
        return json_encode($envelope);
    }
}

if (!function_exists('dt_install_safety_net')) {
    function dt_install_safety_net(): void {
        static $installed = false;
        if ($installed) return;
        $installed = true;

        // No HTML errors leaking into the JSON body.
        error_reporting(E_ALL);
        ini_set('display_errors', '0');

        // Best-effort error logging (don't crash if the dir is read-only).
        ini_set('log_errors', '1');

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        set_exception_handler(function (Throwable $e) {
            error_log('datatables endpoint exception: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            if (!headers_sent()) {
                // Use 200 so DataTables consumes the JSON envelope instead of
                // showing its generic Ajax error toast.
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo dt_envelope('Server error: ' . $e->getMessage());
            exit;
        });

        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err && in_array($err['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR], true)) {
                if (!headers_sent()) {
                    http_response_code(200);
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo dt_envelope('Server error: ' . ($err['message'] ?? 'unknown'));
            }
        });
    }
}

if (!function_exists('dt_safe_log_target')) {
    /**
     * Sets error_log destination only if the path is writable, otherwise
     * lets PHP fall back to its default sapi log.
     */
    function dt_safe_log_target(string $path): void {
        $dir = dirname($path);
        if (is_writable($dir) || (file_exists($path) && is_writable($path))) {
            ini_set('error_log', $path);
        }
    }
}
