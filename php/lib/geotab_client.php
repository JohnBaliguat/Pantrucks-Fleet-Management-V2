<?php
// =====================================================================
// MyGeotab JSON-RPC client (thin).
//
// MyGeotab has no webhooks — you poll. This wraps the two calls Phase 1
// needs: Authenticate (session, cached) and Get/GetFeed (data). The poller
// (php/operations/geotab_poll.php) and the device-mapping page use it.
//
// Protocol notes that shape this file:
//   * POST JSON to https://<server>/apiv1  (no query string).
//   * Authenticate returns credentials{sessionId,...} + a `path`. If `path`
//     is a hostname (e.g. "my3.geotab.com") the account's data lives on that
//     server and every later call must go there; "ThisServer" means stay put.
//   * A response carries either "result" or "error". An expired/invalid
//     session comes back as error name "InvalidUserException" — we re-auth
//     once and retry transparently.
//   * Sessions are long-lived; we cache the session to a temp file so the
//     60s poller isn't authenticating every run.
//
// Credentials come from php/config/geotab.php (git-ignored). See
// geotab.example.php.
// =====================================================================

// Load the legacy defines file (if present) so operators who set GEOTAB_* in
// php/config/geotab.php keep working, then fill in safe fallback defaults for
// the non-secret tuning constants so the pollers always have them — even when
// the connection is configured purely through the admin UI store.
(function () {
    $file = __DIR__ . '/../config/geotab.php';
    if (is_file($file)) { require_once $file; }
})();
if (!defined('GEOTAB_SERVER'))            define('GEOTAB_SERVER',            'my.geotab.com');
if (!defined('GEOTAB_DIAG_ODOMETER'))     define('GEOTAB_DIAG_ODOMETER',     'DiagnosticOdometerAdjustmentId');
if (!defined('GEOTAB_DIAG_ENGINE_HOURS')) define('GEOTAB_DIAG_ENGINE_HOURS', 'DiagnosticEngineHoursAdjustmentId');
if (!defined('GEOTAB_DIAG_TOTAL_FUEL'))   define('GEOTAB_DIAG_TOTAL_FUEL',   'DiagnosticDeviceTotalFuelId');
if (!defined('GEOTAB_FUEL_VARIANCE_PCT')) define('GEOTAB_FUEL_VARIANCE_PCT', 15);
if (!defined('GEOTAB_KM_VARIANCE_PCT'))   define('GEOTAB_KM_VARIANCE_PCT',   15);

if (!function_exists('pt_geotab_settings_path')) {
    // UI-managed connection store: a PHP file that RETURNS an array, so it is
    // executed (never served as text) and the password stays out of any
    // web-readable file. Written by the admin "Geotab Connection" page.
    function pt_geotab_settings_path(): string {
        return __DIR__ . '/../config/geotab.config.php';
    }
}

if (!function_exists('pt_geotab_load_settings')) {
    /** Current UI store as an array, or null if not saved yet. */
    function pt_geotab_load_settings(): ?array {
        $p = pt_geotab_settings_path();
        if (!is_file($p)) { return null; }
        $cfg = @include $p;
        return is_array($cfg) ? $cfg : null;
    }
}

if (!function_exists('pt_geotab_save_settings')) {
    /** Persist the UI store (var_export — plain strings, injection-safe). */
    function pt_geotab_save_settings(array $cfg): bool {
        $clean = [
            'enabled'  => !empty($cfg['enabled']),
            'server'   => trim((string)($cfg['server'] ?: 'my.geotab.com')),
            'database' => trim((string)($cfg['database'] ?? '')),
            'username' => trim((string)($cfg['username'] ?? '')),
            'password' => (string)($cfg['password'] ?? ''),
        ];
        $php = "<?php\n// Geotab API credentials — managed via the admin Geotab Connection page.\n"
             . "// Executed (never served as text); do not commit.\nreturn "
             . var_export($clean, true) . ";\n";
        $ok = @file_put_contents(pt_geotab_settings_path(), $php, LOCK_EX) !== false;
        if ($ok) { @chmod(pt_geotab_settings_path(), 0600); }
        return $ok;
    }
}

