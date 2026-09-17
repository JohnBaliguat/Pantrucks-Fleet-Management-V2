<?php
include 'php/config/config.php';

$id = $_SESSION['user_id'];
$query = "SELECT * FROM drivers WHERE driver_id = '$id'";
$result = $conn->query($query);
$data = ($result)->fetch();
$fname = $data['driver_fname'];
$firstLetter = substr($data['driver_lname'], 0, 1);
$fullName = $data['driver_fname'] . ' ' . $data['driver_lname'];
$profileImage = !empty($data['driver_image']) ? 'php/assets/uploads/' . $data['driver_image'] : 'assets/images/profile/user-1.jpg';
?>

<!-- Modern Glassmorphism Navbar -->
<header class="modern-navbar">
  <div class="navbar-container">
    
    <!-- Left: Menu Toggle -->
    <!-- <button class="menu-toggle" id="headerCollapse" aria-label="Toggle menu">
      <i class="ti ti-menu-2"></i>
    </button> -->

    <!-- Center: Page Title (Mobile) / Breadcrumbs (Desktop) -->
    <div class="navbar-brand">
      <span class="brand-text d-none d-lg-block">Pantrucks Fleet</span>
      <span class="page-title d-lg-none" id="pageTitle">Dashboard</span>
    </div>

    <!-- Right: Actions -->
    <div class="navbar-actions">
      
      <!-- Notifications -->
      <button class="action-btn" id="notifToggle" aria-label="Notifications">
        <i class="ti ti-bell"></i>
        <span class="notif-badge" id="notifCount">0</span>
      </button>

      <!-- Profile Dropdown -->
      <div class="profile-dropdown">
        <button class="profile-trigger" id="profileToggle" aria-label="User menu">
          <img src="<?= $profileImage ?>" alt="<?= $fname ?>" class="avatar">
          <div class="user-info d-none d-md-block">
            <span class="user-name"><?= $fname ?> <?= $firstLetter ?>.</span>
            <span class="user-role">Driver</span>
          </div>
          <i class="ti ti-chevron-down ms-2 d-none d-md-block"></i>
        </button>

        <!-- Dropdown Menu -->
        <div class="dropdown-menu-modern" id="profileMenu">
          <div class="dropdown-header">
            <img src="<?= $profileImage ?>" alt="" class="dropdown-avatar">
            <div>
              <strong><?= $fullName ?></strong>
              <small>Driver ID: <?= $id ?></small>
            </div>
          </div>
          
          <div class="dropdown-body">
            <a href="driver-profile" class="dropdown-item">
              <i class="ti ti-user"></i>
              <span>My Profile</span>
              <i class="ti ti-chevron-right ms-auto"></i>
            </a>
            <a href="driver-unit" class="dropdown-item">
              <i class="ti ti-truck"></i>
              <span>My Units</span>
              <i class="ti ti-chevron-right ms-auto"></i>
            </a>
            <a href="driver-tripReport" class="dropdown-item">
              <i class="ti ti-clipboard-list"></i>
              <span>Trip Reports</span>
              <i class="ti ti-chevron-right ms-auto"></i>
            </a>
          </div>
          
          <div class="dropdown-footer">
            <a href="driver-logout" class="btn-logout">
              <i class="ti ti-logout"></i>
              <span>Sign Out</span>
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Mobile Search/Filter Bar (Optional) -->
  <div class="mobile-search-bar d-lg-none" id="mobileSearch">
    <div class="search-input-wrapper">
      <i class="ti ti-search"></i>
      <input type="text" placeholder="Search trips, bookings..." id="globalSearch">
    </div>
  </div>
</header>

<!-- Notifications Panel (Slide-over) -->
<div class="notif-panel" id="notifPanel">
  <div class="notif-header">
    <h6>Notifications</h6>
    <button class="btn-close-modern" id="closeNotif">
      <i class="ti ti-x"></i>
    </button>
  </div>
  <div class="notif-list" id="notifList">
    <div class="notif-empty">
      <i class="ti ti-bell-off"></i>
      <p>Loading…</p>
    </div>
  </div>
  <div class="notif-footer" style="padding:10px 14px;border-top:1px solid #eee;text-align:right;">
    <button type="button" id="notifMarkAll" class="btn btn-sm btn-link p-0" style="font-size:12px;">
      Mark all as read
    </button>
  </div>
