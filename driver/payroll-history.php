<?php
session_start();

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== "Driver") {
  header("Location: index.php?route=login");
  exit();
}

require_once __DIR__ . '/../php/config/config.php';
require_once __DIR__ . '/../php/helpers/payroll_period.php';

$driverId = (int)$_SESSION['user_id'];

// Show the last 12 cutoffs (~6 months of pay periods).
$periods = payroll_periods_back(12);

// One SQL hit, group totals by period range. To avoid N round trips, we
// fetch all dispatches in the window of (oldest start … today) and group
// in PHP.
$oldestStart = end($periods)['start'];
$todayStr    = (new DateTime())->format('Y-m-d');

$rows = [];
try {
    // Only verified dispatches count toward payroll.
    $stmt = $conn->prepare(
        "SELECT DATE(d.d_datetime) AS d_date,
                COALESCE(SUM(t.piece_rate), 0) AS earnings,
                COUNT(t.trip_id) AS trips
         FROM trips t
         INNER JOIN dispatch d ON d.d_id = t.d_id
         WHERE d.driver_id = ?
           AND DATE(d.d_datetime) BETWEEN ?::date AND ?::date
           AND d.verified_at IS NOT NULL
           AND (t.cancelled_at IS NULL OR (t.foul_trip = TRUE AND t.foul_approved = TRUE))
         GROUP BY DATE(d.d_datetime)
         ORDER BY DATE(d.d_datetime)"
    );
    $stmt->execute([$driverId, $oldestStart, $todayStr]);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    // piece_rate / verified_at column may be missing on legacy DBs — silently degrade.
}

// Bucket the per-day rows into payroll periods.
$buckets = [];
foreach ($periods as $i => $p) {
    $buckets[$i] = ['earnings' => 0.0, 'trips' => 0];
}
foreach ($rows as $r) {
    $d = $r['d_date'];
    foreach ($periods as $i => $p) {
        if ($d >= $p['start'] && $d <= $p['end']) {
            $buckets[$i]['earnings'] += (float)$r['earnings'];
            $buckets[$i]['trips']    += (int)$r['trips'];
            break;
        }
    }
}

// Totals across all shown cutoffs.
$grandEarn  = array_sum(array_column($buckets, 'earnings'));
$grandTrips = array_sum(array_column($buckets, 'trips'));

$pageTitle = 'Payroll History — Pantrucks Driver';
$activeNav = 'payroll-history';
require __DIR__ . '/_layout_top.php';
?>

<main class="driver-page" style="padding:16px; max-width:720px; margin:0 auto;">
  <h2 style="margin:0 0 4px;">Payroll History</h2>
  <p style="margin:0 0 16px; color:#6b7a90; font-size:14px;">
    Earnings broken down by cutoff (6 → 20 and 21 → 5). Only <strong>verified</strong> dispatches are counted. Tap a row to see the trip-by-trip breakdown.
  </p>

  <!-- Totals summary across all shown periods -->
  <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
    <div style="background:#fff; border:1px solid #e6e9f0; border-radius:14px; padding:14px;">
      <div style="font-size:11px; color:#6b7a90; text-transform:uppercase; letter-spacing:.05em;">Last <?= count($periods) ?> Cutoffs · Trips</div>
      <div style="font-size:24px; font-weight:800;"><?= (int)$grandTrips ?></div>
    </div>
    <div style="background:linear-gradient(135deg,#1f5eff,#6a8dff); color:#fff; border-radius:14px; padding:14px;">
      <div style="font-size:11px; opacity:.85; text-transform:uppercase; letter-spacing:.05em;">Total Earnings (₱)</div>
      <div style="font-size:24px; font-weight:800;">₱<?= number_format($grandEarn, 2) ?></div>
    </div>
  </div>

  <div style="display:flex; flex-direction:column; gap:10px;">
    <?php foreach ($periods as $i => $p):
      $isCurrent = ($i === 0);
      $earn = $buckets[$i]['earnings'];
      $tr   = $buckets[$i]['trips'];
    ?>
      <a href="driver-earnings?date_from=<?= $p['start'] ?>&date_to=<?= $p['end'] ?>"
         class="text-decoration-none"
         style="color:inherit;">
        <div style="background:<?= $isCurrent ? 'linear-gradient(135deg,#1f5eff,#6a8dff)' : '#fff' ?>;
                    color:<?= $isCurrent ? '#fff' : 'inherit' ?>;
                    border:1px solid <?= $isCurrent ? 'transparent' : '#e6e9f0' ?>;
                    border-radius:14px; padding:14px;
                    display:flex; align-items:center; gap:12px;">
          <div style="flex:1;">
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
              <strong style="font-size:15px;"><?= htmlspecialchars($p['label']) ?></strong>
              <span style="font-size:10px; padding:2px 8px; border-radius:999px;
                           background:<?= $isCurrent ? 'rgba(255,255,255,0.25)' : '#eef2ff' ?>;
                           color:<?= $isCurrent ? '#fff' : '#1f5eff' ?>;
                           text-transform:uppercase; letter-spacing:.05em; font-weight:700;">
                <?= $isCurrent ? 'Current' : 'Period ' . $p['period'] ?>
              </span>
            </div>
            <div style="font-size:12px; opacity:<?= $isCurrent ? '.85' : '1' ?>; color:<?= $isCurrent ? '#fff' : '#6b7a90' ?>; margin-top:2px;">
              <?= htmlspecialchars($p['start']) ?> → <?= htmlspecialchars($p['end']) ?>
            </div>
          </div>
          <div style="text-align:right;">
            <div style="font-weight:800; font-size:18px;">₱<?= number_format($earn, 2) ?></div>
            <div style="font-size:11px; opacity:<?= $isCurrent ? '.85' : '1' ?>; color:<?= $isCurrent ? '#fff' : '#6b7a90' ?>;">
              <?= $tr ?> trip<?= $tr === 1 ? '' : 's' ?>
            </div>
          </div>
          <i class="ti ti-chevron-right" style="font-size:20px; opacity:.6;"></i>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</main>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
