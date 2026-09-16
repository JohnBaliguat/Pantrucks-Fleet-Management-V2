<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Driver") {
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>Dashboard - Driver</title>
  <link rel="manifest" href="manifest.webmanifest">
  <meta name="theme-color" content="#0d6efd">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="PT Driver">
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="apple-touch-icon" href="assets/images/logos/LogoFleet.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="assets/css/driver-modern.css" />
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
</head>
<body>
  <div class="page-wrapper" id="main-wrapper">
    
    <!-- Mobile Header -->
    <div class="app-topstrip">
      <div class="d-flex align-items-center justify-content-between w-100">
        <!-- <img src="assets/images/logos/pantrucks.png" alt="Logo">
        <h3>Pantrucks Fleet</h3> -->
      </div>
    </div>

    <!-- Sidebar (Desktop) -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="body-wrapper">
      <!--  Header Start -->
        <?php include 'navbar.php'; ?>
      <div class="container-fluid">
        
        <?php
          // Phase 7 — Shift status banner.
          include "php/config/config.php";
          require_once __DIR__ . '/../php/helpers/settings_helper.php';
          // Admin can make the container pickup photo optional from Settings.
          $pickupPhotoRequired = pt_setting_bool($conn, 'require_pickup_photo', true);
          $driverId = (int)$_SESSION['user_id'];
          $shift = null;
          $stmtS = $conn->prepare("SELECT ds_id, truck_code, started_at FROM driver_shift WHERE driver_id = ? AND ended_at IS NULL ORDER BY ds_id DESC LIMIT 1");
          $stmtS->execute([$driverId]);
          $shift = $stmtS->fetch();

          // Earnings — per Pantrucks payroll cycle (6→20 and 21→5).
          require_once __DIR__ . '/../php/helpers/payroll_period.php';
          $period     = payroll_period_for();       // current cutoff
          $prevPeriod = payroll_previous_period();  // previous cutoff
          $earnPeriod = 0.0; $tripsPeriod = 0;
          $earnPrev   = 0.0; $tripsPrev   = 0;

          // "Pending verification" — trips whose dispatch hasn't been verified yet.
          // These are NOT included in the earnings totals; surfaced separately
          // so the driver knows what's still in the pipeline.
          $earnPending = 0.0; $tripsPending = 0;

          try {
              // Only verified dispatches count toward earnings.
              $stmtE = $conn->prepare(
                  "SELECT COALESCE(SUM(t.piece_rate), 0) AS earnings, COUNT(*) AS trips
                   FROM trips t
                   INNER JOIN dispatch d ON d.d_id = t.d_id
                   WHERE d.driver_id = ?
                     AND DATE(d.d_datetime) BETWEEN ?::date AND ?::date
                     AND d.verified_at IS NOT NULL
                     AND (t.cancelled_at IS NULL OR (t.foul_trip = TRUE AND t.foul_approved = TRUE))"
              );
              $stmtE->execute([$driverId, $period['start'], $period['end']]);
              if ($r = $stmtE->fetch()) {
                  $earnPeriod  = (float)$r['earnings'];
                  $tripsPeriod = (int)$r['trips'];
              }
              $stmtE->execute([$driverId, $prevPeriod['start'], $prevPeriod['end']]);
              if ($r = $stmtE->fetch()) {
                  $earnPrev  = (float)$r['earnings'];
                  $tripsPrev = (int)$r['trips'];
              }

              // Pending — current cutoff, dispatch not yet verified.
              $stmtP = $conn->prepare(
                  "SELECT COALESCE(SUM(t.piece_rate), 0) AS earnings, COUNT(*) AS trips
                   FROM trips t
                   INNER JOIN dispatch d ON d.d_id = t.d_id
                   WHERE d.driver_id = ?
                     AND DATE(d.d_datetime) BETWEEN ?::date AND ?::date
                     AND d.verified_at IS NULL
                     AND (t.cancelled_at IS NULL OR (t.foul_trip = TRUE AND t.foul_approved = TRUE))"
              );
              $stmtP->execute([$driverId, $period['start'], $period['end']]);
              if ($r = $stmtP->fetch()) {
                  $earnPending  = (float)$r['earnings'];
                  $tripsPending = (int)$r['trips'];
              }
          } catch (Throwable $e) {
              // piece_rate / verified_at column may not exist on legacy DBs — degrade silently.
          }

          // ---- Earnings projection (pace-based estimate for the current cutoff) ----
          $projected = 0.0; $projSoFar = 0.0; $projDaysLeft = 0; $projShow = false;
          try {
              $pStart = new DateTime($period['start']);
              $pEnd   = new DateTime($period['end']);
              $today  = new DateTime('today');
              $totalDays = (int)$pStart->diff($pEnd)->days + 1;
              $elapsed   = (int)$pStart->diff($today)->days + 1;
              if ($elapsed < 1)          $elapsed = 1;
              if ($elapsed > $totalDays) $elapsed = $totalDays;
              $projSoFar    = $earnPeriod + $earnPending;      // all trips booked this cutoff
              $projDaysLeft = max(0, $totalDays - $elapsed);
              if ($projSoFar > 0) {
                  $projected = ($projSoFar / $elapsed) * $totalDays;
                  $projShow  = true;
              }
          } catch (Throwable $e) { /* projection optional */ }

          // ---- Opt-in leaderboard (Top 10 by verified earnings this cutoff) ----
          // Privacy: shows first name + rank only, and only for drivers who opted in.
          $lbOptIn = false; $lbMyFname = ''; $lbTop = []; $lbMyRank = 0;
          $lbMyEarn = $earnPeriod; $lbInTop10 = false; $lbGapToTop10 = 0.0;
          try {
              pt_ensure_column($conn, 'drivers', 'leaderboard_optin', 'BOOLEAN NOT NULL DEFAULT FALSE');

              $st = $conn->prepare("SELECT driver_fname, COALESCE(leaderboard_optin, FALSE) AS optin FROM drivers WHERE driver_id = ?");
              $st->execute([$driverId]);
              if ($rw0 = $st->fetch()) { $lbMyFname = (string)$rw0['driver_fname']; $lbOptIn = (bool)$rw0['optin']; }

              if ($lbOptIn) {
                  // Note: refresh is per page-load today; cache/schedule this if driver
                  // count grows, to keep Supabase egress down (see rebuild guide).
                  $stL = $conn->prepare(
                      "SELECT dr.driver_id, dr.driver_fname,
                              COALESCE(SUM(t.piece_rate),0) AS earnings,
                              COUNT(t.trip_id) AS trips
                       FROM drivers dr
                       JOIN dispatch d ON d.driver_id = dr.driver_id
                       JOIN trips t    ON t.d_id = d.d_id
                       WHERE COALESCE(dr.leaderboard_optin, FALSE) = TRUE
                         AND DATE(d.d_datetime) BETWEEN ?::date AND ?::date
                         AND d.verified_at IS NOT NULL
                         AND (t.cancelled_at IS NULL OR (t.foul_trip = TRUE AND t.foul_approved = TRUE))
                       GROUP BY dr.driver_id, dr.driver_fname
                       HAVING COALESCE(SUM(t.piece_rate),0) > 0
                       ORDER BY earnings DESC, dr.driver_fname ASC"
                  );
                  $stL->execute([$period['start'], $period['end']]);
                  $rows = $stL->fetchAll();
                  foreach ($rows as $i => $rw) {
                      if ((int)$rw['driver_id'] === $driverId) { $lbMyRank = $i + 1; $lbMyEarn = (float)$rw['earnings']; }
                  }
                  $lbTop     = array_slice($rows, 0, 10);
                  $lbInTop10 = ($lbMyRank >= 1 && $lbMyRank <= 10);
                  if ($lbMyRank > 10 && isset($rows[9])) {
                      $lbGapToTop10 = max(0.0, (float)$rows[9]['earnings'] - $lbMyEarn);
                  }
              }
          } catch (Throwable $e) { /* leaderboard optional — degrade silently */ }
