<?php
/*
 * Google Maps JavaScript API key — single source of truth.
 *
 * New code should reference MAPS_API_KEY rather than hard-coding the
 * string. Consumers can include this file and emit the script tag via
 * the helper below, e.g.:
 *
 *     include 'php/config/maps.php';
 *     emit_maps_script();
 *
 * Existing pages that hard-code the key (driver/dashboard.php,
 * driver/trip-report.php, the per-client *monitoring.php pages under
 * admin/ and dispatcher/) still work — they were updated in-place
 * alongside this file and remain on the same key. Future rotations
 * only need to touch this constant; the in-page copies should be
 * migrated to call emit_maps_script() in a follow-up.
 *
 * NOTE: Do NOT use `// ... ?>` style comments here. PHP's `//` comment
 * terminates at the first `?>` it encounters, which would close PHP
 * mode mid-file. Use block comments (slash-star) for any example that
 * contains PHP tags.
 */

if (!defined('MAPS_API_KEY')) {
    define('MAPS_API_KEY', 'AIzaSyDi9dpeJZM1GkdSfovy2ufBWQZFabMrSRA');
}

if (!function_exists('emit_maps_script')) {
    function emit_maps_script(string $libraries = 'places', ?string $callback = null): void {
        $url = 'https://maps.googleapis.com/maps/api/js?key=' . urlencode(MAPS_API_KEY)
             . '&libraries=' . urlencode($libraries);
        if ($callback) {
            $url .= '&callback=' . urlencode($callback);
        }
        echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" async defer></script>' . "\n";
    }
}
