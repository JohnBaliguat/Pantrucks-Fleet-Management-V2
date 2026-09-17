/* Fuel Ticketing client logic — ported from PTSI Fuel System Base.
 * Endpoints adjusted for Fleet Management New conventions:
 *   php/fetch/fetch_units_fuel.php
 *   php/fetch/get_last_hubo.php
 *   php/fetch/get_last_hour_meter.php
 *   php/fetch/get_unit_std.php
 *   php/fetch/get_latest_fuel_control.php
 */

document.addEventListener('DOMContentLoaded', () => {
  fetchEquipment();
  fetchLatestControlNo();
  updateDateTime();
  setInterval(updateDateTime, 1000);
  setInterval(fetchLatestControlNo, 30000);
});

let equipmentList = [];

// -------------------------
// FETCH
// -------------------------
async function fetchEquipment() {
  try {
    const r = await fetch('php/fetch/fetch_units_fuel.php');
    const data = await r.json();
    equipmentList = data;
    renderEquipment(equipmentList);
  } catch (e) {
    console.error('Error fetching equipment:', e);
  }
}

async function fetchLatestControlNo() {
  try {
    const r = await fetch('php/fetch/get_latest_fuel_control.php');
    const data = (await r.text()).trim();
    let n = data.startsWith('PTSI-') ? parseInt(data.slice(5)) : parseInt(data);
    if (isNaN(n) || n === 0) n = 1;
    document.getElementById('controlNo').value = `PTSI-${String(n).padStart(5, '0')}`;
  } catch (e) {
    console.error('Failed to fetch latest control number:', e);
  }
}

// -------------------------
// RENDER + FILTER
// -------------------------
function renderEquipment(list) {
  const c = document.getElementById('equipmentOptions');
  c.innerHTML = '';
  list.forEach(u => {
    const li = document.createElement('li');
    li.classList.add('dropdown-item');
    li.textContent = u.name;
    li.onclick = () => selectEquipment(u);
    c.appendChild(li);
  });
}

function filterEquipment() {
  const q = document.getElementById('equipmentSearchBox').value.toLowerCase();
  renderEquipment(equipmentList.filter(u => u.name.toLowerCase().includes(q)));
}

// -------------------------
// SELECT
// -------------------------
function selectEquipment(unit) {
  document.getElementById('equipmentDropdownBtn').textContent = unit.name;
  document.getElementById('selectedEquipment').value = unit.id;

  const ticketCodeInput = document.getElementById('ticketCode');
  if (ticketCodeInput && ticketCodeInput.value.trim() !== '' && typeof loadTicketCodeDetails === 'function') {
    loadTicketCodeDetails();
  }

  fetch(`php/fetch/get_last_hubo.php?unit=${encodeURIComponent(unit.name)}`)
    .then(r => r.text())
    .then(d => { document.getElementById('lastHubo').value = d; calculateKmRun(); })
    .catch(e => console.error('Error fetching last hubo:', e));

  fetch(`php/fetch/get_last_hour_meter.php?unit=${encodeURIComponent(unit.name)}`)
    .then(r => r.text())
    .then(d => { document.getElementById('lastHourMeter').value = d; })
    .catch(e => console.error('Error fetching last hour meter:', e));

  fetch(`php/fetch/get_unit_std.php?unit=${encodeURIComponent(unit.name)}`)
    .then(r => r.text())
    .then(d => {
      document.getElementById('givenRatio').value = d;
      calculateIdeNoLt();
      if (typeof checkEquipmentType === 'function') checkEquipmentType();
    })
    .catch(e => console.error('Error fetching unit std:', e));
}

// -------------------------
// MANUAL INPUT TOGGLE
// -------------------------
const manualInputEl = document.getElementById('manualInput');
if (manualInputEl) {
  manualInputEl.addEventListener('change', () => {
    const container = document.getElementById('manualInputContainer');
    const manualMeter = document.getElementById('manualMeter');
    const visible = manualInputEl.checked ? 'block' : 'none';
    container.style.display = manualMeter.style.display = visible;
    manualMeter.value = '';
    calculateKmRun();
  });
}

// -------------------------
// CALCULATIONS
// -------------------------
function calculateKmRun() {
  const lockedKm = parseFloat($('#ticketKmRun').val());
  if (!isNaN(lockedKm) && lockedKm > 0) {
    calculateActualRatio(); calculateIdeNoLt(); calculateExcessSave();
    return;
  }

  const meter = parseFloat($('#meterRead').val());
  const isManual = $('#manualInput').is(':checked');
  const ref = isManual ? parseFloat($('#manualMeter').val()) : parseFloat($('#lastHubo').val());

  if (isNaN(meter) || isNaN(ref)) { $('#totalKmRun').val(''); return; }

  $('#totalKmRun').val((meter - ref).toFixed(2));
  calculateActualRatio(); calculateIdeNoLt(); calculateExcessSave();
}

function calculateActualRatio() {
  const equipment = document.getElementById('selectedEquipment').value.trim();
  const noLiter   = parseFloat(document.getElementById('noLiter').value)   || 0;
  const totalKm   = parseFloat(document.getElementById('totalKmRun').value) || 0;
  let actual = noLiter > 0 ? totalKm / noLiter : 0;
  document.getElementById('actualRatio').value = actual.toFixed(2);
  calculateIdeNoLt(); calculateExcessSave();
}

function calculateIdeNoLt() {
  const equipment = document.getElementById('selectedEquipment').value.trim();
  const given     = parseFloat(document.getElementById('givenRatio').value) || 0;
  const totalKm   = parseFloat(document.getElementById('totalKmRun').value) || 0;
  let ide = 0;
  if (equipment.slice(0, 2) === 'GS' || equipment.slice(0, 2) === 'FL') {
    ide = given * totalKm;
  } else {
    ide = given > 0 ? totalKm / given : 0;
  }
  document.getElementById('ideNoLt').value = ide.toFixed(2);
  calculateExcessSave();
}

function calculateExcessSave() {
  const ide = parseFloat(document.getElementById('ideNoLt').value) || 0;
  const lt  = parseFloat(document.getElementById('noLiter').value) || 0;
  const excess = ide - lt;
  document.getElementById('excessSave').value = excess.toFixed(2);
  updateExcessSaveDisplay();
}

['lastHubo', 'manualMeter', 'meterRead', 'noLiter', 'givenRatio'].forEach(id => {
  const el = document.getElementById(id);
  if (el) {
    el.addEventListener('input', () => {
      calculateKmRun(); calculateActualRatio(); calculateIdeNoLt(); calculateExcessSave();
    });
  }
});

function updateExcessSaveDisplay() {
  const input = document.getElementById('excessSave');
  const disp  = document.getElementById('excessSaveDisplay');
  if (!input || !disp) return;
  const v = parseFloat(input.value);
  if (isNaN(v)) { disp.value = ''; return; }
  if (v < 0) {
    disp.value = `(${Math.abs(v).toFixed(2)})`;
    disp.style.color = 'red';
  } else {
    disp.value = 'GOOD';
    disp.style.color = 'black';
  }
}

// -------------------------
// DATE / TIME
// -------------------------
function updateDateTime() {
  const now = new Date();
  const date = now.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
  const time = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
  const el = document.getElementById('dateTimeDisplay');
  if (el) el.textContent = `Date: ${date} ${time}`;
}
