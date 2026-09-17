<?php
// Admin Settings — controls which driver inputs are required.
// Required vars: $role (expected 'admin').
require_once __DIR__ . '/../helpers/settings_helper.php';

// Current values, defaulting to each requirement's built-in default.
$reqs = pt_photo_requirements();
$current = [];
foreach ($reqs as $key => $meta) {
    $current[$key] = pt_setting_bool($conn, $key, (bool)$meta[1]);
}

// Non-photo required inputs (label, description, default).
$otherReqs = pt_other_requirements();
$otherCurrent = [];
foreach ($otherReqs as $key => $meta) {
    $otherCurrent[$key] = pt_setting_bool($conn, $key, (bool)$meta[2]);
}

// Dispatch feature toggles.
$features = pt_feature_toggles();
$featureCurrent = [];
foreach ($features as $key => $meta) {
    $featureCurrent[$key] = pt_setting_bool($conn, $key, (bool)$meta[2]);
}

// WhatsApp (Meta Cloud API) notification config.
$wa = [
    'enabled'         => pt_setting_bool($conn, 'whatsapp_enabled', false),
    'token'           => pt_setting_get($conn, 'whatsapp_token', ''),
    'phone_number_id' => pt_setting_get($conn, 'whatsapp_phone_number_id', ''),
    'template_name'   => pt_setting_get($conn, 'whatsapp_template_name', ''),
    'template_lang'   => pt_setting_get($conn, 'whatsapp_template_lang', 'en_US'),
    'default_country' => pt_setting_get($conn, 'whatsapp_default_country', '63'),
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Settings</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
       data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-5"><img src="assets/images/logos/pantrucks.png" width="122" alt=""></div>
      <h3 class="text-white mb-0 fs-5">Settings</h3>
    </div>
    <?php include __DIR__ . '/../../' . $role . '/sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include __DIR__ . '/../../' . $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="card mt-3"><div class="card-body">
            <div class="mb-3">
              <h4 class="card-title mb-0">Required Inputs</h4>
              <p class="card-subtitle">Turn driver photo uploads on (required) or off (optional). Changes apply immediately to the driver app and the server checks.</p>
            </div>

            <form id="settingsForm">
              <div class="list-group mb-3">
                <?php foreach ($reqs as $key => $meta): ?>
                <label class="list-group-item d-flex align-items-center justify-content-between">
                  <span>
                    <span class="fw-semibold"><?php echo htmlspecialchars($meta[0]); ?></span>
                    <small class="d-block text-muted">When off, the driver can submit without this photo.</small>
                  </span>
                  <span class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox"
                           name="<?php echo htmlspecialchars($key); ?>"
                           id="set_<?php echo htmlspecialchars($key); ?>"
                           value="1" <?php echo $current[$key] ? 'checked' : ''; ?>>
                  </span>
                </label>
                <?php endforeach; ?>
                <?php foreach ($otherReqs as $key => $meta): ?>
                <label class="list-group-item d-flex align-items-center justify-content-between">
                  <span>
                    <span class="fw-semibold"><?php echo htmlspecialchars($meta[0]); ?></span>
                    <small class="d-block text-muted"><?php echo htmlspecialchars($meta[1]); ?></small>
                  </span>
                  <span class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox"
                           name="<?php echo htmlspecialchars($key); ?>"
                           id="set_<?php echo htmlspecialchars($key); ?>"
                           value="1" <?php echo $otherCurrent[$key] ? 'checked' : ''; ?>>
                  </span>
                </label>
                <?php endforeach; ?>
              </div>
              <hr class="my-4">

              <div class="mb-3">
                <h4 class="card-title mb-0">Dispatch Features</h4>
                <p class="card-subtitle">Enable or disable optional dispatcher capabilities.</p>
              </div>
              <div class="list-group mb-3">
                <?php foreach ($features as $key => $meta): ?>
                <label class="list-group-item d-flex align-items-center justify-content-between">
                  <span>
                    <span class="fw-semibold"><?php echo htmlspecialchars($meta[0]); ?></span>
                    <small class="d-block text-muted"><?php echo htmlspecialchars($meta[1]); ?></small>
                  </span>
                  <span class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox"
                           name="<?php echo htmlspecialchars($key); ?>"
                           id="set_<?php echo htmlspecialchars($key); ?>"
                           value="1" <?php echo $featureCurrent[$key] ? 'checked' : ''; ?>>
                  </span>
                </label>
                <?php endforeach; ?>
              </div>

              <button type="submit" class="btn btn-primary" id="saveSettings"><i class="ti ti-device-floppy"></i> Save changes</button>
            </form>
          </div></div>

          <!-- WhatsApp violation notifications (Meta Cloud API) -->
          <div class="card mt-3"><div class="card-body">
            <div class="mb-3">
              <h4 class="card-title mb-0"><i class="ti ti-brand-whatsapp text-success"></i> WhatsApp Notifications</h4>
              <p class="card-subtitle">When a driver gets a violation, message their registered contact on WhatsApp. Uses the Meta (Facebook) WhatsApp Cloud API. Leave off/blank to disable.</p>
            </div>
            <form id="whatsappForm">
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="whatsapp_enabled" id="whatsapp_enabled" value="1" <?php echo $wa['enabled'] ? 'checked' : ''; ?>>
                <label class="form-check-label fw-semibold" for="whatsapp_enabled">Enable WhatsApp notifications</label>
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Access token</label>
                  <input type="password" class="form-control" name="whatsapp_token" autocomplete="off"
                         placeholder="<?php echo $wa['token'] !== '' ? '•••••••• (leave blank to keep)' : 'Permanent access token'; ?>">
                  <small class="text-muted">Permanent token from your Meta app. Leave blank to keep the current one.</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone number ID</label>
                  <input type="text" class="form-control" name="whatsapp_phone_number_id" value="<?php echo htmlspecialchars($wa['phone_number_id']); ?>" autocomplete="off" placeholder="e.g. 1234567890">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Template name <span class="text-muted">(recommended)</span></label>
                  <input type="text" class="form-control" name="whatsapp_template_name" value="<?php echo htmlspecialchars($wa['template_name']); ?>" autocomplete="off" placeholder="e.g. driver_violation">
                  <small class="text-muted">Approved template. Blank = plain text (only works within a 24h window).</small>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Template language</label>
                  <input type="text" class="form-control" name="whatsapp_template_lang" value="<?php echo htmlspecialchars($wa['template_lang']); ?>" autocomplete="off" placeholder="en_US">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Default country code</label>
                  <input type="text" class="form-control" name="whatsapp_default_country" value="<?php echo htmlspecialchars($wa['default_country']); ?>" autocomplete="off" placeholder="63">
                  <small class="text-muted">For local numbers like 0917… (63 = Philippines).</small>
                </div>
              </div>
              <button type="submit" class="btn btn-primary mt-3" id="saveWhatsapp"><i class="ti ti-device-floppy"></i> Save WhatsApp settings</button>
            </form>
          </div></div>
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
    $('#settingsForm').on('submit', function (e) {
      e.preventDefault();
      var $btn = $('#saveSettings').prop('disabled', true);
      // Build payload: every toggle key, '1' when checked else '0'.
      var data = {};
      $('#settingsForm input[type=checkbox]').each(function () {
        data[this.name] = this.checked ? '1' : '0';
      });
      $.post('php/operations/save_field_settings.php', data, null, 'json')
        .done(function (res) {
          if (res && res.status === 'success') {
            Swal.fire({ icon: 'success', title: 'Saved', timer: 1200, showConfirmButton: false });
          } else {
            Swal.fire({ icon: 'error', text: (res && res.message) || 'Save failed.' });
          }
        })
        .fail(function () { Swal.fire({ icon: 'error', text: 'Save failed. Please try again.' }); })
        .always(function () { $btn.prop('disabled', false); });
    });

    // WhatsApp settings — separate form/endpoint (mix of toggle + text fields).
    $('#whatsappForm').on('submit', function (e) {
      e.preventDefault();
      var $btn = $('#saveWhatsapp').prop('disabled', true);
      var data = { whatsapp_enabled: $('#whatsapp_enabled').is(':checked') ? '1' : '0' };
      $('#whatsappForm input[type=text], #whatsappForm input[type=password]').each(function () {
        data[this.name] = this.value;
      });
      $.post('php/operations/save_whatsapp_settings.php', data, null, 'json')
        .done(function (res) {
          if (res && res.status === 'success') {
            Swal.fire({ icon: 'success', title: 'Saved', text: res.message || '', timer: 1400, showConfirmButton: false });
            $('#whatsappForm input[type=password]').val('');   // don't keep the token in the field
          } else {
            Swal.fire({ icon: 'error', text: (res && res.message) || 'Save failed.' });
          }
        })
        .fail(function () { Swal.fire({ icon: 'error', text: 'Save failed. Please try again.' }); })
        .always(function () { $btn.prop('disabled', false); });
    });
  </script>
</body>
</html>
