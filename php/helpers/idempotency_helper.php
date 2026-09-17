<?php
// Idempotency helper — prevents duplicate writes when the driver app
// retries a submission (offline-queue replay, flaky network, accidental
// double-tap). Each driver submit sends an `idempotency_key` (UUID); on
// arrival we check the log table and short-circuit with the cached
// response if we've already processed that key.

if (!function_exists('pt_ensure_idempotency_log_table')) {
    function pt_ensure_idempotency_log_table(PDO $conn): void {
        static $ensured = false;
        if ($ensured) return;
        try {
            $conn->exec(
                "CREATE TABLE IF NOT EXISTS pt_idempotency_log (
                    idem_key VARCHAR(80) PRIMARY KEY,
                    endpoint VARCHAR(255) NOT NULL,
                    actor_id INTEGER NULL,
                    response_status INTEGER NOT NULL DEFAULT 200,
                    response_body TEXT NOT NULL DEFAULT '',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )"
            );
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_idem_log_created ON pt_idempotency_log (created_at)");
        } catch (Throwable $e) {
            // Table creation must never block the actual request.
        }
        $ensured = true;
    }
}

if (!function_exists('idempotency_extract_key')) {
    function idempotency_extract_key(): string {
        $raw = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($raw === '') return '';
        // Accept UUIDs / short tokens only; cap length and strip noise.
        $raw = preg_replace('/[^A-Za-z0-9_\-]/', '', $raw);
        if (strlen($raw) > 80) $raw = substr($raw, 0, 80);
        return $raw;
    }
}

if (!function_exists('idempotency_lookup')) {
    /**
     * If this key has already been processed, return its cached response
     * as ['status' => int, 'body' => string] so the caller can replay it.
     * Returns null if the key is new (and the caller should proceed).
     */
    function idempotency_lookup(PDO $conn, string $key): ?array {
        if ($key === '') return null;
        pt_ensure_idempotency_log_table($conn);
        try {
            $stmt = $conn->prepare("SELECT response_status, response_body FROM pt_idempotency_log WHERE idem_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            if (!$row) return null;
            return [
                'status' => (int)$row['response_status'],
                'body'   => (string)$row['response_body'],
            ];
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('idempotency_record')) {
    /**
     * Persist the response for a key so future replays return the same thing.
     */
    function idempotency_record(PDO $conn, string $key, string $endpoint, ?int $actorId, int $status, string $body): void {
        if ($key === '') return;
        pt_ensure_idempotency_log_table($conn);
        try {
            // PostgreSQL upsert; ignores duplicate keys (rare race).
            $stmt = $conn->prepare(
                "INSERT INTO pt_idempotency_log (idem_key, endpoint, actor_id, response_status, response_body)
                 VALUES (?, ?, ?, ?, ?)
                 ON CONFLICT (idem_key) DO NOTHING"
            );
            $stmt->execute([$key, $endpoint, $actorId, $status, $body]);
        } catch (Throwable $e) {
            // Logging the response is best-effort — never fail the request.
        }
    }
}

if (!function_exists('idempotency_json_out')) {
    /**
     * Drop-in replacement for json_out() that also persists the response
     * against the idempotency key so future replays return the same thing.
     * Pass key='' (or skip) to behave as a plain json_out.
     */
    function idempotency_json_out(?PDO $conn, string $key, string $endpoint, ?int $actorId, array $payload, int $code = 200): void {
        $body = json_encode($payload);
        if ($key !== '' && $conn !== null && $code >= 200 && $code < 300) {
            idempotency_record($conn, $key, $endpoint, $actorId, $code, $body);
        }
        http_response_code($code);
        header('Content-Type: application/json');
        echo $body;
        exit;
    }
}

if (!function_exists('idempotency_replay_and_exit')) {
    /**
     * Convenience: if the current request's idempotency_key was processed
     * before, emit the cached response (with original status code) and exit.
     * Returns the key (or '') so the caller can pass it to idempotency_record
     * after the real work succeeds.
     */
    function idempotency_replay_and_exit(PDO $conn): string {
        $key = idempotency_extract_key();
        if ($key === '') return '';
        $hit = idempotency_lookup($conn, $key);
        if ($hit === null) return $key;
        http_response_code($hit['status']);
        header('Content-Type: application/json');
        header('X-Idempotency-Replay: 1');
        echo $hit['body'];
        exit;
    }
}
