/*
 * Shama service worker.
 *
 * - Hashed static assets (Vite build, Livewire/Flux scripts with a version id,
 *   icons) are served cache-first, so cold starts of the home-screen app don't
 *   wait for the network to paint.
 * - Shopping list pages (the meal list and custom lists) are network-first,
 *   with the last good copy kept for when there is no connection in the shop.
 *   Signing out clears them (message "clear-pages"). Other HTML is never
 *   cached and falls back to /offline.html.
 * - Push: shows the notification, sets the app icon badge, and opens the
 *   right screen when tapped.
 * - Everything else (Livewire updates, POSTs, cross-origin) is left untouched.
 */

const VERSION = 'v2';
const STATIC_CACHE = `shama-static-${VERSION}`;
const PAGE_CACHE = `shama-pages-${VERSION}`;
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
                keys.filter((key) => key.startsWith('shama-') && key !== STATIC_CACHE && key !== PAGE_CACHE)
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

/** Screens that should open without a connection. */
function isOfflinePage(url) {
    return url.pathname === '/shopping-list' || url.pathname.startsWith('/shopping-list/lists/');
}

async function networkFirstPage(request) {
    const cache = await caches.open(PAGE_CACHE);
    const key = new URL(request.url).pathname + new URL(request.url).search;

    try {
        const response = await fetch(request);

        if (response.ok && ! response.redirected && (response.headers.get('content-type') || '').includes('text/html')) {
            cache.put(key, response.clone());
        }

        return response;
    } catch (error) {
        const cached = await cache.match(key) || await cache.match(new URL(request.url).pathname);
        if (cached) return cached;

        const offline = await (await caches.open(STATIC_CACHE)).match(OFFLINE_URL);
        return offline || Response.error();
    }
}

self.addEventListener('message', (event) => {
    if (event.data?.type === 'clear-pages') {
        event.waitUntil(caches.delete(PAGE_CACHE));
    }
});

self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { body: event.data?.text() };
    }

    const badge = typeof data.badge === 'number' && self.navigator.setAppBadge
        ? (data.badge > 0 ? self.navigator.setAppBadge(data.badge) : self.navigator.clearAppBadge())
        : Promise.resolve();

    event.waitUntil(Promise.all([
        badge.catch(() => {}),
        self.registration.showNotification(data.title || 'Shama', {
            body: data.body || '',
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            data: { url: data.url || '/' },
            tag: data.url || 'shama',
        }),
    ]));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/', self.location.origin).href;

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of windows) {
            if ('focus' in client) {
                await client.focus();
                client.navigate?.(target);

                return;
            }
        }

        await self.clients.openWindow(target);
    })());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Page loads and wire:navigate fetches of the shopping screens.
    if (isOfflinePage(url) && (request.mode === 'navigate' || request.headers.get('X-Livewire-Navigate'))) {
        event.respondWith(networkFirstPage(request));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkWithOfflineFallback(request));
        return;
    }

    if (isImmutableAsset(url)) {
        event.respondWith(cacheFirst(request));
    }
});
