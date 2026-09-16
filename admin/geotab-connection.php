<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {
  require_once __DIR__ . '/../php/lib/geotab_client.php';
  $cfg = pt_geotab_load_settings() ?? ['enabled' => false, 'server' => 'my.geotab.com', 'database' => '', 'username' => '', 'password' => ''];
  $hasPassword = !empty($cfg['password']);
  $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Geotab Connection</title>
    <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
    <link rel="stylesheet" href="assets/css/styles.min.css" />
    <link rel="stylesheet" href="assets/css/enhancements.css" />
    <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  </head>

  <body>
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
      data-sidebar-position="fixed" data-header-position="fixed">

      <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center justify-content-center gap-5 mb-2 mb-lg-0">
          <a class="d-flex justify-content-center" href="#">
            <img src="assets/images/logos/pantrucks.png" alt="" width="122">
          </a>
        </div>
        <div class="d-lg-flex align-items-center gap-2">
          <h3 class="text-white mb-2 mb-lg-0 fs-5 text-center">Pantrucks Fleet Management System</h3>
        </div>
      </div>

      <?php include 'sidebar.php'; ?>

      <div class="body-wrapper">
        <?php include 'navbar.php'; ?>
        <div class="body-wrapper-inner">
          <div class="container-fluid">
            <div class="row g-3">
              <div class="col-lg-7">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Geotab Connection</h4>
                    <p class="card-subtitle mb-4">Connect the live MyGeotab API used by container tracking, geofences, vehicle health, fuel reconciliation and driver behaviour.</p>

                    <form id="geotabForm">
                      <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="enabled">Enable Geotab live data</label>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Server</label>
                        <input type="text" name="server" class="form-control" value="<?= $h($cfg['server'] ?: 'my.geotab.com') ?>" placeholder="my.geotab.com">
                        <div class="form-text">Usually <code>my.geotab.com</code> — the API redirects to your data server automatically.</div>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Database</label>
                        <input type="text" name="database" class="form-control" value="<?= $h($cfg['database']) ?>" placeholder="your_geotab_database">
                        <div class="form-text">The database name in your MyGeotab URL (after the host).</div>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">API username</label>
                        <input type="text" name="username" class="form-control" value="<?= $h($cfg['username']) ?>" placeholder="api-service@yourcompany.com" autocomplete="off">
                        <div class="form-text">Use a dedicated Geotab user for the API — <strong>not</strong> an SSO/MFA account (those can't authenticate via the API).</div>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">API password</label>
                        <input type="password" name="password" class="form-control" autocomplete="new-password"
                               placeholder="<?= $hasPassword ? '•••••••• (unchanged — leave blank to keep)' : 'enter password' ?>">
                      </div>
                      <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="ti ti-device-floppy"></i> Save</button>
                        <button type="button" id="btnTest" class="btn btn-outline-secondary btn-sm"><i class="ti ti-plug"></i> Test connection</button>
                      </div>
                      <div id="testResult" class="mt-3"></div>
                    </form>
                  </div>
                </div>
              </div>

              <div class="col-lg-5">
                <div class="card">
                  <div class="card-body">
                    <h6 class="mb-2"><i class="ti ti-shield-lock"></i> How credentials are stored</h6>
                    <p class="text-muted mb-3" style="font-size:.9rem">
                      Saved to <code>php/config/geotab.config.php</code> — a PHP file that is executed (never served as text),
                      so the password isn't exposed on the web. It's git-ignored. The <code>php/config</code> folder must be writable by PHP.
                    </p>
                    <h6 class="mb-2"><i class="ti ti-checklist"></i> After connecting</h6>
                    <ol class="text-muted mb-0" style="font-size:.9rem">
                      <li>Link trucks on <a href="geotab-devices">Geotab Devices</a>.</li>
                      <li>Sync &amp; classify zones on <a href="geotab-zones">Geotab Zones</a>.</li>
                      <li>Schedule the pollers (Task Scheduler).</li>
                    </ol>
                  </div>
                </div>
              </div>
            </div>
            <div class="py-6 px-6 text-center">
              <p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sidebarmenu.js"></script>
    <script src="assets/js/app.min.js"></script>
    <script src="assets/libs/simplebar/dist/simplebar.js"></script>
    <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
    <script>
      function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

      $('#geotabForm').on('submit', function (e) {
        e.preventDefault();
        const $btn = $(this).find('button[type="submit"]').prop('disabled', true);
        $.post('php/operations/geotab_settings_save.php', $(this).serialize())
          .done(res => {
            if (res.status === 'success') {
              Swal.fire({ icon: 'success', title: 'Saved', text: res.message, timer: 1600, showConfirmButton: false });
              $('input[name="password"]').val('').attr('placeholder', '•••••••• (unchanged — leave blank to keep)');
            } else {
              Swal.fire({ icon: 'error', title: 'Failed', text: res.message || 'Unknown error' });
            }
          })
          .fail(xhr => { let m = 'Save failed'; try { m = JSON.parse(xhr.responseText).message || m; } catch (x) {} Swal.fire({ icon: 'error', title: 'Failed', text: m }); })
          .always(() => $btn.prop('disabled', false));
      });

      $('#btnTest').on('click', function () {
        const $out = $('#testResult').html('<span class="text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Testing…</span>');
        const $btn = $(this).prop('disabled', true);
        $.getJSON('php/operations/geotab_test.php')
          .done(d => {
            $out.html('<div class="alert alert-' + (d.ok ? 'success' : 'danger') + ' mb-0">' +
              '<i class="ti ' + (d.ok ? 'ti-circle-check' : 'ti-circle-x') + ' me-1"></i>' + esc(d.message || '') + '</div>');
          })
          .fail(() => $out.html('<div class="alert alert-danger mb-0">Test request failed.</div>'))
          .always(() => $btn.prop('disabled', false));
      });
    </script>
  </body>
  </html>
<?php
} else {
  header("Location: index.php?route=login");
  exit();
}
