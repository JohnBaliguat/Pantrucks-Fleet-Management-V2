<?php
// Printable blank form for collecting a new driver's registration details.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Driver Registration Form — Pantrucks</title>
  <style>
    * { box-sizing:border-box; }
    body { margin:0; background:#f1f5f9; color:#111827; font:12px Arial, sans-serif; }
    .actions { max-width:210mm; margin:16px auto; text-align:right; }
    .print-btn { border:0; border-radius:6px; background:#1d4ed8; color:#fff; cursor:pointer; padding:10px 16px; font-weight:700; }
    .page { width:210mm; min-height:297mm; margin:0 auto 16px; padding:15mm; background:#fff; }
    .head { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #1e3a8a; padding-bottom:10px; }
    .head img { max-width:120px; max-height:38px; object-fit:contain; }
    h1 { margin:0; color:#172554; font-size:20px; letter-spacing:.5px; }
    .sub { margin-top:4px; color:#475569; }
    .form-no { width:145px; padding:7px; border:1px solid #64748b; font-size:11px; }
    .notice { margin:13px 0; padding:8px 10px; border-left:4px solid #2563eb; background:#eff6ff; line-height:1.45; }
    h2 { margin:15px 0 7px; padding:5px 8px; color:#fff; background:#1e3a8a; font-size:12px; letter-spacing:.3px; }
    .grid { display:grid; grid-template-columns:repeat(2,1fr); gap:9px 14px; }
    .field { min-height:28px; border-bottom:1px solid #475569; padding:12px 2px 2px; position:relative; }
    .field label { position:absolute; top:0; left:2px; color:#475569; font-size:9px; text-transform:uppercase; }
    .field.required label::after { content:' ✓ REQUIRED'; color:#166534; font-weight:700; }
    .full { grid-column:1 / -1; }
    .triple { grid-template-columns:repeat(3,1fr); }
    .checks { display:flex; flex-wrap:wrap; gap:10px 20px; padding:5px 2px; }
    .checks span::before { content:'☐'; margin-right:5px; font-size:15px; vertical-align:-1px; }
    .declaration { margin-top:11px; line-height:1.55; text-align:justify; }
    .signature { margin-top:30px; display:grid; grid-template-columns:repeat(2,1fr); gap:45px; text-align:center; }
    .signature div { border-top:1px solid #111827; padding-top:4px; }
    .footer { margin-top:16px; padding-top:7px; border-top:1px solid #cbd5e1; color:#64748b; font-size:9px; display:flex; justify-content:space-between; }
    @page { size:A4 portrait; margin:0; }
    @media print { body { background:#fff; } .actions { display:none; } .page { margin:0; width:210mm; min-height:297mm; } }
  </style>
</head>
<body>
  <div class="actions"><button class="print-btn" onclick="window.print()">Print registration form</button></div>
  <main class="page">
    <header class="head">
      <div><h1>DRIVER REGISTRATION FORM</h1><div class="sub">Pantrucks Fleet Management System</div></div>
      <div class="form-no">Date: ___________________<br>Form No.: ______________</div>
    </header>
    <div class="notice">Please complete all applicable fields in clear block letters. Submit this form together with a copy of your valid driver’s license and any required company documents.</div>

    <h2>1. PERSONAL INFORMATION</h2>
    <div class="grid">
      <div class="field required"><label>Last name</label></div><div class="field required"><label>First name</label></div>
      <div class="field required"><label>Middle name</label></div><div class="field"><label>Nickname / preferred name</label></div>
      <div class="field required"><label>Date of birth</label></div><div class="field required"><label>Place of birth</label></div>
      <div class="field required"><label>Mobile number</label></div><div class="field"><label>Email address</label></div>
      <div class="field full required"><label>Current residential address</label></div>
    </div>

    <h2>2. DRIVER’S LICENSE INFORMATION</h2>
    <div class="grid triple">
      <div class="field required"><label>License number</label></div><div class="field required"><label>License class / restriction</label></div><div class="field required"><label>Expiry date</label></div>
      <div class="field"><label>Issuing office</label></div><div class="field"><label>Years of driving experience</label></div><div class="field required"><label>Company ID</label></div>
    </div>
    <div class="checks"><span>Professional license</span><span>Non-professional license</span><span>With defensive-driving training</span></div>

    <h2>3. EMPLOYMENT &amp; ASSIGNMENT</h2>
    <div class="grid">
      <div class="field"><label>Preferred / assigned base</label></div><div class="field"><label>Preferred hauling segment</label></div>
      <div class="field"><label>Assigned unit / truck</label></div><div class="field"><label>Date available to start</label></div>
      <div class="field full"><label>Previous employer / relevant driving experience</label></div>
    </div>

    <h2>4. EMERGENCY CONTACT</h2>
    <div class="grid">
      <div class="field"><label>Contact person’s full name</label></div><div class="field"><label>Relationship</label></div>
      <div class="field"><label>Mobile number</label></div><div class="field"><label>Alternate contact number</label></div>
      <div class="field full"><label>Address</label></div>
    </div>

    <h2>5. COMPANY USE / REQUIREMENTS</h2>
    <div class="grid triple">
      <div class="field"><label>Driver ID number</label></div><div class="field"><label>Interviewed by</label></div><div class="field"><label>Date registered</label></div>
    </div>
    <div class="checks"><span>Valid license copy received</span><span>Identity document received</span><span>Background check completed</span><span>System account created</span></div>

    <p class="declaration">I certify that the information provided in this form is true and complete. I authorize Pantrucks to use this information for driver registration, assignment, safety, and operational records in accordance with applicable company policies.</p>
    <div class="signature"><div>Driver’s signature over printed name</div><div>Date signed</div></div>
    <div class="signature"><div>Received / verified by</div><div>Approving officer</div></div>
    <footer class="footer"><span>Pantrucks Fleet Management System</span><span>Driver Registration Form</span></footer>
  </main>
</body>
</html>
