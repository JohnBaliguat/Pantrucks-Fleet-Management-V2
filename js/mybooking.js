function showUnitDispatch(unitId, unitName) {
    document.getElementById("modalUnitName").textContent = unitName;
    const tbody = document.getElementById("dispatchTableBody");
    tbody.innerHTML = `<tr><td colspan="13" class="text-center">Loading...</td></tr>`;

    fetch("php/fetch/fetch_dispatch.php?unit_id=" + unitId)
        .then(res => res.json())
        .then(data => {
            if (data.length > 0) {
                tbody.innerHTML = "";
                data.forEach(row => {
                    tbody.innerHTML += `
                <tr>
                  <td>${row.booking_no}</td>
                  <td>${row.d_datetime}</td>
                  <td>${row.d_dispatcher}</td>
                  <td>${row.d_driverName}</td>
                  <td>${row.d_truck}</td>
                  <td>${row.d_trailer ?? ''}</td>
                  <td>${row.trip_type}</td>
                  <td>${row.trip_container} (${row.trip_containerStat})</td>
                  <td>${row.trip_haulingSegment} - ${row.trip_haulingType}</td>
                  <td>${row.trip_from}</td>
                  <td>${row.trip_to}</td>
                </tr>`;
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="13" class="text-center">No dispatch records found.</td></tr>`;
            }
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="13" class="text-center text-danger">Error loading data</td></tr>`;
        });

    // Show modal
    new bootstrap.Modal(document.getElementById("unitDispatchModal")).show();
}
$(document).ready(function () {
    $('#confirmAssignBtn1').click(function (e) {
        e.preventDefault();

        var booking_no = $("#assignBookingName").val();
        var trip_receipt = $("#trip_receipt").val();
        var ecs = $("#ecs").val();
        var driver = $("#driver").val();
        var driver_id = $("#driverId").val();
        var genset = $("#genset").val();
        var trailer = $("#trailer").val();
        var assignLocation = $("#assinglocation").val();
        var userName = $("#userName").val();
        var unitName = $("#assignUnitName1").val();

        if (!booking_no || !driver || !driver_id || !unitName) {
            Swal.fire({
                text: 'Please fill in all required fields',
                icon: 'info'
            });
            return;
        }

        Swal.fire({
            title: 'Confirm Assignment',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Confirm'
        }).then((result) => {
            if (result.isConfirmed) {
                var formData = new FormData();
                formData.append('booking_no', booking_no);
                formData.append('trip_receipt', trip_receipt);
                formData.append('ecs', ecs);
                formData.append('driver', driver);
                formData.append('driver_id', driver_id);
                formData.append('genset', genset);
                formData.append('trailer', trailer);
                formData.append('assignLocation', assignLocation);
                formData.append('userName', userName);
                formData.append('unitName', unitName);

                $.ajax({
                    url: 'php/operations/assign_booking.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function (data) {
                        if (data.status === "success") {
                            Swal.fire({
                                text: data.message,
                                icon: 'success',
                                showConfirmButton: false,
                                timer: 1200
                            });
                            $('#assignModal1').modal("hide");
                            setTimeout(() => {
                                window.open("index.php?route=print&id=" + data.insert_id, "_blank");
                                location.reload();
                            }, 1300);
                        } else {
                            Swal.fire({
                                text: data.message,
                                icon: 'error'
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        Swal.fire({
                            text: 'Error: ' + error,
                            icon: 'error'
                        });
                    }
                });
            }
        });
    });
});
document.addEventListener("DOMContentLoaded", function () {
    // ===== Driver Search =====
    const driverInput = document.getElementById("driver");
    const driverIdInput = document.getElementById("driverId");
    const driverList = document.getElementById("driverList");
    let allDrivers = [];

    fetch("php/fetch/get_drivers.php")
        .then(res => res.json())
        .then(data => { allDrivers = data; });

    driverInput.addEventListener("input", function () {
        filterDropdown(this, driverList, allDrivers.map(d => d.name), (name) => {
            driverInput.value = name;
            const selected = allDrivers.find(d => d.name === name);
            driverIdInput.value = selected ? selected.id : "";
        });
    });

    // ===== Genset Search =====
    const gensetInput = document.getElementById("genset");
    const gensetList = document.getElementById("gensetList");
    let allGensets = [];

    fetch("php/fetch/get_gensets.php")
        .then(res => res.json())
        .then(data => { allGensets = data; });

    gensetInput.addEventListener("input", function () {
        filterDropdown(this, gensetList, allGensets, (name) => {
            gensetInput.value = name;
        });
    });

    // ===== Trailer Search =====
    const trailerInput = document.getElementById("trailer");
    const trailerList = document.getElementById("trailerList");
    let allTrailers = [];

    fetch("php/fetch/get_trailers.php")
        .then(res => res.json())
        .then(data => { allTrailers = data; });

    trailerInput.addEventListener("input", function () {
        filterDropdown(this, trailerList, allTrailers, (name) => {
            trailerInput.value = name;
        });
    });

    // ===== Shared Function =====
    function filterDropdown(inputElem, listElem, dataArr, onSelect) {
        const searchVal = inputElem.value.toLowerCase();
        listElem.innerHTML = "";

        if (!searchVal) {
            listElem.style.display = "none";
            return;
        }

        const filtered = dataArr.filter(item => item.toLowerCase().includes(searchVal));

        if (filtered.length === 0) {
            listElem.style.display = "none";
            return;
        }

        filtered.forEach(item => {
            const li = document.createElement("li");
            li.className = "list-group-item";
            li.textContent = item;
            li.addEventListener("click", function () {
                onSelect(item);
                listElem.style.display = "none";
            });
            listElem.appendChild(li);
        });

        listElem.style.display = "block";
    }

    // Hide all dropdowns on click outside
    document.addEventListener("click", function (e) {
        [driverList, gensetList, trailerList].forEach(list => {
            if (!list.contains(e.target) &&
                !driverInput.contains(e.target) &&
                !gensetInput.contains(e.target) &&
                !trailerInput.contains(e.target)) {
                list.style.display = "none";
            }
        });
    });
});


// DROP DOWN FUNCTION
const list = document.getElementById("bookingList");
const searchInput = document.getElementById("searchInput");
let allBookings = [];

let selectedClass = "ui-state-highlight"; // highlight selected items
let clickDelay = 600; // ms
let lastClick = 0;

// ===== Fetch Bookings from PHP =====
fetch("php/fetch/get_bookings.php")
    .then(res => res.json())
    .then(data => {
        allBookings = data;
        renderList(allBookings);
    });

// ===== Render Booking List =====
function renderList(items) {
    list.innerHTML = "";

    items.forEach((booking, index) => {
        const li = document.createElement("li");
        li.className = "booking-item list-group-item";
        li.draggable = true;
        li.dataset.index = index;
        li.dataset.bookingId = booking.booking_id;
        li.dataset.dateRequired = booking.dateRequired;
        li.dataset.bookingDate = booking.bookingDate;
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const requiredDateParts = booking.dateRequired.split("-");
        const requiredDate = new Date(
            requiredDateParts[0],
            requiredDateParts[1] - 1,
            requiredDateParts[2]
        );

        const diffDays = Math.floor((requiredDate - today) / (1000 * 60 * 60 * 24));

        const days = Math.min(Math.max(diffDays, 0), 5);

        // Calculate fade: 0 days = 200, 5 days = 255
        let red = 255;
        let green = 200 + (days * 11); // 200 → 255 over 5 days
        let blue  = 200 + (days * 11);

        li.style.backgroundColor = `rgb(${red}, ${green}, ${blue})`;

        li.innerHTML = `
          <div class="booking-left">
            <img src="${booking.img}" alt="user" />
            <div class="booking-text">
              <strong>${booking.bookingNo}</strong><br />
              <strong>${booking.costumerName}</strong><br/>
              <small>${booking.location}</small><br/>
              <small>${booking.others}</small><br/>
              <small><b>Booked Date:</b> ${booking.bookingDate}</small><br/>
              <small><b>Required:</b> ${booking.dateRequired}</small>
            </div>
          </div>
          <div class="booking-number">${booking.quantity}</div>
        `;

        // ---- selection support (click vs drag) ----
        setupSelection(li, index);

        // ---- drag events ----
        li.addEventListener("dragstart", handleDragStart);
        li.addEventListener("dragend", handleDragEnd);

        list.appendChild(li);
    });
}

let lastSelectedIndex = null; // remember last clicked item

function setupSelection(li, index) {
    li.addEventListener("click", (e) => {
        const items = document.querySelectorAll("#bookingList .booking-item");

        if (e.shiftKey && lastSelectedIndex !== null) {
            // SHIFT + Click: select range
            let start = Math.min(lastSelectedIndex, index);
            let end = Math.max(lastSelectedIndex, index);

            items.forEach((el, i) => {
                if (i >= start && i <= end) {
                    el.classList.add(selectedClass);
                }
            });
        } else {
            // Normal click: clear all and select only this
            items.forEach(el => el.classList.remove(selectedClass));
            li.classList.add(selectedClass);
            lastSelectedIndex = index;
        }
    });
}

// ===== Search Filter =====
searchInput.addEventListener("input", function () {
    const val = this.value.toLowerCase();
    const filtered = allBookings.filter(b =>
        b.bookingNo.toLowerCase().includes(val) ||
        b.costumerName.toLowerCase().includes(val) ||
        b.location.toLowerCase().includes(val)
    );
    renderList(filtered);
});

// ===== Drag & Drop =====
function handleDragStart(e) {
    let selected = document.querySelectorAll("." + selectedClass);

    if (selected.length === 0) {
        this.classList.add(selectedClass);
        selected = [this];
    }

    e.dataTransfer.effectAllowed = "move";

    const bookingData = Array.from(selected).map(el => ({
        bookingId: el.dataset.bookingId,
        bookingNo: el.querySelector(".booking-text strong").textContent
    }));

    e.dataTransfer.setData("bookingData", JSON.stringify(bookingData));

    selected.forEach(el => el.classList.add("dragging"));
}

function handleDragEnd() {
    document.querySelectorAll(".dragging").forEach(el => el.classList.remove("dragging"));
}

function allowDrop(e) {
    e.preventDefault();
}

// ===== Drop on Unit =====
function handleUnitDrop(e, el) {
    e.preventDefault();

    const unitCard = el.closest(".unit-card-item");
    const bookingData = JSON.parse(e.dataTransfer.getData("bookingData")); // array
    const unitName = unitCard.getAttribute("data-unit-name");
    const unitId = unitCard.getAttribute("data-unit-id");

    console.log("Dropped on Unit:", unitName, bookingData);

    // Example: show all booking numbers comma-separated
    document.getElementById("assignUnitName1").value = unitName;
    document.getElementById("assignBookingName").value = bookingData.map(b => b.bookingNo).join(", ");

    fetch("php/fetch/fetch_unit_assign.php?unit_id=" + unitId)
        .then((response) => response.json())
        .then((data) => {

            const driverIDInput = document.getElementById("driverId");
            driverIDInput.value = (data.driver_id || "").toString().trim();
            driverIDInput.readOnly = !!data.driver_id;
            
            const driverInput = document.getElementById("driver");
            driverInput.value = data.unit_assign?.trim() || "";
            driverInput.readOnly = !!data.unit_assign;

            const gensetInput = document.getElementById("genset");
            gensetInput.value = data.unit_assignGenset?.trim() || "";
            gensetInput.readOnly = !!data.unit_assignGenset;

            const trailerInput = document.getElementById("trailer");
            trailerInput.value = data.unit_assignTrailer?.trim() || "";
            trailerInput.readOnly = !!data.unit_assignTrailer;

            const modal = new bootstrap.Modal(document.getElementById("assignModal1"));
            modal.show();
        })
        .catch((err) => {
            console.error("Error fetching unit data:", err);
        });

    // clear selection after drop
    document.querySelectorAll("." + selectedClass).forEach(el => el.classList.remove(selectedClass));
}




// 
// Filtering
document.getElementById('unitSearch').addEventListener('keyup', function () {
    const searchValue = this.value.toLowerCase();
    const cards = document.querySelectorAll('.unit-card-item');
    let anyVisible = false;

    cards.forEach(card => {
        const text = card.querySelector('.card-title').textContent.toLowerCase();
        const match = text.includes(searchValue);
        card.style.display = match ? '' : 'none';
        if (match) anyVisible = true;
    });

    const message = document.getElementById('noRecordsMessage');
    if (message) {
        message.style.display = cards.length && !anyVisible ? 'block' : 'none';
    }
});

// LIST
function setView(view) {
    const container = document.getElementById("unitContainer");
    localStorage.setItem("unitView", view);

    if (view === "list") {
        container.classList.add("list-view");
        container.classList.remove("row", "g-3", "justify-content-start");

        document.querySelectorAll(".unit-card-item").forEach(el => {
            el.classList.remove("col-lg-3");
            el.classList.add("unit-list-item");
        });

    } else { // card view
        container.classList.remove("list-view");
        container.classList.add("row", "g-3", "justify-content-start");

        document.querySelectorAll(".unit-card-item").forEach(el => {
            el.classList.add("col-lg-3");
            el.classList.remove("unit-list-item");
        });
    }
}

// Load saved view on page load
document.addEventListener("DOMContentLoaded", () => {
    const savedView = localStorage.getItem("unitView") || "card";
    setView(savedView);
});
