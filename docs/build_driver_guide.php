<?php
/**
 * Build the Pantrucks Driver User Guide PDF.
 *
 *   php docs/build_driver_guide.php
 *
 * Output: docs/Pantrucks-Driver-Guide.pdf
 *
 * Pages are drawn with TCPDF's vector primitives — every "screen" you see
 * in the guide is a wireframe mockup rendered in code so the PDF stays
 * crisp at any zoom and the document doesn't depend on captured screenshots.
 */

require_once __DIR__ . '/../reports/TCPDF-main/tcpdf.php';

// ---------------- palette --------------------------------------------------
$BLUE        = [37, 99, 235];     // primary
$BLUE_DARK   = [29, 78, 216];
$GREEN       = [22, 163, 74];
$AMBER       = [245, 158, 11];
$RED         = [220, 38, 38];
$INDIGO      = [79, 70, 229];
$INK         = [30, 41, 59];      // body text
$INK_MUTED   = [100, 116, 139];
$BG_LIGHT    = [241, 245, 249];
$BG_CARD     = [255, 255, 255];
$BORDER      = [203, 213, 225];
$BORDER_SOFT = [226, 232, 240];

// ---------------- TCPDF setup ---------------------------------------------
class DriverGuidePDF extends TCPDF {
    public $sectionTitle = '';
    public function Header() {
        if ($this->PageNo() <= 1) return; // No header on cover
        $this->SetY(8);
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 6, 'Pantrucks Driver User Guide', 0, 0, 'L');
        $this->Cell(0, 6, $this->sectionTitle, 0, 1, 'R');
        $this->SetDrawColor(226, 232, 240);
        $this->Line(15, 16, $this->getPageWidth() - 15, 16);
    }
    public function Footer() {
        if ($this->PageNo() <= 1) return;
        $this->SetY(-12);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 6, 'Page ' . $this->PageNo() . ' of ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new DriverGuidePDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Pantrucks Fleet Management');
$pdf->SetAuthor('Pantrucks');
$pdf->SetTitle('Pantrucks Driver User Guide');
$pdf->SetSubject('Driver mobile app — how to use it');
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 22, 15);
$pdf->SetAutoPageBreak(true, 20);

// ---------------- helpers --------------------------------------------------
function set_fill($pdf, $rgb)   { $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]); }
function set_draw($pdf, $rgb)   { $pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]); }
function set_text($pdf, $rgb)   { $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]); }

function h1($pdf, $text) {
    global $INK, $BLUE;
    $pdf->SetFont('helvetica', 'B', 22);
    set_text($pdf, $BLUE);
    $pdf->Cell(0, 12, $text, 0, 1, 'L');
    $pdf->Ln(1);
    set_text($pdf, $INK);
}
function h2($pdf, $text) {
    global $INK;
    $pdf->SetFont('helvetica', 'B', 14);
    set_text($pdf, $INK);
    $pdf->Cell(0, 8, $text, 0, 1, 'L');
    $pdf->Ln(1);
}
function h3($pdf, $text) {
    global $INK;
    $pdf->SetFont('helvetica', 'B', 11);
    set_text($pdf, $INK);
    $pdf->Cell(0, 6, $text, 0, 1, 'L');
    $pdf->Ln(0.5);
}
function body($pdf, $text) {
    global $INK;
    $pdf->SetFont('helvetica', '', 10);
    set_text($pdf, $INK);
    $pdf->MultiCell(0, 5, $text, 0, 'L');
    $pdf->Ln(1);
}
function muted($pdf, $text) {
    global $INK_MUTED;
    $pdf->SetFont('helvetica', 'I', 9);
    set_text($pdf, $INK_MUTED);
    $pdf->MultiCell(0, 4.5, $text, 0, 'L');
    $pdf->Ln(0.5);
}
function step($pdf, $num, $title, $body) {
    global $BLUE, $INK, $INK_MUTED;
    $startX = $pdf->GetX();
    $startY = $pdf->GetY();
    // Number bubble
    set_fill($pdf, $BLUE);
    set_draw($pdf, $BLUE);
    $pdf->Circle($startX + 4, $startY + 4, 3.5, 0, 360, 'F');
    set_text($pdf, [255, 255, 255]);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetXY($startX, $startY + 1);
    $pdf->Cell(8, 6, (string)$num, 0, 0, 'C');
    // Title
    set_text($pdf, $INK);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY($startX + 9, $startY);
    $pdf->MultiCell(0, 5, $title, 0, 'L');
    // Body
    set_text($pdf, $INK_MUTED);
    $pdf->SetFont('helvetica', '', 9.5);
    $pdf->SetX($startX + 9);
    $pdf->MultiCell(0, 4.5, $body, 0, 'L');
    set_text($pdf, $INK);
    $pdf->Ln(1.5);
}

function callout($pdf, $tone, $title, $text) {
    global $BLUE, $AMBER, $GREEN, $RED, $INK, $BORDER_SOFT;
    $colors = [
        'tip'  => [$BLUE,  [239, 246, 255]],
        'warn' => [$AMBER, [255, 247, 237]],
        'ok'   => [$GREEN, [240, 253, 244]],
        'err'  => [$RED,   [254, 242, 242]],
    ];
    $c = $colors[$tone] ?? $colors['tip'];
    $x = $pdf->GetX(); $y = $pdf->GetY();
    $w = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
    // Left border strip
    set_fill($pdf, $c[0]);
    $pdf->Rect($x, $y, 1.2, 16, 'F');
    // Background
    set_fill($pdf, $c[1]);
    set_draw($pdf, $BORDER_SOFT);
    $pdf->Rect($x + 1.2, $y, $w - 1.2, 16, 'DF');
    // Title
    $pdf->SetXY($x + 4, $y + 2);
    set_text($pdf, $c[0]);
    $pdf->SetFont('helvetica', 'B', 9.5);
    $pdf->Cell(0, 4, strtoupper($title), 0, 1, 'L');
    // Text
    $pdf->SetX($x + 4);
    set_text($pdf, $INK);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell($w - 6, 4, $text, 0, 'L');
    $pdf->SetY($y + 17);
}

