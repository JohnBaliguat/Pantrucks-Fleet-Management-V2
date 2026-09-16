// Pantrucks Driver — service worker registration + push subscription helper.
(function () {
  if (!('serviceWorker' in navigator)) return;
  // SW lives at site root so its scope can include both driver/* and
  // index.php URLs. Scope falls out of the SW's own URL location.
  navigator.serviceWorker.register('sw-driver.js').then(function (reg) {
    var pendingCount = 0;

    // ---- App update prompt -------------------------------------------------
    // When a new service worker is waiting, offer the driver a one-tap refresh
    // so fixes roll out without reinstalling the app.
    var reloading = false;
    navigator.serviceWorker.addEventListener('controllerchange', function () {
      if (reloading) return;
      reloading = true;
      window.location.reload();
    });

    function showUpdatePrompt(worker) {
      if (!worker || document.getElementById('ptUpdateBar')) return;
      var bar = document.createElement('div');
      bar.id = 'ptUpdateBar';
      bar.style.cssText = 'position:fixed;left:50%;transform:translateX(-50%);bottom:150px;z-index:10000;background:#1d4ed8;color:#fff;padding:10px 14px;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,.25);font-size:13px;font-weight:600;display:flex;align-items:center;gap:12px;max-width:92vw;';

      var label = document.createElement('span');
      label.textContent = 'App update available';
      bar.appendChild(label);

      var btn = document.createElement('button');
      btn.textContent = 'Refresh';
      btn.style.cssText = 'border:0;background:#fff;color:#1d4ed8;font-weight:700;border-radius:8px;padding:6px 12px;font-size:13px;cursor:pointer;';
      btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.textContent = 'Updating…';
        worker.postMessage({ type: 'SKIP_WAITING' });
        // Safety net: if controllerchange doesn't fire, reload anyway.
        setTimeout(function () { if (!reloading) { reloading = true; window.location.reload(); } }, 2000);
      });
      bar.appendChild(btn);

      var dismiss = document.createElement('span');
      dismiss.textContent = '✕';
      dismiss.title = 'Later';
      dismiss.style.cssText = 'cursor:pointer;opacity:.8;padding:0 4px;';
      dismiss.addEventListener('click', function () { bar.remove(); });
      bar.appendChild(dismiss);

      document.body.appendChild(bar);
    }

    // A newer SW may already be waiting from a previous visit.
    if (reg.waiting && navigator.serviceWorker.controller) {
      showUpdatePrompt(reg.waiting);
    }
    // Detect a new SW installing now.
    reg.addEventListener('updatefound', function () {
      var nw = reg.installing;
      if (!nw) return;
      nw.addEventListener('statechange', function () {
        if (nw.state === 'installed' && navigator.serviceWorker.controller) {
          showUpdatePrompt(nw);
        }
      });
    });
    // Check for updates periodically while the app stays open.
    setInterval(function () { reg.update().catch(function () {}); }, 30 * 60 * 1000);

    function confirmDiscard(cb) {
      var msg = pendingCount + ' pending upload' + (pendingCount === 1 ? '' : 's') +
                ' will be discarded and will NOT be sent. Continue?';
      if (window.Swal) {
        window.Swal.fire({
          title: 'Cancel pending sync?',
          text: msg,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Discard',
          cancelButtonText: 'Keep',
          confirmButtonColor: '#dc3545'
        }).then(function (r) { if (r.isConfirmed) cb(); });
      } else if (window.confirm(msg)) {
        cb();
      }
    }

    function ensurePill() {
      var pill = document.getElementById('ptOfflinePill');
      if (!pill) {
        pill = document.createElement('div');
        pill.id = 'ptOfflinePill';
        pill.style.cssText = 'position:fixed;left:50%;transform:translateX(-50%);bottom:90px;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:600;z-index:9999;box-shadow:0 4px 10px rgba(0,0,0,.15);transition:opacity .3s;cursor:pointer;display:flex;align-items:center;gap:8px;';

        var label = document.createElement('span');
        label.id = 'ptPillLabel';
        pill.appendChild(label);

        // Cancel (✕) — discards the queued submissions so the driver can stop a
        // stuck/duplicate sync instead of being forced to send them.
        var cancel = document.createElement('span');
        cancel.id = 'ptPillCancel';
        cancel.textContent = '✕';
        cancel.title = 'Cancel pending sync (discard queued uploads)';
        cancel.style.cssText = 'display:none;padding:0 7px;border-radius:999px;background:rgba(0,0,0,.2);font-weight:700;';
        pill.appendChild(cancel);

        document.body.appendChild(pill);

        pill.addEventListener('click', function () {
          if (pendingCount > 0 && navigator.serviceWorker.controller) {
            pill.dataset.syncing = '1';
            navigator.serviceWorker.controller.postMessage({ type: 'pt-replay' });
            renderPill(navigator.onLine);
          }
        });
        cancel.addEventListener('click', function (e) {
          e.stopPropagation();
          if (pendingCount > 0 && navigator.serviceWorker.controller) {
            confirmDiscard(function () {
              navigator.serviceWorker.controller.postMessage({ type: 'pt-queue-clear' });
            });
          }
        });
      }
      return pill;
    }

    function renderPill(online) {
      var pill = ensurePill();
      var label = pill.querySelector('#ptPillLabel');
      var cancel = pill.querySelector('#ptPillCancel');
      // Cancel is offered whenever there's something queued to discard.
      cancel.style.display = pendingCount > 0 ? 'inline-block' : 'none';

      if (!online) {
        pill.style.background = '#f59e0b';
        pill.style.color = '#1c1917';
        label.textContent = pendingCount > 0
          ? '● Offline — ' + pendingCount + ' waiting to sync'
          : '● Offline — actions will sync later';
        pill.style.opacity = '1';
        return;
      }
      // Online.
      if (pendingCount > 0) {
        pill.style.background = '#2563eb';
        pill.style.color = '#fff';
        var syncing = pill.dataset.syncing === '1';
        label.textContent = syncing
          ? '↻ Syncing ' + pendingCount + '…'
          : '↻ ' + pendingCount + ' waiting — tap to sync';
        pill.style.opacity = '1';
      } else {
        pill.style.background = '#16a34a';
        pill.style.color = '#fff';
        label.textContent = '● Online';
        // Briefly visible, then fade. Useful after a sync completes.
        pill.style.opacity = '1';
        setTimeout(function () { pill.style.opacity = '0'; }, 1500);
      }
    }

    function setOnline(online) {
      if (online && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'pt-replay' });
      }
      renderPill(online);
      requestQueueInfo();
    }

    function requestQueueInfo() {
      if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'pt-queue-info' });
      }
    }

    // Listen for queue updates from the SW.
    navigator.serviceWorker.addEventListener('message', function (event) {
      var data = event.data || {};
      if (data.type === 'pt-queue-info') {
        pendingCount = data.count || 0;
        var pill = document.getElementById('ptOfflinePill');
        if (pill) delete pill.dataset.syncing;
        renderPill(navigator.onLine);
      }
    });

    setOnline(navigator.onLine);
    window.addEventListener('online',  function () { setOnline(true); });
    window.addEventListener('offline', function () { setOnline(false); });
    // Periodic re-check while online — protects against the SW finishing a
    // sync before we got the postMessage (rare) and against missed messages.
    setInterval(function () {
      if (navigator.onLine) requestQueueInfo();
    }, 15000);

    // Push subscription — best-effort. If VAPID is configured server-side
    // we'll subscribe and POST the endpoint to push_subscription. Otherwise
    // silently no-op.
    if (!window.PT_VAPID_PUBLIC_KEY) return;
    if (!('PushManager' in window)) return;
    Notification.requestPermission().then(function (perm) {
      if (perm !== 'granted') return;
      reg.pushManager.getSubscription().then(function (existing) {
        if (existing) return existing;
        return reg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8(window.PT_VAPID_PUBLIC_KEY),
        });
      }).then(function (sub) {
        if (!sub) return;
        var json = sub.toJSON();
        var fd = new FormData();
        fd.append('endpoint',   json.endpoint || sub.endpoint);
        fd.append('p256dh_key', json.keys && json.keys.p256dh ? json.keys.p256dh : '');
        fd.append('auth_key',   json.keys && json.keys.auth   ? json.keys.auth   : '');
        fd.append('user_agent', navigator.userAgent || '');
        fetch('php/crud/add/save_push_subscription.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      }).catch(function () { /* push not available */ });
    });
  }).catch(function (e) { console.warn('SW register failed:', e); });

  function urlBase64ToUint8(base64) {
    var pad = '='.repeat((4 - base64.length % 4) % 4);
    var s = (base64 + pad).replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(s);
    var arr = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; ++i) arr[i] = raw.charCodeAt(i);
    return arr;
  }
})();
