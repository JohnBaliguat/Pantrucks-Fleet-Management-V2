<?php
session_start();
if (($_SESSION['user_type'] ?? '') !== 'Driver') { header("Location: driver-index.php?route=login"); exit(); }
$pageTitle = 'DICT Hustling';
$activeNav = 'home';
include 'driver/_layout_top.php';
?>
<div id="hustleNoDay" class="d-none">
  <div class="alert alert-warning mt-3">
    No hustling day assigned today. Ask your dispatcher to create your <b>DICT Hustling</b> day, then refresh.
  </div>
</div>

<div id="hustleDay" class="d-none">
  <!-- Day summary -->
  <div class="card mt-3"><div class="card-body py-3">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <div class="text-muted small">Truck</div>
        <div class="fw-bold" id="hsTruck">—</div>
      </div>
      <div class="text-center">
        <div class="text-muted small">Containers</div>
        <div class="fw-bold fs-4" id="hsCount">0</div>
      </div>
      <div class="text-end">
        <div class="text-muted small">Est. total</div>
        <div class="fw-bold fs-4 text-success" id="hsTotal">₱0.00</div>
      </div>
    </div>
    <div class="small text-muted mt-1" id="hsRateNote"></div>
  </div></div>

  <!-- Add container form -->
  <div class="card mt-3" id="hsFormCard"><div class="card-body">
    <h6 class="mb-3">Add Container</h6>
    <form id="hustleForm">
      <div class="mb-2">
        <label class="form-label small mb-1">Container No.</label>
        <input type="text" class="form-control text-uppercase" id="hsContainer" name="container_no"
               placeholder="ABCD1234567" maxlength="11" autocomplete="off" required>
        <div class="form-text">4 letters + 7 numbers</div>
      </div>
      <div class="mb-2">
        <label class="form-label small mb-1">Status</label>
        <div class="btn-group w-100" role="group">
          <input type="radio" class="btn-check" name="container_stat" id="hsLoaded" value="Loaded" autocomplete="off" required>
          <label class="btn btn-outline-primary" for="hsLoaded">Loaded</label>
          <input type="radio" class="btn-check" name="container_stat" id="hsEmpty" value="Empty" autocomplete="off">
          <label class="btn btn-outline-primary" for="hsEmpty">Empty</label>
        </div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-6">
          <label class="form-label small mb-1">From</label>
          <input type="text" class="form-control" name="trip_from" id="hsFrom" placeholder="Pickup">
        </div>
        <div class="col-6">
          <label class="form-label small mb-1">To</label>
          <input type="text" class="form-control" name="trip_to" id="hsTo" placeholder="Drop">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label small mb-1">Photo</label>
        <input type="file" class="form-control" id="hsPhoto" name="container_photo" accept="image/*" capture="environment" required>
      </div>
      <input type="hidden" name="lat" id="hsLat">
      <input type="hidden" name="lng" id="hsLng">
      <button type="submit" class="btn btn-primary w-100" id="hsAddBtn"><i class="ti ti-plus"></i> Add Container</button>
    </form>
  </div></div>

  <!-- Containers list -->
  <div class="card mt-3"><div class="card-body">
    <h6 class="mb-2">Today's Containers</h6>
    <div id="hsList"><div class="text-muted small">Loading…</div></div>
  </div></div>

  <!-- Submit day -->
  <div class="mt-3 mb-4" id="hsSubmitWrap">
    <button class="btn btn-success w-100 btn-lg" id="hsSubmitBtn"><i class="ti ti-checks"></i> Submit Day for Verification</button>
  </div>
  <div class="mt-3 mb-4 d-none" id="hsSubmittedNote">
    <div class="alert alert-success mb-0">Submitted for verification. Your dispatcher will approve it.</div>
  </div>
</div>