// ----- Phone mockup helpers -----
function phone_frame($pdf, $x, $y, $w = 70) {
    global $INK, $BG_LIGHT;
    $h = $w * 2.05; // typical phone aspect
    set_draw($pdf, $INK);
    $pdf->SetLineWidth(0.4);
    $pdf->RoundedRect($x, $y, $w, $h, 4, '1111', 'D');
    // Notch
    set_fill($pdf, $INK);
    $pdf->RoundedRect($x + $w/2 - 8, $y + 1, 16, 2, 1, '1111', 'F');
    return [$x + 2, $y + 6, $w - 4, $h - 12]; // inner rect
}
function status_bar($pdf, $ix, $iy, $iw) {
    global $INK_MUTED, $BG_LIGHT;
    set_fill($pdf, $BG_LIGHT);
    $pdf->Rect($ix, $iy, $iw, 4, 'F');
    set_text($pdf, $INK_MUTED);
    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->SetXY($ix + 1, $iy);
    $pdf->Cell($iw/2, 4, '9:41', 0, 0, 'L');
    $pdf->SetXY($ix + $iw/2, $iy);
    $pdf->Cell($iw/2 - 1, 4, '4G  100%', 0, 0, 'R');
}
function app_bar($pdf, $ix, $iy, $iw, $title) {
    global $BLUE;
    set_fill($pdf, $BLUE);
    $pdf->Rect($ix, $iy, $iw, 8, 'F');
    set_text($pdf, [255, 255, 255]);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY($ix, $iy + 1.5);
    $pdf->Cell($iw, 5, $title, 0, 0, 'C');
}
function nav_bar($pdf, $ix, $iy, $iw, $activeIdx = 0) {
    global $BG_CARD, $BLUE, $INK_MUTED, $BORDER_SOFT, $RED;
    set_fill($pdf, $BG_CARD);
    set_draw($pdf, $BORDER_SOFT);
    $pdf->Rect($ix, $iy, $iw, 9, 'DF');
    $items = ['Home', 'Unit', 'Chat', 'Trips', 'SOS'];
    $cellW = $iw / 5;
    $pdf->SetFont('helvetica', '', 5.5);
    for ($i = 0; $i < 5; $i++) {
        $isSOS = ($i === 4);
        $isActive = ($i === $activeIdx);
        set_text($pdf, $isSOS ? $RED : ($isActive ? $BLUE : $INK_MUTED));
        // Dot/icon proxy
        if ($isSOS) {
            set_fill($pdf, $RED);
            $pdf->Circle($ix + $cellW*$i + $cellW/2, $iy + 3, 1.5, 0, 360, 'F');
        } else {
            set_fill($pdf, $isActive ? $BLUE : $INK_MUTED);
            $pdf->Rect($ix + $cellW*$i + $cellW/2 - 1.5, $iy + 1.5, 3, 3, 'F');
        }
        set_text($pdf, $isSOS ? $RED : ($isActive ? $BLUE : $INK_MUTED));
        $pdf->SetXY($ix + $cellW*$i, $iy + 5);
        $pdf->Cell($cellW, 3, $items[$i], 0, 0, 'C');
    }
}
function card($pdf, $x, $y, $w, $h, $title = null, $body = null) {
    global $BG_CARD, $BORDER_SOFT, $INK, $INK_MUTED;
    set_fill($pdf, $BG_CARD);
    set_draw($pdf, $BORDER_SOFT);
    $pdf->RoundedRect($x, $y, $w, $h, 1.5, '1111', 'DF');
    if ($title !== null) {
        set_text($pdf, $INK);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetXY($x + 2, $y + 1);
        $pdf->Cell($w - 4, 3, $title, 0, 1, 'L');
    }
    if ($body !== null) {
        set_text($pdf, $INK_MUTED);
        $pdf->SetFont('helvetica', '', 6);
        $pdf->SetXY($x + 2, $y + 4);
        $pdf->MultiCell($w - 4, 2.5, $body, 0, 'L');
    }
}
function pill($pdf, $x, $y, $w, $h, $text, $bgRgb, $textRgb = [255, 255, 255], $fontPt = 6) {
    set_fill($pdf, $bgRgb);
    $pdf->RoundedRect($x, $y, $w, $h, $h/2, '1111', 'F');
    set_text($pdf, $textRgb);
    $pdf->SetFont('helvetica', 'B', $fontPt);
    $pdf->SetXY($x, $y + ($h - $fontPt*0.5)/2 + 0.2);
    $pdf->Cell($w, $fontPt*0.5, $text, 0, 0, 'C');
}
function button($pdf, $x, $y, $w, $h, $text, $bgRgb, $textRgb = [255, 255, 255]) {
    set_fill($pdf, $bgRgb);
    set_draw($pdf, $bgRgb);
    $pdf->RoundedRect($x, $y, $w, $h, 1.5, '1111', 'F');
    set_text($pdf, $textRgb);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetXY($x, $y + ($h - 4)/2);
    $pdf->Cell($w, 4, $text, 0, 0, 'C');
}
function input_box($pdf, $x, $y, $w, $h, $placeholder = '') {
    global $BORDER, $INK_MUTED;
    set_fill($pdf, [255, 255, 255]);
    set_draw($pdf, $BORDER);
    $pdf->RoundedRect($x, $y, $w, $h, 1, '1111', 'DF');
    if ($placeholder) {
        set_text($pdf, $INK_MUTED);
        $pdf->SetFont('helvetica', '', 6);
        $pdf->SetXY($x + 1.5, $y + ($h - 3)/2);
        $pdf->Cell($w - 3, 3, $placeholder, 0, 0, 'L');
    }
}

// ==========================================================================
// COVER PAGE
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = '';

// Big blue header band
set_fill($pdf, $BLUE);
$pdf->Rect(0, 0, $pdf->getPageWidth(), 90, 'F');

// Logo — drawn as a styled badge (no GD dependency)
set_fill($pdf, [255, 255, 255]);
$pdf->RoundedRect(15, 18, 45, 16, 2, '1111', 'F');
set_text($pdf, $BLUE);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetXY(15, 21);
$pdf->Cell(45, 10, 'PANTRUCKS', 0, 0, 'C');

