/* Driver Location Gate
 *
 * Included on every driver-app page. Behaviour:
 *   1. On load, check geolocation permission via the Permissions API.
 *   2. If 'denied' or 'prompt', show a full-screen blocking modal — the
 *      driver cannot use the app until they grant Location.
 *   3. Once granted, start a watch-position heartbeat that POSTs the
 *      current fix to /php/operations/ingest_driver_gps.php every ~30s.
 *   4. A small status pill in the corner shows GPS health at all times.
 *   5. If the driver revokes mid-session, the watch's error callback
 *      re-opens the gate and stops the heartbeat.
 *
 * Stand-alone (no external deps beyond the browser APIs).
 */
(function () {
    'use strict';

    if (window.__DriverGpsGateLoaded) return;
    window.__DriverGpsGateLoaded = true;

    // ---- config ---------------------------------------------------------
    var HEARTBEAT_MS    = 30 * 1000;  // server-side update cadence
    var STALE_WARN_MS   = 90 * 1000;  // pill turns yellow after this
    var STALE_ERROR_MS  = 5 * 60 * 1000; // pill turns red after this
    var INGEST_URL      = 'php/operations/ingest_driver_gps.php';

    // ---- state ----------------------------------------------------------
    var watchId      = null;
    var lastFixAt    = 0;       // ms epoch of the most recent successful fix
    var lastPostAt   = 0;       // ms epoch of the most recent successful POST
    var lastFix      = null;    // { lat, lng, accuracy }
    var gateVisible  = false;

    // ---- granted memory -------------------------------------------------
    // Once a driver has successfully produced a fix we remember it, so that a
    // later page load / refresh does NOT re-open the blocking gate just because
    // the first GPS read timed out (a cold GPS start routinely exceeds the
    // probe timeout on mobile). Only a real PERMISSION_DENIED clears this.
    var GRANT_KEY = 'drvLocGranted';
    function markGranted() {
        try { localStorage.setItem(GRANT_KEY, '1'); } catch (e) {}
    }
    function clearGranted() {
        try { localStorage.removeItem(GRANT_KEY); } catch (e) {}
    }
    function everGranted() {
        try { return localStorage.getItem(GRANT_KEY) === '1'; } catch (e) { return false; }
    }

    // ---- DOM ------------------------------------------------------------
    function buildDom() {
        if (document.getElementById('drvLocGateOverlay')) return;

        var css = ''
          + '#drvLocGateOverlay { position:fixed; inset:0; z-index:2147483645; '
          +   'background:rgba(15,23,42,.85); display:none; align-items:center; '
          +   'justify-content:center; padding:20px; backdrop-filter: blur(2px); }'
          + '#drvLocGateOverlay.is-visible { display:flex; }'
          + '#drvLocGateCard { max-width:420px; width:100%; background:#fff; '
          +   'border-radius:14px; padding:24px; box-shadow:0 12px 40px rgba(0,0,0,.35); '
          +   'text-align:center; font-family: Inter, system-ui, sans-serif; }'
          + '#drvLocGateCard h3 { margin:0 0 10px; font-size:20px; color:#0f172a; }'
          + '#drvLocGateCard p  { margin:0 0 16px; font-size:14px; color:#475569; line-height:1.45; }'
          + '#drvLocGateCard .pin { font-size:48px; line-height:1; margin-bottom:8px; }'
          + '#drvLocGateCard .btn-primary { background:#0d6efd; color:#fff; border:0; '
          +   'border-radius:10px; padding:12px 18px; font-weight:700; font-size:15px; '
          +   'width:100%; cursor:pointer; }'
          + '#drvLocGateCard .btn-primary:disabled { opacity:.6; cursor:wait; }'
          + '#drvLocGateCard .help { text-align:left; margin-top:14px; font-size:12px; '
          +   'color:#334155; background:#f1f5f9; border-radius:8px; padding:10px 12px; '
          +   'display:none; }'
          + '#drvLocGateCard .help.is-visible { display:block; }'
          + '#drvLocGateCard .help b { color:#0f172a; }'
          + '#drvLocGateCard .help ol { padding-left:18px; margin:6px 0 0; }'
          + '#drvLocGateCard .err { color:#b91c1c; font-size:12px; margin-top:8px; min-height:14px; }'
          + '#drvGpsPill { position:fixed; right:12px; bottom:12px; z-index:2147483644; '
          +   'font-family: Inter, system-ui, sans-serif; font-size:11px; font-weight:700; '
          +   'padding:6px 10px; border-radius:999px; box-shadow:0 4px 12px rgba(0,0,0,.18); '
          +   'cursor:pointer; user-select:none; display:flex; align-items:center; gap:6px; '
          +   'background:#10b981; color:#fff; transition: background .2s, color .2s; }'
          + '#drvGpsPill.is-warn  { background:#f59e0b; color:#0f172a; }'
          + '#drvGpsPill.is-error { background:#dc2626; color:#fff; }'
          + '#drvGpsPill .dot { width:8px; height:8px; border-radius:50%; background:currentColor; '
          +   'box-shadow:0 0 0 2px rgba(255,255,255,.5); }';

        var style = document.createElement('style');
        style.id = 'drvLocGateStyles';
        style.textContent = css;
        document.head.appendChild(style);

        var overlay = document.createElement('div');
        overlay.id = 'drvLocGateOverlay';
        overlay.innerHTML = ''
          + '<div id="drvLocGateCard" role="dialog" aria-modal="true" aria-labelledby="drvLocGateTitle">'
          +   '<div class="pin">📍</div>'
          +   '<h3 id="drvLocGateTitle">Location is required</h3>'
          +   '<p id="drvLocGateMsg">Pantrucks needs your live location so dispatch can route and verify your trips. '
          +     'Tap the button below and choose <b>Allow</b>.</p>'
          +   '<button type="button" class="btn-primary" id="drvLocGateBtn">Enable Location</button>'
          +   '<div class="err" id="drvLocGateErr"></div>'
          +   '<div class="help" id="drvLocGateHelp">'
          +     '<b>If you don\'t see a permission prompt:</b>'
          +     '<ol id="drvLocGateHelpSteps"></ol>'
          +   '</div>'
          + '</div>';
        document.body.appendChild(overlay);

        var pill = document.createElement('div');
        pill.id = 'drvGpsPill';
        pill.innerHTML = '<span class="dot"></span><span class="lbl">Checking GPS…</span>';
        pill.title = 'Click for GPS status';
        document.body.appendChild(pill);

        document.getElementById('drvLocGateBtn').addEventListener('click', requestPermissionFromGesture);
        pill.addEventListener('click', function () {
            // If GPS is stale or denied, re-prompt — otherwise just acknowledge.
            var since = Date.now() - lastFixAt;
            if (since > STALE_WARN_MS || !lastFixAt) showGate('Re-enable location to keep your trips visible to dispatch.');
        });
    }

    // ---- gate -----------------------------------------------------------
    function showGate(message) {
        buildDom();
        var ov  = document.getElementById('drvLocGateOverlay');
        var msg = document.getElementById('drvLocGateMsg');
        if (message) msg.textContent = message;
        ov.classList.add('is-visible');
        gateVisible = true;
        // Hide the page underneath from screen readers / focus.
        document.body.style.overflow = 'hidden';
    }
    function hideGate() {
        var ov = document.getElementById('drvLocGateOverlay');
        if (ov) ov.classList.remove('is-visible');
        gateVisible = false;
        document.body.style.overflow = '';
    }
    function showError(text) {
        var e = document.getElementById('drvLocGateErr');
        if (e) e.textContent = text || '';
    }
    function showPlatformHelp() {
        var help  = document.getElementById('drvLocGateHelp');
        var steps = document.getElementById('drvLocGateHelpSteps');
        if (!help || !steps) return;
        var ua = navigator.userAgent || '';
        var lines;
        if (/iPhone|iPad|iPod/i.test(ua)) {
            lines = [
                'Open <b>Settings</b> on your iPhone',
                'Tap <b>Privacy &amp; Security</b> → <b>Location Services</b>',
                'Make sure Location Services is <b>ON</b>',
                'Scroll to <b>Safari Websites</b> → set to <b>While Using</b>',
                'Come back here and tap <b>Enable Location</b> again'
            ];
        } else if (/Android/i.test(ua)) {
            lines = [
                'Tap the <b>🔒 padlock</b> in the address bar above',
                'Tap <b>Permissions</b> → <b>Location</b>',
                'Choose <b>Allow</b>',
                'Make sure Android <b>Location</b> is also ON (pull down from the top)',
                'Come back here and tap <b>Enable Location</b> again'
            ];
        } else {
            lines = [
                'Click the <b>🔒 padlock</b> in the address bar',
                'Find <b>Location</b> and set it to <b>Allow</b>',
                'Reload the page'
            ];
        }
        steps.innerHTML = lines.map(function (l) { return '<li>' + l + '</li>'; }).join('');
        help.classList.add('is-visible');
    }

    // ---- pill -----------------------------------------------------------
    function setPill(state, label) {
        var pill = document.getElementById('drvGpsPill');
        if (!pill) return;
        pill.classList.toggle('is-warn',  state === 'warn');
        pill.classList.toggle('is-error', state === 'error');
        var lbl = pill.querySelector('.lbl');
        if (lbl) lbl.textContent = label;
    }
    function refreshPill() {
        if (gateVisible) { setPill('error', 'Location OFF'); return; }
        if (!lastFixAt)  { setPill('warn',  'Waiting for GPS…'); return; }
        var ageMs = Date.now() - lastFixAt;
        if (ageMs > STALE_ERROR_MS) setPill('error', 'GPS stale');
        else if (ageMs > STALE_WARN_MS) setPill('warn', 'GPS weak');
        else setPill('ok', 'GPS live');
    }
    setInterval(refreshPill, 15000);

    // ---- heartbeat ------------------------------------------------------
    // Only notify on the open → closed transition during this browser
    // session — not when the page just loaded with no shift. Otherwise the
    // popup re-fires every reload for drivers who haven't clocked in.
    var shiftWasOpen       = false;
    var shiftEndedNotified = false;
    function postFix(lat, lng) {
        // Throttle to ~HEARTBEAT_MS between successful POSTs to save bandwidth.
        if (Date.now() - lastPostAt < HEARTBEAT_MS - 1000) return;
        var fd = new FormData();
        fd.append('lat', lat);
        fd.append('lng', lng);
        fetch(INGEST_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
            .then(function (res) {
                lastPostAt = Date.now();
                if (!res) return;
                if (res.shift_open === true) {
                    // Mark that we have observed an open shift in this session.
                    shiftWasOpen = true;
                } else if (res.shift_open === false && shiftWasOpen && !shiftEndedNotified) {
                    // Observed transition: had an open shift, server now says it's closed.
                    shiftEndedNotified = true;
                    notifyShiftAutoEnded();
                }
            })
            .catch(function () { /* network glitches are tolerated — next fix retries */ });
    }

    function notifyShiftAutoEnded() {
        // Use SweetAlert2 if the page loaded it; otherwise fall back to a
        // built-in banner so the driver still sees the message.
        var msg = 'Your shift was auto-ended after 1 hour of inactivity. ' +
                  'Open the dashboard to start a new shift when you\'re ready.';
        if (window.Swal && typeof Swal.fire === 'function') {
            Swal.fire({
                icon: 'info',
                title: 'Shift ended',
                text: msg,
                confirmButtonText: 'Open Dashboard'
            }).then(function () {
                if (location.pathname.indexOf('driver-dashboard') === -1) {
                    location.href = 'driver-dashboard';
                } else {
                    location.reload();
                }
            });
        } else {
            var banner = document.createElement('div');
            banner.style.cssText = 'position:fixed;top:0;left:0;right:0;background:#0d6efd;color:#fff;'
                + 'padding:12px;text-align:center;font-weight:700;z-index:2147483646;'
                + 'font-family:Inter,system-ui,sans-serif;font-size:14px;';
            banner.textContent = msg;
            document.body.appendChild(banner);
        }
    }

    function startWatch() {
        if (watchId !== null) return;
        try {
            watchId = navigator.geolocation.watchPosition(onFix, onWatchError, {
                enableHighAccuracy: true,
                maximumAge: 15000,
                timeout: 30000
            });
        } catch (e) { /* old browsers throw */ }
    }
    function stopWatch() {
        if (watchId !== null) {
            try { navigator.geolocation.clearWatch(watchId); } catch (e) {}
            watchId = null;
        }
    }
    function onFix(pos) {
        var lat = +pos.coords.latitude.toFixed(7);
        var lng = +pos.coords.longitude.toFixed(7);
        lastFix   = { lat: lat, lng: lng, accuracy: pos.coords.accuracy };
        lastFixAt = Date.now();
        markGranted();
        hideGate();
        postFix(lat, lng);
        refreshPill();
    }
    function onWatchError(err) {
        // PERMISSION_DENIED (1) is the case that re-opens the gate.
        if (err && err.code === 1) {
            clearGranted();
            stopWatch();
            showGate('Location was turned off. Re-enable it to continue.');
            showPlatformHelp();
        }
        // Code 2 (POSITION_UNAVAILABLE) and 3 (TIMEOUT) are transient — let
        // watchPosition keep trying. Pill will flag stale automatically.
        refreshPill();
    }

    // ---- initial check + button gesture --------------------------------
    function requestPermissionFromGesture() {
        var btn = document.getElementById('drvLocGateBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Requesting…'; }
        showError('');
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                if (btn) { btn.disabled = false; btn.textContent = 'Enable Location'; }
                onFix(pos);
                startWatch();
            },
            function (err) {
                if (btn) { btn.disabled = false; btn.textContent = 'Try again'; }
                if (err && err.code === 1) {
                    showError('Permission denied. Follow the steps below to enable.');
                    showPlatformHelp();
                } else if (err && err.code === 2) {
                    showError('Could not get a fix — make sure GPS is on.');
                } else if (err && err.code === 3) {
                    showError('Timed out — please try again.');
                } else {
                    showError('Unknown error.');
                }
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    }

    function init() {
        if (!('geolocation' in navigator)) {
            // No geolocation at all — show the gate with a friendlier message
            // and no Allow button (the driver is on an unsupported browser).
            buildDom();
            showGate('This browser does not support location. Please use Chrome or Safari.');
            var btn = document.getElementById('drvLocGateBtn');
            if (btn) btn.style.display = 'none';
            setPill('error', 'No GPS support');
            return;
        }
        buildDom();

        // Use Permissions API for an upfront check (avoids a flash of the
        // success state when the user already granted in a prior session).
        if (navigator.permissions && navigator.permissions.query) {
            navigator.permissions.query({ name: 'geolocation' }).then(function (status) {
                applyPermissionState(status.state);
                status.onchange = function () { applyPermissionState(status.state); };
            }, function () {
                // Permissions API not implemented for geolocation on this browser
                // (Safari < 16). Fall back to the silent getCurrentPosition.
                silentProbe();
            });
        } else {
            silentProbe();
        }
    }
    function applyPermissionState(state) {
        if (state === 'granted') {
            hideGate();
            // Kick off an immediate fix + watch.
            navigator.geolocation.getCurrentPosition(onFix, onWatchError, {
                enableHighAccuracy: true, timeout: 15000, maximumAge: 0
            });
            startWatch();
        } else if (state === 'prompt') {
            // Some environments — notably the Android driver-app WebView — always
            // report 'prompt' for geolocation even after the OS-level permission
            // is granted. If the driver has produced a fix before, don't block:
            // silently probe and only re-open the gate on a real denial.
            if (everGranted()) {
                navigator.geolocation.getCurrentPosition(
                    function (pos) { onFix(pos); startWatch(); },
                    function (err) {
                        if (err && err.code === 1) {
                            clearGranted();
                            showGate('Location was turned off. Re-enable it to continue.');
                            showPlatformHelp();
                        } else {
                            // Transient failure — keep retrying, don't block.
                            startWatch();
                        }
                        refreshPill();
                    },
                    { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 }
                );
            } else {
                showGate();
            }
        } else {
            clearGranted();
            stopWatch();
            showGate('Location was turned off. Re-enable it to continue.');
            showPlatformHelp();
        }
        refreshPill();
    }
    function silentProbe() {
        // No Permissions API — try a silent getCurrentPosition. If the browser
        // already has a stored answer (granted or denied), this returns
        // immediately. Otherwise the user gets a prompt the first time.
        navigator.geolocation.getCurrentPosition(
            function (pos) { onFix(pos); startWatch(); },
            function (err) {
                if (err && err.code === 1) {
                    // Real permission denial — block and guide the driver.
                    clearGranted();
                    showGate('Location was turned off. Re-enable it to continue.');
                    showPlatformHelp();
                } else if (everGranted()) {
                    // Transient failure (timeout / position unavailable) but the
                    // driver already granted before — don't re-open the gate,
                    // just keep the watch retrying in the background.
                    startWatch();
                } else {
                    // First-ever visit and no fix yet — prompt to enable.
                    showGate();
                }
                refreshPill();
            },
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 }
        );
    }

    // Boot when the DOM is ready (we need <body> to mount the overlay).
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
