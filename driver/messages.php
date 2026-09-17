<?php
session_start();
if (($_SESSION['user_type'] ?? '') !== 'Driver') { header("Location: driver-index.php?route=login"); exit(); }
$pageTitle = 'Messages';
$activeNav = 'messages';
include 'driver/_layout_top.php';
?>
<style>
  .recipient-tabs { display:flex; gap:6px; padding:8px 8px 0; background:#fff; border-bottom:1px solid #eee; }
  .recipient-tabs .rcp-tab {
    flex:1; text-align:center; padding:8px 6px; border-radius:8px 8px 0 0;
    font-size:13px; font-weight:600; color:#475569; cursor:pointer; user-select:none;
    background:#f1f5f9; border:1px solid transparent; border-bottom:0;
  }
  .recipient-tabs .rcp-tab.active { background:#0d6efd; color:#fff; }
  .recipient-tabs .rcp-tab .rcp-badge {
    display:inline-block; min-width:18px; padding:0 5px; margin-left:4px; font-size:11px;
    background:#dc3545; color:#fff; border-radius:10px;
  }
</style>
<div class="card mt-3"><div class="card-body" style="padding:0;">
  <div class="recipient-tabs" id="recipientTabs">
    <div class="rcp-tab active" data-role="dispatcher"><i class="ti ti-headset"></i> Dispatcher <span class="rcp-badge" data-badge="dispatcher" style="display:none;">0</span></div>
    <div class="rcp-tab"        data-role="rescue"><i class="ti ti-life-buoy"></i> Rescuer <span class="rcp-badge" data-badge="rescue" style="display:none;">0</span></div>
    <div class="rcp-tab"        data-role="maintenance"><i class="ti ti-tool"></i> Maintenance <span class="rcp-badge" data-badge="maintenance" style="display:none;">0</span></div>
  </div>
  <div id="messageList" style="height:55vh;overflow-y:auto;padding:12px;background:#f5f7fa;"></div>
  <div style="border-top:1px solid #eee;padding:8px;display:flex;gap:8px;background:#fff;">
    <input id="msgInput" class="form-control-modern" placeholder="Message dispatcher…" style="flex:1;" autocomplete="off">
    <button id="sendMsg" class="btn-modern btn-primary-modern" style="width:auto;padding:8px 16px;"><i class="ti ti-send"></i></button>
  </div>
</div></div>

<script>
function escapeHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}

// Hash the sender id to a colour so different senders stay
// visually distinguishable across the chat. Saturation kept low so
// it doesn't fight the rest of the UI.
function senderColor(seed) {
  var s = String(seed || ''); var h = 0;
  for (var i = 0; i < s.length; i++) { h = (h * 31 + s.charCodeAt(i)) >>> 0; }
  return 'hsl(' + (h % 360) + ', 55%, 40%)';
}

// Per-recipient state so switching tabs doesn't re-render the whole thread.
var threads = {
  dispatcher:  { lastMsgId: 0, lastSenderKey: '', placeholder: 'Message dispatcher…' },
  rescue:      { lastMsgId: 0, lastSenderKey: '', placeholder: 'Message rescuer…' },
  maintenance: { lastMsgId: 0, lastSenderKey: '', placeholder: 'Message maintenance…' }
};
var activeRole = 'dispatcher';

function fetchMessages(replace) {
  var t = threads[activeRole];
  $.getJSON('php/fetch/messages.php', { since: t.lastMsgId, to_role: activeRole }, function (res) {
    if (res.status !== 'success') return;
    var $list = $('#messageList');
    if (replace) { $list.empty(); t.lastSenderKey = ''; }
    res.rows.forEach(function (m) {
      t.lastMsgId = Math.max(t.lastMsgId, parseInt(m.msg_id, 10));
      var mine    = m.from_role === 'driver';
      var sender  = m.sender_label || (m.from_role === 'driver' ? 'You' : m.from_role);
      var sendKey = m.from_role + ':' + m.from_id;
      var showHeader = !mine && sendKey !== t.lastSenderKey;
      var color   = senderColor(sendKey);
      t.lastSenderKey = sendKey;

      var headerHtml = showHeader
        ? '<div style="font-size:11px;font-weight:700;color:' + color + ';margin-bottom:2px;">'
          + '<i class="ti ti-user-circle"></i> ' + escapeHtml(sender) + '</div>'
        : '';

      var bubble = '<div style="display:flex;flex-direction:column;align-items:' + (mine ? 'flex-end' : 'flex-start') + ';margin-bottom:6px;">'
        + headerHtml
        + '<div style="max-width:78%;padding:8px 12px;border-radius:12px;font-size:14px;'
        + (mine
            ? 'background:#0d6efd;color:#fff;'
            : 'background:#fff;color:#222;border:1px solid #e5e7eb;border-left:3px solid ' + color + ';')
        + '">'
        + escapeHtml(m.body)
        + '<div style="font-size:10px;opacity:.7;margin-top:4px;">' + escapeHtml(m.sent_at) + '</div>'
        + '</div></div>';
      $list.append(bubble);
    });
    $list.scrollTop($list.prop('scrollHeight'));
  });
}

function refreshUnreadBadges() {
  ['dispatcher', 'rescue', 'maintenance'].forEach(function (role) {
    $.getJSON('php/fetch/messages_unread.php', { to_role: role }, function (res) {
      if (!res || res.status !== 'success') return;
      var n = parseInt(res.count, 10) || 0;
      var $b = $('[data-badge="' + role + '"]');
      if (n > 0) { $b.text(n).show(); } else { $b.hide(); }
    });
  });
}

function switchRecipient(role) {
  if (!threads[role] || role === activeRole) return;
  activeRole = role;
  $('.rcp-tab').removeClass('active');
  $('.rcp-tab[data-role="' + role + '"]').addClass('active');
  $('#msgInput').attr('placeholder', threads[role].placeholder);
  threads[role].lastMsgId = 0;
  fetchMessages(true);
  refreshUnreadBadges();
}

$('#recipientTabs').on('click', '.rcp-tab', function () {
  switchRecipient($(this).data('role'));
});

fetchMessages(true);
refreshUnreadBadges();
setInterval(function () { fetchMessages(false); }, 5000);
setInterval(refreshUnreadBadges, 8000);

function sendMessage() {
  var body = $('#msgInput').val().trim();
  if (!body) return;
  $('#msgInput').val('');
  $.post('php/operations/send_message.php', { body: body, to_role: activeRole }, function (res) {
    if (res.status === 'success' || res.status === 'queued') fetchMessages(false);
    else Swal.fire({ icon: 'error', text: res.message });
  }, 'json').fail(function () {
    Swal.fire({ icon: 'error', text: 'Network error' });
  });
}
$('#sendMsg').on('click', sendMessage);
$('#msgInput').on('keydown', function (e) { if (e.key === 'Enter') sendMessage(); });
</script>
<?php include 'driver/_layout_bottom.php'; ?>
