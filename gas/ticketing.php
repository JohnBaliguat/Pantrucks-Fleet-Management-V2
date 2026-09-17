<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Gastender') {
    header("Location: login.php?error=Unauthorized");
    exit();
}
include 'php/config/config.php';

$id = $_SESSION['user_id'];
$query = "SELECT user_fname, user_mname, user_lname FROM \"user\" WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$id]);
$data = $stmt->fetch();
$data = $data ?: ['user_fname' => '', 'user_mname' => '', 'user_lname' => ''];

$pageTitle = 'Refueling Ticket';
include 'gas/_layout_top.php';
?>

<div class="row">
    <div class="col-md-6">
      <h4 class="mb-0">Ticketing</h4>
      <p class="text-muted mb-0">Create a new refueling ticket. Validate the printed ticket code from sub-admin to lock the km run.</p>
    </div>
  </div>

  <form id="addForm" action="php/operations/addrecord_fuel.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
    <div class="row">
      <div class="col-md-6">
        <div class="my-3 p-3 bg-body rounded shadow-sm">
          <div class="row border-bottom pb-2 mb-0">
            <div class="col-md-6"><h6>Pantrucks Refueling Ticket</h6></div>
            <div class="col-md-6 text-end"><button type="button" class="btn btn-outline-info btn-sm" id="newHuboBtn">New Hubo</button></div>
          </div>

          <div class="text-body-secondary pt-3">
            <div class="row">
              <div class="col-md-5">
                <button name="ManualDate" type="button" class="btn btn-secondary btn-sm" id="ManualDate">Manual Date</button>
              </div>
              <div class="col-md-1" style="display:none;" id="column1">
                <h5 class="text-end">Date:</h5>
              </div>
              <div class="col-md-4" style="display:none;" id="column2">
                <div class="input-group mb-3">
                  <input type="datetime-local" id="manualDate" name="manualDate" class="form-control">
                  <button class="btn btn-outline-secondary" type="button" id="closeDate"><i class="ti ti-x"></i></button>
                </div>
              </div>
              <div class="col-md-3"><h5 class="text-end">Control No:</h5></div>
              <div class="col-md-4">
                <input type="text" id="controlNo" name="controlNo" class="form-control" style="margin-top:-5px;" readonly>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="equipmentDropdown" class="form-label">Equipment No:</label>
                  <div class="dropdown">
                    <button class="btn btn-outline-secondary text-start" style="width:100%;" type="button" id="equipmentDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                      Select equipment
                    </button>
                    <ul class="dropdown-menu w-100" id="equipmentList" aria-labelledby="equipmentDropdownBtn">
                      <li class="p-2">
                        <input type="text" class="form-control" id="equipmentSearchBox" placeholder="Search..." onkeyup="filterEquipment()">
                      </li>
                      <div id="equipmentOptions"></div>
                    </ul>
                  </div>
                  <input type="hidden" id="selectedEquipment" name="selectedEquipment">
                  <div class="invalid-feedback">Please select an equipment number.</div>
                </div>
              </div>

              <div class="col-md-6">
                <label for="driversName" class="form-label">Driver's Name:</label>
                <input type="number" class="form-control" id="searchId" name="searchId" placeholder="Enter Driver's ID Number">
                <input type="hidden" id="selectedDriver" name="selectedDriver">
              </div>
            </div>

            <div class="row">
              <input type="hidden" class="form-control" id="userName" name="userName"
                value="<?php echo strtoupper($data['user_lname']) . ', ' . strtoupper(substr($data['user_fname'], 0, 1)) . strtoupper(substr($data['user_mname'], 0, 1)); ?>" required>
              <div class="col-md-6">
                <label class="form-label" id="labelMeter">Meter Reading:</label>
                <input type="number" class="form-control" id="meterRead" name="meterRead" placeholder="Enter Meter Reading" required>
                <input type="number" class="form-control" id="meterRead1" name="meterRead1" placeholder="Enter Meter Reading" hidden>
                <div class="invalid-feedback">Please enter the meter reading.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label" id="labelLastMeter">Last Meter Reading:</label>
                <input type="number" id="lastHubo" name="lastHubo" class="form-control" readonly style="background:#adb5bd;">
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <label class="form-label">Hour Meter Reading:</label>
                <input type="number" class="form-control" id="hourMeter" name="hourMeter" placeholder="Enter Hour Meter" required>
                <div class="invalid-feedback">Please enter the hour meter reading.</div>
                <div class="form-check mt-4" hidden>
                  <input class="form-check-input" type="checkbox" id="manualInput" name="manualInput">
                  <label class="form-check-label" for="manualInput">Manually input last reading</label>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Last Hour Meter Reading:</label>
                <input type="number" id="lastHourMeter" name="lastHourMeter" class="form-control" readonly style="background:#adb5bd;">
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <label class="form-label">Total km run.</label>
                <input type="number" class="form-control" id="totalKmRun" name="totalKmRun" readonly style="background:#adb5bd;">
                <input type="number" class="form-control" id="totalKmRun1" name="totalKmRun1" readonly style="background:#adb5bd;" hidden>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">No. of Liters Refueled (Full Tank):</label>
                  <input type="number" class="form-control" id="noLiter" name="noLiter" required placeholder="Enter Number of Liters">
                  <div class="invalid-feedback">Please input the number of liters dispensed.</div>
                </div>
              </div>
            </div>

            <div class="row" id="manualInputContainer" style="display:none;">
              <div class="col-md-6">
                <label class="form-label">Hour Meter Reading/Manual input</label>
                <input type="number" class="form-control" id="manualMeter" name="manualMeter" placeholder="Enter Manual Meter Reading">
              </div>
            </div>

            <div class="row mt-1">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Ticket Code:</label>
                  <input type="text" class="form-control" id="ticketCode" name="ticketCode" placeholder="Enter 6-character ticket code" maxlength="6" style="text-transform:uppercase;">
                  <input type="hidden" id="ticketCodeStatus" value="0">
                  <input type="hidden" id="ticketKmRun" value="">
                  <input type="hidden" id="skipTicketCode" value="0">
                  <div class="form-text">Enter the code printed by sub-admin.</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Ticket Status:</label>
                  <input type="text" class="form-control" id="ticketCodeInfo" value="No code loaded" readonly style="background:#adb5bd;">
                  <button type="button" class="btn btn-warning btn-sm mt-2 w-100" id="skipTicketCodeBtn">⚠️ No Code (Genset/Forklift/Service)</button>
                </div>
              </div>
            </div>

            <div class="row mb-4">
              <div class="col-md-3">
                <h6 class="pb-2 mb-0" style="font-size:13px; padding:2px; border-radius:5px; color:white; background:rgb(45,112,86);">Check the following:</h6>
              </div>
              <div class="col-md-2">
                <div class="form-check mt-1">
                  <input class="form-check-input" type="checkbox" id="padlock" name="padlock">
                  <label class="form-check-label" style="font-weight:600;" for="padlock">PADLOCK</label>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-check mt-1">
                  <input class="form-check-input" type="checkbox" id="fulltank" name="fulltank">
                  <label class="form-check-label" style="font-weight:600;" for="fulltank">FULL TANK</label>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="row">
          <div class="col-md-12">
            <div class="my-3 p-3 bg-body rounded shadow-sm">
              <div class="row">
                <div class="col-md-5">
                  <img src="assets/images/logos/pantrucks.png" alt="" style="width:100%;">
                </div>
                <div class="col-md-7"><h5 class="text-end" id="dateTimeDisplay">Date: </h5></div>
              </div>
              <h6 class="border-bottom pb-2 mb-0"></h6>
              <div class="text-body-secondary pt-3">
                <div class="row">
                  <div class="col-md-6">
                    <div class="row">
                      <div class="col-md-4"><label for="actualRatio" class="col-form-label" style="font-size:13px">Actual Ratio:</label></div>
                      <div class="col-md-8"><input type="text" id="actualRatio" name="actualRatio" class="form-control" readonly style="background:#ced4da;"></div>

                      <div class="col-md-4"><label for="givenRatio" class="col-form-label" style="font-size:13px">Given Ratio:</label></div>
                      <div class="col-md-8"><input type="text" id="givenRatio" name="givenRatio" class="form-control" readonly style="background:#ced4da;"></div>

                      <div class="col-md-4"><label for="ideNoLt" class="col-form-label" style="font-size:10px">Ideal No of liters:</label></div>
                      <div class="col-md-8"><input type="text" id="ideNoLt" name="ideNoLt" class="form-control" readonly style="background:#ced4da;"></div>

                      <div class="col-md-4"><label for="excessSave" class="col-form-label" style="font-size:13px">Excess/Savings:</label></div>
                      <div class="col-md-8">
                        <input type="text" id="excessSaveDisplay" name="excessSaveDisplay" class="form-control" readonly style="background:#ced4da;">
                        <input type="hidden" id="excessSave" name="excessSave" class="form-control" readonly>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="card text-center" style="border:none;">
                      <div class="text-center">
                        <img id="driver_image" src="assets/images/profile/generator.png" class="card-img-top" alt="..." style="width:50%; height:80%; border-radius:10px;">
                      </div>
                      <div class="card-body">
                        <h5 class="card-title" id="driverName">Driver Name</h5>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-12">
            <div class="my-3 p-3 bg-body rounded shadow-sm">
              <div class="row"><div class="col-md"><h5 class="text-start">Check Hauling Segment:</h5></div></div>
              <h6 class="border-bottom pb-2 mb-0"></h6>
              <div class="text-body-secondary">
                <div class="row">
                  <div class="col-md-2"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="bb_dm" name="bb_dm"><label class="form-check-label" style="font-weight:600;" for="bb_dm">BB/DM</label></div></div>
                  <div class="col-md-2"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="dm_rv" name="dm_rv"><label class="form-check-label" style="font-weight:600;" for="dm_rv">DM/RV</label></div></div>
                  <div class="col-md-2"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="dole_rv" name="dole_rv"><label class="form-check-label" style="font-weight:600;" for="dole_rv">DOLE/RV</label></div></div>
                  <div class="col-md-2"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="sumi" name="sumi"><label class="form-check-label" style="font-weight:600;" for="sumi">SUMI</label></div></div>
                  <div class="col-md-2"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="abc" name="abc"><label class="form-check-label" style="font-weight:600;" for="abc">ABC(PANTUKAN)</label></div></div>
                  <div class="col-md-4"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="cat_donmar" name="cat_donmar"><label class="form-check-label" style="font-weight:600;" for="cat_donmar">CAT-DONMAR</label></div></div>
                  <div class="col-md-4"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="other" name="other"><label class="form-check-label" style="font-weight:600;" for="other">OTHER</label></div></div>
                </div>
              </div>
            </div>

            <div class="my-3 p-3 bg-body rounded shadow-sm">
              <div class="row" style="margin:1px;">
                <button name="button" class="btn btn-success" id="saveRecord">Print Ticket</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>

  <div class="row">
    <div class="col-md-12">
      <div class="my-3 p-3 bg-body rounded shadow-sm">
        <div class="row">
          <div class="col-md-6"><h6 class="pb-2 mb-0">Recent Transactions</h6></div>
        </div>
        <div class="text-body-secondary pt-3">
          <table class="table table-hover nowrap" id="table-data" style="width:100%;">
            <thead class="table-light">
              <tr style="font-size:12px;">
                <th>Control No.</th>
                <th>Date/Time</th>
                <th>Unit</th>
                <th>Last Hubo</th>
                <th>Hubo</th>
                <th>Km Run</th>
                <th>No. of Lit.</th>
                <th>Act. Ratio</th>
                <th>Driver</th>
                <th>STD Ratio</th>
                <th>(Excess)/Savings ±10</th>
                <th>Hour Meter</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody style="font-size:12px;"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

