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

if (!function_exists('pt_geotab_config')) {
    function pt_geotab_config(): array {
        static $cfg = null;
        if ($cfg !== null) {
            return $cfg;
        }
        $file = __DIR__ . '/../config/geotab.php';
        if (!is_file($file)) {
            throw new RuntimeException(
                'Geotab not configured: copy php/config/geotab.example.php to geotab.php and fill it in.'
            );
        }
        require_once $file;
        foreach (['GEOTAB_DATABASE', 'GEOTAB_SERVER', 'GEOTAB_USER', 'GEOTAB_PASSWORD'] as $c) {
            if (!defined($c) || constant($c) === '' || constant($c) === 'CHANGE_ME') {
                throw new RuntimeException("Geotab config incomplete: $c is not set.");
            }
        }
        $cfg = [
            'database' => GEOTAB_DATABASE,
            'server'   => GEOTAB_SERVER,
            'user'     => GEOTAB_USER,
            'password' => GEOTAB_PASSWORD,
        ];
        return $cfg;
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