if (!function_exists('pt_geotab_is_configured')) {
    /** True when the connection is usable (UI store enabled+complete, or defines set). */
    function pt_geotab_is_configured(): bool {
        try { pt_geotab_config(); return true; }
        catch (Throwable $e) { return false; }
    }
}

if (!function_exists('pt_geotab_config')) {
    function pt_geotab_config(): array {
        static $cfg = null;
        if ($cfg !== null) {
            return $cfg;
        }

        // 1. UI store wins when enabled and complete.
        $s = pt_geotab_load_settings();
        if ($s && !empty($s['enabled'])
            && !empty($s['server']) && !empty($s['database'])
            && !empty($s['username']) && !empty($s['password'])) {
            $cfg = [
                'database' => (string)$s['database'],
                'server'   => (string)$s['server'],
                'user'     => (string)$s['username'],
                'password' => (string)$s['password'],
            ];
            return $cfg;
        }

        // 2. Fall back to legacy defines in php/config/geotab.php.
        if (defined('GEOTAB_DATABASE') && defined('GEOTAB_USER') && defined('GEOTAB_PASSWORD')
            && GEOTAB_DATABASE !== '' && GEOTAB_DATABASE !== 'your_database_name'
            && GEOTAB_PASSWORD !== '' && GEOTAB_PASSWORD !== 'CHANGE_ME') {
            $cfg = [
                'database' => GEOTAB_DATABASE,
                'server'   => defined('GEOTAB_SERVER') ? GEOTAB_SERVER : 'my.geotab.com',
                'user'     => GEOTAB_USER,
                'password' => GEOTAB_PASSWORD,
            ];
            return $cfg;
        }

        throw new RuntimeException(
            'Geotab not configured: set the connection on the admin Geotab Connection page.'
        );
    }
}

if (!function_exists('pt_geotab_cache_path')) {
    function pt_geotab_cache_path(): string {
        $cfg = pt_geotab_config();
        // Per database+user so switching accounts doesn't reuse a stale session.
        $key = substr(sha1($cfg['database'] . '|' . $cfg['user']), 0, 16);
        return sys_get_temp_dir() . '/pt_geotab_session_' . $key . '.json';
    }
}

if (!function_exists('pt_geotab_load_session')) {
    function pt_geotab_load_session(): ?array {
        $path = pt_geotab_cache_path();
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['credentials']['sessionId']) || empty($data['server'])) {
            return null;
        }
        return $data;
    }
}

if (!function_exists('pt_geotab_save_session')) {
    function pt_geotab_save_session(string $server, array $credentials): void {
        $path = pt_geotab_cache_path();
        @file_put_contents(
            $path,
            json_encode(['server' => $server, 'credentials' => $credentials]),
            LOCK_EX
        );
        @chmod($path, 0600);
    }
}

if (!function_exists('pt_geotab_clear_session')) {
    function pt_geotab_clear_session(): void {
        @unlink(pt_geotab_cache_path());
    }
}

if (!function_exists('pt_geotab_rpc')) {
    /**
     * One raw JSON-RPC POST to a Geotab server. Returns the decoded envelope
     * (['result'=>..] or ['error'=>..]). Throws on transport failure.
     */
    function pt_geotab_rpc(string $server, string $method, array $params): array {
        $url  = 'https://' . $server . '/apiv1';
        $body = json_encode(['method' => $method, 'params' => $params]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Geotab transport error ($method): $err");
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) {
            $preview = substr((string)$resp, 0, 200);
            throw new RuntimeException("Geotab bad response ($method, HTTP $status): $preview");
        }
        return $decoded;
    }
}

