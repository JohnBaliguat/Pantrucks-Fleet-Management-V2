// Shared driver-side upload helpers.
// - DriverUpload.compressPhoto(file, opts) resizes/recompresses an image File
//   client-side so mobile camera photos don't exceed server upload limits or
//   stall on slow networks.
// - DriverUpload.compressMany(files, opts) compresses an array of Files in
//   parallel, leaving non-image / missing entries untouched.
// - DriverUpload.setBtnBusy($btn, isBusy, busyText) toggles a Bootstrap-style
//   spinner on a button and disables it while work is in flight.
// - DriverUpload.errorMessage(xhrOrTextStatus) extracts a friendly message
//   from a jQuery ajax failure.
(function (global) {
  'use strict';

  function compressPhoto(file, opts) {
    opts = opts || {};
    var maxDim = opts.maxDim || 1600;
    var quality = typeof opts.quality === 'number' ? opts.quality : 0.85;

    return new Promise(function (resolve) {
      if (!file || !file.type || file.type.indexOf('image/') !== 0) {
        resolve(file);
        return;
      }
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        var width = img.width;
        var height = img.height;
        var scale = Math.min(1, maxDim / Math.max(width, height));
        width = Math.round(width * scale);
        height = Math.round(height * scale);

        var canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);
        URL.revokeObjectURL(url);

        canvas.toBlob(function (blob) {
          if (!blob) { resolve(file); return; }
          if (blob.size >= file.size) { resolve(file); return; }
          var baseName = (file.name || 'photo').replace(/\.[^.]+$/, '');
          var out = new File([blob], baseName + '.jpg', { type: 'image/jpeg' });
          resolve(out);
        }, 'image/jpeg', quality);
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  function compressMany(files, opts) {
    return Promise.all((files || []).map(function (f) {
      if (!f) return Promise.resolve(f);
      return compressPhoto(f, opts);
    }));
  }

  function setBtnBusy($btn, isBusy, busyText) {
    if (!$btn || !$btn.length) return;
    if (isBusy) {
      if ($btn.data('orig-html') === undefined) {
        $btn.data('orig-html', $btn.html());
      }
      if ($btn.data('orig-disabled') === undefined) {
        $btn.data('orig-disabled', !!$btn.prop('disabled'));
      }
      $btn.prop('disabled', true).html(
        '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
        (busyText || 'Saving...')
      );
    } else {
      var orig = $btn.data('orig-html');
      if (orig !== undefined) $btn.html(orig);
      $btn.removeData('orig-html');
      var wasDisabled = $btn.data('orig-disabled');
      $btn.prop('disabled', wasDisabled === true);
      $btn.removeData('orig-disabled');
    }
  }

  function errorMessage(xhr, textStatus) {
    if (textStatus === 'timeout') {
      return 'Upload timed out. Check your connection and try again.';
    }
    try {
      var body = (xhr && xhr.responseText) ? JSON.parse(xhr.responseText) : null;
      if (body && body.message) return body.message;
    } catch (e) {}
    if (xhr && xhr.status === 413) {
      return 'Photo is too large to upload. Try again with a smaller image.';
    }
    return 'Network error. Please try again.';
  }

  // ---- Page-level offline queue ------------------------------------------
  // Writes pending POSTs to the same IndexedDB (`pt-driver` / `queue`) the
  // service worker uses. This is the fallback for when the SW isn't
  // controlling the page yet (e.g. first visit, just after SW update) and
  // the network is flaky. The SW picks these entries up on its next sync.
  var QUEUE_DB_NAME = 'pt-driver';
  var QUEUE_DB_VERSION = 2;
  var QUEUE_STORE = 'queue';

  function openQueueDB() {
    return new Promise(function (resolve, reject) {
      if (typeof indexedDB === 'undefined') { reject(new Error('IndexedDB unavailable')); return; }
      var req = indexedDB.open(QUEUE_DB_NAME, QUEUE_DB_VERSION);
      req.onupgradeneeded = function () {
        var db = req.result;
        if (!db.objectStoreNames.contains(QUEUE_STORE)) {
          db.createObjectStore(QUEUE_STORE, { keyPath: 'id', autoIncrement: true });
        }
      };
      req.onsuccess = function () { resolve(req.result); };
      req.onerror = function () { reject(req.error); };
    });
  }

  function serializeFormData(fd) {
    var entries = [];
    if (!fd || typeof fd.forEach !== 'function') return entries;
    fd.forEach(function (v, k) {
      if (typeof v === 'string') {
        entries.push({ k: k, kind: 'string', v: v });
      } else if (v) {
        // File/Blob — IndexedDB stores Blobs natively.
        entries.push({
          k: k,
          kind: 'file',
          name: v.name || 'upload',
          type: v.type || 'application/octet-stream',
          blob: v
        });
      }
    });
    return entries;
  }

  function queueRequest(url, fd) {
    return openQueueDB().then(function (db) {
      return new Promise(function (resolve, reject) {
        var absUrl;
        try { absUrl = new URL(url, location.href).toString(); } catch (e) { absUrl = url; }
        var record = {
          url: absUrl,
          method: 'POST',
          kind: 'multipart',
          entries: serializeFormData(fd),
          headers: [],
          queuedAt: Date.now()
        };
        var tx = db.transaction(QUEUE_STORE, 'readwrite');
        tx.objectStore(QUEUE_STORE).add(record);
        tx.oncomplete = function () { resolve(true); };
        tx.onerror = function () { reject(tx.error); };
      });
    });
  }

  /**
   * POST a FormData with offline fallback.
   * - Tries fetch first. If it resolves (even with 4xx/5xx), returns the Response.
   * - If fetch throws (network error AND the SW didn't intercept), queues
   *   the request locally in IndexedDB and returns a synthetic 202 Response
   *   with body `{status:'queued', message:'Saved offline...'}`.
   * Caller treats this exactly like a normal fetch result.
   */
  function fetchOrQueue(url, fd) {
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (res) { return res; })
      .catch(function (err) {
        return queueRequest(url, fd).then(function () {
          // Nudge the SW so it tries the queue once it's active.
          try {
            if (navigator.serviceWorker && navigator.serviceWorker.controller) {
              navigator.serviceWorker.controller.postMessage({ type: 'pt-replay' });
            }
          } catch (_) {}
          return new Response(JSON.stringify({
            status: 'queued',
            message: 'Saved offline — will sync when back online.'
          }), { status: 202, headers: { 'Content-Type': 'application/json' } });
        }).catch(function () {
          // Couldn't even queue locally — propagate original error so the
          // caller can surface a real failure to the user.
          throw err;
        });
      });
  }

  function generateIdempotencyKey() {
    if (global.crypto && typeof global.crypto.randomUUID === 'function') {
      return global.crypto.randomUUID();
    }
    // RFC4122 v4 fallback for older browsers.
    var d = Date.now();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = (d + Math.random() * 16) % 16 | 0;
      d = Math.floor(d / 16);
      return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
  }

  /**
   * Append an idempotency_key field to a FormData. Returns the key so the
   * caller can log it / show it to the driver if needed.
   */
  function attachIdempotencyKey(formData, key) {
    var k = key || generateIdempotencyKey();
    if (formData && typeof formData.set === 'function') {
      formData.set('idempotency_key', k);
    } else if (formData && typeof formData.append === 'function') {
      formData.append('idempotency_key', k);
    }
    return k;
  }

  global.DriverUpload = {
    compressPhoto: compressPhoto,
    compressMany: compressMany,
    setBtnBusy: setBtnBusy,
    errorMessage: errorMessage,
    generateIdempotencyKey: generateIdempotencyKey,
    attachIdempotencyKey: attachIdempotencyKey,
    fetchOrQueue: fetchOrQueue,
    queueRequest: queueRequest
  };
})(window);
