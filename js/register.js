const checkIdForm = document.getElementById("checkId");

if (checkIdForm) {
    checkIdForm.addEventListener("submit", function(e) {
        e.preventDefault();

        let driver_id = document.getElementById("driver_id").value;

        fetch("checkID.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "driver_id=" + encodeURIComponent(driver_id)
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === "not_found") {
                Swal.fire("Error", "ID not available", "error");
            } else if (data.status === "has_account") {
                Swal.fire("Warning", "Driver already has an account", "warning");
            } else if (data.status === "ok") {
                // Hide checkId form, show register form
                document.getElementById("checkId").style.display = "none";
                document.getElementById("register").style.display = "block";

                // Fill hidden driver_id in register form
                document.querySelector("#register input[name='driver_id']").value = data.driver_id;
            }
        })
        .catch(err => {
            Swal.fire("Error", "Something went wrong!", "error");
            console.error(err);
        });
    });
}

    $(document).ready(function() {
        $("#register").on("submit", function(e) {
            e.preventDefault();

            let pass = $("#pass").val();
            let conpass = $("#conpass").val();

            // ✅ Password strength regex
            let strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;

            if (!strongRegex.test(pass)) {
            Swal.fire("Weak Password", 
                "Password must be at least 8 characters, include uppercase, lowercase, number, and special character.", 
                "warning"
            );
            return;
            }

            if (pass !== conpass) {
            Swal.fire("Error", "Passwords do not match!", "error");
            return;
            }

            $.ajax({
            url: "register_update.php",
            type: "POST",
            data: $(this).serialize(),
            success: function(response) {
                let res = JSON.parse(response);
                if (res.status === "success") {
                Swal.fire({
                    icon: "success",
                    title: "Account Created!",
                    text: res.message,
                    showConfirmButton: true
                }).then(() => {
                    window.location.href = "login.php";
                });
                } else {
                Swal.fire("Error", res.message, "error");
                }
            },
            error: function() {
                Swal.fire("Error", "Something went wrong!", "error");
            }
            });
        });
    });
