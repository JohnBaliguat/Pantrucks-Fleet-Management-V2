<?php
session_start();
$pageTitle = 'Not Found';
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Gate-Guard') {
    include 'gate/_layout_top.php';
    echo '<div class="row"><div class="col-12"><div class="card"><div class="card-body text-center py-5">';
    echo '<h2>404 — Not Found</h2><p class="mb-3">That route doesn\'t exist on the gate module.</p>';
    echo '<a href="gate-dashboard" class="btn btn-primary">Back to Dashboard</a>';
    echo '</div></div></div></div>';
    include 'gate/_layout_bottom.php';
} else {
    header("Location: gate-index.php?route=login");
    exit();
}