// Title
set_text($pdf, [255, 255, 255]);
$pdf->SetFont('helvetica', 'B', 28);
$pdf->SetXY(15, 55);
$pdf->Cell(0, 12, 'Driver User Guide', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 13);
$pdf->SetXY(15, 70);
$pdf->Cell(0, 6, 'Pantrucks Fleet Management System', 0, 1, 'L');

// Subtitle
set_text($pdf, $INK);
$pdf->SetFont('helvetica', '', 11);
$pdf->SetXY(15, 105);
$pdf->MultiCell(0, 6,
    "This guide walks you through everything you do as a driver in the Pantrucks app — from logging in and starting a shift to capturing pickup photos, marking trip progress, submitting proof of delivery, and reporting issues on the road.",
    0, 'L'
);

// Highlight cards
$y = 135;
$cardW = 56;
$cardH = 26;
$gap = 4;
$startX = 15;
$cards = [
    ['Daily Workflow',   "Accept jobs, log trip\nstatus, capture POD"],
    ['Photo Capture',    "Pickup, jack-up,\ngateless completion"],
    ['Offline Support',  "Work without signal —\nauto-syncs later"],
];
foreach ($cards as $i => $c) {
    $x = $startX + $i * ($cardW + $gap);
    set_fill($pdf, $BG_LIGHT);
    set_draw($pdf, $BORDER_SOFT);
    $pdf->RoundedRect($x, $y, $cardW, $cardH, 2, '1111', 'DF');
    set_text($pdf, $BLUE);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetXY($x + 3, $y + 3);
    $pdf->Cell($cardW - 6, 5, $c[0], 0, 1, 'L');
    set_text($pdf, $INK);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY($x + 3, $y + 10);
    $pdf->MultiCell($cardW - 6, 4, $c[1], 0, 'L');
}

// Footer band
set_fill($pdf, $BG_LIGHT);
$pdf->Rect(0, $pdf->getPageHeight() - 40, $pdf->getPageWidth(), 40, 'F');
set_text($pdf, $INK_MUTED);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(15, $pdf->getPageHeight() - 30);
$pdf->Cell(0, 5, 'Version 1.0  |  ' . date('F Y'), 0, 1, 'L');
$pdf->SetX(15);
$pdf->Cell(0, 5, 'Designed for use on Android & iOS mobile browsers.', 0, 1, 'L');

// ==========================================================================
// TABLE OF CONTENTS
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = 'Contents';
h1($pdf, 'Table of Contents');
muted($pdf, 'A walkthrough of every screen you will use on the road.');
$pdf->Ln(3);

$toc = [
    ['1',  'Logging in to the Driver App',              3],
    ['2',  'Dashboard at a Glance',                     4],
    ['3',  'Starting Your Shift (Pre-Departure)',       5],
    ['4',  'Accepting or Declining a Dispatch',         6],
    ['5',  'Marking Pickup (Container Number + Photo)', 7],
    ['6',  'Trip Status — On the Way, Arrived, Delivered', 8],
    ['7',  'Proof of Delivery (POD)',                   9],
    ['8',  'Trailer Jack-up at Site',                  10],
    ['9',  'Gateless Completion',                      11],
    ['10', 'Reporting a Breakdown or Sending SOS',     12],
    ['11', 'Offline Mode and Auto-Sync',                13],
    ['12', 'Editing Your Profile',                      14],
    ['13', 'Tips & Troubleshooting',                    15],
];
$pdf->SetFont('helvetica', '', 11);
foreach ($toc as $row) {
    set_text($pdf, $BLUE);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(8, 7, $row[0] . '.', 0, 0, 'L');
    set_text($pdf, $INK);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 7, $row[1] . '  ' . str_repeat('.', max(1, 80 - strlen($row[1]))) . '  ' . $row[2], 0, 1, 'L');
}

// ==========================================================================
// SECTION 1 — LOGGING IN
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = '1. Login';
h1($pdf, '1. Logging In');
body($pdf, "Open your phone browser and go to the Pantrucks app URL provided by your dispatcher (for example, https://app.pantrucks.com or your company's specific address). Bookmark it to your home screen for quick access.");

// Two-column layout: mockup + steps
$mockX = 130; $mockY = $pdf->GetY();
$inner = phone_frame($pdf, $mockX, $mockY, 60);
list($ix, $iy, $iw, $ih) = $inner;
status_bar($pdf, $ix, $iy, $iw);
app_bar($pdf, $ix, $iy + 4, $iw, 'Pantrucks');
// Login form mockup
set_fill($pdf, [255, 255, 255]);
$pdf->Rect($ix, $iy + 12, $iw, $ih - 12, 'F');
set_text($pdf, $INK);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetXY($ix + 4, $iy + 18);
$pdf->Cell($iw - 8, 5, 'Sign in', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 6);
set_text($pdf, $INK_MUTED);
$pdf->SetXY($ix + 4, $iy + 24);
$pdf->Cell($iw - 8, 3, 'Username', 0, 1, 'L');
input_box($pdf, $ix + 4, $iy + 27, $iw - 8, 6, 'driver_001');
$pdf->SetXY($ix + 4, $iy + 35);
$pdf->Cell($iw - 8, 3, 'Password', 0, 1, 'L');
input_box($pdf, $ix + 4, $iy + 38, $iw - 8, 6, '• • • • • • • •');
button($pdf, $ix + 4, $iy + 48, $iw - 8, 7, 'Sign in', $BLUE);

// Steps on the left
$pdf->SetXY(15, $mockY);
step($pdf, 1, 'Open the app URL', "Enter the link in your phone's browser (Chrome on Android, Safari on iOS). The first time, tap your browser menu and choose Add to Home Screen so the app opens like a native app.");
step($pdf, 2, 'Enter your driver credentials', "Use the username and password your dispatcher assigned to you. Keep them private.");
step($pdf, 3, 'Tap Sign In', "Once you sign in, you stay logged in until you explicitly sign out or your session expires. Most drivers stay signed in for a whole shift.");

$pdf->Ln(4);
callout($pdf, 'tip', 'Allow GPS and Camera',
    "On first launch the browser asks for permission to use your phone's GPS and camera. Tap ALLOW for both — pickup photos, jack-up photos, and trip status updates all rely on these."
);

