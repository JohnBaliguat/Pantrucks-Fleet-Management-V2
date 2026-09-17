<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Gate-Guard') {
    header("Location: gate-index.php?route=login");
    exit();
}
$pageTitle = 'Vehicle Check-In / Out';
include 'gate/_layout_top.php';
?>
<div class="row">
  <div class="col-lg-5">
    <div class="card"><div class="card-body">
      <h4 class="card-title">Scan or enter plate</h4>
      <p class="card-subtitle">Scan the dispatch QR code, or enter the truck plate manually if no QR is available.</p>

      <div class="mt-3">
        <button id="startScan" type="button" class="btn btn-primary">
          <i class="ti ti-camera"></i> Start QR Scanner
        </button>
        <button id="stopScan" type="button" class="btn btn-secondary" style="display:none;">Stop</button>
      </div>
      <div id="scannerBox" class="mt-3" style="display:none;">
        <div id="qrReader" style="width:100%;"></div>
      </div>

      <hr>
      <form id="manualForm">
        <label class="form-label">Truck plate</label>
        <input class="form-control" type="text" id="manualPlate" placeholder="e.g. PM651" autocomplete="off">
        <button type="submit" class="btn btn-primary mt-2 w-100">Look Up Dispatch</button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-7">
    <div class="card" id="dispatchCard" style="display:none;"><div class="card-body">
      <div id="dispatchInfo"></div>
      <hr>
      <div class="d-flex gap-2">
        <button class="btn btn-success btn-lg flex-fill" id="logIn"><i class="ti ti-arrow-down-left"></i> Log IN (Entry)</button>
        <button class="btn btn-warning btn-lg flex-fill" id="logOut"><i class="ti ti-arrow-up-right"></i> Log OUT (Exit)</button>
      </div>
      <div class="mt-2">
        <button class="btn btn-link btn-sm" id="addToQueue">Vehicle isn't authorised yet — add to dispatcher queue</button>
      </div>
    </div></div>

    <div class="card" id="emptyCard"><div class="card-body text-center text-muted py-5">
      <i class="ti ti-truck fs-1"></i>
      <p class="mt-2 mb-0">Pick a truck via QR scan or manual entry to begin.</p>
    </div></div>
  </div>
</div>
<div class="py-6 px-6 text-center">
  <p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.10/html5-qrcode.min.js"></script>
<script>
function escapeHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}

let qr = null;
let currentDispatch = null;

function renderDispatch(d){
  currentDispatch = d;
  var customsBadge = '';
  if (d.booking_type === 'Export') {
    customsBadge = (parseInt(d.customs_cleared,10)===1)
      ? '<span class="badge bg-success ms-2">Customs Cleared</span>'
      : '<span class="badge bg-danger ms-2">Customs NOT Cleared</span>';
  }
  var authBadge = (parseInt(d.authorised,10)===1)
    ? '<span class="badge bg-success">Authorised</span>'
    : '<span class="badge bg-warning text-dark">Not yet authorised by dispatcher</span>';
  var html = ''
    + '<h4 class="card-title">' + escapeHtml(d.booking_no || '(no booking)') + ' '
    + '<span class="badge bg-primary">' + escapeHtml(d.booking_type || 'Local') + '</span>' + customsBadge + '</h4>'
    + '<p class="card-subtitle mb-3">' + authBadge + '</p>'
    + '<div class="row">'
    +   '<div class="col-md-6"><b>Driver:</b> ' + escapeHtml(d.d_driverName || '-') + '</div>'
    +   '<div class="col-md-6"><b>Customer:</b> ' + escapeHtml(d.costumer || '-') + '</div>'
    +   '<div class="col-md-6 mt-2"><b>Truck:</b> ' + escapeHtml(d.d_truck || '-') + '</div>'
    +   '<div class="col-md-6 mt-2"><b>Trailer:</b> ' + escapeHtml(d.d_trailer || '-') + '</div>'
    +   '<div class="col-md-6 mt-2"><b>Genset:</b> ' + escapeHtml(d.d_genset || '-') + '</div>'
    +   '<div class="col-md-6 mt-2"><b>Workflow:</b> ' + escapeHtml(d.workflow_stage || '-') + '</div>'
    + '</div>';
  $('#dispatchInfo').html(html);
  $('#emptyCard').hide();
  $('#dispatchCard').show();
}