<script>
(function () {
  function peso(n){ return '₱' + (Number(n)||0).toFixed(2); }
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}

  function render(res) {
    if (!res.has_day) { $('#hustleNoDay').removeClass('d-none'); $('#hustleDay').addClass('d-none'); return; }
    $('#hustleNoDay').addClass('d-none'); $('#hustleDay').removeClass('d-none');
    $('#hsTruck').text(res.truck || '—');
    $('#hsCount').text(res.count);
    $('#hsTotal').text(peso(res.total));
    $('#hsRateNote').text('Flat rate ' + peso(res.rate) + ' per container' + (res.control_no ? ' · Coupon ' + res.control_no : ''));

    if (res.rows.length) {
      $('#hsList').html(res.rows.map(function (r, i) {
        var badge = r.stat === 'Loaded' ? '<span class="badge bg-primary">Loaded</span>' : '<span class="badge bg-secondary">Empty</span>';
        var photo = r.photo_path ? '<a href="' + esc(r.photo_path) + '" target="_blank"><img src="' + esc(r.photo_path) + '" style="width:44px;height:44px;object-fit:cover;border-radius:6px;"></a>' : '';
        return '<div class="d-flex align-items-center gap-2 py-2 border-bottom">' +
          '<div class="text-muted">' + (i + 1) + '.</div>' +
          photo +
          '<div class="flex-grow-1"><div class="fw-bold font-monospace">' + esc(r.container_no) + '</div>' +
          '<div class="small text-muted">' + badge + ' ' + esc(r.trip_from || '—') + ' → ' + esc(r.trip_to || '—') + '</div></div>' +
          '<div class="fw-bold text-success">' + peso(r.piece_rate) + '</div>' +
        '</div>';
      }).join(''));
    } else {
      $('#hsList').html('<div class="text-muted small">No containers yet. Add your first one above.</div>');
    }

    // Lock the form + submit once the day is submitted.
    if (res.submitted) {
      $('#hsFormCard').addClass('d-none');
      $('#hsSubmitWrap').addClass('d-none');
      $('#hsSubmittedNote').removeClass('d-none');
    } else {
      $('#hsFormCard').removeClass('d-none');
      $('#hsSubmitWrap').removeClass('d-none');
      $('#hsSubmittedNote').addClass('d-none');
    }
  }

  function load() {
    $.getJSON('php/fetch/hustling_containers.php', render)
      .fail(function () { $('#hsList').html('<div class="text-danger small">Failed to load.</div>'); });
  }

  // Best-effort GPS fill before submit.
  function fillGps(cb) {
    if (!navigator.geolocation) return cb();
    navigator.geolocation.getCurrentPosition(function (p) {
      $('#hsLat').val(p.coords.latitude); $('#hsLng').val(p.coords.longitude); cb();
    }, function () { cb(); }, { enableHighAccuracy: true, timeout: 6000, maximumAge: 30000 });
  }

  $('#hustleForm').on('submit', function (e) {
    e.preventDefault();
    var form = this;
    var $btn = $('#hsAddBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving…');
    fillGps(function () {
      var fd = new FormData(form);
      if (window.DriverUpload && DriverUpload.attachIdempotencyKey) DriverUpload.attachIdempotencyKey(fd);
      $.ajax({ url: 'php/operations/add_hustling_container.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
        .done(function (res) {
          if (res.status === 'success') {
            form.reset();
            Swal.fire({ icon: 'success', text: res.message, timer: 1200, showConfirmButton: false });
            load();
          } else { Swal.fire({ icon: 'error', text: res.message || 'Failed' }); }
        })
        .fail(function (xhr) { Swal.fire({ icon: 'error', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Network error.' }); })
        .always(function () { $btn.prop('disabled', false).html('<i class="ti ti-plus"></i> Add Container'); });
    });
  });

  $('#hsSubmitBtn').on('click', function () {
    Swal.fire({
      title: 'Submit the day?',
      text: 'You won\'t be able to add more containers after this.',
      icon: 'question', showCancelButton: true, confirmButtonText: 'Submit', confirmButtonColor: '#198754'
    }).then(function (r) {
      if (!r.isConfirmed) return;
      $.post('php/operations/submit_hustling_day.php', {}, null, 'json')
        .done(function (res) {
          if (res.status === 'success') { Swal.fire({ icon: 'success', text: res.message, timer: 1600, showConfirmButton: false }); load(); }
          else { Swal.fire({ icon: 'error', text: res.message || 'Failed' }); }
        })
        .fail(function (xhr) { Swal.fire({ icon: 'error', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Network error.' }); });
    });
  });

  load();
})();
</script>
<?php include 'driver/_layout_bottom.php'; ?>
