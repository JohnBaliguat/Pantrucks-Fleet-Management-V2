// Drop and Drag
const dropArea = document.getElementById('drop-area');
const bookingFile = document.getElementById('bookingFile');
const fileNameDiv = document.getElementById('fileName');

if (dropArea && bookingFile && fileNameDiv) {
    dropArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropArea.style.background = '#f1f1f1';
    });
    dropArea.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropArea.style.background = '';
    });
    dropArea.addEventListener('drop', (e) => {
        e.preventDefault();
        dropArea.style.background = '';
        bookingFile.files = e.dataTransfer.files;
        showFileName();
    });

    bookingFile.addEventListener('change', showFileName);
}

function showFileName() {
    if (!bookingFile || !fileNameDiv) return;
    if (bookingFile.files.length > 0) {
        fileNameDiv.textContent = 'Selected file: ' + bookingFile.files[0].name;
    } else {
        fileNameDiv.textContent = '';
    }
}

const uploadForm = document.getElementById('uploadForm');
if (uploadForm && bookingFile) {
    uploadForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const uploadStatus = document.getElementById('uploadStatus');
        const file = bookingFile.files[0];
        if (!file) {
            if (uploadStatus) {
                uploadStatus.innerHTML = '<span style="color:red;">Please select an Excel file.</span>';
            }
            return;
        }
        if (!(/\.(xlsx|xls)$/i).test(file.name)) {
            if (uploadStatus) {
                uploadStatus.innerHTML = '<span style="color:red;">Only Excel files (.xlsx, .xls) are allowed.</span>';
            }
            return;
        }
        const reader = new FileReader();
        reader.onload = function (e) {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
            displayBookings(jsonData);
            if (uploadStatus) {
                uploadStatus.innerHTML = '<span style="color:green;">File uploaded successfully!</span>';
            }
        };
        reader.readAsArrayBuffer(file);
    });
}

