// Real-time alerts — polls the unread-messages endpoint, shows a
// SweetAlert toast for each new chat message, and updates any
// "#unreadDot" / "#chatUnread" badge in the page chrome.
//
// Configuration (set by the server before this script loads):
//   window.PT_ROLE          — 'Driver' | 'Dispatcher' | 'Admin' | …
//   window.PT_ALERT_INTERVAL — poll period in ms (default 8 000)
//   window.PT_CHAT_HREF     — where toasts route on click; default
//                             auto-detected from role.
//
// Persists the last-seen msg_id in sessionStorage so re-opens of the
// page don't replay the same toasts.

(function () {
  if (typeof window.PT_ROLE !== 'string') return;
  if (typeof $ !== 'function') return;          // jQuery is loaded everywhere by now.
  // Avoid double-init when the script gets included by both _layout_top and a body.
  if (window.__PT_ALERTS_BOOTED__) return;
  window.__PT_ALERTS_BOOTED__ = true;

  var ROLE     = window.PT_ROLE;
  var INTERVAL = parseInt(window.PT_ALERT_INTERVAL, 10) || 8000;
  var CHAT_HREF = window.PT_CHAT_HREF || (
    ROLE === 'Driver'     ? 'driver-messages' :
    ROLE === 'Dispatcher' ? 'dispatch-chat'   :
    ROLE === 'Admin'      ? 'chat'            :
    'dispatch-chat'
  );

  var STORAGE_KEY = 'pt_alerts_last_msg_id_' + ROLE;
  function lastSeen()    { return parseInt(sessionStorage.getItem(STORAGE_KEY) || '0', 10); }
  function setLastSeen(v){ try { sessionStorage.setItem(STORAGE_KEY, String(v)); } catch (e) {} }

  // ---- Tiny chime via WebAudio so we don't ship a binary asset. ----
  var audioCtx = null;
  function chime() {
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      var t = audioCtx.currentTime;
      [880, 660].forEach(function (freq, i) {
        var o = audioCtx.createOscillator();
        var g = audioCtx.createGain();
        o.type = 'sine';
        o.frequency.setValueAtTime(freq, t + i * 0.12);
        g.gain.setValueAtTime(0.0001, t + i * 0.12);
        g.gain.exponentialRampToValueAtTime(0.10, t + i * 0.12 + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, t + i * 0.12 + 0.18);
        o.connect(g); g.connect(audioCtx.destination);
        o.start(t + i * 0.12);
        o.stop(t + i * 0.12 + 0.20);
      });
    } catch (e) { /* audio blocked — silent toast is fine */ }
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function bumpUnreadBadges(count) {
    // Driver bottom-nav dot.
    if (count > 0) $('#unreadDot').show(); else $('#unreadDot').hide();
    // Dispatcher / Admin sidebar pill (gracefully handles "0" rendering).
    var $pill = $('#chatUnread');
    if ($pill.length) {
      if (count > 0) { $pill.text(count).show(); } else { $pill.hide(); }
    }
  }

  function showToast(msg) {
    if (typeof Swal === 'undefined') return;
    var preview = (msg.body || '').length > 90 ? msg.body.substring(0, 87) + '…' : msg.body || '';
    var html = '<div style="text-align:left;font-size:14px;">'
             + '<div style="font-weight:700;margin-bottom:2px;">' + escapeHtml(msg.sender_label || 'Message') + '</div>'
             + '<div style="color:#475569;">' + escapeHtml(preview) + '</div>'
             + '</div>';
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'info',
      iconColor: '#0d6efd',
      html: html,
      showConfirmButton: false,
      showCloseButton: true,
      timer: 6000,
      timerProgressBar: true,
      didOpen: function (toast) {
        toast.style.cursor = 'pointer';
        toast.addEventListener('click', function (e) {
          if (e.target.closest('.swal2-close')) return;   // don't navigate when closing
          window.location.href = CHAT_HREF;
        });
      }
    });
  }

  function poll() {
    if (document.hidden) return;   // don't waste cycles when tab is in background; push handles that
    $.getJSON('php/fetch/messages_unread.php', { include_recent: 1, since: lastSeen() }, function (res) {
      if (!res || res.status !== 'success') return;
      bumpUnreadBadges(parseInt(res.count, 10) || 0);

      if (!res.recent || !res.recent.length) return;
      var maxId = lastSeen();
      var seen = false;
      res.recent.forEach(function (m) {
        var id = parseInt(m.msg_id, 10) || 0;
        if (id <= maxId) return;
        maxId = id; seen = true;
        showToast(m);
      });
      if (seen) {
        chime();
        setLastSeen(maxId);
      }
    }).fail(function () { /* network blip — try next tick */ });
  }

  // Prime the "last seen" cursor so we don't dump the entire backlog
  // on the user the first time they load the page after this rolls out.
  if (lastSeen() === 0) {
    $.getJSON('php/fetch/messages_unread.php', function (res) {
      if (res && res.status === 'success') {
        bumpUnreadBadges(parseInt(res.count, 10) || 0);
      }
      // Then prime to the newest existing message.
      $.getJSON('php/fetch/messages_unread.php', { include_recent: 1, since: 0 }, function (r2) {
        if (r2 && r2.recent && r2.recent.length) {
          var maxId = 0;
          r2.recent.forEach(function (m) { var id = parseInt(m.msg_id, 10) || 0; if (id > maxId) maxId = id; });
          setLastSeen(maxId);
        }
      });
    });
  } else {
    poll();
  }
  setInterval(poll, INTERVAL);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
})();