?>
        <div class="card mt-5 <?= $shift ? 'border-success' : 'border-warning' ?>" style="border-width:2px;">
          <div class="card-body d-flex align-items-center gap-3" style="padding:14px;">
            <i class="ti <?= $shift ? 'ti-truck-delivery' : 'ti-alert-triangle' ?> fs-3 <?= $shift ? 'text-success' : 'text-warning' ?>"></i>
            <div class="flex-fill">
              <?php if ($shift): ?>
                <div class="fw-bold">Shift active &mdash; truck <?= htmlspecialchars($shift['truck_code']) ?></div>
                <div class="text-muted small">Started <?= htmlspecialchars($shift['started_at']) ?></div>
              <?php else: ?>
                <div class="fw-bold">No active shift</div>
                <div class="text-muted small">Run the pre-departure checklist to start.</div>
              <?php endif; ?>
            </div>
            <?php if ($shift): ?>
              <button class="btn-modern btn-outline-modern" id="endShiftBtn" style="width:auto;padding:8px 14px;border-color:#dc3545;color:#dc3545;">
                <i class="ti ti-power"></i> End Shift
              </button>
            <?php else: ?>
              <a href="driver-checklist" class="btn-modern btn-primary-modern" style="width:auto;padding:8px 14px;">Start Shift</a>
            <?php endif; ?>
          </div>
        </div>

        <?php
          // Pre-compute active / completed dispatch counts using the same
          // classification as the booking cards below so the numbers match
          // what the driver actually sees in the list.
          $activeBookings = 0;
          $completedBookings = 0;
          try {
              $stmtA = $conn->prepare(
                  "SELECT COUNT(*)
                   FROM dispatch d
                   WHERE d.driver_id = ?
                     AND d.workflow_stage IN (
                       'dispatcher_assigned','reassigned','driver_accepted',
                       'gate_cleared','en_route','delivered','pending_verification'
                     )
                     AND NOT EXISTS (
                       SELECT 1 FROM trips t WHERE t.d_id = d.d_id AND t.trip_status = 'Done'
                     )"
              );
              $stmtA->execute([$driverId]);
              $activeBookings = (int)$stmtA->fetchColumn();

              $stmtC = $conn->prepare(
                  "SELECT COUNT(*)
                   FROM dispatch d
                   WHERE d.driver_id = ?
                     AND (
                       d.workflow_stage IN ('pod_captured','billing_closed','client_notified')
                       OR EXISTS (
                         SELECT 1 FROM trips t WHERE t.d_id = d.d_id AND t.trip_status = 'Done'
                       )
                     )"
              );
              $stmtC->execute([$driverId]);
              $completedBookings = (int)$stmtC->fetchColumn();
          } catch (Throwable $e) {
              // Leave counts at 0 if the queries can't run.
          }
        ?>
        <!-- Stats Overview -->
        <div class="stats-grid mb-4 mt-3">
          <div class="stat-card">
            <div class="stat-icon">
              <i class="ti ti-clock"></i>
            </div>
            <div class="stat-info">
              <h6>Active</h6>
              <span class="stat-value" id="booking"><?= $activeBookings ?></span>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon success">
              <i class="ti ti-circle-check"></i>
            </div>
            <div class="stat-info">
              <h6>Completed</h6>
              <span class="stat-value" id="totalCount"><?= $completedBookings ?></span>
            </div>
          </div>

        </div>

        <!-- Earnings Snapshot — current payroll cutoff (VERIFIED ONLY) -->
        <a href="driver-earnings?date_from=<?= $period['start'] ?>&date_to=<?= $period['end'] ?>" class="text-decoration-none" style="color:inherit;">
          <div class="card mb-2" style="background:linear-gradient(135deg,#1f5eff,#6a8dff);color:#fff;border:none;border-radius:16px;">
            <div class="card-body d-flex align-items-center" style="padding:16px;">
              <div class="flex-fill">
                <div style="font-size:12px; opacity:.85; text-transform:uppercase; letter-spacing:.05em;">My Earnings — Current Cutoff</div>
                <div style="font-size:14px; opacity:.9; margin-top:2px;"><?= htmlspecialchars($period['label']) ?></div>
                <div style="font-size:30px; font-weight:800; line-height:1.1; margin-top:4px;">₱<?= number_format($earnPeriod, 2) ?></div>
                <div style="font-size:13px; opacity:.85;"><?= $tripsPeriod ?> verified trip<?= $tripsPeriod === 1 ? '' : 's' ?> · tap to see details</div>
              </div>
              <i class="ti ti-chevron-right" style="font-size:22px;"></i>
            </div>
          </div>
        </a>

        <?php if ($projShow): ?>
        <!-- Projected earnings — pace-based estimate for this cutoff -->
        <div class="card mb-2" style="border-radius:14px; border-left:4px solid #1f5eff;">
          <div class="card-body" style="padding:12px 16px;">
            <div class="d-flex align-items-center justify-content-between">
              <div style="font-size:11px; color:#6b7a90; text-transform:uppercase; letter-spacing:.05em;">Projected — This Cutoff</div>
              <span class="badge bg-primary-subtle text-primary" style="font-weight:700;">estimate</span>
            </div>
            <div style="font-size:24px; font-weight:800; line-height:1.1; margin-top:4px;">₱<?= number_format($projected, 2) ?></div>
            <div style="font-size:12px; color:#6b7a90; margin-top:2px;">
              ₱<?= number_format($projSoFar, 2) ?> so far · <?= $projDaysLeft ?> day<?= $projDaysLeft === 1 ? '' : 's' ?> left · based on your current pace
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($tripsPending > 0): ?>
        <!-- Pending verification — earnings-in-waiting -->
        <div class="card mb-2" style="border-left:4px solid #f59e0b; border-radius:14px;">
          <div class="card-body d-flex align-items-center" style="padding:12px 16px;">
            <i class="ti ti-clock-hour-3 fs-3" style="color:#f59e0b;"></i>
            <div class="flex-fill ms-3">
              <div style="font-weight:700;">Pending Verification</div>
              <div style="font-size:12px; color:#6b7a90;">
                ₱<?= number_format($earnPending, 2) ?> from <?= $tripsPending ?> trip<?= $tripsPending === 1 ? '' : 's' ?> · counts toward earnings once your dispatcher verifies the POD.
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Previous cutoff (small reference card) -->
        <a href="driver-earnings?date_from=<?= $prevPeriod['start'] ?>&date_to=<?= $prevPeriod['end'] ?>" class="text-decoration-none" style="color:inherit;">
          <div class="card mb-2" style="border-radius:14px;">
            <div class="card-body d-flex align-items-center" style="padding:12px 16px;">
              <div class="flex-fill">
                <div style="font-size:11px; color:#6b7a90; text-transform:uppercase; letter-spacing:.05em;">Previous Cutoff</div>
                <div style="font-size:13px; color:#6b7a90;"><?= htmlspecialchars($prevPeriod['label']) ?></div>
              </div>
              <div style="text-align:right;">
                <div style="font-weight:800; font-size:18px;">₱<?= number_format($earnPrev, 2) ?></div>
                <div style="font-size:11px; color:#6b7a90;"><?= $tripsPrev ?> trip<?= $tripsPrev === 1 ? '' : 's' ?></div>
              </div>
            </div>
          </div>
        </a>

        <!-- Leaderboard (opt-in; first name + rank only) -->
        <div class="card mb-2" style="border-radius:14px;">
          <div class="card-body" style="padding:14px 16px;">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div style="font-weight:700;"><i class="ti ti-trophy text-warning"></i> Top Earners</div>
              <div style="font-size:11px; color:#6b7a90;"><?= htmlspecialchars($period['label']) ?></div>
            </div>

            <form method="post" action="php/operations/toggle_leaderboard_optin.php" class="d-flex align-items-center justify-content-between mb-2" style="background:#f6f7f9; border-radius:10px; padding:8px 12px;">
              <span style="font-size:12px; color:#556; font-weight:600;">Show me on the leaderboard &mdash; first name &amp; rank only</span>
              <button type="submit" class="btn btn-sm <?= $lbOptIn ? 'btn-primary' : 'btn-outline-secondary' ?>" style="border-radius:999px; font-weight:700; padding:3px 12px; white-space:nowrap;">
                <?= $lbOptIn ? 'Joined &check;' : 'Join' ?>
              </button>
            </form>

            <?php if (!$lbOptIn): ?>
              <div style="font-size:12px; color:#6b7a90;">Join to see how you rank against other drivers this cutoff. Only your first name and rank are shown to others.</div>
            <?php elseif (empty($lbTop)): ?>
              <div style="font-size:12px; color:#6b7a90;">No verified earnings on the board yet this cutoff.</div>
            <?php else: ?>
              <?php foreach ($lbTop as $i => $rw): $rk = $i + 1; $isMe = ((int)$rw['driver_id'] === $driverId);
                    $rkColor = $rk===1?'#e0a419':($rk===2?'#9aa4b2':($rk===3?'#c2793f':'#8a94a2')); ?>
                <div class="d-flex align-items-center gap-2" style="padding:7px <?= $isMe ? '6px' : '0' ?>; <?= $i>0 ? 'border-top:1px solid #eef1f5;' : '' ?> <?= $isMe ? 'background:#eef4ff; border-radius:8px;' : '' ?>">
                  <div style="width:22px; text-align:center; font-weight:800; color:<?= $rkColor ?>;"><?= $rk ?></div>
                  <div style="width:26px; height:26px; border-radius:50%; background:#eef1f5; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; color:#556;"><?= strtoupper(htmlspecialchars(substr((string)$rw['driver_fname'],0,1))) ?></div>
                  <div class="flex-fill">
                    <div style="font-size:13px; font-weight:700;"><?= $isMe ? 'You &mdash; ' : '' ?><?= htmlspecialchars($rw['driver_fname']) ?></div>
                    <div style="font-size:11px; color:#6b7a90;"><?= (int)$rw['trips'] ?> trip<?= (int)$rw['trips']===1?'':'s' ?></div>
                  </div>
                  <div style="font-weight:800; font-size:13px;">₱<?= number_format((float)$rw['earnings'], 0) ?></div>
                </div>
              <?php endforeach; ?>

              <?php if (!$lbInTop10): ?>
                <div style="text-align:center; font-size:10px; color:#9aa4b2; letter-spacing:.08em; text-transform:uppercase; margin:8px 0 4px;">Your rank</div>
                <div class="d-flex align-items-center gap-2" style="padding:7px 6px; background:#eef4ff; border:1px solid rgba(31,94,255,.25); border-radius:8px;">
                  <div style="width:22px; text-align:center; font-weight:800; color:#1f5eff;"><?= $lbMyRank > 0 ? $lbMyRank : '&mdash;' ?></div>
                  <div style="width:26px; height:26px; border-radius:50%; background:#1f5eff; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; color:#fff;"><?= strtoupper(htmlspecialchars(substr($lbMyFname,0,1))) ?></div>
                  <div class="flex-fill">
                    <div style="font-size:13px; font-weight:700; color:#1f5eff;">You &mdash; <?= htmlspecialchars($lbMyFname) ?></div>
                    <div style="font-size:11px; color:#6b7a90;">
                      <?php if ($lbMyRank > 0): ?>₱<?= number_format($lbGapToTop10, 0) ?> to reach top 10<?php else: ?>Complete a verified trip to get ranked<?php endif; ?>
                    </div>
                  </div>
                  <div style="font-weight:800; font-size:13px;">₱<?= number_format($lbMyEarn, 0) ?></div>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Link to full payroll history -->
        <a href="driver-payrollHistory" class="text-decoration-none" style="color:inherit;">
          <div class="card mb-3" style="border-radius:14px;">
            <div class="card-body d-flex align-items-center" style="padding:12px 16px;">
              <i class="ti ti-calendar-stats fs-3 text-primary"></i>
              <div class="flex-fill ms-3">
                <div style="font-weight:700;">View Full Payroll History</div>
                <div style="font-size:12px; color:#6b7a90;">Earnings broken down by cutoff for the past 12 periods</div>
              </div>
              <i class="ti ti-chevron-right" style="font-size:18px; color:#6b7a90;"></i>
            </div>
          </div>
        </a>

        <a href="driver-hustling" class="text-decoration-none" style="color:inherit;">
          <div class="card mb-3" style="border-radius:14px;">
            <div class="card-body d-flex align-items-center" style="padding:12px 16px;">
              <i class="ti ti-container fs-3 text-dark"></i>
              <div class="flex-fill ms-3">
                <div style="font-weight:700;">DICT Hustling</div>
                <div style="font-size:12px; color:#6b7a90;">Log each container for today's hustling day</div>
              </div>
              <i class="ti ti-chevron-right" style="font-size:18px; color:#6b7a90;"></i>
            </div>
          </div>
        </a>

        <!-- Bookings Section -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span>My Bookings</span>
            <span class="badge bg-primary rounded-pill" id="activeCount"><?= $activeBookings ?> Active</span>
          </div>
          <div class="card-body p-0">
            
            <!-- Tabs -->
            <div class="p-3">
              <div class="tab-container">
                <button class="tab-btn active" data-target="all">All</button>
                <button class="tab-btn" data-target="active">Active</button>
                <button class="tab-btn" data-target="completed">Completed</button>
              </div>
            </div>

            <!-- Tab Content -->
            <div class="tab-content-wrapper px-3 pb-3">
              <?php
              include "php/config/config.php";
              $id = $_SESSION['user_id'];

              $sql = "SELECT d.d_id, d.booking_no, d.d_datetime, d.d_dispatcher, d.d_dispatchHub,
                      d.d_driverName, d.driver_id, d.d_truck, d.d_trailer, d.d_genset,
                      d.d_tripReceipt, d.d_ecs, d.costumer, d.workflow_stage,
                      t.trip_id, t.trip_type, t.trip_container, t.container_activity,
                      t.trip_containerStat, t.trip_haulingSegment, t.trip_haulingType,
                      t.trip_from, t.trip_to, t.km_run, t.trip_departureDateTime, t.trip_arrivalDateTime,
                      t.trip_pharrivalDateTime, t.deliver_location, t.deliver_dateTime,
                      t.withdraw_location, t.withdraw_dateTime, t.required_date, t.trip_status
                      FROM dispatch d
                      LEFT JOIN trips t ON d.d_id = t.d_id
                      WHERE d.driver_id = '$id'
                      ORDER BY d.d_datetime DESC";
              
              $result = $conn->query($sql);
              $bookings = [];
              while ($row = $result->fetch()) {
                $bookings[$row['d_id']]['info'] = $row;
                $bookings[$row['d_id']]['trips'][] = $row;
              }
              ?>

              <!-- All Bookings -->
              <div class="tab-panel active" id="all">
                <?php foreach ($bookings as $d_id => $data): 
                  $dispatch = $data['info'];
                  $trips = $data['trips'];
                  $status = end($trips)['trip_status'];
                  $hasDoneTrip = false;
                  foreach ($trips as $tripRow) {
                    if (($tripRow['trip_status'] ?? '') === 'Done') {
                      $hasDoneTrip = true;
                      break;
                    }
                  }
                  // Empty / Loaded — first meaningful container status across the trips.
                  $containerStat = '';
                  foreach ($trips as $tripRow) {
                    $cs = trim((string)($tripRow['trip_containerStat'] ?? ''));
                    if ($cs !== '' && $cs !== '-') { $containerStat = $cs; break; }
                  }
                ?>
                <?php
                  $wf = $dispatch['workflow_stage'] ?? 'dispatcher_assigned';
                  $displayDateRaw = trim((string)($dispatch['required_date'] ?? ''));
                  if ($displayDateRaw === '' || $displayDateRaw === '0000-00-00' || $displayDateRaw === '0000-00-00 00:00:00') {
                    $displayDateRaw = trim((string)($dispatch['d_datetime'] ?? ''));
                  }
                  $displayDate = '—';
                  if ($displayDateRaw !== '') {
                    $ts = strtotime($displayDateRaw);
                    if ($ts !== false && $ts > 0) {
                      $displayDate = date("M d, Y", $ts);
                    }
                  }
                  $isPending  = in_array($wf, ['dispatcher_assigned', 'reassigned'], true);
                  // 'delivered' belongs here too: the driver has tapped Delivered
                  // but still owes the POD. Without it the card renders no status
                  // steps and no POD button, stranding the driver on "Delivered".
                  $isAccepted = in_array($wf, ['driver_accepted', 'gate_cleared', 'en_route', 'delivered'], true);
                  $isAwaiting = $wf === 'pending_verification';
                  $isCompleted = in_array($wf, ['pod_captured', 'billing_closed', 'client_notified'], true);
                  $isDeclined = $wf === 'driver_declined';
                  $wfLabel = [
                    'dispatcher_assigned'  => 'Awaiting your accept',
                    'reassigned'           => 'Re-assigned to you',
                    'driver_accepted'      => 'Accepted',
                    'gate_cleared'         => 'Gate cleared',
                    'en_route'             => 'En route',
                    'delivered'            => 'Delivered',
                    'pending_verification' => 'Pending Verification',
                    'pod_captured'         => 'Completed',
                    'billing_closed'       => 'Completed (Billed)',
                    'client_notified'      => 'Completed (Notified)',
                    'driver_declined'      => 'Declined',
                    'reassigned_from'      => 'Superseded',
                  ][$wf] ?? $wf;

                  if ($hasDoneTrip && !$isCompleted && !$isDeclined) {
                    $isPending = false;
                    $isAccepted = false;
                    $isAwaiting = false;
                    $isCompleted = true;
                    $wfLabel = 'Completed';
                  }

                  if ($isDeclined) {
                    $statusClass = 'declined';
                    $statusText = 'Declined';
                  } elseif ($isCompleted || $status === 'Done') {
                    $statusClass = 'completed';
                    $statusText = 'Completed';
                  } elseif ($isPending || $isAccepted || $isAwaiting || $status === 'Active') {
                    $statusClass = 'active';
                    $statusText = 'Active';
                  } else {
                    $statusClass = 'pending';
                    $statusText = $status ?: 'Pending';
                  }
                  $wfBadgeClass = $isDeclined
                    ? 'wf-declined'
                    : (($isCompleted || $status === 'Done')
                        ? 'wf-completed'
                        : (($isPending || $isAccepted || $isAwaiting || $status === 'Active')
                            ? 'wf-active'
                            : 'wf-pending'));
                ?>
                <div class="booking-card" data-booking-id="<?= $d_id ?>" data-status="<?= strtolower($statusText) ?>" data-wf="<?= htmlspecialchars($wf) ?>">
                  <div class="booking-header">
                    <div>
                      <h6><?= $dispatch['booking_no'] ?></h6>
                      <span class="customer"><?= htmlspecialchars($dispatch['costumer']) ?></span>
                      <div class="meta">
                        <i class="ti ti-calendar"></i> <?= htmlspecialchars($displayDate) ?>
                        <span class="wf-badge <?= $wfBadgeClass ?> ms-2"><?= htmlspecialchars($wfLabel) ?></span>
                        <?php if ($containerStat !== ''): ?>
                          <?php $isLoaded = stripos($containerStat, 'load') !== false; ?>
                          <span class="badge ms-2 <?= $isLoaded ? 'bg-primary' : 'bg-secondary' ?>">
                            <i class="ti <?= $isLoaded ? 'ti-package' : 'ti-package-off' ?>"></i> <?= htmlspecialchars($containerStat) ?>
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                  </div>

                  <div class="booking-details" id="details-<?= $d_id ?>">
                    <div class="mb-3">
                      <small class="text-muted d-block mb-1">Assigned Vehicle</small>
                      <strong><?= $dispatch['d_truck'] ?></strong> • <?= $dispatch['d_trailer'] ?>
                    </div>

                    <?php if ($isPending): ?>
                      <div class="d-flex gap-2 mb-3 phase5-actions">
                        <button class="btn-modern btn-success-modern flex-fill phase5-accept" data-id="<?= $d_id ?>"><i class="ti ti-check"></i> Accept</button>
                        <button class="btn-modern btn-outline-modern flex-fill phase5-decline" data-id="<?= $d_id ?>"><i class="ti ti-x"></i> Decline</button>
                      </div>
                    <?php elseif ($isAccepted): ?>
                      <?php
                        // Determine the highest-progressed driver tap for this dispatch.
                        // Drives the visual hierarchy: completed steps green-disabled, next
                        // step primary blue, future steps faded outline.
                        $statusOrder  = ['picked_up', 'on_the_way', 'arrived', 'delivered'];
                        $statusLabels = ['picked_up' => 'Picked up', 'on_the_way' => 'On the way', 'arrived' => 'Arrived at destination', 'delivered' => 'Mark Delivered'];
                        $statusIcons  = ['picked_up' => 'ti-package', 'on_the_way' => 'ti-arrow-right', 'arrived' => 'ti-map-pin', 'delivered' => 'ti-check'];
                        $stmtX = $conn->prepare(
                            "SELECT stage FROM workflow_event
                             WHERE d_id = ? AND actor_role = 'driver'
                               AND stage IN ('picked_up','on_the_way','arrived','delivered')
                             ORDER BY we_id DESC LIMIT 1"
                        );
                        $stmtX->execute([$d_id]);
                        $latestRow   = $stmtX->fetch();
