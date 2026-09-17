<?php
session_start();

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== "Driver") {
  header("Location: index.php?route=login");
  exit();
}

$pageTitle = 'My Earnings — Pantrucks Driver';
$activeNav = 'earnings';

require_once __DIR__ . '/../php/helpers/payroll_period.php';
$currentPeriod = payroll_period_for();
// Allow ?date_from / ?date_to to override (used when navigating from the
// dashboard's Previous Cutoff card).
$defaultFrom = isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'])
    ? $_GET['date_from'] : $currentPeriod['start'];
$defaultTo   = isset($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])
    ? $_GET['date_to'] : $currentPeriod['end'];

require __DIR__ . '/_layout_top.php';
?>

<main class="driver-page" style="padding:16px; max-width:720px; margin:0 auto;">
  <h2 style="margin:0 0 8px;">My Earnings</h2>
  <p style="margin:0 0 16px; color:#6b7a90; font-size:14px;">Payroll cutoffs run <strong>6 → 20</strong> and <strong>21 → 5</strong>. Only <strong>verified</strong> dispatches count toward earnings. Defaults to the current cutoff.</p>

  <div style="background:#fff; border:1px solid #e6e9f0; border-radius:14px; padding:14px; margin-bottom:14px;">
    <form id="earnFilter" style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:0;">
      <div>
        <label style="display:block; font-size:12px; color:#6b7a90; margin-bottom:4px;">Date From</label>
        <input type="date" id="dateFrom" class="form-control" value="<?= htmlspecialchars($defaultFrom) ?>" required>
      </div>
      <div>
        <label style="display:block; font-size:12px; color:#6b7a90; margin-bottom:4px;">Date To</label>
        <input type="date" id="dateTo" class="form-control" value="<?= htmlspecialchars($defaultTo) ?>" required>
      </div>
      <div style="grid-column:1/-1; display:flex; gap:8px; flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary" style="flex:1;">Load</button>
        <button type="button" class="btn btn-outline-primary" id="currentCutoffBtn" style="flex:1;">Current cutoff</button>
        <button type="button" class="btn btn-outline-secondary" id="prevCutoffBtn" style="flex:1;">Previous cutoff</button>
      </div>
    </form>
  </div>

  <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
    <div style="background:#fff; border:1px solid #e6e9f0; border-radius:14px; padding:14px;">
      <div style="font-size:12px; color:#6b7a90; text-transform:uppercase;">Total Trips</div>
      <div id="statTrips" style="font-size:28px; font-weight:800;">0</div>
    </div>
    <div style="background:linear-gradient(135deg, #1f5eff, #6a8dff); color:#fff; border-radius:14px; padding:14px;">
      <div style="font-size:12px; opacity:.8; text-transform:uppercase;">Total Earnings (₱)</div>
      <div id="statTotal" style="font-size:28px; font-weight:800;">0.00</div>
    </div>
  </div>

  <div style="background:#fff; border:1px solid #e6e9f0; border-radius:14px; padding:14px;">
    <h3 style="margin:0 0 10px; font-size:16px;">Trip-by-trip Breakdown</h3>
    <div id="tripList" style="display:flex; flex-direction:column; gap:10px;">
      <div style="color:#6b7a90; text-align:center; padding:18px;">Pick a date range and tap Load.</div>
    </div>
  </div>
</main>

<script>
(function () {
  // Server already pre-fills dateFrom/dateTo with the current (or query-string)
  // payroll cutoff. The buttons below let the user jump between cutoffs quickly.
  const cutoffs = {
    current:  { from: <?= json_encode(payroll_period_for()['start']) ?>,        to: <?= json_encode(payroll_period_for()['end']) ?> },
    previous: { from: <?= json_encode(payroll_previous_period()['start']) ?>,   to: <?= json_encode(payroll_previous_period()['end']) ?> },
  };
  document.getElementById('currentCutoffBtn').addEventListener('click', () => {
    document.getElementById('dateFrom').value = cutoffs.current.from;
    document.getElementById('dateTo').value   = cutoffs.current.to;
    load();
  });
  document.getElementById('prevCutoffBtn').addEventListener('click', () => {
    document.getElementById('dateFrom').value = cutoffs.previous.from;
    document.getElementById('dateTo').value   = cutoffs.previous.to;
    load();
  });

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function load() {
    const from = document.getElementById('dateFrom').value;
    const to   = document.getElementById('dateTo').value;
    // The fetch endpoint auto-scopes Drivers to themselves — no driver_id needed.
    fetch('php/fetch/get_payroll_data.php?date_from=' + from + '&date_to=' + to + '&driver_id=self', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(resp => {
        if (!resp.success) {
          Swal.fire('Error', resp.message || 'Failed', 'error'); return;
        }
        document.getElementById('statTrips').textContent = resp.summary.total_trips;
        document.getElementById('statTotal').textContent = Number(resp.summary.grand_total).toLocaleString('en-PH', { minimumFractionDigits: 2 });

        const list = document.getElementById('tripList');
        list.innerHTML = '';
        if (!resp.details || resp.details.length === 0) {
          list.innerHTML = '<div style="color:#6b7a90; text-align:center; padding:18px;">No trips in this range.</div>';
          return;
        }
        resp.details.forEach(t => {
          const card = document.createElement('div');
          card.style.cssText = 'border:1px solid #e6e9f0; border-radius:12px; padding:12px;';
          card.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:start; gap:8px;">
              <div style="flex:1;">
                <div style="font-weight:700; font-size:15px;">${escapeHtml(t.trip_from || '')} → ${escapeHtml(t.trip_to || '')}</div>
                <div style="color:#6b7a90; font-size:13px; margin-top:2px;">
                  ${escapeHtml(t.d_datetime || '').slice(0,16)} · ${escapeHtml(t.trip_type || '')} · ${escapeHtml(t.costumer || '')}
                </div>
                <div style="color:#6b7a90; font-size:12px; margin-top:2px;">
                  ${escapeHtml(t.trip_haulingsegment || '')} · ${escapeHtml(t.container_activity || '')} · Status: ${escapeHtml(t.trip_status || '')}
                </div>
              </div>
              <div style="text-align:right;">
                <div style="font-weight:800; color:#1f5eff;">₱${Number(t.piece_rate || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</div>
                <div style="font-size:11px; color:#6b7a90;">Booking ${escapeHtml(t.booking_no || '')}</div>
              </div>
            </div>`;
          list.appendChild(card);
        });
      })
      .catch(() => Swal.fire('Error', 'Network error', 'error'));
  }

  document.getElementById('earnFilter').addEventListener('submit', e => { e.preventDefault(); load(); });
  load();
})();
</script>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
