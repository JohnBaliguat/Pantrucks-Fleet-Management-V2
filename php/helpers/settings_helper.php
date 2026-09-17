<?php
// App settings — a tiny key/value store the Admin can flip from the
// Settings page. First use: making driver photo uploads required or
// optional per capture type (POD / Gate / Jack-up / Breakdown).
//
// Follows the existing pt_ensure_* convention in config.php so the table
// is created lazily on first touch — no migration step needed.

require_once __DIR__ . '/../config/config.php';

if (!function_exists('pt_ensure_app_settings_table')) {
    function pt_ensure_app_settings_table(PDO $conn): void {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS app_settings (
                setting_key   VARCHAR(120) PRIMARY KEY,
                setting_value TEXT NOT NULL DEFAULT '',
                updated_by    INTEGER NULL,
                updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        pt_mark_table_exists('app_settings', true);
        $ensured = true;
    }
}

if (!function_exists('pt_setting_get')) {
    /**
     * Read a setting. Returns $default when the table/row is missing so callers
     * never have to special-case a fresh install.
     */
    function pt_setting_get(PDO $conn, string $key, string $default = ''): string {
        try {
            pt_ensure_app_settings_table($conn);
            $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return $val === false ? $default : (string)$val;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('pt_setting_set')) {
    function pt_setting_set(PDO $conn, string $key, string $value, ?int $userId = null): void {
        pt_ensure_app_settings_table($conn);
        $stmt = $conn->prepare(
            "INSERT INTO app_settings (setting_key, setting_value, updated_by, updated_at)
             VALUES (?, ?, ?, NOW())
             ON CONFLICT (setting_key)
             DO UPDATE SET setting_value = EXCLUDED.setting_value,
                           updated_by    = EXCLUDED.updated_by,
                           updated_at    = NOW()"
        );
        $stmt->execute([$key, $value, $userId]);
    }
}

if (!function_exists('pt_setting_bool')) {
    /**
     * Boolean read with a default. '1' / 'true' / 'on' / 'yes' are truthy.
     */
    function pt_setting_bool(PDO $conn, string $key, bool $default): bool {
        $raw = pt_setting_get($conn, $key, $default ? '1' : '0');
        return in_array(strtolower(trim($raw)), ['1', 'true', 'on', 'yes'], true);
    }
}

if (!function_exists('pt_photo_requirements')) {
    /**
     * The canonical list of admin-toggleable driver photo requirements.
     * Key => [label, default-required]. One place so the Settings page and
     * every enforcement point agree on the keys and defaults.
     */
    function pt_photo_requirements(): array {
        return [
            'require_pickup_photo'    => ['Container pickup photo',          true],
            'require_pod_photos'      => ['POD photos (proof of delivery)', true],
            'require_gate_photos'     => ['Gate (gateless) photos',          true],
            'require_jackup_photo'    => ['Trailer jack-up photo',           true],
            'require_breakdown_photo' => ['Breakdown photo',                 false],
        ];
    }
}

if (!function_exists('pt_other_requirements')) {
    /**
     * Non-photo "required input" toggles. Key => [label, description, default].
     * Rendered in the Settings "Required Inputs" section alongside the photos.
     */
    function pt_other_requirements(): array {
        return [
            'require_movement_timestamps' => [
                'Movement timestamps (manual completion)',
                'Require all four times (picked up / on the way / arrived / delivered) when a dispatcher manually completes a trip. When off, any blank time defaults to the completion time.',
                true,
            ],
        ];
    }
}

if (!function_exists('pt_feature_toggles')) {
    /**
     * Admin-toggleable dispatch features. Key => [label, description, default].
     * Same storage as photo requirements, just rendered in their own section.
     */
    function pt_feature_toggles(): array {
        return [
            'allow_offshift_assign' => [
                'Assign off-shift drivers',
                'Let dispatchers drop a booking on an off-shift driver and start their shift by entering a truck. When off, off-shift drivers are view-only.',
                true,
            ],
            'allow_inuse_equipment' => [
                'Dispatch in-use equipment',
                'Let dispatchers search and assign a truck, trailer, or genset that is already in use on another trip/driver. When off, only free equipment can be selected.',
                false,
            ],
        ];
    }
}