// ==========================================================================
// SECTION 2 — DASHBOARD
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = '2. Dashboard';
h1($pdf, '2. Dashboard at a Glance');
body($pdf, "After signing in you land on the dashboard. Everything you need during a shift is reachable from this one screen.");

$mockX = 120; $mockY = $pdf->GetY();
$inner = phone_frame($pdf, $mockX, $mockY, 70);
list($ix, $iy, $iw, $ih) = $inner;
status_bar($pdf, $ix, $iy, $iw);
app_bar($pdf, $ix, $iy + 4, $iw, 'Dashboard');
// Shift banner
card($pdf, $ix + 2, $iy + 14, $iw - 4, 10, 'Shift active — truck PM651');
// Stats grid
card($pdf, $ix + 2, $iy + 26, ($iw - 6)/2, 14, 'Active', '2');
card($pdf, $ix + 4 + ($iw - 6)/2, $iy + 26, ($iw - 6)/2, 14, 'Completed', '17');
// Earnings tile
card($pdf, $ix + 2, $iy + 42, $iw - 4, 14, 'Earnings  Php 4,250.00', "This cutoff");
// Bookings header
set_text($pdf, $INK);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetXY($ix + 2, $iy + 58);
$pdf->Cell(20, 4, 'My Bookings', 0, 0, 'L');
pill($pdf, $ix + $iw - 18, $iy + 58, 16, 4, '2 Active', $BLUE);
// Booking card
card($pdf, $ix + 2, $iy + 64, $iw - 4, 18, 'BK-20260520-001  ACME Corp', "From: Port  -  To: Warehouse 3\nStatus: En route");
// Bottom nav
nav_bar($pdf, $ix, $iy + $ih - 9, $iw, 0);

$pdf->SetXY(15, $mockY);
$pdf->SetFont('helvetica', '', 10);
set_text($pdf, $INK);
$pdf->MultiCell(95, 5,
    "Key things to look for:", 0, 'L');
$pdf->Ln(1);
step($pdf, 1, 'Shift banner (top)',     "Green = shift active. Yellow = no active shift; tap Start Shift to run the pre-departure checklist.");
step($pdf, 2, 'Active / Completed cards', "Live counts of dispatches in progress and finished today. They update automatically.");
step($pdf, 3, 'Earnings tile',          "Tap to see a breakdown by payroll cutoff.");
step($pdf, 4, 'My Bookings list',       "Every dispatch assigned to you. Tap a card to expand and see the trip status pills, documents, and action buttons.");
step($pdf, 5, 'Bottom nav',             "Home / Unit / Chat / Trip Report / SOS — always one tap away.");

// ==========================================================================
// SECTION 3 — START SHIFT
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = '3. Start Shift';
h1($pdf, '3. Starting Your Shift (Pre-Departure)');
body($pdf, "Before you can be assigned a job, you must complete the pre-departure checklist. This confirms the truck is roadworthy and starts machine-hours billing.");

$mockX = 130; $mockY = $pdf->GetY();
$inner = phone_frame($pdf, $mockX, $mockY, 60);
list($ix, $iy, $iw, $ih) = $inner;
status_bar($pdf, $ix, $iy, $iw);
app_bar($pdf, $ix, $iy + 4, $iw, 'Pre-Departure');
// Truck dropdown
set_text($pdf, $INK);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetXY($ix + 3, $iy + 16);
$pdf->Cell($iw - 6, 4, 'Truck for this shift', 0, 1, 'L');
input_box($pdf, $ix + 3, $iy + 21, $iw - 6, 6, 'PM651');
// Checklist items
$items = ['Fuel topped up', 'Tyres inflated', 'Lights working', 'Cargo area clean', 'Genset functional'];
foreach ($items as $i => $itm) {
    $yi = $iy + 30 + $i * 7;
    set_fill($pdf, [255, 255, 255]);
    set_draw($pdf, $BORDER);
    $pdf->RoundedRect($ix + 3, $yi, $iw - 6, 6, 1, '1111', 'DF');
    set_fill($pdf, $GREEN);
    $pdf->Circle($ix + 5, $yi + 3, 1.2, 0, 360, 'F');
    set_text($pdf, $INK);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetXY($ix + 8, $yi + 1);
    $pdf->Cell($iw - 12, 4, $itm, 0, 0, 'L');
}
button($pdf, $ix + 3, $iy + 70, $iw - 6, 7, 'Submit and Go Available', $BLUE);

$pdf->SetXY(15, $mockY);
step($pdf, 1, 'Pick your truck', "Tap the truck box and choose the unit you're driving today. Only trucks that are good condition and not under maintenance appear in the list.");
step($pdf, 2, 'Tap each checklist item', "Each item turns green when confirmed. You cannot submit until all five are confirmed.");
step($pdf, 3, 'Add remarks (optional)', "If something is borderline (e.g. a tyre that should be looked at next week), note it here for the workshop.");
step($pdf, 4, 'Submit and Go Available', "Your status flips to Available. Dispatchers can now assign you a trip.");
$pdf->Ln(2);
callout($pdf, 'warn', 'Truck not in list?', "It may be under maintenance or not registered in the unit table. Talk to dispatch — they need to clear the maintenance flag before you can use it.");

// ==========================================================================
// SECTION 4 — ACCEPT / DECLINE
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = '4. Accept Dispatch';
h1($pdf, '4. Accepting or Declining a Dispatch');
body($pdf, "When a dispatcher assigns you a job, the booking card on your dashboard shows two buttons: Accept and Decline. You'll also get a push notification if notifications are enabled.");

$mockX = 130; $mockY = $pdf->GetY();
$inner = phone_frame($pdf, $mockX, $mockY, 60);
list($ix, $iy, $iw, $ih) = $inner;
status_bar($pdf, $ix, $iy, $iw);
app_bar($pdf, $ix, $iy + 4, $iw, 'New Dispatch');
// Booking card
card($pdf, $ix + 3, $iy + 16, $iw - 6, 40, 'BK-20260520-002  ACME Corp', "Awaiting your accept\nFrom: Port\nTo: Warehouse 3\nTruck: PM651  Trailer: TR007");
// Accept / Decline
button($pdf, $ix + 3, $iy + 58, ($iw - 9)/2, 7, 'Accept', $GREEN);
button($pdf, $ix + 6 + ($iw - 9)/2, $iy + 58, ($iw - 9)/2, 7, 'Decline', $RED);