<!-- View / Edit Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="exampleModalLabel">Pantrucks Refueling Ticket</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
          <input type="hidden" id="user_Id1" name="user_Id1" value="<?php echo $_SESSION['user_id']; ?>">
          <div class="row">
            <div class="col-md-7"></div>
            <div class="col-md-2"><h6 class="text-end">Control No:</h6></div>
            <div class="col-md-3">
              <input type="hidden" id="f_id1" name="f_id1" readonly>
              <input type="text" class="form-control" id="controlNo1" name="controlNo1" readonly>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6"><label class="form-label" style="font-size:12px;">Equipment No:</label><input type="text" class="form-control" id="equipmentNo1" name="equipmentNo1"></div>
            <div class="col-md-6"><label class="form-label" style="font-size:12px;">Driver's Name:</label><input type="text" class="form-control" id="driver1" name="driver1" readonly></div>
          </div>
          <div class="row">
            <div class="col-md-6"><label class="form-label" style="font-size:12px;">Meter Reading:</label><input type="number" class="form-control" id="meterReading1" name="meterReading1"></div>
            <div class="col-md-6"><label class="form-label" style="font-size:12px;">Last Meter Reading:</label><input type="number" class="form-control" id="lastmeter1" name="lastmeter1"></div>
          </div>
          <div class="row">
            <div class="col-md-6"><label class="form-label" style="font-size:12px;">Total km run.</label><input type="number" class="form-control" id="totalKm1" name="totalKm1" readonly></div>
            <div class="col-md-6"><label class="form-label" style="font-size:12px;">Last Hour Meter Reading:</label><input type="number" class="form-control" id="lastHourMeter1" name="lastHourMeter1" readonly style="background:#adb5bd;"></div>
          </div>
          <div class="row">
            <div class="col-md-6"></div>
            <div class="col-md-6"><label class="form-label" style="font-size:12px;">Hour Meter Reading</label><input type="number" class="form-control" id="hourMeter1" name="hourMeter1" required></div>
          </div>
          <div class="row mt-1">
            <div class="col-md-6"><label class="form-label" style="font-size:12px;">No. of Liters Refueled:</label><input type="number" class="form-control" id="noOfLtr1" name="noOfLtr1"></div>
          </div>
          <div class="row">
            <div class="col-md-4"><label class="col-form-label">Actual Ratio:</label></div>
            <div class="col-md-8"><input type="text" id="actualRatio1" name="actualRatio1" class="form-control" readonly></div>
            <div class="col-md-4"><label class="col-form-label">Given Ratio:</label></div>
            <div class="col-md-8"><input type="text" id="givenRatio1" name="givenRatio1" class="form-control" readonly></div>
            <div class="col-md-4"><label class="col-form-label">Ideal No of liters:</label></div>
            <div class="col-md-8"><input type="text" id="ideNoLt1" name="ideNoLt1" class="form-control" readonly></div>
            <div class="col-md-4"><label class="col-form-label">Excess/Savings:</label></div>
            <div class="col-md-8">
              <input type="text" id="excessSave1" class="form-control" readonly>
              <input type="hidden" id="excessSave2" name="excessSave2" class="form-control" readonly>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" id="updateRecord"><i class="ti ti-edit"></i></button>
        <button class="btn btn-success" id="printRecord"><i class="ti ti-printer"></i></button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="js/fuel-ticketing.js"></script>
