document.querySelectorAll(".drop-zone").forEach((dropZoneElement) => {
                const inputElement = dropZoneElement.querySelector(".drop-zone__input");
                const previewImage = dropZoneElement.parentElement.querySelector(".img-preview");

                dropZoneElement.addEventListener("click", () => inputElement.click());

                inputElement.addEventListener("change", () => {
                    if (inputElement.files.length) {
                        updatePreview(previewImage, inputElement.files[0]);
                    }
                });

                dropZoneElement.addEventListener("dragover", (e) => {
                    e.preventDefault();
                    dropZoneElement.classList.add("drop-zone--over");
                });

                ["dragleave", "dragend"].forEach(type => {
                    dropZoneElement.addEventListener(type, () => dropZoneElement.classList.remove("drop-zone--over"));
                });

                dropZoneElement.addEventListener("drop", (e) => {
                    e.preventDefault();
                    if (e.dataTransfer.files.length) {
                        inputElement.files = e.dataTransfer.files;
                        updatePreview(previewImage, e.dataTransfer.files[0]);
                    }
                    dropZoneElement.classList.remove("drop-zone--over");
                });
            });

            function updatePreview(imgElement, file) {
                if (!file.type.startsWith("image/")) {
                    alert("Please upload an image file.");
                    return;
                }
                const reader = new FileReader();
                reader.onload = () => {
                    imgElement.src = reader.result;
                    imgElement.style.display = "block";
                };
                reader.readAsDataURL(file);
            }

            // Click-to-fullscreen feature
            document.querySelectorAll(".img-preview").forEach(img => {
                img.addEventListener("click", () => {
                    const modalImage = document.getElementById("modalImage");
                    const downloadLink = document.getElementById("downloadImage");

                    modalImage.src = img.src;
                    downloadLink.href = img.src; // Set download link to same image
                    downloadLink.download = img.src.split('/').pop(); // Set filename

                    new bootstrap.Modal(document.getElementById("imageModal")).show();
                });
            });

            $(document).ready(function() {
                $("#addTruck").click(function(e) {
                    e.preventDefault();

                    // Simple required field check
                    let unit_name = $("input[name='unit_name']").val();
                    let std = $("input[name='std']").val();

                    if (unit_name === "" || std === "") {
                        Swal.fire({
                            text: 'Please fill in all required fields',
                            icon: 'info'
                        });
                        return;
                    }

                    Swal.fire({
                        title: 'Confirm Add Truck',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Confirm'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            let formData = new FormData($('#truckForm')[0]);
                            $.ajax({
                                url: 'php/crud/add/addtrucks-page.php',
                                type: 'POST',
                                data: formData,
                                contentType: false,
                                cache: false,
                                processData: false,
                                success: function(data) {
                                    Swal.fire({
                                        text: data,
                                        icon: 'success',
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                    setTimeout(() => {
                                        window.location.href = "dispatcher-index.php?route=truck";
                                    }, 1600);
                                },
                                error: function(xhr, status, error) {
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
