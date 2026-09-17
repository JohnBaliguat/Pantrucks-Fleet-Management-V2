<?php
// Quick Add Equipment — name-only add for trucks / trailers / gensets.
// Required vars: $role ('dispatcher' or 'admin').
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Equipment</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  <style>
    .qa-card { border:1px solid #eaecf0; border-radius:14px; box-shadow:0 1px 2px rgba(16,24,40,.05); height:100%; }
    .qa-top { height:5px; border-radius:14px 14px 0 0; }
    .qa-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px; }
    .qa-added { font-size:12px; }
    .qa-added .badge { font-family:ui-monospace, monospace; font-weight:600; }
  </style>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
       data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-5"><img src="assets/images/logos/pantrucks.png" width="122" alt=""></div>
      <h3 class="text-white mb-0 fs-5">Add Equipment</h3>
    </div>
    <?php include __DIR__ . '/../../' . $role . '/sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include __DIR__ . '/../../' . $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="mt-3 mb-3">
            <h4 class="mb-0">Quick Add Equipment</h4>
            <p class="text-muted mb-0">Add a truck, trailer, or genset by name. Other details can be filled in later from the unit's full profile.</p>
          </div>

          <div class="row g-3">
            <?php
              $cards = [
                ['truck',   'Truck',   'ti-truck', '#2563eb', 'e.g. PM650'],
                ['trailer', 'Trailer', 'ti-box',   '#7c3aed', 'e.g. TR591'],
                ['genset',  'Genset',  'ti-bolt',  '#059669', 'e.g. GS742'],
              ];
              foreach ($cards as $c):
            ?>
            <div class="col-12 col-md-4">
              <div class="qa-card">
                <div class="qa-top" style="background:<?php echo $c[3]; ?>;"></div>
                <div class="card-body">
                  <div class="d-flex align-items-center mb-3">
                    <div class="qa-icon" style="background:<?php echo $c[3]; ?>22;color:<?php echo $c[3]; ?>;"><i class="ti <?php echo $c[2]; ?>"></i></div>
                    <h5 class="mb-0 ms-3"><?php echo $c[1]; ?></h5>
                  </div>
                  <form class="qa-form" data-type="<?php echo $c[0]; ?>">
                    <label class="form-label small fw-semibold"><?php echo $c[1]; ?> name <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <input type="text" class="form-control text-uppercase qa-input" maxlength="100" autocomplete="off" placeholder="<?php echo $c[4]; ?>" required>
                      <button type="submit" class="btn" style="background:<?php echo $c[3]; ?>;color:#fff;"><i class="ti ti-plus"></i> Add</button>
                    </div>
                  </form>
                  <div class="qa-added mt-3 text-muted">
                    <div class="mb-1">Added this session:</div>
                    <div class="d-flex flex-wrap gap-1 qa-list"><span class="text-muted">—</span></div>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
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

    $('.qa-form').on('submit', function (e) {
      e.preventDefault();
      var $form = $(this);
      var type  = $form.data('type');
      var $input = $form.find('.qa-input');
      var name  = String($input.val() || '').trim().toUpperCase();
      if (!name) { $input.trigger('focus'); return; }

      var $btn = $form.find('button[type=submit]').prop('disabled', true);
      $.post('php/operations/quick_add_equipment.php', { type: type, name: name }, null, 'json')
        .done(function (res) {
          if (res && res.status === 'success') {
            var $list = $form.closest('.card-body').find('.qa-list');
            if ($list.find('.text-muted').length) { $list.empty(); }
            $list.prepend('<span class="badge bg-light text-dark border">' + esc(res.name) + '</span> ');
            $input.val('').trigger('focus');
            Swal.fire({ icon: 'success', title: res.message, timer: 1100, showConfirmButton: false });
          } else {
            Swal.fire({ icon: 'error', text: (res && res.message) || 'Add failed.' });
          }
        })
        .fail(function () { Swal.fire({ icon: 'error', text: 'Network error. Please try again.' }); })
        .always(function () { $btn.prop('disabled', false); });
    });
  </script>
</body>
</html>