$latestStage = $latestRow['stage'] ?? '';
                        $latestIdx   = $latestStage !== '' ? array_search($latestStage, $statusOrder, true) : -1;
                      ?>
                      <small class="text-muted d-block mb-1"><i class="ti ti-route"></i> Trip status — tap each as it happens</small>
                      <div class="form-check form-switch mb-2">
                        <input class="form-check-input phase5-late-toggle" type="checkbox" id="lateToggle-<?= $d_id ?>" data-id="<?= $d_id ?>">
                        <label class="form-check-label small text-muted" for="lateToggle-<?= $d_id ?>">
                          <i class="ti ti-clock-edit"></i> No signal earlier? Let me set the actual times
                        </label>
                      </div>
                      <div class="d-flex flex-column gap-1 mb-3 phase5-status-list">
                        <?php foreach ($statusOrder as $i => $st):
                          $done   = $i <= $latestIdx;
                          $isNext = $i === $latestIdx + 1;
                          $cls = $done ? 'btn-success-modern' : ($isNext ? 'btn-primary-modern' : 'btn-outline-modern');
                          $opacity = $done ? '0.7' : ($isNext ? '1' : '0.55');
                        ?>
                          <button class="btn-modern <?= $cls ?> phase5-status text-start"
                                  data-id="<?= $d_id ?>"
                                  data-st="<?= $st ?>"
                                  data-container="<?= htmlspecialchars((string)(end($trips)['trip_container'] ?? '')) ?>"
                                  <?= $isNext ? '' : 'disabled' ?>
                                  style="display:flex;align-items:center;gap:10px;padding:10px 14px;opacity:<?= $opacity ?>;justify-content:flex-start;">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.4);font-weight:700;font-size:12px;"><?= $i + 1 ?></span>
                            <i class="ti <?= $statusIcons[$st] ?>"></i>
                            <span class="flex-fill"><?= $statusLabels[$st] ?></span>
                            <?php if ($done): ?><i class="ti ti-circle-check"></i><?php endif; ?>
                          </button>
                        <?php endforeach; ?>
                      </div>

