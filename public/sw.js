self.addEventListener('push', (event) => {
    if (! event.data) {
        return;
    }

    const payload = event.data.json();

    event.waitUntil(
        self.registration.showNotification(payload.title || 'valueAFRIK', {
            body: payload.body,
            icon: payload.icon || '/favicon-48x48.png',
            badge: payload.badge,
            data: payload.data || {},
            tag: payload.tag,
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data && event.notification.data.url;

    if (! url) {
        return;
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }

            return self.clients.openWindow(url);
        })
    );
});
