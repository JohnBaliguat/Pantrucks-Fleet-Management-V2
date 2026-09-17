<?php
// Shared driver-app page shell. Caller already auth'd as Driver.
$pageTitle = $pageTitle ?? 'Pantrucks Driver';
$activeNav = $activeNav ?? 'home';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  <link rel="manifest" href="manifest.webmanifest">
  <meta name="theme-color" content="#0d6efd">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="apple-touch-icon" href="assets/images/logos/LogoFleet.png">
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="assets/css/driver-modern.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  <!-- JS deps loaded HERE (head) so inline page-body scripts can use $ / Swal / bootstrap. -->
  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script src="driver/driver-upload.js?v=<?php echo @filemtime(__DIR__ . '/driver-upload.js') ?: time(); ?>"></script>
  <!-- Driver location gate: blocks the UI until Location permission is granted,
       then runs a 30s GPS heartbeat so dispatch sees a live position. -->
  <script src="js/driver-location-gate.js?v=<?php echo @filemtime(__DIR__ . '/../js/driver-location-gate.js') ?: time(); ?>"></script>
  <script>
    // Defensive polyfills in case a stale browser/SW cache served an older
    // driver-upload.js that doesn't have the helpers the page expects.
    (function () {
      if (!window.DriverUpload) window.DriverUpload = {};
      var DU = window.DriverUpload;
      if (typeof DU.generateIdempotencyKey !== 'function') {
        DU.generateIdempotencyKey = function () {
          if (window.crypto && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
          return 'idem-' + Date.now() + '-' + Math.random().toString(16).slice(2);
        };
      }
      if (typeof DU.attachIdempotencyKey !== 'function') {
        DU.attachIdempotencyKey = function (fd, key) {
          var k = key || DU.generateIdempotencyKey();
          if (fd && typeof fd.set === 'function') fd.set('idempotency_key', k);
          else if (fd && typeof fd.append === 'function') fd.append('idempotency_key', k);
          return k;
        };
      }
      if (typeof DU.fetchOrQueue !== 'function') {
        DU.fetchOrQueue = function (url, fd) {
          return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
        };
      }
      if (typeof DU.compressPhoto !== 'function') {
        DU.compressPhoto = function (file) { return Promise.resolve(file); };
      }
      if (typeof DU.compressMany !== 'function') {
        DU.compressMany = function (files) { return Promise.resolve(files || []); };
      }
      if (typeof DU.setBtnBusy !== 'function') {
        DU.setBtnBusy = function ($btn, busy, text) {
          if (!$btn || !$btn.length) return;
          if (busy) {
            if ($btn.data('orig-html') === undefined) $btn.data('orig-html', $btn.html());
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + (text || 'Saving...'));
          } else {
            var o = $btn.data('orig-html');
            if (o !== undefined) $btn.html(o);
            $btn.removeData('orig-html').prop('disabled', false);
          }
        };
      }
      if (typeof DU.errorMessage !== 'function') {
        DU.errorMessage = function (xhr, ts) {
          if (ts === 'timeout') return 'Upload timed out. Check your connection and try again.';
          try { var b = xhr && xhr.responseText ? JSON.parse(xhr.responseText) : null; if (b && b.message) return b.message; } catch (e) {}
          return 'Network error. Please try again.';
        };
      }
    })();
  </script>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper">
    <div class="app-topstrip"></div>

    <div class="body-wrapper">
      <header class="app-header" style="padding:10px 15px;display:flex;align-items:center;gap:10px;background:#fff;border-bottom:1px solid #eee;position:sticky;top:0;z-index:5;">
        <a href="driver-dashboard" class="text-decoration-none text-dark"><i class="ti ti-arrow-left fs-5"></i></a>
        <strong><?php echo htmlspecialchars($pageTitle); ?></strong>
      </header>

      <div class="container-fluid pb-5 mb-5">