<?php /* Documents & alt-flows hidden — the driver flow (Mark Delivered → Jack-up → POD)
         already routes through these steps, so the manual buttons aren't needed. */ ?>
<?php if (false): ?>
                      <small class="text-muted d-block mb-1"><i class="ti ti-files"></i> Documents &amp; alt-flows</small>
                      <div class="d-flex flex-wrap gap-1 mb-3">
                        <a href="driver-receipts?d_id=<?= $d_id ?>" class="btn-modern btn-outline-modern" style="flex:1;min-width:48%;font-size:12px;padding:6px;"><i class="ti ti-file"></i> Receipts</a>
                        <a href="driver-pod?d_id=<?= $d_id ?>" class="btn-modern btn-outline-modern" style="flex:1;min-width:48%;font-size:12px;padding:6px;"><i class="ti ti-camera"></i> POD</a>
                        <a href="driver-gateless?d_id=<?= $d_id ?>" class="btn-modern btn-outline-modern" style="flex:1;min-width:48%;font-size:12px;padding:6px;opacity:0.7;" title="No gate at destination? Capture GPS + photos here instead of POD."><i class="ti ti-map-pin"></i> Gateless</a>
                        <a href="driver-jackup?d_id=<?= $d_id ?>" class="btn-modern btn-outline-modern" style="flex:1;min-width:48%;font-size:12px;padding:6px;opacity:0.7;" title="Detach trailer at site (billing keeps running)"><i class="ti ti-trailer"></i> Jack-up</a>
                      </div>