function lookup(payload, mode){
  // mode: 'qr' | 'plate'
  $.getJSON('php/fetch/active_dispatch_by_truck.php', { q: payload, mode: mode || 'plate' }, function(res){
    if (res.status !== 'success') {
      Swal.fire({ icon:'warning', title:'No active dispatch', text: res.message || 'No active dispatch found.', footer: '<a href="#" id="addQueueFromAlert">Add to dispatcher queue</a>' });
      currentDispatch = null;
      $('#dispatchCard').hide(); $('#emptyCard').show();
      $(document).off('click','#addQueueFromAlert').on('click','#addQueueFromAlert', function(e){
        e.preventDefault();
        Swal.close();
        addToQueue(payload);
      });
      return;
    }
    renderDispatch(res.dispatch);
  });
}

function addToQueue(plate){
  $.post('php/operations/gate_queue_add.php', { truck_plate: plate || $('#manualPlate').val() || '' }, function(res){
    Swal.fire({ icon: res.status==='success'?'success':'error', text: res.message, timer: 1800, showConfirmButton:false });
  }, 'json').fail(function(){ Swal.fire({icon:'error',text:'Network error'}); });
}

function logMovement(direction){
  if (!currentDispatch) { Swal.fire({icon:'info',text:'Pick a dispatch first.'}); return; }
  $.post('php/operations/gate_checkin.php', {
    d_id: currentDispatch.d_id,
    direction: direction,
    truck_plate: currentDispatch.d_truck || '',
    trailer_code: currentDispatch.d_trailer || '',
    genset_code: currentDispatch.d_genset || '',
    driver_id: currentDispatch.driver_id || 0,
    qr_payload: currentDispatch._scanned_payload || ''
  }, function(res){
    var icon = res.status === 'success' ? (res.verified==1 ? 'success' : 'warning') : 'error';
    var html = '<div>' + escapeHtml(res.message || '') + '</div>';
    if (res.mismatch_reason) html += '<div class="mt-2 text-danger"><b>Mismatch:</b> ' + escapeHtml(res.mismatch_reason) + '</div>';
    Swal.fire({ icon: icon, html: html });
    if (res.status === 'success') {
      currentDispatch = null;
      $('#dispatchCard').hide(); $('#emptyCard').show();
      $('#manualPlate').val('').focus();
    }
  }, 'json').fail(function(){ Swal.fire({icon:'error',text:'Network error'}); });
}

$('#manualForm').on('submit', function(e){
  e.preventDefault();
  var v = ($('#manualPlate').val() || '').trim().toUpperCase();
  if (!v) return;
  lookup(v, 'plate');
});

$('#logIn').on('click',  function(){ logMovement('IN'); });
$('#logOut').on('click', function(){ logMovement('OUT'); });
$('#addToQueue').on('click', function(){ addToQueue(currentDispatch ? currentDispatch.d_truck : ''); });

// QR scanner — uses html5-qrcode.
$('#startScan').on('click', function(){
  $('#scannerBox').show();
  $('#startScan').hide(); $('#stopScan').show();
  if (!qr) qr = new Html5Qrcode('qrReader');
  qr.start({ facingMode: 'environment' }, { fps: 10, qrbox: 250 }, function(decodedText){
    qr.stop().then(function(){
      $('#scannerBox').hide();
      $('#startScan').show(); $('#stopScan').hide();
    });
    // QR payload may be a d_id, booking_no, or plate — let backend figure out via mode=qr.
    lookup(decodedText, 'qr');
  }, function(){ /* per-frame fail callback intentionally empty */ });
});
$('#stopScan').on('click', function(){
  if (qr) qr.stop().catch(function(){});
  $('#scannerBox').hide();
  $('#startScan').show(); $('#stopScan').hide();
});

setTimeout(function(){ $('#manualPlate').focus(); }, 200);
</script>
<?php include 'gate/_layout_bottom.php'; ?>
