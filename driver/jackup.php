<?php
session_start();
if (($_SESSION['user_type'] ?? '') !== 'Driver') { header("Location: driver-index.php?route=login"); exit(); }
$pageTitle = 'Jack-up Trailer';
$activeNav = 'home';
include __DIR__ . '/_layout_top.php';

include __DIR__ . '/../php/config/config.php';
require_once __DIR__ . '/../php/helpers/settings_helper.php';
$jackupPhotoRequired = pt_setting_bool($conn, 'require_jackup_photo', true);
$driverId = (int)$_SESSION['user_id'];
$dId = (int)($_GET['d_id'] ?? 0);

$dispatch = null;
if ($dId > 0) {
    $stmt = $conn->prepare("SELECT d_id, booking_no, d_truck, d_trailer FROM dispatch WHERE d_id = ? AND driver_id = ? LIMIT 1");
    $stmt->execute([$dId, $driverId]);
    $dispatch = $stmt->fetch();
}
?>
<div class="card mt-3"><div class="card-body">
  <h5 class="card-title">Detach trailer at site</h5>
  <p class="text-muted small">Trailer billing keeps running until the trailer returns to compound. Capture GPS + a photo of the detached trailer.</p>

  <input type="hidden" id="d_id" value="<?php echo (int)($dispatch['d_id'] ?? 0); ?>">

  <div class="mb-3">
    <label class="form-label">Trailer code</label>
    <input id="trailer_code" class="form-control-modern" value="<?php echo htmlspecialchars($dispatch['d_trailer'] ?? ''); ?>" placeholder="e.g. TR007">
  </div>

  <div class="mb-3" id="gpsBox">
    <div class="alert alert-secondary mb-0"><span id="gpsStatus">Acquiring GPS…</span></div>
  </div>

  <label class="btn-modern btn-outline-modern w-100" style="cursor:pointer;text-align:center;">
    <i class="ti ti-camera"></i> Photo of detached trailer<?php echo $jackupPhotoRequired ? '' : ' (optional)'; ?>
    <input type="file" accept="image/*" id="photo" hidden>
  </label>
  <div id="photoPreview" class="mt-2"></div>

  <button id="submitJackup" class="btn-modern btn-primary-modern w-100 mt-3" disabled>
    <i class="ti ti-trailer"></i> Mark Jacked Up
  </button>
</div></div>

<script>
const gps = { lat: null, lng: null };
const JACKUP_PHOTO_REQUIRED = <?php echo $jackupPhotoRequired ? 'true' : 'false'; ?>;
function refresh() {
  const hasPhoto = !!document.getElementById('photo').files[0];
  const ok = gps.lat && document.getElementById('trailer_code').value.trim() && (!JACKUP_PHOTO_REQUIRED || hasPhoto);
  document.getElementById('submitJackup').disabled = !ok;
}
function captureGPS() {
  if (!navigator.geolocation) { document.getElementById('gpsStatus').textContent = 'Geolocation not available.'; return; }
  navigator.geolocation.getCurrentPosition(p => {
    gps.lat = p.coords.latitude.toFixed(7); gps.lng = p.coords.longitude.toFixed(7);
    document.getElementById('gpsStatus').innerHTML = '<i class="ti ti-map-pin text-success"></i> Locked: ' + gps.lat + ', ' + gps.lng;
    refresh();
  }, err => document.getElementById('gpsStatus').textContent = 'GPS error: ' + err.message,
    { enableHighAccuracy: true, timeout: 15000 });
}
captureGPS();
$('#gpsBox').on('click', captureGPS);
$('#trailer_code').on('input', refresh);
document.getElementById('photo').addEventListener('change', function () {
  const f = this.files[0]; if (!f) return;
  const r = new FileReader();
  r.onload = e => document.getElementById('photoPreview').innerHTML =
    '<img src="' + e.target.result + '" style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #ddd;">';
  r.readAsDataURL(f);
  refresh();
});

