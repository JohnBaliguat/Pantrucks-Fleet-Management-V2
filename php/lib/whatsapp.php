<?php
// WhatsApp notifications via the Meta (Facebook) WhatsApp Cloud API.
//
// Sends a driver a WhatsApp message when they get a violation. All config lives
// in app_settings (set from the Admin → Settings page), so the feature is a
// safe no-op until credentials are entered:
//   whatsapp_enabled          '1' to turn sending on
//   whatsapp_token            permanent access token (Bearer)
//   whatsapp_phone_number_id  the WhatsApp Business phone number id
//   whatsapp_template_name    (optional) approved template; blank = plain text
//   whatsapp_template_lang    template language code (default 'en_US')
//   whatsapp_default_country  country calling code for local numbers (default '63' = PH)
//
// Business-initiated messages to a driver who hasn't messaged the business
// number first require an APPROVED TEMPLATE — set whatsapp_template_name to use
// one. Plain text only works inside a 24h customer-service window.

require_once __DIR__ . '/../helpers/settings_helper.php';

if (!function_exists('pt_wa_ensure_log_table')) {
    function pt_wa_ensure_log_table(PDO $conn): void
    {
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS whatsapp_log (
                wl_id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                driver_id INTEGER DEFAULT NULL,
                to_phone VARCHAR(32) NOT NULL DEFAULT '',
                body VARCHAR(1000) NOT NULL DEFAULT '',
                status VARCHAR(20) NOT NULL DEFAULT '',   -- sent | failed | skipped
                context VARCHAR(40) NOT NULL DEFAULT '',   -- violation | violation_resend | ...
                response VARCHAR(2000) NOT NULL DEFAULT '',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }
}

if (!function_exists('pt_wa_enabled')) {
    function pt_wa_enabled(PDO $conn): bool
    {
        return pt_setting_bool($conn, 'whatsapp_enabled', false)
            && pt_setting_get($conn, 'whatsapp_token', '') !== ''
            && pt_setting_get($conn, 'whatsapp_phone_number_id', '') !== '';
    }
}

if (!function_exists('pt_wa_normalize_phone')) {
    // Turn a locally-typed number into E.164 digits (no '+').
    // Examples with default country 63 (PH):
    //   09171234567     -> 639171234567
    //   9171234567      -> 639171234567
    //   +63 917 123 4567-> 639171234567
    //   00639171234567  -> 639171234567
    function pt_wa_normalize_phone(string $raw, string $country = '63'): string
    {
        $d = preg_replace('/\D+/', '', $raw);
        if ($d === '') return '';
        if (strpos($d, '00') === 0) $d = substr($d, 2);          // 00 international prefix
        if (strpos($d, '0') === 0)  $d = $country . substr($d, 1); // local trunk 0
        elseif (strpos($d, $country) !== 0 && strlen($d) <= 10) {
            $d = $country . $d;                                   // bare local number
        }
        return $d;
    }
}

if (!function_exists('pt_wa_log')) {
    function pt_wa_log(PDO $conn, ?int $driverId, string $to, string $body, string $status, string $context, string $response = ''): void
    {
        try {
            pt_wa_ensure_log_table($conn);
            $conn->prepare(
                "INSERT INTO whatsapp_log (driver_id, to_phone, body, status, context, response)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([$driverId, $to, mb_substr($body, 0, 1000), $status, $context, mb_substr($response, 0, 2000)]);
        } catch (Throwable $e) { /* logging must never break the caller */ }
    }
}

if (!function_exists('pt_wa_send_text')) {
    /**
     * Low-level send. Returns ['ok'=>bool, 'skipped'=>bool, 'message'=>string].
     * @param string $rawPhone driver's stored contact number
     * @param string $body     message text (also used as the template body param)
     */
    function pt_wa_send_text(PDO $conn, ?int $driverId, string $rawPhone, string $body, string $context = 'violation'): array
    {
        if (!pt_wa_enabled($conn)) {
            pt_wa_log($conn, $driverId, $rawPhone, $body, 'skipped', $context, 'WhatsApp disabled/unconfigured');
            return ['ok' => false, 'skipped' => true, 'message' => 'WhatsApp is not enabled.'];
        }
        $country = pt_setting_get($conn, 'whatsapp_default_country', '63');
        $to = pt_wa_normalize_phone($rawPhone, $country !== '' ? $country : '63');
        if ($to === '') {
            pt_wa_log($conn, $driverId, $rawPhone, $body, 'skipped', $context, 'No/invalid contact number');
            return ['ok' => false, 'skipped' => true, 'message' => 'No contact number on file.'];
        }

        $token   = pt_setting_get($conn, 'whatsapp_token', '');
        $phoneId = pt_setting_get($conn, 'whatsapp_phone_number_id', '');
        $tmpl    = trim(pt_setting_get($conn, 'whatsapp_template_name', ''));
        $lang    = trim(pt_setting_get($conn, 'whatsapp_template_lang', '')) ?: 'en_US';

        if ($tmpl !== '') {
            // Template message — required for business-initiated sends.
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'   => $to,
                'type' => 'template',
                'template' => [
                    'name' => $tmpl,
                    'language' => ['code' => $lang],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [['type' => 'text', 'text' => mb_substr($body, 0, 1024)]],
                    ]],
                ],
            ];
        } else {
            // Plain text — only delivers inside a 24h session window.
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'   => $to,
                'type' => 'text',
                'text' => ['body' => mb_substr($body, 0, 4096)],
            ];
        }

        $url = 'https://graph.facebook.com/v21.0/' . rawurlencode($phoneId) . '/messages';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        $ok = ($code >= 200 && $code < 300);
        pt_wa_log($conn, $driverId, $to, $body, $ok ? 'sent' : 'failed', $context, $err !== '' ? $err : (string)$resp);
        return [
            'ok' => $ok,
            'skipped' => false,
            'message' => $ok ? 'WhatsApp message sent.' : ('WhatsApp send failed (HTTP ' . $code . ').'),
        ];
    }
}