$pdf->SetXY(15, $mockY);
step($pdf, 1, 'Read the dispatch details', "Tap the booking card to expand it. Check the customer, the from/to locations, and the truck/trailer assigned.");
step($pdf, 2, 'Tap Accept', "The card flips to Accepted. The four-step trip status pills (Picked up → On the way → Arrived → Delivered) appear, and the dispatch is locked to you.");
step($pdf, 3, 'Or tap Decline', "A small dialog asks for a reason (optional but encouraged). Dispatch is notified and the job is reassigned to another driver.");
$pdf->Ln(2);
callout($pdf, 'tip', 'You can accept multiple dispatches', "If dispatch has queued several jobs for you, you can accept and work them in order. The Active count on your dashboard reflects how many are in progress.");

// ==========================================================================
// SECTION 5 — PICKUP
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = '5. Pickup';
h1($pdf, '5. Marking Pickup');
body($pdf, "When you have the container loaded and are about to leave the origin, tap Picked up. A confirmation modal pops up asking for the container number and a photo.");

$mockX = 130; $mockY = $pdf->GetY();
$inner = phone_frame($pdf, $mockX, $mockY, 60);
list($ix, $iy, $iw, $ih) = $inner;
status_bar($pdf, $ix, $iy, $iw);
app_bar($pdf, $ix, $iy + 4, $iw, 'Pickup Confirmation');
// Modal background
set_fill($pdf, [255, 255, 255]);
$pdf->RoundedRect($ix + 3, $iy + 16, $iw - 6, 80, 2, '1111', 'F');
// Container no
set_text($pdf, $INK);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetXY($ix + 5, $iy + 20);
$pdf->Cell($iw - 10, 4, 'Container Number *', 0, 1, 'L');
input_box($pdf, $ix + 5, $iy + 25, $iw - 10, 6, 'ABCD1234567');
set_text($pdf, $INK_MUTED);
$pdf->SetFont('helvetica', '', 5.5);
$pdf->SetXY($ix + 5, $iy + 32);
$pdf->Cell($iw - 10, 3, '4 capital letters + 7 numbers', 0, 1, 'L');
// Photo
set_text($pdf, $INK);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetXY($ix + 5, $iy + 40);
$pdf->Cell($iw - 10, 4, 'Pickup Photo *', 0, 1, 'L');
// Camera frame
set_fill($pdf, $BG_LIGHT);
set_draw($pdf, $BORDER);
$pdf->Rect($ix + 5, $iy + 45, $iw - 10, 22, 'DF');
set_text($pdf, $INK_MUTED);
$pdf->SetFont('helvetica', '', 6);
$pdf->SetXY($ix + 5, $iy + 53);
$pdf->Cell($iw - 10, 4, '[ tap to take photo ]', 0, 0, 'C');
// Buttons
button($pdf, $ix + 5, $iy + 72, ($iw - 14)/2, 7, 'Cancel', [148, 163, 184]);
button($pdf, $ix + 9 + ($iw - 14)/2, $iy + 72, ($iw - 14)/2, 7, 'Save Pickup', $BLUE);

$pdf->SetXY(15, $mockY);
step($pdf, 1, 'Tap the Picked up pill', "It's the first of four status buttons on the expanded booking card.");
step($pdf, 2, 'Enter the container number', "Format: 4 capital letters followed by 7 digits, e.g. ABCD1234567. The field auto-capitalises and strips invalid characters.");
step($pdf, 3, 'Tap Take Photo', "Your phone camera opens. Frame the container so the number is clearly readable, then tap the shutter.");
step($pdf, 4, 'Tap Save Pickup', "The photo is compressed automatically (no need to worry about file size) and uploaded. A spinner shows progress.");
$pdf->Ln(2);
callout($pdf, 'ok', 'Weak signal? No problem', "If the upload can't reach the server, the app saves it locally and shows 'Saved offline — will sync when back online.' You can continue working. The chip at the bottom of the screen shows how many uploads are waiting.");

// ==========================================================================
// SECTION 6 — STATUS UPDATES
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = '6. Trip Status';
h1($pdf, '6. Trip Status Updates');
body($pdf, "Once you've marked Pickup, three more pills become available in sequence: On the way, Arrived, and Mark Delivered. Tap each one as that step actually happens.");

$mockX = 130; $mockY = $pdf->GetY();
$inner = phone_frame($pdf, $mockX, $mockY, 60);
list($ix, $iy, $iw, $ih) = $inner;
status_bar($pdf, $ix, $iy, $iw);
app_bar($pdf, $ix, $iy + 4, $iw, 'Trip Status');
// Booking card
card($pdf, $ix + 3, $iy + 16, $iw - 6, 8, 'BK-20260520-001');
// Status pills (vertical)
set_text($pdf, $INK_MUTED);
$pdf->SetFont('helvetica', '', 6);
$pdf->SetXY($ix + 3, $iy + 27);
$pdf->Cell($iw - 6, 3, 'Trip status — tap each as it happens', 0, 1, 'L');
$pills = [
    ['1', 'Picked up',           $GREEN, true],
    ['2', 'On the way',          $BLUE,  false],
    ['3', 'Arrived',             [203, 213, 225], false],
    ['4', 'Mark Delivered',      [203, 213, 225], false],
];
foreach ($pills as $i => $p) {
    $yi = $iy + 32 + $i * 9;
    button($pdf, $ix + 3, $yi, $iw - 6, 7, $p[1], $p[2], $p[0] === '3' || $p[0] === '4' ? $INK_MUTED : [255, 255, 255]);
    // Number bubble overlay
    set_fill($pdf, [255, 255, 255]);
    $pdf->Circle($ix + 6, $yi + 3.5, 1.6, 0, 360, 'F');
    set_text($pdf, $p[2]);
    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->SetXY($ix + 4.4, $yi + 1.4);
    $pdf->Cell(3.2, 4, $p[0], 0, 0, 'C');
}
nav_bar($pdf, $ix, $iy + $ih - 9, $iw, 0);