<?php endif; ?>
                    <?php elseif ($isAwaiting): ?>
                      <div class="alert alert-info mb-3" style="padding:10px 14px;">
                        <i class="ti ti-clock"></i> Awaiting dispatcher verification of your POD. You'll get a push notification once it's confirmed.
                      </div>
                    <?php elseif ($isCompleted): ?>
                      <div class="alert alert-success mb-3" style="padding:10px 14px;">
                        <i class="ti ti-circle-check"></i> Trip completed. Nothing more to do.
                      </div>
                    <?php endif; ?>

                    <div class="trip-list">
                      <?php foreach ($trips as $trip): ?>
                      <div class="trip-item">
                        <div class="d-flex justify-content-between align-items-start">
                          <div>
                            <strong><?= $trip['container_activity'] ?></strong>
                            <div class="trip-route">
                              <span><?= $trip['trip_from'] ?></span>
                              <i class="ti ti-arrow-right"></i>
                              <span><?= $trip['trip_to'] ?></span>
                            </div>
                          </div>
                          <div class="text-end">
                            <span class="badge bg-light text-dark"><?= $trip['trip_haulingtype'] ?></span>
                            <?php $tcs = trim((string)($trip['trip_containerStat'] ?? '')); ?>
                            <?php if ($tcs !== '' && $tcs !== '-'): ?>
                              <span class="badge <?= stripos($tcs, 'load') !== false ? 'bg-primary' : 'bg-secondary' ?>"><?= htmlspecialchars($tcs) ?></span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                      <button class="btn-modern btn-outline-modern flex-fill view-map"
                              data-did="<?= (int)$d_id ?>"
                              data-from="<?= htmlspecialchars(end($trips)['trip_from']) ?>"
                              data-to="<?= htmlspecialchars(end($trips)['trip_to']) ?>"
                              data-trip='<?= json_encode(end($trips), JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                        <i class="ti ti-map"></i> Map
                      </button>
                      
                      <?php /* Legacy Complete button removed — Phase 5+ replaces it with the
                              Accept → status pills → POD → dispatcher verification flow. The
                              old `view-time` modal at the bottom of this page is still wired so
                              admin/dispatcher tools that deep-link to it keep working. */ ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Mobile Navigation -->
    <div class="mobile-nav">
      <a href="driver-dashboard" class="nav-item active">
        <i class="ti ti-smart-home"></i>
        <span>Home</span>
      </a>
      <a href="driver-unit" class="nav-item">
        <i class="ti ti-truck"></i>
        <span>Unit</span>
      </a>
      <a href="driver-messages" class="nav-item">
        <i class="ti ti-message-circle"></i>
        <span>Chat</span>
      </a>
      <a href="driver-tripReport" class="nav-item">
        <i class="ti ti-clipboard-list"></i>
        <span>Trip Report</span>
      </a>
      <a href="driver-breakdown" class="nav-item sos-btn">
        <i class="ti ti-alert-triangle"></i>
        <span>SOS</span>
      </a>
    </div>

  </div>

  <!-- Map Modal -->
  <div class="modal fade" id="mapModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-route"></i> Trip Route</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="map" style="height: 400px; width: 100%;"></div>
          <div id="tripDetails" class="map-info mt-3"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="pickupModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-camera"></i> Pickup Confirmation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="pickup_d_id">
          <div class="mb-3">
            <label class="form-label">Container Number <span class="text-danger">*</span></label>
            <input type="text" id="pickup_container_no" class="form-control-modern" placeholder="Enter container no." pattern="[A-Z]{4}[0-9]{7}" maxlength="11" inputmode="text" autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false" style="text-transform: uppercase;">
            <small class="text-muted">Use exactly 4 capital letters followed by 7 numbers.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Container Seal</label>
            <input type="text" id="pickup_container_seal" class="form-control-modern" placeholder="Enter seal no. (optional)" maxlength="50" inputmode="text" autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false" style="text-transform: uppercase;">
            <small class="text-muted">Type the seal number stamped on the container, if any.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Shipping Line</label>
            <input type="text" id="pickup_shipping_line" class="form-control-modern" list="pickup_shipping_lines" placeholder="e.g. MAERSK (optional)" maxlength="50" inputmode="text" autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false" style="text-transform: uppercase;">
            <datalist id="pickup_shipping_lines">
              <option value="MAERSK">
              <option value="MSC">
              <option value="CMA CGM">
              <option value="EVERGREEN">
              <option value="COSCO">
              <option value="HAPAG-LLOYD">
              <option value="ONE">
              <option value="OOCL">
              <option value="YANG MING">
              <option value="ZIM">
              <option value="PIL">
              <option value="HMM">
              <option value="WAN HAI">
              <option value="SITC">
            </datalist>
            <small class="text-muted">The carrier name on the container or BOL, if visible.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Pickup Photo <?php if ($pickupPhotoRequired): ?><span class="text-danger">*</span><?php else: ?><span class="text-muted small">(optional)</span><?php endif; ?></label>
            <input type="file" id="pickup_photo" class="form-control-modern" accept="image/*">
            <small class="text-muted">Attach a clear photo of the picked-up container.</small>
          </div>
          <div class="mb-3" id="pickup_time_group" style="display:none;">
            <label class="form-label">Actual Pickup Time <span class="text-danger">*</span></label>
            <input type="datetime-local" id="pickup_event_time" class="form-control-modern">
            <small class="text-muted">No signal earlier? Set when you actually picked up.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
          <button type="button" id="pickupSubmitBtn" class="btn-modern btn-primary-modern">Save Pickup</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Time Modal -->
  <div class="modal fade" id="timeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Update Trip Status</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="trip_id">
          
          <div class="mb-3 d-none" id="containerWrapper">
            <label class="form-label">Container Number</label>
            <input type="text" id="container_no" class="form-control-modern" placeholder="Enter container no." pattern="[A-Z]{4}[0-9]{7}" maxlength="11" inputmode="text" autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false" style="text-transform: uppercase;">
          </div>

          <div class="mb-3">
            <label class="form-label">Arrival at CY</label>
            <input type="datetime-local" id="arrival_cy" class="form-control-modern">
          </div>

          <div class="mb-3">
            <label class="form-label">Departure</label>
            <input type="datetime-local" id="departure" class="form-control-modern">
          </div>

          <div class="mb-3">
            <label class="form-label">Arrival at PH</label>
            <input type="datetime-local" id="arrival_ph" class="form-control-modern">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" id="saveBtn" class="btn-modern btn-primary-modern">Save Progress</button>
          <button type="button" id="doneBtn" class="btn-modern btn-success-modern" style="display:none;">
            <i class="ti ti-check"></i> Mark Complete
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script src="driver/driver-upload.js?v=<?php echo @filemtime(__DIR__ . '/driver-upload.js') ?: time(); ?>"></script>
  <script>
    // Defensive polyfills — see _layout_top.php for the same block.
    (function () {
      if (!window.DriverUpload) window.DriverUpload = {};
      var DU = window.DriverUpload;
      if (typeof DU.generateIdempotencyKey !== 'function') {
        DU.generateIdempotencyKey = function () {
          if (window.crypto && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
          return 'idem-' + Date.now() + '-' + Math.random().toString(16).slice(2);
        };
      }
      if (typeof DU.attachIdempotencyKey !== 'function') {
        DU.attachIdempotencyKey = function (fd, key) {
          var k = key || DU.generateIdempotencyKey();
          if (fd && typeof fd.set === 'function') fd.set('idempotency_key', k);
          else if (fd && typeof fd.append === 'function') fd.append('idempotency_key', k);
          return k;
        };
      }
      if (typeof DU.fetchOrQueue !== 'function') {
        DU.fetchOrQueue = function (url, fd) {
          return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
        };
      }
      if (typeof DU.compressPhoto !== 'function') {
        DU.compressPhoto = function (file) { return Promise.resolve(file); };
      }
      if (typeof DU.compressMany !== 'function') {
        DU.compressMany = function (files) { return Promise.resolve(files || []); };
      }
      if (typeof DU.setBtnBusy !== 'function') {
        DU.setBtnBusy = function ($btn, busy, text) {
          if (!$btn || !$btn.length) return;
          if (busy) {
            if ($btn.data('orig-html') === undefined) $btn.data('orig-html', $btn.html());
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + (text || 'Saving...'));
          } else {
            var o = $btn.data('orig-html');
            if (o !== undefined) $btn.html(o);
            $btn.removeData('orig-html').prop('disabled', false);
          }
        };
      }
      if (typeof DU.errorMessage !== 'function') {
        DU.errorMessage = function (xhr, ts) {
          if (ts === 'timeout') return 'Upload timed out. Check your connection and try again.';
          try { var b = xhr && xhr.responseText ? JSON.parse(xhr.responseText) : null; if (b && b.message) return b.message; } catch (e) {}
          return 'Network error. Please try again.';
        };
      }
    })();
  </script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
  <?php /* Phase 5 — PWA registration. Optional VAPID public key surfaces push if configured. */ ?>
  <?php
    $vapidPublicKey = '';
    $vapidFile = __DIR__ . '/../php/config/vapid.php';
    if (file_exists($vapidFile)) { @include $vapidFile; if (defined('VAPID_PUBLIC_KEY')) $vapidPublicKey = VAPID_PUBLIC_KEY; }
  ?>
  <?php if ($vapidPublicKey !== ''): ?>
  <script>window.PT_VAPID_PUBLIC_KEY = <?php echo json_encode($vapidPublicKey); ?>;</script>
  <?php endif; ?>
  <script src="driver/pwa-register.js"></script>
  <?php include __DIR__ . '/../php/assets/realtime_alerts.php'; ?>

  <script>
    const CONTAINER_NO_REGEX = /^[A-Z]{4}[0-9]{7}$/;

    function normalizeContainerNo(value) {
      // Keep every uppercase letter and digit in the order it was typed,
      // capped at the ISO 6346 length (4 letters + 7 digits = 11 chars).
      // We intentionally DO NOT enforce "letters first, digits after"
      // during typing — some mobile keyboards (Samsung Galaxy, older
      // Gboard) momentarily replace the entire field value when switching
      // alpha/numeric modes. The stricter old logic dropped the next
      // character as a result, wiping the field. Format is still strictly
      // validated at submit via isValidContainerNo().
      return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 11);
    }

    function isValidContainerNo(value) {
      return CONTAINER_NO_REGEX.test(normalizeContainerNo(value));
    }

    $('#pickup_container_no, #container_no').on('input', function() {
      this.value = normalizeContainerNo(this.value);
    });

    // Modern Tab Switching
    $('.tab-btn').on('click', function() {
      $('.tab-btn').removeClass('active');
      $(this).addClass('active');
      
      const target = $(this).data('target');
      $('.booking-card').each(function() {
        const card = $(this);
        if (target === 'all') {
          card.show();
        } else {
          const status = card.data('status');
          card.toggle(status === target);
        }
      });
    });

    // Booking Card Toggle
    $('.booking-card').on('click', function(e) {
      if ($(e.target).closest('button').length) return;
      if ($(e.target).closest('a').length) return;

      const id = $(this).data('booking-id');
      const details = $(`#details-${id}`);
      details.toggleClass('show');
    });

    // Phase 5 — Accept / Decline a pending dispatch.
    $(document).on('click', '.phase5-accept', function (e) {
      e.stopPropagation();
      const id = $(this).data('id');
      $.post('php/operations/driver_accept_job.php', { d_id: id }, function (res) {
        Swal.fire({ icon: res.status === 'success' ? 'success' : (res.status === 'queued' ? 'info' : 'error'),
                    text: res.message, timer: 1500, showConfirmButton: false });
        if (res.status === 'success' || res.status === 'queued') setTimeout(() => location.reload(), 1600);
      }, 'json');
    });
    $(document).on('click', '.phase5-decline', function (e) {
      e.stopPropagation();
      const id = $(this).data('id');
      Swal.fire({ title: 'Decline this job?', input: 'text', inputPlaceholder: 'Reason (optional)',
                  showCancelButton: true, confirmButtonText: 'Decline', confirmButtonColor: '#dc3545' })
        .then(r => {
          if (!r.isConfirmed) return;
          $.post('php/operations/driver_decline_job.php', { d_id: id, reason: r.value || '' }, function (res) {
            Swal.fire({ icon: res.status === 'success' ? 'success' : 'info', text: res.message, timer: 1500, showConfirmButton: false });
            if (res.status === 'success' || res.status === 'queued') setTimeout(() => location.reload(), 1600);
          }, 'json');
        });
    });

    // Phase 7 — End Shift handler. Refuses if there's an in-flight job.
    $('#endShiftBtn').on('click', function () {
      Swal.fire({
        title: 'End your shift?',
        text: 'Machine hours will be recorded based on start → now.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, end shift',
        confirmButtonColor: '#dc3545',
      }).then(r => {
        if (!r.isConfirmed) return;
        $.post('php/operations/end_shift.php', {}, function (res) {
          const ok = res.status === 'success';
          Swal.fire({
            icon: ok ? 'success' : 'warning',
            title: ok ? 'Shift ended' : '',
            html: ok ? '<b>Machine hours:</b> ' + res.machine_hours : escapeStr(res.message),
            confirmButtonColor: '#0d6efd',
          });
          if (ok) setTimeout(() => location.reload(), 1500);
        }, 'json').fail(function (xhr) {
          let msg = 'Network error';
          try { msg = (JSON.parse(xhr.responseText) || {}).message || msg; } catch (e) {}
          Swal.fire({ icon: 'error', text: msg });
        });
      });
      function escapeStr(s){return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
    });

    // Phase 5 — Status pills.
    //
    // Advance the status-pill UI in-place so the driver doesn't have to wait
    // for a full page reload after every tap. Matches the server-rendered
    // logic in driver/dashboard.php (statusOrder + completed/next/future).
    const STATUS_ORDER = ['picked_up', 'on_the_way', 'arrived', 'delivered'];

    // Stable idempotency keys per dispatch for the pickup modal — reused across
    // re-opens/retries so a lost response can't create a duplicate pickup.
    const pickupIdemKeys = {};

    // ---- Late-entry helpers ------------------------------------------------
    // When the driver had no signal on site, they flip the per-trip switch and
    // type the actual time for each step instead of stamping "now".
    function isLateMode(id) {
      return $('.phase5-late-toggle[data-id="' + id + '"]').is(':checked');
    }
    function pad2(n) { return String(n).padStart(2, '0'); }
    function nowLocalInput() {
      const d = new Date();
      return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()) +
             'T' + pad2(d.getHours()) + ':' + pad2(d.getMinutes());
    }
    // Resolves to a 'YYYY-MM-DDTHH:MM' string, or null if the driver cancelled.
    function promptEventTime(label) {
      return Swal.fire({
        title: label || 'When did this happen?',
        html: '<input type="datetime-local" id="swalEventTime" class="swal2-input" ' +
              'value="' + nowLocalInput() + '" max="' + nowLocalInput() + '">',
        showCancelButton: true,
        confirmButtonText: 'Save time',
        focusConfirm: false,
        preConfirm: function () {
          const v = document.getElementById('swalEventTime').value;
          if (!v) { Swal.showValidationMessage('Pick the actual time.'); return false; }
          return v;
        }
      }).then(function (r) { return r.isConfirmed ? r.value : null; });
    }

    function markStatusDone(id, doneStatus) {
      const doneIdx = STATUS_ORDER.indexOf(doneStatus);
      if (doneIdx < 0) return;

      const $list = $('.phase5-status[data-id="' + id + '"]').closest('.phase5-status-list');
      if (!$list.length) return;

      $list.find('.phase5-status').each(function () {
        const $btn = $(this);
        const idx = STATUS_ORDER.indexOf($btn.data('st'));
        const isDone = idx <= doneIdx;
        const isNext = idx === doneIdx + 1;

        $btn.removeClass('btn-success-modern btn-primary-modern btn-outline-modern');
        if (isDone) {
          $btn.addClass('btn-success-modern').prop('disabled', true).css('opacity', '0.7');
          if ($btn.find('.ti-circle-check').length === 0) {
            $btn.append(' <i class="ti ti-circle-check"></i>');
          }
        } else if (isNext) {
          $btn.addClass('btn-primary-modern').prop('disabled', false).css('opacity', '1');
        } else {
          // Future steps stay locked so the driver can't skip ahead.
          $btn.addClass('btn-outline-modern').prop('disabled', true).css('opacity', '0.55');
        }
      });

      // When delivered is marked, light up the POD button so the driver
      // can see what's next without a page refresh.
      if (doneStatus === 'delivered') {
        const $podBtn = $('a[href="driver-pod?d_id=' + id + '"]');
        $podBtn.removeClass('btn-outline-modern').addClass('btn-primary-modern').css('opacity', '');
      }
    }

    function handleStatusResponse(id, st, res) {
      Swal.fire({
        icon: res.status === 'success' ? 'success' : (res.status === 'queued' ? 'info' : 'error'),
        text: res.message,
        timer: 1400,
        showConfirmButton: false
      });
      if (res.status === 'success' || res.status === 'queued') {
        if (st === 'delivered') {
          // Delivered always leads into POD capture.
          setTimeout(() => location.href = 'driver-pod?d_id=' + id, 1500);
        } else {
          // In-place DOM advance — no full page reload for pickup / on the way / arrived.
          markStatusDone(id, st);
        }
      }
    }

    function sendDriverStatus(id, st, lat, lng, done, extraParams) {
      const key = (window.DriverUpload && DriverUpload.generateIdempotencyKey)
        ? DriverUpload.generateIdempotencyKey()
        : ('idem-' + Date.now() + '-' + Math.random().toString(16).slice(2));
      const payload = Object.assign({
        d_id: id,
        status: st,
        lat: lat || '',
        lng: lng || '',
        idempotency_key: key
      }, extraParams || {});
      $.post('php/operations/driver_update_status.php', payload, function (res) {
        if (typeof done === 'function') done();
        handleStatusResponse(id, st, res);
      }, 'json').fail(function (xhr) {
        if (typeof done === 'function') done();
        let message = 'Unable to update trip status.';
        try { message = (JSON.parse(xhr.responseText) || {}).message || message; } catch (e) {}
        Swal.fire({ icon: 'warning', text: message });
      });
    }

    // Shared runner: capture GPS, post status, restore button on error.
    function runDriverStatusFromButton($btn, id, st, extraParams) {
      const originalHtml = $btn.html();
      $btn.data('busy', true)
          .prop('disabled', true)
          .html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...');

      let fired = false;
      const fireOnce = (lat, lng) => {
        if (fired) return;
        fired = true;
        sendDriverStatus(id, st, lat, lng, function () {
          $btn.data('busy', false);
          $btn.prop('disabled', false).html(originalHtml);
        }, extraParams);
      };

      if (navigator.geolocation) {
        let settled = false;
        const finish = (lat, lng) => { if (!settled) { settled = true; fireOnce(lat, lng); } };
        navigator.geolocation.getCurrentPosition(
          p => finish(p.coords.latitude.toFixed(7), p.coords.longitude.toFixed(7)),
          () => finish(),
          { enableHighAccuracy: false, timeout: 2500, maximumAge: 30000 }
        );
        setTimeout(() => finish(), 3000);
      } else {
        fireOnce();
      }
    }

    $(document).on('click', '.phase5-status', function (e) {
      e.stopPropagation();
      const $btn = $(this);
      if ($btn.prop('disabled') || $btn.data('busy')) return;

      const id = $btn.data('id');
      const st = $btn.data('st');
      const existingContainer = $btn.data('container') || '';

      if (st === 'picked_up') {
        const existingSeal = $btn.data('container-seal') || '';
        const existingShippingLine = $btn.data('shipping-line') || '';
        $('#pickup_d_id').val(id);
        $('#pickup_container_no').val(existingContainer);
        $('#pickup_container_seal').val(existingSeal);
        $('#pickup_shipping_line').val(existingShippingLine);
        $('#pickup_photo').val('');
        if (isLateMode(id)) {
          $('#pickup_time_group').show();
          $('#pickup_event_time').val(nowLocalInput()).attr('max', nowLocalInput());
        } else {
          $('#pickup_time_group').hide();
          $('#pickup_event_time').val('');
        }
        $('#pickupModal').modal('show');
        setTimeout(() => $('#pickup_container_no').trigger('focus'), 200);
        return;
      }

      if (st === 'delivered') {
        // Ask the driver whether they want to jackup the trailer before
        // marking this trip delivered.
        //   Yes  → go to the jackup page; that page auto-marks delivered after save.
        //   No   → submit delivered immediately with skip_jackup=1.
        //   Esc  → do nothing.
        const askJackup = function (eventTime) {
          Swal.fire({
            title: 'Jackup trailer?',
            text: 'Do you want to jackup the trailer for this delivery?',
            icon: 'question',
            showCancelButton: true,
            showCloseButton: true,
            confirmButtonText: 'Yes, jackup first',
            cancelButtonText: 'No, mark delivered',
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
          }).then(function (r) {
            if (r.isConfirmed) {
              let url = 'driver-jackup?d_id=' + encodeURIComponent(id) + '&auto_deliver=1';
              if (eventTime) url += '&event_time=' + encodeURIComponent(eventTime);
              location.href = url;
            } else if (r.dismiss === Swal.DismissReason.cancel) {
              const extra = { skip_jackup: '1' };
              if (eventTime) extra.event_time = eventTime;
              runDriverStatusFromButton($btn, id, st, extra);
            }
          });
        };
        if (isLateMode(id)) {
          promptEventTime('When was this delivered?').then(function (t) { if (t) askJackup(t); });
        } else {
          askJackup(null);
        }
        return;
      }

      // Late entry — let the driver type the actual time, then reuse the
      // standard GPS-capture-and-send runner with that time attached.
      if (isLateMode(id)) {
        const statusLabel = $btn.find('.flex-fill').text().trim() || st;
        promptEventTime('When did "' + statusLabel + '" happen?').then(function (t) {
          if (!t) return;
          runDriverStatusFromButton($btn, id, st, { event_time: t });
        });
        return;
      }

      // Visual feedback so a tap is never silent.
      const originalHtml = $btn.html();
      $btn.data('busy', true)
          .prop('disabled', true)
          .html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...');

      // Wrapper that fires AJAX exactly once even if both geo callbacks race.
      let fired = false;
      const fireOnce = (lat, lng) => {
        if (fired) return;
        fired = true;
        sendDriverStatus(id, st, lat, lng, function () {
          $btn.data('busy', false);
          // The page reloads on success, so resetting visuals only matters on error.
          $btn.prop('disabled', false).html(originalHtml);
        });
      };

      if (navigator.geolocation) {
        // Short timeout — don't make the driver wait. If GPS isn't ready in
        // 2.5s we submit without it; the server tolerates missing lat/lng.
        let settled = false;
        const finish = (lat, lng) => { if (!settled) { settled = true; fireOnce(lat, lng); } };
        navigator.geolocation.getCurrentPosition(
          p => finish(p.coords.latitude.toFixed(7), p.coords.longitude.toFixed(7)),
          () => finish(),
          { enableHighAccuracy: false, timeout: 2500, maximumAge: 30000 }
        );
        // Safety net: in case the browser ignores the timeout (some iOS bugs),
        // submit anyway after 3s.
        setTimeout(() => finish(), 3000);
      } else {
        fireOnce();
      }
    });

    $('#pickupSubmitBtn').on('click', function () {
      const $btn = $(this);
      if ($btn.prop('disabled')) return;

      const id = $('#pickup_d_id').val();
      const containerNo = normalizeContainerNo($('#pickup_container_no').val());
      const photo = $('#pickup_photo')[0].files[0];
      // Container seal is optional — uppercased + trimmed, sent only if present.
      const containerSeal = String($('#pickup_container_seal').val() || '')
        .trim().toUpperCase();
      // Shipping line is optional — same normalization.
      const shippingLine = String($('#pickup_shipping_line').val() || '')
        .trim().toUpperCase();
      // Back-dated time, only present/required when the late-entry switch is on.
      const lateMode = $('#pickup_time_group').is(':visible');
      const eventTime = lateMode ? String($('#pickup_event_time').val() || '') : '';

      if (!containerNo) {
        Swal.fire({ icon: 'warning', text: 'Container number is required.' });
        return;
      }
      if (!isValidContainerNo(containerNo)) {
        Swal.fire({ icon: 'warning', text: 'Container number must be exactly 4 capital letters followed by 7 numbers.' });
        return;
      }
      const PICKUP_PHOTO_REQUIRED = <?php echo $pickupPhotoRequired ? 'true' : 'false'; ?>;
      if (PICKUP_PHOTO_REQUIRED && !photo) {
        Swal.fire({ icon: 'warning', text: 'Pickup photo is required.' });
        return;
      }
      if (lateMode && !eventTime) {
        Swal.fire({ icon: 'warning', text: 'Set the actual pickup time.' });
        return;
      }

      DriverUpload.setBtnBusy($btn, true);

      // Reuse a stable key for this dispatch so retries dedupe server-side.
      const idemKey = pickupIdemKeys[id] || (pickupIdemKeys[id] = DriverUpload.generateIdempotencyKey());

      const sendPickup = (uploadFile, lat, lng) => {
        const formData = new FormData();
        formData.append('d_id', id);
        formData.append('status', 'picked_up');
        formData.append('container_no', containerNo);
        if (containerSeal) formData.append('container_seal', containerSeal);
        if (shippingLine)  formData.append('shipping_line', shippingLine);
        if (eventTime)     formData.append('event_time', eventTime);
        // Photo may be optional now — only attach when one was taken.
        if (uploadFile) formData.append('pickup_photo', uploadFile);
        formData.append('idempotency_key', idemKey);
        if (lat) formData.append('lat', lat);
        if (lng) formData.append('lng', lng);

        // fetchOrQueue falls back to a local IndexedDB queue if fetch
        // throws (network blip + SW not controlling).
        DriverUpload.fetchOrQueue('php/operations/driver_update_status.php', formData)
          .then(async function (r) {
            let res = null;
            try { res = await r.json(); } catch (e) {}
            DriverUpload.setBtnBusy($btn, false);
            $('#pickupModal').modal('hide');
            if (!r.ok && r.status !== 202) {
              Swal.fire({ icon: 'error', text: (res && res.message) || ('Pickup failed (HTTP ' + r.status + ').') });
              return;
            }
            if (!res) {
              Swal.fire({ icon: 'error', text: 'Server returned an unexpected response.' });
              return;
            }
            handleStatusResponse(id, 'picked_up', res);
          })
          .catch(function (err) {
            DriverUpload.setBtnBusy($btn, false);
            Swal.fire({
              icon: 'error',
              title: 'Pickup save failed',
              html: 'Something went wrong saving this pickup.<br><br>' +
                    '<small style="color:#888;">' + ((err && err.message) ? String(err.message).replace(/[<>&]/g, '') : 'Unknown error') + '</small>'
            });
          });
      };

      // Skip compression entirely when no photo was attached (now allowed
      // when Admin made the pickup photo optional).
      Promise.resolve(photo ? DriverUpload.compressPhoto(photo) : null).then(function (uploadFile) {
        if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(
            p => sendPickup(uploadFile, p.coords.latitude.toFixed(7), p.coords.longitude.toFixed(7)),
            () => sendPickup(uploadFile),
            { enableHighAccuracy: true, timeout: 5000 }
          );
        } else {
          sendPickup(uploadFile);
        }
      });
    });

    // Live-refresh stats. Endpoint reads driver_id from the session.
    function fetchDriverBookings() {
      $.getJSON("php/fetch/getDriverBooking.php", function(data) {
        if (!data) return;
        if (data.active !== undefined) {
          $("#booking").text(data.active);
          $("#activeCount").text(data.active + " Active");
        }
        if (data.done !== undefined) {
          $("#totalCount").text(data.done);
        }
      });
    }

    setInterval(fetchDriverBookings, 15000);

    // Phase 5 — Chat unread dot on the bottom-nav Chat icon.
    function pollChatUnread() {
      $.getJSON('php/fetch/messages_unread.php', function (res) {
        if (res.status === 'success' && parseInt(res.count, 10) > 0) {
          $('#unreadDot').show();
        } else {
          $('#unreadDot').hide();
        }
      });
    }
    pollChatUnread();
    setInterval(pollChatUnread, 10000);

    // SOS Function
    function sosAlert() {
      Swal.fire({
        title: '🚨 Emergency SOS',
        text: 'Send distress signal to dispatch?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Send SOS',
        reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          $.post("php/operations/send_sos.php", { driver_id: $("#driverID").val() }, function() {
            Swal.fire('Sent!', 'SOS alert transmitted.', 'success');
          });
        }
      });
    }

    // Trip map — Leaflet on Esri satellite imagery, OSRM road route via the
    // server (dispatch_route.php), and the driver's live phone GPS.
    let dmMap = null, dmRouteLayer = null, dmYou = null, dmWatch = null;

    function dmEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

    function dmBuildMap() {
      if (typeof L === 'undefined') { $('#tripDetails').html('<div style="color:#b91c1c">Map could not load. Check your connection.</div>'); return false; }
      if (dmMap) { dmMap.invalidateSize(); return true; }
      dmMap = L.map('map', { zoomControl: true });
      L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Esri' }).addTo(dmMap);
      L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, opacity: 0.9 }).addTo(dmMap);
      dmMap.setView([12.8797, 121.7740], 6);
      return true;
    }
    function dmClearRoute() { if (dmRouteLayer && dmMap) { dmMap.removeLayer(dmRouteLayer); dmRouteLayer = null; } }

    function dmLoadRoute(did, trip) {
      $.getJSON('php/fetch/dispatch_route.php', { d_id: did }, function (res) {
        if (res.status !== 'success' || !dmMap) return;
        dmClearRoute();
        const layer = L.layerGroup().addTo(dmMap);
        const bounds = [];
        if (res.route && res.route.length > 1) { L.polyline(res.route, { color: '#2563eb', weight: 5, opacity: 0.85 }).addTo(layer); res.route.forEach(p => bounds.push(p)); }
        if (res.origin) { L.circleMarker([res.origin.lat, res.origin.lng], { radius: 7, color: '#059669', fillColor: '#10b981', fillOpacity: 1 }).bindPopup('Origin: ' + dmEsc(res.origin.name)).addTo(layer); bounds.push([res.origin.lat, res.origin.lng]); }
        if (res.destination) { L.circleMarker([res.destination.lat, res.destination.lng], { radius: 8, color: '#b91c1c', fillColor: '#ef4444', fillOpacity: 1 }).bindPopup((res.arrived ? '✓ Arrived · ' : 'Destination: ') + dmEsc(res.destination.name)).addTo(layer); bounds.push([res.destination.lat, res.destination.lng]); }
        dmRouteLayer = layer;
        if (bounds.length > 1) { try { dmMap.fitBounds(bounds, { padding: [30, 30] }); } catch (e) {} }
        const arr = res.destination
          ? (res.arrived
              ? '<div class="map-info-row"><span>Status</span><strong style="color:#16a34a">✓ Arrived at ' + dmEsc(res.destination.name) + '</strong></div>'
              : '<div class="map-info-row"><span>Status</span><strong>En route to ' + dmEsc(res.destination.name) + '</strong></div>')
          : '<div class="map-info-row"><span>Status</span><strong>No mapped destination</strong></div>';
        $('#tripDetails').html(
          '<div class="map-info-row"><span>Activity</span><strong>' + dmEsc(trip.container_activity) + '</strong></div>' +
          '<div class="map-info-row"><span>From</span><strong>' + dmEsc(trip.trip_from) + '</strong></div>' +
          '<div class="map-info-row"><span>To</span><strong>' + dmEsc(trip.trip_to) + '</strong></div>' +
          '<div class="map-info-row"><span>Container</span><strong>' + dmEsc(trip.trip_container || 'N/A') + '</strong></div>' + arr);
      }).fail(function () { $('#tripDetails').html('<div style="color:#b91c1c">Could not load the route.</div>'); });
    }

    function dmStartYou() {
      if (!navigator.geolocation) return;
      dmWatch = navigator.geolocation.watchPosition(function (pos) {
        if (!dmMap) return;
        const ll = [pos.coords.latitude, pos.coords.longitude];
        if (!dmYou) { dmYou = L.marker(ll).addTo(dmMap).bindPopup('You are here'); if (!dmRouteLayer) dmMap.setView(ll, 14); }
        else { dmYou.setLatLng(ll); }
      }, function () {}, { enableHighAccuracy: true, maximumAge: 5000, timeout: 10000 });
    }
    function dmStopYou() {
      if (dmWatch != null && navigator.geolocation) { navigator.geolocation.clearWatch(dmWatch); dmWatch = null; }
      if (dmYou && dmMap) { dmMap.removeLayer(dmYou); dmYou = null; }
    }

    $(document).on("click", ".view-map", function () {
      const trip = JSON.parse($(this).attr("data-trip"));
      const did = $(this).data("did") || trip.d_id;
      $("#tripDetails").html('<div style="color:#6b7a90">Loading route…</div>');
      $("#mapModal").modal("show");
      setTimeout(function () {
        if (!dmBuildMap()) return;
        dmMap.invalidateSize();
        dmClearRoute();
        dmLoadRoute(did, trip);
        dmStopYou();
        dmStartYou();
      }, 400);
    });
    $('#mapModal').on('hidden.bs.modal', function () { dmStopYou(); dmClearRoute(); });

    // Time Modal Logic (preserved)
    $(document).on('click', '.view-time', function() {
      const tripId = $(this).data('id');
      const container = $(this).data('container');
      
      $('#trip_id').val(tripId);
      $('#container_no').val('');
      $('#containerWrapper').toggleClass('d-none', container && container.trim() !== '');
      
      $.getJSON('php/fetch/get_tripDateTime1.php', { trip_id: tripId }, function(data) {
        if (data.status === 'success') {
          $('#container_no').val(data.trip.trip_container || '');
          $('#arrival_cy').val(data.trip.trip_arrivalDateTime || '');
          $('#departure').val(data.trip.trip_departureDateTime || '');
          $('#arrival_ph').val(data.trip.trip_pharrivalDateTime || '');
          checkInputs();
        }
      });
      
      $('#timeModal').modal('show');
    });

    function checkInputs() {
      const timeFilled = $('#arrival_cy').val() && $('#departure').val() && $('#arrival_ph').val();
      const containerRequired = !$('#containerWrapper').hasClass('d-none');
      const containerFilled = $('#container_no').val();
      
      if (timeFilled && (!containerRequired || containerFilled)) {
        $('#saveBtn').hide();
        $('#doneBtn').show();
      } else {
        $('#saveBtn').show();
        $('#doneBtn').hide();
      }
    }

    $('#arrival_cy, #departure, #arrival_ph, #container_no').on('input change', checkInputs);

    $('#saveBtn').on('click', function() {
      $.post('php/crud/update/update_tripDateTime1.php', {
        trip_id: $('#trip_id').val(),
        arrival_cy: $('#arrival_cy').val(),
        departure: $('#departure').val(),
        arrival_ph: $('#arrival_ph').val(),
        action: 'save'
      }, function(response) {
        if (response.status === 'success') {
          Swal.fire('Saved!', 'Progress updated.', 'success');
          $('#timeModal').modal('hide');
        }
      }, 'json');
    });

    $('#doneBtn').on('click', function() {
      Swal.fire({
        title: 'Complete Trip?',
        text: 'This will mark the trip as finished.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Complete',
        confirmButtonColor: '#10b981'
      }).then((result) => {
        if (result.isConfirmed) {
          const containerNo = normalizeContainerNo($('#container_no').val());
          if (containerNo && !isValidContainerNo(containerNo)) {
            Swal.fire('Invalid container number', 'Container number must be exactly 4 capital letters followed by 7 numbers.', 'warning');
            return;
          }
          $.post('php/crud/update/update_tripDateTime1.php', {
            trip_id: $('#trip_id').val(),
            container_no: containerNo,
            action: 'done'
          }, function(response) {
            if (response.status === 'success') {
              Swal.fire('Completed!', 'Trip finished successfully.', 'success')
                .then(() => location.reload());
            }
          }, 'json');
        }
      });
    });
  </script>
</body>
</html>
<?php
} else {
  header("Location: driver-index.php?route=login");
  exit();
}
?>
