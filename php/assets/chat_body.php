<?php
// Shared body for the dispatcher / admin Chat page.
// Required vars: $role.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chat</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  <style>
    .chat-wrap   { display:grid; grid-template-columns: 320px 1fr; gap:12px; min-height:70vh; }
    @media (max-width: 768px) { .chat-wrap { grid-template-columns: 1fr; } }
    .chat-list   { background:#fff; border:1px solid #e5e7eb; border-radius:10px; max-height:75vh; overflow-y:auto; }
    .chat-thread { background:#fff; border:1px solid #e5e7eb; border-radius:10px; display:flex; flex-direction:column; min-height:70vh; }
    .thread-row  { padding:10px 12px; border-bottom:1px solid #f1f5f9; cursor:pointer; }
    .thread-row.active { background:#eef4ff; }
    .thread-row:hover { background:#f8fafc; }
    .thread-row .name  { font-weight:600; }
    .thread-row .last  { font-size:12px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .thread-row .meta  { display:flex; align-items:center; justify-content:space-between; font-size:11px; color:#94a3b8; }
    .role-tabs { display:flex; gap:4px; padding:6px; background:#f8fafc; border-bottom:1px solid #e5e7eb; border-radius:10px 10px 0 0; }
    .role-tabs .rl-tab { flex:1; text-align:center; padding:6px 4px; font-size:12px; font-weight:600; color:#475569; border-radius:6px; cursor:pointer; user-select:none; }
    .role-tabs .rl-tab.active { background:#0d6efd; color:#fff; }
    .role-tabs .rl-tab .rl-badge { display:inline-block; min-width:18px; padding:0 5px; margin-left:4px; font-size:11px; background:#dc3545; color:#fff; border-radius:10px; }
    .messages-area    { flex:1; overflow-y:auto; padding:14px; background:#f5f7fa; }
    .composer        { border-top:1px solid #e5e7eb; padding:10px; background:#fff; display:flex; gap:8px; }
    .bubble          { max-width:75%; padding:8px 12px; border-radius:12px; font-size:14px; margin-bottom:8px; }
    .bubble.driver   { background:#fff; color:#222; border:1px solid #e5e7eb; }
    .bubble.disp     { background:#0d6efd; color:#fff; margin-left:auto; }
    .bubble .ts      { font-size:10px; opacity:0.7; margin-top:4px; }
    .empty-state     { text-align:center; padding:40px; color:#94a3b8; }
  </style>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
       data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-5"><img src="assets/images/logos/pantrucks.png" width="122" alt=""></div>
      <h3 class="text-white mb-0 fs-5">Chat</h3>
    </div>

    <?php include __DIR__ . '/../../' . $role . '/sidebar.php'; ?>

    <div class="body-wrapper">
      <?php include __DIR__ . '/../../' . $role . '/navbar.php'; ?>
      <div class="body-wrapper-inner">
        <div class="container-fluid">
          <div class="card mt-3"><div class="card-body">
            <div class="d-md-flex align-items-center mb-3">
              <div>
                <h4 class="card-title mb-0">Chat</h4>
                <p class="card-subtitle">Messages with drivers, rescuers, and maintenance. Pick a tab, then click a name to open the thread.</p>
              </div>
              <div class="ms-auto">
                <button class="btn btn-sm btn-outline-secondary" id="refreshThreads"><i class="ti ti-refresh"></i></button>
              </div>
            </div>

            <div class="chat-wrap">
              <div>
                <div class="role-tabs" id="roleTabs">
                  <div class="rl-tab active" data-role="driver"><i class="ti ti-steering-wheel"></i> Drivers <span class="rl-badge" data-badge="driver" style="display:none;">0</span></div>
                  <div class="rl-tab"        data-role="rescue"><i class="ti ti-life-buoy"></i> Rescuers <span class="rl-badge" data-badge="rescue" style="display:none;">0</span></div>
                  <div class="rl-tab"        data-role="maintenance"><i class="ti ti-tool"></i> Maintenance <span class="rl-badge" data-badge="maintenance" style="display:none;">0</span></div>
                </div>
                <div style="padding:8px;background:#fff;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">
                  <input type="search" id="threadSearch" class="form-control form-control-sm" placeholder="Search by name…" autocomplete="off">
                </div>
                <div class="chat-list" id="threadsBox" style="border-top-left-radius:0;border-top-right-radius:0;border-top:0;"><div class="empty-state">Loading…</div></div>
              </div>
              <div class="chat-thread">
                <div id="threadHeader" style="padding:10px 14px;border-bottom:1px solid #e5e7eb;font-weight:600;background:#fafbfd;">
                  Pick a driver on the left to open the thread.
                </div>
                <div id="messagesArea" class="messages-area">
                  <div class="empty-state">No thread selected.</div>
                </div>
                <div class="composer">
                  <input id="msgInput" class="form-control" placeholder="Type a reply…" autocomplete="off" disabled>
                  <button id="sendBtn" class="btn btn-primary" disabled><i class="ti ti-send"></i></button>
                </div>
              </div>
            </div>
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

  // Active tab — which role (counterpart) we're listing threads for.
  let activeRole       = 'driver';
  let activeTargetId   = null;
  let activeTargetName = '';
  let lastMsgId        = 0;
  let threadRows       = [];   // last loaded rows, used by the search filter

  function roleLabel(r) {
    return r === 'rescue' ? 'Rescuer' : (r === 'maintenance' ? 'Maintenance' : 'Driver');
  }

  function renderThreads() {
    var q = String($('#threadSearch').val() || '').trim().toLowerCase();
    var list = threadRows.filter(function (r) {
      if (!q) return true;
      return [r.target_name, r.sub_label, r.last_body]
        .map(function (x) { return String(x || '').toLowerCase(); })
        .some(function (x) { return x.indexOf(q) !== -1; });
    });
    if (!list.length) {
      var msg = q
        ? 'No ' + roleLabel(activeRole).toLowerCase() + 's match "' + escapeHtml(q) + '".'
        : 'No ' + roleLabel(activeRole).toLowerCase() + 's available.';
      $('#threadsBox').html('<div class="empty-state">' + msg + '</div>');
      return;
    }
    var html = list.map(function (r) {
      var unread = parseInt(r.unread, 10) || 0;
      var badge = unread ? '<span class="badge bg-danger">' + unread + '</span>' : '';
      var cls = (parseInt(activeTargetId, 10) === parseInt(r.target_id, 10)) ? 'active' : '';
      return '<div class="thread-row ' + cls + '" data-id="' + r.target_id + '" data-name="' + escapeHtml(r.target_name) + '">'
        + '<div class="d-flex align-items-center gap-2">'
        +   '<span class="name flex-fill">' + escapeHtml(r.target_name) + '</span>' + badge
        + '</div>'
        + '<div class="last">' + escapeHtml(r.last_body || '(no messages)') + '</div>'
        + '<div class="meta"><span>' + escapeHtml(r.sub_label || '—') + '</span><span>' + escapeHtml(r.last_at || '') + '</span></div>'
        + '</div>';
    }).join('');
    $('#threadsBox').html(html);
  }

  function loadThreads() {
    $.getJSON('php/fetch/chat_threads.php', { role: activeRole }, function (res) {
      if (res.status !== 'success') return;
      threadRows = res.rows || [];
      renderThreads();
    });
  }

  $('#threadSearch').on('input', renderThreads);
  $('#refreshThreads').on('click', function () { loadThreads(); refreshTabBadges(); });

  function refreshTabBadges() {
    ['driver', 'rescue', 'maintenance'].forEach(function (r) {
      $.getJSON('php/fetch/messages_unread.php', { from_role: r }, function (res) {
        if (!res || res.status !== 'success') return;
        var n = parseInt(res.count, 10) || 0;
        var $b = $('[data-badge="' + r + '"]');
        if (n > 0) { $b.text(n).show(); } else { $b.hide(); }
      });
    });
  }

  function loadMessages(append) {
    if (!activeTargetId) return;
    $.getJSON('php/fetch/messages.php', {
      target_role: activeRole,
      target_id:   activeTargetId,
      since:       append ? lastMsgId : 0
    }, function (res) {
      if (res.status !== 'success') return;
      const $area = $('#messagesArea');
      if (!append) $area.empty();
      if (!res.rows.length && !append) {
        $area.html('<div class="empty-state">No messages in this thread yet.</div>');
        return;
      }
      var lastKey = '';
      res.rows.forEach(function (m) {
        lastMsgId = Math.max(lastMsgId, parseInt(m.msg_id, 10));
        // "Mine" = the dispatcher/admin viewing this page (anyone not the counterpart).
        var counterpartRoles = ['driver', 'rescue', 'maintenance'];
        var mine = counterpartRoles.indexOf(m.from_role) === -1;
        var cls = mine ? 'disp' : 'driver';
        var label = m.sender_label || roleLabel(m.from_role);
        var key = m.from_role + ':' + m.from_id;
        var header = (key !== lastKey) ? '<div style="font-size:11px;font-weight:600;color:#475569;margin:6px 0 2px;"><i class="ti ti-user-circle"></i> ' + escapeHtml(label) + '</div>' : '';
        lastKey = key;
        $area.append(header + '<div class="bubble ' + cls + '">' + escapeHtml(m.body)
          + '<div class="ts">' + escapeHtml(m.sent_at || '') + '</div></div>');
      });
      $area.scrollTop($area.prop('scrollHeight'));
    });
  }

  function switchRole(role) {
    if (role === activeRole) return;
    activeRole = role;
    activeTargetId = null;
    activeTargetName = '';
    lastMsgId = 0;
    threadRows = [];
    $('#threadSearch').val('');
    $('.rl-tab').removeClass('active');
    $('.rl-tab[data-role="' + role + '"]').addClass('active');
    $('#threadHeader').html('Pick a ' + roleLabel(role).toLowerCase() + ' on the left to open the thread.');
    $('#messagesArea').html('<div class="empty-state">No thread selected.</div>');
    $('#msgInput').prop('disabled', true).val('').attr('placeholder', 'Type a reply…');
    $('#sendBtn').prop('disabled', true);
    loadThreads();
  }
  $('#roleTabs').on('click', '.rl-tab', function () { switchRole($(this).data('role')); });

  $('#threadsBox').on('click', '.thread-row', function () {
    activeTargetId = $(this).data('id');
    activeTargetName = $(this).data('name');
    lastMsgId = 0;
    $('.thread-row').removeClass('active');
    $(this).addClass('active');
    $('#threadHeader').html('<i class="ti ti-user"></i> ' + escapeHtml(activeTargetName) + ' <span class="text-muted small">— ' + roleLabel(activeRole) + '</span>');
    $('#msgInput').prop('disabled', false).focus();
    $('#sendBtn').prop('disabled', false);
    loadMessages(false);
    setTimeout(function () { loadThreads(); refreshTabBadges(); }, 800);
  });

  function sendReply() {
    if (!activeTargetId) return;
    const body = $('#msgInput').val().trim();
    if (!body) return;
    $('#msgInput').val('');
    $.post('php/operations/send_message.php', {
      body:    body,
      to_role: activeRole,
      to_id:   activeTargetId
    }, function (res) {
      if (res.status === 'success' || res.status === 'queued') loadMessages(false);
      else Swal.fire({ icon: 'error', text: res.message });
    }, 'json').fail(function () { Swal.fire({icon:'error',text:'Network error'}); });
  }
  $('#sendBtn').on('click', sendReply);
  $('#msgInput').on('keydown', function (e) { if (e.key === 'Enter') sendReply(); });

  // Initial + periodic refresh.
  loadThreads();
  refreshTabBadges();
  setInterval(loadThreads, 15000);
  setInterval(refreshTabBadges, 8000);
  setInterval(function () { if (activeTargetId) loadMessages(true); }, 5000);
  </script>
  <?php include __DIR__ . '/realtime_alerts.php'; ?>
</body>
</html>