$pdf->SetXY(15, $mockY);
step($pdf, 1, 'Green = done',  "A completed step shows in green with a checkmark and cannot be re-tapped.");
step($pdf, 2, 'Blue = next',   "The active blue pill is the one you should tap next.");
step($pdf, 3, 'Grey = future', "Greyed-out pills are not yet reachable — finish the previous step first.");
step($pdf, 4, 'Mark Delivered', "After tapping Delivered, you're taken to the POD page. See section 7.");
$pdf->Ln(2);
callout($pdf, 'tip', 'Your GPS is captured automatically', "Each tap silently sends your lat/lng to dispatch. There's nothing extra for you to do — it's part of the tap.");

// ==========================================================================
// SECTION 7 — POD
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = '7. Proof of Delivery';
h1($pdf, '7. Proof of Delivery (POD)');
body($pdf, "After Mark Delivered you're sent to the POD page. Dispatch will not close the job (and you don't get paid for it) until POD is captured and verified.");

$mockX = 130; $mockY = $pdf->GetY();
$inner = phone_frame($pdf, $mockX, $mockY, 60);
list($ix, $iy, $iw, $ih) = $inner;
status_bar($pdf, $ix, $iy, $iw);
app_bar($pdf, $ix, $iy + 4, $iw, 'POD');
// Photo slots
set_text($pdf, $INK);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetXY($ix + 3, $iy + 16);
$pdf->Cell($iw - 6, 4, 'Photos (at least 2)', 0, 1, 'L');
$slotW = ($iw - 12) / 3;
for ($i = 0; $i < 3; $i++) {
    set_fill($pdf, $BG_LIGHT);
    set_draw($pdf, $BORDER);
    $pdf->Rect($ix + 3 + $i * ($slotW + 1.5), $iy + 22, $slotW, 18, 'DF');
    set_text($pdf, $INK_MUTED);
    $pdf->SetFont('helvetica', '', 5.5);
    $pdf->SetXY($ix + 3 + $i * ($slotW + 1.5), $iy + 28);
    $pdf->Cell($slotW, 4, 'Photo ' . ($i + 1), 0, 0, 'C');
}
// Signed by field
set_text($pdf, $INK);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetXY($ix + 3, $iy + 44);
$pdf->Cell($iw - 6, 4, 'Recipient name', 0, 1, 'L');
input_box($pdf, $ix + 3, $iy + 48, $iw - 6, 6, 'Juan Cruz');
// Signature pad
set_text($pdf, $INK);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetXY($ix + 3, $iy + 58);
$pdf->Cell($iw - 6, 4, 'Recipient signature', 0, 1, 'L');
set_fill($pdf, [255, 255, 255]);
set_draw($pdf, $BORDER);
$pdf->Rect($ix + 3, $iy + 62, $iw - 6, 16, 'DF');
// Submit
button($pdf, $ix + 3, $iy + 81, $iw - 6, 7, 'Submit POD', $BLUE);

$pdf->SetXY(15, $mockY);
step($pdf, 1, 'Take at least 2 photos', "Slots 1 and 2 are required. Slot 3 is optional (use it if you need a wider shot or to capture the receiver's ID).");
step($pdf, 2, 'Type the recipient name', "Print the name of the person accepting the cargo.");
step($pdf, 3, 'Get their signature', "Pass the phone to the recipient and have them sign in the box with their finger. Tap Clear if it's illegible.");
step($pdf, 4, 'Submit POD', "Your dispatcher gets a notification and can verify the POD on their end. The job moves to Pending Verification.");
$pdf->Ln(2);
callout($pdf, 'warn', "Don't skip the signature", "POD without a signature can be rejected by the dispatcher, which delays your payment. Always get a sign-off.");

// ==========================================================================
// SECTION 8 — JACK-UP
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = '8. Trailer Jack-up';
h1($pdf, '8. Trailer Jack-up at Site');
body($pdf, "If you're leaving the trailer at the destination site (so the truck can be released for another job), use the Jack-up flow. Trailer billing keeps running until the trailer is returned to compound.");

$mockX = 130; $mockY = $pdf->GetY();
$inner = phone_frame($pdf, $mockX, $mockY, 60);
list($ix, $iy, $iw, $ih) = $inner;
status_bar($pdf, $ix, $iy, $iw);
app_bar($pdf, $ix, $iy + 4, $iw, 'Jack-up');
// Trailer code
set_text($pdf, $INK);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetXY($ix + 3, $iy + 16);
$pdf->Cell($iw - 6, 4, 'Trailer code', 0, 1, 'L');
input_box($pdf, $ix + 3, $iy + 21, $iw - 6, 6, 'TR007');
// GPS box
set_fill($pdf, [240, 253, 244]);
set_draw($pdf, [187, 247, 208]);
$pdf->Rect($ix + 3, $iy + 30, $iw - 6, 7, 'DF');
set_text($pdf, $GREEN);
$pdf->SetFont('helvetica', 'B', 6);
$pdf->SetXY($ix + 4, $iy + 32);
$pdf->Cell($iw - 8, 3, 'GPS Locked: 14.123, 121.456', 0, 0, 'L');
// Photo capture
set_text($pdf, $INK);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetXY($ix + 3, $iy + 40);
$pdf->Cell($iw - 6, 4, 'Photo of detached trailer', 0, 1, 'L');
button($pdf, $ix + 3, $iy + 45, $iw - 6, 7, 'Take Photo', [148, 163, 184]);
// Preview
set_fill($pdf, $BG_LIGHT);
set_draw($pdf, $BORDER);
$pdf->Rect($ix + 3, $iy + 55, 16, 16, 'DF');
// Submit
button($pdf, $ix + 3, $iy + 75, $iw - 6, 7, 'Mark Jacked Up', $BLUE);

$pdf->SetXY(15, $mockY);
step($pdf, 1, 'Open Jack-up from the dispatch card', "Tap Jack-up in the document/alt-flow section of the booking card.");
step($pdf, 2, 'Confirm the trailer code', "The field is pre-filled from your dispatch. Verify it matches the trailer you're actually leaving.");
step($pdf, 3, 'Wait for GPS to lock', "The box turns green when GPS is locked. If GPS times out, tap it to retry.");
step($pdf, 4, 'Take a photo of the detached trailer', "Frame the trailer clearly. The photo is automatically compressed before upload.");
step($pdf, 5, 'Tap Mark Jacked Up', "Your truck is released for the next job. The trailer continues to bill until it's returned to compound.");

