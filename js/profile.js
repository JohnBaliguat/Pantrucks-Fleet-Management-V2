// Update Image
let profilePic1 = document.getElementById("profile-pic1");
let inputFile1 = document.getElementById("input-file1");

inputFile1.onchange = function () {
    profilePic1.src = URL.createObjectURL(inputFile1.files[0]);
}
$(document).ready(function () {
    $('#editForm').submit(function (e) {
        e.preventDefault(); // Prevent default form submission

        var formData = new FormData($(this)[0]); // Correctly get form data
        var user_pass1 = $('#user_pass1').val();
        var conPassword = $('#conPassword').val();
        if (user_pass1 !== '' && user_pass1 !== conPassword) {
            Swal.fire({
                text: 'Passwords do not match!',
                icon: 'error'
            });
            return; // Exit function if passwords don't match
        }
        Swal.fire({
            title: 'Confirm Update Profile',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Confirm'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'php/crud/update/update-profile.php',
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