document.getElementById('submitJackup').addEventListener('click', async () => {
  const trailerCode = document.getElementById('trailer_code').value.trim();
  const photoFile = document.getElementById('photo').files[0];
  if (!trailerCode) {
    Swal.fire({ icon: 'warning', text: 'Trailer code is required.' });
    return;
  }
  if (!gps.lat || !gps.lng) {
    Swal.fire({ icon: 'warning', text: 'GPS is required before jack-up.' });
    return;
  }
  if (JACKUP_PHOTO_REQUIRED && !photoFile) {
    Swal.fire({ icon: 'warning', text: 'Photo of the detached trailer is required.' });
    return;
  }

  const $btn = $('#submitJackup');
  DriverUpload.setBtnBusy($btn, true, 'Uploading...');
  try {
    const fd = new FormData();
    fd.append('d_id', document.getElementById('d_id').value);
    fd.append('trailer_code', trailerCode);
    fd.append('lat', gps.lat); fd.append('lng', gps.lng);
    // Photo may be optional now — only compress + attach when one was taken.
    if (photoFile) {
      const compressed = await DriverUpload.compressPhoto(photoFile);
      fd.append('photo', compressed);
    }
    DriverUpload.attachIdempotencyKey(fd);

    // fetchOrQueue falls back to a local IndexedDB queue if the network
    // truly throws — so a flaky signal won't lose the driver's submission.
    const r = await DriverUpload.fetchOrQueue('php/operations/save_trailer_jackup.php', fd);
    let res;
    try { res = await r.json(); } catch (e) { res = null; }

    if (!r.ok && r.status !== 202) {
      const msg = (res && res.message)
        || (r.status === 413 ? 'Photo too large to upload.'
          : r.status === 409 ? 'This jack-up was already saved.'
          : 'Jack-up save failed (HTTP ' + r.status + ').');
      Swal.fire({ icon: 'error', text: msg });
      return;
    }
    if (!res) {
      Swal.fire({ icon: 'error', text: 'Server returned an unexpected response. Please try again.' });
      return;
    }

    await Swal.fire({
      icon: res.status === 'success' ? 'success' : (res.status === 'queued' ? 'info' : 'error'),
      title: res.status === 'queued' ? 'Saved offline' : '',
      text: res.message,
      confirmButtonText: 'OK'
    });
    if (res.status === 'success' || res.status === 'queued') {
      // If the driver came here from the "Jackup trailer? Yes" prompt on
      // the dashboard, finish the delivery for them so they don't have to
      // navigate back and tap Delivered again.
      const params = new URLSearchParams(location.search);
      if (params.get('auto_deliver') === '1') {
        const dId = document.getElementById('d_id').value;
        try {
          const deliverFd = new FormData();
          deliverFd.append('d_id', dId);
          deliverFd.append('status', 'delivered');
          if (gps.lat) deliverFd.append('lat', gps.lat);
          if (gps.lng) deliverFd.append('lng', gps.lng);
          // Carry the back-dated delivery time through from the dashboard's
          // late-entry flow (no signal at the site).
          const evt = params.get('event_time');
          if (evt) deliverFd.append('event_time', evt);
          DriverUpload.attachIdempotencyKey(deliverFd);
          const dr = await DriverUpload.fetchOrQueue('php/operations/driver_update_status.php', deliverFd);
          let deliverRes = null;
          try { deliverRes = await dr.json(); } catch (e) {}
          if ((dr.ok || dr.status === 202) && deliverRes && (deliverRes.status === 'success' || deliverRes.status === 'queued')) {
            location.href = 'driver-pod?d_id=' + encodeURIComponent(dId);
            return;
          }
          // Fall through to dashboard if auto-deliver couldn't complete; the
          // driver can re-tap Delivered there.
        } catch (e) { /* fall through */ }
      }
      location.href = 'driver-dashboard';
    }
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Jack-up save failed',
      html: 'Something went wrong saving this jack-up.<br><br>' +
            '<small style="color:#888;">' + ((err && err.message) ? String(err.message).replace(/[<>&]/g, '') : 'Unknown error') + '</small>'
    });
  } finally {
    DriverUpload.setBtnBusy($btn, false);
  }
});
</script>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
