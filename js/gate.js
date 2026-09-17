const table = new DataTable('#attend-table', {
    processing: true,
    serverSide: true,
    ajax: {
        url: 'table-fetch/attend-table.php',
        type: 'POST'
    },
    responsive: true,
    searching: false
});
$(document).ready(function () {
    setInterval(function () {
        $('#rfid_no').focus();
    }, 100);

    $('body').mousemove(function () {
        $('#rfid_no').focus();
    });

    $('#rfid_no').on('keydown', function (e) {
        if (e.keyCode === 13) {
            e.preventDefault();
            var rfid = $(this).val().trim();

            if (rfid === "") return;

            processRFID(rfid);
        }
    });

    function processRFID(rfid) {
        $.ajax({
            type: "POST",
            url: "php/operations/checkin_checkout.php",
            data: { rfid: rfid },
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    $('#rfid_no').val("");

                    Swal.fire({
                        position: 'center',
                        icon: data.status === "success" ? 'success' : data.status === "info" ? 'info' : 'error',
                        title: data.message,
                        showConfirmButton: false,
                        timer: 2000
                    });

                    if (data.status === "success") {
                        // Update driver card
                        $('#driver_img').attr('src', 'php/uploads/' + data.profile_image || 'assets/images/profile/user-7.jpg');
                        $('#driver_name').text(data.driver_name);
                        $('#truck_name').text(data.truck || '-');
                        $('#trailer_name').text(data.trailer || '-');
                        $('#genset_name').text(data.genset || '-');
                        $('#trip_segment').text(data.segment || '-');
                        table.ajax.reload();
                    }

                } catch (e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid server response',
                        text: response
                    });
                }
                table.ajax.reload();
            },
            error: function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Request failed. Check network/server.'
                });
            }
        });
    }
});
