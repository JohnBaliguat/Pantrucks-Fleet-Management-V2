<?php
// Phase 6 — outbound notification dispatcher.
//
// Three channels: webpush, email, sms. Each call lands in
// push_send_log so we have a full audit even when the channel
// can't actually deliver (e.g. no VAPID configured, no SMTP setup).
//
// Usage:
//   require_once __DIR__ . '/_push_send.php';
//   pt_notify_driver($conn, $driverId, 'Accepted', 'You accepted booking X.', $dId);
//   pt_notify_dispatchers($conn, 'Incident', 'High-severity incident on PM651', $dId);
//   pt_notify_client($conn, $customerCode, 'Delivered', 'Your shipment has arrived', $dId);

require_once __DIR__ . '/../config/config.php';

if (!function_exists('pt_log_push')) {
    function pt_log_push(PDO $conn, string $toRole, ?int $toId, string $channel, string $subject, string $body, ?int $dId, string $outcome, string $error = ''): void {
        $stmt = $conn->prepare(
            "INSERT INTO push_send_log (to_role, to_id, channel, subject, body, d_id, outcome, error)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) return;
        $stmt->execute([$toRole, $toId, $channel, $subject, $body, $dId, $outcome, $error]);
}
}

if (!function_exists('pt_load_vapid')) {
    function pt_load_vapid(): array {
        $f = __DIR__ . '/../config/vapid.php';
        if (!file_exists($f)) return [];
        @include_once $f;
        if (!defined('VAPID_PUBLIC_KEY') || !defined('VAPID_PRIVATE_KEY')) return [];
        if (VAPID_PUBLIC_KEY === 'PASTE_PUBLIC_KEY_HERE' || VAPID_PRIVATE_KEY === 'PASTE_PRIVATE_KEY_HERE') return [];
        return [
            'subject'    => defined('VAPID_SUBJECT') ? VAPID_SUBJECT : 'mailto:ops@example.com',
            'publicKey'  => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ];
    }
}

if (!function_exists('pt_send_webpush')) {
    function pt_send_webpush(PDO $conn, int $driverId, string $subject, string $body, ?int $dId): void {
        // Look up active subscriptions.
        $stmt = $conn->prepare("SELECT endpoint, p256dh_key, auth_key FROM push_subscription WHERE driver_id = ?");
        $stmt->execute([$driverId]);
        $subs = $stmt->fetchAll();
if (empty($subs)) {
            pt_log_push($conn, 'driver', $driverId, 'webpush', $subject, $body, $dId, 'skipped', 'No subscriptions');
            return;
        }
        $vapid = pt_load_vapid();
        $hasLib = class_exists('Minishlink\\WebPush\\WebPush');
        if (!$vapid || !$hasLib) {
            // Log the intent but don't actually send. Phase 6 ships the
            // schema + scaffolding; ops can install minishlink/web-push
            // and configure VAPID to enable real delivery.
            pt_log_push($conn, 'driver', $driverId, 'webpush', $subject, $body, $dId, 'queued',
                $vapid ? 'minishlink/web-push not installed' : 'VAPID not configured');
            return;
        }
        try {
            $webPush = new \Minishlink\WebPush\WebPush(['VAPID' => $vapid]);
            $payload = json_encode(['title' => $subject, 'body' => $body, 'url' => 'driver-dashboard']);
            foreach ($subs as $s) {
                $sub = \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $s['endpoint'],
                    'publicKey' => $s['p256dh_key'],
                    'authToken' => $s['auth_key'],
                ]);
                $webPush->queueNotification($sub, $payload);
            }
            $errors = '';
            foreach ($webPush->flush() as $report) {
                if (!$report->isSuccess()) {
                    $errors .= $report->getReason() . '; ';
                }
            }
            pt_log_push($conn, 'driver', $driverId, 'webpush', $subject, $body, $dId,
                $errors ? 'failed' : 'sent', $errors);
        } catch (\Throwable $e) {
            pt_log_push($conn, 'driver', $driverId, 'webpush', $subject, $body, $dId, 'failed', substr($e->getMessage(), 0, 480));
        }
    }
}

if (!function_exists('pt_send_email')) {
    function pt_send_email(PDO $conn, string $to, string $toRole, string $subject, string $body, ?int $dId): void {
        // Use PHP's built-in mail(). For prod use, swap in PHPMailer or
        // an SMTP-backed library.
        $headers = "From: Pantrucks Fleet <no-reply@pantrucks.local>\r\n"
                 . "Reply-To: ops@pantrucks.local\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n";
        $ok = @mail($to, $subject, $body, $headers);
        pt_log_push($conn, $toRole, null, 'email', $subject, "$to | $body", $dId, $ok ? 'sent' : 'failed', $ok ? '' : 'mail() returned false');
    }
}

if (!function_exists('pt_notify_driver')) {
    function pt_notify_driver(PDO $conn, int $driverId, string $subject, string $body, ?int $dId = null): void {
        pt_send_webpush($conn, $driverId, $subject, $body, $dId);
    }
}

if (!function_exists('pt_notify_dispatchers')) {
    function pt_notify_dispatchers(PDO $conn, string $subject, string $body, ?int $dId = null): void {
        // Web-push for dispatchers is out of scope for Phase 6 (no
        // dispatcher PWA); we just write the audit row so a real-time
        // panel can read it.
        pt_log_push($conn, 'dispatcher', null, 'inapp', $subject, $body, $dId, 'queued', '');
    }
}

if (!function_exists('pt_notify_client')) {
    function pt_notify_client(PDO $conn, string $customerCode, string $subject, string $body, ?int $dId = null): void {
        // Look up notify_email + notify_phone from customer master.
        $stmt = $conn->prepare("SELECT notify_email, notify_phone FROM customer WHERE customer_code = ? LIMIT 1");
        $stmt->execute([$customerCode]);
        $r = $stmt->fetch();
if (!$r) {
            pt_log_push($conn, 'client', null, 'email', $subject, "(no customer match: $customerCode) " . $body, $dId, 'skipped', 'unknown customer code');
            return;
        }
        if (!empty($r['notify_email'])) {
            pt_send_email($conn, $r['notify_email'], 'client', $subject, $body, $dId);
        } else {
            pt_log_push($conn, 'client', null, 'email', $subject, $body, $dId, 'skipped', 'no notify_email');
        }
        if (!empty($r['notify_phone'])) {
            // SMS gateway is out of scope. Log intent so it's auditable.
            pt_log_push($conn, 'client', null, 'sms', $subject, $r['notify_phone'] . ' | ' . $body, $dId, 'skipped', 'No SMS gateway configured');
        }
    }
}
