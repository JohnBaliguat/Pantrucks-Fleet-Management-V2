<?php
session_start();

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Admin") {


?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Truck</title>
        <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
<link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="datatable/datatables.min.css">
        <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
        <style>
            .bd-mode-toggle {
                z-index: 1500;
            }

            .bd-mode-toggle .dropdown-menu .active .bi {
                display: block !important;
            }

            .table-header {
                background: #343a40;
            }

            .needs-validation input {
                border: .01px solid #6c757d;
            }

            .drop-zone {
                max-width: 100%;
                height: 50px;
                padding: 15px;
                display: flex;
                align-items: center;
                justify-content: center;
                text-align: center;
                cursor: pointer;
                color: #999;
                border: 2px dashed #ccc;
                border-radius: 8px;
                background: #f8f9fa;
            }

            .drop-zone--over {
                border-color: #0d6efd;
                background-color: #e9f3ff;
            }

            .drop-zone__input {
                display: none;
            }
        </style>
    </head>

    <body>
        <!--  Body Wrapper -->
        <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
            data-sidebar-position="fixed" data-header-position="fixed">

            <!--  App Topstrip -->
            <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center justify-content-center gap-5 mb-2 mb-lg-0">
                    <a class="d-flex justify-content-center" href="#">
                        <img src="assets/images/logos/pantrucks.png" alt="" width="122">
                    </a>
                </div>

                <div class="d-lg-flex align-items-center gap-2">
                    <h3 class="text-white mb-2 mb-lg-0 fs-5 text-center">Pantrucks Fleet Management System</h3>
                    <div class="d-flex align-items-center justify-content-center gap-2">
                    </div>
                </div>

            </div>
            <!-- Sidebar Start -->
            <?php include 'sidebar.php'; ?>
            <!--  Sidebar End -->
            <!--  Main wrapper -->
            <div class="body-wrapper">
                <!--  Header Start -->
                <?php include 'navbar.php';

                ?>
                <!--  Header End -->
                <div class="body-wrapper-inner">
                    <div class="container-fluid">
                        <div class="d-flex align-items-center mt-3 mb-1">
                            <a href="truck" class="btn btn-outline-secondary btn-sm"><i class="ti ti-arrow-left"></i> Back to Trucks</a>
                        </div>
                        <form action="php/crud/add/addtrucks-page.php" method="post" id="truckForm" enctype="multipart/form-data">
                            <div class="row">

                                <!-- LEFT SIDE: Truck Information -->
                                <div class="col-md-6">
                                    <div class="my-3 p-3 bg-body rounded shadow-sm">
                                        <h5 class="border-bottom pb-2 mb-3">Truck Information</h5>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Unit Name</label>
                                                    <input type="text" class="form-control" name="unit_name" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">STD</label>
                                                    <input type="text" class="form-control" name="std" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Plate Number</label>
                                                    <input type="text" class="form-control" name="plate_number" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">OR</label>
                                                    <input type="text" class="form-control" name="OR" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">CR</label>
                                                    <input type="text" class="form-control" name="CR" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Brand</label>
                                                    <input type="text" class="form-control" name="brand">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Model</label>
                                                    <input type="text" class="form-control" name="model">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Year</label>
                                                    <input type="number" class="form-control" name="year" min="1900" max="2099">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Engine Number</label>
                                                    <input type="text" class="form-control" name="engine_number">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Chassis Number</label>
                                                    <input type="text" class="form-control" name="chassis_number">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Fuel Type</label>
                                                    <select class="form-select" name="fuel_type">
                                                        <option value="">Select...</option>
                                                        <option>Diesel</option>
                                                        <option>Gasoline</option>
                                                        <option>Electric</option>
                                                        <option>Hybrid</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Capacity (tons)</label>
                                                    <input type="number" class="form-control" name="capacity" step="0.1">
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label class="form-label">Remarks</label>
                                                    <textarea class="form-control" name="remarks" rows="3"></textarea>
                                                </div>
                                            </div>

                                        </div>
                                        <button type="button" class="btn btn-primary" style="width: 100%;" id="addTruck">Save Truck Info</button>
                                    </div>
                                </div>

                                <!-- RIGHT SIDE: Profile Images -->
                                <div class="col-md-6">
                                    <div class="my-3 p-3 bg-body rounded shadow-sm">
                                        <h5 class="border-bottom pb-2 mb-3">Truck Profile Images</h5>

                                        <!-- Row 1: Front & Back -->
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <!-- Front View -->
                                                <div class="mb-4">
                                                    <label class="form-label fw-bold">Front View</label>
                                                    <div class="drop-zone">
                                                        <span class="drop-zone__prompt">Drag & drop or click to upload</span>
                                                        <input type="file" class="drop-zone__input" accept="image/*" id="front-view" name="front_view">
                                                    </div>
                                                    <div class="mt-2 text-center">
                                                        <img class="img-preview img-fluid rounded border" src="assets/images/backgrounds/1.png" style="height:200px; width:100%; cursor:pointer;">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <!-- Back View -->
                                                <div class="mb-4">
                                                    <label class="form-label fw-bold">Back View</label>
                                                    <div class="drop-zone">
                                                        <span class="drop-zone__prompt">Drag & drop or click to upload</span>
                                                        <input type="file" class="drop-zone__input" accept="image/*" id="back-view" name="back_view">
                                                    </div>
                                                    <div class="mt-2 text-center">
                                                        <img class="img-preview img-fluid rounded border" src="assets/images/backgrounds/2.png" style="height:200px; width:100%; cursor:pointer;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Row 2: Left & Right -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <!-- Left Side View -->
                                                <div class="mb-4">
                                                    <label class="form-label fw-bold">Left Side View</label>
                                                    <div class="drop-zone">
                                                        <span class="drop-zone__prompt">Drag & drop or click to upload</span>
                                                        <input type="file" class="drop-zone__input" accept="image/*" id="left-view" name="left_view">
                                                    </div>
                                                    <div class="mt-2 text-center">
                                                        <img class="img-preview img-fluid rounded border" src="assets/images/backgrounds/3.png" style="height:200px; width:100%; cursor:pointer;">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <!-- Right Side View -->
                                                <div class="mb-4">
                                                    <label class="form-label fw-bold">Right Side View</label>
                                                    <div class="drop-zone">
                                                        <span class="drop-zone__prompt">Drag & drop or click to upload</span>
                                                        <input type="file" class="drop-zone__input" accept="image/*" id="right-view" name="right_view">
                                                    </div>
                                                    <div class="mt-2 text-center">
                                                        <img class="img-preview img-fluid rounded border" src="assets/images/backgrounds/4.png" style="height:200px; width:100%; cursor:pointer;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fullscreen Modal -->
                                <div id="imageModal" class="modal fade" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content bg-dark">
                                            <div class="modal-body p-0 text-center position-relative">
                                                <!-- Close Button -->
                                                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-2" data-bs-dismiss="modal"></button>

                                                <!-- Download Button -->
                                                <a id="downloadImage" href="#" download class="btn btn-outline-secondary position-absolute top-0 start-0 m-2">
                                                    Download
                                                </a>

                                                <!-- Image -->
                                                <img id="modalImage" class="img-fluid" src="">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <div class="py-6 px-6 text-center">
                            <p class="mb-0 fs-4">Design and Developed by JA Baliguat | 2025</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="assets/libs/jquery/dist/jquery.min.js"></script>
        <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
        <script src="assets/js/sidebarmenu.js"></script>
        <script src="assets/js/app.min.js"></script>
        <script src="assets/libs/apexcharts/dist/apexcharts.min.js"></script>
        <script src="assets/libs/simplebar/dist/simplebar.js"></script>
        <script src="assets/js/dashboard.js"></script>
        <script src="alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
        <script src="datatable/datatables.min.js"></script>
        <script src="js/addunit-page.js"></script>
        <!-- solar icons -->
        <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
    </body>

    </html>
<?php
} else {
    header("Location: index.php?route=login");
    exit();
}
