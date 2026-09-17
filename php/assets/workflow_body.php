<?php
// Shared body for the Workflow Timeline page. Required vars: $role.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Workflow Timeline</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  <style>
    .summary-strip {
      display: grid;
      grid-template-columns: 2fr repeat(3, minmax(0, 1fr));
      gap: 12px;
    }
    .summary-card {
      background: linear-gradient(180deg, #ffffff, #f8fbff);
      border: 1px solid #e5eef9;
      border-radius: 16px;
      padding: 14px 16px;
      box-shadow: 0 8px 20px rgba(13, 110, 253, 0.05);
    }
    .summary-card.booking {
      background: linear-gradient(135deg, #f7fbff 0%, #edf5ff 100%);
      border-color: #d6e7ff;
    }
    .summary-card .label {
      display: block;
      color: #6c7a8c;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      margin-bottom: 6px;
    }
    .summary-card .value {
      color: #102a43;
      font-size: 18px;
      font-weight: 700;
      line-height: 1.3;
    }
    .summary-card .sub {
      color: #6c7a8c;
      font-size: 12px;
      margin-top: 4px;
    }
    .summary-route {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      margin-top: 8px;
      color: #365486;
      font-size: 13px;
      font-weight: 600;
    }
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
      line-height: 1;
      background: #eef2f7;
      color: #52606d;
    }
    .status-pill.done { background: #dff7e7; color: #157347; }
    .status-pill.curr { background: #dbeafe; color: #0d6efd; }
    .status-pill.warn { background: #fff3cd; color: #997404; }
    .status-pill.danger { background: #fde2e1; color: #c92a2a; }
    .status-pill.muted { background: #edf2f7; color: #61758a; }
    .pipeline {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
    }
    .pipeline .stage {
      padding: 8px 14px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
      background: #edf2f7;
      color: #6c7a8c;
      border: 1px solid #e1e8f0;
    }
    .pipeline .stage.done {
      background: #dff7e7;
      color: #157347;
      border-color: #c5e8d0;
    }
    .pipeline .stage.curr {
      background: #dbeafe;
      color: #0d6efd;
      border-color: #b7d4fe;
    }
    .pipeline .arrow {
      color: #adb5bd;
      font-weight: 700;
    }
    .dispatch-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 12px;
    }
    .dispatch-card {
      border: 1px solid #e6edf5;
      border-radius: 16px;
      padding: 14px 16px;
      background: #fff;
      box-shadow: 0 6px 18px rgba(16, 42, 67, 0.05);
    }
    .dispatch-card .head {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: flex-start;
      margin-bottom: 10px;
    }
    .dispatch-card .id {
      color: #6c7a8c;
      font-size: 12px;
      font-weight: 700;
    }
    .dispatch-card .driver {
      color: #102a43;
      font-size: 15px;
      font-weight: 700;
      margin: 2px 0 0;
    }
    .dispatch-card .truck {
      color: #365486;
      font-size: 12px;
      font-weight: 700;
      margin-top: 2px;
    }
    .dispatch-meta {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px 12px;
    }
    .dispatch-meta .item span {
      display: block;
      font-size: 11px;
      color: #829ab1;
      text-transform: uppercase;
      letter-spacing: .04em;
      margin-bottom: 4px;
      font-weight: 700;
    }
    .dispatch-meta .item strong {
      color: #243b53;
      font-size: 13px;
      font-weight: 700;
    }
    .timeline {
      position: relative;
      margin-top: 20px;
    }
    .timeline:before {
      content: '';
      position: absolute;
      left: 10px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: #d7e3f3;
    }
    .timeline .ev {
      position: relative;
      margin-bottom: 14px;
      padding-left: 34px;
    }
    .timeline .ev:before {
      content: '';
      position: absolute;
      left: 4px;
      top: 8px;
      width: 14px;
      height: 14px;
      border-radius: 50%;
      border: 3px solid #fff;
      background: #0d6efd;
      box-shadow: 0 0 0 1px #bfd4ff;
    }
    .timeline .ev .cardline {
      border: 1px solid #e6edf5;
      border-radius: 14px;
      background: #fff;
      padding: 12px 14px;
      box-shadow: 0 6px 16px rgba(16, 42, 67, 0.04);
    }
    .timeline .ev .topline {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: flex-start;
      margin-bottom: 6px;
      flex-wrap: wrap;
    }
    .timeline .ev .stage {
      font-weight: 700;
      color: #102a43;
    }
    .timeline .ev .meta {
      color: #6c7a8c;
      font-size: 12px;
    }
    .timeline .ev .notes {
      color: #334e68;
      font-size: 13px;
      line-height: 1.5;
      margin-top: 6px;
      word-break: break-word;
    }
    .timeline .ev .chips {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
      margin-top: 8px;
    }
    .timeline .ev .chip {
      padding: 4px 8px;
      border-radius: 999px;
      background: #f5f7fa;
      color: #52606d;
      font-size: 11px;
      font-weight: 700;
    }
    .timeline .ev.done:before { background: #198754; box-shadow: 0 0 0 1px #b7dfc5; }
    .timeline .ev.warn:before { background: #f59f00; box-shadow: 0 0 0 1px #ffe08a; }
    .timeline .ev.danger:before { background: #dc3545; box-shadow: 0 0 0 1px #f1aeb5; }
    @media (max-width: 991.98px) {
      .summary-strip { grid-template-columns: 1fr; }
      .dispatch-meta { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
       data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-5"><img src="assets/images/logos/pantrucks.png" width="122" alt=""></div>
      <h3 class="text-white mb-0 fs-5">Workflow Timeline</h3>
    </div>

    <?php include __DIR__ . '/../../' . $role . '/sidebar.php'; ?>

    <div class="body-wrapper">
      <?php include __DIR__ . '/../../' . $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="card mt-3"><div class="card-body">
            <h4 class="card-title">Workflow Timeline</h4>
            <p class="card-subtitle">Track the booking from assignment to closure, including delivery exceptions, rejections, and verification steps.</p>

            <div class="row g-2 mt-3">
              <div class="col-md-9"><input list="bnList" class="form-control" id="bookingSearch" placeholder="Booking number"></div>
              <div class="col-md-3"><button class="btn btn-primary w-100" id="loadTimeline"><i class="ti ti-search"></i> Load</button></div>
            </div>
            <datalist id="bnList"></datalist>

            <div id="summary" class="mt-3"></div>
            <div id="pipelineBox" class="mt-3"></div>
            <div id="dispatchTable" class="mt-3"></div>
            <div id="timeline" class="timeline"></div>
          </div></div>
          <div class="py-6 px-6 text-center"><p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p></div>
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
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script>
  function escapeHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}

  const STAGES = [
    'order_created', 'dispatcher_assigned', 'driver_accepted',
    'gate_cleared',  'en_route', 'delivered',
    'pod_captured',  'billing_closed', 'client_notified'
  ];
  const STAGE_LABEL = {
    order_created:        'Order Created',
    dispatcher_assigned:  'Dispatcher Assigned',
    driver_accepted:      'Driver Accepted',
    gate_cleared:         'Gate Clearance',
    en_route:             'En Route',
    delivered:            'Delivered',
    pod_captured:         'POD Captured',
    billing_closed:       'Billing Closed',
    client_notified:      'Client Notified',
    pending_verification: 'To Be Verified',
    pod_rejected:         'POD Rejected',
    trailer_jackup:       'Trailer Jack-up',
    picked_up:            'Picked Up',
    on_the_way:           'On The Way',
    arrived:              'Arrived at Destination',
    reassigned:           'Reassigned',
    driver_declined:      'Driver Declined'
  };

  function formatDateTime(value){
    if (!value || value === 'â€”' || value === '-') return '—';
    const d = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString('en-PH', {
      year: 'numeric',
      month: 'short',
      day: '2-digit',
      hour: 'numeric',
      minute: '2-digit'
    });
  }

  function labelStage(stage) {
    return STAGE_LABEL[stage] || String(stage || '').replace(/_/g, ' ').replace(/\b\w/g, function(c){ return c.toUpperCase(); });
  }

  function stageTone(stage) {
    if (['pod_rejected', 'driver_declined'].includes(stage)) return 'danger';
    if (['pending_verification', 'billing_closed'].includes(stage)) return 'warn';
    if (['order_created', 'dispatcher_assigned', 'driver_accepted', 'gate_cleared', 'en_route', 'delivered', 'pod_captured', 'client_notified', 'picked_up', 'on_the_way', 'arrived', 'trailer_jackup'].includes(stage)) return 'done';
    return 'muted';
  }

  function cleanNote(note) {
    let text = String(note || '').trim();
    if (!text) return '';
    text = text.replace(/\s*\|\s*driver_IdNumber=[^|]+/gi, '');
    text = text.replace(/\s*\|\s*released_from_driver=\d+/gi, '');
    text = text.replace(/\s*\|\s*photo-[^|]+/gi, '');
    text = text.replace(/\s*\|\s*lat=[^|]+/gi, '');
    text = text.replace(/\s*\|\s*lng=[^|]+/gi, '');
    text = text.replace(/\bparent_d_id=\d+\b/gi, 'linked parent dispatch');
    text = text.replace(/\bdispatch #(\d+)\b/gi, 'dispatch #$1');
    return text.replace(/\s{2,}/g, ' ').trim();
  }

  function extractChips(note) {
    const text = String(note || '');
    const chips = [];
    const wfMatch = text.match(/\bStatus:\s*([a-z_]+)/i);
    if (wfMatch) chips.push(labelStage(wfMatch[1].toLowerCase()));
    const containerMatch = text.match(/\bcontainer=([A-Z0-9]+)/i);
    if (containerMatch) chips.push('Container ' + containerMatch[1]);
    const trailerMatch = text.match(/\bTrailer\s+([A-Z0-9-]+)/i);
    if (trailerMatch) chips.push('Trailer ' + trailerMatch[1]);
    const driverNumMatch = text.match(/\bdriver_IdNumber=([A-Z0-9-]+)/i);
    if (driverNumMatch) chips.push('Driver ID ' + driverNumMatch[1]);
    return chips;
  }

  function refreshBookingList(q){
    $.getJSON('php/fetch/list_booking_nos.php', { q: q || '' }, function(res){
      if (res.status !== 'success') return;
      const $dl = $('#bnList').empty();
      res.rows.forEach(function(r){
        $dl.append('<option value="' + r.booking_no + '">' + (r.costumer || '') + '</option>');
      });
    });
  }
  $('#bookingSearch').on('focus input', function(){ refreshBookingList($(this).val()); });

  function renderPipeline(allStagesReached, currStage){
    const html = STAGES.map(function(s, i){
      let cls = 'stage';
      if (allStagesReached.has(s) && s !== currStage) cls += ' done';
      if (s === currStage) cls += ' curr';
      const sep = i < STAGES.length - 1 ? '<span class="arrow">&rarr;</span>' : '';
      return '<span class="' + cls + '">' + escapeHtml(labelStage(s)) + '</span>' + sep;
    }).join('');
    return '<div class="pipeline">' + html + '</div>';
  }

  function renderStatusPill(stage, current){
    const tone = current ? 'curr' : stageTone(stage);
    return '<span class="status-pill ' + tone + '">' + escapeHtml(labelStage(stage)) + '</span>';
  }

  function renderSummary(booking, dispatches) {
    const currentDispatch = dispatches.length ? dispatches[dispatches.length - 1] : null;
    const currentStage = currentDispatch ? currentDispatch.workflow_stage : '';
    return '<div class="summary-strip">'
      + '<div class="summary-card booking">'
      +   '<span class="label">Booking</span>'
      +   '<div class="value">' + escapeHtml(booking.booking_no || '?') + '</div>'
      +   '<div class="sub">' + escapeHtml(booking.costumer || 'No customer') + ' • ' + escapeHtml(booking.booking_type || 'Local') + '</div>'
      +   '<div class="summary-route">'
      +     '<span>' + escapeHtml(booking.trip_from || '—') + '</span>'
      +     '<span>→</span>'
      +     '<span>' + escapeHtml(booking.trip_to || '—') + '</span>'
      +   '</div>'
      + '</div>'
      + '<div class="summary-card">'
      +   '<span class="label">Current Status</span>'
      +   '<div class="value">' + renderStatusPill(currentStage, true) + '</div>'
      +   '<div class="sub">' + (currentDispatch ? 'Dispatch #' + currentDispatch.d_id : 'No dispatch yet') + '</div>'
      + '</div>'
      + '<div class="summary-card">'
      +   '<span class="label">Container</span>'
      +   '<div class="value">' + escapeHtml(booking.container || '—') + '</div>'
      +   '<div class="sub">' + escapeHtml(booking.container_status || 'No container status') + '</div>'
      + '</div>'
      + '<div class="summary-card">'
      +   '<span class="label">Dispatch Count</span>'
      +   '<div class="value">' + String(dispatches.length) + '</div>'
      +   '<div class="sub">Includes retries and reassignment flow</div>'
      + '</div>'
      + '</div>';
  }

  function renderDispatchCards(dispatches) {
    if (!dispatches.length) return '';
    return '<div class="dispatch-grid">'
      + dispatches.map(function(d){
          const amount = d.billing_amount && parseFloat(d.billing_amount) > 0
            ? escapeHtml(d.billing_currency || '') + ' ' + parseFloat(d.billing_amount).toFixed(2)
            : '—';
          return '<div class="dispatch-card">'
            + '<div class="head">'
            +   '<div>'
            +     '<div class="id">Dispatch #' + escapeHtml(d.d_id) + '</div>'
            +     '<div class="driver">' + escapeHtml(d.d_driverName || 'Unassigned driver') + '</div>'
            +     '<div class="truck">' + escapeHtml(d.d_truck || 'No truck') + '</div>'
            +   '</div>'
            +   renderStatusPill(d.workflow_stage, false)
            + '</div>'
            + '<div class="dispatch-meta">'
            +   '<div class="item"><span>Last Updated</span><strong>' + escapeHtml(formatDateTime(d.workflow_updated_at)) + '</strong></div>'
            +   '<div class="item"><span>Driver Accepted</span><strong>' + escapeHtml(formatDateTime(d.driver_accepted_at)) + '</strong></div>'
            +   '<div class="item"><span>Billing Closed</span><strong>' + escapeHtml(formatDateTime(d.billing_closed_at)) + '</strong></div>'
            +   '<div class="item"><span>Client Notified</span><strong>' + escapeHtml(formatDateTime(d.client_notified_at)) + '</strong></div>'
            +   '<div class="item"><span>Amount</span><strong>' + amount + '</strong></div>'
            + '</div>'
            + '</div>';
        }).join('')
      + '</div>';
  }

  function renderTimeline(events) {
    if (!events.length) {
      return '<div class="text-muted">No timeline events for this booking yet.</div>';
    }

    return events.map(function(e){
      const tone = stageTone(e.stage);
      const chips = extractChips(e.notes);
      const actorBits = [];
      actorBits.push(e.actor_role ? e.actor_role : 'system');
      if (e.actor_name) actorBits.push(e.actor_name);
      if (e.d_id) actorBits.push('Dispatch #' + e.d_id);
      return '<div class="ev ' + tone + '">'
        + '<div class="cardline">'
        +   '<div class="topline">'
        +     '<div class="stage">' + escapeHtml(labelStage(e.stage)) + '</div>'
        +     '<div>' + renderStatusPill(e.stage, false) + '</div>'
        +   '</div>'
        +   '<div class="meta">' + escapeHtml(formatDateTime(e.event_at)) + ' • ' + escapeHtml(actorBits.join(' • ')) + '</div>'
        +   (cleanNote(e.notes) ? '<div class="notes">' + escapeHtml(cleanNote(e.notes)) + '</div>' : '')
        +   (chips.length ? '<div class="chips">' + chips.map(function(ch){ return '<span class="chip">' + escapeHtml(ch) + '</span>'; }).join('') + '</div>' : '')
        + '</div>'
        + '</div>';
    }).join('');
  }

  function load(){
    const bn = ($('#bookingSearch').val() || '').trim();
    if (!bn) { Swal.fire({icon:'info',text:'Pick a booking.'}); return; }
    $.getJSON('php/fetch/workflow_timeline.php', { booking_no: bn }, function(res){
      if (res.status !== 'success') { Swal.fire({icon:'error',text:res.message}); return; }

      const booking = res.booking || {};
      const dispatches = Array.isArray(res.dispatches) ? res.dispatches : [];
      const events = Array.isArray(res.events) ? res.events : [];

      $('#summary').html(renderSummary(booking, dispatches));

      const reached = new Set();
      let currStage = '';
      dispatches.forEach(function(d){
        reached.add(d.workflow_stage);
        currStage = d.workflow_stage;
      });
      events.forEach(function(e){
        if (STAGES.includes(e.stage)) reached.add(e.stage);
      });
      $('#pipelineBox').html(renderPipeline(reached, currStage));

      $('#dispatchTable').html(renderDispatchCards(dispatches));
      $('#timeline').html(renderTimeline(events));
    });
  }
  $('#loadTimeline').on('click', load);
  $('#bookingSearch').on('change', load);

  (function(){
    const u = new URL(location.href);
    const bn = u.searchParams.get('bn') || u.searchParams.get('booking_no');
    if (bn) { $('#bookingSearch').val(bn); load(); }
  })();
  </script>
  <?php include __DIR__ . '/realtime_alerts.php'; ?>
</body>
</html>
