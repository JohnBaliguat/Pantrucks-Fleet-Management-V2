<?php
// Web Push (VAPID) keys — copy this file to vapid.php and fill in values.
//
// Generate keys with one of:
//   $ npx web-push generate-vapid-keys
//   PHP:   require 'vendor/autoload.php'; $k = Minishlink\WebPush\VAPID::createVapidKeys();
//
// `vapid.php` is intentionally NOT committed to git. The driver PWA
// reads VAPID_PUBLIC_KEY at page-render time; the server-side push
// dispatcher (TODO Phase 6) will read VAPID_PRIVATE_KEY + VAPID_SUBJECT.

if (!defined('VAPID_PUBLIC_KEY'))  define('VAPID_PUBLIC_KEY',  'PASTE_PUBLIC_KEY_HERE');
if (!defined('VAPID_PRIVATE_KEY')) define('VAPID_PRIVATE_KEY', 'PASTE_PRIVATE_KEY_HERE');
if (!defined('VAPID_SUBJECT'))     define('VAPID_SUBJECT',     'mailto:ops@example.com');
