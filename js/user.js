$(document).ready(function () {
    $("#Mnav").attr({
        "class": "nav-link dropdown-toggle active"
    });
    $('#table-data').DataTable();

    // Phase 12 — show the Customer Code input only when user_type = Client.
    function applyClientOnlyToggle() {
        $('[data-client-only]').each(function() {
            var $field = $(this);
            var targetId = $field.data('user-type-target');
            var val = $('#' + targetId).val();
            $field.toggle(val === 'Client');
            $field.find('input').prop('required', val === 'Client');
            if (val !== 'Client') {
                $field.find('input').val('');
            }
        });
    }
    $(document).on('change', '#user_type, #user_type1', applyClientOnlyToggle);
    applyClientOnlyToggle();

    $("#AddModal").click(function () {
        $("#addmodal").modal("show");
        applyClientOnlyToggle();
    });
    $("#close1").click(function () {
        $('#addForm')[0].reset();
        $("#addmodal").modal("hide");
    });
    $('#addUser').click(function (e) {
        e.preventDefault();

        var user_fname = $("#user_fname").val();
        var user_lname = $("#user_lname").val();
        var user_mname = $("#user_mname").val();
        var username = $("#username").val();
        var user_email = $("#user_email").val();
        var user_pass = $("#user_pass").val();
        var user_type = $("#user_type").val();
        var user_assignLocation = $("#user_assignLocation").val();
        var customer_code = $("#customer_code").val().trim();
        if (user_fname == "" || user_lname == "" || user_mname == "" || username == "" || user_email == "" || user_pass == "" || user_type == "" || user_assignLocation == "") {
            Swal.fire({
                text: 'Please fill in all required fields',
                icon: 'info'
            });
            return;
        }
        if (user_type === 'Client' && customer_code === '') {
            Swal.fire({
                text: 'Customer Code is required for Client logins',
                icon: 'info'
            });
            return;
        }
        Swal.fire({
            title: 'Confirm Register User',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Confirm'
        }).then((result) => {
            if (result.isConfirmed) {
                var formData = new FormData($('#addForm')[0]);
                $.ajax({
                    url: 'php/crud/add/adduser.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    cache: false,
                    processData: false,
                    success: function (data) {
                        Swal.fire({
                            text: data,
                            icon: 'success',
                            showConfirmButton: false,
                            timer: 1200
                        });
                        $('#addForm')[0].reset();
                        $("#addmodal").modal("hide");
                        setTimeout(() => {
                            location.reload();
                        }, 1300);
                    },
                    error: function (xhr, status, error) {
                        // The server returns the real reason in the response
                        // body (HTTP 422 / 500). Surface it instead of the
                        // empty `error` argument that jQuery passes for non-2xx.
                        var detail = (xhr && xhr.responseText) ? xhr.responseText : (error || status || 'Unknown error');
                        Swal.fire({
                            text: 'Error: ' + detail,
                            icon: 'error'
                        });
                    }
                });
            }
        });
    });

    $('#UpdateUser').click(function () {
        var id = $('#user_Id1').val();
        var userType = $('#user_type1').val();
        var customerCode = $('#customer_code1').val().trim();
        var formData = new FormData($('#editForm')[0]);

        if (userType === 'Client' && customerCode === '') {
            Swal.fire({
                text: 'Customer Code is required for Client logins',
                icon: 'info'
            });
            return;
        }

        Swal.fire({
            title: 'Confirm Update User',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Confirm'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'php/crud/update/updateuser.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    cache: false,
                    processData: false,
                    success: function (data) {
                        Swal.fire({
                            text: data,
                            icon: 'success',
                            showConfirmButton: false,
                            timer: 1200
                        });
                        $('#editModal').modal('hide');
                        setTimeout(() => {
                            location.reload();
                        }, 1300);
                    },
                    error: function (xhr, status, error) {
                        var detail = (xhr && xhr.responseText) ? xhr.responseText : (error || status || 'Unknown error');
                        Swal.fire({
                            text: 'Error: ' + detail,
                            icon: 'error'
                        });
                    }
                });
            }
        });
    });
});

function openUpdateUser(user_id, user_name, user_fname, user_lname, user_mname, user_assignLocation, user_email, user_pass, user_type, user_accountStat, customer_code) {
    $('#user_Id1').val(user_id);
    $('#user_fname1').val(user_fname);
    $('#user_lname1').val(user_lname);
    $('#user_mname1').val(user_mname);
    $('#username1').val(user_name);
    $('#user_email1').val(user_email);
    $('#user_type1').val(user_type);
    $('#user_stat1').val(user_accountStat);
    $('#user_assignLocation1').val(user_assignLocation);
    $('#customer_code1').val(customer_code || '');
    $('#user_type1').trigger('change');

    $('#editModal').modal('show');
}

function deleteUser(user_id) {
    var user_Id = user_id;

    var form_data = {
        user_Id: user_Id

    };
    Swal.fire({
        title: 'Confirm Remove User',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Confirm'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: "php/crud/delete/deleteuser.php",
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
                        setTimeout(() => {
                            location.reload();
                        }, 1300);
                    }
                }

            });
        }
    });
}