if (!function_exists('pt_geotab_authenticate')) {
    /**
     * Authenticate and cache the session. Follows the server redirect in the
     * `path` field. Returns ['server'=>.., 'credentials'=>[..]].
     */
    function pt_geotab_authenticate(bool $force = false): array {
        if (!$force) {
            $cached = pt_geotab_load_session();
            if ($cached !== null) {
                return $cached;
            }
        }
        $cfg = pt_geotab_config();
        $resp = pt_geotab_rpc($cfg['server'], 'Authenticate', [
            'database' => $cfg['database'],
            'userName' => $cfg['user'],
            'password' => $cfg['password'],
        ]);
        if (isset($resp['error'])) {
            $msg = $resp['error']['message'] ?? json_encode($resp['error']);
            throw new RuntimeException("Geotab authentication failed: $msg");
        }
        $result = $resp['result'] ?? null;
        if (!is_array($result) || empty($result['credentials']['sessionId'])) {
            throw new RuntimeException('Geotab authentication returned no session.');
        }

        // Resolve the data server. `path` = "ThisServer" → stay on the login
        // host; otherwise it's the hostname to use for all subsequent calls.
        $path   = (string)($result['path'] ?? 'ThisServer');
        $server = ($path === '' || $path === 'ThisServer') ? $cfg['server'] : $path;

        pt_geotab_save_session($server, $result['credentials']);
        return ['server' => $server, 'credentials' => $result['credentials']];
    }
}

if (!function_exists('pt_geotab_call')) {
    /**
     * Authenticated call. Injects cached credentials, and on an invalid/expired
     * session re-authenticates once and retries. Returns the `result` payload.
     */
    function pt_geotab_call(string $method, array $params) {
        $session = pt_geotab_authenticate();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $params['credentials'] = $session['credentials'];
            $resp = pt_geotab_rpc($session['server'], $method, $params);

            if (!isset($resp['error'])) {
                return $resp['result'] ?? null;
            }

            // Session expired / invalid → drop cache, re-auth, retry once.
            $name = $resp['error']['errors'][0]['name'] ?? ($resp['error']['name'] ?? '');
            if ($attempt === 0 && stripos($name, 'InvalidUserException') !== false) {
                pt_geotab_clear_session();
                $session = pt_geotab_authenticate(true);
                continue;
            }
            $msg = $resp['error']['message'] ?? json_encode($resp['error']);
            throw new RuntimeException("Geotab $method failed: $msg");
        }
        throw new RuntimeException("Geotab $method failed after re-authentication.");
    }
}

if (!function_exists('pt_geotab_get')) {
    /**
     * Get() — fetch entities of a type, optional search filter and row cap.
     * Returns an array of records (possibly empty).
     */
    function pt_geotab_get(string $typeName, array $search = [], ?int $resultsLimit = null): array {
        $params = ['typeName' => $typeName];
        if ($search) {
            $params['search'] = $search;
        }
        if ($resultsLimit !== null) {
            $params['resultsLimit'] = $resultsLimit;
        }
        $result = pt_geotab_call('Get', $params);
        return is_array($result) ? $result : [];
    }
}

if (!function_exists('pt_geotab_get_feed')) {
    /**
     * GetFeed() — incremental pull. Pass the previous toVersion as $fromVersion
     * (null on first run). Returns ['data'=>[...], 'toVersion'=>'...'].
     */
    function pt_geotab_get_feed(string $typeName, ?string $fromVersion = null): array {
        $params = ['typeName' => $typeName];
        if ($fromVersion !== null && $fromVersion !== '') {
            $params['fromVersion'] = $fromVersion;
        }
        $result = pt_geotab_call('GetFeed', $params);
        return [
            'data'      => (is_array($result) && isset($result['data']) && is_array($result['data'])) ? $result['data'] : [],
            'toVersion' => (is_array($result) && isset($result['toVersion'])) ? (string)$result['toVersion'] : $fromVersion,
        ];
    }
}
