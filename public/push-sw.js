// Imported by the generated Workbox service worker (see vite.config.ts).
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'Czirr Family', body: event.data && event.data.text() };
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'Czirr Family', {
            body: data.body,
            icon: data.icon || '/pwa-192x192.png',
            badge: '/pwa-64x64.png',
            tag: data.tag,
            data: { url: (data.data && data.data.url) || '/dashboard' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/dashboard';

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clients) => {
                for (const client of clients) {
                    if ('focus' in client) {
                        client.navigate(url);
                        return client.focus();
                    }
                }
                return self.clients.openWindow(url);
            }),
    );
});