// ==========================================================================
// SECTION 9 — GATELESS
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = '9. Gateless';
h1($pdf, '9. Gateless Completion');
body($pdf, "Some destinations have no gate or guard to sign for the cargo. Use Gateless Completion to confirm delivery with GPS evidence + two photos.");

$mockX = 130; $mockY = $pdf->GetY();
$inner = phone_frame($pdf, $mockX, $mockY, 60);
list($ix, $iy, $iw, $ih) = $inner;
status_bar($pdf, $ix, $iy, $iw);
app_bar($pdf, $ix, $iy + 4, $iw, 'Gateless');
// GPS
set_fill($pdf, [240, 253, 244]);
set_draw($pdf, [187, 247, 208]);
$pdf->Rect($ix + 3, $iy + 16, $iw - 6, 8, 'DF');
set_text($pdf, $GREEN);
$pdf->SetFont('helvetica', 'B', 6);
$pdf->SetXY($ix + 4, $iy + 18);
$pdf->Cell($iw - 8, 3, 'GPS: 14.123, 121.456 (~8 m)', 0, 0, 'L');
// Photo buttons
button($pdf, $ix + 3, $iy + 28, $iw - 6, 7, 'Photo 1', [148, 163, 184]);
button($pdf, $ix + 3, $iy + 38, $iw - 6, 7, 'Photo 2', [148, 163, 184]);
// Previews
set_fill($pdf, $BG_LIGHT);
set_draw($pdf, $BORDER);
$pdf->Rect($ix + 3, $iy + 48, 16, 16, 'DF');
$pdf->Rect($ix + 21, $iy + 48, 16, 16, 'DF');
// Submit
button($pdf, $ix + 3, $iy + 68, $iw - 6, 7, 'Submit Gateless', $BLUE);

$pdf->SetXY(15, $mockY);
step($pdf, 1, 'Open Gateless from the dispatch card', "It's listed under documents / alt-flows.");
step($pdf, 2, 'Confirm GPS is locked', "Stand outside if the lock is taking too long; concrete and metal roofs interfere with GPS.");
step($pdf, 3, 'Take Photo 1', "Wide shot showing the cargo at the delivery location.");
step($pdf, 4, 'Take Photo 2', "Close-up of the container number or a recognisable landmark for the address.");
step($pdf, 5, 'Tap Submit Gateless', "This is an end-state — the dispatch flips to delivered. No POD signature required.");
$pdf->Ln(2);
callout($pdf, 'ok', 'Works offline too', "If you have no signal at the site, the submission queues locally. As soon as you regain signal it syncs automatically.");

// ==========================================================================
// SECTION 10 — BREAKDOWN / SOS
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = '10. Breakdown / SOS';
h1($pdf, '10. Reporting a Breakdown or Sending SOS');
body($pdf, "If something goes wrong on the road — mechanical failure, cargo issue, or an emergency — you have two tools.");

h3($pdf, 'Breakdown Form');
body($pdf, "From the bottom nav, tap SOS. The Breakdown form lets you classify the incident, set severity, request assistance (tow / mechanic / cargo transfer / emergency), and add a photo.");

h3($pdf, 'SOS Push');
body($pdf, "On the dashboard's bottom nav, the red SOS button sends an immediate distress signal to dispatch with your current location. Use it for genuine emergencies only.");

$mockX = 130; $mockY = $pdf->GetY() - 30;
$inner = phone_frame($pdf, $mockX, $mockY, 60);
list($ix, $iy, $iw, $ih) = $inner;
status_bar($pdf, $ix, $iy, $iw);
app_bar($pdf, $ix, $iy + 4, $iw, 'Breakdown');
set_text($pdf, $INK);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetXY($ix + 3, $iy + 16);
$pdf->Cell($iw - 6, 4, 'Type', 0, 1, 'L');
input_box($pdf, $ix + 3, $iy + 21, $iw - 6, 6, 'Breakdown');
$pdf->SetXY($ix + 3, $iy + 30);
$pdf->Cell($iw - 6, 4, 'Severity', 0, 1, 'L');
input_box($pdf, $ix + 3, $iy + 35, $iw - 6, 6, 'Medium');
$pdf->SetXY($ix + 3, $iy + 44);
$pdf->Cell($iw - 6, 4, 'Assistance', 0, 1, 'L');
input_box($pdf, $ix + 3, $iy + 49, $iw - 6, 6, 'Mechanic');
$pdf->SetXY($ix + 3, $iy + 58);
$pdf->Cell($iw - 6, 4, 'Description', 0, 1, 'L');
set_fill($pdf, [255, 255, 255]);
set_draw($pdf, $BORDER);
$pdf->Rect($ix + 3, $iy + 62, $iw - 6, 12, 'DF');
button($pdf, $ix + 3, $iy + 77, $iw - 6, 7, 'Submit', $RED);

$pdf->SetXY(15, $pdf->GetY() + 10);
callout($pdf, 'err', 'SOS = emergency only', "The SOS button alerts every dispatcher on duty. Use it for accidents, medical emergencies, or threats — not for routine breakdowns.");

// ==========================================================================
// SECTION 11 — OFFLINE
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = '11. Offline Mode';
h1($pdf, '11. Offline Mode and Auto-Sync');
body($pdf, "The Pantrucks driver app is designed to keep working even when the signal drops. Every submit you make — pickup, POD, jack-up, gateless, breakdown, status updates — is queued locally if the network can't reach the server, and it syncs automatically when you're back online.");

h3($pdf, 'The Status Pill');
body($pdf, "Look at the bottom of any driver screen. A small floating pill shows your connection state:");

// Pills illustration
$py = $pdf->GetY();
pill($pdf, 15, $py, 35, 6, '● Online', $GREEN, [255, 255, 255], 8);
pill($pdf, 55, $py, 75, 6, '● Offline -- 2 waiting to sync', $AMBER, [30, 41, 59], 7);
pill($pdf, 135, $py, 55, 6, '↻ 2 waiting -- tap to sync', $BLUE, [255, 255, 255], 7);
$pdf->Ln(10);