function displayBookings(data) {
    const tbody = document.querySelector('#bookingTable tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    for (let i = 1; i < data.length; i++) { // skip header
        const row = data[i];
        const tr = document.createElement('tr');
        for (let j = 0; j < 5; j++) {
            const td = document.createElement('td');
            td.className = 'px-0';
            td.textContent = row[j] !== undefined ? row[j] : '';
            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    }
}

// END of Drop And Drag

// Ajax
if (document.querySelector('#booking-table')) {
    // Current tab: 'active' (default) or 'complete'. Tab clicks update this and reload the table.
    var currentBookingView = 'active';

    const table = new DataTable('#booking-table', {
              processing: true,
              serverSide: true,
              ajax: {
                  url: 'table-fetch/booking-table.php',
                  type: 'POST',
                  data: function (d) {
                      d.view = currentBookingView;
                      return d;
                  }
              },
              responsive: true,
              columnDefs: [
                  { targets: 0, orderable: false, searchable: false, className: 'text-center align-middle' }
              ],
              order: [[1, 'desc']]
          });

    // Tab switching — flip the view flag and reload (page reset to 1 since the
    // dataset changes).
    $(document).on('click', '#bookingViewTabs button[data-view]', function () {
        var $btn = $(this);
        if ($btn.hasClass('active')) return;
        $('#bookingViewTabs button[data-view]').removeClass('active');
        $btn.addClass('active');
        currentBookingView = $btn.data('view');
        // On the Completed tab, bulk delete and per-row select don't apply.
        var isComplete = currentBookingView === 'complete';
        $('#bulkDeleteBookings').toggle(!isComplete);
        table.column(0).visible(!isComplete, false);
        table.ajax.reload();
    });

    // Helper that finds row checkboxes everywhere DataTables might put them
    // (visible cells, Responsive child rows, hidden columns) and returns a
    // single jQuery collection.
    function allRowChecks() {
        var $direct = $('#booking-table').find('input.booking-row-check');
        var $children = $('#booking-table').next('.dtr-details').find('input.booking-row-check');
        var $childRows = $('tr.child input.booking-row-check');
        return $direct.add($children).add($childRows);
    }

    function updateBulkDeleteState() {
        var $boxes = allRowChecks();
        var $checked = $boxes.filter(':checked');
        var count = $checked.length;
        var $btn = $('#bulkDeleteBookings');
        $btn.prop('disabled', count === 0);
        $btn.text(count > 0 ? 'Delete Selected (' + count + ')' : 'Delete Selected');
        var allChecked = $boxes.length > 0 && count === $boxes.length;
        $('#bookingSelectAll').prop('checked', allChecked).prop('indeterminate', count > 0 && !allChecked);
    }

    // Re-evaluate after every redraw (pagination / search / sort).
    table.on('draw', function () {
        $('#bookingSelectAll').prop('checked', false).prop('indeterminate', false);
        updateBulkDeleteState();
    });

    // Use 'click' so it fires even if 'change' is swallowed by Bootstrap styling,
    // and read the state via the DOM property after the click has toggled it.
    $(document).on('click', '#bookingSelectAll', function () {
        var isChecked = !!this.checked;
        allRowChecks().each(function () { this.checked = isChecked; });
        updateBulkDeleteState();
    });

    $(document).on('change', '.booking-row-check', updateBulkDeleteState);

    // Per-row action buttons — delegated so they survive DataTables redraws,
    // tab switches, and Responsive child-row collapsing.
    $(document).on('click', '[data-edit-booking]', function (e) {
        e.preventDefault();
        var id = parseInt($(this).attr('data-edit-booking'), 10);
        if (id > 0 && typeof editBooking === 'function') editBooking(id);
    });
    $(document).on('click', '[data-delete-booking]', function (e) {
        e.preventDefault();
        var id = parseInt($(this).attr('data-delete-booking'), 10);
        if (id > 0 && typeof deleteBooking === 'function') deleteBooking(id);
    });

    $(document).on('click', '#bulkDeleteBookings', function () {
        var ids = $('.booking-row-check:checked').map(function () { return $(this).val(); }).get();
        if (ids.length === 0) {
            Swal.fire({ icon: 'info', text: 'Select at least one booking to delete.' });
            return;
        }
        Swal.fire({
            title: 'Delete ' + ids.length + ' booking' + (ids.length === 1 ? '' : 's') + '?',
            text: 'Only unused bookings (used quantity = 0) will be deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Delete'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            $.ajax({
                url: 'php/crud/delete/deletebookings_bulk.php',
                type: 'POST',
                data: { 'booking_ids[]': ids },
                dataType: 'json'
            }).done(function (res) {
                Swal.fire({
                    icon: res && res.status === 'success' ? 'success' : (res && res.status === 'warning' ? 'warning' : 'error'),
                    text: (res && res.msg) || 'Bulk delete completed.',
                    timer: 1600,
                    showConfirmButton: false
                });
                $('#bookingSelectAll').prop('checked', false);
                table.ajax.reload(null, false);
                setTimeout(updateBulkDeleteState, 100);
            }).fail(function () {
                Swal.fire({ icon: 'error', text: 'Network error while deleting bookings.' });
            });
        });
    });
}

    // Phase 14.1 — booking_no is now Customer-BN-From-To, so the
    // preview is driven by the form inputs, not by polling.
    function fetchBookingNo() {
      var customer = (document.getElementById('costumer') || {}).value || '';
      var from     = (document.getElementById('trip_from') || {}).value || '';
      var to       = (document.getElementById('trip_to')   || {}).value || '';
      var url = 'php/fetch/get_next_booking_no.php?customer=' + encodeURIComponent(customer) +
                '&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
      fetch(url)
        .then(function(r) { return r.text(); })
        .then(function(data) {
          var $bn  = document.getElementById('booking_no');
          var $bn2 = document.getElementById('booking_no2');
          if ($bn)  $bn.value  = data;
          if ($bn2) $bn2.value = data;
        });
    }

    // Initial load + re-fetch whenever the inputs that compose the
    // booking_no change.
    fetchBookingNo();
    ['costumer', 'trip_from', 'trip_to'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) {
        el.addEventListener('change', fetchBookingNo);
        el.addEventListener('input',  fetchBookingNo);
      }
    });

    function applyCthBookingMode(formSelector, suffix, blockClass) {
        var $form = $(formSelector);
        if (!$form.length) return;

        var customer = ($form.find('#costumer' + suffix).val() || '').trim().toUpperCase();
        var isCth = customer === 'CTH';
        var $cthBlocks = $form.find('.' + blockClass);
        var $requiredDate = $form.find('#booking_required' + suffix);
        var $containerStatus = $form.find('#container_status' + suffix);
        var $returnLocation = $form.find('#return_location' + suffix);
        var cthIds = ['booking_sn', 'booking_do', 'haulingStart', 'lastDayStorage', 'lastDayDemurrage', 'lastDayDetention'];

        $cthBlocks.toggle(isCth);
        $requiredDate.prop('required', !isCth);

        if (isCth && suffix === '' && $containerStatus.length) {
            $containerStatus.val('Loaded');
        }

        if ($returnLocation.length) {
            var statusValue = String($containerStatus.val() || '').trim().toLowerCase();
            var isLoadedLane = statusValue.indexOf('loaded') === 0;
            $returnLocation.prop('required', isCth && isLoadedLane);
            if (!isCth) {
                $returnLocation.val('');
            }
        }

        cthIds.forEach(function(baseId) {
            var $field = $form.find('#' + baseId + suffix);
            if (!$field.length) return;
            $field.prop('required', isCth);
            if (!isCth) {
                $field.val('');
            }
        });
    }
     $(document).ready(function() {
        $("#addbooking").click(function () {
          $("#AddBookingModal").modal("show");
          applyCthBookingMode('#addForm', '', 'cth-only-add');
        });
        $("#addbooking1").click(function () {
          $("#AddBookingModal1").modal("show");
        });

        // Auto-fill Hauling Segment from the selected customer's segment so it
        // isn't entered twice. Editable: only overwrite when the field is empty
        // or still holds a previous auto-fill (preserves a manual override).
        function mirrorSegmentToHauling(custSel, haulSel) {
            var $cust = $(custSel), $haul = $(haulSel);
            if (!$cust.length || !$haul.length) return;
            var seg = String($cust.find('option:selected').attr('data-segment') || '').trim();
            var prev = String($haul.data('autoSeg') || '');
            var cur = String($haul.val() || '');
            if (seg) {
                if (cur === '' || cur === prev) { $haul.val(seg); $haul.data('autoSeg', seg); }
            } else if (cur === prev) {
                $haul.val(''); $haul.data('autoSeg', '');
            }
        }

        $('#costumer').on('change', function () {
            applyCthBookingMode('#addForm', '', 'cth-only-add');
            mirrorSegmentToHauling('#costumer', '#hauling_segment');
        });
        $('#container_status').on('change', function () {
            applyCthBookingMode('#addForm', '', 'cth-only-add');
        });
        $('#costumer1').on('change', function () {
            applyCthBookingMode('#editForm', '1', 'cth-only-edit');
            mirrorSegmentToHauling('#costumer1', '#hauling_segment1');
        });
        $('#container_status1').on('change', function () {
            applyCthBookingMode('#editForm', '1', 'cth-only-edit');
        });
        applyCthBookingMode('#addForm', '', 'cth-only-add');
        applyCthBookingMode('#editForm', '1', 'cth-only-edit');

        $("#saveBookingBtn").click(function (e) {
            e.preventDefault();

            // Get values for validation
            var costumer = $("#costumer").val();
            var booking_date = $("#booking_date").val();
            var container = $("#container").val();
            var container_status = $("#container_status").val();
            var hauling_segment = $("#hauling_segment").val();
            var trip_from = $("#trip_from").val();
            var trip_to = $("#trip_to").val();
            var quantity = $("#quantity").val();
            var returnLocation = $("#return_location").val();
            var isCth = costumer === 'CTH';

            if (
                costumer === "" || booking_date === "" || container_status === "" || hauling_segment === "" ||
                trip_from === "" || trip_to === "" || quantity === ""
            ) {
                Swal.fire({
                    text: 'Please fill in all required fields',
                    icon: 'info'
                });
                return;
            }
            if (isCth) {
                var cthRequired = ['#booking_sn', '#booking_do', '#haulingStart', '#lastDayStorage', '#lastDayDemurrage', '#lastDayDetention'];
                var missingCth = cthRequired.some(function(sel) {
                    return ($(sel).val() || '').trim() === '';
                });
                if (missingCth) {
                    Swal.fire({
                        text: 'Please fill in all CTH required fields',
                        icon: 'info'
                    });
                    return;
                }
                if ((returnLocation || '').trim() === '') {
                    Swal.fire({
                        text: 'Return Location is required for CTH bookings',
                        icon: 'info'
                    });
                    return;
                }
            }

            Swal.fire({
                title: 'Confirm Save Booking?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Confirm'
            }).then((result) => {
                if (result.isConfirmed) {
                    var formData = new FormData($('#addForm')[0]);

                    $.ajax({
                        url: 'php/crud/add/addbooking.php',
                        type: 'POST',
                        data: formData,
                        contentType: false,
                        cache: false,
                        processData: false,
                        success: function (response) {
                            let res = JSON.parse(response);
                            Swal.fire({
                                text: res.message,
                                icon: res.status,
                                showConfirmButton: false,
                                timer: 1500
                            });
                            $('#addForm')[0].reset();
                            $('#AddBookingModal').modal('hide');
                            if ($.fn.DataTable.isDataTable('#booking-table')) {
                                $('#booking-table').DataTable().ajax.reload(null, false);
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
        $("#saveChangesBtn").click(function (e) {
            e.preventDefault();

            // Get values for validation
            var booking_no1 = $("#booking_no1").val();
            var booking_date1 = $("#booking_date1").val();
            var customer1 = $("#costumer1").val(); // fixed spelling
            var container1 = $("#container1").val();
            var container_status1 = $("#container_status1").val();
            var hauling_segment1 = $("#hauling_segment1").val();
            var trip_from1 = $("#trip_from1").val();
            var trip_to1 = $("#trip_to1").val();
            var quantity1 = $("#quantity1").val();
            var returnLocation1 = $("#return_location1").val();
            var isCth1 = customer1 === 'CTH';

            if (
                booking_no1 === "" || booking_date1 === "" || customer1 === "" || container_status1 === "" || hauling_segment1 === "" ||
                trip_from1 === "" || trip_to1 === "" || quantity1 === ""
            ) {
                Swal.fire({
                    text: 'Please fill in all required fields',
                    icon: 'info'
                });
                return;
            }
            if (isCth1) {
                var editCthRequired = ['#booking_sn1', '#booking_do1', '#haulingStart1', '#lastDayStorage1', '#lastDayDemurrage1', '#lastDayDetention1'];
                var missingEditCth = editCthRequired.some(function(sel) {
                    return ($(sel).val() || '').trim() === '';
                });
                if (missingEditCth) {
                    Swal.fire({
                        text: 'Please fill in all CTH required fields',
                        icon: 'info'
                    });
                    return;
                }
                if ((container_status1 || '').toLowerCase().indexOf('loaded') === 0 && (returnLocation1 || '').trim() === '') {
                    Swal.fire({
                        text: 'Return Location is required for CTH loaded bookings',
                        icon: 'info'
                    });
                    return;
                }
            }

            Swal.fire({
                title: 'Confirm Save Changes?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Confirm'
            }).then((result) => {
                if (result.isConfirmed) {
                    var formData = new FormData($('#editForm')[0]);

                    $.ajax({
                        url: 'php/operations/editbooking.php',
                        type: 'POST',
                        data: formData,
                        contentType: false,
                        cache: false,
                        processData: false,
                        success: function (response) {
                            try {
                                let res = JSON.parse(response);
                                Swal.fire({
                                    text: res.message,
                                    icon: res.status,
                                    showConfirmButton: false,
                                    timer: 1500
                                });
                                $('#editForm')[0].reset();
                                $('#editModal').modal('hide');
                                if ($.fn.DataTable.isDataTable('#booking-table')) {
                                    $('#booking-table').DataTable().ajax.reload(null, false);
                                }
                            } catch (e) {
                                Swal.fire({
                                    text: 'Invalid JSON: ' + response,
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

    function editBooking(booking_id) {
        $.getJSON('php/fetch/get_booking.php', { booking_id: booking_id }, function (res) {
            if (res.status !== 'success') {
                Swal.fire({ icon: 'error', text: res.message || 'Failed to load booking' });
                return;
            }
            var b = res.booking;
            $('#booking_id1').val(b.booking_id);
            $('#booking_no1').val(b.booking_no);
            $('#booking_type1').val(b.booking_type || 'Local');
            $('#booking_date1').val(b.booking_date);
            // PostgreSQL returns column names lowercased — accept either casing.
            $('#booking_required1').val(b.booking_daterequired || b.booking_dateRequired || '');
            $('#costumer1').val(b.costumer);
            // Phase 11 — auto-fill segment from the just-loaded customer.
            $('#costumer1').trigger('change');
            if (b.customer_segment) {
                $('#customer_segment1').val(b.customer_segment);
            }
            $('#container_seal1').val(b.container_seal);
            $('#container1').val(b.container);
            $('#booking_activity1').val(b.booking_activity);
            // Phase 11 — normalise legacy status values onto the new enum so
            // the select displays a valid option after edit-load.
            var legacyMap = { 'EMPTY': 'Empty', 'LOADED': 'Loaded', 'N/A': 'Empty' };
            var status = legacyMap[b.container_status] || b.container_status || 'Empty';
            $('#container_status1').val(status);
            $('#hauling_segment1').val(b.hauling_segment);
            $('#trip_from1').val(b.trip_from);
            $('#trip_to1').val(b.trip_to);
            $('#return_location1').val(b.return_location || '');
            $('#quantity1').val(b.quantity);
            $('#booking_sn1').val(b.booking_sn || '');
            $('#booking_do1').val(b.booking_do || '');
            $('#haulingStart1').val(b.booking_haulingstartdate || b.booking_haulingStartDate || '');
            $('#lastDayStorage1').val(b.booking_lastdatestorage || b.booking_LastDateStorage || '');
            $('#lastDayDemurrage1').val(b.booking_lastdatedemurrage || b.booking_LastDateDemurrage || '');
            $('#lastDayDetention1').val(b.booking_lastdatedetention || b.booking_LastDateDetention || '');

            // Phase 2 — port fields.
            $('#vessel_name1').val(b.vessel_name || '');
            $('#voyage_no1').val(b.voyage_no || '');
            $('#container_no_port1').val(b.container_no_port || '');
            $('#bill_of_lading1').val(b.bill_of_lading || '');
            $('#port_location1').val(b.port_location || '');
            $('#customs_cleared1').prop('checked', b.customs_cleared == 1);

            // Trigger the booking-type toggle to show/hide port panel.
            $('#booking_type1').trigger('change');
            applyCthBookingMode('#editForm', '1', 'cth-only-edit');

            // If any quantity has been used, lock everything except quantity.
            var quantity_use = parseInt(b.quantity_use, 10) || 0;
            var lockable = '#booking_type1, #booking_no1, #booking_date1, #booking_required1, #costumer1, ' +
                '#booking_sn1, #booking_do1, #haulingStart1, #lastDayStorage1, #lastDayDemurrage1, #lastDayDetention1, ' +
                '#container_seal1, #container1, #booking_activity1, #container_status1, #hauling_segment1, ' +
                '#trip_from1, #trip_to1, #vessel_name1, #voyage_no1, #container_no_port1, ' +
                '#bill_of_lading1, #port_location1, #return_location1';
            if (quantity_use !== 0) {
                $(lockable).prop('readonly', true);
                $('#booking_type1').prop('disabled', true);
                $('#quantity1').prop('readonly', false);
            } else {
                $(lockable + ', #quantity1').prop('readonly', false);
                $('#booking_type1').prop('disabled', false);
            }

            $('#editModal').modal('show');
        }).fail(function () {
            Swal.fire({ icon: 'error', text: 'Failed to load booking' });
        });
    }
        function deleteBooking(booking_id) {
            var booking_Id = booking_id;

            var form_data = {
                booking_Id: booking_Id

            };
            Swal.fire({
                title: 'Confirm Remove Booking',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Confirm'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "php/crud/delete/deletebooking.php",
                        type: "POST",
                        data: form_data,
                        dataType: "json",
                        success: function (response) {
                            if (response['valid'] == false) {
                                Swal.fire({
                                    text: response['msg'],
                                    icon: 'warning'
                                });
                            } else {
                                Swal.fire({
                                    text: response['msg'],
                                    icon: 'success',
                                    showConfirmButton: false,
                                    timer: 1200
                                });
                                if ($.fn.DataTable.isDataTable('#booking-table')) {
                                    $('#booking-table').DataTable().ajax.reload(null, false);
                                }
                            }
                        }

                    });
                }
            });
        }