</div>
<style>
  .notif-item { display:flex; gap:10px; padding:12px 14px; border-bottom:1px solid #f1f5f9;
                cursor:pointer; transition:background .15s; }
  .notif-item:hover { background:#f8fafc; }
  .notif-item.is-seen { opacity:.55; }
  .notif-item .ni-icon { width:34px; height:34px; border-radius:50%; background:#dbeafe;
                         color:#1d4ed8; display:flex; align-items:center; justify-content:center;
                         font-size:18px; flex-shrink:0; }
  .notif-item.type-verified    .ni-icon { background:#d1fae5; color:#065f46; }
  .notif-item.type-rejected    .ni-icon { background:#fee2e2; color:#991b1b; }
  .notif-item.type-maintenance .ni-icon { background:#fef3c7; color:#92400e; }
  .notif-item.type-message     .ni-icon { background:#e0e7ff; color:#3730a3; }
  .notif-item .ni-body { flex:1; min-width:0; }
  .notif-item .ni-title { font-weight:600; font-size:13px; color:#0f172a;
                          white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .notif-item .ni-text  { font-size:12px; color:#475569; line-height:1.35;
                          display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
                          overflow:hidden; }
  .notif-item .ni-time  { font-size:10px; color:#94a3b8; margin-top:3px; }
</style>

<!-- Overlay for mobile -->
<div class="navbar-overlay" id="navOverlay"></div>


<script>
// Toggle Profile Dropdown
document.getElementById('profileToggle').addEventListener('click', function(e) {
  e.stopPropagation();
  document.getElementById('profileMenu').classList.toggle('show');
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
  if (!e.target.closest('.profile-dropdown')) {
    document.getElementById('profileMenu').classList.remove('show');
  }
});

// Notifications Panel
document.getElementById('notifToggle').addEventListener('click', function() {
  document.getElementById('notifPanel').classList.add('show');
  document.getElementById('navOverlay').classList.add('show');
});

document.getElementById('closeNotif').addEventListener('click', function() {
  document.getElementById('notifPanel').classList.remove('show');
  document.getElementById('navOverlay').classList.remove('show');
});

document.getElementById('navOverlay').addEventListener('click', function() {
  document.getElementById('notifPanel').classList.remove('show');
  document.getElementById('navOverlay').classList.remove('show');
});

// ---- Driver notifications -------------------------------------------------
// localStorage tracks which notification IDs the driver has already seen,
// so the badge clears predictably without needing a per-row "read" column
// in every source table.
const NOTIF_SEEN_KEY = 'pt_notif_seen_v1';
function notifSeenIds() {
  try { return new Set(JSON.parse(localStorage.getItem(NOTIF_SEEN_KEY) || '[]')); }
  catch (e) { return new Set(); }
}
function notifSaveSeen(set) {
  // Keep only the last 500 ids so the store doesn't grow forever.
  const arr = Array.from(set).slice(-500);
  try { localStorage.setItem(NOTIF_SEEN_KEY, JSON.stringify(arr)); } catch (e) {}
}
function notifTimeAgo(iso) {
  if (!iso) return '';
  const t = new Date(iso.replace(' ', 'T') + 'Z');
  const secs = Math.max(0, (Date.now() - t.getTime()) / 1000);
  if (secs < 60)        return Math.floor(secs) + 's ago';
  if (secs < 3600)      return Math.floor(secs / 60) + 'm ago';
  if (secs < 86400)     return Math.floor(secs / 3600) + 'h ago';
  return Math.floor(secs / 86400) + 'd ago';
}
function notifEscape(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function fetchNotifications() {
  $.getJSON('php/fetch/driver_notifications.php', function(data) {
    if (!data || data.status !== 'success') return;
    const list  = document.getElementById('notifList');
    const badge = document.getElementById('notifCount');
    const seen  = notifSeenIds();
    const items = data.items || [];
    const unread = items.filter(it => !seen.has(it.id)).length;

    if (unread > 0) {
      badge.textContent = unread > 99 ? '99+' : unread;
      badge.classList.add('has-count');
      badge.style.display = '';
    } else {
      badge.textContent = '0';
      badge.classList.remove('has-count');
      badge.style.display = 'none';
    }

    if (!items.length) {
      list.innerHTML = '<div class="notif-empty"><i class="ti ti-bell-off"></i>' +
                       '<p>No new notifications</p></div>';
      return;
    }
    list.innerHTML = items.map(it => {
      const seenClass = seen.has(it.id) ? ' is-seen' : '';
      const href = it.link ? notifEscape(it.link) : 'javascript:void(0)';
      return '<a class="notif-item type-' + notifEscape(it.type) + seenClass + '" ' +
             'data-nid="' + notifEscape(it.id) + '" href="' + href + '">' +
               '<div class="ni-icon"><i class="ti ' + notifEscape(it.icon) + '"></i></div>' +
               '<div class="ni-body">' +
                 '<div class="ni-title">' + notifEscape(it.title) + '</div>' +
                 '<div class="ni-text">'  + notifEscape(it.body)  + '</div>' +
                 '<div class="ni-time">'  + notifTimeAgo(it.at)   + '</div>' +
               '</div>' +
             '</a>';
    }).join('');
  });
}

// Mark an item seen when the driver taps it.
$(document).on('click', '.notif-item', function() {
  const id = $(this).attr('data-nid');
  if (!id) return;
  const seen = notifSeenIds(); seen.add(id); notifSaveSeen(seen);
  $(this).addClass('is-seen');
  // Decrement the badge.
  const badge = document.getElementById('notifCount');
  const current = parseInt(badge.textContent, 10) || 0;
  const next = Math.max(0, current - 1);
  if (next === 0) { badge.style.display = 'none'; badge.classList.remove('has-count'); }
  badge.textContent = next;
});

// "Mark all as read" — capture every currently visible id.
$(document).on('click', '#notifMarkAll', function() {
  const seen = notifSeenIds();
  $('.notif-item[data-nid]').each(function() {
    seen.add($(this).attr('data-nid'));
    $(this).addClass('is-seen');
  });
  notifSaveSeen(seen);
  const badge = document.getElementById('notifCount');
  badge.textContent = '0'; badge.style.display = 'none'; badge.classList.remove('has-count');
});

// Initial fetch + periodic refresh (every 30 s — slower than chat-unread
// because notifications aren't time-critical).
fetchNotifications();
setInterval(fetchNotifications, 30000);

// Update page title based on current page
const pageTitles = {
  'driver-dashboard': 'Dashboard',
  'driver-unit': 'My Units',
  'driver-tripReport': 'Trip Reports',
  'driver-profile': 'My Profile'
};

const currentPage = window.location.pathname.split('/').pop() || 'driver-dashboard';
document.getElementById('pageTitle').textContent = pageTitles[currentPage] || 'Pantrucks';
</script>

<!-- Driver location gate: blocks the UI until Location permission is granted,
     then runs a 30s GPS heartbeat so dispatch sees a live position. -->
<script src="js/driver-location-gate.js?v=<?php echo @filemtime(__DIR__ . '/../js/driver-location-gate.js') ?: time(); ?>"></script>