step($pdf, 1, 'Green Online (auto-fades)', "Everything is working normally. The pill disappears after a second.");
step($pdf, 2, 'Amber Offline', "No signal. The number shows pending submissions waiting to upload.");
step($pdf, 3, 'Blue tap-to-sync', "You're online and there's a queue. Tap the pill to force a sync attempt.");

$pdf->Ln(1);
callout($pdf, 'tip', 'You will not lose data', "Even if you close the app or your phone restarts, queued submissions stay on disk and resume syncing when the app reopens with signal. Idempotency keys prevent duplicate records on retry.");

h3($pdf, 'What to do when you see a queue');
body($pdf, "Keep working. The queue drains automatically as soon as you get bars. If you need to force it (e.g. you're back in the depot and want to make sure everything's uploaded before clocking out), tap the blue pill.");

// ==========================================================================
// SECTION 12 — PROFILE
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = '12. Profile';
h1($pdf, '12. Editing Your Profile');
body($pdf, "Update your profile photo, name, or password from the profile page.");

$mockX = 130; $mockY = $pdf->GetY();
$inner = phone_frame($pdf, $mockX, $mockY, 60);
list($ix, $iy, $iw, $ih) = $inner;
status_bar($pdf, $ix, $iy, $iw);
app_bar($pdf, $ix, $iy + 4, $iw, 'My Profile');
// Avatar circle
set_fill($pdf, $BG_LIGHT);
$pdf->Circle($ix + $iw/2, $iy + 24, 9, 0, 360, 'F');
set_text($pdf, $INK_MUTED);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($ix, $iy + 21);
$pdf->Cell($iw, 5, 'JC', 0, 0, 'C');
// Name
set_text($pdf, $INK);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetXY($ix, $iy + 36);
$pdf->Cell($iw, 5, 'Juan Cruz', 0, 0, 'C');
set_text($pdf, $INK_MUTED);
$pdf->SetFont('helvetica', '', 6);
$pdf->SetXY($ix, $iy + 41);
$pdf->Cell($iw, 4, 'Driver ID: 247', 0, 0, 'C');
// Username
set_text($pdf, $INK);
$pdf->SetFont('helvetica', 'B', 6);
$pdf->SetXY($ix + 3, $iy + 50);
$pdf->Cell($iw - 6, 3, 'Username', 0, 1, 'L');
input_box($pdf, $ix + 3, $iy + 53, $iw - 6, 5, 'juan.cruz');
$pdf->SetXY($ix + 3, $iy + 61);
$pdf->Cell($iw - 6, 3, 'New password', 0, 1, 'L');
input_box($pdf, $ix + 3, $iy + 64, $iw - 6, 5, '• • • • •');
$pdf->SetXY($ix + 3, $iy + 71);
$pdf->Cell($iw - 6, 3, 'Confirm password', 0, 1, 'L');
input_box($pdf, $ix + 3, $iy + 74, $iw - 6, 5, '• • • • •');
button($pdf, $ix + 3, $iy + 82, $iw - 6, 7, 'Save Changes', $BLUE);

$pdf->SetXY(15, $mockY);
step($pdf, 1, 'Open Profile', "Tap your name in the sidebar (desktop) or the avatar at the top of the dashboard.");
step($pdf, 2, 'Change your photo', "Tap the small camera icon on the avatar. Pick a photo from your gallery or take a new one.");
step($pdf, 3, 'Update name or username', "Edit the fields directly. Username changes take effect on next sign-in.");
step($pdf, 4, 'Change password', "Type a new password and confirm. Leave blank to keep your current one. Tap Save Changes.");
$pdf->Ln(2);
callout($pdf, 'warn', 'Forgot your password?', "Drivers cannot reset their own passwords. Ask your HR Admin or dispatcher to reset it for you.");

// ==========================================================================
// SECTION 13 — TIPS & TROUBLESHOOTING
// ==========================================================================
$pdf->AddPage();
$pdf->sectionTitle = '13. Tips';
h1($pdf, '13. Tips & Troubleshooting');

h3($pdf, 'Add to Home Screen');
body($pdf, "Tap your browser's share/menu button and choose Add to Home Screen. The app then opens like a regular phone app — full screen, no browser bar.");

h3($pdf, 'Photos look slow to upload');
body($pdf, "The app automatically compresses photos before upload (a 10 MB iPhone photo becomes ~400 KB). If uploads still feel slow, you're probably in a dead zone — the offline queue will handle it.");

h3($pdf, 'A button does nothing when I tap it');
body($pdf, "Wait 2–3 seconds. The app may be acquiring GPS in the background. If still nothing after 5 seconds, pull-to-refresh the page once. If the problem persists, screenshot the screen and send it to your supervisor.");

h3($pdf, 'I see DriverUpload is not a function');
body($pdf, "Your browser is serving a stale cached file. Pull-to-refresh once, or close and reopen the app. Cache version markers in the URLs force a fresh load.");

h3($pdf, 'I tapped Decline by accident');
body($pdf, "Tell your dispatcher right away — they can reassign the job back to you. The decline is logged but reversible at the dispatch level.");

h3($pdf, 'My pickup photo did not save');
body($pdf, "Check the pill at the bottom. If it shows 'N waiting to sync', the photo is queued — it'll upload when signal returns. If it shows Online and the photo is genuinely gone, take it again.");

h3($pdf, 'My battery drains fast');
body($pdf, "GPS and continuous network use are battery-hungry. Carry a power bank in the cab. Close other apps you're not using.");

$pdf->Ln(2);
callout($pdf, 'tip', 'Keep your dispatcher updated', "When something doesn't work as expected, the fastest path to a fix is a screenshot + a quick chat message via the Chat icon in the bottom nav. Don't suffer in silence.");

// ---------------- output ---------------------------------------------------
$out = __DIR__ . '/Pantrucks-Driver-Guide.pdf';
$pdf->Output($out, 'F');
echo "Wrote: $out (" . round(filesize($out)/1024, 1) . " KB)\n";
