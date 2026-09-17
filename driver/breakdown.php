<?php
session_start();
if (($_SESSION['user_type'] ?? '') !== 'Driver') { header("Location: driver-index.php?route=login"); exit(); }
$pageTitle = 'Breakdown / Cancel';
$activeNav = 'breakdown';
include 'driver/_layout_top.php';

include 'php/config/config.php';
require_once __DIR__ . '/../php/helpers/settings_helper.php';
$breakdownPhotoRequired = pt_setting_bool($conn, 'require_breakdown_photo', false);

$dId = (int)($_GET['d_id'] ?? 0);
?>
<div class="card mt-3"><div class="card-body">
  <h5 class="card-title text-danger">Report a problem</h5>
  <p class="text-muted small">Dispatcher will be notified immediately. Pick assistance type if you need help.</p>

  <input type="hidden" id="d_id" value="<?php echo $dId; ?>">

  <div class="mb-3">
    <label class="form-label">Type</label>
    <select id="incident_type" class="form-control-modern">
      <option value="breakdown" selected>Breakdown</option>
      <option value="cargo">Cargo issue</option>
      <option value="exception">Other exception</option>
      <option value="cancel">Cancel trip</option>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Severity</label>
    <select id="severity" class="form-control-modern">
      <option value="low">Low</option>
      <option value="med" selected>Medium</option>
      <option value="high">High</option>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Assistance needed</label>
    <select id="assistance" class="form-control-modern">
      <option value="">— none —</option>
      <option value="tow">Tow truck</option>
      <option value="mechanic">Mechanic</option>
      <option value="cargo_transfer">Cargo transfer</option>
      <option value="emergency">Emergency / SOS</option>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">What happened?</label>
    <textarea id="description" class="form-control-modern" rows="3" placeholder="Brief description"></textarea>
  </div>

  <div class="mb-3" id="gpsBox">
    <div class="alert alert-secondary mb-0"><span id="gpsStatus">Acquiring GPS…</span></div>
  </div>

  <label class="btn-modern btn-outline-modern w-100" style="cursor:pointer;text-align:center;">
    <i class="ti ti-camera"></i> Photo<?php echo $breakdownPhotoRequired ? '' : ' (optional)'; ?>
    <input type="file" accept="image/*" id="photo" hidden>
  </label>

  <button id="submitBreakdown" class="btn-modern btn-primary-modern w-100 mt-3" style="background:#dc3545;border-color:#dc3545;">
    <i class="ti ti-alert-triangle"></i> Submit
  </button>
</div></div>

<script>
const gps = { lat: null, lng: null };
function captureGPS() {
  if (!navigator.geolocation) return;
  navigator.geolocation.getCurrentPosition(p => {
    gps.lat = p.coords.latitude.toFixed(7); gps.lng = p.coords.longitude.toFixed(7);
    document.getElementById('gpsStatus').innerHTML = '<i class="ti ti-map-pin text-success"></i> ' + gps.lat + ', ' + gps.lng;
  }, err => document.getElementById('gpsStatus').textContent = 'GPS error: ' + err.message,
    { enableHighAccuracy: true, timeout: 15000 });
}
captureGPS();
$('#gpsBox').on('click', captureGPS);

document.getElementById('submitBreakdown').addEventListener('click', async function () {
  const $btn = $(this);
  if ($btn.prop('disabled')) return;
  const desc = document.getElementById('description').value.trim();
  if (!desc) { Swal.fire({ icon: 'warning', text: 'Please describe the problem.' }); return; }
  const BREAKDOWN_PHOTO_REQUIRED = <?php echo $breakdownPhotoRequired ? 'true' : 'false'; ?>;
  if (BREAKDOWN_PHOTO_REQUIRED && !document.getElementById('photo').files[0]) {
    Swal.fire({ icon: 'warning', text: 'A photo is required for breakdown reports.' }); return;
  }

  DriverUpload.setBtnBusy($btn, true, 'Submitting...');
  try {
    const rawPhoto = document.getElementById('photo').files[0];
    const photo = rawPhoto ? await DriverUpload.compressPhoto(rawPhoto) : null;

    const fd = new FormData();
    fd.append('d_id',          document.getElementById('d_id').value);
    fd.append('incident_type', document.getElementById('incident_type').value);
    fd.append('severity',      document.getElementById('severity').value);
    fd.append('assistance',    document.getElementById('assistance').value);
    fd.append('description',   desc);
    if (gps.lat) { fd.append('lat', gps.lat); fd.append('lng', gps.lng); }
    if (photo) fd.append('photo', photo);
    DriverUpload.attachIdempotencyKey(fd);
    const r = await DriverUpload.fetchOrQueue('php/operations/save_breakdown.php', fd);
    let res;
    try { res = await r.json(); } catch (e) { res = null; }
    if (!r.ok && r.status !== 202) {
      Swal.fire({ icon: 'error', text: (res && res.message) || (r.status === 413 ? 'Photo too large to upload.' : 'Submit failed (HTTP ' + r.status + ').') });
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
      setTimeout(() => location.href = 'driver-dashboard', 1900);
    }
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Submit failed',
      html: 'Something went wrong submitting this report.<br><br>' +
            '<small style="color:#888;">' + ((err && err.message) ? String(err.message).replace(/[<>&]/g, '') : 'Unknown error') + '</small>'
    });
  } finally {
    DriverUpload.setBtnBusy($btn, false);
  }
});
</script>
<?php include 'driver/_layout_bottom.php'; ?>
