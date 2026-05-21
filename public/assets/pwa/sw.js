const CACHE_NAME = 'bdv-checkin-v1';
const ASSETS = [
  '/wp-content/plugins/convoca-enroll/public/assets/css/checkin.css',
];
const API_CACHE = 'bdv-api-v1';

// Install: cache static assets
self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS))
  );
});

// Activate: clean old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME && k !== API_CACHE).map((k) => caches.delete(k)))
    )
  );
});

// Fetch: network-first with offline queue for POST checkins
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // POST to admin-ajax.php = check-in attempt
  if (request.method === 'POST' && url.pathname.includes('admin-ajax.php')) {
    return event.respondWith(networkOrQueue(request));
  }

  // Static assets: cache-first
  if (request.method === 'GET' && ASSETS.some((a) => url.pathname.endsWith(a))) {
    return event.respondWith(caches.match(request).then((c) => c || fetch(request)));
  }

  // Everything else: network-first
  event.respondWith(
    fetch(request).catch(() => caches.match(request))
  );
});

async function networkOrQueue(request) {
  try {
    const response = await fetch(request.clone());
    // Cache successful responses
    if (response.ok) {
      const cache = await caches.open(API_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch (err) {
    // Offline: queue the request
    const body = await request.clone().text();
    const queued = await queueRequest(body);
    return new Response(JSON.stringify({
      success: true,
      queued: true,
      message: 'Check-in encolado. Se procesará cuando haya conexión.',
      queue_id: queued
    }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}

// ── IndexedDB queue ──────────────────────────────────────
const DB_NAME = 'bdv-checkin-queue';
const STORE_NAME = 'pending';
const DB_VERSION = 1;

function openDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = () => {
      req.result.createObjectStore(STORE_NAME, { autoIncrement: true });
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function queueRequest(body) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const id = tx.objectStore(STORE_NAME).add(body);
    tx.oncomplete = () => resolve(id.result);
    tx.onerror = () => reject(tx.error);
  });
}

async function getQueuedRequests() {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readonly');
    const all = tx.objectStore(STORE_NAME).getAll();
    const keys = tx.objectStore(STORE_NAME).getAllKeys();
    tx.oncomplete = () => {
      const items = [];
      for (let i = 0; i < all.result.length; i++) {
        items.push({ id: keys.result[i], body: all.result[i] });
      }
      resolve(items);
    };
    tx.onerror = () => reject(tx.error);
  });
}

async function removeQueuedRequest(id) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readwrite');
    tx.objectStore(STORE_NAME).delete(id);
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

// When back online, flush the queue
self.addEventListener('sync', (event) => {
  if (event.tag === 'bdv-flush-checkins') {
    event.waitUntil(flushQueue());
  }
});

async function flushQueue() {
  const items = await getQueuedRequests();
  for (const item of items) {
    try {
      const res = await fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: item.body
      });
      if (res.ok) {
        await removeQueuedRequest(item.id);
      }
    } catch (e) {
      // Will retry on next sync
      break;
    }
  }
}
