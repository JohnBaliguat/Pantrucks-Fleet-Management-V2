<?php
session_start();
if (($_SESSION['user_type'] ?? '') !== 'Driver') { header("Location: driver-index.php?route=login"); exit(); }
$pageTitle = 'Gateless Completion';
$activeNav = 'home';
include 'driver/_layout_top.php';

include 'php/config/config.php';
require_once __DIR__ . '/../php/helpers/settings_helper.php';
$gatePhotosRequired = pt_setting_bool($conn, 'require_gate_photos', true);

$dId = (int)($_GET['d_id'] ?? 0);
?>
<div class="card mt-3"><div class="card-body">
  <h5 class="card-title">Destination has no gate?</h5>
  <p class="text-muted small">GPS will be auto-captured. <?php echo $gatePhotosRequired ? 'Two photos minimum.' : 'Photos are optional.'; ?> Works offline — your submission will sync when you're back online.</p>
  <input type="hidden" id="d_id" value="<?php echo $dId; ?>">

  <div class="mb-3" id="gpsBox">
    <div class="alert alert-secondary mb-0">
      <span id="gpsStatus">Acquiring GPS…</span>
    </div>
  </div>

  <div class="d-grid gap-2">
    <label class="btn-modern btn-outline-modern" style="cursor:pointer;text-align:center;">
      <i class="ti ti-camera"></i> Photo 1
      <input type="file" accept="image/*" id="photo1" hidden>
    </label>
    <label class="btn-modern btn-outline-modern" style="cursor:pointer;text-align:center;">
      <i class="ti ti-camera"></i> Photo 2
      <input type="file" accept="image/*" id="photo2" hidden>
    </label>
  </div>
  <div class="d-flex gap-2 mt-2" id="photoPreviews"></div>

  <button id="submitGateless" class="btn-modern btn-primary-modern w-100 mt-3" disabled>
    <i class="ti ti-check"></i> Submit gateless completion
  </button>
</div></div>

<script>
const gpsState = { lat: null, lng: null, acc: null, stamped: null };
const GATE_PHOTOS_REQUIRED = <?php echo $gatePhotosRequired ? 'true' : 'false'; ?>;
function refreshSubmit() {
  const hasPhotos = document.getElementById('photo1').files[0] && document.getElementById('photo2').files[0];
  const ok = gpsState.lat && (!GATE_PHOTOS_REQUIRED || hasPhotos);
  document.getElementById('submitGateless').disabled = !ok;
}
function preview(input, slot) {
  const f = input.files && input.files[0]; if (!f) return;
  const r = new FileReader();
  r.onload = e => {
    let img = document.querySelector('[data-pv="' + slot + '"]');
    if (!img) {
      img = document.createElement('img');
      img.dataset.pv = slot;
      img.style.cssText = 'width:60px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #ddd;';
      document.getElementById('photoPreviews').appendChild(img);
    }
    img.src = e.target.result;
    refreshSubmit();
  };
  r.readAsDataURL(f);
}
document.getElementById('photo1').addEventListener('change', function () { preview(this, 1); });
document.getElementById('photo2').addEventListener('change', function () { preview(this, 2); });

function captureGPS() {
  if (!navigator.geolocation) {
    document.getElementById('gpsStatus').textContent = 'Geolocation not available on this device.';
    return;
  }
  navigator.geolocation.getCurrentPosition(pos => {
    gpsState.lat = pos.coords.latitude.toFixed(7);
    gpsState.lng = pos.coords.longitude.toFixed(7);
    gpsState.acc = Math.round(pos.coords.accuracy);
    gpsState.stamped = new Date().toISOString();
    document.getElementById('gpsStatus').innerHTML = '<i class="ti ti-map-pin text-success"></i> Locked: '
      + gpsState.lat + ', ' + gpsState.lng + ' (~' + gpsState.acc + 'm)';
    refreshSubmit();
  }, err => {
    document.getElementById('gpsStatus').textContent = 'GPS error: ' + err.message + ' — try again outside.';
  }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
}
captureGPS();
$('#gpsBox').on('click', captureGPS);

// Stable idempotency key for this page + hard re-entrancy lock, so a slow
// upload that tempts the driver to tap again can't create duplicate
// gateless completions.
const PT_glIdemKey = DriverUpload.generateIdempotencyKey();
let glSubmitting = false;

document.getElementById('submitGateless').addEventListener('click', async function () {
  const $btn = $(this);
  if (glSubmitting || $btn.prop('disabled')) return;

  const p1 = document.getElementById('photo1').files[0];
  const p2 = document.getElementById('photo2').files[0];

  glSubmitting = true;
  let keepLocked = false;
  DriverUpload.setBtnBusy($btn, true, 'Uploading...');
  try {
    const [c1, c2] = await DriverUpload.compressMany([p1, p2], { maxDim: 1280, quality: 0.7 });
    const fd = new FormData();
    fd.append('d_id', document.getElementById('d_id').value);
    fd.append('lat', gpsState.lat);
    fd.append('lng', gpsState.lng);
    fd.append('accuracy_m', gpsState.acc || 0);
    fd.append('captured_at', gpsState.stamped);
    // Photos may be optional now — only append the ones actually taken.
    if (c1) fd.append('photo1', c1);
    if (c2) fd.append('photo2', c2);
    DriverUpload.attachIdempotencyKey(fd, PT_glIdemKey);
    const r = await DriverUpload.fetchOrQueue('php/operations/save_gateless.php', fd);
    let res;
    try { res = await r.json(); } catch (e) { res = null; }
    if (!r.ok && r.status !== 202) {
      Swal.fire({ icon: 'error', text: (res && res.message) || (r.status === 413 ? 'Photos too large to upload.' : 'Upload failed (HTTP ' + r.status + ').') });
      return;
    }
    if (!res) {
      Swal.fire({ icon: 'error', text: 'Server returned an unexpected response. Please try again.' });
      return;
    }
    Swal.fire({
      icon: res.status === 'success' ? 'success' : (res.status === 'queued' ? 'info' : 'error'),
      title: res.status === 'queued' ? 'Saved offline' : '',
      text: res.message, timer: 1800, showConfirmButton: false
    });
    if (res.status === 'success' || res.status === 'queued') {
      keepLocked = true;
      setTimeout(() => location.href = 'driver-dashboard', 1900);
    }
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Upload failed',
      html: 'Something went wrong submitting this completion.<br><br>' +
            '<small style="color:#888;">' + ((err && err.message) ? String(err.message).replace(/[<>&]/g, '') : 'Unknown error') + '</small>'
    });
  } finally {
    if (!keepLocked) {
      DriverUpload.setBtnBusy($btn, false);
      glSubmitting = false;
    }
  }
});
</script>
<?php include 'driver/_layout_bottom.php'; ?>
