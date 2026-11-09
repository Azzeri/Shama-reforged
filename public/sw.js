/*
 * Shama service worker.
 *
 * - Hashed static assets (Vite build, Livewire/Flux scripts with a version id,
 *   icons) are served cache-first, so cold starts of the home-screen app don't
 *   wait for the network to paint.
 * - HTML is never cached (it carries CSRF tokens and per-user data). Full page
 *   navigations go to the network and fall back to /offline.html when it fails.
 * - Everything else (Livewire updates, POSTs, cross-origin) is left untouched.
 */

const VERSION = 'v1';
const STATIC_CACHE = `shama-static-${VERSION}`;
const OFFLINE_URL = '/offline.html';

const PRECACHE = [
    OFFLINE_URL,
    '/icons/icon-192.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key.startsWith('shama-') && key !== STATIC_CACHE)
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

function isImmutableAsset(url) {
    if (url.pathname.startsWith('/build/assets/')) return true;
    if (url.pathname.startsWith('/icons/')) return true;
    // Livewire serves its scripts under a versioned prefix (/livewire-<hash>/...).
    if (/^\/livewire-[0-9a-f]+\//.test(url.pathname) && url.pathname.endsWith('.js')) return true;
    // Flux appends ?id=<hash> to its script URL.
    if (url.pathname.startsWith('/flux/') && url.searchParams.has('id')) return true;

    return false;
}

async function cacheFirst(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cached = await cache.match(request);
    if (cached) return cached;

    const response = await fetch(request);
    if (response.ok && response.type === 'basic') {
        cache.put(request, response.clone());
    }

    return response;
}

async function networkWithOfflineFallback(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const cache = await caches.open(STATIC_CACHE);
        return (await cache.match(OFFLINE_URL)) || Response.error();
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(networkWithOfflineFallback(request));
        return;
    }

    if (isImmutableAsset(url)) {
        event.respondWith(cacheFirst(request));
    }
});