<script>
  // --- Equipment label switching for GS / FL units ---
  function checkEquipmentType() {
    let equipment = $("#selectedEquipment").val().toUpperCase();
    if (equipment.includes("GS") || equipment.includes("FL")) {
      $("#labelMeter").text("HourMeter:");
      $("#labelLastMeter").text("Last HourMeter:");
      $("#hourMeter").prop("readonly", true).css("background", "#e9ecef");
      $("#meterRead").on("input", function () { $("#hourMeter").val($(this).val()); });
    } else {
      $("#labelMeter").text("Meter Reading:");
      $("#labelLastMeter").text("Last Meter Reading:");
      $("#hourMeter").prop("readonly", false).css("background", "");
      $("#meterRead").off("input");
    }
  }

  // --- Manual Date toggle / New Hubo ---
  document.addEventListener('DOMContentLoaded', function () {
    const manualDateBtn = document.getElementById('ManualDate');
    const closeDateBtn = document.getElementById('closeDate');
    const column1 = document.getElementById('column1');
    const column2 = document.getElementById('column2');
    const manualDateWrapper = manualDateBtn.closest('.col-md-5');
    const newHuboBtn = document.getElementById('newHuboBtn');
    const lastHuboInput = document.getElementById('lastHubo');

    manualDateBtn.addEventListener('click', () => { manualDateWrapper.style.display = 'none'; column1.style.display = 'block'; column2.style.display = 'block'; });
    closeDateBtn.addEventListener('click', () => { column1.style.display = 'none'; column2.style.display = 'none'; manualDateWrapper.style.display = 'block'; });

    if (newHuboBtn && lastHuboInput) {
      newHuboBtn.addEventListener('click', () => {
        Swal.fire({ title: 'Confirm New Hubo', text: 'Set the last hubo reading to 0?', icon: 'question',
                    showCancelButton: true, confirmButtonText: 'Yes, set to 0', cancelButtonText: 'Cancel' })
          .then(r => { if (!r.isConfirmed) return; lastHuboInput.value = 0;
                       if (typeof calculateKmRun === 'function') calculateKmRun();
                       Swal.fire({ title: 'Updated', text: 'Last hubo is now 0.', icon: 'success', timer: 1200, showConfirmButton: false }); });
      });
    }
  });

  // --- Driver ID lookup on blur ---
  document.getElementById("searchId").addEventListener("blur", function () {
    const idNumber = this.value.trim();
    if (idNumber.length >= 6) {
      fetch("php/fetch/get_driver_name.php?id=" + encodeURIComponent(idNumber))
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            document.getElementById("selectedDriver").value = data.driverName;
            document.getElementById("driverName").textContent = data.driverName;
            document.getElementById("driver_image").src = data.driverImage;
          } else {
            document.getElementById("selectedDriver").value = "";
            document.getElementById("driverName").textContent = "Driver Name";
            Swal.fire({ icon: 'warning', title: 'Driver Not Found', text: data.message, timer: 2000, showConfirmButton: false });
          }
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong while fetching the driver.' }));
    } else {
      document.getElementById("selectedDriver").value = "";
    }
    if (document.getElementById("ticketCode").value.trim()) loadTicketCodeDetails();
  });

  // --- Ticket code state ---
  function setTicketCodeState(isValid, message, totalKm) {
    $("#ticketCodeStatus").val(isValid ? "1" : "0");
    $("#ticketCodeInfo").val(message);
    $("#ticketKmRun").val(isValid && totalKm ? totalKm : "");
    calculateActualRatio(); calculateIdeNoLt(); calculateExcessSave();
  }

  function loadTicketCodeDetails() {
    const ticketCode = $("#ticketCode").val().trim().toUpperCase();
    $("#ticketCode").val(ticketCode);
    if (!ticketCode) return setTicketCodeState(false, "No code loaded", "");

    fetch("php/fetch/get_ticket_code_details.php?code=" + encodeURIComponent(ticketCode))
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          setTicketCodeState(false, data.message || "Invalid ticket code", "");
          Swal.fire({ icon: "warning", title: "Invalid Ticket Code", text: data.message || "Ticket code not found." });
          return;
        }
        if ((data.ticket.tc_status || "").toLowerCase() === "used") {
          setTicketCodeState(false, "Code already used", "");
          Swal.fire({ icon: "warning", title: "Code Already Used", text: "This ticket code was already used." });
          return;
        }
        const driverId = $("#searchId").val().trim();
        const unitId = $("#selectedEquipment").val().trim();
        const ticketDriverId = (data.ticket.f_driverId || "").trim();
        const ticketUnitName = (data.ticket.unit_name || "").trim().toUpperCase();
        const selectedUnitName = unitId.toUpperCase();

        if ((driverId && ticketDriverId && driverId !== ticketDriverId) || driverId === "") {
          setTicketCodeState(false, "Driver ID mismatch", "");
          Swal.fire({ icon: "warning", title: "Driver Mismatch", text: "The ticket code does not match the selected driver." });
          return;
        }
        if (unitId && ticketUnitName && selectedUnitName !== ticketUnitName) {
          setTicketCodeState(false, "Unit mismatch", "");
          Swal.fire({ icon: "warning", title: "Unit Mismatch", text: "The selected unit does not match the unit used for this ticket code." });
          return;
        }

        let inputMeterRaw1 = parseFloat($("#meterRead").val()) || 0;
        let lasthuboRaw    = parseFloat($("#lastHubo").val()) || 0;
        let ticketKmRun    = parseFloat(data.total_km) || 0;
        let totalKm2       = inputMeterRaw1 - lasthuboRaw;
        let calculatedMeter1 = lasthuboRaw + ticketKmRun;
        let diff1 = inputMeterRaw1 - calculatedMeter1;

        if (ticketCode !== "") {
          if (diff1 <= 10) {
            $("#totalKmRun").val(totalKm2.toFixed(2));
            $("#totalKmRun1").val(ticketKmRun.toFixed(2));
            $("#meterRead1").val(calculatedMeter1.toFixed(2)).prop("readonly", false).css("background", "#adb5bd");
            if (typeof calculateKmRun === "function") calculateKmRun();
          } else {
            $("#totalKmRun").val(ticketKmRun.toFixed(2));
            $("#totalKmRun1").val(totalKm2.toFixed(2));
            $("#meterRead").val(calculatedMeter1.toFixed(2)).prop("readonly", true).css("background", "#adb5bd");
            $("#meterRead1").val(inputMeterRaw1.toFixed(2)).prop("readonly", false).css("background", "#adb5bd");
          }
        }
        setTicketCodeState(true, `Loaded ${data.trips.length} trip(s)`, data.total_km);
      })
      .catch(() => {
        setTicketCodeState(false, "Lookup failed", "");
        Swal.fire({ icon: "error", title: "Error", text: "Something went wrong while checking the ticket code." });
      });
  }
  document.getElementById("ticketCode").addEventListener("blur", loadTicketCodeDetails);
  document.getElementById("ticketCode").addEventListener("input", function () {
    this.value = this.value.toUpperCase();
    if (!this.value.trim()) setTicketCodeState(false, "No code loaded", "");
  });

  // --- Skip ticket code button (for Genset / Forklift / Service) ---
  document.getElementById("skipTicketCodeBtn").addEventListener("click", function () {
    $("#skipTicketCode").val("1");
    $("#ticketCode").val("").prop("disabled", true);
    setTicketCodeState(true, "Skipped (Genset/Forklift/Service)", "");
    Swal.fire({ icon: 'info', title: 'Skipped', text: 'Ticket code is skipped for this transaction.', timer: 1500, showConfirmButton: false });
  });

  // --- Defer DataTable + jQuery-dependent handlers until libs load ---
  // (jQuery + DataTables are loaded by gas/_layout_bottom.php AFTER this script.)
  window.addEventListener('load', function () {
    // Recent transactions table (server-side processing).
    // We deliberately disable scrollX + autoWidth and rely on the Responsive
    // plugin to collapse low-priority columns on narrow viewports.
    $('#table-data').DataTable({
      processing: true,
      serverSide: true,
      autoWidth: false,
      scrollX: false,
      responsive: { details: { type: 'inline' } },
      order: [[0, 'desc']],
      ajax: { url: 'table-fetch/fuel-ticket-table.php', type: 'POST' },
      columns: [
        { title: 'Control No.',          responsivePriority: 1 },
        { title: 'Date/Time',            responsivePriority: 3 },
        { title: 'Unit',                 responsivePriority: 2 },
        { title: 'Last Hubo',            responsivePriority: 8 },
        { title: 'Hubo',                 responsivePriority: 7 },
        { title: 'Km Run',               responsivePriority: 6 },
        { title: 'No. of Lit.',          responsivePriority: 4 },
        { title: 'Act. Ratio',           responsivePriority: 9 },
        { title: 'Driver',               responsivePriority: 5 },
        { title: 'STD Ratio',            responsivePriority: 10 },
        { title: '(Excess)/Savings ±10', responsivePriority: 11 },
        { title: 'Hour Meter',           responsivePriority: 12 },
        { title: 'Action', orderable: false, searchable: false, responsivePriority: 2 }
      ],
      language: { emptyTable: 'No refueling records yet.' }
    });

    // AJAX form submit
    $('#addForm').on('submit', function (e) {
      e.preventDefault();
      const form = this;
      const hourMeter = parseFloat($('#hourMeter').val()) || 0;
      const lastHourMeter = parseFloat($('#lastHourMeter').val()) || 0;
      if (hourMeter < lastHourMeter) {
        return Swal.fire({ icon: 'error', title: 'Invalid Hour Meter',
          text: `Hour meter (${hourMeter}) cannot be less than the previous (${lastHourMeter}).` });
      }
      if (!form.checkValidity()) { form.classList.add('was-validated'); return; }

      const formData = new FormData(form);
      fetch(form.action, { method: 'POST', body: formData })
        .then(r => r.text().then(t => ({ ok: r.ok, text: t })))
        .then(({ ok, text }) => {
          if (!ok) return Swal.fire({ icon: 'error', title: 'Failed', text: text });
          Swal.fire({ icon: 'success', title: 'Saved', text: text, timer: 1500, showConfirmButton: false })
            .then(() => location.reload());
        })
        .catch(err => Swal.fire({ icon: 'error', title: 'Error', text: String(err) }));
    });
  });
</script>

<?php include 'gas/_layout_bottom.php'; ?>