if (!function_exists('pt_wa_violation_message')) {
    function pt_wa_violation_message(string $driverName, string $vrType, string $vrDescription): string
    {
        $name = $driverName !== '' ? $driverName : 'Driver';
        $msg  = "Hi {$name}, a violation has been recorded on your account: {$vrType}.";
        if (trim($vrDescription) !== '') $msg .= " Details: " . trim($vrDescription) . ".";
        $msg .= " You are blocked from dispatch until this is cleared. Please contact HR.";
        return $msg;
    }
}

if (!function_exists('pt_wa_send_violation')) {
    /**
     * Look up the driver's contact + name, build the violation notice, and send.
     * Best-effort — never throws. Returns the pt_wa_send_text() result array.
     */
    function pt_wa_send_violation(PDO $conn, int $driverId, string $vrType, string $vrDescription, string $context = 'violation'): array
    {
        try {
            $st = $conn->prepare(
                "SELECT driver_contact, CONCAT_WS(' ', driver_fname, driver_lname) AS name
                   FROM drivers WHERE driver_id = ? LIMIT 1"
            );
            $st->execute([$driverId]);
            $row = $st->fetch();
            if (!$row) return ['ok' => false, 'skipped' => true, 'message' => 'Driver not found.'];

            $contact = trim((string)($row['driver_contact'] ?? ''));
            $name    = trim((string)($row['name'] ?? ''));
            if ($contact === '') {
                pt_wa_log($conn, $driverId, '', pt_wa_violation_message($name, $vrType, $vrDescription), 'skipped', $context, 'Driver has no contact number');
                return ['ok' => false, 'skipped' => true, 'message' => 'No contact number on file — nothing sent.'];
            }
            $body = pt_wa_violation_message($name, $vrType, $vrDescription);
            return pt_wa_send_text($conn, $driverId, $contact, $body, $context);
        } catch (Throwable $e) {
            return ['ok' => false, 'skipped' => true, 'message' => 'WhatsApp error: ' . $e->getMessage()];
        }
    }
}
