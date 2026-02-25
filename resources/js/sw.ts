/// <reference lib="webworker" />
import { ExpirationPlugin } from 'workbox-expiration';
import { precacheAndRoute } from 'workbox-precaching';
import { registerRoute } from 'workbox-routing';
import { CacheFirst, NetworkFirst } from 'workbox-strategies';

declare let self: ServiceWorkerGlobalScope;

// Workbox injects the precache manifest here
precacheAndRoute(self.__WB_MANIFEST);

// Pages — NetworkFirst with 3s timeout
registerRoute(
    ({ request }) => request.mode === 'navigate',
    new NetworkFirst({
        cacheName: 'pages',
        networkTimeoutSeconds: 3,
        plugins: [
            new ExpirationPlugin({ maxAgeSeconds: 24 * 60 * 60 }),
        ],
    }),
);

// Inertia partial responses — NetworkFirst
registerRoute(
    ({ request }) => request.headers.get('X-Inertia') === 'true',
    new NetworkFirst({
        cacheName: 'inertia-responses',
        networkTimeoutSeconds: 3,
        plugins: [
            new ExpirationPlugin({ maxAgeSeconds: 60 * 60 }),
        ],
    }),
);

// Bunny font stylesheets — CacheFirst
registerRoute(
    /^https:\/\/fonts\.bunny\.net\/css/,
    new CacheFirst({
        cacheName: 'bunny-font-stylesheets',
        plugins: [
            new ExpirationPlugin({ maxAgeSeconds: 365 * 24 * 60 * 60 }),
        ],
    }),
);

// Bunny font files — CacheFirst
registerRoute(
    /^https:\/\/fonts\.bunny\.net\/.*\.(woff2?|ttf|otf|eot)$/,
    new CacheFirst({
        cacheName: 'bunny-font-files',
        plugins: [
            new ExpirationPlugin({
                maxAgeSeconds: 365 * 24 * 60 * 60,
                maxEntries: 30,
            }),
        ],
    }),
);

// Images — CacheFirst
registerRoute(
    /\.(?:png|jpg|jpeg|svg|gif|webp|ico)$/,
    new CacheFirst({
        cacheName: 'images',
        plugins: [
            new ExpirationPlugin({
                maxAgeSeconds: 30 * 24 * 60 * 60,
                maxEntries: 100,
            }),
        ],
    }),
);

// Push notification handler
self.addEventListener('push', (event: PushEvent) => {
    if (!event.data) return;

    const data = event.data.json() as {
        title?: string;
        body?: string;
        icon?: string;
        badge?: string;
        tag?: string;
        data?: { url?: string };
    };

    const title = data.title ?? 'Czirr Family';
    const options: NotificationOptions = {
        body: data.body,
        icon: data.icon ?? '/pwa-192x192.png',
        badge: data.badge ?? '/pwa-64x64.png',
        tag: data.tag,
        data: data.data,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

// Notification click handler
self.addEventListener('notificationclick', (event: NotificationEvent) => {
    event.notification.close();

    const url = (event.notification.data as { url?: string })?.url ?? '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            // Focus existing tab if found
            for (const client of clients) {
                if (client.url.includes(url) && 'focus' in client) {
                    return client.focus();
                }
            }
            // Open new tab
            return self.clients.openWindow(url);
        }),
    );
});
