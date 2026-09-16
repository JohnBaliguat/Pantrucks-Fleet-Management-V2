// Pantrucks Driver — service worker
// Offline-first POST queue: stores FormData entries (including file Blobs)
// natively in IndexedDB and rebuilds them as multipart/form-data on replay.

const CACHE_VERSION = 'pt-driver-v4';
const APP_SHELL = [
  'driver-dashboard',
  'manifest.webmanifest',
  'assets/css/styles.min.css',
  'assets/css/enhancements.css',
  'assets/css/driver-modern.css',
  'assets/libs/jquery/dist/jquery.min.js',
  'assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js',
  'alert/node_modules/sweetalert2/dist/sweetalert2.min.js',
  'alert/node_modules/sweetalert2/dist/sweetalert2.min.css',
  'driver/driver-upload.js',
];

const SYNC_TAG = 'pt-driver-replay';
const DB_NAME  = 'pt-driver';
const DB_VERSION = 2;
const DB_STORE = 'queue';
const QUEUEABLE_PATTERNS = [
  /\/php\/operations\/driver_/,           // accept/decline/status/etc
  /\/php\/operations\/save_pod/,
  /\/php\/operations\/save_gateless/,
  /\/php\/operations\/save_trailer_jackup/,
  /\/php\/operations\/save_breakdown/,
  /\/php\/operations\/save_pre_departure/,
  /\/php\/operations\/send_message/,
];

// ---- IDB helpers ---------------------------------------------------------
function idbOpen() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = (event) => {
      const db = req.result;
      if (!db.objectStoreNames.contains(DB_STORE)) {
        db.createObjectStore(DB_STORE, { keyPath: 'id', autoIncrement: true });
      }
      // If upgrading from v1 (with broken JSON envelopes), wipe stale entries.
      if (event.oldVersion < 2) {
        const tx = req.transaction;
        if (tx) {
          try { tx.objectStore(DB_STORE).clear(); } catch (_) {}
        }
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror   = () => reject(req.error);
  });
}
async function idbAdd(item) {
  const db = await idbOpen();
  return new Promise((r, j) => {
    const tx = db.transaction(DB_STORE, 'readwrite');
    const req = tx.objectStore(DB_STORE).add(item);
    req.onsuccess = () => r(req.result);
    tx.onerror = () => j(tx.error);
  });
}
async function idbGetAll() {
  const db = await idbOpen();
  return new Promise((r, j) => {
    const tx = db.transaction(DB_STORE, 'readonly');
    const req = tx.objectStore(DB_STORE).getAll();
    req.onsuccess = () => r(req.result);
    req.onerror = () => j(req.error);
  });
}
async function idbDelete(id) {
  const db = await idbOpen();
  return new Promise((r, j) => {
    const tx = db.transaction(DB_STORE, 'readwrite');
    tx.objectStore(DB_STORE).delete(id);
    tx.oncomplete = r;
    tx.onerror = () => j(tx.error);
  });
}
async function idbUpdate(item) {
  const db = await idbOpen();
  return new Promise((r, j) => {
    const tx = db.transaction(DB_STORE, 'readwrite');
    tx.objectStore(DB_STORE).put(item);
    tx.oncomplete = r;
    tx.onerror = () => j(tx.error);
  });
}
async function idbCount() {
  const db = await idbOpen();
  return new Promise((r, j) => {
    const tx = db.transaction(DB_STORE, 'readonly');
    const req = tx.objectStore(DB_STORE).count();
    req.onsuccess = () => r(req.result);
    req.onerror = () => j(req.error);
  });
}

// ---- install / activate --------------------------------------------------
self.addEventListener('install', (e) => {
  e.waitUntil(
    // Cache each shell asset INDEPENDENTLY. cache.addAll() is atomic — one
    // failed/slow fetch (e.g. sweetalert2) would leave the whole shell
    // uncached, which strands drivers offline (Swal undefined → Delivered
    // button does nothing). allSettled keeps every asset that did fetch.
    caches.open(CACHE_VERSION).then(cache =>
      Promise.allSettled(APP_SHELL.map(url => cache.add(url)))
    ).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE_VERSION).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// ---- fetch handler -------------------------------------------------------
self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  if (req.method === 'GET') {
    // Digital Asset Links (used by the Android TWA / APK wrapper to verify
    // domain ownership) must always come from the network — a stale cached
    // copy after a signing-key rotation would silently break verification
    // and bring back the Chrome URL bar inside the installed app.
    if (url.pathname === '/.well-known/assetlinks.json') {
      event.respondWith(fetch(req));
      return;
    }
    if (url.origin === location.origin) {
      event.respondWith(
        fetch(req).then(res => {
          if (res && res.status === 200 && res.type === 'basic') {
            const copy = res.clone();
            caches.open(CACHE_VERSION).then(c => c.put(req, copy)).catch(() => {});
          }
          return res;
        }).catch(() => caches.match(req).then(r => r || caches.match('driver-dashboard')))
      );
    }
    return;
  }

  if (req.method === 'POST' && QUEUEABLE_PATTERNS.some(p => p.test(url.pathname))) {
    event.respondWith(handleQueueablePost(req));
  }
});

