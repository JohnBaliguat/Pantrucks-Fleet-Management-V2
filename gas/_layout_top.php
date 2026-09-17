<?php
// Shared layout opener for the Gastender (fuel ticketing) pages.
// Caller must have already called session_start() and verified user_type.
$pageTitle = $pageTitle ?? 'Fuel Ticketing';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/LogoFleet.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <link rel="stylesheet" href="assets/css/enhancements.css" />
  <link rel="stylesheet" href="datatable/datatables.min.css">
  <link rel="stylesheet" href="alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
  <style>
    /* Ticket print + form styles (ported from PTSI Fuel System) */
    @media print {
      @page { size: A4 portrait; margin: 8mm; }
      .no-print, header, aside.left-sidebar, .app-topstrip { display: none !important; }
      .containerTicket { display: flex; justify-content: center; align-items: center;
                         -webkit-print-color-adjust: exact; print-color-adjust: exact;
                         font-size: 10px; padding: 0; margin: 0; max-width: 100%; background: white; }
      .receipt { border: 2px dashed black; padding: 5px; page-break-inside: avoid;
                 width: 48%; display: inline-block; vertical-align: top; }
      .ticket { border: 2px solid black; padding: 5px; }
      .containerTicket .form-control { height: 20px; border: 1px solid black; font-size: 10px;
                                        padding: 2px; border-radius: 0; font-weight: 900; }
    }
    .receipt { border: 2px dashed black; padding: 10px; page-break-inside: avoid; }
    .ticket  { border: 2px solid black;  padding: 10px; }
    .containerTicket .form-control { height: 24px; border: 1px solid black; font-size: 12px;
                                      padding: 2px; border-radius: 0; font-weight: 900; }
    .divider { border-bottom: 1px solid black; margin: 5px 0; }
    .dropdown-menu { max-height: 300px; overflow-y: auto; }
    .dropdown-item { cursor: pointer; }
    .form-control:focus { box-shadow: none; border-color: #6c757d; }
    .text-danger { color: red !important; }

    /* --- Gastender layout spacing overrides --- */
    /* The default theme adds 100px top padding for the fixed header; the
       Gastender module also shows the dark .app-topstrip strip above the
       navbar, so combined they leave a huge empty band. Trim it. */
    .body-wrapper > .container-fluid,
    #main-wrapper[data-layout=vertical][data-header-position=fixed] .body-wrapper > .container-fluid {
      padding-top: 16px !important;
      padding-bottom: 16px !important;
    }
    /* Make sure the page heading sits flush under the navbar */
    .gas-page > main,
    .gas-page > main:first-child {
      margin-top: 0;
    }
    /* Reduce extra top margin from .mt-2/.mt-5 helpers on our pages */
    .gas-page .row.mt-2 { margin-top: 0 !important; }
    .gas-page .row.mt-5 { margin-top: .5rem !important; }
    .gas-page .my-3 { margin-top: .5rem !important; margin-bottom: .75rem !important; }

    /* Keep DataTables wrappers from forcing horizontal scroll. The Responsive
       plugin handles narrow viewports by collapsing low-priority columns. */
    .gas-page .dataTables_wrapper,
    .gas-page .dataTables_wrapper .dataTables_scroll,
    .gas-page .dataTables_wrapper .dataTables_scrollBody {
      overflow-x: hidden !important;
    }
    .gas-page table.dataTable { width: 100% !important; }
    /* Tighter datatables toolbar */
    .gas-page .dataTables_wrapper .row { margin-left: 0; margin-right: 0; }
  </style>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
       data-sidebar-position="fixed" data-header-position="fixed">
    <div class="app-topstrip bg-dark py-6 px-3 w-100 d-lg-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center justify-content-center gap-5 mb-2 mb-lg-0">
        <a class="d-flex justify-content-center" href="#">
          <img src="assets/images/logos/pantrucks.png" alt="" width="122">
        </a>
      </div>
      <div class="d-lg-flex align-items-center gap-2">
        <h3 class="text-white mb-2 mb-lg-0 fs-5 text-center">Pantrucks Fleet Management System &mdash; Fuel Ticketing</h3>
      </div>
    </div>
    <?php include 'gas/sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include 'gas/navbar.php'; ?>
      <div class="body-wrapper-inner gas-page">
        <div class="container-fluid">
