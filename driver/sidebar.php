<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$driverId = $_SESSION['user_id'] ?? '';
?>

<!-- Modern Sidebar - Desktop Only -->
<aside class="modern-sidebar" id="modernSidebar">
  <!-- Sidebar Header -->
  <div class="sidebar-header">
    <a href="driver-dashboard" class="sidebar-brand">
      <img src="assets/images/logos/pantrucks.png" alt="Pantrucks">
    </a>
    <!-- <button class="sidebar-collapse-btn" id="sidebarCollapseDesktop" title="Collapse sidebar">
      <i class="ti ti-panel-left-close"></i>
    </button> -->
  </div>

  <!-- User Profile Mini -->
  <div class="sidebar-profile">
    <div class="profile-avatar">
      <img src="php/assets/uploads/<?php echo !empty($_SESSION['driver_image']) ? $_SESSION['driver_image'] : 'user-profile.jpg'; ?>" alt="Profile">
      <span class="status-dot online"></span>
    </div>
    <div class="profile-info">
      <span class="profile-name"><?php echo $_SESSION['driver_fname'] ?? 'Driver'; ?></span>
      <span class="profile-role">Active Driver</span>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">
    <div class="nav-section">
      <span class="nav-label">Main Menu</span>
      
      <a href="driver-dashboard" class="nav-link <?php echo $currentPage == 'driver-dashboard' ? 'active' : ''; ?>">
        <div class="nav-icon">
          <i class="ti ti-smart-home"></i>
        </div>
        <span class="nav-text">Dashboard</span>
        <?php if ($currentPage == 'driver-dashboard'): ?>
        <span class="nav-indicator"></span>
        <?php endif; ?>
      </a>

      <a href="driver-unit" class="nav-link <?php echo $currentPage == 'driver-unit' ? 'active' : ''; ?>">
        <div class="nav-icon">
          <i class="ti ti-truck"></i>
        </div>
        <span class="nav-text">My Units</span>
        <?php if ($currentPage == 'driver-unit'): ?>
        <span class="nav-indicator"></span>
        <?php endif; ?>
      </a>
    </div>

    <div class="nav-section">
      <span class="nav-label">Reports</span>

      <a href="driver-earnings" class="nav-link <?php echo $currentPage == 'driver-earnings' ? 'active' : ''; ?>">
        <div class="nav-icon">
          <i class="ti ti-cash"></i>
        </div>
        <span class="nav-text">My Earnings</span>
        <?php if ($currentPage == 'driver-earnings'): ?>
        <span class="nav-indicator"></span>
        <?php endif; ?>
      </a>

      <a href="driver-payrollHistory" class="nav-link <?php echo $currentPage == 'payroll-history' ? 'active' : ''; ?>">
        <div class="nav-icon">
          <i class="ti ti-calendar-stats"></i>
        </div>
        <span class="nav-text">Payroll History</span>
        <?php if ($currentPage == 'payroll-history'): ?>
        <span class="nav-indicator"></span>
        <?php endif; ?>
      </a>

      <a href="driver-tripReport" class="nav-link <?php echo $currentPage == 'driver-tripReport' ? 'active' : ''; ?>">
        <div class="nav-icon">
          <i class="ti ti-clipboard-list"></i>
        </div>
        <span class="nav-text">Trip Reports</span>
        <?php if ($currentPage == 'driver-tripReport'): ?>
        <span class="nav-indicator"></span>
        <?php endif; ?>
      </a>

      <a href="driver-driversReport" class="nav-link <?php echo $currentPage == 'driver-driversReport' ? 'active' : ''; ?>" style="display: none;">
        <div class="nav-icon">
          <i class="ti ti-file-analytics"></i>
        </div>
        <span class="nav-text">Driver's Report</span>
      </a>
    </div>

    <div class="nav-section">
      <span class="nav-label">Account</span>
      
      <a href="driver-profile" class="nav-link <?php echo $currentPage == 'driver-profile' ? 'active' : ''; ?>">
        <div class="nav-icon">
          <i class="ti ti-user-circle"></i>
        </div>
        <span class="nav-text">Profile</span>
        <?php if ($currentPage == 'driver-profile'): ?>
        <span class="nav-indicator"></span>
        <?php endif; ?>
      </a>

      <a href="driver-logout" class="nav-link text-danger">
        <div class="nav-icon">
          <i class="ti ti-logout"></i>
        </div>
        <span class="nav-text">Sign Out</span>
      </a>
    </div>
  </nav>

  <!-- Emergency SOS - Sidebar Version -->
  <div class="sidebar-footer">
    <button class="sos-button-sidebar" onclick="sosAlertSidebar()">
      <i class="ti ti-alert-triangle"></i>
      <span>Emergency SOS</span>
    </button>
    <p class="sidebar-help">Press for immediate assistance</p>
  </div>
</aside>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Mobile Header (Alternative to bottom nav for some layouts) -->
<div class="mobile-header d-lg-none">
  <button class="menu-toggle" id="mobileMenuToggle">
    <i class="ti ti-menu-2"></i>
  </button>
  <span class="mobile-title">Pantrucks</span>
  <button class="notif-toggle" id="mobileNotifToggle">
    <i class="ti ti-bell"></i>
  </button>
</div>


<script>
// Sidebar Toggle (Desktop)
document.getElementById('sidebarCollapseDesktop')?.addEventListener('click', function() {
  const sidebar = document.getElementById('modernSidebar');
  sidebar.classList.toggle('collapsed');
  
  // Store preference
  localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
});

// Mobile Menu Toggle
document.getElementById('mobileMenuToggle')?.addEventListener('click', function() {
  const sidebar = document.getElementById('modernSidebar');
  const overlay = document.getElementById('sidebarOverlay');
  
  sidebar.classList.add('show');
  overlay.classList.add('show');
  document.body.style.overflow = 'hidden';
});

// Close Sidebar
document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
  const sidebar = document.getElementById('modernSidebar');
  const overlay = document.getElementById('sidebarOverlay');
  
  sidebar.classList.remove('show');
  overlay.classList.remove('show');
  document.body.style.overflow = '';
});

// Restore sidebar state
document.addEventListener('DOMContentLoaded', function() {
  const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
  if (isCollapsed) {
    document.getElementById('modernSidebar')?.classList.add('collapsed');
  }
});

// SOS Alert
function sosAlertSidebar() {
  Swal.fire({
    title: '🚨 Emergency SOS',
    text: 'Send immediate distress signal to dispatch?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Send SOS',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    backdrop: 'rgba(0,0,0,0.8)'
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: "php/operations/send_sos.php",
        type: "POST",
        data: { driver_id: '<?php echo $driverId; ?>' },
        success: function(response) {
          Swal.fire({
            title: 'SOS Sent!',
            text: 'Help is on the way. Stay calm.',
            icon: 'success',
            confirmButtonColor: '#10b981'
          });
        },
        error: function() {
          Swal.fire('Error', 'Failed to send SOS. Try calling emergency.', 'error');
        }
      });
    }
  });
}
</script>