// ---- queue / replay -------------------------------------------------------
async function serializeRequest(req) {
  const ct = (req.headers.get('content-type') || '').toLowerCase();
  const headers = [];
  // Preserve only safe / relevant headers. Skip content-type for multipart
  // because the boundary changes when we rebuild FormData.
  req.headers.forEach((value, key) => {
    if (key === 'content-type' && ct.startsWith('multipart/form-data')) return;
    headers.push([key, value]);
  });

  if (ct.startsWith('multipart/form-data')) {
    const fd = await req.clone().formData();
    const entries = [];
    for (const [k, v] of fd.entries()) {
      if (typeof v === 'string') {
        entries.push({ k, kind: 'string', v });
      } else {
        // v is a File/Blob — IDB stores Blobs natively.
        entries.push({ k, kind: 'file', name: v.name || 'upload', type: v.type || 'application/octet-stream', blob: v });
      }
    }
    return { url: req.url, method: 'POST', kind: 'multipart', entries, headers, queuedAt: Date.now() };
  }

  // application/json, x-www-form-urlencoded, text/plain — store body as text.
  const text = await req.clone().text();
  return { url: req.url, method: 'POST', kind: 'raw', body: text, contentType: ct, headers, queuedAt: Date.now() };
}

function buildRequestFromItem(item) {
  const headers = new Headers(item.headers || []);
  if (item.kind === 'multipart') {
    const fd = new FormData();
    for (const e of item.entries) {
      if (e.kind === 'string') {
        fd.append(e.k, e.v);
      } else {
        const blob = e.blob;
        const file = (typeof File !== 'undefined' && blob instanceof Blob)
          ? new File([blob], e.name, { type: e.type })
          : blob;
        fd.append(e.k, file, e.name);
      }
    }
    return new Request(item.url, { method: 'POST', headers, body: fd });
  }
  if (item.contentType) headers.set('content-type', item.contentType);
  return new Request(item.url, { method: 'POST', headers, body: item.body });
}

async function handleQueueablePost(req) {
  try {
    const res = await fetch(req.clone());
    // If the server returned 5xx, also queue so we don't lose work to a brief
    // upstream blip — but only for idempotent submissions (we tag them with
    // an idempotency_key, see driver-side helper).
    if (res.status >= 500) {
      try {
        const item = await serializeRequest(req);
        if (hasIdempotencyKey(item)) {
          await idbAdd(item);
          notifyClientsQueueChanged();
          if ('sync' in self.registration) {
            try { await self.registration.sync.register(SYNC_TAG); } catch (_) {}
          }
        }
      } catch (_) {}
    }
    return res;
  } catch (err) {
    // Offline. Serialize + queue and return a synthetic 202 "queued".
    try {
      const item = await serializeRequest(req);
      await idbAdd(item);
      notifyClientsQueueChanged();
      if ('sync' in self.registration) {
        try { await self.registration.sync.register(SYNC_TAG); } catch (_) {}
      }
      return new Response(JSON.stringify({
        status: 'queued',
        message: 'Saved offline — will sync when back online.'
      }), { status: 202, headers: { 'Content-Type': 'application/json' } });
    } catch (e) {
      return new Response(JSON.stringify({
        status: 'error',
        message: 'Offline and could not queue: ' + (e && e.message ? e.message : 'unknown')
      }), { status: 503, headers: { 'Content-Type': 'application/json' } });
    }
  }
}

function hasIdempotencyKey(item) {
  if (item.kind !== 'multipart') return false;
  return (item.entries || []).some(e => e.k === 'idempotency_key' && e.kind === 'string' && e.v);
}

self.addEventListener('sync', (event) => {
  if (event.tag === SYNC_TAG) event.waitUntil(replayQueue());
});
self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (data.type === 'pt-replay') {
    event.waitUntil(replayQueue());
  } else if (data.type === 'pt-queue-info') {
    event.waitUntil(idbCount().then(count => {
      if (event.source && event.source.postMessage) {
        event.source.postMessage({ type: 'pt-queue-info', count });
      }
    }));
  } else if (data.type === 'pt-queue-clear') {
    event.waitUntil(idbGetAll().then(items =>
      Promise.all(items.map(it => idbDelete(it.id)))
    ).then(() => notifyClientsQueueChanged()));
  }
});

async function notifyClientsQueueChanged() {
  try {
    const count = await idbCount();
    const clients = await self.clients.matchAll();
    clients.forEach(c => c.postMessage({ type: 'pt-queue-info', count }));
  } catch (_) {}
}

async function replayQueue() {
  const items = await idbGetAll();
  for (const item of items) {
    try {
      const replayReq = buildRequestFromItem(item);
      const res = await fetch(replayReq);
      if (res.ok || res.status === 409 /* idempotency duplicate */) {
        await idbDelete(item.id);
      } else if (res.status >= 400 && res.status < 500 && res.status !== 408 && res.status !== 429) {
        // 4xx (other than timeout/rate-limit): the server rejected it. Don't
        // retry forever — bump attempts and drop after 3 tries.
        const attempts = (item.attempts || 0) + 1;
        if (attempts >= 3) {
          await idbDelete(item.id);
        } else {
          item.attempts = attempts;
          item.lastError = res.status;
          await idbUpdate(item);
        }
      }
      // 5xx / network errors: leave in queue, try again next time.
    } catch (_) {
      // still offline — leave in queue
    }
  }
  notifyClientsQueueChanged();
}

// ---- Push notifications --------------------------------------------------
self.addEventListener('push', (event) => {
  let payload = { title: 'Pantrucks', body: 'New update', url: 'driver-dashboard' };
  if (event.data) {
    try { payload = Object.assign(payload, event.data.json()); } catch (_) { payload.body = event.data.text(); }
  }
  event.waitUntil(self.registration.showNotification(payload.title, {
    body: payload.body,
    icon: 'assets/images/logos/LogoFleet.png',
    badge: 'assets/images/logos/LogoFleet.png',
    data: { url: payload.url || 'driver-dashboard' },
    tag: payload.tag || 'pt-driver',
  }));
});
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || 'driver-dashboard';
  event.waitUntil(self.clients.matchAll({ type: 'window' }).then(clients => {
    for (const c of clients) { if (c.url.includes(url) && 'focus' in c) return c.focus(); }
    if (self.clients.openWindow) return self.clients.openWindow(url);
  }));
});
